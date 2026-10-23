<?php
require __DIR__ . '/app/bootstrap.php';
must_staff();

$reportTypes = [
    'monthly' => 'Monthly circulation',
    'popular' => 'Most borrowed books',
    'overdue' => 'Overdue loans',
    'fines' => 'Fine collection',
];
$type = (string) ($_GET['type'] ?? 'monthly');
if (!isset($reportTypes[$type])) {
    $type = 'monthly';
}

$month = (string) ($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}

$start = $month . '-01';
$end = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d');
$headers = [];
$records = [];

switch ($type) {
    case 'monthly':
        $headers = ['Loan ID', 'Title', 'Patron', 'Issue date', 'Due date', 'Returned', 'Status'];
        $loans = rows(
            $pdo,
            loan_query('WHERE l.issue_date>=? AND l.issue_date<? ORDER BY l.issue_date DESC'),
            [$start, $end]
        );
        foreach ($loans as $loan) {
            $records[] = [
                $loan['loan_id'],
                $loan['title'],
                $loan['patron_name'],
                $loan['issue_date'],
                $loan['due_date'],
                $loan['return_date'] ?? '—',
                $loan['status'],
            ];
        }
        break;
    case 'popular':
        $headers = ['Title', 'Author', 'ISBN', 'Total loans'];
        $popular = rows(
            $pdo,
            'SELECT b.title,b.author,b.isbn,COUNT(l.loan_id) AS cnt
            FROM books b JOIN loans l USING(book_id)
            GROUP BY b.book_id,b.title,b.author,b.isbn
            ORDER BY cnt DESC,b.title LIMIT 100'
        );
        foreach ($popular as $book) {
            $records[] = [$book['title'], $book['author'], $book['isbn'], $book['cnt']];
        }
        break;
    case 'overdue':
        $headers = ['Loan ID', 'Title', 'Patron', 'Email', 'Due date', 'Days late', 'Estimated fine (PHP)'];
        $overdueLoans = rows(
            $pdo,
            loan_query("WHERE l.status='Active' AND l.due_date<CURDATE() ORDER BY l.due_date")
        );
        foreach ($overdueLoans as $loan) {
            $daysLate = max(
                0,
                (int) (new DateTimeImmutable($loan['due_date']))
                    ->diff(new DateTimeImmutable('today'))
                    ->format('%r%a')
            );
            $records[] = [
                $loan['loan_id'],
                $loan['title'],
                $loan['patron_name'],
                $loan['patron_email'],
                $loan['due_date'],
                $daysLate,
                number_format(money_owed($loan), 2, '.', ''),
            ];
        }
        break;
    case 'fines':
        $headers = ['Loan ID', 'Book title', 'Patron', 'Return date', 'Assessed (PHP)', 'Paid (PHP)', 'Waived (PHP)', 'Outstanding (PHP)'];
        $returnedLoans = rows(
            $pdo,
            loan_query("WHERE l.status='Returned' AND l.return_date>=? AND l.return_date<? ORDER BY l.return_date DESC"),
            [$start, $end]
        );
        foreach ($returnedLoans as $loan) {
            $records[] = [
                $loan['loan_id'],
                $loan['title'],
                $loan['patron_name'],
                $loan['return_date'],
                number_format((float) $loan['fine_amount'], 2, '.', ''),
                number_format((float) $loan['fine_paid'], 2, '.', ''),
                number_format((float) $loan['fine_waived'], 2, '.', ''),
                number_format(money_owed($loan), 2, '.', ''),
            ];
        }
        break;
}
$format = (string) ($_GET['export'] ?? '');
$filename = 'libradesk-' . $type . '-' . $month;
if ($format === 'csv') {
    export_csv($filename, $headers, $records);
}
if ($format === 'pdf') {
    require __DIR__ . '/classes/SimplePdf.php';
    $doc = new SimplePdf($reportTypes[$type], $config['library_name']);
    $doc->table($headers, $records);
    $doc->download($filename . '.pdf');
}
$totals = overview($pdo);
$collected = (float) scalar($pdo, "SELECT COALESCE(SUM(fine_paid),0) FROM loans WHERE status='Returned'");
$outstanding = (float) scalar($pdo, "SELECT COALESCE(SUM(GREATEST(0,fine_amount-fine_paid-fine_waived)),0) FROM loans WHERE status='Returned'");

page_start('Reports', 'reports');
heading('LIBRARY INTELLIGENCE', 'Reports & exports', 'Explore trends, overdue accounts and collected fees. Download CSV or PDF reports.');
?>
<div class="stats-grid three">
    <div class="stat-card">
        <span class="stat-label">CHECKED OUT BOOKS</span>
        <strong><?= $totals['active'] ?></strong>
        <span class="stat-note">Active circulation</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">FINES COLLECTED (ALL TIME)</span>
        <strong><?= money($collected) ?></strong>
        <span class="stat-note">Recorded payments</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">OUTSTANDING RETURNED FEES</span>
        <strong class="danger-text"><?= money($outstanding) ?></strong>
        <span class="stat-note">Excludes live active-loan estimates</span>
    </div>
</div>

<section class="panel">
    <div class="section-title">
        <div>
            <p class="eyebrow">REPORT BUILDER</p>
            <h2><?= e($reportTypes[$type]) ?></h2>
        </div>
        <span class="title-counter"><?= count($records) ?> rows</span>
    </div>
    <form class="toolbar report-toolbar" method="get">
        <label>
            Report type
            <select name="type">
                <?php foreach ($reportTypes as $key => $name): ?>
                    <option value="<?= e($key) ?>" <?= $type === $key ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Month <input type="month" name="month" value="<?= e($month) ?>"></label>
        <button class="button secondary" type="submit">Generate</button>
        <a class="button outline" href="?<?= e(http_build_query(['type' => $type, 'month' => $month, 'export' => 'csv'])) ?>">↓ Export CSV</a>
        <a class="button primary" href="?<?= e(http_build_query(['type' => $type, 'month' => $month, 'export' => 'pdf'])) ?>">↓ Export PDF</a>
    </form>
    <p class="tiny muted">Monthly circulation uses checkout dates; fine collection uses return dates. Popularity and overdue reports show all applicable records, regardless of month.</p>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <?php foreach ($headers as $header): ?>
                        <th><?= e($header) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <?php foreach ($record as $cell): ?>
                            <td><?= e($cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$records): ?>
                    <tr>
                        <td colspan="<?= count($headers) ?>" class="empty">No records available for this report.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php page_end(); ?>
