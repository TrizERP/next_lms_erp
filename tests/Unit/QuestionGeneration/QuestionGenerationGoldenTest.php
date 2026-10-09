<?php

namespace Tests\Unit\QuestionGeneration;

use App\Services\QuestionGenerationService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Golden snapshots of the pre-format-seam MCQ and legacy-narrative generator.
 *
 * NO DATABASE, no network. phpunit.xml points at the live shared vivek_erp, so
 * every method touched here is a pure function of its arguments.
 *
 * WHY THIS EXISTS. The format-driven generator moves the MCQ and narrative
 * prompt text behind a QuestionFormat seam. The requirement is that those two
 * keep producing exactly the bytes they produced before, so the snapshots below
 * were captured from the untouched service and are compared byte-for-byte.
 *
 * To (re)capture deliberately: UPDATE_GOLDEN=1 vendor/bin/phpunit --filter Golden
 */
class QuestionGenerationGoldenTest extends TestCase
{
    private const DIR = __DIR__ . '/../../Fixtures/question_generation/golden/';

    private function service(): QuestionGenerationService
    {
        return new QuestionGenerationService();
    }

    private function invoke(string $method, array $args = [])
    {
        $m = new ReflectionMethod(QuestionGenerationService::class, $method);
        $m->setAccessible(true);

        return $m->invokeArgs($this->service(), $args);
    }

    private function assertGolden(string $name, string $actual): void
    {
        $path = self::DIR . $name;

        if (getenv('UPDATE_GOLDEN') === '1') {
            file_put_contents($path, $actual);
        }

        $this->assertFileExists($path, "Golden fixture {$name} is missing.");
        // Line endings are normalised on both sides: the service source is CRLF in
        // a Windows working tree and LF after a Linux checkout, and that is a
        // property of the checkout, not of the prompt.
        $this->assertSame(
            str_replace("\r\n", "\n", file_get_contents($path)),
            str_replace("\r\n", "\n", $actual),
            "{$name} drifted from the captured pre-seam output."
        );
    }

