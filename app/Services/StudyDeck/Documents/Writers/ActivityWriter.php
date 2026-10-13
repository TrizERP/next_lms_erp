<?php

namespace App\Services\StudyDeck\Documents\Writers;

use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\Documents\CompactActivitiesPdfRenderer;
use App\Services\StudyDeck\Documents\DocumentKind;
use App\Services\StudyDeck\InteractionPlanner;

/**
 * Plans and writes classroom activities a teacher can run.
 *
 * Two steps, as the lesson deck does: first a PLAN (which activities, covering which concepts, in what format, for
 * how long, done alone, in pairs or in groups), checked mechanically and sent back if a concept is left out; then
 * the WORDING of each activity in chunks. In between, the bank questions each activity will use are chosen by code
 * (QuestionPlacement) and handed to the writer, so the quiz in an activity is always the bank's own questions with
 * their own answers, never ones the model made up.
 *
 * What the model writes for an activity is what a teacher needs to run it: learning objectives per concept,
 * materials, what the teacher does and says (with times), what students do, what to expect, questions to discuss
 * with possible answers, the misconception to surface on purpose, how to assess it, how to support and extend
 * learners, and a reflection for students. It is grounded in the chapter like everything else.
 */
class ActivityWriter extends DocumentWriter
{
    /** format => what the teacher sees on the card */
    public const FORMATS = [
        'inquiry' => 'Inquiry',
        'quiz' => 'Quiz',
        'matching' => 'Matching',
        'sequencing' => 'Sequencing',
        'problem_solving' => 'Problem solving',
        'discussion' => 'Discussion',
        'role_play' => 'Role play',
        'reflection' => 'Reflection',
    ];

    public const GROUPINGS = ['individual' => 'Individual', 'pair' => 'Pairs', 'group' => 'Small groups', 'whole_class' => 'Whole class'];

    public const MINUTES = [5, 45];

    public const MAX_ACTIVITIES = 14;

    public const MAX_CONCEPTS = 4;

    /**
     * The COMPACT profile: activities for a set that must fit a few printed pages, as cards. Fewer activities (every
     * concept still covered), no matching or sequencing (they need on-screen exercises), exactly three short steps for
     * the teacher and for the students, and no discussion, differentiation, setup or interaction. The bank's quiz
     * questions are not attached to an activity up front: they are fitted to the page count and printed after the cards.
     * The validator applies the same limits to the finished document.
     */
    public const COMPACT_ACTIVITIES_MAX = 10;

    public const COMPACT_FORMATS = ['inquiry', 'quiz', 'problem_solving', 'discussion', 'role_play', 'reflection'];

    public const COMPACT_OBJECTIVE_WORDS = 20;

    public const COMPACT_MATERIALS = 4;

    public const COMPACT_MATERIAL_WORDS = 6;

    public const COMPACT_STEP_WORDS = 12;

    public const COMPACT_OUTCOME_WORDS = 14;

    public const COMPACT_CRITERION_WORDS = 3;

    public const COMPACT_EVIDENCE_WORDS = 12;

    public const COMPACT_REFLECTION_WORDS = 14;

    public const COMPACT_MISCONCEPTION_WORDS = [10, 12];

    /** Most bank questions one activity carries, by format. */
    public const QUESTIONS = ['quiz' => 5, 'matching' => 2, 'sequencing' => 2, 'default' => 3];

    private const SYSTEM = 'You are an experienced classroom teacher and instructional designer planning inquiry-led classroom activities for one textbook chapter. '
        . 'You reply with a single JSON object and nothing else. You never add facts that are not in the chapter text.';

    public function __construct(Completer $completer, int $chunkSize = 3, private readonly ?InteractionPlanner $checker = null, private readonly bool $compact = false)
    {
        parent::__construct($completer, $chunkSize);
    }

    public function kind(): DocumentKind
    {
        return DocumentKind::Activities;
    }

    /** How many activities a chapter of this many concepts gets: enough to cover them all, not so many it becomes a workbook. @return array{0:int,1:int} */
    public static function range(int $concepts, bool $compact = false): array
    {
        $min = max(min(3, $concepts), (int) ceil($concepts / 4));
        if ($compact) {
            // Few enough to fit a few pages as cards, and still enough that every concept is in one (at most MAX_CONCEPTS each).
            return [$min, max($min, min(self::COMPACT_ACTIVITIES_MAX, (int) ceil($concepts / 3)))];
        }
        $max = min(self::MAX_ACTIVITIES, max($min, (int) ceil($concepts / 2)));

        return [$min, $max];
    }

