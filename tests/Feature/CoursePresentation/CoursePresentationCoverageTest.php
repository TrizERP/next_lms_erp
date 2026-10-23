<?php

namespace Tests\Feature\CoursePresentation;

use App\Services\CoursePresentation\Templates\ConceptCoverage;
use App\Services\CoursePresentation\Templates\SlideDeckAdapter;
use Illuminate\Support\Facades\DB;

/**
 * Complete chapter coverage: every concept of the chapter is accounted for, whatever the semantic intelligence holds.
 *
 * One synthetic chapter has every case on purpose:
 *   1 Dalton postulates        own intelligence, exact name, with its own definition      -> semantic + textbook
 *   2 Molecular mass definition intelligence by a CONTAINED name, no definition of its own -> both, combined
 *   3 Particles in matter      no intelligence of its own, but its TOPIC has some         -> textbook (topic context)
 *   4 Ionic lattice            no intelligence anywhere                                    -> textbook fallback
 *   5 Covalent sharing         no stored definition, but a chapter passage names it       -> textbook passage
 *   6 Quantum spin             no definition and nothing in the chapter text              -> UNRESOLVED, reported
 * and one intelligence entry ("Zinc smelting") that belongs to no concept or topic.
 */
class CoursePresentationCoverageTest extends CoursePresentationTestCase
{
    private const TEXT = <<<'T'
## Page 1

# Chapter 1 Exploration

Dalton postulates state that all matter is made up of very tiny particles called atoms, and that atoms are indivisible.

A model is a simplified representation of a real system, drawn to show only what matters for the question asked.

![Figure](http://localhost/api/assets/a.jpg)

## Page 2

Molecular mass is the total of the atomic masses of every atom in one molecule of a substance, expressed in atomic mass units.

All matter consists of extremely small particles that are always moving and that attract one another.

## Page 3

In a crystal the ions form a regular ionic lattice, held together by the attraction between oppositely charged ions.

Covalent sharing happens when two atoms each contribute electrons and share a pair, which holds the atoms together as a molecule.
T;

    private function topics(): array
    {
        return [['id' => 10, 'name' => 'Atoms and matter', 'description' => null], ['id' => 11, 'name' => 'Bonding', 'description' => 'How atoms hold together.']];
    }

    private function concepts(): array
    {
        $c = fn (int $id, string $name, ?string $def, int $topic) => ['id' => $id, 'name' => $name, 'description' => null, 'definition' => $def, 'topic_id' => $topic];

        return [
            $c(1, 'Dalton postulates', 'Matter is made of atoms.', 10),
            $c(2, 'Molecular mass definition', 'The total of the atomic masses of the atoms in a molecule.', 10),
            $c(3, 'Particles in matter', 'All matter is made of particles.', 10),
            $c(4, 'Ionic lattice', 'Ions form a regular lattice.', 11),
            $c(5, 'Covalent sharing', '', 11),
            $c(6, 'Quantum spin', null, 11),
        ];
    }

    private function semanticRow(?array $entries = null): array
    {
        $entries ??= [
            ['concept' => ['concept_id' => 'ANY-1', 'concept_name' => 'Dalton postulates', 'definition' => 'Atoms are indivisible particles that combine in fixed ratios.'],
                'knowledge_items' => [['statement' => 'Atoms of one element are identical.'], ['statement' => 'Atoms cannot be created or destroyed.']],
                'misconceptions' => [['misconception' => 'Atoms can be split in a reaction', 'correction' => 'Atoms are rearranged, not split.']],
                'real_world_applications' => [['example' => 'Balancing a chemical equation.']]],
            ['concept' => ['concept_id' => 'ANY-2', 'concept_name' => 'Molecular Mass'], 'knowledge_items' => [['statement' => 'Molecular mass is expressed in u.']]],
            ['concept' => ['concept_id' => 'ANY-3', 'concept_name' => 'Atoms and matter'],
                'misconceptions' => [['misconception' => 'Matter is continuous', 'correction' => 'Matter is made of particles.']],
                'real_world_applications' => [['example' => 'Dissolving sugar in tea.']]],
            ['concept' => ['concept_id' => 'ANY-4', 'concept_name' => 'Zinc smelting'], 'knowledge_items' => [['statement' => 'Zinc is smelted from ore.']]],
        ];

        return ['full_intelegance_json' => json_encode(['concepts' => $entries])];
    }

    private function coverage(?array $entries = null): array
    {
        return (new ConceptCoverage())->build($this->topics(), $this->concepts(), $this->semanticRow($entries), self::TEXT);
    }

    // ------------------------------------------------------------------ the coverage map

    public function test_a_concept_with_its_own_intelligence_uses_it_and_the_textbook_stands_beside_it(): void
    {
        $c = $this->coverage()['concepts'][1];

        $this->assertSame(['concept', 'exact'], [$c['semantic']['level'], $c['semantic']['match']]);
        $this->assertSame('Atoms are indivisible particles that combine in fixed ratios.', $c['semantic']['definition']);
        $this->assertContains('misconceptions', $c['semantic']['fields_with_data']);
        $this->assertSame('semantic_and_textbook', $c['explanation_source']);
        $this->assertNotEmpty($c['textbook']['passages'], 'the chapter passage that names it is found too');
        $this->assertSame(1, $c['textbook']['passages'][0]['page']);
    }

    public function test_a_concept_without_intelligence_is_explained_from_the_textbook(): void
    {
        $c = $this->coverage()['concepts'][4];

        $this->assertSame(['none', null], [$c['semantic']['level'], $c['semantic']['match']]);
        $this->assertSame('textbook', $c['explanation_source']);
        $this->assertTrue($c['sufficient']);
        $this->assertSame('Ions form a regular lattice.', $c['textbook']['definition']);
        $this->assertStringContainsString('ionic lattice', mb_strtolower($c['textbook']['passages'][0]['text']));
        $this->assertSame(3, $c['textbook']['passages'][0]['page']);
        $this->assertGreaterThanOrEqual(0.6, $c['textbook']['definition_grounded']);
    }

    public function test_partial_intelligence_is_combined_with_the_textbook(): void
    {
        $c = $this->coverage()['concepts'][2];

        // "Molecular Mass" is contained in "Molecular mass definition": the entry has a statement but no definition.
        $this->assertSame(['concept', 'contained'], [$c['semantic']['level'], $c['semantic']['match']]);
        $this->assertSame('', $c['semantic']['definition']);
        $this->assertSame(['Molecular mass is expressed in u.'], $c['semantic']['knowledge']);
        $this->assertSame('semantic_and_textbook', $c['explanation_source']);
        $this->assertNotEmpty($c['textbook']['passages']);
    }

    public function test_an_intelligence_entry_that_names_only_the_topic_is_topic_context_not_the_concepts_own(): void
    {
        $cov = $this->coverage();
        $c = $cov['concepts'][3];

        $this->assertSame(['topic', 'topic'], [$c['semantic']['level'], $c['semantic']['match']]);
        $this->assertArrayNotHasKey('misconceptions', $c['semantic'], 'topic-level data is not attributed to the concept');
        $this->assertSame('textbook', $c['explanation_source']);
        // It is kept at topic level, where it is true.
        $this->assertSame([['wrong_idea' => 'Matter is continuous', 'correction' => 'Matter is made of particles.']], $cov['topics'][10]['semantic']['misconceptions']);
        $this->assertSame(['Dissolving sugar in tea.'], $cov['topics'][10]['semantic']['real_world']);
        $this->assertSame([], $cov['topics'][11]['semantic']['misconceptions']);
    }

    public function test_a_concept_with_no_stored_definition_is_explained_from_the_chapter_passage_that_names_it(): void
    {
        $c = $this->coverage()['concepts'][5];

        $this->assertSame('', $c['textbook']['definition']);
        $this->assertSame('textbook', $c['explanation_source']);
        $this->assertTrue($c['sufficient']);
        $this->assertStringContainsString('Covalent sharing happens when two atoms', $c['textbook']['passages'][0]['text']);
    }

    public function test_a_concept_that_no_source_supports_is_reported_unresolved_not_dropped(): void
    {
        $cov = $this->coverage();
        $c = $cov['concepts'][6];

        $this->assertSame('unresolved', $c['explanation_source']);
        $this->assertFalse($c['sufficient']);
        $this->assertStringContainsString('no usable stored definition and no passage', $c['unresolved_reason']);
        $this->assertArrayHasKey(6, $cov['concepts'], 'it is still in the map');
    }

    public function test_every_chapter_concept_is_in_the_map_and_the_counts_add_up(): void
    {
        $cov = $this->coverage();
        $s = $cov['summary'];

        $this->assertSame([1, 2, 3, 4, 5, 6], array_keys($cov['concepts']));
        $this->assertSame(6, $s['total_concepts']);
        $this->assertSame(2, $s['semantic_concept_level']);          // 1 exact, 2 contained
        $this->assertSame([1, 1], [$s['semantic_exact'], $s['semantic_contained']]);
        $this->assertSame(1, $s['semantic_topic_level_only']);        // concept 3
        $this->assertSame(3, $s['no_semantic']);                      // 4, 5, 6
        $this->assertSame(5, $s['textbook_supported']);
        $this->assertSame(2, $s['both_sources']);
        $this->assertSame(3, $s['textbook_fallback_only']);           // 3, 4, 5
        $this->assertSame(0, $s['semantic_only']);
        $this->assertSame(1, $s['unresolved']);
        $this->assertSame(5, $s['renderable']);
        $this->assertSame($s['total_concepts'], $s['semantic_concept_level'] + $s['semantic_topic_level_only'] + $s['no_semantic'], 'every concept is in exactly one semantic tier');
        $this->assertSame($s['total_concepts'], $s['both_sources'] + $s['semantic_only'] + $s['textbook_fallback_only'] + $s['unresolved'], 'and in exactly one source group');
        $this->assertSame(['Zinc smelting'], $cov['unmapped_entries'], 'an entry that belongs to nothing is reported');
        $this->assertSame([4, 4], [$s['intelligence_entries'], $s['intelligence_entries_mapped'] + 1]);
    }

    public function test_the_link_to_intelligence_is_by_name_never_by_the_entrys_own_id(): void
    {
        // Entry ids are strings that match nothing in lms_concept; an entry carrying a concept's NUMERIC id must still not attach to it.
        $cov = $this->coverage([
            ['concept' => ['concept_id' => '4', 'concept_name' => 'Unrelated idea'], 'knowledge_items' => [['statement' => 'x']]],
            ['concept' => ['concept_id' => 4, 'concept_name' => 'Mass'], 'knowledge_items' => [['statement' => 'one word is not enough to match']]],
        ]);

        $this->assertSame('none', $cov['concepts'][4]['semantic']['level']);
        $this->assertSame('none', $cov['concepts'][2]['semantic']['level'], 'a single shared word ("Mass") does not match "Molecular mass definition"');
        $this->assertSame(0, $cov['summary']['semantic_concept_level']);
        // Still every concept except the unresolved one is renderable, from the textbook alone.
        $this->assertSame(5, $cov['summary']['renderable']);
    }

    public function test_with_no_intelligence_at_all_the_textbook_still_covers_the_chapter(): void
    {
        $cov = (new ConceptCoverage())->build($this->topics(), $this->concepts(), null, self::TEXT);

        $this->assertSame(0, $cov['summary']['semantic_concept_level']);
        $this->assertSame(0, $cov['summary']['intelligence_entries']);
        $this->assertSame(5, $cov['summary']['textbook_fallback_only']);
        $this->assertSame(5, $cov['summary']['renderable']);
    }

    public function test_passages_are_the_chapters_own_words_without_pictures_and_with_their_page(): void
    {
        $passages = (new ConceptCoverage())->passages(self::TEXT);

        $this->assertNotEmpty($passages);
        foreach ($passages as $p) {
            $this->assertStringNotContainsString('![', $p['text']);
            $this->assertStringNotContainsString('http://localhost', $p['text']);
            $this->assertStringNotContainsString('## Page', $p['text']);
            $this->assertGreaterThanOrEqual(50, mb_strlen($p['text']));
        }
        $this->assertSame([1, 1, 2, 2, 3, 3], array_column($passages, 'page'));
    }

    // ------------------------------------------------------------------ the slides built from it

    /** @return array{0:array<string,mixed>,1:array<int,array{what:string,where:string,why:string}>,2:array<string,mixed>} */
    private function build(string $method, array $pictures = [], ?array $entries = null): array
    {
        $chapter = ['id' => 8592, 'chapter_name' => 'Exploration: Entering the World of Secondary Science', 'standard_id' => 42, 'subject_id' => 3975, 'standard_name' => '9', 'subject_name' => 'Science'];
        $r = (new SlideDeckAdapter())->build($chapter, $this->coverage($entries), [], $method, $pictures);

        return [$r['deck'], $r['missing'], $r['summary']];
    }

    private function explainSlide(array $deck, int $conceptId): ?array
    {
        foreach ($deck['slides'] as $s) {
            if ($s['taught_concept_ids'] === [$conceptId]) {
                return $s;
            }
        }

        return null;
    }

    public function test_every_resolvable_concept_gets_its_own_slide_whatever_the_intelligence_holds(): void
    {
        [$deck, $missing, $summary] = $this->build('explicit_instruction');

        foreach ([1, 2, 3, 4, 5] as $id) {
            $this->assertNotNull($this->explainSlide($deck, $id), "concept $id has a slide");
        }
        $this->assertNull($this->explainSlide($deck, 6));
        $this->assertSame(5, $summary['concepts_explained']);
        $this->assertSame(6, $summary['total_concepts']);
        // The one that could not be mapped is said so, by name, with the reason: never a silent gap.
        $gap = array_values(array_filter($missing, fn ($m) => $m['what'] === 'Explanation (unresolved source mapping)'));
        $this->assertCount(1, $gap);
        $this->assertSame('Quantum spin', $gap[0]['where']);
        $this->assertArrayHasKey('6', (array) $deck['concepts'], 'it stays in the deck\'s concept list');
    }

    public function test_each_slide_says_which_source_it_came_from_and_uses_that_sources_words(): void
    {
        [$deck] = $this->build('explicit_instruction');

        $one = $this->explainSlide($deck, 1);   // own intelligence: its definition and statements; the textbook is never quoted or named
        $this->assertSame('Atoms are indivisible particles that combine in fixed ratios.', $one['content']['explanations'][0]['text']);
        $this->assertSame('Source: semantic intelligence.', $one['content']['body']);
        $this->assertSame('Atoms of one element are identical.', $one['content']['bullets'][0]);
        $this->assertStringNotContainsStringIgnoringCase('textbook', json_encode($deck), 'no slide quotes or names the textbook');

        $two = $this->explainSlide($deck, 2);   // combined: the stored definition + the semantic statement + a textbook passage
        $this->assertSame('The total of the atomic masses of the atoms in a molecule.', $two['content']['explanations'][0]['text']);
        $this->assertSame('Molecular mass is expressed in u.', $two['content']['bullets'][0]);

        $four = $this->explainSlide($deck, 4);  // no intelligence: the textbook alone
        $this->assertSame('Ions form a regular lattice.', $four['content']['explanations'][0]['text']);
        $this->assertSame('Source: the chapter\'s stored concept record.', $four['content']['body']);
        $this->assertSame([], $four['content']['bullets'], 'no textbook passage is quoted beside it');

        $five = $this->explainSlide($deck, 5);  // no stored definition: the passage is the explanation
        $this->assertStringContainsString('Covalent sharing happens when two atoms', $five['content']['explanations'][0]['text']);
    }

    public function test_cards_are_headed_by_what_they_say_never_by_a_number(): void
    {
        foreach (array_keys(SlideDeckAdapter::PHASES) as $method) {
            [$deck] = $this->build($method);
            foreach ($deck['slides'] as $s) {
                foreach ((array) ($s['interaction']['items'] ?? []) as $item) {
                    $this->assertFalse(\App\Services\StudyDeck\CardTitle::isGeneric($item['label']), "$method slide {$s['n']}: {$item['label']}");
                }
                $this->assertFalse(\App\Services\StudyDeck\CardTitle::isGeneric($s['title']) && $s['slide_type'] !== 'cover', "$method slide {$s['n']} title");
            }
        }

        // Concept 1 has two stored statements: with a method that asks for no mistake comparison they are the two cards.
        [$deck] = $this->build('inquiry_based');
        $cards = $this->explainSlide($deck, 1)['interaction'];
        $this->assertSame('reveal', $cards['kind']);
        $this->assertSame(['Atoms of one element are identical', 'Atoms cannot be created or destroyed'], array_column($cards['items'], 'label'));
        // The card text is the stored statement, unchanged, and the heading is not the whole text.
        $this->assertSame(['Atoms of one element are identical.', 'Atoms cannot be created or destroyed.'], array_column($cards['items'], 'text'));
    }

    public function test_the_coverage_map_keeps_each_statement_with_its_short_line_and_kind(): void
    {
        $entries = [['concept' => ['concept_name' => 'Dalton postulates', 'definition' => 'D.'], 'knowledge_items' => [
            ['statement' => 'A longer statement about the rate.', 'knowledge' => 'Rates give estimates', 'knowledge_type' => 'Process'],
            ['statement' => 'Another statement only.'],
        ]]];
        $cov = $this->coverage($entries);
        $cards = $cov['concepts'][1]['semantic']['knowledge_cards'];

        $this->assertSame([['statement' => 'A longer statement about the rate.', 'knowledge' => 'Rates give estimates', 'type' => 'Process'], ['statement' => 'Another statement only.', 'knowledge' => '', 'type' => '']], $cards);
        $this->assertSame(['A longer statement about the rate.', 'Another statement only.'], $cov['concepts'][1]['semantic']['knowledge'], 'the statements themselves are unchanged');
    }

    public function test_topic_level_intelligence_appears_as_the_topics_not_as_a_concepts(): void
    {
        [$deck] = $this->build('explicit_instruction');

        $topic = array_values(array_filter($deck['slides'], fn ($s) => $s['title'] === 'A common mistake in this topic: Atoms and matter'));
        $this->assertCount(1, $topic);
        $this->assertSame(['wrong_idea' => 'Matter is continuous', 'correction' => 'Matter is made of particles.'], $topic[0]['content']['misconception']);
        $this->assertSame('Recorded for the topic as a whole.', $topic[0]['content']['body']);
        // The concept-level mistake (Dalton) has its own slide; nothing is attributed to concept 3.
        $own = array_values(array_filter($deck['slides'], fn ($s) => $s['title'] === 'Dalton postulates' && (($s['interaction']['kind'] ?? '') === 'compare')));
        $this->assertCount(1, $own, 'the concept\'s own mistake is a comparison on its own slide');
        $this->assertSame(['wrong', 'right'], array_column($own[0]['interaction']['items'], 'id'));
        $this->assertNull($this->explainSlide($deck, 3)['content']['misconception']);
    }

    public function test_a_missing_picture_never_removes_a_concept_and_a_picture_goes_only_where_it_was_verified(): void
    {
        $pic = ['type' => 'diagram', 'url' => 'study-deck-image:7', 'alt' => 'A', 'caption' => null, 'width' => 8, 'height' => 4, 'texts' => [], 'licence' => null, 'source_url' => null, 'creator' => null, 'attribution' => '', 'attribution_required' => false];
        [$deck, , $summary] = $this->build('explicit_instruction', [4 => $pic]);

        $with = array_values(array_filter($deck['slides'], fn ($s) => $s['image'] !== null));
        $this->assertCount(1, $with);
        $this->assertSame([4], $with[0]['taught_concept_ids']);
        $this->assertSame(1, $summary['pictures_placed']);
        foreach ([1, 2, 3, 5] as $id) {
            $s = $this->explainSlide($deck, $id);
            $this->assertNotNull($s, "concept $id is still taught");
            $this->assertNull($s['image']);
            $this->assertSame('No suitable picture is available for this concept.', $s['image_missing']);
        }
    }

    public function test_the_selected_pedagogy_changes_the_order_while_covering_the_same_concepts(): void
    {
        [$explicit] = $this->build('explicit_instruction');
        [$inquiry] = $this->build('inquiry_based');

        $taught = fn (array $deck) => array_values(array_unique(array_merge(...array_map(fn ($s) => $s['taught_concept_ids'], $deck['slides']))));
        $this->assertSame([1, 2, 3, 4, 5], $taught($explicit));
        $this->assertSame([1, 2, 3, 4, 5], $taught($inquiry), 'the same concepts, whatever the method');
        $titles = fn (array $deck, string $section) => array_values(array_map(fn ($s) => $s['title'], array_filter($deck['slides'], fn ($s) => $s['section'] === $section)));
        // The methods differ in how a concept is framed: inquiry asks first (a prediction prompt), explicit instruction does not.
        $this->assertNull($this->explainSlide($explicit, 4)['content']['discussion']);
        $this->assertNotNull($this->explainSlide($inquiry, 4)['content']['discussion']);
        // Explicit instruction ends a topic with a short check; no method repeats a topic list or a recap per topic.
        $exp = $titles($explicit, 'Atoms and matter');
        $this->assertStringStartsWith('Check yourself', end($exp), 'a topic with three or more ideas ends with a short check');
        $this->assertContains('Ionic lattice', $titles($explicit, 'Bonding'));
        foreach ([$explicit, $inquiry] as $deck) {
            $this->assertCount(1, array_filter($deck['slides'], fn ($s) => $s['title'] === 'The chapter at a glance'), 'one overview');
            $this->assertSame([], array_filter($deck['slides'], fn ($s) => str_starts_with($s['title'], 'Summary:')), 'no recap after every topic');
        }
    }

    public function test_with_no_intelligence_the_deck_is_still_complete_from_the_textbook(): void
    {
        [$deck, $missing, $summary] = $this->build('explicit_instruction', [], [['concept' => ['concept_name' => 'Unrelated']]]);

        foreach ([1, 2, 3, 4, 5] as $id) {
            $this->assertNotNull($this->explainSlide($deck, $id));
            $this->assertStringContainsString('stored concept record', $this->explainSlide($deck, $id)['content']['body']);
            $this->assertStringNotContainsStringIgnoringCase('textbook', $this->explainSlide($deck, $id)['content']['body']);
        }
        $this->assertSame(0, $summary['semantic_concept_level']);
        // What the textbook cannot give is reported, not invented: no example and no misconception exist anywhere here.
        $what = array_column($missing, 'what');
        $this->assertContains('Real-world context', $what);
        $this->assertContains('Common mistakes', $what);
    }

    public function test_the_deck_keeps_the_shape_the_existing_viewer_reads(): void
    {
        foreach (array_keys(SlideDeckAdapter::PHASES) as $method) {
            [$deck] = $this->build($method);
            $this->assertSame(3, $deck['version'], $method);
            $this->assertSame(range(1, count($deck['slides'])), array_column($deck['slides'], 'n'), $method);
            $this->assertSame('cover', $deck['slides'][0]['slide_type']);
            foreach ($deck['slides'] as $s) {
                $this->assertSame([], $s['activities']);
                $this->assertNull($s['interaction'] ? \App\Services\CoursePresentation\PresentationQuality::interactionProblem($s['interaction'], ($s['image']['type'] ?? '') === 'diagram') : null, "$method slide {$s['n']}");
                foreach (array_merge($s['concept_ids'], $s['taught_concept_ids']) as $id) {
                    $this->assertArrayHasKey((string) $id, $deck['concepts'], "$method names concept $id");
                }
            }
            $this->assertCount(2, $deck['outline']);
            $problems = array_filter((new \App\Services\CoursePresentation\PresentationQuality())->check($deck, [1, 2, 3, 4, 5]), fn ($p) => !in_array($p['code'], ['missing_concept', 'duplicate_content'], true)); // this synthetic chapter gives several concepts the same fallback sentence on purpose
            $this->assertSame([], array_values($problems), "$method passes its own quality rules");
        }
    }

    public function test_learning_outcomes_and_competencies_come_from_the_curriculum_in_chunks_of_five(): void
    {
        $outcomes = [];
        foreach (range(1, 7) as $i) {
            $outcomes[] = ['type' => 'learning_outcome', 'code' => "LO-$i", 'description' => "Outcome $i"];
        }
        $outcomes[] = ['type' => 'competency', 'code' => 'C1.1', 'description' => 'Compete'];
        $chapter = ['id' => 8592, 'chapter_name' => 'Exploration: Entering the World of Secondary Science', 'standard_id' => 42, 'subject_id' => 3975];
        $deck = (new SlideDeckAdapter())->build($chapter, $this->coverage(), $outcomes, 'explicit_instruction')['deck'];

        $obj = array_values(array_filter($deck['slides'], fn ($s) => $s['slide_type'] === 'objectives'));
        $this->assertSame(['Learning outcomes (1 of 2)', 'Learning outcomes (2 of 2)', 'Competencies'], array_column($obj, 'title'));
        $this->assertSame(['LO-1 Outcome 1', 'LO-2 Outcome 2', 'LO-3 Outcome 3', 'LO-4 Outcome 4', 'LO-5 Outcome 5'], $obj[0]['content']['bullets']);
        $this->assertSame(['C1.1 Compete'], $obj[2]['content']['bullets']);
        $none = (new SlideDeckAdapter())->build($chapter, $this->coverage(), [], 'explicit_instruction')['missing'];
        $this->assertCount(0, array_filter($none, fn ($m) => in_array($m['what'], ['Learning outcomes', 'Competencies'], true)), 'a chapter without outcomes gets no outcome slide and no "missing" note');
    }

    public function test_what_no_source_holds_is_reported_never_invented(): void
    {
        [$deck, $missing] = $this->build('experiential', [], [['concept' => ['concept_name' => 'Unrelated']]]);

        $activity = array_values(array_filter($missing, fn ($m) => $m['what'] === 'Hands-on activity'));
        $this->assertCount(1, $activity, 'said once for the whole chapter, not once per topic');
        $this->assertStringContainsString('Prayogshala labs are a separate, unpublished resource', $activity[0]['why']);
        foreach ($deck['slides'] as $s) {
            $this->assertStringNotContainsStringIgnoringCase('experiment', json_encode($s['content']), 'no experiment is made up');
        }
        $this->assertNotEmpty(array_filter($this->build('gamified')[1], fn ($m) => str_contains($m['why'], 'Levels, points')));
        $this->assertNotEmpty(array_filter($this->build('project_based')[1], fn ($m) => str_contains($m['why'], 'project plan')));
    }

    public function test_a_reflective_prompt_is_answered_from_the_sources_only(): void
    {
        [$deck] = $this->build('socratic');
        $slide = $this->explainSlide($deck, 4);

        $this->assertSame('What do you already think about "Ionic lattice"?', $slide['content']['discussion']['prompt']);
        $this->assertSame('Ions form a regular lattice.', $slide['content']['discussion']['answer'], 'the answer is the stored explanation, nothing added');
    }

    public function test_a_long_explanation_is_shortened_at_a_sentence_never_in_the_middle_of_a_word(): void
    {
        $long = 'A molecule is an electrically neutral group of two or more atoms bonded together, able to exist independently and showing the properties of the substance. A single water molecule is the smallest particle that is still water and it keeps going on and on.';
        $concepts = [['id' => 1, 'name' => 'Molecule', 'description' => null, 'definition' => $long, 'topic_id' => 10]];
        $cov = (new ConceptCoverage())->build([['id' => 10, 'name' => 'Atoms', 'description' => null]], $concepts, null, 'A molecule is a group of atoms. ' . str_repeat('Atoms bond together to form a molecule of the substance. ', 3));
        $deck = (new SlideDeckAdapter())->build(['id' => 1, 'chapter_name' => 'c', 'standard_id' => 1, 'subject_id' => 1], $cov, [], 'explicit_instruction')['deck'];
        $slide = array_values(array_filter($deck['slides'], fn ($s) => $s['slide_type'] === 'concept_intro'))[0];

        $this->assertSame($long, $slide['content']['explanations'][0]['text'], 'the explanation itself is never cut');
        $this->assertSame('A molecule is an electrically neutral group of two or more atoms bonded together, able to exist independently and showing the properties of the substance.', $slide['content']['key_idea']);
    }

    public function test_an_unknown_method_has_no_template(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SlideDeckAdapter())->build(['id' => 1, 'chapter_name' => 'x', 'standard_id' => 1, 'subject_id' => 1], $this->coverage(), [], 'made_up');
    }

