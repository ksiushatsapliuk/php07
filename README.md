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
