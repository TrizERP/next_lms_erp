<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The inward register, as the `inward` table records it.
 *
 * ONE ROW IS ONE DOCUMENT RECEIVED
 *
 * `inward` carries the inward number, title, description, the place it came from, the
 * physical file it was filed into, the date it was received and a scan, scoped by
 * institute and academic year.
 *
 * THERE IS NO STATUS COLUMN. THIS IS THE WHOLE POINT OF THIS CLASS.
 *
 * The obvious question to ask an inward register is "what is pending", and this table
 * cannot answer it. It records no status, no owner, no assignee, no due date, no action
 * taken and no link to a reply. `outward` is a separate register with no foreign key back
 * to this one, so "which inward letters have been answered" is not derivable either.
 *
 * A tool that answered "pending" anyway would have to invent the concept, and every
 * answer built on it would be a fabrication with a number attached. So nothing here
 * returns a status, and `unfiled()` — the nearest honest thing — reports the two gaps the
 * table really does record:
 *
 *   · no physical file location, so the paper has nowhere recorded to be found
 *   · no scan attached, so there is no copy in the system
 *
 * Both are checkable facts about the REGISTER, not claims about the document's progress.
 * Age in days is computed from `inward_date` and is likewise a fact; it is not evidence
 * that anything is overdue, because nothing here records what was due.
 *
 * OUTWARD IS NOT READ HERE
 *
 * `outward` is the other half of the same screen and a different register. Nothing in this
 * module reads it: a dispatched letter is not an inward record, and mixing the two would
 * make "how many did we receive" wrong.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read, and `syear` where the caller
 * carries an academic year. The two lookups — `place_master` and `physical_file_location`
 * — are joined on institute as well, so a place or a file name belonging to another school
 * can never appear on this school's register even if an id collides.
 */
