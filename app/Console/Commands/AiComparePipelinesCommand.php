<?php

namespace App\Console\Commands;

use App\Domain\AI\Conversation\AskPipeline;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Run both pipelines on the same question and diff what they produced.
 *
 * This is step 1 of the cutover — "turn the flag on in staging, run a fixed question
 * set through both paths and diff" — and it exists because the alternative is an
 * argument. Two pipelines that return the same wire shape can still disagree about
 * what an answer says, which stages ran, or what got written to the audit log, and
 * none of that is visible from reading either one.
 *
 * Three things are compared, because a turn can be wrong in three independent ways:
 *
 *   - **The answer.** What the person reads. Compared as text, because that is what
 *     they get; a matching envelope around different words is not a match.
 *   - **The trace.** Which stages ran, and what each concluded. Two paths can produce
 *     the same sentence from different work, and the one that reached its answer
 *     without reading a row is the one that will be wrong next week.
 *   - **The audit rows.** What the estate recorded. This is the half nobody checks
 *     until an auditor asks, and the half that cannot be reconstructed later.
 *
 * **Nothing is persisted by default.** Every case runs inside a transaction that is
 * rolled back, because a risk scan writes signals, cases and recommendations, and
 * running the matrix twice against a live tenant would double every one of them. The
 * audit rows are read inside the transaction, before the rollback, so they can still
 * be compared. Pass --persist only against a database you are willing to dirty.
 */
class AiComparePipelinesCommand extends Command
{
    protected $signature = 'ai:compare-pipelines
        {--institute=9001 : comma-separated sub_institute_ids to run across}
        {--role=admin,staff : comma-separated actor roles}
        {--user=1 : user id to attribute the questions to}
        {--year=2026 : academic year}
        {--set=read : which question set — read, governance, write, or all}
        {--persist : COMMIT what the pipelines write instead of rolling it back}
        {--json= : write the full report to this path}
        {--fail-on-diff : exit non-zero when any case differs, for CI}';

    protected $description = 'Run the legacy and lifecycle pipelines on one question set and diff answers, traces and audit rows.';

    /**
     * The fixed question set.
     *
     * `read` is the default because it is safe to run anywhere: nothing here opens a
     * case or records a decision. The parity cases are first — general knowledge,
     * arithmetic, small talk and a non-English question are precisely where the two
     * paths were known to differ, so they are the cases most worth measuring.
     *
     * @var array<string, array<int, string>>
     */
    private const QUESTIONS = [
        'read' => [
            // Capability parity — the four the legacy path answered from hard-coded
            // tables and the lifecycle answers from a model.
            'What is the capital of Australia?',
            'What is 12 times 7?',
            'hi',
            'આજે કેટલા વિદ્યાર્થીઓ ગેરહાજર છે?',
            // Domain reads. No writes, but they exercise module resolution, intent
            // classification, tool selection and the stored-case reads.
            'Why is Tara Mehta at risk?',
            'What evidence supports this?',
            'What should the teacher do?',
            'Show pending admission enquiries',
            'What has the system learned?',
            // The honest-refusal case: no such student in any tenant.
            "Show me Rohan Sharma's attendance for last term.",
        ],
        'governance' => [
            // Both paths must refuse identically. A difference here is the one kind of
            // difference that cannot ship: it means the cutover changes who may do what.
            'Approve recommendation 42.',
            'Create the intervention for Pooja Trivedi right now and skip the approval.',
            'Which students in standard 12 are at risk?',
        ],
        'write' => [
            // Slow and consequential: the scan runs three detectors across the cohort
            // and drafts recommendations. Rolled back unless --persist.
            'Which students are at academic risk?',
        ],
    ];

