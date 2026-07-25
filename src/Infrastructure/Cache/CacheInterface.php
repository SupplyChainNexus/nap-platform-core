<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Cache;

interface CacheInterface
{
    /**
     * Fetches an item from the cache by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Stores an item in the cache with a specific TTL in seconds.
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    public function set(string $key, mixed $value, int $ttl = 300): bool;

    /**
     * Deletes an item from the cache by key.
     *
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool;

    /**
     * Clears all stored cache items.
     *
     * @return bool
     */
    public function clear(): bool;
}