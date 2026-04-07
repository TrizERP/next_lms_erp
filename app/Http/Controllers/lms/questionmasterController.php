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
use function App\Helpers\neo4jCreateNode;
use function App\Helpers\neo4jCreateRelationship;



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
        $res['lms_mapping_type'] = DB::table('lms_mapping_type')
        ->where('status', '=', 1)
        ->where('parent_id', '=', 0)
        ->where(function ($q) use ($request) {
            $q->where('globally', '=', 1)
                ->orWhere('chapter_id', $request->get('chapter_id'));
        })->where(function ($q) use ($request) {
            $q->where('topic_id', '=', 0)
                ->orWhere('topic_id', $request->get('topic_id'));
        })
        ->get()->toArray();
        
        // Get grade_id and subject_id from chapter_master table if not provided in request
        $chapter_id = $request->get('chapter_id');
        if ($chapter_id) {
            $chapterData = chapterModel::find($chapter_id);
            if ($chapterData) {
                $res['grade_id'] = $request->get('grade_id') ?? $chapterData->grade_id;
                $res['subject_id'] = $request->get('subject_id') ?? $chapterData->subject_id;
                $res['standard_id'] = $request->get('standard_id') ?? $chapterData->standard_id;
            } else {
                $res['grade_id'] = $request->get('grade_id');
                $res['subject_id'] = $request->get('subject_id');
                $res['standard_id'] = $request->get('standard_id');
            }
        } else {
            $res['grade_id'] = $request->get('grade_id');
            $res['subject_id'] = $request->get('subject_id');
            $res['standard_id'] = $request->get('standard_id');
        }
        $res['chapter_id'] = $request->get('chapter_id');
        $res['topic_id'] = $request->get('topic_id');
        
        // Get question types for AI question generation
        $res['questiontype_data'] = questiontypeModel::select('*')->get();
        
        // echo "<pre>";print_r($data);exit;
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

        $data['questionmaster_data'] = lmsQuestionMasterModel::select('lms_question_master.*','standard.name as standard_name','academic_section.title as grade_name', 'subject_name', 'chapter_name', 'question_type',DB::raw('group_concat(distinct t1.name SEPARATOR "||") as type_name'),DB::raw('IFNULL(loea.question_id,"0") as attempt_question')
        )
        ->join('standard', 'standard.id', '=', 'lms_question_master.standard_id')
        ->join('academic_section', 'academic_section.id', '=', 'lms_question_master.grade_id')
        ->join('subject', 'subject.id', '=', 'lms_question_master.subject_id')
        ->join('chapter_master as cm', 'cm.id', '=', 'lms_question_master.chapter_id')
        ->join('question_type_master as tm', 'tm.id', '=', 'lms_question_master.question_type_id')
        ->leftJoin('lms_question_mapping as ltm', 'ltm.questionmaster_id', '=', 'lms_question_master.id')            
        ->leftJoin('lms_mapping_type as t', 't.id', 'ltm.mapping_type_id')
        ->leftJoin('lms_mapping_type as t1', function($query) {
            $query->on('t1.id', 'ltm.mapping_value_id');
        })
        ->leftJoin('lms_online_exam_answer as loea','loea.question_id','=','lms_question_master.id')
        ->where($where_condition)
        ->orderBy('lms_question_master.id')
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

        // Execute the query and get the result
        $result = $breadcrum_data->first();
        
        // Check if result exists before returning
        if (!empty($result)) {
            return $result;
        } else {
            return null;
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
        $res['lms_mapping_type'] = DB::table('lms_mapping_type')
        ->where('status', '=', 1)
        ->where('parent_id', '=', 0)
        ->where(function ($q) use ($request) {
            $q->where('globally', '=', 1)
                ->orWhere('chapter_id', $request->get('chapter_id'));
        })->where(function ($q) use ($request) {
            $q->where('topic_id', '=', 0)
                ->orWhere('topic_id', $request->get('topic_id'));
        })
        ->get()->toArray();
        
        // Get grade_id and subject_id from chapter_master table if not provided in request
        $chapter_id = $request->get('chapter_id');
        if ($chapter_id) {
            $chapterData = chapterModel::find($chapter_id);
            if ($chapterData) {
                $res['grade_id'] = $request->get('grade_id') ?? $chapterData->grade_id;
                $res['subject_id'] = $request->get('subject_id') ?? $chapterData->subject_id;
                $res['standard_id'] = $request->get('standard_id') ?? $chapterData->standard_id;
            } else {
                $res['grade_id'] = $request->get('grade_id');
                $res['subject_id'] = $request->get('subject_id');
                $res['standard_id'] = $request->get('standard_id');
            }
        } else {
            $res['grade_id'] = $request->get('grade_id');
            $res['subject_id'] = $request->get('subject_id');
            $res['standard_id'] = $request->get('standard_id');
        }
        $res['chapter_id'] = $request->get('chapter_id');
        $res['topic_id'] = $request->get('topic_id');
        
        // Get question types for AI question generation
        $res['questiontype_data'] = questiontypeModel::select('*')->get();
        
        // echo "<pre>";print_r($res['lms_mapping_type']);exit;
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
            ,DB::raw('group_concat(DISTINCT t1.name SEPARATOR "||") as type_name'),
            DB::raw('IFNULL(loea.question_id,"0") as attempt_question')
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
            ->leftJoin('lms_online_exam_answer as loea','loea.question_id','=','lms_question_master.id')
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
    $sub_institute_id = $request->session()->get('sub_institute_id');

    DB::beginTransaction();

    try {

        // ================= SQL INSERT =================
        $question = lmsQuestionMasterModel::create([
            'question'         => $request->question,
            'chapter_id'       => $request->chapter_id,
            'subject_id'       => $request->subject_id,
            'standard_id'      => $request->standard_id,
            'grade_id'         => $request->grade_id,
            'question_type_id' => $request->question_type_id,
            'sub_institute_id' => $sub_institute_id,
            'created_by'       => session()->get('user_id')
        ]);

        $question_id = $question->id;

        DB::commit();

    } catch (\Exception $e) {
        DB::rollBack();

        \Log::error('❌ SQL Question Error: ' . $e->getMessage());

        return back()->with([
            'status_code' => 0,
            'message' => 'Error saving question'
        ]);
    }

    // ======================================================
    // ✅ NEO4J (CORRECT)
    // ======================================================

    try {

        // 🔹 QUESTION NODE
        neo4jCreateNode(
            'Question',
            ['qId' => (int)$question_id],
            [
                'question_id' => (int)$question_id,
                'text' => $request->question,
                'chapter_id' => (int)$request->chapter_id,
                'displayLabel' => 'Question:' . substr($request->question, 0, 30),
                'sub_institute_id' => (int)$sub_institute_id
            ]
        );

        // 🔹 CHAPTER NODE
        neo4jCreateNode(
            'Chapter',
            ['chId' => (int)$request->chapter_id],
            [
                'chapter_id' => (int)$request->chapter_id,
                'displayLabel' => 'Chapter:' . $request->chapter_id,
                'sub_institute_id' => (int)$sub_institute_id
            ]
        );

        // 🔹 RELATION
        neo4jCreateRelationship(
            'Question',
            ['qId' => (int)$question_id],
            'BELONGS_TO',
            'Chapter',
            ['chId' => (int)$request->chapter_id]
        );

        \Log::info('✅ Neo4j Question Created', [
            'qId' => $question_id
        ]);

    } catch (\Exception $e) {
        \Log::error('❌ Neo4j Question Error: ' . $e->getMessage());
    }

    return back()->with([
        'status_code' => 1,
        'message' => 'Question Added Successfully'
    ]);
}
    // ===== YOUR EXISTING CODE CONTINUES =====

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
    $sub_institute_id = $request->session()->get('sub_institute_id');

    DB::beginTransaction();

    try {

        $question = lmsQuestionMasterModel::where('id', $id)
            ->where('sub_institute_id', $sub_institute_id)
            ->first();

        if (!$question) {
            DB::rollBack();
            return back()->with([
                'status_code' => 0,
                'message' => 'Question not found'
            ]);
        }

        // ================= SQL UPDATE =================
        $question->update([
            'question'   => $request->question,
            'chapter_id' => $request->chapter_id
        ]);

        DB::commit();

    } catch (\Exception $e) {
        DB::rollBack();

        \Log::error('❌ SQL Update Question Error: ' . $e->getMessage());

        return back()->with([
            'status_code' => 0,
            'message' => 'Error updating question'
        ]);
    }

    // ======================================================
    // ✅ NEO4J UPDATE (CORRECT)
    // ======================================================

    try {

        // 🔹 UPDATE QUESTION NODE
        neo4jCreateNode(
            'Question',
            ['qId' => (int)$id],
            [
                'text' => $request->question,
                'chapter_id' => (int)$request->chapter_id,
                'updated_at' => now()->toDateTimeString()
            ]
        );

        // 🔹 ENSURE CHAPTER NODE
        neo4jCreateNode(
            'Chapter',
            ['chId' => (int)$request->chapter_id],
            []
        );

        // 🔹 RELATION
        neo4jCreateRelationship(
            'Question',
            ['qId' => (int)$id],
            'BELONGS_TO',
            'Chapter',
            ['chId' => (int)$request->chapter_id]
        );

        \Log::info('✅ Neo4j Question Updated', [
            'qId' => $id
        ]);

    } catch (\Exception $e) {
        \Log::error('❌ Neo4j Update Error: ' . $e->getMessage());
    }

    return back()->with([
        'status_code' => 1,
        'message' => 'Question Updated Successfully'
    ]);
}
    // ============================================

    // ===== YOUR EXISTING CODE CONTINUES =====

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

    public function getMappedValue(Request $request){
       $mappedType = DB::table('lms_question_mapping as ltm') 
                ->selectRaw('ltm.*,t.name as name,GROUP_CONCAT(mapping_value_id) as mappedVal')         
                ->join('lms_mapping_type as t', 't.id', 'ltm.mapping_type_id')
                ->where('ltm.questionmaster_id',$request->question_id)
                ->groupBy('mapping_type_id')
                ->get()->toArray();
            
        $mappedValues = [];
        foreach ($mappedType as $key => $value) {
            $mappedValues[$key] = $value;
            $mappedValues[$key]->mappedValue = DB::table('lms_mapping_type')->whereRaw('id in ('.$value->mappedVal.')')->get()->toArray();
        }
        $res['questionTitle'] =DB::table('lms_question_master')->where('id',$request->question_id)->value('question_title');
        $res['MappedData'] =  $mappedValues;
        return $res;
    }
}

