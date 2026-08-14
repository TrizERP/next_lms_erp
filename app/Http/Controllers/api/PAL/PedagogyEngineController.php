<?php

namespace App\Http\Controllers\api\PAL;

use App\Http\Controllers\Controller;
use App\Services\PAL\Pedagogy\PedagogyEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PAL V4 Pedagogy Engine API.
 *
 * Serves the codified rule set (mastery tiers, engagement state, error and
 * misconception handling, learning style and context, the engagement score
 * composition and the pedagogy x trigger map) to the PAL UI.
 *
 * Read-only reference data with no learner or tenant scope, so it is registered
 * outside the `pal.auth` group alongside the other read-only PAL content routes
 * the Next.js server renderer consumes.
 */
class PedagogyEngineController extends Controller
{
    public function __construct(
        private readonly PedagogyEngineService $engine
    ) {
    }

    /**
     * GET /api/pal/pedagogy-engine
     *
     * Whole module in one response: every section plus its rows, with each rule
     * executed against a concept from `semantic_intelligence`.
     *
     * ?chapterId=  chapter_id / extraction_id / semantic row id (latest if omitted)
     * ?concept=    concept index or concept_name within that chapter (first if omitted)
     * ?include_hidden=1 also returns sections flagged ui_visible = false.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->engine->getModule($this->selection($request)),
            ]);
        } catch (Throwable $e) {
            return $this->failure('Failed to load the Pedagogy Engine module', $e);
        }
    }

    /**
     * GET /api/pal/pedagogy-engine/chapters
     *
     * Extracted chapters the engine can be evaluated against.
     */
    public function chapters(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->engine->listChapters(),
            ]);
        } catch (Throwable $e) {
            return $this->failure('Failed to load the extracted chapter list', $e);
        }
    }

    /**
     * @return array{include_hidden:bool, chapterId:string|null, concept:string|null}
     */
    private function selection(Request $request): array
    {
        return [
            'include_hidden' => $request->boolean('include_hidden'),
            'chapterId' => $request->query('chapterId') ?? $request->query('chapter_id'),
            'concept' => $request->query('concept'),
        ];
    }

    /**
     * GET /api/pal/pedagogy-engine/sections
     *
     * Navigation only - section ids, names and row counts.
     */
    public function sections(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->engine->listSections([
                    'include_hidden' => $request->boolean('include_hidden'),
                ]),
            ]);
        } catch (Throwable $e) {
            return $this->failure('Failed to load the Pedagogy Engine sections', $e);
        }
    }

    /**
     * GET /api/pal/pedagogy-engine/{section}
     *
     * One submodule: tier-1 | tier-2 | tier-3 | tier-4 | tier-5 |
     * engagement-score | trigger-map.
     */
    public function show(Request $request, string $section): JsonResponse
    {
        try {
            $data = $this->engine->getSection($section, $this->selection($request));

            if ($data === null) {
                return response()->json([
                    'success' => false,
                    'message' => "Pedagogy Engine section `{$section}` was not found.",
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return $this->failure("Failed to load Pedagogy Engine section `{$section}`", $e);
        }
    }

    private function failure(string $message, Throwable $e): JsonResponse
    {
        Log::error('PAL Pedagogy Engine API: ' . $message . ' - ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $message . '.',
        ], 500);
    }
}
