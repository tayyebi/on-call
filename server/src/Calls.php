<?php
/**
 * A "call" is one dispatched request (sms / notification / ring) fanned out
 * to one or more devices. Each fan-out target is tracked in call_devices so
 * results.php can show what happened per device, and command-center /
 * webhook can retry only the failed ones.
 */
class OC_Calls
{
    /**
     * @param int[] $deviceIds
     */
    public static function create(string $type, array $payload, array $deviceIds, string $ip): int
    {
        $db = OC_Database::get();
        $db->beginTransaction();

        $stmt = $db->prepare('INSERT INTO calls (ip, type, payload) VALUES (?, ?, ?)');
        $stmt->execute([$ip, $type, json_encode($payload, JSON_UNESCAPED_SLASHES)]);
        $callId = (int) $db->lastInsertId();

        $insertTarget = $db->prepare('INSERT INTO call_devices (call_id, device_id, status) VALUES (?, ?, \'pending\')');
        foreach ($deviceIds as $deviceId) {
            $insertTarget->execute([$callId, $deviceId]);
        }

        $db->commit();
        return $callId;
    }

    public static function retryFailed(int $callId): void
    {
        $stmt = OC_Database::get()->prepare(
            'UPDATE call_devices SET status = \'pending\', result = NULL, updated_at = datetime(\'now\') WHERE call_id = ? AND status = \'failed\''
        );
        $stmt->execute([$callId]);
    }

    public static function recent(int $limit = 100): array
    {
        $stmt = OC_Database::get()->prepare('SELECT * FROM calls ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = OC_Database::get()->prepare('SELECT * FROM calls WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Devices targeted by a call, joined with their outcome. */
    public static function targets(int $callId): array
    {
        $stmt = OC_Database::get()->prepare(
            'SELECT cd.*, d.uid, d.model, d.ip AS device_ip
             FROM call_devices cd JOIN devices d ON d.id = cd.device_id
             WHERE cd.call_id = ? ORDER BY cd.id'
        );
        $stmt->execute([$callId]);
        return $stmt->fetchAll();
    }

    public static function targetLabel(int $callId): string
    {
        $targets = self::targets($callId);
        $total = count($targets);
        if ($total === 0) {
            return 'none';
        }
        if ($total === 1) {
            return $targets[0]['model'] ?: $targets[0]['uid'];
        }
        return $total . ' devices';
    }

    /** Pull pending commands for a device (used by the long-poll endpoint). */
    public static function pendingFor(int $deviceId): array
    {
        $stmt = OC_Database::get()->prepare(
            'SELECT cd.id AS target_id, c.id AS call_id, c.type, c.payload
             FROM call_devices cd JOIN calls c ON c.id = cd.call_id
             WHERE cd.device_id = ? AND cd.status = \'pending\'
             ORDER BY cd.id'
        );
        $stmt->execute([$deviceId]);
        $commands = $stmt->fetchAll();
        foreach ($commands as &$command) {
            $command['payload'] = json_decode($command['payload'], true) ?: [];
        }
        unset($command);
        return $commands;
    }

    public static function reportResult(int $targetId, string $status, string $result = ''): void
    {
        $stmt = OC_Database::get()->prepare(
            'UPDATE call_devices SET status = ?, result = ?, updated_at = datetime(\'now\') WHERE id = ?'
        );
        $stmt->execute([$status, $result, $targetId]);
    }
}
