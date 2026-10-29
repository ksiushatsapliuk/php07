<?php
require_once __DIR__ . '/Plant.php';
require_once __DIR__ . '/Succulent.php';
require_once __DIR__ . '/../lib/FileCache.php';

class Garden 
{
    protected PDO $pdo;
    private FileCache $cache;

    public function __construct(PDO $pdo) 
    {
        $this->pdo = $pdo;

        $config = require __DIR__ . '/../config.php';
        $namespace = __DIR__ . '|' . $config['host'] . '|' . $config['port'] . '|' . $config['database'];

        $this->cache = new FileCache($namespace);
    }

    // GROUP BY рахує кількість рослин кожного інтервалу один раз, без SQL у циклі.
    public function getAllPlants(string $query = '', ?int $interval = null, bool $due = false): array
    {
        $conditions = [];
        $params = [];

        if ($query !== '') {
            $conditions[] = "p.name LIKE :q ESCAPE '!'";
            $params[':q'] = '%' . strtr($query, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        }

        if ($interval !== null) {
            $conditions[] = 'p.watering_interval_days = :interval';
            $params[':interval'] = $interval;
        }

        if ($due) {
            $conditions[] = 'DATE_ADD(p.last_watered_at, INTERVAL p.watering_interval_days DAY) <= CURDATE()';
        }

        $sql = 'SELECT p.*, grouped.same_interval_count
                FROM plants p
                JOIN (
                    SELECT watering_interval_days, COUNT(*) AS same_interval_count
                    FROM plants
                    GROUP BY watering_interval_days
                ) grouped ON grouped.watering_interval_days = p.watering_interval_days';

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->pdo->prepare($sql . ' ORDER BY p.id DESC');
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function needsWaterToday(): array
    {
        return $this->cache->remember(fn() => $this->getAllPlants('', null, true));
    }

    public function cacheStatus(): string
    {
        return $this->cache->status;
    }

    public function clearCache(): void
    {
        $this->cache->clear();
    }

    public function getCounts(): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS total, COALESCE(SUM(DATE_ADD(last_watered_at, INTERVAL watering_interval_days DAY) <= :today), 0) AS due FROM plants');
        $stmt->execute([':today' => date('Y-m-d')]);
        return $stmt->fetch();
    }

    public function getPlantById(int $id): ?array 
    {
        $stmt = $this->pdo->prepare('SELECT p.*, (SELECT COUNT(*) FROM plants other WHERE other.watering_interval_days = p.watering_interval_days) AS same_interval_count FROM plants p WHERE p.id = :id');
        $stmt->execute([':id' => $id]);
        $plant = $stmt->fetch();
        return $plant ?: null;
    }

    /**
     * Варіант 18: addPlant() - INSERT
     */
    public function addPlant(string $name, int $interval, string $lastWatered, bool $isSucculent, string $sunlightNeeds = 'Пряме сонце'): bool 
    {
        $sql = 'INSERT INTO plants (name, watering_interval_days, last_watered_at, is_succulent, sunlight_needs) 
                VALUES (:name, :interval, :last_watered, :is_succulent, :sunlight_needs)';
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            ':name' => $name,
            ':interval' => $interval,
            ':last_watered' => $lastWatered,
            ':is_succulent' => $isSucculent ? 1 : 0,
            ':sunlight_needs' => $sunlightNeeds
        ]);

        $this->clearCache();

        return $result;
    }

    /**
     * UPDATE запису
     */
    public function updatePlant(int $id, string $name, int $interval, string $lastWatered, bool $isSucculent, string $sunlightNeeds): bool 
    {
        $sql = 'UPDATE plants 
                SET name = :name, watering_interval_days = :interval, last_watered_at = :last_watered, 
                    is_succulent = :is_succulent, sunlight_needs = :sunlight_needs 
                WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':interval' => $interval,
            ':last_watered' => $lastWatered,
            ':is_succulent' => $isSucculent ? 1 : 0,
            ':sunlight_needs' => $sunlightNeeds
        ]);

        $this->clearCache();

        return $result;
    }

    /**
     * Варіант 18: markWatered($id)
     */
    public function markWatered(int $id): bool 
    {
        $stmt = $this->pdo->prepare('UPDATE plants SET last_watered_at = CURDATE() WHERE id = :id');
        $result = $stmt->execute([':id' => $id]);

        $this->clearCache();

        return $result;
    }

    /**
     * Варіант 18: deletePlant($id)
     */
    public function deletePlant(int $id): bool 
    {
        $stmt = $this->pdo->prepare('DELETE FROM plants WHERE id = :id');
        $result = $stmt->execute([':id' => $id]);

        $this->clearCache();

        return $result;
    }
}
