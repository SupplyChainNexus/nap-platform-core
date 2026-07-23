<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Console;

use NAP\Application\Commands\CreateCaseCommand as AppCreateCaseCommand;
use NAP\Application\Handlers\CreateCaseHandler;

final readonly class CreateCaseConsoleCommand
{
    public function __construct(
        private CreateCaseHandler $handler
    ) {}

    /**
     * @return array{status: string, case_id: string}
     */
    public function execute(string $businessContext): array
    {
        $command = new AppCreateCaseCommand($businessContext);
        $caseId = $this->handler->handle($command);

        return [
            "status" => "success",
            "case_id" => $caseId->value(),
        ];
    }
}
