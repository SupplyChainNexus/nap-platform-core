<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use NAP\Application\Commands\CreateCaseCommand;
use NAP\Application\UseCase\CreateCaseHandler;
use NAP\Infrastructure\Persistence\InMemoryCaseRepository;

$repository = new InMemoryCaseRepository();

$handler = new CreateCaseHandler($repository);

$command = new CreateCaseCommand(
    '018f4d7d-89ab-7c35-91f2-123456789abc'
);

$case = $handler->handle($command);

echo "Case created successfully!" . PHP_EOL;
echo "Case ID: " . $case->caseId()->value() . PHP_EOL;