<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One call for the native mobile shell: who the caller is, and which menu tiles
 * their role may open.
 *
 * Identity, profile and tenant come exclusively from the verified JWT (via the
 * `api.session` middleware). The legacy /api/homescreen and /teacher_homescreen
 * endpoints read user_profile_id / sub_institute_id from the request body, so a
 * valid token for one role can ask for another role's menu; this endpoint cannot.
 * Those endpoints are left untouched for the existing apps.
 */
class MobileBootstrapApiController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $session = $request->session();
        $userId = (int) $session->get('user_id');
        $instituteId = (int) $session->get('sub_institute_id');
        $profileId = (int) $session->get('user_profile_id');
        $isStudent = (bool) $session->get('is_student');

        // The mobile menu rows are keyed by the profile's own name, so use the master
        // table rather than session('user_profile_name') (which reads "Super Admin" for admins).
        $profileName = (string) DB::table('tbluserprofilemaster')->where('id', $profileId)->value('name');

        $table = $isStudent ? 'mobile_homescreen' : 'teacher_mobile_homescreen';
        $sections = [];

        if ($profileName !== '' && Schema::hasTable($table)) {
            $hasRender = Schema::hasColumn($table, 'render_type');
            $rows = DB::table($table)
                ->where('status', 'Yes')
                ->where('user_profile_id', $profileId)
                ->where('user_profile_name', $profileName)
                ->where('sub_institute_id', $instituteId)
                ->orderBy('main_sort_order')
                ->orderBy('sub_title_sort_order')
                ->get();

            $base = $request->getSchemeAndHttpHost();

            foreach ($rows as $row) {
                $key = $row->main_title;
                $sections[$key] ??= [
                    'main_title' => $row->main_title,
                    'menu_type' => $row->menu_type,
                    'color' => $row->main_title_color_code,
                    'background_image' => $row->main_title_background_image,
                    'contents' => [],
                ];

                $webUrl = $hasRender ? trim((string) ($row->web_url ?? '')) : '';
                if ($webUrl !== '' && ! preg_match('#^https?://#i', $webUrl)) {
                    $webUrl = rtrim($base, '/') . '/' . ltrim($webUrl, '/');
                }

                $sections[$key]['contents'][] = [
                    'title' => $row->sub_title_of_main ?: $row->main_title,
                    'icon' => $row->sub_title_icon,
                    'api' => $row->sub_title_api,
                    'api_param' => $row->sub_title_api_param,
                    'screen_name' => $row->screen_name,
                    'render_type' => $hasRender ? ($row->render_type ?: 'native') : 'native',
                    'web_url' => $webUrl !== '' ? $webUrl : null,
                    'open_mode' => $hasRender && ($row->open_mode ?? '') === 'external' ? 'external' : 'in_app',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Bootstrap fetched successfully.',
            'data' => [
                'user' => [
                    'id' => $userId,
                    'sub_institute_id' => $instituteId,
                    'user_profile_id' => $profileId,
                    'user_profile_name' => $profileName,
                    'is_student' => $isStudent,
                    'is_admin' => (int) $session->get('is_admin'),
                    'school_name' => (string) $session->get('school_name'),
                ],
                'menu' => array_values($sections),
            ],
        ]);
    }
}
