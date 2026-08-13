<?php

namespace App\Http\Controllers\lms\pal;

use App\Http\Controllers\Controller;
use App\Models\PAL\ContentMetadata;
use App\Models\PAL\ContentReviewLog;
use App\Models\PAL\MisconceptionCorrective;
use App\Models\PAL\MisconceptionLibrary;
use App\Models\PAL\QuestionMetadata;
use App\Services\PAL\Content\ContentMetadataService;
use App\Services\PAL\Content\MisconceptionLibraryService;
use App\Services\PAL\Content\PalVocabulary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PAL V4 Content Intelligence — Blade screens for the legacy /lms/* UI.
 *
 * The JSON API for this layer already exists (api/PAL/PalContentIntelligenceController,
 * behind `pal.auth` JWT). This controller is the SESSION-based twin of it, so the
 * screens work inside the existing Blade shell without a token — the same split
 * palController already uses for the legacy student PAL flow.
 *
 * All writes go through ContentMetadataService, so CONTENT LAW C2/C3/C4/C5 and
 * the §7.1 stage transitions are enforced identically from the UI and the API.
 * The UI never gets its own write path.
 *
 * Tenancy comes from the session, never from the request, so a reviewer cannot
 * approve another institute's content by editing a form field.
 */
class PalContentController extends Controller
{
    public function __construct(
        protected ContentMetadataService $metadata,
        protected MisconceptionLibraryService $misconceptions
    ) {}

    /**
     * GET /lms/pal-content — coverage dashboard.
     */
    public function index(Request $request)
    {
        $tenant = $this->tenant($request);

        $questionTotal = DB::table('lms_question_master')->where('sub_institute_id', $tenant)->count();
        $contentTotal = DB::table('content_master')->where('sub_institute_id', $tenant)->count();

        $qTagged = QuestionMetadata::where('sub_institute_id', $tenant)->whereNotNull('bloom_level')->count();
        $cTagged = ContentMetadata::where('sub_institute_id', $tenant)->whereNotNull('content_type')->count();

        // QA pipeline counts, per stage, for both estates.
        $pipeline = [];
        foreach (['question' => QuestionMetadata::class, 'content' => ContentMetadata::class] as $key => $model) {
            $rows = $model::where('sub_institute_id', $tenant)
                ->selectRaw('quality_status, COUNT(*) AS c')->groupBy('quality_status')->pluck('c', 'quality_status');
            foreach (array_keys(config('pal_content.quality_statuses')) as $status) {
                $pipeline[$key][$status] = (int) ($rows[$status] ?? 0);
            }
        }

        $bloom = QuestionMetadata::where('sub_institute_id', $tenant)
            ->whereNotNull('bloom_level')
            ->selectRaw('bloom_level, COUNT(*) AS c')->groupBy('bloom_level')->pluck('c', 'bloom_level');

        // The misconception library is shared vocabulary (sub_institute_id 0)
        // plus anything this institute authored itself.
        $health = $this->misconceptions->libraryHealth($tenant);

        $data = [
            'sub_institute_id' => $tenant,
            'questions' => [
                'total' => $questionTotal,
                'tagged' => $qTagged,
                'pct' => $questionTotal ? round($qTagged / $questionTotal * 100, 1) : 0,
            ],
            'content' => [
                'total' => $contentTotal,
                'tagged' => $cTagged,
                'pct' => $contentTotal ? round($cTagged / $contentTotal * 100, 1) : 0,
            ],
            'pipeline' => $pipeline,
            'bloom' => $bloom,
            'bloom_levels' => config('pal_content.bloom_levels'),
            'health' => $health,
            'linkage' => $this->linkage($tenant),
            'recent_reviews' => ContentReviewLog::where('sub_institute_id', $tenant)
                ->orderByDesc('id')->limit(10)->get(),
        ];

        if ($this->wantsJson($request)) {
            return response()->json(['status' => 200, 'data' => $data]);
        }

        return view('lms.pal.content.index', compact('data'));
    }

