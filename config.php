<?php
declare(strict_types=1);

session_start();

const DB_HOST = '127.0.0.1';
const DB_NAME = 'c2c_marketplace';
const DB_USER = 'root';
const DB_PASS = '';

const APP_NAME = 'C2C Marketplace';
const ADMIN_EMAIL = 'admin@c2c.local';
const ADMIN_PASSWORD = 'Admin123!';

const PROVINCES = [
    'Eastern Cape',
    'Free State',
    'Gauteng',
    'KwaZulu-Natal',
    'Limpopo',
    'Mpumalanga',
    'Northern Cape',
    'North West',
    'Western Cape',
];

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money(int|float|string $value): string
{
    return 'R' . number_format((float) $value, 2, '.', ' ');
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('Please log in to continue.');
        redirect('index.php?route=login');
    }

    return $user;
}

function require_role(array|string $roles): array
{
    $user = require_login();
    $roles = (array) $roles;
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('Access denied');
    }

    return $user;
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }

    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $message;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(419);
            exit('Invalid form token');
        }
    }
}

function cart_items_count(): int
{
    return array_sum($_SESSION['cart'] ?? []);
}

function selected(string $actual, string $expected): string
{
    return $actual === $expected ? 'selected' : '';
}

function checked(bool $value): string
{
    return $value ? 'checked' : '';
}
