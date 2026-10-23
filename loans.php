<?php
require __DIR__ . '/app/bootstrap.php';
must_login();

$service = new Loan($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  must_staff();
  verify_post();
    try {
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['loan_id'] ?? 0);
        switch ($action) {
      case 'issue':
        $service->issue(
          (int) ($_POST['book_id'] ?? 0),
          (int) ($_POST['patron_id'] ?? 0),
          current_id(),
          (string) ($_POST['due_date'] ?? '')
        );
        flash('Book checked out successfully.');
        break;
      case 'return':
        $service->returnBook($id);
        flash('Return processed and any late fee assessed.');
        break;
      case 'extend':
        $service->changeDue($id, (string) ($_POST['due_date'] ?? ''));
        flash('Due date updated.');
        break;
      case 'pay':
      case 'waive':
        $service->settle($id, (float) ($_POST['amount'] ?? 0), $action);
        flash($action === 'pay' ? 'Payment recorded.' : 'Fine waiver recorded.');
        break;
      case 'archive':
        $service->archive($id);
        flash('Returned loan archived.');
        break;
      default:
        throw new DomainException('Unknown action.');
        }
  } catch (DomainException $e) {
    flash($e->getMessage(), 'error');
  } catch (Throwable $e) {
    error_log($e->getMessage());
    flash('Could not process this circulation transaction.', 'error');
  }

    redirect('loans.php');
}
$search = trim((string) ($_GET['q'] ?? ''));
$state = (string) ($_GET['status'] ?? '');
$loans = $service->list(is_staff() ? null : current_id(), $search, $state);
$available = is_staff()
  ? rows($pdo, "SELECT book_id,title,author,isbn FROM books WHERE archived_at IS NULL AND status='Available' ORDER BY title")
  : [];
$patrons = is_staff()
  ? rows(
    $pdo,
    "SELECT u.user_id,u.first_name,u.last_name FROM users u JOIN roles r USING(role_id)
    WHERE r.role_name='Patron' AND u.account_status='Active' AND u.archived_at IS NULL ORDER BY u.last_name,u.first_name"
  )
  : [];
$activeCount = count(array_filter($loans, fn ($loan) => $loan['status'] === 'Active'));
$overdueCount = count(array_filter($loans, fn ($loan) => loan_display_status($loan) === 'Overdue'));

page_start(is_staff() ? 'Circulation' : 'My borrowing', 'loans');
heading(
  is_staff() ? 'CIRCULATION DESK' : 'PERSONAL LOAN HISTORY',
  is_staff() ? 'Circulation & loans' : 'My borrowing',
  is_staff() ? 'Issue, return and track books with live overdue status.' : 'Keep track of every book you have borrowed.',
  is_staff()
    ? '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#issueLoanModal">+ Issue new loan</button>'
    : ''
);
?>
<div class="slim-stats">
  <div><span>VISIBLE TRANSACTIONS</span><strong><?= count($loans) ?></strong></div>
  <div><span>ACTIVE LOANS</span><strong><?= $activeCount ?></strong></div>
  <div><span>OVERDUE</span><strong class="danger-text"><?= $overdueCount ?></strong></div>
  <div><span>FINE RATE</span><strong><?= money(fine_rate()) ?><small> / day</small></strong></div>
</div>

<div class="panel">
  <div class="section-title">
    <div>
      <p class="eyebrow">CIRCULATION REGISTER</p>
      <h2><?= is_staff() ? 'Transactions' : 'Borrowing history' ?></h2>
    </div>
  </div>
  <form class="toolbar" method="get">
    <div class="search-wrap">
      <span>⌕</span>
      <input class="form-control" name="q" type="search" value="<?= e($search) ?>" placeholder="Search books, patrons or ISBN">
    </div>
    <select class="form-select" name="status" aria-label="Loan status">
      <option value="">All loans</option>
      <?php foreach (['Active', 'Overdue', 'Returned'] as $option): ?>
        <option <?= $state === $option ? 'selected' : '' ?>><?= $option ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary" type="submit">Filter</button>
    <a class="btn btn-outline-dark" href="loans.php">Reset</a>
  </form>
  <div class="table-scroll">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>BOOK</th>
          <?php if (is_staff()): ?><th>PATRON</th><?php endif; ?>
          <th>ISSUED</th>
          <th>DUE / RETURNED</th>
          <th>STATUS</th>
          <th>FEE BALANCE</th>
          <?php if (is_staff()): ?><th>ACTIONS</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($loans as $loan): ?>
          <?php
          $balance = money_owed($loan);
          $stateText = loan_display_status($loan);
          ?>
          <tr>
            <td>
              <div class="book-title">
                <span class="book-cover">⇄</span>
                <div><strong><?= e($loan['title']) ?></strong><small><?= e($loan['isbn']) ?></small></div>
              </div>
            </td>
            <?php if (is_staff()): ?><td><?= e($loan['patron_name']) ?></td><?php endif; ?>
            <td><?= e($loan['issue_date']) ?></td>
            <td>
              <?= e($loan['due_date']) ?>
              <?php if ($loan['return_date']): ?>
                <small class="table-sub">Returned <?= e($loan['return_date']) ?></small>
              <?php endif; ?>
            </td>
            <td><?= badge($stateText) ?></td>
            <td class="<?= $balance > 0 ? 'danger-text' : '' ?>"><?= money($balance) ?></td>
            <?php if (is_staff()): ?>
              <td class="action-cell">
                <?php if ($loan['status'] === 'Active'): ?>
                  <button type="button" class="btn btn-link btn-sm p-0 text-primary" data-bs-toggle="modal" data-bs-target="#returnLoanModal_<?= (int) $loan['loan_id'] ?>">Return</button>
                  <button type="button" class="btn btn-link btn-sm p-0 text-secondary" data-bs-toggle="modal" data-bs-target="#extendLoanModal_<?= (int) $loan['loan_id'] ?>">Extend</button>
                <?php else: ?>
                  <?php if ($balance > 0): ?>
                    <button type="button" class="btn btn-link btn-sm p-0 text-warning" data-bs-toggle="modal" data-bs-target="#settleLoanModal_<?= (int) $loan['loan_id'] ?>">Fine</button>
                  <?php endif; ?>
                  <button type="button" class="btn btn-link btn-sm p-0 text-danger" data-bs-toggle="modal" data-bs-target="#archiveLoanModal_<?= (int) $loan['loan_id'] ?>">Archive</button>
                <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        <?php if (!$loans): ?>
          <tr><td colspan="7" class="empty">No borrowing records found for this view.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="table-footnote">Overdue fees are calculated by calendar days past the due date. Historical fees are preserved when a loan is returned.</p>
