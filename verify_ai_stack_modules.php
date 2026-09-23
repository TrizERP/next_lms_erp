<?php

/**
 * Registration and resolution check for every module that has an AI Stack.
 *
 * WHAT IT ANSWERS
 *
 * The three errors this was written for, in the order a person hits them:
 *
 *   "PTM has no row in ai_modules for this institute"  → the Policies tab
 *   "That module is not one this school has" (404)     → the Prompts tab
 *   "That module is not one this school has" (404)     → the Templates tab
 *
 * All three are the same fact — no `ai_modules` row — reported by three different callers.
 * So the first section runs `TemplateModuleCatalog::exists()`, which is the check behind
 * the 404, for every module against several real institutes, and the second shows the
 * `ai_modules` ids a policy save would scope to. A module that answers `yes` and reports a
 * scope id cannot produce either error.
 *
 * It then checks the things those errors hide: that each module's AI Stack route resolves
 * to that module and not a neighbour, that its report data sources are its own, that its
 * lifecycle bindings are present, that a first-time user lands on a worked example rather
 * than an empty tab, and that the Models tab resolves a real provider.
 *
 * WHY THE MODELS SECTION LOOKS AT CAPABILITIES AND NOT AT MODULES
 *
 * Because a provider is bound to an AI capability — `conversational_ai`, `generative_ai`,
 * `agent_reasoning` — and never to a product module. `AiConfigurationResolver::overview()`
 * enumerates the first kind; `ai_modules` holds the second. Searching the first for `ptm`
 * can only ever miss, which is what made four Models tabs report "no row" on a correctly
 * configured estate.
 *
 * IT ONLY READS. No INSERT, UPDATE or DELETE anywhere. Safe against production; running it
 * there is how you find out whether an estate is actually configured.
 *
 *   php verify_ai_stack_modules.php
 *
 * Cross-institute and cross-module isolation is a separate question, answered by
 * `check_module_ai_isolation.php`.
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\AI\Lifecycle\Modules\ModuleRegistry;
use App\Domain\AI\Templates\ReportDataSourceCatalog;
use App\Domain\AI\Templates\TemplateModuleCatalog;
use App\Domain\AI\Workspace\RouteMatcher;
use Illuminate\Support\Facades\DB;

$modules = ['fees','attendance','admissions','students','exam','ptm','hostel','student_request','circular','mobile_apps','student_icard','certificate','easy_com','timetable','student_medical',
    // Added 2026-09-24. Two of these keys predate the work — see the note beside their
    // routes below.
    'inward_outward','user_icard','petty_cash','consent','visitor_management','transportation',
    // Added 2026-09-25. Two of these keys carry a hyphen, and `migration-modules` is
    // the Utility module — see its routes below.
    'inventory','front_desk','task_management','complaint','migration-modules','document-templates'];

// Every institute that actually has users, so "the current sub_institute_id" is a real one.
$institutes = DB::table('tblstudent_enrollment')->where('sub_institute_id','>',0)
    ->distinct()->orderBy('sub_institute_id')->limit(6)->pluck('sub_institute_id')->all();

$catalog = app(TemplateModuleCatalog::class);
$sources = app(ReportDataSourceCatalog::class);
$registry = app(ModuleRegistry::class);
$matcher = new RouteMatcher();

echo "TemplateModuleCatalog::exists() — the check behind \"That module is not one this school has\" (404)\n";
echo str_repeat('-', 108)."\n";
printf("%-18s %s\n", 'module', 'resolves for sub_institute_id: '.implode(', ', $institutes));
foreach ($modules as $m) {
    $row = [];
    foreach ($institutes as $i) { $row[] = $catalog->exists($m, $i) ? 'yes' : 'NO'; }
    printf("%-18s %s\n", $m, implode('  ', array_map(fn($v) => str_pad($v, 4), $row)));
}

echo "\nPolicy scoping — ai_modules ids a policy save would use (moduleIdsFor)\n";
echo str_repeat('-', 108)."\n";
foreach ($modules as $m) {
    $ids = DB::table('ai_modules')->where('module_key',$m)
        ->where(fn($q) => $q->where('sub_institute_id', $institutes[0])->orWhereNull('sub_institute_id'))
        ->pluck('id')->all();
    printf("%-18s %s\n", $m, $ids ? 'scope_id '.implode(',', $ids).' — policies can be saved' : 'NONE — save blocked');
}

echo "\nReport data sources and route resolution\n";
echo str_repeat('-', 108)."\n";
$routes = [
    'fees' => '/fees/ai-stack', 'attendance' => '/modules/attendance/ai-stack',
    'admissions' => '/modules/admission/ai-stack', 'students' => '/modules/student/ai-stack',
    'exam' => '/modules/exam/ai-stack', 'ptm' => '/modules/ptm/ai-stack',
    'hostel' => '/modules/hostel/ai-stack', 'student_request' => '/modules/student-request/ai-stack',
    'circular' => '/modules/circular/ai-stack',
    'mobile_apps' => '/modules/mobile-apps/ai-stack',
    'student_icard' => '/modules/student-i-card/ai-stack',
    'certificate' => '/modules/certificate/ai-stack',
    // The menu slug, not the module key. See `lib/communication/communication-ai-stack.ts`
    // for why Communication keeps the `easy_com` key it has always had.
    'easy_com' => '/modules/communication/ai-stack',
    'timetable' => '/modules/timetable/ai-stack',
    'student_medical' => '/modules/student-medical/ai-stack',
    // The menu slugs again, not the module keys: Inward is keyed `inward_outward` and
    // Transport `transportation`, which are the keys those two rows have carried since the
    // workspace was seeded. See `lib/inward/inward-ai-stack.ts` for why neither was
    // given a new one.
    'inward_outward' => '/modules/inward-outward/ai-stack',
    'user_icard' => '/modules/user-i-card/ai-stack',
    'petty_cash' => '/modules/petty-cash/ai-stack',
    'consent' => '/modules/consent/ai-stack',
    'visitor_management' => '/modules/visitor-management/ai-stack',
    'transportation' => '/modules/transport/ai-stack',
    'inventory' => '/modules/inventory/ai-stack',
    'front_desk' => '/modules/front-desk/ai-stack',
    'task_management' => '/modules/task-management/ai-stack',
    'complaint' => '/modules/complaint/ai-stack',
    // The menu slug is `utility`; the KEY is `migration-modules`, which has owned
    // /Utility/** since the workspace was seeded.
    'migration-modules' => '/modules/utility/ai-stack',
    'document-templates' => '/modules/document-templates/ai-stack',
];
foreach ($modules as $m) {
    $srcs = array_column($sources->forModule($m), 'name');
    // Which module the workspace resolves that route to, from ai_modules.route_patterns.
    $winner = null; $best = -1;
    foreach (DB::table('ai_modules')->where('status',1)->get(['module_key','route_patterns']) as $row) {
        $pats = json_decode((string) $row->route_patterns, true);
        if (!is_array($pats)) continue;
        $r = $matcher->best(array_filter($pats,'is_string'), $routes[$m]);
        if ($r['matched'] && $r['specificity'] > $best) { $best = $r['specificity']; $winner = $row->module_key; }
    }
    printf("%-18s route %-38s -> %-16s sources: %s\n", $m, $routes[$m], $winner ?? 'general', implode(', ', array_slice($srcs,0,3)));
}

echo "\nLifecycle bindings\n";
echo str_repeat('-', 108)."\n";
foreach ($modules as $m) {
    $cap = $registry->find($m, (int) $institutes[0]);
    printf("%-18s tools=%-3d agent=%-18s workflow=%-24s caps=%s\n", $m,
        count($cap?->mcpTools ?? []), $cap?->agentKey ?? '-', $cap?->workflowKey ?? '-',
        implode(',', array_keys(array_filter($cap?->capabilities ?? []))));
}

echo "\nExamples visible on each module's tabs (what a first-time user lands on)\n";
echo str_repeat('-', 108)."\n";
printf("%-18s %-9s %-9s %-9s %-9s %s\n", 'module', 'policies', 'prompts', 'reports', 'suggest', 'example policy');
foreach ($modules as $m) {
    $ids = DB::table('ai_modules')->where('module_key',$m)
        ->where(fn($q) => $q->where('sub_institute_id', $institutes[0])->orWhereNull('sub_institute_id'))
        ->pluck('id')->all();
    $pol = DB::table('ai_policies as p')
        ->whereExists(fn($e) => $e->from('ai_policy_assignments as a')->whereColumn('a.policy_id','p.id')
            ->where('a.scope_type','module')->whereIn('a.scope_id', $ids))
        ->where(fn($q) => $q->where('p.sub_institute_id', $institutes[0])->orWhereNull('p.sub_institute_id'))
        ->get(['p.id','p.name','p.is_example']);
    $example = $pol->firstWhere('is_example', 1);
    printf("%-18s %-9d %-9d %-9d %-9d %s\n", $m, $pol->count(),
        DB::table('ai_templates')->where('module_key',$m)->where('kind','prompt')->where('status','published')->count(),
        DB::table('ai_templates')->where('module_key',$m)->where('kind','report')->where('status','published')->count(),
        DB::table('ai_suggestions')->where('module_key',$m)->where('status',1)->count(),
        $example ? $example->name : 'NONE');
}

echo "\nRule counts on each example policy (ai_policy_rules)\n";
echo str_repeat('-', 108)."\n";
foreach (DB::table('ai_policies')->where('is_example',1)->whereNull('sub_institute_id')->get(['id','name']) as $p) {
    $on = DB::table('ai_policy_rules')->where('policy_id',$p->id)->where('rule_value',1)->count();
    $off = DB::table('ai_policy_rules')->where('policy_id',$p->id)->where('rule_value',0)->count();
    $asg = DB::table('ai_policy_assignments')->where('policy_id',$p->id)->where('scope_type','module')->value('scope_id');
    $mod = $asg ? DB::table('ai_modules')->where('id',$asg)->value('module_key') : '-';
    printf("%-46s -> module %-18s rules on=%d off=%d\n", substr($p->name,0,44), $mod ?? 'unassigned', $on, $off);
}

echo "\nModels tab — the AI capability each module's generation actually resolves through\n";
echo str_repeat('-', 108)."\n";
echo "A provider is bound to a CAPABILITY (ai_configurations), never to a product module (ai_modules).\n";
echo "Searching overview() for a product module key can only ever miss; these are the rows the tab reads.\n\n";

$overview = app(\App\Domain\AI\Configuration\AiConfigurationResolver::class)->overview((int) $institutes[0]);
$byCapability = [];
foreach ($overview as $row) { $byCapability[$row['module'] ?? ''] = $row; }

// The same mapping the shared Models screen uses: a product capability flag -> the AI
// capability module a credential is bound to.
$lanes = ['conversational' => 'conversational_ai', 'generative' => 'generative_ai', 'agent' => 'agent_reasoning'];

printf("%-18s %-18s %-22s %-22s %-7s %s\n", 'module', 'capability', 'provider', 'model', 'wired', 'source');
foreach ($modules as $m) {
    $caps = json_decode((string) DB::table('ai_modules')->where('module_key', $m)->whereNull('sub_institute_id')->value('capabilities'), true) ?: [];
    $printed = false;

    foreach ($lanes as $flag => $capability) {
        if (empty($caps[$flag])) { continue; }
        $r = $byCapability[$capability] ?? null;
        printf("%-18s %-18s %-22s %-22s %-7s %s\n", $printed ? '' : $m, $capability,
            $r['provider_label'] ?? 'NOT CONFIGURED', $r['model'] ?? 'provider default',
            $r ? var_export($r['wired'] ?? null, true) : '-', $r['source'] ?? '-');
        $printed = true;
    }

    if (! $printed) { printf("%-18s %s\n", $m, 'no model-backed capability enabled'); }
}

echo "\nPer-module credential binding (the one figure that really is per module)\n";
echo str_repeat('-', 108)."\n";
$controller = app(\App\Http\Controllers\AI\AiModuleController::class);
$providerUsage = new ReflectionMethod($controller, 'providerUsage');
$providerUsage->setAccessible(true);
foreach ($modules as $m) {
    $p = $providerUsage->invoke($controller, $m, (int) $institutes[0]);
    printf("%-18s bound=%-7s provider=%-14s daily_limit=%s\n", $m,
        var_export($p['bound'] ?? null, true), $p['provider'] ?? 'shared pool',
        $p['daily_limit'] === null ? 'not set' : $p['daily_limit']);
}
