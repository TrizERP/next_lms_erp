<?php

namespace App\Services\StudyDeck\Documents\Writers;

use App\Services\StudyDeck\Documents\DocumentKind;

/**
 * Writes the wording of the parts of a remedial class that are neither a lesson nor a bank question:
 *
 *   objectives   one "I can ..." statement for each topic, built from the objectives the chapter data lists for its concepts
 *   clinic       for a lesson concept with a listed misconception: why the wrong idea seems right, what is true, and a way to
 *                test it against the chapter's own example (the wrong idea itself is the chapter data's, copied by code)
 *   teacher      for each lesson: what a teacher may notice, one thing to try, and what to do if learners are still stuck
 *
 * Nothing here claims anything about a class. The chapter data lists misconceptions as ideas learners OFTEN hold, not
 * ideas this class holds, and no results exist for this class, so the teacher's text speaks of what may be seen (a rule
 * the writer repairs against and the validator applies), never of what has been diagnosed.
 */
class RemedialFrameWriter extends DocumentWriter
{
    public const OBJECTIVE_WORDS = [6, 30];

    public const SEEMS_TRUE_WORDS = [5, 24];

    public const CORRECTION_WORDS = [6, 30];

    public const CHECK_IT_WORDS = [5, 24];

    public const LOOK_FOR_WORDS = [6, 24];

    public const TRY_THIS_WORDS = [8, 34];

    public const STUCK_WORDS = [6, 26];

    /** A teacher's "look for" speaks of what MAY be seen: one of these words says so. */
    public const HEDGES = ['may', 'might', 'could', 'sometimes', 'if', 'some learners', 'often'];

    private const SYSTEM = 'You are a patient remedial teacher preparing a reteaching class for learners who found a textbook chapter hard. '
        . 'You reply with a single JSON object and nothing else. You never add facts that are not in the chapter text, '
        . 'and you never state that learners in this class have a difficulty: you only say what may be seen.';

    public function __construct(\App\Services\StudyDeck\Contracts\Completer $completer, int $chunkSize = 4)
    {
        parent::__construct($completer, $chunkSize);
    }

    public function kind(): DocumentKind
    {
        return DocumentKind::Remedial;
    }

    /**
     * @param array<string,mixed> $context ConceptContextBuilder::assemble()
     * @param array<string,mixed> $map LearningPlanBuilder::build()
     * @param array<int,int> $scope the concepts of the document
     * @param array{focus:array<int,int>, lessons:array<int,array<string,mixed>>, reasons:array<int,array<int,string>>} $placement
     *        the lesson concepts, the lessons as written, and the reasons each concept may be hard (GapAnalysis)
     * @param callable(int,int):void|null $progress
     * @return array{objectives:array<int,string>, clinic:array<int,array<string,string>>, teacher:array<int,array<string,string>>}
     */
    public function write(array $context, array $map, array $scope, array $placement = [], ?callable $progress = null): array
    {
        $focus = (array) ($placement['focus'] ?? []);
        $lessons = (array) ($placement['lessons'] ?? []);
        $reasons = (array) ($placement['reasons'] ?? []);

        // Objectives: one statement for each topic of the chapter.
        $topics = [];
        foreach ($map['topics'] as $t) {
            $ids = array_values(array_intersect($t['concept_ids'], $scope));
            if ($ids !== []) {
                $objectives = [];
                foreach ($ids as $id) {
                    $objectives = array_merge($objectives, array_slice($map['concepts'][$id]['objectives'], 0, 2));
                }
                $topics[$t['id']] = ['topic_id' => $t['id'], 'name' => $t['name'], 'listed_objectives' => array_slice($objectives, 0, 8)];
            }
        }
        $objectives = $this->inChunks(
            $topics,
            self::SYSTEM,
            6000,
            'topic objectives',
            fn (array $chunk) => $this->objectivesPrompt($context, $chunk),
            fn (array $json, array $chunk) => $this->parseObjectives($json, $chunk),
            fn (array $item) => self::objectiveProblems($item),
            $progress
        );

        // Clinic: the LAST listed misconception of a lesson concept, so it differs from the revision notes' mix-up (the first).
        $items = [];
        foreach ($focus as $id) {
            $m = array_values($map['concepts'][$id]['misconceptions'] ?? []);
            if ($m === []) {
                continue;
            }
            $m = $m[count($m) - 1];
            $items[$id] = [
                'concept_id' => $id,
                'name' => $map['concepts'][$id]['name'],
                'wrong_idea' => $m['wrong_idea'],
                'learners_think' => $m['statement'],
                'listed_correction' => $m['correction'],
                'chapter_facts' => array_slice($map['concepts'][$id]['knowledge'], 0, 3),
            ];
        }
        $clinic = $items === [] ? [] : $this->inChunks(
            $items,
            self::SYSTEM,
            8000,
            'misconception corrections',
            fn (array $chunk) => $this->clinicPrompt($context, $chunk),
            fn (array $json, array $chunk) => $this->parseClinic($json, $chunk),
            fn (array $item, array $work) => self::clinicProblems($item, $work),
            $progress
        );

        // Teacher: what to look for and do, for each lesson.
        $units = [];
        foreach ($focus as $id) {
            $lesson = $lessons[$id] ?? [];
            $units[$id] = [
                'concept_id' => $id,
                'name' => $map['concepts'][$id]['name'],
                'builds_on' => array_values(array_filter(array_map(fn ($r) => $map['concepts'][$r]['name'] ?? null, $map['concepts'][$id]['requires']))),
                'why_it_may_be_hard' => $reasons[$id] ?? [],
                'lesson_explanation' => $lesson['simple_explanation'] ?? '',
                'lesson_worked_example' => $lesson['worked_example']['problem'] ?? null,
                'lesson_picture' => $lesson['real_life'] ?? null,
            ];
        }
        $teacher = $this->inChunks(
            $units,
            self::SYSTEM,
            9000,
            'teacher interventions',
            fn (array $chunk) => $this->teacherPrompt($context, $chunk),
            fn (array $json, array $chunk) => $this->parseTeacher($json, $chunk),
            fn (array $item) => self::teacherProblems($item),
            $progress
        );

        return ['objectives' => array_map(fn ($o) => $o['text'], $objectives), 'clinic' => $clinic, 'teacher' => $teacher];
    }

