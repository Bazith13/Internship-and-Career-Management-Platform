<?php
/* MEMBER 3: Employer internship listings and applicant review. */
declare(strict_types=1);

/* MEMBER 3 — Handle internship and applicant actions. */
function handle_employer_action(string $action): void
{
    $employer = require_role('employer');

    if ($action === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $workType = (string)($_POST['work_type'] ?? '');
        $deadline = trim((string)($_POST['deadline'] ?? ''));
        $date = $deadline === '' ? null : DateTimeImmutable::createFromFormat('!Y-m-d', $deadline);

        if (strlen($title) < 4 || strlen($title) > 150 || strlen($description) < 20 || strlen($description) > 5000 || strlen($location) < 2 || strlen($location) > 150) {
            flash('error', 'Check the title, description, and location, then try again.');
            redirect('my-jobs');
        }
        if (!in_array($workType, ['On-site', 'Hybrid', 'Remote'], true)) {
            flash('error', 'Choose a valid work type.');
            redirect('my-jobs');
        }
        if ($deadline !== '' && (!$date || $date->format('Y-m-d') !== $deadline || $date < new DateTimeImmutable('today'))) {
            flash('error', 'Choose a valid future application deadline.');
            redirect('my-jobs');
        }

        $statement = db()->prepare('INSERT INTO internships (employer_id, title, description, location, work_type, deadline) VALUES (?, ?, ?, ?, ?, ?)');
        $statement->execute([(int)$employer['id'], $title, $description, $location, $workType, $deadline === '' ? null : $deadline]);
        flash('success', 'Internship submitted. It will appear after admin review.');
        redirect('my-jobs');
    }

    if ($action === 'application_status') {
        $applicationId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
        $status = (string)($_POST['status'] ?? '');
        if (!$applicationId || !in_array($status, ['shortlisted', 'rejected'], true)) {
            flash('error', 'Choose a valid applicant status.');
            redirect('applicants');
        }

        $statement = db()->prepare("UPDATE applications a JOIN internships i ON i.id = a.internship_id SET a.status = ? WHERE a.id = ? AND i.employer_id = ?");
        $statement->execute([$status, $applicationId, (int)$employer['id']]);
        flash('success', 'Applicant status updated.');
        redirect('applicants');
    }

    flash('error', 'That employer action is not available.');
    redirect('employer');
}

