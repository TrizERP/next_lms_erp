<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The staff identity-card list, as `tbluser` records the people on it.
 *
 * WHAT A USER I-CARD IS, AND WHAT IT IS NOT
 *
 * The User I-Card screen prints a card for a member of staff: a name, a photograph, a
 * profile and an employee number. It is the staff counterpart of the Student I-Card
 * module, which reads `tblstudent` and is bound to different tools entirely. Neither
 * module can reach the other's table.
 *
 * THE COLUMN LIST IS THE ACCESS CONTROL. DO NOT WIDEN IT.
 *
 * `tbluser` is the staff master record and carries payroll and identity data that has
 * nothing to do with a card: `bank_name`, `account_no`, `ifsc_code`, `amount`,
 * `per_hours_amount`, `pan_no`, `aadhar_no`, `pf_no`, `esic_no`, `uan_no`, every
 * `*_deduction`, `plain_password`, `password`, `termination_reason`, `noticereason`. None
 * of them appears in `columns()` below and none may be added.
 *
 * This is not a stylistic preference. A tool that selected `*` would hand a model a
 * teacher's bank account because somebody asked whose card needs printing, and no prompt
 * rule downstream could put that back. The restriction lives here, in the query, where
 * nothing downstream can widen it.
 *
 * `expire_date` IS AN ACCOUNT EXPIRY, NOT A CARD EXPIRY
 *
 * The obvious question — "show users whose I-cards are expired" — has no column behind
 * it. This estate records no card issue date, no card expiry date and no print history
 * anywhere. What `tbluser.expire_date` records is when the person's ERP ACCOUNT is set to
 * lapse, which the office often does set to the end of a contract and often leaves at a
 * far-future default.
 *
 * So `account_expired` is reported under that name, with the date beside it, and the rule
 * on every payload says what it is. Renaming it `card_expired` would be one word of
 * convenience bought with a false fact about a person's identity document.
 *
 * STUDENTS ARE EXCLUDED, THE SAME WAY THE SCREEN EXCLUDES THEM
 *
 * `teacherIcardController::teacher_types()` lists profiles `WHERE name <> 'Student'`, and
 * this does the same. A student's card comes from the Student I-Card module, from the
 * student record, with the student's own fields.
 *
 * THE SUBJECT ARGUMENT IS `staff_id`, NEVER `user_id`
 *
 * A tool argument called `user_id` reads as "act as this user", which is the shape of an
 * argument that chooses its own scope rather than taking it from the token.
 * `McpSecurityStackTest` forbids that name on any tool, beside `institute_id` and
 * `client_id`, and it was right to catch this one. What this module needs is the SUBJECT
 * of the read, and `staff_id` can only mean that.
 *
 * SCOPING
 *
 * `sub_institute_id` from the caller's token on every read. The two lookups —
 * `tbluserprofilemaster` and `hrms_departments` — are joined on institute as well, so a
 * profile or department name from another school can never be printed onto this school's
 * card, even if an id collides.
 */
