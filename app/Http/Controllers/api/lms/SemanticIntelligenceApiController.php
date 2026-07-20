<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SemanticIntelligenceApiController extends Controller
{
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

