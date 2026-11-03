ALTER TABLE plants ADD COLUMN next_watering_at DATE
GENERATED ALWAYS AS (DATE_ADD(last_watered_at, INTERVAL watering_interval_days DAY)) STORED;
