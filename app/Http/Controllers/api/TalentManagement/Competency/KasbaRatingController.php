<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\Concerns\RequiresTalentAdmin;
use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\KasbaRatingController`.
 *
 * Backs the "Rate KASBA items" panel embedded in the Employee Profiles screen
 * (`KasbaRatingPanel` / `kasbaRatingService`). As-is port: same three
 * endpoints, same query logic, same validation, same response shapes. Only
 * the identity/tenant resolution layer changed - see
 * `ResolvesCompetencyContext::competencyContext()` (session-based, matching
 * every other ported Competency Management controller) in place of the
 * source's Sanctum `profile:admin,hr` middleware + `resolveApiIdentity()`.
 *
 * This controller was NOT in the original port scope (it lives in a separate
 * G2G service file, `services/competency/kasba-rating.ts`, alongside but
 * distinct from `services/competency/employee-profiles.ts`) and was added
 * once the frontend's 404 against `/api/competency/kasba-rating` surfaced the
 * gap. `competency`, `competency_kasba_item` and `competency_kasba_rating`
 * already existed in this schema (created for Task Management's "What this
 * task builds" competency mapping) but were missing the columns this
 * controller's queries need; `jobrole_competency_map` did not exist at all.
 * See `2026_08_21_130000_add_kasba_rating_support_columns.php` for the
 * additive schema changes.
 */
class KasbaRatingController extends Controller
{
    use ResolvesCompetencyContext;
    use RequiresTalentAdmin;

    /** 1..5. Zero is deliberately not a rating - it would collide with "unrated". */
    private const MIN = 1;
    private const MAX = 5;

    /**
     * GET /competency/kasba-rating?user_id=N - what this person can be rated
     * on, with any rating they already have. An unrated item comes back with
     * `rating: null` rather than being omitted, so the UI can render it as a
     * blank to fill in rather than silently dropping it.
     */
    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $sid     = (int) $context['sub_institute_id'];
        $subject = $request->integer('user_id');

        $user = DB::table('tbluser')
            ->where('id', $subject)->where('sub_institute_id', $sid)
            ->first(['id', 'jobtitle_id']);

        if (!$user) {
            return response()->json(['status' => 0, 'message' => 'Employee not found.'], 404);
        }

        if (!$user->jobtitle_id) {
            return response()->json([
                'status' => 1,
                'data'   => ['user_id' => $subject, 'jobrole_id' => null, 'items' => []],
                'empty_is_expected' => true,
                'empty_reason'      => 'This employee has no job role, so no competencies are required of them yet.',
                'rating_range'      => ['min' => self::MIN, 'max' => self::MAX],
            ]);
        }

        $items = DB::table('jobrole_competency_map as m')
            ->join('competency_kasba_item as k', 'k.competency_id', '=', 'm.competency_id')
            ->leftJoin('competency as c', 'c.id', '=', 'm.competency_id')
            ->leftJoin('competency_kasba_rating as r', function ($j) use ($subject) {
                $j->on('r.kasba_item_id', '=', 'k.id')->where('r.user_id', '=', $subject);
            })
            ->where('m.sub_institute_id', $sid)
            ->where('k.sub_institute_id', $sid)
            ->where('m.jobrole_id', $user->jobtitle_id)
            ->orderBy('c.name')->orderBy('k.kasba_type')->orderBy('k.item_label')
            ->get([
                'k.id as kasba_item_id',
                'k.kasba_type',
                'k.item_label',
                'k.weight',
                'm.competency_id',
                'c.name as competency_name',
                'm.required_proficiency',
                'm.is_mandatory',
                'r.rating',
                'r.note',
                'r.rated_at',
            ]);

        return response()->json([
            'status' => 1,
            'data'   => [
                'user_id'    => $subject,
                'jobrole_id' => (int) $user->jobtitle_id,
                'items'      => $items,
                'rated'      => $items->whereNotNull('rating')->count(),
                'total'      => $items->count(),
            ],
            'empty_is_expected' => $items->isEmpty(),
            'empty_reason'      => $items->isEmpty()
                ? 'This job role has no competencies mapped to it yet. Add them in Role Requirements.'
                : null,
            'rating_range' => ['min' => self::MIN, 'max' => self::MAX],
        ]);
    }

    /**
     * POST /competency/kasba-rating - rate one KASBA item for one employee.
     * Idempotent on (tenant, user, item): rating the same item again UPDATES
     * the row rather than adding a second one.
     */
    public function store(Request $request)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'kasba_item_id' => 'required|integer',
            'user_id'       => 'required|integer',
            'rating'        => 'required|integer|min:' . self::MIN . '|max:' . self::MAX,
            'note'          => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $sid     = (int) $context['sub_institute_id'];
        $actor   = (int) ($context['user_id'] ?? 0);
        $itemId  = $request->integer('kasba_item_id');
        $subject = $request->integer('user_id');

        $itemOk = DB::table('competency_kasba_item')
            ->where('id', $itemId)->where('sub_institute_id', $sid)->exists();
        if (!$itemOk) {
            return response()->json(['status' => 0, 'message' => 'Competency item not found.'], 404);
        }

        $userOk = DB::table('tbluser')
            ->where('id', $subject)->where('sub_institute_id', $sid)->exists();
        if (!$userOk) {
            return response()->json(['status' => 0, 'message' => 'Employee not found.'], 404);
        }

        DB::table('competency_kasba_rating')->updateOrInsert(
            ['sub_institute_id' => $sid, 'user_id' => $subject, 'kasba_item_id' => $itemId],
            [
                'rating'      => $request->integer('rating'),
                'assessor_id' => $actor ?: null,
                'source'      => 'manual',
                'note'        => $request->input('note'),
                'rated_at'    => now(),
                'updated_at'  => now(),
            ]
        );

        AuditLog::record([
            'module'      => 'competency_management',
            'action'      => 'kasba_rating.saved',
            'entity_type' => 'kasba_rating',
            'entity_id'   => $itemId,
            'new_values'  => [
                'user_id'       => $subject,
                'kasba_item_id' => $itemId,
                'rating'        => $request->integer('rating'),
                'note'          => $request->input('note'),
            ],
        ]);

        return response()->json([
            'status'  => 1,
            'message' => 'Rating saved.',
            'data'    => ['kasba_item_id' => $itemId, 'user_id' => $subject],
        ], 201);
    }

    /**
     * DELETE /competency/kasba-rating - remove a rating, which returns the
     * item to UNMEASURED (not to a rating of zero).
     */
    public function destroy(Request $request)
    {
        if ($response = $this->assertIsAdmin()) { return $response; }

        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'kasba_item_id' => 'required|integer',
            'user_id'       => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        $deleted = DB::table('competency_kasba_rating')
            ->where('sub_institute_id', (int) $context['sub_institute_id'])
            ->where('user_id', $request->integer('user_id'))
            ->where('kasba_item_id', $request->integer('kasba_item_id'))
            ->delete();

        if ($deleted) {
            AuditLog::record([
                'module'      => 'competency_management',
                'action'      => 'kasba_rating.deleted',
                'entity_type' => 'kasba_rating',
                'entity_id'   => $request->integer('kasba_item_id'),
                'new_values'  => [
                    'user_id'       => $request->integer('user_id'),
                    'kasba_item_id' => $request->integer('kasba_item_id'),
                ],
            ]);
        }

        return response()->json([
            'status'  => 1,
            'message' => $deleted ? 'Rating removed; the item is unmeasured again.' : 'No rating to remove.',
            'data'    => ['removed' => (bool) $deleted],
        ]);
    }
}
