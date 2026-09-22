<?php

namespace App\Services\PAL\Flow;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * The database half of the flow control plane: which profiles exist, which
 * version of each is live, and which institute is assigned what.
 *
 * Structurally mirrors ArchitectureRegistry, including the thing that matters
 * most about it — EVERY read is guarded by Schema::hasTable() and degrades to
 * "nothing configured" rather than throwing.
 *
 * That is not defensive habit, it is a fact about this estate: 408 of its 996
 * migrations are pending, hosts drift, and the flow tables will exist on some
 * and not others for as long as that is true. A registry that threw on a host
 * without the tables would take the whole learning engine down on exactly the
 * hosts that are furthest behind. Instead they resolve the shipped `standard`
 * flow from config and carry on, which is what they were doing anyway.
 *
 * ---------------------------------------------------------------------------
 * IMMUTABILITY IS ENFORCED HERE, NOT IN THE SCHEMA
 * ---------------------------------------------------------------------------
 * A version that has left 'draft' can never be edited — publish() refuses it,
 * and an edit creates version N+1 while marking N superseded with
 * superseded_by_id pointing forward.
 *
 * In PHP rather than as a database trigger because this estate is forward-only
 * (AppServiceProvider.php:76-90 throws on any SQL containing "DROP TABLE"), so
 * a trigger that turned out to be wrong could not be removed.
 */
class EsoFlowRegistry
{
    private const PROFILES = 'pal_flow_profiles';

    private const VERSIONS = 'pal_flow_profile_versions';

    private const ASSIGNMENTS = 'pal_flow_assignments';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUPERSEDED = 'superseded';

    /** @var array<string, mixed> resolved lookups, per request */
    private array $memo = [];

    /**
     * Table presence, cached for the life of the PROCESS rather than the
     * request.
     *
     * Deliberately static. Schema::hasTable() is a round trip, this needs
     * three of them, and whether a table exists cannot change inside a running
     * process — only a migration changes it, and that is a different process.
     *
     * The instance memo below is NOT enough, because both this registry and
     * the resolver that owns it are bound per resolution: every nextAction()
     * call builds fresh ones, so an instance-level cache is cold every single
     * time. That cost three queries per resolve and was caught by
     * EsoFlowParityTest's query-count assertion.
     */
    private static ?bool $tablesPresent = null;

    /**
     * Are the flow tables present on this connection?
     *
     * Checked once per request rather than per call: Schema::hasTable() is a
     * round trip, and on a remote database the memo() docblock in
     * EsoPolicyService measures one at roughly 29ms.
     */
    public function available(): bool
    {
        return self::$tablesPresent ??= (
            Schema::hasTable(self::PROFILES)
            && Schema::hasTable(self::VERSIONS)
            && Schema::hasTable(self::ASSIGNMENTS)
        );
    }

    /**
     * Forget the cached table presence.
     *
     * Only a test that creates or drops the flow tables mid-process needs
     * this. Production never does.
     */
    public static function forgetSchemaCache(): void
    {
        self::$tablesPresent = null;
    }

