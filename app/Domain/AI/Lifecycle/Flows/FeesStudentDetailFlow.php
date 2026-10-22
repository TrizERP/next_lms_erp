<?php

namespace App\Domain\AI\Lifecycle\Flows;

use App\Domain\AI\Conversation\Intent;
use App\Domain\AI\Lifecycle\StageContext;
use App\Domain\AI\Lifecycle\Support\McpToolCaller;
use App\Services\Mcp\DateRangeResolver;
use App\Services\Mcp\FeesReminderService;

/**
 * One student's fees: what they owe, and for which months.
 *
 * WHY THIS EXISTS
 *
 * "Show the fee details for MARYA FIROZ KHAN PATHAN" was answered with the whole school's fee
 * summary and a table of classes. `fees_query` has no deterministic route on purpose — the
 * right tool depends on the wording — so such a sentence went to the model planner, and when
 * that could not plan (no provider credential, or its quota spent) the plain fallback read the
 * module's first two whole-school tools. Nothing in that path ever looked for the student.
 *
 * This is the route for the case where the sentence names ONE student, by a student number or
 * by a name, or where a student was picked off a list the previous answer showed. It finds the
 * student through the Fees module's own student search, then reads that student's ledger
 * position and unpaid months through `fees.outstanding_accounts` and `fees.getPending`. It
 * returns that student's records or says plainly that it could not find them — never the
 * school-wide summary.
 *
 * WHEN IT DOES NOT APPLY
 *
 * A sentence about a class, a month, "all students" or the school is not about one student, and
 * `target()` returns null for it so it takes the route it always took. The name check is
 * deliberately conservative: a phrase after "for" or "of" is treated as a name only if it is
 * made of name-like words and none of the words that mean something else.
 *
 * Every read goes through MCP tools bound to the Fees module, scoped to the caller's institute
 * and academic year. No student, amount or month is written into this file.
 */
class FeesStudentDetailFlow
{
    /** Words that mean the phrase is not a person ("fees for class 5", "dues of this month"). */
    private const NOT_A_NAME = [
        'class', 'classes', 'standard', 'standards', 'grade', 'division', 'section', 'school', 'institute',
        'everyone', 'everybody', 'all', 'every', 'each', 'total', 'overall', 'whole', 'students', 'parents',
        'defaulters', 'month', 'months', 'year', 'term', 'quarter', 'week', 'today', 'yesterday', 'tomorrow',
        'this', 'that', 'these', 'those', 'my', 'our', 'the', 'a', 'an', 'fees', 'fee', 'dues', 'due', 'pending',
        'outstanding', 'collection', 'report', 'summary', 'list', 'top', 'highest', 'lowest', 'most', 'least',
        'first', 'second', 'third', 'last', 'next', 'previous', 'above', 'below', 'academic', 'session',
    ];

    /** Trailing words that are not part of a name. */
    private const TRAILING = '/\s+(?:this year|this month|this term|this session|please|now|today|currently|so far|in total|at present|right now|again|too)\s*$/iu';

    public function __construct(
        private readonly McpToolCaller $mcp,
        private readonly FeesReminderService $reminders,
    ) {
    }

    /**
     * Who the sentence is about: ['id' => int], ['name' => string], or null when it is not
     * about one student.
     *
     * @return array{id?: int, name?: string}|null
     */
    public static function target(string $question, ?Intent $intent = null): ?array
    {
        // A student picked off the previous answer arrives as a selected row.
        if ($intent !== null && $intent->key === 'record_detail'
            && $intent->slot('record_id_field') === 'student_id' && is_numeric($intent->slot('record_id'))) {
            return ['id' => (int) $intent->slot('record_id')];
        }

        $slot = $intent?->slot('student_id');

        if (is_numeric($slot) && (int) $slot > 0) {
            return ['id' => (int) $slot];
        }

        if (preg_match('/\bstudent(?:\s+(?:id|no\.?|number))?\s*#?\s*(\d{1,9})\b/i', $question, $m)) {
            return ['id' => (int) $m[1]];
        }

        $candidate = null;

        if (preg_match('/\bhow much (?:does|do|did|is|has|are)\s+(.+?)\s+(?:owe|owes|owed|pay|paid|pending|due)\b/iu', $question, $m)) {
            $candidate = $m[1];
        } elseif (preg_match('/\b(?:is|are)\s+(.+?)\s+(?:behind|overdue|late|unpaid|owing|in arrears)\b/iu', $question, $m)
            || preg_match('/\b(?:has|have|did)\s+(.+?)\s+(?:not paid|paid|pay)\b/iu', $question, $m)) {
            $candidate = $m[1];
        } elseif (preg_match('/\b(?:fees?|dues?|balance|outstanding|pending|breakdown|owes?|owing|details?|status|arrears|payment)\b.{0,30}?\b(?:for|of|about)\s+(.+?)\s*[?.!]*\s*$/iu', $question, $m)) {
            $candidate = $m[1];
        }

        return $candidate === null ? null : self::asName($candidate);
    }

