<?php

declare(strict_types=1);

namespace Four\Elo\Service;

use Imagick;
use ImagickException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Converts images (TIF, JPG, PNG) to PDF using Imagick
 */
class ImageConverter
{
    /**
     * @param string|null $tempDir
     */
    public function __construct(?string $tempDir = null)
    {
        if (!extension_loaded('imagick')) {
            throw new RuntimeException('Imagick extension is not available');
        }
    }

    /**
     * Convert image file to PDF
     *
     * @param string $sourcePath Path to image file (TIF, JPG, PNG)
     * @param string $destinationPath Path to generated PDF file
     * @return bool
     */
    public function convertToPdf(string $sourcePath, string $destinationPath): bool
    {
        if (!file_exists($sourcePath)) {
            throw new InvalidArgumentException("Source file not found: {$sourcePath}");
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
            $result = $imagick->writeImages($destinationPath, true);
            $imagick->clear();

            return $result;

        } catch (ImagickException $e) {
            throw new RuntimeException(
                "Failed to convert image to PDF: {$sourcePath}",
                0,
                $e
            );
        }
    }

    /**
     * Check if file format is supported
     * @param string $extension
     * @return bool
     */
    public function isSupportedFormat(string $extension): bool
    {
        return in_array(
            strtolower($extension),
            ['tif', 'tiff', 'jpg', 'jpeg', 'png', 'gif'], true);
    }
}