    /**
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @param array<int,int> $scope the concepts to cover, in teaching order
     * @param callable(array<int,int>,int):array<int,array<string,mixed>> $allocate (concept ids, limit) => the bank questions that activity will use; each is used once in the document
     * @param callable(int,int):void|null $progress
     * @return array{plan:array<int,array<string,mixed>>, activities:array<string,array<string,mixed>>, questions:array<string,array<int,array<string,mixed>>>}
     */
    public function write(array $context, array $map, array $scope, callable $allocate, ?callable $progress = null): array
    {
        $plan = $this->plan($context, $map, $scope);

        $topicName = array_column($map['topics'], 'name', 'id');
        $questions = [];
        $work = [];
        foreach ($plan as $a) {
            $limit = self::QUESTIONS[$a['format']] ?? self::QUESTIONS['default'];
            // A compact set attaches no quiz to an activity: its questions are fitted to the page count afterwards.
            $questions[$a['id']] = $this->compact ? [] : $allocate($a['concept_ids'], $limit);

            $concepts = [];
            foreach ($a['concept_ids'] as $cid) {
                $c = $map['concepts'][$cid];
                $concepts[] = [
                    'concept_id' => $cid,
                    'name' => $c['name'],
                    'topic' => $topicName[$c['topic_id']] ?? '',
                    'definition' => $c['definition'],
                    'objectives' => array_slice($c['objectives'], 0, 3),
                    'knowledge' => array_slice($c['knowledge'], 0, 4),
                    'misconceptions' => array_map(fn ($m) => ['wrong_idea' => $m['wrong_idea'], 'correction' => $m['correction']], $c['misconceptions']),
                    'real_world' => array_slice($c['real_world'], 0, 2),
                    'suggested_pedagogy' => $c['pedagogy'],
                ];
            }
            $work[$a['id']] = $a + [
                'concepts' => $concepts,
                // Numbered 1, 2, 3 within the activity, never by their database id: the id means nothing to a teacher, and a model
                // that is shown one writes it into the steps ("use questions 701675, 701688").
                'bank_questions_attached' => array_map(fn ($q, $i) => ['number' => $i + 1, 'form' => $q['form'], 'question' => mb_substr(trim(strip_tags($q['stem'])), 0, 200)], $questions[$a['id']], array_keys($questions[$a['id']])),
            ];
        }

        $compact = $this->compact;
        $activities = $this->inChunks(
            $work,
            self::SYSTEM,
            $compact ? 8000 : 14000,
            'classroom activities',
            fn (array $chunk) => $compact ? $this->compactWritePrompt($context, $chunk) : $this->writePrompt($context, $chunk),
            fn (array $json, array $chunk) => $this->parse($json, $chunk),
            fn (array $activity, array $item) => self::problems($activity, $item, $this->checker, $compact),
            $progress
        );

        return ['plan' => $plan, 'activities' => $this->finalise($activities, $work), 'questions' => $questions];
    }

    // ---------------------------------------------------------------------------------------------------------
    // Stage 1: the plan

    /**
     * @param array<int,int> $scope
     * @return array<int,array{id:string,title:string,format:string,grouping:string,minutes:int,concept_ids:array<int,int>,focus:string}>
     */
    public function plan(array $context, array $map, array $scope): array
    {
        [$min, $max] = self::range(count($scope), $this->compact);
        $prompt = $this->planPrompt($context, $map, $scope, $min, $max);

        $plan = [];
        $errors = [];
        foreach ([$prompt, null, null] as $round => $first) {
            $text = $first ?? ($prompt . "\n\nYour previous plan was rejected for these reasons:\n- " . implode("\n- ", array_slice($errors, 0, 25))
                . "\n\nReturn a corrected, COMPLETE plan as strictly valid JSON. Fixing one problem must not break another, so every rule still holds: between {$min} and {$max} activities, "
                . 'every concept covered by at least one activity, at most ' . self::MAX_CONCEPTS . ' concepts in any one activity.');
            $json = $this->readJson($this->ask(self::SYSTEM, $text, 8000));
            if ($json === null) {
                $errors = ['Your reply was not valid JSON (' . json_last_error_msg() . ').'];
                continue;
            }
            $plan = $this->cleanPlan($json, $scope);
            $errors = self::planProblems($plan, $scope, $map, $min, $max, $this->compact);
            if ($errors === []) {
                return $plan;
            }
        }

        throw new \RuntimeException("The activity plan is invalid after two repair attempts:\n- " . implode("\n- ", $errors));
    }

