<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http;

use PDO;

final readonly class RateLimitMiddleware
{
    public function __construct(
        private PDO $pdo,
        private int $maxTokens = 10,
        private int $refillIntervalSec = 60
    ) {}

    public function allowRequest(string $clientIp): bool
    {
        $now = time();
        $stmt = $this->pdo->prepare("SELECT tokens, last_updated FROM rate_limits WHERE key = :key");
        $stmt->execute(["key" => $clientIp]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $insert = $this->pdo->prepare("INSERT INTO rate_limits (key, tokens, last_updated) VALUES (:key, :tokens, :updated)");
            $insert->execute(["key" => $clientIp, "tokens" => $this->maxTokens - 1, "updated" => $now]);
            return true;
        }

        /** @var array{tokens: float|int|string, last_updated: int|string} $row */
        $tokens = (float) $row["tokens"];
        $lastUpdated = (int) $row["last_updated"];

        $elapsed = $now - $lastUpdated;
        $tokens = min($this->maxTokens, $tokens + ($elapsed * ($this->maxTokens / $this->refillIntervalSec)));

        if ($tokens >= 1.0) {
            $update = $this->pdo->prepare("UPDATE rate_limits SET tokens = :tokens, last_updated = :updated WHERE key = :key");
            $update->execute(["key" => $clientIp, "tokens" => $tokens - 1.0, "updated" => $now]);
            return true;
        }

        return false;
    }
}