    // ------------------------------------------------------------------ the endpoints
    private function seedChapterContent(): void
    {
        $this->seedPilotChapter();
        config(['course_presentation_pilot.earlier_bundle_base' => $this->runDir . '/no-earlier-draft']);
        DB::table('document_extractions')->where('id', 81)->update(['md_content' => str_repeat(' ', 10) . self::TEXT . "\n\n" . str_repeat('Atoms combine to make molecules in a chemical change. ', 60)]);
        DB::table('lms_concept')->delete();
        DB::table('topic_master')->insert(array_map(fn ($t) => ['id' => $t['id'], 'chapter_id' => self::CHAPTER, 'name' => $t['name'], 'description' => $t['description'], 'topic_sort_order' => $t['id']], $this->topics()));
        foreach ($this->concepts() as $c) {
            DB::table('lms_concept')->insert(['chapter_id' => self::CHAPTER] + $c);
        }
        DB::table('semantic_intelligence')->where('id', 13)->update($this->semanticRow());
    }

    private function url(string $what, array $q = []): string
    {
        return "/api/lms/course-presentation/$what?" . http_build_query($q + ['chapter_id' => self::CHAPTER, 'pedagogy' => 'explicit_instruction', 'format' => 'interactive_slides']);
    }

    public function test_the_preflight_reports_the_coverage_map(): void
    {
        $this->seedChapterContent();
        $res = $this->asTeacher()->getJson($this->url('preflight'))->assertOk();

        $cov = $res->json('coverage');
        $this->assertSame(6, $cov['summary']['total_concepts']);
        $this->assertSame([2, 1, 3, 5, 2, 3, 1], [$cov['summary']['semantic_concept_level'], $cov['summary']['semantic_topic_level_only'], $cov['summary']['no_semantic'], $cov['summary']['from_intelligence'] + $cov['summary']['from_concept_record_only'], $cov['summary']['from_intelligence'], $cov['summary']['from_concept_record_only'], $cov['summary']['unresolved']]);
        $this->assertSame(['Quantum spin'], array_column($cov['unresolved'], 'name'));
        $this->assertSame(['Zinc smelting'], $cov['intelligence_entries_not_mapped']);
        $this->assertCount(6, $cov['concepts'], 'one line per concept');
        $this->assertSame(['name', 'topic', 'source', 'semantic', 'match', 'picture', 'sufficient', 'reason'], array_keys($cov['concepts'][0]), 'no record ids and no passage text');
        // The school has stored outcomes for this synthetic chapter, so the note is there; a chapter without any gets none (see below).
        $this->assertStringContainsString('chapter-level', $cov['learning_outcomes']);
        // A missing semantic entry is not a blocker: the textbook covers the concept, and the combination still continues.
        $this->assertTrue($res->json('fit.continue_allowed'));
        $this->assertTrue($res->json('fit.template.available'));
    }

