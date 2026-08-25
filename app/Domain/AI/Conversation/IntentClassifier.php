<?php

namespace App\Domain\AI\Conversation;

/**
 * Natural language in, one intent and its slots out.
 *
 * This is deliberately deterministic. Intent routing decides whether an agent runs
 * against a school's real records and whether an approval is recorded, so it is not a
 * place for a model that answers differently on Tuesday. Every intent declares the
 * phrasings it accepts, and the trace reports exactly which of them matched — so when
 * a question is misread, the fix is a visible line in this file rather than a prompt
 * nobody can inspect.
 *
 * Robustness to wording comes from breadth: each intent carries a lexicon of the ways
 * people actually ask for it, so "find students at academic risk", "who is struggling"
 * and "show me at-risk kids" all land on the same intent, which is the behaviour the
 * test plan checks by rephrasing.
 */
class IntentClassifier
{
    /**
     * Below this, the system says it did not understand rather than guessing. Acting on
     * a coin-flip reading of a question is worse than asking again.
     */
    private const CONFIDENCE_FLOOR = 0.34;

    /**
     * Intent definitions, scoped to the Student Profiles module.
     *
     * `anchors` are the terms that make an intent plausible at all; `signals` are the
     * terms that distinguish it from its neighbours and are therefore weighted higher.
     * `patterns` are full-phrase matches that settle a case outright.
     */
    private const INTENTS = [
        'student_risk_scan' => [
            'label' => 'Find students at academic risk',
            'description' => 'Runs the academic risk agent across the students in scope.',
            'anchors' => ['student', 'students', 'child', 'children', 'kid', 'kids', 'learner', 'learners', 'pupil'],
            'signals' => [
                'risk' => 3.0, 'at risk' => 4.0, 'at-risk' => 4.0, 'struggling' => 3.5,
                'failing' => 3.0, 'falling behind' => 3.5, 'behind' => 2.0, 'weak' => 2.0,
                'concern' => 2.5, 'concerns' => 2.5, 'worried' => 2.5, 'attention' => 2.0,
                'academic risk' => 5.0, 'need help' => 2.5, 'needs help' => 2.5,
                'poor performance' => 3.0, 'underperforming' => 3.5, 'not doing well' => 3.0,
                'flag' => 2.0, 'identify' => 1.5, 'find' => 1.0, 'list' => 1.0,
                'which' => 1.0, 'who' => 1.0, 'show' => 1.0, 'scan' => 2.0, 'analyse' => 1.5,
                'analyze' => 1.5, 'check' => 1.0,
            ],
            'patterns' => [
                '/\b(which|what|who|any)\b.{0,30}\b(student|students|children|kids|learners)\b.{0,40}\b(risk|struggl|behind|fail|concern)/i',
                '/\b(find|show|list|identify|flag|scan for)\b.{0,30}\b(at[\s-]?risk|risk|struggling|failing)\b/i',
                '/\backademic risk\b/i',
            ],
            'slots' => [],
        ],

        'student_risk_explain' => [
            'label' => 'Explain why one student is at risk',
            'description' => 'Reads back the stored, evidence-cited explanation for one student\'s case.',
            'anchors' => ['why', 'reason', 'explain', 'cause', 'because', 'how come', 'what makes'],
            'signals' => [
                'why' => 4.0, 'explain' => 4.0, 'reason' => 3.5, 'reasons' => 3.5,
                'cause' => 3.0, 'how come' => 4.0, 'what makes' => 3.5, 'justify' => 3.0,
                'at risk' => 2.0, 'flagged' => 2.5, 'raised' => 1.5,
            ],
            'patterns' => [
                '/\bwhy\b.{0,40}\b(at[\s-]?risk|risk|flagged|struggling|behind)\b/i',
                '/\b(explain|reason for|reasons for|justify)\b.{0,30}\b(risk|case|flag)/i',
                '/\bwhy is\b/i',
                '/\bwhy was\b/i',
            ],
            'slots' => ['student'],
        ],

        'evidence_inspect' => [
            'label' => 'Show the evidence behind the conclusion',
            'description' => 'Lists the verified, source-linked evidence rows cited by the case.',
            'anchors' => ['evidence', 'proof', 'data', 'basis', 'support', 'back'],
            'signals' => [
                'evidence' => 5.0, 'proof' => 4.5, 'prove' => 4.0, 'basis' => 3.5,
                'what data' => 4.0, 'which data' => 4.0, 'back this' => 4.0,
                'backs this' => 4.0, 'support this' => 4.0, 'supports this' => 4.0,
                'based on' => 3.0, 'source' => 3.0, 'sources' => 3.0, 'figures' => 2.5,
                'numbers' => 2.5, 'show me the' => 1.5,
            ],
            'patterns' => [
                '/\bwhat (evidence|data|proof)\b/i',
                '/\b(evidence|proof|data)\b.{0,25}\b(support|supports|back|backs|behind|for this)\b/i',
                '/\bhow do you know\b/i',
                '/\bwhere (did|does) (this|that) come from\b/i',
            ],
            'slots' => ['case'],
        ],

        'recommendation_advice' => [
            'label' => 'What should be done about it',
            'description' => 'Returns the drafted recommendation and the action it would take.',
            'anchors' => ['do', 'action', 'recommend', 'suggest', 'next', 'help', 'fix', 'plan'],
            'signals' => [
                'what should' => 4.5, 'what can' => 3.5, 'recommend' => 4.5,
                'recommendation' => 5.0, 'recommendations' => 5.0, 'suggest' => 4.0,
                'suggestion' => 4.0, 'advice' => 3.5, 'next step' => 4.0,
                'next steps' => 4.0, 'what do i do' => 4.0, 'how do i help' => 4.0,
                'how can we help' => 4.0, 'intervention' => 3.5, 'action plan' => 4.0,
                'plan' => 2.0, 'teacher do' => 4.0, 'teacher should' => 4.5,
            ],
            'patterns' => [
                '/\bwhat should (the )?(teacher|we|i|school)\b/i',
                '/\b(recommend|recommendation|suggest|advice|next steps?)\b/i',
                '/\bhow (do|can) (i|we)\b.{0,20}\b(help|support|fix|improve)\b/i',
            ],
            'slots' => ['case'],
        ],

        'approve_recommendation' => [
            'label' => 'Approve the recommendation',
            'description' => 'Records a human approval and starts the intervention workflow.',
            'anchors' => ['approve', 'accept', 'agree', 'yes', 'go ahead', 'proceed', 'confirm'],
            'signals' => [
                'approve' => 6.0, 'approved' => 5.5, 'accept' => 4.5, 'accepted' => 4.0,
                'agree' => 3.5, 'go ahead' => 5.0, 'proceed' => 4.5, 'confirm' => 4.5,
                'do it' => 4.0, 'apply it' => 4.0, 'sign off' => 5.0, 'authorise' => 5.0,
                'authorize' => 5.0, 'ok do' => 3.0, 'yes' => 2.0,
            ],
            'patterns' => [
                '/\bapprove\b/i',
                '/\b(go ahead|proceed|sign[\s-]?off|do it)\b/i',
                '/\b(accept|confirm)\b.{0,25}\brecommendation\b/i',
                // A sentence that opens with the verb is a command, not a question.
                // Without this, "approve the workflow step" scores as a status enquiry,
                // because "workflow" and "step" are heavy words in that intent.
                '/^\s*(please\s+)?(approve|confirm|accept|authorise|authorize)\b/i',
                '/\b(approve|confirm)\b.{0,25}\b(step|activities|activity|draft|workflow)\b/i',
            ],
            'slots' => ['recommendation'],
        ],

        'reject_recommendation' => [
            'label' => 'Reject the recommendation',
            'description' => 'Records a human rejection; nothing downstream runs.',
            'anchors' => ['reject', 'decline', 'no', 'cancel', 'dismiss', 'refuse'],
            'signals' => [
                'reject' => 6.0, 'decline' => 5.0, 'dismiss' => 4.5, 'refuse' => 4.5,
                'cancel' => 4.0, 'do not' => 3.0, "don't" => 3.0, 'not now' => 3.0,
                'disagree' => 4.0, 'no thanks' => 3.5,
            ],
            'patterns' => [
                '/\b(reject|decline|dismiss|refuse)\b/i',
                '/\b(do not|don\'t)\b.{0,20}\b(approve|proceed|do it)\b/i',
                '/^\s*(please\s+)?(reject|decline|dismiss|refuse)\b/i',
                '/\b(reject|decline)\b.{0,25}\b(step|activities|activity|draft|workflow)\b/i',
            ],
            'slots' => ['recommendation'],
        ],

        'workflow_status' => [
            'label' => 'What happened after approval',
            'description' => 'Reads the workflow run: every step, in order, with its state.',
            'anchors' => ['workflow', 'process', 'status', 'happened', 'progress', 'step'],
            'signals' => [
                'workflow' => 5.0, 'process' => 3.5, 'status' => 3.5, 'progress' => 4.0,
                'what happened' => 4.5, 'steps' => 4.0, 'step' => 3.0,
                'after approval' => 4.5, 'after i approved' => 4.5, 'running' => 2.5,
                'stage' => 2.5, 'where are we' => 3.5,
            ],
            'patterns' => [
                '/\b(workflow|process)\b.{0,20}\b(status|progress|steps?|state)\b/i',
                '/\bwhat happened\b/i',
                '/\bwhat (is|was) (created|done|assigned)\b/i',
                '/\bshow (me )?(the )?(steps|progress)\b/i',
            ],
            'slots' => ['case'],
        ],

        'outcome_status' => [
            'label' => 'Did the intervention work',
            'description' => 'Compares the recorded baseline against the latest measurement.',
            'anchors' => ['outcome', 'result', 'work', 'improve', 'better', 'effect', 'impact'],
            'signals' => [
                'outcome' => 5.0, 'outcomes' => 5.0, 'did it work' => 5.5,
                'is it working' => 5.0, 'improve' => 4.0, 'improved' => 4.0,
                'improvement' => 4.0, 'better' => 3.0, 'effect' => 3.5,
                'impact' => 3.5, 'result' => 3.0, 'results' => 3.0,
                'before and after' => 5.0, 'baseline' => 4.5, 'measure' => 3.5,
                'measured' => 3.5, 'progress since' => 4.0,
            ],
            'patterns' => [
                '/\bdid (it|this|the intervention) work\b/i',
                '/\b(outcome|baseline|before and after)\b/i',
                '/\b(has|did|have)\b.{0,25}\bimprove/i',
                '/\bany (change|difference|improvement)\b/i',
            ],
            'slots' => ['case'],
        ],

        'learning_effectiveness' => [
            'label' => 'What the system has learned',
            'description' => 'Aggregate effectiveness of each action type, from measured outcomes.',
            'anchors' => ['learn', 'learned', 'effective', 'effectiveness', 'history', 'usually'],
            'signals' => [
                'learn' => 4.5, 'learned' => 5.0, 'learning' => 5.0,
                'effective' => 5.0, 'effectiveness' => 5.5, 'success rate' => 5.0,
                'how often' => 4.0, 'usually' => 3.0, 'historically' => 4.0,
                'track record' => 4.5, 'does this work' => 3.5, 'in general' => 2.5,
                'across students' => 3.0, 'over time' => 3.0,
            ],
            'patterns' => [
                '/\b(what has|what have)\b.{0,25}\blearn/i',
                '/\b(effectiveness|success rate|track record)\b/i',
                '/\bhow (well|often)\b.{0,30}\b(work|works|worked|effective)\b/i',
            ],
            'slots' => [],
        ],
    ];

