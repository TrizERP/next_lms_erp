<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fills the Enterprise Brain screens that the real pipeline leaves empty
 * (AI Assistant, Memory, Agent Monitor, Decision Intelligence, Graph entities,
 * Process library, ESO efficacy, Executive metrics, Ingestion sources,
 * Settings) so a school demo has something on every menu.
 *
 * Scope: Brain-owned `hpbrain_*` tables only, one tenant, never LMS tables.
 * Idempotent: every row has a fixed `demo-*` id and is created only if absent.
 * Remove with:  DELETE FROM <table> WHERE id LIKE 'demo-%'  (see down()).
 *
 * Run (bare `artisan db:seed` is fine here, unlike `migrate`):
 *   php artisan db:seed --class=BrainDemoDataSeeder
 *   BRAIN_DEMO_TENANT=1 controls the tenant (default 1).
 */
class BrainDemoDataSeeder extends Seeder
{
    private string $tenant;
    private string $actor = 'demo-seed';

    /** Tables touched, for down(). */
    private const TABLES = [
        'hpbrain_risks', 'hpbrain_reasoning_patterns', 'hpbrain_learnings',
        'hpbrain_conversation_sessions', 'hpbrain_conversation_messages',
        'hpbrain_prompt_templates', 'hpbrain_ai_executions', 'hpbrain_executors',
        'hpbrain_metrics', 'hpbrain_metric_snapshots', 'hpbrain_data_sources',
        'hpbrain_event_store', 'hpbrain_context_entities', 'hpbrain_process_definitions',
        'hpbrain_eso_efficacy_records', 'hpbrain_notifications', 'hpbrain_policies',
        'hpbrain_reasoning_steps', 'hpbrain_recommendations', 'hpbrain_decisions', 'hpbrain_eso_executions', 'hpbrain_outcomes', 'hpbrain_capability_assignments',
    ];

    public function run(): void
    {
        $this->tenant = (string) env('BRAIN_DEMO_TENANT', '1');
        $org = DB::table('hpbrain_organizations')->where('tenant_id', $this->tenant)->value('id');
        if (! $org) {
            $this->command?->warn("Tenant {$this->tenant} has no hpbrain_organizations row; run Brain ingestion first.");
            return;
        }

        $this->risks();
        $this->patterns($org);
        $this->learnings();
        $this->assistant();
        $this->executors();
        $this->policies();
        $this->moduleTrails();
        $this->metrics();
        $this->dataSources();
        $this->events();
        $this->contextEntities($org);
        $this->processes($org);
        $this->efficacy();
        $this->notifications();
        $this->assignments();
        $this->settings();

        $this->command?->info("Brain demo data seeded for tenant {$this->tenant}.");
    }

    public function down(): void
    {
        foreach (self::TABLES as $t) {
            if (Schema::hasTable($t)) {
                DB::table($t)->where('id', 'like', 'demo-%')->delete();
            }
        }
        DB::table('hpbrain_settings')->whereIn('key', ['general.school_name','general.timezone','general.academic_year_start','general.briefing_day','general.briefing_time','general.attendance_cutoff','general.fee_due_day','general.alert_channels'])->delete();
    }

    /* ------------------------------------------------------------ helpers */

    private function put(string $table, string $id, array $row): void
    {
        if (! Schema::hasTable($table) || DB::table($table)->where('id', $id)->exists()) {
            return;
        }
        $row = ['id' => $id] + $row;
        // The seeder is tenant-scoped; event_store/metrics etc. carry tenant_id too.
        $row['tenant_id'] = $this->tenant;
        DB::table($table)->insert($row);
    }

    private function ago(int $days, int $hour = 10): string
    {
        return now()->subDays($days)->setTime($hour, 0)->toDateTimeString();
    }

    private function json(mixed $v): string
    {
        return json_encode($v, JSON_UNESCAPED_UNICODE);
    }

    /* -------------------------------------------------- decision intelligence */

