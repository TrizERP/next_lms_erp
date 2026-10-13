<?php

namespace Tests\Unit\StudyDeck\Documents;

use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\Documents\CompactRevisionPdfRenderer;
use App\Services\StudyDeck\Documents\DocumentAssembler;
use App\Services\StudyDeck\Documents\DocumentKind;
use App\Services\StudyDeck\Documents\QuestionPlacement;
use App\Services\StudyDeck\Documents\StudyDocumentService;
use App\Services\StudyDeck\Documents\Writers\RevisionNotesWriter;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Unit\StudyDeck\StudyDeckFixture;

/**
 * The compact profile of the revision notes: a pack fitted to a page count.
 *
 * No database and no Laravel: the model is a script of replies, the page count is measured by a stand-in or by real
 * Dompdf where the test is about the PDF itself, and every step that is code runs for real.
 */
class CompactRevisionNotesTest extends TestCase
{
    use StudyDeckFixture;

    // ---------------------------------------------------------------------------------------------------------
    // The writer's limits

    private function note(array $over = []): array
    {
        return $over + [
            'summary' => 'A scientific model is a simplified representation of a real system.',
            'key_points' => ['A model keeps only what the question needs.', 'A model ignores some details on purpose.'],
            'definition' => null, 'rules' => [], 'example' => null, 'misconception' => null, 'remember' => null,
            'checklist' => ['I can say what a model is.'], 'diagram' => null, 'diagram_notes' => [],
            'bloom' => 'understand', 'dok' => 2, 'minutes' => 2,
        ];
    }

    public function test_a_compact_note_within_the_limits_has_no_problems(): void
    {
        $this->assertSame([], RevisionNotesWriter::problems($this->note(), ['misconceptions' => []], true));
    }

    public function test_the_compact_limits_are_tighter_than_the_full_ones(): void
    {
        $long = $this->note([
            'summary' => implode(' ', array_fill(0, 30, 'word')),
            'key_points' => ['one idea here', 'two ideas here', 'three ideas here', 'four ideas here'],
            'definition' => ['term' => 'Model', 'text' => implode(' ', array_fill(0, 30, 'word'))],
            'checklist' => ['I can do one thing.', 'I can do another thing.'],
        ]);

        $compact = implode(' | ', RevisionNotesWriter::problems($long, ['misconceptions' => []], true));
        $this->assertStringContainsString('summary has 30 words', $compact);
        $this->assertStringContainsString('4 key points', $compact);
        $this->assertStringContainsString('definition is over', $compact);
        $this->assertStringContainsString('exactly one checklist statement', $compact);

        // The same note is fine for the full notes (30 words, four points, two checklist lines, a 30-word definition).
        $this->assertSame([], RevisionNotesWriter::problems($long, ['misconceptions' => []], false));
    }

    public function test_a_point_over_the_compact_word_limit_is_a_problem(): void
    {
        $note = $this->note(['key_points' => [implode(' ', array_fill(0, 17, 'word')), 'A short second point here.']]);

        $this->assertStringContainsString('over 16 words', implode(' ', RevisionNotesWriter::problems($note, ['misconceptions' => []], true)));
        $this->assertSame([], RevisionNotesWriter::problems($note, ['misconceptions' => []], false));
    }

    // ---------------------------------------------------------------------------------------------------------
    // Which questions a compact pack may print, and in what order

    private function choice(int $id, int $concept, string $why = 'Because the second choice is the right one.', array $over = []): array
    {
        return $over + [
            'id' => $id, 'concept_id' => $concept, 'form' => 'mcq', 'stem' => 'Which describes question ' . $id . '?',
            'options' => [['label' => 'A', 'text' => 'First choice'], ['label' => 'B', 'text' => 'Second choice']],
            'correct_label' => 'B', 'answer_text' => '', 'explanation' => $why, 'bloom' => 'apply', 'difficulty' => 'medium',
        ];
    }

