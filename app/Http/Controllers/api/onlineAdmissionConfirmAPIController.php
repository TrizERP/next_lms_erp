<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class onlineAdmissionConfirmAPIController extends Controller
{

    public function index(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required',
            'syear'            => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => $validator->messages()->first(),
                'data' => [],
            ], 422);
        }

        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');

        $data = DB::table('new_admission_inquiry_registration')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $syear)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    public function show(Request $request, $id)
    {

        $data = DB::table('new_admission_inquiry_registration')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (! $data) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Record not found',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'Success',
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required',
            'syear'            => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 0,
                'message' => $validator->messages()->first(),
                'data' => [],
            ], 422);
        }

        $data = $request->except([
            '_method',
            '_token',
            'token',
            'submit',
            'type',
        ]);

        $id = DB::table('new_admission_inquiry_registration')->insertGetId($data);
        $record = DB::table('new_admission_inquiry_registration')->where('id', $id)->first();

        return response()->json([
            'status_code' => 1,
            'message' => 'Added successfully',
            'data' => $record,
        ]);
    }

    public function update(Request $request, $id)
    {

        $record = DB::table('new_admission_inquiry_registration')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (! $record) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Record not found',
                'data' => [],
            ], 404);
        }

        $data = $request->except([
            '_method',
            '_token',
            'token',
            'submit',
            'type',
            'id',
        ]);

        DB::table('new_admission_inquiry_registration')->where('id', $id)->update($data);
        $updatedRecord = DB::table('new_admission_inquiry_registration')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return response()->json([
            'status_code' => 1,
            'message' => 'Updated successfully',
            'data' => $updatedRecord,
        ]);
    }

    public function destroy(Request $request, $id)
    {

        $record = DB::table('new_admission_inquiry_registration')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (! $record) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Record not found',
                'data' => [],
            ], 404);
        }

        DB::table('new_admission_inquiry_registration')
            ->where('id', $id)
            ->update(['deleted_at' => now()]);

        return response()->json([
            'status_code' => 1,
            'message' => 'Deleted successfully',
            'data' => [],
        ]);
    }
}