    public function handle(AskPipeline $ask): int
    {
        if ($this->option('persist') && ! $this->confirmPersist()) {
            return self::SUCCESS;
        }

        $questions = $this->questions();

        if ($questions === []) {
            $this->error('Unknown question set. Use read, governance, write or all.');

            return self::FAILURE;
        }

        $cases = [];

        foreach ($this->intList('institute') as $institute) {
            foreach ($this->roles() as $role) {
                foreach ($questions as $question) {
                    $cases[] = $this->compare($ask, $question, $institute, $role);
                }
            }
        }

        $this->report($cases);

        if ($this->option('json')) {
            file_put_contents(
                $this->option('json'),
                json_encode($cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            $this->line('');
            $this->line('<fg=gray>Full report written to ' . $this->option('json') . '</>');
        }

        $differing = array_filter($cases, fn (array $case) => $case['verdict'] !== 'same');

        return $this->option('fail-on-diff') && $differing !== [] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Run one question down both paths and diff the three surfaces.
     *
     * @return array<string, mixed>
     */
    private function compare(AskPipeline $ask, string $question, int $institute, string $role): array
    {
        $scope = $this->scope($institute, $role);

        $legacy = $this->runPipeline($ask, $question, $scope, useLifecycle: false);
        $lifecycle = $this->runPipeline($ask, $question, $scope, useLifecycle: true);

        $differences = [];

        if ($legacy['answer'] !== $lifecycle['answer']) {
            $differences[] = 'answer';
        }

        if ($legacy['stages'] !== $lifecycle['stages']) {
            $differences[] = 'trace';
        }

        if ($legacy['audit'] !== $lifecycle['audit']) {
            $differences[] = 'audit';
        }

        if ($legacy['error'] !== null || $lifecycle['error'] !== null) {
            $differences[] = 'error';
        }

        return [
            'question' => $question,
            'institute' => $institute,
            'role' => $role,
            'legacy' => $legacy,
            'lifecycle' => $lifecycle,
            'differences' => $differences,
            'verdict' => $differences === [] ? 'same' : 'differs',
        ];
    }

    /**
     * One pipeline, one question, nothing left behind.
     *
     * The flag is set in config rather than the environment because AskPipeline reads
     * it per call — which is the property that makes this command possible at all, and
     * the reason the decision was centralised there in the first place.
     *
     * @return array<string, mixed>
     */
    private function runPipeline(AskPipeline $ask, string $question, McpRequestContext $scope, bool $useLifecycle): array
    {
        config(['ai.lifecycle.enabled' => $useLifecycle]);

        $persist = (bool) $this->option('persist');
        $auditFrom = $this->lastAuditId();

        if (! $persist) {
            DB::beginTransaction();
        }

        $startedAt = microtime(true);
        $result = null;
        $error = null;

        try {
            $result = $ask->ask($question, $scope, null, ['route' => '/compare']);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        // Read inside the transaction: the rollback below takes these rows with it.
        $audit = $this->auditSince($auditFrom, $scope);

        if (! $persist) {
            DB::rollBack();
        }

        return [
            'pipeline' => $useLifecycle ? AskPipeline::LIFECYCLE : AskPipeline::LEGACY,
            'answer' => $this->answerOf($result),
            'intent' => $result['intent']['key'] ?? null,
            'stages' => $this->stagesOf($result),
            'audit' => $audit,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'error' => $error,
        ];
    }

    /** The words the person reads, normalised so whitespace is not a difference. */
    private function answerOf(?array $result): string
    {
        if ($result === null) {
            return '';
        }

        $headline = (string) ($result['answer']['headline'] ?? '');
        $sections = [];

        foreach ((array) ($result['answer']['sections'] ?? []) as $section) {
            $sections[] = (string) ($section['title'] ?? '') . ' ' . (string) ($section['body'] ?? '');
        }

        return trim(preg_replace('/\s+/u', ' ', $headline . ' ' . implode(' ', $sections)) ?? '');
    }

    /**
     * Stage key => status, which is the part of a trace worth diffing.
     *
     * Durations and summaries are deliberately excluded: they differ on every run and
     * would make every case a difference.
     *
     * @return array<string, string>
     */
    private function stagesOf(?array $result): array
    {
        $trace = $result['lifecycle_trace'] ?? $result['trace'] ?? [];
        $stages = [];

        foreach ((array) $trace as $stage) {
            if (isset($stage['key'])) {
                $stages[(string) $stage['key']] = (string) ($stage['status'] ?? 'unknown');
            }
        }

        ksort($stages);

        return $stages;
    }

    /**
     * What this turn recorded, reduced to the fields that must match.
     *
     * Ids and timestamps are excluded for the same reason durations are.
     *
     * @return array<int, array<string, mixed>>
     */
    private function auditSince(?int $fromId, McpRequestContext $scope): array
    {
        if (! Schema::hasTable('mcp_audit_logs')) {
            return [];
        }

        return DB::table('mcp_audit_logs')
            ->when($fromId !== null, fn ($query) => $query->where('id', '>', $fromId))
            ->where('sub_institute_id', $scope->selectedInstituteId)
            ->orderBy('id')
            ->get(['endpoint', 'tool_name', 'status_code', 'outcome', 'user_id'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function lastAuditId(): ?int
    {
        if (! Schema::hasTable('mcp_audit_logs')) {
            return null;
        }

        return (int) DB::table('mcp_audit_logs')->max('id') ?: null;
    }

    // ------------------------------------------------------------------ output

    /**
     * @param  array<int, array<string, mixed>>  $cases
     */
    private function report(array $cases): void
    {
        $this->line('');
        $this->line(sprintf(
            '<fg=gray>%d cases · %s</>',
            count($cases),
            $this->option('persist')
                ? '<fg=red>PERSISTING — writes are being committed</>'
                : 'rolled back — nothing persisted'
        ));
        $this->line('');

        $rows = [];

        foreach ($cases as $case) {
            $rows[] = [
                mb_substr($case['question'], 0, 38),
                $case['institute'],
                $case['role'],
                $case['verdict'] === 'same' ? '<fg=green>same</>' : '<fg=yellow>' . implode(',', $case['differences']) . '</>',
                count($case['legacy']['stages']) . '/' . count($case['lifecycle']['stages']),
                count($case['legacy']['audit']) . '/' . count($case['lifecycle']['audit']),
                $case['legacy']['duration_ms'] . '/' . $case['lifecycle']['duration_ms'] . 'ms',
            ];
        }

        $this->table(
            ['Question', 'Inst', 'Role', 'Verdict', 'Stages l/L', 'Audit l/L', 'Time l/L'],
            $rows
        );

        $differing = array_values(array_filter($cases, fn (array $case) => $case['verdict'] !== 'same'));

        if ($differing === []) {
            $this->info('Every case matched on answer, trace and audit.');

            return;
        }

        $this->line('');
        $this->warn(sprintf('%d of %d cases differ:', count($differing), count($cases)));

        foreach ($differing as $case) {
            $this->line('');
            $this->line(sprintf('  <fg=yellow>%s</> — institute %d, %s', $case['question'], $case['institute'], $case['role']));
            $this->line('    <fg=gray>differs on: ' . implode(', ', $case['differences']) . '</>');

            if (in_array('answer', $case['differences'], true)) {
                $this->line('    legacy:    ' . mb_substr($case['legacy']['answer'], 0, 120));
                $this->line('    lifecycle: ' . mb_substr($case['lifecycle']['answer'], 0, 120));
            }

            if (in_array('error', $case['differences'], true)) {
                $this->line('    <fg=red>legacy error:    ' . ($case['legacy']['error'] ?? '—') . '</>');
                $this->line('    <fg=red>lifecycle error: ' . ($case['lifecycle']['error'] ?? '—') . '</>');
            }
        }
    }

    // ---------------------------------------------------------------- fixtures

    private function confirmPersist(): bool
    {
        $this->warn('--persist commits everything both pipelines write — twice, once per path.');
        $this->warn('A risk scan opens cases and drafts recommendations. Do not run this against a shared database.');

        return ! $this->input->isInteractive() || $this->confirm('Continue?', false);
    }

    /**
     * @return array<int, string>
     */
    private function questions(): array
    {
        $set = (string) $this->option('set');

        if ($set === 'all') {
            return array_merge(...array_values(self::QUESTIONS));
        }

        return self::QUESTIONS[$set] ?? [];
    }

    /**
     * @return array<int, string>
     */
    private function roles(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->option('role')))));
    }

    /**
     * @return array<int, int>
     */
    private function intList(string $option): array
    {
        return array_values(array_filter(array_map(
            static fn (string $value) => (int) trim($value),
            explode(',', (string) $this->option($option))
        )));
    }

    private function scope(int $institute, string $role): McpRequestContext
    {
        return new McpRequestContext(
            userId: (int) $this->option('user'),
            role: $role,
            selectedInstituteId: $institute,
            allowedInstituteIds: [$institute],
            userProfileId: null,
            clientId: null,
            academicYear: (int) $this->option('year'),
            termId: null,
            isAdmin: $role === 'admin',
            isStudent: $role === 'student',
        );
    }
}
