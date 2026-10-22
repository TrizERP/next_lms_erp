<?php

namespace App\Console\Commands\LMS;

use App\Services\ContentGenerationService;
use App\Services\StudyDeck\ClaudeApiCompleter;
use App\Services\StudyDeck\ClaudeCliCompleter;
use App\Services\StudyDeck\Contracts\Completer;
use App\Services\StudyDeck\StudyDeckService;
use App\Services\StudyDeck\StudyImageStores;
use App\Services\StudyDeck\TeachingMethodProfile;
use Illuminate\Console\Command;

/**
 * Generate a classroom study deck for one chapter and write a REVIEW BUNDLE.
 *
 * By default this READS the chapter and writes the deck's text files under
 * storage/app/study-deck/chapter-<id>/, plus the deck's pictures into the
 * study_deck_images table (they are kept in the database, never as files).
 * Nothing reaches content_master or the shared object store unless --store is
 * passed, and --store refuses a deck that failed validation. Pictures of a run
 * that is never stored are removed by `study-deck:images-prune`. --store hands the bundle it just wrote to
 * `lms:store-study-deck`, so what is stored is exactly what was written here
 * (and can be reviewed first: run without --store, look, then store).
 *
 * The bundle is laid out so the existing validator runs on it unchanged:
 *   php artisan lms:validate-content storage/app/study-deck/chapter-8592 --file=presentation.html
 */
class GenerateStudyDeckCommand extends Command
{
    protected $signature = 'lms:generate-study-deck
        {chapter : chapter_master.id}
        {--tenant=1 : sub_institute_id}
        {--executor= : api | cli (cli is dev-only; default from config claude.executor)}
        {--out= : bundle directory (default storage/app/study-deck/chapter-<id>)}
        {--teaching-method= : shape the deck for a teaching method (explicit_instruction, inquiry_based, problem_based, project_based, experiential, collaborative, socratic, flipped_classroom, differentiated_mastery, gamified); none keeps the default deck}
        {--template= : build the lesson with a reusable template instead of the slide planner (story_branching_scenario: a story with an act per topic, in the study-deck story player)}
        {--pictures-from= : with --template: a finished story bundle (a directory with deck.json and story.json); its text is NOT rewritten, and the scenes without a picture are given the best relevant ones (the picture pass). The result goes to --out; the source bundle is not changed}
        {--picture-plan-file= : with --pictures-from: a JSON file of picture plans written by a person who read the scenes ({"a1/s3": {"image_queries": [...], "show": "what the photograph must show", "picture_caption": "what to notice", "process_step": "evidence", "priority": 10}}). It is the whole plan: no model call is made to plan pictures}
        {--replan : with --pictures-from: plan the pictures again instead of reusing the plan an earlier picture pass saved (the failed searches it saved are still reused)}
        {--picture-seconds=300 : with --pictures-from: how long the picture pass may work, most important scenes first; scenes not reached stay pending and a later pass continues}
        {--min-slides=30}
        {--max-slides=35}
        {--per-concept=4 : most bank questions offered per concept}
        {--max-practice= : most bank questions placed in the whole deck (default: SlidePlanner::MAX_PRACTICE; raising it asks for practice spread through the lesson)}
        {--per-slide= : most bank questions on one slide (default: SlidePlanner::MAX_QUESTIONS_PER_SLIDE)}
        {--max-visuals= : most pictures or diagrams the deck may ask for (default: SlidePlanner::MAX_VISUALS)}
        {--max-scenarios= : most branching scenarios the deck may carry (default: InteractionPlanner::MAX_SCENARIOS)}
        {--replay : do not call the model: rebuild and re-validate the deck from the plan, slide text, pictures and interactions already in the bundle (after a validator or renderer fix)}
        {--recheck-images : with --replay: read the chosen photographs back and replace any that fail the picture rules (out of focus) by the diagram planned for the slide; no search, no model call}
        {--redraw-diagrams : with --replay: draw the diagrams of the deck again with the current DiagramRenderer (same words, same places; only the look changes)}
        {--images-from= : with --replay: a JSON file of chosen pictures to swap in (photographs by source, or figures drawn for the lesson); each faces the picture rules and its hotspots are moved to match (see AppServicesStudyDeckImageOverrides)}
        {--export-player= : copy deck.json (picture references only, no picture files) to this directory so the local student player can load the review bundle}
        {--store : on a passing validation, store THIS bundle (see lms:store-study-deck: writes to the DB and shared storage)}';

