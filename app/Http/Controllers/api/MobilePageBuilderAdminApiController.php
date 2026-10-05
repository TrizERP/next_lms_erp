<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\mobile_pageModel;
use App\Models\mobile_page_versionModel;
use App\Services\MobilePage\MobilePageAssetUploadService;
use App\Support\MobileFormFieldRegistry;
use App\Support\MobilePageLayoutValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin CRUD + Draft/Publish for Custom Mobile Pages -- authored from the
 * lms_k12 Next.js "Mobile Page Builder", not a Laravel Blade view. Sibling of
 * MobileDynamicPageAdminApiController: same `api.session` auth (real JWT,
 * hydrated session -- see routes/mobile_page_builder.php), same
 * Admin/Super-Admin gate, same {status:'1'|'0', message, data} envelope.
 *
 * IMPORTANT, read before adding a new capability here: this controller never
 * gains a way to execute a layout's action/data-binding config on the
 * server. Every "what does this button do" / "where does this field's value
 * come from" question is answered later, in the Next.js runtime page, by
 * calling the configured endpoint AS THE VIEWER, through the same
 * /api/proxy + bearer-token path every hand-coded lms_k12 page already uses.
 * That endpoint's own controller enforces its own auth exactly as if it had
 * been called directly. This controller's job is only to store and validate
 * the JSON that says which endpoint -- never to call it. See
 * MobilePageLayoutValidator's class doc.
 *
 * See MobilePageBuilderApiController for the separate, non-admin-gated
 * runtime read a mobile user's WebView actually hits.
 */
class MobilePageBuilderAdminApiController extends Controller
{
    private function requireAdmin(): ?JsonResponse
    {
        $profileName = strtolower((string) session()->get('user_profile_name'));
        $isAdmin = (int) session()->get('is_admin');

        if ($isAdmin >= 1 || in_array($profileName, ['admin', 'super admin'], true)) {
            return null;
        }

        return response()->json(['status' => '0', 'message' => 'You do not have permission to manage mobile pages.'], 403);
    }

    private function unavailable(): ?JsonResponse
    {
        if (Schema::hasTable('mobile_pages') && Schema::hasTable('mobile_page_versions')) {
            return null;
        }

        return response()->json(['status' => '0', 'message' => 'The mobile page builder is not available on this installation.'], 503);
    }

