<?php

namespace App\Domain\AI\Lifecycle\Flows;

use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\McpToolCaller;
use App\Services\Mcp\AdministratorCheck;
use App\Services\Mcp\ParentMessageService;
use Illuminate\Support\Facades\DB;

/**
 * A message to parents by SMS or WhatsApp, across the turns it takes to do safely.
 *
 *   1. "Send an SMS to the parents of class 10 A saying school is closed tomorrow."
 *        -> asks for whatever is missing (channel, who, the wording), otherwise shows the preview
 *   2. "Yes."  -> sent; what the provider said about each
 *
 * Nothing is sent until step 2, and step 2 sends only the list shown in step 1: the service is
 * handed that list's fingerprint — channel, wording, students and numbers — and refuses if the
 * database no longer gives the same one. Changing the channel, the class or the wording
 * ("send it by WhatsApp instead", "make it …") produces a fresh preview and is never approval.
 *
 * NO MODEL IS INVOLVED in reading the sentence. A class, a student, a channel and a quoted message
 * are read with patterns; anything not stated stays unset and is asked for. A recipient picked by a
 * model's guess at a name would be messaging a real family.
 *
 * THE STATE LIVES IN `pending_action`, like the fee reminders and the admission confirmation, and the
 * answer is rendered by `ParentMessageAnswer`, so the stages stay uniform.
 */
class ParentMessageFlow
{
    public const KIND = 'parent_message';

    public const TOOL = 'communication.send_parent_message';

    public const NOT_AN_ADMINISTRATOR = 'Sending messages to parents is limited to school administrators, and your login is not one.';

    /** At most this many students are listed when a name matches several. */
    private const AMBIGUOUS_LIMIT = 6;

    public function __construct(
        private readonly ParentMessageService $messages,
        private readonly ?AdministratorCheck $admins = null,
        private readonly ?McpToolCaller $mcp = null,
    ) {
    }

    // ------------------------------------------------------------- recognising

    /**
     * @param  array<string, mixed>  $memory
     * @return array<string, mixed>|null
     */
    public function pending(array $memory): ?array
    {
        $pending = $memory['pending_action'] ?? null;

        return is_array($pending) && ($pending['kind'] ?? null) === self::KIND ? $pending : null;
    }

    public function looksLikeCancel(string $question): bool
    {
        // "Do not send anything yet, show me the preview" is a request to hold, not to abandon the task.
        if (preg_match('/\b(do not|don\'?t)\s+send\b.{0,30}\b(yet|for now|until)\b/i', $question) === 1
            && preg_match('/\b(cancel|never ?mind|forget it|abort|discard)\b/i', $question) !== 1) {
            return false;
        }

        return (bool) preg_match('/\b(cancel|never ?mind|forget it|stop|abort|leave it|not now|discard|don\'?t send|do not send)\b/i', $question)
            || (bool) preg_match('/^\s*(no|nope|nah)[\s.!]*$/i', $question);
    }

    /**
     * Wording that approves the preview on the screen — and nothing else.
     *
     * Stricter than "contains yes": a reply that also names a channel, a class, a number or new wording
     * is a change to the request, and approving the wrong list by being polite is the one mistake
     * this must not make.
     */
    public function looksLikeApproval(string $question): bool
    {
        $text = trim($question);

        if (preg_match('/^\s*(yes|y|yep|yeah|yup|ok|okay|confirm(ed)?|approve(d)?|go ahead|proceed|do it|send( it| them| these| the messages?)?|please send( them| it)?|yes,? (send( it| them| these)?|confirm|go ahead|proceed))[\s.!]*$/i', $text)) {
            return true;
        }

        return (bool) preg_match('/\b(yes|confirm|approve|go ahead|proceed|send them|send it)\b/i', $text)
            && ! preg_match('/\d/', $text)
            && ! preg_match('/\b(sms|whats\s?app|text|instead|message|wording|class|standard|saying)\b/i', $text)
            && ! $this->looksLikeCancel($text);
    }

