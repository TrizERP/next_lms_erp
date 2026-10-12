<?php

/**
 * Builds a THROWAWAY SQLite database for browser E2E of the Alumni module, so the screens can be exercised
 * (including new migrations) without touching the shared MySQL database.
 *
 *   php tests/Support/build_e2e_sqlite.php <path-to-new-file.sqlite>
 *
 * Then start a backend that uses it - the environment variables below take precedence over .env:
 *
 *   DB_CONNECTION=sqlite DB_DATABASE=<that file> SESSION_DRIVER=array CACHE_STORE=array CACHE_DRIVER=array \
 *   QUEUE_CONNECTION=sync php artisan serve --port=8001
 *
 * SAFETY. The connection is forced to sqlite BEFORE the framework boots and the script refuses to continue unless
 * the live connection really is that file, so it cannot create or fill tables in the shared database. The file is
 * deleted first if it exists. It contains only what the alumni screens and the JWT session hydrator read: one
 * institute (id 1), an Admin, a Peon (not allowed to manage alumni), a Teacher, students (one with a leaving
 * certificate), the current academic year, every create_alumni_* migration, and the labelled sample alumni.
 */
$file = $argv[1] ?? null;
if (! $file) {
    fwrite(STDERR, "usage: php tests/Support/build_e2e_sqlite.php <file.sqlite>\n");
    exit(1);
}
$file = str_replace('\\', '/', $file);
if (is_file($file) && ! @unlink($file)) {
    // On Windows a running dev server can hold the file open, so it cannot be deleted: empty it instead.
    $old = new PDO('sqlite:' . $file);
    foreach ($old->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $old->exec('DROP TABLE "' . str_replace('"', '""', $table) . '"');
    }
    $old = null;
}
touch($file);

foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $file, 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'CACHE_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $k => $v) {
    putenv("$k=$v");
    $_ENV[$k] = $_SERVER[$k] = $v;
}

// routes/custom_module.php reads this table while the routes load, i.e. during boot, before any migration can run.
$pdo = new PDO('sqlite:' . $file);
$pdo->exec('CREATE TABLE custom_module_tables (id INTEGER PRIMARY KEY, module_name TEXT, access_link TEXT, table_name TEXT)');
$pdo = null;

chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (DB::connection()->getDriverName() !== 'sqlite' || realpath(DB::connection()->getDatabaseName()) !== realpath($file)) {
    fwrite(STDERR, "REFUSING: the active connection is not the scratch sqlite file.\n");
    exit(2);
}

Schema::create('school_setup', function ($t) {
    $t->integer('Id')->primary();
    $t->string('SchoolName')->nullable();
    $t->string('ShortCode')->nullable();
    $t->string('institute_type')->nullable();
    $t->string('is_Lms')->nullable();
});
Schema::create('tbluserprofilemaster', function ($t) {
    $t->integer('id')->primary();
    $t->string('name');
    $t->integer('status')->default(1);
    $t->integer('sub_institute_id');
    $t->integer('parent_id')->nullable();
});
Schema::create('tbluser', function ($t) {
    $t->integer('id')->primary();
    $t->string('user_name')->nullable();
    $t->string('first_name')->nullable();
    $t->string('last_name')->nullable();
    $t->string('email')->nullable();
    $t->integer('user_profile_id')->nullable();
    $t->integer('sub_institute_id')->nullable();
    $t->integer('is_admin')->nullable();
    $t->integer('client_id')->nullable();
});
Schema::create('academic_year', function ($t) {
    $t->increments('id');
    $t->integer('term_id')->default(1);
    $t->string('syear');
    $t->integer('sub_institute_id');
    $t->string('title')->nullable();
    $t->integer('sort_order')->default(1);
    $t->date('start_date');
    $t->date('end_date');
});
Schema::create('class_teacher', function ($t) {
    $t->increments('id');
    $t->integer('teacher_id');
    $t->integer('sub_institute_id');
    $t->string('syear')->nullable();
    $t->integer('grade_id')->nullable();
    $t->integer('standard_id')->nullable();
    $t->integer('division_id')->nullable();
});
Schema::create('tblstudent', function ($t) {
    $t->integer('id')->primary();
    $t->integer('sub_institute_id');
    $t->string('enrollment_no')->nullable();
    $t->string('admission_year')->nullable();
    $t->string('first_name')->nullable();
    $t->string('middle_name')->nullable();
    $t->string('last_name')->nullable();
    $t->string('gender')->nullable();
    $t->date('dob')->nullable();
    $t->string('email')->nullable();
    $t->string('mobile')->nullable();
    $t->string('student_mobile')->nullable();
    $t->string('address')->nullable();
    $t->string('city')->nullable();
    $t->string('state')->nullable();
    $t->string('pincode')->nullable();
    $t->string('bloodgroup')->nullable();
    $t->integer('student_inactive')->nullable();
    $t->date('inactive_date')->nullable();
});
Schema::create('tblstudent_tc_details', function ($t) {
    $t->increments('id');
    $t->integer('sub_institute_id');
    $t->integer('student_id');
});
Schema::create('tblstudent_enrollment', function ($t) {
    $t->increments('id');
    $t->integer('sub_institute_id');
    $t->integer('student_id');
    $t->string('syear');
    $t->integer('standard_id')->nullable();
    $t->integer('section_id')->nullable();
    $t->date('start_date')->nullable();
    $t->date('end_date')->nullable();
    $t->string('enrollment_code')->nullable();
});
Schema::create('standard', function ($t) {
    $t->integer('id')->primary();
    $t->string('name');
});
Schema::create('division', function ($t) {
    $t->integer('id')->primary();
    $t->string('name');
});

