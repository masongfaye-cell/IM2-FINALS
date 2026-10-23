<?php
require __DIR__ . '/app/bootstrap.php';
must_staff();

$service = new User($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_post();
    try {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['user_id'] ?? 0);
        switch ($action) {
      case 'add':
        $service->register($_POST);
        flash('Patron account created. Share their initial password securely.');
        break;
      case 'update':
        $service->update($id, $_POST);
        flash('Member details updated.');
        break;
      case 'reset':
        $service->resetPassword($id, (string) ($_POST['password'] ?? ''));
        flash('Member login password updated. Share it securely.');
        break;
      case 'status':
        $service->status($id, (string) ($_POST['account_status'] ?? ''));
        flash('Member status updated.');
        break;
      case 'archive':
        $service->archive($id);
        flash('Member archived. Historical transactions remain available.');
        break;
      default:
        throw new DomainException('Unknown action.');
        }
  } catch (DomainException $e) {
    flash($e->getMessage(), 'error');
  } catch (Throwable $e) {
    error_log($e->getMessage());
    flash('Unable to save member changes.', 'error');
  }

    redirect('members.php');
}
$q = trim((string) ($_GET['q'] ?? ''));
$members = $service->listPatrons($q);

page_start('Members', 'members');
heading(
  'COMMUNITY DIRECTORY',
  'Member management',
  'Manage registered readers, access and borrowing eligibility.',
  '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#memberAddModal">+ Register patron</button>'
);
?>
<section class="panel">
  <div class="section-title">
    <div>
      <p class="eyebrow">READER DIRECTORY</p>
      <h2>Patrons <span class="title-counter"><?= count($members) ?></span></h2>
    </div>
  </div>
  <form class="toolbar" method="get">
    <div class="search-wrap">
      <span>⌕</span>
      <input type="search" class="form-control" name="q" value="<?= e($q) ?>" placeholder="Search name or email">
    </div>
    <button class="btn btn-outline-secondary">Search</button>
    <a class="btn btn-outline-dark" href="members.php">Reset</a>
  </form>
  <div class="table-scroll">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>MEMBER</th>
          <th>EMAIL</th>
          <th>PHONE</th>
          <th>REGISTERED</th>
          <th>ACTIVE LOANS</th>
          <th>STATUS</th>
          <th>ACTIONS</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $member): ?>
          <tr>
            <td>
              <div class="member-name">
                <span class="avatar avatar-light"><?= e(strtoupper(substr($member['first_name'], 0, 1))) ?></span>
                <strong><?= e($member['first_name'] . ' ' . $member['last_name']) ?></strong>
              </div>
            </td>
            <td><?= e($member['email']) ?></td>
            <td><?= e($member['phone'] ?: '—') ?></td>
            <td><?= e(substr($member['created_at'], 0, 10)) ?></td>
            <td><?= (int) $member['active_loans'] ?></td>
            <td><?= badge($member['account_status'] === 'Active' ? 'Active member' : $member['account_status']) ?></td>
            <td class="action-cell">
              <button type="button" class="btn btn-link btn-sm p-0 text-primary" data-bs-toggle="modal" data-bs-target="#editMemberModal_<?= (int) $member['user_id'] ?>">Edit</button>
              <button type="button" class="btn btn-link btn-sm p-0 text-secondary" data-bs-toggle="modal" data-bs-target="#resetMemberModal_<?= (int) $member['user_id'] ?>">Password</button>
              <button type="button" class="btn btn-link btn-sm p-0 text-warning" data-bs-toggle="modal" data-bs-target="#statusMemberModal_<?= (int) $member['user_id'] ?>"><?= $member['account_status'] === 'Active' ? 'Suspend' : 'Activate' ?></button>
              <button type="button" class="btn btn-link btn-sm p-0 text-danger" data-bs-toggle="modal" data-bs-target="#archiveMemberModal_<?= (int) $member['user_id'] ?>">Archive</button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$members): ?>
          <tr>
            <td colspan="7" class="empty">No patrons found. Register your first member to start lending.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<div class="modal fade" id="memberAddModal" tabindex="-1" aria-labelledby="memberAddModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="modal-header"><p class="eyebrow mb-0">MEMBER MANAGEMENT</p><h2 class="modal-title" id="memberAddModalLabel">Register new patron</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body row g-3">
        <div class="col-md-6"><label class="form-label">First name<input class="form-control" name="first_name" maxlength="50" required></label></div>
        <div class="col-md-6"><label class="form-label">Last name<input class="form-control" name="last_name" maxlength="50" required></label></div>
        <div class="col-12"><label class="form-label">Email address<input class="form-control" type="email" name="email" maxlength="100" required></label></div>
        <div class="col-12"><label class="form-label">Phone number<input class="form-control" name="phone" maxlength="20" placeholder="Optional"></label></div>
        <div class="col-12"><label class="form-label">Initial password (10+ characters)<input class="form-control" type="password" name="password" minlength="10" required autocomplete="new-password"></label><small class="text-muted">Give this password privately to the patron. They can change it in My profile.</small></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Create account</button></div>
    </form>
  </div>
