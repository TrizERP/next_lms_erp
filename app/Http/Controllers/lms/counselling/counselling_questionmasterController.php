<?php

namespace App\Http\Controllers\lms\counselling;

use App\Http\Controllers\Controller;
use App\Models\lms\counselling\counsellingAnswerModel;
use App\Models\lms\counselling\counsellingQuestionMappingModel;
use App\Models\lms\counselling\counsellingQuestionModel;
use App\Models\lms\lmsmappingtypeModel;
use App\Models\lms\questiontypeModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class counselling_questionmasterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data['questionmaster_data'];
        $res['breadcrum_data'] = $data['breadcrum_data'];

        return is_mobile($type, 'lms/counselling/show_counselling_questionmaster', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $course_id = $request->get('course_id');


        $data['questionmaster_data'] = counsellingQuestionModel::select('counselling_question_master.*',
            'question_type', 'c.title as course_title')
            ->join('question_type_master as tm', 'tm.id', '=', 'counselling_question_master.question_type_id')
            ->join('counselling_course as c', 'c.id', '=', 'counselling_question_master.counselling_course_id')
            ->where([
                'counselling_question_master.sub_institute_id'      => $sub_institute_id,
                'counselling_question_master.counselling_course_id' => $course_id,
            ])
            ->orderby('counselling_question_master.id')
            ->get();

        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id, $request->get('course_id'));

        return $data;
    }

    public function getBreadcrum($sub_institute_id, $course_id)
    {
        $breadcrum_data = DB::table('counselling_course as c')
            ->selectRaw('title as course_title,id as course_id')
            ->where('c.sub_institute_id', $sub_institute_id)
            ->where('c.id', $course_id)->get()->toArray();

        return $breadcrum_data[0];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data['questiontype_data'] = questiontypeModel::select('*')->get();

        $data['course_id'] = $request->get('course_id');

        $lms_mapping_type = DB::table('lms_mapping_type')->where('parent_id', 0)
            ->where('globally', 1)->get()->toArray();

        $lms_mapping_type = json_decode(json_encode($lms_mapping_type), true);
        $data['lms_mapping_type'] = $lms_mapping_type;

        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id, $request->get('course_id'));

        return is_mobile($type, 'lms/counselling/add_counselling_questionmaster', $data, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        //echo ('<pre>');print_r($_REQUEST);die;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $status = $request->get('status');
        $status_val = $status ?? '';

        $multiple_answer = $request->get('multiple_answer');
        $multiple_answer_val = $multiple_answer ?? '';

        $question = [
            'question_type_id'      => $request->get('question_type_id'),
            'counselling_course_id' => $request->get('course_id'),
            'question_title'        => $request->get('question_title'),
            'description'           => $request->get('description'),
            'multiple_answer'       => $multiple_answer_val,
            'points'                => $request->get('points'),
            'status'                => $status_val,
            'created_by'            => $user_id,
            'sub_institute_id'      => $sub_institute_id,
        ];
        $question_id = counsellingQuestionModel::insertGetId($question);

        //START Insert into answer_master
        $mapping_type = $request->get('mapping_type');
        $mapping_value = $request->get('mapping_value');
        foreach ($mapping_type as $key => $val) {
            if ($val != "" && $mapping_value[$key] != "") {
                $contentmappingtype = [
                    'questionmaster_id' => $question_id,
                    'mapping_type_id'   => $val,
                    'mapping_value_id'  => $mapping_value[$key],
                ];
                counsellingQuestionMappingModel::insert($contentmappingtype);
            }
        }
        //END Insert into answer_master

        //START Insert into answer_master
        if ($request->get('question_type_id') == 1) {
            $option_arr = $request->get('options');
            foreach ($option_arr['NEW'] as $key => $val) {
                $correct_answer_val = 0;
                if ($request->has('correct_answer')) {
                    $correct_answer = $request->get('correct_answer');
                    $correct_answer_val = in_array($key, $correct_answer) ? 1 : 0;
                }

                $answer = [
                    'question_id'      => $question_id,
                    'answer'           => $val,
                    'correct_answer'   => $correct_answer_val,
                    'created_by'       => $user_id,
                    'sub_institute_id' => $sub_institute_id,
                ];
                counsellingAnswerModel::insert($answer);
            }
        }
        //END Insert into answer_master                               

        $res = [
            "status_code" => 1,
            "message"     => "Question-Master Added Successfully",
        ];
        $type = $request->input('type');

        return redirect()->route('lmsCounsellingQuestion.index', ['course_id' => $request->get('course_id')]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['questionmaster_data'] = counsellingQuestionModel::find($id)->toArray();

        ////GET Question Answer values
        $question_type_id = $data['questionmaster_data']['question_type_id'];
        if ($question_type_id == 1) // For multiple answer 
        {
            $ansData = counsellingAnswerModel::where(['sub_institute_id' => $sub_institute_id, 'question_id' => $id])
                ->get()->toArray();
            $data['answer_data'] = $ansData;
        }

        $data['questiontype_data'] = questiontypeModel::select('*')->get();

        ////GET Question Mapping values
        $question_mapping_type = counsellingQuestionMappingModel::where(['questionmaster_id' => $id])->get()->toArray();
        $i = 1;
        $final_question_mapping_type = [];
        foreach ($question_mapping_type as $key => $val) {
            $final_question_mapping_type[$i]['TYPE_ID'] = $val['mapping_type_id'];
            $final_question_mapping_type[$i]['VALUE_ID'] = $val['mapping_value_id'];
            $i++;
        }
        $data['question_mapping_data'] = $final_question_mapping_type;


        //GET LMS Mapping values
        $lms_mapping_type = DB::table('lms_mapping_type')->where('parent_id', 0)
            ->where('globally', 1)->get()->toArray();
        $lms_mapping_type = json_decode(json_encode($lms_mapping_type), true);
        foreach ($lms_mapping_type as $lkey => $lval) {
            $arr = lmsmappingtypeModel::where(['parent_id' => $lval['id']])->get()->toArray();
            foreach ($arr as $k => $v) {
                $lms_mapping_value[$lval['id']][$v['id']] = $v['name'];
            }
        }
        $data['lms_mapping_value'] = $lms_mapping_value;
        $data['lms_mapping_type'] = $lms_mapping_type;

        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id,
            $data['questionmaster_data']['counselling_course_id']);

        return is_mobile($type, "lms/counselling/edit_counselling_questionmaster", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $status = $request->get('status');
        $status_val = $status ?? '';

        // $multiple_answer = $request->get('multiple_answer');             
        // $multiple_answer_val = isset($multiple_answer) ? $multiple_answer : '';  

        $question = [
            'counselling_course_id' => $request->get('course_id'),
            'question_title'        => $request->get('question_title'),
            'description'           => $request->get('description'),
            'points'                => $request->get('points'),
            'status'                => $status_val,
            'created_by'            => $user_id,
            'sub_institute_id'      => $sub_institute_id,
        ];

        counsellingQuestionModel::where(["id" => $id])->update($question);

        if ($request->get('hid_question_type_id') == 1) {
            $option_arr = $request->get('options');
            foreach ($option_arr['EDIT'] as $key => $val) {
                $correct_answer_val = 0;
                if ($request->has('correct_answer')) {
                    $correct_answer = $request->get('correct_answer');
                    $correct_answer_val = in_array($key, $correct_answer) ? 1 : 0;
                }
                $answer = [
                    'question_id'      => $id,
                    'answer'           => $val,
                    'correct_answer'   => $correct_answer_val,
                    'created_by'       => $user_id,
                    'sub_institute_id' => $sub_institute_id,
                ];
                counsellingAnswerModel::where(["id" => $key])->update($answer);
            }
        }

        //START Delete and insert into question_mapping_Data
        counsellingQuestionMappingModel::where(["questionmaster_id" => $id])->delete();

        $mapping_type = $request->get('mapping_type');
        $mapping_value = $request->get('mapping_value');

        foreach ($mapping_type as $key => $val) {
            if ($val != "" && $mapping_value[$key] != "") {
                $questionmappingtype = [
                    'questionmaster_id' => $id,
                    'mapping_type_id'   => $val,
                    'mapping_value_id'  => $mapping_value[$key],
                ];
                counsellingQuestionMappingModel::insert($questionmappingtype);
            }
        }
        //END Delete and insert into question_mapping_Data    

        $res = [
            "status_code" => 1,
            "message"     => "Question-Master Updated Successfully",
        ];
        $type = $request->input('type');

        //return is_mobile($type, "question_master.index", $res, "redirect");
        return redirect()->route('lmsCounsellingQuestion.index', ['course_id' => $request->get('course_id')]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return RedirectResponse
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        $questiondata = counsellingQuestionModel::where(["id" => $id])->get()->toArray();
        $course_id = $questiondata[0]['counselling_course_id'];

        counsellingQuestionModel::where(["id" => $id])->delete();
        counsellingAnswerModel::where(["question_id" => $id])->delete();
        counsellingQuestionMappingModel::where(["questionmaster_id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Question-Master Deleted Successfully";

        return redirect()->route('lmsCounsellingQuestion.index', ['course_id' => $course_id]);
    }

    public function ajaxdestroycounsellinganswer_master(Request $request)
    {
        $id = $request->input('id');
        counsellingAnswerModel::where(["id" => $id])->delete();
    }

}
