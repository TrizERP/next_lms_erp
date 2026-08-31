<?php

namespace App\Domain\AI\Lifecycle\Flows;

use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\McpToolCaller;
use App\Domain\AI\Support\OpenRouterClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Confirming an admission, across however many turns it takes.
 *
 * This is the one genuinely multi-turn task in the platform, and it is the reason
 * conversation memory holds a `pending_action` at all. The estate needs seven fields
 * before an admission can become a student enrolment; a person typically has some of
 * them to hand and has to go and find the rest. So the exchange is:
 *
 *   1. "Confirm the admission for enquiry 21."  -> three fields are missing, here they are
 *   2. "Division B, quota general, enrolled today." -> two accepted, one still missing
 *   3. "Enrollment number 2026-0481."           -> ready; here is what will happen
 *   4. "Yes."                                    -> confirmed, student created
 *
 * The state machine lives here rather than spread across the twelve stages, so the
 * stages stay uniform: they call in, report what came back, and never know there is a
 * conversation shape behind it.
 *
 * Two rules the flow will not bend:
 *
 *   - **Nothing is invented.** Field values come from what the user actually typed. The
 *     model's only job is to say which of the named fields a sentence supplies; a value
 *     it cannot ground in the text is left missing, because a guessed enrollment number
 *     would become a real student record.
 *   - **The last step is a person.** Readiness is computed from the database, never
 *     assumed, and the confirmation itself goes through the tool's own token gate.
 */
class AdmissionsFlow
{
    public const KIND = 'admissions_confirm';

    private const MODEL = 'deepseek/deepseek-chat';

    /** Fields the flow may collect, with the words a person is likely to use. */
    private const FIELD_LABELS = [
        'first_name' => 'first name',
        'last_name' => 'last name',
        'admission_standard' => 'standard or class',
        'admission_division' => 'division or section',
        'student_quota' => 'quota',
        'admission_date' => 'admission date (YYYY-MM-DD)',
        'enrollment_no' => 'enrollment number',
    ];

    public function __construct(
        private readonly McpToolCaller $mcp,
        private readonly OpenRouterClient $llm,
    ) {
    }

    // ------------------------------------------------------------- recognising

    /**
     * The in-flight admission on this thread, if there is one.
     *
     * @param  array<string, mixed>  $memory
     * @return array<string, mixed>|null
     */
    public function pending(array $memory): ?array
    {
        $pending = $memory['pending_action'] ?? null;

        if (! is_array($pending) || ($pending['kind'] ?? null) !== self::KIND) {
            return null;
        }

        return $pending;
    }

    /**
     * Wording that abandons the task.
     *
     * Deliberately generous. A user who wants out and cannot get out will simply stop
     * using the assistant, so a false positive — dropping a flow they meant to continue
     * — costs one retyped sentence, while a false negative traps them.
     */
    public function looksLikeCancel(string $question): bool
    {
        return (bool) preg_match(
            '/\b(cancel|never ?mind|forget it|stop|abort|leave it|not now|discard)\b/i',
            $question
        );
    }

    /** Wording that accepts the pending confirmation. */
    public function looksLikeYes(string $question): bool
    {
        return (bool) preg_match(
            '/\b(yes|yep|yeah|confirm|go ahead|proceed|do it|approve|ok|okay)\b/i',
            $question
        );
    }

    // ------------------------------------------------------------- transitions

    /**
     * Begin, or re-check, the confirmation of one enquiry.
     *
     * @return array<string, mixed>
     */
    public function start(StageContext $context, int $enquiryId): array
    {
        $validation = $this->validate($context, $enquiryId);

        if ($validation === null) {
            return $this->blocked('That admission enquiry could not be read.', null);
        }

        return $this->fromValidation($context, $enquiryId, $validation, []);
    }

