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

    /**
     * @param string $outputPath
     */
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
        // Create output directory
        $this->filesystem->mkdir($this->outputPath);
    }

    /**
     * Add document to export
     * @param stdClass $document
     * @param string $pdfRelativePath
     * @param string $pdfContent
     * @return string
     */
    public function addDocument(stdClass $document, string $pdfRelativePath, string $pdfContent): string
    {
        // Create target directory
        $path = pathinfo($pdfRelativePath);
        $targetDir = $this->outputPath . '/' . $path['dirname'] . '/';
        $this->filesystem->mkdir($targetDir);

        // Generate unique filename if needed
        $pdfPath = $targetDir . $path['filename'] . ".pdf";
        $counter = 1;
        while (file_exists($pdfPath)) {
            $pdfPath = $targetDir . $path['filename'] . "_$counter.pdf";
            $counter++;
        }

        // Write PDF to target location
        $this->filesystem->dumpFile($pdfPath, $pdfContent);

        return $pdfPath;
    }
}
