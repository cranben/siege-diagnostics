<?php

require_once __DIR__ . '/bundle_cdr_helpers.php';

class BundleCdrAnalyzer
{
    public function analyzeCall(array $call, int $callIndex): array
    {
        $observations = [];

        $status = $this->callStatus($call);
        $duration = $this->callDurationSeconds($call);
        $disposition = $this->sipDisposition($call);
        $mos = $this->mosValue($call);
        $flowState = $this->flowState($call);
        $recordingState = $this->recordingState($call);
        $transcriptRequested = bundle_boolish(bundle_call_value($call, [
            ['transcript_metadata', 'requested'],
        ]));

        if ($disposition === 'send_bye') {
            $observations[] = $this->buildObservation(
                'local_sip_bye_observed',
                'Local SIP BYE observed',
                'High',
                'SIP teardown disposition was send_bye.',
                [
                    ['label' => 'SIP Disposition', 'value' => bundle_call_display_value($call, [['v_xml_cdr', 'sip_hangup_disposition'], ['sip_hangup_disposition'], ['sip_disposition']])],
                    ['label' => 'Hangup Cause', 'value' => bundle_call_display_value($call, [['v_xml_cdr', 'hangup_cause'], ['hangup_cause']])],
                    ['label' => 'Q.850', 'value' => bundle_call_display_value($call, [['v_xml_cdr', 'hangup_cause_q850'], ['q850']])],
                ],
                'This identifies teardown disposition only and does not explain why the call ended.'
            );
        }

        if ($disposition === 'recv_bye') {
            $observations[] = $this->buildObservation(
                'remote_sip_bye_observed',
                'Remote SIP BYE observed',
                'High',
                'SIP teardown disposition was recv_bye.',
                [
                    ['label' => 'SIP Disposition', 'value' => bundle_call_display_value($call, [['v_xml_cdr', 'sip_hangup_disposition'], ['sip_hangup_disposition'], ['sip_disposition']])],
                    ['label' => 'Hangup Cause', 'value' => bundle_call_display_value($call, [['v_xml_cdr', 'hangup_cause'], ['hangup_cause']])],
                    ['label' => 'Q.850', 'value' => bundle_call_display_value($call, [['v_xml_cdr', 'hangup_cause_q850'], ['q850']])],
                ],
                'This identifies teardown disposition only and does not explain why the call ended.'
            );
        }

        if ($status === 'answered' && $duration !== null && $duration < 10) {
            $observations[] = $this->buildObservation(
                'short_answered_call_observed',
                'Short answered call observed',
                'Medium-High',
                'Answered call duration was under 10 seconds.',
                [
                    ['label' => 'Status', 'value' => bundle_call_display_value($call, [['v_xml_cdr', 'status'], ['v_xml_cdr', 'call_status'], ['status'], ['call_status']])],
                    ['label' => 'Billsec', 'value' => bundle_call_display_value($call, [['v_xml_cdr', 'billsec'], ['billsec']])],
                    ['label' => 'Duration', 'value' => bundle_call_display_value($call, [['v_xml_cdr', 'duration'], ['duration']])],
                ],
                'This describes observed duration only and does not explain why the answered call was short.'
            );
        }

        if ($mos !== null && $mos < 3.5) {
            $observations[] = $this->buildObservation(
                'low_mos_observed',
                'Low MOS observed',
                'Medium',
                'Observed MOS was below 3.5.',
                [
                    ['label' => 'MOS', 'value' => bundle_call_display_value($call, [['v_xml_cdr', 'rtp_audio_in_mos'], ['v_xml_cdr', 'mos'], ['mos']])],
                    ['label' => 'Read Codec', 'value' => bundle_call_display_value($call, [['v_xml_cdr', 'read_codec'], ['read_codec']])],
                    ['label' => 'Write Codec', 'value' => bundle_call_display_value($call, [['v_xml_cdr', 'write_codec'], ['write_codec']])],
                ],
                'This captures the observed quality score only and does not identify the cause of reduced audio quality.'
            );
        }

        if ($flowState === 'Present') {
            $observations[] = $this->buildObservation(
                'call_flow_evidence_available',
                'Call flow evidence available',
                'High',
                'Supplemental parsed call-flow evidence was present.',
                [
                    ['label' => 'Call Flow Present', 'value' => bundle_call_display_value($call, [['v_xml_cdr_flow', 'present']])],
                    ['label' => 'JSON Valid', 'value' => bundle_call_display_value($call, [['v_xml_cdr_flow', 'json_valid']])],
                    ['label' => 'Size Bytes', 'value' => bundle_call_display_value($call, [['v_xml_cdr_flow', 'size_bytes']])],
                ],
                'This confirms the presence of supplemental call-flow evidence only and does not interpret the signaling sequence.'
            );
        }

        if ($flowState === 'Not available') {
            $observations[] = $this->buildObservation(
                'supplemental_call_flow_evidence_unavailable',
                'Supplemental call-flow evidence unavailable',
                'High',
                'Supplemental parsed call-flow evidence was explicitly not available.',
                [
                    ['label' => 'Call Flow Present', 'value' => bundle_call_display_value($call, [['v_xml_cdr_flow', 'present']])],
                    ['label' => 'JSON Valid', 'value' => bundle_call_display_value($call, [['v_xml_cdr_flow', 'json_valid']])],
                    ['label' => 'Size Bytes', 'value' => bundle_call_display_value($call, [['v_xml_cdr_flow', 'size_bytes']])],
                ],
                'This indicates supplemental call-flow availability only and does not explain why that evidence is absent.'
            );
        }

        if ($recordingState !== null && bundle_normalize_section_key($recordingState) === 'not_recorded') {
            $observations[] = $this->buildObservation(
                'recording_was_not_associated_with_this_call',
                'Recording was not associated with this call',
                'High',
                'Recording availability state was not_recorded.',
                [
                    ['label' => 'Recording State', 'value' => $recordingState],
                    ['label' => 'Record Path', 'value' => bundle_call_display_value($call, [['recording_metadata', 'record_path']])],
                    ['label' => 'Record Name', 'value' => bundle_call_display_value($call, [['recording_metadata', 'record_name']])],
                ],
                'This reflects the stored recording association state only and does not explain why no recording was associated.'
            );
        }

        if ($transcriptRequested === false) {
            $observations[] = $this->buildObservation(
                'transcript_was_not_requested',
                'Transcript was not requested',
                'High',
                'Transcript metadata shows the call was not requested for transcription.',
                [
                    ['label' => 'Requested', 'value' => bundle_call_display_value($call, [['transcript_metadata', 'requested']])],
                    ['label' => 'Queue UUID', 'value' => bundle_call_display_value($call, [['transcript_metadata', 'queue_uuid']])],
                    ['label' => 'Queue Status', 'value' => bundle_call_display_value($call, [['transcript_metadata', 'queue_status']])],
                ],
                'This reflects transcript request state only and does not explain why transcription was not requested.'
            );
        }

        return [
            'call_index' => $callIndex,
            'observations' => $observations,
        ];
    }

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

    private function buildObservation(
        string $key,
        string $title,
        string $confidence,
        string $summary,
        array $evidence,
        string $limitation
    ): array {
        $filteredEvidence = [];

        foreach ($evidence as $row) {
            $value = $row['value'] ?? null;

            if ($value === null || trim((string)$value) === '') {
                continue;
            }

            $filteredEvidence[] = [
                'label' => (string)$row['label'],
                'value' => (string)$value,
            ];
        }

        return [
            'key' => $key,
            'title' => $title,
            'confidence' => $confidence,
            'summary' => $summary,
            'evidence' => $filteredEvidence,
            'limitation' => $limitation,
        ];
    }
}