    /**
     * Take whatever the user just said and move the task on.
     *
     * @param  array<string, mixed>  $pending
     * @return array<string, mixed>
     */
    public function advance(StageContext $context, array $pending): array
    {
        $enquiryId = (int) ($pending['enquiry_id'] ?? 0);

        if ($enquiryId <= 0) {
            return $this->blocked('The pending admission lost its enquiry reference.', null);
        }

        // A person saying "yes" to a ready admission is the approval, and it is the only
        // thing on this path that writes a student record.
        if (($pending['state'] ?? '') === 'ready' && $this->looksLikeYes($context->question)) {
            return $this->confirm($context, $pending);
        }

        $missing = $this->missingFieldNames($pending);
        $supplied = $missing === []
            ? []
            : $this->extractFields($context->question, $missing, $context);

        if ($supplied !== []) {
            $this->mcp->call(
                $context,
                'admissions.updateEnquiry',
                ['enquiry_id' => $enquiryId, 'updates' => $supplied],
                'Fill in the admission fields the user just supplied.'
            );
        }

        $validation = $this->validate($context, $enquiryId);

        if ($validation === null) {
            return $this->blocked('That admission enquiry could not be re-read.', $pending);
        }

        return $this->fromValidation($context, $enquiryId, $validation, $supplied);
    }

    /**
     * The final step: the tool's own confirmation gate.
     *
     * Two calls by design. The first returns a token and changes nothing; the second
     * spends it. A single call that both previewed and wrote would make the token
     * ceremonial.
     *
     * @param  array<string, mixed>  $pending
     * @return array<string, mixed>
     */
    private function confirm(StageContext $context, array $pending): array
    {
        $enquiryId = (int) $pending['enquiry_id'];

        $preview = $this->mcp->confirmable(
            $context,
            'admissions.confirm',
            ['enquiry_id' => $enquiryId],
            'Confirm the admission the user has just approved.'
        );

        if (($preview['token'] ?? null) === null) {
            return $this->blocked(
                $preview['error'] ?? 'The admission could not be confirmed.',
                $pending
            );
        }

        $result = $this->mcp->call(
            $context,
            'admissions.confirm',
            ['enquiry_id' => $enquiryId],
            'Execute the confirmed admission.',
            confirmationToken: $preview['token']
        );

        if ($result === null) {
            return $this->blocked('The admission confirmation did not complete.', $pending);
        }

        return [
            'state' => 'confirmed',
            'enquiry_id' => $enquiryId,
            'message' => $result['message'] ?? 'The admission has been confirmed.',
            'data' => $result['data'] ?? [],
            'missing' => [],
            'supplied' => [],
            'pending' => null,
        ];
    }

    // ------------------------------------------------------------- internals

    /**
     * @param  array<string, mixed>  $validation
     * @param  array<string, string>  $supplied
     * @return array<string, mixed>
     */
    private function fromValidation(
        StageContext $context,
        int $enquiryId,
        array $validation,
        array $supplied
    ): array {
        $missing = array_values(array_filter(
            (array) ($validation['missing_fields'] ?? []),
            'is_array'
        ));

        if (($validation['already_confirmed'] ?? false) === true) {
            return [
                'state' => 'already_confirmed',
                'enquiry_id' => $enquiryId,
                'message' => 'That admission has already been confirmed.',
                'data' => $validation,
                'missing' => [],
                'supplied' => $supplied,
                'pending' => null,
            ];
        }

        if ($missing !== []) {
            return [
                'state' => 'collecting',
                'enquiry_id' => $enquiryId,
                'message' => sprintf(
                    '%d field%s still needed before this admission can be confirmed.',
                    count($missing),
                    count($missing) === 1 ? ' is' : 's are'
                ),
                'data' => $validation,
                'missing' => $missing,
                'supplied' => $supplied,
                'pending' => [
                    'kind' => self::KIND,
                    'state' => 'collecting',
                    'enquiry_id' => $enquiryId,
                    'missing' => array_column($missing, 'field'),
                ],
            ];
        }

        return [
            'state' => 'ready',
            'enquiry_id' => $enquiryId,
            'message' => 'This admission has everything it needs and is ready to confirm.',
            'data' => $validation,
            'missing' => [],
            'supplied' => $supplied,
            'pending' => [
                'kind' => self::KIND,
                'state' => 'ready',
                'enquiry_id' => $enquiryId,
                'missing' => [],
            ],
        ];
    }

