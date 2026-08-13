<?php

namespace App\Http\Controllers\front_desk\circular;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CircularReportController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->input('type');
        if (in_array($type, ['API', 'JSON'])) {
            $sub_institute_id = $request->input('sub_institute_id');
            $syear = $request->input('syear');
        } else {
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
        }

        $standard = $request->input('standard_id');
        $division = $request->input('division_id');

        $circular_type = DB::table('circular_type')->get();

        $result = [];

        if ($standard || $division || $request->from_date || $request->to_date) {
            $query = DB::table("circular as c")
                ->join('standard as s', 's.id', '=', 'c.standard_id')
                ->join('circular_type as t', 't.id', '=', 'c.type')
                ->join('division as d', function ($join) {
                    $join->whereRaw("d.id = c.division_id AND d.sub_institute_id = c.sub_institute_id");
                })
                ->selectRaw('c.*, s.name as std_name, t.type as circular_type, d.name as div_name')
                ->where("c.syear", $syear)
                ->where("c.sub_institute_id", $sub_institute_id);

            if ($standard) {
                $query->where("c.standard_id", $standard);
            }

            if ($division) {
                $query->where("c.division_id", $division);
            }
            if ($request->from_date && $request->to_date) {
                $query->whereBetween("c.date_", [$request->from_date, $request->to_date]);
            }

            $result = $query->orderBy('c.id', 'DESC')->get();
        }

        if (in_array($type, ['API', 'JSON'])) {
            return response()->json([
                'data' => $result,
                'circular_type' => $circular_type,
            ]);
        }

        return view('front_desk.circular.report', compact('circular_type', 'result'));
    }
}

