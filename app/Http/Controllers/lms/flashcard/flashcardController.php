<?php

namespace App\Http\Controllers\lms\flashcard;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\lms\flashcardModel;
use App\Models\lms\contentModel;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\DB;

class flashcardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request){        
        $data = $this->getData($request); 		
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data['flashcard_data'];                              
        $res['breadcrum_data'] = $data['breadcrum_data']; 
                                
        return is_mobile($type,'lms/flashcard/show_flashcard',$res,"view");  
    }

    public function getData($request){        
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $data['flashcard_data'] = array();        
        
        $where_condition['lms_question_master.sub_institute_id'] = $sub_institute_id;        

        $data['flashcard_data'] = flashcardModel::select('lms_flashcard.*','s.name as standard_name','c.chapter_name','sub.subject_name')
        ->join('standard as s','s.id','lms_flashcard.standard_id')
        ->join('subject as sub','sub.id','lms_flashcard.subject_id')        
        ->join('chapter_master as c','c.id','lms_flashcard.chapter_id')        
        ->where(['lms_flashcard.sub_institute_id'=>$sub_institute_id,'lms_flashcard.syear'=>$syear,'lms_flashcard.content_id'=>$request->get('content_id')])                              
        ->get();        
       
        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id,$syear,$request->get('content_id'));

        return $data;
    }

    public function getBreadcrum($sub_institute_id,$syear,$content_id)
    {               
        $breadcrum_data = DB::select("SELECT c.*,st.name AS standard_name,su.display_name AS subject_name,
        ch.chapter_name,t.name AS topic_name,c.id as content_id
        FROM content_master c
        INNER JOIN standard st ON st.id = c.standard_id
        INNER JOIN sub_std_map su ON su.subject_id = c.subject_id AND su.standard_id = c.standard_id
        INNER JOIN chapter_master ch ON ch.id = c.chapter_id
        INNER JOIN topic_master t ON t.id = c.topic_id
        WHERE c.sub_institute_id = '".$sub_institute_id."' AND c.id = '".$content_id."' 
        ");//AND c.syear = '".$syear."'

        return $breadcrum_data[0];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');                        
        $syear = $request->session()->get('syear');                        

        $content_data = contentModel::where('id',$request->get('content_id'))->get()->toArray();
       
        $data['grade_id'] = $content_data[0]['grade_id'];                            
        $data['standard_id'] = $content_data[0]['standard_id'];                            
        $data['subject_id'] = $content_data[0]['subject_id'];                            
        $data['content_id'] = $content_data[0]['id'];                            
        $data['chapter_id'] = $content_data[0]['chapter_id'];                          
        $data['topic_id'] = $content_data[0]['topic_id'];                       

        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id,$syear,$request->get('content_id')); 

        return is_mobile($type,'lms/flashcard/add_flashcard',$data,"view");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {        
        //echo ('<pre>');print_r($_REQUEST);die;
        $sub_institute_id = $request->session()->get('sub_institute_id');               
        $syear = $request->session()->get('syear'); 		        
        $user_id = $request->session()->get('user_id');       
        $status = $request->get('status');             
        $status_val = isset($status) ? $status : '';  
        
        $flashcard_array = array(                       
            'standard_id' => $request->get('standard_id'),
            'subject_id' => $request->get('subject_id'),
            'chapter_id' => $request->get('chapter_id'),
            'topic_id' => $request->get('topic_id'),
            'content_id' => $request->get('content_id'),
            'title' => $request->get('title'),
            'front_text' => $request->get('front_text'),
            'back_text' => $request->get('back_text'),            
            'status' => $status_val,                     
            'created_by' => $user_id,
            'sub_institute_id' => $sub_institute_id,            
            'syear' => $syear            
        );
        $question_id = flashcardModel::insertGetId($flashcard_array);
                                    
            		
		$res = array(
			"status_code" => 1,
			"message" => "Flash Card Added Successfully",
		);
        $type = $request->input('type');
        //return is_mobile($type, "question_master.index", $res, "redirect");
        return redirect()->route('lms_flashcard.index', ['content_id' => $request->get('content_id')]);
    }    

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request,$id)
    {
        $type = $request->input('type');        
        $sub_institute_id = $request->session()->get('sub_institute_id');       
		$syear = $request->session()->get('syear'); 		
					
        $data['flashcard_data'] = flashcardModel::find($id)->toArray();                               

        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id,$syear,$data['flashcard_data']['content_id']);              

        return is_mobile($type, "lms/flashcard/add_flashcard", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    // public function show(Request $request)
    // {
    // }

    public function update(Request $request, $id)
    {        
        //echo ('<pre>');print_r($_REQUEST);die;
        $sub_institute_id = $request->session()->get('sub_institute_id'); 
		$syear = $request->session()->get('syear'); 		
        $user_id = $request->session()->get('user_id');
        $status = $request->get('status');             
        $status_val = isset($status) ? $status : '';  
              
        $flashcard_array = array(                       
            'standard_id' => $request->get('standard_id'),
            'subject_id' => $request->get('subject_id'),
            'chapter_id' => $request->get('chapter_id'),
            'topic_id' => $request->get('topic_id'),
            'content_id' => $request->get('content_id'),
            'title' => $request->get('title'),
            'front_text' => $request->get('front_text'),
            'back_text' => $request->get('back_text'),            
            'status' => $status_val,                     
            'created_by' => $user_id,
            'sub_institute_id' => $sub_institute_id,            
            'syear' => $syear            
        );	         

        flashcardModel::where(["id" => $id])->update($flashcard_array);
                
		$res = array(
			"status_code" => 1,
			"message" => "Flash Card Updated Successfully",
		);
        $type = $request->input('type');
        //return is_mobile($type, "question_master.index", $res, "redirect");
        return redirect()->route('lms_flashcard.index', ['content_id' => $request->get('content_id')]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,$id)
    {
        $type = $request->input('type');
        $flashcarddata = flashcardModel::where(["id" => $id])->get()->toArray();
        $content_id = $flashcarddata[0]['content_id'];        

        flashcardModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Flash Card Deleted Successfully";
        
        return redirect()->route('lms_flashcard.index', ['content_id' => $content_id]);
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
        
        $lomasterData = questionmasterModel::where(['sub_institute_id' => $sub_institute_id,'chapter_id' => $chapter_id])        
        ->get()->toArray();
        
        return $lomasterData;    
    }     
    
}
