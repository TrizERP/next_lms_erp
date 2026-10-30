<?php

namespace Tests\Feature\QuestionPapers;

use App\Http\Controllers\api\Platform\QuestionPaperController;
use App\Services\QuestionPapers\QuestionPaperJobService;
use App\Services\QuestionPapers\QuestionPaperProcessor;
use App\Services\QuestionPapers\QuestionPaperRemovalService;
use App\Services\QuestionPapers\RemovalRefused;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Removing a question paper: exactly that paper's records go, nothing else does.
 */
class QuestionPaperRemovalTest extends QuestionPaperTestCase
{
    protected function createSurroundingTables(): void
    {
        parent::createSurroundingTables();
        (require base_path('database/migrations/2026_08_20_000001_create_jobs_table.php'))->up();
    }

    private function removal(): QuestionPaperRemovalService
    {
        return app(QuestionPaperRemovalService::class);
    }

    private function queueMessage(string $queue, string $uuid, ?int $reservedAt = null): int
    {
        return (int) DB::table('jobs')->insertGetId([
            'queue' => $queue, 'payload' => json_encode(['displayName' => 'App\\Jobs\\QuestionPaperExtractionJob', 'data' => ['command' => 'O:...:"uuid";s:36:"' . $uuid . '";']]),
            'attempts' => 0, 'reserved_at' => $reservedAt, 'available_at' => time(), 'created_at' => time(),
        ]);
    }

    private function noticeFor(object $job, int $user = self::OWNER): void
    {
        app(\App\Services\QuestionPapers\UserNotificationService::class)->notify(
            self::TENANT, $user, 'question_paper.completed', 'success', 'Done', 'body', '/x', 'question_paper_job', $job->uuid, "qp:{$job->id}:completed:0"
        );
    }

    private function addItems(object $job, int $count, bool $saved = false): void
    {
        for ($i = 1; $i <= $count; $i++) {
            DB::table('question_paper_items')->insert([
                'job_id' => $job->id, 'sub_institute_id' => self::TENANT, 'ordinal' => $i, 'content_hash' => hash('sha256', "{$job->id}-{$i}"),
                'state' => $saved ? 'saved' : 'parsed', 'payload' => '{}', 'question_id' => $saved ? 9000 + $i : null,
            ]);
        }
    }

    /** A second, unrelated paper with all its records: it must come through every removal untouched. */
    private function bystander(): array
    {
        $other = $this->makeJob(user: self::COLLEAGUE);
        $this->addItems($other, 2);
        $this->noticeFor($other, self::COLLEAGUE);
        $message = $this->queueMessage('question-papers', $other->uuid);
        DB::table('lms_question_master')->insert(['id' => 5000, 'question_title' => 'Existing bank question', 'sub_institute_id' => self::TENANT, 'status' => 1, 'answer' => '{}']);

        return [$other, $message];
    }

    private function snapshotOfBystander(object $other, int $message): array
    {
        return [
            DB::table('question_paper_jobs')->where('id', $other->id)->count(),
            DB::table('question_paper_items')->where('job_id', $other->id)->count(),
            DB::table('user_notifications')->where('source_id', $other->uuid)->count(),
            DB::table('jobs')->where('id', $message)->count(),
            DB::table('document_master')->where('id', $other->document_id)->whereNull('deleted_at')->count(),
            DB::table('lms_question_master')->count(),
        ];
    }

