<?php

namespace App\Console\Commands\PAL;

use App\Services\PAL\Flow\EsoFlowRegistry;
use App\Services\PAL\Flow\EsoFlowResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Put one institute on one flow profile.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS A COMMAND AND NOT A TINKER ONE-LINER
 * ---------------------------------------------------------------------------
 * This is the single call in the whole system that changes what real students
 * are served. Until this runs, every institute resolves the shipped default
 * and the entire flow feature is inert.
 *
 * Doing it through `php artisan tinker` works, and is a bad idea for exactly
 * that reason: no confirmation, no record of what the institute was on before,
 * no check that the profile key is one that exists, and a typo assigns
 * something nobody meant with no way to notice. A change with this blast
 * radius should be hard to make by accident and easy to reverse.
 *
 * ---------------------------------------------------------------------------
 * WHAT IT DOES NOT DO
 * ---------------------------------------------------------------------------
 * It does not touch learners who are already part-way through a concept. Their
 * nodes carry learner_node_state.flow_version_id, and the resolver honours
 * that pin over the new assignment — so nobody is taught under one flow and
 * assessed under another. New concepts pick the new flow up.
 */
class FlowAssignCommand extends Command
{
    protected $signature = 'pal:flow-assign
        {institute? : sub_institute_id}
        {profile? : standard | diagnostic_free | no_cfu | check_first}
        {--remove : Remove the assignment, returning this institute to the default}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Assign an institute to a PAL flow profile. This changes what its students are served.';

    public function handle(): int
    {
        $registry = app(EsoFlowRegistry::class);

        if (! $registry->available()) {
            $this->error('The PAL flow tables are not present. Run the 2026_09_21_1000* migrations with --path.');

            return self::FAILURE;
        }

        $institute = $this->argument('institute');

        if ($institute === null) {
            return $this->showCurrent($registry);
        }

        $institute = (int) $institute;

        if ($this->option('remove')) {
            return $this->remove($registry, $institute);
        }

        $profile = $this->argument('profile');

        if ($profile === null) {
            $this->error('Name a profile, or pass --remove. Available: ' . implode(', ', $this->profileKeys($registry)) . '.');

            return self::FAILURE;
        }

        return $this->assign($registry, $institute, (string) $profile);
    }

    private function showCurrent(EsoFlowRegistry $registry): int
    {
        $this->line('');
        $this->line('  <fg=cyan>Flow profiles</>');
        $this->table(
            ['Profile', 'Label', 'Default', 'Institutes using it'],
            array_map(static fn (array $p): array => [
                $p['profile_key'],
                $p['label'],
                $p['is_default'] ? 'yes' : '',
                (string) $p['institutes'],
            ], $registry->catalogue())
        );

        $assignments = DB::table('pal_flow_assignments as a')
            ->join('pal_flow_profiles as p', 'p.id', '=', 'a.profile_id')
            ->select('a.sub_institute_id', 'p.profile_key', 'a.updated_at')
            ->orderBy('a.sub_institute_id')
            ->get();

        if ($assignments->isEmpty()) {
            $this->line('  <fg=gray>No institute is assigned. Every one of them resolves the default profile,</>');
            $this->line('  <fg=gray>which is what they were being served before any of this existed.</>');
            $this->line('');

            return self::SUCCESS;
        }

        $this->line('  <fg=cyan>Assignments</>');
        $this->table(
            ['Institute', 'Profile', 'Since'],
            $assignments->map(static fn ($a): array => [
                (string) $a->sub_institute_id,
                (string) $a->profile_key,
                (string) $a->updated_at,
            ])->all()
        );

        return self::SUCCESS;
    }

    private function assign(EsoFlowRegistry $registry, int $institute, string $profile): int
    {
        $available = $this->profileKeys($registry);

        if (! in_array($profile, $available, true)) {
            $this->error("Unknown profile '{$profile}'. Available: " . implode(', ', $available) . '.');

            return self::FAILURE;
        }

        $before = $registry->assignedProfileKey($institute);
        $wasOn = $before ?? 'the default';

        if ($before === $profile) {
            $this->info("  Institute {$institute} is already on '{$profile}'. Nothing to do.");

            return self::SUCCESS;
        }

        $students = (int) DB::table('tblstudent')->where('sub_institute_id', $institute)->count();

        $this->line('');
        $this->line("  Institute <fg=cyan>{$institute}</>: <fg=yellow>{$wasOn}</> -> <fg=green>{$profile}</>");
        $this->line("  Students in this institute: {$students}");
        $this->line('');
        $this->line('  <fg=gray>Learners already part-way through a concept keep the flow they started</>');
        $this->line('  <fg=gray>under. New concepts pick this one up.</>');
        $this->line('');

        if (! $this->option('force') && ! $this->confirm('Apply this?', false)) {
            $this->line('  Cancelled. Nothing changed.');

            return self::SUCCESS;
        }

        try {
            $registry->assign($institute, $profile);
        } catch (Throwable $e) {
            $this->error('  ' . $e->getMessage());

            return self::FAILURE;
        }

        $plan = app(EsoFlowResolver::class)->resolve($institute);

        $this->info("  Done. Institute {$institute} now runs '{$plan->profileKey()}' [" . implode(' > ', $plan->phaseOrder()) . '].');
        $this->line("  <fg=gray>Undo with: php artisan pal:flow-assign {$institute} --remove</>");
        $this->line('');

        return self::SUCCESS;
    }

    private function remove(EsoFlowRegistry $registry, int $institute): int
    {
        $before = $registry->assignedProfileKey($institute);

        if ($before === null) {
            $this->info("  Institute {$institute} has no assignment. It already resolves the default.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Remove institute {$institute} from '{$before}' and return it to the default?", false)) {
            $this->line('  Cancelled. Nothing changed.');

            return self::SUCCESS;
        }

        $registry->unassign($institute);

        $plan = app(EsoFlowResolver::class)->resolve($institute);

        $this->info("  Done. Institute {$institute} is back on '{$plan->profileKey()}'.");

        return self::SUCCESS;
    }

    /** @return array<int, string> */
    private function profileKeys(EsoFlowRegistry $registry): array
    {
        return array_map(static fn (array $p): string => $p['profile_key'], $registry->catalogue());
    }
}