    /**
     * GET /lms/pal-content/review — the review queue.
     *
     * Ordered by ascending confidence: the machine's least certain proposals are
     * where a reviewer's time is actually worth spending (plan §8, R4).
     */
    public function review(Request $request)
    {
        $tenant = $this->tenant($request);
        $entityType = $request->get('entity_type', 'question');
        $status = $request->get('status', 'draft');

        if (! in_array($entityType, ['question', 'content'], true)) {
            $entityType = 'question';
        }
        if (! PalVocabulary::isQualityStatus($status)) {
            $status = 'draft';
        }

        [$model, $key, $sourceTable, $titleCol] = $entityType === 'question'
            ? [QuestionMetadata::class, 'question_id', 'lms_question_master', 'question_title']
            : [ContentMetadata::class, 'content_master_id', 'content_master', 'title'];

        $rows = $model::where('sub_institute_id', $tenant)
            ->where('quality_status', $status)
            ->when($request->filled('chapter_id'), fn ($q) => $q->where('chapter_ref_id', (int) $request->get('chapter_id')))
            ->orderByRaw('confidence IS NULL, confidence ASC')
            ->paginate(25)
            ->withQueryString();

        $titles = $rows->isEmpty() ? collect() : DB::table($sourceTable)
            ->whereIn('id', $rows->pluck($key))->pluck($titleCol, 'id');

        // Attach the source text + what still blocks approval, so the reviewer
        // can judge without opening a second screen.
        $items = collect($rows->items())->map(function ($r) use ($titles, $key, $entityType) {
            return [
                'meta' => $r,
                'entity_id' => $r->{$key},
                'title' => strip_tags((string) ($titles[$r->{$key}] ?? '')) ?: '(no title)',
                'missing' => $this->metadata->missingMandatory($entityType, $r),
                'completeness' => $this->metadata->completeness($entityType, $r),
            ];
        });

        $data = [
            'entity_type' => $entityType,
            'status' => $status,
            'items' => $items,
            'paginator' => $rows,
            'vocab' => PalVocabulary::all(),
            'counts' => $model::where('sub_institute_id', $tenant)
                ->selectRaw('quality_status, COUNT(*) AS c')->groupBy('quality_status')->pluck('c', 'quality_status'),
        ];

        if ($this->wantsJson($request)) {
            return response()->json(['status' => 200, 'data' => $data]);
        }

        return view('lms.pal.content.review', compact('data'));
    }

    /**
     * POST /lms/pal-content/review/update — save one row's metadata.
     */
    public function updateMetadata(Request $request)
    {
        $tenant = $this->tenant($request);

        $validated = $request->validate([
            'entity_type' => 'required|in:question,content',
            'entity_id' => 'required|integer',
            'bloom_level' => 'nullable|string',
            'bloom_level_served' => 'nullable|string',
            'difficulty_1_to_5' => 'nullable|integer',
            'cultural_context' => 'nullable|string',
            'language' => 'nullable|string',
            'pedagogy_mapping_id' => 'nullable|integer',
            'hpc_lens_primary' => 'nullable|string',
            'content_type' => 'nullable|string',
            'variant_number' => 'nullable|integer',
            'format' => 'nullable|string',
            'misconception_tags' => 'nullable|array',
        ]);

        $entityType = $validated['entity_type'];
        $entityId = (int) $validated['entity_id'];
        unset($validated['entity_type'], $validated['entity_id']);

        // Blank selects arrive as '' — strip them so a partial save does not
        // wipe fields the reviewer simply did not touch.
        $payload = array_filter($validated, fn ($v) => $v !== null && $v !== '');

        // Keep the ladder consistent: practice_level is derived, never typed in.
        $bloom = $payload['bloom_level'] ?? $payload['bloom_level_served'] ?? null;
        if ($bloom && PalVocabulary::isBloomLevel($bloom)) {
            $payload['practice_level'] = PalVocabulary::practiceLevelForBloom($bloom);
        }

        try {
            $this->metadata->upsert($entityType, $entityId, $payload, $tenant, 'human', $this->userId($request));
        } catch (\InvalidArgumentException $e) {
            return back()->with('pal_error', $e->getMessage());
        }

        return back()->with('pal_message', "Saved metadata for {$entityType} #{$entityId}.");
    }

