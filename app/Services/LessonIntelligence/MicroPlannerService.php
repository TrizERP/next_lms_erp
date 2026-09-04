<?php

namespace App\Services\LessonIntelligence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Phase 3 - Micro planner.
 *
 * Writes the actual teaching content for one period: a 5E breakdown,
 * differentiation strategies, three formative questions and homework, stored in
 * lms_lesson_plan_periods.plan_json.
 *
 * This is the only phase that costs money - roughly one DeepSeek call per
 * period - so it is always explicit: one period at a time, or a bounded batch.
 *
 * The prompt is enriched with the chapter's semantic intelligence (objectives,
 * abilities, pedagogy strategies, misconceptions, real-world applications,
 * prerequisites), the subject's official NCF/NCERT learning outcomes, and the
 * previously planned period so lessons reference what came before.
 */
class MicroPlannerService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an expert curriculum designer and lesson planner.
Your goal is to design a detailed, engaging lesson plan for a single teaching period.
You will receive rich context including: concepts to teach, semantic intelligence data
(learning objectives, abilities, misconceptions, pedagogy strategies, real-world applications),
and official NCF/NCERT learning outcomes. Use ALL of this data to create a well-aligned,
pedagogically sound lesson plan.
You must strictly return a valid JSON object matching the requested schema. Do not include markdown formatting or explanations outside the JSON.