    public function test_a_queued_paper_is_removed_with_exactly_its_own_records_and_the_document_goes_to_the_trash(): void
    {
        $job = $this->makeJob();
        $this->addItems($job, 7);
        $this->noticeFor($job);
        $mine = [$this->queueMessage('question-papers', $job->uuid), $this->queueMessage('default', $job->uuid)];
        $unrelated = [$this->queueMessage('default', 'ffffffff-ffff-4fff-8fff-ffffffffffff'), $this->queueMessage('question-papers', '00000000-0000-4000-8000-000000000000')];
        [$other, $otherMessage] = $this->bystander();
        $before = $this->snapshotOfBystander($other, $otherMessage);

        $summary = $this->removal()->remove($job, self::OWNER);

        $this->assertSame([7, 2, 1, true, 0], [$summary['items_deleted'], $summary['queue_messages_deleted'], $summary['notifications_deleted'], $summary['document_trashed'], $summary['queue_messages_left_reserved']]);
        $this->assertSame(0, DB::table('question_paper_jobs')->where('id', $job->id)->count());
        $this->assertSame(0, DB::table('question_paper_items')->where('job_id', $job->id)->count());
        $this->assertSame(0, DB::table('jobs')->whereIn('id', $mine)->count(), 'both of this paper\'s queue messages are cancelled');
        $this->assertSame(2, DB::table('jobs')->whereIn('id', $unrelated)->count(), 'other papers\' and other features\' messages stay');
        $this->assertSame(0, DB::table('user_notifications')->where('source_id', $job->uuid)->count());

        // The document is in the Trash (restorable), not destroyed, and the removal is audited.
        $doc = DB::table('document_master')->where('id', $job->document_id)->first();
        $this->assertNotNull($doc->deleted_at);
        $this->assertSame(1, DB::table('document_history')->where('document_id', $job->document_id)->where('action', 'delete')->count());

        $this->assertSame($before, $this->snapshotOfBystander($other, $otherMessage), 'no other paper, document, notice, queue message or question changed');
    }

    public function test_a_message_a_worker_has_already_reserved_is_left_alone_and_the_worker_then_does_nothing(): void
    {
        $job = $this->makeJob();
        $reserved = $this->queueMessage('question-papers', $job->uuid, time());

        $summary = $this->removal()->remove($job, self::OWNER);

        $this->assertSame([0, 1], [$summary['queue_messages_deleted'], $summary['queue_messages_left_reserved']]);
        $this->assertSame(1, DB::table('jobs')->where('id', $reserved)->count(), 'a reserved message belongs to its worker');

        // That worker now runs the job: the row is gone, so it returns without touching anything.
        app(QuestionPaperProcessor::class)->run($job->uuid);
        $this->assertSame(0, DB::table('lms_question_master')->count());
        $this->assertSame(0, DB::table('question_paper_jobs')->count());
    }

    public function test_a_paper_a_live_worker_holds_is_refused_and_nothing_is_deleted(): void
    {
        $job = $this->makeJob();
        $this->addItems($job, 3);
        $message = $this->queueMessage('question-papers', $job->uuid);
        app(QuestionPaperJobService::class)->acquireLease((int) $job->id); // a worker is on it

        try {
            $this->removal()->remove(DB::table('question_paper_jobs')->where('id', $job->id)->first(), self::OWNER);
            $this->fail('a running paper must not be removed');
        } catch (RemovalRefused $e) {
            $this->assertStringContainsString('worker is processing', $e->getMessage());
        }

        $this->assertSame(1, DB::table('question_paper_jobs')->where('id', $job->id)->count());
        $this->assertSame(3, DB::table('question_paper_items')->where('job_id', $job->id)->count());
        $this->assertSame(1, DB::table('jobs')->where('id', $message)->count());
        $this->assertNull(DB::table('document_master')->where('id', $job->document_id)->value('deleted_at'));
    }

    public function test_a_paper_whose_worker_died_can_be_removed_once_its_lease_has_expired(): void
    {
        $job = $this->makeJob();
        app(QuestionPaperJobService::class)->acquireLease((int) $job->id);
        DB::table('question_paper_jobs')->where('id', $job->id)->update(['lease_expires_at' => now()->subMinute()]);

        $summary = $this->removal()->remove(DB::table('question_paper_jobs')->where('id', $job->id)->first(), self::OWNER);

        $this->assertTrue($summary['document_trashed']);
        $this->assertSame(0, DB::table('question_paper_jobs')->count());
    }

    public function test_a_finished_paper_is_removed_without_any_worker_involvement(): void
    {
        $job = $this->makeJob();
        DB::table('question_paper_jobs')->where('id', $job->id)->update(['status' => 'failed', 'finished_at' => now()]);

        $this->removal()->remove(DB::table('question_paper_jobs')->where('id', $job->id)->first(), self::OWNER);

        $this->assertSame(0, DB::table('question_paper_jobs')->count());
    }

