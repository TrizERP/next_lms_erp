<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Users module: who has an account in this ERP, and what state it is in.
 *
 * THIS IS A DIFFERENT VIEW OF `tbluser` FROM THE ONE User I-Card READS
 *
 * Both modules sit on the same table and neither may drift into the other:
 *
 *   · `UserIcardService` returns the fields an identity CARD prints — name, photo,
 *     employee number, profile, department.
 *   · this returns the fields an ACCOUNT has — profile, status, whether the person is a
 *     portal user or an administrator, when they joined, when the account expires, and
 *     when they last logged in.
 *
 * They overlap on name and profile, because both a card and an account have those. What
 * matters is that neither reaches wider, and the explicit column list below is what
 * enforces it.
 *
 * THE COLUMN LIST IS THE ACCESS CONTROL. DO NOT WIDEN IT.
 *
 * `tbluser` carries `bank_name`, `account_no`, `ifsc_code`, `amount`, `per_hours_amount`,
 * `pan_no`, `aadhar_no`, `pf_no`, `esic_no`, `uan_no`, every `*_deduction`,
 * `plain_password`, `password`, `termination_reason` and `noticereason`. None of them
 * appears in `columns()` and none may be added. A question about who has an account must
 * never be a way to read a colleague's salary.
 *
 * `last_login` IS THE ONLY ACTIVITY THIS TABLE RECORDS
 *
 * There is no session log, no page view, no action history on the user record — one
 * timestamp. So "who is active" can be answered only as "who has logged in, and when it
 * was last recorded", and never as how much anybody uses the system. A null `last_login`
 * means no login has been RECORDED against the account, which is not the same as the
 * person never having used it: the column may simply predate being populated.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read. The profile lookup is joined
 * on institute too.
 */
class UserAccountService
{
    /**
     * Accounts in this institute.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function directory(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('tbluser')) {
            return ['count' => 0, 'accounts' => [], 'note' => 'User accounts are not held in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        foreach (['user_profile_id' => 'u.user_profile_id', 'department_id' => 'u.department_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        // Inactive accounts are excluded by default, because "how many users do we have"
        // means working accounts. Asking for them is explicit.
        if (empty($filters['include_inactive'])) {
            $query->where('u.status', 1);
        }

        if (! empty($filters['never_logged_in_only'])) {
            $query->where(static function ($inner): void {
                $inner->whereNull('u.last_login')->orWhereRaw("TRIM(u.last_login) = ''");
            });
        }

        if (! empty($filters['administrators_only'])) {
            $query->where('u.is_admin', 1);
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('u.first_name', 'like', $needle)
                    ->orWhere('u.last_name', 'like', $needle)
                    ->orWhere('u.user_name', 'like', $needle)
                    ->orWhere('u.email', 'like', $needle);
            });
        }

        // Every figure over the whole filtered set, like `count`.
        $total = (clone $query)->count();
        $neverLoggedIn = (clone $query)
            ->where(static function ($inner): void {
                $inner->whereNull('u.last_login')->orWhereRaw("TRIM(u.last_login) = ''");
            })
            ->count();
        $administrators = (clone $query)->where('u.is_admin', 1)->count();

        $byProfile = (clone $query)
            ->selectRaw("COALESCE(NULLIF(TRIM(p.name), ''), '(profile not in this institute)') AS profile, COUNT(*) AS accounts")
            ->groupByRaw("COALESCE(NULLIF(TRIM(p.name), ''), '(profile not in this institute)')")
            ->orderByDesc('accounts')
            ->limit(25)
            ->get()
            ->map(static fn ($row) => ['profile' => $row->profile, 'accounts' => (int) $row->accounts])
            ->all();

        $rows = $query
            ->selectRaw($this->columns())
            ->orderBy('u.first_name')
            ->orderBy('u.last_name')
            ->limit($limit)
            ->get();

        return [
            'count' => $total,
            'row_count' => $rows->count(),
            'never_logged_in' => $neverLoggedIn,
            'administrators' => $administrators,
            'by_profile' => $byProfile,
            'includes_inactive' => ! empty($filters['include_inactive']),
            'figures_cover' => 'every account matching these filters, not only the rows listed',
            'accounts' => $rows->map(fn ($row) => $this->map($row))->all(),
            'rule' => $this->rule(),
        ];
    }

    /** The rule every Users answer carries. */
    private function rule(): string
    {
        return 'One row is one ERP ACCOUNT in this institute. Only account fields are readable: name, '
            .'username, profile, department, status, whether the account is an administrator or a portal '
            .'user, and the join, expiry and last-login dates. The staff record also holds salary, bank, '
            .'PAN, Aadhaar, provident fund and contract details — this tool cannot return any of them and '
            .'you must never refer to, estimate or ask about them. `last_login` is the ONLY activity this '
            .'table records: there is no session log, no page view and no action history, so never say '
            .'how much anybody uses the system, who is most active, or who is not doing their work. A '
            .'null last login means no login has been RECORDED, which is not proof the person has never '
            .'signed in.';
    }

    /** The user query, scoped at every hop that carries an institute. */
    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        return DB::table('tbluser as u')
            ->leftJoin('tbluserprofilemaster as p', function ($join) use ($institute) {
                $join->on('p.id', '=', 'u.user_profile_id')->where('p.sub_institute_id', '=', $institute);
            })
            ->where('u.sub_institute_id', $institute);
    }

    /**
     * The account's fields, and nothing else.
     *
     * READ THE CLASS NOTE BEFORE ADDING A COLUMN HERE.
     */
    private function columns(): string
    {
        return "u.id AS user_id, u.user_name, u.email, u.mobile, u.status, u.is_admin, u.portal_user,
                u.user_profile_id, u.department_id, u.employee_no, u.joined_date, u.expire_date,
                u.last_login, u.created_on,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS user_name_full,
                p.name AS user_profile";
    }

    /** @return array<string, mixed> */
    private function map(object $row): array
    {
        $lastLogin = trim((string) ($row->last_login ?? ''));
        $expiry = trim((string) ($row->expire_date ?? ''));
        // '0000-00-00' is this schema's way of saying nothing was entered.
        $expiryRecorded = $expiry !== '' && ! str_starts_with($expiry, '0000-00-00');

        return [
            'user_id' => (int) $row->user_id,
            'name' => trim((string) ($row->user_name_full ?? '')) ?: null,
            'username' => $row->user_name ?: null,
            'email' => $row->email ?: null,
            'mobile' => $row->mobile ?: null,
            'employee_no' => $row->employee_no ?: null,
            'user_profile_id' => $row->user_profile_id === null ? null : (int) $row->user_profile_id,
            // Null when the profile belongs to another institute — a record to correct,
            // not a name to borrow from elsewhere.
            'user_profile' => $row->user_profile,
            'department_id' => $row->department_id === null ? null : (int) $row->department_id,
            'active' => (int) ($row->status ?? 0) === 1,
            'is_administrator' => ! empty($row->is_admin),
            'portal_user' => ! empty($row->portal_user),
            'joined_date' => $row->joined_date ?: null,
            'account_expiry_date' => $expiryRecorded ? $expiry : null,
            // The only activity on this table. Null means no login has been RECORDED.
            'last_login' => $lastLogin !== '' ? $lastLogin : null,
            'last_login_recorded' => $lastLogin !== '',
            'account_created_on' => $row->created_on ?: null,
        ];
    }
}
