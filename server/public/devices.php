<?php
require_once __DIR__ . '/bootstrap.php';
OC_Auth::requireLogin();

$devices = OC_Devices::all();

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
<tr><th>IP</th><th>Model</th><th>Last seen</th><th>Status</th><th>Command center</th></tr>
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
<td><?= OC_Devices::isOnline($device) ? 'online' : 'offline' ?></td>
<td><a href="command-center.php?device=<?= (int) $device['id'] ?>">Open</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>
<?php
OC_View::end();
