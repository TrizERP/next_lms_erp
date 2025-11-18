<?php

namespace App\Http\Controllers\front_desk\circular;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CircularReportController extends Controller
{
    public function index(Request $request)
    {
        $sub_institute_id = session()->get('sub_institute_id');
        $syear = session()->get('syear');

        // Load dropdown for Type
        $circular_type = DB::table('circular_type')->get();

        // Default empty result
        $result = [];

        // ---------------------------
        // WHEN USER CLICKS SEARCH
        // ---------------------------
        if (
            $request->has('from_date') ||
            $request->has('title') ||
            $request->has('type') ||
            $request->has('standard_id') ||
            $request->has('division_id')
        ) {

            $query = DB::table("circular as c")
                ->join('standard as s', 's.id', '=', 'c.standard_id')
                ->join('circular_type as t', 't.id', '=', 'c.type')
                ->join('division as d', function ($join) {
                    $join->whereRaw("d.id = c.division_id AND d.sub_institute_id = c.sub_institute_id");
                })
                ->selectRaw('c.*, s.name as std_name, t.type as circular_type, d.name as div_name')
                ->where("c.syear", $syear)
                ->where("c.sub_institute_id", $sub_institute_id);

            // Apply Filters
            if ($request->standard_id) {
                $query->whereIn("c.standard_id", $request->standard_id);
            }

            if ($request->division_id) {
                $query->whereIn("c.division_id", $request->division_id);
            }
            if ($request->from_date && $request->to_date) {
                $query->whereBetween("c.date_", [$request->from_date, $request->to_date]);
            }

            $result = $query->orderBy('c.id', 'DESC')->get();
        }

        // Return ONE single view
        return view('front_desk.circular.report', compact('circular_type', 'result'));
    }
}

