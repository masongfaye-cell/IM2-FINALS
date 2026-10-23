<?php
require __DIR__ . '/app/bootstrap.php';
must_staff();

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT a.*, CONCAT(u.first_name," ",u.last_name) AS actor, r.role_name
	FROM activity_logs a
	JOIN users u USING(user_id)
	JOIN roles r USING(role_id)
	WHERE 1=1';
$params = [];
if ($q !== '') {
	$sql .= ' AND (a.action LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
	$params = array_fill(0, 3, '%' . $q . '%');
}
$logs = rows($pdo, $sql . ' ORDER BY a.timestamp DESC,a.log_id DESC LIMIT 300', $params);

page_start('Activity log', 'activity');
heading('AUDIT TRAIL', 'System activity', 'A transparent record of significant staff and account actions.');
?>
<div class="panel">
	<div class="section-title">
		<div>
			<p class="eyebrow">RECENT EVENTS</p>
			<h2>Activity log</h2>
		</div>
		<span class="muted tiny">LAST 300 MATCHES</span>
	</div>
	<form method="get" class="toolbar">
		<div class="search-wrap">
			<span>⌕</span>
			<input type="search" name="q" value="<?= e($q) ?>" placeholder="Search action or actor">
		</div>
		<button class="button secondary">Search</button>
		<a class="button ghost" href="activity.php">Reset</a>
	</form>
	<div class="table-scroll">
		<table>
			<thead>
				<tr><th>DATE & TIME</th><th>ACTION</th><th>PERFORMED BY</th><th>ROLE</th></tr>
			</thead>
			<tbody>
				<?php foreach ($logs as $log): ?>
					<tr>
						<td class="monospace"><?= e($log['timestamp']) ?></td>
						<td class="strong-cell"><?= e($log['action']) ?></td>
						<td><?= e($log['actor']) ?></td>
						<td><?= e($log['role_name']) ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if (!$logs): ?>
					<tr><td class="empty" colspan="4">No matching activity.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
<?php page_end(); ?>
