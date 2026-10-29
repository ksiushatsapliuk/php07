<?php
// спільні відповіді та перетворення даних. маршрутизація лише в api.php.
function apiReply(mixed $data, int $status = 200, array $meta = []): never
{
    http_response_code($status);

    $result = ['success' => true, 'data' => $data];

    if ($meta !== []) $result['meta'] = $meta;

    $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    header('Content-Length: ' . strlen($json));

    Profile::finish($GLOBALS['pdo'] ?? null);

    echo $json;

    exit;
}

function apiError(string $message, int $status): never
{
    http_response_code($status);

    $json = json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    header('Content-Length: ' . strlen($json));

    Profile::finish($GLOBALS['pdo'] ?? null);

    echo $json;
    
    exit;
}

set_exception_handler(function (Throwable $error): void {
    error_log((string)$error);
    apiError('Не вдалося виконати запит. Перевірте сервер і підключення до бази даних.', 500);
});

function apiQuery(string $name, ?string $default = null): ?string
{
    if (!array_key_exists($name, $_GET)) return $default;
    
    if (!is_string($_GET[$name])) apiError('Параметр ' . $name . ' має бути рядком.', 400);
    
    return trim($_GET[$name]);
}

function apiId(mixed $value): int
{
    if ((!is_string($value) && !is_int($value)) ||
        !($id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]))) {
        apiError('Поле id має бути додатним цілим числом.', 400);
    }
    return $id;
}

function apiBody(): array
{
    $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    
    if ($type === 'application/json') {
        try {
            $object = json_decode(file_get_contents('php://input'), false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            apiError('Тіло запиту містить некоректний JSON.', 400);
        }
        
        if (!$object instanceof stdClass) apiError('Тіло JSON має бути об’єктом із полями.', 400);
        
        return get_object_vars($object);
    }

    if (!in_array($type, ['', 'application/x-www-form-urlencoded', 'multipart/form-data'], true)) {
        apiError('Використовуйте application/json або дані HTML-форми.', 415);
    }

    if (!validCsrfToken()) apiError('Оновіть сторінку та повторіть дію: неправильний токен форми.', 403);
    return $_POST;
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/classes/Garden.php';
require_once __DIR__ . '/lib/functions.php';

function plantJson(array $row): array
{
    $days = (int)(new DateTimeImmutable($row['last_watered_at']))->diff(new DateTimeImmutable('today'))->format('%r%a');
    $succulent = (bool)$row['is_succulent'];
    
    $plant = $succulent
        ? new Succulent($row['name'], (int)$row['watering_interval_days'], $days, $row['sunlight_needs'] ?? 'Пряме сонце')
        : new Plant($row['name'], (int)$row['watering_interval_days'], $days);
    return [
        'id' => (int)$row['id'], 'name' => $plant->getName(),
        'same_interval_count' => (int)($row['same_interval_count'] ?? 1),
        'watering_interval_days' => $plant->getWateringIntervalDays(), 'last_watered_at' => $row['last_watered_at'],
        'is_succulent' => $succulent, 'sunlight_needs' => $plant instanceof Succulent ? $plant->getSunlightNeeds() : 'Розсіяне світло',
        'days_since_watered' => $plant->getDaysSinceWatered(), 'needs_water' => $plant->needsWater(),
        'next_watering' => nextWateringDate($plant->getDaysSinceWatered(), $plant->getWateringIntervalDays()),
    ];
}
