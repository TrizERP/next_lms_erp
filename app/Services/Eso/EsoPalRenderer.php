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
