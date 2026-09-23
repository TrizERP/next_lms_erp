<?php

namespace App\Services\Mcp;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The student identity-card roster, and what one card would print.
 *
 * THERE IS NO I-CARD TABLE, AND THAT IS THE POINT OF THIS SERVICE
 *
 * `studentIcardController` builds a card from the enrolment record plus, when the school
 * uses it, the child's transport mapping — there is no `student_icard` table anywhere in
 * this schema and no issue history. So the module's records are "the students a card can
 * be printed for, and the fields that card carries".
 *
 * WHICH IS WHY THE COLUMN LIST IS SHORT AND FIXED
 *
 * Only the fields a card actually prints: name, enrolment and roll number, class, photo,
 * blood group, date of birth, the parents' names, a contact number, and the bus and stop
 * when transport is mapped. Nothing else is read.
 *
 * This is deliberate and it is the module boundary. The Student module has its own AI
 * Stack, its own tools and its own rights; binding `students.directory` here as well would
 * make "show me the I-card list" a second, ungoverned route into the student directory —
 * one that returns admission dates, addresses and quota codes to anybody who can print a
 * card. A card needs a face, a name and a class. It does not need a file.
 *
 * NO CARD IS ISSUED, NUMBERED OR RECORDED HERE
 *
 * The service reads. Printing a card is what the I-card screen does, and nothing in this
 * estate records that it happened — so no read below reports an issue date, a card number
 * or a reprint count, because none exists.
 *
 * SCOPING
 *
 * `tblstudent.sub_institute_id` and `tblstudent_enrollment.sub_institute_id` both matched
 * against the caller's token, and `syear` against the caller's academic year, which is the
 * same pair `SearchStudent()` filters on. An enrolment that has ended is excluded exactly
 * as the helper excludes it.
 */
class StudentIcardService
{
    /**
     * The students a card can be printed for, in roll-number order within a class.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function roster(McpRequestContext $context, array $filters): array
    {
        if (! Schema::hasTable('tblstudent') || ! Schema::hasTable('tblstudent_enrollment')) {
            return ['count' => 0, 'students' => [], 'note' => 'Student enrolment is not recorded in this estate.'];
        }

        $limit = min(max((int) ($filters['limit'] ?? 50), 1), 200);
        $query = $this->query($context);

        foreach ([
            'grade_id' => 'e.grade_id',
            'standard_id' => 'e.standard_id',
            'division_id' => 'e.section_id',
            'student_id' => 's.id',
        ] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, (int) $filters[$filter]);
            }
        }

        if (! empty($filters['search_text'])) {
            $needle = '%'.$filters['search_text'].'%';
            $query->where(function ($inner) use ($needle) {
                $inner->where('s.first_name', 'like', $needle)
                    ->orWhere('s.last_name', 'like', $needle)
                    ->orWhere('s.enrollment_no', 'like', $needle);
            });
        }

        // Only those with transport mapped, for a card run that prints bus details.
        if (! empty($filters['with_transport'])) {
            $query->whereNotNull('tm.id');
        }

        // Counted before the limit, so a page of fifty is never read as the whole cohort.
        $total = (clone $query)->count();

        $rows = $query
            ->selectRaw($this->columns())
            ->orderBy('std.name')
            ->orderBy('div.name')
            ->orderByRaw('CAST(e.roll_no AS UNSIGNED)')
            ->limit($limit)
            ->get();

        $printable = 0;
        $students = [];

        foreach ($rows as $row) {
            $card = $this->map($row);

            if ($card['card_ready']) {
                $printable++;
            }

            $students[] = $card;
        }

        return [
            'count' => $total,
            'row_count' => count($students),
            'academic_year' => $context->academicYear,
            'card_ready' => $printable,
            'missing_something' => count($students) - $printable,
            'students' => $students,
            'rule' => 'Only the fields an identity card prints are read. `card_ready` is false when a '
                .'photo, a roll number or a class is missing — a detail the school has not recorded, not '
                .'a student who may not have a card.',
        ];
    }

    /**
     * One student's card fields in full.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function cardDetails(McpRequestContext $context, array $arguments): array
    {
        if (! Schema::hasTable('tblstudent')) {
            return ['found' => false, 'note' => 'Student records are not held in this estate.'];
        }

        $studentId = (int) ($arguments['student_id'] ?? 0);

        if ($studentId < 1) {
            return ['found' => false, 'note' => 'A student id is required.'];
        }

        $row = $this->query($context)->where('s.id', $studentId)->selectRaw($this->columns())->first();

        if ($row === null) {
            // The same answer for "no such student" and "a student of another institute or
            // another year": confirming the id exists elsewhere is itself a disclosure.
            return [
                'found' => false,
                'student_id' => $studentId,
                'note' => 'No student with that id is enrolled in this institute for this academic year.',
            ];
        }

        $card = $this->map($row);

        return [
            'found' => true,
            'card' => $card,
            'card_ready' => $card['card_ready'],
            'missing_fields' => $card['missing_fields'],
            'rule' => 'These are the fields an identity card prints and nothing else. No card number, '
                .'issue date or reprint history is reported, because this estate records none.',
        ];
    }

    /**
     * The enrolment join every read uses.
     *
     * The transport tables are left joins: a school that does not run buses still prints
     * cards, and an inner join would silently empty the roster.
     */
    private function query(McpRequestContext $context): Builder
    {
        $institute = $context->selectedInstituteId;

        $query = DB::table('tblstudent as s')
            ->join('tblstudent_enrollment as e', function ($join) use ($institute) {
                $join->on('e.student_id', '=', 's.id')
                    ->where('e.sub_institute_id', '=', $institute);
            })
            ->leftJoin('standard as std', 'std.id', '=', 'e.standard_id')
            ->leftJoin('division as div', 'div.id', '=', 'e.section_id')
            ->leftJoin('academic_section as grade', 'grade.id', '=', 'e.grade_id')
            ->leftJoin('transport_map_student as tm', function ($join) use ($institute) {
                $join->on('tm.student_id', '=', 's.id')
                    ->where('tm.sub_institute_id', '=', $institute);
            })
            ->leftJoin('transport_vehicle as tv', function ($join) use ($institute) {
                $join->on('tv.id', '=', 'tm.from_bus_id')
                    ->where('tv.sub_institute_id', '=', $institute);
            })
            ->where('s.sub_institute_id', $institute)
            // An enrolment that has ended is not a card to print, which is the rule
            // `SearchStudent()` applies.
            ->whereNull('e.end_date');

        if ($context->academicYear !== null) {
            $query->where('e.syear', $context->academicYear);
        }

        return $query;
    }

