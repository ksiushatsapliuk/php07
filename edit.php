<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/classes/Garden.php';
require_once __DIR__ . '/lib/functions.php';

$garden = new Garden($pdo);
$id = (int)($_GET['id'] ?? 0);
$plantData = $garden->getPlantById($id);

if (!$plantData) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireFormPost();
    try {
        $input = plantInput($_POST);

        if ($input['succulent'] === (bool)$plantData['is_succulent']) {
            $input['sunlight'] = $plantData['sunlight_needs'];
        }
    } catch (InvalidArgumentException $error) {
        http_response_code(422);
        exit(htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8') . ' Поверніться до форми кнопкою Назад.');
    }
    $garden->updatePlant($id, $input['name'], $input['interval'], $input['date'], $input['succulent'],
        $input['sunlight']);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Редагування — FloraCare</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-container">
        <div class="form-card">
            <h2 class="form-title">Редагування рослини #<?= $id ?></h2>
            <form action="edit.php?id=<?= $id ?>" method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label for="name">Назва рослини</label>
                    <input type="text" id="name" name="name" maxlength="255" class="form-control" value="<?= htmlspecialchars($plantData['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="wateringIntervalDays">Інтервал поливу (днів)</label>
                    <input type="number" id="wateringIntervalDays" name="watering_interval_days" min="1" max="3650" class="form-control" value="<?= (int)$plantData['watering_interval_days'] ?>" required>
                </div>
                <div class="form-group">
                    <label for="lastWatered">Дата останнього поливу</label>
                    <input type="date" id="lastWatered" name="last_watered_at" max="<?= date('Y-m-d') ?>" class="form-control" value="<?= htmlspecialchars($plantData['last_watered_at'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label class="checkbox-group">
                        <input type="checkbox" id="isSucculent" name="is_succulent" class="custom-checkbox-input" <?= $plantData['is_succulent'] ? 'checked' : '' ?>>
                        <span class="custom-checkbox-box"></span>
                        <span class="checkbox-label-text">Це сукулент / кактус</span>
                    </label>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Зберегти зміни</button>
                    <a href="index.php" class="btn-secondary" style="text-align: center; text-decoration: none;">Скасувати</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
