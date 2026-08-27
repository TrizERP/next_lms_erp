<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backs GET /ai-platforms (routes/api.php:154). The class was referenced by
 * the route and its `use` import since commit 4ec19e64b but never created,
 * so any code path that loads routes/api.php - including `route:list` and
 * `route:cache` - fatals with a ReflectionException before this fix.
 *
 * The frontend at app/ai-platforms/page.tsx is currently fully static (5
 * hardcoded cards, no API call), so this only needs to exist and respond
 * safely, not back a live feature yet. Reads the single global module row
 * seeded by 2026_08_21_000001_seed_ai_module_coverage.php
 * (sub_institute_id is null on that row by design). No client input is
 * consumed, so there is no tenant-scoping surface to get wrong here.
 */
class AiPlatformController extends Controller
{
    public function index(): JsonResponse
    {
        if (! Schema::hasTable('ai_modules')) {
            return response()->json(['status_code' => 1, 'data' => null]);
        }

        $module = DB::table('ai_modules')
            ->where('module_key', 'ai-platforms')
            ->whereNull('sub_institute_id')
            ->first();

        return response()->json([
            'status_code' => 1,
            'data' => $module,
        ]);
    }
}