    /**
     * Whether the sentence asks what a particular student PAID, and for which student.
     *
     * "How much did Greeva pay yesterday?" is about receipts on a date — not about what Greeva owes —
     * so it takes a different route from "what does Greeva owe?" even though both name one student.
     * A payment word and a date are both required, so "fee details for Greeva" keeps its answer.
     *
     * A follow-up that names no one ("and on 5 October?", "how much in cash?", "what about last week?")
     * is about the student the thread was just on, when it carries no word that makes it an
     * institute-wide question. Those words win: "fees collected yesterday" after a student is still the
     * whole institute's collection.
     *
     * @param  array<string, mixed>  $memory
     * @return array{id?: int, name?: string}|null
     */
    public static function paymentTarget(string $question, array $memory, ?Intent $intent = null): ?array
    {
        $pays = (bool) preg_match('/\b(pay|paid|payments?|collected|received|receipts?|deposit(?:ed)?|how much)\b/iu', $question);
        $dated = DateRangeResolver::mentionsDate($question);

        // Two students over the same period.
        $compare = self::comparisonNames($question);

        if ($compare !== null) {
            return $dated ? ['compare' => $compare] : null;
        }
        $last = is_array($memory['last_period'] ?? null) ? $memory['last_period'] : null;
        $lastStudent = $last !== null && is_numeric($last['student_id'] ?? null) ? (int) $last['student_id'] : null;

        $named = self::target($question, $intent);

        // "he", "she", "them" are not names, whatever the sentence shape makes of them.
        if ($named !== null && isset($named['name']) && preg_match('/^(?:he|she|him|her|his|they|them|their|it|this|that|same)$/iu', $named['name'])) {
            $named = null;
        }

        if ($named !== null) {
            $samePeriod = $lastStudent !== null && preg_match('/\b(same|that)\s+(?:period|day|date|time|range)\b/iu', $question);

            if ($pays && ($dated || $samePeriod)) {
                return $named;
            }

            // "How much did Greeva pay?" asks about money received but names no day. The ledger answer to that
            // sentence is a balance, which is the one thing it must not be. Ask which period instead.
            if (! $dated && preg_match('/\bhow much\b.*\b(?:did|has|have)\b.*\b(?:pay|paid)\b|\bwhat\b.*\bdid\b.*\bpay\b/iu', $question)) {
                return $named + ['needs_period' => true];
            }

            return null;
        }

        // Nobody is named. This is a follow-up about the student the thread was just on, which is only
        // known from a payment answer (it recorded who and when) or, failing that, a student picked off
        // a list. A follow-up is elliptical — "and on 14 August?" has no payment word — so for a student
        // the thread is already on, a date alone is enough; for one merely picked, it must say it is
        // about paying.
        $selected = $lastStudent;
        $record = $memory['selected_record']['item'] ?? null;

        if ($selected === null && is_array($record) && ($memory['selected_record']['singular'] ?? null) === 'student' && is_numeric($record['id'] ?? null)) {
            $selected = (int) $record['id'];
        }

        if ($selected === null || ! ($lastStudent !== null ? ($dated || $pays) : ($dated && $pays))) {
            return null;
        }

        $institutional = (bool) preg_match('/\b(fees?|school|institute|everyone|everybody|all|total|students|collection|overall|whole)\b/iu', $question);
        $pronoun = (bool) preg_match('/\b(he|she|him|her|his|hers|they|them|their|this student|that student|same student)\b/iu', $question);

        return $pronoun || (! $institutional && str_word_count($question) <= 9) ? ['id' => $selected] : null;
    }

