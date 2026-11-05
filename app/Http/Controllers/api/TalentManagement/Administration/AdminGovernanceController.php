<?php

namespace App\Http\Controllers\api\TalentManagement\Administration;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Talent Management -> Administration & Governance: everything except the workflow list
 * (AdminWorkflowController). Backs the KPI row and the Templates, Permissions, Integrations,
 * Configuration and Audit & Compliance tabs.
 *
 * Tenant and actor come from the JWT-hydrated session, never from request input. Every change made
 * here is written to `talent_admin_audit_events`, which the Audit & Compliance tab reads.
 */
class AdminGovernanceController extends Controller
{
    private const TEMPLATE_CATEGORIES = ['Offer Letter', 'Appraisal Form', 'Onboarding Checklist', 'Exit Checklist', 'Email'];
    private const INTEGRATION_CATEGORIES = ['Job Board', 'Calendar', 'Email', 'HRIS', 'Background Check', 'E-Sign'];

    /** key => [group, label, type, default, options, help] */
    private const SETTINGS = [
        'default_notice_days' => ['General', 'Default notice period (days)', 'number', 30, null, 'Used to suggest the last working day on a new exit case.'],
        'probation_months' => ['General', 'Probation period (months)', 'number', 6, null, 'New joiners are reviewed for confirmation at the end of this period.'],
        'review_cycle_frequency' => ['General', 'Review cycle frequency', 'select', 'Annual', ['Annual', 'Half-yearly', 'Quarterly'], 'How often a new performance cycle is expected.'],
        'require_requisition_approval' => ['Recruitment', 'Require approval for job requisitions', 'boolean', true, null, 'A requisition must clear its workflow before a job can be posted.'],
        'auto_screening' => ['Recruitment', 'Screen applications automatically', 'boolean', true, null, 'Run AI screening as soon as an application arrives.'],
        'offer_validity_days' => ['Recruitment', 'Offer validity (days)', 'number', 7, null, 'An offer lapses if the candidate has not replied by then.'],
        'onboarding_duration_days' => ['Onboarding', 'Onboarding duration (days)', 'number', 30, null, 'Default length of a new joiner journey.'],
        'auto_assign_buddy' => ['Onboarding', 'Assign a buddy automatically', 'boolean', false, null, 'Pair each new joiner with a colleague from the same department.'],
        'rating_scale' => ['Performance', 'Rating scale', 'select', '5', ['3', '4', '5', '10'], 'Highest rating a reviewer can give.'],
        'self_review_required' => ['Performance', 'Self review required', 'boolean', true, null, 'Employees must submit a self review before the manager review opens.'],
        'calibration_enabled' => ['Performance', 'Enable calibration', 'boolean', true, null, 'Ratings pass through a calibration round before they are final.'],
        'email_notifications' => ['Notifications', 'Send email notifications', 'boolean', true, null, 'Email people when a task or approval is waiting for them.'],
        'reminder_days_before_due' => ['Notifications', 'Reminder before due date (days)', 'number', 3, null, 'How early a reminder goes out.'],
        'audit_retention_months' => ['Governance', 'Audit log retention (months)', 'number', 24, null, 'Events older than this may be archived.'],
        'mask_compensation' => ['Governance', 'Mask compensation figures', 'boolean', true, null, 'Hide salary figures from people without HR access.'],
    ];

    /** GET talent/admin/overview */
    public function overview()
    {
        $sid = $this->tenant();
        $since = now()->subDays(30);

        return $this->ok([
            'active_workflows' => DB::table('talent_workflows')->where('sub_institute_id', $sid)->whereNull('deleted_at')->where('status', 'Active')->count(),
            'templates' => DB::table('talent_admin_templates')->where('sub_institute_id', $sid)->whereNull('deleted_at')->where('status', '!=', 'Archived')->count(),
            'user_roles' => $this->profilesQuery($sid)->count(),
            'integrations' => DB::table('talent_admin_integrations')->where('sub_institute_id', $sid)->where('status', 'Connected')->count(),
            'audit_events_30d' => DB::table('talent_admin_audit_events')->where('sub_institute_id', $sid)->where('created_at', '>=', $since)->count(),
        ]);
    }

    // ---------------------------------------------------------------- Templates

