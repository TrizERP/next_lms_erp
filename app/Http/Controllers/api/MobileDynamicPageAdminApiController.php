<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\mobile_dynamic_page_fieldModel;
use App\Models\mobile_dynamic_pageModel;
use App\Support\MobileDynamicPageFieldRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

/**
 * JSON API for configuring native, server-driven mobile pages -- the admin
 * screen for this lives in the lms_k12 Next.js frontend, not a Laravel Blade
 * view (see resources/views/user/mobile_dynamic_page.blade.php's removal),
 * so this is the ONLY way the pages/tiles are authored.
 *
 * Behind `api.session`: identity and tenant come from the verified JWT the
 * session was hydrated from, same as every other lms_k12-facing API. Gated
 * to Admin/Super Admin, the same rule
 * MobileAppMenuRightsApiController::permissions() applies for full access --
 * misconfiguring a tile a mobile user is about to see is not a Teacher- or
 * Student-level action.
 */
class MobileDynamicPageAdminApiController extends Controller
{
    private function requireAdmin(): ?JsonResponse
    {
        $profileName = strtolower((string) session()->get('user_profile_name'));
        $isAdmin = (int) session()->get('is_admin');

        if ($isAdmin >= 1 || in_array($profileName, ['admin', 'super admin'], true)) {
            return null;
        }

        return response()->json(['status' => '0', 'message' => 'You do not have permission to manage native dynamic pages.'], 403);
    }

    private function unavailable(): ?JsonResponse
    {
        if (Schema::hasTable('mobile_dynamic_page') && Schema::hasTable('mobile_dynamic_page_field')) {
            return null;
        }

        return response()->json(['status' => '0', 'message' => 'Native dynamic pages are not available on this installation.'], 503);
    }

