<?php

namespace App\Http\Controllers\leave;

use App\Http\Controllers\Controller;
use App\Imports\LeaveImport;
use App\Models\HrmsDepartment;
use App\Models\HrmsEmpLeave;
use App\Models\HrmsLeaveType;
use App\Models\user\tbluserModel;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class ApplyLeaveController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $sub_institute_id = session()->get('sub_institute_id');

        try {
            $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');
            $users = tbluserModel::where('sub_institute_id', $sub_institute_id)->get();
            //echo("<pre>");print_r($users);exit;
            $leave_types = HrmsLeaveType::get();
            return view('leave.apply_leave', compact('departments', 'users', 'leave_types'));
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function importLeave()
    {
        $sub_institute_id = session()->get('sub_institute_id');

        try {
            $departments = HrmsDepartment::where('status', true)->pluck('department', 'id');
            $users = tbluserModel::where('sub_institute_id', $sub_institute_id)->get();
            $leave_types = HrmsLeaveType::get();
            return view('leave.import_leave', compact('departments', 'users', 'leave_types'));
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
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
        $request->validate([
            'type_leave' => 'required',
            'employee_id' => 'required_if:type_leave,employee|nullable|exists:tbluser,id',
            'leave_type' => 'required|exists:hrms_leave_types,id',
            'day_type' => 'required|in:full,half',
            'from_date' => 'required|date',
            'to_date' => 'required_if:day_type,full|date|nullable|after_or_equal:from_date',
            'slot' => 'required_if:day_type,half',
            'comment' => 'required',
        ]);
        try {
            HrmsEmpLeave::updateOrCreate([
                'user_id' => $request->employee_id ?? session()->get('user_id'),
                'from_date' => $request->from_date,
            ],
                [
                    'department_id' => $request->department_id,
                    'leave_type_id' => $request->leave_type,
                    'day_type' => $request->day_type,
                    'from_date' => $request->from_date,
                    'to_date' => $request->to_date,
                    'slot' => $request->slot,
                    'comment' => $request->comment,
                ]);
            return response()->json(['message' => 'Holiday saved successfully !!'], 200);
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function importOldLeave(Request $request)
    {
        $request->validate([
            'upload_file' => 'required',
        ]);
        try {
            Excel::import(new LeaveImport, $request->upload_file);

            return response()->json(['message' => 'Leave Imported successfully !!'], 200);
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
        //
    }

    public function myLeave(Request $request)
    {
        try {
            if ($request->ajax()) {
                $data = HrmsEmpLeave::where('user_id', session()->get('user_id'))
                    ->with('leave_type')->get();
                return DataTables::of($data)
                    ->addIndexColumn()
                    ->addColumn('days', function ($row) {
                        if ($row->day_type == 'full') {
                            $fdate = $row->from_date;
                            $tdate = $row->to_date;
                            $datetime1 = new DateTime($fdate);
                            $datetime2 = new DateTime($tdate);
                            $interval = $datetime1->diff($datetime2);
                            $days = $interval->format('%a');
                            return $days;
                        } else {
                            return 0.5;
                        }
                    })
                    ->addColumn('leave_type', function ($row) {
                        return $row->leave_type->leave_type ?? '-';
                    })
                    ->rawColumns(['days', 'leave_type'])
                    ->make(true);
            }
            return view('leave.leave_list');
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
}
