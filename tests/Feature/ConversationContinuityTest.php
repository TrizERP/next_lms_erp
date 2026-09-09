<?php

namespace Tests\Feature;

use App\Domain\AI\Conversation\ConversationStore;
use App\Domain\AI\Conversation\FollowUpComposer;
use App\Domain\AI\Conversation\IntentClassifier;
use App\Domain\AI\Conversation\ReferenceResolver;
use App\Domain\AI\Conversation\ResultSet;
use App\Domain\AI\Lifecycle\Modules\ModuleCapability;
use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\RecordDetail;
use App\Domain\AI\Lifecycle\Support\SelectionAnswerComposer;
use App\Services\Mcp\McpRequestContext;
use Tests\TestCase;

/**
 * The conversation has to survive its own first answer.
 *
 * The reported defect was small to describe and total in effect: "show pending admission
 * enquiries" returned six real rows, and "show the details of the first candidate" replied
 * that no registered intent matched and the module had no tools that could answer it
 * another way. Every list in the estate was a terminus. The rows were right, they were on
 * screen, and there was no sentence a user could type to open one of them.
 *
 * What follows asserts the three properties that fix requires, and the three guards that
 * stop the fix becoming a new way to act on the wrong record.
 *
 * Read-only throughout: every collaborator under test is pure, or reads configuration and
 * the module registry. Nothing here writes to the database.
 */
class ConversationContinuityTest extends TestCase
{
    /** Rows shaped like the ones admissions.listEnquiries actually returns. */
    private const ENQUIRIES = [
        ['enquiry_id' => 21, 'enquiry_no' => 'ENQ-101', 'student_name' => 'Ravi Sharma', 'mobile' => '98000001', 'standard_name' => '6', 'status' => 'new', 'followup_date' => '2026-09-20', 'father_name' => null],
        ['enquiry_id' => 22, 'enquiry_no' => 'ENQ-102', 'student_name' => 'Meera Patel', 'mobile' => '98000002', 'standard_name' => '6', 'status' => 'pending', 'followup_date' => null, 'father_name' => 'K Patel'],
        ['enquiry_id' => 23, 'enquiry_no' => 'ENQ-103', 'student_name' => 'Arjun Nair', 'mobile' => '98000003', 'standard_name' => '7', 'status' => 'new', 'followup_date' => '2026-09-25', 'father_name' => null],
    ];

    // ------------------------------------------------- remembering the answer

    public function test_a_list_is_remembered_by_position_identity_and_name(): void
    {
        $set = $this->set();

        // The identifying column is read off the rows rather than assumed. Enquiry rows
        // carry both enquiry_id and standard_id; picking the wrong one would offer to
        // open a class when the user asked for a candidate.
        $this->assertSame('enquiry_id', $set->idField);
        $this->assertSame('enquiry', $set->singular);
        $this->assertSame(21, $set->at(1)['id']);
        $this->assertSame('Arjun Nair', $set->last()['title']);
        $this->assertSame(22, $set->byTitle('meera patel')['id']);
    }

    public function test_a_set_survives_the_round_trip_through_conversation_memory(): void
    {
        // Memory is JSON in a column, so a set that cannot be rebuilt from its own array
        // form is a set that works in one turn and not the next — which is the whole
        // point of it existing.
        $rebuilt = ResultSet::fromArray($this->set()->toArray());

        $this->assertNotNull($rebuilt);
        $this->assertSame('enquiry_id', $rebuilt->idField);
        $this->assertSame('Ravi Sharma', $rebuilt->at(1)['title']);
    }

    public function test_the_result_set_is_a_memory_key_or_nothing_can_carry_it(): void
    {
        $this->assertContains('last_result_set', ConversationStore::MEMORY_KEYS);
        $this->assertContains('selected_record', ConversationStore::MEMORY_KEYS);
    }

    // ------------------------------------------------------------- pointing

