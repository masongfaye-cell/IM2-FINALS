<?php
require __DIR__ . '/app/bootstrap.php';
must_login();

$bookEntity = new Book($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  must_staff();
  verify_post();
    try {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'add') {
      $bookEntity->save($_POST);
      flash('Book added to the catalog.');
    } elseif ($action === 'update') {
      $bookEntity->save($_POST, (int) ($_POST['book_id'] ?? 0));
      flash('Book details saved.');
    } elseif ($action === 'archive') {
      $bookEntity->archive((int) ($_POST['book_id'] ?? 0));
      flash('Book archived. Its loan history is preserved.');
    } else {
      throw new DomainException('Unknown action.');
    }
  } catch (DomainException $e) {
    flash($e->getMessage(), 'error');
  } catch (Throwable $e) {
    error_log($e->getMessage());
    flash('Could not save your changes. Please check the details and retry.', 'error');
  }

    redirect('books.php');
}
$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$books = $bookEntity->list($search, $status);
$categories = rows($pdo, 'SELECT category_name FROM categories ORDER BY category_name');
$publishers = rows($pdo, 'SELECT name FROM publishers ORDER BY name');

page_start('Book catalog', 'books');
heading(
  'LIBRARY COLLECTION',
  'Book catalog',
  'Discover, organize and maintain your entire collection.',
  is_staff()
    ? '<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookAddModal">+ Add new book</button>'
    : ''
);
?>
<div class="panel">
  <div class="section-title">
    <div>
      <p class="eyebrow">DIGITAL INVENTORY</p>
      <h2>All titles <span class="title-counter"><?= count($books) ?></span></h2>
    </div>
  </div>
  <form class="toolbar" method="get">
    <div class="search-wrap">
      <span>⌕</span>
      <input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Search title, author, ISBN, category or publisher">
    </div>
    <select name="status" class="form-select" aria-label="Availability status">
      <option value="">All statuses</option>
      <option value="Available" <?= $status === 'Available' ? 'selected' : '' ?>>Available</option>
      <option value="On Loan" <?= $status === 'On Loan' ? 'selected' : '' ?>>On loan</option>
    </select>
    <button class="btn btn-outline-secondary" type="submit">Filter</button>
    <a class="btn btn-outline-dark" href="books.php">Reset</a>
  </form>
  <div class="table-scroll">
    <table class="table table-hover align-middle">
      <thead>
        <tr>
          <th>BOOK / AUTHOR</th>
          <th>ISBN</th>
          <th>CATEGORY</th>
          <th>SHELF</th>
          <th>PUBLISHER</th>
          <th>AVAILABILITY</th>
          <?php if (is_staff()): ?><th>ACTIONS</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
<?php foreach ($books as $b): ?>
        <tr>
          <td>
            <div class="book-title">
              <span class="book-cover">▤</span>
              <div><strong><?= e($b['title']) ?></strong><small><?= e($b['author']) ?></small></div>
            </div>
          </td>
          <td class="monospace"><?= e($b['isbn']) ?></td>
          <td><?= e($b['category_name'] ?? '—') ?></td>
          <td><?= e($b['shelf_location'] ?: '—') ?></td>
          <td><?= e($b['publisher_name'] ?? '—') ?></td>
          <td><?= badge($b['status']) ?></td>
          <?php if (is_staff()): ?>
            <td class="action-cell">
              <button type="button" class="btn btn-link btn-sm p-0 text-primary" data-bs-toggle="modal" data-bs-target="#editBookModal_<?= (int) $b['book_id'] ?>">Edit</button>
              <button type="button" class="btn btn-link btn-sm p-0 text-danger" data-bs-toggle="modal" data-bs-target="#archiveBookModal_<?= (int) $b['book_id'] ?>" <?= $b['status'] === 'On Loan' ? 'disabled title="Return book first"' : '' ?>>Archive</button>
            </td>
          <?php endif; ?>
        </tr>
<?php endforeach; ?>
        <?php if (!$books): ?>
          <tr>
            <td colspan="7" class="empty">
              No titles match your filters.
              <?= is_staff() ? 'Use “Add new book” to grow the collection.' : 'Try another search.' ?>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php if (is_staff()): ?>
