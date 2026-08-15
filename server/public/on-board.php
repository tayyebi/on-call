<?php
require_once __DIR__ . '/bootstrap.php';
OC_Auth::requireLogin();

$code = $_GET['code'] ?? '';
if ($code === '' || !ctype_digit($code)) {
    $code = OC_Devices::startOnboard();
    header('Location: on-board.php?code=' . $code);
    exit;
}

$status = OC_Devices::onboardStatus($code);

OC_View::start('On-board device');
?>
<section>
<h2>On-board a new device</h2>
<?php if ($status === 'waiting'): ?>
<meta http-equiv="refresh" content="3;url=on-board.php?code=<?= OC_View::e($code) ?>">
<p>Enter this code in the on-call mobile app together with this server's address:</p>
<p><strong><?= OC_View::e($code) ?></strong></p>
<p>Waiting for the device to connect. This code is valid for 5 minutes and this page refreshes automatically.</p>
<?php elseif ($status === 'success'): ?>
<p>Device paired successfully.</p>
<p><a href="devices.php">Back to devices</a></p>
<?php elseif ($status === 'timeout'): ?>
<p>The pairing code expired before any device connected.</p>
<form method="get" action="on-board.php">
<p><button type="submit">Generate a new code</button></p>
</form>
<?php else: ?>
<p>Unknown pairing code.</p>
<p><a href="on-board.php">Start over</a></p>
<?php endif; ?>
</section>
<?php
OC_View::end();
