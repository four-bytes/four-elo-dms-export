<?php

declare(strict_types=1);

namespace Four\Elo\Service;

use stdClass;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Organizes exported documents into Nextcloud-ready folder structure
 */
class ExportOrganizer
{
    private Filesystem $filesystem;
    private string $outputPath;
    private ?array $exportedIds = null;
    private string $exportedIdsFile;

    /**
     * @param string $outputPath
     */
    public function __construct(string $outputPath)
    {
        $this->filesystem = new Filesystem();
        $this->outputPath = rtrim($outputPath, '/');
        $this->exportedIdsFile = $this->outputPath . '/exported_ids.txt';
    }

    /**
     * Initialize output directory structure
     */
    public function initialize(): void
    {
        // Create output directory
        $this->filesystem->mkdir($this->outputPath);
    }

    /**
     * Get exported IDs (lazy load from file on first access)
     * @return string[]
     */
    private function getExportedIds(): array
    {
        if ($this->exportedIds === null) {
            if (file_exists($this->exportedIdsFile)) {
                $this->exportedIds = file($this->exportedIdsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            } else {
                $this->exportedIds = [];
            }
        }
        return $this->exportedIds;
    }

    /**
     * Check if an object ID has already been exported
     * @param int $objid
     * @return bool
     */
    public function isExported(int $objid): bool
    {
        return in_array((string)$objid, $this->getExportedIds(), true);
    }

    /**
     * Mark an object ID as exported
     * @param int $objid
     */
    public function markExported(int $objid): void
    {
        if (!$this->isExported($objid)) {
            $this->exportedIds[] = (string)$objid;
            // Append immediately to file for crash recovery (fast!)
            file_put_contents($this->exportedIdsFile, "$objid\n", FILE_APPEND);
        }
    }

    /**
     * Get count of already exported IDs
     * @return int
     */
    public function getExportedCount(): int
    {
        return count($this->getExportedIds());
    }

    /**
     * Add file to export
     * @param string $sourcePath
     * @param string $relativePath
     * @return string
     */
    public function addFile(string $sourcePath, string $relativePath): string
    {
        // Create the target directory
        $path = pathinfo($relativePath);
        $targetDir = $this->outputPath . '/' . $path['dirname'] . '/';
        $this->filesystem->mkdir($targetDir);

        // File parts
        $fileName = $path['basename']; // include possible dots, relativePath has no extension
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        // Check if conversion is supported
        $imageConverter = new ImageConverter();
        if ($imageConverter->isSupportedFormat($extension)) {
            // Convert to PDF
            $extension = 'pdf';
            $targetPath = $this->generateUniqueFilename($targetDir, $fileName, $extension);
            $imageConverter->convertToPdf($sourcePath, $targetPath);
        } else {
            // Copy file
            $targetPath = $this->generateUniqueFilename($targetDir, $fileName, $extension);
            $this->filesystem->copy($sourcePath, $targetPath);
        }

        // Return real unique target path
        return $targetPath;
    }

    /**
     * @param string $dir
     * @param string $fileName
     * @param string $extension
     * @return string
     */
    public function generateUniqueFilename(string $dir, string $fileName, string $extension): string
    {
        $dir = rtrim($dir, '/');
        $path = "$dir/$fileName.$extension";
        $counter = 0;
        while (file_exists($path)) {
            $counter++;
            $path = "$dir/{$fileName}_$counter.$extension";
        }
        return $path;
    }
}
