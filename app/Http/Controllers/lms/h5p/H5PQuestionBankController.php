<?php

namespace App\Http\Controllers\lms\h5p;

use App\Http\Controllers\Controller;
use App\Services\lms\H5P\QuestionBankSource;
use Illuminate\Http\Request;

/**
 * One endpoint that serves any H5P type its questions from the bank.
 *
 *     GET /h5p/question_bank/{h5pType}?type=API
 *         &standard_id=&subject_id=&chapter_id=&sub_institute_id=
 *         &selectedLevel=Easy&take=10&random=1
 *
 * WHY ONE ENDPOINT AND NOT SIXTEEN. Every H5P type asks the same question --
 * "which questions in this chapter can I render?" -- and the only thing that
 * differs is which `question_type_catalog` forms it can carry, which is a
 * table in `QuestionBankSource`, not a controller. Sixteen copies of this
 * would be sixteen places for the form ladder or the tenancy scope to drift.
 *
 * WHAT IT DOES NOT DO. It does not write. Nothing here creates an `h5p_*` row,
 * so a question rendered this way exists once, in `lms_question_master`, and
 * editing it there changes every screen that shows it.
 *
 * THE RESPONSE names its parts plainly -- `levels`, `questions`, `answers`.
 * The MCQ screen's older `mcq_levels` / `question_arr` / `answer_arr` keys are
 * kept alongside them so the existing client works against this endpoint
 * unchanged; they are aliases, not a second payload.
 */
class H5PQuestionBankController extends Controller
{
    public function __construct(private QuestionBankSource $source)
    {
    }

    public function index(Request $request, string $h5pType)
    {
        $sub_institute_id = session()->get('sub_institute_id');

        if (in_array($request->input('type'), ['API', 'JSON'])) {
            $sub_institute_id = $request->input('sub_institute_id', $sub_institute_id);
        }

        if (!QuestionBankSource::supports($h5pType)) {
            return response()->json([
                'status'  => false,
                'message' => "No question bank forms are mapped to $h5pType.",
                'h5p_type' => $h5pType,
                'supported' => array_keys(QuestionBankSource::TYPE_CODES),
            ], 404);
        }

        $level = trim((string) $request->input('selectedLevel', ''));
        $take  = (int) $request->input('take', 10);
        $random = $request->boolean('random', true);

        $questions = $this->source->questions(
            $request,
            $sub_institute_id,
            $h5pType,
            $level !== '' ? $level : null,
            $take,
            $random
        );

        $ids = $questions->pluck('id')->all();
        $answers = $this->source->answersFor($ids, $sub_institute_id);

        // `question_arr` has carried these two keys since the MCQ screen was
        // written; the rest is added rather than renamed so nothing that reads
        // it has to change.
        $shaped = $questions->map(fn ($q) => [
            'question_id'        => $q->id,
            'question_text'      => $q->question_title,
            'question_type_code' => $q->question_type_code,
            'marks'              => $q->points,
            'difficulty'         => $q->g_difficulty,
            'bloom'              => $q->g_bloom,
            'model_answer'       => $q->model_answer,
            'chapter_id'         => $q->chapter_id,
            'subject_id'         => $q->subject_id,
            'standard_id'        => $q->standard_id,
            'topic_id'           => $q->topic_id,
            'concept_id'         => $q->concept_id,
        ])->values()->toArray();

        $levels = $this->source->levels($request, $sub_institute_id, $h5pType);

        $res = [
            'status'        => true,
            'h5p_type'      => $h5pType,
            'codes'         => QuestionBankSource::codesFor($h5pType),
            'selectedLevel' => $level !== '' ? $level : null,

            'levels'        => $levels,
            'questions'     => $shaped,
            'answers'       => $answers,

            // Aliases, for the MCQ client that predates this endpoint.
            'mcq_levels'    => $levels,
            'question_arr'  => $shaped,
            'answer_arr'    => $answers,

            'chapter_id'    => $request->input('chapter_id'),
            'subject_id'    => $request->input('subject_id'),
            'standard_id'   => $request->input('standard_id'),
        ];

        return response()->json($res);
    }
}
