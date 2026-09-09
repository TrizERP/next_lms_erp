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

    private readonly ReferenceResolver $references;

    /**
     * The resolver is injected but defaulted, because this store is constructed directly
     * in tests and in a couple of console paths. It holds no state and reaches nothing,
     * so a default instance is the same object the container would have built.
     */
    public function __construct(?ReferenceResolver $references = null)
    {
        $this->references = $references ?? new ReferenceResolver();
    }

    /** Referents that survive between turns. */
    public const MEMORY_KEYS = [
        'student_id',
        'student_name',
        'case_id',
        'case_reference',
        /*
         * The admission enquiry this thread is about.
         *
         * `admission_confirm` declares `enquiry` as a required slot, and both the
         * admissions flow and a row selected off an enquiry list already put an
         * `enquiry_id` on the turn's links — but it was not kept here, so it was dropped
         * the moment the turn ended. The effect was the whole admissions conversation
         * ending one step short of its point: a user could list pending enquiries, open
         * the first candidate, and then say "confirm this admission" only to be told the
         * enquiry could not be read, because the sentence carried no id and memory no
         * longer held one.
         */
        'enquiry_id',
        'recommendation_id',
        'recommendation_reference',
        'workflow_run_id',
        'outcome_id',
        'agent_run_id',
        'last_intent',
        /*
         * The module this thread most recently ran in.
         *
         * Held in memory rather than read off ai_conversations.module_key, which is
         * written once when the thread opens and then never moves. A conversation that
         * starts on the dashboard and is routed to the student agent by its own first
         * question belongs to the student module from that point on, and an elliptical
         * follow-up has to inherit where the conversation actually went rather than
         * where it was opened.
         */
        'module',
        'last_case_list',
        /*
         * The list the previous answer showed, and the row this thread has open.
         *
         * `last_case_list` above is the same idea for exactly one screen — the ranked
         * academic-risk cases — and it is kept because the risk journey resolves through
         * it. These two are the general form: any list any tool returns can be selected
         * from by position or by the name that was printed, which is what makes "show the
         * details of the first candidate" a question rather than a dead end.
         *
         * Shapes: ResultSet::toArray(), and {item, set, singular, module}.
         */
        'last_result_set',
        'selected_record',
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
     * The module a readable thread is pinned to, if any.
     *
     * Follow-up turns are often too short to re-state their module in words:
     * "approve it", "what happened after approval?", "did it work?". The thread
     * already knows which module those referents came from, so exposing that hint lets
     * module resolution stay on the same governed path instead of reclassifying a
     * follow-up into General or some keyword-adjacent module.
     */
    public function moduleHint(?int $conversationId, McpRequestContext $scope): ?string
    {
        return $this->threadHint($conversationId, $scope)['module'];
    }

    /**
     * What the thread can tell module resolution — its module, and whether this
     * question is about the answer the thread has already given.
     *
     * The second fact is what stops a row selection changing modules mid-flow. "Show the
     * details of the first student" is a question about the previous answer, but the
     * bare word "student" scores for the student module at exactly the margin that
     * stands a context module down — so selecting a defaulter off a fees list routed the
     * turn to the student module, which binds a different detail tool and, because the
     * hand-off card is chosen by module, dropped the "Collect fees" button that was the
     * whole point of asking.
     *
     * A sentence that resolves against the rows this thread just printed is, by
     * construction, about those rows. The module that produced them keeps it.
     *
     * Both facts come from one row read, because they are read together on every turn.
     *
     * @return array{module:?string, points_at_previous_answer:bool}
     */
    public function threadHint(?int $conversationId, McpRequestContext $scope, string $question = ''): array
    {
        $none = ['module' => null, 'points_at_previous_answer' => false];

        if ($conversationId === null || ! $this->hasConversations()) {
            return $none;
        }

        $row = DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->where('user_id', $scope->userId)
            ->first();

        if (! $row) {
            return $none;
        }

        $memory = $this->decode($row->memory);

        $pointsBack = $question !== ''
            && (isset($memory['last_result_set']) || isset($memory['selected_record']))
            && $this->references->resolve($question, $memory) !== null;

        // Memory first, the column second.
        //
        // `module_key` is stamped when the thread opens and never moves, so a
        // conversation that began on one screen and was routed elsewhere by its own
        // first question would keep offering the opening screen as its module for every
        // turn after. Memory records where the conversation actually went, which is what
        // an elliptical follow-up needs to inherit.
        $remembered = $memory['module'] ?? null;

        if (is_string($remembered) && $remembered !== '' && $remembered !== 'general') {
            return ['module' => $remembered, 'points_at_previous_answer' => $pointsBack];
        }

        if (! is_string($row->module_key) || $row->module_key === '') {
            return ['module' => null, 'points_at_previous_answer' => $pointsBack];
        }

        return ['module' => $row->module_key, 'points_at_previous_answer' => $pointsBack];
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
     * @param  string|null  $question  The raw sentence, needed to resolve a reference to
     *                                 the previous answer — "the first candidate" cannot
     *                                 be read off slots, only off the words.
     * @return array{0:Intent, 1:array<string,mixed>}
     */
    public function resolveReferents(Intent $intent, array $memory, ?string $question = null): array
    {
        // A teacher can select a ranked student by typing the displayed name. This is
        // an exact match only, so similarly named students are never silently merged.
        if ($intent->isUnknown() && isset($memory['last_case_list']) && is_array($memory['last_case_list'])) {
            $selected = $this->selectedRankedCase($intent, $memory['last_case_list']);

            if ($selected !== null) {
                return [new Intent(
                    'student_risk_explain',
                    'Explain why one student is at risk',
                    1.0,
                    [
                        'student_id' => (int) ($selected['student_id'] ?? 0),
                        'student_name' => $selected['student_name'] ?? null,
                        'case_id' => (int) ($selected['case_id'] ?? 0),
                    ],
                    ['exact_ranked_student_name'],
                    ['selected_from_ranked_list' => $selected]
                ), ['selected_from_ranked_list' => $selected]];
            }
        }

        // Pointing at the previous answer, in the general case.
        //
        // This runs before the slot filling below because it can *replace* the intent
        // rather than enrich it: "show the details of the first candidate" classifies as
        // nothing at all, and read against the six enquiries the last turn listed it is
        // an unambiguous instruction. See selectionIntent() for what it refuses to
        // hijack — an intent with a route of its own and a referent it can resolve is
        // always left alone.
        if ($question !== null) {
            $selection = $this->selectionIntent($intent, $memory, $question);

            if ($selection !== null) {
                return $selection;
            }
        }

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

        // A bare student_name that exactly matches an entry in the ranked list the
        // previous turn showed should be resolved to that entry even when the intent
        // is already known. Without this, "Why is Ravi at risk?" classifies as
        // student_risk_explain and the bare name is passed through to the MCP search,
        // which may find multiple matches and block the turn.
        if (! $known('student_id') && $intent->slot('student_name') !== null && isset($memory['last_case_list']) && is_array($memory['last_case_list'])) {
            $needle = $this->normaliseName((string) $intent->slot('student_name'));
            foreach ($memory['last_case_list'] as $listed) {
                if ($needle !== '' && $needle === $this->normaliseName((string) ($listed['student_name'] ?? ''))) {
                    $slots['student_id'] = (int) ($listed['student_id'] ?? 0);
                    $slots['case_id'] = (int) ($listed['case_id'] ?? 0);
                    $slots['student_name'] = $listed['student_name'] ?? null;
                    $inherited['ranked_student_name_resolved_to'] = $listed;
                    break;
                }
            }
        }

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

                /*
                 * "Confirm this admission" names no enquiry, and it does not have to:
                 * the thread has been about one since the user picked it off a list.
                 *
                 * `admission_confirm` has always declared this slot as required, and the
                 * branch to satisfy it was simply missing — so the intent classified
                 * perfectly, arrived at the admissions flow with enquiry 0, and reported
                 * that the enquiry could not be read. A referent the conversation is
                 * plainly holding was treated as one the user had failed to supply.
                 */
                case 'enquiry':
                    if ($known('enquiry_id')) {
                        break;
                    }

                    if (isset($memory['enquiry_id'])) {
                        $slots['enquiry_id'] = (int) $memory['enquiry_id'];
                        $inherited['enquiry_id'] = $memory['enquiry_id'];

                        break;
                    }

                    /*
                     * "Confirm the admission for the first candidate" names its record
                     * by position rather than by id, and that is the ordinary way to say
                     * it straight after reading a list.
                     *
                     * This resolves the referent without touching the intent, which is
                     * the distinction that keeps it safe. selectionIntent() deliberately
                     * refuses to reinterpret consequential wording — a sentence about
                     * confirming an admission must never quietly become a request to
                     * filter a list — so `admission_confirm` survives intact and only
                     * gains the enquiry it was always missing.
                     */
                    $pointed = $this->references->resolve($question, $memory);
                    $enquiryId = $pointed['item']['row']['enquiry_id'] ?? null;

                    if ($pointed !== null && is_numeric($enquiryId) && (int) $enquiryId > 0) {
                        $slots['enquiry_id'] = (int) $enquiryId;
                        $inherited['enquiry_id_resolved_from_previous_answer'] = [
                            'matched_by' => $pointed['matched_by'] ?? null,
                            'position' => $pointed['position'] ?? null,
                            'title' => $pointed['item']['title'] ?? null,
                        ];
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
     * A selection or a filter over the previous answer, when the sentence is one.
     *
     * Two questions have to be answered before this may fire, and the second is the one
     * that keeps it safe.
     *
     * **Does the sentence resolve?** Only against rows the previous answer actually
     * printed. A thread with no remembered list resolves nothing, so an ordinal on turn
     * one is left to fail honestly rather than selecting from a list nobody has seen.
     *
     * **May it outrank what the classifier said?** Not always. An intent with a
     * deterministic route and a referent it can resolve is doing its job, and replacing
     * it would break the risk and approval journeys — "approve the recommendation" must
     * never become "filter the last list". So the hijack is allowed in exactly three
     * cases: nothing classified; the intent classified but this estate has no route for
     * it; or it classified and the referent it needs is nowhere to be found, which means
     * it was going to fail anyway. The third is what rescues "show enquiries with new
     * status", which scores as a workflow enquiry on the word "status" while the thread
     * holds no workflow at all.
     *
     * @param  array<string, mixed>  $memory
     * @return array{0:Intent, 1:array<string,mixed>}|null
     */
    private function selectionIntent(Intent $intent, array $memory, string $question): ?array
    {
        if (! isset($memory['last_result_set']) && ! isset($memory['selected_record'])) {
            return null;
        }

        $reference = $this->references->resolve($question, $memory);

        // How much the reference is allowed to overrule depends on how it was made.
        //
        // A position or a displayed name identifies a row and cannot mean anything else,
        // so it outranks any intent that is not a decision. "Show the details of the
        // first enquiry" classifies as the admissions *list* intent on the word
        // "enquiry" — which would re-list what the reader is already looking at, and is
        // exactly the dead end this whole path exists to remove.
        //
        // A bare pronoun is weaker. "Why is she at risk?" resolves against the previous
        // list *and* classifies as a risk explanation, and the classifier is right — so a
        // pronoun only wins under the conservative guard, where the intent had no route
        // or no referent to run with.
        if ($reference !== null
            && ! (($reference['explicit'] ?? false) ? $this->mayFilter($intent) : $this->mayOutrank($intent, $memory))) {
            $reference = null;
        }

        if ($reference !== null) {
            $item = $reference['item'];
            $set = $reference['set'];

            $slots = array_filter([
                'record_position' => $reference['position'],
                'record_id' => $item['id'] ?? null,
                'record_title' => $item['title'] ?? null,
                'record_id_field' => $set?->idField,
            ], static fn ($value) => $value !== null);

            // A row that names a student is also a student, so the rest of the estate can
            // keep using it. This is what lets "is she at academic risk?" work after the
            // candidate was selected off an admissions list.
            foreach (['student_id', 'enquiry_id', 'case_id'] as $field) {
                $value = $item['row'][$field] ?? null;

                if (is_numeric($value) && (int) $value > 0) {
                    $slots[$field] = (int) $value;
                }
            }

            if ($set?->idField !== null && isset($item['id']) && is_numeric($item['id'])) {
                $slots[$set->idField] = (int) $item['id'];
            }

            if (isset($item['row']['student_name']) && is_string($item['row']['student_name'])) {
                $slots['student_name'] = $item['row']['student_name'];
            }

            return [
                new Intent(
                    key: 'record_detail',
                    label: 'Show one record from the previous answer',
                    confidence: 1.0,
                    slots: $slots,
                    matched: ['resolved_by' => $reference['matched_by']],
                    resolvedFrom: ['selected_from_previous_answer' => $reference['matched_by']],
                ),
                [
                    'selected_from_previous_answer' => [
                        'matched_by' => $reference['matched_by'],
                        'position' => $reference['position'],
                        'title' => $item['title'] ?? null,
                    ],
                    'selected_record' => [
                        'item' => $item,
                        'set' => $set?->toArray() ?? null,
                        'singular' => $set?->singular ?? 'record',
                        'module' => $set?->module ?? null,
                    ],
                ],
            ];
        }

        // Filtering is held to a looser guard than selection, deliberately.
        //
        // A sentence that names a column of the answer on screen *and* a value that
        // answer holds is describing those rows, and there is not much else it could be
        // doing. "Show enquiries with new status" classifies as the admissions list
        // intent on the word "enquiries" — which would re-run the same query and return
        // the same unfiltered list, answering a narrower question with the broader one it
        // was asked to narrow. The filter is the better reading, and unlike a selection
        // it commits to nothing: it returns a subset of rows the user is already looking
        // at, so being wrong costs a re-phrase rather than a record.
        //
        // Consequential wording is still never reinterpreted — see mayOutrank().
        $filter = $this->mayFilter($intent)
            ? $this->references->resolveFilter($question, $memory)
            : null;

        if ($filter === null) {
            return null;
        }

        return [
            new Intent(
                key: 'record_filter',
                label: 'Narrow the previous answer',
                confidence: 1.0,
                slots: array_filter([
                    'filter_field' => $filter['field'],
                    'filter_value' => $filter['value'],
                    'filter_mode' => $filter['mode'],
                ], static fn ($value) => $value !== null),
                matched: ['resolved_by' => 'a field and value held by the rows already shown'],
                resolvedFrom: ['filtered_previous_answer' => $filter['field']],
            ),
            [
                'filtered_previous_answer' => [
                    'field' => $filter['field'],
                    'value' => $filter['value'],
                    'mode' => $filter['mode'],
                    'matched_rows' => count($filter['items']),
                    'items' => array_values(array_map(static fn ($item) => $item['row'] ?? $item, $filter['items'])),
                ],
            ],
        ];
    }

    /**
     * Intents that are never reinterpreted, whatever the thread is holding.
     *
     * The resolver would almost certainly decline these anyway, but "almost certainly" is
     * the wrong standard for wording that records an approval against a person's account
     * or creates a student on the roll.
     */
    private const NEVER_REINTERPRETED = [
        'approve_recommendation',
        'reject_recommendation',
        'admission_confirm',
    ];

    /** Whether a filter may replace what the classifier decided. See selectionIntent(). */
    private function mayFilter(Intent $intent): bool
    {
        return ! in_array($intent->key, self::NEVER_REINTERPRETED, true);
    }

    /**
     * Whether a selection may replace what the classifier decided. See selectionIntent().
     *
     * @param  array<string, mixed>  $memory
     */
    private function mayOutrank(Intent $intent, array $memory): bool
    {
        if ($intent->isUnknown()) {
            return true;
        }

        if (in_array($intent->key, self::NEVER_REINTERPRETED, true)) {
            return false;
        }

        // An intent this build has no steps for cannot answer anything, so there is
        // nothing to outrank.
        if (IntentClassifier::requiredSlots($intent->key) === [] && ! $this->hasRoute($intent->key)) {
            return true;
        }

        foreach (IntentClassifier::requiredSlots($intent->key) as $need) {
            $slot = match ($need) {
                'student' => 'student_id',
                'case' => 'case_id',
                'recommendation' => 'recommendation_id',
                'enquiry' => 'enquiry_id',
                default => null,
            };

            if ($slot === null) {
                continue;
            }

            // The referent this intent needs exists somewhere, so let it run.
            if ($intent->slot($slot) !== null || isset($memory[$slot])) {
                return false;
            }
        }

        // Every referent it needs is missing. It was going to fail; a resolvable
        // selection is a better reading of the same sentence.
        return IntentClassifier::requiredSlots($intent->key) !== [];
    }

    /**
     * Intents the deterministic planner knows how to route without a referent.
     *
     * Kept as a list rather than asked of the planner, because the store must not depend
     * on the lifecycle package — the legacy AskService uses it too.
     */
    private function hasRoute(string $intentKey): bool
    {
        return in_array($intentKey, [
            'student_risk_scan',
            'learning_effectiveness',
            'admission_enquiry_list',
            'admission_confirm',
            'record_detail',
            'record_filter',
        ], true);
    }

    /** @param array<int, array<string, mixed>> $cases */
    private function selectedRankedCase(Intent $intent, array $cases): ?array
    {
        $typed = $intent->slot('student_name');

        if (! is_string($typed) || trim($typed) === '') {
            return null;
        }

        $needle = $this->normaliseName($typed);

        foreach ($cases as $case) {
            if ($needle !== '' && $needle === $this->normaliseName((string) ($case['student_name'] ?? ''))) {
                return $case;
            }
        }

        return null;
    }

    private function normaliseName(string $name): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim($name))) ?? '';
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
