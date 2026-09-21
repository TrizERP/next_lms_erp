<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Physical pre-run snapshot of every column this pipeline may touch.
 *
 * None of lms_question_master, content_master or lms_teacher_resource
 * has an updated_at column, so without this there is no forensic record
 * of a run outside our own audit table. remap:verify proves the audit
 * trail is COMPLETE by diffing live data against this snapshot, which
 * is a check the audit table cannot perform on itself.
 *
 * Note: this app installs a DB::listen guard (AppServiceProvider) that
 * throws on DROP TABLE and TRUNCATE TABLE. Refreshing a snapshot
 * therefore uses DELETE + INSERT ... SELECT rather than dropping.
 */
class RemapSnapshot extends Command
{
    protected $signature = 'remap:snapshot
                            {--run= : run id these snapshots belong to}
                            {--force : refresh existing snapshot tables in place}';

    protected $description = 'Create bak_* snapshot tables for the std-10 rows in scope';

    public function handle(): int
    {
        $runId = $this->option('run');

        if (!$runId) {
            $this->error('--run is required so the snapshot can be tied to a run');
            return self::FAILURE;
        }

        $scope    = config('remap.scope');
        $tenant   = (int) $scope['sub_institute_id'];
        $standard = (int) $scope['standard_id'];
        $short    = substr(str_replace('-', '', $runId), 0, 8);
        $made     = [];

        foreach (config('remap.entities') as $key => $entity) {
            $table  = $entity['table'];
            $backup = "bak_{$table}_{$short}";
            $cols   = $entity['snapshot_columns'];

            foreach ($cols as $c) {
                if (!Schema::hasColumn($table, $c)) {
                    $this->error("{$table}.{$c} does not exist; refusing to snapshot a partial row");
                    return self::FAILURE;
                }
            }

            $select = implode(', ', array_map(fn ($c) => "`{$c}`", $cols));
            // Values are config-sourced and cast to int. They are inlined
            // because MySQL does not bind placeholders inside
            // CREATE TABLE ... AS SELECT -- a parameterised WHERE there
            // silently matches nothing and yields an empty snapshot.
            $where  = "WHERE sub_institute_id = {$tenant} AND standard_id = {$standard}";

            if (Schema::hasTable($backup)) {
                if (!$this->option('force')) {
                    $made[] = [$key, $backup, 'kept', DB::table($backup)->count()];
                    continue;
                }

                DB::table($backup)->delete();
                $state = 'refreshed';
            } else {
                // Structure only. The rows are inserted below through
                // affectingStatement so the copied count is checked
                // rather than assumed -- DB::statement returns a bool
                // and gave us a silently empty snapshot once already.
                DB::statement("CREATE TABLE `{$backup}` AS SELECT {$select} FROM `{$table}` WHERE 1 = 0");

                try {
                    DB::statement("ALTER TABLE `{$backup}` ADD INDEX `idx_id` (`id`)");
                } catch (\Throwable $e) {
                    $this->warn("could not index {$backup}: " . $e->getMessage());
                }

                $state = 'created';
            }

            $inserted = DB::affectingStatement(
                "INSERT INTO `{$backup}` ({$select}) SELECT {$select} FROM `{$table}` {$where}"
            );

            $expected = DB::table($table)
                ->where('sub_institute_id', $tenant)
                ->where('standard_id', $standard)
                ->count();

            if ($inserted !== $expected) {
                $this->error("{$backup}: copied {$inserted} rows but source has {$expected}");
                return self::FAILURE;
            }

            $copied = DB::table($backup)->count();

            if ($copied === 0) {
                $this->error("{$backup} is empty; refusing to proceed without a real snapshot");
                return self::FAILURE;
            }

            $made[] = [$key, $backup, $state, $copied];
        }

        $this->table(['entity', 'snapshot table', 'state', 'rows'], $made);

        DB::table('lms_remap_run')->where('run_id', $runId)->update(['notes' => 'snapshot suffix ' . $short]);

        $this->info("snapshot suffix: {$short}");
        $this->line("next: php artisan remap:apply --run={$runId}");

        return self::SUCCESS;
    }
}