    private function risks(): void
    {
        $decisions = DB::table('hpbrain_decisions')->where('tenant_id', $this->tenant)->pluck('id')->all();
        $recs = DB::table('hpbrain_recommendations')->where('tenant_id', $this->tenant)->limit(8)->pluck('id')->all();
        $rows = [
            ['Fees', 0.70, 'high', 'Collections are recorded outside the system; outstanding balances cannot be calculated.', 'Move counter receipts into the fee module and reconcile weekly.', 'open'],
            ['Attendance', 0.55, 'high', 'Daily attendance is not captured for every class, hiding chronic absentees.', 'Make attendance marking mandatory before first period; alert class teachers by 10 am.', 'open'],
            ['Academic', 0.45, 'medium', 'Several subjects have no published timetable slots for the current term.', 'Timetable coordinator to complete slots and publish by Friday.', 'mitigating'],
            ['Transport', 0.40, 'medium', 'Some routes run over the registered vehicle capacity.', 'Re-balance routes against the vehicle register before the next timetable.', 'mitigating'],
            ['Compliance', 0.35, 'high', 'Staff records are missing mandatory details (mobile, department).', 'HR to complete the 233 incomplete staff profiles this month.', 'open'],
            ['Data quality', 0.60, 'medium', 'Duplicate and stale department rows distort headcount reports.', 'Merge duplicate departments and archive unused ones.', 'mitigating'],
            ['Safety', 0.20, 'low', 'Visitor log is not used at every gate.', 'Roll out visitor pass at the main gate first.', 'accepted'],
            ['Library', 0.25, 'low', 'Catalogue exists but circulation is dormant.', 'Run a reading-week campaign to restart issue and return.', 'closed'],
        ];
        $weight = ['low' => 1, 'medium' => 2, 'high' => 3];
        foreach ($rows as $i => [$cat, $p, $impact, $desc, $mit, $status]) {
            $this->put('hpbrain_risks', 'demo-risk-'.($i + 1), [
                'decision_id' => $decisions[$i % max(count($decisions), 1)] ?? null,
                'recommendation_id' => $recs[$i] ?? null,
                'category' => $cat.' — '.$desc,
                'probability' => $p, 'impact' => $impact,
                'score' => round($p * $weight[$impact] * 3.33, 4),
                'mitigation' => $mit, 'status' => $status,
                'created_by' => $this->actor, 'created_date' => $this->ago(20 - $i), 'updated_date' => $this->ago(3),
            ]);
        }
    }

    private function patterns(string $org): void
    {
        $rows = [
            ['RP-RECURRING-GAP', 'Recurring gap', 'detection', 'The same gap re-appears after a fix within two terms; the fix addressed the symptom, not the cause.'],
            ['RP-CASCADE', 'Cascading failure', 'causal', 'A gap in one module (e.g. attendance) predictably produces gaps downstream (results, fees follow-up).'],
            ['RP-DATA-VOID', 'Data void', 'detection', 'A module is configured but records are absent, so every metric derived from it is unreliable.'],
            ['RP-CAPACITY', 'Capacity breach', 'threshold', 'Demand exceeds a configured limit (vehicle seats, section strength, library copies).'],
            ['RP-SEASONAL', 'Seasonal spike', 'temporal', 'Load clusters around admissions, exams and fee due-dates; plan staffing ahead of the window.'],
            ['RP-OWNERSHIP', 'Unowned process', 'structural', 'A recurring task has no responsible role, so it is repeatedly late.'],
        ];
        foreach ($rows as $i => [$code, $name, $type, $desc]) {
            $this->put('hpbrain_reasoning_patterns', 'demo-pattern-'.($i + 1), [
                'org_id' => $org, 'pattern_code' => $code, 'name' => $name, 'pattern_type' => $type,
                'description' => $desc,
                'template' => $this->json(['steps' => ['observe', 'compare to baseline', 'find root cause', 'recommend'], 'minEvidence' => 2]),
                'version' => 1, 'status' => 'active', 'created_by' => $this->actor,
                'created_date' => $this->ago(40), 'updated_date' => $this->ago(5),
            ]);
        }
    }

