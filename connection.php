<?php
$config = [
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_name' => getenv('DB_NAME') ?: 'libradesk',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    'setup_key' => getenv('APP_SETUP_KEY') ?: '',
    'fine_per_day' => (float) (getenv('FINE_PER_DAY') ?: 5),
    'library_name' => getenv('LIBRARY_NAME') ?: 'Bogo Cute Library',
    'mail_from' => getenv('MAIL_FROM') ?: '',
];
if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_replace($config, require __DIR__ . '/config.local.php');
}
try {
    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    error_log('LibraDesk database connection failed: ' . $e->getMessage());
    http_response_code(503);
    exit('LibraDesk cannot connect to the database. Check config.local.php and README.md.');
}
