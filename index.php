<?php require_once __DIR__ . '/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FloraCare — Догляд за рослинами (PDO/MySQL)</title>
    <link rel="stylesheet" href="style.css?v=3">
</head>
<body>
<div class="app-container">
    <header class="header">
        <div class="header-icon">
            <img src="alocasia.png" alt="FloraCare" class="header-icon-img">
        </div>
        <div>
            <h1>FloraCare</h1>
            <p class="subtitle">Каталог догляду за рослинами - v7 - Оптимізація API</p>
        </div>
    </header>

    <!-- Форма додавання (INSERT) -->
    <div class="form-card" style="margin-bottom: 32px;">
        <h2 class="form-title">Додати нову рослину</h2>
        <form id="addPlantForm" action="api.php?resource=plants" method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label for="name">Назва рослини <span class="req">*</span></label>
                <input type="text" id="name" name="name" maxlength="255" class="form-control" required placeholder="напр. Сукулент Красула">
            </div>
            <div class="form-group">
                <label for="wateringIntervalDays">Інтервал поливу (днів) <span class="req">*</span></label>
                <input type="number" id="wateringIntervalDays" name="watering_interval_days" class="form-control" min="1" max="3650" required placeholder="7">
            </div>
            <div class="form-group">
                <label for="lastWatered">Дата останнього поливу <span class="req">*</span></label>
                <input type="date" id="lastWatered" name="last_watered_at" class="form-control" max="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label class="checkbox-group">
                    <input type="checkbox" id="isSucculent" name="is_succulent" class="custom-checkbox-input">
                    <span class="custom-checkbox-box"></span>
                    <img src="succulent.png" alt="Сукулент" class="checkbox-icon-img">
                    <span class="checkbox-label-text">Це сукулент / кактус</span>
                </label>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-primary">Зберегти в базу даних</button>
            </div>
        </form>
    </div>

    <!-- Підсумок каталогу -->
    <div class="summary-card">
        <div class="summary-info">
            <span class="summary-label">Сьогодні потребують поливу</span>
            <div class="summary-value">
                <span id="needsWaterCount">…</span>
                <small id="plantCount">із … рослин</small>
            </div>
        </div>
        <div class="summary-status-icon" id="summaryIcon">
            <img src="watering-can.png" alt="Полив" class="summary-icon-img">
        </div>
    </div>

    <!-- Живий пошук і список рослин, яким потрібен полив -->
    <div class="form-card" style="margin-bottom: 32px;">
        <h2 class="form-title">Пошук за назвою</h2>
        <form id="searchForm" class="form-actions" style="margin-top:0;">
            <input type="search" id="search" name="q" class="form-control" placeholder="Назва або частина назви рослини" aria-label="Назва рослини">
            <button type="submit" class="btn-primary" style="flex:none;">Знайти</button>
        </form>
        <label class="checkbox-group" style="margin-top:16px;">
            <input type="checkbox" id="onlyNeedsWater" class="custom-checkbox-input">
            <span class="custom-checkbox-box"></span>
            <span class="checkbox-label-text">Лише ті, що потребують поливу</span>
        </label>
    </div>

    <!-- Сортування збережено з практичної роботи 3 -->
    <div class="sort-bar">
        <label for="sortSelect">Сортування каталогу:</label>
        <select id="sortSelect" class="form-control">
            <option value="default">За замовчуванням (новіші спочатку)</option>
            <option value="name">За назвою (А–Я)</option>
            <option value="days">За датою поливу (спочатку давно не поливані)</option>
        </select>
    </div>

    <!-- Таблиця всіх записів (SELECT) -->
    <div class="table-wrap">
        <table class="plant-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Назва</th>
                    <th>Інтервал (дн.)</th>
                    <th>Останній полив</th>
                    <th>Минуло днів</th>
                    <th>Наступний полив</th>
                    <th>Статус</th>
                    <th>Дії</th>
                </tr>
            </thead>
            <tbody id="plantTableBody" aria-live="polite">
            </tbody>
        </table>
    </div>
</div>
<script src="sort.js"></script>
<script src="script.js"></script>
</body>
</html>
