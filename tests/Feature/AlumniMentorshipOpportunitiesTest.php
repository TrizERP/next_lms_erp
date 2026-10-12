<?php

namespace Tests\Feature;

use App\Http\Controllers\api\alumni\AlumniEngagementApiController;
use App\Http\Controllers\api\alumni\AlumniMentorshipApiController;
use App\Http\Controllers\api\alumni\AlumniOpportunityApiController;
use App\Http\Controllers\api\alumni\AlumniStudentApiController;
use App\Services\Alumni\AlumniEngagementService;
use App\Services\Alumni\AlumniService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase C: mentorship workflow, opportunities, engagement score, and the student-facing isolation rules.
 *
 * RUNS ON IN-MEMORY SQLITE, FORCED IN setUp (phpunit.xml does not force it); setUp refuses to continue otherwise.
 */
class AlumniMentorshipOpportunitiesTest extends TestCase
{
    private AlumniService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a real database');
        (require base_path('database/migrations/2026_10_10_100000_create_alumni_core_tables.php'))->up();
        (require base_path('database/migrations/2026_10_11_100000_create_alumni_engagement_tables.php'))->up();
        Schema::create('tblstudent', function ($t) {
            $t->integer('id')->primary();
            $t->integer('sub_institute_id');
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('enrollment_no')->nullable();
        });
        DB::table('tblstudent')->insert([
            ['id' => 501, 'sub_institute_id' => 7, 'first_name' => 'Stu', 'last_name' => 'One', 'enrollment_no' => 'E1'],
            ['id' => 502, 'sub_institute_id' => 7, 'first_name' => 'Stu', 'last_name' => 'Two', 'enrollment_no' => 'E2'],
            ['id' => 601, 'sub_institute_id' => 8, 'first_name' => 'Other', 'last_name' => 'Tenant', 'enrollment_no' => 'E3'],
        ]);
        $this->svc = app(AlumniService::class);
    }

    private function res(string $controller, string $method, array $input = [], int $tenant = 7, string $profile = 'Admin', array $args = [], bool $student = false, ?int $userId = 55)
    {
        $request = Request::create('/x', 'POST', $input);
        $request->merge(['type' => 'API']);   // what the api.session middleware does to every request
        $store = app('session.store');
        $store->flush();
        $request->setLaravelSession($store);
        $store->put('sub_institute_id', $tenant);
        $store->put('user_id', $userId);
        $store->put('user_profile_name', $profile);
        $store->put('is_student', $student);

        return app($controller)->{$method}($request, ...$args);
    }

    private function go(string $controller, string $method, array $input = [], int $tenant = 7, string $profile = 'Admin', array $args = []): array
    {
        $r = $this->res($controller, $method, $input, $tenant, $profile, $args);

        return [$r->getStatusCode(), $r->getData(true)];
    }

    private function asStudent(string $method, int $studentId, array $input = [], array $args = [], int $tenant = 7): array
    {
        $r = $this->res(AlumniStudentApiController::class, $method, $input, $tenant, 'Student', $args, true, $studentId);

        return [$r->getStatusCode(), $r->getData(true)];
    }

    private function alum(string $first, array $over = [], int $tenant = 7, string $status = 'active'): object
    {
        static $n = 0;
        $n++;
        $row = $this->svc->create($tenant, array_merge([
            'first_name' => $first, 'last_name' => 'Alum', 'dob' => '2000-02-' . str_pad((string) (($n % 27) + 1), 2, '0', STR_PAD_LEFT),
            'email' => "a{$n}@example.org", 'mobile' => '80000' . str_pad((string) $n, 5, '0', STR_PAD_LEFT), 'passing_year' => 2018,
        ], $over), 1);
        $this->svc->changeStatus($tenant, (int) $row->id, $status, 1);

        return $this->svc->find($tenant, (int) $row->id);
    }

    private function mentor(object $alumnus, array $over = []): int
    {
        [$s, $b] = $this->go(AlumniMentorshipApiController::class, 'mentorStore', array_merge([
            'alumni_id' => $alumnus->id, 'expertise' => 'Data analytics, machine learning', 'industry' => 'IT', 'job_role' => 'Data scientist', 'capacity' => 3,
        ], $over));
        $this->assertSame(201, $s, json_encode($b));

        return (int) $b['data']['id'];
    }

    private function request(array $over = [], int $student = 501): int
    {
        [$s, $b] = $this->go(AlumniMentorshipApiController::class, 'requestStore', array_merge(['student_id' => $student, 'career_area' => 'Data science', 'guidance_needed' => 'How to start with analytics'], $over));
        $this->assertSame(201, $s, json_encode($b));

        return (int) $b['data']['id'];
    }

    // ------------------------------------------------------------------ mentors

    public function test_only_verified_or_active_alumni_can_register_as_mentors_once(): void
    {
        $pending = $this->alum('Pending', [], 7, 'pending_verification');
        $other = $this->alum('Elsewhere', [], 8);
        $good = $this->alum('Good');

        [$a] = $this->go(AlumniMentorshipApiController::class, 'mentorStore', ['alumni_id' => $pending->id]);
        [$b] = $this->go(AlumniMentorshipApiController::class, 'mentorStore', ['alumni_id' => $other->id]);   // another institute
        [$c] = $this->go(AlumniMentorshipApiController::class, 'mentorStore', ['alumni_id' => $good->id, 'available_days' => ['mon', 'someday']]);
        $this->mentor($good);
        [$dup, $d] = $this->go(AlumniMentorshipApiController::class, 'mentorStore', ['alumni_id' => $good->id]);

        $this->assertSame([422, 422, 422], [$a, $b, $c]);
        $this->assertSame(422, $dup);
        $this->assertArrayHasKey('alumni_id', $d['errors']);
        [, $list] = $this->go(AlumniMentorshipApiController::class, 'mentors');
        $this->assertCount(1, $list['data']);
    }

    // ----------------------------------------------------------------- workflow

    public function test_full_mentorship_workflow_credits_the_mentor_exactly_once(): void
    {
        $alum = $this->alum('Maya');
        $this->mentor($alum);
        $req = $this->request();

        [$m, $match] = $this->go(AlumniMentorshipApiController::class, 'match', ['mentor_alumni_id' => $alum->id], 7, 'Admin', [$req]);
        [$early] = $this->go(AlumniMentorshipApiController::class, 'sessionStore', ['scheduled_at' => now()->addDay()->toDateTimeString()], 7, 'Admin', [$req]);
        [$acc] = $this->go(AlumniMentorshipApiController::class, 'respond', ['decision' => 'accept'], 7, 'Admin', [$req]);
        [$noSession] = $this->go(AlumniMentorshipApiController::class, 'complete', [], 7, 'Admin', [$req]);
        [$past] = $this->go(AlumniMentorshipApiController::class, 'sessionStore', ['scheduled_at' => now()->subDay()->toDateTimeString()], 7, 'Admin', [$req]);
        [$sess, $s] = $this->go(AlumniMentorshipApiController::class, 'sessionStore', ['scheduled_at' => now()->addDay()->toDateTimeString(), 'duration_minutes' => 30], 7, 'Admin', [$req]);
        $sid = $s['data']['id'];
        [$fbEarly] = $this->go(AlumniMentorshipApiController::class, 'feedback', ['from_role' => 'student', 'rating' => 5], 7, 'Admin', [$sid]);
        [$done] = $this->go(AlumniMentorshipApiController::class, 'sessionUpdate', ['status' => 'completed', 'notes' => 'Great'], 7, 'Admin', [$sid]);
        [$again] = $this->go(AlumniMentorshipApiController::class, 'sessionUpdate', ['status' => 'completed'], 7, 'Admin', [$sid]);
        [$fb1] = $this->go(AlumniMentorshipApiController::class, 'feedback', ['from_role' => 'student', 'rating' => 5, 'comments' => 'Helpful'], 7, 'Admin', [$sid]);
        [$fb2] = $this->go(AlumniMentorshipApiController::class, 'feedback', ['from_role' => 'mentor', 'rating' => 4], 7, 'Admin', [$sid]);
        [$fbDup] = $this->go(AlumniMentorshipApiController::class, 'feedback', ['from_role' => 'student', 'rating' => 1], 7, 'Admin', [$sid]);
        [$fbBad] = $this->go(AlumniMentorshipApiController::class, 'feedback', ['from_role' => 'mentor', 'rating' => 9], 7, 'Admin', [$sid]);
        [$comp, $c] = $this->go(AlumniMentorshipApiController::class, 'complete', [], 7, 'Admin', [$req]);

        $this->assertSame([200, 'matched'], [$m, $match['data']['status']]);
        $this->assertSame(409, $early);       // not accepted yet
        $this->assertSame(200, $acc);
        $this->assertSame(409, $noSession);
        $this->assertSame(422, $past);        // sessions are scheduled in the future
        $this->assertSame(201, $sess);
        $this->assertSame(409, $fbEarly);     // feedback needs a completed session
        $this->assertSame(200, $done);
        $this->assertSame(409, $again);       // already completed
        $this->assertSame([201, 201, 409, 422], [$fb1, $fb2, $fbDup, $fbBad]);
        $this->assertSame([200, 'completed'], [$comp, $c['data']['status']]);

        $credits = DB::table('alumni_activity')->where('alumni_id', $alum->id)->where('activity_type', 'mentoring')->get();
        $this->assertCount(1, $credits);                                      // exactly once
        $this->assertSame(20, (int) $credits[0]->points);                     // the spec's weight
        $this->assertSame(20, app(AlumniEngagementService::class)->scores(7, [$alum->id])[$alum->id]);
    }

    public function test_suggestions_are_ranked_explained_and_respect_capacity_and_declines(): void
    {
        $data = $this->alum('Data');
        $law = $this->alum('Law');
        $full = $this->alum('Full');
        $this->mentor($data, ['expertise' => 'Data science analytics python', 'industry' => 'IT', 'job_role' => 'Data scientist']);
        $this->mentor($law, ['expertise' => 'Corporate law', 'industry' => 'Legal', 'job_role' => 'Lawyer']);
        $this->mentor($full, ['expertise' => 'Data science', 'capacity' => 1]);
        $busy = $this->request(['career_area' => 'Law'], 502);
        $this->go(AlumniMentorshipApiController::class, 'match', ['mentor_alumni_id' => $full->id], 7, 'Admin', [$busy]);   // fills Full's only slot
        $req = $this->request(['career_area' => 'Data science', 'guidance_needed' => 'analytics with python']);

        [, $m] = $this->go(AlumniMentorshipApiController::class, 'matches', [], 7, 'Admin', [$req]);
        $names = array_column($m['data'], 'full_name');

        $this->assertSame('Data Alum', $names[0]);                            // best word overlap first
        $this->assertContains('data', $m['data'][0]['matched_on']);
        $this->assertContains('python', $m['data'][0]['matched_on']);
        $this->assertNotContains('Full Alum', $names);                        // no free slot

        // a mentor who declines is not suggested again and cannot be force-matched
        $this->go(AlumniMentorshipApiController::class, 'match', ['mentor_alumni_id' => $data->id], 7, 'Admin', [$req]);
        [$noReason] = $this->go(AlumniMentorshipApiController::class, 'respond', ['decision' => 'decline'], 7, 'Admin', [$req]);
        [$declined, $d] = $this->go(AlumniMentorshipApiController::class, 'respond', ['decision' => 'decline', 'note' => 'Too busy'], 7, 'Admin', [$req]);
        [, $after] = $this->go(AlumniMentorshipApiController::class, 'matches', [], 7, 'Admin', [$req]);
        [$forced, $f] = $this->go(AlumniMentorshipApiController::class, 'match', ['mentor_alumni_id' => $data->id], 7, 'Admin', [$req]);

        $this->assertSame(422, $noReason);
        $this->assertSame([200, 'open'], [$declined, $d['data']['status']]);
        $this->assertNull($d['data']['mentor_alumni_id']);
        $this->assertNotContains('Data Alum', array_column($after['data'], 'full_name'));
        $this->assertSame(409, $forced);
        $this->assertStringContainsString('declined', $f['message']);
    }

    public function test_workflow_rejects_out_of_order_actions_and_over_capacity_matches(): void
    {
        $one = $this->alum('One');
        $this->mentor($one, ['capacity' => 1]);
        $r1 = $this->request([], 501);
        $r2 = $this->request([], 502);

        [$respondOpen] = $this->go(AlumniMentorshipApiController::class, 'respond', ['decision' => 'accept'], 7, 'Admin', [$r1]);
        [$ok] = $this->go(AlumniMentorshipApiController::class, 'match', ['mentor_alumni_id' => $one->id], 7, 'Admin', [$r1]);
        [$rematch] = $this->go(AlumniMentorshipApiController::class, 'match', ['mentor_alumni_id' => $one->id], 7, 'Admin', [$r1]);
        [$full, $f] = $this->go(AlumniMentorshipApiController::class, 'match', ['mentor_alumni_id' => $one->id], 7, 'Admin', [$r2]);
        [$notMentor] = $this->go(AlumniMentorshipApiController::class, 'match', ['mentor_alumni_id' => 999999], 7, 'Admin', [$r2]);
        [$cancel] = $this->go(AlumniMentorshipApiController::class, 'cancel', [], 7, 'Admin', [$r1]);
        [$cancelAgain] = $this->go(AlumniMentorshipApiController::class, 'cancel', [], 7, 'Admin', [$r1]);
        [$afterCancel] = $this->go(AlumniMentorshipApiController::class, 'match', ['mentor_alumni_id' => $one->id], 7, 'Admin', [$r2]);

        $this->assertSame(409, $respondOpen);
        $this->assertSame(200, $ok);
        $this->assertSame(409, $rematch);
        $this->assertSame(409, $full);
        $this->assertStringContainsString('no free', $f['message']);
        $this->assertSame(409, $notMentor);
        $this->assertSame([200, 409], [$cancel, $cancelAgain]);
        $this->assertSame(200, $afterCancel);                                  // cancelling freed the slot
    }

    public function test_other_institutes_and_non_staff_cannot_touch_mentorship_records(): void
    {
        $alum = $this->alum('Maya');
        $this->mentor($alum);
        $req = $this->request();

        foreach ([['requestShow', []], ['matches', []], ['match', ['mentor_alumni_id' => $alum->id]], ['cancel', []]] as [$method, $input]) {
            [$other] = $this->go(AlumniMentorshipApiController::class, $method, $input, 8, 'Admin', [$req]);
            $this->assertSame(404, $other, $method);   // another institute's request is simply not found
        }
        [$teacher] = $this->go(AlumniMentorshipApiController::class, 'requests', [], 7, 'Teacher');
        [$crossStudent] = $this->go(AlumniMentorshipApiController::class, 'requestStore', ['student_id' => 601, 'career_area' => 'x']);   // student of another institute

        $this->assertSame(403, $teacher);
        $this->assertSame(422, $crossStudent);
        $this->assertSame('open', DB::table('alumni_mentorship_requests')->where('id', $req)->value('status'));
    }

    public function test_a_student_may_have_at_most_three_requests_in_progress(): void
    {
        foreach ([1, 2, 3] as $i) {
            $this->request(['career_area' => "Area $i"]);
        }
        [$status, $b] = $this->go(AlumniMentorshipApiController::class, 'requestStore', ['student_id' => 501, 'career_area' => 'Area 4']);

        $this->assertSame(409, $status);
        $this->assertStringContainsString('three requests', $b['message']);
    }

    public function test_student_search_is_scoped_minimal_and_staff_only(): void
    {
        [, $r] = $this->go(AlumniMentorshipApiController::class, 'studentSearch', ['q' => 'Stu']);
        [, $short] = $this->go(AlumniMentorshipApiController::class, 'studentSearch', ['q' => 'S']);
        [, $other] = $this->go(AlumniMentorshipApiController::class, 'studentSearch', ['q' => 'Stu'], 8);
        [$teacher] = $this->go(AlumniMentorshipApiController::class, 'studentSearch', ['q' => 'Stu'], 7, 'Teacher');

        $this->assertSame([501, 502], array_column($r['data'], 'id'));
        $this->assertSame(['id', 'first_name', 'last_name', 'enrollment_no'], array_keys($r['data'][0]));   // nothing else about a student leaves
        $this->assertSame([], $short['data']);                                                              // too short to search
        $this->assertSame([], $other['data']);                                                              // another institute's students are invisible
        $this->assertSame(403, $teacher);
    }

    // ------------------------------------------------------------ student-facing

    public function test_student_endpoints_are_for_students_and_bound_to_the_student_in_the_token(): void
    {
        $alum = $this->alum('Maya', ['email' => 'maya.private@example.org', 'mobile' => '9998887776']);
        $this->mentor($alum);

        [$staffTry] = $this->go(AlumniStudentApiController::class, 'mentors');                                 // staff are not students
        [$ghost] = $this->asStudent('myRequests', 999999);                                                       // not a student of this institute
        [$wrongTenant] = $this->asStudent('myRequests', 601);                                                    // student 601 belongs to institute 8
        [$ok, $mine] = $this->asStudent('requestStore', 501, ['career_area' => 'Analytics', 'student_id' => 502]);   // a smuggled student_id is ignored
        [, $two] = $this->asStudent('myRequests', 502);
        [, $one] = $this->asStudent('myRequests', 501);
        $reqId = $mine['data']['id'];
        [$cancelOthers] = $this->asStudent('requestCancel', 502, [], [$reqId]);
        [$cancelMine] = $this->asStudent('requestCancel', 501, [], [$reqId]);
        [, $mentors] = $this->asStudent('mentors', 501);

        $this->assertSame([403, 403, 403], [$staffTry, $ghost, $wrongTenant]);
        $this->assertSame(201, $ok);
        $this->assertSame(501, (int) $mine['data']['student_id']);
        $this->assertSame('student', $mine['data']['created_by_role']);
        $this->assertCount(0, $two['data']);
        $this->assertCount(1, $one['data']);
        $this->assertSame(404, $cancelOthers);
        $this->assertSame(200, $cancelMine);
        $row = $mentors['data'][0];
        $this->assertSame('Maya Alum', $row['full_name']);
        $this->assertArrayNotHasKey('email', $row);                                                              // alumni contact details are never shown
        $this->assertArrayNotHasKey('mobile', $row);
        $this->assertStringNotContainsString('maya.private', json_encode($mentors));
    }

    public function test_student_feedback_is_limited_to_their_own_completed_sessions(): void
    {
        $alum = $this->alum('Maya');
        $this->mentor($alum);
        $req = $this->request([], 501);
        $this->go(AlumniMentorshipApiController::class, 'match', ['mentor_alumni_id' => $alum->id], 7, 'Admin', [$req]);
        $this->go(AlumniMentorshipApiController::class, 'respond', ['decision' => 'accept'], 7, 'Admin', [$req]);
        [, $s] = $this->go(AlumniMentorshipApiController::class, 'sessionStore', ['scheduled_at' => now()->addDay()->toDateTimeString()], 7, 'Admin', [$req]);
        $sid = $s['data']['id'];
        $this->go(AlumniMentorshipApiController::class, 'sessionUpdate', ['status' => 'completed'], 7, 'Admin', [$sid]);

        [$other] = $this->asStudent('sessionFeedback', 502, ['rating' => 5], [$sid]);
        [$bad] = $this->asStudent('sessionFeedback', 501, ['rating' => 0], [$sid]);
        [$ok] = $this->asStudent('sessionFeedback', 501, ['rating' => 5, 'comments' => 'Thank you'], [$sid]);
        [$dup] = $this->asStudent('sessionFeedback', 501, ['rating' => 4], [$sid]);
        [, $mine] = $this->asStudent('myRequests', 501);

        $this->assertSame([404, 422, 201, 409], [$other, $bad, $ok, $dup]);
        $this->assertSame(5, $mine['data'][0]['sessions'][0]['my_rating']);
    }

    // ------------------------------------------------------------- opportunities

    private function opp(array $over = [], int $tenant = 7): array
    {
        return $this->go(AlumniOpportunityApiController::class, 'store', array_merge([
            'opportunity_type' => 'internship', 'company' => 'Acme', 'title' => 'Data intern', 'last_date' => now()->addDays(10)->toDateString(),
            'application_url' => 'https://acme.example/apply', 'skills' => 'python, sql',
        ], $over), $tenant);
    }

    public function test_alumni_submitted_opportunities_wait_for_approval_and_are_credited_once(): void
    {
        $alum = $this->alum('Giver');
        [$s, $b] = $this->opp(['alumni_id' => $alum->id]);
        $id = $b['data']['id'];
        [, $board1] = $this->asStudent('opportunities', 501);

        [$noReason] = $this->go(AlumniOpportunityApiController::class, 'review', ['decision' => 'reject'], 7, 'Admin', [$id]);
        [$ok, $a] = $this->go(AlumniOpportunityApiController::class, 'review', ['decision' => 'approve'], 7, 'Admin', [$id]);
        [$again] = $this->go(AlumniOpportunityApiController::class, 'review', ['decision' => 'approve'], 7, 'Admin', [$id]);
        [, $board2] = $this->asStudent('opportunities', 501);

        $this->assertSame([201, 'pending_approval'], [$s, $b['data']['status']]);
        $this->assertCount(0, $board1['data']['rows']);                        // invisible until approved
        $this->assertSame(422, $noReason);
        $this->assertSame([200, 'published'], [$ok, $a['data']['status']]);
        $this->assertSame(409, $again);
        $this->assertCount(1, $board2['data']['rows']);
        $this->assertSame('Giver Alum', $board2['data']['rows'][0]['shared_by']);
        $credit = DB::table('alumni_activity')->where('alumni_id', $alum->id)->where('activity_type', 'internship_support')->get();
        $this->assertCount(1, $credit);
        $this->assertSame(25, (int) $credit[0]->points);
    }

    public function test_rejected_opportunities_never_reach_students_and_institute_postings_publish_directly(): void
    {
        $alum = $this->alum('Giver');
        [, $b] = $this->opp(['alumni_id' => $alum->id, 'title' => 'Rejected one']);
        $this->go(AlumniOpportunityApiController::class, 'review', ['decision' => 'reject', 'remarks' => 'Not relevant'], 7, 'Admin', [$b['data']['id']]);
        [$s, $own] = $this->opp(['title' => 'Institute posting']);                 // no alumnus: the institute itself
        [, $board] = $this->asStudent('opportunities', 501);

        $this->assertSame([201, 'published'], [$s, $own['data']['status']]);
        $this->assertSame(['Institute posting'], array_column($board['data']['rows'], 'title'));
        $this->assertSame(0, DB::table('alumni_activity')->where('alumni_id', $alum->id)->where('activity_type', 'internship_support')->count());   // no credit for a rejection
    }

    public function test_with_approval_switched_off_everything_publishes_immediately(): void
    {
        $this->go(\App\Http\Controllers\api\alumni\AlumniApiController::class, 'saveSettings', ['opportunity_approval_required' => false]);
        $alum = $this->alum('Giver');

        [, $b] = $this->opp(['alumni_id' => $alum->id]);

        $this->assertSame('published', $b['data']['status']);
    }

    public function test_expiry_is_derived_from_the_date_and_hides_listings_from_students(): void
    {
        [, $live] = $this->opp(['title' => 'Live']);
        [, $dead] = $this->opp(['title' => 'Dead']);
        DB::table('alumni_opportunities')->where('id', $dead['data']['id'])->update(['last_date' => now()->subDay()->toDateString()]);   // time passes
        [$past] = $this->opp(['last_date' => now()->subDay()->toDateString()]);                                                          // cannot be created expired

        [, $board] = $this->asStudent('opportunities', 501);
        [, $staffExpired] = $this->go(AlumniOpportunityApiController::class, 'index', ['status' => 'expired']);
        [, $staffPublished] = $this->go(AlumniOpportunityApiController::class, 'index', ['status' => 'published']);
        [$showDead] = $this->asStudent('opportunityShow', 501, [], [$dead['data']['id']]);

        $this->assertSame(422, $past);
        $this->assertSame(['Live'], array_column($board['data']['rows'], 'title'));
        $this->assertSame(['Dead'], array_column($staffExpired['data']['rows'], 'title'));
        $this->assertSame('expired', $staffExpired['data']['rows'][0]['effective_status']);
        $this->assertSame(['Live'], array_column($staffPublished['data']['rows'], 'title'));
        $this->assertSame(404, $showDead);
    }

    public function test_application_links_must_be_http_and_the_board_is_scoped_and_minimal(): void
    {
        [$js] = $this->opp(['application_url' => 'javascript:alert(1)']);
        [$data] = $this->opp(['application_url' => 'data:text/html,<script>1</script>']);
        [$bare] = $this->opp(['application_url' => 'acme.example/apply']);
        [$ok] = $this->opp(['title' => 'Mine']);
        $this->opp(['title' => 'Theirs'], 8);

        [, $board] = $this->asStudent('opportunities', 501);
        $row = $board['data']['rows'][0];

        $this->assertSame([422, 422, 422, 201], [$js, $data, $bare, $ok]);
        $this->assertSame(['Mine'], array_column($board['data']['rows'], 'title'));
        foreach (['posted_by', 'reviewed_by', 'review_remarks', 'alumni_id', 'sub_institute_id'] as $private) {
            $this->assertArrayNotHasKey($private, $row, $private);
        }
    }

    public function test_editing_a_published_alumni_listing_sends_it_back_for_review_and_other_institutes_are_blocked(): void
    {
        $alum = $this->alum('Giver');
        [, $b] = $this->opp(['alumni_id' => $alum->id]);
        $id = $b['data']['id'];
        $this->go(AlumniOpportunityApiController::class, 'review', ['decision' => 'approve'], 7, 'Admin', [$id]);

        [, $u] = $this->go(AlumniOpportunityApiController::class, 'update', ['title' => 'Changed title'], 7, 'Admin', [$id]);
        [$cross] = $this->go(AlumniOpportunityApiController::class, 'update', ['title' => 'Hijack'], 8, 'Admin', [$id]);
        [$crossClose] = $this->go(AlumniOpportunityApiController::class, 'close', [], 8, 'Admin', [$id]);
        [$crossDelete] = $this->go(AlumniOpportunityApiController::class, 'destroy', [], 8, 'Admin', [$id]);
        [$teacher] = $this->go(AlumniOpportunityApiController::class, 'index', [], 7, 'Teacher');
        [, $closed] = $this->go(AlumniOpportunityApiController::class, 'close', [], 7, 'Admin', [$id]);
        [$editClosed] = $this->go(AlumniOpportunityApiController::class, 'update', ['title' => 'x'], 7, 'Admin', [$id]);

        $this->assertSame('pending_approval', $u['data']['status']);
        $this->assertSame([404, 404, 404, 403], [$cross, $crossClose, $crossDelete, $teacher]);
        $this->assertSame('closed', $closed['data']['status']);
        $this->assertSame(409, $editClosed);
        $this->assertSame('Changed title', DB::table('alumni_opportunities')->where('id', $id)->value('title'));
    }

    // ---------------------------------------------------------------- engagement

    public function test_engagement_score_uses_configurable_weights_a_window_a_cap_and_levels(): void
    {
        $eng = app(AlumniEngagementService::class);
        $a = $this->alum('Star');
        $b = $this->alum('Quiet');

        [$s, $r] = $this->go(AlumniEngagementApiController::class, 'record', ['alumni_id' => $a->id, 'engagement_type' => 'guest_lecture', 'title' => 'Talk on AI']);
        $this->go(AlumniEngagementApiController::class, 'record', ['alumni_id' => $a->id, 'engagement_type' => 'donation', 'title' => 'Gift']);
        $this->go(AlumniEngagementApiController::class, 'record', ['alumni_id' => $a->id, 'engagement_type' => 'mentoring', 'title' => 'Mentored']);
        $this->go(AlumniEngagementApiController::class, 'record', ['alumni_id' => $a->id, 'engagement_type' => 'campus_visit', 'title' => 'Visit', 'occurred_on' => now()->subYears(5)->toDateString()]);   // outside the 24-month window
        [$cap, $c] = $this->go(AlumniEngagementApiController::class, 'record', ['alumni_id' => $a->id, 'engagement_type' => 'donation', 'title' => 'Another']);
        $this->go(AlumniEngagementApiController::class, 'record', ['alumni_id' => $b->id, 'engagement_type' => 'event_participation', 'title' => 'Reunion']);

        $this->assertSame([201, 25], [$s, $r['data']['score']]);               // guest lecture = 25 by default
        $this->assertSame(100, $c['data']['score']);                           // 25 + 30 + 20 + 30 = 105, capped; the 5-year-old visit is outside the window
        $this->assertSame('highly_active', $c['data']['level']);
        $this->assertSame('occasional', $eng->level(7, $eng->scores(7, [$b->id])[$b->id]));
        $this->assertSame('dormant', $eng->level(7, 0));

        // the tenant can change a weight
        $this->go(\App\Http\Controllers\api\alumni\AlumniApiController::class, 'saveSettings', ['engagement_weights' => ['event_participation' => 50]]);
        [, $r2] = $this->go(AlumniEngagementApiController::class, 'record', ['alumni_id' => $b->id, 'engagement_type' => 'event_participation', 'title' => 'Second reunion']);
        $this->assertSame(60, $r2['data']['score']);                           // 10 earlier + 50 now
        [, $lb] = $this->go(AlumniEngagementApiController::class, 'leaderboard');
        $this->assertSame(['Star Alum', 'Quiet Alum'], array_column($lb['data'], 'full_name'));
    }

    public function test_automatic_engagement_credit_is_idempotent_and_protected_but_manual_entries_can_be_removed(): void
    {
        $eng = app(AlumniEngagementService::class);
        $a = $this->alum('Auto');
        $first = $eng->record(7, $a->id, 'mentoring', 'Session', 1, null, null, 'mentorship_session', 42);
        $second = $eng->record(7, $a->id, 'mentoring', 'Session', 1, null, null, 'mentorship_session', 42);
        $this->go(AlumniEngagementApiController::class, 'record', ['alumni_id' => $a->id, 'engagement_type' => 'volunteering', 'title' => 'Cleanup drive']);
        $manual = DB::table('alumni_activity')->where('activity_type', 'volunteering')->value('id');

        [$delAuto, $d] = $this->go(AlumniEngagementApiController::class, 'destroy', [], 7, 'Admin', [$first]);
        [$delManual] = $this->go(AlumniEngagementApiController::class, 'destroy', [], 7, 'Admin', [$manual]);
        [$delOther] = $this->go(AlumniEngagementApiController::class, 'destroy', [], 8, 'Admin', [$first]);

        $this->assertNotNull($first);
        $this->assertNull($second);                                            // the same source twice adds nothing
        $this->assertSame(1, DB::table('alumni_activity')->where('activity_type', 'mentoring')->count());
        $this->assertSame(409, $delAuto);
        $this->assertStringContainsString('automatically', $d['message']);
        $this->assertSame(200, $delManual);
        $this->assertSame(404, $delOther);
    }

    public function test_engagement_recording_validates_and_is_scoped(): void
    {
        $mine = $this->alum('Mine');
        $theirs = $this->alum('Theirs', [], 8);

        [$badType] = $this->go(AlumniEngagementApiController::class, 'record', ['alumni_id' => $mine->id, 'engagement_type' => 'selfie', 'title' => 'x']);
        [$other] = $this->go(AlumniEngagementApiController::class, 'record', ['alumni_id' => $theirs->id, 'engagement_type' => 'donation', 'title' => 'x']);
        [$future] = $this->go(AlumniEngagementApiController::class, 'record', ['alumni_id' => $mine->id, 'engagement_type' => 'donation', 'title' => 'x', 'occurred_on' => now()->addYear()->toDateString()]);
        [$pts] = $this->go(AlumniEngagementApiController::class, 'record', ['alumni_id' => $mine->id, 'engagement_type' => 'donation', 'title' => 'x', 'points' => 500]);
        [$teacher] = $this->go(AlumniEngagementApiController::class, 'record', ['alumni_id' => $mine->id, 'engagement_type' => 'donation', 'title' => 'x'], 7, 'Teacher');
        [$forOther] = $this->go(AlumniEngagementApiController::class, 'forAlumnus', [], 8, 'Admin', [$mine->id]);

        $this->assertSame([422, 422, 422, 422, 403, 404], [$badType, $other, $future, $pts, $teacher, $forOther]);
        $this->assertSame(0, DB::table('alumni_activity')->where('activity_type', 'donation')->count());
    }
}
