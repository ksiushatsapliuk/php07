<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/MeasuredPDO.php';

$config = require __DIR__ . '/config.php';
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']);

try {
    $pdo = new MeasuredPDO($dsn, $config['user'], $config['password'], [
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    // CURDATE() у MySQL відповідає поточній даті Europe/Kyiv у PHP.
    $pdo->prepare('SET time_zone = :zone')->execute([':zone' => date('P')]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    if (defined('JSON_API')) {
        apiError('Не вдалося підключитися до БД. Перевірте MySQL, config.php та імпорт schema.sql.', 500);
    }
    exit('Не вдалося підключитися до БД. Перевірте MySQL, config.php та імпорт schema.sql.');
}
