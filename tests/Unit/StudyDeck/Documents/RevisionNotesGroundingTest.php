<?php

namespace Tests\Unit\StudyDeck\Documents;

use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\Documents\Writers\RevisionNotesWriter;
use App\Services\StudyDeck\Documents\Writers\TopicRevisionWriter;
use PHPUnit\Framework\TestCase;

/** Revision notes: word limits and numbers are held to the chapter while the model can still be asked to fix them. */
class RevisionNotesGroundingTest extends TestCase
{
    private const CHAPTER = 'A model is a simplified representation of a real system. Water boils at 100 degrees Celsius.';

    /** @param array<string,mixed> $over */
    private function sheet(array $over = []): array
    {
        return $over + [
            'big_idea' => 'A model keeps only what a question needs.',
            'rows' => [1 => ['essential' => 'A model is a simplified representation of a real system.', 'terms' => ['model']]],
            'compare' => null, 'mixups' => [], 'recall' => [], 'checklist' => ['I can say what a model is.'], 'terms' => [],
        ];
    }

    /** @return array<string,mixed> */
    private function work(): array
    {
        return ['concepts' => [['concept_id' => 1, 'name' => 'Models', 'key_terms' => ['model'], 'misconceptions' => []]]];
    }

    private function problems(array $sheet): string
    {
        return implode(' ', TopicRevisionWriter::sheetProblems($sheet, $this->work(), self::CHAPTER));
    }

    public function test_a_number_the_chapter_never_gives_is_a_problem_in_every_kind_of_text(): void
    {
        $this->assertSame('', $this->problems($this->sheet()));

        foreach ([
            'row' => ['rows' => [1 => ['essential' => 'A model is a simplified copy of 180 parts.', 'terms' => []]]],
            'recall point' => ['recall' => ['A model can hold 180 details.']],
            'checklist' => ['checklist' => ['I can name 180 things.']],
            'table cell' => ['compare' => ['title' => 'Kinds', 'columns' => ['A', 'B'], 'rows' => [['one', 'costs 180']]]],
        ] as $where => $over) {
            $found = $this->problems($this->sheet($over));
            $this->assertStringContainsString('180', $found, "an invented number in a $where is reported");
            $this->assertStringContainsString('not in the chapter text', $found);
        }
    }

    public function test_a_number_the_chapter_does_give_is_not_a_problem(): void
    {
        $this->assertSame('', $this->problems($this->sheet(['recall' => ['Water boils at 100 degrees Celsius.']])));
    }

    public function test_a_recall_point_over_its_limit_names_its_word_count(): void
    {
        $long = implode(' ', array_fill(0, 30, 'word'));
        $found = $this->problems($this->sheet(['recall' => [$long]]));

        $this->assertStringContainsString('over 24 words', $found);
        $this->assertStringContainsString('30 words', $found);
    }

    public function test_the_compact_notes_writer_uses_the_same_grounding_rule(): void
    {
        $this->assertSame(['180'], RevisionNotesWriter::ungroundedNumbers(['summary' => 'It has 180 parts.', 'key_points' => ['A model is simplified.']], [self::CHAPTER]));
        $this->assertSame([], RevisionNotesWriter::ungroundedNumbers(['summary' => 'Water boils at 100 degrees Celsius.'], [self::CHAPTER]));
    }
}
