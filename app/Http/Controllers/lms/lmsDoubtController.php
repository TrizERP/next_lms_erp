<?php

namespace App\Http\Controllers\lms;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\lms\doubtModel;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use App\Models\school_setup\sub_std_mapModel;
use App\Models\lms\chapterModel;
use App\Models\lms\topicModel;
use GenTux\Jwt\JwtToken;
use GenTux\Jwt\GetsJwtToken;
use function App\Helpers\aut_token;

class lmsDoubtController extends Controller
{
    use GetsJwtToken;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {                
    }

    public function getData($request)
    {        
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request){         
        $type = $request->input('type');
        $action = $request->get('action');
        $sub_institute_id = $request->session()->get('sub_institute_id'); 
        $syear = $request->session()->get('syear'); 
        
        if(strtoupper(session()->get('user_profile_name')) == "STUDENT")
        {
            $student_id = session()->get('user_id');                           

            $res = DB::select("SELECT * FROM sub_std_map s WHERE s.standard_id = 
            (
                SELECT standard_id FROM tblstudent_enrollment WHERE student_id = '".$student_id."' AND 
                sub_institute_id = '".$sub_institute_id."' AND syear = '".$syear."'
            )
            and sub_institute_id = '".$sub_institute_id."'");
            $subject_arr = json_decode(json_encode($res),true);              
            $data['subject_arr'] = $subject_arr;
        }     

        $data['action'] = $action;    
        return is_mobile($type,'lms/add_doubt',$data,"view");
    }  

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {        
        $sub_institute_id = $request->session()->get('sub_institute_id');       
        $syear = $request->session()->get('syear');       
        $user_profile_id = $request->session()->get('user_profile_id');         
        $user_id = $request->session()->get('user_id');       
        
        $newfilename = "";
        if($request->hasFile('filename'))
        {           
            $img = $request->file('filename');
            $filename = $img->getClientOriginalName();
            $ext = $img->getClientOriginalExtension();
            $size = $img->getSize();
            $newfilename = 'lms_'.date('Y-m-d_h-i-s').'.'.$ext;                         
            //$img->move(public_path().'/lms_content_file/',$newfilename);
            $img->storeAs('public/lms_doubts/',$newfilename);
        }       
           
        $content = array(            
            'subject_id' => $request->get('subject'),                                   
            'chapter_id' => $request->get('chapter'),                                   
            'topic_id' => $request->get('topic'),                                   
            'title' => $request->get('title'),                                   
            'description' => $request->get('description'),                                   
            'visibility' => $request->get('visibility'),                                   
            'file_name' => $newfilename,                           
            'user_id' => $user_id,
            'user_profile_id' => $user_profile_id,
            'sub_institute_id' => $sub_institute_id,
            'syear' => $syear
        );  
        
        doubtModel::insert($content);
        
        $res = array(
            "status_code" => 1,
            "message" => "Doubts Added Successfully",
        );
        $type = $request->input('type');
        return is_mobile($type, "lmsPortfolio.index", $res, "redirect");        
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request,$id)
    {
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request,$id)
    {
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
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request,$id)
    {
    } 

    public function studentSocialCollabrativeAPI(Request $request) {
       try {
            if (!$this->jwtToken()->validate()) {
                $response = array('status' => '2', 'message' => 'Token Auth Failed', 'data' => array());
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            $response = array('status' => '2', 'message' => $e->getMessage(), 'data' => array());
            return response()->json($response, 401);
        }
                
        $student_id = $request->input("student_id");
        $type = $request->input("type");
        $sub_institute_id = $request->input("sub_institute_id");
        $syear = $request->input("syear");        

        if($student_id != "" && $sub_institute_id != "" && $syear != "")
        {         
            $doubtdata = DB::select("SELECT *
            FROM lms_doubt d            
            WHERE d.user_id = '".$student_id."' AND d.syear = '".$syear."' AND d.sub_institute_id = '".$sub_institute_id."'             
            ");
            $doubtdata = json_decode(json_encode($doubtdata),true);          
            foreach($doubtdata as $key => $val)
            {                
                $conversationData = DB::select("SELECT c.*,CONCAT_WS(' ',s.first_name,s.middle_name,s.last_name) as student_name 
                FROM lms_doubt_conversation c
                INNER JOIN tblstudent s on c.user_id = s.id and c.sub_institute_id=s.sub_institute_id
                WHERE c.sub_institute_id = '".$sub_institute_id."'  AND c.doubt_id = '".$val['id']."'                        
                ");
                $conversationData = json_decode(json_encode($conversationData),true);  

                $finaldata[$val['id']] = $val;
                $finaldata[$val['id']]['ConversationData'] = $conversationData;
            }
                      
            $res['status'] = 1;
            $res['message'] = "Success";
            $res['data'] = $finaldata;   
        }else{
            $res['status'] = 0;
            $res['message'] = "Parameter Missing";
        }
        //return  \App\Helpers\is_mobile($type, "implementation", $res);
        return json_encode($res);       
    }       
        
}
