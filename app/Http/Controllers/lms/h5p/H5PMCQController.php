<?php

namespace App\Http\Controllers\lms\h5p;

use App\Http\Controllers\Controller;
use App\Services\lms\H5P\QuestionBankSource;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;

class H5PMCQController extends Controller
{
    public function __construct(private QuestionBankSource $source)
    {
    }

    /**
     * The MCQ quiz: pick a difficulty, get ten questions at it.
     *
     * LEVELS AND QUESTIONS BOTH COME FROM `lms_question_master` NOW.
     *
     * This screen used to list the fixed `lms_mapping_type` tree under parent
     * id 9 and then match questions through `lms_question_mapping`. That made a
     * question's difficulty a fact recorded in two places, and the second one
     * had to be written per question for the quiz to find it. On chapter 1012,
     * institute 1, that returned 1 question at Easy, 6 at Medium and 4 at Hard
     * out of 726 playable questions -- a menu with no kitchen behind it.
     *
     * Difficulty is `g_difficulty`, a stored generated column holding
     * `answer -> $.difficulty` and already indexed as part of
     * `idx_qm_blueprint`. The same three levels now draw a full ten each.
     *
     * AND `question_type_id = 1` IS GONE. It restricted this to rows the
     * grading engine spells "multiple", which describes how a question was
     * CATALOGUED rather than whether it can be played here. `QuestionBankSource`
     * asks the question that matters -- does it have at least two options? --
     * and resolves the form through `question_type_catalog` instead.
     *
     * EVERY OTHER H5P TYPE USES THE SAME SERVICE, through
     * `GET /h5p/question_bank/{h5pType}`. This controller keeps its own action
     * only because its response shape predates that endpoint and the Blade view
     * reads it.
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');

        if (in_array($type, ['API', 'JSON'])) {
            $sub_institute_id = $request->sub_institute_id;
        }

        $selectedLevel = trim((string) $request->input('selectedLevel', ''));

        $res['mcq_levels'] = $this->source->levels($request, $sub_institute_id, 'h5p_mcq');

        $questionList = $answer = [];

        if ($selectedLevel !== '') {
            $questions = $this->source->questions(
                $request,
                $sub_institute_id,
                'h5p_mcq',
                $selectedLevel,
                10
            );

            foreach ($questions as $k => $v) {
                $questionList[$k]['question_id'] = $v->id;
                $questionList[$k]['question_text'] = $v->question_title;
            }

            // One query for every option on the paper. Ten questions used to
            // cost ten round trips, one per question.
            $answer = $this->source->answersFor($questions->pluck('id')->all(), $sub_institute_id);
        }

        $res['chapter_id'] = $request->input('chapter_id');
        $res['subject_id'] = $request->input('subject_id');
        $res['standard_id'] = $request->input('standard_id');
        $res['selectedLevel'] = $selectedLevel !== '' ? $selectedLevel : null;
        $res['question_arr'] = $questionList;
        $res['answer_arr'] = $answer;

        return is_mobile($type, 'lms/h5p/mcq/index', $res, "view");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
