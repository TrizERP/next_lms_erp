<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parent communication, as the `parent_communication` table records it.
 *
 * ONE ROW IS ONE MESSAGE A PARENT WROTE TO THE SCHOOL
 *
 * `parent_communication` carries the student it concerns, a title, the message body, the
 * date, and — when somebody has answered — a reply, the user who wrote it and when. There
 * are 22,729 rows across this estate, so this is one of the busiest tables the AI Stack
 * touches.
 *
 * IT IS THE INBOUND DIRECTION, AND THAT IS NOT THE COMMUNICATION MODULE
 *
 * The Communication module (`easy_com`) is what the school SENDS: SMS, WhatsApp and app
 * notifications going out. This is what parents send IN. They are different tables in
 * different directions and neither module binds the other's tools — a count of "messages"
 * means something different in each, and summing them would be meaningless.
 *
 * AN EMPTY REPLY MEANS NOBODY HAS ANSWERED
 *
 * The same three-state rule Consent and PTM carry, and it matters here because the person
 * waiting is a parent. A message with no reply has not been refused, declined or dismissed
 * — nobody has answered it yet. Every published prompt forbids reporting it as anything
 * else, and forbids inferring why.
 *
 * THE MESSAGE BODY IS A LETTER FROM A NAMED FAMILY
 *
 * Read the sample data and this is obvious: parents write about their child by name, at
 * length, about things they care about. So the body is returned for a read that is already
 * about one message or one student, and the published prompts forbid quoting or
 * paraphrasing a named family's message into a summary other people will read. A
 * `summary()` over a cohort returns counts and titles, never bodies.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read, and `syear` where the caller
 * carries an academic year. The student and replier lookups are joined on institute too.
 */
class ParentCommunicationService
{
    /**
     * Messages from parents, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function messages(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('parent_communication')) {
            return ['count' => 0, 'messages' => [], 'note' => 'Parent communication is not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        $this->applyFilters($query, $filters);

        $state = trim((string) ($filters['state'] ?? 'any'));

        if ($state === 'answered') {
            $query->whereNotNull('p.reply')->whereRaw("TRIM(p.reply) <> ''");
        } elseif ($state === 'unanswered') {
            $query->where($this->unanswered());
        }

        // Counted over the whole filtered set, and the two states with it.
        $total = (clone $query)->count();
        $unanswered = (clone $query)->where($this->unanswered())->count();

        // The body comes back only for a read already narrowed to one student or one
        // message. A cohort read gets titles and counts — see the class note.
        $oneFamily = ! empty($filters['student_id']) || ! empty($filters['message_id']);

        $rows = $query
            ->selectRaw($this->columns($oneFamily))
            ->orderByDesc('p.date_')
            ->orderByDesc('p.id')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'unanswered' => $unanswered,
            'answered' => $total - $unanswered,
            'figures_cover' => 'every message matching these filters, not only the rows listed',
            'message_body_included' => $oneFamily,
            'messages' => $rows->map(static function ($row) use ($oneFamily) {
                $reply = trim((string) ($row->reply ?? ''));

                return [
                    'message_id' => (int) $row->id,
                    'title' => $row->title,
                    // Present only on a read about one student or one message.
                    'message' => $oneFamily ? $row->message : null,
                    'received_on' => $row->date_,
                    'student_id' => $row->student_id === null ? null : (int) $row->student_id,
                    // Null when the student is not of this institute — a record to
                    // correct, not a name to borrow from elsewhere.
                    'student_name' => trim((string) ($row->student_name ?? '')) ?: null,
                    'enrollment_no' => $row->enrollment_no ?: null,
                    'reply' => $oneFamily && $reply !== '' ? $reply : null,
                    'answered' => $reply !== '',
                    // Spelled out so a model cannot reach for "declined" or "ignored".
                    'answer_state' => $reply !== '' ? 'a reply is recorded' : 'no reply recorded yet',
                    'replied_by' => trim((string) ($row->replied_by_name ?? '')) ?: null,
                    'replied_on' => $row->reply_on ?: null,
                    'days_since_received' => $row->days_since_received === null ? null : (int) $row->days_since_received,
                ];
            })->all(),
            'rule' => $this->rule($oneFamily),
        ];
    }

    /**
     * Messages counted by whether they have been answered, and by month.
     *
     * Never returns a body. A cohort figure is the one place a parent's letter has no
     * business appearing.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function summary(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('parent_communication')) {
            return ['count' => 0, 'by_month' => [], 'note' => 'Parent communication is not recorded in this estate.'];
        }

        $query = $this->query($context);
        $this->applyFilters($query, $filters);

        $total = (clone $query)->count();
        $unanswered = (clone $query)->where($this->unanswered())->count();

        $byMonth = (clone $query)
            ->selectRaw("DATE_FORMAT(p.date_, '%Y-%m') AS month, COUNT(*) AS messages,
                SUM(CASE WHEN p.reply IS NULL OR TRIM(p.reply) = '' THEN 1 ELSE 0 END) AS unanswered")
            ->groupByRaw("DATE_FORMAT(p.date_, '%Y-%m')")
            ->orderByDesc('month')
            ->limit(24)
            ->get()
            ->map(static fn ($row) => [
                'month' => $row->month,
                'messages' => (int) $row->messages,
                'unanswered' => (int) $row->unanswered,
            ])
            ->all();

        // How long the unanswered ones have been waiting — arithmetic on the date, and the
        // one figure the office would act on.
        $oldest = (clone $query)->where($this->unanswered())->min('p.date_');

        return [
            'count' => $total,
            'academic_year' => $context->academicYear,
            'unanswered' => $unanswered,
            'answered' => $total - $unanswered,
            'oldest_unanswered_date' => $oldest,
            'by_month' => $byMonth,
            'rule' => $this->rule(false).' This summary deliberately returns no message body at all: a '
                .'cohort figure is the one place a named family\'s letter has no business appearing.',
        ];
    }

    /** The rule every parent communication answer carries. */
    private function rule(bool $oneFamily): string
    {
        $rule = 'One row is one message a PARENT wrote to the school. This is the inbound direction and is '
            .'not the Communication module, which records what the school SENDS — never combine the two '
            .'or present one total for both. A message with no reply means NOBODY HAS ANSWERED IT YET: '
            .'never report it as refused, declined, dismissed or ignored, and never infer why. '
            .'`days_since_received` is arithmetic on the date and is not a breach of anything, because '
            .'this table records no promised response time.';

        $rule .= $oneFamily
            ? ' The message body is included because this read is about one student or one message. Do '
                .'not repeat it into anything a wider audience will read.'
            : ' The message body is deliberately NOT included on a read covering more than one family. '
                .'Work from the titles and counts, and never quote, paraphrase or characterise what a '
                .'parent wrote.';

        return $rule;
    }

