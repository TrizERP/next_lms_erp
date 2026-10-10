<?php

namespace App\Http\Controllers\api\lms;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePrayogshalaActivityJob;
use App\Services\lms\Prayogshala\PrayogshalaGenerator;
use App\Services\lms\Prayogshala\PrayogshalaService;
use App\Services\lms\Prayogshala\SimulationConfigValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Prayogshala - chapter-scoped practical / experiment / activity resources.
 *
 *   POST|GET lms/prayogshala                 list a chapter's activities (+ chapter context)
 *   GET      lms/prayogshala/{id}            one activity
 *   POST     lms/prayogshala/store           create            (lms.content create)
 *   POST     lms/prayogshala/{id}/update     update            (lms.content create)
 *   POST     lms/prayogshala/{id}/delete     soft delete       (lms.content create)
 *   POST|GET lms/prayogshala/coverage        per-chapter topic / activity counts for a standard+subject
 *   POST     lms/prayogshala/generate        generate (or explicitly regenerate) a topic's activity
 *                                            (lms.content create)
 *
 * TENANCY. Every route sits behind `api.session`, which verifies the bearer JWT and
 * hydrates the session from it. The institute and the user are read from that session -
 * never from the request body - so a caller cannot read or write another institute's
 * activities by naming it. (The older content endpoints read sub_institute_id from the
 * body; this one deliberately does not copy that.)
 *
 * VISIBILITY follows content_master exactly: an institute sees its own rows, and an
 * institute flagged is_Lms = 'Y' additionally sees the platform tenant's (id 1) shared
 * rows. Writes never cross that line: a row can only be changed by the institute that
 * owns it, and the platform's shared rows are therefore read-only to everyone else.
 *
 * HIERARCHY. standard_id / subject_id / grade_id are derived from chapter_master on every
 * write. A client may send them, but only so a mismatch can be refused; they are never
 * trusted. The chapter itself must be one the institute can see.
 */
class PrayogshalaApiController extends Controller
{
    public function __construct(
        private PrayogshalaService $service,
        private SimulationConfigValidator $labValidator,
    ) {
    }

    /** Roles that may author, mirroring ApiLmsCourseController::persistContent. */
    private const AUTHOR_ROLES = ['TEACHER', 'LMS TEACHER', 'ADMIN', 'SUPER ADMIN'];

    private const RESOURCE_TYPES = ['image', 'video', 'pdf', 'link'];

