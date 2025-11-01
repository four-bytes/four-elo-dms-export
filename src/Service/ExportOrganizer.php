<?php

declare(strict_types=1);

namespace Four\Elo\Service;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Organizes exported documents into Nextcloud-ready folder structure
 */
class ExportOrganizer
{
    private Filesystem $filesystem;
    private string $outputPath;
    private array $processedDocuments = [];

    public function __construct(string $outputPath)
    {
        $this->filesystem = new Filesystem();
        $this->outputPath = rtrim($outputPath, '/');
    }

    /**
     * Initialize output directory structure
     */
    public function initialize(): void
    {
        $this->filesystem->mkdir([
            $this->outputPath,
            $this->outputPath . '/documents',
            $this->outputPath . '/metadata',
        ]);
    }

    /**
     * Add document to export
     *
     * @param array<string, mixed> $metadata Document metadata from database
     * @param string $pdfPath Path to converted PDF file
     */
    public function addDocument(array $metadata, string $pdfPath): string
    {
        // Sanitize filename from objshort
        $filename = $this->sanitizeFilename($metadata['objshort'] ?? 'document');
        $filename .= '.pdf';

        // Use folder path from ELO hierarchy
        $targetDir = $this->outputPath . '/documents';

        if (!empty($metadata['folder_path'])) {
            $targetDir .= '/' . $metadata['folder_path'];
        }

        // Create target directory
        $this->filesystem->mkdir($targetDir);

        // Generate unique filename if needed
        $targetPath = $targetDir . '/' . $filename;
        $counter = 1;
        while (file_exists($targetPath)) {
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            $targetPath = $targetDir . '/' . $baseName . "_{$counter}.pdf";
            $counter++;
        }

        // Copy PDF to target location
        $this->filesystem->copy($pdfPath, $targetPath);

        // Track processed document
        $this->processedDocuments[] = array_merge($metadata, [
            'export_path' => $targetPath,
            'export_date' => date('Y-m-d H:i:s'),
        ]);

        return $targetPath;
    }

    /**
     * Export metadata to CSV file
     *
     * @param array<array<string, mixed>> $documents All documents metadata
     */
    public function exportMetadata(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $csvPath = $this->outputPath . '/metadata/documents.csv';

        $fp = fopen($csvPath, 'w');
        if ($fp === false) {
            throw new \RuntimeException("Failed to create CSV file: {$csvPath}");
        }

        // Write header
        $headers = array_keys($documents[0]);
        fputcsv($fp, $headers);

        // Write data
        foreach ($documents as $document) {
            fputcsv($fp, $document);
        }

        fclose($fp);
    }

    /**
     * Export summary JSON
     */
    public function exportSummary(array $stats): void
    {
        $summaryPath = $this->outputPath . '/metadata/export-report.json';

        $summary = [
            'export_date' => date('c'),
            'total_documents' => count($this->processedDocuments),
            'statistics' => $stats,
            'documents' => $this->processedDocuments,
        ];

        $this->filesystem->dumpFile(
            $summaryPath,
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Sanitize filename according to ELO export rules
     * - Remove invalid characters
     * - Replace / with -
     * - Replace other special chars with single space
     */
    private function sanitizeFilename(string $filename): string
    {
        // Replace forward slash with dash
        $filename = str_replace('/', '-', $filename);

        // Remove or replace invalid filename characters
        $filename = preg_replace('/[\\\\:*?"<>|]/', '', $filename);

        // Replace multiple spaces/underscores with single space
        $filename = preg_replace('/[\s_]+/', ' ', $filename);

        // Trim and limit length
        $filename = trim($filename);
        if (mb_strlen($filename) > 200) {
            $filename = mb_substr($filename, 0, 200);
        }

        return $filename ?: 'untitled';
    }

    /**
     * Parse ELO date format (assumed to be YYYYMMDD or timestamp)
     */
    private function parseEloDate(string $dateStr): ?\DateTime
    {
        // Try YYYYMMDD format first
        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $dateStr, $matches)) {
            try {
                return new \DateTime("{$matches[1]}-{$matches[2]}-{$matches[3]}");
            } catch (\Exception $e) {
                // Invalid date
            }
        }

        // Try timestamp
        if (is_numeric($dateStr)) {
            try {
                return new \DateTime('@' . $dateStr);
            } catch (\Exception $e) {
                // Invalid timestamp
            }
        }

        return null;
    }

    /**
     * Get processed documents
     */
    public function getProcessedDocuments(): array
    {
        return $this->processedDocuments;
    }
}
