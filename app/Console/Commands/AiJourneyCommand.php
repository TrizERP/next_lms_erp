<?php

namespace App\Console\Commands;

use App\Domain\AI\Conversation\AskPipeline;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Walk the AI journey from the command line and print every stage.
 *
 * This exists so the pipeline can be verified without a browser, a token or a login. It
 * goes through AskPipeline, which is the same object the HTTP endpoint uses — so the
 * command reaches whichever pipeline is live rather than pinning itself to one. That
 * matters more than it sounds: this command used to inject the legacy AskService
 * directly, which meant that the moment the lifecycle flag was turned on, the terminal
 * and the API would have been running different code while appearing to agree. A
 * verification tool that can disagree with the thing it verifies is worse than none.
 *
 * The banner names the pipeline that answered, so a trace pasted into a ticket says
 * which one produced it.
 *
 *   php artisan ai:journey --institute=1
 *   php artisan ai:journey --institute=1 --ask="Why is Ravi Kumar at risk?"
 *   php artisan ai:journey --institute=1 --full --approve
 */
class AiJourneyCommand extends Command
{
    protected $signature = 'ai:journey
        {--institute= : sub_institute_id to run against (defaults to the most recent agent run)}
        {--user=1 : user id to attribute the questions and any approval to}
        {--role=admin : actor role, which decides what the agent and workflow permit}
        {--year= : academic year override}
        {--term= : term override}
        {--ask= : ask one question instead of the scripted journey}
        {--full : include the follow-up questions after the scan}
        {--approve : also approve the drafted recommendation — this WRITES a decision and starts the workflow}
        {--json : print the raw trace as JSON instead of the ladder}';

    protected $description = 'Run the Student Profiles AI journey and print the full pipeline trace for each turn.';

    /** The scripted journey, in the order the documentation walks it. */
    private const SCRIPT_CORE = [
        'Which students are at academic risk?',
    ];

    private const SCRIPT_FULL = [
        'Why is Student A at risk?',
        'What evidence supports this?',
        'What should the teacher do?',
    ];

    private const SCRIPT_AFTER_APPROVAL = [
        'What happened after approval?',
        'Did the intervention work?',
        'What has the system learned?',
    ];

    public function handle(AskPipeline $ask): int
    {
        $scope = $this->resolveScope();

        if ($scope === null) {
            return self::FAILURE;
        }

        $this->line('');
        $this->info(sprintf(
            'Institute %d · year %s · term %s · role %s · user %d',
            $scope->selectedInstituteId,
            $scope->academicYear ?? '—',
            $scope->termId ?? '—',
            $scope->role,
            $scope->userId
        ));
        $this->line(sprintf(
            '<fg=gray>pipeline %s — the same one POST %s/ask would use</>',
            $ask->name(),
            rtrim((string) config('ai.route_prefix', 'api/ai'), '/')
        ));

        $questions = $this->option('ask')
            ? [$this->option('ask')]
            : array_merge(
                self::SCRIPT_CORE,
                $this->option('full') ? self::SCRIPT_FULL : [],
                $this->option('approve') ? ['Approve the recommendation.'] : [],
                $this->option('approve') ? self::SCRIPT_AFTER_APPROVAL : []
            );

        if ($this->option('approve')) {
            $this->warn('--approve will record a real human decision and start the intervention workflow.');

            if ($this->input->isInteractive() && ! $this->confirm('Continue?', false)) {
                return self::SUCCESS;
            }
        }

        $conversationId = null;

        foreach ($questions as $question) {
            try {
                $result = $ask->ask($question, $scope, $conversationId);
            } catch (Throwable $exception) {
                $this->error('  ' . $exception->getMessage());

                return self::FAILURE;
            }

            $conversationId = $result['conversation']['id'] ?? $conversationId;

            $this->renderTurn($question, $result);
        }

        $this->line('');
        $this->info('Conversation id ' . $conversationId . ' — replay it with:');
        $this->line('  GET ' . rtrim((string) config('ai.route_prefix', 'api/ai'), '/') . '/conversations/' . $conversationId);
        $this->line('');

        return self::SUCCESS;
    }

