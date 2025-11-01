<?php

declare(strict_types=1);

namespace Four\Elo\Service;

use PDO;

/**
 * Reads document metadata from ELO MDB database via ODBC
 */
class DatabaseReader
{
    private PDO $connection;
    private string $databasePath;

    public function __construct(string $databasePath, ?string $customDsn = null)
    {
        if (!in_array('odbc', \PDO::getAvailableDrivers(), true)) {
            throw new \RuntimeException('PDO ODBC driver is not available');
        }

        if (!file_exists($databasePath)) {
            throw new \InvalidArgumentException("Database file not found: {$databasePath}");
        }

        $this->databasePath = $databasePath;

        $dsn = $customDsn ?? $this->buildDsn($databasePath);
        $this->connection = new PDO($dsn);
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Build ODBC DSN for MDB file
     */
    private function buildDsn(string $databasePath): string
    {
        $realPath = realpath($databasePath);

        // Try MDBTools ODBC driver first (Linux)
        if (PHP_OS_FAMILY === 'Linux') {
            // Try different DSN formats that MDBTools might accept
            return sprintf(
                'odbc:Driver=MDBTools;DBQ=%s;',
                $realPath
            );
        }

        // Fall back to Microsoft Access driver (Windows)
        return sprintf(
            'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=%s;',
            $realPath
        );
    }

    /**
     * Get list of all tables in database (for schema exploration)
     */
    public function getTables(): array
    {
        $stmt = $this->connection->query(
            "SELECT name FROM MSysObjects WHERE type=1"
        );

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get total count of documents to be exported
     *
     * @return int
     */
    public function getDocumentCount(): int
    {
        // Count active files (objstatus = 0)
        // MDBTools ODBC is very limited, manually count results
        $sql = "SELECT objid FROM objekte WHERE objtype > 254 AND objstatus = 0";
        $stmt = $this->connection->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return count($rows);
    }

    /**
     * Get documents from ELO database as a generator (streaming)
     *
     * Returns all file objects (objtype > 254) that are not deleted (objstatus = 0)
     *
     * @return \Generator
     */
    public function getDocuments(): \Generator
    {
        // 1. Load only folders (objtype < 255, active objstatus = 0)
        $sql = "SELECT * FROM objekte WHERE objtype < 255 AND objstatus = 0";
        $stmt = $this->connection->query($sql);
        $folders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Build folder path lookup from folders only
        $folderPaths = $this->buildFolderPathLookup($folders);

        // Free folders from memory
        unset($folders);

        // 3. Stream file entries (objtype > 254, active objstatus = 0)
        $sql = "SELECT * FROM objekte WHERE objtype > 254 AND objstatus = 0";
        $stmt = $this->connection->query($sql);

        // 4. Yield files one at a time with folder paths
        while ($doc = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Add folder path using objparent to lookup folder path
            $objparent = isset($doc['objparent']) ? (int)$doc['objparent'] : 0;
            $doc['folder_path'] = $folderPaths[$objparent] ?? '';

            yield $doc;
        }
    }

    /**
     * Build folder path lookup for folders (objtype < 255)
     * Returns map of objid => folder path string
     */
    private function buildFolderPathLookup(array $allObjects): array
    {
        // First pass: create objid => object lookup (only folders)
        $objLookup = [];
        foreach ($allObjects as $obj) {
            $objid = (int)$obj['objid'];
            $objLookup[$objid] = $obj;
        }

        // Second pass: build folder paths (only for folders objtype < 255)
        $paths = [];
        foreach ($allObjects as $obj) {
            $objtype = (int)($obj['objtype'] ?? 0);

            // Only process folders
            if ($objtype >= 255) {
                continue;
            }

            $objid = (int)$obj['objid'];
            $path = $this->buildFolderPathRecursive($objid, $objLookup);
            $paths[$objid] = $path;
        }

        return $paths;
    }

    /**
     * Build folder path recursively by traversing objparent
     */
    private function buildFolderPathRecursive(int $objid, array &$objLookup, array &$visited = []): string
    {
        // Prevent infinite loops
        if (isset($visited[$objid])) {
            return '';
        }
        $visited[$objid] = true;

        if (!isset($objLookup[$objid])) {
            return '';
        }

        $obj = $objLookup[$objid];
        $objparent = isset($obj['objparent']) ? (int)$obj['objparent'] : 0;

        // If no parent or parent is root (typically 1), return empty path
        if ($objparent <= 1) {
            return '';
        }

        // Get parent path
        $parentPath = $this->buildFolderPathRecursive($objparent, $objLookup, $visited);

        // Get sanitized folder name from parent
        $parentObj = $objLookup[$objparent] ?? null;
        if (!$parentObj) {
            return $parentPath;
        }

        $folderName = $this->sanitizeFolderName($parentObj['objshort'] ?? 'folder');

        // Combine parent path with current folder
        if (empty($parentPath)) {
            return $folderName;
        }

        return $parentPath . '/' . $folderName;
    }

    /**
     * Sanitize folder/file name according to ELO export rules
     * - Replace / with -
     * - Remove invalid characters
     * - Replace rest with single space
     */
    private function sanitizeFolderName(string $name): string
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
     * Load all objkeys records at once
     * Returns map of parentid => [okeyname => okeydata]
     *
     * Note: MDBTools ODBC driver has issues with text fields returning empty values,
     * so we use mdb-export directly for reliable data access
     */
    private function loadAllObjKeys(): array
    {
        // Use mdb-export to get reliable data (MDBTools ODBC has text field issues)
        $cmd = sprintf('mdb-export %s objkeys', escapeshellarg($this->databasePath));
        $output = shell_exec($cmd);

        if (!$output) {
            return [];
        }

        $map = [];
        $lines = explode("\n", trim($output));

        // Skip header line
        array_shift($lines);

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            // Parse CSV line
            $fields = str_getcsv($line);
            if (count($fields) < 4) {
                continue;
            }

            $parentid = (int)$fields[0];
            $keyname = trim($fields[2], '"'); // Remove quotes
            $keydata = trim($fields[3], '"'); // Remove quotes

            if (!isset($map[$parentid])) {
                $map[$parentid] = [];
            }

            $map[$parentid][$keyname] = $keydata;
        }

        return $map;
    }

    /**
     * Get all metadata keys for a specific object
     *
     * @return array<array<string, mixed>>
     */
    public function getObjectKeys(int $objectId): array
    {
        $stmt = $this->connection->prepare(
            "SELECT * FROM objkeys WHERE parentid = ?"
        );
        $stmt->execute([$objectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get folder hierarchy for an object
     *
     * @return array<array<string, mixed>>
     */
    public function getFolderPath(int $objectId): array
    {
        $path = [];
        $currentId = $objectId;

        while ($currentId > 0) {
            $stmt = $this->connection->prepare(
                "SELECT objid, objshort, objparent FROM objekte WHERE objid = ?"
            );
            $stmt->execute([$currentId]);
            $folder = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$folder) {
                break;
            }

            array_unshift($path, $folder);
            $currentId = (int)($folder['objparent'] ?? 0);
        }

        return $path;
    }

    /**
     * Convert objdoc to hexadecimal filename
     * objdoc (decimal) → hexadecimal uppercase, padded to 8 chars
     * Example: 10 → "0000000A", 3101 → "00000C1D"
     */
    public function objdocToHexFilename(string $objdoc): string
    {
        $decimal = (int)$objdoc;
        $hex = strtoupper(dechex($decimal));
        return str_pad($hex, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Build file path for ELO document file using objdoc
     *
     * Path format: Archivdata/DMS_1/UP{first6chars}/{hexFilename}.{ext}
     * Example: objdoc=3101 → Archivdata/DMS_1/UP00000C/00000C1D.{TIF|JPG}
     */
    public function buildFilePathFromObjdoc(string $objdoc, string $archiveBasePath = 'Archivdata'): string
    {
        $hexFilename = $this->objdocToHexFilename($objdoc);

        // Extract first 6 characters for UP folder
        $first6 = substr($hexFilename, 0, 6);
        $upFolder = 'UP' . $first6;

        // Build path without extension (caller will glob for extension)
        return "{$archiveBasePath}/DMS_1/{$upFolder}/{$hexFilename}";
    }

    /**
     * Execute raw query (for testing and exploration)
     */
    public function query(string $sql): array
    {
        $stmt = $this->connection->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get connection for advanced queries
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }
}