    // ---------------------------------------------------------------------------------------------------------
    // Rules (the validator applies the same ones)

    /** @param array<string,mixed> $item @return array<int,string> */
    public static function objectiveProblems(array $item): array
    {
        $out = [];
        $n = self::words($item['text']);
        if ($n < self::OBJECTIVE_WORDS[0] || $n > self::OBJECTIVE_WORDS[1]) {
            $out[] = 'The objective has ' . $n . ' words; it must be ' . self::OBJECTIVE_WORDS[0] . ' to ' . self::OBJECTIVE_WORDS[1] . '.';
        }
        if (!preg_match('/^I can\b/i', $item['text'])) {
            $out[] = 'The objective must start "I can".';
        }

        return $out;
    }

    /** @param array<string,mixed> $item @param array<string,mixed> $work @return array<int,string> */
    public static function clinicProblems(array $item, array $work = []): array
    {
        $out = [];
        foreach ([['why_it_seems_true', self::SEEMS_TRUE_WORDS, 'why the idea seems right'], ['correction', self::CORRECTION_WORDS, 'what is true'], ['check_it', self::CHECK_IT_WORDS, 'how to test it']] as [$key, $range, $what]) {
            $n = self::words($item[$key]);
            if ($n < $range[0] || $n > $range[1]) {
                $out[] = '"' . $key . '" (' . $what . ') has ' . $n . ' words; it must be ' . $range[0] . ' to ' . $range[1] . '.';
            }
        }
        if (isset($work['wrong_idea']) && self::overlap($item['correction'], $work['wrong_idea']) >= 0.9 && self::overlap($work['wrong_idea'], $item['correction']) >= 0.9) {
            $out[] = 'The correction only repeats the wrong idea; say what is true instead.';
        }

        return $out;
    }

    /** @param array<string,mixed> $item @return array<int,string> */
    public static function teacherProblems(array $item): array
    {
        $out = [];
        foreach ([['look_for', self::LOOK_FOR_WORDS], ['try_this', self::TRY_THIS_WORDS], ['if_still_stuck', self::STUCK_WORDS]] as [$key, $range]) {
            $n = self::words($item[$key]);
            if ($n < $range[0] || $n > $range[1]) {
                $out[] = '"' . $key . '" has ' . $n . ' words; it must be ' . $range[0] . ' to ' . $range[1] . '.';
            }
        }
        if (!self::hedged($item['look_for'])) {
            $out[] = '"look_for" must speak of what MAY be seen (use "may", "might", "could" or "if"); you do not know that these learners have the difficulty.';
        }

        return $out;
    }

