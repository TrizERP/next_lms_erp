<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\admission\admissionEnquiryModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class admissionEnquiryAPIController extends Controller
{

    private function baseQuery(Request $request)
    {
        $sub_institute_id = $request->input('sub_institute_id');
        $syear = $request->input('syear');

        return admissionEnquiryModel::query()
            ->when($sub_institute_id, function ($query) use ($sub_institute_id) {
                $query->where('sub_institute_id', $sub_institute_id);
            })
            ->when($syear, function ($query) use ($syear) {
                $query->where('syear', $syear);
            });
    }

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

        $data = $this->baseQuery($request)
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

        $data = admissionEnquiryModel::where('id', $id)->first();

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

        $record = new admissionEnquiryModel();
        $record->fill($data);
        $record->save();

        return response()->json([
            'status_code' => 1,
            'message' => 'Added successfully',
            'data' => $record,
        ]);
    }

    public function update(Request $request, $id)
    {

        $record = admissionEnquiryModel::where('id', $id)->first();

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

        $record->fill($data);
        $record->save();

        return response()->json([
            'status_code' => 1,
            'message' => 'Updated successfully',
            'data' => $record->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {

        $record = admissionEnquiryModel::where('id', $id)->first();

        if (! $record) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Record not found',
                'data' => [],
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status_code' => 1,
            'message' => 'Deleted successfully',
            'data' => [],
        ]);
    }
}
