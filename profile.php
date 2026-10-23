<?php
require __DIR__ . '/app/bootstrap.php';
must_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_post();
  $current = (string) ($_POST['current_password'] ?? '');
  $password = (string) ($_POST['new_password'] ?? '');
  $hash = scalar($pdo, 'SELECT password_hash FROM users WHERE user_id=?', [current_id()]);

  if (!password_verify($current, (string) $hash)) {
    flash('Current password is incorrect.', 'error');
  } elseif (strlen($password) < 10) {
    flash('New password must contain at least 10 characters.', 'error');
  } elseif ($password !== ($_POST['confirm_password'] ?? null)) {
    flash('New passwords do not match.', 'error');
  } else {
    $pdo->prepare('UPDATE users SET password_hash=? WHERE user_id=?')
      ->execute([password_hash($password, PASSWORD_DEFAULT), current_id()]);
    log_action($pdo, 'Changed account password');
    flash('Password updated successfully.');
  }

    redirect('profile.php');
}
page_start('My profile', 'profile');
heading('ACCOUNT SETTINGS', 'My profile', 'Review your account and update your password.');
?>
<div class="two-panels">
  <section class="panel">
    <p class="eyebrow">ACCOUNT DETAILS</p>
    <h2><?= e(user()['first_name'] . ' ' . user()['last_name']) ?></h2>
    <div class="detail-list">
      <div><span>Email</span><strong><?= e(user()['email']) ?></strong></div>
      <div><span>Account type</span><strong><?= is_staff() ? 'Librarian' : 'Patron' ?></strong></div>
      <div><span>Library</span><strong><?= e($config['library_name']) ?></strong></div>
    </div>
  </section>
  <section class="panel">
    <p class="eyebrow">SECURITY</p>
    <h2>Change password</h2>
    <form method="post" class="form-stack">
      <?= csrf_field() ?>
      <label>Current password<input name="current_password" type="password" required></label>
      <label>New password<input name="new_password" type="password" minlength="10" required></label>
      <label>Confirm new password<input name="confirm_password" type="password" minlength="10" required></label>
      <button class="button primary" type="submit">Update password</button>
    </form>
  </section>
</div>
<?php page_end(); ?>
