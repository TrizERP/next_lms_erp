<?php

namespace Tests\Unit\QuestionGeneration;

use App\Services\QuestionGeneration\Formats\AssertionReasonFormat;
use App\Services\QuestionGeneration\Formats\CaseStudyFormat;
use App\Services\QuestionGeneration\Formats\ConstructionFormat;
use App\Services\QuestionGeneration\Formats\DragTextFormat;
use App\Services\QuestionGeneration\Formats\FillBlankFormat;
use App\Services\QuestionGeneration\Formats\LongFormat;
use App\Services\QuestionGeneration\Formats\MarkTheWordsFormat;
use App\Services\QuestionGeneration\Formats\MatchFollowingFormat;
use App\Services\QuestionGeneration\Formats\NumericalFormat;
use App\Services\QuestionGeneration\Formats\ProofFormat;
use App\Services\QuestionGeneration\Formats\ShortFormat;
use App\Services\QuestionGeneration\Formats\TrueFalseFormat;
use App\Services\QuestionGeneration\Formats\VeryShortFormat;
use App\Services\QuestionGeneration\QuestionFormat;
use App\Services\QuestionGeneration\QuestionFormatRegistry;
use Tests\TestCase;
use Tests\Unit\QuestionGeneration\Support\FakeCatalogue;
use Tests\Unit\QuestionGeneration\Support\TestableGenerationService;

/**
 * The thirteen non-legacy format classes: schema, prompt, validation and row preparation.
 *
 * NO DATABASE, no network. Each format's own EXAMPLE ROW (the one it shows the
 * model) is the baseline valid row, so a rule tightened without updating the
 * example fails here at once; the negative cases then mutate it.
 */
class QuestionFormatsTest extends TestCase
{
    /** @return list<QuestionFormat> */
    private function formats(): array
    {
        return [
            new TrueFalseFormat(), new FillBlankFormat(), new MatchFollowingFormat(),
            new AssertionReasonFormat(), new NumericalFormat(), new VeryShortFormat(),
            new ShortFormat(), new LongFormat(), new CaseStudyFormat(),
            new DragTextFormat(), new MarkTheWordsFormat(), new ProofFormat(), new ConstructionFormat(),
        ];
    }

    private const MARKER = "EXAMPLE ROW (structure only; do not reuse its content)\n";

    /** The format's own example row, decoded. */
    private function example(QuestionFormat $format): array
    {
        $rules = $format->constructionRules(5);
        $at = strpos($rules, self::MARKER);
        $this->assertNotFalse($at, "{$format->code()} has no example row");

        $row = json_decode(trim(substr($rules, $at + strlen(self::MARKER))), true);
        $this->assertIsArray($row, "{$format->code()} example is not valid JSON: " . json_last_error_msg());

        return $row;
    }

    private function with(QuestionFormat $format, array $answer = [], array $row = []): array
    {
        $base = $this->example($format);
        foreach ($row as $key => $value) {
            $base[$key] = $value;
        }
        foreach ($answer as $key => $value) {
            $base['answer'][$key] = $value;
        }

        return $base;
    }

    private function reasons(QuestionFormat $format, array $row): array
    {
        return (new TestableGenerationService())->validate($format->engine(), [$row], $format)['skipped'];
    }

    private function assertValid(QuestionFormat $format, array $row, string $message = ''): void
    {
        $this->assertSame([], $this->reasons($format, $row), $message ?: "{$format->code()} row should be valid");
    }

    private function assertRejected(QuestionFormat $format, array $row, string $contains): void
    {
        $reasons = $this->reasons($format, $row);
        $this->assertNotSame([], $reasons, "{$format->code()} row should be rejected for: {$contains}");
        $this->assertStringContainsString($contains, $reasons[0]);
    }

    // ---- every format -----------------------------------------------------

    public function test_each_formats_example_row_is_valid_and_prepares_cleanly(): void
    {
        foreach ($this->formats() as $format) {
            $row = $this->example($format);
            $this->assertValid($format, $row, "{$format->code()} example row must pass its own validator");

            $prepared = $format->prepareRow($row);
            $this->assertSame($format->code(), $prepared['answer']['question_type']);
            $this->assertNotEmpty($prepared['answer']['sub_type'] ?? null, $format->code());
        }
    }

    public function test_each_format_has_a_well_formed_schema_that_pins_the_format_code(): void
    {
        foreach ($this->formats() as $format) {
            $schema = json_decode($format->responseSchema(), true);
            $this->assertIsArray($schema, "{$format->code()} schema is not valid JSON");

            $this->assertSame($format->code(), $schema['properties']['question_type']['const']);
            $answer = $schema['properties']['rows']['items']['properties']['answer'];
            $this->assertSame($format->code(), $answer['properties']['question_type']['const']);
            $this->assertFalse($schema['additionalProperties']);
            $this->assertFalse($answer['additionalProperties']);

            foreach ($answer['required'] as $key) {
                $this->assertArrayHasKey($key, $answer['properties'], "{$format->code()} requires an undeclared key {$key}");
            }
            foreach (['rows', 'question_type', 'semantic_concept_key'] as $key) {
                $this->assertContains($key, $schema['required']);
            }
        }
    }

