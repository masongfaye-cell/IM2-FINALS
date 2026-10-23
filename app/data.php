<?php
function sync_notifications(PDO $pdo, int $userId, bool $staff): void {
    if ($staff) return;
    $items = rows($pdo, "SELECT loan_id, title, due_date FROM loans JOIN books USING(book_id)
        WHERE patron_id = ? AND loans.status = 'Active' AND due_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)", [$userId]);
    $stmt = $pdo->prepare('INSERT IGNORE INTO notifications (user_id, loan_id, kind, message) VALUES (?, ?, ?, ?)');
    foreach ($items as $loan) {
        $kind = $loan['due_date'] < date('Y-m-d') ? 'overdue' : 'due_soon';
        $message = $kind === 'overdue'
            ? 'Overdue: ' . $loan['title'] . ' was due ' . $loan['due_date'] . '.'
            : 'Due soon: ' . $loan['title'] . ' is due ' . $loan['due_date'] . '.';
        $stmt->execute([$userId, $loan['loan_id'], $kind, mb_safe_substr($message, 0, 255)]);
    }
}
function unread_count(PDO $pdo, int $userId): int {
    return (int) scalar($pdo, "SELECT COUNT(*) FROM notifications n
        JOIN loans l ON l.loan_id = n.loan_id WHERE n.user_id = ? AND n.read_at IS NULL AND l.status = 'Active'", [$userId]);
}
function overview(PDO $pdo): array {
    return [
        'books' => (int) scalar($pdo, 'SELECT COUNT(*) FROM books WHERE archived_at IS NULL'),
        'available' => (int) scalar($pdo, "SELECT COUNT(*) FROM books WHERE archived_at IS NULL AND status = 'Available'"),
        'active' => (int) scalar($pdo, "SELECT COUNT(*) FROM loans WHERE status = 'Active'"),
        'overdue' => (int) scalar($pdo, "SELECT COUNT(*) FROM loans WHERE status = 'Active' AND due_date < CURDATE()"),
        'members' => (int) scalar($pdo, "SELECT COUNT(*) FROM users JOIN roles USING(role_id) WHERE role_name='Patron' AND users.archived_at IS NULL"),
    ];
}
function loan_query(string $where = ''): string {
    return "SELECT l.*, b.title, b.isbn, b.author, CONCAT(p.first_name,' ',p.last_name) AS patron_name,
        p.email AS patron_email, CONCAT(s.first_name,' ',s.last_name) AS librarian_name
        FROM loans l JOIN books b ON b.book_id = l.book_id JOIN users p ON p.user_id=l.patron_id
        JOIN users s ON s.user_id=l.issued_by " . $where;
}
