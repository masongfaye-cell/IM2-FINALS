<?php
require __DIR__ . '/app/bootstrap.php';
must_login();

if (!is_staff()) {
    $mine = rows(
        $pdo,
        loan_query('WHERE l.patron_id=? ORDER BY l.issue_date DESC LIMIT 6'),
        [current_id()]
    );
    $active = (int) scalar(
        $pdo,
        "SELECT COUNT(*) FROM loans WHERE patron_id=? AND status='Active'",
        [current_id()]
    );
    $overdue = (int) scalar(
        $pdo,
        "SELECT COUNT(*) FROM loans WHERE patron_id=? AND status='Active' AND due_date<CURDATE()",
        [current_id()]
    );
    $history = (int) scalar(
        $pdo,
        "SELECT COUNT(*) FROM loans WHERE patron_id=? AND status='Returned'",
        [current_id()]
    );

    page_start('My overview', 'dashboard');
    heading(
        'PATRON DASHBOARD',
        'Welcome back, ' . user()['first_name'],
        'A quick look at your reading journey.',
        '<a class="button primary" href="books.php">Browse catalog →</a>'
    );
    ?>
    <div class="stats-grid three">
        <article class="stat-card">
            <span class="stat-label">CURRENT BORROWINGS</span>
            <strong><?= $active ?></strong>
            <span class="stat-note">Books in your care</span>
        </article>
        <article class="stat-card">
            <span class="stat-label">OVERDUE BOOKS</span>
            <strong class="danger-text"><?= $overdue ?></strong>
            <span class="stat-note">Please return these soon</span>
        </article>
        <article class="stat-card">
            <span class="stat-label">COMPLETED READS</span>
            <strong><?= $history ?></strong>
            <span class="stat-note">Books returned</span>
        </article>
    </div>

    <section class="panel">
        <div class="section-title">
            <div>
                <p class="eyebrow">YOUR ACTIVITY</p>
                <h2>Recent loans</h2>
            </div>
            <a class="inline-link" href="loans.php">View history →</a>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Book</th>
                        <th>Borrowed</th>
                        <th>Due date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mine as $loan): ?>
                        <tr>
                            <td class="strong-cell"><?= e($loan['title']) ?></td>
                            <td><?= e($loan['issue_date']) ?></td>
                            <td><?= e($loan['due_date']) ?></td>
                            <td><?= badge(loan_display_status($loan)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$mine): ?>
                        <tr>
                            <td colspan="4" class="empty">No loans yet. Explore the catalog to find your next read.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
    page_end();
    exit;
}
$stats = overview($pdo);
$latest = rows(
    $pdo,
    loan_query('WHERE l.archived_at IS NULL ORDER BY l.issue_date DESC, l.loan_id DESC LIMIT 7')
);
$popular = rows(
    $pdo,
    "SELECT b.title,b.author,COUNT(l.loan_id) AS loans_count
    FROM books b JOIN loans l USING(book_id)
    GROUP BY b.book_id,b.title,b.author
    ORDER BY loans_count DESC,b.title LIMIT 5"
);
$logs = rows(
    $pdo,
    'SELECT a.action,a.timestamp,CONCAT(u.first_name," ",u.last_name) AS actor
    FROM activity_logs a JOIN users u USING(user_id)
    ORDER BY a.timestamp DESC,a.log_id DESC LIMIT 5'
);
$recentMonths = rows(
    $pdo,
    "SELECT DATE_FORMAT(issue_date,'%Y-%m') AS month,COUNT(*) AS cnt
    FROM loans
    WHERE issue_date >= DATE_SUB(CURDATE(),INTERVAL 5 MONTH)
    GROUP BY DATE_FORMAT(issue_date,'%Y-%m')
    ORDER BY month"
);
$max = 1;
if ($recentMonths) {
    $max = max(array_map(fn ($month) => (int) $month['cnt'], $recentMonths));
}
page_start('Overview', 'dashboard');
heading(
    'LIBRARY AT A GLANCE',
    'Good day, ' . user()['first_name'] . '!',
    'Here is what is happening across your library today.',
    '<a class="button primary" href="loans.php">+ Issue a loan</a>'
);
?>
<div class="stats-grid">
    <article class="stat-card">
        <div class="stat-top"><span class="stat-label">TOTAL BOOKS</span><span class="stat-symbol orange-bg">▤</span></div>
        <strong><?= number_format($stats['books']) ?></strong>
        <span class="stat-note"><?= $stats['available'] ?> ready to borrow</span>
    </article>
    <article class="stat-card">
        <div class="stat-top"><span class="stat-label">ACTIVE LOANS</span><span class="stat-symbol cyan-bg">⇄</span></div>
        <strong><?= number_format($stats['active']) ?></strong>
        <span class="stat-note">Currently checked out</span>
    </article>
    <article class="stat-card">
        <div class="stat-top"><span class="stat-label">REGISTERED MEMBERS</span><span class="stat-symbol violet-bg">♙</span></div>
        <strong><?= number_format($stats['members']) ?></strong>
        <span class="stat-note">Readers in the community</span>
    </article>
    <article class="stat-card">
        <div class="stat-top"><span class="stat-label">OVERDUE TITLES</span><span class="stat-symbol rose-bg">!</span></div>
        <strong class="<?= $stats['overdue'] ? 'danger-text' : '' ?>"><?= number_format($stats['overdue']) ?></strong>
        <span class="stat-note">Need attention</span>
    </article>
