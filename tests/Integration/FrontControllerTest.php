<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class FrontControllerTest extends TestCase
{
    public function testPublicIndexHtmlFileExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../../public/index.html');
        $this->assertFileExists(__DIR__ . '/../../public/index.php');
    }

    public function testIndexHtmlContainsDashboardTitles(): void
    {
        $content = (string) file_get_contents(__DIR__ . '/../../public/index.html');
        $this->assertStringContainsString("NAP Platform Core Operations", $content);
        $this->assertStringContainsString("Ingest Audatex Claim Webhook", $content);
    }
}