    /**
     * @dataProvider waysOfPointing
     */
    public function test_a_sentence_selects_the_row_it_names(string $question, int $expectedId): void
    {
        $resolved = app(ReferenceResolver::class)->resolve($question, $this->memory());

        $this->assertNotNull($resolved, sprintf('"%s" should select a row.', $question));
        $this->assertSame($expectedId, $resolved['item']['id']);
        $this->assertTrue($resolved['explicit'], 'A position or a name identifies a row outright.');
    }

    /**
     * @return array<string, array{0:string, 1:int}>
     */
    public static function waysOfPointing(): array
    {
        return [
            'the reported case' => ['Show the details of the first candidate.', 21],
            'second' => ['Show the details of the second candidate.', 22],
            'numeric ordinal' => ['Show me the 3rd one', 23],
            'row number' => ['Open record 2', 22],
            'last' => ['Show the last candidate', 23],
            'the name that was printed' => ['Tell me about Meera Patel', 22],
            'the list own noun' => ['Show the first enquiry.', 21],
        ];
    }

    public function test_an_ordinal_that_is_not_about_the_list_selects_nothing(): void
    {
        // "The first term" is a position in something that is not the previous answer.
        // Resolving it to row one would answer a question nobody asked.
        $resolver = app(ReferenceResolver::class);

        $this->assertNull($resolver->resolve('What about the first term?', $this->memory()));
        $this->assertNull($resolver->resolve('Show the first instalment', $this->memory()));
    }

    public function test_nothing_resolves_when_no_answer_has_been_given_yet(): void
    {
        // An ordinal on the opening turn has no list behind it, and inventing one would
        // select from records the user has never seen.
        $this->assertNull(
            app(ReferenceResolver::class)->resolve('Show the details of the first candidate.', [])
        );
    }

    // --------------------------------------------------------------- routing

    public function test_selecting_a_row_outranks_the_list_intent_it_scores_as(): void
    {
        // The regression in its exact shape. "Show the details of the first enquiry"
        // scores as the admissions *list* intent on the word "enquiry", which would
        // re-list what the reader is already looking at.
        $question = 'Show the details of the first enquiry.';
        $classified = (new IntentClassifier())->classify($question, $this->memory());

        $this->assertSame('admission_enquiry_list', $classified->key, 'Precondition: it scores as the list.');

        [$resolved] = app(ConversationStore::class)->resolveReferents($classified, $this->memory(), $question);

        $this->assertSame('record_detail', $resolved->key);
        $this->assertSame(21, $resolved->slot('record_id'));
        $this->assertSame('enquiry_id', $resolved->slot('record_id_field'));
        $this->assertSame(21, $resolved->slot('enquiry_id'), 'The estate addresses the row by its own id.');
    }

    public function test_asking_for_the_list_itself_still_asks_for_the_list(): void
    {
        // The opposite guard. A question that names no row must not be turned into a
        // selection just because a list happens to be in memory.
        $question = 'Show pending admission enquiries';
        $classified = (new IntentClassifier())->classify($question, $this->memory());

        [$resolved] = app(ConversationStore::class)->resolveReferents($classified, $this->memory(), $question);

        $this->assertSame('admission_enquiry_list', $resolved->key);
    }

    public function test_a_pronoun_never_overrules_an_intent_that_can_run(): void
    {
        // "Why is she at risk?" resolves against the previous list *and* classifies as a
        // risk explanation, and the classifier is right. A pronoun is a guess; only a
        // position or a printed name is allowed to overrule a working route.
        $question = 'Why is she at risk?';
        $memory = $this->memory() + ['case_id' => 7, 'student_id' => 44];

        $classified = (new IntentClassifier())->classify($question, $memory);
        [$resolved] = app(ConversationStore::class)->resolveReferents($classified, $memory, $question);

        $this->assertSame('student_risk_explain', $resolved->key);
    }

