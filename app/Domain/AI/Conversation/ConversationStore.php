<?php

namespace App\Domain\AI\Conversation;

use App\Domain\AI\Lifecycle\RecordableTrace;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

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
    /** Retries allowed when two threads race for the same reference. */
    private const REFERENCE_ATTEMPTS = 3;

    /** Retries allowed when two turns race for the same sequence number. */
    private const SEQUENCE_ATTEMPTS = 3;

    private ?bool $hasConversations = null;

    private ?bool $hasTurns = null;

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
        /*
         * A task the thread is part-way through.
         *
         * Everything else here is a referent — a noun an later turn can point at. This
         * is different: it is a task with state, and it is what makes a multi-turn
         * exchange possible at all. Confirming an admission takes three turns because
         * the estate needs seven fields and the user has four of them; without somewhere
         * to park "we are collecting fields for enquiry 21", each turn would start over
         * and the flow could never finish.
         *
         * Shape: {kind, ...task state}. Cleared with forgetOn(), never by writing null.
         */
        'pending_action',
    ];

    /**
     * Load an existing thread or open a new one.
     *
     * Opening a fresh thread when the caller asked for a specific one is a legitimate
     * outcome — an id from another user or another institute must not be honoured, and
     * memory starting empty is what stops it leaking. What was wrong was doing it
     * *silently*: the turn came back reading `turn 1, memory {}` with nothing anywhere
     * saying the requested thread had been declined, which is indistinguishable from
     * the frontend having failed to send an id at all. Those two have completely
     * different fixes, so the return now carries which happened and why.
     *
     * @return array{
     *   id:int|null, reference:string|null, memory:array, turn_count:int,
     *   module_key:string|null, requested_id:int|null, reused:bool,
     *   not_reused_reason:string|null
     * }
     */
    public function open(?int $conversationId, McpRequestContext $scope, string $moduleKey = 'student_profiles'): array
    {
        if (! $this->hasConversations()) {
            // The console still works without the tables; it simply forgets between
            // turns, and the trace says so rather than pretending to remember.
            return $this->ephemeral(
                $conversationId,
                'The ai_conversations table is not present in this database, so nothing about this '
                . 'thread can be stored or recalled. Run the AI conversation migration to fix it.'
            );
        }

        $declined = null;

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
                    'module_key' => $row->module_key,
                    'requested_id' => $conversationId,
                    'reused' => true,
                    'not_reused_reason' => null,
                ];
            }

            $declined = sprintf(
                'Conversation #%d was requested but is not readable as user %d in institute %d — it '
                . 'does not exist, or belongs to another user or another school. A new thread was '
                . 'opened instead, with empty memory, rather than reading someone else\'s.',
                $conversationId,
                $scope->userId,
                $scope->selectedInstituteId
            );
        }

        return $this->create($scope, $moduleKey, $conversationId, $declined);
    }

    /**
     * Insert a new thread, tolerating a reference collision.
     *
     * `conversation_reference` is unique and its sequence is read-then-written, so two
     * questions asked in the same instant compute the same reference and one insert
     * fails. That used to escape as a QueryException, which the pipeline turned into a
     * blocked stage 1 — halting all twelve stages and returning no answer at all,
     * because two people pressed Ask together. A retry costs one query; the alternative
     * cost the whole turn.
     *
     * @return array<string, mixed>
     */
    private function create(
        McpRequestContext $scope,
        string $moduleKey,
        ?int $requestedId,
        ?string $declined
    ): array {
        for ($attempt = 0; $attempt < self::REFERENCE_ATTEMPTS; $attempt++) {
            $reference = $this->nextReference($attempt);

            try {
                $id = (int) DB::table('ai_conversations')->insertGetId([
                    'conversation_reference' => $reference,
                    'module_key' => $moduleKey,
                    'title' => null,
                    'memory' => $this->encode([]),
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

                return [
                    'id' => $id,
                    'reference' => $reference,
                    'memory' => [],
                    'turn_count' => 0,
                    'module_key' => $moduleKey,
                    'requested_id' => $requestedId,
                    'reused' => false,
                    'not_reused_reason' => $declined,
                ];
            } catch (QueryException $exception) {
                if ($this->isDuplicate($exception) && $attempt < self::REFERENCE_ATTEMPTS - 1) {
                    continue;
                }

                $this->warn('AI conversation could not be opened.', $exception, [
                    'reference' => $reference,
                    'user_id' => $scope->userId,
                    'sub_institute_id' => $scope->selectedInstituteId,
                ]);

                // The docblock on ConversationalAiStage promises that an unopenable
                // thread costs continuity rather than the answer. This is where that
                // promise is kept: the turn runs on an in-memory thread and the trace
                // says the storage failed.
                return $this->ephemeral(
                    $requestedId,
                    'The thread could not be written to the database, so this turn will not be '
                    . 'remembered: ' . $exception->getMessage()
                );
            }
        }

        return $this->ephemeral(
            $requestedId,
            'A unique conversation reference could not be allocated after '
            . self::REFERENCE_ATTEMPTS . ' attempts.'
        );
    }

    /**
     * A thread that exists only for this turn.
     *
     * @return array<string, mixed>
     */
    private function ephemeral(?int $requestedId, string $reason): array
    {
        return [
            'id' => null,
            'reference' => null,
            'memory' => [],
            'turn_count' => 0,
            'module_key' => null,
            'requested_id' => $requestedId,
            'reused' => false,
            'not_reused_reason' => $reason,
        ];
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
        // Widened from FlowTrace so both pipelines can record a turn: the legacy
        // fifteen-stage ladder and the twelve-stage lifecycle store identically, which
        // is what lets the cutover happen module by module rather than all at once.
        RecordableTrace $trace,
        array $links,
        int $durationMs,
        ?string $error = null
    ): ?int {
        if ($conversationId === null || ! $this->hasTurns()) {
            return null;
        }

        $row = [
            'conversation_id' => $conversationId,
            'question' => $question,
            'intent_key' => $intent->key,
            'intent_confidence' => round($intent->confidence, 4),
            'intent_slots' => $this->encode($intent->slots),
            'answer' => $this->encode($answer),
            'trace' => $this->encode($trace->toArray()),
            'stage_counts' => $this->encode($trace->summaryCounts()),
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
        ];

        // Two questions on one thread at the same moment computed the same sequence.
        // With the unique index now on (conversation_id, sequence) the loser retries
        // instead of quietly writing a second "turn 2" that made turn_count disagree
        // with the rows underneath it.
        for ($attempt = 0; $attempt < self::SEQUENCE_ATTEMPTS; $attempt++) {
            $sequence = $this->nextSequence($conversationId);

            try {
                $turnId = (int) DB::table('ai_conversation_turns')
                    ->insertGetId($row + ['sequence' => $sequence]);
            } catch (QueryException $exception) {
                if ($this->isDuplicate($exception) && $attempt < self::SEQUENCE_ATTEMPTS - 1) {
                    continue;
                }

                // Recording a turn is bookkeeping. It runs after the answer is fully
                // composed, and it used to run unguarded — so a failure here escaped to
                // the controller, which turned a complete, correct answer into a 500.
                // Losing the audit row is bad; throwing away the work that produced it
                // and telling the user their question failed is worse.
                $this->warn('AI conversation turn could not be recorded.', $exception, [
                    'conversation_id' => $conversationId,
                    'sequence' => $sequence,
                ]);

                return null;
            }

            // Guarded separately, and deliberately: the turn is already written, and a
            // failure to merge memory must not discard it. It does mean the next turn
            // opens with empty memory, which is exactly the symptom this log explains.
            try {
                $this->rememberOn(
                    $conversationId,
                    $scope,
                    $links + ['last_intent' => $intent->key],
                    $question,
                    $sequence
                );
            } catch (Throwable $exception) {
                $this->warn('AI conversation memory could not be merged.', $exception, [
                    'conversation_id' => $conversationId,
                    'sequence' => $sequence,
                    'consequence' => 'The next turn on this thread will start with empty memory.',
                ]);
            }

            return $turnId;
        }

        return null;
    }

    /**
     * The next sequence number on a thread.
     *
     * `max(sequence) + 1` rather than `turn_count + 1`, so a thread whose counter has
     * drifted still numbers its turns from the rows that actually exist.
     */
    private function nextSequence(int $conversationId): int
    {
        return ((int) DB::table('ai_conversation_turns')
            ->where('conversation_id', $conversationId)
            ->max('sequence')) + 1;
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
        if (! $this->hasConversations()) {
            return;
        }

        // Scoped by user as well as institute, matching `open()`. It read only
        // sub_institute_id, so two colleagues in one school had a window in which one
        // could write referents onto the other's thread — the read gate and the write
        // gate on the same row have to agree.
        $row = DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->where('user_id', $scope->userId)
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
            'memory' => $this->encode($memory),
            'turn_count' => $sequence > 0 ? $sequence : (int) $row->turn_count,
            'title' => $row->title ?: ($question ? mb_substr($question, 0, 200) : null),
            'last_turn_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Drop keys from a thread's memory.
     *
     * `rememberOn()` deliberately never lets a null overwrite a value, so a turn that
     * does not mention a case cannot erase the case the thread is about. That rule is
     * right for referents and wrong for tasks: without an explicit way to forget, a
     * half-finished admission would follow the user for the rest of the conversation
     * and there would be no way to cancel it. Hence a separate, obvious verb rather
     * than a magic value threaded through the merge.
     *
     * @param  array<int, string>  $keys
     */
    public function forgetOn(int $conversationId, McpRequestContext $scope, array $keys): void
    {
        if ($keys === [] || ! $this->hasConversations()) {
            return;
        }

        $row = DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->where('user_id', $scope->userId)
            ->first();

        if (! $row) {
            return;
        }

        $memory = $this->decode($row->memory);

        foreach ($keys as $key) {
            unset($memory[$key]);
        }

        DB::table('ai_conversations')->where('id', $conversationId)->update([
            'memory' => $this->encode($memory),
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

    /**
     * The next thread reference, skipping `$offset` places on a retry.
     *
     * Ordered by the reference itself rather than by id: the highest id whose reference
     * matches this year's prefix is not necessarily the highest reference, and taking
     * the wrong one produces a number already in use.
     */
    private function nextReference(int $offset = 0): string
    {
        $prefix = sprintf('CONV-%d-', now()->year);

        $last = DB::table('ai_conversations')
            ->where('conversation_reference', 'like', $prefix . '%')
            ->orderByDesc('conversation_reference')
            ->value('conversation_reference');

        $sequence = ($last ? (int) substr($last, strlen($prefix)) : 0) + 1 + $offset;

        return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * JSON that is always writable to a json column.
     *
     * `json_encode` returns false on malformed UTF-8, and this estate's student names
     * come from tables old enough to contain some. That false became an empty string in
     * the insert, MySQL rejected it as invalid JSON, and the exception took the answer
     * with it. Substituting the bad bytes keeps a slightly lossy audit row, which is
     * strictly better than none.
     */
    private function encode(mixed $value): string
    {
        $json = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return is_string($json) ? $json : '{}';
    }

    /** A unique-constraint violation, as opposed to any other database error. */
    private function isDuplicate(QueryException $exception): bool
    {
        return ($exception->errorInfo[1] ?? null) === 1062
            || $exception->getCode() === '23000';
    }

    /**
     * Report a storage failure without becoming a second failure.
     *
     * @param  array<string, mixed>  $context
     */
    private function warn(string $message, Throwable $exception, array $context = []): void
    {
        try {
            report($exception);

            Log::warning($message, $context + [
                'exception' => $exception->getMessage(),
                'class' => $exception::class,
            ]);
        } catch (Throwable) {
            // Deliberately swallowed. The caller's fallback matters more than the log.
        }
    }

    /**
     * Memoised table checks.
     *
     * `Schema::hasTable()` is a metadata query, and Stage 1 asked it four times per
     * turn. Memoising per instance keeps the answer fresh across requests — the store
     * is resolved from the container per request — while costing one query instead of
     * four.
     */
    private function hasConversations(): bool
    {
        return $this->hasConversations ??= Schema::hasTable('ai_conversations');
    }

    private function hasTurns(): bool
    {
        return $this->hasTurns ??= Schema::hasTable('ai_conversation_turns');
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
