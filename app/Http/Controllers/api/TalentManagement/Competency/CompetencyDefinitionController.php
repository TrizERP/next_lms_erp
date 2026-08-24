<?php

namespace App\Http\Controllers\api\TalentManagement\Competency;

use App\Http\Controllers\api\TalentManagement\Competency\Concerns\ResolvesCompetencyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\CompetencyDefinitionController`.
 *
 * The Competency Library's "picker" — a competency is a NAMED BUNDLE OF KASBA
 * ITEMS. NOT to be confused with `CompetencyLibraryCrudController` (already
 * ported, on `/competency-library/*`) which serves the same `competency` +
 * `competency_kasba_item` tables to the richer library screen; this
 * controller keeps the source's original `/competency/definitions` list +
 * create shape used by a smaller picker sub-feature.
 *
 * `competency` and `competency_kasba_item` already exist in this target
 * (created by `2026_08_20_101000_create_competency_task_map_tables.php`,
 * extended by `2026_08_21_130000_add_kasba_rating_support_columns.php` and
 * `2026_08_24_090300_add_competency_library_crud_columns.php` for the
 * already-ported `KasbaRatingController` / `CompetencyLibraryCrudController`)
 * with every column this controller's queries need — `framework_id`,
 * `competency_type`, `criticality`, `status`; `competency_kasba_item.item_id`
 * — EXCEPT two the source inserts on create that this target's `competency`
 * table does not carry and no other ported controller in this namespace
 * reads: `requires_assessment` and `version` (confirmed via
 * `2026_08_24_090300_add_competency_library_crud_columns.php`'s own doc-block,
 * which added every column `CompetencyLibraryCrudController` needed and
 * deliberately excluded these two as unread). Required tenant-schema
 * adaptation: those two insert keys are dropped rather than added as new
 * columns, since nothing in this codebase reads them — flagged here rather
 * than guessed past.
 *
 * `s_users_skills` (skill) already exists. `s_user_knowledge`, `s_user_ability`,
 * `s_user_attitude`, `s_user_behaviour` — the canonical tables the source
 * verifies non-skill `item_id`s against — do NOT exist in this target
 * (confirmed: no `Schema::create` for any of the four across
 * `database/migrations`). Required adaptation: `itemTableExists()` guards
 * each lookup with `Schema::hasTable()`, matching the exact defensive pattern
 * `LibraryDependantsController` (ported alongside this controller) already
 * uses for the same class of problem — a missing table drops the id to a
 * label rather than throwing, which is the source's own "unverified id ->
 * held by label" rule extended to "unqueryable table -> held by label" so no
 * behaviour is invented that the source did not specify for this case.
 */
class CompetencyDefinitionController extends Controller
{
    use ResolvesCompetencyContext;

    private const KASBA = ['skill', 'knowledge', 'ability', 'attitude', 'behaviour'];

    /**
     * The canonical table behind each dimension - what an `item_id` must exist in,
     * inside the caller's own tenant, before it is stored as a key.
     */
    private const ITEM_TABLES = [
        'skill'     => 's_users_skills',
        'knowledge' => 's_user_knowledge',
        'ability'   => 's_user_ability',
        'attitude'  => 's_user_attitude',
        'behaviour' => 's_user_behaviour',
    ];

    /** GET /api/competency/definitions — competencies with their KASBA composition and its resolution state. */
    public function index(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $rows = DB::table('competency')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        $items = DB::table('competency_kasba_item')
            ->where('sub_institute_id', $context['sub_institute_id'])
            ->whereIn('competency_id', $rows->pluck('id'))
            ->get()
            ->groupBy('competency_id');

        return response()->json([
            'status' => 1,
            'data'   => $rows->map(function ($c) use ($items) {
                $own = $items->get($c->id, collect());

                return [
                    'id'           => (int) $c->id,
                    'code'         => $c->code,
                    'name'         => $c->name,
                    'type'         => $c->competency_type,
                    'criticality'  => $c->criticality,
                    'status'       => $c->status,
                    'items'        => $own->map(fn ($i) => [
                        'kasba_type' => $i->kasba_type,
                        'item_id'    => $i->item_id ? (int) $i->item_id : null,
                        'item_label' => $i->item_label,
                        'weight'     => (float) $i->weight,
                        // Honest about what is unresolved, rather than guessing.
                        'resolved'   => $i->item_id !== null,
                    ])->values(),
                    // Feeds the capability-coverage metric.
                    'unresolved_items' => $own->whereNull('item_id')->count(),
                ];
            })->values(),
        ]);
    }

    /** POST /api/competency/definitions — create a competency together with its KASBA items, in one transaction. */
    public function store(Request $request)
    {
        $context = $this->competencyContext($request);
        if (!is_array($context)) {
            return $context;
        }

        $validator = Validator::make($request->all(), [
            'name'                => 'required|string|max:191',
            'code'                => 'nullable|string|max:64',
            'description'         => 'nullable|string',
            // OPTIONAL, AND VERIFIED AGAINST THE CALLER'S OWN TENANT BELOW.
            'framework_id'        => 'nullable|integer',
            'competency_type'     => 'nullable|string|max:64',
            'criticality'         => 'nullable|string|max:32',
            'items'               => 'required|array|min:1',
            'items.*.kasba_type'  => 'required|in:' . implode(',', self::KASBA),
            'items.*.item_id'     => 'nullable|integer',
            'items.*.item_label'  => 'nullable|string|max:191',
            'items.*.weight'      => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 0, 'message' => $validator->errors()->first()], 422);
        }

