<?php

namespace Tests\Feature\Brain;

use App\Brain\Intelligence\ModuleSignalBridge;
use App\Brain\Intelligence\RuleCatalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * One test for the contract every module's Intelligence endpoint speaks.
 *
 * ── WHY THIS IS CROSS-MODULE ────────────────────────────────────────────────
 *
 * Each module already has its own test, and each one passed while eleven of the
 * twelve modules were emitting `ruleStatus` as `{rule, label, status, detail}`.
 * The screen reads `key`, `checked` and `raised`, so those panels rendered every
 * check as "not run" against a year whose checks had all just run — a per-module
 * test asserting "the endpoint returns 200 and has a ruleStatus key" cannot see
 * that, because the shape it should be checking against lives in the frontend.
 *
 * This asserts the payload against `lms_k12/components/intelligence/module/
 * payload.ts` FIELD BY FIELD, for every module, so drift fails here instead of
 * rendering a lie.
 *
 * ── IT RUNS AGAINST REAL ROWS ───────────────────────────────────────────────
 *
 * Each module picks the institute-year that actually holds the most of its own
 * data, and skips when the database has none. A fixture would assert the shape
 * of the fixture; this asserts the shape of what a school will actually be
 * served.
 */
class ModuleIntelligenceContractTest extends TestCase
{
    /**
     * module key => [controller, source table, year column or null when the
     * table is not year-scoped].
     *
     * @return array<string,array{0:class-string,1:string,2:?string}>
     */
    public static function modules(): array
    {
        return [
            'result' => [\App\Http\Controllers\Brain\BrainResultIntelligenceController::class, 'result_personalize_marks', 'syear'],
            'attendance' => [\App\Http\Controllers\Brain\BrainAttendanceIntelligenceController::class, 'result_student_attendance_master', 'syear'],
            'student' => [\App\Http\Controllers\Brain\BrainStudentIntelligenceController::class, 'tblstudent_enrollment', 'syear'],
            'transport' => [\App\Http\Controllers\Brain\BrainTransportIntelligenceController::class, 'transport_map_student', 'syear'],
            'library' => [\App\Http\Controllers\Brain\BrainLibraryIntelligenceController::class, 'library_book_circulations', 'syear'],
            'academic' => [\App\Http\Controllers\Brain\BrainAcademicIntelligenceController::class, 'timetable', 'syear'],
            'hr' => [\App\Http\Controllers\Brain\BrainHrIntelligenceController::class, 'tbluser', null],
            'communication' => [\App\Http\Controllers\Brain\BrainCommunicationIntelligenceController::class, 'parent_communication', 'syear'],
            'homework' => [\App\Http\Controllers\Brain\BrainHomeworkIntelligenceController::class, 'homework', 'syear'],
            'admissions' => [\App\Http\Controllers\Brain\BrainAdmissionsIntelligenceController::class, 'admission_registration_v1', null],
            'inventory' => [\App\Http\Controllers\Brain\BrainInventoryIntelligenceController::class, 'item_scan_details', null],
            'hostel' => [\App\Http\Controllers\Brain\BrainHostelIntelligenceController::class, 'hostel_room_allocation', null],
            // The gate register carries a visit DATE and no syear, so it is
            // year-scoped through the institute's own term dates — the same
            // mechanism hr and admissions use, which is why its year column is
            // null here too.
            'visitor' => [\App\Http\Controllers\Brain\BrainVisitorIntelligenceController::class, 'visitor_master', null],
            'correspondence' => [\App\Http\Controllers\Brain\BrainCorrespondenceIntelligenceController::class, 'inward', 'syear'],
        ];
    }

    /** @return iterable<string,array{0:string}> */
    public static function moduleKeys(): iterable
    {
        foreach (array_keys(self::modules()) as $key) {
            yield $key => [$key];
        }
    }

