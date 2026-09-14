<?php

namespace App\Domain\AI\Conversation;

use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\RecordDetail;

/**
 * What is worth asking next, read off what this turn actually produced.
 *
 * The chips under an answer used to come from `ai_suggestions` — a curated list per
 * module, identical for every turn in it. That is fine as an opening menu and useless as
 * a follow-up: a user who has just been shown six admission enquiries is offered the same
 * two sentences they were offered before they asked anything, and the conversation stops.
 *
 * So the suggestions here are derived, per turn, from five things the turn already knows:
 * the rows it returned, the record it opened, the task it is part-way through, the tools
 * the module is bound to, and whether an approval is waiting on a person. Nothing is
 * compiled in. A question is only offered when the state that makes it answerable is
 * present — which is the rule that matters, because a suggestion the assistant cannot
 * then honour is worse than no suggestion at all.
 *
 * `ModuleSuggestions` is not replaced. It remains the fallback for a turn with no state
 * to derive from, which is what an opening question looks like.
 */
class FollowUpComposer
{
    /** More than this under an answer is a menu rather than a conversation. */
    private const LIMIT = 6;

    /** Rows of a list offered by position. Past the third, people scroll instead. */
    private const OFFERED_POSITIONS = 3;

    private const ORDINAL_WORDS = [
        1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth', 5 => 'fifth',
        6 => 'sixth', 7 => 'seventh', 8 => 'eighth', 9 => 'ninth', 10 => 'tenth',
    ];

    /**
     * Columns nobody wants to filter a list by. Ids address a row rather than describe
     * it, and offering "show enquiries with standard id 4" is a question in database.
     */
    private const UNFILTERABLE = [
        'id', 'position', 'sort_order', 'created_at', 'updated_at', 'sub_institute_id',
        'syear', 'academic_year', 'client_id', 'user_id',
    ];

    public function __construct(private readonly RecordDetail $details)
    {
    }

    /**
     * @return array<int, string>
     */
    public function forTurn(StageContext $context): array
    {
        $suggestions = [
            ...$this->forTaskInFlight($context),
            ...$this->forPendingDecision($context),
            ...$this->forOpenRecord($context),
            ...$this->forList($context),
        ];

        return array_slice($this->dedupe($suggestions), 0, self::LIMIT);
    }

    // ---------------------------------------------------------------- branches

    /**
     * A multi-turn task decides its own next step, and it outranks everything else.
     *
     * Somebody half-way through confirming an admission does not want to be invited to
     * browse the list they came from — they want to know what is still outstanding and
     * how to stop.
     *
     * @return array<int, string>
     */
    private function forTaskInFlight(StageContext $context): array
    {
        $flow = $context->get('admissions_flow');

        if (! is_array($flow)) {
            return [];
        }

        return match ((string) ($flow['state'] ?? '')) {
            'ready' => ['Yes, confirm the admission.', 'What information is missing?', 'Cancel.'],
            'collecting' => ['What information is missing?', 'Cancel.'],
            'confirmed' => ['Show pending admission enquiries.'],
            default => [],
        };
    }

    /**
     * A drafted recommendation is a question put to a person, so the reply belongs here.
     *
     * @return array<int, string>
     */
    private function forPendingDecision(StageContext $context): array
    {
        if ($context->pendingRecommendation === null) {
            return [];
        }

        return [
            'Approve the recommendation.',
            'Reject the recommendation.',
            'What evidence supports this?',
        ];
    }

