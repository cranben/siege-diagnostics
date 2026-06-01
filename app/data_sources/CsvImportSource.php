<?php

require_once __DIR__ . '/CdrSourceInterface.php';

// CSV adapter scaffold for the staged source-layer migration. The live import
// workflow remains in scripts/import_csv.php until orchestration is moved in a
// later step, so this class is not wired into application behavior yet.
class CsvImportSource implements CdrSourceInterface
{
    public function __construct(
        private PDO $pdo,
        private array $config
    ) {}

    public function import(int $batchId): array
    {
        $batchStmt = $this->pdo->prepare("
            SELECT id, stored_filename
            FROM cdr_import_batches
            WHERE id = :id
        ");

        $batchStmt->execute([':id' => $batchId]);
        $batch = $batchStmt->fetch(PDO::FETCH_ASSOC);

        if (!$batch) {
            throw new Exception("Batch not found: {$batchId}");
        }

        $filePath = rtrim($this->config['upload_dir'], '/') . '/' . $batch['stored_filename'];

        if (!is_readable($filePath)) {
            throw new Exception("File not readable: {$filePath}");
        }

        $handle = fopen($filePath, 'r');

        if (!$handle) {
            throw new Exception("Could not open file.");
        }

        try {
            $headers = fgetcsv($handle);

            if (!$headers) {
                throw new Exception("CSV has no header row.");
            }

            $headers = array_map([$this, 'cleanKey'], $headers);

            // cdr_records is the source-agnostic diagnostic input model. Row
            // execution remains in scripts/import_csv.php until the next step.
            $insert = $this->pdo->prepare("
                INSERT INTO cdr_records (
                    batch_id,
                    uuid,
                    domain_name,
                    domain_uuid,
                    extension,
                    caller_id_number,
                    destination_number,
                    direction,
                    start_stamp,
                    answer_stamp,
                    end_stamp,
                    duration,
                    billsec,
                    hangup_cause,
                    bridge_hangup_cause,
                    sip_hangup_disposition,
                    sip_term_status,
                    q850_cause,
                    read_codec,
                    write_codec,
                    rtp_audio_in_mos,
                    rtp_audio_in_jitter_min_variance,
                    rtp_audio_in_jitter_max_variance,
                    rtp_audio_in_packet_count,
                    rtp_audio_in_skip_packet_count,
                    remote_media_ip,
                    network_addr,
                    user_agent,
                    sip_call_id,
                    sip_from_host,
                    sip_to_host,
                    sip_req_uri,
                    sip_user_agent,
                    sip_network_ip,
                    call_direction,
                    call_status,
                    recording_file,
                    accountcode,
                    context,
                    cc_queue,
                    cc_agent,
                    cc_member_uuid,
                    destination_country,
                    destination_type,
                    raw_data
                ) VALUES (
                    :batch_id,
                    :uuid,
                    :domain_name,
                    :domain_uuid,
                    :extension,
                    :caller_id_number,
                    :destination_number,
                    :direction,
                    :start_stamp,
                    :answer_stamp,
                    :end_stamp,
                    :duration,
                    :billsec,
                    :hangup_cause,
                    :bridge_hangup_cause,
                    :sip_hangup_disposition,
                    :sip_term_status,
                    :q850_cause,
                    :read_codec,
                    :write_codec,
                    :rtp_audio_in_mos,
                    :rtp_audio_in_jitter_min_variance,
                    :rtp_audio_in_jitter_max_variance,
                    :rtp_audio_in_packet_count,
                    :rtp_audio_in_skip_packet_count,
                    :remote_media_ip,
                    :network_addr,
                    :user_agent,
                    :sip_call_id,
                    :sip_from_host,
                    :sip_to_host,
                    :sip_req_uri,
                    :sip_user_agent,
                    :sip_network_ip,
                    :call_direction,
                    :call_status,
                    :recording_file,
                    :accountcode,
                    :context,
                    :cc_queue,
                    :cc_agent,
                    :cc_member_uuid,
                    :destination_country,
                    :destination_type,
                    :raw_data
                )
            ");

            $totalRows = 0;
            $importedRows = 0;
            $failedRows = 0;

            // Imports currently commit row-by-row. Production hardening should
            // define retry behavior before changing this partial-import behavior.
            while (($data = fgetcsv($handle)) !== false) {
                $totalRows++;

                $row = [];
                foreach ($headers as $index => $header) {
                    $row[$header] = $data[$index] ?? null;
                }

                try {
                    $insert->execute([
                        ':batch_id' => $batchId,

                        ':uuid' => $this->value($row, ['uuid', 'call_uuid', 'xml_cdr_uuid']),
                        ':domain_name' => $this->value($row, ['domain_name', 'domain']),
                        ':domain_uuid' => $this->value($row, ['domain_uuid']),
                        ':extension' => $this->value($row, ['extension', 'caller_extension']),

                        ':caller_id_number' => $this->value($row, ['caller_id_number', 'cid_num', 'caller_number']),
                        ':destination_number' => $this->value($row, ['destination_number', 'dest', 'destination']),

                        ':direction' => $this->value($row, ['direction']),
                        ':start_stamp' => $this->value($row, ['start_stamp', 'start_time']),
                        ':answer_stamp' => $this->value($row, ['answer_stamp', 'answer_time']),
                        ':end_stamp' => $this->value($row, ['end_stamp', 'end_time']),

                        ':duration' => $this->intValue($this->value($row, ['duration'])),
                        ':billsec' => $this->intValue($this->value($row, ['billsec'])),

                        ':hangup_cause' => $this->value($row, ['hangup_cause']),
                        ':bridge_hangup_cause' => $this->value($row, ['bridge_hangup_cause']),
                        ':sip_hangup_disposition' => $this->value($row, ['sip_hangup_disposition']),
                        ':sip_term_status' => $this->value($row, ['sip_term_status', 'sip_status']),
                        ':q850_cause' => $this->value($row, ['q850_cause']),

                        ':read_codec' => $this->value($row, ['read_codec']),
                        ':write_codec' => $this->value($row, ['write_codec']),

                        ':rtp_audio_in_mos' => $this->numericValue($this->value($row, ['rtp_audio_in_mos', 'mos'])),
                        ':rtp_audio_in_jitter_min_variance' => $this->numericValue($this->value($row, ['rtp_audio_in_jitter_min_variance'])),
                        ':rtp_audio_in_jitter_max_variance' => $this->numericValue($this->value($row, ['rtp_audio_in_jitter_max_variance'])),
                        ':rtp_audio_in_packet_count' => $this->intValue($this->value($row, ['rtp_audio_in_packet_count'])),
                        ':rtp_audio_in_skip_packet_count' => $this->intValue($this->value($row, ['rtp_audio_in_skip_packet_count'])),

                        ':remote_media_ip' => $this->ipValue($this->value($row, ['remote_media_ip', 'rtp_remote_audio_ip'])),
                        ':network_addr' => $this->ipValue($this->value($row, ['network_addr'])),

                        ':user_agent' => $this->value($row, ['user_agent']),
                        ':sip_call_id' => $this->value($row, ['sip_call_id']),
                        ':sip_from_host' => $this->value($row, ['sip_from_host']),
                        ':sip_to_host' => $this->value($row, ['sip_to_host']),
                        ':sip_req_uri' => $this->value($row, ['sip_req_uri']),
                        ':sip_user_agent' => $this->value($row, ['sip_user_agent']),
                        ':sip_network_ip' => $this->ipValue($this->value($row, ['sip_network_ip'])),

                        ':call_direction' => $this->value($row, ['call_direction']),
                        ':call_status' => $this->value($row, ['call_status', 'status']),

                        ':recording_file' => $this->value($row, ['recording_file', 'record_name', 'record_path']),
                        ':accountcode' => $this->value($row, ['accountcode']),
                        ':context' => $this->value($row, ['context']),

                        ':cc_queue' => $this->value($row, ['cc_queue']),
                        ':cc_agent' => $this->value($row, ['cc_agent']),
                        ':cc_member_uuid' => $this->value($row, ['cc_member_uuid']),

                        ':destination_country' => $this->value($row, ['destination_country']),
                        ':destination_type' => $this->value($row, ['destination_type']),

                        // Preserve the normalized-header source row for auditability.
                        ':raw_data' => json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ]);

                    $importedRows++;
                } catch (Throwable $e) {
                    $failedRows++;

                    echo "Row {$totalRows} failed: " . $e->getMessage() . "\n";

                    if ($failedRows >= 5) {
                        echo "Stopping after 5 failures so we can debug.\n";
                        break;
                    }
                }
            }

            $update = $this->pdo->prepare("
                UPDATE cdr_import_batches
                SET
                    status = :status,
                    imported_at = now(),
                    total_rows = :total_rows,
                    imported_rows = :imported_rows,
                    failed_rows = :failed_rows
                WHERE id = :id
            ");

            $update->execute([
                ':status' => $failedRows > 0 ? 'imported_with_errors' : 'imported',
                ':total_rows' => $totalRows,
                ':imported_rows' => $importedRows,
                ':failed_rows' => $failedRows,
                ':id' => $batchId,
            ]);

            return [
                'batch_id' => $batchId,
                'total_rows' => $totalRows,
                'imported_rows' => $importedRows,
                'failed_rows' => $failedRows,
            ];
        } finally {
            fclose($handle);
        }
    }

    private function cleanKey(string $key): string
    {
        // FusionPBX exports can label equivalent columns differently. Keep this
        // behavior aligned with clean_key() in scripts/import_csv.php.
        $key = strtolower(trim($key));
        $key = str_replace([' ', '-', '.'], '_', $key);
        return preg_replace('/[^a-z0-9_]/', '', $key);
    }

    private function value(array $row, array $keys): ?string
    {
        // Prefer the first populated alias so normalization remains compatible
        // with the current CSV import path.
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
                return trim((string)$row[$key]);
            }
        }
        return null;
    }

    private function intValue(?string $value): ?int
    {
        return is_numeric($value) ? (int)$value : null;
    }

    private function numericValue(?string $value): ?string
    {
        return is_numeric($value) ? $value : null;
    }

    private function ipValue(?string $value): ?string
    {
        return filter_var($value, FILTER_VALIDATE_IP) ? $value : null;
    }
}
