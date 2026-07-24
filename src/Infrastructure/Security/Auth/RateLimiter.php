<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Security\Auth;

final class RateLimiter
{
    private int $maxRequests;
    private int $decaySeconds;
    private string $storagePath;

    public function __construct(int $maxRequests = 60, int $decaySeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->decaySeconds = $decaySeconds;
        $this->storagePath = sys_get_temp_dir() . "/nap_rate_limit.json";
    }

    public function isAllowed(string $clientIp): bool
    {
        $now = time();
        $data = $this->loadStorage();

        $clientData = $data[$clientIp] ?? ["count" => 0, "reset" => $now + $this->decaySeconds];

        if ($now > $clientData["reset"]) {
            $clientData = ["count" => 1, "reset" => $now + $this->decaySeconds];
        } else {
            $clientData["count"]++;
        }

        $data[$clientIp] = $clientData;
        $this->saveStorage($data);

        return $clientData["count"] <= $this->maxRequests;
    }

    /**
     * @return array<string, array{count: int, reset: int}>
     */
    private function loadStorage(): array
    {
        if (!file_exists($this->storagePath)) {
            return [];
        }
        $raw = @file_get_contents($this->storagePath);
        if ($raw === false) {
            return [];
        }
        /** @var array<string, array{count: int, reset: int}>|null $decoded */
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, array{count: int, reset: int}> $data
     */
    private function saveStorage(array $data): void
    {
        @file_put_contents($this->storagePath, (string) json_encode($data), LOCK_EX);
    }
}
