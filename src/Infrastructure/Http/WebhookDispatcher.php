<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http;

final readonly class WebhookDispatcher
{
    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(string $endpointUrl, array $payload): bool
    {
        $ch = curl_init($endpointUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $result !== false && $status >= 200 && $status < 300;
    }
}
