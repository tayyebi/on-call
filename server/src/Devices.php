<?php
/** Device directory + pairing. */
class OC_Devices
{
    public static function all(): array
    {
        $stmt = OC_Database::get()->query(
            'SELECT * FROM devices WHERE paired = 1 OR model IS NOT NULL ORDER BY last_seen DESC, id DESC'
        );
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
        $stmt = OC_Database::get()->prepare(
            'SELECT * FROM devices WHERE uid = ? AND token = ? AND paired = 1 AND disabled = 0'
        );
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
            'UPDATE devices SET uid = ?, token = ?, model = ?, ip = ?, last_seen = datetime(\'now\'), paired = 1,
             disabled = 0, pair_code = NULL, pair_code_expires = NULL WHERE id = ?'
        );
        $update->execute([$uid, $token, $model, $ip, $row['id']]);

        return ['uid' => $uid, 'token' => $token];
    }

    public static function touch(int $id, string $ip): void
    {
        $stmt = OC_Database::get()->prepare('UPDATE devices SET last_seen = datetime(\'now\'), ip = ? WHERE id = ?');
        $stmt->execute([$ip, $id]);
    }

    public static function renewPairCode(int $id): ?string
    {
        $device = self::find($id);
        if (!$device || (int) $device['disabled'] === 1) {
            return null;
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 300);
        $stmt = OC_Database::get()->prepare(
            'UPDATE devices SET uid = NULL, token = NULL, paired = 0, pair_code = ?, pair_code_expires = ? WHERE id = ?'
        );
        $stmt->execute([$code, $expires, $id]);
        return $code;
    }

    public static function setDisabled(int $id, bool $disabled): void
    {
        $stmt = OC_Database::get()->prepare('UPDATE devices SET disabled = ? WHERE id = ?');
        $stmt->execute([$disabled ? 1 : 0, $id]);
    }

    public static function remove(int $id): void
    {
        $stmt = OC_Database::get()->prepare('DELETE FROM devices WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** @param int[] $ids @return int[] */
    public static function activeIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = OC_Database::get()->prepare(
            "SELECT id FROM devices WHERE paired = 1 AND disabled = 0 AND id IN ($placeholders)"
        );
        $stmt->execute($ids);
        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    /** Devices considered online: seen within the poll timeout window. */
    public static function isOnline(array $device): bool
    {
        if ((int) $device['paired'] !== 1 || (int) $device['disabled'] === 1 || !$device['last_seen']) {
            return false;
        }
        return (time() - strtotime($device['last_seen'])) < 45;
    }
}
