<?php

namespace App\Services\StudyDeck\Branching;

use App\Services\lms\H5P\BranchingPayload;
use App\Services\StudyDeck\DeckValidator;
use App\Services\StudyDeck\QuestionSelector;
use App\Services\StudyDeck\SlideContentGenerator;

/**
 * The last gate before a generated scenario is shown to a teacher.
 *
 * ScenarioBlueprint proved the STRUCTURE sound before any text existed; this
 * checks what the structure cannot: that the text is complete, grounded in the
 * chapter, not repeated, pitched at a learner, and that the scenario TEACHES
 * rather than quizzes. Nothing here changes the scenario or calls a model; it
 * returns findings, each tied to a node where it can be, so the service can ask
 * the writer to fix just those nodes.
 *
 * Findings are ERRORS (the scenario is not shown as ready) or WARNINGS (shown,
 * for a human to look at). The line between them is whether a learner would be
 * misled or stuck: an invented number or a missing explanation is an error; a
 * capitalised name the chapter does not contain is a warning, because a person
 * can tell a character's first name from a claim.
 *
 * PURE: arrays in, arrays out. DeckValidator::groundingCheck is the same rule
 * the study decks and documents are held to.
 */
final class ScenarioValidator
{
    /** Words below which a screen is not teaching anything. */
    private const MIN_SCENE_WORDS = 25;

    /** Words above which a screen is a wall of text. */
    private const MAX_SCENE_WORDS = 190;

    private const BANNED = '/\b(oops|unfortunately|wrong!|incorrect!|you failed|stupid|silly)\b/i';

    public function __construct(private readonly DeckValidator $grounding = new DeckValidator())
    {
    }