    private function tenantId(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    private function findPage(int $id): ?mobile_pageModel
    {
        return mobile_pageModel::where('id', $id)->where('sub_institute_id', $this->tenantId())->first();
    }

    private function uniqueSlug(string $base, ?int $excludePageId = null): string
    {
        $slug = Str::slug($base) ?: 'page';
        $slug = substr($slug, 0, 140);
        $candidate = $slug;
        $suffix = 2;

        while (
            mobile_pageModel::where('sub_institute_id', $this->tenantId())
                ->where('slug', $candidate)
                ->when($excludePageId, fn ($query) => $query->where('id', '!=', $excludePageId))
                ->exists()
        ) {
            $candidate = substr($slug, 0, 140 - strlen((string) $suffix) - 1) . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function defaultLayout(string $name): array
    {
        return [
            'page' => [
                'name' => $name,
                'width' => 375,
                'height' => 812,
                'background' => [
                    'type' => 'color',
                    'color' => '#FFFFFF',
                    'opacity' => 1,
                ],
            ],
            'components' => [],
        ];
    }

    private function pageSummary(mobile_pageModel $page): array
    {
        $draft = $page->draftVersion;
        $published = $page->publishedVersion;

        return [
            'id' => $page->id,
            'name' => $page->name,
            'slug' => $page->slug,
            'description' => $page->description,
            'status' => $page->status,
            'draft' => $draft ? [
                'versionNumber' => $draft->version_number,
                'updatedOn' => $draft->updated_on ?? $draft->created_on,
            ] : null,
            'published' => $published ? [
                'versionNumber' => $published->version_number,
                'publishedAt' => $published->published_at,
            ] : null,
            'createdOn' => $page->created_on,
            'updatedOn' => $page->updated_on,
        ];
    }

    public function index(): JsonResponse
    {
        if ($response = $this->requireAdmin()) return $response;
        if ($response = $this->unavailable()) return $response;

        $pages = mobile_pageModel::with(['draftVersion', 'publishedVersion'])
            ->where('sub_institute_id', $this->tenantId())
            ->orderByDesc('updated_on')
            ->get();

        return response()->json(['status' => '1', 'data' => $pages->map(fn ($page) => $this->pageSummary($page))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->requireAdmin()) return $response;
        if ($response = $this->unavailable()) return $response;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'slug' => 'nullable|string|max:150|regex:/^[a-z0-9\-]+$/',
            'description' => 'nullable|string|max:2000',
        ], [
            'slug.regex' => 'Slug may only contain lowercase letters, numbers and hyphens.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => '0', 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $name = (string) $request->input('name');
        $slug = $this->uniqueSlug((string) $request->input('slug') ?: $name);
        $actorId = session()->get('user_id');

        $page = DB::transaction(function () use ($name, $slug, $actorId, $request) {
            $page = mobile_pageModel::create([
                'sub_institute_id' => $this->tenantId(),
                'name' => $name,
                'slug' => $slug,
                'description' => $request->input('description'),
                'status' => 'draft',
                'created_by' => $actorId,
                'updated_by' => $actorId,
                'created_on' => now(),
                'updated_on' => now(),
            ]);

            $draft = mobile_page_versionModel::create([
                'page_id' => $page->id,
                'version_number' => 1,
                'layout_json' => json_encode($this->defaultLayout($name)),
                'status' => 'draft',
                'created_by' => $actorId,
                'created_on' => now(),
                'updated_on' => now(),
            ]);

            $page->current_draft_version_id = $draft->id;
            $page->save();

            return $page;
        });

        return response()->json(['status' => '1', 'message' => 'Page created.', 'data' => $this->pageSummary($page->fresh(['draftVersion', 'publishedVersion']))]);
    }

    public function show(int $id): JsonResponse
    {
        if ($response = $this->requireAdmin()) return $response;
        if ($response = $this->unavailable()) return $response;

        $page = $this->findPage($id);
        if (! $page) {
            return response()->json(['status' => '0', 'message' => 'Page not found.'], 404);
        }

        $draft = $page->draftVersion;
        $published = $page->publishedVersion;

        return response()->json([
            'status' => '1',
            'data' => [
                'id' => $page->id,
                'name' => $page->name,
                'slug' => $page->slug,
                'description' => $page->description,
                'status' => $page->status,
                'layout' => $draft ? json_decode($draft->layout_json, true) : $this->defaultLayout($page->name),
                'draft' => $draft ? ['versionNumber' => $draft->version_number, 'updatedOn' => $draft->updated_on ?? $draft->created_on] : null,
                'published' => $published ? ['versionNumber' => $published->version_number, 'publishedAt' => $published->published_at] : null,
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($response = $this->requireAdmin()) return $response;
        if ($response = $this->unavailable()) return $response;

        $page = $this->findPage($id);
        if (! $page) {
            return response()->json(['status' => '0', 'message' => 'Page not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'slug' => 'required|string|max:150|regex:/^[a-z0-9\-]+$/',
            'description' => 'nullable|string|max:2000',
            'status' => ['nullable', Rule::in(['draft', 'published', 'inactive'])],
        ], [
            'slug.regex' => 'Slug may only contain lowercase letters, numbers and hyphens.',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => '0', 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        $slug = (string) $request->input('slug');
        if (
            mobile_pageModel::where('sub_institute_id', $this->tenantId())
                ->where('slug', $slug)
                ->where('id', '!=', $page->id)
                ->exists()
        ) {
            return response()->json(['status' => '0', 'message' => 'That slug is already in use.'], 422);
        }

        $page->update([
            'name' => $request->input('name'),
            'slug' => $slug,
            'description' => $request->input('description'),
            // A page a mobile user could currently be looking at can be
            // deactivated here without touching its layout -- reactivating
            // it (back to 'draft' or 'published') never resurrects a
            // published version that publish() itself did not create.
            'status' => $request->input('status', $page->status === 'inactive' ? 'draft' : $page->status),
            'updated_by' => session()->get('user_id'),
            'updated_on' => now(),
        ]);

        return response()->json(['status' => '1', 'message' => 'Page updated.', 'data' => $this->pageSummary($page->fresh(['draftVersion', 'publishedVersion']))]);
    }

    public function saveDraft(Request $request, int $id): JsonResponse
    {
        if ($response = $this->requireAdmin()) return $response;
        if ($response = $this->unavailable()) return $response;

        $page = $this->findPage($id);
        if (! $page) {
            return response()->json(['status' => '0', 'message' => 'Page not found.'], 404);
        }

        $layout = $request->input('layout');
        $raw = json_encode($layout);
        if ($raw === false || strlen($raw) > MobilePageLayoutValidator::MAX_JSON_BYTES) {
            return response()->json(['status' => '0', 'message' => 'The page design is too large to save.'], 422);
        }

        $errors = MobilePageLayoutValidator::validate($layout);
        if ($errors !== []) {
            return response()->json(['status' => '0', 'message' => $errors[0], 'errors' => $errors], 422);
        }

        $actorId = session()->get('user_id');

        DB::transaction(function () use ($page, $raw, $actorId) {
            $draft = $page->draftVersion;

            if (! $draft) {
                $nextVersion = (int) mobile_page_versionModel::where('page_id', $page->id)->max('version_number') + 1;
                $draft = mobile_page_versionModel::create([
                    'page_id' => $page->id,
                    'version_number' => $nextVersion,
                    'layout_json' => $raw,
                    'status' => 'draft',
                    'created_by' => $actorId,
                    'created_on' => now(),
                    'updated_on' => now(),
                ]);
                $page->current_draft_version_id = $draft->id;
            } else {
                $draft->update(['layout_json' => $raw, 'updated_on' => now()]);
            }

            $page->updated_by = $actorId;
            $page->updated_on = now();
            $page->save();
        });

        return response()->json(['status' => '1', 'message' => 'Draft saved.']);
    }

    public function publish(int $id): JsonResponse
    {
        if ($response = $this->requireAdmin()) return $response;
        if ($response = $this->unavailable()) return $response;

        $page = $this->findPage($id);
        if (! $page) {
            return response()->json(['status' => '0', 'message' => 'Page not found.'], 404);
        }

        $draft = $page->draftVersion;
        if (! $draft) {
            return response()->json(['status' => '0', 'message' => 'This page has no draft to publish.'], 422);
        }

        $layout = json_decode($draft->layout_json, true);
        $errors = MobilePageLayoutValidator::validate($layout);
        if ($errors !== []) {
            return response()->json(['status' => '0', 'message' => 'The current draft is invalid and cannot be published: ' . $errors[0], 'errors' => $errors], 422);
        }

        $actorId = session()->get('user_id');

        DB::transaction(function () use ($page, $draft, $actorId) {
            if ($page->publishedVersion) {
                $page->publishedVersion->update(['status' => 'archived']);
            }

            $nextVersion = (int) mobile_page_versionModel::where('page_id', $page->id)->max('version_number') + 1;
            $published = mobile_page_versionModel::create([
                'page_id' => $page->id,
                'version_number' => $nextVersion,
                'layout_json' => $draft->layout_json,
                'status' => 'published',
                'created_by' => $actorId,
                'created_on' => now(),
                'published_at' => now(),
                'published_by' => $actorId,
            ]);

            $page->published_version_id = $published->id;
            $page->status = 'published';
            $page->updated_by = $actorId;
            $page->updated_on = now();
            $page->save();
        });

        return response()->json(['status' => '1', 'message' => 'Page published.', 'data' => $this->pageSummary($page->fresh(['draftVersion', 'publishedVersion']))]);
    }

    public function versions(int $id): JsonResponse
    {
        if ($response = $this->requireAdmin()) return $response;
        if ($response = $this->unavailable()) return $response;

        $page = $this->findPage($id);
        if (! $page) {
            return response()->json(['status' => '0', 'message' => 'Page not found.'], 404);
        }

        return response()->json(['status' => '1', 'data' => $page->versions()->get(['id', 'version_number', 'status', 'created_on', 'published_at'])]);
    }

    /**
     * Soft: flips status to 'inactive' rather than deleting the row, so a
     * menu row's custom_page_id never points at nothing (see the
     * page_source migration's nullOnDelete, which only fires on a genuine
     * row delete -- this never triggers it).
     */
    public function destroy(int $id): JsonResponse
    {
        if ($response = $this->requireAdmin()) return $response;
        if ($response = $this->unavailable()) return $response;

        $page = $this->findPage($id);
        if (! $page) {
            return response()->json(['status' => '0', 'message' => 'Page not found.'], 404);
        }

        $page->update(['status' => 'inactive', 'updated_by' => session()->get('user_id'), 'updated_on' => now()]);

        return response()->json(['status' => '1', 'message' => 'Page deactivated.']);
    }

    public function uploadAsset(Request $request, int $id, MobilePageAssetUploadService $uploads): JsonResponse
    {
        if ($response = $this->requireAdmin()) return $response;
        if ($response = $this->unavailable()) return $response;

        $page = $this->findPage($id);
        if (! $page) {
            return response()->json(['status' => '0', 'message' => 'Page not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|image|max:10240',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => '0', 'message' => $validator->messages()->first(), 'errors' => $validator->errors()], 422);
        }

        try {
            $stored = $uploads->store($request->file('file'), $this->tenantId(), $page->id);
        } catch (\RuntimeException $exception) {
            return response()->json(['status' => '0', 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['status' => '1', 'message' => 'Uploaded.', 'data' => $stored]);
    }

    /**
     * "Select Existing Page" in the create flow -- see MobileFormFieldRegistry's
     * class doc for what this is and why it's a curated registry rather than
     * live page introspection.
     */
    public function sourcePages(): JsonResponse
    {
        if ($response = $this->requireAdmin()) return $response;

        return response()->json(['status' => '1', 'data' => MobileFormFieldRegistry::pages()]);
    }

    public function sourcePage(string $key): JsonResponse
    {
        if ($response = $this->requireAdmin()) return $response;

        $entry = MobileFormFieldRegistry::get($key);
        if (! $entry) {
            return response()->json(['status' => '0', 'message' => 'Unknown source page.'], 404);
        }

        return response()->json(['status' => '1', 'data' => $entry + ['key' => $key]]);
    }

    /**
     * Every real page on this tenant's sidebar -- level 3 in tblmenumaster
     * (level 1/2 are section headers with no page of their own; a handful
     * of level 4 rows are rare enough to fold into their level-3 parent's
     * group rather than add a fourth grouping tier), grouped by their
     * level-1/2 ancestor's name the way the sidebar itself groups them.
     *
     * Each item says whether MobileFormFieldRegistry has already traced its
     * fields (`sourceKey` set -> selecting it in the Next.js picker calls
     * sourcePage() and auto-imports, exactly like the hand-picked cards
     * already do) or not (`sourceKey` null -> selecting it creates a blank
     * page pre-named after the menu item, since this app has ~367 such
     * pages and only a few have been hand-traced so far -- see
     * MobileFormFieldRegistry's class doc for why that tracing is
     * deliberately manual, not automatic).
     */
    public function menuPages(): JsonResponse
    {
        if ($response = $this->requireAdmin()) return $response;

        $tenantId = $this->tenantId();

        $rows = DB::table('tblmenumaster')
            ->select('id', 'name', 'link', 'level', 'parent_menu_id')
            ->whereIn('level', [1, 2, 3, 4])
            ->where('status', 1)
            ->whereRaw('FIND_IN_SET(?, sub_institute_id)', [$tenantId])
            ->orderBy('sort_order')
            ->get()
            ->keyBy('id');

        $sectionNameFor = function ($row) use ($rows, &$sectionNameFor) {
            if ((int) $row->level <= 2) {
                return $row->name;
            }
            $parent = $rows->get($row->parent_menu_id);
            return $parent ? $sectionNameFor($parent) : 'Other';
        };

        $items = $rows
            ->filter(function ($row) {
                if (! in_array((int) $row->level, [3, 4], true)) {
                    return false;
                }
                $link = trim((string) $row->link);
                return $link !== '' && ! str_starts_with($link, 'javascript:');
            })
            ->map(function ($row) use ($sectionNameFor) {
                $sourceKey = MobileFormFieldRegistry::keyForMenuLink($row->link);
                return [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'section' => $sectionNameFor($row),
                    'sourceKey' => $sourceKey,
                ];
            })
            ->sortBy('name')
            ->values();

        return response()->json(['status' => '1', 'data' => $items]);
    }
}