    /**
     * The institute-year holding the most rows of this module's own data.
     *
     * @return array{0:string,1:?string}|null
     */
    private function richestScope(string $table, ?string $yearColumn): ?array
    {
        try {
            $query = DB::table($table)
                ->select('sub_institute_id', DB::raw('COUNT(*) as c'))
                ->groupBy('sub_institute_id')
                ->orderByDesc('c');

            if ($yearColumn !== null) {
                $query = DB::table($table)
                    ->select('sub_institute_id', $yearColumn.' as y', DB::raw('COUNT(*) as c'))
                    ->whereNotNull($yearColumn)
                    ->where($yearColumn, '>', 0)
                    ->groupBy('sub_institute_id', $yearColumn)
                    ->orderByDesc('c');
            }

            $row = $query->first();
        } catch (\Throwable) {
            return null;
        }

        if ($row === null) {
            return null;
        }

        return [(string) $row->sub_institute_id, $yearColumn === null ? null : (string) $row->y];
    }

    /** @return array<string,mixed> */
    private function payload(string $controller, string $module, string $tenant, ?string $syear): array
    {
        $request = Request::create(
            "/api/brain/{$tenant}/{$module}/intelligence",
            'GET',
            $syear === null ? [] : ['syear' => $syear],
        );
        $request->attributes->set('tenantId', $tenant);
        $request->attributes->set('auth.tenantId', $tenant);
        $request->attributes->set('auth.userId', '0');
        $request->attributes->set('brain.payload', ['is_student' => false]);

        return json_decode(app($controller)->index($request)->getContent(), true) ?? [];
    }

