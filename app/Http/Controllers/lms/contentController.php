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
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        'user_profile_name'            => $request->get('user_profile_name', $request->session()->get('user_profile_name')),
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
        'user_profile_name'            => $request->get('user_profile_name', $request->session()->get('user_profile_name')),
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
            'user_profile_name'            => $request->get('user_profile_name', $request->session()->get('user_profile_name')),
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
 * Generate a Gamma-style PDF presentation from AI generated content.
 */
public function generateGammaStyleSlide(Request $request)
{
    try {
        $request->validate([
            'generated_content' => 'required|string',
            'subject_name' => 'required|string',
            'chapter_name' => 'required|string',
            'slide_count' => 'nullable|integer|min:1|max:20',
        ]);

        $openAIService = new OpenAIService();
        $result = $openAIService->generateGammaStylePresentationPdf([
            'generatedContent' => $request->generated_content,
            'standardId' => $request->standard_id ?? '',
            'subjectName' => $request->subject_name,
            'chapterName' => $request->chapter_name,
            'topicName' => $request->topic_name ?? '',
            'contentCategory' => $request->content_category ?? 'Classroom Presentation',
            'slideCount' => $request->slide_count ?? 8,
        ]);

        return response()->json([
            'success' => true,
            'file_name' => $result['file_name'],
            'file_base64' => base64_encode($result['pdf_binary']),
            'title' => $result['title'],
            'message' => 'Slide PDF generated successfully',
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error generating slide PDF: ' . $e->getMessage()
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
/**
 * Extract mapping type and value from AI prompt
 */
private function extractMappingsFromPrompt($prompt)
{
    $mappings = [];
    
    // Define mapping patterns based on your lms_mapping_type table
    // This is an example - adjust based on your actual mapping types
    $mappingPatterns = [
        // Example: If prompt contains "Bloom's Taxonomy Level 2"
        'bloom' => [
            'type_id' => 1,  // ID for Bloom's Taxonomy in lms_mapping_type
            'patterns' => [
                'Remember' => 101,
                'Understand' => 102,
                'Apply' => 103,
                'Analyze' => 104,
                'Evaluate' => 105,
                'Create' => 106,
            ]
        ],
        // Add more mapping types as needed
    ];
    
    foreach ($mappingPatterns as $key => $config) {
        foreach ($config['patterns'] as $keyword => $valueId) {
            if (stripos($prompt, $keyword) !== false) {
                $mappings[] = [
                    'mapping_type_id' => $config['type_id'],
                    'mapping_value_id' => $valueId
                ];
                break; // Only take first match per type
            }
        }
    }
    
    return $mappings;
}
public function generateGammaPDF(Request $request)
{
    try {
            // Validate the request
            $request->validate([
                'prompt' => 'required|string|max:400000', // Gamma supports up to 400k chars [citation:4]
                'format' => 'nullable|in:presentation,document,social',
                'export_format' => 'nullable|in:pdf,pptx',
            ]);

            $apiKey = env('GAMMA_API_KEY');
            $baseUrl = env('GAMMA_BASE_URL');

            if (!$apiKey || !$baseUrl) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gamma API credentials are missing'
                ], 500);
            }

            // Step 1: Create a generation (async)
            $generationResponse = Http::withHeaders([
                'X-API-KEY' => $apiKey,  // Important: Not Bearer token, but X-API-KEY header [citation:1]
                'Content-Type' => 'application/json',
            ])->timeout(60)
              ->post($baseUrl . 'generations', [  // Note: No leading slash needed if base URL ends with /
                'inputText' => $request->prompt,
                'textMode' => 'generate',  // Rewrite and expand content [citation:7]
                'format' => $request->format ?? 'document',
                'exportAs' => $request->export_format ?? 'pdf',
                'numCards' => $request->slide_count ?? 10,  // Optional: control number of cards/pages
            ]);

            if (!$generationResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create generation',
                    'error' => $generationResponse->json()
                ], $generationResponse->status());
            }

            $generationId = $generationResponse->json('generationId');
            
            if (!$generationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No generation ID received'
                ], 500);
            }

            // Step 2: Poll for completion (max 30 attempts = 2.5 minutes)
            $maxAttempts = 30;
            $attempts = 0;
            $status = 'processing';
            $result = null;

            while ($attempts < $maxAttempts && $status !== 'completed' && $status !== 'failed') {
                $attempts++;
                
                // Wait 5 seconds between polls as recommended [citation:2]
                if ($attempts > 1) {
                    sleep(5);
                }
                
                $pollResponse = Http::withHeaders([
                    'X-API-KEY' => $apiKey,
                ])->get($baseUrl . 'generations/' . $generationId);
                
                if (!$pollResponse->successful()) {
                    Log::warning('Poll attempt failed', [
                        'attempt' => $attempts,
                        'response' => $pollResponse->body()
                    ]);
                    continue;
                }
                
                $result = $pollResponse->json();
                $status = $result['status'] ?? 'processing';
                
                Log::info('Polling generation status', [
                    'generation_id' => $generationId,
                    'status' => $status,
                    'attempt' => $attempts
                ]);
            }

            // Check final status
            if ($status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => $status === 'failed' ? 'Generation failed' : 'Generation timeout',
                    'generation_id' => $generationId,
                    'status' => $status
                ], 408);
            }

            // Success! Return the file URL and details
            return response()->json([
                'success' => true,
                'message' => 'PDF generated successfully',
                'data' => [
                    'file_url' => $result['exportUrl'],  // Direct download URL [citation:1]
                    'gamma_url' => $result['gammaUrl'],  // View in Gamma app
                    'generation_id' => $generationId,
                    'status' => 'completed',
                     'content_category' => $request->input('content_category', 'Classroom Presentation'), // Add this line
                'title' => $request->input('title', 'Gamma Generated Presentation'), // Optional: pass title
                
                    'credits_used' => $result['credits']['deducted'] ?? null,
                    'credits_remaining' => $result['credits']['remaining'] ?? null,
                    'created_at' => now()->toISOString(),
                            'mappings' => $request->input('mappings', [])  // Pass mappings from request

                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('PDF Generation Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }

    }

    public function storeGammaContent(Request $request)
    {
        @set_time_limit(600);

        $sub_institute_id = $request->sub_institute_id;
        $syear = $request->input('syear') ?? $request->session()->get('syear');
        $user_id = $request->input('user_id') ?? $request->session()->get('user_id');

        $request->validate([
            'prompt' => 'required|string|max:400000',
            'chapter_name' => 'required|string',
            'content_type' => 'required|string',
            'format' => 'nullable|in:presentation,document,social',
            'export_format' => 'nullable|in:pdf,pptx',
            'slide_count' => 'nullable|integer|min:1|max:50',
        ]);

        $chapterName = $request->chapter_name;
        $prompt = $request->prompt;
        $contentType = trim((string) $request->input('content_type'));
        $isPresentation = strcasecmp($contentType, 'Presentation') === 0;
        $format = $isPresentation ? 'presentation' : 'document';
        $exportAs = $isPresentation ? 'pptx' : 'pdf';
        $numCards = (int) $request->input('slide_count', 30);
        $themeId = env('GAMMA_THEME_ID');

        $validThemes = ['simple', 'minimal', 'corporate', 'creative', 'bold', 'elegant', 'modern'];
        if (empty($themeId) || !in_array($themeId, $validThemes)) {
            $themeId = null;
        }

        $chapterData = chapterModel::select('chapter_master.*')
            ->where([
                'chapter_master.chapter_name' => $chapterName,
            ])
            ->first();

        if (!$chapterData) {
            return response()->json([
                'success' => false,
                'message' => 'Chapter not found: ' . $chapterName
            ], 404);
        }

        $subjectName = DB::table('sub_std_map')
            ->where('subject_id', $chapterData->subject_id)
            ->where('standard_id', $chapterData->standard_id)
            ->where(function ($query) use ($chapterData) {
                $query->where('sub_institute_id', $chapterData->sub_institute_id)
                    ->orWhere('sub_institute_id', 1);
            })
            ->orderByDesc('sub_institute_id')
            ->value('display_name');

        if (!$subjectName) {
            $subjectName = DB::table('subject')
                ->where('id', $chapterData->subject_id)
                ->value('subject_name');
        }

        $standardName = DB::table('standard')
            ->where('id', $chapterData->standard_id)
            ->value('name');

        $boardName = $request->input('board_name');
        if (!$boardName && is_string($standardName) && preg_match('/^([A-Za-z]+)[-\s]+(.+)$/', $standardName, $matches)) {
            $boardName = strtoupper($matches[1]);
            $standardName = trim($matches[2]);
        }

        $isClassroomActivity = in_array(strtolower($contentType), [
            'classroom activity',
            'classroom activities',
        ], true);

        if ($isClassroomActivity) {
            $contentType = 'Classroom Activity';
            if (trim((string) $prompt) === '') {
                $prompt = $this->classroomActivityPrompt(
                    $chapterName,
                    $boardName ?: 'CBSE',
                    $standardName,
                    $subjectName
                );
            }
        }

        if (!$isPresentation) {
            $apiKey = env('GEMINI_API_KEY');
            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gemini API key is not configured. Please set GEMINI_API_KEY in the backend .env file.',
                ], 500);
            }

            try {
                $model = env('GEMINI_MODEL', 'gemini-2.5-flash');
                $baseUrl = rtrim(env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/');

                Log::info('Gemini content generation request', [
                    'chapter_name' => $chapterName,
                    'content_type' => $contentType,
                    'sub_institute_id' => $sub_institute_id,
                    'user_id' => $user_id,
                ]);

                $geminiResponse = Http::timeout((int) env('GEMINI_REQUEST_TIMEOUT', 120))
                    ->retry(1, 500)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'x-goog-api-key' => $apiKey,
                    ])
                    ->post("{$baseUrl}/models/{$model}:generateContent", [
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => $prompt],
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.35,
                            'topP' => 0.9,
                            'maxOutputTokens' => 24000,
                        ],
                    ]);

                if (!$geminiResponse->successful()) {
                    Log::warning('Gemini content generation failed', [
                        'status' => $geminiResponse->status(),
                        'body' => $geminiResponse->body(),
                    ]);

                    $geminiPayload = $geminiResponse->json();
                    $retryAfterSeconds = $this->geminiRetryDelaySeconds($geminiPayload);
                    $statusCode = $geminiResponse->status() === 429 ? 429 : 502;
                    $responseBody = [
                        'success' => false,
                        'message' => $this->geminiContentErrorMessage($geminiPayload, $geminiResponse->status()),
                    ];

                    if ($retryAfterSeconds !== null) {
                        $responseBody['retry_after_seconds'] = $retryAfterSeconds;
                    }

                    return response()->json($responseBody, $statusCode);
                }

                $generatedContent = $this->extractGeminiText($geminiResponse->json());
                if (!$generatedContent) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Gemini returned empty content. Please try again.',
                    ], 502);
                }

                $pdf = $this->renderGeneratedContentPdf($generatedContent, $chapterName, $contentType);
                $safeChapter = preg_replace('/[^A-Za-z0-9_-]+/', '_', strtolower($chapterName));
                $safeType = preg_replace('/[^A-Za-z0-9_-]+/', '_', strtolower($contentType));
                $fileName = trim($safeType, '_') . '_' . trim($safeChapter, '_') . '_' . time() . '.pdf';
                $spacesPath = 'public/lms_content_file/' . $fileName;
                $disk = Storage::disk('digitalocean');
                $uploaded = $disk->put($spacesPath, $pdf, 'public');

                if (!$uploaded || !$disk->exists($spacesPath)) {
                    Log::error('DigitalOcean Spaces PDF upload failed', [
                        'path' => $spacesPath,
                        'chapter_name' => $chapterName,
                        'content_type' => $contentType,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'PDF was generated, but upload to DigitalOcean Spaces failed.',
                        'data' => [
                            'storage_path' => $spacesPath,
                        ],
                    ], 500);
                }

                $fileUrl = $disk->url($spacesPath);
                if (empty($fileUrl)) {
                    $endpoint = rtrim((string) env('DO_SPACES_ENDPOINT'), '/');
                    $bucket = trim((string) env('DO_SPACES_BUCKET'), '/');
                    $fileUrl = $endpoint && $bucket
                        ? "{$endpoint}/{$bucket}/{$spacesPath}"
                        : $spacesPath;
                }

                $content = [
                    'grade_id' => $chapterData->grade_id,
                    'standard_id' => $chapterData->standard_id,
                    'subject_id' => $chapterData->subject_id,
                    'chapter_id' => $chapterData->id,
                    'topic_id' => null,
                    'title' => $chapterName . ' ' . $contentType,
                    'description' => $prompt,
                    'file_folder' => '/lms_content_file',
                    'filename' => $fileName,
                    'url' => $fileUrl,
                    'file_type' => 'pdf',
                    'file_size' => strlen($pdf) ?: null,
                    'show_hide' => '1',
                    'sort_order' => null,
                    'meta_tags' => null,
                    'content_category' => $contentType,
                    'created_by' => $user_id,
                    'sub_institute_id' => $sub_institute_id,
                    'restrict_date' => null,
                    'pre_grade_topic' => null,
                    'post_grade_topic' => null,
                    'cross_curriculum_grade_topic' => null,
                    'basic_advance' => '1',
                    'user_profile_name' => $request->user_profile_name,
                    'syear' => $syear,
                ];

                contentModel::insert($content);
                $lastId = DB::getPDO()->lastInsertId();

                return response()->json([
                    'success' => true,
                    'status_code' => 1,
                    'message' => $contentType . ' generated with Gemini and stored successfully',
                    'data' => [
                        'id' => $lastId,
                        'file_url' => $fileUrl,
                        'storage_path' => $spacesPath,
                        'filename' => $fileName,
                        'file_type' => 'pdf',
                        'content_category' => $contentType,
                        'model' => $model,
                    ],
                ], 201);
            } catch (\Exception $e) {
                Log::error('Gemini Content API Error: ' . $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred: ' . $e->getMessage(),
                ], 500);
            }
        }

        try {
            $requestJson = [
                'inputText' => $prompt,
                'textMode' => 'generate',
                'format' => $format,
                'numCards' => (int) $numCards,
                'cardSplit' => 'auto',
                'additionalInstructions' => $request->additional_instructions ?? '',
                'exportAs' => $exportAs,
                'textOptions' => [
                    'amount' => 'detailed',
                    'tone' => 'professional, inspiring',
                    'audience' => 'students',
                    'language' => 'en'
                ],
                'imageOptions' => [
                    'source' => 'aiGenerated',
                    'model' => 'imagen-4-pro',
                    'style' => 'photorealistic'
                ],
                'cardOptions' => [
                    'dimensions' => 'fluid'
                ]
            ];
// echo "<pre>";
// print_r($requestJson);
// exit();
            if (!empty($themeId)) {
                $requestJson['themeId'] = $themeId;
            }

            Log::info('Gamma content generation request', [
                'chapter_name' => $chapterName,
                'slide_count' => $numCards,
                'format' => $format,
                'export_format' => $exportAs,
                'sub_institute_id' => $sub_institute_id,
                'user_id' => $user_id,
            ]);

            $generationResponse = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-API-KEY' => env('GAMMA_API_KEY'),
            ])->timeout(120)->post(env('GAMMA_BASE_URL', 'https://public-api.gamma.app/v1.0/') . 'generations', $requestJson);
                // return $generationResponse;
            if (!$generationResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create generation',
                    'error' => $generationResponse->json()
                ], $generationResponse->status());
            }

            $generationId = $generationResponse->json('generationId');

            if (!$generationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No generation ID received'
                ], 500);
            }

            $maxAttempts = 90;
            $attempts = 0;
            $status = 'processing';
            $result = null;

            while ($attempts < $maxAttempts && $status !== 'completed' && $status !== 'failed') {
                $attempts++;

                if ($attempts > 1) {
                    sleep(5);
                }

                $pollResponse = Http::withHeaders([
                    'X-API-KEY' => env('GAMMA_API_KEY'),
                ])->timeout(60)->get(env('GAMMA_BASE_URL', 'https://public-api.gamma.app/v1.0/') . 'generations/' . $generationId);

                if (!$pollResponse->successful()) {
                    Log::warning('Poll attempt failed', [
                        'attempt' => $attempts,
                        'response' => $pollResponse->body()
                    ]);
                    continue;
                }

                $result = $pollResponse->json();
                $status = $result['status'] ?? 'processing';

                Log::info('Polling generation status', [
                    'generation_id' => $generationId,
                    'status' => $status,
                    'attempt' => $attempts
                ]);
            }

            if ($status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => $status === 'failed' ? 'Generation failed' : 'Generation timeout',
                    'generation_id' => $generationId,
                    'status' => $status
                ], 408);
            }

            $url = null;
            $pdfUrl = null;

            if (isset($result['presentation'])) {
                $presentation = $result['presentation'];
                if (isset($presentation['url'])) $url = $presentation['url'];
                if (isset($presentation['shareUrl'])) $url = $presentation['shareUrl'];
                if (isset($presentation['exportUrl'])) $pdfUrl = $presentation['exportUrl'];
                if (isset($presentation['id'])) $url = "https://gamma.app/docs/" . $presentation['id'];
            }

            if (isset($result['url'])) $url = $result['url'];
            if (isset($result['pdf_url'])) $pdfUrl = $result['pdf_url'];
            if (isset($result['shareUrl'])) $url = $result['shareUrl'];
            if (isset($result['exportUrl'])) $pdfUrl = $result['exportUrl'];
            if (isset($result['presentation_url'])) $url = $result['presentation_url'];

            if (isset($result['data']) && is_array($result['data'])) {
                $nestedData = $result['data'];
                if (isset($nestedData['url'])) $url = $nestedData['url'];
                if (isset($nestedData['pdf_url'])) $pdfUrl = $nestedData['pdf_url'];
                if (isset($nestedData['shareUrl'])) $url = $nestedData['shareUrl'];
                if (isset($nestedData['exportUrl'])) $pdfUrl = $nestedData['exportUrl'];
                if (isset($nestedData['presentation_url'])) $url = $nestedData['presentation_url'];

                if (isset($nestedData['presentation'])) {
                    $presentation = $nestedData['presentation'];
                    if (isset($presentation['url'])) $url = $presentation['url'];
                    if (isset($presentation['shareUrl'])) $url = $presentation['shareUrl'];
                    if (isset($presentation['id'])) $url = "https://gamma.app/docs/" . $presentation['id'];
                }
            }

            $gammaUrl = $url ?: $pdfUrl;
            $fileUrl = $pdfUrl ?: $url;

            $content = [
                'grade_id' => $chapterData->grade_id,
                'standard_id' => $chapterData->standard_id,
                'subject_id' => $chapterData->subject_id,
                'chapter_id' => $chapterData->id,
                'topic_id' => null,
                'title' => $chapterName,
                'description' => $prompt,
                'file_folder' => '/lms_content_file',
                'filename' => $gammaUrl,
                'url' => $fileUrl,
                'file_type' => 'link',
                'file_size' => null,
                'show_hide' => '1',
                'sort_order' => null,
                'meta_tags' => null,
                'content_category' => $contentType,
                'created_by' => $user_id,
                'sub_institute_id' => $sub_institute_id,
                'restrict_date' => null,
                'pre_grade_topic' => null,
                'post_grade_topic' => null,
                'cross_curriculum_grade_topic' => null,
                'basic_advance' => '1',
                'user_profile_name' => $request->user_profile_name,
                'syear' => $syear,
            ];

            contentModel::insert($content);
            $lastId = DB::getPDO()->lastInsertId();

            return response()->json([
                'success' => true,
                'status_code' => 1,
                'message' => 'Presentation generated with Gamma and stored successfully',
                'data' => [
                    'id' => $lastId,
                    'gamma_url' => $gammaUrl,
                    'file_url' => $fileUrl,
                    'file_type' => 'link',
                    'slide_count' => $numCards,
                    'generation_id' => $generationId,
                    'status' => $status,
                    'credits_used' => $result['credits']['deducted'] ?? null,
                    'credits_remaining' => $result['credits']['remaining'] ?? null,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Gamma Content API Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    private function extractGeminiText($payload)
    {
        if (!is_array($payload)) {
            return '';
        }

        $parts = $payload['candidates'][0]['content']['parts'] ?? [];
        $text = '';

        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'] . "\n";
            }
        }

        return trim($text);
    }

    private function geminiContentErrorMessage($payload, $status)
    {
        if ($status === 429) {
            $retryAfterSeconds = $this->geminiRetryDelaySeconds($payload);
            $retryText = $retryAfterSeconds !== null
                ? ' Please retry in about ' . $retryAfterSeconds . ' seconds.'
                : ' Please retry after a short wait.';

            return 'Gemini generation quota was exceeded.' . $retryText;
        }

        if (is_array($payload)) {
            $message = $payload['error']['message'] ?? $payload['message'] ?? null;
            if ($message) {
                return 'Gemini generation failed: ' . $message;
            }
        }

        return 'Gemini generation failed with status ' . $status . '.';
    }

    private function geminiRetryDelaySeconds($payload)
    {
        if (!is_array($payload)) {
            return null;
        }

        $message = $payload['error']['message'] ?? $payload['message'] ?? '';
        if (is_string($message) && preg_match('/retry in\s+([0-9]+(?:\.[0-9]+)?)s/i', $message, $matches)) {
            return (int) ceil((float) $matches[1]);
        }

        $details = $payload['error']['details'] ?? $payload['details'] ?? [];
        if (is_array($details)) {
            foreach ($details as $detail) {
                $retryDelay = $detail['retryDelay'] ?? null;
                if (is_string($retryDelay) && preg_match('/^([0-9]+(?:\.[0-9]+)?)s$/', $retryDelay, $matches)) {
                    return (int) ceil((float) $matches[1]);
                }
            }
        }

        return null;
    }

    private function renderGeneratedContentPdf($content, $chapterName, $contentType)
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $html = $this->generatedContentHtml($content, $chapterName, $contentType);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function generatedContentHtml($content, $chapterName, $contentType)
    {
        $body = $this->formatGeneratedPdfBody($content);
        $title = e($chapterName . ' ' . $contentType);

        return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 13px; line-height: 1.55; margin: 32px; }
    h1 { color: #1e3a8a; font-size: 24px; margin: 0 0 18px; }
    h2 { color: #1e40af; font-size: 18px; margin: 22px 0 8px; }
    h3 { color: #334155; font-size: 15px; margin: 16px 0 6px; }
    p { margin: 0 0 10px; }
    ul, ol { margin: 0 0 12px 22px; padding: 0; }
    li { margin: 0 0 6px; }
    table { width: 100%; border-collapse: collapse; margin: 12px 0; }
    th, td { border: 1px solid #cbd5e1; padding: 7px 8px; vertical-align: top; }
    th { background: #eff6ff; font-weight: bold; }
    .content { white-space: normal; }
  </style>
</head>
<body>
  <h1>{$title}</h1>
  <div class="content">{$body}</div>
</body>
</html>
HTML;
    }

    private function formatGeneratedPdfBody($content)
    {
        $content = trim((string) $content);
        $content = preg_replace('/^```(?:html)?\s*|\s*```$/i', '', $content);

        if ($content !== strip_tags($content)) {
            return strip_tags($content, '<h1><h2><h3><h4><p><br><strong><b><em><i><ul><ol><li><table><thead><tbody><tr><th><td>');
        }

        return nl2br(e($content));
    }

    private function classroomActivityPrompt($chapterName, $boardName, $standardName, $subjectName)
    {
        $boardGradeStandard = trim(implode(' ', array_filter([
            trim((string) $boardName),
            trim((string) $standardName),
        ])));

        $subjectName = trim((string) $subjectName) ?: 'Subject';
        $chapterName = trim((string) $chapterName) ?: 'Chapter';
        $boardGradeStandard = $boardGradeStandard ?: 'Board/Grade/Standard';

        return <<<PROMPT
Generate a classroom activity using Inquiry-based Learning for {$chapterName} for {$boardGradeStandard} {$subjectName}.

The inquiry-based learning activity should include interactive elements such as role-playing, quizzes, puzzles, or simulations, following educational best practices.

The activity must:

Align with the chapter's learning objectives.
Focus on student engagement.
Enhance knowledge retention and application of knowledge.

Incorporate the following elements:

Clear activity instructions for the teacher.
Materials/resources required for the activity.
Step-by-step implementation instructions for the classroom.
Assessment criteria to measure learning outcomes.
PROMPT;
    }
}


