<?php

namespace App\Console\Commands\LMS;

use App\Services\lms\Content\ContentProvenanceService;
use App\Services\lms\Content\LmsContentVocabulary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Phase A1 - backfill ownership/provenance over the existing content estate.
 *
 * Tracker "Content & LMS Architecture" row 4 says to add the field "now, before more
 * content is authored by more sources". Adding the column is the easy half; the hard
 * half is that 96,477 rows already exist with no provenance recorded anywhere. This
 * command derives it from the signals that DO exist.
 *
 * DERIVATION RULES, in order. First match wins.
 *
 *   1. Platform tenant  -> ownership = 'platform'
 *      content_master.sub_institute_id IN config(lms_content.platform_sub_institute_ids).
 *      Measured 2026-09-07: 16,379 of 31,385 content_master rows sit on tenant 1.
 *
 *   2. Teacher profile  -> ownership = 'teacher'
 *      user_profile_name matches config(lms_content.teacher_profile_patterns).
 *      This fires on very few rows - user_profile_name is populated on only 54 of
 *      31,385 - and that is fine. See rule 3.
 *
 *   3. Otherwise        -> ownership = 'school'
 *      A tenant-owned row whose author cannot be identified. Recording 'school' is
 *      the honest answer: it is certainly not platform content, and claiming a
 *      specific teacher authored it would be inventing a fact.
 *
 * authoring_mode is derived from content_master.source, which is populated on only
 * 45 of 31,385 rows (Gamma AI 39, Claude AI 5, Uploaded 1). Everything else is
 * recorded as 'imported' - true by construction, since those rows predate any
 * authoring flow that could have recorded a mode.
 *
 * derived_from_entity_id is ALWAYS left NULL here. Whether a school item was created
 * as an extension of a platform item is not reconstructible after the fact, and
 * guessing it would corrupt the "layer on top, never a fork" overlay that
 * ContentOwnershipDecorator builds. It is only ever written forward, by the authoring
 * service in Phase A3.
 *
 * IDEMPOTENT: writes go through ContentProvenanceService::recordMany(), a chunked
 * INSERT ... ON DUPLICATE KEY UPDATE on (entity_type, entity_id,
 * owner_sub_institute_id). Re-running rewrites the same values and changes nothing.
 */
class BackfillContentProvenanceCommand extends Command
{
    protected $signature = 'lms:backfill-content-provenance
        {--estate=all : all | content | teacher_resource | question | h5p}
        {--tenant= : restrict to one sub_institute_id}
        {--batch=500 : rows per chunk}
        {--limit=0 : stop after N rows per estate (0 = all)}
        {--dry-run : classify and report, write nothing}';

    protected $description = 'Backfill content ownership (platform/school/teacher) into lms_content_provenance';

    public function __construct(
        private LmsContentVocabulary $vocabulary,
        private ContentProvenanceService $provenance
    ) {
        parent::__construct();
    }

    /**
     * Which column carries the author signal in each estate.
     *
     * lms_teacher_resource and lms_question_master have created_by but no
     * user_profile_name, so rule 2 cannot fire there and every tenant-owned row
     * lands on 'school'. Stated here rather than discovered at runtime.
     */
    private const ESTATES = [
        'content' => [
            'table'   => 'content_master',
            'profile' => 'user_profile_name',
            'source'  => 'source',
        ],
        'teacher_resource' => [
            'table'   => 'lms_teacher_resource',
            'profile' => null,
            'source'  => null,
        ],
        'question' => [
            'table'   => 'lms_question_master',
            'profile' => null,
            'source'  => null,
        ],
        // Phase A2: H5P items surface in the content list as a format, so they need
        // the same ownership layer as everything else. Only h5p_scenarios is
        // populated today (11 rows); the other two H5P tables are empty.
        'h5p' => [
            'table'   => 'h5p_scenarios',
            'profile' => null,
            'source'  => null,
        ],
    ];

