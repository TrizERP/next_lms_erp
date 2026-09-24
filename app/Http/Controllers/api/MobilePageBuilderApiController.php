<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\mobile_pageModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

/**
 * The runtime read a Custom Mobile Page's Next.js route
 * (lms_k12 app/mobile/custom/[slug]/page.tsx) calls to fetch what to render
 * -- what Flutter's WebView ultimately displays, by way of the existing
 * WebView + web-handoff machinery (unchanged -- see
 * MobileAppMenuRightsApiController::updateConfig() for how a menu row's
 * web_url ends up pointing at that Next.js route in the first place).
 *
 * Deliberately its own controller, separate from
 * MobilePageBuilderAdminApiController: this one is any-authenticated-user,
 * not admin-gated, and only ever serves a PUBLISHED version -- never a
 * draft, regardless of who is asking. Mirrors the existing
 * MobileDynamicPageApiController vs MobileDynamicPageAdminApiController
 * split for the same reason.
 */
class MobilePageBuilderApiController extends Controller
{
    public function runtime(string $slug): JsonResponse
    {
        if (! Schema::hasTable('mobile_pages') || ! Schema::hasTable('mobile_page_versions')) {
            return response()->json(['status' => '0', 'message' => 'This page is not available.'], 503);
        }

        $subInstituteId = (int) session()->get('sub_institute_id');
        if ($subInstituteId <= 0) {
            return response()->json(['status' => '2', 'message' => 'Token Auth Failed'], 401);
        }

        $page = mobile_pageModel::with('publishedVersion')
            ->where('sub_institute_id', $subInstituteId)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();

        // Same response whether the slug is unknown, belongs to another
        // tenant, or exists but was never published (or was deactivated) --
        // nothing legitimate distinguishes those cases from the caller's
        // side, and a 404 rather than 403 avoids confirming a slug exists
        // for a tenant it does not belong to.
        if (! $page || ! $page->publishedVersion) {
            return response()->json(['status' => '0', 'message' => 'This page is not available.'], 404);
        }

        return response()->json([
            'status' => '1',
            'data' => [
                'page' => [
                    'name' => $page->name,
                    'slug' => $page->slug,
                ],
                'layout' => json_decode($page->publishedVersion->layout_json, true),
                'version' => $page->publishedVersion->version_number,
            ],
        ]);
    }
}
