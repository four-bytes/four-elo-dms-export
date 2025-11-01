# ELO DMS Export Tool

A PHP CLI tool for exporting ELO DMS archives to Nextcloud-ready folder structures. Converts TIF/JPG images to PDF format and organizes documents with searchable metadata.

## Features

- Extract documents and metadata from ELO MDB databases
- Convert TIF/JPG images to PDF using PHP Imagick
- Organize documents by date in clean folder hierarchy
- Export metadata to CSV for easy filtering
- Generate JSON export reports
- Sanitize filenames for cross-platform compatibility
- Support for multi-page TIFF documents

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

```
export-directory/
├── documents/
│   ├── 2024/
│   │   ├── 01/
│   │   │   ├── invoice-2024-001.pdf
│   │   │   └── contract-abc.pdf
│   │   └── 02/
│   └── 2025/
├── metadata/
│   ├── documents.csv          # All metadata in CSV format
│   └── export-report.json     # Export statistics and details
└── README.md                  # Export documentation
```

## ELO Database Schema

The tool works with standard ELO DMS database structure:

- **objekte** table - Main document objects
- **objkeys** table - Document metadata and keys
- **dochistory** table - Document version history

### Key Fields

- Files are identified by `objtype > 254`
- Deleted documents have `objstatus != 0` (excluded from export)
- Folder hierarchy built from `objparent` references
- File path format: `Archivdata/DMS_1/UP{objdoc>>10 in 6-char hex}/{objdoc in 8-char hex}`
- Example: objdoc=3101 → `Archivdata/DMS_1/UP000003/00000C1D.TIF`

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
