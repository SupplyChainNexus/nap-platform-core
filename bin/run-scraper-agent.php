<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NAP\Application\Services\CatalogScraperAgent;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use NAP\Infrastructure\Persistence\PartCrossReferenceRepository;

$pdo = new PDO("sqlite:" . __DIR__ . "/../database.sqlite");
$db = new DatabaseAdapter($pdo);
$repository = new PartCrossReferenceRepository($db);
$agent = new CatalogScraperAgent($repository);

echo "?? Launching Automotive OEM & Alternative Part Scraper Agent...\n";
$stats = $agent->runScrapePass();

echo "? Scraping Pass Complete:\n";
echo "   - Total Records Scraped: {$stats['scrapedCount']}\n";
echo "   - Cross-References Upserted: {$stats['addedCount']}\n";
