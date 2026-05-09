<?php
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'save_wallet':
        $wallet = sanitize($_POST['wallet_address'] ?? '');
        if ($wallet) {
            $stmt = $pdo->prepare("UPDATE users SET wallet_address = ? WHERE id = ?");
            $stmt->execute([$wallet, $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
        }
        break;
        
    case 'save_credential':
        $docId = (int)($_POST['doc_id'] ?? 0);
        $txSig = sanitize($_POST['tx_signature'] ?? '');
        if ($docId && $txSig) {
            $stmt = $pdo->prepare("UPDATE documents SET nft_mint_address = ?, status = 'approved', verified_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$txSig, $docId, $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
        }
        break;
        
    case 'save_escrow':
        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $txSig = sanitize($_POST['tx_signature'] ?? '');
        if ($shiftId && $txSig) {
            $stmt = $pdo->prepare("UPDATE shifts SET escrow_tx_signature = ?, status = 'open' WHERE id = ? AND facility_id = ?");
            $stmt->execute([$txSig, $shiftId, $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
        }
        break;
        
    case 'release_payment':
        $shiftId = (int)($_POST['shift_id'] ?? 0);
        $txSig = sanitize($_POST['tx_signature'] ?? '');
        if ($shiftId && $txSig) {
            $stmt = $pdo->prepare("UPDATE shift_applications SET payment_tx_signature = ?, status = 'completed', completed_at = NOW() WHERE shift_id = ?");
            $stmt->execute([$txSig, $shiftId]);
            $stmt = $pdo->prepare("UPDATE shifts SET status = 'completed' WHERE id = ?");
            $stmt->execute([$shiftId]);
            echo json_encode(['success' => true]);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
