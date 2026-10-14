<?php

namespace Tests\Feature\ExamEvaluation\IsolatedMysql;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\ExamEvaluation\ExamEvaluationTestCase;
use Tests\Feature\ExamEvaluation\IdentityTestHelpers;

/**
 * Approval, re-opening and re-running when two requests REALLY overlap -- two PHP processes,
 * two database connections, one real MySQL/MariaDB lock manager.
 *
 * ApprovalConcurrencyTest stages the bad interleaving on SQLite, where there are no locks.
 * This is the same set of races on an engine that has them. Each scenario sends two requests
 * through the real HTTP kernel (tests/Support/exam_eval_review_worker.php) released at the
 * same instant, and the slower one is held for a moment after its first read of the sheet --
 * standing in for a slow network between "read" and "write" -- so the overlap is certain
 * rather than a matter of luck. The old controller (validate on the copy just read, lock only
 * to write) fails every scenario; the current one passes them all.
 *
 * Opt-in and throwaway-only; see UsesIsolatedMysql. Skipped when not pointed at a scratch server.
 */
class ReviewConcurrencyMysqlTest extends ExamEvaluationTestCase
{
    use IdentityTestHelpers;
    use UsesIsolatedMysql;

    private const FAST = 222;
    private const SLOW = 111;
    /** Long enough that the fast request has finished, short enough to keep the run quick. */
    private const STALL_MS = 1500;

    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->useIsolatedMysql('conc');

        foreach ([
            '2026_09_21_000000_create_exam_evaluation_tables',
            '2026_10_13_100000_add_review_fields_to_exam_evaluation',
            '2026_10_13_110000_add_identity_confirmation_to_exam_evaluation_sheet',
            '2026_10_07_100000_create_platform_audit_log_table',
        ] as $migration) {
            (require base_path("database/migrations/{$migration}.php"))->up();
        }

