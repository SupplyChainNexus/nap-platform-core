<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NAP\Application\Services\CatalogScraperAgent;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use NAP\Infrastructure\Persistence\PartCrossReferenceRepository;

// Disable execution time limit for daemon execution
set_time_limit(0);

$pdo = new PDO("sqlite:" . __DIR__ . "/../database.sqlite");
$db = new DatabaseAdapter($pdo);
$repository = new PartCrossReferenceRepository($db);
$agent = new CatalogScraperAgent($repository);

echo "====================================================\n";
echo "?? 24/7 Automotive Cross-Reference Scraper Agent Live\n";
echo "====================================================\n";

$passCount = 0;
$sleepIntervalSeconds = 5; // Adjust loop interval to prevent rate-limiting

while (true) {
    $passCount++;
    $timestamp = date("Y-m-d H:i:s");
    echo "[{$timestamp}] [Pass #{$passCount}] Executing catalog scrape pass...\n";

    try {
        $stats = $agent->runScrapePass();
        echo "  +- Records Scraped: {$stats['scrapedCount']} | Upserted: {$stats['addedCount']}\n";
    } catch (\Throwable $e) {
        echo "  +- [ERROR] Scrape cycle failed: " . $e->getMessage() . "\n";
    }

    // Gentle sleep interval between cycles
    sleep($sleepIntervalSeconds);
}
