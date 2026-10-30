<?php

namespace Tests\Feature\QuestionPapers;

use App\Http\Controllers\api\Platform\QuestionPaperController;
use App\Http\Controllers\api\Platform\UserNotificationController;
use App\Jobs\QuestionPaperExtractionJob;
use App\Services\QuestionPapers\QuestionPaperJobService;
use App\Services\QuestionPapers\UserNotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

class QuestionPaperApiTest extends QuestionPaperTestCase
{
    private function controller(): QuestionPaperController
    {
        return app(QuestionPaperController::class);
    }

    private function pdfUpload(string $name = 'Science paper.pdf', ?string $bytes = null): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'up') . '.pdf';
        file_put_contents($path, $bytes ?? TinyPdf::make(['Class 9 Science question paper with enough text to be real.']));

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    private function upload(array $extra = [], ?UploadedFile $file = null, int $tenant = self::TENANT, int $user = self::OWNER)
    {
        $request = $this->authed('POST', $extra, $tenant, $user);
        $request->files->set('file', $file ?? $this->pdfUpload());

        return $this->controller()->store($request);
    }

    /* ---------- routes ---------- */

    public function test_routes_sit_behind_token_auth_and_the_staff_gate(): void
    {
        foreach ([['POST', 'api/platform/question-papers'], ['GET', 'api/platform/question-papers'], ['GET', 'api/platform/question-papers/{uuid}'], ['POST', 'api/platform/question-papers/{uuid}/retry']] as [$verb, $uri]) {
            $route = Route::getRoutes()->match(Request::create(str_replace('{uuid}', '11111111-1111-4111-8111-111111111111', $uri), $verb));
            $this->assertContains('lms.auth', $route->gatherMiddleware(), "$verb $uri");
            $this->assertContains('lms.staff', $route->gatherMiddleware(), "$verb $uri");
        }
        foreach (['api/platform/notifications/user/read-all', 'api/platform/notifications/user/5/read'] as $uri) {
            $route = Route::getRoutes()->match(Request::create($uri, 'POST'));
            $this->assertContains('lms.auth', $route->gatherMiddleware(), $uri);
        }
    }

    public function test_requests_without_a_verified_identity_get_401_and_no_fallback_user_is_used(): void
    {
        $anonymous = Request::create('/x', 'GET', [], [], [], ['HTTP_X_USER_ID' => '1', 'HTTP_X_SUB_INSTITUTE_ID' => '1']);
        $anonymous->attributes->set('lms_auth', null);

        $this->assertSame(401, $this->controller()->index($anonymous)->getStatusCode());
        $this->assertSame(401, $this->controller()->store($anonymous)->getStatusCode());
        $this->assertSame(401, $this->controller()->show($anonymous, '11111111-1111-4111-8111-111111111111')->getStatusCode());
        $this->assertSame(401, app(UserNotificationController::class)->markAllRead($anonymous)->getStatusCode());
    }

    /* ---------- upload ---------- */

    public function test_a_valid_upload_returns_at_once_with_a_stored_job_and_a_queued_background_run(): void
    {
        Queue::fake();

        $response = $this->upload(['academic_year' => '2026-27']);

        $this->assertSame(201, $response->getStatusCode());
        $data = $response->getData(true)['data'];
        $this->assertSame('queued', $data['status']);
        $this->assertArrayNotHasKey('storage_path', $data);
        Queue::assertPushed(QuestionPaperExtractionJob::class, fn ($job) => $job->jobUuid === $data['id']);

        $job = DB::table('question_paper_jobs')->where('uuid', $data['id'])->first();
        $this->assertSame([self::TENANT, self::OWNER], [(int) $job->sub_institute_id, (int) $job->user_id]);
        $doc = DB::table('document_master')->where('id', $job->document_id)->first();
        $this->assertSame('question_paper', $doc->declared_type);
        $this->assertSame('Science paper.pdf', $doc->original_file_name);
        $this->assertSame(self::OWNER, (int) $doc->owner_id);
        $this->assertNotNull($job->last_dispatched_at);
        $this->assertSame(0, DB::table('lms_question_master')->count(), 'nothing is extracted inside the request');
    }

    public function test_upload_rejects_wrong_extension_fake_pdf_truncated_pdf_and_foreign_academic_ids(): void
    {
        Queue::fake();

        $wrongExt = $this->upload([], new UploadedFile($this->pdfUpload()->getRealPath(), 'paper.docx', 'application/pdf', null, true));
        $this->assertSame(422, $wrongExt->getStatusCode());

        $path = tempnam(sys_get_temp_dir(), 'up') . '.pdf';
        file_put_contents($path, str_repeat('MZ this is an executable ', 10));
        $fake = $this->upload([], new UploadedFile($path, 'paper.pdf', 'application/pdf', null, true));
        $this->assertSame(422, $fake->getStatusCode());
        $this->assertStringContainsString('not a PDF', $fake->getData(true)['message']);

        $truncated = $this->upload([], $this->pdfUpload('cut.pdf', '%PDF-1.4 ' . str_repeat('x', 300)));
        $this->assertSame(422, $truncated->getStatusCode());

        // Standard 19 belongs to another school.
        $foreign = $this->upload(['standard_id' => 19]);
        $this->assertSame(422, $foreign->getStatusCode());

        $this->assertSame(0, DB::table('question_paper_jobs')->count());
        $this->assertSame(0, DB::table('document_master')->count());
        Queue::assertNothingPushed();
    }

    public function test_chosen_academic_ids_must_agree_with_each_other(): void
    {
        Queue::fake();
        $this->assertSame(201, $this->upload(['standard_id' => 9, 'subject_id' => 3, 'chapter_id' => 101])->getStatusCode());
        $this->assertSame(422, $this->upload(['standard_id' => 9, 'subject_id' => 3, 'chapter_id' => 201])->getStatusCode());
    }

    public function test_the_sync_queue_driver_never_runs_extraction_inside_the_request(): void
    {
        config(['queue.default' => 'sync']);
        Queue::fake();

        $response = $this->upload();

        $this->assertSame(201, $response->getStatusCode());
        $this->assertStringContainsString('worker', $response->getData(true)['message']);
        Queue::assertNothingPushed();
        $this->assertSame('queued', DB::table('question_paper_jobs')->value('status'));
    }

    /* ---------- authorization and tenant isolation ---------- */

    public function test_a_job_is_visible_to_its_uploader_and_school_admins_only(): void
    {
        $job = $this->makeJob();

        $own = $this->controller()->show($this->authed('GET'), $job->uuid);
        $this->assertSame(200, $own->getStatusCode());
        $this->assertSame($job->uuid, $own->getData(true)['data']['id']);

        $colleague = $this->controller()->show($this->authed('GET', [], self::TENANT, self::COLLEAGUE), $job->uuid);
        $this->assertSame(404, $colleague->getStatusCode(), 'another staff member of the same school cannot see it');

        $admin = $this->controller()->show($this->authed('GET', [], self::TENANT, self::COLLEAGUE, 1), $job->uuid);
        $this->assertSame(200, $admin->getStatusCode());

        $otherSchoolAdmin = $this->controller()->show($this->authed('GET', [], self::OTHER_TENANT, 900, 1), $job->uuid);
        $this->assertSame(404, $otherSchoolAdmin->getStatusCode(), 'another school gets 404, not 403');
    }

    public function test_the_list_is_scoped_to_the_caller_and_the_school(): void
    {
        $mine = $this->makeJob();
        $this->makeJob(user: self::COLLEAGUE);
        $this->makeJob(tenant: self::OTHER_TENANT, user: 900);

        $ids = fn (Request $r) => array_column($this->controller()->index($r)->getData(true)['data']['jobs'], 'id');

        $this->assertSame([$mine->uuid], $ids($this->authed('GET')));
        $this->assertCount(2, $ids($this->authed('GET', [], self::TENANT, 70, 1)), 'a school admin sees their school only');
    }

    public function test_retry_is_owner_or_admin_only_and_only_for_retryable_states(): void
    {
        Queue::fake();
        $job = $this->makeJob();
        DB::table('question_paper_jobs')->where('id', $job->id)->update(['status' => 'failed', 'retryable' => 1, 'last_error' => 'x']);

        $this->assertSame(404, $this->controller()->retry($this->authed('POST', [], self::TENANT, self::COLLEAGUE), $job->uuid)->getStatusCode());
        $this->assertSame(404, $this->controller()->retry($this->authed('POST', [], self::OTHER_TENANT, 900, 1), $job->uuid)->getStatusCode());
        Queue::assertNothingPushed();

        $this->assertSame(200, $this->controller()->retry($this->authed('POST'), $job->uuid)->getStatusCode());
        Queue::assertPushed(QuestionPaperExtractionJob::class, 1);
        $this->assertSame('queued', DB::table('question_paper_jobs')->where('id', $job->id)->value('status'));

        // A running job cannot be "retried" over the top of itself.
        $this->assertSame(409, $this->controller()->retry($this->authed('POST'), $job->uuid)->getStatusCode());
    }

    public function test_job_detail_lists_what_needs_attention_without_internal_data(): void
    {
        $job = $this->makeJob();
        DB::table('question_paper_items')->insert([
            'job_id' => $job->id, 'sub_institute_id' => self::TENANT, 'ordinal' => 1, 'item_number' => '4', 'source_page' => 2,
            'content_hash' => str_repeat('a', 64), 'state' => 'skipped', 'reason' => 'No question text could be read.', 'payload' => '{}',
        ]);
        DB::table('question_paper_jobs')->where('id', $job->id)->update(['error_log' => '[{"message":"internal trace"}]']);

        $data = $this->controller()->show($this->authed('GET'), $job->uuid)->getData(true)['data'];

        $this->assertSame('No question text could be read.', $data['attention_items'][0]['reason']);
        $this->assertArrayNotHasKey('error_log', $data);
        $this->assertArrayNotHasKey('storage_path', $data);
        $this->assertArrayNotHasKey('lease_token', $data);
    }

    public function test_academic_options_list_only_the_callers_school(): void
    {
        $data = $this->controller()->options($this->authed('GET', ['standard_id' => 9, 'subject_id' => 3]))->getData(true)['data'];

        $this->assertSame([9], array_column($data['standards'], 'id'));
        $this->assertSame([3], array_column($data['subjects'], 'id'));
        $this->assertSame([101], array_column($data['chapters'], 'id'));

        // Asking for another school's standard/subject through the query string returns nothing of theirs.
        $foreign = $this->controller()->options($this->authed('GET', ['standard_id' => 19, 'subject_id' => 13]))->getData(true)['data'];
        $this->assertSame([], $foreign['chapters']);
    }

    /* ---------- progress persistence ---------- */

    public function test_progress_is_read_from_the_database_not_from_the_run(): void
    {
        $job = $this->makeJob();
        DB::table('question_paper_jobs')->where('id', $job->id)->update([
            'status' => 'processing', 'stage' => 'parsing_questions', 'total_pages' => 30, 'processed_pages' => 12,
            'total_chunks' => 10, 'parsed_chunks' => 4, 'questions_extracted' => 48, 'questions_saved' => 0,
        ]);

        // A "new page load": a fresh request, nothing carried over.
        $data = $this->controller()->show($this->authed('GET'), $job->uuid)->getData(true)['data'];

        $this->assertSame(['processing', 'parsing_questions', 30, 12, 48], [$data['status'], $data['stage'], $data['total_pages'], $data['processed_pages'], $data['questions_extracted']]);
        $this->assertSame(36, $data['progress_percent'], '4 of 10 chunks parsed, scaled to the parsing share');
    }

    public function test_percent_is_not_invented_when_nothing_is_known(): void
    {
        $job = $this->makeJob();
        $present = app(QuestionPaperJobService::class)->present($job);

        $this->assertNull($present['progress_percent']);
        $this->assertNull($present['total_pages']);
    }

    /* ---------- scheduler recovery ---------- */

    public function test_the_scheduler_dispatches_stalled_jobs_only_and_in_bounded_batches(): void
    {
        Queue::fake();
        $fresh = $this->makeJob();                    // never dispatched
        $stalled = $this->makeJob();                  // dispatched long ago, still queued
        DB::table('question_paper_jobs')->where('id', $stalled->id)->update(['last_dispatched_at' => now()->subMinutes(10)]);
        $recent = $this->makeJob();                   // dispatched a moment ago
        DB::table('question_paper_jobs')->where('id', $recent->id)->update(['last_dispatched_at' => now()->subSeconds(20)]);
        $held = $this->makeJob();                     // a live worker holds it
        DB::table('question_paper_jobs')->where('id', $held->id)->update(['lease_token' => 'w', 'lease_expires_at' => now()->addMinutes(5)]);
        $deadWorker = $this->makeJob();               // worker died: lease expired, last dispatch old
        DB::table('question_paper_jobs')->where('id', $deadWorker->id)->update(['status' => 'processing', 'lease_token' => 'w', 'lease_expires_at' => now()->subMinutes(1), 'last_dispatched_at' => now()->subMinutes(30)]);
        $done = $this->makeJob();
        DB::table('question_paper_jobs')->where('id', $done->id)->update(['status' => 'completed']);

        $this->artisan('idms:dispatch-question-papers', ['--limit' => 2])->assertExitCode(0);
        Queue::assertPushed(QuestionPaperExtractionJob::class, 2);

        Queue::fake();
        $this->artisan('idms:dispatch-question-papers')->assertExitCode(0);
        $pushed = [];
        Queue::assertPushed(QuestionPaperExtractionJob::class, function ($job) use (&$pushed) {
            $pushed[] = $job->jobUuid;

            return true;
        });
        $this->assertNotContains($held->uuid, $pushed);
        $this->assertNotContains($done->uuid, $pushed);
        $this->assertNotContains($recent->uuid, $pushed);
        $this->assertContains($deadWorker->uuid, $pushed);
    }

    public function test_the_scheduler_refuses_to_run_extraction_on_the_sync_driver(): void
    {
        config(['queue.default' => 'sync']);
        $this->makeJob();

        $this->artisan('idms:dispatch-question-papers')->assertExitCode(1);
        $this->assertNull(DB::table('question_paper_jobs')->value('last_dispatched_at'));
    }

    public function test_the_recovery_command_is_scheduled_every_minute(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $event = collect($schedule->events())->first(fn ($e) => str_contains($e->command, 'idms:dispatch-question-papers'));

        $this->assertNotNull($event);
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    /* ---------- notifications ---------- */

    public function test_notifications_are_persistent_deduplicated_and_marked_read_by_their_owner_only(): void
    {
        $service = app(UserNotificationService::class);
        $make = fn (string $key, int $user = self::OWNER, int $tenant = self::TENANT) => $service->notify($tenant, $user, 't', 'success', 'Done', 'body', '/documents_new', 'question_paper_job', 'u', $key);

        $this->assertTrue($make('k1'));
        $this->assertFalse($make('k1'), 'the same dedupe key never makes a second row');
        $this->assertTrue($make('k2', self::COLLEAGUE));
        $this->assertSame(1, $service->unreadCount(self::TENANT, self::OWNER));

        $id = (int) DB::table('user_notifications')->where('user_id', self::OWNER)->value('id');
        $controller = app(UserNotificationController::class);

        // Wrong person / wrong school cannot mark it.
        $controller->markRead($this->authed('POST', [], self::TENANT, self::COLLEAGUE), $id);
        $controller->markRead($this->authed('POST', [], self::OTHER_TENANT, self::OWNER), $id);
        $this->assertNull(DB::table('user_notifications')->where('id', $id)->value('read_at'));

        $response = $controller->markRead($this->authed('POST'), $id);
        $this->assertSame(0, $response->getData(true)['data']['unread']);
        $this->assertNotNull(DB::table('user_notifications')->where('id', $id)->value('read_at'));
        $this->assertNull(DB::table('user_notifications')->where('user_id', self::COLLEAGUE)->value('read_at'));
    }

    public function test_the_bell_feed_carries_the_notice_with_link_and_read_state_for_its_owner_only(): void
    {
        app(UserNotificationService::class)->notify(self::TENANT, self::OWNER, 'question_paper.completed', 'success', 'Question paper processing completed', 'Science.pdf - 45 questions saved.', '/documents_new?tab=question-papers&job=abc', 'question_paper_job', 'abc', 'k');

        $feed = fn (int $tenant, int $user) => app(\App\Http\Controllers\api\Platform\MyNotificationsController::class)
            ->index($this->authed('GET', [], $tenant, $user))->getData(true)['data'];

        $mine = $feed(self::TENANT, self::OWNER);
        $notice = collect($mine)->firstWhere('key', 'user:' . DB::table('user_notifications')->value('id'));
        $this->assertNotNull($notice);
        $this->assertFalse($notice['read']);
        $this->assertSame('/documents_new?tab=question-papers&job=abc', $notice['link']);

        $this->assertNull(collect($feed(self::TENANT, self::COLLEAGUE))->firstWhere('source', 'user'));
        $this->assertNull(collect($feed(self::OTHER_TENANT, self::OWNER))->firstWhere('source', 'user'));
    }

    public function test_end_to_end_with_a_real_pdf_and_scripted_parser_notifies_the_uploader_after_the_request_is_long_gone(): void
    {
        Queue::fake();
        $this->upload(['standard_id' => 9, 'subject_id' => 3, 'chapter_id' => 101]);
        $uuid = DB::table('question_paper_jobs')->value('uuid');

        // Later, on the worker, with no request and no browser.
        $this->fakeParser([['header' => [], 'questions' => [$this->mcq('Which state of matter has a definite shape?', 'A')]]]);
        app(\App\Services\QuestionPapers\QuestionPaperProcessor::class)->run($uuid);

        $this->assertSame('completed', DB::table('question_paper_jobs')->value('status'));
        $this->assertSame(1, DB::table('lms_question_master')->count());
        $this->assertSame(self::OWNER, (int) DB::table('user_notifications')->value('user_id'));
    }
}
