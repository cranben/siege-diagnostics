# Database Operations

Siege Diagnostics uses PostgreSQL. Keep fresh installation, live upgrades, and
schema checks as separate operational steps.

## Fresh Installs

Use `database/schema.sql` only when creating a fresh Siege Diagnostics database.
It defines the current tables, relationships, comments, and indexes expected by
the application.

## Live Upgrades

Use files under `database/migrations/*.sql` to update an existing database.
Migrations are additive changes intended to preserve existing data.

Run migrations as the PostgreSQL `postgres` user or another database
administrator account:

```bash
sudo -u postgres psql -d siege_diagnostics -f database/migrations/001_sync_live_schema.sql
```

Do not grant schema-altering privileges to the normal application database user
for routine operation.

## Schema Checks

Run the read-only database checker after deployment and after applying a
migration:

```bash
php scripts/check_database.php
```

`scripts/check_database.php` checks required tables, columns, indexes, and
cascade foreign keys without changing application data.

## Application User Permissions

The database credentials in `app/config.php` should belong to the normal
application user. That user should retain routine `SELECT`, `INSERT`, `UPDATE`,
and `DELETE` access only.

Use a PostgreSQL administrator account for fresh schema installation, live
migrations, and other DDL changes.

## Current Schema Direction

`cdr_records` remains the central analysis table. Importers should normalize
source data into this table before diagnostics run, whether the source is an
uploaded CSV, a FusionPBX collector bundle, or a future adapter.

Source and domain metadata exists to preserve where a batch came from without
making the diagnostic engine source-specific. It lets operators compare batches
from the same FusionPBX system or domain while keeping rules focused on
normalized CDR fields.

## FusionPBX Source Tracking

`fusionpbx_sources` identifies a FusionPBX system known to Siege Diagnostics.
Important fields:

- `source_key`: stable unique machine key for the source.
- `source_label`: human-readable source name for operators.
- `config_key`: unique key used by application configuration or collectors.
- `status`: source lifecycle state, currently expected to be values such as
  `active`.

`fusionpbx_domains` identifies domains within a FusionPBX source. A domain is
unique by `(fusionpbx_source_id, domain_uuid)`, allowing different sources to
contain domains with the same UUID without mixing their history.

`cdr_import_batches.fusionpbx_source_id` optionally links an import batch to the
FusionPBX source that produced it. CSV imports may leave this empty when the
source is not known.

`cdr_import_batch_domains` links an import batch to one or more FusionPBX
domains. This supports bundle imports that contain records for multiple domains
while preserving a single batch as the unit of upload and analysis.
