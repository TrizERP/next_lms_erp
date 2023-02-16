<?php

namespace App\Http\Controllers\report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LogReportController extends Controller
{
    /**
     * Display a listing of the Students Marks Report.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request){
        $type="web";
        return \App\Helpers\is_mobile($type, "reports/log_report", [], "view");
    }
}
