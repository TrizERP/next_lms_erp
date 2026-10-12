<?php

/**
 * Before/after permission report for the Alumni menu rights of the platform-level SUPER ADMIN profiles.
 *
 * READ-ONLY: it only SELECTs. It reports on whichever database the application is currently pointed at and prints that
 * database's host and name first, so a report can never be mistaken for another environment's.
 *
 *   php database/manual/alumni_super_admin_rights_report.php            coverage report (markdown)
 *   php database/manual/alumni_super_admin_rights_report.php --plan      also the exact rows migration
 *                                                                        2026_10_14_100100_grant_super_admin_donations_audit_menu would insert
 *
 * Nothing here prints a credential or a personal record: only profile ids, menu links and rights flags.
 */
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$plan = in_array('--plan', $argv, true);
$module = DB::table('tblmenumaster')->where('name', 'Alumni Management')->where('level', 2)->first();
if (! $module) {
    fwrite(STDERR, "The Alumni Management module row was not found in this database.\n");
    exit(1);
}
$screens = DB::table('tblmenumaster')->where('parent_menu_id', $module->id)->orderBy('sort_order')->get(['id', 'link']);
$profiles = DB::table('tbluserprofilemaster')->where('status', 1)->whereRaw("UPPER(TRIM(name)) = 'SUPER ADMIN'")->orderBy('id')->get(['id', 'sub_institute_id']);
$grants = ['alumni/donations' => [1, 1, 1, 0], 'alumni/audit' => [1, 0, 0, 0]];

echo '# Alumni menu rights for SUPER ADMIN profiles', "\n\n";
echo 'Database: `', config('database.connections.mysql.host'), '` / `', DB::connection()->getDatabaseName(), "`  (read-only report)\n\n";
echo 'Alumni Management screens: ', $screens->count(), '; active SUPER ADMIN profiles: ', $profiles->count(), "\n\n";
echo "| Profile id | Institute id | Module row | Screens held | Missing |\n|---|---|---|---|---|\n";
$totalMissing = 0;
foreach ($profiles as $p) {
    $held = DB::table('tblgroupwise_rights')->where('profile_id', $p->id)->where('sub_institute_id', $p->sub_institute_id)->whereIn('menu_id', $screens->pluck('id'))->pluck('menu_id')->all();
    $moduleRow = DB::table('tblgroupwise_rights')->where('profile_id', $p->id)->where('sub_institute_id', $p->sub_institute_id)->where('menu_id', $module->id)->exists();
    $missing = $screens->reject(fn ($s) => in_array($s->id, $held, true))->pluck('link')->map(fn ($l) => str_replace('alumni/', '', $l));
    $totalMissing += $missing->count();
    echo "| {$p->id} | {$p->sub_institute_id} | ", $moduleRow ? 'yes' : 'NO', ' | ', count($held), '/', $screens->count(), ' | ', $missing->implode(', ') ?: '-', " |\n";
}
echo "\nTotal missing rights rows across these profiles: **{$totalMissing}**\n";
echo 'Rights table: ', DB::table('tblgroupwise_rights')->count(), ' rows, highest id ', DB::table('tblgroupwise_rights')->max('id'), "\n";

if ($plan) {
    echo "\n## Rows the migration would insert\n\n| Profile id | menu_id | link | sub_institute_id | view | add | edit | delete |\n|---|---|---|---|---|---|---|---|\n";
    $n = 0;
    foreach ($grants as $link => [$v, $a, $e, $d]) {
        $menuId = DB::table('tblmenumaster')->where('link', $link)->value('id');
        foreach ($profiles as $p) {
            $exists = $menuId && DB::table('tblgroupwise_rights')->where('menu_id', $menuId)->where('profile_id', $p->id)->where('sub_institute_id', $p->sub_institute_id)->exists();
            if ($menuId && ! $exists) {
                echo "| {$p->id} | {$menuId} | {$link} | {$p->sub_institute_id} | {$v} | {$a} | {$e} | {$d} |\n";
                $n++;
            }
        }
    }
    echo "\nRows to insert: **{$n}**. No existing row is changed or removed.\n";
}
