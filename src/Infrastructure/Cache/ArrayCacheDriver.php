<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Cache;

final class ArrayCacheDriver implements CacheInterface
{
    /** @var array<string, array{value: mixed, expiresAt: int}> */
    private array $storage = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->storage[$key])) {
            return $default;
        }

        $item = $this->storage[$key];
        if (time() >= $item["expiresAt"]) {
            unset($this->storage[$key]);
            return $default;
        }

        return $item["value"];
    }

    public function set(string $key, mixed $value, int $ttl = 300): bool
    {
        $this->storage[$key] = [
            "value" => $value,
            "expiresAt" => time() + $ttl
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->storage[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->storage = [];
        return true;
    }

    public function count(): int
    {
        return count($this->storage);
    }
}