
Export of the files:
- Name files like in objshort, but sanatize the name by removing invalid characters replacing characters like / with - and rest with a single space


Table [objekte]:
objtype
objid
objflags
objshort
objsreg
objidate
objxdate
objkey
objkind
objpath
objinfo
objmask
objdoc
objattach
objakey1
objakey2
objlkey1
objlkey2
objuser
objlock
objparent
objstatus
objhistcount
objdesc
objchildcount
objdeldate
objsyncdateloc
objsyncdaterem
objvtrep
objacl
replset
objguid
objtstamp
objsdata
objsdesc

Table [dochistory]:
objectid
documentid
userid
createdate
histid
histcomment
histversion
docmd5
docguid
doctstamp
docflags
docstatus
docsignature

Table [objkeys]:
parentid
okeyno
okeyname
okeydata
okeysdata

- Open database Archivdata/DMS.MDB
- Files have objtype > 255 
- objparant contains objid of the parent folder
- objstatus 1 means deleted
- objdoc is the id of the associated document file 
- objectid refers to objekte objid, but this table is not necessary
- objkeys parentid refers to objekte objid
- Get file name with okeyname ELO_FNAME for each file object
- To build the full file path use the file name (example 00000C1D.TIF):
  - folder name is UP plus first 6 characters of the file name with prefix UP: UP00000C
  - path is Archivdata/DMS_1/UP00000C/00000C1D.TIF

