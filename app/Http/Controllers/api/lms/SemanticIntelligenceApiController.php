<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SemanticIntelligenceApiController extends Controller
{
    /**
     * Column names holding large per-concept JSON blobs. Excluded from the
     * default `rows()` response (rows are ~400KB+ with these included) —
     * pass include_full=1 to opt in. `knowledge` is NOT in this list: it is
     * always returned (decoded), since callers need it by default.
     */
    private const BLOB_COLUMNS = [
        'full_intelegance_json',
        'full_intelligence_json',
        'ability',
        'skill',
        'competency',
        'prerequisites',
        'misconceptions',
        'real_world_applications',
        'pedagogy',
        'learning_objectives',
        'learning_outcomes',
        'assessment_blueprint',
        'assessment_rubrics',
    ];

    /**
     * Filtered, paginated listing of raw semantic_intelligence rows.
     * Supports sub_institute_id / subject_id / chapter_id / standard_id.
     * There is no concept_id column on this table — concepts live inside
     * the per-row JSON blob — so it is not offered as a filter here.
     */
    public function rows(Request $request): JsonResponse
    {
        try {
            $columns = array_diff(
                $this->tableColumns(),
                $request->boolean('include_full') ? [] : self::BLOB_COLUMNS
            );

            $query = DB::table('semantic_intelligence')
                ->select($columns)
                ->when($request->filled('sub_institute_id'), function ($q) use ($request) {
                    $q->where('sub_institute_id', $request->integer('sub_institute_id'));
                })
                ->when($request->filled('subject_id'), function ($q) use ($request) {
                    $q->where('subject_id', $request->integer('subject_id'));
                })
                ->when($request->filled('chapter_id'), function ($q) use ($request) {
                    $q->where('chapter_id', $request->integer('chapter_id'));
                })
                ->when($request->filled('standard_id'), function ($q) use ($request) {
                    $q->where('standard_id', $request->integer('standard_id'));
                })
                ->orderByDesc('id');

            $perPage = min(max((int) $request->input('per_page', 50), 1), 200);
            $paginated = $query->paginate($perPage);

            $items = collect($paginated->items())->map(function ($row) {
                $data = (array) $row;
                if (array_key_exists('knowledge', $data) && is_string($data['knowledge'])) {
                    $decoded = json_decode($data['knowledge'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $data['knowledge'] = $decoded;
                    }
                }
                return $data;
            })->values();

            return response()->json([
                'status' => true,
                'message' => 'SUCCESS',
                'data' => $items,
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error('Semantic intelligence rows fetch failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Fetch failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function tableColumns(): array
    {
        return DB::getSchemaBuilder()->getColumnListing('semantic_intelligence');
    }

    public function index(): JsonResponse
    {
        try {
            $chapters = DB::table('document_extractions as d')
                ->select([
                    'd.id',
                    'd.document_tittle',
                    'd.subject_name',
                    'd.standard',
                    'd.syear',
                    'd.chapter_number',
                    'd.created_at',
                ])
                ->selectRaw('EXISTS(SELECT 1 FROM semantic_intelligence s WHERE s.extraction_id = d.id) as is_processed')
                ->whereRaw('LOWER(d.document_type) = ?', ['chapter'])
                ->orderByDesc('d.id')
                ->get()
                ->map(function ($row) {
                    $data = (array) $row;
                    $data['is_processed'] = (bool) $data['is_processed'];
                    return $data;
                })
                ->values();

            return response()->json([
                'status' => true,
                'message' => 'SUCCESS',
                'data' => $chapters,
            ], 200);
        } catch (Throwable $e) {
            Log::error('Semantic intelligence list failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Fetch failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $extractionId): JsonResponse
    {
        try {
            $row = DB::table('semantic_intelligence as s')
                ->leftJoin('document_extractions as d', 's.extraction_id', '=', 'd.id')
                ->select('s.*', 'd.md_content')
                ->where(function ($query) use ($extractionId) {
                    $query->where('s.extraction_id', $extractionId)
                        ->orWhere('s.chapter_id', $extractionId);
                })
                ->first();

            if (!$row) {
                return response()->json([
                    'status' => false,
                    'message' => 'Semantic data not found',
                ], 404);
            }

            $data = (array) $row;
            foreach (['full_intelegance_json', 'full_intelligence_json'] as $key) {
                if (array_key_exists($key, $data) && is_string($data[$key])) {
                    $decoded = json_decode($data[$key], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $data[$key] = $decoded;
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'SUCCESS',
                'data' => $data,
            ], 200);
        } catch (Throwable $e) {
            Log::error('Semantic intelligence result failed: ' . $e->getMessage(), [
                'extraction_id' => $extractionId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Fetch failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

