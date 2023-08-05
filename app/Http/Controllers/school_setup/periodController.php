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

        return is_mobile($type, 'school_setup/show_period', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $marking_period_id = session()->get('term_id');

        return periodModel::select('period.*')
            ->where(['period.sub_institute_id' => $sub_institute_id])
            // ->when($marking_period_id,function($query) use ($marking_period_id){
            //     $query->where('marking_period_id',$marking_period_id);
            // })
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
        $data['academic_section_data'] = $academic_section_data;
        $data['academic_year_data'] = $academic_year_data;

        return is_mobile($type, 'school_setup/add_period', $data, "view");
    }

    public function store(Request $request)
    {
        ValidateInsertData('period', 'insert');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $length = $this->gettime_diff($request->get('start_time'), $request->get('end_time'));
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
                'academic_year_id'    => $request->get('academic_year_id') ?? null,
                'start_time'          => $request->get('start_time'),
                'end_time'            => $request->get('end_time'),
                'length'              => $length,
                'sub_institute_id'    => $sub_institute_id,
                //'marking_period_id'   => $marking_period_id,
                'status'              => "1",
            ]);

            $period->save();
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
        $data['academic_section_data'] = $academic_section_data;
        $data['academic_year_data'] = $academic_year_data;
        $data['period_data'] = $period_data;

        return is_mobile($type, "school_setup/add_period", $data, "view");
    }

    public function update(Request $request, $id)
    {
        ValidateInsertData('period', 'update');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $length = $this->gettime_diff($request->get('start_time'), $request->get('end_time'));
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
                'academic_year_id'    => $request->get('academic_year_id') ?? null,
                'start_time'          => $request->get('start_time'),
                'end_time'            => $request->get('end_time'),
                'length'              => $length,
                'sub_institute_id'    => $sub_institute_id,
                'marking_period_id'   => $marking_period_id,                
                'status'              => "1",
            ];
            periodModel::where(["id" => $id])->update($period_data);
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
        periodModel::where(["id" => $id])->delete();
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
