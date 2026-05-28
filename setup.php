<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$schema = (string) file_get_contents(__DIR__ . '/schema.sql');
foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
    $pdo->exec($statement);
}

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([ADMIN_EMAIL]);
if (!$stmt->fetch()) {
    $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role, province) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        'System Admin',
        ADMIN_EMAIL,
        password_hash(ADMIN_PASSWORD, PASSWORD_BCRYPT),
        'admin',
        'Gauteng',
    ]);
}

echo 'Setup complete. Admin login: ' . ADMIN_EMAIL . ' / ' . ADMIN_PASSWORD;
