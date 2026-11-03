# Практична робота 07 — варіант 18

## Мета роботи
Навчитися вимірювати продуктивність власного PHP-застосунку (час виконання, використання пам'яті), проводити просте навантажувальне тестування засобом Apache Bench (ab) і застосовувати базові прийоми оптимізації - кешування результатів запитів, індекси бази даних, зменшення кількості SQL-запитів на один HTTP-запит.

## Запуск
```powershell
& C:/php/php.exe -d extension_dir=C:/php/ext -d extension=pdo_mysql tools/install.php
```

Запуск каталогу:
```powershell
& C:/php/php.exe -d extension_dir=C:/php/ext -d extension=pdo_mysql -d extension=mbstring -S localhost:8000
```
Відкрити http://localhost:8000/index.php.

## Структура та обов’язкові контрольні версії
- classes/, lib/, assets/ — код каталогу й ресурси.
- sql/ — схема, окремі CREATE INDEX та міграція дати.
- tools/install.php — встановлення; tools/benchmark.php — відтворення експерименту.
- benchmarks/original06.php — базовий списковий алгоритм API 06 без нового групового підрахунку. Використовує спільні бібліотеки; повної копії старого проєкту немає.
- benchmarks/BeforeGarden.php — збережений N+1: COUNT для кожної рослини в циклі.
- benchmarks/UncachedGarden.php — оптимізований список без кешу для окремого контролю кешування.
- results/ab.txt — вихідний журнал усіх семи AB-серій; results/summary.json — метрики, 70 окремих вимірів, SQL, параметри й плани EXPLAIN.
- docs/ — завдання PDF та звіт Word. README — текстове пояснення.