    /** GET talent/admin/templates */
    public function templates(Request $request)
    {
        $sid = $this->tenant();
        $q = DB::table('talent_admin_templates')->where('sub_institute_id', $sid)->whereNull('deleted_at')
            ->when($this->filter($request, 'category'), fn ($x, $v) => $x->where('category', $v))
            ->when($this->filter($request, 'status'), fn ($x, $v) => $x->where('status', $v))
            ->when(trim((string) $request->input('search')) !== '', fn ($x) => $x->where(fn ($s) => $s
                ->where('name', 'like', '%' . trim((string) $request->input('search')) . '%')
                ->orWhere('description', 'like', '%' . trim((string) $request->input('search')) . '%')));

        [$rows, $pagination] = $this->paginate($q->orderByDesc('updated_at'), $request, 10);

        return $this->ok($rows->map(fn ($r) => [
            'id' => (string) $r->id, 'name' => $r->name, 'category' => $r->category, 'module' => $r->module,
            'status' => $r->status, 'version' => $r->version, 'description' => $r->description,
            'body' => $r->body, 'lastUpdated' => $this->fmt($r->updated_at),
        ])->all(), ['pagination' => $pagination, 'categories' => self::TEMPLATE_CATEGORIES]);
    }

    /** POST talent/admin/templates */
    public function storeTemplate(Request $request)
    {
        $data = $this->validateTemplate($request);
        $id = DB::table('talent_admin_templates')->insertGetId($data + [
            'sub_institute_id' => $this->tenant(), 'created_by' => $this->actorId(), 'updated_by' => $this->actorId(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit('Templates', 'created', 'template', $data['name'], "Created a {$data['category']} template.");

        return $this->ok(['id' => $id], ['message' => 'Template created'], 201);
    }

    /** PUT talent/admin/templates/{id} */
    public function updateTemplate(Request $request, int $id)
    {
        $row = $this->ownedTemplate($id);
        $data = $this->validateTemplate($request, true);
        DB::table('talent_admin_templates')->where('id', $row->id)->update($data + ['updated_by' => $this->actorId(), 'updated_at' => now()]);
        $this->audit('Templates', 'updated', 'template', $data['name'] ?? $row->name, 'Edited the template.');

        return $this->ok(['id' => $id], ['message' => 'Template updated']);
    }

    /** DELETE talent/admin/templates/{id} */
    public function destroyTemplate(int $id)
    {
        $row = $this->ownedTemplate($id);
        DB::table('talent_admin_templates')->where('id', $row->id)->update(['deleted_at' => now(), 'deleted_by' => $this->actorId()]);
        $this->audit('Templates', 'deleted', 'template', $row->name, 'Deleted the template.');

        return $this->ok(['id' => $id], ['message' => 'Template deleted']);
    }

    // -------------------------------------------------------------- Permissions

    /**
     * GET talent/admin/permissions
     * Read only. Talent Management access follows the same rule the HR routes enforce
     * (App\Http\Middleware\RequireHrRole): a profile whose name contains one of the HR keywords.
     */
    public function permissions()
    {
        $sid = $this->tenant();
        $keywords = $this->hrKeywords();
        $counts = DB::table('tbluser')->where('sub_institute_id', $sid)->where('status', 1)
            ->selectRaw('user_profile_id, count(*) c')->groupBy('user_profile_id')->pluck('c', 'user_profile_id');

        $rows = $this->profilesQuery($sid)->orderBy('sort_order')->orderBy('name')->get()->map(function ($p) use ($counts, $keywords) {
            $name = strtolower((string) $p->name);
            $isStudent = in_array($name, ['student', 'parent'], true);
            $hr = ! $isStudent && collect($keywords)->contains(fn ($k) => $k !== '' && str_contains($name, $k));

            return [
                'id' => (string) $p->id, 'role' => $p->name, 'description' => $p->description,
                'users' => (int) ($counts[$p->id] ?? 0), 'hrAccess' => $hr,
                'areas' => $hr
                    ? ['Recruitment' => 'Manage', 'Onboarding' => 'Manage', 'Performance' => 'Manage', 'Mobility' => 'Manage', 'Offboarding' => 'Manage', 'Administration' => 'Manage']
                    : ['Recruitment' => 'None', 'Onboarding' => 'None', 'Performance' => 'None', 'Mobility' => 'None', 'Offboarding' => 'None', 'Administration' => 'None'],
            ];
        })->values()->all();

        return $this->ok($rows, ['keywords' => $keywords]);
    }

    // ------------------------------------------------------------- Integrations

    /** GET talent/admin/integrations */
    public function integrations(Request $request)
    {
        $rows = DB::table('talent_admin_integrations')->where('sub_institute_id', $this->tenant())
            ->when($this->filter($request, 'category'), fn ($x, $v) => $x->where('category', $v))
            ->orderBy('category')->orderBy('name')->get()->map(function ($r) {
                $oauth = $r->provider ? AdminIntegrationController::oauth($r->provider, $this->tenant()) : null;
                $kind = match (true) {
                    $r->provider === 'builtin' => 'builtin',
                    $r->provider === 'smtp' => 'smtp',
                    $oauth !== null => 'oauth',
                    default => 'unavailable',
                };

                return [
                    'id' => (string) $r->id, 'name' => $r->name, 'category' => $r->category, 'status' => $r->status,
                    'description' => $r->description, 'lastSync' => $r->last_sync_at ? $this->fmt($r->last_sync_at) : null, 'lastSyncNote' => $r->last_sync_note,
                    'kind' => $kind, 'account' => $r->connected_account,
                    'providerLabel' => $oauth['label'] ?? null,
                    'setupRequired' => $kind === 'oauth' && (empty($oauth['client_id']) || empty($oauth['client_secret'])),
                ];
            })->all();

        return $this->ok($rows, ['categories' => self::INTEGRATION_CATEGORIES, 'canSetup' => AdminIntegrationController::isSuperAdmin()]);
    }

    // ------------------------------------------------------------ Configuration

    /** GET talent/admin/settings */
    public function settings()
    {
        $stored = DB::table('talent_admin_settings')->where('sub_institute_id', $this->tenant())->pluck('setting_value', 'setting_key');
        $out = [];
        foreach (self::SETTINGS as $key => [$group, $label, $type, $default, $options, $help]) {
            $out[] = [
                'key' => $key, 'group' => $group, 'label' => $label, 'type' => $type, 'options' => $options, 'help' => $help,
                'value' => $stored->has($key) ? $this->cast($type, $stored[$key]) : $default, 'isDefault' => ! $stored->has($key),
            ];
        }

        return $this->ok($out);
    }

    /** PUT talent/admin/settings  { values: { key: value, ... } } */
    public function updateSettings(Request $request)
    {
        $values = $request->validate(['values' => 'required|array'])['values'];
        $sid = $this->tenant();
        $changed = [];

        foreach ($values as $key => $value) {
            if (! isset(self::SETTINGS[$key])) {
                continue;
            }
            [$group, $label, $type, , $options] = self::SETTINGS[$key];
            if ($type === 'number') {
                abort_if(! is_numeric($value) || $value < 0 || $value > 1000, 422, "{$label} must be a number between 0 and 1000.");
                $value = (string) (int) $value;
            } elseif ($type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            } elseif ($type === 'select') {
                abort_if(! in_array((string) $value, $options, true), 422, "{$label} has an invalid value.");
                $value = (string) $value;
            } else {
                $value = mb_substr((string) $value, 0, 500);
            }

            $existing = DB::table('talent_admin_settings')->where('sub_institute_id', $sid)->where('setting_key', $key)->first();
            if ($existing && (string) $existing->setting_value === $value) {
                continue;
            }
            DB::table('talent_admin_settings')->updateOrInsert(
                ['sub_institute_id' => $sid, 'setting_key' => $key],
                ['setting_group' => $group, 'setting_value' => $value, 'updated_by' => $this->actorId(), 'updated_at' => now(), 'created_at' => $existing->created_at ?? now()]
            );
            $changed[] = $label;
        }

        if ($changed) {
            $this->audit('Configuration', 'settings_changed', 'setting', null, 'Changed: ' . implode(', ', $changed) . '.');
        }

        return $this->ok(['changed' => count($changed)], ['message' => $changed ? 'Settings saved' : 'Nothing to change']);
    }

    // -------------------------------------------------------------------- Audit

    /** GET talent/admin/audit */
    public function auditLog(Request $request)
    {
        $sid = $this->tenant();
        $q = DB::table('talent_admin_audit_events')->where('sub_institute_id', $sid)
            ->when($this->filter($request, 'module'), fn ($x, $v) => $x->where('module', $v))
            ->when($this->filter($request, 'action'), fn ($x, $v) => $x->where('action', $v))
            ->when(trim((string) $request->input('search')) !== '', fn ($x) => $x->where(fn ($s) => $s
                ->where('actor_name', 'like', '%' . trim((string) $request->input('search')) . '%')
                ->orWhere('entity_name', 'like', '%' . trim((string) $request->input('search')) . '%')
                ->orWhere('details', 'like', '%' . trim((string) $request->input('search')) . '%')));

        [$rows, $pagination] = $this->paginate($q->orderByDesc('created_at')->orderByDesc('id'), $request, 15);

        $summary = DB::table('talent_admin_audit_events')->where('sub_institute_id', $sid)->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('module, count(*) c')->groupBy('module')->pluck('c', 'module');

        return $this->ok($rows->map(fn ($r) => [
            'id' => (string) $r->id, 'actor' => $r->actor_name ?: 'System', 'module' => $r->module, 'action' => $r->action,
            'entityType' => $r->entity_type, 'entity' => $r->entity_name, 'details' => $r->details, 'when' => $this->fmt($r->created_at),
        ])->all(), ['pagination' => $pagination, 'summary' => $summary, 'modules' => DB::table('talent_admin_audit_events')->where('sub_institute_id', $sid)->distinct()->pluck('module')->all()]);
    }

    // ------------------------------------------------------------------ helpers

    private function tenant(): int
    {
        return (int) session()->get('sub_institute_id');
    }

    private function actorId(): ?int
    {
        return session()->get('user_id') !== null ? (int) session()->get('user_id') : null;
    }

    private function audit(string $module, string $action, ?string $entityType, ?string $entityName, ?string $details): void
    {
        $actor = $this->actorId() ? DB::table('tbluser')->where('id', $this->actorId())->first(['first_name', 'last_name']) : null;
        DB::table('talent_admin_audit_events')->insert([
            'sub_institute_id' => $this->tenant(), 'actor_id' => $this->actorId(),
            'actor_name' => $actor ? trim($actor->first_name . ' ' . $actor->last_name) : null,
            'module' => $module, 'action' => $action, 'entity_type' => $entityType, 'entity_name' => $entityName,
            'details' => $details, 'ip_address' => request()->ip(), 'created_at' => now(),
        ]);
    }

    private function filter(Request $request, string $key): ?string
    {
        $v = $request->input($key);

        return is_string($v) && $v !== '' && $v !== 'all' ? $v : null;
    }

    private function paginate($query, Request $request, int $default): array
    {
        $perPage = min(100, max(5, (int) ($request->input('per_page') ?: $default)));
        $page = max(1, (int) ($request->input('page') ?: 1));
        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get();

        return [$rows, ['total' => $total, 'per_page' => $perPage, 'current_page' => $page, 'last_page' => max(1, (int) ceil($total / $perPage))]];
    }

    private function fmt($ts): string
    {
        return \Carbon\Carbon::parse($ts)->format('d M Y, h:i A');
    }

    private function cast(string $type, $v)
    {
        return match ($type) {
            'number' => (int) $v,
            'boolean' => $v === '1' || $v === 1 || $v === true,
            default => (string) $v,
        };
    }

    private function profilesQuery(int $sid)
    {
        return DB::table('tbluserprofilemaster')->where('sub_institute_id', $sid)->where('status', 1);
    }

    private function hrKeywords(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', strtolower((string) env(
            'HR_PROFILE_KEYWORDS',
            'admin,hr,human,principal,director,manager,head,management,owner,chairman,trustee'
        ))))));
    }

    private function ownedTemplate(int $id)
    {
        $row = DB::table('talent_admin_templates')->where('sub_institute_id', $this->tenant())->whereNull('deleted_at')->where('id', $id)->first();
        abort_if(! $row, 404, 'Template not found');

        return $row;
    }

    private function validateTemplate(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes|required' : 'required';

        return $request->validate([
            'name' => "$req|string|max:191",
            'category' => "$req|in:" . implode(',', self::TEMPLATE_CATEGORIES),
            'module' => 'nullable|string|max:50',
            'status' => 'nullable|in:Active,Draft,Archived',
            'version' => 'nullable|string|max:20',
            'description' => 'nullable|string|max:1000',
            'body' => 'nullable|string|max:20000',
        ]);
    }

    private function ok($data, array $extra = [], int $code = 200)
    {
        return response()->json(['status' => 1, 'data' => $data] + $extra, $code);
    }
}
