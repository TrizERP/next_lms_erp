<?php

namespace Tests\Unit\StudyDeck\Documents;

use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\Documents\CompactActivitiesPdfRenderer;
use App\Services\StudyDeck\Documents\CompactRevisionPdfRenderer;
use App\Services\StudyDeck\Documents\DocumentKind;
use App\Services\StudyDeck\Documents\StudyDocumentService;
use App\Services\StudyDeck\Documents\Writers\ActivityWriter;
use App\Services\StudyDeck\Documents\Writers\RemedialWriter;
use Dompdf\Dompdf;
use Dompdf\Options;
use PHPUnit\Framework\TestCase;
use Tests\Unit\StudyDeck\StudyDeckFixture;

/**
 * The compact profile of the remedial class and of the classroom activities: the same fit to a page count as the
 * compact revision notes, with the cards of each kind.
 *
 * No database and no Laravel: the model is a script of replies, the page count is measured by a stand-in or by real
 * Dompdf where the test is about the PDF itself.
 */
class CompactStudyDocumentsTest extends TestCase
{
    use StudyDeckFixture;

    /** Pages a document comes to: three for the cards, one more for every two questions. */
    private function pagesOf(int $cards = 3): \Closure
    {
        return function (array $document) use ($cards): array {
            $q = array_sum(array_map(fn ($s) => count($s['question_ids'] ?? []), $document['sections']));
            $pages = $cards + intdiv($q + 1, 2);

            return ['revision' => $pages, 'practice' => $pages];
        };
    }

    private function service(array $replies): StudyDocumentService
    {
        return StudyDocumentService::make($this->completer(array_map('json_encode', $replies)), $this->memoryStore(), $this->imageSearch(null), new ConceptContextBuilder());
    }

    // ---------------------------------------------------------------------------------------------------------
    // Remedial

    private function unit(array $over = []): array
    {
        return $over + [
            'prerequisites' => [], 'simple_explanation' => 'A model is a simplified view of a real system.',
            'steps' => [['id' => 'i1', 'label' => 'Pick a system', 'text' => 'Choose the real system to study.'], ['id' => 'i2', 'label' => 'Keep what matters', 'text' => 'Keep only what the question needs.'], ['id' => 'i3', 'label' => 'Leave the rest', 'text' => 'Ignore the other details on purpose.']],
            'real_life' => null, 'worked_example' => null, 'mistakes' => [], 'practice' => [], 'win' => 'You can now say what a model is.',
            'diagram' => null, 'diagram_notes' => [], 'bloom' => 'understand', 'dok' => 2, 'minutes' => 4,
        ];
    }

    public function test_a_compact_unit_within_the_limits_has_no_problems(): void
    {
        $this->assertSame([], RemedialWriter::problems($this->unit(), ['misconceptions' => [], 'practice' => []], null, true));
    }

    public function test_the_compact_unit_limits_are_tighter_than_the_full_ones(): void
    {
        $long = $this->unit([
            'simple_explanation' => implode(' ', array_fill(0, 30, 'word')),
            'steps' => [['id' => 'i1', 'label' => 'One', 'text' => implode(' ', array_fill(0, 12, 'word'))], ['id' => 'i2', 'label' => 'Two two two two', 'text' => 'Short text here now.'], ['id' => 'i3', 'label' => 'Three', 'text' => 'Short text here now.'], ['id' => 'i4', 'label' => 'Four', 'text' => 'Short text here now.']],
            'win' => 'You can now do a great many things with this idea of the whole chapter.',
        ]);
        $work = ['misconceptions' => [], 'practice' => []];

        $compact = implode(' | ', RemedialWriter::problems($long, $work, null, true));
        $this->assertStringContainsString('simple explanation has 30 words', $compact);
        $this->assertStringContainsString('exactly 3 steps', $compact);
        $this->assertStringContainsString('win has', $compact);

        // The same unit is fine as a full unit apart from its step count, which the full rules allow.
        $this->assertStringNotContainsString('simple explanation', implode(' ', RemedialWriter::problems($this->unit(['simple_explanation' => implode(' ', array_fill(0, 30, 'word'))]), $work, null, false)));
    }