    /**
     * @param array<int,int> $scope
     * @return array<int,array<string,mixed>>
     */
    private function cleanPlan(array $json, array $scope): array
    {
        $plan = [];
        foreach ((array) ($json['activities'] ?? []) as $i => $a) {
            if (!is_array($a)) {
                continue;
            }
            $format = strtolower(str_replace([' ', '-'], '_', $this->str($a['format'] ?? '')));
            $grouping = strtolower(str_replace([' ', '-'], '_', $this->str($a['grouping'] ?? '')));
            $grouping = ['pairs' => 'pair', 'groups' => 'group', 'small_group' => 'group', 'small_groups' => 'group', 'class' => 'whole_class', 'whole' => 'whole_class', 'solo' => 'individual'][$grouping] ?? $grouping;
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($a['concept_ids'] ?? [])), fn ($id) => in_array($id, $scope, true))));
            $plan[] = [
                'id' => 'a' . (count($plan) + 1),
                'title' => $this->str($a['title'] ?? ''),
                'format' => $format,
                'grouping' => $grouping,
                'minutes' => (int) ($a['minutes'] ?? 0),
                'concept_ids' => $ids,
                'focus' => $this->str($a['focus'] ?? ''),
            ];
        }

        return $plan;
    }

    /**
     * What is wrong with a plan. Public so the validator can hold the finished document to the same rules.
     *
     * @param array<int,array<string,mixed>> $plan
     * @param array<int,int> $scope
     * @return array<int,string>
     */
    public static function planProblems(array $plan, array $scope, array $map, int $min, int $max, bool $compact = false): array
    {
        $out = [];
        if (count($plan) < $min || count($plan) > $max) {
            $out[] = 'The plan has ' . count($plan) . ' activities; it must have between ' . $min . ' and ' . $max . '.';
        }

        $covered = [];
        $titles = [];
        foreach ($plan as $i => $a) {
            $n = $i + 1;
            if ($a['title'] === '') {
                $out[] = "Activity $n has no title.";
            } elseif (isset($titles[mb_strtolower($a['title'])])) {
                $out[] = "Activity $n repeats the title of activity {$titles[mb_strtolower($a['title'])]}.";
            } else {
                $titles[mb_strtolower($a['title'])] = $n;
            }
            if (!isset(self::FORMATS[$a['format']])) {
                $out[] = "Activity $n: format \"{$a['format']}\" is not one of " . implode(', ', array_keys(self::FORMATS)) . '.';
            } elseif ($compact && !in_array($a['format'], self::COMPACT_FORMATS, true)) {
                $out[] = "Activity $n: format \"{$a['format']}\" is not one of " . implode(', ', self::COMPACT_FORMATS) . ' (matching and sequencing need on-screen exercises a printed set does not have).';
            }
            if (!isset(self::GROUPINGS[$a['grouping']])) {
                $out[] = "Activity $n: grouping \"{$a['grouping']}\" is not one of " . implode(', ', array_keys(self::GROUPINGS)) . '.';
            }
            if ($a['minutes'] < self::MINUTES[0] || $a['minutes'] > self::MINUTES[1]) {
                $out[] = "Activity $n: minutes must be " . self::MINUTES[0] . ' to ' . self::MINUTES[1] . '.';
            }
            if ($a['concept_ids'] === [] || count($a['concept_ids']) > self::MAX_CONCEPTS) {
                $out[] = "Activity $n must cover 1 to " . self::MAX_CONCEPTS . ' concepts from the list.';
            }
            if ($a['focus'] === '') {
                $out[] = "Activity $n has no \"focus\" (what students do and learn, one sentence).";
            }
            foreach ($a['concept_ids'] as $id) {
                $covered[$id] = true;
            }
        }

        $missing = array_diff($scope, array_keys($covered));
        if ($missing) {
            $out[] = 'Concepts that no activity covers: ' . implode('; ', array_map(fn ($id) => ($map['concepts'][$id]['name'] ?? '?') . " ($id)", $missing));
        }

        // Variety is what makes a set of activities a lesson rather than one activity repeated.
        if (count($plan) >= 6) {
            $groupings = array_unique(array_column($plan, 'grouping'));
            foreach (['individual', 'pair', 'group'] as $needed) {
                if (!in_array($needed, $groupings, true)) {
                    $out[] = "No activity is done $needed; include at least one of each of individual, pair and group.";
                }
            }
            $formats = array_unique(array_column($plan, 'format'));
            if (!array_intersect($formats, ['quiz', 'matching'])) {
                $out[] = 'Include at least one quiz or matching activity.';
            }
            if (!array_intersect($formats, ['inquiry', 'problem_solving', 'role_play'])) {
                $out[] = 'Include at least one inquiry, problem_solving or role_play activity.';
            }
            if (!array_intersect($formats, ['discussion', 'reflection'])) {
                $out[] = 'Include at least one discussion or reflection activity.';
            }
        } elseif (count($plan) >= 2 && count(array_unique(array_column($plan, 'format'))) < 2) {
            $out[] = 'Use at least two different formats.';
        }

        return $out;
    }

    /** @param array<int,int> $scope */
    private function planPrompt(array $context, array $map, array $scope, int $min, int $max): string
    {
        $topicName = array_column($map['topics'], 'name', 'id');
        $concepts = [];
        foreach ($scope as $id) {
            $c = $map['concepts'][$id];
            $concepts[] = [
                'id' => $id,
                'name' => $c['name'],
                'topic' => $topicName[$c['topic_id']] ?? '',
                'definition' => $c['definition'],
                'difficulty' => $c['difficulty'],
                'bloom_levels' => $c['blooms'],
                'dok_levels' => $c['dok'],
                'suggested_pedagogy' => $c['pedagogy'],
                'has_listed_misconceptions' => $c['misconceptions'] !== [],
                'real_world' => array_slice($c['real_world'], 0, 1),
            ];
        }
        $input = json_encode([
            'chapter' => $context['chapter']['chapter_name'],
            'class' => $context['chapter']['standard_name'] . ' ' . $context['chapter']['subject_name'],
            'concepts_in_teaching_order' => $concepts,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $formats = implode(', ', $this->compact ? self::COMPACT_FORMATS : array_keys(self::FORMATS));
        $groupings = implode(', ', array_keys(self::GROUPINGS));
        $compactNote = $this->compact
            ? "\n- This set is printed on five pages as cards, so keep it small. Do NOT use the matching or sequencing formats: they need on-screen exercises a printed set does not have."
            : '';
        [$minM, $maxM] = self::MINUTES;
        $maxC = self::MAX_CONCEPTS;
        $count = count($scope);
        $ground = self::GROUND_RULES;

        return <<<PROMPT
Plan the classroom activities for the chapter below. Nothing is written yet; you are only planning.

A teacher will run these activities while or after teaching the chapter. Each is inquiry-led and has every student doing something: investigating, matching, ordering, solving, discussing, acting or reflecting. Each covers one concept, or a few that belong together. Choose what suits the concepts and what the chapter really contains. An activity may not need equipment, places, people or facts the chapter text does not mention.

Rules
{$ground}
- Between {$min} and {$max} activities. There are {$count} concepts and EVERY one must be covered by at least one activity (list its id in "concept_ids"). An activity covers 1 to {$maxC} concepts.
- "format" is one of: {$formats}.
  inquiry = students investigate a question (predict, do or observe, explain); quiz = a teacher-run quiz round; matching = pair terms with their meanings; sequencing = put a real process in order; problem_solving = use the chapter's ideas on a real-world problem; discussion = structured talk such as think-pair-share; role_play = students act out roles drawn from the chapter; reflection = an exit ticket or journal.
  Matching needs real terms the chapter defines. Sequencing needs a real sequence the chapter describes.
- "grouping" is one of: {$groupings}.
- Vary both. With six or more activities, include at least one individual, one pair and one group activity; at least one quiz or matching; at least one inquiry, problem_solving or role_play; and at least one discussion or reflection.
- "minutes": whole minutes, {$minM} to {$maxM}, honest for a real class.
- "title": short, sentence case, different from every other title. "focus": one sentence saying what students do and what they learn.{$compactNote}

Reply with:
{"activities": [{"title": "", "format": "inquiry", "grouping": "pair", "minutes": 15, "concept_ids": [0], "focus": ""}]}

CHAPTER AND CONCEPTS
{$input}

{$this->chapterBlock($context)}
PROMPT;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Stage 2: the wording

    /**
     * What is wrong with one written activity, as instructions to the model. The validator applies the same rules.
     *
     * @param array<string,mixed> $activity a cleaned activity
     * @param array<string,mixed> $work the planned activity it was written for
     * @param bool $compact the compact profile (a set that must fit a few pages): tighter limits, see COMPACT_*
     * @return array<int,string>
     */
    public static function problems(array $activity, array $work, ?InteractionPlanner $checker = null, bool $compact = false): array
    {
        $out = [];

        $have = array_column($activity['objectives'], 'concept_id');
        foreach ($work['concept_ids'] as $cid) {
            if (!in_array($cid, $have, true)) {
                $out[] = 'Write a learning objective (starting "Students will") for concept ' . $cid . '.';
            }
        }
        foreach ($activity['objectives'] as $o) {
            if (!preg_match('/^Students will\b/i', $o['text']) || self::words($o['text']) > 35) {
                $out[] = 'An objective must start "Students will" and be one sentence of at most 35 words.';
                break;
            }
        }

        if ($activity['materials'] === []) {
            $out[] = 'List the materials, or write "No materials needed".';
        }

        $steps = $activity['teacher_steps'];
        if (count($steps) < 2 || count($steps) > 7) {
            $out[] = 'There must be 2 to 7 teacher steps; there are ' . count($steps) . '.';
        }
        foreach ($steps as $s) {
            if (self::words($s['text']) < 4 || self::words($s['text']) > 55) {
                $out[] = 'A teacher step must be 4 to 55 words.';
                break;
            }
        }
        $total = array_sum(array_column($steps, 'minutes'));
        $planned = (int) ($work['minutes'] ?? 0);
        if ($planned > 0 && $steps !== [] && ($total < $planned * 0.6 || $total > $planned * 1.4)) {
            $out[] = "The teacher steps add up to $total minutes but the activity is $planned; make the step times add up to about $planned.";
        }

        if (count($activity['student_steps']) < 2 || count($activity['student_steps']) > 7) {
            $out[] = 'There must be 2 to 7 student steps, written to the student ("You ...").';
        }
        if ($activity['expected_outcomes'] === []) {
            $out[] = 'Say what the teacher should expect to see or hear (1 to 5 expected outcomes).';
        }
        if (count($activity['assessment']) < 2 || count($activity['assessment']) > 4) {
            $out[] = 'Give 2 to 4 assessment criteria, each {"criterion", "evidence"} that can be observed.';
        }
        if ($activity['reflection'] === []) {
            $out[] = 'Give 1 to 3 reflection prompts for students.';
        }

        $listed = [];
        foreach ($work['concepts'] as $c) {
            foreach ($c['misconceptions'] as $m) {
                $listed[] = $m['wrong_idea'];
            }
        }
        if ($listed !== [] && $activity['misconception'] === null) {
            $out[] = 'These concepts have listed misconceptions; choose one to surface deliberately and give it as "misconception".';
        }
        if ($activity['misconception'] !== null && !self::traces($activity['misconception']['wrong_idea'], $listed)) {
            $out[] = 'The misconception is not one of the listed ones; use a listed misconception (same idea) or null.';
        }

        foreach ($activity['discussion'] as $d) {
            if (self::words($d['prompt']) < 3 || self::words($d['answer']) < 3) {
                $out[] = 'A discussion question needs a prompt and a possible answer of at least 3 words each.';
                break;
            }
        }

        $kind = $activity['interaction']['kind'] ?? null;
        $needs = ['matching' => 'match', 'sequencing' => 'order'][$work['format']] ?? null;
        if ($needs !== null && $kind !== $needs) {
            $out[] = 'A ' . $work['format'] . ' activity needs an "interaction" of kind "' . $needs . '".';
        }
        if ($kind !== null && $checker !== null) {
            [, $bad] = $checker->check(
                [0 => ['interaction' => $activity['interaction'], 'reason' => 'a hands-on way to practise this idea']],
                [0 => ['_anchors' => [], 'scenario_allowed' => false, 'slide_type' => 'activity']]
            );
            foreach ($bad[0] ?? [] as $issue) {
                $out[] = 'Interaction: ' . $issue;
            }
        }

        return $compact ? array_merge($out, self::compactProblems($activity)) : $out;
    }

    /**
     * What is wrong with a COMPACT activity beyond the usual rules: every limit of the profile, and the text of each half
     * of its card against the box it is printed in.
     *
     * @param array<string,mixed> $a a cleaned activity
     * @return array<int,string>
     */
    private static function compactProblems(array $a): array
    {
        $out = [];
        $words = fn (array $list) => array_map(fn ($x) => self::words((string) $x), $list);

        if (isset($a['objectives'][0]) && self::words($a['objectives'][0]['text']) > self::COMPACT_OBJECTIVE_WORDS + 2) {
            $out[] = 'The first objective is over ' . self::COMPACT_OBJECTIVE_WORDS . ' words; it is the one printed on the card, so make it the main aim in one short sentence.';
        }
        if (count($a['materials']) > self::COMPACT_MATERIALS || max([0, ...$words($a['materials'])]) > self::COMPACT_MATERIAL_WORDS) {
            $out[] = 'List at most ' . self::COMPACT_MATERIALS . ' materials of at most ' . self::COMPACT_MATERIAL_WORDS . ' words each.';
        }
        if (count($a['teacher_steps']) !== 3 || max([0, ...$words(array_column($a['teacher_steps'], 'text'))]) > self::COMPACT_STEP_WORDS) {
            $out[] = 'Write exactly 3 teacher steps of at most ' . self::COMPACT_STEP_WORDS . ' words each.';
        }
        if (count($a['student_steps']) !== 3 || max([0, ...$words($a['student_steps'])]) > self::COMPACT_STEP_WORDS) {
            $out[] = 'Write exactly 3 student steps of at most ' . self::COMPACT_STEP_WORDS . ' words each.';
        }
        if (count($a['expected_outcomes']) > 2 || max([0, ...$words($a['expected_outcomes'])]) > self::COMPACT_OUTCOME_WORDS) {
            $out[] = 'Give 1 or 2 expected outcomes of at most ' . self::COMPACT_OUTCOME_WORDS . ' words each.';
        }
        if (count($a['assessment']) !== 2
            || max([0, ...$words(array_column($a['assessment'], 'criterion'))]) > self::COMPACT_CRITERION_WORDS
            || max([0, ...$words(array_column($a['assessment'], 'evidence'))]) > self::COMPACT_EVIDENCE_WORDS) {
            $out[] = 'Give exactly 2 assessment criteria: a criterion of at most ' . self::COMPACT_CRITERION_WORDS . ' words and evidence of at most ' . self::COMPACT_EVIDENCE_WORDS . '.';
        }
        if (count($a['reflection']) !== 1 || max([0, ...$words($a['reflection'])]) > self::COMPACT_REFLECTION_WORDS) {
            $out[] = 'Give exactly 1 reflection prompt of at most ' . self::COMPACT_REFLECTION_WORDS . ' words.';
        }
        if ($a['misconception'] !== null
            && (self::words($a['misconception']['wrong_idea']) > self::COMPACT_MISCONCEPTION_WORDS[0] || self::words($a['misconception']['correction']) > self::COMPACT_MISCONCEPTION_WORDS[1])) {
            $out[] = 'The misconception is at most ' . self::COMPACT_MISCONCEPTION_WORDS[0] . ' words and its correction at most ' . self::COMPACT_MISCONCEPTION_WORDS[1] . '.';
        }
        if ($a['discussion'] !== [] || ($a['interaction'] ?? null) !== null) {
            $out[] = 'A compact activity has no discussion questions and no interaction.';
        }

        foreach ([false => 'student', true => 'teacher'] as $teacher => $name) {
            $lines = CompactActivitiesPdfRenderer::halfLines($a, (bool) $teacher);
            if ($lines > CompactActivitiesPdfRenderer::HALF_LINES) {
                $out[] = 'The ' . $name . ' half of the card runs to ' . $lines . ' lines; its box holds ' . CompactActivitiesPdfRenderer::HALF_LINES . '. Shorten the ' . ($teacher ? 'teacher steps, criteria and misconception' : 'aim, materials, student steps and reflection') . '.';
            }
        }

        return $out;
    }

    /** @param array<string,array<string,mixed>> $chunk work items by activity id */
    private function writePrompt(array $context, array $chunk): string
    {
        $input = json_encode(array_values($chunk), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ground = self::GROUND_RULES;

        return <<<PROMPT
Write the classroom activities listed under ACTIVITIES TO WRITE, one complete activity each, exactly as planned: keep each one's format, grouping, minutes and concepts. A teacher must be able to run each without further interpretation, so be specific and short.

Rules
{$ground}
- "objectives": one for EVERY concept the activity covers, {"concept_id": id, "text": "Students will ..."}, one sentence each.
- "materials": at most 7, what is needed, from common classroom items or things the chapter mentions: ["..."]. Write ["No materials needed"] if none.
- "setup": one sentence on how to arrange the room or groups, or null.
- "teacher_steps": 2 to 7 steps in order, each {"minutes": whole minutes, "text": "what the teacher does or says"}. The minutes add up to about the planned minutes.
- "student_steps": 2 to 7 steps written to the student ("You ..."), in order.
- "expected_outcomes": 1 to 6 things the teacher should see or hear when it goes well, consistent with the chapter text.
- "discussion": 0 to 3 questions to talk over, each {"prompt": "...", "answer": "a possible answer for the teacher"}.
- "misconception": the one misconception to surface on purpose, ONLY from the listed misconceptions of the concepts it covers: {"wrong_idea": "...", "correction": "..."}; null if none are listed.
- "assessment": 2 to 4 criteria a teacher can observe, each {"criterion": "short name", "evidence": "what a student says or does that shows it"}.
- "differentiation": {"support": "one way to help a learner who is stuck", "extension": "one way to stretch a learner who is ready"}.
- "reflection": 1 to 3 prompts for the students to answer at the end.
- "interaction": for format "matching" give {"kind": "match", "intro": "one short instruction", "pairs": [{"term": "1 to 6 words", "meaning": "4 to 20 words"}], "wrapup": ""} with 3 to 5 pairs of terms the chapter defines; for "sequencing" give {"kind": "order", "intro": "...", "items": [{"text": "2 to 14 words"}], "wrapup": ""} with 3 to 5 steps of a real sequence IN THE CORRECT ORDER. For other formats it is null, or a {"kind": "reveal" | "steps" | "compare", "intro": "...", "items": [{"label": "1 to 8 words", "text": "5 to 35 words"}], "wrapup": ""} when it helps (compare needs 2 or 3 items and a "wrapup" saying how they compare).
- "bank_questions_attached" are the quiz questions this activity will use, already chosen and printed with the activity: tell the teacher WHEN in the activity to use them (in a teacher step), calling them "the quiz questions" (never by a number or an id), but do not rewrite, answer or add to them.
- "bloom" is one of remember, understand, apply, analyze, evaluate, create. "dok" is 1 to 4.

Reply with:
{"activities": [{"id": "a1", "objectives": [{"concept_id": 0, "text": "Students will ..."}], "materials": [""], "setup": null, "teacher_steps": [{"minutes": 3, "text": ""}], "student_steps": [""], "expected_outcomes": [""], "discussion": [{"prompt": "", "answer": ""}], "misconception": null, "assessment": [{"criterion": "", "evidence": ""}], "differentiation": {"support": "", "extension": ""}, "reflection": [""], "interaction": null, "bloom": "apply", "dok": 2}]}

ACTIVITIES TO WRITE
{$input}

{$this->chapterBlock($context)}
PROMPT;
    }

    /**
     * @param array<string,mixed> $json
     * @param array<string,array<string,mixed>> $chunk
     * @return array<string,array<string,mixed>>
     */
    private function parse(array $json, array $chunk): array
    {
        $out = [];
        foreach ((array) ($json['activities'] ?? []) as $a) {
            $id = (string) ($a['id'] ?? '');
            if (!is_array($a) || !isset($chunk[$id])) {
                continue;
            }
            $activity = $this->clean($a, $chunk[$id]);
            $out[$id] = $this->compact ? $this->compactOnly($activity) : $activity;
        }

        return $out;
    }

    /**
     * A compact activity has no setup, discussion, differentiation or interaction, and at most the steps, outcomes,
     * criteria and prompts the card prints. Whatever the model sends beyond that is dropped here.
     *
     * @param array<string,mixed> $a a cleaned activity
     * @return array<string,mixed>
     */
    private function compactOnly(array $a): array
    {
        $a['setup'] = null;
        $a['discussion'] = [];
        $a['differentiation'] = null;
        $a['interaction'] = null;
        $a['materials'] = array_slice($a['materials'], 0, self::COMPACT_MATERIALS);
        $a['teacher_steps'] = array_slice($a['teacher_steps'], 0, 3);
        $a['student_steps'] = array_slice($a['student_steps'], 0, 3);
        $a['expected_outcomes'] = array_slice($a['expected_outcomes'], 0, 2);
        $a['assessment'] = array_slice($a['assessment'], 0, 2);
        $a['reflection'] = array_slice($a['reflection'], 0, 1);

        return $a;
    }

    /**
     * The compact profile's prompt: the same structured fields as a full activity, tighter, and fewer of them.
     *
     * @param array<string,array<string,mixed>> $chunk work items by activity id
     */
    private function compactWritePrompt(array $context, array $chunk): string
    {
        $input = json_encode(array_values($chunk), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ground = self::GROUND_RULES;
        $obj = self::COMPACT_OBJECTIVE_WORDS;
        $mat = self::COMPACT_MATERIALS;
        $matW = self::COMPACT_MATERIAL_WORDS;
        $step = self::COMPACT_STEP_WORDS;
        $out = self::COMPACT_OUTCOME_WORDS;
        $crit = self::COMPACT_CRITERION_WORDS;
        $evid = self::COMPACT_EVIDENCE_WORDS;
        $refl = self::COMPACT_REFLECTION_WORDS;
        [$misW, $corW] = self::COMPACT_MISCONCEPTION_WORDS;

        return <<<PROMPT
Write the COMPACT classroom activities listed under ACTIVITIES TO WRITE, one each, exactly as planned: keep each one's format, grouping, minutes and concepts. The whole set is printed on five pages as cards, so every field is short, and a teacher must still be able to run each activity without further interpretation.

Rules
{$ground}
- "objectives": one for EVERY concept the activity covers, {"concept_id": id, "text": "Students will ..."}, one sentence each, at most {$obj} words. The card prints the FIRST one, so make it the activity's main aim.
- "materials": at most {$mat} short items (at most {$matW} words each), from common classroom items or things the chapter mentions: ["..."]. Write ["No materials needed"] if none.
- "teacher_steps": EXACTLY 3 steps in order, each {"minutes": whole minutes, "text": "what the teacher does or says, at most {$step} words"}. The minutes add up to about the planned minutes.
- "student_steps": EXACTLY 3 steps written to the student ("You ..."), in order, at most {$step} words each.
- "expected_outcomes": 1 or 2 things the teacher should see or hear when it goes well, at most {$out} words each, consistent with the chapter text.
- "misconception": the one misconception to surface on purpose, ONLY from the listed misconceptions of the concepts it covers: {"wrong_idea": "at most {$misW} words", "correction": "at most {$corW} words"}; null if none are listed.
- "assessment": EXACTLY 2 criteria a teacher can observe, each {"criterion": "at most {$crit} words", "evidence": "what a student says or does that shows it, at most {$evid} words"}.
- "reflection": EXACTLY 1 prompt for the students to answer at the end, at most {$refl} words.
- "bloom" is one of remember, understand, apply, analyze, evaluate, create: the thinking level the activity asks of students. Across the whole set use at least three different levels, so vary them. "dok" is 1 to 4.
- Do not write setup, discussion questions, differentiation or an interaction: this set does not print them.

Reply with:
{"activities": [{"id": "a1", "objectives": [{"concept_id": 0, "text": "Students will ..."}], "materials": [""], "teacher_steps": [{"minutes": 3, "text": ""}], "student_steps": [""], "expected_outcomes": [""], "misconception": null, "assessment": [{"criterion": "", "evidence": ""}], "reflection": [""], "bloom": "apply", "dok": 2}]}

ACTIVITIES TO WRITE
{$input}

{$this->chapterBlock($context)}
PROMPT;
    }

    /**
     * @param array<string,mixed> $a
     * @param array<string,mixed> $work
     * @return array<string,mixed>
     */
    private function clean(array $a, array $work): array
    {
        $objectives = [];
        foreach ((array) ($a['objectives'] ?? []) as $o) {
            $id = (int) ($o['concept_id'] ?? 0);
            if (is_array($o) && in_array($id, $work['concept_ids'], true) && $this->str($o['text'] ?? '') !== '') {
                $objectives[$id] = ['concept_id' => $id, 'text' => $this->str($o['text'])];
            }
        }

        $teacher = [];
        foreach ((array) ($a['teacher_steps'] ?? []) as $s) {
            $text = $this->str(is_array($s) ? ($s['text'] ?? '') : $s);
            if ($text !== '') {
                $teacher[] = ['minutes' => $this->minutes(is_array($s) ? ($s['minutes'] ?? 1) : 1, 1, 45), 'text' => $text];
            }
        }

        $discussion = [];
        foreach ((array) ($a['discussion'] ?? []) as $d) {
            if (is_array($d) && $this->str($d['prompt'] ?? '') !== '') {
                $discussion[] = ['prompt' => $this->str($d['prompt']), 'answer' => $this->str($d['answer'] ?? '')];
            }
        }

        $m = $a['misconception'] ?? null;
        $misconception = is_array($m) && $this->str($m['wrong_idea'] ?? '') !== '' && $this->str($m['correction'] ?? '') !== ''
            ? ['wrong_idea' => $this->str($m['wrong_idea']), 'correction' => $this->str($m['correction'])]
            : null;

        $assessment = [];
        foreach ((array) ($a['assessment'] ?? []) as $c) {
            if (is_array($c) && $this->str($c['criterion'] ?? '') !== '') {
                $assessment[] = ['criterion' => $this->str($c['criterion']), 'evidence' => $this->str($c['evidence'] ?? '')];
            }
        }

        $d = $a['differentiation'] ?? null;
        $differentiation = is_array($d) && ($this->str($d['support'] ?? '') !== '' || $this->str($d['extension'] ?? '') !== '')
            ? ['support' => $this->str($d['support'] ?? ''), 'extension' => $this->str($d['extension'] ?? '')]
            : null;

        $interaction = $a['interaction'] ?? null;
        $interaction = is_array($interaction) && ($interaction['kind'] ?? '') !== '' ? $interaction : null;

        return [
            'objectives' => array_values($objectives),
            'materials' => $this->strings($a['materials'] ?? [], 7),
            'setup' => $this->nullable($a['setup'] ?? null),
            'teacher_steps' => array_slice($teacher, 0, 7),
            'student_steps' => $this->strings($a['student_steps'] ?? [], 8),
            'expected_outcomes' => $this->strings($a['expected_outcomes'] ?? [], 6),
            'discussion' => array_slice($discussion, 0, 3),
            'misconception' => $misconception,
            'assessment' => array_slice($assessment, 0, 5),
            'differentiation' => $differentiation,
            'reflection' => $this->strings($a['reflection'] ?? [], 3),
            'interaction' => $interaction,
            'bloom' => $this->bloom($a['bloom'] ?? ''),
            'dok' => $this->dok($a['dok'] ?? 2),
        ];
    }

    /**
     * Once the rules have had their say: a misconception that is not a listed one is dropped, and so is an
     * interaction that is not the kind the format promises, rather than printing something untrue to the chapter.
     *
     * @param array<string,array<string,mixed>> $activities
     * @param array<string,array<string,mixed>> $work
     * @return array<string,array<string,mixed>>
     */
    private function finalise(array $activities, array $work): array
    {
        foreach ($activities as $id => $a) {
            $listed = [];
            foreach ($work[$id]['concepts'] as $c) {
                foreach ($c['misconceptions'] as $m) {
                    $listed[] = $m['wrong_idea'];
                }
            }
            if ($a['misconception'] !== null && !self::traces($a['misconception']['wrong_idea'], $listed)) {
                $activities[$id]['misconception'] = null;
            }
            // Every concept the activity covers gets its objective, even if the model skipped one after the repair round:
            // the concept's own listed objective stands in, so the activity never claims less than it covers.
            $have = array_column($a['objectives'], 'concept_id');
            foreach ($work[$id]['concepts'] as $c) {
                if (!in_array($c['concept_id'], $have, true) && ($c['objectives'][0] ?? '') !== '') {
                    $activities[$id]['objectives'][] = ['concept_id' => $c['concept_id'], 'text' => 'Students will be able to: ' . rtrim(lcfirst((string) $c['objectives'][0]), '.') . '.'];
                }
            }
        }

        return $activities;
    }
}
