<?php

namespace App\Services\QuestionPapers;

use App\Services\QuestionGeneration\QuestionFormatRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Writes one parsed question into the existing question bank, exactly once.
 *
 * Tables written: lms_question_master (the question + its JSON answer envelope), answer_master (one
 * row per option, the rows the quiz runtime reads) and question_paper_items (provenance back to the
 * source page and number). Nothing else, and existing rows are never updated or deleted.
 *
 * Idempotency has three layers: the item row is locked and skipped once it holds a question_id;
 * each extracted occurrence has a stable (job_id, source_key); and a normalized question title
 * already in the same resolved chapter is recorded as a duplicate instead of inserted.
 * Each write is one transaction, so a crash leaves either a complete question or none.
 */
class QuestionPaperPersistence
{
    /** @var array<string, bool> */
    private array $columns = [];

    public function __construct(private ?QuestionFormatRegistry $registry = null)
    {
        $this->registry ??= new QuestionFormatRegistry();
    }

    /**
     * @return string the item's final state: saved | needs_review | unresolved | duplicate | already
     */
    public function save(object $job, int $itemId): string
    {
        return DB::transaction(function () use ($job, $itemId) {
            $row = DB::table('question_paper_items')->where('id', $itemId)->lockForUpdate()->first();
            if (!$row) {
                return 'already';
            }
            if ($row->question_id !== null) {
                return 'already';
            }

            // Without a standard and subject the question has no home in the bank: hold it, do not guess.
            if ($job->standard_id === null || $job->subject_id === null) {
                $this->setState($itemId, 'unresolved', 'Standard or subject could not be determined.');

                return 'unresolved';
            }

            $item = json_decode($row->payload, true);
            if (!is_array($item)) {
                $this->setState($itemId, 'skipped', 'Stored question data could not be read.');

                return 'skipped';
            }

            $explicitChapterId = !empty($item['chapter_text'])
                ? app(QuestionPaperMetadataResolver::class)->resolveChapterTitle($job, (string) $item['chapter_text'])
                : null;
            $chapterId = $explicitChapterId
                ?? (!empty($item['chapter_id'])
                    ? app(QuestionPaperMetadataResolver::class)->validChapter((int) $job->sub_institute_id, (int) $job->standard_id, (int) $job->subject_id, (int) $item['chapter_id'])
                    : app(QuestionPaperMetadataResolver::class)->validChapter(
                        (int) $job->sub_institute_id,
                        (int) $job->standard_id,
                        (int) $job->subject_id,
                        $job->chapter_id !== null ? (int) $job->chapter_id : null
                    ));

            if ($chapterId !== null) {
                $chapter = DB::table('chapter_master')->where('id', $chapterId)
                    ->where('sub_institute_id', $job->sub_institute_id)
                    ->where('standard_id', $job->standard_id)
                    ->where('subject_id', $job->subject_id)
                    ->lockForUpdate()
                    ->first(['id']);

                if ($chapter && $this->existsInChapter($job, $chapterId, (string) $item['question_text'])) {
                    $this->setState($itemId, 'duplicate', 'A matching question already exists in this chapter.');

                    return 'duplicate';
                }

                if (!$chapter) {
                    $chapterId = null;
                }
            }

            $reasons = $item['review'] ?? [];
            if ($chapterId === null) {
                $reasons[] = 'Chapter is not resolved.';
            }
            $active = $reasons === [];

            $questionId = DB::table('lms_question_master')->insertGetId($this->masterRow($job, $item, $row->content_hash, $active, $reasons, $chapterId));

            $answers = $this->answerRows($job, $questionId, $item);
            if ($answers !== []) {
                DB::table('answer_master')->insert($answers);
            }

            DB::table('question_paper_items')->where('id', $itemId)->update([
                'question_id' => $questionId,
                'state' => $active ? 'saved' : 'needs_review',
                'reason' => $active ? null : mb_substr(implode(' ', $reasons), 0, 255),
                'updated_at' => now(),
            ]);

            return $active ? 'saved' : 'needs_review';
        });
    }