    private function learnings(): void
    {
        $outcome = DB::table('hpbrain_outcomes')->where('tenant_id', $this->tenant)->value('id');
        $models = DB::table('hpbrain_mental_models')->where('tenant_id', $this->tenant)->pluck('id', 'domain')->all();
        $rows = [
            ['Naming a head for each department lifts follow-through', 'Departments that were assigned a head closed their open actions faster than unowned ones.', 'structural_dormancy', 0.82],
            ['Fix the entry point before the report', 'Where fee receipts were captured at the counter, balance reports became reliable within a week.', 'data_quality', 0.78],
            ['Class-teacher alerts beat weekly summaries', 'Same-day absentee alerts reduced repeat absences more than end-of-week digests.', 'attendance', 0.74],
            ['Re-measure before re-routing', 'Checking vehicle capacity against the register avoided unnecessary route changes.', 'transport', 0.70],
            ['Publish the timetable before the term starts', 'Late timetables caused cover gaps in the first fortnight every term.', 'academic', 0.66],
            ['Complete staff profiles during onboarding', 'Profiles filled at joining stay complete; back-filling later rarely finishes.', 'compliance', 0.72],
            ['Parents respond to one clear call-to-action', 'Messages with a single action were answered far more often than newsletters.', 'communication', 0.61],
        ];
        foreach ($rows as $i => [$pattern, $desc, $domain, $conf]) {
            $this->put('hpbrain_learnings', 'demo-learning-'.($i + 1), [
                'outcome_id' => $i % 2 === 0 ? $outcome : null,
                'mental_model_id' => $models[$domain] ?? null,
                'pattern' => $pattern, 'description' => $desc, 'domain' => $domain,
                'confidence' => $conf, 'reusable' => 1,
                'created_by' => $this->actor, 'created_date' => $this->ago(30 - $i * 3),
            ]);
        }
    }

    /* ---------------------------------------------------------- AI assistant */

    private function assistant(): void
    {
        $user = (string) (DB::table('tbluser')->where('sub_institute_id', $this->tenant)->where('user_profile_id', 1)->orderBy('id')->value('id') ?? 1);

        $templates = [
            ['Weekly principal briefing', 'briefing', 'Summarise the top {{count}} signals for {{school}} this week with the evidence behind each and one recommended action.', ['count', 'school']],
            ['Attendance follow-up message', 'communication', 'Write a polite message to the parents of {{student}} about {{days}} absences in {{month}}.', ['student', 'days', 'month']],
            ['Fee reminder (gentle)', 'communication', 'Draft a respectful fee reminder for {{parent}} for {{amount}} due on {{date}}.', ['parent', 'amount', 'date']],
            ['Explain a signal', 'explain', 'Explain in plain language why "{{signal}}" matters to a school leader and what to do first.', ['signal']],
            ['Root-cause analysis', 'analysis', 'List likely root causes for {{problem}} given this evidence: {{evidence}}.', ['problem', 'evidence']],
        ];
        foreach ($templates as $i => [$name, $cat, $tpl, $vars]) {
            $this->put('hpbrain_prompt_templates', 'demo-prompt-'.($i + 1), [
                'name' => $name, 'template' => $tpl, 'variables' => $this->json($vars), 'version' => 1,
                'status' => 'active', 'created_by' => $this->actor, 'created_date' => $this->ago(25),
                'category' => $cat, 'default_model' => 'claude-sonnet-5-5', 'default_temperature' => 0.40,
            ]);
        }

        $chats = [
            ['Which fee signals need attention?', 'fees', [
                ['user', 'Which fee signals need attention this week?'],
                ['assistant', 'The most important one is that fees appear to be collected outside the system: receipts exist for only 1 of 3,438 students, so outstanding balances cannot be calculated. I suggest moving counter receipts into the fee module first.', [['type' => 'signal', 'label' => 'Fees are being collected outside the system']]],
                ['user', 'What should the accountant do first?'],
                ['assistant', 'Start by entering this month’s counter receipts through the fee module, then run the pending-fees report to confirm balances match the cash book.', []],
            ]],
            ['Absentee follow-up plan', 'attendance', [
                ['user', 'Give me a plan to follow up chronic absentees.'],
                ['assistant', 'Mark attendance before the first period, send same-day alerts to class teachers, and call parents of anyone absent three days in a row. Review the list every Friday.', [['type' => 'capability', 'label' => 'Attendance coverage']]],
            ]],
            ['Department heads missing', 'organization', [
                ['user', 'How many departments have no head?'],
                ['assistant', 'Most of the 18 departments have no head assigned. Assigning a head to each is the quickest way to give every open action an owner.', [['type' => 'department', 'label' => 'Departments']]],
            ]],
            ['Prepare principal briefing', 'briefing', [
                ['user', 'Prepare this week’s principal briefing.'],
                ['assistant', 'Top items: (1) fees collected outside the system, (2) attendance not marked for all classes, (3) 233 incomplete staff profiles, (4) timetable gaps in some subjects. Each has linked evidence in the Intelligence Loop.', []],
            ]],
        ];
        foreach ($chats as $i => [$title, $ctx, $msgs]) {
            $sid = 'demo-chat-'.($i + 1);
            $this->put('hpbrain_conversation_sessions', $sid, [
                'title' => $title, 'context_type' => $ctx, 'created_by' => $user, 'pinned' => $i === 0 ? 1 : 0,
                'created_date' => $this->ago(8 - $i), 'updated_date' => $this->ago(8 - $i, 15),
            ]);
            foreach ($msgs as $j => $m) {
                $this->put('hpbrain_conversation_messages', "demo-msg-{$sid}-{$j}", [
                    'session_id' => $sid, 'role' => $m[0], 'content' => $m[1],
                    'citations' => $this->json($m[2] ?? []), 'created_date' => $this->ago(8 - $i, 10 + $j),
                ]);
            }
        }

        $services = ['briefing', 'explain', 'communication', 'analysis'];
        for ($i = 0; $i < 12; $i++) {
            $ok = $i % 6 !== 5;
            $this->put('hpbrain_ai_executions', 'demo-aiexec-'.($i + 1), [
                'user_id' => $user, 'service_name' => $services[$i % 4],
                'prompt_template_id' => 'demo-prompt-'.(($i % 5) + 1),
                'provider' => 'anthropic', 'model' => 'claude-sonnet-5-5',
                'status' => $ok ? 'completed' : 'failed',
                'input_tokens' => 600 + $i * 90, 'output_tokens' => 220 + $i * 35,
                'latency_ms' => 1100 + $i * 140, 'estimated_cost_usd' => round(0.004 + $i * 0.0007, 4),
                'error' => $ok ? null : 'Provider timeout; request retried automatically.',
                'entity_type' => 'Tenant', 'entity_id' => $this->tenant, 'created_date' => $this->ago(12 - $i),
            ]);
        }
    }

