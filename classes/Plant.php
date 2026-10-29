<?php
class Plant
{
    protected string $name;
    protected int $wateringIntervalDays;
    protected int $daysSinceWatered;

    public function __construct(string $name, int $wateringIntervalDays, int $daysSinceWatered = 0)
    {
        $this->name = $name;
        $this->wateringIntervalDays = $wateringIntervalDays;
        $this->daysSinceWatered = $daysSinceWatered;
    }

    public function getName(): string 
    {
        return $this->name;
    }

    public function getWateringIntervalDays(): int 
    {
        return $this->wateringIntervalDays;
    }

    public function getDaysSinceWatered(): int 
    {
        return $this->daysSinceWatered;
    }

    public function needsWater(): bool 
    {
        return $this->daysSinceWatered >= $this->wateringIntervalDays;
    }

}