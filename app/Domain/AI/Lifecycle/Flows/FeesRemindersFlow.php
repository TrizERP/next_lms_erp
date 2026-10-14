<?php

namespace App\Domain\AI\Lifecycle\Flows;

use App\Domain\AI\Conversation\ResultSet;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\McpToolCaller;
use App\Services\Mcp\FeesReminderService;
use Illuminate\Support\Carbon;

/**
 * Fee payment reminders, across the turns it takes to do safely.
 *
 *   1. "Send reminders to the 5 students who owe the most."  -> who, and a question: by when?
 *   2. "By 31 October."                                       -> here is exactly what will be sent
 *   3. "Yes."                                                 -> sent; here is what happened to each
 *   4. "Were the reminders sent?"                             -> the record, per student
 *
 * Nothing is sent until step 3, and step 3 sends only the list shown in step 2: the tool is
 * handed that list's fingerprint — students, amounts, deadline, channel and wording — and
 * refuses if the ledger no longer gives the same one.
 *
 * ALL OF IT CAN BE CHANGED BEFORE THE "YES"
 *
 * The deadline ("make it 5 November"), the number of students ("only the top 3"), the channel
 * ("send it by SMS instead") and the wording ("change the message to …") each produce a fresh
 * preview. None of them counts as approval, and an approval of the old preview cannot send the
 * new list.
 *
 * IT KNOWS WHAT IS ON THE SCREEN
 *
 * "This student", "all 10", "the second one" and a name printed a moment ago are read against
 * the list the previous answer showed — the same remembered list the rest of the chat uses — so
 * a follow-up is about the students the person was looking at and never about a re-run of the
 * whole-school summary.
 *
 * NO MODEL IS INVOLVED. The sentence is read with patterns — a count, a minimum amount, a date,
 * a channel, a quoted message, a name — and anything it cannot read stays unset and is asked
 * for. A deadline that was guessed would be printed on a message to a family, and a student
 * picked by a model's reading of a name would be told they owe money.
 *
 * THE STATE LIVES IN `pending_action`, like the admission confirmation, and the answer is
 * rendered by `ReasoningStage`, so the stages stay uniform: they call in, report what came
 * back, and never know there is a conversation shape behind it.
 */
class FeesRemindersFlow
{
    public const KIND = 'fees_reminders';

    public const TOOL = 'fees.send_reminders';

    /** Words that name a group, not a person, after "remind"/"notify". */
    private const GROUP_WORDS = [
        'students', 'student', 'parents', 'parent', 'defaulters', 'defaulter', 'everyone', 'everybody', 'all',
        'them', 'those', 'these', 'the', 'top', 'high', 'highest', 'anyone', 'families', 'guardians', 'who',
        'whose', 'with', 'having', 'owing', 'that', 'class', 'standard', 'a', 'an', 'fee', 'fees', 'pending',
        'outstanding', 'overdue', 'unpaid', 'payment', 'payments', 'about', 'regarding', 'to', 'of', 'in',
        'this', 'sms', 'email', 'text', 'message', 'him', 'her', 'it', 'by', 'via', 'on',
    ];

    private const ORDINALS = [
        'first' => 1, '1st' => 1, 'second' => 2, '2nd' => 2, 'third' => 3, '3rd' => 3, 'fourth' => 4, '4th' => 4,
        'fifth' => 5, '5th' => 5, 'sixth' => 6, '6th' => 6, 'seventh' => 7, '7th' => 7, 'eighth' => 8, '8th' => 8,
        'ninth' => 9, '9th' => 9, 'tenth' => 10, '10th' => 10,
    ];

    public const NOT_AN_ADMINISTRATOR = 'Sending fee reminders is limited to school administrators, and your login is not one. '
        .'An administrator can send them; you can still see who owes and how much.';

    public function __construct(
        private readonly McpToolCaller $mcp,
        private readonly FeesReminderService $reminders,
    ) {
    }

    // ------------------------------------------------------------- recognising

    /**
     * The in-flight reminder on this thread, if there is one.
     *
     * @param  array<string, mixed>  $memory
     * @return array<string, mixed>|null
     */
    public function pending(array $memory): ?array
    {
        $pending = $memory['pending_action'] ?? null;

        return is_array($pending) && ($pending['kind'] ?? null) === self::KIND ? $pending : null;
    }

    /** Wording that abandons the task. Generous on purpose: a user who cannot get out stops using it. */
    public function looksLikeCancel(string $question): bool
    {
        return (bool) preg_match('/\b(cancel|never ?mind|forget it|stop|abort|leave it|not now|discard|don\'?t send|do not send)\b/i', $question)
            || (bool) preg_match('/^\s*(no|nope|nah)[\s.!]*$/i', $question);
    }

