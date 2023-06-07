<?php

namespace App\Http\Controllers\leave;

use App\Http\Controllers\Controller;
use App\Models\HrmsLeaveType;
use Exception;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class LeaveTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            if ($request->ajax()) {
                $data = HrmsLeaveType::latest()->get();
                return DataTables::of($data)
                    ->addIndexColumn()
                    ->addColumn('action', function ($row) {
                        $actionBtn = '<a href="javascript:void(0)" class="edit btn btn-success btn-edit btn-sm" data-id="' . $row->id . '">Edit</a> <a href="javascript:void(0)" class="delete btn btn-danger btn-delete btn-sm"data-id="' . $row->id . '">Delete</a>';
                        return $actionBtn;
                    })
                    ->rawColumns(['action'])
                    ->make(true);
            }
            return view('leave.leave_type_master');
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
            'leave_type_name' => 'required',
        ]);

        try {
            $objLeave = HrmsLeaveType::find($request->leave_id) ?? HrmsLeaveType::firstOrNew(['leave_type' => $request->leave_type_name]);
            $objLeave->leave_type_id = $objLeave->leave_type_id ?? $objLeave->setLeaveTypeId();
            $objLeave->leave_type = $request->leave_type_name;
            if ($objLeave->save()) {
                return response()->json(['message' => 'Leave type added successfully !!'], 200);
            }
            return response()->json(['message' => 'Something went wrong !!'], 500);
        } catch (Exception $e) {
            return response()->json($e->getMessage());
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
        try {
            $data = HrmsLeaveType::find($id);
            return response()->json(['data' => $data], 200);
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
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
            HrmsLeaveType::find($id)->delete();
            return response()->json(['message' => 'Leave type deleted successfully !!'], 200);
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
}
