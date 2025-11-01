<?php

declare(strict_types=1);

namespace Four\Elo\Service;

use Imagick;
use ImagickException;

/**
 * Converts images (TIF, JPG, PNG) to PDF using Imagick
 */
class ImageConverter
{
    private string $tempDir;

    public function __construct(?string $tempDir = null)
    {
        if (!extension_loaded('imagick')) {
            throw new \RuntimeException('Imagick extension is not available');
        }

        $this->tempDir = $tempDir ?? sys_get_temp_dir();

        if (!is_writable($this->tempDir)) {
            throw new \RuntimeException("Temp directory is not writable: {$this->tempDir}");
        }
    }

    /**
     * Convert image file to PDF
     *
     * @param string $sourcePath Path to image file (TIF, JPG, PNG)
     * @param string|null $outputPath Optional output path (defaults to temp file)
     * @return string Path to generated PDF file
     * @throws ImagickException
     */
    public function convertToPdf(string $sourcePath, ?string $outputPath = null): string
    {
        if (!file_exists($sourcePath)) {
            throw new \InvalidArgumentException("Source file not found: {$sourcePath}");
        }

        // Generate output path if not provided
        if ($outputPath === null) {
            $outputPath = $this->tempDir . '/' . uniqid('elo_pdf_', true) . '.pdf';
        }

        try {
            $imagick = new Imagick();

            // Read source file (supports multi-page TIFFs)
            $imagick->readImage($sourcePath);

            // Set PDF format
            $imagick->setImageFormat('pdf');

            // Optimize for file size
            $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality(85);

            // Write PDF file
            $imagick->writeImages($outputPath, true);
            $imagick->clear();
            $imagick->destroy();

            return $outputPath;

        } catch (ImagickException $e) {
            throw new \RuntimeException(
                "Failed to convert image to PDF: {$sourcePath}",
                0,
                $e
            );
        }
    }

    /**
     * Check if file format is supported
     */
    public function isSupported(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, ['tif', 'tiff', 'jpg', 'jpeg', 'png', 'gif'], true);
    }

    /**
     * Get image information
     *
     * @return array{width: int, height: int, pages: int, format: string}
     */
    public function getImageInfo(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        try {
            $imagick = new Imagick($filePath);

            $info = [
                'width' => $imagick->getImageWidth(),
                'height' => $imagick->getImageHeight(),
                'pages' => $imagick->getNumberImages(),
                'format' => $imagick->getImageFormat(),
            ];

            $imagick->clear();
            $imagick->destroy();

            return $info;

        } catch (ImagickException $e) {
            throw new \RuntimeException(
                "Failed to read image info: {$filePath}",
                0,
                $e
            );
        }
    }

    /**
     * Batch convert multiple images to single multi-page PDF
     *
     * @param string[] $sourcePaths Array of image file paths
     * @param string $outputPath Output PDF path
     * @return string Path to generated PDF
     */
    public function convertMultipleToPdf(array $sourcePaths, string $outputPath): string
    {
        if (empty($sourcePaths)) {
            throw new \InvalidArgumentException('No source files provided');
        }

        try {
            $imagick = new Imagick();

            foreach ($sourcePaths as $sourcePath) {
                if (!file_exists($sourcePath)) {
                    throw new \InvalidArgumentException("Source file not found: {$sourcePath}");
                }
                $imagick->readImage($sourcePath);
            }

            $imagick->setImageFormat('pdf');
            $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality(85);

            $imagick->writeImages($outputPath, true);
            $imagick->clear();
            $imagick->destroy();

            return $outputPath;

        } catch (ImagickException $e) {
            throw new \RuntimeException(
                'Failed to convert multiple images to PDF',
                0,
                $e
            );
        }
    }
}
