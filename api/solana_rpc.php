<?php
/**
 * Same-origin JSON-RPC proxy to Solana devnet.
 * Browsers cannot call https://api.devnet.solana.com/ directly (CORS / OPTIONS preflight).
 * @solana/web3.js POSTs here; PHP forwards the body upstream via cURL.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'jsonrpc' => '2.0',
        'error'   => ['code' => -32600, 'message' => 'POST required'],
        'id'      => null,
    ]);
    exit;
}

$upstream = 'https://api.devnet.solana.com';

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    http_response_code(400);
    echo json_encode([
        'jsonrpc' => '2.0',
        'error'   => ['code' => -32700, 'message' => 'Empty body'],
        'id'      => null,
    ]);
    exit;
}

if (!function_exists('curl_init')) {
    http_response_code(501);
    echo json_encode([
        'jsonrpc' => '2.0',
        'error'   => ['code' => -32603, 'message' => 'PHP cURL extension required for Solana proxy'],
        'id'      => null,
    ]);
    exit;
}

$ch = curl_init($upstream);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $raw,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$errno = curl_errno($ch);
$err = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode([
        'jsonrpc' => '2.0',
        'error'   => ['code' => -32603, 'message' => 'Upstream error: ' . ($err ?: 'curl ' . $errno)],
        'id'      => null,
    ]);
    exit;
}

if ($httpCode >= 400 && $httpCode < 500) {
    http_response_code(200);
} elseif ($httpCode >= 500) {
    http_response_code(502);
}

echo $response;