    public function registry(): JsonResponse
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }

        $fieldsByEndpoint = [];
        foreach (MobileDynamicPageFieldRegistry::endpoints() as $endpoint) {
            $fieldsByEndpoint[$endpoint] = collect(MobileDynamicPageFieldRegistry::fieldsFor($endpoint))
                ->map(fn ($info, $key) => [
                    'field_key' => $key,
                    'label' => $info['label'],
                    'field_type' => $info['field_type'],
                    'display_key' => $info['display_key'],
                    'drill_endpoint' => $info['drill_endpoint'] ?? null,
                ])
                ->values();
        }

        return response()->json([
            'status' => '1',
            'data' => [
                'endpoints' => MobileDynamicPageFieldRegistry::endpoints(),
                'fields_by_endpoint' => $fieldsByEndpoint,
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }
        if ($response = $this->unavailable()) {
            return $response;
        }

        $pages = mobile_dynamic_pageModel::with('fields')
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->orderBy('page_key')
            ->get();

        return response()->json(['status' => '1', 'data' => $pages]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }
        if ($response = $this->unavailable()) {
            return $response;
        }

        $subInstituteId = session()->get('sub_institute_id');

        $validator = Validator::make($request->all(), [
            'page_key' => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/',
                function ($attribute, $value, $fail) use ($subInstituteId) {
                    if (mobile_dynamic_pageModel::where('sub_institute_id', $subInstituteId)->where('page_key', $value)->exists()) {
                        $fail('That page key is already in use.');
                    }
                },
            ],
            'title' => 'required|string|max:150',
            'data_endpoint' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! in_array($value, MobileDynamicPageFieldRegistry::endpoints(), true)) {
                    $fail('Unknown data source.');
                }
            }],
        ], [
            'page_key.regex' => 'Page key may only contain lowercase letters, numbers and underscores.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => '0', 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $page = mobile_dynamic_pageModel::create([
            'sub_institute_id' => $subInstituteId,
            'page_key' => $request->input('page_key'),
            'title' => $request->input('title'),
            'data_endpoint' => $request->input('data_endpoint'),
            'status' => 'Yes',
            'created_on' => now(),
            'updated_by' => session()->get('user_id'),
        ]);

        return response()->json(['status' => '1', 'message' => 'Page created.', 'data' => $page]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }
        if ($response = $this->unavailable()) {
            return $response;
        }

        $page = mobile_dynamic_pageModel::where('id', $id)
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->first();

        if (! $page) {
            return response()->json(['status' => '0', 'message' => 'Page not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:150',
            'status' => 'required|in:Yes,No',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => '0', 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $page->update([
            'title' => $request->input('title'),
            'status' => $request->input('status'),
            'updated_on' => now(),
            'updated_by' => session()->get('user_id'),
        ]);

        return response()->json(['status' => '1', 'message' => 'Page updated.', 'data' => $page]);
    }

    public function addField(Request $request, int $pageId): JsonResponse
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }
        if ($response = $this->unavailable()) {
            return $response;
        }

        $page = mobile_dynamic_pageModel::where('id', $pageId)
            ->where('sub_institute_id', session()->get('sub_institute_id'))
            ->first();

        if (! $page) {
            return response()->json(['status' => '0', 'message' => 'Page not found.'], 404);
        }

        $available = MobileDynamicPageFieldRegistry::fieldsFor($page->data_endpoint);

        $validator = Validator::make($request->all(), [
            'field_key' => ['required', 'string', function ($attribute, $value, $fail) use ($available) {
                if (! array_key_exists($value, $available)) {
                    $fail('Unknown field for this page\'s data source.');
                }
            }],
            'label' => 'nullable|string|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => '0', 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $fieldKey = $request->input('field_key');
        $defaults = $available[$fieldKey];

        $fieldData = [
            'page_id' => $page->id,
            'field_key' => $fieldKey,
            'display_key' => $defaults['display_key'],
            'label' => $request->filled('label') ? $request->input('label') : $defaults['label'],
            'field_type' => $defaults['field_type'],
            'sort_order' => $request->input('sort_order', (mobile_dynamic_page_fieldModel::where('page_id', $page->id)->max('sort_order') ?? 0) + 1),
            'status' => 'Yes',
            'created_on' => now(),
        ];

        if (Schema::hasColumn('mobile_dynamic_page_field', 'drill_endpoint')) {
            $fieldData['drill_endpoint'] = $defaults['drill_endpoint'] ?? null;
        }

        $field = mobile_dynamic_page_fieldModel::create($fieldData);

        return response()->json(['status' => '1', 'message' => 'Tile added.', 'data' => $field]);
    }

    public function updateField(Request $request, int $fieldId): JsonResponse
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }
        if ($response = $this->unavailable()) {
            return $response;
        }

        $field = mobile_dynamic_page_fieldModel::with('page')
            ->whereHas('page', fn ($q) => $q->where('sub_institute_id', session()->get('sub_institute_id')))
            ->find($fieldId);

        if (! $field) {
            return response()->json(['status' => '0', 'message' => 'Tile not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'label' => 'required|string|max:100',
            'sort_order' => 'required|integer',
            'status' => 'required|in:Yes,No',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => '0', 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $field->update([
            'label' => $request->input('label'),
            'sort_order' => $request->input('sort_order'),
            'status' => $request->input('status'),
            'updated_on' => now(),
        ]);

        return response()->json(['status' => '1', 'message' => 'Tile updated.', 'data' => $field]);
    }

    public function deleteField(int $fieldId): JsonResponse
    {
        if ($response = $this->requireAdmin()) {
            return $response;
        }
        if ($response = $this->unavailable()) {
            return $response;
        }

        $deleted = mobile_dynamic_page_fieldModel::whereHas('page', fn ($q) => $q->where('sub_institute_id', session()->get('sub_institute_id')))
            ->where('id', $fieldId)
            ->delete();

        return $deleted
            ? response()->json(['status' => '1', 'message' => 'Tile removed.'])
            : response()->json(['status' => '0', 'message' => 'Tile not found.'], 404);
    }
}
