<?php

namespace App\Services\Access;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Native app screens per role (see config/mobile_app_modules.php for the catalogue).
 *
 * The catalogue is code, because every entry must have a Flutter screen behind it. The grants are
 * data (`mobile_role_modules`), one row per school, role and module, so Roles & Access can change
 * them without an app release.
 */
class MobileAppModules
{
    public static function audienceFor(string $profileName): string
    {
        return in_array(strtolower(trim($profileName)), ['student', 'parent', 'parents'], true) ? 'student' : 'staff';
    }

    /** @return array<string,array{title:string,group:string,audience:string}> */
    public static function catalog(string $audience): array
    {
        $out = [];
        foreach ((array) config('mobile_app_modules.modules') as $key => [$title, $group, $aud]) {
            if ($aud === $audience) {
                $out[$key] = ['title' => $title, 'group' => $group, 'audience' => $aud];
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function grantedKeys(int $tenant, int $profileId): array
    {
        if (! Schema::hasTable('mobile_role_modules')) {
            return [];
        }

        return DB::table('mobile_role_modules')
            ->where('sub_institute_id', $tenant)->where('profile_id', $profileId)
            ->pluck('module_key')->all();
    }

    /**
     * The role's modules grouped for the app, in catalogue order. Keys no longer in the catalogue
     * (or of the other audience) are ignored, so a stale grant can never open a missing screen.
     *
     * @return list<array{title:string,items:list<array{key:string,title:string}>}>
     */
    public static function sectionsFor(int $tenant, int $profileId, string $profileName): array
    {
        $catalog = self::catalog(self::audienceFor($profileName));
        $granted = array_flip(self::grantedKeys($tenant, $profileId));
        $order = (array) config('mobile_app_modules.groups.'.self::audienceFor($profileName));

        $byGroup = [];
        foreach ($catalog as $key => $m) {
            if (isset($granted[$key])) {
                $byGroup[$m['group']][] = ['key' => $key, 'title' => $m['title']];
            }
        }

        $sections = [];
        foreach ($order as $group) {
            if (! empty($byGroup[$group])) {
                $sections[] = ['title' => $group, 'items' => $byGroup[$group]];
            }
        }

        return $sections;
    }

    /** A short fingerprint of what the role can open; the app re-fetches its menu when this changes. */
    public static function versionFor(int $tenant, int $profileId): string
    {
        $keys = self::grantedKeys($tenant, $profileId);
        sort($keys);

        return substr(md5(implode(',', $keys)), 0, 12);
    }

    /**
     * Replace the role's grants with $keys (only catalogue keys of the role's audience are kept).
     *
     * @param  list<string>  $keys
     * @return array{added:list<string>,removed:list<string>}
     */
    public static function replace(int $tenant, int $profileId, string $profileName, array $keys, ?int $actorId): array
    {
        $allowed = self::catalog(self::audienceFor($profileName));
        $want = array_values(array_unique(array_filter($keys, fn ($k) => isset($allowed[$k]))));
        $have = self::grantedKeys($tenant, $profileId);

        $added = array_values(array_diff($want, $have));
        $removed = array_values(array_diff($have, $want));

        DB::transaction(function () use ($tenant, $profileId, $added, $removed, $actorId) {
            if ($removed) {
                DB::table('mobile_role_modules')
                    ->where('sub_institute_id', $tenant)->where('profile_id', $profileId)
                    ->whereIn('module_key', $removed)->delete();
            }
            foreach ($added as $key) {
                DB::table('mobile_role_modules')->insert([
                    'sub_institute_id' => $tenant, 'profile_id' => $profileId, 'module_key' => $key,
                    'updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        return ['added' => $added, 'removed' => $removed];
    }

    /** @return list<string> the catalogue keys a role starts with (matched by lower-case profile name) */
    public static function defaultsFor(string $profileName): array
    {
        $name = strtolower(trim($profileName));
        $map = (array) config('mobile_app_modules.defaults');
        if (in_array($name, ['parent', 'parents'], true)) {
            $name = 'student';
        }
        $keys = $map[$name] ?? [];
        $audience = self::audienceFor($profileName);

        return array_values(array_filter($keys, fn ($k) => isset(self::catalog($audience)[$k])));
    }
}
