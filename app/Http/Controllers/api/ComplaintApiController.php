<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\frontdesk\complaintModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

/**
 * Stateless JSON API for the Complaint module, used by the Next.js frontend.
 *
 * This controller is deliberately separate from
 * `App\Http\Controllers\frontdesk\complaintController`, which stays untouched
 * and keeps serving the Blade screens. It exists because that controller cannot
 * serve an API caller:
 *
 *  - `store()`/`update()` insert `$request->except(['_method','_token','submit',
 *    'ATTACHEMENT'])` verbatim, so any API context parameter (`type`,
 *    `sub_institute_id`, `syear`, `user_id`) becomes a column in the INSERT and
 *    MySQL rejects it with "Unknown column 'type' in 'field list'". Laravel's
 *    `except()` covers the query string too, so there is no way to pass
 *    `type=API` without breaking the write.
 *  - `edit()` calls `->whwre(...)` (a typo) and would fatal before rendering,
 *    and returns a Blade view with no `type=API` branch.
 *  - `destroy()` deletes from `taskModel` (the `task` table) rather than the
 *    `complaint` table.
 *
 * Every write here targets an explicit column whitelist taken from the
 * `complaint` migration, so no request parameter can ever reach the SQL.
 */
class ComplaintApiController extends Controller
{
    use GetsJwtToken;

    /** `complaint_status` rows are shared; complaints use the COMPLAIN type. */
    private const SOLUTION_TYPE = 'COMPLAIN';

    /** `complaintController::store()` seeds every new row with this state. */
    private const DEFAULT_SOLUTION = 'PENDING';

    private function authenticate()
    {
        try {
            if (! $this->jwtToken()->validate()) {
                return response()->json([
                    'status_code' => 2,
                    'message'     => 'Token Auth Failed',
                    'data'        => [],
                ], 401);
            }
        } catch (\Exception $exception) {
            return response()->json([
                'status_code' => 2,
                'message'     => $exception->getMessage(),
                'data'        => [],
            ], 401);
        }

        return null;
    }