    public function handle(): int
    {
        $estate = (string) $this->option('estate');
        $estates = $estate === 'all' ? array_keys(self::ESTATES) : [$estate];

        foreach ($estates as $key) {
            if (! isset(self::ESTATES[$key])) {
                $this->error("Unknown estate \"{$key}\". Use: all, " . implode(', ', array_keys(self::ESTATES)));

                return self::FAILURE;
            }
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN - classifying only, nothing will be written.');
        }

        $grand = [];

        foreach ($estates as $key) {
            $this->line('');
            $this->info("== {$key} ({$this->tableFor($key)}) ==");
            $grand[$key] = $this->backfillEstate($key);
        }

        $this->line('');
        $this->info('== summary ==');
        $rows = [];
        foreach ($grand as $key => $counts) {
            $rows[] = [
                $key,
                $counts['seen'],
                $counts['platform'],
                $counts['school'],
                $counts['teacher'],
                $counts['written'],
                $counts['skipped'],
                $counts['failed'],
            ];
        }
        $this->table(
            ['estate', 'seen', 'platform', 'school', 'teacher', 'written', 'skipped', 'failed'],
            $rows
        );

        $failed = array_sum(array_column($grand, 'failed'));
        if ($failed > 0) {
            $this->error("{$failed} row(s) could not be classified - see the warnings above.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function tableFor(string $estate): string
    {
        return self::ESTATES[$estate]['table'];
    }

    /**
     * @return array{seen:int,platform:int,school:int,teacher:int,written:int,skipped:int,failed:int}
     */
    private function backfillEstate(string $estate): array
    {
        $spec = self::ESTATES[$estate];
        $table = $spec['table'];

        $counts = [
            'seen' => 0, 'platform' => 0, 'school' => 0, 'teacher' => 0,
            'written' => 0, 'skipped' => 0, 'failed' => 0,
        ];

        $columns = ['id', 'sub_institute_id'];
        if (DB::getSchemaBuilder()->hasColumn($table, 'created_by')) {
            $columns[] = 'created_by';
        }
        if ($spec['profile'] !== null) {
            $columns[] = $spec['profile'];
        }
        if ($spec['source'] !== null) {
            $columns[] = $spec['source'];
        }

        $query = DB::table($table)->select($columns)->orderBy('id');

        if ($tenant = $this->option('tenant')) {
            $query->where('sub_institute_id', $tenant);
        }

        $limit = (int) $this->option('limit');
        $batch = max(1, (int) $this->option('batch'));
        $dryRun = (bool) $this->option('dry-run');

        $total = (clone $query)->count();
        $target = $limit > 0 ? min($limit, $total) : $total;
        $this->line("  {$total} row(s) in scope" . ($limit > 0 ? ", processing {$target}" : ''));

        $pending = [];
        $stop = false;

        // Chunked by id rather than offset: the estate is live, and an offset walk
        // over a table other people are inserting into skips rows.
        $query->chunkById($batch, function ($rows) use (&$counts, &$pending, $estate, $spec, $dryRun, $limit, &$stop) {
            foreach ($rows as $row) {
                if ($limit > 0 && $counts['seen'] >= $limit) {
                    $stop = true;

                    return false;
                }

                $counts['seen']++;

                $tenantId = (int) ($row->sub_institute_id ?? 0);

                if ($tenantId <= 0) {
                    // Undecidable tenancy - CONTENT LAW C3 says reject, not default.
                    $counts['skipped']++;
                    continue;
                }

                $ownership = $this->classify($row, $spec, $tenantId);
                $counts[$ownership]++;

                if ($dryRun) {
                    continue;
                }

                $pending[] = [
                    'entity_type'            => $estate,
                    'entity_id'              => (int) $row->id,
                    'owner_sub_institute_id' => $tenantId,
                    'ownership'              => $ownership,
                    'authored_by_user_id'    => isset($row->created_by) && is_numeric($row->created_by)
                        ? (int) $row->created_by
                        : null,
                    'authored_by_profile'    => $spec['profile'] ? (($row->{$spec['profile']} ?: null)) : null,
                    'authoring_mode'         => $this->authoringMode($row, $spec),
                    'generation_source'      => $spec['source'] ? (($row->{$spec['source']} ?: null)) : null,
                    // Follows OWNERSHIP, not tenancy: a teacher-authored item sitting in
                    // the platform library is still tenant-visible, not global.
                    'visibility'             => $ownership === 'platform' ? 'global' : 'tenant',
                    'status'                 => 'active',
                ];
            }

            $this->flush($pending, $counts);
            $this->line(sprintf('  ... %d seen / %d written', $counts['seen'], $counts['written']));

            return ! $stop;
        }, 'id');

        $this->flush($pending, $counts);

        return $counts;
    }

    /**
     * Send one accumulated batch through the service and clear it.
     *
     * A failed chunk is retried row by row, so one bad row costs one row rather
     * than the whole batch - the batch exists for speed, not to hide errors.
     *
     * @param  list<array<string,mixed>>  $pending
     * @param  array<string,int>  $counts
     */
    private function flush(array &$pending, array &$counts): void
    {
        if ($pending === []) {
            return;
        }

        try {
            $counts['written'] += $this->provenance->recordMany($pending);
        } catch (Throwable $e) {
            $this->newLine();
            $this->warn('  batch rejected (' . $e->getMessage() . ') - retrying row by row');

            foreach ($pending as $row) {
                try {
                    $this->provenance->recordMany([$row]);
                    $counts['written']++;
                } catch (Throwable $inner) {
                    $counts['failed']++;
                    $this->warn(sprintf('  %s#%d: %s', $row['entity_type'], $row['entity_id'], $inner->getMessage()));
                }
            }
        }

        $pending = [];
    }

    /**
     * Rules 1-3 from the class docblock. First match wins.
     */
    private function classify(object $row, array $spec, int $tenantId): string
    {
        // AUTHOR SIGNAL FIRST, TENANT SECOND. Order corrected 2026-09-08.
        //
        // This originally tested the platform tenant first, which meant an explicit author
        // signal was discarded for every row on tenant 1 - i.e. for over half the estate.
        // The only two rows in content_master carrying the documented teacher marker
        // (user_profile_name 'LMS Teache', ids 53869/53870) both sit on tenant 1, so the
        // teacher rule below could never fire on the exact rows it was written for, and
        // "teacher = 0" looked like a fact about the data rather than about this ordering.
        //
        // An explicit author signal is now believed wherever it appears. Only when there
        // is none does the tenant decide.
        if ($spec['profile'] !== null) {
            $profile = strtolower((string) ($row->{$spec['profile']} ?? ''));
            foreach ((array) config('lms_content.teacher_profile_patterns', []) as $pattern) {
                if ($profile !== '' && str_contains($profile, strtolower((string) $pattern))) {
                    return 'teacher';
                }
            }
        }

        if ($this->vocabulary->isPlatformTenant($tenantId)) {
            return 'platform';
        }

        return 'school';
    }

    /**
     * content_master.source is the only authoring-mode signal in the estate, and it
     * is populated on 45 of 31,385 rows. Everything else is honestly 'imported'.
     */
    private function authoringMode(object $row, array $spec): string
    {
        if ($spec['source'] === null) {
            return 'imported';
        }

        $source = strtolower((string) ($row->{$spec['source']} ?? ''));

        if ($source === '') {
            return 'imported';
        }

        if (str_contains($source, 'upload')) {
            return 'upload';
        }

        // 'Gamma AI', 'Claude AI' - anything else non-blank came from a generator.
        return 'generate';
    }
}
