<?php

namespace App\Brain\Intelligence;

/**
 * The management summary — "what is happening", in one short paragraph.
 *
 * WHY THIS IS A CLASS AND NOT A MODEL CALL. Every sentence below is assembled
 * from figures FeesIntelligence has already computed, with the same arithmetic
 * the cards on the screen show. Nothing is generated, predicted or paraphrased
 * by a language model, and there is no configured grounded-AI path in this
 * installation to do it honestly — so the summary is composed, deterministic and
 * reproducible. The same institute-year produces the same words every time,
 * which is what makes it quotable in a management meeting.
 *
 * THE RULES IT FOLLOWS:
 *
 *   - A sentence is emitted only when its inputs exist. No clause is padded with
 *     a zero, and an absent figure removes the sentence rather than filling it.
 *   - It describes, never diagnoses. "Outstanding is concentrated in Class 6"
 *     is a statement about where money sits; "Class 6 is causing the problem" is
 *     a causal claim this layer has no evidence for, so it is never made.
 *   - It never forecasts. Nothing here says what a follow-up would recover.
 */
final class FeesSummary
{
    public function __construct(private readonly FeesIntelligence $fees)
    {
    }

    /**
     * @return array{available: bool, reason: ?string, headline: ?string, sentences: array<int, string>}
     */
    public function compose(): array
    {
        $coverage = $this->fees->coverage();

        if (! $coverage['available']) {
            return [
                'available' => false,
                'reason' => $coverage['reason'],
                'headline' => null,
                'sentences' => [],
            ];
        }

        $position = $this->fees->position();
        $sentences = [];

        // 1. The position itself.
        if ($position['collectionRate'] !== null) {
            $sentences[] = sprintf(
                'Collection stands at %s of the %s billed for this year, leaving %s outstanding across %s.',
                $this->pct($position['collectionRate']),
                Narrative::money($position['demandAmount']),
                Narrative::money($position['outstandingAmount']),
                $this->plural($position['defaulterAccounts'], 'account', 'accounts')
            );
        } elseif ($position['collectedAmount'] > 0) {
            $sentences[] = sprintf(
                '%s has been collected, but no fee structure matches this year\'s enrolments, so a collection rate cannot be calculated.',
                Narrative::money($position['collectedAmount'])
            );
        }

        // 2. Whether collection is even being recorded — this changes what the
        //    outstanding figure MEANS, so it comes before any claim about it.
        if ($position['feeAccounts'] > 0 && $position['payingAccounts'] === 0) {
            $sentences[] = 'No receipt has been recorded against any fee account this year, so the outstanding figure may reflect unrecorded collection rather than unpaid fees.';
        } elseif ($position['feeAccounts'] > 0) {
            $without = $position['feeAccounts'] - $position['payingAccounts'];
            if ($without > 0 && $without / $position['feeAccounts'] >= 0.5) {
                $sentences[] = sprintf(
                    '%s of %s fee accounts carry no receipt at all, so part of this balance may be collection that was never entered.',
                    number_format($without),
                    number_format($position['feeAccounts'])
                );
            }
        }

        // 3. Where it sits — concentration, then the class carrying the most.
        $concentration = $this->fees->concentration();
        // Only when the share is genuinely concentrated. On a 5,000-account roll
        // the ten largest accounts hold about 1% of the balance, and calling that
        // "concentrated" would be the opposite of what the figure says — so the
        // sentence uses the same threshold the concentration RULE fires on.
        $concentrationFloor = (float) config('brain.thresholds.fee_outstanding_concentration', 0.60) * 100;
        if ($concentration['available'] && $concentration['share'] !== null
            && $concentration['share'] >= $concentrationFloor) {
            $sentences[] = sprintf(
                'The balance is concentrated: %s of it sits in the %d largest accounts.',
                $this->pct($concentration['share']),
                $concentration['topCount']
            );
        }

        $classes = $this->fees->classes();
        if ($classes !== [] && $position['outstandingAmount'] > 0) {
            $top = $classes[0];
            if ($top['outstandingAmount'] > 0) {
                $share = $top['outstandingAmount'] / $position['outstandingAmount'] * 100;
                $sentences[] = sprintf(
                    '%s carries the largest share at %s (%s of the outstanding total).',
                    $top['label'],
                    Narrative::money($top['outstandingAmount']),
                    $this->pct($share)
                );
            }
        }

        // 4. Aging, because money owed for cycles already past behaves
        //    differently from money not yet due.
        if ($position['overdueAmount'] > 0 && $position['demandAmount'] > 0) {
            $sentences[] = sprintf(
                '%s of the balance relates to %s whose month has already passed.',
                Narrative::money($position['overdueAmount']),
                $this->plural($position['overdueCycles'], 'fee cycle', 'fee cycles')
            );
        }

        // 5. Ledger quality, where the school records cancellations at all.
        $adjustments = $this->fees->adjustments();
        if ($adjustments['available'] && $adjustments['cancelledShareOfCollection'] !== null
            && $adjustments['cancelledShareOfCollection'] >= 25.0) {
            $sentences[] = sprintf(
                '%s was cancelled across %s this year, which is large relative to the collection recorded.',
                Narrative::money($adjustments['cancelledAmount']),
                $this->plural($adjustments['cancelledReceipts'], 'receipt', 'receipts')
            );
        }

        return [
            'available' => $sentences !== [],
            'reason' => $sentences === []
                ? 'There is fee data for this year, but not enough to summarise the position.'
                : null,
            'headline' => $this->headline($position),
            'sentences' => $sentences,
        ];
    }

    /**
     * One line a principal can read in two seconds.
     *
     * Bands rather than adjectives about the school: "collection is behind" is a
     * statement about the number, not a judgement about anybody.
     */
    private function headline(array $position): ?string
    {
        $rate = $position['collectionRate'];
        if ($rate === null) {
            return 'Collection rate cannot be calculated for this year';
        }

        if ($rate >= 90) {
            return sprintf('Collection is on track at %s', $this->pct($rate));
        }
        if ($rate >= 70) {
            return sprintf('Collection is broadly on track at %s, with %s outstanding', $this->pct($rate), Narrative::money($position['outstandingAmount']));
        }
        if ($rate >= 40) {
            return sprintf('Collection is behind at %s, with %s outstanding', $this->pct($rate), Narrative::money($position['outstandingAmount']));
        }

        return sprintf('Collection is well behind at %s, with %s outstanding', $this->pct($rate), Narrative::money($position['outstandingAmount']));
    }

    private function pct(float $value): string
    {
        return ($value >= 10 ? (string) round($value) : rtrim(rtrim(number_format($value, 1), '0'), '.')).'%';
    }

    private function plural(int $count, string $one, string $many): string
    {
        return number_format($count).' '.($count === 1 ? $one : $many);
    }
}
