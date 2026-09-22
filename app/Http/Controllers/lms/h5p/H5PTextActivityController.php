<?php

namespace App\Http\Controllers\lms\h5p;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\lms\h5p\H5pTextActivity;
use App\Models\lms\h5p\H5pTextActivityBlank;
use App\Services\lms\H5P\H5PTextActivityBuilder;
use App\Services\lms\H5P\H5PTextPackageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use function App\Helpers\is_mobile;

/**
 * CRUD, publish, duplicate and package exchange for the three text-passage
 * H5P types: Drag the Words, Fill in the Blanks, Mark the Words.
 *
 * ONE CONTROLLER, THREE SUBCLASSES.
 *
 * The three differ in exactly two ways -- which `content_type` they read and
 * write, and which fields the authoring UI shows -- and agree on everything
 * else: tenancy, the `type=API` JSON contract, the draft/published rule, the
 * audit entries, the answer-key re-parse on save, and the whole of package
 * exchange. Three copies of that would be three places to fix the next
 * draft-leak bug. So the behaviour is here and each subclass supplies its
 * type, its route prefix and its noun (see H5PDragTextController and its two
 * siblings, each about ten lines).
 *
 * WHERE IT DIFFERS FROM H5PDragDropController, AND WHY
 *
 *  1. WRITES ARE MULTIPART-OR-JSON. A drag-and-drop save is a nested document
 *     and had to be JSON. A text activity is a handful of scalars plus one
 *     passage string, so it accepts either encoding and the frontend can post
 *     a plain form. Validation is identical for both.
 *
 *  2. THE ANSWER KEY IS DERIVED, NOT SENT. The client never sends blanks. The
 *     passage is the only authored artefact, and the child rows are re-parsed
 *     from it on every write (H5PTextActivityBuilder::parseAnswerKey). That is
 *     what makes it impossible for the stored answer key to disagree with the
 *     passage the learner is shown -- there is no second place to edit it.
 *
 *  3. DUPLICATE EXISTS. Teachers build a term's worth of cloze passages by
 *     varying one, which was the actual request behind this endpoint. A copy
 *     always lands as a draft, whatever the original's state.
 */
abstract class H5PTextActivityController extends Controller
{
    public function __construct(
        protected readonly H5PTextActivityBuilder $builder,
        protected readonly H5PTextPackageService $packages
    ) {
    }

    // -----------------------------------------------------------------------
    // What each subclass supplies
    // -----------------------------------------------------------------------

    /** The `content_type` discriminator: one of H5pTextActivity::TYPES. */
    abstract protected function contentType(): string;

    /** Laravel route prefix, e.g. `h5p_blanks`. */
    abstract protected function routePrefix(): string;

    /** Blade view directory under `lms/h5p/`. */
    abstract protected function viewDirectory(): string;

    /** Sentence-case noun for user-facing messages, e.g. "fill in the blanks activity". */
    protected function noun(): string
    {
        return strtolower(H5pTextActivity::LABELS[$this->contentType()] ?? 'activity') . ' activity';
    }

    /** Key the payload is returned under, so a caller can find its row. */
    protected function payloadKey(): string
    {
        return 'activity';
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

        $query = H5pTextActivity::with('blanks')
            ->ofType($this->contentType())
            ->where('sub_institute_id', $subInstituteId)
            ->where('standard_id', $request->standard_id)
            ->where('subject_id', $request->subject_id)
            ->where('chapter_id', $request->chapter_id);

        // A student never sees a draft. This is the only place the rule lives,
        // so a draft cannot leak through the list into a player deep link.
        if ($this->isStudent($request)) {
            $query->where('status', 'published');
        }

        $rows = $query->orderByDesc('id')->get();

        $res = $request->all();
        $res['activityLists'] = $rows->map(fn (H5pTextActivity $a) => $this->present($a))->values();
        $res['content_type'] = $this->contentType();
        $res['library'] = $this->builder->machineName($this->contentType());
        $res['chapter_id'] = $request->chapter_id;
        $res['standard_id'] = $request->standard_id;
        $res['subject_id'] = $request->subject_id;

        return is_mobile($type, 'lms/h5p/' . $this->viewDirectory() . '/index', $res, 'view');
    }

    public function create(Request $request)
    {
        $type = $request->input('type');
        $res = $request->all();

        // The defaults the authoring UI opens with, served rather than
        // duplicated client-side, so changing a default is a one-file change.
        $res['defaults'] = $this->defaults();
        $res['content_type'] = $this->contentType();
        $res['library'] = $this->builder->machineName($this->contentType());

        return is_mobile($type, 'lms/h5p/' . $this->viewDirectory() . '/create', $res, 'view');
    }