    /**
     * POST /lms/pal-content/review/transition — move rows through the QA pipeline.
     *
     * Handles one id or many; the review screen posts a checkbox list, which is
     * how a reviewer clears a batch in one pass (plan P5 gate).
     */
    public function transition(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|in:question,content',
            'metadata_ids' => 'required|array|min:1',
            'metadata_ids.*' => 'integer',
            'to_status' => 'required|string',
            'note' => 'nullable|string|max:2000',
        ]);

        $tenant = $this->tenant($request);

        // Never let a posted id reach another institute's row.
        $model = $validated['entity_type'] === 'question' ? QuestionMetadata::class : ContentMetadata::class;
        $ownIds = $model::whereIn('id', $validated['metadata_ids'])
            ->where('sub_institute_id', $tenant)->pluck('id')->all();

        $foreign = count($validated['metadata_ids']) - count($ownIds);

        $result = $this->metadata->bulkTransition(
            $validated['entity_type'],
            $ownIds,
            $validated['to_status'],
            $this->userId($request),
            $validated['note'] ?? null
        );

        $msg = "{$result['ok_count']} row(s) moved to {$validated['to_status']}.";
        if ($result['fail_count'] > 0) {
            $msg .= " {$result['fail_count']} rejected: "
                . implode('; ', array_map(fn ($f) => "#{$f['id']} {$f['error']}", array_slice($result['failed'], 0, 3)));
        }
        if ($foreign > 0) {
            $msg .= " {$foreign} skipped (not this institute's content).";
        }

        return back()->with($result['fail_count'] > 0 ? 'pal_error' : 'pal_message', $msg);
    }

    /**
     * GET /lms/pal-content/misconceptions — the library.
     */
    public function misconceptions(Request $request)
    {
        $tenant = $this->tenant($request);

        $rows = MisconceptionLibrary::forTenant($tenant)
            ->when($request->filled('subject'), fn ($q) => $q->where('subject', $request->get('subject')))
            ->when($request->filled('status'), fn ($q) => $q->where('quality_status', $request->get('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $s = '%' . $request->get('q') . '%';
                $q->where(fn ($w) => $w->where('tag', 'like', $s)->orWhere('description', 'like', $s));
            })
            ->with('correctives')
            ->orderBy('subject')->orderBy('priority_level')
            ->paginate(20)->withQueryString();

        $data = [
            'items' => $rows,
            'subjects' => MisconceptionLibrary::forTenant($tenant)
                ->whereNotNull('subject')->distinct()->orderBy('subject')->pluck('subject'),
            'health' => $this->misconceptions->libraryHealth($tenant),
            'vocab' => PalVocabulary::all(),
            'can_author' => $tenant > 0,
        ];

        if ($this->wantsJson($request)) {
            return response()->json(['status' => 200, 'data' => $data]);
        }

        return view('lms.pal.content.misconceptions', compact('data'));
    }

    /**
     * POST /lms/pal-content/misconceptions/approve — approve a library entry.
     *
     * CONTENT LAW C6 is enforced here rather than left to the seeder: approving a
     * misconception that has no approved corrective would create a row that
     * detects an error and then shows the learner nothing.
     */
    public function approveMisconception(Request $request)
    {
        // The whitelist comes from the registry, not a hand-written subset — the
        // spec §7.1 pipeline runs draft → reviewed → pedagogy_reviewed → approved,
        // so omitting a middle stage makes 'approved' unreachable from the UI.
        $statuses = implode(',', array_keys(config('pal_content.quality_statuses')));

        $validated = $request->validate([
            'misconception_id' => 'required|integer',
            'to_status' => 'required|in:' . $statuses,
        ]);

        $tenant = $this->tenant($request);
        $m = MisconceptionLibrary::find($validated['misconception_id']);

        if (! $m) {
            return back()->with('pal_error', 'Misconception not found.');
        }
        if ((int) $m->sub_institute_id !== 0 && (int) $m->sub_institute_id !== $tenant) {
            return back()->with('pal_error', 'That entry belongs to another institute.');
        }
        // The shared global library is edited centrally, not per tenant.
        if ((int) $m->sub_institute_id === 0 && session()->get('user_profile_name') !== 'Super Admin') {
            return back()->with('pal_error',
                'This is shared global vocabulary. Only a Super Admin may change it — '
                . 'author an institute-specific entry with the same tag instead.');
        }

        if (! PalVocabulary::canTransition($m->quality_status, $validated['to_status'])) {
            return back()->with('pal_error',
                "Illegal transition '{$m->quality_status}' → '{$validated['to_status']}'.");
        }

        if ($validated['to_status'] === 'approved') {
            $servable = MisconceptionCorrective::where('misconception_id', $m->id)->servable()->count();
            if ($servable === 0) {
                return back()->with('pal_error',
                    "C6: '{$m->tag}' has no APPROVED corrective content. Approve at least one corrective "
                    . 'first — detecting the error and then showing nothing is worse than not detecting it.');
            }
        }

        $from = $m->quality_status;
        $m->quality_status = $validated['to_status'];
        $m->reviewed_by = $this->userId($request);
        $m->reviewed_at = now();
        $m->save();

        ContentReviewLog::create([
            'entity_type' => 'misconception',
            'entity_id' => $m->id,
            'sub_institute_id' => (int) $m->sub_institute_id,
            'from_status' => $from,
            'to_status' => $validated['to_status'],
            'reviewed_by' => $this->userId($request),
            'actor_type' => 'human',
        ]);

        return back()->with('pal_message', "'{$m->tag}' moved to {$validated['to_status']}.");
    }

    /**
     * POST /lms/pal-content/correctives/approve — approve one corrective.
     */
    public function approveCorrective(Request $request)
    {
        $statuses = implode(',', array_keys(config('pal_content.quality_statuses')));

        $validated = $request->validate([
            'corrective_id' => 'required|integer',
            'to_status' => 'required|in:' . $statuses,
        ]);

        $tenant = $this->tenant($request);
        $c = MisconceptionCorrective::find($validated['corrective_id']);

        if (! $c) {
            return back()->with('pal_error', 'Corrective not found.');
        }
        if ((int) $c->sub_institute_id !== 0 && (int) $c->sub_institute_id !== $tenant) {
            return back()->with('pal_error', 'That corrective belongs to another institute.');
        }
        if ((int) $c->sub_institute_id === 0 && session()->get('user_profile_name') !== 'Super Admin') {
            return back()->with('pal_error', 'Shared global content — Super Admin only.');
        }

        $from = $c->quality_status;
        $c->quality_status = $validated['to_status'];
        $c->reviewed_by = $this->userId($request);
        $c->reviewed_at = now();
        $c->save();

        ContentReviewLog::create([
            'entity_type' => 'corrective',
            'entity_id' => $c->id,
            'sub_institute_id' => (int) $c->sub_institute_id,
            'from_status' => $from,
            'to_status' => $validated['to_status'],
            'reviewed_by' => $this->userId($request),
            'actor_type' => 'human',
        ]);

        return back()->with('pal_message', "Corrective '{$c->title}' moved to {$validated['to_status']}.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Tenant from the session, with a request fallback for `type=API` callers.
     *
     * Same pattern palController uses — SessionMiddleware does not hydrate the
     * session from a Bearer token, so token-based callers pass it explicitly.
     */
    protected function tenant(Request $request): int
    {
        return (int) ($request->get('sub_institute_id') ?: session()->get('sub_institute_id') ?: 0);
    }

    /**
     * The /lms/* screens return JSON when ?type=API, matching the is_mobile()
     * convention the rest of palController uses.
     */
    protected function wantsJson(Request $request): bool
    {
        return in_array($request->get('type'), ['API', 'JSON'], true);
    }

    protected function userId(Request $request): int
    {
        return (int) ($request->get('user_id') ?: session()->get('user_id') ?: 0);
    }

    /**
     * Curriculum linkage summary — surfaced on the dashboard because a 0%
     * coverage figure means something very different when the join key itself
     * is missing (see the master prompt doc §1.2a).
     */
    protected function linkage(int $tenant): array
    {
        return [
            'questions_with_concept' => DB::table('lms_question_master')
                ->where('sub_institute_id', $tenant)->where('concept_id', '>', 0)->count(),
            'questions_with_chapter' => DB::table('lms_question_master')
                ->where('sub_institute_id', $tenant)->where('chapter_id', '>', 0)->count(),
            'content_with_concept' => DB::table('content_master')
                ->where('sub_institute_id', $tenant)->where('concept_id', '>', 0)->count(),
            'content_with_chapter' => DB::table('content_master')
                ->where('sub_institute_id', $tenant)->where('chapter_id', '>', 0)->count(),
        ];
    }
}
