<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\DB;

/**
 * The dashboard engine: modules register widgets, the platform handles the
 * layout, who sees what, and refresh.
 *
 * A module registers a widget by adding an entry to `definitions()`: a key, a
 * title, the module it belongs to, a kind (`count` or `list`), and a provider that
 * receives the tenant and returns its data. The provider is the only thing a
 * module writes; ordering, hiding and per-user layout are the platform's.
 *
 * `right` names the platform right needed to see the widget; the controller drops
 * widgets the caller lacks, so a hidden widget's data is never computed for them.
 */
class DashboardWidgets
{
    /** @return array<string,array{title:string,module:string,kind:string,description:string,right:?string,provider:callable}> */
    private function definitions(): array
    {
        return [
            'platform.pending_approvals' => [
                'title' => 'Approvals waiting', 'module' => 'platform', 'kind' => 'count', 'right' => 'platform.workflow',
                'description' => 'Requests waiting on a decision.',
                'provider' => fn (int $t) => ['value' => DB::table('platform_workflow_runs')->where('sub_institute_id', $t)->where('status', 'pending')->count(), 'href' => '/platform-services/workflow/runs'],
            ],
            'platform.failed_notifications' => [
                'title' => 'Failed notifications, last 24 hours', 'module' => 'platform', 'kind' => 'count', 'right' => 'platform.notification',
                'description' => 'Messages that could not be delivered.',
                'provider' => fn (int $t) => ['value' => DB::table('platform_notification_log')->where('sub_institute_id', $t)->where('status', 'failed')->where('created_at', '>=', now()->subDay())->count(), 'href' => '/platform-services/notification/log'],
            ],
            'platform.audit_today' => [
                'title' => 'Audit events today', 'module' => 'platform', 'kind' => 'count', 'right' => 'platform.audit',
                'description' => 'Changes recorded since midnight.',
                'provider' => fn (int $t) => ['value' => DB::table('platform_audit_log')->where('sub_institute_id', $t)->where('created_at', '>=', now()->startOfDay())->count(), 'href' => '/platform-services/audit'],
            ],
            'platform.files_total' => [
                'title' => 'Attached files', 'module' => 'platform', 'kind' => 'count', 'right' => null,
                'description' => 'Files attached to records.',
                'provider' => fn (int $t) => ['value' => DB::table('platform_file_attachments')->where('sub_institute_id', $t)->whereNull('deleted_at')->count(), 'href' => '/platform-services/files'],
            ],
            'platform.recent_runs' => [
                'title' => 'Latest approval requests', 'module' => 'platform', 'kind' => 'list', 'right' => 'platform.workflow',
                'description' => 'The five most recent requests.',
                'provider' => fn (int $t) => ['items' => DB::table('platform_workflow_runs')->where('sub_institute_id', $t)->orderByDesc('id')->limit(5)
                    ->get(['title', 'status', 'created_at'])->map(fn ($r) => ['label' => $r->title ?? 'Request', 'meta' => $r->status, 'at' => $r->created_at])->all(), 'href' => '/platform-services/workflow/runs'],
            ],
        ];
    }

    /**
     * Widgets this caller may see, in their saved order, with data.
     *
     * @param  callable(string):bool  $may  whether the caller holds a right (null right = everyone)
     * @return array<int,array<string,mixed>>
     */
    public function forUser(int $tenant, int $userId, callable $may): array
    {
        $defs = array_filter($this->definitions(), fn ($d) => $d['right'] === null || $may($d['right']));
        $saved = DB::table('platform_dashboard_layouts')->where('sub_institute_id', $tenant)->where('user_id', $userId)->value('layout');
        $layout = (array) json_decode((string) $saved, true);

        $order = [];
        $hidden = [];
        foreach ($layout as $entry) {
            if (isset($entry['key']) && isset($defs[$entry['key']])) {
                $order[] = $entry['key'];
                if (! empty($entry['hidden'])) {
                    $hidden[$entry['key']] = true;
                }
            }
        }
        // Widgets registered after the user saved their layout appear at the end.
        foreach (array_keys($defs) as $key) {
            if (! in_array($key, $order, true)) {
                $order[] = $key;
            }
        }

        $out = [];
        foreach ($order as $key) {
            $d = $defs[$key];
            $isHidden = isset($hidden[$key]);
            $out[] = [
                'key' => $key, 'title' => $d['title'], 'module' => $d['module'], 'kind' => $d['kind'], 'description' => $d['description'],
                'hidden' => $isHidden,
                // Hidden widgets are not computed: no data is read for what is not shown.
                'data' => $isHidden ? null : ($d['provider'])($tenant),
            ];
        }

        return $out;
    }

    /** @param  array<int,array{key:string,hidden?:bool}>  $layout */
    public function saveLayout(int $tenant, int $userId, array $layout): void
    {
        $known = array_keys($this->definitions());
        $clean = [];
        foreach ($layout as $entry) {
            if (is_array($entry) && isset($entry['key']) && in_array($entry['key'], $known, true)) {
                $clean[] = ['key' => $entry['key'], 'hidden' => ! empty($entry['hidden'])];
            }
        }
        DB::table('platform_dashboard_layouts')->updateOrInsert(
            ['sub_institute_id' => $tenant, 'user_id' => $userId],
            ['layout' => json_encode($clean), 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
