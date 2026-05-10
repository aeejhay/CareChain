<?php
/**
 * JSON API for wallet, credentials, and prototype Solana escrow / payment release.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/solana_escrow.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$role = getUserRole();
$action = $_POST['action'] ?? '';

function wallet_json_error($code, $message)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function wallet_json_ok($extra = [])
{
    echo json_encode(array_merge(['success' => true], $extra));
    exit;
}

switch ($action) {
    case 'save_wallet':
        $wallet = trim((string) ($_POST['wallet_address'] ?? ''));
        if ($wallet === '') {
            wallet_json_error(400, 'Missing wallet address');
        }
        if (strlen($wallet) > 64) {
            wallet_json_error(400, 'Invalid wallet address');
        }
        $stmt = $pdo->prepare('UPDATE users SET wallet_address = ? WHERE id = ?');
        $stmt->execute([$wallet, $userId]);
        wallet_json_ok();
        break;

    case 'save_credential':
        $docId = (int) ($_POST['doc_id'] ?? 0);
        $txSig = trim((string) ($_POST['tx_signature'] ?? ''));
        if ($docId < 1 || $txSig === '') {
            wallet_json_error(400, 'Invalid request');
        }
        $stmt = $pdo->prepare(
            "UPDATE documents SET nft_mint_address = ?, status = 'approved', verified_at = NOW() WHERE id = ? AND user_id = ?"
        );
        $stmt->execute([$txSig, $docId, $userId]);
        wallet_json_ok();
        break;

    case 'save_escrow':
        if ($role !== 'facility') {
            wallet_json_error(403, 'Only facilities can fund escrow');
        }
        $shiftId = (int) ($_POST['shift_id'] ?? 0);
        $txSig = trim((string) ($_POST['escrow_tx_signature'] ?? ''));
        $amountSol = isset($_POST['escrow_amount_sol']) ? (float) $_POST['escrow_amount_sol'] : 0.0;

        if ($shiftId < 1 || $txSig === '') {
            wallet_json_error(400, 'Missing shift or transaction signature');
        }
        if (strlen($txSig) > 200 || strlen($txSig) < 32) {
            wallet_json_error(400, 'Invalid transaction signature');
        }
        if ($amountSol <= 0 || $amountSol > 5000) {
            wallet_json_error(400, 'Invalid escrow amount');
        }

        $stmt = $pdo->prepare(
            'SELECT id, facility_id, escrow_status FROM shifts WHERE id = ? AND facility_id = ?'
        );
        $stmt->execute([$shiftId, $userId]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$shift) {
            wallet_json_error(404, 'Shift not found or not owned by your facility');
        }
        $escSt = $shift['escrow_status'] ?? 'not_funded';
        if (in_array($escSt, ['funded', 'released'], true)) {
            wallet_json_error(400, 'Escrow is already funded for this shift');
        }

        $vault = defined('CARECHAIN_PROTO_ESCROW_PUBKEY') ? CARECHAIN_PROTO_ESCROW_PUBKEY : '';
        if ($vault === '') {
            wallet_json_error(500, 'Escrow vault not configured');
        }

        $stmt = $pdo->prepare(
            'UPDATE shifts SET
                escrow_tx_signature = ?,
                escrow_amount_sol = ?,
                escrow_status = \'funded\',
                escrow_funded_at = NOW(),
                escrow_address = ?
             WHERE id = ? AND facility_id = ?'
        );
        $stmt->execute([$txSig, $amountSol, substr($vault, 0, 64), $shiftId, $userId]);
        wallet_json_ok([
            'shift_id' => $shiftId,
            'escrow_status' => 'funded',
            'escrow_tx_signature' => $txSig,
        ]);
        break;

    case 'release_payment':
        if ($role !== 'facility') {
            wallet_json_error(403, 'Only facilities can release payment');
        }
        $shiftId = (int) ($_POST['shift_id'] ?? 0);
        $workerId = (int) ($_POST['worker_id'] ?? 0);
        $paySig = trim((string) ($_POST['payment_tx_signature'] ?? ''));

        if ($shiftId < 1 || $workerId < 1 || $paySig === '') {
            wallet_json_error(400, 'Missing shift, worker, or payment signature');
        }
        if (strlen($paySig) > 200 || strlen($paySig) < 32) {
            wallet_json_error(400, 'Invalid transaction signature');
        }

        $stmt = $pdo->prepare(
            'SELECT id, facility_id, status, escrow_status, payment_tx_signature FROM shifts WHERE id = ? AND facility_id = ?'
        );
        $stmt->execute([$shiftId, $userId]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$shift) {
            wallet_json_error(404, 'Shift not found or not owned by your facility');
        }
        if ($shift['status'] !== 'completed') {
            wallet_json_error(400, 'This shift must be completed before payment can be released');
        }
        if (!empty($shift['payment_tx_signature'])) {
            wallet_json_error(400, 'Payment has already been released for this shift');
        }
        if (($shift['escrow_status'] ?? '') !== 'funded') {
            wallet_json_error(400, 'Escrow must be funded before payment can be released');
        }

        $stmt = $pdo->prepare(
            "SELECT sa.id FROM shift_applications sa
             WHERE sa.shift_id = ? AND sa.worker_id = ? AND sa.status = 'completed'"
        );
        $stmt->execute([$shiftId, $workerId]);
        if (!$stmt->fetch()) {
            wallet_json_error(400, 'Worker is not recorded as having completed this shift');
        }

        $stmt = $pdo->prepare('SELECT wallet_address FROM users WHERE id = ?');
        $stmt->execute([$workerId]);
        $wWallet = $stmt->fetchColumn();
        if (!$wWallet || trim((string) $wWallet) === '') {
            wallet_json_error(400, 'Worker wallet address missing — worker must save a wallet on their profile');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE shifts SET
                    payment_tx_signature = ?,
                    payment_released_at = NOW(),
                    escrow_status = \'released\'
                 WHERE id = ? AND facility_id = ? AND escrow_status = \'funded\''
            );
            $stmt->execute([$paySig, $shiftId, $userId]);
            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                wallet_json_error(400, 'Payment could not be recorded (escrow state may have changed)');
            }
            $stmt = $pdo->prepare(
                'UPDATE shift_applications SET payment_tx_signature = ? WHERE shift_id = ? AND worker_id = ?'
            );
            $stmt->execute([$paySig, $shiftId, $workerId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            wallet_json_error(500, 'Could not save payment');
        }

        wallet_json_ok([
            'shift_id' => $shiftId,
            'worker_id' => $workerId,
            'escrow_status' => 'released',
            'payment_tx_signature' => $paySig,
        ]);
        break;

    default:
        wallet_json_error(400, 'Invalid action');
}
