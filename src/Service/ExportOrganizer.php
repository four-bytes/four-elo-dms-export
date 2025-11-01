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
        $fileName = $path['filename'];
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