SCHEMA:
{
  "blooms_level": "string (e.g. Remember, Understand, Apply, Analyze, Evaluate, Create)",
  "dok_level": "integer (1 to 4)",
  "pedagogy_method": "string (Strictly map to 5E model)",
  "difficulty_level": "string (Easy, Medium, Hard)",
  "learning_objectives": ["string", "string"],
  "plan_json": {
    "engage": {
      "duration_min": "integer",
      "description": "string (Hook the students, explicitly link to Previous Period context if provided)"
    },
    "explore": {
      "duration_min": "integer",
      "activity_description": "string (Hands-on or conceptual exploration)"
    },
    "explain": {
      "duration_min": "integer",
      "strategy": "string (Core teaching, clarifying misconceptions)"
    },
    "elaborate": {
      "duration_min": "integer",
      "real_world_application": "string"
    },
    "evaluate": {
      "duration_min": "integer",
      "quick_assessment": "string"
    },
    "differentiation": {
      "remedial_strategy": "string (How to help struggling students)",
      "enrichment_activity": "string (Advanced task for fast learners)"
    },
    "formative_assessment": [
      {
        "question": "string (Multiple choice question)",
        "options": ["A. ...", "B. ...", "C. ...", "D. ..."],
        "correct_answer": "string (Exact text of the correct option)"
      }
    ],
    "homework": "string"
  }
}
PROMPT;

    public function __construct(private LessonIntelligenceService $intel)
    {
    }

    /**
     * Generate and store the lesson content for one period.
     *
     * Returns a 'skipped' result rather than throwing when there is nothing to
     * plan (no concepts) or the work is already done, so batch runs keep going.
     */
    public function generateForPeriod(int $periodId, bool $force = false): array
    {
        $period = DB::table(LessonIntelligenceService::TBL_PLAN_PERIODS . ' as p')
            ->join(
                LessonIntelligenceService::TBL_LESSON_PLANS . ' as lp',
                'p.lms_intelligence_lesson_plans_id',
                '=',
                'lp.id'
            )
            ->where('p.id', $periodId)
            ->first(['p.*', 'lp.standard_id', 'lp.subject_id']);

        if (!$period) {
            throw new RuntimeException("Period {$periodId} not found.");
        }

        // plan_json is the real "already generated" marker. The period's status
        // column tracks the teacher's delivery progress and is left alone.
        if (!$force && !empty($period->plan_json)) {
            return ['status' => 'skipped', 'period_id' => $periodId, 'reason' => 'A plan already exists for this period.'];
        }

        $concepts = DB::table(LessonIntelligenceService::TBL_PLAN_CONCEPTS . ' as c')
            ->leftJoin('lms_concept as lc', 'c.concept_id', '=', 'lc.id')
            ->where('c.lms_lesson_plan_periods_id', $periodId)
            ->get(['c.concept_name', 'c.coverage_percent', 'lc.description']);

        if ($concepts->isEmpty()) {
            return ['status' => 'skipped', 'period_id' => $periodId, 'reason' => 'No concepts are mapped to this period.'];
        }

        $prompt = $this->buildPrompt($period, $concepts);
        $data   = $this->callDeepSeek($prompt);

        DB::table(LessonIntelligenceService::TBL_PLAN_PERIODS)
            ->where('id', $periodId)
            ->update([
                'blooms_level'        => $data['blooms_level'] ?? null,
                'dok_level'           => isset($data['dok_level']) ? (int) $data['dok_level'] : null,
                'pedagogy_method'     => $data['pedagogy_method'] ?? null,
                'difficulty_level'    => $data['difficulty_level'] ?? null,
                'learning_objectives' => json_encode($data['learning_objectives'] ?? [], JSON_UNESCAPED_UNICODE),
                'plan_json'           => json_encode($data['plan_json'] ?? new \stdClass(), JSON_UNESCAPED_UNICODE),
                'updated_at'          => now(),
            ]);

        return ['status' => 'success', 'period_id' => $periodId];
    }

    /**
     * Generate plans for up to $limit periods of a plan that do not have one yet.
     * Runs sequentially - the provider rate-limits parallel calls.
     */
    public function generateBatch(int $planId, int $limit = 10): array
    {
        $limit = max(1, min($limit, 50));

        $periodIds = DB::table(LessonIntelligenceService::TBL_PLAN_PERIODS)
            ->where('lms_intelligence_lesson_plans_id', $planId)
            ->whereNull('plan_json')
            ->orderBy('scheduled_date')
            ->orderBy('period_slot')
            ->limit($limit)
            ->pluck('id');

        if ($periodIds->isEmpty()) {
            return ['status' => 'success', 'processed' => 0, 'message' => 'Every period in this plan already has a lesson plan.', 'results' => []];
        }

        $results = [];
        foreach ($periodIds as $pid) {
            try {
                $results[] = $this->generateForPeriod((int) $pid);
            } catch (\Throwable $e) {
                // One bad period must not abandon the rest of the batch.
                Log::error("MicroPlanner: period {$pid} failed - " . $e->getMessage());
                $results[] = ['status' => 'failed', 'period_id' => (int) $pid, 'error' => $e->getMessage()];
            }
        }

        return [
            'status'    => 'success',
            'processed' => count($results),
            'succeeded' => count(array_filter($results, fn ($r) => $r['status'] === 'success')),
            'results'   => $results,
        ];
    }

    /* ================================================================= */

    private function buildPrompt(object $period, $concepts): string
    {
        $duration = (int) ($period->planned_duration_min ?: LessonIntelligenceService::DEFAULT_PERIOD_DURATION_MIN);

        $conceptsText = '';
        foreach ($concepts as $c) {
            $conceptsText .= "- Concept: {$c->concept_name} (Coverage in this period: {$c->coverage_percent}%)\n";
            if (!empty($c->description)) {
                $conceptsText .= "  Description: {$c->description}\n";
            }
        }

        return "Design a {$duration}-minute lesson plan for standard (class) {$period->standard_id}, chapter \"{$period->chapter_name}\".\n"
            . "Type of period: {$period->period_type}\n\n"
            . "Concepts to cover in this period:\n{$conceptsText}\n"
            . $this->semanticContext($period)
            . $this->learningOutcomeContext($period)
            . $this->previousPeriodContext($period)
            . "\nConstraints:\n"
            . "- The sum of duration_min for engage, explore, explain, elaborate, and evaluate MUST equal exactly {$duration}.\n"
            . "- Make the activities engaging and age-appropriate using the 5E pedagogy model.\n"
            . "- Identify at least one common misconception during the explain phase.\n"
            . "- Align with the official learning outcomes where possible.\n"
            . "- Use the recommended pedagogy strategies from semantic intelligence.\n"
            . "- Include exactly 3 multiple-choice questions in the formative_assessment array.\n"
            . "- Provide strong differentiation strategies for both struggling and gifted learners.\n";
    }

    /** Chapter-level intelligence: objectives, abilities, pedagogy, misconceptions, applications. */
    private function semanticContext(object $period): string
    {
        if (empty($period->chapter_id)) {
            return '';
        }

        $row = DB::table('semantic_intelligence')
            ->where('chapter_id', $period->chapter_id)
            ->first([
                'learning_objective', 'learning_objectives', 'learning_outcomes',
                'ability', 'knowledge', 'misconceptions', 'pedagogy',
                'real_world_applications', 'prerequisites', 'blooms_level', 'dok',
            ]);

        if (!$row) {
            return '';
        }

        $pick = fn ($col, $key, $limit) => array_values(array_filter(array_map(
            fn ($item) => is_array($item) ? trim((string) ($item[$key] ?? '')) : trim((string) $item),
            array_slice((array) ($this->intel->decodeJsonPublic($row->{$col}) ?: []), 0, $limit)
        )));

        $out = '';

        if ($objectives = $pick('learning_objectives', 'objective', 5)) {
            $out .= "\nTeacher's Learning Objectives (from semantic intelligence):\n";
            foreach ($objectives as $o) {
                $out .= "  - {$o}\n";
            }
        }

        if ($abilities = $pick('ability', 'ability', 4)) {
            $out .= "\nTarget Student Abilities:\n";
            foreach ($abilities as $a) {
                $out .= "  - {$a}\n";
            }
        }

        if ($strategies = $pick('pedagogy', 'strategy', 3)) {
            $out .= "\nRecommended Pedagogy Strategies: " . implode(', ', $strategies) . "\n";
        }

        if ($misconceptions = $pick('misconceptions', 'misconception', 3)) {
            $out .= "\nKnown Student Misconceptions:\n";
            foreach ($misconceptions as $m) {
                $out .= "  ! {$m}\n";
            }
        }

        if ($applications = $pick('real_world_applications', 'example', 3)) {
            $out .= "\nReal-World Applications:\n";
            foreach ($applications as $a) {
                $out .= "  * {$a}\n";
            }
        }

        if ($prerequisites = array_unique($pick('prerequisites', 'concept_name', 4))) {
            $out .= "\nPrerequisite Knowledge: " . implode(', ', $prerequisites) . "\n";
        }

        return $out;
    }

    /**
     * Official NCF/NCERT competencies for the subject.
     *
     * These are stored against the content institute's standard/subject ids, not
     * the school's, so the plan's own ids have to be resolved first - querying
     * with them directly matches nothing and silently drops this whole section
     * from the prompt while the constraints still ask the model to align to it.
     */
    private function learningOutcomeContext(object $period): string
    {
        [, $contentStd, $contentSub] = $this->intel->resolveContentSource(
            (int) $period->standard_id,
            (int) $period->subject_id
        );

        $rows = DB::table('lms_learning_outcomes')
            ->where('standard_id', $contentStd)
            ->where('subject_id', $contentSub)
            ->where('type', 'competency')
            ->orderBy('id')
            ->limit(5)
            ->get(['code', 'description']);

        if ($rows->isEmpty()) {
            return '';
        }

        $out = "\nOfficial Learning Outcomes (NCF/NCERT Competencies) to align with:\n";
        foreach ($rows as $r) {
            $desc = (string) $r->description;
            if (mb_strlen($desc) > 120) {
                $desc = mb_substr($desc, 0, 120) . '...';
            }
            $out .= "  [{$r->code}] {$desc}\n";
        }

        return $out;
    }

    /**
     * The previously planned teaching period, so the Engage hook can build on it.
     * Keyed off plan_json rather than a status flag - that is what actually
     * records whether a period has been planned.
     */
    private function previousPeriodContext(object $period): string
    {
        $prev = DB::table(LessonIntelligenceService::TBL_PLAN_PERIODS)
            ->where('lms_intelligence_lesson_plans_id', $period->lms_intelligence_lesson_plans_id)
            ->where('id', '<', $period->id)
            ->where('period_type', 'teaching')
            ->whereNotNull('plan_json')
            ->orderByDesc('id')
            ->first(['primary_concept_name']);

        if (!$prev || empty($prev->primary_concept_name)) {
            return '';
        }

        return "\nPrevious Period Context:\n"
            . "- Previously taught concept: {$prev->primary_concept_name}\n"
            . "- Please explicitly reference this in your 'Engage' hook to maintain continuity.\n";
    }

    /**
     * Call DeepSeek and return the decoded JSON object.
     * Key resolution matches QuestionGenerationService: ai_api_keys table first,
     * then the DEEPSEEK_API_KEY env var.
     */
    private function callDeepSeek(string $userPrompt): array
    {
        $apiKey = $this->resolveApiKey();
        if (!$apiKey) {
            throw new RuntimeException('No DeepSeek API key is configured. Add one to ai_api_keys or set DEEPSEEK_API_KEY.');
        }

        $timeout = (int) config('deepseek.timeout_seconds', 600);
        set_time_limit($timeout + 60);

        $body = [
            'model'    => config('deepseek.model', 'deepseek-chat'),
            'messages' => [
                ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            'response_format' => ['type' => 'json_object'],
            'temperature'     => (float) config('deepseek.temperature_narrative', 0.6),
            'stream'          => false,
        ];

        $maxTokens = (int) config('deepseek.max_output_tokens', 0);
        if ($maxTokens > 0) {
            $body['max_tokens'] = $maxTokens;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])
            ->timeout($timeout)
            ->connectTimeout(20)
            ->post(rtrim((string) config('deepseek.base_url', 'https://api.deepseek.com'), '/') . '/chat/completions', $body);

        if (!$response->successful()) {
            throw new RuntimeException('DeepSeek request failed: ' . $response->status() . ' ' . $response->body());
        }

        $content = $response->json('choices.0.message.content');
        if (!is_string($content) || $content === '') {
            throw new RuntimeException('DeepSeek returned an empty message.');
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new RuntimeException('DeepSeek returned malformed JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    private function resolveApiKey(): ?string
    {
        if (function_exists('getAIKey')) {
            $row = getAIKey(config('deepseek.api_type', 'DEEPSEEK_API_KEY'), 1);
            if (!empty($row->api_key) && $row->api_key !== '-') {
                return $row->api_key;
            }
        }

        $row = DB::table('ai_api_keys')
            ->where('api_type', config('deepseek.api_type', 'DEEPSEEK_API_KEY'))
            ->where('status', 1)
            ->first();

        if (!empty($row->api_key) && $row->api_key !== '-') {
            return $row->api_key;
        }

        $envKey = config('deepseek.api_key');

        return !empty($envKey) ? $envKey : null;
    }
}
