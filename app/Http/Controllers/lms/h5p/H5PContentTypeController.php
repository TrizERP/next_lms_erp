<?php

namespace App\Http\Controllers\lms\h5p;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use function App\Helpers\is_mobile;

/**
 * The CRUD / publish / duplicate / media / package cycle every H5P content
 * type in this ERP has, with only the type-specific parts left abstract.
 *
 * WHY A BASE CLASS AND NOT A FOURTH COPY
 *
 * H5PDragDropController established the shape: session-derived tenancy, the
 * `type=API` JSON contract, a draft/published state students never see through,
 * wholesale child replacement on update, an AuditLog entry on every write, and
 * package import/export over config/h5p_libraries.php. H5PTextActivityController
 * then reproduced it. The 2026-09-21 vertical adds four more types, and four
 * more copies would be seven places for the tenancy rule to drift -- which is
 * the one rule in this file that must not.
 *
 * So the cycle is here once, and a subclass declares:
 *
 *   modelClass()        the Eloquent model
 *   registryCode()      the config/pal_h5p.php + config/h5p_libraries.php key
 *   routePrefix()       the route name prefix, e.g. h5p_memory_game
 *   payloadKey()        the JSON key one item is returned under
 *   listKey()           the JSON key a list is returned under
 *   label()             what to call this type in a message
 *   relations()         what to eager-load on every read
 *   saveRules()         validation for store/update
 *   attributesFrom()    validated data -> parent row columns
 *   syncChildren()      write the child rows
 *   publishBlocker()    why this item cannot be published yet, or null
 *   buildParams()       rows -> H5P params, for the content_json cache
 *   exportPackage()     stream this item as .h5p
 *   parsePackage()      read an uploaded .h5p into row payloads
 *   createFromImport()  row payloads -> a new draft
 *   duplicableColumns() what a copy carries over
 *
 * The existing two controllers are deliberately NOT retrofitted onto this
 * base: they pass their tests, their behaviour is what this base was derived
 * FROM, and rewriting working, audited write paths to remove duplication that
 * has already been paid for is risk with no user-visible result.
 */
abstract class H5PContentTypeController extends Controller
{
    // -----------------------------------------------------------------------
    // What a subclass must say
    // -----------------------------------------------------------------------

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    abstract protected function registryCode(): string;

    abstract protected function routePrefix(): string;

    abstract protected function payloadKey(): string;

    abstract protected function listKey(): string;

    abstract protected function label(): string;

    /** @return list<string> */
    abstract protected function relations(): array;

    /** @return array<string,mixed> */
    abstract protected function saveRules(): array;

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    abstract protected function attributesFrom(array $data, Request $request): array;

    /** @param array<string,mixed> $data */
    abstract protected function syncChildren(Model $item, array $data, int|string|null $subInstituteId, int|string|null $userId): void;

    abstract protected function publishBlocker(Model $item): ?string;

    /** @return array<string,mixed> */
    abstract protected function buildParams(Model $item): array;

    /** @return array{path: string, filename: string, warnings: list<string>} */
    abstract protected function exportPackage(Model $item): array;

    /** @return array<string,mixed> */
    abstract protected function parsePackage(\Illuminate\Http\UploadedFile $file, int|string|null $subInstituteId): array;

    /** @param array<string,mixed> $parsed */
    abstract protected function createFromImport(array $parsed, Request $request, int|string|null $subInstituteId, int|string|null $userId): Model;

    /** @return list<string> */
    abstract protected function duplicableColumns(): array;

    /**
     * Copy the children of $original onto $copy.
     *
     * Separate from syncChildren() because a duplicate has rows to copy, not a
     * client payload to write.
     */
    abstract protected function duplicateChildren(Model $original, Model $copy, int|string|null $subInstituteId, int|string|null $userId): void;

    /** Blade view directory, e.g. `lms/h5p/memorygame`. */
    abstract protected function viewPath(): string;

