<?php
$pageTitle = 'Verify Credentials';
require_once 'includes/header.php';

if (!isLoggedIn() || getUserRole() !== 'admin') redirect('/carechain/dashboard.php');

// Handle verify/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $docId = (int)($_POST['doc_id'] ?? 0);
    
    if (isset($_POST['approve'])) {
        $stmt = $pdo->prepare("UPDATE documents SET status = 'approved', verified_at = NOW() WHERE id = ?");
        $stmt->execute([$docId]);
        
        // Check if all docs approved — mark worker as verified
        $stmt = $pdo->prepare("SELECT user_id FROM documents WHERE id = ?");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM documents WHERE user_id = ? AND status = 'pending'");
            $stmt->execute([$doc['user_id']]);
            $remaining = $stmt->fetch()['pending'];
            
            if ($remaining == 0) {
                $stmt = $pdo->prepare("UPDATE worker_profiles SET is_verified = 1 WHERE user_id = ?");
                $stmt->execute([$doc['user_id']]);
            }
        }
        
        flash('success', 'Document approved! Worker can now mint their credential on-chain.');
    }
    
    if (isset($_POST['reject'])) {
        $stmt = $pdo->prepare("UPDATE documents SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$docId]);
        flash('error', 'Document rejected.');
    }
    
    redirect('/carechain/verify.php');
}

// Get pending documents
$stmt = $pdo->query("
    SELECT d.*, wp.first_name, wp.last_name, wp.job_title, u.email
    FROM documents d
    JOIN users u ON d.user_id = u.id
    JOIN worker_profiles wp ON d.user_id = wp.user_id
    WHERE d.status = 'pending'
    ORDER BY d.uploaded_at ASC
");
$pendingDocs = $stmt->fetchAll();

// Get recently verified
$stmt = $pdo->query("
    SELECT d.*, wp.first_name, wp.last_name
    FROM documents d
    JOIN worker_profiles wp ON d.user_id = wp.user_id
    WHERE d.status = 'approved'
    ORDER BY d.verified_at DESC
    LIMIT 10
");
$recentlyVerified = $stmt->fetchAll();
?>

<div class="container">
    <div class="page-header">
        <h1>Credential Verification</h1>
        <span class="badge badge-pending" style="font-size: 0.95rem; padding: 0.4rem 1rem;">
            <?= count($pendingDocs) ?> pending
        </span>
    </div>
    
    <?php if (empty($pendingDocs)): ?>
        <div class="card" style="text-align: center; padding: 3rem;">
            <p style="color: var(--gray-500); font-size: 1.1rem;">&#x2705; All caught up! No documents pending review.</p>
        </div>
    <?php else: ?>
        <h2 style="margin-bottom: 1rem;">Pending Review</h2>
        <?php foreach ($pendingDocs as $doc): ?>
            <div class="card" style="margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <div style="font-weight: 600; font-size: 1.05rem;">
                            <?= sanitize($doc['first_name'] . ' ' . $doc['last_name']) ?>
                            <span style="color: var(--gray-500); font-weight: 400; margin-left: 0.5rem;"><?= ucfirst($doc['job_title']) ?></span>
                        </div>
                        <div style="margin-top: 0.5rem;">
                            <span class="badge badge-pending"><?= strtoupper(str_replace('_', ' ', $doc['doc_type'])) ?></span>
                        </div>
                        <div style="font-size: 0.85rem; color: var(--gray-500); margin-top: 0.4rem;">
                            File: <?= sanitize($doc['doc_name']) ?> &bull; Uploaded <?= date('M j, Y H:i', strtotime($doc['uploaded_at'])) ?>
                        </div>
                        <a href="/carechain/<?= sanitize($doc['file_path']) ?>" target="_blank" class="btn btn-outline btn-sm" style="margin-top: 0.5rem;">View Document</a>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                            <button type="submit" name="approve" value="1" class="btn btn-primary btn-sm">&#x2713; Approve</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
                            <button type="submit" name="reject" value="1" class="btn btn-outline btn-sm" style="color: var(--red-400); border-color: var(--red-400);">&#x2717; Reject</button>
                        </form>
                    </div>
                </div>
                
                <!-- Mint on-chain button (shown after approval, but keeping here for admin flow) -->
                <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid var(--gray-100); font-size: 0.85rem; color: var(--gray-500);">
                    After approval, the worker can mint this credential as a soulbound NFT from their profile.
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (!empty($recentlyVerified)): ?>
        <h2 style="margin: 2rem 0 1rem;">Recently Verified</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Worker</th>
                        <th>Document</th>
                        <th>Verified</th>
                        <th>On-Chain</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentlyVerified as $doc): ?>
                        <tr>
                            <td><strong><?= sanitize($doc['first_name'] . ' ' . $doc['last_name']) ?></strong></td>
                            <td><?= ucfirst(str_replace('_', ' ', $doc['doc_type'])) ?></td>
                            <td><?= date('M j, Y', strtotime($doc['verified_at'])) ?></td>
                            <td>
                                <?php if ($doc['nft_mint_address']): ?>
                                    <span class="badge badge-verified" style="cursor: pointer;" onclick="viewOnExplorer('<?= $doc['nft_mint_address'] ?>')">&#x26D3; Minted</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Not yet minted</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
