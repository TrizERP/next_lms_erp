<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\frontdesk\frontdeskModel;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

/**
 * Stateless JSON API for the Front desk module, used by the Next.js frontend.
 *
 * Separate from `App\Http\Controllers\frontdesk\frontdeskController`, which is
 * unchanged and keeps serving the Blade screens. It exists because that
 * controller cannot serve an API caller:
 *
 *  - `store()`/`update()` insert `$request->except(['_method','_token',
 *    'submit','VISITOR_PHOTO'])` verbatim, so API context parameters (`type`,
 *    `sub_institute_id`, `syear`, `user_id`) become columns in the SQL and
 *    MySQL rejects the write with "Unknown column 'type' in 'field list'".
 *  - `edit()` returns `view('frontdesk/edit_frontdesk')` directly instead of
 *    going through `is_mobile()`, so there is no way to read a single record.
 *  - `index()`/`store()` read `sub_institute_id`, `syear`, `term_id`,
 *    `user_id` and `user_profile_name` from the Laravel session only.
 *  - `frontdesk/*` is not in `VerifyCsrfToken::$except`, so its POSTs answer 419.
 *
 * Role scoping is preserved: a non-admin profile still sees only the entries
 * addressed to it. The profile is resolved from the database using the caller's
 * `user_id` rather than trusted from the request.
 */
