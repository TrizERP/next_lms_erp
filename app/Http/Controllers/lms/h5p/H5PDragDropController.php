<?php

namespace App\Http\Controllers\lms\h5p;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\lms\h5p\H5pDragDrop;
use App\Models\lms\h5p\H5pDragDropElement;
use App\Models\lms\h5p\H5pDragDropZone;
use App\Services\lms\H5P\H5PDragQuestionBuilder;
use App\Services\lms\H5P\H5PPackageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use function App\Helpers\is_mobile;

/**
 * H5P Drag and Drop (H5P.DragQuestion) CRUD, publish, and package exchange.
 *
 * Shaped like H5PScenarioController and H5PFlashcardController -- same session
 * handling, same `type=API` JSON contract, same audit entries -- with three
 * differences that the type genuinely needs:
 *
 *  1. WRITES ARE JSON, NOT MULTIPART. A task is a background image plus two
 *     arrays of positioned children that reference each other. Encoding that
 *     as a multipart form (the way the scenario controller does) would mean
 *     re-parsing nested arrays out of flat keys on every save. Images are
 *     uploaded once by `media()` and referenced by URL afterwards, so a save
 *     is a plain JSON document and an autosave costs no image traffic.
 *
 *  2. IDS ARE ASSIGNED IN ONE PASS. Zones and elements point at each other, so
 *     the client sends stable client-side refs, not ids. store()/update()
 *     insert elements first, map ref -> real id, write the zones with those
 *     ids, then back-fill the element side. Nothing downstream ever sees a ref.
 *
 *  3. PUBLISH IS A STATE CHANGE, NOT A SAVE. Student surfaces read
 *     `status = published`; the author keeps editing a draft until they say so.
 */
class H5PDragDropController extends Controller
{
    public function __construct(
        private readonly H5PDragQuestionBuilder $builder,
        private readonly H5PPackageService $packages
    ) {
    }

    // -----------------------------------------------------------------------
    // Read
    // -----------------------------------------------------------------------

    public function index(Request $request)
    {
        $type = $request->input('type');
        $subInstituteId = $this->tenant($request);

        if (in_array($type, ['API', 'JSON'], true)) {
            $request->validate([
                'sub_institute_id' => 'required|integer',
                'standard_id' => 'required|integer',
                'subject_id' => 'required|integer',
                'chapter_id' => 'required|integer',
            ]);
        }

        $query = H5pDragDrop::with(['zones', 'elements'])
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
        $res['dragDropLists'] = $query->orderByDesc('id')->get();
        $res['chapter_id'] = $request->chapter_id;
        $res['standard_id'] = $request->standard_id;
        $res['subject_id'] = $request->subject_id;

        return is_mobile($type, 'lms/h5p/dragdrop/index', $res, 'view');
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $res = $request->all();
        // The defaults the authoring UI opens with, served rather than
        // duplicated client-side, so changing a default is a one-file change.
        $res['defaults'] = [
            'image_fit' => 'contain',
            'canvas_width' => 620,
            'canvas_height' => 310,
            'pass_percentage' => 100,
            'enable_retry' => true,
            'enable_show_solution' => true,
            'enable_check' => true,
            'single_point' => false,
            'apply_penalties' => true,
        ];
        $res['library'] = config('h5p_libraries.libraries.drag_and_drop.machine_name');

        return is_mobile($type, 'lms/h5p/dragdrop/create', $res, 'view');
    }

