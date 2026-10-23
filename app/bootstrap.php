<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('libradesk_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure,
        'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/../classes/Book.php';
require_once __DIR__ . '/../classes/Loan.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/layout.php';
