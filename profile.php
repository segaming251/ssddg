<?php
// ═══════════════════════════════════════════════════════
//  WEBRAT v2.0 — Trang Profile
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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
  <title>PROFILE — <?= $authedUser ?></title>
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
    html, body { width: 100%; min-height: 100vh; background: #08050c; }
    body { font-family: 'Share Tech Mono','Courier New',monospace; color: #fff; overflow-x: hidden; }

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
    .logo-img { height:44px; width:auto; margin-left:4px; filter:brightness(0) invert(1) sepia(1) saturate(500%) hue-rotate(296deg) brightness(1.0) drop-shadow(0 0 10px #ff009099); vertical-align:middle; flex-shrink:0; }
    .nav { display:flex; align-items:center; gap:10px; flex-shrink:0; }
    .back-link {
      display:flex; align-items:center; gap:6px;
      border:1px solid #2a2a3e; padding:6px 14px;
      color:#bbb; font-size:11px; letter-spacing:1px;
      cursor:pointer; background:rgba(255,255,255,.05);
      transition:all .2s; font-family:inherit; white-space:nowrap;
      border-radius:20px; text-decoration:none;
    }
    .back-link:hover { border-color:var(--accent); color:var(--accent); background:color-mix(in srgb, var(--accent) 10%, transparent); box-shadow:0 0 12px color-mix(in srgb, var(--accent) 20%, transparent); }

    /* ─── ACCENT GLOW BAR ─── */
    #accentGlowBar {
      width:100%; height:2px; position:relative; z-index:99;
      background:color-mix(in srgb, var(--accent) 12%, transparent);
      box-shadow:0 0 16px 0 var(--glow);
    }
    #accentGlowBar .agb-line {
      display:block; height:100%; width:100%;
      background: linear-gradient(90deg, transparent 0%, var(--accent) 50%, transparent 100%);
      opacity:0.8;
    }

    /* ─── MAIN LAYOUT ─── */
    .profile-outer {
      max-width: 1100px;
      margin: 36px auto 60px;
      padding: 0 20px;
      display: flex;
      gap: 28px;
      align-items: flex-start;
    }

    /* ─── LEFT: EDITOR ─── */
    .editor-col {
      flex: 1;
      min-width: 0;
    }

    /* ─── RIGHT: PREVIEW ─── */
    .preview-col {
      width: 320px;
      flex-shrink: 0;
      position: sticky;
      top: 72px;
    }

    .page-title {
      font-size: 13px;
      letter-spacing: 3px;
      color: var(--accent);
      text-shadow: 0 0 12px var(--glow);
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .page-title::before { content: '>_'; color: var(--accent); opacity: .7; }

    /* ─── COVER IMAGE SECTION ─── */
    .cover-section {
      border: 1px solid color-mix(in srgb, var(--accent) 25%, transparent);
      border-radius: 12px;
      overflow: hidden;
      margin-bottom: 20px;
      background: rgba(255,0,144,.04);
    }
    .cover-area {
      position: relative; width: 100%; height: 160px;
      background: linear-gradient(135deg, #0d0818 0%, #1a0a2e 50%, #0a0a1a 100%);
      cursor: pointer; overflow: hidden;
      display: flex; align-items: center; justify-content: center;
      transition: filter .2s;
    }
    .cover-area:hover { filter: brightness(1.15); }
    .cover-area:hover .cover-overlay { opacity: 1; }
    #coverPreview { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: none; }
    .cover-overlay {
      position: absolute; inset: 0; background: rgba(0,0,0,.5);
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      gap: 8px; opacity: 0; transition: opacity .2s; pointer-events: none;
    }
    .cover-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: var(--accent); opacity: .5; }
    .cover-placeholder .cp-icon { font-size: 32px; }
    .cover-placeholder .cp-text { font-size: 9px; letter-spacing: 2px; }
    .cover-actions { display: flex; gap: 8px; padding: 10px 14px; background: rgba(0,0,0,.3); }
    .cover-upload-btn, .cover-remove-btn {
      font-family: inherit; font-size: 9px; letter-spacing: 1.5px;
      padding: 5px 12px; border-radius: 6px; cursor: pointer;
      transition: all .2s; border: 1px solid;
    }
    .cover-upload-btn { border-color: color-mix(in srgb, var(--accent) 50%, transparent); color: var(--accent); background: color-mix(in srgb, var(--accent) 8%, transparent); }
    .cover-upload-btn:hover { background: color-mix(in srgb, var(--accent) 18%, transparent); box-shadow: 0 0 10px color-mix(in srgb, var(--accent) 25%, transparent); }
    .cover-remove-btn { border-color: #333; color: #777; background: transparent; display: none; }
    .cover-remove-btn:hover { border-color: #ff4444; color: #ff4444; }

    /* ─── PROFILE CARD (EDITOR) ─── */
    .profile-card {
      border: 1px solid color-mix(in srgb, var(--accent) 25%, transparent);
      border-radius: 12px; overflow: hidden;
      background: rgba(5,3,12,.7); backdrop-filter: blur(10px);
      margin-bottom: 16px;
    }
    .pc-header { padding: 20px 20px 16px; display: flex; align-items: flex-start; gap: 20px; border-bottom: 1px solid rgba(255,255,255,.05); }
    .avatar-wrap { position: relative; width: 80px; height: 80px; flex-shrink: 0; }
    .avatar-inner {
      width: 80px; height: 80px; border-radius: 50%;
      border: 2px solid color-mix(in srgb, var(--accent) 50%, transparent);
      box-shadow: 0 0 20px color-mix(in srgb, var(--accent) 25%, transparent);
      background: color-mix(in srgb, var(--accent) 10%, transparent);
      display: flex; align-items: center; justify-content: center;
      font-size: 28px; color: var(--accent);
      overflow: hidden; cursor: pointer; transition: all .2s; position: relative;
    }
    .avatar-inner:hover { border-color: var(--accent); box-shadow: 0 0 28px color-mix(in srgb, var(--accent) 45%, transparent); }
    .avatar-inner:hover .av-overlay { opacity: 1; }
    #avatarPreview { width: 100%; height: 100%; object-fit: cover; display: none; }
    .av-overlay { position: absolute; inset: 0; background: rgba(0,0,0,.55); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .2s; font-size: 20px; border-radius: 50%; }
    .avatar-badge { position: absolute; bottom: 0; right: 0; width: 22px; height: 22px; border-radius: 50%; background: var(--accent); border: 2px solid #08050c; display: flex; align-items: center; justify-content: center; font-size: 10px; cursor: pointer; }

    /* ─── AVATAR DECORATION ─── */
    .av-deco-layer {
      position: absolute; pointer-events: none; z-index: 10;
      top: 50%; left: 50%;
      width: 130%; height: 130%;
      transform: translate(calc(-50% + var(--deco-x,0px)), calc(-50% + var(--deco-y,0px)));
      display: none;
    }
    .av-deco-layer.active { display: block; }
    .av-deco-layer img { width: 100%; height: 100%; object-fit: contain; }

    .deco-section { border-top: 1px solid rgba(255,255,255,.06); padding-top: 14px; display: flex; flex-direction: column; gap: 10px; }
    .deco-toggle-row { display: flex; align-items: center; gap: 10px; }
    .deco-toggle-btn {
      font-family: inherit; font-size: 9px; letter-spacing: 1.5px; padding: 6px 14px;
      border-radius: 7px; cursor: pointer; transition: all .2s; border: 1px solid;
      border-color: rgba(255,255,255,.15); color: #666; background: transparent; white-space: nowrap;
    }
    .deco-toggle-btn.active {
      border-color: var(--accent); color: var(--accent);
      background: color-mix(in srgb, var(--accent) 10%, transparent);
      box-shadow: 0 0 10px color-mix(in srgb, var(--accent) 20%, transparent);
    }
    .deco-preview-thumb { width: 28px; height: 28px; object-fit: contain; opacity: .4; transition: opacity .2s; }
    .deco-preview-thumb.active { opacity: 1; }
    .deco-sliders { display: none; flex-direction: column; gap: 8px; }
    .deco-sliders.visible { display: flex; }
    .deco-slider-row { display: flex; align-items: center; gap: 10px; }
    .deco-slider-label { font-size: 9px; letter-spacing: 1px; color: #555; width: 38px; flex-shrink: 0; text-transform: uppercase; }
    .deco-slider-val { font-size: 9px; color: var(--accent); width: 36px; text-align: right; flex-shrink: 0; font-family: inherit; }
    input[type=range].deco-slider {
      -webkit-appearance: none; flex: 1; height: 3px;
      background: rgba(255,255,255,.1); border-radius: 2px; outline: none; cursor: pointer;
    }
    input[type=range].deco-slider::-webkit-slider-thumb {
      -webkit-appearance: none; width: 13px; height: 13px; border-radius: 50%;
      background: var(--accent); box-shadow: 0 0 6px var(--accent); cursor: pointer;
    }

    .user-info { flex: 1; min-width: 0; padding-top: 4px; }
    .user-display-name { font-size: 18px; letter-spacing: 2px; color: #fff; margin-bottom: 4px; }
    .user-display-name .highlight { color: var(--accent); text-shadow: 0 0 10px var(--glow); }
    .user-at { font-size: 10px; letter-spacing: 1px; color: #555; }

    /* ─── FORM SECTIONS ─── */
    .pc-body { padding: 20px; display: flex; flex-direction: column; gap: 18px; }
    .field-group { display: flex; flex-direction: column; gap: 8px; }
    .field-label { font-size: 9px; letter-spacing: 2px; color: var(--accent); opacity: .8; display: flex; align-items: center; gap: 6px; }
    .field-label::before { content: '◈'; font-size: 8px; }
    .field-input {
      background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1);
      border-radius: 8px; color: #fff; font-family: inherit;
      font-size: 12px; letter-spacing: .5px; padding: 10px 14px;
      outline: none; transition: all .2s; width: 100%;
    }
    .field-input:focus { border-color: color-mix(in srgb, var(--accent) 55%, transparent); background: rgba(255,0,144,.04); box-shadow: 0 0 14px color-mix(in srgb, var(--accent) 12%, transparent); }
    .field-input::placeholder { color: #444; }
    textarea.field-input { resize: vertical; min-height: 90px; max-height: 200px; line-height: 1.6; }
    .avatar-upload-row { display: flex; gap: 8px; align-items: center; }
    .upload-btn { font-family: inherit; font-size: 9px; letter-spacing: 1.5px; padding: 7px 14px; border-radius: 7px; cursor: pointer; transition: all .2s; border: 1px solid; white-space: nowrap; }
    .upload-btn-accent { border-color: color-mix(in srgb, var(--accent) 50%, transparent); color: var(--accent); background: color-mix(in srgb, var(--accent) 8%, transparent); }
    .upload-btn-accent:hover { background: color-mix(in srgb, var(--accent) 18%, transparent); box-shadow: 0 0 10px color-mix(in srgb, var(--accent) 25%, transparent); }
    .upload-btn-muted { border-color: #333; color: #666; background: transparent; display: none; }
    .upload-btn-muted:hover { border-color: #ff4444; color: #ff4444; }
    .avatar-status { font-size: 9px; letter-spacing: 1px; color: #555; }

    /* ─── SAVE BUTTON ─── */
    .save-row { display: flex; align-items: center; gap: 12px; padding-top: 4px; }
    .save-btn { font-family: inherit; font-size: 11px; letter-spacing: 2px; padding: 10px 28px; border-radius: 8px; cursor: pointer; background: color-mix(in srgb, var(--accent) 15%, transparent); border: 1px solid color-mix(in srgb, var(--accent) 55%, transparent); color: var(--accent); transition: all .2s; }
    .save-btn:hover { background: color-mix(in srgb, var(--accent) 25%, transparent); box-shadow: 0 0 18px color-mix(in srgb, var(--accent) 30%, transparent); }
    .save-btn:active { transform: scale(0.97); }
    .save-btn:disabled { opacity: .5; cursor: not-allowed; }
    #saveStatus { font-size: 10px; letter-spacing: 1.5px; display: none; transition: opacity .3s; }
    #saveStatus.ok { color: #00ff88; display: block; }
    #saveStatus.err { color: #ff4444; display: block; }
    .sep { height: 1px; background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--accent) 20%, transparent), transparent); }

    /* ─── PREVIEW PANEL ─── */
    .preview-label {
      font-size: 9px; letter-spacing: 3px; color: var(--accent); opacity: .7;
      margin-bottom: 12px; display: flex; align-items: center; gap: 8px;
    }
    .preview-label::before { content: '>_'; opacity: .6; }

    .pv-card {
      border-radius: 14px; overflow: hidden;
      background: #18101f;
      border: 1px solid rgba(0,0,0,.18);
      box-shadow: 0 8px 40px rgba(0,0,0,.5), inset 0 0 0 1px rgba(255,255,255,.06);
      min-height: 520px;
      display: flex; flex-direction: column;
    }
    .pv-cover {
      width: 100%; height: 120px; position: relative; overflow: hidden; flex-shrink: 0;
      background: linear-gradient(135deg, #2d0b50 0%, #5a1080 45%, #c060e0 100%);
    }
    .pv-cover img { width: 100%; height: 100%; object-fit: cover; display: none; }
    .pv-body { padding: 0 20px 32px; flex: 1; display: flex; flex-direction: column; }
    .pv-top-row { display: flex; align-items: flex-end; margin-top: -44px; margin-bottom: 16px; }
    .pv-av-wrap { position: relative; width: 88px; height: 88px; flex-shrink: 0; }
    .pv-av-inner {
      width: 88px; height: 88px; border-radius: 50%;
      border: none;
      background: color-mix(in srgb, var(--accent) 20%, #1a0030);
      overflow: hidden; display: flex; align-items: center; justify-content: center;
      font-size: 34px; color: var(--accent);
      box-shadow: 0 0 0 4px rgba(255,255,255,.55), 0 4px 18px rgba(0,0,0,.45);
    }
    .pv-av-inner img { width: 100%; height: 100%; object-fit: cover; display: none; }
    .pv-name { font-size: 20px; letter-spacing: .4px; color: #111; font-weight: bold; margin-bottom: 5px; line-height: 1.2; }
    .pv-username { font-size: 13px; color: rgba(0,0,0,.62); letter-spacing: .5px; margin-bottom: 2px; }
    .pv-divider { height: 1px; background: rgba(0,0,0,.12); margin: 18px 0; }
    .pv-section-label { font-size: 11px; color: rgba(0,0,0,.58); letter-spacing: 2px; margin-bottom: 10px; text-transform: uppercase; }
    .pv-bio { font-size: 13px; color: #111; line-height: 1.75; word-break: break-word; }
    .pv-bio a { color: #1a6bff; text-decoration: underline; word-break: break-all; }
    .pv-bio-empty { font-size: 12px; color: rgba(0,0,0,.28); font-style: italic; }
    .pv-extra { margin-top: auto; padding-top: 24px; display: flex; flex-direction: column; gap: 10px; }
    .pv-stat-row { display: flex; gap: 20px; }
    .pv-stat { display: flex; flex-direction: column; gap: 2px; }
    .pv-stat-val { font-size: 16px; color: #111; font-weight: bold; letter-spacing: .5px; }
    .pv-stat-label { font-size: 11px; color: rgba(0,0,0,.58); letter-spacing: 2px; text-transform: uppercase; }

    /* ─── COLOR PICKER ─── */
    .color-picker-row { display: flex; gap: 14px; }
    .color-picker-item { flex: 1; display: flex; flex-direction: column; gap: 8px; }
    .color-picker-label { font-size: 9px; letter-spacing: 2px; color: #666; text-transform: uppercase; }
    .color-picker-wrap {
      display: flex; align-items: center; gap: 10px;
      background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1);
      border-radius: 8px; padding: 8px 12px; cursor: pointer; transition: all .2s;
    }
    .color-picker-wrap:hover { border-color: color-mix(in srgb, var(--accent) 55%, transparent); }
    .color-swatch {
      width: 28px; height: 28px; border-radius: 6px; flex-shrink: 0;
      border: 2px solid rgba(255,255,255,.15);
      cursor: pointer; transition: transform .15s; flex-shrink: 0;
    }
    .color-swatch:hover { transform: scale(1.1); }
    /* Overlay the native color input over the entire wrap so mobile touch triggers it directly */
    .color-picker-trigger {
      position: relative; display: flex; align-items: center; gap: 10px;
      flex: 1; overflow: hidden;
    }
    .color-picker-trigger input[type="color"] {
      position: absolute; inset: 0; width: 100%; height: 100%;
      opacity: 0; cursor: pointer; border: none; padding: 0; margin: 0;
    }
    .color-hex-val { font-size: 12px; color: #bbb; letter-spacing: 1px; flex: 1; pointer-events: none; }
    .color-reset-btn {
      background: none; border: 1px solid #333; color: #555; font-size: 9px;
      font-family: inherit; letter-spacing: 1px; padding: 3px 8px; border-radius: 5px;
      cursor: pointer; transition: all .2s; white-space: nowrap;
    }
    .color-reset-btn:hover { border-color: #ff4444; color: #ff4444; }

    /* ─── DECO OPEN BUTTON ─── */
    .deco-open-btn {
      font-family: inherit; font-size: 9px; letter-spacing: 1.5px;
      padding: 7px 16px; border-radius: 7px; cursor: pointer;
      border: 1px solid color-mix(in srgb, var(--accent) 45%, transparent);
      color: var(--accent); background: color-mix(in srgb, var(--accent) 8%, transparent);
      transition: all .2s; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px;
    }
    .deco-open-btn:hover {
      background: color-mix(in srgb, var(--accent) 18%, transparent);
      box-shadow: 0 0 12px color-mix(in srgb, var(--accent) 28%, transparent);
    }
    .deco-open-btn .deco-thumb-mini {
      width: 20px; height: 20px; object-fit: contain; border-radius: 50%; display: none;
    }
    .deco-open-btn .deco-thumb-mini.visible { display: inline-block; }

    /* ─── DECO MODAL ─── */
    .deco-modal-overlay {
      position: fixed; inset: 0; z-index: 9000;
      background: rgba(0,0,0,.75); backdrop-filter: blur(6px);
      display: none; align-items: center; justify-content: center;
      padding: 16px;
    }
    .deco-modal-overlay.open { display: flex; }
    .deco-modal {
      background: #0d0918; border: 1px solid color-mix(in srgb, var(--accent) 30%, transparent);
      border-radius: 14px; width: 100%; max-width: 480px;
      max-height: 90vh; overflow-y: auto;
      box-shadow: 0 8px 60px rgba(0,0,0,.7), 0 0 0 1px rgba(255,255,255,.05);
      display: flex; flex-direction: column;
    }
    .deco-modal::-webkit-scrollbar { width: 3px; }
    .deco-modal::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 3px; }
    .deco-modal-head {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 18px 12px; border-bottom: 1px solid rgba(255,255,255,.06);
      position: sticky; top: 0; background: #0d0918; z-index: 2;
      flex-shrink: 0;
    }
    .deco-modal-title {
      font-size: 10px; letter-spacing: 2.5px; color: var(--accent);
      display: flex; align-items: center; gap: 8px;
    }
    .deco-modal-title::before { content: '◈'; font-size: 9px; opacity: .7; }
    .deco-modal-close {
      font-family: inherit; font-size: 10px; letter-spacing: 1px; padding: 5px 12px;
      border-radius: 6px; cursor: pointer; border: 1px solid #333; color: #666;
      background: transparent; transition: all .2s;
    }
    .deco-modal-close:hover { border-color: #ff4444; color: #ff4444; }
    .deco-modal-body { padding: 16px 18px 20px; display: flex; flex-direction: column; gap: 16px; }

    /* ─── DECO PRESET GALLERY ─── */
    .deco-gallery-label {
      font-size: 9px; letter-spacing: 2px; color: var(--accent); opacity: .75;
      margin-bottom: 10px; display: flex; align-items: center; gap: 6px;
    }
    .deco-gallery-label::before { content: '◈'; font-size: 8px; }
    .deco-preset-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(76px, 1fr));
      gap: 10px;
      padding: 2px 2px 6px;
    }
    .deco-preset-item {
      display: flex; flex-direction: column; align-items: center; gap: 7px;
      cursor: pointer; padding: 8px 4px 6px;
      border-radius: 10px; border: 1px solid rgba(255,255,255,.07);
      background: rgba(255,255,255,.03);
      transition: all .18s; position: relative;
      -webkit-tap-highlight-color: transparent;
    }
    .deco-preset-item:hover {
      border-color: color-mix(in srgb, var(--accent) 45%, transparent);
      background: color-mix(in srgb, var(--accent) 7%, transparent);
    }
    .deco-preset-item.selected {
      border-color: var(--accent);
      background: color-mix(in srgb, var(--accent) 12%, transparent);
      box-shadow: 0 0 14px color-mix(in srgb, var(--accent) 28%, transparent),
                  inset 0 0 8px color-mix(in srgb, var(--accent) 8%, transparent);
    }
    .deco-preset-item.selected::after {
      content: '✓';
      position: absolute; top: 4px; right: 5px;
      font-size: 9px; color: var(--accent);
      text-shadow: 0 0 6px var(--accent);
    }
    .deco-preset-circle {
      position: relative; width: 52px; height: 52px; flex-shrink: 0;
    }
    .dpc-av {
      width: 52px; height: 52px; border-radius: 50%;
      background: color-mix(in srgb, var(--accent) 18%, #1a0030);
      border: 2px solid rgba(255,255,255,.15);
      display: flex; align-items: center; justify-content: center;
      font-size: 20px; color: var(--accent); overflow: hidden;
      font-family: 'Share Tech Mono', monospace;
    }
    .dpc-deco {
      position: absolute; inset: 0; pointer-events: none;
      width: 140%; height: 140%; top: 50%; left: 50%;
      transform: translate(-50%, -50%);
    }
    .dpc-deco img { width: 100%; height: 100%; object-fit: contain; }
    .dpc-none-icon {
      width: 52px; height: 52px; border-radius: 50%;
      background: rgba(255,255,255,.04);
      border: 2px dashed rgba(255,255,255,.15);
      display: flex; align-items: center; justify-content: center;
      font-size: 18px; color: #444;
    }
    .deco-preset-name {
      font-size: 8px; letter-spacing: 1px; color: #666;
      text-align: center; line-height: 1.3; text-transform: uppercase;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      max-width: 72px;
    }
    .deco-preset-item.selected .deco-preset-name { color: var(--accent); }
    .deco-preset-skeleton {
      width: 52px; height: 52px; border-radius: 50%;
      background: linear-gradient(90deg, rgba(255,255,255,.04) 25%, rgba(255,255,255,.08) 50%, rgba(255,255,255,.04) 75%);
      background-size: 200% 100%;
      animation: shimmer 1.4s infinite;
    }
    @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

    /* ─── DECO SLIDERS (inside modal) ─── */
    .deco-modal-sliders {
      display: none; flex-direction: column; gap: 8px;
      padding-top: 14px; border-top: 1px solid rgba(255,255,255,.06);
    }
    .deco-modal-sliders.visible { display: flex; }
    .deco-sliders-title {
      font-size: 8px; letter-spacing: 2px; color: #444; text-transform: uppercase; margin-bottom: 4px;
      display: flex; align-items: center; gap: 6px;
    }
    .deco-sliders-title::before { content: '◈'; font-size: 7px; }

    /* ─── SCAN LINES ─── */
    body::after {
      content: ''; position: fixed; inset: 0; z-index: 9999; pointer-events: none;
      background: repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0,0,0,.025) 2px, rgba(0,0,0,.025) 4px);
    }

    /* ─── MOBILE ─── */
    @media (max-width: 860px) {
      .profile-outer { flex-direction: column; }
      .preview-col { width: 100%; position: static; }
      .pv-card { max-width: 100%; }
    }
    @media (max-width: 600px) {
      .profile-outer { margin: 12px auto 40px; padding: 0 10px; }
      .cover-area { height: 120px; }
      .pc-header { flex-wrap: wrap; gap: 12px; }
      .color-picker-row { flex-direction: column; gap: 10px; }
      .color-picker-item { width: 100%; }
      /* Prevent any horizontal overflow */
      .editor-col, .profile-card, .pc-body,
      .cover-section, .field-group { max-width: 100%; overflow-x: hidden; }
      /* Modal on mobile */
      .deco-modal { max-height: 95vh; border-radius: 12px 12px 0 0; }
      .deco-modal-overlay { align-items: flex-end; padding: 0; }
      .deco-preset-grid { grid-template-columns: repeat(auto-fill, minmax(68px, 1fr)); gap: 8px; }
    }
    @media (max-width: 400px) {
      .pc-header { flex-direction: column; align-items: center; text-align: center; }
      .user-at { text-align: center; }
      .avatar-upload-row { flex-wrap: wrap; gap: 6px; }
      .deco-preset-grid { grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); }
    }
  </style>
</head>
<body>
<div id="bg-layer"></div>
<div id="bg-overlay"></div>

<!-- HEADER -->
<header class="header">
  <div class="logo">&gt;_ ELSARAT<img src="elsarat-logo.png" class="logo-img" alt="logo"></div>
  <nav class="nav">
    <a href="index.php" class="back-link">← DASHBOARD</a>
  </nav>
</header>
<div id="accentGlowBar"><span class="agb-line"></span></div>

<!-- TWO-COLUMN LAYOUT -->
<div class="profile-outer">

  <!-- ── LEFT: EDITOR ── -->
  <div class="editor-col">
    <div class="page-title">PROFILE_EDITOR</div>

    <!-- COVER IMAGE -->
    <div class="cover-section">
      <div class="cover-area" id="coverArea" onclick="document.getElementById('coverFile').click()" title="Nhấn để đổi ảnh bìa">
        <img id="coverPreview" alt="cover" />
        <div class="cover-placeholder" id="coverPlaceholder">
          <span class="cp-icon">🖼</span>
          <span class="cp-text">NHẤN ĐỂ TẢI ẢNH BÌA / GIF</span>
        </div>
        <div class="cover-overlay">
          <span style="font-size:24px;">🖼</span>
          <span style="font-size:9px;letter-spacing:2px;color:#fff">ĐỔI ẢNH BÌA</span>
        </div>
      </div>
      <div class="cover-actions">
        <input type="file" id="coverFile" accept="image/*,.gif" style="display:none" onchange="loadCover(this)" />
        <button class="cover-upload-btn" onclick="document.getElementById('coverFile').click()">▲ TẢI ẢNH BÌA / GIF</button>
        <button class="cover-remove-btn" id="coverRemoveBtn" onclick="removeCover()">✕ XÓA ẢNH BÌA</button>
      </div>
      <span id="coverStatus" style="font-size:10px;letter-spacing:1px;font-family:inherit;display:block;margin-top:6px;min-height:16px;padding:0 14px 8px;"></span>
    </div>

    <!-- PROFILE CARD -->
    <div class="profile-card">
      <div class="pc-header">
        <div class="avatar-wrap">
          <div class="avatar-inner" id="avatarInner" onclick="document.getElementById('avatarFile').click()" title="Nhấn để đổi ảnh đại diện">
            <img id="avatarPreview" alt="avatar" />
            <span id="avatarPlaceholder"><?= strtoupper(substr($authedUser, 0, 1)) ?></span>
            <div class="av-overlay">📷</div>
          </div>
          <div class="avatar-badge" onclick="document.getElementById('avatarFile').click()" title="Đổi ảnh đại diện">✎</div>
          <input type="file" id="avatarFile" accept="image/*" style="display:none" onchange="loadAvatar(this)" />
          <!-- Decoration overlay (editor) -->
          <div class="av-deco-layer" id="editorDecoLayer">
            <img id="editorDecoImg" src="" alt="deco" crossorigin="anonymous" />
          </div>
        </div>
        <div class="user-info">
          <div class="user-display-name">
            <span class="highlight" id="displayNamePreview"><?= $authedUser ?></span>
          </div>
          <div class="user-at">@<?= $authedUser ?></div>
        </div>
      </div>

      <div class="pc-body">
        <!-- AVATAR ACTIONS -->
        <div class="field-group">
          <div class="field-label">ẢNH ĐẠI DIỆN</div>
          <div class="avatar-upload-row">
            <button class="upload-btn upload-btn-accent" onclick="document.getElementById('avatarFile').click()">▲ TẢI ẢNH</button>
            <button class="upload-btn upload-btn-muted" id="rmAvatarBtn" onclick="removeAvatar()">✕ XÓA ẢNH</button>
            <span class="avatar-status" id="avatarStatus">Chưa có ảnh đại diện</span>
          </div>

          <!-- DECORATION: single button -->
          <div style="margin-top:4px;">
            <button class="deco-open-btn" id="decoOpenBtn" onclick="openDecoModal()">
              🎨 CHỌN KHUNG AVATAR (DECORATION)
              <img class="deco-thumb-mini" id="decoThumbMini" src="" alt="" />
            </button>
          </div>
        </div>

        <div class="sep"></div>

        <!-- NICKNAME -->
        <div class="field-group">
          <div class="field-label">BIỆT DANH (HIỂN THỊ TRONG CHAT)</div>
          <input type="text" class="field-input" id="nicknameInput"
                 placeholder="Nhập biệt danh của bạn..." maxlength="30"
                 oninput="syncPreview()" />
        </div>

        <!-- BIO -->
        <div class="field-group">
          <div class="field-label">TIỂU SỬ (BIO)</div>
          <textarea class="field-input" id="bioInput"
                    placeholder="Giới thiệu về bản thân..." maxlength="300"
                    oninput="syncPreview()"></textarea>
        </div>

        <div class="sep"></div>

        <!-- PROFILE BACKGROUND COLORS -->
        <div class="field-group">
          <div class="field-label">MÀU NỀN PROFILE</div>
          <div class="color-picker-row">
            <!-- Top color -->
            <div class="color-picker-item">
              <div class="color-picker-label">MÀU BÊN TRÊN</div>
              <div class="color-picker-wrap">
                <div class="color-picker-trigger">
                  <div class="color-swatch" id="colorTopSwatch" style="background:#1a0a2e;"></div>
                  <span class="color-hex-val" id="colorTopHex">#1a0a2e</span>
                  <input type="color" id="colorTopPicker" value="#1a0a2e"
                    oninput="onColorChange('top', this.value)"
                    onchange="onColorChange('top', this.value)"/>
                </div>
                <button class="color-reset-btn" onclick="resetColor('top')">ĐẶT LẠI</button>
              </div>
            </div>
            <!-- Bottom color -->
            <div class="color-picker-item">
              <div class="color-picker-label">MÀU BÊN DƯỚI</div>
              <div class="color-picker-wrap">
                <div class="color-picker-trigger">
                  <div class="color-swatch" id="colorBottomSwatch" style="background:#0a0814;"></div>
                  <span class="color-hex-val" id="colorBottomHex">#0a0814</span>
                  <input type="color" id="colorBottomPicker" value="#0a0814"
                    oninput="onColorChange('bottom', this.value)"
                    onchange="onColorChange('bottom', this.value)"/>
                </div>
                <button class="color-reset-btn" onclick="resetColor('bottom')">ĐẶT LẠI</button>
              </div>
            </div>
          </div>
        </div>

        <div class="sep"></div>

        <!-- SAVE -->
        <div class="save-row">
          <button class="save-btn" id="saveBtn" onclick="saveProfile()">💾 LƯU THAY ĐỔI</button>
          <span id="saveStatus"></span>
        </div>
      </div>
    </div>
  </div><!-- /editor-col -->

  <!-- ── RIGHT: PREVIEW ── -->
  <div class="preview-col">
    <div class="preview-label">XEM TRƯỚC PROFILE</div>
    <div class="pv-card" id="pvCard">
      <!-- cover -->
      <div class="pv-cover" id="pvCoverArea">
        <img id="pvCoverImg" alt="cover" />
      </div>
      <!-- body -->
      <div class="pv-body">
        <div class="pv-top-row">
          <div class="pv-av-wrap">
            <div class="pv-av-inner" id="pvAvInner">
              <img id="pvAvImg" alt="avatar" />
              <span id="pvAvLetter"><?= strtoupper(substr($authedUser, 0, 1)) ?></span>
            </div>
            <!-- Decoration overlay (preview) -->
            <div class="av-deco-layer" id="previewDecoLayer">
              <img id="previewDecoImg" src="" alt="deco" crossorigin="anonymous" />
            </div>
          </div>
        </div>
        <div class="pv-name" id="pvName"><?= $authedUser ?></div>
        <div class="pv-username" id="pvUsername">@<?= $authedUser ?></div>
        <div class="pv-divider"></div>
        <div class="pv-section-label">TIỂU SỬ</div>
        <div id="pvBio"><div class="pv-bio-empty">Chưa có tiểu sử.</div></div>
        <div class="pv-extra">
          <div class="pv-divider"></div>
          <div class="pv-section-label">THÔNG TIN</div>
          <div class="pv-stat-row">
            <div class="pv-stat">
              <span class="pv-stat-val" id="pvStatJoined">—</span>
              <span class="pv-stat-label">THAM GIA</span>
            </div>
            <div class="pv-stat">
              <span class="pv-stat-val" id="pvStatRole">MEMBER</span>
              <span class="pv-stat-label">VAI TRÒ</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div><!-- /preview-col -->

</div><!-- /profile-outer -->

<!-- ── DECORATION MODAL ── -->
<div class="deco-modal-overlay" id="decoModalOverlay" onclick="overlayClose(event)">
  <div class="deco-modal">
    <div class="deco-modal-head">
      <div class="deco-modal-title">CHỌN KHUNG AVATAR (DECORATION)</div>
      <button class="deco-modal-close" onclick="closeDecoModal()">✕ ĐÓNG</button>
    </div>
    <div class="deco-modal-body">
      <!-- Preset grid -->
      <div>
        <div class="deco-gallery-label">CHỌN KHUNG</div>
        <div class="deco-preset-grid" id="decoPresetGrid">
          <div class="deco-preset-item" style="pointer-events:none">
            <div class="deco-preset-skeleton"></div>
            <span class="deco-preset-name" style="background:rgba(255,255,255,.05);width:40px;height:8px;border-radius:4px;">&nbsp;</span>
          </div>
          <div class="deco-preset-item" style="pointer-events:none">
            <div class="deco-preset-skeleton"></div>
            <span class="deco-preset-name" style="background:rgba(255,255,255,.05);width:40px;height:8px;border-radius:4px;">&nbsp;</span>
          </div>
        </div>
      </div>

      <!-- Sliders (shown when deco selected) -->
      <div class="deco-modal-sliders" id="decoSliders">
        <div class="deco-sliders-title">ĐIỀU CHỈNH VỊ TRÍ</div>
        <div class="deco-slider-row">
          <span class="deco-slider-label">SIZE</span>
          <input type="range" class="deco-slider" id="decoSize" min="60" max="200" value="130" oninput="onDecoChange()" />
          <span class="deco-slider-val" id="decoSizeVal">130%</span>
        </div>
        <div class="deco-slider-row">
          <span class="deco-slider-label">X</span>
          <input type="range" class="deco-slider" id="decoX" min="-40" max="40" value="0" oninput="onDecoChange()" />
          <span class="deco-slider-val" id="decoXVal">0px</span>
        </div>
        <div class="deco-slider-row">
          <span class="deco-slider-label">Y</span>
          <input type="range" class="deco-slider" id="decoY" min="-40" max="40" value="0" oninput="onDecoChange()" />
          <span class="deco-slider-val" id="decoYVal">0px</span>
        </div>
        <div style="display:flex;gap:8px;margin-top:4px;">
          <button class="upload-btn upload-btn-muted" style="display:inline-block;font-size:9px;padding:5px 12px;" onclick="resetDeco()">↺ RESET VỊ TRÍ</button>
        </div>
      </div>

      <!-- Apply button -->
      <div style="display:flex;gap:8px;justify-content:flex-end;padding-top:4px;">
        <button class="save-btn" style="font-size:10px;padding:8px 20px;" onclick="closeDecoModal()">✓ ÁP DỤNG</button>
      </div>
    </div>
  </div>
</div>

<script>
// ─── STATE ───────────────────────────────────────────
const BASE_USER = <?= json_encode($authedUser) ?>;
const DEFAULT_COLOR_TOP    = '#1a0a2e';
const DEFAULT_COLOR_BOTTOM = '#0a0814';
let profileData = { avatar: null, cover: null, nickname: null, bio: null, profile_color_top: null, profile_color_bottom: null, avatar_deco_url: null, avatar_deco_settings: null };

// ─── SYNC PREVIEW ────────────────────────────────────
function syncPreview() {
  const ni = document.getElementById('nicknameInput');
  const bi = document.getElementById('bioInput');
  const name = (ni && ni.value.trim()) ? ni.value.trim() : BASE_USER;

  // name + username
  const pvName = document.getElementById('pvName');
  const dp     = document.getElementById('displayNamePreview');
  if (pvName) pvName.textContent = name;
  if (dp)     dp.textContent     = name;

  // bio
  const pvBio = document.getElementById('pvBio');
  if (pvBio) {
    const bioText = bi ? bi.value.trim() : '';
    pvBio.innerHTML = bioText
      ? `<div class="pv-bio">${linkify(bioText).replace(/\n/g,'<br>')}</div>`
      : `<div class="pv-bio-empty">Chưa có tiểu sử.</div>`;
  }

  // gradient background
  applyProfileGradient();
}

// ─── PROFILE GRADIENT ────────────────────────────────
function applyProfileGradient() {
  const card = document.getElementById('pvCard');
  if (!card) return;
  const top    = profileData.profile_color_top    || DEFAULT_COLOR_TOP;
  const bottom = profileData.profile_color_bottom || DEFAULT_COLOR_BOTTOM;
  const body = card.querySelector('.pv-body');
  if (body) body.style.background = `linear-gradient(180deg, ${top} 0%, ${bottom} 100%)`;
}

// ─── COLOR PICKER HANDLERS ───────────────────────────
function onColorChange(which, hex) {
  if (which === 'top') {
    profileData.profile_color_top = hex;
    const sw = document.getElementById('colorTopSwatch');
    const lbl = document.getElementById('colorTopHex');
    if (sw)  sw.style.background = hex;
    if (lbl) lbl.textContent = hex;
  } else {
    profileData.profile_color_bottom = hex;
    const sw = document.getElementById('colorBottomSwatch');
    const lbl = document.getElementById('colorBottomHex');
    if (sw)  sw.style.background = hex;
    if (lbl) lbl.textContent = hex;
  }
  applyProfileGradient();
}

function resetColor(which) {
  if (which === 'top') {
    profileData.profile_color_top = null;
    const picker = document.getElementById('colorTopPicker');
    const sw     = document.getElementById('colorTopSwatch');
    const lbl    = document.getElementById('colorTopHex');
    if (picker) picker.value = DEFAULT_COLOR_TOP;
    if (sw)     sw.style.background = DEFAULT_COLOR_TOP;
    if (lbl)    lbl.textContent = DEFAULT_COLOR_TOP;
  } else {
    profileData.profile_color_bottom = null;
    const picker = document.getElementById('colorBottomPicker');
    const sw     = document.getElementById('colorBottomSwatch');
    const lbl    = document.getElementById('colorBottomHex');
    if (picker) picker.value = DEFAULT_COLOR_BOTTOM;
    if (sw)     sw.style.background = DEFAULT_COLOR_BOTTOM;
    if (lbl)    lbl.textContent = DEFAULT_COLOR_BOTTOM;
  }
  applyProfileGradient();
}

function applyColorPickerUI(top, bottom) {
  const topVal    = top    || DEFAULT_COLOR_TOP;
  const bottomVal = bottom || DEFAULT_COLOR_BOTTOM;

  const topPicker = document.getElementById('colorTopPicker');
  const topSwatch = document.getElementById('colorTopSwatch');
  const topHex    = document.getElementById('colorTopHex');
  if (topPicker) topPicker.value         = topVal;
  if (topSwatch) topSwatch.style.background = topVal;
  if (topHex)    topHex.textContent      = topVal;

  const botPicker = document.getElementById('colorBottomPicker');
  const botSwatch = document.getElementById('colorBottomSwatch');
  const botHex    = document.getElementById('colorBottomHex');
  if (botPicker) botPicker.value         = bottomVal;
  if (botSwatch) botSwatch.style.background = bottomVal;
  if (botHex)    botHex.textContent      = bottomVal;
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function linkify(text) {
  // Escape HTML first, then replace URLs with <a> tags
  const escaped = escHtml(text);
  return escaped.replace(
    /(https?:\/\/[^\s<>"']+)/g,
    '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
  );
}

// ─── LOAD PROFILE FROM SERVER ────────────────────────
async function loadProfile() {
  try {
    const res  = await fetch('api.php?action=get_profile');
    const json = await res.json();
    if (json.status !== 'ok') return;
    const d = json.data;

    profileData.avatar               = d.avatar               || null;
    profileData.cover                = d.cover                || null;
    profileData.nickname             = d.nickname             || null;
    profileData.bio                  = d.bio                  || null;
    profileData.profile_color_top    = d.profile_color_top    || null;
    profileData.profile_color_bottom = d.profile_color_bottom || null;
    profileData.avatar_deco_url      = d.avatar_deco_url      || null;
    profileData.avatar_deco_settings = d.avatar_deco_settings || null;

    if (profileData.avatar) applyAvatar(profileData.avatar);
    if (profileData.cover)  applyCover(profileData.cover);

    // Apply saved colors to UI pickers
    applyColorPickerUI(profileData.profile_color_top, profileData.profile_color_bottom);
    applyProfileGradient();

    // Load decoration from database
    if (profileData.avatar_deco_settings) loadDecoSettings(profileData.avatar_deco_settings);
    setDecoImages(profileData.avatar_deco_url);
    syncDecoSliders();
    applyDecoLayers();
    updateDecoBtn();
    // Re-render preset grid to mark the correct selected preset
    await loadDecoPresets();

    const ni = document.getElementById('nicknameInput');
    const bi = document.getElementById('bioInput');
    if (ni) ni.value = profileData.nickname || '';
    if (bi) bi.value = profileData.bio      || '';

    // Joined date
    const pvStatJoined = document.getElementById('pvStatJoined');
    if (pvStatJoined) {
      if (d.created_at) {
        const dt = new Date(d.created_at);
        pvStatJoined.textContent = dt.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
      } else {
        pvStatJoined.textContent = '—';
      }
    }

    // Role — 0=member, 1=admin, 2=hacker, 3=manager
    const pvStatRole = document.getElementById('pvStatRole');
    if (pvStatRole) {
      const roleMap = {
        0: { label: 'MEMBER',  color: '#ffffff' },
        1: { label: 'ADMIN',   color: '#ff3333' },
        2: { label: 'HACKER',  color: '#00ff88' },
        3: { label: 'MANAGER', color: '#ffcc00' },
      };
      const roleCode = Number(d.admin_rights ?? 0);
      const role = roleMap[roleCode] ?? roleMap[0];
      pvStatRole.textContent = role.label;
      pvStatRole.style.color = role.color;
      pvStatRole.style.fontWeight = 'bold';
      pvStatRole.style.letterSpacing = '1px';
    }

    syncPreview();
  } catch (e) { console.error('loadProfile error', e); }
}

// ─── AVATAR DECORATION ───────────────────────────────
let decoState = { enabled: true, size: 130, x: 0, y: 0 };
let decoPresets = []; // loaded from DB

// ── Modal open/close
function openDecoModal() {
  const overlay = document.getElementById('decoModalOverlay');
  if (overlay) overlay.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeDecoModal() {
  const overlay = document.getElementById('decoModalOverlay');
  if (overlay) overlay.classList.remove('open');
  document.body.style.overflow = '';
}
function overlayClose(e) {
  if (e.target === document.getElementById('decoModalOverlay')) closeDecoModal();
}

// ── Update the open-button label/thumb
function updateDecoBtn() {
  const btn   = document.getElementById('decoOpenBtn');
  const thumb = document.getElementById('decoThumbMini');
  if (!btn) return;
  const url = profileData.avatar_deco_url;
  if (url) {
    btn.childNodes[0].textContent = '🎨 ĐANG DÙNG: ';
    if (thumb) { thumb.src = url; thumb.classList.add('visible'); }
  } else {
    btn.childNodes[0].textContent = '🎨 CHỌN KHUNG AVATAR (DECORATION)';
    if (thumb) { thumb.src = ''; thumb.classList.remove('visible'); }
  }
}

// ── Apply deco image to all overlay <img> elements
function setDecoImages(url) {
  ['editorDecoImg', 'previewDecoImg'].forEach(id => {
    const img = document.getElementById(id);
    if (!img) return;
    if (url) { img.src = url; img.style.display = ''; }
    else     { img.src = ''; img.style.display = 'none'; }
  });
}

// ── Show/hide deco overlay layers
function applyDecoLayers() {
  const hasUrl = !!profileData.avatar_deco_url;
  ['editorDecoLayer', 'previewDecoLayer'].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    if (hasUrl) {
      el.classList.add('active');
      el.style.width  = decoState.size + '%';
      el.style.height = decoState.size + '%';
      el.style.setProperty('--deco-x', decoState.x + 'px');
      el.style.setProperty('--deco-y', decoState.y + 'px');
    } else {
      el.classList.remove('active');
    }
  });
}

// ── Sync slider UI values
function syncDecoSliders() {
  const sliders = document.getElementById('decoSliders');
  const sizeIn  = document.getElementById('decoSize');
  const xIn     = document.getElementById('decoX');
  const yIn     = document.getElementById('decoY');
  const sizeVal = document.getElementById('decoSizeVal');
  const xVal    = document.getElementById('decoXVal');
  const yVal    = document.getElementById('decoYVal');
  const hasUrl  = !!profileData.avatar_deco_url;

  // Show sliders only when a deco is selected
  if (sliders) sliders.classList.toggle('visible', hasUrl);

  if (sizeIn)  sizeIn.value = decoState.size;
  if (xIn)     xIn.value    = decoState.x;
  if (yIn)     yIn.value    = decoState.y;
  if (sizeVal) sizeVal.textContent = decoState.size + '%';
  if (xVal)    xVal.textContent    = decoState.x + 'px';
  if (yVal)    yVal.textContent    = decoState.y + 'px';
}

function onDecoChange() {
  decoState.size = Number(document.getElementById('decoSize')?.value  ?? 130);
  decoState.x    = Number(document.getElementById('decoX')?.value     ?? 0);
  decoState.y    = Number(document.getElementById('decoY')?.value     ?? 0);
  syncDecoSliders();
  applyDecoLayers();
  saveDecoSettings();
}

function resetDeco() {
  decoState.size = 130; decoState.x = 0; decoState.y = 0;
  syncDecoSliders();
  applyDecoLayers();
  saveDecoSettings();
}

// ── Encode/decode decoState as JSON string for DB storage
function saveDecoSettings() {
  profileData.avatar_deco_settings = JSON.stringify({
    enabled: !!profileData.avatar_deco_url,
    size: decoState.size, x: decoState.x, y: decoState.y,
  });
}

function loadDecoSettings(settingsJson) {
  if (!settingsJson) return;
  try {
    const d = JSON.parse(settingsJson);
    if (d) {
      if (d.size !== undefined) decoState.size = Number(d.size);
      if (d.x    !== undefined) decoState.x    = Number(d.x);
      if (d.y    !== undefined) decoState.y    = Number(d.y);
    }
  } catch {}
}

// ── Select a preset (url = '' or null means NONE)
function selectDecoPreset(url) {
  profileData.avatar_deco_url = url || null;
  setDecoImages(profileData.avatar_deco_url);
  syncDecoSliders();
  applyDecoLayers();
  saveDecoSettings();
  updateDecoBtn();
  // Update selected state in grid
  document.querySelectorAll('.deco-preset-item').forEach(el => {
    el.classList.toggle('selected', (el.dataset.url || '') === (url || ''));
  });
}

// ── Load presets from DB and build grid
async function loadDecoPresets() {
  const grid = document.getElementById('decoPresetGrid');
  if (!grid) return;

  let presets = [];
  try {
    const res  = await fetch('api.php?action=get_deco_presets');
    const json = await res.json();
    if (json.status === 'ok' && Array.isArray(json.data)) presets = json.data;
  } catch {}
  decoPresets = presets;

  // Render grid
  const userLetter = BASE_USER ? BASE_USER[0].toUpperCase() : '?';
  const currentUrl = profileData.avatar_deco_url || '';

  let html = '';

  // NONE card
  html += `<div class="deco-preset-item${currentUrl === '' ? ' selected' : ''}" data-url="" onclick="selectDecoPreset('')" title="Không dùng decoration">
    <div class="deco-preset-circle">
      <div class="dpc-none-icon">✕</div>
    </div>
    <span class="deco-preset-name">Không có</span>
  </div>`;

  // Preset cards
  for (const p of presets) {
    const sel = (p.url === currentUrl && currentUrl !== '') ? ' selected' : '';
    const safeUrl  = p.url.replace(/"/g, '%22');
    const safeName = p.name.replace(/</g, '&lt;').replace(/>/g, '&gt;');
    html += `<div class="deco-preset-item${sel}" data-url="${safeUrl}" onclick="selectDecoPreset('${safeUrl}')" title="${safeName}">
      <div class="deco-preset-circle">
        <div class="dpc-av">${userLetter}</div>
        <div class="dpc-deco"><img src="${safeUrl}" alt="${safeName}" crossorigin="anonymous" loading="lazy" onerror="this.style.opacity='.2'"/></div>
      </div>
      <span class="deco-preset-name">${safeName}</span>
    </div>`;
  }

  grid.innerHTML = html;
}

// ─── AVATAR ──────────────────────────────────────────
function applyAvatar(dataUrl) {
  // editor
  const preview     = document.getElementById('avatarPreview');
  const placeholder = document.getElementById('avatarPlaceholder');
  const rmBtn       = document.getElementById('rmAvatarBtn');
  const status      = document.getElementById('avatarStatus');
  if (preview)     { preview.src = dataUrl; preview.style.display = 'block'; }
  if (placeholder) placeholder.style.display = 'none';
  if (rmBtn)       rmBtn.style.display = 'inline-block';
  if (status)      status.textContent = '✓ Đã có ảnh đại diện';
  // preview panel
  const pvImg    = document.getElementById('pvAvImg');
  const pvLetter = document.getElementById('pvAvLetter');
  if (pvImg)    { pvImg.src = dataUrl; pvImg.style.display = 'block'; }
  if (pvLetter) pvLetter.style.display = 'none';
}

function clearAvatar() {
  const preview     = document.getElementById('avatarPreview');
  const placeholder = document.getElementById('avatarPlaceholder');
  const rmBtn       = document.getElementById('rmAvatarBtn');
  const fileInput   = document.getElementById('avatarFile');
  const status      = document.getElementById('avatarStatus');
  if (preview)     { preview.src = ''; preview.style.display = 'none'; }
  if (placeholder) placeholder.style.display = '';
  if (rmBtn)       rmBtn.style.display = 'none';
  if (fileInput)   fileInput.value = '';
  if (status)      status.textContent = 'Chưa có ảnh đại diện';
  // preview panel
  const pvImg    = document.getElementById('pvAvImg');
  const pvLetter = document.getElementById('pvAvLetter');
  if (pvImg)    { pvImg.src = ''; pvImg.style.display = 'none'; }
  if (pvLetter) pvLetter.style.display = '';
}

function compressImage(file, maxSize = 400) {
  return new Promise(resolve => {
    const reader = new FileReader();
    reader.onload = e => {
      const img = new Image();
      img.onload = () => {
        let w = img.width, h = img.height;
        if (w > maxSize || h > maxSize) {
          if (w > h) { h = Math.round(h * maxSize / w); w = maxSize; }
          else       { w = Math.round(w * maxSize / h); h = maxSize; }
        }
        const c = document.createElement('canvas');
        c.width = w; c.height = h;
        c.getContext('2d').drawImage(img, 0, 0, w, h);
        resolve(c.toDataURL('image/jpeg', 0.85));
      };
      img.onerror = () => resolve(e.target.result);
      img.src = e.target.result;
    };
    reader.onerror = () => resolve(null);
    reader.readAsDataURL(file);
  });
}

const GIF_MAX_BYTES = 15 * 1024 * 1024;
function gifSizeWarning(file, statusId) {
  const st = document.getElementById(statusId);
  if (!st) return true;
  if (file.size > GIF_MAX_BYTES) {
    st.textContent = '✗ GIF quá lớn (>' + (GIF_MAX_BYTES/1024/1024).toFixed(0) + 'MB), không thể lưu';
    st.style.color = '#ff4466'; return false;
  }
  if (file.size > 3 * 1024 * 1024) {
    st.textContent = '⚠ GIF ' + (file.size/1024/1024).toFixed(1) + 'MB — lưu có thể mất vài giây';
    st.style.color = '#ffaa00';
  } else {
    st.textContent = '✓ Đã chọn GIF (' + (file.size/1024).toFixed(0) + ' KB)';
    st.style.color = '#00ff99';
  }
  return true;
}

function loadAvatar(input) {
  const file = input.files[0]; if (!file) return;
  if (file.type === 'image/gif') {
    if (!gifSizeWarning(file, 'avatarStatus')) return;
    const reader = new FileReader();
    reader.onload = e => { profileData.avatar = e.target.result; applyAvatar(e.target.result); };
    reader.readAsDataURL(file);
  } else {
    compressImage(file, 400).then(data => { if (!data) return; profileData.avatar = data; applyAvatar(data); });
  }
}
function removeAvatar() { profileData.avatar = null; profileData.remove_avatar = true; clearAvatar(); }

// ─── COVER ───────────────────────────────────────────
function applyCover(dataUrl) {
  // editor
  const preview  = document.getElementById('coverPreview');
  const plHolder = document.getElementById('coverPlaceholder');
  const rmBtn    = document.getElementById('coverRemoveBtn');
  if (preview)  { preview.src = dataUrl; preview.style.display = 'block'; }
  if (plHolder) plHolder.style.display = 'none';
  if (rmBtn)    rmBtn.style.display = 'inline-block';
  // preview panel
  const pvCover = document.getElementById('pvCoverImg');
  if (pvCover) { pvCover.src = dataUrl; pvCover.style.display = 'block'; }
}

function clearCover() {
  const preview   = document.getElementById('coverPreview');
  const plHolder  = document.getElementById('coverPlaceholder');
  const rmBtn     = document.getElementById('coverRemoveBtn');
  const fileInput = document.getElementById('coverFile');
  if (preview)   { preview.src = ''; preview.style.display = 'none'; }
  if (plHolder)  plHolder.style.display = '';
  if (rmBtn)     rmBtn.style.display = 'none';
  if (fileInput) fileInput.value = '';
  // preview panel
  const pvCover = document.getElementById('pvCoverImg');
  if (pvCover) { pvCover.src = ''; pvCover.style.display = 'none'; }
}

function loadCover(input) {
  const file = input.files[0]; if (!file) return;
  if (file.type === 'image/gif') {
    if (!gifSizeWarning(file, 'coverStatus')) return;
    const reader = new FileReader();
    reader.onload = e => { profileData.cover = e.target.result; profileData.remove_cover = false; applyCover(e.target.result); };
    reader.readAsDataURL(file);
  } else {
    compressImage(file, 1280).then(data => { if (!data) return; profileData.cover = data; profileData.remove_cover = false; applyCover(data); });
  }
}
function removeCover() { profileData.cover = null; profileData.remove_cover = true; clearCover(); }

// ─── SAVE PROFILE ────────────────────────────────────
async function saveProfile() {
  const btn    = document.getElementById('saveBtn');
  const status = document.getElementById('saveStatus');
  const ni     = document.getElementById('nicknameInput');
  const bi     = document.getElementById('bioInput');

  btn.disabled = true; btn.textContent = '⏳ ĐANG LƯU...';
  status.className = ''; status.style.display = 'none';

  const payload = {
    nickname: (ni ? ni.value.trim() : null) || null,
    bio:      (bi ? bi.value.trim() : null) || null,
  };
  if (profileData.avatar !== undefined && profileData.avatar !== null) payload.avatar = profileData.avatar;
  else if (profileData.remove_avatar) payload.remove_avatar = true;
  if (profileData.cover !== undefined && profileData.cover !== null) payload.cover = profileData.cover;
  else if (profileData.remove_cover) payload.remove_cover = true;
  // Colors — always send (null means "reset to default")
  payload.profile_color_top    = profileData.profile_color_top    || null;
  payload.profile_color_bottom = profileData.profile_color_bottom || null;
  // Decoration
  saveDecoSettings();
  payload.avatar_deco_url      = profileData.avatar_deco_url      || null;
  payload.avatar_deco_settings = profileData.avatar_deco_settings || null;

  try {
    const res  = await fetch('api.php?action=save_profile', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
    const json = await res.json();
    if (json.status === 'ok') {
      status.textContent = '✓ ĐÃ LƯU THÀNH CÔNG'; status.className = 'ok'; status.style.display = 'block';
    } else {
      status.textContent = '✗ LỖI: ' + (json.message || 'Không lưu được'); status.className = 'err'; status.style.display = 'block';
    }
  } catch (e) {
    status.textContent = '✗ LỖI KẾT NỐI SERVER'; status.className = 'err'; status.style.display = 'block';
  }
  btn.disabled = false; btn.textContent = '💾 LƯU THAY ĐỔI';
  setTimeout(() => { status.style.display = 'none'; status.className = ''; }, 3500);
}

// ─── SYNC THEME FROM INDEX.PHP SETTINGS ─────────────
// Đọc webrat_settings từ localStorage (giống index.php) và áp dụng accent, bg, opacity
const SETTINGS_KEY = 'webrat_settings';
function loadSettings() { try { return JSON.parse(localStorage.getItem(SETTINGS_KEY) || '{}'); } catch { return {}; } }

function hslToHex(h, s, l) {
  s /= 100; l /= 100;
  const a = s * Math.min(l, 1 - l);
  const f = n => { const k = (n + h / 30) % 12; const c = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1); return Math.round(255 * c).toString(16).padStart(2, '0'); };
  return '#' + f(0) + f(8) + f(4);
}

let _rainbowTimer = null;
function startRainbow() {
  let hue = 0;
  _rainbowTimer = setInterval(() => {
    hue = (hue + 1) % 360;
    const color = hslToHex(hue, 100, 55);
    document.documentElement.style.setProperty('--accent', color);
    document.documentElement.style.setProperty('--glow', color + '66');
  }, 30);
}

function applyStoredSettings() {
  const s = loadSettings();

  // 1. Accent color / Rainbow
  if (s.rainbow) {
    startRainbow();
  } else if (s.accent) {
    document.documentElement.style.setProperty('--accent', s.accent);
    document.documentElement.style.setProperty('--glow', s.accent + '66');
  }

  // 2. Overlay opacity
  if (s.opacity !== undefined) {
    document.documentElement.style.setProperty('--bg-alpha', (Number(s.opacity) / 100).toFixed(2));
  }

  // 3. Background image / gradient
  const bgLayer = document.getElementById('bg-layer');
  if (bgLayer) {
    if (s.bgImage) {
      bgLayer.style.backgroundImage = `url(${s.bgImage})`;
      bgLayer.style.backgroundSize = 'cover';
      bgLayer.style.backgroundPosition = 'center';
    } else if (s.bgGradient) {
      bgLayer.style.backgroundImage = s.bgGradient;
      bgLayer.style.backgroundColor = s.bgColor || '#08050c';
    }
  }
}

// ─── INIT ────────────────────────────────────────────
applyStoredSettings();
syncDecoSliders();
applyDecoLayers();
loadDecoPresets(); // load gallery (presets load independently)
loadProfile();     // load saved profile + re-renders grid with selected state
</script>
</body>
</html>