    public function test_each_formats_example_row_conforms_to_its_own_schema_keys(): void
    {
        foreach ($this->formats() as $format) {
            $answerProps = json_decode($format->responseSchema(), true)['properties']['rows']['items']['properties']['answer']['properties'];
            $row = $this->example($format);

            foreach (array_keys($row['answer']) as $key) {
                $this->assertArrayHasKey($key, $answerProps, "{$format->code()} example uses {$key}, which its schema forbids");
            }
        }
    }

    public function test_construction_rules_are_complete_and_interpolated(): void
    {
        foreach ($this->formats() as $format) {
            $rules = $format->constructionRules(5);

            $this->assertStringNotContainsString('{$', $rules, "{$format->code()} left a PHP variable unresolved");
            $this->assertStringContainsString('points', $rules);
            $this->assertStringContainsString('answer.sub_type', $rules);
            $this->assertStringContainsString('answer.explanation', $rules, 'common rules missing');
            $this->assertStringContainsString($format->code(), $rules, 'the example names the format');
        }
    }

    public function test_formats_never_stray_into_each_others_territory_in_their_metadata(): void
    {
        $codes = array_map(fn (QuestionFormat $f) => $f->code(), $this->formats());
        $this->assertSame($codes, array_values(array_unique($codes)));

        foreach ($this->formats() as $format) {
            $this->assertFalse($format->isLegacy());
            $this->assertSame($format->code(), $format->persistedFormatCode());
            $this->assertSame($format->code(), $format->responseType());
            $this->assertTrue($format->scopesDedupByFormat());
            $this->assertNotEmpty($format->allowedBloomLevels());
            [$min, $max] = $format->marksRange();
            $this->assertLessThanOrEqual($max, $min);
            $this->assertGreaterThanOrEqual($min, $format->defaultMarks());
            $this->assertLessThanOrEqual($max, $format->defaultMarks());
            $this->assertGreaterThanOrEqual(1, $format->batchSize());
        }
    }

    public function test_the_registered_formats_are_exactly_the_fifteen_supported_codes(): void
    {
        $registry = new QuestionFormatRegistry(FakeCatalogue::live());

        $this->assertSame(
            ['mcq', 'true_false', 'fill_blank', 'drag_text', 'mark_the_words', 'match_following', 'assertion_reason', 'numerical', 'very_short', 'short', 'long', 'case_study', 'proof', 'construction', 'drag_drop'],
            array_column($registry->generatable(), 'code')
        );
        // Catalogued, but with no generator behind them: not offered.
        foreach (['flash_card', 'case_study_parent', 'case_study_child', 'competency_focused', 'source_based_integrated', 'antonym'] as $unsupported) {
            $this->assertNull($registry->get($unsupported), $unsupported);
        }
    }

    public function test_the_live_catalogue_gives_each_format_its_marks(): void
    {
        $registry = new QuestionFormatRegistry(FakeCatalogue::live());
        $marks = array_column($registry->generatable(), 'default_marks', 'code');

        $this->assertSame(
            ['mcq' => 1, 'true_false' => 1, 'fill_blank' => 1, 'drag_text' => 2, 'mark_the_words' => 2, 'match_following' => 1,
             'assertion_reason' => 1, 'numerical' => 2, 'very_short' => 2, 'short' => 3, 'long' => 5, 'case_study' => 4,
             'proof' => 5, 'construction' => 3, 'drag_drop' => 4],
            $marks,
            'case_study is NULL in the catalogue and falls back to 4'
        );
    }

    public function test_row_points_must_sit_inside_the_formats_marks_range(): void
    {
        $this->assertRejected(new VeryShortFormat(), $this->with(new VeryShortFormat(), [], ['points' => 3]), 'points must be between 1 and 2');
        $this->assertRejected(new TrueFalseFormat(), $this->with(new TrueFalseFormat(), [], ['points' => 2]), 'points must be between 1 and 1');
        $this->assertRejected(new LongFormat(), $this->with(new LongFormat(), [], ['points' => 2]), 'points must be between 4 and 6');
    }

    public function test_a_row_naming_another_format_or_a_disallowed_bloom_level_is_rejected(): void
    {
        $tf = new TrueFalseFormat();

        $this->assertRejected($tf, $this->with($tf, ['question_type' => 'fill_blank']), 'answer.question_type must be "true_false"');
        $this->assertRejected($tf, $this->with($tf, ['bloom_level' => 'Create']), 'not written at the Create level');
    }

