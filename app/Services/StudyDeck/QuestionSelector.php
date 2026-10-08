<?php

namespace App\Services\StudyDeck;

use App\Services\QuestionGeneration\QuestionFormResolver;

/**
 * Chooses which existing question-bank rows may appear in a study deck.
 *
 * A deck shows a question to a learner who has only seen the slides. A stem that
 * points at "the passage", "the source", "the diagram", "the figure on the left"
 * or "Person B" cannot be answered there, and an earlier pilot found 27 of 33
 * deck questions were exactly that. Such questions are EXCLUDED, never
 * rewritten: rewriting would silently turn a reviewed question into a different,
 * unreviewed one.
 *
 * Wording is handled differently. A stem padded with intensifiers ("deeply",
 * "absolutely", "mindlessly") is still answerable, so it is not excluded - but it
 * is FLAGGED, used only when a concept has nothing cleaner, and reported for a
 * human wording review instead of being passed off as good student content.
 *
 * Pure - rows in, rows out - so the whole policy is testable with no database.
 */
class QuestionSelector
{
    /** A stem matching any of these needs context the deck does not provide. */
    private const DEPENDENT = [
        // Reading material that is not on the slide.
        '/\b(according to|as per|based on|from|in|using|read) (the |this |these |those )?(above |following |given |below )?(passage|paragraph|text|extract|excerpt|source|textbook|chapter|reading|author|case study|statement|information|data)\b/i',
        '/\b(the|this|following|above|given) (passage|paragraph|extract|excerpt|source|textbook|case study)\b/i',
        '/\bas (mentioned|stated|described|discussed|explained|given|seen|noted) (earlier|above|before|previously|in the)/i',
        '/\b(above|below|preceding|previous|following) (text|passage|paragraph|statement|lines?)\b/i',
        '/\brefer to\b/i',
        // A picture the learner is told to look at.
        '/\b(above|below|preceding|previous|following) (figure|diagram|picture|image|graph|chart|map|photograph|illustration)\b/i',
        '/\b(figure|fig\.?|diagram|picture|image|graph|chart|map|photograph|illustration) (given|shown|above|below|alongside|here)\b/i',
        '/\b(shown|given|depicted|displayed|drawn|marked|labelled|labeled) (in|on|above|below) (the |this )?(figure|diagram|picture|image|graph|chart|table|map)/i',
        '/\b(in|from|on|using|see|study|observe|look at|refer to) (the |this )(figure|diagram|picture|image|graph|chart|photograph|illustration)\b/i',
        // Left / right of something unseen.
        '/\b(on|at) the (left|right)\b/i',
        '/\b(left|right)[- ]hand (side|figure|diagram|picture|panel|part)\b/i',
        // Labelled parts: "parts P, Q and R", "X, Y and Z", "point A".
        '/\b[P-Z],? ?[P-Z],? (and|or|&) ?[P-Z]\b/',
        '/\b(parts?|points?|labels?|regions?|areas?|boxes|zones?|arrows?|rows?|columns?|beakers?|circuits?) ?[A-Z]\b(?![a-z])/',
        '/\b(labelled|labeled|marked|indicated by|pointed at|shown at) [A-Z]\b(?![a-z\'])/',
        // "the graph shows ...": the demonstrative names a picture the learner was never given.
        '/\b(the|this) (figure|diagram|picture|image|graph|chart|photograph|illustration)\b/i',
        // A character introduced somewhere else: "Person B's approach".
        '/\b(Person|Student|Group|Team|Learner|Scientist|Method|Approach|Model) [A-D]\b(?!\s+(is|are|says|said|thinks|uses|measures|claims|states|argues|notes|observes|records|predicts|plans|decides|believes|suggests|has|gets|takes|wants|tries|finds|asks)\b)/',
        // A numbered example of the book.
        '/\bExample \d+(\.\d+)?\b/i',
    ];

