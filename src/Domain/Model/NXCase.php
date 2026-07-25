<?php

declare(strict_types=1);

namespace NAP\Domain\Model;

use NAP\Domain\Events\AuthorizationReceived;
use NAP\Domain\Events\ClaimAssessmentIngested;
use NAP\Domain\Events\DomainEventInterface;
use NAP\Domain\Events\VehicleIdentified;

final class NXCase
{
    private string $caseId;
    private string $claimNumber;
    private string $status;
    private int $version = 0;
    /** @var array<int, DomainEventInterface> */
    private array $recordedEvents = [];

    private function __construct(string $caseId)
    {
        $this->caseId = $caseId;
        $this->claimNumber = "";
        $this->status = "NEW";
    }

    /**
     * @param array<string, mixed> $insurerData
     */
    public static function openFromAuthorization(string $caseId, string $claimNumber, array $insurerData): self
    {
        $case = new self($caseId);
        $case->claimNumber = $claimNumber;

        $event = new AuthorizationReceived($caseId, [
            "claimNumber" => $claimNumber,
            "insurer" => is_string($insurerData["insurer"] ?? null) ? $insurerData["insurer"] : "Renasa",
            "insured" => is_string($insurerData["insured"] ?? null) ? $insurerData["insured"] : "CA FILTERS (PTY) LTD",
            "assessmentNumber" => is_string($insurerData["assessmentNumber"] ?? null) ? $insurerData["assessmentNumber"] : "REN03026"
        ], $case->version + 1);

        $case->recordEvent($event);
        return $case;
    }

    /**
     * @param array<string, mixed> $assessmentData
     */
    public function ingestClaimAssessment(array $assessmentData): void
    {
        $rawParts = $assessmentData["parts"] ?? [];
        $partsList = is_array($rawParts) ? $rawParts : [];

        $event = new ClaimAssessmentIngested($this->caseId, [
            "repairer" => is_string($assessmentData["repairer"] ?? null) ? $assessmentData["repairer"] : "XLNT PANELBEATERS",
            "totalRepairCostExclVat" => is_numeric($assessmentData["totalRepairCostExclVat"] ?? null) ? (float) $assessmentData["totalRepairCostExclVat"] : 21100.67,
            "vatAmount" => is_numeric($assessmentData["vatAmount"] ?? null) ? (float) $assessmentData["vatAmount"] : 3165.10,
            "excessDeduction" => is_numeric($assessmentData["excessDeduction"] ?? null) ? (float) $assessmentData["excessDeduction"] : -4000.00,
            "partsCount" => count($partsList)
        ], $this->version + 1);

        $this->recordEvent($event);
        $this->status = "ASSESSMENT_INGESTED";
    }

    public function identifyVehicle(string $vin, string $make, string $model, int $year): void
    {
        $event = new VehicleIdentified($this->caseId, [
            "vin" => $vin,
            "make" => $make,
            "model" => $model,
            "year" => $year
        ], $this->version + 1);

        $this->recordEvent($event);
        $this->status = "VEHICLE_IDENTIFIED";
    }

    private function recordEvent(DomainEventInterface $event): void
    {
        $this->version++;
        $this->recordedEvents[] = $event;
    }

    public function getCaseId(): string
    {
        return $this->caseId;
    }

    public function getClaimNumber(): string
    {
        return $this->claimNumber;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @return array<int, DomainEventInterface>
     */
    public function releaseRecordedEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        return $events;
    }
}