    /**
     * @dataProvider consequentialWording
     */
    public function test_consequential_wording_is_never_reinterpreted(string $question): void
    {
        // Selecting a row is a read. Approving one is not, and wording that classified as
        // a decision must reach the governed path it was classified onto — whatever the
        // thread happens to be holding.
        $classified = (new IntentClassifier())->classify($question, $this->memory());

        $this->assertNotSame('unknown', $classified->key, 'Precondition: it classifies as a decision.');

        [$resolved] = app(ConversationStore::class)->resolveReferents($classified, $this->memory(), $question);

        $this->assertSame($classified->key, $resolved->key);
    }

    /**
     * @return array<int, array{0:string}>
     */
    public static function consequentialWording(): array
    {
        return [
            ['Approve the recommendation.'],
            ['Reject the first recommendation.'],
            ['Confirm the admission for enquiry 21'],
        ];
    }

    // --------------------------------------------------------------- filtering

    public function test_a_filter_narrows_the_rows_already_shown(): void
    {
        $resolver = app(ReferenceResolver::class);

        $present = $resolver->resolveFilter('Which candidates have a follow-up date?', $this->memory());

        $this->assertNotNull($present);
        $this->assertSame('followup_date', $present['field']);
        $this->assertSame('present', $present['mode']);
        $this->assertCount(2, $present['items'], 'Two of the three rows carry a follow-up date.');

        $equals = $resolver->resolveFilter('Show enquiries where status is new.', $this->memory());

        $this->assertNotNull($equals);
        $this->assertSame(['status', 'new'], [$equals['field'], $equals['value']]);
        $this->assertCount(2, $equals['items']);
    }

    public function test_a_single_character_value_still_filters(): void
    {
        // Grades and class names are one character, and a length floor to stop a stray
        // letter matching threw away every one of them. Values match on whole words
        // instead, so "D" finds the grade and not the "d" inside "standard".
        $set = ResultSet::fromRows('exam', 'exams.results', 'results', [
            ['student_id' => 301, 'student_name' => 'Sara Khan', 'grade' => 'D'],
            ['student_id' => 302, 'student_name' => 'Nina Das', 'grade' => 'C'],
        ], 'lowest results', 2);

        $filter = app(ReferenceResolver::class)->resolveFilter(
            'Show results where grade is D.',
            ['last_result_set' => $set->toArray()]
        );

        $this->assertNotNull($filter);
        $this->assertSame('D', strtoupper((string) $filter['value']));
        $this->assertCount(1, $filter['items']);
    }

    public function test_a_filter_nothing_matches_is_not_a_filter(): void
    {
        // Reporting "0 enquiries with status closed" from a list where "closed" never
        // appeared would answer a question the previous answer already refutes.
        $this->assertNull(
            app(ReferenceResolver::class)->resolveFilter('Show enquiries where status is closed.', $this->memory())
        );
    }

    // --------------------------------------------------------------- answering

    public function test_a_record_opens_from_memory_when_no_lookup_is_bound(): void
    {
        // The fields came out of the database on the previous turn and are no less real
        // for having been read then. Refusing here is what made selection impossible in
        // every module without a bespoke detail tool.
        $module = new ModuleCapability(key: 'admissions', label: 'Admissions', capabilities: ['conversational' => true]);
        $context = $this->context($module, 'Show the details of the first candidate.');

        [$context->intent] = app(ConversationStore::class)->resolveReferents(
            (new IntentClassifier())->classify($context->question, $this->memory()),
            $this->memory(),
            $context->question
        );

        $outcome = app(SelectionAnswerComposer::class)->detail($context);

        $this->assertStringContainsString('Ravi Sharma', (string) $context->headline());
        $this->assertSame('ran', $outcome->status->value);

        $titles = array_column($context->sections(), 'title');

        $this->assertContains('Enquiry details', $titles);
        // "What information is missing?" is answered before it is asked, because blank
        // columns are a property of the record rather than a separate question.
        $this->assertContains('Not recorded yet', $titles);
    }