    /**
     * Whether the sentence asks for individual students rather than classes, and which.
     *
     * "Show students with pending fees", "Which students owe the most?" and — with a class on
     * screen — "show me the student's fee detail" all want PEOPLE, ranked by what they owe.
     * Class-wise, summary and count questions keep their existing answers.
     *
     * "The details of these students" after a list of students means THOSE students: their ids are
     * returned so the ledger is read for exactly the people on screen, with their unpaid months.
     *
     * @param  array<string, mixed>  $memory
     * @return array{limit: int, standard_id: ?int, student_ids?: array<int, int>}|null
     */
    public static function listTarget(string $question, array $memory): ?array
    {
        if (preg_match('/\bclass[\s-]?wise\b|\bby class\b|\bper class\b|\bclasses\b|\bhow many\b|\bcount\b|\bnumber of\b|\bcollection\b|\bsummary\b|\boverview\b|\bposition\b|\bremind|\bnotify\b|\bsend\b|\bsms\b/i', $question)) {
            return null;
        }

        $listed = self::listedStudentIds($memory);

        if ($listed !== [] && (
            preg_match('/\\b(these|those|the above|the listed|same)\\b.{0,20}\\bstudents?\\b/i', $question)
            || preg_match('/\\b(details?|breakdown|fees?|dues?|unpaid|months?)\\b.{0,30}\\b(of|for|about)\\s+(them|these|those|the above|the listed)\\b/i', $question)
        )) {
            return ['limit' => count($listed), 'standard_id' => null, 'student_ids' => $listed];
        }

        $classId = self::classNamedIn($question, $memory) ?? FeesRemindersFlow::selectedClassId($memory);

        $asksForPeople = preg_match('/\bstudents?\b.{0,40}\b(pending|unpaid|outstanding|owe[sd]?|owing|dues?|overdue|defaulters?|arrears)\b/i', $question)
            || preg_match('/\b(who|whom)\b.{0,25}\b(owes?|owing|pending|unpaid|overdue)\b/i', $question)
            || preg_match('/\bdefaulters?\b/i', $question)
            || preg_match('/\b(top|highest|largest|biggest)\b.{0,30}\b(outstanding|pending|dues?|owe\w*|defaulters?|unpaid)\b/i', $question);

        // With a class in play, "the student's fee detail" / "students in this class" mean its students.
        $asksAboutClassStudents = $classId !== null
            && preg_match('/\bstudents?\b/i', $question)
            && ! preg_match('/\b(first|second|third|fourth|fifth)\s+(student|one)\b/i', $question);

        if (! $asksForPeople && ! $asksAboutClassStudents) {
            return null;
        }

        $limit = preg_match('/\b(?:top|first|highest|largest)\s+(\d{1,2})\b/i', $question, $m) ? min(max((int) $m[1], 1), 25) : 10;

        return ['limit' => $limit, 'standard_id' => $classId];
    }