    public function test_a_question_is_offered_only_when_its_answer_area_holds_it_whole(): void
    {
        $this->assertTrue(CompactRevisionPdfRenderer::fits($this->choice(1, 1)));
        $this->assertFalse(CompactRevisionPdfRenderer::fits($this->choice(2, 1, str_repeat('a long reason ', 30))), 'a reason longer than the area is never cut');
        $this->assertFalse(CompactRevisionPdfRenderer::fits($this->choice(3, 1, '')), 'a choice question needs its stored reason');
        $this->assertFalse(CompactRevisionPdfRenderer::fits($this->choice(4, 1, 'ok', ['correct_label' => 'Z'])), 'no correct option, no answer');
        $this->assertFalse(CompactRevisionPdfRenderer::fits($this->choice(5, 1, 'ok', ['stem' => str_repeat('x', 241)])));
        $this->assertFalse(CompactRevisionPdfRenderer::fits($this->choice(6, 1, 'ok', ['options' => [['label' => 'A', 'text' => str_repeat('y', 111)], ['label' => 'B', 'text' => 'b']]])));

        $short = ['id' => 7, 'concept_id' => 1, 'form' => 'very_short_answer', 'stem' => 'What does a law describe?', 'options' => [], 'correct_label' => '', 'answer_text' => 'A repeated pattern.', 'explanation' => '', 'bloom' => 'remember', 'difficulty' => 'easy'];
        $this->assertTrue(CompactRevisionPdfRenderer::fits($short), 'a short answer is printed with its model answer');
        $this->assertFalse(CompactRevisionPdfRenderer::fits(['answer_text' => ''] + $short));
        $this->assertSame(['MODEL ANSWER', 'A repeated pattern.', ''], CompactRevisionPdfRenderer::answerOf($short));
        $this->assertSame(['ANSWER', 'B. Second choice', 'Because the second choice is the right one.'], CompactRevisionPdfRenderer::answerOf($this->choice(1, 1)));
    }

    public function test_the_order_is_one_from_every_topic_before_a_second_from_any(): void
    {
        $concepts = [1 => ['topic_id' => 10], 2 => ['topic_id' => 10], 3 => ['topic_id' => 11], 4 => ['topic_id' => 12]];
        $eligible = [
            1 => [$this->choice(11, 1), $this->choice(12, 1)],
            2 => [$this->choice(21, 2)],
            3 => [$this->choice(31, 3), $this->choice(32, 3)],
            4 => [$this->choice(41, 4, str_repeat('too long a reason ', 40))],   // does not fit: topic 12 offers nothing
        ];

        $ordered = (new QuestionPlacement())->forCompact($eligible, [1, 2, 3, 4], $concepts);
        $ids = array_column($ordered, 'id');

        // First pass: topic 10, then topic 11 (topic 12 has nothing that fits). Second pass: what is left, topic by topic.
        $this->assertSame(2, count(array_intersect(array_slice($ids, 0, 2), [11, 12, 21, 31, 32])), 'the first pass has one question per topic');
        $this->assertSame([10, 11], array_map(fn ($q) => $concepts[$q['concept_id']]['topic_id'], array_slice($ordered, 0, 2)));
        $this->assertNotContains(41, $ids, 'a question that cannot be printed whole is never offered');
        $this->assertCount(5, $ids);
    }

    public function test_a_concept_not_yet_asked_about_comes_before_one_that_has_been(): void
    {
        $concepts = [1 => ['topic_id' => 10], 2 => ['topic_id' => 10]];
        $eligible = [1 => [$this->choice(11, 1), $this->choice(12, 1)], 2 => [$this->choice(21, 2)]];

        $ids = array_column((new QuestionPlacement())->forCompact($eligible, [1, 2], $concepts), 'id');

        // The topic's second question is the other concept's, not a second one of concept 1.
        $this->assertSame(21, $ids[1]);
    }

    public function test_the_first_n_are_grouped_by_concept_in_teaching_order(): void
    {
        $ordered = [$this->choice(31, 3), $this->choice(11, 1), $this->choice(12, 1)];

        $placed = (new QuestionPlacement())->takeCompact($ordered, 3, [1, 2, 3]);

        $this->assertSame([1, 3], array_keys($placed));
        $this->assertSame([11, 12], array_column($placed[1], 'id'));
        $this->assertSame([], (new QuestionPlacement())->takeCompact($ordered, 0, [1, 2, 3]));
    }

