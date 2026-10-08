<?php

namespace App\Services\StudyDeck;

/**
 * Decides HOW each bank question is played on a study-deck slide.
 *
 * It produces ACTIVITY SPECS, not H5P content. A spec is
 *   { question_id, concept_id, as, pattern, label, ... }
 * and the student player hands the question row to the existing native players
 * (components/h5p/players/QuestionPlayer) with `as` as the requested H5P target.
 * Nothing is converted or stored here, and no H5P row exists: the row is read from
 * lms_question_master at play time, which is the repository's own runtime design
 * (lib/h5p/question-bank-runtime.ts).
 *
 * `FORM_TARGETS` mirrors the "also plays as" lists in lib/h5p/question-bank-h5p-map.ts,
 * which remains the only authority. The player re-checks every spec at run time and
 * falls back to the question's own default target if a spec asks for something the
 * row cannot be, so a drift here degrades to a plainer activity and never to a broken
 * one. lib/study-deck has a test that fails if this list names a target the runtime
 * does not know.
 *
 * Selection is by what the concept and the question ARE (complexity, objective verbs,
 * form, relationships, the class), never by subject or chapter.
 */
class ActivityPlanner
{
    /** Every target the runtime can play. Branching Scenario is NOT one of them. */
    public const TARGETS = [
        'single_choice_set', 'true_false', 'fill_in_the_blanks', 'drag_text', 'mark_the_words',
        'memory_game', 'flashcards', 'course_presentation', 'essay', 'drag_drop',
    ];

    /** Backend form name => [default target, other targets the form can be asked as]. */
    private const FORM_TARGETS = [
        'mcq' => ['single_choice_set', ['flashcards', 'course_presentation']],
        'true_false' => ['true_false', ['single_choice_set', 'flashcards', 'course_presentation']],
        'assertion_reason' => ['single_choice_set', ['course_presentation', 'flashcards']],
        'very_short_answer' => ['essay', ['fill_in_the_blanks', 'drag_text', 'mark_the_words', 'flashcards', 'course_presentation']],
        'short_answer' => ['essay', ['fill_in_the_blanks', 'flashcards', 'course_presentation']],
        'long_answer' => ['essay', ['flashcards', 'course_presentation']],
        'fill_blank' => ['fill_in_the_blanks', ['drag_text', 'mark_the_words', 'flashcards', 'course_presentation']],
        'match_following' => ['memory_game', ['flashcards', 'drag_text', 'course_presentation']],
        'drag_drop' => ['drag_drop', []],
    ];

    /** Slide types whose job is a decision, a consequence or a real situation. */
    private const DECISION_SLIDES = ['scenario', 'application', 'worked_example'];

    /** The activity labels a learner sees, by what is being asked of them. */
    public const LABELS = ['Try it', 'Apply', 'Explain', 'Check', 'Think about it'];

    public function __construct(private readonly H5pPatternSelector $patterns = new H5pPatternSelector())
    {
    }

    /**
     * @param array<string,mixed> $slide planned slide (slide_type, concept_ids, h5p_pattern, relationship)
     * @param array<int,array<string,mixed>> $questions the bank questions placed on this slide
     * @param array<int,array<string,mixed>> $concepts concept_id => learning-map concept
     * @param int|null $level the class as a number (Std 9 => 9), null when unknown
     * @return array<int,array<string,mixed>>
     */
    public function plan(array $slide, array $questions, array $concepts, ?int $level = null): array
    {
        $pattern = $slide['h5p_pattern']['type'] ?? null;
        $primary = $concepts[$slide['concept_ids'][0] ?? 0] ?? null;
        $branching = $pattern === 'branching' ? $this->branchingEligible($slide, $questions, $concepts) : null;
        $out = [];

        foreach (array_values($questions) as $i => $q) {
            $concept = $concepts[$q['concept_id']] ?? $primary ?? [];
            [$as, $why] = $this->targetFor($q, $pattern, $concept, $level, $branching['ok'] ?? false);
            $connects = $primary && $q['concept_id'] !== ($primary['id'] ?? $q['concept_id']);

            $out[] = [
                'source' => 'bank',
                'question_id' => $q['id'],
                'concept_id' => $q['concept_id'],
                'as' => $as,
                'default_as' => $this->defaultTarget($q['form']),
                'pattern' => $this->effectivePattern($pattern, $as, $branching['ok'] ?? false),
                'decision' => ($branching['ok'] ?? false) && $as === 'single_choice_set' && $q['form'] === 'mcq',
                'connects_concept' => $connects ? $q['concept_id'] : null,
                'label' => $this->label($q['bloom'], $q['form'], $connects, $i),
                'bloom' => $q['bloom'] !== '' ? $q['bloom'] : null,
                'difficulty' => $q['difficulty'] !== '' ? $q['difficulty'] : null,
                'dok' => $q['dok'],
                'why' => $why,
            ];
        }

        return $out;
    }

