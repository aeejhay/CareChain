<?php
$pageTitle = 'Workers';
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('/carechain/login.php');
}

$role = getUserRole();
if (!in_array($role, ['facility', 'admin'], true)) {
    flash('error', 'This directory is for facilities and admins.');
    redirect('/carechain/dashboard.php');
}

require_once __DIR__ . '/includes/review_helpers.php';

$jobFilter = sanitize($_GET['job'] ?? '');
$verifiedOnly = isset($_GET['verified']) && $_GET['verified'] === '1';
$sort = $_GET['sort'] ?? 'rating';
if (!in_array($sort, ['rating', 'experience', 'rate'], true)) {
    $sort = 'rating';
}

$sql = 'SELECT wp.*, u.id AS profile_user_id
        FROM worker_profiles wp
        INNER JOIN users u ON u.id = wp.user_id AND u.role = \'worker\'
        WHERE 1=1';
$params = [];
if ($jobFilter !== '' && in_array($jobFilter, ['nurse', 'hca', 'carer', 'midwife', 'physio', 'other'], true)) {
    $sql .= ' AND wp.job_title = ?';
    $params[] = $jobFilter;
}
if ($verifiedOnly) {
    $sql .= ' AND wp.is_verified = 1';
}
if ($sort === 'rating') {
    $sql .= ' ORDER BY wp.rating DESC, COALESCE(wp.total_reviews, 0) DESC, wp.last_name ASC';
} elseif ($sort === 'experience') {
    $sql .= ' ORDER BY wp.years_experience DESC, wp.rating DESC';
} else {
    $sql .= ' ORDER BY wp.hourly_rate ASC, wp.rating DESC';
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Browse workers</h1>
        <a href="/carechain/dashboard.php" class="btn btn-outline">Dashboard</a>
    </div>

    <form method="get" class="card directory-filters" action="/carechain/workers.php">
        <div class="directory-filters-row">
            <div class="form-group" style="margin-bottom: 0;">
                <label>Job title</label>
                <select name="job" class="form-control">
                    <option value="">All</option>
                    <?php foreach (['hca' => 'HCA', 'nurse' => 'Nurse', 'carer' => 'Carer', 'midwife' => 'Midwife', 'physio' => 'Physio', 'other' => 'Other'] as $val => $lab): ?>
                        <option value="<?= $val ?>" <?= $jobFilter === $val ? 'selected' : '' ?>><?= $lab ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>Sort by</label>
                <select name="sort" class="form-control">
                    <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Rating</option>
                    <option value="experience" <?= $sort === 'experience' ? 'selected' : '' ?>>Experience</option>
                    <option value="rate" <?= $sort === 'rate' ? 'selected' : '' ?>>Hourly rate (low to high)</option>
                </select>
            </div>
            <label class="directory-checkbox">
                <input type="checkbox" name="verified" value="1" <?= $verifiedOnly ? 'checked' : '' ?>>
                Verified only
            </label>
            <button type="submit" class="btn btn-primary">Apply</button>
        </div>
    </form>

    <?php if (empty($workers)): ?>
        <div class="card" style="text-align: center; padding: 2rem;">
            <p style="color: var(--gray-500);">No workers match these filters.</p>
        </div>
    <?php else: ?>
        <?php foreach ($workers as $w): ?>
            <?php
            $bioShort = $w['bio'] ?? '';
            if (strlen($bioShort) > 180) {
                $bioShort = substr($bioShort, 0, 177) . '…';
            }
            ?>
            <div class="card directory-card" style="margin-bottom: 1rem;">
                <div class="directory-card-head">
                    <div>
                        <strong class="directory-card-title"><?= sanitize(trim(($w['first_name'] ?? '') . ' ' . ($w['last_name'] ?? ''))) ?></strong>
                        <span class="text-muted"> · <?= ucfirst(sanitize($w['job_title'] ?? '')) ?></span>
                        <?php if (!empty($w['is_verified'])): ?>
                            <span class="badge badge-verified">Verified</span>
                        <?php endif; ?>
                    </div>
                    <div class="directory-card-rating">
                        <span class="profile-stars small" aria-hidden="true"><?= carechain_stars_unicode((float) ($w['rating'] ?? 0)) ?></span>
                        <span><?= number_format((float) ($w['rating'] ?? 0), 1) ?></span>
                        <span class="text-muted">(<?= (int) ($w['total_reviews'] ?? 0) ?>)</span>
                    </div>
                </div>
                <p class="directory-meta">
                    <?= (int) ($w['years_experience'] ?? 0) ?> yrs experience
                    <?php if (!empty($w['hourly_rate'])): ?>
                        · From &euro;<?= number_format((float) $w['hourly_rate'], 2) ?>/hr
                    <?php endif; ?>
                </p>
                <?php if ($bioShort !== ''): ?>
                    <p class="directory-bio"><?= nl2br(sanitize($bioShort)) ?></p>
                <?php endif; ?>
                <a href="/carechain/profile.php?user=<?= (int) $w['profile_user_id'] ?>" class="btn btn-outline btn-sm">View profile</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
