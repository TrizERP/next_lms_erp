<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\school_setup\batchModel;
use App\Models\school_setup\standardModel;
use App\Models\school_setup\std_div_mappingModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class batchController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data;

        return is_mobile($type, 'school_setup/show_batch', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        return batchModel::select('batch.*', 'batch.standard_id', 'standard.name as standard_name'
            , 'division.name as division_name', DB::raw('group_concat(batch.title) as titles'))
            ->join('standard', 'standard.id', '=', 'batch.standard_id')
            ->join('division', 'division.id', '=', 'batch.division_id')
            ->where(['batch.sub_institute_id' => $sub_institute_id, 'batch.syear' => $syear])
            ->groupBy('batch.standard_id', 'batch.division_id')
            ->orderBy('batch.standard_id', 'asc')
            ->get();
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $standard_data = standardModel::where(['sub_institute_id' => $sub_institute_id])->get();
        $data['standard_data'] = $standard_data;

        return is_mobile($type, 'school_setup/add_batch', $data, "view");
    }

    public function store(Request $request)
    {
        $marking_period_id = session()->get('term_id');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $title_Arr = $request->get('title');

        foreach ($title_Arr['NEW'] as $key => $val) {
            //Check if Subject Already Exist or not
            $exist = $this->check_exist($val, $request->get('standard_id'), $request->get('division_id'),
                $sub_institute_id, $syear);
            if ($exist == 0) {
                $finalArray = [
                    'title'            => $val,
                    'standard_id'      => $request->get('standard_id'),
                    'division_id'      => $request->get('division_id'),
                    'sub_institute_id' => $sub_institute_id,
                    'syear'            => $syear,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                    //'marking_period_id'=>$marking_period_id ?? null,
                ];
                batchModel::insert($finalArray);
                $res = [
                    "status_code" => 1,
                    "message"     => "Batch Added Successfully",
                ];
            } else {
                $res = [
                    "status_code" => 0,
                    "message"     => "Batch Already Exist",
                ];
            }
        }

        $type = $request->input('type');

        return is_mobile($type, "batch_master.index", $res, "redirect");
    }

    public function check_exist($batch_name, $std, $div, $sub_institute_id, $syear)
    {
        $batch_name = strtoupper($batch_name);

        $data = DB::table('batch')->selectRaw('count(*) as tot')
            ->where('standard_id', $std)
            ->where('division_id', $div)
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->whereRaw("UPPER(title) = '".$batch_name."'")->get()->toArray();

        return $data[0]->tot;
    }

    public function edit(Request $request, $std_id)
    {
        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $div_id = $request->input('div_id');

        $batch_data = batchModel::select('batch.*', DB::raw('group_concat(batch.title) as titles'),
            DB::raw('group_concat(batch.id) as ids'))
            ->where([
                'sub_institute_id' => $sub_institute_id,
                'syear' => $syear,
                'division_id' => $div_id,
                'standard_id' => $std_id,
            ])
            ->get()->toArray();

        $standard_data = standardModel::where(['sub_institute_id' => $sub_institute_id])->get();

        $division_data = std_div_mappingModel::select('division.id', 'division.name')
            ->join('division', 'division.id', '=', 'std_div_map.division_id')
            ->where(['std_div_map.sub_institute_id' => $sub_institute_id, 'std_div_map.standard_id' => $std_id])
            ->get();
        $data['standard_data'] = $standard_data;
        $data['division_data'] = $division_data;
        $data['batch_data'] = $batch_data[0];

        return is_mobile($type, "school_setup/add_batch", $data, "view");
    }

    public function update(Request $request, $id)
    {

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $title_Arr = $request->get('title');
        $marking_period_id = session()->get('marking_period_id');

        foreach ($title_Arr['NEW'] as $key => $val) {
            if ($val != "") {
                $finalArray[] = [
                    'title'            => $val,
                    'standard_id'      => $request->get('standard_id'),
                    'division_id'      => $request->get('division_id'),
                    'sub_institute_id' => $sub_institute_id,
                    'syear'            => $syear,
                    //'marking_period_id'=>$marking_period_id ?? null,
                    
                ];
            }
        }
        foreach ($title_Arr['EDIT'] as $key => $val) {
            $finalArray[] = [
                'title'            => $val,
                'standard_id'      => $request->get('standard_id'),
                'division_id'      => $request->get('division_id'),
                'sub_institute_id' => $sub_institute_id,
                'syear'            => $syear,
                'id'               => $key,
                'marking_period_id'=>$marking_period_id ?? null,
                
            ];
        }

        foreach ($finalArray as $key => $val) {
            // $check_for_duplicate = $check_for = "";
            // if( isset($val['id']) )
            // {
            // $check_for_duplicate = $val['id'];
            // $check_for = "id";
            // }

            // if( isset($val['title']) )
            // {
            // $check_for_duplicate = $val['title'];
            // $check_for = "title";
            // }

            //$check_for => $check_for_duplicate
            batchModel::updateOrCreate(
                [
                    'standard_id' => $val['standard_id'],
                    'division_id' => $val['division_id'],
                    'title'       => $val['title'] ?? "",
                    'syear'       => $syear,
                    //'marking_period_id'=>$marking_period_id ?? null,
                    
                ],
                [
                    'standard_id'      => $val['standard_id'],
                    'division_id'      => $val['division_id'],
                    'title'            => $val['title'],
                    'sub_institute_id' => $sub_institute_id,
                    'syear'            => $syear,
                    //'marking_period_id'=>$marking_period_id ?? null,
                    
                ]
            );
        }

        $res = [
            "status_code" => 1,
            "message"     => "Batch Updated Successfully",
        ];
        $type = $request->input('type');

        return is_mobile($type, "batch_master.index", $res, "redirect");
    }

    public function destroy(Request $request, $std_id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $div_id = $request->input('div_id');
        $type = $request->input('type');
        batchModel::where([
            "sub_institute_id" => $sub_institute_id, "syear" => $syear, "standard_id" => $std_id,
            "division_id"      => $div_id,
        ])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Batch Deleted Successfully";

        return is_mobile($type, "batch_master.index", $res);
    }

    public function ajaxdestroy(Request $request)
    {
        $id = $request->input('id');
        $type = $request->input('type');
        batchModel::where(["id" => $id])->delete();
        $res['status_code'] = "1";
        $res['message'] = "Batch Deleted Successfully";

        return is_mobile($type, "batch_master.index", $res);
    }

    public function StandardwiseDivision(Request $request)
    {
        $standard_id = $request->input("standard_id");
        $sub_institute_id = $request->session()->get("sub_institute_id");

        return std_div_mappingModel::select('std_div_map.division_id', 'division.name')
            ->join("division", "division.id", "=", "std_div_map.division_id")
            ->where(['std_div_map.sub_institute_id' => $sub_institute_id, 'standard_id' => $standard_id])
            ->get()->toArray();
    }
}
