<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Throw-away logins for checking each role's dashboard and menu.
 *
 *   php artisan roles:test-users 1 --create    one "TEST" user per role, random passwords (printed once)
 *   php artisan roles:test-users 1 --list      show which exist
 *   php artisan roles:test-users 1 --delete    remove exactly the users this command made
 *
 * Every user it makes has a user_name starting with "dashtest_" and the first name "TEST", and the
 * delete only matches that prefix inside the given tenant, so a real account can never be removed.
 */
class RoleTestUsers extends Command
{
    protected $signature = 'roles:test-users {tenant : sub_institute_id} {--create} {--list} {--delete}';

    protected $description = 'Create, list or delete one throw-away TEST login per dashboard role';

    private const PREFIX = 'dashtest_';

    public function handle(): int
    {
        $tenant = (int) $this->argument('tenant');

        if ($this->option('delete')) {
            $n = DB::table('tbluser')->where('sub_institute_id', $tenant)->where('user_name', 'like', self::PREFIX.'%')->where('first_name', 'TEST')->delete();
            $this->info("Deleted {$n} test user(s).");

            return self::SUCCESS;
        }

        if ($this->option('create')) {
            return $this->create($tenant);
        }

        foreach (DB::table('tbluser')->where('sub_institute_id', $tenant)->where('user_name', 'like', self::PREFIX.'%')->get(['id', 'email', 'user_profile_id']) as $u) {
            $this->line("#{$u->id}  {$u->email}  profile {$u->user_profile_id}");
        }

        return self::SUCCESS;
    }

    private function create(int $tenant): int
    {
        $client = (int) DB::table('tbluser')->where('sub_institute_id', $tenant)->where('user_profile_id', 1)->value('client_id');
        $profiles = DB::table('tbluserprofilemaster')->where('sub_institute_id', $tenant)->get(['id', 'name']);
        $rows = [];

        foreach (config('role_dashboards.roles') as $key => $role) {
            if (in_array($key, ['admin', 'teacher', 'student'], true)) {
                continue;
            }
            $profile = $profiles->first(fn ($p) => in_array(strtolower(trim($p->name)), $role['profiles'], true));
            if (! $profile) {
                continue;
            }

            $email = "test.{$key}@example.com";
            if (DB::table('tbluser')->where('sub_institute_id', $tenant)->where('email', $email)->exists()) {
                $this->warn("{$email} already exists, skipped");

                continue;
            }

            $password = Str::password(12, true, true, false, false);
            DB::table('tbluser')->insert([
                'user_name' => self::PREFIX.$key,
                'password' => $password,
                'first_name' => 'TEST',
                'last_name' => $role['label'],
                'email' => $email,
                'mobile' => '0000000000',
                'user_profile_id' => $profile->id,
                'join_year' => date('Y'),
                'sub_institute_id' => $tenant,
                'client_id' => $client,
                'is_admin' => 0,
                'status' => 1,
            ]);
            $rows[] = [$role['label'], $profile->name, $email, $password];
        }

        $this->table(['Role', 'Profile', 'Email (login)', 'Password'], $rows);
        $this->line('Delete them afterwards with: php artisan roles:test-users '.$tenant.' --delete');

        return self::SUCCESS;
    }
}
