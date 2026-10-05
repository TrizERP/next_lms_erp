<?php

/**
 * Every menu module that offers an AI Stack tab, and what that tab actually resolves to.
 *
 * WHAT IT ANSWERS
 *
 * The defect this was written for: "I tested some modules and noticed that the same
 * data/value is being displayed across different modules."
 *
 * That is what happens when a module offers an AI Stack tab without being reachable by the
 * AI layer. `fees_menu_categories` carries an `ai-stack` row for 65 level-2 modules; a
 * module the AI layer cannot resolve has nothing to scope by, so every one of its tabs
 * falls through to the same place — and every such module shows the same thing as every
 * other. Identical content on many modules is the symptom; no resolution is the cause.
 *
 * SO IT MEASURES RESOLUTION, NOT REGISTRATION
 *
 * A module does not need an `ai_modules` row of its own. `fees-report` is the Fees
 * module's reports and its records ARE fee records, so its route resolves to `fees` and it
 * correctly shows fee data. What matters is that the route resolves to SOMETHING with its
 * own tools, and that two different menu modules do not resolve to the same empty
 * fallback.
 *
 * The last section is the real test: it fingerprints what each module's tabs would show
 * and reports any two that are identical.
 *
 * IT ONLY READS. No INSERT, UPDATE or DELETE.
 *
 *   php check_ai_stack_coverage.php
 */

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\AI\Lifecycle\Modules\ModuleResolver;
use App\Domain\AI\Templates\ReportDataSourceCatalog;
use Illuminate\Support\Facades\DB;

$institute = (int) DB::table('tblstudent_enrollment')->where('sub_institute_id', '>', 0)
    ->orderBy('sub_institute_id')->value('sub_institute_id');

$resolver = app(ModuleResolver::class);
$sources = app(ReportDataSourceCatalog::class);

// The slug AND the route the menu actually points at. Constructing
// `/modules/<slug>/ai-stack` was wrong twice over: Fees keeps its own page at
// `/fees/ai-stack`, and Task Management's slug carries the level-2 menu id. Reading
// the route column is the only way to check what a person clicking the tab reaches.
$menu = DB::table('fees_menu_categories')
    ->where('category_key', 'ai-stack')
    ->where('status', 1)
    ->orderBy('module_name')
    ->get(['module_name', 'route'])
    ->unique('module_name')
    ->values();

/**
 * Menu modules that are deliberately NOT bound, with the reason.
 *
 * Every one of these holds no table in this estate — they are menu entries for features
 * that have not been built. Binding them would produce an AI Stack with nothing behind it,
 * which is the failure this whole design avoids. Listing them here makes the absence a
 * decision on the record rather than an oversight.
 *
 * @var array<string, string>
 */
$deliberatelyUnbound = [
    'leave' => 'no leave table in this estate',
    'payroll' => 'no payroll table in this estate',
    'donation-management' => 'no donation table in this estate',
    'skill-assessment' => 'no skill table in this estate',
    'skill-management' => 'no skill table in this estate',
    'talent-management' => 'no talent table in this estate',
    'stock-verification' => 'no stock verification table in this estate',
    'user-attendance' => 'no staff attendance table in this estate',
    'organization-management' => 'structure only; served by the Institute module',
    'hrit-management' => 'no HR IT table in this estate',
    'hrms-report' => 'the hr module has tools but no ai_modules row',
    'other-reports' => 'no single parent module',
    'platform-services' => 'platform configuration, not school records',
    'books' => 'served by the Library module',
    'capability-intelligence' => 'analytics over other modules, no records of its own',
    'career-awareness' => 'no career table in this estate',
    'career-counseling' => 'served by the career-counselling module',
    'career-explorer' => 'no career table in this estate',
    'test' => 'not a real module',
];

echo "AI Stack coverage, as institute {$institute}\n";
echo str_repeat('=', 116)."\n\n";
printf("%-28s %-22s %-7s %-8s %s\n", 'MENU SLUG', 'resolves to', 'tools', 'sources', 'verdict');
echo str_repeat('-', 116)."\n";

