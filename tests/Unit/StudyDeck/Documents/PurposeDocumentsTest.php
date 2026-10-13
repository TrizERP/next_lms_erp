<?php

namespace Tests\Unit\StudyDeck\Documents;

use App\Services\StudyDeck\ConceptContextBuilder;
use App\Services\StudyDeck\Documents\DocumentKind;
use App\Services\StudyDeck\Documents\DocumentValidator;
use App\Services\StudyDeck\Documents\GapAnalysis;
use App\Services\StudyDeck\Documents\PurposePdfRenderer;
use App\Services\StudyDeck\Documents\QuestionPlacement;
use App\Services\StudyDeck\Documents\StudyDocumentService;
use App\Services\StudyDeck\Documents\Writers\RemedialFrameWriter;
use App\Services\StudyDeck\Documents\Writers\RemedialWriter;
use App\Services\StudyDeck\Documents\Writers\TopicRevisionWriter;
use App\Services\StudyDeck\InteractionPlanner;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Tests\Unit\StudyDeck\StudyDeckFixture;

/**
 * Revision notes and a remedial class as two different documents (the PURPOSE profile).
 *
 * They used to be the same thing: a part for every concept, written from the same data, with different field names. These
 * tests hold the new design to what each document is FOR: revision notes are sheets for exam preparation and teach nothing; a
 * remedial class finds where help is needed, reteaches only the concepts the chapter's data suggests are hard, and says that is
 * all it is. They also hold the two apart: no question and no part type is shared.
 *
 * No database and no Laravel: the model is a scripted stand-in (PurposeModel), the chapter is the small fixture one with more
 * bank questions, and the PDF is checked as the markup it is drawn from.
 */
class PurposeDocumentsTest extends TestCase
{
    use StudyDeckFixture;

    // ---------------------------------------------------------------------------------------------------------
    // Helpers

    /** The fixture chapter with a bank big enough to give every stage of a class its own questions. @return array<string,mixed> */
    private function richRaw(): array
    {
        $raw = $this->raw();
        $words = ['alpha', 'bravo', 'delta', 'gamma', 'sigma', 'omega', 'kappa', 'lambda', 'theta', 'zeta', 'omicron'];
        $bloom = ['Remember', 'Understand', 'Apply', 'Analyze'];
        $difficulty = ['Easy', 'Medium', 'Hard'];
        $id = 200;
        foreach ([1, 2, 3, 4] as $concept) {
            foreach ($words as $k => $word) {
                $correct = ['A', 'B', 'C'][$k % 3];
                $raw['questions'][] = [
                    'id' => ++$id, 'concept_id' => $concept, 'question_title' => 'Which statement about the ' . $word . ' idea of concept ' . ['one', 'two', 'three', 'four'][$concept - 1] . ' is correct?',
                    'points' => 1, 'question_type' => 'multiple', 'g_bloom' => $bloom[$k % 4], 'g_difficulty' => $difficulty[$k % 3], 'g_dok' => 1 + $k % 3,
                    'answer' => json_encode(['item_form' => 'mcq', 'correct_option' => $correct, 'model_answer' => 'The ' . $word . ' statement is right because a model keeps only what the question needs.',
                        'options' => array_map(fn ($l) => ['label' => $l, 'text' => 'The ' . $word . ' choice ' . strtolower($l), 'is_correct' => $l === $correct], ['A', 'B', 'C'])]),
                ];
            }
        }

        return $raw;
    }

    private function service(PurposeModel $model): StudyDocumentService
    {
        return StudyDocumentService::make($model, $this->memoryStore(), $this->imageSearch(null), new ConceptContextBuilder());
    }

    /** @return array<string,mixed> */
    private function generate(DocumentKind $kind, ?PurposeModel $model = null, array $options = []): array
    {
        return $this->service($model ?? new PurposeModel())->fromRaw($kind, $this->richRaw(), ['per_concept' => 12] + $options);
    }

    /** The document's questions, by id, as the renderers print them. @param array<string,mixed> $r @return array<int,array<string,mixed>> */
    private function questionsOf(array $r): array
    {
        $out = [];
        foreach ($r['selection']['eligible'] as $list) {
            foreach ($list as $q) {
                $out[$q['id']] = $q;
            }
        }

        return $out;
    }

    /** @param array<string,mixed> $document @return array<int,int> */
    private function questionIds(array $document): array
    {
        return array_merge(...array_map(fn ($s) => $s['question_ids'], $document['sections']));
    }

    /** A learning map of `$n` concepts in topics of three, with data that makes some of them harder than others. @return array<string,mixed> */
    private function syntheticMap(int $n = 12): array
    {
        $concepts = [];
        $topics = [];
        for ($i = 1; $i <= $n; $i++) {
            $topic = (int) ceil($i / 3);
            $concepts[$i] = [
                'id' => $i, 'name' => 'Idea ' . $i, 'topic_id' => $topic, 'difficulty' => $i % 4 === 0 ? 'Medium' : 'Easy',
                'dok' => [$i % 3 === 0 ? 3 : 1], 'blooms' => [$i % 3 === 0 ? 'analyze' : 'remember'],
                'misconceptions' => array_fill(0, $i % 3, ['wrong_idea' => 'x']), 'requires' => $i > 1 ? [$i - 1] : [], 'required_by' => $i < $n ? [$i + 1] : [],
            ];
            $topics[$topic]['id'] = $topic;
            $topics[$topic]['concept_ids'][] = $i;
        }

        return ['concepts' => $concepts, 'topics' => array_values($topics), 'sequence' => range(1, $n)];
    }

