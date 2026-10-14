<?php

namespace Tests\Feature\ExamEvaluation;

use App\Services\Evaluation\ExamEvaluationStorage;
use GenTux\Jwt\JwtToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The exam-evaluation routes behind the REAL guard (RequireApiJwt), end to end:
 * a genuine bearer token goes in, and what reaches the controller -- and what is
 * written to the database -- is checked. Unlike the other feature tests in this
 * folder, the guard is switched ON here.
 *
 * Tokens are minted the way ApiLoginController's are and the way the repo's other
 * guard tests do it, with a throwaway secret set for the duration of the test.
 */
class ExamEvaluationGuardTest extends ExamEvaluationTestCase
{
    private const TEACHER_ID = 4242;

    private ?string $previousSecret = null;

    private string $scan = '';

    private int $batchId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousSecret = getenv('JWT_SECRET') === false ? null : (string) getenv('JWT_SECRET');
        putenv('JWT_SECRET=' . str_repeat('s', 40));
        config(['api_guard.enforce' => true]);

        // What the guard reads to decide whether a profile is a student or parent.
        Schema::create('tbluserprofilemaster', function ($t) {
            $t->integer('id')->primary();
            $t->string('name');
            $t->integer('status')->default(1);
            $t->integer('sub_institute_id')->nullable();
            $t->integer('parent_id')->nullable();
        });
        DB::table('tbluserprofilemaster')->insert([
            ['id' => 2, 'name' => 'Teacher', 'sub_institute_id' => self::TENANT],
            ['id' => 3, 'name' => 'Student', 'sub_institute_id' => self::TENANT],
            ['id' => 4, 'name' => 'Parent', 'sub_institute_id' => self::TENANT],
        ]);

        $this->seedPaper();
        $this->batchId = $this->makeBatch();

        $this->scan = (string) tempnam(sys_get_temp_dir(), 'exam-eval-guard-');
        file_put_contents($this->scan, '%PDF-1.4 the original scan');

        $scan = $this->scan;
        $this->app->instance(ExamEvaluationStorage::class, new class($scan) extends ExamEvaluationStorage {
            public function __construct(private string $path)
            {
            }

            public function sheetPath(string $fileName): ?string
            {
                return $this->path;
            }

            public function annotatedPath(string $fileName): ?string
            {
                return null;
            }
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->scan);
        $this->previousSecret === null ? putenv('JWT_SECRET') : putenv('JWT_SECRET=' . $this->previousSecret);
        parent::tearDown();
    }

    private function token(array $claims = []): string
    {
        return app(JwtToken::class)->createToken(array_merge([
            'id' => self::TEACHER_ID,
            'sub_institute_id' => self::TENANT,
            'is_admin' => 0,
            'client_id' => 1,
            'user_profile_id' => 2,
            'is_student' => false,
        ], $claims));
    }

    /** @return array<string,string> */
    private function bearer(?string $token): array
    {
        // Per request: withHeaders() would carry one request's token into the next.
        return $token === null ? [] : ['Authorization' => 'Bearer ' . $token];
    }

    private function readyToApprove(): int
    {
        return $this->makeSheet($this->batchId);
    }

    private function approveBody(array $extra = []): array
    {
        return $this->body($extra + [
            'approve' => true,
            'marks' => [['question_no' => 2, 'teacher_marks' => 4], ['question_no' => 3, 'teacher_marks' => 2]],
        ]);
    }

