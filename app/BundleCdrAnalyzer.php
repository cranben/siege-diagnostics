<?php

require_once __DIR__ . '/bundle_cdr_helpers.php';

class BundleCdrAnalyzer
{
    public function analyze(array $selectedCallsSection): array
    {
        $calls = bundle_selected_calls($selectedCallsSection);
        $summary = [
            'selected_calls_count' => count($calls),
            'status_known_count' => 0,
            'answered_count' => 0,
            'failed_count' => 0,
            'missed_no_answer_count' => 0,
            'duration_known_count' => 0,
            'short_answered_calls_count' => 0,
            'disposition_known_count' => 0,
            'send_bye_count' => 0,
            'recv_bye_count' => 0,
            'mos_sample_count' => 0,
            'mos_average' => null,
            'mos_minimum' => null,
            'recording_state_counts' => [],
            'transcript_state_counts' => [],
            'flow_known_count' => 0,
            'call_flow_counts' => [
                'Present' => 0,
                'Not available' => 0,
                'Unknown' => 0,
            ],
        ];

        $mosTotal = 0.0;

        foreach ($calls as $call) {
            if (!is_array($call)) {
                continue;
            }

            $status = $this->callStatus($call);
            if ($status !== null) {
                $summary['status_known_count']++;

                if ($status === 'answered') {
                    $summary['answered_count']++;
                } elseif ($status === 'failed') {
                    $summary['failed_count']++;
                } elseif (in_array($status, ['missed', 'no_answer'], true)) {
                    $summary['missed_no_answer_count']++;
                }
            }

            $duration = $this->callDurationSeconds($call);
            if ($duration !== null) {
                $summary['duration_known_count']++;

                if ($status === 'answered' && $duration < 10) {
                    $summary['short_answered_calls_count']++;
                }
            }

            $disposition = $this->sipDisposition($call);
            if ($disposition !== null) {
                $summary['disposition_known_count']++;

                if ($disposition === 'send_bye') {
                    $summary['send_bye_count']++;
                } elseif ($disposition === 'recv_bye') {
                    $summary['recv_bye_count']++;
                }
            }

            $mos = $this->mosValue($call);
            if ($mos !== null) {
                $summary['mos_sample_count']++;
                $mosTotal += $mos;
                $summary['mos_minimum'] = $summary['mos_minimum'] === null
                    ? $mos
                    : min($summary['mos_minimum'], $mos);
            }

            $recordingState = $this->recordingState($call);
            $this->incrementCount(
                $summary['recording_state_counts'],
                $recordingState ?? 'Unknown'
            );

            $transcriptState = $this->transcriptState($call);
            $this->incrementCount(
                $summary['transcript_state_counts'],
                $transcriptState
            );

            $flowState = $this->flowState($call);
            if ($flowState !== 'Unknown') {
                $summary['flow_known_count']++;
            }
            $summary['call_flow_counts'][$flowState]++;
        }

        if ($summary['mos_sample_count'] > 0) {
            $summary['mos_average'] = round($mosTotal / $summary['mos_sample_count'], 3);
            $summary['mos_minimum'] = round((float)$summary['mos_minimum'], 3);
        }

        ksort($summary['recording_state_counts']);
        ksort($summary['transcript_state_counts']);

        return $summary;
    }

    private function callStatus(array $call): ?string
    {
        $status = bundle_call_display_value($call, [
            ['v_xml_cdr', 'status'],
            ['v_xml_cdr', 'call_status'],
            ['status'],
            ['call_status'],
        ]);

        if ($status === null) {
            return null;
        }

        $normalized = $this->normalizeValue($status);

        if ($normalized === 'no_answer' || $normalized === 'noanswer') {
            return 'no_answer';
        }

        if (in_array($normalized, ['answered', 'failed', 'missed'], true)) {
            return $normalized;
        }

        return $normalized !== '' ? $normalized : null;
    }

    private function callDurationSeconds(array $call): ?float
    {
        $value = bundle_call_value($call, [
            ['v_xml_cdr', 'billsec'],
            ['v_xml_cdr', 'duration'],
            ['billsec'],
            ['duration'],
        ]);

        if ($value === null || !is_numeric($value)) {
            return null;
        }

        return (float)$value;
    }

    private function sipDisposition(array $call): ?string
    {
        $value = bundle_call_display_value($call, [
            ['v_xml_cdr', 'sip_hangup_disposition'],
            ['sip_hangup_disposition'],
            ['sip_disposition'],
        ]);

        if ($value === null) {
            return null;
        }

        $normalized = $this->normalizeValue($value);
        return $normalized !== '' ? $normalized : null;
    }

    private function mosValue(array $call): ?float
    {
        $value = bundle_call_value($call, [
            ['v_xml_cdr', 'rtp_audio_in_mos'],
            ['v_xml_cdr', 'mos'],
            ['mos'],
        ]);

        if ($value === null || !is_numeric($value)) {
            return null;
        }

        return (float)$value;
    }

    private function recordingState(array $call): ?string
    {
        return bundle_recording_state($call);
    }

    private function transcriptState(array $call): string
    {
        $requested = bundle_boolish(bundle_call_value($call, [
            ['transcript_metadata', 'requested'],
        ]));

        if ($requested === false) {
            return 'Not requested';
        }

        if ($requested === true) {
            $queueStatus = bundle_call_display_value($call, [
                ['transcript_metadata', 'queue_status'],
            ]);

            return $queueStatus ?? 'Requested';
        }

        return 'Unknown';
    }

    private function flowState(array $call): string
    {
        $present = bundle_boolish(bundle_call_value($call, [
            ['v_xml_cdr_flow', 'present'],
        ]));

        if ($present === true) {
            return 'Present';
        }

        if ($present === false) {
            return 'Not available';
        }

        return 'Unknown';
    }

    private function incrementCount(array &$counts, string $label): void
    {
        if (!isset($counts[$label])) {
            $counts[$label] = 0;
        }

        $counts[$label]++;
    }

    private function normalizeValue(string $value): string
    {
        return bundle_normalize_section_key($value);
    }
}