    private const TABLE_REFERENCE = '/\b(table|data) (given|shown|above|below|provided)\b|\b(the|this) table\b/i';

    /** Intensifiers that pad a stem or its options without adding meaning. */
    private const PADDING = [
        'deeply', 'absolutely', 'exceedingly', 'highly', 'fundamentally', 'perfectly', 'mindlessly', 'completely',
        'strictly', 'incredibly', 'rigorously', 'seamlessly', 'heavily', 'exclusively', 'entirely', 'definitively',
        'critically', 'boundless', 'brilliant', 'robust', 'insurmountable', 'distinctly', 'immensely', 'tremendously',
        'unequivocally', 'undeniably', 'overarching', 'exceptionally', 'profoundly', 'meticulously', 'intricately',
        'carefully structured',
    ];

    private const ACCEPTABLE_FORMS = ['mcq', 'true_false', 'very_short_answer', 'short_answer'];

    /**
     * @param array<int,array<string,mixed>> $rows lms_question_master rows (id, concept_id, question_title, points, answer, g_bloom, g_difficulty, g_dok)
     * @param array<int,string> $allowedForms normalised form codes the board allows in a deck
     * @return array{eligible:array<int,array<int,array<string,mixed>>>, excluded:array<int,array{id:int,reason:string}>, flagged:array<int,array{id:int,flags:array<int,string>}>}
     */
    public function select(array $rows, int $perConcept = 4, array $allowedForms = self::ACCEPTABLE_FORMS): array
    {
        $eligible = [];
        $excluded = [];
        $seen = [];

        foreach ($rows as $row) {
            $q = $this->normalise($row);
            $reason = $this->rejection($q, $allowedForms);

            if ($reason === null) {
                $hash = md5(mb_strtolower(strip_tags($q['stem'])));
                if (isset($seen[$hash])) {
                    $reason = 'duplicate of another question';
                } else {
                    $seen[$hash] = true;
                }
            }

            if ($reason !== null) {
                $excluded[] = ['id' => $q['id'], 'reason' => $reason];
                continue;
            }

            $eligible[$q['concept_id']][] = $q;
        }

        $flagged = [];
        foreach ($eligible as $conceptId => $list) {
            $clean = array_values(array_filter($list, fn ($q) => $q['flags'] === []));
            // A flagged question is a last resort for a concept with nothing cleaner.
            $pool = $clean !== [] ? $clean : $list;
            $eligible[$conceptId] = $this->spread($pool, $perConcept);

            foreach ($list as $q) {
                if ($q['flags'] !== []) {
                    $flagged[] = ['id' => $q['id'], 'flags' => $q['flags']];
                }
            }
        }

        return ['eligible' => $eligible, 'excluded' => $excluded, 'flagged' => $flagged];
    }

