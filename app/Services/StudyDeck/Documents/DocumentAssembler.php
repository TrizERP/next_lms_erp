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
     * @return array<string,mixed> the document
     */
    public function revisionNotes(array $context, array $map, array $scope, array $draft, array $placed): array
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
        foreach ($scope as $id) {
            $c = $map['concepts'][$id];
            $note = $draft['notes'][$id];
            [$image, $interaction] = $diagrams < self::MAX_DIAGRAMS ? $this->figure($note['diagram'], $note['diagram_notes'], count($sections)) : [null, null];
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

        return $this->document(DocumentKind::RevisionNotes, $context, $map, $scope, $sections, [
            'lede' => $overview['lede'],
        ]);
    }

    // ---------------------------------------------------------------------------------------------------------
    // Remedial class

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $map
     * @param array<int,int> $scope
     * @param array{units:array<int,array<string,mixed>>} $draft
     * @param array<int,array<int,array<string,mixed>>> $placed concept_id => the bank questions chosen for it, easiest first
     * @return array<string,mixed>
     */
    public function remedial(array $context, array $map, array $scope, array $draft, array $placed): array
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
        foreach ($scope as $id) {
            $numberOf[$id] = count($sections);
            $c = $map['concepts'][$id];
            $unit = $draft['units'][$id];
            [$image, $interaction] = $diagrams < self::MAX_DIAGRAMS ? $this->figure($unit['diagram'], $unit['diagram_notes'], $numberOf[$id]) : [null, null];
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

        return $this->document(DocumentKind::Remedial, $context, $map, $scope, $sections, [
            'lede' => 'Learn ' . $context['chapter']['chapter_name'] . ' one small step at a time, at your own pace.',
        ]);
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
     * @return array<string,mixed>
     */
    public function activities(array $context, array $map, array $scope, array $draft): array
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

        return $this->document(DocumentKind::Activities, $context, $map, $scope, $sections, [
            'lede' => count($sheet) . ' activities for ' . $context['chapter']['chapter_name'] . ', about ' . $total . ' minutes in all.',
        ]);
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