    // ---------------------------------------------------------------------------------------------------------
    // Diagrams

    public function test_a_compact_pack_keeps_the_two_diagrams_with_most_to_show(): void
    {
        $notes = [
            1 => ['diagram' => ['layout' => 'flow', 'title' => 'a', 'nodes' => ['One', 'Two', 'Three']]],
            2 => ['diagram' => ['layout' => 'flow', 'title' => 'b', 'nodes' => ['One', 'Two', 'Three', 'Four', 'Five']]],
            3 => ['diagram' => ['layout' => 'compare', 'title' => 'c', 'left' => ['heading' => 'L', 'items' => ['x', 'y']], 'right' => ['heading' => 'R', 'items' => ['p', 'q', 'r']]]],
            4 => ['diagram' => ['layout' => 'flow', 'title' => 'd', 'nodes' => ['One', 'Two']]],   // fewer than three parts: not worth a place
            5 => ['diagram' => null],
        ];
        $assembler = (new \ReflectionClass(DocumentAssembler::class))->newInstanceWithoutConstructor();
        $best = (new \ReflectionMethod(DocumentAssembler::class, 'bestDiagrams'));

        $this->assertSame([2 => 5, 3 => 5], $best->invoke($assembler, $notes, [1, 2, 3, 4, 5], 2), 'most parts first, the earlier concept on a tie');
    }

    // ---------------------------------------------------------------------------------------------------------
    // The pipeline: write, assemble, fit, validate

    /** Notes for the fixture chapter's four concepts, in the compact shape. */
    private function compactNotes(): array
    {
        $n = fn (int $id, string $summary, array $points, string $bloom, ?array $def = null) => [
            'concept_id' => $id, 'summary' => $summary, 'key_points' => $points, 'definition' => $def,
            'checklist' => ['I can explain this idea.'], 'diagram' => null, 'diagram_notes' => [], 'bloom' => $bloom, 'dok' => 2, 'minutes' => 2,
        ];

        return ['notes' => [
            $n(1, 'A scientific model is a simplified representation of a real system.', ['A model ignores some details on purpose.', 'A map shows roads but ignores trees.'], 'understand', ['term' => 'Model', 'text' => 'A simplified representation of a real system.']),
            $n(2, 'A model ignores some details on purpose.', ['A map shows roads but ignores trees.', 'What is ignored depends on the question.'], 'apply'),
            $n(3, 'A law describes a repeated pattern.', ['Newton gave three laws of motion.', 'A law says what happens.'], 'remember'),
            $n(4, 'A theory explains why patterns occur.', ['A theory says why, a law says what.', 'The symbol m stands for mass.'], 'analyze'),
        ]];
    }

    private function overviewReply(): array
    {
        return [
            'lede' => 'Revise how scientists model, describe and explain the world before your examination.',
            'summary' => 'A scientific model is a simplified representation of a real system that ignores some details on purpose. A law describes a repeated pattern and a theory explains why patterns occur.',
            'topics' => [['topic_id' => 10, 'gist' => 'Models keep what the question needs and ignore the rest.'], ['topic_id' => 11, 'gist' => 'Laws describe patterns and theories explain them.']],
        ];
    }

    /** @return array{0:StudyDocumentService,1:object} */
    private function service(): array
    {
        $completer = $this->completer([json_encode($this->overviewReply()), json_encode($this->compactNotes())]);

        return [StudyDocumentService::make($completer, $this->memoryStore(), $this->imageSearch(null), new ConceptContextBuilder()), $completer];
    }

    /** Pages a document comes to: three for the notes, one more for every two questions. Both copies alike unless `$practice` says otherwise. */
    private function pagesOf(int $notes = 3, ?int $practice = null): \Closure
    {
        return function (array $document) use ($notes, $practice): array {
            $q = array_sum(array_map(fn ($s) => count($s['question_ids'] ?? []), $document['sections']));
            $pages = $notes + intdiv($q + 1, 2);

            return ['revision' => $pages, 'practice' => $practice ?? $pages];
        };
    }

