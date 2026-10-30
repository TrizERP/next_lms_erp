<?php

namespace App\Services\QuestionPapers;

use App\Services\Documents\DocumentStorageService;
use App\Services\Documents\Extraction\TextExtractionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Runs one Question Paper job: text -> questions -> academic context -> question bank -> outcome.
 *
 * Re-entrant by design. Progress lives in the database (parsed_chunks, one question_paper_items row
 * per question), so a retry or a replacement worker resumes where the last one stopped and cannot
 * write a question twice (see QuestionPaperPersistence). Only one worker runs a job at a time (lease).
 */
class QuestionPaperProcessor
{
    public const MAX_ATTEMPTS = 4;

    private const CHUNK_CHARS = 8000;

    public function __construct(
        private QuestionPaperJobService $jobs,
        private QuestionPaperParser $parser,
        private QuestionPaperItemNormaliser $normaliser,
        private QuestionPaperMetadataResolver $resolver,
        private QuestionPaperPersistence $persistence,
        private UserNotificationService $notifications,
        private QuestionPaperChapterMatcher $matcher,
    ) {
    }

    /**
     * @throws Throwable a retryable failure, rethrown so the queue backs off; the job row already
     *                   records it and is back to "retrying"
     */
    public function run(string $uuid): void
    {
        $job = $this->jobs->findByUuid($uuid);
        if (!$job || in_array($job->status, QuestionPaperJobService::TERMINAL, true)) {
            return;
        }

        $token = $this->jobs->acquireLease((int) $job->id);
        if ($token === null) {
            return; // finished meanwhile, or another worker owns it
        }

        $temp = null;
        try {
            $job = $this->jobs->find((int) $job->id);
            if ((int) $job->attempts > self::MAX_ATTEMPTS) {
                throw new PermanentQuestionPaperException('Gave up after ' . self::MAX_ATTEMPTS . ' attempts. Last error: ' . ($job->last_error ?? 'unknown'), true);
            }

            $parsingDone = $job->total_chunks !== null && (int) $job->parsed_chunks >= (int) $job->total_chunks;

            if (!$parsingDone) {
                $temp = $this->fetchPdf($job, $token);
                $chunks = $this->extract($job, $token, $temp);
                $this->parse($job, $token, $chunks);
            }

            $this->resolveAndSave($job, $token);
            $this->finalize($job, $token);
        } catch (LeaseLostException) {
            Log::warning("Question paper job {$uuid}: lease lost, a newer worker owns it.");

            return; // do not touch the row: it is no longer ours
        } catch (PermanentQuestionPaperException $e) {
            $this->fail((int) $job->id, $token, $e->getMessage(), $e->manualRetry);
        } catch (Throwable $e) {
            Log::error("Question paper job {$uuid}: " . $e->getMessage());
            $this->jobs->recordError((int) $job->id, $e->getMessage(), true);

            if ((int) $this->jobs->find((int) $job->id)->attempts >= self::MAX_ATTEMPTS) {
                // The error is already in the history; only the outcome is left to record.
                $this->markFailed($this->jobs->find((int) $job->id), 'Failed after ' . self::MAX_ATTEMPTS . ' attempts: ' . $e->getMessage(), true);

                return;
            }

            DB::table('question_paper_jobs')->where('id', $job->id)->where('lease_token', $token)->update(['status' => 'retrying', 'updated_at' => now()]);
            throw $e;
        } finally {
            if ($temp && is_file($temp)) {
                @unlink($temp);
            }
            $this->jobs->releaseLease((int) $job->id, $token);
        }
    }

    /** Called by the queue when attempts are exhausted or the worker was killed (timeout). */
    public function failPermanently(string $uuid, string $message): void
    {
        $job = $this->jobs->findByUuid($uuid);
        if (!$job || in_array($job->status, QuestionPaperJobService::TERMINAL, true)) {
            return;
        }
        $this->jobs->recordError((int) $job->id, $message, false);
        $this->markFailed($job, $message, true);
    }