    /**
     * One record is open — what else can honestly be asked about it.
     *
     * Every line here is conditional on state this turn actually has: a missing-fields
     * question only when fields are genuinely blank, a sibling row only when the list is
     * still remembered and has one, the risk journey only where an agent exists.
     *
     * @return array<int, string>
     */
    private function forOpenRecord(StageContext $context): array
    {
        $selected = $context->get('selected_record');

        if (! is_array($selected) || ! is_array($selected['item'] ?? null)) {
            return [];
        }

        $item = $selected['item'];
        $singular = (string) ($selected['singular'] ?? 'record');
        $title = trim((string) ($item['title'] ?? ''));
        $row = is_array($item['row'] ?? null) ? $item['row'] : [];
        $out = [];

        if ($this->hasBlankFields($row)) {
            $out[] = 'What information is missing?';
        }

        foreach (['status', 'state', 'stage'] as $field) {
            if (array_key_exists($field, $row)) {
                $out[] = sprintf('What is the %s of this %s?', $field, $singular);

                break;
            }
        }

        // The next row of the list this record came from. A conversation that has just
        // opened one candidate is very often about to open the next.
        $set = ResultSet::fromArray($context->thread['memory']['last_result_set'] ?? null)
            ?? ResultSet::fromArray($selected['set'] ?? null);

        $position = (int) ($item['position'] ?? 0);

        if ($set !== null && $position > 0 && $set->at($position + 1) !== null) {
            $out[] = sprintf(
                'Show the details of the %s %s.',
                self::ORDINAL_WORDS[$position + 1] ?? 'next',
                $set->singular
            );
        }

        // Only where the agent genuinely exists, and only when the record names the
        // person the agent reasons about. Offered anywhere else it is an invitation into
        // a journey the module cannot start.
        if ($title !== '' && $context->module->hasAgent() && isset($row['student_id'])) {
            $out[] = sprintf('Is %s at academic risk?', $title);
        }

        // The record is open, it is an enquiry, and the module can actually confirm one.
        // This is the step the admissions conversation exists to reach: a person who has
        // just read a candidate's details is being asked the question the workflow turns
        // on, rather than being left to discover that "confirm this admission" is a
        // sentence the assistant understands.
        if ($this->canConfirmAdmissions($context) && isset($row['enquiry_id'])) {
            $out[] = $title === ''
                ? 'Confirm this admission.'
                : sprintf('Confirm the admission for %s.', $title);
        }

        if ($set !== null && $set->count() > 1) {
            $out[] = sprintf('Show the %s again.', $set->noun);
        }

        return $out;
    }

    /**
     * Whether this module can carry an admission through to confirmation.
     *
     * Asked of the module's own bindings rather than of its key, so the offer appears
     * exactly where it can be honoured. A module that cannot reach `admissions.confirm`
     * is never invited to confirm anything.
     */
    private function canConfirmAdmissions(StageContext $context): bool
    {
        return in_array('admissions.confirm', $context->module->mcpTools, true);
    }

    /**
     * A list was returned — offer the rows by position, and the filters the rows support.
     *
     * @return array<int, string>
     */
    private function forList(StageContext $context): array
    {
        $set = ResultSet::fromArray($context->get('result_set'));

        if ($set === null) {
            return [];
        }

        $out = [];
        $offered = min(self::OFFERED_POSITIONS, $set->count());

        for ($position = 1; $position <= $offered; $position++) {
            $out[] = sprintf(
                'Show the details of the %s %s.',
                self::ORDINAL_WORDS[$position] ?? (string) $position,
                $set->singular
            );
        }

        // A list of enquiries is a list of decisions waiting to be made, so the offer to
        // make one belongs here rather than three questions later.
        //
        // Phrased against a specific row on purpose. "Do you want to confirm an
        // admission?" cannot be honoured — a bare yes names no candidate out of ten, and
        // guessing which one would be a guess about a student record. Naming the first
        // row asks the same question in a form the assistant can actually answer, and the
        // per-row detail offers above cover choosing a different one.
        if ($this->canConfirmAdmissions($context) && $set->idField === 'enquiry_id') {
            $first = $set->at(1);

            if ($first !== null) {
                $out[] = trim((string) ($first['title'] ?? '')) === ''
                    ? sprintf('Confirm the admission for the first %s.', $set->singular)
                    : sprintf('Confirm the admission for %s.', $first['title']);
            }
        }

        foreach ($this->filtersFor($set) as $filter) {
            $out[] = $filter;
        }

        if ($set->truncated()) {
            $out[] = sprintf('Show all %d %s.', $set->total, $set->noun);
        }

        return $out;
    }

