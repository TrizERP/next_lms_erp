<?php

namespace App\Services\PAL\Framework;

use App\Models\PAL\Content;
use App\Models\PAL\FrameworkProgress;
use App\Models\PAL\LearningEvidence;
use App\Models\PAL\LearningEvent;
use App\Models\PAL\PedagogyEffectiveness;
use App\Models\PAL\TelemetryEvent;
use Illuminate\Support\Facades\DB;

class FrameworkProgressService
{
    public function __construct(private readonly FrameworkCatalogService $catalog)
    {
    }

    public function getCatalog(): array
    {
        return $this->catalog->catalog();
    }

    public function getContentMetadata(int $contentId): array
    {
        $content = Content::findOrFail($contentId);

        return $this->serializeContent($content);
    }

    public function upsertContentMetadata(int $contentId, array $payload): array
    {
        $content = Content::findOrFail($contentId);
        $metadata = $this->catalog->normalizeContentMetadata($payload);

        $content->fill($metadata);
        $content->save();

        return $this->serializeContent($content->fresh());
    }

    public function recordTelemetryEvidence(TelemetryEvent $event, array $statement = []): ?LearningEvidence
    {
        $context = (array) ($statement['context']['extensions'] ?? []);
        $contentId = $this->extractNumericId($context['content_id'] ?? null)
            ?? $this->extractNumericId($event->object_id);
        $content = $contentId ? Content::find($contentId) : null;

        $frameworkTags = $this->frameworkTagsFromContent($content);
        $pedagogyTag = $content?->pedagogy_tag ?? $this->catalog->normalizePedagogy($context['pedagogy_tag'] ?? '');

        $payload = [
            'verb' => $event->verb,
            'content_id' => $content?->id,
            'concept_id' => $content?->concept_id,
            'pedagogy_tag' => $pedagogyTag,
            'h5p_type' => $content?->h5p_type ?? $context['h5p_type'] ?? null,
            'framework_tags' => $frameworkTags,
            'score' => $this->extractScore($event->result),
            'duration_seconds' => (int) ($event->duration_seconds ?? 0),
            'completion' => (bool) data_get($event->result, 'completion', false),
            'language' => $context['language'] ?? data_get($statement, 'context.language'),
            'platform' => $context['platform'] ?? data_get($statement, 'context.platform'),
            'riasec_signal' => $content?->riasec_signal,
            'gardner_intelligence' => $content?->gardner_intelligence ?? [],
            'misconception_data' => $context['misconception_data'] ?? [],
        ];

        LearningEvent::create([
            'learner_id' => $event->actor_id,
            'event_type' => $this->mapVerbToEventType($event->verb),
            'content_id' => $payload['content_id'],
            'concept_id' => $payload['concept_id'],
            'session_id' => $event->session_id,
            'pedagogy_tag' => $payload['pedagogy_tag'],
            'h5p_type' => $payload['h5p_type'],
            'framework_tags' => $payload['framework_tags'],
            'score' => $payload['score'],
            'duration_seconds' => $payload['duration_seconds'],
            'completion' => $payload['completion'],
            'source' => 'xapi',
            'language' => $payload['language'],
            'platform' => $payload['platform'],
            'riasec_signal' => $payload['riasec_signal'],
            'gardner_intelligence' => $payload['gardner_intelligence'],
            'misconception_data' => $payload['misconception_data'],
            'event_data' => $payload,
        ]);

        if ($payload['content_id'] === null && empty(array_filter($frameworkTags))) {
            return null;
        }

        $evidence = LearningEvidence::create([
            'learner_id' => $event->actor_id,
            'content_id' => $payload['content_id'],
            'concept_id' => $payload['concept_id'],
            'session_id' => $event->session_id,
            'pedagogy_tag' => $payload['pedagogy_tag'],
            'h5p_type' => $payload['h5p_type'],
            'evidence_type' => $payload['verb'],
            'framework_tags' => $frameworkTags,
            'score' => $payload['score'],
            'duration_seconds' => $payload['duration_seconds'],
            'completion' => $payload['completion'],
            'evidence_source' => 'xapi',
            'context_data' => $payload,
            'recorded_at' => $event->timestamp,
        ]);

        $this->updateFrameworkProgress($event->actor_id, $frameworkTags, $payload);

        return $evidence;
    }