    private function fetchPdf(object $job, string $token): string
    {
        $this->jobs->update((int) $job->id, $token, ['stage' => 'validating']);

        $document = DB::table('document_master')->where('id', $job->document_id)->whereNull('deleted_at')->first();
        if (!$document) {
            throw new PermanentQuestionPaperException('The uploaded document no longer exists.');
        }

        $temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qp_' . $job->uuid . '.pdf';
        file_put_contents($temp, app(DocumentStorageService::class)->get($document->storage_path)); // storage errors are transient

        if ($problem = PdfFileCheck::problem($temp)) {
            @unlink($temp);
            throw new PermanentQuestionPaperException($problem);
        }

        return $temp;
    }

    /** @return list<list<array{page: int|null, text: string}>> pages grouped into chunks */
    private function extract(object $job, string $token, string $path): array
    {
        $this->jobs->update((int) $job->id, $token, ['stage' => 'extracting_text']);

        $pages = [];
        $pageCount = null;
        try {
            $pdf = (new PdfParser())->parseFile($path);
            foreach ($pdf->getPages() as $i => $page) {
                $pages[] = ['page' => $i + 1, 'text' => trim((string) $page->getText())];
            }
            $pageCount = count($pages);
        } catch (Throwable $e) {
            // Unreadable structure: OCR may still cope, otherwise it is a corrupt file below.
            Log::info("Question paper job {$job->uuid}: PDF parser failed ({$e->getMessage()}), trying OCR.");
        }

        $chars = array_sum(array_map(fn ($p) => mb_strlen($p['text']), $pages));
        $usedOcr = false;
        if ($pageCount === null || $chars < max(50, 20 * $pageCount)) {
            // Scanned PDF: use the OCR the document library already has (Tesseract, else cloud vision).
            $result = app(TextExtractionManager::class)->extractText($path, 'application/pdf', 'question-paper.pdf', (int) $job->sub_institute_id);
            if (trim($result['text']) === '') {
                throw new PermanentQuestionPaperException(
                    $pageCount === null ? 'The PDF is corrupted and could not be read.' : 'No text could be read from the PDF, even with OCR. Upload a clearer copy.'
                );
            }
            $usedOcr = (bool) $result['used_ocr'];
            $split = preg_split('/\f/', $result['text']) ?: [$result['text']];
            $pages = [];
            foreach ($split as $i => $text) {
                if (trim($text) !== '') {
                    $pages[] = ['page' => count($split) > 1 ? $i + 1 : null, 'text' => trim($text)];
                }
            }
            $pageCount ??= count($pages);
        }

        $chunks = [];
        $current = [];
        $size = 0;
        foreach ($pages as $page) {
            if ($current !== [] && $size + mb_strlen($page['text']) > self::CHUNK_CHARS) {
                $chunks[] = $current;
                $current = [];
                $size = 0;
            }
            $current[] = $page;
            $size += mb_strlen($page['text']);
        }
        if ($current !== []) {
            $chunks[] = $current;
        }

        $this->jobs->update((int) $job->id, $token, [
            'total_pages' => $pageCount,
            'total_chunks' => count($chunks),
            'used_ocr' => $usedOcr,
        ]);

        return $chunks;
    }

    private function parse(object $job, string $token, array $chunks): void
    {
        $this->jobs->update((int) $job->id, $token, ['stage' => 'parsing_questions']);

        $fresh = $this->jobs->find((int) $job->id);
        $header = json_decode((string) $fresh->metadata_notes, true)['header'] ?? [];
        $start = (int) $fresh->parsed_chunks;
        $pagesDone = (int) $fresh->processed_pages;

        foreach ($chunks as $index => $pages) {
            if ($index < $start) {
                continue; // parsed by an earlier attempt
            }

            $parsed = $this->parser->parseChunk($pages, (int) $job->sub_institute_id);
            foreach ($parsed['header'] as $key => $value) {
                $header[$key] ??= $value;
            }
            $this->storeItems($job, $parsed['questions']);

            $pagesDone += count($pages);
            $this->jobs->update((int) $job->id, $token, [
                'parsed_chunks' => $index + 1,
                'processed_pages' => $pagesDone,
                'questions_extracted' => DB::table('question_paper_items')->where('job_id', $job->id)->count(),
                'metadata_notes' => json_encode(['header' => $header], JSON_UNESCAPED_UNICODE),
            ]);
        }

        // total_chunks == 0 (blank document) falls through to finalize, which reports "no questions".
    }