    /* ---------------------------------------------------------- agent monitor */

    private function executors(): void
    {
        $people = DB::table('hpbrain_people')->where('tenant_id', $this->tenant)->orderBy('id')->limit(4)->pluck('id')->all();
        $rows = [
            ['human', 'Principal', ['governance', 'approvals'], 0.95, 3, 1, $people[0] ?? null],
            ['human', 'Academic Coordinator', ['timetable', 'curriculum', 'attendance'], 0.88, 4, 2, $people[1] ?? null],
            ['human', 'Accounts Officer', ['fees', 'receipts'], 0.85, 5, 3, $people[2] ?? null],
            ['human', 'Class Teacher Group', ['attendance', 'parent-contact'], 0.80, 8, 4, $people[3] ?? null],
            ['ai_agent', 'Attendance Watch Agent', ['attendance', 'alerts'], 0.72, 20, 6, null],
            ['ai_agent', 'Fee Reconciliation Agent', ['fees', 'reconciliation'], 0.70, 10, 2, null],
            ['ai_agent', 'Parent Communication Agent', ['communication', 'drafting'], 0.68, 15, 5, null],
            ['ai_agent', 'Data Quality Agent', ['data-quality', 'dedup'], 0.74, 10, 0, null],
        ];
        foreach ($rows as $i => [$type, $name, $tags, $trust, $max, $load, $person]) {
            $this->put('hpbrain_executors', 'demo-exec-'.($i + 1), [
                'executor_type' => $type, 'name' => $name, 'person_id' => $person,
                'capability_tags' => $this->json($tags), 'trust_level' => $trust,
                'max_concurrent' => $max, 'current_workload' => $load, 'available' => $i === 7 ? 0 : 1,
                'status' => 'active', 'created_date' => $this->ago(30), 'updated_date' => $this->ago(1),
            ]);
        }
    }

