<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\Controller;
use App\Models\settings\templateMasterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class templateMasterController extends Controller
{

    public function index(Request $request)
    {
        $type = $request->input('type');
        $data = $this->getData($request);
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data['template_data'];

        return is_mobile($type, 'settings/show_templates', $res, "view");
    }

    public function getData($request)
    {

        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['template_data'] = templateMasterModel::select('template_master.*',
            DB::raw('concat_ws(" ",u.first_name,u.middle_name,u.last_name) as created_by'))
            ->join('tbluser as u', function($join){
                $join->on('u.id', '=', 'template_master.created_by')->where('u.status',1);   // 23-04-24 by uma
            })
            ->where(['template_master.sub_institute_id' => $sub_institute_id])
            ->get()->toArray();

        return $data;
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $data = [];

        return is_mobile($type, 'settings/add_templates', $data, "view");
    }

    public function viewAllTag(Request $request)
    {
        $type = $request->input('type');
        // $data = "
        // <ul>
        // <li><b><< receipt_logo >></b> : Institute/School Logo</li>
        // <li><b><< receipt_line_1 >></b> : Institute/School Name</li>
        // <li><b><< receipt_line_2 >></b> : Institute/School Address Line 1</li>
        // <li><b><< receipt_line_3 >></b> : Institute/School Address Line 2</li>
        // <li><b><< receipt_line_4 >></b> : Institute/School Address Line 3</li>
        // <li><b><< admission_number_value >></b> : Student Admission Number</li>
        // <li><b><< receipt_year_value >></b> : Educational Year /  Session</li>
        // <li><b><< receipt_number_value >></b> : Receipt Number</li>
        // <li><b><< receipt_date_value >></b> : Receipt Date</li>
        // <li><b><< student_name_value >></b> : Student Name</li>
        // <li><b><< student_enrollment_value >></b>: Student Enrollment Number</li>
        // <li><b><< student_standard_value >></b> : Student Standard</li>
        // <li><b><< student_mobile_value >></b> : Student Mobile Number</li>
        // <li><b><< fees_head_content >></b> : Fees Head-wise Content with amount and head type</li>
        // <li><b><< total_amount_in_words >></b> : Total Amount in words</li>
        // <li><b><< payment_mode >></b> : Payment Mode with cash or cheque details</li>
        // <li><b><< admin_user >></b> : Logged User</li>
        // <li><b><< student_image_value >></b> : Student Image</li>
        // <li><b><< student_division_value >></b> : Student division</li>
        // <li><b><< student_year_value >></b> : Student year</li>
        // <li><b><< student_dob_value >></b> : Student dob</li>
        // <li><b><< current_date >></b> : Current date</li>
        // <li><b><< student_dob_word_value >></b> : Student dob word</li>
        // <li><b><< student_dise_uid_value >></b> : Student dise uid</li>
        // <li><b><< certificate_no >></b> : Certificate no</li>
        // <li><b><< affiliation_no_value >></b> : Affiliation no</li>
        // <li><b><< school_code_value >></b> : School code</li>
        // <li><b><< nationality_value >></b> : Nationality</li>
        // <li><b><< place_of_birth_value >></b> : Place of birth</li>
        // <li><b><< father_name_value >></b> : Father name</li>
        // <li><b><< mother_name_value >></b> : Mother name</li>
        // <li><b><< religion_name_value >></b> : Religion name</li>
        // <li><b><< caste_name_value >></b> : Caste name</li>
        // <li><b><< subcast_value >></b> : Subcast</li>
        // <li><b><< candidate_belongs_to_value >></b> : Candidate belongs to</li>
        // <li><b><< date_of_first_admission_value >></b> : Date of first admission</li>
        // <li><b><< class_in_which_pupil_last_studied_value >></b> : Class in which pupil last studied</li>
        // <li><b><< last_school_board_value >></b> : Last school board</li>
        // <li><b><< whether_failed_value >></b> : Whether failed</li>
        // <li><b><< subjects_studied_value >></b> : Subjects studied</li>
        // <li><b><< whether_qualified_value >></b> : Whether qualified</li>
        // <li><b><< if_to_which_class_value >></b> : If to which class</li>
        // <li><b><< month_up_paid_school_dues_value >></b> : Month up paid school dues</li>
        // <li><b><< admission_under_value >></b> : Admission under</li>
        // <li><b><< total_working_days_value >></b> : Total working days</li>
        // <li><b><< total_working_days_present_value >></b> : Total working days present</li>
        // <li><b><< games_played_value >></b> : Games played</li>
        // <li><b><< general_conduct_value >></b> : General conduct</li>
        // <li><b><< date_of_application_for_certificate_value >></b> : Date of application for certificate</li>
        // <li><b><< date_of_issue_of_certificate_value >></b> : Date of issue of certificate</li>
        // <li><b><< reason_leaving_school_value >></b> : Reason leaving school</li>
        // <li><b><< proof_for_dob_value >></b> : Proof for dob</li>
        // <li><b><< whether_school_is_under_goverment_value >></b> : Whether school is under goverment</li>
        // <li><b><< date_on_which_pupil_name_was_struck_value >></b> : Date on which pupil name was struck</li>
        // <li><b><< any_fees_concession_value >></b> : Any fees concession</li>
        // <li><b><< whether_ncc_cadet_value >></b> : Whether ncc cadet</li>
        // <li><b><< any_other_remarks_value >></b> : Any other remarks</li>
        // <li><b><< student_uniqueid_value >></b> : Student unique ID</li>
        // <li><b><< he_she_value >></b> : he/she</li>
        // <li><b><< his_her_value >></b> : His/Her</li>
        // <li><b><< mr_miss >></b> : Mr./Miss.</li>
        // <li><b><< daughter_or_son >></b> : daughter/son</li>        
        // <li><b><< certificate_reason >></b> : certificate reason</li>
        // <li><b><< student_father_name >></b> : Father name</li>
        // <li><b><< fees_details >></b> : Fees Details</li>
        // <li><b><< total_amount_in_words >></b> : Total Amount in words</li>
        // <li><b><< student_last_name_value >></b> : Last Name</li>
        // <li><b><< admission_date_value >></b> : Admission</li>
        // <li><b><< short_standard_name_value >></b> : Last Standard Name</li>
        // <li><b><< short_standard_name_in_word_value >></b> : Last Standard Name in Word</li>
        // <li><b><< teacher_remark_value >></b> : Teacher Remark</li>
        // <li><b><< month_name >></b> : Month Name</li>
        // <li><b><< date_on_which_pupil_name_value >></b> : Date of application for certificate</li>
        // <li><b><< date_of_application_for_certificate_value >></b> : Date on which pupil's name was struck off the rolls of the school</li>
        // <li><b><< date_of_issue_of_certificate_new_value >></b> : Date of issue of certificate</li>
        // <li><b><< activity_tag_marks >></b> : Student Activity Report Marks</li>

        // <li><b><< bank_name >></b> : Bank Name in fees receipt</li>
        // <li><b><< cheque_no >></b> : Cheque Number in fees receipt</li>
        // <li><b><< cheque_date >></b> : Cheque Date in fees receipt</li>
        // <li><b><< bank_branch >></b> : Bank Branch in fees receipt</li>
        // <li><b><< payment_mode_type >></b> : Payment Mode in fees receipt</li>
        // <li><b><< parent_pan_card >></b> : Parent Pan Card No in fees receipt</li>

        // </ul>";
        $data = [
            'receipt_logo' => 'Institute/School Logo from fees receipt book master',
            'receipt_line_1' => 'Institute/School Name from fees receipt book master',
            'receipt_line_2' => 'Institute/School Address Line 1 from fees receipt book master',
            'receipt_line_3' => 'Institute/School Address Line 2 from fees receipt book master',
            'receipt_line_4' => 'Institute/School Address Line 3 from fees receipt book master',
            'admission_number_value' => 'Student Admission Number from student profile',
            // 'receipt_year_value' => 'Educational Year / Session',
            // 'receipt_number_value' => 'Receipt Number',
            // 'receipt_date_value' => 'Receipt Date',
            'student_enrollment_value' => 'Student Enrollment Number / GR No.', 
            'student_roll_no_value' => 'Student Roll No.',// added on 05-05-2025
            'student_name_value' => 'Student Name first and last name',
            'student_full_name' => 'Student Name first, middle and last name', // added on 05-05-2025
            'student_first_name_value' => 'Student Name first name', // added on 05-05-2025
            'student_middle_name_value' => 'Student Name middle name', // added on 05-05-2025
            'student_last_name_value' => 'Student Name last name', // added on 05-05-2025
            'student_image_value' => 'Student Image from student Profile',
            'student_division_value' => 'Student division',
            'student_year_value' => 'Student year like 2025-2026',
            'student_dob_value' => 'Student dob from student profile',
            'father_name_value' => 'Father name',
            'mother_name_value' => 'Mother name',
            'aadhar_number' => 'Student Aadhar Number from student profile', // added on 05-05-2025
            'student_gender' => 'Student Gender from student profile', // added on 05-05-2025
            'admission_date_value' => 'Admission Date',
            'short_standard_name_value' => 'Current Standard Name in Short Form',
            'student_standard_value' => 'Student Current Standard',
            'short_standard_name_in_word_value' => 'School stream value from standard',
            'next_std_name'=>'Next Standard as per selected year from standard',// added on 05-05-2025
            'next_std_stream'=>'Next Standard stream as per selected year from standard',// added on 05-05-2025
            'next_std_short_name'=>'Next Standard short name as per selected year from standard',// added on 05-05-2025
            'current_medium'=>'current medium as per selected year from standard',// added on 05-05-2025
            'next_medium'=>'Next medium as per selected year from standard',// added on 05-05-2025
            'student_mobile_value' => 'Student Mobile Number',
            'fees_head_content' => 'Fees Head-wise Content with amount and head type',
            'total_amount_in_words' => 'Total Amount in words',
            'payment_mode' => 'Payment Mode with cash or cheque details',
            'admin_user' => 'Current Logged User',
            'current_date' => 'Current date in 26-Apr-2025 format',
            'current_date_dmy' => 'Current date in 26-04-2025 format',
            'student_dob_word_value' => 'Student dob word from student profile',
            'student_dise_uid_value' => 'Student dise uid from student profile',
            'student_uniqueid_value' => 'Student unique ID',
            'certificate_no' => 'Certificate no',
            'affiliation_no_value' => 'Affiliation no',
            'school_code_value' => 'School code',
            'nationality_value' => 'Nationality',
            'place_of_birth_value' => 'Place of birth',
            'religion_name_value' => 'Religion name',
            'caste_name_value' => 'Caste name',
            'subcast_value' => 'Subcast',
            'candidate_belongs_to_value' => 'Candidate belongs to, from TC information student profile',
            'date_of_first_admission_value' => 'Date of first admission, from TC information student profile',
            'class_in_which_pupil_last_studied_value' => 'Class in which pupil last studied, from TC information student profile',
            'last_school_board_value' => 'Last school board, from TC information student profile',
            'whether_failed_value' => 'Whether failed, from TC information student profile',
            'subjects_studied_value' => 'Subjects studied, from TC information student profile',
            'whether_qualified_value' => 'Whether qualified, from TC information student profile',
            'if_to_which_class_value' => 'If to which class, from TC information student profile',
            'month_up_paid_school_dues_value' => 'Month up paid school dues, from TC information student profile',
            'admission_under_value' => 'Admission under, from TC information student profile',
            'total_working_days_value' => 'Total working days, from TC information student profile',
            'total_working_days_present_value' => 'Total working days present, from TC information student profile',
            'games_played_value' => 'Games played, from TC information student profile',
            'general_conduct_value' => 'General conduct, from TC information student profile',
            'date_of_application_for_certificate_value' => 'Date of application for certificate, from TC information student profile',
            'date_of_issue_of_certificate_value' => 'Date of issue of certificate, from TC information student profile',
            'reason_leaving_school_value' => 'Reason leaving school, from TC information student profile',
            'proof_for_dob_value' => 'Proof for dob, from TC information student profile',
            'whether_school_is_under_goverment_value' => 'Whether school is under government, from TC information student profile',
            'date_on_which_pupil_name_was_struck_value' => 'Date on which pupil name was struck, from TC information student profile',
            'any_fees_concession_value' => 'Any fees concession, from TC information student profile',
            'whether_ncc_cadet_value' => 'Whether ncc cadet, from TC information student profile',
            'any_other_remarks_value' => 'Any other remarks, from TC information student profile',
            'date_on_which_pupil_name_value' => 'Date of application for certificate, from TC information student profile',
            'date_of_issue_of_certificate_new_value' => 'Date of issue of certificate, from TC information student profile',
            'date_application_for_certificate_value' => 'Date of application of certificate, from TC information student profile',
            'HE_SHE' => 'he/she in Upper case',
            'he_she' => 'he/she in lower case',
            'his_her' => 'His/Her in lower case',
            'HIS_HER' => 'His/Her in Upper case',
            'mr_miss' => 'Mr./Miss.',
            'daughter_or_son' => 'daughter/son',
            'certificate_reason' => 'certificate reason',
            'fees_details' => 'Fees Details',
            'teacher_remark_value' => 'Teacher Remark from result_student_attendance_master',
            'month_name' => 'Fees Paid Month Name',
            'activity_tag_marks' => 'Student Activity Report Marks',
            'bank_name' => 'Bank Name in fees receipt',
            'cheque_no' => 'Cheque Number in fees receipt',
            'cheque_date' => 'Cheque Date in fees receipt',
            'bank_branch' => 'Bank Branch in fees receipt',
            'payment_mode_type' => 'Payment Mode in fees receipt',
            'parent_pan_card' => 'Parent Pan Card No in fees receipt',
            'subjects_studied_system'=>'Main and Optional subject as per student selected', // added on 05-05-2025
            'subjects_studied_2_line'=>'Main and Optional subject as per student selected break between 2 lines', // added on 05-05-2025
            'annual_value_result'=>'Remarks from remarks master', // added on 05-05-2025
            'total_working_days_system'=>'Count days from start date and end date academic year',// added on 05-05-2025
            'total_working_days_present_system'=>'Count days as per attendance taken',// added on 05-05-2025
            'student_dise_uid_plus'=>'udise + number of student', // added on 11-07-2025 by uma for hill TC
        ];

        return is_mobile($type, 'settings/view_all_tag', $data, "view");
    }

    public function store(Request $request)
    {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');

        $content = array(
            'module_name'      => $request->get('module_name'),
            'title'            => $request->get('title'),
            'html_content'     => $request->get('html_content'),
            'status'           => '1',
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
        );

        templateMasterModel::insert($content);

        $res = array(
            "status_code" => 1,
            "message"     => "Template Added Successfully",
        );
        $type = $request->input('type');

        return is_mobile($type, "templatemaster.index", $res, "redirect");
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $data['template_data'] = templateMasterModel::find($id)->toArray();

        return is_mobile($type, "settings/add_templates", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $user_id = $request->session()->get('user_id');

        $data = [
            'module_name'      => $request->get('module_name'),
            'title'            => $request->get('title'),
            'html_content'     => $request->get('html_content'),
            'status'           => '1',
            'created_by'       => $user_id,
            'sub_institute_id' => $sub_institute_id,
        ];

        templateMasterModel::where(["id" => $id])->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Template Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "templatemaster.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        templateMasterModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Template Deleted Successfully";

        return is_mobile($type, "templatemaster.index", $res);
    }

}
