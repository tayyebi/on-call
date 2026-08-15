<?php
/** Web session auth (username/password/totp) + API bearer token check. */
class OC_Auth
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function attempt(string $username, string $password, string $totp): bool
    {
        $envUser = OC_Env::get('USERNAME', '');
        $envPass = OC_Env::get('PASSWORD', '');
        $envTotp = OC_Env::get('TOTP', '');

        if ($envUser === '' || $envPass === '') {
            return false;
        }

        $userOk = hash_equals($envUser, $username);
        $passOk = hash_equals($envPass, $password);
        $totpOk = $envTotp === '' || OC_Totp::verify($envTotp, $totp);

        if ($userOk && $passOk && $totpOk) {
            self::start();
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['username'] = $username;
            return true;
        }
        return false;
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['authenticated']);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        session_destroy();
    }

    /** Bearer token check for the webhook REST API (server-to-server). */
    public static function checkApiToken(): bool
    {
        $expected = OC_Env::get('API_TOKEN', '');
        if ($expected === '') {
            return false;
        }
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return false;
        }
        return hash_equals($expected, trim($m[1]));
    }
}
