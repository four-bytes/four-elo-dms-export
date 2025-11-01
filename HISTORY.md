# four-elo-dms-export - Change History

## [0.3.0] - 2025-11-01

### Changed
- **Streaming architecture**: Converted getDocuments() to use PHP generator for memory-efficient processing
- **Partial database reading**: Separated folder loading from file loading to reduce memory footprint
- **Optimized queries**: Added WHERE clauses to filter at database level
- **Logs relocated**: Moved all logs to var/log relative to tool (not in export path)
- **Clean export structure**: Export folder now contains only documents/ hierarchy, no metadata files

### Removed
- Metadata export functionality (CSV and JSON reports)
- processedDocuments tracking (no longer needed)

### Fixed
- Undefined $filename variable in logging

### Technical Details
- getDocuments() now yields documents one at a time instead of loading all into memory
- Added getDocumentCount() for efficient progress bar counting
- Folder hierarchy built first from `SELECT * FROM objekte WHERE objtype < 255`
- Files streamed from `SELECT * FROM objekte WHERE objtype > 254 AND (objstatus IS NULL OR objstatus != 1)`
- Log path: `{project_root}/var/log/`
- Export path: `{user_specified}/documents/`

## [0.2.0] - 2025-11-01

### Fixed
- Corrected folder hierarchy export logic
- Files now placed in proper ELO folder structure
- Filenames now use sanitized objshort values
- Fixed MDBTools ODBC text field issues using mdb-export

### Changed
- Folder paths built from folders (objtype < 255) using objparent traversal
- Files (objtype > 254) use objparent to determine folder placement
- objkeys loaded via mdb-export CLI tool instead of PDO (workaround for ODBC issues)
- Enhanced DBSCHEMA.md with comprehensive workflow documentation

### Technical Details
- Export structure: `Export/documents/<folder_path>/<sanitized_objshort>.pdf`
- Folder lookup: Recursive objparent traversal for hierarchy
- Filename sanitization: Unified rules for folders and files
- File lookup: ELO_FNAME from objkeys via mdb-export

## [0.1.0] - 2025-11-01

### Added
- Initial project structure following 4 Bytes standards
- Composer configuration with Symfony Console framework
- Service architecture for modular export processing
- Support for PHP Imagick-based image to PDF conversion
- PDO ODBC integration for ELO MDB database access
- Comprehensive logging system
- Project documentation (CLAUDE.md, HISTORY.md, DBSCHEMA.md)

### Technical Details
- **Package**: four-bytes/four-elo-dms-export
- **Namespace**: Four\Elo
- **PHP Version**: 8.1+
- **Key Dependencies**: symfony/console ^7.0, ext-imagick, ext-pdo
- **License**: MIT
- **Repository**: Public under four-bytes GitHub organization

### Architecture
- Command pattern via Symfony Console
- Service layer: DatabaseReader, ImageConverter, ExportOrganizer, Logger
- Nextcloud-ready output structure with metadata export
