# four-elo-dms-export - Change History

## [0.5.0] - 2025-11-01

### Changed
- **Complete DatabaseReader refactor**: Simplified to object-based API with single-load caching
- **Data model**: Changed from associative arrays to stdClass objects for cleaner property access
- **Caching strategy**: Load all objects once via `getObjects()`, cache in memory, filter on demand
- **Helper properties**: Added `isFolder`, `isDocument`, `isDeleted` flags to each object for easy filtering
- **Method signatures**: Simplified to work with stdClass objects instead of mixed types
- **Path creation**: New dedicated methods `createDocumentPath()` and `createFolderPath()`
- **Null safety**: Improved with null coalescing operator (`??`) throughout
- **Early returns**: Added optimization for root/invalid folders in `createFolderPath()`
- **Explicit imports**: Using explicit `InvalidArgumentException` and `RuntimeException` imports

### Added
- `getObjects()`: Loads and caches all database objects as stdClass with helper flags
- `getDocuments()`: Returns filtered array of document objects (non-deleted files)
- `getFolders()`: Returns filtered array of folder objects (non-deleted folders)
- `createDocumentPath()`: Generates full export path from document object
- `createFolderPath()`: Recursively builds folder hierarchy path
- `buildFilePath()`: Creates physical file path from document object

### Removed
- Generator/streaming approach (replaced with cached approach)
- `buildFilePathFromObjdoc()` (replaced with `buildFilePath()`)
- `objdocToHexFilename()` (renamed to `objdocidToHexFilename()`)
- Two-pass processing complexity
- **ext-pdo dependency** from composer.json (no longer needed with mdb-json)
- ODBC/DSN configuration requirements
- `--dsn` command option (no longer applicable)

### Technical Details
- Single `mdb-json` execution loads all objects into memory
- Objects stored as `stdClass` (not arrays) using `json_decode($line, false)`
- Objects indexed by `objid` for O(1) lookups
- Helper flags computed once during load: `isFolder`, `isDocument`, `isDeleted`
- Null coalescing operator (`??`) used for: `objstatus`, `objparent`, `objshort`, `objdoc`
- Early return in `createFolderPath()` for `$folderId <= 1` (root/invalid)
- Folder path recursion uses cached object lookup for performance
- Only system dependency: `mdbtools` package (provides `mdb-json`)
- No PHP PDO/ODBC extensions required
- Much simpler and more maintainable codebase

## [0.4.0] - 2025-11-01

### Changed
- **Database access method**: Switched from PDO ODBC to mdb-json for reliable and maintained database access
- **Folder calculation corrected**: Fixed file path calculation from "first 6 hex chars" to proper `objdoc >> 10` formula
- **Line-by-line streaming**: Database output now streamed line-by-line using popen() and fgets()
- **Type flexibility**: buildFilePathFromObjdoc() now accepts both string and int parameters
- **Status filtering**: Changed from `objstatus != 1` to `objstatus = 0` for active records

### Fixed
- MDBTools ODBC unreliability issues (SQL parsing errors, segmentation faults)
- Incorrect folder path calculation for physical file locations
- Type errors when passing objdoc values between methods

### Technical Details
- Uses `mdb-json {database} objekte` which outputs one JSON object per line
- Folder calculation: `folder = (objdoc >> 10)` converted to 6-char hex with UP prefix
- Example: objdoc=3101 → 3101>>10=3 → UP000003 → `Archivdata/DMS_1/UP000003/00000C1D.TIF`
- Two-pass processing: First pass collects folders, second pass streams files
- All JSON decoding uses associative arrays (not objects) for consistent access
- Database access completely independent of ODBC drivers

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