    /**
     * @dataProvider moduleKeys
     */
    public function test_module_emits_the_canonical_payload(string $module): void
    {
        [$controller, $table, $yearColumn] = self::modules()[$module];

        $scope = $this->richestScope($table, $yearColumn);
        if ($scope === null) {
            $this->markTestSkipped("No {$table} rows in this database for {$module}.");
        }

        $payload = $this->payload($controller, $module, $scope[0], $scope[1]);

        /* ------------------------------------------------------- envelope */
        foreach ([
            'tenantId', 'organization', 'source', 'academicYear', 'coverage',
            'freshness', 'execution', 'summary', 'position', 'breakdowns',
            'findings', 'priorities', 'recommendations', 'decisionTrail',
            'learning', 'dataQuality', 'ruleStatus',
        ] as $key) {
            $this->assertArrayHasKey($key, $payload, "{$module}: payload is missing '{$key}'");
        }

        /* ------------------------------------------------ L0: the coverage */
        foreach (['available', 'reason', 'syear', 'sources', 'counts'] as $key) {
            $this->assertArrayHasKey($key, $payload['coverage'], "{$module}: coverage is missing '{$key}'");
        }
        $this->assertIsBool($payload['coverage']['available'], "{$module}: coverage.available must be a boolean");
        $this->assertIsArray($payload['coverage']['sources'], "{$module}: coverage.sources must be a map");
        $this->assertIsArray($payload['coverage']['counts'], "{$module}: coverage.counts must be a map");

        // An unavailable block must say WHY in the module's own words. A generic
        // "no data" erases the difference between "nobody is enrolled" and
        // "marks were never entered", which have different answers.
        if ($payload['coverage']['available'] === false) {
            $this->assertNotEmpty(
                $payload['coverage']['reason'],
                "{$module}: coverage is unavailable but gives no reason",
            );

            return;
        }

        /* ------------------------------------------------------- L1: position */
        $this->assertIsArray($payload['position'], "{$module}: position must be a metric group");
        $this->assertArrayHasKey('metrics', $payload['position'], "{$module}: position is missing metrics[]");
        foreach ($payload['position']['metrics'] as $i => $metric) {
            foreach (['key', 'label', 'value', 'format'] as $key) {
                $this->assertArrayHasKey($key, $metric, "{$module}: position.metrics[{$i}] is missing '{$key}'");
            }
            $this->assertContains(
                $metric['format'],
                ['currency', 'currencyExact', 'count', 'percent', 'decimal', 'duration', 'text'],
                "{$module}: position.metrics[{$i}] declares an unknown format",
            );
            $this->assertTrue(
                $metric['value'] === null || is_numeric($metric['value']),
                "{$module}: position.metrics[{$i}].value must be a number or null, never a string",
            );
        }

        /* --------------------------------------------------- L2: breakdowns */
        foreach ($payload['breakdowns'] as $i => $breakdown) {
            foreach (['key', 'label', 'available', 'reason', 'columns', 'rows'] as $key) {
                $this->assertArrayHasKey($key, $breakdown, "{$module}: breakdowns[{$i}] is missing '{$key}'");
            }
            $columnKeys = array_column($breakdown['columns'], 'key');
            if ($breakdown['primaryColumn'] ?? null) {
                $this->assertContains(
                    $breakdown['primaryColumn'],
                    $columnKeys,
                    "{$module}: breakdowns[{$i}] names a primaryColumn that is not one of its columns",
                );
            }
            foreach ($breakdown['rows'] as $r => $row) {
                $this->assertArrayHasKey('values', $row, "{$module}: breakdowns[{$i}].rows[{$r}] is missing values");
                foreach ($row['values'] as $column => $value) {
                    $this->assertTrue(
                        $value === null || is_numeric($value),
                        "{$module}: breakdowns[{$i}].rows[{$r}].values['{$column}'] must be a number or null",
                    );
                }
            }
        }

        /* ----------------------------------------------------- L3: findings */
        foreach ($payload['findings'] as $i => $finding) {
            foreach ([
                'id', 'severity', 'severityLabel', 'title', 'whatHappened', 'whyItMatters',
                'evidence', 'likelyCause', 'causeConfirmed', 'recommendation', 'owner',
                'priority', 'confidence', 'affected', 'raisedAt', 'impact', 'syear', 'status',
            ] as $key) {
                $this->assertArrayHasKey($key, $finding, "{$module}: findings[{$i}] is missing '{$key}'");
            }

            // payload.ts rule 3. An assertion with no figures behind it is a
            // chart caption, not a finding.
            $this->assertNotEmpty(
                $finding['evidence'],
                "{$module}: findings[{$i}] ('{$finding['title']}') was raised with no evidence",
            );
            foreach ($finding['evidence'] as $e => $point) {
                $this->assertArrayHasKey('label', $point, "{$module}: findings[{$i}].evidence[{$e}] has no label");
                $this->assertArrayHasKey('value', $point, "{$module}: findings[{$i}].evidence[{$e}] has no value");
            }

            // Confidence always travels with its word — "High", never a bare 0.85.
            $this->assertIsArray($finding['confidence'], "{$module}: findings[{$i}].confidence must be {band,value}");
            $this->assertArrayHasKey('band', $finding['confidence'], "{$module}: findings[{$i}].confidence has no band");
            $this->assertArrayHasKey('value', $finding['confidence'], "{$module}: findings[{$i}].confidence has no value");

            // Impact is a figure and its noun, never prose.
            if ($finding['impact'] !== null) {
                $this->assertIsArray($finding['impact'], "{$module}: findings[{$i}].impact must be {value,display,label}");
                $this->assertArrayHasKey('display', $finding['impact'], "{$module}: findings[{$i}].impact has no display");
                $this->assertArrayHasKey('label', $finding['impact'], "{$module}: findings[{$i}].impact has no label");
            }

            // An unconfirmed cause must be flagged as unconfirmed, so the screen
            // can label it a hypothesis rather than a conclusion.
            $this->assertIsBool($finding['causeConfirmed'], "{$module}: findings[{$i}].causeConfirmed must be a boolean");
        }

        /* --------------------------------------------------- the rule ledger */
        foreach ($payload['ruleStatus'] as $i => $rule) {
            foreach (['key', 'label', 'checked', 'raised'] as $key) {
                $this->assertArrayHasKey($key, $rule, "{$module}: ruleStatus[{$i}] is missing '{$key}'");
            }
            $this->assertIsBool($rule['checked'], "{$module}: ruleStatus[{$i}].checked must be a boolean");
            $this->assertIsBool($rule['raised'], "{$module}: ruleStatus[{$i}].raised must be a boolean");
        }

        // Silence has to be readable: a rule that fired must be reflected in the
        // ledger, or "no findings" and "no checks ran" look identical.
        $raisedRules = array_values(array_filter($payload['ruleStatus'], fn ($r) => $r['raised'] === true));
        if ($payload['findings'] !== []) {
            $this->assertNotEmpty(
                $raisedRules,
                "{$module}: findings were raised but no rule in ruleStatus is marked as having raised one",
            );
        }

        /* ------------------------------------------------------ data quality */
        foreach (['available', 'reason', 'checks'] as $key) {
            $this->assertArrayHasKey($key, $payload['dataQuality'], "{$module}: dataQuality is missing '{$key}'");
        }
        $this->assertIsBool($payload['dataQuality']['available'], "{$module}: dataQuality.available must be a boolean");
        foreach ($payload['dataQuality']['checks'] as $i => $check) {
            foreach (['key', 'label', 'value', 'format', 'sharePercent', 'state', 'note'] as $key) {
                $this->assertArrayHasKey($key, $check, "{$module}: dataQuality.checks[{$i}] is missing '{$key}'");
            }
            $this->assertContains(
                $check['state'],
                ['ok', 'attention'],
                "{$module}: dataQuality.checks[{$i}].state must be 'ok' or 'attention'",
            );
        }

        /* ---------------------------------------------------------- learning */
        foreach (['available', 'reason', 'entries'] as $key) {
            $this->assertArrayHasKey($key, $payload['learning'], "{$module}: learning is missing '{$key}'");
        }
        if ($payload['learning']['available'] === false) {
            $this->assertNotEmpty(
                $payload['learning']['reason'],
                "{$module}: learning is unavailable but does not say why",
            );
        }

        /* --------------------------------------------------------- summary */
        $this->assertArrayHasKey('sentences', $payload['summary'], "{$module}: summary is missing sentences[]");
        $this->assertIsArray($payload['summary']['sentences'], "{$module}: summary.sentences must be a list");
    }