    public function test_the_opened_record_becomes_what_the_thread_is_about(): void
    {
        $context = $this->context($this->admissions(), 'Show the details of the second candidate.');

        [$context->intent] = app(ConversationStore::class)->resolveReferents(
            (new IntentClassifier())->classify($context->question, $this->memory()),
            $this->memory(),
            $context->question
        );

        app(SelectionAnswerComposer::class)->detail($context);

        $this->assertSame(22, $context->links()['enquiry_id'] ?? null);
        $this->assertSame('Meera Patel', $context->get('selected_record')['item']['title']);
    }

    // ------------------------------------------------------------- follow-ups

    /**
     * The property that matters most: **the assistant only offers what it can honour.**
     *
     * A suggestion the next turn cannot resolve is worse than no suggestion — it teaches
     * a user that the chips are decoration. So every follow-up the composer produces is
     * fed back through classification and referent resolution, and must come out as
     * something the deterministic planner has a route for.
     *
     * Four modules with four differently shaped payloads, because the composer is meant
     * to be generic and a single fixture would not show that.
     *
     * @dataProvider modulePayloads
     */
    public function test_every_follow_up_it_offers_can_be_answered(
        string $moduleKey,
        string $tool,
        string $key,
        array $rows
    ): void {
        $module = app(ModuleRegistry::class)->find($moduleKey);

        if ($module === null) {
            $this->markTestSkipped(sprintf('The %s module is not configured in this estate.', $moduleKey));
        }

        $set = ResultSet::fromRows($moduleKey, $tool, $key, $rows, 'the opening question', count($rows));
        $context = $this->context($module, 'the opening question');
        $context->set('result_set', $set->toArray());

        $followUps = app(FollowUpComposer::class)->forTurn($context);
        $memory = ['last_result_set' => $set->toArray()];

        $this->assertNotEmpty($followUps, sprintf('A list of %s should offer somewhere to go next.', $key));

        foreach ($followUps as $followUp) {
            [$resolved] = app(ConversationStore::class)->resolveReferents(
                (new IntentClassifier())->classify($followUp, $memory),
                $memory,
                $followUp
            );

            $this->assertContains(
                $resolved->key,
                ['record_detail', 'record_filter'],
                sprintf('"%s" is offered but resolves to "%s", which cannot answer it.', $followUp, $resolved->key)
            );
        }
    }

    /**
     * @return array<string, array{0:string, 1:string, 2:string, 3:array<int, array<string, mixed>>}>
     */
    public static function modulePayloads(): array
    {
        return [
            'admissions' => ['admissions', 'admissions.listEnquiries', 'enquiries', self::ENQUIRIES],
            'fees' => ['fees', 'fees.getPending', 'students', [
                ['student_id' => 101, 'student_name' => 'Ravi Sharma', 'standard_name' => '6', 'pending_amount' => '4500', 'fee_type' => 'Tuition'],
                ['student_id' => 102, 'student_name' => 'Meera Patel', 'standard_name' => '7', 'pending_amount' => '1200', 'fee_type' => 'Tuition'],
                ['student_id' => 103, 'student_name' => 'Arjun Nair', 'standard_name' => '7', 'pending_amount' => '900', 'fee_type' => 'Transport'],
            ]],
            'attendance' => ['attendance', 'attendance.overview', 'students', [
                ['student_id' => 201, 'student_name' => 'Anita Roy', 'standard_name' => '8', 'attendance_percent' => '61', 'status' => 'low'],
                ['student_id' => 202, 'student_name' => 'Dev Menon', 'standard_name' => '8', 'attendance_percent' => '58', 'status' => 'low'],
            ]],
            'exams' => ['exam', 'exams.results', 'results', [
                ['student_id' => 301, 'student_name' => 'Sara Khan', 'subject_name' => 'Maths', 'grade' => 'D'],
                ['student_id' => 302, 'student_name' => 'Iqbal Shah', 'subject_name' => 'Maths', 'grade' => 'D'],
                ['student_id' => 303, 'student_name' => 'Nina Das', 'subject_name' => 'Science', 'grade' => 'C'],
            ]],
        ];
    }