    public function getLearnerDashboard(int $learnerId): array
    {
        $frameworkProgress = FrameworkProgress::query()
            ->where('learner_id', $learnerId)
            ->orderBy('framework_type')
            ->orderByDesc('progress_score')
            ->get()
            ->groupBy('framework_type')
            ->map(fn ($group) => $group->map(fn ($row) => [
                'tag' => $row->framework_tag,
                'progress' => round((float) $row->progress_score, 1),
                'evidence_count' => (int) $row->evidence_count,
                'status' => $row->status,
            ])->values())
            ->toArray();

        $pedagogy = PedagogyEffectiveness::query()
            ->select('pedagogy_type', DB::raw('AVG(effectiveness_score) as avg_score'), DB::raw('COUNT(*) as sessions'))
            ->where('learner_id', $learnerId)
            ->groupBy('pedagogy_type')
            ->orderByDesc('avg_score')
            ->get()
            ->map(fn ($row) => [
                'tag' => $this->catalog->normalizePedagogy($row->pedagogy_type),
                'effectiveness' => round((float) $row->avg_score, 1),
                'sessions' => (int) $row->sessions,
            ])
            ->values()
            ->toArray();

        $evidenceCount = LearningEvidence::where('learner_id', $learnerId)->count();
        $recentHistory = LearningEvent::where('learner_id', $learnerId)
            ->latest()
            ->limit(10)
            ->get(['event_type', 'pedagogy_tag', 'h5p_type', 'score', 'completion', 'created_at'])
            ->map(fn ($row) => [
                'event_type' => $row->event_type,
                'pedagogy_tag' => $row->pedagogy_tag,
                'h5p_type' => $row->h5p_type,
                'score' => $row->score,
                'completion' => (bool) $row->completion,
                'created_at' => optional($row->created_at)->toIso8601String(),
            ])
            ->toArray();

        $mastery = DB::table('pal_competencies')->where('learner_id', $learnerId)->avg('mastery_score') ?? 0;
        $engagement = DB::table('pal_learning_sessions')->where('learner_id', $learnerId)->avg('engagement_score') ?? 0;

        return [
            'learner_id' => $learnerId,
            'current_pedagogy' => $pedagogy[0]['tag'] ?? 'inquiry_based',
            'pedagogy_effectiveness' => $pedagogy,
            'mastery' => round((float) $mastery, 1),
            'engagement' => round((float) $engagement, 1),
            'framework_progress' => $frameworkProgress,
            'learning_evidence_count' => $evidenceCount,
            'h5p_activity_history' => $recentHistory,
            'stream_mountain_sky' => $this->buildProgressBands($learnerId),
        ];
    }

