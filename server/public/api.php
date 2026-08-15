<?php
require_once __DIR__ . '/bootstrap.php';
OC_Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'retry') {
    OC_Calls::retryFailed((int) ($_POST['call'] ?? 0));
    header('Location: api.php');
    exit;
}

$calls = OC_Calls::recent();

OC_View::start('Call log');
?>
<section>
<h2>Last calls</h2>
<table>
<thead>
<tr><th>Datetime</th><th>IP</th><th>Request</th><th>Target device(s)</th><th>Results</th><th>Retry failed</th></tr>
</thead>
<tbody>
<?php if (!$calls): ?>
<tr><td colspan="6">No calls yet.</td></tr>
<?php endif; ?>
<?php foreach ($calls as $call): ?>
<tr>
<td><?= OC_View::e($call['datetime']) ?></td>
<td><?= OC_View::e($call['ip']) ?></td>
<td><a href="call.php?id=<?= (int) $call['id'] ?>"><?= OC_View::e($call['type']) ?></a></td>
<td><a href="results.php?id=<?= (int) $call['id'] ?>"><?= OC_View::e(OC_Calls::targetLabel((int) $call['id'])) ?></a></td>
<td><a href="results.php?id=<?= (int) $call['id'] ?>">View results</a></td>
<td>
<form method="post" action="api.php">
<input type="hidden" name="action" value="retry">
<input type="hidden" name="call" value="<?= (int) $call['id'] ?>">
<button type="submit">Retry failed devices</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>
<?php
OC_View::end();