    /** The card's fields, and nothing else. See the note at the top about why it is short. */
    private function columns(): string
    {
        return "s.id AS student_id, s.enrollment_no, s.admission_id, s.image, s.bloodgroup, s.dob,
                s.gender, s.mobile, s.father_name, s.mother_name,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                e.roll_no, e.standard_id, e.section_id, e.grade_id,
                std.name AS standard_name, div.name AS division_name, grade.title AS grade_name,
                tm.from_stop, tm.to_stop, tv.title AS bus_title, tv.vehicle_number";
    }

    /**
     * One row as a card.
     *
     * `missing_fields` names what would print blank. A card with no photo is a card the
     * office has to chase a photo for, and saying which field is missing is the difference
     * between a usable report and a list of names.
     *
     * @return array<string, mixed>
     */
    private function map(object $row): array
    {
        $photo = trim((string) $row->image);
        $roll = trim((string) $row->roll_no);
        $standard = trim((string) $row->standard_name);

        $missing = [];

        if ($photo === '') {
            $missing[] = 'photo';
        }

        if ($roll === '') {
            $missing[] = 'roll number';
        }

        if ($standard === '') {
            $missing[] = 'class';
        }

        return [
            'student_id' => (int) $row->student_id,
            'student_name' => trim((string) $row->student_name) ?: null,
            'enrollment_no' => $row->enrollment_no,
            'admission_id' => $row->admission_id,
            'roll_no' => $roll === '' ? null : $roll,
            'standard_id' => $row->standard_id === null ? null : (int) $row->standard_id,
            'standard_name' => $standard === '' ? null : $standard,
            'division_name' => $row->division_name,
            'grade_name' => $row->grade_name,
            'photo' => $photo === '' ? null : $photo,
            'blood_group' => trim((string) $row->bloodgroup) ?: null,
            'date_of_birth' => $row->dob,
            'gender' => $row->gender,
            'contact' => $row->mobile,
            'father_name' => $row->father_name,
            'mother_name' => $row->mother_name,
            // Only present when the school maps transport. Null is "no bus", not "unknown".
            'bus' => $row->bus_title,
            'bus_number' => $row->vehicle_number,
            'pickup_stop' => $row->from_stop,
            'drop_stop' => $row->to_stop,
            'card_ready' => $missing === [],
            'missing_fields' => $missing,
        ];
    }
}