    public function test_every_exam_evaluation_route_refuses_a_caller_with_no_token(): void
    {
        $sheetId = $this->readyToApprove();
        $q = '?sub_institute_id=' . self::TENANT;

        $this->getJson("/api/exam-evaluation/batches{$q}")->assertStatus(401);
        $this->getJson("/api/exam-evaluation/batches/{$this->batchId}{$q}")->assertStatus(401);
        $this->getJson("/api/exam-evaluation/sheets/{$sheetId}{$q}")->assertStatus(401);
        $this->getJson("/api/exam-evaluation/sheets/{$sheetId}/file{$q}")->assertStatus(401);
        $this->getJson("/api/exam-evaluation/answer-key/1{$q}")->assertStatus(401);
        $this->postJson("/api/exam-evaluation/sheets/{$sheetId}/review", $this->approveBody())->assertStatus(401);
        $this->postJson("/api/exam-evaluation/sheets/{$sheetId}/reprocess", $this->body())->assertStatus(401);
        $this->postJson("/api/exam-evaluation/batches/{$this->batchId}/publish", $this->body())->assertStatus(401);
        $this->postJson('/api/exam-evaluation/batches', $this->body(['question_paper_id' => 1]))->assertStatus(401);
        $this->deleteJson("/api/exam-evaluation/sheets/{$sheetId}{$q}")->assertStatus(401);

        $this->assertNull(DB::table('exam_evaluation_sheet')->find($sheetId)->approved_by, 'nothing may be written for an anonymous caller');
    }

    public function test_a_forged_or_garbage_token_is_refused(): void
    {
        $sheetId = $this->readyToApprove();

        $this->postJson("/api/exam-evaluation/sheets/{$sheetId}/review", $this->approveBody(), $this->bearer('not.a.token'))->assertStatus(401);

        $tampered = $this->token();
        $tampered = substr($tampered, 0, -4) . 'AAAA';
        $this->postJson("/api/exam-evaluation/sheets/{$sheetId}/review", $this->approveBody(), $this->bearer($tampered))->assertStatus(401);
        $this->assertSame('Needs review', DB::table('exam_evaluation_sheet')->find($sheetId)->status);
    }

    public function test_the_approver_is_the_token_holder_even_when_the_client_claims_to_be_someone_else(): void
    {
        $sheetId = $this->readyToApprove();

        // The body says user_id 1 (the default body says 55); the token says 4242.
        $this->postJson(
            "/api/exam-evaluation/sheets/{$sheetId}/review",
            $this->approveBody(['user_id' => 1]),
            $this->bearer($this->token())
        )->assertOk();

        $sheet = DB::table('exam_evaluation_sheet')->find($sheetId);
        $this->assertSame('Approved', $sheet->status);
        $this->assertSame(self::TEACHER_ID, (int) $sheet->approved_by, 'approved_by must come from the verified token');

        $audit = DB::table('platform_audit_log')->where('action', 'approve')->first();
        $this->assertSame(self::TEACHER_ID, (int) $audit->actor_user_id, 'the audit trail names the token holder too');
    }

    public function test_a_new_batch_is_attributed_to_the_token_holder(): void
    {
        $this->postJson('/api/exam-evaluation/batches', $this->body(['question_paper_id' => 1, 'user_id' => 1]), $this->bearer($this->token()))->assertOk();

        $this->assertSame(self::TEACHER_ID, (int) DB::table('exam_evaluation_batch')->orderByDesc('id')->value('created_by'));
    }

    public function test_students_and_parents_are_refused_on_every_route_whether_flagged_or_by_profile(): void
    {
        $sheetId = $this->readyToApprove();
        $q = '?sub_institute_id=' . self::TENANT;

        foreach ([
            $this->token(['is_student' => true, 'user_profile_id' => 3]),
            $this->token(['is_student' => false, 'user_profile_id' => 3]),   // profile name alone
            $this->token(['is_student' => false, 'user_profile_id' => 4]),   // parent
        ] as $studentLike) {
            $this->getJson("/api/exam-evaluation/sheets/{$sheetId}{$q}", $this->bearer($studentLike))->assertStatus(403);
            $this->getJson("/api/exam-evaluation/sheets/{$sheetId}/file{$q}", $this->bearer($studentLike))->assertStatus(403);
            $this->getJson("/api/exam-evaluation/batches{$q}", $this->bearer($studentLike))->assertStatus(403);
            $this->postJson("/api/exam-evaluation/sheets/{$sheetId}/review", $this->approveBody(), $this->bearer($studentLike))->assertStatus(403);
            $this->postJson("/api/exam-evaluation/batches/{$this->batchId}/publish", $this->body(), $this->bearer($studentLike))->assertStatus(403);
        }

        $this->assertSame('Needs review', DB::table('exam_evaluation_sheet')->find($sheetId)->status);
    }

