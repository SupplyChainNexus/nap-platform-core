<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Persistence;

use PDO;
use RuntimeException;

final readonly class DatabaseConnectionFactory
{
    public static function createFromEnv(): PDO
    {
        $driver = getenv("DB_DRIVER") ?: "sqlite";
        if ($driver === "sqlite") {
            $path = getenv("DATABASE_PATH") ?: __DIR__ . "/../../database.sqlite";
            $pdo = new PDO("sqlite:" . $path);
        } elseif ($driver === "pgsql" || $driver === "postgres") {
            $host = getenv("DB_HOST") ?: "127.0.0.1";
            $port = getenv("DB_PORT") ?: "5432";
            $dbname = getenv("DB_NAME") ?: "nap";
            $user = getenv("DB_USER") ?: "postgres";
            $pass = getenv("DB_PASS") ?: "";
            $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", $host, $port, $dbname);
            $pdo = new PDO($dsn, $user, $pass);
        } else {
            throw new RuntimeException(sprintf("Unsupported database driver: %s", $driver));
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }
}
