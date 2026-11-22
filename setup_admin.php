<?php
/* MEMBER 5: Run once from the command line to create the first admin account. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found.');
}

require_once __DIR__ . '/common.php';

$existing = db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ((int)$existing > 0) {
    exit("An admin account already exists.\n");
}

fwrite(STDOUT, 'Admin name: ');
$name = trim((string)fgets(STDIN));
fwrite(STDOUT, 'Admin email: ');
$email = strtolower(trim((string)fgets(STDIN)));
fwrite(STDOUT, 'Password (at least 10 characters): ');
$password = trim((string)fgets(STDIN));

if (strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
    exit("Name, email, or password is invalid. Nothing was created.\n");
}

$statement = db()->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
$statement->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Admin account created. Keep the password private.\n");