    public function test_a_teacher_cannot_name_another_school(): void
    {
        $sheetId = $this->readyToApprove();
        $other = $this->makeSheet($this->makeBatch(['sub_institute_id' => self::OTHER_TENANT]), ['sub_institute_id' => self::OTHER_TENANT, 'student_id' => 201]);

        // Asking for school 8 with a school-7 token is refused outright by the guard...
        $this->getJson("/api/exam-evaluation/sheets/{$other}?sub_institute_id=" . self::OTHER_TENANT, $this->bearer($this->token()))->assertStatus(403);
        $this->postJson("/api/exam-evaluation/sheets/{$other}/review", $this->approveBody(['sub_institute_id' => self::OTHER_TENANT]), $this->bearer($this->token()))->assertStatus(403);
        // ...and naming school 7 but another school's sheet id finds nothing.
        $this->getJson("/api/exam-evaluation/sheets/{$other}?sub_institute_id=" . self::TENANT, $this->bearer($this->token()))->assertStatus(404);
        $this->getJson("/api/exam-evaluation/sheets/{$other}/file?sub_institute_id=" . self::TENANT, $this->bearer($this->token()))->assertStatus(404);
        $this->postJson("/api/exam-evaluation/sheets/{$other}/review", $this->approveBody(), $this->bearer($this->token()))->assertStatus(404);

        $this->assertNull(DB::table('exam_evaluation_sheet')->find($other)->approved_by);
        $this->assertNotNull($sheetId);
    }

    public function test_the_original_scan_is_served_inline_to_staff_and_never_names_a_server_path(): void
    {
        $sheetId = $this->readyToApprove();

        $response = $this->get(
            "/api/exam-evaluation/sheets/{$sheetId}/file?sub_institute_id=" . self::TENANT,
            $this->bearer($this->token()) + ['Accept' => 'application/pdf']
        );

        $response->assertOk();
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-1.4 the original scan', file_get_contents($response->baseResponse->getFile()->getPathname()));

        // Nothing in the headers or the sheet payload reveals where files live on the server.
        $headers = json_encode($response->headers->all());
        $this->assertStringNotContainsString('exam_evaluation', (string) $headers);
        $this->assertStringNotContainsString(sys_get_temp_dir(), (string) $headers);

        $payload = $this->getJson("/api/exam-evaluation/sheets/{$sheetId}?sub_institute_id=" . self::TENANT, $this->bearer($this->token()))->assertOk()->getContent();
        foreach (['file_name', 'annotated_file_name', 'storage_path', 'exam_evaluation/'] as $leak) {
            $this->assertStringNotContainsString($leak, (string) $payload, "the sheet payload must not expose {$leak}");
        }
    }

    public function test_a_missing_marked_up_copy_is_a_clean_404_not_a_path_leak(): void
    {
        $sheetId = $this->readyToApprove();

        $response = $this->getJson(
            "/api/exam-evaluation/sheets/{$sheetId}/file?annotated=1&sub_institute_id=" . self::TENANT,
            $this->bearer($this->token())
        );

        $response->assertStatus(404)->assertJsonPath('message', 'No marked-up copy was produced for this sheet.');
    }

    public function test_the_guard_covers_each_exam_evaluation_route_in_the_route_table(): void
    {
        $unguarded = [];
        $seen = 0;

        foreach (app('router')->getRoutes() as $route) {
            if (strpos($route->uri(), 'api/exam-evaluation/') !== 0) {
                continue;
            }

            $seen++;
            $request = \Illuminate\Http\Request::create('/' . $route->uri(), $route->methods()[0]);

            if (! app(\App\Http\Middleware\RequireApiJwt::class)->isProtected($request)) {
                $unguarded[] = $route->methods()[0] . ' ' . $route->uri();
            }
        }

        $this->assertGreaterThanOrEqual(12, $seen, 'the 12 exam-evaluation routes should all be registered');
        $this->assertSame([], $unguarded, 'every exam-evaluation route must be inside the guard');
    }
}
