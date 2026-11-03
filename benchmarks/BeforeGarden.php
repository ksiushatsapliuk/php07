<?php
require_once __DIR__ . '/../classes/Garden.php';

// Збережений стан «до»: один список і додатковий COUNT для кожної рослини.
class BeforeGarden extends Garden
{
    protected function selectSql(): string
    {
        return 'SELECT p.* FROM plants p';
    }

    public function getAllPlants(string $query = '', ?int $interval = null, bool $due = false): array
    {
        $rows = parent::getAllPlants($query, $interval, $due);

        foreach ($rows as &$row) {
            $count = $this->pdo->prepare('SELECT COUNT(*) FROM plants WHERE watering_interval_days = ?');
            $count->execute([$row['watering_interval_days']]);
            $row['same_interval_count'] = (int)$count->fetchColumn();
        }

        return $rows;
    }

    public function needsWaterToday(): array
    {
        return $this->getAllPlants('', null, true);
    }
}