    public function test_the_screen_never_names_or_quotes_the_textbook(): void
    {
        $this->seedChapterContent();
        foreach (['preflight', 'preview'] as $what) {
            $json = json_encode($this->asTeacher()->getJson($this->url($what))->assertOk()->json());
            $this->assertStringNotContainsStringIgnoringCase('textbook', $json, $what);
        }
    }

    public function test_the_source_entry_for_intelligence_describes_the_three_ways_a_concept_is_covered(): void
    {
        $this->seedChapterContent();
        $sem = collect($this->asTeacher()->getJson($this->url('preflight'))->json('sources'))->firstWhere('key', 'semantic_intelligence');

        $this->assertSame('Available — partial coverage', $sem['status_label']);
        $this->assertStringContainsString("2 of the chapter's 6 concepts have their own intelligence (1 by exact name, 1 by a contained name)", $sem['summary']);
        $this->assertStringContainsString('1 more are covered only through their topic and 3 have none', $sem['summary']);
        $this->assertStringContainsString('3 from the stored concept record alone and 2 from its intelligence; 1 could not be tied to any source', $sem['summary']);
        $this->assertSame(2, $sem['coverage']['summary']['from_intelligence']);
    }

    public function test_the_preview_covers_every_resolvable_concept_and_reports_the_rest(): void
    {
        $this->seedChapterContent();
        $res = $this->asTeacher()->getJson($this->url('preview'))->assertOk();

        $explain = array_values(array_filter($res->json('deck.slides'), fn ($s) => count($s['taught_concept_ids']) === 1));
        $this->assertCount(5, $explain);
        $this->assertSame(5, $res->json('summary.concepts_explained'));
        $this->assertSame(6, $res->json('summary.total_concepts'));
        $this->assertContains('Explanation (unresolved source mapping)', array_column($res->json('missing'), 'what'));
        $this->assertSame(1, $res->json('coverage.summary.unresolved'));
        $this->assertTrue($res->json('wrote_nothing'));
        $this->assertSame('Exploration: Entering the World of Secondary Science', $res->json('deck.chapter.name'));
        $this->assertSame(['9', 'Science'], [$res->json('deck.chapter.standard_name'), $res->json('deck.chapter.subject_name')]);
    }