/* MEMBER 3 — Show employer listings and applicants. */
function render_employer_dashboard(): void
{
    $employer = require_role('employer');
    $counts = db()->prepare("SELECT
        (SELECT COUNT(*) FROM internships WHERE employer_id = ?) AS listings,
        (SELECT COUNT(*) FROM internships WHERE employer_id = ? AND status = 'pending') AS pending,
        (SELECT COUNT(*) FROM applications a JOIN internships i ON i.id = a.internship_id WHERE i.employer_id = ?) AS applicants");
    $counts->execute([(int)$employer['id'], (int)$employer['id'], (int)$employer['id']]);
    $stats = $counts->fetch() ?: ['listings' => 0, 'pending' => 0, 'applicants' => 0];
    ?>
    <section class="page-heading"><span class="eyebrow">EMPLOYER PORTAL</span><h1>Welcome, <?= e($employer['organization'] ?: $employer['name']) ?></h1><p>Post opportunities and follow applications from one workspace.</p></section>
    <div class="stat-grid"><article class="stat-card"><span>Listings</span><strong><?= (int)$stats['listings'] ?></strong></article><article class="stat-card"><span>Awaiting review</span><strong><?= (int)($stats['pending'] ?? 0) ?></strong></article><article class="stat-card stat-accent"><span>Applicants</span><strong><?= (int)$stats['applicants'] ?></strong></article></div>
    <section class="cta-strip"><div><span class="eyebrow">READY TO HIRE?</span><h2>Post an internship for student review.</h2></div><a class="button button-light" href="<?= e(url('my-jobs')) ?>#new-listing">Create listing</a></section>
    <?php
}

function render_employer_jobs(): void
{
    $employer = require_role('employer');
    $statement = db()->prepare('SELECT i.*, (SELECT COUNT(*) FROM applications a WHERE a.internship_id = i.id) AS applicant_count FROM internships i WHERE i.employer_id = ? ORDER BY i.created_at DESC');
    $statement->execute([(int)$employer['id']]);
    $jobs = $statement->fetchAll();
    ?>
    <section class="page-heading"><span class="eyebrow">EMPLOYER PORTAL</span><h1>My listings</h1><p>New listings are sent to the career office for approval.</p></section>
    <section class="panel" id="new-listing"><div class="section-heading"><div><span class="eyebrow">CREATE OPPORTUNITY</span><h2>Post an internship</h2></div></div>
        <form method="post" action="<?= e(url('my-jobs')) ?>" class="form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="employer_create">
            <label>Internship title<input name="title" maxlength="150" required></label><label>Location<input name="location" maxlength="150" placeholder="City or remote" required></label>
            <label>Work type<select name="work_type" required><option>On-site</option><option>Hybrid</option><option>Remote</option></select></label><label>Application deadline<input type="date" name="deadline" min="<?= e(date('Y-m-d')) ?>"></label>
            <label class="full-width">Description<textarea name="description" rows="5" minlength="20" maxlength="5000" required></textarea></label><button class="button" type="submit">Submit for review</button>
        </form>
    </section>
    <section class="section-block"><div class="section-heading"><div><span class="eyebrow">YOUR OPPORTUNITIES</span><h2>Submitted listings</h2></div></div>
        <?php if ($jobs): ?><div class="job-list"><?php foreach ($jobs as $job): ?><article class="job-card"><div class="section-heading"><div><span class="pill"><?= e(ucfirst($job['status'])) ?></span><h3><?= e($job['title']) ?></h3><p class="muted"><?= e($job['location']) ?> · <?= (int)$job['applicant_count'] ?> applicants</p></div><a class="text-link" href="<?= e(url('applicants')) ?>">Review applicants</a></div></article><?php endforeach; ?></div><?php else: ?><div class="empty-state">You have not posted an internship yet.</div><?php endif; ?>
    </section>
    <?php
}

function render_employer_applicants(): void
{
    $employer = require_role('employer');
    $statement = db()->prepare('SELECT a.id, a.status, a.created_at, a.cover_letter, i.title, u.name AS student_name, u.email AS student_email FROM applications a JOIN internships i ON i.id = a.internship_id JOIN users u ON u.id = a.student_id WHERE i.employer_id = ? ORDER BY a.created_at DESC');
    $statement->execute([(int)$employer['id']]);
    $applicants = $statement->fetchAll();
    ?>
    <section class="page-heading"><span class="eyebrow">EMPLOYER PORTAL</span><h1>Applicants</h1><p>Review student applications and update their status.</p></section>
    <?php if ($applicants): ?><div class="job-list"><?php foreach ($applicants as $item): ?><article class="job-card"><div class="section-heading"><div><span class="pill"><?= e(ucfirst($item['status'])) ?></span><h2><?= e($item['student_name']) ?></h2><p class="muted"><?= e($item['title']) ?> · <?= e($item['student_email']) ?></p></div><span class="muted"><?= e(substr((string)$item['created_at'], 0, 10)) ?></span></div><?php if ($item['cover_letter']): ?><p><?= nl2br(e($item['cover_letter'])) ?></p><?php endif; ?>
            <form method="post" action="<?= e(url('applicants')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="employer_application_status"><input type="hidden" name="application_id" value="<?= (int)$item['id'] ?>"><button class="button button-small" name="status" value="shortlisted" type="submit">Shortlist</button><button class="button button-small button-light" name="status" value="rejected" type="submit">Reject</button></form>
        </article><?php endforeach; ?></div><?php else: ?><div class="empty-state">There are no applications for your listings yet.</div><?php endif; ?>
    <?php
}
