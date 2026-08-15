<?php
// Long-polling endpoint the mobile app blocks on to receive commands.
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');

$uid = (string) ($_GET['uid'] ?? '');
$token = (string) ($_GET['token'] ?? '');
$device = $uid !== '' && $token !== '' ? OC_Devices::findByUidToken($uid, $token) : null;

if (!$device) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

OC_Devices::touch((int) $device['id'], $_SERVER['REMOTE_ADDR'] ?? '');

$deviceId = (int) $device['id'];
$commands = OC_LongPoll::wait(static function () use ($deviceId) {
    $pending = OC_Calls::pendingFor($deviceId);
    return $pending ? $pending : null;
}, 25, 1);

echo json_encode(['commands' => $commands ?? []]);
