<?php

namespace App\Http\Controllers\implementation;

use App\Http\Controllers\Controller;
use App\Models\implementation\implementation_MasterModel;
use App\Models\school_setup\standardModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class implementation_MasterController extends Controller
{

    public function index(Request $request)
    {

        $type = $request->input('type');
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $sessionData = $request->session()->get('data');

        $data = implementation_MasterModel::where(['SUB_INSTITUTE_ID' => $sub_institute_id])->get()->toArray();
        $standard_data = standardModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $implementation_data = array();
        foreach ($data as $key => $value) {
            $implementation_data[$value['standard_id']]['std_wise_total_boys'] = $value['std_wise_total_boys'];
            $implementation_data[$value['standard_id']]['std_wise_total_girls'] = $value['std_wise_total_girls'];
            $implementation_data[$value['standard_id']]['std_wise_total'] = $value['std_wise_total'];

        }

        $res['status_code'] = 1;
        $res['message'] = "Success";
        if (count($data) > 0) {
            $res['total_boys'] = $data[0]['total_boys'];
            $res['total_girls'] = $data[0]['total_girls'];
            $res['total_strenght'] = $data[0]['total_strenght'];
            $res['final_std_total_boys'] = $data[0]['final_std_total_boys'];
            $res['final_std_total_girls'] = $data[0]['final_std_total_girls'];
            $res['final_std_total'] = $data[0]['final_std_total'];
            $res['total_male'] = $data[0]['total_male'];
            $res['total_female'] = $data[0]['total_female'];
        }
        $res['implementation_data'] = $implementation_data;
        $res['standard_data'] = $standard_data;
        if (isset($sessionData['isImplementation'])) {
            $res['isImplementation'] = 1;
        }

        return is_mobile($type, "implementation/add_implementation", $res, "view");
    }

    public function create(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $standard_data = standardModel::where(['sub_institute_id' => $sub_institute_id])->get()->toArray();
        view()->share('standard_data', $standard_data);

        return view('implementation/add_implementation');
    }

    public function store(Request $request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $type = $request->input('type');
        $isImplementation = $request->input('isImplementation');
        $standard_id = $request['standard_id'];
        $std_wise_total_boys = $request['std_wise_total_boys'];
        $std_wise_total_girls = $request['std_wise_total_girls'];
        $std_wise_total = $request['std_wise_total'];

        implementation_MasterModel::where(["sub_institute_id" => $sub_institute_id])->delete();

        $request->request->remove('standard_id');
        $request->request->remove('std_wise_total_boys');
        $request->request->remove('std_wise_total_girls');
        $request->request->remove('std_wise_total');
        $request->request->remove('isImplementation');

        foreach ($standard_id as $key => $value) {
            if ($value == '') {
                break;
            }
            $request->request->set('standard_id', $value);
            $request->request->set('std_wise_total_boys', $std_wise_total_boys[$key]);
            $request->request->set('std_wise_total_girls', $std_wise_total_girls[$key]);
            $request->request->set('std_wise_total', $std_wise_total[$key]);
            $data = $this->saveData($request);
        }

        $res['status_code'] = "1";
        $res['message'] = "Implementation created successfully";
        if (isset($isImplementation)) {
            $res['isImplementation'] = 1;

            return is_mobile($type, "implementation_1", $res);
        } else {
            return is_mobile($type, "add_implementation.index", $res);
        }
    }

    public function saveData(Request $request)
    {
        $newRequest = $request->all();
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['syear'] = $syear;

        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;
            }
        }


        implementation_MasterModel::insert($finalArray);

        return DB::getPdo()->lastInsertId();
    }

    public function updateData(Request $request)
    {
        $newRequest = $request->all();
        $user_id = $newRequest['id'];
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');
        $finalArray['sub_institute_id'] = $sub_institute_id;
        $finalArray['syear'] = $syear;

        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'id') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;
            }
        }

        return implementation_MasterModel::where(['id' => $user_id])->update($finalArray);
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $editData = implementation_MasterModel::find($id)->toArray();
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return view('implementation/edit_implementation', ['data' => $editData]);
    }

    public function update(Request $request, $id)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');
        $type = $request->input('type');

        $request->request->add(['id' => $id]); //add request
        $user_id = $id;

        $standard_id = $request['standard_id'];
        $std_wise_total_boys = $request['std_wise_total_boys'];
        $std_wise_total_girls = $request['std_wise_total_girls'];
        $std_wise_total = $request['std_wise_total'];

        $request->request->remove('standard_id');
        $request->request->remove('std_wise_total_boys');
        $request->request->remove('std_wise_total_girls');
        $request->request->remove('std_wise_total');

        foreach ($standard_id as $key => $value) {
            if ($value == '') {
                break;
            }
            $request->request->set('standard_id', $value);
            $request->request->set('std_wise_total_boys', $std_wise_total_boys[$key]);
            $request->request->set('std_wise_total_girls', $std_wise_total_girls[$key]);
            $request->request->set('std_wise_total', $std_wise_total[$key]);
            $data = $this->updateData($request);
        }


        $res['status_code'] = "1";
        $res['message'] = "Implementation updated successfully";
        $res['data'] = $data;

        return is_mobile($type, "add_implementation.index", $res);
    }

    /*public function destroy(Request $request, $id)
            {
                $user = array(
                    'status' => "0"
                );
                $type = $request->input('type');
                implementation_MasterModel::where(["id" => $id]);
                // tbluserModel::where(["id" => $id])->delete();
                $res['status_code'] = "1";
                $res['message'] = "Implementation deleted successfully";
                return is_mobile($type, "add_implementation.index", $res);
    */

}
