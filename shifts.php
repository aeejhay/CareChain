<?php
$pageTitle = 'Shifts';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/review_helpers.php';

if (!isLoggedIn()) {
    redirect('/carechain/login.php');
}

$role = getUserRole();
$userId = (int) $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$shiftId = (int) ($_GET['id'] ?? 0);

// Handle shift creation (facility only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'facility' && $action === 'create') {
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $shiftDate = $_POST['shift_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? '';
    $hourlyRate = (float)($_POST['hourly_rate'] ?? 0);
    $requiredRole = sanitize($_POST['required_role'] ?? 'any');
    $urgency = sanitize($_POST['urgency'] ?? 'normal');
    
    // Calculate total pay from hours
    $start = strtotime($shiftDate . ' ' . $startTime);
    $end = strtotime($shiftDate . ' ' . $endTime);
    if ($end <= $start) $end += 86400; // Overnight shift
    $hours = ($end - $start) / 3600;
    $totalPay = $hourlyRate * $hours;
    
    $stmt = $pdo->prepare("INSERT INTO shifts (facility_id, title, description, shift_date, start_time, end_time, hourly_rate, total_pay, required_role, urgency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $title, $description, $shiftDate, $startTime, $endTime, $hourlyRate, $totalPay, $requiredRole, $urgency]);
    
    flash('success', 'Shift posted successfully! Workers can now apply.');
    redirect('/carechain/shifts.php');
}

// Handle shift application (worker only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'worker' && isset($_POST['apply_shift'])) {
    $applyShiftId = (int)$_POST['apply_shift'];
    
    // Check not already applied
    $stmt = $pdo->prepare("SELECT id FROM shift_applications WHERE shift_id = ? AND worker_id = ?");
    $stmt->execute([$applyShiftId, $userId]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO shift_applications (shift_id, worker_id) VALUES (?, ?)");
        $stmt->execute([$applyShiftId, $userId]);
        flash('success', 'Application submitted!');
    } else {
        flash('error', 'You already applied for this shift.');
    }
    redirect('/carechain/shifts.php');
}

