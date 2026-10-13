<?php

namespace App\Http\Controllers\api\alumni;

use App\Http\Controllers\api\alumni\Concerns\AlumniApiSupport;
use App\Http\Controllers\Controller;
use App\Services\Alumni\AlumniImportService;
use App\Services\Alumni\AlumniReportService;
use App\Services\Alumni\AlumniRuleException;
use App\Services\Platform\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Bulk import of alumni from CSV / XLSX (spec section 38). Four steps; nothing reaches the alumni list until confirm.
 *
 *   GET  alumni/import/meta                 importable fields and limits
 *   GET  alumni/import                      recent imports
 *   POST alumni/import/upload               multipart `file` -> batch, headings, sample rows, suggested mapping
 *   POST alumni/import/{id}/preview         {mapping: {field: column index}} -> validates every row, finds duplicates
 *   GET  alumni/import/{id}                 the batch and its counts
 *   GET  alumni/import/{id}/rows            ?status= &page= row results with their problems
 *   POST alumni/import/{id}/confirm         imports the next 100 valid rows; repeat until `remaining` is 0 (resumable)
 *   POST alumni/import/{id}/cancel          discards the batch and the uploaded file
 *   POST alumni/import/{id}/problems        CSV of the rows that need attention, to fix and re-upload
 *   POST alumni/import/template             CSV with the headings and one example row
 *
 * Gate: `manage`. Rules and their reasons (never overwrite, no imported consent, no status, row cap) are documented on
 * AlumniImportService. Downloads are POST because the relay that streams binary files only forwards POST; every cell in them
 * is formula-neutralised.
 */
class AlumniImportApiController extends Controller
{
    use AlumniApiSupport;

    public function __construct(private AlumniImportService $import)
    {
    }