    /**
     * Questions that narrow this list, built from the values the rows actually hold.
     *
     * Two shapes, and both are answerable from the rows already in memory — which is
     * why they can be offered without knowing whether the tool that produced the list
     * supports a matching filter argument. "Which enquiries have a followup date?" is a
     * question about the answer on screen, not a new query.
     *
     * @return array<int, string>
     */
    private function filtersFor(ResultSet $set): array
    {
        if ($set->count() < 2) {
            return [];
        }

        $out = [];

        foreach ($this->filterableFields($set) as $field) {
            $human = str_replace('_', ' ', $field);

            // A field only some rows carry is the difference between those rows, which is
            // usually the next thing a reader wants to see.
            if ($this->isPartiallyPopulated($set, $field)) {
                $out[] = sprintf('Which %s have a %s?', $set->noun, $human);

                continue;
            }

            $category = $this->commonestCategory($set, $field);

            if ($category !== null) {
                // Phrased as "where X is Y" rather than "with Y X", because the second
                // form only reads correctly for some fields — "with new status" is fine
                // and "with 6 standard name" is not, and the composer has no way to know
                // which noun it is holding.
                $out[] = sprintf('Show %s where %s is %s.', $set->noun, $human, $category);
            }

            if (count($out) >= 3) {
                break;
            }
        }

        return $out;
    }

    /**
     * A value at least two rows share, or null.
     *
     * Sharing is what makes a value a category rather than an identifier. A mobile number
     * appears once, so "show enquiries where mobile is 9800000001" is a question with a
     * one-row answer the reader can already see — while a status two rows share genuinely
     * splits the list.
     */
    private function commonestCategory(ResultSet $set, string $field): ?string
    {
        $counts = [];

        foreach ($set->items as $item) {
            $value = $item['row'][$field] ?? null;

            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }

            $value = trim((string) $value);

            // A long value is prose, not a category, and belongs in a filter no more than
            // a phone number does.
            if (mb_strlen($value) > 24) {
                continue;
            }

            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        arsort($counts);
        $best = array_key_first($counts);

        return $best !== null && $counts[$best] >= 2 && count($counts) >= 2 ? (string) $best : null;
    }

    /**
     * True when some rows carry this field and some do not.
     */
    private function isPartiallyPopulated(ResultSet $set, string $field): bool
    {
        $held = 0;

        foreach ($set->items as $item) {
            $value = $item['row'][$field] ?? null;

            if (is_scalar($value) && trim((string) $value) !== '') {
                $held++;
            }
        }

        return $held > 0 && $held < $set->count();
    }

    /**
     * @return array<int, string>
     */
    private function filterableFields(ResultSet $set): array
    {
        $fields = [];

        foreach ($set->populatedFields() as $field) {
            if ($field === $set->idField || in_array($field, self::UNFILTERABLE, true)) {
                continue;
            }

            // An identifier describes a row rather than a group of them, so filtering by
            // one is selecting a row the harder way. Contact details are the same shape:
            // near-unique per person, and never the category a reader means.
            // Names are deliberately not excluded. "Standard name" is a perfectly good
            // cut and "student name" is not, and what separates them is whether rows
            // share the value — which commonestCategory() decides from the rows rather
            // than from a guess about what the column is called.
            if (preg_match('/(_id|_no|_code|number|mobile|phone|email|url|link)$/i', $field)) {
                continue;
            }

            $fields[] = $field;
        }

        // Fewest distinct values first, because that is what a category looks like: a
        // status shared by half the list is a more useful cut than a date nobody repeats.
        usort(
            $fields,
            static fn (string $a, string $b) => count($set->valuesOf($a)) <=> count($set->valuesOf($b))
        );

        return $fields;
    }

    /**
     * True when the record carries columns it has no value for.
     *
     * Read off the row rather than off a schema, so "what is missing?" is only offered
     * when there is something to report — and what it reports is the estate's own idea
     * of the fields this record should have, not a list somebody wrote here.
     *
     * @param  array<string, mixed>  $row
     */
    private function hasBlankFields(array $row): bool
    {
        foreach ($row as $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $suggestions
     * @return array<int, string>
     */
    private function dedupe(array $suggestions): array
    {
        $seen = [];
        $out = [];

        foreach ($suggestions as $suggestion) {
            $suggestion = trim($suggestion);

            if ($suggestion === '') {
                continue;
            }

            $key = mb_strtolower(preg_replace('/\s+/u', ' ', $suggestion) ?? $suggestion);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $suggestion;
        }

        return $out;
    }
}
