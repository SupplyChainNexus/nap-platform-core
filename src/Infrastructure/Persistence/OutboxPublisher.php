<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Persistence;

use PDO;

final readonly class OutboxPublisher
{
    public function __construct(
        private PDO $pdo
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function enqueue(string $eventType, array $payload, string $createdAt): void
    {
        $messageId = sprintf("msg_%s_%s", bin2hex(random_bytes(4)), time());
        $payloadWithIdempotency = array_merge($payload, [
            "_metadata" => [
                "message_id" => $messageId,
                "idempotency_key" => $messageId,
                "enqueued_at" => $createdAt,
            ],
        ]);

        $stmt = $this->pdo->prepare("
            INSERT INTO outbox_messages (event_type, payload, created_at, published_at)
            VALUES (:type, :payload, :created_at, NULL)
        ");
        $stmt->execute([
            "type" => $eventType,
            "payload" => (string) json_encode($payloadWithIdempotency),
            "created_at" => $createdAt,
        ]);
    }

    public function processPending(callable $dispatcher): int
    {
        $stmt = $this->pdo->query("SELECT * FROM outbox_messages WHERE published_at IS NULL ORDER BY id ASC LIMIT 50");
        if (!$stmt) {
            return 0;
        }

        /** @var list<array{id: int|string, event_type: string, payload: string}> $pending */
        $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;

        foreach ($pending as $msg) {
            /** @var array<string, mixed> $payload */
            $payload = (array) json_decode($msg["payload"], true);
            $dispatcher($msg["event_type"], $payload);

            $update = $this->pdo->prepare("UPDATE outbox_messages SET published_at = CURRENT_TIMESTAMP WHERE id = :id");
            $update->execute(["id" => $msg["id"]]);
            $count++;
        }

        return $count;
    }
}
