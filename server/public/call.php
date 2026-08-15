<?php
require_once __DIR__ . '/bootstrap.php';
OC_Auth::requireLogin();

$call = OC_Calls::find((int) ($_GET['id'] ?? 0));
if (!$call) {
    header('Location: api.php');
    exit;
}

$payload = json_decode($call['payload'], true) ?: [];

OC_View::start('Call details');
?>
<section>
<h2>Call #<?= (int) $call['id'] ?> — <?= OC_View::e($call['type']) ?></h2>
<dl>
<dt>Datetime</dt><dd><?= OC_View::e($call['datetime']) ?></dd>
<dt>Origin IP</dt><dd><?= OC_View::e($call['ip']) ?></dd>
<dt>Type</dt><dd><?= OC_View::e($call['type']) ?></dd>
<?php foreach ($payload as $key => $value): ?>
<dt><?= OC_View::e((string) $key) ?></dt><dd><?= OC_View::e((string) $value) ?></dd>
<?php endforeach; ?>
</dl>
<p><a href="results.php?id=<?= (int) $call['id'] ?>">See results for this call</a></p>
<p><a href="api.php">Back to call log</a></p>
</section>
<?php
OC_View::end();
