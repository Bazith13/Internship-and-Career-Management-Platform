<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../app/db.php';
$email = strtolower(trim($argv[1] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/create_admin.php admin@example.com\n");
    exit(1);
}
fwrite(STDOUT, 'Admin name: ');
$name = trim((string) fgets(STDIN));
fwrite(STDOUT, 'Password (10+ chars): ');
$password = trim((string) fgets(STDIN));
if ($name === '' || strlen($password) < 10) {
    fwrite(STDERR, "Name is required and password must have at least 10 characters.\n");
    exit(1);
}
$stmt = db()->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,'admin')");
$stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Admin account created.\n");
