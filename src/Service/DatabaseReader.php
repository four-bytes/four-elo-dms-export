<?php

declare(strict_types=1);

namespace Four\Elo\Service;

use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Reads document metadata from ELO MDB database using mdb-json
 */
class DatabaseReader
{
    /** @var string */
    private string $databasePath;

    /** @var stdClass[]|null */
    private ?array $objects = null;

    /**
     * @param string $databasePath
     * @param string|null $customDsn
     */
    public function __construct(string $databasePath, ?string $customDsn = null)
    {
        if (!file_exists($databasePath)) {
            throw new InvalidArgumentException("Database file not found: {$databasePath}");
        }
        $this->databasePath = $databasePath;
    }

    /**
     * @return stdClass[]
     */
    public function getObjects(): array
    {
        if (!$this->objects) {
            // 1. Export objekte table using mdb-json (one JSON object per line)
            $cmd = sprintf('mdb-json %s objekte', escapeshellarg($this->databasePath));
            $process = popen($cmd, 'r');
            if (!$process) {
                throw new RuntimeException("Failed to execute mdb-json command");
            }

            // 2. Collect all objects
            $objects = [];
            while (($line = fgets($process)) !== false) {
                $record = json_decode($line, false);
                if (!$record) {
                    continue;
                }
                // Extend properties, objtype 9999 is root
                $record->isFolder = $record->objtype < 255;
                $record->isDocument = $record->objtype > 254 && $record->objtype < 9999;
                $record->isDeleted = ($record->objstatus ?? 0) != 0;
                // Add object to collection
                $objects[$record->objid] = $record;
            }

            // Close
            pclose($process);

            // Remember objects
            $this->objects = $objects;
        }
        return $this->objects;
    }


    /**
     * @param stdClass $obj
     * @return bool
     */
    private function isDocument(stdClass $obj): bool
    {
        return $obj->objtype > 254 && $obj->objstatus === 0;
    }

    /**
     * @return stdClass[]
     */
    public function getDocuments(): array
    {
        return array_filter($this->getObjects(), fn($obj) => $obj->isDocument && !$obj->isDeleted);
    }

    /**
     * @return stdClass[]
     */
    public function getFolders(): array
    {
        return array_filter($this->getObjects(), fn($obj) => $obj->isFolder && !$obj->isDeleted);
    }

    /**
     * @return int
     */
    public function getDocumentCount(): int
    {
        return count($this->getDocuments());
    }

    /**
     * @param stdClass $obj
     * @return string
     */
    public function createDocumentPath(stdClass $obj): string
    {
        $path = $this->createFolderPath($obj->objparent ?? 0);
        return $path . "/" . $this->sanitizeFileName($obj->objshort ?? 'document');
    }

    /**
     * @param int $folderId
     * @return string
     */
    public function createFolderPath(int $folderId): string
    {
        if ($folderId <= 1) {
            return "";
        }
        $folders = $this->getFolders();
        $folder = $folders[$folderId] ?? null;
        $path = "";
        while ($folder && $folder->objparent > 1) {
            $path = $this->sanitizeFileName($folder->objshort) . "/" . $path;
            $folder = $folders[$folder->objparent] ?? null;
        }
        return $path;
    }

    /**
     * Sanitize folder/file name according to ELO export rules
     * - Replace / with -
     * - Remove invalid characters
     * - Replace rest with single space
     * @param string $name
     * @return string
     */
    public function sanitizeFileName(string $name): string
    {
        // Replace forward slash with dash
        $name = str_replace('/', '-', $name);

        // Remove invalid filename characters
        $name = preg_replace('/[\\\\:*?"<>|]/', '', $name);

        // Replace multiple spaces/underscores with single space
        $name = preg_replace('/[\s_]+/', ' ', $name);

        // Trim and limit length
        $name = trim($name);
        if (mb_strlen($name) > 200) {
            $name = mb_substr($name, 0, 200);
        }

        return $name ?: 'untitled';
    }

    /**
     * Convert objdoc to hexadecimal filename
     * objdoc (decimal) → hexadecimal uppercase, padded to 8 chars
     * Example: 10 → "0000000A", 3101 → "00000C1D"
     * @param int $objdocid
     * @param int $len
     * @return string
     */
    public function objdocidToHexFilename(int $objdocid, int $len = 8): string
    {
        $hex = strtoupper(dechex($objdocid));
        return str_pad($hex, $len, '0', STR_PAD_LEFT);
    }

    /**
     * Build file path for ELO document file using objdoc
     *
     * Path format: Archivdata/DMS_1/UP{folder_hex}/{hexFilename}.{ext}
     * Folder is calculated by: objdoc >> 10 (divide by 1024), then converted to 6-char hex
     * Example: objdoc=3101 → folder=3101>>10=3=000003 → Archivdata/DMS_1/UP000003/00000C1D.{TIF|JPG}
     * @param stdClass $obj
     * @param string $archiveBasePath
     * @return string
     */
    public function buildFilePath(stdClass $obj, string $archiveBasePath = 'Archivdata'): string
    {
        // Obj doc id necessary
        $objdocid = $obj->objdoc ?? 0;
        if (!$objdocid) {
            return "";
        }

        // Folder: shift right by 10 bits (divide by 1024), convert to 6-char hex
        $folder = 'UP' . $this->objdocidToHexFilename($objdocid >> 10 << 2, 6);

        // Hex file name (8 characters)
        $hexFilename = $this->objdocidToHexFilename($objdocid);

        // Build path without extension (caller will glob for extension)
        return "$archiveBasePath/DMS_1/$folder/$hexFilename";
    }
}
