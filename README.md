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
  - `pdo_odbc` - For MDB database access
  - `imagick` - For image to PDF conversion
- System packages:
  - `unixODBC`
  - `mdbtools-odbc` (Linux) or Microsoft Access ODBC Driver (Windows)

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

Install UnixODBC and MDBTools for MDB database access:

```bash
# Install ODBC and MDBTools
sudo apt-get update
sudo apt-get install -y unixodbc unixodbc-dev mdbtools mdbtools-dev

# Install PHP extensions
sudo apt-get install -y php-odbc

# Verify installation
php -m | grep -i odbc
php -m | grep -i pdo
```

Configure MDBTools ODBC driver:

```bash
# Edit ODBC configuration
sudo nano /etc/odbcinst.ini
```

Add this configuration:

```ini
[MDBTools]
Description = MDBTools ODBC Driver
Driver = /usr/lib/x86_64-linux-gnu/odbc/libmdbodbc.so
Setup = /usr/lib/x86_64-linux-gnu/odbc/libmdbodbc.so
FileUsage = 1
```

Test ODBC connection:

```bash
# Check available drivers
odbcinst -q -d

# Test connection to MDB file
isql -v "Driver=MDBTools;DBQ=/path/to/file.mdb"
```

#### Windows

For Windows, install the Microsoft Access Database Engine:

1. Download [Microsoft Access Database Engine 2016 Redistributable](https://www.microsoft.com/en-us/download/details.aspx?id=54920)
2. Install the appropriate version (32-bit or 64-bit matching your PHP installation)
3. PHP ODBC extension should be available by default

#### Install PHP Imagick Extension

```bash
# Linux / WSL 2
sudo apt-get install -y php-imagick imagemagick

# Verify installation
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
- `--dsn` - Custom ODBC DSN for database connection (optional)

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

- Files are identified by `objtype > 255`
- Deleted documents have `objstatus = 1` (excluded from export)
- Filename stored in `objkeys.okeydata` where `okeyname = 'ELO_FNAME'`
- File path format: `Archivdata/DMS_1/UP{first6chars}/{filename}`

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