</div>
<?php foreach($members as $m): ?>
<div class="modal fade" id="editMemberModal_<?= (int)$m['user_id'] ?>" tabindex="-1" aria-labelledby="editMemberModalLabel_<?= (int)$m['user_id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
      <div class="modal-header"><p class="eyebrow mb-0">MEMBER MANAGEMENT</p><h2 class="modal-title" id="editMemberModalLabel_<?= (int)$m['user_id'] ?>">Edit patron details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body row g-3">
        <div class="col-md-6"><label class="form-label">First name<input class="form-control" name="first_name" maxlength="50" value="<?= e($m['first_name']) ?>" required></label></div>
        <div class="col-md-6"><label class="form-label">Last name<input class="form-control" name="last_name" maxlength="50" value="<?= e($m['last_name']) ?>" required></label></div>
        <div class="col-12"><label class="form-label">Email address<input class="form-control" type="email" name="email" maxlength="100" value="<?= e($m['email']) ?>" required></label></div>
        <div class="col-12"><label class="form-label">Phone number<input class="form-control" name="phone" maxlength="20" value="<?= e($m['phone'] ?: '') ?>" placeholder="Optional"></label></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save changes</button></div>
    </form>
  </div>
</div>
<div class="modal fade" id="resetMemberModal_<?= (int)$m['user_id'] ?>" tabindex="-1" aria-labelledby="resetMemberModalLabel_<?= (int)$m['user_id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
      <div class="modal-header"><p class="eyebrow mb-0">ACCOUNT ACCESS</p><h2 class="modal-title" id="resetMemberModalLabel_<?= (int)$m['user_id'] ?>">Reset patron password</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body"><label class="form-label">New password (10+ characters)<input class="form-control" type="password" name="password" minlength="10" autocomplete="new-password" required></label><p class="small text-muted mb-0 mt-2">No passwords are shown or emailed by the system. Share the new password privately.</p></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Reset password</button></div>
    </form>
  </div>
</div>
<div class="modal fade" id="statusMemberModal_<?= (int)$m['user_id'] ?>" tabindex="-1" aria-labelledby="statusMemberModalLabel_<?= (int)$m['user_id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>"><input type="hidden" name="account_status" value="<?= $m['account_status']==='Active'?'Suspended':'Active' ?>">
      <div class="modal-header"><p class="eyebrow mb-0">ACCESS CONTROL</p><h2 class="modal-title" id="statusMemberModalLabel_<?= (int)$m['user_id'] ?>">Change member status</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body"><p class="mb-0">Suspended patrons cannot sign in or borrow new books. Their existing loans remain trackable.</p></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Confirm change</button></div>
    </form>
  </div>
</div>
<div class="modal fade" id="archiveMemberModal_<?= (int)$m['user_id'] ?>" tabindex="-1" aria-labelledby="archiveMemberModalLabel_<?= (int)$m['user_id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
      <div class="modal-header"><p class="eyebrow mb-0">MEMBER RECORD</p><h2 class="modal-title" id="archiveMemberModalLabel_<?= (int)$m['user_id'] ?>">Archive this member?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body"><p class="mb-0">Members with active loans or unpaid fees cannot be archived. Their loan history is always retained.</p></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Archive patron</button></div>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php page_end(); ?>
