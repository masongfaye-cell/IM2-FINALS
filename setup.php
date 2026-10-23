<?php
require __DIR__.'/app/bootstrap.php';
$staffCount=(int)scalar($pdo,"SELECT COUNT(*) FROM users u JOIN roles r USING(role_id) WHERE r.role_name='Staff' AND u.password_hash IS NOT NULL");
if ($staffCount) { flash('Initial setup is complete. Sign in as a librarian.'); redirect('login.php'); }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_post();
    $key=(string)($_POST['setup_key']??'');
    $name=trim((string)($_POST['first_name']??'')); $last=trim((string)($_POST['last_name']??''));
    $email=strtolower(trim((string)($_POST['email']??''))); $pass=(string)($_POST['password']??'');
    if (!$config['setup_key'] || $config['setup_key']==='REPLACE_WITH_A_LONG_RANDOM_SECRET' || !hash_equals((string)$config['setup_key'],$key))
        flash('Invalid setup key. Configure a strong setup_key in config.local.php first.','error');
    elseif (!$name || !$last || strlen($name)>50 || strlen($last)>50 || !validate_email($email) || strlen($email)>100 || strlen($pass)<12)
        flash('Enter valid names, email and a password at least 12 characters long.','error');
    else {
        try {
            $pdo->beginTransaction();
            $role=(int)scalar($pdo,"SELECT role_id FROM roles WHERE role_name='Staff'");
            if (!$role) throw new DomainException('Import schema.sql or the upgrade migration first.');
            $stmt=$pdo->prepare("INSERT INTO users(role_id,first_name,last_name,email,password_hash,account_status) VALUES (?,?,?,?,?,'Active')");
            $stmt->execute([$role,$name,$last,$email,password_hash($pass,PASSWORD_DEFAULT)]);
            $id=(int)$pdo->lastInsertId(); log_action($pdo,'Created first librarian account',$id);
            $pdo->commit(); flash('Librarian account created. Please sign in.'); redirect('login.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack(); flash($e instanceof PDOException?'Could not create the account. Check if that email is already registered.':$e->getMessage(),'error');
        }
    }
    redirect('setup.php');
}
page_start('Initial setup'); ?>
<div class="setup-wrap"><div class="panel"><p class="eyebrow">FIRST-RUN SETUP</p><h1>Create librarian account</h1><p class="muted">For new installations only. Before continuing, import the SQL schema and set a private setup key in <code>config.local.php</code>.</p>
<form method="post" class="form-grid"><?= csrf_field() ?>
<label>First name<input name="first_name" maxlength="50" required></label><label>Last name<input name="last_name" maxlength="50" required></label>
<label class="full">Email<input type="email" name="email" required></label>
<label class="full">Password (12+ characters)<input type="password" name="password" minlength="12" required></label>
<label class="full">Private setup key<input type="password" name="setup_key" required></label>
<div class="full"><button class="button primary" type="submit">Create librarian account</button> <a class="button ghost" href="login.php">Back to sign in</a></div>
</form></div></div>
<?php page_end(); ?>
