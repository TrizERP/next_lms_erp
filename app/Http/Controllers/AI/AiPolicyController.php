<?php

namespace App\Http\Controllers\AI;

use App\Domain\AI\Support\AiAuditLogger;
use App\Services\AI\AiPolicyResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiPolicyController extends AiController
{
    public function __construct(
        private readonly AiPolicyResolver $resolver,
        private readonly AiAuditLogger $audit,
    ) {
    }

    public function options(Request $request)
    {
        try {
            $this->scope($request);

            return $this->success('AI policy options resolved.', [
                'policy_types' => $this->resolver->policyTypeOptions(),
                'rule_catalogue' => $this->resolver->ruleCatalogue(),
                'scope_types' => [
                    ['value' => 'global', 'label' => 'Global'],
                    ['value' => 'academic_year', 'label' => 'Academic year'],
                    ['value' => 'grade', 'label' => 'Grade'],
                    ['value' => 'course', 'label' => 'Course'],
                    ['value' => 'class', 'label' => 'Class'],
                    ['value' => 'assignment', 'label' => 'Assignment'],
                    ['value' => 'assessment', 'label' => 'Assessment'],
                    ['value' => 'activity', 'label' => 'Activity'],
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function index(Request $request)
    {
        try {
            $scope = $this->scope($request);
            $this->ensureDefaultExamplePolicy($scope);
            $institute = $scope->selectedInstituteId;

            $rows = DB::table('ai_policies as p')
                ->where(function ($query) use ($institute) {
                    $query->where('p.sub_institute_id', $institute)
                        ->orWhereNull('p.sub_institute_id');
                })
                ->orderByDesc('p.updated_at')
                ->orderByDesc('p.id')
                ->get()
                ->all();

            $policies = array_values(array_filter(array_map(
                fn ($row) => $this->policyDetail((int) $row->id, $institute),
                $rows
            )));

            return $this->success('AI policies resolved.', [
                'sub_institute_id' => $institute,
                'policies' => $policies,
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function store(Request $request)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;
            $data = $this->validatedPolicy($request);

            $id = DB::table('ai_policies')->insertGetId([
                'sub_institute_id' => $institute,
                'name' => trim($data['name']),
                'description' => $data['description'] !== null ? trim($data['description']) : null,
                'policy_type' => $data['policy_type'],
                'status' => $data['status'] ?? 1,
                'require_disclosure' => $data['require_disclosure'] ?? 0,
                'require_acknowledgement' => $data['require_acknowledgement'] ?? 0,
                'ai_detection_required' => $data['ai_detection_required'] ?? 0,
                'plagiarism_check_required' => $data['plagiarism_check_required'] ?? 0,
                'detection_provider' => $data['detection_provider'] ?? null,
                'detection_threshold' => $data['detection_threshold'] ?? null,
                'created_by' => $scope->userId,
                'updated_by' => $scope->userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->saveRules($id, $data['rules'] ?? []);
            $this->saveAssignments($id, $data['assignments'] ?? [], $institute, $scope->userId);

            $this->audit->record('ai.policy.created', $scope, [
                'related_type' => 'ai_policies',
                'related_id' => $id,
                'message' => 'AI policy created.',
            ]);

            return $this->success('AI policy saved.', [
                'policy' => $this->policyDetail($id, $institute),
            ], 201);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            $row = DB::table('ai_policies')->where('id', $id)->first();

            if ($row === null) {
                return $this->failure('That AI policy was not found.', 404);
            }

            $data = $this->validatedPolicy($request, false);

            DB::table('ai_policies')->where('id', $id)->update([
                'name' => trim($data['name']),
                'description' => $data['description'] !== null ? trim($data['description']) : null,
                'policy_type' => $data['policy_type'],
                'status' => $data['status'] ?? 1,
                'require_disclosure' => $data['require_disclosure'] ?? 0,
                'require_acknowledgement' => $data['require_acknowledgement'] ?? 0,
                'ai_detection_required' => $data['ai_detection_required'] ?? 0,
                'plagiarism_check_required' => $data['plagiarism_check_required'] ?? 0,
                'detection_provider' => $data['detection_provider'] ?? null,
                'detection_threshold' => $data['detection_threshold'] ?? null,
                'updated_by' => $scope->userId,
                'updated_at' => now(),
            ]);

            $this->saveRules($id, $data['rules'] ?? []);
            $this->saveAssignments($id, $data['assignments'] ?? [], $institute, $scope->userId);

            $this->audit->record('ai.policy.updated', $scope, [
                'related_type' => 'ai_policies',
                'related_id' => $id,
                'message' => 'AI policy updated.',
            ]);

            return $this->success('AI policy updated.', [
                'policy' => $this->policyDetail($id, $institute),
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    public function destroy(Request $request, int $id)
    {
        try {
            $scope = $this->scope($request);
            $institute = $scope->selectedInstituteId;

            $row = DB::table('ai_policies')->where('id', $id)->first();

            if ($row === null) {
                return $this->failure('That AI policy was not found.', 404);
            }

            DB::table('ai_policies')->where('id', $id)->update([
                'status' => 0,
                'updated_at' => now(),
            ]);

            $this->audit->record('ai.policy.retired', $scope, [
                'related_type' => 'ai_policies',
                'related_id' => $id,
                'message' => 'AI policy retired.',
            ]);

            return $this->success('AI policy retired.', [
                'id' => $id,
                'sub_institute_id' => $institute,
            ]);
        } catch (Throwable $exception) {
            return $this->handle($exception);
        }
    }

    private function validatedPolicy(Request $request, bool $isCreate = true): array
    {
        $policyTypes = array_column($this->resolver->policyTypeOptions(), 'value');
        $scopeTypes = array_column([
            ['value' => 'global'],
            ['value' => 'academic_year'],
            ['value' => 'grade'],
            ['value' => 'course'],
            ['value' => 'class'],
            ['value' => 'assignment'],
            ['value' => 'assessment'],
            ['value' => 'activity'],
        ], 'value');

        $rules = [
            'name' => 'required|string|max:191',
            'description' => 'nullable|string|max:2000',
            'policy_type' => 'required|string|in:' . implode(',', $policyTypes),
            'status' => 'nullable|integer|in:0,1',
            'require_disclosure' => 'nullable|integer|in:0,1',
            'require_acknowledgement' => 'nullable|integer|in:0,1',
            'ai_detection_required' => 'nullable|integer|in:0,1',
            'plagiarism_check_required' => 'nullable|integer|in:0,1',
            'detection_provider' => 'nullable|string|max:120',
            'detection_threshold' => 'nullable|numeric|min:0|max:100',
            'rules' => 'nullable|array',
            'rules.*' => 'nullable|boolean',
            'assignments' => 'nullable|array',
            'assignments.*.scope_type' => 'required|string|in:' . implode(',', $scopeTypes),
            'assignments.*.scope_id' => 'nullable|integer|min:1',
            'assignments.*.status' => 'nullable|integer|in:0,1',
        ];

        if ($isCreate) {
            $rules['assignments'] = 'nullable|array';
        }

        $validated = $request->validate($rules);

        $normalisedRules = [];
        foreach ($validated['rules'] ?? [] as $key => $value) {
            $normalisedRules[(string) $key] = (bool) $value;
        }

        $validated['rules'] = $normalisedRules;

        $validated['assignments'] = array_map(function (array $assignment): array {
            return [
                'scope_type' => (string) ($assignment['scope_type'] ?? 'global'),
                'scope_id' => isset($assignment['scope_id']) && $assignment['scope_id'] !== ''
                    ? (int) $assignment['scope_id']
                    : null,
                'status' => isset($assignment['status']) ? (int) $assignment['status'] : 1,
            ];
        }, $validated['assignments'] ?? []);

        return $validated;
    }

    private function ensureDefaultExamplePolicy(object $scope): void
    {
        $exists = DB::table('ai_policies')->where('is_example', 1)->exists();

        if ($exists) {
            return;
        }

        $exampleId = DB::table('ai_policies')->insertGetId([
            'sub_institute_id' => null,
            'name' => 'K-12 Assignment – AI Assisted Learning Policy',
            'description' => 'Students are allowed to use AI tools for learning support, brainstorming, concept explanations, and grammar assistance. However, students must create and submit their final assignment work themselves and must disclose any AI assistance used.',
            'policy_type' => 'ai_assisted',
            'is_example' => 1,
            'status' => 1,
            'require_disclosure' => 1,
            'require_acknowledgement' => 1,
            'ai_detection_required' => 0,
            'plagiarism_check_required' => 0,
            'detection_provider' => null,
            'detection_threshold' => null,
            'created_by' => $scope->userId,
            'updated_by' => $scope->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->saveRules($exampleId, [
            'use_ai_for_brainstorming' => true,
            'use_ai_for_grammar_spelling' => true,
            'use_ai_for_explanations' => true,
            'use_ai_for_summarization' => true,
            'use_ai_for_rewriting' => true,
            'use_ai_for_generating_answers' => false,
            'use_ai_for_generating_code' => false,
            'use_ai_for_generating_images' => false,
            'use_ai_for_completing_assignments' => false,
        ]);

        $this->saveAssignments($exampleId, [], $scope->selectedInstituteId, $scope->userId);
    }

    private function policyDetail(int $id, int|string|null $institute): ?array
    {
        $row = DB::table('ai_policies')->where('id', $id)->first();

        if ($row === null) {
            return null;
        }

        $rulesData = DB::table('ai_policy_rules')
            ->where('policy_id', $id)
            ->get()
            ->all();

        $rules = [];
        foreach ($rulesData as $rule) {
            $rules[$rule->rule_key] = (bool) $rule->rule_value;
        }

        $assignments = DB::table('ai_policy_assignments')
            ->where('policy_id', $id)
            ->orderBy('id')
            ->get()
            ->map(fn ($assignment) => [
                'id' => (int) $assignment->id,
                'policy_id' => (int) $assignment->policy_id,
                'scope_type' => $assignment->scope_type,
                'scope_id' => $assignment->scope_id !== null ? (int) $assignment->scope_id : null,
                'sub_institute_id' => $assignment->sub_institute_id !== null ? (int) $assignment->sub_institute_id : null,
                'status' => (int) $assignment->status,
            ])
            ->all();

        return [
            'id' => (int) $row->id,
            'sub_institute_id' => $row->sub_institute_id !== null ? (int) $row->sub_institute_id : null,
            'name' => $row->name,
            'description' => $row->description,
            'policy_type' => $row->policy_type,
            'is_example' => (int) ($row->is_example ?? 0),
            'status' => (int) $row->status,
            'require_disclosure' => (int) $row->require_disclosure,
            'require_acknowledgement' => (int) $row->require_acknowledgement,
            'ai_detection_required' => (int) $row->ai_detection_required,
            'plagiarism_check_required' => (int) $row->plagiarism_check_required,
            'detection_provider' => $row->detection_provider,
            'detection_threshold' => $row->detection_threshold !== null ? (float) $row->detection_threshold : null,
            'created_by' => $row->created_by,
            'updated_by' => $row->updated_by,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'rules' => $rules,
            'assignments' => $assignments,
            'institute_scope' => $institute,
        ];
    }

    private function saveRules(int $policyId, array $rules): void
    {
        DB::table('ai_policy_rules')->where('policy_id', $policyId)->delete();

        foreach ($rules as $ruleKey => $enabled) {
            if (! is_string($ruleKey) || $ruleKey === '') {
                continue;
            }

            DB::table('ai_policy_rules')->insert([
                'policy_id' => $policyId,
                'rule_key' => $ruleKey,
                'rule_value' => $enabled ? '1' : '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function saveAssignments(int $policyId, array $assignments, int|string|null $institute, int|string|null $userId): void
    {
        DB::table('ai_policy_assignments')->where('policy_id', $policyId)->delete();

        foreach ($assignments as $assignment) {
            $scopeType = trim((string) ($assignment['scope_type'] ?? 'global'));
            $scopeId = isset($assignment['scope_id']) && $assignment['scope_id'] !== ''
                ? (int) $assignment['scope_id']
                : null;

            DB::table('ai_policy_assignments')->insert([
                'policy_id' => $policyId,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'sub_institute_id' => $institute,
                'status' => isset($assignment['status']) ? (int) $assignment['status'] : 1,
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