    /**
     * Wording that approves the list on the screen — and nothing else.
     *
     * Stricter than "contains yes": a reply that also carries a new date, a number, a channel or
     * new wording is a change to the request, not an approval of the one shown, and approving the
     * wrong list by being polite is the one mistake this must not make.
     */
    public function looksLikeApproval(string $question): bool
    {
        $text = trim($question);

        if (preg_match('/^\s*(yes|y|yep|yeah|yup|ok|okay|confirm(ed)?|approve(d)?|go ahead|proceed|do it|send( it| them| these| the reminders?)?|please send( them| it)?|yes,? (send|confirm|go ahead)[^.!?]*)[\s.!]*$/i', $text)) {
            return true;
        }

        return (bool) preg_match('/\b(yes|confirm|approve|go ahead|proceed|send them|send it)\b/i', $text)
            && self::parseDeadline($text) === null
            && ! preg_match('/\d/', $text)
            && ! preg_match('/\b(sms|e-?mail|text|instead|message|wording)\b/i', $text)
            && ! $this->looksLikeCancel($text);
    }

    // ------------------------------------------------------------- transitions

    /**
     * A new request: read who, by when and by what channel from the sentence, then ask for
     * whatever is missing.
     *
     * @return array<string, mixed>
     */
    public function start(StageContext $context): array
    {
        // Said first, not after a deadline has been asked for and given: a person who may not send
        // should not be walked through a form that ends in a refusal.
        if (! $this->reminders->mayRemind($context->scope)) {
            return $this->blocked(self::NOT_AN_ADMINISTRATOR, null);
        }

        $request = $this->parseRequest($context->question);
        $args = array_filter([
            'top' => $request['top'],
            'min_outstanding' => $request['min_outstanding'],
            'channel' => $request['channel'],
            'message' => $request['message'],
        ], static fn ($value) => $value !== null);

        // "This student" while a CLASS is what the conversation is about: ask which student of that class.
        $classId = self::selectedClassId(is_array($context->thread['memory'] ?? null) ? $context->thread['memory'] : []);

        if ($classId !== null && $request['asked_for_one']) {
            $args['standard_id'] = $classId;
        }

        return $this->proceed($context, $args, $request, true);
    }

    /**
     * Take what the user just said and move the task on.
     *
     * @param  array<string, mixed>  $pending
     * @return array<string, mixed>
     */
    public function advance(StageContext $context, array $pending): array
    {
        if (! $this->reminders->mayRemind($context->scope)) {
            return $this->blocked(self::NOT_AN_ADMINISTRATOR, null);
        }

        $args = is_array($pending['args'] ?? null) ? $pending['args'] : [];
        $state = (string) ($pending['state'] ?? '');

        if ($state === 'ready' && $this->looksLikeApproval($context->question)) {
            return $this->confirm($context, $pending);
        }

        // Anything else is a change to the request: a new date, a different number of students,
        // a minimum amount, another channel, new wording. Merge what was said over what was asked
        // and look again. Who the students are is read again only while that is still unknown.
        $request = $this->parseRequest($context->question, $state === 'need_student');
        $changed = false;

        foreach (['top', 'min_outstanding', 'channel', 'message'] as $key) {
            if ($request[$key] !== null) {
                $args[$key] = $request[$key];
                $changed = true;
            }
        }

        if ($request['deadline'] !== null) {
            $args['deadline'] = $request['deadline'];
            $changed = true;
        }

        if ($state === 'need_student') {
            return $this->proceed($context, $args, $request, false, true);
        }

        $wantsToEdit = (bool) preg_match('/\b(edit|change|reword|rewrite|modify|different|own)\b.{0,25}\b(message|wording|text|sms)\b/i', $context->question);

        if ($request['message'] === null && $wantsToEdit) {
            return $this->afterChange($context, $args, 'Tell me the wording and I will show it back for approval — for example: '
                .'change the message to "Dear {name}, your fee of {amount} is due by {deadline}." '
                .'{name}, {amount} and {deadline} are replaced with each student\'s own details.');
        }

        if (! isset($args['deadline'])) {
            return $this->needDeadline($context, $args, $changed ? null : 'I need a payment deadline before I can prepare these. '
                .'Give a date, for example "by 31 October" or "by 2026-10-31".');
        }

        if (! $changed) {
            return $this->previewState($context, $args, 'Reply "yes" to send exactly these, "cancel" to stop, '
                .'or change the deadline, the number of students, the channel (SMS or email) or the message and I will show the list again.');
        }

        return $this->previewState($context, $args);
    }