    /**
     * @param array<string,mixed> $payload ScenarioAssembler::assemble()['payload']
     * @param array<string,mixed> $options existing (titles and concept sets of scenarios already written)
     * @param list<array<string,string>> $suggestions picture ideas the writer offered
     * @return array{ok:bool,errors:list<string>,warnings:list<string>,issues:list<array<string,mixed>>,stats:array<string,mixed>,suggestions:list<array<string,string>>,by_node:array<string,list<string>>}
     */
    public function validate(array $payload, SourcePack $pack, array $options = [], array $suggestions = []): array
    {
        $issues = [];
        $add = function (string $level, ?string $node, string $code, string $message) use (&$issues): void {
            $issues[] = ['level' => $level, 'node' => $node, 'code' => $code, 'message' => $message];
        };

        $nodes = array_values($payload['nodes']);
        $label = [];
        foreach ($nodes as $i => $n) {
            $label[(string) $n['ref']] = BranchingPayload::label($n, $i);
        }

        // 1. Structure and completeness, by the same rules the editor applies.
        $report = BranchingPayload::analyse($nodes, true);
        foreach ($report['errors'] as $e) {
            $add('error', $e['node'], $e['code'], $e['message']);
        }
        foreach ($report['warnings'] as $w) {
            $add('warning', $w['node'], $w['code'], $w['message']);
        }

        $stats = $report['stats'];
        $words = ['screens' => 0, 'decisions' => 0, 'endings' => 0, 'feedback' => 0];
        $texts = [];

        // 2. Per node.
        foreach ($nodes as $n) {
            $ref = (string) $n['ref'];
            $name = $label[$ref];
            $type = $n['node_type'];
            $body = SourcePack::flat((string) ($n['body'] ?? ''));
            $bodyWords = str_word_count($body);

            if (SourcePack::flat((string) ($n['title'] ?? '')) === '') {
                $add('error', $ref, 'no_title', $name . ' has no title.');
            }

            if ($type === 'text') {
                $words['screens'] += $bodyWords;
                if ($bodyWords < self::MIN_SCENE_WORDS) {
                    $add('error', $ref, 'thin', $name . ' says too little to teach anything (' . $bodyWords . ' words). Explain the idea and why it matters.');
                }
                if ($bodyWords > self::MAX_SCENE_WORDS) {
                    $add('warning', $ref, 'long', $name . ' is long (' . $bodyWords . ' words) for one screen. Consider splitting it.');
                }
                $this->sentences($body, $pack->level(), $name, $ref, $add);
            } elseif ($type === 'ending') {
                $words['endings'] += $bodyWords;
                if ($bodyWords < self::MIN_SCENE_WORDS) {
                    $add('error', $ref, 'thin', $name . ' has too little closing text (' . $bodyWords . ' words). Say what this path showed and what to take from it.');
                }
                if (! str_contains((string) $n['body'], '<li>')) {
                    $add('warning', $ref, 'no_takeaways', $name . ' lists no key takeaways.');
                }
            } else {
                $words['decisions'] += $bodyWords;
                $seenChoices = [];
                $feedbacks = [];
                foreach ($n['options'] as $j => $o) {
                    $fb = SourcePack::flat((string) $o['feedback_text']);
                    $words['feedback'] += str_word_count($fb);
                    $feedbacks[] = $fb;
                    $key = mb_strtolower(SourcePack::flat((string) $o['text']));
                    if (isset($seenChoices[$key])) {
                        $add('error', $ref, 'duplicate_choice', $name . ' offers the same choice twice ("' . $o['text'] . '").');
                    }
                    $seenChoices[$key] = true;

                    if (str_word_count($fb) < 8) {
                        $add('error', $ref, 'no_consequence', 'Choice ' . ($j + 1) . ' of ' . $name . ' does not explain what happens and why.');
                    }
                    if (str_word_count((string) $o['text']) > 30) {
                        $add('warning', $ref, 'long_choice', 'Choice ' . ($j + 1) . ' of ' . $name . ' is long for a button.');
                    }
                }
                // Feedback pasted onto every choice teaches nothing about the difference.
                if (count($feedbacks) >= 2 && count(array_unique($feedbacks)) === 1 && empty($n['source_question_id'])) {
                    $add('error', $ref, 'same_feedback', $name . ' gives the same feedback for every choice, so the choice does not matter.');
                }
                if (empty($n['source_question_id']) && $bodyWords < 6) {
                    $add('error', $ref, 'thin', $name . ' has no real question for the learner.');
                }
            }

            $all = SourcePack::flat((string) $n['title'] . ' ' . $n['body'] . ' ' . implode(' ', array_map(fn ($o) => $o['text'] . ' ' . $o['feedback_title'] . ' ' . $o['feedback_text'], $n['options'])));
            $texts[$ref] = $all;

            // Wording.
            if (preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $all)) {
                $add('error', $ref, 'emoji', $name . ' contains an emoji. The product carries meaning in words.');
            }
            if (preg_match(self::BANNED, $all, $m)) {
                $add('error', $ref, 'tone', $name . ' uses "' . $m[0] . '". A weaker choice is explained, not scolded.');
            }
            if ($type !== 'branching_question' || empty($n['source_question_id'])) {
                // A bank question is allowed its own wording; what the model wrote is not.
                $reason = (new QuestionSelector())->explanationDependsOnMissingContext(preg_replace('/<[^>]+>/', ' ', (string) ($n['body'] ?? '') . ' ' . implode(' ', array_map(fn ($o) => $o['feedback_text'], $n['options']))));
                if ($reason !== null) {
                    $add('error', $ref, 'missing_context', $name . ' ' . $reason . '. The learner sees only this scenario.');
                }
            }
            if (substr_count($all, '!') >= 2) {
                $add('warning', $ref, 'exclaim', $name . ' uses several exclamation marks; the tone should stay calm.');
            }

            // Grounding, per node so the writer can be told which node.
            if ($type !== 'branching_question' || empty($n['source_question_id'])) {
                $errors = [];
                $warnings = [];
                $html = '<p>' . htmlspecialchars((string) $n['title'], ENT_QUOTES, 'UTF-8') . '.</p>' . ($n['body'] ?? '')
                    . implode('', array_map(fn ($o) => '<p>' . htmlspecialchars((string) $o['text'], ENT_QUOTES, 'UTF-8') . '.</p>' . $o['feedback_text'], $n['options']));
                $this->grounding->groundingCheck($html, '', $pack->context, $pack->questions, $errors, $warnings);
                foreach ($errors as $e) {
                    $add('error', $ref, 'ungrounded', $name . ': ' . $e . ' Use only numbers that the chapter gives.');
                }
                foreach ($warnings as $w) {
                    $add('warning', $ref, 'names', $name . ': ' . $w);
                }
            }
        }

        // 3. Duplicates between nodes.
        $refs = array_keys($texts);
        for ($i = 0; $i < count($refs); $i++) {
            for ($j = $i + 1; $j < count($refs); $j++) {
                $a = $nodes[$i];
                $b = $nodes[$j];
                if (in_array('ending', [$a['node_type'], $b['node_type']], true) && $a['node_type'] !== $b['node_type']) {
                    continue;
                }
                $overlap = min(SlideContentGenerator::overlap($texts[$refs[$i]], $texts[$refs[$j]]), SlideContentGenerator::overlap($texts[$refs[$j]], $texts[$refs[$i]]));
                if ($overlap >= 0.8 && str_word_count($texts[$refs[$i]]) >= 20) {
                    $add('error', $refs[$j], 'duplicate', $label[$refs[$j]] . ' repeats ' . $label[$refs[$i]] . '. Each screen should add something new.');
                }
            }
        }

