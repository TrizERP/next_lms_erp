<?php

namespace Tests\Support;

use App\Services\Alumni\AlumniService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Shared setup for the alumni feature tests.
 *
 * Everything runs on IN-MEMORY SQLITE, forced here: phpunit.xml does not force it, so without this a DB test would hit the
 * shared database. bootAlumni() refuses to continue unless the active connection really is sqlite. It builds the alumni
 * tables from the REAL migrations (so a migration bug fails a test), plus the platform notification tables the real
 * NotificationSender writes to.
 *
 * act() builds a request the way the `api.session` middleware leaves it - a session with the JWT's tenant and user, AND
 * type=API merged into the input - so a controller that reads a field called `type` fails here instead of in the browser.
 */
trait AlumniTestHelpers
{
    protected AlumniService $svc;

    /** The user id in the JWT session built by act(); tests that need two people (a recorder and a verifier) change it. */
    protected int $actingUser = 55;

    protected function bootAlumni(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a real database');

        foreach (['2026_10_10_100000_create_alumni_core_tables', '2026_10_11_100000_create_alumni_engagement_tables', '2026_10_12_100000_create_alumni_communication_tables',
            '2026_10_12_100100_create_alumni_events_tables', '2026_10_12_100200_create_alumni_survey_tables', '2026_10_13_100000_create_alumni_donation_tables', '2026_10_13_100200_create_alumni_import_tables', '2026_10_14_100000_create_alumni_auth_tables', '2026_09_11_100000_create_platform_notification_channels_table',
            '2026_09_11_100100_create_platform_notification_preferences_table', '2026_10_07_100100_create_platform_integration_configs_table',
            '2026_10_07_110000_create_platform_engine_tables', '2026_10_07_100000_create_platform_audit_log_table'] as $m) {
            $file = base_path("database/migrations/{$m}.php");
            if (is_file($file)) {
                (require $file)->up();
            }
        }
        Schema::create('school_setup', function ($t) {
            $t->integer('Id')->primary();
            $t->string('SchoolName')->nullable();
            $t->string('ShortCode')->nullable();
        });
        DB::table('school_setup')->insert([['Id' => 7, 'SchoolName' => 'Test School', 'ShortCode' => 'TST'], ['Id' => 8, 'SchoolName' => 'Other School', 'ShortCode' => 'OTH']]);
        Storage::fake('local');
        $this->svc = app(AlumniService::class);
    }

    protected function act(string $controller, string $method, array $input = [], int $tenant = 7, string $profile = 'Admin', array $args = [], array $files = [])
    {
        $request = Request::create('/x', 'POST', $input, [], $files);
        $request->merge(['type' => 'API']);   // what the api.session middleware does to every request
        $store = app('session.store');
        $store->flush();
        $request->setLaravelSession($store);
        $store->put('sub_institute_id', $tenant);
        $store->put('user_id', $this->actingUser);
        $store->put('user_profile_name', $profile);
        $store->put('is_student', false);

        return app($controller)->{$method}($request, ...$args);
    }

    /** @return array{0:int, 1:array} */
    protected function go(string $controller, string $method, array $input = [], int $tenant = 7, string $profile = 'Admin', array $args = []): array
    {
        $r = $this->act($controller, $method, $input, $tenant, $profile, $args);

        return [$r->getStatusCode(), $r->getData(true)];
    }

    /** An alumnus in the given status, with contact details and (optionally) consent. */
    protected function alum(string $first, array $over = [], int $tenant = 7, string $status = 'active'): object
    {
        static $n = 0;
        $n++;
        $row = $this->svc->create($tenant, array_merge([
            'first_name' => $first, 'last_name' => 'Alum', 'dob' => '2000-02-' . str_pad((string) (($n % 27) + 1), 2, '0', STR_PAD_LEFT),
            'email' => strtolower($first) . $n . '@example.org', 'mobile' => '70000' . str_pad((string) $n, 5, '0', STR_PAD_LEFT), 'passing_year' => 2018, 'batch' => '2017-18',
            'country' => 'India', 'city' => 'Pune',
        ], $over), 1);
        if ($status !== 'registered') {
            $this->svc->changeStatus($tenant, (int) $row->id, $status, 1);
        }

        return $this->svc->find($tenant, (int) $row->id);
    }

    protected function consent(object $alumnus, array $channels = ['email', 'sms', 'whatsapp', 'push']): void
    {
        $cols = ['email' => 'consent_email', 'sms' => 'consent_sms', 'whatsapp' => 'consent_whatsapp', 'push' => 'consent_push'];
        DB::table('alumni')->where('id', $alumnus->id)->update(array_map(fn () => 1, array_flip(array_map(fn ($c) => $cols[$c], $channels))));
    }

    protected function log(array $where = []): \Illuminate\Support\Collection
    {
        $q = DB::table('alumni_message_log');
        foreach ($where as $k => $v) {
            $q->where($k, $v);
        }

        return $q->orderBy('id')->get();
    }
}