    /**
     * Defaults the authoring UI opens with.
     *
     * Served rather than duplicated client-side, so changing a default is a
     * one-file change.
     *
     * @return array<string,mixed>
     */
    protected function authoringDefaults(): array
    {
        return [];
    }

    // -----------------------------------------------------------------------
    // Read
    // -----------------------------------------------------------------------

    public function index(Request $request)
    {
        $type = $request->input('type');
        $subInstituteId = $this->tenant($request);

        if ($this->isApi($type)) {
            $request->validate([
                'sub_institute_id' => 'required|integer',
                'standard_id' => 'required|integer',
                'subject_id' => 'required|integer',
                'chapter_id' => 'required|integer',
            ]);
        }

        $query = $this->modelClass()::with($this->relations())
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $request->standard_id)
            ->where('subject_id', $request->subject_id)
            ->where('chapter_id', $request->chapter_id);

        // A student never sees a draft. This is the only place the rule lives,
        // so a draft cannot leak through the list into a player deep link.
        if ($this->isStudent($request)) {
            $query->where('status', 'published');
        }

        $res = $request->all();
        $res[$this->listKey()] = $query->orderByDesc('id')->get()->map(fn (Model $item) => $this->present($item));
        $res['chapter_id'] = $request->chapter_id;
        $res['standard_id'] = $request->standard_id;
        $res['subject_id'] = $request->subject_id;

        return is_mobile($type, $this->viewPath() . '/index', $res, 'view');
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $res = $request->all();
        $res['defaults'] = $this->authoringDefaults();
        $res['library'] = config('h5p_libraries.libraries.' . $this->registryCode() . '.machine_name');

