<?php
// Спільна міграція для встановлення та тестів; повторне виконання не дублює індекси.
function applyIndexes(PDO $pdo): void
{
    $column = $pdo->query("SHOW COLUMNS FROM plants LIKE 'next_watering_at'")->fetch(PDO::FETCH_ASSOC);

    if (!$column) {
        $pdo->exec(file_get_contents(__DIR__ . '/../sql/01_due_date.sql'));
    } elseif (!str_contains($column['Extra'], 'STORED GENERATED')) {
        throw new RuntimeException('next_watering_at має бути обчислюваним STORED-стовпцем.');
    }

    foreach (explode(';', file_get_contents(__DIR__ . '/../sql/indexes.sql')) as $sql) {
        $sql = trim($sql);

        if ($sql === '') continue;

        if (!preg_match('/^CREATE INDEX (\w+) ON plants \((\w+)\)$/', $sql, $match)) {
            throw new RuntimeException('Некоректний оператор у indexes.sql.');
        }

        $index = $pdo->query("SHOW INDEX FROM plants WHERE Key_name = " . $pdo->quote($match[1]))->fetchAll(PDO::FETCH_ASSOC);

        if ($index === []) {
            $pdo->exec($sql);
        } elseif (count($index) !== 1 || $index[0]['Column_name'] !== $match[2]) {
            throw new RuntimeException('Індекс ' . $match[1] . ' має інше визначення.');
        }
    }
}
