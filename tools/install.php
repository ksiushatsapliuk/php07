<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../lib/migrations.php';

$config = require __DIR__ . '/../config.php';

if (!preg_match('/^[a-zA-Z0-9_]+$/', $config['database'])) {
    throw new RuntimeException('Некоректна назва БД.');
}

$pdo = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config['host'], $config['port']), $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$schema = str_replace('practicum7', $config['database'], file_get_contents(__DIR__ . '/../sql/schema.sql'));
$pdo->exec($schema);
applyIndexes($pdo);

echo "Схему та обидва індекси підготовлено. Повторний запуск безпечний.\n";
