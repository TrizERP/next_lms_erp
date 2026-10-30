<?php

namespace App\Console\Commands;

use App\Services\lms\Simulation\SimulationStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * READ-ONLY consistency check of Simulation Learning. Prints PASS or FAIL for each check; the exit code is 1 if any check fails, so a deploy step or
 * a cron can rely on it.
 *
 *   php artisan lms:simulations-reconcile
 *   php artisan lms:simulations-reconcile --check-bucket        (also re-hashes every registered object on the disk)
 *
 * CHECKS
 *   1. simulations per tenant: counted and listed; FAIL for a simulation owned by no tenant or by a tenant that may not own one (only the platform
 *      tenants, config lms_content.platform_sub_institute_ids, author).
 *   2. no orphan links: every link's simulation row and concept row exist.
 *   3. every link's standard, subject, chapter and topic equal its concept's (the link copies them; this finds a copy that has drifted).
 *   4. every link's topic belongs to its chapter (topic_master.chapter_id).
 *   5. every file a current specification names has a live registry row.
 *   6. (--check-bucket) every such file is on the disk and its bytes hash to the registered sha256.
 * It writes nothing: no database row, no object.
 */
class SimulationsReconcileCommand extends Command
{
    protected $signature = 'lms:simulations-reconcile
        {--check-bucket : Also read every registered asset object back and re-hash it (slower; reads the disk)}';

    protected $description = 'Read-only check of simulations, links and assets; exits non-zero when any check fails.';

    private int $failed = 0;

