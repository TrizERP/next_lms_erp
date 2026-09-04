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
    public static function teachInstruction(
        ConceptNode $node,
        LearnerNodeState $state,
        Collection $priorNodeLabels,
        ?array $learningContent = null
    ): string {
        $lines = [];
        $lines[] = sprintf('Teach %s: %s.', $node->node_type, $node->label);

        $lines[] = $state->attempts === 0
            ? 'The student has not attempted this node yet. Use a minimal explanation with exactly one worked example.'
            : sprintf('The student is still practicing this node (mastery %.0f%%, %d attempt(s) so far). Reinforce briefly — do not re-explain from the beginning.', $state->mastery_estimate * 100, $state->attempts);

        $lines[] = self::learningMaterialLine($learningContent);

        if ($priorNodeLabels->isNotEmpty()) {
            $lines[] = 'Do not re-explain: ' . $priorNodeLabels->implode(', ') . '.';
        }

        $lines[] = 'Do not introduce any node beyond ' . $node->node_type . ': ' . $node->label . '.';

        return implode(' ', array_filter($lines));
    }

    /**
     * Ground the explanation in the concept's own authored/extracted material
     * when the content model has any, so Pal is rephrasing real curriculum
     * text instead of improvising from a node label.
     *
     * Returns '' when there is nothing — the instruction then reads exactly as
     * it did before this existed, which is the required graceful fallback.
     *
     * @param  array{format:string, format_label:string, title:?string, body:?string, media_url:?string, source:string}|null  $content
     */
    protected static function learningMaterialLine(?array $content): string
    {
        $body = trim((string) ($content['body'] ?? ''));
        if ($content === null || $body === '') {
            return '';
        }

        $line = 'Base the explanation ONLY on this approved material for the concept, and do not add facts it does not contain: '
            . strip_tags($body);

        // An authored asset is shown to the student alongside the text, so Pal
        // must introduce it rather than duplicate what it contains.
        if (($content['media_url'] ?? null) !== null) {
            $line .= ' The student is also being shown a ' . ($content['format_label'] ?? 'resource')
                . ' alongside this — point them at it briefly, do not describe its contents.';
        }

        return $line;
    }

    /**
     * The Check-For-Understanding gate, served immediately after teaching and
     * before any scored practice.
     *
     * This is deliberately framed as a check, not as practice: the answers do
     * not move mastery_estimate, attempts or consecutive_correct (see
     * EsoPolicyService::recordCheckUnderstanding()), so Pal must not present
     * it as a test the student can fail their way out of the concept with.
     */
    public static function checkUnderstandingInstruction(ConceptNode $node, int $itemCount): string
    {
        $lines = [];
        $lines[] = sprintf('The student has just been taught %s: %s and is about to answer a short check of understanding (%d question(s)).', $node->node_type, $node->label, $itemCount);
        $lines[] = 'In one or two sentences, tell them this is a quick check to see whether the explanation landed, not a graded test, and that getting one wrong just means we explain it a different way.';
        $lines[] = 'Do not re-teach the material here, and do not reveal or hint at any answer.';

        return implode(' ', $lines);
    }

    /**
     * The "not understood" branch of CFU: re-explain the SAME node a different
     * way. Distinct from teachInstruction() (which assumes a blank slate) and
     * from contrastPairInstruction() (which targets one identified
     * misconception) — here we know only that the first explanation did not
     * land, so the instruction is to change the approach, not to repeat it.
     */
    public static function reteachInstruction(
        ConceptNode $node,
        Collection $priorNodeLabels,
        int $cfuAttempts,
        ?array $learningContent = null
    ): string {
        $lines = [];
        $lines[] = sprintf('The student was taught %s: %s but did not pass the check of understanding (attempt %d).', $node->node_type, $node->label, max(1, $cfuAttempts));
        $lines[] = 'Explain the SAME material again in a different way from the first explanation — change the angle, the example or the representation. Do not simply repeat the earlier wording.';

        // When the content model has a different FORMAT authored for this
        // concept, the change of approach is not left to Pal's imagination.
        if (($learningContent['format_label'] ?? null) !== null) {
            $lines[] = 'This time the material is presented as: ' . $learningContent['format_label'] . '.';
        }

        $lines[] = 'Use exactly one fresh worked example.';
        $lines[] = self::learningMaterialLine($learningContent);

        if ($priorNodeLabels->isNotEmpty()) {
            $lines[] = 'Do not re-explain: ' . $priorNodeLabels->implode(', ') . '.';
        }

        $lines[] = 'Do not introduce any node beyond ' . $node->node_type . ': ' . $node->label . '.';
        $lines[] = 'Do not reveal or hint at the check questions that will follow.';

        return implode(' ', array_filter($lines));
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
     * The "activation energy" message: shown once, at the moment a student
     * moves from having UNDERSTOOD a node to having to grind through practice
     * on it. Understanding is not the hard part for many students — starting
     * the practice is, so this names a concrete reason the concept is worth
     * the effort rather than cheering generically.
     *
     * The fact itself comes from ConceptRelevanceResolver: the concept's own
     * extracted real-world application where one exists, otherwise its plain
     * definition. Both are stated as the deterministic instruction Pal may
     * only rephrase — it must never invent an application the data doesn't
     * contain, which is why the definition fallback is explicitly labelled as
     * a definition here rather than passed off as a real-world use.
     *
     * @param  array{source:string, text:?string, application_type:?string}  $relevance
     */
    public static function practiceMotivationInstruction(ConceptNode $node, string $conceptName, array $relevance): ?string
    {
        $text = trim((string) ($relevance['text'] ?? ''));
        if ($text === '') {
            return null; // nothing honest to say — say nothing rather than pad it
        }

        $lines = [];
        $lines[] = sprintf(
            'The student has just understood %s: %s and is about to start practising it. They may need a reason to push through the practice.',
            $node->node_type,
            $node->label
        );

        if (($relevance['source'] ?? null) === 'real_world') {
            $lines[] = ($relevance['application_type'] ?? null) !== null
                ? sprintf('A real use of %s (%s): %s', $conceptName, $relevance['application_type'], $text)
                : sprintf('A real use of %s: %s', $conceptName, $text);
        } else {
            // Definition, not an application — say so, so Pal doesn't dress a
            // definition up as a real-world example the data never claimed.
            $lines[] = sprintf('What %s actually is: %s', $conceptName, $text);
            $lines[] = 'Do not invent a real-world application — none is available for this concept.';
        }

        $lines[] = 'In one or two short sentences, encourage them to practise this now, using only the fact above. Do not add new facts, do not restate the question, do not give away any answer.';

        return implode(' ', $lines);
    }

    /**
     * The same nudge written FOR the student, used when Pal can't render.
     *
     * Every other instruction here is engine-facing prose ("The student has
     * just understood…") that reads as meta-commentary if it ever reaches a
     * student's screen. That is tolerable for a teach/practice instruction,
     * which is at least about the material — but a motivational line that
     * talks about the student in the third person is worse than no line at
     * all, so this pass supplies a plain student-facing version for the
     * provider-unavailable path rather than leaking the instruction.
     *
     * @param  array{source:string, text:?string, application_type:?string}  $relevance
     */
    public static function practiceMotivationFallback(string $conceptName, array $relevance): ?string
    {
        $text = trim((string) ($relevance['text'] ?? ''));
        if ($text === '') {
            return null;
        }

        return ($relevance['source'] ?? null) === 'real_world'
            ? sprintf('Worth the practice: %s Get this solid and you can actually use it.', rtrim($text, '.') . '.')
            : sprintf('Quick reminder of why this matters — %s A few practice questions and this one is yours.', rtrim($text, '.') . '.');
    }

    /**
     * A short recap attached to a due spaced-retrieval check, so the reminder
     * carries something to jog the memory rather than assuming the student
     * remembers what a node label meant weeks (or months) later. Deterministic
     * string assembly, same as the other *Instruction() methods.
     */
    public static function retentionSummaryInstruction(
        ConceptNode $node,
        string $conceptName,
        int $daysSinceMastery,
        ?string $material = null
    ): ?string {
        $material = trim((string) $material);

        // No approved material behind it means the only way Pal could produce
        // a "refresher" is by inventing one from a node label. A student
        // returning after 180 days deserves silence over a confident summary
        // of something nobody wrote — so the recap is omitted and they go
        // straight to the check.
        if ($material === '') {
            return null;
        }

        $lines = [];
        $lines[] = sprintf(
            'The student mastered %s: %s (part of %s) about %d day(s) ago and is due a short spaced-review check.',
            $node->node_type,
            $node->label,
            $conceptName,
            max(1, $daysSinceMastery)
        );
        $lines[] = 'Give a two-line refresher as a memory jog before the check, using ONLY this approved material and adding no facts it does not contain: ' . strip_tags($material);
        $lines[] = 'Do not re-teach the concept from the beginning, and do not reveal or hint at the review questions that follow.';

        return implode(' ', $lines);
    }

    /**
     * The same recap written FOR the student, used when Pal can't render.
     *
     * Same reasoning as practiceMotivationFallback(): the instruction above is
     * engine-facing ("The student mastered…") and reads as meta-commentary if
     * it reaches a screen verbatim, which is worse than showing nothing.
     */
    public static function retentionSummaryFallback(string $conceptName, ?string $material = null): ?string
    {
        $material = trim((string) $material);
        if ($material === '') {
            return null;
        }

        // Kept genuinely brief — this is a memory jog before a check, not a
        // second teaching screen.
        $jog = strip_tags($material);
        if (mb_strlen($jog) > 240) {
            $jog = rtrim(mb_substr($jog, 0, 240)) . '…';
        }

        return sprintf('Quick refresher on %s before the check — %s', $conceptName, $jog);
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
            'check_understanding' => [
                'title' => 'Quick check',
                'subtitle' => "A couple of questions to see whether the explanation of {$concept} landed.",
                'reasons' => ['This part of the concept has been taught but not yet checked'],
                'cta_label' => 'Check my understanding',
            ],
            'reteach' => [
                'title' => "Let's try that a different way",
                'subtitle' => "The first explanation of {$concept} didn't quite land — here's another angle.",
                'reasons' => ['The check of understanding was not passed'],
                'cta_label' => 'Show me again',
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
                // No longer a dead end: practice stops, but the concept opens
                // onto enrichment and the next eligible concept.
                'subtitle' => "You've cleared {$concept} — practice stops here and the next concept opens up.",
                'reasons' => ['Knowledge and application mastery thresholds are both met'],
                'cta_label' => 'See what opens up',
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
