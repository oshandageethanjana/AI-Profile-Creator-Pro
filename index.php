<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

if (!auth_current_user()) auth_try_refresh_access();
$user = auth_current_user();
$csrf = csrf_ensure_cookie();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>ProfileAI — Premium AI Profile Image Generator</title>
  <meta name="description" content="Premium AI profile images with background removal, blur blend, studio styles, and pro exports.">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@500;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= e(app_path('/assets/css/app.css')) ?>">
</head>
<body>
  <div class="orb-layer" aria-hidden="true">
    <div class="orbs">
      <div class="orb o1"></div>
      <div class="orb o2"></div>
      <div class="orb o3"></div>
    </div>
  </div>

  <div class="app-root">
    <header class="topbar">
      <a class="brand" href="<?= e(app_path('/')) ?>">
        <div class="mark"></div>
        <div>
          <strong>ProfileAI</strong> <span>Studio</span>
        </div>
      </a>
      <div class="topbar-spacer"></div>
      <div id="userSlot" style="display:flex;align-items:center;gap:10px;"></div>
    </header>

    <div class="shell">
      <aside class="sidebar">
        <div class="panel">
          <div class="panel-hd">
            <div>
              <h3>Create</h3>
              <p>Upload. Style. Export.</p>
            </div>
            <div class="chip" title="Security status">
              <i data-lucide="shield"></i>
              Secure
            </div>
          </div>
          <div class="panel-body">
            <div class="section">
              <div class="label">Upload</div>
              <div class="dropzone" id="dropzone">
                <input id="fileInput" type="file" accept="image/jpeg,image/png,image/webp" style="display:none">
                <div class="dz-icon"><i data-lucide="upload"></i></div>
                <div class="dz-title">Upload a photo</div>
                <p class="dz-sub">JPG, PNG, WEBP · Max 10MB · Best results on clear portraits</p>
              </div>
            </div>

            <div class="section">
              <div class="label">Templates</div>
              <div class="tpl-grid" id="tplGrid"></div>
              <div style="margin-top:8px;color:var(--text-3);font-size:12px;line-height:1.4;">
                Pick a look, then upload. You can switch templates anytime.
              </div>
            </div>

            <div class="section">
              <div class="label">Background</div>
              <div class="opt-grid" id="bgGrid">
                <div class="opt selected" data-bg="blur" data-title="Blur blend">
                  <i data-lucide="droplets"></i>
                  <div><div class="t">Blur blend</div><div class="s">Bokeh depth separation</div></div>
                </div>
                <div class="opt" data-bg="studio" data-title="Studio">
                  <i data-lucide="aperture"></i>
                  <div><div class="t">Studio</div><div class="s">Soft spotlight</div></div>
                </div>
                <div class="opt" data-bg="linkedin" data-title="LinkedIn">
                  <i data-lucide="briefcase"></i>
                  <div><div class="t">LinkedIn</div><div class="s">Clean professional</div></div>
                </div>
                <div class="opt" data-bg="solid" data-title="Solid">
                  <i data-lucide="square"></i>
                  <div><div class="t">Solid</div><div class="s">Brand color</div></div>
                </div>
              </div>
              <div style="margin-top:10px;display:flex;gap:10px;align-items:center;">
                <input id="colorInput" type="color" value="#0b0b0b" style="width:46px;height:38px;border-radius:12px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.04);">
                <div style="flex:1">
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <div style="font-weight:900;font-size:13px">Blur strength</div>
                    <div style="color:rgba(244,244,245,.6);font-size:12px"><span id="blurVal">52</span></div>
                  </div>
                  <input id="blurRange" class="slider" type="range" min="8" max="90" value="52">
                </div>
              </div>
            </div>

            <div class="section">
              <div class="label">Framing</div>
              <div style="display:flex;flex-direction:column;gap:8px;">
                <div style="font-size:12px;color:var(--text-3);">
                  Drag the photo in the square to reposition. Use the slider to zoom in or out.
                </div>
                <div>
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <div style="font-weight:900;font-size:13px">Size</div>
                    <div style="color:rgba(244,244,245,.6);font-size:12px"><span id="frameVal">100</span>%</div>
                  </div>
                  <input id="frameRange" class="slider" type="range" min="70" max="180" value="100">
                </div>
                <div>
                  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <div style="font-weight:900;font-size:13px">Rotate</div>
                    <div style="color:rgba(244,244,245,.6);font-size:12px"><span id="rotVal">0</span>°</div>
                  </div>
                  <input id="rotRange" class="slider" type="range" min="-25" max="25" value="0">
                </div>
              </div>
            </div>

            <div class="section">
              <div class="label">Effects</div>
              <div class="opt-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
                <button class="opt selected" id="fxShadow" type="button">
                  <i data-lucide="layers"></i>
                  <div><div class="t">Shadow</div><div class="s">Depth separation</div></div>
                </button>
                <button class="opt" id="fxOutline" type="button">
                  <i data-lucide="scan"></i>
                  <div><div class="t">Outline</div><div class="s">Clean edge</div></div>
                </button>
                <button class="opt" id="fxGlow" type="button">
                  <i data-lucide="sparkles"></i>
                  <div><div class="t">Glow</div><div class="s">Soft highlight</div></div>
                </button>
              </div>
            </div>

            <div class="section">
              <div class="label">Stickers</div>
              <div class="opt-grid">
                <button class="opt" id="stCircleGold" type="button">
                  <i data-lucide="circle"></i>
                  <div><div class="t">Circle gold</div><div class="s">Premium ring</div></div>
                </button>
                <button class="opt" id="stCircleMono" type="button">
                  <i data-lucide="circle-dot"></i>
                  <div><div class="t">Circle mono</div><div class="s">Minimal</div></div>
                </button>
                <button class="opt" id="stGlow" type="button">
                  <i data-lucide="zap"></i>
                  <div><div class="t">Glow</div><div class="s">Blur glow</div></div>
                </button>
                <button class="opt" id="stBwGold" type="button">
                  <i data-lucide="contrast"></i>
                  <div><div class="t">B&W gold</div><div class="s">High contrast</div></div>
                </button>
              </div>
              <div class="sticker-preview-block">
                <div class="sticker-preview-frame">
                  <canvas id="stickerPreview" width="220" height="220"></canvas>
                </div>
                <div class="sticker-actions">
                  <button class="btn btn-outline" id="btnStickerPng" type="button"><i data-lucide="download"></i>Sticker PNG</button>
                  <button class="btn btn-outline" id="btnStickerWebp" type="button"><i data-lucide="download-cloud"></i>Sticker WebP</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </aside>

      <main class="stage">
        <div class="panel">
          <div class="stage-top">
            <div>
              <div class="title">Editor</div>
              <div class="hint">Drag & drop upload to begin · No raw errors shown</div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
              <div class="chip"><i data-lucide="sparkles"></i><span id="bgMode">Blur blend</span></div>
              <div class="chip"><i data-lucide="activity"></i><span id="statusLine">Idle</span></div>
            </div>
          </div>

          <div class="canvas-wrap">
            <div class="canvas-frame">
              <canvas id="editorCanvas"></canvas>
              <div class="canvas-loader" id="canvasLoader" aria-hidden="true">
                <div class="loader-ring"></div>
                <div class="loader-mark">
                  <div class="mark"></div>
                </div>
              </div>
            </div>
          </div>

          <div class="stage-bottom">
            <div style="display:flex;gap:10px;align-items:center;">
              <button class="btn btn-primary" id="btnGenerate"><i data-lucide="sparkles"></i>Generate</button>
              <button class="btn btn-outline" id="btnExport"><i data-lucide="download"></i>Export PNG</button>
              <button class="btn btn-outline" id="btnExportJpg"><i data-lucide="image"></i>Export JPG</button>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
              <button class="btn btn-outline" id="btnUpgrade"><i data-lucide="crown"></i>Upgrade to PRO</button>
            </div>
          </div>
        </div>
      </main>

      <aside class="rightbar">
        <div class="panel">
          <div class="panel-hd">
            <div>
              <h3>Recents</h3>
              <p>Latest generations</p>
            </div>
            <a class="btn btn-ghost" href="<?= e(app_path('/dashboard.php')) ?>" style="height:34px;padding:0 12px"><i data-lucide="layout-grid"></i>All</a>
          </div>
          <div class="panel-body">
            <div class="list" id="recentList"></div>
          </div>
        </div>
      </aside>
    </div>
  </div>

  <!-- Full-screen processing overlay -->
  <div class="processing-overlay" id="processingOverlay" aria-hidden="true">
    <div class="processing-inner">
      <div class="processing-ring-wrap">
        <div class="processing-ring"></div>
        <div class="processing-logo">
          <div class="mark"></div>
        </div>
      </div>
      <div class="processing-text">
        <span id="processingTyping"></span><span class="processing-cursor" aria-hidden="true">|</span>
      </div>
    </div>
  </div>

  <div class="toast-host" id="toastHost" aria-live="polite" aria-relevant="additions removals"></div>

  <script>
    window.__BOOT__ = <?=
      json_encode(
        ['csrf' => $csrf, 'user' => $user, 'basePath' => app_base_path()],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
      );
    ?>;
  </script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
  <script src="<?= e(app_path('/assets/js/editor.js')) ?>"></script>
  <script src="<?= e(app_path('/assets/js/app.js')) ?>"></script>
</body>
</html>

