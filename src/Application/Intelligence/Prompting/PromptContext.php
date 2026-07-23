<?php

declare(strict_types=1);

namespace NAP\Application\Intelligence\Prompting;

final readonly class PromptContext
{
    /**
     * @param array<string, mixed> $variables
     */
    public function __construct(
        public string $templateName,
        public array $variables = []
    ) {}
}
