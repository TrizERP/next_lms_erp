<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The shared reporting engine's catalogue and query layer.
 *
 * A module makes a report available to every screen, export and schedule by
 * adding one entry to `definitions()`: a key, a label, its columns and a query.
 * The query always receives the tenant and returns plain rows, so exporting and
 * scheduling work the same for every report and none can read another school.
 *
 * Reports shipped here describe the platform's own services. A module's report is
 * added the same way when it is ready to be shared.
 */
class ReportCatalog
{
    /** @return array<string,array{label:string,description:string,module:string,columns:array<string,string>,filters:array<int,string>,query:callable}> */
    private function definitions(): array
    {
        return [
            'platform.audit' => [
                'label' => 'Audit trail',
                'description' => 'Who changed what, and when.',
                'module' => 'platform',
                'columns' => ['created_at' => 'When', 'module' => 'Module', 'component' => 'Component', 'action' => 'Action', 'entity_type' => 'Record type', 'entity_id' => 'Record', 'actor_name' => 'By'],
                'filters' => ['from', 'to', 'module'],
                'query' => function (int $tenant, array $f) {
                    $q = DB::table('platform_audit_log')->where('sub_institute_id', $tenant);
                    $this->dates($q, 'created_at', $f);
                    if (! empty($f['module'])) {
                        $q->where('module', $f['module']);
                    }

                    return $q->orderByDesc('id')->limit(5000);
                },
            ],
            'platform.notification_delivery' => [
                'label' => 'Notification delivery',
                'description' => 'Every notification attempt with its result.',
                'module' => 'platform',
                'columns' => ['created_at' => 'When', 'event_key' => 'Event', 'channel' => 'Channel', 'recipient' => 'Recipient', 'status' => 'Result', 'attempts' => 'Tries', 'last_error' => 'Reason'],
                'filters' => ['from', 'to', 'status'],
                'query' => function (int $tenant, array $f) {
                    $q = DB::table('platform_notification_log')->where('sub_institute_id', $tenant);
                    $this->dates($q, 'created_at', $f);
                    if (! empty($f['status'])) {
                        $q->where('status', $f['status']);
                    }

                    return $q->orderByDesc('id')->limit(5000);
                },
            ],
            'platform.approval_requests' => [
                'label' => 'Approval requests',
                'description' => 'Requests moving through approval chains.',
                'module' => 'platform',
                'columns' => ['created_at' => 'Requested', 'flow_key' => 'Approval point', 'title' => 'Request', 'requested_by_name' => 'By', 'status' => 'Status', 'completed_at' => 'Completed'],
                'filters' => ['from', 'to', 'status'],
                'query' => function (int $tenant, array $f) {
                    $q = DB::table('platform_workflow_runs')->where('sub_institute_id', $tenant);
                    $this->dates($q, 'created_at', $f);
                    if (! empty($f['status'])) {
                        $q->where('status', $f['status']);
                    }

                    return $q->orderByDesc('id')->limit(5000);
                },
            ],
            'platform.files' => [
                'label' => 'Attached files',
                'description' => 'Files attached to records, with versions.',
                'module' => 'platform',
                'columns' => ['created_at' => 'Added', 'entity_type' => 'Record type', 'entity_id' => 'Record', 'original_name' => 'File', 'version' => 'Version', 'size' => 'Bytes', 'uploaded_by_name' => 'By'],
                'filters' => ['from', 'to'],
                'query' => function (int $tenant, array $f) {
                    $q = DB::table('platform_file_attachments')->where('sub_institute_id', $tenant)->whereNull('deleted_at');
                    $this->dates($q, 'created_at', $f);

                    return $q->orderByDesc('id')->limit(5000);
                },
            ],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function catalog(): array
    {
        $out = [];
        foreach ($this->definitions() as $key => $d) {
            $out[] = ['key' => $key, 'label' => $d['label'], 'description' => $d['description'], 'module' => $d['module'], 'columns' => $d['columns'], 'filters' => $d['filters']];
        }

        return $out;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->definitions());
    }

    /** @return array{columns:array<string,string>,rows:array<int,array<string,mixed>>} */
    public function run(string $key, int $tenant, array $filters = []): array
    {
        $def = $this->definitions()[$key] ?? null;
        if (! $def) {
            throw new InvalidArgumentException("There is no report called {$key}.");
        }
        $rows = ($def['query'])($tenant, $filters)->get(array_keys($def['columns']))
            ->map(fn ($r) => (array) $r)->all();

        return ['columns' => $def['columns'], 'rows' => $rows];
    }

    /** Renders rows as CSV text. Cells starting with = + - @ are prefixed so a spreadsheet never runs them as formulas. */
    public function toCsv(array $result): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
        fputcsv($out, array_values($result['columns']));
        foreach ($result['rows'] as $row) {
            fputcsv($out, array_map(function ($v) {
                $s = (string) ($v ?? '');

                return $s !== '' && strpbrk($s[0], "=+-@\t\r") !== false ? "'".$s : $s;
            }, array_values($row)));
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    private function dates($query, string $column, array $f): void
    {
        if (! empty($f['from'])) {
            $query->where($column, '>=', $f['from'].' 00:00:00');
        }
        if (! empty($f['to'])) {
            $query->where($column, '<=', $f['to'].' 23:59:59');
        }
    }
}