    /**
     * A tenant only ever sees its own rows.
     *
     * @dataProvider moduleKeys
     */
    public function test_module_payload_is_pinned_to_the_requested_tenant(string $module): void
    {
        [$controller, $table, $yearColumn] = self::modules()[$module];

        $scope = $this->richestScope($table, $yearColumn);
        if ($scope === null) {
            $this->markTestSkipped("No {$table} rows in this database for {$module}.");
        }

        $payload = $this->payload($controller, $module, $scope[0], $scope[1]);

        $this->assertSame(
            $scope[0],
            (string) $payload['tenantId'],
            "{$module}: payload reports a tenant other than the one it was scoped to",
        );

        if ($scope[1] !== null && ($payload['coverage']['available'] ?? false)) {
            $this->assertSame(
                $scope[1],
                (string) $payload['academicYear']['syear'],
                "{$module}: payload reports a year other than the one requested",
            );
        }
    }

    /**
     * The module namespace and the institute-wide one must stay disjoint.
     *
     * ── THE FAILURE THIS GUARDS ─────────────────────────────────────────────
     *
     * `LmsSignalRules` already owns `student_missing_identity`,
     * `student_absence_rate`, `attendance_decline`, `result_low_performance` and
     * `homework_non_submission`. The bridge originally namespaced a module rule
     * as `<module>_<rule>`, so `ModuleLoop` reading `rule_key LIKE "student_%"`
     * picked up six institute-wide rules the Student module never raised — and
     * the Student screen showed a recommendation it could not explain.
     *
     * `mod_` makes the two key spaces disjoint by construction. This asserts it
     * stays that way.
     */
    public function test_module_rule_keys_cannot_collide_with_institute_wide_rules(): void
    {
        $moduleKeys = [];
        $wideKeys = [];

        foreach (array_keys(RuleCatalogue::CAUSES) as $key) {
            if (str_starts_with($key, ModuleSignalBridge::NAMESPACE)) {
                $moduleKeys[] = $key;
            } else {
                $wideKeys[] = $key;
            }
        }

        $this->assertNotEmpty($moduleKeys, 'No module rule carries the module namespace.');

        // The read-back prefix for each module must match nothing outside it.
        foreach (array_keys(ModuleSignalBridge::MODULES) as $module) {
            $prefix = ModuleSignalBridge::ruleKeyPrefix($module);

            foreach ($wideKeys as $wide) {
                $this->assertFalse(
                    str_starts_with($wide, $prefix),
                    "Institute-wide rule '{$wide}' would be read back as a {$module} module rule.",
                );
            }
        }
    }

