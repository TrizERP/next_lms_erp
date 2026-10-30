<?php

namespace App\Services\QuestionPapers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Persistent state of a Question Paper job: creation, the single-worker lease, guarded progress
 * writes, recovery selection and the shape returned to the browser.
 *
 * Every progress write is conditional on the caller still holding the lease, so a worker that
 * stalled past its lease cannot overwrite what its replacement has done.
 */
class QuestionPaperJobService
{
    public const TERMINAL = ['completed', 'completed_with_warnings', 'failed'];

    public const LEASE_SECONDS = 1200;

    public function create(int $documentId, int $tenant, int $userId, array $academic = []): object
    {
        $uuid = (string) Str::uuid();
        $now = now();

        $id = DB::table('question_paper_jobs')->insertGetId([
            'uuid' => $uuid,
            'document_id' => $documentId,
            'sub_institute_id' => $tenant,
            'user_id' => $userId,
            'status' => 'queued',
            'stage' => 'validating',
            'standard_id' => $academic['standard_id'] ?? null,
            'subject_id' => $academic['subject_id'] ?? null,
            'chapter_id' => $academic['chapter_id'] ?? null,
            'academic_year' => $academic['academic_year'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->find($id);
    }

    public function find(int $id): ?object
    {
        return DB::table('question_paper_jobs')->where('id', $id)->first();
    }

    public function findByUuid(string $uuid): ?object
    {
        return DB::table('question_paper_jobs')->where('uuid', $uuid)->first();
    }

    /** Tenant-scoped lookup; the caller then applies the owner/admin rule. */
    public function findForTenant(string $uuid, int $tenant): ?object
    {
        return DB::table('question_paper_jobs')->where('uuid', $uuid)->where('sub_institute_id', $tenant)->first();
    }

    /**
     * Take the job for one worker. Atomic: the UPDATE only matches a non-terminal job whose lease is
     * free or expired, so two workers racing for it cannot both succeed.
     *
     * @return string|null the lease token, or null when the job is finished or someone else holds it
     */
    public function acquireLease(int $id): ?string
    {
        $token = (string) Str::uuid();
        $now = now();

        $taken = DB::table('question_paper_jobs')
            ->where('id', $id)
            ->whereNotIn('status', self::TERMINAL)
            ->where(function ($q) use ($now) {
                $q->whereNull('lease_token')->orWhere('lease_expires_at', '<', $now);
            })
            ->update([
                'lease_token' => $token,
                'lease_expires_at' => $now->copy()->addSeconds(self::LEASE_SECONDS),
                'status' => 'processing',
                'attempts' => DB::raw('attempts + 1'),
                'started_at' => DB::raw('COALESCE(started_at, ' . DB::getPdo()->quote($now->toDateTimeString()) . ')'),
                'updated_at' => $now,
            ]);

        return $taken === 1 ? $token : null;
    }

    public function releaseLease(int $id, string $token): void
    {
        DB::table('question_paper_jobs')->where('id', $id)->where('lease_token', $token)
            ->update(['lease_token' => null, 'lease_expires_at' => null, 'updated_at' => now()]);
    }

    /**
     * Write progress while holding the lease; also extends it.
     *
     * @throws LeaseLostException when the lease is no longer ours
     */
    public function update(int $id, string $token, array $fields): void
    {
        $fields['lease_expires_at'] = now()->addSeconds(self::LEASE_SECONDS);
        $fields['updated_at'] = now();

        $n = $this->applyLeasedUpdate($id, $token, $fields);
        // MySQL reports rows CHANGED, so an update that rewrites identical values returns 0 even though the
        // row matched. Only treat it as a lost lease if the lease really is no longer ours.
        if ($n !== 1 && !$this->holdsLease($id, $token)) {
            throw new LeaseLostException("Lease lost for question paper job {$id}.");
        }
    }

    /** The conditional write itself; its own method so a test can make it report 0 changed rows. */
    protected function applyLeasedUpdate(int $id, string $token, array $fields): int
    {
        return DB::table('question_paper_jobs')->where('id', $id)->where('lease_token', $token)->update($fields);
    }

    protected function holdsLease(int $id, string $token): bool
    {
        return DB::table('question_paper_jobs')->where('id', $id)->where('lease_token', $token)->exists();
    }

    /**
     * Item counts per job and state, in one query, for the status breakdown.
     *
     * @param  list<int>  $jobIds
     * @return array<int, array<string, int>> jobId => state => count
     */
    public function breakdowns(array $jobIds): array
    {
        if ($jobIds === []) {
            return [];
        }
        $out = [];
        $rows = DB::table('question_paper_items')->whereIn('job_id', $jobIds)->selectRaw('job_id, state, count(*) as c')->groupBy('job_id', 'state')->get();
        foreach ($rows as $row) {
            $out[(int) $row->job_id][$row->state] = (int) $row->c;
        }

        return $out;
    }

    /** @param array<string, int> $states */
    public static function shapeBreakdown(array $states): array
    {
        return [
            'saved_active' => $states['saved'] ?? 0,
            'saved_inactive' => $states['needs_review'] ?? 0,
            'unresolved' => $states['unresolved'] ?? 0,
            'duplicate' => $states['duplicate'] ?? 0,
            'skipped' => $states['skipped'] ?? 0,
            'pending' => $states['parsed'] ?? 0,
        ];
    }

    /**
     * What the system has resolved, by name, plus the candidates it could not choose between.
     *
     * @return array{standard: ?array{id: int, name: string}, subject: ?array{id: int, name: string}, chapter: ?array{id: int, name: string}, candidates: array<string, mixed>, heading: array<string, mixed>}
     */
    public function academicContext(object $job): array
    {
        $name = fn (string $table, string $column, $id) => $id === null ? null : (($n = DB::table($table)->where('id', $id)->where('sub_institute_id', $job->sub_institute_id)->value($column)) === null ? null : ['id' => (int) $id, 'name' => (string) $n]);
        $meta = json_decode((string) $job->metadata_notes, true);
        $meta = is_array($meta) ? $meta : [];

        return [
            'standard' => $name('standard', 'name', $job->standard_id),
            'subject' => $name('subject', 'subject_name', $job->subject_id),
            'chapter' => $name('chapter_master', 'chapter_name', $job->chapter_id),
            'candidates' => (object) ($meta['candidates'] ?? []),
            // What the questions suggest when the heading did not settle a field. Suggestions only; never assigned.
            'suggestions' => (object) ($meta['suggestions'] ?? []),
            'heading' => (object) ($meta['header'] ?? []),
        ];
    }

    /** Append to the failure history; the last entry is also what the owner sees as last_error. */
    public function recordError(int $id, string $message, bool $transient): void
    {
        $job = $this->find($id);
        $log = json_decode((string) ($job->error_log ?? ''), true);
        $log = is_array($log) ? $log : [];
        $log[] = ['at' => now()->toIso8601String(), 'attempt' => (int) $job->attempts, 'transient' => $transient, 'message' => mb_substr($message, 0, 500)];

        DB::table('question_paper_jobs')->where('id', $id)->update([
            'last_error' => mb_substr($message, 0, 500),
            'error_log' => json_encode(array_slice($log, -20), JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    /**
     * Jobs the scheduler should (re)dispatch: waiting too long unpicked, or running with a dead lease.
     * Dispatching one that is actually fine is harmless, because the lease admits a single worker.
     *
     * @return list<object>
     */
    public function dispatchable(int $limit): array
    {
        $now = now();

        return DB::table('question_paper_jobs')
            ->whereNotIn('status', self::TERMINAL)
            ->where(function ($q) use ($now) {
                $q->whereNull('lease_token')->orWhere('lease_expires_at', '<', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('last_dispatched_at')
                    ->orWhere(function ($w) use ($now) {
                        $w->where('status', 'queued')->where('last_dispatched_at', '<', $now->copy()->subMinutes(3));
                    })
                    ->orWhere(function ($w) use ($now) {
                        $w->whereIn('status', ['processing', 'retrying'])->where('last_dispatched_at', '<', $now->copy()->subMinutes(10));
                    });
            })
            ->orderBy('id')->limit($limit)->get()->all();
    }

    public function markDispatched(int $id): void
    {
        DB::table('question_paper_jobs')->where('id', $id)->update(['last_dispatched_at' => now()]);
    }

    /**
     * Put the job on the queue. Refused on the `sync` driver: it would run the whole extraction inside
     * the web request or the scheduler, which is exactly what this feature must not do. The row stays
     * queued and is picked up as soon as a real queue driver and worker are configured.
     */
    public function dispatch(object $job): bool
    {
        if (config('queue.default') === 'sync') {
            \Illuminate\Support\Facades\Log::warning("Question paper job {$job->uuid} not dispatched: QUEUE_CONNECTION is sync. Set it to database or redis and run a queue worker.");

            return false;
        }

        $this->markDispatched((int) $job->id);
        \App\Jobs\QuestionPaperExtractionJob::dispatch($job->uuid);

        return true;
    }

    /**
     * Whether $auth may see the job: its uploader, or an institute administrator of the same school.
     * The tenant is part of the lookup (findForTenant), so this only decides within one school.
     */
    public function canView(object $job, int $userId, int $isAdmin): bool
    {
        return (int) $job->user_id === $userId || $isAdmin >= 1;
    }

    /** Owner-triggered re-run; returns false when the job is not in a retryable state. */
    public function prepareManualRetry(object $job, array $academic): bool
    {
        $leaseExpired = $job->status === 'processing' && $job->lease_expires_at !== null && \Illuminate\Support\Carbon::parse($job->lease_expires_at)->isPast();
        if ((!in_array($job->status, ['failed', 'completed_with_warnings', 'retrying'], true) && !$leaseExpired) || !$job->retryable) {
            return false;
        }

        DB::transaction(function () use ($job, $academic) {
            $extractionComplete = $job->total_chunks !== null && (int) $job->parsed_chunks >= (int) $job->total_chunks;
            $fields = [
                'status' => 'queued',
                'stage' => $extractionComplete ? 'saving' : 'queued',
                // Parsed item rows make metadata-only retries independent from PDF extraction.
                'attempts' => 0,
                'manual_retries' => (int) $job->manual_retries + 1,
                'retryable' => true,
                'last_error' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_dispatched_at' => null,
                'finished_at' => null,
                'updated_at' => now(),
            ];
            foreach (['standard_id', 'subject_id', 'chapter_id'] as $key) {
                if (!empty($academic[$key])) {
                    $fields[$key] = (int) $academic[$key];
                }
            }
            $extractionComplete = $job->total_chunks !== null && (int) $job->parsed_chunks >= (int) $job->total_chunks;
            if ($extractionComplete) {
                $fields['total_chunks'] = (int) $job->parsed_chunks;
                $fields['parsed_chunks'] = (int) $job->parsed_chunks;
            }
            DB::table('question_paper_jobs')->where('id', $job->id)->update($fields);

            // Items that waited for metadata get another go; saved ones are never touched.
            DB::table('question_paper_items')->where('job_id', $job->id)->whereIn('state', ['unresolved', 'needs_review'])
                ->whereNull('question_id')->update(['state' => 'parsed', 'reason' => null, 'updated_at' => now()]);

            // Existing inactive rows are this job's own questions. A teacher's metadata choice
            // updates their academic foreign keys in place; it never inserts a second question.
            if (!empty($academic['standard_id']) && !empty($academic['subject_id'])) {
                $questionIds = DB::table('question_paper_items')->where('job_id', $job->id)->whereNotNull('question_id')->pluck('question_id');
                // The chapter column is written ONLY when a paper-wide chapter was chosen. Writing null here (as an earlier
                // version did) would erase the chapter already given to individual questions.
                $inPlace = ['standard_id' => $academic['standard_id'], 'subject_id' => $academic['subject_id']];
                if (!empty($academic['chapter_id'])) {
                    $inPlace['chapter_id'] = (int) $academic['chapter_id'];
                }
                DB::table('lms_question_master')->whereIn('id', $questionIds)->where('sub_institute_id', $job->sub_institute_id)
                    ->where('answer', 'like', '%"job":"' . $job->uuid . '"%')
                    ->update($inPlace);
            }
            foreach (($academic['item_chapters'] ?? []) as $itemId => $chapterId) {
                $item = DB::table('question_paper_items')->where('id', (int) $itemId)->where('job_id', $job->id)->lockForUpdate()->first();
                if (!$item || !$chapterId) continue;
                $payload = json_decode((string) $item->payload, true);
                $payload = is_array($payload) ? $payload : [];
                $payload['chapter_id'] = (int) $chapterId;
                $payload['chapter_source'] = 'teacher'; // automatic matching never overrides a person's choice
                DB::table('question_paper_items')->where('id', $item->id)->update(['payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
                if ($item->question_id !== null) {
                    DB::table('lms_question_master')->where('id', $item->question_id)->where('sub_institute_id', $job->sub_institute_id)
                        ->where('answer', 'like', '%"job":"' . $job->uuid . '"%')->update(['chapter_id' => (int) $chapterId]);
                }
            }
        });

        return true;
    }

    /** The browser's view of a job. No storage paths, no raw error log. */
    public function present(object $job, ?object $document = null, ?array $states = null): array
    {
        $running = in_array($job->status, ['queued', 'processing', 'retrying'], true);
        $pagesKnown = $job->total_pages !== null && (int) $job->total_pages > 0;

        return [
            'id' => $job->uuid,
            'document_id' => (int) $job->document_id,
            'document_title' => $document->title ?? null,
            'file_name' => $document->original_file_name ?? null,
            'status' => $job->status,
            'stage' => $job->stage,
            'standard_id' => $job->standard_id !== null ? (int) $job->standard_id : null,
            'subject_id' => $job->subject_id !== null ? (int) $job->subject_id : null,
            'chapter_id' => $job->chapter_id !== null ? (int) $job->chapter_id : null,
            'academic_year' => $job->academic_year,
            'metadata_notes' => $this->notes($job),
            'total_pages' => $pagesKnown ? (int) $job->total_pages : null,
            'processed_pages' => (int) $job->processed_pages,
            'total_chunks' => $job->total_chunks !== null ? (int) $job->total_chunks : null,
            'parsed_chunks' => (int) $job->parsed_chunks,
            'questions_extracted' => (int) $job->questions_extracted,
            'questions_saved' => (int) $job->questions_saved,
            'questions_needing_review' => (int) $job->questions_needing_review,
            'questions_skipped' => (int) $job->questions_skipped,
            // Per-state item counts straight from question_paper_items (null when the caller did not load them).
            'breakdown' => $states === null ? null : self::shapeBreakdown($states),
            'used_ocr' => (bool) $job->used_ocr,
            // Real progress only: pages read so far out of the pages the file has, once known.
            'progress_percent' => $this->percent($job, $running),
            'attempts' => (int) $job->attempts,
            // Seconds a queued job has gone unpicked, by the SERVER clock (so browser clock skew cannot matter). The screen
            // uses it to say "no worker has started this" instead of showing "Waiting" for ever.
            'queued_seconds' => $job->status === 'queued' ? max(0, (int) \Illuminate\Support\Carbon::parse($job->updated_at ?? $job->created_at)->diffInSeconds(now(), true)) : null,
            // For a queued job only: is the question-papers worker alive (active|busy|not_detected|inline), or null when
            // that cannot be known. The screen uses it to say WHY nothing has started, instead of waiting in silence.
            'worker_state' => $job->status === 'queued' ? QuestionPaperWorkerHeartbeat::state() : null,
            'last_error' => $job->last_error,
            'can_retry' => (in_array($job->status, ['failed', 'completed_with_warnings', 'retrying'], true) || ($job->status === 'processing' && $job->lease_expires_at !== null && \Illuminate\Support\Carbon::parse($job->lease_expires_at)->isPast())) && (bool) $job->retryable,
            'created_at' => $job->created_at,
            'started_at' => $job->started_at,
            'finished_at' => $job->finished_at,
        ];
    }

    /** @return list<string> */
    private function notes(object $job): array
    {
        $decoded = json_decode((string) $job->metadata_notes, true);

        return is_array($decoded) && is_array($decoded['notes'] ?? null) ? array_values($decoded['notes']) : [];
    }

    private function percent(object $job, bool $running): ?int
    {
        if (!$running) {
            return in_array($job->status, ['completed', 'completed_with_warnings'], true) ? 100 : null;
        }
        if ($job->total_chunks !== null && (int) $job->total_chunks > 0 && in_array($job->stage, ['parsing_questions', 'extracting_text'], true)) {
            // Parsing is the long stage; scale it to 0-90 and leave the rest for saving/finalizing.
            return (int) floor(90 * min(1, (int) $job->parsed_chunks / (int) $job->total_chunks));
        }

        return in_array($job->stage, ['saving', 'finalizing', 'resolving_metadata'], true) ? 90 : null;
    }
}