    /** @return array<string,mixed> */
    public function normalise(array $row): array
    {
        $a = json_decode((string) ($row['answer'] ?? ''), true);
        $a = is_array($a) ? $a : [];

        // The form is resolved the way the question-bank API resolves it, because the student player
        // reads the API's answer: a sidecar code, then the recorded format, then the envelope's item_form,
        // then the derived tag. A narrative row with none of those is "no form recorded" to the player and
        // cannot be asked, whatever its sub_type says. A choice row with none is still plain multiple choice.
        $form = strtolower((string) QuestionFormResolver::resolve(
            $row['sidecar_code'] ?? null,
            $row['question_format_code'] ?? null,
            $row['answer'] ?? null,
            $row['g_qtype_code'] ?? null,
            null
        ));
        if ($form === '') {
            $form = in_array(strtolower((string) ($row['question_type'] ?? '')), ['multiple', 'mcq', 'multiple choice', 'multiple_choice'], true) ? 'mcq' : 'unrecorded';
        }

        // The bank spells written forms two ways ("short" from the catalogue, "Short Answer"
        // from the row's sub_type); one spelling is used from here on.
        $form = ['short' => 'short_answer', 'very_short' => 'very_short_answer', 'long' => 'long_answer'][$form] ?? $form;

        $options = [];
        foreach ((array) ($a['options'] ?? []) as $o) {
            if (isset($o['label'], $o['text'])) {
                $options[] = ['label' => (string) $o['label'], 'text' => trim((string) $o['text'])];
            }
        }

        $correct = isset($a['correct_option']) ? (string) $a['correct_option'] : null;
        if ($correct === null) {
            foreach ((array) ($a['options'] ?? []) as $o) {
                if (!empty($o['is_correct'])) {
                    $correct = (string) ($o['label'] ?? '');
                }
            }
        }

        $modelAnswer = trim((string) ($a['model_answer'] ?? ''));
        $isChoice = in_array($form, ['mcq', 'true_false'], true);

        // The bank's own labels win. Only when a row carries none is the Bloom level
        // left empty (and the deck says so) rather than borrowed from the slide.
        $bloom = $this->bloom((string) ($row['g_bloom'] ?? $a['bloom_level'] ?? ''));
        $dok = (int) ($row['g_dok'] ?? $a['dok_level'] ?? 0);

        $q = [
            'id' => (int) $row['id'],
            'concept_id' => (int) $row['concept_id'],
            'form' => $form,
            'stem' => $this->cleanStem((string) $row['question_title']),
            'options' => $options,
            'correct_label' => $correct,
            // What the learner is shown after answering: for a choice question the
            // stored rationale, for a written one the stored model answer.
            'answer_text' => $isChoice ? '' : $modelAnswer,
            'explanation' => trim((string) ($a['explanation'] ?? '')) !== '' ? trim((string) $a['explanation']) : ($isChoice ? $modelAnswer : ''),
            'bloom' => $bloom,
            'bloom_source' => $bloom !== '' ? 'bank' : 'none',
            'difficulty' => strtolower((string) ($row['g_difficulty'] ?? $a['difficulty'] ?? '')),
            'dok' => $dok >= 1 && $dok <= 4 ? $dok : null,
            'points' => $row['points'] ?? null,
            'figure_required' => !empty($a['figure_required']),
        ];
        $q['flags'] = $this->wordingFlags($q);

        return $q;
    }

    /** @param array<string,mixed> $q */
    public function rejection(array $q, array $allowedForms): ?string
    {
        if ($q['form'] === 'unrecorded') {
            return 'no question form is recorded on this row, so the player cannot ask it';
        }
        if (!in_array($q['form'], $allowedForms, true)) {
            return 'form "' . $q['form'] . '" not allowed in a deck';
        }
        if ($q['figure_required']) {
            return 'needs a figure the deck does not show';
        }
        if (trim(strip_tags($q['stem'])) === '') {
            return 'empty stem';
        }
        if ($reason = $this->dependsOnMissingContext($q['stem'])) {
            return $reason;
        }
        foreach ($q['options'] as $o) {
            if ($reason = $this->dependsOnMissingContext($o['text'])) {
                return 'an option ' . $reason;
            }
        }
        if ($q['form'] === 'mcq' || $q['form'] === 'true_false') {
            $labels = array_column($q['options'], 'label');
            if (count($q['options']) < 2 || $q['correct_label'] === null || !in_array($q['correct_label'], $labels, true)) {
                return 'no usable options or answer key';
            }
            if ($q['explanation'] === '') {
                return 'no stored explanation to show after the answer';
            }
        } elseif ($q['answer_text'] === '') {
            return 'no model answer to reveal';
        }

        // What the learner is shown AFTER answering must stand on its own too: "This matches the passage"
        // explains nothing to someone who never saw a passage.
        foreach ([$q['explanation'], $q['answer_text']] as $shown) {
            if ($shown !== '' && $reason = $this->explanationDependsOnMissingContext($shown)) {
                return 'its explanation ' . $reason;
            }
        }

        return null;
    }

