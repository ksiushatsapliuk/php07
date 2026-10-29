<?php
function nextWateringDate(int $daysSince, int $interval): string
{
    $daysLeft = $interval - $daysSince;
    if ($daysLeft === 0) return 'Сьогодні';
    if ($daysLeft < 0) return 'Прострочено на ' . abs($daysLeft) . ' дн.';
    return date('Y-m-d', strtotime("+{$daysLeft} days"));
}

// Форми та JSON API використовують однакові назви полів.
function plantInput(array $data): array
{
    $name = $data['name'] ?? null;
    if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name), 'UTF-8') > 255) {
        throw new InvalidArgumentException('Поле name: потрібна назва від 1 до 255 символів.');
    }
    $interval = $data['watering_interval_days'] ?? null;
    if ((!is_string($interval) && !is_int($interval)) ||
        ($interval = filter_var($interval, FILTER_VALIDATE_INT)) === false || $interval < 1 || $interval > 3650) {
        throw new InvalidArgumentException('Поле watering_interval_days: потрібне ціле число від 1 до 3650.');
    }
    $date = $data['last_watered_at'] ?? null;
    $parsed = is_string($date) && preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
    if (!$parsed || $parsed->format('Y-m-d') !== $date || $date > date('Y-m-d') || $date < '1000-01-01') {
        throw new InvalidArgumentException('Поле last_watered_at: потрібна дійсна дата YYYY-MM-DD не пізніше сьогодні.');
    }
    $flag = $data['is_succulent'] ?? false;
    if (is_array($flag) || is_object($flag)) {
        throw new InvalidArgumentException('Поле is_succulent: використовуйте true/false або 1/0.');
    }
    $succulent = filter_var($flag, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($succulent === null) {
        throw new InvalidArgumentException('Поле is_succulent: використовуйте true/false або 1/0.');
    }
    $sunlight = $data['sunlight_needs'] ?? ($succulent ? 'Пряме сонце' : 'Розсіяне світло');
    if (!is_string($sunlight) || trim($sunlight) === '' || mb_strlen($sunlight, 'UTF-8') > 100) {
        throw new InvalidArgumentException('Поле sunlight_needs: потрібен текст від 1 до 100 символів.');
    }
    return ['name' => trim($name), 'interval' => $interval, 'date' => $date,
        'succulent' => $succulent, 'sunlight' => trim($sunlight)];
}
