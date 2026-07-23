<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Console;

use PDO;
use PDOStatement;
use RuntimeException;

final readonly class MigrateConsoleCommand
{
    public function __construct(
        private PDO $pdo,
        private string $migrationsDir
    ) {}

    /**
     * @return array{status: string, applied: list<string>}
     */
    public function execute(): array
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS schema_migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $stmt = $this->pdo->query("SELECT migration FROM schema_migrations");
        /** @var list<string> $executed */
        $executed = ($stmt instanceof PDOStatement) ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        $files = glob($this->migrationsDir . "/*.sql");
        if ($files === false) {
            return ["status" => "success", "applied" => []];
        }

        sort($files);
        $appliedNow = [];

        foreach ($files as $file) {
            $filename = basename($file);
            if (in_array($filename, $executed, true)) {
                continue;
            }

            /** @var string $sql */
            $sql = file_get_contents($file);

            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec($sql);
                $insertStmt = $this->pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (:migration)");
                $insertStmt->execute(["migration" => $filename]);
                $this->pdo->commit();
                $appliedNow[] = $filename;
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                throw new RuntimeException(sprintf("Failed applying migration %s: %s", $filename, $e->getMessage()), 0, $e);
            }
        }

        return [
            "status" => "success",
            "applied" => $appliedNow,
        ];
    }
}