    /** @return array<int,array<int,array<string,mixed>>> four questions for each of `$n` concepts, easy to hard */
    private function syntheticQuestions(int $n = 12, int $per = 4): array
    {
        $out = [];
        foreach (range(1, $n) as $c) {
            foreach (array_slice(['easy', 'medium', 'medium', 'hard', 'medium', 'easy'], 0, $per) as $k => $difficulty) {
                $out[$c][] = [
                    'id' => $c * 10 + $k, 'concept_id' => $c, 'form' => $k === 3 ? 'short_answer' : 'mcq', 'stem' => 'Question ' . ($c * 10 + $k) . ' about idea ' . $c . '?',
                    'options' => $k === 3 ? [] : [['label' => 'A', 'text' => 'One'], ['label' => 'B', 'text' => 'Two']], 'correct_label' => 'A',
                    'answer_text' => $k === 3 ? 'A model answer.' : '', 'explanation' => 'Because it is.', 'bloom' => ['remember', 'understand', 'apply', 'analyze', 'apply', 'remember'][$k], 'difficulty' => $difficulty, 'dok' => 1 + $k % 3,
                ];
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Which concepts a remedial class reteaches

    public function test_a_class_reteaches_about_a_sixth_of_the_chapter_never_fewer_than_four_nor_more_than_seven(): void
    {
        $this->assertSame(5, GapAnalysis::lessonCount(32));
        $this->assertSame(4, GapAnalysis::lessonCount(12), 'a class that teaches less than four concepts is not a class');
        $this->assertSame(7, GapAnalysis::lessonCount(60), 'nor is one that would no longer fit a class');
        $this->assertSame(3, GapAnalysis::lessonCount(3), 'a chapter with three concepts has three');
    }

    public function test_the_lessons_are_the_concepts_the_chapter_data_rates_hardest_and_the_rest_are_ranked_below_them(): void
    {
        $map = $this->syntheticMap();
        $a = (new GapAnalysis())->analyse($map, $map['sequence'], $this->syntheticQuestions());

        $this->assertCount(4, $a['focus']);
        $sorted = $a['focus'];
        sort($sorted);
        $this->assertSame($sorted, $a['focus'], 'the lessons are in teaching order');

        // Every lesson scores at least as high as every concept that is not one.
        $weakest = min(array_map(fn ($id) => $a['rows'][$id]['score'], $a['focus']));
        foreach (array_diff($map['sequence'], $a['focus']) as $id) {
            $this->assertLessThanOrEqual($weakest, $a['rows'][$id]['score']);
        }

        // The bands are the rank: the lessons, then the next sixth, then everything else. (The last band is the one a stale
        // ranking collapsed into the second, so it must really be there.)
        $bands = array_count_values(array_column($a['rows'], 'priority'));
        $this->assertSame(4, $bands['higher']);
        $this->assertSame(2, $bands['medium']);
        $this->assertSame(6, $bands['lower']);
        $this->assertSame($a['others'], array_keys(array_filter($a['rows'], fn ($r) => $r['priority'] === 'lower')));
    }

    public function test_no_more_than_two_lessons_come_from_one_topic_while_another_topic_has_a_candidate(): void
    {
        $map = $this->syntheticMap();
        // Make every concept of the first topic the hardest in the chapter.
        foreach ([1, 2, 3] as $id) {
            $map['concepts'][$id]['difficulty'] = 'Hard';
            $map['concepts'][$id]['misconceptions'] = array_fill(0, 3, ['wrong_idea' => 'x']);
        }
        $a = (new GapAnalysis())->analyse($map, $map['sequence'], []);

        $this->assertLessThanOrEqual(2, count(array_filter($a['focus'], fn ($id) => $map['concepts'][$id]['topic_id'] === 1)));
    }

    public function test_the_reasons_are_facts_about_the_concept_and_never_a_claim_about_learners(): void
    {
        $map = $this->syntheticMap();
        $a = (new GapAnalysis())->analyse($map, $map['sequence'], $this->syntheticQuestions());

        foreach ($a['rows'] as $row) {
            foreach ($row['reasons'] as $reason) {
                $this->assertDoesNotMatchRegularExpression('/\d/', $reason, 'a number would have to be in the chapter text');
                $this->assertDoesNotMatchRegularExpression('/\b(struggle|fail|scored|results show|your class|learners in)\b/i', $reason);
            }
        }
        $this->assertStringContainsString('potential difficulties', GapAnalysis::NOTE);
        $this->assertStringContainsString('not results from a class', GapAnalysis::NOTE);
    }

    public function test_a_class_can_be_made_shorter_by_dropping_its_lowest_ranked_lessons(): void
    {
        $map = $this->syntheticMap();
        $a = (new GapAnalysis())->analyse($map, $map['sequence'], $this->syntheticQuestions(), 5);
        $fewer = GapAnalysis::withLessons($a, 3);

        $this->assertSame(array_slice($a['focus_ranked'], 0, 3), $fewer['focus_ranked']);
        $this->assertCount(3, $fewer['focus']);
        foreach (array_diff($a['focus'], $fewer['focus']) as $dropped) {
            $this->assertSame('medium', $fewer['rows'][$dropped]['priority'], 'a dropped lesson is still an idea that may be hard');
        }
    }

    public function test_the_fit_drops_a_lesson_before_it_cuts_the_guided_practice(): void
    {
        $ladder = StudyDocumentService::remedialLadder(6);

        $first = array_search(true, array_map(fn ($r) => $r[2] < 6, $ladder), true);
        $guidedCut = array_search(true, array_map(fn ($r) => $r[1] < 2, $ladder), true);
        $this->assertSame([6, 2, 6], $ladder[0], 'most content first');
        $this->assertLessThan($guidedCut, $first, 'a lesson goes before the guided practice is cut to one question');
        $this->assertSame(4, min(array_column($ladder, 2)), 'never below four lessons');
        $this->assertSame([3, 1, 4], end($ladder));
    }

    // ---------------------------------------------------------------------------------------------------------
    // Which questions go where

    public function test_only_a_question_that_can_be_printed_whole_is_offered(): void
    {
        $q = $this->syntheticQuestions(1)[1][0];
        $this->assertTrue(QuestionPlacement::printable($q));
        $this->assertFalse(QuestionPlacement::printable(['stem' => str_repeat('word ', 90)] + $q), 'a stem that would take a page');
        $this->assertFalse(QuestionPlacement::printable(['options' => array_fill(0, 7, ['label' => 'A', 'text' => 'x'])] + $q), 'seven choices');
        $this->assertFalse(QuestionPlacement::printable(['options' => [['label' => 'A', 'text' => str_repeat('x', 130)], ['label' => 'B', 'text' => 'y']]] + $q), 'a choice that runs on');
        $this->assertFalse(QuestionPlacement::printable(['explanation' => str_repeat('x', 560)] + $q));
        $this->assertFalse(QuestionPlacement::printable(['answer_text' => str_repeat('x', 700)] + $q), 'a model answer that would take a page');
        $this->assertTrue(QuestionPlacement::printable(['answer_text' => str_repeat('x', 600)] + $q), 'a long written answer is printed (only the answers-shown copy prints it)');
    }

    public function test_the_revision_set_has_a_question_from_every_topic_before_a_second_from_any(): void
    {
        $map = $this->syntheticMap();
        $set = (new QuestionPlacement())->forCheck($this->syntheticQuestions(), $map['sequence'], $map['concepts'], 6);

        $this->assertCount(6, $set);
        $this->assertCount(4, array_unique(array_map(fn ($q) => $map['concepts'][$q['concept_id']]['topic_id'], $set)), 'six questions reach all four topics: one from each comes before a second from any');
        $this->assertSame(count($set), count(array_unique(array_column($set, 'id'))));
        $positions = array_map(fn ($q) => array_search($q['concept_id'], $map['sequence'], true), $set);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'in the chapter\'s order');
    }

    public function test_each_stage_of_a_class_has_its_own_questions_and_none_of_the_revision_notes(): void
    {
        $map = $this->syntheticMap();
        $eligible = $this->syntheticQuestions(12, 6);
        $placement = new QuestionPlacement();
        $a = (new GapAnalysis())->analyse($map, $map['sequence'], $eligible);
        $reserved = array_column($placement->forCheck($eligible, $map['sequence'], $map['concepts'], 12), 'id');
        $sets = $placement->forRemedialClass($eligible, $map['sequence'], $map['concepts'], $a['focus'], $reserved);

        $all = array_merge(array_merge(...array_values($sets['guided'])), $sets['exit'], $sets['diagnostic'], $sets['independent']);
        $ids = array_column($all, 'id');
        $this->assertSame(count($ids), count(array_unique($ids)), 'no question is used twice');
        $this->assertSame([], array_intersect($ids, $reserved), 'and none is the revision notes\'');

        $this->assertSame($a['focus'], array_keys($sets['guided']), 'every lesson has guided practice');
        foreach ($sets['guided'] as $list) {
            $difficulty = array_map(fn ($q) => ['easy' => 1, 'medium' => 2, 'hard' => 3][$q['difficulty']], $list);
            $sorted = $difficulty;
            sort($sorted);
            $this->assertSame($sorted, $difficulty, 'guided practice starts with the easiest');
        }
        $this->assertCount(4, $sets['diagnostic'], 'a question for each topic');
        $this->assertCount(4, array_unique(array_map(fn ($q) => $map['concepts'][$q['concept_id']]['topic_id'], $sets['diagnostic'])));
        $exitConcepts = array_unique(array_column($sets['exit'], 'concept_id'));
        $this->assertEqualsCanonicalizing($a['focus'], $exitConcepts, 'an exit question for each lesson');
        $this->assertGreaterThan(count($a['focus']), count($sets['exit']), 'and a second for the lessons that have one, so ready is not "all of them"');
        $this->assertLessThanOrEqual(6, count($sets['exit']));
        $this->assertNotEmpty($sets['independent']);
    }

    // ---------------------------------------------------------------------------------------------------------
    // The rules the writers repair against

    /** @return array<string,mixed> */
    private function sheet(array $over = []): array
    {
        return $over + [
            'big_idea' => 'A model keeps only what a question needs.',
            'rows' => [1 => ['essential' => 'A model is a simplified representation of a real system.', 'terms' => ['model']], 2 => ['essential' => 'A model ignores some details on purpose.', 'terms' => []]],
            'compare' => null, 'mixups' => [], 'recall' => [], 'checklist' => ['I can say what a model is.'], 'terms' => [],
        ];
    }

    /** @return array<string,mixed> */
    private function work(): array
    {
        return ['concepts' => [
            ['concept_id' => 1, 'name' => 'Models', 'key_terms' => ['model'], 'misconceptions' => [['wrong_idea' => 'A model is an exact copy', 'correction' => 'It keeps only needed features.']]],
            ['concept_id' => 2, 'name' => 'Ignoring details', 'key_terms' => [], 'misconceptions' => []],
        ]];
    }

    public function test_a_clean_revision_sheet_has_no_problems(): void
    {
        $this->assertSame([], TopicRevisionWriter::sheetProblems($this->sheet(), $this->work(), 'A model is a simplified representation. The word model matters.'));
    }

    public function test_a_revision_sheet_must_condense_not_explain(): void
    {
        $long = $this->sheet(['rows' => [1 => ['essential' => implode(' ', array_fill(0, 40, 'word')), 'terms' => []], 2 => ['essential' => 'A model ignores some details on purpose.', 'terms' => []]]]);
        $this->assertStringContainsString('has 40 words', implode(' ', TopicRevisionWriter::sheetProblems($long, $this->work())));

        $missing = $this->sheet(['rows' => [1 => ['essential' => 'A model is a simplified representation of a real system.', 'terms' => []]]]);
        $this->assertStringContainsString('no row for concept 2', implode(' ', TopicRevisionWriter::sheetProblems($missing, $this->work())));
    }

    public function test_a_comparison_table_has_a_cell_for_every_column_in_every_row(): void
    {
        $bad = $this->sheet(['compare' => ['title' => 'Kinds', 'columns' => ['A', 'B'], 'rows' => [['one', 'two'], ['three']]]]);
        $this->assertStringContainsString('exactly one cell for each column', implode(' ', TopicRevisionWriter::sheetProblems($bad, $this->work())));

        $good = $this->sheet(['compare' => ['title' => 'Kinds', 'columns' => ['A', 'B'], 'rows' => [['one', 'two'], ['three', 'four']]]]);
        $this->assertSame([], TopicRevisionWriter::sheetProblems($good, $this->work()));
    }

    public function test_a_glossary_term_is_vocabulary_not_the_title_of_a_concept_and_a_mixup_is_a_listed_misconception(): void
    {
        $title = $this->sheet(['terms' => [['concept_id' => 1, 'term' => 'Models', 'meaning' => 'A simplified representation of a real system.']]]);
        $this->assertStringContainsString('title of a concept', implode(' ', TopicRevisionWriter::sheetProblems($title, $this->work(), 'models are simplified')));

        $invented = $this->sheet(['mixups' => [['wrong_idea' => 'Atoms are made of jelly', 'correct' => 'Atoms are made of smaller particles.']]]);
        $this->assertStringContainsString('not one of the listed misconceptions', implode(' ', TopicRevisionWriter::sheetProblems($invented, $this->work())));
    }

    public function test_a_recall_point_that_only_repeats_a_row_is_not_a_recall_point(): void
    {
        $dup = $this->sheet(['recall' => ['A model is a simplified representation of a real system.']]);

        $this->assertStringContainsString('only repeats a row', implode(' ', TopicRevisionWriter::sheetProblems($dup, $this->work())));
    }

    /** @return array<string,mixed> a sheet with the keys the clean-up reads */
    private function plainSheet(array $over = []): array
    {
        return $over + ['rows' => [], 'compare' => null, 'mixups' => [], 'recall' => [], 'terms' => []];
    }

    public function test_a_glossary_meaning_that_is_a_rows_own_sentence_is_dropped_and_a_new_one_is_kept(): void
    {
        $sheets = TopicRevisionWriter::dedupe([
            1 => $this->plainSheet([
                'rows' => [1 => ['essential' => 'A law describes a regular pattern observed in nature, often expressed using words or mathematical relationships.', 'terms' => []]],
                'terms' => [
                    ['concept_id' => 1, 'term' => 'Law', 'meaning' => 'Describes a regular pattern observed in nature, often expressed using words or mathematical relationships.'],
                    ['concept_id' => 1, 'term' => 'Assumption', 'meaning' => 'A simplifying choice made on purpose so that a question stays answerable.'],
                ],
            ]),
        ]);

        $this->assertSame(['Assumption'], array_column($sheets[1]['terms'], 'term'));
    }

    public function test_a_recall_point_that_is_another_topics_row_or_an_earlier_recall_point_is_dropped(): void
    {
        $sheets = TopicRevisionWriter::dedupe([
            4 => $this->plainSheet(['recall' => [
                'Predictions are reasoned expectations based on evidence, not guesses made without careful thinking.',
                'Newton gave three laws of motion that describe how forces change the motion of a body.',
            ]]),
            5 => $this->plainSheet([
                'rows' => [7 => ['essential' => 'Predictions are not guesses; they are reasoned expectations based on evidence and careful thinking.', 'terms' => []]],
                'recall' => ['Newton gave three laws of motion that describe how forces change the motion of a body.'],
            ]),
        ]);

        $this->assertSame(['Newton gave three laws of motion that describe how forces change the motion of a body.'], $sheets[4]['recall'], 'the first mention stays, the repeat of another topic\'s row goes');
        $this->assertSame([], $sheets[5]['recall'], 'and a point already made by an earlier topic is not made again');
    }

    public function test_a_meaning_that_repeats_a_comparison_cell_is_dropped_and_the_table_and_rows_are_untouched(): void
    {
        $rows = [1 => ['essential' => 'The magnifying glass symbolises careful observation.', 'terms' => ['observation']]];
        $compare = ['title' => 'Two symbols', 'columns' => ['Symbol', 'Meaning'], 'rows' => [['Compass', 'Choosing appropriate models, asking the right questions, knowing limits of where ideas apply']]];
        $sheets = TopicRevisionWriter::dedupe([1 => $this->plainSheet([
            'rows' => $rows, 'compare' => $compare,
            'terms' => [['concept_id' => 1, 'term' => 'Direction', 'meaning' => 'Choosing appropriate models, asking the right questions, and knowing the limits of where our ideas apply.']],
        ])]);

        $this->assertSame([], $sheets[1]['terms']);
        $this->assertSame($rows, $sheets[1]['rows']);
        $this->assertSame($compare, $sheets[1]['compare']);
    }

    public function test_a_short_phrase_is_never_a_repeat_and_the_clean_up_is_idempotent(): void
    {
        $sheets = [1 => $this->plainSheet([
            'rows' => [1 => ['essential' => 'A model keeps only what the question needs.', 'terms' => []]],
            'terms' => [['concept_id' => 1, 'term' => 'Model', 'meaning' => 'A simplified view.']],
            'mixups' => [['wrong_idea' => 'A model is an exact copy', 'correct' => 'It keeps only needed features.'], ['wrong_idea' => 'A model is an exact copy', 'correct' => 'It keeps only needed features.']],
        ])];

        $once = TopicRevisionWriter::dedupe($sheets);
        $this->assertCount(1, $once[1]['terms'], 'two content words say too little to repeat anything');
        $this->assertCount(1, $once[1]['mixups'], 'the same mix-up twice is one');
        $this->assertSame($once, TopicRevisionWriter::dedupe($once));
    }

    public function test_the_revision_notes_apply_the_clean_up_to_what_the_model_wrote(): void
    {
        $model = new PurposeModel();
        $model->tweak['sheets'] = function (array $reply): array {
            // The glossary entry of the first topic is the sentence of its first row.
            $sheet = $reply['topics'][0];
            $reply['topics'][0]['terms'][0]['meaning'] = $sheet['rows'][0]['essential'];

            return $reply;
        };
        $r = $this->generate(DocumentKind::RevisionNotes, $model);
        $glossary = array_values(array_filter($r['document']['sections'], fn ($s) => $s['type'] === 'glossary'))[0]['content']['terms'];
        $rows = array_merge(...array_map(fn ($s) => array_column($s['content']['rows'], 'essential'), array_filter($r['document']['sections'], fn ($s) => $s['type'] === 'topic')));

        foreach ($glossary as $t) {
            $this->assertNotContains($t['meaning'], $rows, 'no key term is just a concept line said again');
        }
    }

    /** @return array<string,mixed> a lesson that passes the purpose rules */
    private function lesson(array $over = []): array
    {
        return $over + [
            'simple_explanation' => 'A model is a simplified view of a real system. It keeps only what a question needs so the problem stays small enough to answer.',
            'steps' => [['id' => 'i1', 'label' => 'Pick a system', 'text' => 'Choose the real system to study.'], ['id' => 'i2', 'label' => 'Keep what matters', 'text' => 'Keep only what the question needs.'], ['id' => 'i3', 'label' => 'Leave the rest', 'text' => 'Ignore the other details on purpose.']],
            'real_life' => 'Think of a map that shows roads but not trees.',
            'worked_example' => ['problem' => 'A map is used to plan a journey.', 'steps' => [['text' => 'Look at the roads.', 'why' => 'Roads decide the route.'], ['text' => 'Ignore the trees.', 'why' => 'Trees do not change the route.'], ['text' => 'Pick the shortest road.', 'why' => 'The question asks for the shortest journey.']], 'answer' => 'The map is enough to plan the journey.'],
            'mistakes' => [], 'practice' => [], 'win' => 'You can now say what a model is.',
        ];
    }

    public function test_a_lesson_must_teach_with_a_worked_example_of_its_own(): void
    {
        $work = ['misconceptions' => [], 'practice' => []];
        $this->assertSame([], RemedialWriter::problems($this->lesson(), $work, null, false, true));

        $this->assertStringContainsString('worked example is required', implode(' ', RemedialWriter::problems($this->lesson(['worked_example' => null]), $work, null, false, true)));
        $shallow = $this->lesson(['worked_example' => ['problem' => 'A map is used.', 'steps' => [['text' => 'Look.', 'why' => ''], ['text' => 'Pick.', 'why' => 'It is shortest.'], ['text' => 'Go.', 'why' => 'It is shorter overall.']], 'answer' => 'The map is enough.']]);
        $this->assertStringContainsString('needs a reason', implode(' ', RemedialWriter::problems($shallow, $work, null, false, true)));
    }

    public function test_a_lesson_is_not_a_page_of_text(): void
    {
        $work = ['misconceptions' => [], 'practice' => []];
        $steps = array_map(fn ($i) => ['id' => 'i' . $i, 'label' => 'Step ' . $i, 'text' => 'Do thing ' . $i . '.'], [1, 2, 3, 4, 5]);

        $this->assertStringContainsString('There must be 3 to 4 steps', implode(' ', RemedialWriter::problems($this->lesson(['steps' => $steps]), $work, null, false, true)));
        $this->assertStringContainsString('simple explanation has', implode(' ', RemedialWriter::problems($this->lesson(['simple_explanation' => implode(' ', array_fill(0, 80, 'word'))]), $work, null, false, true)));
    }

    public function test_the_teachers_notes_speak_of_what_may_be_seen_not_of_what_these_learners_do(): void
    {
        $this->assertTrue(RemedialFrameWriter::hedged('Some learners may find it hard to say why.'));
        $this->assertTrue(RemedialFrameWriter::hedged('If a learner skips a step, slow down.'));
        $this->assertFalse(RemedialFrameWriter::hedged('Learners struggle with this idea.'));

        $problems = implode(' ', RemedialFrameWriter::teacherProblems(['look_for' => 'Learners struggle to put the idea in their own words.', 'try_this' => 'Ask the learner to talk through the worked example one step at a time.', 'if_still_stuck' => 'Go back to the idea it builds on and try again.']));
        $this->assertStringContainsString('MAY be seen', $problems);
    }

    // ---------------------------------------------------------------------------------------------------------
    // Revision notes, end to end

    public function test_revision_notes_are_a_sheet_for_each_topic_the_key_terms_and_a_few_questions(): void
    {
        $r = $this->generate(DocumentKind::RevisionNotes);
        $d = $r['document'];

        $this->assertSame([], $r['report']['errors'], implode("\n", $r['report']['errors']));
        $this->assertSame('purpose', $d['profile']);
        $this->assertSame(['overview', 'topic', 'topic', 'glossary', 'check'], array_column($d['sections'], 'type'));
        $this->assertSame([1, 2, 3, 4], array_merge(...array_map(fn ($s) => array_column($s['content']['rows'], 'concept_id'), array_filter($d['sections'], fn ($s) => $s['type'] === 'topic'))), 'every concept has one line, in the chapter\'s order');
        $this->assertSame([1 => [1], 2 => [1], 3 => [2], 4 => [2]], $d['taught_by'], 'a concept is covered by the sheet of its topic');
        $this->assertSame([5, 15], [$d['purpose']['min_pages'], $d['purpose']['max_pages']]);
    }

    public function test_revision_notes_do_not_teach(): void
    {
        $d = $this->generate(DocumentKind::RevisionNotes)['document'];

        foreach ($d['sections'] as $s) {
            foreach (['simple_explanation', 'steps', 'worked_example', 'guided', 'prerequisites', 'follow_up'] as $lesson) {
                $this->assertArrayNotHasKey($lesson, $s['content'], 'a ' . $s['type'] . ' part carries "' . $lesson . '", which is for a lesson');
            }
        }
        foreach (array_filter($d['sections'], fn ($s) => $s['type'] === 'topic') as $sheet) {
            foreach ($sheet['content']['rows'] as $row) {
                $this->assertLessThanOrEqual(TopicRevisionWriter::ESSENTIAL_WORDS[1], str_word_count($row['essential']), 'a concept gets one short line');
            }
        }
    }

    public function test_the_key_terms_are_alphabetical_and_the_questions_are_a_small_exam_style_selection(): void
    {
        $d = $this->generate(DocumentKind::RevisionNotes)['document'];
        $terms = array_column(array_values(array_filter($d['sections'], fn ($s) => $s['type'] === 'glossary'))[0]['content']['terms'], 'term');
        $sorted = $terms;
        usort($sorted, 'strcasecmp');
        $this->assertSame($sorted, $terms);

        $check = array_values(array_filter($d['sections'], fn ($s) => $s['type'] === 'check'))[0];
        $this->assertGreaterThanOrEqual(6, count($check['question_ids']));
        $this->assertLessThanOrEqual(StudyDocumentService::REVISION_CHECK_QUESTIONS, count($check['question_ids']));
        foreach ($check['activities'] as $a) {
            $this->assertSame('bank', $a['source'], 'the model never writes a question');
        }
    }

    // ---------------------------------------------------------------------------------------------------------
    // A remedial class, end to end

    public function test_a_remedial_class_begins_by_finding_where_help_is_needed_and_ends_by_saying_what_ready_is(): void
    {
        $r = $this->generate(DocumentKind::Remedial);
        $d = $r['document'];

        $this->assertSame([], $r['report']['errors'], implode("\n", $r['report']['errors']));
        $types = array_column($d['sections'], 'type');
        $this->assertSame(['overview', 'diagnostic', 'gaps'], array_slice($types, 0, 3));
        $this->assertSame(['clinic', 'independent', 'exit', 'teacher'], array_slice($types, -4));
        $this->assertSame(['unit'], array_values(array_unique(array_slice($types, 3, -4))));

        $overview = $d['sections'][0]['content'];
        $this->assertCount(2, $overview['objectives'], 'an objective for each topic');
        $this->assertSame(array_column($d['sections'], 'title', 'n')[1], 'Where to start');
        $this->assertSame(range(1, count($d['sections']) - 1), array_column($overview['pathway'], 'n'));
    }

    public function test_the_class_says_its_difficulties_are_potential_and_explains_why_each_lesson_is_there(): void
    {
        $d = $this->generate(DocumentKind::Remedial)['document'];
        $gaps = array_values(array_filter($d['sections'], fn ($s) => $s['type'] === 'gaps'))[0];

        $this->assertSame(GapAnalysis::NOTE, $gaps['content']['note']);
        $taught = array_merge(...array_column(array_filter($d['sections'], fn ($s) => $s['type'] === 'unit'), 'taught_concept_ids'));
        foreach ($taught as $id) {
            $this->assertContains($id, array_column($gaps['content']['rows'], 'concept_id'), 'a lesson is in the table, with the reasons');
        }
        $this->assertEqualsCanonicalizing(array_keys($d['concepts']), $gaps['concept_ids'], 'every concept is addressed by the table, taught or not');
    }

    public function test_the_lessons_teach_and_the_misconceptions_are_in_the_clinic_not_the_lessons(): void
    {
        $d = $this->generate(DocumentKind::Remedial)['document'];

        foreach (array_filter($d['sections'], fn ($s) => $s['type'] === 'unit') as $unit) {
            $this->assertNotNull($unit['content']['worked_example'], 'a lesson reasons through an example');
            $this->assertSame([], $unit['content']['mistakes']);
            $this->assertGreaterThanOrEqual(3, count($unit['content']['steps']));
            $this->assertLessThanOrEqual(2, count($unit['content']['guided']));
            $this->assertSame(1, $unit['content']['guided'][0]['level'] ?? 1);
        }
        $clinic = array_values(array_filter($d['sections'], fn ($s) => $s['type'] === 'clinic'))[0];
        $this->assertSame('A model is an exact copy', $clinic['content']['items'][0]['wrong_idea'], 'the wrong idea is the chapter data\'s own words');
    }

    public function test_the_exit_check_has_a_readiness_rule_that_adds_up_and_a_way_back_to_every_unit(): void
    {
        $d = $this->generate(DocumentKind::Remedial)['document'];
        $exit = array_values(array_filter($d['sections'], fn ($s) => $s['type'] === 'exit'))[0];
        $units = array_keys(array_filter($d['sections'], fn ($s) => $s['type'] === 'unit'));

        $this->assertSame(count($exit['question_ids']), $exit['content']['total']);
        $this->assertSame((int) ceil(0.8 * $exit['content']['total']), $exit['content']['ready_at']);
        $this->assertSame($units, array_column($exit['content']['revisit'], 'n'));
        $this->assertNotEmpty($exit['content']['criteria']);
    }

    // ---------------------------------------------------------------------------------------------------------
    // The two are different documents

    public function test_the_two_documents_share_no_question_and_no_part_but_the_overview(): void
    {
        $rev = $this->generate(DocumentKind::RevisionNotes)['document'];
        $rem = $this->generate(DocumentKind::Remedial)['document'];

        $this->assertSame([], array_intersect($this->questionIds($rev), $this->questionIds($rem)), 'a question is used for one purpose');
        $this->assertSame(['overview'], array_values(array_intersect(array_unique(array_column($rev['sections'], 'type')), array_unique(array_column($rem['sections'], 'type')))));
        $this->assertGreaterThan(0, count($this->questionIds($rev)));
        $this->assertGreaterThan(0, count($this->questionIds($rem)));
    }

    // ---------------------------------------------------------------------------------------------------------
    // The validator holds each document to its purpose

    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:DocumentValidator} */
    private function validated(DocumentKind $kind): array
    {
        $r = $this->generate($kind);

        return [$r, $r['document'], new DocumentValidator(new InteractionPlanner(new PurposeModel()))];
    }

    /** @param array<string,mixed> $r @param array<string,mixed> $document */
    private function errorsOf(DocumentValidator $v, DocumentKind $kind, array $r, array $document): string
    {
        return implode("\n", $v->validate($kind, $r['context'], $r['map'], $r['scope'], $r['selection']['eligible'], $document, $r['html'], [])['errors']);
    }

    private function section(array &$d, string $type): int
    {
        foreach ($d['sections'] as $i => $s) {
            if ($s['type'] === $type) {
                return $i;
            }
        }
        $this->fail('no ' . $type);
    }

    public function test_a_remedial_class_that_lost_its_note_about_potential_difficulties_does_not_pass(): void
    {
        [$r, $d, $v] = $this->validated(DocumentKind::Remedial);
        $d['sections'][$this->section($d, 'gaps')]['content']['note'] = 'These ideas are hard for your learners.';

        $this->assertStringContainsString('standard note that they are potential', $this->errorsOf($v, DocumentKind::Remedial, $r, $d));
    }

    public function test_a_lesson_without_a_worked_example_or_with_mistakes_does_not_pass(): void
    {
        [$r, $d, $v] = $this->validated(DocumentKind::Remedial);
        $i = $this->section($d, 'unit');
        $d['sections'][$i]['content']['worked_example'] = null;
        $d['sections'][$i]['content']['mistakes'] = [['wrong_idea' => 'a', 'why_wrong' => 'b', 'correct_idea' => 'c']];

        $errors = $this->errorsOf($v, DocumentKind::Remedial, $r, $d);
        $this->assertStringContainsString('worked example is required', $errors);
        $this->assertStringContainsString('mistakes belong in the mix-ups', $errors);
    }

    public function test_a_class_that_reteaches_fewer_than_four_concepts_does_not_pass(): void
    {
        [$r, $d, $v] = $this->validated(DocumentKind::Remedial);
        // Keep two of the four lessons and renumber the parts.
        $units = array_keys(array_filter($d['sections'], fn ($s) => $s['type'] === 'unit'));
        unset($d['sections'][$units[2]], $d['sections'][$units[3]]);
        $d['sections'] = array_values($d['sections']);
        foreach ($d['sections'] as $n => $s) {
            $d['sections'][$n]['n'] = $n;
        }

        $this->assertStringContainsString('reteaches 4 to 8 concepts', $this->errorsOf($v, DocumentKind::Remedial, $r, $d));
    }

    public function test_the_teachers_notes_must_say_what_may_be_seen_and_cover_every_unit(): void
    {
        [$r, $d, $v] = $this->validated(DocumentKind::Remedial);
        $i = $this->section($d, 'teacher');
        $d['sections'][$i]['content']['interventions'][0]['look_for'] = 'Learners do not understand this idea at all.';
        array_pop($d['sections'][$i]['content']['interventions']);

        $errors = $this->errorsOf($v, DocumentKind::Remedial, $r, $d);
        $this->assertStringContainsString('MAY be seen', $errors);
        $this->assertStringContainsString('has nothing for the unit', $errors);
    }

    public function test_a_readiness_rule_that_does_not_add_up_does_not_pass(): void
    {
        [$r, $d, $v] = $this->validated(DocumentKind::Remedial);
        $d['sections'][$this->section($d, 'exit')]['content']['ready_at'] = 1;

        $this->assertStringContainsString('readiness rule does not match', $this->errorsOf($v, DocumentKind::Remedial, $r, $d));
    }

    public function test_revision_notes_with_a_concept_missing_from_its_sheet_or_a_lesson_part_do_not_pass(): void
    {
        [$r, $d, $v] = $this->validated(DocumentKind::RevisionNotes);
        $i = $this->section($d, 'topic');
        array_pop($d['sections'][$i]['content']['rows']);
        $this->assertStringContainsString('one row for every concept of the topic', $this->errorsOf($v, DocumentKind::RevisionNotes, $r, $d));

        [$r, $d, $v] = $this->validated(DocumentKind::RevisionNotes);
        $d['sections'][] = ['n' => count($d['sections']), 'type' => 'unit'] + $d['sections'][1];
        $this->assertStringContainsString('has only: overview, topic, glossary, check', $this->errorsOf($v, DocumentKind::RevisionNotes, $r, $d));
    }

    public function test_key_terms_out_of_order_do_not_pass(): void
    {
        [$r, $d, $v] = $this->validated(DocumentKind::RevisionNotes);
        $i = $this->section($d, 'glossary');
        $d['sections'][$i]['content']['terms'] = array_reverse($d['sections'][$i]['content']['terms']);

        $this->assertStringContainsString('not in alphabetical order', $this->errorsOf($v, DocumentKind::RevisionNotes, $r, $d));
    }

    // ---------------------------------------------------------------------------------------------------------
    // The page limit

    public function test_revision_notes_are_trimmed_to_their_aim_by_dropping_questions_and_say_so_when_they_cannot_be(): void
    {
        $aim = fn (int $per) => fn (array $doc) => ['revision' => $per + $doc['purpose']['check_questions'], 'practice' => $per + $doc['purpose']['check_questions']];

        $fits = $this->generate(DocumentKind::RevisionNotes, null, ['measure' => $aim(4)]);
        $this->assertSame(6, $fits['document']['purpose']['check_questions'], 'the most questions that keep it within ten pages');
        $this->assertSame(['revision' => 10, 'practice' => 10], $fits['document']['purpose']['pages']);
        $this->assertTrue($fits['report']['ok']);

        $over = $this->generate(DocumentKind::RevisionNotes, null, ['measure' => $aim(6)]);
        $this->assertTrue($over['report']['ok'], 'inside the limit of fifteen, so it passes');
        $this->assertStringContainsString('aimed at 5 to 10', implode(' ', $over['report']['warnings']));
    }

    public function test_a_document_outside_five_to_fifteen_pages_does_not_pass(): void
    {
        $short = $this->generate(DocumentKind::RevisionNotes, null, ['measure' => fn () => ['revision' => 3, 'practice' => 3]]);
        $this->assertFalse($short['report']['ok']);
        $this->assertStringContainsString('must come to 5 to 15', implode(' ', $short['report']['errors']));

    }

    /**
     * A run that gives up writes the model's raw replies to storage_path() (Laravel's helper). This test has no Laravel, so it runs
     * in its own process with a minimal container whose storage path is a temporary folder: nothing is written anywhere else.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_document_that_cannot_be_brought_within_fifteen_pages_stops_the_run(): void
    {
        Container::setInstance(new class extends Container {
            public function storagePath($path = '')
            {
                return sys_get_temp_dir() . '/purpose-test-storage' . ($path !== '' ? '/' . $path : '');
            }
        });

        try {
            $this->generate(DocumentKind::RevisionNotes, null, ['measure' => fn () => ['revision' => 40, 'practice' => 40]]);
            $this->fail('a document that breaks the page limit must not be produced');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('more than 15 pages', $e->getMessage());
            $this->assertStringContainsString('rejected-revision_notes-purpose-replies.json', $e->getMessage(), 'and the replies go to a file of their own, never over an earlier run\'s');
        }
    }

    // ---------------------------------------------------------------------------------------------------------
    // The PDF: what each copy shows

    /** @return array<int,string> the markup of the shown copy and the hidden copy */
    private function markup(array $r): array
    {
        $renderer = fn () => new PurposePdfRenderer(null, null);

        return [
            $renderer()->html($r['document'], '', ['variant' => 'revision', 'questions' => $this->questionsOf($r)]),
            $renderer()->html($r['document'], '', ['variant' => 'practice', 'questions' => $this->questionsOf($r)]),
        ];
    }

    public function test_the_hidden_copy_of_a_remedial_class_shows_no_answer_no_correction_and_no_teacher_guide(): void
    {
        // Sentences that appear nowhere else, so that "not in the hidden copy" can only mean the mix-up's own answer is left out.
        $model = new PurposeModel();
        $model->tweak['clinic'] = function (array $reply): array {
            foreach ($reply['items'] as $i => $item) {
                $reply['items'][$i]['correction'] = 'What is true here is a sentence that appears only in the mixup answer.';
                $reply['items'][$i]['check_it'] = 'Test it with a sentence that appears only in the mixup test.';
            }

            return $reply;
        };
        $r = $this->generate(DocumentKind::Remedial, $model);
        [$shown, $hidden] = $this->markup($r);
        $clinic = array_values(array_filter($r['document']['sections'], fn ($s) => $s['type'] === 'clinic'))[0]['content']['items'][0];

        $this->assertStringContainsString('FOR THE TEACHER', $shown);
        $this->assertStringContainsString($clinic['correction'], $shown);
        $this->assertStringContainsString($clinic['check_it'], $shown);
        $this->assertStringContainsString('&#10003; Correct', $shown);
        $this->assertStringContainsString('WHY THE OTHER CHOICES DO NOT FIT', $shown);
        $this->assertStringContainsString('ANSWERS SHOWN', $shown);

        $this->assertStringNotContainsString('FOR THE TEACHER', $hidden);
        $this->assertStringNotContainsString($clinic['correction'], $hidden, 'what is true is the answer to a mix-up');
        $this->assertStringNotContainsString($clinic['check_it'], $hidden);
        $this->assertStringNotContainsString('&#10003; Correct', $hidden);
        $this->assertStringNotContainsString('WHY THE OTHER CHOICES DO NOT FIT', $hidden);
        $this->assertStringContainsString($clinic['wrong_idea'], $hidden, 'the idea to judge is still there');
        $this->assertStringContainsString('YOUR REASON', $hidden);
        $this->assertStringContainsString('ANSWERS HIDDEN', $hidden);
    }

    public function test_the_hidden_copy_of_revision_notes_shows_no_answer_but_keeps_every_sheet(): void
    {
        $r = $this->generate(DocumentKind::RevisionNotes);
        [$shown, $hidden] = $this->markup($r);

        $this->assertStringContainsString('&#10003; Correct', $shown);
        $this->assertStringNotContainsString('&#10003; Correct', $hidden);
        $this->assertStringContainsString('>WHY<', $shown);
        $this->assertStringNotContainsString('>WHY<', $hidden);
        foreach (['TOPIC 1', 'TOPIC 2', 'Key terms', 'Test yourself', 'BIG IDEA'] as $needle) {
            $this->assertStringContainsString($needle, $shown);
            $this->assertStringContainsString($needle, $hidden);
        }
    }
}
