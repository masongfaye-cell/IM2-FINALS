<?php
function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string {
    if (!isset($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function verify_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Invalid request token. Reload the page and try again.');
    }
}
function redirect(string $path): void {
    header('Location: ' . $path, true, 303);
    exit;
}

function flash(string $message, string $type = 'success'): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_staff(): bool {
    return (user()['role_name'] ?? null) === 'Staff';
}

function must_login(): void {
    global $pdo;
    if (!user()) redirect('login.php');

    static $verified = false;
    if (!$verified) {
        $stmt = $pdo->prepare("SELECT u.user_id,u.first_name,u.last_name,u.email,r.role_name
            FROM users u JOIN roles r USING(role_id)
            WHERE u.user_id=? AND u.account_status='Active' AND u.archived_at IS NULL");
        $stmt->execute([current_id()]);
        $account = $stmt->fetch();
        if (!$account) {
            unset($_SESSION['user']);
            flash('Your account is no longer active. Contact library staff.', 'error');
            redirect('login.php');
        }
        $_SESSION['user'] = $account;
        $verified = true;
    }
}

function must_staff(): void {
    must_login();
    if (!is_staff()) {
        http_response_code(403);
        exit('403 — Librarian access required.');
    }
}

function current_id(): int {
    return (int) (user()['user_id'] ?? 0);
}

function money($value): string {
    return '₱' . number_format((float) $value, 2);
}

function fine_rate(): float {
    global $config;
    return max(0, (float) $config['fine_per_day']);
}

function scalar(PDO $pdo, string $sql, array $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

function rows(PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function log_action(PDO $pdo, string $action, ?int $actor = null): void {
    $actor ??= current_id();
    $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action) VALUES (?, ?)');
    $stmt->execute([$actor, mb_safe_substr($action, 0, 255)]);
}

function mb_safe_substr(string $value, int $start, int $length): string {
    return function_exists('mb_substr') ? mb_substr($value, $start, $length) : substr($value, $start, $length);
}

function validate_email(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function safe_date(string $value): ?string {
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $dt && $dt->format('Y-m-d') === $value ? $value : null;
}

function money_owed(array $loan): float {
    $fine = (float) $loan['fine_amount'];
    if ($loan['status'] === 'Active') {
        $days = max(0, (int) ((new DateTimeImmutable('today'))->diff(new DateTimeImmutable($loan['due_date']))->format('%r%a')) * -1);
        $fine = $days * fine_rate();
    }

    return max(0, $fine - (float) ($loan['fine_paid'] ?? 0) - (float) ($loan['fine_waived'] ?? 0));
}

function alert_class(string $status): string {
    return match ($status) {
        'Available', 'Returned', 'Active member' => 'positive',
        'Overdue', 'Suspended', 'Archived' => 'danger',
        default => 'warning',
    };
}

function loan_display_status(array $loan): string {
    return $loan['status'] === 'Active' && $loan['due_date'] < date('Y-m-d') ? 'Overdue' : $loan['status'];
}

function csv_cell($value): string {
    $s = (string) $value;
    return preg_match('/^[\s]*[=+@\-]/u', $s) ? "'" . $s : $s;
}

function export_csv(string $filename, array $headers, array $records): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers, ',', '"', '');
    foreach ($records as $record) fputcsv($out, array_map('csv_cell', array_values($record)), ',', '"', '');
    fclose($out);
    exit;
}
