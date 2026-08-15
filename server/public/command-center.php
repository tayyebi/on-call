<?php
require_once __DIR__ . '/bootstrap.php';
OC_Auth::requireLogin();

$deviceId = (int) ($_GET['device'] ?? $_POST['device'] ?? 0);
$device = $deviceId ? OC_Devices::find($deviceId) : null;
if (!$device) {
    header('Location: devices.php');
    exit;
}

$notice = '';
$renewCode = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($action === 'renew') {
        $renewCode = OC_Devices::renewPairCode($deviceId) ?? '';
        $notice = $renewCode === '' ? 'Could not renew this device.' : 'Enter this code in the Android app within 5 minutes.';
        $device = OC_Devices::find($deviceId);
    } elseif ((int) $device['paired'] !== 1 || (int) $device['disabled'] === 1) {
        $notice = 'Enable and pair this device before sending commands.';
    } elseif ($action === 'sms') {
        OC_Calls::create('sms', ['number' => $_POST['number'] ?? '', 'text' => $_POST['text'] ?? ''], [$deviceId], $ip);
        $notice = 'SMS command queued.';
    } elseif ($action === 'notification') {
        OC_Calls::create('notification', ['text' => $_POST['notification_text'] ?? ''], [$deviceId], $ip);
        $notice = 'Notification command queued.';
    } elseif ($action === 'ring') {
        OC_Calls::create('ring', [], [$deviceId], $ip);
        $notice = 'Ring command queued.';
    }
}

OC_View::start('Command center');
?>
<section>
<h2>Command center — <?= OC_View::e($device['model'] ?: $device['uid']) ?></h2>
<p>IP: <?= OC_View::e($device['ip']) ?> — <?= (int) $device['disabled'] === 1 ? 'disabled' : (OC_Devices::isOnline($device) ? 'online' : 'offline') ?></p>
<?php if ($notice !== ''): ?>
<p><?= OC_View::e($notice) ?></p>
<?php endif; ?>
<?php if ($renewCode !== ''): ?>
<p><strong>Renew code: <?= OC_View::e($renewCode) ?></strong></p>
<?php endif; ?>

<?php if (!OC_Devices::isOnline($device) && (int) $device['disabled'] !== 1): ?>
<form method="post" action="command-center.php?device=<?= $deviceId ?>">
<input type="hidden" name="device" value="<?= $deviceId ?>">
<input type="hidden" name="action" value="renew">
<button type="submit">Renew pairing code</button>
</form>
<?php endif; ?>

<?php if ((int) $device['paired'] === 1 && (int) $device['disabled'] !== 1): ?>
<form method="post" action="command-center.php?device=<?= $deviceId ?>">
<input type="hidden" name="device" value="<?= $deviceId ?>">
<input type="hidden" name="action" value="sms">
<h3>Send SMS</h3>
<p><label>Number <input type="tel" name="number" required></label></p>
<p><label>Text <textarea name="text" required></textarea></label></p>
<p><button type="submit">Send SMS</button></p>
</form>

<form method="post" action="command-center.php?device=<?= $deviceId ?>">
<input type="hidden" name="device" value="<?= $deviceId ?>">
<input type="hidden" name="action" value="notification">
<h3>Dispatch notification</h3>
<p><label>Text <textarea name="notification_text" required></textarea></label></p>
<p><button type="submit">Dispatch notification</button></p>
</form>

<form method="post" action="command-center.php?device=<?= $deviceId ?>">
<input type="hidden" name="device" value="<?= $deviceId ?>">
<input type="hidden" name="action" value="ring">
<h3>Ring</h3>
<p><button type="submit">Play ring sound</button></p>
</form>
<?php else: ?>
<p>Commands are unavailable until this device is enabled and paired.</p>
<?php endif; ?>

<p><a href="devices.php">Back to devices</a></p>
</section>
<?php
OC_View::end();