    // ------------------------------------------------------------- transitions

    /**
     * A new request.
     *
     * @return array<string, mixed>
     */
    public function start(StageContext $context): array
    {
        // Said first: a person who may not send should not be walked through a form that ends in a refusal.
        if (! ($this->admins ?? app(AdministratorCheck::class))->isAdministrator($context->scope)) {
            return $this->settled('blocked', self::NOT_AN_ADMINISTRATOR);
        }

        return $this->resolve($context, [
            'channel' => null,
            'message' => null,
            'standard_id' => null,
            'division_id' => null,
            'student_ids' => [],
            'target_label' => null,
        ], $context->question, true);
    }

    /**
     * A reply on a thread that is part-way through a message.
     *
     * @param  array<string, mixed>  $pending
     * @return array<string, mixed>
     */
    public function advance(StageContext $context, array $pending): array
    {
        $args = is_array($pending['args'] ?? null) ? $pending['args'] : [];
        $state = (string) ($pending['state'] ?? '');

        if ($state === 'ready' && $this->looksLikeApproval($context->question)) {
            return $this->send($context, $args, (string) ($pending['fingerprint'] ?? ''));
        }

        // Anything else is an edit to the request, and an edit is never an approval.
        return $this->resolve($context, $args, $context->question, false, $state);
    }

    /**
     * The saved record of parent messages already sent, with the provider's reply and what is known about delivery.
     *
     * Read through the history tool, not around it, so the turn's sources and the audit trail name what was read.
     *
     * @return array<string, mixed>
     */
    public function status(StageContext $context): array
    {
        if (! in_array($context->scope->role, ['admin', 'staff'], true)) {
            return $this->settled('blocked', 'Parent message history is for staff and administrators.');
        }

        $filter = [];

        if (preg_match('/\bwhats\s?app\b/i', $context->question)) {
            $filter['channel'] = 'whatsapp';
        } elseif (preg_match('/\bsms\b|\btext messages?\b/i', $context->question)) {
            $filter['channel'] = 'sms';
        }

        if (preg_match('/\bfail(ed|ure|ures)?\b/i', $context->question)) {
            $filter['status'] = 'failed';
        }

        if (preg_match('/\bPM-\d{8}-[a-z0-9]{5}\b/i', $context->question, $reference) === 1) {
            $filter['batch'] = strtoupper($reference[0]);
        }

        $history = ($this->mcp ?? app(McpToolCaller::class))->call(
            $context,
            'communication.parent_message_history',
            $filter,
            'Read the saved record of parent messages: who, which channel, the wording, what the provider answered and what is known about delivery.'
        );

        if ($history === null) {
            return $this->settled('blocked', 'The message records could not be read, so nothing can be reported about what was sent.');
        }

        if (! ($history['success'] ?? false)) {
            return $this->settled('blocked', (string) ($history['message'] ?? 'The message history could not be read.'));
        }

        $data = is_array($history['data'] ?? null) ? $history['data'] : [];
        $data['asked_about_delivery'] = (bool) preg_match('/\b(deliver(?:ed|y)?|receiv(?:ed|e)|reach(?:ed)?|arriv(?:ed)?|read|seen|opened)\b/i', $context->question);

        return ['state' => 'status', 'message' => (string) $history['message'], 'data' => $data, 'pending' => null];
    }