    /** "Nobody has answered yet", written once so the rows and the totals agree. */
    private function unanswered(): callable
    {
        return static function ($inner): void {
            $inner->whereNull('p.reply')->orWhereRaw("TRIM(p.reply) = ''");
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['student_id'])) {
            $query->where('p.student_id', (int) $filters['student_id']);
        }

        if (! empty($filters['message_id'])) {
            $query->where('p.id', (int) $filters['message_id']);
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            $date = trim((string) ($filters[$filter] ?? ''));

            if ($date !== '') {
                $query->whereDate('p.date_', $operator, $date);
            }
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            // Title only. Searching the body would let a caller fish for a phrase inside
            // families' letters without ever naming a student.
            $query->where('p.title', 'like', '%'.$search.'%');
        }
    }

    /** The parent communication join, scoped at every hop that carries an institute. */
    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table('parent_communication as p')
            ->leftJoin('tblstudent as s', function ($join) use ($institute) {
                $join->on('s.id', '=', 'p.student_id')->where('s.sub_institute_id', '=', $institute);
            })
            ->leftJoin('tbluser as u', function ($join) use ($institute) {
                $join->on('u.id', '=', 'p.reply_by')->where('u.sub_institute_id', '=', $institute);
            })
            ->where('p.sub_institute_id', $institute);

        if ($context->academicYear !== null) {
            $query->where('p.syear', $context->academicYear);
        }

        return $query;
    }

    /** `$oneFamily` decides whether the body is selected at all — not merely hidden later. */
    private function columns(bool $oneFamily): string
    {
        $body = $oneFamily ? 'p.message, p.reply' : 'NULL AS message, p.reply';

        return "p.id, p.title, p.date_, p.student_id, p.reply_on,
                {$body},
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                s.enrollment_no,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS replied_by_name,
                DATEDIFF(CURDATE(), p.date_) AS days_since_received";
    }
}
