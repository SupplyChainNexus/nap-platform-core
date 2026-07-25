<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ContainerConfigTest extends TestCase
{
    public function testDockerFilesAndCiWorkflowExist(): void
    {
        $this->assertFileExists(__DIR__ . '/../../Dockerfile');
        $this->assertFileExists(__DIR__ . '/../../docker-compose.yml');
        $this->assertFileExists(__DIR__ . '/../../.dockerignore');
        $this->assertFileExists(__DIR__ . '/../../.github/workflows/ci.yml');
    }

    public function testDockerfileContainsMultiStageBuild(): void
    {
        $dockerfile = (string) file_get_contents(__DIR__ . '/../../Dockerfile');
        $this->assertStringContainsString("FROM php:8.3-cli-alpine AS builder", $dockerfile);
        $this->assertStringContainsString("FROM php:8.3-fpm-alpine", $dockerfile);
    }
}