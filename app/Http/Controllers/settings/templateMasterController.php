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
            ->join('tbluser as u', 'u.id', '=', 'template_master.created_by')
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
        <li><b><< mr_miss >></b> : Mr./Miss.</li>
        <li><b><< daughter_or_son >></b> : daughter/son</li>        
        <li><b><< certificate_reason >></b> : certificate reason</li>
        <li><b><< student_father_name >></b> : Father name</li>
        <li><b><< fees_details >></b> : Fees Details</li>
        <li><b><< total_amount_in_words >></b> : Total Amount in words</li>
        </ul>";

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
