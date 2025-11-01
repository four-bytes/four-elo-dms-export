
# ELO DMS Database Schema Documentation

## Export Workflow

### Overview
This tool exports documents from an ELO DMS archive by:
1. Reading metadata from the MDB database
2. Building folder hierarchy from folder objects
3. Converting image files (TIF/JPG) to PDF
4. Organizing exported files in ELO folder structure

### Step-by-Step Process

1. **Load all objects from database**
   - Query: `SELECT * FROM objekte`
   - Loads both folders (objtype < 255) and files (objtype > 254)

2. **Build folder path lookup**
   - Process folders only (objtype < 255)
   - For each folder, traverse `objparent` chain to root
   - Creates lookup: `[objid] => "path/to/folder"`
   - Folder names are sanitized `objshort` values

3. **Process file objects**
   - Filter files (objtype > 254) where objstatus != 1
   - For each file:
     - Convert `objdoc` (decimal) to hex filename (e.g., 3101 → "00000C1D")
     - Build file path: `Archivdata/DMS_1/UP{first6}/{hexFilename}.*`
     - Use glob to find file with any extension (.TIF, .JPG, etc.)
     - Get folder path using `objparent` from folder lookup
     - Convert TIF/JPG to PDF
     - Save as: `Export/<folder_path>/<sanitized_objshort>.pdf`

## Database Structure

### Database Location
- **Path**: `Archivdata/DMS.MDB`
- **Driver**: MDBTools ODBC (Linux) or MS Access ODBC (Windows)

### Table: objekte
Main table containing all ELO objects (folders and files)

**Key Fields**:
- `objid` (int) - Unique object identifier
- `objtype` (int) - Object type:
  - `< 255` = Folder/Container
  - `> 254` = File/Document
- `objshort` (text) - Display name/title of object
- `objparent` (int) - Parent folder's objid (builds hierarchy)
- `objstatus` (int) - Status flag:
  - `1` = Deleted (skip these)
  - `NULL` or other = Active
- `objdoc` (text) - Document file ID reference
- `objidate` (date) - Index/creation date

**Other Fields**:
objflags, objsreg, objxdate, objkey, objkind, objpath, objinfo, objmask,
objattach, objakey1, objakey2, objlkey1, objlkey2, objuser, objlock,
objhistcount, objdesc, objchildcount, objdeldate, objsyncdateloc,
objsyncdaterem, objvtrep, objacl, replset, objguid, objtstamp, objsdata, objsdesc

### Table: objkeys
Stores additional metadata key-value pairs for objects *(not used in basic export)*

**Fields**:
- `parentid` (int) - References objekte.objid
- `okeyno` (int) - Key number/index
- `okeyname` (text) - Key name (e.g., "ELO_FNAME")
- `okeydata` (text) - Key value
- `okeysdata` (text) - Additional data

**Note**: File extensions are determined via glob pattern matching, so objkeys is not required for basic export

### Table: dochistory
Document version history (not used in basic export)

**Fields**:
objectid, documentid, userid, createdate, histid, histcomment, histversion,
docmd5, docguid, doctstamp, docflags, docstatus, docsignature

## File Storage Structure

### Physical File Location
Files are stored in a two-level directory structure based on filename:

**Pattern**: `Archivdata/DMS_1/UP{first6chars}/{filename}`

**Example**:
- Filename: `00000C1D.TIF`
- Folder: `UP` + first 6 chars = `UP00000C`
- Full path: `Archivdata/DMS_1/UP00000C/00000C1D.TIF`

## Filename Sanitization Rules

When exporting, filenames (from `objshort`) are sanitized:

1. Replace `/` with `-` (dash)
2. Remove invalid characters: `\ : * ? " < > |`
3. Replace multiple spaces/underscores with single space
4. Trim whitespace
5. Limit length to 200 characters
6. Default to "untitled" if empty

## Export Example

**Database entries**:
```
objekte:
  objid=100, objtype=1,   objshort="Invoices", objparent=1        (folder)
  objid=200, objtype=1,   objshort="2024",     objparent=100      (folder)
  objid=300, objtype=256, objshort="INV-001",  objparent=200,  objdoc="3101"  (file)
```

**Export result**:
```
Export/documents/Invoices/2024/INV-001.pdf
```

**Process**:
1. Build folder path for objid=200: "Invoices/2024"
2. File objid=300 has objparent=200, objdoc="3101"
3. Get folder path from objparent=200: "Invoices/2024"
4. Convert objdoc to hex: 3101 (decimal) → "00000C1D" (hex, 8 chars)
5. Build file path: `Archivdata/DMS_1/UP00000C/00000C1D.*`
6. Glob finds: `Archivdata/DMS_1/UP00000C/00000C1D.TIF`
7. Convert TIF → PDF
8. Save as: "Export/documents/Invoices/2024/INV-001.pdf"

## Filename Conversion

**objdoc to Hex Filename**:
- objdoc is stored as decimal integer in database
- Convert to hexadecimal uppercase
- Pad with zeros to 8 characters

**Examples**:
| objdoc (decimal) | Hexadecimal | Padded Filename |
|-----------------|-------------|-----------------|
| 10              | A           | 0000000A        |
| 41              | 29          | 00000029        |
| 3101            | C1D         | 00000C1D        |
| 65535           | FFFF        | 0000FFFF        |

