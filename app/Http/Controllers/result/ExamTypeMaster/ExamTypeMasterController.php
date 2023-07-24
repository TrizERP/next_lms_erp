<?php

namespace App\Http\Controllers\result\ExamTypeMaster;

use App\Http\Controllers\Controller;
use App\Models\result\ExamTypeMaster\ExamTypeMater;
use Illuminate\Http\Request;
use function App\Helpers\is_mobile;

class ExamTypeMasterController extends Controller
{

    public function index(Request $request)
    {
        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $type = $request->input('type');
        
        $school_data['data'] = $this->getData();

        return is_mobile($type, "result/ExamTypeMaster/show_exam_type", $school_data, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input('type');

        $sub_institute_id = session()->get('sub_institute_id');

        $maxCode = ExamTypeMater::where(['SubInstituteId' => $sub_institute_id])
            ->max('Code');
        ++$maxCode;
        $maxSortOrder = ExamTypeMater::where(['SubInstituteId' => $sub_institute_id])
            ->max('SortOrder');
        ++$maxSortOrder;

        $dataStore['Code'] = $maxCode;
        $dataStore['SortOrder'] = $maxSortOrder;

        return is_mobile($type, 'result/ExamTypeMaster/add_exam_type', $dataStore, "view");
    }

    public function store(Request $request)
    {
        $exam_type = new ExamTypeMater([
            'Code'           => $request->get('Code'),
            'ExamType'       => $request->get('ExamType'),
            'ShortName'      => $request->get('ShortName'),
            'SortOrder'      => $request->get('SortOrder'),
            'SubInstituteId' => session()->get('sub_institute_id'),
        ]);
        $exam_type->save();

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "exam_type_master.index", $res, "redirect");
    }

    public function getData()
    {
        $sub_institute_id = session()->get('sub_institute_id');
       
        $exam_type = ExamTypeMater::where(['SubInstituteId' => $sub_institute_id])
            ->orderBy('id')->get();

        $i = 1;
        foreach ($exam_type as $id => $arr) {
            $arr->SrNo = $i;
            $i++;
        }

        return $exam_type;
    }

    public function edit(Request $request, $id)
    {

        $type = $request->input('type');
        $data = ExamTypeMater::find($id);

        return is_mobile($type, "result/ExamTypeMaster/add_exam_type", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $data = [
            'Code'      => $request->get('Code'),
            'ExamType'  => $request->get('ExamType'),
            'ShortName' => $request->get('ShortName'),
            'SortOrder' => $request->get('SortOrder'),
        ];

        ExamTypeMater::where(["Id" => $id])->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];
        $type = $request->input('type');

        return is_mobile($type, "exam_type_master.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        ExamTypeMater::where(["Id" => $id])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "exam_type_master.index", $res, "redirect");
    }
}
