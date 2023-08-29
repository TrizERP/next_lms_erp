<?php

namespace App\Http\Controllers\leave;

use App\Http\Controllers\Controller;
use App\Models\HrmsDepartment;
use App\Models\HrmsHoliday;
use App\Models\HrmsWeekday;
use Exception;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class HolidayController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = HrmsHoliday::latest()
                ->when(request()->year, function ($q) {
                    $q->whereYear('from_date', request()->year);
                })
                ->get();
            return DataTables::of($data)
                ->addColumn('checkbox', function ($row) {
                    return '<input type="checkbox" id="' . $row->id . '" name="someCheckbox" class="checkSingle" />';
                })
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    $actionBtn = '<a href="javascript:void(0)" class="delete btn btn-danger btn-delete btn-sm"data-id="' . $row->id . '">Delete</a>';
                    return $actionBtn;
                })
                ->rawColumns(['checkbox', 'action'])
                ->make(true);
        }
        $departments = HrmsDepartment::whereStatus(true)->pluck('department', 'id');
        $weekdays = HrmsWeekday::pluck('day_type', 'day');

        if ($weekdays->isEmpty()) {
            $weekdays = [
                'monday' => '',    // Default value for Monday
                'tuesday' => '',   // Default value for Tuesday
                'wednesday' => '', // Default value for Wednesday
                'thursday' => '', // Default value for thursday
                'friday' => '', // Default value for friday
                'saturday' => '', // Default value for saturday
                'sunday' => '', // Default value for sunday
            ];
        }

        return view('leave.holiday_master', compact('weekdays', 'departments'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        echo("hi");die;
        $request->validate([
            'holiday_name' => 'required',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:' . $request->from_date,
            'day_Type' => 'required|in:full,half',
            'department' => 'required|array',
        ]);

        try {
            HrmsHoliday::updateOrCreate(['from_date' => $request->from_date],
                [
                    'holiday_name' => $request->holiday_name,
                    'to_date' => $request->to_date,
                    'day_type' => $request->day_Type,
                    'department' => implode(',', $request->department),
                ]);
            return response()->json(['message' => 'Holiday saved successfully !!'], 200);
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            HrmsHoliday::whereIn('id', explode(',', $id))->delete();
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function storeWeekdays(Request $request)
    {
        $request->validate([
            'monday' => 'required|in:full,half,weekend',
            'tuesday' => 'required|in:full,half,weekend',
            'wednesday' => 'required|in:full,half,weekend',
            'thursday' => 'required|in:full,half,weekend',
            'friday' => 'required|in:full,half,weekend',
            'saturday' => 'required|in:full,half,weekend',
            'sunday' => 'required|in:full,half,weekend',
        ]);

        try {
            HrmsWeekday::updateOrCreate(['day' => 'monday'], ['day_type' => $request->monday]);
            HrmsWeekday::updateOrCreate(['day' => 'tuesday'], ['day_type' => $request->tuesday]);
            HrmsWeekday::updateOrCreate(['day' => 'wednesday'], ['day_type' => $request->wednesday]);
            HrmsWeekday::updateOrCreate(['day' => 'thursday'], ['day_type' => $request->thursday]);
            HrmsWeekday::updateOrCreate(['day' => 'friday'], ['day_type' => $request->friday]);
            HrmsWeekday::updateOrCreate(['day' => 'saturday'], ['day_type' => $request->saturday]);
            HrmsWeekday::updateOrCreate(['day' => 'sunday'], ['day_type' => $request->sunday]);
            return response()->json(['message' => 'Weekday saved successfully !!'], 200);
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
}