    private function renderTurn(string $question, array $result): void
    {
        $this->line('');
        $this->line('<options=bold>YOU</> ' . $question);
        $this->line('<options=bold;fg=cyan>AI </> ' . $result['answer']['headline']);
        $this->line(sprintf(
            '    <fg=gray>intent %s · confidence %d%% · %dms</>',
            $result['intent']['key'],
            round(($result['intent']['confidence'] ?? 0) * 100),
            $result['duration_ms']
        ));

        if ($this->option('json')) {
            $this->line(json_encode($result['trace'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        foreach ($result['answer']['sections'] as $section) {
            $this->line('');
            $this->line('    <fg=yellow>' . strtoupper($section['title']) . '</>');
            $this->renderSection($section);
        }

        $this->line('');
        $this->line('    <fg=yellow>PIPELINE</>');

        foreach ($result['trace'] as $stage) {
            $colour = match ($stage['status']) {
                'ran' => 'green',
                'pending' => 'yellow',
                'blocked' => 'red',
                'skipped' => 'gray',
                default => 'gray',
            };

            $mark = match ($stage['status']) {
                'ran' => 'OK', 'pending' => '..', 'blocked' => 'XX', 'skipped' => '--', default => '  ',
            };

            $this->line(sprintf(
                '    <fg=%s>[%s]</> %-30s <fg=gray>%s</>',
                $colour,
                $mark,
                $stage['layer'],
                $this->truncate($stage['summary'] !== '' ? $stage['summary'] : ($stage['note'] ?? ''), 90)
            ));

            // Records are the proof: a stage that claims to have run names the rows.
            if ($stage['status'] === 'ran' && ! empty($stage['records']['ids'])) {
                $this->line(sprintf(
                    '         <fg=gray>-> %s id %s</>',
                    $stage['records']['table'] ?? '?',
                    implode(', ', array_slice($stage['records']['ids'], 0, 10))
                ));
            }
        }

        if ($result['answer']['actions'] !== []) {
            $this->line('');
            $this->line('    <fg=yellow>AVAILABLE ACTIONS</>');

            foreach ($result['answer']['actions'] as $action) {
                $this->line('      • ' . $action['label'] . ' <fg=gray>(say: "' . $action['utterance'] . '")</>');
            }
        }
    }

    private function renderSection(array $section): void
    {
        switch ($section['type']) {
            case 'text':
                $this->line('    ' . wordwrap($section['body'], 96, "\n    "));
                break;

            case 'records':
                foreach ($section['items'] as $item) {
                    $this->line('      • ' . $item['title'] . (isset($item['badge']) ? '  [' . $item['badge'] . ']' : ''));

                    foreach ($item['lines'] ?? [] as $line) {
                        $this->line('        <fg=gray>' . $line . '</>');
                    }

                    if (! empty($item['meta'])) {
                        $this->line('        <fg=gray>' . implode(' · ', array_map(
                            fn ($k, $v) => "$k: $v",
                            array_keys($item['meta']),
                            $item['meta']
                        )) . '</>');
                    }
                }
                break;

            case 'key_values':
                foreach ($section['items'] as $item) {
                    $this->line(sprintf('      %-22s %s', $item['label'], $item['value']));
                }
                break;

            case 'evidence':
                foreach ($section['items'] as $item) {
                    $this->line(sprintf(
                        '      • %s%s',
                        $item['summary'],
                        $item['value'] ? '  = ' . $item['value'] : ''
                    ));
                    $this->line(sprintf(
                        '        <fg=gray>#%s · %s · %s · %s</>',
                        $item['id'],
                        $item['kind'],
                        $item['source'],
                        $item['is_generated'] ? 'generated' : ($item['verified'] ? 'verified' : 'unverified')
                    ));
                }
                break;

            case 'steps':
                foreach ($section['items'] as $item) {
                    $mark = match ($item['status']) {
                        'completed' => 'x', 'rejected', 'failed' => '!', default => $item['is_current'] ? '>' : ' ',
                    };
                    $this->line(sprintf('      [%s] %-38s %s', $mark, $item['label'], $item['status']));
                }
                break;

            case 'comparison':
                foreach ($section['items'] as $item) {
                    $this->line(sprintf(
                        '      %-28s before %-10s after %-10s %s',
                        $item['label'],
                        $item['before'] ?? '—',
                        $item['after'] ?? 'not yet measured',
                        $item['status']
                    ));
                }
                break;
        }
    }

    /**
     * Build the same scope object the HTTP middleware would build, from options or from
     * the most recent agent run. Nothing here widens what a role is allowed to do — the
     * agent and workflow still apply their own role gates to whatever is passed in.
     */
    private function resolveScope(): ?McpRequestContext
    {
        $institute = (int) ($this->option('institute') ?: 0);
        $year = $this->option('year');
        $term = $this->option('term');

        if ($institute <= 0) {
            $last = DB::table('ai_agent_runs')->orderByDesc('id')->first();

            if (! $last) {
                $this->error('No --institute given and no previous agent run to infer one from.');

                return null;
            }

            $institute = (int) $last->sub_institute_id;
            $year ??= $last->academic_year;
            $term ??= $last->term_id;
        }

        if ($year === null || $term === null) {
            $current = DB::table('academic_year')
                ->where('sub_institute_id', $institute)
                ->whereRaw('? between start_date and end_date', [now()->toDateString()])
                ->orderBy('sort_order')
                ->first();

            $year ??= $current->syear ?? null;
            $term ??= $current->term_id ?? null;
        }

        $role = (string) $this->option('role');

        return new McpRequestContext(
            userId: (int) $this->option('user'),
            role: $role,
            selectedInstituteId: $institute,
            allowedInstituteIds: [$institute],
            userProfileId: null,
            clientId: null,
            academicYear: $year === null ? null : (int) $year,
            termId: $term === null ? null : (int) $term,
            isAdmin: $role === 'admin',
            isStudent: $role === 'student'
        );
    }

    private function truncate(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length - 1) . '…' : $value;
    }
}
