<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Stateless JSON API for the Petty cash module, used by the Next.js frontend.
 *
 * Separate from the three Blade controllers in
 * `App\Http\Controllers\frontdesk` (PettyCashController,
 * PettyCashMasterController, PettyCashReportController), which are unchanged.
 * It exists because those cannot serve an API caller:
 *
 *  - `PettyCashController::update()` calls
 *    `PettyCashMasterModel::where(['id' => $id])->update($data)` — it writes to
 *    `petty_cash_master`, so editing an entry overwrites an unrelated expense
 *    head. That is the defect this controller fixes.
 *  - `store()`/`update()` in both write controllers insert
 *    `$request->except(['_method','_token','submit'])` verbatim, so API context
 *    parameters (`type`, `syear`, `user_id`) become columns in the SQL and
 *    MySQL rejects the write with "Unknown column ... in 'field list'".
 *  - Every read takes `sub_institute_id` from the Laravel session only.
 *  - `frontdesk/*` is not in `VerifyCsrfToken::$except`, so its POSTs answer 419.
 *
 * Schema note: `petty_cash.amount` is an INT column, so amounts are whole
 * numbers. Validation rejects fractions rather than letting MySQL silently
 * truncate them.
 */
class PettyCashApiController extends Controller
{
    use GetsJwtToken;

    /** `PettyCashController::store()` moves bill images to public/pettycash. */
    private const BILL_DIRECTORY = 'pettycash';

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

    private function heads($subInstituteId)
    {
        return DB::table('petty_cash_master')
            ->select('id', 'title')
            ->where('sub_institute_id', $subInstituteId)
            ->orderBy('title')
            ->get();
    }

    private function entryQuery($subInstituteId)
    {
        return DB::table('petty_cash as p')
            ->join('petty_cash_master as pm', 'pm.id', '=', 'p.title_id')
            ->leftJoin('tbluser as u', function ($join) {
                $join->on('u.id', '=', 'p.user_id')->where('u.status', 1);
            })
            ->selectRaw("p.*, pm.title as title_name,
                DATE_FORMAT(p.created_on, '%Y-%m-%d') as bill_date,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) as user_name,
                IF(p.bill_image IS NULL OR p.bill_image = '', '',
                    CONCAT('/".self::BILL_DIRECTORY."/', p.bill_image)) as bill_image_url")
            ->where('p.sub_institute_id', $subInstituteId);
    }

    /**
     * Moves an uploaded bill where `PettyCashController::store()` puts it.
     *
     * The Blade version derives `file_type` from
     * `File::extension($image->getClientOriginalExtension())`, which always
     * yields an empty string because there is no dot in an extension. The real
     * extension is stored here instead.
     *
     * @return array{bill_image:string,file_size:int,file_type:string}|array{}
     */
    private function storeBill(Request $request, $userId)
    {
        if (! $request->hasFile('bill_image')) {
            return [];
        }

        $image = $request->file('bill_image');
        $extension = $image->getClientOriginalExtension();
        $fileName = $userId.'-'.time().'.'.$extension;
        $image->move(public_path('/'.self::BILL_DIRECTORY), $fileName);

        return [
            'bill_image' => $fileName,
            'file_size'  => $image->getSize(),
            'file_type'  => $extension,
        ];
    }

    // ---------------------------------------------------------------- heads

