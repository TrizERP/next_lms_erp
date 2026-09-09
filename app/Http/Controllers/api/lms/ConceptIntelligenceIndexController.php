<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Services\lms\Intelligence\ConceptIntelligenceProjection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Read-only access to the projected Concept Intelligence index.
 *
 * Follow-on to tracker row 6 / Decision #38. The verification memo established that
 * `semantic_intelligence` is a blob cache, not a queryable Evidence store; this exposes
 * the projection of it (`lms_concept_intelligence_index`) as a stable contract so other
 * modules consume a shape rather than parsing `full_intelegance_json` themselves - which
 * is what the frontend does today (chapters.ts carries the backend's misspelling of that
 * column into its TypeScript types).
 *
 * DELIBERATELY READ ONLY. There is no write path here, and there will not be one:
 * `semantic_intelligence` stays the source of truth, and the projection is rebuilt by
 * `php artisan lms:project-concept-intelligence`.
 *
 * This is NOT the Evidence & Context Engine (Track D, Central Engines sheet). It has no
 * learner linkage and makes no claim on that scope.
 */
class ConceptIntelligenceIndexController extends Controller
{
    /**
     * GET /api/lms/concept-intelligence/{chapterId}/index
     *
     * Optional: ?dimension=competency  ?concept=Acid-Base%20Indicators  ?limit=
     */
    public function show(Request $request, int $chapterId): JsonResponse
    {
        $dimension = $request->query('dimension');

        if ($dimension !== null && ! in_array($dimension, ConceptIntelligenceProjection::dimensions(), true)) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Unknown dimension.',
                'allowed' => ConceptIntelligenceProjection::dimensions(),
            ], 422);
        }

        $query = DB::table('lms_concept_intelligence_index')
            ->where('chapter_id', $chapterId);

        // Tenancy is applied only when the caller states one. These endpoints have no
        // authentication yet (Phase A4 adds it), so this deliberately does not pretend
        // to enforce a boundary it cannot yet verify.
        if ($tenant = $request->query('sub_institute_id')) {
            $query->where('sub_institute_id', $tenant);
        }

        if ($dimension !== null) {
            $query->where('dimension', $dimension);
        }

        if ($concept = $request->query('concept')) {
            $query->where('concept_name', $concept);
        }

        $limit = (int) $request->query('limit', 0);
        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->orderBy('dimension')->orderBy('concept_name')->orderBy('ordinal')->get();

        $byDimension = [];
        foreach ($rows as $row) {
            $byDimension[$row->dimension][] = [
                'concept_name' => $row->concept_name,
                'item_key' => $row->item_key,
                'item_label' => $row->item_label,
                'confidence' => $row->confidence === null ? null : (float) $row->confidence,
                'attributes' => $row->attributes ? json_decode($row->attributes, true) : null,
            ];
        }

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'chapter_id' => $chapterId,
            'dimensions' => ConceptIntelligenceProjection::dimensions(),
            'counts' => array_map('count', $byDimension),
            'total' => $rows->count(),
            'data' => $byDimension,
        ], 200);
    }

    /**
     * GET /api/lms/concept-intelligence/lookup?dimension=competency&item_key=...
     *
     * The cross-chapter query that was impossible before the projection existed:
     * "which concepts, in which chapters, develop this competency?"
     */
    public function lookup(Request $request): JsonResponse
    {
        $dimension = (string) $request->query('dimension', '');
        $itemKey = (string) $request->query('item_key', '');

        if (! in_array($dimension, ConceptIntelligenceProjection::dimensions(), true)) {
            return response()->json([
                'status_code' => 0,
                'message' => 'A valid `dimension` is required.',
                'allowed' => ConceptIntelligenceProjection::dimensions(),
            ], 422);
        }

        if ($itemKey === '') {
            return response()->json([
                'status_code' => 0,
                'message' => '`item_key` is required. Use the item_key values returned by the index endpoint.',
            ], 422);
        }

        $query = DB::table('lms_concept_intelligence_index')
            ->where('dimension', $dimension)
            ->where('item_key', $itemKey);

        if ($tenant = $request->query('sub_institute_id')) {
            $query->where('sub_institute_id', $tenant);
        }

        $rows = $query->orderBy('chapter_id')->limit((int) $request->query('limit', 200))->get();

        return response()->json([
            'status_code' => 1,
            'message' => 'SUCCESS',
            'dimension' => $dimension,
            'item_key' => $itemKey,
            'total' => $rows->count(),
            'data' => $rows->map(static fn ($r) => [
                'chapter_id' => (int) $r->chapter_id,
                'sub_institute_id' => (int) $r->sub_institute_id,
                'concept_name' => $r->concept_name,
                'item_label' => $r->item_label,
                'confidence' => $r->confidence === null ? null : (float) $r->confidence,
            ])->all(),
        ], 200);
    }
}