// Handle accept/complete application (facility only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'facility') {
    if (isset($_POST['accept_app'])) {
        $appId = (int)$_POST['accept_app'];
        $stmt = $pdo->prepare("
            UPDATE shift_applications SET status = 'accepted', accepted_at = NOW() WHERE id = ? 
            AND shift_id IN (SELECT id FROM shifts WHERE facility_id = ?)
        ");
        $stmt->execute([$appId, $userId]);
        
        // Update shift status
        $stmt = $pdo->prepare("
            UPDATE shifts SET status = 'claimed' WHERE id = (SELECT shift_id FROM shift_applications WHERE id = ?)
        ");
        $stmt->execute([$appId]);
        
        flash('success', 'Worker accepted! They have been notified.');
        redirect('/carechain/shifts.php?id=' . ($_POST['shift_id'] ?? ''));
    }
    
    if (isset($_POST['complete_shift'])) {
        $completeShiftId = (int)$_POST['complete_shift'];
        $stmt = $pdo->prepare("UPDATE shifts SET status = 'completed' WHERE id = ? AND facility_id = ?");
        $stmt->execute([$completeShiftId, $userId]);
        $stmt = $pdo->prepare("UPDATE shift_applications SET status = 'completed', completed_at = NOW() WHERE shift_id = ? AND status = 'accepted'");
        $stmt->execute([$completeShiftId]);
        
        flash('success', 'Shift marked complete! Payment can now be released.');
        redirect('/carechain/shifts.php?id=' . $completeShiftId);
    }
}

// Handle 360° review after shift completed (validated server-side; reviewee derived from DB)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_shift_review'])) {
    $reviewShiftId = (int) ($_POST['shift_id'] ?? 0);
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if (strlen($comment) > 2000) {
        flash('error', 'Comment is too long (max 2000 characters).');
        redirect('/carechain/shifts.php?id=' . $reviewShiftId);
    }
    if ($rating < 1 || $rating > 5) {
        flash('error', 'Please choose a rating from 1 to 5 stars.');
        redirect('/carechain/shifts.php?id=' . $reviewShiftId);
    }

    $stmt = $pdo->prepare('SELECT id, facility_id, status FROM shifts WHERE id = ?');
    $stmt->execute([$reviewShiftId]);
    $shiftRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$shiftRow || $shiftRow['status'] !== 'completed') {
        flash('error', 'Reviews are only allowed after the shift is completed.');
        redirect('/carechain/shifts.php?id=' . $reviewShiftId);
    }

    $revieweeId = null;
    if ($role === 'facility' && (int) $shiftRow['facility_id'] === $userId) {
        $targetWorker = (int) ($_POST['review_worker_id'] ?? 0);
        $chk = $pdo->prepare(
            'SELECT 1 FROM shift_applications WHERE shift_id = ? AND worker_id = ? AND status = ?'
        );
        $chk->execute([$reviewShiftId, $targetWorker, 'completed']);
        if (!$chk->fetch()) {
            flash('error', 'You can only review a worker who completed this shift with you.');
            redirect('/carechain/shifts.php?id=' . $reviewShiftId);
        }
        $revieweeId = $targetWorker;
    } elseif ($role === 'worker') {
        $chk = $pdo->prepare(
            'SELECT 1 FROM shift_applications WHERE shift_id = ? AND worker_id = ? AND status = ?'
        );
        $chk->execute([$reviewShiftId, $userId, 'completed']);
        if (!$chk->fetch()) {
            flash('error', 'You can only review a facility for shifts you completed.');
            redirect('/carechain/shifts.php?id=' . $reviewShiftId);
        }
        $revieweeId = (int) $shiftRow['facility_id'];
    } else {
        flash('error', 'You cannot submit this review.');
        redirect('/carechain/shifts.php?id=' . $reviewShiftId);
    }

    if ($revieweeId === $userId) {
        flash('error', 'Invalid review.');
        redirect('/carechain/shifts.php?id=' . $reviewShiftId);
    }

    $dup = $pdo->prepare('SELECT id FROM reviews WHERE shift_id = ? AND reviewer_id = ? AND reviewee_id = ?');
    $dup->execute([$reviewShiftId, $userId, $revieweeId]);
    if ($dup->fetch()) {
        flash('error', 'You have already submitted a review for this person on this shift.');
        redirect('/carechain/shifts.php?id=' . $reviewShiftId);
    }

    try {
        $ins = $pdo->prepare(
            'INSERT INTO reviews (shift_id, reviewer_id, reviewee_id, rating, comment) VALUES (?, ?, ?, ?, ?)'
        );
        $ins->execute([$reviewShiftId, $userId, $revieweeId, $rating, $comment === '' ? null : $comment]);
        carechain_refresh_ratings($pdo, $revieweeId);
        flash('success', 'Thank you — your review has been saved.');
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) {
            flash('error', 'You have already submitted a review for this person on this shift.');
        } else {
            flash('error', 'Could not save review. Please try again.');
        }
    }
    redirect('/carechain/shifts.php?id=' . $reviewShiftId);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <?php if ($action === 'create' && $role === 'facility'): ?>
        <!-- CREATE SHIFT FORM -->
        <div class="page-header">
            <h1>Post a New Shift</h1>
            <a href="/carechain/shifts.php" class="btn btn-outline">Back to Shifts</a>
        </div>
        
        <div class="card" style="max-width: 640px;">
            <form method="POST">
                <div class="form-group">
                    <label>Shift Title</label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Night Shift — Nursing Care">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" placeholder="Describe the role, responsibilities, and any special requirements..."></textarea>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="shift_date" class="form-control" required min="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Hourly Rate (&euro;)</label>
                        <input type="number" name="hourly_rate" class="form-control" required step="0.50" min="12" placeholder="18.00">
                    </div>
                    <div class="form-group">
                        <label>Required Role</label>
                        <select name="required_role" class="form-control">
                            <option value="any">Any Qualified Worker</option>
                            <option value="nurse">Nurse</option>
                            <option value="hca">Healthcare Assistant</option>
                            <option value="carer">Carer</option>
                            <option value="midwife">Midwife</option>
                            <option value="physio">Physiotherapist</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Urgency</label>
                    <select name="urgency" class="form-control">
                        <option value="normal">Normal</option>
                        <option value="urgent">Urgent — Need someone soon</option>
                        <option value="critical">Critical — Immediate need</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Post Shift</button>
            </form>
        </div>
        
    <?php elseif ($shiftId > 0): ?>
        <!-- SHIFT DETAIL VIEW -->
        <?php
        $stmt = $pdo->prepare("
            SELECT s.*, fp.facility_name, fp.city, fp.county, fp.facility_type 
            FROM shifts s 
            JOIN facility_profiles fp ON s.facility_id = fp.user_id 
            WHERE s.id = ?
        ");
        $stmt->execute([$shiftId]);
        $shift = $stmt->fetch();
        
        if (!$shift) { flash('error', 'Shift not found.'); redirect('/carechain/shifts.php'); }

        $escStatus = $shift['escrow_status'] ?? 'not_funded';
        $escFunded = in_array($escStatus, ['funded', 'released'], true);
        $escReleased = ($escStatus === 'released') || !empty($shift['payment_tx_signature']);
        $canFundEscrow = $role === 'facility' && (int) $shift['facility_id'] === $userId && $shift['status'] === 'open'
            && in_array($escStatus, ['not_funded', 'failed'], true);
        
        // Get applications
        $stmt = $pdo->prepare("
            SELECT sa.*, wp.first_name, wp.last_name, wp.job_title, wp.is_verified, u.wallet_address
            FROM shift_applications sa
            JOIN worker_profiles wp ON sa.worker_id = wp.user_id
            JOIN users u ON sa.worker_id = u.id
            WHERE sa.shift_id = ?
            ORDER BY sa.applied_at DESC
        ");
        $stmt->execute([$shiftId]);
        $applications = $stmt->fetchAll();

        // Review eligibility (360°): only after shift completed, one review per reviewer per shift
        $workerShiftCompletedApp = false;
        $workerReviewedFacility = false;
        if ($role === 'worker' && $shift['status'] === 'completed') {
            $wa = $pdo->prepare(
                "SELECT 1 FROM shift_applications WHERE shift_id = ? AND worker_id = ? AND status = 'completed'"
            );
            $wa->execute([$shiftId, $userId]);
            $workerShiftCompletedApp = (bool) $wa->fetch();
            if ($workerShiftCompletedApp) {
                $wr = $pdo->prepare(
                    'SELECT id FROM reviews WHERE shift_id = ? AND reviewer_id = ? AND reviewee_id = ?'
                );
                $wr->execute([$shiftId, $userId, (int) $shift['facility_id']]);
                $workerReviewedFacility = (bool) $wr->fetch();
            }
        }
        ?>
        
        <div class="page-header">
            <h1><?= sanitize($shift['title']) ?></h1>
            <a href="/carechain/shifts.php" class="btn btn-outline">Back</a>
        </div>
        
        <div class="card" style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                <div>
                    <div style="font-size: 1rem; color: var(--gray-500); margin-bottom: 0.3rem;"><?= sanitize($shift['facility_name']) ?> &mdash; <?= sanitize($shift['city']) ?>, <?= sanitize($shift['county']) ?></div>
                    <span class="badge badge-<?= $shift['urgency'] === 'normal' ? 'open' : $shift['urgency'] ?>"><?= ucfirst($shift['urgency']) ?></span>
                    <span class="badge badge-<?= str_replace('_', '-', $shift['status']) ?>"><?= ucfirst(str_replace('_', ' ', $shift['status'])) ?></span>
                    <?php
                    $escBadgeClass = 'badge-escrow-not-funded';
                    $escLabel = 'Escrow: not funded';
                    if ($escReleased) {
                        $escBadgeClass = 'badge-escrow-released';
                        $escLabel = 'Payment released';
                    } elseif ($escFunded) {
                        $escBadgeClass = 'badge-escrow-funded';
                        $escLabel = 'Escrow funded';
                    } elseif ($escStatus === 'failed') {
                        $escBadgeClass = 'badge-escrow-failed';
                        $escLabel = 'Escrow failed';
                    }
                    ?>
                    <span class="badge <?= $escBadgeClass ?>"><?= htmlspecialchars($escLabel, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="shift-pay" style="font-size: 1.5rem;">&euro;<?= number_format($shift['total_pay'], 2) ?></div>
            </div>
            <div class="shift-meta" style="margin-bottom: 1rem;">
                <span>&#x1F4C5; <?= date('l, M j Y', strtotime($shift['shift_date'])) ?></span>
                <span>&#x1F552; <?= date('H:i', strtotime($shift['start_time'])) ?> — <?= date('H:i', strtotime($shift['end_time'])) ?></span>
                <span>&#x1F4B6; &euro;<?= number_format($shift['hourly_rate'], 2) ?>/hr</span>
                <span>Role: <?= ucfirst(str_replace('_', ' ', $shift['required_role'])) ?></span>
            </div>
            <?php if ($shift['description']): ?>
                <p style="color: var(--gray-700); line-height: 1.6;"><?= nl2br(sanitize($shift['description'])) ?></p>
            <?php endif; ?>

            <div class="card escrow-prototype-callout" style="margin-top: 1rem; padding: 1rem 1.25rem;">
                <strong style="color: var(--teal-600);">Prototype escrow (Solana devnet)</strong>
                <p style="margin: 0.5rem 0 0; font-size: 0.88rem; color: var(--gray-700); line-height: 1.5;">
                    <strong>Fund Escrow</strong> sends SOL from your Phantom wallet to the CareChain <em>Prototype Escrow Vault</em> (demo only).
                    <strong>Release Payment</strong> (after the shift is marked complete) sends SOL from your facility wallet directly to the worker&rsquo;s saved wallet — simulating payout. Production would use an on-chain program / PDA vault.
                </p>
            </div>

            <?php if ($escFunded && !empty($shift['escrow_tx_signature'])): ?>
                <div class="escrow-status-panel escrow-status-funded">
                    <span>&#x26D3; Escrow funded on Solana devnet<?php if (!empty($shift['escrow_amount_sol'])): ?> (~<?= htmlspecialchars(number_format((float) $shift['escrow_amount_sol'], 4), ENT_QUOTES, 'UTF-8') ?> SOL)<?php endif; ?></span>
                    <button type="button" onclick="viewOnExplorer('<?= htmlspecialchars($shift['escrow_tx_signature'], ENT_QUOTES, 'UTF-8') ?>')" class="btn btn-sm btn-outline">View transaction on Solana Explorer</button>
                </div>
            <?php elseif (!$escFunded): ?>
                <div class="escrow-status-panel escrow-status-pending">
                    <span><?= $escStatus === 'failed' ? 'Last escrow attempt failed — you can try funding again.' : 'Escrow not funded yet — facility funds devnet escrow before workers rely on this shift.' ?></span>
                </div>
            <?php endif; ?>

            <?php if ($escReleased && !empty($shift['payment_tx_signature'])): ?>
                <div class="escrow-status-panel escrow-status-released">
                    <span>&#x26A1; Payment released to worker wallet</span>
                    <button type="button" onclick="viewOnExplorer('<?= htmlspecialchars($shift['payment_tx_signature'], ENT_QUOTES, 'UTF-8') ?>')" class="btn btn-sm btn-outline">View payment on Solana Explorer</button>
                </div>
            <?php endif; ?>
            
            <?php if ($role === 'worker' && $shift['status'] === 'open'): ?>
                <?php if ($escFunded): ?>
                    <form method="POST" style="margin-top: 1rem;">
                        <input type="hidden" name="apply_shift" value="<?= (int) $shift['id'] ?>">
                        <button type="submit" class="btn btn-primary">Apply for This Shift</button>
                    </form>
                <?php else: ?>
                    <p style="margin-top: 1rem; padding: 0.85rem; background: var(--amber-50); border-radius: var(--radius-sm); color: var(--amber-600); font-size: 0.9rem;">
                        Escrow is not funded yet — the facility must fund escrow on devnet before you can apply. This helps you know the shift is backed for demo purposes.
                    </p>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if ($canFundEscrow): ?>
                <div style="margin-top: 1rem;">
                    <button type="button" onclick="fundEscrow(<?= (int) $shift['id'] ?>, 0.01)" class="btn btn-primary">&#x26D3; Fund Escrow</button>
                    <span style="font-size: 0.82rem; color: var(--gray-500); margin-left: 0.5rem;">Sends 0.01 SOL to Prototype Escrow Vault (devnet)</span>
                </div>
            <?php elseif ($role === 'facility' && (int) $shift['facility_id'] === $userId && $shift['status'] === 'open' && $escFunded): ?>
                <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--teal-600); font-weight: 600;">Escrow funded — workers can now apply.</p>
            <?php endif; ?>
            
            <?php if ($role === 'facility' && $shift['facility_id'] == $userId && $shift['status'] === 'claimed'): ?>
                <form method="POST" style="margin-top: 1rem;">
                    <input type="hidden" name="complete_shift" value="<?= $shift['id'] ?>">
                    <button type="submit" class="btn btn-primary">&#x2713; Mark Shift Complete</button>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if ($role === 'facility' && $shift['facility_id'] == $userId && !empty($applications)): ?>
            <h2 style="margin-bottom: 1rem;">Applications (<?= count($applications) ?>)</h2>
            <?php foreach ($applications as $app): ?>
                <div class="card" style="margin-bottom: 0.75rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                        <div>
                            <strong><?= sanitize($app['first_name'] . ' ' . $app['last_name']) ?></strong>
                            <span style="color: var(--gray-500); margin-left: 0.5rem;"><?= ucfirst($app['job_title']) ?></span>
                            <?= $app['is_verified'] ? '<span class="badge badge-verified" style="margin-left: 0.5rem;">Verified</span>' : '' ?>
                            <div style="font-size: 0.82rem; color: var(--gray-500);">Applied <?= date('M j, H:i', strtotime($app['applied_at'])) ?></div>
                        </div>
                        <div style="display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
                            <?php if ($app['status'] === 'pending'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="accept_app" value="<?= $app['id'] ?>">
                                    <input type="hidden" name="shift_id" value="<?= $shiftId ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">Accept</button>
                                </form>
                            <?php elseif ($app['status'] === 'accepted'): ?>
                                <span class="badge badge-claimed">Accepted</span>
                            <?php elseif ($app['status'] === 'completed'): ?>
                                <span class="badge badge-completed">Completed</span>
                                <?php
                                $wAddr = trim((string) ($app['wallet_address'] ?? ''));
                                $canReleasePay = $shift['status'] === 'completed'
                                    && $escStatus === 'funded'
                                    && !$escReleased
                                    && $wAddr !== '';
                                ?>
                                <?php if ($canReleasePay): ?>
                                    <button type="button"
                                        class="btn btn-coral btn-sm"
                                        onclick="releasePayment(<?= (int) $shiftId ?>, <?= json_encode($wAddr) ?>, 0.01, <?= (int) $app['worker_id'] ?>)"
                                    >&#x26A1; Release Payment</button>
                                <?php elseif ($escReleased || !empty($app['payment_tx_signature']) || !empty($shift['payment_tx_signature'])): ?>
                                    <?php
                                    $paySigDisp = !empty($app['payment_tx_signature'])
                                        ? $app['payment_tx_signature']
                                        : ($shift['payment_tx_signature'] ?? '');
                                    ?>
                                    <?php if ($paySigDisp !== ''): ?>
                                        <button type="button" onclick="viewOnExplorer('<?= htmlspecialchars($paySigDisp, ENT_QUOTES, 'UTF-8') ?>')" class="btn btn-outline btn-sm">&#x2713; Payment released</button>
                                    <?php else: ?>
                                        <span class="badge badge-escrow-released">Payment released</span>
                                    <?php endif; ?>
                                <?php elseif ($shift['status'] === 'completed' && !$escFunded): ?>
                                    <span class="text-muted" style="font-size: 0.82rem;">Fund escrow before releasing payment</span>
                                <?php elseif ($shift['status'] === 'completed' && $escFunded && $wAddr === ''): ?>
                                    <span class="text-muted" style="font-size: 0.82rem;">Worker wallet address missing</span>
                                <?php endif; ?>
                                <?php
                                if ($shift['status'] === 'completed') {
                                    $fr = $pdo->prepare(
                                        'SELECT id FROM reviews WHERE shift_id = ? AND reviewer_id = ? AND reviewee_id = ?'
                                    );
                                    $fr->execute([$shiftId, $userId, (int) $app['worker_id']]);
                                    $facilityReviewedWorker = (bool) $fr->fetch();
                                } else {
                                    $facilityReviewedWorker = true;
                                }
                                ?>
                                <?php if ($shift['status'] === 'completed'): ?>
                                    <?php if ($facilityReviewedWorker): ?>
                                        <span class="review-submitted-label">Review submitted</span>
                                    <?php else: ?>
                                        <details class="review-inline-details">
                                            <summary class="btn btn-outline btn-sm">Review Worker</summary>
                                            <form method="post" class="review-inline-form">
                                                <input type="hidden" name="submit_shift_review" value="1">
                                                <input type="hidden" name="shift_id" value="<?= (int) $shift['id'] ?>">
                                                <input type="hidden" name="review_worker_id" value="<?= (int) $app['worker_id'] ?>">
                                                <div class="form-group">
                                                    <label>Rating (1–5)</label>
                                                    <select name="rating" class="form-control" required>
                                                        <?php for ($st = 5; $st >= 1; $st--): ?>
                                                            <option value="<?= $st ?>"><?= $st ?> star<?= $st === 1 ? '' : 's' ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div class="form-group">
                                                    <label>Comment (optional)</label>
                                                    <textarea name="comment" class="form-control" rows="3" maxlength="2000" placeholder="Share feedback for other facilities…"></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm">Submit review</button>
                                            </form>
                                        </details>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($role === 'worker' && $shift['status'] === 'completed' && $workerShiftCompletedApp): ?>
            <div class="card review-shift-card">
                <h2 style="margin-bottom: 0.75rem;">Rate this facility</h2>
                <?php if ($workerReviewedFacility): ?>
                    <p style="color: var(--gray-500);">Review submitted.</p>
                <?php else: ?>
                    <form method="post" class="review-form-block">
                        <input type="hidden" name="submit_shift_review" value="1">
                        <input type="hidden" name="shift_id" value="<?= (int) $shift['id'] ?>">
                        <div class="form-group">
                            <label>Star rating</label>
                            <select name="rating" class="form-control" style="max-width: 220px;" required>
                                <?php for ($st = 5; $st >= 1; $st--): ?>
                                    <option value="<?= $st ?>"><?= $st ?> star<?= $st === 1 ? '' : 's' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Comment (optional)</label>
                            <textarea name="comment" class="form-control" rows="4" maxlength="2000" placeholder="How was your experience at this facility?"></textarea>
                        </div>
                        <button type="submit" class="btn btn-coral">Review Facility</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    <?php else: ?>
        <!-- SHIFT LIST -->
        <div class="page-header">
            <h1><?= $role === 'facility' ? 'Your Shifts' : 'Available Shifts' ?></h1>
            <div class="page-header-actions">
                <a href="/carechain/map.php" class="btn btn-outline">Map view</a>
                <?php if ($role === 'facility'): ?>
                    <a href="/carechain/shifts.php?action=create" class="btn btn-primary">Post a Shift</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php
        if ($role === 'facility') {
            $stmt = $pdo->prepare("SELECT s.* FROM shifts s WHERE s.facility_id = ? ORDER BY s.shift_date DESC");
            $stmt->execute([$userId]);
            $shifts = $stmt->fetchAll();
            $workerMyShifts = [];
        } else {
            $stmt = $pdo->prepare("
                SELECT s.*, fp.facility_name, fp.city, fp.county 
                FROM shifts s 
                JOIN facility_profiles fp ON s.facility_id = fp.user_id 
                WHERE s.status = 'open' AND s.shift_date >= CURDATE()
                  AND (
                    s.escrow_status IN ('funded', 'released')
                    OR (s.escrow_tx_signature IS NOT NULL AND TRIM(s.escrow_tx_signature) != '')
                  )
                ORDER BY s.urgency = 'critical' DESC, s.urgency = 'urgent' DESC, s.shift_date ASC
            ");
            $stmt->execute();
            $shifts = $stmt->fetchAll();

            $stmt = $pdo->prepare("
                SELECT s.*, fp.facility_name, fp.city, fp.county, sa.status AS app_status
                FROM shift_applications sa
                JOIN shifts s ON sa.shift_id = s.id
                JOIN facility_profiles fp ON s.facility_id = fp.user_id
                WHERE sa.worker_id = ?
                ORDER BY s.shift_date DESC, s.id DESC
                LIMIT 40
            ");
            $stmt->execute([$userId]);
            $workerMyShifts = $stmt->fetchAll();
        }
        ?>
        
        <?php if (empty($shifts)): ?>
            <div class="card" style="text-align: center; padding: 3rem;">
                <p style="color: var(--gray-500);">
                    <?= $role === 'facility' ? 'No shifts posted yet.' : 'No open shifts with devnet escrow funded right now. Facilities fund escrow before shifts appear here — check back soon!' ?>
                </p>
            </div>
        <?php endif; ?>
        
        <?php foreach ($shifts as $shift): ?>
            <div class="shift-card">
                <div class="shift-header">
                    <div>
                        <div class="shift-title"><?= sanitize($shift['title']) ?></div>
                        <?php if (isset($shift['facility_name'])): ?>
                            <div style="color: var(--gray-500); font-size: 0.88rem;"><?= sanitize($shift['facility_name']) ?> &mdash; <?= sanitize($shift['city']) ?>, <?= sanitize($shift['county']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="shift-pay">&euro;<?= number_format($shift['total_pay'], 2) ?></div>
                </div>
                <div class="shift-meta">
                    <span>&#x1F4C5; <?= date('D, M j', strtotime($shift['shift_date'])) ?></span>
                    <span>&#x1F552; <?= date('H:i', strtotime($shift['start_time'])) ?> - <?= date('H:i', strtotime($shift['end_time'])) ?></span>
                    <span>&#x1F4B6; &euro;<?= number_format($shift['hourly_rate'], 2) ?>/hr</span>
                    <span class="badge badge-<?= $shift['urgency'] === 'normal' ? 'open' : $shift['urgency'] ?>"><?= ucfirst($shift['urgency']) ?></span>
                    <?php
                    $sEsc = $shift['escrow_status'] ?? 'not_funded';
                    $sFunded = in_array($sEsc, ['funded', 'released'], true)
                        || (!empty($shift['escrow_tx_signature']) && trim((string) $shift['escrow_tx_signature']) !== '');
                    $sReleased = ($sEsc === 'released') || !empty($shift['payment_tx_signature']);
                    ?>
                    <?php if ($sReleased): ?>
                        <span class="badge badge-escrow-released">Payment released</span>
                    <?php elseif ($sFunded): ?>
                        <span class="badge badge-escrow-funded">Escrow funded</span>
                    <?php else: ?>
                        <span class="badge badge-escrow-not-funded">Escrow not funded</span>
                    <?php endif; ?>
                </div>
                <div class="shift-footer">
                    <span style="font-size: 0.85rem; color: var(--gray-500);">
                        <?= ucfirst(str_replace('_', ' ', $shift['required_role'])) ?>
                    </span>
                    <a href="/carechain/shifts.php?id=<?= $shift['id'] ?>" class="btn btn-outline btn-sm">View Details</a>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($role === 'worker' && !empty($workerMyShifts)): ?>
            <h2 style="margin: 2rem 0 1rem;">My shifts &amp; applications</h2>
            <p style="color: var(--gray-500); font-size: 0.9rem; margin-bottom: 1rem;">
                Open a completed shift here to leave a facility review.
            </p>
            <?php foreach ($workerMyShifts as $ms): ?>
                <div class="shift-card shift-card-my">
                    <div class="shift-header">
                        <div>
                            <div class="shift-title"><?= sanitize($ms['title']) ?></div>
                            <div style="color: var(--gray-500); font-size: 0.88rem;"><?= sanitize($ms['facility_name']) ?> &mdash; <?= sanitize($ms['city']) ?>, <?= sanitize($ms['county']) ?></div>
                        </div>
                        <div class="shift-pay">&euro;<?= number_format($ms['total_pay'], 2) ?></div>
                    </div>
                    <div class="shift-meta">
                        <span>&#x1F4C5; <?= date('D, M j', strtotime($ms['shift_date'])) ?></span>
                        <span class="badge badge-<?= str_replace('_', '-', sanitize($ms['app_status'])) ?>"><?= ucfirst($ms['app_status']) ?></span>
                        <span class="badge badge-<?= str_replace('_', '-', sanitize($ms['status'])) ?>"><?= ucfirst(str_replace('_', ' ', $ms['status'])) ?></span>
                        <?php
                        $mEsc = $ms['escrow_status'] ?? 'not_funded';
                        $mRel = ($mEsc === 'released') || !empty($ms['payment_tx_signature']);
                        $mFund = in_array($mEsc, ['funded', 'released'], true)
                            || (!empty($ms['escrow_tx_signature']) && trim((string) $ms['escrow_tx_signature']) !== '');
                        ?>
                        <?php if ($mRel): ?>
                            <span class="badge badge-escrow-released">Payment released</span>
                        <?php elseif ($mFund): ?>
                            <span class="badge badge-escrow-funded">Escrow funded</span>
                        <?php else: ?>
                            <span class="badge badge-escrow-not-funded">Escrow not funded</span>
                        <?php endif; ?>
                    </div>
                    <div class="shift-footer">
                        <a href="/carechain/shifts.php?id=<?= (int) $ms['id'] ?>" class="btn btn-outline btn-sm">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
