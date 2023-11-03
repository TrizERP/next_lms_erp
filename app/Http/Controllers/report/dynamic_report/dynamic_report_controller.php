<?php

namespace App\Http\Controllers\report\dynamic_report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class dynamic_report_controller extends Controller
{
    private $query;

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }


        $school_data["data"] = $this->getData();

        $type = $request->input('type');

        return is_mobile($type, 'dynamic_report/dynamic_report/show', $school_data, 'view');
    }

    public function getData($id = "")
    {
        $where_arr = [
            "sub_institute_id" => session()->get('sub_institute_id'),
        ];

        $data = array();
        if ($id != "") {
            $where_arr["id"] = $id;
            $data = DB::table('report_module_data')
                ->where($where_arr)
                ->get();
        } else {
            $or_where_arr = array(
                "privacy" => 1,
            );
            $data = DB::table('report_module_data')
                ->where($where_arr)
                ->orWhere($or_where_arr)
                ->get();
        }

        $i = 1;
        foreach ($data as $key => $arr) {
            $arr->SrNo = $i;
            $i++;
        }

        return $data;
    }

    public function get_all_dd()
    {
        $result = DB::table('report_module')->groupBy('main_module')->orderBy('id')->get()->toArray();

        $main_module = array();
        foreach ($result as $id => $arr) {
            $main_module[$arr->id] = $arr->main_module;
        }

        return [
            'main_module' => $main_module,
        ];
    }

    public function dynamicReportStep2(Request $request)
    {
        $report_name = $_REQUEST["report_name"];
        $main_module = $_REQUEST["main_module"];
        $sub_module = $_REQUEST["sub_module"];
        $description = $_REQUEST["description"];

        //private
        $privacy = $_REQUEST["privacy"];

        $sub_module[] = $main_module;

        $all_sub_module_fields = DB::table("report_module_fields")
            ->whereIn("menu_id", $sub_module)
            ->get();

        $sub_module_showing_name = array();
        $sub_module_db_fields = array();
        foreach ($all_sub_module_fields as $id => $arr) {
            $temp_showing_name = explode(",", $all_sub_module_fields[$id]->showing_name);
            $temp_db_fields = explode("|", $all_sub_module_fields[$id]->database_fields);
            $sub_module_showing_name = array_merge($sub_module_showing_name, $temp_showing_name);
            $sub_module_db_fields = array_merge($sub_module_db_fields, $temp_db_fields);
        }
        $all_fields_name = $sub_module_showing_name;
        $all_fields_index = $sub_module_db_fields;

        $send_arr = array(
            "report_name" => $_REQUEST["report_name"],
            "main_module" => $_REQUEST["main_module"],
            "sub_module"  => $_REQUEST["sub_module"],
            "description" => $_REQUEST["description"],
            "privacy"     => $privacy,
            "all_fields"  => $all_fields_name,
        );

        $type = "";

        return is_mobile($type, 'dynamic_report/dynamic_report/step2', $send_arr, 'view');

    }

    public function dynamicReportStep3(Request $request)
    {
        $group_by_arr = [];
        if (isset($_REQUEST["group_by1"]) && $_REQUEST["group_by1"] != '') {
            $group_by_arr[] = $_REQUEST["group_by1"];
        }
        if (isset($_REQUEST["group_by2"]) && $_REQUEST["group_by2"] != '') {
            $group_by_arr[] = $_REQUEST["group_by2"];
        }
        if (isset($_REQUEST["group_by3"]) && $_REQUEST["group_by3"] != '') {
            $group_by_arr[] = $_REQUEST["group_by3"];
        }

        $sort_order_arr = [];
        if (isset($_REQUEST["sort_order1"]) && $_REQUEST["sort_order1"] != '') {
            $sort_order_arr[] = $_REQUEST["sort_order1"];
        }
        if (isset($_REQUEST["sort_order2"]) && $_REQUEST["sort_order2"] != '') {
            $sort_order_arr[] = $_REQUEST["sort_order2"];
        }
        if (isset($_REQUEST["sort_order3"]) && $_REQUEST["sort_order3"] != '') {
            $sort_order_arr[] = $_REQUEST["sort_order3"];
        }

        $old_data = unserialize($_REQUEST["old_data"]);
        $old_data["selected_fields"] = explode(",", $_REQUEST["selected_fields"]);
        $old_data["group_by"] = $group_by_arr;
        $old_data["sort_order"] = $sort_order_arr;
        $old_data["old_data"] = serialize($old_data);

        $type = "";

        return is_mobile($type, 'dynamic_report/dynamic_report/step3', $old_data, 'view');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(Request $request)
    {
        $type = $request->input('type');
        $dataStore = $this->get_all_dd();

        return is_mobile($type, 'dynamic_report/dynamic_report/add', $dataStore, 'view');
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     *
     * @return Response
     */
    public function store(Request $request)
    {
        // echo '<pre>'; print_r($_REQUEST); exit;
        $old_data = unserialize($_REQUEST["old_data"]);
        unset($old_data["old_data"]);
        $old_data["condition"] = $_REQUEST["condition"];

        $data = array(
            'sub_institute_id' => session()->get('sub_institute_id'),
            'report_name'      => $old_data["report_name"],
            'description'      => $old_data["description"],
            'privacy'          => $old_data["privacy"],
        );
        $all_data = serialize($old_data);
        $data["all_data"] = $all_data;

        DB::table('report_module_data')
            ->insert($data);

        $res = array(
            'status_code' => 1,
            'message'     => 'Data Saved',
        );

        $type = $request->input('type');

        return is_mobile($type, 'dynamic_report.index', $res, 'redirect');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     *
     * @return Response
     */
    public function show($id)
    {
        //getting all data from table
        $data = $this->getData($id);
        $all_detail = unserialize($data[0]->all_data);

        //getting main module name
        $main_module = $all_detail["main_module"];
        $main_module_name = DB::table("report_module")
            ->where("id", $main_module)
            ->get();
        $main_module_name = $main_module_name[0]->main_module;


        //gettting submodule name 
        $sub_module = $all_detail["sub_module"];
        $sub_module[] = $main_module;
        $sub_module_name = DB::table("report_module")
            ->whereIn("id", $sub_module)
            ->get();

        //gettting  all_filed with their index
        $all_sub_module_fields = DB::table("report_module_fields")
            ->whereIn("menu_id", $sub_module)
            ->get();

        $sub_module_showing_name = [];
        $sub_module_db_fields = [];
        foreach ($all_sub_module_fields as $id => $arr) {
            $temp_showing_name = explode(",", $all_sub_module_fields[$id]->showing_name);
            $temp_db_fields = explode("|", $all_sub_module_fields[$id]->database_fields);
            $sub_module_showing_name = array_merge($sub_module_showing_name, $temp_showing_name);
            $sub_module_db_fields = array_merge($sub_module_db_fields, $temp_db_fields);
        }
        $all_fields_name = $sub_module_showing_name;
        $all_fields_index = $sub_module_db_fields;
        // echo "<pre>";print_r($all_fields_index);exit;

        $enrollment_join = [
            'se.student_id'       => 's.id',
            'se.sub_institute_id' => 's.sub_institute_id',
        ];
        $grade_join = [
            'acs.id'               => 'se.grade_id',
            'acs.sub_institute_id' => 'se.sub_institute_id',
        ];
        $std_join = [
            'st.id'               => 'se.standard_id',
            'st.sub_institute_id' => 'se.sub_institute_id',
        ];
        $div_join = [
            'di.id'               => 'se.section_id',
            'di.sub_institute_id' => 'se.sub_institute_id',
        ];

        // $query = new DB;
        $main_table_initial = "";
        $main_table_initial_capital = false;
        if ($main_module_name == "Homework") {
            $this->query = DB::table('homework as hm');
            $main_table_initial = "hm";
            foreach ($sub_module_name as $id => $arr) {
                if ($arr->sub_module == "Student") {

                    $tblstudent_join = [
                        'hm.student_id'       => 's.id',
                        'hm.sub_institute_id' => 's.sub_institute_id',
                    ];
                    $subject_join = [
                        'sj.id'               => 'hm.subject_id',
                        'hm.sub_institute_id' => 'sj.sub_institute_id',
                    ];
                    $this->query->join('tblstudent as s', $tblstudent_join);
                    $this->query->join('tblstudent_enrollment as se', $enrollment_join);
                    $this->query->join('academic_section as acs', $grade_join);
                    $this->query->join('standard as st', $std_join);
                    $this->query->join('division as di', $div_join);
                    $this->query->join('subject as sj', $subject_join);
                }
            }
        } else {
            if ($main_module_name == "Attendance") {
                $this->query = DB::table('attendance_student as a');
                $main_table_initial = "a";
                foreach ($sub_module_name as $id => $arr) {

                    if ($arr->sub_module == "Student") {
                        $tblstudent_join = [
                            'a.student_id'       => 's.id',
                            'a.sub_institute_id' => 's.sub_institute_id',
                        ];

                        $this->query->join('tblstudent as s', $tblstudent_join);
                        $this->query->join('tblstudent_enrollment as se', $enrollment_join);
                        $this->query->join('academic_section as acs', $grade_join);
                        $this->query->join('standard as st', $std_join);
                        $this->query->join('division as di', $div_join);
                    }
                    if ($arr->sub_module == "Teacher") {
                        $tbluser_join = [
                            'a.teacher_id'    => 'u.id',
                            'a.user_group_id' => 'u.user_profile_id',
                        ];

                        $this->query->join('tbluser as u', $tbluser_join);
                    }
                }
            } else {
                if ($main_module_name == "Assignment") {
                    $this->query = DB::table('homework as hm');
                    $main_table_initial = "hm";
                    foreach ($sub_module_name as $id => $arr) {

                        foreach ($sub_module_name as $id => $arr) {
                            if ($arr->sub_module == "Student") {

                                $tblstudent_join = [
                                    'hm.student_id'       => 's.id',
                                    'hm.sub_institute_id' => 's.sub_institute_id',
                                ];
                                $subject_join = [
                                    'sj.id'               => 'hm.subject_id',
                                    'hm.sub_institute_id' => 'sj.sub_institute_id',
                                ];
                                $this->query->join('tblstudent as s', $tblstudent_join);
                                $this->query->join('tblstudent_enrollment as se', $enrollment_join);
                                $this->query->join('academic_section as acs', $grade_join);
                                $this->query->join('standard as st', $std_join);
                                $this->query->join('division as di', $div_join);
                                $this->query->join('subject as sj', $subject_join);
                            }
                        }
                        if ($arr->sub_module == "Teacher") {
                            $tbluser_join = [
                                'hm.created_by' => 'u.id',
                            ];

                            $this->query->join('tbluser as u', $tbluser_join);
                        }
                    }
                } else {
                    if ($main_module_name == "Mobile App User") {
                        $this->query = DB::table('gcm_users as gu');
                        $main_table_initial = "gu";
                        // foreach ($sub_module_name as $id => $arr) {

                        foreach ($sub_module_name as $id => $arr) {
                            if ($arr->sub_module == "Student") {

                                $tblstudent_join = [
                                    'gu.mobile_no'        => 's.mobile',
                                    'gu.sub_institute_id' => 's.sub_institute_id',
                                ];

                                $this->query->join('tblstudent as s', $tblstudent_join);
                                $this->query->join('tblstudent_enrollment as se', $enrollment_join);
                                $this->query->join('academic_section as acs', $grade_join);
                                $this->query->join('standard as st', $std_join);
                                $this->query->join('division as di', $div_join);
                            }
                        }
                    } else {
                        if ($main_module_name == "Mobile Notification") {
                            $this->query = DB::table('app_notification as n');
                            $main_table_initial = "n";
                            $main_table_initial_capital = true;
                            // foreach ($sub_module_name as $id => $arr) {

                            foreach ($sub_module_name as $id => $arr) {
                                if ($arr->sub_module == "Student") {

                                    $tblstudent_join = [
                                        'n.student_id'       => 's.id',
                                        'n.sub_institute_id' => 's.sub_institute_id',
                                    ];

                                    $this->query->join('tblstudent as s', $tblstudent_join);
                                    $this->query->join('tblstudent_enrollment as se', $enrollment_join);
                                    $this->query->join('academic_section as acs', $grade_join);
                                    $this->query->join('standard as st', $std_join);
                                    $this->query->join('division as di', $div_join);
                                    // }
                                }
                            }
                        } else {
                            if ($main_module_name == "Sent SMS") {
                                $this->query = DB::table('sms_sent_parents as sms');
                                $main_table_initial = "sms";
                                // $main_table_initial_capital = true;

                                foreach ($sub_module_name as $id => $arr) {
                                    if ($arr->sub_module == "Student") {

                                        $tblstudent_join = [
                                            'sms.student_id'       => 's.id',
                                            'sms.sub_institute_id' => 's.sub_institute_id',
                                        ];

                                        $this->query->join('tblstudent as s', $tblstudent_join);
                                        $this->query->join('tblstudent_enrollment as se', $enrollment_join);
                                        $this->query->join('academic_section as acs', $grade_join);
                                        $this->query->join('standard as st', $std_join);
                                        $this->query->join('division as di', $div_join);
                                    }
                                }
                            }else{
                                if($main_module_name == "LMS"){

//                                     SELECT st.name AS standard, chm.chapter_name AS chapter_name, sub.subject_name, COUNT(DISTINCT cm.content_category) AS total_main_content, GROUP_CONCAT(DISTINCT cm.content_category) AS content_type, COUNT(DISTINCT cm.title) AS total_sub_content,GROUP_CONCAT(DISTINCT cm.title) AS contents
// FROM content_master cm
// LEFT JOIN chapter_master chm ON chm.id = cm.chapter_id
// LEFT JOIN subject sub ON chm.subject_id = sub.id
// INNER JOIN standard st ON st.id = cm.standard_id
// WHERE cm.sub_institute_id = 1
// GROUP BY st.name, chm.chapter_name, sub.subject_name


                    $this->query = DB::table('content_master as cm');
                    $main_table_initial = "cm";

                        foreach ($sub_module_name as $id => $arr) {
                            if ($arr->sub_module == "LMS") {
                                $sub_institute_id = session()->get('sub_institute_id');
                                                    
                                $std_join = [
                                    'cm.standard_id'       => 'st.id',
                                    'cm.sub_institute_id' => 'st.sub_institute_id',
                                ];
                                $subject_join = [
                                    'sj.id'               => 'cm.subject_id',
                                   'cm.sub_institute_id' => 'sj.sub_institute_id',
                                ];
                                $chapter_join = [
                                    'chm.id'               => 'cm.chapter_id',
                                ];
                                $this->query->join('chapter_master as chm', $chapter_join);
                                $this->query->join('subject as sj', $subject_join);
                                $this->query->join('standard as st', $std_join);

                            }
                        }
                    
                }
                else{
                    if ($main_module_name == "Bank Detail") {
                        $this->query = DB::table('tblstudent as s');
                        $main_table_initial = "s";
                        foreach ($sub_module_name as $id => $arr) {
                            if ($arr->sub_module == "Student") {
                                $tblstudent_join = [
                                    'bk.student_id'       => 's.id',
                                    'bk.sub_institute_id' => 's.sub_institute_id',
                                ];
                                $this->query->join('tblstudent_enrollment as se', $enrollment_join);
                                $this->query->join('academic_section as acs', $grade_join);
                                $this->query->join('standard as st', $std_join);
                                $this->query->join('division as di', $div_join);
                                $this->query->leftjoin('tblstudent_bank_detail as bk', $tblstudent_join);
                            }
                        }
                    }
                    else{
                        if ($main_module_name == "Transport") {
                            foreach ($sub_module_name as $id => $arr) {
                                if ($arr->sub_module == "Vehicle") {
                                    $this->query = DB::table('transport_vehicle as tv');
                                    $main_table_initial = "tv";
                                    $tbltransport_join = [
                                        'tv.driver'       => 'tdd.id',
                                        'tv.sub_institute_id' => 'tdd.sub_institute_id',
                                    ];
                                    
                                    $this->query->join('transport_driver_detail as tdd', function($join){
                                        $join->whereRaw('tv.driver = tdd.id or tv.conductor = tdd.id and tv.sub_institute_id');
                                    });
                                }
                                if ($arr->sub_module == "Route") {
                                    $this->query = DB::table('transport_map_student as tms');
                                    $main_table_initial = "tms";
                                    $tblstudent_join = [
                                        'tms.student_id'       => 's.id',
                                        'tms.sub_institute_id' => 's.sub_institute_id',
                                    ];
                                    $tblshift_join = [
                                        'tss.id' => 'tms.from_shift_id',
                                        'tss.id' => 'tms.to_shift_id',
                                    ];
                                    $tblstop_join = [
                                        'ts.id' => 'tms.from_stop',
                                        'ts.id' => 'tms.to_stop',
                                    ];
                                    $tblvehicle_join = [
                                        'tv.id' => 'tms.from_bus_id',
                                        'tv.id' => 'tms.to_bus_id',
                                    ];
                                    $this->query->join('tblstudent as s', $tblstudent_join);
                                    $this->query->join('tblstudent_enrollment as se', $enrollment_join);
                                    $this->query->join('academic_section as acs', $grade_join);
                                    $this->query->join('standard as st', $std_join);
                                    $this->query->join('division as di', $div_join);
                                    $this->query->leftjoin('transport_stop as ts', $tblstop_join);
                                    $this->query->leftjoin('transport_vehicle as tv', $tblvehicle_join);
                                    $this->query->leftjoin('transport_school_shift as tss', $tblshift_join);
                                }
                            }
                        }
                    else{
                        if ($main_module_name == "Shift Wise Van Rate") {
                            $this->query = DB::table('tblstudent as s');
                            $main_table_initial = "s";
                            foreach ($sub_module_name as $id => $arr) {
                                if ($arr->sub_module == "Student") {
                                    $tblstudent_join = [
                                        'tms.student_id'       => 's.id',
                                        'tms.sub_institute_id' => 's.sub_institute_id',
                                    ];
                                    $tblvehicle_join = [
                                        'tv.id' => 'tms.from_bus_id',
                                    ];
                                    $this->query->join('tblstudent_enrollment as se', $enrollment_join);
                                    $this->query->join('academic_section as acs', $grade_join);
                                    $this->query->join('standard as st', $std_join);
                                    $this->query->join('division as di', $div_join);
                                    $this->query->leftjoin('transport_map_student as tms', $tblstudent_join);
                                    $this->query->leftjoin('transport_vehicle as tv', $tblvehicle_join);
                                }
                            }
                        }
                    else{
                        if ($main_module_name == "Online Payment LOG") {
                            $this->query = DB::table('tblstudent as s');
                            $main_table_initial = "s";
                            foreach ($sub_module_name as $id => $arr) {
                                if ($arr->sub_module == "Student") {
                                    $tblstudent_join = [
                                        'fp.student_id'       => 'se.student_id',
                                        'fp.sub_institute_id' => 'se.sub_institute_id',
                                        //'fp.syear' => session()->get('syear'),
                                    ];
                                    $this->query->join('tblstudent_enrollment as se', $enrollment_join);
                                    $this->query->join('academic_section as acs', $grade_join);
                                    $this->query->join('standard as st', $std_join);
                                    $this->query->join('division as di', $div_join);
                                    $this->query->leftjoin('fees_payment as fp', $tblstudent_join);
                                }
                            }
                        }        
                    else {
                        if ($main_module_name == "Circular") {
                            $this->query = DB::table('circular as c');
                            $main_table_initial = "c";
                            // $main_table_initial_capital = true;

                            foreach ($sub_module_name as $id => $arr) {
                                if ($arr->sub_module == "Standard") {

                                    $standard_join = [
                                        'c.standard_id' => 'st.id',
                                    ];

                                    $this->query->join('standard as st', $standard_join);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    }
                }
            }
        }
    }
}
//echo("<pre>");print_r($all_fields_name);exit;
// |COUNT(DISTINCT cm.content_category) as total_content|GROUP_CONCAT(DISTINCT cm.content_category) as content_type|COUNT(DISTINCT cm.title) as total_sub_content|GROUP_CONCAT(DISTINCT cm.title) as sub_contents

// Standard,Chapter Name,Subject Name,Total Contents,Content Name,Total Sub Content,Sub Content Name
        $col = [];
        foreach ($all_detail["selected_fields"] as $id => $val) {
            if ($main_module_name !="Transport" && $all_fields_name[$val] == "Full Name") {
                $col[] = DB::raw("concat_ws(' ',s.first_name,s.middle_name,s.last_name) as full_name");
            }elseif($main_module_name =="Transport" && $all_fields_name[$val] == " Full Name")
            {
                if($sub_module_name[0] == "vehicle" ){
                    $col[] = DB::raw("concat_ws(' ',tdd.first_name,tdd.last_name) as full_name");
                }else{
                    $col[] = DB::raw("concat_ws(' ',s.first_name,s.middle_name,s.last_name) as full_name");
                }
            }
            elseif($all_fields_name[$val]=="Chapter Name"){
                $col[] = DB::raw("GROUP_CONCAT(DISTINCT chm.chapter_name) as chapter_name");
            }
            elseif($all_fields_name[$val]=="Content Name"){
                $col[] = DB::raw("GROUP_CONCAT(DISTINCT cm.content_category) as content_type");
            }
            elseif($all_fields_name[$val]=="Sub Content Name"){
                $col[] = DB::raw("GROUP_CONCAT(DISTINCT cm.title) as sub_contents");
            }
            elseif($all_fields_name[$val]=="Total Contents"){
                $col[] = DB::raw("COUNT(DISTINCT cm.content_category) as total_content");
            }
            elseif( $all_fields_name[$val] == "Total Sub Content"){
                $col[] = DB::raw("COUNT(DISTINCT cm.title) as total_sub_content");
            }
            else {
/* echo("<pre>");print_r($all_fields_name);echo("<br>");echo"value";
echo("<pre>");print_r($val); */


             $col[] = $all_fields_index[$val];
            }
        }
        // echo "<pre>";print_r($col);
        // exit;
        $result = "";
        $sub_institute_id = session()->get('sub_institute_id');
        foreach ($all_detail["condition"] as $must_any => $arr) {
            if ($main_table_initial_capital) {
                $this->query->whereRaw("$main_table_initial.SUB_INSTITUTE_ID = $sub_institute_id");
            } else {
                $this->query->whereRaw("$main_table_initial.sub_institute_id = $sub_institute_id");
            }
            if ($must_any == "must") {
                $count = count($all_detail["condition"]["must"]["field"]);
                for ($i = 0; $i < $count; $i++) {
                    if ($all_detail["condition"]["must"]["con"][$i] != '') {
                        $sign = "";

                        if ($all_detail["condition"]["must"]["con"][$i] == "equals") {
                            $sign = "=";
                        }
                        if ($all_detail["condition"]["must"]["con"][$i] == "not_equals") {
                            $sign = "!=";
                        }
                        if ($all_detail["condition"]["must"]["con"][$i] == "less_then") {
                            $sign = "<";
                        }
                        if ($all_detail["condition"]["must"]["con"][$i] == "grater_then") {
                            $sign = ">";
                        }
                        $val = "'".$all_detail["condition"]["must"]["val"][$i]."'";
                        $fld = $all_fields_index[$all_detail["condition"]["must"]["field"][$i]];

                        $temp_fld_arr = explode("as ", $fld);
                        $fld = $temp_fld_arr[0];

                        $this->query->whereRaw("$fld $sign $val");
                    }
                }
            } else {
                $count = count($all_detail["condition"]["any"]["field"]);
                $where = "";
                for ($i = 0; $i < $count; $i++) {
                    if ($all_detail["condition"]["any"]["con"][$i] != '') {
                        $sign = "";

                        if ($all_detail["condition"]["any"]["con"][$i] == "equals") {
                            $sign = "=";
                        }
                        if ($all_detail["condition"]["any"]["con"][$i] == "not_equals") {
                            $sign = "!=";
                        }
                        if ($all_detail["condition"]["any"]["con"][$i] == "less_then") {
                            $sign = "<";
                        }
                        if ($all_detail["condition"]["any"]["con"][$i] == "grater_then") {
                            $sign = ">";
                        }
                        $val = "'".$all_detail["condition"]["any"]["val"][$i]."'";
                        $fld = $all_fields_index[$all_detail["condition"]["any"]["field"][$i]];

                        $temp_fld_arr = explode("as ", $fld);
                        $fld = $temp_fld_arr[0];

                        if ($i == 0) {
                            $where = "(";
                        }
                        $where .= " $fld $sign $val or";
                    }
                }
                if ($where != "") {
                    $where = rtrim($where, "or");
                    $where .= ')';
                    $this->query->whereRaw($where);
                }
            }
        }
        // EP-1
        if (isset($all_detail["group_by"][0])) {
            $group_by_arr = explode(" as", $all_fields_index[$all_detail["group_by"][0]]);

    		$pattern = '/\((.*?)\)/';
            	preg_match($pattern, $group_by_arr[0], $matches);
           	 $field = str_replace(["(DISTINCT "], "", $matches[0] ?? $matches);
       	 if(isset($matches[0])){
         	   $order = substr($field, 0,-1);
        	}else{
        	    $order = $group_by_arr[0];
       	 }

            $this->query->groupBy($group_by_arr[0]);
            if (isset($all_detail["sort_order"][0])) {
                $this->query->orderBy($order, $all_detail["sort_order"][0]);
            }
        }
        // if (isset($all_detail["group_by"][1])) {
        //     // echo $all_detail["group_by"][1];exit;

        //    // $group_by_arr = explode(" as", $all_fields_index[$all_detail["group_by"][1]]);
// <<<<<<< HEAD
        // $group_by_arr = explode(" as", $all_fields_index[$all_detail["group_by"][1]]);
        //  $pattern = '/\((.*?)\)/';
        //  preg_match($pattern, $group_by_arr[0], $matches);
        //     $field = str_replace(["(DISTINCT "], "", $matches[0] ?? $matches);
        //       if(isset($matches[0])){
        //             $order = substr($field, 0,-1);
        //     }else{
        //          $order = $group_by_arr[0];
        //       }
// =======
		// $group_by_arr = explode(" as", $all_fields_index[$all_detail["group_by"][1]]);
        // 	$pattern = '/\((.*?)\)/';
        // 	preg_match($pattern, $group_by_arr[0], $matches);
        //     $field = str_replace(["(DISTINCT "], "", $matches[0] ?? $matches);
        //    	 if(isset($matches[0])){
        //      	   $order = substr($field, 0,-1);
        //     }else{
        //     	    $order = $group_by_arr[0];
        //    	 }
// >>>>>>> 5711c69549bbcd152bf4c8020191d51575f0ee7c
        //     $this->query->groupBy($group_by_arr[0]);
        //     if (isset($all_detail["sort_order"][1])) {
        //         $this->query->orderBy($order, $all_detail["sort_order"][1]);
        //     }
        // }
// EP-2

 if (isset($all_detail["group_by"]) && isset($all_detail["group_by"][1])) {
    // $group_by_arr = explode(" as", $all_fields_index[$all_detail["group_by"][2]]);
        $group_by_arr = explode(" as", $all_fields_index[$all_detail["group_by"][1]]);
        $pattern = '/\((.*?)\)/';
        preg_match($pattern, $group_by_arr[0], $matches);
        $field = str_replace(["(DISTINCT "], "", $matches[0] ?? $matches);
        if(isset($matches[0])){
            $order = substr($field, 0,-1);
        }else{
            $order = $group_by_arr[0];
        }

        $this->query->groupBy($order);
        if (isset($all_detail["sort_order"]) && isset($all_detail["sort_order"][2])) {
            $this->query->orderBy($order, $all_detail["sort_order"][2]);
        }
    }        // EP-3
        if (isset($all_detail["group_by"]) && isset($all_detail["group_by"][2])) {
    // $group_by_arr = explode(" as", $all_fields_index[$all_detail["group_by"][2]]);
        $group_by_arr = explode(" as", $all_fields_index[$all_detail["group_by"][2]]);
        $pattern = '/\((.*?)\)/';
        preg_match($pattern, $group_by_arr[0], $matches);
        $field = str_replace(["(DISTINCT "], "", $matches[0] ?? $matches);
        if(isset($matches[0])){
            $order = substr($field, 0,-1);
        }else{
            $order = $group_by_arr[0];
        }

        $this->query->groupBy($order);
        if (isset($all_detail["sort_order"]) && isset($all_detail["sort_order"][2])) {
            $this->query->orderBy($order, $all_detail["sort_order"][2]);
        }
    }


        // echo "<pre>";print_r($col);exit;
        // $this->query->select($col);
        // if(isset($counts) && $counts == "counts"){
        //     $this->query->selectRaw(implode(', ', $col));
        // }else{
        $this->query->select($col);
        // }
        $result = $this->query->get();
        $tbl_detail = [];
        foreach ($all_detail["selected_fields"] as $id => $val) {

            $field_arr = explode("as ", $all_fields_index[$val]);

            if (isset($field_arr[1])) {
                $tbl_detail[] = $field_arr[1];
            } else {
                $temp_val = $field_arr[0];
                $field_arr = explode(".", $temp_val);
                $tbl_detail[] = $field_arr[1];
            }
        }
        $tbl_heading = [];
        foreach ($all_detail["selected_fields"] as $id => $val) {
            $tbl_heading[] = $all_fields_name[$val];
        }

        $result_data = array();
        $result_data["tbl_heading"] = $tbl_heading;
        $result_data["tbl_detail"] = $tbl_detail;
        $result_data["result"] = $result;
        $type = "";

        return is_mobile($type, 'dynamic_report/dynamic_report/show_report', $result_data, 'view');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return Response
     */
    public function edit(Request $request, $id)
    {
        $all_dd = $this->get_all_dd();

        $allData = DB::table('LEARNING_OUTCOME_INDICATOR')->where('ID', $id)->get()->toArray();

        $standard = $allData[0]->STANDARD;
        $medium = $allData[0]->MEDIUM;

        $where = [
            'LEARNING_OUTCOME_PDF.standard' => $standard,
            'LEARNING_OUTCOME_PDF.medium'   => $medium,
        ];

        $std_sub_map = DB::table('LEARNING_OUTCOME_PDF')
            ->where($where)
            ->pluck('LEARNING_OUTCOME_PDF.DISPLAY_SUBJECT', 'LEARNING_OUTCOME_PDF.SUBJECTS');

        $data = [
            'medium'           => $all_dd['medium'],
            'std'              => $all_dd['std'],
            'selected_medium'  => $allData[0]->MEDIUM,
            'selected_std'     => $allData[0]->STANDARD,
            'selected_subject' => $allData[0]->SUBJECT,
            'learning_outcome' => $allData[0]->INDICATOR,
            'subject'          => $std_sub_map,
            'id'               => $id,

        ];

        $type = $request->input('type');

        return is_mobile($type, "learning_outcome/indicator_mapping/edit", $data, "view");
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     *
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $data = [
            'MEDIUM'     => $request->get('medium'),
            'STANDARD'   => $request->get('std'),
            'SUBJECT'    => $request->get('subject'),
            'INDICATOR'  => $request->get('learning_outcome'),
            'UPDATED_AT' => now(),
            'UPDATED_BY' => $request->session()->get('user_id'),
        ];

        DB::table('LEARNING_OUTCOME_INDICATOR')
            ->where(["ID" => $id])
            ->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];
        $type = $request->input('type');

        return is_mobile($type, "indicator_mapping.index", $res, "redirect");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return Response
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        DB::table('report_module_data')
            ->where(["id" => $id])
            ->delete();

        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "dynamic_report.index", $res, "redirect");
    }
}