    /**
     * Classify one utterance.
     *
     * @param  array<string, mixed>  $memory  Referents carried from earlier turns.
     */
    public function classify(string $utterance, array $memory = []): Intent
    {
        $normalised = $this->normalise($utterance);

        if ($normalised === '') {
            return $this->unknown();
        }

        $scores = [];
        $matches = [];

        foreach (self::INTENTS as $key => $definition) {
            [$score, $matched] = $this->score($normalised, $definition);

            if ($score > 0) {
                $scores[$key] = $score;
                $matches[$key] = $matched;
            }
        }

        if ($scores === []) {
            return $this->unknown();
        }

        arsort($scores);
        $best = array_key_first($scores);
        $bestScore = $scores[$best];

        // Confidence is the winner's share of everything that scored. A sentence that
        // fits one intent and nothing else scores near 1; a genuinely ambiguous one
        // sits near 0.5 and, if it drops below the floor, is refused rather than run.
        $confidence = $bestScore / max(0.001, array_sum($scores));

        // A decisive phrase match is worth more than the arithmetic suggests: "approve"
        // is not ambiguous just because the sentence also contains "student".
        if (! empty($matches[$best]['patterns'])) {
            $confidence = min(1.0, $confidence + 0.25);
        }

        if ($confidence < self::CONFIDENCE_FLOOR) {
            return $this->unknown(array_slice(array_keys($scores), 0, 3));
        }

        $slots = $this->extractSlots($utterance, $normalised);

        return new Intent(
            key: $best,
            label: self::INTENTS[$best]['label'],
            confidence: $confidence,
            slots: $slots,
            matched: $matches[$best],
        );
    }