    /**
     * The record of reminders already sent, read back from the ledger they were written to.
     *
     * @return array<string, mixed>
     */
    public function status(StageContext $context): array
    {
        if (! in_array($context->scope->role, ['admin', 'staff'], true)) {
            return $this->blocked('Fee reminder history is for staff and administrators.', null);
        }

        $filter = [];

        if (preg_match('/\bfail(ed|ure|ures)?\b/i', $context->question)) {
            $filter['status'] = 'failed';
        }

        if (preg_match('/\bsms\b|\btext message/i', $context->question)) {
            $filter['channel'] = 'sms';
        } elseif (preg_match('/\be-?mail/i', $context->question)) {
            $filter['channel'] = 'email';
        }

        if (preg_match('/\btoday\b/i', $context->question)) {
            $filter['date'] = now()->toDateString();
        }

        $one = $this->selectedStudent($context->thread['memory'] ?? [], $context->question);

        if ($one !== null) {
            $filter['student_id'] = $one[0];
        }

        $history = $this->reminders->history($context->scope, $filter);

        if (! ($history['success'] ?? false)) {
            return $this->blocked((string) ($history['message'] ?? 'The reminder history could not be read.'), null);
        }

        return [
            'state' => 'status',
            'message' => (string) $history['message'],
            'data' => $history['data'],
            'pending' => null,
        ];
    }

    // ------------------------------------------------------------- internals

    /**
     * From "who and by what channel" to the next question or the preview.
     *
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function proceed(StageContext $context, array $args, array $request, bool $fresh, bool $fromNeedStudent = false): array
    {
        $selection = $this->selectStudents($context, $request);

        if ($selection['state'] !== 'ok' && $selection['state'] !== 'none') {
            return $selection + ['pending' => null];
        }

        if ($selection['state'] === 'ok') {
            $args['student_ids'] = $selection['ids'];
            unset($args['standard_id']);
        }

        // "a student", "this student" or "student id" with nobody identified: asking is better
        // than quietly reminding the ten who owe the most.
        $oneStudentWanted = $selection['state'] === 'none'
            && ! isset($args['student_ids'])
            && ($request['asked_for_one'] || $fromNeedStudent)
            && $request['top'] === null && $request['min_outstanding'] === null;

        if ($oneStudentWanted) {
            return $this->needStudent($context, $args, $fromNeedStudent
                ? 'I could not tell which student you meant. Give a name or a student number, or say "the first", "the second"…'
                : null);
        }

        if (! isset($args['deadline']) && $request['deadline'] !== null) {
            $args['deadline'] = $request['deadline'];
        }

        if (! isset($args['deadline'])) {
            return $this->needDeadline($context, $args);
        }

        return $this->previewState($context, $args);
    }

    /**
     * Which students a sentence means: a number, a name, "this student", "all of them", "the
     * second one" or a name that was printed on screen a moment ago.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>  state ok (ids), none, or a refusal state with its message
     */
    private function selectStudents(StageContext $context, array $request): array
    {
        $ids = $request['student_ids'];

        if ($request['names'] !== []) {
            $resolved = $this->resolveNames($context, $request['names']);

            if ($resolved['state'] !== 'ok') {
                return $resolved;
            }

            $ids = array_merge($ids, $resolved['ids']);
        }

        $referenced = $this->referencedStudents($context, $context->question);

        if ($referenced !== null) {
            $ids = array_merge($ids, $referenced);
        }

        $ids = array_slice(array_values(array_unique(array_map('intval', $ids))), 0, FeesReminderService::MAX_RECIPIENTS);

        return $ids === [] ? ['state' => 'none'] : ['state' => 'ok', 'ids' => $ids];
    }