    // ---- true_false -------------------------------------------------------

    public function test_true_false_rejects_questions_unclear_verdicts_and_missing_explanations(): void
    {
        $f = new TrueFalseFormat();

        $this->assertRejected($f, $this->with($f, [], ['question_title' => 'Is the product of 5 and -3 negative?']), 'statement, not a question');
        $this->assertRejected($f, $this->with($f, ['model_answer' => 'Maybe']), 'exactly "True" or "False"');
        $this->assertRejected($f, $this->with($f, ['model_answer' => 'True. Because unlike signs.']), 'exactly "True" or "False"');
        $this->assertRejected($f, $this->with($f, ['explanation' => 'Too short.']), 'explanation is required');
        $this->assertRejected($f, $this->with($f, ['knowledge_refs' => []]), 'knowledge_refs required');
    }

    public function test_true_false_stores_the_bare_word_and_two_options_with_one_flagged(): void
    {
        $f = new TrueFalseFormat();

        $false = $f->prepareRow($this->with($f, ['model_answer' => 'false']))['answer'];
        $this->assertSame('False', $false['model_answer']);
        $this->assertFalse($false['statement_truth']);
        $this->assertSame('B', $false['correct_option']);
        $this->assertSame(['True', 'False'], array_column($false['options'], 'text'));
        $this->assertSame([false, true], array_column($false['options'], 'is_correct'));

        $true = $f->prepareRow($this->with($f, ['model_answer' => 'TRUE']))['answer'];
        $this->assertSame('True', $true['model_answer']);
        $this->assertSame([true, false], array_column($true['options'], 'is_correct'));
        $this->assertSame('A', $true['correct_option']);
    }

    // ---- fill_blank -------------------------------------------------------

    public function test_fill_blank_requires_the_drawn_gaps_to_equal_the_answers(): void
    {
        $f = new FillBlankFormat();

        $this->assertRejected($f, $this->with($f, ['answers' => ['negative', 'zero']]), 'draws 1 gap(s) but there are 2 answer(s)');
        $this->assertRejected($f, $this->with($f, [], ['question_title' => 'The product of a positive and a negative integer is always negative.']), 'draws 0 gap(s)');
        // Three dots are a gap to the reader, so an ellipsis would silently add one.
        $this->assertRejected($f, $this->with($f, [], ['question_title' => 'The product of a positive and a negative integer ... is always ______.']), 'draws 2 gap(s)');
        $this->assertRejected($f, $this->with($f, [], ['question_title' => '______ is the sign of the product of unlike signs here.']), 'must not start with a gap');
    }

    public function test_fill_blank_mirrors_the_readers_answer_limits(): void
    {
        $f = new FillBlankFormat();

        $this->assertRejected($f, $this->with($f, ['answers' => ['a very long answer with seven whole words']]), 'too long to be a blank');
        $this->assertRejected($f, $this->with($f, ['answers' => [str_repeat('x', 61)]]), 'too long to be a blank');
        $this->assertRejected($f, $this->with($f, ['answers' => ['negative; zero']]), 'reserved character');
        $this->assertRejected($f, $this->with($f, ['answers' => ['neg|ative']]), 'reserved character');
        $this->assertRejected($f, $this->with($f, ['answers' => ['  ']]), 'an answer is empty');
        $this->assertRejected(
            $f,
            $this->with($f, ['answers' => ['negative']], ['question_title' => 'A negative result is what negative signs give, namely ______.']),
            'already appears in the sentence'
        );
    }

    public function test_fill_blank_joins_the_answers_into_the_key_the_reader_splits(): void
    {
        $f = new FillBlankFormat();
        $row = $this->with($f, ['answers' => [' referee ', 'judge'], 'accepted_alternatives' => [['umpire', ''], []]], [
            'question_title' => 'There are two officials: a ______ on the mat and a ______ at the table.',
        ]);

        $this->assertValid($f, $row);
        $prepared = $f->prepareRow($row)['answer'];

        $this->assertSame('referee; judge', $prepared['model_answer']);
        $this->assertSame(['referee', 'judge'], $prepared['answers']);
        $this->assertSame([['umpire'], []], $prepared['accepted_alternatives']);
    }

    // ---- assertion_reason -------------------------------------------------

    public function test_assertion_reason_derives_the_letter_from_the_facts(): void
    {
        $f = new AssertionReasonFormat();

        $this->assertSame('A', $f->impliedOption(true, true, true));
        $this->assertSame('B', $f->impliedOption(true, true, false));
        $this->assertSame('C', $f->impliedOption(true, false, false));
        $this->assertSame('D', $f->impliedOption(false, true, false));
        $this->assertNull($f->impliedOption(false, false, false));
    }

