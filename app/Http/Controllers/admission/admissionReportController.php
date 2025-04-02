<?php

namespace App\Http\Controllers\admission;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use App\Models\settings\tblcustomfieldsModel;
use GenTux\Jwt\GetsJwtToken;

class admissionReportController extends Controller
{
    use GetsJwtToken;

    public function enquiryReport(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        if($type=="API"){
            $sub_institute_id=$request->sub_institute_id;
            $syear=$request->syear;
        }
        $report = $request->input('report');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $standard = $request->input('standard');
        $user = $request->input('user');
        $marking_period_id = session()->get('term_id');

        $users = DB::table('tbluser')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereIn('id', function ($query) {
                $query->select(DB::raw('distinct(created_by)'))
                    ->from('admission_registration');
            })
            ->where('status',1) // 23-04-24 by uma
            ->get();

        if (isset($report)) {

             $extra = '';
            if(in_array($sub_institute_id, [201,202,203,204,324,326,327]))
            { // for re-print fees_circular (hillshigh school)
                $extra = ",ai.id,ai.fees_circular_form_no as Form_No,ai.admission_fees,ai.fees_amount,ai.fees_remark,ai.fees_circular_html as fees_circular";
            }
            // 2024-12-28 start 
            $customFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "admission_enquiry"])
            ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1) and user_type="" ')
            ->selectRaw('GROUP_CONCAT("ai.",field_name) as fileds')
            ->first();
            $customField = '1=1';
            if(isset($customFields->fileds) && $customFields->fileds!=''){
                $customField = $customFields->fileds;
            }
            // echo "<pre>";print_r($customFields);exit;

            if(in_array($sub_institute_id,[47,48,49,62,69,72,195,201,202,203,204,324,326,327,233,254])){
              $select = "ai.enquiry_no, DATE_FORMAT(ai.created_on, '%d-%m-%Y %h:%i:%s') as created_on, DATE_FORMAT(ai.followup_date, '%d-%m-%Y') as followup_date, ai.first_name, ai.middle_name, ai.last_name,
                ai.gender, ai.mobile, ai.email, ai.address, DATE_FORMAT(ai.date_of_birth, '%d-%m-%Y') as date_of_birth, ai.age, ai.syear, ai.previous_school_name,s_previous.name as previous_standard,
                s.name as admission_standard, ai.remarks,fu.status as enquiry_status, ai.source_of_enquiry, ai.created_by,
                ai.counciler_name, ai.father_name,CONCAT_WS(' ',ts.first_name,ts.last_name) AS created_by, cs.caste_name $extra";
            }else{
                $select = "ai.enquiry_no, DATE_FORMAT(ai.created_on, '%d-%m-%Y %h:%i:%s') as created_on, DATE_FORMAT(ai.followup_date, '%d-%m-%Y') as followup_date, ai.first_name, ai.middle_name, ai.last_name,
                ai.gender, ai.mobile, ai.email, ai.address, DATE_FORMAT(ai.date_of_birth, '%d-%m-%Y') as date_of_birth, ai.age,$customField, ai.syear,CONCAT_WS(' ',ts.first_name,ts.last_name) AS created_by";
            }
            // 2024-12-28 end 
            
            $getQuery = DB::table('admission_enquiry as ai')
                ->join('tbluser as ts', function ($join) {
                    $join->whereRaw('ts.id = ai.created_by AND ts.sub_institute_id = ai.sub_institute_id')->where('ts.status',1); // 23-04-24 by uma
                })->leftJoin('caste as cs', function ($join) {
                    $join->whereRaw('cs.id = ai.category');
                })->leftJoin('follow_up as fu', function ($join) {
                    $join->whereRaw('fu.enquiry_id = ai.id AND fu.sub_institute_id = ai.sub_institute_id');
                })->join('standard as s', function ($join) use ($marking_period_id){
                    $join->whereRaw('s.id = ai.admission_standard AND s.sub_institute_id = ai.sub_institute_id');
                })
                ->LeftJoin('standard as s_previous', function ($join) {
                    $join->whereRaw('s_previous.id = ai.previous_standard AND s_previous.sub_institute_id = ai.sub_institute_id');
                })
                ->selectRaw($select) // 2024-12-28 removed query and added variable
                ->whereRaw("(DATE_FORMAT(ai.created_on, '%Y-%m-%d') BETWEEN '" . $from_date . "' AND '" . $to_date . "')
                    AND ai.sub_institute_id = '" . $sub_institute_id . "' AND ai.syear = '" . $syear . "'");

            if ($standard != '') {
                $getQuery->where('admission_standard', $standard);
            }

            if ($user) {
                $getQuery->where('ai.created_by', $user);

            }

            $data = $getQuery->get()->toArray();

            $data = array_map(function ($value) {
                return (array) $value;
            }, $data);

            if (count($data) > 0) {
                $headers = array_keys($data['0']);
                $res['headers'] = $headers;
                $res['data'] = $data;
                $res['from_date'] = $from_date;
                $res['to_date'] = $to_date;
                $res['user'] = $user;
            } else {
                $res['status_code'] = 0;
                $res['message'] = "Please revise your search. No data found.";

                return is_mobile($type, "admission_enquiry_report", $res);
            }

        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['users'] = $users;
        $res['ser_user'] = $user;
        // echo "<pre>";print_r($res);exit;
        return is_mobile($type, "admission.report.show_enquiry_report", $res, 'view');
    }

    public function formReport(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $report = $request->input('report');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $standard = $request->input('standard');
        $status = $request->input('status');
        $dynamicFields = $request->input('dynamicFields');
        $marking_period_id = session()->get('term_id');

        $formFields = DB::select("DESC admission_form");

        $formFields = array_map(function ($value) {
            return (array) $value;
        }, $formFields);

        $reportFields = array();

        $dFields[0] = "first_name";
        $dFields[1] = "middle_name";
        $dFields[2] = "last_name";
        $dFields[3] = "mobile";
        $dFields[4] = "email";

        if ($dynamicFields == '') {
            $dynamicFields = $dFields;
        } else {
            $dynamicFields = array_merge($dFields, $dynamicFields);
        }
        if(in_array($sub_institute_id, [201,202,203,204,324,326,327])){
            foreach ($formFields as $key => $value) {
                $reportFields[$value['Field']] = ucfirst(str_replace("_", " ", $value['Field']));
            }
        }
        else
        {
            $customFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "admission_form"])
            ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1) and user_type="" ')
            ->get();
            foreach ($customFields as $key => $value) {
                $reportFields[$value['field_name']] = ucfirst(str_replace("_", " ", $value['field_name']));
            }
        }

        if (isset($report)) {

            if ($dynamicFields == '') {
                $res['status_code'] = 0;
                $res['message'] = "Please select fields to view report";


                return is_mobile($type, "admission_form_report", $res);
            }


            $getQuery = DB::table('admission_form as ar')
                ->join('admission_enquiry as ae', function ($join) {
                    $join->whereRaw('ar.enquiry_id = ae.id');
                })
                ->selectRaw('ar.*, ae.admission_standard, ae.first_name, ae.middle_name, ae.last_name, ae.mobile, ae.email')
                ->whereRaw("DATE_FORMAT(ar.created_on, '%Y-%m-%d') BETWEEN '" . $from_date . "' AND '" . $to_date . "'
                    AND ae.sub_institute_id = '" . $sub_institute_id . "' AND ae.syear = '" . $syear . "'");


            if ($standard != '') {
                $getQuery->where('ae.admission_standard', $standard);
            }

            if ($status != '') {
                $getQuery->where('ar.status', $status);
            }

            $data = $getQuery->get()->toArray();

            $data = array_map(function ($value) {
                return (array) $value;
            }, $data);

            if (count($data) > 0) {

                $headers = $dynamicFields;
                $res['headers'] = $headers;  // echo "<pre>";print_r($headers);exit;
                $res['data'] = $data;
                $res['from_date'] = $from_date;
                $res['to_date'] = $to_date;
                if ($status != '') {
                    $res['status'] = $status;
                }
                if ($standard != '') {
                    $res['standard'] = $standard;
                }
            } else {
                $res['status_code'] = 0;
                $res['message'] = "Please revise your search. No data found.";


                return is_mobile($type, "admission_registration_report", $res);
            }

        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fields'] = $reportFields;
        
        return is_mobile($type, "admission.report.show_form_report", $res, 'view');
    }

    public function regReport(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $report = $request->input('report');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $standard = $request->input('standard');
        $status = $request->input('status');
        $dynamicFields = $request->input('dynamicFields');
        $marking_period_id=session()->get('term_id');
        $formFields = DB::select("DESC admission_registration");

        $formFields = array_map(function ($value) {
            return (array) $value;
        }, $formFields);

        $reportFields = array();

        $dFields[0] = "first_name";
        $dFields[1] = "middle_name";
        $dFields[2] = "last_name";
        $dFields[3] = "mobile";
        $dFields[4] = "email";

        if ($dynamicFields == '') {
            $dynamicFields = $dFields;
        } else {
            $dynamicFields = array_merge($dFields, $dynamicFields);
        }

        foreach ($formFields as $key => $value) {
            $reportFields[$value['Field']] = ucfirst(str_replace("_", " ", $value['Field']));
        }

        if (isset($report)) {
            $getQuery = DB::table('admission_form as af')
                    ->select('af.*','ar.*', 'ae.admission_standard', 'ae.first_name', 'ae.middle_name', 'ae.last_name', 'ae.mobile', 'ae.email')
                    ->join('admission_enquiry as ae', 'af.enquiry_id', '=', 'ae.id')
                    ->leftJoin('admission_registration as ar', 'ar.enquiry_id', '=', 'ae.id')
                    ->whereBetween(DB::raw('DATE_FORMAT(af.created_on, "%Y-%m-%d")'), [$from_date, $to_date])
                    ->where('ae.sub_institute_id', '=', $sub_institute_id)
                    ->where('ae.syear', '=', $syear)
                    ->whereNull('ar.id');

            if ($standard != '') {
                $getQuery = $getQuery->where('ae.admission_standard', $standard);
            }

            if ($status != '') {
                $getQuery = $getQuery->where('af.status', $status);
            }

            $data = $getQuery->get()->toArray();

            $data = array_map(function ($value) {
                return (array) $value;
            }, $data);

            if (count($data) > 0) {
                // $headers = array_keys($data['0']);
                $headers = $dynamicFields;
                $res['headers'] = $headers;
                $res['data'] = $data;
                $res['from_date'] = $from_date;
                $res['to_date'] = $to_date;
                if ($status != '') {
                    $res['status'] = $status;
                }
                if ($standard != '') {
                    $res['standard'] = $standard;
                }
            } else {
                $res['status_code'] = 0;
                $res['message'] = "Please revise your search. No data found.";

                return is_mobile($type, "admission_without_con_report", $res);
            }

        }
        $customFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "admission_enquiry"])
        ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1) and user_type="" ')
        ->get();
        $res['dataCustomFields']=$customFields;
        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fields'] = $reportFields;

        return is_mobile($type, "admission.report.show_reg_report", $res, 'view');
    }

    public function conReport(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        if($type=="API"){
            try {
                if (!$this->jwtToken()->validate()) {
                    $response = ['status' => '2', 'message' => 'Token Auth Failed', 'data' => []];
    
                    return response()->json($response, 401);
                }
            } catch (\Exception $e) {
                $response = ['status' => '2', 'message' => $e->getMessage(), 'data' => []];
    
                return response()->json($response, 401);
            }
            $sub_institute_id = $request->get('sub_institute_id');
            $syear = $request->get('syear');            
        }
        $report = $request->input('report');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $standard = $request->input('standard');
        $status = $request->input('status');
        $dynamicFields = $request->input('dynamicFields');
        $marking_period_id = session()->get('term_id');
        $formFields = DB::select("DESC admission_registration");

        $formFields = array_map(function ($value) {
            return (array) $value;
        }, $formFields);

        $reportFields = array();

        $dFields[0] = "first_name";
        $dFields[1] = "middle_name";
        $dFields[2] = "last_name";
        $dFields[3] = "mobile";
        $dFields[4] = "email";

        if ($dynamicFields == '') {
            $dynamicFields = $dFields;
        } else {
            $dynamicFields = array_merge($dFields, $dynamicFields);
        }

        foreach ($formFields as $key => $value) {
            $reportFields[$value['Field']] = ucfirst(str_replace("_", " ", $value['Field']));
        }

        if (isset($report)) {
            // 2024-12-28 start 
            $customFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "admission_registration"])
            ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1) and user_type="" ')
            ->selectRaw('GROUP_CONCAT("ar.",field_name) as fileds')
            ->first();
            $customField = '1=1';
            if(isset($customFields->fileds) && $customFields->fileds!=''){
                $customField = $customFields->fileds;
            }
            // echo "<pre>";print_r($customFields);exit;

            if(in_array($sub_institute_id,[47,48,49,62,69,72,195,201,202,203,204,324,326,327,233,254])){
                $select = "ai.enquiry_no,ai.first_name, ai.middle_name, ai.last_name, ai.gender,
                ai.mobile, ai.email,s.name AS admission_standard,d.name AS div_name,sq.title AS stu_quota,
                ar.place_of_birth,ar.enrollment_no,ar.payment_mode,ar.bank_name,ar.bank_branch,ar.cheque_no,
                ar.cheque_date,bg.bloodgroup,ar.aadhar_number,ar.mother_name,ar.mother_mobile_number,
                ar.admission_date,ar.admission_division,ar.remarks,ar.followup_date,ar.`status`,
                ar.admission_status,ar.date_of_payment,
                ai.created_on,ai.address, ai.date_of_birth, ai.age, ai.syear, ai.previous_school_name,
                ai.previous_standard,ai.source_of_enquiry,ai.father_name, CONCAT_WS(' ',ts.first_name,ts.last_name) AS created_by";
            }else{
                $select = "ai.enquiry_no,ai.first_name, ai.middle_name, ai.last_name, ai.gender,
                ai.mobile, ai.email,s.name AS admission_standard,d.name AS div_name,sq.title AS stu_quota,
                $customField, CONCAT_WS(' ',ts.first_name,ts.last_name) AS created_by";
            }
            // 2024-12-28 end 
            $getQuery = DB::table('admission_registration as ar')
                ->join('admission_enquiry as ai', function ($join) {
                    $join->whereRaw('ar.enquiry_id = ai.id');
                })->join('tbluser as ts', function ($join) {
                    $join->whereRaw('ts.id = ar.created_by AND ts.sub_institute_id = ai.sub_institute_id')->where('ts.status',1); // 23-04-24 by uma
                })->join('standard as s', function ($join) use($marking_period_id) {
                    $join->whereRaw('s.id = ai.admission_standard AND s.sub_institute_id = ai.sub_institute_id');
                    // ->when($marking_period_id,function($query) use($marking_period_id){
                    //     $query->where('s.marking_period_id',$marking_period_id);
                    // });
                })->join('std_div_map as sd', function ($join) {
                    $join->whereRaw('sd.standard_id = ai.admission_standard AND sd.sub_institute_id = ai.sub_institute_id');
                })->join('division as d', function ($join) {
                    $join->whereRaw('d.id = ar.admission_division AND d.sub_institute_id = sd.sub_institute_id');
                })->leftJoin('student_quota as sq', function ($join) {
                    $join->whereRaw('sq.id = ar.student_quota AND sq.sub_institute_id = ar.sub_institute_id');
                })->leftJoin('blood_group as bg', function ($join) {
                    $join->whereRaw('bg.id = ar.blood_group');
                })
                ->selectRaw($select)
                ->where('ai.sub_institute_id', $sub_institute_id)
                ->where('ai.syear', $syear);

            if ($from_date != '' && $to_date != '') {
                $getQuery = $getQuery->whereRaw("DATE_FORMAT(ar.created_on, '%Y-%m-%d') BETWEEN '".$from_date."' AND '".$to_date."'");
            }
            if ($standard != '') {
                $getQuery = $getQuery->where('ai.admission_standard', $standard);
            }
            if ($status != '') {
                $getQuery = $getQuery->where('ar.admission_status', $status);
            }
            $getQuery = $getQuery->groupBy('ar.id');
            $data = $getQuery->get()->toArray();

            $data = array_map(function ($value) {
                return (array) $value;
            }, $data);

            if (count($data) > 0) {

                $headers = array_keys($data['0']);
                $res['headers'] = $headers;
                $res['data'] = $data;
                $res['from_date'] = $from_date;
                $res['to_date'] = $to_date;
                if ($status != '') {
                    $res['status'] = $status;
                }
                if ($standard != '') {
                    $res['standard'] = $standard;
                }
            } else {
                $res['status_code'] = 0;
                $res['message'] = "Please revise your search. No data found.";

                return is_mobile($type, "admission_confirmation_report", $res);
            }
        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        $res['fields'] = $reportFields;
        $customFields = tblcustomfieldsModel::where(['status' => "1", 'table_name' => "admission_enquiry"])
        ->whereRaw('(sub_institute_id = '.$sub_institute_id.' OR common_to_all = 1) and user_type="" ')
        ->get();
        $res['dataCustomFields']=$customFields;
        return is_mobile($type, "admission.report.show_con_report", $res, 'view');
    }

    public function followUpReport(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $report = $request->input('report');
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $follow_up_status = $request->input('follow_up_status');
        $marking_period_id = session()->get('term_id');
        if (isset($report)) {
            $data = DB::table('admission_enquiry as ae')
                ->join('follow_up as fu', function ($join) {
                    $join->whereRaw("fu.enquiry_id = ae.id AND fu.sub_institute_id = ae.sub_institute_id AND fu.module_type = 'enquiry'");
                })->join('standard as st', function ($join) use($marking_period_id) {
                    $join->whereRaw('st.id = ae.admission_standard AND st.sub_institute_id = ae.sub_institute_id');
                    // ->when($marking_period_id,function($query) use($marking_period_id){
                    //     $query->where('st.marking_period_id',$marking_period_id);
                    // });
                })
                ->selectRaw("ae.id AS enquiry_id,ae.enquiry_no,DATE_FORMAT(ae.created_on,'%d-%m-%Y') AS enquiry_date,
						CONCAT_WS(' ',ae.first_name,ae.middle_name,ae.last_name) AS student_name,
						IFNULL(ae.middle_name,ae.father_name) AS father_name,
						ae.previous_school_name,st.name AS admission_std,ae.address,ae.mobile,ae.source_of_enquiry,
						DATE_FORMAT(fu.follow_up_date,'%d-%m-%Y') as follow_up_date,
						fu.remarks AS followup_remark,ae.email")
                ->whereRaw("fu.sub_institute_id = '" . $sub_institute_id . "' AND ae.syear = '" . $syear . "'
                        AND DATE_FORMAT(fu.created_on,'%Y-%m-%d') BETWEEN '" . $from_date . "' AND '" . $to_date . "'")
                ->groupByRaw('fu.id,fu.remarks')
                ->when($follow_up_status == 'Followed', function ($q) {
                    $q->having('fu.remarks', '!=', '');
                })->when($follow_up_status == 'Unfollowed', function ($q) {
                    $q->having('fu.remarks', '=', '');
                })->orderBy('ae.id')->get()->toArray();

            $data = array_map(function ($value) {
                return (array) $value;
            }, $data);

            if (count($data) > 0) {
                $res['data'] = $data;
                $res['from_date'] = $from_date;
                $res['to_date'] = $to_date;
            } else {
                $res['status_code'] = 0;
                $res['message'] = "Please revise your search. No data found.";

                return is_mobile($type, "admission_enquiry_followup_report", $res);
            }

        }

        $res['status_code'] = 1;
        $res['message'] = "Success";

        return is_mobile($type, "admission.report.show_enquiry_followup_report", $res, 'view');
    }
}
