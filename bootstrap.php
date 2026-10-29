<?php
// однаковий час і захист форм для сторінок та API, без підключення до БД.
date_default_timezone_set('Europe/Kyiv');

session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf'];

// запити пошуку не блокують одне одного через відкриту сесію.
session_write_close();

function validCsrfToken(): bool
{
    global $csrfToken;
    $received = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? null;
    return is_string($received) && hash_equals($csrfToken, $received);
}

function requireFormPost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST'); http_response_code(405);
        exit('Ця дія доступна лише через відправлення форми.');
    }
    if (!validCsrfToken()) {
        http_response_code(403);
        exit('Не вдалося перевірити форму. Оновіть сторінку та повторіть дію.');
    }
}
