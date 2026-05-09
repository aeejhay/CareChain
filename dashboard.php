<?php
$pageTitle = 'Dashboard';
require_once 'includes/header.php';

if (!isLoggedIn()) redirect('/carechain/login.php');

$role = getUserRole();
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT wallet_address FROM users WHERE id = ?');
$stmt->execute([$userId]);
$userWalletRow = $stmt->fetch(PDO::FETCH_ASSOC);
$savedWalletAddress = $userWalletRow['wallet_address'] ?? null;

if ($role === 'worker') {
    // Worker dashboard data
    $stmt = $pdo->prepare("SELECT * FROM worker_profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    
    // Count stats
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM shift_applications WHERE worker_id = ? AND status = 'completed'");
    $stmt->execute([$userId]);
    $completedShifts = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM shift_applications WHERE worker_id = ? AND status = 'pending'");
    $stmt->execute([$userId]);
    $pendingApps = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM documents WHERE user_id = ? AND status = 'approved'");
    $stmt->execute([$userId]);
    $verifiedDocs = $stmt->fetch()['total'];
    
    // Open shifts available
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM shifts WHERE status = 'open'");
    $stmt->execute();
    $openShifts = $stmt->fetch()['total'];
    
    // Recent shifts applied for
    $stmt = $pdo->prepare("
        SELECT s.*, sa.status as app_status, sa.applied_at, fp.facility_name 
        FROM shift_applications sa 
        JOIN shifts s ON sa.shift_id = s.id 
        JOIN facility_profiles fp ON s.facility_id = fp.user_id
        WHERE sa.worker_id = ? 
        ORDER BY sa.applied_at DESC LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentApplications = $stmt->fetchAll();

} elseif ($role === 'facility') {
    // Facility dashboard data
    $stmt = $pdo->prepare("SELECT * FROM facility_profiles WHERE user_id = ?");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM shifts WHERE facility_id = ? AND status = 'open'");
    $stmt->execute([$userId]);
    $activeShifts = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM shifts WHERE facility_id = ? AND status = 'completed'");
    $stmt->execute([$userId]);
    $completedShifts = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM shift_applications sa 
        JOIN shifts s ON sa.shift_id = s.id 
        WHERE s.facility_id = ? AND sa.status = 'pending'
    ");
    $stmt->execute([$userId]);
    $pendingApps = $stmt->fetch()['total'];
    
    // Recent shifts posted
    $stmt = $pdo->prepare("SELECT * FROM shifts WHERE facility_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $recentShifts = $stmt->fetchAll();
}
?>

<div class="container">
    <div class="card dashboard-wallet-card">
        <div class="dashboard-wallet-card-header">
            <h2 class="dashboard-wallet-title">Wallet (Solana devnet)</h2>
            <button type="button" class="btn btn-outline btn-sm" onclick="refreshBalance()">Refresh balance</button>
        </div>
        <p class="text-muted dashboard-wallet-copy">
            This balance is read live from devnet RPC (same as the header wallet). It is not stored in CareChain.
            If Phantom shows a different amount, check that Phantom is set to <strong>Devnet</strong> — mainnet SOL is a separate balance.
        </p>
        <div class="dashboard-wallet-grid">
            <div>
                <div class="stat-label">Devnet SOL</div>
                <div class="dashboard-wallet-sol" data-sol-balance>— SOL</div>
            </div>
            <div>
                <div class="stat-label">Saved wallet (profile)</div>
                <div class="dashboard-wallet-saved">
                    <?php if ($savedWalletAddress): ?>
                        <code><?= sanitize(substr($savedWalletAddress, 0, 8)) ?>…<?= sanitize(substr($savedWalletAddress, -8)) ?></code>
                    <?php else: ?>
                        <span class="text-muted">Not saved yet — connect wallet in the header</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <p class="dashboard-wallet-hint text-muted" id="dashboardWalletClusterHint" style="display: none;"></p>
    </div>

    <?php if ($role === 'worker'): ?>
        <div class="page-header">
            <div>
                <h1>Welcome, <?= sanitize($profile['first_name'] ?? 'Worker') ?></h1>
                <p style="color: var(--gray-500);">
                    <?= $profile['is_verified'] ? '<span class="badge badge-verified">&#x2713; Verified</span>' : '<span class="badge badge-pending">Pending Verification</span>' ?>
                </p>
            </div>
            <a href="/carechain/shifts.php" class="btn btn-primary">Browse Shifts</a>
        </div>
        
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-label">Completed Shifts</div>
                <div class="stat-value teal"><?= $completedShifts ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending Applications</div>
                <div class="stat-value"><?= $pendingApps ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Verified Documents</div>
                <div class="stat-value"><?= $verifiedDocs ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Open Shifts Available</div>
                <div class="stat-value coral"><?= $openShifts ?></div>
            </div>
        </div>
        
        <h2 style="margin-bottom: 1rem;">Recent Applications</h2>
        <?php if (empty($recentApplications)): ?>
            <div class="card" style="text-align: center; padding: 3rem;">
                <p style="color: var(--gray-500); margin-bottom: 1rem;">No applications yet. Start browsing available shifts!</p>
                <a href="/carechain/shifts.php" class="btn btn-primary">Find Shifts</a>
            </div>
        <?php else: ?>
            <?php foreach ($recentApplications as $app): ?>
                <div class="shift-card">
                    <div class="shift-header">
                        <div>
                            <div class="shift-title"><?= sanitize($app['title']) ?></div>
                            <div style="color: var(--gray-500); font-size: 0.88rem;"><?= sanitize($app['facility_name']) ?></div>
                        </div>
                        <div class="shift-pay">&euro;<?= number_format($app['total_pay'], 2) ?></div>
                    </div>
                    <div class="shift-meta">
                        <span>&#x1F4C5; <?= date('D, M j', strtotime($app['shift_date'])) ?></span>
                        <span>&#x1F552; <?= date('H:i', strtotime($app['start_time'])) ?> - <?= date('H:i', strtotime($app['end_time'])) ?></span>
                        <span class="badge badge-<?= $app['app_status'] ?>"><?= ucfirst($app['app_status']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
    <?php elseif ($role === 'facility'): ?>
        <div class="page-header">
            <div>
                <h1><?= sanitize($profile['facility_name'] ?? 'Facility') ?></h1>
                <p style="color: var(--gray-500);"><?= sanitize($profile['city'] ?? '') ?>, <?= sanitize($profile['county'] ?? '') ?></p>
            </div>
            <a href="/carechain/shifts.php?action=create" class="btn btn-primary">Post a Shift</a>
        </div>
        
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-label">Active Shifts</div>
                <div class="stat-value teal"><?= $activeShifts ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending Applications</div>
                <div class="stat-value coral"><?= $pendingApps ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Completed Shifts</div>
                <div class="stat-value"><?= $completedShifts ?></div>
            </div>
        </div>
        
        <h2 style="margin-bottom: 1rem;">Your Recent Shifts</h2>
        <?php if (empty($recentShifts)): ?>
            <div class="card" style="text-align: center; padding: 3rem;">
                <p style="color: var(--gray-500); margin-bottom: 1rem;">No shifts posted yet. Create your first shift listing!</p>
                <a href="/carechain/shifts.php?action=create" class="btn btn-primary">Post a Shift</a>
            </div>
        <?php else: ?>
            <?php foreach ($recentShifts as $shift): ?>
                <div class="shift-card">
                    <div class="shift-header">
                        <div class="shift-title"><?= sanitize($shift['title']) ?></div>
                        <div class="shift-pay">&euro;<?= number_format($shift['total_pay'], 2) ?></div>
                    </div>
                    <div class="shift-meta">
                        <span>&#x1F4C5; <?= date('D, M j', strtotime($shift['shift_date'])) ?></span>
                        <span>&#x1F552; <?= date('H:i', strtotime($shift['start_time'])) ?> - <?= date('H:i', strtotime($shift['end_time'])) ?></span>
                        <span class="badge badge-<?= $shift['status'] ?>"><?= ucfirst($shift['status']) ?></span>
                    </div>
                    <div class="shift-footer">
                        <span style="font-size: 0.85rem; color: var(--gray-500);">
                            <?= ucfirst(str_replace('_', ' ', $shift['required_role'])) ?> needed
                        </span>
                        <a href="/carechain/shifts.php?id=<?= $shift['id'] ?>" class="btn btn-outline btn-sm">View Applications</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
    <?php elseif ($role === 'admin'): ?>
        <div class="page-header">
            <h1>Admin Dashboard</h1>
            <a href="/carechain/verify.php" class="btn btn-primary">Review Documents</a>
        </div>
        
        <?php
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'worker'");
        $totalWorkers = $stmt->fetch()['total'];
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'facility'");
        $totalFacilities = $stmt->fetch()['total'];
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM shifts");
        $totalShifts = $stmt->fetch()['total'];
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM documents WHERE status = 'pending'");
        $pendingDocs = $stmt->fetch()['total'];
        ?>
        
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-label">Workers</div>
                <div class="stat-value teal"><?= $totalWorkers ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Facilities</div>
                <div class="stat-value coral"><?= $totalFacilities ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Shifts</div>
                <div class="stat-value"><?= $totalShifts ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Docs Pending Review</div>
                <div class="stat-value" style="color: var(--amber-600);"><?= $pendingDocs ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
