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
use Illuminate\Support\Facades\Storage;
use App\Services\OpenAIService;

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
            $sub_institute_id = $request->input('sub_institute_id');
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
        return $breadcrum_data[0] ?? [];
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
    
    $file_folder = $ext = $size = $newfilename = $file_url = '';
    
    // Check if Gamma presentation URL is present (this should be treated as link)
    $gamma_presentation_url = $request->get('gamma_presentation_url');
    $gamma_presentation_name = $request->get('gamma_presentation_name');
    
    // Check if IBL generated URL is present
    $ibl_generated_url = $request->get('ibl_generated_url');
    $ibl_content_type = $request->get('ibl_content_type');
    
    // Priority 1: Gamma Presentation (URL) - Store the URL as filename with file_type = 'link'
    if (!empty($gamma_presentation_url)) {
        // This is a Gamma presentation - store the actual URL as filename
        $newfilename = $gamma_presentation_url; // Store the URL, not the name
        $ext = 'link';
        $file_folder = '/lms_content_file';
        $file_url = $gamma_presentation_url;
        
        // Also store the name in title field (already done via form)
        \Log::info('Gamma URL stored in filename (topic-wise): ' . $newfilename);
    }
    // Priority 2: IBL generated content
    elseif (!empty($ibl_generated_url)) {
        if ($ibl_content_type === 'link') {
            $newfilename = $ibl_generated_url; // Store the URL
            $ext = 'link';
            $file_url = $ibl_generated_url;
        } else {
            // For PDF, store the URL as filename
            $newfilename = $ibl_generated_url;
            $ext = $ibl_content_type ?? 'pdf';
            $file_url = $ibl_generated_url;
        }
        $file_folder = '/lms_content_file';
    }
    // Priority 3: Regular file upload (This includes Classroom Presentation PDFs)
    elseif($request->hasFile('filename'))
    {           
        $img = $request->file('filename');
        $filename = $img->getClientOriginalName();
        $ext = $img->getClientOriginalExtension();
        $size = $img->getSize();
        $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;             
        $file_folder = '/lms_content_file';
        Storage::disk('digitalocean')->putFileAs('public/lms_content_file/', $img, $newfilename, 'public');
        $file_url = ''; // No URL for uploaded files
    }
    // Priority 4: Manual link entry
    elseif($request->get('contentType') == "link")
    {
        $newfilename = $request->get('link');
        $ext = "link";
        $file_url = $request->get('link');
        // If Gamma presentation name is provided, use it as filename
        if($request->has('gamma_presentation_name') && !empty($request->get('gamma_presentation_name'))) {
            $newfilename = $request->get('gamma_presentation_name');
        }
    }       
       
    $chapter_data = chapterModel::select('*')        
    ->where(['chapter_master.sub_institute_id'=>$sub_institute_id,'chapter_master.id'=>$request->get('hid_chapter_id')])         
    ->get()->toArray(); 
    $chapter_data = $chapter_data[0] ?? []; 

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

    // Debug log to check what's being saved
    \Log::info('Content being saved:', [
        'content_category' => $request->get('content_category'),
        'file_type' => $ext,
        'filename' => $newfilename,
        'title' => $request->get('title')
    ]);

    $content = [
        'grade_id'                     => $chapter_data['grade_id'] ?? null,
        'standard_id'                  => $chapter_data['standard_id'] ?? null,
        'subject_id'                   => $chapter_data['subject_id'] ?? null,
        'chapter_id'                   => $request->get('hid_chapter_id'),
        'topic_id'                     => $request->get('hid_topic_id'),
        'title'                        => $request->get('title'),
        'description'                  => $request->get('description'),
        'file_folder'                  => $file_folder,
        'filename'                     => $newfilename,
        'url'                          => $file_url,
        'file_type'                    => $ext,
        'file_size'                    => $size,
        'show_hide'                    => $show_hide_val,
        'sort_order'                   => $request->get('sort_order'),
        'meta_tags'                    => $request->get('meta_tags'),
        'content_category'             => $request->get('content_category'), // This will save "Classroom Presentation"
        'created_by'                   => $user_id,
        'sub_institute_id'             => $sub_institute_id,
        'restrict_date'                => !empty($request->get('restrict_date')) ? date('Y-m-d', strtotime($request->get('restrict_date'))) : null,
        'pre_grade_topic'              => $pre_topic,
        'post_grade_topic'             => $post_topic,
        'cross_curriculum_grade_topic' => $cross_curriculum_topic,
        'basic_advance'                => $basic_advanced_val,
        'syear'                        => $syear,
    ];
    
    // Log what's being stored (topic-wise)
    \Log::info('Storing topic-wise content:', [
        'filename' => $newfilename,
        'file_type' => $ext,
        'title' => $request->get('title'),
        'content_category' => $request->get('content_category'),
        'gamma_url' => $gamma_presentation_url ?? ''
    ]);
    
    contentModel::insert($content);
    $last_id = DB::getPDO()->lastInsertId();

    $mapping_type = $request->get('mapping_type');
    $mapping_value = $request->get('mapping_value');
    if (!empty($mapping_type)) {
        foreach ($mapping_type as $key => $val) {
            if ($val != "" && isset($mapping_value[$key]) && $mapping_value[$key] != "") {
                $contentmappingtype = [
                    'content_id'       => $last_id,
                    'mapping_type_id'  => $val,
                    'mapping_value_id' => $mapping_value[$key],
                ];
                contentmappingtypeModel::insert($contentmappingtype);
            }
        }
    }

    $res = [
        "status_code" => 1,
        "message"     => "Content Added Successfully",
    ];
    $type = $request->input('type');
    if($type == "API")
        return is_mobile($type, "content_master.index", $res, "redirect");
    else{
        if ($request->has('hid_topic_id') && !empty($request->get('hid_topic_id'))) {
            return redirect()->route('topic_master.index', ['id' => $request->get('hid_chapter_id'),'standard_id' => $chapter_data['standard_id'] ?? '', 'subject_id' => $chapter_data['subject_id'] ?? '', 'perm' => $sub_institute_id]);
        } else {
            return redirect()->route('chapter_master.index', ['standard_id' => $chapter_data['standard_id'] ?? '', 'subject_id' => $chapter_data['subject_id'] ?? '', 'perm' => $sub_institute_id]);
        }
    }
}


    public function storeChapter(Request $request){      
        //echo "<pre>"; print_r($request->all()); exit;
        $type = $request->input('type');
        if($type == "API"){
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
            $user_id = $request->input('user_id');
        }
        else{
            $sub_institute_id = $request->session()->get('sub_institute_id');       
            $syear = $request->session()->get('syear');         
            $user_id = $request->session()->get('user_id');
        }

    $show_hide = $request->get('show_hide');
    $show_hide_val = $show_hide ?? '';

    //Basic means 1 and advance means 0 
    $basic_advanced = $request->get('toggle_basic_advanced');
    $basic_advanced_val = ! isset($basic_advanced) ? '0' : '1';
    
    $file_folder = $ext = $size = $newfilename = "";
    
    // Check if Gamma presentation URL is present (this should be treated as link)
    $gamma_presentation_url = $request->get('gamma_presentation_url');
    $gamma_presentation_name = $request->get('gamma_presentation_name');
    
    // Check if IBL generated URL is present
    $ibl_generated_url = $request->get('ibl_generated_url');
    $ibl_content_type = $request->get('ibl_content_type');
    
    // Priority 1: Gamma Presentation (URL) - Store the URL as filename with file_type = 'link'
    if (!empty($gamma_presentation_url)) {
        // This is a Gamma presentation - store the actual URL as filename
        $newfilename = $gamma_presentation_url; // Store the URL, not the name
        $ext = 'link';
        $file_folder = '/lms_content_file';
        
        // Also store the name in title field (already done via form)
        \Log::info('Gamma URL stored in filename: ' . $newfilename);
    }
    // Priority 2: IBL generated content
    elseif (!empty($ibl_generated_url)) {
        if ($ibl_content_type === 'link') {
            $newfilename = $ibl_generated_url; // Store the URL
            $ext = 'link';
        } else {
            // For PDF, store the URL as filename
            $newfilename = $ibl_generated_url;
            $ext = $ibl_content_type ?? 'pdf';
        }
        $file_folder = '/lms_content_file';
    }
    // Priority 3: AI generated filename
    elseif (!empty($request->get('ai_generated_filename'))) {
        // AI generated file - use the generated filename
        $newfilename = $request->get('ai_generated_filename');
        $file_folder = '/lms_content_file';
        $ext = 'pdf';
        
        // Move the file from storage to DigitalOcean
        $sourcePath = storage_path('app/public/pdfs/' . $request->get('ai_generated_filename'));
        if (file_exists($sourcePath)) {
            $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.pdf';
            // Read the file content and upload to DigitalOcean
            $fileContent = file_get_contents($sourcePath);
            Storage::disk('digitalocean')->put('public/lms_content_file/' . $newfilename, $fileContent, 'public');
        }
    }
    // Priority 4: Regular file upload
    elseif($request->hasFile('filename'))
    {           
        $img = $request->file('filename');
        $filename = $img->getClientOriginalName();
        $ext = $img->getClientOriginalExtension();
        $size = $img->getSize();
        $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;             
        $file_folder = '/lms_content_file';
        Storage::disk('digitalocean')->putFileAs('public/lms_content_file/', $img, $newfilename, 'public');
    }
    // Priority 5: Manual link entry
    elseif($request->get('contentType') == "link")
    {
        $newfilename = $request->get('link'); // Store the URL
        $ext = "link";
    }
   
    $chapter_data = chapterModel::select('*')        
    ->where(['chapter_master.sub_institute_id'=>$sub_institute_id,'chapter_master.id'=>$request->get('hid_chapter_id')])         
    ->get()->toArray(); 
    $chapter_data = $chapter_data[0] ?? []; 

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

    // Log what's being stored
    \Log::info('Storing content:', [
        'filename' => $newfilename,
        'file_type' => $ext,
        'title' => $request->get('title'),
        'gamma_url' => $gamma_presentation_url
    ]);

    $content = [
        'grade_id'                     => $chapter_data['grade_id'],
        'standard_id'                  => $chapter_data['standard_id'],
        'subject_id'                   => $chapter_data['subject_id'],
        'chapter_id'                   => $request->get('hid_chapter_id'),
        'topic_id'                     => $request->get('hid_topic_id'),
        'title'                        => $request->get('title'),
        'description'                  => $request->get('description'),
        'file_folder'                  => $file_folder,
        'filename'                     => $newfilename, // This will store the URL for link type
        'file_type'                    => $ext, // This will be 'link'
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

    if($type == "API")
        return is_mobile($type, "content_master.index", $res, "redirect");
    else
        return redirect()->route('chapter_master.index', ['standard_id' => $request->get('hid_standard_id'), 'subject_id' => $request->get('hid_subject_id'),'perm'=>$sub_institute_id]);
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
            $pre_arr_chapter_id = $pre_arr[0] ?? '-';
            $pre_arr_topic_id = $pre_arr[1] ?? '-';

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
            $data['pretopicData'] = $pretopicData[0] ?? [];            
        }
        //END Get Pre Topic  

        //START Get Post Topic
        $data['posttopicData'] = [];
        if($data['content_data']['post_grade_topic'] != "")
        {
            $post_arr = explode("####",$data['content_data']['post_grade_topic']);
            $post_arr_chapter_id = $post_arr[0] ?? '-';
            $post_arr_topic_id = $post_arr[1] ?? '-';

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
            $data['posttopicData'] = $posttopicData[0] ?? [];            
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
        $url = $request->get('link');
        $image_data = [];
        if ($request->hasFile('filename')) {
            if ($request->has('hid_filename')) {
                /* if (file_exists($filePath.$request->hasFile('filename'))){
                 unlink('storage'.$request->input('hid_filename'));
                }*/

                // delete file from digital ocean 
                /*
                $digiPath  = 'public/' . $request->has('hid_filename');
                if (Storage::disk('digitalocean')->exists($digiPath)) {
                    Storage::disk('digitalocean')->delete($digiPath);
                    if (!Storage::disk('digitalocean')->exists($digiPath)) {
                        $message="file deleted";
                    }   
                } 
                */
            }

            $img = $request->file('filename');
            $filename = $img->getClientOriginalName();
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;
            //$img->move(public_path().'/lms_content_file/',$newfilename);
            // $img->storeAs('public/lms_content_file/', $newfilename);
            Storage::disk('digitalocean')->putFileAs('public/lms_content_file/', $img, $newfilename, 'public');

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
            $url='';
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
            'url'                          => $url,
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
        return redirect('/lms/chapter_master?standard_id='.$request->get('standard').'&subject_id='.$request->get('subject').'&perm='.$sub_institute_id.' ');
    }

    public function destroy(Request $request,$id){
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $contentdata = contentModel::where(["id" => $id])->get()->toArray();
        $chapter_id = $contentdata[0]['chapter_id'];
        $std = $contentdata[0]['standard_id'];
        $subject = $contentdata[0]['subject_id'];

        contentModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Content Deleted Successfully";
        return redirect()->route('chapter_master.index', ['standard_id' => $std,'subject_id' => $subject,'perm'=>$sub_institute_id]);
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
    // updated on 13-05-2025 by uma
	public function processAIData(Request $request)
    {
         return response()->json([
                'title' => '',
                'description' => '',
            ]);
        //([
        //     $request->validate 'standard_id' => 'required',
        //     'subject_name' => 'required',
        //     'chapter_name' => 'required',
        //     'topic_name' => 'required',
        //     'content_type' => 'required',
        //     'content_category' => 'required',
        // ]);

        // try {
        //     $openAIService = new OpenAIService();
        //     $generatedData = $openAIService->generateTitleAndDescription(
        //         $request->topic_name,
        //         $request->chapter_name,
        //         $request->subject_name,
        //         $request->content_category // Add the missing fourth argument
        //     );

        //     return response()->json([
        //         'title' => $generatedData['title'],
        //         'description' => $generatedData['description'],
        //     ]);
        // } catch (\Exception $e) {
        //     return response()->json([
        //         'title' => '',
        //         'description' => '',
        //     ]);
        // }
    }
    public function generateSportsData(Request $request)
    {   
        try {
        $request->validate([
            'standard_id' => 'required',
            'subject_name' => 'required',
            'chapter_name' => 'required',
            'topic_name' => 'required',
            'content_category' => 'required',
            'content_type' => 'required',
        ]);

        $openAIService = new OpenAIService();
        $filePath = $openAIService->generateSportsData(
            $request->topic_name,
            $request->chapter_name,
            $request->subject_name,
            $request->content_category,
            $request->content_type,
        );

        if ($filePath) {
            return response()->json(['file_url' => $filePath]);
        } else {
            return response()->json(['error' => 'Failed to generate.'], 500);
        }
    }catch (\Exception $e) {
            // Log::error('Error generating Data: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }
    public function generateLessonPlan(Request $request)
    {   
    //     try {
    //     $request->validate([
    //         'standard_id' => 'required',
    //         'subject_name' => 'required',
    //         'chapter_name' => 'required',
    //         'topic_name' => 'required',
    //         'content_category' => 'required',
    //         'content_type' => 'required',
    //         'booklist_data' => 'required|array',
    //     ]);

    //     $openAIService = new OpenAIService();
    //     $result = $openAIService->generateLessonPlan(
    //         $request->topic_name,
    //         $request->chapter_name,
    //         $request->subject_name,
    //         $request->content_category,
    //         $request->content_type,
    //         $request->booklist_data
    //     );
    //     if (isset($result['fileUrl']) && isset($result['prompt'])) {
    //         return response()->json([
    //             'file_url' => $result['fileUrl'],
    //             'prompt' => $result['prompt']
    //         ]);
    //     } else {
    //         return response()->json(['error' => 'Failed to generate.'], 500);
    //     }
    // }catch (\Exception $e) {
            // Log::error('Error generating Data: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
    //     }
    return response()->json([
            'file_url' => '',
            'prompt' => ''
        ]);
    }
    public function generateLessonPlanNew(Request $request)
    {   
    //     try {
    //     $request->validate([
    //         'standard_id' => 'required',
    //         'subject_name' => 'required',
    //         'chapter_name' => 'required',
    //         'topic_name' => 'required',
    //         'content_category' => 'required',
    //         'content_type' => 'required',
    //         'booklist_data' => 'required|array',
    //         'prompt' => 'required'
    //     ]);
    //     $openAIService = new OpenAIService();
    //     $result = $openAIService->generateLessonPlanNew(
    //         $request->topic_name,
    //         $request->chapter_name,
    //         $request->subject_name,
    //         $request->content_category,
    //         $request->content_type,
    //         $request->booklist_data,
    //         $request -> prompt
    //     );
    //     if (isset($result['fileUrl']) && isset($result['prompt'])) {
    //         return response()->json([
    //             'file_url' => $result['fileUrl'],
    //             'prompt' => $result['prompt']
    //         ]);
    //     } else {
    //         return response()->json(['error' => 'Failed to generate.'], 500);
    //     }
    // }catch (\Exception $e) {
            // Log::error('Error generating Data: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
    //     }
     return response()->json([
            'file_url' => '',
            'prompt' =>''
        ]);
    }

    /**
     * Generate slides based on slide count
     * Creates PDF from AI-generated outline and uploads it
     */
    public function generateSlides(Request $request)
    {
        try {
            $request->validate([
                'standard_id' => 'required',
                'subject_name' => 'required',
                'chapter_name' => 'required',
                //'topic_name' => 'required',
                'slide_count' => 'required|integer|min:1|max:50',
            ]);

            $standardId = $request->standard_id;
            $subjectName = $request->subject_name;
            $chapterName = $request->chapter_name;
            //$topicName = $request->topic_name;
            $contentCategory = $request->content_category ?? $request->mapping_value ?? 'General';
            $slideCount = $request->slide_count;

            // Generate outline using OpenAI service
            $openAIService = new OpenAIService();
            
            // Generate the outline using the service method (returns JSON structured data)
            $slidesData = $openAIService->generateSlideOutline(
                $topicName, 
                $chapterName, 
                $subjectName, 
                $standardId, 
                $contentCategory, 
                $slideCount
            );
            
            // Save as JSON file
            $jsonResult = $openAIService->saveSlideOutlineAsJson($slidesData, $topicName);
            
            if ($jsonResult) {
                // Get the filename
                $fileName = $jsonResult['file_name'];
                
                return response()->json([
                    'success' => true,
                    'file_name' => $fileName,
                    'message' => 'Slides generated successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save slide outline'
                ], 500);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate slides with KAAB competencies and pedagogical approach
     */
    public function generateKAABSlides(Request $request)
    {
        try {
            $request->validate([
                'standard_id' => 'required',
                'subject_name' => 'required',
                'chapter_name' => 'required',
                //'topic_name' => 'required',
                'slide_count' => 'required|integer|min:1|max:50',
            ]);

            $params = [
                //'topicName' => $request->topic_name,
                'chapterName' => $request->chapter_name,
                'subjectName' => $request->subject_name,
                'standardId' => $request->standard_id,
            'contentCategory' => $request->content_category ?? $request->mapping_value ?? 'General',
            'slideCount' => $request->slide_count,
            
            // KAAB competencies
            'knowledge' => $request->knowledge ?? [],
            'ability' => $request->ability ?? [],
            'attitude' => $request->attitude ?? [],
            'behaviour' => $request->behaviour ?? [],
            
            // Pedagogical approach
            'mappingType' => $request->mapping_type ?? '',
            'mappingValue' => $request->mapping_value ?? '',
            'mappingReason' => $request->mapping_reason ?? '',
            
            // Additional context
            'instructionTaskText' => $request->instruction_task_text ?? '',
            'criticalWorkFunction' => $request->critical_work_function ?? '',
            'jobRole' => $request->job_role ?? '',
            'keyTask' => $request->key_task ?? '',
            'industry' => $request->industry ?? '',
            'department' => $request->department ?? '',
            'modalityString' => $request->modality_string ?? 'Online',
        ];

        // Generate outline using OpenAI service with KAAB
        $openAIService = new OpenAIService();
        $slidesData = $openAIService->generateKAABSlideOutline($params);
        
        // Save as JSON file
        $jsonResult = $openAIService->saveSlideOutlineAsJson($slidesData);
        
        if ($jsonResult) {
            // Get the filename
            $fileName = $jsonResult['file_name'];
            
            return response()->json([
                'success' => true,
                'file_name' => $fileName,
                'slides_data' => $slidesData,
                'message' => 'KAAB slides generated successfully'
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save slide outline'
            ], 500);
        }
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Internal Server Error: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Generate PDF Presentation using Gamma API
 */
public function generateGammaPresentation(Request $request)
{
    try {
        $request->validate([
            //'topic_name' => 'required',
            'chapter_name' => 'required',
            'subject_name' => 'required',
            'slide_count' => 'required|integer|min:1|max:50',
        ]);

        $params = [
            //'topicName' => $request->topic_name,
            'chapterName' => $request->chapter_name,
            'subjectName' => $request->subject_name,
            'standardId' => $request->standard_id ?? '',
            'contentCategory' => $request->content_category ?? 'General',
            'slideCount' => $request->slide_count,
            'keyTopics' => $request->key_topics ?? '',
        ];

       

        $openAIService = new OpenAIService();
        $result = $openAIService->generateGammaPresentation($params);

        return response()->json($result);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' => 'Error generating presentation: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Get Gamma Presentation Generation Status
 */
public function getGammaPresentationStatus(Request $request)
{
    
    try {
        $request->validate([
            'generation_id' => 'required',
        ]);

        $openAIService = new OpenAIService();
        $result = $openAIService->getGammaPresentationStatus($request->generation_id);


        return response()->json($result);

    } catch (\Exception $e) {
       
        return response()->json([
            'success' => false,
            'message' => 'Error getting status: ' . $e->getMessage()
        ], 500);
    }
}

}