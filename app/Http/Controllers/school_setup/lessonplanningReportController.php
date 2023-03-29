<?php

namespace App\Http\Controllers\school_setup;

use App\Http\Controllers\Controller;
use App\Models\school_setup\lessonplanningModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use function App\Helpers\is_mobile;

class lessonplanningReportController extends Controller
{
    public function index(Request $request)
    {
        $data = $this->getData($request);
        $type = $request->input('type');
        $res['status_code'] = 1;
        $res['message'] = "SUCCESS";
        $res['data'] = $data;

        return is_mobile($type, 'school_setup/show_lessonplanningReport', $res, "view");
    }

    public function getData($request)
    {
        $sub_institute_id = $request->session()->get('sub_institute_id');

        return lessonplanningModel::from("lessonplan as l")
            ->select('l.id', 'l.title', 'l.description', 'l.school_date',
                's.name as standard_name', 'd.name as division_name', 'ss.subject_code', 'ss.subject_name',
                DB::raw('ifnull(le.lessonplan_status,"-") as lessonplan_status'),
                DB::raw('ifnull(le.lessonplan_reason,"-") as lessonplan_reason'),
                DB::raw('ifnull(le.school_date,"-") as lessonplan_date'))
            ->join('standard as s', 's.id', '=', 'l.standard_id')
            ->join('division as d', 'd.id', '=', 'l.division_id')
            ->join('subject as ss', 'ss.id', '=', 'l.subject_id')
            ->leftjoin('lessonplan_execution as le', 'le.lessonplan_id', '=', 'l.id')
            ->where(['l.sub_institute_id' => $sub_institute_id])
            ->orderBy('l.school_date', 'asc')
            ->get();
    }
}
