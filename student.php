<?php
/* MEMBER 2: Student dashboard, internship search, applications, and profile. */
declare(strict_types=1);

/* MEMBER 2 — Handle applications and profile updates. */
function handle_student_action(string $action): void
{
    $student = require_role('student');

    if ($action === 'apply') {
        $internshipId = filter_input(INPUT_POST, 'internship_id', FILTER_VALIDATE_INT);
        $coverLetter = trim((string) ($_POST['cover_letter'] ?? ''));
        if (!$internshipId || strlen($coverLetter) > 2000) {
            flash('error', 'Please check the application details and try again.');
            redirect('jobs');
        }

        $job = db()->prepare("SELECT id FROM internships WHERE id = ? AND status = 'approved' AND (deadline IS NULL OR deadline >= CURRENT_DATE)");
        $job->execute([$internshipId]);
        if (!$job->fetch()) {
            flash('error', 'That internship is no longer available.');
            redirect('jobs');
        }

        try {
            $statement = db()->prepare('INSERT INTO applications (internship_id, student_id, cover_letter) VALUES (?, ?, ?)');
            $statement->execute([$internshipId, (int) $student['id'], $coverLetter]);
            flash('success', 'Your application was submitted.');
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                flash('error', 'You have already applied for this internship.');
                redirect('jobs');
            }
            throw $exception;
        }
        redirect('applications');
    }

    if ($action === 'profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        if (strlen($name) < 2 || strlen($name) > 100) {
            flash('error', 'Your name must be between 2 and 100 characters.');
            redirect('profile');
        }
        $statement = db()->prepare('UPDATE users SET name = ? WHERE id = ? AND role = \'student\'');
        $statement->execute([$name, (int) $student['id']]);
        $_SESSION['user']['name'] = $name;
        flash('success', 'Profile updated.');
        redirect('profile');
    }

    flash('error', 'That student action is not available.');
    redirect('student');
}

/* MEMBER 2 — Show available internships and the apply form. */
function render_jobs_page(): void
{
    $statement = db()->query("SELECT i.*, u.organization FROM internships i JOIN users u ON u.id = i.employer_id WHERE i.status = 'approved' AND (i.deadline IS NULL OR i.deadline >= CURRENT_DATE) ORDER BY i.created_at DESC");
    $jobs = $statement->fetchAll();
    $user = current_user();
    $appliedIds = [];
    if ($user && $user['role'] === 'student') {
        $applied = db()->prepare('SELECT internship_id FROM applications WHERE student_id = ?');
        $applied->execute([(int) $user['id']]);
        $appliedIds = array_map('intval', $applied->fetchAll(PDO::FETCH_COLUMN));
    }
    ?>
    <section class="page-heading"><span class="eyebrow">OPPORTUNITIES</span>
        <h1>Find your next internship</h1>
        <p>Explore approved opportunities from employers on CareerBridge.</p>
    </section>
    <?php if ($jobs): ?>
        <div class="job-list"><?php foreach ($jobs as $job): ?>
                <article class="job-card job-row">
                    <div class="job-copy"><span class="pill"><?= e($job['work_type']) ?></span>
                        <h2><?= e($job['title']) ?></h2>
                        <p class="muted"><?= e($job['organization']) ?> · <?= e($job['location']) ?><?php if ($job['deadline']): ?>
                                · Apply by <?= e($job['deadline']) ?><?php endif; ?></p>
                        <p><?= nl2br(e($job['description'])) ?></p>
                    </div>
                    <div class="job-action">
                        <?php if ($user && $user['role'] === 'student' && in_array((int) $job['id'], $appliedIds, true)): ?><span
                                class="pill pill-success">Applied</span>
                        <?php elseif ($user && $user['role'] === 'student'): ?>
                            <form method="post" action="<?= e(url('jobs')) ?>" class="apply-form">
                                <?= csrf_field() ?><input type="hidden" name="action" value="student_apply"><input type="hidden"
                                    name="internship_id" value="<?= (int) $job['id'] ?>">
                                <label class="visually-hidden" for="letter-<?= (int) $job['id'] ?>">Short cover letter</label><textarea
                                    id="letter-<?= (int) $job['id'] ?>" name="cover_letter" maxlength="2000"
                                    placeholder="Short note to the employer (optional)"></textarea>
                                <button class="button button-small" type="submit">Apply</button>
                            </form>
                        <?php elseif (!$user): ?><a class="button button-small" href="<?= e(url('login')) ?>">Sign in to
                                apply</a><?php else: ?><span class="muted">Student accounts can apply.</span><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div><?php else: ?>
        <div class="empty-state">No internships are open right now. Please check again later.</div><?php endif; ?>
<?php
}

