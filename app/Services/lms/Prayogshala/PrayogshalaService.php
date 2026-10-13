<?php

namespace App\Services\lms\Prayogshala;

use GenTux\Jwt\GetsJwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The one place Prayogshala's tenancy rules and shapes live.
 *
 * Two callers share it so they cannot drift apart:
 *   - PrayogshalaApiController (list / show / create / update / delete), and
 *   - ApiLmsCourseController::getChapterContentCategories, which merges the same rows
 *     into the chapter content list as a `Prayogshala` category. That merge is what puts
 *     Prayogshala under "All content" and gives the tab its count, with no second
 *     content system and nothing for the frontend to aggregate.
 *
 * TENANCY. An institute reads its own rows, and an institute flagged is_Lms = 'Y'
 * additionally reads the platform tenant's (config lms_content.platform_sub_institute_ids,
 * which is how content_master is shared). The institute is NEVER taken from a request
 * body: the controller takes it from the api.session-hydrated session, and the content
 * list - whose route carries no session middleware - takes it from the verified JWT via
 * identityFromToken(). No verified token means no Prayogshala rows, not "all of them".
 */
class PrayogshalaService
{
    use GetsJwtToken;

    public const CATEGORY = 'Prayogshala';

    /**
     * What an activity may be called. Returned with every list so the client renders its
     * choices from the server. Subject-neutral: a label, not a gate on which subjects
     * may use Prayogshala.
     */
    public const ACTIVITY_TYPES = [
        'experiment'    => 'Experiment',
        'activity'      => 'Activity',
        'demonstration' => 'Demonstration',
        'investigation' => 'Investigation',
        'exploration'   => 'Exploration',
        'map_activity'  => 'Map activity',
        'project'       => 'Project',
    ];

    /**
     * Who the verified bearer token says is calling, or null when there is no valid one.
     *
     * @return array{tenant:int,is_student:bool}|null
     */
    public function identityFromToken(Request $request): ?array
    {
        try {
            $jwt = $this->jwtToken($request);
            if (! $jwt->validate()) {
                return null;
            }
            $payload = $jwt->payload();
            $tenant = (int) ($payload['sub_institute_id'] ?? 0);
        } catch (\Throwable $e) {
            return null;
        }

        return $tenant > 0 ? ['tenant' => $tenant, 'is_student' => ! empty($payload['is_student'])] : null;
    }

    public function available(): bool
    {
        return Schema::hasTable('lms_prayogshala_activity');
    }

    /** @return list<int> institutes whose rows this tenant may read. */
    public function visibleTenants(int $tenant): array
    {
        $platform = array_map('intval', (array) config('lms_content.platform_sub_institute_ids', [1]));
        $isLms = DB::table('school_setup')->where('Id', $tenant)->value('is_Lms');

        return $isLms === 'Y'
            ? array_values(array_unique(array_merge([$tenant], $platform)))
            : [$tenant];
    }

    /** A chapter this institute can see, or null. */
    public function visibleChapter(int $chapterId, int $tenant): ?object
    {
        return DB::table('chapter_master')
            ->select('id', 'sub_institute_id', 'grade_id', 'standard_id', 'subject_id', 'chapter_name')
            ->where('id', $chapterId)
            ->whereIn('sub_institute_id', $this->visibleTenants($tenant))
            ->first();
    }

    /** @return array<string,mixed> the chapter's standard / subject / chapter, by name and id. */
    public function chapterContext(object $chapter): array
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

