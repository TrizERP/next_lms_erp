<?php

namespace App\Services\Lms;

use App\Domain\AI\Configuration\AiModelClientFactory;
use App\Http\Controllers\lms\h5p\H5PBranchingScenarioController;
use App\Models\lms\chapterModel;
use App\Services\StudyDeck\Branching\BranchingScenarioService;
use App\Services\StudyDeck\GeminiCompleter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bridge between the Teach/Learn 3-agent pipeline and the existing H5P Branching Scenario architecture.
 *
 * It owns NO storage and NO generator of its own:
 *   - generation is BranchingScenarioService (the same planner, writer and validator the H5P editor's "generate"
 *     action uses) answered by Gemini from the central AI configuration. It does NOT go through
 *     ContentGenerationService, so it needs no Anthropic key and no claude.chapter_ids entry;
 *   - saving is H5PBranchingScenarioController::store(), the editor's own save, so nodes, refs, drafts and the
 *     cached player payload are written exactly as for a scenario a teacher typed. Scenarios therefore live in
 *     h5p_branching_scenario / h5p_branching_nodes (not content_master) and start as DRAFTS: publishing stays a
 *     separate human step, as the H5P module requires.
 *
 * "Generated" is only ever reported after the stored row has been read back.
 */
class TeachLearnBranchingService
{
    public const RESOURCE_TYPE = 'Branching Scenario';

    /** A live (not soft-deleted) scenario already stored for this chapter and school, if any. */
    public function findExisting(int $subInstituteId, int $chapterId): ?object
    {
        return DB::table('h5p_branching_scenario')
            ->where('sub_institute_id', $subInstituteId)
            ->where('chapter_id', $chapterId)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->first(['id', 'title', 'status', 'chapter_id', 'syear', 'created_at']);
    }

    /**
     * A Gemini-backed client for one school, from the central AI configuration (the same providers, keys and models
     * the rest of the LMS uses; nothing is read from .env and no key is stored here).
     *
     * The 'content_generation' module may be pointed at another provider for this school (DeepSeek, for school 1), so
     * its chain is searched for a Gemini leg first, then the product module's and the shared pool's.
     *
     * @return array{client:\App\Domain\AI\Support\FallbackChainModelClient, model:?string}
     *
     * @throws \RuntimeException when no usable Gemini configuration exists
     */
    public function geminiClient(int $subInstituteId): array
    {
        return $this->geminiClients($subInstituteId)[0];
    }

    /**
     * Every distinct usable Gemini model the configuration offers, best first, so an overloaded one (HTTP 503) can be
     * followed by the next instead of failing the run.
     *
     * @return list<array{client:AppDomainAISupportFallbackChainModelClient, model:?string}>
     *
     * @throws RuntimeException when no usable Gemini configuration exists
     */
    public function geminiClients(int $subInstituteId): array
    {
        $factory = app(AiModelClientFactory::class);
        $lookups = [
            ['content_generation', $subInstituteId, 'teach_learn'],
            ['teach_learn', $subInstituteId, null],
            [null, $subInstituteId, null],
            ['content_generation', null, 'teach_learn'],
        ];

        $found = [];
        foreach ($lookups as [$module, $tenant, $product]) {
            try {
                foreach ($factory->chainFor($module, $tenant, $product) as $config) {
                    if (strtolower($config->provider) === 'gemini' && $config->hasKey() && ! isset($found[(string) $config->model])) {
                        $found[(string) $config->model] = ['client' => $factory->fromConfiguration($config, $subInstituteId), 'model' => $config->model];
                    }
                }
            } catch (Throwable) {
                // Try the next lookup; a missing module entry is not fatal.
            }
        }

        if ($found === []) {
            throw new RuntimeException('No Gemini API key/model is configured for this school (AI Stack settings).');
        }

        return array_values($found);
    }

    /**
     * Generate and save one scenario for a chapter, using Gemini.
     *
     * Safe to retry: an existing scenario for the chapter is reused, and a lock stops two runs writing at once.
     *
     * @return array{success:bool, content_id?:int, title?:string, status?:string, error?:string, message?:string, verified?:array<string,mixed>}
     */
    public function generateAndStore(int $subInstituteId, int $chapterId, ?int $syear = null, ?int $userId = null): array
    {
        $chapter = chapterModel::where('id', $chapterId)->first();
        if ($chapter === null || (int) $chapter->sub_institute_id !== $subInstituteId) {
            return ['success' => false, 'error' => "Chapter #{$chapterId} was not found for this school."];
        }

        // Retry / re-run: reuse what is already stored instead of writing a second scenario.
        $existing = $this->findExisting($subInstituteId, $chapterId);
        if ($existing !== null) {
            return [
                'success' => true,
                'status' => 'verified_existing',
                'is_new' => false,
                'content_id' => (int) $existing->id,
                'title' => (string) $existing->title,
                'message' => "Branching scenario #{$existing->id} already exists for this chapter; reused, no duplicate created.",
            ];
        }

        $lock = null;
        try {
            $lock = Cache::lock("teach_learn_branching_{$subInstituteId}_{$chapterId}", 900);
            if (! $lock->get()) {
                return ['success' => false, 'error' => 'A branching scenario is already being generated for this chapter. Try again shortly.'];
            }
        } catch (Throwable) {
            $lock = null; // No lock store available: fall through; the existing-check above still guards sequential retries.
        }

        try {
            return $this->generateLocked($chapter, $subInstituteId, $chapterId, $syear, $userId);
        } finally {
            if ($lock !== null) {
                try {
                    $lock->release();
                } catch (Throwable) {
                }
            }
        }
    }

