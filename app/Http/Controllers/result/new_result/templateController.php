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
        $data['data'] =result_template::join('tbluser','tbluser.id','=','result_template_master.created_by')->where('result_template_master.sub_institute_id',$sub_institute_id)->orderBy('result_template_master.sort_order')->selectRaw('result_template_master.id as id,result_template_master.module_name,result_template_master.title,result_template_master.html_content,result_template_master.created_by,result_template_master.created_at,result_template_master.status,CONCAT(tbluser.first_name," ",tbluser.middle_name," ",tbluser.last_name) as user_created')->get()->toArray();
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
        <li><b><< receipt_logo >></b> : Institute/School Logo</li>
        <li><b><< receipt_line_1 >></b> : Institute/School Name</li>
        <li><b><< receipt_line_2 >></b> : Institute/School Address Line 1</li>
        <li><b><< receipt_line_3 >></b> : Institute/School Address Line 2</li>
        <li><b><< receipt_line_4 >></b> : Institute/School Address Line 3</li>
        <li><b><< admission_number_value >></b> : Student Admission Number</li>
        <li><b><< receipt_year_value >></b> : Educational Year /  Session</li>
        <li><b><< receipt_number_value >></b> : Receipt Number</li>
        <li><b><< receipt_date_value >></b> : Receipt Date</li>
        <li><b><< student_name_value >></b> : Student Name</li>
        <li><b><< student_enrollment_value >></b>: Student Enrollment Number</li>
        <li><b><< student_standard_value >></b> : Student Standard</li>
        <li><b><< student_mobile_value >></b> : Student Mobile Number</li>
        <li><b><< fees_head_content >></b> : Fees Head-wise Content with amount and head type</li>
        <li><b><< total_amount_in_words >></b> : Total Amount in words</li>
        <li><b><< payment_mode >></b> : Payment Mode</li>
        <li><b><< admin_user >></b> : Logged User</li>
        <li><b><< student_image_value >></b> : Student Image</li>
        <li><b><< student_division_value >></b> : Student division</li>
        <li><b><< student_year_value >></b> : Student year</li>
        <li><b><< student_dob_value >></b> : Student dob</li>
        <li><b><< current_date >></b> : Current date</li>
        <li><b><< student_dob_word_value >></b> : Student dob word</li>
        <li><b><< student_dise_uid_value >></b> : Student dise uid</li>
        <li><b><< certificate_no >></b> : Certificate no</li>
        <li><b><< affiliation_no_value >></b> : Affiliation no</li>
        <li><b><< school_code_value >></b> : School code</li>
        <li><b><< nationality_value >></b> : Nationality</li>
        <li><b><< place_of_birth_value >></b> : Place of birth</li>
        <li><b><< father_name_value >></b> : Father name</li>
        <li><b><< mother_name_value >></b> : Mother name</li>
        <li><b><< religion_name_value >></b> : Religion name</li>
        <li><b><< caste_name_value >></b> : Caste name</li>
        <li><b><< subcast_value >></b> : Subcast</li>
        <li><b><< candidate_belongs_to_value >></b> : Candidate belongs to</li>
        <li><b><< date_of_first_admission_value >></b> : Date of first admission</li>
        <li><b><< class_in_which_pupil_last_studied_value >></b> : Class in which pupil last studied</li>
        <li><b><< last_school_board_value >></b> : Last school board</li>
        <li><b><< whether_failed_value >></b> : Whether failed</li>
        <li><b><< subjects_studied_value >></b> : Subjects studied</li>
        <li><b><< whether_qualified_value >></b> : Whether qualified</li>
        <li><b><< if_to_which_class_value >></b> : If to which class</li>
        <li><b><< month_up_paid_school_dues_value >></b> : Month up paid school dues</li>
        <li><b><< admission_under_value >></b> : Admission under</li>
        <li><b><< total_working_days_value >></b> : Total working days</li>
        <li><b><< total_working_days_present_value >></b> : Total working days present</li>
        <li><b><< games_played_value >></b> : Games played</li>
        <li><b><< general_conduct_value >></b> : General conduct</li>
        <li><b><< date_of_application_for_certificate_value >></b> : Date of application for certificate</li>
        <li><b><< date_of_issue_of_certificate_value >></b> : Date of issue of certificate</li>
        <li><b><< reason_leaving_school_value >></b> : Reason leaving school</li>
        <li><b><< proof_for_dob_value >></b> : Proof for dob</li>
        <li><b><< whether_school_is_under_goverment_value >></b> : Whether school is under goverment</li>
        <li><b><< date_on_which_pupil_name_was_struck_value >></b> : Date on which pupil name was struck</li>
        <li><b><< any_fees_concession_value >></b> : Any fees concession</li>
        <li><b><< whether_ncc_cadet_value >></b> : Whether ncc cadet</li>
        <li><b><< any_other_remarks_value >></b> : Any other remarks</li>
        <li><b><< student_uniqueid_value >></b> : Student unique ID</li>
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
        <li><b><< class_teacher_remark >></b> : Teachers Remark</li>      
        <li><b><< result >></b> : Pass or Fail</li>    
        <li><b><< teacher_sign_value >></b> : Teacher's sign</li> 
        <li><b><< principle_sign_value >></b> : Principle's sign</li>                                                                                                                                                                                                                              
        <li><b><< director_sign_value >></b> : directors's sign</li>                                                                                                                                                                                                                                                                                                                                                                                                                                                                   
                
        <li><b><< school_open_date >></b> : Openning date of school</li>
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
