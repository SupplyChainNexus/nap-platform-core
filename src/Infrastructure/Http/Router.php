<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http;

final class Router
{
    /** @var array<string, array<string, callable(array<string, mixed>): array{status_code: int, body: array<string, mixed>}>> */
    private array $routes = [];

    /**
     * @param callable(array<string, mixed>): array{status_code: int, body: array<string, mixed>} $handler
     */
    public function post(string $path, callable $handler): void
    {
        $this->routes["POST"][$path] = $handler;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status_code: int, body: array<string, mixed>}
     */
    public function dispatch(string $method, string $uri, array $payload = []): array
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $method = strtoupper($method);

        if (isset($this->routes[$method][$path])) {
            return ($this->routes[$method][$path])($payload);
        }

        return [
            "status_code" => 404,
            "body" => [
                "status" => "error",
                "error" => sprintf("Route %s %s not found.", $method, $path),
            ],
        ];
    }
}
