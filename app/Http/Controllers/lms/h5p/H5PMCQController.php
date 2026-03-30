<?php

namespace App\Http\Controllers\lms\h5p;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\DB;
use App\Models\lms\answermasterModel;

class H5PMCQController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');

        if (in_array($type, ['API', 'JSON'])) {
            $sub_institute_id = $request->sub_institute_id;
        }

        $res['mcq_levels'] = DB::table('lms_mapping_type as parent')
            ->join('lms_mapping_type as child', 'child.parent_id', '=', 'parent.id')
            ->where('parent.parent_id', 0)
            ->where('parent.id', 9)
            ->select('child.id', 'child.name')
            ->groupBy('child.id')
            ->get();
        $questionList = $answer = [];
        if ($request->has('selectedLevel') && !empty($request->input('selectedLevel'))) {
            $randomQuestions = DB::table('lms_question_master as lqm')
                ->join('lms_question_mapping as lm', 'lqm.id', '=', 'lm.questionmaster_id')
                ->join('answer_master as am', 'lqm.id', '=', 'am.question_id')
                ->select('lqm.*')
                ->where('lqm.sub_institute_id', $sub_institute_id)
                ->where('lqm.standard_id', $request->standard_id)
                ->where('lqm.subject_id', $request->subject_id)
                ->where('lqm.chapter_id', $request->chapter_id)
                ->where('lqm.question_type_id', 1)
                ->when($request->has('selectedLevel') && !empty($request->input('selectedLevel')), function ($query) use ($request) {
                    $query->where('lm.mapping_value_id', $request->selectedLevel);
                })
                ->inRandomOrder()
                ->take(10)
                ->groupBy('lqm.question_title')
                ->get()->toArray();
            foreach ($randomQuestions as $k => $v) {
                $questionList[$k]['question_id'] = $v->id;
                $questionList[$k]['question_text'] = $v->question_title;
            }
            
            $existQusetion = [];
            if (!empty($questionList)) {
                foreach ($questionList as $key => $val) {
                    if (!in_array($val['question_id'], $existQusetion)) {
                        $answer_arr = answermasterModel::where([
                            "question_id"      => $val['question_id'],
                            "sub_institute_id" => $sub_institute_id,
                        ])->get()->toArray();
                        if (count($answer_arr) > 0) {
                            foreach ($answer_arr as $anskey => $ansval) {
                                $answer[$val['question_id']][] = $ansval;
                            }
                        }
                        $existQusetion[] = $val['question_id'];
                    }
                }
            }
        }

        $res['chapter_id'] = $request->input('chapter_id');
        $res['subject_id'] = $request->input('subject_id');
        $res['standard_id'] = $request->input('standard_id');
        $res['selectedLevel'] = $request->input('selectedLevel');
        $res['question_arr'] = $questionList;
        $res['answer_arr'] = $answer;
        // return $res['question_arr'];
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
