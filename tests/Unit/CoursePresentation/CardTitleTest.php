<?php

namespace Tests\Unit\CoursePresentation;

use App\Services\StudyDeck\CardTitle;
use PHPUnit\Framework\TestCase;

/**
 * NO DATABASE and no framework. The cases in tests/Fixtures/card-titles.json are real cards of chapter 8592 (Std 9 Science,
 * "Exploration"), read from its stored Semantic Intelligence; the same file's twin in lms_k12 checks the TypeScript port.
 */
class CardTitleTest extends TestCase
{
    /** @return array<int,array<string,mixed>> */
    private function cases(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/card-titles.json'), true);
    }

    public function test_numbered_placeholders_are_recognised_and_real_titles_with_numbers_are_not(): void
    {
        foreach (['Key idea 1', 'key idea 2', 'Key ideas 3', 'Key point 4', 'Point 1', 'Item 1', 'Card 1', 'Idea 2', 'Tab 3', 'Key idea #2', 'Item: 5', '3', '#4', 'Untitled', ''] as $label) {
            $this->assertTrue(CardTitle::isGeneric($label), "\"$label\" is a placeholder");
        }
        foreach (["Newton's 2nd law", 'Step 2 of the water cycle', 'Example 1.1: a cricket shot for a six', 'Point of view', 'Key idea', 'Card sorting by size', 'Using 2 rates to estimate', 'Vitamin B12', 'Estimating with an assumed rate'] as $label) {
            $this->assertFalse(CardTitle::isGeneric($label), "\"$label\" is a real heading");
        }
    }

    public function test_every_real_card_gets_the_recorded_title_and_never_a_numbered_one(): void
    {
        $byConcept = [];
        foreach ($this->cases() as $c) {
            $byConcept[$c['concept']][] = $c;
        }
        foreach ($byConcept as $concept => $cases) {
            $resolved = CardTitle::resolveAll(array_column($cases, 'card'), $concept);
            foreach ($cases as $i => $c) {
                $this->assertSame($c['title'], $resolved[$i]['title'], $concept);
                $this->assertSame($c['level'], $resolved[$i]['level']);
                $this->assertFalse(CardTitle::isGeneric($resolved[$i]['title']));
            }
            $titles = array_map(fn ($r) => mb_strtolower($r['title']), $resolved);
            $this->assertSame($titles, array_values(array_unique($titles)), "the cards of \"$concept\" have different headings");
        }
    }

    public function test_a_title_says_what_its_card_says(): void
    {
        $texts = [
            'Estimate using rates and assumed values' => [
                'An assumed rate is combined over a relevant time or number of persons to give an estimate.' => ['assumed rate', 'time'],
                'A rough estimate can be built from simple assumed rates and values.' => ['rough estimate', 'assumed rates'],
            ],
        ];
        foreach ($texts as $concept => $cards) {
            foreach ($cards as $text => $mustMention) {
                $title = CardTitle::resolve(['statement' => 'Some longer statement.', 'knowledge' => $text, 'type' => 'Process'], $concept)['title'];
                foreach ($mustMention as $word) {
                    $this->assertStringContainsStringIgnoringCase($word, $title);
                }
                $this->assertLessThanOrEqual(CardTitle::MAX_WORDS + 4, count(explode(' ', $title)));
            }
        }
    }

    public function test_the_sources_own_short_line_is_the_heading_when_it_is_already_one(): void
    {
        $r = CardTitle::resolve(['statement' => 'The compass is used here as a symbol of purposeful direction in exploration.', 'knowledge' => 'Compass represents direction', 'type' => 'Fact'], 'Purposeful direction');

        $this->assertSame(['title' => 'Compass represents direction', 'level' => 1], $r);
    }

    public function test_a_card_with_only_its_statement_gets_a_heading_compressed_from_it(): void
    {
        $r = CardTitle::resolve(['statement' => 'Atoms of one element are identical and differ from the atoms of every other element.']);

        $this->assertSame(2, $r['level']);
        $this->assertSame('Atoms of one element are identical', $r['title']);
    }

    public function test_when_nothing_can_be_compressed_the_concept_and_the_kind_of_knowledge_are_used(): void
    {
        $r = CardTitle::resolve(['statement' => 'Yes.', 'knowledge' => '', 'type' => 'Rule'], 'Scientific models');

        $this->assertSame(['title' => 'The rule: Scientific models', 'level' => 3], $r);
        $this->assertSame(['title' => 'More about Scientific models', 'level' => 4], CardTitle::resolve(['statement' => '...'], 'Scientific models'));
    }

    public function test_incomplete_content_is_handled_safely_and_never_invents_a_claim(): void
    {
        $empty = CardTitle::resolve([]);
        $this->assertSame(['title' => 'More about this idea', 'level' => 4], $empty);
        $this->assertFalse(CardTitle::isGeneric($empty['title']));
        $this->assertNull(CardTitle::compress(''));
        $this->assertNull(CardTitle::compress('  .  '));
        $this->assertNull(CardTitle::compress('It is.'), 'too short to be a heading');
        // Two cards that would get the same heading are told apart, never numbered.
        $two = CardTitle::resolveAll([['statement' => 'Atoms combine in fixed ratios.'], ['statement' => 'Atoms combine in fixed ratios.']], 'Atoms');
        $this->assertNotSame($two[0]['title'], $two[1]['title']);
        $this->assertSame(2, $two[0]['level'] === 2 ? 2 : 0);
        foreach ($two as $r) {
            $this->assertFalse(CardTitle::isGeneric($r['title']));
        }
    }

    public function test_headings_are_cut_at_phrase_boundaries_and_never_end_on_a_function_word(): void
    {
        foreach ($this->cases() as $c) {
            $last = mb_strtolower(preg_replace('/^.*\s/u', '', $c['title']));
            $this->assertNotContains($last, ['a', 'an', 'the', 'of', 'to', 'and', 'or', 'by', 'from', 'with', 'is', 'are', 'can', 'rather'], $c['title']);
            $this->assertDoesNotMatchRegularExpression('/[,;:.]$/u', $c['title']);
        }
        $this->assertSame('Quantities such as mass, velocity', CardTitle::compress('Quantities such as mass, velocity, force and electric current have conventional symbols.'));
    }
}
