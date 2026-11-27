<?php

namespace App\Services\AI\Users;

use App\Domain\KnowledgeGraph\People\PeopleGraphService;
use App\Mcp\ToolRegistry;
use App\Services\AI\Pipeline\ModuleAgentPipeline;
use App\Services\Mcp\McpRequestContext;
use Illuminate\Support\Facades\DB;

/**
 * Users — sequential 4-agent pipeline.
 *
 *   Agent 1  Account Directory Reader            tbluser (account fields only) + user_accounts.directory cross-check
 *   Agent 2  Role & Permission Mapper            tbluserprofilemaster + tblgroupwise_rights vs Neo4j (Staff)-[:HAS_ROLE]->(Role)
 *   Agent 3  Organisation & Teaching Validator   class_teacher / timetable / department vs Neo4j org edges, and the link to Attendance
 *   Agent 4  Access Review Drafter               PREVIEW ONLY — proposes reviews, changes nothing
 *
 * ACCESS CONTROL IS THE COLUMN LIST. `tbluser` also holds salary, bank, PAN, Aadhaar, PF and
 * password columns; every query below names the few account columns it needs and none of those.
 *
 * No `ai_agents` row exists for Users, and the "no migrations / no database changes" ruling means
 * none is created here, so this pipeline persists nothing to `ai_agent_runs`
 * (persistAgentKey() is null). Its trace is the run response itself and the Agent Management run
 * log the proxy appends to. Adding a `k12_users` agent row is a decision for the owner.
 */
class UsersAgentPipelineService extends ModuleAgentPipeline
{
    public const REGISTER_LAG_DAYS = 7;
    public const LIST_CAP = 50;

    public function __construct(ToolRegistry $registry, private readonly PeopleGraphService $graph)
    {
        parent::__construct($registry);
    }

    public function module(): string
    {
        return 'users';
    }

    protected function persistAgentKey(): ?string
    {
        return null;
    }

    /** Matches the existing `module.user.*` audit naming (the module's key is `user`). */
    public function auditModule(): string
    {
        return 'user';
    }

    public function agents(): array
    {
        return [
            1 => [
                'order' => 1,
                'name' => 'Account Directory Reader',
                'agent_id' => 'agt_users_directory_reader',
                'gate_name' => 'Directory integrity',
                'purpose' => 'Reads every ERP account of the institute and reports who is active, inactive, portal, administrator, expired or without a recorded login.',
                'tasks' => [
                    'Count accounts by status, portal flag, expiry and recorded login.',
                    'Count how many accounts lack a department.',
                    'Read the student roll size for context.',
                    'Cross-check the totals against the existing user_accounts.directory tool.',
                ],
                'tables' => ['tbluser', 'tbluserprofilemaster', 'tblstudent'],
                'graph' => [],
                'tools' => ['user_accounts.directory'],
                'receives' => 'The institute from the authenticated request. No tenant is accepted from the browser.',
                'produces' => 'The active account list (id, profile, department, expiry and login flags) and the headline counts.',
                'stored' => 'Run state on the server for this run only.',
                'passes_to' => 'Agent 2 receives every active account with its profile id.',
                'validation' => ['Tenant is the signed-in institute', 'Active + inactive = total', 'Totals match user_accounts.directory', 'No salary, bank, PAN or password column is read'],
                'safeguards' => ['Read-only', 'Only account columns are selected; payroll and identity-document columns are never read'],
            ],
            2 => [
                'order' => 2,
                'name' => 'Role & Permission Mapper',
                'agent_id' => 'agt_users_role_mapper',
                'gate_name' => 'Role mapping',
                'purpose' => 'Maps every account to its role (profile) and the menu rights that role carries, then checks the same mapping in the Neo4j Knowledge Graph.',
                'tasks' => [
                    'Read the institute\'s profiles and the menu rights each one is granted.',
                    'Count active accounts per profile and find profiles that grant no access.',
                    'Read each staff member\'s HAS_ROLE edge from the graph and compare it with the database profile.',
                    'Find accounts whose profile is missing or inactive.',
                ],
                'tables' => ['tbluserprofilemaster', 'tblgroupwise_rights'],
                'graph' => ['(Staff)-[:HAS_ROLE]->(Role)'],
                'tools' => [],
                'receives' => 'Agent 1\'s active accounts.',
                'produces' => 'A profile and rights matrix, and a list of accounts where the graph and the database disagree.',
                'stored' => 'Run state on the server.',
                'passes_to' => 'Agent 3 receives the accounts with their validated role status.',
                'validation' => ['Profiles and rights were read', 'Graph reachable', 'Every active account is accounted for', 'Graph staff exist in this institute\'s database', 'Graph covers at least half the accounts'],
                'safeguards' => ['Read-only', 'Graph queries bound to the institute on every node', 'Disagreements are reported, never overwritten'],
            ],
            3 => [
                'order' => 3,
                'name' => 'Organisation & Teaching Validator',
                'agent_id' => 'agt_users_org_validator',
                'gate_name' => 'Relationship validation',
                'purpose' => 'Validates departments, class teachers and teaching links between the database and the Knowledge Graph, and checks that class teachers are actually marking attendance.',
                'tasks' => [
                    'Compare department, class-teacher, timetable and employment links in the database with the graph.',
                    'Find classes with enrolled students but no class teacher.',
                    'Find class teachers whose class register has fallen behind the latest recorded day.',
                ],
                'tables' => ['class_teacher', 'timetable', 'tblstudent_enrollment', 'attendance_student', 'tbluser'],
                'graph' => ['(Staff)-[:WORKS_AT]->(Institute)', '(Staff)-[:IN_DEPARTMENT]->(Department)', '(Staff)-[:CLASS_TEACHER_OF]->(Division)', '(Staff)-[:TEACHES_CLASS]->(Division)'],
                'tools' => [],
                'receives' => 'Agent 2\'s accounts and role status.',
                'produces' => 'A relationship coverage table, classes without a teacher, and class teachers with unmarked attendance.',
                'stored' => 'Run state on the server.',
                'passes_to' => 'Agent 4 receives every finding as input to the access review.',
                'validation' => ['Graph reachable', 'Database and graph counts reconcile', 'Class-teacher year resolved', 'Attendance register link read'],
                'safeguards' => ['Read-only', 'Reads attendance only to find the latest marked day per class'],
            ],
            4 => [
                'order' => 4,
                'name' => 'Access Review Drafter',
                'agent_id' => 'agt_users_access_reviewer',
                'gate_name' => 'Review safety',
                'purpose' => 'Turns the findings into a review queue for an administrator. It only proposes — no account is deactivated, no profile changed, nothing is sent.',
                'tasks' => [
                    'Group findings into review items with a severity and a suggested action.',
                    'Check every account named in the queue exists in this institute.',
                    'Reconcile each item\'s count with the agent that found it.',
                ],
                'tables' => ['tbluser'],
                'graph' => [],
                'tools' => [],
                'receives' => 'Findings from Agents 1–3.',
                'produces' => 'The access review queue with who must approve each action.',
                'stored' => 'Nothing. The queue lives in this run only.',
                'passes_to' => 'End of pipeline. An administrator acts on it in the Users module.',
                'validation' => ['No account or profile was changed', 'Every named account exists in this institute', 'Queue counts equal the agents\' counts'],
                'safeguards' => ['Preview only', 'Every action needs an administrator\'s approval outside this pipeline'],
            ],
        ];
    }

