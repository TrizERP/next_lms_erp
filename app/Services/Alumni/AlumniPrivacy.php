<?php

namespace App\Services\Alumni;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Field-level visibility for alumni (spec section 31).
 *
 * LEVELS (most open to most closed): public, alumni_only, institution_only, private.
 *   public            anyone who may see the directory
 *   alumni_only       other alumni and the institution
 *   institution_only  the institution's staff, not other alumni
 *   private           only the alumnus
 *
 * VIEWERS: 'institution' (staff), 'alumni' (another alumnus), 'public'. The viewer is ALWAYS derived
 * server-side from the verified session - never from a request parameter - so a caller cannot widen
 * what they see by changing an API parameter.
 *
 * A viewer sees a field when its level is in visibleLevels(viewer). A field with no stored choice
 * falls back to the tenant default, then to the built-in default below. Built-in defaults are
 * deliberately conservative: contact details stay with the institution (which already holds them from
 * the student record) and are not shown to other alumni until the alumnus opens them up.
 *
 * The same rule is applied three ways, so results cannot disagree:
 *   mask()        hides values in a returned row
 *   scopeVisible  restricts a query so a hidden field cannot be searched on (filtering on a field would
 *                 otherwise reveal it: "city = Pune" returns exactly the people living in Pune)
 *   visibilityFor loads the per-alumnus choices for a page of rows in one query
 */
class AlumniPrivacy
{
    public const LEVELS = ['public', 'alumni_only', 'institution_only', 'private'];

    /** Setting key -> the columns it governs. Identity (name, code, batch, passing year, course) is not listed: it is always visible. */
    public const GROUPS = [
        'email' => ['email'],
        'mobile' => ['mobile', 'alt_mobile'],
        'address' => ['current_address', 'permanent_address', 'pincode'],
        'dob' => ['dob'],
        'location' => ['city', 'state', 'country'],
        'employment' => ['current_company', 'current_job_title', 'current_industry', 'employment_status'],
        'linkedin' => ['linkedin_url'],
        'photo' => ['photo_path'],
        'skills' => [],            // alumni_skills rows
        'higher_education' => [],  // alumni_education rows
    ];

    public const LABELS = [
        'email' => 'Email', 'mobile' => 'Mobile numbers', 'address' => 'Address', 'dob' => 'Date of birth',
        'location' => 'City, state and country', 'employment' => 'Company, job role and industry',
        'linkedin' => 'LinkedIn profile', 'photo' => 'Profile photo', 'skills' => 'Skills',
        'higher_education' => 'Higher education',
    ];

    public const DEFAULTS = [
        'email' => 'institution_only', 'mobile' => 'institution_only', 'address' => 'institution_only',
        'dob' => 'institution_only', 'location' => 'alumni_only', 'employment' => 'alumni_only',
        'linkedin' => 'alumni_only', 'photo' => 'alumni_only', 'skills' => 'alumni_only', 'higher_education' => 'alumni_only',
    ];

    public function __construct(private AlumniService $alumni)
    {
    }

    /** @return array<int, string> */
    public static function visibleLevels(string $viewer): array
    {
        return match ($viewer) {
            'institution' => ['public', 'alumni_only', 'institution_only'],
            'alumni' => ['public', 'alumni_only'],
            default => ['public'],
        };
    }

    /** @return array<string, string> group => level, after tenant overrides. */
    public function defaults(int $tenant): array
    {
        $over = (array) $this->alumni->setting($tenant, 'privacy_defaults', []);
        $out = self::DEFAULTS;
        foreach ($over as $group => $level) {
            if (isset($out[$group]) && in_array($level, self::LEVELS, true)) {
                $out[$group] = $level;
            }
        }

        return $out;
    }

    /**
     * Stored choices for these alumni, merged over the defaults.
     *
     * @param  array<int, int>  $alumniIds
     * @return array<int, array<string, string>> alumni_id => [group => level]
     */
    public function visibilityFor(int $tenant, array $alumniIds): array
    {
        $defaults = $this->defaults($tenant);
        $map = [];
        foreach ($alumniIds as $id) {
            $map[(int) $id] = $defaults;
        }
        if ($alumniIds === []) {
            return $map;
        }
        $rows = DB::table('alumni_privacy')->where('sub_institute_id', $tenant)->whereIn('alumni_id', $alumniIds)
            ->get(['alumni_id', 'field_key', 'visibility']);
        foreach ($rows as $r) {
            if (isset(self::GROUPS[$r->field_key]) && in_array($r->visibility, self::LEVELS, true)) {
                $map[(int) $r->alumni_id][$r->field_key] = $r->visibility;
            }
        }

        return $map;
    }

    public function canSee(string $level, string $viewer): bool
    {
        return in_array($level, self::visibleLevels($viewer), true);
    }

