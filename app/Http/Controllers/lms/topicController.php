<?php

namespace App\Http\Controllers\lms;

use App\Http\Controllers\Controller;
use App\Models\lms\contentModel;
use App\Models\lms\flashcardModel;
use App\Models\lms\topicModel;
use App\Models\school_setup\sub_std_mapModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class topicController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['topic_data'] = $data['topic_data'];
        $res['content_data'] = $data['content_data'];
        $res['chapter_id'] = $request->input('id');
        $res['breadcrum_data'] = $data['breadcrum_data'];

        return is_mobile($type, 'lms/show_topic', $res, "view");
    }

    public function getData($request)
    {
        if($request->has('preload_lms')){
            $sub_institute_id = 1;
            $syear = $request->session()->get('syear');
            $ch_id = $request->input('id');
        }else{
            $sub_institute_id = $request->session()->get('sub_institute_id');
            $syear = $request->session()->get('syear');
            $ch_id = $request->input('id');
        }

        $getIsLms = DB::table('school_setup')
            ->where('Id', $sub_institute_id)
            ->value('is_Lms');

        $data['content_data'] = array();

        $user_profile_name = $request->session()->get('user_profile_name');

        $extra_where = array();
        if ($user_profile_name == "Student") {
            $extra_where['topic_master.topic_show_hide'] = "1";
            $content_where['content_master.show_hide'] = '1';
        }

        $data['topic_data'] = topicModel::select('topic_master.*', 'chapter_master.chapter_name',
            'chapter_master.grade_id', 'chapter_master.standard_id', 'chapter_master.subject_id')
            ->join('chapter_master', 'chapter_master.id', '=', 'topic_master.chapter_id')
            ->where(function ($query) use ($getIsLms, $sub_institute_id) {
                if ($getIsLms == 'Y') {
                    $query->where('topic_master.sub_institute_id', '1')
                        ->orWhere('topic_master.sub_institute_id', $sub_institute_id);
                }
            })
            ->where('topic_master.chapter_id', $ch_id)
            ->where($extra_where)
            ->orderBy('topic_sort_order', 'asc')
            ->get();//,'topic_master.syear'=>$syear

        $content_where = array(
            /* 'content_master.sub_institute_id'=>$sub_institute_id, */
            'content_master.chapter_id' => $ch_id,
        );//'content_master.syear'=>$syear,
        if ($request->has('content_category')) {
            $content_where['content_category'] = $request->input('content_category');
        }
        if ($user_profile_name == "Student") {
            $content_where['content_master.show_hide'] = '1';
        }

        $content_data = contentModel::select('content_master.*')
            ->where(function ($query) use ($getIsLms, $sub_institute_id) {
                if ($getIsLms == 'Y') {
                    $query->where('content_master.sub_institute_id', '1')
                        ->orWhere('content_master.sub_institute_id', $sub_institute_id);
                }
            })
            ->where($content_where)
            ->get()->toArray();

        foreach ($content_data as $key => $val) {
            $data['content_data'][$val['topic_id']][$val['id']] = $val;

            $flashcard_data = flashcardModel::select('*')
                ->where(function ($query) use ($getIsLms, $sub_institute_id) {
                    if ($getIsLms == 'Y') {
                        $query->where('sub_institute_id', '1')
                            ->orWhere('sub_institute_id', $sub_institute_id);
                    }
                })
                ->where(['chapter_id' => $ch_id, 'topic_id' => $val['topic_id'], 'content_id' => $val['id']])
                ->take('4')->get()->toArray();

            foreach ($flashcard_data as $fkey => $fval) {
                $data['content_data'][$val['topic_id']][$val['id']]['FLASHCARD'][] = $fval;
            }
        }

        //dd($data);


        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id, $ch_id);

        //dd($data);
        return $data;
    }

    public function getBreadcrum($sub_institute_id, $chapter_id)
    {
        $getIsLms = DB::table('school_setup')
            ->where('Id', $sub_institute_id)
            ->value('is_Lms');

        $where_institute_id = "c.sub_institute_id = '$sub_institute_id'";
        if ($getIsLms == 'Y') {
            $where_institute_id = "(c.sub_institute_id = '1' OR c.sub_institute_id = '$sub_institute_id')";
        }

        $breadcrum_data = DB::table('chapter_master as c')
            ->join('sub_std_map as s', function ($join) {
                $join->whereRaw('s.subject_id = c.subject_id AND s.standard_id = c.standard_id');
            })->join('standard as st', function ($join) {
                $join->whereRaw('st.id = c.standard_id');
            })->selectRaw('c.subject_id,s.display_name AS subject_name,c.standard_id,st.name AS standard_name,
                c.id AS chapter_id,c.chapter_name')
            ->whereRaw($where_institute_id)
            ->where('c.id', $chapter_id)->get()->toArray();

        return $breadcrum_data[0]??[];

    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $res['chapter_id'] = $request->input('chapter_id');

        return is_mobile($type, 'lms/add_topic', $res, "view");
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');

        $topic_name = $request->get('topic_name');
        $topic_desc = $request->get('topic_desc');
        $topic_sort_order = $request->get('topic_sort_order');
        $topic_show_hide = $request->get('topic_show_hide');

        foreach ($topic_name as $key => $val) {
            $show_hide_val = $topic_show_hide[$key] ?? '';
            $sort_order_val = $topic_sort_order[$key] ?? '';
            $topic_desc_val = $topic_desc[$key] ?? '';

            $topic = [
                'chapter_id'       => $request->get('hidchapter_id'),
                'main_topic_id'    => 0,
                'name'             => $val,
                'description'      => $topic_desc_val,
                'topic_sort_order' => $sort_order_val,
                'topic_show_hide'  => $show_hide_val,
                'created_by'       => $user_id,
                'sub_institute_id' => $sub_institute_id,
                'syear'            => $syear,
            ];

            topicModel::insert($topic);
        }

        $res = [
            "status_code" => 1,
            "message"     => "Topic Added Successfully",
        ];
        $type = $request->input('type');

        return redirect()->route('topic_master.index', ['id' => $request->get('hidchapter_id'),'standard_id' => $request->get('standard_id')]);
      

    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');

        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['topic_data'] = topicModel::find($id)->toArray();

        return is_mobile($type, "lms/edit_topic", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');

        $show_hide_val = $request->get("topic_show_hide")[0] ?? '';

        $data = [
            'name'             => $request->get('topic_name')[0] ?? '',
            'description'      => $request->get('topic_desc')[0] ?? '',
            'topic_sort_order' => $request->get('topic_sort_order')[0] ?? '',
            'topic_show_hide'  => $show_hide_val,
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
        ];

        topicModel::where(["id" => $id])->update($data);
        $res = [
            "status_code" => 1,
            "message"     => "Topic Updated Successfully",
        ];
        $type = $request->input('type');

        return redirect()->route('topic_master.index', ['id' => $request->get('hidchapter_id'),'standard_id' => $request->get('standard_id')]);
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        
        $topicdata = topicModel::where(["id" => $id])->get()->toArray();
        $chapter_id = $topicdata[0]['chapter_id'];

        topicModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Topic Deleted Successfully";

        return redirect()->route('topic_master.index', ['id' => $chapter_id,'standard_id' => $request->get('standard_id')]);
    }

    public function StandardwiseSubject(Request $request)
    {
        $std_id = $request->input("std_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id])
            ->orderBy('display_name')->get()->toArray();
    }

    function ajax_topicDependencies(Request $request)
    {
        $topic_id = $request->input("topic_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        $data = topicModel::where([
            'topic_master.sub_institute_id' => $sub_institute_id,
            'topic_master.id'               => $topic_id,
        ])
            ->leftjoin('content_master as cm', 'cm.topic_id', '=', 'topic_master.id')
            ->havingRaw('cm.id is not null')
            ->get()->toArray();

        return count($data);
    }

    public function show(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $content_id = $id;

        $content_data = contentModel::select('content_master.*')
            ->where(['content_master.sub_institute_id' => $sub_institute_id, 'content_master.id' => $content_id])
            ->get()->toArray();

        $data['status_code'] = 1;
        $data['message'] = "SUCCESS";
        $data['content_data'] = $content_data[0];


       
        if($content_data[0]['file_type'] == 'jpg' || $content_data[0]['file_type'] == 'gif' || $content_data[0]['file_type'] == 'png')
        {
            return is_mobile($type, "lms/view_content_image", $data, "view");
        }
        else if($content_data[0]['file_type'] == 'mp3' || $content_data[0]['file_type'] == 'mp4' || $content_data[0]['file_type'] == 'mkv')
        {
            return is_mobile($type, "lms/view_content_video", $data, "view");
        }
        else
        {//dd($data);
            return is_mobile($type, "lms/view_content", $data, "view");
        }
        
    }

    public function addtopic(Request $request)
    {
        // $sub_institute_id = $request->session()->get('sub_institute_id');
        // $data = chapterModel::select('chapter_master.*','standard.name as standard_name'
        // ,'academic_section.title as grade_name','subject_name')
        // ->join('standard', 'standard.id', '=', 'chapter_master.standard_id')
        // ->join('academic_section', 'academic_section.id', '=', 'chapter_master.grade_id')        
        // ->join('subject', 'subject.id', '=', 'chapter_master.subject_id')        
        // ->where(['chapter_master.sub_institute_id'=>$sub_institute_id])
        // ->orderBy('chapter_master.standard_id', 'asc')
        // ->get();       

        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";

        //$res['data'] = $data;        
        return is_mobile($type, 'lms/show_topic', $res, "view");
    }
	
}
