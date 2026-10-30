<?php

namespace App\Services\QuestionPapers;

/**
 * Chapter suggestion for ONE question of a paper, scoped to the paper's resolved standard and subject.
 *
 * Kept as the review screen's entry point (same method and keys as before), but the ranking is now the calibrated
 * QuestionPaperChapterMatcher: TF-IDF over the chapters' title, key concepts, description, topics, concepts, learning
 * outcomes and semantic intelligence, with thresholds measured against questions whose chapter is already known. One
 * implementation serves both the pipeline (automatic assignment) and this screen (the suggestion a teacher sees), so
 * they can never disagree.
 */
class QuestionPaperChapterSuggester
{
    public function __construct(private QuestionPaperChapterMatcher $matcher)
    {
    }

    /**
     * @return array{chapter_id: int, chapter_name: string, confidence: int, explanation: string, level: string, assigned: bool, score: float, margin: float, alternatives: list<array{id: int, name: string, score: float}>}|null
     */
    public function suggest(object $job, array $question): ?array
    {
        if (!$job->standard_id || !$job->subject_id || empty($question['question_text'])) {
            return null;
        }

        $s = $this->matcher->suggest((int) $job->sub_institute_id, (int) $job->standard_id, (int) $job->subject_id, [0 => $question])[0];
        if ($s['suggested_id'] === null) {
            return null;
        }

        return [
            'chapter_id' => (int) $s['suggested_id'],
            'chapter_name' => (string) $s['suggested_name'],
            // The share of comparable questions this level has named correctly in the measured set (see the matcher).
            'confidence' => (int) $s['confidence'],
            'explanation' => (string) $s['reason'],
            'level' => (string) $s['level'],
            'assigned' => (bool) $s['auto'],
            'score' => (float) $s['score'],
            'margin' => (float) $s['margin'],
            'alternatives' => $s['alternatives'],
        ];
    }
}