    public function test_a_compact_class_asks_for_no_practice_hints(): void
    {
        $work = ['misconceptions' => [], 'practice' => [['question_id' => 7, 'model_answer' => 'x']]];

        $this->assertStringContainsString('Write a hint', implode(' ', RemedialWriter::problems($this->unit(), $work, null, false)));
        $this->assertSame([], RemedialWriter::problems($this->unit(), $work, null, true));
    }

    /** @return array<int,array<string,mixed>> */
    private function unitsReply(): array
    {
        $u = fn (int $id, string $explanation, string $bloom, array $mistakes = []) => [
            'concept_id' => $id, 'simple_explanation' => $explanation, 'steps' => [
                ['label' => 'Look first', 'text' => 'Start from the real system.'], ['label' => 'Keep the key', 'text' => 'Keep only what the question needs.'], ['label' => 'Say it plainly', 'text' => 'Say the idea in plain words.'],
            ], 'mistakes' => $mistakes, 'win' => 'You can now explain this idea.', 'diagram' => null, 'diagram_notes' => [], 'bloom' => $bloom, 'dok' => 2, 'minutes' => 3,
        ];

        return ['units' => [
            $u(1, 'A scientific model is a simplified representation of a real system.', 'understand', [['wrong_idea' => 'A model is an exact copy', 'why_wrong' => 'A model keeps only needed features', 'correct_idea' => 'A model keeps only needed features']]),
            $u(2, 'A model ignores some details on purpose.', 'apply'),
            $u(3, 'A law describes a repeated pattern.', 'remember'),
            $u(4, 'A theory explains why patterns occur.', 'analyze'),
        ]];
    }

    public function test_the_compact_remedial_class_has_a_unit_for_every_concept_and_fits_its_pages(): void
    {
        $r = $this->service([$this->unitsReply()])->fromRaw(DocumentKind::Remedial, $this->raw(), ['compact' => true, 'pages' => 5, 'measure' => $this->pagesOf(3)]);
        $d = $r['document'];

        $this->assertSame([], $r['report']['errors'], implode("\n", $r['report']['errors']));
        $this->assertTrue($r['report']['ok']);
        $this->assertSame('compact', $d['profile']);
        $this->assertSame(['Models', 'Ignoring details', 'Laws', 'Theories'], array_column(array_filter($d['sections'], fn ($s) => $s['type'] === 'unit'), 'title'));
        $this->assertSame([1 => [1], 2 => [2], 3 => [3], 4 => [4]], $d['taught_by']);
        $this->assertSame(['revision' => 5, 'practice' => 5], $d['compact']['pages']);
        $this->assertSame(3, $d['stats']['questions'], 'every question that can be printed whole fits, up to the page target');
    }

    public function test_a_compact_unit_has_no_worked_example_no_refresher_and_at_most_one_mistake(): void
    {
        $reply = $this->unitsReply();
        $reply['units'][0]['worked_example'] = ['problem' => 'Find the model', 'steps' => [['text' => 'a', 'why' => 'b'], ['text' => 'c', 'why' => 'd']], 'answer' => 'e'];
        $reply['units'][0]['real_life'] = 'Think of a map.';
        $reply['units'][0]['prerequisites'] = [['concept_id' => 99, 'refresher' => 'x']];

        $r = $this->service([$reply])->fromRaw(DocumentKind::Remedial, $this->raw(), ['compact' => true, 'pages' => 5, 'measure' => $this->pagesOf(3)]);
        $unit = array_values(array_filter($r['document']['sections'], fn ($s) => $s['type'] === 'unit'))[0]['content'];

        $this->assertNull($unit['worked_example']);
        $this->assertNull($unit['real_life']);
        $this->assertCount(3, $unit['steps']);
        $this->assertLessThanOrEqual(1, count($unit['mistakes']));
    }

    // ---------------------------------------------------------------------------------------------------------
    // Activities

