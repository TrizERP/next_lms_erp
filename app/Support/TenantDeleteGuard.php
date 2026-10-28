<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;

/**
 * Stops a legacy `destroy($id)` action from deleting another school's record.
 *
 * About 250 controllers delete by id alone and no global tenant scope exists. Instead of
 * editing each one, this finds the table a destroy method works on (from its own source:
 * the first `DB::table('x')` or `Model::find/destroy/where`) and checks that the row
 * named in the URL belongs to the caller's school.
 *
 * It only ever refuses when it is sure: the table was identified, it has a
 * `sub_institute_id` column and an `id` key, the row exists, and its school differs.
 * If anything cannot be worked out the request is left alone, exactly as before.
 */
class TenantDeleteGuard
{
    public static function crossesTenant(Request $request): bool
    {
        $route = $request->route();
        if (!$route || !is_string($route->getActionName()) || !str_contains($route->getActionName(), '@')) {
            return false;
        }

        [$class, $method] = explode('@', $route->getActionName(), 2);
        if ($method !== 'destroy' || !class_exists($class)) {
            return false;
        }

        $params = $route->parameters();
        if (count($params) !== 1) {
            return false;
        }
        $id = reset($params);
        if (!is_scalar($id) || !ctype_digit((string) $id)) {
            return false;
        }

        $tenant = (string) session()->get('sub_institute_id');
        if ($tenant === '' || strtolower((string) session()->get('user_profile_name')) === 'super admin') {
            return false;
        }

        $table = self::tableFor($class, $method);
        if ($table === null) {
            return false;
        }

        $owner = DB::table($table)->where('id', (int) $id)->value('sub_institute_id');
        if ($owner === null || $owner === '' || (string) $owner === '0') {
            return false;
        }

        // A few shared tables store several schools as a comma list.
        $owners = array_map('trim', explode(',', (string) $owner));

        return !in_array($tenant, $owners, true);
    }

    /** The table whose rows a destroy method works on, or null when it cannot be told. */
    public static function tableFor(string $class, string $method): ?string
    {
        return Cache::remember('tenant_delete_table:' . $class, 3600, function () use ($class, $method) {
            try {
                $ref = new ReflectionMethod($class, $method);
                $file = $ref->getFileName();
                if (!$file || !is_readable($file)) {
                    return false;
                }
                $lines = file($file);
                $body = implode('', array_slice($lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1));
                $table = self::deriveTable($body, (string) file_get_contents($file), $ref->getDeclaringClass()->getNamespaceName());
            } catch (\Throwable) {
                return false;
            }

            if ($table === null || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                return false;
            }
            if (!Schema::hasColumn($table, 'sub_institute_id') || !Schema::hasColumn($table, 'id')) {
                return false;
            }

            return $table;
        }) ?: null;
    }

    public static function deriveTable(string $body, string $fileSource, string $namespace): ?string
    {
        $found = [];

        if (preg_match('/DB::table\(\s*[\'"]([A-Za-z0-9_]+)[\'"]\s*\)/', $body, $m, PREG_OFFSET_CAPTURE)) {
            $found[$m[0][1]] = $m[1][0];
        }

        // Model class names in this codebase are often lower-case (bankmasterModel::where).
        if (preg_match_all('/\b([A-Za-z_][A-Za-z0-9_]*)::(?:find|findOrFail|destroy|where)\(/', $body, $all, PREG_OFFSET_CAPTURE, 0)) {
            foreach ($all[1] as $i => [$short, $offset]) {
                if ($short === 'DB') {
                    continue;
                }
                $fqcn = null;
                if (preg_match('/^use\s+([A-Za-z0-9_\\\\]+\\\\' . preg_quote($short, '/') . ')\s*;/m', $fileSource, $u)) {
                    $fqcn = $u[1];
                } elseif (class_exists($namespace . '\\' . $short)) {
                    $fqcn = $namespace . '\\' . $short;
                }
                if ($fqcn && class_exists($fqcn) && is_subclass_of($fqcn, Model::class)) {
                    $found[$all[0][$i][1]] = (new $fqcn)->getTable();
                    break;
                }
            }
        }

        if ($found === []) {
            return null;
        }
        ksort($found);

        return reset($found);
    }
}