// Every alumni table, from the real migrations (a failure here is a real migration bug).
$migrations = glob(base_path('database/migrations/*create_alumni_*.php'));
sort($migrations);
foreach ($migrations as $m) {
    (require $m)->up();
}
// Platform tables the real NotificationSender, integration check and audit trail read and write.
foreach (['2026_09_11_100000_create_platform_notification_channels_table', '2026_09_11_100100_create_platform_notification_preferences_table',
    '2026_10_07_100000_create_platform_audit_log_table', '2026_10_07_100100_create_platform_integration_configs_table', '2026_10_07_110000_create_platform_engine_tables'] as $name) {
    $platform = base_path("database/migrations/{$name}.php");
    if (is_file($platform)) {
        (require $platform)->up();
    }
}

DB::table('school_setup')->insert(['Id' => 1, 'SchoolName' => 'E2E Test School', 'ShortCode' => 'E2E']);
DB::table('tbluserprofilemaster')->insert([
    ['id' => 1, 'name' => 'Admin', 'sub_institute_id' => 1], ['id' => 2, 'name' => 'Peon', 'sub_institute_id' => 1],
    ['id' => 3, 'name' => 'Student', 'sub_institute_id' => 1], ['id' => 4, 'name' => 'Accountant', 'sub_institute_id' => 1],
]);
DB::table('tbluser')->insert([
    ['id' => 7013, 'user_name' => 'e2eadmin', 'first_name' => 'E2E', 'last_name' => 'Admin', 'email' => 'admin@e2e.invalid', 'user_profile_id' => 1, 'sub_institute_id' => 1, 'client_id' => 1],
    ['id' => 49973, 'user_name' => 'e2epeon', 'first_name' => 'E2E', 'last_name' => 'Peon', 'email' => 'peon@e2e.invalid', 'user_profile_id' => 2, 'sub_institute_id' => 1, 'client_id' => 1],
    ['id' => 49974, 'user_name' => 'e2eacct', 'first_name' => 'E2E', 'last_name' => 'Accountant', 'email' => 'acct@e2e.invalid', 'user_profile_id' => 4, 'sub_institute_id' => 1, 'client_id' => 1],
]);
$year = (int) date('Y');
DB::table('academic_year')->insert(['term_id' => 1, 'syear' => (string) $year, 'sub_institute_id' => 1, 'title' => 'Current', 'start_date' => ($year - 1) . '-01-01', 'end_date' => ($year + 1) . '-12-31']);
DB::table('standard')->insert(['id' => 10, 'name' => '12']);
DB::table('division')->insert(['id' => 1, 'name' => 'A']);
DB::table('tblstudent')->insert([
    ['id' => 501, 'sub_institute_id' => 1, 'enrollment_no' => 'EN501', 'first_name' => 'Asha', 'last_name' => 'Student', 'email' => 'asha.s@e2e.invalid', 'mobile' => '0000000501', 'admission_year' => '2012', 'student_inactive' => 0, 'inactive_date' => null],
    ['id' => 502, 'sub_institute_id' => 1, 'enrollment_no' => 'EN502', 'first_name' => 'Ravi', 'last_name' => 'Student', 'email' => 'ravi.s@e2e.invalid', 'mobile' => '0000000502', 'admission_year' => '2012', 'student_inactive' => 0, 'inactive_date' => null],
    ['id' => 503, 'sub_institute_id' => 1, 'enrollment_no' => 'EN503', 'first_name' => 'Leaver', 'last_name' => 'Student', 'email' => 'leaver@e2e.invalid', 'mobile' => '0000000503', 'admission_year' => '2008', 'student_inactive' => 1, 'inactive_date' => ($year - 1) . '-03-31'],
]);
DB::table('tblstudent_tc_details')->insert(['sub_institute_id' => 1, 'student_id' => 503]);
DB::table('tblstudent_enrollment')->insert(['sub_institute_id' => 1, 'student_id' => 503, 'syear' => (string) ($year - 1), 'standard_id' => 10, 'section_id' => 1, 'start_date' => ($year - 1) . '-04-01']);

Illuminate\Support\Facades\Artisan::call('alumni:sample-data', ['tenant' => 1]);
echo trim(Illuminate\Support\Facades\Artisan::output()) . "\n";
echo 'Built ' . $file . ' with ' . count($migrations) . " alumni migration(s); alumni rows: " . DB::table('alumni')->count() . "\n";