    /** Null when an explanation or model answer stands on its own; otherwise why it does not. */
    public function explanationDependsOnMissingContext(string $text): ?string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($text)));

        // An explanation talks ABOUT the source more freely than a stem does ("the text", "the author").
        if (preg_match('/\b(the|this|these|those) (text|passage|source|paragraph|extract|excerpt|reading|author|textbook|case study|statement above)\b|\baccording to\b|\bas (mentioned|stated|described|discussed|explained|noted) (earlier|above|before|previously|in)\b/i', $plain, $m)) {
            return 'depends on missing context ("' . trim($m[0]) . '")';
        }

        return $this->dependsOnMissingContext($text);
    }

    /** Null when the stem stands on its own; otherwise why it does not. */
    public function dependsOnMissingContext(string $stem): ?string
    {
        $hasTable = stripos($stem, '<table') !== false;
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($stem)));

        foreach (self::DEPENDENT as $pattern) {
            if (preg_match($pattern, $plain, $m)) {
                // A figure or table the stem itself carries is context the learner does see.
                if ($hasTable && preg_match('/table|data|rows?|columns?/i', $m[0])) {
                    continue;
                }

                return 'depends on missing context ("' . trim($m[0]) . '")';
            }
        }
        if (!$hasTable && preg_match(self::TABLE_REFERENCE, $plain, $m)) {
            return 'refers to a table that is not in the stem ("' . trim($m[0]) . '")';
        }

        return null;
    }

    /**
     * Padded or unnatural wording, as reasons for a human reviewer.
     *
     * @param array<string,mixed> $q
     * @return array<int,string>
     */
    public function wordingFlags(array $q): array
    {
        $text = mb_strtolower(trim(strip_tags($q['stem'] . ' ' . implode(' ', array_column($q['options'], 'text')))));
        $words = max(1, str_word_count($text));
        $hits = [];
        foreach (self::PADDING as $pad) {
            $n = preg_match_all('/\b' . preg_quote($pad, '/') . '\b/u', $text);
            if ($n > 0) {
                $hits[$pad] = $n;
            }
        }
        $total = array_sum($hits);

        $flags = [];
        if ($total >= 3 || ($total >= 2 && $total / $words > 0.04)) {
            $flags[] = 'padded wording: ' . implode(', ', array_keys($hits));
        }
        if (preg_match('/\b(incorrect|not|except)\b.*\b(incorrect|not|except)\b/i', strip_tags($q['stem']))) {
            $flags[] = 'double negative in the stem';
        }

        return $flags;
    }

    /** Keep a mix of Bloom levels and forms, not the first N by id. */
    private function spread(array $list, int $max): array
    {
        usort($list, fn ($a, $b) => $a['id'] <=> $b['id']);
        $picked = [];
        $covered = [];

        foreach ($list as $q) {
            if (count($picked) >= $max) {
                break;
            }
            $key = $q['bloom'] . '|' . $q['form'];
            if ($q['bloom'] !== '' && isset($covered[$key])) {
                continue;
            }
            $covered[$key] = true;
            $picked[] = $q;
        }
        foreach ($list as $q) {
            if (count($picked) >= $max) {
                break;
            }
            if (!in_array($q, $picked, true)) {
                $picked[] = $q;
            }
        }

        return $picked;
    }

    private function bloom(string $b): string
    {
        $b = strtolower(trim($b));
        $b = $b === 'analyse' ? 'analyze' : $b;

        return in_array($b, ['remember', 'understand', 'apply', 'analyze', 'evaluate', 'create'], true) ? $b : '';
    }

    /** The exported rows wrap the stem in a full HTML document; keep only what a slide can carry. */
    private function cleanStem(string $stem): string
    {
        $stem = preg_replace('#</?(html|body|head)[^>]*>#i', '', $stem);

        return trim(preg_replace('/\s+/', ' ', (string) $stem));
    }
}