    public function test_a_compact_set_is_smaller_but_still_covers_every_concept(): void
    {
        $this->assertSame([8, 10], ActivityWriter::range(32, true));
        $this->assertSame([8, 14], ActivityWriter::range(32, false));
        // Every concept must still fit: at most four to an activity, so never fewer than a quarter of them.
        foreach ([4, 9, 20, 32, 60] as $n) {
            [$min, $max] = ActivityWriter::range($n, true);
            $this->assertGreaterThanOrEqual((int) ceil($n / ActivityWriter::MAX_CONCEPTS), $min, "$n concepts need at least a quarter as many activities");
            $this->assertGreaterThanOrEqual($min, $max, "the range for $n concepts must not be upside down");
            // Never more than the compact maximum, unless covering every concept needs more (then the minimum is all there is).
            $this->assertLessThanOrEqual(max($min, ActivityWriter::COMPACT_ACTIVITIES_MAX), $max);
        }
    }

    public function test_a_compact_set_has_no_matching_or_sequencing(): void
    {
        $plan = [['id' => 'a1', 'title' => 'Match terms', 'format' => 'matching', 'grouping' => 'pair', 'minutes' => 15, 'concept_ids' => [1, 2, 3, 4], 'focus' => 'Students pair terms with meanings.']];
        $map = ['concepts' => [1 => ['name' => 'a'], 2 => ['name' => 'b'], 3 => ['name' => 'c'], 4 => ['name' => 'd']]];

        $compact = implode(' ', ActivityWriter::planProblems($plan, [1, 2, 3, 4], $map, 1, 3, true));
        $this->assertStringContainsString('need on-screen exercises', $compact);
        $this->assertStringNotContainsString('need on-screen exercises', implode(' ', ActivityWriter::planProblems($plan, [1, 2, 3, 4], $map, 1, 3, false)));
    }

    private function activity(array $over = []): array
    {
        return $over + [
            'objectives' => [['concept_id' => 1, 'text' => 'Students will say what a model keeps.']],
            'materials' => ['Chart paper', 'Markers'], 'setup' => null,
            'teacher_steps' => [['minutes' => 5, 'text' => 'Show a map and ask what it leaves out.'], ['minutes' => 5, 'text' => 'Ask pairs to list what a model keeps.'], ['minutes' => 5, 'text' => 'Collect the lists and compare them.']],
            'student_steps' => ['You look at the map.', 'You list what it keeps.', 'You share your list.'],
            'expected_outcomes' => ['Students say a model keeps what matters.'], 'discussion' => [], 'misconception' => null,
            'assessment' => [['criterion' => 'Names details', 'evidence' => 'Students name what a model keeps.'], ['criterion' => 'Explains why', 'evidence' => 'Students say why details are ignored.']],
            'differentiation' => null, 'reflection' => ['What does a model leave out?'], 'interaction' => null, 'bloom' => 'apply', 'dok' => 2,
        ];
    }

    private function work(): array
    {
        return ['concept_ids' => [1], 'minutes' => 15, 'format' => 'inquiry', 'concepts' => [['misconceptions' => []]]];
    }

    public function test_a_compact_activity_within_the_limits_has_no_problems(): void
    {
        $this->assertSame([], ActivityWriter::problems($this->activity(), $this->work(), null, true));
    }

    public function test_the_compact_activity_limits_are_tighter_than_the_full_ones(): void
    {
        $long = $this->activity([
            'teacher_steps' => [['minutes' => 5, 'text' => 'Show a map and ask what it leaves out.'], ['minutes' => 10, 'text' => 'Ask pairs to list what a model keeps.']],
            'discussion' => [['prompt' => 'Why keep details out?', 'answer' => 'To answer the question simply.']],
            'reflection' => ['What does a model leave out?', 'Why does it do that?'],
        ]);

        $compact = implode(' | ', ActivityWriter::problems($long, $this->work(), null, true));
        $this->assertStringContainsString('exactly 3 teacher steps', $compact);
        $this->assertStringContainsString('exactly 1 reflection prompt', $compact);
        $this->assertStringContainsString('no discussion questions', $compact);
        $this->assertSame([], ActivityWriter::problems($long, ['minutes' => 15] + $this->work(), null, false), 'the full rules allow it');
    }

