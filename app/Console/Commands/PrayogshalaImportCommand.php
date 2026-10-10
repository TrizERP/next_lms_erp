<?php

namespace App\Console\Commands;

use App\Services\lms\Prayogshala\PrayogshalaGenerator;
use Illuminate\Console\Command;

/**
 * Files activities written outside the AI call (for example by an authoring session) against their
 * topics. Each entry is a JSON object with a `topic_id` plus the same fields the generator produces.
 * It uses PrayogshalaGenerator::storeAuthored, so every entry gets the same topic-visibility check,
 * one-activity-per-topic rule, config validation and `review` status as a generated activity.
 *
 *   php artisan prayogshala:import labs.json --tenant=1 --by="Claude Code session"
 *
 * An entry for a topic that already has an activity is skipped, never overwritten.
 */
class PrayogshalaImportCommand extends Command
{
    protected $signature = 'prayogshala:import
        {file : JSON file holding a list of activities}
        {--tenant= : Institute that owns the curriculum and the activities}
        {--by=import : Who wrote them (recorded as the generation model)}';

    protected $description = 'Import authored Prayogshala activities (validated, one per topic, status review).';

    public function handle(PrayogshalaGenerator $generator): int
    {
        $tenant = (int) $this->option('tenant');
        $path = (string) $this->argument('file');
        if ($tenant < 1 || ! is_file($path)) {
            $this->error('A --tenant and an existing file are required.');

            return self::FAILURE;
        }
        $items = json_decode((string) file_get_contents($path), true);
        if (! is_array($items) || ! array_is_list($items)) {
            $this->error('The file must contain a JSON list.');

            return self::FAILURE;
        }

        $counts = [];
        foreach ($items as $item) {
            $topicId = (int) ($item['topic_id'] ?? 0);
            unset($item['topic_id']);
            $r = $generator->storeAuthored($topicId, $tenant, null, $item, (string) $this->option('by'));
            $counts[$r['outcome']] = ($counts[$r['outcome']] ?? 0) + 1;
            $this->line(sprintf('  topic #%d -> %s%s', $topicId, $r['outcome'], $r['outcome'] === 'created' ? '' : ': ' . $r['message']));
        }
        $this->info('Result: ' . json_encode($counts));

        return self::SUCCESS;
    }
}
