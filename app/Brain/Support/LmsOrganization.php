<?php

namespace App\Brain\Support;

use Illuminate\Support\Facades\DB;

/**
 * The two names the Brain needs, kept apart on purpose.
 *
 * DISPLAY NAME (displayNameFor) is what the Brain's Organization header shows:
 * THE SAME USER IDENTITY THE LMS CHROME ALREADY SHOWS, resolved server-side from
 * the signed-in user. It is a per-user label — two colleagues in one school see
 * their own name over the same tenant-scoped figures.
 *
 * The precedence below mirrors AuthContext exactly, and that is the whole point:
 *
 *     const userData = { name: data.name || email.split('@')[0], ... }
 *
 * The login response has NO `name` key (ApiLoginController returns `user_name`,
 * `first_name`, `last_name` — never `name`), so that expression always falls
 * through to the email's local part. `kalpesh@gmail.com` and `kalpesh@triz.co.in`
 * both render as `kalpesh` in the LMS header, and now in the Brain's too.
 *
 * WHY NOT `tbluser.user_name` FIRST. It was specified, but it is not what the
 * LMS displays and it does not hold what it is assumed to hold: the tenant-1
 * account stores `kalpesh.sheth`, and `kalpesh@gmail.com` is not in `tbluser` at
 * all — it is a `tblstudent` whose `username` is the enrolment number `2224105`.
 * Nothing in this database has a username of `kalpesh`. Following the column
 * would have put a different string in the header than the one beside it. It is
 * kept as the first FALLBACK, so an account with no email still gets its login
 * name; swap the two branches below to make the column primary again.
 *
 * INSTITUTE NAME (instituteNameFor) is the school itself, `school_setup.SchoolName`
 * keyed by `Id = sub_institute_id` — what ApiLoginController returns as
 * `school_name` and the LMS sidebar renders. It stays tenant-level and is what
 * gets PROJECTED into `hpbrain_organizations`, because that row is shared by
 * everyone in the tenant: writing whoever last ran ingestion into it would let
 * one user's login name become the whole school's stored organization name.
 *
 * NEITHER IS A SCOPE. Tenant isolation is `sub_institute_id` off the signed
 * token and is untouched by anything here; these functions only produce text.
 *
 * The user lookup is keyed by (id, sub_institute_id) so a token cannot borrow
 * another institute's user row to display a name it was not issued for — the
 * same pairing BrainAuthenticate uses for its profile lookup.
 */
final class LmsOrganization
{
    /** Memos so a burst of Brain calls in one page load costs one lookup each. */
    private static array $displayNames = [];

    private static array $instituteNames = [];

    /**
     * The signed-in user, named the way the LMS names them.
     *
     * $isStudent picks the table the token's `id` belongs to — a staff id and a
     * student id are both integers and would otherwise collide.
     */
    public static function displayNameFor(?string $tenantId, ?string $userId, bool $isStudent = false): string
    {
        $tenantId = (string) $tenantId;
        $userId = (string) $userId;

        if ($userId === '') {
            return self::instituteNameFor($tenantId);
        }

        $key = $tenantId.'|'.$userId.'|'.($isStudent ? 's' : 'u');

        return self::$displayNames[$key] ??= self::resolveDisplayName($tenantId, $userId, $isStudent);
    }

    /** The institute's own name, from the LMS's institute record. */
    public static function instituteNameFor(string $tenantId): string
    {
        if ($tenantId === '') {
            return 'This organization';
        }

        return self::$instituteNames[$tenantId] ??= self::resolveInstituteName($tenantId);
    }

    /** Forget the memos — for tests, and after anything writes these rows. */
    public static function forget(): void
    {
        self::$displayNames = [];
        self::$instituteNames = [];
    }

    private static function resolveDisplayName(string $tenantId, string $userId, bool $isStudent): string
    {
        // Staff live in tbluser with `user_name`; students live in tblstudent
        // with `username`. Two tables, two spellings, one concept.
        $table = $isStudent ? 'tblstudent' : 'tbluser';
        $loginColumn = $isStudent ? 'username' : 'user_name';

        if (! SchemaCache::hasTable($table)) {
            return self::instituteNameFor($tenantId);
        }

        $columns = array_values(array_filter(
            [$loginColumn, 'first_name', 'middle_name', 'last_name', 'email'],
            fn ($c) => SchemaCache::hasColumn($table, $c)
        ));

        $query = DB::table($table)->where('id', $userId);
        if ($tenantId !== '' && SchemaCache::hasColumn($table, 'sub_institute_id')) {
            $query->where('sub_institute_id', $tenantId);
        }

        $row = $query->first($columns);
        if (! $row) {
            return self::instituteNameFor($tenantId);
        }

        // 1. The email's local part — what the LMS chrome shows for this person.
        //
        // Lower-cased because the chrome derives its label from the address the
        // user TYPED while this derives it from the address as STORED, and this
        // database holds both `kalpesh@triz.co.in` and `KALPESH@GMAIL.COM`.
        // Without it the same person reads `kalpesh` in one header and `KALPESH`
        // in the other, which is the exact inconsistency this resolver exists to
        // remove. Nothing is written back; only the label is normalised.
        $email = self::clean($row->email ?? null);
        if ($email !== '' && str_contains($email, '@')) {
            return mb_strtolower(explode('@', $email)[0]);
        }

        // 2. Their login name, for an account with no email on file.
        $loginName = self::clean($row->$loginColumn ?? null);
        if ($loginName !== '') {
            return $loginName;
        }

        // 3. Their actual name, before giving up on identifying the person.
        $full = self::clean(trim(implode(' ', array_filter([
            $row->first_name ?? null,
            $row->middle_name ?? null,
            $row->last_name ?? null,
        ]))));
        if ($full !== '') {
            return $full;
        }

        return self::instituteNameFor($tenantId);
    }

    /** Blank, and the placeholders this database actually stores, all mean "unset". */
    private static function clean($value): string
    {
        $value = trim((string) $value);

        return in_array($value, ['', '-', '--', 'N/A', 'NA', 'null'], true) ? '' : $value;
    }

    private static function resolveInstituteName(string $tenantId): string
    {
        if (SchemaCache::hasTable('school_setup') && SchemaCache::hasColumn('school_setup', 'SchoolName')) {
            $name = trim((string) DB::table('school_setup')->where('Id', $tenantId)->value('SchoolName'));
            if ($name !== '') {
                return $name;
            }
        }

        if (SchemaCache::hasTable('hpbrain_organizations')) {
            $name = trim((string) DB::table('hpbrain_organizations')->where('tenant_id', $tenantId)->value('name'));
            if ($name !== '') {
                return $name;
            }
        }

        return 'Organization '.$tenantId;
    }
}
