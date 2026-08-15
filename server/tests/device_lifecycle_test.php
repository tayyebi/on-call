<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Autoloader.php';
OC_Autoloader::register(__DIR__ . '/../src');

$dataDir = sys_get_temp_dir() . '/oncall-test-' . bin2hex(random_bytes(6));
mkdir($dataDir, 0775, true);
putenv('DATA_DIR=' . $dataDir);

try {
    $db = OC_Database::get();
    $code = OC_Devices::startOnboard();
    $claim = OC_Devices::claimPairCode($code, 'test-model', '127.0.0.1');
    if (!$claim) {
        throw new RuntimeException('initial pairing failed');
    }
    $device = OC_Devices::findByUidToken($claim['uid'], $claim['token']);
    if (!$device) {
        throw new RuntimeException('paired device not found');
    }
    $deviceId = (int) $device['id'];
    $callId = OC_Calls::create('ring', [], [$deviceId], '127.0.0.1');
    $targetBefore = OC_Calls::targets($callId);
    OC_Calls::create('notification', ['text' => 'test notification'], [$deviceId], '127.0.0.1');
    $pending = OC_Calls::pendingFor($deviceId);
    if (($pending[1]['payload']['text'] ?? '') !== 'test notification') {
        throw new RuntimeException('pending command payload was not decoded');
    }

    $renewCode = OC_Devices::renewPairCode($deviceId);
    $renewed = OC_Devices::find($deviceId);
    if (!$renewCode || !$renewed || (int) $renewed['id'] !== $deviceId || count($targetBefore) !== 1) {
        throw new RuntimeException('renewal did not preserve device history');
    }
    if (OC_Devices::findByUidToken($claim['uid'], $claim['token']) !== null) {
        throw new RuntimeException('old credentials still work after renewal');
    }

    OC_Devices::claimPairCode($renewCode, 'test-model', '127.0.0.1');
    $repaired = OC_Devices::find($deviceId);
    OC_Devices::setDisabled($deviceId, true);
    if (OC_Devices::findByUidToken($repaired['uid'], $repaired['token']) !== null) {
        throw new RuntimeException('disabled credentials still work');
    }
    OC_Devices::remove($deviceId);
    if (OC_Calls::targets($callId)) {
        throw new RuntimeException('remove did not delete device history');
    }
    echo "device lifecycle checks passed\n";
} finally {
    $files = glob($dataDir . '/*') ?: [];
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($dataDir);
}
