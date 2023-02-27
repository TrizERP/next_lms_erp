<?php

namespace App\Http\Controllers\settings;

use App\Http\Controllers\Controller;
use App\Models\school_setupModel;
use App\Models\settings\instituteDetailModel;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;


class instituteDetailController extends Controller
{

    public function index(Request $request)
    {
        $res['status_code'] = 1;
        $res['data'] = $this->getData();
        $type = $request->input('type');

        return is_mobile($type, "settings/add_institute_detail", $res, "view");
    }

    public function getData()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $data = school_setupModel::select("*")
            ->leftjoin("institute_detail as i", 'school_setup.Id', 'i.sub_institute_id')
            ->where(['school_setup.Id' => $sub_institute_id])
            ->get()->toArray();

        return $data[0];
    }


    public function store(Request $request)
    {

        $sub_institute_id = session()->get('sub_institute_id');

        $newRequest = $request->post();
        $finalArray['sub_institute_id'] = $sub_institute_id;
        foreach ($newRequest as $key => $value) {
            if ($key != '_method' && $key != '_token' && $key != 'submit' && $key != 'college_name') {
                if (is_array($value)) {
                    $value = implode(",", $value);
                }
                $finalArray[$key] = $value;
            }
        }

        instituteDetailModel::updateOrCreate([
            'sub_institute_id' => $sub_institute_id,
        ], $finalArray);

        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "Institute Detail Added Successfully";
        $res['data'] = $this->getData();

        return is_mobile($type, "settings/add_institute_detail", $res, "view");
    }

    public function edit(Request $request, $id)
    {
        //
    }

    public function update(Request $request, $id)
    {
        //
    }

    public function destroy(Request $request, $id)
    {
        //
    }


}
