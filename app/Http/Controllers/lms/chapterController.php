<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use App\Models\lms\contentModel;
use App\Models\lms\topicModel;
use App\Models\school_setup\sub_std_mapModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class chapterController extends Controller
{
    public $searchArr = ["<p>", "</p>", "&nbsp;", "\n", "\r", "'", "<", '"'];
    public $replaceArr = ["", "", "", "", "", "", "", ''];

    public function index(Request $request)
    {
        $data = $this->getData($request);
        // echo "<pre>";print_r($data);exit;
        $type = $request->input('type');
        $res['sub_institute_id'] = session()->get('sub_institute_id');
        // 28-02-2025 starts     
        $lms_mapping_type = DB::table('lms_mapping_type')
        ->where('status', '=', 1)
        ->where('parent_id', '=', 0)
        ->where(function ($q) use ($request) {
            $q->where('globally', '=', 1)
                ->orWhere('chapter_id', $request->get('chapter_id'));
        })->where(function ($q) use ($request) {
            $q->where('topic_id', '=', 0)
                ->orWhere('topic_id', $request->get('topic_id'));
        })
        ->where('element_id','content_library')
        ->get()->toArray();

        $lms_mapping_type = json_decode(json_encode($lms_mapping_type), true);

       $lms_mapping_Values = [];
       foreach ($lms_mapping_type as $key => $value) {
            $lms_mapping_Values[$value['name']]=  DB::table('lms_mapping_type')
            ->where('status', '=', 1)
            ->where('parent_id', '=', $value['id'])
            ->get()->toArray();
       }

        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data['chapter_data'];
        $res['content_data'] = $data['content_data'];
        $res['grade'] = $data['basic_ids']['grade_id'];
        $res['standard'] = $data['basic_ids']['standard_id'];
        $res['subject'] = $data['basic_ids']['subject_id'];
        $res['subject_name'] = $data['basic_ids']['subject_name'];
        $res['show_content'] = $data['basic_ids']['add_content'];
        $res['lms_mapping_type'] = $lms_mapping_type;  // added on 28-02-2025
        $res['lms_mapping_Values'] = $lms_mapping_Values;  // added on 28-02-2025
        $res['mapped_type'] = $request->mapping_type;  // added on 28-02-2025
        $res['mapped_value'] = $request->mapped_value;  // added on 28-02-2025
        // echo "<pre>";print_r($data['chapter_data']);exit;
        return is_mobile($type, 'lms/show_chapter', $res, "view");
    }

    public function getData($request)
    {
        if($request->has('preload_lms')){
            $sub_institute_id = 1;
            $year = DB::table('academic_year')->where('sub_institute_id',$sub_institute_id)->get()->toArray();
            $syear =$year[0]->syear;
            $user_profile_name = 1;
        }else{
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = $request->session()->get('syear');
            $user_profile_name = $request->session()->get('user_profile_name');
        }

        $getIsLms = DB::table('school_setup')
            ->where('Id', $sub_institute_id)
            ->value('is_Lms');

        $extra_where = array();
        if ($user_profile_name == "Student") {
            $extra_where['chapter_master.show_hide'] = "1";
            $content_where['content_master.show_hide'] = '1';
        }

        $subject_id = $request->input('subject_id');
        $standard_id = $request->input('standard_id');
        $data['chapter_data'] = array();

        // DB::enableQueryLog();
        $data['chapter_data'] = chapterModel::select('chapter_master.*',
            DB::raw('COUNT(content_master.id) as total_content,sum(if(content_category = "Triz", 1, 0)) AS total_triz_content,
        sum(if(content_category = "OER", 1, 0)) AS total_OER_content'))
            ->leftjoin('content_master', 'content_master.chapter_id', '=', 'chapter_master.id')
            ->where(function ($query) use ($getIsLms, $sub_institute_id) {
                if ($getIsLms == 'Y') {
                    $query->where('chapter_master.sub_institute_id', '1')
                        ->orWhere('chapter_master.sub_institute_id', $sub_institute_id);
                } else {
                    $query->Where('chapter_master.sub_institute_id', $sub_institute_id);
                }
            })
            ->where('chapter_master.subject_id', $subject_id)
            ->where('chapter_master.standard_id', $standard_id)
            ->where($extra_where)
            ->groupBy('chapter_master.id')
            ->orderBy('chapter_master.sort_order')
            ->get();

        $data['basic_ids'] = sub_std_mapModel::select('standard.grade_id', 'sub_std_map.subject_id',
            'sub_std_map.standard_id',
            'sub_std_map.display_name as subject_name', 'sub_std_map.add_content')
            ->join('standard', 'standard.id', '=', 'sub_std_map.standard_id')
            ->where(function ($query) use ($getIsLms, $sub_institute_id) {
                if ($getIsLms == 'Y') {
                    $query->where('sub_std_map.sub_institute_id', '1')
                        ->orWhere('sub_std_map.sub_institute_id', $sub_institute_id);
                }
            })
            ->where('sub_std_map.subject_id', $subject_id)
            ->where('sub_std_map.standard_id', $standard_id)
            ->get()->toArray();

        $content_data = contentModel::select('content_master.*')
            ->where(function ($query) use ($getIsLms, $sub_institute_id) {
                if ($getIsLms == 'Y') {
                    $query->where('content_master.sub_institute_id', '1')
                        ->orWhere('content_master.sub_institute_id', $sub_institute_id);
                }
            })
            ->where('content_master.subject_id', $subject_id)
            ->where('content_master.standard_id', $standard_id)
            ->where(function ($query) {
                $query->whereNull('content_master.topic_id')
                    ->orWhere('content_master.topic_id', '0');
            })
            ->get()->toArray(); // commented on 28-02-2025
        // added on 28-02-2025
        // $content_data = contentModel::select('content_master.*',DB::Raw('group_concat(cmt.mapping_type_id) as mapping_types'),DB::Raw('group_concat(cmt.mapping_value_id) as mapping_values'))
        // ->leftjoin('content_mapping_type as cmt','cmt.content_id','=','content_master.id')
        // ->where(function ($query) use ($getIsLms, $sub_institute_id) {
        //     if ($getIsLms == 'Y') {
        //         $query->where('content_master.sub_institute_id', '1')
        //             ->orWhere('content_master.sub_institute_id', $sub_institute_id);
        //     }
        // })
        // ->where('content_master.subject_id', $subject_id)
        // ->where('content_master.standard_id', $standard_id)
        // ->where(function ($query) {
        //     $query->whereNull('content_master.topic_id')
        //         ->orWhere('content_master.topic_id', '0');
        // })
        // ->when($request->has('mapped_value') && $request->mapped_value!='',function($q) use($request){
        //     $q->whereIn('cmt.mapping_value_id',explode(',',$request->mapped_value));
        // })
        // ->groupBy('content_master.id')
        // ->get()->toArray();

         $content_data_array =[];
        $mappedVals = explode(',',$request->mapped_value);

        if (!empty($content_data)) {
            foreach ($content_data as $content) {
                if(isset($mappedVals[0]) && $mappedVals[0]!=''){
                    
                    $mappedValArr = [];
                    foreach($mappedVals as $mk=>$mv){
                        $mappedValArr[] = DB::table('content_mapping_type')->where('content_id',$content['id'] ?? 0)->whereIn('mapping_value_id',$mappedVals)->value('content_id');   
                    }
                    // echo "<pre>";print_r($mappedValArr);exit;
                    $content_data_array[$content['chapter_id']][$content['content_category']][] =in_array($content['id'],$mappedValArr) ? $content : [];
                }else{
                    $content_data_array[$content['chapter_id']][$content['content_category']][] = $content;
                }
                // $content_data_array[$content['chapter_id']][$content['content_category']][] = $content;
            }
            // After processing all content, append flashcards at the end
            foreach ($content_data_array as $chapter_id => &$chapter_content) {
                
                if (!isset($chapter_content['Flash Cards'])) {
                    $chapter_content['Flash Cards'] =$flash =DB::table('lms_flashcard')
                    ->where(['chapter_id' => $chapter_id, 'sub_institute_id' => $sub_institute_id, 'status' => 1])
                    ->get()
                    ->toArray();
                }
                if (!isset($chapter_content['Mindmap'])) {
                    $chapter_content['Mindmap'] = array();
                }
                if (!isset($chapter_content['Virtual Lab'])) {
                    $chapter_content['Virtual Lab'] = array();
                }
            }
        }
        // echo "<pre>";print_r($content_data_array);exit;
        $data['content_data'] = $content_data_array;

        $data['basic_ids'] = $data['basic_ids'][0];

        return $data;
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $res = [];

        return is_mobile($type, 'lms/add_chapter', $res, "view");
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $chapter_name = $request->get('chapter_name');
        $chapter_desc = $request->get('chapter_desc');
        $availability = $request->get('availability');
        $sort_order = $request->get('sort_order');
        $show_hide = $request->get('show_hide');

        foreach ($chapter_name as $key => $val) {
            $show_hide_val = $show_hide[$key] ?? '';
            $availability_val = $availability[$key] ?? '';
            $chapter_desc_val = $chapter_desc[$key] ?? '';
            $sort_order_val = $sort_order[$key] ?? '';

            $ch = [
                'grade_id'         => $request->get('grade'),
                'standard_id'      => $request->get('standard'),
                'subject_id'       => $request->get('subject'),
                'chapter_name'     => $val,
                'availability'     => $availability_val,
                'show_hide'        => $show_hide_val,
                'chapter_desc'     => $chapter_desc_val,
                'created_by'       => $user_id,
                'sub_institute_id' => $sub_institute_id,
                'sort_order'       => $sort_order_val,
                'syear'            => $syear,
            ];

            chapterModel::insert($ch);
        }

        $res = [
            "status_code" => 1,
            "message"     => "Chapters Added Successfully",
            "subject_id"  => $request->get('subject'),
        ];

        $type = $request->input('type');

        return redirect()->route('chapter_master.index',
            [
                'standard_id' => $request->get('standard'), 'subject_id' => $request->get('subject'),'perm'=>$sub_institute_id
            ]);//->with(['data' => $res]);
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $std_id = $request->input('std_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $stdData = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id])
            ->orderBy('display_name')->get()->toArray();

        $data['subjects'] = $stdData;

        $data['chapter_data'] = chapterModel::find($id)->toArray();

        return is_mobile($type, "lms/edit_chapter", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
// print_r($request->get('show_hide')[0]);EXIT;
        $data = [
            'grade_id'         => $request->get('grade'),
            'standard_id'      => $request->get('standard'),
            'subject_id'       => $request->get('subject'),
            'chapter_name'     => $request->get('chapter_name')[0] ?? '',
            'availability'     => $request->get('availability')[0] ?? '',
            'show_hide'        => $request->get('show_hide')[0] ?? '',
            'chapter_desc'     => $request->get('chapter_desc')[0] ?? '',
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'sort_order'       => $request->get('sort_order')[0] ?? '',
            'syear'            => $syear,
        ];

        chapterModel::where(["id" => $id])->update($data);
        $res = [
            "status_code" => 1,
            "message"     => "Chapter Updated Successfully",
        ];
        $type = $request->input('type');

        return redirect()->route('chapter_master.index',
            [
                'subject_id' => $request->get('subject'), 'standard_id' => $request->get('standard'),'perm'=>$sub_institute_id
            ]);//->with(['data' => $res]);
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $chapterdata = chapterModel::where(["id" => $id])->get()->toArray();
        $subject_id = $chapterdata[0]['subject_id'];
        $standard_id = $chapterdata[0]['standard_id'];
        chapterModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Chapter Deleted Successfully";

        return redirect()->route('chapter_master.index', ['subject_id' => $subject_id, 'standard_id' => $standard_id,'perm'=>$sub_institute_id]);
    }

    public function StandardwiseSubject(Request $request)
    {
        $std_id = $request->input("std_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id])
            ->orderBy('display_name')->get()->toArray();
    }

    public function chapter_search(Request $request)
    {
        $type = $request->input('type');
        $submit = $request->input('submit');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $grade = $request->input('grade');
        $standard = $request->input('standard');
        $subject = $request->input('subject');

        $search_arr = [
            'chapter_master.grade_id'         => $grade,
            'chapter_master.standard_id'      => $standard,
            'chapter_master.subject_id'       => $subject,
            'chapter_master.sub_institute_id' => $sub_institute_id,
        ];

        $data = [];
        $data['data'] = chapterModel::select('chapter_master.*', 'standard.name as standard_name'
            , 'academic_section.title as grade_name', 'subject_name')
            ->join('standard', 'standard.id', '=', 'chapter_master.standard_id')
            ->join('academic_section', 'academic_section.id', '=', 'chapter_master.grade_id')
            ->join('subject', 'subject.id', '=', 'chapter_master.subject_id')
            ->where($search_arr)
            ->orderBy('chapter_master.standard_id', 'asc')
            ->get();

        if (count($data['data']) > 0) {
            $topic_data = topicModel::select('*')
                ->where(['sub_institute_id' => $sub_institute_id])
                ->where('main_topic_id', '=', '0')
                ->get()->toArray();

            foreach ($topic_data as $key => $val) {
                $data['topic_data'][$val['chapter_id']][] = $val;
            }

            $subtopic_data = topicModel::select('*')
                ->where(['sub_institute_id' => $sub_institute_id])
                ->where('main_topic_id', '!=', '0')
                ->get()->toArray();

            foreach ($subtopic_data as $subkey => $subval) {
                $data['subtopic_data'][$subval['main_topic_id']][] = $subval;
            }

        }

        $subject_data = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $standard])
            ->orderBy('display_name')->get()->toArray();

        $data['subject_arr'] = $subject_data;
        $data['status_code'] = 1;
        $data['message'] = "SUCCESS";
        $data['grade'] = $grade;
        $data['standard'] = $standard;
        $data['subject'] = $subject;

        return is_mobile($type, "lms/show_chapter", $data, "view");
    }

    function ajax_chapterDependencies(Request $request)
    {
        $chapter_id = $request->input("chapter_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $data = chapterModel::where([
            'chapter_master.sub_institute_id' => $sub_institute_id,
            'chapter_master.id'               => $chapter_id,
        ])
            ->leftjoin('topic_master as tm', 'tm.chapter_id', '=', 'chapter_master.id')
            ->leftjoin('content_master as cm', 'cm.chapter_id', '=', 'chapter_master.id')
            ->havingRaw('tm.id is not null or cm.id is not null')
            ->get()->toArray();

        return count($data);
    }

    public function show(Request $request, $id)
    {

        $type = $request->input('type');
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");
        $subject_id = $id;

        $action = $request->get('action');

        //START Graph
        $graph_data = "[";

        if ($action == "subjectwise")//show subjectwise graph
        {
            $standard_id = $request->get('standard_id');

            //START Get subject name
            $subject = DB::table('sub_std_map')->where('subject_id', $subject_id)
                ->where('standard_id', $standard_id)->get()->toArray();

            $subject_name = str_replace($this->searchArr, $this->replaceArr, $subject[0]->display_name);
            //END Get subject name

            $graph_data .= $this->get_chapter_data($request, $subject_id, $standard_id, $subject_name);

            $data['subject_name'] = $subject_name;
        } elseif ($action == "chapterwise")//show chapterwise graph
        {
            $chapter_id = $request->get('chapter_id');
            $chapter_name = str_replace($this->searchArr, $this->replaceArr, $request->get('chapter_name'));
            $graph_data .= $this->get_topic_data($request, $chapter_name, $chapter_id);

            $data['subject_name'] = $chapter_name;
        } elseif ($action == "topicwise")//show topicwise graph
        {
            $topic_id = $request->get('topic_id');
            $topic_name = str_replace($this->searchArr, $this->replaceArr, $request->get('topic_name'));
            $graph_data .= $this->get_content_data($request, $topic_name, $topic_id);

            $data['subject_name'] = $topic_name;
        }

        $graph_data .= "]";
        //END Graph

        $data['graph_data'] = $graph_data;

        //START for blank graph
        if ($data['graph_data'] == "[]") {
            $data['graph_data'] = "[";
            $data['graph_data'] .= "['".$data['subject_name']."','']";
            $data['graph_data'] .= "]";
        }

        //END for blank graph

        return is_mobile($type, "lms/view_knowledgegraph", $data, "view");
    }

    public function get_chapter_data(Request $request, $subject_id, $standard_id, $subject_name)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");

        //START Get all Chapter data
        $chapters = DB::table('chapter_master as c')
            ->where('c.sub_institute_id', $sub_institute_id)
            ->where('c.standard_id', $standard_id)
            ->where('c.subject_id', $subject_id)
            ->where('c.syear', $syear)
            ->get()->toArray();

        $chapter_arr = json_decode(json_encode($chapters), true);

        $chapter_data = "";
        foreach ($chapter_arr as $ckey => $cval) {
            $chapter_name = str_replace($this->searchArr, $this->replaceArr, $cval['chapter_name']);

            $chapter_data .= "[";
            $chapter_data .= "'".$subject_name."','".$chapter_name."'";
            $chapter_data .= ",'red',4,'dot'],";

            //START Get topic data
            $chapter_data .= $this->get_topic_data($request, $chapter_name, $cval['id']);
            //END Get topic data
        }

        //END Get all Chapter data

        return $chapter_data;
    }

    public function get_topic_data(Request $request, $chapter_name, $chapter_id)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");

        $topics = DB::table('topic_master as t')
            ->where('t.sub_institute_id', $sub_institute_id)
            ->where('t.chapter_id', $chapter_id)
            ->where('t.syear', $syear)
            ->get()->toArray();

        $topic_arr = json_decode(json_encode($topics), true);

        $topic_data = "";
        foreach ($topic_arr as $tkey => $tval) {
            $topic_name = str_replace($this->searchArr, $this->replaceArr, $tval['name']);

            $topic_data .= "[";
            $topic_data .= "'".$chapter_name."','".$topic_name."'";
            $topic_data .= "],";
            //START Get content data
            $topic_data .= $this->get_content_data($request, $topic_name, $tval['id']);
            //END Get content data

            //START Get Question & Answer data
            $topic_data .= $this->get_question_data($request, $topic_name, $tval['id']);
            //END Get Question & Answer data

        }

        return $topic_data;
    }

    public function get_content_data(Request $request, $topic_name, $topic_id)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");

        $contents = DB::table('content_master as c')
            ->where('c.sub_institute_id', $sub_institute_id)
            ->where('c.topic_id', $topic_id)
            ->where('c.syear', $syear)
            ->get()->toArray();

        $content_arr = json_decode(json_encode($contents), true);

        $content_data = "";

        //START ADD Label for Content
        $topic_content_label = $topic_name."(Content)";

        $content_data .= "[";
        $content_data .= "'".$topic_name."','".$topic_content_label."'";
        $content_data .= "],";
        //END ADD Label for Content

        foreach ($content_arr as $tkey => $tval) {
            $content_title = str_replace($this->searchArr, $this->replaceArr, $tval['title']);
            $content_data .= "[";
            $content_data .= "'".$topic_content_label."','".$content_title."'";
            $content_data .= ",'green',4,'LongDashDotDot'],";
            //START Get content mapping data
            $content_data .= $this->get_content_mapping_data($request, $content_title, $tval['id']);
            //END Get content mapping data
        }

        return $content_data;
    }

    public function get_content_mapping_data(Request $request, $content_title, $content_id)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $contentMappings = DB::table('content_mapping_type as c')
            ->join('lms_mapping_type as t1', function ($join) {
                $join->whereRaw('t1.id = c.mapping_type_id');
            })->join('lms_mapping_type as t2', function ($join) {
                $join->whereRaw('t2.id = c.mapping_value_id');
            })
            ->selectRaw("c.*,CONCAT_WS(' ',t1.name,' => ',t2.name) mapping_value")
            ->where('c.content_id', $content_id)
            ->get()->toArray();

        $contentMapping_arr = json_decode(json_encode($contentMappings), true);

        $contentMapping_data = "";
        foreach ($contentMapping_arr as $tkey => $tval) {
            $mapping_value = str_replace($this->searchArr, $this->replaceArr, $tval['mapping_value']);
            $contentMapping_data .= "[";
            $contentMapping_data .= "'".$content_title."','".$mapping_value."'";
            $contentMapping_data .= ",'blue',4,'dash'],";
        }

        return $contentMapping_data;
    }

    public function get_question_data(Request $request, $topic_name, $topic_id)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");

        $questions = DB::table('lms_question_master as q')
            ->selectRaw("*,SUBSTRING(q.question_title,1,20) as ques_title")
            ->where('q.sub_institute_id', $sub_institute_id)
            ->where('q.topic_id', $topic_id)
            ->where('status',1)
            ->get()->toArray();

        $question_arr = json_decode(json_encode($questions), true);

        $question_data = "";

        //START ADD Label for Question
        $topic_question_label = $topic_name."(Q&A)";

        $question_data .= "[";
        $question_data .= "'".$topic_name."','".$topic_question_label."'";
        $question_data .= "],";
        //END ADD Label for Question

        foreach ($question_arr as $tkey => $tval) {
            $ques_title = str_replace($this->searchArr, $this->replaceArr, $tval['ques_title']);

            $question_data .= "[";
            $question_data .= "'".$topic_question_label."','".$ques_title."'";
            $question_data .= "],";
            //START Get Question mapping data
            $question_data .= $this->get_question_mapping_data($request, $ques_title, $tval['id']);
            //END Get Question mapping data
        }

        return $question_data;
    }

    public function get_question_mapping_data(Request $request, $ques_title, $question_id)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $questionMappings = DB::table('lms_question_master as q')
            ->join('lms_mapping_type as t1', function ($join) {
                $join->whereRaw('t1.id = c.mapping_type_id');
            })->join('lms_mapping_type as t2', function ($join) {
                $join->whereRaw('t2.id = c.mapping_value_id');
            })
            ->selectRaw("c.*,SUBSTRING(CONCAT_WS(' ',t1.name,' => ',t2.name),1,35) as mapping_value")
            ->where('q.questionmaster_id', $question_id)
            ->where('q.status',1)
            ->get()->toArray();

        $questionMapping_arr = json_decode(json_encode($questionMappings), true);
        $questionMapping_data = "";
        foreach ($questionMapping_arr as $tkey => $tval) {
            $mapping_value = str_replace($this->searchArr, $this->replaceArr, $tval['mapping_value']);
            $questionMapping_data .= "[";
            $questionMapping_data .= "'".$ques_title."','".$mapping_value."'";
            $questionMapping_data .= ",'blue',4,'ShortDash'],";
        }

        return $questionMapping_data;
    }

}