    /** Does the sentence speak of what may be, rather than what is? */
    public static function hedged(string $text): bool
    {
        foreach (self::HEDGES as $h) {
            if (preg_match('/\b' . preg_quote($h, '/') . '\b/i', $text)) {
                return true;
            }
        }

        return false;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Objectives

    /** @param array<int,array<string,mixed>> $chunk */
    private function objectivesPrompt(array $context, array $chunk): string
    {
        $input = json_encode(array_values($chunk), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        [$min, $max] = self::OBJECTIVE_WORDS;
        $ground = self::GROUND_RULES;
        $chapter = json_encode(['name' => $context['chapter']['chapter_name'], 'learning_objective' => $context['learning_objective']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
Write the learning objective of a remedial class for EVERY topic listed under TOPICS. Each is one statement, {$min} to {$max} words, starting "I can", saying what a learner will be able to do with this topic by the end of the class. Build it from the topic's listed objectives (combine them, do not copy one); plain words, specific, no new facts.

Rules
{$ground}

Reply with:
{"objectives": [{"topic_id": 0, "text": "I can ..."}]}

CHAPTER
{$chapter}

TOPICS
{$input}

{$this->chapterBlock($context)}
PROMPT;
    }

    /**
     * @param array<string,mixed> $json
     * @param array<int,array<string,mixed>> $chunk
     * @return array<int,array<string,mixed>>
     */
    private function parseObjectives(array $json, array $chunk): array
    {
        $out = [];
        foreach ((array) ($json['objectives'] ?? []) as $o) {
            $id = (int) ($o['topic_id'] ?? 0);
            if (is_array($o) && isset($chunk[$id]) && $this->str($o['text'] ?? '') !== '') {
                $out[$id] = ['text' => $this->str($o['text'])];
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Clinic

    /** @param array<int,array<string,mixed>> $chunk */
    private function clinicPrompt(array $context, array $chunk): string
    {
        $input = json_encode(array_values($chunk), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        [$seemsMin, $seemsMax] = self::SEEMS_TRUE_WORDS;
        [$corMin, $corMax] = self::CORRECTION_WORDS;
        [$chkMin, $chkMax] = self::CHECK_IT_WORDS;
        $ground = self::GROUND_RULES;

        return <<<PROMPT
For EVERY idea listed under IDEAS TO CORRECT, write a short corrective explanation for a learner who may hold it. The idea ("wrong_idea") is a common one listed for the concept; you do not know that any learner in this class holds it, so do not say they do.

Rules
{$ground}
- Reading level: plain, short sentences, a supportive tone. Never mock the idea or the learner.
- "why_it_seems_true": why a learner could reasonably think this, {$seemsMin} to {$seemsMax} words. Start from the listed "learners_think" and the chapter facts; do not add new facts.
- "correction": what is true instead, {$corMin} to {$corMax} words, from the listed correction and the chapter text. Say it positively ("In fact...").
- "check_it": one way the learner can test the idea against an example or situation that is IN the chapter text, {$chkMin} to {$chkMax} words, ending with what they should find. No invented numbers or examples.

Reply with:
{"items": [{"concept_id": 0, "why_it_seems_true": "", "correction": "", "check_it": ""}]}

IDEAS TO CORRECT
{$input}

{$this->chapterBlock($context)}
PROMPT;
    }

    /**
     * @param array<string,mixed> $json
     * @param array<int,array<string,mixed>> $chunk
     * @return array<int,array<string,mixed>>
     */
    private function parseClinic(array $json, array $chunk): array
    {
        $out = [];
        foreach ((array) ($json['items'] ?? []) as $i) {
            $id = (int) ($i['concept_id'] ?? 0);
            if (is_array($i) && isset($chunk[$id]) && $this->str($i['correction'] ?? '') !== '') {
                $out[$id] = [
                    'why_it_seems_true' => $this->str($i['why_it_seems_true'] ?? ''),
                    'correction' => $this->str($i['correction']),
                    'check_it' => $this->str($i['check_it'] ?? ''),
                ];
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Teacher

    /** @param array<int,array<string,mixed>> $chunk */
    private function teacherPrompt(array $context, array $chunk): string
    {
        $input = json_encode(array_values($chunk), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        [$lookMin, $lookMax] = self::LOOK_FOR_WORDS;
        [$tryMin, $tryMax] = self::TRY_THIS_WORDS;
        [$stuckMin, $stuckMax] = self::STUCK_WORDS;
        $ground = self::GROUND_RULES;

        return <<<PROMPT
For EVERY lesson listed under LESSONS, write brief guidance for the teacher who will run it.

You have NO results from this class. "why_it_may_be_hard" lists only reasons the chapter's data gives for the concept being potentially difficult. So never say that learners in this class DO struggle; say what the teacher MAY see.

Rules
{$ground}
- "look_for": what the teacher may notice if a learner is finding the idea hard, {$lookMin} to {$lookMax} words. It must say what MAY be seen: use "may", "might", "could" or "if".
- "try_this": one thing to do in the lesson that uses its own worked example or real-life picture (listed), {$tryMin} to {$tryMax} words: a question to ask, something to have the learner say or draw, or a step to slow down. Concrete.
- "if_still_stuck": what to do if the learner still does not follow, {$stuckMin} to {$stuckMax} words: go back to a named earlier idea (use "builds_on") or put the idea another way using the chapter's own example.
- No new facts. Plain language for a busy teacher.

Reply with:
{"interventions": [{"concept_id": 0, "look_for": "", "try_this": "", "if_still_stuck": ""}]}

LESSONS
{$input}

{$this->chapterBlock($context)}
PROMPT;
    }

    /**
     * @param array<string,mixed> $json
     * @param array<int,array<string,mixed>> $chunk
     * @return array<int,array<string,mixed>>
     */
    private function parseTeacher(array $json, array $chunk): array
    {
        $out = [];
        foreach ((array) ($json['interventions'] ?? []) as $i) {
            $id = (int) ($i['concept_id'] ?? 0);
            if (is_array($i) && isset($chunk[$id]) && $this->str($i['try_this'] ?? '') !== '') {
                $out[$id] = [
                    'look_for' => $this->str($i['look_for'] ?? ''),
                    'try_this' => $this->str($i['try_this']),
                    'if_still_stuck' => $this->str($i['if_still_stuck'] ?? ''),
                ];
            }
        }

        return $out;
    }
}