    private function setState(int $itemId, string $state, string $reason): void
    {
        DB::table('question_paper_items')->where('id', $itemId)->update(['state' => $state, 'reason' => $reason, 'updated_at' => now()]);
    }

    private function existsInChapter(object $job, int $chapterId, string $questionText): bool
    {
        $normaliser = app(QuestionPaperItemNormaliser::class);
        $normalized = $normaliser->normaliseQuestionText($questionText);

        $questions = DB::table('lms_question_master')
            ->where('sub_institute_id', $job->sub_institute_id)
            ->where('chapter_id', $chapterId)
            ->whereNotNull('question_title')
            ->when($this->hasColumn('lms_question_master', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->orderBy('id')
            ->cursor(['question_title']);

        foreach ($questions as $question) {
            if ($normaliser->normaliseQuestionText((string) $question->question_title) === $normalized) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function masterRow(object $job, array $item, string $hash, bool $active, array $reasons, ?int $chapterId): array
    {
        $format = $this->registry->get($item['format_code']);
        $document = DB::table('document_master')->where('id', $job->document_id)->first(['title']);

        $envelope = [
            'v' => 1,
            'item_form' => $this->registry->get($item['format_code'])->persistedFormatCode() ?? $item['format_code'],
            'item_number' => $item['item_number'],
            'options' => $item['options'],
            'correct_option' => $item['correct_option'],
            'model_answer' => $item['model_answer'],
            'pairs' => $item['pairs'],
            'content_hash' => $hash,
            // Provenance: where this question came from, so it can be traced and withdrawn.
            'source' => [
                'type' => 'question_paper_upload',
                'job' => $job->uuid,
                'document_id' => (int) $job->document_id,
                'page' => $item['source_page'],
            ],
            'review_reasons' => $reasons,
            'times_served' => 0,
            'times_correct' => 0,
        ];

        $row = [
            'question_type_id' => $this->registry->questionTypeIdFor($format),
            'standard_id' => $job->standard_id,
            'subject_id' => $job->subject_id,
            'chapter_id' => $chapterId,
            'sub_institute_id' => $job->sub_institute_id,
            'question_title' => $item['question_text'],
            'description' => mb_substr('From question paper: ' . ($document->title ?? 'uploaded document'), 0, 250),
            'points' => $item['marks'],
            'multiple_answer' => 0,
            // Inactive until a person has resolved what the paper left open.
            'status' => $active ? 1 : 0,
            'created_by' => $job->user_id,
            'created_on' => now(),
            'hint_text' => $item['explanation'],
            'answer' => json_encode($envelope, JSON_UNESCAPED_UNICODE),
        ];

        if ($this->hasColumn('lms_question_master', 'question_format_code')) {
            // The catalogue code the bank and PAL read (e.g. case_study is stored as its catalogue form), as the generator does.
            $row['question_format_code'] = $format->persistedFormatCode() ?? $item['format_code'];
        }
        // g_content_hash is a generated column over answer.content_hash; it is never written directly.
        return $row;
    }

    /**
     * answer_master rows exist only when the paper gave the key: a question with options but no known
     * correct one would otherwise be served and graded wrong for every learner.
     *
     * @return list<array<string, mixed>>
     */
    private function answerRows(object $job, int $questionId, array $item): array
    {
        if (empty($item['options']) || $item['correct_option'] === null) {
            return [];
        }

        $rows = [];
        foreach ($item['options'] as $option) {
            $rows[] = [
                'question_id' => $questionId,
                'answer' => mb_substr($option['text'], 0, 250),
                'feedback' => null,
                'correct_answer' => $option['is_correct'] ? 1 : 0,
                'sub_institute_id' => $job->sub_institute_id,
                'created_by' => $job->user_id,
                'created_on' => now(),
            ];
        }

        return $rows;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->columns["{$table}.{$column}"] ??= Schema::hasColumn($table, $column);
    }
}