    /**
     * Whether SMS and WhatsApp can really reach parents, asked of the providers themselves.
     *
     * Read through the health tool so the turn's sources and the audit trail name what was checked. Nothing is sent.
     *
     * @return array<string, mixed>
     */
    public function health(StageContext $context): array
    {
        if (! in_array($context->scope->role, ['admin', 'staff'], true)) {
            return $this->settled('blocked', 'The messaging check is for staff and administrators.');
        }

        $filter = [];

        if (preg_match('/\bwhats\s?app\b/i', $context->question) && ! preg_match('/\bsms\b/i', $context->question)) {
            $filter['channel'] = 'whatsapp';
        } elseif (preg_match('/\bsms\b/i', $context->question) && ! preg_match('/\bwhats\s?app\b/i', $context->question)) {
            $filter['channel'] = 'sms';
        }

        $health = ($this->mcp ?? app(McpToolCaller::class))->call(
            $context,
            'communication.channel_health',
            $filter,
            'Ask the SMS gateway and Meta whether messages can reach parents, and read what they report about the recent messages.'
        );

        if ($health === null || ! ($health['success'] ?? false)) {
            return $this->settled('blocked', 'The channels could not be checked, so nothing can be said about whether messages reach parents.');
        }

        return ['state' => 'health', 'message' => (string) ($health['message'] ?? ''), 'data' => (array) ($health['data'] ?? []), 'pending' => null];
    }

    // ----------------------------------------------------------------- internal

