<?php

namespace App\Services\PAL\ContentModel;

use App\Models\PAL\ConceptVideo;
use App\Services\PAL\Content\PalVocabulary;
use Illuminate\Support\Facades\DB;

/**
 * Write, review and approve concept videos.
 *
 * The one rule this class exists to enforce: a machine may propose, only a
 * human may publish. Every ingest path forces `draft`, and nothing here can
 * stamp a servable status except through transition(), which requires a user
 * id. That is CONTENT LAW C5, and it is the reason the harvester can be run
 * by anyone without risking what a student sees.
 */
class ConceptVideoLibraryService
{
    /**
     * Record candidate videos for a concept as drafts.
     *
     * Idempotent on (concept_id, sub_institute_id, media_url): re-running the
     * harvester updates the score and metadata of a row it already proposed,
     * and never resurrects one a human has since approved or deprecated.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array{created:int, updated:int, skipped:int}
     */
    public function ingest(int $conceptId, ?int $chapterId, int $tenant, array $candidates, ?int $userId = null): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($candidates as $rank => $candidate) {
            $url = trim((string) ($candidate['media_url'] ?? ''));
            if ($url === '') {
                $result['skipped']++;
                continue;
            }

            $existing = ConceptVideo::query()
                ->where('concept_id', $conceptId)
                ->where('sub_institute_id', $tenant)
                ->where('media_url', $url)
                ->first();

            // A human has already ruled on this video. Re-proposing it would
            // either undo an approval or re-open something deliberately
            // rejected, so the harvester leaves it alone.
            if ($existing !== null && $existing->tagged_by === 'human') {
                $result['skipped']++;
                continue;
            }

            if ($existing !== null && ! PalVocabulary::isMachineWritable($existing->quality_status)) {
                $result['skipped']++;
                continue;
            }

            $payload = [
                'chapter_id' => $chapterId,
                'source' => (string) ($candidate['source'] ?? 'institute'),
                'provider' => $candidate['provider'] ?? null,
                'external_id' => $candidate['external_id'] ?? null,
                'content_master_id' => $candidate['content_master_id'] ?? null,
                'thumbnail_url' => $candidate['thumbnail_url'] ?? null,
                'title' => $this->clip($candidate['title'] ?? null, 512),
                'description' => $candidate['description'] ?? null,
                'attribution' => $this->clip($candidate['attribution'] ?? null, 191),
                'duration_seconds' => $candidate['duration_seconds'] ?? null,
                'language' => $candidate['language'] ?? null,
                'match_score' => $candidate['match_score'] ?? null,
                'match_reason' => $this->clip($candidate['match_reason'] ?? null, 255),
                'rank' => $rank,
                'quality_status' => 'draft',
                'tagged_by' => 'ai',
            ];

            if ($existing !== null) {
                $existing->fill($payload)->save();
                $result['updated']++;
                continue;
            }

            ConceptVideo::create($payload + [
                'concept_id' => $conceptId,
                'sub_institute_id' => $tenant,
                'media_url' => $url,
                'created_by' => $userId,
            ]);
            $result['created']++;
        }