    /**
     * A module reads back only the signals it raised.
     *
     * @dataProvider moduleKeys
     */
    public function test_module_reads_back_only_its_own_recommendations(string $module): void
    {
        [$controller, $table, $yearColumn] = self::modules()[$module];

        $scope = $this->richestScope($table, $yearColumn);
        if ($scope === null) {
            $this->markTestSkipped("No {$table} rows in this database for {$module}.");
        }

        $payload = $this->payload($controller, $module, $scope[0], $scope[1]);
        $recommendations = $payload['recommendations'] ?? [];

        if ($recommendations === []) {
            $this->markTestSkipped("The loop has not been run for {$module} at this institute-year.");
        }

        $prefix = ModuleSignalBridge::ruleKeyPrefix($module);

        foreach ($recommendations as $i => $recommendation) {
            $signalId = $recommendation['finding']['signalId'] ?? null;
            $this->assertNotEmpty($signalId, "{$module}: recommendations[{$i}] names no signal");

            $ruleKey = (string) DB::table('hpbrain_signals')->where('id', $signalId)->value('rule_key');

            $this->assertStringStartsWith(
                $prefix,
                $ruleKey,
                "{$module} is showing a recommendation raised by '{$ruleKey}', which is not one of its own rules.",
            );
        }
    }

    /**
     * A recommendation whose rule has no approved cause must NOT carry one.
     *
     * The human-approval gate is the difference between the Brain detecting
     * something and the Brain inventing an explanation for it.
     *
     * @dataProvider moduleKeys
     */
    public function test_a_recommendation_without_an_approved_cause_explains_nothing(string $module): void
    {
        [$controller, $table, $yearColumn] = self::modules()[$module];

        $scope = $this->richestScope($table, $yearColumn);
        if ($scope === null) {
            $this->markTestSkipped("No {$table} rows in this database for {$module}.");
        }

        $payload = $this->payload($controller, $module, $scope[0], $scope[1]);
        $recommendations = $payload['recommendations'] ?? [];

        if ($recommendations === []) {
            $this->markTestSkipped("The loop has not been run for {$module} at this institute-year.");
        }

        foreach ($recommendations as $i => $recommendation) {
            $signalId = $recommendation['finding']['signalId'] ?? null;
            $ruleKey = (string) DB::table('hpbrain_signals')->where('id', $signalId)->value('rule_key');
            $approved = RuleCatalogue::for($ruleKey);

            if ($approved === null) {
                $this->assertNull(
                    $recommendation['why'],
                    "{$module}: recommendations[{$i}] explains a finding whose cause was never approved.",
                );

                continue;
            }

            $this->assertSame(
                $approved['hypothesis'],
                $recommendation['why'],
                "{$module}: recommendations[{$i}] states a cause other than the approved one.",
            );
        }
    }

    /**
     * The loop never reports a stage that did not happen.
     *
     * @dataProvider moduleKeys
     */
    public function test_the_decision_trail_never_invents_a_stage(string $module): void
    {
        [$controller, $table, $yearColumn] = self::modules()[$module];

        $scope = $this->richestScope($table, $yearColumn);
        if ($scope === null) {
            $this->markTestSkipped("No {$table} rows in this database for {$module}.");
        }

        $payload = $this->payload($controller, $module, $scope[0], $scope[1]);

        foreach ($payload['decisionTrail'] ?? [] as $i => $entry) {
            $this->assertContains(
                $entry['outcomeState'],
                ['resolved', 'partially_resolved', 'not_reached', 'undetermined', 'awaiting_outcome', 'no_action_queued'],
                "{$module}: decisionTrail[{$i}] reports an outcome state the screen cannot render",
            );

            // An outcome cannot exist without the execution that produced it.
            if ($entry['outcome'] !== null) {
                $this->assertNotSame(
                    'no_action_queued',
                    $entry['outcomeState'],
                    "{$module}: decisionTrail[{$i}] has an outcome but claims nothing was queued",
                );
            }

            // A measured before/after is null unless somebody recorded one. A
            // zero here would claim the action moved nothing.
            if ($entry['outcome'] !== null && $entry['outcome']['measured'] !== null) {
                $this->assertArrayHasKey('before', $entry['outcome']['measured']);
                $this->assertArrayHasKey('after', $entry['outcome']['measured']);
                $this->assertArrayHasKey('basis', $entry['outcome']['measured']);
            }
        }

        // Asserted unconditionally, so an institute-year with an empty trail
        // still checks something. A test that can pass by doing nothing tells
        // you nothing when it passes.
        $this->assertIsArray($payload['decisionTrail'], "{$module}: decisionTrail must be a list");

        $learning = $payload['learning'];
        $this->assertIsBool($learning['available'], "{$module}: learning.available must be a boolean");

        if ($learning['available'] === false) {
            $this->assertNotEmpty($learning['reason'], "{$module}: learning is unavailable but does not say why");
            $this->assertSame([], $learning['entries']);

            return;
        }

        // Every entry is a decision somebody actually saw through. Nothing here
        // is inferred, so each one must name what was done and what resulted.
        $this->assertNotEmpty($learning['entries'], "{$module}: learning is available but holds nothing");

        foreach ($learning['entries'] as $i => $entry) {
            foreach (['finding', 'action', 'result', 'recordedAt', 'appliesToThisYear'] as $field) {
                $this->assertArrayHasKey($field, $entry, "{$module}: learning.entries[{$i}] is missing '{$field}'");
            }
            $this->assertNotEmpty($entry['action'], "{$module}: learning.entries[{$i}] names no action");
        }
    }

