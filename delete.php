<?php
require_once __DIR__ . '/bootstrap.php';

requireFormPost();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if (!$id) { http_response_code(422); exit('Некоректний ID рослини.'); }

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/classes/Garden.php';

$garden = new Garden($pdo);
$garden->deletePlant($id);

header('Location: index.php', true, 303);

exit;
