<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\Fees\Eso\FeesEsoRegistry;
use App\Services\Fees\Eso\FeesQualityGateEngine;
use App\Mcp\ToolRegistry;
use Carbon\Carbon;

echo "========================================================\n";
echo "FEES MODULE AGENT PIPELINE & QUALITY GATES AUDIT TEST\n";
echo "========================================================\n\n";

$gatesEngine = new FeesQualityGateEngine();
$toolRegistry = new class extends ToolRegistry {
    public function __construct() {}
    public function has(string $name): bool { return true; }
    public function tool(string $name): ?\App\Mcp\AbstractMcpTool { return null; }
};
$esoRegistry = new FeesEsoRegistry($toolRegistry);

$allPassed = true;

// -----------------------------------------------------------------------------
// TEST 1: AGENT 1 & GATE 1 (Fee Position & Arrears Reader)
// -----------------------------------------------------------------------------
echo "[1] Testing Agent 1 & Gate 1 (Position & Arrears Grounding)...\n";

$mockPosition = [
    'success' => true,
    'data' => [
        'headline' => 'Whole School Fee Position',
        'total_demand' => 5000000.0,
        'total_collected' => 3200000.0,
        'total_outstanding' => 1800000.0,
        'collection_rate' => '64.0%',
        'students_owing' => 120,
    ],
];

$mockArrears = [
    'success' => true,
    'data' => [
        'cohort_size' => 120,
        'students_checked' => 25,
        'truncated' => true,
        'failed' => [],
        'students_with_arrears' => [
            ['student_id' => 101, 'student_name' => 'Aarav Patel', 'enrollment_no' => 'EN101', 'standard_name' => '10-A', 'outstanding' => 25000.0, 'pending_items' => 2],
            ['student_id' => 102, 'student_name' => 'Diya Sharma', 'enrollment_no' => 'EN102', 'standard_name' => '9-B', 'outstanding' => 15000.0, 'pending_items' => 1],
            ['student_id' => 103, 'student_name' => 'Rohan Mehta', 'enrollment_no' => 'EN103', 'standard_name' => '8-A', 'outstanding' => 30000.0, 'pending_items' => 3],
        ],
    ],
];

$gate1 = $gatesEngine->evaluateGate1(1, 2026, $mockPosition, $mockArrears, false);

if ($gate1['status'] === 'passed' && $gate1['score'] === 100) {
    echo "  -> Gate 1 PASSED (Score: {$gate1['score']}/100, Checks passed: {$gate1['passed_checks']}/{$gate1['total_checks']})\n";
    foreach ($gate1['checks'] as $checkKey => $check) {
        echo "     [x] {$checkKey}: {$check['message']}\n";
    }
} else {
    echo "  -> Gate 1 FAILED! Status: {$gate1['status']}\n";
    $allPassed = false;
}

// Test Handoff 1 -> 2
$candidates = $mockArrears['data']['students_with_arrears'];
$handoff1to2 = $esoRegistry->validateHandoff('agent_1', 'agent_2', ['candidates' => $candidates]);
if ($handoff1to2['valid'] === true) {
    echo "  -> Handoff Contract Agent 1 -> Agent 2 VERIFIED: {$handoff1to2['message']} ({$handoff1to2['candidates_count']} candidates)\n\n";
} else {
    echo "  -> Handoff Contract 1 -> 2 FAILED: {$handoff1to2['error']}\n\n";
    $allPassed = false;
}

// -----------------------------------------------------------------------------
// TEST 2: AGENT 2 & GATE 2 (Collection Evidence & Reminder History Analyst)
// -----------------------------------------------------------------------------
echo "[2] Testing Agent 2 & Gate 2 (Collection Evidence & Cooldown Audit)...\n";

$mockCollection = [
    'success' => true,
    'data' => [
        'period' => ['label' => 'this month'],
        'summary' => [
            'total_collected' => 450000.0,
            'total_receipts' => 42,
        ],
    ],
];

$mockHistory = [
    'success' => true,
    'data' => [
        'reminders' => [
            // Student 102 was reminded 2 days ago (within 7-day cooldown)
            [
                'student_id' => 102,
                'channel' => 'email',
                'recorded_at' => Carbon::now()->subDays(2)->toIso8601String(),
                'batch' => 'BATCH_1001',
            ],
            // Student 103 was reminded 30 days ago (outside 7-day cooldown)
            [
                'student_id' => 103,
                'channel' => 'sms',
                'recorded_at' => Carbon::now()->subDays(30)->toIso8601String(),
                'batch' => 'BATCH_950',
            ],
        ],
    ],
];

// Reconcile candidates into eligible vs held back based on 7-day cooldown
$cooldownDays = 7;
$cutoff = Carbon::now()->subDays($cooldownDays);
$eligible = [];
$held = [];

foreach ($candidates as $cand) {
    $sid = $cand['student_id'];
    $prior = null;
    foreach ($mockHistory['data']['reminders'] as $r) {
        if ($r['student_id'] === $sid) {
            $prior = $r;
            break;
        }
    }

    if ($prior && Carbon::parse($prior['recorded_at'])->gte($cutoff)) {
        $held[] = $cand + [
            'reason' => "Already reminded on {$prior['recorded_at']} via {$prior['channel']} (within cooldown).",
            'last_reminder' => $prior,
        ];
    } else {
        $eligible[] = $cand;
    }
}

$gate2 = $gatesEngine->evaluateGate2($candidates, $eligible, $held, $mockCollection, $mockHistory);

