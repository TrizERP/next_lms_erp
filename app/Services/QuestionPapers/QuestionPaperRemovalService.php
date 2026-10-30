<?php

namespace App\Services\QuestionPapers;

use App\Models\Documents\DocumentMaster;
use App\Services\Documents\DocumentAuditService;
use Illuminate\Support\Facades\DB;

/**
 * Removes ONE Question Paper: its job, its extracted items, its not-yet-picked-up queue messages and its bell notice, and
 * moves its uploaded document to the Trash (the library's own, restorable deletion) instead of destroying the file.
 *
 * What it will NOT do, by design:
 *  - touch a question already saved to the question bank (the paper is refused instead: removing it would orphan them);
 *  - remove a paper a worker is running (it must claim the job first, which only succeeds when no live lease exists);
 *  - touch any other paper, document, queue message of another job, or any table row it was not asked about;
 *  - delete audit history (document_history keeps its rows).
 */
class QuestionPaperRemovalService
{
    public function __construct(private QuestionPaperJobService $jobs)
    {
    }

    /**
     * @return array{job: string, items_deleted: int, queue_messages_deleted: int, queue_messages_left_reserved: int, notifications_deleted: int, document_trashed: bool}
     *
     * @throws RemovalRefused when the paper cannot be removed safely right now
     */
    public function remove(object $job, int $actorUserId): array
    {
        $savedQuestions = DB::table('question_paper_items')->where('job_id', $job->id)->whereNotNull('question_id')->count();
        if ($savedQuestions > 0 && $job->status !== 'failed') {
            throw new RemovalRefused("{$savedQuestions} question(s) from this paper are already saved in the question bank. Removing the paper would leave them without their review screen, so it is not allowed.");
        }

        // A finished paper has no worker. An unfinished one must be CLAIMED first: the claim succeeds only when no live
        // worker holds it, so a running extraction is never deleted from under itself.
        $claimed = null;
        if (!in_array($job->status, QuestionPaperJobService::TERMINAL, true)) {
            $claimed = $this->jobs->acquireLease((int) $job->id);
            if ($claimed === null) {
                throw new RemovalRefused('A worker is processing this paper right now. Try again when it has finished or stopped.');
            }
        }

        return DB::transaction(function () use ($job, $actorUserId) {
            if ($job->status === 'failed') {
                $questionIds = DB::table('question_paper_items')->where('job_id', $job->id)->whereNotNull('question_id')->pluck('question_id');
                if ($questionIds->isNotEmpty()) {
                    DB::table('lms_question_master')
                        ->whereIn('id', $questionIds)
                        ->where('sub_institute_id', $job->sub_institute_id)
                        ->delete();
                }
            }

            $items = DB::table('question_paper_items')->where('job_id', $job->id)->delete();
            DB::table('question_paper_jobs')->where('id', $job->id)->delete();

            // Only messages no worker has reserved. One that was reserved in the meantime is left to its worker, which finds
            // no job and does nothing (QuestionPaperProcessor::run returns when the job row is gone).
            $messages = DB::table('jobs')->whereNull('reserved_at')->where('payload', 'like', '%' . $job->uuid . '%')->delete();
            $reserved = DB::table('jobs')->whereNotNull('reserved_at')->where('payload', 'like', '%' . $job->uuid . '%')->count();

            $notices = DB::table('user_notifications')
                ->where('source_type', 'question_paper_job')->where('source_id', $job->uuid)
                ->where('sub_institute_id', $job->sub_institute_id)->delete();

            $trashed = false;
            $document = DocumentMaster::where('id', $job->document_id)->where('sub_institute_id', $job->sub_institute_id)->first();
            if ($document) {
                DocumentAuditService::log($document, 'delete', $actorUserId, ['reason' => 'question_paper_removed', 'job' => $job->uuid]);
                $document->delete(); // soft delete: the Trash, restorable until the retention window passes
                $trashed = true;
            }

            return [
                'job' => $job->uuid,
                'items_deleted' => $items,
                'queue_messages_deleted' => $messages,
                'queue_messages_left_reserved' => $reserved,
                'notifications_deleted' => $notices,
                'document_trashed' => $trashed,
            ];
        });
    }
}