    /** @return array{0:string,1:string} [target, reason] */
    public function targetFor(array $q, ?string $pattern, array $concept, ?int $level, bool $branchingOk): array
    {
        $form = $q['form'];
        $default = $this->defaultTarget($form);
        $allowed = array_merge([$default], self::FORM_TARGETS[$form][1] ?? []);
        $can = fn (string $t) => in_array($t, $allowed, true);

        $answerWords = str_word_count($q['answer_text'] ?? '');
        $shortAnswer = ($q['answer_text'] ?? '') !== '' && $answerWords <= 8 && mb_strlen($q['answer_text']) <= 60;
        $hard = in_array(strtolower((string) ($concept['difficulty'] ?? '')), ['hard', 'difficult'], true) || in_array(3, (array) ($concept['dok'] ?? []), true);
        $young = $level !== null && $level <= 5;

        // A written explanation of why is the right check for a hard, conceptual idea; typing a
        // single word into a blank is not.
        if ($pattern === 'fill_blanks' && $can('fill_in_the_blanks') && $shortAnswer && !$hard) {
            return ['fill_in_the_blanks', 'a short factual answer reinforces exact terminology'];
        }
        if ($pattern === 'flashcards' && $can('flashcards') && ($q['bloom'] ?: 'remember') === 'remember') {
            return ['flashcards', 'a recall question on a term or definition suits a flip card'];
        }
        if ($pattern === 'true_false' && $form === 'true_false') {
            return ['true_false', 'a statement to judge exposes a common wrong idea'];
        }
        if ($pattern === 'matching' && $form === 'match_following') {
            return ['memory_game', 'two columns of related ideas are matched'];
        }
        if ($pattern === 'drag_drop' && $form === 'drag_drop') {
            return ['drag_drop', 'items are sorted onto the parts of a picture'];
        }
        if ($pattern === 'branching' && $branchingOk && $form === 'mcq') {
            return ['single_choice_set', 'a decision with consequences, answered as a choice'];
        }
        // Younger learners get recall-style cards instead of written reasoning.
        if ($young && $default === 'essay' && $can('flashcards')) {
            return ['flashcards', 'a written answer is replaced by a card for younger learners'];
        }

        return [$default, 'the question\'s own form'];
    }

    /**
     * Branching Scenario is only worth using when the concept truly turns on a decision.
     * There is no multi-path branching player in this platform, so an eligible slide is
     * played as a decision question (a choice with feedback) and says so honestly.
     *
     * @param array<int,array<string,mixed>> $questions
     * @param array<int,array<string,mixed>> $concepts
     * @return array{ok:bool,reason:string}
     */
    public function branchingEligible(array $slide, array $questions, array $concepts): array
    {
        if (!in_array($slide['slide_type'] ?? '', self::DECISION_SLIDES, true)) {
            return ['ok' => false, 'reason' => 'a branching scenario needs a scenario, application or worked-example slide'];
        }
        if (!array_filter($questions, fn ($q) => $q['form'] === 'mcq')) {
            return ['ok' => false, 'reason' => 'there is no choice question to carry the decision'];
        }

        $concept = $concepts[$slide['concept_ids'][0] ?? 0] ?? [];
        $consequence = !empty($concept['real_world']) || !empty($concept['misconceptions'])
            || !empty($slide['relationship']) || count($concept['relationships'] ?? []) > 0;
        if (!$consequence) {
            return ['ok' => false, 'reason' => 'the concept has no consequence, misconception or relationship to branch on'];
        }

        $thinking = array_intersect(['apply', 'analyze', 'evaluate', 'create'], (array) ($concept['blooms'] ?? []))
            || array_filter((array) ($concept['dok'] ?? []), fn ($d) => $d >= 2);
        if (!$thinking) {
            return ['ok' => false, 'reason' => 'a recall-level concept has no decision to make'];
        }

        return ['ok' => true, 'reason' => 'a decision with a consequence, on a concept that asks for judgement'];
    }

    public function defaultTarget(string $form): string
    {
        return self::FORM_TARGETS[$form][0] ?? 'single_choice_set';
    }

    public function label(string $bloom, string $form, bool $connects, int $index): string
    {
        if ($connects) {
            return 'Think about it';
        }
        $written = in_array($form, ['short_answer', 'very_short_answer', 'long_answer'], true);

        return match (true) {
            $bloom === 'create' => 'Try it',
            $bloom === 'apply' => 'Apply',
            in_array($bloom, ['analyze', 'evaluate'], true) => 'Think about it',
            $written => 'Explain',
            $bloom === 'remember' => 'Check',
            // Unlabelled by the bank: alternate so a stack of questions does not read as one repeated prompt.
            default => $index % 2 === 0 ? 'Check' : 'Try it',
        };
    }

    /** The pattern recorded on an activity: what actually shaped it. */
    private function effectivePattern(?string $pattern, string $as, bool $branchingOk): ?string
    {
        // A pattern is recorded on an activity only if the activity really takes its shape:
        // a "flashcards" slide whose question ended up as a written answer was not flashcards.
        $shapes = [
            'flashcards' => ['flashcards'],
            'fill_blanks' => ['fill_in_the_blanks'],
            'true_false' => ['true_false'],
            'matching' => ['memory_game'],
            'drag_drop' => ['drag_drop'],
            'question_set' => ['single_choice_set'],
            'course_presentation' => ['course_presentation', 'single_choice_set', 'true_false', 'essay'],
        ];
        if ($pattern === 'branching') {
            return $branchingOk && $as === 'single_choice_set' ? 'branching' : null;
        }

        return $pattern !== null && in_array($as, $shapes[$pattern] ?? [], true) ? $pattern : null;
    }
}
