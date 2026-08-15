<?php
// Called by the mobile app to claim an on-boarding code. No auth required
// beyond knowing the (short-lived, single-use) pairing code itself.
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
$code = trim((string) ($body['code'] ?? ''));
$model = (string) ($body['model'] ?? '');
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

if (!ctype_digit($code) || strlen($code) !== 6) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid code']);
    exit;
}

$result = OC_Devices::claimPairCode($code, $model, $ip);
if (!$result) {
    http_response_code(404);
    echo json_encode(['error' => 'code not found or expired']);
    exit;
}

echo json_encode($result);