    /**
     * Ask the estate what this admission still needs.
     *
     * Returns the tool's `data` payload, and only when the tool actually succeeded.
     *
     * Both of those are load-bearing and neither was there at first. A ToolResult puts
     * its findings under `data`, so reading `missing_fields` from the top level found
     * nothing — and "nothing missing" is indistinguishable from "ready". The flow
     * cheerfully announced that an admission with four empty required columns was ready
     * to confirm. A failed call did the same thing, for the same reason.
     *
     * So a payload that is not a success, or that does not carry the key this decision
     * turns on, is null here — which the caller reports as blocked rather than as good
     * news.
     *
     * @return array<string, mixed>|null
     */
    private function validate(StageContext $context, int $enquiryId): ?array
    {
        $result = $this->mcp->call(
            $context,
            'admissions.validateConfirmation',
            ['enquiry_id' => $enquiryId],
            'Check what this admission still needs before it can be confirmed.'
        );

        if (! is_array($result) || ($result['success'] ?? false) !== true) {
            return null;
        }

        $data = is_array($result['data'] ?? null) ? $result['data'] : null;

        // `missing_fields` is what readiness is decided from. If the tool did not send
        // it, this flow does not know whether the admission is complete and must not
        // guess in the direction that creates a student record.
        if ($data === null || ! array_key_exists('missing_fields', $data)) {
            return null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $pending
     * @return array<int, string>
     */
    private function missingFieldNames(array $pending): array
    {
        return array_values(array_filter(
            (array) ($pending['missing'] ?? []),
            static fn ($field) => is_string($field) && isset(self::FIELD_LABELS[$field])
        ));
    }

    /**
     * Read the named fields out of what the user typed.
     *
     * Constrained on both sides: the model is told exactly which fields are wanted, and
     * whatever comes back is filtered to that same list before anything is written. A
     * value the model returns for a field nobody asked about is dropped rather than
     * saved, so a loose reply cannot quietly rewrite a name that was already correct.
     *
     * @param  array<int, string>  $missing
     * @return array<string, string>
     */
    private function extractFields(string $question, array $missing, StageContext $context): array
    {
        if ($missing === [] || ! $this->llm->isConfigured()) {
            return [];
        }

        $wanted = [];

        foreach ($missing as $field) {
            $wanted[] = sprintf('- %s (%s)', $field, self::FIELD_LABELS[$field]);
        }

        $response = $this->llm->json(
            [
                [
                    'role' => 'system',
                    'content' => "You extract field values from one sentence. Return JSON only:\n"
                        . "{\"fields\": {\"field_name\": \"value\"}}\n\n"
                        . "Wanted fields:\n" . implode("\n", $wanted) . "\n\n"
                        . "Rules:\n"
                        . "- Only include a field if the sentence actually states its value.\n"
                        . "- Never invent, guess or infer a value. Omit it instead.\n"
                        . "- Use the exact field names above.\n"
                        . "- For a date, use YYYY-MM-DD. 'today' means " . now()->toDateString() . ".\n"
                        . "- For standard or division, return the name the user said; ids are resolved later.",
                ],
                ['role' => 'user', 'content' => $question],
            ],
            self::MODEL,
            maxTokens: 300,
        );

        $fields = is_array($response['fields'] ?? null) ? $response['fields'] : [];
        $clean = [];

        foreach ($fields as $field => $value) {
            if (! is_string($field) || ! in_array($field, $missing, true) || ! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $clean[$field] = $value;
        }

        return $this->resolveReferences($clean, $context);
    }

    /**
     * Turn a standard or division the user named into the id the estate stores.
     *
     * People say "8B", never "standard 2897". A name that matches nothing is dropped
     * rather than written through, because storing "eight" in a column that holds an id
     * would leave the admission looking complete while being unusable.
     *
     * @param  array<string, string>  $fields
     * @return array<string, string>
     */
    private function resolveReferences(array $fields, StageContext $context): array
    {
        foreach (['admission_standard' => 'standard', 'admission_division' => 'division'] as $field => $table) {
            if (! isset($fields[$field]) || is_numeric($fields[$field])) {
                continue;
            }

            $id = Schema::hasTable($table)
                ? DB::table($table)
                    ->where('sub_institute_id', $context->scope->selectedInstituteId)
                    ->where('name', $fields[$field])
                    ->value('id')
                : null;

            $id === null
                ? $fields[$field] = null
                : $fields[$field] = (string) $id;
        }

        return array_filter($fields, static fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>|null  $pending
     * @return array<string, mixed>
     */
    private function blocked(string $message, ?array $pending): array
    {
        return [
            'state' => 'blocked',
            'enquiry_id' => $pending['enquiry_id'] ?? null,
            'message' => $message,
            'data' => [],
            'missing' => [],
            'supplied' => [],
            // The task survives a failed step: a transient error should not throw away
            // the fields the user has already supplied.
            'pending' => $pending,
        ];
    }

    /**
     * A person-readable label for a field, for the answer.
     */
    public static function label(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? str_replace('_', ' ', $field);
    }
}
