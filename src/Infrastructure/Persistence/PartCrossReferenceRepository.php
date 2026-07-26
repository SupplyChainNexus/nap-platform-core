<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Persistence;

final class PartCrossReferenceRepository
{
    private DatabaseAdapter $db;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
        $this->initializeSchema();
    }

    /**
     * Initializes the cross-reference schema.
     */
    public function initializeSchema(): void
    {
        $pdo = $this->db->getPdo();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nx_part_cross_references (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                oem_part_number TEXT NOT NULL,
                supplier_part_number TEXT NOT NULL,
                brand_name TEXT NOT NULL,
                last_quoted_price REAL NOT NULL,
                occurrence_count INTEGER DEFAULT 1,
                updated_at TEXT NOT NULL,
                UNIQUE(oem_part_number, supplier_part_number, brand_name)
            );

            CREATE INDEX IF NOT EXISTS idx_oem_part ON nx_part_cross_references(oem_part_number);
        ");
    }

    /**
     * Records or increments a cross-reference mapping.
     */
    public function recordMapping(
        string $oemPartNumber,
        string $supplierPartNumber,
        string $brandName,
        float $lastQuotedPrice
    ): void {
        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare("
            INSERT INTO nx_part_cross_references 
                (oem_part_number, supplier_part_number, brand_name, last_quoted_price, occurrence_count, updated_at)
            VALUES 
                (:oem, :supplier_part, :brand, :price, 1, :updated_at)
            ON CONFLICT(oem_part_number, supplier_part_number, brand_name) DO UPDATE SET
                last_quoted_price = :price,
                occurrence_count = occurrence_count + 1,
                updated_at = :updated_at
        ");

        $stmt->execute([
            ":oem" => strtoupper(trim($oemPartNumber)),
            ":supplier_part" => strtoupper(trim($supplierPartNumber)),
            ":brand" => trim($brandName),
            ":price" => $lastQuotedPrice,
            ":updated_at" => date("c")
        ]);
    }

    /**
     * Looks up all registered alternative supplier parts for a target OEM part number.
     *
     * @param string $oemPartNumber
     * @return array<int, array{supplierPartNumber: string, brandName: string, lastQuotedPrice: float, occurrenceCount: int, updatedAt: string}>
     */
    public function findAlternativesForOem(string $oemPartNumber): array
    {
        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare("
            SELECT supplier_part_number, brand_name, last_quoted_price, occurrence_count, updated_at
            FROM nx_part_cross_references
            WHERE oem_part_number = :oem
            ORDER BY occurrence_count DESC, last_quoted_price ASC
        ");

        $stmt->execute([":oem" => strtoupper(trim($oemPartNumber))]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $results = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $results[] = [
                        "supplierPartNumber" => (string) ($row["supplier_part_number"] ?? ""),
                        "brandName" => (string) ($row["brand_name"] ?? ""),
                        "lastQuotedPrice" => (float) ($row["last_quoted_price"] ?? 0.0),
                        "occurrenceCount" => (int) ($row["occurrence_count"] ?? 0),
                        "updatedAt" => (string) ($row["updated_at"] ?? "")
                    ];
                }
            }
        }

        return $results;
    }
}
