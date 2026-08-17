<?php

namespace App\Services\PAL\Administration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * New PAL → Administration — the architecture settings registry.
 *
 * Owns the read and write path for the nine subsystems defined in
 * config/pal_architecture.php. Two invariants make the rest of the module safe:
 *
 *   1. A STORED OVERRIDE ONLY EVER HOLDS EDITABLE FIELDS. Everything a panel
 *      marks `editable => false` — a layer's name, a loop step's description,
 *      a dimension's measurement formula — is re-read from config on every
 *      request and can never be written by a client. So a deploy that revises
 *      the blueprint wording reaches every tenant immediately, and a caller
 *      cannot rewrite the architecture by POSTing a fabricated record.
 *
 *   2. THE RECORD SET IS FIXED. There are nine layers, twelve loop steps, five
 *      agents. A write may retune a record, never add or delete one, so the
 *      structure the engines rely on cannot be edited away from underneath
 *      them. Records are matched by their `key`; an unknown key is rejected
 *      rather than appended.
 *
 * Resolution order on read: the caller's institute → the estate-wide row
 * (sub_institute_id 0, written by a super-admin) → the shipped config default.
 */
class ArchitectureRegistry
{
    private const TABLE = 'pal_architecture_settings';

    /** Panel kinds that hold settings. `metrics` and `catalog` are computed/read-only. */
    private const WRITABLE_KINDS = ['params', 'records', 'matrix'];

    /**
     * Subsystem summaries for the Administration overview.
     *
     * Deliberately does not merge overrides — the overview shows what each
     * subsystem IS plus whether it has been retuned, not its full settings.
     */
    public function catalogue(?int $tenant): array
    {
        $customised = $this->customisedGroups($tenant);

        $out = [];
        foreach ($this->config() as $key => $definition) {
            $groups = $this->writableGroups($definition);

            $out[] = [
                'key' => $key,
                'label' => $definition['label'] ?? $key,
                'tagline' => $definition['tagline'] ?? '',
                'icon' => $definition['icon'] ?? 'layers',
                'source_document' => $definition['source_document'] ?? null,
                'setting_groups' => count($groups),
                'customised_groups' => count(array_intersect($groups, $customised[$key] ?? [])),
                'requires_confirmation' => in_array($key, $this->guard('confirm_on_write'), true),
            ];
        }

        return $out;
    }

    /** The full descriptor for one subsystem: panels plus resolved settings. */
    public function subsystem(string $key, ?int $tenant): array
    {
        $definition = $this->definition($key);
        $overrides = $this->overridesFor($key, $tenant);

        $settings = [];
        $customised = [];

        foreach ($definition['settings'] ?? [] as $group => $default) {
            if (! array_key_exists($group, $overrides)) {
                $settings[$group] = $default;
                continue;
            }

            $settings[$group] = $this->mergeGroup($definition, $group, $default, $overrides[$group]);
            $customised[] = $group;
        }

        return [
            'key' => $key,
            'label' => $definition['label'] ?? $key,
            'tagline' => $definition['tagline'] ?? '',
            'icon' => $definition['icon'] ?? 'layers',
            'source_document' => $definition['source_document'] ?? null,
            'requires_confirmation' => in_array($key, $this->guard('confirm_on_write'), true),
            'panels' => array_values($definition['panels'] ?? []),
            'settings' => $settings,
            'customised_groups' => $customised,
        ];
    }

