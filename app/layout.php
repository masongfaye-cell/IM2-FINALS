<?php
function page_start(string $title, string $active = ''): void {
    global $config, $pdo;
    $viewer = user();
    if ($viewer && !is_staff()) sync_notifications($pdo, current_id(), false);
    $unread = $viewer && !is_staff() ? unread_count($pdo, current_id()) : 0;
    $alerts = flashes();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0e1630">
  <title><?= e($title) ?> | LibraDesk</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/bootstrap-overrides.css">
</head>
<body class="bg-body-tertiary">
<div class="app-shell">
  <?php if ($viewer): ?>
    <aside class="sidebar">
      <a class="brand" href="index.php">
        <span class="brand-mark">▥</span>
        <span>LIBRA<span class="orange">DESK</span><small>LIBRARY OS</small></span>
      </a>
      <div class="side-label">WORKSPACE</div>
      <nav class="side-nav" aria-label="Main navigation">
        <a class="<?= $active === 'dashboard' ? 'selected' : '' ?>" href="index.php"><span>◫</span> Overview</a>
        <a class="<?= $active === 'books' ? 'selected' : '' ?>" href="books.php"><span>▤</span> Book catalog</a>
        <a class="<?= $active === 'loans' ? 'selected' : '' ?>" href="loans.php"><span>⇄</span> <?= is_staff() ? 'Circulation' : 'My borrowing' ?></a>
        <?php if (is_staff()): ?>
          <a class="<?= $active === 'members' ? 'selected' : '' ?>" href="members.php"><span>♙</span> Members</a>
          <a class="<?= $active === 'reports' ? 'selected' : '' ?>" href="reports.php"><span>▥</span> Reports & exports</a>
          <a class="<?= $active === 'activity' ? 'selected' : '' ?>" href="activity.php"><span>◷</span> Activity log</a>
        <?php else: ?>
          <a class="<?= $active === 'notifications' ? 'selected' : '' ?>" href="notifications.php">
            <span>♢</span> Notifications <?= $unread ? '<strong class="nav-count">' . $unread . '</strong>' : '' ?>
          </a>
        <?php endif; ?>
      </nav>
      <div class="side-bottom">
        <span class="side-dot"></span> <?= e($config['library_name']) ?>
        <small>Centralized library management</small>
      </div>
    </aside>
  <?php endif; ?>

  <div class="page <?= $viewer ? '' : 'page-auth' ?>">
    <?php if ($viewer): ?>
      <header class="topbar">
        <div class="breadcrumb">LIBRADESK <span>/</span> <?= e($title) ?></div>
        <div class="top-actions">
          <span class="role-tag"><?= is_staff() ? 'LIBRARIAN' : 'PATRON' ?></span>
          <a class="profile-link" href="profile.php" title="My profile">
            <span class="avatar"><?= e(strtoupper(substr($viewer['first_name'], 0, 1))) ?></span>
            <span><?= e($viewer['first_name']) ?></span>
          </a>
          <form action="logout.php" method="post">
            <?= csrf_field() ?>
            <button class="text-button" type="submit">Log out ↗</button>
          </form>
        </div>
      </header>
    <?php endif; ?>
    <main class="content">
      <?php foreach ($alerts as $alert): ?>
        <?php
        $variant = match ($alert['type'] ?? 'success') {
          'error' => 'danger',
          'warning' => 'warning',
          'info' => 'info',
          default => 'success',
        };
        ?>
        <div class="alert alert-<?= $variant ?> alert-dismissible fade show" role="alert">
          <?= e($alert['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endforeach; ?>
<?php
}
function page_end(): void { ?>
    </main>
    <footer class="footer">
      LibraDesk <span>·</span> Organized knowledge, effortless circulation <span>·</span> <?= date('Y') ?>
    </footer>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>
<?php }
function heading(string $eyebrow, string $title, string $subtitle, string $actionHtml = ''): void { ?>
<div class="page-heading">
  <div>
    <p class="eyebrow"><?= e($eyebrow) ?></p>
    <h1><?= e($title) ?></h1>
    <p class="subtitle"><?= e($subtitle) ?></p>
  </div>
  <?= $actionHtml ?>
</div>
<?php }
function badge(string $label): string {
  return '<span class="badge ' . alert_class($label) . '">' . e($label) . '</span>';
}
