<?php

namespace App\Services\StudyDeck\Documents;

use App\Services\StudyDeck\DiagramRenderer;
use App\Services\StudyDeck\ImagePlanner;
use App\Services\StudyDeck\InteractionPlanner;

/**
 * Turns what the writers drafted into the STORED DOCUMENT: the JSON that the PDF is drawn from and the online
 * practice reads.
 *
 * Everything that is not the model's wording is decided here, by code, from the chapter's own data:
 *
 *   - the numbering of the parts and the concept -> part map, the topic outline, the concept list;
 *   - which bank questions appear where, and how each is played (the study deck's own activity spec);
 *   - drawn diagrams, and the numbered, explained parts of a diagram (hotspots) that go with them;
 *   - what to revisit after practice (from the prerequisite graph), the prerequisites a unit starts from;
 *   - the glossary and the checklist (collected from the notes, not asked for again);
 *   - the run sheet of a set of activities, and the standing text that explains how a remedial class works.
 *
 * A part ("section") has the same keys a study-deck slide has where the deck's PDF renderer and the player read them
 * (`n`, `title`, `concept_ids`, `taught_concept_ids`, `image`, `interaction`, `activities`, `question_ids`), so the
 * same figure, interaction and question code serve both, and `content` holds what is particular to the kind.
 */
class DocumentAssembler
{
    /** Most drawn diagrams one document carries: a picture earns its place or is left out. */
    public const MAX_DIAGRAMS = 8;

    /** Most drawn diagrams a COMPACT pack carries (the compact renderer draws no more than this, side by side). */
    public const COMPACT_DIAGRAMS = CompactRevisionPdfRenderer::MAX_DIAGRAMS;

    /** @var array<string,array{0:?array<string,mixed>,1:?array<string,mixed>}> figures already drawn by this assembler, so the same spec is drawn once */
    private array $drawn = [];

    /** The closing line of a diagram's parts online. Fixed (the writer is not asked for it); a PDF leaves it out. */
    public const STOCK_WRAPUP = 'You have read about every part.';

    /** What the three levels of remedial practice are called to the learner. */
    public const LEVELS = [1 => 'With a hint', 2 => 'With a little help', 3 => 'On your own'];

    public function __construct(
        private readonly ImagePlanner $images,
        private readonly InteractionPlanner $interactions,
        private readonly QuestionPlacement $placement = new QuestionPlacement(),
    ) {
    }

    // ---------------------------------------------------------------------------------------------------------
    // Revision notes

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array<int,int> $scope
     * @param array{overview:array<string,mixed>, notes:array<int,array<string,mixed>>} $draft
     * @param array<int,array<int,array<string,mixed>>> $placed concept_id => chosen bank questions
     * @param bool $compact the compact profile: at most COMPACT_DIAGRAMS pictures (the ones with most to show), and the
     *                      document says so (`profile: compact`) so it is drawn by the compact renderer
     * @return array<string,mixed> the document
     */
    public function revisionNotes(array $context, array $map, array $scope, array $draft, array $placed, bool $compact = false): array
    {
        $level = $this->level($context);
        $overview = $draft['overview'];
        $sections = [];

        $topics = [];
        foreach ($map['topics'] as $t) {
            if (array_intersect($t['concept_ids'], $scope)) {
                $topics[] = ['topic_id' => $t['id'], 'name' => $t['name'], 'gist' => $overview['topics'][$t['id']] ?? ''];
            }
        }
        $sections[] = $this->section(0, 'overview', $context['chapter']['chapter_name'], 'intro', ['content' => ['summary' => $overview['summary'], 'topics' => $topics]]);

        $diagrams = 0;
        $chosen = $compact ? $this->bestDiagrams($draft['notes'], $scope, self::COMPACT_DIAGRAMS) : [];
        foreach ($scope as $id) {
            $c = $map['concepts'][$id];
            $note = $draft['notes'][$id];
            $wanted = $compact ? isset($chosen[$id]) : $diagrams < self::MAX_DIAGRAMS;
            [$image, $interaction] = $wanted ? $this->figure($note['diagram'], $note['diagram_notes'], count($sections)) : [null, null];
            $diagrams += $image !== null ? 1 : 0;
            $questions = $placed[$id] ?? [];

            $sections[] = $this->section(count($sections), 'note', $c['name'], 'explain', [
                'topic_id' => $c['topic_id'],
                'concept_ids' => [$id],
                'taught' => [$id],
                'content' => [
                    'summary' => $note['summary'],
                    'key_points' => $note['key_points'],
                    'definition' => $note['definition'],
                    'rules' => $note['rules'],
                    'example' => $note['example'],
                    'misconception' => $note['misconception'],
                    'remember' => $note['remember'],
                    'checklist' => $note['checklist'],
                    'bloom' => self::bloomOf($c, $note['bloom']),
                    'dok' => self::dokOf($c, $note['dok']),
                    'minutes' => $note['minutes'],
                ],
                'image' => $image,
                'interaction' => $interaction,
                'questions' => $questions,
                'specs' => $this->placement->specs($questions, $map['concepts'], $level, true),
            ]);
        }

        $document = $this->document(DocumentKind::RevisionNotes, $context, $map, $scope, $sections, [
            'lede' => $overview['lede'],
        ]);

        // Said in the document itself, so the PDF, the validator and a later reader all know what it is.
        return $compact ? ['profile' => 'compact'] + $document : $document;
    }

