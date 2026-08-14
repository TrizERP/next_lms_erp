<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\batchModel;
use App\Models\school_setup\divisionModel;
use App\Models\school_setup\periodModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\std_div_mappingModel;
use App\Models\school_setup\sub_std_mapModel;
use App\Models\school_setup\timetableModel;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class timetableController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";

        if($request->has('standard_id') || $request->has('division_id') ){
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
            $grade_id = $request->grade_id;
            $division_id = $request->division_id;
            $standard_id = $request->standard_id;
            
            $res = $this->getTimetable_data($request, $grade_id, $standard_id, $division_id, $sub_institute_id,
            $syear);  
        }
       
        $res = array_merge($res, $data);

        return is_mobile($type, 'school_setup/show_timetable', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $academic_section_data = academic_sectionModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $data['academic_section_data'] = $academic_section_data;

        return $data;
    }

    public function AcademicwiseStandard(Request $request)
    {
        $academic_id = $request->input("academic_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return standardModel::where(['sub_institute_id' => $sub_institute_id, 'grade_id' => $academic_id])
        // ->when($marking_period_id,function($query) use ($marking_period_id){
        //     $query->where('marking_period_id',$marking_period_id);
        // })
            ->get()->toArray();
    }

    public function getTimetable(Request $request)
    {
        $academic_section_id = $request->input("academic_section_id");
        $standard_id = $request->input("standard_id");
        $division_id = $request->input("division_id");
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $type = $request->input('type');
        $marking_period_id = session()->get('term_id');
        $res = $this->getTimetable_data($request, $academic_section_id, $standard_id, $division_id, $sub_institute_id,
            $syear,$marking_period_id);

        return is_mobile($type, 'school_setup/show_timetable', $res, "view");
    }

    public function store(Request $request)
    {
        $finalArray = [];
        $period_arr = $_REQUEST['subject'];
        $teacher_arr = $_REQUEST['teacher'];

        $standard_arr = $division_arr = '';
        if (isset($_REQUEST['standard']) && $_REQUEST['standard'] != '') {
            $standard_arr = $_REQUEST['standard'];
        }
        if (isset($_REQUEST['division']) && $_REQUEST['division'] != '') {
            $division_arr = $_REQUEST['division'];
        }

        if (isset($_REQUEST['batch'])) {
            $batch_arr = $_REQUEST['batch'];
        }
        $hid_academic_section_id = $_REQUEST['hid_academic_section_id'];
        $hid_standard_id = $_REQUEST['hid_standard_id'];
        $hid_division_id = $_REQUEST['hid_division_id'];
        $hid_academic_id = $_REQUEST['hid_academic_section_id'];        
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $marking_period_id = session()->get('term_id');
        // timetableModel::where(
        //     [
        //     "sub_institute_id" => $sub_institute_id,
        //     "standard_id" => $hid_standard_id,
        //     "division_id" => $hid_division_id,
        //     "syear" => $syear,
        //     ])->delete();


        foreach ($period_arr as $period_id => $pval) {
            if (array_filter($period_arr[$period_id])) {
                $week_data_arr = array_filter($period_arr[$period_id]);
                foreach ($week_data_arr as $week_day => $subject_id) {
                    if ($subject_id != "" && $teacher_arr[$period_id][$week_day] != "") {
                        $check_exist_data = DB::table('timetable')
                            ->selectRaw('count(*) as total_data')
                            ->where('sub_institute_id', $sub_institute_id)
                            ->where('syear', $syear)
                            ->where('academic_section_id', $hid_academic_section_id)
                            ->where('standard_id', $hid_standard_id)
                            ->where('division_id', $hid_division_id)
                            ->where('period_id', $period_id)
                            ->where('week_day', $week_day)
                            // ->when($marking_period_id,function($query) use ($marking_period_id){
                            //     $query->where('marking_period_id',$marking_period_id);
                            // })
                           ->get()->toArray();

                        if (is_array($pval[$week_day])) {
                            foreach ($pval[$week_day] as $key => $val) {
                                $batch_id = $batch_arr[$period_id][$week_day][$key];
                                $subject_id = $period_arr[$period_id][$week_day][$key];
                                $teacher_id = $teacher_arr[$period_id][$week_day][$key];

                                if ($batch_id != "" && $subject_id != "" && $teacher_id != "") {
                                    $check_exist_data_batch = DB::table('timetable')
                                        ->selectRaw('count(*) as total_data')
                                        ->where('sub_institute_id', $sub_institute_id)
                                        ->where('syear', $syear)
                                        ->where('academic_section_id', $hid_academic_section_id)
                                        ->where('standard_id', $hid_standard_id)
                                        ->where('division_id', $hid_division_id)
                                        ->where('period_id', $period_id)
                                        ->where('batch_id', $batch_id)
                                        ->where('week_day', $week_day)
                                        // ->when($marking_period_id,function($query) use ($marking_period_id){
                                        //     $query->where('marking_period_id',$marking_period_id);
                                        // })
                                       ->get()->toArray();


                                    if ($check_exist_data_batch[0]->total_data != 0) {
                                        $finalArray = [
                                            'batch_id'   => $batch_id,
                                            'subject_id' => $subject_id,
                                            'teacher_id' => $teacher_id,
                                            'updated_at' => now(),
                                        ];

                                        timetableModel::where([
                                            "sub_institute_id"    => $sub_institute_id, "syear" => $syear,
                                            "academic_section_id" => $hid_academic_section_id,
                                            "standard_id"         => $hid_standard_id,
                                            "division_id"         => $hid_division_id, "period_id" => $period_id,
                                            "week_day"            => $week_day, "batch_id" => $batch_id,
                                            'marking_period_id'   => $marking_period_id,
                                        ])->update($finalArray);
                                    } else {

                                        $finalArray = [
                                            'sub_institute_id'    => $sub_institute_id,
                                            'syear'               => $syear,
                                            'academic_section_id' => $hid_academic_section_id,
                                            'standard_id'         => $hid_standard_id,
                                            'division_id'         => $hid_division_id,
                                            'period_id'           => $period_id,
                                            'batch_id'            => $batch_id,
                                            'subject_id'          => $subject_id,
                                            'teacher_id'          => $teacher_id,
                                            'week_day'            => $week_day,
                                            'created_at'          => now(),
                                            'updated_at'          => now(),
                                            'marking_period_id'   => $marking_period_id,                                            
                                        ];
                                        timetableModel::insert($finalArray);
                                    }
                                }
                            }
                        } else {
                            $merge = '';
                            if (isset($standard_arr[$period_id]) && isset($division_arr[$period_id])) {
                                $merge = 1;
                            }

                            if ($check_exist_data[0]->total_data != 0) {
                                $finalArray = [
                                    'subject_id' => $subject_id,
                                    'teacher_id' => $teacher_arr[$period_id][$week_day],
                                    'merge'      => $merge,
                                    'updated_at' => now(),
                                ];

                                timetableModel::where([
                                    "sub_institute_id"    => $sub_institute_id, "syear" => $syear,
                                    "academic_section_id" => $hid_academic_section_id,
                                    "standard_id"         => $hid_standard_id, "division_id" => $hid_division_id,
                                    "period_id"           => $period_id, "week_day" => $week_day,
                                    'marking_period_id'   => $marking_period_id,                                    
                                ])->update($finalArray);
                            } else {
                                $finalArray = [
                                    'sub_institute_id'    => $sub_institute_id,
                                    'syear'               => $syear,
                                    'academic_section_id' => $hid_academic_section_id,
                                    'standard_id'         => $hid_standard_id,
                                    'division_id'         => $hid_division_id,
                                    'period_id'           => $period_id,
                                    'batch_id'            => null,
                                    'subject_id'          => $subject_id,
                                    'teacher_id'          => $teacher_arr[$period_id][$week_day],
                                    'week_day'            => $week_day,
                                    'merge'               => $merge,
                                    'created_at'          => now(),
                                    'updated_at'          => now(),
                                    'marking_period_id'   => $marking_period_id,                                    
                                ];
                                timetableModel::insert($finalArray);
                            }

                            if (isset($standard_arr[$period_id][$week_day]) && isset($division_arr[$period_id][$week_day])) {
                                $get_academic_section_id = standardModel::where([
                                    'sub_institute_id' => $sub_institute_id,
                                    'id'               => $standard_arr[$period_id][$week_day],
                                ])->get()->toArray();
                                $new_academic_section_id = $get_academic_section_id[0]['grade_id'];

                                if ($check_exist_data[0]->total_data != 0) {
                                    $finalArray = [
                                        'subject_id' => $subject_id,
                                        'teacher_id' => $teacher_arr[$period_id][$week_day],
                                        'merge'      => $merge,
                                        'updated_at' => now(),
                                    ];

                                    timetableModel::where([
                                        "sub_institute_id"    => $sub_institute_id, "syear" => $syear,
                                        "academic_section_id" => $new_academic_section_id,
                                        "standard_id"         => $standard_arr[$period_id][$week_day],
                                        "division_id"         => $division_arr[$period_id][$week_day],
                                        "period_id"           => $period_id, "week_day" => $week_day,
                                        'marking_period_id'   => $marking_period_id,                                        
                                    ])->update($finalArray);
                                } else {
                                    $finalArray = [
                                        'sub_institute_id'    => $sub_institute_id,
                                        'syear'               => $syear,
                                        'academic_section_id' => $new_academic_section_id,
                                        'standard_id'         => $standard_arr[$period_id][$week_day],
                                        'division_id'         => $division_arr[$period_id][$week_day],
                                        'period_id'           => $period_id,
                                        'batch_id'            => null,
                                        'subject_id'          => $subject_id,
                                        'teacher_id'          => $teacher_arr[$period_id][$week_day],
                                        'week_day'            => $week_day,
                                        'merge'               => $merge,
                                        'created_at'          => now(),
                                        'updated_at'          => now(),
                                        'marking_period_id'   => $marking_period_id,                                        
                                    ];
                                    timetableModel::insert($finalArray);
                                }
                            }
                        }
                    }
                }
            }
        }

        $type = $request->input('type');
        $res = $this->getTimetable_data($request, $hid_academic_section_id, $hid_standard_id, $hid_division_id,
            $sub_institute_id, $syear);
        $res['message'] = 'Timetable Added Successfully';

        return is_mobile($type, "timetable.index", $res, "redirect");
    }

    public function getTimetable_data(Request $request,$academic_section_id,$standard_id,$division_id,$sub_institute_id,$syear,$marking_period_id='') 
    {
        $html = "";
        $timetable_data = timetableModel::
        where([
            'sub_institute_id'    => $sub_institute_id,
            'academic_section_id' => $academic_section_id,
            'standard_id'         => $standard_id,
            'division_id'         => $division_id,
            'syear'               => $syear,
            //'marking_period_id'   => $marking_period_id,            
        ])->get()->toArray();

        // check_permission in general data 
        $check_general_data = DB::table('general_data')->where(['sub_institute_id'=>$sub_institute_id,'fieldname'=>'timetable_teacher'])->first();
        
        // echo "<pre>";print_r($check_general_data);exit;
        foreach ($timetable_data as $k => $p) {
            $old_timetable_data[$p['week_day']][$p['period_id']]['SUBJECT_ID'][] = $p['subject_id'];
            $old_timetable_data[$p['week_day']][$p['period_id']]['TEACHER_ID'][] = $p['teacher_id'];
            if (isset($p['batch_id']) && $p['batch_id'] != "") {
                $old_timetable_data[$p['week_day']][$p['period_id']]['BATCH_ID'][] = $p['batch_id'];
            }
        }

        $batch_data = batchModel::where([
            'sub_institute_id' => $sub_institute_id,
            'standard_id' => $standard_id,
            'division_id' => $division_id,
            'syear' => $syear,
            //'marking_period_id'   => $marking_period_id,            
        ])->get()->toArray();
        $total_batches = count($batch_data);

        $period_data = periodModel::where(['sub_institute_id' => $sub_institute_id])
            ->orderby('sort_order')->get()->toArray();//"academic_section_id"=>$academic_section_id

        $subject_data = sub_std_mapModel::where([
            'sub_institute_id' => $sub_institute_id, "standard_id" => $standard_id,
        ])
            ->get(["subject_id", "display_name"])->toArray();

        /*$period_data =  periodModel::where(['sub_institute_id'=>$sub_institute_id,
        "academic_section_id"=>$academic_section_id])->orderby('sort_order')->get()->toArray();

        $subject_data =  sub_std_mapModel::where(['sub_institute_id'=>$sub_institute_id,
        "standard_id"=>$standard_id])->get(["subject_id","display_name"])->toArray();*/

        $teacher_data = tbluserModel::select('tbluser.*',
            DB::raw('CONCAT_WS(" ",tbluser.first_name,tbluser.middle_name,tbluser.last_name) AS teacher_name,
                (CASE WHEN total_lecture IS NULL THEN "Unlimited" ELSE tbluser.total_lecture - count(t.id) END) AS remaining_lecture'))
            ->join('tbluserprofilemaster', 'tbluserprofilemaster.id', '=', 'tbluser.user_profile_id')
            ->leftjoin("timetable AS t", function ($join) {
                $join->on("t.teacher_id", "=", "tbluser.id")
                    ->on("t.sub_institute_id", "=", "tbluser.sub_institute_id");
            })
            ->where(['tbluser.sub_institute_id' => $sub_institute_id, 'tbluserprofilemaster.name' => 'Teacher'])
            // ->when($marking_period_id,function($query) use ($marking_period_id){
            //     $query->where('t.marking_period_id',$marking_period_id);
            // })
            ->where('tbluser.status', 1)
           ->groupby("tbluser.id")
            ->orderby("tbluser.first_name")
            ->get();

        $week_data = $this->getweeks();
        $html = "<form action=".route('timetable.store')." name='timetable' id='timetable' method='post'>";
        $html .= csrf_field();
        $html .="<table class='table table-bordered table-center'>
                <tr>
                <td style='display: table-cell;width:30px;'>
                    <span class='label label-info'>Days/Lectures</span>
                </td>";

        //START FOR Total Lecture Capacity Checking added hidden variables
        foreach ($teacher_data as $tkey => $tval) {
            $html .= "<input type='hidden' name='hid_total_lecture_".$tval['id']."' id='hid_total_lecture_".$tval['id']."' value='".$tval['remaining_lecture']."'>";
        }
        //END FOR Total Lecture Capacity Checking added hidden variables

        foreach ($period_data as $pkey => $pval) {
            $html .= "<td style='width: 400px;display: table-cell;'><span class='label label-info'>".$pval['title']."</span></td>";
        }
        $html .= "</tr>";

        foreach ($week_data as $wkey => $wval) {
            $html .= "<tr>";
            $html .= "<td style='display: table-cell;'><span class='label label-warning'>".$wkey."</span></td>";
            foreach ($period_data as $pkey => $pval) {
                $divvalue = $wval.'-'.$pval['id'];
                $html .= "<td>";
                $html .= "<div id=".$divvalue." style='display:flex;'>";
                $html .= "<div>";

                //START Check Allocated Teachers - 16/11/2021
                $assigned_teacher_data = timetableModel::select('timetable.*')
                    ->where([
                        'sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'period_id' => $pval['id'],
                        'week_day'         => $wval,
                    ])
                    ->get()->toArray();

                $assigned_teacher_id_array = [];
                foreach ($assigned_teacher_data as $teacher_data1) {
                    $assigned_teacher_id_array[] = $teacher_data1['teacher_id'];
                }
                $assigned_teacher_ids = implode(",", $assigned_teacher_id_array);
                //END Check Allocated Teachers - 16/11/2021

                //IF BATCHES FOUND
                if (isset($old_timetable_data[$wval][$pval['id']]['SUBJECT_ID'])) {
                    //saved data
                    $sub_count = count($old_timetable_data[$wval][$pval['id']]['SUBJECT_ID']);
                    // $sub_count = $total_batches;
                    $k = 0;
                    $j = 1;
                    while ($sub_count > 0) {
                        if (isset($old_timetable_data[$wval][$pval['id']]['BATCH_ID'][$k])) {
                            $sub_select_name = "subject[".$pval['id']."][".$wval."][$j]";
                            $teacher_select_name = "teacher[".$pval['id']."][".$wval."][$j]";
                        } else {
                            $sub_select_name = "subject[".$pval['id']."][".$wval."]";
                            $teacher_select_name = "teacher[".$pval['id']."][".$wval."]";
                        }
                        $html .= "<select class='form-control' name='".$sub_select_name."' id='".$sub_select_name."' onchange=getMappingTeachers(this.value,'".$divvalue."'); >
                                <option value=''>Subject</option>";
                        foreach ($subject_data as $skey => $sval) {
                            $selected = '';
                            if (isset($old_timetable_data[$wval][$pval['id']]['SUBJECT_ID'][$k])) {
                                if ($old_timetable_data[$wval][$pval['id']]['SUBJECT_ID'][$k] == $sval['subject_id']) {
                                    $selected = "selected";
                                }
                            }
                            $html .= "<option ".$selected." value='".$sval['subject_id']."'>".$sval['display_name']."</option>";
                        }
                        $html .= "</select>";

                        //START Check Allocated Teachers - 16/11/2021
                        $old_assigned_teacher_data = timetableModel::select('timetable.*')
                            ->where([
                                'sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'period_id' => $pval['id'],
                                'week_day'         => $wval,
                            ])
                            // ->when($marking_period_id,function($query) use ($marking_period_id){
                            //     $query->where('marking_period_id',$marking_period_id);
                            // })                
                            ->get()->toArray();

                        $old_assigned_teacher_id_array = [];
                        foreach ($old_assigned_teacher_data as $teacher_data1) {
                            if (isset($old_timetable_data[$wval][$pval['id']]['TEACHER_ID'][$k])) {
                                if ($old_timetable_data[$wval][$pval['id']]['TEACHER_ID'][$k] != $teacher_data1['teacher_id']) {
                                    $old_assigned_teacher_id_array[] = $teacher_data1['teacher_id'];
                                }
                            }
                        }
                        $old_assigned_teacher_ids = implode(",", $old_assigned_teacher_id_array);
                        $extra_where = '';
                        if ($old_assigned_teacher_ids != '' && (empty($check_general_data) || $check_general_data->fieldvalue!='Yes')) {
                            $extra_where = 'AND tbluser.id NOT IN (' . $old_assigned_teacher_ids . ') ';
                        }

                        //END Check Allocated Teachers - 16/11/2021

                        $old_teacher_data = DB::table('tbluser')
                            ->join('tbluserprofilemaster', function ($join) {
                                $join->whereRaw('tbluserprofilemaster.id = tbluser.user_profile_id');
                            })
                            ->leftJoin('timetable AS t', function ($join) {
                                $join->whereRaw('t.teacher_id = tbluser.id AND t.sub_institute_id = tbluser.sub_institute_id');
                            })->selectRaw('tbluser.*, CONCAT_WS(" ",tbluser.first_name,tbluser.middle_name,tbluser.last_name)
                                AS teacher_name, (CASE WHEN total_lecture IS NULL THEN "Unlimited" ELSE tbluser.total_lecture
                                - COUNT(t.id) END) AS remaining_lecture')
                            ->whereRaw('(tbluser.sub_institute_id = "' . $sub_institute_id . '" AND tbluserprofilemaster.name
                                = "Teacher" AND tbluser.status = 1) ' . $extra_where)
                            // ->when($marking_period_id,function($query) use ($marking_period_id){
                            //         $query->where('t.marking_period_id',$marking_period_id);
                            //     })
                               ->groupBy('tbluser.id')
                            ->orderBy('tbluser.first_name')->get()->toArray();

                        $old_teacher_data_arr = json_decode(json_encode($old_teacher_data), true);

                        $html .= "<select class='form-control teacher_capacity_check' style='margin-top:10px;width:100px' name='".$teacher_select_name."' id='".$teacher_select_name."'>
                                <option value=''>Teacher</option>";
                        foreach ($old_teacher_data_arr as $tkey => $tval) {
                            $teacher_selected = '';
                            if (isset($old_timetable_data[$wval][$pval['id']]['TEACHER_ID'][$k])) {
                                if ($old_timetable_data[$wval][$pval['id']]['TEACHER_ID'][$k] == $tval['id']) {
                                    $teacher_selected = "selected";
                                }
                            }
                            $teacher_name = $tval['teacher_name']."(".$tval['remaining_lecture']." Lectures)";
                            $html .= "<option ".$teacher_selected." value='".$tval['id']."'>".$teacher_name."</option>";
                        }
                        $html .= "</select>";

                        if (isset($old_timetable_data[$wval][$pval['id']]['BATCH_ID'][$k])) {
                            $html .= "<select class='form-control' style='margin-top:10px;width:100px' name='batch[".$pval['id']."][".$wval."][$j]' id='batch[".$pval['id']."][".$wval."][$j]'>
                            <option value=''>Batch</option>";
                            foreach ($batch_data as $bkey => $bval) {
                                $batch_selected = '';
                                if (isset($old_timetable_data[$wval][$pval['id']]['BATCH_ID'][$k])) {
                                    if ($old_timetable_data[$wval][$pval['id']]['BATCH_ID'][$k] == $bval['id']) {
                                        $batch_selected = "selected";
                                    }
                                }
                                $html .= "<option ".$batch_selected." value='".$bval['id']."'>".$bval['title']."</option>";
                            }
                            $html .= "</select>";
                        }

                        $sub_count--;
                        if ($sub_count != 0) {
                            $html .= "<hr>";
                        }
                        $k++;
                        $j++;
                    }
                    $html .= "</div>";

                    $buttonhtml = '';
                    if ($total_batches > 0) {
                        if (isset($old_timetable_data[$wval][$pval['id']]['BATCH_ID']) && count($old_timetable_data[$wval][$pval['id']]['BATCH_ID']) > 0) {
                            $buttonhtml = "<a class='fas fa-minus-square' href='#' onclick=removeNewRow('".$divvalue."');></a>";
                        } else {
                            $buttonhtml = "<a class='fas fa-plus-square' href='#' onclick=addNewRow('".$divvalue."');></a>";
                        }
                    }

                    $html .= "<div class='plus_div' style='margin-left: 10px;'>";
                    $html .= $buttonhtml;
                    $html .= "<a class='mdi mdi-source-merge fa-fw text-danger ' href='#' onclick=addNewStdandardDiv('".$divvalue."');></a>";

                    // $delete_route = "{{ route('tasks.destroy', ['id' => 'divya']) }}";

                    $html .= "<a class='fas fa-window-close text-danger' href='#' onclick=deleteTimetable('".$divvalue."'); ></a>";
                    $html .= "</div>";
                    $html .= "</div>";
                    $html .= "</td>";
                } else {   //IF NOT BATCHES FOUND
                    //not saved data
                    // echo 'in ELSE : '.'<br>';
                    $html .= "<select class='form-control' name='subject[".$pval['id']."][".$wval."]' id='subject[".$pval['id']."][".$wval."]' onchange=getMappingTeachers(this.value,'".$divvalue."');>
                                <option value=''>Subject</option>";
                    foreach ($subject_data as $skey => $sval) {
                        $html .= "<option value='".$sval['subject_id']."'>".$sval['display_name']."</option>";
                    }
                    $html .= "</select>";

                    $html .= "<select class='form-control  teacher_capacity_check' style='margin-top:10px;width:100px' name='teacher[".$pval['id']."][".$wval."]' id='teacher[".$pval['id']."][".$wval."]'>
                            <option value=''>Teacher</option>";

                    //START Check Allocated Teachers - 16/11/2021
                    $assigned_teacher_data = timetableModel::select('timetable.*')
                        ->where([
                            'sub_institute_id' => $sub_institute_id, 'syear' => $syear, 'period_id' => $pval['id'],
                            'week_day'         => $wval,
                        ])
                        // ->when($marking_period_id,function($query) use ($marking_period_id){
                        //     $query->where('marking_period_id',$marking_period_id);
                        // })
                       ->get()->toArray();

                    $assigned_teacher_id_array = array();
                    foreach ($assigned_teacher_data as $teacher_data1) {
                        $assigned_teacher_id_array[] = $teacher_data1['teacher_id'];
                    }
                    $assigned_teacher_ids = implode(",", $assigned_teacher_id_array);
                    //END Check Allocated Teachers - 16/11/2021

                    $extra_where = '';
                    if ($assigned_teacher_ids != ''  && (empty($check_general_data) || $check_general_data->fieldvalue!='Yes')) {
                        $extra_where = 'AND tbluser.id NOT IN (' . $assigned_teacher_ids . ') ';
                    }

                    $new_teacher_data = DB::table('tbluser')
                        ->join('tbluserprofilemaster', function ($join) {
                            $join->whereRaw('tbluserprofilemaster.id = tbluser.user_profile_id');
                        })
                        ->leftJoin('timetable AS t', function ($join) {
                            $join->whereRaw('t.teacher_id = tbluser.id AND t.sub_institute_id = tbluser.sub_institute_id');
                        })->selectRaw('tbluser.*, CONCAT_WS(" ",tbluser.first_name,tbluser.middle_name,tbluser.last_name)
                                AS teacher_name, (CASE WHEN total_lecture IS NULL THEN "Unlimited" ELSE tbluser.total_lecture
                                - COUNT(t.id) END) AS remaining_lecture')
                        ->whereRaw('(tbluser.sub_institute_id = "' . $sub_institute_id . '" AND tbluserprofilemaster.name
                                = "Teacher" AND tbluser.status = 1) ' . $extra_where)
                        // ->when($marking_period_id,function($query) use ($marking_period_id){
                        //             $query->where('t.marking_period_id',$marking_period_id);
                        //         })
                        ->groupBy('tbluser.id')
                        ->orderBy('tbluser.first_name')->get()->toArray();
                    $new_teacher_data = json_decode(json_encode($new_teacher_data), true);

                    foreach ($new_teacher_data as $tkey => $tval) {
                        $teacher_name = $tval['teacher_name']."(".$tval['remaining_lecture']." Lectures)";
                        $html .= "<option value='".$tval['id']."'>".$teacher_name."</option>";
                    }

                    $html .= "</select>";

                    $html .= "</div>";
                    $html .= "<div class='plus_div' style='margin-left: 10px;'>";
                    if ($total_batches > 0) {
                        $html .= "<a class='fas fa-plus-square' href='#' onclick=addNewRow('".$divvalue."');></a>";
                    }
                    $html .= "<a class='mdi mdi-source-merge fa-fw text-danger' href='#' onclick=addNewStdandardDiv('".$divvalue."');></a>";
                    $html .= "</div>";
                    $html .= "</div>";
                    $html .= "</td>";
                }
            }
            $html .= "</tr>";
        }

        $colspan = count($period_data) + 1;
        $html .= "<tr><td align='center' colspan='".$colspan."'>
                    <center>
                        <input type='submit' name='submit' value='Save' class='btn btn-success'>
                        <input type='button' name='submit' value='Cancel' class='btn btn-success' onclick=window.location.href='".route('timetable.index')."'>
                    </center>
                    <input type='hidden' name='hid_academic_section_id' value='".$academic_section_id."'>
                    <input type='hidden' name='hid_standard_id' id='hid_standard_id' value='".$standard_id."'>
                    <input type='hidden' name='hid_division_id' id='hid_division_id' value='".$division_id."'>
                    <input type='hidden' name='hid_batchcount' id='hid_batchcount' value='".$total_batches."'>
                </td></tr>";
        $html .= "</table></form>";

        if (count($period_data) == 0)//If no period found
        {
            $html = "<center><font color='red'>Please Create Periods before creating timetable.</font></center>";
        }
        $stdData = standardModel::where(['sub_institute_id' => $sub_institute_id, 'grade_id' => $academic_section_id])
            ->get();

        $divData = std_div_mappingModel::select('division.*')
            ->join('division', 'division.id', "=", 'std_div_map.division_id')
            ->where(['std_div_map.sub_institute_id' => $sub_institute_id, 'std_div_map.standard_id' => $standard_id])
            ->get();

        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['HTML'] = $html;
        $res['academic_section_id'] = $academic_section_id;
        $res['standard_data'] = $stdData;
        $res['standard_id'] = $standard_id;
        $res['division_data'] = $divData;
        $res['division_id'] = $division_id;

        return array_merge($res, $data);
    }

    public function getweeks()
    {
        return [
            "Monday"   => "M", "Tuesday" => "T", "Wednesday" => "W", "Thursday" => "H", "Friday" => "F",
            "Saturday" => "S",
        ];
    }

    //Get BatchwiseTimetable -- Ajax Call
    public function getBatchTimetable(Request $request)
    {
        $division_id = $request->input("division_id");
        $id = $request->input("id");
        $standard_id = $request->input("standard_id");
        $mode = $request->input("mode");
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $marking_period_id = session()->get('term_id');
        $arr = explode("-", $id);

        $subject_data = sub_std_mapModel::where([
            'sub_institute_id' => $sub_institute_id,
            "standard_id"      => $standard_id,
        ]) 
        // ->when($marking_period_id,function($query) use ($marking_period_id){
        //     $query->where('marking_period_id',$marking_period_id);
        // })
       ->get(["subject_id", "display_name"])->toArray();

        $teacher_data = tbluserModel::select('tbluser.*')
            ->join('tbluserprofilemaster', 'tbluserprofilemaster.id', "=", 'tbluser.user_profile_id')
            ->where(['tbluser.sub_institute_id' => $sub_institute_id, 'tbluserprofilemaster.name' => 'Teacher', 'tbluser.status' => 1])
            // ->when($marking_period_id,function($query) use ($marking_period_id){
            //     $query->where('marking_period_id',$marking_period_id);
            // })
            ->get();

        $batch_data = batchModel::where([
            'sub_institute_id' => $sub_institute_id,
            'standard_id'      => $standard_id,
            'division_id'      => $division_id,
            'syear'            => $syear,
            //'marking_period_id'=> $marking_period_id,
        ])->get()->toArray();
        $total_batches = count($batch_data);

        $html = "";
        if ($mode == 'batchwise') {
            $html .= "<div>";
            for ($i = 1; $i <= $total_batches; $i++) {
                $html .= "<select class='form-control' name='subject[".$arr['1']."][".$arr['0']."][".$i."]' id='subject[".$arr['1']."][".$arr['0']."][".$i."]'>
                        <option value=''>Subject</option>";
                foreach ($subject_data as $skey => $sval) {
                    $html .= "<option value='".$sval['subject_id']."'>".$sval['display_name']."</option>";
                }
                $html .= "</select>";

                $html .= "<select class='form-control' style='margin-top:10px;width:100px' name='teacher[".$arr['1']."][".$arr['0']."][".$i."]' id='teacher[".$arr['1']."][".$arr['0']."][".$i."]'>
                        <option value=''>Teacher</option>";
                foreach ($teacher_data as $tkey => $tval) {
                    $teacher_name = $tval['first_name']." ".$tval['middle_name']." ".$tval['last_name'];
                    $html .= "<option value='".$tval['id']."'>".$teacher_name."</option>";
                }
                $html .= "</select>";

                $html .= "<select class='form-control' style='margin-top:10px;' name='batch[".$arr['1']."][".$arr['0']."][".$i."]' id='batch[".$arr['1']."][".$arr['0']."][".$i."]'>
                        <option value=''>Batch</option>";
                foreach ($batch_data as $bkey => $bval) {
                    $html .= "<option value='".$bval['id']."'>".$bval['title']."</option>";
                }
                $html .= "</select>";
                if ($i == $total_batches) {
                    // $html .="<div class='minus_div' style='margin-top: 32px;margin-left: 10px;'>";
                    // $html .="<a href='#' onclick=removeNewRow('".$id."');>-</a>";
                    // $html .="</div>";
                } else {
                    $html .= "<hr>";
                }
            }
            $html .= "</div>";
            $html .= "<div class='minus_div' style='margin-left: 10px;'>";
            $html .= "<a class='fas fa-minus-square' href='#' onclick=removeNewRow('".$id."');></a>";
            $html .= "<a class='mdi mdi-source-merge fa-fw text-danger' href='#' onclick=addNewStdandardDiv('".$id."');></a>";
            $html .= "<a class='fas fa-window-close text-danger' href='#'></a>";
            $html .= "</div>";
        } else {
            if ($mode == 'normal') {
                $html .= "<div>";
                $html .= "<select class='form-control' name='subject[".$arr['1']."][".$arr['0']."]' id='subject[".$arr['1']."][".$arr['0']."]'>
                        <option value=''>Subject</option>";
                foreach ($subject_data as $skey => $sval) {
                    $html .= "<option value='".$sval['subject_id']."'>".$sval['display_name']."</option>";
                }
                $html .= "</select>";

                $html .= "<select class='form-control' style='margin-top:10px;width:100px' name='teacher[".$arr['1']."][".$arr['0']."]' id='teacher[".$arr['1']."][".$arr['0']."]'>
                    <option value=''>Teacher</option>";
                foreach ($teacher_data as $tkey => $tval) {
                    $teacher_name = $tval['first_name']." ".$tval['middle_name']." ".$tval['last_name'];
                    $html .= "<option value='".$tval['id']."'>".$teacher_name."</option>";
                }
                $html .= "</select>";

                $html .= "</div>";
                $html .= "<div class='plus_div' style='margin-left: 10px;'>";
                $html .= "<a class='fas fa-plus-square' href='#' onclick=addNewRow('".$id."');></a>";
                $html .= "<a class='mdi mdi-source-merge fa-fw text-danger' href='#' onclick=addNewStdandardDiv('".$id."');></a>";
                $html .= "<a class='fas fa-window-close text-danger' href=#></a>";
                $html .= "</div>";
            }
        }

        echo $html;
        exit;
    }

    //Get NewStandardDiv -- Ajax Call
    public function getNewStandardDiv(Request $request)
    {
        $division_id = $request->input("division_id");
        $id = $request->input("id");
        $standard_id = $request->input("standard_id");
        $mode = $request->input("mode");
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        $arr = explode("-", $id);

        $subject_data = sub_std_mapModel::where([
            'sub_institute_id' => $sub_institute_id,
            "standard_id"      => $standard_id,
        ]) 
        // ->when($marking_period_id,function($query) use ($marking_period_id){
        //     $query->where('marking_period_id',$marking_period_id);
        // })
       ->get(["subject_id", "display_name"])->toArray();

        $teacher_data = tbluserModel::select('tbluser.*')
            ->join('tbluserprofilemaster', 'tbluserprofilemaster.id', "=", 'tbluser.user_profile_id')
            ->where(['tbluser.sub_institute_id' => $sub_institute_id, 'tbluserprofilemaster.name' => 'Teacher', 'tbluser.status' => 1])
            ->get();

        $standard_data = standardModel::where(['sub_institute_id' => $sub_institute_id])
        // ->when($marking_period_id,function($query) use ($marking_period_id){
        //     $query->where('marking_period_id',$marking_period_id);
        // })
       ->get([
            "id", "name",
        ])->toArray();
        $division_data = divisionModel::where(['sub_institute_id' => $sub_institute_id])->get([
            "id", "name",
        ])->toArray();

        $html = "";
        $html .= "<div>";
        $html .= "<select class='form-control' name='subject[".$arr['1']."][".$arr['0']."]' id='subject[".$arr['1']."][".$arr['0']."]'>
            <option value=''>Subject</option>";
        foreach ($subject_data as $skey => $sval) {
            $html .= "<option value='".$sval['subject_id']."'>".$sval['display_name']."</option>";
        }
        $html .= "</select>";

        $html .= "<select class='form-control' style='margin-top:10px;width:100px' name='teacher[".$arr['1']."][".$arr['0']."]' id='teacher[".$arr['1']."][".$arr['0']."]'>
                    <option value=''>Teacher</option>";
        foreach ($teacher_data as $tkey => $tval) {
            $teacher_name = $tval['first_name']." ".$tval['middle_name']." ".$tval['last_name'];
            $html .= "<option value='".$tval['id']."'>".$teacher_name."</option>";
        }
        $html .= "</select>";

        $html .= "<select class='form-control' style='margin-top:10px;width:100px' name='standard[".$arr['1']."][".$arr['0']."]' id='standard[".$arr['1']."][".$arr['0']."]'>
                    <option value=''>Standard</option>";
        foreach ($standard_data as $skey => $sval) {
            $html .= "<option value='".$sval['id']."'>".$sval['name']."</option>";
        }
        $html .= "</select>";

        $html .= "<select class='form-control' style='margin-top:10px;width:100px' name='division[".$arr['1']."][".$arr['0']."]' id='division[".$arr['1']."][".$arr['0']."]'>
                <option value=''>Division</option>";
        foreach ($division_data as $tkey => $tval) {
            $html .= "<option value='".$tval['id']."'>".$tval['name']."</option>";
        }
        $html .= "</select>";

        $html .= "</div>";
        $html .= "<div class='plus_div' style='margin-left: 10px;'>";
        // $html .="<a class='fas fa-plus-square' href='#' onclick=addNewRow('".$id."');></a>";
        $html .= "<a class='mdi mdi-source-merge fa-fw text-danger' href='#' onclick=addNewStdandardDiv('".$id."');></a>";
        $html .= "<a class='fas fa-window-close text-danger' href='#'></a>";
        $html .= "</div>";

        echo $html;
        exit;
    }

    //DELETE Timetable data -- Ajax Call
    public function deleteTimetable(Request $request)
    {
        $division_id = $request->input("division_id");
        $id = $request->input("id");
        $standard_id = $request->input("standard_id");
        $grade_id = $request->input("grade_id");
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $marking_period_id=session()->get('term_id');
        $arr = explode("-", $id);
        $week_day = $arr[0];
        $period_id = $arr[1];

        $check_timetable_data = timetableModel::where([
            'sub_institute_id' => $sub_institute_id,
            'syear'            => $syear,
            'standard_id'      => $standard_id,
            'division_id'      => $division_id,
            'week_day'         => $week_day,
            'period_id'        => $period_id,
            //'marking_period_id'=> $marking_period_id,
        ])->get()->toArray();

        if (count($check_timetable_data) > 0) {
            $deleted_record = timetableModel::where(
                [
                    "sub_institute_id" => $sub_institute_id,
                    "standard_id"      => $standard_id,
                    "division_id"      => $division_id,
                    "syear"            => $syear,
                    "week_day"         => $week_day,
                    "period_id"        => $period_id,
                    //"marking_period_id"=>$marking_period_id,
                ])->delete();
        }

        // echo $deleted_record;
        // exit;
        $res['redirect'] = '/timetable';        
        $type =$request->input('type');
        // // Return a JSON response
        return response()->json($res);
        // return is_mobile($type, "timetable.index", $res, "redirect");

    }

    //Get Subject Mapped Tecahers -- Ajax Call
    public function getMappingTeachers(Request $request)
    {
        $id = $request->input("id");
        $subject_id = $request->input("subject_id");
        $standard_id = $request->input("standard_id");
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $marking_period_id = session()->get('term_id');
        $arr = explode("-", $id);

        $subject_data = sub_std_mapModel::where([
            'sub_institute_id' => $sub_institute_id,
            "standard_id"      => $standard_id,
        ])->get(["subject_id", "display_name"])->toArray();

        $teacher_data = DB::table('tbluser')
            ->where('sub_institute_id', $sub_institute_id, 'status', 1)
            ->whereRaw("FIND_IN_SET('".$subject_id."',subject_ids)")->get()->toArray();

        $teacher_data = json_decode(json_encode($teacher_data), true);

        $html = "";
        $html .= "<div>";
        $html .= "<select class='form-control' name='subject[".$arr['1']."][".$arr['0']."]' id='subject[".$arr['1']."][".$arr['0']."]' onchange=getMappingTeachers(this.value,'".$id."');>
            <option value=''>Subject</option>";
        foreach ($subject_data as $skey => $sval) {
            $selected = '';
            if ($sval['subject_id'] == $subject_id) {
                $selected = 'selected = "selected" ';
            }

            $html .= "<option value='".$sval['subject_id']."' $selected>".$sval['display_name']."</option>";
        }
        $html .= "</select>";

        $html .= "<select class='form-control' style='margin-top:10px;width:100px' name='teacher[".$arr['1']."][".$arr['0']."]' id='teacher[".$arr['1']."][".$arr['0']."]'>
                    <option value=''>Teacher</option>";
        foreach ($teacher_data as $tkey => $tval) {
            $teacher_name = $tval['first_name']." ".$tval['middle_name']." ".$tval['last_name'];
            $html .= "<option value='".$tval['id']."'>".$teacher_name."</option>";
        }
        $html .= "</select>";
        $html .= "</div>";

        echo count($teacher_data)."///".$html;
        exit;
    }

    // ===================================================================
    // New JSON API endpoints for the Next.js "Create Timetable" page.
    // Additive only - existing web/Blade methods above are untouched.
    // ===================================================================

    public function getSectionsApi(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $sections = academic_sectionModel::where(['sub_institute_id' => $sub_institute_id])
            ->orderby('sort_order')->get(['id', 'title'])->toArray();

        return response()->json(['status' => 'SUCCESS', 'message' => 'SUCCESS', 'data' => $sections]);
    }

    public function getStandardsApi(Request $request)
    {
        $academic_id = $request->input('academic_section_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $standards = standardModel::where(['sub_institute_id' => $sub_institute_id, 'grade_id' => $academic_id])
            ->get(['id', 'name'])->toArray();

        return response()->json(['status' => 'SUCCESS', 'message' => 'SUCCESS', 'data' => $standards]);
    }

    public function getDivisionsApi(Request $request)
    {
        $standard_id = $request->input('standard_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');

        $divisions = std_div_mappingModel::select('division.id', 'division.name')
            ->join('division', 'division.id', '=', 'std_div_map.division_id')
            ->where(['std_div_map.sub_institute_id' => $sub_institute_id, 'standard_id' => $standard_id])
            ->get()->toArray();

        return response()->json(['status' => 'SUCCESS', 'message' => 'SUCCESS', 'data' => $divisions]);
    }

    public function getTimetableGridApi(Request $request)
    {
        $academic_section_id = $request->input('academic_section_id');
        $standard_id = $request->input('standard_id');
        $division_id = $request->input('division_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        if (! $academic_section_id || ! $standard_id || ! $division_id) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'academic_section_id, standard_id and division_id are required',
            ], 422);
        }

        $period_data = periodModel::where(['sub_institute_id' => $sub_institute_id])
            ->orderby('sort_order')->get(['id', 'title', 'sort_order'])->toArray();

        $subject_data = sub_std_mapModel::where([
            'sub_institute_id' => $sub_institute_id, 'standard_id' => $standard_id,
        ])->get(['subject_id', 'display_name'])->toArray();

        $batch_data = batchModel::where([
            'sub_institute_id' => $sub_institute_id, 'standard_id' => $standard_id,
            'division_id' => $division_id, 'syear' => $syear,
        ])->get(['id', 'title'])->toArray();

        $teacher_data = tbluserModel::select('tbluser.id',
            DB::raw('CONCAT_WS(" ",tbluser.first_name,tbluser.middle_name,tbluser.last_name) AS name,
                (CASE WHEN tbluser.total_lecture IS NULL THEN NULL ELSE tbluser.total_lecture - count(t.id) END) AS remaining_lecture'))
            ->join('tbluserprofilemaster', 'tbluserprofilemaster.id', '=', 'tbluser.user_profile_id')
            ->leftjoin('timetable AS t', function ($join) {
                $join->on('t.teacher_id', '=', 'tbluser.id')
                    ->on('t.sub_institute_id', '=', 'tbluser.sub_institute_id');
            })
            ->where([
                'tbluser.sub_institute_id' => $sub_institute_id,
                'tbluserprofilemaster.name' => 'Teacher',
                'tbluser.status' => 1,
            ])
            ->groupby('tbluser.id')
            ->orderby('tbluser.first_name')
            ->get()->toArray();

        $entries = DB::table('timetable')
            ->where([
                'timetable.sub_institute_id' => $sub_institute_id, 'timetable.syear' => $syear,
                'timetable.academic_section_id' => $academic_section_id, 'timetable.standard_id' => $standard_id,
                'timetable.division_id' => $division_id,
            ])
            ->leftjoin('period', 'period.id', '=', 'timetable.period_id')
            ->leftjoin('sub_std_map', function ($join) use ($standard_id) {
                $join->on('sub_std_map.subject_id', '=', 'timetable.subject_id')
                    ->where('sub_std_map.standard_id', $standard_id);
            })
            ->leftjoin('tbluser', 'tbluser.id', '=', 'timetable.teacher_id')
            ->leftjoin('batch', 'batch.id', '=', 'timetable.batch_id')
            ->select('timetable.id', 'timetable.week_day', 'timetable.period_id', 'period.title as period_name',
                'timetable.subject_id', 'sub_std_map.display_name as subject_name',
                'timetable.teacher_id', DB::raw('CONCAT_WS(" ",tbluser.first_name,tbluser.middle_name,tbluser.last_name) as teacher_name'),
                'timetable.batch_id', 'batch.title as batch_name')
            ->orderbyraw("FIELD(timetable.week_day,'M','T','W','H','F','S')")
            ->orderby('period.sort_order')
            ->get();

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'SUCCESS',
            'weekdays' => $this->weekdaysListApi(),
            'periods' => $period_data,
            'subjects' => $subject_data,
            'teachers' => $teacher_data,
            'batches' => $batch_data,
            'entries' => $entries,
        ]);
    }

    private function weekdaysListApi()
    {
        $out = [];
        foreach ($this->getweeks() as $label => $key) {
            $out[] = ['key' => $key, 'label' => $label];
        }

        return $out;
    }

    public function saveTimetableEntryApi(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $marking_period_id = $request->session()->get('term_id');

        $academic_section_id = $request->input('academic_section_id');
        $standard_id = $request->input('standard_id');
        $division_id = $request->input('division_id');
        $period_id = $request->input('period_id');
        $week_day = $request->input('week_day');
        $subject_id = $request->input('subject_id');
        $teacher_id = $request->input('teacher_id');
        $batch_id = $request->input('batch_id') ?: null;

        if (! $academic_section_id || ! $standard_id || ! $division_id || ! $period_id
            || ! $week_day || ! $subject_id || ! $teacher_id) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'academic_section_id, standard_id, division_id, period_id, week_day, subject_id and teacher_id are all required',
            ], 422);
        }

        if (! in_array($week_day, array_values($this->getweeks()), true)) {
            return response()->json(['status' => 'ERROR', 'message' => 'Invalid week_day'], 422);
        }

        // Same-teacher-double-booked conflict check. The legacy Blade UI only
        // hides already-booked teachers from the dropdown (soft UX filter,
        // driven by general_data.fieldname = 'timetable_teacher'); here we
        // enforce it as a hard validation error at save time when that flag
        // is not explicitly set to 'Yes'.
        $check_general_data = DB::table('general_data')->where([
            'sub_institute_id' => $sub_institute_id, 'fieldname' => 'timetable_teacher',
        ])->first();
        $allowDoubleBooking = $check_general_data && $check_general_data->fieldvalue === 'Yes';

        if (! $allowDoubleBooking) {
            $conflict = DB::table('timetable')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)
                ->where('period_id', $period_id)
                ->where('week_day', $week_day)
                ->where('teacher_id', $teacher_id)
                ->where(function ($q) use ($standard_id, $division_id) {
                    $q->where('standard_id', '!=', $standard_id)->orWhere('division_id', '!=', $division_id);
                })
                ->first();

            if ($conflict) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'This teacher is already assigned to another class in this period.',
                ], 409);
            }
        }

        $matchConditions = [
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear,
            'academic_section_id' => $academic_section_id, 'standard_id' => $standard_id,
            'division_id' => $division_id, 'period_id' => $period_id, 'week_day' => $week_day,
        ];

        $existingQuery = DB::table('timetable')->where($matchConditions);
        $batch_id ? $existingQuery->where('batch_id', $batch_id) : $existingQuery->whereNull('batch_id');
        $existing = $existingQuery->first();

        $values = [
            'subject_id' => $subject_id, 'teacher_id' => $teacher_id,
            'updated_at' => now(), 'marking_period_id' => $marking_period_id,
        ];

        if ($existing) {
            timetableModel::where('id', $existing->id)->update($values);
            $id = $existing->id;
        } else {
            $id = timetableModel::insertGetId(array_merge(
                $matchConditions,
                ['batch_id' => $batch_id],
                $values,
                ['created_at' => now()]
            ));
        }

        $saved = DB::table('timetable')
            ->leftjoin('period', 'period.id', '=', 'timetable.period_id')
            ->leftjoin('sub_std_map', function ($join) use ($standard_id) {
                $join->on('sub_std_map.subject_id', '=', 'timetable.subject_id')
                    ->where('sub_std_map.standard_id', $standard_id);
            })
            ->leftjoin('tbluser', 'tbluser.id', '=', 'timetable.teacher_id')
            ->leftjoin('batch', 'batch.id', '=', 'timetable.batch_id')
            ->where('timetable.id', $id)
            ->select('timetable.id', 'timetable.week_day', 'timetable.period_id', 'period.title as period_name',
                'timetable.subject_id', 'sub_std_map.display_name as subject_name',
                'timetable.teacher_id', DB::raw('CONCAT_WS(" ",tbluser.first_name,tbluser.middle_name,tbluser.last_name) as teacher_name'),
                'timetable.batch_id', 'batch.title as batch_name')
            ->first();

        return response()->json(['status' => 'SUCCESS', 'message' => 'Timetable entry saved successfully', 'data' => $saved]);
    }

    public function deleteTimetableEntryApi(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $id = $request->input('id');

        if (! $id) {
            return response()->json(['status' => 'ERROR', 'message' => 'id is required'], 422);
        }

        $deleted = timetableModel::where(['id' => $id, 'sub_institute_id' => $sub_institute_id, 'syear' => $syear])->delete();

        if (! $deleted) {
            return response()->json(['status' => 'ERROR', 'message' => 'Timetable entry not found'], 404);
        }

        return response()->json(['status' => 'SUCCESS', 'message' => 'Timetable entry deleted successfully']);
    }

}