    /**
     * The intents this module understands — used by the console to show the user what
     * they can ask, and by the test plan as the list to work through.
     */
    public function catalogue(): array
    {
        return array_map(fn ($key, $definition) => [
            'key' => $key,
            'label' => $definition['label'],
            'description' => $definition['description'],
            'requires' => $definition['slots'],
        ], array_keys(self::INTENTS), self::INTENTS);
    }

    public static function requiredSlots(string $intentKey): array
    {
        return self::INTENTS[$intentKey]['slots'] ?? [];
    }

    // ---------------------------------------------------------------- internals

    /**
     * @return array{0:float, 1:array{anchors:array, signals:array, patterns:array}}
     */
    private function score(string $normalised, array $definition): array
    {
        $matched = ['anchors' => [], 'signals' => [], 'patterns' => []];
        $score = 0.0;

        foreach ($definition['anchors'] as $anchor) {
            if ($this->contains($normalised, $anchor)) {
                $matched['anchors'][] = $anchor;
                $score += 0.5;
            }
        }

        foreach ($definition['signals'] as $term => $weight) {
            if ($this->contains($normalised, $term)) {
                $matched['signals'][] = $term;
                $score += $weight;
            }
        }

        foreach ($definition['patterns'] as $pattern) {
            if (preg_match($pattern, $normalised)) {
                $matched['patterns'][] = $pattern;
                $score += 6.0;
            }
        }

        return [$score, $matched];
    }