/* MEMBER 2 — Show application progress and student pages. */
function render_student_dashboard(): void
{
    $student = require_role('student');
    $stats = db()->prepare('SELECT COUNT(*) AS total, SUM(status = \'submitted\') AS submitted, SUM(status = \'shortlisted\') AS shortlisted FROM applications WHERE student_id = ?');
    $stats->execute([(int) $student['id']]);
    $counts = $stats->fetch() ?: ['total' => 0, 'submitted' => 0, 'shortlisted' => 0];
    $recent = db()->prepare('SELECT a.status, a.created_at, i.title, u.organization FROM applications a JOIN internships i ON i.id = a.internship_id JOIN users u ON u.id = i.employer_id WHERE a.student_id = ? ORDER BY a.created_at DESC LIMIT 5');
    $recent->execute([(int) $student['id']]);
    $recentRows = $recent->fetchAll();
    ?>
    <section class="page-heading"><span class="eyebrow">STUDENT PORTAL</span>
        <h1>Hello, <?= e($student['name']) ?></h1>
        <p>Keep track of your search and make your next career move.</p>
    </section>
    <div class="stat-grid">
        <article class="stat-card"><span>Applications</span><strong><?= (int) $counts['total'] ?></strong></article>
        <article class="stat-card"><span>Submitted</span><strong><?= (int) ($counts['submitted'] ?? 0) ?></strong></article>
        <article class="stat-card stat-accent">
            <span>Shortlisted</span><strong><?= (int) ($counts['shortlisted'] ?? 0) ?></strong></article>
    </div>
    <section class="panel">
        <div class="section-heading">
            <div><span class="eyebrow">RECENT ACTIVITY</span>
                <h2>Your latest applications</h2>
            </div><a class="text-link" href="<?= e(url('applications')) ?>">All applications</a>
        </div>
        <?php if ($recentRows): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Internship</th>
                            <th>Employer</th>
                            <th>Status</th>
                            <th>Applied</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($recentRows as $item): ?>
                            <tr>
                                <td><?= e($item['title']) ?></td>
                                <td><?= e($item['organization']) ?></td>
                                <td><span class="pill"><?= e(ucfirst($item['status'])) ?></span></td>
                                <td><?= e(substr((string) $item['created_at'], 0, 10)) ?></td>
                            </tr><?php endforeach; ?>
                    </tbody>
                </table>
            </div><?php else: ?>
            <div class="empty-state">You have not applied yet. <a href="<?= e(url('jobs')) ?>">Browse internships</a>.</div>
        <?php endif; ?>
    </section>
    <?php
}

function render_student_applications(): void
{
    $student = require_role('student');
    $statement = db()->prepare('SELECT a.status, a.created_at, a.cover_letter, i.title, u.organization, i.location FROM applications a JOIN internships i ON i.id = a.internship_id JOIN users u ON u.id = i.employer_id WHERE a.student_id = ? ORDER BY a.created_at DESC');
    $statement->execute([(int) $student['id']]);
    $applications = $statement->fetchAll();
    ?>
    <section class="page-heading"><span class="eyebrow">STUDENT PORTAL</span>
        <h1>My applications</h1>
        <p>See the current status of each opportunity you applied for.</p>
    </section>
    <?php if ($applications): ?>
        <div class="job-list"><?php foreach ($applications as $item): ?>
                <article class="job-card">
                    <div class="section-heading">
                        <div><span class="pill"><?= e(ucfirst($item['status'])) ?></span>
                            <h2><?= e($item['title']) ?></h2>
                            <p class="muted"><?= e($item['organization']) ?> · <?= e($item['location']) ?></p>
                        </div><span class="muted"><?= e(substr((string) $item['created_at'], 0, 10)) ?></span>
                    </div><?php if ($item['cover_letter']): ?>
                        <p><?= nl2br(e($item['cover_letter'])) ?></p><?php endif; ?>
                </article><?php endforeach; ?>
        </div><?php else: ?>
        <div class="empty-state">No applications yet. <a href="<?= e(url('jobs')) ?>">Browse internships</a>.</div>
    <?php endif; ?>
<?php
}

function render_student_profile(): void
{
    $student = require_role('student');
    ?>
    <section class="page-heading"><span class="eyebrow">STUDENT PORTAL</span>
        <h1>My profile</h1>
        <p>Update the name shown to employers.</p>
    </section>
    <section class="form-card form-card-wide">
        <form method="post" action="<?= e(url('profile')) ?>"><?= csrf_field() ?><input type="hidden" name="action"
                value="student_profile"><label>Name<input name="name" value="<?= e($student['name']) ?>" maxlength="100"
                    required></label><label>Email<input type="email" value="<?= e($student['email']) ?>"
                    disabled></label><button class="button" type="submit">Save profile</button></form>
    </section>
    <?php
}
