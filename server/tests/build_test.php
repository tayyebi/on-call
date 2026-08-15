<?php
/**
 * Sanity checks for the merged dist/app.php build (server/build.php).
 * Plain PHP, no test framework — run with `php tests/build_test.php`
 * after `php build.php`, from the server/ directory.
 */
declare(strict_types=1);

$distFile = __DIR__ . '/../dist/app.php';
$styleFile = __DIR__ . '/../dist/style.css';
$failures = [];

function check(array &$failures, bool $ok, string $message): void
{
    echo ($ok ? "ok   " : "FAIL ") . $message . "\n";
    if (!$ok) {
        $failures[] = $message;
    }
}

check($failures, is_file($distFile), "dist/app.php exists (run php build.php first)");
check($failures, is_file($styleFile), "dist/style.css exists (run php build.php first)");
if (!is_file($distFile)) {
    fwrite(STDERR, "\ncannot continue without dist/app.php\n");
    exit(1);
}

$source = file_get_contents($distFile);

check($failures, str_starts_with($source, "<?php"), "starts with a <?php tag");

$lintOutput = [];
$lintStatus = 0;
exec('php -l ' . escapeshellarg($distFile) . ' 2>&1', $lintOutput, $lintStatus);
check($failures, $lintStatus === 0, "php -l reports no syntax errors: " . implode(' ', $lintOutput));

// OC_Autoloader itself is deliberately excluded from the merge — the
// concatenated build needs no autoloading — so it isn't checked for here.
foreach (['OC_Auth', 'OC_Calls', 'OC_Database', 'OC_Devices', 'OC_Env', 'OC_LongPoll', 'OC_Totp', 'OC_View'] as $class) {
    check($failures, (bool) preg_match('/\bclass\s+' . preg_quote($class, '/') . '\b/', $source), "defines $class");
}

foreach (['index', 'api', 'call', 'command-center', 'devices', 'login', 'logout', 'on-board', 'pair', 'poll', 'report', 'results', 'webhook'] as $route) {
    check($failures, str_contains($source, "\$__routes['{$route}']"), "registers route '$route'");
}

check($failures, str_contains($source, 'bootstrap.php') === false, "bootstrap.php require is stripped from merged pages");
check($failures, str_contains($source, "OC_Env::load(__DIR__ . '/.env');"), "front controller loads .env");
check($failures, str_contains($source, 'href=\"style.css\"'), "pages load the shared stylesheet");

// Each route closure must open directly in PHP-code mode. If it closes
// into HTML mode instead, the page's own leading PHP statements (auth
// checks, data fetch) get emitted as literal text instead of executing.
check(
    $failures,
    (bool) !preg_match('/\$__routes\[[^\]]+\]\s*=\s*function\s*\(\)\s*\{\s*\?>/', $source),
    "no route closure opens straight into HTML mode"
);

// api.php does real work (auth guard, DB read) before its first echoed
// HTML; confirm that code survived the merge as executable PHP, not text.
check(
    $failures,
    (bool) preg_match('/function \(\) \{\s*OC_Auth::requireLogin\(\);/', $source),
    "api route's pre-render auth check is live PHP code, not literal output"
);

if ($failures) {
    fwrite(STDERR, "\n" . count($failures) . " check(s) failed.\n");
    exit(1);
}

echo "\nall checks passed.\n";
