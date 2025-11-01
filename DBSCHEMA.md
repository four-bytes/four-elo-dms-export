
# ELO DMS Database Schema Documentation

## Export Workflow

### Overview
This tool exports documents from an ELO DMS archive by:
1. Reading metadata from the MDB database
2. Building folder hierarchy from folder objects
3. Converting image files (TIF/JPG) to PDF
4. Organizing exported files in ELO folder structure

### Step-by-Step Process

1. **Load objkeys with ELO_FNAME**
   - Query: `SELECT * FROM objkeys WHERE okeyname = 'ELO_FNAME'`
   - Creates lookup: `[objid] => filename` (e.g., "00000C1D.TIF")

2. **Build folder path lookup**
   - Query: `SELECT * FROM objekte WHERE objtype < 255` (folders only)
   - For each folder, traverse `objparent` chain to root
   - Creates lookup: `[objid] => "path/to/folder"`
   - Folder names are sanitized `objshort` values

3. **Process file objects**
   - Query: `SELECT * FROM objekte WHERE objtype > 254 AND objstatus != 1`
   - For each file:
     - Get filename from `ELO_FNAME` lookup
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
Stores additional metadata key-value pairs for objects

**Fields**:
- `parentid` (int) - References objekte.objid
- `okeyno` (int) - Key number/index
- `okeyname` (text) - Key name (e.g., "ELO_FNAME")
- `okeydata` (text) - Key value
- `okeysdata` (text) - Additional data

**Important Keys**:
- `ELO_FNAME` - Contains actual filename with extension (e.g., "00000C1D.TIF")

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
  objid=100, objtype=1,   objshort="Invoices", objparent=1     (folder)
  objid=200, objtype=1,   objshort="2024",     objparent=100   (folder)
  objid=300, objtype=256, objshort="INV-001",  objparent=200   (file)

objkeys:
  parentid=300, okeyname="ELO_FNAME", okeydata="00000C1D.TIF"
```

**Export result**:
```
Export/documents/Invoices/2024/INV-001.pdf
```

**Process**:
1. Build folder path for objid=200: "Invoices/2024"
2. File objid=300 has objparent=200
3. Get folder path: "Invoices/2024"
4. Get filename: ELO_FNAME = "00000C1D.TIF"
5. Convert TIF → PDF
6. Save as: "Export/documents/Invoices/2024/INV-001.pdf"