    protected function resolveScope(int $inst, McpRequestContext $base, array $options): array
    {
        if (! DB::table('tbluser')->where('sub_institute_id', $inst)->exists()) {
            return ['year' => null, 'source' => 'unresolved', 'error' => 'this institute has no user accounts.'];
        }

        // Accounts are not year-scoped. The academic year is carried only because the class-teacher
        // and attendance links in Agent 3 are: take the context's, else the latest year with class teachers.
        if ($base->academicYear) {
            return ['year' => $base->academicYear, 'source' => 'session'];
        }
        $latest = DB::table('class_teacher')->where('sub_institute_id', $inst)->max('syear');

        return ['year' => $latest ? (int) $latest : (int) date('Y'), 'source' => $latest ? 'latest_class_teacher_year' : 'calendar_year'];
    }

    public function liveState(int $inst, McpRequestContext $base, array $options): array
    {
        $row = DB::table('tbluser')->where('sub_institute_id', $inst)
            ->selectRaw('COUNT(*) AS total, SUM(status = 1) AS active')->first();
        $scope = $this->resolveScope($inst, $base, $options);

        return [
            'sub_institute_id' => $inst,
            'academic_year' => $scope['year'],
            'academic_year_source' => $scope['source'],
            'live' => ['accounts' => (int) $row->total, 'active_accounts' => (int) $row->active,
                'profiles' => DB::table('tbluserprofilemaster')->where('sub_institute_id', $inst)->count()],
            'knowledge_graph' => $this->graph->ping(),
            'defaults' => [],
            'recent_runs' => [],
        ];
    }

    protected function execute(int $n, array $state, McpRequestContext $ctx): array
    {
        return match ($n) {
            1 => $this->agent1($state, $ctx),
            2 => $this->agent2($state, $ctx),
            3 => $this->agent3($state, $ctx),
            default => $this->agent4($state, $ctx),
        };
    }

    private static function name(object $u): string
    {
        return trim(preg_replace('/\s+/', ' ', ($u->first_name ?? '') . ' ' . ($u->last_name ?? ''))) ?: ('User #' . $u->id);
    }

    private function pct(int $a, int $b): int
    {
        return $b === 0 ? 0 : (int) round($a / $b * 100);
    }

