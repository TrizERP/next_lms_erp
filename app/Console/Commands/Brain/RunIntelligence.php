<?php

namespace App\Console\Commands\Brain;

use App\Brain\Intelligence\IntelligencePipeline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-runs the Brain intelligence loop against the live vivek_erp data.
 *
 * This is the "data refresh" mechanism: LMS rows change continuously, and a
 * signal raised last night is only true until someone assigns the department
 * head it complained about. The pipeline is idempotent, so this is safe to
 * schedule as often as the institute wants its intelligence to be current.
 *
 *   php artisan brain:intelligence            # every institute with staff
 *   php artisan brain:intelligence --tenant=1 # one institute
 */
class RunIntelligence extends Command
{
    protected $signature = 'brain:intelligence
        {--tenant= : sub_institute_id to run for; omit to run every institute that has staff}';

    protected $description = 'Run the Enterprise Brain intelligence loop over the live LMS data';

    public function handle(): int
    {
        $tenants = $this->option('tenant')
            ? [(string) $this->option('tenant')]
            : DB::table('tbluser')->whereNotNull('sub_institute_id')
                ->distinct()->orderBy('sub_institute_id')->pluck('sub_institute_id')
                ->map(fn ($id) => (string) $id)->all();

        if ($tenants === []) {
            $this->warn('No institutes found.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenantId) {
            $this->info("── institute {$tenantId}");
            $result = (new IntelligencePipeline($tenantId))->run();

            $this->line(sprintf(
                '   rules evaluated %d · signals created %d · refreshed %d',
                $result['rules']['evaluated'],
                $result['rules']['signalsCreated'],
                $result['rules']['signalsRefreshed'],
            ));
            $this->line(sprintf(
                '   cases %d · hypotheses %d · reasoning steps %d · recommendations %d · undetermined %d',
                $result['reasoning']['cases'],
                $result['reasoning']['hypotheses'],
                $result['reasoning']['steps'],
                $result['reasoning']['recommendations'],
                $result['reasoning']['undetermined'],
            ));
            $this->line(sprintf(
                '   knowledge assets written %d · updated %d · %dms',
                $result['knowledge']['written'],
                $result['knowledge']['updated'],
                $result['elapsedMs'],
            ));

            foreach ($result['rules']['outcomes'] as $outcome) {
                if (! empty($outcome['error'])) {
                    $this->error(sprintf('   ! %s: %s', $outcome['rule'], $outcome['error']));
                }
            }
        }

        return self::SUCCESS;
    }
}