</div>
<?php if (is_staff()): ?>
<div class="modal fade" id="issueLoanModal" tabindex="-1" aria-labelledby="issueLoanModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="action" value="issue">
      <div class="modal-header"><p class="eyebrow mb-0">NEW TRANSACTION</p><h2 class="modal-title" id="issueLoanModalLabel">Issue a book</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body row g-3">
        <div class="col-12"><label class="form-label">Available book<select class="form-select" name="book_id" required><option value="">Select an available title</option><?php foreach($available as $b): ?><option value="<?= (int)$b['book_id'] ?>"><?= e($b['title']) ?> (<?= e($b['isbn']) ?>)</option><?php endforeach; ?></select></label></div>
        <div class="col-12"><label class="form-label">Patron<select class="form-select" name="patron_id" required><option value="">Select registered patron</option><?php foreach($patrons as $p): ?><option value="<?= (int)$p['user_id'] ?>"><?= e($p['last_name'].', '.$p['first_name']) ?></option><?php endforeach; ?></select></label></div>
        <div class="col-12"><label class="form-label">Due date<input class="form-control" name="due_date" type="date" min="<?= date('Y-m-d',strtotime('+1 day')) ?>" value="<?= date('Y-m-d',strtotime('+14 days')) ?>" required></label></div>
        <div class="col-12"><p class="mb-0 text-muted">Issued by: <?= e(user()['first_name'].' '.user()['last_name']) ?> · Checkout date: <?= date('M j, Y') ?></p></div>
      </div>
      <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit" <?= !$available||!$patrons?'disabled':'' ?>>Issue book</button></div>
    </form>
  </div>
</div>
<?php foreach ($loans as $l): $balance=money_owed($l); ?>
<div class="modal fade" id="returnLoanModal_<?= (int)$l['loan_id'] ?>" tabindex="-1" aria-labelledby="returnLoanModalLabel_<?= (int)$l['loan_id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="action" value="return"><input type="hidden" name="loan_id" value="<?= (int)$l['loan_id'] ?>">
      <div class="modal-header"><p class="eyebrow mb-0">CHECK IN</p><h2 class="modal-title" id="returnLoanModalLabel_<?= (int)$l['loan_id'] ?>">Confirm return</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body"><p class="mb-0">The book becomes available immediately. Any late fee will be finalized.</p></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Return book</button></div>
    </form>
  </div>
</div>
<div class="modal fade" id="extendLoanModal_<?= (int)$l['loan_id'] ?>" tabindex="-1" aria-labelledby="extendLoanModalLabel_<?= (int)$l['loan_id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="action" value="extend"><input type="hidden" name="loan_id" value="<?= (int)$l['loan_id'] ?>">
      <div class="modal-header"><p class="eyebrow mb-0">LOAN ADJUSTMENT</p><h2 class="modal-title" id="extendLoanModalLabel_<?= (int)$l['loan_id'] ?>">Change due date</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body"><label class="form-label">New due date<input class="form-control" name="due_date" type="date" min="<?= date('Y-m-d') ?>" value="<?= e($l['due_date']) ?>" required></label></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Update date</button></div>
    </form>
  </div>
</div>
<div class="modal fade" id="settleLoanModal_<?= (int)$l['loan_id'] ?>" tabindex="-1" aria-labelledby="settleLoanModalLabel_<?= (int)$l['loan_id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="loan_id" value="<?= (int)$l['loan_id'] ?>">
      <div class="modal-header"><p class="eyebrow mb-0">FINE MANAGEMENT</p><h2 class="modal-title" id="settleLoanModalLabel_<?= (int)$l['loan_id'] ?>">Settle late fee</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body row g-3">
        <div class="col-12"><label class="form-label">Amount (PHP)<input class="form-control" name="amount" type="number" step="0.01" min="0.01" value="<?= number_format((float)$balance, 2, '.', '') ?>" required></label></div>
        <div class="col-12"><label class="form-label">Action<select class="form-select" name="action" required><option value="pay">Record payment</option><option value="waive">Waive fee</option></select></label></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save transaction</button></div>
    </form>
  </div>
</div>
<div class="modal fade" id="archiveLoanModal_<?= (int)$l['loan_id'] ?>" tabindex="-1" aria-labelledby="archiveLoanModalLabel_<?= (int)$l['loan_id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="loan_id" value="<?= (int)$l['loan_id'] ?>">
      <div class="modal-header"><p class="eyebrow mb-0">HISTORY MANAGEMENT</p><h2 class="modal-title" id="archiveLoanModalLabel_<?= (int)$l['loan_id'] ?>">Archive returned loan?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body"><p class="mb-0">Hides this loan from the circulation list without deleting the permanent borrowing history or reports.</p></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger" type="submit">Archive record</button></div>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php endif; page_end(); ?>
