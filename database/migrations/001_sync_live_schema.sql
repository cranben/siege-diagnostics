-- Bring an existing Siege Diagnostics database in line with the live schema.
-- This migration is additive and safe to rerun.

BEGIN;

ALTER TABLE cdr_import_batches
    ADD COLUMN IF NOT EXISTS source_type TEXT NOT NULL DEFAULT 'csv';

ALTER TABLE cdr_import_batches
    ADD COLUMN IF NOT EXISTS source_label TEXT;

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