    private function policies(): void
    {
        $rows = [
            ['Attendance alerts autonomy', 'attendance', ['ai_agent', 'human'], 'executor_autonomy', ['AI may draft parent alerts; a class teacher sends them.']],
            ['Fee reminder approval', 'fees', ['human'], 'approval_gate', ['Reminders to parents need Accounts Officer approval before sending.']],
            ['Student data access', 'data', ['human'], 'data_access', ['Only the class teacher and above may open a student profile.']],
            ['Escalation after 3 days', 'governance', ['human', 'ai_agent'], 'escalation', ['Unactioned high-severity signals escalate to the Principal after 3 days.']],
            ['Parent communication tone', 'communication', ['ai_agent'], 'content', ['Messages must be polite, in the parent’s preferred language, with one clear action.']],
        ];
        foreach ($rows as $i => [$name, $scope, $classes, $type, $rules]) {
            $this->put('hpbrain_policies', 'demo-policy-'.($i + 1), [
                'name' => $name, 'scope' => $scope, 'allowed_executor_classes' => $this->json($classes),
                'trust_levels' => $this->json(['min' => 0.6]), 'routing_criteria' => $this->json(['scope' => $scope]),
                'escalation_path' => $this->json(['Class Teacher', 'Academic Coordinator', 'Principal']),
                'status' => 'active', 'created_by' => $this->actor, 'created_date' => $this->ago(30), 'updated_date' => $this->ago(5),
                'policy_type' => $type, 'rules' => $this->json($rules), 'version' => 1,
                'approval_gates' => $this->json($type === 'approval_gate' ? ['Accounts Officer'] : []),
                'data_access_rules' => $this->json([]), 'regulatory_constraints' => $this->json([]),
            ]);
        }
    }

