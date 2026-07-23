<?php

declare(strict_types=1);

namespace NAP\SharedKernel;

final class EnvironmentLoader
{
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, "#") || !str_contains($line, "=")) {
                continue;
            }
            [$name, $value] = explode("=", $line, 2);
            $name = trim($name);
            $value = trim($value, " \"'");
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf("%s=%s", $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}
