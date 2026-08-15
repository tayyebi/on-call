<?php
require_once __DIR__ . '/bootstrap.php';
OC_Auth::requireLogin();

$devices = OC_Devices::all();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deviceId = (int) ($_POST['device'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'disable') {
        OC_Devices::setDisabled($deviceId, true);
    } elseif ($action === 'enable') {
        OC_Devices::setDisabled($deviceId, false);
    } elseif ($action === 'remove') {
        OC_Devices::remove($deviceId);
    }
    header('Location: devices.php');
    exit;
}

OC_View::start('Devices');
?>
<section>
<h2>Connected devices</h2>
<form method="get" action="on-board.php">
<p><button type="submit">On-board a new device</button></p>
</form>
<table>
<caption>Current devices</caption>
<thead>
<tr><th>IP</th><th>Model</th><th>Last seen</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>
<?php if (!$devices): ?>
<tr><td colspan="5">No devices paired yet.</td></tr>
<?php endif; ?>
<?php foreach ($devices as $device): ?>
<tr>
<td><?= OC_View::e($device['ip']) ?></td>
<td><?= OC_View::e($device['model']) ?></td>
<td><?= OC_View::e($device['last_seen']) ?></td>
<td><?= (int) $device['disabled'] === 1 ? 'disabled' : (OC_Devices::isOnline($device) ? 'online' : 'offline') ?></td>
<td>
<a href="command-center.php?device=<?= (int) $device['id'] ?>">Open</a>
<form method="post" action="devices.php">
<input type="hidden" name="device" value="<?= (int) $device['id'] ?>">
<?php if ((int) $device['disabled'] === 1): ?>
<button type="submit" name="action" value="enable">Enable</button>
<?php else: ?>
<button type="submit" name="action" value="disable">Disable</button>
<?php endif; ?>
<button type="submit" name="action" value="remove" onclick="return confirm('Remove this device and its command history?')">Remove</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>
<?php
OC_View::end();
