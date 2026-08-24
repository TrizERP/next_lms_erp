<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\Controller;
use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\CompetencyLibraryCrudController`.
 *
 * Confirmed as the correct source (not the legacy skill-library controller)
 * by reading the G2G frontend's `services/competency/library.ts`
 * (`BASE = '/competency-library'`).
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * WHY THIS EXISTS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * `cm-competency-library.tsx` is 1,799 lines of good screen: list, filters,
 * sorting, detail drawer, import, taxonomy editing. It has always been fed by
 * a skill-library controller on `s_users_skills` - a SKILL table wearing
 * competency labels.
 *
 * `cm-competency-definitions.tsx` reads the REAL competency tables but is 186
 * lines: a list and a create form, nothing else.
 *
 *     ONE SCREEN HAD THE INTERFACE. THE OTHER HAD THE DATA.
 *
 * So this controller serves the SAME RESPONSE SHAPE the rich screen already
 * expects, from `competency` and `competency_kasba_item`. The screen keeps every
 * feature and starts showing competencies. No component is rewritten, and the
 * skill endpoints are left untouched so skill management is not orphaned.
 *
 * Table note - `competency` and `competency_kasba_item` already exist in this
 * target (created by `2026_08_20_101000_create_competency_task_map_tables.php`
 * for Task Management's competency mapping, extended by
 * `2026_08_21_130000_add_kasba_rating_support_columns.php` for the ported
 * `KasbaRatingController`) but were missing the columns this controller's
 * queries need (`description`, `competency_type`, `framework_id`, `status`,
 * `updated_by` on `competency`; `item_id` on `competency_kasba_item`) - added
 * by this port's `2026_08_24_090300_add_competency_library_crud_columns.php`
 * migration. `s_competency_frameworks` (the `framework_id` link target) and
 * `jobrole_competency_map` (read by `destroy()`) already existed.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * FIELDS THE SKILL TABLE HAD AND A COMPETENCY DOES NOT
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Returned as NULL rather than invented or borrowed:
 *
 *   approve_status            there is a competency_approvals table, but it is
 *                             empty and its workflow is unbuilt. Reporting
 *                             "Approved" for everything would be a claim nobody
 *                             made.
 *   the eight detail columns  free-text skill fields with no competency
 *                             equivalent. NULL, not blank strings: absent and
 *                             empty are different answers.
 *
 * WHAT A COMPETENCY HAS THAT A SKILL NEVER DID is returned as well: its KASBA
 * items, counted by dimension. That is the whole point of the model and no skill
 * row could express it.
 *
 * ═══════════════════════════════════════════════════════════════════════════
 * DEVIATION FROM G2G: category / sub_category / department ARE PERSISTED
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * In G2G itself, `category`/`sub_category`/`department` are dead form
 * fields - its own `store()`/`update()` never write them (confirmed by
 * reading that controller directly); `category` is always overwritten with
 * the linked framework's name and the other two are hardcoded null. This
 * project's user hit that exact confusion (typed a category, it never
 * saved) and explicitly asked for it to be corrected rather than preserved
 * as-is, so - unlike the rest of this port - these three columns were added
 * to `competency` (`2026_08_24_140000_add_taxonomy_columns_to_competency.php`)
 * and are read/written for real below.
 */
class CompetencyLibraryCrudController extends Controller
{
    use ResolvesCompetencyContext;

    private const SORTABLE = [
        'title'           => 'c.name',
        // 'category' displayed to the user is always the linked framework's
        // name (see shape()), never the raw `c.category` column - so sorting
        // must follow the same derivation or "sort by Category" would order
        // by a column nobody sees. Reverted from 'c.category' back to
        // 'f.name' to match that.
        'category'        => 'f.name',
        'competency_type' => 'c.competency_type',
        'updated_at'      => 'c.updated_at',
        'created_at'      => 'c.created_at',
    ];

    /** GET /competency-library/competency-list */
    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $sid     = (int) $context['sub_institute_id'];
        $perPage = min(max((int) $request->input('per_page', 25), 1), 200);
        $page    = max((int) $request->input('page', 1), 1);
        $sort    = self::SORTABLE[$request->input('sort_by', 'updated_at')] ?? 'c.updated_at';
        $dir     = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $base = fn () => $this->libraryQuery($sid, $request);

        $total = $base()->count();

        $rows = $base()->orderBy($sort, $dir)
            ->forPage($page, $perPage)
            ->get([
                'c.id', 'c.name', 'c.code', 'c.description', 'c.competency_type',
                'c.criticality', 'c.status', 'c.framework_id', 'c.department', 'c.created_by',
                'c.created_at', 'c.updated_at', 'f.name as framework_name',
            ]);

        // Item counts in one query rather than N - a library page listing 200
        // competencies must not fire 200 follow-ups.
        $counts = DB::table('competency_kasba_item')
            ->whereIn('competency_id', $rows->pluck('id'))
            ->selectRaw('competency_id, COUNT(*) n')
            ->groupBy('competency_id')->pluck('n', 'competency_id');

        // Same one-query-not-N rule for approval status - see approveStatuses().
        $statuses = $this->approveStatuses($sid, $rows->pluck('id')->all());

        return response()->json([
            'status'  => true,
            'message' => 'Success',
            'data'    => $rows->map(fn ($r) => $this->shape($r, (int) ($counts[$r->id] ?? 0), $statuses[$r->id] ?? null))->values(),
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
            ],
            'empty_is_expected' => $total === 0,
            'empty_reason' => $total === 0
                ? 'No competencies have been created for your organisation yet.'
                : null,
        ]);
    }

    /**
     * The filter set shared by index() and exportRows() - export must return
     * every row the current list filters would show, never a different set.
     */
    private function libraryQuery(int $sid, Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        return DB::table('competency as c')
            ->leftJoin('s_competency_frameworks as f', 'f.id', '=', 'c.framework_id')
            ->where('c.sub_institute_id', $sid)
            ->whereNull('c.deleted_at')
            ->when($search !== '', function ($w) use ($search) {
                $w->where(function ($x) use ($search) {
                    $x->where('c.name', 'like', "%{$search}%")
                      ->orWhere('c.code', 'like', "%{$search}%")
                      ->orWhere('c.description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('competency_type'), fn ($w) => $w->where('c.competency_type', $request->input('competency_type')))
            ->when($request->filled('framework_id'), fn ($w) => $w->where('c.framework_id', $request->integer('framework_id')));
    }

    /**
     * Latest `s_competency_approvals` status per competency id, one query for
     * the whole batch. Competencies with no approval record at all are simply
     * absent from the returned map - the frontend's own `statusLabel()`
     * already falls back to "Pending" for that case, so shape() need only
     * return null rather than guess a status nobody set.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function approveStatuses(int $sid, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return DB::table('s_competency_approvals')
            ->where('sub_institute_id', $sid)
            ->where('subject_type', 'competency')
            ->whereIn('subject_id', $ids)
            ->whereNull('deleted_at')
            // Latest row per subject: order so the first row PHP sees per id,
            // when reduced below, is the newest one.
            ->orderByDesc('id')
            ->get(['subject_id', 'status'])
            ->reduce(function (array $carry, $row) {
                $carry[(int) $row->subject_id] ??= $row->status;
                return $carry;
            }, []);
    }

    /** GET /competency-library/competency/{id} */
    public function show(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $sid = (int) $context['sub_institute_id'];

        $row = DB::table('competency as c')
            ->leftJoin('s_competency_frameworks as f', 'f.id', '=', 'c.framework_id')
            ->where('c.id', (int) $id)->where('c.sub_institute_id', $sid)->whereNull('c.deleted_at')
            ->first([
                'c.id', 'c.name', 'c.code', 'c.description', 'c.competency_type',
                'c.criticality', 'c.status', 'c.framework_id', 'c.department', 'c.created_by',
                'c.created_at', 'c.updated_at', 'f.name as framework_name',
            ]);

        if (!$row) {
            return response()->json(['status' => false, 'message' => 'Competency not found.'], 404);
        }

        $items = DB::table('competency_kasba_item')
            ->where('competency_id', $row->id)->where('sub_institute_id', $sid)
            ->orderBy('kasba_type')->orderBy('item_label')
            ->get(['id', 'kasba_type', 'item_id', 'item_label', 'weight']);

        $status = $this->approveStatuses($sid, [$row->id])[$row->id] ?? null;
        $shaped = $this->shape($row, $items->count(), $status);
        // THE PART NO SKILL ROW COULD CARRY.
        $shaped['items'] = $items;
        $shaped['items_by_type'] = $items->groupBy('kasba_type')->map->count();

        return response()->json(['status' => true, 'message' => 'Success', 'data' => $shaped]);
    }

    /** POST /competency-library/competency */
    public function store(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:191',
            'code'            => 'nullable|string|max:64',
            'description'     => 'nullable|string',
            'competency_type' => 'nullable|string|max:64',
            'criticality'     => 'nullable|string|max:32',
            'framework_id'    => 'nullable|integer',
            // The department field, directly editable in the form (distinct
            // from `category`, which is derived from framework_id via join
            // and never a raw form value - see the class doc-block).
            'department'      => 'nullable|string|max:191',
            // THE KASBA ITEMS, OPTIONAL ON CREATE BUT ACCEPTED HERE.
            // Without these the library could only make an empty competency -
            // a heading with nothing measurable under it. The Definitions screen
            // could build them and the library could not, which is why the two
            // screens could not simply be merged by renaming a menu.
            'items'              => 'nullable|array',
            'items.*.kasba_type' => 'required_with:items|string|in:knowledge,ability,skill,behaviour,attitude',
            'items.*.item_id'    => 'nullable|integer',
            'items.*.item_label' => 'nullable|string|max:191',
            'items.*.weight'     => 'nullable|numeric|min:0|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $sid   = (int) $context['sub_institute_id'];
        $actor = (int) $context['user_id'];

        // Same tenant check as the definitions controller: a bare exists rule
        // would accept another organisation's framework id.
        $fw = $request->input('framework_id') !== null ? (int) $request->input('framework_id') : null;
        if ($fw !== null && !DB::table('s_competency_frameworks')->where('id', $fw)->where('sub_institute_id', $sid)->exists()) {
            return response()->json(['status' => false, 'message' => 'That framework does not exist in your organisation.'], 404);
        }

        $code = $request->input('code');
        if ($code && DB::table('competency')->where('sub_institute_id', $sid)->where('code', $code)->exists()) {
            return response()->json(['status' => false, 'message' => 'That competency code is already used in this organisation.'], 422);
        }

        /*
         * competency.code is NOT NULL, and the library form has no Code field.
         *
         * So every create from that screen inserted NULL and died with
         * "Column 'code' cannot be null" - a 500, not a validation message.
         * Creating a competency from the Competency Library has never worked.
         *
         * A code is generated from the name rather than made required: it is a
         * human reference, and asking every author to invent a unique string
         * before they can save is a worse screen than deriving one they can
         * edit later.
         */
        if (!$code) {
            $code = $this->generateCode($sid, (string) $request->input('name'));
        }

        $items = $request->input('items', []);
        $id = null;
        $written = 0;

        // ONE TRANSACTION. A competency that lands without its items is worse
        // than one that fails outright: it looks authored and measures nothing.
        DB::transaction(function () use (&$id, &$written, $sid, $fw, $code, $actor, $request, $items) {
            $id = DB::table('competency')->insertGetId([
                'sub_institute_id' => $sid,
                'framework_id'     => $fw,
                'code'             => $code,
                'name'             => $request->input('name'),
                'description'      => $request->input('description'),
                'competency_type'  => $request->input('competency_type'),
                'criticality'      => $request->input('criticality'),
                'department'       => $request->input('department'),
                'status'           => 1,
                'created_by'       => $actor,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            foreach ($items as $item) {
                // item_id is the TARGET - a row in a canonical table. item_label
                // is the HOLDING - free text for something not yet canonical.
                // Both are permitted; neither is invented from the other.
                DB::table('competency_kasba_item')->insert([
                    'sub_institute_id' => $sid,
                    'competency_id'    => $id,
                    'kasba_type'       => $item['kasba_type'],
                    'item_id'          => isset($item['item_id']) ? (int) $item['item_id'] : null,
                    'item_label'       => $item['item_label'] ?? null,
                    'weight'           => $item['weight'] ?? 1,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                $written++;
            }
        });

        return response()->json([
            'status'  => true,
            'message' => $written
                ? sprintf('Competency created with %d capability item(s).', $written)
                : 'Competency created.',
            'data'    => ['id' => $id, 'items_created' => $written],
            // Said plainly rather than left for the user to discover: a
            // competency with no items cannot be rated against.
            'next_step' => $written
                ? null
                : 'Add capability items to this competency so people can be rated against it.',
        ], 201);
    }

    /**
     * A tenant-unique code derived from the competency's name.
     *
     * Shape follows what is already in the table - uppercase, hyphenated, short
     * - so a generated code sits beside the curated ones without looking alien.
     * The numeric suffix only appears when it has to, and the unique index on
     * (sub_institute_id, code) is what it is defending.
     */
    private function generateCode(int $sid, string $name): string
    {
        $stem = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', trim($name)) ?: 'COMPETENCY');
        $stem = trim($stem, '-');
        // Leave room for a "-99" suffix inside varchar(64).
        $stem = substr($stem, 0, 60) ?: 'COMPETENCY';

        $candidate = $stem;
        $suffix    = 1;

        while (DB::table('competency')->where('sub_institute_id', $sid)->where('code', $candidate)->exists()) {
            $candidate = $stem . '-' . (++$suffix);
        }

        return $candidate;
    }

    /** PUT /competency-library/competency/{id} */
    public function update(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $sid = (int) $context['sub_institute_id'];

        $exists = DB::table('competency')->where('id', (int) $id)
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')->exists();

        if (!$exists) {
            return response()->json(['status' => false, 'message' => 'Competency not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'            => 'sometimes|required|string|max:191',
            'code'            => 'nullable|string|max:64',
            'description'     => 'nullable|string',
            'competency_type' => 'nullable|string|max:64',
            'criticality'     => 'nullable|string|max:32',
            'framework_id'    => 'nullable|integer',
            'department'      => 'nullable|string|max:191',
            'status'          => 'nullable|integer',
            // THE COMPOSITION, EDITABLE AT LAST.
            //
            // store() has always accepted items; update() did not, so once a
            // competency existed its KASBA bundle was frozen - there was no
            // route and no screen that could add, correct or remove one.
            //
            // Omitting `items` leaves the composition untouched. Sending it
            // REPLACES it, the same sync semantics this port's role-mapping
            // writer uses, so the client sends the state it wants rather than
            // a diff.
            'items'              => 'nullable|array',
            'items.*.kasba_type' => 'required_with:items|string|in:knowledge,ability,skill,behaviour,attitude',
            'items.*.item_id'    => 'nullable|integer',
            'items.*.item_label' => 'nullable|string|max:191',
            'items.*.weight'     => 'nullable|numeric|min:0|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $update = array_filter([
            'name'            => $request->input('name'),
            'code'            => $request->input('code'),
            'description'     => $request->input('description'),
            'competency_type' => $request->input('competency_type'),
            'criticality'     => $request->input('criticality'),
            // THE FIX: department is now a real, directly-editable column -
            // previously never written here (or in store()), so a department
            // typed in the form silently vanished on save. See shape()'s
            // 'department' key and the class doc-block's "DEVIATION FROM G2G".
            'department'      => $request->input('department'),
            'status'          => $request->input('status'),
        ], fn ($v) => $v !== null);

        if ($request->has('framework_id')) {
            $fw = $request->input('framework_id') !== null ? (int) $request->input('framework_id') : null;
            if ($fw !== null && !DB::table('s_competency_frameworks')->where('id', $fw)->where('sub_institute_id', $sid)->exists()) {
                return response()->json(['status' => false, 'message' => 'That framework does not exist in your organisation.'], 404);
            }
            $update['framework_id'] = $fw;
        }

        $update['updated_by'] = (int) $context['user_id'];
        $update['updated_at'] = now();

        $itemsGiven = $request->has('items');
        $items      = $request->input('items', []);
        $written    = 0;

        // One transaction: a competency whose row updated but whose composition
        // did not is a competency that measures something other than it claims.
        DB::transaction(function () use ($id, $sid, $update, $itemsGiven, $items, &$written) {
            DB::table('competency')->where('id', (int) $id)->where('sub_institute_id', $sid)->update($update);

            if (!$itemsGiven) {
                return;
            }

            // Replace, tenant-scoped. competency_kasba_item has no deleted_at,
            // so this is a hard delete by design - an item removed from a
            // competency was never part of it, rather than retired from it.
            DB::table('competency_kasba_item')
                ->where('competency_id', (int) $id)
                ->where('sub_institute_id', $sid)
                ->delete();

            foreach ($items as $item) {
                DB::table('competency_kasba_item')->insert([
                    'sub_institute_id' => $sid,
                    'competency_id'    => (int) $id,
                    'kasba_type'       => $item['kasba_type'],
                    // Same rule as store(): item_id is the resolved target,
                    // item_label the holding state. Neither is invented from
                    // the other.
                    'item_id'          => isset($item['item_id']) ? (int) $item['item_id'] : null,
                    'item_label'       => $item['item_label'] ?? null,
                    'weight'           => $item['weight'] ?? 1,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                $written++;
            }
        });

        return response()->json([
            'status'  => true,
            'message' => 'Competency updated.',
            'data'    => ['id' => (int) $id, 'items_written' => $itemsGiven ? $written : null],
        ]);
    }

    /** DELETE /competency-library/competency/{id} — SOFT, and it says what it kept. */
    public function destroy(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $sid = (int) $context['sub_institute_id'];

        $row = DB::table('competency')->where('id', (int) $id)
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')->first(['id']);

        if (!$row) {
            return response()->json(['status' => false, 'message' => 'Competency not found.'], 404);
        }

        // WHAT WOULD BE ORPHANED, COUNTED AND REPORTED. A competency in use by a
        // job role is not a free deletion, and the caller should know before the
        // gap analysis quietly loses a requirement.
        $roles = DB::table('jobrole_competency_map')->where('competency_id', $row->id)->count();
        $items = DB::table('competency_kasba_item')->where('competency_id', $row->id)->count();

        DB::table('competency')->where('id', $row->id)->update([
            'deleted_at' => now(),
            'deleted_by' => (int) $context['user_id'],
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Competency removed.',
            // SOFT DELETE: the row is retained and can be restored. Its items and
            // role mappings are NOT removed - deleting them would destroy history
            // that a restore could not rebuild.
            'retained' => ['kasba_items' => $items, 'jobrole_mappings' => $roles],
        ]);
    }

    /**
     * The shape the existing screen expects, from a competency row.
     *
     * Fields with no competency equivalent are NULL, never a placeholder string.
     * Absent and empty are different answers, and a screen that shows "-" for
     * both cannot tell a user which it is looking at.
     */
    private function shape($r, int $itemCount, ?string $approveStatus = null): array
    {
        return [
            'id'              => (int) $r->id,
            'name'            => $r->name,
            'code'            => $r->code,
            'description'     => $r->description,
            // The framework is what a competency is filed under - the honest
            // equivalent of the skill taxonomy's category.
            'category'        => $r->framework_name,
            'sub_category'    => null,
            'competency_type' => $r->competency_type,
            'proficiency_level' => $r->criticality,
            // Real, directly-editable column now (was hardcoded null) - see
            // store()/update() below, and the class doc-block's "DEVIATION
            // FROM G2G" note. This is the fix for the reported bug: a
            // department typed in the form was silently discarded.
            'department'      => $r->department ?? null,
            'department_id'   => null,
            'status'          => ((int) ($r->status ?? 1)) === 1 ? 'Active' : 'Inactive',
            // Latest s_competency_approvals row for this competency, batched by
            // the caller (see approveStatuses()) - null only when no approval
            // record exists at all, which the frontend's own statusLabel()
            // already reads as "Pending".
            'approve_status'  => $approveStatus,
            'owner'           => null,
            'created_at'      => $r->created_at,
            'updated_at'      => $r->updated_at,
            'created_by'      => $r->created_by !== null ? (int) $r->created_by : null,
            'framework_id'    => $r->framework_id !== null ? (int) $r->framework_id : null,
            // What no skill row could carry.
            'items_count'     => $itemCount,
        ];
    }

    /** GET /competency-library/competency/{id}/detail — the tabbed detail panel. */
    public function detail(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $sid = (int) $context['sub_institute_id'];

        $row = DB::table('competency as c')
            ->leftJoin('s_competency_frameworks as f', 'f.id', '=', 'c.framework_id')
            ->where('c.id', (int) $id)->where('c.sub_institute_id', $sid)->whereNull('c.deleted_at')
            ->first(['c.*', 'f.name as framework_name', 'f.status as framework_status']);

        if (!$row) {
            return response()->json(['status' => false, 'message' => 'Competency not found.'], 404);
        }

        // ASSOCIATIONS - ROLES. jobrole_competency_map.jobrole_id is a genuine
        // FK to s_user_jobrole.id (per its own migration comment), unlike the
        // name-string matching CapabilityLibraryController/RoleMappingController
        // use against s_user_skill_jobrole for the unrelated skill catalog - so
        // this joins on the id column directly rather than by name.
        $roleRows = DB::table('jobrole_competency_map as m')
            ->leftJoin('s_user_jobrole as jr', 'jr.id', '=', 'm.jobrole_id')
            ->where('m.competency_id', $row->id)
            ->where('m.sub_institute_id', $sid)
            ->get(['jr.jobrole as jobrole_name', 'jr.department', 'm.required_proficiency']);

        // jobrole_competency_map.required_proficiency is a BOOLEAN column in
        // this schema ("is this competency required for the role"), not a
        // numeric level - so the string passed through here is "1"/"0", never
        // a "Level N" label. Mapped straight through rather than invented.
        $roles = $roleRows->map(fn ($r) => [
            'jobrole'           => $r->jobrole_name ?? 'Unknown role',
            'proficiency_level' => $r->required_proficiency !== null ? (string) (int) $r->required_proficiency : null,
        ])->values()->all();

        $topRoles = $roleRows->sortByDesc(fn ($r) => (int) ($r->required_proficiency ?? 0))
            ->take(5)
            ->map(fn ($r) => [
                'jobrole'           => $r->jobrole_name ?? 'Unknown role',
                'proficiency_level' => $r->required_proficiency !== null ? (string) (int) $r->required_proficiency : null,
                'department'        => $r->department,
            ])->values()->all();

        // ASSOCIATIONS - FRAMEWORKS. This target's competency has a single
        // `framework_id` link (not a many-to-many join table like G2G might
        // imply), so at most one row here - never invented as a list.
        $frameworks = [];
        if ($row->framework_id !== null) {
            $frameworks[] = [
                'id'                    => (int) $row->framework_id,
                'name'                  => $row->framework_name,
                'status'                => $row->framework_status,
                'required_proficiency'  => null,
            ];
        }

        // SUMMARY counts - each from the real table that already carries a
        // competency_id/kasba_item_id for this record; none invented.
        $itemIds = DB::table('competency_kasba_item')
            ->where('competency_id', $row->id)->where('sub_institute_id', $sid)
            ->pluck('id');

        $ratedEmployees = $itemIds->isEmpty() ? 0 : DB::table('competency_kasba_rating')
            ->whereIn('kasba_item_id', $itemIds)
            ->distinct('user_id')->count('user_id');

        $planCount = DB::table('s_competency_development_plans')
            ->where('competency_id', $row->id)->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')->count();

        $certCount = DB::table('s_competency_certification_requirements')
            ->where('competency_id', $row->id)->where('sub_institute_id', $sid)
            ->whereNull('deleted_at')->count();

        // PROFICIENCY - the tenant-global scale, same source/fallback rule as
        // CompetencyStudioController::proficiencyScale() (skill_id IS NULL
        // rows). A competency has no per-record scale link in this schema, so
        // scope is always 'global' here rather than invented as 'competency'.
        $levels = DB::table('s_proficiency_levels')
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')->whereNull('skill_id')
            ->orderByRaw('CAST(proficiency_type AS UNSIGNED)')
            ->get(['proficiency_type', 'proficiency_level', 'type_description', 'description']);

        $proficiencyLevels = $levels->map(fn ($l) => [
            'level'       => (int) $l->proficiency_type,
            'label'       => $l->proficiency_level,
            'name'        => $l->type_description,
            'description' => $l->description,
        ])->values()->all();

        // HISTORY - the real per-subject activity feed logCompetencyActivity()
        // writes to on every store()/update()/clone()/archive() call for this
        // competency. Approval decisions themselves (approve/reject notes) are
        // a separate trail the frontend fetches from
        // GET /competency/approvals/for/competency/{id} - not duplicated here.
        $history = DB::table('s_competency_activity_log')
            ->where('sub_institute_id', $sid)
            ->where('subject_type', 'competency')
            ->where('subject_id', $row->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['action', 'description', 'actor_name', 'created_at'])
            ->map(fn ($h) => [
                'action' => $h->description ?: $h->action,
                'by'     => $h->actor_name ?: 'System',
                'date'   => $h->created_at,
            ])->values()->all();

        $data = [
            'summary' => [
                'description'         => $row->description,
                // Same derivation as shape(): the framework name, not the raw
                // (never-written) `category` column, so this agrees with what
                // the list/detail header already show for this competency.
                'category'            => $row->framework_name,
                'sub_category'        => $row->sub_category,
                'competency_type'     => $row->competency_type,
                'status'              => ((int) ($row->status ?? 1)) === 1 ? 'Active' : 'Inactive',
                'role_count'          => $roleRows->count(),
                'framework_count'     => count($frameworks),
                'rated_employees'     => (int) $ratedEmployees,
                'plan_count'          => $planCount,
                'certification_count' => $certCount,
                // No assessment/learning-assignment/evidence table keys off this
                // real `competency` table in this target - reported as 0 rather
                // than a guessed non-zero count.
                'assessment_count'    => 0,
                'learning_count'      => 0,
                'evidence_count'      => 0,
            ],
            'top_roles' => $topRoles,
            'proficiency' => [
                'scale_label' => $proficiencyLevels !== [] ? 'Organisation proficiency scale' : null,
                'scope'       => 'global',
                'levels'      => $proficiencyLevels,
            ],
            'associations' => [
                'roles'           => $roles,
                'frameworks'      => $frameworks,
                'role_count'      => $roleRows->count(),
                'framework_count' => count($frameworks),
            ],
            // No free-text attachment columns exist on `competency` (unlike the
            // old s_users_skills-backed screen's business_links/learning_resources
            // etc.) - empty, not invented placeholder text.
            'attachments' => [],
            'history'     => $history,
        ];

        return response()->json(['status' => true, 'message' => 'Success', 'data' => $data]);
    }

    /** GET /competency-library/competency-export — every filtered row, no pagination. */
    public function exportRows(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $sid  = (int) $context['sub_institute_id'];
        $sort = self::SORTABLE[$request->input('sort_by', 'updated_at')] ?? 'c.updated_at';
        $dir  = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $rows = $this->libraryQuery($sid, $request)
            ->orderBy($sort, $dir)
            ->get([
                'c.id', 'c.name', 'c.code', 'c.description', 'c.competency_type',
                'c.criticality', 'c.status', 'c.framework_id', 'c.department', 'c.created_by',
                'c.created_at', 'c.updated_at', 'f.name as framework_name',
            ]);

        $counts = DB::table('competency_kasba_item')
            ->whereIn('competency_id', $rows->pluck('id'))
            ->selectRaw('competency_id, COUNT(*) n')
            ->groupBy('competency_id')->pluck('n', 'competency_id');

        $statuses = $this->approveStatuses($sid, $rows->pluck('id')->all());

        return response()->json([
            'status'  => true,
            'message' => 'Success',
            'data'    => $rows->map(fn ($r) => $this->shape($r, (int) ($counts[$r->id] ?? 0), $statuses[$r->id] ?? null))->values(),
        ]);
    }

    /** POST /competency-library/competency-import — bulk-create from client-parsed CSV rows. */
    public function importRows(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'rows'                     => 'required|array|min:1',
            'rows.*.name'              => 'nullable|string|max:191',
            'rows.*.description'       => 'nullable|string',
            'rows.*.category'          => 'nullable|string|max:191',
            'rows.*.sub_category'      => 'nullable|string|max:191',
            'rows.*.competency_type'   => 'nullable|string|max:64',
            'rows.*.proficiency_level' => 'nullable|string|max:32',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()], 422);
        }

        $sid   = (int) $context['sub_institute_id'];
        $actor = (int) $context['user_id'];

        $imported = 0;
        $skipped  = 0;
        $details  = [];

        // ONE TRANSACTION for the whole file: a half-imported sheet is a worse
        // outcome than a clearly reported all-or-partial result inside a
        // consistent state.
        DB::transaction(function () use ($request, $sid, $actor, &$imported, &$skipped, &$details) {
            foreach ($request->input('rows', []) as $i => $row) {
                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    $skipped++;
                    $details[] = ['row' => $i + 1, 'name' => (string) ($row['name'] ?? ''), 'reason' => 'Missing required "name" field.'];
                    continue;
                }

                // Same generated-code rule as store(): the import sheet has no
                // Code column, so every row is derived the same way a manual
                // create without one would be.
                $code = $this->generateCode($sid, $name);

                DB::table('competency')->insert([
                    'sub_institute_id' => $sid,
                    'name'             => $name,
                    'code'             => $code,
                    'description'      => $row['description'] ?? null,
                    'category'         => $row['category'] ?? null,
                    'sub_category'     => $row['sub_category'] ?? null,
                    'competency_type'  => $row['competency_type'] ?? null,
                    // proficiency_level on the import row maps to the same
                    // criticality column the list/detail shape() reads it back
                    // from - store()/update() make the identical mapping.
                    'criticality'      => $row['proficiency_level'] ?? null,
                    'status'           => 1,
                    'created_by'       => $actor,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);

                $imported++;
            }
        });

        return response()->json([
            'status'  => true,
            'message' => $imported
                ? sprintf('Imported %d competenc%s.', $imported, $imported === 1 ? 'y' : 'ies')
                : 'No rows were imported.',
            'data'    => ['imported' => $imported, 'skipped' => $skipped, 'details' => $details],
        ]);
    }

    /** POST /competency-library/competency/{id}/clone — duplicate as a new draft entry. */
    public function clone(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $sid   = (int) $context['sub_institute_id'];
        $actor = (int) $context['user_id'];

        $source = DB::table('competency')->where('id', (int) $id)
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')->first();

        if (!$source) {
            return response()->json(['status' => false, 'message' => 'Competency not found.'], 404);
        }

        $name = trim((string) $request->input('name')) !== '' ? $request->input('name') : ($source->name . ' (Copy)');
        $code = $this->generateCode($sid, $name);

        $newId   = null;
        $written = 0;

        // Same clone shape as CompetencyFrameworkController::clone(): copy the
        // metadata row, then copy every child row (here, competency_kasba_item
        // instead of s_competency_framework_items) into the new parent - one
        // transaction so a clone never lands with a composition half-copied.
        DB::transaction(function () use (&$newId, &$written, $sid, $source, $name, $code, $actor) {
            $newId = DB::table('competency')->insertGetId([
                'sub_institute_id' => $sid,
                'framework_id'     => $source->framework_id,
                'code'             => $code,
                'name'             => $name,
                'description'      => $source->description,
                'category'         => $source->category,
                'sub_category'     => $source->sub_category,
                'department'       => $source->department,
                'competency_type'  => $source->competency_type,
                'criticality'      => $source->criticality,
                'status'           => 1,
                'created_by'       => $actor,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $items = DB::table('competency_kasba_item')
                ->where('competency_id', $source->id)->where('sub_institute_id', $sid)
                ->get();

            foreach ($items as $item) {
                DB::table('competency_kasba_item')->insert([
                    'sub_institute_id' => $sid,
                    'competency_id'    => $newId,
                    'kasba_type'       => $item->kasba_type,
                    'item_id'          => $item->item_id,
                    'item_label'       => $item->item_label,
                    'weight'           => $item->weight,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                $written++;
            }
        });

        // A clone starts with NO row in s_competency_approvals - not a new
        // column, not an inserted "Pending" record. shape()/detail() already
        // return null approve_status when no approval row exists, and the
        // frontend's own statusLabel() fallback already reads that as
        // "Pending" - so a fresh clone reads correctly with nothing written.
        $this->logCompetencyActivity(
            $sid,
            $actor,
            'cloned_competency',
            'Cloned competency "' . $source->name . '" as "' . $name . '"',
            'competency',
            (int) $newId,
            $name
        );

        return response()->json([
            'status'  => true,
            'message' => $written
                ? sprintf('Competency cloned with %d capability item(s).', $written)
                : 'Competency cloned.',
            'data'    => ['id' => (int) $newId, 'name' => $name],
        ], 201);
    }

    /** PUT /competency-library/competency/{id}/archive — archive (Cancelled) or restore (Pending). */
    public function archive(Request $request, $id)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $sid   = (int) $context['sub_institute_id'];
        $actor = (int) $context['user_id'];

        $row = DB::table('competency')->where('id', (int) $id)
            ->where('sub_institute_id', $sid)->whereNull('deleted_at')->first(['id', 'name']);

        if (!$row) {
            return response()->json(['status' => false, 'message' => 'Competency not found.'], 404);
        }

        $restore = $request->boolean('restore', false);

        // Restore returns the item to Pending, not Approved - the frontend's
        // own confirmArchive() comment: archiving/restoring is a status move,
        // never a silent re-approval nobody reviewed.
        $status = $restore ? 'Pending' : 'Cancelled';

        // `competency` has no approve_status column in this target (only
        // s_competency_approvals does, ported earlier this session) - so
        // archiving here means upserting THIS table's latest row for
        // (subject_type='competency', subject_id={id}), reusing
        // CompetencyApprovalController's table/column names. NOTE: that
        // controller's own SUBJECTS['competency'] entry points at a DIFFERENT
        // table (s_users_skills, the legacy skill-as-competency screen) for
        // its submit/approve/reject workflow, so subject_id values written
        // from there and from here are drawn from different id spaces sharing
        // the same subject_type string. They cannot collide in a query scoped
        // by (sub_institute_id, subject_type, subject_id) unless a skill row
        // and a real competency row happen to share both a tenant and a
        // numeric id - flagged as a naming overlap worth resolving later
        // (e.g. a distinct subject_type such as 'competency_library'), not
        // fixed here since it was called out as the intended design.
        $existing = DB::table('s_competency_approvals')
            ->where('sub_institute_id', $sid)
            ->where('subject_type', 'competency')
            ->where('subject_id', $row->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            DB::table('s_competency_approvals')->where('id', $existing->id)->update([
                'status'        => $status,
                'subject_name'  => mb_substr($row->name, 0, 191),
                'reviewer_id'   => $actor,
                'reviewer_name' => $this->actorName($actor),
                'reviewed_at'   => now(),
                'updated_at'    => now(),
            ]);
        } else {
            DB::table('s_competency_approvals')->insert([
                'sub_institute_id'  => $sid,
                'subject_type'      => 'competency',
                'subject_id'        => $row->id,
                'subject_name'      => mb_substr($row->name, 0, 191),
                'status'            => $status,
                'submitted_by'      => $actor,
                'submitted_by_name' => $this->actorName($actor),
                'submitted_at'      => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        $this->logCompetencyActivity(
            $sid,
            $actor,
            $restore ? 'restored_competency' : 'archived_competency',
            ($restore ? 'Restored' : 'Archived') . ' competency "' . $row->name . '"',
            'competency',
            (int) $row->id,
            $row->name
        );

        return response()->json([
            'status'  => true,
            'message' => $restore ? 'Competency restored.' : 'Competency archived.',
            'data'    => ['id' => (int) $row->id, 'approve_status' => $status],
        ]);
    }

    /** Actor display name for the approvals row, same lookup CompetencyApprovalController uses. */
    private function actorName(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        $user = DB::table('tbluser')->where('id', $userId)->first();
        if (!$user) {
            return null;
        }

        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return $name !== '' ? $name : ($user->user_name ?? null);
    }
}
