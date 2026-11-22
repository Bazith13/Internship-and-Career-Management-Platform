<?php
/* MEMBER 1: Registration, sign-in, sign-out, and password recovery. */
declare(strict_types=1);

/* MEMBER 1 — Handle account form submissions. */
function handle_auth_action(string $action): void
{
    if ($action === 'login') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $statement = db()->prepare('SELECT id, name, email, password_hash, role, organization, is_active FROM users WHERE email = ? LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();

        if (!$user || !(int)$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            flash('error', 'Email or password is incorrect.');
            redirect('login');
        }

        session_regenerate_id(true);
        unset($user['password_hash'], $user['is_active']);
        $_SESSION['user'] = $user;
        flash('success', 'Welcome back, ' . $user['name'] . '.');
        redirect(dashboard_page());
    }

    if ($action === 'register') {
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? '');
        $organization = trim((string)($_POST['organization'] ?? ''));

        if (strlen($name) < 2 || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a name and a valid email address.');
            redirect('register');
        }
        if (strlen($password) < 10) {
            flash('error', 'Choose a password with at least 10 characters.');
            redirect('register');
        }
        if (!in_array($role, ['student', 'employer'], true)) {
            flash('error', 'Choose Student or Employer as the account type.');
            redirect('register');
        }
        if ($role === 'employer' && (strlen($organization) < 2 || strlen($organization) > 150)) {
            flash('error', 'Enter the employer or organization name.');
            redirect('register');
        }

        try {
            $statement = db()->prepare('INSERT INTO users (name, email, password_hash, role, organization) VALUES (?, ?, ?, ?, ?)');
            $statement->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
                $role === 'employer' ? $organization : null,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                flash('error', 'An account with that email already exists.');
                redirect('register');
            }
            throw $exception;
        }

        flash('success', 'Account created. You can now sign in.');
        redirect('login');
    }

    if ($action === 'forgot') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $statement = db()->prepare('SELECT id, email FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
            $statement->execute([$email]);
            $user = $statement->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $delete = db()->prepare('DELETE FROM password_resets WHERE user_id = ?');
                $delete->execute([(int)$user['id']]);
                $insert = db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR))');
                $insert->execute([(int)$user['id'], $tokenHash]);

                $link = APP_URL . '/index.php?page=reset&token=' . rawurlencode($token);
                $subject = 'Reset your CareerBridge password';
                $message = "A password reset was requested for your CareerBridge account.\n\n" .
                    "Open this link within one hour to choose a new password:\n" . $link . "\n\n" .
                    "If you did not request this, ignore this email.";
                $headers = 'From: CareerBridge <' . MAIL_FROM . ">\r\n" .
                    "Content-Type: text/plain; charset=UTF-8\r\n";

                if (!mail($user['email'], $subject, $message, $headers)) {
                    $delete = db()->prepare('DELETE FROM password_resets WHERE user_id = ?');
                    $delete->execute([(int)$user['id']]);
                }
            }
        }

        flash('success', 'If an active account matches that email and email is configured, a reset link will arrive shortly.');
        redirect('forgot');
    }

    if ($action === 'reset') {
        $token = (string)($_POST['token'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['password_confirmation'] ?? '');

        if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            flash('error', 'That reset link is invalid or expired.');
            redirect('forgot');
        }
        if (strlen($password) < 10 || $password !== $confirmation) {
            flash('error', 'Use a matching password with at least 10 characters.');
            redirect('reset', ['token' => $token]);
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('SELECT user_id FROM password_resets WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP() FOR UPDATE');
            $statement->execute([hash('sha256', $token)]);
            $reset = $statement->fetch();

            if (!$reset) {
                $pdo->rollBack();
                flash('error', 'That reset link is invalid or expired.');
                redirect('forgot');
            }

            $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND is_active = 1');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), (int)$reset['user_id']]);
            $delete = $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?');
            $delete->execute([(int)$reset['user_id']]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        flash('success', 'Password changed. Sign in with your new password.');
        redirect('login');
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('success', 'You have signed out.');
        redirect('home');
    }

    flash('error', 'That action is not available.');
    redirect('home');
}

/* MEMBER 1 — Show login, registration, and password recovery forms. */
function render_auth_page(string $page): void
{
    if ($page === 'login') {
        ?>
        <section class="form-card">
            <span class="eyebrow">WELCOME BACK</span><h1>Sign in</h1>
            <form method="post" action="<?= e(url('login')) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="auth_login">
                <label>Email<input type="email" name="email" required autocomplete="email"></label>
                <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
                <button class="button" type="submit">Sign in</button>
            </form>
            <p><a href="<?= e(url('forgot')) ?>">Forgot password?</a></p>
            <p>New to CareerBridge? <a href="<?= e(url('register')) ?>">Create an account</a></p>
        </section>
        <?php
        return;
    }

    if ($page === 'register') {
        ?>
        <section class="form-card">
            <span class="eyebrow">JOIN CAREERBRIDGE</span><h1>Create an account</h1>
            <form method="post" action="<?= e(url('register')) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="auth_register">
                <label>Your name<input name="name" maxlength="100" required autocomplete="name"></label>
                <label>Email<input type="email" name="email" required autocomplete="email"></label>
                <label>Account type<select name="role" id="account-role" required><option value="student">Student</option><option value="employer">Employer</option></select></label>
                <label id="organization-field" class="hidden">Organization<input name="organization" maxlength="150"></label>
                <label>Password <small>(at least 10 characters)</small><input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
                <button class="button" type="submit">Create account</button>
            </form>
            <p>Already registered? <a href="<?= e(url('login')) ?>">Sign in</a></p>
        </section>
        <?php
        return;
    }

    if ($page === 'forgot') {
        ?>
        <section class="form-card">
            <span class="eyebrow">ACCOUNT RECOVERY</span><h1>Forgot password?</h1>
            <p>Enter your account email. If email is configured, we will send a one-hour reset link.</p>
            <form method="post" action="<?= e(url('forgot')) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="auth_forgot">
                <label>Email<input type="email" name="email" required autocomplete="email"></label>
                <button class="button" type="submit">Send reset link</button>
            </form>
            <p><a href="<?= e(url('login')) ?>">Back to sign in</a></p>
        </section>
        <?php
        return;
    }

    $token = (string)($_GET['token'] ?? '');
    ?>
    <section class="form-card">
        <span class="eyebrow">ACCOUNT RECOVERY</span><h1>Choose a new password</h1>
        <form method="post" action="<?= e(url('reset')) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="auth_reset">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label>New password <small>(at least 10 characters)</small><input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
            <label>Confirm password<input type="password" name="password_confirmation" minlength="10" required autocomplete="new-password"></label>
            <button class="button" type="submit">Change password</button>
        </form>
    </section>
    <?php
}
