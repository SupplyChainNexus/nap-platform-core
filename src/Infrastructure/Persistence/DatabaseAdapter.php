<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Persistence;

use PDO;
use PDOException;

final class DatabaseAdapter
{
    private PDO $pdo;
    private string $driver;

    public function __construct(?PDO $customPdo = null)
    {
        if ($customPdo !== null) {
            $this->pdo = $customPdo;
            $rawAttr = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $this->driver = is_string($rawAttr) ? $rawAttr : "sqlite";
            $this->initializeSchema();
            return;
        }

        $dbUrl = getenv("DATABASE_URL") ?: getenv("PDO_DSN");

        if (!empty($dbUrl) && str_contains((string) $dbUrl, "postgres")) {
            $this->driver = "pgsql";
            /** @var array<string, mixed>|false $dbopts */
            $dbopts = parse_url((string) $dbUrl);
            $host = is_string($dbopts["host"] ?? null) ? $dbopts["host"] : "localhost";
            $port = is_numeric($dbopts["port"] ?? null) ? (int) $dbopts["port"] : 5432;
            $user = is_string($dbopts["user"] ?? null) ? $dbopts["user"] : "";
            $pass = is_string($dbopts["pass"] ?? null) ? $dbopts["pass"] : "";
            $db   = is_string($dbopts["path"] ?? null) ? ltrim($dbopts["path"], "/") : "";

            $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } else {
            $this->driver = "sqlite";
            $envPath = getenv("NAP_DB_PATH");
            $dbPath = is_string($envPath) && !empty($envPath) ? $envPath : __DIR__ . "/../../../data/telemetry.sqlite";
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            $this->pdo = new PDO("sqlite:" . $dbPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        }

        $this->initializeSchema();
    }

    private function initializeSchema(): void
    {
        $idType = $this->driver === "pgsql" ? "SERIAL PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT";
        $textType = "TEXT";
        $realType = $this->driver === "pgsql" ? "DOUBLE PRECISION" : "REAL";

        $sql = "CREATE TABLE IF NOT EXISTS evaluation_logs (
            id {$idType},
            part_number {$textType} NOT NULL,
            supplier_id {$textType} NOT NULL,
            original_amount {$realType} NOT NULL,
            recommended_amount {$realType} NOT NULL,
            savings_amount {$realType} NOT NULL,
            confidence {$realType} NOT NULL,
            currency {$textType} DEFAULT 'ZAR',
            decision {$textType} NOT NULL,
            reasons {$textType} NOT NULL,
            created_at {$textType} NOT NULL
        )";

        $this->pdo->exec($sql);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }
}
