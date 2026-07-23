<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Logging;

final readonly class StructuredFileLogger implements LoggerInterface
{
    public function __construct(
        private string $logFilePath
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log("INFO", $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log("ERROR", $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context): void
    {
        $dir = dirname($this->logFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $record = [
            "timestamp" => date("c"),
            "level" => $level,
            "message" => $message,
            "context" => $context,
        ];

        file_put_contents($this->logFilePath, json_encode($record) . PHP_EOL, FILE_APPEND);
    }
}
