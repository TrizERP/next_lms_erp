<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The visitor register, as the `visitor_master` table records it.
 *
 * ONE ROW IS ONE VISIT
 *
 * `visitor_master` carries the visitor's name and contact, where they came from, who they
 * came to meet, the purpose, the visitor type, the date, an entry time, an exit time and
 * whether an exit message was sent.
 *
 * NO EXIT TIME MEANS NO EXIT WAS RECORDED. IT DOES NOT MEAN THEY ARE STILL HERE.
 *
 * `visitor_masterController` colours a row green with `IF(out_time IS NULL, …)`, and it is
 * tempting to read that as "on the premises". On real data it is not safe: on one live
 * institute 205 of 463 visits have no exit time, including visits months old, because
 * signing out is something people forget rather than something the system enforces.
 *
 * So there are three states here and never two:
 *
 *   · an exit was recorded — the time is known
 *   · an entry was recorded and no exit was — the visitor may still be on site, or may
 *     have left without signing out. Both are consistent with the record.
 *   · no entry was recorded — the visit was booked or logged but never checked in
 *
 * `no_exit_recorded` is the name every payload uses for the second state, and the rule
 * spells out what it does and does not prove. A security answer that asserted somebody was
 * in the building on the strength of a missing field would be the most consequential kind
 * of wrong this module can be.
 *
 * THERE IS NO APPROVAL
 *
 * `appointment_type` records whether the visit was direct or by appointment. It is not an
 * approval, and no approval, approver, decision or rejection is recorded anywhere in this
 * table. "Show pending visitor approvals" has no column behind it and nothing here invents
 * one.
 *
 * THE HOSTEL'S VISITORS ARE A DIFFERENT REGISTER
 *
 * `hostel_visitor_master` is the Hostel module's own table for visitors to boarders. It is
 * not read here and must not be: they are different registers kept by different people,
 * and summing them would make both counts wrong.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read. This table has no `syear`, so
 * date ranges are the period filter. `visitor_type` and the recording user are joined on
 * institute too.
 */
