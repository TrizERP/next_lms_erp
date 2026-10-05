<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Certificates, as `certificate_history` records them and `template_master` designs them.
 *
 * TWO TABLES, TWO DIFFERENT QUESTIONS
 *
 * `certificate_history` is what was issued: one row per certificate, carrying its type,
 * the student, the certificate number, the academic year and the rendered HTML that was
 * printed. `template_master` is what a certificate looks like: an HTML layout filed under
 * a `module_name` such as Transfer Certificate, Bonafide or Character Certificate, per
 * institute.
 *
 * They are reported separately because they answer different things. "Which certificates
 * did we issue this year" is history; "which certificates can this school issue at all" is
 * the template list, and a school with a Bonafide template and no Bonafide issued has an
 * empty history and a configured module. Merging them would make the second look like the
 * first.
 *
 * THE RENDERED HTML IS NEVER RETURNED
 *
 * `certificate_history.certificate_html` holds the full printed document — a child's name,
 * parents, conduct, dates, fee status, everything the certificate says. A list of
 * certificates does not need it, a model summarising a list must not be handed it, and a
 * report that printed it would reproduce whole certificates in a table. The reads below
 * return the metadata and report that the document exists; a person who needs the
 * document opens it on the Certificate screen, which is where the existing permission
 * check already is.
 *
 * SCOPING
 *
 * `sub_institute_id` on both tables from the caller's token. `syear` on history, matched
 * against the caller's academic year. `template_master` carries no year — a layout is not
 * a year's record — and is filtered by institute only, with the shared `sub_institute_id
 * = 0` rows included because this estate uses 0 for a platform-wide template.
 */
class CertificateService
{
    /**
     * Certificates issued, newest first.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function issued(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('certificate_history')) {
            return ['count' => 0, 'certificates' => [], 'note' => 'Certificates are not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->historyQuery($context);

        if (! empty($filters['certificate_type'])) {
            $query->where('c.certificate_type', 'like', '%'.$filters['certificate_type'].'%');
        }

        if (! empty($filters['student_id'])) {
            $query->where('c.student_id', (int) $filters['student_id']);
        }

        if (! empty($filters['certificate_number'])) {
            $query->where('c.certificate_number', 'like', '%'.$filters['certificate_number'].'%');
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('c.created_at', '>=', (string) $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('c.created_at', '<=', (string) $filters['to_date']);
        }

        // Counted before the limit, so a page is never read as the year's total.
        $total = (clone $query)->count();
        $byType = $this->issuedByType($context);

        $rows = $query
            ->selectRaw(
                "c.id, c.certificate_type, c.certificate_number, c.syear, c.student_id,
                 c.created_at, c.updated_at,
                 CHAR_LENGTH(COALESCE(c.certificate_html, '')) AS document_length,
                 s.enrollment_no,
                 CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name"
            )
            ->orderByDesc('c.created_at')
            ->orderByDesc('c.id')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'academic_year' => $context->academicYear,
            'by_type' => $byType,
            'certificates' => $rows->map(static fn ($row) => [
                'certificate_id' => (int) $row->id,
                'certificate_type' => $row->certificate_type,
                'certificate_number' => $row->certificate_number,
                'student_id' => $row->student_id === null ? null : (int) $row->student_id,
                'student_name' => trim((string) $row->student_name) ?: null,
                'enrollment_no' => $row->enrollment_no,
                'academic_year' => $row->syear,
                'issued_on' => $row->created_at,
                // The document is reported as present, never returned. See the note above.
                'document_stored' => (int) $row->document_length > 0,
            ])->all(),
            'rule' => 'The printed certificate text is deliberately not returned. Each row reports that a '
                .'document exists; opening it is done on the Certificate screen, where the existing '
                .'permission check already applies.',
        ];
    }

    /**
     * The certificate designs this institute can issue from.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function templates(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('template_master')) {
            return ['count' => 0, 'templates' => [], 'note' => 'Certificate layouts are not held in this estate.'];
        }

        $query = DB::table('template_master')
            // 0 is this estate's spelling of "shared by every institute". Included so a
            // school using only the shared layouts is not reported as having none.
            ->where(function ($inner) use ($context) {
                $inner->where('sub_institute_id', $context->selectedInstituteId)
                    ->orWhere('sub_institute_id', 0);
            })
            // Certificate layouts only. `template_master` also holds fee receipts, salary
            // slips and result sheets, and returning those would make this a second,
            // unscoped template list for other modules.
            ->where('module_name', 'like', '%certificate%');

        if (! empty($filters['certificate_type'])) {
            $query->where('module_name', 'like', '%'.$filters['certificate_type'].'%');
        }

        if (! empty($filters['only_active'])) {
            $query->where('status', 1);
        }

        $rows = $query
            ->orderBy('module_name')
            ->orderBy('title')
            ->limit(min(max((int) ($filters['limit'] ?? 100), 1), 200))
            ->get(['id', 'sub_institute_id', 'module_name', 'title', 'status', 'created_on']);

        return [
            'count' => $rows->count(),
            'templates' => $rows->map(static fn ($row) => [
                'template_id' => (int) $row->id,
                'certificate_type' => $row->module_name,
                'title' => $row->title,
                'active' => (int) $row->status === 1,
                // Says whose layout it is, because a shared one cannot be edited here.
                'scope' => (int) $row->sub_institute_id === 0 ? 'shared' : 'this institute',
                'created_on' => $row->created_on,
            ])->all(),
            'rule' => 'Only layouts filed under a certificate module are listed. `template_master` also '
                .'holds fee receipts, salary slips and result sheets, which belong to other modules.',
        ];
    }

    /**
     * How many of each type have been issued, across the whole scope.
     *
     * Reported beside a limited page so "5 shown" is never read as "5 issued".
     *
     * @return array<string, int>
     */
    private function issuedByType(McpRequestContext $context): array
    {
        $rows = $this->historyQuery($context)
            ->selectRaw('c.certificate_type AS type, COUNT(*) AS total')
            ->groupBy('c.certificate_type')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $type = trim((string) $row->type);
            $totals[$type === '' ? 'unspecified' : $type] = (int) $row->total;
        }

        return $totals;
    }

    /**
     * The history join, scoped to the caller's institute and academic year.
     *
     * The student join is a left join: a certificate issued to a student who has since
     * been removed is still a certificate this school issued, and dropping it would
     * quietly shrink the register that an audit reads.
     */
    private function historyQuery(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table('certificate_history as c')
            ->leftJoin('tblstudent as s', function ($join) use ($institute) {
                $join->on('s.id', '=', 'c.student_id')
                    ->where('s.sub_institute_id', '=', $institute);
            })
            ->where('c.sub_institute_id', $institute);

        if ($context->academicYear !== null) {
            $query->where('c.syear', $context->academicYear);
        }

        return $query;
    }
}
