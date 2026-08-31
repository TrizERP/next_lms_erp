<?php

namespace App\Domain\Governance;

/**
 * The governance ladder.
 *
 * Every act of the intelligence layer is one of these verbs, and they are ordered.
 * An agent, a workflow step or a conversational tool is licensed up to a ceiling;
 * anything above that ceiling is refused by GovernanceValidator rather than merely
 * discouraged in a prompt.
 *
 * DETECT   — notice a condition. Reads only.
 * ANALYSE  — gather evidence and build a case. Reads only.
 * EXPLAIN  — state why, with every claim citing evidence.
 * RECOMMEND— draft an action. Still changes nothing.
 * EXECUTE  — change the world. Requires a human Decision first, always.
 *
 * The gap between RECOMMEND and EXECUTE is the human approval gate. It is the whole
 * point of this file: no amount of agent confidence crosses it.
 */
enum Verb: string
{
    case Detect = 'detect';
    case Analyse = 'analyse';
    case Explain = 'explain';
    case Recommend = 'recommend';
    case Execute = 'execute';

    /** Higher rank means more consequential. */
    public function rank(): int
    {
        return match ($this) {
            self::Detect => 1,
            self::Analyse => 2,
            self::Explain => 3,
            self::Recommend => 4,
            self::Execute => 5,
        };
    }

    /** Does this verb change data outside the intelligence tables? */
    public function isConsequential(): bool
    {
        return $this === self::Execute;
    }

    /** Must a human decision exist before this verb may run? */
    public function requiresHumanDecision(): bool
    {
        return $this->isConsequential();
    }

    public function permits(self $requested): bool
    {
        return $requested->rank() <= $this->rank();
    }

    public static function fromName(?string $value, self $fallback = self::Recommend): self
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return self::tryFrom(strtolower(trim($value))) ?? $fallback;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