if ($gate2['status'] === 'passed' && $gate2['score'] === 100) {
    echo "  -> Gate 2 PASSED (Score: {$gate2['score']}/100, Checks passed: {$gate2['passed_checks']}/{$gate2['total_checks']})\n";
    echo "     Eligible candidates: " . count($eligible) . " (Students: " . implode(', ', array_column($eligible, 'student_id')) . ")\n";
    echo "     Held-back candidates: " . count($held) . " (Student 102 suppressed: within 7-day cooldown)\n";
    foreach ($gate2['checks'] as $checkKey => $check) {
        echo "     [x] {$checkKey}: {$check['message']}\n";
    }
} else {
    echo "  -> Gate 2 FAILED! Status: {$gate2['status']}\n";
    $allPassed = false;
}

// Test Handoff 2 -> 3
$handoff2to3 = $esoRegistry->validateHandoff('agent_2', 'agent_3', ['eligible' => $eligible, 'held_back' => $held]);
if ($handoff2to3['valid'] === true) {
    echo "  -> Handoff Contract Agent 2 -> Agent 3 VERIFIED: {$handoff2to3['message']} ({$handoff2to3['eligible_count']} eligible, {$handoff2to3['held_back_count']} held back)\n\n";
} else {
    echo "  -> Handoff Contract 2 -> 3 FAILED: {$handoff2to3['error']}\n\n";
    $allPassed = false;
}

// -----------------------------------------------------------------------------
// TEST 3: AGENT 3 & GATE 3 (Reminder Drafter & Non-Destructive Safety)
// -----------------------------------------------------------------------------
echo "[3] Testing Agent 3 & Gate 3 (Reminder Drafter & Non-Destructive Preview)...\n";

$mockPreview = [
    'success' => true,
    'message' => 'Reminder preview generated successfully for 2 eligible students.',
    'data' => [
        'channel' => 'email',
        'deadline' => Carbon::now()->addDays(7)->toDateString(),
        'recipient_count' => count($eligible),
        'unreachable_count' => 0,
        'total_outstanding' => array_sum(array_column($eligible, 'outstanding')),
        'recipients' => array_map(function ($s) {
            return [
                'student_id' => $s['student_id'],
                'name' => $s['student_name'],
                'class' => $s['standard_name'],
                'outstanding' => $s['outstanding'],
                'contact_masked' => 'p*****@example.com',
            ];
        }, $eligible),
        'unreachable' => [],
        'fingerprint' => hash('sha256', 'fees_preview_subinst_1_' . count($eligible) . '_' . Carbon::now()->toDateString()),
        'sent' => 0,
    ],
];

// Evaluate Gate 3 asserting zero sent messages
$gate3 = $gatesEngine->evaluateGate3($eligible, $mockPreview, true, []);

if ($gate3['status'] === 'passed' && $gate3['score'] === 100) {
    echo "  -> Gate 3 PASSED (Score: {$gate3['score']}/100, Checks passed: {$gate3['passed_checks']}/{$gate3['total_checks']})\n";
    echo "     Fingerprint generated: {$mockPreview['data']['fingerprint']}\n";
    echo "     Messages sent: {$mockPreview['data']['sent']} (STRICT ZERO DISPATCH PRESERVED)\n";
    foreach ($gate3['checks'] as $checkKey => $check) {
        echo "     [x] {$checkKey}: {$check['message']}\n";
    }
} else {
    echo "  -> Gate 3 FAILED! Status: {$gate3['status']}\n";
    $allPassed = false;
}

// -----------------------------------------------------------------------------
// TEST 4: COMPOSITE QUALITY SCORECARD & SUMMARY
// -----------------------------------------------------------------------------
echo "\n[4] Testing Composite Quality Scorecard Calculation...\n";

$composite = $gatesEngine->computeCompositeScore([
    'gate_1' => $gate1,
    'gate_2' => $gate2,
    'gate_3' => $gate3,
]);

echo "  -> Composite Quality Score: {$composite['composite_score']}/100\n";
echo "  -> Overall Status: {$composite['overall_status']}\n";
echo "  -> Quality Tier: {$composite['quality_tier']}\n";
echo "  -> Gates Evaluated: {$composite['gates_evaluated']}/3\n\n";

// -----------------------------------------------------------------------------
// TEST 5: SAFETY GATE REFUSAL TEST (Assertion that Gate 3 rejects premature sends)
// -----------------------------------------------------------------------------
echo "[5] Testing Safety Enforcement (Tamper & Send Prevention)...\n";

$tamperedSentPreview = $mockPreview;
$tamperedGate3 = $gatesEngine->evaluateGate3($eligible, $tamperedSentPreview, true, ['sent_email_id_999']);

if ($tamperedGate3['checks']['nothing_sent']['status'] === 'failed') {
    echo "  -> Gate 3 SAFETY REJECTION PASSED: Gate 3 successfully trapped premature send indicator!\n";
    echo "     [x] Trapped error: {$tamperedGate3['checks']['nothing_sent']['message']}\n";
} else {
    echo "  -> CRITICAL FLAW: Gate 3 allowed premature message send!\n";
    $allPassed = false;
}

echo "\n========================================================\n";
if ($allPassed) {
    echo "ALL 5 PIPELINE & GATE INTEGRATION TESTS PASSED (100% SUCCESS)\n";
} else {
    echo "ONE OR MORE TESTS FAILED!\n";
}
echo "========================================================\n";
