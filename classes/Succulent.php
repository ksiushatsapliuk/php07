<?php
require_once __DIR__ . '/Plant.php';

class Succulent extends Plant 
{
    private string $sunlightNeeds;

    public function __construct(string $name, int $wateringIntervalDays, int $daysSinceWatered = 0, string $sunlightNeeds = 'Пряме сонце') 
    {
        parent::__construct($name, $wateringIntervalDays, $daysSinceWatered);
        $this->sunlightNeeds = $sunlightNeeds;
    }

    public function getSunlightNeeds(): string 
    {
        return $this->sunlightNeeds;
    }

}