    public function test_assertion_reason_rejects_a_key_that_contradicts_its_own_facts(): void
    {
        $f = new AssertionReasonFormat();

        $this->assertRejected($f, $this->with($f, ['correct_option' => 'B']), 'contradicts the stated facts (they imply A)');
        $this->assertRejected($f, $this->with($f, ['assertion_true' => false, 'reason_true' => false]), 'both statements false');
        $this->assertRejected($f, $this->with($f, ['correct_option' => 'E']), 'invalid correct_option');
        $this->assertRejected($f, $this->with($f, ['reason_true' => 'yes']), 'reason_true must be true or false');
        $this->assertRejected($f, $this->with($f, ['reason' => 'The product of 5 and -3 is negative.']), 'repeats the assertion');
        $this->assertRejected($f, $this->with($f, ['assertion' => 'Short']), 'both required');
    }

    public function test_assertion_reason_attaches_the_four_standard_options_and_a_complete_stem(): void
    {
        $f = new AssertionReasonFormat();
        $row = $this->with($f, ['assertion_true' => true, 'reason_true' => false, 'reason_explains_assertion' => false, 'correct_option' => 'C']);
        $prepared = $f->prepareRow($row);
        $answer = $prepared['answer'];

        $this->assertSame(['A', 'B', 'C', 'D'], array_column($answer['options'], 'label'));
        $this->assertSame(array_values(AssertionReasonFormat::OPTIONS), array_column($answer['options'], 'text'));
        $this->assertSame([false, false, true, false], array_column($answer['options'], 'is_correct'));
        $this->assertSame("Assertion (A): {$answer['assertion']}\nReason (R): {$answer['reason']}", $prepared['question_title']);
        $this->assertSame('C', $answer['correct_option']);
    }

    // ---- numerical --------------------------------------------------------

    public function test_numerical_accepts_only_a_bare_number(): void
    {
        $f = new NumericalFormat();

        foreach (['-12', '3.5', '0.25', '3/4', '100'] as $ok) {
            $this->assertValid($f, $this->with($f, ['model_answer' => $ok]), $ok);
        }
        foreach (['12 m', 'twelve', '1,200', '-', '3.', '12.5.1', '= 12', '12%'] as $bad) {
            $this->assertRejected($f, $this->with($f, ['model_answer' => $bad]), 'number only');
        }
        $this->assertRejected($f, $this->with($f, ['model_answer' => '3/0']), 'divides by zero');
    }

    public function test_numerical_needs_workings_and_a_sane_unit(): void
    {
        $f = new NumericalFormat();

        $this->assertRejected($f, $this->with($f, ['solution_steps' => ['only one step']]), '2 to 6 steps');
        $this->assertRejected($f, $this->with($f, ['solution_steps' => ['one', '  ']]), 'a solution step is empty');
        $this->assertRejected($f, $this->with($f, ['unit' => str_repeat('m', 21)]), 'unit must be short');
        $this->assertRejected($f, $this->with($f, [], ['question_title' => 'Too short?']), 'too short');
    }

    public function test_numerical_prepares_a_clean_typed_answer(): void
    {
        $f = new NumericalFormat();
        $prepared = $f->prepareRow($this->with($f, ['model_answer' => ' -12 ', 'unit' => '  ']))['answer'];

        $this->assertSame('-12', $prepared['model_answer']);
        $this->assertNull($prepared['unit']);
        $this->assertSame('Numerical', $prepared['sub_type']);
    }

    // ---- very_short / short / long ----------------------------------------

    public function test_written_answer_formats_require_one_marking_point_per_mark(): void
    {
        foreach ([new VeryShortFormat(), new ShortFormat(), new LongFormat()] as $f) {
            $row = $this->example($f);
            $row['answer']['marking_points'] = array_slice($row['answer']['marking_points'], 0, max(0, count($row['answer']['marking_points']) - 1));

            $this->assertRejected($f, $row, 'marking_points count must equal points');
        }
    }

    public function test_written_answer_formats_reject_the_wrong_kind_of_answer(): void
    {
        $this->assertRejected(new VeryShortFormat(), $this->with(new VeryShortFormat(), ['model_answer' => str_repeat('word ', 60)]), 'a Very Short Answer answer runs 1-25');
        $this->assertRejected(new LongFormat(), $this->with(new LongFormat(), ['model_answer' => 'Too short.']), 'a Long Answer answer runs 80-220');
        $this->assertRejected(new ShortFormat(), $this->with(new ShortFormat(), ['model_answer' => '']), 'model_answer is required');
    }