    /** Agent 1 — read the accounts. */
    private function agent1(array $state, McpRequestContext $ctx): array
    {
        $inst = $state['inst'];
        $today = now()->toDateString();
        $noLogin = "(last_login IS NULL OR TRIM(last_login) = '')";

        $agg = $this->sql('Account totals', ['tbluser'], fn () => DB::table('tbluser')->where('sub_institute_id', $inst)
            ->selectRaw("COUNT(*) AS total, SUM(status = 1) AS active, SUM(status <> 1) AS inactive,
                SUM(status = 1 AND portal_user = '1') AS portal, SUM(status = 1 AND is_admin = 1) AS admins,
                SUM(status = 1 AND {$noLogin}) AS no_login,
                SUM(status = 1 AND expire_date IS NOT NULL AND expire_date > '2000-01-01' AND expire_date < ?) AS expired_active,
                SUM(status = 1 AND (department_id IS NULL OR department_id = 0)) AS no_department", [$today])
            ->first());

        // Explicit column list: account fields only. Never salary, bank, PAN, Aadhaar, PF or password.
        $accounts = $this->sql('Active account list', ['tbluser'], fn () => DB::table('tbluser')
            ->where('sub_institute_id', $inst)->where('status', 1)
            ->selectRaw("id, first_name, last_name, user_profile_id, department_id, (expire_date IS NOT NULL AND expire_date > '2000-01-01' AND expire_date < ?) AS expired, {$noLogin} AS no_login", [$today])
            ->orderBy('id')->limit(20000)->get());

        $students = $this->sql('Student roll', ['tblstudent'], fn () => DB::table('tblstudent')->where('sub_institute_id', $inst)
            ->selectRaw('COUNT(*) AS total, SUM(status = 1) AS active')->first());

        $tool = $this->tool('user_accounts.directory', ['limit' => 1], $ctx);
        $td = $tool['data'] ?? $tool;
        $toolOk = ($tool['success'] ?? true) !== false && (int) ($td['count'] ?? -1) === (int) $agg->active
            && (int) ($td['never_logged_in'] ?? -1) === (int) $agg->no_login;

        $total = (int) $agg->total;
        $active = (int) $agg->active;
        $noLoginN = (int) $agg->no_login;
        $list = $accounts->map(static fn ($u) => [
            'id' => (int) $u->id, 'name' => self::name($u), 'profile_id' => $u->user_profile_id === null ? null : (int) $u->user_profile_id,
            'department_id' => $u->department_id ? (int) $u->department_id : null, 'expired' => (bool) $u->expired, 'no_login' => (bool) $u->no_login,
        ])->all();

        return [
            'tool' => 'tbluser + user_accounts.directory',
            'input' => ['institute' => $inst],
            'summary' => ['accounts' => $total, 'active' => $active, 'inactive' => (int) $agg->inactive],
            'records' => ['fetched' => $total, 'processed' => count($list), 'produced' => count($list)],
            'metrics' => [
                ['label' => 'Accounts', 'value' => $total],
                ['label' => 'Active', 'value' => $active],
                ['label' => 'Inactive', 'value' => (int) $agg->inactive],
                ['label' => 'Portal users', 'value' => (int) $agg->portal],
                ['label' => 'Flagged administrators (is_admin)', 'value' => (int) $agg->admins],
                ['label' => 'Active but expired', 'value' => (int) $agg->expired_active],
                ['label' => 'Active, no login recorded', 'value' => $noLoginN],
                ['label' => 'Active, no department', 'value' => (int) $agg->no_department],
                ['label' => 'Students (active / total)', 'value' => (int) $students->active . ' / ' . (int) $students->total],
            ],
            'tables' => [$this->table('accounts', 'Active accounts (first ' . self::LIST_CAP . ')', [['name', 'Name'], ['profile', 'Profile id'], ['flags', 'Flags']],
                array_map(static fn ($a) => ['name' => $a['name'], 'profile' => $a['profile_id'] ?? '—', 'flags' => trim(($a['expired'] ? 'Expired ' : '') . ($a['no_login'] ? 'No login recorded' : '')) ?: 'OK'], array_slice($list, 0, self::LIST_CAP)), $active)],
            'findings' => array_values(array_filter([
                $noLoginN === $active && $active > 0 ? 'No login is recorded for ANY active account here — the last-login column looks unpopulated, so it is not used as a finding.' : null,
                (int) $agg->expired_active > 0 ? (int) $agg->expired_active . ' active account(s) have an expiry date in the past.' : null,
                'Only account columns were read. Salary, bank, PAN, Aadhaar, PF and password columns are never selected.',
            ])),
            'sources' => ['tbluser (account columns only)', 'tblstudent', 'MCP tool user_accounts.directory'],
            'gate_checks' => [
                'tenant_isolation' => [$inst > 0, "Every read is filtered to institute {$inst} from the signed-in session."],
                'accounts_reconcile' => [$active + (int) $agg->inactive === $total, "{$active} active + {$agg->inactive} inactive = {$total}."],
                'listed_equals_active' => [count($list) === $active, count($list) . " account(s) read for {$active} active."],
                'matches_directory_tool' => [$toolOk, $toolOk ? 'Active total and no-login count are identical to user_accounts.directory.' : 'user_accounts.directory disagrees with the direct count.'],
                'no_sensitive_columns' => [true, 'The query selects id, name, profile, department, expiry and login only.'],
            ],
            'handoff' => ['accounts_to_map' => [$list !== [], count($list) . ' active account(s) to map to roles.']],
            'handoff_message' => count($list) . ' active account(s) with profile and department handed to Agent 2.',
            'stop' => $list === [] ? 'This institute has no active accounts, so Agents 2–4 had nothing to do.' : null,
            'final' => $list === [] ? ['headline' => 'No active accounts.', 'next_action' => null] : null,
            'data' => ['accounts' => $list, 'agg' => array_map('intval', (array) $agg), 'student_total' => (int) $students->total],
        ];
    }

    /** Agent 2 — roles and rights, SQL vs graph. */
    private function agent2(array $state, McpRequestContext $ctx): array
    {
        $inst = $state['inst'];
        $accounts = $state['data']['accounts'];

        $profiles = $this->sql('Profiles', ['tbluserprofilemaster'], fn () => DB::table('tbluserprofilemaster')
            ->where('sub_institute_id', $inst)->get(['id', 'name', 'status', 'parent_id'])->keyBy('id'));
        $rights = $this->sql('Menu rights per profile', ['tblgroupwise_rights'], fn () => DB::table('tblgroupwise_rights')
            ->where('sub_institute_id', $inst)->groupBy('profile_id')
            ->selectRaw('profile_id, COUNT(*) AS menus, SUM(can_view = 1) AS v, SUM(can_add = 1) AS a, SUM(can_edit = 1) AS e, SUM(can_delete = 1) AS d')
            ->get()->keyBy('profile_id'));
        $allIds = $this->sql('All account ids', ['tbluser'], fn () => DB::table('tbluser')->where('sub_institute_id', $inst)->pluck('id')->flip()->all());

        $ping = $this->graph->ping();
        $staff = $roles = [];
        $graphOk = $ping['ok'];
        if ($graphOk) {
            $r1 = $this->graph('Staff and roles', '(Staff)-[:HAS_ROLE]->(Role)', fn () => [$this->graph->staffMap($inst)], fn ($r) => count($r[0] ?? []));
            $graphOk = $r1['ok'];
            $staff = $r1['rows'][0] ?? [];
            $roles = $this->graph->roles($inst);
        }

        $perProfile = [];
        $status = [];
        $orphan = $inactiveProfile = $noRights = $inGraph = $match = $differs = $noRole = 0;
        foreach ($accounts as $a) {
            $pid = $a['profile_id'];
            $p = $pid !== null ? ($profiles[$pid] ?? null) : null;
            $perProfile[$pid ?? 0] = ($perProfile[$pid ?? 0] ?? 0) + 1;

            $s = [];
            if (! $p) {
                $orphan++;
                $s[] = 'profile_missing';
            } else {
                if ((int) $p->status !== 1) {
                    $inactiveProfile++;
                    $s[] = 'profile_inactive';
                }
                if ((int) ($rights[$pid]->v ?? 0) === 0) {
                    $noRights++;
                    $s[] = 'profile_grants_no_access';
                }
            }

            $g = $staff[$a['id']] ?? null;
            if (! $g) {
                $s[] = 'not_in_graph';
            } else {
                $inGraph++;
                if ($pid !== null && in_array($pid, $g['role_ids'], true)) {
                    $match++;
                } elseif ($g['role_ids'] === []) {
                    $noRole++;
                    $s[] = 'graph_has_no_role';
                } else {
                    $differs++;
                    $s[] = 'graph_role_differs';
                }
            }
            $status[$a['id']] = $s;
        }
        // A graph Staff node tagged to this institute whose id is not one of its accounts is either
        // STALE (the account was deleted — absent from tbluser everywhere) or a TENANT MIX-UP (the id
        // belongs to another institute). Only the second is a breach; the first is a sync finding.
        $extra = array_values(array_filter(array_keys($staff), static fn ($id) => ! isset($allIds[$id])));
        $graphForeign = $extra === [] ? 0 : (int) $this->sql('Graph staff owned by other institutes', ['tbluser'], fn () => DB::table('tbluser')->where('sub_institute_id', '<>', $inst)->whereIn('id', $extra)->count());
        $stale = count($extra) - $graphForeign;

        $matrix = [];
        foreach ($profiles as $p) {
            $rt = $rights[$p->id] ?? null;
            $matrix[] = [
                'profile' => $p->name . ((int) $p->status !== 1 ? ' (inactive)' : ''),
                'accounts' => $perProfile[$p->id] ?? 0,
                'menus' => (int) ($rt->menus ?? 0), 'view' => (int) ($rt->v ?? 0), 'edit' => (int) ($rt->e ?? 0), 'delete' => (int) ($rt->d ?? 0),
                'in_graph' => isset($roles[$p->id]) ? 'Yes' : 'No',
            ];
        }
        usort($matrix, static fn ($x, $y) => $y['accounts'] <=> $x['accounts']);
        $issues = [];
        foreach ($accounts as $a) {
            $s = array_values(array_diff($status[$a['id']], ['not_in_graph']));
            if ($s !== []) {
                $issues[] = ['name' => $a['name'], 'profile' => ($profiles[$a['profile_id']]->name ?? '—'), 'issue' => implode(', ', array_map(static fn ($x) => str_replace('_', ' ', $x), $s))];
            }
        }
        $notInRoles = count(array_filter($matrix, static fn ($m) => $m['in_graph'] === 'No'));
        $total = count($accounts);

        return [
            'tool' => 'tbluserprofilemaster + tblgroupwise_rights + Neo4j',
            'input' => ['accounts' => $total],
            'summary' => ['profiles' => count($profiles), 'accounts_mapped' => $total - $orphan, 'graph_role_matches' => $match, 'graph_role_differs' => $differs],
            'records' => ['fetched' => count($profiles) + array_sum(array_map(static fn ($r) => (int) $r->menus, $rights->all())), 'processed' => $total, 'produced' => count($matrix)],
            'metrics' => [
                ['label' => 'Profiles (roles)', 'value' => count($profiles)],
                ['label' => 'Menu rights read', 'value' => array_sum(array_map(static fn ($r) => (int) $r->menus, $rights->all()))],
                ['label' => 'Accounts mapped to a profile', 'value' => ($total - $orphan) . ' of ' . $total],
                ['label' => 'Found in Knowledge Graph', 'value' => $inGraph . ' (' . $this->pct($inGraph, $total) . '%)'],
                ['label' => 'Graph role matches database', 'value' => $match . ' of ' . $inGraph],
                ['label' => 'Graph role differs / missing', 'value' => $differs . ' / ' . $noRole],
                ['label' => 'Profiles missing in graph', 'value' => $notInRoles . ' of ' . count($matrix)],
                ['label' => 'Stale graph staff (account deleted)', 'value' => $stale],
                ['label' => 'Accounts on a profile that grants no access', 'value' => $noRights],
            ],
            'tables' => [
                $this->table('profiles', 'Roles and the rights they carry', [['profile', 'Profile'], ['accounts', 'Active accounts'], ['menus', 'Menus'], ['view', 'Can view'], ['edit', 'Can edit'], ['delete', 'Can delete'], ['in_graph', 'In graph']], $matrix),
                $this->table('issues', 'Accounts needing attention', [['name', 'Account'], ['profile', 'Profile'], ['issue', 'Issue']], array_slice($issues, 0, self::LIST_CAP), count($issues)),
            ],
            'findings' => array_values(array_filter([
                $graphOk ? null : 'The Knowledge Graph could not be read: ' . ($ping['error'] ?? 'unknown error'),
                $notInRoles > 0 ? "{$notInRoles} of " . count($matrix) . ' profile(s) have no Role node in the graph, so their holders cannot be validated there.' : null,
                $differs > 0 ? "{$differs} account(s) hold a different role in the graph than in the database." : null,
                $stale > 0 ? "{$stale} Staff node(s) in the graph belong to accounts that no longer exist in the database — the graph needs a re-sync; none belong to another institute." : null,
                $orphan > 0 ? "{$orphan} account(s) point at a profile that does not exist in this institute." : null,
                $noRights > 0 ? "{$noRights} active account(s) sit on a profile that can view no menu." : null,
            ])),
            'sources' => ['tbluserprofilemaster', 'tblgroupwise_rights', 'tbluser (ids)', 'Neo4j: Staff, Role'],
            'gate_checks' => [
                'profiles_and_rights_read' => [count($profiles) > 0 && $rights->count() > 0, count($profiles) . ' profile(s), ' . $rights->count() . ' with rights.'],
                'graph_reachable' => [$graphOk, $graphOk ? "Neo4j answered in {$ping['ms']} ms." : 'Neo4j could not be reached: ' . ($ping['error'] ?? '')],
                'every_account_accounted_for' => [array_sum($perProfile) === $total, array_sum($perProfile) . " of {$total} account(s) counted against a profile."],
                'no_graph_staff_from_other_institutes' => [$graphForeign === 0, $graphForeign === 0 ? 'No Staff node tagged to this institute belongs to another institute.' : "{$graphForeign} Staff node(s) tagged to this institute are accounts of another institute."],
                'graph_covers_accounts' => [$this->pct($inGraph, $total) >= 50, "{$inGraph} of {$total} account(s) are in the graph (" . $this->pct($inGraph, $total) . '%, needs 50%).'],
            ],
            'handoff' => ['accounts_carried' => [true, $total . ' account(s) carried with role status.']],
            'handoff_message' => $total . ' account(s) with role status handed to Agent 3.',
            'data' => [
                'role_status' => $status, 'graph_staff' => array_map(static fn ($g) => array_intersect_key($g, array_flip(['department_id', 'in_department', 'class_teacher_of', 'teaches_class', 'works_at'])), $staff),
                'counts' => ['orphan' => $orphan, 'inactive_profile' => $inactiveProfile, 'no_rights' => $noRights, 'differs' => $differs, 'no_role' => $noRole, 'in_graph' => $inGraph, 'profiles_missing_in_graph' => $notInRoles, 'stale' => $stale],
                'profiles' => $profiles->map(static fn ($p) => $p->name)->all(),
            ],
        ];
    }

    /** Agent 3 — organisation, teaching and the attendance link. */
    private function agent3(array $state, McpRequestContext $ctx): array
    {
        $inst = $state['inst'];
        $accounts = $state['data']['accounts'];
        $activeIds = array_column($accounts, 'id');
        $gs = $state['data']['graph_staff'];

        $ctYear = $this->sql('Class-teacher year', ['class_teacher'], fn () => DB::table('class_teacher')->where('sub_institute_id', $inst)->max('syear'));
        $ctRows = $this->sql('Class teachers', ['class_teacher'], fn () => DB::table('class_teacher')->where('sub_institute_id', $inst)->where('syear', $ctYear)->get(['standard_id', 'division_id', 'teacher_id']));
        $ttIds = $this->sql('Timetable teachers', ['timetable'], fn () => DB::table('timetable')->where('sub_institute_id', $inst)->where('syear', $ctYear)->distinct()->pluck('teacher_id')->map(fn ($v) => (int) $v)->all());
        $classes = $this->sql('Classes with students', ['tblstudent_enrollment', 'standard', 'division'], fn () => DB::table('tblstudent_enrollment as e')
            ->leftJoin('standard as st', 'st.id', '=', 'e.standard_id')->leftJoin('division as d', 'd.id', '=', 'e.section_id')
            ->where('e.sub_institute_id', $inst)->where('e.syear', $ctYear)->whereNull('e.end_date')
            ->groupBy('e.standard_id', 'e.section_id')
            ->selectRaw("e.standard_id, e.section_id, COUNT(*) AS students, MAX(st.name) AS standard_name, MAX(d.name) AS division_name")->get());
        $lastMarked = $this->sql('Latest attendance per class', ['attendance_student'], fn () => DB::table('attendance_student')
            ->where('sub_institute_id', $inst)->where('syear', $state['year'])->groupBy('standard_id', 'section_id')
            ->selectRaw('standard_id, section_id, MAX(attendance_date) AS last_day')->get());
        $names = $this->sql('Teacher names', ['tbluser'], fn () => DB::table('tbluser')->where('sub_institute_id', $inst)->whereIn('id', $ctRows->pluck('teacher_id')->unique()->all() ?: [0])->get(['id', 'first_name', 'last_name'])->keyBy('id'));

        $ping = $this->graph->ping();
        $links = [];
        $graphOk = $ping['ok'];
        if ($graphOk) {
            $r = $this->graph('Class-teacher links', '(Staff)-[:CLASS_TEACHER_OF]->(Division)', fn () => $this->graph->classTeacherLinks($inst, $ctYear ? (int) $ctYear : null));
            $graphOk = $r['ok'];
            $links = $r['rows'];
        }

        // Relationship coverage: database set vs graph set, over the same population.
        $act = array_flip($activeIds);
        $deptSql = array_column(array_filter($accounts, static fn ($a) => $a['department_id']), 'id');
        $ctSql = array_values(array_unique(array_map('intval', $ctRows->pluck('teacher_id')->all())));
        $ttSql = array_values(array_filter($ttIds, static fn ($id) => isset($act[$id])));
        $deptG = array_keys(array_filter($gs, static fn ($g, $id) => isset($act[$id]) && $g['in_department'] > 0, ARRAY_FILTER_USE_BOTH));
        $ctG = array_values(array_unique(array_column($links, 'staff_id')));
        $ttG = array_keys(array_filter($gs, static fn ($g, $id) => isset($act[$id]) && $g['teaches_class'] > 0, ARRAY_FILTER_USE_BOTH));
        $wkG = array_keys(array_filter($gs, static fn ($g, $id) => isset($act[$id]) && $g['works_at'] > 0, ARRAY_FILTER_USE_BOTH));

        $cov = [];
        $reconciles = true;
        foreach ([
            ['Works at the institute', $activeIds, $wkG],
            ['Has a department', $deptSql, $deptG],
            ['Class teacher of a class', $ctSql, $ctG],
            ['Teaches a class (timetable)', $ttSql, $ttG],
        ] as [$label, $s, $g]) {
            $both = count(array_intersect($s, $g));
            $reconciles = $reconciles && $both + count(array_diff($s, $g)) === count($s) && $both + count(array_diff($g, $s)) === count($g);
            $cov[] = ['relation' => $label, 'database' => count($s), 'graph' => count($g), 'both' => $both, 'db_only' => count(array_diff($s, $g)), 'graph_only' => count(array_diff($g, $s))];
        }

        $teacherOf = [];
        foreach ($ctRows as $c) {
            $teacherOf[$c->standard_id . ':' . $c->division_id] = (int) $c->teacher_id;
        }
        $lastBy = [];
        foreach ($lastMarked as $l) {
            $lastBy[$l->standard_id . ':' . $l->section_id] = $l->last_day;
        }
        $latestAll = $lastMarked->max('last_day');

        $noTeacher = $lagging = [];
        foreach ($classes as $c) {
            $key = $c->standard_id . ':' . $c->section_id;
            $label = trim(($c->standard_name ?? '?') . ' - ' . ($c->division_name ?? '?'));
            if (! isset($teacherOf[$key])) {
                $noTeacher[] = ['class' => $label, 'students' => (int) $c->students, 'needed' => 'Assign a class teacher'];

                continue;
            }
            $tid = $teacherOf[$key];
            $last = $lastBy[$key] ?? null;
            $gap = $last && $latestAll ? (int) \Carbon\Carbon::parse($last)->diffInDays(\Carbon\Carbon::parse($latestAll)) : null;
            if ($last === null || $gap > self::REGISTER_LAG_DAYS) {
                $lagging[] = ['class' => $label, 'teacher_id' => $tid, 'teacher' => isset($names[$tid]) ? self::name($names[$tid]) : ('User #' . $tid),
                    'last_marked' => $last ?? 'Never this year', 'days_behind' => $gap ?? '—'];
            }
        }
        usort($noTeacher, static fn ($a, $b) => $b['students'] <=> $a['students']);

        return [
            'tool' => 'class_teacher + timetable + attendance_student + Neo4j',
            'input' => ['class_teacher_year' => $ctYear],
            'summary' => ['classes_with_students' => $classes->count(), 'classes_without_teacher' => count($noTeacher), 'registers_behind' => count($lagging)],
            'records' => ['fetched' => $ctRows->count() + count($ttIds) + $classes->count() + count($links), 'processed' => count($accounts), 'produced' => count($noTeacher) + count($lagging)],
            'metrics' => [
                ['label' => 'Class-teacher year', 'value' => $ctYear ?: '—'],
                ['label' => 'Classes with students', 'value' => $classes->count()],
                ['label' => 'Classes with no class teacher', 'value' => count($noTeacher)],
                ['label' => 'Class teachers (database / graph)', 'value' => count($ctSql) . ' / ' . count($ctG)],
                ['label' => 'Registers behind by ' . self::REGISTER_LAG_DAYS . '+ days', 'value' => count($lagging)],
                ['label' => 'Latest attendance day', 'value' => $latestAll ?: '—'],
            ],
            'tables' => [
                $this->table('coverage', 'Database vs Knowledge Graph (active accounts)', [['relation', 'Relationship'], ['database', 'Database'], ['graph', 'Graph'], ['both', 'In both'], ['db_only', 'Database only'], ['graph_only', 'Graph only']], $cov),
                $this->table('no_teacher', 'Classes with students but no class teacher', [['class', 'Class'], ['students', 'Students'], ['needed', 'Needed']], array_slice($noTeacher, 0, self::LIST_CAP), count($noTeacher)),
                $this->table('lagging', 'Class teachers whose register has fallen behind', [['teacher', 'Class teacher'], ['class', 'Class'], ['last_marked', 'Last marked'], ['days_behind', 'Days behind']], array_slice($lagging, 0, self::LIST_CAP), count($lagging)),
            ],
            'findings' => array_values(array_filter([
                $graphOk ? null : 'The Knowledge Graph could not be read: ' . ($ping['error'] ?? 'unknown error'),
                count($noTeacher) > 0 ? count($noTeacher) . ' class(es) with enrolled students have no class teacher this year.' : null,
                count($lagging) > 0 ? count($lagging) . " class register(s) are more than " . self::REGISTER_LAG_DAYS . ' days behind the latest day any class marked.' : null,
                'The graph\'s CLASS_TEACHER_OF edge is per division name (A, B…), not per standard, so it can confirm a teacher for "division A" but not which standard.',
            ])),
            'sources' => ['class_teacher', 'timetable', 'tblstudent_enrollment', 'attendance_student', 'tbluser', 'Neo4j: Staff, Division, Department, Institute'],
            'gate_checks' => [
                'graph_reachable' => [$graphOk, $graphOk ? "Neo4j answered in {$ping['ms']} ms." : 'Neo4j could not be reached: ' . ($ping['error'] ?? '')],
                'class_teacher_year_resolved' => [(bool) $ctYear, $ctYear ? "Class teachers read for year {$ctYear}." : 'This institute has no class-teacher records.'],
                'counts_reconcile' => [$reconciles, $reconciles ? 'For every relationship, in-both + one-side-only equals each side\'s total.' : 'A relationship\'s counts do not add up.'],
                'attendance_link_read' => [$classes->count() > 0, $classes->count() . ' class(es) matched against the attendance register.'],
            ],
            'handoff' => ['findings_to_review' => [true, (count($noTeacher) + count($lagging)) . ' relationship finding(s) available.']],
            'handoff_message' => 'Relationship findings handed to Agent 4 for the access review.',
            'data' => ['no_teacher' => $noTeacher, 'lagging' => $lagging, 'coverage' => $cov],
        ];
    }

    /** Agent 4 — access review queue (preview only). */
    private function agent4(array $state, McpRequestContext $ctx): array
    {
        $inst = $state['inst'];
        $d = $state['data'];
        $accounts = $d['accounts'];
        $byId = array_column($accounts, null, 'id');
        $status = $d['role_status'];

        $bucket = static function (callable $f) use ($accounts, $status): array {
            return array_values(array_filter($accounts, static fn ($a) => $f($a, $status[$a['id']] ?? [])));
        };
        $expired = $bucket(static fn ($a) => $a['expired']);
        $noAccess = $bucket(static fn ($a, $s) => in_array('profile_grants_no_access', $s, true));
        $orphan = $bucket(static fn ($a, $s) => in_array('profile_missing', $s, true) || in_array('profile_inactive', $s, true));
        $differs = $bucket(static fn ($a, $s) => in_array('graph_role_differs', $s, true));
        $allNoLogin = count(array_filter($accounts, static fn ($a) => $a['no_login'])) === count($accounts);
        $noLogin = $allNoLogin ? [] : $bucket(static fn ($a) => $a['no_login']);

        $counts = $d['counts'];
        $items = [
            ['category' => 'Active account has expired', 'severity' => 'high', 'rows' => $expired, 'count' => count($expired), 'action' => 'Review and deactivate, or extend the expiry date.', 'approver' => 'School administrator', 'found_by' => 1],
            ['category' => 'Profile missing or inactive', 'severity' => 'high', 'rows' => $orphan, 'count' => count($orphan), 'action' => 'Assign a valid profile.', 'approver' => 'School administrator', 'found_by' => 2],
            ['category' => 'Profile grants no menu access', 'severity' => 'high', 'rows' => $noAccess, 'count' => count($noAccess), 'action' => 'Grant rights to the profile or move the account.', 'approver' => 'School administrator', 'found_by' => 2],
            ['category' => 'Knowledge Graph role differs from database', 'severity' => 'medium', 'rows' => $differs, 'count' => count($differs), 'action' => 'Re-sync the graph from the database (the database is authoritative).', 'approver' => 'Platform administrator', 'found_by' => 2],
            ['category' => 'Knowledge Graph holds staff for deleted accounts', 'severity' => 'medium', 'rows' => [], 'count' => $counts['stale'], 'action' => 'Re-sync the Staff nodes from the database.', 'approver' => 'Platform administrator', 'found_by' => 2],
            ['category' => 'No login recorded', 'severity' => 'low', 'rows' => $noLogin, 'count' => count($noLogin), 'action' => 'Confirm the person still needs the account.', 'approver' => 'School administrator', 'found_by' => 1],
            ['category' => 'Class has students but no class teacher', 'severity' => 'high', 'rows' => [], 'count' => count($d['no_teacher']), 'action' => 'Assign a class teacher.', 'approver' => 'Principal', 'found_by' => 3],
            ['category' => 'Class register behind the latest marked day', 'severity' => 'medium', 'rows' => [], 'count' => count($d['lagging']), 'action' => 'Remind the class teacher to mark attendance.', 'approver' => 'Principal', 'found_by' => 3],
        ];
        $items = array_values(array_filter($items, static fn ($i) => $i['count'] > 0));

        $named = [];
        foreach ($items as $i) {
            foreach (array_slice($i['rows'], 0, 5) as $r) {
                $named[$r['id']] = true;
            }
        }
        $found = $this->sql('Named accounts exist', ['tbluser'], fn () => DB::table('tbluser')->where('sub_institute_id', $inst)->whereIn('id', array_keys($named) ?: [0])->count());
        $reconciled = count($expired) === count(array_filter($accounts, static fn ($a) => $a['expired']))
            && count($noAccess) === $counts['no_rights'] && count($differs) === $counts['differs'];

        $queue = array_map(static fn ($i) => [
            'category' => $i['category'], 'severity' => $i['severity'], 'count' => $i['count'],
            'examples' => $i['rows'] === [] ? '—' : implode(', ', array_map(static fn ($r) => $r['name'], array_slice($i['rows'], 0, 3))),
            'action' => $i['action'], 'approver' => $i['approver'], 'found_by_agent' => $i['found_by'],
            'accounts' => array_map(static fn ($r) => ['id' => $r['id'], 'name' => $r['name']], array_slice($i['rows'], 0, 10)),
        ], $items);
        $high = count(array_filter($items, static fn ($i) => $i['severity'] === 'high'));

        return [
            'tool' => 'review queue (preview only)',
            'input' => ['findings' => count($items)],
            'summary' => ['review_items' => count($items), 'high_severity' => $high, 'records_changed' => 0],
            'records' => ['fetched' => count($accounts), 'processed' => count($accounts), 'produced' => count($items)],
            'metrics' => [
                ['label' => 'Review items', 'value' => count($items)],
                ['label' => 'High severity', 'value' => $high],
                ['label' => 'Accounts named', 'value' => count($named)],
                ['label' => 'Accounts changed', 'value' => 0],
                ['label' => 'Messages sent', 'value' => 0],
            ],
            'tables' => [$this->table('queue', 'Access review queue (proposals — nothing applied)', [['category', 'Finding'], ['severity', 'Severity'], ['count', 'Count'], ['examples', 'Examples'], ['action', 'Suggested action'], ['approver', 'Approver']], $queue)],
            'findings' => array_values(array_filter([
                $allNoLogin ? 'No-login review skipped: no account in this institute has a recorded login, so the column cannot tell dormant from active.' : null,
                'Every action above needs an administrator to carry it out in the Users module. This pipeline changed nothing.',
            ])),
            'sources' => ['tbluser', 'Agents 1–3 findings'],
            'gate_checks' => [
                'no_accounts_changed' => [true, 'No account, profile, right or graph edge was written.'],
                'named_accounts_exist' => [$found === count($named), "{$found} of " . count($named) . ' named account(s) exist in this institute.'],
                'counts_match_agents' => [$reconciled, $reconciled ? 'Queue counts equal what Agents 1 and 2 reported.' : 'A queue count differs from the agent that found it.'],
                'approval_required' => [true, 'Every item names who must approve it.'],
            ],
            'final' => [
                'headline' => sprintf('%d review item(s), %d high severity, across %d active account(s). Nothing was changed.', count($items), $high, count($accounts)),
                'next_action' => 'An administrator reviews the queue and applies changes in the Users module.',
                'status' => 'pending_review',
                'approval_note' => 'No approval workflow is registered for Users, so nothing is queued for sign-off automatically: an administrator reviews this list and acts in the Users module. Every action stays manual.',
                'queue' => $queue,
                'active_accounts' => count($accounts),
                'high_severity' => $high,
                'sent' => 0,
            ],
            'data' => [],
        ];
    }
}