    /**
     * People & Competency (and other module) screens read recommendations and
     * the decision trail from signal → step → recommendation → decision →
     * execution → outcome. Where a module's own signal has no recommendation
     * yet, add up to two worked examples, every rationale prefixed "[Sample]".
     */
    private function moduleTrails(): void
    {
        $modules = ['organization', 'task-management', 'talent', 'hr', 'capability', 'lms-activity', 'staff-attendance'];
        $esos = DB::table('hpbrain_eso_definitions')->where('tenant_id', $this->tenant)->orderBy('id')->pluck('id')->all();
        if (! $esos) {
            return;
        }
        $stages = [
            ['approved', 'completed', 'improved', 0.82],
            ['approved', 'completed', 'partial', 0.70],
            ['approved', 'queued', null, 0.64],
        ];
        $k = 0;
        foreach ($modules as $m) {
            $signals = DB::table('hpbrain_signals as sg')
                ->where('sg.tenant_id', $this->tenant)->where('sg.rule_key', 'like', 'mod\_'.$m.'\_%')
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('hpbrain_reasoning_steps as rs')
                    ->join('hpbrain_recommendations as r', 'r.reasoning_step_id', '=', 'rs.id')
                    ->whereColumn('rs.signal_id', 'sg.id'))
                ->orderBy('sg.id')->limit(2)->get(['sg.id', 'sg.metadata', 'sg.rule_key']);
            foreach ($signals as $i => $sg) {
                $k++;
                $meta = json_decode((string) $sg->metadata, true) ?: [];
                $title = (string) ($meta['title'] ?? str_replace('_', ' ', $sg->rule_key));
                [$dStatus, $xStatus, $result, $conf] = $stages[$k % 3];
                $ids = fn ($t) => "demo-{$t}-{$m}-{$i}";
                $eso = $esos[$k % count($esos)];

                $this->put('hpbrain_reasoning_steps', $ids('step'), [
                    'signal_id' => $sg->id, 'step_order' => 1, 'description' => "Checked the underlying records for: {$title}.",
                    'confidence_score' => $conf, 'created_by' => $this->actor, 'created_date' => $this->ago(14),
                ]);
                $this->put('hpbrain_recommendations', $ids('rec'), [
                    'reasoning_step_id' => $ids('step'), 'category' => 'act', 'title' => 'Review and resolve: '.$title,
                    'description' => "Assign an owner, fix the records behind \"{$title}\" and confirm in the next review.",
                    'priority' => $i === 0 ? 'high' : 'medium', 'confidence' => $conf, 'status' => 'approved',
                    'created_by' => $this->actor, 'created_date' => $this->ago(13), 'updated_date' => $this->ago(12),
                    'urgency' => 'normal', 'eso_id' => $eso,
                ]);
                $this->put('hpbrain_decisions', $ids('dec'), [
                    'recommendation_id' => $ids('rec'), 'decided_by' => $this->actor, 'executor_type' => 'human',
                    'rationale' => '[Sample] Approved by the school leadership; owner assigned and review set for next month.',
                    'alternatives_considered' => $this->json(['Defer to next term', 'Handle case by case']),
                    'status' => $dStatus, 'created_date' => $this->ago(12), 'confidence' => $conf,
                    'explanation' => 'Sample decision to demonstrate the approval trail.', 'trace' => $this->json([]),
                    'approved_by' => $this->actor, 'approved_date' => $this->ago(12),
                    'approval_note' => '[Sample] Go ahead.',
                ]);
                $this->put('hpbrain_eso_executions', $ids('run'), [
                    'eso_id' => $eso, 'decision_id' => $ids('dec'), 'status' => $xStatus, 'executed_by' => $this->actor,
                    'executor_type' => 'human', 'input' => $this->json(['recommendationId' => $ids('rec')]),
                    'output' => $this->json(['result' => $result ?? 'pending']), 'eso_definition_id' => $eso,
                    'started_date' => $this->ago(11), 'completed_date' => $xStatus === 'completed' ? $this->ago(6) : null,
                    'created_date' => $this->ago(11),
                ]);
                if ($result) {
                    $this->put('hpbrain_outcomes', $ids('out'), [
                        'decision_id' => $ids('dec'), 'result' => $result, 'metrics' => $this->json([]), 'kpis' => $this->json([]),
                        'evidence_ids' => $this->json([]),
                        'feedback' => $result === 'improved' ? '[Sample] Owner assigned and the gap closed on review.' : '[Sample] Partly resolved; remaining records to be fixed next month.',
                        'confidence' => $conf, 'created_by' => $this->actor, 'created_date' => $this->ago(5),
                    ]);
                }
            }
        }
    }

    /* ------------------------------------------------------ executive metrics */

    private function metrics(): void
    {
        $series = [
            'attendance_rate' => [88.1, 89.0, 87.4, 90.2, 91.5, 92.0],
            'fee_collection_pct' => [61, 64, 66, 70, 72, 76],
            'staff_profile_complete_pct' => [48, 52, 55, 60, 63, 65],
            'timetable_coverage_pct' => [70, 74, 79, 83, 88, 91],
            'open_signals' => [112, 108, 104, 101, 98, 95],
        ];
        foreach ($series as $name => $vals) {
            foreach ($vals as $i => $v) {
                $d = (5 - $i) * 7;
                $this->put('hpbrain_metrics', "demo-m-{$name}-{$i}", [
                    'metric_name' => $name, 'metric_value' => $v,
                    'tags' => $this->json(['source' => 'demo']), 'recorded_at' => $this->ago($d),
                ]);
                $this->put('hpbrain_metric_snapshots', "demo-ms-{$name}-{$i}", [
                    'snapshot_date' => now()->subDays($d)->toDateString(), 'metric_key' => $name,
                    'dimension_key' => 'school', 'value' => $v, 'confidence' => 0.9, 'sample_n' => 100,
                    'created_date' => $this->ago($d),
                ]);
            }
        }
    }

    /* ---------------------------------------------------------- ingestion */

    private function dataSources(): void
    {
        $rows = [
            ['lms.students', 'lms', 'Student register (tblstudent)', 'student'],
            ['lms.staff', 'lms', 'Staff directory (tbluser)', 'person'],
            ['lms.departments', 'lms', 'Departments (hrms_departments)', 'department'],
            ['lms.attendance', 'lms', 'Daily attendance', 'attendance'],
            ['lms.fees', 'lms', 'Fee receipts', 'fee_receipt'],
            ['lms.timetable', 'lms', 'Timetable', 'timetable_slot'],
            ['csv.import', 'file', 'Bulk CSV import', 'generic'],
        ];
        foreach ($rows as $i => [$key, $type, $name, $entity]) {
            $this->put('hpbrain_data_sources', 'demo-ds-'.($i + 1), [
                'source_key' => $key, 'source_type' => $type, 'display_name' => $name,
                'universal_entity' => $entity, 'field_map' => $this->json(['mode' => 'auto']),
                'checkpoint' => (string) now()->subDays($i)->timestamp,
                'last_synced_at' => $this->ago($i, 6), 'is_active' => 1, 'created_by' => $this->actor,
                'created_date' => $this->ago(40), 'updated_date' => $this->ago($i),
            ]);
        }
    }

    private function events(): void
    {
        $types = ['signal.raised', 'recommendation.created', 'decision.approved', 'execution.completed', 'ingestion.completed', 'policy.updated'];
        for ($i = 0; $i < 18; $i++) {
            $t = $types[$i % count($types)];
            $this->put('hpbrain_event_store', 'demo-ev-'.($i + 1), [
                'type' => $t, 'entity_type' => ucfirst(explode('.', $t)[0]), 'entity_id' => 'demo-'.($i + 1),
                'actor_id' => $this->actor, 'payload' => $this->json(['note' => 'Demonstration event', 'seq' => $i + 1]),
                'metadata' => $this->json(['source' => 'demo']), 'status' => 'completed',
                'created_at' => $this->ago(18 - $i, 9), 'processed_at' => $this->ago(18 - $i, 9),
                'completed_at' => $this->ago(18 - $i, 9),
            ]);
        }
    }

    /* ------------------------------------------------------- graph & library */

    private function contextEntities(string $org): void
    {
        $rows = [
            ['term', 'Academic Year', 'The April–March period used for classes, fees and results.'],
            ['term', 'Section', 'A division of a class (e.g. 6-A) with its own class teacher and timetable.'],
            ['term', 'Receipt', 'Proof of a fee payment recorded in the fee module.'],
            ['term', 'Chronic absentee', 'A student absent on 20% or more of working days in a term.'],
            ['role', 'Class Teacher', 'Teacher responsible for attendance and parent contact of a section.'],
            ['role', 'Academic Coordinator', 'Owns timetable, curriculum pacing and teaching cover.'],
            ['policy', 'Fee due-date rule', 'Fees are due on the 10th; reminders start on the 12th.'],
            ['policy', 'Attendance cut-off', 'Attendance must be marked before the end of the first period.'],
        ];
        foreach ($rows as $i => [$type, $term, $meaning]) {
            $this->put('hpbrain_context_entities', 'demo-ctx-'.($i + 1), [
                'org_id' => $org, 'entity_type' => $type, 'key_term' => $term, 'canonical_meaning' => $meaning,
                'tenant_specific_value' => $this->json(['school' => 'Triz International School']),
                'status' => 'active', 'created_by' => $this->actor,
                'created_date' => $this->ago(30), 'updated_date' => $this->ago(10),
            ]);
        }
    }

    private function assignments(): void
    {
        $caps = DB::table('hpbrain_capabilities')->where('tenant_id', $this->tenant)->orderBy('id')->limit(10)->pluck('id')->all();
        $deps = DB::table('hpbrain_departments')->where('tenant_id', $this->tenant)->orderBy('id')->limit(5)->pluck('id')->all();
        $ppl = DB::table('hpbrain_people')->where('tenant_id', $this->tenant)->orderBy('id')->limit(5)->pluck('id')->all();
        $n = 0;
        foreach ($caps as $i => $cap) {
            foreach ([['department', $deps[$i % max(count($deps), 1)] ?? null], ['person', $ppl[$i % max(count($ppl), 1)] ?? null]] as [$type, $target]) {
                if (! $target) {
                    continue;
                }
                $this->put('hpbrain_capability_assignments', 'demo-ca-'.(++$n), [
                    'capability_id' => $cap, 'target_type' => $type, 'target_id' => $target,
                    'assigned_by' => $this->actor, 'assigned_date' => $this->ago(15), 'status' => 'active',
                ]);
            }
        }
    }

    private function processes(string $org): void
    {
        $rows = [
            ['PRC-ADMISSION', 'Student admission', 'admissions', ['Enquiry', 'Application', 'Document check', 'Entrance / interview', 'Offer', 'Fee payment', 'Enrolment']],
            ['PRC-FEE-COLLECT', 'Fee collection & reconciliation', 'fees', ['Generate invoice', 'Collect payment', 'Issue receipt', 'Daily cash reconciliation', 'Follow up pending']],
            ['PRC-ATTENDANCE', 'Daily attendance & absentee follow-up', 'attendance', ['Mark attendance', 'Flag absentees', 'Notify parents', 'Weekly review']],
            ['PRC-EXAM', 'Exam cycle', 'academic', ['Schedule', 'Question paper', 'Conduct', 'Evaluate', 'Publish results', 'Remedial plan']],
            ['PRC-LEAVE', 'Staff leave approval', 'hr', ['Apply', 'Reporting-manager approval', 'Arrange cover', 'Record']],
            ['PRC-TRANSPORT', 'Transport route change', 'transport', ['Request', 'Capacity check', 'Approve', 'Notify parents']],
        ];
        foreach ($rows as $i => [$code, $name, $cat, $steps]) {
            $this->put('hpbrain_process_definitions', 'demo-proc-'.($i + 1), [
                'org_id' => $org, 'process_code' => $code, 'name' => $name,
                'description' => "Standard operating flow for {$name}.", 'category' => $cat,
                'steps' => $this->json(array_map(fn ($s, $k) => ['order' => $k + 1, 'name' => $s], $steps, array_keys($steps))),
                'version' => 1, 'status' => $i < 5 ? 'published' : 'draft', 'created_by' => $this->actor,
                'created_date' => $this->ago(35), 'updated_date' => $this->ago(7),
            ]);
        }
    }

    private function efficacy(): void
    {
        $esos = DB::table('hpbrain_eso_definitions')->where('tenant_id', $this->tenant)->orderBy('id')->limit(8)->pluck('id')->all();
        $scores = [0.82, 0.74, 0.69, 0.91, 0.58, 0.77, 0.85, 0.63];
        foreach ($esos as $i => $eso) {
            $this->put('hpbrain_eso_efficacy_records', 'demo-eff-'.($i + 1), [
                'eso_definition_id' => $eso, 'gap_type' => ['attendance', 'fees', 'academic', 'compliance'][$i % 4],
                'population' => 'Whole school, current academic year', 'efficacy_score' => $scores[$i],
                'sample_size' => 25 + $i * 11, 'computed_date' => $this->ago(4 + $i), 'created_by' => $this->actor,
                'created_date' => $this->ago(4 + $i),
            ]);
        }
    }

    /* ------------------------------------------------- notifications & config */

    private function notifications(): void
    {
        $users = DB::table('tbluser')->where('sub_institute_id', $this->tenant)->where('user_profile_id', 1)->orderBy('id')->limit(5)->pluck('id')->all();
        $items = [
            ['signal', 'New high-severity signal', 'Fees are being collected outside the system.'],
            ['recommendation', 'Recommendation awaiting your decision', 'Assign a head to each department.'],
            ['execution', 'Execution completed', 'Attendance alert rollout finished for 6 classes.'],
            ['digest', 'Weekly principal briefing is ready', 'Open the Executive Dashboard to review.'],
        ];
        foreach ($users as $u) {
            foreach ($items as $i => [$type, $title, $body]) {
                $this->put('hpbrain_notifications', "demo-nt-{$u}-{$i}", [
                    'user_id' => (string) $u, 'type' => $type, 'title' => $title, 'body' => $body,
                    'entity_type' => 'Tenant', 'entity_id' => $this->tenant,
                    'read_date' => $i === 3 ? $this->ago(1) : null, 'created_date' => $this->ago($i + 1, 8),
                ]);
            }
        }
    }

    private function settings(): void
    {
        $kv = [
            'general.school_name' => 'Triz International School',
            'general.timezone' => 'Asia/Kolkata',
            'general.academic_year_start' => '04-01',
            'general.briefing_day' => 'Monday',
            'general.briefing_time' => '08:00',
            'general.attendance_cutoff' => 'End of first period',
            'general.fee_due_day' => 10,
            'general.alert_channels' => ['in_app', 'email'],
        ];
        foreach ($kv as $k => $v) {
            DB::table('hpbrain_settings')->updateOrInsert(
                ['tenant_id' => $this->tenant, 'user_id' => '_org_', 'key' => $k],
                ['value' => json_encode($v), 'updated_date' => now()]
            );
        }
    }
}
