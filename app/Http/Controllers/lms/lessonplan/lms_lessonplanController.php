<?php

namespace App\Http\Controllers\lms\lessonplan;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\subjectModel;
use App\Models\school_setup\std_div_mappingModel;
use App\Models\school_setup\timetableModel;
use function App\Helpers\is_mobile;
use Illuminate\Support\Facades\DB;
use function App\Helpers\ValidateInsertData;
use App\Models\school_setup\lessonplanningModel;
use App\Models\FormTable;
use App\Models\FormSubmitData;

class lms_lessonplanController extends Controller
{
    public function index(Request $request){  
        // dd($request->all());              
        $type = $request->input('type');

        $formData = $this->getFormData($request);

        $data = $this->getData($request);   
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";            
        $res['lessonplan_data'] = $data;            
        $res['form_data'] = $formData;            
        return is_mobile($type,'lms/lessonplan/add_lessonplan',$res,"view");  
    } 

    /**
     * FormBuilder
     * Get Form Data
     */
    public function getFormData($request) {
        // $form_id = 1;
        $user_id = $request->session()->get('user_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $standard_id = $request->standard_id;
        $subject_id = $request->subject_id;
        $chapter_id = $request->chapter_id;
        
        
        // get form submitted Data
        // DB::enableQueryLog();
        $get_form_data = FormSubmitData::where('user_id', $user_id)
            ->where('sub_institute_id', $sub_institute_id)
            ->where('standard', $standard_id)
            ->where('subject', $subject_id)
            ->where('chapter', $chapter_id)
            ->get()
            ->first();

        if ( $get_form_data ) {
            // Get Form
            $get_from_fields_json = FormTable::find($get_form_data->form_id);
            
            // echo "<pre>"; print_r($get_form_data); exit;
            $form_fields_object = json_decode($get_from_fields_json->form_json);
            if ( !empty($form_fields_object) && !empty($get_form_data) ) {
                $form_data = (array) json_decode($get_form_data->form_data);
                $fieldObject = [
                    'form_id' => $get_form_data['form_id'],
                    'chapter_id' => $chapter_id
                ];
                
                foreach ($form_fields_object as $formField) {
                    
                    if ($formField->type == 'header') {
                        $fieldObject['header'] = $formField->label;
                        // continue;
                    }
    
                    if ( $formField->type == 'date' || $formField->type == 'text' || $formField->type == 'textarea' || $formField->type == 'number' || $formField->type == 'date' ) {
                        if ( isset($form_data[$formField->name]) ) {
                            $formField->value = $form_data[$formField->name];
                            $fieldObject[$formField->label] = $form_data[$formField->name];
                        }
                    }
    
                    if ( $formField->type == 'select' ) {
    
                        if ( isset($form_data[$formField->name]) ) {
                            $formField->values = $form_data[$formField->name];
                            $fieldObject[$formField->label] = $form_data[$formField->name];
                        }
    
                        if ( $formField->label == 'Standard' ) {
                            if ( isset($form_data[$formField->name]) ) {
                                
                                $get_standard = DB::table('standard')
                                ->select('name')
                                ->where('id', $form_data[$formField->name])
                                ->where('sub_institute_id', $sub_institute_id)
                                ->first();
    
                                $formField->values = $form_data[$formField->name];
                                // dd($get_standard->name);
                                $fieldObject[$formField->label] = $get_standard->name;
                            }                        
                        } else if ( $formField->label == 'Subject' ) {
    
                            // dd($form_data[$formField->name]);
                            if ( isset($form_data[$formField->name]) ) {
                                // DB::enableQueryLog();
                                $get_subject = DB::table('subject')
                                ->select('subject_name')
                                ->where('id', $form_data[$formField->name])
                                ->where('sub_institute_id', $sub_institute_id)
                                ->first();
                                // dd(DB::getQueryLog());
    
                                // dd($get_subject);
    
                                $formField->values = $form_data[$formField->name];
                                $fieldObject[$formField->label] = $get_subject->subject_name;
                            }
                        } else if ( $formField->label == 'Chapters' ) {
    
                            // dd($form_data[$formField->name]);
                            if ( isset($form_data[$formField->name]) ) {
                                // DB::enableQueryLog();
                                $get_chapter = DB::table('chapter_master')
                                ->select('chapter_name')
                                ->where('id', $form_data[$formField->name])
                                ->where('sub_institute_id', $sub_institute_id)
                                ->first();
                                // dd(DB::getQueryLog());
    
                                // dd($get_chapter);
    
                                $formField->values = $form_data[$formField->name];
                                $fieldObject[$formField->label] = $get_chapter->chapter_name;
                            }
                        }
    
                       
                    }
                }
                return $fieldObject;
            }
        }
        return false;
    }

    public function getData($request){

        $sub_institute_id = $request->session()->get('sub_institute_id');       
        $standard_id = $request->get('standard_id');       
        $subject_id = $request->get('subject_id');       
        $title = $request->get('title');       

        $std_data = standardModel::select('*')        
        ->where(["sub_institute_id"=>$sub_institute_id,"id"=>$standard_id])                              
        ->get()->toArray();

        $sub_data = subjectModel::select('*')        
        ->where(["sub_institute_id"=>$sub_institute_id,"id"=>$subject_id])                              
        ->get()->toArray(); 

        $div_data = std_div_mappingModel::select('d.id','d.name as division_name')
        ->join('division as d','std_div_map.division_id','d.id')        
        ->where(["std_div_map.sub_institute_id"=>$sub_institute_id,"std_div_map.standard_id"=>$standard_id])                              
        ->get()->toArray();
        
        $lessonplan_data['standard_name'] = $std_data[0]['name'];        
        $lessonplan_data['standard_id'] = $standard_id;        
        $lessonplan_data['grade_id'] = $std_data[0]['grade_id'];        
        if($title != null)
        {
            $lessonplan_data['subject_name'] = $sub_data[0]['subject_name'] .' - '.$title; 
        }
        else
        {
            $lessonplan_data['subject_name'] = $sub_data[0]['subject_name']; 
        }
        
        $lessonplan_data['subject_id'] = $subject_id; 
        $lessonplan_data['division_data'] = $div_data; 

        return $lessonplan_data;
    }  

    public function store(Request $request){
       //dd($request);       
        
        $sub_institute_id = $request->session()->get('sub_institute_id'); 		
        $syear = $request->session()->get('syear'); 		
        $user_id = $request->session()->get('user_id');  
        $lecture_ids = $request->input("lecture_ids");
        $lecture_date = $request->input("lecture_date");
        $lecture_title = $request->input("lecture_title");
        $lecture_desc = $request->input("lecture_desc");
        $teachers = $request->input("teacher_id");
        $tarr = explode("####",$teachers);
        $teacher_id = $tarr[0];
        $teacher_profile_id = $tarr[1];      
        
        foreach($lecture_ids as $key => $val)
        {
            $arr = array(
            'title' => $lecture_title[$key],
            'description' => $lecture_desc[$key],
            'standard_id' => $request->get('hid_standard_id'),
            'subject_id' => $request->get('hid_subject_id'),                        
            'school_date' => $lecture_date[$key],
            'division_id' => $request->get('division'),                        
            'grade_id' => $request->get('hid_grade_id'),                      
            'user_group_id' => $teacher_profile_id,                        
            'teacher_id' => $teacher_id,                        
            'syear' => $syear,                        
            'sub_institute_id' => $sub_institute_id, 
            'created_at' => now(), 
            'updated_at' => now(), 
            'total_marks' => $request->get('total_marks'),     
            'book_link' => $request->get('book_link'),     
            );
            
            lessonplanningModel::insert($arr);    
            
        }        
        
		$res = array(
			"status_code" => 1,
			"message" => "Lesson Plan Added Successfully",
		);
        $type = $request->input('type');       
        return redirect()->route('course_master.index');
    }

    public function ajax_getTeacher(Request $request)
    {
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");

        $division_id = $request->input("division_id");        
        $standard_id = $request->input("standard_id");        
        $subject_id = $request->input("subject_id"); 

        $teacherData = DB::select("SELECT DISTINCT(teacher_id), CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) AS teacher_name,u.user_profile_id
            FROM timetable t
            INNER JOIN tbluser u ON u.id = t.teacher_id AND u.sub_institute_id = t.sub_institute_id
            WHERE t.sub_institute_id = '".$sub_institute_id."' AND t.standard_id = '".$standard_id."' 
            AND t.division_id = '".$division_id."' AND t.subject_id = '".$subject_id."' AND t.syear = '".$syear."'
            ORDER BY first_name asc
        ");
        $teacherData = json_decode(json_encode($teacherData),true);   

        return $teacherData;
    }	    
    
    public function ajax_Timetable(Request $request)
    {             
        $from_date = $request->input("from_date");        
        $to_date = $request->input("to_date");        
        $division_id = $request->input("division_id");        
        $standard_id = $request->input("standard_id");        
        $subject_id = $request->input("subject_id");        
        $teacher_id = $request->input("teacher_id");        
        $sub_institute_id = $request->session()->get("sub_institute_id");
        $syear = $request->session()->get("syear");
        
        //START Get weekday and date between from-date & to-date
        $days_arr = $this->getcountdays($from_date, $to_date);  
        //END Get weekday and date between from-date & to-date           

        //START Get Timetable data
        $timetableData = timetableModel::select('*')
        ->join('period as p','p.id','timetable.period_id')
        ->where(
            [
            'timetable.sub_institute_id' => $sub_institute_id,
            'timetable.standard_id' => $standard_id,
            'timetable.division_id' => $division_id,
            'timetable.subject_id' => $subject_id,
            'timetable.teacher_id' => $teacher_id,
            'timetable.syear' => $syear
            ]
            )        
        ->get()->toArray(); 
               
        $period = array();
        if( count($timetableData) > 0 )
        {
            foreach($timetableData as $key => $tdata)
            {
                $period[$tdata['week_day']][] = $tdata['title'];
            }
        }
        //END Get Timetable data

        //START Get Already lesson planning data
        $lessonplanData = lessonplanningModel::select('*')        
        ->where(
            [
            'sub_institute_id' => $sub_institute_id,
            'standard_id' => $standard_id,
            'division_id' => $division_id,
            'subject_id' => $subject_id,
            'teacher_id' => $teacher_id,
            'syear' => $syear
            ]
            )        
        ->groupBy('school_date','standard_id','division_id','subject_id')
        ->get()        
        ->toArray();        

        $lpData = array();
        if( count($lessonplanData) > 0 )
        {
            foreach($lessonplanData as $lkey => $lval)
            {
                $lpData[] = $lval['school_date'];
            }
        }        
        //END Get Already lesson planning data

        $from_date1=$from_date;
        $days = array('1' => 'M','2' => 'T','3' => 'W','4' => 'H','5' => 'F','6' => 'S');
        $final_timetable_data = array();
        while (strtotime($from_date1) <= strtotime($to_date)) 
        {
            $week_no = date("N", strtotime($from_date1));
            if($week_no != 7)
            {
                $week_day = $days[$week_no];
                if (array_key_exists($week_day,$period)) 
                {
                    foreach($days_arr[$week_day] as $dkey => $dval)
                    {                   
                        foreach($period[$week_day] as $wkey => $wval)
                        {       
                            if( !in_array($dval,$lpData) )//If lesson planning exist that dont add that date again
                            {
                                $final_timetable_data[$dval.'####'.$wval] = $dval.' / '.$wval;    
                            }
                        }
                    }                
                }                
            }
            $from_date1 = date("Y-m-d", strtotime("+1 day", strtotime($from_date1)));
        }
             
        return $final_timetable_data;    
    }

    public function getcountdays($from_date, $to_date)
    {       
        //5 for count Friday, 6 for Saturday , 7 for Sunday
        $days = array('M' => '1','T' => '2','W' => '3','H' => '4','F' => '5','S' => '6');
        foreach ($days as $key => $day) {
            $i = 0;
            $from_date1=$from_date;
            while (strtotime($from_date1) <= strtotime($to_date)) {
                if (date("N", strtotime($from_date1))==$day) {
                    $i++;
                    $counter[$key][] = $from_date1;
                }
                $from_date1 = date("Y-m-d", strtotime("+1 day", strtotime($from_date1)));
            }
        }        
        return $counter;
    }
        
	
}
