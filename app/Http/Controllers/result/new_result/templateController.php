<?php

namespace App\Http\Controllers\result\new_result;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\result\result_template;
use function App\Helpers\is_mobile;

class templateController extends Controller
{
    //
    public function index(Request $request)
    {   $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $data['data'] =result_template::join('tbluser',function($join){
            $join->on('tbluser.id','=','result_template_master.created_by')->where('tbluser.status',1);  // 23-04-24 by uma
        })
        ->where('result_template_master.sub_institute_id',$sub_institute_id)
        ->orderBy('result_template_master.sort_order')
        ->selectRaw('result_template_master.id as id,result_template_master.module_name,result_template_master.title,result_template_master.html_content,result_template_master.created_by,result_template_master.created_at,result_template_master.status,CONCAT(tbluser.first_name," ",tbluser.middle_name," ",tbluser.last_name) as user_created')
        ->get()->toArray();
        $data['status_code'] = 1;
        $data['message'] = "SUCCESS";
        // echo "<pre>";print_r($data['data']);exit;
        return is_mobile($type, "result/new_result/template/show", $data, "view");
    }

    public function create(Request $request)
    {   $type = $request->input('type');
        $data = '';
        return is_mobile($type, "result/new_result/template/add", $data, "view");
    }

    public function viewAllTag(Request $request)
    {
        $type = $request->input('type');
        $data = "To make the template values dynamic,please use the following table as below.
        <ul>
        <li><b><< result_left_logo >> & << result_right_logo >></b> : Institute/School Logo</li>
        <li><b><< result_line_1 >></b> : Institute/School Name</li>
        <li><b><< result_line_2 >></b> : Institute/School Address Line 1</li>
        <li><b><< result_line_3 >></b> : Institute/School Address Line 2</li>
        <li><b><< result_line_4 >></b> : Institute/School Address Line 3</li>
        <li><b><< admission_number_value >></b> : Student Admission Number</li>
        <li><b><< student_id >></b> : Student Id</li>        
        <li><b><< student_name_value >></b> : Student Name</li>
        <li><b><< student_enrollment_value >></b>: Student Enrollment Number</li>
        <li><b><< student_uniqueid_value >></b>: Student Unique id</li>        
        <li><b><< student_roll_no_value >></b>: Student Roll Number</li>        
        <li><b><< student_standard_value >></b> : Student Standard</li>
        <li><b><< short_standard_name >></b> : Standard in short form</li>   
        <li><b><< next_std >></b> : Next Standard</li>                                     
        <li><b><< student_mobile_value >></b> : Student Mobile Number</li>
        <li><b><< admin_user >></b> : Logged User</li>
        <li><b><< student_image_value >></b> : Student Image</li>
        <li><b><< student_division_value >></b> : Student division</li>
        <li><b><< student_year_value >></b> : Student year</li>
        <li><b><< student_dob_value >></b> : Student dob</li>
        <li><b><< current_date >></b> : Current date</li>
        <li><b><< student_dob_word_value >></b> : Student dob word</li>
        <li><b><< student_dise_uid_value >></b> : Student dise uid</li>
        <li><b><< school_code_value >></b> : School code</li>
        <li><b><< nationality_value >></b> : Nationality</li>
        <li><b><< place_of_birth_value >></b> : Place of birth</li>
        <li><b><< father_name_value >></b> : Father name</li>
        <li><b><< mother_name_value >></b> : Mother name</li>
        <li><b><< religion_name_value >></b> : Religion name</li>
        <li><b><< caste_name_value >></b> : Caste name</li>
        <li><b><< subcast_value >></b> : Subcast</li>
        <li><b><< he_she_value >></b> : he/she</li>
        <li><b><< his_her_value >></b> : His/Her</li>
        <li><b><< certificate_reason >></b> : certificate reason</li>
        <li><b><< student_father_name >></b> : Father name</li>
        <li><b><< scholastic_marks >></b> : Marks format 10.22</li>
        <li><b><< scholastic_marks_single_zero >></b> : Marks format 10.2</li>
        <li><b><< scholastic_marks_no_zero >></b> : Marks format 10</li>   
        <li><b><< co_scholastic_marks >></b> : co scholastic</li>
        <li><b><< total_attendance >></b> : Attandence by sytem</li>
        <li><b><< total_attendance_manual >></b> : Attandence by manual</li>  
        <li><b><< class_teacher_remark >></b> : Teachers Remark in result</li>      
        <li><b><< result >></b> : Pass or Fail</li>    
        <li><b><< teacher_sign_value >></b> : Teacher's sign</li> 
        <li><b><< principle_sign_value >></b> : Principle's sign</li>
        <li><b><< director_sign_value >></b> : directors's sign</li>
        <li><b><< director_sign_value >></b> : directors's sign</li>
        <li><b><< school_open_date >></b> : Openning date of school</li>
        <li><b><< scholastic_marks_hills >></b> : Hills scholastic area for primary</li>
        <li><b><< scholastic_marks_hills_upper >></b> : Hills scholastic area for upper primary</li>
        <li><b><< scholastic_marks_hills_11 >></b> : Hills scholastic area for standard 11</li>          
        <li><b><< co_scholastic_marks_hills >></b> : Hills Co-scholastic area</li>     
        <li><b><< co_scholastic_marks_hills_upper >></b> : Hills Co-scholastic area for upper primary</li> 
        <li><b><< other_tags_hills >></b> : other values like skill data</li>
        <li><b><< academic_years >></b> : Next Acedamic year </li>
        <li><b><< co_scholastic_marks_mmis >></b> : Co-Scholatic area for mmis </li>
        <li><b><< optional_marks_mmiss >></b> : Co-Scholatic Optinal Subject area for mmis </li>        
        <li><b><< scholastic_marks_altius >></b> : Scholatic for altius</li>     
        <li><b><< scholastic_grade_marks >></b> : Get Grade array Like 90-80 for scholastic</li>  
        <li><b><< co_scholastic_grade_marks >></b> : Get Grade array Like A1,A2 for Co-scholastic</li>    
        <li><b><< total_attendance_simple >></b> : Attendance Like 100/100</li>      
        <li><b><< total_attendance >></b> : Attendance in table format</li>  
        <li><b><< class_teacher_remark >><b> : Class teacher remark for selected term</li>  
        <li><b><< class_teacher_remark_anual >><b> : Class teacher remark for Yearly</li> 
        <li><b><< attandance_altius >><b> : Attendance for altius</li>
        <li><b><< attendance_hills >><b> : Attendance for hills</li>
        </ul>";

        return is_mobile($type, 'settings/view_all_tag', $data, "view");
    }

    public function store(Request $request){
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');

        $content = array(
            'module_name'      => $request->get('module_name'),
            'title'            => $request->get('title'),
            'html_content'     => $request->get('html_content'),
            'status'           => '1',
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'created_at'        => now(),
        );

        result_template::insert($content);

        $res = array(
            "status_code" => 1,
            "message"     => "Result Template Added Successfully",
        );
        $type = $request->input('type');

        return is_mobile($type, "result-template.index", $res, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['template_data'] = result_template::find($id)->toArray();

        return is_mobile($type, "result/new_result/template/add", $data, "view");
    }


    public function update(Request $request, $id)
    {
        // return $request;exit;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');

        $data = [
            'module_name'      => $request->get('module_name'),
            'title'            => $request->get('title'),
            'html_content'     =>$request->get('html_content'),
            'status'           => '1',
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
            'updated_at'       => now(),
        ];

        result_template::where(["id" => $id])->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Template Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "result-template.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        result_template::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Template Deleted Successfully";

        return is_mobile($type, "result-template.index", $res);
    }
}
