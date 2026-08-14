<?php

namespace App\Http\Controllers\calendar\calendar;

use App\Http\Controllers\Controller;
use App\Models\calendar\calendar\calendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * New JSON endpoints for the /front_desk/calendar page (Next.js frontend).
 *
 * Added alongside calendar_controller without changing its existing
 * store()/getData() logic. Those methods read sub_institute_id/syear from
 * $request->session(), which is never populated for bearer-token/JWT API
 * calls (no Laravel session cookie is ever established), so events saved
 * from the new frontend were written with a null tenant and then never
 * matched back on reload. These methods read sub_institute_id/syear from
 * the request payload instead, mirroring how circularController::store()
 * already handles its 'API' request path.
 */
class calendar_api_controller extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'            => 'required|string|max:50',
            'event_type'       => 'required|string|max:15',
            'school_date'      => 'required|numeric',
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
            'description'      => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => '0',
                'success' => false,
                'message' => $validator->errors()->first(),
                'data'    => null,
            ]);
        }

        $standard = $request->input('standard', []);
        if (is_array($standard)) {
            $standard = implode(',', $standard);
        }

        $record = [
            'title'            => $request->input('title'),
            'description'      => $request->input('description'),
            'event_type'       => $request->input('event_type'),
            'standard'         => $standard,
            'school_date'      => date('Y-m-d', $request->input('school_date') / 1000),
            'syear'            => $request->input('syear'),
            'sub_institute_id' => $request->input('sub_institute_id'),
        ];

        $id = calendar::insertGetId($record);

        return response()->json([
            'status'  => '1',
            'success' => true,
            'message' => 'Event added',
            'data'    => $record + ['id' => $id],
        ]);
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|numeric',
            'syear'            => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => '0',
                'success' => false,
                'message' => $validator->errors()->first(),
                'data'    => [],
            ]);
        }

        $data = calendar::from('calendar_events as lp')
            ->where('lp.sub_institute_id', $request->input('sub_institute_id'))
            ->where('lp.syear', $request->input('syear'))
            ->orderBy('lp.id', 'desc')
            ->get()->toArray();

        return response()->json([
            'status'  => '1',
            'success' => true,
            'message' => '',
            'data'    => $data,
        ]);
    }
}
