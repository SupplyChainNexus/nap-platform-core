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
    public function get(string $path, callable $handler): void
    {
        $this->routes["GET"][$path] = $handler;
    }

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
        $queryParams = [];
        $queryStr = (string) parse_url($uri, PHP_URL_QUERY);
        if ($queryStr !== "") {
            parse_str($queryStr, $queryParams);
        }

        $method = strtoupper($method);

        if (isset($this->routes[$method][$path])) {
            /** @var array<string, mixed> $mergedPayload */
            $mergedPayload = array_merge($payload, $queryParams);
            return ($this->routes[$method][$path])($mergedPayload);
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
