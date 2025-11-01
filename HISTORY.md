# four-elo-dms-export - Change History

## [0.1.0] - 2025-11-01

### Added
- Initial project structure following 4 Bytes standards
- Composer configuration with Symfony Console framework
- Service architecture for modular export processing
- Support for PHP Imagick-based image to PDF conversion
- PDO ODBC integration for ELO MDB database access
- Project documentation (CLAUDE.md, HISTORY.md)

### Technical Details
- **Package**: four-bytes/four-elo-dms-export
- **Namespace**: Four\Elo
- **PHP Version**: 8.1+
- **Key Dependencies**: symfony/console ^7.0, ext-imagick, ext-pdo
- **License**: MIT
- **Repository**: Public under four-bytes GitHub organization

### Architecture
- Command pattern via Symfony Console
- Service layer: DatabaseReader, ImageConverter, ExportOrganizer
- Nextcloud-ready output structure with metadata export
