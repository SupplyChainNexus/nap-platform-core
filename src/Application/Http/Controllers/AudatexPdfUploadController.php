<?php

declare(strict_types=1);

namespace NAP\Application\Http\Controllers;

use NAP\Application\Services\AudatexClaimParserService;
use Smalot\PdfParser\Parser;

final class AudatexPdfUploadController
{
    private AudatexClaimParserService $parserService;

    public function __construct(AudatexClaimParserService $parserService)
    {
        $this->parserService = $parserService;
    }

    /**
     * Handles uploaded Audatex PDF estimate documents.
     *
     * @param array<string, mixed> $files
     * @param array<string, mixed> $postData
     * @return array<string, mixed>
     */
    public function handlePdfUpload(array $files, array $postData): array
    {
        $uploadedFile = $files['pdf_file'] ?? null;
        if (!is_array($uploadedFile) || !isset($uploadedFile['tmp_name']) || !is_string($uploadedFile['tmp_name'])) {
            return [
                'status'  => 'error',
                'code'    => 400,
                'message' => 'No valid PDF file uploaded.'
            ];
        }

        $preferredInput = $postData['preferredSupplier'] ?? null;
        $preferredSupplier = is_string($preferredInput) ? $preferredInput : null;

        try {
            $pdfParser = new Parser();
            $pdf = $pdfParser->parseFile((string) $uploadedFile['tmp_name']);
            $extractedText = $pdf->getText();

            $result = $this->parserService->parseAndEvaluateClaim($extractedText, $preferredSupplier);

            return [
                'status'  => 'success',
                'code'    => 200,
                'message' => 'Audatex PDF document parsed and evaluated successfully.',
                'data'    => $result
            ];
        } catch (\Throwable $e) {
            return [
                'status'  => 'error',
                'code'    => 500,
                'message' => 'Failed to parse PDF text: ' . $e->getMessage()
            ];
        }
    }
}
