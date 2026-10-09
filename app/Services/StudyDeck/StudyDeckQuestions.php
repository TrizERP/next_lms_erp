<?php

namespace App\Services\StudyDeck;

use Illuminate\Support\Facades\DB;

/**
 * The question-bank rows a stored study deck points at, in the form the deck was built from.
 *
 * A deck stores question IDS, never question text (so a question edited in the bank changes in the deck). The PDF
 * therefore reads the rows again, with a plain SELECT, and normalises them with the same QuestionSelector the deck
 * generator used: stem, options, correct option, stored explanation, model answer. A question that has since been
 * deleted or switched off is simply left out of the PDF, the way the player leaves it out of its practice.
 */
class StudyDeckQuestions
{
    /** @return array<int,int> every bank question id a deck refers to, once each */
    public static function idsIn(array $deck): array
    {
        $ids = [];
        foreach ($deck['slides'] ?? [] as $slide) {
            foreach ($slide['activities'] ?? [] as $activity) {
                if (($activity['source'] ?? '') === 'bank' && !empty($activity['question_id'])) {
                    $ids[(int) $activity['question_id']] = true;
                }
            }
            foreach ($slide['question_ids'] ?? [] as $id) {
                $ids[(int) $id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param array<int,int> $ids
     * @return array<int,array<string,mixed>> normalised questions by id
     */
    public function load(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('lms_question_master as q')
            ->leftJoin('question_type_master as t', 't.id', '=', 'q.question_type_id')
            ->leftJoin('lms_question_extraction as e', 'e.question_id', '=', 'q.id')
            ->whereIn('q.id', $ids)->whereNull('q.deleted_at')->where('q.status', 1)
            ->get(['q.id', 'q.concept_id', 'q.question_title', 'q.points', 'q.answer', 'q.g_bloom', 'q.g_difficulty', 'q.g_dok', 'q.question_format_code', 'q.g_qtype_code', 'e.question_type_code as sidecar_code', 't.question_type']);

        $selector = new QuestionSelector();
        $out = [];
        foreach ($rows as $row) {
            $q = $selector->normalise((array) $row);
            $out[$q['id']] = $q;
        }

        return $out;
    }
}