    public function test_each_half_of_a_card_is_budgeted_in_wrapped_lines(): void
    {
        $content = $this->activity();
        $lines = CompactActivitiesPdfRenderer::halfLines($content, false);

        // Every paragraph starts a line: aim, materials, the "what you do" label, three steps, reflection.
        $this->assertSame(7, $lines);
        // The teacher half: its label (1), three one-line steps (3) and two things to look for, which wrap to two lines each (4).
        $this->assertSame(8, CompactActivitiesPdfRenderer::halfLines($content, true));

        $wordy = $this->activity(['student_steps' => array_fill(0, 3, str_repeat('word ', 40))]);
        $this->assertGreaterThan(CompactActivitiesPdfRenderer::HALF_LINES, CompactActivitiesPdfRenderer::halfLines($wordy, false));
        $this->assertStringContainsString('student half of the card runs to', implode(' ', ActivityWriter::problems($wordy, $this->work(), null, true)));
    }

    /** @return array<int,array<string,mixed>> */
    private function planReply(): array
    {
        return ['activities' => [
            ['title' => 'Model the map', 'format' => 'inquiry', 'grouping' => 'pair', 'minutes' => 15, 'concept_ids' => [1, 2], 'focus' => 'Students list what a model keeps and what it ignores.'],
            ['title' => 'Law or theory', 'format' => 'discussion', 'grouping' => 'group', 'minutes' => 15, 'concept_ids' => [3], 'focus' => 'Students talk about what a law and a theory say.'],
            ['title' => 'Say it back', 'format' => 'reflection', 'grouping' => 'individual', 'minutes' => 15, 'concept_ids' => [4], 'focus' => 'Students write one line on why patterns occur.'],
        ]];
    }

    /** @return array<int,array<string,mixed>> */
    private function activitiesReply(): array
    {
        $a = fn (string $id, array $concepts, string $bloom, ?array $misconception = null) => [
            'id' => $id,
            'objectives' => array_map(fn ($c) => ['concept_id' => $c, 'text' => 'Students will explain this idea in their own words.'], $concepts),
            'materials' => ['Chart paper', 'Markers'],
            'teacher_steps' => [['minutes' => 5, 'text' => 'Introduce the idea in a short talk.'], ['minutes' => 5, 'text' => 'Ask pairs to discuss the idea.'], ['minutes' => 5, 'text' => 'Collect what pairs found.']],
            'student_steps' => ['You listen to the idea.', 'You talk it over.', 'You share what you found.'],
            'expected_outcomes' => ['Students say the idea clearly.'],
            'misconception' => $misconception,
            'assessment' => [['criterion' => 'States idea', 'evidence' => 'Students state the idea correctly.'], ['criterion' => 'Gives reason', 'evidence' => 'Students give a reason for it.']],
            'reflection' => ['What did you learn today?'], 'bloom' => $bloom, 'dok' => 2,
        ];

        return ['activities' => [
            $a('a1', [1, 2], 'understand', ['wrong_idea' => 'A model is an exact copy', 'correction' => 'A model keeps only needed features.']),
            $a('a2', [3], 'remember'),
            $a('a3', [4], 'analyze'),
        ]];
    }

    public function test_the_compact_activities_cover_every_concept_and_the_quiz_joins_the_activity_that_covers_its_concept(): void
    {
        $r = $this->service([$this->planReply(), $this->activitiesReply()])->fromRaw(DocumentKind::Activities, $this->raw(), ['compact' => true, 'pages' => 5, 'measure' => $this->pagesOf(3)]);
        $d = $r['document'];

        $this->assertSame([], $r['report']['errors'], implode("\n", $r['report']['errors']));
        $this->assertSame('compact', $d['profile']);
        $this->assertSame(['Model the map', 'Law or theory', 'Say it back'], array_column(array_filter($d['sections'], fn ($s) => $s['type'] === 'activity'), 'title'));
        $this->assertSame([1 => [1], 2 => [1], 3 => [2], 4 => [3]], $d['taught_by']);

        // The bank questions are 101 (concept 1), 103 (concept 2), 104 (concept 3): each is in the activity that covers it.
        $byActivity = [];
        foreach ($d['sections'] as $s) {
            if ($s['type'] === 'activity') {
                $byActivity[$s['title']] = $s['question_ids'];
            }
        }
        $this->assertSame([101, 103], $byActivity['Model the map']);
        $this->assertSame([104], $byActivity['Law or theory']);
        $this->assertSame([], $byActivity['Say it back']);
    }