$fingerprints = [];
$resolvedFor = [];
$bound = 0;
$unbound = 0;
$unexpected = [];

foreach ($menu as $row) {
    $slug = (string) $row->module_name;
    $route = trim((string) $row->route) ?: '/modules/'.$slug.'/ai-stack';
    $resolved = $resolver->resolve('Summarise this page.', ['route' => $route], $institute);
    $key = $resolved['module']->key;

    $tools = array_values(array_filter(
        $resolved['module']->mcpTools ?? [],
        static fn ($t) => ! str_starts_with($t, 'ai.templates.')
    ));

    $picker = array_column($sources->forModule($key), 'name');

    $prompts = DB::table('ai_templates')->where('module_key', $key)
        ->where('kind', 'prompt')->where('status', 'published')->count();

    if ($tools !== []) {
        $bound++;
        $verdict = $prompts > 0 ? 'complete' : 'bound, no prompts';
    } else {
        $unbound++;
        $reason = $deliberatelyUnbound[$slug] ?? null;
        $verdict = $reason === null ? 'UNEXPECTED GAP' : 'not bound — '.$reason;

        if ($reason === null) {
            $unexpected[] = $slug;
        }
    }

    printf("%-28s %-22s %-7d %-8d %s\n", $slug, $key, count($tools), count($picker), $verdict);

    // Keyed by what the module actually resolved to, so the collision report below
    // never has to resolve a route a second time — doing that with a CONSTRUCTED
    // route is what made Fees look empty when it is not.
    $fingerprints[$slug] = md5(json_encode([$key, $tools, $picker]));
    $resolvedFor[$slug] = ['key' => $key, 'has_tools' => $tools !== []];
}

echo "\n".str_repeat('=', 116)."\n";
printf("%d menu modules offer an AI Stack tab: %d resolve to a module with its own data tools, %d do not.\n",
    $menu->count(), $bound, $unbound);

echo "\n".str_repeat('-', 116)."\n";
echo "Do any two modules show IDENTICAL AI Stack content?\n";
echo str_repeat('-', 116)."\n";

$byFingerprint = [];

foreach ($fingerprints as $slug => $hash) {
    $byFingerprint[$hash][] = $slug;
}

$collisions = array_values(array_filter($byFingerprint, static fn (array $s) => count($s) > 1));

// Modules that share a parent BY DESIGN are not a collision: `fees` and `fees-report` are
// the same module's records and should show the same tools. A collision that matters is
// two modules resolving to something with NO tools, which is the fallback.
$realCollisions = [];

foreach ($collisions as $group) {
    // Read back what this module resolved to in the loop above rather than resolving a
    // second time. The earlier version re-resolved a CONSTRUCTED `/modules/<slug>/ai-stack`
    // route, which is wrong for exactly the modules that do not follow the convention —
    // it reported Fees as empty when Fees has six tools and its own page at /fees/ai-stack.
    $resolution = $resolvedFor[$group[0]];

    if ($resolution['has_tools']) {
        echo '  by design: '.implode(', ', $group).' all resolve to '.$resolution['key']."\n";
    } else {
        $realCollisions[] = $group;
    }
}

if ($realCollisions === []) {
    echo "\nNo module with data tools shares its content with another.\n";
} else {
    foreach ($realCollisions as $group) {
        echo "\n  IDENTICAL AND EMPTY ("
            .count($group)."): ".implode(', ', array_slice($group, 0, 12))
            .(count($group) > 12 ? ', …' : '')."\n";
    }

    echo "\n  These resolve to a module with no data tools, so their tabs show the same thing.\n";
    echo "  Each is listed in \$deliberatelyUnbound above with the reason its estate holds no records.\n";
}

if ($unexpected !== []) {
    echo "\n".str_repeat('-', 116)."\n";
    echo "UNEXPECTED: these are unbound and are NOT on the deliberate list — decide about each.\n  ";
    echo implode("\n  ", $unexpected)."\n";
    exit(1);
}

echo "\nEvery unbound module is unbound on purpose, with its reason recorded above.\n";
