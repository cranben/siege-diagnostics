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