    /**
     * Activities for one chapter, in order.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    public function rowsForChapter(object $chapter, int $tenant, bool $publishedOnly, ?int $topicId = null)
    {
        $query = DB::table('lms_prayogshala_activity')
            ->whereNull('deleted_at')
            ->where('chapter_id', $chapter->id)
            ->where('standard_id', $chapter->standard_id)
            ->where('subject_id', $chapter->subject_id)
            ->whereIn('sub_institute_id', $this->visibleTenants($tenant));

        if ($topicId !== null) {
            $query->where('topic_id', $topicId);
        }
        if ($publishedOnly) {
            // Published AND real: a placeholder that is still generating, or failed, has no lab.
            $query->where('show_hide', 1)->where('status', 'published')
                ->where(fn ($q) => $q->whereNull('generation_status')->orWhere('generation_status', 'ready'));
        }

        // One activity per topic: when an institute has its own and can also read the platform's,
        // its own wins. Chapter-wide rows (no topic) are never merged.
        return $query->orderBy('sort_order')->orderBy('id')->get()
            ->sortByDesc(fn ($r) => (int) ((int) $r->sub_institute_id === $tenant))
            ->unique(fn ($r) => $r->topic_id !== null ? 't' . $r->topic_id : 'r' . $r->id)
            ->sortBy([['sort_order', 'asc'], ['id', 'asc']])
            ->values();
    }

    /**
     * Topic and concept names for a set of rows, in two queries however many rows.
     *
     * @param  iterable<object>  $rows
     * @return array{topics: array<int,string>, concepts: array<int,string>}
     */
    private function names(iterable $rows): array
    {
        $topicIds = [];
        $conceptIds = [];
        foreach ($rows as $row) {
            if ($row->topic_id) {
                $topicIds[] = (int) $row->topic_id;
            }
            if ($row->concept_id) {
                $conceptIds[] = (int) $row->concept_id;
            }
            foreach ((array) json_decode((string) ($row->concept_ids ?? ''), true) as $cid) {
                $conceptIds[] = (int) $cid;
            }
        }

        return [
            'topics'   => $topicIds === [] ? [] : DB::table('topic_master')->whereIn('id', array_unique($topicIds))->pluck('name', 'id')->all(),
            'concepts' => $conceptIds === [] ? [] : DB::table('lms_concept')->whereIn('id', array_unique($conceptIds))->pluck('name', 'id')->all(),
        ];
    }

    /**
     * @param  iterable<object>  $rows
     * @return list<array<string,mixed>>
     */
    public function presentMany(iterable $rows, int $tenant, bool $forLearner = false): array
    {
        $names = $this->names($rows);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->present($row, $tenant, $names, $forLearner);
        }

