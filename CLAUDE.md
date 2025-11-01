# four-elo-dms-export - CLAUDE.md

## Project Overview

CLI tool for exporting ELO DMS (Document Management System) archives to Nextcloud-ready folder structures.

**Purpose**: Extract documents and metadata from ELO MDB databases, convert TIF/JPG images to PDF format, and organize everything in a clean folder hierarchy suitable for direct copying to Nextcloud.

**Technology Stack**:
- PHP 8.1+
- Symfony Console (CLI framework)
- PHP Imagick (image to PDF conversion)
- PDO ODBC (MDB database access)

**Dependencies**:
- System: `unixODBC`, `mdbtools-odbc` (or Microsoft Access ODBC driver)
- PHP Extensions: `pdo_odbc`, `imagick`

## Development Commands

```bash
# Install dependencies
composer install

# Run export command
./bin/elo-export export --source=/path/to/elo/archive --output=/path/to/nextcloud

# Run tests
composer test
```

## Architecture Overview

**Framework Stack**:
- Symfony Console for CLI commands
- PSR-4 autoloading
- Service-based architecture

**Key Directories**:
```
src/
├── Command/         # CLI commands (Symfony Console)
├── Service/         # Business logic services
│   ├── DatabaseReader.php    # MDB/ODBC database access
│   ├── ImageConverter.php    # Imagick-based image to PDF conversion
│   └── ExportOrganizer.php   # File organization and metadata export
└── bootstrap.php    # Application initialization
```

**Design Patterns**:
- Command pattern (Symfony Console)
- Service layer pattern
- Dependency injection

## ELO Database Schema

**Status**: ⚠️ Awaiting ELO database structure documentation

Required information:
- Table structure for documents and metadata
- ID building/generation patterns
- File path resolution from database records
- Key fields for categorization and organization

## Output Structure

```
nextcloud-export/
├── documents/
│   ├── YYYY/                  # Organized by date
│   │   ├── category/
│   │   │   └── document.pdf
│   │   └── ...
│   └── ...
├── metadata/
│   ├── documents.csv          # All metadata for filtering
│   ├── index.html            # Optional browsable index
│   └── export-report.json    # Export statistics
└── README.md                 # Export documentation
```

## Configuration

**Environment Variables** (optional .env support):
```
ELO_DB_PATH=/path/to/elo.mdb
ELO_FILES_PATH=/path/to/elo/files
EXPORT_OUTPUT_PATH=/path/to/output
```

## Architectural Change History

### [0.1.0] - 2025-11-01

**Initial Project Setup**

#### Added
- Project structure following 4 Bytes standards
- Symfony Console framework for CLI
- Service architecture (DatabaseReader, ImageConverter, ExportOrganizer)
- Composer configuration with PHP 8.1+ requirement
- Imagick and PDO_ODBC dependencies

#### Technical Details
- Package: four-bytes/four-elo-dms-export
- Namespace: Four\Elo
- Public repository under four-bytes GitHub organization
- MIT License for open source distribution