</div>

<div class="dash-grid">
    <section class="panel">
        <div class="section-title">
            <div>
                <p class="eyebrow">CIRCULATION INSIGHTS</p>
                <h2>Loans over time</h2>
            </div>
            <span class="muted tiny">LAST 6 MONTHS</span>
        </div>
        <div class="bars" aria-label="Monthly loan counts">
            <?php if ($recentMonths): ?>
                <?php foreach ($recentMonths as $month): ?>
                    <div class="bar-group">
                        <span class="bar-count"><?= (int) $month['cnt'] ?></span>
                        <div class="bar" style="height:<?= max(6, round((int) $month['cnt'] / $max * 145)) ?>px"></div>
                        <small><?= e(date('M', strtotime($month['month'] . '-01'))) ?></small>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty">Loan trends will appear after the first checkout.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="section-title">
            <div>
                <p class="eyebrow">READER FAVORITES</p>
                <h2>Most borrowed</h2>
            </div>
        </div>
        <?php foreach ($popular as $index => $book): ?>
            <div class="popular-row">
                <span class="rank"><?= $index + 1 ?></span>
                <div><strong><?= e($book['title']) ?></strong><small><?= e($book['author']) ?></small></div>
                <span class="count-chip"><?= (int) $book['loans_count'] ?> loans</span>
            </div>
        <?php endforeach; ?>
        <?php if (!$popular): ?>
            <p class="empty">Popular titles will appear as loans are recorded.</p>
        <?php endif; ?>
    </section>
</div>

<section class="panel">
    <div class="section-title">
        <div>
            <p class="eyebrow">LATEST TRANSACTIONS</p>
            <h2>Recent circulation</h2>
        </div>
        <a class="inline-link" href="loans.php">All transactions →</a>
    </div>
    <div class="table-scroll">
        <table>
            <thead>
                <tr><th>Title</th><th>Patron</th><th>Checked out</th><th>Due</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($latest as $loan): ?>
                    <tr>
                        <td class="strong-cell"><?= e($loan['title']) ?></td>
                        <td><?= e($loan['patron_name']) ?></td>
                        <td><?= e($loan['issue_date']) ?></td>
                        <td><?= e($loan['due_date']) ?></td>
                        <td><?= badge(loan_display_status($loan)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$latest): ?>
                    <tr><td colspan="5" class="empty">No circulation transactions yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel mini-activity">
    <div class="section-title">
        <div>
            <p class="eyebrow">ACCOUNTABILITY</p>
            <h2>Recent system activity</h2>
        </div>
        <a class="inline-link" href="activity.php">Activity log →</a>
    </div>
    <?php foreach ($logs as $log): ?>
        <div class="activity-row">
            <span class="activity-circle">✓</span>
            <div><strong><?= e($log['action']) ?></strong><small><?= e($log['actor']) ?> · <?= e($log['timestamp']) ?></small></div>
        </div>
    <?php endforeach; ?>
    <?php if (!$logs): ?>
        <p class="empty">Staff actions will appear here.</p>
    <?php endif; ?>
</section>
<?php page_end(); ?>
