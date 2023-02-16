<?php

namespace App\Http\Controllers\result\ExamTypeMaster;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\result\ExamTypeMaster\ExamTypeMater;

class ExamTypeMasterController extends Controller {

    //
    public function index(Request $request) {

        if (session()->has('data')) { // check if it exists
            $data_arr = session('data'); // to retrieve value
            if (isset($data_arr['message'])) {
                $school_data['message'] = $data_arr['message'];
            }
        }

        $school_data['data'] = $this->getData();
        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "result/ExamTypeMaster/show_exam_type", $school_data, "view");
    }

    public function create(Request $request) {
        $type = $request->input('type');

        $sub_institute_id = session()->get('sub_institute_id');

        $maxCode = ExamTypeMater::
                where(['SubInstituteId' => $sub_institute_id])
                ->max('Code');
        $maxCode = $maxCode + 1;
        $maxSortOrder = ExamTypeMater::
                where(['SubInstituteId' => $sub_institute_id])
                ->max('SortOrder');
        $maxSortOrder = $maxSortOrder + 1;

        $dataStore['Code'] = $maxCode;
        $dataStore['SortOrder'] = $maxSortOrder;
        return \App\Helpers\is_mobile($type, 'result/ExamTypeMaster/add_exam_type', $dataStore, "view");
    }

    public function store(Request $request) {

//        \App\Helpers\ValidateInsertData('exam_type_master', $request);

//        echo "<pre>";
//        print_r(session()->get('sub_institute_id'));
//        exit;
        $exam_type = new ExamTypeMater([
            'Code' => $request->get('Code'),
            'ExamType' => $request->get('ExamType'),
            'ShortName' => $request->get('ShortName'),
            'SortOrder' => $request->get('SortOrder'),
            'SubInstituteId' => session()->get('sub_institute_id'),
        ]);
        $exam_type->save();

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );

        $type = $request->input('type');
        return \App\Helpers\is_mobile($type, "exam_type_master.index", $res, "redirect");
    }

    public function getData() {
        $sub_institute_id = session()->get('sub_institute_id');
        $exam_type = ExamTypeMater::
                        where(['SubInstituteId' => $sub_institute_id])
                        ->orderBy('id')->get();

        $i = 1;
        foreach ($exam_type as $id => $arr) {
            $arr->SrNo = $i;
            $i++;
        }

        return $exam_type;
    }

    public function edit(Request $request, $id) {

        $type = $request->input('type');
        $data = ExamTypeMater::find($id);

        return \App\Helpers\is_mobile($type, "result/ExamTypeMaster/add_exam_type", $data, "view");
    }

    public function update(Request $request, $id) {

//        \App\Helpers\ValidateInsertData('exam_type_master', 'update');

        $data = array(
            'Code' => $request->get('Code'),
            'ExamType' => $request->get('ExamType'),
            'ShortName' => $request->get('ShortName'),
            'SortOrder' => $request->get('SortOrder'),
        );

        ExamTypeMater::where(["Id" => $id])->update($data);

        $res = array(
            "status_code" => 1,
            "message" => "Data Saved",
        );
        $type = $request->input('type');

        return \App\Helpers\is_mobile($type, "exam_type_master.index", $res, "redirect");
    }

    public function destroy(Request $request, $id) {
        $type = $request->input('type');
        ExamTypeMater::where(["Id" => $id])->delete();
        $res = array(
            "status_code" => 1,
            "message" => "Data Deleted",
        );

        return \App\Helpers\is_mobile($type, "exam_type_master.index", $res, "redirect");
    }

}
