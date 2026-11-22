<?php
/* MEMBER 4: Admin overview and internship moderation. */
declare(strict_types=1);

/* MEMBER 4 — Approve or reject internship listings. */
function handle_admin_action(string $action): void
{
    require_role('admin');

    if ($action === 'listing_status') {
        $internshipId = filter_input(INPUT_POST, 'internship_id', FILTER_VALIDATE_INT);
        $status = (string)($_POST['status'] ?? '');
        if (!$internshipId || !in_array($status, ['approved', 'rejected'], true)) {
            flash('error', 'Choose a valid listing decision.');
            redirect('admin');
        }
        $statement = db()->prepare("UPDATE internships SET status = ? WHERE id = ? AND status = 'pending'");
        $statement->execute([$status, $internshipId]);
        flash('success', 'Listing review saved.');
        redirect('admin');
    }

    flash('error', 'That admin action is not available.');
    redirect('admin');
}

/* MEMBER 4 — Show platform activity and pending listings. */
function render_admin_page(): void
{
    require_role('admin');
    $stats = db()->query("SELECT (SELECT COUNT(*) FROM users WHERE role = 'student') AS students, (SELECT COUNT(*) FROM users WHERE role = 'employer') AS employers, (SELECT COUNT(*) FROM internships WHERE status = 'pending') AS pending, (SELECT COUNT(*) FROM applications) AS applications")->fetch();
    $pending = db()->query("SELECT i.id, i.title, i.location, i.created_at, u.organization, u.name FROM internships i JOIN users u ON u.id = i.employer_id WHERE i.status = 'pending' ORDER BY i.created_at ASC")->fetchAll();
    ?>
    <section class="page-heading"><span class="eyebrow">CAREER OFFICE</span><h1>Platform overview</h1><p>Review new internship listings before students see them.</p></section>
    <div class="stat-grid"><article class="stat-card"><span>Students</span><strong><?= (int)$stats['students'] ?></strong></article><article class="stat-card"><span>Employers</span><strong><?= (int)$stats['employers'] ?></strong></article><article class="stat-card stat-accent"><span>Listings to review</span><strong><?= (int)$stats['pending'] ?></strong></article><article class="stat-card"><span>Applications</span><strong><?= (int)$stats['applications'] ?></strong></article></div>
    <section class="panel"><div class="section-heading"><div><span class="eyebrow">QUALITY CHECK</span><h2>Internships awaiting review</h2></div></div>
        <?php if ($pending): ?><div class="table-wrap"><table><thead><tr><th>Opportunity</th><th>Employer</th><th>Location</th><th>Action</th></tr></thead><tbody><?php foreach ($pending as $item): ?><tr><td><strong><?= e($item['title']) ?></strong><small><?= e(substr((string)$item['created_at'], 0, 10)) ?></small></td><td><?= e($item['organization'] ?: $item['name']) ?></td><td><?= e($item['location']) ?></td><td><form method="post" action="<?= e(url('admin')) ?>" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="admin_listing_status"><input type="hidden" name="internship_id" value="<?= (int)$item['id'] ?>"><button class="button button-small" name="status" value="approved" type="submit">Approve</button><button class="button button-small button-light" name="status" value="rejected" type="submit">Reject</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state">No internships are waiting for review.</div><?php endif; ?>
    </section>
    <?php
}
