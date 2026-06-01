-- Siege Diagnostics PostgreSQL schema bootstrap.
--
-- The schema preserves the current workflow:
-- upload batch -> normalized CDR records -> rule-based findings -> matched calls.
-- It is intentionally limited to tables already required by the application.

BEGIN;

CREATE TABLE IF NOT EXISTS cdr_import_batches (
    id              BIGSERIAL PRIMARY KEY,
    original_filename TEXT NOT NULL,
    stored_filename   TEXT NOT NULL,
    file_type         TEXT NOT NULL,
    source_type       TEXT NOT NULL DEFAULT 'csv',
    source_label      TEXT,
    status            TEXT NOT NULL,
    total_rows        INTEGER NOT NULL DEFAULT 0,
    imported_rows     INTEGER NOT NULL DEFAULT 0,
    failed_rows       INTEGER NOT NULL DEFAULT 0,
    uploaded_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    imported_at       TIMESTAMPTZ
);

COMMENT ON TABLE cdr_import_batches IS
    'Upload and import ledger. Each stored CDR artifact receives a stable batch ID before normalization begins.';
COMMENT ON COLUMN cdr_import_batches.status IS
    'Current import stage, such as uploaded, imported, or imported_with_errors.';
COMMENT ON COLUMN cdr_import_batches.stored_filename IS
    'Opaque server-side filename used to locate the uploaded artifact.';
COMMENT ON COLUMN cdr_import_batches.source_type IS
    'Stable machine-readable ingestion adapter or source category.';
COMMENT ON COLUMN cdr_import_batches.source_label IS
    'Optional human-readable description of the ingestion source.';

