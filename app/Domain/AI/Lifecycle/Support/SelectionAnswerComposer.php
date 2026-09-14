<?php

namespace App\Domain\AI\Lifecycle\Support;

use App\Domain\AI\Conversation\AnswerComposer;
use App\Domain\AI\Conversation\ResultSet;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\StageOutcome;

/**
 * The answer for a turn that pointed at the previous answer.
 *
 * Two shapes, and they share a rule that is the whole reason this class exists: **the
 * turn answers from what it can actually show.** A module bound to a lookup for this kind
 * of record gets the record in full; a module bound to nothing still answers, from the
 * row the reader was shown a turn ago. Refusing the second case would be pedantry — those
 * fields came out of the database on the previous turn and are no less real for having
 * been read then — and it is what made selecting a row impossible outside admissions.
 *
 * "What information is missing?" is answered here rather than routed separately, because
 * the answer is a property of the record and not a different question: the blank columns
 * are listed beside the filled ones, every time, so the follow-up is already answered
 * before it is asked.
 */
class SelectionAnswerComposer
{
    /** Fields shown before the list is cut, so an answer stays readable. */
    private const MAX_FIELDS = 24;

    /** Rows of a filtered list shown before it is cut. */
    private const MAX_ROWS = 10;

    /**
     * Columns that are plumbing rather than information. Listing them under "not
     * recorded" would invite a user to go and fill in a tenant id.
     */
    private const INTERNAL = [
        'sub_institute_id', 'client_id', 'syear', 'created_at', 'updated_at', 'deleted_at',
        'created_by', 'updated_by', 'password', 'remember_token', 'position',
    ];

    public function __construct(private readonly AnswerComposer $compose)
    {
    }

    /**
     * One record, opened.
     */
    public function detail(StageContext $context): StageOutcome
    {
        $intent = $context->intent;
        $set = ResultSet::fromArray($context->thread['memory']['last_result_set'] ?? null);
        $title = trim((string) ($intent?->slot('record_title') ?? ''));
        $singular = $set?->singular ?? 'record';

        $remembered = $this->rememberedRow($context, $set);
        $loaded = $this->loadedRecord($context, $singular);
        $missingFromTool = $this->declaredMissing($context);

        // The lookup wins where it ran, because it reads the whole record rather than the
        // handful of columns a list projects. The remembered row backfills anything the
        // lookup does not carry, so a title the reader saw never disappears.
        $record = $loaded === null ? $remembered : $loaded + $remembered;

        if ($record === []) {
            return StageOutcome::blocked(
                'The row this refers to is no longer in the conversation, so there is nothing to open.',
                ['intent' => $intent?->key]
            )->withNote(
                'Ask the question that produced the list again, then select from the fresh answer.'
            );
        }

        if ($title === '') {
            $title = (string) ($record['student_name'] ?? $record['name'] ?? ucfirst($singular));
        }

        [$held, $blank] = $this->partition($record);

        $context->setHeadline($this->headline($title, $singular, $intent?->slot('record_position')));

        if ($held !== []) {
            $context->addSection($this->compose->keyValues(
                sprintf('%s details', ucfirst($singular)),
                array_slice($held, 0, self::MAX_FIELDS, true)
            ));
        }

        // Both sources of "missing" are reported, and they are not the same thing. A
        // blank column is a field the record has no value for; a field the module's own
        // validator names is one the estate will *require* before something can happen.
        $required = array_values(array_unique([...$missingFromTool, ...array_slice($blank, 0, 12)]));

        if ($required !== []) {
            $context->addSection($this->compose->records(
                'Not recorded yet',
                array_map(static fn (string $field) => [
                    'title' => ucfirst(str_replace('_', ' ', $field)),
                    'lines' => [],
                    'meta' => in_array($field, $missingFromTool, true)
                        ? ['Needed' => 'before this record can be completed']
                        : [],
                ], $required)
            ));
        }

        $context->addSection($this->compose->text(
            'Where this came from',
            $loaded !== null
                ? sprintf(
                    'Read live through %s. Every field above is a column on the record, not a summary.',
                    implode(', ', $context->executedTools()) ?: 'the module lookup'
                )
                : sprintf(
                    'These are the fields the previous answer returned for this %s, read from the '
                    . 'school database on that turn. The %s module binds no deeper lookup for this '
                    . 'record, so nothing further was requested.',
                    $singular,
                    strtolower($context->module->label)
                )
        ));

        $this->remember($context, $set, $record, $title, $singular, $intent?->slot('record_position'));

        return StageOutcome::ran(
            sprintf(
                'Opened "%s" from the previous answer — %d field%s held, %d not recorded.',
                $title,
                count($held),
                count($held) === 1 ? '' : 's',
                count($blank)
            ),
            [
                'selected_by' => $intent?->matched['resolved_by'] ?? 'a reference to the previous answer',
                'position' => $intent?->slot('record_position'),
                'record_id' => $intent?->slot('record_id'),
                'loaded_through' => $loaded !== null ? $context->executedTools() : [],
                'fields_held' => count($held),
                'fields_blank' => count($blank),
                'validator_required' => $missingFromTool,
            ]
        );
    }