        // uq_ck_item-equivalent check done in application code (the source's
        // unique index on (competency_id, kasba_type, item_id) is not
        // replicated as a DB constraint here, but the duplicate-detection
        // behaviour it protected is): adding the same skill twice under the
        // same dimension is refused with a sentence.
        $pairs = [];
        foreach ($request->input('items') as $i => $item) {
            if (empty($item['item_id'])) {
                continue;
            }
            $key = $item['kasba_type'] . ':' . $item['item_id'];
            if (isset($pairs[$key])) {
                return response()->json([
                    'status'  => 0,
                    'message' => "Item " . ($i + 1) . " repeats the same {$item['kasba_type']} item already listed.",
                ], 422);
            }
            $pairs[$key] = true;
        }

        // A row naming nothing at all is refused - the holding state is a LABEL,
        // not an absence.
        foreach ($request->input('items') as $i => $item) {
            if (empty($item['item_id']) && trim((string) ($item['item_label'] ?? '')) === '') {
                return response()->json([
                    'status'  => 0,
                    'message' => "Item " . ($i + 1) . " needs an item_id or an item_label.",
                ], 422);
            }
        }

        $sid    = $context['sub_institute_id'];
        $actor  = $context['user_id'];

        // uq_competency_tenant_code-equivalent check: a duplicate code must not
        // reach the caller as a raw SQL error.
        if ($request->filled('code')) {
            $clash = DB::table('competency')
                ->where('sub_institute_id', $sid)
                ->where('code', $request->input('code'))
                ->exists();

            if ($clash) {
                return response()->json([
                    'status'  => 0,
                    'message' => 'That competency code is already used in this organisation.',
                ], 422);
            }
        }

        // THE FRAMEWORK MUST BE THE CALLER'S OWN. A bare `exists:` rule would
        // accept ANY tenant's framework id. `$sid` comes from session context.
        $frameworkId = $request->input('framework_id') !== null
            ? (int) $request->input('framework_id')
            : null;

        if ($frameworkId !== null) {
            $ok = DB::table('s_competency_frameworks')
                ->where('id', $frameworkId)->where('sub_institute_id', $sid)->exists();

            if (!$ok) {
                return response()->json([
                    'status'  => 0,
                    'message' => 'That framework does not exist in your organisation.',
                ], 404);
            }
        }

        $competencyId = DB::transaction(function () use ($request, $sid, $actor, $frameworkId) {
            $id = DB::table('competency')->insertGetId([
                'sub_institute_id' => $sid,
                // NULL is permitted and permanent: a competency not filed under a
                // framework is a normal competency, not an incomplete one.
                'framework_id'     => $frameworkId,
                'code'             => $request->input('code'),
                'name'             => $request->input('name'),
                'description'      => $request->input('description'),
                'competency_type'  => $request->input('competency_type'),
                'criticality'      => $request->input('criticality'),
                // `requires_assessment` and `version` are the source's, but this
                // target's `competency` table carries neither column and no
                // other ported controller reads them - see class doc.
                'status'           => 'draft',
                'created_by'       => $actor,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            foreach ($request->input('items') as $item) {
                $itemId = $item['item_id'] ?? null;

                // EVERY item must resolve inside the caller's own tenant, or it is
                // held by label rather than pointed at someone else's row.
                $table = self::ITEM_TABLES[$item['kasba_type']] ?? null;
                if ($itemId && $table && $this->itemTableExists($table)) {
                    $ok = DB::table($table)
                        ->where('id', $itemId)
                        ->where('sub_institute_id', $sid)
                        ->exists();
                    if (!$ok) {
                        $itemId = null;
                    }
                } elseif ($itemId) {
                    // No canonical table for this type in this install (or the
                    // dimension has none at all), so the id cannot be verified
                    // and is DROPPED to a label rather than stored on trust.
                    $itemId = null;
                }

                DB::table('competency_kasba_item')->insert([
                    'sub_institute_id' => $sid,
                    'competency_id'    => $id,
                    'kasba_type'       => $item['kasba_type'],
                    'item_id'          => $itemId,
                    'item_label'       => $item['item_label'] ?? null,
                    'weight'           => $item['weight'],
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }

            return $id;
        });

        $this->logCompetencyActivity(
            $sid,
            $actor,
            'created_competency_definition',
            'Created competency "' . $request->input('name') . '"',
            'competency',
            (int) $competencyId,
            $request->input('name')
        );

        return response()->json([
            'status'  => 1,
            'message' => 'Competency created.',
            'data'    => ['id' => $competencyId],
        ], 201);
    }

    /** Whether the item's canonical table exists in this install - see class doc. */
    private function itemTableExists(string $table): bool
    {
        static $cache = [];

        return $cache[$table] ??= Schema::hasTable($table);
    }
}
