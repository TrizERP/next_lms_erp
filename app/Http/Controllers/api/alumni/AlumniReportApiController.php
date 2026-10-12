<?php

namespace App\Http\Controllers\api\alumni;

use App\Exports\AlumniMasterExport;
use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniReportService;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Alumni reports and their exports (spec sections 36-37). The definitions live in AlumniReportService; this class gates by
 * role, validates paging and filters, scopes to the institute and audits every export.
 *
 *   GET  alumni/reports                    the reports this caller may open
 *   GET  alumni/reports/{key}              ?page= &per_page= plus the report's filters
 *   POST alumni/reports/{key}/export       format=csv|xlsx plus the same filters; up to 20,000 rows
 *
 * Each report has its own gate: the donation report needs the `finance` role, the rest need `manage`. An unknown report
 * is a 404, and a report the caller may not open is a 403 - never a silent empty list. Exports are POST because the relay
 * that streams binary files to the browser only forwards POST.
 */
class AlumniReportApiController extends Controller
{
    use AlumniApiSupport;

    public function __construct(private AlumniReportService $reports)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $out = [];
        foreach (AlumniReportService::REPORTS as $key => [$label, $description, $area]) {
            if ($this->gate($request, $area) === null) {
                $out[] = ['key' => $key, 'label' => $label, 'description' => $description, 'filters' => $this->reports->filterKeys($key)];
            }
        }
        if ($out === []) {
            return $this->forbidden();
        }

        return $this->ok($out);
    }

    public function show(Request $request, string $key): JsonResponse
    {
        if ($refused = $this->refuse($request, $key)) {
            return $refused;
        }
        $v = Validator::make($request->all(), ['page' => 'nullable|integer|min:1|max:100000', 'per_page' => 'nullable|integer|min:1|max:100'] + $this->filterRules($key));
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $per = (int) ($request->input('per_page') ?: 25);
        $page = (int) ($request->input('page') ?: 1);
        $r = $this->reports->run($this->tenant($request), $key, $request->all(), $page, $per);
        [$label] = AlumniReportService::REPORTS[$key];

        return $this->ok([
            'key' => $key, 'label' => $label, 'columns' => $r['columns'], 'rows' => $r['rows'], 'summary' => $r['summary'], 'generated_at' => now()->toDateTimeString(),
            'pagination' => ['page' => $page, 'per_page' => $per, 'total' => $r['total'], 'last_page' => (int) max(1, ceil($r['total'] / $per))],
        ]);
    }

    public function export(Request $request, string $key)
    {
        if ($refused = $this->refuse($request, $key)) {
            return $refused;
        }
        $v = Validator::make($request->all(), ['format' => 'nullable|in:csv,xlsx'] + $this->filterRules($key));
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        $data = $this->reports->all($t, $key, $request->all());
        if ($data === null) {
            return response()->json(['status_code' => 0, 'message' => 'That report has more than ' . number_format(AlumniReportService::MAX_ROWS) . ' rows. Narrow the filters and try again.'], 422);
        }
        $headings = array_column($data['columns'], 'label');
        $keys = array_column($data['columns'], 'key');
        $rows = array_map(fn (array $r) => array_map([AlumniReportService::class, 'safeCell'], array_map(fn ($k) => $r[$k] ?? null, $keys)), $data['rows']);

        $format = $request->input('format', 'csv');
        $name = 'alumni-report-' . $key . '-' . now()->format('Ymd-His') . '.' . $format;
        AuditTrail::record($t, 'alumni', 'alumni.reports', 'export', ['entity_type' => 'alumni_report', 'after' => ['report' => $key, 'format' => $format, 'rows' => count($rows), 'filters' => $this->appliedFilters($request, $key)]], $request);

        if ($format === 'xlsx') {
            return Excel::download(new AlumniMasterExport($rows, $headings), $name);
        }

        return response()->streamDownload(function () use ($rows, $headings) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM so Excel opens UTF-8 names correctly
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** 404 for an unknown report, 403 when this caller's role may not open it; null when allowed. */
    private function refuse(Request $request, string $key): ?JsonResponse
    {
        if (! AlumniReportService::exists($key)) {
            return $this->notFound('Report');
        }

        return $this->gate($request, AlumniReportService::REPORTS[$key][2]);
    }

    /** @return array<string, string> */
    private function filterRules(string $key): array
    {
        $rules = [];
        foreach ($this->reports->filterKeys($key) as $k) {
            $rules[$k] = match (true) {
                in_array($k, ['from', 'to'], true) => 'nullable|date',
                in_array($k, ['passing_year', 'passing_year_from', 'passing_year_to', 'admission_year', 'campaign_id'], true) => 'nullable|integer|min:1',
                $k === 'current_only' => 'nullable|boolean',
                default => 'nullable|string|max:190',
            };
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    private function appliedFilters(Request $request, string $key): array
    {
        return array_filter($request->only($this->reports->filterKeys($key)), fn ($v) => $v !== null && $v !== '');
    }
}