    /**
     * Persist an override for one settings group.
     *
     * @throws InvalidArgumentException when the subsystem, group or a value is
     *         not one the panel descriptor allows. The message names the exact
     *         field so the UI can surface it verbatim.
     */
    public function save(string $key, string $group, mixed $value, ?int $tenant, ?int $userId): array
    {
        $definition = $this->definition($key);
        $panel = $this->writablePanel($definition, $group);
        $default = $definition['settings'][$group] ?? null;

        if ($default === null) {
            throw new InvalidArgumentException("Unknown settings group '{$group}'.");
        }

        $clean = match ($panel['kind']) {
            'params' => $this->sanitiseParams($panel, $value, $default),
            'records' => $this->sanitiseRecords($panel, $value, $default),
            'matrix' => $this->sanitiseMatrix($panel, $value),
            default => throw new InvalidArgumentException("Settings group '{$group}' is not editable."),
        };

        $this->assertTable();

        $scope = $this->scope($tenant);
        $now = now();

        $existing = DB::table(self::TABLE)
            ->where('sub_institute_id', $scope)
            ->where('subsystem', $key)
            ->where('settings_key', $group)
            ->first();

        $encoded = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($existing !== null) {
            DB::table(self::TABLE)->where('id', $existing->id)->update([
                'value' => $encoded,
                'updated_by' => $userId,
                'updated_at' => $now,
            ]);
        } else {
            DB::table(self::TABLE)->insert([
                'sub_institute_id' => $scope,
                'subsystem' => $key,
                'settings_key' => $group,
                'value' => $encoded,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $this->subsystem($key, $tenant);
    }

    /** Drop an override so the group tracks the shipped blueprint default again. */
    public function reset(string $key, ?string $group, ?int $tenant): array
    {
        $definition = $this->definition($key);

        if ($group !== null && ! array_key_exists($group, $definition['settings'] ?? [])) {
            throw new InvalidArgumentException("Unknown settings group '{$group}'.");
        }

        if (Schema::hasTable(self::TABLE)) {
            $query = DB::table(self::TABLE)
                ->where('sub_institute_id', $this->scope($tenant))
                ->where('subsystem', $key);

            if ($group !== null) {
                $query->where('settings_key', $group);
            }

            $query->delete();
        }

        return $this->subsystem($key, $tenant);
    }

    /** Resolved settings for one group — the read path engines should use. */
    public function settings(string $key, string $group, ?int $tenant): mixed
    {
        return $this->subsystem($key, $tenant)['settings'][$group] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->config());
    }

    /** May this caller write? Read access is decided by the controller. */
    public function mayWrite(array $auth): bool
    {
        if ((int) ($auth['is_admin'] ?? 0) > 0) {
            return true;
        }

        $profile = strtolower(trim((string) ($auth['user_profile_name'] ?? '')));
        if ($profile === '') {
            return false;
        }

        foreach ($this->guard('writer_profiles') as $allowed) {
            if ($profile === strtolower($allowed)) {
                return true;
            }
        }

        return false;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Merging
    // ══════════════════════════════════════════════════════════════════════

    private function mergeGroup(array $definition, string $group, mixed $default, mixed $override): mixed
    {
        $panel = $this->panelFor($definition, $group);

        if ($panel === null) {
            return $default;
        }

        return match ($panel['kind']) {
            // A params override is a partial map: unknown keys were stripped on
            // write, so a straight overlay is safe and keeps new config fields
            // visible without a migration.
            'params' => is_array($override) ? array_merge((array) $default, $override) : $default,

            // Records merge per record, by key. Non-editable columns always
            // come from config, so blueprint wording stays authoritative.
            'records' => $this->mergeRecords($default, $override),

            'matrix' => $this->mergeMatrix($panel, $default, $override),

            default => $default,
        };
    }

    private function mergeRecords(mixed $default, mixed $override): array
    {
        $defaults = is_array($default) ? $default : [];
        if (! is_array($override)) {
            return $defaults;
        }

        $patch = [];
        foreach ($override as $row) {
            if (is_array($row) && isset($row['key'])) {
                $patch[(string) $row['key']] = $row;
            }
        }

        return array_map(static function (array $record) use ($patch) {
            $key = (string) ($record['key'] ?? '');

            return isset($patch[$key]) ? array_merge($record, $patch[$key]) : $record;
        }, $defaults);
    }

    private function mergeMatrix(array $panel, mixed $default, mixed $override): array
    {
        $merged = is_array($default) ? $default : [];
        if (! is_array($override)) {
            return $merged;
        }

        foreach ($panel['rows'] ?? [] as $row) {
            $rowKey = (string) ($row['key'] ?? '');
            if ($rowKey === '' || ! isset($override[$rowKey]) || ! is_array($override[$rowKey])) {
                continue;
            }

            foreach ($panel['columns'] ?? [] as $column) {
                $colKey = (string) ($column['key'] ?? '');
                if ($colKey !== '' && isset($override[$rowKey][$colKey])) {
                    $merged[$rowKey][$colKey] = (string) $override[$rowKey][$colKey];
                }
            }
        }

        return $merged;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Sanitising — every write passes through here
    // ══════════════════════════════════════════════════════════════════════

    private function sanitiseParams(array $panel, mixed $value, mixed $default): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Expected an object of settings.');
        }

        $clean = [];
        foreach ($panel['fields'] ?? [] as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '' || ! array_key_exists($key, $value)) {
                continue;
            }

            $clean[$key] = $this->coerce(
                $field,
                $value[$key],
                is_array($default) ? ($default[$key] ?? null) : null,
                $field['label'] ?? $key
            );
        }

        if ($clean === []) {
            throw new InvalidArgumentException('No recognised settings were supplied.');
        }

        return $clean;
    }

    private function sanitiseRecords(array $panel, mixed $value, mixed $default): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Expected a list of records.');
        }