    public function test_follow_ups_are_derived_from_the_rows_not_from_a_list_somebody_wrote(): void
    {
        // The same composer, two different payloads, two different sets of questions —
        // which is the difference between a dynamic recommendation and a static menu
        // wearing one.
        $admissions = $this->followUpsFor('admissions', 'admissions.listEnquiries', 'enquiries', self::ENQUIRIES);

        $exams = $this->followUpsFor('exam', 'exams.results', 'results', [
            ['student_id' => 301, 'student_name' => 'Sara Khan', 'subject_name' => 'Maths', 'grade' => 'D'],
            ['student_id' => 302, 'student_name' => 'Nina Das', 'subject_name' => 'Science', 'grade' => 'C'],
        ]);

        $this->assertNotEquals($admissions, $exams);
        $this->assertStringContainsString('enquiry', implode(' ', $admissions));
        $this->assertStringContainsString('result', implode(' ', $exams));
    }

    public function test_an_open_record_is_offered_the_questions_its_own_fields_support(): void
    {
        $context = $this->context($this->admissions(), 'Show the details of the first candidate.');
        $context->set('selected_record', [
            'item' => ['position' => 1, 'id' => 21, 'title' => 'Ravi Sharma', 'row' => self::ENQUIRIES[0]],
            'singular' => 'enquiry',
            'module' => 'admissions',
            'set' => $this->set()->toArray(),
        ]);
        $context->thread = ['id' => 1, 'memory' => $this->memory(), 'turn_count' => 2, 'reused' => true];

        $followUps = app(FollowUpComposer::class)->forTurn($context);

        // Offered because the row genuinely has a blank column, and genuinely has a
        // status. Neither sentence is compiled in against admissions.
        $this->assertContains('What information is missing?', $followUps);
        $this->assertContains('What is the status of this enquiry?', $followUps);
        $this->assertContains('Show the details of the second enquiry.', $followUps);
    }

    public function test_a_module_without_the_agent_is_never_sent_on_the_risk_journey(): void
    {
        // The existing rule, still holding through the new path: admissions binds no
        // agent, so nothing here may invite the reader into an academic-risk scan.
        $followUps = $this->followUpsFor('admissions', 'admissions.listEnquiries', 'enquiries', self::ENQUIRIES);

        foreach ($followUps as $followUp) {
            $this->assertStringNotContainsStringIgnoringCase('academic risk', $followUp);
        }
    }

    // ---------------------------------------------------------- record lookups

    public function test_a_module_names_the_tool_that_opens_its_records(): void
    {
        $registry = app(ModuleRegistry::class);
        $details = app(RecordDetail::class);

        $admissions = $registry->find('admissions');

        if ($admissions !== null) {
            $this->assertSame(
                'admissions.getEnquiryDetails',
                $details->toolFor($admissions, 'enquiry_id')
            );
        }

        $student = $registry->find('student');

        if ($student !== null) {
            // Six bound tools accept a student id and only one of them is "who is this
            // person", which is why the binding is configured rather than derived here.
            $this->assertSame('students.search', $details->toolFor($student, 'student_id'));
        }
    }

    public function test_a_lookup_is_never_a_way_round_a_module_binding(): void
    {
        $unbound = new ModuleCapability(
            key: 'admissions',
            label: 'Admissions',
            capabilities: ['conversational' => true],
            mcpTools: [],
        );

        $this->assertNull(app(RecordDetail::class)->toolFor($unbound, 'enquiry_id'));
        $this->assertNull(app(RecordDetail::class)->toolFor($this->admissions(), null));
    }

    // --------------------------------------------------------------- fixtures

    private function set(): ResultSet
    {
        return ResultSet::fromRows(
            'admissions',
            'admissions.listEnquiries',
            'enquiries',
            self::ENQUIRIES,
            'Show pending admission enquiries',
            3
        );
    }

