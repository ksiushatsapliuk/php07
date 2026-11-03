<?php
// Базовий списковий API роботи 06. Використовує спільні бібліотеки без їх дублювання.
if (getenv('FLORACARE_BENCHMARK') !== '1') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../lib/Profile.php';
Profile::start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
define('JSON_API', true);
require_once __DIR__ . '/../lib/api_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    apiError('Метод не підтримується.', 405);
}

if (apiQuery('resource') !== 'plants') apiError('Невідомий ресурс.', 404);
$query = apiQuery('q', '');
require_once __DIR__ . '/../lib/db.php';

class OriginalGarden
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getAllPlants(string $query): array
    {
        if ($query === '') {
            return $this->pdo->query('SELECT * FROM plants ORDER BY id DESC')->fetchAll();
        }

        $stmt = $this->pdo->prepare("SELECT * FROM plants WHERE name LIKE :q ESCAPE '!' ORDER BY id DESC");
        $stmt->execute([':q' => '%' . strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%']);

        return $stmt->fetchAll();
    }

    public function getCounts(): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(DATE_ADD(last_watered_at, INTERVAL watering_interval_days DAY) <= :today), 0) AS due FROM plants');
        $stmt->execute([':today' => date('Y-m-d')]);

        return $stmt->fetch();
    }
}

$garden = new OriginalGarden($pdo);
$counts = $garden->getCounts();
$data = array_map('plantJson', $garden->getAllPlants($query));

// У базовому списку 06 ще не було нового групового підрахунку.
foreach ($data as &$plant) unset($plant['same_interval_count']);

apiReply($data, 200, ['total' => (int)$counts['total'], 'needs_water' => (int)$counts['due']]);