    /**
     * Read what the sentence adds, then ask for what is still missing or show the preview.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function resolve(StageContext $context, array $args, string $text, bool $fresh, string $previousState = ''): array
    {
        $read = self::parse($text);

        // A reply to "what should it say?" is the wording itself, with or without quotes — all of it. Words in
        // the wording ("SMS Day", "class 9 picnic", "WhatsApp") are not a channel, a class or a student; only a
        // reply that is nothing but a channel ("by WhatsApp") is read as a change of channel.
        if ($previousState === 'need_message' && trim($text) !== '' && ! $this->looksLikeCancel($text)) {
            $channelOnly = preg_match('/^\s*(?:by|via|use|send(?:\s+it)?(?:\s+by|\s+via)?)?\s*(?:sms|whats\s?app(?:\s+message)?)\s*(?:instead)?[.!]?\s*$/i', $text) === 1;

            $read = [
                'channel' => $channelOnly ? $read['channel'] : null,
                'message' => $channelOnly ? null : trim($text, " \t\n\r\0\x0B\"'“”"),
                'class_number' => null,
                'division' => null,
                'student_name' => null,
            ];
        }

        // A reply to "whose parents?" that is just a name is that student: "Aarav Shah".
        if ($previousState === 'need_recipients' && $read['class_number'] === null && $read['student_name'] === null
            && $read['channel'] === null && $read['message'] === null && ! $this->looksLikeCancel($text)
            && preg_match('/^\s*(?:student\s+)?([A-Za-z][A-Za-z .\'-]{1,60}?)\s*[.!]?\s*$/', $text, $name) === 1
            && ! preg_match('/\d/', $text)) {
            $read['student_name'] = trim($name[1]);
        }

        if ($read['channel'] !== null) {
            $args['channel'] = $read['channel'];
        }

        if ($read['message'] !== null) {
            $args['message'] = $read['message'];
        }

        $target = $this->target($context, $read, $text, $previousState);

        if ($target['failure'] !== null) {
            return $this->ask('need_recipients', $target['failure'], $args);
        }

        if ($target['ids'] !== [] || $target['standard_id'] !== null) {
            $args['student_ids'] = $target['ids'];
            $args['standard_id'] = $target['standard_id'];
            $args['division_id'] = $target['division_id'];
            $args['target_label'] = $target['label'];
        }

        if (empty($args['channel'])) {
            return $this->ask('need_channel', 'Should I send it by SMS or by WhatsApp?', $args);
        }

        if (empty($args['student_ids']) && empty($args['standard_id'])) {
            return $this->ask('need_recipients', 'Whose parents should get it? Name a class ("class 10 A") or a student ("the parent of Aarav Shah").', $args);
        }

        if (empty($args['message'])) {
            // Once the student and the channel are known, show what the Parent module's screen shows at this
            // point: who was selected, the registered mobile number, and the saved template, before the wording.
            $details = $this->messages->describe($context->scope, array_filter([
                'channel' => $args['channel'],
                'student_ids' => $args['student_ids'] ?: null,
                'standard_id' => $args['standard_id'],
                'division_id' => $args['division_id'],
            ], static fn ($value) => $value !== null && $value !== []));

            $extra = ($details['success'] ?? false) === true ? ['details' => $details['data']] : [];

            if (($details['success'] ?? false) !== true) {
                // The channel is not set up for this institute, or the selection is not usable: say so now,
                // not after the person has typed a message.
                return $this->settled('failed', (string) ($details['message'] ?? 'The message could not be prepared.'), $args);
            }

            return $this->ask('need_message', 'What should the message say?', $args, $extra);
        }

        $tool = array_filter([
            'channel' => $args['channel'],
            'message' => $args['message'],
            'student_ids' => $args['student_ids'] ?: null,
            'standard_id' => $args['standard_id'],
            'division_id' => $args['division_id'],
        ], static fn ($value) => $value !== null && $value !== []);

        $preview = $this->messages->preview($context->scope, $tool);

        if (($preview['success'] ?? false) !== true) {
            return $this->settled('failed', (string) ($preview['message'] ?? 'The message could not be prepared.'), $args);
        }

        $data = $preview['data'];

        if (($data['fingerprint'] ?? null) === null) {
            return $this->settled('nothing_to_send', (string) $preview['message'], $args, $data);
        }

        return [
            'state' => 'ready',
            'message' => (string) $preview['message'],
            'data' => $data + ['target_label' => $args['target_label'] ?? null],
            'pending' => [
                'kind' => self::KIND,
                'state' => 'ready',
                'args' => $args,
                'fingerprint' => $data['fingerprint'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function send(StageContext $context, array $args, string $fingerprint): array
    {
        $tool = array_filter([
            'channel' => $args['channel'] ?? null,
            'message' => $args['message'] ?? null,
            'student_ids' => ($args['student_ids'] ?? []) ?: null,
            'standard_id' => $args['standard_id'] ?? null,
            'division_id' => $args['division_id'] ?? null,
        ], static fn ($value) => $value !== null && $value !== []);

        $result = $this->messages->send($context->scope, $tool, $fingerprint);

        if (($result['success'] ?? false) === true) {
            return ['state' => 'sent', 'message' => (string) $result['message'], 'data' => (array) $result['data'], 'pending' => null];
        }

        $details = is_array($result['error']['details'] ?? null) ? $result['error']['details'] : [];

        // A refused send keeps nothing pending: the list was not approved as it now stands.
        return ['state' => 'failed', 'message' => (string) ($result['message'] ?? 'Nothing was sent.'), 'data' => $details, 'pending' => null];
    }

    /**
     * Who the sentence names: a class (with an optional division) or one student, resolved from this
     * institute's own records.
     *
     * @param  array{class_number:?string, division:?string, student_name:?string}  $read
     * @return array{ids:array<int,int>, standard_id:?int, division_id:?int, label:?string, failure:?string}
     */
    private function target(StageContext $context, array $read, string $text, string $previousState): array
    {
        $none = ['ids' => [], 'standard_id' => null, 'division_id' => null, 'label' => null, 'failure' => null];
        $institute = (int) $context->scope->selectedInstituteId;

        // "the student with id 4512", a reply to a list of look-alikes.
        if ($previousState === 'need_recipients' && preg_match('/\b(?:id|student)\s*#?\s*(\d{1,9})\b/i', $text, $m) === 1) {
            $row = DB::table('tblstudent')->where('sub_institute_id', $institute)->where('id', (int) $m[1])->first(['id', 'first_name', 'last_name']);

            return $row === null
                ? ['failure' => 'I could not find a student with that id in this school.'] + $none
                : ['ids' => [(int) $row->id], 'label' => trim($row->first_name.' '.$row->last_name)] + $none;
        }

        if ($read['student_name'] !== null) {
            $words = preg_split('/\s+/', trim($read['student_name'])) ?: [];
            $query = DB::table('tblstudent')->where('sub_institute_id', $institute);

            foreach ($words as $word) {
                $query->where(fn ($q) => $q->where('first_name', 'like', $word.'%')->orWhere('middle_name', 'like', $word.'%')->orWhere('last_name', 'like', $word.'%'));
            }

            $rows = $query->orderBy('first_name')->limit(self::AMBIGUOUS_LIMIT + 1)->get(['id', 'first_name', 'middle_name', 'last_name', 'enrollment_no']);

            if ($rows->isEmpty()) {
                return ['failure' => sprintf('I could not find a student called "%s" in this school.', $read['student_name'])] + $none;
            }

            if ($rows->count() > 1) {
                // Enough to tell look-alikes apart: the full name, the GR number and the class this year.
                $classes = DB::table('tblstudent_enrollment as e')
                    ->leftJoin('standard as s', fn ($j) => $j->on('s.id', '=', 'e.standard_id')->on('s.sub_institute_id', '=', 'e.sub_institute_id'))
                    ->leftJoin('division as d', fn ($j) => $j->on('d.id', '=', 'e.section_id')->on('d.sub_institute_id', '=', 'e.sub_institute_id'))
                    ->where('e.sub_institute_id', $institute)
                    ->when($context->scope->academicYear !== null, fn ($q) => $q->where('e.syear', $context->scope->academicYear))
                    ->whereIn('e.student_id', $rows->pluck('id')->all())
                    ->get(['e.student_id', 's.name as standard_name', 'd.name as division_name'])
                    ->keyBy('student_id');

                return ['failure' => 'Several students match "'.$read['student_name'].'": '
                    .$rows->take(self::AMBIGUOUS_LIMIT)->map(function ($r) use ($classes) {
                        $class = $classes->get($r->id);
                        $facts = array_filter([
                            $class === null ? null : 'class '.trim($class->standard_name.' '.$class->division_name),
                            $r->enrollment_no ? 'GR '.$r->enrollment_no : null,
                            'id '.$r->id,
                        ]);

                        return trim(preg_replace('/\s+/', ' ', $r->first_name.' '.$r->middle_name.' '.$r->last_name)).' ('.implode(', ', $facts).')';
                    })->implode('; ')
                    .'. Which one? Reply with "student" and the id.'] + $none;
            }

            $only = $rows->first();

            return ['ids' => [(int) $only->id], 'label' => trim($only->first_name.' '.$only->last_name)] + $none;
        }

        if ($read['class_number'] === null) {
            return $none;
        }

        $syear = $context->scope->academicYear;
        $number = $read['class_number'];

        // Standards of that name that actually have students this year, so a retired or empty "10" is not offered.
        $standards = DB::table('standard as s')
            ->join('tblstudent_enrollment as e', fn ($j) => $j->on('e.standard_id', '=', 's.id')->on('e.sub_institute_id', '=', 's.sub_institute_id'))
            ->where('s.sub_institute_id', $institute)
            ->when($syear !== null, fn ($q) => $q->where('e.syear', $syear))
            ->where(fn ($q) => $q->where('s.name', $number)->orWhere('s.name', 'like', '%-'.$number))
            ->groupBy('s.id', 's.name')
            ->get(['s.id', 's.name']);

        if ($standards->isEmpty()) {
            return ['failure' => sprintf('I could not find a class %s with students this year in this school.', $number)] + $none;
        }

        if ($standards->count() > 1) {
            return ['failure' => 'More than one class matches "'.$number.'": '.$standards->pluck('name')->implode(', ').'. Which one?'] + $none;
        }

        $standardId = (int) $standards->first()->id;
        $label = 'Class '.$standards->first()->name;
        $divisionId = null;

        if ($read['division'] !== null) {
            $divisions = DB::table('division as d')
                ->join('tblstudent_enrollment as e', fn ($j) => $j->on('e.section_id', '=', 'd.id')->on('e.sub_institute_id', '=', 'd.sub_institute_id'))
                ->where('d.sub_institute_id', $institute)
                ->where('e.standard_id', $standardId)
                ->when($syear !== null, fn ($q) => $q->where('e.syear', $syear))
                ->whereRaw('lower(d.name) = ?', [mb_strtolower($read['division'])])
                ->groupBy('d.id', 'd.name')
                ->get(['d.id', 'd.name']);

            if ($divisions->count() !== 1) {
                return ['failure' => sprintf('I could not find division %s in %s this year.', $read['division'], $label)] + $none;
            }

            $divisionId = (int) $divisions->first()->id;
            $label .= ' '.$divisions->first()->name;
        }

        return ['standard_id' => $standardId, 'division_id' => $divisionId, 'label' => $label] + $none;
    }

