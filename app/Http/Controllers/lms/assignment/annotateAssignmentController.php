<?php

namespace App\Http\Controllers\lms\assignment;

use App\Http\Controllers\Controller;
use App\Models\lms\assignment\lms_assignmentModel;
use App\Models\lms\questionpaperModel;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\lms\lmsOfflineExamModel;
use App\Models\lms\lmsOfflineExamAnswerModel;

class annotateAssignmentController extends Controller {
	/**
	 * Display a listing of the resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function index(Request $request) {
		$type = $request->input('type');
		$submit = $request->input('submit');
		$sub_institute_id = $request->session()->get('sub_institute_id');
		$res['status_code'] = 1;
		$res['message'] = "Success";
		$data = $this->getData($request);     
		$res['assignment_data'] = $data['assignment_data']; 		 
		return is_mobile($type, "lms/assignment/annotate_assignment", $res, "view");
	}

	public function getData($request){        
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');        
                
        $data['assignment_data'] = DB::table('lms_assignment as a')
        ->select('a.*','subject_name',
        	DB::raw('concat_ws(" ",ts.first_name,ts.middle_name,ts.last_name) as student_name,st.name as standard_name'))
        ->join('subject as s','s.id','a.subject_id')
        ->join('tblstudent as ts','ts.id','a.student_id')
        ->join('standard as st','st.id','a.standard_id')
        ->where(['a.sub_institute_id'=>$sub_institute_id,'a.syear'=>$syear])                                      
        ->get()->toArray();        
        
        $data['assignment_data'] = json_decode(json_encode($data['assignment_data']),true);

        return $data;
    }

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function create(Request $request) {		
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @return \Illuminate\Http\Response
	 */
	public function store(Request $request) {

		$type = $request->input('type');        
		$sub_institute_id = $request->session()->get('sub_institute_id'); 		 
		$user_id = $request->session()->get('user_id'); 		 
		$syear = $request->session()->get('syear'); 		 

		$question_paper_id = $request->get('hid_question_paper_id');
		$assignment_id = $request->get('hid_assignment_id');
		$student_id = $request->get('hid_student_id');
		$question_arr = $request->get('questions');	        

		$data['questionpaper_data'] = questionpaperModel::find($question_paper_id)->toArray();

        $questionData = DB::select("SELECT *
            FROM lms_question_master 
            WHERE id in (".$data['questionpaper_data']['question_ids'].") 
            AND sub_institute_id = '".$sub_institute_id."'            
            ");
        $questionData = json_decode(json_encode($questionData),true);        
        if( count($questionData) > 0 )
        {
	        foreach($questionData as $k => $v)
	        {	        		
	        	//IF MCQ Question is not wrong	        	
	        	if(!isset($question_arr[$v['id']]) )
	        	{
	        		$question_arr[$v['id']] = 0;
	        	}
	        }          	
	    }

	    $total_wrong = $total_right = $obtain_marks = 0;
	    foreach($question_arr as $id => $marks)
        {
        	if($marks == 0)
        	{
        		$total_wrong++;
        	}
        	else
        	{
        		$total_right++;	
        	}
        	$obtain_marks+= $marks;
        }
	    
	    //START Insert into lms_offline_exam table
        $offline_exam = array(
            'student_id' => $student_id,                            
            'question_paper_id' => $question_paper_id,
            'assignment_id' => $assignment_id,
            'total_right' => $total_right,
            'total_wrong' => $total_wrong,
            'obtain_marks' => $obtain_marks,
            'created_by' => $user_id,
            'syear' => $syear,
            'sub_institute_id' => $sub_institute_id,
        );
                     
        lmsOfflineExamModel::insert($offline_exam);        
        $offline_exam_id = DB::getPDO()->lastInsertId();
        //END Insert into lms_offline_exam table

        //START Insert into lms_offline_answer_exam table
        foreach($question_arr as $id => $marks)
        {    
        	if($marks == 0)
        	{
        		$ans_status = "wrong";
        	}
        	else{
        		$ans_status = "right";
        	}        
            $answer = array(
                'question_paper_id' => $question_paper_id,
                'offline_exam_id' => $offline_exam_id,                            
                'student_id' => $student_id,                            
                'question_id' => $id,                                                                                
                'ans_status' => $ans_status,
                'created_by' => $user_id,
            );
            lmsOfflineExamAnswerModel::insert($answer);                                                        
        }
        //END Insert into lms_offline_answer_exam table

        //START Update into lms_assignment table
         $assignment_arr = array(            
            'teacher_id' => $user_id,
            'teacher_remarks' => $request->get('teacher_remarks'),
            'teacher_submission_date' => date('Y-m-d'),
            'teacher_submission_status' => 'Y'            
        );   		         
        lms_assignmentModel::where(["id" => $assignment_id])->update($assignment_arr);
		
		$res = array(
            "status_code" => 1,
            "message" => "Assignment Reviewed Successfully",
        );
               
        return is_mobile($type, "lmsAnnotate_assignment.index", $res, "redirect");
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function show($id) {
		//
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function edit(Request $request,$id) {
		$type = $request->input('type');        
		$sub_institute_id = $request->session()->get('sub_institute_id'); 		
						
        $data['assignment_data'] = lms_assignmentModel::find($id)->toArray(); 

        $data['questionpaper_data'] = questionpaperModel::find($data['assignment_data']['exam_id'])->toArray();

        $questionData = DB::select("SELECT *
            FROM lms_question_master 
            WHERE id in (".$data['questionpaper_data']['question_ids'].") 
            AND sub_institute_id = '".$sub_institute_id."'            
            ");
        $questionData = json_decode(json_encode($questionData),true);
        $data['questionData'] = $questionData;            

        return is_mobile($type, "lms/assignment/review_assignment", $data, "view");
	}

	// public function ajax_SaveAnnotations(Request $request){
	// 	echo "aa";
	// 	die;

	// }

	/**
	 * Update the specified resource in storage.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function update(Request $request, $id) {
		//
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
	 * @return \Illuminate\Http\Response
	 */
	public function destroy($id) {
		//
	}	
}