    public function getTeacherDashboard(array $filters = []): array
    {
        $base = LearningEvidence::query();
        if (! empty($filters['learner_id'])) {
            $base->where('learner_id', (int) $filters['learner_id']);
        }

        $pedagogy = PedagogyEffectiveness::query()
            ->select('pedagogy_type', DB::raw('AVG(effectiveness_score) as avg_score'), DB::raw('COUNT(*) as learners'))
            ->groupBy('pedagogy_type')
            ->orderByDesc('avg_score')
            ->limit(12)
            ->get()
            ->map(fn ($row) => [
                'pedagogy_type' => $this->catalog->normalizePedagogy($row->pedagogy_type),
                'effectiveness' => round((float) $row->avg_score, 1),
                'learners' => (int) $row->learners,
            ])
            ->values()
            ->toArray();

        $frameworkSummary = FrameworkProgress::query()
            ->select('framework_type', DB::raw('COUNT(*) as rows'), DB::raw('AVG(progress_score) as avg_progress'))
            ->groupBy('framework_type')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->framework_type => [
                    'rows' => (int) $row->rows,
                    'avg_progress' => round((float) $row->avg_progress, 1),
                ],
            ])
            ->toArray();

        return [
            'pedagogy' => $pedagogy,
            'evidence_total' => (clone $base)->count(),
            'framework_summary' => $frameworkSummary,
            'content_performance' => $this->contentPerformance(),
        ];
    }

    private function serializeContent(Content $content): array
    {
        return [
            'id' => $content->id,
            'title' => $content->title,
            'pedagogy_tag' => $content->pedagogy_tag,
            'pedagogy_tags' => $content->pedagogy_tags ?? [],
            'casel_domain' => $content->casel_domain,
            'ngss_practice' => $content->ngss_practice,
            'ncdg_goal' => $content->ncdg_goal,
            'music_domain' => $content->music_domain,
            'sports_domain' => $content->sports_domain,
            'finance_domain' => $content->finance_domain,
            'riasec_signal' => $content->riasec_signal,
            'gardner_intelligence' => $content->gardner_intelligence ?? [],
            'h5p_type' => $content->h5p_type,
            'assessment_method' => $content->assessment_method,
            'cross_curricular_links' => $content->cross_curricular_links ?? [],
            'framework_metadata' => $content->framework_metadata ?? [],
            'evidence_requirements' => $content->evidence_requirements ?? [],
            'pedagogy_profile' => $content->pedagogy_profile ?? [],
        ];
    }

    private function frameworkTagsFromContent(?Content $content): array
    {
        if (! $content) {
            return [];
        }

        return array_filter([
            'casel' => $content->casel_domain,
            'ngss' => $content->ngss_practice,
            'ncdg' => $content->ncdg_goal,
            'music' => $content->music_domain,
            'sports' => $content->sports_domain,
            'finance' => $content->finance_domain,
        ]);
    }

    private function updateFrameworkProgress(int $learnerId, array $frameworkTags, array $payload): void
    {
        foreach ($frameworkTags as $frameworkType => $frameworkTag) {
            $row = FrameworkProgress::firstOrNew([
                'learner_id' => $learnerId,
                'framework_type' => $frameworkType,
                'framework_tag' => $frameworkTag,
            ]);

            $score = $payload['score'] ?? ($payload['completion'] ? 100 : 0);
            $row->progress_score = round((($row->progress_score ?? 0) * ($row->evidence_count ?? 0) + $score) / max(1, ($row->evidence_count ?? 0) + 1), 2);
            $row->evidence_count = (int) ($row->evidence_count ?? 0) + 1;
            $row->last_evidenced_at = now();
            $row->status = $row->progress_score >= 75 ? 'mastered' : ($row->progress_score >= 40 ? 'developing' : 'emerging');
            $row->metadata = [
                'pedagogy_tag' => $payload['pedagogy_tag'] ?? null,
                'h5p_type' => $payload['h5p_type'] ?? null,
            ];
            $row->save();
        }
    }

    private function buildProgressBands(int $learnerId): array
    {
        $average = FrameworkProgress::where('learner_id', $learnerId)->avg('progress_score') ?? 0;

        return [
            'stream' => round(min(100, $average), 1),
            'mountain' => round(min(100, max(0, $average - 10)), 1),
            'sky' => round(min(100, max(0, $average - 20)), 1),
        ];
    }

    private function contentPerformance(): array
    {
        return LearningEvidence::query()
            ->select('content_id', DB::raw('AVG(score) as avg_score'), DB::raw('COUNT(*) as evidence_count'))
            ->whereNotNull('content_id')
            ->groupBy('content_id')
            ->orderByDesc('avg_score')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'content_id' => (int) $row->content_id,
                'avg_score' => round((float) $row->avg_score, 1),
                'evidence_count' => (int) $row->evidence_count,
            ])
            ->toArray();
    }

    private function mapVerbToEventType(string $verb): string
    {
        return match ($verb) {
            'answered' => 'assessment_answer',
            'completed' => 'content_completed',
            'attempted' => 'content_attempted',
            'progressed' => 'content_progressed',
            'submitted' => 'portfolio_submitted',
            default => $verb,
        };
    }

    private function extractNumericId(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (is_string($value) && preg_match('/(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private function extractScore(mixed $result): float
    {
        if (! is_array($result)) {
            return 0;
        }
        $raw = data_get($result, 'score.raw');
        $max = data_get($result, 'score.max');
        if (is_numeric($raw) && is_numeric($max) && (float) $max > 0) {
            return round(((float) $raw / (float) $max) * 100, 2);
        }

        return is_numeric($raw) ? (float) $raw : 0;
    }
}
