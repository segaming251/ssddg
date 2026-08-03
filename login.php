<?php
// ═══════════════════════════════════════════════════════
//  WEBRAT v2.0 — Login / Logout
// ═══════════════════════════════════════════════════════

session_start();
require_once __DIR__ . '/db.php';

if (isset($_SESSION['webrat_user'])) {
    header('Location: index.php');
    exit;
}

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $loginError = 'MISSING CREDENTIALS';
    } else {
        try {
            $db   = getDB2();
            $stmt = $db->prepare('SELECT password_hash FROM accounts WHERE username = ? AND is_active = 1 LIMIT 1');
            $stmt->execute([$username]);
            $row  = $stmt->fetch();

            if ($row && password_verify($password, $row['password_hash'])) {
                $db->prepare('UPDATE accounts SET last_login = NOW() WHERE username = ?')->execute([$username]);
                session_regenerate_id(true);
                $_SESSION['webrat_user'] = $username;
                header('Location: index.php');
                exit;
            } else {
                $loginError = 'INVALID USERNAME OR PASSWORD';
            }
        } catch (Exception $e) {
            error_log('[WEBRAT LOGIN] ' . $e->getMessage());
            $loginError = 'DATABASE ERROR — CHECK LOGS';
        }
    }
}

$savedUser = htmlspecialchars($_POST['username'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>ELSARAT v2.0 — Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --accent: #ff0090; --glow: rgba(255,0,144,0.4); }
    html, body { width: 100%; min-height: 100vh; background: #08050c; }
    body { font-family: 'Share Tech Mono','Courier New',monospace; color: #fff; overflow: hidden; }

    /* ── Background ── */
    #bg-layer   { position:fixed; inset:0; z-index:-2; background-color:#08050c; }
    #bg-overlay { position:fixed; inset:0; z-index:-1; background:rgba(8,5,18,.88); }

    /* ── Falling logos ── */
    #logo-canvas { position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
    .logo-item {
      position:absolute; width:72px; height:72px; object-fit:contain;
      will-change:transform; animation:fall-down linear infinite;
    }
    @keyframes fall-down {
      0%   { transform:translateY(-100px) rotate(0deg); }
      100% { transform:translateY(110vh)  rotate(360deg); }
    }

    /* ── Scanlines ── */
    .scan-lines {
      position:fixed; inset:0; z-index:1; pointer-events:none;
      background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,.06) 2px,rgba(0,0,0,.06) 4px);
      animation:scan-move 8s linear infinite;
    }
    @keyframes scan-move { from{background-position:0 0} to{background-position:0 40px} }

    /* ── Layout ── */
    .login-root {
      min-height:100vh; display:flex;
      align-items:center; justify-content:center;
      position:relative; z-index:2;
    }

    /* ── Login box ── */
    .login-box {
      position:relative; width:420px;
      background:rgba(8,5,18,.93);
      border:1px solid var(--accent);
      border-radius:20px;
      box-shadow:0 0 48px var(--glow), inset 0 0 80px rgba(0,0,0,.45);
      padding:40px 36px 32px;
      backdrop-filter:blur(6px);
      transition: border-color .3s, box-shadow .3s;
    }
    .corner {
      position:absolute; width:18px; height:18px;
      border-color:var(--accent); border-style:solid;
      transition: border-color .3s;
    }
    .corner.tl { top:10px;    left:10px;  border-width:2px 0 0 2px; border-radius:6px 0 0 0; }
    .corner.tr { top:10px;    right:10px; border-width:2px 2px 0 0; border-radius:0 6px 0 0; }
    .corner.bl { bottom:10px; left:10px;  border-width:0 0 2px 2px; border-radius:0 0 0 6px; }
    .corner.br { bottom:10px; right:10px; border-width:0 2px 2px 0; border-radius:0 0 6px 0; }

    .login-logo {
      font-size:26px; letter-spacing:4px; color:var(--accent);
      text-shadow:0 0 20px var(--glow);
      display:flex; align-items:center; gap:8px; margin-bottom:6px;
      transition: color .3s, text-shadow .3s;
    }
    .logo-inline {
      display: inline-block;
      width: 52px;
      height: 52px;
      vertical-align: middle;
      margin-left: -6px;
      position: relative;
      top: -2px;
      background-color: var(--accent);
      -webkit-mask: url('elsarat-logo.png') no-repeat center / contain;
              mask: url('elsarat-logo.png') no-repeat center / contain;
      opacity: 0.9;
      transition: background-color .3s;
    }
    .login-subtitle { font-size:11px; letter-spacing:3px; color:#444; margin-bottom:20px; }
    .login-divider {
      height:1px; background:linear-gradient(90deg,var(--accent),transparent);
      margin-bottom:24px; opacity:.4; transition: background .3s;
    }

    .login-field { margin-bottom:18px; }
    .login-label { display:block; font-size:10px; letter-spacing:2px; color:#555; margin-bottom:8px; }
    .login-input-wrap {
      display:flex; align-items:center;
      background:rgba(255,255,255,.03); border:1px solid #1a1a2e;
      border-radius:10px; overflow:hidden;
      transition:border-color .2s, box-shadow .2s;
    }
    .login-input-wrap:focus-within { border-color:var(--accent); box-shadow:0 0 10px var(--glow); }
    .login-field-icon { color:var(--accent); padding:0 10px; font-size:13px; flex-shrink:0; }
    .login-input {
      flex:1; background:none; border:none; color:#ddd;
      font-family:'Share Tech Mono',monospace; font-size:13px;
      padding:12px 12px 12px 0; outline:none; letter-spacing:1px;
    }
    .login-input::placeholder { color:#333; }
    .show-pass-btn {
      background:none; border:none; color:#555;
      cursor:pointer; padding:0 12px; font-size:14px; transition:color .2s;
    }
    .show-pass-btn:hover { color:var(--accent); }

    .login-error {
      background:rgba(192,57,43,.1); border:1px solid rgba(192,57,43,.3);
      border-radius:10px; color:#e74c3c; font-size:11px; letter-spacing:1px;
      padding:10px 14px; margin-bottom:14px;
      display:flex; align-items:center; gap:8px;
    }

    .login-btn {
      width:100%; padding:13px;
      background:rgba(255,0,144,.1); border:1px solid var(--accent);
      border-radius:10px; color:var(--accent);
      font-family:'Share Tech Mono',monospace; font-size:13px;
      letter-spacing:3px; cursor:pointer; transition:all .25s; margin-top:8px;
    }
    .login-btn:hover {
      box-shadow:0 0 24px var(--glow); transform:translateY(-1px);
    }
    .login-btn:active { transform:translateY(0); }

    .login-footer {
      display:flex; justify-content:space-between;
      margin-top:24px; padding-top:16px;
      border-top:1px solid rgba(255,255,255,.05);
      font-size:9px; letter-spacing:1px; color:#333;
    }

    /* ════════════════════════════════
       COLOR PICKER BUTTON + PANEL
    ════════════════════════════════ */
    #color-btn {
      position:fixed; top:20px; right:20px; z-index:100;
      width:42px; height:42px; border-radius:50%;
      background:rgba(8,5,18,.85);
      border:1.5px solid var(--accent);
      box-shadow:0 0 14px var(--glow);
      cursor:pointer; display:flex; align-items:center; justify-content:center;
      font-size:18px; transition:all .25s; backdrop-filter:blur(4px);
    }
    #color-btn:hover { transform:scale(1.1); box-shadow:0 0 22px var(--glow); }

    #color-panel {
      position:fixed; top:70px; right:16px; z-index:100;
      width:230px;
      background:rgba(8,5,18,.97);
      border:1px solid var(--accent);
      border-radius:14px; padding:18px 16px;
      box-shadow:0 0 30px var(--glow);
      backdrop-filter:blur(8px);
      display:none; flex-direction:column; gap:14px;
      transition: border-color .3s, box-shadow .3s;
    }
    #color-panel.open { display:flex; }

    .cp-title {
      font-size:10px; letter-spacing:3px; color:var(--accent);
      border-bottom:1px solid rgba(255,255,255,.06); padding-bottom:10px;
    }

    .cp-presets {
      display:grid; grid-template-columns:repeat(5,1fr); gap:8px;
    }
    .cp-swatch {
      width:32px; height:32px; border-radius:50%; cursor:pointer;
      border:2px solid transparent; transition:all .2s;
    }
    .cp-swatch:hover, .cp-swatch.active {
      border-color:#fff; transform:scale(1.15);
      box-shadow:0 0 10px currentColor;
    }

    .cp-custom {
      display:flex; align-items:center; gap:10px;
    }
    .cp-custom label { font-size:10px; letter-spacing:2px; color:#555; white-space:nowrap; }
    #custom-color {
      width:36px; height:30px; border:1px solid #333; border-radius:6px;
      background:none; cursor:pointer; padding:2px;
    }
    #custom-hex {
      flex:1; background:rgba(255,255,255,.04); border:1px solid #2a2a3e;
      border-radius:6px; color:#ccc; font-family:'Share Tech Mono',monospace;
      font-size:12px; padding:6px 8px; outline:none; letter-spacing:1px;
    }
    #custom-hex:focus { border-color:var(--accent); }

    ::-webkit-scrollbar { width:4px; }
    ::-webkit-scrollbar-thumb { background:var(--accent); border-radius:4px; }
  </style>
</head>
<body>
<div id="bg-layer"></div>
<div id="bg-overlay"></div>
<div id="logo-canvas"></div>
<div class="scan-lines"></div>

<!-- ── Color picker button ── -->
<button id="color-btn" onclick="togglePanel()" title="Đổi màu giao diện">🎨</button>

<!-- ── Color picker panel ── -->
<div id="color-panel">
  <div class="cp-title">ACCENT COLOR</div>

  <div class="cp-presets">
    <span class="cp-swatch" style="background:#ff0090" data-color="#ff0090" title="Pink"></span>
    <span class="cp-swatch" style="background:#00ffcc" data-color="#00ffcc" title="Cyan"></span>
    <span class="cp-swatch" style="background:#ff6600" data-color="#ff6600" title="Orange"></span>
    <span class="cp-swatch" style="background:#00aaff" data-color="#00aaff" title="Blue"></span>
    <span class="cp-swatch" style="background:#aa00ff" data-color="#aa00ff" title="Purple"></span>
    <span class="cp-swatch" style="background:#ffcc00" data-color="#ffcc00" title="Gold"></span>
    <span class="cp-swatch" style="background:#ff3333" data-color="#ff3333" title="Red"></span>
    <span class="cp-swatch" style="background:#00ff44" data-color="#00ff44" title="Green"></span>
    <span class="cp-swatch" style="background:#ff66cc" data-color="#ff66cc" title="Rose"></span>
    <span class="cp-swatch" style="background:#ffffff" data-color="#ffffff" title="White"></span>
  </div>

  <div class="cp-custom">
    <label>CUSTOM</label>
    <input type="color" id="custom-color" value="#ff0090" oninput="applyFromPicker(this.value)" />
    <input type="text"  id="custom-hex"   value="#ff0090" maxlength="7" placeholder="#rrggbb"
           oninput="applyFromHex(this.value)" />
  </div>
</div>

<div class="login-root">
  <div class="login-box" id="login-box">
    <span class="corner tl"></span>
    <span class="corner tr"></span>
    <span class="corner bl"></span>
    <span class="corner br"></span>

    <div class="login-logo">
      <span>&gt;_</span> ELSARAT <span class="logo-inline"></span>
    </div>
    <div class="login-subtitle">SECURE ADMIN ACCESS</div>
    <div class="login-divider"></div>

    <?php if ($loginError): ?>
    <div class="login-error">
      <span>✕</span> <?= htmlspecialchars($loginError) ?>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="login-field">
        <label class="login-label">USERNAME</label>
        <div class="login-input-wrap">
          <span class="login-field-icon">&gt;</span>
          <input class="login-input" type="text" name="username"
            placeholder="Enter username..." autofocus spellcheck="false"
            autocomplete="off" value="<?= $savedUser ?>" />
        </div>
      </div>

      <div class="login-field">
        <label class="login-label">PASSWORD</label>
        <div class="login-input-wrap">
          <span class="login-field-icon">*</span>
          <input class="login-input" type="password" name="password"
            id="passInput" placeholder="Enter password..."
            autocomplete="current-password" />
          <button type="button" class="show-pass-btn" id="toggleBtn" onclick="togglePass()">◉</button>
        </div>
      </div>

      <button class="login-btn" type="submit">[ LOGIN → ]</button>
    </form>

    <div class="login-footer">
      <span>SYS://ELSARAT.LOCAL</span>
      <span>Hoàng Chả _ Trần Anh Khoa</span>
    </div>
  </div>
</div>

<script>
/* ── Password toggle ── */
function togglePass() {
  const i = document.getElementById('passInput');
  const b = document.getElementById('toggleBtn');
  i.type = i.type === 'password' ? 'text' : 'password';
  b.textContent = i.type === 'password' ? '◉' : '◎';
}

/* ── Color picker panel ── */
function togglePanel() {
  document.getElementById('color-panel').classList.toggle('open');
}
document.addEventListener('click', function(e) {
  const panel = document.getElementById('color-panel');
  const btn   = document.getElementById('color-btn');
  if (!panel.contains(e.target) && e.target !== btn) {
    panel.classList.remove('open');
  }
});

/* ── Hex → CSS filter (tints white logo to target color) ── */
function hexToFilter(hex) {
  const r = parseInt(hex.slice(1,3),16)/255;
  const g = parseInt(hex.slice(3,5),16)/255;
  const b = parseInt(hex.slice(5,7),16)/255;
  const max = Math.max(r,g,b), min = Math.min(r,g,b);
  let h = 0, s = 0, l = (max+min)/2;
  if (max !== min) {
    const d = max - min;
    s = l > 0.5 ? d/(2-max-min) : d/(max+min);
    if (max===r)      h = (g-b)/d + (g<b?6:0);
    else if (max===g) h = (b-r)/d + 2;
    else              h = (r-g)/d + 4;
    h /= 6;
  }
  h = Math.round(h*360);
  s = Math.round(s*100);
  l = Math.round(l*100);
  // sepia base ≈ hue 35°, sat 70%, lum 40%
  const hRot = h - 35;
  const sat  = Math.min(9999, Math.round(s / 70 * 500));
  const brt  = Math.round(l / 40 * 100);
  return `brightness(0) sepia(1) hue-rotate(${hRot}deg) saturate(${sat}%) brightness(${brt}%)`;
}

/* ── Apply accent color ── */
function applyColor(hex) {
  if (!/^#[0-9a-fA-F]{6}$/.test(hex)) return;

  const r = parseInt(hex.slice(1,3),16);
  const g = parseInt(hex.slice(3,5),16);
  const b = parseInt(hex.slice(5,7),16);
  const glow = `rgba(${r},${g},${b},0.4)`;

  document.documentElement.style.setProperty('--accent', hex);
  document.documentElement.style.setProperty('--glow', glow);

  // Update login-btn background tint
  document.querySelector('.login-btn').style.background = `rgba(${r},${g},${b},0.1)`;

  // Sync inputs
  document.getElementById('custom-color').value = hex;
  document.getElementById('custom-hex').value   = hex;

  // Mark active swatch
  document.querySelectorAll('.cp-swatch').forEach(s => {
    s.classList.toggle('active', s.dataset.color.toLowerCase() === hex.toLowerCase());
  });

  localStorage.setItem('webrat_login_accent', hex);
}

function applyFromPicker(hex) {
  document.getElementById('custom-hex').value = hex;
  applyColor(hex);
}

function applyFromHex(val) {
  if (/^#[0-9a-fA-F]{6}$/.test(val)) {
    document.getElementById('custom-color').value = val;
    applyColor(val);
  }
}

/* Swatch clicks */
document.querySelectorAll('.cp-swatch').forEach(s => {
  s.addEventListener('click', () => applyColor(s.dataset.color));
});

/* Load saved color */
const saved = localStorage.getItem('webrat_login_accent');
if (saved) applyColor(saved);
else document.querySelector('.cp-swatch[data-color="#ff0090"]').classList.add('active');

/* ── Falling logos ── */
(function () {
  const canvas   = document.getElementById('logo-canvas');
  const LOGO_SRC = 'elsarat-logo.png';
  const COLS = 7, LOGO_SIZE = 64;
  const vw   = window.innerWidth || 1200;
  const spacing = vw / COLS;

  for (let col = 0; col < COLS; col++) {
    for (let row = 0; row < 3; row++) {
      const img = document.createElement('img');
      img.src       = LOGO_SRC;
      img.className = 'logo-item';
      img.draggable = false;

      const x    = spacing * col + spacing * 0.1 + Math.random() * (spacing * 0.8);
      const left = Math.min(x, vw - LOGO_SIZE - 10);
      const dur  = 10 + Math.random() * 8;
      const delay = -(Math.random() * dur);
      const opacity = 0.45 + Math.random() * 0.25;

      img.style.left             = left + 'px';
      img.style.width            = LOGO_SIZE + 'px';
      img.style.height           = LOGO_SIZE + 'px';
      img.style.opacity          = opacity;
      img.style.animationDuration = dur + 's';
      img.style.animationDelay   = delay + 's';

      canvas.appendChild(img);
    }
  }
})();
</script>
</body>
</html>
