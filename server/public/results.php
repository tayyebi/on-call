<?php
require_once __DIR__ . '/bootstrap.php';
OC_Auth::requireLogin();

$call = OC_Calls::find((int) ($_GET['id'] ?? 0));
if (!$call) {
    header('Location: api.php');
    exit;
}

$targets = OC_Calls::targets((int) $call['id']);

OC_View::start('Results');
?>
<section>
<h2>Results for call #<?= (int) $call['id'] ?> (<?= OC_View::e($call['type']) ?>)</h2>
<table>
<thead>
<tr><th>Device</th><th>IP</th><th>Status</th><th>Result</th><th>Updated</th></tr>
</thead>
<tbody>
<?php if (!$targets): ?>
<tr><td colspan="5">No devices were targeted.</td></tr>
<?php endif; ?>
<?php foreach ($targets as $target): ?>
<tr>
<td><?= OC_View::e($target['model'] ?: $target['uid']) ?></td>
<td><?= OC_View::e($target['device_ip']) ?></td>
<td><?= OC_View::e($target['status']) ?></td>
<td><?= OC_View::e($target['result']) ?></td>
<td><?= OC_View::e($target['updated_at']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<p><a href="call.php?id=<?= (int) $call['id'] ?>">Back to call details</a></p>
<p><a href="api.php">Back to call log</a></p>
</section>
<?php
OC_View::end();