    /**
     * The concepts whose diagram a compact pack draws: the ones with the most parts to show (at least three), at most
     * `$max`, earlier concepts first on a tie. A diagram earns a place on a few pages only if it shows something a
     * sentence does not.
     *
     * @param array<int,array<string,mixed>> $notes drafted notes by concept id
     * @param array<int,int> $scope
     * @return array<int,int> concept_id => number of parts
     */
    private function bestDiagrams(array $notes, array $scope, int $max): array
    {
        $parts = [];
        foreach ($scope as $position => $id) {
            $spec = $notes[$id]['diagram'] ?? null;
            if (!is_array($spec) || DiagramRenderer::problems($spec) !== []) {
                continue;
            }
            $n = count($spec['nodes'] ?? []) + count($spec['left']['items'] ?? []) + count($spec['right']['items'] ?? []);
            if ($n >= 3) {
                $parts[$id] = [$n, $position];
            }
        }
        uasort($parts, fn ($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_map(fn ($p) => $p[0], array_slice($parts, 0, $max, true));
    }

    // ---------------------------------------------------------------------------------------------------------
    // Remedial class

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array<int,int> $scope
     * @param array{units:array<int,array<string,mixed>>} $draft
     * @param array<int,array<int,array<string,mixed>>> $placed concept_id => the bank questions chosen for it, easiest first
     * @param bool $compact the compact profile: at most COMPACT_DIAGRAMS pictures, and the document says so (`profile: compact`)
     * @return array<string,mixed>
     */
    public function remedial(array $context, array $map, array $scope, array $draft, array $placed, bool $compact = false): array
    {
        $level = $this->level($context);
        $sections = [];

        $sections[] = $this->section(0, 'overview', $context['chapter']['chapter_name'], 'intro', [
            'content' => [
                'method' => self::method(),
                // The order the units come in is the order to take them: what each one needs comes before it.
                'focus' => array_map(fn ($id) => ['concept_id' => $id, 'name' => $map['concepts'][$id]['name'], 'difficulty' => $map['concepts'][$id]['difficulty']], $scope),
            ],
        ]);

        $numberOf = [];
        $diagrams = 0;
        $chosen = $compact ? $this->bestDiagrams($draft['units'], $scope, self::COMPACT_DIAGRAMS) : [];
        foreach ($scope as $id) {
            $numberOf[$id] = count($sections);
            $c = $map['concepts'][$id];
            $unit = $draft['units'][$id];
            $wanted = $compact ? isset($chosen[$id]) : $diagrams < self::MAX_DIAGRAMS;
            [$image, $interaction] = $wanted ? $this->figure($unit['diagram'], $unit['diagram_notes'], $numberOf[$id]) : [null, null];
            $diagrams += $image !== null ? 1 : 0;
            $questions = $placed[$id] ?? [];
            $byQuestion = array_column($unit['practice'], null, 'question_id');

            $guided = [];
            foreach (array_values($questions) as $i => $q) {
                $level3 = min($i + 1, 3);
                $guided[] = [
                    'level' => $level3,
                    'label' => self::LEVELS[$level3],
                    'question_id' => $q['id'],
                    'hint' => $byQuestion[$q['id']]['hint'] ?? '',
                    'not_options' => $byQuestion[$q['id']]['not_options'] ?? [],
                ];
            }

            $prerequisites = [];
            $refreshers = array_column($unit['prerequisites'], 'refresher', 'concept_id');
            foreach ($c['requires'] as $r) {
                if (!isset($map['concepts'][$r])) {
                    continue;
                }
                $prerequisites[] = [
                    'concept_id' => $r,
                    'name' => $map['concepts'][$r]['name'],
                    // The model's one-line refresher; failing that, the chapter's own definition of it.
                    'refresher' => $refreshers[$r] ?? (string) $map['concepts'][$r]['definition'],
                ];
            }

            $sections[] = $this->section($numberOf[$id], 'unit', $c['name'], 'explain', [
                'topic_id' => $c['topic_id'],
                'concept_ids' => array_values(array_unique(array_merge([$id], array_column($prerequisites, 'concept_id')))),
                'taught' => [$id],
                'content' => [
                    'prerequisites' => $prerequisites,
                    'simple_explanation' => $unit['simple_explanation'],
                    'steps' => $unit['steps'],
                    'real_life' => $unit['real_life'],
                    'worked_example' => $unit['worked_example'],
                    'mistakes' => $unit['mistakes'],
                    'guided' => $guided,
                    'follow_up' => [],
                    'win' => $unit['win'],
                    'bloom' => self::bloomOf($c, $unit['bloom']),
                    'dok' => self::dokOf($c, $unit['dok']),
                    'minutes' => $unit['minutes'],
                ],
                'image' => $image,
                'interaction' => $interaction,
                'questions' => $questions,
                'specs' => $this->placement->specs($questions, $map['concepts'], $level, false),
            ]);
        }

        // What to go back to if practice was hard: this unit's own steps, then the unit it builds on. Known only now
        // that every unit has its number.
        foreach ($sections as $i => $s) {
            if ($s['type'] !== 'unit') {
                continue;
            }
            $id = $s['taught_concept_ids'][0];
            $follow = [['concept_id' => $id, 'name' => $map['concepts'][$id]['name'], 'section' => $s['n'], 'why' => 'Go back over the steps in this unit.']];
            foreach ($s['content']['prerequisites'] as $p) {
                if (isset($numberOf[$p['concept_id']])) {
                    $follow[] = ['concept_id' => $p['concept_id'], 'name' => $p['name'], 'section' => $numberOf[$p['concept_id']], 'why' => 'This unit builds on it.'];
                }
            }
            $sections[$i]['content']['follow_up'] = $follow;
        }

        $document = $this->document(DocumentKind::Remedial, $context, $map, $scope, $sections, [
            'lede' => 'Learn ' . $context['chapter']['chapter_name'] . ' one small step at a time, at your own pace.',
        ]);

        return $compact ? ['profile' => 'compact'] + $document : $document;
    }

    /**
     * How a remedial class works. Standing text, the same for every chapter: it describes the method of the document,
     * not the subject, so nothing in it is a claim the chapter has to support.
     *
     * @return array<int,array{label:string,text:string}>
     */
    public static function method(): array
    {
        return [
            ['label' => 'Start from what you know', 'text' => 'Each unit begins with the ideas it builds on, so you can see what to look at again first.'],
            ['label' => 'Say it in plain words', 'text' => 'The idea is explained in short sentences and everyday words before any technical wording.'],
            ['label' => 'Take it step by step', 'text' => 'The idea is broken into small steps. Take them one at a time, in order.'],
            ['label' => 'See it worked', 'text' => 'Where the chapter has an example, it is worked through slowly, with the reason for each step.'],
            ['label' => 'Watch for the mistakes', 'text' => 'The mistakes learners often make are named, with why they do not hold and what is true instead.'],
            ['label' => 'Practise, then check yourself', 'text' => 'Practice starts with a hint and gets harder. Then you go back to the steps if you need to.'],
        ];
    }

    // ---------------------------------------------------------------------------------------------------------
    // Classroom activities

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array<int,int> $scope
     * @param array{plan:array<int,array<string,mixed>>, activities:array<string,array<string,mixed>>, questions:array<string,array<int,array<string,mixed>>>} $draft
     * @param bool $compact the compact profile: the document says so (`profile: compact`) and is drawn by the compact renderer
     * @return array<string,mixed>
     */
    public function activities(array $context, array $map, array $scope, array $draft, bool $compact = false): array
    {
        $level = $this->level($context);
        $sections = [];
        $sections[] = $this->section(0, 'overview', $context['chapter']['chapter_name'], 'intro', ['content' => ['run_sheet' => [], 'total_minutes' => 0]]);

        $total = 0;
        $sheet = [];
        foreach ($draft['plan'] as $a) {
            $written = $draft['activities'][$a['id']];
            $n = count($sections);
            $questions = $draft['questions'][$a['id']] ?? [];
            $first = $map['concepts'][$a['concept_ids'][0]];

            $interaction = null;
            if ($written['interaction'] !== null) {
                [$ok] = $this->interactions->check(
                    [$n => ['interaction' => $written['interaction'], 'reason' => 'a hands-on way to practise this idea']],
                    [$n => ['_anchors' => [], 'scenario_allowed' => false, 'slide_type' => 'activity']]
                );
                $interaction = $ok[$n]['interaction'] ?? null;
            }

            $sections[] = $this->section($n, 'activity', $a['title'], 'activity', [
                'topic_id' => $first['topic_id'],
                'concept_ids' => $a['concept_ids'],
                'taught' => $a['concept_ids'],
                'content' => [
                    'format' => $a['format'],
                    'grouping' => $a['grouping'],
                    'minutes' => $a['minutes'],
                    'focus' => $a['focus'],
                    'objectives' => $written['objectives'],
                    'materials' => $written['materials'],
                    'setup' => $written['setup'],
                    'teacher_steps' => $written['teacher_steps'],
                    'student_steps' => $written['student_steps'],
                    'expected_outcomes' => $written['expected_outcomes'],
                    'discussion' => $written['discussion'],
                    'misconception' => $written['misconception'],
                    'assessment' => $written['assessment'],
                    'differentiation' => $written['differentiation'],
                    'reflection' => $written['reflection'],
                    'bloom' => $written['bloom'],
                    'dok' => $written['dok'],
                ],
                'interaction' => $interaction,
                'questions' => $questions,
                'specs' => $this->placement->specs($questions, $map['concepts'], $level, false),
            ]);

            $total += $a['minutes'];
            $sheet[] = ['n' => $n, 'title' => $a['title'], 'format' => $a['format'], 'grouping' => $a['grouping'], 'minutes' => $a['minutes'], 'concept_ids' => $a['concept_ids']];
        }
        $sections[0]['content'] = ['run_sheet' => $sheet, 'total_minutes' => $total];

        $document = $this->document(DocumentKind::Activities, $context, $map, $scope, $sections, [
            'lede' => count($sheet) . ' activities for ' . $context['chapter']['chapter_name'] . ', about ' . $total . ' minutes in all.',
        ]);

        return $compact ? ['profile' => 'compact'] + $document : $document;
    }

    // ---------------------------------------------------------------------------------------------------------
    // The PURPOSE profile: revision notes and a remedial class that are different documents
    //
    // Revision notes are for a learner who has studied the chapter and is preparing for an examination: a SHEET for each
    // topic (every concept in one line, a table where the chapter compares things, a few facts to fix in memory), the
    // key terms in one place, and a short exam-style set of the bank's questions. A remedial class is for a learner who
    // did not follow the chapter: it begins by finding out where help is needed, reteaches only the concepts the chapter's
    // data suggests are hard, tackles the common wrong ideas, gives practice without help, and says what "ready" looks
    // like. They share the chapter's facts and nothing else: not the structure, not the wording, not the questions.

    /** The pages a purpose-based document may come to, in each copy. */
    public const PURPOSE_PAGES = [5, 15];

    /** Most drawn diagrams a purpose-based document carries. */
    public const PURPOSE_DIAGRAMS = ['revision_notes' => 4, 'remedial' => 2];

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array<int,int> $scope
     * @param array{overview:array<string,mixed>, topics:array<int,array<string,mixed>>} $draft TopicRevisionWriter::write()
     * @param array<int,array<string,mixed>> $check the bank questions of "test yourself", in teaching order
     * @return array<string,mixed> the document
     */
    public function revisionPurpose(array $context, array $map, array $scope, array $draft, array $check): array
    {
        $level = $this->level($context);
        $overview = $draft['overview'];
        $sections = [];

        $listed = [];
        foreach ($map['topics'] as $t) {
            if (array_intersect($t['concept_ids'], $scope)) {
                $listed[] = ['topic_id' => $t['id'], 'name' => $t['name'], 'gist' => $overview['topics'][$t['id']] ?? ''];
            }
        }
        $sections[] = $this->section(0, 'overview', $context['chapter']['chapter_name'], 'intro', ['content' => ['summary' => $overview['summary'], 'topics' => $listed]]);

        $chosen = $this->bestSheetDiagrams($draft['topics'], self::PURPOSE_DIAGRAMS['revision_notes']);
        $terms = [];
        foreach ($map['topics'] as $t) {
            $ids = array_values(array_intersect($t['concept_ids'], $scope));
            if ($ids === []) {
                continue;
            }
            $sheet = $draft['topics'][$t['id']];
            $n = count($sections);
            [$image, $interaction] = isset($chosen[$t['id']]) ? $this->figure($sheet['diagram'], $sheet['diagram_notes'], $n) : [null, null];

            $rows = [];
            foreach ($ids as $id) {
                $c = $map['concepts'][$id];
                $rows[] = [
                    'concept_id' => $id,
                    'name' => $c['name'],
                    'essential' => $sheet['rows'][$id]['essential'],
                    'terms' => $sheet['rows'][$id]['terms'],
                    // The thinking level of the concept, from the chapter's own data: what the HTML marks each row with.
                    'bloom' => self::bloomOf($c, 'understand'),
                    'dok' => self::dokOf($c, 2),
                ];
            }
            foreach ($sheet['terms'] as $x) {
                $terms[] = ['term' => $x['term'], 'meaning' => $x['meaning'], 'concept_id' => $x['concept_id'], 'topic_id' => (int) $t['id']];
            }

            $sections[] = $this->section($n, 'topic', $t['name'], 'explain', [
                'topic_id' => $t['id'],
                'concept_ids' => $ids,
                'taught' => $ids,
                'content' => [
                    'big_idea' => $sheet['big_idea'],
                    'rows' => $rows,
                    'compare' => $sheet['compare'],
                    'mixups' => $sheet['mixups'],
                    'recall' => $sheet['recall'],
                    'checklist' => $sheet['checklist'],
                    'minutes' => max(2, 2 * count($ids)),
                ],
                'image' => $image,
                'interaction' => $interaction,
            ]);
        }

        // One entry for a term the sheets name twice; alphabetical, as a glossary is looked up.
        $unique = [];
        foreach ($terms as $x) {
            $unique[mb_strtolower($x['term'])] ??= $x;
        }
        $terms = array_values($unique);
        usort($terms, fn ($a, $b) => strcasecmp($a['term'], $b['term']));
        if ($terms !== []) {
            $sections[] = $this->section(count($sections), 'glossary', 'Key terms', 'explain', [
                'concept_ids' => array_values(array_unique(array_column($terms, 'concept_id'))),
                'content' => ['terms' => $terms],
            ]);
        }

        if ($check !== []) {
            $sections[] = $this->section(count($sections), 'check', 'Test yourself', 'check', [
                'concept_ids' => array_values(array_unique(array_map(fn ($q) => (int) $q['concept_id'], $check))),
                'content' => [
                    'intro' => 'Answer each question from memory, then check.',
                    'note' => 'These are questions from the question bank, a few from every topic.',
                ],
                'questions' => $check,
                'specs' => $this->placement->specs($check, $map['concepts'], $level, true),
            ]);
        }

        return $this->purposeDocument(DocumentKind::RevisionNotes, $context, $map, $scope, $sections, [
            'lede' => 'Revise ' . $context['chapter']['chapter_name'] . ' before your examination: one sheet for each topic.',
        ]);
    }

    /**
     * The topics whose diagram a purpose-based pack draws: the ones with the most parts to show (at least three), at most
     * `$max`, earlier topics first on a tie.
     *
     * @param array<int,array<string,mixed>> $sheets drafted sheets by topic id
     * @return array<int,int> topic_id => number of parts
     */
    private function bestSheetDiagrams(array $sheets, int $max): array
    {
        $parts = [];
        $position = 0;
        foreach ($sheets as $id => $sheet) {
            $spec = $sheet['diagram'] ?? null;
            $position++;
            if (!is_array($spec) || DiagramRenderer::problems($spec) !== []) {
                continue;
            }
            $n = count($spec['nodes'] ?? []) + count($spec['left']['items'] ?? []) + count($spec['right']['items'] ?? []);
            if ($n >= 3) {
                $parts[$id] = [$n, $position];
            }
        }
        uasort($parts, fn ($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_map(fn ($p) => $p[0], array_slice($parts, 0, $max, true));
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array<int,int> $scope
     * @param array{units:array<int,array<string,mixed>>, frame:array{objectives:array<int,string>, clinic:array<int,array<string,string>>, teacher:array<int,array<string,string>>}} $draft
     * @param array{rows:array<int,array<string,mixed>>, focus:array<int,int>, others:array<int,int>} $analysis GapAnalysis::analyse()
     * @param array{guided:array<int,array<int,array<string,mixed>>>, exit:array<int,array<string,mixed>>, diagnostic:array<int,array<string,mixed>>, independent:array<int,array<string,mixed>>} $sets QuestionPlacement::forRemedialClass()
     * @return array<string,mixed> the document
     */
    public function remedialPurpose(array $context, array $map, array $scope, array $draft, array $analysis, array $sets): array
    {
        $level = $this->level($context);
        $focus = $analysis['focus'];
        $frame = $draft['frame'];
        $name = fn (int $id) => (string) $map['concepts'][$id]['name'];

        // The order of the parts is fixed, so every number is known before any part is built.
        $at = ['diagnostic' => 1, 'gaps' => 2];
        $unitAt = [];
        foreach (array_values($focus) as $i => $id) {
            $unitAt[$id] = 3 + $i;
        }
        $next = 3 + count($focus);
        $at += ['clinic' => $next, 'independent' => $next + 1, 'exit' => $next + 2, 'teacher' => $next + 3];

        $topicName = array_column($map['topics'], 'name', 'id');
        $sections = [];

        $objectives = [];
        foreach ($map['topics'] as $t) {
            if (isset($frame['objectives'][$t['id']])) {
                $objectives[] = ['topic_id' => $t['id'], 'name' => $t['name'], 'text' => $frame['objectives'][$t['id']]];
            }
        }
        $pathway = [
            ['n' => $at['diagnostic'], 'type' => 'diagnostic', 'title' => 'Where to start'],
            ['n' => $at['gaps'], 'type' => 'gaps', 'title' => 'Where it may be hard'],
        ];
        foreach ($unitAt as $id => $n) {
            $pathway[] = ['n' => $n, 'type' => 'unit', 'title' => $name($id)];
        }
        array_push($pathway,
            ['n' => $at['clinic'], 'type' => 'clinic', 'title' => 'Common mix-ups'],
            ['n' => $at['independent'], 'type' => 'independent', 'title' => 'On your own'],
            ['n' => $at['exit'], 'type' => 'exit', 'title' => 'Exit check'],
            ['n' => $at['teacher'], 'type' => 'teacher', 'title' => 'Teacher guide'],
        );
        $sections[] = $this->section(0, 'overview', $context['chapter']['chapter_name'], 'intro', ['content' => [
            'method' => self::purposeMethod(),
            'focus' => array_map(fn ($id) => ['concept_id' => $id, 'name' => $name($id), 'difficulty' => $map['concepts'][$id]['difficulty'] ?? null], $focus),
            'objectives' => $objectives,
            'pathway' => $pathway,
        ]]);

        // Where to start: one question for each topic; a miss points at the lessons to take first.
        $titles = [];
        foreach ($unitAt as $id => $n) {
            $titles[$n] = $name($id);
        }
        $diagnostic = [];
        foreach ($sets['diagnostic'] as $q) {
            $diagnostic[] = [
                'question_id' => (int) $q['id'],
                'concept_id' => (int) $q['concept_id'],
                'topic_id' => (int) $map['concepts'][$q['concept_id']]['topic_id'],
                'if_missed' => $this->lessonsFor((int) $q['concept_id'], $map, $unitAt, $titles),
            ];
        }
        $sections[] = $this->section($at['diagnostic'], 'diagnostic', 'Where to start', 'check', [
            'concept_ids' => array_values(array_unique(array_column($diagnostic, 'concept_id'))),
            'content' => [
                'intro' => 'Answer these questions on your own, without looking at the chapter. There is one for each topic.',
                'scoring' => 'Mark your answers. For each question you missed, the line under it says which unit to take first, or that the idea has no unit in this class. If you answered them all correctly, go straight to the practice on your own.',
                'items' => $diagnostic,
            ],
            'questions' => $sets['diagnostic'],
            'specs' => $this->placement->specs($sets['diagnostic'], $map['concepts'], $level, false),
        ]);

        // Where it may be hard: the ideas the chapter's data suggests are hard, and where each is dealt with.
        $probe = [];
        foreach ($sets['diagnostic'] as $i => $q) {
            $probe[(int) $q['concept_id']] ??= 'Check question ' . ($i + 1);
        }
        $exitAt = [];
        foreach ($sets['exit'] as $i => $q) {
            $exitAt[(int) $q['concept_id']] ??= 'Exit question ' . ($i + 1);
        }
        $tabled = [];
        foreach ($scope as $id) {
            $r = $analysis['rows'][$id];
            if ($r['priority'] === 'lower') {
                continue;
            }
            $tabled[] = [
                'concept_id' => $id,
                'name' => $r['name'],
                'topic_id' => $r['topic_id'],
                'priority' => $r['priority'],
                'reasons' => $r['reasons'],
                'check_first' => $r['check_first'],
                'covered_in' => isset($unitAt[$id]) ? 'Unit ' . $unitAt[$id] : ($probe[$id] ?? ($exitAt[$id] ?? 'Not retaught in this class')),
            ];
        }
        $sections[] = $this->section($at['gaps'], 'gaps', 'Where it may be hard', 'explain', [
            'concept_ids' => array_values($scope),
            'content' => [
                'note' => GapAnalysis::NOTE,
                'rows' => $tabled,
                'others' => array_map($name, $analysis['others']),
            ],
        ]);

        // The lessons.
        $chosen = $this->bestLessonDiagrams($draft['units'], $focus, self::PURPOSE_DIAGRAMS['remedial']);
        foreach ($unitAt as $id => $n) {
            $c = $map['concepts'][$id];
            $unit = $draft['units'][$id];
            [$image, $interaction] = isset($chosen[$id]) ? $this->figure($unit['diagram'], $unit['diagram_notes'], $n) : [null, null];
            $questions = $sets['guided'][$id] ?? [];
            $byQuestion = array_column($unit['practice'], null, 'question_id');

            $guided = [];
            foreach (array_values($questions) as $i => $q) {
                $guided[] = [
                    'level' => $i + 1,
                    'label' => self::LEVELS[min($i + 1, 3)],
                    'question_id' => (int) $q['id'],
                    'hint' => $byQuestion[$q['id']]['hint'] ?? '',
                    'not_options' => $byQuestion[$q['id']]['not_options'] ?? [],
                ];
            }

            $refreshers = array_column($unit['prerequisites'], 'refresher', 'concept_id');
            $prerequisites = [];
            foreach ($c['requires'] as $r) {
                if (isset($map['concepts'][$r])) {
                    $prerequisites[] = ['concept_id' => $r, 'name' => $name($r), 'refresher' => $refreshers[$r] ?? (string) $map['concepts'][$r]['definition']];
                }
            }
            // What to go back to if practice was hard: this unit's steps, then the unit it builds on when the class has one.
            $follow = [['concept_id' => $id, 'name' => $name($id), 'section' => $n, 'why' => 'Go back over the steps in this unit.']];
            foreach ($prerequisites as $p) {
                if (isset($unitAt[$p['concept_id']])) {
                    $follow[] = ['concept_id' => $p['concept_id'], 'name' => $p['name'], 'section' => $unitAt[$p['concept_id']], 'why' => 'This unit builds on it.'];
                }
            }

            $sections[] = $this->section($n, 'unit', $c['name'], 'explain', [
                'topic_id' => $c['topic_id'],
                'concept_ids' => array_values(array_unique(array_merge([$id], array_column($prerequisites, 'concept_id')))),
                'taught' => [$id],
                'content' => [
                    'prerequisites' => $prerequisites,
                    'simple_explanation' => $unit['simple_explanation'],
                    'steps' => $unit['steps'],
                    'real_life' => $unit['real_life'],
                    'worked_example' => $unit['worked_example'],
                    'mistakes' => [],
                    'guided' => $guided,
                    'follow_up' => $follow,
                    'win' => $unit['win'],
                    'bloom' => self::bloomOf($c, $unit['bloom']),
                    'dok' => self::dokOf($c, $unit['dok']),
                    'minutes' => $unit['minutes'],
                ],
                'image' => $image,
                'interaction' => $interaction,
                'questions' => $questions,
                'specs' => $this->placement->specs($questions, $map['concepts'], $level, false),
            ]);
        }

        // The clinic: a wrong idea the chapter's data lists, to be judged before it is corrected.
        $items = [];
        foreach ($focus as $id) {
            $text = $frame['clinic'][$id] ?? null;
            $listed = array_values($map['concepts'][$id]['misconceptions'] ?? []);
            if ($text === null || $listed === []) {
                continue;
            }
            $items[] = [
                'concept_id' => $id,
                'wrong_idea' => (string) $listed[count($listed) - 1]['wrong_idea'],
                'why_it_seems_true' => $text['why_it_seems_true'],
                'correction' => $text['correction'],
                'check_it' => $text['check_it'],
                'unit' => $unitAt[$id] ?? null,
            ];
        }
        $sections[] = $this->section($at['clinic'], 'clinic', 'Common mix-ups', 'misconception', [
            'concept_ids' => array_column($items, 'concept_id'),
            'content' => [
                'intro' => 'Each card holds an idea many learners find believable. Decide whether it is right, write why in one sentence, then read what is true.',
                'items' => $items,
            ],
        ]);

        $sections[] = $this->section($at['independent'], 'independent', 'On your own', 'check', [
            'concept_ids' => array_values(array_unique(array_map(fn ($q) => (int) $q['concept_id'], $sets['independent']))),
            'content' => ['intro' => 'No hints now. Answer each question yourself, then check.'],
            'questions' => $sets['independent'],
            'specs' => $this->placement->specs($sets['independent'], $map['concepts'], $level, false),
        ]);

        $total = count($sets['exit']);
        $criteria = [];
        foreach ($focus as $id) {
            $win = trim((string) preg_replace('/^You can now\s*/i', '', (string) $draft['units'][$id]['win']));
            if ($win !== '') {
                $criteria[] = ['text' => 'I can ' . rtrim($win, '.') . '.'];
            }
        }
        $revisit = [];
        foreach ($unitAt as $id => $n) {
            $revisit[] = ['concept_id' => $id, 'name' => $name($id), 'n' => $n];
        }
        $sections[] = $this->section($at['exit'], 'exit', 'Exit check', 'check', [
            'concept_ids' => array_values(array_unique(array_map(fn ($q) => (int) $q['concept_id'], $sets['exit']))),
            'content' => [
                'intro' => 'Answer these questions on your own to see whether the units have helped.',
                'criteria' => $criteria,
                'ready_at' => $total > 0 ? (int) ceil(0.8 * $total) : 0,
                'total' => $total,
                'revisit' => $revisit,
            ],
            'questions' => $sets['exit'],
            'specs' => $this->placement->specs($sets['exit'], $map['concepts'], $level, false),
        ]);

        // The teacher's guide: how long each part takes, how to run it, and what to look for in each unit.
        $pacing = [
            ['n' => $at['diagnostic'], 'title' => 'Where to start', 'minutes' => max(5, 2 * count($sets['diagnostic']))],
            ['n' => $at['gaps'], 'title' => 'Where it may be hard', 'minutes' => 5],
        ];
        foreach ($unitAt as $id => $n) {
            $pacing[] = ['n' => $n, 'title' => $name($id), 'minutes' => (int) $draft['units'][$id]['minutes']];
        }
        array_push($pacing,
            ['n' => $at['clinic'], 'title' => 'Common mix-ups', 'minutes' => max(5, 3 * count($items))],
            ['n' => $at['independent'], 'title' => 'On your own', 'minutes' => max(5, 3 * count($sets['independent']))],
            ['n' => $at['exit'], 'title' => 'Exit check', 'minutes' => max(5, 3 * $total)],
        );
        $interventions = [];
        foreach ($focus as $id) {
            if (isset($frame['teacher'][$id])) {
                $interventions[] = ['concept_id' => $id, 'name' => $name($id), 'n' => $unitAt[$id]] + $frame['teacher'][$id];
            }
        }
        $sections[] = $this->section($at['teacher'], 'teacher', 'Teacher guide', 'activity', [
            'concept_ids' => array_column($interventions, 'concept_id'),
            'content' => [
                'purpose' => 'This part is for the teacher and is left out of the copy that learners work from. It says how to run the class, how long each part takes, and what to look for and try in each unit.',
                'how_to_run' => self::howToRun(),
                'pacing' => $pacing,
                'total_minutes' => array_sum(array_column($pacing, 'minutes')),
                'interventions' => $interventions,
            ],
        ]);

        return $this->purposeDocument(DocumentKind::Remedial, $context, $map, $scope, $sections, [
            'lede' => 'Find where help is needed in ' . $context['chapter']['chapter_name'] . ', then rebuild those ideas one small step at a time.',
        ]);
    }

    /**
     * The units to take first if a question about a concept is missed: its own unit, then the units that build on it,
     * then the units of its topic; at most two.
     *
     * @param array<string,mixed> $map
     * @param array<int,int> $unitAt concept_id => part number of its unit
     * @param array<int,string> $titles part number => unit title
     * @return array<int,array{n:int,title:string}>
     */
    private function lessonsFor(int $conceptId, array $map, array $unitAt, array $titles): array
    {
        $found = [];
        if (isset($unitAt[$conceptId])) {
            $found[$unitAt[$conceptId]] = true;
        }
        foreach ($unitAt as $id => $n) {
            if (in_array($conceptId, $map['concepts'][$id]['requires'], true)) {
                $found[$n] = true;
            }
        }
        foreach ($unitAt as $id => $n) {
            if ((int) $map['concepts'][$id]['topic_id'] === (int) $map['concepts'][$conceptId]['topic_id']) {
                $found[$n] = true;
            }
        }

        return array_map(fn ($n) => ['n' => $n, 'title' => $titles[$n]], array_slice(array_keys($found), 0, 2));
    }

    /**
     * The lessons whose diagram a purpose-based class draws: the ones with the most steps to show, at most `$max`.
     *
     * @param array<int,array<string,mixed>> $units drafted lessons by concept id
     * @param array<int,int> $focus
     * @return array<int,int> concept_id => number of parts
     */
    private function bestLessonDiagrams(array $units, array $focus, int $max): array
    {
        $parts = [];
        foreach ($focus as $position => $id) {
            $spec = $units[$id]['diagram'] ?? null;
            if (!is_array($spec) || DiagramRenderer::problems($spec) !== []) {
                continue;
            }
            $n = count($spec['nodes'] ?? []) + count($spec['left']['items'] ?? []) + count($spec['right']['items'] ?? []);
            if ($n >= 3) {
                $parts[$id] = [$n, $position];
            }
        }
        uasort($parts, fn ($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_map(fn ($p) => $p[0], array_slice($parts, 0, $max, true));
    }

    /**
     * How a purpose-based remedial class works. Standing text, the same for every chapter: it describes the method of the
     * document, not the subject, so nothing in it is a claim the chapter has to support.
     *
     * @return array<int,array{label:string,text:string}>
     */
    public static function purposeMethod(): array
    {
        return [
            ['label' => 'Find where to start', 'text' => 'Answer the short check first. It shows which units to take before the others.'],
            ['label' => 'See where it may be hard', 'text' => 'A table lists ideas that may be hard, and why. These are possibilities worked out from the chapter\'s data, not results from your class.'],
            ['label' => 'Take the units you need', 'text' => 'Each unit rebuilds one idea from what it needs, in small steps, with a worked example and practice that starts with a hint.'],
            ['label' => 'Fix the mix-ups', 'text' => 'The common mix-ups are taken one at a time: decide whether the idea is right, then read what is true.'],
            ['label' => 'Practise, then check', 'text' => 'Practise without hints, then answer the exit check. It says what ready looks like and where to go back if you are not there yet.'],
        ];
    }

    /** @return array<int,string> */
    public static function howToRun(): array
    {
        return [
            'Begin with the short check in "Where to start". Mark it with the answers shown in this copy.',
            'Use the line under each check question to decide which units each learner takes first. A learner who answers every question correctly can go to the practice on their own.',
            'Take the units in order. After each guided question, ask the learner to say why the answer fits before you show it.',
            'Run the mix-ups aloud: let learners decide whether the idea is right before anyone reads what is true.',
            'Finish with the exit check. Use its readiness rule to decide who needs another unit.',
        ];
    }

    /**
     * The document of a purpose-based kind: the common envelope, and the marks that say which design it is and how long
     * it may be (the page counts are recorded once the PDFs have been drawn).
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array<int,int> $scope
     * @param array<int,array<string,mixed>> $sections
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function purposeDocument(DocumentKind $kind, array $context, array $map, array $scope, array $sections, array $extra): array
    {
        $document = $this->document($kind, $context, $map, $scope, $sections, $extra);

        return ['profile' => 'purpose', 'purpose' => ['min_pages' => self::PURPOSE_PAGES[0], 'max_pages' => self::PURPOSE_PAGES[1]]] + $document;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Shared

    /**
     * A section, in the keys the deck's slide uses where the shared renderers read them. `questions` and `specs`
     * (the bank rows and their play specs) are consumed here and become `question_ids` and `activities`.
     *
     * @param array<string,mixed> $o
     * @return array<string,mixed>
     */
    private function section(int $n, string $type, string $title, string $block, array $o = []): array
    {
        $questions = $o['questions'] ?? [];
        $specs = $o['specs'] ?? [];

        return [
            'n' => $n,
            'type' => $type,
            'block' => $block,
            'title' => $title,
            'topic_id' => $o['topic_id'] ?? null,
            'concept_ids' => array_values($o['concept_ids'] ?? []),
            'taught_concept_ids' => array_values($o['taught'] ?? []),
            'content' => $o['content'] ?? [],
            'interaction' => $o['interaction'] ?? null,
            'question_ids' => array_values(array_map(fn ($q) => (int) $q['id'], $questions)),
            'activities' => $specs,
            'image' => $o['image'] ?? null,
        ];
    }

    /**
     * The document around its sections.
     *
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array<int,int> $scope
     * @param array<int,array<string,mixed>> $sections
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function document(DocumentKind $kind, array $context, array $map, array $scope, array $sections, array $extra): array
    {
        $ch = $context['chapter'];

        $concepts = [];
        foreach ($map['concepts'] as $id => $c) {
            $concepts[$id] = [
                'id' => $id, 'name' => $c['name'], 'topic_id' => $c['topic_id'],
                'requires' => $c['requires'], 'related' => $c['related'], 'definition' => $c['definition'],
            ];
        }

        $outline = [];
        foreach ($map['topics'] as $t) {
            $ids = array_values(array_intersect($t['concept_ids'], $scope));
            if ($ids) {
                $outline[] = ['topic_id' => $t['id'], 'name' => $t['name'], 'concept_ids' => $ids];
            }
        }

        $taughtBy = [];
        $conceptQuestions = [];
        foreach ($sections as $s) {
            foreach ($s['taught_concept_ids'] as $id) {
                $taughtBy[$id][] = $s['n'];
            }
            foreach ($s['activities'] as $a) {
                if (($a['source'] ?? '') === 'bank' && !empty($a['concept_id'])) {
                    $conceptQuestions[(int) $a['concept_id']][] = (int) $a['question_id'];
                }
            }
        }

        $questions = array_sum(array_map(fn ($s) => count($s['question_ids']), $sections));
        $diagrams = count(array_filter($sections, fn ($s) => $s['image'] !== null));
        $interactive = count(array_filter($sections, fn ($s) => $s['interaction'] !== null));

        return [
            'version' => DocumentKind::VERSION,
            'kind' => $kind->value,
            'category' => $kind->category(),
            'chapter' => [
                'id' => (int) $ch['id'],
                'name' => $ch['chapter_name'],
                'standard_id' => (int) $ch['standard_id'],
                'subject_id' => (int) $ch['subject_id'],
                'standard_name' => $ch['standard_name'],
                'subject_name' => $ch['subject_name'],
            ],
            'chapter_id' => (int) $ch['id'],
            'title' => $ch['chapter_name'],
            'lede' => (string) ($extra['lede'] ?? ''),
            'scope' => ['all' => count($scope) === count($map['concepts']) && !array_diff(array_keys($map['concepts']), $scope), 'concept_ids' => array_values($scope)],
            'section_count' => count($sections),
            'outline' => $outline,
            'concepts' => $concepts,
            'sections' => $sections,
            'taught_by' => $taughtBy,
            'concept_questions' => $conceptQuestions,
            'stats' => [
                'sections' => count($sections),
                'concepts' => count($scope),
                'questions' => $questions,
                'diagrams' => $diagrams,
                'interactions' => $interactive,
            ],
        ];
    }

    /**
     * A drawn diagram and its numbered, explained parts, from what the writer proposed.
     *
     * The diagram is drawn by code from the spec, so every word in it is one the spec named and its alt text is true
     * by construction. Its parts become hotspots only if EVERY label has an explanation that passes the study deck's
     * own rules for one; otherwise the picture is kept without them rather than with a part nobody can read about.
     *
     * @param array<string,mixed>|null $spec
     * @param array<int,array{label:string,text:string}> $notes
     * @return array{0:?array<string,mixed>,1:?array<string,mixed>} [image, interaction]
     */
    private function figure(?array $spec, array $notes, int $n): array
    {
        if ($spec === null || DiagramRenderer::problems($spec) !== []) {
            return [null, null];
        }

        // The same drawing is made once however many times a document is put together (a compact pack is assembled
        // again for each number of questions it tries).
        $key = md5((string) json_encode([$spec, $notes]));

        return $this->drawn[$key] ??= $this->drawFigure($spec, $notes, $n);
    }

    /** @return array{0:?array<string,mixed>,1:?array<string,mixed>} */
    private function drawFigure(array $spec, array $notes, int $n): array
    {
        $image = $this->images->diagram($spec);

        $text = [];
        foreach ($notes as $p) {
            $text[mb_strtolower($p['label'])] = $p['text'];
        }
        $anchors = DiagramRenderer::anchors($spec);
        $spots = [];
        foreach ($anchors as $a) {
            if (!isset($text[mb_strtolower($a['label'])])) {
                return [$image, null];
            }
            $spots[] = ['label' => $a['label'], 'text' => $text[mb_strtolower($a['label'])]];
        }

        [$ok] = $this->interactions->check(
            [$n => [
                'interaction' => ['kind' => 'hotspots', 'intro' => 'Select each part of the diagram to read about it.', 'spots' => $spots, 'wrapup' => self::STOCK_WRAPUP],
                'reason' => 'the diagram has named parts and each is worth a sentence',
            ]],
            [$n => ['_anchors' => $anchors, 'scenario_allowed' => false, 'slide_type' => 'diagram']]
        );

        return [$image, $ok[$n]['interaction'] ?? null];
    }

    /**
     * The thinking level of a part: the highest the concept's own intelligence lists for it, so the range a document
     * spans is the range the chapter's data describes; the writer's guess only when the data lists none.
     *
     * @param array<string,mixed> $concept learning-map concept
     */
    public static function bloomOf(array $concept, string $written): string
    {
        $order = ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'];
        $listed = array_values(array_filter((array) ($concept['blooms'] ?? []), fn ($b) => in_array($b, $order, true)));
        if ($listed === []) {
            return $written;
        }
        usort($listed, fn ($a, $b) => array_search($b, $order, true) <=> array_search($a, $order, true));

        return $listed[0];
    }

    /** @param array<string,mixed> $concept learning-map concept */
    public static function dokOf(array $concept, int $written): int
    {
        $listed = array_values(array_filter(array_map('intval', (array) ($concept['dok'] ?? [])), fn ($d) => $d >= 1 && $d <= 4));

        return $listed === [] ? $written : max($listed);
    }

    /** The class as a number (Std 9 => 9), null when the standard has none. */
    private function level(array $context): ?int
    {
        return preg_match('/\d+/', (string) ($context['chapter']['standard_name'] ?? ''), $m) ? (int) $m[0] : null;
    }
}
