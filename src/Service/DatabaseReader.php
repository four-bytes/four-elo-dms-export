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

    public function __construct(string $databasePath, ?string $customDsn = null)
    {
        if (!in_array('odbc', \PDO::getAvailableDrivers(), true)) {
            throw new \RuntimeException('PDO ODBC driver is not available');
        }

        if (!file_exists($databasePath)) {
            throw new \InvalidArgumentException("Database file not found: {$databasePath}");
        }

        $dsn = $customDsn ?? $this->buildDsn($databasePath);
        $this->connection = new PDO($dsn);
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Build ODBC DSN for MDB file
     */
    private function buildDsn(string $databasePath): string
    {
        // Try MDBTools ODBC driver first (Linux)
        if (PHP_OS_FAMILY === 'Linux') {
            return sprintf(
                'odbc:Driver=MDBTools;Dbq=%s',
                realpath($databasePath)
            );
        }

        // Fall back to Microsoft Access driver (Windows)
        return sprintf(
            'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=%s',
            realpath($databasePath)
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
     * Get documents from ELO database
     *
     * Returns all file objects (objtype > 255) that are not deleted (objstatus != 1)
     * Includes metadata from objkeys table (especially ELO_FNAME)
     *
     * @return array<array<string, mixed>>
     */
    public function getDocuments(): array
    {
        $sql = "
            SELECT
                o.*,
                k.okeydata as elo_fname
            FROM objekte o
            LEFT JOIN objkeys k ON o.objid = k.parentid AND k.okeyname = 'ELO_FNAME'
            WHERE o.objtype > 255
            AND (o.objstatus IS NULL OR o.objstatus != 1)
            ORDER BY o.objid
        ";

        $stmt = $this->connection->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
     * Build file path for ELO document file
     *
     * Path format: Archivdata/DMS_1/UP{first6chars}/{filename}
     * Example: Archivdata/DMS_1/UP00000C/00000C1D.TIF
     */
    public function buildFilePath(string $filename, string $archiveBasePath = 'Archivdata'): string
    {
        // Extract first 6 characters from filename (without extension)
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $first6 = substr($baseName, 0, 6);

        // Build UP folder name
        $upFolder = 'UP' . $first6;

        // Build full path
        return "{$archiveBasePath}/DMS_1/{$upFolder}/{$filename}";
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
