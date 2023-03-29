<?php

namespace App\Http\Controllers\report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use function App\Helpers\is_mobile;

class StudentsMarksReportController extends Controller
{
    /**
     * Display a listing of the Students Marks Report.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $type = "web";

        return is_mobile($type, "reports/exam_report", [], "view");
    }
}
