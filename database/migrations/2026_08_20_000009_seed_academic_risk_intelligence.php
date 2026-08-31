<?php

use App\Domain\Governance\Verb;
use App\Domain\K12\AcademicRisk\AcademicRiskAgent;
use App\Domain\K12\AcademicRisk\AcademicRiskMetrics;
use App\Domain\K12\AcademicRisk\AssessmentDeclineDetector;
use App\Domain\K12\AcademicRisk\AttendanceRiskDetector;
use App\Domain\K12\AcademicRisk\MissedAssignmentDetector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds the Academic Risk domain: signal definitions, the agent manifest, the
 * intervention workflow, and the one prompt template it may use.
 *
 * The signal thresholds recorded here are the ones already in
 * PredictiveInterventionEngine (bands at 0.75 / 0.5 / 0.25, triggers at 0.6 / 0.7 /
 * 0.75). They are written as tenant-null baselines for visibility in the admin UI;
 * ThresholdRegistry still reads the engine at runtime, so the engine remains the
 * source of truth and these cannot silently diverge.
 *
 * The agent's manifest is the important part. `max_verb` is `recommend` and
 * `may_execute_actions` is false, so the Academic Risk agent physically cannot create
 * an intervention — only draft one for a teacher to approve.
 *
 * Idempotent.
 */
