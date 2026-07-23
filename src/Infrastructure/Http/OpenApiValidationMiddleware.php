<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http;

final readonly class OpenApiValidationMiddleware
{
    /**
     * @param array<string, mixed> $payload
     * @return array{valid: bool, errors: list<string>}
     */
    public function validatePricingAnalyzePayload(array $payload): array
    {
        $errors = [];
        $schemaPath = __DIR__ . "/../../../resources/docs/openapi.json";
        if (file_exists($schemaPath)) {
            $specContent = (string) file_get_contents($schemaPath);
            /** @var array<string, mixed> $spec */
            $spec = (array) json_decode($specContent, true);
            /** @var array<string, mixed> $components */
            $components = (array) ($spec["components"] ?? []);
            /** @var array<string, mixed> $schemas */
            $schemas = (array) ($components["schemas"] ?? []);
            /** @var array<string, mixed> $requestSchema */
            $requestSchema = (array) ($schemas["PricingAnalyzeRequest"] ?? []);
            /** @var list<string> $required */
            $required = (array) ($requestSchema["required"] ?? ["partNumber", "amountInCents", "currency"]);

            foreach ($required as $field) {
                if (!array_key_exists($field, $payload) || $payload[$field] === "" || $payload[$field] === null) {
                    $errors[] = sprintf("Schema Violation: missing required field '%s' according to openapi.json spec.", $field);
                }
            }
        }

        if (isset($payload["amountInCents"]) && (!is_int($payload["amountInCents"]) || $payload["amountInCents"] <= 0)) {
            $errors[] = "The field 'amountInCents' must be a positive integer.";
        }

        if (isset($payload["currency"]) && (!is_string($payload["currency"]) || strlen($payload["currency"]) !== 3)) {
            $errors[] = "The field 'currency' must be a 3-letter ISO code.";
        }

        return [
            "valid" => count($errors) === 0,
            "errors" => $errors,
        ];
    }
}
