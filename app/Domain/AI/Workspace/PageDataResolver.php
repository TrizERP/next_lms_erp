<?php

namespace App\Domain\AI\Workspace;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the page is showing, resolved on the server when the page did not say.
 *
 * PageSnapshot exists because only the page knows it is filtered to Standard 8. That is
 * true, and it stays true — but it quietly assumed every page would send its rows, and
 * a page that sends nothing is indistinguishable from a page with nothing on it. The
 * consequence was a catalogue summary that reported "no courses available" to a school
 * with 208 of them.
 *
 * So the snapshot is now a *preference*, not a requirement. If the page reports its rows,
 * those win — they reflect the filters and search actually applied. If it reports none,
 * the module's own resolver reads them here instead.
 *
 * Every resolver reads through the caller's scope, so this widens nothing: it returns the
 * same rows the user could already see on the page they are standing on.
 */
class PageDataResolver
{
    /** Matches PageSnapshot::MAX_RECORDS — these land in a prompt, not a report. */
    private const MAX_RECORDS = 25;

    /**
     * Fill in the page data for this context, if a resolver exists and the page was
     * silent.
     *
     * @return array{
     *   resolved: bool, source: string|null, records: array, record_count: int,
     *   metrics: array, filters: array
     * }
     */
    public function resolve(AiContext $context): array
    {
        $empty = [
            'resolved' => false,
            'source' => null,
            'records' => [],
            'record_count' => 0,
            'metrics' => [],
            'filters' => [],
        ];

        // The page spoke for itself. Never override it — its rows carry the user's
        // filters, and ours would not.
        if ($context->page->hasRecords()) {
            return $empty;
        }

        return match ($context->moduleKey) {
            'course-master' => $this->courseCatalog($context),
            default => $empty,
        };
    }

    /**
     * The course catalogue: subjects mapped to grades, which is what this estate means
     * by a "course". Grouped so the summary can talk about spread rather than list 208
     * rows into a prompt.
     */
    private function courseCatalog(AiContext $context): array
    {
        if (! Schema::hasTable('sub_std_map') || ! Schema::hasTable('standard')) {
            return ['resolved' => false, 'source' => null, 'records' => [], 'record_count' => 0, 'metrics' => [], 'filters' => []];
        }

        $tenantId = $context->scope->selectedInstituteId;

        $rows = DB::table('sub_std_map as s')
            ->join('standard as std', function ($join) {
                $join->on('std.id', '=', 's.standard_id')
                    ->on('std.sub_institute_id', '=', 's.sub_institute_id');
            })
            ->where('s.sub_institute_id', $tenantId)
            ->where('s.status', 1)
            ->selectRaw(
                'std.name as standard_name, s.standard_id, s.display_name as subject_name, '
                . "IFNULL(NULLIF(s.subject_category, ''), 'General') as category"
            )
            ->orderBy('s.sort_order')
            ->get();

        if ($rows->isEmpty()) {
            // A genuinely empty catalogue. Reported as resolved-but-empty, so the
            // grounding guard still refuses rather than the model inventing courses —
            // but at least the emptiness is now a fact about the data, not the request.
            return [
                'resolved' => true,
                'source' => 'sub_std_map',
                'records' => [],
                'record_count' => 0,
                'metrics' => [],
                'filters' => [],
            ];
        }

        // One row per subject, listing the grades it is taught in — the shape a teacher
        // browsing a catalogue actually thinks in, and far more compact than the raw map.
        $bySubject = [];
        $categories = [];
        $grades = [];

        foreach ($rows as $row) {
            $name = trim((string) $row->subject_name);

            if ($name === '') {
                continue;
            }

            $bySubject[$name] ??= ['category' => $row->category, 'grades' => []];
            $bySubject[$name]['grades'][(string) $row->standard_name] = true;

            $categories[$row->category] = ($categories[$row->category] ?? 0) + 1;
            $grades[(string) $row->standard_name] = true;
        }

        ksort($grades, SORT_NATURAL);
        arsort($categories);

        $records = [];

        foreach (array_slice($bySubject, 0, self::MAX_RECORDS, true) as $subject => $detail) {
            $gradeList = array_keys($detail['grades']);
            sort($gradeList, SORT_NATURAL);

            $records[] = [
                'id' => null,
                'label' => $subject,
                'attributes' => [
                    'category' => $detail['category'],
                    'grades' => implode(', ', $gradeList),
                    'grade_count' => count($gradeList),
                ],
            ];
        }

        $metrics = [
            ['key' => 'subjects', 'label' => 'Distinct subjects', 'value' => (string) count($bySubject), 'unit' => null, 'trend' => null],
            ['key' => 'grades', 'label' => 'Grades covered', 'value' => (string) count($grades), 'unit' => null, 'trend' => null],
            ['key' => 'mappings', 'label' => 'Course entries', 'value' => (string) $rows->count(), 'unit' => null, 'trend' => null],
        ];

        foreach (array_slice($categories, 0, 5, true) as $category => $count) {
            $metrics[] = [
                'key' => 'category_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower((string) $category)),
                'label' => 'Category: ' . $category,
                'value' => (string) $count,
                'unit' => 'entries',
                'trend' => null,
            ];
        }

        return [
            'resolved' => true,
            'source' => 'sub_std_map',
            'records' => $records,
            // The real total, not the windowed count — "these 25" is wrong when the
            // catalogue holds 208.
            'record_count' => count($bySubject),
            'metrics' => $metrics,
            // Rendered as "label: value" alongside any page filters, so the label is a
            // bare noun — the template supplies the sentence around it.
            'filters' => [
                ['key' => 'grades', 'label' => 'Grades', 'value' => implode(', ', array_keys($grades))],
            ],
        ];
    }
}
