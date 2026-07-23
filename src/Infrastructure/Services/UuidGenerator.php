<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Services;

use NAP\SharedKernel\Domain\Contracts\IdGeneratorInterface;

final readonly class UuidGenerator implements IdGeneratorInterface
{
    public function generate(): string
    {
        $time = (int) (microtime(true) * 1000);
        $timeHex = str_pad(dechex($time), 12, '0', STR_PAD_LEFT);
        
        $bytes = random_bytes(10);
        $bytes[0] = chr((ord($bytes[0]) & 0x0f) | 0x70); // UUIDv7
        $bytes[2] = chr((ord($bytes[2]) & 0x3f) | 0x80); // Variant 1

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($timeHex, 0, 8),
            substr($timeHex, 8, 4),
            bin2hex(substr($bytes, 0, 2)),
            bin2hex(substr($bytes, 2, 2)),
            bin2hex(substr($bytes, 4, 6))
        );
    }
}