    /**
     * Null out every hidden column in $row and report which groups were hidden.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $visibility  group => level for this alumnus
     * @return array{0: array<string, mixed>, 1: array<int, string>} [masked row, hidden groups]
     */
    public function mask(array $row, array $visibility, string $viewer): array
    {
        $hidden = [];
        foreach (self::GROUPS as $group => $columns) {
            $level = $visibility[$group] ?? self::DEFAULTS[$group];
            if ($this->canSee($level, $viewer)) {
                continue;
            }
            $hidden[] = $group;
            foreach ($columns as $c) {
                if (array_key_exists($c, $row)) {
                    $row[$c] = null;
                }
            }
        }

        return [$row, $hidden];
    }

    /**
     * Restrict $query (on the `alumni` table) to rows whose $group is visible to $viewer. Use before
     * filtering or searching on any column of that group.
     */
    public function scopeVisible(Builder $query, int $tenant, string $group, string $viewer): void
    {
        $levels = self::visibleLevels($viewer);
        $defaultVisible = in_array($this->defaults($tenant)[$group] ?? 'private', $levels, true);

        $query->where(function ($w) use ($tenant, $group, $levels, $defaultVisible) {
            // an explicit choice that this viewer may see ...
            $w->whereExists(function ($e) use ($tenant, $group, $levels) {
                $e->select(DB::raw(1))->from('alumni_privacy as ap')
                    ->whereColumn('ap.alumni_id', 'alumni.id')->where('ap.sub_institute_id', $tenant)
                    ->where('ap.field_key', $group)->whereIn('ap.visibility', $levels);
            });
            // ... or no explicit choice and a default that this viewer may see
            if ($defaultVisible) {
                $w->orWhereNotExists(function ($e) use ($tenant, $group) {
                    $e->select(DB::raw(1))->from('alumni_privacy as ap2')
                        ->whereColumn('ap2.alumni_id', 'alumni.id')->where('ap2.sub_institute_id', $tenant)
                        ->where('ap2.field_key', $group);
                });
            }
        });
    }

    /**
     * One alumnus's privacy settings as shown on screen: each field group's level (and whether it is the default) plus the
     * four channel consents. Shared by the staff screen and the alumni's own portal so the two can never disagree.
     *
     * @return array{fields: array<int, array{key:string,label:string,level:string,is_default:bool}>, levels: array<int,string>, consents: array<string,bool>}
     */
    public function payload(int $tenant, object $row): array
    {
        $vis = $this->visibilityFor($tenant, [(int) $row->id])[(int) $row->id];
        $stored = DB::table('alumni_privacy')->where('sub_institute_id', $tenant)->where('alumni_id', $row->id)->pluck('visibility', 'field_key')->all();
        $fields = [];
        foreach (self::GROUPS as $key => $cols) {
            $fields[] = ['key' => $key, 'label' => self::LABELS[$key], 'level' => $vis[$key], 'is_default' => ! isset($stored[$key])];
        }

        return [
            'fields' => $fields,
            'levels' => self::LEVELS,
            'consents' => [
                'consent_email' => (bool) $row->consent_email, 'consent_sms' => (bool) $row->consent_sms,
                'consent_whatsapp' => (bool) $row->consent_whatsapp, 'consent_push' => (bool) $row->consent_push,
            ],
        ];
    }

    /**
     * Save level choices ('default' removes a stored choice) and channel consents for ONE alumnus of ONE institute, in a
     * transaction. The caller must already have validated the keys and levels and checked who may change them.
     *
     * @param  array<string, string>  $fields    group => level|'default'
     * @param  array<string, mixed>   $consents  consent_email|consent_sms|consent_whatsapp|consent_push => bool
     */
    public function saveChoices(int $tenant, object $row, array $fields, array $consents, ?int $userId): void
    {
        $now = now();
        DB::transaction(function () use ($tenant, $row, $fields, $consents, $userId, $now) {
            foreach ($fields as $group => $level) {
                $where = ['sub_institute_id' => $tenant, 'alumni_id' => $row->id, 'field_key' => $group];
                if ($level === 'default') {
                    DB::table('alumni_privacy')->where($where)->delete();
                } elseif (DB::table('alumni_privacy')->where($where)->exists()) {
                    DB::table('alumni_privacy')->where($where)->update(['visibility' => $level, 'updated_at' => $now]);
                } else {
                    DB::table('alumni_privacy')->insert($where + ['visibility' => $level, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
            $flags = [];
            foreach ($consents as $k => $val) {
                $flags[$k] = filter_var($val, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
            if ($flags !== []) {
                DB::table('alumni')->where('sub_institute_id', $tenant)->where('id', $row->id)->update($flags + ['updated_by' => $userId, 'updated_at' => $now]);
            }
        });
    }
}
