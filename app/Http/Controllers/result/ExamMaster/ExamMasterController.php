<?php

namespace App\Http\Controllers\result\ExamMaster;

use App\Http\Controllers\Controller;
use App\Models\result\ExamMaster\ExamMaster;
use App\Models\result\ExamTypeMaster\ExamTypeMater;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class ExamMasterController extends Controller
{

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

        return is_mobile($type, "result/ExamMaster/show_exam", $school_data, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input('type');

        $sub_institute_id = session()->get('sub_institute_id');

        $maxCode = ExamMaster::where(['SubInstituteId' => $sub_institute_id])
            ->max('Code');
        ++$maxCode;
        $maxSortOrder = ExamMaster::where(['SubInstituteId' => $sub_institute_id])
            ->max('SortOrder');
        ++$maxSortOrder;

        $ddvalue = ExamTypeMater::where(['SubInstituteId' => $sub_institute_id])->get()->toArray();

        $dataStore['Code'] = $maxCode;
        $dataStore['SortOrder'] = $maxSortOrder;
        $dataStore['ddValue'] = $ddvalue;

        return is_mobile($type, 'result/ExamMaster/add_exam', $dataStore, "view");
    }

    public function store(Request $request)
    {
        $exam = new ExamMaster([
            'Code'           => $request->get('Code'),
            'ExamType'       => $request->get('ExamType'),
            'ExamTitle'      => $request->get('ExamTitle'),
            'SortOrder'      => $request->get('SortOrder'),
            'SubInstituteId' => session()->get('sub_institute_id'),
        ]);
        $exam->save();

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "exam_master.index", $res, "redirect");
    }

    public function getData()
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $exam = ExamMaster::select('result_exam_master.*',
            DB::raw('COUNT(result_create_exam.id) AS total_count,result_exam_type_master.ExamType'))
            ->join('result_exam_type_master', 'result_exam_type_master.id', '=', 'result_exam_master.ExamType')
            ->leftjoin("result_create_exam", function ($join) {
                $join->on("result_create_exam.exam_id", "=", "result_exam_master.Id")
                    ->on("result_create_exam.sub_institute_id", "=", "result_exam_master.SubInstituteId");
            })
            ->where(['result_exam_master.SubInstituteId' => $sub_institute_id])
            ->groupby('result_exam_master.Id')
            ->get();
        $i = 1;
        foreach ($exam as $id => $arr) {
            $arr->SrNo = $i;
            $i++;
        }

        return $exam;
    }

    public function edit(Request $request, $id)
    {

        $sub_institute_id = session()->get('sub_institute_id');
        $type = $request->input('type');
        $ddvalue = ExamTypeMater::where(['SubInstituteId' => $sub_institute_id])->get()->toArray();
        $data = ExamMaster::find($id);
        $data['ddValue'] = $ddvalue;

        return is_mobile($type, "result/ExamMaster/add_exam", $data, "view");
    }

    public function update(Request $request, $id)
    {
        $data = [
            'Code'      => $request->get('Code'),
            'ExamType'  => $request->get('ExamType'),
            'ExamTitle' => $request->get('ExamTitle'),
            'SortOrder' => $request->get('SortOrder'),
        ];

        ExamMaster::where(["Id" => $id])->update($data);

        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];
        $type = $request->input('type');

        return is_mobile($type, "exam_master.index", $res, "redirect");
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        ExamMaster::where(["Id" => $id])->delete();
        $res = [
            "status_code" => 1,
            "message"     => "Data Deleted",
        ];

        return is_mobile($type, "exam_master.index", $res, "redirect");
    }

}
