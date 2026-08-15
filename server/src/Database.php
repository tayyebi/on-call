<?php
/** SQLite connection + schema bootstrap. */
class OC_Database
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dataDir = OC_Env::get('DATA_DIR', __DIR__ . '/../data');
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $dataDir . '/oncall.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::$pdo = $pdo;
        self::migrate($pdo);

        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS devices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uid TEXT UNIQUE,
            token TEXT,
            model TEXT,
            ip TEXT,
            last_seen TEXT,
            paired INTEGER NOT NULL DEFAULT 0,
            disabled INTEGER NOT NULL DEFAULT 0,
            pair_code TEXT,
            pair_code_expires TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )');

        $deviceColumns = $pdo->query('PRAGMA table_info(devices)')->fetchAll();
        $deviceColumnNames = array_column($deviceColumns, 'name');
        if (!in_array('disabled', $deviceColumnNames, true)) {
            $pdo->exec('ALTER TABLE devices ADD COLUMN disabled INTEGER NOT NULL DEFAULT 0');
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS calls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            datetime TEXT NOT NULL DEFAULT (datetime(\'now\')),
            ip TEXT,
            type TEXT NOT NULL,
            payload TEXT NOT NULL
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS call_devices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            call_id INTEGER NOT NULL REFERENCES calls(id) ON DELETE CASCADE,
            device_id INTEGER NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
            status TEXT NOT NULL DEFAULT \'pending\',
            result TEXT,
            updated_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )');

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_call_devices_call ON call_devices(call_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_call_devices_device ON call_devices(device_id)');
    }
}