CREATE TABLE IF NOT EXISTS cdr_records (
    id                                  BIGSERIAL PRIMARY KEY,
    batch_id                            BIGINT NOT NULL REFERENCES cdr_import_batches(id) ON DELETE CASCADE,
    uuid                                TEXT,
    domain_name                         TEXT,
    domain_uuid                         TEXT,
    extension                           TEXT,
    caller_id_number                    TEXT,
    destination_number                  TEXT,
    direction                           TEXT,
    start_stamp                         TIMESTAMPTZ,
    answer_stamp                        TIMESTAMPTZ,
    end_stamp                           TIMESTAMPTZ,
    duration                            INTEGER,
    billsec                             INTEGER,
    hangup_cause                        TEXT,
    bridge_hangup_cause                 TEXT,
    sip_hangup_disposition              TEXT,
    sip_term_status                     TEXT,
    q850_cause                          TEXT,
    read_codec                          TEXT,
    write_codec                         TEXT,
    rtp_audio_in_mos                    NUMERIC,
    rtp_audio_in_jitter_min_variance    NUMERIC,
    rtp_audio_in_jitter_max_variance    NUMERIC,
    rtp_audio_in_packet_count           INTEGER,
    rtp_audio_in_skip_packet_count      INTEGER,
    remote_media_ip                     INET,
    network_addr                        INET,
    user_agent                          TEXT,
    sip_call_id                         TEXT,
    sip_from_host                       TEXT,
    sip_to_host                         TEXT,
    sip_req_uri                         TEXT,
    sip_user_agent                      TEXT,
    sip_network_ip                      INET,
    call_direction                      TEXT,
    call_status                         TEXT,
    recording_file                      TEXT,
    accountcode                         TEXT,
    context                             TEXT,
    cc_queue                            TEXT,
    cc_agent                            TEXT,
    cc_member_uuid                      TEXT,
    destination_country                 TEXT,
    destination_type                    TEXT,
    raw_data                            JSONB NOT NULL,
    created_at                          TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE cdr_records IS
    'Source-agnostic normalized CDR records used as input by the diagnostic rule engine.';
COMMENT ON COLUMN cdr_records.batch_id IS
    'Import batch that supplied this normalized call record.';
COMMENT ON COLUMN cdr_records.raw_data IS
    'Original imported row after header normalization, retained for auditability and mapping improvements.';
COMMENT ON COLUMN cdr_records.created_at IS
    'Time the normalized record entered Siege Diagnostics, not the original call time.';

CREATE TABLE IF NOT EXISTS diagnostic_rules (
    id                      BIGSERIAL PRIMARY KEY,
    rule_key                TEXT NOT NULL UNIQUE,
    scenario                TEXT NOT NULL,
    enabled                 BOOLEAN NOT NULL DEFAULT true,
    severity                INTEGER NOT NULL,
    confidence              INTEGER NOT NULL,
    diagnostic_direction    TEXT NOT NULL,
    recommended_next_step   TEXT NOT NULL,
    conditions              JSONB NOT NULL,
    evidence_template       JSONB NOT NULL,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE diagnostic_rules IS
    'Tunable diagnostic rules. A rule maps an operator-facing scenario to JSON pattern conditions and evidence text.';
COMMENT ON COLUMN diagnostic_rules.rule_key IS
    'Stable identity used by the rule seeder for ON CONFLICT updates.';
COMMENT ON COLUMN diagnostic_rules.conditions IS
    'JSON pattern interpreted by scripts/analyze_batch.php.';
COMMENT ON COLUMN diagnostic_rules.evidence_template IS
    'Human-readable evidence facts copied into materialized findings.';

CREATE TABLE IF NOT EXISTS diagnostic_findings (
    id                      BIGSERIAL PRIMARY KEY,
    batch_id                BIGINT NOT NULL REFERENCES cdr_import_batches(id) ON DELETE CASCADE,
    rule_id                 BIGINT NOT NULL REFERENCES diagnostic_rules(id),
    scenario                TEXT NOT NULL,
    diagnostic_direction    TEXT NOT NULL,
    severity                INTEGER NOT NULL,
    confidence              INTEGER NOT NULL,
    matched_call_count      INTEGER NOT NULL,
    group_key               TEXT,
    group_value             TEXT,
    evidence                JSONB NOT NULL,
    recommended_next_step   TEXT NOT NULL,
    created_at              TIMESTAMPTZ NOT NULL DEFAULT now()
);

COMMENT ON TABLE diagnostic_findings IS
    'Materialized analysis results for one batch. Findings snapshot scenario, evidence, and recommendation text from a matched rule.';
COMMENT ON COLUMN diagnostic_findings.evidence IS
    'Evidence template plus measured context such as matched call count and optional group value.';
COMMENT ON COLUMN diagnostic_findings.group_key IS
    'Allowlisted CDR field used to cluster calls when a rule requests grouping.';

CREATE TABLE IF NOT EXISTS diagnostic_finding_calls (
    finding_id      BIGINT NOT NULL REFERENCES diagnostic_findings(id) ON DELETE CASCADE,
    cdr_record_id   BIGINT NOT NULL REFERENCES cdr_records(id) ON DELETE CASCADE,
    PRIMARY KEY (finding_id, cdr_record_id)
);

COMMENT ON TABLE diagnostic_finding_calls IS
    'Links a materialized finding to the concrete call details that support its evidence.';

CREATE INDEX IF NOT EXISTS idx_cdr_records_batch_id
    ON cdr_records (batch_id);

CREATE INDEX IF NOT EXISTS idx_cdr_records_call_status
    ON cdr_records (call_status);

CREATE INDEX IF NOT EXISTS idx_cdr_records_batch_direction
    ON cdr_records (batch_id, direction);

CREATE INDEX IF NOT EXISTS idx_cdr_records_batch_sip_hangup_disposition
    ON cdr_records (batch_id, sip_hangup_disposition);

CREATE INDEX IF NOT EXISTS idx_cdr_records_batch_extension
    ON cdr_records (batch_id, extension);

CREATE INDEX IF NOT EXISTS idx_cdr_records_batch_duration
    ON cdr_records (batch_id, duration);

CREATE INDEX IF NOT EXISTS idx_cdr_records_batch_rtp_audio_in_mos
    ON cdr_records (batch_id, rtp_audio_in_mos);

CREATE INDEX IF NOT EXISTS idx_cdr_records_batch_call_status
    ON cdr_records (batch_id, call_status);

CREATE INDEX IF NOT EXISTS idx_diagnostic_rules_enabled_priority
    ON diagnostic_rules (enabled, severity DESC, confidence DESC, id);

CREATE INDEX IF NOT EXISTS idx_diagnostic_findings_batch_priority
    ON diagnostic_findings (batch_id, severity DESC, confidence DESC, matched_call_count DESC, id);

CREATE INDEX IF NOT EXISTS idx_diagnostic_finding_calls_cdr_record_id
    ON diagnostic_finding_calls (cdr_record_id);

COMMIT;
