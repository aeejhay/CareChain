<?php
$pageTitle = 'Shifts';
require_once 'includes/header.php';

if (!isLoggedIn()) redirect('/carechain/login.php');

$role = getUserRole();
$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'list';
$shiftId = (int)($_GET['id'] ?? 0);

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
                    <span class="badge badge-<?= $shift['status'] ?>"><?= ucfirst($shift['status']) ?></span>
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
            
            <?php if ($shift['escrow_tx_signature']): ?>
                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--teal-50); border-radius: var(--radius-sm);">
                    <span style="font-size: 0.85rem; color: var(--teal-600); font-weight: 600;">&#x26D3; Escrow funded on Solana</span>
                    <button onclick="viewOnExplorer('<?= $shift['escrow_tx_signature'] ?>')" class="btn btn-sm btn-outline" style="margin-left: 0.5rem;">View on Explorer</button>
                </div>
            <?php endif; ?>
            
            <?php if ($role === 'worker' && $shift['status'] === 'open'): ?>
                <form method="POST" style="margin-top: 1rem;">
                    <input type="hidden" name="apply_shift" value="<?= $shift['id'] ?>">
                    <button type="submit" class="btn btn-primary">Apply for This Shift</button>
                </form>
            <?php endif; ?>
            
            <?php if ($role === 'facility' && $shift['facility_id'] == $userId && $shift['status'] === 'open'): ?>
                <div style="margin-top: 1rem;">
                    <button onclick="createShiftEscrow(<?= $shift['id'] ?>, 0.01)" class="btn btn-primary">&#x26D3; Fund Escrow on Solana</button>
                    <span style="font-size: 0.82rem; color: var(--gray-500); margin-left: 0.5rem;">Locks payment in smart contract</span>
                </div>
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
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?= sanitize($app['first_name'] . ' ' . $app['last_name']) ?></strong>
                            <span style="color: var(--gray-500); margin-left: 0.5rem;"><?= ucfirst($app['job_title']) ?></span>
                            <?= $app['is_verified'] ? '<span class="badge badge-verified" style="margin-left: 0.5rem;">Verified</span>' : '' ?>
                            <div style="font-size: 0.82rem; color: var(--gray-500);">Applied <?= date('M j, H:i', strtotime($app['applied_at'])) ?></div>
                        </div>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
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
                                <?php if ($app['wallet_address'] && !$app['payment_tx_signature']): ?>
                                    <button onclick="releaseEscrow(<?= $shiftId ?>, '<?= $app['wallet_address'] ?>', 0.01)" class="btn btn-primary btn-sm">&#x26A1; Pay Now</button>
                                <?php elseif ($app['payment_tx_signature']): ?>
                                    <button onclick="viewOnExplorer('<?= $app['payment_tx_signature'] ?>')" class="btn btn-outline btn-sm">&#x2713; Paid</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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
        } else {
            $stmt = $pdo->prepare("
                SELECT s.*, fp.facility_name, fp.city, fp.county 
                FROM shifts s 
                JOIN facility_profiles fp ON s.facility_id = fp.user_id 
                WHERE s.status = 'open' AND s.shift_date >= CURDATE()
                ORDER BY s.urgency = 'critical' DESC, s.urgency = 'urgent' DESC, s.shift_date ASC
            ");
            $stmt->execute();
        }
        $shifts = $stmt->fetchAll();
        ?>
        
        <?php if (empty($shifts)): ?>
            <div class="card" style="text-align: center; padding: 3rem;">
                <p style="color: var(--gray-500);">
                    <?= $role === 'facility' ? 'No shifts posted yet.' : 'No open shifts at the moment. Check back soon!' ?>
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
                </div>
                <div class="shift-footer">
                    <span style="font-size: 0.85rem; color: var(--gray-500);">
                        <?= ucfirst(str_replace('_', ' ', $shift['required_role'])) ?>
                    </span>
                    <a href="/carechain/shifts.php?id=<?= $shift['id'] ?>" class="btn btn-outline btn-sm">View Details</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
