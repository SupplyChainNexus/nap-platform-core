<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Persistence;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            $dbPath = __DIR__ . "/../../../data/nap_platform.sqlite";
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            self::$pdo = new PDO("sqlite:" . $dbPath);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            self::initializeSchema(self::$pdo);
        }

        return self::$pdo;
    }

    private static function initializeSchema(PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS evaluations_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            part_number TEXT NOT NULL,
            supplier_id TEXT NOT NULL,
            original_amount REAL NOT NULL,
            recommended_amount REAL NOT NULL,
            savings_amount REAL NOT NULL,
            confidence REAL NOT NULL,
            reasons_json TEXT NOT NULL,
            created_at TEXT NOT NULL
        )";

        $pdo->exec($sql);
    }
}