    private const LIST_FIELDS = ['materials_required', 'procedure_steps'];

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chapter_id' => 'required|integer|min:1',
            'topic_id'   => 'nullable|integer|min:1',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->messages());
        }

        $tenant = $this->tenant($request);
        $chapter = $this->service->visibleChapter((int) $request->input('chapter_id'), $tenant);
        if (! $chapter) {
            return $this->notFound('Chapter not found.');
        }
        $topicId = $request->filled('topic_id') ? (int) $request->input('topic_id') : null;
        if ($topicId !== null && ! DB::table('topic_master')->where('id', $topicId)->where('chapter_id', $chapter->id)->exists()) {
            return $this->validationError(['topic_id' => ['The topic does not belong to this chapter.']]);
        }

        // Learners only see what a teacher has published.
        $rows = $this->service->rowsForChapter($chapter, $tenant, $this->isStudent($request), $topicId);

        return response()->json([
            'status_code' => 1,
            'message'     => 'SUCCESS',
            'data'        => [
                'chapter'        => $this->chapterContext($chapter),
                // Placeholders (generating / failed / needs_content, no lab yet) are not activities:
                // they appear only as a topic's state in topic_coverage.
                'activities'     => $this->service->presentMany(
                    $rows->reject(fn ($r) => $r->lab_config === null && in_array($r->generation_status, ['generating', 'failed', 'needs_content'], true))->values(),
                    $tenant,
                    $this->isStudent($request)
                ),
                'activity_types' => $this->activityTypes(),
                'topics'         => $this->topicsFor($chapter),
                'topic_coverage' => $this->service->topicCoverage($chapter, $tenant, $this->isStudent($request)),
                'can_manage'     => $this->canAuthor($request),
            ],
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $tenant = $this->tenant($request);
        $row = $this->findVisible((int) $id, $tenant, $this->isStudent($request));
        if (! $row) {
            return $this->notFound('Activity not found.');
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'SUCCESS',
            'data'        => $this->service->present($row, $tenant, null, $this->isStudent($request)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->canAuthor($request)) {
            return $this->forbidden();
        }

        $validator = Validator::make($request->all(), $this->rules(true));
        $this->checkLab($validator, $request);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->messages());
        }

        $tenant = $this->tenant($request);
        $chapter = $this->service->visibleChapter((int) $request->input('chapter_id'), $tenant);
        if (! $chapter) {
            return $this->notFound('Chapter not found.');
        }
        if ($mismatch = $this->hierarchyMismatch($request, $chapter)) {
            return $this->validationError(['chapter_id' => [$mismatch]]);
        }
        if ($error = $this->topicConceptError($request->input('topic_id'), $request->input('concept_id'), $chapter)) {
            return $this->validationError($error);
        }
        $topicId = $request->filled('topic_id') ? (int) $request->input('topic_id') : null;
        if ($topicId !== null && ($taken = $this->topicTaken($topicId, $tenant))) {
            return $this->validationError(['topic_id' => [$taken]]);
        }

        $userId = (int) $request->session()->get('user_id');
        $now = now();
        try {
            $id = DB::table('lms_prayogshala_activity')->insertGetId(array_merge($this->payload($request), [
            'topic_slot'       => $topicId,
            'sub_institute_id' => $tenant,
            'syear'            => $request->session()->get('syear') ?: null,
            'grade_id'         => $chapter->grade_id,
            'standard_id'      => $chapter->standard_id,
            'subject_id'       => $chapter->subject_id,
            'chapter_id'       => $chapter->id,
            'created_by'       => $userId,
            'updated_by'       => $userId,
            'created_at'       => $now,
            'updated_at'       => $now,
            ]));
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Lost a race to another request for the same topic or slug.
            return $this->validationError(['topic_id' => ['This topic already has a Prayogshala activity.']]);
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'Prayogshala activity added.',
            'data'        => $this->service->present(DB::table('lms_prayogshala_activity')->find($id), $tenant),
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        if (! $this->canAuthor($request)) {
            return $this->forbidden();
        }

        $tenant = $this->tenant($request);
        $row = $this->findOwned((int) $id, $tenant);
        if (! $row) {
            // Absent, deleted, or another institute's: indistinguishable on purpose.
            return $this->notFound('Activity not found.');
        }

        $validator = Validator::make($request->all(), $this->rules(false));
        $this->checkLab($validator, $request);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->messages());
        }

        // Content that was never generated (or whose generation failed) has nothing to publish.
        if ($request->input('status') === 'published' && $row->lab_config === null
            && in_array($row->generation_status, ['generating', 'failed', 'needs_content'], true)) {
            return $this->validationError(['status' => ['This activity has no generated content yet, so it cannot be published.']]);
        }

        // The chapter an activity belongs to is fixed once created. Moving it would mean
        // re-deriving standard and subject, and a re-file is a delete + add.
        if ($request->has('chapter_id') && (int) $request->input('chapter_id') !== (int) $row->chapter_id) {
            return $this->validationError(['chapter_id' => ['An activity cannot be moved to another chapter.']]);
        }

        $chapter = $this->service->visibleChapter((int) $row->chapter_id, $tenant);
        if ($chapter) {
            if ($mismatch = $this->hierarchyMismatch($request, $chapter)) {
                return $this->validationError(['chapter_id' => [$mismatch]]);
            }
            if (($request->has('topic_id') || $request->has('concept_id'))
                && ($error = $this->topicConceptError(
                    $request->has('topic_id') ? $request->input('topic_id') : $row->topic_id,
                    $request->has('concept_id') ? $request->input('concept_id') : $row->concept_id,
                    $chapter
                ))) {
                return $this->validationError($error);
            }
        }

        $changes = $this->payload($request, $row);
        if ($request->has('topic_id')) {
            $newTopic = $request->filled('topic_id') ? (int) $request->input('topic_id') : null;
            if ($newTopic !== null && $newTopic !== (int) $row->topic_id && ($taken = $this->topicTaken($newTopic, $tenant))) {
                return $this->validationError(['topic_id' => [$taken]]);
            }
            $changes['topic_slot'] = $newTopic;
        }
        try {
            DB::table('lms_prayogshala_activity')->where('id', $row->id)->update(array_merge(
                $changes,
                ['updated_by' => (int) $request->session()->get('user_id'), 'updated_at' => now()]
            ));
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return $this->validationError(['topic_id' => ['This topic already has a Prayogshala activity.']]);
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'Prayogshala activity updated.',
            'data'        => $this->service->present(DB::table('lms_prayogshala_activity')->find($row->id), $tenant),
        ]);
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        if (! $this->canAuthor($request)) {
            return $this->forbidden();
        }

        $tenant = $this->tenant($request);
        $row = $this->findOwned((int) $id, $tenant);
        if (! $row) {
            return $this->notFound('Activity not found.');
        }

        DB::table('lms_prayogshala_activity')->where('id', $row->id)->update([
            'deleted_at' => now(),
            // Frees the topic for a replacement; see the topic_slot unique index.
            'topic_slot' => null,
            'updated_by' => (int) $request->session()->get('user_id'),
        ]);

        return response()->json(['status_code' => 1, 'message' => 'Prayogshala activity removed.']);
    }

    /**
     * Topic-wise coverage of a standard (+ optional subject), one entry per chapter: how many
     * topics it has and how many are in each state. Paginated by chapter. This is the "what is
     * actually done" report, computed from the table and never assumed.
     */
    public function coverage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'standard_id' => 'required|integer|min:1',
            'subject_id'  => 'nullable|integer|min:1',
            'page'        => 'nullable|integer|min:1',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->messages());
        }

        $tenant = $this->tenant($request);
        $tenants = $this->service->visibleTenants($tenant);
        $learner = $this->isStudent($request);

        $chapters = DB::table('chapter_master')
            ->whereIn('sub_institute_id', $tenants)
            ->where('standard_id', (int) $request->input('standard_id'))
            ->when($request->filled('subject_id'), fn ($q) => $q->where('subject_id', (int) $request->input('subject_id')))
            ->orderBy('subject_id')->orderBy('sort_order')->orderBy('id')
            ->paginate((int) $request->input('per_page', 50), ['id', 'subject_id', 'chapter_name'], 'page', (int) $request->input('page', 1));

        $ids = $chapters->pluck('id');
        $topicCounts = DB::table('topic_master')->whereIn('chapter_id', $ids)->selectRaw('chapter_id, count(*) as n')->groupBy('chapter_id')->pluck('n', 'chapter_id');
        $rows = DB::table('lms_prayogshala_activity')->whereNull('deleted_at')->whereIn('chapter_id', $ids)
            ->whereIn('sub_institute_id', $tenants)->whereNotNull('topic_id')
            ->get(['chapter_id', 'topic_id', 'sub_institute_id', 'status', 'show_hide', 'generation_status'])
            ->sortByDesc(fn ($r) => (int) ((int) $r->sub_institute_id === $tenant))->unique('topic_id');

        $items = [];
        foreach ($chapters as $chapter) {
            $states = ['published' => 0, 'ready' => 0, 'generating' => 0, 'failed' => 0, 'needs_content' => 0];
            foreach ($rows->where('chapter_id', $chapter->id) as $r) {
                $published = $r->status === 'published' && (int) $r->show_hide === 1 && in_array($r->generation_status, [null, 'ready'], true);
                if ($learner && ! $published) {
                    continue;
                }
                $key = in_array($r->generation_status, ['generating', 'failed', 'needs_content'], true) ? $r->generation_status : ($published ? 'published' : 'ready');
                $states[$key]++;
            }
            $total = (int) ($topicCounts[$chapter->id] ?? 0);
            $items[] = [
                'chapter_id'    => (int) $chapter->id,
                'subject_id'    => (int) $chapter->subject_id,
                'chapter_name'  => $chapter->chapter_name,
                'topics'        => $total,
                'states'        => $states,
                'not_generated' => max(0, $total - array_sum($states)),
            ];
        }

        return response()->json([
            'status_code' => 1,
            'message'     => 'SUCCESS',
            'data'        => [
                'chapters'   => $items,
                'pagination' => ['page' => $chapters->currentPage(), 'per_page' => $chapters->perPage(), 'total' => $chapters->total(), 'last_page' => $chapters->lastPage()],
            ],
        ]);
    }

    /**
     * Generate the topic's one activity, or - only when `regenerate` is sent - replace it.
     * Safe to call twice: a ready topic answers `exists` without an AI call.
     */
    public function generate(Request $request, PrayogshalaGenerator $generator): JsonResponse
    {
        if (! $this->canAuthor($request)) {
            return $this->forbidden();
        }
        $validator = Validator::make($request->all(), [
            'topic_id'   => 'required|integer|min:1',
            'regenerate' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->messages());
        }

        $tenant = $this->tenant($request);
        $userId = (int) $request->session()->get('user_id');
        $topicId = (int) $request->input('topic_id');
        $regenerate = $request->boolean('regenerate');

        // Real queue: answer at once and let the UI poll the topic list. `sync` (the local default)
        // has no worker, so the job would run inline anyway - do it here and return the result.
        if (config('queue.default') !== 'sync') {
            if (! $generator->context($topicId, $tenant)) {
                return $this->notFound('Topic not found.');
            }
            GeneratePrayogshalaActivityJob::dispatch($topicId, $tenant, $userId, $regenerate);

            return response()->json(['status_code' => 1, 'message' => 'Generation queued.', 'data' => ['outcome' => 'queued']], 202);
        }

        $result = $generator->generate($topicId, $tenant, $userId, $regenerate);
        if ($result['outcome'] === 'not_found') {
            return $this->notFound($result['message']);
        }
        if ($result['outcome'] === 'forbidden') {
            return $this->forbidden();
        }
        $row = $result['activity_id'] ? DB::table('lms_prayogshala_activity')->find($result['activity_id']) : null;

        return response()->json([
            'status_code' => $result['outcome'] === 'failed' ? 0 : 1,
            'message'     => $result['message'],
            'data'        => [
                'outcome'  => $result['outcome'],
                'activity' => $row ? $this->service->present($row, $tenant) : null,
            ],
        ], $result['outcome'] === 'failed' ? 502 : 200);
    }

    // ---------------------------------------------------------------- validation

    /** @return array<string,mixed> */
    private function rules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes|required';

        return [
            'chapter_id'          => $creating ? 'required|integer|min:1' : 'sometimes|integer|min:1',
            // Accepted only so a disagreement with the chapter can be refused.
            'standard_id'         => 'nullable|integer',
            'subject_id'          => 'nullable|integer',
            'topic_id'            => 'nullable|integer|min:1',
            'concept_id'          => 'nullable|integer|min:1',
            'title'               => $required . '|string|max:250',
            'activity_type'       => ['sometimes', 'string', Rule::in(array_keys(PrayogshalaService::ACTIVITY_TYPES))],
            'description'         => 'nullable|string|max:20000',
            'objective'           => 'nullable|string|max:20000',
            'materials_required'  => 'nullable|array|max:100',
            'materials_required.*' => 'string|max:500',
            'procedure_steps'     => 'nullable|array|max:100',
            'procedure_steps.*'   => 'string|max:2000',
            'observation'         => 'nullable|string|max:20000',
            'result'              => 'nullable|string|max:20000',
            'safety_instructions' => 'nullable|string|max:20000',
            'teacher_instructions' => 'nullable|string|max:20000',
            'student_instructions' => 'nullable|string|max:20000',
            'estimated_minutes'   => 'nullable|integer|min:1|max:1440',
            'resources'           => 'nullable|array|max:50',
            'resources.*.type'    => ['required', Rule::in(self::RESOURCE_TYPES)],
            'resources.*.title'   => 'nullable|string|max:250',
            // http(s) only: a javascript: or data: URL would be rendered as a link.
            'resources.*.url'     => ['required', 'string', 'max:2048', 'regex:#^https?://#i'],
            'show_hide'           => 'nullable|boolean',
            'status'              => ['nullable', Rule::in(['draft', 'review', 'published'])],
            // The lab is arbitrary structured data the frontend's engines interpret. Bounded
            // here; its shape is the frontend's contract and unknown engines render a notice.
            // Shape and expressions are checked by SimulationConfigValidator (see checkLab).
            'lab_config'          => 'nullable|array',
            'sort_order'          => 'nullable|integer|min:0|max:100000',
            'slug'                => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]+$/'],
        ];
    }

    /**
     * The writable columns present in the request. On update only keys the caller sent are
     * included, so a partial update cannot blank a field it never mentioned.
     *
     * @return array<string,mixed>
     */
    private function payload(Request $request, ?object $existing = null): array
    {
        $out = [];
        $plain = [
            'title', 'activity_type', 'description', 'objective', 'observation', 'result',
            'safety_instructions', 'teacher_instructions', 'student_instructions',
            'estimated_minutes', 'topic_id', 'concept_id', 'sort_order',
        ];
        foreach ($plain as $key) {
            if ($request->has($key)) {
                $value = $request->input($key);
                $out[$key] = is_string($value) ? trim($value) : $value;
                if ($out[$key] === '') {
                    $out[$key] = null;
                }
            }
        }
        if (isset($out['title']) === false && $existing === null) {
            $out['title'] = trim((string) $request->input('title'));
        }
        if ($existing === null) {
            $out['activity_type'] = $out['activity_type'] ?? 'experiment';
            $out['sort_order'] = $out['sort_order'] ?? 0;
        }
        if ($request->filled('status')) {
            $out['status'] = $request->input('status');
        } elseif ($existing === null) {
            $out['status'] = 'published';
        }
        if ($request->has('lab_config')) {
            $lab = $request->input('lab_config');
            $json = $lab ? json_encode($lab, JSON_UNESCAPED_UNICODE) : null;
            if ($json !== null && strlen($json) > 200000) {
                throw \Illuminate\Validation\ValidationException::withMessages(['lab_config' => ['The lab configuration is too large.']]);
            }
            $out['lab_config'] = $json;
        }
        if ($request->has('slug')) {
            $out['slug'] = $request->input('slug') ?: null;
        }
        if ($request->has('show_hide')) {
            $out['show_hide'] = $request->boolean('show_hide') ? 1 : 0;
        } elseif ($existing === null) {
            $out['show_hide'] = 1;
        }

        foreach (self::LIST_FIELDS as $key) {
            if ($request->has($key)) {
                $items = array_values(array_filter(
                    array_map(fn ($v) => trim((string) $v), (array) $request->input($key)),
                    fn ($v) => $v !== ''
                ));
                $out[$key] = $items === [] ? null : json_encode($items, JSON_UNESCAPED_UNICODE);
            }
        }
        if ($request->has('resources')) {
            $items = array_values(array_map(fn ($r) => [
                'type'  => $r['type'],
                'title' => isset($r['title']) ? trim((string) $r['title']) : '',
                'url'   => trim((string) $r['url']),
            ], (array) $request->input('resources')));
            $out['resources'] = $items === [] ? null : json_encode($items, JSON_UNESCAPED_UNICODE);
        }

        return $out;
    }

    /** Adds SimulationConfigValidator's findings to the request validator. */
    private function checkLab(\Illuminate\Validation\Validator $validator, Request $request): void
    {
        if (! $request->filled('lab_config')) {
            return;
        }
        $validator->after(function ($v) use ($request) {
            foreach (array_slice($this->labValidator->validate($request->input('lab_config')), 0, 8) as $problem) {
                $v->errors()->add('lab_config', $problem);
            }
        });
    }

    /** Why this institute cannot file another activity under the topic, or null when it can. */
    private function topicTaken(int $topicId, int $tenant): ?string
    {
        $exists = DB::table('lms_prayogshala_activity')->where('topic_id', $topicId)->whereNull('deleted_at')
            ->whereIn('sub_institute_id', $this->service->visibleTenants($tenant))->exists();

        return $exists ? 'This topic already has a Prayogshala activity. Edit or regenerate it instead of adding another.' : null;
    }

    // ------------------------------------------------------------------- scoping

    private function tenant(Request $request): int
    {
        return (int) $request->session()->get('sub_institute_id');
    }

    private function isStudent(Request $request): bool
    {
        return (bool) $request->session()->get('is_student');
    }

    private function canAuthor(Request $request): bool
    {
        if ($this->isStudent($request)) {
            return false;
        }

        return in_array(
            strtoupper(trim((string) $request->session()->get('user_profile_name'))),
            self::AUTHOR_ROLES,
            true
        );
    }

    private function findVisible(int $id, int $tenant, bool $publishedOnly): ?object
    {
        $query = DB::table('lms_prayogshala_activity')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->whereIn('sub_institute_id', $this->service->visibleTenants($tenant));
        if ($publishedOnly) {
            $query->where('show_hide', 1)->where('status', 'published');
        }

        return $query->first();
    }

    /** A row this institute owns - the only kind it may change. */
    private function findOwned(int $id, int $tenant): ?object
    {
        return DB::table('lms_prayogshala_activity')
            ->where('id', $id)
            ->where('sub_institute_id', $tenant)
            ->whereNull('deleted_at')
            ->first();
    }

    private function hierarchyMismatch(Request $request, object $chapter): ?string
    {
        if ($request->filled('standard_id') && (int) $request->input('standard_id') !== (int) $chapter->standard_id) {
            return 'The chapter does not belong to the given standard.';
        }
        if ($request->filled('subject_id') && (int) $request->input('subject_id') !== (int) $chapter->subject_id) {
            return 'The chapter does not belong to the given subject.';
        }

        return null;
    }

    /**
     * A topic must belong to the chapter, a concept must belong to the chapter, and when
     * both are given the concept must sit under that topic. Same ids the rest of the LMS
     * uses (topic_master.chapter_id, lms_concept.chapter_id / topic_id) - no new hierarchy.
     *
     * @return array<string,list<string>>|null
     */
    private function topicConceptError($topicId, $conceptId, object $chapter): ?array
    {
        $topicId = ($topicId === null || $topicId === '') ? null : (int) $topicId;
        $conceptId = ($conceptId === null || $conceptId === '') ? null : (int) $conceptId;

        if ($topicId !== null
            && ! DB::table('topic_master')->where('id', $topicId)->where('chapter_id', $chapter->id)->exists()) {
            return ['topic_id' => ['The topic does not belong to this chapter.']];
        }
        if ($conceptId !== null) {
            $concept = DB::table('lms_concept')->where('id', $conceptId)->where('chapter_id', $chapter->id)->first();
            if (! $concept) {
                return ['concept_id' => ['The concept does not belong to this chapter.']];
            }
            if ($topicId !== null && (int) $concept->topic_id !== $topicId) {
                return ['concept_id' => ['The concept does not belong to the selected topic.']];
            }
        }

        return null;
    }

    // -------------------------------------------------------------- presentation

    /** @return array<string,mixed> */
    private function chapterContext(object $chapter): array
    {
        return [
            'chapter_id'    => (int) $chapter->id,
            'chapter_name'  => $chapter->chapter_name,
            'standard_id'   => (int) $chapter->standard_id,
            'standard_name' => DB::table('standard')->where('id', $chapter->standard_id)->value('name'),
            'subject_id'    => (int) $chapter->subject_id,
            'subject_name'  => DB::table('subject')->where('id', $chapter->subject_id)->value('subject_name'),
        ];
    }

    /** @return list<array{value:string,label:string}> */
    private function activityTypes(): array
    {
        $out = [];
        foreach (PrayogshalaService::ACTIVITY_TYPES as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }

    /**
     * The chapter's topics, so the editor can offer them without a second request and
     * without the client knowing how topics are stored.
     *
     * @return list<array{id:int,name:string}>
     */
    private function topicsFor(object $chapter): array
    {
        return DB::table('topic_master')
            ->where('chapter_id', $chapter->id)
            ->orderBy('topic_sort_order')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn ($t) => ['id' => (int) $t->id, 'name' => $t->name])
            ->all();
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['status_code' => 0, 'message' => 'Validation failed.', 'errors' => $errors], 422);
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json(['status_code' => 0, 'message' => $message], 404);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['status_code' => 0, 'message' => 'Unauthorized. Admin or Teacher access required.'], 403);
    }
}