    /**
     * Students the sentence points at on the list the previous answer showed, or null.
     *
     * Only what was on screen can be selected: a student who was never listed cannot be reached
     * by "all of them" or "the third".
     *
     * @return array<int, int>|null
     */
    private function referencedStudents(StageContext $context, string $question): ?array
    {
        $memory = is_array($context->thread['memory'] ?? null) ? $context->thread['memory'] : [];
        $set = ResultSet::fromArray($memory['last_result_set'] ?? null);

        if ($set === null || $set->idField === null) {
            return $this->selectedStudent($memory, $question);
        }

        $ids = [];
        $pick = static function (?array $item) use (&$ids): void {
            if (is_array($item) && is_numeric($item['id'] ?? null)) {
                $ids[] = (int) $item['id'];
            }
        };

        // "all of them", "all 10 students", "everyone listed", "these students"
        if (preg_match('/\b(all|every|each)\b.{0,25}\b(of them|them|students?|listed|above|these|those|ten|\d+)\b|\beveryone\b|\b(these|those) students\b/i', $question)) {
            foreach ($set->items as $item) {
                $pick($item);
            }

            return $ids === [] ? null : $ids;
        }

        // "the first 3 students"
        if (preg_match('/\bfirst\s+(\d{1,2})\b/i', $question, $m)) {
            foreach (array_slice($set->items, 0, (int) $m[1]) as $item) {
                $pick($item);
            }

            return $ids === [] ? null : $ids;
        }

        // "the second student", "the 3rd one"
        foreach (self::ORDINALS as $word => $position) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/i', $question)) {
                $pick($set->at($position));
            }
        }

        if ($ids !== []) {
            return $ids;
        }

        // A name that was printed in full.
        $pick($set->titleMentionedIn($question));

        if ($ids !== []) {
            return $ids;
        }

        return $this->selectedStudent($memory, $question);
    }

    /**
     * "this student", "that student", "him"/"her": the one record the thread is about.
     *
     * @param  array<string, mixed>  $memory
     * @return array<int, int>|null
     */
    private function selectedStudent(array $memory, string $question): ?array
    {
        if (! preg_match('/\b(this|that|the same)\s+student\b|\bto\s+(him|her)\b|\bremind\s+(him|her)\b/i', $question)) {
            return null;
        }

        $selected = $memory['selected_record']['item'] ?? null;

        // The id of a selected CLASS is not a student's id: only a record that is a student counts.
        $isStudent = in_array((string) ($memory['selected_record']['singular'] ?? 'student'), ['student', 'record'], true)
            && ! isset($selected['row']['standard_id']);

        foreach ([$selected['row']['student_id'] ?? null, $isStudent ? ($selected['id'] ?? null) : null, $memory['student_id'] ?? null] as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return [(int) $candidate];
            }
        }

        return null;
    }

    /**
     * The class the conversation is about, when the selected record is a class.
     *
     * @param  array<string, mixed>  $memory
     */
    public static function selectedClassId(array $memory): ?int
    {
        $selected = $memory['selected_record'] ?? null;

        if (! is_array($selected) || ! in_array((string) ($selected['singular'] ?? ''), ['class', 'standard'], true)) {
            return null;
        }

        $id = $selected['item']['row']['standard_id'] ?? $selected['item']['id'] ?? null;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    /**
     * Who would be asked, and the question that is missing: by when?
     *
     * The list is read through the Fees module's own `fees.outstanding_accounts`, so what a
     * person is shown here is the same ranking they would get by asking for it.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function needDeadline(StageContext $context, array $args, ?string $note = null): array
    {
        $list = $this->candidates($context, $args);

        if ($list['accounts'] === []) {
            return $this->blocked($list['empty'], null);
        }

        return [
            'state' => 'need_deadline',
            'message' => $note ?? 'By what date should they pay?',
            'data' => [
                'accounts' => $list['accounts'],
                'selection' => $this->selectionLabel($args),
                'channel' => (string) ($args['channel'] ?? 'email'),
            ],
            'pending' => ['kind' => self::KIND, 'state' => 'need_deadline', 'args' => $args],
        ];
    }

    /**
     * No student was identified, but one was asked for: show who owes the most and ask which.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function needStudent(StageContext $context, array $args, ?string $note = null): array
    {
        $list = $this->candidates($context, ['top' => FeesReminderService::DEFAULT_TOP] + array_diff_key($args, ['student_ids' => 1]));

        if ($list['accounts'] === []) {
            return $this->blocked($list['empty'], null);
        }

        return [
            'state' => 'need_student',
            'message' => $note ?? 'Which student should I remind?',
            'data' => [
                'accounts' => $list['accounts'],
                'selection' => isset($args['standard_id']) ? 'Students in this class who owe the most' : 'Students who owe the most',
                'channel' => (string) ($args['channel'] ?? 'email'),
            ],
            'pending' => ['kind' => self::KIND, 'state' => 'need_student', 'args' => array_diff_key($args, ['student_ids' => 1])],
        ];
    }

    /**
     * The students a reminder would go to, read through the Fees module's own ranking.
     *
     * @param  array<string, mixed>  $args
     * @return array{accounts: array<int, array<string, mixed>>, empty: string}
     */
    private function candidates(StageContext $context, array $args): array
    {
        $query = ['limit' => min((int) ($args['top'] ?? FeesReminderService::DEFAULT_TOP), FeesReminderService::MAX_RECIPIENTS)];

        if (isset($args['student_ids'])) {
            $query['student_ids'] = $args['student_ids'];
        }

        if (isset($args['standard_id'])) {
            $query['standard_id'] = (int) $args['standard_id'];
        }

        $read = $this->mcp->call(
            $context,
            'fees.outstanding_accounts',
            $query,
            'Read who owes the most, so the person can see who a reminder would go to.'
        );

        $accounts = is_array($read['data']['accounts'] ?? null) ? $read['data']['accounts'] : [];
        $minimum = (float) ($args['min_outstanding'] ?? 0);

        $accounts = array_values(array_filter(
            $accounts,
            static fn ($account) => is_array($account) && (float) ($account['outstanding'] ?? 0) > 0 && (float) $account['outstanding'] >= $minimum
        ));

        return [
            'accounts' => array_map(
                fn (array $account) => $account + ['outstanding_label' => $this->reminders->rupees((float) $account['outstanding'])],
                array_slice($accounts, 0, FeesReminderService::MAX_RECIPIENTS)
            ),
            'empty' => $read === null
                ? 'The fee ledger could not be read, so no reminder list was prepared.'
                : 'No student matches: nobody owes fees under that selection, so there is nobody to remind.',
        ];
    }

    /**
     * Ask the tool for the preview — recipients, amounts, deadline, channel and the literal
     * message.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function previewState(StageContext $context, array $args, ?string $note = null): array
    {
        $result = $this->mcp->confirmable(
            $context,
            self::TOOL,
            $args,
            'Show who would be reminded, what each owes, the deadline, the channel and the message — sending nothing.'
        );

        $preview = $result['preview'];

        if (($preview['success'] ?? null) === false) {
            $code = (string) ($preview['error']['code'] ?? '');
            $message = (string) ($preview['message'] ?? 'The reminders could not be prepared.');

            if ($code === 'invalid_deadline') {
                unset($args['deadline']);

                return $this->needDeadline($context, $args, 'That date cannot be used. '.$message);
            }

            if ($code === 'sms_not_configured' || $code === 'invalid_channel' || $code === 'message_too_long') {
                // The rest of the request is fine; only the channel or wording is not. Keep the
                // task alive on the last good setting so the person can change that one thing.
                $kept = array_diff_key($args, ['channel' => 1, 'message' => 1]);

                return [
                    'state' => 'blocked',
                    'message' => $message,
                    'data' => [],
                    'pending' => ['kind' => self::KIND, 'state' => 'ready', 'args' => $kept, 'fingerprint' => ''],
                ];
            }

            return $this->blocked($message, null);
        }

        if (! is_array($preview['data'] ?? null)) {
            return $this->blocked((string) ($result['error'] ?? 'The reminders could not be prepared.'), null);
        }

        $data = $preview['data'];

        if ((int) ($data['recipient_count'] ?? 0) === 0) {
            return [
                'state' => 'nothing_to_send',
                'message' => (string) ($preview['message'] ?? 'Nobody can be reminded.'),
                'data' => $data + ['selection' => $this->selectionLabel($args)],
                // Kept, so that "send it by email instead" or a different channel can be tried.
                'pending' => ['kind' => self::KIND, 'state' => 'ready', 'args' => $args, 'fingerprint' => ''],
            ];
        }

        return [
            'state' => 'ready',
            'message' => $note ?? (string) ($preview['message'] ?? ''),
            'data' => $data + ['selection' => $this->selectionLabel($args)],
            'pending' => [
                'kind' => self::KIND,
                'state' => 'ready',
                'args' => $args,
                'fingerprint' => (string) ($data['fingerprint'] ?? ''),
            ],
        ];
    }

    /**
     * After a change that has no new preview to show yet: say what is needed and keep the state.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function afterChange(StageContext $context, array $args, string $note): array
    {
        return isset($args['deadline']) ? $this->previewState($context, $args, $note) : $this->needDeadline($context, $args, $note);
    }

    /**
     * The approval: two calls by design — the first spends nothing and returns a token, the
     * second spends it — and the list sent is the one whose fingerprint the person saw.
     *
     * @param  array<string, mixed>  $pending
     * @return array<string, mixed>
     */
    private function confirm(StageContext $context, array $pending): array
    {
        $args = is_array($pending['args'] ?? null) ? $pending['args'] : [];
        $fingerprint = (string) ($pending['fingerprint'] ?? '');

        if ($fingerprint === '') {
            return $this->blocked('There is no reviewed list to approve yet. Tell me what to change and I will show it again.', $pending);
        }

        $call = $args + ['approved_fingerprint' => $fingerprint];

        $offer = $this->mcp->confirmable(
            $context,
            self::TOOL,
            $call,
            'Take the confirmation for the list the person approved.'
        );

        if (($offer['token'] ?? null) === null) {
            return $this->blocked((string) ($offer['error'] ?? 'The reminders could not be sent.'), $pending);
        }

        $result = $this->mcp->call(
            $context,
            self::TOOL,
            $call,
            'Send the reminders the person approved, and record each outcome.',
            confirmationToken: $offer['token']
        );

        if ($result === null) {
            return $this->blocked('The sending did not complete, and nothing is recorded as sent.', $pending);
        }

        if (($result['success'] ?? false) !== true) {
            if (($result['error']['code'] ?? '') === 'recipients_changed') {
                return $this->previewState(
                    $context,
                    $args,
                    'The list changed since you reviewed it, so nothing was sent. Here is the current list — approve it to send.'
                );
            }

            return [
                'state' => 'failed',
                'message' => (string) ($result['message'] ?? 'No reminder could be sent.'),
                'data' => is_array($result['error']['details'] ?? null) ? $result['error']['details'] : [],
                'pending' => null,
            ];
        }

        return [
            'state' => 'sent',
            'message' => (string) $result['message'],
            'data' => is_array($result['data'] ?? null) ? $result['data'] : [],
            'pending' => null,
        ];
    }

    /**
     * A named student, found by the Fees module's own student search. Exactly one match or
     * a question — never a guess.
     *
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private function resolveNames(StageContext $context, array $names): array
    {
        $ids = [];

        foreach (array_slice($names, 0, 5) as $name) {
            $found = $this->mcp->call(
                $context,
                'students.search',
                ['query' => $name, 'limit' => 5],
                'Find the student the person named.'
            );

            $students = is_array($found['students'] ?? null)
                ? $found['students']
                : (is_array($found['data']['students'] ?? null) ? $found['data']['students'] : []);

            if ($students === []) {
                return [
                    'state' => 'not_found',
                    'message' => sprintf('I could not find a student named "%s", so nothing was prepared.', $name),
                    'data' => [],
                ];
            }

            if (count($students) > 1) {
                return [
                    'state' => 'ambiguous',
                    'message' => sprintf('More than one student matches "%s". Say which one — by enrolment number — and I will prepare the reminder.', $name),
                    'data' => ['students' => array_map(static fn ($s) => [
                        'name' => $s['student_name'] ?? '',
                        'enrollment_no' => $s['enrollment_no'] ?? '',
                    ], $students)],
                ];
            }

            $ids[] = (int) $students[0]['id'];
        }

        return ['state' => 'ok', 'ids' => array_values(array_unique($ids))];
    }

    /**
     * What the sentence asked for. Anything not in it stays null.
     *
     * @return array{top:?int, min_outstanding:?float, deadline:?string, names:array<int,string>, student_ids:array<int,int>, channel:?string, message:?string, asked_for_one:bool}
     */
    public function parseRequest(string $question, bool $withNames = true): array
    {
        $top = null;

        if (preg_match('/\btop\s+(\d{1,3})\b/i', $question, $m)
            || preg_match('/\b(?:highest|most|biggest|largest)\s+(\d{1,3})\b/i', $question, $m)
            || preg_match('/\b(\d{1,3})\s+(?:highest|biggest|largest|top)\b/i', $question, $m)
            || preg_match('/\b(\d{1,3})\s+(?:students?|defaulters?|accounts?|families|parents?)\b/i', $question, $m)) {
            $top = (int) $m[1];
        }

        $minimum = null;

        if (preg_match('/(?:more than|above|over|greater than|at least|exceeding|owe[sd]?(?: more than)?|>=?)\s*(?:rs\.?|inr|₹)?\s*([\d][\d,]*(?:\.\d+)?)\s*(k|thousand|lakhs?|lacs?|l\b)?/iu', $question, $m)) {
            $minimum = (float) str_replace(',', '', $m[1]);
            $unit = strtolower((string) ($m[2] ?? ''));
            $minimum *= match (true) {
                $unit === 'k' || $unit === 'thousand' => 1000,
                str_starts_with($unit, 'lakh') || str_starts_with($unit, 'lac') || $unit === 'l' => 100000,
                default => 1,
            };

            // A bare number after "owe" with no unit that is really a count ("owe 5 students")
            // is not an amount.
            if ($minimum < 100 && preg_match('/\bowe[sd]?\s+\d/i', $question) && $top === null) {
                $minimum = null;
            }
        }

        $ids = [];

        if ($withNames && preg_match_all('/\bstudent(?:\s+(?:id|no\.?|number))?\s*#?\s*(\d{1,9})\b/i', $question, $found)) {
            $ids = array_slice(array_values(array_unique(array_map('intval', $found[1]))), 0, FeesReminderService::MAX_RECIPIENTS);
        }

        $channel = null;

        // "text" on its own is not a channel ("change the message text"); "SMS", "text message",
        // "text them" and "by text" are.
        if (preg_match('/\bsms\b|\btext messages?\b|\btext (?:them|him|her)\b|\b(?:by|via|over) text\b/i', $question)
            && ! preg_match('/\be-?mail(?:ed)?\s+(?:it\s+)?instead\b/i', $question)) {
            $channel = 'sms';
        } elseif (preg_match('/\be-?mail(?:ed|s)?\b/i', $question)) {
            $channel = 'email';
        }

        return [
            'top' => $top,
            'min_outstanding' => $minimum,
            'deadline' => self::parseDeadline($question),
            'names' => $withNames ? $this->namesIn($question) : [],
            'student_ids' => $ids,
            'channel' => $channel,
            'message' => $this->messageIn($question),
            'asked_for_one' => $withNames && $ids === [] && $top === null
                && (bool) preg_match('/\b(?:a|one|this|that|the|single)\s+student\b|\bstudent\s+id\b|\bto\s+(?:him|her)\b/i', $question),
        ];
    }

    /**
     * Wording the person supplied: quoted text after "message", or text after "message:".
     * A message the person did not give is null — it is never invented.
     */
    private function messageIn(string $question): ?string
    {
        if (preg_match('/\b(?:message|wording|text|sms|reminder)\b[^"“\n]{0,40}["“]([^"”]{8,480})["”]/is', $question, $m)) {
            return trim($m[1]);
        }

        if (preg_match('/\b(?:message|wording)\s*(?:to|as|is|:)\s*:?\s*([^\n]{12,480})$/is', $question, $m)
            && preg_match('/\b(?:change|edit|use|set|make|reword|rewrite|modify)\b/i', $question)) {
            return trim($m[1], " \t\"'“”");
        }

        return null;
    }

    /**
     * A date in the sentence, as YYYY-MM-DD, or null.
     *
     * Day first, as everywhere in this estate. A date with no year means its next
     * occurrence, unless it passed only days ago, in which case it is returned as written so the
     * tool can refuse it. A date that has already passed is returned as written — the tool is
     * what refuses it, with a reason the person can read.
     */
    public static function parseDeadline(string $text, ?Carbon $today = null): ?string
    {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $lower = mb_strtolower($text);
        $months = [
            'jan' => 1, 'january' => 1, 'feb' => 2, 'february' => 2, 'mar' => 3, 'march' => 3, 'apr' => 4, 'april' => 4,
            'may' => 5, 'jun' => 6, 'june' => 6, 'jul' => 7, 'july' => 7, 'aug' => 8, 'august' => 8,
            'sep' => 9, 'sept' => 9, 'september' => 9, 'oct' => 10, 'october' => 10, 'nov' => 11, 'november' => 11,
            'dec' => 12, 'december' => 12,
        ];
        $monthPattern = implode('|', array_keys($months));

        $build = static function (int $year, int $month, int $day): ?string {
            return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
        };

        $nextOccurrence = static function (int $month, int $day) use ($today, $build): ?string {
            $candidate = $build((int) $today->year, $month, $day);

            // Passed this year. Only a date well behind us means next year ("5 January" said in
            // December). One that went by days ago is a date the person has already missed, and is
            // returned as it is so the tool can refuse it - rolling "1 October", said on the 13th,
            // to next October would tell a family to pay a year from now.
            if ($candidate !== null && Carbon::parse($candidate)->lt($today->copy()->subDays(60))) {
                $candidate = $build((int) $today->year + 1, $month, $day);
            }

            return $candidate;
        };

        // 2026-10-31
        if (preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/', $lower, $m)) {
            return $build((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // 31/10/2026, 31-10-2026, 31.10.26 (day first)
        if (preg_match('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2}|\d{4})\b/', $lower, $m)) {
            $year = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];

            return $build($year, (int) $m[2], (int) $m[1]);
        }

        // 31/10 (no year)
        if (preg_match('/\b(?:by|before|on|until|till)\s+(\d{1,2})[\/](\d{1,2})\b/', $lower, $m)) {
            return $nextOccurrence((int) $m[2], (int) $m[1]);
        }

        // 31 October 2026, 31st Oct, 31 oct
        if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(?:of\s+)?('.$monthPattern.')\b\.?,?(?:\s+(\d{4}))?/', $lower, $m)) {
            $month = $months[$m[2]];

            return isset($m[3]) && $m[3] !== ''
                ? $build((int) $m[3], $month, (int) $m[1])
                : $nextOccurrence($month, (int) $m[1]);
        }

        // October 31, Oct 31st, October 31 2026
        if (preg_match('/\b('.$monthPattern.')\.?\s+(\d{1,2})(?:st|nd|rd|th)?\b,?(?:\s+(\d{4}))?/', $lower, $m)) {
            $month = $months[$m[1]];

            return isset($m[3]) && $m[3] !== ''
                ? $build((int) $m[3], $month, (int) $m[2])
                : $nextOccurrence($month, (int) $m[2]);
        }

        if (preg_match('/\bday after tomorrow\b/', $lower)) {
            return $today->copy()->addDays(2)->toDateString();
        }

        if (preg_match('/\btomorrow\b/', $lower)) {
            return $today->copy()->addDay()->toDateString();
        }

        if (preg_match('/\b(?:by|before|until|till|on)\s+today\b|\bend of (?:the )?day\b/', $lower)) {
            return $today->toDateString();
        }

        if (preg_match('/\bin\s+(\d{1,3})\s+days?\b/', $lower, $m)) {
            return $today->copy()->addDays((int) $m[1])->toDateString();
        }

        if (preg_match('/\bin\s+(\d{1,2})\s+weeks?\b/', $lower, $m)) {
            return $today->copy()->addWeeks((int) $m[1])->toDateString();
        }

        if (preg_match('/\bnext week\b/', $lower)) {
            return $today->copy()->addWeek()->toDateString();
        }

        if (preg_match('/\b(?:end of (?:the |this )?month|month[- ]end)\b/', $lower)) {
            return $today->copy()->endOfMonth()->toDateString();
        }

        if (preg_match('/\b(?:by|before|until|till|on|this|next)\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/', $lower, $m)) {
            $weekdays = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
            // The next one that is not today: "by friday" said on a Friday means a week on.
            $ahead = ($weekdays[$m[1]] - (int) $today->dayOfWeek + 7) % 7;

            return $today->copy()->addDays($ahead === 0 ? 7 : $ahead)->toDateString();
        }

        return null;
    }

    /**
     * Personal names after a sending verb: "remind Janvi Jangale", "notify Meet Patel".
     * Group words ("students", "defaulters") are never a name, and nothing here is guessed
     * from lower-case text.
     *
     * @return array<int, string>
     */
    private function namesIn(string $question): array
    {
        if (! preg_match('/(?i:remind|notify|email|reminders? to|message)\s+((?:[A-Z][A-Za-z\'\-]+\s*){1,4})/u', $question, $m)) {
            return [];
        }

        $words = preg_split('/\s+/', trim($m[1])) ?: [];
        $kept = [];

        foreach ($words as $word) {
            if (in_array(strtolower($word), self::GROUP_WORDS, true)) {
                break;
            }

            $kept[] = $word;
        }

        return $kept === [] ? [] : [implode(' ', $kept)];
    }

    /**
     * How the selection reads aloud, so the person sees what "the students who owe the most"
     * was taken to mean.
     *
     * @param  array<string, mixed>  $args
     */
    private function selectionLabel(array $args): string
    {
        if (isset($args['student_ids'])) {
            return count($args['student_ids']) === 1 ? 'The student you chose' : 'The students you chose';
        }

        $top = (int) ($args['top'] ?? FeesReminderService::DEFAULT_TOP);
        $top = min(max($top, 1), FeesReminderService::MAX_RECIPIENTS);

        if (isset($args['min_outstanding']) && (float) $args['min_outstanding'] > 0) {
            return sprintf('Students owing at least %s, largest first (at most %d)', $this->reminders->rupees((float) $args['min_outstanding']), $top);
        }

        return sprintf('The %d student%s who owe the most', $top, $top === 1 ? '' : 's');
    }

    /**
     * @param  array<string, mixed>|null  $pending
     * @return array<string, mixed>
     */
    private function blocked(string $message, ?array $pending): array
    {
        return ['state' => 'blocked', 'message' => $message, 'data' => [], 'pending' => $pending];
    }
}