class InwardService
{
    /**
     * The inward register, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function register(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('inward')) {
            return ['count' => 0, 'records' => [], 'note' => 'An inward register is not kept in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        $this->applyFilters($query, $filters);

        // Counted before the limit. A busy school records thousands of inward items a
        // year, so a page of fifty read as the total would be wrong by two orders.
        //
        // The two gap figures are counted over the SAME whole set, not over the page.
        // Putting a page-scoped figure next to a whole-set `count` is how a reader ends up
        // told that three of four thousand records have no scan.
        $total = (clone $query)->count();
        $withAttachment = (clone $query)->whereNotNull('i.attachment')->where('i.attachment', '<>', '')->count();
        $withoutLocation = (clone $query)
            ->where(static function ($inner): void {
                $inner->whereNull('i.file_location_id')->orWhere('i.file_location_id', 0);
            })
            ->count();

        $rows = $query
            ->selectRaw($this->columns())
            ->orderByDesc('i.inward_date')
            ->orderByDesc('i.id')
            ->limit($limit)
            ->get();

        $records = $rows->map(fn ($row) => $this->map($row))->all();

        return [
            'count' => $total,
            'row_count' => count($records),
            'academic_year' => $context->academicYear,
            'with_attachment' => $withAttachment,
            'without_file_location' => $withoutLocation,
            'figures_cover' => 'every record matching these filters, not only the rows listed',
            'records' => $records,
            'rule' => 'One row is one document received and entered in the inward register. This table '
                .'records NO status, owner, due date or action taken, and there is no link from an inward '
                .'record to an outward reply. Nothing here may be described as pending, overdue, actioned, '
                .'closed or answered — the register does not record any of those.',
        ];
    }

    /**
     * Inward records with a gap in the register itself, oldest first.
     *
     * The two gaps this table can prove: no physical file location recorded, so nobody can
     * be told where the paper is; and no scan attached, so there is no copy in the system.
     * Neither says anything about whether the document was dealt with.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function unfiled(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('inward')) {
            return ['count' => 0, 'records' => [], 'note' => 'An inward register is not kept in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $gap = trim((string) ($filters['gap'] ?? 'any'));
        $gap = in_array($gap, ['file_location', 'attachment', 'any'], true) ? $gap : 'any';

        $query = $this->query($context);

        $this->applyFilters($query, $filters);

        $missingLocation = static function ($inner): void {
            $inner->whereNull('i.file_location_id')->orWhere('i.file_location_id', 0);
        };

        $missingAttachment = static function ($inner): void {
            $inner->whereNull('i.attachment')->orWhere('i.attachment', '');
        };

        if ($gap === 'file_location') {
            $query->where($missingLocation);
        } elseif ($gap === 'attachment') {
            $query->where($missingAttachment);
        } else {
            $query->where(static function ($inner) use ($missingLocation, $missingAttachment): void {
                $inner->where($missingLocation)->orWhere($missingAttachment);
            });
        }

        // All three figures over the same whole set, never over the page. See `register()`.
        $total = (clone $query)->count();
        $missingLocationCount = (clone $query)->where($missingLocation)->count();
        $missingAttachmentCount = (clone $query)->where($missingAttachment)->count();

        // Oldest first: the register's oldest gap is the one somebody most likely meant to
        // come back to. That is an ordering, not a judgement that it is overdue.
        $rows = $query
            ->selectRaw($this->columns())
            ->orderBy('i.inward_date')
            ->orderBy('i.id')
            ->limit($limit)
            ->get();

        $records = $rows->map(fn ($row) => $this->map($row))->all();

        return [
            'count' => $total,
            'row_count' => count($records),
            'academic_year' => $context->academicYear,
            'gap' => $gap,
            'missing_file_location' => $missingLocationCount,
            'missing_attachment' => $missingAttachmentCount,
            'figures_cover' => 'every record matching these filters, not only the rows listed',
            'records' => $records,
            'rule' => 'A gap here is a gap in the REGISTER: no physical file location recorded, or no scan '
                .'attached. It is not a document awaiting action — this table records no status at all. '
                .'`days_since_received` is arithmetic on the received date and is not evidence that '
                .'anything is late, because nothing records what was due.',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['place_id' => 'i.place_id', 'file_location_id' => 'i.file_location_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        foreach (['from_date' => '>=', 'to_date' => '<='] as $filter => $operator) {
            $date = trim((string) ($filters[$filter] ?? ''));

            if ($date !== '') {
                $query->whereDate('i.inward_date', $operator, $date);
            }
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('i.title', 'like', $needle)
                    ->orWhere('i.description', 'like', $needle)
                    ->orWhere('i.inward_number', 'like', $needle);
            });
        }
    }

    /** The inward join, scoped at every hop that carries an institute. */
    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table('inward as i')
            ->leftJoin('place_master as pm', function ($join) use ($institute) {
                $join->on('pm.id', '=', 'i.place_id')->where('pm.sub_institute_id', '=', $institute);
            })
            ->leftJoin('physical_file_location as fl', function ($join) use ($institute) {
                $join->on('fl.id', '=', 'i.file_location_id')->where('fl.sub_institute_id', '=', $institute);
            })
            ->where('i.sub_institute_id', $institute);

        if ($context->academicYear !== null) {
            $query->where('i.syear', $context->academicYear);
        }

        return $query;
    }

    private function columns(): string
    {
        return 'i.id, i.inward_number, i.title, i.description, i.inward_date,
                i.attachment, i.attachment_type, i.attachment_size,
                i.place_id, i.file_location_id,
                pm.title AS place_title,
                fl.title AS file_location_title, fl.file_code, fl.file_location AS file_location_path,
                DATEDIFF(CURDATE(), i.inward_date) AS days_since_received';
    }

    /** @return array<string, mixed> */
    private function map(object $row): array
    {
        $attachment = trim((string) ($row->attachment ?? ''));

        return [
            'inward_id' => (int) $row->id,
            'inward_number' => $row->inward_number,
            'title' => $row->title,
            'description' => $row->description,
            'inward_date' => $row->inward_date,
            // Arithmetic on the received date. Not a measure of lateness — see the rule.
            'days_since_received' => $row->days_since_received === null ? null : (int) $row->days_since_received,
            'place_id' => $row->place_id === null ? null : (int) $row->place_id,
            // Null when the place belongs to another institute — a record to correct, not
            // a name to borrow from elsewhere.
            'place' => $row->place_title,
            'file_location_id' => $row->file_location_id === null ? null : (int) $row->file_location_id,
            'file_location' => $row->file_location_title,
            'file_code' => $row->file_code,
            'file_location_path' => $row->file_location_path,
            // Whether a scan exists, never the scan itself. The file is not returned here.
            'attachment_present' => $attachment !== '',
            'attachment_type' => $attachment === '' ? null : ($row->attachment_type ?: null),
        ];
    }
}