    public function test_the_pack_holds_as_many_questions_as_the_page_target_allows(): void
    {
        [$service] = $this->service();

        $r = $service->fromRaw(DocumentKind::RevisionNotes, $this->raw(), ['compact' => true, 'pages' => 4, 'measure' => $this->pagesOf()]);

        // 3 pages of notes + 1 page for up to two questions: two of the three offered questions fit.
        $this->assertSame('compact', $r['document']['profile']);
        $this->assertSame(2, $r['document']['stats']['questions']);
        $this->assertSame(['target_pages' => 4, 'pages' => ['revision' => 4, 'practice' => 4], 'questions_offered' => 3, 'questions_printed' => 2], $r['document']['compact']);
        $this->assertSame([], $r['report']['errors'], implode("\n", $r['report']['errors']));
        $this->assertTrue($r['report']['ok']);
        $this->assertSame(['revision' => 4, 'practice' => 4], $r['report']['stats']['pages']);
    }

    public function test_every_concept_is_covered_in_order_and_the_questions_are_the_banks_own(): void
    {
        [$service] = $this->service();

        $r = $service->fromRaw(DocumentKind::RevisionNotes, $this->raw(), ['compact' => true, 'pages' => 5, 'measure' => $this->pagesOf()]);
        $d = $r['document'];

        $this->assertSame(['Models', 'Ignoring details', 'Laws', 'Theories'], array_column(array_filter($d['sections'], fn ($s) => $s['type'] === 'note'), 'title'));
        $this->assertSame([0, 1, 2, 3, 4], array_column($d['sections'], 'n'));
        $this->assertSame([1 => [1], 2 => [2], 3 => [3], 4 => [4]], $d['taught_by']);
        $asked = array_merge(...array_map(fn ($s) => $s['question_ids'], $d['sections']));
        sort($asked);
        $this->assertSame([101, 103, 104], $asked, 'only bank questions that are self-contained and fit; 102 and 105 need the passage');
    }

    /**
     * A run that gives up writes the model's raw replies to storage_path() for inspection (Laravel's helper). This test
     * has no Laravel, so it runs in its own process with a minimal container whose storage path is a temporary folder:
     * nothing is written anywhere else.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_notes_that_alone_overrun_the_target_stop_the_run(): void
    {
        Container::setInstance(new class extends Container {
            public function storagePath($path = '')
            {
                return sys_get_temp_dir() . '/compact-test-storage' . ($path !== '' ? '/' . $path : '');
            }
        });
        [$service] = $this->service();

        try {
            // Six pages of notes before a single question: nothing that is printed can bring it down to five.
            $service->fromRaw(DocumentKind::RevisionNotes, $this->raw(), ['compact' => true, 'pages' => 5, 'measure' => $this->pagesOf(6)]);
            $this->fail('a pack whose notes overrun the target must not be produced');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('The notes alone come to 6 pages (the target is 5)', $e->getMessage());
        }
    }

    public function test_the_two_copies_must_each_come_to_the_target(): void
    {
        [$service] = $this->service();

        // The answers-hidden copy is always drawn at 4 pages: a pack that is 5 pages in one copy and 4 in the other is not 5 pages.
        $r = $service->fromRaw(DocumentKind::RevisionNotes, $this->raw(), ['compact' => true, 'pages' => 5, 'measure' => $this->pagesOf(3, 4)]);

        $this->assertFalse($r['report']['ok']);
        $this->assertSame(['The answers-hidden copy comes to 4 pages; the compact pack must be exactly 5.'], $r['report']['errors']);
    }

    public function test_a_compact_pack_needs_a_way_to_measure_its_pages(): void
    {
        [$service] = $this->service();

        $this->expectException(\InvalidArgumentException::class);
        $service->fromRaw(DocumentKind::RevisionNotes, $this->raw(), ['compact' => true, 'pages' => 5]);
    }

    // ---------------------------------------------------------------------------------------------------------
    // The PDF

    /** @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>} the compact document and the bank questions it prints */
    private function packWithQuestions(): array
    {
        [$service] = $this->service();
        $r = $service->fromRaw(DocumentKind::RevisionNotes, $this->raw(), ['compact' => true, 'pages' => 5, 'measure' => $this->pagesOf()]);
        $questions = [];
        foreach ($r['selection']['eligible'] as $list) {
            foreach ($list as $q) {
                $questions[$q['id']] = $q;
            }
        }

        return [$r['document'], $questions];
    }

