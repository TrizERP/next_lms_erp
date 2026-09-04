<?php

namespace App\Services\Eso;

use App\Models\Eso\LearnerNodeState;
use App\Models\PAL\ConceptNode;
use App\Models\PAL\MisconceptionLibrary;
use App\Services\PAL\ContentModel\ContentModelLlmClient;
use Illuminate\Support\Collection;

/**
 * Pal as constrained renderer — Adaptive Learning Engine Developer Brief v1,
 * Phase 10/6.3.
 *
 * The engine decides WHETHER/WHAT/WHICH; Pal decides HOW to say it. This
 * class enforces that split structurally, not by convention:
 *
 *   - The `*Instruction()` static methods build the exact constrained text
 *     EsoPolicyService hands to Pal (and stores verbatim in
 *     eso_decision_log.llm_instruction) — pure string assembly, no LLM call,
 *     fully unit-testable without a network dependency.
 *   - `render()` is the ONLY method in this whole feature that calls an LLM,
 *     and it is given a system prompt that forbids the model from choosing
 *     content: it may only rephrase the instruction it was handed.
 *
 * Reuses ContentModelLlmClient (existing OpenRouter/DeepSeek transport,
 * provider-key resolution, response caching) rather than opening a new LLM
 * integration — see the implementation plan §I.
 */
class EsoPalRenderer
{
    public function __construct(protected ContentModelLlmClient $client)
    {
    }

    /**
     * "Teach K4. Use a minimal explanation. Provide one worked example.
     * Do not re-explain K1-K3." — the brief's own example, generated exactly
     * this way.
     */
    public static function teachInstruction(ConceptNode $node, LearnerNodeState $state, Collection $priorNodeLabels): string
    {
        $lines = [];
        $lines[] = sprintf('Teach %s: %s.', $node->node_type, $node->label);

        $lines[] = $state->attempts === 0
            ? 'The student has not attempted this node yet. Use a minimal explanation with exactly one worked example.'
            : sprintf('The student is still practicing this node (mastery %.0f%%, %d attempt(s) so far). Reinforce briefly — do not re-explain from the beginning.', $state->mastery_estimate * 100, $state->attempts);

        if ($priorNodeLabels->isNotEmpty()) {
            $lines[] = 'Do not re-explain: ' . $priorNodeLabels->implode(', ') . '.';
        }

        $lines[] = 'Do not introduce any node beyond ' . $node->node_type . ': ' . $node->label . '.';

        return implode(' ', $lines);
    }

    /**
     * The D3 contrast-pair instruction: example + non-example + "explain the
     * difference", never a generic re-explanation.
     */
    public static function contrastPairInstruction(ConceptNode $node, ?MisconceptionLibrary $misconception, ?array $corrective): string
    {
        $lines = [];
        $lines[] = sprintf(
            'The student selected an answer for %s: %s that reflects a known misconception%s.',
            $node->node_type,
            $node->label,
            $misconception ? ": \"{$misconception->description}\"" : '.'
        );

        $lines[] = 'Present ONE correct example and ONE non-example that isolates exactly this misconception — do not give a generic re-explanation of the whole concept.';

        if ($corrective && ! empty($corrective['body'])) {
            $lines[] = 'Base the contrast on this approved corrective content: ' . strip_tags((string) $corrective['body']);
        }

        $lines[] = 'Ask the student to explain, in their own words, what the difference is between the example and the non-example.';
        $lines[] = 'Do not reveal or hint at the retest question that will follow.';

        return implode(' ', $lines);
    }