    public function test_the_preview_writes_nothing_and_calls_no_model(): void
    {
        $this->seedChapterContent();
        \Illuminate\Support\Facades\Bus::fake();
        $before = [DB::table('content_master')->count(), DB::table('study_deck_images')->count(), DB::table('study_deck_image_links')->count(), DB::table('semantic_intelligence')->count(), DB::table('lms_concept')->count()];
        $writes = [];
        DB::listen(function ($q) use (&$writes) {
            if (!str_starts_with(strtolower(ltrim($q->sql)), 'select')) {
                $writes[] = $q->sql;
            }
        });

        $this->asTeacher()->getJson($this->url('preview'))->assertOk();

        \Illuminate\Support\Facades\Bus::assertNothingDispatched();
        $this->assertSame([], $writes);
        $this->assertSame($before, [DB::table('content_master')->count(), DB::table('study_deck_images')->count(), DB::table('study_deck_image_links')->count(), DB::table('semantic_intelligence')->count(), DB::table('lms_concept')->count()]);
    }

    public function test_pictures_attach_through_the_verified_pool_to_the_right_concept_slide(): void
    {
        $this->seedChapterContent();
        $store = new \App\Services\StudyDeck\StudyDeckImages();
        $im = imagecreatetruecolor(16, 9);
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        $stored = $store->put($png, self::TENANT, self::CHAPTER);
        config(['course_presentation_pilot.earlier_bundle_base' => $this->runDir . '/earlier']);
        $dir = storage_path($this->runDir . '/earlier/chapter-' . self::CHAPTER);
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/images.json', json_encode(['2' => ['type' => 'diagram', 'url' => $stored['ref'], 'texts' => ['Ionic lattice', 'Ions'], 'alt' => 'Lattice', 'caption' => null]]));
        file_put_contents($dir . '/plan.json', json_encode(['slides' => [['n' => 2, 'taught_concept_ids' => [4]]]]));

        $res = $this->asTeacher()->getJson($this->url('preview'))->assertOk();

        $with = array_values(array_filter($res->json('deck.slides'), fn ($s) => $s['image'] !== null));
        $this->assertCount(1, $with);
        $this->assertSame('Ionic lattice', $with[0]['title']);
        $this->assertStringContainsString('/study-deck/images/' . $stored['id'], $with[0]['image']['url']);
        $this->assertSame(['with_a_verified_picture' => 1, 'without' => 5, 'candidates_rejected' => 0], $res->json('coverage.pictures'));
        $this->assertSame(1, DB::table('study_deck_images')->count(), 'no record was created');
    }

    public function test_the_coverage_is_isolated_to_the_pilot_chapter_and_ignores_prayogshala(): void
    {
        $this->seedChapterContent();
        DB::table('chapter_master')->insert(['id' => 8615, 'chapter_name' => 'Other', 'standard_id' => self::STANDARD, 'subject_id' => self::SUBJECT, 'sub_institute_id' => self::TENANT]);
        DB::table('lms_concept')->insert(['id' => 99, 'chapter_id' => 8615, 'name' => 'Another chapter concept', 'definition' => 'Not ours.', 'topic_id' => 10]);

        $this->asTeacher()->getJson($this->url('preview', ['chapter_id' => 8615]))->assertStatus(403);
        $this->asTeacher(self::OTHER_TENANT)->getJson($this->url('preview'))->assertStatus(404);
        $deck = $this->asTeacher()->getJson($this->url('preview'))->assertOk()->json('deck');
        $this->assertStringNotContainsString('Another chapter concept', json_encode($deck), 'a concept of another chapter never enters this deck');
        $this->assertStringNotContainsStringIgnoringCase('prayogshala', json_encode($deck));
        $this->assertSame(2, DB::table('lms_prayogshala_activity')->where('status', 'review')->count());
    }
}
