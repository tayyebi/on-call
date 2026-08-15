<?php
require_once __DIR__ . '/bootstrap.php';
OC_Auth::start();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = OC_Auth::attempt(
        $_POST['username'] ?? '',
        $_POST['password'] ?? '',
        $_POST['totp'] ?? ''
    );
    if ($ok) {
        header('Location: devices.php');
        exit;
    }
    $error = 'Invalid username, password or authenticator code.';
}

if (OC_Auth::check()) {
    header('Location: devices.php');
    exit;
}

OC_View::start('Log in', false);
?>
<section>
<h2>Log in</h2>
<?php if ($error !== ''): ?>
<p role="alert"><?= OC_View::e($error) ?></p>
<?php endif; ?>
<form method="post" action="login.php">
<p>
<label>Username <input type="text" name="username" required autocomplete="username"></label>
</p>
<p>
<label>Password <input type="password" name="password" required autocomplete="current-password"></label>
</p>
<p>
<label>Authenticator code <input type="text" name="totp" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code"></label>
</p>
<p><button type="submit">Login</button></p>
</form>
</section>
<?php
OC_View::end();