    private function generateLocked(chapterModel $chapter, int $subInstituteId, int $chapterId, ?int $syear, ?int $userId): array
    {
        $syear ??= app(ContentAgentService::class)->currentSyear($subInstituteId);

        try {
            $clients = $this->geminiClients($subInstituteId);
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        // Two model stages with several calls each; the web server's default limit would end it mid-way.
        @set_time_limit(900);

        $result = null;
        $lastError = 'Gemini could not write the scenario.';
        foreach ($clients as $gemini) {
            try {
                $result = BranchingScenarioService::make(new GeminiCompleter($gemini['client']))->generate($chapterId, $subInstituteId, [
                    // 'short' (8-13 steps): keeps one synchronous Start Agents request within web time limits and is far
                    // less likely to overshoot the planner's node budget than 'standard' (Gemini wrote 21 against 11-17).
                    'length' => 'short',
                    'use_questions' => true,
                    'existing' => [],
                    'fix_rounds' => 3,
                    'model' => $gemini['model'],
                ]);
                break;
            } catch (InvalidArgumentException $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning('Teach/Learn branching generation (Gemini) failed', ['chapter_id' => $chapterId, 'model' => $gemini['model'], 'error' => $lastError]);
                // Only an overloaded / rate-limited model is worth trying the next configured Gemini model for.
                if (! preg_match('/busy|high demand|rate limit|temporarily|unavailable|429|503/i', $lastError)) {
                    return ['success' => false, 'error' => $lastError];
                }
            }
        }

        if ($result === null) {
            return ['success' => false, 'error' => $lastError];
        }

        if (! ($result['report']['ok'] ?? false)) {
            return ['success' => false, 'error' => 'The Gemini scenario failed validation and was not stored: ' . implode('; ', array_slice((array) ($result['report']['errors'] ?? []), 0, 3))];
        }

        $scenario = $result['scenario'];

        try {
            $request = Request::create('/', 'POST', $scenario + [
                'type' => 'API',
                'standard_id' => (int) $chapter->standard_id,
                'subject_id' => (int) $chapter->subject_id,
                'chapter_id' => $chapterId,
                'syear' => $syear,
                'sub_institute_id' => $subInstituteId,
                'user_id' => $userId,
            ]);
            $response = app(H5PBranchingScenarioController::class)->store($request);
            $payload = method_exists($response, 'getData') ? (array) $response->getData(true) : [];
            $id = (int) ($payload['id'] ?? 0);
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'Scenario was written but could not be saved: ' . $e->getMessage()];
        }

        // Read it back. Success means these rows exist, are this school's, this chapter's, and have nodes.
        $verified = $this->verify($id, $subInstituteId, $chapterId);
        if ($verified['error'] !== null) {
            return ['success' => false, 'error' => $verified['error']];
        }

        return [
            'success' => true,
            'content_id' => $id,
            'title' => $verified['title'],
            'status' => 'generated_new',
            'verified' => $verified,
        ];
    }

    /** @return array{error:?string, title?:string, chapter_id?:int, syear?:mixed, nodes?:int, scenario_status?:string} */
    public function verify(int $scenarioId, int $subInstituteId, int $chapterId): array
    {
        if ($scenarioId <= 0) {
            return ['error' => 'Scenario save returned no id; nothing was stored.'];
        }

        $row = DB::table('h5p_branching_scenario')->where('id', $scenarioId)->whereNull('deleted_at')->first();
        if ($row === null) {
            return ['error' => "Scenario #{$scenarioId} is not in h5p_branching_scenario after saving."];
        }
        if ((int) $row->sub_institute_id !== $subInstituteId || (int) $row->chapter_id !== $chapterId) {
            return ['error' => "Scenario #{$scenarioId} was stored against the wrong school or chapter."];
        }

        $nodes = (int) DB::table('h5p_branching_nodes')->where('scenario_id', $scenarioId)->whereNull('deleted_at')->count();
        if ($nodes < 1) {
            return ['error' => "Scenario #{$scenarioId} was stored without any steps."];
        }

        return [
            'error' => null,
            'title' => (string) $row->title,
            'chapter_id' => (int) $row->chapter_id,
            'syear' => $row->syear,
            'nodes' => $nodes,
            'scenario_status' => (string) $row->status,
        ];
    }
}
