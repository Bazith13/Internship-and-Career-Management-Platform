<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post();
}

$page = (string)($_GET['page'] ?? 'home');
$allowed = ['home','login','register','jobs','student','applications','employer','employer_jobs','admin','profile'];
if (!in_array($page, $allowed, true)) {
    http_response_code(404);
    $page = 'home';
}
$user = current_user();
if (in_array($page, ['login','register'], true) && $user) {
    redirect(dashboard_page());
}

switch ($page) {
    case 'jobs':
        $search = trim((string)($_GET['q'] ?? ''));
        $mode = (string)($_GET['mode'] ?? '');
        $sql = "SELECT i.*,u.name,u.organization FROM internships i JOIN users u ON u.id=i.employer_id WHERE i.status='approved' AND u.is_active=1 AND (i.deadline IS NULL OR i.deadline>=CURDATE())";
        $params = [];
        if ($search !== '') {
            $sql .= ' AND (i.title LIKE ? OR i.description LIKE ? OR i.location LIKE ? OR u.organization LIKE ?)';
            $term = '%' . $search . '%'; $params = [$term,$term,$term,$term];
        }
        if (in_array($mode, ['onsite','hybrid','remote'], true)) { $sql .= ' AND i.work_mode=?'; $params[] = $mode; }
        $sql .= ' ORDER BY i.created_at DESC LIMIT 100';
        $stmt = db()->prepare($sql); $stmt->execute($params); $jobs = $stmt->fetchAll();
        break;
    case 'student':
        $student = require_role('student');
        $stmt = db()->prepare("SELECT COUNT(*) applications, SUM(status='reviewing') reviewing FROM applications WHERE student_id=?"); $stmt->execute([$student['id']]); $stats = $stmt->fetch();
        $stats['reviewing'] = $stats['reviewing'] ?? 0;
        $stats['jobs'] = (int)db()->query("SELECT COUNT(*) FROM internships WHERE status='approved' AND (deadline IS NULL OR deadline>=CURDATE())")->fetchColumn();
        $stmt = db()->prepare('SELECT a.status,a.created_at,i.title,u.organization FROM applications a JOIN internships i ON i.id=a.internship_id JOIN users u ON u.id=i.employer_id WHERE a.student_id=? ORDER BY a.created_at DESC LIMIT 8'); $stmt->execute([$student['id']]); $applications=$stmt->fetchAll();
        break;
    case 'applications':
        $student = require_role('student');
        $stmt = db()->prepare('SELECT a.status,a.created_at,i.title,u.organization FROM applications a JOIN internships i ON i.id=a.internship_id JOIN users u ON u.id=i.employer_id WHERE a.student_id=? ORDER BY a.created_at DESC'); $stmt->execute([$student['id']]); $applications=$stmt->fetchAll();
        break;
    case 'profile':
        $profileUser = require_role('student', 'employer');
        break;
    case 'employer':
        $employer = require_role('employer');
        $stmt = db()->prepare("SELECT COUNT(*) listings FROM internships WHERE employer_id=? AND status='approved'"); $stmt->execute([$employer['id']]); $stats=$stmt->fetch();
        $stmt = db()->prepare('SELECT COUNT(*) applications, SUM(a.status IN (\'submitted\',\'reviewing\')) pending FROM applications a JOIN internships i ON i.id=a.internship_id WHERE i.employer_id=?'); $stmt->execute([$employer['id']]); $counts=$stmt->fetch(); $stats=array_merge($stats,$counts); $stats['pending']=$stats['pending'] ?? 0;
        $stmt = db()->prepare('SELECT a.id,a.status,a.cover_letter,u.name student_name,u.email,i.title FROM applications a JOIN internships i ON i.id=a.internship_id JOIN users u ON u.id=a.student_id WHERE i.employer_id=? ORDER BY a.created_at DESC LIMIT 30'); $stmt->execute([$employer['id']]); $applicants=$stmt->fetchAll();
        break;
    case 'employer_jobs':
        $employer = require_role('employer');
        $stmt = db()->prepare('SELECT * FROM internships WHERE employer_id=? ORDER BY created_at DESC'); $stmt->execute([$employer['id']]); $listings=$stmt->fetchAll();
        break;
    case 'admin':
        require_role('admin');
        $stats=['students'=>(int)db()->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),'employers'=>(int)db()->query("SELECT COUNT(*) FROM users WHERE role='employer'")->fetchColumn(),'pending'=>(int)db()->query("SELECT COUNT(*) FROM internships WHERE status='pending'")->fetchColumn()];
        $pendingListings=db()->query("SELECT i.*,u.organization FROM internships i JOIN users u ON u.id=i.employer_id WHERE i.status='pending' ORDER BY i.created_at ASC LIMIT 50")->fetchAll();
        $users=db()->query('SELECT id,name,email,role,is_active FROM users ORDER BY created_at DESC LIMIT 50')->fetchAll();
        break;
}

require __DIR__ . '/../app/views/layout/header.php';
$view = __DIR__ . '/../app/views/pages/' . $page . '.php';
require $view;
require __DIR__ . '/../app/views/layout/footer.php';
