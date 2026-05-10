<?php
$pageTitle = 'Facilities';
require_once __DIR__ . '/config/database.php';

if (!isLoggedIn()) {
    redirect('/carechain/login.php');
}

$role = getUserRole();
if (!in_array($role, ['worker', 'admin'], true)) {
    flash('error', 'This directory is for workers and admins.');
    redirect('/carechain/dashboard.php');
}

require_once __DIR__ . '/includes/review_helpers.php';

$typeFilter = sanitize($_GET['type'] ?? '');
$countyFilter = trim((string) ($_GET['county'] ?? ''));
$sort = $_GET['sort'] ?? 'rating';
if (!in_array($sort, ['rating', 'name'], true)) {
    $sort = 'rating';
}

$sql = 'SELECT fp.*, u.id AS profile_user_id
        FROM facility_profiles fp
        INNER JOIN users u ON u.id = fp.user_id AND u.role = \'facility\'
        WHERE 1=1';
$params = [];
if ($typeFilter !== '' && in_array($typeFilter, ['nursing_home', 'hospital', 'home_care', 'clinic', 'rehab', 'other'], true)) {
    $sql .= ' AND fp.facility_type = ?';
    $params[] = $typeFilter;
}
if ($countyFilter !== '') {
    $sql .= ' AND fp.county LIKE ?';
    $params[] = '%' . $countyFilter . '%';
}
if ($sort === 'rating') {
    $sql .= ' ORDER BY fp.rating DESC, COALESCE(fp.total_reviews, 0) DESC, fp.facility_name ASC';
} else {
    $sql .= ' ORDER BY fp.facility_name ASC';
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Browse facilities</h1>
        <a href="/carechain/dashboard.php" class="btn btn-outline">Dashboard</a>
    </div>

    <form method="get" class="card directory-filters" action="/carechain/facilities.php">
        <div class="directory-filters-row">
            <div class="form-group" style="margin-bottom: 0;">
                <label>Facility type</label>
                <select name="type" class="form-control">
                    <option value="">All</option>
                    <?php foreach (['nursing_home' => 'Nursing home', 'hospital' => 'Hospital', 'home_care' => 'Home care', 'clinic' => 'Clinic', 'rehab' => 'Rehab', 'other' => 'Other'] as $val => $lab): ?>
                        <option value="<?= $val ?>" <?= $typeFilter === $val ? 'selected' : '' ?>><?= $lab ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>County (contains)</label>
                <input type="text" name="county" class="form-control" value="<?= htmlspecialchars($countyFilter, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. Dublin">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label>Sort by</label>
                <select name="sort" class="form-control">
                    <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Rating</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name (A–Z)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Apply</button>
        </div>
    </form>

    <?php if (empty($facilities)): ?>
        <div class="card" style="text-align: center; padding: 2rem;">
            <p style="color: var(--gray-500);">No facilities match these filters.</p>
        </div>
    <?php else: ?>
        <?php foreach ($facilities as $f): ?>
            <?php
            $descShort = $f['description'] ?? '';
            if (strlen($descShort) > 200) {
                $descShort = substr($descShort, 0, 197) . '…';
            }
            ?>
            <div class="card directory-card" style="margin-bottom: 1rem;">
                <div class="directory-card-head">
                    <div>
                        <strong class="directory-card-title"><?= sanitize($f['facility_name'] ?? '') ?></strong>
                        <span class="text-muted"> · <?= ucfirst(str_replace('_', ' ', sanitize($f['facility_type'] ?? ''))) ?></span>
                        <?php if (!empty($f['is_verified'])): ?>
                            <span class="badge badge-verified">Verified</span>
                        <?php endif; ?>
                    </div>
                    <div class="directory-card-rating">
                        <span class="profile-stars small" aria-hidden="true"><?= carechain_stars_unicode((float) ($f['rating'] ?? 0)) ?></span>
                        <span><?= number_format((float) ($f['rating'] ?? 0), 1) ?></span>
                        <span class="text-muted">(<?= (int) ($f['total_reviews'] ?? 0) ?>)</span>
                    </div>
                </div>
                <p class="directory-meta"><?= sanitize($f['city'] ?? '') ?>, <?= sanitize($f['county'] ?? '') ?></p>
                <?php if ($descShort !== ''): ?>
                    <p class="directory-bio"><?= nl2br(sanitize($descShort)) ?></p>
                <?php endif; ?>
                <a href="/carechain/profile.php?user=<?= (int) $f['profile_user_id'] ?>" class="btn btn-outline btn-sm">View profile</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