        // 4. Teaches, not quizzes.
        $screens = count(array_filter($nodes, fn ($n) => $n['node_type'] === 'text'));
        $decisions = count(array_filter($nodes, fn ($n) => $n['node_type'] === 'branching_question'));
        $checks = count(array_filter($nodes, fn ($n) => ! empty($n['source_question_id'])));
        $teachingWords = $words['screens'] + $words['feedback'] + $words['endings'];
        if ($screens < max(3, $decisions)) {
            $add('error', null, 'too_many_questions', 'This is mostly decisions (' . $decisions . ') with few screens that teach (' . $screens . '). A scenario is a lesson, not a quiz.');
        }
        if ($teachingWords < 300) {
            $add('error', null, 'too_little_teaching', 'There are only ' . $teachingWords . ' words of explanation across the whole scenario. A lesson needs more than that.');
        }
        if ($checks > intdiv($decisions, 2) + ($decisions % 2)) {
            $add('error', null, 'questions_dominate', 'Most decisions are bank questions. Questions should support the lesson, not be most of it.');
        }

        // 5. Coverage.
        $covered = array_unique(array_filter(array_map(fn ($n) => $n['concept_id'] ?? null, $nodes)));
        $missing = array_diff($pack->conceptIds(), $covered);
        foreach ($missing as $id) {
            $add('error', null, 'concept_missing', 'The concept "' . ($pack->concepts[$id]['name'] ?? $id) . '" is not taught by any step.');
        }

        // 6. Not a repeat of one already written for this chapter.
        $title = mb_strtolower(trim((string) $payload['title']));
        $ids = $pack->conceptIds();
        sort($ids);
        foreach ((array) ($options['existing'] ?? []) as $existing) {
            if ($title !== '' && mb_strtolower(trim((string) ($existing['title'] ?? ''))) === $title) {
                $add('error', null, 'same_title', 'A scenario called "' . $payload['title'] . '" already exists for this chapter. Give this one its own title and situation.');
            }
            $theirs = array_values(array_map('intval', (array) ($existing['concept_ids'] ?? [])));
            sort($theirs);
            if ($theirs !== [] && $theirs === $ids) {
                $add('warning', null, 'same_concepts', 'A scenario on exactly these concepts already exists ("' . ($existing['title'] ?? '') . '"). Check this one takes a different situation.');
            }
        }

        // 7. Whole-scenario duration, for the report.
        $minutes = (int) max(2, round(($teachingWords + $words['decisions']) / 130 + $decisions * 0.5));

        $errors = array_values(array_unique(array_map(fn ($i) => $i['message'], array_filter($issues, fn ($i) => $i['level'] === 'error'))));
        $warnings = array_values(array_unique(array_map(fn ($i) => $i['message'], array_filter($issues, fn ($i) => $i['level'] === 'warning'))));

        $byNode = [];
        foreach ($issues as $issue) {
            if ($issue['level'] === 'error' && $issue['node'] !== null) {
                $byNode[$issue['node']][] = $issue['message'];
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'issues' => $issues,
            'by_node' => $byNode,
            'suggestions' => $suggestions,
            'stats' => $stats + [
                'concepts' => count($pack->concepts),
                'concepts_covered' => count(array_intersect($pack->conceptIds(), $covered)),
                'bank_questions' => $checks,
                'teaching_words' => $teachingWords,
                'minutes' => $minutes,
            ],
        ];
    }

    /**
     * Sentence length against the learner's class.
     *
     * Advisory: a long sentence in a science explanation is not an error, but
     * several of them on one screen are a reading barrier worth flagging.
     */
    private function sentences(string $text, ?int $level, string $name, string $ref, callable $add): void
    {
        $sentences = array_filter(preg_split('/(?<=[.?])\s+/u', $text) ?: [], fn ($s) => str_word_count($s) > 0);
        if (count($sentences) < 2) {
            return;
        }
        $limit = $level !== null && $level <= 5 ? 18 : ($level !== null && $level <= 8 ? 22 : 28);
        $long = count(array_filter($sentences, fn ($s) => str_word_count($s) > $limit));
        if ($long >= 2 || ($long >= 1 && count($sentences) <= 3)) {
            $add('warning', $ref, 'long_sentences', $name . ' has ' . $long . ' sentence(s) over ' . $limit . ' words, which is hard going at this level.');
        }
    }
}
