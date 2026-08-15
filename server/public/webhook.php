<?php
require_once __DIR__ . '/bootstrap.php';
header('Content-Type: application/json');

if (!OC_Auth::checkApiToken()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$body = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

/** @return int[] */
function oc_resolve_targets($devices): array
{
    if ($devices === 'all') {
        return array_map(static fn ($d) => (int) $d['id'], OC_Devices::all());
    }
    if (is_array($devices)) {
        return array_map('intval', $devices);
    }
    return [];
}

switch ($action) {
    case 'sms':
        $targets = oc_resolve_targets($body['devices'] ?? []);
        if (($body['number'] ?? '') === '' || !$targets) {
            http_response_code(422);
            echo json_encode(['error' => 'number and devices are required']);
            break;
        }
        $callId = OC_Calls::create('sms', ['number' => $body['number'], 'text' => $body['text'] ?? ''], $targets, $ip);
        echo json_encode(['call_id' => $callId]);
        break;

    case 'notification':
        $targets = oc_resolve_targets($body['devices'] ?? []);
        if (($body['text'] ?? '') === '' || !$targets) {
            http_response_code(422);
            echo json_encode(['error' => 'text and devices are required']);
            break;
        }
        $callId = OC_Calls::create('notification', ['text' => $body['text']], $targets, $ip);
        echo json_encode(['call_id' => $callId]);
        break;

    case 'ring':
        $targets = oc_resolve_targets($body['devices'] ?? []);
        if (!$targets) {
            http_response_code(422);
            echo json_encode(['error' => 'devices is required']);
            break;
        }
        $callId = OC_Calls::create('ring', [], $targets, $ip);
        echo json_encode(['call_id' => $callId]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'unknown action']);
}