    public function test_keywords_that_appear_in_the_question_do_not_count_and_are_dropped(): void
    {
        $f = new ShortFormat();
        $row = $this->example($f);
        // "diver" is in the question, so it cannot score; four others remain.
        $row['answer']['keywords'][] = ['term' => 'diver', 'weight' => 0.1, 'synonyms' => []];
        $this->assertValid($f, $row);

        $kept = array_column($f->prepareRow($row)['answer']['keywords'], 'term');
        $this->assertNotContains('diver', $kept);
        $this->assertCount(4, $kept);

        $row['answer']['keywords'] = [['term' => 'diver', 'weight' => 1, 'synonyms' => []]];
        $this->assertRejected($f, $row, 'need >= 4 keywords that do not appear in the question');
    }

    public function test_written_answer_formats_fix_the_sub_type_and_default_the_threshold(): void
    {
        foreach ([[new VeryShortFormat(), 'Very Short Answer'], [new ShortFormat(), 'Short Answer'], [new LongFormat(), 'Long Answer']] as [$f, $sub]) {
            $row = $this->example($f);
            unset($row['answer']['full_credit_threshold']);
            $prepared = $f->prepareRow($row)['answer'];

            $this->assertSame($sub, $prepared['sub_type']);
            $this->assertSame((int) ceil(0.75 * $row['points']), $prepared['full_credit_threshold']);
        }
    }

    // ---- match_following --------------------------------------------------

    public function test_match_following_enforces_a_clean_one_to_one_pairing(): void
    {
        $f = new MatchFollowingFormat();
        $pairs = $this->example($f)['answer']['pairs'];

        $this->assertRejected($f, $this->with($f, ['pairs' => array_slice($pairs, 0, 3)]), 'pairs must hold 4 to 6');
        $this->assertRejected($f, $this->with($f, ['pairs' => array_merge($pairs, $pairs, [['left' => 'x', 'right' => 'y']])]), 'pairs must hold 4 to 6');

        $dupLeft = $pairs; $dupLeft[1]['left'] = $pairs[0]['left'];
        $this->assertRejected($f, $this->with($f, ['pairs' => $dupLeft]), 'two lefts are the same');

        $dupRight = $pairs; $dupRight[1]['right'] = strtoupper($pairs[0]['right']);
        $this->assertRejected($f, $this->with($f, ['pairs' => $dupRight]), 'two rights are the same');

        $same = $pairs; $same[2]['right'] = $same[2]['left'];
        $this->assertRejected($f, $this->with($f, ['pairs' => $same]), 'matches a side with itself');

        $crossed = $pairs; $crossed[3]['right'] = $pairs[0]['left'];
        $this->assertRejected($f, $this->with($f, ['pairs' => $crossed]), 'a right is also a left');

        $empty = $pairs; $empty[0]['right'] = ' ';
        $this->assertRejected($f, $this->with($f, ['pairs' => $empty]), 'empty side');

        $reserved = $pairs; $reserved[0]['left'] = 'A; B';
        $this->assertRejected($f, $this->with($f, ['pairs' => $reserved]), 'reserved character');

        $wordy = $pairs; $wordy[0]['right'] = str_repeat('word ', 15);
        $this->assertRejected($f, $this->with($f, ['pairs' => $wordy]), 'too wordy');
    }

    public function test_match_following_instruction_must_not_list_the_items(): void
    {
        $f = new MatchFollowingFormat();

        $this->assertRejected($f, $this->with($f, [], ['question_title' => str_repeat('Match these items now. ', 8)]), 'must not list the items');
        $this->assertRejected($f, $this->with($f, [], ['question_title' => 'Match.']), 'too short');
    }

    public function test_match_following_keeps_pairs_verbatim_even_with_separator_characters(): void
    {
        $f = new MatchFollowingFormat();
        $pairs = [
            ['left' => 'x-axis', 'right' => 'Horizontal reference line'],
            ['left' => 'y-axis', 'right' => 'Vertical reference line'],
            ['left' => 'Origin (0, 0)', 'right' => 'Where the axes cross'],
            ['left' => 'Ratio a:b', 'right' => 'Comparison of two quantities'],
        ];
        $row = $this->with($f, ['pairs' => $pairs]);

        $this->assertValid($f, $row);
        $prepared = $f->prepareRow($row)['answer']['pairs'];

        // This is the whole reason pairs are structured: "x-axis" and "a:b" survive.
        $this->assertSame($pairs, $prepared);
    }

