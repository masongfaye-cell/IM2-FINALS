<?php
require __DIR__ . '/app/bootstrap.php';
must_login();
if (is_staff()) {
  redirect('index.php');
}
sync_notifications($pdo, current_id(), false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    $pdo->prepare('UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL')->execute([current_id()]);
  flash('All notifications marked as read.');
  redirect('notifications.php');
}
$notes = rows(
  $pdo,
  "SELECT n.*,l.status AS loan_status
  FROM notifications n JOIN loans l USING(loan_id)
  WHERE n.user_id=? AND l.status='Active'
  ORDER BY n.created_at DESC LIMIT 100",
  [current_id()]
);

page_start('Notifications', 'notifications');
heading(
  'DUE DATE REMINDERS',
  'Notifications',
  'Automatic reminders for books that are due soon or overdue.',
  $notes
    ? '<form method="post">' . csrf_field() . '<button class="button secondary">Mark all as read</button></form>'
    : ''
);
?>
<div class="panel">
  <div class="section-title">
    <div>
      <p class="eyebrow">CURRENT ALERTS</p>
      <h2>Loan reminders</h2>
    </div>
  </div>
  <?php foreach ($notes as $note): ?>
    <article class="notification <?= !$note['read_at'] ? 'unread' : '' ?>">
      <span class="notice-icon"><?= $note['kind'] === 'overdue' ? '!' : '◷' ?></span>
      <div>
        <strong><?= e($note['kind'] === 'overdue' ? 'Overdue book' : 'Upcoming return') ?></strong>
        <p><?= e($note['message']) ?></p>
        <small><?= e($note['created_at']) ?></small>
      </div>
      <?= !$note['read_at'] ? '<span class="unread-mark"></span>' : '' ?>
    </article>
  <?php endforeach; ?>
  <?php if (!$notes): ?>
    <p class="empty">You're all caught up. No active loan reminders right now.</p>
  <?php endif; ?>
</div>
<?php page_end(); ?>