    /** @return array<string, mixed> */
    private function memory(): array
    {
        return ['last_result_set' => $this->set()->toArray()];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function followUpsFor(string $moduleKey, string $tool, string $key, array $rows): array
    {
        $module = app(ModuleRegistry::class)->find($moduleKey)
            ?? new ModuleCapability(key: $moduleKey, label: ucfirst($moduleKey), capabilities: ['conversational' => true]);

        $set = ResultSet::fromRows($moduleKey, $tool, $key, $rows, 'the opening question', count($rows));
        $context = $this->context($module, 'the opening question');
        $context->set('result_set', $set->toArray());

        return app(FollowUpComposer::class)->forTurn($context);
    }

    private function admissions(): ModuleCapability
    {
        return app(ModuleRegistry::class)->find('admissions')
            ?? new ModuleCapability(key: 'admissions', label: 'Admissions', capabilities: ['conversational' => true]);
    }


    // --------------------------------------------- pointing at a list of many

    /**
     * A pronoun aimed at a list of several is a question, not a refusal.
     *
     * "Why was that student flagged?" after a four-student risk scan reached the
     * reasoning stage, found no single subject, and stopped with "the question did not
     * identify a student or a case" — while the thread was holding all four names. The
     * refusal was correct and the reply was useless: nothing on screen told the user
     * that naming one of them would work, so the conversation ended on turn two.
     *
     * Guessing is the one thing that must not happen here. Picking the first of four
     * would hang an explanation, then a recommendation, then an approval on a child
     * nobody chose — so the stage still halts. It just says what would settle it.
     */
    public function test_a_pronoun_over_many_rows_asks_which_rather_than_guessing(): void
    {
        $context = $this->context($this->admissions(), 'Why was that candidate flagged?');
        $context->intent = (new IntentClassifier())->classify($context->question, []);

        $outcome = app(\App\Domain\AI\Lifecycle\Stages\ReasoningStage::class)->run($context);

        $this->assertSame('blocked', $outcome->status->value, 'Selecting for the user is worse than asking.');
        $this->assertTrue($outcome->halts(), 'Nothing downstream may run against an unchosen record.');

        // Every row the previous answer printed is offered back, so the reply names its
        // own resolution rather than leaving the user to guess the phrasing.
        $this->assertStringContainsString('Which of the 3', (string) $context->headline());

        $offered = implode(' ', $context->followUps());

        foreach (['Ravi Sharma', 'Meera Patel', 'Arjun Nair'] as $name) {
            $this->assertStringContainsString($name, $offered, sprintf('%s was listed but not offered.', $name));
        }
    }

    public function test_every_candidate_it_offers_can_actually_be_resolved(): void
    {
        // The guarantee that makes the question above honest: each suggestion is one the
        // resolver can read straight back off the same remembered rows. An option that
        // does not resolve is a dead end wearing a button.
        $context = $this->context($this->admissions(), 'Why was that candidate flagged?');
        $context->intent = (new IntentClassifier())->classify($context->question, []);

        app(\App\Domain\AI\Lifecycle\Stages\ReasoningStage::class)->run($context);

        $resolver = app(ReferenceResolver::class);

        foreach ($context->followUps() as $followUp) {
            $resolved = $resolver->resolve($followUp, $this->memory());

            $this->assertNotNull($resolved, sprintf('"%s" was offered but resolves to nothing.', $followUp));
            $this->assertTrue($resolved['explicit'], sprintf('"%s" resolves only by guesswork.', $followUp));
        }
    }
    private function context(ModuleCapability $module, string $question): StageContext
    {
        $context = new StageContext(question: $question, scope: $this->scope(), module: $module, conversationId: 1);
        $context->thread = ['id' => 1, 'memory' => $this->memory(), 'turn_count' => 1, 'reused' => true];

        return $context;
    }

    private function scope(): McpRequestContext
    {
        return new McpRequestContext(
            userId: 1,
            role: 'admin',
            selectedInstituteId: 1,
            allowedInstituteIds: [1],
            userProfileId: null,
            clientId: null,
            academicYear: 2026,
            termId: null,
            isAdmin: true,
            isStudent: false,
        );
    }
}
