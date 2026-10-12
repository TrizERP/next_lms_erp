<?php
/**
 * Builds the STAGING database (local MariaDB on 127.0.0.1:3307) with a production-equivalent STRUCTURE.
 *
 * READ-ONLY on production: it runs SHOW TABLES / SHOW CREATE TABLE and, for the Super Admin rehearsal only, SELECTs the
 * Alumni Management menu rows, the SUPER ADMIN profile rows and those profiles' rights rows for those menus (configuration,
 * not personal data), plus the names in the `migrations` table. It writes ONLY to the staging server and refuses to
 * run if that is not 127.0.0.1:3307. It never prints a credential or a data row, only counts.
 */
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$prodHost = (string) config('database.connections.mysql.host');
if (in_array($prodHost, ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "REFUSING: the default connection is already local; this script expects to READ production through it.\n");
    exit(2);
}

$staging = new PDO('mysql:host=127.0.0.1;port=3307;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($staging->query('SELECT @@port')->fetchColumn() != 3307) {
    fwrite(STDERR, "REFUSING: not the staging server.\n");
    exit(2);
}
$staging->exec('DROP DATABASE IF EXISTS staging_erp');
$staging->exec('CREATE DATABASE staging_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$staging->exec('USE staging_erp');
$staging->exec('SET FOREIGN_KEY_CHECKS=0');
$staging->exec("SET sql_mode='NO_ENGINE_SUBSTITUTION'");

$tables = [];
foreach (DB::select("SHOW TABLES LIKE 'alumni%'") as $r) {
    $tables[] = array_values((array) $r)[0];
}
foreach (DB::select("SHOW TABLES LIKE 'platform\\_%'") as $r) {
    $tables[] = array_values((array) $r)[0];
}
$tables = array_merge($tables, ['school_setup', 'tbluser', 'tbluserprofilemaster', 'tblmenumaster', 'tblgroupwise_rights', 'tblindividual_rights', 'academic_year', 'tblstudent',
    'tblstudent_enrollment', 'standard', 'division', 'migrations', 'custom_module_tables', 'class_teacher', 'jobs', 'failed_jobs']);
$made = 0;
$skipped = [];
foreach (array_unique($tables) as $t) {
    try {
        $sql = DB::selectOne("SHOW CREATE TABLE `$t`")->{'Create Table'} ?? null;
    } catch (Throwable $e) {
        $skipped[] = $t;

        continue;
    }
    $sql = preg_replace('/ AUTO_INCREMENT=\d+/', '', $sql);
    $staging->exec($sql);
    $made++;
}
echo "structure copied: {$made} tables", $skipped ? ' (absent in production: ' . implode(',', $skipped) . ')' : '', "\n";

// the names of the migrations already applied, so `migrate --path` behaves exactly as in production
$names = DB::table('migrations')->pluck('migration')->all();
$batch = (int) DB::table('migrations')->max('batch');
$ins = $staging->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
foreach ($names as $n) {
    $ins->execute([$n, $batch]);
}
echo 'migration names copied: ', count($names), "\n";

// configuration rows needed to rehearse the Super Admin menu grant: the Alumni Management menu, SUPER ADMIN profiles, their rights on those menus
$module = DB::table('tblmenumaster')->where('name', 'Alumni Management')->where('level', 2)->first();
$menus = DB::table('tblmenumaster')->where('id', $module->id)->orWhere('parent_menu_id', $module->id)->get();
$profiles = DB::table('tbluserprofilemaster')->whereRaw("UPPER(TRIM(name)) IN ('SUPER ADMIN','ADMIN','ACCOUNTANT')")->limit(400)->get();
$rights = DB::table('tblgroupwise_rights')->whereIn('menu_id', $menus->pluck('id'))->whereIn('profile_id', $profiles->pluck('id'))->get();
$copy = function (string $table, $rows) use ($staging) {
    $n = 0;
    foreach ($rows as $row) {
        $row = (array) $row;
        $cols = '`' . implode('`,`', array_keys($row)) . '`';
        $st = $staging->prepare("INSERT INTO `$table` ($cols) VALUES (" . implode(',', array_fill(0, count($row), '?')) . ')');
        $st->execute(array_values($row));
        $n++;
    }

    return $n;
};
echo 'menu rows copied: ', $copy('tblmenumaster', $menus), ', profile rows copied: ', $copy('tbluserprofilemaster', $profiles), ', rights rows copied: ', $copy('tblgroupwise_rights', $rights), "\n";
echo "staging ready\n";