        $known = [];
        foreach (is_array($default) ? $default : [] as $record) {
            if (is_array($record) && isset($record['key'])) {
                $known[(string) $record['key']] = $record;
            }
        }

        // Only editable columns are ever stored — see invariant 1.
        $editable = [];
        foreach ($panel['columns'] ?? [] as $column) {
            if (! empty($column['editable'])) {
                $editable[(string) $column['key']] = $column;
            }
        }

        if ($editable === []) {
            throw new InvalidArgumentException('This table has no editable columns.');
        }

        $clean = [];
        foreach ($value as $row) {
            if (! is_array($row) || ! isset($row['key'])) {
                continue;
            }

            $key = (string) $row['key'];
            if (! isset($known[$key])) {
                // Invariant 2: the record set is fixed.
                throw new InvalidArgumentException("Unknown record '{$key}'.");
            }

            $entry = ['key' => $key];
            foreach ($editable as $columnKey => $column) {
                if (! array_key_exists($columnKey, $row)) {
                    continue;
                }

                $entry[$columnKey] = $this->coerce(
                    $column,
                    $row[$columnKey],
                    $known[$key][$columnKey] ?? null,
                    ($column['label'] ?? $columnKey) . " on '{$key}'"
                );
            }

            $clean[] = $entry;
        }

        if ($clean === []) {
            throw new InvalidArgumentException('No recognised records were supplied.');
        }

