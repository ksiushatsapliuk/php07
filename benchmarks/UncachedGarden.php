<?php
require_once __DIR__ . '/../classes/Garden.php';

// Контроль кешування: та сама оптимізована логіка без файлового кешу.
class UncachedGarden extends Garden
{
    public function needsWaterToday(): array
    {
        return $this->getAllPlants('', null, true);
    }
}