    public function show(Request $request, $id)
    {
        $type = $request->input('type');
        $activity = $this->findForTenant($request, $id);

        if ($this->isStudent($request) && ! $activity->isPublished()) {
            return $this->fail($type, 'This activity has not been published yet.', 403);
        }

        $res = $request->all();
        $res[$this->payloadKey()] = $this->present($activity);
        // The player reads rows, but a caller that wants to hand the activity
        // to an H5P runtime gets the params for free rather than rebuilding.
        $res['params'] = $this->builder->build($activity);
        $res['chapter_id'] = $activity->chapter_id;
        $res['standard_id'] = $activity->standard_id;
        $res['subject_id'] = $activity->subject_id;

        return is_mobile($type, 'lms/h5p/' . $this->viewDirectory() . '/show', $res, 'view');
    }

    public function edit(Request $request, $id)
    {
        $type = $request->input('type');
        $activity = $this->findForTenant($request, $id);

        $res = $request->all();
        $res[$this->payloadKey()] = $this->present($activity);
        $res['defaults'] = $this->defaults();
        $res['chapter_id'] = $activity->chapter_id;
        $res['standard_id'] = $activity->standard_id;
        $res['subject_id'] = $activity->subject_id;

        return is_mobile($type, 'lms/h5p/' . $this->viewDirectory() . '/edit', $res, 'view');
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

        $activity = DB::transaction(function () use ($request, $data, $subInstituteId, $userId) {
            $activity = H5pTextActivity::create($this->attributes($data) + [
                'content_type' => $this->contentType(),
                'standard_id' => $request->standard_id,
                'subject_id' => $request->subject_id,
                'chapter_id' => $request->chapter_id,
                'sub_institute_id' => $subInstituteId,
                'syear' => $request->input('syear'),
                'status' => 'draft',
                'library' => $this->builder->libraryVersionString($this->contentType()),
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            $this->syncBlanks($activity, $subInstituteId, $userId);

            return $activity;
        });

        $activity->load('blanks');
        $this->cacheParams($activity);

        $this->audit('created', $activity);

        return $this->respond($request, $type, [
            'status' => true,
            'message' => ucfirst($this->noun()) . ' created successfully!',
            'id' => $activity->id,
            $this->payloadKey() => $this->present($activity),
        ]);
    }

    public function update(Request $request, $id)
    {
        $type = $request->input('type');
        $subInstituteId = $this->tenant($request);
        $userId = $this->userId($request);

        $activity = $this->findForTenant($request, $id);
        $data = $request->validate($this->saveRules());

        DB::transaction(function () use ($activity, $data, $subInstituteId, $userId) {
            $activity->update($this->attributes($data) + [
                'library' => $this->builder->libraryVersionString($this->contentType()),
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            // The answer key is replaced wholesale rather than diffed: it is
            // derived from the passage, so there is nothing stable to diff
            // against and an activity holds tens of rows, not thousands. Soft
            // deletes keep the old key for audit.
            $this->clearBlanks($activity, $userId);
            $this->syncBlanks($activity, $subInstituteId, $userId);
        });

        $activity->load('blanks');
        $this->cacheParams($activity);

        $this->audit('updated', $activity);

        return $this->respond($request, $type, [
            'status' => true,
            'message' => ucfirst($this->noun()) . ' updated successfully!',
            'id' => $activity->id,
            $this->payloadKey() => $this->present($activity),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $type = $request->input('type');
        $userId = $this->userId($request);
        $activity = $this->findForTenant($request, $id);

        DB::transaction(function () use ($activity, $userId) {
            $this->clearBlanks($activity, $userId);

            $activity->deleted_by = $userId;
            $activity->save();
            $activity->delete();
        });

        $this->audit('deleted', $activity);

        return $this->respond($request, $type, [
            'status' => true,
            'message' => ucfirst($this->noun()) . ' deleted successfully!',
        ]);
    }

    /**
     * Publish or unpublish.
     *
     * Publishing refuses an activity that cannot be scored. An activity whose
     * passage marks no answers renders perfectly and awards nothing, which is
     * the kind of thing only noticed after a class has sat it -- the same
     * reasoning as H5PDragDropController::publishBlocker().
     */
    public function publish(Request $request, $id)
    {
        $type = $request->input('type');
        $userId = $this->userId($request);
        $activity = $this->findForTenant($request, $id);

        $publish = filter_var($request->input('published', true), FILTER_VALIDATE_BOOLEAN);

        if ($publish) {
            $problem = $this->publishBlocker($activity);
            if ($problem !== null) {
                return $this->fail($type, $problem, 422);
            }
        }

        $activity->update([
            'status' => $publish ? 'published' : 'draft',
            'published_at' => $publish ? now() : null,
            'updated_by' => $userId,
        ]);

        $this->audit($publish ? 'published' : 'unpublished', $activity, [
            'status' => $activity->status,
            'published_at' => $activity->published_at,
        ]);

        return $this->respond($request, $type, [
            'status' => true,
            'message' => $publish ? 'Activity published.' : 'Activity moved back to draft.',
            $this->payloadKey() => $this->present($activity->fresh('blanks')),
        ]);
    }

    /**
     * Copy an activity into a new draft in the same chapter.
     *
     * The answer key is re-parsed from the copied passage rather than copied
     * row by row, so a duplicate goes through exactly the same derivation as a
     * save. A copy of a published activity is still a draft: duplicating is
     * how a teacher starts the NEXT one, and it must not put an unreviewed
     * variant in front of a class.
     */
    public function duplicate(Request $request, $id)
    {
        $type = $request->input('type');
        $subInstituteId = $this->tenant($request);
        $userId = $this->userId($request);

        $original = $this->findForTenant($request, $id);

        $copy = DB::transaction(function () use ($original, $request, $subInstituteId, $userId) {
            $attributes = $original->only([
                'content_type', 'description', 'task_description', 'passage', 'distractors',
                'media_image', 'media_alt', 'enable_retry', 'enable_show_solution', 'enable_check',
                'case_sensitive', 'accept_spelling_errors', 'instant_feedback', 'show_score_points',
                'separate_lines', 'solution_requires_input', 'points_per_blank', 'pass_percentage',
                'feedback_bands', 'standard_id', 'subject_id', 'chapter_id', 'syear',
            ]);

            $copy = H5pTextActivity::create($attributes + [
                'title' => $this->copyTitle($original, $subInstituteId),
                'sub_institute_id' => $subInstituteId,
                'status' => 'draft',
                'published_at' => null,
                'library' => $this->builder->libraryVersionString($this->contentType()),
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            $this->syncBlanks($copy, $subInstituteId, $userId);

            return $copy;
        });

        $copy->load('blanks');
        $this->cacheParams($copy);

        $this->audit('duplicated', $copy, [
            'copied_from' => $original->id,
            'title' => $copy->title,
        ]);

        return $this->respond($request, $type, [
            'status' => true,
            'message' => 'Copied to a new draft.',
            'id' => $copy->id,
            $this->payloadKey() => $this->present($copy),
        ]);
    }

    /**
     * "Term 2 cloze" -> "Term 2 cloze (copy)" -> "Term 2 cloze (copy 2)".
     *
     * Counting existing copies rather than always appending "(copy)" keeps a
     * list of five duplicates distinguishable, which is the state a teacher
     * building a set actually ends up in.
     */
    private function copyTitle(H5pTextActivity $original, int|string|null $subInstituteId): string
    {
        $base = trim((string) $original->title) ?: (H5pTextActivity::LABELS[$this->contentType()] ?? 'Activity');
        $base = preg_replace('/\s*\(copy(?:\s+\d+)?\)$/iu', '', $base) ?: $base;

        $taken = H5pTextActivity::withTrashed()
            ->ofType($this->contentType())
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

        return mb_substr($candidate, 0, 255);
    }

    // -----------------------------------------------------------------------
    // Media
    // -----------------------------------------------------------------------

    /**
     * Upload the optional illustration and return its URL.
     *
     * DigitalOcean first with a local fallback, the same order and the same
     * bucket path the scenario and drag-and-drop controllers use, so H5P media
     * stays in one place regardless of which type wrote it.
     */
    public function media(Request $request)
    {
        $type = $request->input('type');

        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:8192',
        ]);

        $file = $request->file('image');
        if (! $file || ! $file->isValid()) {
            return $this->fail($type, 'That image could not be read. Please choose another file.', 422);
        }

        $filename = 'textactivity_' . date('Y-m-d_H-i-s') . '_' . substr(bin2hex(random_bytes(4)), 0, 8)
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
        $activity = $this->findForTenant($request, $id);

        try {
            $package = $this->packages->export($activity);
        } catch (\Throwable $e) {
            return $this->fail($request->input('type'), $e->getMessage(), 422);
        }

        $this->audit('exported', $activity, [
            'filename' => $package['filename'],
            'warnings' => $package['warnings'],
        ]);

        return response()
            ->download($package['path'], $package['filename'], [
                'Content-Type' => 'application/zip',
                // Surfaced so the caller can tell the author something was
                // flattened or left out instead of shipping a broken package.
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
            $parsed = $this->packages->import($request->file('package'), $this->contentType(), $subInstituteId);
        } catch (\Throwable $e) {
            return $this->fail($type, $e->getMessage(), 422);
        }

        $activity = DB::transaction(function () use ($request, $parsed, $subInstituteId, $userId) {
            $activity = H5pTextActivity::create($parsed['activity'] + [
                'title' => $parsed['title'],
                'description' => '',
                'points_per_blank' => 1,
                'standard_id' => $request->standard_id,
                'subject_id' => $request->subject_id,
                'chapter_id' => $request->chapter_id,
                'sub_institute_id' => $subInstituteId,
                'syear' => $request->input('syear'),
                // An imported activity always lands as a draft. The teacher
                // checks it against this chapter before students see it.
                'status' => 'draft',
                'library' => $this->builder->libraryVersionString($this->contentType()),
                'created_by' => $userId,
                'created_at' => now(),
            ]);

            // Re-parsed from the imported passage rather than trusting the
            // package's own key, so an import lands in exactly the state a
            // save would have produced from the same passage.
            $this->syncBlanks($activity, $subInstituteId, $userId);

            return $activity;
        });

        $activity->load('blanks');
        $this->cacheParams($activity);

        $this->audit('imported', $activity, [
            'title' => $activity->title,
            'warnings' => $parsed['warnings'],
        ]);

        return $this->respond($request, $type, [
            'status' => true,
            'message' => 'Package imported as a draft.',
            'id' => $activity->id,
            $this->payloadKey() => $this->present($activity),
            'warnings' => $parsed['warnings'],
        ]);
    }

    // -----------------------------------------------------------------------
    // Save plumbing
    // -----------------------------------------------------------------------

    /**
     * Validation, shared by all three types.
     *
     * Type-specific fields are validated but not required: `distractors` is
     * meaningless outside Drag the Words and `case_sensitive` outside Fill in
     * the Blanks, and attributes() drops each where it does not apply. Letting
     * them through validation and discarding them there keeps one rule set and
     * one place that decides what a type stores.
     *
     * @return array<string,mixed>
     */
    protected function saveRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'task_description' => 'nullable|string|max:5000',
            // The passage IS the activity, so it is required and bounded
            // rather than nullable -- an activity with no passage has nothing
            // to render and nothing to score.
            'passage' => 'required|string|max:20000',
            'distractors' => 'nullable|string|max:2000',
            'media_image' => 'nullable|string|max:2048',
            'media_alt' => 'nullable|string|max:255',

            'enable_retry' => 'nullable|boolean',
            'enable_show_solution' => 'nullable|boolean',
            'enable_check' => 'nullable|boolean',
            'case_sensitive' => 'nullable|boolean',
            'accept_spelling_errors' => 'nullable|boolean',
            'instant_feedback' => 'nullable|boolean',
            'show_score_points' => 'nullable|boolean',
            'separate_lines' => 'nullable|boolean',
            'solution_requires_input' => 'nullable|boolean',

            'points_per_blank' => 'nullable|integer|min:1|max:100',
            'pass_percentage' => 'nullable|integer|min:0|max:100',

            'feedback_bands' => 'nullable|array|max:20',
            'feedback_bands.*.from' => 'required|integer|min:0|max:100',
            'feedback_bands.*.to' => 'required|integer|min:0|max:100',
            'feedback_bands.*.feedback' => 'nullable|string|max:500',
        ];
    }

    /**
     * Row attributes from validated input, with the fields this type has no
     * use for normalised away rather than stored as misleading values.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function attributes(array $data): array
    {
        $type = $this->contentType();

        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'task_description' => $data['task_description'] ?? '',
            'passage' => $data['passage'],
            // Only Drag the Words has spare draggables to offer.
            'distractors' => $type === 'drag_text' ? ($data['distractors'] ?? '') : '',
            'media_image' => $data['media_image'] ?? null,
            'media_alt' => $data['media_alt'] ?? null,

            'enable_retry' => $data['enable_retry'] ?? true,
            'enable_show_solution' => $data['enable_show_solution'] ?? true,
            'enable_check' => $data['enable_check'] ?? true,

            // Typed input only exists in Fill in the Blanks; the other two
            // compare words the learner never retyped, so a case or spelling
            // rule there would be a setting that does nothing.
            'case_sensitive' => $type === 'fill_in_the_blanks' ? ($data['case_sensitive'] ?? false) : false,
            'accept_spelling_errors' => $type === 'fill_in_the_blanks' ? ($data['accept_spelling_errors'] ?? false) : false,
            'separate_lines' => $type === 'fill_in_the_blanks' ? ($data['separate_lines'] ?? false) : false,
            'solution_requires_input' => $type === 'fill_in_the_blanks' ? ($data['solution_requires_input'] ?? true) : true,

            // Mark the Words has no partial state to give instant feedback on
            // -- a word is marked or it is not, and the Check button is the
            // moment of truth.
            'instant_feedback' => $type === 'mark_the_words' ? false : ($data['instant_feedback'] ?? false),
            'show_score_points' => $data['show_score_points'] ?? true,

            'points_per_blank' => $data['points_per_blank'] ?? 1,
            'pass_percentage' => $data['pass_percentage'] ?? 100,
            'feedback_bands' => $this->normaliseBands($data['feedback_bands'] ?? null),
        ];
    }

    /**
     * Sort the bands and clamp each to 0-100.
     *
     * H5P reads them in order and an out-of-order list shows the wrong message
     * for a score, which looks like a scoring bug and is not one.
     *
     * @return list<array<string,mixed>>|null
     */
    private function normaliseBands(?array $bands): ?array
    {
        if ($bands === null || $bands === []) {
            return null;
        }

        $out = [];
        foreach ($bands as $band) {
            $from = max(0, min(100, (int) ($band['from'] ?? 0)));
            $to = max(0, min(100, (int) ($band['to'] ?? 100)));
            if ($to < $from) {
                [$from, $to] = [$to, $from];
            }
            $out[] = ['from' => $from, 'to' => $to, 'feedback' => trim((string) ($band['feedback'] ?? ''))];
        }

        usort($out, fn (array $a, array $b) => $a['from'] <=> $b['from']);

        return $out;
    }

    /**
     * Re-derive the answer key from the saved passage.
     *
     * Called after every write. The client never sends blanks, so this is the
     * only way they are created and there is no path by which the key can
     * disagree with the passage.
     */
    protected function syncBlanks(H5pTextActivity $activity, int|string|null $subInstituteId, int|string|null $userId): void
    {
        $slots = $this->builder->parseAnswerKey(
            (string) $activity->content_type,
            $activity->passage,
            $activity->distractors
        );

        $audit = [
            'sub_institute_id' => $subInstituteId,
            'created_by' => $userId,
            'created_at' => now(),
        ];

        foreach ($slots as $slot) {
            H5pTextActivityBlank::create([
                'text_activity_id' => $activity->id,
                'blank_index' => $slot['blank_index'],
                'solution' => mb_substr((string) $slot['solution'], 0, 500),
                'alternatives' => array_values(array_map(
                    fn ($a) => mb_substr((string) $a, 0, 500),
                    (array) ($slot['alternatives'] ?? [])
                )),
                'tip' => $slot['tip'] !== null ? mb_substr((string) $slot['tip'], 0, 500) : null,
                'is_distractor' => (bool) ($slot['is_distractor'] ?? false),
            ] + $audit);
        }
    }

    protected function clearBlanks(H5pTextActivity $activity, int|string|null $userId): void
    {
        $activity->blanks()->get()->each(function (H5pTextActivityBlank $blank) use ($userId) {
            $blank->deleted_by = $userId;
            $blank->save();
            $blank->delete();
        });
    }

    /**
     * Refresh the derived params cache after a write.
     *
     * Kept out of the transaction deliberately: it is a cache, and a failure
     * to rebuild it must not roll back a save the author has already been told
     * succeeded. A stale cache is corrected on the next write or ignored by
     * the player, which reads rows.
     */
    protected function cacheParams(H5pTextActivity $activity): void
    {
        try {
            $activity->forceFill([
                'content_json' => json_encode(
                    $this->builder->build($activity),
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ),
            ])->saveQuietly();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Why an activity cannot be published yet, or null if it can. */
    protected function publishBlocker(H5pTextActivity $activity): ?string
    {
        $activity->loadMissing('blanks');

        if (trim((string) $activity->passage) === '') {
            return 'Add the text passage before publishing.';
        }

        $scorable = $activity->blanks->where('is_distractor', false);
        if ($scorable->isEmpty()) {
            return 'No answers are marked in the passage yet, so this activity cannot be scored. '
                . 'Wrap each answer in asterisks, for example *answer*.';
        }

        return null;
    }

    // -----------------------------------------------------------------------
    // Presentation
    // -----------------------------------------------------------------------

    /**
     * One row as every client reads it.
     *
     * `max_score` and `blank_count` are computed here rather than left to each
     * caller, because a list page, the player and the analytics pipeline must
     * agree on what an activity is worth -- and three independent counts of
     * "blanks that are not distractors" is three chances to disagree.
     *
     * @return array<string,mixed>
     */
    protected function present(H5pTextActivity $activity): array
    {
        $activity->loadMissing('blanks');

        $scorable = $activity->blanks->where('is_distractor', false);

        return $activity->toArray() + [
            'label' => $activity->label(),
            'machine_name' => $this->builder->machineName((string) $activity->content_type),
            'blank_count' => $scorable->count(),
            'distractor_count' => $activity->blanks->where('is_distractor', true)->count(),
            'max_score' => $activity->maxScore(),
        ];
    }

    // -----------------------------------------------------------------------
    // Session / tenancy
    // -----------------------------------------------------------------------

    /**
     * The tenant to read and write as.
     *
     * Session first: for `type=API` the session is hydrated from a verified
     * JWT (HydratesLegacyApiSession), so it is the trustworthy value and the
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

    /**
     * Scoped by tenant AND by content type -- so an id belonging to a Mark the
     * Words activity is a 404 on the Fill in the Blanks routes rather than
     * something the wrong editor opens.
     */
    protected function findForTenant(Request $request, $id): H5pTextActivity
    {
        return H5pTextActivity::with('blanks')
            ->ofType($this->contentType())
            ->where('sub_institute_id', $this->tenant($request))
            ->findOrFail($id);
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

    /** The authoring defaults for this type. @return array<string,mixed> */
    protected function defaults(): array
    {
        $type = $this->contentType();

        return [
            'enable_retry' => true,
            'enable_show_solution' => true,
            'enable_check' => true,
            'case_sensitive' => false,
            'accept_spelling_errors' => false,
            'instant_feedback' => false,
            'show_score_points' => true,
            'separate_lines' => false,
            'solution_requires_input' => true,
            'points_per_blank' => 1,
            'pass_percentage' => 100,
            'feedback_bands' => [
                ['from' => 0, 'to' => 49, 'feedback' => 'Keep practising — read the passage again.'],
                ['from' => 50, 'to' => 99, 'feedback' => 'Good work. Check the ones you missed.'],
                ['from' => 100, 'to' => 100, 'feedback' => 'Everything correct.'],
            ],
            // What the editor tells the author the markup means, per type.
            'markup_hint' => match ($type) {
                'fill_in_the_blanks' => 'Wrap each answer in asterisks. Separate alternatives with a slash and add a hint after a colon: *Norway/Noreg:It is a Nordic country*.',
                'drag_text' => 'Wrap each word learners drag into place in asterisks, and add a hint after a colon: *Norway:It is a Nordic country*.',
                default => 'Wrap each word learners should mark in asterisks: The *dog* chased the *cat*.',
            },
        ];
    }

    // -----------------------------------------------------------------------
    // Responses
    // -----------------------------------------------------------------------

    /** @param array<string,mixed> $res */
    protected function respond(Request $request, ?string $type, array $res)
    {
        if (in_array($type, ['API', 'JSON'], true)) {
            return response()->json($res);
        }

        return redirect()
            ->route($this->routePrefix() . '.index', $this->contextParams($request))
            ->with('data', $res);
    }

    protected function fail(?string $type, string $message, int $status)
    {
        if (in_array($type, ['API', 'JSON'], true)) {
            return response()->json(['status' => false, 'message' => $message], $status);
        }

        return back()->with('data', ['status' => false, 'message' => $message]);
    }

    /** @param array<string,mixed>|null $values */
    protected function audit(string $action, H5pTextActivity $activity, ?array $values = null): void
    {
        AuditLog::record([
            'module' => 'lms',
            // Namespaced by content type, so an audit trail reads as "drag
            // text published", not "text activity published" for all three.
            'action' => 'h5p_' . $activity->content_type . '_' . $action,
            'entity_type' => 'h5p_text_activity',
            'entity_id' => $activity->id,
            'new_values' => $values ?? $activity->toArray(),
        ]);
    }
}