    /** GET api/petty-cash/heads */
    public function headIndex(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = $this->validateContext($request);
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'Success',
            'data'        => ['heads' => $this->heads($request->integer('sub_institute_id'))],
        ]);
    }

    /** POST api/petty-cash/heads */
    public function headStore(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'title'            => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $id = DB::table('petty_cash_master')->insertGetId([
            'sub_institute_id' => $request->integer('sub_institute_id'),
            'title'            => $request->input('title'),
        ]);

        return response()->json([
            'status_code' => 1,
            'message'     => 'Petty cash head added successfully.',
            'data'        => ['id' => $id],
        ]);
    }

    /** POST api/petty-cash/heads/{id} */
    public function headUpdate(Request $request, $id)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'title'            => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $updated = DB::table('petty_cash_master')
            ->where('id', $id)
            ->where('sub_institute_id', $request->integer('sub_institute_id'))
            ->update(['title' => $request->input('title')]);

        if (! $updated) {
            return $this->failed('Petty cash head not found.', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'Petty cash head updated successfully.',
            'data'        => ['id' => (int) $id],
        ]);
    }

    /**
     * POST api/petty-cash/heads/{id}/delete
     *
     * Refuses while entries still reference the head. The Blade `destroy()`
     * deletes unconditionally, leaving those entries orphaned — the entry list
     * inner-joins the master, so they silently disappear from every screen.
     */
    public function headDestroy(Request $request, $id)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = $this->validateContext($request);
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');

        $inUse = DB::table('petty_cash')
            ->where('title_id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->count();

        if ($inUse > 0) {
            return $this->failed(
                'This head is used by '.$inUse.' petty cash entry(s). Reassign or delete them first.'
            );
        }

        $deleted = DB::table('petty_cash_master')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->delete();

        if (! $deleted) {
            return $this->failed('Petty cash head not found.', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'Petty cash head deleted successfully.',
            'data'        => ['id' => (int) $id],
        ]);
    }

    // -------------------------------------------------------------- entries

    /** GET api/petty-cash */
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

        return response()->json([
            'status_code' => 1,
            'message'     => 'Success',
            'data'        => [
                'entries' => $this->entryQuery($subInstituteId)->orderByDesc('p.id')->get(),
                'heads'   => $this->heads($subInstituteId),
            ],
        ]);
    }

    /**
     * GET api/petty-cash/report
     *
     * Same filters as `PettyCashReportController::getpettycashreport()`.
     * The Blade version compares the `created_on` TIMESTAMP against bare dates,
     * so entries recorded later on the to-date fall outside the range; the
     * comparison is done on the date part here so a full day is included.
     */
    public function report(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), [
            'sub_institute_id' => 'required|integer',
            'from_date'        => 'required|date',
            'to_date'          => 'required|date',
            'title_id'         => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');

        $entries = $this->entryQuery($subInstituteId)
            ->whereDate('p.created_on', '>=', $request->input('from_date'))
            ->whereDate('p.created_on', '<=', $request->input('to_date'))
            ->when($request->input('title_id'), function ($query, $titleId) {
                $query->where('p.title_id', $titleId);
            })
            ->orderBy('p.created_on')
            ->get();

        return response()->json([
            'status_code' => 1,
            'message'     => 'Success',
            'data'        => [
                'entries' => $entries,
                'heads'   => $this->heads($subInstituteId),
                'total'   => (int) $entries->sum('amount'),
            ],
        ]);
    }

    /** GET api/petty-cash/{id} */
    public function show(Request $request, $id)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = $this->validateContext($request);
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $entry = $this->entryQuery($request->integer('sub_institute_id'))
            ->where('p.id', $id)
            ->first();

        if (! $entry) {
            return $this->failed('Petty cash entry not found.', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'Success',
            'data'        => ['entry' => $entry],
        ]);
    }

    private function entryRules()
    {
        return [
            'sub_institute_id' => 'required|integer',
            'user_id'          => 'required|integer',
            'title_id'         => 'required|integer',
            'amount'           => 'required|integer|min:0',
            'description'      => 'nullable|string|max:255',
            'created_on'       => 'required|date',
        ];
    }

    /** POST api/petty-cash */
    public function store(Request $request)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), $this->entryRules());
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');
        $userId = $request->integer('user_id');

        if (! $this->headBelongsToInstitute($request->integer('title_id'), $subInstituteId)) {
            return $this->failed('The selected petty cash head does not belong to this institute.');
        }

        $entry = array_merge([
            'title_id'         => $request->integer('title_id'),
            'description'      => $request->input('description'),
            'amount'           => $request->integer('amount'),
            'created_on'       => $request->input('created_on'),
            'user_id'          => $userId,
            'sub_institute_id' => $subInstituteId,
        ], $this->storeBill($request, $userId));

        $id = DB::table('petty_cash')->insertGetId($entry);

        return response()->json([
            'status_code' => 1,
            'message'     => 'Petty cash entry added successfully.',
            'data'        => ['id' => $id],
        ]);
    }

    /**
     * POST api/petty-cash/{id}
     *
     * Updates `petty_cash`. This is the fix for
     * `PettyCashController::update()`, which updates `petty_cash_master`
     * instead and so corrupts an expense head.
     */
    public function update(Request $request, $id)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = Validator::make($request->all(), $this->entryRules());
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $subInstituteId = $request->integer('sub_institute_id');
        $userId = $request->integer('user_id');

        $existing = DB::table('petty_cash')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->first();

        if (! $existing) {
            return $this->failed('Petty cash entry not found.', 404);
        }

        if (! $this->headBelongsToInstitute($request->integer('title_id'), $subInstituteId)) {
            return $this->failed('The selected petty cash head does not belong to this institute.');
        }

        // `user_id` records who booked the expense and is left as it was.
        $entry = array_merge([
            'title_id'    => $request->integer('title_id'),
            'description' => $request->input('description'),
            'amount'      => $request->integer('amount'),
            'created_on'  => $request->input('created_on'),
        ], $this->storeBill($request, $userId));

        DB::table('petty_cash')
            ->where('id', $id)
            ->where('sub_institute_id', $subInstituteId)
            ->update($entry);

        return response()->json([
            'status_code' => 1,
            'message'     => 'Petty cash entry updated successfully.',
            'data'        => ['id' => (int) $id],
        ]);
    }

    /** POST api/petty-cash/{id}/delete */
    public function destroy(Request $request, $id)
    {
        if ($authResponse = $this->authenticate()) {
            return $authResponse;
        }

        $validator = $this->validateContext($request);
        if ($validator->fails()) {
            return $this->failed($validator->messages()->first());
        }

        $deleted = DB::table('petty_cash')
            ->where('id', $id)
            ->where('sub_institute_id', $request->integer('sub_institute_id'))
            ->delete();

        if (! $deleted) {
            return $this->failed('Petty cash entry not found.', 404);
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'Petty cash entry deleted successfully.',
            'data'        => ['id' => (int) $id],
        ]);
    }

    private function headBelongsToInstitute($titleId, $subInstituteId)
    {
        return DB::table('petty_cash_master')
            ->where('id', $titleId)
            ->where('sub_institute_id', $subInstituteId)
            ->exists();
    }
}
