<?php
/* MEMBER 5: The single entry point connects the five feature modules. */
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/student.php';
require_once __DIR__ . '/employer.php';
require_once __DIR__ . '/admin.php';

$page = (string)($_GET['page'] ?? 'home');
$allowedPages = ['home', 'login', 'register', 'forgot', 'reset', 'jobs', 'student', 'applications', 'profile', 'employer', 'my-jobs', 'applicants', 'admin'];
if (!in_array($page, $allowedPages, true)) {
    http_response_code(404);
    $page = 'home';
}

/* MEMBER 5 — Send submitted forms to their member-owned feature file. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');

    if (str_starts_with($action, 'auth_')) {
        handle_auth_action(substr($action, 5));
    } elseif (str_starts_with($action, 'student_')) {
        handle_student_action(substr($action, 8));
    } elseif (str_starts_with($action, 'employer_')) {
        handle_employer_action(substr($action, 9));
    } elseif (str_starts_with($action, 'admin_')) {
        handle_admin_action(substr($action, 6));
    } else {
        http_response_code(400);
        exit('Unknown form action.');
    }
}

function render_nav_link(string $label, string $target, string $current): void
{
    $class = $target === $current ? ' class="active"' : '';
    echo '<a' . $class . ' href="' . e(url($target)) . '">' . e($label) . '</a>';
}

function render_flash_message(): void
{
    $message = take_flash();
    if ($message) {
        echo '<div class="notice ' . e($message['type']) . '" role="status">' . e($message['message']) . '</div>';
    }
}

/* MEMBER 5 — Connect the shared page layout and public home page. */
function render_home_page(): void
{
    $statement = db()->query("SELECT i.*, u.organization FROM internships i JOIN users u ON u.id = i.employer_id WHERE i.status = 'approved' AND (i.deadline IS NULL OR i.deadline >= CURRENT_DATE) ORDER BY i.created_at DESC LIMIT 3");
    $featured = $statement->fetchAll();
    ?>
    <section class="hero">
        <div><span class="eyebrow">YOUR NEXT STEP STARTS HERE</span><h1>Find experience that moves your career forward.</h1><p>Discover internships, connect with employers, and keep every application organized in one place.</p><a class="button" href="<?= e(url('jobs')) ?>">Explore internships</a></div>
        <div class="hero-note"><span class="hero-number">01</span><strong>Discover</strong><span>Opportunities chosen for your next move.</span></div>
    </section>
    <section class="section-block">
        <div class="section-heading"><div><span class="eyebrow">FRESH OPPORTUNITIES</span><h2>Featured internships</h2></div><a class="text-link" href="<?= e(url('jobs')) ?>">View all</a></div>
        <?php if ($featured): ?><div class="card-grid"><?php foreach ($featured as $job): ?>
            <article class="job-card"><span class="pill"><?= e($job['work_type']) ?></span><h3><?= e($job['title']) ?></h3><p class="muted"><?= e($job['organization']) ?> · <?= e($job['location']) ?></p><a class="text-link" href="<?= e(url('jobs')) ?>">View opportunity</a></article>
        <?php endforeach; ?></div><?php else: ?><div class="empty-state">No approved internships yet. Check back soon.</div><?php endif; ?>
    </section>
    <section class="cta-strip"><div><span class="eyebrow">BUILT FOR EVERY STEP</span><h2>One place for students, employers, and the career office.</h2></div><a class="button button-light" href="<?= e(url('register')) ?>">Get started</a></section>
    <?php
}

function page_title(string $page): string
{
    return match ($page) {
        'login' => 'Sign in', 'register' => 'Create account', 'forgot' => 'Forgot password', 'reset' => 'Reset password',
        'jobs' => 'Internships', 'student' => 'Student dashboard', 'applications' => 'My applications', 'profile' => 'My profile',
        'employer' => 'Employer dashboard', 'my-jobs' => 'My listings', 'applicants' => 'Applicants', 'admin' => 'Admin dashboard',
        default => 'CareerBridge',
    };
}

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="CareerBridge connects students with internships and employers.">
    <title><?= e(page_title($page)) ?> · CareerBridge</title>
    <link rel="stylesheet" href="style.css">
    <script src="app.js" defer></script>
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= e(url('home')) ?>"><span class="brand-mark">C</span><span>career<span class="brand-accent">bridge</span></span></a>
    <nav class="main-nav" aria-label="Main navigation">
        <?php render_nav_link('Home', 'home', $page); ?><?php render_nav_link('Internships', 'jobs', $page); ?>
        <?php $user = current_user(); if ($user && $user['role'] === 'student'): ?>
            <?php render_nav_link('Dashboard', 'student', $page); ?><?php render_nav_link('Applications', 'applications', $page); ?><?php render_nav_link('Profile', 'profile', $page); ?>
        <?php elseif ($user && $user['role'] === 'employer'): ?>
            <?php render_nav_link('Dashboard', 'employer', $page); ?><?php render_nav_link('My listings', 'my-jobs', $page); ?><?php render_nav_link('Applicants', 'applicants', $page); ?>
        <?php elseif ($user && $user['role'] === 'admin'): ?>
            <?php render_nav_link('Admin', 'admin', $page); ?>
        <?php endif; ?>
    </nav>
    <div class="nav-actions">
        <?php if ($user): ?><span class="nav-name"><?= e($user['name']) ?></span><form method="post" action="<?= e(url($page)) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="auth_logout"><button class="button button-small button-outline" type="submit">Sign out</button></form>
        <?php else: ?><a class="text-link" href="<?= e(url('login')) ?>">Sign in</a><a class="button button-small" href="<?= e(url('register')) ?>">Join now</a><?php endif; ?>
    </div>
</header>
<main class="container">
    <?php render_flash_message(); ?>
    <?php
    if (in_array($page, ['login', 'register', 'forgot', 'reset'], true)) {
        render_auth_page($page);
    } elseif ($page === 'jobs') {
        render_jobs_page();
    } elseif ($page === 'student') {
        render_student_dashboard();
    } elseif ($page === 'applications') {
        render_student_applications();
    } elseif ($page === 'profile') {
        render_student_profile();
    } elseif ($page === 'employer') {
        render_employer_dashboard();
    } elseif ($page === 'my-jobs') {
        render_employer_jobs();
    } elseif ($page === 'applicants') {
        render_employer_applicants();
    } elseif ($page === 'admin') {
        render_admin_page();
    } else {
        render_home_page();
    }
    ?>
</main>
<footer class="site-footer"><span>© <?= date('Y') ?> CareerBridge</span><span>Internships and careers, connected.</span></footer>
</body>
</html>