return new class extends Migration
{
    private const AGENT_KEY = 'k12_academic_risk';

    public function up(): void
    {
        $this->seedSignalDefinitions();
        $this->seedAgent();
        $this->seedWorkflow();
        $this->seedTemplates();
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_agents')) {
            DB::table('ai_agents')->where('agent_key', self::AGENT_KEY)->whereNull('sub_institute_id')->delete();
        }

        if (Schema::hasTable('workflow_definitions')) {
            $definition = DB::table('workflow_definitions')
                ->where('workflow_key', AcademicRiskAgent::WORKFLOW_KEY)
                ->whereNull('sub_institute_id')
                ->first();

            if ($definition && Schema::hasTable('workflow_versions')) {
                DB::table('workflow_versions')->where('definition_id', $definition->id)->delete();
            }

            DB::table('workflow_definitions')
                ->where('workflow_key', AcademicRiskAgent::WORKFLOW_KEY)
                ->whereNull('sub_institute_id')
                ->delete();
        }

        if (Schema::hasTable('ai_signal_definitions')) {
            DB::table('ai_signal_definitions')
                ->whereIn('signal_key', [
                    AssessmentDeclineDetector::KEY,
                    AttendanceRiskDetector::KEY,
                    MissedAssignmentDetector::KEY,
                ])
                ->whereNull('sub_institute_id')
                ->delete();
        }

        if (Schema::hasTable('ai_templates')) {
            DB::table('ai_templates')
                ->where('template_key', 'k12.intervention_activity')
                ->whereNull('sub_institute_id')
                ->delete();
        }
    }

    private function seedSignalDefinitions(): void
    {
        if (! Schema::hasTable('ai_signal_definitions')) {
            return;
        }

        // Mirrors PredictiveInterventionEngine::classifyRisk(). Kept here for the
        // admin UI; the engine remains authoritative at runtime.
        $bands = ['bands' => ['critical' => 0.75, 'high' => 0.5, 'moderate' => 0.25]];

        $definitions = [
            [
                'signal_key' => AssessmentDeclineDetector::KEY,
                'label' => 'Declining assessment performance',
                'description' => 'Recent assessment results have fallen relative to the previous window, or sit below expectation.',
                'detector_class' => AssessmentDeclineDetector::class,
                'thresholds' => $bands + ['trigger' => 0.7],
                'inputs' => ['lms_online_exam'],
            ],
            [
                'signal_key' => AttendanceRiskDetector::KEY,
                'label' => 'Attendance risk',
                'description' => 'Absence rate or consecutive absence is high enough to affect learning.',
                'detector_class' => AttendanceRiskDetector::class,
                'thresholds' => $bands + ['trigger' => 0.6],
                'inputs' => ['attendance_student'],
            ],
            [
                'signal_key' => MissedAssignmentDetector::KEY,
                'label' => 'Repeatedly incomplete assigned work',
                'description' => 'Assigned homework or activities are going uncompleted past their due date.',
                'detector_class' => MissedAssignmentDetector::class,
                'thresholds' => $bands + ['trigger' => 0.6],
                'inputs' => ['homework'],
            ],
        ];

        foreach ($definitions as $definition) {
            $payload = [
                'label' => $definition['label'],
                'domain' => 'k12',
                'subject_entity_key' => 'student',
                'description' => $definition['description'],
                'detector_class' => $definition['detector_class'],
                'severity_scale' => 'risk_score',
                'thresholds' => json_encode($definition['thresholds']),
                'inputs' => json_encode($definition['inputs']),
                'requires_evidence' => true,
                'status' => 1,
                'updated_at' => now(),
            ];

            $this->upsert('ai_signal_definitions', ['signal_key' => $definition['signal_key']], $payload, [
                'signal_key' => $definition['signal_key'],
            ]);
        }
    }

    private function seedAgent(): void
    {
        if (! Schema::hasTable('ai_agents')) {
            return;
        }

        $payload = [
            'name' => 'Academic Risk Agent',
            'domain' => 'k12',
            'purpose' => 'Identify students showing academic risk, explain why with evidence, and draft an intervention for a teacher to approve.',
            'description' => 'Runs the assessment-decline, attendance and missed-assignment detectors, builds a case per student, composes an evidence-backed explanation and drafts an intervention recommendation.',
            'runner_class' => AcademicRiskAgent::class,
            'agent_type' => 'domain',

            // Reach. Read-only entities the agent needs to do its job, nothing more.
            'allowed_tools' => json_encode([]),
            'allowed_entities' => json_encode([
                'student', 'enrollment', 'standard', 'division', 'subject',
                'assessment', 'attendance_record', 'learning_evidence',
                'signal', 'evidence', 'case', 'recommendation',
            ]),
            'allowed_signal_keys' => json_encode([
                AssessmentDeclineDetector::KEY,
                AttendanceRiskDetector::KEY,
                MissedAssignmentDetector::KEY,
            ]),

            // The ceiling. It may recommend; it may not execute.
            'max_verb' => Verb::Recommend->value,
            'may_execute_actions' => false,
            'authorized_workflow_keys' => AcademicRiskAgent::WORKFLOW_KEY,

            'input_schema' => json_encode([
                'type' => 'object',
                'properties' => [
                    'subject_id' => ['type' => 'integer'],
                    'student_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 200],
                ],
            ]),
            'output_schema' => json_encode([
                'type' => 'object',
                'required' => ['students_at_risk', 'cases'],
                'properties' => [
                    'students_at_risk' => ['type' => 'integer'],
                    'cases' => ['type' => 'array'],
                    'confidence' => ['type' => 'number'],
                ],
            ]),
            'required_permissions' => json_encode(['lms:student:read']),
            // Students may not run risk analysis over their peers.
            'allowed_roles' => json_encode(['admin', 'staff']),

            'min_confidence' => 0.5,
            'min_evidence_count' => 1,
            'timeout_seconds' => 120,
            'max_retries' => 1,
            'config' => json_encode(['case_type' => AcademicRiskAgent::CASE_TYPE]),
            'status' => 1,
            'updated_at' => now(),
        ];

        $this->upsert('ai_agents', ['agent_key' => self::AGENT_KEY], $payload, [
            'agent_key' => self::AGENT_KEY,
        ]);
    }

    private function seedWorkflow(): void
    {
        if (! Schema::hasTable('workflow_definitions') || ! Schema::hasTable('workflow_versions')) {
            return;
        }

        $definitionPayload = [
            'name' => 'Academic intervention',
            'domain' => 'k12',
            'module' => 'academic_intervention',
            'description' => 'Runs after a teacher approves an academic risk recommendation: generate the activity, confirm with the teacher, create the intervention, notify, and capture the baseline to measure against.',
            'trigger_type' => 'recommendation_approved',
            'trigger_config' => json_encode(['recommendation_action_type' => 'create_academic_intervention']),
            'conditions' => json_encode([
                ['field' => 'student_id', 'operator' => 'exists'],
            ]),
            'subject_entity_key' => 'student',
            'required_permissions' => json_encode(['lms:student:read']),
            'allowed_roles' => json_encode(['admin', 'staff']),
            'requires_approval' => true,
            'is_consequential' => true,
            'timeout_minutes' => 60 * 24 * 7,
            'max_retries' => 1,
            'status' => 1,
            'updated_at' => now(),
        ];

        $definitionId = $this->upsert(
            'workflow_definitions',
            ['workflow_key' => AcademicRiskAgent::WORKFLOW_KEY],
            $definitionPayload,
            ['workflow_key' => AcademicRiskAgent::WORKFLOW_KEY]
        );

        if ($definitionId === null) {
            return;
        }

        /*
         * The step graph.
         *
         * Note the ordering: generation happens BEFORE the approval step, so the
         * teacher approves a concrete activity rather than a promise. The action step
         * that actually creates the intervention sits after the approval, and the
         * engine independently re-checks the decision record before running it.
         */
        $steps = [
            [
                'key' => 'generate_activity',
                'type' => 'generate',
                'label' => 'Draft intervention activities',
                'sequence' => 0,
                'config' => [
                    'template' => 'k12.intervention_activity',
                    'purpose' => 'intervention_activity',
                    'domain' => 'k12',
                    // Optional: a generation outage must not stop a teacher acting.
                    'required' => false,
                    'variables_from' => [
                        'student_name' => 'input.student_name',
                        'subject_name' => 'input.focus_subject_name',
                        'severity' => 'input.severity',
                    ],
                ],
                'next' => 'teacher_approval',
            ],
            [
                'key' => 'teacher_approval',
                'type' => 'approval',
                'label' => 'Teacher confirms the intervention',
                'sequence' => 1,
                'config' => [
                    'step_key' => 'teacher_approval',
                    'approver_role' => 'staff',
                    'expires_in_hours' => 168,
                ],
                'next' => 'create_intervention',
            ],
            [
                'key' => 'create_intervention',
                'type' => 'action',
                'label' => 'Create the intervention and assign activities',
                'sequence' => 2,
                'config' => [
                    'action' => 'create_academic_intervention',
                    'intervention_type' => 'academic_support',
                    'due_in_days' => 14,
                ],
                'next' => 'notify_student',
            ],
            [
                'key' => 'notify_student',
                'type' => 'notify',
                'label' => 'Tell the student what has been assigned',
                'sequence' => 3,
                'config' => [
                    'channel' => 'in_app',
                    'audience' => 'student',
                    'notification_type' => 'ACADEMIC_SUPPORT',
                    'recipient_from' => 'input.student_id',
                    'message' => 'Your teacher has assigned some extra practice to help you catch up. Check your activities.',
                ],
                'next' => 'capture_baseline',
            ],
            [
                'key' => 'capture_baseline',
                'type' => 'measure',
                'label' => 'Record the starting point',
                'sequence' => 4,
                'config' => [],
                'next' => null,
            ],
        ];

        $existingVersion = DB::table('workflow_versions')
            ->where('definition_id', $definitionId)
            ->where('version', 1)
            ->first();

        $versionPayload = [
            'status' => 'published',
            'steps' => json_encode($steps),
            'outcome_metrics' => json_encode([AcademicRiskMetrics::ASSESSMENT_AVERAGE]),
            'entry_step_key' => 'generate_activity',
            'change_note' => 'Initial published version.',
            'published_at' => now(),
            'updated_at' => now(),
        ];

        if ($existingVersion) {
            DB::table('workflow_versions')->where('id', $existingVersion->id)->update($versionPayload);
            $versionId = (int) $existingVersion->id;
        } else {
            $versionId = (int) DB::table('workflow_versions')->insertGetId($versionPayload + [
                'definition_id' => $definitionId,
                'version' => 1,
                'created_at' => now(),
            ]);
        }

        DB::table('workflow_definitions')
            ->where('id', $definitionId)
            ->update(['active_version_id' => $versionId, 'updated_at' => now()]);
    }

    private function seedTemplates(): void
    {
        if (! Schema::hasTable('ai_templates')) {
            return;
        }

        $payload = [
            'name' => 'Intervention activity draft',
            'domain' => 'k12',
            'category' => 'intervention',
            'description' => 'Drafts two or three short practice activities for a student who needs academic support.',
            'status' => 'published',
            'system_prompt' => implode(' ', [
                'You draft short, practical practice activities for school teachers to assign.',
                'Write for the teacher, not the student. Be concrete and brief.',
                'Do not state facts about the student beyond what you are given.',
                'Do not diagnose, label, or speculate about the student personally.',
                'Return JSON only.',
            ]),
            'user_prompt' => implode("\n", [
                'Draft 2-3 short practice activities for a student who needs academic support.',
                '',
                'Student: {{student_name}}',
                'Subject focus: {{subject_name}}',
                'Risk level: {{severity}}',
                '',
                'Each activity should take 15-20 minutes and be completable independently.',
                'Return JSON of the form:',
                '{"activities":[{"title":"...","type":"practice","instructions":"..."}]}',
            ]),
            'variables' => json_encode([
                ['key' => 'student_name', 'label' => 'Student name', 'required' => true, 'type' => 'string'],
                ['key' => 'subject_name', 'label' => 'Subject', 'required' => false, 'type' => 'string'],
                ['key' => 'severity', 'label' => 'Risk level', 'required' => false, 'type' => 'string'],
            ]),
            'output_schema' => json_encode([
                'type' => 'object',
                'required' => ['activities'],
                'properties' => [
                    'activities' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'items' => [
                            'type' => 'object',
                            'required' => ['title', 'instructions'],
                            'properties' => [
                                'title' => ['type' => 'string', 'minLength' => 3, 'maxLength' => 200],
                                'type' => ['type' => 'string'],
                                'instructions' => ['type' => 'string', 'minLength' => 10],
                            ],
                        ],
                    ],
                ],
            ]),
            'output_format' => 'json',
            'provider' => 'openrouter',
            'model' => null,
            'temperature' => 0.4,
            'max_tokens' => 900,
            'safety_rules' => json_encode([]),
            // Generated practice is content, never evidence about the student.
            'allow_as_evidence' => false,
            'requires_review' => true,
            'updated_at' => now(),
        ];

        $this->upsert(
            'ai_templates',
            ['template_key' => 'k12.intervention_activity', 'version' => 1],
            $payload,
            ['template_key' => 'k12.intervention_activity', 'version' => 1]
        );
    }

    /**
     * Insert-or-update against the platform baseline (sub_institute_id IS NULL),
     * never touching a tenant's own override.
     */
    private function upsert(string $table, array $match, array $payload, array $insertOnly): ?int
    {
        $query = DB::table($table)->whereNull('sub_institute_id');

        foreach ($match as $column => $value) {
            $query->where($column, $value);
        }

        $existing = $query->first();

        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($payload);

            return (int) $existing->id;
        }

        return (int) DB::table($table)->insertGetId($payload + $insertOnly + [
            'sub_institute_id' => null,
            'client_id' => null,
            'created_at' => now(),
        ]);
    }
};
