<?php

namespace App\Http\Controllers\lms\counselling;

use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\lms\counselling\counsellingOnlineExamModel;

class MBTIController extends Controller
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
        $res['MBTI_data'] = $data['MBTI_data'];                              
        $res['breadcrum_data'] = $data['breadcrum_data'];  
        //dd($res);                                    
        return is_mobile($type,'lms/counselling/show_MBTIPaper',$res,"view");  
    }

    public function getData($request){     
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $course_id = $request->get('course_id');

        $data['MBTI_data'] = DB::select("SELECT * FROM MBTI_paper");
        $data['MBTI_data'] = $data['MBTI_data'][0]->html;
       
        $data['breadcrum_data'] = $this->getBreadcrum($sub_institute_id,$request->get('course_id'));

        return $data;
    }

    public function getBreadcrum($sub_institute_id,$course_id)
    {
        $breadcrum_data = DB::select("SELECT title as course_title,id as course_id
        FROM counselling_course c        
        WHERE c.sub_institute_id = '".$sub_institute_id."' and c.id = '".$course_id."'");

        return $breadcrum_data[0];
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */    

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {               
        $sub_institute_id = $request->session()->get('sub_institute_id'); 		        
        $user_id = $request->session()->get('user_id');  
        $answer = $request->get('first').$request->get('second').$request->get('third').$request->get('fourth');  

        //START Insert into lms_online_exam table
        $MBTI_exam = array(
            'user_id' => $user_id,                            
            'sub_institute_id' => $sub_institute_id,
            'course_id' => $request->get('course_id'),
            'total_right' => 0,
            'total_wrong' => 0,
            'obtain_marks' => $answer,
        );                      
        counsellingOnlineExamModel::insert($MBTI_exam);         
        //END Insert into lms_online_exam table
        
        $answer_data = DB::select("SELECT * FROM MBTI_answer WHERE ans_key = '".$answer."'");

        $res['answer_data'] = $answer_data[0]->answer_html;        
        $type = $request->input('type');
        
        return is_mobile($type,'lms/counselling/show_MBTIResult',$res,"view");          
    }    

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    
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

    
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */    
    
}