    /**
     * Deterministic display copy for the chapter dashboard's "Next Step"
     * panel, keyed off the exact `action` nextAction() already resolved —
     * pure string assembly like teachInstruction()/contrastPairInstruction()
     * above, no LLM call. The dashboard is a summary view, not a place to
     * introduce a second opinion about what the student should do next.
     *
     * @return array{title:string, subtitle:string, reasons:array<int,string>, cta_label:?string}
     */
    public static function dashboardNextStep(string $action, ?string $conceptName, ?string $prerequisiteName): array
    {
        $concept = $conceptName ?? 'this concept';

        return match ($action) {
            'diagnostic' => [
                'title' => 'Start with a quick check',
                'subtitle' => "We need a few responses on {$concept} before this step can be personalised.",
                'reasons' => ["No responses recorded for {$concept}"],
                'cta_label' => 'Start diagnostic',
            ],
            'remediate_prerequisite' => [
                'title' => 'Master a prerequisite first',
                'subtitle' => $prerequisiteName
                    ? "Strengthen {$prerequisiteName} before continuing with {$concept}."
                    : "A prerequisite concept needs work before continuing with {$concept}.",
                'reasons' => [
                    $prerequisiteName
                        ? "{$prerequisiteName} is below the mastery needed to unlock {$concept}"
                        : 'A prerequisite concept is below the mastery needed to unlock this one',
                ],
                'cta_label' => 'Review prerequisite',
            ],
            'teach' => [
                'title' => "Learn {$concept}",
                'subtitle' => 'This is your first look at this part of the concept.',
                'reasons' => ["No responses recorded for {$concept} yet"],
                'cta_label' => 'Start learning',
            ],
            'practice' => [
                'title' => 'Keep practicing',
                'subtitle' => "A few more responses on {$concept} will help PAL personalise your path.",
                'reasons' => ['Not enough recent responses to confirm mastery yet'],
                'cta_label' => 'Practice now',
            ],
            'serve_contrast_pair' => [
                'title' => "Let's clear up a mix-up",
                'subtitle' => "One of your responses on {$concept} pointed at a common misunderstanding.",
                'reasons' => ['A response matched a known misconception'],
                'cta_label' => 'Review the mix-up',
            ],
            'mastered_stop_practice' => [
                'title' => 'Concept mastered',
                'subtitle' => "You've cleared {$concept} — practice stops here.",
                'reasons' => ['Knowledge and application mastery thresholds are both met'],
                'cta_label' => 'See mastery details',
            ],
            'continue_practice' => [
                'title' => 'Keep practicing',
                'subtitle' => "{$concept} is close, but not yet at the mastery threshold.",
                'reasons' => ['Knowledge or application mastery is still below threshold'],
                'cta_label' => 'Practice now',
            ],
            'retrieval_due' => [
                'title' => 'Quick review',
                'subtitle' => "A short check to make sure {$concept} is still solid.",
                'reasons' => ['Scheduled spaced-review check is due'],
                'cta_label' => 'Start review',
            ],
            'no_nodes_defined' => [
                'title' => 'Not ready yet',
                'subtitle' => "{$concept} doesn't have adaptive content authored yet.",
                'reasons' => ['No K/A/S nodes are defined for this concept'],
                'cta_label' => null,
            ],
            default => [
                'title' => 'Continue learning',
                'subtitle' => "Keep going with {$concept}.",
                'reasons' => [],
                'cta_label' => 'Continue',
            ],
        };
    }

    /**
     * Render an engine instruction conversationally. This is the ONLY LLM
     * call in the Adaptive Learning Engine — the system prompt is the
     * enforcement point that keeps Pal from choosing content on its own.
     *
     * Returns the rendered text, or null if no LLM provider is configured
     * (callers should fall back to showing $instruction's plain content
     * directly rather than blocking the student on an LLM outage).
     */
    public function render(string $instruction, array $context = []): ?string
    {
        if (! $this->client->enabled()) {
            return null;
        }

        $system = 'You are Pal, a friendly K-12 tutor inside an adaptive learning app. '
            . 'You will be given an INSTRUCTION written by a deterministic decision engine that has already '
            . 'decided exactly what to teach and why. Your ONLY job is to phrase that instruction as warm, '
            . 'age-appropriate, conversational text for the student. '
            . 'You must NOT decide what concept, node, or misconception to address — that decision has '
            . 'already been made and is final. You must NOT introduce material the instruction does not '
            . 'mention. You must NOT skip anything the instruction says to include (such as a worked example, '
            . 'or asking the student a question). Keep it under 150 words. '
            . 'Respond with ONLY a JSON object of the exact shape {"text": "<your rendered message>"} — no other keys, no markdown fences.';

        $user = 'INSTRUCTION: ' . $instruction;
        if ($context !== []) {
            $user .= "\n\nContext (for tone only, not for content decisions): " . json_encode($context);
        }

        $result = $this->client->json($system, $user);

        if (empty($result['ok'])) {
            return null;
        }

        $text = $result['data']['text'] ?? null;

        return is_string($text) && trim($text) !== '' ? trim($text) : null;
    }
}
