<?php
/** Device directory + pairing. */
class OC_Devices
{
    public static function all(): array
    {
        $stmt = OC_Database::get()->query('SELECT * FROM devices WHERE paired = 1 ORDER BY last_seen DESC');
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = OC_Database::get()->prepare('SELECT * FROM devices WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByUidToken(string $uid, string $token): ?array
    {
        $stmt = OC_Database::get()->prepare('SELECT * FROM devices WHERE uid = ? AND token = ? AND paired = 1');
        $stmt->execute([$uid, $token]);
        return $stmt->fetch() ?: null;
    }

    /** Create a fresh 6-digit pairing code, valid 5 minutes. Returns the code. */
    public static function startOnboard(): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 300);
        $stmt = OC_Database::get()->prepare(
            'INSERT INTO devices (pair_code, pair_code_expires, paired) VALUES (?, ?, 0)'
        );
        $stmt->execute([$code, $expires]);
        return $code;
    }

    public static function onboardStatus(string $code): string
    {
        $db = OC_Database::get();
        $stmt = $db->prepare('SELECT * FROM devices WHERE pair_code = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        if (!$row) {
            return 'unknown';
        }
        if ((int) $row['paired'] === 1) {
            return 'success';
        }
        if (strtotime($row['pair_code_expires']) < time()) {
            return 'timeout';
        }
        return 'waiting';
    }

    /** Mobile device claims a pairing code and receives uid+token. */
    public static function claimPairCode(string $code, string $model, string $ip): ?array
    {
        $db = OC_Database::get();
        $stmt = $db->prepare('SELECT * FROM devices WHERE pair_code = ? AND paired = 0 LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        if (!$row || strtotime($row['pair_code_expires']) < time()) {
            return null;
        }

        $uid = bin2hex(random_bytes(8));
        $token = bin2hex(random_bytes(24));
        $update = $db->prepare(
            'UPDATE devices SET uid = ?, token = ?, model = ?, ip = ?, last_seen = datetime(\'now\'), paired = 1 WHERE id = ?'
        );
        $update->execute([$uid, $token, $model, $ip, $row['id']]);

        return ['uid' => $uid, 'token' => $token];
    }

    public static function touch(int $id, string $ip): void
    {
        $stmt = OC_Database::get()->prepare('UPDATE devices SET last_seen = datetime(\'now\'), ip = ? WHERE id = ?');
        $stmt->execute([$ip, $id]);
    }

    /** Devices considered online: seen within the poll timeout window. */
    public static function isOnline(array $device): bool
    {
        if (!$device['last_seen']) {
            return false;
        }
        return (time() - strtotime($device['last_seen'])) < 45;
    }
}