        return $out;
    }

    /**
     * @param  array{topics: array<int,string>, concepts: array<int,string>}|null  $names
     * @return array<string,mixed>
     */
    public function present(object $row, int $tenant, ?array $names = null, bool $forLearner = false): array
    {
        $names ??= $this->names([$row]);
        $decode = fn ($v) => $v ? (json_decode($v, true) ?: []) : [];
        $lab = $row->lab_config ? json_decode($row->lab_config, true) : null;
        $concepts = array_values(array_map('intval', (array) json_decode((string) ($row->concept_ids ?? ''), true)));
        // Teacher-only material never leaves the server for a learner.
        if ($forLearner && is_array($lab)) {
            unset($lab['teacher_script']);
        }

        return [
            'id'                   => (int) $row->id,
            'chapter_id'           => (int) $row->chapter_id,
            'standard_id'          => (int) $row->standard_id,
            'subject_id'           => (int) $row->subject_id,
            'topic_id'             => $row->topic_id !== null ? (int) $row->topic_id : null,
            'topic_name'           => $row->topic_id ? ($names['topics'][(int) $row->topic_id] ?? null) : null,
            'concept_id'           => $row->concept_id !== null ? (int) $row->concept_id : null,
            'concept_name'         => $row->concept_id ? ($names['concepts'][(int) $row->concept_id] ?? null) : null,
            'title'                => $row->title,
            'activity_type'        => $row->activity_type,
            'activity_type_label'  => self::ACTIVITY_TYPES[$row->activity_type] ?? ucfirst(str_replace('_', ' ', (string) $row->activity_type)),
            'description'          => $row->description,
            'objective'            => $row->objective,
            'materials_required'   => $decode($row->materials_required),
            'procedure_steps'      => $decode($row->procedure_steps),
            'observation'          => $row->observation,
            'result'               => $row->result,
            'safety_instructions'  => $row->safety_instructions,
            'teacher_instructions' => $forLearner ? null : $row->teacher_instructions,
            'student_instructions' => $row->student_instructions,
            'estimated_minutes'    => $row->estimated_minutes !== null ? (int) $row->estimated_minutes : null,
            'resources'            => $decode($row->resources),
            'slug'                 => $row->slug,
            'status'               => $row->status,
            'generation_status'    => $row->generation_status,
            'generation_version'   => (int) $row->generation_version,
            'generated_at'         => $row->generated_at,
            // Provider diagnostics and source provenance are staff material.
            'generation_error'     => $forLearner ? null : $row->generation_error,
            'source_refs'          => $forLearner ? null : ($row->source_refs ? json_decode($row->source_refs, true) : null),
            'concept_ids'          => $concepts,
            'concept_names'        => array_values(array_filter(array_map(fn ($id) => $names['concepts'][$id] ?? null, $concepts))),
            'lab_config'           => is_array($lab) ? $lab : null,
            'show_hide'            => (int) $row->show_hide,
            'sort_order'           => (int) $row->sort_order,
            // True for the institute's own rows. The platform tenant's shared rows are
            // visible to is_Lms institutes but cannot be edited by them.
            'editable'             => (int) $row->sub_institute_id === $tenant,
            'created_at'           => $row->created_at,
            'updated_at'           => $row->updated_at,
        ];
    }

    /**
     * The chapter's topics, each with the one activity filed against it (or none).
     *
     * This is the topic-wise view the UI is built on, and it is also the honest coverage report:
     * a topic with no row is "not generated"; a row says whether it is generating, ready, failed
     * or needs more source content. A learner only gets topics that have a published activity
     * attached, and none of the generation fields.
     *
     * @return list<array<string,mixed>>
     */
    public function topicCoverage(object $chapter, int $tenant, bool $forLearner): array
    {
        $topics = DB::table('topic_master')->where('chapter_id', $chapter->id)
            ->orderBy('topic_sort_order')->orderBy('id')->get(['id', 'name', 'description']);
        $rows = $this->rowsForChapter($chapter, $tenant, $forLearner);
        $byTopic = $rows->filter(fn ($r) => $r->topic_id !== null)->keyBy('topic_id');
        $presented = collect($this->presentMany($byTopic->values(), $tenant, $forLearner))->keyBy('topic_id');
        $conceptCounts = DB::table('lms_concept')->where('chapter_id', $chapter->id)->whereIn('topic_id', $topics->pluck('id'))
            ->selectRaw('topic_id, count(*) as n')->groupBy('topic_id')->pluck('n', 'topic_id');

        $out = [];
        foreach ($topics as $topic) {
            $activity = $presented->get($topic->id);
            if ($forLearner && ! $activity) {
                continue;
            }
            $out[] = [
                'topic_id'      => (int) $topic->id,
                'topic_name'    => $topic->name,
                'concept_count' => (int) ($conceptCounts[$topic->id] ?? 0),
                'has_description' => mb_strlen(trim((string) $topic->description)) >= 40,
                'state'         => $this->topicState($activity),
                'activity'      => $activity,
            ];
        }

        return $out;
    }

    /** not_generated | generating | ready | published | failed | needs_content */
    private function topicState(?array $activity): string
    {
        if (! $activity) {
            return 'not_generated';
        }
        $gen = $activity['generation_status'];
        if (in_array($gen, ['generating', 'failed', 'needs_content'], true)) {
            return $gen;
        }

        return $activity['status'] === 'published' ? 'published' : 'ready';
    }

    /**
     * The chapter's activities as content-list assets, for the "All content" merge.
     *
     * Keys mirror content_master so the existing list, search, tab counts and card
     * render them like any other item. The full activity rides along under
     * `prayogshala` so opening one needs no second request. Ids are namespaced, like
     * the H5P adapter's, so they can never collide with a content_master id.
     *
     * Learners get published activities only; the content list has no per-row role, so
     * the caller says whether the viewer is staff.
     *
     * @return list<array<string,mixed>>
     */
    public function assetsForChapter(object $chapter, ?int $tenant, bool $publishedOnly = true): array
    {
        if ($tenant === null || ! $this->available()) {
            return [];
        }

        // A topic still generating, failed, or short of source has no activity to open yet: it
        // lives in the topic list, not in the content list as a card.
        $rows = $this->rowsForChapter($chapter, $tenant, $publishedOnly)
            ->reject(fn ($r) => in_array($r->generation_status, ['generating', 'failed', 'needs_content'], true) && $r->lab_config === null)
            ->values();
        $context = $rows->isEmpty() ? null : $this->chapterContext($chapter);
        $assets = [];
        foreach ($this->presentMany($rows, $tenant, $publishedOnly) as $activity) {
            $activity['context'] = $context;
            $assets[] = [
                'id'               => 'prayogshala:' . $activity['id'],
                'prayogshala_id'   => $activity['id'],
                'title'            => $activity['title'],
                'description'      => $activity['description'] ?? $activity['objective'],
                'content_category' => self::CATEGORY,
                'format'           => 'prayogshala',
                'file_type'        => 'activity',
                'source'           => 'Authored',
                'url'              => null,
                'chapter_id'       => $activity['chapter_id'],
                'subject_id'       => $activity['subject_id'],
                'standard_id'      => $activity['standard_id'],
                'sub_institute_id' => $rows->firstWhere('id', $activity['id'])->sub_institute_id ?? null,
                'topic_id'         => $activity['topic_id'],
                'topic_name'       => $activity['topic_name'],
                'concept_id'       => $activity['concept_id'],
                'concept_name'     => $activity['concept_name'],
                'created_at'       => $activity['updated_at'] ?? $activity['created_at'],
                'audience'         => 'both',
                'prayogshala'      => $activity,
            ];
        }

        return $assets;
    }
}