<datalist id="category-list">
  <?php foreach ($categories as $category): ?>
    <option value="<?= e($category['category_name']) ?>">
  <?php endforeach; ?>
</datalist>
<datalist id="publisher-list">
  <?php foreach ($publishers as $publisher): ?>
    <option value="<?= e($publisher['name']) ?>">
  <?php endforeach; ?>
</datalist>
<div class="modal fade" id="bookAddModal" tabindex="-1" aria-labelledby="bookAddModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="action" value="add">
      <div class="modal-header"><p class="eyebrow mb-0">CATALOG MANAGEMENT</p><h2 class="modal-title" id="bookAddModalLabel">Add a book</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body row g-3">
        <div class="col-12"><label class="form-label">Book title<input class="form-control" name="title" maxlength="255" required placeholder="e.g., The Great Gatsby"></label></div>
        <div class="col-12"><label class="form-label">Author<input class="form-control" name="author" maxlength="255" required placeholder="Full author name"></label></div>
        <div class="col-md-6"><label class="form-label">ISBN<input class="form-control" name="isbn" maxlength="20" required></label></div>
        <div class="col-md-6"><label class="form-label">Shelf location<input class="form-control" name="shelf_location" maxlength="80" placeholder="e.g., A-03-02"></label></div>
        <div class="col-md-6"><label class="form-label">Category / Genre<input class="form-control" name="category_name" list="category-list" maxlength="100" required></label></div>
        <div class="col-md-6"><label class="form-label">Publisher<input class="form-control" name="publisher_name" list="publisher-list" maxlength="100" required></label></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save book</button></div>
    </form>
  </div>
</div>
<?php foreach ($books as $b): ?>
<div class="modal fade" id="editBookModal_<?= (int)$b['book_id'] ?>" tabindex="-1" aria-labelledby="editBookModalLabel_<?= (int)$b['book_id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="book_id" value="<?= (int)$b['book_id'] ?>">
      <div class="modal-header"><p class="eyebrow mb-0">CATALOG MANAGEMENT</p><h2 class="modal-title" id="editBookModalLabel_<?= (int)$b['book_id'] ?>">Edit book details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body row g-3">
        <div class="col-12"><label class="form-label">Book title<input class="form-control" name="title" maxlength="255" value="<?= e($b['title']) ?>" required></label></div>
        <div class="col-12"><label class="form-label">Author<input class="form-control" name="author" maxlength="255" value="<?= e($b['author']) ?>" required></label></div>
        <div class="col-md-6"><label class="form-label">ISBN<input class="form-control" name="isbn" maxlength="20" value="<?= e($b['isbn']) ?>" required></label></div>
        <div class="col-md-6"><label class="form-label">Shelf location<input class="form-control" name="shelf_location" maxlength="80" value="<?= e($b['shelf_location'] ?: '') ?>" placeholder="e.g., A-03-02"></label></div>
        <div class="col-md-6"><label class="form-label">Category / Genre<input class="form-control" name="category_name" list="category-list" maxlength="100" value="<?= e($b['category_name'] ?? '') ?>" required></label></div>
        <div class="col-md-6"><label class="form-label">Publisher<input class="form-control" name="publisher_name" list="publisher-list" maxlength="100" value="<?= e($b['publisher_name'] ?? '') ?>" required></label></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save changes</button></div>
    </form>
  </div>
</div>
<div class="modal fade" id="archiveBookModal_<?= (int)$b['book_id'] ?>" tabindex="-1" aria-labelledby="archiveBookModalLabel_<?= (int)$b['book_id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="book_id" value="<?= (int)$b['book_id'] ?>">
      <div class="modal-header"><p class="eyebrow mb-0">ARCHIVE RECORD</p><h2 class="modal-title" id="archiveBookModalLabel_<?= (int)$b['book_id'] ?>">Archive this book?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body"><p class="mb-0">This title will disappear from the active catalog. Its past loan records will remain in reports.</p></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Archive book</button></div>
    </form>
  </div>
</div>
<?php endforeach; ?>
<?php endif; page_end(); ?>
