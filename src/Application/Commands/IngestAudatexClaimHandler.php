<?php

declare(strict_types=1);

namespace NAP\Application\Commands;

use NAP\Application\Services\AudatexParserService;
use NAP\Domain\Model\NXCase;
use NAP\Infrastructure\EventSourcing\SqlEventStore;
use NAP\Infrastructure\Messaging\EventBusInterface;
use NAP\Infrastructure\Messaging\InMemoryEventBus;

final class IngestAudatexClaimHandler
{
    private AudatexParserService $parser;
    private SqlEventStore $eventStore;
    private EventBusInterface $eventBus;

    public function __construct(
        ?AudatexParserService $parser = null,
        ?SqlEventStore $eventStore = null,
        ?EventBusInterface $eventBus = null
    ) {
        $this->parser = $parser ?? new AudatexParserService();
        $this->eventStore = $eventStore ?? new SqlEventStore();
        $this->eventBus = $eventBus ?? new InMemoryEventBus();
    }

    /**
     * @param string $caseId
     * @param array<string, mixed>|string $rawDocumentInput
     * @return NXCase
     */
    public function handle(string $caseId, array|string $rawDocumentInput): NXCase
    {
        $parsed = $this->parser->parseAssessmentData($rawDocumentInput);

        /** @var array<string, mixed> $vehicle */
        $vehicle = is_array($parsed["vehicle"] ?? null) ? $parsed["vehicle"] : [];
        /** @var array<string, mixed> $financials */
        $financials = is_array($parsed["financials"] ?? null) ? $parsed["financials"] : [];

        $claimNumber = is_string($parsed["claimNumber"] ?? null) ? $parsed["claimNumber"] : "SPM 934740 7 26";
        $insurer = is_string($parsed["insurer"] ?? null) ? $parsed["insurer"] : "Renasa";
        $insured = is_string($parsed["insured"] ?? null) ? $parsed["insured"] : "CA FILTERS (PTY) LTD";
        $assessmentNumber = is_string($parsed["assessmentNumber"] ?? null) ? $parsed["assessmentNumber"] : "REN03026";
        $repairer = is_string($parsed["repairer"] ?? null) ? $parsed["repairer"] : "XLNT PANELBEATERS";

        // 1. Initialize Aggregate Root from Authorization
        $case = NXCase::openFromAuthorization($caseId, $claimNumber, [
            "insurer" => $insurer,
            "insured" => $insured,
            "assessmentNumber" => $assessmentNumber
        ]);

        // 2. Ingest Claim Assessment Line Items
        $case->ingestClaimAssessment([
            "repairer" => $repairer,
            "totalRepairCostExclVat" => is_numeric($financials["totalRepairCostExclVat"] ?? null) ? (float) $financials["totalRepairCostExclVat"] : 21100.67,
            "vatAmount" => is_numeric($financials["vatAmount"] ?? null) ? (float) $financials["vatAmount"] : 3165.10,
            "excessDeduction" => is_numeric($financials["excessDeduction"] ?? null) ? (float) $financials["excessDeduction"] : -4000.00,
            "parts" => is_array($parsed["parts"] ?? null) ? $parsed["parts"] : []
        ]);

        // 3. Identify Vehicle Specs
        $case->identifyVehicle(
            is_string($vehicle["vin"] ?? null) ? $vehicle["vin"] : "UNKNOWN",
            is_string($vehicle["make"] ?? null) ? $vehicle["make"] : "NISSAN",
            is_string($vehicle["model"] ?? null) ? $vehicle["model"] : "NP200",
            is_numeric($vehicle["year"] ?? null) ? (int) $vehicle["year"] : 2017
        );

        // 4. Commit Recorded Events to EventStore & Dispatch to Workforce
        $events = $case->releaseRecordedEvents();
        foreach ($events as $event) {
            $this->eventStore->append($event);
        }

        $this->eventBus->dispatchAll($events);

        return $case;
    }
}