    // ---------------------------------------------------------------------------------------------------------
    // Fitting again from an earlier draft

    public function test_a_pack_can_be_fitted_again_from_an_earlier_draft_without_asking_the_model(): void
    {
        $first = $this->service([$this->unitsReply()])->fromRaw(DocumentKind::Remedial, $this->raw(), ['compact' => true, 'pages' => 5, 'measure' => $this->pagesOf(3)]);
        // As the draft.json of the bundle reads back.
        $draft = json_decode((string) json_encode($first['draft']), true);

        $completer = $this->completer([]);
        $service = StudyDocumentService::make($completer, $this->memoryStore(), $this->imageSearch(null), new ConceptContextBuilder());
        $again = $service->fromRaw(DocumentKind::Remedial, $this->raw(), ['compact' => true, 'pages' => 4, 'measure' => $this->pagesOf(3), 'draft' => $draft]);

        $this->assertSame([], $completer->prompts, 'no model call: the wording is the earlier draft');
        $this->assertTrue($again['report']['ok'], implode("
", $again['report']['errors']));
        $this->assertSame(array_column(array_slice($first['document']['sections'], 1), 'title'), array_column(array_slice($again['document']['sections'], 1), 'title'));
        $this->assertSame(
            array_map(fn ($s) => $s['content']['simple_explanation'], array_slice($first['document']['sections'], 1)),
            array_map(fn ($s) => $s['content']['simple_explanation'], array_slice($again['document']['sections'], 1)),
            'the same words'
        );
        $this->assertSame(4, $again['document']['compact']['target_pages'], 'fitted to the new target');
        $this->assertLessThan($first['document']['stats']['questions'], $again['document']['stats']['questions'], 'a shorter target holds fewer questions');
    }

    public function test_a_draft_that_does_not_cover_the_chapter_is_refused(): void
    {
        $first = $this->service([$this->unitsReply()])->fromRaw(DocumentKind::Remedial, $this->raw(), ['compact' => true, 'pages' => 5, 'measure' => $this->pagesOf(3)]);
        $draft = json_decode((string) json_encode($first['draft']), true);
        unset($draft['units'][4]);   // no unit for concept 4

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('nothing for concept(s) 4');
        $this->service([])->fromRaw(DocumentKind::Remedial, $this->raw(), ['compact' => true, 'pages' => 5, 'measure' => $this->pagesOf(3), 'draft' => $draft]);
    }

    public function test_the_run_sheet_names_every_concept_an_activity_covers(): void
    {
        [$document, $questions] = $this->pack(DocumentKind::Activities, [$this->planReply(), $this->activitiesReply()]);
        $html = CompactRevisionPdfRenderer::for($document)->html($document, '', ['variant' => 'revision', 'questions' => $questions]);

        // Activity 1 covers two concepts: both are named, not "one + 1 more".
        $this->assertStringContainsString('Models; Ignoring details', $html);
        $this->assertStringNotContainsString('more</td>', $html);
        foreach (['Models', 'Ignoring details', 'Laws', 'Theories'] as $name) {
            $this->assertStringContainsString($name, $html);
        }
    }

    // ---------------------------------------------------------------------------------------------------------
    // Where the other copy is kept

    public function test_the_other_copy_is_kept_beside_the_document_under_a_name_no_document_can_have(): void
    {
        $name = 'study_revision_notes_exploration_all_e3cc71e3c17b.pdf';
        $kept = \App\Services\ContentGenerationService::studyDocumentPracticePdfPath($name);

        $this->assertSame('public/lms_content_file/' . $name . '.practice.pdf', $kept);
        $this->assertNotSame(\App\Services\ContentGenerationService::studyDocumentPdfPath($name), $kept);
        $this->assertNotSame(\App\Services\ContentGenerationService::studyDocumentSidecarPath($name), $kept);
        $this->assertNull(DocumentKind::fromFilename(basename($kept)), 'it can never be taken for a content row\'s own file');
        $this->assertSame(DocumentKind::RevisionNotes, DocumentKind::fromFilename($name), 'while the document itself is');
    }

    // ---------------------------------------------------------------------------------------------------------
    // The PDF of each kind

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

    /** @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>} */
    private function pack(DocumentKind $kind, array $replies): array
    {
        $r = $this->service($replies)->fromRaw($kind, $this->raw(), ['compact' => true, 'pages' => 5, 'measure' => $this->pagesOf(3)]);
        $questions = [];
        foreach ($r['selection']['eligible'] as $list) {
            foreach ($list as $q) {
                $questions[$q['id']] = $q;
            }
        }

        return [$r['document'], $questions];
    }

    public function test_each_kind_is_drawn_by_the_renderer_laid_out_for_it(): void
    {
        $this->assertInstanceOf(\App\Services\StudyDeck\Documents\CompactRemedialPdfRenderer::class, CompactRevisionPdfRenderer::for(['kind' => 'remedial']));
        $this->assertInstanceOf(CompactActivitiesPdfRenderer::class, CompactRevisionPdfRenderer::for(['kind' => 'activities']));
        $this->assertSame(CompactRevisionPdfRenderer::class, get_class(CompactRevisionPdfRenderer::for(['kind' => 'revision_notes'])));

        foreach (['revision_notes', 'remedial', 'activities'] as $kind) {
            $this->assertTrue(CompactRevisionPdfRenderer::isCompact(['profile' => 'compact', 'kind' => $kind]));
        }
        $this->assertFalse(CompactRevisionPdfRenderer::isCompact(['kind' => 'remedial']));
        $this->assertFalse(CompactRevisionPdfRenderer::isCompact(['profile' => 'compact', 'kind' => 'nonsense']));
    }

    public function test_the_compact_remedial_pdf_hides_the_answers_and_paginates_alike(): void
    {
        [$document, $questions] = $this->pack(DocumentKind::Remedial, [$this->unitsReply()]);
        $renderer = fn () => CompactRevisionPdfRenderer::for($document);
        $body = fn (string $html) => preg_replace('#<style>.*?</style>#s', '', $html);

        $shown = $renderer()->html($document, '', ['variant' => 'revision', 'questions' => $questions]);
        $hidden = $renderer()->html($document, '', ['variant' => 'practice', 'questions' => $questions]);

        $this->assertStringContainsString('REMEDIAL CLASS', $shown);
        $this->assertStringContainsString('A scientific model is a simplified representation of a real system.', $hidden, 'the units are in both copies');
        $this->assertStringContainsString('A repeated pattern.', $body($shown));
        $this->assertStringNotContainsString('A repeated pattern.', $body($hidden));
        $this->assertStringNotContainsString('cx-ok', $body($hidden));
        $this->assertSame(
            CompactRevisionPdfRenderer::pageCount($this->pdf($shown)),
            CompactRevisionPdfRenderer::pageCount($this->pdf($hidden))
        );
    }

    public function test_the_two_activity_copies_differ_only_in_the_teacher_half_and_the_answers(): void
    {
        [$document, $questions] = $this->pack(DocumentKind::Activities, [$this->planReply(), $this->activitiesReply()]);
        $teacher = CompactRevisionPdfRenderer::for($document)->html($document, '', ['variant' => 'revision', 'questions' => $questions]);
        $student = CompactRevisionPdfRenderer::for($document)->html($document, '', ['variant' => 'practice', 'questions' => $questions]);

        $this->assertStringContainsString('TEACHER EDITION', $teacher);
        $this->assertStringContainsString('STUDENT HANDOUT', $student);
        $this->assertStringContainsString('Introduce the idea in a short talk.', $teacher);
        $this->assertStringNotContainsString('Introduce the idea in a short talk.', $student, 'the student handout has no teacher steps');
        $this->assertStringNotContainsString('Students state the idea correctly.', $student, 'nor what to look for');
        $this->assertStringContainsString('You listen to the idea.', $student, 'the students\' half is in both copies');
        $this->assertStringContainsString('You listen to the idea.', $teacher);
        $this->assertStringContainsString('YOUR NOTES', $student);

        $this->assertSame(
            CompactRevisionPdfRenderer::pageCount($this->pdf($teacher)),
            CompactRevisionPdfRenderer::pageCount($this->pdf($student)),
            'a fixed-height card: the notes box is as tall as the teacher half'
        );
    }
}