    public function handle(SimulationStore $store): int
    {
        $this->failed = 0;   // a command object can be run more than once in a process (tests, a scheduler)
        $platform = array_map('intval', (array) config('lms_content.platform_sub_institute_ids', [1]));

        // 1. simulations per tenant
        $per = DB::table('lms_simulation')->whereNull('deleted_at')->selectRaw('sub_institute_id, count(*) as n')->groupBy('sub_institute_id')->get();
        $stray = $per->filter(fn ($r) => ! in_array((int) $r->sub_institute_id, $platform, true));
        $this->report('simulations per tenant', $stray->isEmpty(),
            $per->isEmpty() ? 'none yet' : $per->map(fn ($r) => 'tenant ' . ($r->sub_institute_id ?? 'NULL') . ': ' . $r->n)->implode(', '),
            $stray->map(fn ($r) => 'tenant ' . ($r->sub_institute_id ?? 'NULL') . ' owns ' . $r->n . ' simulation(s) but is not a platform tenant')->all());

        // 2. orphan links
        $noSim = DB::table('lms_simulation_concept as l')->leftJoin('lms_simulation as s', 's.id', '=', 'l.simulation_id')->whereNull('s.id')->pluck('l.id')->all();
        $noConcept = DB::table('lms_simulation_concept as l')->leftJoin('lms_concept as c', 'c.id', '=', 'l.concept_id')->whereNull('c.id')->pluck('l.id')->all();
        $this->report('no orphan links', $noSim === [] && $noConcept === [], DB::table('lms_simulation_concept')->count() . ' link(s) checked', array_merge(
            $noSim === [] ? [] : ['link(s) ' . implode(', ', $noSim) . ' point at a simulation that does not exist'],
            $noConcept === [] ? [] : ['link(s) ' . implode(', ', $noConcept) . ' point at a concept that does not exist'],
        ));

        // 3. the copies equal the concept's
        $drift = DB::table('lms_simulation_concept as l')->join('lms_concept as c', 'c.id', '=', 'l.concept_id')
            ->where(function ($w) {
                $w->whereColumn('l.standard_id', '!=', 'c.standard_id')
                    ->orWhereColumn('l.subject_id', '!=', 'c.subject_id')
                    ->orWhereColumn('l.chapter_id', '!=', 'c.chapter_id')
                    ->orWhere(function ($t) {
                        $t->whereNull('l.topic_id')->whereNotNull('c.topic_id');
                    })->orWhere(function ($t) {
                        $t->whereNotNull('l.topic_id')->whereNull('c.topic_id');
                    })->orWhere(function ($t) {
                        $t->whereNotNull('l.topic_id')->whereNotNull('c.topic_id')->whereColumn('l.topic_id', '!=', 'c.topic_id');
                    });
            })
            ->select('l.id', 'l.simulation_id', 'l.concept_id', 'l.chapter_id as l_chapter', 'c.chapter_id as c_chapter', 'l.topic_id as l_topic', 'c.topic_id as c_topic')->get();
        $this->report('links match their concept (standard, subject, chapter, topic)', $drift->isEmpty(), 'every link compared with its concept',
            $drift->map(fn ($r) => "link {$r->id} (simulation {$r->simulation_id}, concept {$r->concept_id}): chapter {$r->l_chapter} vs {$r->c_chapter}, topic " . ($r->l_topic ?? 'NULL') . ' vs ' . ($r->c_topic ?? 'NULL'))->all());

        // 4. a link's topic is in its chapter
        $outside = DB::table('lms_simulation_concept as l')->join('topic_master as t', 't.id', '=', 'l.topic_id')
            ->whereColumn('t.chapter_id', '!=', 'l.chapter_id')->select('l.id', 'l.topic_id', 'l.chapter_id', 't.chapter_id as topic_chapter')->get();
        $this->report('link topics belong to the link chapter', $outside->isEmpty(), 'checked',
            $outside->map(fn ($r) => "link {$r->id}: topic {$r->topic_id} is in chapter {$r->topic_chapter}, the link says chapter {$r->chapter_id}")->all());

        // 5. every file a current spec names is registered
        $named = [];
        foreach (DB::table('lms_simulation')->whereNull('deleted_at')->get() as $sim) {
            $json = $store->specJson($sim);
            foreach ($json === null ? [] : $store->assetHashes(json_decode($json)) as $hash) {
                $named[$hash][] = $sim;
            }
        }
        $unregistered = [];
        $registered = [];
        foreach ($named as $hash => $sims) {
            foreach ($sims as $sim) {
                $rows = $store->assetRows($sim, [$hash]);
                if (! $rows->has($hash)) {
                    $unregistered[] = "{$hash} (named by simulation {$sim->id} \"{$sim->slug}\")";
                } else {
                    $registered[$hash] = $rows->get($hash);
                }
            }
        }
        $this->report('every file a current spec names is registered', $unregistered === [], count($named) . ' distinct file(s) named', $unregistered);

        // 6. the bucket
        if ($this->option('check-bucket')) {
            $bad = [];
            $disk = Storage::disk(SimulationStore::ASSET_DISK);
            foreach ($registered as $hash => $row) {
                try {
                    $stream = $disk->exists($row->storage_path) ? $disk->readStream($row->storage_path) : null;
                    if (! is_resource($stream)) {
                        $bad[] = "{$hash}: {$row->storage_path} is not on the disk";

                        continue;
                    }
                    $context = hash_init('sha256');
                    hash_update_stream($context, $stream);
                    fclose($stream);
                    $actual = hash_final($context);
                    if ($actual !== $row->sha256) {
                        $bad[] = "{$hash}: {$row->storage_path} hashes to {$actual}";
                    }
                } catch (\Throwable $e) {
                    $bad[] = "{$hash}: could not be read (" . $e->getMessage() . ')';
                }
            }
            $this->report('registered objects are on the disk and hash to their sha256', $bad === [], count($registered) . ' object(s) re-hashed', $bad);
        }

        $this->line($this->failed === 0 ? 'ALL CHECKS PASSED' : "{$this->failed} CHECK(S) FAILED");

        return $this->failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param  list<string>  $problems */
    private function report(string $check, bool $ok, string $detail, array $problems = []): void
    {
        if (! $ok) {
            $this->failed++;
        }
        $this->line(($ok ? 'PASS' : 'FAIL') . '  ' . $check . '  (' . $detail . ')');
        foreach ($problems as $problem) {
            $this->line('        ' . $problem);
        }
    }
}
