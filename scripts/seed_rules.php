<?php

$config = require __DIR__ . '/../app/config.php';
$pdo = require __DIR__ . '/../app/db.php';


$rules = [
    [
        'rule_key' => 'short_answered_calls',
        'scenario' => 'Short Calls',
        'severity' => 2,
        'confidence' => 60,
        'diagnostic_direction' => 'Short completed calls detected',
        'recommended_next_step' => 'Review whether these were expected user behavior, voicemail attempts, abandoned calls, or early disconnects.',
        'conditions' => [
            'call_status' => 'answered',
            'duration_less_than' => 30,
        ],
        'evidence_template' => [
            'status=answered',
            'duration < 30 seconds',
        ],
    ],
    [
        'rule_key' => 'failed_inbound_calls',
        'scenario' => 'Failed Inbound',
        'severity' => 3,
        'confidence' => 65,
        'diagnostic_direction' => 'Inbound calls failed or were not completed',
        'recommended_next_step' => 'Check inbound routes, destination formatting, DID delivery format, and carrier SIP response details.',
        'conditions' => [
            'direction' => 'inbound',
            'status_not' => 'answered',
        ],
        'evidence_template' => [
            'direction=inbound',
            'status is not answered',
        ],
    ],
    [
        'rule_key' => 'failed_outbound_calls',
        'scenario' => 'Failed Outbound',
        'severity' => 3,
        'confidence' => 65,
        'diagnostic_direction' => 'Outbound calls failed or were not completed',
        'recommended_next_step' => 'Check outbound route, caller ID format, carrier rejection, SIP status, and account/trunk permissions.',
        'conditions' => [
            'direction' => 'outbound',
            'status_not' => 'answered',
        ],
        'evidence_template' => [
            'direction=outbound',
            'status is not answered',
        ],
    ],
    [
        'rule_key' => 'remote_side_hangup',
        'scenario' => 'Dropped Calls',
        'severity' => 2,
        'confidence' => 75,
        'diagnostic_direction' => 'Remote side appears to have ended the call',
        'recommended_next_step' => 'Review SIP trace if the customer disputes who disconnected. Compare with carrier logs.',
        'conditions' => [
            'sip_hangup_disposition' => 'recv_bye',
        ],
        'evidence_template' => [
            'sip_hangup_disposition=recv_bye',
        ],
    ],
    [
        'rule_key' => 'local_side_hangup',
        'scenario' => 'Dropped Calls',
        'severity' => 2,
        'confidence' => 75,
        'diagnostic_direction' => 'Local side appears to have ended the call',
        'recommended_next_step' => 'Check extension device behavior, PBX dialplan behavior, timeout settings, and SIP trace.',
        'conditions' => [
            'sip_hangup_disposition' => 'send_bye',
        ],
        'evidence_template' => [
            'sip_hangup_disposition=send_bye',
        ],
    ],
    [
        'rule_key' => 'poor_audio_mos',
        'scenario' => 'Poor Audio',
        'severity' => 4,
        'confidence' => 80,
        'diagnostic_direction' => 'Poor audio quality indicated by MOS',
        'recommended_next_step' => 'Check jitter, packet loss, codec, remote IP, customer network, and ISP path.',
        'conditions' => [
            'mos_less_than' => 3.5,
        ],
        'evidence_template' => [
            'rtp_audio_in_mos < 3.5',
        ],
    ],
    [
        'rule_key' => 'possible_fraud_short_outbound',
        'scenario' => 'Possible Fraud',
        'severity' => 5,
        'confidence' => 55,
        'diagnostic_direction' => 'Possible fraud pattern: short outbound call attempts',
        'recommended_next_step' => 'Review destination patterns, time of day, extension activity, failed attempts, and unusual international destinations.',
        'conditions' => [
            'direction' => 'outbound',
            'duration_less_than' => 30,
        ],
        'evidence_template' => [
            'direction=outbound',
            'duration < 30 seconds',
        ],
    ],
    [
        'rule_key' => 'extension_cluster_review',
        'scenario' => 'Extension Issues',
        'severity' => 3,
        'confidence' => 60,
        'diagnostic_direction' => 'Calls should be grouped by extension for endpoint pattern review',
        'recommended_next_step' => 'Sort by extension and compare failures, durations, codecs, and hangup disposition.',
        'conditions' => [
            'group_by' => 'extension',
            'minimum_count' => 3,
        ],
        'evidence_template' => [
            'multiple calls share the same extension',
        ],
    ],
];

$stmt = $pdo->prepare("
    INSERT INTO diagnostic_rules (
        rule_key,
        scenario,
        enabled,
        severity,
        confidence,
        diagnostic_direction,
        recommended_next_step,
        conditions,
        evidence_template
    ) VALUES (
        :rule_key,
        :scenario,
        true,
        :severity,
        :confidence,
        :diagnostic_direction,
        :recommended_next_step,
        :conditions,
        :evidence_template
    )
    ON CONFLICT (rule_key) DO UPDATE SET
        scenario = EXCLUDED.scenario,
        severity = EXCLUDED.severity,
        confidence = EXCLUDED.confidence,
        diagnostic_direction = EXCLUDED.diagnostic_direction,
        recommended_next_step = EXCLUDED.recommended_next_step,
        conditions = EXCLUDED.conditions,
        evidence_template = EXCLUDED.evidence_template,
        updated_at = now()
");

foreach ($rules as $rule) {
    $stmt->execute([
        ':rule_key' => $rule['rule_key'],
        ':scenario' => $rule['scenario'],
        ':severity' => $rule['severity'],
        ':confidence' => $rule['confidence'],
        ':diagnostic_direction' => $rule['diagnostic_direction'],
        ':recommended_next_step' => $rule['recommended_next_step'],
        ':conditions' => json_encode($rule['conditions']),
        ':evidence_template' => json_encode($rule['evidence_template']),
    ]);
}

echo "Seeded " . count($rules) . " diagnostic rules.\n";