    protected $description = 'Generate a 30-35 slide classroom study deck from a chapter\'s LMS data';

    public function handle(ContentGenerationService $content): int
    {
        $chapterId = (int) $this->argument('chapter');
        $tenant = (int) $this->option('tenant');
        $executor = (string) ($this->option('executor') ?: config('claude.executor', 'api'));
        $dir = rtrim((string) ($this->option('out') ?: storage_path('app/study-deck/chapter-' . $chapterId)), '/\\');
        $store = (bool) $this->option('store');

        // A story lesson is always written by Gemini (the estate's own client and key pool): never the Anthropic key or the Claude CLI.
        $story = $this->option('template') === \App\Services\StudyDeck\Story\StoryTemplate::KEY;
        $this->line($story ? 'Model: <options=bold>gemini</> (story lessons always use Gemini)' : 'Executor: <options=bold>' . $executor . '</>' . ($executor === 'cli' ? ' (dev-only, tools disabled)' : ''));

        try {
            $replay = (bool) $this->option('replay');
            if (!$replay && $this->option('images-from')) {
                throw new RuntimeException('--images-from works with --replay (it changes the pictures of a deck that is already planned).');
            }
            $completer = $replay ? $this->noModel() : ($story ? $this->geminiCompleter($tenant) : $this->completer($executor, $content));
            // Pictures go straight into the database (study_deck_images) and the bundle carries only their
            // references: a run writes no picture file anywhere. Publishing is lms:store-study-deck's job.
            $images = StudyImageStores::database($tenant, $chapterId);
            $service = StudyDeckService::make($completer, $images);

            $method = $this->option('teaching-method') ?: null;
            if ($method !== null && !in_array($method, TeachingMethodProfile::keys(), true)) {
                throw new RuntimeException('--teaching-method must be one of: ' . implode(', ', TeachingMethodProfile::keys()) . '.');
            }
            $template = $this->option('template') ?: null;
            if ($template !== null && $template !== \App\Services\StudyDeck\Story\StoryTemplate::KEY) {
                throw new RuntimeException('--template must be: ' . \App\Services\StudyDeck\Story\StoryTemplate::KEY . '.');
            }
            if ($template !== null && $replay) {
                throw new RuntimeException('--template builds a new lesson; it does not combine with --replay.');
            }
            $pictureSource = (string) $this->option('pictures-from');
            if ($pictureSource !== '' && ($template === null || $replay)) {
                throw new RuntimeException('--pictures-from works with --template (a finished story bundle) and not with --replay.');
            }
            if ($pictureSource !== '' && realpath($pictureSource) === realpath($dir)) {
                throw new RuntimeException('--out must be a different directory from --pictures-from: the finished bundle is kept as it is.');
            }
            $options = [
                'template' => $template, 'tenant' => $tenant,
                ...($pictureSource !== '' ? $this->pictureOptions($pictureSource) + ['picture_seconds' => max(1, (int) $this->option('picture-seconds'))] : []),
                // Finished acts are kept between runs of the same chapter, so a failure late in the story is resumed, not redone.
                'story_checkpoint_dir' => $template !== null ? storage_path('app/study-deck/story-checkpoints/chapter-' . $chapterId . '-school-' . $tenant) : null,
                'teaching_method' => $method,
                'rejected_replies_dir' => $dir,
                'min_slides' => (int) $this->option('min-slides'),
                'max_slides' => (int) $this->option('max-slides'),
                'per_concept' => (int) $this->option('per-concept'),
                'limits' => array_filter([
                    'max_practice' => $this->option('max-practice') ?: null,
                    'questions_per_slide' => $this->option('per-slide') ?: null,
                    'max_visuals' => $this->option('max-visuals') ?: null,
                    'max_scenarios' => $this->option('max-scenarios') ?: null,
                ], fn ($v) => $v !== null),
            ];
            $say = fn ($stage, $msg) => $this->line(sprintf('  <fg=cyan>%-8s</> %s', $stage, $msg));

            if ($replay) {
                $saved = $this->savedBundle($dir);
                // The run's own limits unless this call names others: the deck is judged by the numbers it was planned under.
                $options['limits'] = $options['limits'] ?: $saved['limits'];
                $options['recheck_images'] = (bool) $this->option('recheck-images');
                $options['redraw_diagrams'] = (bool) $this->option('redraw-diagrams');
                if ($from = (string) $this->option('images-from')) {
                    if (!is_file($from)) {
                        throw new RuntimeException("--images-from: $from is not a file.");
                    }
                    $spec = json_decode((string) file_get_contents($from), true);
                    if (!is_array($spec)) {
                        throw new RuntimeException("--images-from: $from is not valid JSON.");
                    }
                    $options['image_overrides'] = ['spec' => $spec, 'base' => dirname($from)];
                }
                unset($saved['limits']);
                $result = $service->replayChapter($chapterId, $tenant, $saved, $options, $say);
            } else {
                $result = $service->generate($chapterId, $tenant, $options, $say);
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->writeBundle($dir, $result);
        if ($target = (string) $this->option("export-player")) {
            $this->exportPlayer($dir, $target);
        }
        $this->newLine();
        $this->line('Bundle: ' . $dir);

        foreach ($result['report']['warnings'] as $w) {
            $this->warn('  warning: ' . $w);
        }
        foreach ($result['report']['errors'] as $e) {
            $this->line('  <fg=red>FAIL</> ' . $e);
        }
        $this->line('  ' . json_encode($result['report']['stats']));

        if (!$result['report']['ok']) {
            $this->error('Validation failed - not stored.');

            return self::FAILURE;
        }

        $this->info('Validation passed.');

        if (!$store) {
            $this->line('Review the bundle; then run: php artisan lms:store-study-deck ' . $chapterId . ' --dry-run   (and without --dry-run to store it)');

            return self::SUCCESS;
        }

        return $this->call('lms:store-study-deck', ['chapter' => $chapterId, '--bundle' => $dir, '--tenant' => $tenant]);
    }
    /** A replay calls no model; if anything asks for one, that is a bug to stop on, not to paper over. */
    private function noModel(): Completer
    {
        return new class implements Completer {
            public function complete(string $system, string $prompt, int $maxTokens = 16000): string
            {
                throw new \LogicException('A replay must not call the model.');
            }
        };
    }

    /**
     * What an earlier run saved of the model's work, for --replay.
     *
     * @return array{plan:array<string,mixed>,content:array<int,array<string,mixed>>,images:array<int,array<string,mixed>>,interactions:array<int,array<string,mixed>>,limits:array<string,int>}
     */
    private function savedBundle(string $dir): array
    {
        $read = function (string $file) use ($dir): array {
            $path = $dir . '/' . $file;
            $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            if (!is_array($data)) {
                throw new \RuntimeException("The bundle has no readable $file ($path); a replay needs a bundle from lms:generate-study-deck.");
            }

            return $data;
        };

        return [
            'plan' => $read('plan.json'),
            'content' => $read('slides.json'),
            'images' => $read('images.json'),
            'interactions' => $read('interactions.json'),
            'limits' => (array) ($read('learning-map.json')['limits'] ?? []),
        ];
    }

    private function geminiCompleter(int $tenant): Completer
    {
        $client = app(\App\Domain\AI\Support\GeminiClient::class)->forInstitute($tenant);
        if (!$client->isConfigured()) {
            throw new RuntimeException('No Gemini API key (ai_api_keys api_type=gemini, or GEMINI_API_KEY). Story lessons are written by Gemini only.');
        }
        $completer = new \App\Services\StudyDeck\Story\GeminiStoryCompleter($client);
        $this->line('  Gemini model: ' . $completer->model());

        return $completer;
    }

    private function completer(string $executor, ContentGenerationService $content): Completer
    {
        if ($executor === 'cli') {
            return new ClaudeCliCompleter();
        }

        // The service's own key resolution and streaming call, reached through a
        // closure so this command carries no Anthropic code of its own.
        $bridge = new class extends ContentGenerationService {
            public function completer(): ?ClaudeApiCompleter
            {
                // Providers, model and keys come from the 'content_generation' AI configuration.
                return new ClaudeApiCompleter(fn (string $s, string $p, int $m): array => $this->callClaude($p, $s, $m));
            }
        };

        return $bridge->completer()
            ?? throw new \RuntimeException('No AI provider configured for Content Generation. Use --executor=cli for a dev run.');
    }

    /**
     * The finished story of a bundle, as the story generator takes it: the bible and notes, each act's skeleton, and each written act (the
     * story slides of deck.json, in order). A earlier picture pass's plan and failed searches are picked up too, so a second pass continues.
     *
     * @return array<string,mixed>
     */
    private function pictureOptions(string $source): array
    {
        $read = function (string $name) use ($source): array {
            $path = rtrim($source, '/\\') . '/' . $name;
            $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

            return is_array($data) ? $data : [];
        };
        $deck = $read('deck.json');
        $story = $read('story.json');
        if (($deck['slides'] ?? []) === [] || ($story['acts'] ?? []) === []) {
            throw new RuntimeException("--pictures-from: $source has no deck.json and story.json of a story lesson.");
        }
        $interactions = array_values(array_map(fn ($s) => $s['interaction'], array_filter((array) $deck['slides'], fn ($s) => ($s['interaction']['template'] ?? null) === \App\Services\StudyDeck\Story\StoryTemplate::KEY)));
        $keys = array_column($story['acts'], 'key');
        if (count($interactions) !== count($keys)) {
            throw new RuntimeException('--pictures-from: the bundle has ' . count($interactions) . ' story slides but ' . count($keys) . ' acts.');
        }
        $earlier = $read('pictures.json');

        return [
            'story_prebuilt' => [
                'bible' => $story['bible'], 'notes' => array_values(array_filter((array) ($story['notes'] ?? []), fn ($n) => !str_starts_with((string) $n, 'Pictures: '))),
                'skeletons' => array_combine($keys, array_column($story['acts'], 'skeleton')),
                'interactions' => array_combine($keys, $interactions),
            ],
            'picture_plan' => $this->option('replan') ? [] : (array) ($earlier['plan'] ?? []), 'picture_misses' => (array) ($earlier['misses'] ?? []),
            ...($this->pictureCurated() !== null ? ['picture_curated' => $this->pictureCurated(), 'picture_curated_only' => true, 'picture_misses' => []] : []),
        ];
    }

    /** The picture plan a person wrote (--picture-plan-file), or null when none was given. @return array<string,mixed>|null */
    private function pictureCurated(): ?array
    {
        $file = (string) $this->option('picture-plan-file');
        if ($file === '') {
            return null;
        }
        $plan = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($plan) || $plan === []) {
            throw new RuntimeException("--picture-plan-file: $file is not a JSON object of picture plans.");
        }

        return $plan;
    }

    /** @param array<string,mixed> $r */
    private function writeBundle(string $dir, array $r): void
    {
        foreach ([$dir, $dir . '/out'] as $d) {
            if (!is_dir($d)) {
                mkdir($d, 0775, true);
            }
        }
        $json = fn ($v) => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        file_put_contents($dir . '/concepts.json', $json(array_values(array_map(fn ($c) => $c['name'], $r['context']['concepts']))));
        file_put_contents($dir . '/learning-map.json', $json($r['map']));
        file_put_contents($dir . '/plan.json', $json($r['plan']));
        file_put_contents($dir . '/slides.json', $json($r['content']));
        file_put_contents($dir . '/images.json', $json($r['images']));
        file_put_contents($dir . '/deck.json', $json($r['deck']));
        file_put_contents($dir . '/questions-excluded.json', $json($r['selection']['excluded']));
        file_put_contents($dir . '/questions-flagged.json', $json($r['selection']['flagged'] ?? []));
        file_put_contents($dir . '/activities.json', $json($r['activities'] ?? []));
        file_put_contents($dir . '/interactions.json', $json($r['interactions'] ?? []));
        file_put_contents($dir . '/report.json', $json($r['report']));
        if (isset($r['pictures'])) {
            // What the picture pass did (counts, why images were refused, licences), the plan it followed and the searches that found nothing.
            file_put_contents($dir . '/pictures.json', $json($r['pictures']));
        }
        if (isset($r['story'])) {
            // The story's bible, its checked skeletons and what was left out (a picture whose labels the chapter does not support).
            file_put_contents($dir . '/story.json', $json($r['story']));
        }
        file_put_contents($dir . '/out/presentation.html', $r['html']);
    }

    /**
     * Copy the review bundle's deck to a folder the local player can serve.
     *
     * Only deck.json: its pictures are `study-deck-image:<id>` references to rows in the database, which the player
     * turns into addresses through the API. No picture is copied, so nothing accumulates in that folder.
     */
    private function exportPlayer(string $dir, string $target): void
    {
        $target = rtrim($target, '/\\');
        if (!is_dir($target)) {
            mkdir($target, 0775, true);
        }
        copy($dir . '/deck.json', $target . '/deck.json');
        $this->line('Player fixture: ' . $target . '/deck.json');
    }
}