    public function meta(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $fields = [];
        foreach (AlumniImportService::FIELDS as $key => [$label, $required]) {
            $fields[] = ['key' => $key, 'label' => $label, 'required' => $required];
        }

        return $this->ok(['fields' => $fields, 'max_rows' => AlumniImportService::MAX_ROWS, 'max_mb' => AlumniImportService::MAX_BYTES / 1048576, 'formats' => ['csv', 'xlsx']]);
    }

    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }

        return $this->ok(DB::table('alumni_import_batches')->where('sub_institute_id', $this->tenant($request))->orderByDesc('id')->limit(25)
            ->get(['id', 'original_name', 'status', 'total_rows', 'valid_count', 'error_count', 'duplicate_count', 'imported_count', 'skipped_count', 'failed_count', 'created_at', 'confirmed_at']));
    }

    public function upload(Request $request): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['file' => 'required|file']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }

        return $this->guard(function () use ($request) {
            $r = $this->import->store($this->tenant($request), $request->file('file'), $this->userId($request));
            AuditTrail::record($this->tenant($request), 'alumni', 'alumni.import', 'upload', ['entity_type' => 'alumni_import_batch', 'entity_id' => (int) $r['batch']->id, 'after' => ['file' => $r['batch']->original_name, 'rows' => (int) $r['batch']->total_rows]], $request);

            return $this->ok($r, 'File received. Nothing has been imported yet.', 201);
        });
    }

    public function preview(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['mapping' => 'required|array']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }

        return $this->guard(fn () => $this->ok($this->import->preview($this->tenant($request), (int) $id, (array) $request->input('mapping')), 'Checked every row. Nothing has been imported yet.'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $b = $this->import->find($this->tenant($request), (int) $id);

        return $b ? $this->ok(['batch' => $b, 'headers' => json_decode((string) $b->headers, true), 'mapping' => json_decode((string) $b->mapping, true)]) : $this->notFound('Import');
    }

    public function rows(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $v = Validator::make($request->all(), ['status' => 'nullable|in:valid,error,duplicate,imported,skipped,failed', 'page' => 'nullable|integer|min:1', 'per_page' => 'nullable|integer|min:1|max:100']);
        if ($v->fails()) {
            return $this->validationError($v->errors()->messages());
        }
        $t = $this->tenant($request);
        if (! $this->import->find($t, (int) $id)) {
            return $this->notFound('Import');
        }
        $q = DB::table('alumni_import_rows')->where('sub_institute_id', $t)->where('batch_id', (int) $id)->when($request->input('status'), fn ($x, $s) => $x->where('status', $s))->orderBy('row_no');
        $total = (clone $q)->count();
        $per = (int) ($request->input('per_page') ?: 25);
        $page = (int) ($request->input('page') ?: 1);
        $rows = $q->forPage($page, $per)->get()->map(function ($r) {
            $d = (array) json_decode((string) $r->data, true);

            return ['row_no' => (int) $r->row_no, 'status' => $r->status, 'errors' => (object) (array) json_decode((string) $r->errors, true), 'note' => $r->note, 'duplicate_of' => $r->duplicate_of, 'alumni_id' => $r->alumni_id,
                'name' => trim(($d['first_name'] ?? '') . ' ' . ($d['last_name'] ?? '')) ?: null, 'email' => $d['email'] ?? null, 'mobile' => $d['mobile'] ?? null, 'batch' => $d['batch'] ?? null];
        });

        return $this->ok(['rows' => $rows, 'pagination' => ['page' => $page, 'per_page' => $per, 'total' => $total, 'last_page' => (int) max(1, ceil($total / $per))]]);
    }

    public function confirm(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }

        return $this->guard(function () use ($request, $id) {
            $b = $this->import->confirm($this->tenant($request), (int) $id, $this->userId($request), $request);

            return $this->ok($b, $b->remaining > 0 ? 'Importing: ' . $b->remaining . ' rows to go. Call confirm again to continue.' : 'Import finished.');
        });
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }

        return $this->guard(fn () => $this->ok($this->import->cancel($this->tenant($request), (int) $id, $request), 'Import cancelled.'));
    }

    /** CSV of the rows that still need attention, so they can be corrected and uploaded again. */
    public function problems(Request $request, $id)
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        $t = $this->tenant($request);
        if (! $this->import->find($t, (int) $id)) {
            return $this->notFound('Import');
        }
        $rows = DB::table('alumni_import_rows')->where('sub_institute_id', $t)->where('batch_id', (int) $id)->whereIn('status', ['error', 'duplicate', 'failed', 'skipped'])->orderBy('row_no')->get();
        $fieldKeys = array_values(array_diff(array_keys(AlumniImportService::FIELDS), ['full_name']));
        $headings = array_merge(['Row in your file', 'Outcome', 'Problem'], array_map(fn ($k) => AlumniImportService::FIELDS[$k][0], $fieldKeys));
        $out = [];
        foreach ($rows as $r) {
            $d = (array) json_decode((string) $r->data, true);
            $errors = (array) json_decode((string) $r->errors, true);
            $problem = $errors ? implode(' ', array_map(fn ($m) => implode(' ', (array) $m), $errors)) : (string) $r->note;
            $out[] = array_map([AlumniReportService::class, 'safeCell'], array_merge([(int) $r->row_no, ucfirst((string) $r->status), $problem], array_map(fn ($k) => $d[$k] ?? null, $fieldKeys)));
        }
        AuditTrail::record($t, 'alumni', 'alumni.import', 'download_problems', ['entity_type' => 'alumni_import_batch', 'entity_id' => (int) $id, 'after' => ['rows' => count($out)]], $request);

        return $this->csv('alumni-import-problems-' . (int) $id . '.csv', $headings, $out);
    }

    public function template(Request $request)
    {
        if ($denied = $this->gate($request)) {
            return $denied;
        }
        [$headings, $example] = AlumniImportService::template();

        return $this->csv('alumni-import-template.csv', $headings, [$example]);
    }

    private function csv(string $name, array $headings, array $rows)
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM so Excel opens UTF-8 correctly
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function guard(callable $fn): JsonResponse
    {
        try {
            return $fn();
        } catch (AlumniRuleException $e) {
            if ($e->status === 404) {
                return $this->notFound('Import');
            }

            return $e->field
                ? response()->json(['status_code' => 0, 'message' => $e->getMessage(), 'errors' => [$e->field => [$e->getMessage()]]], $e->status)
                : response()->json(['status_code' => 0, 'message' => $e->getMessage()], $e->status);
        }
    }
}