    private function validateContext(Request $request)
    {
        return Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required|integer',
        ]);
    }

    private function failed($message, $status = 422)
    {
        return response()->json([
            'status_code' => 0,
            'message'     => $message,
            'data'        => [],
        ], $status);
    }

    /**
     * Stores an uploaded attachment exactly where the Blade screens expect it
     * (`storage/app/public/frontdesk`, served from `/storage/frontdesk/...`).
     *
     * @return array{ATTACHEMENT:string,FILE_SIZE:int,FILE_TYPE:string}|array{}
     */
    private function storeAttachment(Request $request)
    {
        if (! $request->hasFile('ATTACHEMENT')) {
            return [];
        }

        $file = $request->file('ATTACHEMENT');
        $extension = File::extension($file->getClientOriginalName());
        $fileName = 'complaint_'.date('YmdHis').'.'.$extension;
        $file->storeAs('public/frontdesk/', $fileName);

        return [
            'ATTACHEMENT' => $fileName,
            'FILE_SIZE'   => $file->getSize(),
            'FILE_TYPE'   => $extension,
        ];
    }

    /**
     * GET api/complaints
     *
     * Optional `from_date` / `to_date` narrow the list, matching the date range
     * the Blade complaint report posts to `complaintController::index()`.
     */
    public function index(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = $this->validateContext($request);
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $complaints = DB::table('complaint as c')
            ->leftJoin('tbluser as u', function ($join) use ($subInstituteId) {
                $join->on('u.id', '=', 'c.COMPLAINT_BY')
                    ->where('u.sub_institute_id', '=', $subInstituteId)
                    ->where('u.status', 1);
            })
            ->leftJoin('tbluser as u2', function ($join) use ($subInstituteId) {
                $join->on('u2.id', '=', 'c.COMPLAINT_SOLUTION_BY')
                    ->where('u2.sub_institute_id', '=', $subInstituteId)
                    ->where('u2.status', 1);
            })
            ->selectRaw("c.*,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS COMPLAINT_BY_NAME,
                CONCAT_WS(' ', u2.first_name, u2.middle_name, u2.last_name) AS COMPLAINT_SOLUTION_BY_NAME")
            ->where('c.SUB_INSTITUTE_ID', $subInstituteId)
            ->where('c.SYEAR', $syear)
            ->when($fromDate, function ($query) use ($fromDate) {
                $query->whereDate('c.DATE', '>=', $fromDate);
            })
            ->when($toDate, function ($query) use ($toDate) {
                $query->whereDate('c.DATE', '<=', $toDate);
            })
            ->orderByDesc('c.ID')
            ->get();

        $statuses = DB::table('complaint_status')
            ->select('ID', 'TITLE')
            ->where('TYPE', self::SOLUTION_TYPE)
            ->orderBy('TITLE')
            ->get();

        $users = DB::table('tbluser')
            ->select('id', DB::raw('concat_ws(" ", first_name, middle_name, last_name) as name'))
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 1)
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'status_code' => 1,
            'message'     => 'Success',
            'data'        => [
                'complaints' => $complaints,
                'statuses'   => $statuses,
                'users'      => $users,
            ],
        ]);
    }

    /**
     * POST api/complaints
     *
     * Reproduces `complaintController::store()`: the raiser is the calling user,
     * the solution state starts at PENDING, and SYEAR / MARKING_PERIOD_ID /
     * CREATED_IP / CREATED_DATE are stamped server-side.
     */
    public function store(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required|integer',
            'user_id'          => 'required|integer',
            'TITLE'            => 'required|string|max:150',
            'DESCRIPTION'      => 'required|string',
            'DATE'             => 'required|date',
            'term_id'          => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $complaint = array_merge([
            'SUB_INSTITUTE_ID'   => $request->integer('sub_institute_id'),
            'SYEAR'              => $request->integer('syear'),
            'MARKING_PERIOD_ID'  => $request->input('term_id') ?: null,
            'TITLE'              => $request->input('TITLE'),
            'DESCRIPTION'        => $request->input('DESCRIPTION'),
            'DATE'               => $request->input('DATE'),
            'COMPLAINT_BY'       => $request->integer('user_id'),
            'COMPLAINT_SOLUTION' => self::DEFAULT_SOLUTION,
            'CREATED_IP'         => $request->ip(),
            'CREATED_DATE'       => now(),
        ], $this->storeAttachment($request));

        $id = complaintModel::insertGetId($complaint);

        return response()->json([
            'status_code' => 1,
            'message'     => 'Complaint added successfully.',
            'data'        => ['id' => $id],
        ]);
    }

    /**
     * POST api/complaints/{id}
     *
     * The working equivalent of `complaintController::edit()` + `update()`.
     *
     * Deviation from the Blade controller, on purpose: `update()` there rewrites
     * COMPLAINT_BY to the editing user, which loses who actually raised the
     * complaint. That path is unreachable today (its `edit()` fatals first), so
     * the original raiser is preserved here instead.
     */
    public function update(Request $request, $id)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id'      => 'required|integer',
            'syear'                 => 'required|integer',
            'TITLE'                 => 'required|string|max:150',
            'DESCRIPTION'           => 'required|string',
            'DATE'                  => 'required|date',
            'COMPLAINT_SOLUTION'    => 'nullable|string',
            'COMPLAINT_SOLUTION_BY' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');
        $syear = $request->integer('syear');

        $existing = DB::table('complaint')
            ->where('ID', $id)
            ->where('SUB_INSTITUTE_ID', $subInstituteId)
            ->where('SYEAR', $syear)
            ->first();

        if (! $existing) {
            return $this->failed('Complaint not found.', 404);
        }

        $complaint = array_merge([
            'TITLE'                 => $request->input('TITLE'),
            'DESCRIPTION'           => $request->input('DESCRIPTION'),
            'DATE'                  => $request->input('DATE'),
            'COMPLAINT_SOLUTION'    => $request->input('COMPLAINT_SOLUTION') ?: self::DEFAULT_SOLUTION,
            'COMPLAINT_SOLUTION_BY' => $request->input('COMPLAINT_SOLUTION_BY') ?: null,
            'UPDATED_ON'            => now(),
        ], $this->storeAttachment($request));

        DB::table('complaint')
            ->where('ID', $id)
            ->where('SUB_INSTITUTE_ID', $subInstituteId)
            ->update($complaint);

        return response()->json([
            'status_code' => 1,
            'message'     => 'Complaint updated successfully.',
            'data'        => ['id' => (int) $id],
        ]);
    }

    /**
     * POST api/complaints/{id}/delete
     *
     * Deletes from the `complaint` table. The Blade `destroy()` deletes from
     * `taskModel` instead, which removes an unrelated `task` row.
     */
    public function destroy(Request $request, $id)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = $this->validateContext($request);
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $deleted = DB::table('complaint')
            ->where('ID', $id)
            ->where('SUB_INSTITUTE_ID', $request->integer('sub_institute_id'))
            ->where('SYEAR', $request->integer('syear'))
            ->delete();

        if (! $deleted) {
            return $this->failed('Complaint not found.', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'Complaint deleted successfully.',
            'data'        => ['id' => (int) $id],
        ]);
    }
}
