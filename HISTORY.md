# four-elo-dms-export - Change History

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
