<?php
require __DIR__.'/app/bootstrap.php';
if (user()) redirect('index.php');
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_post();
    $email=strtolower(trim((string)($_POST['email']??'')));
    $password=(string)($_POST['password']??'');
    $attempts=$_SESSION['failed_logins']??['count'=>0,'since'=>time()];
    if (time()-$attempts['since']>900) $attempts=['count'=>0,'since'=>time()];
    if ($attempts['count']>=8) flash('Too many attempts. Try again in 15 minutes.','error');
    else {
        $stmt=$pdo->prepare("SELECT u.*,r.role_name FROM users u JOIN roles r USING(role_id)
          WHERE u.email=? AND u.account_status='Active' AND u.archived_at IS NULL LIMIT 1");
        $stmt->execute([$email]); $account=$stmt->fetch();
        if ($account && $account['password_hash'] && password_verify($password,$account['password_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['failed_logins']);
            if (password_needs_rehash($account['password_hash'],PASSWORD_DEFAULT))
                $pdo->prepare('UPDATE users SET password_hash=? WHERE user_id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$account['user_id']]);
            $_SESSION['user']=array_intersect_key($account,array_flip(['user_id','first_name','last_name','email','role_name']));
            log_action($pdo,'Signed in');
            redirect('index.php');
        }
        $attempts['count']++; $_SESSION['failed_logins']=$attempts;
        flash('Invalid email or password, or this account is suspended.','error');
    }
    redirect('login.php');
}
page_start('Sign in');
?>
<div class="auth-layout"><section class="auth-message"><div class="auth-logo"><span>▥</span> LIBRA<span>DESK</span></div>
<p class="eyebrow">WELCOME TO A SMARTER LIBRARY</p><h1>Every book.<br>Every borrower.<br><em>In perfect order.</em></h1>
<p>Centralize circulation, protect your catalog, and spend more time connecting readers with the stories they love.</p>
<div class="auth-features"><span>✦ Digital catalog</span><span>✦ Real-time availability</span><span>✦ Smart overdue tracking</span></div></section>
<section class="auth-panel"><div class="auth-card"><div class="mini-icon">▣</div><p class="eyebrow">SECURE ACCESS</p><h2>Welcome back</h2><p class="muted">Sign in to your LibraDesk account.</p>
<form method="post" class="form-stack"><?= csrf_field() ?>
<label>Email address<input type="email" name="email" placeholder="you@library.org" autocomplete="username" required maxlength="100"></label>
<label>Password<input type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required></label>
<button class="button primary wide" type="submit">Sign in <span>→</span></button></form>
<p class="auth-help">New librarian? <a href="setup.php">Initial setup</a><br>Patrons receive an account from library staff.</p></div></section></div>
<?php page_end(); ?>