    public function test_match_following_title_key_and_shuffle_agree_with_the_pairs(): void
    {
        $f = new MatchFollowingFormat();
        $prepared = $f->prepareRow($this->example($f));
        $pairs = $prepared['answer']['pairs'];

        $this->assertMatchesRegularExpression('/^Match each term in Column A with its description in Column B\.\nColumn A: .+\nColumn B: .+$/s', $prepared['question_title']);

        [, $columnB] = explode("\nColumn B: ", $prepared['question_title']);
        $shown = array_map(fn ($cell) => preg_replace('/^\([ivx]+\) /', '', $cell), explode('; ', $columnB));

        $this->assertEqualsCanonicalizing(array_column($pairs, 'right'), $shown, 'column B holds every right side exactly once');
        $this->assertNotSame(array_column($pairs, 'right'), $shown, 'column B must not be in answer-key order');

        // Every "a-(ii)" entry of the key points at the right side of THAT pair.
        $roman = ['i' => 0, 'ii' => 1, 'iii' => 2, 'iv' => 3, 'v' => 4, 'vi' => 5];
        foreach (explode(', ', $prepared['answer']['model_answer']) as $i => $entry) {
            $this->assertMatchesRegularExpression('/^[a-f]-\([ivx]+\)$/', $entry);
            $this->assertSame(chr(ord('a') + $i), $entry[0]);
            $numeral = substr($entry, 3, -1);
            $this->assertSame($pairs[$i]['right'], $shown[$roman[$numeral]], "pair {$i}");
        }
    }

    public function test_match_following_shuffle_is_deterministic(): void
    {
        $f = new MatchFollowingFormat();

        $this->assertSame(
            $f->prepareRow($this->example($f))['question_title'],
            $f->prepareRow($this->example($f))['question_title']
        );
    }

    // ---- case_study -------------------------------------------------------

    public function test_case_study_validates_stimulus_parts_and_marks(): void
    {
        $f = new CaseStudyFormat();
        $parts = $this->example($f)['answer']['sub_parts'];

        $this->assertRejected($f, $this->with($f, ['stimulus' => 'Far too short a stimulus.']), 'stimulus is');
        $this->assertRejected($f, $this->with($f, ['stimulus' => str_repeat('word ', 150)]), 'stimulus is');
        $this->assertRejected($f, $this->with($f, ['sub_parts' => array_slice($parts, 0, 1)]), '2 or 3 parts');

        $badLabel = $parts; $badLabel[1]['label'] = 'c';
        $this->assertRejected($f, $this->with($f, ['sub_parts' => $badLabel]), 'labels must run a, b, c');

        $badMarks = $parts; $badMarks[0]['marks'] = 5;
        $this->assertRejected($f, $this->with($f, ['sub_parts' => $badMarks]), 'sub-part marks sum to 8 but points is 4');

        $zero = $parts; $zero[0]['marks'] = 0;
        $this->assertRejected($f, $this->with($f, ['sub_parts' => $zero]), 'whole marks of at least 1');

        $noAnswer = $parts; $noAnswer[2]['model_answer'] = ' ';
        $this->assertRejected($f, $this->with($f, ['sub_parts' => $noAnswer]), 'no model answer');

        $this->assertRejected($f, $this->with($f, ['marking_points' => [['mark' => 1, 'criterion' => 'only one']]]), 'marking_points count must equal points');
        $this->assertRejected($f, $this->with($f, [], ['question_title' => ' ']), 'heading is missing');
    }

    public function test_case_study_is_one_row_with_the_whole_case_in_the_stem(): void
    {
        $f = new CaseStudyFormat();
        $example = $this->example($f);
        $prepared = $f->prepareRow($example);
        $answer = $prepared['answer'];

        $this->assertStringStartsWith($answer['stimulus'], $prepared['question_title']);
        $this->assertStringContainsString("(a) Write the diver's change in position each minute as a signed integer. [1 mark]", $prepared['question_title']);
        $this->assertStringContainsString('(b) Calculate the diver\'s position after 4 minutes. [2 marks]', $prepared['question_title']);
        $this->assertSame(['a', 'b', 'c'], $answer['sub_part_labels']);
        $this->assertStringStartsWith("a) -3 m", $answer['model_answer']);
        $this->assertSame(3, substr_count($answer['model_answer'], "\n") + 1);
        $this->assertSame('Case Study', $answer['sub_type']);
        $this->assertArrayNotHasKey('parent_question_id', $answer);
    }

    public function test_case_study_marks_default_to_four_and_range_three_to_eight(): void
    {
        $f = new CaseStudyFormat();

        $this->assertSame(4, $f->defaultMarks());
        $this->assertSame([3, 8], $f->marksRange());
        $this->assertRejected($f, $this->with($f, [], ['points' => 2]), 'points must be between 3 and 8');
    }

    // ---- proof / construction ----------------------------------------------

    public function test_proof_and_construction_are_written_answers_with_their_own_marks_and_levels(): void
    {
        $proof = new ProofFormat();
        $construction = new ConstructionFormat();

        $this->assertSame([4, 6], $proof->marksRange());
        $this->assertSame(5, $proof->defaultMarks());
        $this->assertSame([2, 4], $construction->marksRange());
        $this->assertSame(3, $construction->defaultMarks());
        $this->assertSame(2, $proof->fallbackQuestionTypeId());
        $this->assertSame(2, $construction->fallbackQuestionTypeId());
        $this->assertNotContains('Remember', $proof->allowedBloomLevels());
        $this->assertNotContains('Remember', $construction->allowedBloomLevels());
    }

