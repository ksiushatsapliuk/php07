<?php
require_once __DIR__ . '/lib/Profile.php';

Profile::start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

ini_set('display_errors', '0');
define('JSON_API', true);

require_once __DIR__ . '/api_common.php';

// контракт і приклади: API.md. Усі JSON-відповіді мають success та data або error.
$method = $_SERVER['REQUEST_METHOD'];
$resource = apiQuery('resource');
$action = apiQuery('action');

if ($resource !== 'plants') apiError('Невідомий ресурс. Використовуйте resource=plants.', 404);

if ($action !== null && $action !== 'water') apiError('Невідома дія. Підтримується action=water.', 404);

if (!in_array($method, ['GET', 'POST'], true) || ($action === 'water' && $method !== 'POST')) {
    header('Allow: ' . ($action === 'water' ? 'POST' : 'GET, POST'));
    apiError('Метод не підтримується для цього ресурсу або дії.', 405);
}

$id = array_key_exists('id', $_GET) ? apiId($_GET['id']) : null;

$q = apiQuery('q', '');
$dueValue = apiQuery('due', '0');

if (!in_array($dueValue, ['0', '1'], true)) {
    apiError('Параметр due має бути 0 або 1.', 400);
}

$due = $dueValue === '1';
$interval = null;

if (array_key_exists('interval', $_GET)) {
    $interval = apiId($_GET['interval']);

    if ($interval > 3650) {
        apiError('Параметр interval має бути від 1 до 3650.', 400);
    }
}


if ($method === 'POST') {
    $body = apiBody();

    if ($action === 'water') {
        $bodyId = array_key_exists('id', $body) ? apiId($body['id']) : null;

        if ($id !== null && $bodyId !== null && $id !== $bodyId) apiError('ID у URL і тілі запиту не збігаються.', 400);
        
        $id = $id ?? $bodyId;
        
        if ($id === null) apiError('Для action=water потрібне поле id.', 400);
    } else {
        if ($id !== null) apiError('Створення запису не приймає id у URL.', 400);
        
        try { 
            $input = plantInput($body); 
            }
        catch (InvalidArgumentException $error) { 
            apiError($error->getMessage(), 400); 
            }
    }
}

require_once __DIR__ . '/db.php';

$garden = new Garden($pdo);

if ($method === 'GET' && $id === null) {
    $counts = $garden->getCounts();


    $rows = $due && $q === '' && $interval === null
        ? $garden->needsWaterToday()
        : $garden->getAllPlants($q, $interval, $due);

    header('X-Cache: ' . $garden->cacheStatus());

    apiReply(array_map('plantJson', $rows), 200,
        ['total' => (int)$counts['total'], 'needs_water' => (int)$counts['due']]);

} elseif ($method === 'GET') {
    $row = $garden->getPlantById($id);

    if (!$row) apiError('Рослину з таким id не знайдено.', 404);
    apiReply(plantJson($row));

} elseif ($action === 'water') {
    if (!$garden->getPlantById($id)) apiError('Рослину з таким id не знайдено.', 404);
    
    $garden->markWatered($id);

    apiReply(plantJson($garden->getPlantById($id)));

} else {
    $garden->addPlant($input['name'], $input['interval'], $input['date'], $input['succulent'], $input['sunlight']);
    
    $createdId = (int)$pdo->lastInsertId();
    
    header('Location: api.php?resource=plants&id=' . $createdId);
    
    apiReply(plantJson($garden->getPlantById($createdId)), 201);
}
