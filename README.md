# ELO DMS Export Tool

A PHP CLI tool for exporting ELO DMS archives to Nextcloud-ready folder structures. Preserves the original ELO folder hierarchy and converts image files to PDF format.

## Features

- Extract documents from ELO MDB databases using mdb-json
- Convert image files (TIF, TIFF, JPG, JPEG, PNG, GIF) to PDF using PHP Imagick
- Preserve original ELO folder hierarchy structure
- Copy unsupported file formats as-is
- Support for multi-page TIFF to multi-page PDF conversion
- Sanitize filenames for cross-platform compatibility
- Direct PDF generation without temporary files

## Requirements

- PHP 8.1 or higher
- PHP Extensions:
  - `imagick` - For image to PDF conversion
- System packages:
  - `mdbtools` - For MDB database access (provides mdb-json command)

## Installation

```bash
# Clone repository
git clone https://github.com/four-bytes/four-elo-dms-export.git
cd four-elo-dms-export

# Install dependencies
composer install
```

### System Requirements Setup

#### Linux / WSL 2 (Ubuntu/Debian)

Install MDBTools and PHP Imagick:

```bash
# Install MDBTools for MDB database access
sudo apt-get update
sudo apt-get install -y mdbtools

# Install PHP Imagick extension
sudo apt-get install -y php-imagick imagemagick

# Verify MDBTools installation
mdb-json --version

# Verify Imagick installation
php -m | grep imagick
```

#### Windows

Install MDBTools and Imagick:

1. **MDBTools**: Download from [MDBTools releases](https://github.com/mdbtools/mdbtools/releases) or use package manager
2. **Imagick**: Download [ImageMagick](https://imagemagick.org/script/download.php#windows) and PHP Imagick extension for your PHP version

#### macOS

```bash
# Install using Homebrew
brew install mdbtools imagemagick
brew install php-imagick
```

#### Verify Installation

```bash
# Test MDBTools
mdb-json --help

# Test Imagick
php -m | grep imagick
```

## Usage

Basic export command:

```bash
./bin/elo-export export /path/to/DMS.MDB /path/to/Archivdata --output=/path/to/export
```

### Arguments

- `database` - Path to ELO MDB database file (e.g., `Archivdata/DMS.MDB`)
- `files` - Path to ELO files directory (e.g., `Archivdata`)

### Options

- `--output` / `-o` - Output directory for export (default: `./nextcloud-export`)

### Example

```bash
# Export ELO archive to Nextcloud directory
./bin/elo-export export \
  /mnt/archive/Archivdata/DMS.MDB \
  /mnt/archive/Archivdata \
  --output=/home/user/Nextcloud/Documents/ELO-Archive
```

## Output Structure

The tool preserves the original ELO folder hierarchy:

```
export-directory/
├── Invoices/
│   ├── 2024/
│   │   ├── invoice-2024-001.pdf
│   │   └── contract-abc.pdf
│   └── 2025/
│       └── invoice-2025-001.pdf
├── Contracts/
│   └── client-agreement.pdf
└── HR Documents/
    └── employee-handbook.pdf
```

- Folder structure mirrors the original ELO folder hierarchy
- Supported image formats (TIF, JPG, PNG, GIF) are converted to PDF
- Unsupported file formats are copied as-is with original extension
- Filenames are sanitized for cross-platform compatibility
- Duplicate filenames are automatically numbered (file_1.pdf, file_2.pdf, etc.)

## ELO Database Schema

The tool works with standard ELO DMS database structure:

- **objekte** table - Main document objects
- **objkeys** table - Document metadata and keys
- **dochistory** table - Document version history

### Key Fields

- Files are identified by `objtype > 254` and `objtype < 9999` (excluding root object)
- Deleted documents have `objstatus != 0` (excluded from export)
- Folder hierarchy built from `objparent` references
- File path format: `Archivdata/DMS_1/UP{(objdoc>>10)<<2 in 6-char hex}/{objdoc in 8-char hex}`
- Example: objdoc=3101 → folder=(3101>>10)<<2=12 → `Archivdata/DMS_1/UP00000C/00000C1D.TIF`

## Development

```bash
# Install development dependencies
composer install

# Run tests
composer test
```

## License

MIT License - see [LICENSE](LICENSE) file for details

## Author

**4 Bytes**
Email: info@4bytes.de

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

For issues and questions, please use the [GitHub issue tracker](https://github.com/four-bytes/four-elo-dms-export/issues).