class VisitorService
{
    /**
     * Visits, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function visits(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('visitor_master')) {
            return ['count' => 0, 'visits' => [], 'note' => 'A visitor register is not kept in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        $this->applyFilters($query, $filters);

        // Counted before the limit, so a page is never read as the whole register — and
        // the three state figures over the SAME whole set. "How many have not signed out"
        // is a question somebody may act on, and a page-scoped answer to it would be read
        // as the whole gate.
        $total = (clone $query)->count();
        $noEntry = (clone $query)
            ->where(static function ($inner): void {
                $inner->whereNull('v.in_time')->orWhereRaw("TRIM(v.in_time) = ''");
            })
            ->count();
        $noExit = (clone $query)
            ->whereNotNull('v.in_time')
            ->whereRaw("TRIM(v.in_time) <> ''")
            ->where(static function ($inner): void {
                $inner->whereNull('v.out_time')->orWhereRaw("TRIM(v.out_time) = ''");
            })
            ->count();

        $rows = $query
            ->selectRaw($this->columns())
            ->orderByDesc('v.meet_date')
            ->orderByDesc('v.in_time')
            ->orderByDesc('v.id')
            ->limit($limit)
            ->get();

        $visits = $rows->map(fn ($row) => $this->map($row))->all();

        return [
            'count' => $total,
            'row_count' => count($visits),
            'exit_recorded' => $total - $noEntry - $noExit,
            'no_exit_recorded' => $noExit,
            'no_entry_recorded' => $noEntry,
            'figures_cover' => 'every visit matching these filters, not only the rows listed',
            'visits' => $visits,
            'rule' => $this->rule(),
        ];
    }

    /**
     * Visits with an entry time and no exit time, oldest entry first.
     *
     * Deliberately NOT named "currently on the premises". See the class note: a missing
     * exit time is a missing exit time.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function withoutExit(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('visitor_master')) {
            return ['count' => 0, 'visits' => [], 'note' => 'A visitor register is not kept in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        $this->applyFilters($query, $filters);

        $query->whereNotNull('v.in_time')
            ->whereRaw("TRIM(v.in_time) <> ''")
            ->where(static function ($inner): void {
                $inner->whereNull('v.out_time')->orWhereRaw("TRIM(v.out_time) = ''");
            });

        $total = (clone $query)->count();

        // How many are from a day already past — the one figure that tells the office
        // these are records to tidy rather than people in the lobby. Counted over the
        // whole matching set, like `count`, and not over the page.
        $earlierDays = (clone $query)->whereDate('v.meet_date', '<', now()->toDateString())->count();

        $rows = $query
            ->selectRaw($this->columns())
            ->orderBy('v.meet_date')
            ->orderBy('v.in_time')
            ->limit($limit)
            ->get();

        $visits = $rows->map(fn ($row) => $this->map($row))->all();

        return [
            'count' => $total,
            'row_count' => count($visits),
            'from_an_earlier_day' => $earlierDays,
            'figures_cover' => 'every visit matching these filters, not only the rows listed',
            'visits' => $visits,
            'rule' => $this->rule().' Every row here has an entry time and no exit time. A row dated before '
                .'today almost certainly means a sign-out that was never recorded, not a visitor who has '
                .'been in the building for days — `from_an_earlier_day` is how many of the matching visits '
                .'that applies to.',
        ];
    }

    /**
     * The three-state rule, written once and attached to both payloads.
     */
    private function rule(): string
    {
        return 'One row is one visit. A visit has THREE states and never two: an exit was recorded, an '
            .'entry was recorded and no exit was, or no entry was recorded at all. `no exit recorded` is '
            .'exactly that — the visitor may still be on site, or may have left without signing out, and '
            .'the register cannot tell the difference. NEVER state that a named person is currently in the '
            .'building on the strength of a missing exit time. This table also records NO approval, '
            .'approver, decision or rejection of any kind, so no visit may be described as pending '
            .'approval, approved or rejected; `appointment_type` records only whether the visit was direct '
            .'or by appointment. The Hostel module keeps its own separate visitor register, which is not '
            .'included here. A visitor\'s phone number and email are in this register for the front desk '
            .'and are not to be repeated into any summary, message or report.';
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['visitor_type_id'])) {
            $query->where('v.visitor_type', (int) $filters['visitor_type_id']);
        }

        $appointment = trim((string) ($filters['appointment_type'] ?? ''));

        if ($appointment !== '') {
            $query->where('v.appointment_type', $appointment);
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            $date = trim((string) ($filters[$filter] ?? ''));

            if ($date !== '') {
                $query->whereDate('v.meet_date', $operator, $date);
            }
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('v.name', 'like', $needle)
                    ->orWhere('v.to_meet', 'like', $needle)
                    ->orWhere('v.purpose', 'like', $needle)
                    ->orWhere('v.coming_from', 'like', $needle);
            });
        }
    }

    /** The visitor join, scoped at every hop that carries an institute. */
    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table('visitor_master as v')
            ->leftJoin('tbluser as u', function ($join) use ($institute) {
                $join->on('u.id', '=', 'v.created_by')->where('u.sub_institute_id', '=', $institute);
            })
            ->where('v.sub_institute_id', $institute);

        if (Schema::hasTable('visitor_type')) {
            $query->leftJoin('visitor_type as vt', function ($join) use ($institute) {
                $join->on('vt.id', '=', 'v.visitor_type')->where('vt.sub_institute_id', '=', $institute);
            });
        }

        return $query;
    }

    private function columns(): string
    {
        $type = Schema::hasTable('visitor_type') ? 'vt.title AS visitor_type_title' : 'NULL AS visitor_type_title';

        return "v.id, v.name, v.contact, v.email, v.coming_from, v.to_meet, v.relation, v.purpose,
                v.appointment_type, v.visitor_type, v.meet_date, v.in_time, v.out_time,
                v.visitor_idcard, v.photo, v.exit_msg_sent, v.created_by,
                {$type},
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS recorded_by_name";
    }

    /** @return array<string, mixed> */
    private function map(object $row): array
    {
        $in = trim((string) ($row->in_time ?? ''));
        $out = trim((string) ($row->out_time ?? ''));

        $entryRecorded = $in !== '';
        // Only meaningful once an entry exists. Null where there is no entry to exit from,
        // rather than false, which would read as "did not leave".
        $exitRecorded = $entryRecorded ? ($out !== '') : null;

        return [
            'visit_id' => (int) $row->id,
            'visitor_name' => $row->name,
            'contact' => $row->contact ?: null,
            'email' => $row->email ?: null,
            'coming_from' => $row->coming_from ?: null,
            // Free text on this table, not a link to a staff record. Reported verbatim.
            'to_meet' => $row->to_meet ?: null,
            'relation' => $row->relation ?: null,
            'purpose' => $row->purpose ?: null,
            'appointment_type' => $row->appointment_type ?: null,
            'visitor_type_id' => $row->visitor_type === null ? null : (int) $row->visitor_type,
            // Null when the type belongs to another institute — a record to correct, not a
            // name to borrow from elsewhere.
            'visitor_type' => $row->visitor_type_title,
            'meet_date' => $row->meet_date,
            'in_time' => $entryRecorded ? $in : null,
            'out_time' => $out !== '' ? $out : null,
            'entry_recorded' => $entryRecorded,
            'exit_recorded' => $exitRecorded,
            // Spelled out, so a model reading the row cannot reach for "still inside".
            'presence_state' => $entryRecorded
                ? ($exitRecorded ? 'exit recorded' : 'no exit recorded — may have left without signing out')
                : 'no entry recorded',
            // Whether an identity document and a photo were captured, never the files.
            'id_document_captured' => trim((string) ($row->visitor_idcard ?? '')) !== '',
            'photo_captured' => trim((string) ($row->photo ?? '')) !== '',
            'exit_message_sent' => ! empty($row->exit_msg_sent),
            'recorded_by' => trim((string) ($row->recorded_by_name ?? '')) ?: null,
        ];
    }
}