    /**
     * The previous answer, narrowed.
     *
     * Answered from the rows already in memory rather than by re-querying, which is both
     * faster and more honest: the user is asking about the list in front of them, and a
     * fresh query could return a different list and quietly answer a different question.
     */
    public function filter(StageContext $context): StageOutcome
    {
        $intent = $context->intent;
        $set = ResultSet::fromArray($context->thread['memory']['last_result_set'] ?? null);

        if ($set === null || $intent === null) {
            return StageOutcome::blocked(
                'There is no previous answer in this conversation to narrow.',
                []
            );
        }

        $field = (string) $intent->slot('filter_field');
        $value = $intent->slot('filter_value');
        $mode = (string) $intent->slot('filter_mode', 'equals');
        $matched = [];

        foreach ($set->items as $item) {
            $held = $item['row'][$field] ?? null;
            $present = is_scalar($held) && trim((string) $held) !== '';

            if ($mode === 'present' ? $present : ($present && mb_strtolower(trim((string) $held)) === $value)) {
                $matched[] = $item;
            }
        }

        // Renumbered from one, because these rows are the list the reader is now looking
        // at and the next follow-up will point into. Keeping the original positions
        // would print "1., 3." above chips offering "the second enquiry", which is two
        // numbering schemes on one screen and no way to tell which one is meant.
        $matched = array_values(array_map(
            static fn (array $item, int $index) => ['position' => $index + 1] + $item,
            $matched,
            array_keys($matched)
        ));

        $human = str_replace('_', ' ', $field);

        $context->setHeadline(sprintf(
            '%d of %d %s %s.',
            count($matched),
            $set->count(),
            $set->noun,
            $mode === 'present'
                ? sprintf('have a %s', $human)
                : sprintf('have %s %s', $human, $value)
        ));

        $context->addSection($this->compose->records(
            ucfirst($set->noun),
            array_map(fn (array $item) => [
                'id' => $item['id'],
                'title' => sprintf('%d. %s', $item['position'], $item['title']),
                'lines' => [],
                'meta' => $this->meta($item['row']),
            ], array_slice($matched, 0, self::MAX_ROWS)),
            sprintf('None of the %s shown carried that value.', $set->noun)
        ));

        $context->addSection($this->compose->text(
            'What was narrowed',
            sprintf(
                'These are the %d %s the previous answer returned, filtered on %s. Nothing was '
                . 're-queried, so this is the same set of records you were already looking at.',
                $set->count(),
                $set->noun,
                $human
            )
        ));

        // The narrowed list becomes the list a further follow-up points at, so "show the
        // first one" after a filter means the first of the filtered rows.
        if ($matched !== []) {
            $narrowed = new ResultSet(
                module: $set->module,
                tool: $set->tool,
                noun: $set->noun,
                singular: $set->singular,
                idField: $set->idField,
                items: $matched,
                question: $context->question,
                total: count($matched),
            );

            $context->set('result_set', $narrowed->toArray());
            $context->link(['last_result_set' => $narrowed->toArray()]);
        }

        return StageOutcome::ran(
            sprintf('Narrowed the previous answer to %d of %d %s.', count($matched), $set->count(), $set->noun),
            [
                'field' => $field,
                'value' => $value,
                'mode' => $mode,
                'matched' => count($matched),
                'from' => $set->count(),
                'rule' => 'Applied to the rows the previous turn returned. No tool was called, so the '
                    . 'answer cannot disagree with the list it narrows.',
            ]
        );
    }

    // ---------------------------------------------------------------- internals

    /**
     * The row the previous answer showed, by position or by id.
     *
     * @return array<string, mixed>
     */
    private function rememberedRow(StageContext $context, ?ResultSet $set): array
    {
        $intent = $context->intent;

        if ($set === null || $intent === null) {
            return [];
        }

        $position = $intent->slot('record_position');

        if (is_numeric($position)) {
            $item = $set->at((int) $position);

            if ($item !== null) {
                return is_array($item['row'] ?? null) ? $item['row'] : [];
            }
        }

        $title = $intent->slot('record_title');

        if (is_string($title)) {
            $item = $set->byTitle($title);

            if ($item !== null) {
                return is_array($item['row'] ?? null) ? $item['row'] : [];
            }
        }

        return [];
    }

