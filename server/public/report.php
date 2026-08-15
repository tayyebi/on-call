<?php
// The mobile app reports back the outcome of a command it executed.
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
$uid = (string) ($body['uid'] ?? '');
$token = (string) ($body['token'] ?? '');
$device = $uid !== '' && $token !== '' ? OC_Devices::findByUidToken($uid, $token) : null;

if (!$device) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$targetId = (int) ($body['target_id'] ?? 0);
$status = (string) ($body['status'] ?? 'failed');
$result = (string) ($body['result'] ?? '');

if (!in_array($status, ['success', 'failed'], true) || $targetId <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'invalid report']);
    exit;
}

OC_Calls::reportResult($targetId, $status, $result);
echo json_encode(['ok' => true]);