        return is_mobile($type, $this->viewPath() . '/create', $res, 'view');
    }

    public function show(Request $request, $id)
    {
        $type = $request->input('type');
        $item = $this->findForTenant($request, $id);

        if ($this->isStudent($request) && $item->status !== 'published') {
            return $this->fail($type, 'This activity has not been published yet.', 403);
        }

        $res = $request->all();
        $res[$this->payloadKey()] = $this->present($item);
        // The player reads rows, but a caller that wants to hand the item to an
        // H5P runtime gets the params for free rather than rebuilding them.
        $res['params'] = $this->buildParams($item);
        $res['chapter_id'] = $item->chapter_id;
        $res['standard_id'] = $item->standard_id;
        $res['subject_id'] = $item->subject_id;

        return is_mobile($type, $this->viewPath() . '/show', $res, 'view');
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $item = $this->findForTenant($request, $id);

        $res = $request->all();
        $res[$this->payloadKey()] = $this->present($item);
        $res['chapter_id'] = $item->chapter_id;
        $res['standard_id'] = $item->standard_id;
        $res['subject_id'] = $item->subject_id;

        return is_mobile($type, $this->viewPath() . '/edit', $res, 'view');
    }

    // -----------------------------------------------------------------------
    // Write
    // -----------------------------------------------------------------------

    public function store(Request $request)
    {
        $type = $request->input('type');
        $subInstituteId = $this->tenant($request);
        $userId = $this->userId($request);

        $data = $request->validate($this->saveRules());

        $item = DB::transaction(function () use ($request, $data, $subInstituteId, $userId) {
            $item = $this->modelClass()::create($this->attributesFrom($data, $request) + [
                'standard_id' => $request->standard_id,
                'subject_id' => $request->subject_id,
                'chapter_id' => $request->chapter_id,
                'sub_institute_id' => $subInstituteId,
                'syear' => $request->input('syear'),
                // Everything starts as a draft. Publishing is a separate,
                // validated act -- see publish().
                'status' => 'draft',
                'library' => $this->libraryVersionString(),
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            $this->syncChildren($item, $data, $subInstituteId, $userId);

            return $item;
        });

        $item->load($this->relations());
        $this->cacheParams($item);
        $this->audit('created', $item, $item->toArray());

        return $this->respond($request, $type, [
            'status' => true,
            'message' => $this->label() . ' created successfully!',
            'id' => $item->id,
            $this->payloadKey() => $this->present($item),
        ]);
    }

    public function update(Request $request, $id)
    {
        $type = $request->input('type');
        $subInstituteId = $this->tenant($request);
        $userId = $this->userId($request);

        $item = $this->findForTenant($request, $id);
        $data = $request->validate($this->saveRules());

        DB::transaction(function () use ($item, $request, $data, $subInstituteId, $userId) {
            $item->update($this->attributesFrom($data, $request) + [
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            // Children are replaced wholesale rather than diffed. The client
            // sends refs, not ids, so there is nothing stable to diff against;
            // and an item holds tens of rows, not thousands, so a replace is
            // both correct and cheap. Soft deletes keep the old rows for audit.
            $this->softDeleteChildren($item, $userId);
            $this->syncChildren($item, $data, $subInstituteId, $userId);
        });

        $item->load($this->relations());
        $this->cacheParams($item);
        $this->audit('updated', $item, $item->toArray());

        return $this->respond($request, $type, [
            'status' => true,
            'message' => $this->label() . ' updated successfully!',
            'id' => $item->id,
            $this->payloadKey() => $this->present($item),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        $userId = $this->userId($request);
        $item = $this->findForTenant($request, $id);

        DB::transaction(function () use ($item, $userId) {
            $this->softDeleteChildren($item, $userId);
            $item->deleted_by = $userId;
            $item->save();
            $item->delete();
        });

        $this->audit('deleted', $item, $item->toArray());

        return $this->respond($request, $type, [
            'status' => true,
            'message' => $this->label() . ' deleted successfully!',
        ]);
    }

    /**
     * Publish or unpublish.
     *
     * Publishing refuses an item that cannot be used -- the specific reason is
     * returned verbatim, because "no pair has both sides filled in" names the
     * thing the author still has to do and "cannot publish" does not.
     */
    public function publish(Request $request, $id)
    {
        $type = $request->input('type');
        $userId = $this->userId($request);
        $item = $this->findForTenant($request, $id);

        $publish = filter_var($request->input('published', true), FILTER_VALIDATE_BOOLEAN);

        if ($publish) {
            $problem = $this->publishBlocker($item);
            if ($problem !== null) {
                return $this->fail($type, $problem, 422);
            }
        }

        $item->update([
            'status' => $publish ? 'published' : 'draft',
            'published_at' => $publish ? now() : null,
            'updated_by' => $userId,
        ]);

        $this->audit($publish ? 'published' : 'unpublished', $item, [
            'status' => $item->status,
            'published_at' => $item->published_at,
        ]);

        return $this->respond($request, $type, [
            'status' => true,
            'message' => $publish ? 'Activity published.' : 'Activity moved back to draft.',
            $this->payloadKey() => $this->present($item->fresh($this->relations())),
        ]);
    }

    /**
     * Copy an item, and its children, into a new draft in the same chapter.
     *
     * A copy is always a draft even when the original is published: the reason
     * a teacher duplicates is to change something, and a copy that went live
     * the moment it was made would put the unchanged duplicate in front of a
     * class.
     */
    public function duplicate(Request $request, $id)
    {
        $type = $request->input('type');
        $subInstituteId = $this->tenant($request);
        $userId = $this->userId($request);

        $original = $this->findForTenant($request, $id);

        $copy = DB::transaction(function () use ($original, $subInstituteId, $userId) {
            $copy = $this->modelClass()::create($original->only($this->duplicableColumns()) + [
                'title' => $this->copyTitle($original, $subInstituteId),
                'sub_institute_id' => $subInstituteId,
                'status' => 'draft',
                'published_at' => null,
                'library' => $this->libraryVersionString(),
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            $this->duplicateChildren($original, $copy, $subInstituteId, $userId);

            return $copy;
        });

        $copy->load($this->relations());
        $this->cacheParams($copy);
        $this->audit('duplicated', $copy, ['copied_from' => $original->id, 'title' => $copy->title]);

        return $this->respond($request, $type, [
            'status' => true,
            'message' => 'Copied to a new draft.',
            'id' => $copy->id,
            $this->payloadKey() => $this->present($copy),
        ]);
    }

    // -----------------------------------------------------------------------
    // Media
    // -----------------------------------------------------------------------

    /**
     * Upload one image, audio or video file and return its URL.
     *
     * DigitalOcean first with a local fallback, the same order and the same
     * bucket path every other H5P type uses, so H5P media stays in one place
     * regardless of which type wrote it.
     *
     * Accepted types are declared per role rather than as one permissive list:
     * a background that is allowed to be an mp4 is a background that will be
     * one by accident.
     */
    public function media(Request $request)
    {
        $type = $request->input('type');

        $role = (string) $request->input('role', 'image');
        $rules = $this->mediaRulesFor($role);
        if ($rules === null) {
            return $this->fail($type, 'Unknown upload role.', 422);
        }

        $request->validate(['file' => $rules['validation']]);

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return $this->fail($type, 'That file could not be read. Please choose another.', 422);
        }

        $filename = $this->registryCode() . '_' . $role . '_' . date('Y-m-d_H-i-s') . '_'
            . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $file->getClientOriginalExtension();

        try {
            Storage::disk('digitalocean')->putFileAs('public/h5p_content/', $file, $filename, 'public');
            if (! Storage::disk('digitalocean')->exists('public/h5p_content/' . $filename)) {
                throw new \Exception('DigitalOcean upload failed');
            }
            $url = Storage::disk('digitalocean')->url('public/h5p_content/' . $filename);
        } catch (\Exception $e) {
            $destination = public_path('/h5p_content/');
            if (! file_exists($destination)) {
                mkdir($destination, 0755, true);
            }
            $file->move($destination, $filename);
            $url = asset('/h5p_content/' . $filename);
        }

        $res = ['status' => true, 'message' => 'File uploaded.', 'url' => $url, 'kind' => $rules['kind']];

        if ($this->isApi($type)) {
            return response()->json($res);
        }

        return back()->with('data', $res);
    }

    /**
     * Validation and family for one upload role.
     *
     * A subclass widens this by overriding `mediaRoles()`; the shapes below
     * are the three families and are the same wherever they are accepted.
     *
     * @return array{validation: string, kind: string}|null
     */
    protected function mediaRulesFor(string $role): ?array
    {
        $family = $this->mediaRoles()[$role] ?? null;

        return match ($family) {
            'image' => ['validation' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:8192', 'kind' => 'image'],
            // 64 MB and 32 MB: a slide's clip and a pronunciation sample, not
            // a lecture recording. The content list is where a long video
            // belongs.
            'video' => ['validation' => 'required|file|mimes:mp4,webm,ogg|max:65536', 'kind' => 'video'],
            'audio' => ['validation' => 'required|file|mimes:mp3,wav,ogg,m4a|max:32768', 'kind' => 'audio'],
            default => null,
        };
    }

    /**
     * Upload roles this type accepts, mapped to a media family.
     *
     * @return array<string,string>
     */
    protected function mediaRoles(): array
    {
        return ['image' => 'image'];
    }

    // -----------------------------------------------------------------------
    // Package exchange
    // -----------------------------------------------------------------------

    /** Stream this item as a .h5p package. */
    public function export(Request $request, $id)
    {
        $item = $this->findForTenant($request, $id);
        $item->load($this->relations());

        try {
            $package = $this->exportPackage($item);
        } catch (\Throwable $e) {
            return $this->fail($request->input('type'), $e->getMessage(), 422);
        }

        $this->audit('exported', $item, [
            'filename' => $package['filename'],
            'warnings' => $package['warnings'],
        ]);

        return response()
            ->download($package['path'], $package['filename'], [
                'Content-Type' => 'application/zip',
                // Surfaced so the caller can tell the author something did not
                // make it into the package instead of shipping a broken one.
                'X-H5P-Export-Warnings' => (string) count($package['warnings']),
                // The messages themselves, base64'd because a header cannot
                // carry newlines or non-ASCII and these are sentences.
                'X-H5P-Export-Notes' => base64_encode(json_encode(array_values($package['warnings']))),
            ])
            ->deleteFileAfterSend(true);
    }

    /** Create a new draft from an uploaded .h5p package. */
    public function import(Request $request)
    {
        $type = $request->input('type');
        $subInstituteId = $this->tenant($request);
        $userId = $this->userId($request);

        $request->validate([
            'package' => 'required|file|max:65536',
            'standard_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'chapter_id' => 'required|integer',
        ]);

        try {
            $parsed = $this->parsePackage($request->file('package'), $subInstituteId);
        } catch (\Throwable $e) {
            return $this->fail($type, $e->getMessage(), 422);
        }

        $item = DB::transaction(
            fn () => $this->createFromImport($parsed, $request, $subInstituteId, $userId)
        );

        $item->load($this->relations());
        $this->cacheParams($item);
        $this->audit('imported', $item, [
            'title' => $item->title,
            'warnings' => $parsed['warnings'] ?? [],
        ]);

        return $this->respond($request, $type, [
            'status' => true,
            'message' => 'Package imported as a draft.',
            'id' => $item->id,
            $this->payloadKey() => $this->present($item),
            'warnings' => array_values((array) ($parsed['warnings'] ?? [])),
        ]);
    }

    // -----------------------------------------------------------------------
    // Presentation
    // -----------------------------------------------------------------------

    /**
     * One item, as the API returns it.
     *
     * The derived fields -- label, machine name, max score -- are computed
     * SERVER-SIDE and sent with every row, deliberately. A list page, the
     * player and the analytics pipeline all have to agree on what an item is
     * worth, and three independent counts is three chances to disagree.
     *
     * @return array<string,mixed>
     */
    protected function present(Model $item): array
    {
        $row = $item->toArray();

        $row['label'] = $this->label();
        $row['machine_name'] = (string) config('h5p_libraries.libraries.' . $this->registryCode() . '.machine_name');
        $row['h5p_type'] = $this->registryCode();
        $row['max_score'] = method_exists($item, 'maxScore') ? $item->maxScore() : 0;

        return $row;
    }

    // -----------------------------------------------------------------------
    // Save plumbing
    // -----------------------------------------------------------------------

    /**
     * Soft-delete every child row, stamping who did it.
     *
     * Drives off `relations()` so a type that gains a third child table does
     * not need this rewritten. Relations are walked deepest-last, which is why
     * a subclass with nested children lists them parent-first.
     */
    protected function softDeleteChildren(Model $item, int|string|null $userId): void
    {
        foreach ($this->childRelations() as $relation) {
            if (! method_exists($item, $relation)) {
                continue;
            }
            $item->{$relation}()->get()->each(function (Model $child) use ($userId) {
                $child->deleted_by = $userId;
                $child->save();
                $child->delete();
            });
        }
    }

    /**
     * The relations softDeleteChildren() walks.
     *
     * Defaults to relations(), which is right when every eager-loaded relation
     * is a child. A type that eager-loads something it does not own overrides.
     *
     * @return list<string>
     */
    protected function childRelations(): array
    {
        return array_values(array_filter(
            $this->relations(),
            fn (string $relation) => ! str_contains($relation, '.')
        ));
    }

    /**
     * Refresh the derived params cache after a write.
     *
     * Kept out of the transaction deliberately: it is a cache, and a failure
     * to rebuild it must not roll back a save the author has already been told
     * succeeded. A stale cache is corrected on the next write or ignored by
     * the player, which reads rows.
     */
    protected function cacheParams(Model $item): void
    {
        try {
            $item->forceFill([
                'content_json' => json_encode($this->buildParams($item), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ])->saveQuietly();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * "Term 2 recall" -> "Term 2 recall (copy)" -> "Term 2 recall (copy 2)".
     *
     * Counting existing copies rather than always appending "(copy)" keeps a
     * list of five duplicates distinguishable, which is the state a teacher
     * building a set actually ends up in.
     */
    protected function copyTitle(Model $original, int|string|null $subInstituteId): string
    {
        $base = trim((string) $original->title) ?: $this->label();
        $base = preg_replace('/\s*\(copy(?:\s+\d+)?\)$/iu', '', $base) ?: $base;

        $taken = $this->modelClass()::withTrashed()
            ->where('sub_institute_id', $subInstituteId)
            ->where('chapter_id', $original->chapter_id)
            ->where('title', 'like', $base . ' (copy%')
            ->pluck('title')
            ->map(fn ($t) => strtolower((string) $t))
            ->all();

        $candidate = $base . ' (copy)';
        $n = 1;
        while (in_array(strtolower($candidate), $taken, true)) {
            $n++;
            $candidate = $base . ' (copy ' . $n . ')';
        }

        return $candidate;
    }

    protected function libraryVersionString(): string
    {
        $library = (array) config('h5p_libraries.libraries.' . $this->registryCode(), []);

        return sprintf(
            '%s %d.%d',
            $library['machine_name'] ?? 'H5P.Unknown',
            $library['major_version'] ?? 1,
            $library['minor_version'] ?? 0
        );
    }

    /** @param array<string,mixed> $values */
    protected function audit(string $action, Model $item, array $values): void
    {
        AuditLog::record([
            'module' => 'lms',
            'action' => $this->registryCode() . '_' . $action,
            'entity_type' => $item->getTable(),
            'entity_id' => $item->id,
            'new_values' => $values,
        ]);
    }

    // -----------------------------------------------------------------------
    // Session / tenancy
    // -----------------------------------------------------------------------

    /**
     * The tenant to read and write as.
     *
     * Session first: for `type=API` the session is hydrated from a verified JWT
     * (HydratesLegacyApiSession), so it is the trustworthy value and the
     * request field is only validated, never trusted. The request fallback is
     * for the web path where no API session exists.
     */
    protected function tenant(Request $request): int|string|null
    {
        return session()->get('sub_institute_id') ?? $request->input('sub_institute_id');
    }

    protected function userId(Request $request): int|string|null
    {
        return session()->get('user_id') ?? $request->input('user_id');
    }

    protected function isStudent(Request $request): bool
    {
        $profile = session()->get('user_profile_name') ?? $request->input('user_profile_name', '');

        return strtolower((string) $profile) === 'student';
    }

    /** Every read goes through here, so an id from another school 404s. */
    protected function findForTenant(Request $request, $id): Model
    {
        return $this->modelClass()::with($this->relations())
            ->where('sub_institute_id', $this->tenant($request))
            ->findOrFail($id);
    }

    // -----------------------------------------------------------------------
    // Responses
    // -----------------------------------------------------------------------

    protected function isApi(?string $type): bool
    {
        return in_array($type, ['API', 'JSON'], true);
    }

    /** @param array<string,mixed> $res */
    protected function respond(Request $request, ?string $type, array $res)
    {
        if ($this->isApi($type)) {
            return response()->json($res);
        }

        return redirect()
            ->route($this->routePrefix() . '.index', $this->contextParams($request))
            ->with('data', $res);
    }

    protected function fail(?string $type, string $message, int $status)
    {
        if ($this->isApi($type)) {
            return response()->json(['status' => false, 'message' => $message], $status);
        }

        return back()->with('data', ['status' => false, 'message' => $message]);
    }

    /** @return array<string,mixed> */
    protected function contextParams(Request $request): array
    {
        return [
            'chapter_id' => $request->chapter_id,
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
        ];
    }
}
