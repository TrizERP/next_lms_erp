<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\DB;

class AiPolicyResolver
{
    /**
     * The rule keys a policy editor can toggle, plus the labels the UI reads. The
     * values are stored in ai_policy_rules as rule_key => rule_value.
     */
    public function ruleCatalogue(): array
    {
        return [
            [
                'key' => 'use_ai_for_brainstorming',
                'label' => 'Use AI for brainstorming',
                'default' => true,
            ],
            [
                'key' => 'use_ai_for_grammar_spelling',
                'label' => 'Use AI for grammar/spelling',
                'default' => true,
            ],
            [
                'key' => 'use_ai_for_explanations',
                'label' => 'Use AI for explanations',
                'default' => true,
            ],
            [
                'key' => 'use_ai_for_generating_answers',
                'label' => 'Use AI for generating answers',
                'default' => false,
            ],
            [
                'key' => 'use_ai_for_generating_code',
                'label' => 'Use AI for generating code',
                'default' => false,
            ],
            [
                'key' => 'use_ai_for_generating_images',
                'label' => 'Use AI for generating images',
                'default' => false,
            ],
            [
                'key' => 'use_ai_for_summarization',
                'label' => 'Use AI for summarization',
                'default' => true,
            ],
            [
                'key' => 'use_ai_for_rewriting',
                'label' => 'Use AI for rewriting',
                'default' => true,
            ],
            [
                'key' => 'use_ai_for_completing_assignments',
                'label' => 'Use AI for completing assignments',
                'default' => false,
            ],
        ];
    }

    public function policyTypeOptions(): array
    {
        return [
            ['value' => 'ai_free', 'label' => 'AI-Free'],
            ['value' => 'ai_assisted', 'label' => 'AI-Assisted'],
            ['value' => 'ai_empowered', 'label' => 'AI-Empowered'],
            ['value' => 'custom', 'label' => 'Custom'],
        ];
    }

    public function resolve(int|string|null $subInstituteId, array $context = []): array
    {
        $scopeType = trim((string) ($context['scope_type'] ?? ''));
        $scopeId = isset($context['scope_id']) && $context['scope_id'] !== '' ? (int) $context['scope_id'] : null;

        $policyId = null;
        $policy = null;
        $assignments = $this->assignmentsForScope($subInstituteId, $context);

        if ($assignments !== []) {
            $policyId = (int) $assignments[0]['policy_id'];
            $policy = $this->policyRow($policyId);
        }

        $rules = $policyId ? $this->rulesForPolicy($policyId) : [];

        $operation = trim((string) ($context['operation'] ?? 'ai_request'));
        $allowed = $this->operationAllowed($operation, $policy, $rules);

        return [
            'allowed' => $allowed,
            'policy' => $policy,
            'rules' => $rules,
            'message' => $allowed
                ? 'AI request is permitted under the resolved policy.'
                : $this->restrictionMessage($policy, $operation),
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'policy_id' => $policyId,
        ];
    }

    public function activePoliciesForInstitute(int|string|null $subInstituteId): array
    {
        $rows = DB::table('ai_policies as p')
            ->leftJoin('ai_policy_assignments as a', 'a.policy_id', '=', 'p.id')
            ->where('p.status', 1)
            ->where(function ($query) use ($subInstituteId) {
                $query->where('p.sub_institute_id', $subInstituteId)
                    ->orWhereNull('p.sub_institute_id');
            })
            ->select([
                'p.id',
                'p.name',
                'p.description',
                'p.policy_type',
                'p.status',
                'p.require_disclosure',
                'p.require_acknowledgement',
                'p.ai_detection_required',
                'p.plagiarism_check_required',
                'p.detection_provider',
                'p.detection_threshold',
                'a.scope_type',
                'a.scope_id',
            ])
            ->get()
            ->all();

        return $rows;
    }

