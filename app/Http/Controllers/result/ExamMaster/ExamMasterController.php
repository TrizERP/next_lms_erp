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

        $type = $request->input('type');
        
        if ($type == "API") {
            $sub_institute_id = $request->input('sub_institute_id');
            $standard_id = $request->input('standard_id') ?? '';
            $school_data['data'] = $this->getData($sub_institute_id,$standard_id,$type);
        } else {
            $school_data['data'] = $this->getData();
		}

        return is_mobile($type, "result/ExamMaster/show_exam", $school_data, "view");
    }

    public function create(Request $request)
    {
        $type = $request->input('type');

        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');        

        $maxCode = ExamMaster::where(['SubInstituteId' => $sub_institute_id])
            ->max('Code');
        ++$maxCode;

        $maxSortOrder = ExamMaster::where(['SubInstituteId' => $sub_institute_id])
            ->max('SortOrder');
        ++$maxSortOrder;
        $all_term = DB::table('academic_year')->where(['sub_institute_id' => $sub_institute_id,'syear'=>$syear])->get()->toArray();
        $all_standard = DB::table('standard')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $dataStore['all_term'] = $all_term;
        $dataStore['code'] = $maxCode;
        $dataStore['SortOrder'] = $maxSortOrder;
        $dataStore['all_standard'] = $all_standard;

        return is_mobile($type, 'result/ExamMaster/add_exam', $dataStore, "view");
    }

    public function store(Request $request)
    { 
        $sort = $request->get('SortOrder');
        foreach ($request->get('all_standard') as $std) {
            foreach ($request->get('all_term') as $term) {
                $val = [
                    'Code' => $request->get('Code'),
                    'ExamType' => 14,
                    'ExamTitle' => $request->get('ExamTitle'),
                    'SortOrder' => $request->get('SortOrder'),//$sort++,
                    'SubInstituteId' => session()->get('sub_institute_id'),
                    'created_at' => now(),
                    'standard_id' => $std,
                    'term_id' => $term,
                    'weightage' => $request->get('weightage') ?? '',
                    'created_by' => session()->get('user_id'),
                ];

                $insert = DB::table('result_exam_master')->insert($val);
            }
        }
        $res = [
            "status_code" => 1,
            "message"     => "Data Saved",
        ];

        $type = $request->input('type');

        return is_mobile($type, "exam_master.index", $res, "redirect");
    }

    public function getData($sub_institute_id = '', $standard_id = '', $type = '')
    {
        if($sub_institute_id == '')
        {
            $sub_institute_id = session()->get('sub_institute_id');
        }

        $exam = ExamMaster::select('result_exam_master.*',
                DB::raw('COUNT(result_create_exam.id) AS total_count'),'standard.name as std_name','academic_year.title as term')
            ->leftJoin('standard', 'standard.id', '=', 'result_exam_master.standard_id')
            ->leftJoin('academic_year', function ($join) use ($sub_institute_id) {
                $join->on('academic_year.term_id', '=', 'result_exam_master.term_id')
                     ->on('academic_year.sub_institute_id', '=', 'result_exam_master.SubInstituteId');
            })
            ->leftJoin("result_create_exam", function ($join) {
                $join->on("result_create_exam.exam_id", "=", "result_exam_master.Id")
                    ->on("result_create_exam.sub_institute_id", "=", "result_exam_master.SubInstituteId")
                    ->on("result_create_exam.syear", "=", "academic_year.syear");
            });

        if ($type == 'API' && !empty($standard_id)) {
            $exam->where('result_exam_master.standard_id', $standard_id);
        }

        $exam = $exam
            ->where('result_exam_master.SubInstituteId', $sub_institute_id)
            ->groupBy('result_exam_master.Id')
            ->orderByRaw('standard.sort_order, academic_year.sort_order, result_exam_master.SortOrder')
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
        $syear = session()->get('syear');
        
        $type = $request->input('type');
        $ddvalue = ExamTypeMater::where(['SubInstituteId' => $sub_institute_id])->get()->toArray();
        $data = ExamMaster::find($id);
        $data['ddValue'] = $ddvalue;

        $all_term = DB::table('academic_year')->where(['sub_institute_id' => $sub_institute_id,'syear'=>$syear])->get()->toArray();
        $all_standard = DB::table('standard')->where(['sub_institute_id' => $sub_institute_id])->get()->toArray();

        $data['all_term'] = $all_term;
        $data['all_standard'] = $all_standard;
        // return $data->weightage;exit;
        return is_mobile($type, "result/ExamMaster/add_exam", $data, "view");
    }

    public function update(Request $request, $id)
    {
        //dd($request);
        $data = [
            'ExamTitle' => $request->get('ExamTitle'),
            'SortOrder' => $request->get('SortOrder'),
            'SubInstituteId' => session()->get('sub_institute_id'),
            'created_at' => now(),
            'standard_id' => $request->input('all_standard.0'),
            'term_id' => $request->input('all_term.0'),
            'weightage' => $request->get('weightage') ?? '',
            'created_by' => session()->get('user_id'),
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
