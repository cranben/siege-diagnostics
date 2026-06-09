# ZIP Bundle Importer Direction

ZIP import should support collector-style support bundles, not only raw CSV
archives. The importer should treat known files as inputs and ignore unknown
optional files for now.

## Suggested Bundle Structure

```text
support_bundle.zip
|-- manifest.json
|-- cdr/
|   `-- cdr_export.csv
|-- domains/
|   `-- domains.csv
|-- system/
|   `-- system_info.txt
|-- registrations/
|   `-- registrations.csv
`-- logs/
```

`manifest.json` is optional initially, but should be read when present. It can
describe collector version, source identity, export timestamp, included files,
and bundle format version.

`cdr/cdr_export.csv` is the primary import file for the first ZIP importer
iteration. It should feed the same normalization path as existing CSV import.

`domains/domains.csv` can provide FusionPBX domain metadata used to associate
the import batch with one or more `fusionpbx_domains`.

Other files are reserved for later diagnostics and should not cause import
failure unless they are explicitly required by a future importer version.

## Import Behavior

1. Upload ZIP and create an import batch.
2. Extract the ZIP to a temporary directory.
3. Read `manifest.json` if present.
4. Import `cdr/cdr_export.csv` if present and valid.
5. Associate the batch with `fusionpbx_sources` when the source is known from
   the manifest, configuration, or collector metadata.
6. Associate the batch with one or more `fusionpbx_domains` when domain metadata
   is available.
7. Ignore unknown optional files and directories.
8. Fail clearly only when required import files are missing, unreadable, or
   invalid.

## Required Versus Optional

For the initial ZIP importer, the CDR CSV is required because diagnostics still
depend on normalized CDR records. Manifest, domain, system, registration, log,
and other collector files are optional unless the importer explicitly promotes
one of them to required input later.

Failures should identify the missing or invalid file and leave the batch in a
clear failed status. Partial optional data should not block CDR analysis.