    /**
     * The record a lookup tool returned, if one ran.
     *
     * Recognised by shape rather than by tool name — the record is the first object in
     * the payload that is a row rather than a list — so a lookup added tomorrow needs no
     * change here.
     *
     * @return array<string, mixed>|null
     */
    private function loadedRecord(StageContext $context, string $singular): ?array
    {
        foreach ((array) $context->get('mcp_step_results', []) as $payload) {
            $payload = $this->unwrap($payload);

            if ($payload === null) {
                continue;
            }

            // A payload that names the record after the list it came from is
            // unambiguous, so try that before falling back to shape.
            $named = $payload[str_replace(' ', '_', $singular)] ?? null;

            if (is_array($named) && $named !== [] && ! array_is_list($named)) {
                return $named;
            }

            foreach ($payload as $value) {
                if (is_array($value) && $value !== [] && ! array_is_list($value)) {
                    return $value;
                }

                // A search tool asked for one id answers with a list of one. That is the
                // record, and refusing to read it because of the wrapper would send the
                // turn back to the columns the list projected while the full row sat
                // one array level away.
                if (is_array($value) && count($value) === 1 && array_is_list($value) && is_array($value[0])) {
                    return $value[0];
                }
            }
        }

        return null;
    }

    /**
     * Fields the module's own validator says the record still needs.
     *
     * This is the difference between "this column is blank" and "this record cannot be
     * completed until this column is filled", and only the module knows which is which.
     *
     * @return array<int, string>
     */
    private function declaredMissing(StageContext $context): array
    {
        $fields = [];

        foreach ((array) $context->get('mcp_step_results', []) as $payload) {
            $payload = $this->unwrap($payload);

            if ($payload === null) {
                continue;
            }

            foreach ($payload as $value) {
                if (! is_array($value)) {
                    continue;
                }

                foreach ((array) ($value['missing_fields'] ?? []) as $missing) {
                    if (is_string($missing)) {
                        $fields[] = $missing;
                    } elseif (is_array($missing) && is_string($missing['field'] ?? null)) {
                        $fields[] = $missing['field'];
                    }
                }
            }
        }

        return array_values(array_unique($fields));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function unwrap(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        if (array_key_exists('success', $payload) && is_array($payload['data'] ?? null)) {
            return $payload['success'] === true ? $payload['data'] : null;
        }

        return $payload;
    }

    /**
     * Split a record into what it holds and what it does not.
     *
     * @param  array<string, mixed>  $record
     * @return array{0:array<string, string>, 1:array<int, string>}
     */
    private function partition(array $record): array
    {
        $held = [];
        $blank = [];

        foreach ($record as $field => $value) {
            $field = (string) $field;

            if (in_array($field, self::INTERNAL, true) || str_ends_with($field, '_id')) {
                continue;
            }

            if (is_array($value)) {
                continue;
            }

            if ($value === null || trim((string) $value) === '') {
                $blank[] = $field;

                continue;
            }

            $held[ucfirst(str_replace('_', ' ', $field))] = is_bool($value)
                ? ($value ? 'yes' : 'no')
                : (string) $value;
        }

        return [$held, $blank];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function meta(array $row): array
    {
        $meta = [];

        foreach ($row as $field => $value) {
            if (! is_scalar($value) || trim((string) $value) === '' || str_ends_with((string) $field, '_id')) {
                continue;
            }

            $meta[ucfirst(str_replace('_', ' ', (string) $field))] = (string) $value;
        }

        return array_slice($meta, 0, 4, true);
    }

    private function headline(string $title, string $singular, mixed $position): string
    {
        return is_numeric($position)
            ? sprintf('%s — %s %d from the previous answer.', $title, $singular, (int) $position)
            : sprintf('%s — the %s this conversation has open.', $title, $singular);
    }

    /**
     * Pin this record as the one the thread is about, so "their attendance" resolves.
     *
     * @param  array<string, mixed>  $record
     */
    private function remember(
        StageContext $context,
        ?ResultSet $set,
        array $record,
        string $title,
        string $singular,
        mixed $position
    ): void {
        $item = [
            'position' => is_numeric($position) ? (int) $position : 1,
            'id' => $context->intent?->slot('record_id'),
            'title' => $title,
            'row' => array_filter($record, static fn ($value) => is_scalar($value) || $value === null),
        ];

        $context->set('selected_record', [
            'item' => $item,
            'singular' => $singular,
            'module' => $context->module->key,
            'set' => $set?->toArray(),
        ]);

        $context->link(array_filter([
            'selected_record' => [
                'item' => $item,
                'singular' => $singular,
                'module' => $context->module->key,
            ],
            // A selected row that names a student is the student this thread is about,
            // which is what lets the estate's existing student journeys carry on from a
            // record picked off an admissions or fees list.
            'student_id' => isset($record['student_id']) && is_numeric($record['student_id'])
                ? (int) $record['student_id']
                : null,
            'student_name' => isset($record['student_name']) && is_string($record['student_name'])
                ? $record['student_name']
                : null,
            'enquiry_id' => isset($record['enquiry_id']) && is_numeric($record['enquiry_id'])
                ? (int) $record['enquiry_id']
                : null,
        ], static fn ($value) => $value !== null));
    }
}
