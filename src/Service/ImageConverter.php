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
     * @return string Path to generated PDF file
     */
    public function convertToPdf(string $sourcePath): string
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
            $content = (string)$imagick;
            $imagick->clear();

            return $content;

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
     * @param string $filePath
     * @return bool
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
            throw new InvalidArgumentException("File not found: {$filePath}");
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
            throw new RuntimeException(
                "Failed to read image info: {$filePath}",
                0,
                $e
            );
        }
    }
}