    /**
     * The students the last answer listed, by id. Empty unless that answer was a list of students.
     *
     * @param  array<string, mixed>  $memory
     * @return array<int, int>
     */
    private static function listedStudentIds(array $memory): array
    {
        $set = $memory['last_result_set'] ?? null;

        if (! is_array($set) || ($set['id_field'] ?? null) !== 'student_id') {
            return [];
        }

        $ids = [];

        foreach ((array) ($set['items'] ?? []) as $item) {
            $id = $item['row']['student_id'] ?? $item['id'] ?? null;

            if (is_numeric($id) && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }

        return array_slice(array_values(array_unique($ids)), 0, 25);
    }

    /**
     * The standard id of "class 6" when the last answer listed that class.
     *
     * @param  array<string, mixed>  $memory
     */
    private static function classNamedIn(string $question, array $memory): ?int
    {
        if (! preg_match('/\b(?:class|standard|grade)\s+([\w-]{1,6})\b/i', $question, $m)) {
            return null;
        }

        foreach ((array) ($memory['last_result_set']['items'] ?? []) as $item) {
            $row = is_array($item['row'] ?? null) ? $item['row'] : [];
            $name = (string) ($row['standard_name'] ?? '');
            $id = $row['standard_id'] ?? $item['id'] ?? null;

            if ($name !== '' && strcasecmp($name, 'Class ' . $m[1]) === 0 && is_numeric($id)) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * The students who owe, largest balance first — individual people, from the fee ledger of
     * this institute and year.
     *
     * @param  array{limit: int, standard_id: ?int, student_ids?: array<int, int>}  $list
     * @return array<string, mixed>
     */
    private function listStudents(StageContext $context, array $list): array
    {
        $query = ['limit' => $list['limit']];

        // The people on screen, with the months each has not paid.
        if (! empty($list['student_ids'])) {
            $query['student_ids'] = $list['student_ids'];
            $query['include_due_months'] = true;
        }

        if ($list['standard_id'] !== null) {
            $query['standard_id'] = $list['standard_id'];
        }

        $read = $this->mcp->call($context, 'fees.outstanding_accounts', $query, 'Read which students owe the most, as individual people.');
        $accounts = is_array($read['data']['accounts'] ?? null) ? $read['data']['accounts'] : [];
        $accounts = array_values(array_filter($accounts, static fn ($a) => is_array($a) && (float) ($a['outstanding'] ?? 0) > 0));

        if ($accounts === []) {
            return $this->state('list', $read === null
                ? 'The fee ledger could not be read, so I have no students to list.'
                : 'No student owes fees under that selection.', ['accounts' => [], 'standard_id' => $list['standard_id']]);
        }

        $rows = [];

        foreach ($accounts as $account) {
            $contact = $this->reminders->contactSummary($context->scope, (int) $account['student_id']);

            $rows[] = $account + [
                'outstanding_label' => FeesReminderService::formatRupees((float) $account['outstanding']),
                'contact' => $contact,
            ];
        }

        return $this->state('list', '', [
            'accounts' => $rows,
            'standard_id' => $list['standard_id'],
            'limit' => $list['limit'],
            'of_listed' => ! empty($list['student_ids']),
        ]);
    }

    /** @return array{name: string}|null */
    private static function asName(string $candidate): ?array
    {
        $candidate = trim(preg_replace(self::TRAILING, '', trim($candidate)) ?? '', " \t\n\r\0\x0B?.!,\"'“”");
        $candidate = trim(preg_replace('/^(?:the\s+)?student\s+(?:named\s+|called\s+)?/iu', '', $candidate) ?? '');

        // "Pari Dipak Vyas on 30 September 2026", "Greeva yesterday": the date belongs to the question, not
        // the name. Left on, it made the phrase fail the name check and the question lose its student.
        if (preg_match('/^(.+?)\s+(?:on|for|in|during|from|between|since|as of|till|until)\s+(.+)$/iu', $candidate, $m)
            && DateRangeResolver::mentionsDate($m[2])) {
            $candidate = trim($m[1]);
        } elseif (preg_match('/^(.+?)\s+(?:yesterday|today|last week|this week|last month|this month)$/iu', $candidate, $m)) {
            $candidate = trim($m[1]);
        }

        if ($candidate === '' || ! preg_match("/^[\p{L}][\p{L}.'\-]*(?:\s+[\p{L}][\p{L}.'\-]*){0,4}$/u", $candidate)) {
            return null;
        }

        foreach (preg_split('/\s+/', mb_strtolower($candidate)) ?: [] as $word) {
            if (in_array($word, self::NOT_A_NAME, true)) {
                return null;
            }
        }

        return ['name' => $candidate];
    }

    /**
     * Find the student and read their fees.
     *
     * @return array<string, mixed>
     */
    public function run(StageContext $context): array
    {
        $memory = is_array($context->thread['memory'] ?? null) ? $context->thread['memory'] : [];

        // A student and a date, asked about paying, is a question about receipts — not about the ledger.
        $payment = self::paymentTarget($context->question, $memory, $context->intent);
        $target = $payment ?? self::target($context->question, $context->intent);
        $wantsPayments = $payment !== null;

        if ($target === null) {
            $list = self::listTarget($context->question, $memory);

            if ($list !== null) {
                return $this->listStudents($context, $list);
            }

            return $this->state('blocked', 'I could not tell which student you meant. Give a name or a student number.');
        }

        if (! empty($target['needs_period'])) {
            return $this->state('needs_period', sprintf(
                'Which day or period do you mean? Say, for example, "yesterday", "last 7 days" or "13 October 2026", and I will show only what %s paid in it.',
                $target['name'] ?? 'the student'
            ));
        }

        if (isset($target['compare'])) {
            return $this->comparePayments($context, $target['compare'], $memory);
        }

        if (isset($target['id'])) {
            return $wantsPayments
                ? $this->payments($context, $target['id'], null, $memory)
                : $this->detail($context, $target['id'], null);
        }

        $found = $this->mcp->call(
            $context,
            'students.search',
            ['query' => $target['name'], 'limit' => 5, 'active_only' => false],
            'Find the student the person named.'
        );

        $students = is_array($found['students'] ?? null)
            ? $found['students']
            : (is_array($found['data']['students'] ?? null) ? $found['data']['students'] : []);

        if ($students === []) {
            return $this->state('not_found', sprintf('I could not find a student named "%s" in this institute, so I have no fee details to show.', $target['name']));
        }

        if (count($students) > 1) {
            return $this->state('ambiguous', sprintf(
                'More than one student matches "%s". Say which one — by enrolment number — and I will show %s.',
                $target['name'],
                $wantsPayments ? 'what they paid' : 'their fees'
            ), [
                'students' => array_map(static fn ($s) => [
                    'student_id' => $s['id'] ?? null,
                    'name' => $s['student_name'] ?? '',
                    'enrollment_no' => $s['enrollment_no'] ?? '',
                ], $students),
            ]);
        }

        return $wantsPayments
            ? $this->payments($context, (int) $students[0]['id'], $students[0], $memory)
            : $this->detail($context, (int) $students[0]['id'], $students[0]);
    }

    /**
     * The two students a comparison names: "compare what A and B paid in September".
     *
     * @return array{0: string, 1: string}|null
     */
    public static function comparisonNames(string $question): ?array
    {
        if (! preg_match('/\bcompare\b\s+(?:what|how much)?\s*(.+?)\s+(?:and|vs\.?|versus|with|against)\s+(.+?)\s+(?:paid|pay|payments?|collections?|fee payments?)\b/iu', $question, $m)) {
            return null;
        }

        $a = self::asName($m[1]);
        $b = self::asName($m[2]);

        return $a === null || $b === null || mb_strtolower($a['name']) === mb_strtolower($b['name'])
            ? null
            : [$a['name'], $b['name']];
    }

    /**
     * Each named student's payments over the one period, side by side.
     *
     * Either name that matches nobody, or more than one student, stops the comparison and says which:
     * a comparison made against a guessed student is worse than none.
     *
     * @param  array{0: string, 1: string}  $names
     * @param  array<string, mixed>  $memory
     * @return array<string, mixed>
     */
    private function comparePayments(StageContext $context, array $names, array $memory): array
    {
        $reports = [];

        foreach ($names as $name) {
            $found = $this->mcp->call(
                $context,
                'students.search',
                ['query' => $name, 'limit' => 5, 'active_only' => false],
                'Find a student to compare.'
            );

            $students = is_array($found['students'] ?? null)
                ? $found['students']
                : (is_array($found['data']['students'] ?? null) ? $found['data']['students'] : []);

            if ($students === []) {
                return $this->state('not_found', sprintf('I could not find a student named "%s" in this institute, so I cannot compare.', $name));
            }

            if (count($students) > 1) {
                return $this->state('ambiguous', sprintf('More than one student matches "%s". Say which one — by enrolment number — and I will compare what they paid.', $name), [
                    'students' => array_map(static fn ($s) => [
                        'student_id' => $s['id'] ?? null,
                        'name' => $s['student_name'] ?? '',
                        'enrollment_no' => $s['enrollment_no'] ?? '',
                    ], $students),
                ]);
            }

            $one = $this->payments($context, (int) $students[0]['id'], $students[0], []);

            if (($one['state'] ?? '') !== 'payments') {
                return $one;
            }

            $reports[] = $one['data'];
        }

        $label = (string) ($reports[0]['period']['label'] ?? 'the period');
        $on = ($reports[0]['period']['from'] ?? null) === ($reports[0]['period']['to'] ?? null) ? 'on' : 'from';
        $money = static fn ($value): string => FeesReminderService::formatRupees((float) $value);
        [$a, $b] = $reports;
        $totalA = (float) ($a['summary']['total_collected'] ?? 0);
        $totalB = (float) ($b['summary']['total_collected'] ?? 0);
        $nameA = (string) ($a['student']['name'] ?? $names[0]);
        $nameB = (string) ($b['student']['name'] ?? $names[1]);

        $message = match (true) {
            $totalA == 0.0 && $totalB == 0.0 => sprintf('Neither %s nor %s paid anything %s %s.', $nameA, $nameB, $on, $label),
            default => sprintf('%s paid %s and %s paid %s %s %s.', $nameA, $money($totalA), $nameB, $money($totalB), $on, $label),
        };

        return $this->state('payments_compare', $message, ['reports' => $reports]);
    }

    /**
     * What this student actually paid in the period asked about — the receipts, and nothing else.
     *
     * Read through `fees.collection_report` with the student's id, so it is filtered by BOTH the
     * student and the dates. It does not touch the ledger: what the student owes or was charged is
     * a different question, and mixing the two is how "how much did she pay yesterday?" came back as
     * a balance.
     *
     * @param  array<string, mixed>  $memory
     * @param  array<string, mixed>|null  $searchRow
     * @return array<string, mixed>
     */
    private function payments(StageContext $context, int $studentId, ?array $searchRow, array $memory): array
    {
        $arguments = ['student_id' => $studentId, 'limit' => 50];
        $last = is_array($memory['last_period'] ?? null) ? $memory['last_period'] : null;

        if (DateRangeResolver::mentionsDate($context->question)) {
            $arguments['period'] = $context->question;
        } elseif ($last !== null && isset($last['from'], $last['to'])) {
            // A follow-up that names no day is about the day the thread was on.
            $arguments['from_date'] = (string) $last['from'];
            $arguments['to_date'] = (string) $last['to'];
        } else {
            return $this->state('blocked', 'Which day or period do you mean? For example "yesterday" or "13 October 2026".');
        }

        if (preg_match('/\b(cash|online|cheque|dd|upi|card)\b/iu', $context->question, $mode)) {
            $arguments['payment_mode'] = ucfirst(strtolower($mode[1]));
        }

        $read = $this->mcp->call($context, 'fees.collection_report', $arguments, 'Read what this student paid in the period.');
        $report = is_array($read['data']['summary'] ?? null) ? $read['data'] : $read;

        if (! isset($report['summary'], $report['headline'])) {
            return $this->state('blocked', 'I could not read this student\'s payments just now, so nothing is shown.');
        }

        return $this->state('payments', (string) $report['headline'], $report + ['student_id' => $studentId]);
    }

    /**
     * @param  array<string, mixed>|null  $searchRow
     * @return array<string, mixed>
     */
    private function detail(StageContext $context, int $studentId, ?array $searchRow): array
    {
        $ledger = $this->mcp->call(
            $context,
            'fees.outstanding_accounts',
            ['student_ids' => [$studentId], 'limit' => 1],
            'Read this student\'s balance from the fee ledger.'
        );

        $account = is_array($ledger['data']['accounts'][0] ?? null) ? $ledger['data']['accounts'][0] : null;

        $pending = $this->mcp->call(
            $context,
            'fees.getPending',
            ['student_id' => $studentId],
            'Read which fee months this student has not paid.'
        );

        $student = is_array($pending['data']['student'] ?? null) ? $pending['data']['student'] : [];
        $items = is_array($pending['data']['pending_items'] ?? null) ? $pending['data']['pending_items'] : [];

        if ($account === null && $student === [] && $searchRow === null) {
            return $this->state('not_found', sprintf('I could not find fee records for student %d in this institute.', $studentId));
        }

        $periods = [];
        $sum = 0.0;

        foreach ($items as $item) {
            if (! is_array($item) || (float) ($item['remain'] ?? 0) <= 0) {
                continue;
            }

            $sum += (float) $item['remain'];
            // The pay link is deliberately dropped: it points at whatever this server's base URL
            // is configured as, which here is a local address no parent could open.
            $periods[] = [
                'month' => (string) ($item['month'] ?? ''),
                'remaining' => (float) $item['remain'],
                'discount' => (float) ($item['discount'] ?? 0),
            ];
        }

        $name = trim((string) ($account['student_name'] ?? $student['student_name'] ?? $searchRow['student_name'] ?? ''));

        return $this->state('ok', '', [
            'student_id' => $studentId,
            'student_name' => preg_replace('/\s+-\s+/', ' ', $name) ?: ('Student #'.$studentId),
            'standard_name' => $account['standard_name'] ?? $student['standard_name'] ?? null,
            'enrollment_no' => $account['enrollment_no'] ?? $student['enrollment_no'] ?? $searchRow['enrollment_no'] ?? null,
            'ledger_outstanding' => $account === null ? 0.0 : (float) $account['outstanding'],
            'owes' => $account !== null && (float) $account['outstanding'] > 0,
            'periods' => $periods,
            'periods_total' => round($sum, 2),
            'failed_payments' => $account['failed_payments'] ?? null,
            'contact' => $this->reminders->contactSummary($context->scope, $studentId),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function state(string $state, string $message, array $data = []): array
    {
        return ['state' => $state, 'message' => $message, 'data' => $data, 'pending' => null];
    }
}
