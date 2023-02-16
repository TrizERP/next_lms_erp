<?php

namespace App\Http\Controllers\report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use function App\Helpers\is_mobile;

class StudentsMarksReportController extends Controller
{
    /**
     * Display a listing of the Students Marks Report.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request){
        $type="web";
        return \App\Helpers\is_mobile($type, "reports/exam_report", [], "view");
    }
}
