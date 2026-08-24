<?php

namespace App\Http\Controllers\api\TalentManagement\Competency\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Ported from G2G's `App\Http\Controllers\Api\Competency\Concerns\ManagesCompetencySettings`
 * with no logic changes - it is pure `s_competency_settings` read/write and
 * carries no identity resolution of its own.
 *
 * Shared read/write for s_competency_settings, the module's key/value settings
 * store. Used by the Framework Studio's "Weighting Configuration" panel and the
 * Assessment Workspace's "View Configuration" panel.
 *
 * Values are stored as text and cast back on read against a defaults map, so a
 * setting that has never been saved returns its default rather than null and a
 * caller never has to guard for a missing row.
 */
trait ManagesCompetencySettings
{
    private const SETTINGS_TABLE = 's_competency_settings';

    /**
     * Read one scope's settings, merged over $defaults.
     *
     * @param  array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    protected function competencySettings(int $sid, string $scope, array $defaults, ?int $scopeId = null): array
    {
        $query = DB::table(self::SETTINGS_TABLE)
            ->where('sub_institute_id', $sid)
            ->where('scope', $scope);

        $scopeId === null ? $query->whereNull('scope_id') : $query->where('scope_id', $scopeId);

        $stored = $query->pluck('value', 'key');

        $settings = [];
        foreach ($defaults as $key => $default) {
            $settings[$key] = $stored->has($key)
                ? $this->castSetting($stored[$key], $default)
                : $default;
        }

        return $settings;
    }

    /**
     * Upsert one scope's settings. Only keys present in $defaults are written,
     * so an unexpected field in the request body cannot create a stray setting.
     *
     * @param  array<string, mixed> $values   incoming (already validated)
     * @param  array<string, mixed> $defaults the allowed key set + types
     * @return array<string, mixed> the settings as they now stand
     */
    protected function saveCompetencySettings(
        int $sid,
        string $scope,
        array $values,
        array $defaults,
        ?int $userId = null,
        ?int $scopeId = null
    ): array {
        foreach ($defaults as $key => $default) {
            if (!array_key_exists($key, $values)) {
                continue;
            }

            $value = $values[$key];

            if (is_bool($default)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            } elseif (is_array($default)) {
                $value = json_encode(array_values((array) $value));
            } else {
                $value = (string) $value;
            }

            DB::table(self::SETTINGS_TABLE)->updateOrInsert(
                [
                    'sub_institute_id' => $sid,
                    'scope'            => $scope,
                    'scope_id'         => $scopeId,
                    'key'              => $key,
                ],
                [
                    'value'      => $value,
                    'updated_by' => $userId,
                    'created_by' => DB::raw('COALESCE(created_by, ' . ($userId ?: 'NULL') . ')'),
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }

        return $this->competencySettings($sid, $scope, $defaults, $scopeId);
    }

    /** Cast a stored string back to the type implied by its default. */
    private function castSetting($stored, $default)
    {
        if (is_bool($default)) {
            return filter_var($stored, FILTER_VALIDATE_BOOLEAN);
        }
        if (is_int($default)) {
            return (int) $stored;
        }
        if (is_float($default)) {
            return (float) $stored;
        }
        if (is_array($default)) {
            $decoded = json_decode((string) $stored, true);
            return is_array($decoded) ? $decoded : $default;
        }

        return (string) $stored;
    }
}
