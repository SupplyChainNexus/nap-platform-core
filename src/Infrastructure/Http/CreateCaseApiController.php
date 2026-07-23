<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http;

use NAP\Application\Commands\CreateCaseCommand;
use NAP\Application\Handlers\CreateCaseHandler;
use InvalidArgumentException;

final readonly class CreateCaseApiController
{
    public function __construct(
        private CreateCaseHandler $handler
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @return array{status_code: int, body: array{status: string, case_id?: string, error?: string}}
     */
    public function handle(array $payload): array
    {
        $businessContext = $payload["businessContext"] ?? null;

        if (!is_string($businessContext) || trim($businessContext) === "") {
            return [
                "status_code" => 400,
                "body" => ["status" => "error", "error" => "Missing or invalid businessContext parameter."],
            ];
        }

        try {
            $command = new CreateCaseCommand($businessContext);
            $caseId = $this->handler->handle($command);

            return [
                "status_code" => 201,
                "body" => [
                    "status" => "success",
                    "case_id" => $caseId->value(),
                ],
            ];
        } catch (InvalidArgumentException $e) {
            return [
                "status_code" => 422,
                "body" => ["status" => "error", "error" => $e->getMessage()],
            ];
        }
    }
}