    // ------------------------------------------------------------------ parsing

    /**
     * What a sentence says about the channel, the audience and the wording. Public and static so the
     * reading can be tested without a database.
     *
     * @return array{channel:?string, message:?string, class_number:?string, division:?string, student_name:?string}
     */
    public static function parse(string $text): array
    {
        $channel = null;

        if (preg_match('/\bwhats\s?app\b/i', $text)) {
            $channel = 'whatsapp';
        } elseif (preg_match('/\b(sms|text message|text them|text the)\b/i', $text)) {
            $channel = 'sms';
        }

        $message = null;

        if (preg_match('/["“”]([^"“”]{2,})["“”]/u', $text, $m) === 1) {
            $message = trim($m[1]);
        } elseif (preg_match('/\b(?:saying|says|that says|message\s+is|with the message|telling them|informing them)\b[\s:,-]*(?:that\s+)?(.{3,})$/is', $text, $m) === 1
            || preg_match('/:\s*(.{3,})$/s', $text, $m) === 1) {
            $message = trim($m[1], " \t\n\r\0\x0B.\"'");
        }

        $classNumber = null;
        $division = null;

        if (preg_match('/\b(?:class|standard|std|grade)\s*(\d{1,2})(?:\s*[-]?\s*([A-Za-z])\b)?/i', $text, $m) === 1) {
            $classNumber = $m[1];
            $letter = $m[2] ?? '';

            // A lowercase "a" or "i" after a class number is an article or a pronoun, not a division.
            if ($letter !== '' && (ctype_upper($letter) || ! in_array($letter, ['a', 'i'], true))) {
                $division = strtoupper($letter);
            }
        }

        $studentName = null;

        if ($classNumber === null
            && preg_match('/\b(?:parents?|guardians?|mother|father)\s+of\s+([A-Za-z][A-Za-z .\'-]{1,40}?)(?=\s+(?:saying|says|that|by|via|on|with|about|through|using)\b|[.,:;"“]|$)/i', $text, $m) === 1) {
            $studentName = trim($m[1]);
        }

        // "Send SMS to Aarav Shah saying …": a student named straight after the channel. Group words
        // ("all", "them", "everyone") are not names.
        if ($classNumber === null && $studentName === null
            && preg_match('/\b(?:sms|text|whats\s?app(?:\s+message)?)\s+(?:to\s+)?(?!(?i:all|them|him|her|these|those|everyone|the|class|parents?|guardians?)\b)([A-Z][A-Za-z.\'-]+(?:\s+[A-Z][A-Za-z.\'-]+){0,3})/u', $text, $m) === 1) {
            $studentName = trim($m[1]);
        }

        return [
            'channel' => $channel,
            'message' => $message,
            'class_number' => $classNumber,
            'division' => $division,
            'student_name' => $studentName,
        ];
    }

    // ------------------------------------------------------------------- states

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function ask(string $state, string $message, array $args, array $extra = []): array
    {
        return [
            'state' => $state,
            'message' => $message,
            'data' => ['target_label' => $args['target_label'] ?? null, 'channel' => $args['channel'] ?? null, 'message_text' => $args['message'] ?? null] + $extra,
            'pending' => ['kind' => self::KIND, 'state' => $state, 'args' => $args],
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function settled(string $state, string $message, array $args = [], array $data = []): array
    {
        return ['state' => $state, 'message' => $message, 'data' => $data, 'pending' => null];
    }
}
