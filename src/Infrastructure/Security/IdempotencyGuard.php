<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Security;

use NAP\Infrastructure\Persistence\DatabaseAdapter;

final class IdempotencyGuard
{
    private DatabaseAdapter $db;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
        $this->ensureTableExists();
    }

    private function ensureTableExists(): void
    {
        $pdo = $this->db->getPdo();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nx_processed_webhooks (
                idempotency_key TEXT PRIMARY KEY,
                processed_at TEXT NOT NULL
            )
        ");
    }

    public function isProcessed(string $key): bool
    {
        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare("SELECT 1 FROM nx_processed_webhooks WHERE idempotency_key = :key");
        $stmt->execute([":key" => $key]);

        return $stmt->fetch() !== false;
    }

    public function markProcessed(string $key): void
    {
        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare("
            INSERT OR IGNORE INTO nx_processed_webhooks (idempotency_key, processed_at)
            VALUES (:key, :processed_at)
        ");
        $stmt->execute([
            ":key" => $key,
            ":processed_at" => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
        ]);
    }
}