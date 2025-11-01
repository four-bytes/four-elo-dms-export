
# ELO DMS Database Schema Documentation

## Export Workflow

### Overview
This tool exports documents from an ELO DMS archive by:
1. Reading metadata from the MDB database using mdb-json
2. Building folder hierarchy from folder objects
3. Converting image files (TIF/JPG) to PDF
4. Organizing exported files in ELO folder structure

### Step-by-Step Process

1. **Load all objects from database**
   - Uses: `mdb-json {database} objekte` (outputs one JSON object per line)
   - Streams data line-by-line for memory efficiency
   - Processes both folders (objtype < 255) and files (objtype > 254)

2. **Build folder path lookup**
   - Process folders only (objtype < 255) where objstatus = 0 (active)
   - For each folder, traverse `objparent` chain to root
   - Creates lookup: `[objid] => "path/to/folder"`
   - Folder names are sanitized `objshort` values

3. **Process file objects**
   - Filter files (objtype > 254 and < 9999) where objstatus = 0 (active, excluding root)
   - For each file:
     - Convert `objdoc` (decimal) to hex filename (e.g., 3101 → "00000C1D")
     - Calculate folder: `(objdoc >> 10) << 2` (divide by 256), convert to 6-char hex
     - Build file path: `Archivdata/DMS_1/UP{folder_hex}/{hexFilename}.*`
     - Example: objdoc=3101 → folder=(3101>>10)<<2=12=00000C → `Archivdata/DMS_1/UP00000C/00000C1D.*`
     - Use glob to find file with any extension
     - Get folder path using `objparent` from folder lookup
     - Convert supported formats (TIF, JPG, PNG, GIF) to PDF or copy unsupported files as-is
     - Save as: `Export/<folder_path>/<sanitized_objshort>.<ext>`

## Database Structure

### Database Location
- **Path**: `Archivdata/DMS.MDB`
- **Access Method**: mdb-json from MDBTools package (outputs line-delimited JSON)

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
Files are stored in a two-level directory structure based on objdoc calculation:

**Pattern**: `Archivdata/DMS_1/UP{folder_hex}/{filename}`

**Folder Calculation**:
- Take objdoc value (decimal)
- Divide by 256 using: `(objdoc >> 10) << 2`
  - Shift right 10 bits (divide by 1024)
  - Shift left 2 bits (multiply by 4)
  - Net effect: divide by 256
- Convert result to 6-character uppercase hex
- Prefix with "UP"

**Example**:
- objdoc: 3101 (decimal)
- Folder calculation: (3101 >> 10) << 2 = 3 << 2 = 12
- Folder hex: 00000C (6 chars)
- Folder name: UP00000C
- Filename: 00000C1D.TIF (objdoc 3101 in 8-char hex)
- Full path: `Archivdata/DMS_1/UP00000C/00000C1D.TIF`

**More Examples**:
| objdoc | (objdoc>>10)<<2 | Folder Hex | Folder Name | Filename    |
|--------|-----------------|------------|-------------|-------------|
| 3101   | 12              | 00000C     | UP00000C    | 00000C1D    |
| 18000  | 68              | 000044     | UP000044    | 00004650    |
| 115000 | 448             | 0001C0     | UP0001C0    | 0001C138    |

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
Export/Invoices/2024/INV-001.pdf
```

**Process**:
1. Build folder path for objid=200: "Invoices/2024"
2. File objid=300 has objparent=200, objdoc="3101"
3. Get folder path from objparent=200: "Invoices/2024"
4. Convert objdoc to hex: 3101 (decimal) → "00000C1D" (hex, 8 chars)
5. Calculate folder: (3101 >> 10) << 2 = 12 → "00000C" (hex, 6 chars) → "UP00000C"
6. Build file path: `Archivdata/DMS_1/UP00000C/00000C1D.*`
7. Glob finds: `Archivdata/DMS_1/UP00000C/00000C1D.TIF`
8. Convert TIF → PDF (or copy if unsupported format)
9. Save as: "Export/Invoices/2024/INV-001.pdf"

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

