<?php

$config = require __DIR__ . '/../app/config.php';

if ($argc < 2) {
    exit("Usage: php analyze_batch.php <batch_id>\n");
}

$batchId = (int)$argv[1];

$pdo = require __DIR__ . '/../app/db.php';

function json_array($value): array {
    if (is_array($value)) {
        return $value;
    }

    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function build_where(array $conditions, array &$params): string {
    $where = ["batch_id = :batch_id"];

    if (!empty($conditions['call_status'])) {
        $where[] = "call_status = :call_status";
        $params[':call_status'] = $conditions['call_status'];
    }

    if (!empty($conditions['status_not'])) {
        $where[] = "(call_status IS NULL OR call_status <> :status_not)";
        $params[':status_not'] = $conditions['status_not'];
    }

    if (!empty($conditions['direction'])) {
        $where[] = "direction = :direction";
        $params[':direction'] = $conditions['direction'];
    }

    if (!empty($conditions['sip_hangup_disposition'])) {
        $where[] = "sip_hangup_disposition = :sip_hangup_disposition";
        $params[':sip_hangup_disposition'] = $conditions['sip_hangup_disposition'];
    }

    if (isset($conditions['duration_less_than'])) {
        $where[] = "duration IS NOT NULL AND duration < :duration_less_than";
        $params[':duration_less_than'] = (int)$conditions['duration_less_than'];
    }

    if (isset($conditions['mos_less_than'])) {
        $where[] = "rtp_audio_in_mos IS NOT NULL AND rtp_audio_in_mos < :mos_less_than";
        $params[':mos_less_than'] = $conditions['mos_less_than'];
    }

    return implode(" AND ", $where);
}

function group_field_from_conditions(array $conditions): ?string {
    $allowed = [
        'extension',
        'domain_uuid',
        'domain_name',
        'destination_number',
        'caller_id_number',
        'sip_hangup_disposition',
        'call_status',
        'direction',
        'read_codec',
        'write_codec',
        'remote_media_ip',
    ];

    if (!empty($conditions['group_by']) && in_array($conditions['group_by'], $allowed, true)) {
        return $conditions['group_by'];
    }

    return null;
}

$pdo->beginTransaction();

try {
    $delete = $pdo->prepare("
        DELETE FROM diagnostic_findings
        WHERE batch_id = :batch_id
    ");
    $delete->execute([':batch_id' => $batchId]);

    $rules = $pdo->query("
        SELECT *
        FROM diagnostic_rules
        WHERE enabled = true
        ORDER BY severity DESC, confidence DESC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $findingInsert = $pdo->prepare("
        INSERT INTO diagnostic_findings (
            batch_id,
            rule_id,
            scenario,
            diagnostic_direction,
            severity,
            confidence,
            matched_call_count,
            group_key,
            group_value,
            evidence,
            recommended_next_step
        ) VALUES (
            :batch_id,
            :rule_id,
            :scenario,
            :diagnostic_direction,
            :severity,
            :confidence,
            :matched_call_count,
            :group_key,
            :group_value,
            :evidence,
            :recommended_next_step
        )
        RETURNING id
    ");

    $linkInsert = $pdo->prepare("
        INSERT INTO diagnostic_finding_calls (
            finding_id,
            cdr_record_id
        ) VALUES (
            :finding_id,
            :cdr_record_id
        )
    ");

    $findingsCreated = 0;
    $callsLinked = 0;

    foreach ($rules as $rule) {
        $conditions = json_array($rule['conditions']);
        $evidenceTemplate = json_array($rule['evidence_template']);

        $params = [':batch_id' => $batchId];
        $where = build_where($conditions, $params);

        $groupBy = group_field_from_conditions($conditions);
        $minimumCount = isset($conditions['minimum_count']) ? (int)$conditions['minimum_count'] : 1;

        if ($groupBy) {
            $sql = "
                SELECT {$groupBy} AS group_value, COUNT(*) AS total
                FROM cdr_records
                WHERE {$where}
                  AND {$groupBy} IS NOT NULL
                  AND {$groupBy}::text <> ''
                GROUP BY {$groupBy}
                HAVING COUNT(*) >= :minimum_count
                ORDER BY total DESC
            ";

            $params[':minimum_count'] = $minimumCount;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($groups as $group) {
                $callStmt = $pdo->prepare("
                    SELECT id
                    FROM cdr_records
                    WHERE {$where}
                      AND {$groupBy}::text = :group_value
                    ORDER BY id
                ");

                $callParams = $params;
                unset($callParams[':minimum_count']);
                $callParams[':group_value'] = $group['group_value'];

                $callStmt->execute($callParams);
                $calls = $callStmt->fetchAll(PDO::FETCH_ASSOC);

                $evidence = $evidenceTemplate;
                $evidence[] = "{$groupBy}=" . $group['group_value'];
                $evidence[] = "matched_call_count=" . count($calls);

                $findingInsert->execute([
                    ':batch_id' => $batchId,
                    ':rule_id' => $rule['id'],
                    ':scenario' => $rule['scenario'],
                    ':diagnostic_direction' => $rule['diagnostic_direction'],
                    ':severity' => $rule['severity'],
                    ':confidence' => $rule['confidence'],
                    ':matched_call_count' => count($calls),
                    ':group_key' => $groupBy,
                    ':group_value' => $group['group_value'],
                    ':evidence' => json_encode($evidence),
                    ':recommended_next_step' => $rule['recommended_next_step'],
                ]);

                $findingId = $findingInsert->fetchColumn();
                $findingsCreated++;

                foreach ($calls as $call) {
                    $linkInsert->execute([
                        ':finding_id' => $findingId,
                        ':cdr_record_id' => $call['id'],
                    ]);
                    $callsLinked++;
                }
            }
        } else {
            $stmt = $pdo->prepare("
                SELECT id
                FROM cdr_records
                WHERE {$where}
                ORDER BY id
            ");
            $stmt->execute($params);
            $calls = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($calls) < $minimumCount) {
                continue;
            }

            $evidence = $evidenceTemplate;
            $evidence[] = "matched_call_count=" . count($calls);

            $findingInsert->execute([
                ':batch_id' => $batchId,
                ':rule_id' => $rule['id'],
                ':scenario' => $rule['scenario'],
                ':diagnostic_direction' => $rule['diagnostic_direction'],
                ':severity' => $rule['severity'],
                ':confidence' => $rule['confidence'],
                ':matched_call_count' => count($calls),
                ':group_key' => null,
                ':group_value' => null,
                ':evidence' => json_encode($evidence),
                ':recommended_next_step' => $rule['recommended_next_step'],
            ]);

            $findingId = $findingInsert->fetchColumn();
            $findingsCreated++;

            foreach ($calls as $call) {
                $linkInsert->execute([
                    ':finding_id' => $findingId,
                    ':cdr_record_id' => $call['id'],
                ]);
                $callsLinked++;
            }
        }
    }

    $pdo->commit();

    echo "Analysis complete.\n";
    echo "Batch ID: {$batchId}\n";
    echo "Rules processed: " . count($rules) . "\n";
    echo "Findings created: {$findingsCreated}\n";
    echo "Matched call links: {$callsLinked}\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    exit("Analysis failed: " . $e->getMessage() . "\n");
}