class FrontDeskApiController extends Controller
{
    use GetsJwtToken;

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
            'user_id'          => 'required|integer',
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
     * Resolves the caller's profile name from the database. `frontdeskController
     * ::index()` reads this from the session; trusting a request-supplied value
     * would let a caller widen its own visibility.
     */
    private function profileName($userId, $subInstituteId)
    {
        return (string) DB::table('tbluser as u')
            ->join('tbluserprofilemaster as p', 'p.id', '=', 'u.user_profile_id')
            ->where('u.id', $userId)
            ->where('u.sub_institute_id', $subInstituteId)
            ->value('p.name');
    }

    private function isAdmin($userId, $subInstituteId)
    {
        return strtoupper($this->profileName($userId, $subInstituteId)) === 'ADMIN';
    }

    private function baseQuery($subInstituteId)
    {
        return DB::table('front_desk as fd')
            ->leftJoin('tblstudent as s', 's.id', '=', 'fd.STUDENT_ID')
            ->leftJoin('tbluser as u', function ($join) {
                $join->on('u.id', '=', 'fd.TO_WHOM_MEET')->where('u.status', 1);
            })
            ->selectRaw("fd.*,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS user_name")
            ->where('fd.SUB_INSTITUTE_ID', $subInstituteId);
    }

    /**
     * Stores an uploaded photo where the Blade screens expect it
     * (`storage/app/public/frontdesk`, served from `/storage/frontdesk/...`).
     *
     * @return array{VISITOR_PHOTO:string,FILE_SIZE:int,FILE_TYPE:string}|array{}
     */
    private function storePhoto(Request $request)
    {
        if (! $request->hasFile('VISITOR_PHOTO')) {
            return [];
        }

        $file = $request->file('VISITOR_PHOTO');
        $originalName = $file->getClientOriginalName();
        $extension = File::extension($originalName);
        $fileName = $originalName.date('YmdHis').'.'.$extension;
        $file->storeAs('public/frontdesk/', $fileName);

        return [
            'VISITOR_PHOTO' => $fileName,
            'FILE_SIZE'     => $file->getSize(),
            'FILE_TYPE'     => $extension,
        ];
    }

    /**
     * GET api/front-desk
     *
     * The visitor log plus the staff list the form needs. Non-admin profiles are
     * restricted to entries addressed to them, matching `index()`.
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
        $userId = $request->integer('user_id');
        $isAdmin = $this->isAdmin($userId, $subInstituteId);

        $entries = $this->baseQuery($subInstituteId)
            ->when(! $isAdmin, function ($query) use ($userId) {
                $query->where('fd.TO_WHOM_MEET', $userId);
            })
            ->orderByDesc('fd.ID')
            ->get();

        // create() excludes the calling user from the "to whom meet" list.
        $staff = DB::table('tbluser')
            ->select('id', DB::raw('concat_ws(" ", first_name, middle_name, last_name) as name'))
            ->where('sub_institute_id', $subInstituteId)
            ->where('status', 1)
            ->where('id', '!=', $userId)
            ->orderBy('first_name')
            ->get();

        return response()->json([
            'status_code' => 1,
            'message'     => 'Success',
            'data'        => [
                'entries'  => $entries,
                'staff'    => $staff,
                'is_admin' => $isAdmin,
            ],
        ]);
    }

    /**
     * GET api/front-desk/report
     *
     * Date-ranged log for the current academic year, matching
     * `frontDeskReport()`. Each bound is optional.
     */
    public function report(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = $this->validateContext($request);
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');
        $userId = $request->integer('user_id');

        $entries = $this->baseQuery($subInstituteId)
            ->where('fd.SYEAR', $request->integer('syear'))
            ->when(! $this->isAdmin($userId, $subInstituteId), function ($query) use ($userId) {
                $query->where('fd.TO_WHOM_MEET', $userId);
            })
            ->when($request->input('from_date'), function ($query, $fromDate) {
                $query->where('fd.DATE', '>=', $fromDate);
            })
            ->when($request->input('to_date'), function ($query, $toDate) {
                $query->where('fd.DATE', '<=', $toDate);
            })
            ->orderByDesc('fd.DATE')
            ->orderByDesc('fd.ID')
            ->get();

        return response()->json([
            'status_code' => 1,
            'message'     => 'Success',
            'data'        => ['entries' => $entries],
        ]);
    }

    /**
     * GET api/front-desk/{id}
     *
     * The read that `edit()` never provided.
     */
    public function show(Request $request, $id)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = $this->validateContext($request);
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');
        $userId = $request->integer('user_id');

        $entry = $this->baseQuery($subInstituteId)
            ->where('fd.ID', $id)
            ->when(! $this->isAdmin($userId, $subInstituteId), function ($query) use ($userId) {
                $query->where('fd.TO_WHOM_MEET', $userId);
            })
            ->first();

        if (! $entry) {
            return $this->failed('Front desk entry not found.', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'Success',
            'data'        => ['entry' => $entry],
        ]);
    }

    private function writeRules()
    {
        return [
            'sub_institute_id' => 'required|integer',
            'syear'            => 'required|integer',
            'user_id'          => 'required|integer',
            'VISITOR_TYPE'     => 'required|string|max:50',
            'TITLE'            => 'required|string|max:250',
            'DESCRIPTION'      => 'required|string',
            'STUDENT_ID'       => 'required|integer',
            'TO_WHOM_MEET'     => 'required|integer',
            'DATE'             => 'required|date',
            'IN_TIME'          => 'required',
            'OUT_TIME'         => 'nullable',
            'term_id'          => 'nullable|integer',
        ];
    }

    /**
     * POST api/front-desk
     *
     * Reproduces `store()`, including `OUT_DATE` defaulting to `DATE`, but takes
     * `STUDENT_ID` and `TO_WHOM_MEET` as plain ids instead of the Blade form's
     * "Label - id" strings.
     */
    public function store(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), $this->writeRules());
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $entry = array_merge([
            'SUB_INSTITUTE_ID'  => $request->integer('sub_institute_id'),
            'SYEAR'             => $request->integer('syear'),
            'MARKING_PERIOD_ID' => $request->input('term_id') ?: null,
            'VISITOR_TYPE'      => $request->input('VISITOR_TYPE'),
            'TITLE'             => $request->input('TITLE'),
            'DESCRIPTION'       => $request->input('DESCRIPTION'),
            'STUDENT_ID'        => $request->integer('STUDENT_ID'),
            'TO_WHOM_MEET'      => $request->integer('TO_WHOM_MEET'),
            'DATE'              => $request->input('DATE'),
            'IN_TIME'           => $request->input('IN_TIME'),
            'OUT_TIME'          => $request->input('OUT_TIME') ?: null,
            'OUT_DATE'          => $request->input('DATE'),
            'CREATED_BY'        => $request->integer('user_id'),
            'CREATED_IP'        => $request->ip(),
            'CREATED_ON'        => now(),
        ], $this->storePhoto($request));

        $id = frontdeskModel::insertGetId($entry);

        return response()->json([
            'status_code' => 1,
            'message'     => 'Front desk entry added successfully.',
            'data'        => ['id' => $id],
        ]);
    }

    /**
     * POST api/front-desk/{id}
     *
     * The working equivalent of `edit()` + `update()`. `CREATED_BY` and
     * `CREATED_ON` are left alone so the original author survives an edit; the
     * Blade `update()` overwrites both.
     */
    public function update(Request $request, $id)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), $this->writeRules());
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');
        $userId = $request->integer('user_id');

        $existing = DB::table('front_desk')
            ->where('ID', $id)
            ->where('SUB_INSTITUTE_ID', $subInstituteId)
            ->when(! $this->isAdmin($userId, $subInstituteId), function ($query) use ($userId) {
                $query->where('TO_WHOM_MEET', $userId);
            })
            ->first();

        if (! $existing) {
            return $this->failed('Front desk entry not found.', 404);
        }

        $entry = array_merge([
            'SYEAR'             => $request->integer('syear'),
            'MARKING_PERIOD_ID' => $request->input('term_id') ?: null,
            'VISITOR_TYPE'      => $request->input('VISITOR_TYPE'),
            'TITLE'             => $request->input('TITLE'),
            'DESCRIPTION'       => $request->input('DESCRIPTION'),
            'STUDENT_ID'        => $request->integer('STUDENT_ID'),
            'TO_WHOM_MEET'      => $request->integer('TO_WHOM_MEET'),
            'DATE'              => $request->input('DATE'),
            'IN_TIME'           => $request->input('IN_TIME'),
            'OUT_TIME'          => $request->input('OUT_TIME') ?: null,
        ], $this->storePhoto($request));

        DB::table('front_desk')
            ->where('ID', $id)
            ->where('SUB_INSTITUTE_ID', $subInstituteId)
            ->update($entry);

        return response()->json([
            'status_code' => 1,
            'message'     => 'Front desk entry updated successfully.',
            'data'        => ['id' => (int) $id],
        ]);
    }

    /**
     * POST api/front-desk/{id}/delete
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

        $subInstituteId = $request->integer('sub_institute_id');
        $userId = $request->integer('user_id');

        $deleted = DB::table('front_desk')
            ->where('ID', $id)
            ->where('SUB_INSTITUTE_ID', $subInstituteId)
            ->when(! $this->isAdmin($userId, $subInstituteId), function ($query) use ($userId) {
                $query->where('TO_WHOM_MEET', $userId);
            })
            ->delete();

        if (! $deleted) {
            return $this->failed('Front desk entry not found.', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'Front desk entry deleted successfully.',
            'data'        => ['id' => (int) $id],
        ]);
    }
}