    /**
     * The flow this institute should run: its profile key, version id and
     * stored structure, in ONE query.
     *
     * Resolved as a single statement rather than "look up the assignment, then
     * look up that profile's active version" because this runs on the path of
     * every single nextAction() call. Two queries per resolve is two round
     * trips per learner action, and on a remote database EsoPolicyService's
     * own memo() docblock measures one at roughly 29ms.
     *
     * The LEFT JOIN plus the ordering is what makes it one query: an institute
     * with an assignment matches on it and sorts first; an institute without
     * one falls through to the row flagged is_default. Both cases come back
     * from the same statement.
     *
     * Null means no flow tables, or no published default — both of which mean
     * "resolve from config", and neither of which is an error.
     *
     * @return array{profile_key:string, id:int, definition:array<string,mixed>}|null
     */
    public function flowFor(int $subInstituteId): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $key = 'flow:' . $subInstituteId;

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $row = DB::table(self::PROFILES . ' as p')
            ->join(self::VERSIONS . ' as v', 'v.id', '=', 'p.active_version_id')
            ->leftJoin(self::ASSIGNMENTS . ' as a', function ($join) use ($subInstituteId) {
                $join->on('a.profile_id', '=', 'p.id')
                    ->where('a.sub_institute_id', '=', $subInstituteId);
            })
            ->where('v.status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNotNull('a.id')->orWhere('p.is_default', true);
            })
            ->orderByRaw('CASE WHEN a.id IS NULL THEN 1 ELSE 0 END')
            ->select('p.profile_key', 'v.id', 'v.definition')
            ->first();

        if ($row === null) {
            return $this->memo[$key] = null;
        }

        return $this->memo[$key] = [
            'profile_key' => (string) $row->profile_key,
            'id' => (int) $row->id,
            'definition' => $this->decode($row->definition, (int) $row->id),
        ];
    }

    /**
     * The profile key this institute is assigned, ignoring the default.
     *
     * Only used by the admin surface, which needs to distinguish "assigned to
     * standard" from "not assigned, therefore standard". The resolve path uses
     * flowFor() instead.
     */
    public function assignedProfileKey(int $subInstituteId): ?string
    {
        if (! $this->available()) {
            return null;
        }

        $key = 'assigned:' . $subInstituteId;

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $row = DB::table(self::ASSIGNMENTS . ' as a')
            ->join(self::PROFILES . ' as p', 'p.id', '=', 'a.profile_id')
            ->where('a.sub_institute_id', $subInstituteId)
            ->select('p.profile_key')
            ->first();

        return $this->memo[$key] = $row === null ? null : (string) $row->profile_key;
    }

    /**
     * The live version of a named profile.
     *
     * @return array{id:int, definition:array<string,mixed>}|null
     */
    public function activeVersion(string $profileKey): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $key = 'active:' . $profileKey;

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $row = DB::table(self::PROFILES . ' as p')
            ->join(self::VERSIONS . ' as v', 'v.id', '=', 'p.active_version_id')
            ->where('p.profile_key', $profileKey)
            ->where('v.status', self::STATUS_ACTIVE)
            ->select('v.id', 'v.definition')
            ->first();

        if ($row === null) {
            return $this->memo[$key] = null;
        }

        return $this->memo[$key] = [
            'id' => (int) $row->id,
            'definition' => $this->decode($row->definition, (int) $row->id),
        ];
    }

    /**
     * One version by id, whatever its status.
     *
     * This is the PINNED read: a learner carrying flow_version_id = 7 must
     * resolve version 7 even though it was superseded months ago, which is the
     * entire point of pinning. So it deliberately does not filter on status.
     *
     * @return array{id:int, definition:array<string,mixed>}|null
     */
    public function version(int $versionId): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $key = 'version:' . $versionId;

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $row = DB::table(self::VERSIONS)->where('id', $versionId)->first();

        if ($row === null) {
            return $this->memo[$key] = null;
        }

        return $this->memo[$key] = [
            'id' => (int) $row->id,
            'definition' => $this->decode($row->definition, $versionId),
        ];
    }

    /**
     * Every profile, with whether it is live and how many institutes run it.
     *
     * @return array<int, array<string,mixed>>
     */
    public function catalogue(): array
    {
        if (! $this->available()) {
            return [];
        }

        return DB::table(self::PROFILES . ' as p')
            ->leftJoin(self::VERSIONS . ' as v', 'v.id', '=', 'p.active_version_id')
            ->leftJoin(self::ASSIGNMENTS . ' as a', 'a.profile_id', '=', 'p.id')
            ->groupBy('p.id', 'p.profile_key', 'p.label', 'p.description', 'p.is_default', 'v.id', 'v.version', 'v.status')
            ->selectRaw(
                'p.id, p.profile_key, p.label, p.description, p.is_default, '
                . 'v.id as version_id, v.version, v.status, COUNT(a.id) as institutes'
            )
            ->orderByDesc('p.is_default')
            ->orderBy('p.profile_key')
            ->get()
            ->map(static fn ($r): array => [
                'id' => (int) $r->id,
                'profile_key' => (string) $r->profile_key,
                'label' => (string) $r->label,
                'description' => $r->description,
                'is_default' => (bool) $r->is_default,
                'version_id' => $r->version_id === null ? null : (int) $r->version_id,
                'version' => $r->version === null ? null : (int) $r->version,
                'status' => $r->status,
                'institutes' => (int) $r->institutes,
            ])
            ->all();
    }

    /**
     * Assign an institute to a profile.
     *
     * Takes effect for concepts a learner has not started yet. Learners already
     * part-way through keep the version pinned on their node state, which is
     * what step 6's flow_version_id column is for — without it this method
     * would change the rules under a learner mid-concept, which is precisely
     * the defect pal_architecture_settings has.
     */
    public function assign(int $subInstituteId, string $profileKey, ?int $userId = null): void
    {
        $this->requireTables();

        $profile = DB::table(self::PROFILES)->where('profile_key', $profileKey)->first();

        if ($profile === null) {
            throw new InvalidArgumentException(
                "Unknown flow profile '{$profileKey}'. Profiles are a closed set; a school is "
                . 'assigned one of them rather than describing its own.'
            );
        }

        if ($profile->active_version_id === null) {
            throw new InvalidArgumentException(
                "Flow profile '{$profileKey}' has no published version, so there is nothing to run."
            );
        }

        DB::table(self::ASSIGNMENTS)->updateOrInsert(
            ['sub_institute_id' => $subInstituteId],
            ['profile_id' => $profile->id, 'assigned_by' => $userId, 'updated_at' => now(), 'created_at' => now()]
        );

        $this->forget();
    }

    /** Remove an assignment, returning the institute to the default profile. */
    public function unassign(int $subInstituteId): void
    {
        $this->requireTables();

        DB::table(self::ASSIGNMENTS)->where('sub_institute_id', $subInstituteId)->delete();

        $this->forget();
    }

    /**
     * Publish a draft version, making it the profile's live one.
     *
     * The previous active version is marked superseded with superseded_by_id
     * pointing at this one, so a learner still pinned to it can be traced
     * forward to what replaced it.
     *
     * @return array{id:int, version:int}
     */
    public function publish(int $versionId, ?int $userId = null): array
    {
        $this->requireTables();

        $version = DB::table(self::VERSIONS)->where('id', $versionId)->first();

        if ($version === null) {
            throw new InvalidArgumentException("No flow version {$versionId}.");
        }

        if ($version->status !== self::STATUS_DRAFT) {
            throw new InvalidArgumentException(
                "Flow version {$versionId} is '{$version->status}' and cannot be republished. "
                . 'A published version is immutable because learners are pinned to it; '
                . 'edit it by creating the next version instead.'
            );
        }

        DB::transaction(function () use ($version, $versionId, $userId): void {
            $previous = DB::table(self::PROFILES)->where('id', $version->profile_id)->value('active_version_id');

            if ($previous !== null) {
                DB::table(self::VERSIONS)->where('id', $previous)->update([
                    'status' => self::STATUS_SUPERSEDED,
                    'superseded_by_id' => $versionId,
                    'updated_at' => now(),
                ]);
            }

            DB::table(self::VERSIONS)->where('id', $versionId)->update([
                'status' => self::STATUS_ACTIVE,
                'published_by' => $userId,
                'published_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table(self::PROFILES)->where('id', $version->profile_id)->update([
                'active_version_id' => $versionId,
                'updated_at' => now(),
            ]);
        });

        $this->forget();

        return ['id' => $versionId, 'version' => (int) $version->version];
    }

    /**
     * Write the shipped profiles into the database, once.
     *
     * Idempotent by profile_key: a profile that already exists is left exactly
     * as it is, including any version an administrator has since published.
     * Re-running this must never overwrite an estate's own tuning, which is why
     * it inserts rather than upserts the definition.
     *
     * @param  callable(string):array<string,mixed>  $resolveStructure  turns a
     *         profile key into its fully resolved structure
     * @return array<string, string> profile key => what happened
     */
    public function seedShippedProfiles(callable $resolveStructure): array
    {
        $this->requireTables();

        $outcome = [];

        foreach ((array) config('pal_flow.profiles', []) as $key => $profile) {
            $key = (string) $key;

            if (DB::table(self::PROFILES)->where('profile_key', $key)->exists()) {
                $outcome[$key] = 'already present, left alone';
                continue;
            }

            DB::transaction(function () use ($key, $profile, $resolveStructure, &$outcome): void {
                $profileId = DB::table(self::PROFILES)->insertGetId([
                    'sub_institute_id' => 0,
                    'profile_key' => $key,
                    'label' => (string) ($profile['label'] ?? $key),
                    'description' => $profile['description'] ?? null,
                    'is_default' => (bool) ($profile['is_default'] ?? false),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $versionId = DB::table(self::VERSIONS)->insertGetId([
                    'profile_id' => $profileId,
                    'version' => 1,
                    'status' => self::STATUS_DRAFT,
                    'definition' => json_encode($resolveStructure($key), JSON_THROW_ON_ERROR),
                    'change_note' => 'Seeded from config/pal_flow.php at rollout step 5.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $outcome[$key] = 'created';

                // Published immediately: a seeded profile that stayed a draft
                // could not be assigned, which would make this whole step a
                // no-op with extra rows.
                DB::table(self::VERSIONS)->where('id', $versionId)->update([
                    'status' => self::STATUS_ACTIVE,
                    'published_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table(self::PROFILES)->where('id', $profileId)->update([
                    'active_version_id' => $versionId,
                    'updated_at' => now(),
                ]);
            });
        }

        $this->forget();

        return $outcome;
    }

    /** Drop memoised lookups. Called after every write, and by tests. */
    public function forget(): void
    {
        $this->memo = [];
    }

    private function requireTables(): void
    {
        if (! $this->available()) {
            throw new RuntimeException(
                'The PAL flow tables are not present on this connection. Run '
                . 'database/migrations/2026_09_21_100000_create_pal_flow_profile_tables.php '
                . '(with --path, since this estate carries hundreds of unrelated pending migrations).'
            );
        }
    }

    /**
     * @param  mixed  $raw
     * @return array<string,mixed>
     */
    private function decode($raw, int $versionId): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            // Loud, because a version that cannot be read is a learner who
            // cannot be served, and silently falling back to config would hide
            // that a pinned learner is now running a different flow.
            throw new RuntimeException(
                "Flow version {$versionId} holds a definition that is not valid JSON."
            );
        }

        return $decoded;
    }
}
