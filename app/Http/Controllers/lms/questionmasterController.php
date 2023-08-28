<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\answermasterModel;
use App\Models\lms\chapterModel;
use App\Models\lms\lmsmappingtypeModel;
use App\Models\lms\lmsQuestionMappingModel;
use App\Models\lms\lmsQuestionMasterModel;
use App\Models\lms\questionmasterModel;
use App\Models\lms\questionpaperModel;
use App\Models\lms\questiontypeModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class questionmasterController extends Controller
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

        return is_mobile($type, 'lms/show_questionmaster', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data['questionmaster_data'] = array();

        $where_condition = array();

        if ($request->has('chapter_id')) {
            $where_condition['cm.id'] = $request->get('chapter_id');
        }

        if ($request->has('topic_id')) {
            $where_condition['lms_question_master.topic_id'] = $request->get('topic_id');
        }

        $where_condition['lms_question_master.sub_institute_id'] = $sub_institute_id;

        $data['questionmaster_data'] = lmsQuestionMasterModel::select('lms_question_master.*',
            'standard.name as standard_name',
            'academic_section.title as grade_name', 'subject_name', 'chapter_name', 'question_type',DB::raw('group_concat(t1.name) as type_name'))
            ->join('standard', 'standard.id', '=', 'lms_question_master.standard_id')
            ->join('academic_section', 'academic_section.id', '=', 'lms_question_master.grade_id')
            ->join('subject', 'subject.id', '=', 'lms_question_master.subject_id')
            ->join('chapter_master as cm', 'cm.id', '=', 'lms_question_master.chapter_id')
            //->join('topic_master as tj', 'tj.id', '=', 'chapter_master.topic_id')
            ->join('question_type_master as tm', 'tm.id', '=', 'lms_question_master.question_type_id')
            ->LeftJoin('lms_question_mapping as ltm', 'ltm.questionmaster_id', '=', 'lms_question_master.id')            
            ->LeftJoin('lms_mapping_type as t', 't.id', 'ltm.mapping_type_id')
            ->LeftJoin('lms_mapping_type as t1', function($query) {
                $query->on('t1.id', 'ltm.mapping_value_id');
            })
            ->where($where_condition)
            ->orderby('lms_question_master.id')
            ->groupBy('lms_question_master.id')
            ->get();

        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id, $request->get('chapter_id'),
            $request->get('topic_id'));

        return $data;
    }

    public function getBreadcrum($sub_institute_id, $chapter_id, $topic_id = '')
    {
        $where = '';
        $select = '';

        // Get breadcrum
        $breadcrum_data = DB::table('chapter_master as c')
            ->select(
                'c.subject_id',
                's.display_name AS subject_name',
                'c.standard_id',
                'st.name AS standard_name',
                'c.id AS chapter_id',
                'c.chapter_name'
            )->join('sub_std_map as s', function ($query) {
                $query->on('s.subject_id', '=', 'c.subject_id')
                    ->on('s.standard_id', '=', 'c.standard_id');
            })
            ->join('standard as st', 'st.id', '=', "c.standard_id")
            ->where('c.id',$chapter_id);

        if ($topic_id) {
            $breadcrum_data->addSelect('t.id as topic_id', 't.name as topic_name');
            $breadcrum_data->join('topic_master as t', 't.chapter_id', '=', 'c.id');
        }

        // dd($breadcrum_data);
        if (!empty($breadcrum_data)) {
            return $breadcrum_data->first();
        } else {
            return 0;
        }
    }

    /**
     * Display a listing of the Chapter resource.
     *
     * @return Response
     */
    public function indexChapter(Request $request)
    {
        $data = $this->getChepterData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data['questionmaster_data'];
        $res['breadcrum_data'] = $data['breadcrum_data'];
        // echo "<pre>";print_r($data);exit;
        return is_mobile($type, 'lms/show_chapter_questionmaster', $res, "view");
    }

    /**
     * get data by chapter
     */
    public function getChepterData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data['questionmaster_data'] = array();

        $where_condition = array();

        if ($request->has('chapter_id')) {
            $where_condition['cm.id'] = $request->get('chapter_id');
            $where_condition['lms_question_master.topic_id'] = null;
        }

        $where_condition['lms_question_master.sub_institute_id'] = $sub_institute_id;

        $data['questionmaster_data'] = lmsQuestionMasterModel::select('lms_question_master.*',
            'standard.name as standard_name',
            'academic_section.title as grade_name', 'subject_name', 'chapter_name', 'question_type'
            ,DB::raw('group_concat(t1.name) as type_name')
            // , 't.id as type_id'
            // , 't1.name as value_name', 't1.id as value_id'
            )
            ->join('standard', 'standard.id', '=', 'lms_question_master.standard_id')
            ->join('academic_section', 'academic_section.id', '=', 'lms_question_master.grade_id')
            ->join('subject', 'subject.id', '=', 'lms_question_master.subject_id')
            ->join('chapter_master as cm', 'cm.id', '=', 'lms_question_master.chapter_id')
            ->join('question_type_master as tm', 'tm.id', '=', 'lms_question_master.question_type_id')
            ->LeftJoin('lms_question_mapping as ltm', 'ltm.questionmaster_id', '=', 'lms_question_master.id')            
            ->LeftJoin('lms_mapping_type as t', 't.id', 'ltm.mapping_type_id')
            ->LeftJoin('lms_mapping_type as t1', function($query) {
                $query->on('t1.id', 'ltm.mapping_value_id');
            })
            ->where($where_condition)
            ->orderby('lms_question_master.id')
            ->groupBy('lms_question_master.id')
            ->get();

        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id, $request->get('chapter_id'));

        return $data;
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

        $chapter_data = chapterModel::where('id', $request->get('chapter_id'))->get()->toArray();
        $data['grade_id'] = $chapter_data[0]['grade_id'];
        $data['standard_id'] = $chapter_data[0]['standard_id'];
        $data['subject_id'] = $chapter_data[0]['subject_id'];
        $data['chapter_id'] = $request->get('chapter_id');

        // if topic id exist
        $where = '';
        if ($request->get('topic_id')) {
            $data['topic_id'] = $request->get('topic_id');
            $where = "and (topic_id = '".$request->get('topic_id')."' or topic_id = 0)";
        }

        $lms_mapping_type = DB::select("SELECT * FROM lms_mapping_type WHERE status=1 AND parent_id=0 AND
                                (globally=1 OR chapter_id = '".$request->get('chapter_id')."') $where");
        $lms_mapping_type = json_decode(json_encode($lms_mapping_type), true);
        $data['lms_mapping_type'] = $lms_mapping_type;

        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id, $request->get('chapter_id'),
            $request->get('topic_id'));

        //GET lms mapping from lmsmappingController
        if ($request->get('topic_id')) {
            $request->request->remove('topic_id');
        }

        $lms_data = app('App\Http\Controllers\lms\lmsmappingController')->getData($request);
        $data['lms_mapping_data'] = $lms_data['final_data'];

        return is_mobile($type, 'lms/add_questionmaster', $data, "view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return RedirectResponse|Response
     */
    public function store(Request $request)
    {
        // echo ('<pre>');print_r($_REQUEST);die;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $status = $request->get('status');
        $status_val = isset($status) ? $status : '';

        $multiple_answer = $request->get('multiple_answer');
        $multiple_answer_val = isset($multiple_answer) ? $multiple_answer : '';

        $pre_topic = $post_topic = $cross_curriculum_topic = "";
        if ($request->get('prechapter') != "") {
            $pre_topic = $request->get('prechapter').'####'.$request->get('pretopic');
        }
        if ($request->get('postchapter') != "") {
            $post_topic = $request->get('postchapter').'####'.$request->get('posttopic');
        }
        if ($request->get('cross-curriculumchapter') != "") {
            $cross_curriculum_topic = $request->get('cross-curriculumchapter').'####'.$request->get('cross-curriculumtopic');
        }

        $question = array(
            'question_type_id'             => $request->get('question_type_id'),
            'grade_id'                     => $request->get('grade_id'),
            'standard_id'                  => $request->get('standard_id'),
            'subject_id'                   => $request->get('subject_id'),
            'chapter_id'                   => $request->get('chapter_id'),
            'topic_id'                     => $request->get('topic_id'),
            'question_title'               => $request->get('question_title'),
            'description'                  => $request->get('description'),
            'multiple_answer'              => $multiple_answer_val,
            'pre_grade_topic'              => $pre_topic,
            'post_grade_topic'             => $post_topic,
            'cross_curriculum_grade_topic' => $cross_curriculum_topic,
            'points'                       => $request->get('points'),
            'status'                       => $status_val,
            'created_by'                   => $user_id,
            'sub_institute_id'             => $sub_institute_id,
            'hint_text'                    => $request->get('hint_text'),
            'learning_outcome'             => $request->get('learning_outcome'),
        );
        $question_id = lmsQuestionMasterModel::insertGetId($question);
        // echo "<pre>";print_r($question);

        //START Insert into answer_master
        $mapping_type = $request->get('mapping_type');
        $mapping_value = $request->get('mapping_value');
        $reasons = $request->get('reasons');
        
        foreach ($mapping_type as $key => $val) {
            if ($val != "" && $mapping_value[$key] != "") {
                $contentmappingtype = array(
                    'questionmaster_id' => $question_id,
                    'mapping_type_id'   => $val,
                    'mapping_value_id'  => $mapping_value[$key],
                    'mapping_value_id'  => $mapping_value[$key], 
                    'reasons' => $reasons[$key],                   
                );
        // echo "<pre>";print_r($contentmappingtype);
                
                lmsQuestionMappingModel::insert($contentmappingtype);
            }
        }
        //END Insert into answer_master

        //START Insert into answer_master
        if ($request->get('question_type_id') == 1 || $request->get('question_type_id') == 8) {
            $option_arr = $request->get('options');
            $feedback_arr = $request->get('feedback');
            foreach ($option_arr['NEW'] as $key => $val) {
                $correct_answer_val = 0;
                if ($request->has('correct_answer')) {
                    $correct_answer = $request->get('correct_answer');
                    $correct_answer_val = in_array($key, $correct_answer) ? 1 : 0;
                }

                $answer = array(
                    'question_id'      => $question_id,
                    'answer'           => $val,
                    'feedback'         => $feedback_arr['NEW'][$key],
                    'correct_answer'   => $correct_answer_val,
                    'created_by'       => $user_id,
                    'sub_institute_id' => $sub_institute_id,
                );

                answermasterModel::insert($answer);
            }
        }
        //END Insert into answer_master
// exit;
        $res = array(
            "status_code" => 1,
            "message"     => "Question-Master Added Successfully",
        );
        $type = $request->input('type');

        // return array
        if ($request->get('topic_id')) {
            return redirect()->route('question_master.index',
                ['chapter_id' => $request->get('chapter_id'), 'topic_id' => $request->get('topic_id'),'standard_id'=>$request->get('standard_id')]);
        } else {
            return redirect()->route('question_chapter_master', ['chapter_id' => $request->get('chapter_id'),'standard_id'=>$request->get('standard_id')]);
        }

        //return is_mobile($type, "question_master.index", $res, "redirect");
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

        $data['questionmaster_data'] = lmsQuestionMasterModel::find($id)->toArray();

        ////GET Question Answer values
        $question_type_id = $data['questionmaster_data']['question_type_id'];
        if ($question_type_id == 1 || $question_type_id == 8) // For multiple answer
        {
            $ansData = answermasterModel::where(['sub_institute_id' => $sub_institute_id, 'question_id' => $id])
                ->get()->toArray();
            $data['answer_data'] = $ansData;
        }

        $data['questiontype_data'] = questiontypeModel::select('*')->get();

        ////GET Question Mapping values
        $question_mapping_type = lmsQuestionMappingModel::where(['questionmaster_id' => $id])->get()->toArray();
        $i = 1;
        $final_question_mapping_type = array();
        foreach ($question_mapping_type as $key => $val) {
            $final_question_mapping_type[$i]['TYPE_ID'] = $val['mapping_type_id'];
            $final_question_mapping_type[$i]['VALUE_ID'] = $val['mapping_value_id'];
            $final_question_mapping_type[$i]['REASONS'] = $val['reasons'];
            $i++;
        }
        $data['question_mapping_data'] = $final_question_mapping_type;
        // echo "<pre>";print_r($data['question_mapping_data']);

        //GET LMS Mapping values
        $lms_mapping_type = DB::table('lms_mapping_type')
            ->where('status', '=', 1)
            ->where('parent_id', '=', 0)
            ->where(function ($q) use ($data) {
                $q->where('globally', '=', 1)
                    ->orWhere('chapter_id', $data['questionmaster_data']['chapter_id']);
            })->where(function ($q) use ($data) {
                $q->where('topic_id', '=', $data['questionmaster_data']['topic_id'])
                    ->orWhere('topic_id', '=', 0);
            })->get()->toArray();
        $lms_mapping_type = json_decode(json_encode($lms_mapping_type), true);
        foreach ($lms_mapping_type as $lkey => $lval) {
            $arr = lmsmappingtypeModel::where(['parent_id' => $lval['id']])->get()->toArray();
            foreach ($arr as $k => $v) {
                $lms_mapping_value[$lval['id']][$v['id']] = $v['name'];
            }
        }
        // exit;
        $data['lms_mapping_value'] = $lms_mapping_value;
        $data['lms_mapping_type'] = $lms_mapping_type;

        //START Get Pre Topic
        $data['pretopicData'] = [];
        if ($data['questionmaster_data']['pre_grade_topic'] != "") {
            $pre_arr = explode("####", $data['questionmaster_data']['pre_grade_topic']);
            $pre_arr_chapter_id = $pre_arr[0];
            $pre_arr_topic_id = $pre_arr[1];

            //If both chapter and topic are mapped
            if ($pre_arr_chapter_id != "" && $pre_arr_topic_id != "") {
                $pretopicData = DB::table('topic_master as t')
                    ->join('chapter_master as c', function ($join) {
                        $join->whereRaw('c.id = t.chapter_id');
                    })
                    ->selectRaw('t.id as topic_id,c.id AS chapter_id,c.standard_id,c.subject_id')
                    ->where('t.id', '=', $pre_arr_topic_id)
                    ->get()->toArray();
            } else {
                if ($pre_arr_chapter_id != "") //If only chapter is mapped
                {
                    $pretopicData = DB::table('chapter_master as c')
                        ->selectRaw('c.id AS chapter_id,c.standard_id,c.subject_id')
                        ->where('c.id', '=', $pre_arr_chapter_id)
                        ->get()->toArray();

                }
            }

            $pretopicData = json_decode(json_encode($pretopicData), true);
            $data['pretopicData'] = $pretopicData[0];
        }
        //END Get Pre Topic

        //START Get Post Topic
        $data['posttopicData'] = [];
        if ($data['questionmaster_data']['post_grade_topic'] != "") {
            $post_arr = explode("####", $data['questionmaster_data']['post_grade_topic']);
            $post_arr_chapter_id = $post_arr[0];
            $post_arr_topic_id = $post_arr[1];

            //If both chapter and topic are mapped
            if ($post_arr_chapter_id != "" && $post_arr_topic_id != "") {
                $posttopicData = DB::table('topic_master as t')
                    ->join('chapter_master as c', function ($join) {
                        $join->whereRaw('c.id = t.chapter_id');
                    })
                    ->selectRaw('t.id as topic_id,c.id AS chapter_id,c.standard_id,c.subject_id')
                    ->where('t.id', '=', $post_arr_topic_id)
                    ->get()->toArray();

            } else {
                if ($post_arr_chapter_id != "") //If only chapter is mapped
                {
                    $posttopicData = DB::table('chapter_master as c')
                        ->selectRaw('c.id AS chapter_id,c.standard_id,c.subject_id')
                        ->where('c.id', '=', $post_arr_chapter_id)
                        ->get()->toArray();
                }
            }
            $posttopicData = json_decode(json_encode($posttopicData), true);
            $data['posttopicData'] = $posttopicData[0];
        }
        //END Get Post Topic

        //START Get Cross curriculum Topic
        $data['cctopicData'] = [];
        if ($data['questionmaster_data']['cross_curriculum_grade_topic'] != "") {
            $cc_arr = explode("####", $data['questionmaster_data']['cross_curriculum_grade_topic']);
            $cc_arr_chapter_id = $cc_arr[0];
            $cc_arr_topic_id = $cc_arr[1];

            //If both chapter and topic are mapped
            if ($cc_arr_chapter_id != "" && $cc_arr_topic_id != "") {
                $cctopicData = DB::table('topic_master as t')
                    ->join('chapter_master as c', function ($join) {
                        $join->whereRaw('c.id = t.chapter_id');
                    })
                    ->selectRaw('t.id as topic_id,c.id AS chapter_id,c.standard_id,c.subject_id')
                    ->where('t.id', '=', $cc_arr_topic_id)
                    ->get()->toArray();

            } else {
                if ($cc_arr_chapter_id != "") //If only chapter is mapped
                {
                    $cctopicData = DB::table('chapter_master as c')
                        ->selectRaw('c.id AS chapter_id,c.standard_id,c.subject_id')
                        ->where('c.id', '=', $cc_arr_chapter_id)
                        ->get()->toArray();
                }
            }
            $cctopicData = json_decode(json_encode($cctopicData), true);
            $data['cctopicData'] = $cctopicData[0];
        }
        //END Get Cross curriculum Topic

        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id, $data['questionmaster_data']['chapter_id'],
            $data['questionmaster_data']['topic_id']);

        // condition for page call form chapter or topic
        if ($request->has('topic_id')) {
            $data['topic_id'] = $request->get('topic_id');
        }
        // echo "<pre>";print_r($data);exit;

        return is_mobile($type, "lms/edit_questionmaster", $data, "view");
    }


    public function update(Request $request, $id)
    {
        // dd($request->all());
        // echo ('<pre>');print_r($_REQUEST);die;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $status = $request->get('status');
        $status_val = $status ?? '';

        // $multiple_answer = $request->get('multiple_answer');
        // $multiple_answer_val = isset($multiple_answer) ? $multiple_answer : '';
        $pre_topic = $post_topic = $cross_curriculum_topic = "";
        if ($request->get('prechapter') != "") {
            $pre_topic = $request->get('prechapter').'####'.$request->get('pretopic');
        }
        if ($request->get('postchapter') != "") {
            $post_topic = $request->get('postchapter').'####'.$request->get('posttopic');
        }
        if ($request->get('cross-curriculumchapter') != "") {
            $cross_curriculum_topic = $request->get('cross-curriculumchapter').'####'.$request->get('cross-curriculumtopic');
        }

        $question = array(
            'grade_id'                     => $request->get('grade_id'),
            'standard_id'                  => $request->get('standard_id'),
            'subject_id'                   => $request->get('subject_id'),
            'chapter_id'                   => $request->get('chapter_id'),
            'topic_id'                     => $request->get('topic_id'),
            'question_title'               => $request->get('question_title'),
            'description'                  => $request->get('description'),
            'points'                       => $request->get('points'),
            'pre_grade_topic'              => $pre_topic,
            'post_grade_topic'             => $post_topic,
            'cross_curriculum_grade_topic' => $cross_curriculum_topic,
            'status'                       => $status_val,
            'created_by'                   => $user_id,
            'sub_institute_id'             => $sub_institute_id,
            'hint_text'                    => $request->get('hint_text'),
            'learning_outcome'             => $request->get('learning_outcome'),
        );

        lmsQuestionMasterModel::where(["id" => $id])->update($question);

        if ($request->get('hid_question_type_id') == 1) {
            $option_arr = $request->get('options');
            $feedback_arr = $request->get('feedback');
            foreach ($option_arr['EDIT'] as $key => $val) {
                $correct_answer_val = 0;
                if ($request->has('correct_answer')) {
                    $correct_answer = $request->get('correct_answer');
                    $correct_answer_val = in_array($key, $correct_answer) ? 1 : 0;
                }
                $answer = array(
                    'question_id'      => $id,
                    'answer'           => $val,
                    'feedback'         => $feedback_arr['EDIT'][$key],
                    'correct_answer'   => $correct_answer_val,
                    'created_by'       => $user_id,
                    'sub_institute_id' => $sub_institute_id,
                );
                answermasterModel::where(["id" => $key])->update($answer);
            }
        }

        //START Delete and insert into question_mapping_Data
        lmsQuestionMappingModel::where(["questionmaster_id" => $id])->delete();

        $mapping_type = $request->get('mapping_type');
        $mapping_value = $request->get('mapping_value');
        $reasons = $request->get('reasons');        

        foreach ($mapping_type as $key => $val) {
            if ($val != "" && $mapping_value[$key] != "") {
                $questionmappingtype = [
                    'questionmaster_id' => $id,
                    'mapping_type_id'   => $val,
                    'mapping_value_id'  => $mapping_value[$key],
                    'reasons'  => $reasons[$key],                    
                ];
                lmsQuestionMappingModel::insert($questionmappingtype);
            }
        }
        //END Delete and insert into question_mapping_Data

        $res = [
            "status_code" => 1,
            "message"     => "Question-Master Updated Successfully",
        ];
        $type = $request->input('type');
        //return is_mobile($type, "question_master.index", $res, "redirect");

        // return array
        if ($request->get('topic_id')) {
            return redirect()->route('question_master.index',
                ['chapter_id' => $request->get('chapter_id'), 'topic_id' => $request->get('topic_id'),'standard_id'=>$request->get('standard_id')]);
        } else {
            return redirect()->route('question_chapter_master', ['chapter_id' => $request->get('chapter_id'),'standard_id'=>$request->get('standard_id')]);
        }
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
        $questiondata = lmsQuestionMasterModel::where(["id" => $id])->get()->toArray();
        $chapter_id = $questiondata[0]['chapter_id'];
        $topic_id = $questiondata[0]['topic_id'];
        $standard_id = $questiondata[0]['standard_id'];        

        lmsQuestionMasterModel::where(["id" => $id])->delete();
        answermasterModel::where(["question_id" => $id])->delete();
        lmsQuestionMappingModel::where(["questionmaster_id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Question-Master Deleted Successfully";

        return redirect()->route('question_master.index', ['chapter_id' => $chapter_id, 'topic_id' => $topic_id,'standard_id'=>$standard_id]);
        //return is_mobile($type, "question_master.index", $res);
    }

    function ajaxdestroyanswer_master(Request $request)
    {
        $id = $request->input('id');
        answermasterModel::where(["id" => $id])->delete();
    }

    public function ajax_ChapterwiseLOmaster(Request $request)
    {
        $chapter_id = $request->input("chapter_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $lomasterData = questionmasterModel::where([
            'sub_institute_id' => $sub_institute_id, 'chapter_id' => $chapter_id,
        ])
            ->get()->toArray();

        return $lomasterData;
    }

    function ajax_questionDependencies(Request $request)
    {
        $question_id = $request->input("question_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $data = questionpaperModel::select(DB::raw('count(*) as total'))
            ->where('question_paper.sub_institute_id', $sub_institute_id)
            ->whereraw('find_in_set('.$question_id.',question_ids)')
            ->get()->toArray();

        return $data[0]['total'];
    }

    // Delete multiple questions
    public function ajax_multiDeleteQuestion(Request $request)
    {
        $question_ids = lmsQuestionMasterModel::whereIn('id', $request->question_ids)->delete();
        if ($question_ids) {
            $res['status_code'] = "1";
            $res['message'] = "Questions Deleted Successfully";

            return response()->json($res, 200);
        }
    }
}