    public function test_proof_and_construction_hold_their_marks_and_marking_points_together(): void
    {
        foreach ([new ProofFormat(), new ConstructionFormat()] as $f) {
            $row = $this->example($f);
            $row['answer']['marking_points'] = array_slice($row['answer']['marking_points'], 0, -1);
            $this->assertRejected($f, $row, 'marking_points count must equal points');

            $this->assertRejected($f, $this->with($f, [], ['points' => 1]), 'points must be between');
            $this->assertRejected($f, $this->with($f, ['bloom_level' => 'Remember']), 'is not written at the Remember level');
            $this->assertRejected($f, $this->with($f, ['model_answer' => 'Too short.']), 'answer runs');
        }
    }

    public function test_proof_and_construction_store_their_own_sub_type(): void
    {
        $this->assertSame('Proof', (new ProofFormat())->prepareRow($this->example(new ProofFormat()))['answer']['sub_type']);
        $this->assertSame('Construction', (new ConstructionFormat())->prepareRow($this->example(new ConstructionFormat()))['answer']['sub_type']);
    }

    // ---- drag_text ---------------------------------------------------------

    public function test_drag_text_is_a_fill_in_the_blank_with_a_word_bank(): void
    {
        $f = new DragTextFormat();

        $this->assertInstanceOf(FillBlankFormat::class, $f);
        $this->assertSame(1, $f->fallbackQuestionTypeId(), 'the catalogue types it as MCQ');
        $this->assertSame([2, 2], $f->marksRange());
    }

    public function test_drag_text_inherits_the_gap_rules(): void
    {
        $f = new DragTextFormat();

        $this->assertRejected($f, $this->with($f, ['answers' => ['photosynthesis']]), 'draws 2 gap(s) but there are 1 answer(s)');
        $this->assertRejected($f, $this->with($f, ['answers' => ['photosynthesis']], ['question_title' => '______ is how plants make food from light and water.']), 'must not start with a gap');
        $this->assertRejected($f, $this->with($f, ['answers' => ['photosynthesis', 'carbon dioxide; oxygen']]), 'reserved character');
    }

    public function test_drag_text_answers_must_fit_a_draggable_chip(): void
    {
        $f = new DragTextFormat();

        $this->assertRejected($f, $this->with($f, ['answers' => ['photosynthesis', 'the gas carbon dioxide']]), 'too long to drag');
        $this->assertRejected($f, $this->with($f, ['answers' => ['photosynthesis', 'carbon, dioxide']]), 'comma');
    }

    public function test_drag_text_needs_a_clean_word_bank(): void
    {
        $f = new DragTextFormat();

        $this->assertRejected($f, $this->with($f, ['distractors' => []]), 'distractors must hold 1 to 4');
        $this->assertRejected($f, $this->with($f, ['distractors' => ['a', 'b', 'c', 'd', 'e']]), 'distractors must hold 1 to 4');
        $this->assertRejected($f, $this->with($f, ['distractors' => ['  ']]), 'a distractor is empty');
        $this->assertRejected($f, $this->with($f, ['distractors' => ['Carbon Dioxide']]), 'repeats an answer');
        $this->assertRejected($f, $this->with($f, ['distractors' => ['oxygen', 'Oxygen']]), 'two distractors are the same');
        $this->assertRejected($f, $this->with($f, ['distractors' => ['oxygen, nitrogen']]), 'reserved character');
        $this->assertRejected($f, $this->with($f, ['distractors' => ['the noble gas argon']]), 'too long to drag');
        $unset = $this->example($f);
        unset($unset['answer']['distractors']);
        $this->assertRejected($f, $unset, 'distractors must hold');
    }

    public function test_drag_text_stores_the_key_and_the_bank_without_typing_alternatives(): void
    {
        $f = new DragTextFormat();
        $row = $this->with($f, ['answers' => [' photosynthesis ', 'carbon dioxide'], 'distractors' => [' respiration ', 'oxygen'], 'accepted_alternatives' => [['x']]]);

        $this->assertValid($f, $row);
        $answer = $f->prepareRow($row)['answer'];

        $this->assertSame('photosynthesis; carbon dioxide', $answer['model_answer']);
        $this->assertSame(['respiration', 'oxygen'], $answer['distractors']);
        $this->assertSame('Drag the Words', $answer['sub_type']);
        $this->assertArrayNotHasKey('accepted_alternatives', $answer);
    }

    // ---- mark_the_words ----------------------------------------------------