        return $result;
    }

    /**
     * A human attaching a specific video to a concept by hand. Unlike the
     * harvester this may publish directly, because a person is doing it.
     */
    public function addManual(int $conceptId, ?int $chapterId, int $tenant, array $payload, int $userId, bool $approve = false): ConceptVideo
    {
        $url = trim((string) ($payload['media_url'] ?? ''));
        if ($url === '') {
            throw new \InvalidArgumentException('A video URL is required.');
        }

        $video = ConceptVideo::query()->firstOrNew([
            'concept_id' => $conceptId,
            'sub_institute_id' => $tenant,
            'media_url' => $url,
        ]);

        $video->fill([
            'chapter_id' => $chapterId,
            'source' => 'manual',
            'provider' => $payload['provider'] ?? null,
            'external_id' => $payload['external_id'] ?? null,
            'title' => $this->clip($payload['title'] ?? null, 512),
            'description' => $payload['description'] ?? null,
            'attribution' => $this->clip($payload['attribution'] ?? null, 191),
            'thumbnail_url' => $payload['thumbnail_url'] ?? null,
            'match_reason' => 'Added by a teacher for this concept',
            'tagged_by' => 'human',
            'created_by' => $video->exists ? $video->created_by : $userId,
            'updated_by' => $userId,
        ]);

        if (! $video->exists) {
            $video->quality_status = 'draft';
        }

        $video->save();

        if ($approve) {
            $this->transition($video, 'approved', $userId, 'Added and approved by a teacher');
        }

        return $video->refresh();
    }

    /**
     * Move a video between quality statuses. The only way anything becomes
     * servable, and it always records who decided.
     */
    public function transition(ConceptVideo $video, string $to, int $userId, ?string $note = null): ConceptVideo
    {
        if (! in_array($to, ['draft', 'approved', 'deprecated'], true)) {
            throw new \InvalidArgumentException("Unsupported status [{$to}] for a concept video.");
        }

        $video->quality_status = $to;
        $video->review_note = $note;
        $video->reviewed_by = $userId;
        $video->reviewed_at = now();
        $video->updated_by = $userId;
        $video->version = (int) $video->version + 1;
        $video->save();

        return $video;
    }

    /**
     * Approve many at once — the flow that makes reviewing a chapter's videos
     * an afternoon rather than a backlog.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkTransition(array $ids, int $tenant, string $to, int $userId, ?string $note = null): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($ids, $tenant, $to, $userId, $note) {
            $videos = ConceptVideo::query()
                ->whereIn('id', $ids)
                ->where('sub_institute_id', $tenant)
                ->get();

            foreach ($videos as $video) {
                $this->transition($video, $to, $userId, $note);
            }

            return $videos->count();
        });
    }

    /**
     * The review queue. Defaults to drafts, because that is what a reviewer
     * opens this screen to act on.
     *
     * @param  array{status?:string, chapter_id?:int, concept_id?:int, source?:string}  $filters
     */
    public function reviewQueue(int $tenant, array $filters = [], int $limit = 100): array
    {
        $query = ConceptVideo::query()
            ->where('sub_institute_id', $tenant)
            ->where('quality_status', $filters['status'] ?? 'draft');

        foreach (['chapter_id', 'concept_id', 'source'] as $key) {
            if (! empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }

        $rows = $query
            ->orderByDesc('match_score')
            ->orderBy('concept_id')
            ->orderBy('rank')
            ->limit($limit)
            ->get();

        // Concept names make the queue reviewable; without them a reviewer is
        // approving a video against an integer.
        $names = DB::table('lms_concept')
            ->whereIn('id', $rows->pluck('concept_id')->unique()->all())
            ->pluck('name', 'id');

        return $rows->map(static function (ConceptVideo $row) use ($names) {
            return [
                'id' => (int) $row->id,
                'concept_id' => (int) $row->concept_id,
                'concept_name' => $names[$row->concept_id] ?? null,
                'chapter_id' => $row->chapter_id === null ? null : (int) $row->chapter_id,
                'source' => $row->source,
                'provider' => $row->provider,
                'title' => $row->title,
                'media_url' => $row->media_url,
                'thumbnail_url' => $row->thumbnail_url,
                'attribution' => $row->attribution,
                'duration_seconds' => $row->duration_seconds,
                'match_score' => $row->match_score,
                'match_reason' => $row->match_reason,
                'quality_status' => $row->quality_status,
                'tagged_by' => $row->tagged_by,
            ];
        })->all();
    }

    /** @return array<string, int> */
    public function pipelineCounts(int $tenant, ?int $chapterId = null): array
    {
        $query = ConceptVideo::query()->where('sub_institute_id', $tenant);

        if ($chapterId !== null && $chapterId > 0) {
            $query->where('chapter_id', $chapterId);
        }

        $counts = ['draft' => 0, 'approved' => 0, 'deprecated' => 0];

        foreach ($query->select('quality_status', DB::raw('COUNT(*) as n'))->groupBy('quality_status')->get() as $row) {
            $counts[$row->quality_status] = (int) $row->n;
        }

        return $counts;
    }

    protected function clip(?string $value, int $length): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $length);
    }
}
