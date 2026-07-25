<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Logging;

final class JsonLogger
{
    /** @var array<int, array<string, mixed>> */
    private array $logs = [];
    private string $correlationId;

    public function __construct(?string $correlationId = null)
    {
        $this->correlationId = $correlationId ?? "corr-" . bin2hex(random_bytes(8));
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    /**
     * @param string $level
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $this->logs[] = [
            "timestamp" => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            "correlationId" => $this->correlationId,
            "level" => strtoupper($level),
            "message" => $message,
            "context" => $context
        ];
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log("INFO", $message, $context);
    }

    /**
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log("ERROR", $message, $context);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    public function clear(): void
    {
        $this->logs = [];
    }
}