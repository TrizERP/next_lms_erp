<?php

namespace App\Jobs;

use App\Models\lms\chapterModel;
use App\Services\ContentGenerationService;
use App\Services\StudyDeck\Documents\DocumentKind;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes ONE study document (revision notes, remedial class or classroom activities) for a chapter in the background, the way the
 * Classroom Resource drawer does it: ContentGenerationService::generateStudyDocument builds it from the chapter's own data, checks
 * it, and stores it as one content_master row with its PDF. AI Stack (Teach/Learn automations) starts it from here because a
 * document takes many model calls, far longer than one Start Agents request should wait.
 *
 * Unique per school, chapter and kind, so pressing Start twice is one document. A document that fails validation is not stored and
 * the reason is logged (nothing is retried: a second try is the teacher's decision).
 */
class GenerateStudyDocumentJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 3600;
    public int $uniqueFor = 3600;

    public function __construct(
        public int $chapterId,
        public int $tenant,
        public string $kind,
        public ?int $userId = null,
        public ?string $syear = null,
        public ?string $profile = null,
    ) {
        // The queue a worker already runs for Course Presentation drafts, so this is not left waiting where no worker listens.
        // With the synchronous driver it runs after the response, as the presentation draft does.
        if (config('queue.default') !== 'sync') {
            $this->onConnection(GenerateCoursePresentationJob::CONNECTION)
                ->onQueue((string) config('queue.connections.' . GenerateCoursePresentationJob::CONNECTION . '.queue', 'course-presentation'));
        }
    }

    /** Where the reason a document was not stored is kept for the AI Stack screen (a stored document needs no marker: its row is the proof). */
    public static function failureKey(int $tenant, int $chapterId, string $kind): string
    {
        return "study-document-failure:{$tenant}:{$chapterId}:{$kind}";
    }

    public function uniqueId(): string
    {
        return "study-document:{$this->tenant}:{$this->chapterId}:{$this->kind}";
    }

    public function handle(ContentGenerationService $content): void
    {
        @set_time_limit(0);
        $failureKey = self::failureKey($this->tenant, $this->chapterId, $this->kind);
        Cache::forget($failureKey);
        $kind = DocumentKind::tryFrom($this->kind);
        $chapter = chapterModel::where('id', $this->chapterId)->first();
        if ($kind === null || $chapter === null || (int) $chapter->sub_institute_id !== $this->tenant) {
            Log::warning('Study document job refused', ['chapter_id' => $this->chapterId, 'kind' => $this->kind]);
            Cache::put($failureKey, 'The chapter was not found for this school.', 86400);

            return;
        }
        $gradeId = $chapter->grade_id ?: DB::table('standard')->where('id', $chapter->standard_id)->value('grade_id');
        if (!$gradeId) {
            Log::warning('Study document job: grade could not be resolved', ['chapter_id' => $this->chapterId]);
            Cache::put($failureKey, 'The grade of this chapter could not be resolved.', 86400);

            return;
        }

        $result = $content->generateStudyDocument($kind, [
            'chapter' => $chapter,
            'chapter_name' => $chapter->chapter_name,
            'grade_id' => $gradeId,
            'concept_id' => null,
            'concept_ids' => [],
            'sub_institute_id' => $this->tenant,
            'syear' => $this->syear ?? $chapter->syear,
            'created_by' => (int) ($this->userId ?? 0),
            'user_profile_name' => $this->profile,
        ]);

        if (($result['http'] ?? 500) >= 400 || empty($result['body']['success'])) {
            $reason = trim((string) ($result['body']['message'] ?? 'The document was not stored.') . ' ' . (string) (((array) ($result['body']['errors'] ?? []))[0] ?? ''));
            Cache::put($failureKey, mb_substr($reason, 0, 400), 86400);
            Log::warning('Study document was not stored', [
                'chapter_id' => $this->chapterId,
                'kind' => $this->kind,
                'message' => $result['body']['message'] ?? null,
                'errors' => array_slice((array) ($result['body']['errors'] ?? []), 0, 5),
            ]);
        }
    }
}
