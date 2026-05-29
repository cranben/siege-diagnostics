<?php
// will become this as this moves to app/data_sources/CsvImportSource.php
//$source = new CsvImportSource($pdo, $config);
//$result = $source->import($batchId);

$config = require __DIR__ . '/../app/config.php';

if ($argc < 2) {
    exit("Usage: php import_csv.php <batch_id>\n");
}

$batchId = (int)$argv[1];

$pdo = require __DIR__ . '/../app/db.php';

function clean_key(string $key): string
{
    $key = strtolower(trim($key));
    $key = str_replace([' ', '-', '.'], '_', $key);
    return preg_replace('/[^a-z0-9_]/', '', $key);
}

function value(array $row, array $keys): ?string
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }
    return null;
}

function int_value(?string $value): ?int
{
    return is_numeric($value) ? (int)$value : null;
}

function numeric_value(?string $value): ?string
{
    return is_numeric($value) ? $value : null;
}

function ip_value(?string $value): ?string
{
    return filter_var($value, FILTER_VALIDATE_IP) ? $value : null;
}

$batchStmt = $pdo->prepare("
    SELECT id, stored_filename
    FROM cdr_import_batches
    WHERE id = :id
");

$batchStmt->execute([':id' => $batchId]);
$batch = $batchStmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    exit("Batch not found: {$batchId}\n");
}

$filePath = rtrim($config['upload_dir'], '/') . '/' . $batch['stored_filename'];

if (!is_readable($filePath)) {
    exit("File not readable: {$filePath}\n");
}

$handle = fopen($filePath, 'r');

if (!$handle) {
    exit("Could not open file.\n");
}

$headers = fgetcsv($handle);

if (!$headers) {
    exit("CSV has no header row.\n");
}

$headers = array_map('clean_key', $headers);

$insert = $pdo->prepare("
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

// $pdo->beginTransaction();

try {
    while (($data = fgetcsv($handle)) !== false) {
        $totalRows++;

        $row = [];
        foreach ($headers as $index => $header) {
            $row[$header] = $data[$index] ?? null;
        }

        try {
            $insert->execute([
                ':batch_id' => $batchId,

                ':uuid' => value($row, ['uuid', 'call_uuid', 'xml_cdr_uuid']),
                ':domain_name' => value($row, ['domain_name', 'domain']),
                ':domain_uuid' => value($row, ['domain_uuid']),
		':extension' => value($row, ['extension', 'caller_extension']),

                ':caller_id_number' => value($row, ['caller_id_number', 'cid_num', 'caller_number']),
                ':destination_number' => value($row, ['destination_number', 'dest', 'destination']),

                ':direction' => value($row, ['direction']),
                ':start_stamp' => value($row, ['start_stamp', 'start_time']),
                ':answer_stamp' => value($row, ['answer_stamp', 'answer_time']),
                ':end_stamp' => value($row, ['end_stamp', 'end_time']),

                ':duration' => int_value(value($row, ['duration'])),
                ':billsec' => int_value(value($row, ['billsec'])),

                ':hangup_cause' => value($row, ['hangup_cause']),
                ':bridge_hangup_cause' => value($row, ['bridge_hangup_cause']),
                ':sip_hangup_disposition' => value($row, ['sip_hangup_disposition']),
                ':sip_term_status' => value($row, ['sip_term_status', 'sip_status']),
                ':q850_cause' => value($row, ['q850_cause']),

                ':read_codec' => value($row, ['read_codec']),
                ':write_codec' => value($row, ['write_codec']),

                ':rtp_audio_in_mos' => numeric_value(value($row, ['rtp_audio_in_mos', 'mos'])),
                ':rtp_audio_in_jitter_min_variance' => numeric_value(value($row, ['rtp_audio_in_jitter_min_variance'])),
                ':rtp_audio_in_jitter_max_variance' => numeric_value(value($row, ['rtp_audio_in_jitter_max_variance'])),
                ':rtp_audio_in_packet_count' => int_value(value($row, ['rtp_audio_in_packet_count'])),
                ':rtp_audio_in_skip_packet_count' => int_value(value($row, ['rtp_audio_in_skip_packet_count'])),

                ':remote_media_ip' => ip_value(value($row, ['remote_media_ip', 'rtp_remote_audio_ip'])),
                ':network_addr' => ip_value(value($row, ['network_addr'])),

                ':user_agent' => value($row, ['user_agent']),
                ':sip_call_id' => value($row, ['sip_call_id']),
                ':sip_from_host' => value($row, ['sip_from_host']),
                ':sip_to_host' => value($row, ['sip_to_host']),
                ':sip_req_uri' => value($row, ['sip_req_uri']),
                ':sip_user_agent' => value($row, ['sip_user_agent']),
                ':sip_network_ip' => ip_value(value($row, ['sip_network_ip'])),

                ':call_direction' => value($row, ['call_direction']),
                ':call_status' => value($row, ['call_status', 'status']),

                ':recording_file' => value($row, ['recording_file', 'record_name', 'record_path']),
                ':accountcode' => value($row, ['accountcode']),
                ':context' => value($row, ['context']),

                ':cc_queue' => value($row, ['cc_queue']),
                ':cc_agent' => value($row, ['cc_agent']),
                ':cc_member_uuid' => value($row, ['cc_member_uuid']),

                ':destination_country' => value($row, ['destination_country']),
                ':destination_type' => value($row, ['destination_type']),

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
    fclose($handle);

    $update = $pdo->prepare("
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

   // $pdo->commit();

    echo "Import complete.\n";
    echo "Batch ID: {$batchId}\n";
    echo "Total rows: {$totalRows}\n";
    echo "Imported rows: {$importedRows}\n";
    echo "Failed rows: {$failedRows}\n";

    } catch (Throwable $e) {
//      $pdo->rollBack();
        exit("Import failed: " . $e->getMessage() . "\n");
}
