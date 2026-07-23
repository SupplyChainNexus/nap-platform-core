<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class BinNapTest extends TestCase
{
    public function testBinNapHelpMenuOutputsZeroExitCode(): void
    {
        $output = [];
        $exitCode = 0;
        exec("php -d extension=pdo_sqlite bin/nap help", $output, $exitCode);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString("NAP Platform Core CLI Driver", implode("\n", $output));
    }
}
