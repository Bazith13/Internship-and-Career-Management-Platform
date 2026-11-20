<?php $config = require __DIR__ . '/../../../config/config.php'; $user = current_user(); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($config['app_name']) ?> — <?= e(ucfirst(str_replace('_', ' ', $page))) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= e(url('home')) ?>"><span class="brand-mark">C</span> CareerBridge</a>
    <button class="menu-toggle" aria-label="Toggle navigation" aria-expanded="false">☰</button>
    <nav class="nav-links" aria-label="Main navigation">
        <a href="<?= e(url('jobs')) ?>">Explore internships</a>
        <?php if ($user): ?>
            <a href="<?= e(url(dashboard_page())) ?>">Dashboard</a>
            <?php if (in_array($user['role'], ['student','employer'], true)): ?><a href="<?= e(url('profile')) ?>">Profile</a><?php endif; ?>
            <form method="post" action="<?= e(url('home')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="nav-button" type="submit">Sign out</button></form>
        <?php else: ?>
            <a href="<?= e(url('login')) ?>">Sign in</a><a class="button button-small" href="<?= e(url('register')) ?>">Create account</a>
        <?php endif; ?>
    </nav>
</header>
<main class="container">
<?php foreach (take_flashes() as $notice): ?>
    <div class="alert alert-<?= e($notice['type']) ?>" role="status"><?= e($notice['message']) ?></div>
<?php endforeach; ?>
