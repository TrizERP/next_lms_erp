<?php

namespace App\Http\Controllers\result\GradeMaster;

use App\Http\Controllers\Controller;
use App\Models\result\GradeMaster\GradeMaster;
use App\Models\result\GradeMaster\GradeMasterData;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;

class GradeMasterController extends Controller
{

    //
    public function index(Request $request)
    {

        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->getData();
        $type = $request->input('type');

        return is_mobile($type, "result/GradeMaster/show_grade_type", $school_data, "view");
    }

    public function getData()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');
        $data['grade'] = GradeMaster::where(['sub_institute_id' => $sub_institute_id])
            ->orderBy('sort_order')->get();

        foreach ($data['grade'] as $id => $arr) {
            $data['grade_data'][$arr['id']] = GradeMasterData::where([
                'sub_institute_id' => $sub_institute_id,
                'grade_id'         => $arr['id'],
                'syear'            => $syear,
            ])->orderBy('sort_order')->get();
        }

        return $data;
    }

    public function create(Request $request)
    {
        return view('result/GradeMaster/add_grade_type');
    }

    public function store(Request $request)
    {

        if (isset($_REQUEST['add_type']) && $_REQUEST['add_type'] == 'add_grade_data') {
            $exam = new GradeMasterData([
                'title'            => $request->get('title'),
                'breakoff'         => $request->get('breakoff'),
                'gp'               => $request->get('gp'),
                'sort_order'       => $request->get('sort_order'),
                'comment'          => $request->get('comment'),
                'grade_id'         => $request->get('grade_id'),
                'sub_institute_id' => session()->get('sub_institute_id'),
                'syear'            => session()->get('syear'),
            ]);
        } else {
            $exam = new GradeMaster([
                'grade_name'       => $request->get('grade_name'),
                'sub_institute_id' => session()->get('sub_institute_id'),
                'sort_order'       => $request->get('sort_order'),
            ]);
        }
        $exam->save();

        $res = array(
            "status_code" => 1,
            "message"     => "Data Saved",
        );

        $type = $request->input('type');

        return is_mobile($type, "grade_master.index", $res, "redirect");
    }

    public function AddAllData(Request $request, $id)
    {
        $type = $request->input('type');
        $data['grade_id'] = $id;

        return is_mobile($type, "result/GradeMaster/add_grade_data", $data, "view");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        GradeMasterData::where(["id" => $id])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "grade_master.index", $res, "redirect");
    }

}
