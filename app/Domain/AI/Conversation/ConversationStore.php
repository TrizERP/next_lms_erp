<?php

namespace App\Domain\AI\Conversation;

use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The thread, and the memory that makes a follow-up question possible.
 *
 * "Why is she at risk?" only means something because the previous turn named a
 * student. That carry-over is the whole job of this class, and it is deliberately
 * explicit: memory holds named referents (student, case, recommendation, workflow run,
 * outcome) rather than a transcript blob, so what the system inherited from an earlier
 * turn is inspectable and reportable in the trace rather than implied.
 *
 * Scope is pinned on the conversation row and re-checked on every load. A thread
 * belongs to one user in one institute, so a conversation id cannot be used to reach
 * another school's history.
 */
class ConversationStore
{
    /** Referents that survive between turns. */
    public const MEMORY_KEYS = [
        'student_id',
        'student_name',
        'case_id',
        'case_reference',
        'recommendation_id',
        'recommendation_reference',
        'workflow_run_id',
        'outcome_id',
        'agent_run_id',
        'last_intent',
        'last_case_list',
    ];

    /**
     * Load an existing thread or open a new one.
     *
     * @return array{id:int|null, reference:string|null, memory:array, turn_count:int}
     */
    public function open(?int $conversationId, McpRequestContext $scope, string $moduleKey = 'student_profiles'): array
    {
        if (! Schema::hasTable('ai_conversations')) {
            // The console still works without the tables; it simply forgets between
            // turns, and the trace says so rather than pretending to remember.
            return ['id' => null, 'reference' => null, 'memory' => [], 'turn_count' => 0];
        }

        if ($conversationId !== null) {
            $row = DB::table('ai_conversations')
                ->where('id', $conversationId)
                ->where('sub_institute_id', $scope->selectedInstituteId)
                ->where('user_id', $scope->userId)
                ->first();

            if ($row) {
                return [
                    'id' => (int) $row->id,
                    'reference' => $row->conversation_reference,
                    'memory' => $this->decode($row->memory),
                    'turn_count' => (int) $row->turn_count,
                ];
            }
            // An unknown or out-of-scope id opens a fresh thread rather than failing —
            // and, because memory starts empty, cannot leak the other thread's context.
        }

        $reference = $this->nextReference();

        $id = (int) DB::table('ai_conversations')->insertGetId([
            'conversation_reference' => $reference,
            'module_key' => $moduleKey,
            'title' => null,
            'memory' => json_encode([]),
            'turn_count' => 0,
            'status' => 'open',
            'user_id' => $scope->userId,
            'actor_role' => $scope->role,
            'sub_institute_id' => $scope->selectedInstituteId,
            'client_id' => $scope->clientId,
            'academic_year' => $scope->academicYear,
            'term_id' => $scope->termId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id, 'reference' => $reference, 'memory' => [], 'turn_count' => 0];
    }

    /**
     * Fill the slots this intent needs but the sentence did not supply.
     *
     * Returns the enriched intent plus a note of what came from memory, so the trace can
     * say "student was carried over from turn 2" instead of silently inventing a subject.
     *
     * @return array{0:Intent, 1:array<string,mixed>}
     */
    public function resolveReferents(Intent $intent, array $memory): array
    {
        if ($intent->isUnknown()) {
            return [$intent, []];
        }

        $required = IntentClassifier::requiredSlots($intent->key);
        $inherited = [];
        $slots = [];

        // "Student A" / "Student B" are positions in the list the previous answer showed,
        // not names. Resolve them against that list before anything else, so the
        // documented walkthrough reads the way a person would expect.
        if ($intent->slot('student_label') !== null && isset($memory['last_case_list'])) {
            $index = ord(substr((string) $intent->slot('student_label'), -1)) - ord('A');
            $listed = $memory['last_case_list'][$index] ?? null;

            if (is_array($listed)) {
                $slots['student_id'] = (int) $listed['student_id'];
                $slots['case_id'] = (int) $listed['case_id'];
                $slots['student_name'] = $listed['student_name'] ?? null;
                $inherited['student_label_resolved_to'] = $listed;
            }
        }

        // A referent already pinned above — by the sentence, or by a "Student A" position —
        // is never overwritten by what the thread happens to remember.
        $known = fn (string $slot) => $intent->slot($slot) !== null || isset($slots[$slot]);

        foreach ($required as $need) {
            switch ($need) {
                case 'student':
                    if (! $known('student_id') && isset($memory['student_id'])) {
                        $slots['student_id'] = (int) $memory['student_id'];
                        $inherited['student_id'] = $memory['student_id'];

                        if (isset($memory['student_name'])) {
                            $slots['student_name'] = $memory['student_name'];
                            $inherited['student_name'] = $memory['student_name'];
                        }
                    }
                    break;

                case 'case':
                    if (! $known('case_id') && isset($memory['case_id'])) {
                        $slots['case_id'] = (int) $memory['case_id'];
                        $inherited['case_id'] = $memory['case_id'];
                    }
                    break;

                case 'recommendation':
                    if (! $known('recommendation_id') && isset($memory['recommendation_id'])) {
                        $slots['recommendation_id'] = (int) $memory['recommendation_id'];
                        $inherited['recommendation_id'] = $memory['recommendation_id'];
                    }
                    break;
            }
        }

        // A student named in memory is useful to most intents, not only the ones that
        // demand it — but it is never allowed to override what the sentence said.
        if (! $known('student_id') && isset($memory['student_id'])) {
            $slots['student_id'] = (int) $memory['student_id'];
            $inherited['student_id'] = $memory['student_id'];
        }

        return [$intent->with($slots, $inherited), $inherited];
    }

    /**
     * Write the turn, and update what the thread remembers.
     */
    public function recordTurn(
        ?int $conversationId,
        McpRequestContext $scope,
        string $question,
        Intent $intent,
        array $answer,
        FlowTrace $trace,
        array $links,
        int $durationMs,
        ?string $error = null
    ): ?int {
        if ($conversationId === null || ! Schema::hasTable('ai_conversation_turns')) {
            return null;
        }

        $sequence = ((int) DB::table('ai_conversation_turns')
            ->where('conversation_id', $conversationId)
            ->max('sequence')) + 1;

        $turnId = (int) DB::table('ai_conversation_turns')->insertGetId([
            'conversation_id' => $conversationId,
            'sequence' => $sequence,
            'question' => $question,
            'intent_key' => $intent->key,
            'intent_confidence' => round($intent->confidence, 4),
            'intent_slots' => json_encode($intent->slots),
            'answer' => json_encode($answer),
            'trace' => json_encode($trace->toArray()),
            'stage_counts' => json_encode($trace->summaryCounts()),
            'subject_entity_key' => $links['subject_entity_key'] ?? null,
            'subject_id' => $links['student_id'] ?? null,
            'case_id' => $links['case_id'] ?? null,
            'recommendation_id' => $links['recommendation_id'] ?? null,
            'agent_run_id' => $links['agent_run_id'] ?? null,
            'workflow_run_id' => $links['workflow_run_id'] ?? null,
            'decision_id' => $links['decision_id'] ?? null,
            'duration_ms' => $durationMs,
            'status' => $error === null ? 'answered' : 'failed',
            'error_message' => $error,
            'sub_institute_id' => $scope->selectedInstituteId,
            'user_id' => $scope->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->rememberOn($conversationId, $scope, $links + ['last_intent' => $intent->key], $question, $sequence);

        return $turnId;
    }

    /**
     * Merge new referents into the thread's memory.
     *
     * Only the keys in MEMORY_KEYS are kept, and a null never overwrites a value — a
     * turn that did not mention a case must not erase the case the thread is about.
     */
    public function rememberOn(
        int $conversationId,
        McpRequestContext $scope,
        array $referents,
        ?string $question = null,
        int $sequence = 0
    ): void {
        if (! Schema::hasTable('ai_conversations')) {
            return;
        }

        $row = DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->first();

        if (! $row) {
            return;
        }

        $memory = $this->decode($row->memory);

        foreach (self::MEMORY_KEYS as $key) {
            if (array_key_exists($key, $referents) && $referents[$key] !== null && $referents[$key] !== []) {
                $memory[$key] = $referents[$key];
            }
        }

        DB::table('ai_conversations')->where('id', $conversationId)->update([
            'memory' => json_encode($memory),
            'turn_count' => $sequence > 0 ? $sequence : (int) $row->turn_count,
            'title' => $row->title ?: ($question ? mb_substr($question, 0, 200) : null),
            'last_turn_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The thread, oldest turn first — what the console renders on reload and what the
     * test plan uses to prove the journey was continuous.
     */
    public function transcript(int $conversationId, McpRequestContext $scope, int $limit = 50): array
    {
        if (! Schema::hasTable('ai_conversations')) {
            return ['conversation' => null, 'turns' => []];
        }

        $conversation = DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->where('user_id', $scope->userId)
            ->first();

        if (! $conversation) {
            return ['conversation' => null, 'turns' => []];
        }

        $turns = DB::table('ai_conversation_turns')
            ->where('conversation_id', $conversationId)
            ->orderBy('sequence')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $trace = $this->decode($row->trace);
                $lifecycle = (new LifecycleTraceProjector())->project($trace);

                return [
                    'id' => (int) $row->id,
                    'sequence' => (int) $row->sequence,
                    'question' => $row->question,
                    'intent' => [
                        'key' => $row->intent_key,
                        'confidence' => $row->intent_confidence === null ? null : (float) $row->intent_confidence,
                        'slots' => $this->decode($row->intent_slots),
                    ],
                    'answer' => $this->decode($row->answer),
                    'trace' => $trace,
                    'stage_counts' => $this->decode($row->stage_counts),
                    'lifecycle_trace' => $lifecycle,
                    'lifecycle_stage_counts' => (new LifecycleTraceProjector())->summaryCounts($lifecycle),
                    'links' => array_filter([
                        'case_id' => $row->case_id ? (int) $row->case_id : null,
                        'recommendation_id' => $row->recommendation_id ? (int) $row->recommendation_id : null,
                        'agent_run_id' => $row->agent_run_id ? (int) $row->agent_run_id : null,
                        'workflow_run_id' => $row->workflow_run_id ? (int) $row->workflow_run_id : null,
                        'student_id' => $row->subject_id ? (int) $row->subject_id : null,
                    ]),
                    'duration_ms' => $row->duration_ms === null ? null : (int) $row->duration_ms,
                    'status' => $row->status,
                    'asked_at' => $row->created_at,
                ];
            })
            ->all();

        return [
            'conversation' => [
                'id' => (int) $conversation->id,
                'reference' => $conversation->conversation_reference,
                'module_key' => $conversation->module_key,
                'title' => $conversation->title,
                'memory' => $this->decode($conversation->memory),
                'turn_count' => (int) $conversation->turn_count,
                'started_at' => $conversation->created_at,
                'last_turn_at' => $conversation->last_turn_at,
            ],
            'turns' => $turns,
        ];
    }

    // ---------------------------------------------------------------- internals

    private function nextReference(): string
    {
        $prefix = sprintf('CONV-%d-', now()->year);

        $last = DB::table('ai_conversations')
            ->where('conversation_reference', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('conversation_reference');

        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
