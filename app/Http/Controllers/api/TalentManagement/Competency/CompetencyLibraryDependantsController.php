<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\LibraryDependantsController`.
 *
 * WHAT DEPENDS ON THIS LIBRARY ROW — the delete-impact check behind the
 * Capability Library's delete dialog: "deleting this affects N records"
 * instead of a silent orphan admission.
 *
 * THE COUNT IS BY KEY, not by matching the legacy text column, for the same
 * reason the source documents: a text-column count over/under-counts against
 * duplicate titles, and a user shown the honest key-based number can act on
 * it. The tenant condition lives inside each join's ON-equivalent (a
 * `where('sub_institute_id', $tenant)` alongside the FK match), not as a
 * trailing filter, matching the source's habit.
 *
 * Every dependant table is checked with `Schema::hasTable()` /
 * `Schema::hasColumn()` before being queried — this is the source's own
 * defensive pattern (a mapping table "not in this install" is skipped, not an
 * error), so no adaptation was needed for `course_jobrole_map`, which does
 * not exist in this target: it is simply skipped, exactly as the source
 * would skip it on an install that lacked it. `s_users_skills`,
 * `competency_kasba_item`, `s_user_skill_jobrole`, `s_user_jobrole`,
 * `s_user_jobrole_task` and `jobrole_competency_map` all already exist in
 * this target with the columns this controller reads.
 *
 * Source used `App\Http\Controllers\Api\Concerns\ResolvesApiIdentity`
 * (Sanctum token identity); this port uses this namespace's own
 * `ResolvesCompetencyContext::competencyContext()` instead, per this
 * session's established convention - identical `sub_institute_id` shape, no
 * behaviour change.
 */
class CompetencyLibraryDependantsController extends Controller
{
    use ResolvesCompetencyContext;

    /**
     * Where each library kind is depended upon.
     *
     * `key` is the FK column; `text` is the legacy name column that still
     * exists beside it. `text` is used ONLY to compute the divergence, never
     * to produce the headline number.
     *
     * @var array<string, array{table:string, title:string, rows:array<int,array{table:string,key:string,text:?string,label:string}>}>
     */
    private const DEPENDANTS = [
        'skill' => [
            'table' => 's_users_skills',
            'title' => 'title',
            'rows'  => [
                ['table' => 's_user_skill_jobrole', 'key' => 'skill_id', 'text' => 'skill', 'label' => 'job role mappings'],
                ['table' => 'competency_kasba_item', 'key' => 'item_id', 'text' => null, 'label' => 'competency items'],
            ],
        ],
        'jobrole' => [
            'table' => 's_user_jobrole',
            'title' => 'jobrole',
            'rows'  => [
                ['table' => 's_user_skill_jobrole', 'key' => 'jobrole_id', 'text' => 'jobrole', 'label' => 'skill mappings'],
                ['table' => 's_user_jobrole_task', 'key' => 'jobrole_id', 'text' => 'jobrole', 'label' => 'task mappings'],
                ['table' => 'jobrole_competency_map', 'key' => 'jobrole_id', 'text' => null, 'label' => 'competency requirements'],
                ['table' => 'course_jobrole_map', 'key' => 'jobrole_id', 'text' => null, 'label' => 'course mappings'],
            ],
        ],
    ];

    /** GET /api/competency/library/dependants?kind=&id= */
    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $data = $request->validate([
            'kind' => 'required|string|max:32',
            'id'   => 'required|integer|min:1',
        ]);

        $kind = $data['kind'];
        if (!isset(self::DEPENDANTS[$kind])) {
            return response()->json(['status' => 0, 'message' => "No dependant map for '$kind'."], 422);
        }

        $spec   = self::DEPENDANTS[$kind];
        $tenant = $context['sub_institute_id'];

        // The row must be the caller's own before anything is counted for it.
        $subject = DB::table($spec['table'])
            ->where('id', $data['id'])
            ->where('sub_institute_id', $tenant)
            ->first(['id', $spec['title']]);

        if (!$subject) {
            return response()->json(['status' => 0, 'message' => 'Not found in your organisation.'], 404);
        }

        $titleValue = $subject->{$spec['title']} ?? null;

        $breakdown = [];
        $total = 0;
        $textTotal = 0;
        $anyTextComparable = false;

        foreach ($spec['rows'] as $dep) {
            if (!Schema::hasTable($dep['table']) || !Schema::hasColumn($dep['table'], $dep['key'])) {
                continue;   // the mapping table is not in this install
            }

            $byKey = DB::table($dep['table'])
                ->where($dep['key'], $subject->id)
                ->where('sub_institute_id', $tenant)
                ->count();

            $total += $byKey;
            $breakdown[] = ['label' => $dep['label'], 'count' => $byKey];

            // The divergence, computed only where a legacy text column survives.
            if ($dep['text'] !== null && $titleValue !== null && Schema::hasColumn($dep['table'], $dep['text'])) {
                $anyTextComparable = true;
                $textTotal += DB::table($dep['table'])
                    ->where($dep['text'], $titleValue)
                    ->where('sub_institute_id', $tenant)
                    ->count();
            } else {
                $textTotal += $byKey;
            }
        }

        $divergence = $anyTextComparable ? $textTotal - $total : 0;

        return response()->json([
            'status' => 1,
            'data'   => [
                'total'     => $total,
                'basis'     => 'key',
                'breakdown' => $breakdown,
                // Present ONLY when it is not zero. Null means "nothing to say",
                // which is what the dialog needs to decide whether to mention it.
                'divergence' => $divergence !== 0 ? [
                    'by_text'    => $textTotal,
                    'difference' => $divergence,
                    'reason'     => 'Rows sharing this name resolve differently where the product still joins by name.',
                ] : null,
            ],
        ]);
    }
}
