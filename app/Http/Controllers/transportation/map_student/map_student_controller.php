<?php

namespace App\Http\Controllers\transportation\map_student;

use App\Http\Controllers\Controller;
use App\Models\transportation\add_vehicle\add_vehicle;
use App\Models\transportation\map_student\map_student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use function App\Helpers\is_mobile;
use function App\Helpers\SearchStudent;

class map_student_controller extends Controller
{

    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $data['message'] = $data_arr['message'];
            }
        }

        $data['data'] = [];
        $data['area'] = $this->area();
        $data['sel_area'] = $request->area;
        $type = $request->input('type');

        return is_mobile($type, "transportation/map_student/show", $data, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $grade=$name=$grno=$area="";

        if(isset($_REQUEST['name'])){
           $name =   $_REQUEST['name'];
        }
        
        if(isset($_REQUEST['grno'])){
            $grno =   $_REQUEST['grno'];
         }
         $stud_id = [];
         
        if(isset($_REQUEST['area'])){
           $data =  map_student::where('from_stop',$_REQUEST['area'])->get()->toArray();
            foreach($data as $val){
                $stud_id[]=$val['student_id'];
            }
            $area =   $_REQUEST['area'];
            
         }
        //  echo "<pre>";print_r($data);exit;
        if (isset($_REQUEST['grade']) &&  isset($_REQUEST['standard']) &&  isset($_REQUEST['division'])) {
            $student_data = SearchStudent($_REQUEST['grade'], $_REQUEST['standard'], $_REQUEST['division'],"","","",$name,"","",$grno, "");
        } else if (isset($_REQUEST['grade']) &&  isset($_REQUEST['standard'])) {
                $student_data = SearchStudent($_REQUEST['grade'], $_REQUEST['standard'],"","","","",$name,"","",$grno, "");
            } else {
                if (isset($_REQUEST['grade'])) {
                    $student_data = SearchStudent($_REQUEST['grade'],"","","","","",$name,"","",$grno, "");
                }elseif(isset($_REQUEST['area']) || isset($_REQUEST['grno']) || isset($_REQUEST['name'])){
                    $student_data = SearchStudent("", "","", "", "", "",$name,"","",$grno, "");
                }
        }
        $grade=0;
        if (isset($request->id)) {
            $student_data = SearchStudent("", "", "", "", "", "", "", "", "", "", $request->id);
            $grade = !empty($student_data) ? $student_data[0]['grade_id'] : 0;
        } else {
            $grade = $_REQUEST['grade'];
        }

        //START set default shift_from and shift_to
        $result = DB::table("academic_section as a")
        ->join('transport_school_shift as s', function ($join) {
            $join->whereRaw("s.sub_institute_id = a.sub_institute_id");
        })
        ->selectRaw('*,s.id as shift_id')
        ->where("a.sub_institute_id", "=", session()->get('sub_institute_id'))
        ->where("a.id", "=", $grade)
        ->get()->toArray();

        $default_shift_id = !empty($result) ? $result[0]->shift_id : '';

        $responce_arr = [];

        foreach ($student_data as $id => $arr) {
            $responce_arr['stu_data'][$id]['sr.no'] = $id + 1;
            $responce_arr['stu_data'][$id]['name'] = $arr['first_name'] . ' ' . $arr['middle_name'] . ' ' . $arr['last_name'];
            $responce_arr['stu_data'][$id]['student_id'] = $arr['student_id'];
            $responce_arr['stu_data'][$id]['mobile'] = $arr['mobile'];
            $responce_arr['stu_data'][$id]['std-div'] = $arr['standard_name'] . " / " . $arr['division_name'];
            $responce_arr['stu_data'][$id]['enrollment_no'] = $arr['enrollment_no'];
        
            if (isset($request->id)) {
                $responce_arr['stu_data'][$id]['address'] = $arr['address'];
                $responce_arr['stu_data'][$id]['city'] = $arr['city'];
                $responce_arr['stu_data'][$id]['state'] = $arr['state'];
            }
        
            $matchThese = [
                "syear"            => session()->get('syear'),
                "student_id"       => $arr['student_id'],
                "sub_institute_id" => session()->get('sub_institute_id'),
            ];
        
            $results = map_student::where($matchThese)->get()->toArray();

            if (count($results) > 0) {
                $responce_arr['stu_data'][$id]['from_shift_id'] = $results[0]['from_shift_id'];
                $responce_arr['stu_data'][$id]['from_bus_id'] = $results[0]['from_bus_id'];
                $responce_arr['stu_data'][$id]['from_stop'] = $results[0]['from_stop'];
                $responce_arr['stu_data'][$id]['to_shift_id'] = $results[0]['to_shift_id'];
                $responce_arr['stu_data'][$id]['to_bus_id'] = $results[0]['to_bus_id'];
                $responce_arr['stu_data'][$id]['to_stop'] = $results[0]['to_stop'];
                $responce_arr['stu_data'][$id]['total_amount'] = $results[0]['amount'];
                $responce_arr['stu_data'][$id]['distance'] = $results[0]['distance'];     
                $shift = DB::table('transport_school_shift')->where(['id'=>$results[0]['from_shift_id'],'sub_institute_id'=>$sub_institute_id])->get()->toArray();
                if (count($shift) > 0 && isset($request->id) ) {
                $responce_arr['stu_data'][$id]['shift_rate'] = $shift[0]->shift_rate;
                $responce_arr['stu_data'][$id]['km_amount'] = $shift[0]->km_amount;
                $responce_arr['stu_data'][$id]['van-shift'] = $results[0]['from_bus_id']."-".$results[0]['from_shift_id'];
                $responce_arr['stu_data'][$id]['van_shift'] = $this->van_shift();
                $responce_arr['stu_data'][$id]['area'] = $this->area();
                
                }     
             
                $responce_arr['stu_data'][$id]['ddShift'] = $this->ddShift();
               
                        
                //dd from bus
                $where = [
                    "tv.sub_institute_id" => session()->get('sub_institute_id'),
                    "tv.school_shift"     => $responce_arr['stu_data'][$id]['from_shift_id'],
                ];

                $bus = DB::table('transport_vehicle as tv')
                    ->where($where)
                    ->pluck('tv.title', 'tv.id');
                $responce_arr['stu_data'][$id]['ddFromBus'] = $bus;

                //dd to bus
                $where = [
                    "tv.sub_institute_id" => session()->get('sub_institute_id'),
                    "tv.school_shift"     => $responce_arr['stu_data'][$id]['to_shift_id'],
                ];

                $bus = DB::table('transport_vehicle as tv')
                    ->where($where)
                    ->pluck('tv.title', 'tv.id');
                $responce_arr['stu_data'][$id]['ddToBus'] = $bus;

                //dd from
                $school_shift = $responce_arr['stu_data'][$id]['from_shift_id'];
                $vehicle_id = $responce_arr['stu_data'][$id]['from_bus_id'];

                $where = [
                    "ss.id" => $school_shift,
                    "tv.id" => $vehicle_id,
                ];

                $routs = DB::table('transport_stop as ts')
                    ->join('transport_route_stop as rs', 'rs.stop_id', '=', 'ts.id')
                    ->join('transport_route as tr', 'tr.id', '=', 'rs.route_id')
                    ->join('transport_route_bus as rb', 'rb.route_id', '=', 'tr.id')
                    ->join('transport_vehicle as tv', 'tv.id', '=', 'rb.bus_id')
                    ->join('transport_school_shift as ss', 'ss.id', '=', 'tv.school_shift')
                    ->where($where)
                    ->groupBy('ts.id')
                    ->pluck('ts.stop_name', 'ts.id');

                $responce_arr['stu_data'][$id]['ddFrom'] = $routs;

                $school_shift = $responce_arr['stu_data'][$id]['to_shift_id'];
                $vehicle_id = $responce_arr['stu_data'][$id]['to_bus_id'];

                $where = [
                    "ss.id" => $school_shift,
                    "tv.id" => $vehicle_id,
                ];

                $routs = DB::table('transport_stop as ts')
                    ->join('transport_route_stop as rs', 'rs.stop_id', '=', 'ts.id')
                    ->join('transport_route as tr', 'tr.id', '=', 'rs.route_id')
                    ->join('transport_route_bus as rb', 'rb.route_id', '=', 'tr.id')
                    ->join('transport_vehicle as tv', 'tv.id', '=', 'rb.bus_id')
                    ->join('transport_school_shift as ss', 'ss.id', '=', 'tv.school_shift')
                    ->where($where)
                    ->groupBy('ts.id')
                    ->pluck('ts.stop_name', 'ts.id');


                $responce_arr['stu_data'][$id]['ddTo'] = $routs;
            } else {
                //START to fill from bus and to bus by default
                $where = [
                    "tv.sub_institute_id" => session()->get('sub_institute_id'),
                    "tv.school_shift"     => $default_shift_id,
                ];
        
                $bus = DB::table('transport_vehicle as tv')
                    ->where($where)
                    ->pluck('tv.title', 'tv.id');
                //END to fill from bus and to bus by default                    

                $responce_arr['stu_data'][$id]['from_shift_id'] = $default_shift_id;
                $responce_arr['stu_data'][$id]['from_bus_id'] = "";
                $responce_arr['stu_data'][$id]['from_stop'] = "";
                $responce_arr['stu_data'][$id]['to_shift_id'] = $default_shift_id;
                $responce_arr['stu_data'][$id]['to_bus_id'] = "";
                $responce_arr['stu_data'][$id]['to_stop'] = "";
                $responce_arr['stu_data'][$id]['ddFromBus'] = $bus;
                $responce_arr['stu_data'][$id]['ddToBus'] = $bus;
                $responce_arr['stu_data'][$id]['ddFrom'] = [];
                $responce_arr['stu_data'][$id]['ddTo'] = [];
                $responce_arr['stu_data'][$id]['ddShift'] = $this->ddShift();
            }
        }
        if(isset($request->id)){
            return $responce_arr;
        }else{
            $responce_arr['area']=$area;
            return is_mobile($type, "transportation/map_student/add", $responce_arr, "view");
        }
    }
    public function area()
    {
        return DB::table('transport_stop')
        ->select('stop_name', 'id')
        ->where("sub_institute_id", session()->get('sub_institute_id'))
        ->pluck('stop_name','id');
    
    }
    public function ddShift()
    {
        return DB::table('transport_school_shift')
            ->select('transport_school_shift.shift_title', 'transport_school_shift.id')
            ->where("transport_school_shift.sub_institute_id", session()->get('sub_institute_id'))
            ->pluck('shift_title', 'id');
    }
    public function van_shift()
    {
        $shifts = DB::table('transport_vehicle')
            ->select('transport_school_shift.shift_title', 'transport_school_shift.id', 'transport_vehicle.id as vid', 'transport_vehicle.vehicle_number')
            ->join('transport_school_shift', 'transport_school_shift.id', '=', 'transport_vehicle.school_shift')
            ->where("transport_school_shift.sub_institute_id", session()->get('sub_institute_id'))
            ->get();
    
        $result = [];
    
        foreach ($shifts as $shift) {
            $transport = $shift->vehicle_number . '[' . $shift->shift_title . ']';
            $result[$shift->vid.'-'.$shift->id] = $transport;
        }
    
        return $result;
    }    

    public function fetchData(Request $request)
    {
        $response = ['response' => '', 'success' => false];

        $validator = Validator::make($request->all(), [
            'student_id'       => 'required|numeric',
            'syear'            => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $response['response'] = $validator->messages();
        } else {
            //process the request

            $sub_institute_id = $_REQUEST['sub_institute_id'];
            $syear = $_REQUEST['syear'];
            $student_id = $_REQUEST['student_id'];

            $data_sql = "SELECT tms.syear,tms.student_id,tms.sub_institute_id,
                tssf.shift_title from_shift,tvf.title from_vehicle,fd.first_name from_driver,fd.mobile from_driver_mobile,fc.first_name from_cundoctor,fc.mobile from_conductor_mobile,tfs.stop_name from_stop,
                tsst.shift_title to_shift,tvt.title to_vehicle,td.first_name to_driver,td.mobile to_driver_mobile,tc.first_name to_cundoctor,tc.mobile to_conductor_mobile,tts.stop_name to_stop
                FROM transport_map_student tms
                INNER JOIN transport_school_shift tssf ON tssf.id = tms.from_shift_id
                INNER JOIN transport_vehicle tvf ON tvf.id = tms.from_bus_id
                INNER JOIN transport_stop tfs ON tfs.id = tms.from_stop
                INNER JOIN transport_school_shift tsst ON tsst.id = tms.to_shift_id
                INNER JOIN transport_vehicle tvt ON tvt.id = tms.to_bus_id
                INNER JOIN transport_stop tts ON tts.id = tms.to_stop
                INNER JOIN (
                SELECT *
                FROM transport_driver_detail
                WHERE `type` = 'Driver') fd ON tvf.driver = fd.id
                INNER JOIN (
                SELECT *
                FROM transport_driver_detail
                WHERE `type` = 'Driver') td ON tvt.driver = td.id
                INNER JOIN (
                SELECT *
                FROM transport_driver_detail
                WHERE `type` = 'Conductor') fc ON tvf.conductor = fc.id
                INNER JOIN (
                SELECT *
                FROM transport_driver_detail
                WHERE `type` = 'Conductor') tc ON tvt.conductor = tc.id
                WHERE tms.student_id = '$student_id' AND
                tms.sub_institute_id = '$sub_institute_id' AND
                tms.syear = '$syear'";

            $data_sql = preg_replace('/\n+/', '', $data_sql);
            $result_data = DB::select($data_sql);

            $response['response'] = $result_data;
            $response['success'] = true;
        }

        return json_encode($response);
    }


    public function store(Request $request)
    {
        if (isset($_REQUEST['values'])) {
            foreach ($_REQUEST['values'] as $student_id => $arr) {
                if (isset($arr['ckbox'])) {

                    map_student::where([
                        "syear"            => session()->get('syear'),
                        "student_id"       => $student_id,
                        "sub_institute_id" => session()->get('sub_institute_id'),
                    ])->delete();

                    $exam = new map_student([
                        "syear"            => session()->get('syear'),
                        "student_id"       => $student_id,
                        "from_shift_id"    => $arr['from_shift'],
                        "from_bus_id"      => $arr['from_bus'],
                        "from_stop"        => $arr['from_stop'],
                        "to_shift_id"      => $arr['to_shift'],
                        "to_bus_id"        => $arr['to_bus'],
                        "to_stop"          => $arr['to_stop'],
                        'sub_institute_id' => session()->get('sub_institute_id'),
                    ]);
                    $exam->save();
                }
            }
        }
        $res = [
            "status_code" => 1,
            "message"     => "Student Mapped Successfully",
        ];

        $type = $request->input('type');

        return is_mobile($type, "map_student.index", $res, "redirect");
    }

    public function ajaxChackRemainCapacity(Request $request)
    {
        if ($request->ajax()) {
            $syear = session()->get("syear");
            $sub_institute_id = session()->get("sub_institute_id");
            $bus_id = $request->input("bus_id");
            $shift_id = $request->input("shift_id");

            $getTotalCapacity = add_vehicle::select('sitting_capacity')
                ->where('id', $bus_id)
                ->where('school_shift', $shift_id)
                ->where('sub_institute_id', $sub_institute_id)
                ->get()
                ->first();

            $totalReserveCapacity = DB::table('transport_map_student')
                ->where('from_bus_id', $bus_id)
                ->where('from_shift_id', $shift_id)
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $syear)
                ->count();

            $totalCapacity = $getTotalCapacity->sitting_capacity ?? '';
            $remainCapacity = $totalCapacity - $totalReserveCapacity;

            return ['status' => 200, 'total_capacity' => $totalCapacity, 'total_remain_capacity' => $remainCapacity];
        }
    }
}
