<?php
declare(strict_types=1);

function handle_post(): void
{
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'register') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? '');
        $organization = trim((string)($_POST['organization'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($password) < 10 || !in_array($role, ['student', 'employer'], true)
            || ($role === 'employer' && $organization === '')) {
            flash('error', 'Check your details. Passwords must have at least 10 characters.');
            redirect('register');
        }
        try {
            $stmt = db()->prepare('INSERT INTO users (name,email,password_hash,role,organization) VALUES (?,?,?,?,?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $role === 'employer' ? $organization : null]);
            flash('success', 'Account created. You can now sign in.');
            redirect('login');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                flash('error', 'That email address is already registered.');
                redirect('register');
            }
            throw $exception;
        }
    }

    if ($action === 'login') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT id,name,email,password_hash,role,organization,is_active FROM users WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            flash('error', 'Email or password was not recognized.');
            redirect('login');
        }
        session_regenerate_id(true);
        unset($user['password_hash'], $user['is_active']);
        $_SESSION['user'] = $user;
        redirect(dashboard_page());
    }

    if ($action === 'logout') {
        require_role('student', 'employer', 'admin');
        $_SESSION = [];
        session_regenerate_id(true);
        flash('success', 'You have signed out.');
        redirect('home');
    }

    if ($action === 'update_profile') {
        $user = require_role('student', 'employer');
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $organization = trim((string)($_POST['organization'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || ($user['role'] === 'employer' && ($organization === '' || mb_strlen($organization) > 160))) {
            flash('error', 'Please enter a valid name, email, and organization where required.');
            redirect('profile');
        }
        try {
            if ($user['role'] === 'employer') {
                $stmt = db()->prepare('UPDATE users SET name=?,email=?,organization=? WHERE id=?');
                $stmt->execute([$name, $email, $organization, $user['id']]);
            } else {
                $stmt = db()->prepare('UPDATE users SET name=?,email=? WHERE id=?');
                $stmt->execute([$name, $email, $user['id']]);
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                flash('error', 'That email address is already in use.');
                redirect('profile');
            }
            throw $exception;
        }
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        if ($user['role'] === 'employer') {
            $_SESSION['user']['organization'] = $organization;
        }
        flash('success', 'Your profile has been updated.');
        redirect('profile');
    }

    if ($action === 'create_internship') {
        $user = require_role('employer');
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $mode = (string)($_POST['work_mode'] ?? 'onsite');
        $deadline = trim((string)($_POST['deadline'] ?? ''));
        $parsedDeadline = $deadline === '' ? false : DateTimeImmutable::createFromFormat('!Y-m-d', $deadline);
        $validDate = $deadline === '' || ($parsedDeadline instanceof DateTimeImmutable && $parsedDeadline->format('Y-m-d') === $deadline);
        if ($title === '' || mb_strlen($title) > 160 || $description === '' || mb_strlen($description) > 10000
            || $location === '' || mb_strlen($location) > 120 || !in_array($mode, ['onsite','hybrid','remote'], true) || !$validDate) {
            flash('error', 'Please complete all internship fields with valid values.');
            redirect('employer_jobs');
        }
        $stmt = db()->prepare('INSERT INTO internships (employer_id,title,description,location,work_mode,deadline) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$user['id'], $title, $description, $location, $mode, $deadline ?: null]);
        flash('success', 'Internship submitted for administrator review.');
        redirect('employer_jobs');
    }

    if ($action === 'apply') {
        $user = require_role('student');
        $internshipId = filter_input(INPUT_POST, 'internship_id', FILTER_VALIDATE_INT);
        $letter = trim((string)($_POST['cover_letter'] ?? ''));
        if (!$internshipId || $letter === '' || mb_strlen($letter) > 5000) {
            flash('error', 'Please include a short cover letter.');
            redirect('jobs');
        }
        $stmt = db()->prepare("INSERT INTO applications (internship_id,student_id,cover_letter) SELECT i.id,?,? FROM internships i JOIN users u ON u.id=i.employer_id WHERE i.id=? AND i.status='approved' AND u.is_active=1 AND (i.deadline IS NULL OR i.deadline >= CURDATE())");
        try {
            $stmt->execute([$user['id'], $letter, $internshipId]);
            if ($stmt->rowCount() === 0) {
                flash('error', 'This internship is no longer accepting applications.');
            } else {
                flash('success', 'Your application was submitted.');
            }
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                flash('error', 'You have already applied to this internship.');
            } else {
                throw $exception;
            }
        }
        redirect('applications');
    }

    if ($action === 'application_status') {
        $employer = require_role('employer');
        $applicationId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
        $status = (string)($_POST['status'] ?? '');
        if (!$applicationId || !in_array($status, ['reviewing','accepted','rejected'], true)) {
            flash('error', 'Invalid application update.');
            redirect('employer');
        }
        $stmt = db()->prepare('UPDATE applications a JOIN internships i ON i.id=a.internship_id SET a.status=? WHERE a.id=? AND i.employer_id=?');
        $stmt->execute([$status, $applicationId, $employer['id']]);
        flash('success', 'Application status updated.');
        redirect('employer');
    }

    if ($action === 'admin_status') {
        require_role('admin');
        $type = (string)($_POST['type'] ?? '');
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $status = (string)($_POST['status'] ?? '');
        $allowed = ['internship' => ['approved','rejected','closed'], 'user' => ['active','inactive']];
        if (!$id || !isset($allowed[$type]) || !in_array($status, $allowed[$type], true)) {
            flash('error', 'Invalid moderation request.');
            redirect('admin');
        }
        if ($type === 'internship') {
            $stmt = db()->prepare('UPDATE internships SET status=? WHERE id=?');
            $stmt->execute([$status, $id]);
        } else {
            $active = $status === 'active' ? 1 : 0;
            $stmt = db()->prepare("UPDATE users SET is_active=? WHERE id=? AND role <> 'admin'");
            $stmt->execute([$active, $id]);
        }
        flash('success', 'Record updated.');
        redirect('admin');
    }

    http_response_code(400);
    exit('Unknown action.');
}