    private function encode($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function slice(): array
    {
        return [
            'concept' => 'Multiplication of integers with different signs',
            'concept_description' => 'Product of a positive and a negative integer.',
            'knowledge_items' => [['knowledge' => 'The product of a positive and a negative integer is negative.']],
            'abilities' => [['ability' => 'Multiply integers of different signs', 'verb' => 'Calculate']],
            'competency' => [],
            'dok' => [],
            'evidence' => [],
            'prerequisites' => [['prerequisite' => 'Multiplication of whole numbers']],
            'misconceptions' => [['misconception' => 'A negative times a positive is positive', 'correction' => 'The sign is negative.']],
            'real_world_applications' => [['application' => 'A diver descends 3 m every minute.']],
            'learning_objectives' => [],
            'learning_outcomes' => [['outcome' => 'Multiplies integers with unlike signs']],
        ];
    }

    private function mcqQuota(): array
    {
        return [
            ['level' => 'Remember', 'count' => 1, 'dok' => 1, 'difficulty' => 'Easy', 'points' => 1, 'sub_type' => 'MCQ'],
            ['level' => 'Apply', 'count' => 2, 'dok' => 2, 'difficulty' => 'Medium', 'points' => 1, 'sub_type' => 'MCQ'],
        ];
    }

    private function narrativeQuota(): array
    {
        return [
            ['level' => 'Remember', 'count' => 1, 'dok' => 1, 'difficulty' => 'Easy', 'points' => 1, 'sub_type' => 'Very Short Answer'],
            ['level' => 'Analyze', 'count' => 1, 'dok' => 3, 'difficulty' => 'Hard', 'points' => 4, 'sub_type' => 'Long Answer'],
        ];
    }

    public function test_system_prompt_is_byte_identical(): void
    {
        $this->assertGolden('system_prompt.txt', $this->invoke('systemPrompt'));
    }

    public function test_mcq_user_prompt_is_byte_identical(): void
    {
        $prompt = $this->invoke('userPrompt', ['mcq', $this->mcqQuota(), $this->slice(), ['Existing stem one', 'Existing stem two'], 'MULTIPLICATION_0123', true, null]);
        $this->assertGolden('user_prompt_mcq.txt', $prompt);
    }

    public function test_mcq_user_prompt_without_content_and_with_stage_is_byte_identical(): void
    {
        $prompt = $this->invoke('userPrompt', ['mcq', $this->mcqQuota(), $this->slice(), [], 'MULTIPLICATION_0123', false, 'concept_diagnostic']);
        $this->assertGolden('user_prompt_mcq_nocontent_stage.txt', $prompt);
    }

    public function test_narrative_user_prompt_is_byte_identical(): void
    {
        $prompt = $this->invoke('userPrompt', ['narrative', $this->narrativeQuota(), $this->slice(), ['Existing stem one'], 'MULTIPLICATION_0123', true, null]);
        $this->assertGolden('user_prompt_narrative.txt', $prompt);
    }

    public function test_schemas_are_byte_identical(): void
    {
        $this->assertGolden('schema_mcq.json', $this->invoke('mcqColumnSchema'));
        $this->assertGolden('schema_narrative.json', $this->invoke('narrativeColumnSchema'));
    }

    public function test_quota_building_is_unchanged(): void
    {
        $snapshot = [
            'mcq_default_10' => $this->invoke('buildQuota', ['mcq', 10, [], [], []]),
            'mcq_default_7' => $this->invoke('buildQuota', ['mcq', 7, [], [], []]),
            'narrative_default_10' => $this->invoke('buildQuota', ['narrative', 10, [], [], []]),
            'mcq_explicit' => $this->invoke('buildQuota', ['mcq', 5, ['quota' => [
                ['level' => 'Apply', 'count' => 3, 'difficulty' => 'Hard', 'points' => 9],
                ['level' => 'Nonsense', 'count' => 9],
                ['level' => 'Remember', 'count' => 2],
            ]], [], []]),
            'narrative_explicit' => $this->invoke('buildQuota', ['narrative', 5, ['quota' => [
                ['level' => 'Understand', 'count' => 2, 'points' => 3],
                ['level' => 'Create', 'count' => 3],
            ]], [], []]),
            'batches_mcq_25' => $this->invoke('splitQuotaIntoBatches', [$this->invoke('buildQuota', ['mcq', 25, [], [], []]), 10]),
            'batches_narrative_7' => $this->invoke('splitQuotaIntoBatches', [$this->invoke('buildQuota', ['narrative', 7, [], [], []]), 3]),
            'batch_size_mcq' => $this->invoke('questionBatchSize', ['mcq']),
            'batch_size_narrative' => $this->invoke('questionBatchSize', ['narrative']),
        ];

        $this->assertGolden('quota.json', $this->encode($snapshot));
    }

    private function validMcqRow(array $override = []): array
    {
        $row = [
            'question_title' => 'A diver descends 3 m every minute. What is the change in depth after 4 minutes?',
            'description' => 'Tests multiplication at Apply.',
            'subconcept' => 'The product of a positive and a negative integer is negative.',
            'points' => 1,
            'multiple_answer' => 0,
            'hint_text' => null,
            'learning_outcome' => ['Multiplies integers with unlike signs'],
            'answer' => [
                'v' => 'ans-2.0', 'question_type' => 'mcq', 'sub_type' => 'MCQ',
                'bloom_level' => 'Apply', 'dok_level' => 2, 'difficulty' => 'Medium',
                'options' => [
                    ['label' => 'A', 'text' => '+12 m', 'is_correct' => false],
                    ['label' => 'B', 'text' => '-12 m', 'is_correct' => true],
                    ['label' => 'C', 'text' => '-7 m', 'is_correct' => false],
                    ['label' => 'D', 'text' => '+7 m', 'is_correct' => false],
                ],
                'correct_option' => 'B',
                'knowledge_refs' => ['The product of a positive and a negative integer is negative.'],
            ],
        ];

        return array_replace_recursive($row, $override);
    }

    private function validNarrativeRow(): array
    {
        return [
            'question_title' => 'State the sign of the product of a positive and a negative integer.',
            'description' => 'Tests recall.',
            'subconcept' => 'Sign rule',
            'points' => 2,
            'multiple_answer' => 0,
            'hint_text' => null,
            'learning_outcome' => ['Multiplies integers with unlike signs'],
            'answer' => [
                'v' => 'ans-2.0', 'question_type' => 'narrative', 'sub_type' => 'Short Answer',
                'bloom_level' => 'Understand', 'dok_level' => 1, 'difficulty' => 'Easy',
                'model_answer' => 'The product is negative.',
                'marking_points' => [['mark' => 1, 'criterion' => 'negative'], ['mark' => 1, 'criterion' => 'unlike signs']],
                'keywords' => [['term' => 'a'], ['term' => 'b'], ['term' => 'c'], ['term' => 'd']],
            ],
        ];
    }

    public function test_row_validation_is_unchanged(): void
    {
        $noHint = $this->validMcqRow();
        unset($noHint['hint_text']);

        $threeOptions = $this->validMcqRow();
        array_pop($threeOptions['answer']['options']);

        $badRows = [
            'not_object' => 'x',
            'missing_key' => $noHint,
            'bad_bloom' => $this->validMcqRow(['answer' => ['bloom_level' => 'Nope']]),
            'bad_points' => $this->validMcqRow(['points' => 0]),
            'multiple_answer' => $this->validMcqRow(['multiple_answer' => 1]),
            'bad_subtype' => $this->validMcqRow(['answer' => ['sub_type' => 'Other']]),
            'three_options' => $threeOptions,
            'bad_correct' => $this->validMcqRow(['answer' => ['correct_option' => 'Z']]),
        ];

        $snapshot = [
            'mcq' => $this->invoke('validateRows', ['mcq', array_merge([$this->validMcqRow()], array_values($badRows)), []]),
            'narrative' => $this->invoke('validateRows', ['narrative', [$this->validNarrativeRow(), array_replace($this->validNarrativeRow(), ['points' => 3])], []]),
        ];

        $this->assertGolden('validation.json', $this->encode($snapshot));
    }

    public function test_flow_category_and_semantic_key_are_unchanged(): void
    {
        $mcq = $this->validMcqRow()['answer'];
        $misc = $mcq;
        $misc['options'] = array_map(fn ($o) => $o + ['distractor_type' => 'misconception'], $mcq['options']);

        $snapshot = [
            'mcq_apply' => $this->invoke('learningFlowCategory', [$mcq, []]),
            'mcq_misconception' => $this->invoke('learningFlowCategory', [$misc, []]),
            'case' => $this->invoke('learningFlowCategory', [['bloom_level' => 'Apply', 'sub_type' => 'Case Study'], []]),
            'stage' => $this->invoke('learningFlowCategory', [$mcq, [], 'adaptive_diagnostic']),
            'semantic_key' => $this->invoke('semanticConceptKey', [123, 'Multiplication of integers']),
        ];

        $this->assertGolden('flow_category.json', $this->encode($snapshot));
    }

    public function test_json_parsing_is_unchanged(): void
    {
        $snapshot = [
            'fenced' => $this->invoke('parseResponse', ["```json\n{\"a\":1,\"rows\":[]}\n```"]),
            'bare' => $this->invoke('parseResponse', ['{"a":1,"rows":[]}']),
            'prose' => $this->invoke('parseResponse', ['no json here']),
            'trailing_comma' => $this->invoke('parseResponse', ['{"a":1,}']),
        ];

        $this->assertGolden('parse.json', $this->encode($snapshot));
    }
}
