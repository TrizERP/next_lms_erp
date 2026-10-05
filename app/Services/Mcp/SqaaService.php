<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SQAA — the school quality assurance and accreditation framework.
 *
 * WHAT THE TABLES ARE
 *
 *   `sqaa_master`           the criteria tree: title, description, `parent_id`, `level`
 *   `sqaa_documant_master`  the document slots a criterion asks for
 *   `sqaa_documents`        the evidence actually uploaded against a slot
 *   `sqaa_marks`            scores, of which this estate holds six rows in total
 *
 * THE GAP BETWEEN SLOTS AND EVIDENCE IS THE WHOLE STORY
 *
 * 1,534 document slots exist across this estate and 86 documents have been uploaded
 * against them. That ratio is the useful, checkable fact this module can report, and it is
 * also the trap: a reader seeing "86 documents" could easily conclude the school is 86
 * documents' worth of ready, when what it means is that most slots are empty.
 *
 * So `evidence()` reports both figures side by side and never one alone.
 *
 * NOTHING HERE SCORES A SCHOOL
 *
 * `sqaa_marks` holds six rows estate-wide — effectively nothing — and no rubric, no
 * weighting and no grade boundary is recorded anywhere. Accreditation is a judgement made
 * by an assessor against a framework, and this module must never anticipate it: no score,
 * no rating, no band, no "ready for assessment", no percentage complete beyond the plain
 * count of slots filled. Every published prompt says so.
 *
 * `availability` IS WHAT SOMEBODY TICKED, NOT WHETHER A FILE EXISTS
 *
 * A row can be marked available and carry no file, and can carry a file while marked
 * otherwise. Both are reported separately — `marked_available` and `file_attached` — and
 * neither is presented as the other.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read. `sqaa_documant_master` is
 * joined on institute as well, so a slot belonging to another school can never supply a
 * title here.
 */
class SqaaService
{
    /**
     * The criteria tree.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function criteria(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('sqaa_master')) {
            return ['count' => 0, 'criteria' => [], 'note' => 'A quality assurance framework is not kept in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        $query = DB::table('sqaa_master')->where('sub_institute_id', $context->selectedInstituteId);

        if (isset($filters['level']) && $filters['level'] !== '' && $filters['level'] !== null) {
            $query->where('level', (int) $filters['level']);
        }

        if (! empty($filters['parent_id'])) {
            $query->where('parent_id', (int) $filters['parent_id']);
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('title', 'like', $needle)->orWhere('description', 'like', $needle);
            });
        }

        $total = (clone $query)->count();

        $byLevel = (clone $query)
            ->selectRaw('level, COUNT(*) AS criteria')
            ->groupBy('level')
            ->orderBy('level')
            ->get()
            ->map(static fn ($row) => [
                'level' => $row->level === null ? null : (int) $row->level,
                'criteria' => (int) $row->criteria,
            ])
            ->all();

        $rows = $query
            ->orderBy('level')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title', 'description', 'parent_id', 'level', 'status', 'sort_order']);

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'by_level' => $byLevel,
            'figures_cover' => 'every criterion matching these filters, not only the rows listed',
            'criteria' => $rows->map(static fn ($row) => [
                'criterion_id' => (int) $row->id,
                'title' => $row->title,
                'description' => $row->description,
                'parent_id' => $row->parent_id === null ? null : (int) $row->parent_id,
                'level' => $row->level === null ? null : (int) $row->level,
                'active' => (int) ($row->status ?? 0) === 1,
            ])->all(),
            'rule' => $this->rule(),
        ];
    }

    /**
     * The document slots and the evidence uploaded against them.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function evidence(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('sqaa_documents') || ! Schema::hasTable('sqaa_documant_master')) {
            return ['count' => 0, 'evidence' => [], 'note' => 'Quality assurance evidence is not kept in this estate.'];
        }

        $institute = $context->selectedInstituteId;
        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);

        // Both figures, always. See the class note about the 1,534-to-86 gap.
        $slotsTotal = DB::table('sqaa_documant_master')->where('sub_institute_id', $institute)->count();

        $query = DB::table('sqaa_documents as d')
            ->leftJoin('sqaa_documant_master as m', function ($join) use ($institute) {
                $join->on('m.id', '=', 'd.document_id')->where('m.sub_institute_id', '=', $institute);
            })
            ->leftJoin('tbluser as u', function ($join) use ($institute) {
                $join->on('u.id', '=', 'd.created_by')->where('u.sub_institute_id', '=', $institute);
            })
            ->where('d.sub_institute_id', $institute);

        if (! empty($filters['menu_id'])) {
            $query->where('d.menu_id', (int) $filters['menu_id']);
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('d.title', 'like', $needle)->orWhere('m.title', 'like', $needle);
            });
        }

        $total = (clone $query)->count();
        $withFile = (clone $query)->whereNotNull('d.file')->where('d.file', '<>', '')->count();

        $rows = $query
            ->selectRaw("d.id, d.menu_id, d.document_id, d.title, d.reasons, d.availability, d.file,
                d.created_at, m.title AS slot_title,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS uploaded_by_name")
            ->orderByDesc('d.created_at')
            ->orderByDesc('d.id')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            // The two figures that must never be reported apart.
            'document_slots_defined' => $slotsTotal,
            'evidence_rows_recorded' => $total,
            'evidence_with_a_file' => $withFile,
            'figures_cover' => 'every evidence row matching these filters, not only the rows listed',
            'evidence' => $rows->map(static function ($row) {
                $file = trim((string) ($row->file ?? ''));
                $availability = trim((string) ($row->availability ?? ''));

                return [
                    'evidence_id' => (int) $row->id,
                    'title' => $row->title,
                    // Null when the slot belongs to another institute — a record to
                    // correct, not a title to borrow from elsewhere.
                    'document_slot' => $row->slot_title,
                    'menu_id' => $row->menu_id === null ? null : (int) $row->menu_id,
                    // What somebody ticked …
                    'marked_available' => $availability !== '' ? $availability : null,
                    // … and whether a file is actually there. Two different facts.
                    'file_attached' => $file !== '',
                    'reasons' => $row->reasons ?: null,
                    'uploaded_by' => trim((string) ($row->uploaded_by_name ?? '')) ?: null,
                    'uploaded_on' => $row->created_at,
                ];
            })->all(),
            'rule' => $this->rule().' `document_slots_defined` and `evidence_rows_recorded` must always be '
                .'reported together: on this estate there are 1,534 slots and 86 uploads, so a count of '
                .'evidence quoted on its own reads as progress when it is mostly absence. '
                .'`marked_available` is what somebody ticked and `file_attached` is whether a file is '
                .'actually there — they are two different facts and neither stands for the other.',
        ];
    }

    /** The rule every SQAA answer carries. */
    private function rule(): string
    {
        return 'SQAA is a quality assurance framework: criteria, the documents they ask for, and the '
            .'evidence uploaded against them. NOTHING HERE SCORES A SCHOOL. No rubric, weighting or grade '
            .'boundary is recorded anywhere and the marks table holds six rows across the whole estate, '
            .'so never state a score, a rating, a band, a percentage of readiness, or that a school is '
            .'ready or not ready for assessment. Accreditation is a judgement an assessor makes, not one '
            .'to anticipate from a count of uploaded files.';
    }
}