    private function storeItems(object $job, array $questions): void
    {
        $ordinal = (int) DB::table('question_paper_items')->where('job_id', $job->id)->max('ordinal');

        foreach ($questions as $raw) {
            $ordinal++;
            $result = $this->normaliser->normalise($raw);
            $now = now();

            if ($result['ok']) {
                $item = $result['item'];
                $row = [
                    'item_number' => $item['item_number'],
                    'source_page' => $item['source_page'],
                    'format_code' => $item['format_code'],
                    'content_hash' => $item['content_hash'],
                    'state' => 'parsed',
                    'reason' => null,
                    'payload' => json_encode($item, JSON_UNESCAPED_UNICODE),
                ];
            } else {
                // Kept (not dropped) so the owner sees every question that was left out and why.
                $row = [
                    'item_number' => mb_substr(trim((string) ($raw['item_number'] ?? '')), 0, 16) ?: null,
                    'source_page' => isset($raw['page']) && is_numeric($raw['page']) ? (int) $raw['page'] : null,
                    'format_code' => null,
                    'content_hash' => hash('sha256', 'skipped|' . $job->id . '|' . json_encode($raw)),
                    'state' => 'skipped',
                    'reason' => mb_substr($result['reason'], 0, 255),
                    'payload' => json_encode($raw, JSON_UNESCAPED_UNICODE),
                ];
            }

            // The unique (job_id, content_hash) makes re-parsing a chunk after a crash harmless.
            DB::table('question_paper_items')->insertOrIgnore($row + [
                'job_id' => $job->id,
                'sub_institute_id' => $job->sub_institute_id,
                'ordinal' => $ordinal,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function resolveAndSave(object $job, string $token): void
    {
        $id = (int) $job->id;
        $this->jobs->update($id, $token, ['stage' => 'resolving_metadata']);

        $fresh = $this->jobs->find($id);
        $header = json_decode((string) $fresh->metadata_notes, true)['header'] ?? [];
        $academic = $this->resolver->resolve($fresh, $header);
        $suggestions = $this->suggestSubject($fresh, $academic);

        $headingSubject = $header['subject'] ?? null;
        $headingAbsent = $headingSubject === null || trim($headingSubject) === '';
        $matchedCandidates = ($academic['standard_id'] !== null && !empty($headingSubject))
            ? $this->resolver->mappedSubjectsNamed((int) $fresh->sub_institute_id, (int) $academic['standard_id'], $headingSubject)
            : [];
        $headingAmbiguous = count($matchedCandidates) > 1;
        $headingGeneric = $this->isGenericHeading($headingSubject);

        $headingAbsentOrUnclear = $headingAbsent || $headingAmbiguous || $headingGeneric;

        // When heading is absent or unclear, assign mapped subject from question content secondary evidence if there is a clear match
        if ($academic['subject_id'] === null && $headingAbsentOrUnclear && !empty($suggestions['subject']['id']) && $academic['standard_id'] !== null) {
            $suggestedId = (int) $suggestions['subject']['id'];
            if (in_array($suggestedId, $this->resolver->mappedSubjectIds((int) $fresh->sub_institute_id, (int) $academic['standard_id']), true)) {
                $academic['subject_id'] = $suggestedId;
                $academic['notes'][] = "Subject resolved as \"{$suggestions['subject']['name']}\" using question content as secondary evidence.";
            }
        }

        $this->jobs->update($id, $token, [
            'standard_id' => $academic['standard_id'],
            'subject_id' => $academic['subject_id'],
            'chapter_id' => $academic['chapter_id'],
            'academic_year' => $academic['academic_year'],
            'metadata_notes' => json_encode(array_filter(['header' => $header, 'notes' => $academic['notes'], 'candidates' => $academic['candidates'], 'suggestions' => $suggestions]), JSON_UNESCAPED_UNICODE),
        ]);

        // Each question gets its own chapter from its own wording, now that the standard and subject are known. This reads the
        // items already stored, so a metadata-only retry never repeats the PDF extraction or the AI parsing.
        $fresh = $this->jobs->find($id);
        $unconfirmed = $this->matchChapters($fresh);
        if ($unconfirmed > 0) {
            $meta = json_decode((string) $fresh->metadata_notes, true) ?: [];
            $meta['notes'][] = "{$unconfirmed} question(s) have no confirmed chapter: they are saved inactive until a teacher confirms each chapter.";
            $this->jobs->update($id, $token, ['metadata_notes' => json_encode($meta, JSON_UNESCAPED_UNICODE)]);
            $fresh = $this->jobs->find($id);
        }
        $this->jobs->update($id, $token, ['stage' => 'saving']);

        $pending = DB::table('question_paper_items')->where('job_id', $id)->where('state', 'parsed')->orderBy('ordinal')->pluck('id');
        foreach ($pending as $n => $itemId) {
            // A failure here propagates: the job is never reported complete with questions unsaved.
            $this->persistence->save($fresh, (int) $itemId);

            if ($n % 10 === 9) {
                $this->jobs->update($id, $token, ['questions_saved' => $this->count($id, ['saved', 'needs_review'])]);
            }
        }
    }

    /**
     * When the heading settled no subject, say which of the standard's subjects the QUESTIONS point to. A suggestion only:
     * it is stored for the teacher to confirm and is never assigned.
     *
     * @param  array<string, mixed>  $academic
     * @return array<string, mixed>
     */
    private function suggestSubject(object $job, array $academic): array
    {
        if ($academic['subject_id'] !== null || $academic['standard_id'] === null) {
            return [];
        }
        $ids = $this->resolver->mappedSubjectIds((int) $job->sub_institute_id, (int) $academic['standard_id']);
        $items = [];
        foreach (DB::table('question_paper_items')->where('job_id', $job->id)->whereIn('state', ['parsed', 'unresolved', 'needs_review'])->pluck('payload') as $payload) {
            $decoded = json_decode((string) $payload, true);
            if (is_array($decoded)) {
                $items[] = $decoded;
            }
        }
        if ($ids === [] || $items === []) {
            return [];
        }
        $best = $this->matcher->suggestSubject((int) $job->sub_institute_id, (int) $academic['standard_id'], $ids, $items);
        if ($best === null) {
            return [];
        }
        $name = collect($this->resolver->mappedSubjects((int) $job->sub_institute_id, (int) $academic['standard_id']))->firstWhere('id', $best['subject_id'])['name'] ?? null;

        return ['subject' => [
            'id' => $best['subject_id'], 'name' => $name, 'share' => $best['share'],
            'reason' => round($best['share'] * 100) . "% of the questions that match any subject fit \"{$name}\" best.",
        ]];
    }

    private function isGenericHeading(?string $heading): bool
    {
        if ($heading === null || trim($heading) === '') {
            return true;
        }
        $folded = mb_strtolower(trim($heading));
        $genericPatterns = [
            '~^(?:annual|term|terminal|semester|half yearly|unit|mid[- ]?term|pre[- ]?board|mock|final|quarterly|periodic)?\s*(?:exam|examination|test|paper|assessment|evaluation)?$~u',
            '~^(?:class|std|standard|grade)\s*\d{1,2}$~u',
        ];
        foreach ($genericPatterns as $pattern) {
            if (preg_match($pattern, $folded)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rank every not-yet-active question against the chapters of the resolved standard and subject and record the result on
     * the item. A chapter is ASSIGNED only for a high-confidence match and only when nobody has chosen one: a paper-wide
     * chapter (chosen or stated) and a teacher's per-question choice both always win. Questions already saved inactive are
     * updated in place (never re-inserted); their status is left to the review and activation rules.
     *
     * @return int how many of these questions end with no chapter (they need a teacher)
     */
    private function matchChapters(object $job): int
    {
        if ($job->standard_id === null || $job->subject_id === null) {
            return 0;
        }

        $rows = DB::table('question_paper_items')->where('job_id', $job->id)->whereIn('state', ['parsed', 'needs_review'])->orderBy('ordinal')->get(['id', 'payload', 'question_id']);
        $payloads = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row->payload, true);
            if (is_array($decoded)) {
                $payloads[$row->id] = $decoded;
            }
        }
        if ($payloads === []) {
            return 0;
        }

        $matches = $this->matcher->suggest((int) $job->sub_institute_id, (int) $job->standard_id, (int) $job->subject_id, $payloads);
        $without = 0;

        foreach ($rows as $row) {
            if (!isset($payloads[$row->id])) {
                continue;
            }
            $payload = $payloads[$row->id];
            $match = $matches[$row->id];
            // A chapter with no recorded source was chosen by a person (older retries wrote none); only 'auto' is ours to replace.
            $chosenByPerson = !empty($payload['chapter_id']) && ($payload['chapter_source'] ?? 'teacher') !== 'auto';

            $explicitId = null;
            if (!empty($payload['chapter_text'])) {
                $explicitId = $this->resolver->resolveChapterTitle($job, (string) $payload['chapter_text']);
            }

            if (!$chosenByPerson && $job->chapter_id === null) {
                if ($explicitId !== null) {
                    $payload['chapter_id'] = $explicitId;
                    $payload['chapter_source'] = 'explicit';
                    $match['chapter_id'] = $explicitId;
                    $match['suggested_id'] = $explicitId;
                    $match['suggested_name'] = $this->matcher->chapterNames((int) $job->sub_institute_id, (int) $job->standard_id, (int) $job->subject_id)[$explicitId] ?? (string) $payload['chapter_text'];
                    $match['auto'] = true;
                    $match['level'] = 'high';
                    $match['confidence'] = 99;
                    $match['reason'] = "Explicit chapter match: \"{$payload['chapter_text']}\".";
                } elseif ($match['auto']) {
                    $payload['chapter_id'] = (int) $match['chapter_id'];
                    $payload['chapter_source'] = 'auto';
                } else {
                    unset($payload['chapter_id'], $payload['chapter_source']); // a stale automatic pick no longer holds
                }
            }
            $payload['chapter_match'] = array_intersect_key($match, array_flip(['suggested_id', 'suggested_name', 'confidence', 'level', 'score', 'margin', 'reason', 'auto', 'alternatives']));

            DB::table('question_paper_items')->where('id', $row->id)->update(['payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);

            $effective = !empty($payload['chapter_id']) ? (int) $payload['chapter_id'] : ($job->chapter_id !== null ? (int) $job->chapter_id : null);
            if ($effective === null) {
                $without++;
            }

            // Already in the bank (inactive): align its academic columns in place, only for this job's own question.
            if ($row->question_id !== null) {
                $columns = ['standard_id' => $job->standard_id, 'subject_id' => $job->subject_id];
                if ($effective !== null) {
                    $columns['chapter_id'] = $effective;
                }
                DB::table('lms_question_master')->where('id', $row->question_id)->where('sub_institute_id', $job->sub_institute_id)
                    ->where('status', 0)->where('answer', 'like', '%"job":"' . $job->uuid . '"%')->update($columns);
            }
        }

        return $without;
    }

    private function finalize(object $job, string $token): void
    {
        $id = (int) $job->id;
        $this->jobs->update($id, $token, ['stage' => 'finalizing']);

        $saved = $this->count($id, ['saved', 'needs_review']);
        $review = $this->count($id, ['needs_review', 'unresolved']);
        $skipped = $this->count($id, ['skipped', 'duplicate']);
        $extracted = DB::table('question_paper_items')->where('job_id', $id)->count();

        $counts = [
            'questions_extracted' => $extracted,
            'questions_saved' => $saved,
            'questions_needing_review' => $review,
            'questions_skipped' => $skipped,
        ];

        $fresh = $this->jobs->find($id);
        $stillParsed = DB::table('question_paper_items')->where('job_id', $id)->where('state', 'parsed')->count();
        if ($stillParsed > 0) {
            throw new \RuntimeException("{$stillParsed} question(s) were not written to the question bank.");
        }

        if ($extracted === 0) {
            $this->jobs->update($id, $token, $counts);
            throw new PermanentQuestionPaperException('No questions were found in this document. Check that it is a question paper.', true);
        }

        $status = ($review > 0 || $skipped > 0) ? 'completed_with_warnings' : 'completed';
        $this->jobs->update($id, $token, $counts + ['status' => $status, 'stage' => 'finalizing', 'finished_at' => now(), 'last_error' => null]);
        $this->notify($this->jobs->find($id), $status);
    }

    private function fail(int $id, string $token, string $message, bool $manualRetry): void
    {
        $job = $this->jobs->find($id);
        $this->jobs->recordError($id, $message, false);
        $this->markFailed($job, $message, $manualRetry);
    }

    private function markFailed(object $job, string $message, bool $manualRetry): void
    {
        $counts = [
            'questions_extracted' => DB::table('question_paper_items')->where('job_id', $job->id)->count(),
            'questions_saved' => $this->count((int) $job->id, ['saved', 'needs_review']),
            'questions_needing_review' => $this->count((int) $job->id, ['needs_review', 'unresolved']),
            'questions_skipped' => $this->count((int) $job->id, ['skipped', 'duplicate']),
        ];

        DB::table('question_paper_jobs')->where('id', $job->id)->update($counts + [
            'status' => 'failed',
            'retryable' => $manualRetry,
            'last_error' => mb_substr($message, 0, 500),
            'finished_at' => now(),
            'lease_token' => null,
            'lease_expires_at' => null,
            'updated_at' => now(),
        ]);

        $this->notify($this->jobs->find((int) $job->id), 'failed');
    }

    /** @param list<string> $states */
    private function count(int $jobId, array $states): int
    {
        return DB::table('question_paper_items')->where('job_id', $jobId)->whereIn('state', $states)->count();
    }

    private function notify(object $job, string $outcome): void
    {
        $document = DB::table('document_master')->where('id', $job->document_id)->first(['title', 'original_file_name']);
        $name = $document->original_file_name ?? $document->title ?? 'Question paper';
        $link = '/documents_new?tab=question-papers&job=' . $job->uuid;

        [$title, $severity, $body] = match ($outcome) {
            'completed' => ['Question paper processing completed', 'success', "{$name} - {$job->questions_saved} questions saved."],
            'completed_with_warnings' => [
                'Question paper processed with warnings', 'warning',
                "{$name} - {$job->questions_saved} questions saved, {$job->questions_needing_review} need review, {$job->questions_skipped} skipped.",
            ],
            default => ['Question paper processing failed', 'error', "{$name} - " . ($job->last_error ?: 'processing failed') . " ({$job->questions_saved} questions saved before it stopped)."],
        };

        // One notification per outcome per run: a retried job that lands on the same outcome again
        // within the same run cannot raise a second alert.
        $this->notifications->notify(
            (int) $job->sub_institute_id,
            (int) $job->user_id,
            'question_paper.' . $outcome,
            $severity,
            $title,
            $body,
            $link,
            'question_paper_job',
            $job->uuid,
            "qp:{$job->id}:{$outcome}:{$job->manual_retries}"
        );
    }
}
