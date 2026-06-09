# Architecture Direction

Siege Diagnostics keeps collection and analysis separate. Import and collector
code can be source-specific, but analysis should operate on normalized records.

## Layers

### Source Layer

The source layer accepts data from CSV upload, ZIP support bundles, and future
collectors. Each source adapter is responsible for reading its own files and
mapping source fields into the normalized shape expected by the application.

CSV import already exists. ZIP bundle import is the next priority and should be
designed as another source adapter, not a replacement for CSV import.

### Normalized Analysis Layer

All sources normalize call data into `cdr_records`. This table is the central
analysis surface and should remain source-agnostic.

Import batches in `cdr_import_batches` track the uploaded artifact and import
status. When known, batches can also point to a FusionPBX source and one or more
FusionPBX domains.

### Diagnostic/Rules Layer

Diagnostic rules evaluate normalized CDR fields, not raw collector files. Rules
should not need to know whether a record came from CSV, ZIP, FusionPBX, or a
future collection method.

### Findings Layer

Analysis results are materialized in `diagnostic_findings`, with supporting call
links in `diagnostic_finding_calls`. Findings snapshot the rule result for a
batch so later reporting does not need to rerun the rule engine just to display
existing results.

## Design Notes

- Keep collection and analysis separate.
- Keep importers source-aware, but keep diagnostics source-agnostic.
- Normalize before analysis.
- Preserve source/domain context as metadata, not as rule-engine branching.