class UserIcardService
{
    /**
     * The staff a card can be printed for.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function roster(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('tbluser')) {
            return ['count' => 0, 'users' => [], 'note' => 'Staff records are not held in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        foreach (['user_profile_id' => 'u.user_profile_id', 'department_id' => 'u.department_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        $search = trim((string) ($filters['search_text'] ?? ''));

        if ($search !== '') {
            $needle = '%'.$search.'%';
            $query->where(static function ($inner) use ($needle): void {
                $inner->where('u.first_name', 'like', $needle)
                    ->orWhere('u.last_name', 'like', $needle)
                    ->orWhere('u.employee_no', 'like', $needle);
            });
        }

        // The account expiry recorded on the staff record, NOT a card expiry. See the note
        // at the top of this class.
        if (! empty($filters['account_expired_only'])) {
            $query->whereNotNull('u.expire_date')
                ->where('u.expire_date', '<>', '0000-00-00')
                ->whereDate('u.expire_date', '<', now()->toDateString());
        }

        if (! empty($filters['missing_photo_only'])) {
            $query->where(static function ($inner): void {
                $inner->whereNull('u.image')->orWhere('u.image', '');
            });
        }

        // Counted before the limit, so a page of fifty is never read as the whole staff.
        //
        // The readiness and expiry figures are counted over the SAME whole set. A school
        // with four hundred staff and two cards ready must not be told "2 ready" beside a
        // page of three, which is what a page-scoped tally does.
        $total = (clone $query)->count();

        $ready = (clone $query)
            ->whereRaw("TRIM(CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name)) <> ''")
            ->whereNotNull('u.image')->where('u.image', '<>', '')
            ->whereNotNull('u.employee_no')->whereRaw("TRIM(u.employee_no) <> ''")
            ->whereNotNull('p.name')->whereRaw("TRIM(p.name) <> ''")
            ->count();

        $expired = (clone $query)
            ->whereNotNull('u.expire_date')
            ->where('u.expire_date', '<>', '0000-00-00')
            ->whereDate('u.expire_date', '<', now()->toDateString())
            ->count();

        $rows = $query
            ->selectRaw($this->columns())
            ->orderBy('u.first_name')
            ->orderBy('u.last_name')
            ->limit($limit)
            ->get();

        $users = $rows->map(fn ($row) => $this->map($row))->all();

        return [
            'count' => $total,
            'row_count' => count($users),
            'card_ready' => $ready,
            'missing_something' => $total - $ready,
            'account_expired' => $expired,
            'figures_cover' => 'every member of staff matching these filters, not only the rows listed',
            'users' => $users,
            'rule' => 'Only the fields a staff identity card prints are read — never payroll, bank, PAN, '
                .'Aadhaar or contract details, which this tool cannot return at all. `card_ready` is false '
                .'when a photograph, an employee number or a profile is missing: a detail the office has '
                .'not recorded, not a person who may not have a card. `account_expired` is the ERP ACCOUNT '
                .'expiry date on the staff record. This estate records NO card issue date, NO card expiry '
                .'and NO print history, so nothing here may state that a CARD has expired or is due for '
                .'renewal.',
        ];
    }

    /**
     * One member of staff's card fields in full.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function cardDetails(McpRequestContext $context, array $arguments): array
    {
        if (! Schema::hasTable('tbluser')) {
            return ['found' => false, 'note' => 'Staff records are not held in this estate.'];
        }

        // `staff_id`, NOT `user_id`. A tool argument named `user_id` reads as "act as
        // this user", which is the shape of an argument that chooses its own scope, and
        // `McpSecurityStackTest::test_no_tool_accepts_a_tenant_argument` forbids the name
        // outright beside `institute_id` and `client_id`. What this needs is the SUBJECT
        // of the read — whose card to print — and `staff_id` can only mean that, the same
        // way `student_id` does on the medical and consent tools.
        $staffId = (int) ($arguments['staff_id'] ?? 0);

        if ($staffId < 1) {
            return ['found' => false, 'note' => 'A staff id is required.'];
        }

        // Scoped exactly as the roster is, so a detail read cannot reach a user this
        // caller's institute does not employ.
        $row = $this->query($context)->where('u.id', $staffId)->selectRaw($this->columns())->first();

        if ($row === null) {
            return [
                'found' => false,
                'staff_id' => $staffId,
                // The same answer for "not employed here" as for "does not exist", so an id
                // probe discloses nothing about another school's staff.
                'note' => 'No card-printable staff record with that id in this institute.',
            ];
        }

        return [
            'found' => true,
            'card' => $this->map($row),
            'rule' => 'These are the fields the card prints and nothing else. Payroll, bank and government '
                .'identity numbers are not readable through this tool.',
        ];
    }

    /**
     * The staff query, scoped at every hop that carries an institute.
     *
     * Active staff only, and never a Student profile — the rule the I-Card screen itself
     * applies.
     */
    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table('tbluser as u')
            ->leftJoin('tbluserprofilemaster as p', function ($join) use ($institute) {
                $join->on('p.id', '=', 'u.user_profile_id')->where('p.sub_institute_id', '=', $institute);
            })
            ->where('u.sub_institute_id', $institute)
            ->where('u.status', 1)
            // `teacherIcardController::teacher_types()` lists profiles WHERE name <> 'Student'.
            // A student's card is the Student I-Card module's, from the student record.
            ->where(static function ($inner): void {
                $inner->whereNull('p.name')->orWhere('p.name', '<>', 'Student');
            });

        if (Schema::hasTable('hrms_departments')) {
            $query->leftJoin('hrms_departments as d', function ($join) use ($institute) {
                $join->on('d.id', '=', 'u.department_id')->where('d.sub_institute_id', '=', $institute);
            });
        }

        return $query;
    }

    /**
     * The card's fields, and nothing else.
     *
     * READ THE CLASS NOTE BEFORE ADDING A COLUMN HERE. `tbluser` carries payroll, bank and
     * government identity numbers, and this list is the only thing keeping them out.
     */
    private function columns(): string
    {
        $department = Schema::hasTable('hrms_departments') ? 'd.department AS department_name' : 'NULL AS department_name';

        return "u.id AS staff_id, u.employee_no, u.employee_id, u.image, u.gender, u.birthdate,
                u.mobile, u.email, u.joined_date, u.expire_date, u.user_profile_id, u.department_id,
                CONCAT_WS(' ', u.first_name, u.middle_name, u.last_name) AS user_name,
                p.name AS user_profile,
                {$department}";
    }

    /** @return array<string, mixed> */
    private function map(object $row): array
    {
        $photo = trim((string) ($row->image ?? ''));
        $employeeNo = trim((string) ($row->employee_no ?? ''));
        $name = trim((string) ($row->user_name ?? ''));
        $profile = trim((string) ($row->user_profile ?? ''));

        $expiry = trim((string) ($row->expire_date ?? ''));
        // '0000-00-00' is this schema's way of saying nothing was entered, and reading it
        // as a date in the year zero would report every such person as long expired.
        $expiryRecorded = $expiry !== '' && $expiry !== '0000-00-00';

        return [
            'staff_id' => (int) $row->staff_id,
            'user_name' => $name ?: null,
            'employee_no' => $employeeNo ?: null,
            'employee_id' => $row->employee_id ?: null,
            'user_profile_id' => $row->user_profile_id === null ? null : (int) $row->user_profile_id,
            // Null when the profile belongs to another institute — a record to correct,
            // not a name to borrow from elsewhere.
            'user_profile' => $profile ?: null,
            'department_id' => $row->department_id === null ? null : (int) $row->department_id,
            'department' => $row->department_name ?: null,
            'gender' => $row->gender ?: null,
            'date_of_birth' => $row->birthdate ?: null,
            'mobile' => $row->mobile ?: null,
            'email' => $row->email ?: null,
            'joined_date' => $row->joined_date ?: null,
            // Whether a photograph is on file, never the file itself.
            'photo_present' => $photo !== '',
            // The ACCOUNT expiry from the staff record. Not a card expiry — none is recorded
            // anywhere in this estate.
            'account_expiry_date' => $expiryRecorded ? $expiry : null,
            'account_expired' => $expiryRecorded ? ($expiry < now()->toDateString()) : null,
            'card_ready' => $name !== '' && $photo !== '' && $employeeNo !== '' && $profile !== '',
        ];
    }
}
