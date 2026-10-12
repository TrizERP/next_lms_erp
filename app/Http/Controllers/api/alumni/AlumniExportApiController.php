<?php

namespace App\Http\Controllers\api\alumni;

use App\Exports\AlumniMasterExport;
use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniPrivacy;
use App\Services\Alumni\AlumniSearch;
use App\Services\Alumni\AlumniService;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Export and bulk actions over the staff alumni list.
 *
 *   POST alumni/export         the list's filters (or an explicit ids[]) as CSV or XLSX
 *   POST alumni/bulk-status    change the status of up to 500 selected alumni
 *
 * EXPORTS ARE MASKED. An export is a sharing surface, so every row goes through AlumniPrivacy for the
 * 'institution' viewer: a field an alumnus has made private is blank in the file, exactly as in the directory.
 * The filters come from the same AlumniSearch the list uses, always scoped to the caller's institute, and the
 * ids[] option is intersected with that scope, so an id from another institute exports nothing.
 *
 * FORMULA INJECTION. A cell beginning with = + - @ (or a tab / CR) is executed as a formula by spreadsheet
 * software. Alumni can type anything into name and company fields, so every text cell is neutralised by a
 * leading apostrophe before it is written, for CSV and XLSX alike.
 *
 * LIMIT. 20,000 rows per file; beyond that the caller is asked to narrow the filters. POST is used because
 * the relay that streams binary responses to the browser only forwards POST.
 */
class AlumniExportApiController extends Controller
{
    use AlumniApiSupport;

    public const MAX_ROWS = 20000;

    private const HEADINGS = ['Alumni ID', 'Name', 'Student ID', 'Batch', 'Passing year', 'Institute', 'Course', 'Mobile', 'Email',
        'City', 'Country', 'Employment status', 'Company', 'Job role', 'Status', 'Profile completion %'];

    public function __construct(private AlumniService $alumni, private AlumniSearch $search, private AlumniPrivacy $privacy)
    {
    }

    public function export(Request $request)
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'format' => 'nullable|in:csv,xlsx',
            'ids' => 'nullable|array|max:' . self::MAX_ROWS, 'ids.*' => 'integer|min:1',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $q = $this->search->filtered($t, $request->all());
        if (is_array($request->input('ids'))) {
            $q->whereIn('id', array_map('intval', $request->input('ids')));
        }
        $total = (clone $q)->count();
        if ($total > self::MAX_ROWS) {
            return response()->json(['status_code' => 0, 'message' => 'That is ' . number_format($total) . ' rows. Narrow the filters to ' . number_format(self::MAX_ROWS) . ' or fewer and try again.'], 422);
        }

        $institute = Schema::hasTable('school_setup') ? (string) DB::table('school_setup')->where('Id', $t)->value('SchoolName') : '';
        $rows = [];
        $q->orderBy('id')->chunk(500, function ($chunk) use (&$rows, $t, $institute) {
            $vis = $this->privacy->visibilityFor($t, $chunk->pluck('id')->map(fn ($i) => (int) $i)->all());
            foreach ($chunk as $r) {
                [$m] = $this->privacy->mask((array) $r, $vis[(int) $r->id], 'institution');
                $rows[] = array_map([$this, 'cell'], [
                    $m['alumni_code'], $m['full_name'], $m['student_id'], $m['batch'], $m['passing_year'], $institute, $m['course'],
                    $m['mobile'], $m['email'], $m['city'], $m['country'], $m['employment_status'], $m['current_company'],
                    $m['current_job_title'], $m['status'], $m['profile_completion'],
                ]);
            }
        });

        $format = $request->input('format', 'csv');
        $name = 'alumni-master-' . now()->format('Ymd-His') . '.' . $format;
        AuditTrail::record($t, 'alumni', 'alumni.export', 'export', [
            'entity_type' => 'alumni_export', 'after' => ['format' => $format, 'rows' => count($rows), 'filters' => array_filter($request->only(AlumniSearch::FILTERS))],
        ], $request);

        if ($format === 'xlsx') {
            return Excel::download(new AlumniMasterExport($rows, self::HEADINGS), $name);
        }

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM so Excel opens UTF-8 names correctly
            fputcsv($out, self::HEADINGS);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function bulkStatus(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), [
            'ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer|min:1',
            'status' => ['required', Rule::in(AlumniService::STATUSES)], 'remarks' => 'nullable|string|max:500',
        ]);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $ids = array_values(array_unique(array_map('intval', $request->input('ids'))));
        // only this institute's rows; anything else is reported as not found, never touched
        $owned = DB::table('alumni')->where('sub_institute_id', $t)->whereIn('id', $ids)->pluck('id')->map(fn ($i) => (int) $i)->all();
        foreach ($owned as $id) {
            $this->alumni->changeStatus($t, $id, $request->input('status'), $this->userId($request), $request->input('remarks'), $request);
        }

        return $this->ok(['updated' => count($owned), 'not_found' => array_values(array_diff($ids, $owned))], count($owned) . ' alumni updated.');
    }

    /** Neutralise spreadsheet formulas; leave numbers and nulls alone. */
    private function cell($value)
    {
        if (! is_string($value)) {
            return $value;
        }

        if (preg_match('/^\+[0-9][0-9 ()\-]*$/', $value)) {
            return $value;   // an international phone number (+91 98765 43210): digits and separators only, cannot call a function
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