    private function html(array $document, array $questions, string $variant): string
    {
        return (new CompactRevisionPdfRenderer())->html($document, '', ['variant' => $variant, 'questions' => $questions]);
    }

    public function test_the_hidden_copy_shows_no_answer_and_no_marking(): void
    {
        [$document, $questions] = $this->packWithQuestions();

        // The page itself, not its stylesheet (which names the marking class in both copies).
        $body = fn (string $html) => preg_replace('#<style>.*?</style>#s', '', $html);
        $shown = $body($this->html($document, $questions, 'revision'));
        $hidden = $body($this->html($document, $questions, 'practice'));

        $this->assertStringContainsString('class="cx-ol cx-ok"', $shown, 'the shown copy marks the correct option');
        $this->assertStringContainsString('A repeated pattern.', $shown);
        $this->assertStringContainsString('The second choice is right because a model keeps only what the question needs.', $shown);

        foreach (['cx-ok', '<span class="cx-al">ANSWER</span>', '<span class="cx-al">MODEL ANSWER</span>', '<span class="cx-al">WHY</span>', 'A repeated pattern.', 'The second choice is right because'] as $leak) {
            $this->assertStringNotContainsString($leak, $hidden, "the hidden copy must not contain \"$leak\"");
        }
        // It still asks every question, with every option, as the bank has them.
        foreach (['Which describes a scientific model?', 'Why does a map ignore trees?', 'What does a law describe?', 'First choice', 'Second choice'] as $kept) {
            $this->assertStringContainsString($kept, $hidden);
        }
    }

    public function test_the_two_copies_differ_only_in_the_answer_areas(): void
    {
        [$document, $questions] = $this->packWithQuestions();
        // The answer areas, the marking of the correct option, the name of the copy and the one-line instruction under "Practice questions" are what differ.
        $strip = fn (string $html) => preg_replace(['#<div class="cx-a[^"]*">.*?</div>#s', '# cx-ok#', '#ANSWERS (SHOWN|HIDDEN)#', '#<div class="cx-sn">.*?</div>#s'], ['<A>', '', 'ANSWERS', '<N>'], $html);

        $this->assertSame($strip($this->html($document, $questions, 'revision')), $strip($this->html($document, $questions, 'practice')));
    }

    private function pdf(string $html): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    public function test_the_page_counter_agrees_with_dompdf(): void
    {
        $three = $this->pdf('<p>one</p><div style="page-break-after:always"></div><p>two</p><div style="page-break-after:always"></div><p>three</p>');
        $one = $this->pdf('<p>only</p>');

        $this->assertSame(3, CompactRevisionPdfRenderer::pageCount($three));
        $this->assertSame(1, CompactRevisionPdfRenderer::pageCount($one));
        $this->assertSame(0, CompactRevisionPdfRenderer::pageCount('not a pdf'));
    }

    public function test_both_copies_paginate_alike(): void
    {
        [$document, $questions] = $this->packWithQuestions();

        $shown = CompactRevisionPdfRenderer::pageCount($this->pdf($this->html($document, $questions, 'revision')));
        $hidden = CompactRevisionPdfRenderer::pageCount($this->pdf($this->html($document, $questions, 'practice')));

        $this->assertSame($shown, $hidden, 'the answer area is the same height in both copies');
        $this->assertGreaterThanOrEqual(1, $shown);
    }

    public function test_a_document_is_drawn_by_the_compact_renderer_only_when_it_says_it_is_compact(): void
    {
        [$document] = $this->packWithQuestions();

        $this->assertTrue(CompactRevisionPdfRenderer::isCompact($document));
        $this->assertFalse(CompactRevisionPdfRenderer::isCompact(['kind' => 'revision_notes']), 'a document is compact only when it says so');
        $this->assertFalse(CompactRevisionPdfRenderer::isCompact(['profile' => 'compact', 'kind' => 'nonsense']), 'and only for a kind there is');
    }
}
