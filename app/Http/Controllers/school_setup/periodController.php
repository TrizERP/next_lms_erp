<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\school_setup\academic_sectionModel;
use App\Models\school_setup\academic_yearModel;
use App\Models\school_setup\periodModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;
use function App\Helpers\ValidateInsertData;

class periodController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data;
        // echo "<pre>";print_r($data);exit;
        return is_mobile($type, 'school_setup/show_period', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');

        return periodModel::select('period.*')
            ->leftJoin('period_details','period_details.period_id','=','period.id')
            ->selectRaw('period.*,GROUP_CONCAT(DISTINCT period_details.start_time) as startTime,GROUP_CONCAT(DISTINCT period_details.end_time) as endTime')
            ->where(['period.sub_institute_id' => $sub_institute_id])
            // ->when($marking_period_id,function($query) use ($marking_period_id){
            //     $query->where('marking_period_id',$marking_period_id);
            // })
            ->orderBy('period.sort_order','ASC')
            ->groupBy('period.id')
            ->get();
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        $academic_section_data = academic_sectionModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $academic_year_data = academic_yearModel::where([
            'sub_institute_id' => $sub_institute_id, 'syear' => $syear
        ])->get();

        $data['standardLists'] = DB::table('standard')->where(['sub_institute_id'=>$sub_institute_id])->orderBy('sort_order')->get()->toArray();
        $data['academic_section_data'] = $academic_section_data;
        $data['academic_year_data'] = $academic_year_data;

        return is_mobile($type, 'school_setup/add_period', $data, "view");
    }

    public function store(Request $request)
    {
        //ValidateInsertData('period', 'insert');
        // echo "<pre>";print_r($request->all());exit;
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');

        $marking_period_id = session()->get('term_id');

        //Check if Subject Already Exist or not
        $exist = $this->check_exist($request->get('title'), $request->get('academic_section_id'), $sub_institute_id,$marking_period_id);
        if ($exist == 0) {
            $period = new periodModel([
                'title'               => $request->get('title'),
                'short_name'          => $request->get('short_name'),
                'sort_order'          => $request->get('sort_order'),
                'used_for_attendance' => $request->get('used_for_attendance') != '' ? $request->get('used_for_attendance') : "",
                'academic_section_id' => $request->get('academic_section_id') ?? null,
                'academic_year_id'    => $request->get('academic_year_id') ?? 0,
                'start_time'          => isset($request->start_time[0]) ? $request->start_time[0] : now(),
                'end_time'            => isset($request->end_time[0]) ? $request->end_time[0] : now(),
                'length'              => null,
                'sub_institute_id'    => $sub_institute_id,
                //'marking_period_id'   => $marking_period_id,
                'status'              => "1",
            ]);

            $period->save();
            $lastInsertId = isset($period->id) ? $period->id :  0; 
            // set time for peroids 26-03-2025
            if($request->has('standards')){
                foreach ($request->standards as $key => $stdArr) {
                    $startTime = isset($request->start_time[$key]) ? $request->start_time[$key] : null;
                    $endTime = isset($request->end_time[$key]) ? $request->end_time[$key] : null;
                    $length = $this->gettime_diff($startTime, $endTime);

                    if(isset($stdArr[0])){
                        foreach ($stdArr as $stdK => $stdV) {
                            if($stdV!='-'){
                                $insertArr = [
                                    'period_id'=>$lastInsertId,
                                    'standard_id'=>$stdV,
                                    'start_time'=>$startTime,
                                    'end_time'=>$endTime,
                                    'length'=>$length,
                                    'sub_institute_id'=>$sub_institute_id,
                                    'created_by'=>$user_id,
                                    'created_at'=>now()

                                ];
                                DB::table('period_details')->insert($insertArr);
                            }
                        }
                    }
                }
            }
            // end 26-03-2025
            $res = [
                "status_code" => 1,
                "message"     => "Period Added Successfully",
            ];
        } else {
            $res = [
                "status_code" => 0,
                "message"     => "Period Already Exist",
            ];
        }
        $type = $request->input('type');

        return is_mobile($type, "period_master.index", $res, "redirect");
    }

    public function check_exist($title, $academic_section_id = null, $sub_institute_id,$marking_period_id='')
    {
        $title = strtoupper($title);

        $data = DB::table('period')
            ->selectRaw('count(*) as tot')
            ->where('sub_institute_id', $sub_institute_id)
            ->whereRaw("UPPER(title) = '".$title."'")
            // ->when($marking_period_id,function($query) use ($marking_period_id){
            //     $query->where('marking_period_id',$marking_period_id);
            // })
            ->get()->toArray();

        return $data[0]->tot;
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $period_data = periodModel::find($id);
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $academic_section_data = academic_sectionModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $academic_year_data = academic_yearModel::where(['sub_institute_id' => $sub_institute_id, 'syear' => $syear])->get();

        $period_details = DB::table('period_details')
        ->where('period_id',$id)->where('sub_institute_id',$sub_institute_id)
        ->selectRaw('*,GROUP_CONCAT(DISTINCT standard_id) as standards,GROUP_CONCAT(DISTINCT id) as detailsIds')
        ->groupByRaw('start_time,end_time')
        ->get()->toArray();
        $data['standardLists'] = DB::table('standard')->where(['sub_institute_id'=>$sub_institute_id])->orderBy('sort_order')->get()->toArray();
        $data['academic_section_data'] = $academic_section_data;
        $data['academic_year_data'] = $academic_year_data;
        $data['period_data'] = $period_data;
        $data['period_details'] = $period_details;
        // echo "<pre>";print_r($data);exit;

        return is_mobile($type, "school_setup/add_period", $data, "view");
    }

    public function update(Request $request, $id)
    {
        // echo "<pre>";print_r($request->all());exit;
        //ValidateInsertData('period', 'update');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $user_id = $request->session()->get('user_id');
        $marking_period_id = session()->get('term_id');
        //Check if Subject Already Exist or not
        $exist = $this->check_exist($request->get('title'), $request->get('academic_section_id'), $sub_institute_id,$marking_period_id);

        $data = DB::table('period')
            ->selectRaw('count(*) as tot')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('id', $id)
            ->get()->toArray();

        $total_count = $data[0]->tot;
        if ($total_count > 0 && $exist == 1) {
            $period_data = [
                'title'               => $request->get('title'),
                'short_name'          => $request->get('short_name'),
                'sort_order'          => $request->get('sort_order'),
                'used_for_attendance' => $request->get('used_for_attendance') != '' ? $request->get('used_for_attendance') : "",
                'academic_section_id' => $request->get('academic_section_id') ?? null,
                'academic_year_id'    => $request->get('academic_year_id') ?? 0,
                'start_time'          => isset($request->start_time[0]) ? $request->start_time[0] : now(),
                'end_time'            => isset($request->end_time[0]) ? $request->end_time[0] : now(),
                'length'              => null,
                'sub_institute_id'    => $sub_institute_id,
                'marking_period_id'   => $marking_period_id,                
                'status'              => "1",
            ];
            periodModel::where(["id" => $id])->update($period_data);

            // set time for peroids 26-03-2025
            if($request->has('standards')){
                foreach ($request->standards as $key => $stdArr) {
                    $startTime = isset($request->start_time[$key]) ? $request->start_time[$key] : null;
                    $endTime = isset($request->end_time[$key]) ? $request->end_time[$key] : null;
                    $length = $this->gettime_diff($startTime, $endTime);

                    if(isset($stdArr[0])){
                        foreach ($stdArr as $stdK => $stdV) {
                            if($stdV!='-'){
                                $insertArr = [
                                    'period_id'=>$id,
                                    'standard_id'=>$stdV,
                                    'start_time'=>$startTime,
                                    'end_time'=>$endTime,
                                    'length'=>$length,
                                    'sub_institute_id'=>$sub_institute_id,
                                ];

                                $checkExists = DB::table('period_details')->where(['period_id'=>$id,'standard_id'=>$stdV,'sub_institute_id'=>$sub_institute_id,])->first();

                                if(!empty($checkExists) && isset($checkExists)){
                                    $insertArr['updated_by'] = $user_id;
                                    $insertArr['updated_at'] = now();
                                    DB::table('period_details')->where('id',$checkExists->id)->update($insertArr);
                                }
                                else{
                                    $insertArr['created_by'] = $user_id;
                                    $insertArr['created_at'] = now();
                                    DB::table('period_details')->insert($insertArr);
                                }
                            }
                        }
                    }
                }
            }
            // end 26-03-2025
            $res = [
                "status_code" => 1,
                "message"     => "Period Updated Successfully",
            ];
        } else {
            $res = [
                "status_code" => 0,
                "message"     => "Period Already Exist",
            ];
        }
        $type = $request->input('type');

        return is_mobile($type, "period_master.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');

        if($request->has('deleteType') && $request->deleteType=="detailsDelete"){
            $explodeId = explode(',',$request->deleteIds);
            foreach ($explodeId as $key => $dataId) {
                DB::table('period_details')->where(["id" => $dataId])->delete();
            }
            $res['status_code'] = "1";
            $res['message'] = "Period Details Deleted Successfully";
            return $res;
        }else{
            periodModel::where(["id" => $id])->delete();
        }
        $res['status_code'] = "1";
        $res['message'] = "Period Deleted Successfully";

        return is_mobile($type, "period_master.index", $res);
    }

    public function gettime_diff($start_time, $end_time)
    {
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);
        $diff = $start_time - $end_time;

        return abs($diff) / 60;
    }

}
