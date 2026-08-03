<?php
// ═══════════════════════════════════════════════════════
//  WEBRAT v2.0 — Admin Chat (Tách riêng từ index.php)
// ═══════════════════════════════════════════════════════
session_start();
if (!isset($_SESSION['webrat_user'])) {
    header('Location: login.php');
    exit;
}
$authedUser = htmlspecialchars($_SESSION['webrat_user']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
  <title>ADMIN CHAT — ELSARAT</title>
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root { --accent: #ff0090; --glow: rgba(255,0,144,0.4); --bg-alpha: 0.88; }
    html, body { width:100%; height:100%; overflow:hidden; background:#08050c; font-family:'Share Tech Mono','Courier New',monospace; color:#fff; }

    /* ── Layout ── */
    .chat-page { display:flex; flex-direction:column; height:100vh; height:100dvh; position:relative; }
    #bg-layer { position:fixed; inset:0; z-index:-2; background:#08050c; }
    #bg-overlay { position:fixed; inset:0; z-index:-1; background:rgba(8,5,18,var(--bg-alpha)); transition:background .3s; }
    .scan-lines { position:fixed; inset:0; z-index:0; pointer-events:none; background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,0,0,.05) 2px,rgba(0,0,0,.05) 4px); }

    /* ── Header ── */
    .chat-header {
      display:flex; align-items:center; gap:12px;
      padding:0 14px; height:54px; flex-shrink:0;
      background:rgba(5,3,12,.96);
      border-bottom:1px solid color-mix(in srgb,var(--accent) 30%,transparent);
      box-shadow:0 4px 24px rgba(0,0,0,.5);
      position:relative; z-index:10;
    }
    .back-btn {
      background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
      color:#888; cursor:pointer; font-size:12px; font-family:inherit;
      padding:6px 12px; border-radius:8px; transition:all .2s;
      display:flex; align-items:center; gap:5px; flex-shrink:0; letter-spacing:1px;
      white-space:nowrap;
    }
    .back-btn:hover { border-color:var(--accent); color:var(--accent); background:color-mix(in srgb,var(--accent) 8%,transparent); }
    .header-title { flex:1; display:flex; align-items:center; gap:8px; min-width:0; }
    .title-dot { width:8px; height:8px; border-radius:50%; background:var(--accent); box-shadow:0 0 8px var(--accent); animation:blink 2s infinite; flex-shrink:0; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.35} }
    .title-text { font-size:13px; letter-spacing:3px; color:var(--accent); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .user-badge {
      display:flex; align-items:center; gap:7px;
      background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.06);
      padding:4px 10px 4px 4px; border-radius:20px; white-space:nowrap; flex-shrink:0;
      cursor:pointer; transition:all .2s; -webkit-tap-highlight-color:transparent;
    }
    .user-badge:hover { border-color:var(--accent); background:color-mix(in srgb,var(--accent) 8%,transparent); }
    .ub-avatar {
      width:26px; height:26px; border-radius:50%; flex-shrink:0;
      background:color-mix(in srgb,var(--accent) 25%,transparent);
      border:1px solid color-mix(in srgb,var(--accent) 35%,transparent);
      overflow:hidden; display:flex; align-items:center; justify-content:center;
      font-size:11px; color:var(--accent);
    }
    .ub-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
    .ub-name { font-size:10px; letter-spacing:1px; color:#888; max-width:90px; overflow:hidden; text-overflow:ellipsis; }

    /* ── Messages ── */
    .chat-messages {
      flex:1; overflow-y:auto; padding:14px 12px;
      display:flex; flex-direction:column; gap:10px;
      min-height:0; position:relative; z-index:1;
    }
    .chat-messages::-webkit-scrollbar { width:3px; }
    .chat-messages::-webkit-scrollbar-thumb { background:var(--accent); border-radius:3px; }

    /* ── Message bubble ── */
    .msg { display:flex; gap:8px; align-items:flex-end; max-width:80%; }
    .msg.mine  { flex-direction:row-reverse; align-self:flex-end; }
    .msg.theirs { align-self:flex-start; }
    .msg-avatar {
      width:32px; height:32px; border-radius:50%; flex-shrink:0;
      background:color-mix(in srgb,var(--accent) 20%,transparent);
      border:1px solid color-mix(in srgb,var(--accent) 35%,transparent);
      display:flex; align-items:center; justify-content:center;
      font-size:13px; color:var(--accent); overflow:hidden; margin-bottom:2px;
    }
    .msg-avatar img { width:100%; height:100%; object-fit:cover; }
    .msg.mine .msg-avatar { border-color:rgba(255,255,255,.12); background:rgba(255,255,255,.06); color:#888; }
    .msg-body { display:flex; flex-direction:column; gap:3px; max-width:100%; min-width:0; }
    .msg-header { display:flex; align-items:baseline; gap:6px; }
    .msg.mine .msg-header { flex-direction:row-reverse; }
    .msg-sender { font-size:10px; color:var(--accent); letter-spacing:.5px; }
    .msg.mine .msg-sender { color:#666; }
    .msg-time { font-size:9px; color:#444; }
    /* Text bubble */
    .msg-bubble {
      background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08);
      border-radius:14px 14px 14px 4px; padding:9px 13px;
      font-size:13px; color:#ddd; line-height:1.55; word-break:break-word;
    }
    .msg.mine .msg-bubble {
      background:color-mix(in srgb,var(--accent) 12%,transparent);
      border-color:color-mix(in srgb,var(--accent) 28%,transparent);
      color:#eee; border-radius:14px 14px 4px 14px;
    }
    /* Image */
    .msg-img { max-width:220px; max-height:200px; border-radius:10px; cursor:pointer; border:1px solid rgba(255,255,255,.1); display:block; object-fit:cover; }
    /* Video */
    .msg-video { max-width:220px; border-radius:10px; border:1px solid rgba(255,255,255,.1); display:block; }
    /* Voice */
    .msg-voice {
      display:flex; align-items:center; gap:10px; padding:9px 12px;
      background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08);
      border-radius:14px; min-width:190px; max-width:240px;
    }
    .msg.mine .msg-voice { background:color-mix(in srgb,var(--accent) 12%,transparent); border-color:color-mix(in srgb,var(--accent) 28%,transparent); }
    .voice-play {
      width:32px; height:32px; border-radius:50%; flex-shrink:0;
      background:color-mix(in srgb,var(--accent) 18%,transparent);
      border:1px solid var(--accent); color:var(--accent);
      cursor:pointer; font-family:inherit; font-size:11px;
      display:flex; align-items:center; justify-content:center; transition:all .2s;
    }
    .voice-play:hover { background:color-mix(in srgb,var(--accent) 32%,transparent); }
    .voice-bars { flex:1; height:22px; display:flex; align-items:center; gap:2px; overflow:hidden; }
    .voice-bar { width:3px; border-radius:3px; background:color-mix(in srgb,var(--accent) 50%,transparent); flex-shrink:0; }
    .voice-dur { font-size:10px; color:#555; flex-shrink:0; min-width:28px; text-align:right; }
    /* Empty */
    .msg-empty { color:#444; font-size:11px; letter-spacing:1.5px; text-align:center; padding:40px 0; }

    /* ── Attachment preview ── */
    #attachPreview {
      display:none; flex-shrink:0; position:relative; z-index:5;
      background:rgba(5,3,12,.98); border-top:1px solid color-mix(in srgb,var(--accent) 18%,transparent);
      padding:10px 14px; align-items:center; gap:10px;
    }
    #attachPreview.show { display:flex; }
    #apImg  { max-height:60px; max-width:100px; border-radius:6px; display:none; border:1px solid rgba(255,255,255,.1); }
    #apVid  { max-height:60px; max-width:100px; border-radius:6px; display:none; }
    .ap-info { flex:1; min-width:0; }
    .ap-name { font-size:11px; color:#bbb; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ap-size { font-size:9px; color:#555; margin-top:2px; letter-spacing:1px; }
    .ap-rm   { background:none; border:1px solid rgba(255,60,60,.35); color:#e66; font-size:11px; padding:4px 10px; border-radius:6px; cursor:pointer; font-family:inherit; transition:all .2s; }
    .ap-rm:hover { border-color:#f44; color:#f44; }

    /* ── Recording indicator ── */
    #recIndicator {
      display:none; flex-shrink:0; position:relative; z-index:5;
      background:rgba(5,3,12,.98); border-top:1px solid rgba(255,30,30,.35);
      padding:10px 14px; align-items:center; gap:10px;
    }
    #recIndicator.show { display:flex; }
    .rec-dot { width:10px; height:10px; border-radius:50%; background:#f00; box-shadow:0 0 8px #f00; animation:recblink 0.8s infinite; flex-shrink:0; }
    @keyframes recblink { 0%,100%{opacity:1} 50%{opacity:.15} }
    .rec-label { font-size:11px; color:#f88; letter-spacing:2px; flex:1; }
    .rec-timer { font-size:14px; color:#f55; letter-spacing:2px; font-weight:bold; }
    .rec-hint  { font-size:9px; color:#555; letter-spacing:1px; }

    /* ── Input bar ── */
    .input-bar {
      display:flex; align-items:center; gap:8px;
      padding:10px 12px;
      background:rgba(5,3,12,.97);
      border-top:1px solid color-mix(in srgb,var(--accent) 14%,transparent);
      flex-shrink:0; position:relative; z-index:5;
    }
    .act-btn {
      width:40px; height:40px; border-radius:10px; flex-shrink:0;
      background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.07);
      color:#666; font-size:17px; cursor:pointer; font-family:inherit;
      display:flex; align-items:center; justify-content:center; transition:all .2s;
      -webkit-user-select:none; user-select:none; -webkit-tap-highlight-color:transparent;
      position:relative;
    }
    .act-btn:hover { border-color:var(--accent); color:var(--accent); background:color-mix(in srgb,var(--accent) 8%,transparent); }
    #micBtn.recording {
      background:rgba(255,0,0,.2) !important; border-color:#f00 !important;
      color:#f55 !important; box-shadow:0 0 18px rgba(255,0,0,.35) !important;
      animation:micpulse .6s infinite;
    }
    @keyframes micpulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.1)} }
    .chat-input {
      flex:1; min-width:0;
      background:rgba(0,0,0,.4); border:1px solid rgba(255,255,255,.08);
      color:#ddd; font-family:inherit; font-size:13px;
      padding:10px 13px; outline:none; border-radius:10px; transition:border-color .2s;
    }
    .chat-input:focus { border-color:color-mix(in srgb,var(--accent) 45%,transparent); color:#fff; }
    .chat-input::placeholder { color:#444; }
    .send-btn {
      width:40px; height:40px; border-radius:10px; flex-shrink:0;
      background:color-mix(in srgb,var(--accent) 15%,transparent);
      border:1px solid color-mix(in srgb,var(--accent) 40%,transparent);
      color:var(--accent); font-size:15px; cursor:pointer; font-family:inherit;
      display:flex; align-items:center; justify-content:center; transition:all .2s;
    }
    .send-btn:hover { background:color-mix(in srgb,var(--accent) 28%,transparent); box-shadow:0 0 14px color-mix(in srgb,var(--accent) 28%,transparent); }

    /* ── Lightbox ── */
    #lightbox { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.93); align-items:center; justify-content:center; }
    #lightbox.show { display:flex; }
    #lbImg { max-width:94vw; max-height:94vh; object-fit:contain; border-radius:8px; }
    .lb-close { position:fixed; top:14px; right:14px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.18); color:#ccc; font-size:16px; cursor:pointer; width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-family:inherit; transition:all .2s; }
    .lb-close:hover { border-color:var(--accent); color:var(--accent); }

    /* ── Avatar wrapper (clickable) ── */
    .msg-av-wrap {
      position:relative; flex-shrink:0; cursor:pointer;
      display:inline-flex; align-items:flex-end; margin-bottom:2px;
    }
    .msg-av-wrap:hover .msg-avatar { opacity:.82; }

    /* ── Avatar decoration overlay (chat messages) ── */
    .av-deco-chat {
      position:absolute; pointer-events:none; z-index:10;
      top:50%; left:50%;
      width:150%; height:150%;
      transform:translate(-50%,-50%);
    }
    .av-deco-chat img { width:100%; height:100%; object-fit:contain; }

    /* ── Avatar decoration overlay (profile popup) ── */
    .pp-av-deco {
      position:absolute; pointer-events:none; z-index:10;
      top:50%; left:50%;
      width:150%; height:150%;
      transform:translate(-50%,-50%);
    }
    .pp-av-deco img { width:100%; height:100%; object-fit:contain; }

    /* ── Profile Popup ── */
    #profilePopup {
      display:none; position:fixed; inset:0; z-index:9990;
      align-items:center; justify-content:center;
      background:rgba(0,0,0,.65); backdrop-filter:blur(8px);
    }
    #profilePopup.show { display:flex; }
    .pp-card {
      width:340px; max-width:92vw;
      border-radius:16px; overflow:hidden;
      background:#18101f;
      border:1px solid rgba(255,255,255,.07);
      box-shadow:0 8px 48px rgba(0,0,0,.8);
      position:relative; animation:ppIn .2s cubic-bezier(.22,1,.36,1);
      max-height:90dvh; overflow-y:auto;
    }
    @keyframes ppSlideUp { from{transform:translateY(100%)} to{transform:translateY(0)} }
    @keyframes ppIn { from{opacity:0;transform:scale(.93) translateY(10px)} to{opacity:1;transform:scale(1) translateY(0)} }
    .pp-close {
      position:absolute; top:10px; right:10px; z-index:20;
      width:32px; height:32px; border-radius:50%;
      background:rgba(0,0,0,.6); border:1px solid rgba(255,255,255,.15);
      color:#ccc; font-size:13px; cursor:pointer; font-family:inherit;
      display:flex; align-items:center; justify-content:center; transition:all .2s;
    }
    .pp-close:hover { border-color:var(--accent); color:var(--accent); }
    .pp-cover {
      width:100%; height:120px; position:relative; overflow:hidden; flex-shrink:0;
      background:linear-gradient(135deg,#2d0b50 0%,#5a1080 45%,#c060e0 100%);
    }
    .pp-cover img { width:100%; height:100%; object-fit:cover; display:block; }
    .pp-body { padding:0 20px 32px; display:flex; flex-direction:column; }
    .pp-top-row {
      display:flex; align-items:flex-end;
      margin-top:-44px; margin-bottom:16px;
    }
    .pp-av-wrap { position:relative; width:88px; height:88px; flex-shrink:0; }
    .pp-av-inner {
      width:88px; height:88px; border-radius:50%;
      border:4px solid #18101f;
      background:color-mix(in srgb,var(--accent) 20%,#1a0030);
      overflow:hidden; display:flex; align-items:center; justify-content:center;
      font-size:34px; color:var(--accent);
      box-shadow:0 4px 18px rgba(0,0,0,.65);
    }
    .pp-av-inner img { width:100%; height:100%; object-fit:cover; display:block; }
    .pp-name { font-size:20px; letter-spacing:.4px; color:#111; font-weight:bold; margin-bottom:5px; line-height:1.2; }
    .pp-username { font-size:13px; color:rgba(0,0,0,.62); letter-spacing:.5px; margin-bottom:2px; }
    .pp-divider { height:1px; background:rgba(0,0,0,.12); margin:18px 0; }
    .pp-section-label { font-size:11px; color:rgba(0,0,0,.58); letter-spacing:2px; margin-bottom:10px; text-transform:uppercase; }
    .pp-bio { font-size:13px; color:#111; line-height:1.75; word-break:break-word; }
    .pp-bio a { color:#1a6bff; text-decoration:underline; word-break:break-all; }
    .pp-bio-empty { font-size:12px; color:rgba(0,0,0,.28); font-style:italic; }
    .pp-loading { text-align:center; padding:28px 0 10px; font-size:11px; color:rgba(0,0,0,.4); letter-spacing:2px; }
    .pp-extra { padding-top:8px; display:flex; flex-direction:column; gap:10px; }
    .pp-stat-row { display:flex; gap:20px; }
    .pp-stat { display:flex; flex-direction:column; gap:2px; }
    .pp-stat-val { font-size:16px; color:#111; font-weight:bold; letter-spacing:.5px; }
    .pp-stat-label { font-size:11px; color:rgba(0,0,0,.58); letter-spacing:2px; text-transform:uppercase; }

    /* ── Toast ── */
    #toast { display:none; position:fixed; bottom:90px; left:50%; transform:translateX(-50%); background:rgba(20,10,30,.97); border:1px solid rgba(255,60,60,.4); color:#f88; font-size:11px; padding:10px 18px; border-radius:10px; z-index:9998; letter-spacing:1px; max-width:88vw; text-align:center; }
  </style>
</head>
<body>
<div id="bg-layer"></div>
<div id="bg-overlay"></div>
<div class="scan-lines"></div>
<div id="toast"></div>

<!-- Lightbox -->
<div id="lightbox" onclick="closeLightbox()">
  <button class="lb-close" onclick="event.stopPropagation();closeLightbox()">✕</button>
  <img id="lbImg" src="" alt="img" onclick="event.stopPropagation()"/>
</div>

<!-- Profile Popup -->
<div id="profilePopup" onclick="closeProfilePopup()">
  <div class="pp-card" onclick="event.stopPropagation()">
    <button class="pp-close" onclick="closeProfilePopup()">✕</button>
    <div class="pp-cover" id="ppCover">
      <img id="ppCoverImg" src="" alt="" style="display:none"/>
    </div>
    <div class="pp-body">
      <div class="pp-top-row">
        <div class="pp-av-wrap">
          <div class="pp-av-inner" id="ppAvInner">?</div>
        </div>
      </div>
      <div class="pp-loading" id="ppLoading">[ ĐANG TẢI... ]</div>
      <div id="ppContent" style="display:none">
        <div class="pp-name" id="ppName"></div>
        <div class="pp-username" id="ppUsername"></div>
        <div class="pp-divider"></div>
        <div class="pp-section-label">TIỂU SỬ</div>
        <div id="ppBio"></div>
        <div class="pp-extra">
          <div class="pp-divider"></div>
          <div class="pp-section-label">THÔNG TIN</div>
          <div class="pp-stat-row">
            <div class="pp-stat">
              <span class="pp-stat-val" id="ppStatJoined">—</span>
              <span class="pp-stat-label">THAM GIA</span>
            </div>
            <div class="pp-stat">
              <span class="pp-stat-val" id="ppStatRole">MEMBER</span>
              <span class="pp-stat-label">VAI TRÒ</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="chat-page">

  <!-- Header -->
  <div class="chat-header">
    <button class="back-btn" onclick="location.href='index.php'">← BACK</button>
    <div class="header-title">
      <span class="title-dot"></span>
      <span class="title-text">ADMIN_CHAT</span>
    </div>
    <div class="user-badge" id="myBadge" onclick="showUserProfile(CHAT_ME)" title="Xem profile của bạn">
      <div class="ub-avatar" id="myBadgeAvatar"><?= strtoupper(substr($authedUser, 0, 1)) ?></div>
      <span class="ub-name" id="myBadgeName"><?= $authedUser ?></span>
    </div>
  </div>

  <!-- Messages -->
  <div class="chat-messages" id="chatMessages">
    <div class="msg-empty">[ ĐANG TẢI... ]</div>
  </div>

  <!-- Recording indicator (shown while holding mic) -->
  <div id="recIndicator">
    <span class="rec-dot"></span>
    <span class="rec-label">GHI ÂM...</span>
    <span class="rec-timer" id="recTimer">0:00</span>
    <span class="rec-hint">THẢ ĐỂ GỬI</span>
  </div>

  <!-- Attachment preview (shown when file selected) -->
  <div id="attachPreview">
    <img  id="apImg"  src="" alt="preview"/>
    <video id="apVid" muted  playsinline></video>
    <div class="ap-info">
      <div class="ap-name" id="apName">file</div>
      <div class="ap-size" id="apSize">0 KB</div>
    </div>
    <button class="ap-rm" onclick="clearAttach()">✕ HỦY</button>
  </div>

  <!-- Input bar -->
  <div class="input-bar">
    <!-- Attach button -->
    <button class="act-btn" onclick="document.getElementById('fileInput').click()" title="Gửi ảnh / video">
      📎
      <input type="file" id="fileInput" accept="image/*,video/*" style="display:none" onchange="handleFile(this)"/>
    </button>

    <input type="text" class="chat-input" id="chatInput" placeholder="Nhập tin nhắn..." maxlength="1000" autocomplete="off"/>

    <!-- Mic (giữ để ghi âm) -->
    <button class="act-btn" id="micBtn" title="Giữ để ghi âm voice">🎤</button>

    <button class="send-btn" onclick="sendMsg()" title="Gửi">➤</button>
  </div>

</div><!-- .chat-page -->

<script>
// ═══════════════════════════════════════════════════════
//  CHAT.PHP — Client-side logic
// ═══════════════════════════════════════════════════════
const CHAT_ME = <?= json_encode($authedUser) ?>;
let chatLastId = 0;
let MY_PROFILE = { nickname: null, avatar: null, avatar_deco_url: null, avatar_deco_settings: null };

// ── Apply settings from localStorage (accent, background, opacity) ────
(function () {
  try {
    const raw = localStorage.getItem('webrat_settings');
    const s = raw ? JSON.parse(raw) : {};

    // Accent color
    if (s.accent && /^#[0-9a-fA-F]{6}$/.test(s.accent)) {
      const hex = s.accent;
      const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
      document.documentElement.style.setProperty('--accent', hex);
      document.documentElement.style.setProperty('--glow', `rgba(${r},${g},${b},0.4)`);
    }

    // Background overlay opacity (0–100 → 0–1)
    // Set directly on the element (bypasses CSS variable, works reliably)
    const alpha = s.opacity !== undefined
      ? Math.min(100, Math.max(0, Number(s.opacity))) / 100
      : 0.88;
    const bgOverlay = document.getElementById('bg-overlay');
    if (bgOverlay) bgOverlay.style.background = `rgba(8,5,18,${alpha.toFixed(2)})`;

    // Background image / gradient / color
    // Keys used by index.php: bgImage, bgGradient, bgColor
    const bgLayer = document.getElementById('bg-layer');
    if (bgLayer) {
      if (s.bgImage) {
        bgLayer.style.backgroundImage    = `url(${s.bgImage})`;
        bgLayer.style.backgroundSize     = 'cover';
        bgLayer.style.backgroundPosition = 'center';
        bgLayer.style.backgroundColor    = '';
      } else if (s.bgGradient) {
        bgLayer.style.backgroundImage = s.bgGradient;
        bgLayer.style.backgroundColor = s.bgColor || '#08050c';
      } else {
        bgLayer.style.backgroundImage = '';
        bgLayer.style.backgroundColor = '#08050c';
      }
    }
  } catch {}
})();

// ── Helpers ───────────────────────────────────────────
function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtTime(dt) { return dt ? dt.slice(11,16) : ''; }
function fmtBytes(b) {
  if (!b) return '0 B';
  if (b < 1024) return b + ' B';
  if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
  return (b/1048576).toFixed(1) + ' MB';
}
function scrollBottom(smooth) {
  const el = document.getElementById('chatMessages');
  if (el) el.scrollTo({ top: el.scrollHeight, behavior: smooth ? 'smooth' : 'instant' });
}
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.style.display = 'block';
  clearTimeout(t._t);
  t._t = setTimeout(() => t.style.display = 'none', 4000);
}

// ── Load profile (nickname + avatar + deco) ───────────
async function loadProfile() {
  try {
    const res  = await fetch('api.php?action=get_profile');
    const json = await res.json();
    if (json.status === 'ok') {
      MY_PROFILE.nickname            = json.data.nickname            || null;
      MY_PROFILE.avatar              = json.data.avatar              || null;
      MY_PROFILE.avatar_deco_url     = json.data.avatar_deco_url     || null;
      MY_PROFILE.avatar_deco_settings = json.data.avatar_deco_settings || null;
      // Update header badge
      const badgeName   = document.getElementById('myBadgeName');
      const badgeAvatar = document.getElementById('myBadgeAvatar');
      if (badgeName && MY_PROFILE.nickname) {
        badgeName.textContent = MY_PROFILE.nickname;
      }
      if (badgeAvatar && MY_PROFILE.avatar) {
        badgeAvatar.innerHTML = `<img src="${MY_PROFILE.avatar}" alt="av" onerror="this.style.display='none'"/>`;
      }
    }
  } catch {}
}

// ── Render one message ────────────────────────────────
function renderMsg(msg) {
  const isMine = msg.sender === CHAT_ME;
  const wrap = document.createElement('div');
  wrap.className = 'msg ' + (isMine ? 'mine' : 'theirs');

  const nickname = msg.nickname || (isMine ? MY_PROFILE.nickname : null);
  const avatar   = msg.avatar   || (isMine ? MY_PROFILE.avatar   : null);
  const dname    = nickname ? esc(nickname) : esc(msg.sender);
  const t        = fmtTime(msg.sent_at);
  const uid      = 'a' + (msg.id || Date.now()) + '_' + Math.random().toString(36).slice(2,6);

  // Avatar (clickable → profile popup)
  const sender   = msg.sender || '';
  const decoUrl  = msg.avatar_deco_url || (isMine ? MY_PROFILE.avatar_deco_url : null) || '';
  const decoSettings = msg.avatar_deco_settings || (isMine ? MY_PROFILE.avatar_deco_settings : null) || '';
  let decoEnabled = false, decoSize = 150, decoX = 0, decoY = 0;
  if (decoUrl) {
    try {
      const ds = JSON.parse(decoSettings || '{}');
      decoEnabled = ds.enabled !== false; // default enabled if URL exists
      decoSize    = Number(ds.size || 150);
      decoX       = Number(ds.x   || 0);
      decoY       = Number(ds.y   || 0);
    } catch { decoEnabled = true; }
  }
  const decoOverlay = (decoUrl && decoEnabled)
    ? `<div class="av-deco-chat" style="width:${decoSize}%;height:${decoSize}%;transform:translate(calc(-50% + ${decoX}px),calc(-50% + ${decoY}px))"><img src="${esc(decoUrl)}" alt="" crossorigin="anonymous"/></div>`
    : '';
  const avInner = avatar
    ? `<div class="msg-avatar"><img src="${esc(avatar)}" alt="av" onerror="this.style.display='none'"/></div>`
    : `<div class="msg-avatar">${esc((nickname||sender||'?')[0]).toUpperCase()}</div>`;
  const avHtml  = `<div class="msg-av-wrap" onclick="showUserProfile('${esc(sender)}')" data-user="${esc(sender)}">${avInner}${decoOverlay}</div>`;

  // Content based on type
  const type  = msg.type  || 'text';
  const media = msg.media || '';
  let content = '';

  if (type === 'image' && media) {
    content = `<img class="msg-img" src="${esc(media)}" alt="img"
      onclick="openLightbox('${esc(media)}')"
      onerror="this.outerHTML='<span style=color:#f55;font-size:11px>[ảnh lỗi]</span>'"/>`;
  } else if (type === 'video' && media) {
    content = `<video class="msg-video" src="${esc(media)}" controls preload="metadata"
      onerror="this.outerHTML='<span style=color:#f55;font-size:11px>[video lỗi]</span>'"></video>`;
  } else if (type === 'voice' && media) {
    // Pseudo waveform bars
    let bars = '';
    for (let i = 0; i < 18; i++) {
      const h = 4 + Math.abs(Math.sin(i * 0.7 + parseInt(msg.id||0) * 0.3)) * 14 + (i % 3 === 0 ? 3 : 0);
      bars += `<div class="voice-bar" style="height:${Math.min(22, Math.max(4, Math.round(h)))}px"></div>`;
    }
    content = `<div class="msg-voice">
      <button class="voice-play" id="pb_${uid}" onclick="toggleAudio('${uid}',this)">▶</button>
      <div class="voice-bars">${bars}</div>
      <span class="voice-dur" id="dur_${uid}">—</span>
      <audio id="${uid}" src="${esc(media)}" preload="metadata"
        onloadedmetadata="setVoiceDur('${uid}',this.duration)"
        onended="document.getElementById('pb_${uid}').textContent='▶'"></audio>
    </div>`;
  } else {
    content = `<div class="msg-bubble">${esc(msg.message||'').replace(/\n/g,'<br>')}</div>`;
  }

  wrap.innerHTML = `${avHtml}<div class="msg-body">
    <div class="msg-header">
      <span class="msg-sender">${dname}</span>
      <span class="msg-time">${esc(t)}</span>
    </div>${content}
  </div>`;
  return wrap;
}

function setVoiceDur(id, dur) {
  const el = document.getElementById('dur_' + id);
  if (!el || !isFinite(dur)) return;
  const m = Math.floor(dur/60), s = Math.floor(dur%60);
  el.textContent = `${m}:${s.toString().padStart(2,'0')}`;
}
function toggleAudio(id, btn) {
  const a = document.getElementById(id);
  if (!a) return;
  if (a.paused) { a.play(); btn.textContent = '⏸'; }
  else          { a.pause(); btn.textContent = '▶'; }
}
function openLightbox(src) {
  document.getElementById('lbImg').src = src;
  document.getElementById('lightbox').classList.add('show');
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('show');
}

// ── Add message to DOM ────────────────────────────────
function appendMsg(msg, smooth) {
  const container = document.getElementById('chatMessages');
  const empty = container.querySelector('.msg-empty');
  if (empty) empty.remove();
  container.appendChild(renderMsg(msg));
  chatLastId = Math.max(chatLastId, parseInt(msg.id)||0);
  scrollBottom(smooth);
}

// ── Load full history ─────────────────────────────────
async function loadHistory() {
  try {
    const res  = await fetch('api.php?action=get_chat');
    const json = await res.json();
    const container = document.getElementById('chatMessages');
    container.innerHTML = '';
    if (json.status !== 'ok' || !Array.isArray(json.data) || json.data.length === 0) {
      container.innerHTML = '<div class="msg-empty">[ CHƯA CÓ TIN NHẮN ]</div>';
      return;
    }
    json.data.forEach(msg => {
      container.appendChild(renderMsg(msg));
      chatLastId = Math.max(chatLastId, parseInt(msg.id)||0);
    });
    scrollBottom(false);
  } catch {}
}

// ── Poll new messages ─────────────────────────────────
async function pollNew() {
  if (chatLastId === 0) return;
  try {
    const res  = await fetch('api.php?action=get_chat&since=' + chatLastId);
    const json = await res.json();
    if (json.status !== 'ok' || !Array.isArray(json.data) || json.data.length === 0) return;
    json.data.forEach(msg => appendMsg(msg, true));
  } catch {}
}

// ── Send text ─────────────────────────────────────────
async function sendMsg() {
  if (pendingAttach) { await sendAttach(); return; }
  const input = document.getElementById('chatInput');
  const text  = input.value.trim();
  if (!text) return;
  input.value = '';
  await doSend({ message: text, type: 'text' });
}

// ── Generic send ─────────────────────────────────────
async function doSend(payload) {
  try {
    const res  = await fetch('api.php?action=send_chat', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (json.status === 'ok') {
      const now = new Date();
      const t   = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
      appendMsg({
        sender:               CHAT_ME,
        message:              payload.message || '',
        type:                 payload.type    || 'text',
        media:                payload.media   || null,
        sent_at:              '0000-00-00 ' + t + ':00',
        id:                   json.data.id || chatLastId + 1,
        nickname:             MY_PROFILE.nickname,
        avatar:               MY_PROFILE.avatar,
        avatar_deco_url:      MY_PROFILE.avatar_deco_url,
        avatar_deco_settings: MY_PROFILE.avatar_deco_settings,
      }, true);
    } else {
      showToast('⚠ Gửi thất bại: ' + (json.message || 'Unknown error'));
    }
  } catch { showToast('⚠ Mất kết nối — thử lại.'); }
}

// ── Attachment ────────────────────────────────────────
let pendingAttach = null;

async function handleFile(input) {
  const file = input.files[0];
  input.value = '';
  if (!file) return;
  const isImg = file.type.startsWith('image/');
  const isVid = file.type.startsWith('video/');
  if (!isImg && !isVid) { showToast('⚠ Chỉ hỗ trợ ảnh và video.'); return; }

  const maxMB = isVid ? 8 : 4;
  if (file.size > maxMB * 1048576) {
    showToast(`⚠ File quá lớn (>${maxMB}MB). Hãy chọn file nhỏ hơn.`);
    return;
  }

  let dataUrl;
  if (isImg) {
    dataUrl = await compressImage(file, 960, 0.82); // compress mobile photos
  } else {
    dataUrl = await readFileAsDataURL(file);
  }

  pendingAttach = { type: isImg ? 'image' : 'video', dataUrl, name: file.name, size: file.size };
  showAttachPreview(pendingAttach);
}

function readFileAsDataURL(file) {
  return new Promise(resolve => {
    const r = new FileReader();
    r.onload = e => resolve(e.target.result);
    r.readAsDataURL(file);
  });
}

function compressImage(file, maxPx, quality) {
  return new Promise(resolve => {
    const r = new FileReader();
    r.onload = e => {
      const img = new Image();
      img.onload = () => {
        let w = img.width, h = img.height;
        if (w > maxPx || h > maxPx) {
          if (w > h) { h = Math.round(h * maxPx / w); w = maxPx; }
          else       { w = Math.round(w * maxPx / h); h = maxPx; }
        }
        const c = document.createElement('canvas');
        c.width = w; c.height = h;
        c.getContext('2d').drawImage(img, 0, 0, w, h);
        resolve(c.toDataURL('image/jpeg', quality));
      };
      img.onerror = () => resolve(e.target.result); // fallback
      img.src = e.target.result;
    };
    r.readAsDataURL(file);
  });
}

function showAttachPreview(attach) {
  const apImg = document.getElementById('apImg');
  const apVid = document.getElementById('apVid');
  apImg.style.display = 'none'; apVid.style.display = 'none';
  if (attach.type === 'image') { apImg.src = attach.dataUrl; apImg.style.display = 'block'; }
  else                         { apVid.src = attach.dataUrl; apVid.style.display = 'block'; }
  document.getElementById('apName').textContent = attach.name;
  document.getElementById('apSize').textContent = fmtBytes(attach.size);
  document.getElementById('attachPreview').classList.add('show');
}

function clearAttach() {
  pendingAttach = null;
  document.getElementById('attachPreview').classList.remove('show');
  document.getElementById('apImg').src = '';
  document.getElementById('apVid').src = '';
}

async function sendAttach() {
  if (!pendingAttach) return;
  const { type, dataUrl, name } = pendingAttach;
  const caption = document.getElementById('chatInput').value.trim();
  document.getElementById('chatInput').value = '';
  clearAttach();
  await doSend({ message: caption || name, type, media: dataUrl });
}

// ── Voice recording ───────────────────────────────────
let mediaRecorder   = null;
let recordedChunks  = [];
let recInterval     = null;
let recSecs         = 0;
let isRecording     = false;

async function startRec() {
  if (isRecording) return;
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    recordedChunks = [];
    const types = [
      'audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg', ''
    ];
    const mimeType = types.find(t => t === '' || MediaRecorder.isTypeSupported(t)) || '';
    mediaRecorder = new MediaRecorder(stream, mimeType ? { mimeType } : {});
    mediaRecorder.ondataavailable = e => { if (e.data && e.data.size > 0) recordedChunks.push(e.data); };
    mediaRecorder.onstop = () => {
      stream.getTracks().forEach(t => t.stop());
      const blob  = new Blob(recordedChunks, { type: mimeType || 'audio/webm' });
      const rdr   = new FileReader();
      rdr.onload  = async ev => { await doSend({ message: '[Voice message]', type: 'voice', media: ev.target.result }); };
      rdr.readAsDataURL(blob);
    };
    mediaRecorder.start(100);
    isRecording = true;
    document.getElementById('micBtn').classList.add('recording');
    document.getElementById('recIndicator').classList.add('show');
    recSecs = 0;
    document.getElementById('recTimer').textContent = '0:00';
    recInterval = setInterval(() => {
      recSecs++;
      const m = Math.floor(recSecs/60), s = recSecs % 60;
      document.getElementById('recTimer').textContent = `${m}:${s.toString().padStart(2,'0')}`;
      if (recSecs >= 120) stopRec();
    }, 1000);
  } catch (err) {
    showToast('⚠ Không truy cập được mic: ' + err.message);
  }
}

function stopRec() {
  if (!isRecording || !mediaRecorder) return;
  mediaRecorder.stop();
  isRecording = false;
  clearInterval(recInterval);
  document.getElementById('micBtn').classList.remove('recording');
  document.getElementById('recIndicator').classList.remove('show');
}

const micBtn = document.getElementById('micBtn');
// Desktop
micBtn.addEventListener('mousedown',  e => { e.preventDefault(); startRec(); });
document.addEventListener('mouseup',  ()  => { if (isRecording) stopRec(); });
// Mobile touch
micBtn.addEventListener('touchstart', e => { e.preventDefault(); startRec(); }, { passive: false });
document.addEventListener('touchend', ()  => { if (isRecording) stopRec(); });
// If touch moves off button (cancel)
micBtn.addEventListener('touchcancel', () => { if (isRecording) stopRec(); });

// ── Profile Popup ─────────────────────────────────────
function esc2(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function linkify2(text) {
  const escaped = esc2(text);
  return escaped.replace(
    /(https?:\/\/[^\s<>"']+)/g,
    '<a href="$1" target="_blank" rel="noopener noreferrer" style="color:#1a6bff;text-decoration:underline;word-break:break-all">$1</a>'
  );
}

// Fetch đầy đủ profile (bao gồm avatar, cover) — không dùng cache
async function fetchFullProfile(username) {
  try {
    // Nếu là chính mình → dùng get_profile (đáng tin cậy hơn, dùng session)
    const isSelf = (username === CHAT_ME);
    const url = isSelf
      ? 'api.php?action=get_profile'
      : 'api.php?action=get_user_profile&username=' + encodeURIComponent(username);

    const res  = await fetch(url);
    if (!res.ok) { console.warn('[profile] HTTP error', res.status, url); return null; }

    const json = await res.json();

    if (json && json.status === 'ok' && json.data) {
      const d = json.data;
      // Bổ sung trường thiếu khi dùng get_profile (không có username)
      if (isSelf) {
        d.username = d.username || CHAT_ME;
      }
      return d;
    }
    console.warn('[profile] API returned non-ok or no data', json);
  } catch (e) { console.error('[profile] fetchFullProfile error', e); }
  return null;
}

async function showUserProfile(username) {
  const uname   = (username || '').trim() || CHAT_ME;
  const popup   = document.getElementById('profilePopup');
  const loading = document.getElementById('ppLoading');
  const content = document.getElementById('ppContent');
  const coverEl = document.getElementById('ppCoverImg');
  const avInner = document.getElementById('ppAvInner');

  // Reset popup về trạng thái loading
  loading.style.display = 'block';
  content.style.display = 'none';
  coverEl.style.display = 'none';
  coverEl.src = '';
  avInner.innerHTML = esc2((uname[0] || '?').toUpperCase());
  popup.classList.add('show');

  // Lấy đầy đủ profile (avatar, cover, nickname, bio)
  const d = await fetchFullProfile(uname) || {};

  // Profile background gradient
  const ppCard = document.querySelector('.pp-card');
  if (ppCard) {
    const top    = (d.profile_color_top    && /^#[0-9a-fA-F]{6}$/.test(d.profile_color_top))    ? d.profile_color_top    : '#1a0a2e';
    const bottom = (d.profile_color_bottom && /^#[0-9a-fA-F]{6}$/.test(d.profile_color_bottom)) ? d.profile_color_bottom : '#0a0814';
    const ppBody = ppCard.querySelector('.pp-body');
    if (ppBody) ppBody.style.background = `linear-gradient(180deg, ${top} 0%, ${bottom} 100%)`;
  }

  // Cover
  if (d.cover && typeof d.cover === 'string' && d.cover.trim()) {
    coverEl.src = d.cover;
    coverEl.style.display = 'block';
  }

  // Avatar
  const fallbackLetter = ((d.nickname || d.username || uname || '?')[0] || '?').toUpperCase();
  if (d.avatar && typeof d.avatar === 'string' && d.avatar.trim()) {
    avInner.innerHTML = `<img src="${esc2(d.avatar)}" alt="av"
      onerror="this.onerror=null;this.parentElement.innerHTML='';this.parentElement.textContent='${esc2(fallbackLetter)}'"/>`;
  } else {
    avInner.textContent = fallbackLetter;
  }

  // Decoration overlay on profile popup avatar
  const ppAvWrap = document.querySelector('.pp-av-wrap');
  if (ppAvWrap) {
    // Remove existing deco
    const oldDeco = ppAvWrap.querySelector('.pp-av-deco');
    if (oldDeco) oldDeco.remove();
    if (d.avatar_deco_url) {
      let ppDecoEnabled = true, ppDecoSize = 150, ppDecoX = 0, ppDecoY = 0;
      try {
        const ds = JSON.parse(d.avatar_deco_settings || '{}');
        ppDecoEnabled = ds.enabled !== false;
        ppDecoSize    = Number(ds.size || 150);
        ppDecoX       = Number(ds.x   || 0);
        ppDecoY       = Number(ds.y   || 0);
      } catch {}
      if (ppDecoEnabled) {
        const decoEl = document.createElement('div');
        decoEl.className = 'pp-av-deco';
        decoEl.style.cssText = `width:${ppDecoSize}%;height:${ppDecoSize}%;transform:translate(calc(-50% + ${ppDecoX}px),calc(-50% + ${ppDecoY}px))`;
        decoEl.innerHTML = `<img src="${esc2(d.avatar_deco_url)}" alt="" crossorigin="anonymous"/>`;
        ppAvWrap.appendChild(decoEl);
      }
    }
  }

  // Tên hiển thị — ưu tiên nickname, rồi username, rồi uname gốc
  const displayName = (d.nickname && d.nickname !== d.username)
    ? d.nickname
    : (d.nickname || d.username || uname);
  document.getElementById('ppName').textContent     = displayName;
  document.getElementById('ppUsername').textContent = '@' + (d.username || uname);

  const bioEl = document.getElementById('ppBio');
  if (d.bio && d.bio.trim()) {
    bioEl.innerHTML = `<div class="pp-bio">${linkify2(d.bio).replace(/\n/g,'<br>')}</div>`;
  } else {
    bioEl.innerHTML = `<div class="pp-bio-empty">Chưa có tiểu sử.</div>`;
  }

  // Stats — joined date + role
  const statJoined = document.getElementById('ppStatJoined');
  if (statJoined) {
    if (d.created_at) {
      const dt = new Date(d.created_at);
      statJoined.textContent = dt.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } else {
      statJoined.textContent = '—';
    }
  }
  const statRole = document.getElementById('ppStatRole');
  if (statRole) {
    const roleMap = {
      0: { label: 'MEMBER',  color: '#ffffff' },
      1: { label: 'ADMIN',   color: '#ff3333' },
      2: { label: 'HACKER',  color: '#00ff88' },
      3: { label: 'MANAGER', color: '#ffcc00' },
    };
    const roleCode = Number(d.admin_rights ?? 0);
    const role = roleMap[roleCode] ?? roleMap[0];
    statRole.textContent = role.label;
    statRole.style.color = role.color;
    statRole.style.fontWeight = 'bold';
    statRole.style.letterSpacing = '1px';
  }

  loading.style.display = 'none';
  content.style.display = 'block';
}

function closeProfilePopup() {
  document.getElementById('profilePopup').classList.remove('show');
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeProfilePopup();
});

// ── Enter to send ─────────────────────────────────────
document.getElementById('chatInput').addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
});

// ── Heartbeat — giữ last_seen luôn fresh (mỗi 10s) ───
async function heartbeat() {
  try { await fetch('api.php?action=ping'); } catch {}
}

// ── Boot ──────────────────────────────────────────────
heartbeat(); // ping ngay khi vào trang
loadProfile().then(() => loadHistory());
setInterval(heartbeat, 10000);
setInterval(pollNew, 3000);
</script>
</body>
</html>
