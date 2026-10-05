<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\mobile_dynamic_pageModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Serves the schema for a native, server-driven mobile page: a title plus an
 * ordered list of tiles, each naming the JSON key it reads and how to format
 * it. Mobile renders real Flutter widgets from this, never a WebView.
 *
 * Only the SCHEMA is served here. The live values it describes come from
 * calling `data_endpoint` directly -- exactly the same already-authenticated,
 * already-permission-gated call every other native screen in the app already
 * makes (see menu_service.dart et al) -- so this endpoint does not proxy or
 * duplicate that call.
 *
 * Behind `api.session`, same as the menu feed itself: identity comes from the
 * verified JWT, never from the request body.
 */
class MobileDynamicPageApiController extends Controller
{
    public function show(Request $request, string $pageKey): JsonResponse
    {
        if (! Schema::hasTable('mobile_dynamic_page') || ! Schema::hasTable('mobile_dynamic_page_field')) {
            return response()->json(['status' => '0', 'message' => 'Native dynamic pages are not available on this installation.', 'data' => []], 503);
        }

        $subInstituteId = session()->get('sub_institute_id');

        $page = mobile_dynamic_pageModel::with('fields')
            ->where('sub_institute_id', $subInstituteId)
            ->where('page_key', $pageKey)
            ->where('status', 'Yes')
            ->first();

        if (! $page) {
            return response()->json(['status' => '0', 'message' => 'This page is not configured.', 'data' => []], 404);
        }

        // Guarded so an installation that has not yet run
        // 2026_09_23_180000_add_drill_endpoint_to_... keeps serving tiles as
        // plain (non-tappable) stats instead of erroring.
        $hasDrillEndpoint = Schema::hasColumn('mobile_dynamic_page_field', 'drill_endpoint');

        return response()->json([
            'status' => '1',
            'message' => 'Success',
            'data' => [
                'page_key' => $page->page_key,
                'title' => $page->title,
                'data_endpoint' => $page->data_endpoint,
                'tiles' => $page->fields->where('status', 'Yes')->values()->map(fn ($field) => [
                    'field_key' => $field->field_key,
                    'display_key' => $field->display_key,
                    'label' => $field->label,
                    'field_type' => $field->field_type,
                    'drill_endpoint' => $hasDrillEndpoint ? $field->drill_endpoint : null,
                ]),
            ],
        ]);
    }
}