        return $clean;
    }

    private function sanitiseMatrix(array $panel, mixed $value): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException('Expected a rubric grid.');
        }

        $rowKeys = array_column($panel['rows'] ?? [], 'key');
        $colKeys = array_column($panel['columns'] ?? [], 'key');

        $clean = [];
        foreach ($rowKeys as $rowKey) {
            if (! isset($value[$rowKey]) || ! is_array($value[$rowKey])) {
                continue;
            }

            foreach ($colKeys as $colKey) {
                if (! array_key_exists($colKey, $value[$rowKey])) {
                    continue;
                }

                $cell = trim((string) $value[$rowKey][$colKey]);
                if ($cell === '') {
                    throw new InvalidArgumentException("The {$rowKey}/{$colKey} descriptor cannot be empty.");
                }
                if (mb_strlen($cell) > 1000) {
                    throw new InvalidArgumentException("The {$rowKey}/{$colKey} descriptor is too long (1000 characters maximum).");
                }

                $clean[$rowKey][$colKey] = $cell;
            }
        }

        if ($clean === []) {
            throw new InvalidArgumentException('No recognised rubric cells were supplied.');
        }

        return $clean;
    }

    /** One field or column value, coerced and range-checked against its descriptor. */
    private function coerce(array $descriptor, mixed $raw, mixed $fallback, string $label): mixed
    {
        $type = (string) ($descriptor['type'] ?? 'text');

        switch ($type) {
            case 'toggle':
                return $raw === true || $raw === 1 || $raw === '1' || $raw === 'true';

            case 'number':
                if (! is_numeric($raw)) {
                    throw new InvalidArgumentException("{$label} must be a number.");
                }
                $number = (float) $raw;

                if (isset($descriptor['min']) && $number < (float) $descriptor['min']) {
                    throw new InvalidArgumentException("{$label} cannot be below {$descriptor['min']}.");
                }
                if (isset($descriptor['max']) && $number > (float) $descriptor['max']) {
                    throw new InvalidArgumentException("{$label} cannot be above {$descriptor['max']}.");
                }

                // Keep integers integral so a round-trip does not turn 3 into 3.0.
                $isIntegral = ! isset($descriptor['step']) || fmod((float) $descriptor['step'], 1.0) === 0.0;

                return $isIntegral && fmod($number, 1.0) === 0.0 ? (int) $number : $number;

            case 'select':
                $options = array_map('strval', (array) ($descriptor['options'] ?? []));
                $choice = (string) $raw;
                if (! in_array($choice, $options, true)) {
                    throw new InvalidArgumentException("{$label} must be one of: " . implode(', ', $options) . '.');
                }

                return $choice;

            case 'tags':
                if (! is_array($raw)) {
                    throw new InvalidArgumentException("{$label} must be a list.");
                }
                $tags = [];
                foreach ($raw as $tag) {
                    $tag = trim((string) $tag);
                    if ($tag !== '') {
                        $tags[] = mb_substr($tag, 0, 64);
                    }
                }

                return array_values(array_unique($tags));

            case 'code':
            case 'text':
            default:
                $text = trim((string) $raw);
                if (mb_strlen($text) > 1000) {
                    throw new InvalidArgumentException("{$label} is too long (1000 characters maximum).");
                }

                // An empty string is a legitimate "inherit the default" for the
                // optional per-agent model pin, so it is kept rather than
                // replaced by the fallback.
                return $text === '' && $fallback === null ? '' : $text;
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // Plumbing
    // ══════════════════════════════════════════════════════════════════════

    private function config(): array
    {
        return (array) config('pal_architecture.subsystems', []);
    }

    private function guard(string $key): array
    {
        return (array) config("pal_architecture.guards.{$key}", []);
    }

    private function definition(string $key): array
    {
        $config = $this->config();

        if (! array_key_exists($key, $config)) {
            throw new InvalidArgumentException("Unknown architecture subsystem '{$key}'.");
        }

        return $config[$key];
    }

    private function panelFor(array $definition, string $group): ?array
    {
        foreach ($definition['panels'] ?? [] as $panel) {
            if (($panel['key'] ?? null) === $group) {
                return $panel;
            }
        }

        return null;
    }

    private function writablePanel(array $definition, string $group): array
    {
        $panel = $this->panelFor($definition, $group);

        if ($panel === null || ! in_array($panel['kind'] ?? '', self::WRITABLE_KINDS, true)) {
            throw new InvalidArgumentException("Settings group '{$group}' is not editable.");
        }

        return $panel;
    }

    /** @return string[] group keys that a client is allowed to write */
    private function writableGroups(array $definition): array
    {
        $groups = [];
        foreach ($definition['panels'] ?? [] as $panel) {
            if (in_array($panel['kind'] ?? '', self::WRITABLE_KINDS, true) && isset($panel['key'])) {
                $groups[] = (string) $panel['key'];
            }
        }

        return $groups;
    }

    /**
     * Stored overrides for one subsystem, institute row winning over the
     * estate-wide row.
     *
     * @return array<string, mixed> keyed by settings group
     */
    private function overridesFor(string $key, ?int $tenant): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }

        $scopes = array_values(array_unique([0, $this->scope($tenant)]));

        $rows = DB::table(self::TABLE)
            ->where('subsystem', $key)
            ->whereIn('sub_institute_id', $scopes)
            // Ascending, so the institute row (higher id than 0) overwrites the
            // estate-wide one in the loop below.
            ->orderBy('sub_institute_id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row->value, true);
            if (is_array($decoded)) {
                $out[(string) $row->settings_key] = $decoded;
            }
        }

        return $out;
    }

    /** @return array<string, string[]> subsystem key → customised group keys */
    private function customisedGroups(?int $tenant): array
    {
        if (! Schema::hasTable(self::TABLE)) {
            return [];
        }

        $rows = DB::table(self::TABLE)
            ->select('subsystem', 'settings_key')
            ->whereIn('sub_institute_id', array_values(array_unique([0, $this->scope($tenant)])))
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->subsystem][] = (string) $row->settings_key;
        }

        return $out;
    }

    private function scope(?int $tenant): int
    {
        return $tenant === null ? 0 : max(0, $tenant);
    }

    private function assertTable(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            throw new InvalidArgumentException(
                'The architecture settings table is missing. Run the PAL migrations on this server before saving.'
            );
        }
    }
}