    private function assignmentsForScope(int|string|null $subInstituteId, array $context): array
    {
        $candidates = [];

        $assignmentId = isset($context['assignment_id']) && $context['assignment_id'] !== ''
            ? (int) $context['assignment_id']
            : null;

        $assessmentId = isset($context['assessment_id']) && $context['assessment_id'] !== ''
            ? (int) $context['assessment_id']
            : null;

        $activityId = isset($context['activity_id']) && $context['activity_id'] !== ''
            ? (int) $context['activity_id']
            : null;

        $classId = isset($context['class_id']) && $context['class_id'] !== ''
            ? (int) $context['class_id']
            : null;

        $courseId = isset($context['course_id']) && $context['course_id'] !== ''
            ? (int) $context['course_id']
            : null;

        $gradeId = isset($context['grade_id']) && $context['grade_id'] !== ''
            ? (int) $context['grade_id']
            : null;

        $academicYear = isset($context['academic_year']) && $context['academic_year'] !== ''
            ? (int) $context['academic_year']
            : null;

        $priority = [
            'global' => 0,
            'academic_year' => 1,
            'grade' => 2,
            'course' => 3,
            'class' => 4,
            'assignment' => 5,
            'assessment' => 6,
            'activity' => 7,
        ];

        $candidateScopes = [
            ['global', null],
            ['academic_year', $academicYear],
            ['grade', $gradeId],
            ['course', $courseId],
            ['class', $classId],
            ['assignment', $assignmentId],
            ['assessment', $assessmentId],
            ['activity', $activityId],
        ];

        foreach ($candidateScopes as [$scopeType, $scopeId]) {
            if ($scopeId === null && $scopeType !== 'global') {
                continue;
            }

            $candidates[] = [$scopeType, $scopeId, $priority[$scopeType] ?? 0];
        }

        $matches = [];

        foreach ($candidates as [$scopeType, $scopeId, $level]) {
            $row = DB::table('ai_policy_assignments as a')
                ->join('ai_policies as p', 'p.id', '=', 'a.policy_id')
                ->where('a.status', 1)
                ->where('p.status', 1)
                ->where(function ($query) use ($subInstituteId) {
                    $query->where('a.sub_institute_id', $subInstituteId)
                        ->orWhereNull('a.sub_institute_id');
                })
                ->where('a.scope_type', $scopeType)
                ->when($scopeId !== null, fn ($query) => $query->where('a.scope_id', $scopeId))
                ->when($scopeType === 'global', fn ($query) => $query->whereNull('a.scope_id'))
                ->select(['a.policy_id', 'p.name', 'p.policy_type', 'a.scope_type', 'a.scope_id'])
                ->orderByDesc('a.id')
                ->first();

            if ($row !== null) {
                $matches[] = $row + ['priority' => $level];
            }
        }

        if ($matches === []) {
            return [];
        }

        usort(
            $matches,
            static fn (array $left, array $right): int => $right['priority'] <=> $left['priority']
        );

        return $matches;
    }

    private function policyRow(int $policyId): ?array
    {
        $policy = DB::table('ai_policies')->where('id', $policyId)->first();

        if ($policy === null) {
            return null;
        }

        return [
            'id' => (int) $policy->id,
            'name' => $policy->name,
            'description' => $policy->description,
            'policy_type' => $policy->policy_type,
            'status' => (int) $policy->status,
            'require_disclosure' => (int) $policy->require_disclosure,
            'require_acknowledgement' => (int) $policy->require_acknowledgement,
            'ai_detection_required' => (int) $policy->ai_detection_required,
            'plagiarism_check_required' => (int) $policy->plagiarism_check_required,
            'detection_provider' => $policy->detection_provider,
            'detection_threshold' => $policy->detection_threshold,
            'created_by' => $policy->created_by,
            'updated_by' => $policy->updated_by,
            'created_at' => $policy->created_at,
            'updated_at' => $policy->updated_at,
        ];
    }

    private function rulesForPolicy(int $policyId): array
    {
        $rules = DB::table('ai_policy_rules')
            ->where('policy_id', $policyId)
            ->get()
            ->all();

        $mapped = [];

        foreach ($rules as $rule) {
            $mapped[$rule->rule_key] = $this->coerceRuleValue($rule->rule_value);
        }

        foreach ($this->ruleCatalogue() as $catalogueRule) {
            $mapped[$catalogueRule['key']] ??= (bool) $catalogueRule['default'];
        }

        return $mapped;
    }

    private function coerceRuleValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    private function operationAllowed(string $operation, ?array $policy, array $rules): bool
    {
        if ($policy === null || ($policy['status'] ?? 0) !== 1) {
            return true;
        }

        $type = (string) ($policy['policy_type'] ?? 'custom');

        if ($type === 'ai_free') {
            return false;
        }

        if ($type === 'ai_empowered') {
            return true;
        }

        $ruleKeyMap = [
            'brainstorming' => 'use_ai_for_brainstorming',
            'grammar' => 'use_ai_for_grammar_spelling',
            'explanations' => 'use_ai_for_explanations',
            'answers' => 'use_ai_for_generating_answers',
            'generate_answers' => 'use_ai_for_generating_answers',
            'code' => 'use_ai_for_generating_code',
            'images' => 'use_ai_for_generating_images',
            'summarization' => 'use_ai_for_summarization',
            'rewriting' => 'use_ai_for_rewriting',
            'completing_assignments' => 'use_ai_for_completing_assignments',
            'assignment_completion' => 'use_ai_for_completing_assignments',
            'ai_request' => 'use_ai_for_explanations',
        ];

        $key = $ruleKeyMap[$operation] ?? null;

        if ($key === null) {
            return true;
        }

        return (bool) ($rules[$key] ?? false);
    }

    private function restrictionMessage(?array $policy, string $operation): string
    {
        if ($policy === null) {
            return 'This AI operation is not allowed by the active policy.';
        }

        $policyName = $policy['name'] ?? 'AI policy';

        return sprintf(
            'This AI operation is blocked by the %s policy because the configured AI usage rules do not permit %s.',
            $policyName,
            $operation
        );
    }
}
