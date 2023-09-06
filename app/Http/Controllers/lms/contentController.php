<?php

namespace App\Http\Controllers\lms;
use App\Http\Controllers\Controller;
use App\Models\lms\chapterModel;
use App\Models\lms\contentmappingtypeModel;
use App\Models\lms\contentModel;
use App\Models\lms\lmsContentCategoryModel;
use App\Models\lms\lmsmappingtypeModel;
use App\Models\lms\topicModel;
use App\Models\school_setup\sub_std_mapModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class contentController extends Controller
{
    public function index(Request $request){         
        $data = $this->getData($request); 		
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data['content_data'];        
        $res['content_category'] = $data['content_category'];        
        return is_mobile($type,'lms/show_content',$res,"view");  
    }

    public function getData($request){
        if($request->has('preload_lms')){
            $sub_institute_id = 1;
        }else{
        $sub_institute_id = $request->session()->get('sub_institute_id');
        }
        $data['content_data'] = contentModel::select('content_master.*','standard.name as standard_name','academic_section.title as grade_name',
        'subject_name','chapter_name','tm.name as topic_name','stm.name as sub_topic_name')
        ->join('standard', 'standard.id', '=', 'content_master.standard_id')
        ->join('academic_section', 'academic_section.id', '=', 'content_master.grade_id')
        ->join('subject', 'subject.id', '=', 'content_master.subject_id')       
        ->join('chapter_master as cm','cm.id','=','content_master.chapter_id')
        ->leftjoin('topic_master as tm','tm.id','=','content_master.topic_id')
        ->leftjoin('topic_master as stm','stm.id','=','content_master.sub_topic_id')
        ->where('content_master.sub_institute_id',$sub_institute_id)                      
        ->get();

        return $data;
    }

    public function getBreadcrum($sub_institute_id,$chapter_id,$topic_id = '')
    {
        $where = '';
        $topic = '';
        // return $chapter_id;exit;
        // $breadcrum_data = array();
        if ($topic_id != '') {
            $topic = 't.id as topic_id,';
        }

        $breadcrum_data = DB::table('chapter_master as c')
            ->join('sub_std_map as s', function ($join) {
                $join->whereRaw('s.subject_id = c.subject_id AND s.standard_id = c.standard_id');
            })->join('standard as st', function ($join) {
                $join->whereRaw('st.id = c.standard_id');
            })->Leftjoin('topic_master as t', function ($join) {
                $join->whereRaw('t.chapter_id = c.id');
            })->selectRaw('c.subject_id,s.display_name AS subject_name,c.standard_id,st.name AS standard_name,
                c.id AS chapter_id, c.chapter_name, '.$topic.' t.name as topic_name')
            ->where('c.sub_institute_id', $sub_institute_id)
            ->where('c.id', $chapter_id);

        if ($topic_id != '') {
            $breadcrum_data = $breadcrum_data->where('t.id', $topic_id);
            $topic = 't.id as topic_id,';
        }

        $breadcrum_data = $breadcrum_data->get()->toArray();
        // dd($breadcrum_data);
        if(isset($breadcrum_data[0]) && $breadcrum_data != " "){
        return $breadcrum_data[0];
        }
        else{
            return back();
        }
    }

    public function create(Request $request)
    {

        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = array();

        $lms_mapping_type = DB::table('lms_mapping_type')
            ->where('status', '=', 1)
            ->where('parent_id', '=', 0)
            ->where(function ($q) use ($request) {
                $q->where('globally', '=', 1)
                    ->orWhere('chapter_id', $request->get('chapter_id'));
            })->where(function ($q) use ($request) {
                $q->where('topic_id', '=', 0)
                    ->orWhere('topic_id', $request->get('topic_id'));
            })->get()->toArray();

        $lms_mapping_type = json_decode(json_encode($lms_mapping_type), true);
        $data['lms_mapping_type'] = $lms_mapping_type;

        //START Get Content Category
        $data['content_category'] = lmsContentCategoryModel::where('status', '2')->get()->toArray(); //Rajesh = From topicwise - Add Content to display content category
        //END Get Content Category

        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id, $request->get('chapter_id'),
            $request->get('topic_id'));

        //START Get Standard
        $chapter_data = chapterModel::select('*')        
        ->where(['chapter_master.sub_institute_id'=>$sub_institute_id,'chapter_master.id'=>$request->get('chapter_id')])         
        ->get()->toArray(); 
        
        $data['standard_id'] = $chapter_data[0]['standard_id'];
        //END Get Standard

        //$data['YouTubeSuggestionList'] = $this->getYouTubeSuggestion($data['breadcrum_data']->standard_name,$data['breadcrum_data']->subject_name,$data['breadcrum_data']->chapter_name);        

        return is_mobile($type,'lms/add_content',$data,"view");
    }

    /**
     * Create Chapter wise
     */
    public function createChapter(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $data = array();

        $lms_mapping_type = DB::table('lms_mapping_type')
            ->where('status', '=', 1)
            ->where('parent_id', '=', 0)
            ->where(function ($q) use ($request) {
                $q->where('globally', '=', 1)
                    ->orWhere('chapter_id', $request->get('chapter_id'));
            })->get()->toArray();

        $lms_mapping_type = json_decode(json_encode($lms_mapping_type), true);
        $data['lms_mapping_type'] = $lms_mapping_type;

        //START Get Content Category
        $data['content_category'] = lmsContentCategoryModel::where('status', '2')->get()->toArray(); //Rajesh = From chapterwise - Add Content to display content category
        //END Get Content Category

        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id, $request->get('chapter_id'));

        //START Get Standard
        $chapter_data = chapterModel::select('*')        
        ->where(['chapter_master.sub_institute_id'=>$sub_institute_id,'chapter_master.id'=>$request->get('chapter_id')])         
        ->get()->toArray(); 
        
        $data['standard_id'] = $chapter_data[0]['standard_id'];
        //END Get Standard

        //$data['YouTubeSuggestionList'] = $this->getYouTubeSuggestion($data['breadcrum_data']->standard_name,$data['breadcrum_data']->subject_name,$data['breadcrum_data']->chapter_name);        
        
        return is_mobile($type,'lms/add_chapter_content',$data,"view");
    }

    public function ajax_getYouTubeSuggestion(Request $request)
    {
        $api_key = env('GOOGLE_API_KEY');  
        $formatted_keyword = $request->input('keyword');
        $type = $request->input('type');

        $link = "https://www.googleapis.com/youtube/v3/search?safeSearch=moderate&order=relevance&part=snippet&q=".urlencode($formatted_keyword). "&maxResults=10&key=". $api_key;

        $video = file_get_contents($link);

        $video = json_decode($video, true); 

        $video_arr = array();

        // if($video['error']['code'] != 403)
        // {
            foreach($video['items'] as $key => $val)
            {       
                if(isset($val['id']['videoId']))
                {    
                    $title = $val['snippet']['title'];
                    $description = $val['snippet']['description'];
                    $vid = $val['id']['videoId'];
                    $image = "https://img.youtube.com/vi/$vid/default.jpg";

                    $video_arr[$key]['title'] = $title;
                    $video_arr[$key]['description'] = $description;
                    $video_arr[$key]['videoID'] = $vid;
                    $video_arr[$key]['image_url'] = $image;
                    $video_arr[$key]['video_link'] = "https://www.youtube.com/watch?v=$vid";
                }
            }
        //} 
       
        return $video_arr;
    }      
    
    public function store(Request $request){               
        $sub_institute_id = $request->session()->get('sub_institute_id'); 		
        $syear = $request->session()->get('syear'); 		
        $user_id = $request->session()->get('user_id');       
        $show_hide = $request->get('show_hide');             
        $show_hide_val = isset($show_hide) ? $show_hide : '';

        //Basic means 1 and advance means 0 
        $basic_advanced = $request->get('toggle_basic_advanced');             
        $basic_advanced_val = isset($basic_advanced) ? '1' : '0';
        
        $file_folder = $ext = $size = $newfilename = "";
        if($request->hasFile('filename'))
        {           
            $img = $request->file('filename');
            $filename = $img->getClientOriginalName();
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;             
            $file_folder = '/lms_content_file';
            //$img->move(public_path().'/lms_content_file/',$newfilename);
            $img->storeAs('public/lms_content_file/',$newfilename);
        }

        if($request->get('contentType') == "link")
        {
            $newfilename = $request->get('link');
            $ext = "link";
        }       
           
        $chapter_data = chapterModel::select('*')        
        ->where(['chapter_master.sub_institute_id'=>$sub_institute_id,'chapter_master.id'=>$request->get('hid_chapter_id')])         
        ->get()->toArray(); 
        $chapter_data = $chapter_data[0]; 

        $pre_topic = $post_topic = $cross_curriculum_topic = "";
        if($request->get('prechapter') != "")
        {
            $pre_topic = $request->get('prechapter').'####'.$request->get('pretopic');
        } 
        if($request->get('postchapter') != "")
        {
            $post_topic = $request->get('postchapter').'####'.$request->get('posttopic');
        }
        if($request->get('cross-curriculumchapter') != "")
        {
            $cross_curriculum_topic = $request->get('cross-curriculumchapter').'####'.$request->get('cross-curriculumtopic');
        }

        $content = [
            'grade_id'                     => $chapter_data['grade_id'],
            'standard_id'                  => $chapter_data['standard_id'],
            'subject_id'                   => $chapter_data['subject_id'],
            'chapter_id'                   => $request->get('hid_chapter_id'),
            'topic_id'                     => $request->get('hid_topic_id'),
            'title'                        => $request->get('title'),
            'description'                  => $request->get('description'),
            'file_folder'                  => $file_folder,
            'filename'                     => $newfilename,
            'file_type'                    => $ext,
            'file_size'                    => $size,
            'show_hide'                    => $show_hide_val,
            'sort_order'                   => $request->get('sort_order'),
            'meta_tags'                    => $request->get('meta_tags'),
            'content_category'             => $request->get('content_category'),
            'created_by'                   => $user_id,
            'sub_institute_id'             => $sub_institute_id,
            'restrict_date'                => $request->get('restrict_date'),
            'pre_grade_topic'              => $pre_topic,
            'post_grade_topic'             => $post_topic,
            'cross_curriculum_grade_topic' => $cross_curriculum_topic,
            'basic_advance'                => $basic_advanced_val,
            'syear'                        => $syear,
        ];
        //'sub_topic_id' => $request->get('subtopic'),                            
        contentModel::insert($content);
        $last_id = DB::getPDO()->lastInsertId();

        $mapping_type = $request->get('mapping_type');
        $mapping_value = $request->get('mapping_value');
        foreach ($mapping_type as $key => $val) {
            if ($val != "" && $mapping_value[$key] != "") {
                $contentmappingtype = [
                    'content_id'       => $last_id,
                    'mapping_type_id'  => $val,
                    'mapping_value_id' => $mapping_value[$key],
                ];
                contentmappingtypeModel::insert($contentmappingtype);
            }
        }

        $res = [
            "status_code" => 1,
            "message"     => "Content Added Successfully",
        ];
        $type = $request->input('type');
        //return is_mobile($type, "content_master.index", $res, "redirect");
        // return redirect()->route('topic_master.index', ['id' => $request->get('hid_chapter_id')]);
        if ( $request->has('hid_topic_id') ) {
            return redirect()->route('topic_master.index', ['id' => $request->get('hid_chapter_id'),'standard_id' => $chapter_data['standard_id']]);
        } else {
            return redirect()->route('chapter_master.index', ['standard_id' => $chapter_data['standard_id'], 'subject_id' => $chapter_data['subject_id']]);
        }
    }

    public function storeChapter(Request $request){      
        // echo "<pre>"; print_r($request->all()); exit;         
        $sub_institute_id = $request->session()->get('sub_institute_id'); 		
        $syear = $request->session()->get('syear'); 		
        $user_id = $request->session()->get('user_id');
        $show_hide = $request->get('show_hide');
        $show_hide_val = $show_hide ?? '';

        //Basic means 1 and advance means 0 
        $basic_advanced = $request->get('toggle_basic_advanced');
        $basic_advanced_val = ! isset($basic_advanced) ? '0' : '1';
        
        $file_folder = $ext = $size = $newfilename = "";
        if($request->hasFile('filename'))
        {           
            $img = $request->file('filename');
            $filename = $img->getClientOriginalName();
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;             
            $file_folder = '/lms_content_file';
            //$img->move(public_path().'/lms_content_file/',$newfilename);
            $img->storeAs('public/lms_content_file/',$newfilename);
        }

        if($request->get('contentType') == "link")
        {
            $newfilename = $request->get('link');
            $ext = "link";
        }       
           
        $chapter_data = chapterModel::select('*')        
        ->where(['chapter_master.sub_institute_id'=>$sub_institute_id,'chapter_master.id'=>$request->get('hid_chapter_id')])         
        ->get()->toArray(); 
        $chapter_data = $chapter_data[0]; 

        $pre_topic = $post_topic = $cross_curriculum_topic = "";
        if($request->get('prechapter') != "")
        {
            $pre_topic = $request->get('prechapter').'####'.$request->get('pretopic');
        } 
        if($request->get('postchapter') != "")
        {
            $post_topic = $request->get('postchapter').'####'.$request->get('posttopic');
        }
        if($request->get('cross-curriculumchapter') != "")
        {
            $cross_curriculum_topic = $request->get('cross-curriculumchapter').'####'.$request->get('cross-curriculumtopic');
        }

        $content = [
            'grade_id'                     => $chapter_data['grade_id'],
            'standard_id'                  => $chapter_data['standard_id'],
            'subject_id'                   => $chapter_data['subject_id'],
            'chapter_id'                   => $request->get('hid_chapter_id'),
            'topic_id'                     => $request->get('hid_topic_id'),
            'title'                        => $request->get('title'),
            'description'                  => $request->get('description'),
            'file_folder'                  => $file_folder,
            'filename'                     => $newfilename,
            'file_type'                    => $ext,
            'file_size'                    => $size,
            'show_hide'                    => $show_hide_val,
            'sort_order'                   => $request->get('sort_order'),
            'meta_tags'                    => $request->get('meta_tags'),
            'content_category'             => $request->get('content_category'),
            'created_by'                   => $user_id,
            'sub_institute_id'             => $sub_institute_id,
            'restrict_date'                => date('Y-m-d', strtotime($request->get('restrict_date'))),
            'pre_grade_topic'              => $pre_topic,
            'post_grade_topic'             => $post_topic,
            'cross_curriculum_grade_topic' => $cross_curriculum_topic,
            'basic_advance'                => $basic_advanced_val,
            'syear'                        => $syear,
        ];

        // dd($content);
        //'sub_topic_id' => $request->get('subtopic'),  
        DB::enableQueryLog();
        contentModel::insert($content);
        $last_id = DB::getPDO()->lastInsertId();

        $mapping_type = $request->get('mapping_type');
        $mapping_value = $request->get('mapping_value');
        foreach ($mapping_type as $key => $val) {
            if ($val != "" && $mapping_value[$key] != "") {
                $contentmappingtype = [
                    'content_id'       => $last_id,
                    'mapping_type_id'  => $val,
                    'mapping_value_id' => $mapping_value[$key],
                ];
                contentmappingtypeModel::insert($contentmappingtype);
            }
        }

        $res = array(
            "status_code" => 1,
			"message" => "Content Added Successfully",
		);
        $type = $request->input('type');
        //return is_mobile($type, "content_master.index", $res, "redirect");
        return redirect()->route('chapter_master.index', ['standard_id' => $request->get('hid_standard_id'), 'subject_id' => $request->get('hid_subject_id')]);
    }
		
    public function edit(Request $request,$id){
        $type = $request->input('type');

        if($request->has('preload_lms')){
            $sub_institute_id = 1;
        }else{
        $sub_institute_id = $request->session()->get('sub_institute_id');
        } 		
						
        $data['content_data'] = contentModel::find($id)->toArray();

        $content_mapping_type = contentmappingtypeModel::where(['content_id' => $id])->get()->toArray();
        $i = 1;
        $final_content_mapping_type = array();
        foreach ($content_mapping_type as $key => $val) {
            $final_content_mapping_type[$i]['TYPE_ID'] = $val['mapping_type_id'];
            $final_content_mapping_type[$i]['VALUE_ID'] = $val['mapping_value_id'];
            $i++;
        }

        $lms_mapping_type = DB::table('lms_mapping_type')
            ->where('parent_id', '=', 0)
            ->where(function ($q) use ($data) {
                $q->where('globally', '=', 1)
                    ->orWhere('chapter_id', $data['content_data']['chapter_id']);
            })->where(function ($q) use ($data) {
                $q->where('globally', '=', 1)
                    ->orWhere('chapter_id', $data['content_data']['topic_id']);
            })->get()->toArray();

        $lms_mapping_type = json_decode(json_encode($lms_mapping_type), true);
        $data['lms_mapping_type'] = $lms_mapping_type;

        foreach ($lms_mapping_type as $lkey => $lval) {
            $arr = lmsmappingtypeModel::where(['parent_id' => $lval['id']])->get()->toArray();
            foreach ($arr as $k => $v) {
                $lms_mapping_value[$lval['id']][$v['id']] = $v['name'];
            }
        }

        $data['lms_mapping_value'] = $lms_mapping_value;
        $data['lms_mapping_type'] = $lms_mapping_type;
        $data['content_mapping_type'] = $final_content_mapping_type;

        //START Get Content Category        
        $data['content_category'] = lmsContentCategoryModel::where('status', '2')->get()->toArray(); //Rajesh = From topicwise - Add Content to display content category
        //END Get Content Category 

        //START Get Pre Topic
        $data['pretopicData'] = [];
        if ($data['content_data']['pre_grade_topic'] != "") {
            $pre_arr = explode("####", $data['content_data']['pre_grade_topic']);
            $pre_arr_chapter_id = $pre_arr[0];
            $pre_arr_topic_id = $pre_arr[1];

            //If both chapter and topic are mapped
            if ($pre_arr_chapter_id != "" && $pre_arr_topic_id != "") {
                $pretopicData = DB::table('topic_master as t')
                    ->join('chapter_master as c', function ($join) {
                        $join->whereRaw('c.id = t.chapter_id');
                    })->selectRaw('t.id as topic_id,c.id AS chapter_id,c.standard_id,c.subject_id')
                    ->where('t.id', $pre_arr_topic_id)->get()->toArray();

            } else {
                if ($pre_arr_chapter_id != "")//If only chapter is mapped
                {
                    $pretopicData = DB::table('chapter_master as c')
                        ->selectRaw('c.id AS chapter_id,c.standard_id,c.subject_id')
                        ->where('c.id', $pre_arr_chapter_id)->get()->toArray();

                }
            }

            $pretopicData = json_decode(json_encode($pretopicData), true);
            $data['pretopicData'] = $pretopicData[0];            
        }
        //END Get Pre Topic  

        //START Get Post Topic
        $data['posttopicData'] = [];
        if($data['content_data']['post_grade_topic'] != "")
        {
            $post_arr = explode("####",$data['content_data']['post_grade_topic']);
            $post_arr_chapter_id = $post_arr[0];
            $post_arr_topic_id = $post_arr[1];

            //If both chapter and topic are mapped
            if($post_arr_chapter_id != "" && $post_arr_topic_id != "" ) {
                $posttopicData = DB::table('topic_master as t')
                    ->join('chapter_master as c', function ($join) {
                        $join->whereRaw('c.id = t.chapter_id');
                    })->selectRaw('t.id as topic_id,c.id AS chapter_id,c.standard_id,c.subject_id')
                    ->where('t.id', $post_arr_topic_id)->get()->toArray();
            }
            else if($post_arr_chapter_id != "")//If only chapter is mapped
            {
                $posttopicData = DB::table('chapter_master as c')
                    ->selectRaw('c.id AS chapter_id,c.standard_id,c.subject_id')
                    ->where('c.id', $post_arr_chapter_id)->get()->toArray();

            }           
            $posttopicData = json_decode(json_encode($posttopicData),true);
            $data['posttopicData'] = $posttopicData[0];            
        }
        //END Get Post Topic 


        //START Get Cross curriculum Topic
        $data['cctopicData'] = [];
        if($data['content_data']['cross_curriculum_grade_topic'] != "")
        {
            $cc_arr = explode("####",$data['content_data']['cross_curriculum_grade_topic']);
            $cc_arr_chapter_id = $cc_arr[0] ?? [];
            $cc_arr_topic_id = $cc_arr[1] ?? [];

            //If both chapter and topic are mapped
            if($cc_arr_chapter_id != "" && $cc_arr_topic_id != "" ) {
                $cctopicData = DB::table('topic_master as t')
                    ->join('chapter_master as c', function ($join) {
                        $join->whereRaw('c.id = t.chapter_id');
                    })->selectRaw('t.id as topic_id,c.id AS chapter_id,c.standard_id,c.subject_id')
                    ->where('t.id', $cc_arr_topic_id)->get()->toArray();

            }
            else if($cc_arr_chapter_id != "")//If only chapter is mapped
            {
                $cctopicData = DB::table('chapter_master as c')
                    ->selectRaw('c.id AS chapter_id,c.standard_id,c.subject_id')
                    ->where('c.id', $cc_arr_chapter_id)->get()->toArray();

            }           
            $cctopicData = json_decode(json_encode($cctopicData),true);
            $data['cctopicData'] = $cctopicData[0] ?? [];            
        }
        //END Get Cross curriculum Topic 


        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id,$data['content_data']['chapter_id'],$data['content_data']['topic_id']);

        return is_mobile($type, "lms/edit_content", $data, "view");
    }
	
    public function update(Request $request,$id)
    {
        //ValidateInsertData('subject','update');        
        // echo "<pre>";print_r($request);exit;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');
        $show_hide = $request->get('show_hide');
        $show_hide_val = $show_hide ?? '';
        $filePath = "public/lms_content_file/"; 

        $image_data = [];
        if ($request->hasFile('filename')) {
            if ($request->has('hid_filename')) {
                        if (file_exists($filePath.$request->hasFile('filename'))){

                unlink('storage'.$request->input('hid_filename'));
            }
            }
            $img = $request->file('filename');
            $filename = $img->getClientOriginalName();
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;
            //$img->move(public_path().'/lms_content_file/',$newfilename);
            $img->storeAs('public/lms_content_file/', $newfilename);

            $image_data = [
                'file_folder' => '/lms_content_file',
                'filename'    => $newfilename,
                'file_type'   => $ext,
                'file_size'   => $size,
            ];
        } 

        if($request->get('contentType') == "link") {
            $image_data = [
                'filename'  => $request->get('link'),
                'file_type' => "link",
            ];
        }   

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

        $data = [
            'grade_id'                     => $request->get('grade'),
            'standard_id'                  => $request->get('standard'),
            'subject_id'                   => $request->get('subject'),
            'chapter_id'                   => $request->get('chapter'),
            'topic_id'                     => $request->get('topic'),
            'sub_topic_id'                 => $request->get('subtopic'),
            'title'                        => $request->get('title'),
            'description'                  => $request->get('description'),
            'show_hide'                    => $show_hide_val,
            'sort_order'                   => $request->get('sort_order'),
            'meta_tags'                    => $request->get('meta_tags'),
            'content_category'             => $request->get('content_category'),
            'created_by'                   => $user_id,
            'sub_institute_id'             => $sub_institute_id,
            'url'                          => $request->get('link'),
            'restrict_date'                => $request->get('restrict_date'),
            'pre_grade_topic'              => $pre_topic,
            'post_grade_topic'             => $post_topic,
            'cross_curriculum_grade_topic' => $cross_curriculum_topic,
            'syear'                        => $syear,
        ];
        
        $data = array_merge($data,$image_data);    

		contentModel::where(["id" => $id])->update($data);
        
        //START Delete and insert into content_mapping_Data
        contentmappingtypeModel::where(["content_id" => $id])->delete();

        $mapping_type = $request->get('mapping_type');
        $mapping_value = $request->get('mapping_value');
       
        foreach($mapping_type as $key => $val) {
            if ($val != "" && $mapping_value[$key] != "") {
                $contentmappingtype = [
                    'content_id'       => $id,
                    'mapping_type_id'  => $val,
                    'mapping_value_id' => $mapping_value[$key],
                ];
                contentmappingtypeModel::insert($contentmappingtype);
            }
        }
        //END Delete and insert into content_mapping_Data

        $res = [
            "status_code" => 1,
            "message"     => "Content Updated Successfully",
        ];
        $type = $request->input('type');

        // return redirect()->route('topic_master.index', ['id' => $request->get('chapter')]);
        return redirect('/lms/chapter_master?standard_id='.$request->get('standard').'&subject_id='.$request->get('subject').' ');
    }

    public function destroy(Request $request,$id){
        $type = $request->input('type');
        
        $contentdata = contentModel::where(["id" => $id])->get()->toArray();
        $chapter_id = $contentdata[0]['chapter_id'];
        $std = $contentdata[0]['standard_id'];

        contentModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Content Deleted Successfully";
        return redirect()->route('topic_master.index', ['id' => $chapter_id,'standard_id' => $std]);
    }
	
    public function ajax_LMS_MappingValue(Request $request)
    {
        $mapping_type = $request->input("mapping_type");
        $mapping_types = explode(",", $mapping_type);
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return DB::table('lms_mapping_type')
            ->select(['id', 'name'])
            ->whereIn("parent_id", $mapping_types)
            ->where(['status' => '1'])
            ->get()->toArray();
    }

	public function StandardwiseSubject(Request $request)
    {
        $std_id = $request->input("std_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id, 'standard_id' => $std_id])
            ->orderBy('display_name')->get()->toArray();
    }

    public function chapter_search(Request $request) {
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
            ->where(['sub_institute_id'=>$sub_institute_id])      
            ->where('main_topic_id','!=','0')      
            ->get()->toArray();
                    
            foreach($subtopic_data as $subkey => $subval)
            {
                $data['subtopic_data'][$subval['main_topic_id']][] = $subval; 
            }
            
        }
      
        $subject_data = sub_std_mapModel::where(['sub_institute_id' => $sub_institute_id,'standard_id' => $standard])
        ->orderBy('display_name')->get()->toArray();		
        
        $data['subject_arr'] = $subject_data;        
        $data['status_code'] = 1;
        $data['message'] = "SUCCESS";           
        $data['grade'] = $grade;           
        $data['standard'] = $standard;           
        $data['subject'] = $subject;           
        

		return is_mobile($type, "lms/show_chapter", $data, "view");
    }
    
    public function ajax_SubjectwiseChapter(Request $request)
    {
        $sub_id = $request->input("sub_id");
        $std_id = $request->input("std_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return chapterModel::where([
            'sub_institute_id' => $sub_institute_id,
            'subject_id'       => $sub_id,
            'standard_id'      => $std_id,
            'availability'     => 1,
        ])->get()->toArray();
    }

    public function ajax_ChapterwiseTopic(Request $request)
    {
        $chapter_id = $request->input("chapter_id");
        $main_topic_id = $request->input("main_topic_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return topicModel::where([
            'sub_institute_id' => $sub_institute_id,
            'chapter_id'       => $chapter_id,
            'main_topic_id'    => $main_topic_id,
        ])->get()->toArray();
    }
        
	
}