    public function test_mark_the_words_is_a_type_one_format_with_fixed_marks(): void
    {
        $f = new MarkTheWordsFormat();

        $this->assertSame(1, $f->fallbackQuestionTypeId());
        $this->assertSame([2, 2], $f->marksRange());
    }

    public function test_mark_the_words_answers_must_be_whole_words_present_in_the_passage(): void
    {
        $f = new MarkTheWordsFormat();

        $this->assertRejected($f, $this->with($f, ['answers' => ['chlorophyll']]), 'does not appear in the passage as a whole word');
        // "oxyge" is a fragment of "oxygen", not a word of the passage.
        $this->assertRejected($f, $this->with($f, ['answers' => ['oxyge']]), 'does not appear in the passage as a whole word');
        $this->assertRejected($f, $this->with($f, ['answers' => ['carbon dioxide']]), 'single plain word');
        $this->assertRejected($f, $this->with($f, ['answers' => ['oxygen.']]), 'single plain word');
        $this->assertRejected($f, $this->with($f, ['answers' => ['oxygen', 'Oxygen']]), 'two answers are the same word');
        $this->assertRejected($f, $this->with($f, ['answers' => []]), 'answers must hold');
        $this->assertRejected($f, $this->with($f, ['answers' => ['oxygen', 'leaf', 'glucose', 'starch', 'sunlight', 'air']]), 'answers must hold');
    }

    public function test_mark_the_words_matches_case_insensitively_like_the_reader(): void
    {
        $f = new MarkTheWordsFormat();

        $this->assertValid($f, $this->with($f, ['answers' => ['OXYGEN']]));
        $this->assertValid($f, $this->with($f, ['answers' => ['Starch']]));
    }

    public function test_mark_the_words_instruction_must_not_give_the_answer_away(): void
    {
        $f = new MarkTheWordsFormat();

        $this->assertRejected($f, $this->with($f, ['instruction' => 'Mark the word oxygen wherever it appears.']), 'appears in the instruction');
        $this->assertRejected($f, $this->with($f, ['instruction' => 'Mark']), 'instruction is missing or the wrong length');
    }

    public function test_mark_the_words_passage_must_be_plain_prose_of_a_sensible_length(): void
    {
        $f = new MarkTheWordsFormat();

        $this->assertRejected($f, $this->with($f, ['passage' => 'Oxygen is a gas.']), 'passage is 4 words');
        $this->assertRejected($f, $this->with($f, ['passage' => str_repeat('oxygen ', 5) . str_repeat('word ', 90)]), 'passage is');
        $this->assertRejected($f, $this->with($f, ['passage' => "The leaf releases *oxygen* into the air during the day while it makes glucose from water and carbon dioxide."]), 'plain text');
        $this->assertRejected($f, $this->with($f, ['passage' => "The leaf releases oxygen into the air.\nIt also makes glucose from water and carbon dioxide each day."]), 'plain text');
    }

    public function test_mark_the_words_keeps_the_marked_share_and_repeats_in_check(): void
    {
        $f = new MarkTheWordsFormat();
        $passage = 'The leaf releases oxygen. The leaf makes glucose. The leaf stores starch. The leaf takes in carbon dioxide from the air around it daily.';

        // "leaf" occurs 4 times: more than the 3 a learner is asked to find.
        $this->assertRejected($f, $this->with($f, ['passage' => $passage, 'answers' => ['leaf']]), 'appears 4 times');

        // Marking half the words leaves nothing ordinary to choose between.
        $heavy = 'Oxygen gas oxygen gas oxygen gas leaf leaf leaf air air air water water water make make make use use use';
        $this->assertRejected($f, $this->with($f, ['instruction' => 'Mark the words that repeat most often.', 'passage' => $heavy, 'answers' => ['oxygen', 'gas', 'leaf', 'air', 'water']]), 'too much of the passage is marked');
    }

    public function test_mark_the_words_stem_is_instruction_then_passage_with_the_key_in_the_envelope(): void
    {
        $f = new MarkTheWordsFormat();
        $example = $this->example($f);
        $prepared = $f->prepareRow($example);
        $answer = $prepared['answer'];

        $this->assertSame($answer['instruction'] . "\n" . $answer['passage'], $prepared['question_title']);
        $this->assertSame('oxygen', $answer['model_answer']);
        $this->assertSame('Mark the Words', $answer['sub_type']);
        $this->assertSame(['oxygen'], $answer['answers']);
    }

    public function test_mark_the_words_joins_several_answers_for_the_reader(): void
    {
        $f = new MarkTheWordsFormat();
        $row = $this->with($f, ['answers' => [' oxygen ', 'glucose', 'starch']]);

        $this->assertValid($f, $row);
        $this->assertSame('oxygen; glucose; starch', $f->prepareRow($row)['answer']['model_answer']);
    }
}
