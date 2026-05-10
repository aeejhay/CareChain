<?php
$pageTitle = 'Profile';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/review_helpers.php';

if (!isLoggedIn()) {
    redirect('/carechain/login.php');
}

$role = getUserRole();
$userId = (int) $_SESSION['user_id'];
$publicUserId = (int) ($_GET['user'] ?? 0);

// Public profile by user id (directories link here; no edit forms)
if ($publicUserId > 0) {
    if ($publicUserId === $userId) {
        redirect('/carechain/profile.php');
    }
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
    $stmt->execute([$publicUserId]);
    $pubUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pubUser || $pubUser['role'] === 'admin') {
        flash('error', 'Profile not found.');
        redirect('/carechain/profile.php');
    }
    $pubRole = $pubUser['role'];
    if ($pubRole === 'worker') {
        $stmt = $pdo->prepare('SELECT * FROM worker_profiles WHERE user_id = ?');
        $stmt->execute([$publicUserId]);
        $pubProfile = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare(
            'SELECT r.rating, r.comment, r.created_at, fp.facility_name AS reviewer_label
             FROM reviews r
             INNER JOIN facility_profiles fp ON fp.user_id = r.reviewer_id
             WHERE r.reviewee_id = ?
             ORDER BY r.created_at DESC LIMIT 12'
        );
        $stmt->execute([$publicUserId]);
        $pubReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM facility_profiles WHERE user_id = ?');
        $stmt->execute([$publicUserId]);
        $pubProfile = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare(
            'SELECT r.rating, r.comment, r.created_at,
                    TRIM(CONCAT(COALESCE(wp.first_name, \'\'), \' \', COALESCE(wp.last_name, \'\'))) AS reviewer_label
             FROM reviews r
             INNER JOIN worker_profiles wp ON wp.user_id = r.reviewer_id
             WHERE r.reviewee_id = ?
             ORDER BY r.created_at DESC LIMIT 12'
        );
        $stmt->execute([$publicUserId]);
        $pubReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (!$pubProfile) {
        flash('error', 'Profile not found.');
        redirect('/carechain/profile.php');
    }
    require_once __DIR__ . '/includes/header.php';
    ?>
<div class="container">
    <div class="page-header">
        <h1><?= $pubRole === 'worker' ? sanitize(($pubProfile['first_name'] ?? '') . ' ' . ($pubProfile['last_name'] ?? '')) : sanitize($pubProfile['facility_name'] ?? 'Facility') ?></h1>
        <a href="javascript:history.back()" class="btn btn-outline">Back</a>
    </div>

    <div class="card profile-rating-banner">
        <div class="profile-rating-row">
            <span class="profile-stars" aria-hidden="true"><?= carechain_stars_unicode((float) ($pubProfile['rating'] ?? 0)) ?></span>
            <strong><?= number_format((float) ($pubProfile['rating'] ?? 0), 1) ?></strong>
            <span class="text-muted">· <?= (int) ($pubProfile['total_reviews'] ?? 0) ?> review<?= ((int) ($pubProfile['total_reviews'] ?? 0)) === 1 ? '' : 's' ?></span>
            <?php if (!empty($pubProfile['is_verified'])): ?>
                <span class="badge badge-verified">Verified</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($pubRole === 'worker'): ?>
        <div class="card" style="margin-bottom: 1.5rem;">
            <p><strong><?= ucfirst(sanitize($pubProfile['job_title'] ?? '')) ?></strong>
                · <?= (int) ($pubProfile['years_experience'] ?? 0) ?> yrs experience
                <?php if (!empty($pubProfile['hourly_rate'])): ?>
                    · From &euro;<?= number_format((float) $pubProfile['hourly_rate'], 2) ?>/hr
                <?php endif; ?>
            </p>
            <?php if (!empty($pubProfile['bio'])): ?>
                <p style="color: var(--gray-700); line-height: 1.6;"><?= nl2br(sanitize($pubProfile['bio'])) ?></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card" style="margin-bottom: 1.5rem;">
            <p><strong><?= ucfirst(str_replace('_', ' ', sanitize($pubProfile['facility_type'] ?? ''))) ?></strong>
                · <?= sanitize($pubProfile['city'] ?? '') ?>, <?= sanitize($pubProfile['county'] ?? '') ?></p>
            <?php if (!empty($pubProfile['description'])): ?>
                <p style="color: var(--gray-700); line-height: 1.6;"><?= nl2br(sanitize($pubProfile['description'])) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="margin-bottom: 1rem;">Recent reviews</h2>
        <?php if (empty($pubReviews)): ?>
            <p style="color: var(--gray-500);">No reviews yet.</p>
        <?php else: ?>
            <ul class="profile-review-list">
                <?php foreach ($pubReviews as $rev): ?>
                    <li class="profile-review-item">
                        <div class="profile-review-meta">
                            <span class="profile-stars small" aria-hidden="true"><?= carechain_stars_unicode((float) $rev['rating']) ?></span>
                            <span class="text-muted"><?= sanitize($rev['reviewer_label'] ?? 'Member') ?></span>
                            <span class="text-muted">· <?= date('M j, Y', strtotime($rev['created_at'])) ?></span>
                        </div>
                        <?php if (!empty($rev['comment'])): ?>
                            <p class="profile-review-comment"><?= nl2br(sanitize($rev['comment'])) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Own profile: load user
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if ($role === 'worker') {
        $stmt = $pdo->prepare('UPDATE worker_profiles SET first_name = ?, last_name = ?, phone = ?, job_title = ?, years_experience = ?, bio = ?, availability = ?, hourly_rate = ? WHERE user_id = ?');
        $stmt->execute([
            sanitize($_POST['first_name']),
            sanitize($_POST['last_name']),
            sanitize($_POST['phone'] ?? ''),
            sanitize($_POST['job_title']),
            (int) ($_POST['years_experience'] ?? 0),
            sanitize($_POST['bio'] ?? ''),
            sanitize($_POST['availability'] ?? 'flexible'),
            (float) ($_POST['hourly_rate'] ?? 0),
            $userId,
        ]);
    } else {
        $stmt = $pdo->prepare('UPDATE facility_profiles SET facility_name = ?, facility_type = ?, address = ?, city = ?, county = ?, eircode = ?, phone = ?, contact_person = ?, description = ? WHERE user_id = ?');
        $stmt->execute([
            sanitize($_POST['facility_name']),
            sanitize($_POST['facility_type']),
            sanitize($_POST['address']),
            sanitize($_POST['city']),
            sanitize($_POST['county']),
            sanitize($_POST['eircode'] ?? ''),
            sanitize($_POST['phone'] ?? ''),
            sanitize($_POST['contact_person'] ?? ''),
            sanitize($_POST['description'] ?? ''),
            $userId,
        ]);
    }
    flash('success', 'Profile updated!');
    redirect('/carechain/profile.php');
}

// Handle document upload (worker only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc']) && $role === 'worker') {
    $docType = sanitize($_POST['doc_type'] ?? '');

    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

        if (in_array(strtolower($ext), $allowed)) {
            $filename = 'doc_' . $userId . '_' . time() . '.' . $ext;
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($_FILES['document']['tmp_name'], $filepath)) {
                $stmt = $pdo->prepare('INSERT INTO documents (user_id, doc_type, doc_name, file_path) VALUES (?, ?, ?, ?)');
                $stmt->execute([$userId, $docType, $_FILES['document']['name'], 'uploads/documents/' . $filename]);
                flash('success', 'Document uploaded! It will be reviewed by an admin.');
            } else {
                flash('error', 'Upload failed. Please try again.');
            }
        } else {
            flash('error', 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX');
        }
    } else {
        flash('error', 'Please select a file to upload.');
    }
    redirect('/carechain/profile.php');
}

$reviewsReceived = [];
if ($role === 'worker') {
    $stmt = $pdo->prepare('SELECT * FROM worker_profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare('SELECT * FROM documents WHERE user_id = ? ORDER BY uploaded_at DESC');
    $stmt->execute([$userId]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        'SELECT r.rating, r.comment, r.created_at, fp.facility_name AS reviewer_label
         FROM reviews r
         INNER JOIN facility_profiles fp ON fp.user_id = r.reviewer_id
         WHERE r.reviewee_id = ?
         ORDER BY r.created_at DESC LIMIT 12'
    );
    $stmt->execute([$userId]);
    $reviewsReceived = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($role === 'facility') {
    $stmt = $pdo->prepare('SELECT * FROM facility_profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
        'SELECT r.rating, r.comment, r.created_at,
                TRIM(CONCAT(COALESCE(wp.first_name, \'\'), \' \', COALESCE(wp.last_name, \'\'))) AS reviewer_label
         FROM reviews r
         INNER JOIN worker_profiles wp ON wp.user_id = r.reviewer_id
         WHERE r.reviewee_id = ?
         ORDER BY r.created_at DESC LIMIT 12'
    );
    $stmt->execute([$userId]);
    $reviewsReceived = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Your Profile</h1>
        <div>
            <?php if ($user['wallet_address']): ?>
                <span class="wallet-display"><?= substr(sanitize($user['wallet_address']), 0, 4) ?>...<?= substr(sanitize($user['wallet_address']), -4) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($role === 'worker' || $role === 'facility'): ?>
        <div class="card profile-rating-banner" style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1rem; margin-bottom: 0.5rem; color: var(--gray-500);"><?= $role === 'worker' ? 'Your rating' : 'Facility rating' ?></h2>
            <div class="profile-rating-row">
                <span class="profile-stars" aria-hidden="true"><?= carechain_stars_unicode((float) ($profile['rating'] ?? 0)) ?></span>
                <strong><?= number_format((float) ($profile['rating'] ?? 0), 1) ?></strong>
                <span class="text-muted">· <?= (int) ($profile['total_reviews'] ?? 0) ?> review<?= ((int) ($profile['total_reviews'] ?? 0)) === 1 ? '' : 's' ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($role === 'worker'): ?>
        <!-- WORKER PROFILE -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <h2 style="margin-bottom: 1rem;">Personal Information</h2>
            <form method="POST">
                <input type="hidden" name="update_profile" value="1">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" class="form-control" value="<?= sanitize($profile['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?= sanitize($profile['last_name'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" class="form-control" value="<?= sanitize($profile['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Job Title</label>
                        <select name="job_title" class="form-control">
                            <?php foreach (['hca' => 'Healthcare Assistant', 'nurse' => 'Nurse', 'carer' => 'Carer', 'midwife' => 'Midwife', 'physio' => 'Physiotherapist', 'other' => 'Other'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($profile['job_title'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Years of Experience</label>
                        <input type="number" name="years_experience" class="form-control" value="<?= (int) ($profile['years_experience'] ?? 0) ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>Preferred Hourly Rate (&euro;)</label>
                        <input type="number" name="hourly_rate" class="form-control" value="<?= number_format((float) ($profile['hourly_rate'] ?? 0), 2, '.', '') ?>" step="0.50" min="12">
                    </div>
                </div>
                <div class="form-group">
                    <label>Availability</label>
                    <select name="availability" class="form-control">
                        <option value="flexible" <?= ($profile['availability'] ?? '') === 'flexible' ? 'selected' : '' ?>>Flexible</option>
                        <option value="full_time" <?= ($profile['availability'] ?? '') === 'full_time' ? 'selected' : '' ?>>Full Time</option>
                        <option value="part_time" <?= ($profile['availability'] ?? '') === 'part_time' ? 'selected' : '' ?>>Part Time</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Bio</label>
                    <textarea name="bio" class="form-control" placeholder="Tell facilities about yourself, your experience, and what makes you a great care worker..."><?= sanitize($profile['bio'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>

        <div class="card" style="margin-bottom: 1.5rem;">
            <h2 style="margin-bottom: 1rem;">Reviews from facilities</h2>
            <?php if (empty($reviewsReceived)): ?>
                <p style="color: var(--gray-500);">When you complete shifts, facilities can leave you a rating here.</p>
            <?php else: ?>
                <ul class="profile-review-list">
                    <?php foreach ($reviewsReceived as $rev): ?>
                        <li class="profile-review-item">
                            <div class="profile-review-meta">
                                <span class="profile-stars small" aria-hidden="true"><?= carechain_stars_unicode((float) $rev['rating']) ?></span>
                                <span><?= sanitize($rev['reviewer_label'] ?? '') ?></span>
                                <span class="text-muted">· <?= date('M j, Y', strtotime($rev['created_at'])) ?></span>
                            </div>
                            <?php if (!empty($rev['comment'])): ?>
                                <p class="profile-review-comment"><?= nl2br(sanitize($rev['comment'])) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- CREDENTIALS / DOCUMENTS -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <h2 style="margin-bottom: 0.5rem;">Credentials &amp; Documents</h2>
            <p style="color: var(--gray-500); font-size: 0.9rem; margin-bottom: 1.5rem;">Upload your qualifications. Once verified by an admin, they'll be minted on Solana as soulbound tokens.</p>

            <?php if (!empty($documents)): ?>
                <?php foreach ($documents as $doc): ?>
                    <div class="credential-card" style="<?= $doc['status'] === 'pending' ? 'background: linear-gradient(135deg, var(--gray-700), var(--gray-500));' : '' ?>">
                        <div class="cred-type"><?= strtoupper(str_replace('_', ' ', $doc['doc_type'])) ?></div>
                        <div class="cred-name"><?= sanitize($doc['doc_name']) ?></div>
                        <div class="cred-status">
                            <?php if ($doc['status'] === 'approved'): ?>
                                &#x2713; Verified <?= $doc['verified_at'] ? date('M j, Y', strtotime($doc['verified_at'])) : '' ?>
                            <?php elseif ($doc['status'] === 'pending'): ?>
                                &#x23F3; Pending review
                            <?php else: ?>
                                &#x2717; Rejected
                            <?php endif; ?>
                        </div>
                        <?php if ($doc['nft_mint_address']): ?>
                            <div class="nft-badge" onclick="viewOnExplorer('<?= sanitize($doc['nft_mint_address']) ?>')" style="cursor: pointer;">
                                &#x26D3; On-chain credential — View
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--gray-100);">
                <h3 style="font-size: 1rem; margin-bottom: 1rem;">Upload New Document</h3>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="upload_doc" value="1">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Document Type</label>
                            <select name="doc_type" class="form-control" required>
                                <option value="nmbi_registration">NMBI Registration</option>
                                <option value="garda_vetting">Garda Vetting</option>
                                <option value="fetac_cert">FETAC/QQI Certificate</option>
                                <option value="manual_handling">Manual Handling</option>
                                <option value="patient_moving">Patient Moving & Handling</option>
                                <option value="first_aid">First Aid</option>
                                <option value="covid_cert">Covid Certification</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>File (PDF, JPG, PNG, DOC)</label>
                            <input type="file" name="document" class="form-control" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload Document</button>
                </form>
            </div>
        </div>

    <?php elseif ($role === 'facility'): ?>
        <!-- FACILITY PROFILE -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <h2 style="margin-bottom: 1rem;">Facility Information</h2>
            <form method="POST">
                <input type="hidden" name="update_profile" value="1">
                <div class="form-group">
                    <label>Facility Name</label>
                    <input type="text" name="facility_name" class="form-control" value="<?= sanitize($profile['facility_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Facility Type</label>
                    <select name="facility_type" class="form-control">
                        <?php foreach (['nursing_home' => 'Nursing Home', 'hospital' => 'Hospital', 'home_care' => 'Home Care', 'clinic' => 'Clinic', 'rehab' => 'Rehabilitation', 'other' => 'Other'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($profile['facility_type'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" class="form-control" value="<?= sanitize($profile['address'] ?? '') ?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" class="form-control" value="<?= sanitize($profile['city'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>County</label>
                        <input type="text" name="county" class="form-control" value="<?= sanitize($profile['county'] ?? '') ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Eircode</label>
                        <input type="text" name="eircode" class="form-control" value="<?= sanitize($profile['eircode'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" class="form-control" value="<?= sanitize($profile['phone'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" name="contact_person" class="form-control" value="<?= sanitize($profile['contact_person'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" placeholder="Tell workers about your facility..."><?= sanitize($profile['description'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-bottom: 1rem;">Reviews from workers</h2>
            <?php if (empty($reviewsReceived)): ?>
                <p style="color: var(--gray-500);">When workers complete shifts with you, they can rate your facility here.</p>
            <?php else: ?>
                <ul class="profile-review-list">
                    <?php foreach ($reviewsReceived as $rev): ?>
                        <li class="profile-review-item">
                            <div class="profile-review-meta">
                                <span class="profile-stars small" aria-hidden="true"><?= carechain_stars_unicode((float) $rev['rating']) ?></span>
                                <span><?= sanitize($rev['reviewer_label'] ?? 'Worker') ?></span>
                                <span class="text-muted">· <?= date('M j, Y', strtotime($rev['created_at'])) ?></span>
                            </div>
                            <?php if (!empty($rev['comment'])): ?>
                                <p class="profile-review-comment"><?= nl2br(sanitize($rev['comment'])) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
