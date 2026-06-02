-- Add support-tool FusionPBX source and domain metadata.
-- This migration is additive and safe to rerun.

BEGIN;

CREATE TABLE IF NOT EXISTS fusionpbx_sources (
    id           BIGSERIAL PRIMARY KEY,
    source_key   TEXT NOT NULL UNIQUE,
    source_label TEXT NOT NULL,
    config_key   TEXT NOT NULL UNIQUE,
    status       TEXT NOT NULL DEFAULT 'active',
    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS fusionpbx_domains (
    id                  BIGSERIAL PRIMARY KEY,
    fusionpbx_source_id BIGINT NOT NULL
        REFERENCES fusionpbx_sources(id) ON DELETE RESTRICT,
    domain_uuid         TEXT NOT NULL,
    domain_name         TEXT,
    status              TEXT NOT NULL DEFAULT 'active',
    created_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (fusionpbx_source_id, domain_uuid)
);

ALTER TABLE cdr_import_batches
    ADD COLUMN IF NOT EXISTS fusionpbx_source_id BIGINT
        REFERENCES fusionpbx_sources(id) ON DELETE RESTRICT;

CREATE TABLE IF NOT EXISTS cdr_import_batch_domains (
    batch_id            BIGINT NOT NULL
        REFERENCES cdr_import_batches(id) ON DELETE CASCADE,
    fusionpbx_domain_id BIGINT NOT NULL
        REFERENCES fusionpbx_domains(id) ON DELETE RESTRICT,
    PRIMARY KEY (batch_id, fusionpbx_domain_id)
);

CREATE INDEX IF NOT EXISTS idx_fusionpbx_sources_status
    ON fusionpbx_sources (status);

CREATE INDEX IF NOT EXISTS idx_fusionpbx_domains_source_id
    ON fusionpbx_domains (fusionpbx_source_id);

CREATE INDEX IF NOT EXISTS idx_fusionpbx_domains_status
    ON fusionpbx_domains (status);

CREATE INDEX IF NOT EXISTS idx_cdr_import_batches_fusionpbx_source_id
    ON cdr_import_batches (fusionpbx_source_id);

CREATE INDEX IF NOT EXISTS idx_cdr_import_batch_domains_domain_id
    ON cdr_import_batch_domains (fusionpbx_domain_id);

COMMIT;
