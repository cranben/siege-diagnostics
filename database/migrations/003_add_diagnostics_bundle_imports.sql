-- Store inspected FusionPBX Diagnostics bundle imports separately from CDR batches.
-- This migration is additive and safe to rerun.

BEGIN;

CREATE TABLE IF NOT EXISTS diagnostics_bundle_imports (
    id                BIGSERIAL PRIMARY KEY,
    original_filename TEXT NOT NULL,
    collection_id     TEXT,
    generated_at      TEXT,
    collector_version TEXT,
    schema_version    TEXT,
    manifest_json     JSONB,
    collector_json    JSONB,
    sections_json     JSONB NOT NULL DEFAULT '[]'::jsonb,
    warnings_json     JSONB NOT NULL DEFAULT '[]'::jsonb,
    errors_json       JSONB NOT NULL DEFAULT '[]'::jsonb,
    status            TEXT NOT NULL,
    imported_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE diagnostics_bundle_imports IS
    'Persistent inspection ledger for FusionPBX Diagnostics ZIP bundles. These are not CDR analysis batches.';
COMMENT ON COLUMN diagnostics_bundle_imports.sections_json IS
    'Parsed sections/*.json documents stored with their bundle-relative filenames.';
COMMENT ON COLUMN diagnostics_bundle_imports.status IS
    'Inspection status, such as inspected or inspected_with_errors. Analysis is not run for this table.';

CREATE INDEX IF NOT EXISTS idx_diagnostics_bundle_imports_imported_at
    ON diagnostics_bundle_imports (imported_at DESC, id DESC);

CREATE INDEX IF NOT EXISTS idx_diagnostics_bundle_imports_collection_id
    ON diagnostics_bundle_imports (collection_id);

COMMIT;