    /**
     * Whole-word containment, so "no" does not match "enrolment" and "do" does not
     * match "doing".
     */
    private function contains(string $haystack, string $needle): bool
    {
        return (bool) preg_match('/(?<![a-z])' . preg_quote($needle, '/') . '(?![a-z])/i', $haystack);
    }

    private function normalise(string $utterance): string
    {
        $value = mb_strtolower(trim($utterance));
        $value = str_replace(['’', '‘'], "'", $value);
        $value = preg_replace('/[^\p{L}\p{N}\'\-\s]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    /**
     * Pull explicit referents out of the sentence: a case reference, a recommendation
     * reference, an enrolment number, a quoted name, or "student 42".
     *
     * Anything not found here is filled from conversation memory by ConversationStore,
     * and the trace shows which was which.
     */
    private function extractSlots(string $raw, string $normalised): array
    {
        $slots = [];

        if (preg_match('/\bCASE-\d{4}-\d+\b/i', $raw, $m)) {
            $slots['case_reference'] = strtoupper($m[0]);
        }

        if (preg_match('/\bREC-\d{4}-\d+\b/i', $raw, $m)) {
            $slots['recommendation_reference'] = strtoupper($m[0]);
        }

        if (preg_match('/\bRUN-\d{4}-\d+\b/i', $raw, $m)) {
            $slots['run_reference'] = strtoupper($m[0]);
        }

        // "case 12", "recommendation 7", "student 45"
        if (preg_match('/\bcase\s+#?(\d{1,9})\b/i', $raw, $m)) {
            $slots['case_id'] = (int) $m[1];
        }

        if (preg_match('/\brecommendation\s+#?(\d{1,9})\b/i', $raw, $m)) {
            $slots['recommendation_id'] = (int) $m[1];
        }

        if (preg_match('/\bstudent\s+(?:id\s+)?#?(\d{1,9})\b/i', $raw, $m)) {
            $slots['student_id'] = (int) $m[1];
        }

        // The worked example labels students "Student A" — support it explicitly so the
        // documented walkthrough works verbatim. Checked before the name rule, because
        // "Student A" would otherwise be read as somebody called A.
        if (preg_match('/\bstudent\s+([a-z])\b(?!\w)/i', $raw, $m)) {
            $slots['student_label'] = 'Student ' . strtoupper($m[1]);
        }

        // A quoted name, or a capitalised run after a phrase that introduces a person.
        // The lead-in is matched case-insensitively ("Why is …" starts a sentence) while
        // the name itself must stay capitalised, so "why is she at risk" does not
        // produce a student called "she".
        if (preg_match('/["\']([^"\']{2,60})["\']/', $raw, $m)) {
            $slots['student_name'] = trim($m[1]);
        } elseif (! isset($slots['student_label'])
            // The lead-in is case-insensitive because it often starts a sentence; the
            // name stays case-sensitive so the match stops at the first lowercase word.
            // Without that split, "Why is Ravi Kumar at risk?" yields "Ravi Kumar at risk".
            && preg_match('/(?i:why is|why was|about|for|of)\s+([A-Z][\w.\-]*(?:\s+[A-Z][\w.\-]*){0,3})/', $raw, $m)) {
            $slots['student_name'] = trim($m[1]);
        }

        // Scope qualifiers the agent understands.
        if (preg_match('/\bclass\s+([\w\-]{1,20})\b/i', $raw, $m)) {
            $slots['class_hint'] = $m[1];
        }

        if ($this->contains($normalised, 'high risk') || $this->contains($normalised, 'critical')) {
            $slots['severity_filter'] = 'high';
        }

        return $slots;
    }

    private function unknown(array $nearMisses = []): Intent
    {
        return new Intent(
            key: Intent::UNKNOWN,
            label: 'Not understood',
            confidence: 0.0,
            suggestions: [
                'Which students are at academic risk?',
                'Why is <student name> at risk?',
                'What evidence supports this?',
                'What should the teacher do?',
                'Approve the recommendation.',
                'What happened after approval?',
                'Did the intervention work?',
                'What has the system learned?',
            ],
            matched: $nearMisses === [] ? [] : ['near_misses' => $nearMisses],
        );
    }
}