        // Read while the application boots, so the worker processes need it too.
        Schema::create('custom_module_tables', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('module_name')->nullable();
            $table->string('access_link')->nullable();
        });

        $this->createSurroundingTables();
        $this->seedPaper();
        \App\Services\Evaluation\SheetIdentity::forgetSchema();

        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'exam-eval-conc-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->dir);
        $this->dropIsolatedMysql();
        parent::tearDown();
    }

    // -- the harness ----------------------------------------------------------------------

    /**
     * Sends two requests at the same instant from two processes.
     *
     * @param  array{method?:string,uri:string,body:array<string,mixed>,stall_ms?:int}  $first
     * @param  array{method?:string,uri:string,body:array<string,mixed>,stall_ms?:int}  $second
     * @return array{0:array<string,mixed>,1:array<string,mixed>}
     */
    private function together(array $first, array $second): array
    {
        $go = $this->dir . DIRECTORY_SEPARATOR . 'go-' . uniqid();
        $procs = [];

        foreach ([$first, $second] as $i => $request) {
            $spec = [
                'db' => $this->isolatedServer,
                'request' => ['method' => $request['method'] ?? 'POST', 'uri' => $request['uri'], 'body' => $request['body']],
                'ready' => "{$this->dir}/ready-{$i}-" . basename($go),
                'go' => $go,
                'out' => "{$this->dir}/out-{$i}-" . basename($go),
                'stall_ms' => $request['stall_ms'] ?? 0,
            ];
            $specFile = "{$this->dir}/spec-{$i}-" . basename($go) . '.json';
            file_put_contents($specFile, json_encode($spec));

            $process = proc_open(
                [PHP_BINARY, '-d', 'memory_limit=-1', base_path('tests/Support/exam_eval_review_worker.php'), $specFile],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            $this->assertIsResource($process, 'could not start a worker');
            fclose($pipes[0]);
            $procs[$i] = ['process' => $process, 'pipes' => $pipes, 'spec' => $spec];
        }

        // Both booted and waiting; then release them together.
        $deadline = microtime(true) + 90;

        while (true) {
            clearstatcache(true);

            if (is_file($procs[0]['spec']['ready']) && is_file($procs[1]['spec']['ready'])) {
                break;
            }

            // The message is only built once the deadline has passed. (Passed as an argument to
            // assertLessThan it was built on EVERY poll, and reading a running worker's stderr
            // blocks until the worker exits -- which stalled the whole run for a minute.)
            if (microtime(true) > $deadline) {
                $this->fail('the workers did not become ready: ' . $this->diagnose($procs));
            }

            usleep(20000);
        }

        touch($go);

        $results = [];

        foreach ($procs as $i => $worker) {
            while (proc_get_status($worker['process'])['running']) {
                if (microtime(true) > $deadline + 60) {
                    $this->fail('a worker did not finish: ' . $this->diagnose($procs));
                }

                usleep(10000);
            }

            $stderr = stream_get_contents($worker['pipes'][2]);
            $stdout = stream_get_contents($worker['pipes'][1]);
            $exit = proc_close($worker['process']);

            $this->assertFileExists(
                $worker['spec']['out'],
                "worker {$i} produced no result (exit {$exit}). stderr: " . substr(trim($stderr), 0, 1500) . ' | stdout: ' . substr(trim($stdout), 0, 1500)
            );
            $results[$i] = json_decode((string) file_get_contents($worker['spec']['out']), true);
        }

        return [$results[0], $results[1]];
    }

    private function diagnose(array $procs): string
    {
        $out = [];

        foreach ($procs as $i => $worker) {
            // Never block on a worker that is still running.
            stream_set_blocking($worker['pipes'][2], false);
            $out[] = "worker {$i}: " . trim((string) stream_get_contents($worker['pipes'][2]));
        }

        return implode(' | ', $out);
    }

    /** @return array<string,mixed> */
    private function as_(int $actor, array $extra = []): array
    {
        return $extra + ['sub_institute_id' => self::TENANT, 'syear' => self::SYEAR, 'user_id' => $actor];
    }

    private function reviewRequest(int $sheetId, int $actor, array $extra, int $stallMs = 0): array
    {
        return ['uri' => "/api/exam-evaluation/sheets/{$sheetId}/review", 'body' => $this->as_($actor, $extra), 'stall_ms' => $stallMs];
    }

    private function freshSheet(array $over = []): int
    {
        // A batch of its own each time: one sheet per student per batch.
        return $this->makeSheet($this->makeBatch(), $over);
    }

    /** @return array<int,string> */
    private function audit(int $sheetId, string $action): array
    {
        return DB::table('platform_audit_log')
            ->where('entity_type', 'exam_evaluation_sheet')->where('entity_id', (string) $sheetId)->where('action', $action)
            ->orderBy('id')->pluck('actor_user_id')->map(fn ($actor) => (string) $actor)->all();
    }

    private function sheetRow(int $id): object
    {
        return DB::table('exam_evaluation_sheet')->find($id);
    }

    private function marks(): array
    {
        return ['marks' => [['question_no' => 2, 'teacher_marks' => 4], ['question_no' => 3, 'teacher_marks' => 2]]];
    }

    private function diag(array ...$results): string
    {
        return json_encode(array_map(fn ($r) => ['status' => $r['status'], 'message' => $r['body']['message'] ?? null], $results));
    }

    // -- two approvals ------------------------------------------------------------------------

    public function test_two_simultaneous_approvals_leave_one_approver_one_timestamp_and_one_audit_event(): void
    {
        $sheetId = $this->freshSheet();

        [$slow, $fast] = $this->together(
            $this->reviewRequest($sheetId, self::SLOW, ['approve' => true], self::STALL_MS),
            $this->reviewRequest($sheetId, self::FAST, ['approve' => true] + $this->marks())
        );

        $this->assertSame(200, $fast['status'], $this->diag($fast, $slow));
        $this->assertSame(200, $slow['status'], 'a bare approval of an already-approved sheet is a no-op, not an error: ' . $this->diag($slow));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Approved', $sheet->status);
        $this->assertSame(self::FAST, (int) $sheet->approved_by, 'the request that got the lock first is the approver');
        $this->assertNotNull($sheet->approved_at);
        $this->assertSame([(string) self::FAST], $this->audit($sheetId, 'approve'), 'one approval, one event, naming the same person the sheet does');
    }

    // -- an edit meets an approval -------------------------------------------------------------

    public function test_an_edit_that_loses_the_race_to_an_approval_cannot_change_the_approved_marks(): void
    {
        $sheetId = $this->freshSheet();

        [$edit, $approve] = $this->together(
            $this->reviewRequest($sheetId, self::SLOW, ['marks' => [['question_no' => 2, 'teacher_marks' => 1]]], self::STALL_MS),
            $this->reviewRequest($sheetId, self::FAST, ['approve' => true] + $this->marks())
        );

        $this->assertSame(200, $approve['status'], $this->diag($approve, $edit));
        $this->assertSame(422, $edit['status'], 'the late edit must be refused: ' . $this->diag($edit));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Approved', $sheet->status);
        $q2 = DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->where('question_no', 2)->value('teacher_marks');
        $this->assertEquals(4.0, $q2, 'the approved mark stands');
        $this->assertEquals(8.0, $sheet->teacher_total, 'and the total is the approved one: 2 + 4 + 2');
        $this->assertSame([], array_values(array_diff($this->audit($sheetId, 'update_marks'), [(string) self::FAST])), 'no edit by the slower request reached the trail');
    }

    public function test_the_total_always_equals_the_sum_of_the_marks_whichever_request_wins(): void
    {
        // No stall: a genuine race between an edit and an approval. Either order is legal;
        // what must never happen is an approved sheet whose total disagrees with its marks.
        for ($round = 0; $round < 3; $round++) {
            $sheetId = $this->freshSheet();

            [$edit, $approve] = $this->together(
                $this->reviewRequest($sheetId, self::SLOW, ['marks' => [['question_no' => 2, 'teacher_marks' => 1]]]),
                $this->reviewRequest($sheetId, self::FAST, ['approve' => true] + $this->marks())
            );

            $this->assertContains($approve['status'], [200, 422], $this->diag($approve, $edit));
            $this->assertContains($edit['status'], [200, 422], $this->diag($approve, $edit));

            $sheet = $this->sheetRow($sheetId);
            $sum = (float) DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->selectRaw('sum(coalesce(teacher_marks, ai_marks, 0)) s')->value('s');

            if ($sheet->status === 'Approved') {
                $this->assertEquals($sum, (float) $sheet->teacher_total, 'round ' . $round . ': approved total disagrees with its marks');
                $this->assertSame(0, DB::table('exam_evaluation_answer')->where('sheet_id', $sheetId)->whereNull('teacher_marks')->count(), 'an approved sheet has a teacher mark on every question');
            }
        }
    }

    // -- re-opening ----------------------------------------------------------------------------------

    public function test_two_simultaneous_reopens_one_wins_and_only_it_is_recorded(): void
    {
        $sheetId = $this->freshSheet(['status' => 'Approved', 'approved_by' => 55, 'approved_at' => now()]);

        [$slow, $fast] = $this->together(
            $this->reviewRequest($sheetId, self::SLOW, ['unapprove' => true], self::STALL_MS),
            $this->reviewRequest($sheetId, self::FAST, ['unapprove' => true])
        );

        $this->assertSame(200, $fast['status'], $this->diag($fast, $slow));
        $this->assertSame(422, $slow['status'], 'the second re-open finds nothing to re-open: ' . $this->diag($slow));

        $sheet = $this->sheetRow($sheetId);
        $this->assertSame('Needs review', $sheet->status);
        $this->assertNull($sheet->approved_by);
        $this->assertNull($sheet->approved_at);
        $this->assertSame([(string) self::FAST], $this->audit($sheetId, 'reopen'));
    }

    // -- re-running meets an approval --------------------------------------------------------------------

    public function test_a_rerun_that_loses_the_race_to_an_approval_cannot_reset_the_approved_sheet(): void
    {
        $sheetId = $this->freshSheet();

        [$rerun, $approve] = $this->together(
            ['uri' => "/api/exam-evaluation/sheets/{$sheetId}/reprocess", 'body' => $this->as_(self::SLOW), 'stall_ms' => self::STALL_MS],
            $this->reviewRequest($sheetId, self::FAST, ['approve' => true] + $this->marks())
        );

        $this->assertSame(200, $approve['status'], $this->diag($approve, $rerun));
        $this->assertSame(422, $rerun['status'], 'the re-run must be refused: ' . $this->diag($rerun));
        $this->assertSame('Approved', $this->sheetRow($sheetId)->status, 'an approved sheet is not sent back to Pending');
        $this->assertSame([], $this->audit($sheetId, 'reprocess'));
        $this->assertSame(0, $rerun['dispatched']['EvaluateAnswerSheetJob'] + $rerun['dispatched']['IdentifyAnswerSheetJob'], 'and no job was queued for it');
    }

    // -- contention without a stall: no deadlocks, no lost requests --------------------------------------

    public function test_unrelated_reviews_in_one_batch_contend_without_deadlock(): void
    {
        for ($round = 0; $round < 3; $round++) {
            $batchId = $this->makeBatch();
            $one = $this->makeSheet($batchId, ['student_id' => 101]);
            $two = $this->makeSheet($batchId, ['student_id' => 102]);

            [$a, $b] = $this->together(
                $this->reviewRequest($one, self::SLOW, ['approve' => true] + $this->marks()),
                $this->reviewRequest($two, self::FAST, ['approve' => true] + $this->marks())
            );

            $this->assertSame([200, 200], [$a['status'], $b['status']], 'round ' . $round . ': ' . $this->diag($a, $b));
            $this->assertSame('Approved', $this->sheetRow($one)->status);
            $this->assertSame('Approved', $this->sheetRow($two)->status);
        }
    }

    public function test_assigning_the_same_student_to_two_sheets_at_once_lets_exactly_one_through(): void
    {
        // Two teachers pick Ravi for two different unmatched sheets at the same moment.
        $batchId = $this->makeBatch();
        $one = $this->makeSheet($batchId, ['student_id' => null, 'identity_source' => 'unmatched', 'identity_confirmed_at' => null]);
        $two = $this->makeSheet($batchId, ['student_id' => null, 'identity_source' => 'unmatched', 'identity_confirmed_at' => null]);

        [$a, $b] = $this->together(
            $this->reviewRequest($one, self::SLOW, ['student_id' => 102]),
            $this->reviewRequest($two, self::FAST, ['student_id' => 102])
        );

        $statuses = [$a['status'], $b['status']];
        sort($statuses);
        $this->assertSame([200, 422], $statuses, 'exactly one wins: ' . $this->diag($a, $b));
        $this->assertSame(1, DB::table('exam_evaluation_sheet')->where('batch_id', $batchId)->where('student_id', 102)->count(), 'one sheet per student per batch');
        $loser = $a['status'] === 422 ? $a : $b;
        $this->assertSame('Another sheet in this batch is already assigned to that student.', $loser['body']['message']);
    }
}