    public function test_a_paper_with_questions_already_in_the_bank_is_refused_and_the_bank_is_untouched(): void
    {
        $job = $this->makeJob();
        $this->addItems($job, 2, saved: true);
        DB::table('lms_question_master')->insert(['id' => 9001, 'question_title' => 'From the paper', 'sub_institute_id' => self::TENANT, 'status' => 0, 'answer' => '{}']);

        try {
            $this->removal()->remove($job, self::OWNER);
            $this->fail('a paper with saved bank questions must not be removed');
        } catch (RemovalRefused $e) {
            $this->assertStringContainsString('already saved in the question bank', $e->getMessage());
        }

        $this->assertSame(1, DB::table('question_paper_jobs')->count());
        $this->assertSame(2, DB::table('question_paper_items')->count());
        $this->assertSame(1, DB::table('lms_question_master')->count());
    }

    public function test_the_endpoint_removes_for_the_owner_and_an_admin_only_and_the_list_forgets_it(): void
    {
        $job = $this->makeJob();
        $controller = app(QuestionPaperController::class);

        // Another staff member of the same school, and an admin of another school, cannot even see it.
        foreach ([[self::TENANT, self::COLLEAGUE, 0], [self::OTHER_TENANT, 900, 1]] as [$tenant, $user, $admin]) {
            $this->assertSame(404, $controller->destroy($this->authed('DELETE', [], $tenant, $user, $admin), $job->uuid)->getStatusCode());
        }
        $this->assertSame(1, DB::table('question_paper_jobs')->count());

        $anonymous = $this->authed('DELETE');
        $anonymous->attributes->set('lms_auth', null);
        $this->assertSame(401, $controller->destroy($anonymous, $job->uuid)->getStatusCode());

        $response = $controller->destroy($this->authed('DELETE'), $job->uuid);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['data']['document_trashed']);

        $list = $controller->index($this->authed('GET'))->getData(true)['data'];
        $this->assertSame(0, $list['total']);
        $this->assertSame(404, $controller->show($this->authed('GET'), $job->uuid)->getStatusCode(), 'and it stays gone on a refresh');
    }

    public function test_the_endpoint_reports_a_refusal_as_409_and_the_route_is_guarded(): void
    {
        $job = $this->makeJob();
        app(QuestionPaperJobService::class)->acquireLease((int) $job->id);

        $this->assertSame(409, app(QuestionPaperController::class)->destroy($this->authed('DELETE'), $job->uuid)->getStatusCode());

        $route = Route::getRoutes()->match(Request::create('api/platform/question-papers/11111111-1111-4111-8111-111111111111', 'DELETE'));
        $this->assertContains('lms.auth', $route->gatherMiddleware());
        $this->assertContains('lms.staff', $route->gatherMiddleware());
    }

    public function test_a_failed_paper_with_draft_questions_can_be_removed_and_cleans_up_questions(): void
    {
        $job = $this->makeJob();
        DB::table('question_paper_jobs')->where('id', $job->id)->update(['status' => 'failed', 'finished_at' => now()]);
        $this->addItems($job, 2, saved: true);
        DB::table('lms_question_master')->insert([
            ['id' => 9001, 'question_title' => 'Q1', 'sub_institute_id' => self::TENANT, 'status' => 0, 'answer' => '{"job":"' . $job->uuid . '"}'],
            ['id' => 9002, 'question_title' => 'Q2', 'sub_institute_id' => self::TENANT, 'status' => 0, 'answer' => '{"job":"' . $job->uuid . '"}'],
        ]);

        $summary = $this->removal()->remove(DB::table('question_paper_jobs')->where('id', $job->id)->first(), self::OWNER);

        $this->assertSame(2, $summary['items_deleted']);
        $this->assertSame(0, DB::table('question_paper_jobs')->where('id', $job->id)->count());
        $this->assertSame(0, DB::table('question_paper_items')->where('job_id', $job->id)->count());
        $this->assertSame(0, DB::table('lms_question_master')->whereIn('id', [9001, 9002])->count(), 'draft questions are cleaned up');
    }
}