    public function show(Request $request, $id)
    {
        $type = $request->input('type');
        $task = $this->findForTenant($request, $id);

        if ($this->isStudent($request) && ! $task->isPublished()) {
            return $this->fail($type, 'This activity has not been published yet.', 403);
        }

        $res = $request->all();
        $res['dragDrop'] = $task;
        // The player reads rows, but a caller that wants to hand the task to an
        // H5P runtime gets the params for free rather than rebuilding them.
        $res['params'] = $this->builder->build($task);
        $res['chapter_id'] = $task->chapter_id;
        $res['standard_id'] = $task->standard_id;
        $res['subject_id'] = $task->subject_id;

        return is_mobile($type, 'lms/h5p/dragdrop/show', $res, 'view');
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $task = $this->findForTenant($request, $id);

        $res = $request->all();
        $res['dragDrop'] = $task;
        $res['chapter_id'] = $task->chapter_id;
        $res['standard_id'] = $task->standard_id;
        $res['subject_id'] = $task->subject_id;

        return is_mobile($type, 'lms/h5p/dragdrop/edit', $res, 'view');
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

        $task = DB::transaction(function () use ($request, $data, $subInstituteId, $userId) {
            $task = H5pDragDrop::create($this->taskAttributes($data, $request) + [
                'standard_id' => $request->standard_id,
                'subject_id' => $request->subject_id,
                'chapter_id' => $request->chapter_id,
                'sub_institute_id' => $subInstituteId,
                'syear' => $request->input('syear'),
                'status' => 'draft',
                'library' => $this->libraryVersionString(),
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            $this->syncChildren($task, $data, $subInstituteId, $userId);

            return $task;
        });

        $task->load(['zones', 'elements']);
        $this->cacheParams($task);

        AuditLog::record([
            'module' => 'lms',
            'action' => 'h5p_drag_drop_created',
            'entity_type' => 'h5p_drag_drop',
            'entity_id' => $task->id,
            'new_values' => $task->toArray(),
        ]);

        $res = [
            'status' => true,
            'message' => 'Drag and drop activity created successfully!',
            'id' => $task->id,
            'dragDrop' => $task,
        ];

        if (in_array($type, ['API', 'JSON'], true)) {
            return response()->json($res);
        }

        return redirect()->route('h5p_drag_drop.index', $this->contextParams($request))->with('data', $res);
    }

    public function update(Request $request, $id)
    {
        $type = $request->input('type');
        $subInstituteId = $this->tenant($request);
        $userId = $this->userId($request);

        $task = $this->findForTenant($request, $id);
        $data = $request->validate($this->saveRules());

        DB::transaction(function () use ($task, $request, $data, $subInstituteId, $userId) {
            $task->update($this->taskAttributes($data, $request) + [
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            // Children are replaced wholesale rather than diffed. The client
            // sends refs, not ids, so there is nothing stable to diff against;
            // and a task holds tens of rows, not thousands, so a replace is
            // both correct and cheap. Soft deletes keep the old rows for audit.
            $task->zones()->get()->each(function (H5pDragDropZone $zone) use ($userId) {
                $zone->deleted_by = $userId;
                $zone->save();
                $zone->delete();
            });
            $task->elements()->get()->each(function (H5pDragDropElement $element) use ($userId) {
                $element->deleted_by = $userId;
                $element->save();
                $element->delete();
            });

            $this->syncChildren($task, $data, $subInstituteId, $userId);
        });

        $task->load(['zones', 'elements']);
        $this->cacheParams($task);

        AuditLog::record([
            'module' => 'lms',
            'action' => 'h5p_drag_drop_updated',
            'entity_type' => 'h5p_drag_drop',
            'entity_id' => $task->id,
            'new_values' => $task->toArray(),
        ]);

        $res = [
            'status' => true,
            'message' => 'Drag and drop activity updated successfully!',
            'id' => $task->id,
            'dragDrop' => $task,
        ];

        if (in_array($type, ['API', 'JSON'], true)) {
            return response()->json($res);
        }

        return redirect()->route('h5p_drag_drop.index', $this->contextParams($request))->with('data', $res);
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        $userId = $this->userId($request);
        $task = $this->findForTenant($request, $id);

        DB::transaction(function () use ($task, $userId) {
            $task->zones()->get()->each(fn (H5pDragDropZone $zone) => tap($zone, function ($z) use ($userId) {
                $z->deleted_by = $userId;
                $z->save();
            })->delete());
            $task->elements()->get()->each(fn (H5pDragDropElement $element) => tap($element, function ($e) use ($userId) {
                $e->deleted_by = $userId;
                $e->save();
            })->delete());

            $task->deleted_by = $userId;
            $task->save();
            $task->delete();
        });

        AuditLog::record([
            'module' => 'lms',
            'action' => 'h5p_drag_drop_deleted',
            'entity_type' => 'h5p_drag_drop',
            'entity_id' => $task->id,
            'new_values' => $task->toArray(),
        ]);

        $res = ['status' => true, 'message' => 'Drag and drop activity deleted successfully!'];

        if (in_array($type, ['API', 'JSON'], true)) {
            return response()->json($res);
        }

        return redirect()->route('h5p_drag_drop.index', $this->contextParams($request))->with('data', $res);
    }

    /**
     * Publish or unpublish. Publishing refuses an activity that cannot be
     * scored -- a task with no correct mapping renders fine and awards nothing,
     * which is the kind of thing that is only noticed after a class has sat it.
     */
    public function publish(Request $request, $id)
    {
        $type = $request->input('type');
        $userId = $this->userId($request);
        $task = $this->findForTenant($request, $id);

        $publish = filter_var($request->input('published', true), FILTER_VALIDATE_BOOLEAN);

        if ($publish) {
            $problem = $this->publishBlocker($task);
            if ($problem !== null) {
                return $this->fail($type, $problem, 422);
            }
        }

        $task->update([
            'status' => $publish ? 'published' : 'draft',
            'published_at' => $publish ? now() : null,
            'updated_by' => $userId,
        ]);

        AuditLog::record([
            'module' => 'lms',
            'action' => $publish ? 'h5p_drag_drop_published' : 'h5p_drag_drop_unpublished',
            'entity_type' => 'h5p_drag_drop',
            'entity_id' => $task->id,
            'new_values' => ['status' => $task->status, 'published_at' => $task->published_at],
        ]);

        $res = [
            'status' => true,
            'message' => $publish ? 'Activity published.' : 'Activity moved back to draft.',
            'dragDrop' => $task->fresh(['zones', 'elements']),
        ];

        if (in_array($type, ['API', 'JSON'], true)) {
            return response()->json($res);
        }

        return redirect()->route('h5p_drag_drop.index', $this->contextParams($request))->with('data', $res);
    }

    // -----------------------------------------------------------------------
    // Media
    // -----------------------------------------------------------------------

    /**
     * Upload one image (background or draggable) and return its URL.
     *
     * DigitalOcean first with a local fallback, the same order and the same
     * bucket path the scenario controller uses, so H5P media stays in one place
     * regardless of which type wrote it.
     */
    public function media(Request $request)
    {
        $type = $request->input('type');

        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:8192',
            'role' => 'nullable|in:background,element',
        ]);

        $file = $request->file('image');
        if (! $file || ! $file->isValid()) {
            return $this->fail($type, 'That image could not be read. Please choose another file.', 422);
        }

        $role = $request->input('role', 'element');
        $filename = 'dragdrop_' . $role . '_' . date('Y-m-d_H-i-s') . '_' . substr(bin2hex(random_bytes(4)), 0, 8)
            . '.' . $file->getClientOriginalExtension();

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

        $res = ['status' => true, 'message' => 'Image uploaded.', 'url' => $url];

        if (in_array($type, ['API', 'JSON'], true)) {
            return response()->json($res);
        }

        return back()->with('data', $res);
    }

    // -----------------------------------------------------------------------
    // Package exchange
    // -----------------------------------------------------------------------

    /** Stream this activity as a .h5p package. */
    public function export(Request $request, $id)
    {
        $task = $this->findForTenant($request, $id);
        $task->load(['zones', 'elements']);

        try {
            $package = $this->packages->export($task);
        } catch (\Throwable $e) {
            return $this->fail($request->input('type'), $e->getMessage(), 422);
        }

        AuditLog::record([
            'module' => 'lms',
            'action' => 'h5p_drag_drop_exported',
            'entity_type' => 'h5p_drag_drop',
            'entity_id' => $task->id,
            'new_values' => ['filename' => $package['filename'], 'warnings' => $package['warnings']],
        ]);

        return response()
            ->download($package['path'], $package['filename'], [
                'Content-Type' => 'application/zip',
                // Surfaced so the caller can tell the author an image did not
                // make it into the package instead of shipping a broken one.
                'X-H5P-Export-Warnings' => (string) count($package['warnings']),
            ])
            ->deleteFileAfterSend(true);
    }

    /** Create a new activity from an uploaded .h5p package. */
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
            $parsed = $this->packages->import($request->file('package'), $subInstituteId);
        } catch (\Throwable $e) {
            return $this->fail($type, $e->getMessage(), 422);
        }

        // The parsed package speaks in refs, exactly like a client save does,
        // so it goes through the same write path rather than a second one.
        $data = [
            'title' => $parsed['title'],
            'description' => '',
            'zones' => array_map(function (array $zone) {
                $zone['ref'] = $zone['_ref'];
                $zone['correct_element_refs'] = $zone['_correct_element_refs'];

                return $zone;
            }, $parsed['zones']),
            'elements' => array_map(function (array $element) {
                $element['ref'] = $element['_ref'];
                $element['drop_zone_refs'] = $element['_drop_zone_refs'];

                return $element;
            }, $parsed['elements']),
        ] + $parsed['task'];

        $task = DB::transaction(function () use ($request, $data, $parsed, $subInstituteId, $userId) {
            $task = H5pDragDrop::create([
                'title' => $data['title'],
                'description' => '',
                'task_description' => $parsed['task']['task_description'] ?? '',
                'background_image' => $parsed['task']['background_image'] ?? null,
                'image_fit' => $parsed['task']['image_fit'] ?? 'contain',
                'canvas_width' => $parsed['task']['canvas_width'],
                'canvas_height' => $parsed['task']['canvas_height'],
                'pass_percentage' => $parsed['task']['pass_percentage'],
                'enable_retry' => $parsed['task']['enable_retry'],
                'enable_show_solution' => $parsed['task']['enable_show_solution'],
                'enable_check' => $parsed['task']['enable_check'],
                'single_point' => $parsed['task']['single_point'],
                'apply_penalties' => $parsed['task']['apply_penalties'],
                'background_opacity_full' => $parsed['task']['background_opacity_full'],
                'standard_id' => $request->standard_id,
                'subject_id' => $request->subject_id,
                'chapter_id' => $request->chapter_id,
                'sub_institute_id' => $subInstituteId,
                'syear' => $request->input('syear'),
                // An imported activity always lands as a draft. The teacher
                // checks it against this chapter before students see it.
                'status' => 'draft',
                'library' => $this->libraryVersionString(),
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            $this->syncChildren($task, $data, $subInstituteId, $userId);

            return $task;
        });

        $task->load(['zones', 'elements']);
        $this->cacheParams($task);

        AuditLog::record([
            'module' => 'lms',
            'action' => 'h5p_drag_drop_imported',
            'entity_type' => 'h5p_drag_drop',
            'entity_id' => $task->id,
            'new_values' => ['title' => $task->title, 'warnings' => $parsed['warnings']],
        ]);

        $res = [
            'status' => true,
            'message' => 'Package imported as a draft.',
            'id' => $task->id,
            'dragDrop' => $task,
            'warnings' => $parsed['warnings'],
        ];

        if (in_array($type, ['API', 'JSON'], true)) {
            return response()->json($res);
        }

        return redirect()->route('h5p_drag_drop.index', $this->contextParams($request))->with('data', $res);
    }

    // -----------------------------------------------------------------------
    // Save plumbing
    // -----------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function saveRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'task_description' => 'nullable|string',
            'background_image' => 'nullable|string|max:2048',
            // contain keeps the whole image visible and keeps the percentage
            // geometry aligned with it; the rest are deliberate author choices.
            'image_fit' => 'nullable|in:contain,cover,original,stretch',
            'canvas_width' => 'nullable|integer|min:200|max:4000',
            'canvas_height' => 'nullable|integer|min:120|max:4000',
            'pass_percentage' => 'nullable|integer|min:0|max:100',
            'enable_retry' => 'nullable|boolean',
            'enable_show_solution' => 'nullable|boolean',
            'enable_check' => 'nullable|boolean',
            'single_point' => 'nullable|boolean',
            'apply_penalties' => 'nullable|boolean',
            'background_opacity_full' => 'nullable|boolean',

            'elements' => 'required|array|min:1',
            'elements.*.ref' => 'required|string|max:64',
            'elements.*.element_type' => 'required|in:text,image',
            // A text draggable with no text and an image draggable with no
            // image are both invisible on the canvas; required_if catches each.
            'elements.*.text' => 'nullable|required_if:elements.*.element_type,text|string|max:1000',
            'elements.*.image_path' => 'nullable|required_if:elements.*.element_type,image|string|max:2048',
            'elements.*.image_alt' => 'nullable|string|max:255',
            'elements.*.position_x' => 'required|numeric|min:0|max:100',
            'elements.*.position_y' => 'required|numeric|min:0|max:100',
            'elements.*.width' => 'required|numeric|min:1|max:100',
            'elements.*.height' => 'required|numeric|min:1|max:100',
            'elements.*.multiple' => 'nullable|boolean',
            'elements.*.drop_zone_refs' => 'nullable|array',
            'elements.*.drop_zone_refs.*' => 'string|max:64',

            'zones' => 'required|array|min:1',
            'zones.*.ref' => 'required|string|max:64',
            'zones.*.label' => 'nullable|string|max:255',
            'zones.*.tip' => 'nullable|string|max:1000',
            'zones.*.position_x' => 'required|numeric|min:0|max:100',
            'zones.*.position_y' => 'required|numeric|min:0|max:100',
            'zones.*.width' => 'required|numeric|min:1|max:100',
            'zones.*.height' => 'required|numeric|min:1|max:100',
            'zones.*.single' => 'nullable|boolean',
            'zones.*.auto_align' => 'nullable|boolean',
            'zones.*.show_label' => 'nullable|boolean',
            'zones.*.correct_element_refs' => 'nullable|array',
            'zones.*.correct_element_refs.*' => 'string|max:64',
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function taskAttributes(array $data, Request $request): array
    {
        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'task_description' => $data['task_description'] ?? '',
            'background_image' => $data['background_image'] ?? null,
            'image_fit' => $data['image_fit'] ?? 'contain',
            'canvas_width' => $data['canvas_width'] ?? 620,
            'canvas_height' => $data['canvas_height'] ?? 310,
            'pass_percentage' => $data['pass_percentage'] ?? 100,
            'enable_retry' => $data['enable_retry'] ?? true,
            'enable_show_solution' => $data['enable_show_solution'] ?? true,
            'enable_check' => $data['enable_check'] ?? true,
            'single_point' => $data['single_point'] ?? false,
            'apply_penalties' => $data['apply_penalties'] ?? true,
            'background_opacity_full' => $data['background_opacity_full'] ?? true,
        ];
    }

    /**
     * Write both child tables and resolve the ref mapping between them.
     *
     * Elements go in first so zones can be written with real element ids in one
     * pass; the element side is then back-filled with the zone ids. That is one
     * UPDATE per element, which is why refs never reach the database.
     *
     * @param  array<string,mixed>  $data
     */
    private function syncChildren(H5pDragDrop $task, array $data, int|string|null $subInstituteId, int|string|null $userId): void
    {
        $audit = [
            'sub_institute_id' => $subInstituteId,
            'created_by' => $userId,
            'created_at' => now(),
        ];

        $elementIdByRef = [];
        foreach (array_values($data['elements']) as $order => $element) {
            $row = H5pDragDropElement::create([
                'drag_drop_id' => $task->id,
                'element_type' => $element['element_type'],
                'text' => $element['element_type'] === 'text' ? ($element['text'] ?? '') : null,
                'image_path' => $element['element_type'] === 'image' ? ($element['image_path'] ?? '') : null,
                'image_alt' => $element['image_alt'] ?? null,
                'position_x' => $element['position_x'],
                'position_y' => $element['position_y'],
                'width' => $element['width'],
                'height' => $element['height'],
                'multiple' => $element['multiple'] ?? false,
                'drop_zone_ids' => [],
                'sort_order' => $order,
            ] + $audit);

            $elementIdByRef[(string) $element['ref']] = $row->id;
        }

        $zoneIdByRef = [];
        foreach (array_values($data['zones']) as $order => $zone) {
            $row = H5pDragDropZone::create([
                'drag_drop_id' => $task->id,
                'label' => $zone['label'] ?? '',
                'tip' => $zone['tip'] ?? '',
                'position_x' => $zone['position_x'],
                'position_y' => $zone['position_y'],
                'width' => $zone['width'],
                'height' => $zone['height'],
                'single' => $zone['single'] ?? true,
                'auto_align' => $zone['auto_align'] ?? true,
                'show_label' => $zone['show_label'] ?? true,
                'correct_element_ids' => $this->resolveRefs($zone['correct_element_refs'] ?? [], $elementIdByRef),
                'sort_order' => $order,
            ] + $audit);

            $zoneIdByRef[(string) $zone['ref']] = $row->id;
        }

        foreach ($data['elements'] as $element) {
            $id = $elementIdByRef[(string) $element['ref']] ?? null;
            if ($id === null) {
                continue;
            }
            H5pDragDropElement::where('id', $id)->update([
                'drop_zone_ids' => json_encode($this->resolveRefs($element['drop_zone_refs'] ?? [], $zoneIdByRef)),
            ]);
        }
    }

    /**
     * @param  array<int,mixed>  $refs
     * @param  array<string,int>  $map
     * @return list<int>
     */
    private function resolveRefs(array $refs, array $map): array
    {
        $ids = [];
        foreach ($refs as $ref) {
            $key = (string) $ref;
            if (isset($map[$key])) {
                $ids[] = (int) $map[$key];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Refresh the derived params cache after a write.
     *
     * Kept out of the transaction deliberately: it is a cache, and a failure to
     * rebuild it must not roll back a save the author has already been told
     * succeeded. A stale cache is corrected on the next write or ignored by the
     * player, which reads rows.
     */
    private function cacheParams(H5pDragDrop $task): void
    {
        try {
            $task->forceFill([
                'content_json' => json_encode($this->builder->build($task), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ])->saveQuietly();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Why an activity cannot be published yet, or null if it can.
     */
    private function publishBlocker(H5pDragDrop $task): ?string
    {
        $task->load(['zones', 'elements']);

        if ($task->elements->isEmpty()) {
            return 'Add at least one draggable before publishing.';
        }
        if ($task->zones->isEmpty()) {
            return 'Add at least one drop zone before publishing.';
        }

        $mapped = $task->zones->contains(fn (H5pDragDropZone $zone) => ! empty($zone->correct_element_ids));
        if (! $mapped) {
            return 'No drop zone has a correct draggable yet, so this activity cannot be scored.';
        }

        return null;
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
    private function tenant(Request $request): int|string|null
    {
        return session()->get('sub_institute_id') ?? $request->input('sub_institute_id');
    }

    private function userId(Request $request): int|string|null
    {
        return session()->get('user_id') ?? $request->input('user_id');
    }

    private function isStudent(Request $request): bool
    {
        $profile = session()->get('user_profile_name') ?? $request->input('user_profile_name', '');

        return strtolower((string) $profile) === 'student';
    }

    private function findForTenant(Request $request, $id): H5pDragDrop
    {
        return H5pDragDrop::with(['zones', 'elements'])
            ->where('sub_institute_id', $this->tenant($request))
            ->findOrFail($id);
    }

    /** @return array<string,mixed> */
    private function contextParams(Request $request): array
    {
        return [
            'chapter_id' => $request->chapter_id,
            'standard_id' => $request->standard_id,
            'subject_id' => $request->subject_id,
        ];
    }

    private function libraryVersionString(): string
    {
        $library = (array) config('h5p_libraries.libraries.drag_and_drop', []);

        return sprintf(
            '%s %d.%d',
            $library['machine_name'] ?? 'H5P.DragQuestion',
            $library['major_version'] ?? 1,
            $library['minor_version'] ?? 14
        );
    }

    private function fail(?string $type, string $message, int $status)
    {
        if (in_array($type, ['API', 'JSON'], true)) {
            return response()->json(['status' => false, 'message' => $message], $status);
        }

        return back()->with('data', ['status' => false, 'message' => $message]);
    }
}
