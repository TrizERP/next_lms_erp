<?php

namespace App\Domain\AI\Lifecycle\Stages;

use App\Domain\AI\Conversation\ConversationStore;
use App\Domain\AI\Conversation\IntentClassifier;
use App\Domain\AI\Lifecycle\LifecycleStage;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageKey;
use App\Domain\AI\Lifecycle\StageOutcome;

/**
 * Stage 2 — understanding, and the honest state of generation.
 *
 * Two halves live under this label and only one of them usually runs.
 *
 * **Understanding** always runs: the classifier turns a sentence into one governed
 * intent, or reports that it matched none. Not matching is not a failure here and must
 * not halt the turn — the module may still have a model-planned route at stage 4, and
 * halting at stage 2 would make every question outside the intent registry look
 * unanswerable when it is not.
 *
 * **Generation** usually has not happened. Intervention text is rendered inside the
 * workflow, at `generate_activity`, after a human approves — never before. Reporting
 * stage 2 as though generation were part of every turn would be the single most
 * flattering lie in the trace, so the stage says plainly that nothing was generated and
 * where generation actually occurs.
 */
class GenerativeAiStage implements LifecycleStage
{
    public function __construct(
        private readonly IntentClassifier $classifier,
        private readonly ConversationStore $conversations,
    ) {
    }

    public function key(): StageKey
    {
        return StageKey::GenerativeAi;
    }

    public function run(StageContext $context): StageOutcome
    {
        $memory = $context->thread['memory'] ?? [];
        $intent = $this->classifier->classify($context->question, $memory);

        // A console button sends the same sentence a user could type, plus the id it was
        // rendered against. The sentence still drives the intent; the payload only
        // removes ambiguity about which record it applies to, so clicking and typing
        // follow one code path and produce one trace shape.
        $pinned = $this->pinnedIds($context);

        if ($pinned !== []) {
            $intent = $intent->with($pinned);
        }

        [$intent, $inherited] = $this->conversations->resolveReferents($intent, $memory);
        $context->intent = $intent;
        $context->set('inherited_referents', $inherited);

        $data = $intent->toArray() + [
            'inherited_from_earlier_turns' => $inherited,
            'pinned_by_caller' => $pinned,
            'classifier' => 'deterministic lexicon + phrase patterns',
            'confidence_floor' => 0.34,
            'generation' => [
                'ran' => false,
                'where_it_happens' => 'Inside the bound workflow, at the generate_activity step, '
                    . 'after a human approves. Nothing is generated before then.',
            ],
        ];

        $verify = ['api' => 'POST ' . $this->prefix() . '/ask/interpret  {"question": "..."} '
            . '— classification only, nothing is written'];

        if ($intent->isUnknown()) {
            // Skipped, not blocked. The classifier was reached and gave a real answer:
            // "none of my intents fit". Whether that ends the turn is stage 4's call.
            return StageOutcome::skipped(
                'No registered intent matched this question with enough confidence to route it directly.',
                $data
            )->withNote(
                'Planning will decide whether this module can answer it another way. '
                . 'Nothing has been ruled out yet.'
            );
        }

        return StageOutcome::ran(
            sprintf('Understood as "%s" (confidence %.0f%%).', $intent->label, $intent->confidence * 100),
            $data,
            [],
            $verify
        )->withNote(
            'Understanding ran; nothing was generated on this turn. Generation happens inside '
            . 'the workflow, after approval.'
        );
    }

    /**
     * Ids a caller may pin to a turn. Nothing else from the payload is honoured.
     *
     * @return array<string, int>
     */
    private function pinnedIds(StageContext $context): array
    {
        $pinned = [];

        foreach (['case_id', 'student_id', 'recommendation_id', 'workflow_approval_id'] as $key) {
            $value = $context->payload($key);

            if ($value !== null) {
                $pinned[$key] = $value;
            }
        }

        return $pinned;
    }

    private function prefix(): string
    {
        return '/' . trim((string) config('ai.route_prefix', 'api/ai'), '/');
    }
}
