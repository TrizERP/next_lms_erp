<?php

namespace Tests\Unit\StudyDeck\Documents;

use App\Services\StudyDeck\Documents\Writers\ActivityWriter;
use App\Services\StudyDeck\Documents\Writers\DocumentWriter;
use PHPUnit\Framework\TestCase;

/** A text a word or two over its limit, after the model's rewrites, is shortened here instead of failing the whole document. */
class WordLimitTrimTest extends TestCase
{
    public function test_a_teacher_step_one_word_over_is_shortened_to_the_limit_by_dropping_filler(): void
    {
        // "Bond detectives", teacher step 1: 13 words against a limit of 12.
        $over = 'Ask pairs to just sort each molecule cards by the bond type shown.';
        $this->assertSame(13, DocumentWriter::words($over));

        $out = DocumentWriter::shortenToLimit($over, 12);

        $this->assertSame(12, DocumentWriter::words($out));
        $this->assertStringNotContainsString('just', $out, 'filler goes first');
        $this->assertStringContainsString('sort each molecule cards', $out, 'the meaning is kept');
        $this->assertStringEndsWith('.', $out);
    }

    public function test_articles_go_when_there_is_no_other_filler(): void
    {
        $out = DocumentWriter::shortenToLimit('Name the compound from the formula that you were given today.', 10);

        $this->assertLessThanOrEqual(10, DocumentWriter::words($out));
        $this->assertStringStartsWith('Name', $out);
    }

    public function test_a_text_within_its_limit_or_far_over_it_is_left_alone(): void
    {
        $this->assertSame('Short enough here.', DocumentWriter::shortenToLimit('Short enough here.', 5));

        $far = implode(' ', array_fill(0, 20, 'word'));
        $this->assertSame($far, DocumentWriter::shortenToLimit($far, 12), 'over by 8: for the model, not for cutting');
    }

    public function test_the_text_never_ends_on_a_connector_after_cutting(): void
    {
        $out = DocumentWriter::shortenToLimit('Students compare the two molecules and then explain with', 7);

        $this->assertLessThanOrEqual(7, DocumentWriter::words($out));
        $this->assertDoesNotMatchRegularExpression('/\b(and|with|of|to|the)\.?$/i', $out);
    }

    public function test_every_limited_text_of_a_compact_activity_is_brought_within_its_limit(): void
    {
        $twelve = fn (int $n) => implode(' ', array_fill(0, $n, 'step'));
        $activity = [
            'objectives' => [['concept_id' => 1, 'text' => 'Students will name the ionic compound.']],
            'materials' => ['one two three four five six seven'],
            'teacher_steps' => [['minutes' => 3, 'text' => $twelve(13)], ['minutes' => 5, 'text' => $twelve(12)]],
            'student_steps' => [$twelve(14)],
            'expected_outcomes' => [$twelve(15)],
            'assessment' => [['criterion' => 'names the bond clearly now', 'evidence' => $twelve(13)]],
            'reflection' => [],
            'misconception' => null,
        ];

        $out = ActivityWriter::trimToLimits($activity);

        $this->assertLessThanOrEqual(ActivityWriter::COMPACT_MATERIAL_WORDS, DocumentWriter::words($out['materials'][0]));
        $this->assertLessThanOrEqual(ActivityWriter::COMPACT_STEP_WORDS, DocumentWriter::words($out['teacher_steps'][0]['text']));
        $this->assertSame($twelve(12), $out['teacher_steps'][1]['text'], 'a step already within its limit is untouched');
        $this->assertLessThanOrEqual(ActivityWriter::COMPACT_STEP_WORDS, DocumentWriter::words($out['student_steps'][0]));
        $this->assertLessThanOrEqual(ActivityWriter::COMPACT_OUTCOME_WORDS, DocumentWriter::words($out['expected_outcomes'][0]));
        $this->assertLessThanOrEqual(ActivityWriter::COMPACT_CRITERION_WORDS, DocumentWriter::words($out['assessment'][0]['criterion']));
        $this->assertLessThanOrEqual(ActivityWriter::COMPACT_EVIDENCE_WORDS, DocumentWriter::words($out['assessment'][0]['evidence']));
        $this->assertSame($activity['objectives'], $out['objectives']);
    }
}
