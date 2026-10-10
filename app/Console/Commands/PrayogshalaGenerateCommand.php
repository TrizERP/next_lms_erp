<?php

namespace App\Console\Commands;

use App\Jobs\GeneratePrayogshalaActivityJob;
use App\Services\lms\Prayogshala\PrayogshalaGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bounded, resumable Prayogshala generation over any slice of the curriculum.
 *
 *   php artisan prayogshala:generate --tenant=1 --standard=9 --subject=Science --chapter=8592 --limit=10
 *   php artisan prayogshala:generate --tenant=1 --standard=9 --dry-run          (coverage only, no AI)
 *   php artisan prayogshala:generate --tenant=1 --standard=9 --limit=25 --queue (one job per topic)
 *
 * Selection is by real ids/names from standard / subject / chapter_master / topic_master; nothing
 * is listed here. A topic that already has an activity is skipped, so a re-run continues where the
 * last one stopped. --limit bounds every run (default 10, hard cap 200) so a typo cannot start an
 * unbounded run of paid AI calls. --regenerate replaces existing content and so needs --topic.
 *
 * --per-chapter=3 is the "every chapter gets 2-3 different activities" mode: for each chapter it counts the
 * activities that already exist and nominates only enough extra topics to reach N, choosing the topics with
 * the most concepts (the most material to build a faithful activity from). Run it again to continue.
 */
class PrayogshalaGenerateCommand extends Command
{
    protected $signature = 'prayogshala:generate
        {--tenant= : Institute (sub_institute_id) that owns the curriculum and the activities}
        {--standard= : standard.id or standard.name}
        {--subject= : subject.id or subject.subject_name}
        {--chapter= : chapter_master.id}
        {--topic= : topic_master.id}
        {--per-chapter= : Aim for N activities in every selected chapter (1-5): picks the richest topics (most concepts) that have none yet}
        {--limit=10 : Max topics to generate this run (1-200)}
        {--queue : Dispatch one job per topic instead of generating inline}
        {--regenerate : Replace the activity of --topic}
        {--dry-run : Report coverage and source sufficiency; make no AI call}';

    protected $description = 'Generate one Prayogshala activity per topic from the LMS curriculum (bounded, idempotent).';

    public function handle(PrayogshalaGenerator $generator): int
    {
        $tenant = (int) $this->option('tenant');
        if ($tenant < 1) {
            $this->error('--tenant is required.');

            return self::FAILURE;
        }
        $limit = max(1, min(200, (int) $this->option('limit')));
        $regenerate = (bool) $this->option('regenerate');
        if ($regenerate && ! $this->option('topic')) {
            $this->error('--regenerate replaces content, so it needs an explicit --topic.');

            return self::FAILURE;
        }

        $query = DB::table('topic_master as t')
            ->join('chapter_master as ch', 'ch.id', '=', 't.chapter_id')
            ->where('ch.sub_institute_id', $tenant)
            ->orderBy('ch.standard_id')->orderBy('ch.subject_id')->orderBy('ch.sort_order')->orderBy('ch.id')
            ->orderBy('t.topic_sort_order')->orderBy('t.id')
            ->select('t.id', 't.name', 'ch.id as chapter_id');

        if ($std = $this->option('standard')) {
            $query->whereIn('ch.standard_id', DB::table('standard')->where('id', is_numeric($std) ? $std : 0)->orWhere('name', $std)->pluck('id'));
        }
        if ($sub = $this->option('subject')) {
            $query->whereIn('ch.subject_id', DB::table('subject')->where('id', is_numeric($sub) ? $sub : 0)->orWhere('subject_name', $sub)->pluck('id'));
        }
        if ($this->option('chapter')) {
            $query->where('ch.id', (int) $this->option('chapter'));
        }
        if ($this->option('topic')) {
            $query->where('t.id', (int) $this->option('topic'));
        }

        $topics = $query->get();
        if ($topics->isEmpty()) {
            $this->warn('No topics match the selection.');

            return self::SUCCESS;
        }

        $done = DB::table('lms_prayogshala_activity')->where('sub_institute_id', $tenant)->whereNull('deleted_at')
            ->whereIn('topic_id', $topics->pluck('id'))
            ->get(['topic_id', 'lab_config', 'generation_status'])->keyBy('topic_id');

        $pending = $topics->filter(function ($t) use ($done, $regenerate) {
            $row = $done->get($t->id);

            return $regenerate || ! $row || $row->lab_config === null
                || in_array($row->generation_status, ['failed', 'needs_content', 'generating'], true);
        })->values();

        $withActivity = $topics->count() - $pending->count();
        $perChapter = (int) $this->option('per-chapter');
        if ($perChapter > 0) {
            $perChapter = min(5, $perChapter);
            $conceptCount = DB::table('lms_concept')->whereIn('topic_id', $pending->pluck('id'))
                ->selectRaw('topic_id, count(*) as n')->groupBy('topic_id')->pluck('n', 'topic_id');
            $have = DB::table('lms_prayogshala_activity')->where('sub_institute_id', $tenant)->whereNull('deleted_at')
                ->whereNotNull('lab_config')->whereIn('chapter_id', $topics->pluck('chapter_id')->unique())
                ->selectRaw('chapter_id, count(*) as n')->groupBy('chapter_id')->pluck('n', 'chapter_id');
            $order = $topics->pluck('id')->flip();
            $pending = $pending->groupBy('chapter_id')->flatMap(function ($group, $chapterId) use ($perChapter, $conceptCount, $have) {
                $need = max(0, $perChapter - (int) ($have[$chapterId] ?? 0));

                return $group->sortByDesc(fn ($t) => (int) ($conceptCount[$t->id] ?? 0))->take($need);
            })->sortBy(fn ($t) => $order[$t->id])->values();
        }

        $this->info(sprintf('%d topics selected, %d already have an activity, %d to generate (limit %d).',
            $topics->count(), $withActivity, $pending->count(), $limit));

        $counts = [];
        foreach ($pending->take($limit) as $t) {
            if ($this->option('dry-run')) {
                $ctx = $generator->context((int) $t->id, $tenant);
                $verdict = $ctx === null ? 'not visible' : ($ctx['sufficient'] ? 'enough source' : 'NEEDS CONTENT');
                $this->line(sprintf('  #%d %s - %s (%d concepts, %d chars of chapter text)', $t->id, $t->name, $verdict,
                    count($ctx['concepts'] ?? []), $ctx['source_refs']['excerpt_chars'] ?? 0));
                $counts[$verdict] = ($counts[$verdict] ?? 0) + 1;
                continue;
            }
            if ($this->option('queue')) {
                GeneratePrayogshalaActivityJob::dispatch((int) $t->id, $tenant, null, $regenerate);
                $counts['queued'] = ($counts['queued'] ?? 0) + 1;
                continue;
            }
            $r = $generator->generate((int) $t->id, $tenant, null, $regenerate);
            $counts[$r['outcome']] = ($counts[$r['outcome']] ?? 0) + 1;
            $this->line(sprintf('  #%d %s -> %s%s', $t->id, $t->name, $r['outcome'], in_array($r['outcome'], ['failed', 'needs_content'], true) ? ': ' . $r['message'] : ''));
        }

        $this->info('Result: ' . json_encode($counts));

        return self::SUCCESS;
    }
}
