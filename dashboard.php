<?php
//dashboard 
declare(strict_types=1);

require_once __DIR__ . 'page.php';
require_once __DIR__ . 'database.php';

$boot = page_boot();
$u = $boot['user'];
if (!$u) {
    header('Location: ' . app_path('/auth/login.php'));
    exit;
}

$images = db_all('SELECT id, public_id, processed_path, thumb_path, created_at FROM images WHERE user_id=? ORDER BY id DESC LIMIT 30', [(int)$u['id']]);
$downloads = db_all('SELECT d.id, d.format, d.width, d.height, d.is_pro_export, d.created_at, i.public_id FROM downloads d JOIN images i ON i.id=d.image_id WHERE d.user_id=? ORDER BY d.id DESC LIMIT 30', [(int)$u['id']]);

page_head('Dashboard', 'Your creations, downloads and subscription.');
page_topbar($u);
?>

<div class="shell" style="grid-template-columns: 1fr 1fr; gap:14px; padding:14px;">
  <div class="panel" style="min-height:0; overflow:hidden;">
    <div class="panel-hd">
      <div>
        <h3>Account</h3>
        <p><?= e((string)$u['email']) ?></p>
      </div>
      <div style="display:flex;gap:10px;align-items:center;">
        <?php if (($u['plan'] ?? 'free') === 'pro'): ?>
          <span class="chip"><i data-lucide="crown"></i>PRO</span>
        <?php else: ?>
          <a class="btn btn-primary" href="<?= e(app_path('/pro/upgrade.php')) ?>" style="height:34px;"><i data-lucide="crown"></i>Upgrade</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="panel-body">
      <div style="display:grid;gap:10px;">
        <div class="opt" style="cursor:default;">
          <i data-lucide="badge-check"></i>
          <div>
            <div class="t">Email</div>
            <div class="s"><?= $u['email_verified_at'] ? 'Verified' : 'Not verified' ?></div>
          </div>
        </div>
        <div class="opt" style="cursor:default;">
          <i data-lucide="calendar"></i>
          <div>
            <div class="t">Plan</div>
            <div class="s"><?= e(strtoupper((string)$u['plan'])) ?><?= (!empty($u['pro_ends_at']) ? ' · Ends ' . e((string)$u['pro_ends_at']) : '') ?></div>
          </div>
        </div>
        <div class="opt" style="cursor:default;">
          <i data-lucide="shield"></i>
          <div>
            <div class="t">Sessions</div>
            <div class="s">JWT access + refresh session</div>
          </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a class="btn btn-outline" href="<?= e(app_path('/')) ?>"><i data-lucide="sparkles"></i>Open Studio</a>
          <a class="btn btn-ghost" href="<?= e(app_path('/pro/redeem.php')) ?>"><i data-lucide="ticket"></i>Redeem PRO code</a>
        </div>
      </div>
    </div>
  </div>

  <div class="panel" style="min-height:0; overflow:hidden;">
    <div class="panel-hd">
      <div>
        <h3>Downloads</h3>
        <p>Recent exports (free and PRO)</p>
      </div>
    </div>
    <div class="panel-body">
      <div class="list">
        <?php if (!$downloads): ?>
          <div style="color:var(--text-3);font-size:12px;">No downloads yet.</div>
        <?php endif; ?>
        <?php foreach ($downloads as $d): ?>
          <div class="thumb" style="cursor:default;">
            <div class="dz-icon"><i data-lucide="<?= $d['is_pro_export'] ? 'crown' : 'download' ?>"></i></div>
            <div class="meta">
              <strong><?= e((string)$d['public_id']) ?> · <?= e(strtoupper((string)$d['format'])) ?></strong>
              <span><?= (int)$d['width'] ?>×<?= (int)$d['height'] ?> · <?= e((string)$d['created_at']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="panel" style="grid-column: 1 / -1; min-height:0; overflow:hidden;">
    <div class="panel-hd">
      <div>
        <h3>My creations</h3>
        <p>Recent generations</p>
      </div>
      <a class="btn btn-outline" href="<?= e(app_path('/')) ?>" style="height:34px;"><i data-lucide="plus"></i>New</a>
    </div>
    <div class="panel-body">
      <div style="display:grid;grid-template-columns: repeat(6, minmax(0, 1fr));gap:10px;">
        <?php if (!$images): ?>
          <div style="color:var(--text-3);font-size:12px;">No creations yet.</div>
        <?php endif; ?>
        <?php foreach ($images as $img): ?>
          <a class="thumb" href="<?= e(app_path((string)$img['processed_path'])) ?>" target="_blank" rel="noreferrer" style="flex-direction:column;align-items:stretch;gap:10px;">
            <img src="<?= e(app_path((string)($img['thumb_path'] ?: $img['processed_path']))) ?>" alt="">
            <div class="meta">
              <strong><?= e((string)$img['public_id']) ?></strong>
              <span><?= e((string)$img['created_at']) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php page_tail($boot); ?>

