<?php

namespace App\Console\Commands\LMS;

use App\Services\StudyDeck\Story\StoryTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Register the reusable "Story-Based Branching Scenario" template in the existing Template Management (`template_master`).
 *
 * One row, module `study_deck`, holding the design document (StoryTemplate::html()): its configuration and the print markup the
 * static PowerPoint/PDF fallback follows. It is chapter-independent. Storing it does not change the student player (which is
 * bound to the template by `interaction.template`, not by this row); editing its configuration in Template Management changes the
 * size and wording limits the validator applies to NEW lessons, and nothing already stored.
 *
 * Idempotent and conservative: an existing row for the school is never overwritten (it may have been edited); `--refresh` replaces
 * the design document only when the stored one is still this template's own and has the same key. `--dry-run` writes nothing.
 * Undo: `delete from template_master where id = <printed id>` (the only row this command ever writes).
 */
class SeedStoryTemplateCommand extends Command
{
    protected $signature = 'lms:seed-story-template {--tenant=1 : sub_institute_id that owns the row (1 = the platform copy every school uses)} {--refresh : replace the design document of the existing row} {--dry-run}';

    protected $description = 'Register the Story-Based Branching Scenario template in Template Management';

    public function handle(): int
    {
        $tenant = (int) $this->option('tenant');
        $dry = (bool) $this->option('dry-run');
        $existing = DB::table('template_master')
            ->where('sub_institute_id', $tenant)->where('module_name', StoryTemplate::MODULE)->where('title', StoryTemplate::NAME)
            ->orderBy('id')->first(['id', 'html_content', 'status']);

        if ($existing !== null && !$this->option('refresh')) {
            $this->info("Already registered (template_master id {$existing->id}); nothing changed.");

            return self::SUCCESS;
        }
        if ($existing !== null) {
            if (StoryTemplate::parse((string) $existing->html_content) === null) {
                $this->error("Row {$existing->id} does not hold this template's design document, so it is not replaced.");

                return self::FAILURE;
            }
            $this->line(($dry ? 'Would refresh' : 'Refreshing') . " the design document of row {$existing->id}.");
            if (!$dry) {
                DB::table('template_master')->where('id', $existing->id)->update(['html_content' => StoryTemplate::html(), 'status' => 1]);
            }

            return self::SUCCESS;
        }

        $this->line(($dry ? 'Would register' : 'Registering') . ' "' . StoryTemplate::NAME . '" for school ' . $tenant . ' (module ' . StoryTemplate::MODULE . ', version ' . StoryTemplate::VERSION . ').');
        if ($dry) {
            return self::SUCCESS;
        }
        $id = DB::table('template_master')->insertGetId([
            'sub_institute_id' => $tenant, 'module_name' => StoryTemplate::MODULE, 'title' => StoryTemplate::NAME,
            'html_content' => StoryTemplate::html(), 'status' => 1, 'created_by' => null,
        ]);
        $this->info("Registered: template_master id $id.");

        return self::SUCCESS;
    }
}