    /* --------------------------------------------- the L5 honesty invariant */

    /**
     * NOTHING IS MARKED DONE THAT WAS NOT DONE.
     *
     * The single worst thing this architecture could do is tell a head that a
     * recommendation was carried out when nobody carried it out. An execution
     * reaching `completed` is a claim about the physical world, so it must carry
     * the three things that make that claim checkable: who did it, when they
     * finished, and what came of it.
     *
     * This runs over the WHOLE ledger rather than one module's slice, because
     * the claim is equally false wherever it is made.
     */
    public function test_no_execution_claims_to_be_completed_without_having_happened(): void
    {
        if (! \App\Brain\Support\SchemaCache::hasTable('hpbrain_eso_executions')) {
            $this->markTestSkipped('The execution ledger is not provisioned in this database.');
        }

        $completed = DB::table('hpbrain_eso_executions as e')
            ->leftJoin('hpbrain_outcomes as o', 'o.decision_id', '=', 'e.decision_id')
            ->leftJoin('hpbrain_decisions as d', 'd.id', '=', 'e.decision_id')
            ->where('e.status', 'completed')
            ->get([
                'e.id', 'e.executed_by', 'e.executor_type', 'e.completed_date',
                'o.id as outcome_id', 'o.result', 'd.id as decision_id', 'd.status as decision_status',
            ]);

        if ($completed->isEmpty()) {
            // Not a skip: an empty set is the invariant holding vacuously, and
            // saying so is more useful than reporting nothing ran.
            $this->assertTrue(true, 'No execution claims completion, so none can claim it falsely.');

            return;
        }

        foreach ($completed as $execution) {
            $this->assertNotEmpty(
                $execution->completed_date,
                "Execution {$execution->id} is marked completed and carries no completion date.",
            );
            $this->assertNotEmpty(
                $execution->executed_by,
                "Execution {$execution->id} is marked completed and names nobody who completed it.",
            );
            $this->assertNotNull(
                $execution->decision_id,
                "Execution {$execution->id} is marked completed and hangs off no decision, so nobody approved it.",
            );
            $this->assertNotNull(
                $execution->outcome_id,
                "Execution {$execution->id} is marked completed with no outcome reported. Completion is a claim "
                    .'about the world; without an outcome nobody has said what happened.',
            );
            $this->assertContains(
                (string) $execution->result,
                ['success', 'partial', 'failure', 'no_change'],
                "Execution {$execution->id} has an outcome whose result is not one the loop recognises.",
            );
        }
    }

    /**
     * A DECISION IS A PERSON, NOT A DEFAULT.
     *
     * Approving a recommendation records a decision against somebody's name.
     * A decision with no decider would let the trail show an approval that no
     * human gave.
     */
    public function test_every_decision_names_who_made_it(): void
    {
        if (! \App\Brain\Support\SchemaCache::hasTable('hpbrain_decisions')) {
            $this->markTestSkipped('The decision ledger is not provisioned in this database.');
        }

        $decisions = DB::table('hpbrain_decisions')
            ->get(['id', 'decided_by', 'status', 'recommendation_id', 'rationale']);

        if ($decisions->isEmpty()) {
            $this->assertTrue(true, 'No decision has been recorded, so none can name nobody.');

            return;
        }

        foreach ($decisions as $decision) {
            $this->assertNotEmpty(
                $decision->decided_by,
                "Decision {$decision->id} names nobody who made it.",
            );
            $this->assertNotEmpty(
                $decision->recommendation_id,
                "Decision {$decision->id} answers no recommendation.",
            );
        }
    }
}
