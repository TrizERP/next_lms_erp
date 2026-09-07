<?php

/**
 * Module manifest for the k12-style Neo4j scripts.
 * ---------------------------------------------------------------------------
 *
 * One entry per module in `database/neo4j/cypher/`. `neo4j:csv-export` reads the `csv`
 * block; `neo4j:cypher` reads `file` and `extends`.
 *
 *   file     the .cypher script, relative to database/neo4j/cypher/
 *   extends  protected relationship types this module is ALLOWED to grow. Anything else
 *            moving is treated as a failure — see CypherRunCommand::PROTECTED_RELS. Only
 *            the two enrolment edges qualify: they are the k12 statements verbatim, run
 *            over the whole source table instead of the 5,589-row subset.
 *   csv      csvName => SQL. The .cypher file references the name only
 *            (`LOAD CSV WITH HEADERS FROM 'file:///<name>.csv'`), so the same script runs
 *            on the Neo4j host against server-side CSVs and from here over Bolt.
 *
 * SQL CONVENTIONS
 *  - Aggregate tables (decision AGG_EDGE) are grouped HERE, not in Cypher: the CSV is
 *    already one row per edge. `lms_online_exam_answer` alone is 2.4M rows and 24k edges,
 *    so grouping in SQL is also what keeps the export off the wire.
 *  - A CSV whose name ends `_agg` is aggregated; the raw table keeps its own name so an
 *    aggregate can never shadow a raw copy already sitting in the server's import dir.
 *  - Parent ids that the graph keys on (`academic_year_id`) are resolved in SQL, because
 *    the :AcademicYear uid is `AcademicYear:<tenant>:<syear>:<id>` and the id cannot be
 *    derived from a (tenant, syear) pair — there are 2-4 term rows per year.
 *  - Columns are listed explicitly rather than `SELECT *`: it keeps credentials and
 *    unused blobs out of the export, and a schema change then fails loudly here instead
 *    of silently adding a property.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | people — finish what the k12 script started
    |--------------------------------------------------------------------------
    |
    | The reference script loaded :Student from a 5,409-row CSV; the table holds
    | 176,458 enrolments. Everything here either completes that or hangs student
    | facts off :StuDetail.
    |
    | Student-keyed tables attach to :StuDetail {sdId}, NOT :Student {stuId}.
    | Measured 2026-09-03: `student_optional_subject` 99.9%, `dicipline` 100%,
    | `result_student_attendance_master` 99.5% against `tblstudent.id`, and 22-33%
    | against `tblstudent_enrollment.id`. A graph property called `student_id` in
    | this schema is the master id, not the enrolment id.
    */
    'people' => [
        'file'    => '10_people.cypher',
        'extends' => ['HAS_STUDENT', 'ENROLLED_IN'],
        'csv' => [

            // Every enrolment. `MERGE (:Student {stuId})` matches the 5,589 already
            // present and creates the rest; ON CREATE SET means the existing ones keep
            // every property they have.
            'tblstudent_enrollment' => "
                SELECT id, syear, student_id, roll_no, grade_id, standard_id, section_id,
                       student_quota, house_id, term_id, enrollment_code, start_date, end_date,
                       sub_institute_id
                FROM tblstudent_enrollment",

            // One row per (student, subject, year) already — 103,680 rows, 103,680 groups.
            // Grouped anyway so a future duplicate cannot fan the edge out.
            'student_optional_subject_agg' => "
                SELECT student_id, subject_id, syear, sub_institute_id,
                       MAX(level) AS level, COUNT(*) AS rows_merged
                FROM student_optional_subject
                WHERE student_id > 0 AND subject_id > 0
                GROUP BY student_id, subject_id, syear, sub_institute_id",

            // `dicipline.dicipline` is FREE TEXT ('Check' 13,038, '' 4,029, 'Bad', 'good'),
            // not a foreign key to dicipline_master — 0 of 19,642 rows resolve to an id.
            // So the category travels as a property on the edge and the incident attaches
            // to the academic year, not to a :DisciplineCategory node.
            // academic_year: MIN(id) per (tenant, syear) — a year has 2-4 term rows and a
            // discipline row names no term.
            'dicipline_agg' => "
                SELECT d.student_id, d.syear, d.sub_institute_id,
                       TRIM(COALESCE(d.dicipline, '')) AS category,
                       COUNT(*) AS incidents,
                       MIN(d.date_) AS first_date, MAX(d.date_) AS last_date,
                       (SELECT MIN(a.id) FROM academic_year a
                         WHERE a.sub_institute_id = d.sub_institute_id AND a.syear = d.syear) AS academic_year_id
                FROM dicipline d
                WHERE d.student_id > 0
                GROUP BY d.student_id, d.syear, d.sub_institute_id, TRIM(COALESCE(d.dicipline, ''))",

            // `siblings_id` is a comma-separated list of tblstudent ids, not a single FK.
            'tblstudent_siblings' => "
                SELECT id, siblings_id, sub_institute_id FROM tblstudent_siblings",

            // Term-grain attendance. The (tenant, syear, term_id) join to academic_year
            // resolves 53,035 of 53,035 — an exact term node exists for every row.
            'result_student_attendance_master_agg' => "
                SELECT m.student_id, m.syear, m.term_id, m.sub_institute_id, ay.id AS academic_year_id,
                       MAX(m.attendance) AS attendance, MAX(m.working_day) AS working_day,
                       MAX(m.percentage) AS percentage, COUNT(*) AS rows_merged
                FROM result_student_attendance_master m
                JOIN academic_year ay
                  ON ay.sub_institute_id = m.sub_institute_id AND ay.syear = m.syear AND ay.term_id = m.term_id
                WHERE m.student_id > 0
                GROUP BY m.student_id, m.syear, m.term_id, m.sub_institute_id, ay.id",
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | hr — staff, leave, payroll, timetable
    |--------------------------------------------------------------------------
    |
    | `tbluser` holds 4,771 people; the graph already has 118 of them as :Teacher
    | (teacherId = tbluser.id) from the reference script. Loading all of them into a
    | second label would put those 118 in the graph twice. So the export takes every
    | row and the SCRIPT skips the ones that are already :Teacher
    | (`OPTIONAL MATCH (t:Teacher {teacherId: ...}) ... WHERE t IS NULL`), which keeps
    | the exporter ignorant of the graph. Every HR edge then resolves the person as
    | Teacher-if-present-else-Staff, so one person is one node either way.
    |
    | Credentials and bank details are deliberately not selected: password,
    | plain_password, otp, login_ip, account_no, ifsc_code, pan_no, aadhar_no.
    | Projecting them would copy secrets into a database with no role-based access
    | control (Community edition, one shared credential).
    */
    'hr' => [
        'file'    => '20_hr.cypher',
        'extends' => [],
        'csv' => [

            'tbluser' => "
                SELECT id, user_name, first_name, middle_name, last_name, email, mobile, gender,
                       user_profile_id, department_id, jobtitle_id, employee_no, qualification,
                       occupation, joined_date, relieving_date, reporting_manager_id, subject_ids,
                       allocated_standards, total_lecture, `load` AS teaching_load, status, is_admin,
                       client_id, sub_institute_id
                FROM tbluser",

            'hrms_holidays' => "
                SELECT id, sub_institute_id, holiday_name, description, day_type, department,
                       from_date, to_date
                FROM hrms_holidays WHERE deleted_at IS NULL",

            'hrms_leave_types' => "
                SELECT id, leave_type_id, leave_type, sort_order, carry_forward, status, sub_institute_id
                FROM hrms_leave_types WHERE deleted_at IS NULL",

            'payroll_types' => "
                SELECT id, payroll_type, payroll_name, amount_type, status, sort_order, sub_institute_id
                FROM payroll_types",

            'tbluser_shift_master' => "
                SELECT id, shift_name, start_time, end_time, sub_institute_id
                FROM tbluser_shift_master WHERE deleted_at IS NULL",

            // Money columns are omitted from the node: the fee/payroll ledger is not
            // authoritative in the graph, and a second copy of a salary is a liability
            // rather than a traversal. `employee_salary_data` is a JSON blob of amounts.
            'employee_salary_structures' => "
                SELECT id, employee_id, year, sub_institute_id FROM employee_salary_structures",

            'hrms_salary_certificate' => "
                SELECT id, employee_id, departement_id, year, month, payroll_type_id, reason,
                       sub_institute_id
                FROM hrms_salary_certificate",

            // leave_type_id resolves to hrms_leave_types.id on 28,408 of 28,408 rows.
            'hrms_emp_leaves' => "
                SELECT id, user_id, leave_type_id, department_id, day_type, slot, from_date, to_date,
                       status, sub_institute_id
                FROM hrms_emp_leaves WHERE deleted_at IS NULL",

            'hrms_leave_allocation' => "
                SELECT id, employee_id, leave_type_id, department_id, year, value, sub_institute_id
                FROM hrms_leave_allocation WHERE deleted_at IS NULL",

            'mapped_teachers' => "
                SELECT id, teacher_id, standard_id, subject_id, `load` AS teaching_load, syear,
                       sub_institute_id
                FROM mapped_teachers",

            // 102,686 timetable rows are one period each; the graph wants one edge per
            // (teacher, subject, class, year) carrying how many periods that is.
            'timetable_agg' => "
                SELECT teacher_id, subject_id, division_id, standard_id, syear, sub_institute_id,
                       COUNT(*) AS periods, COUNT(DISTINCT week_day) AS week_days
                FROM timetable
                WHERE teacher_id > 0 AND subject_id > 0
                GROUP BY teacher_id, subject_id, division_id, standard_id, syear, sub_institute_id",

            'proxy_master_agg' => "
                SELECT teacher_id, proxy_teacher_id, syear, sub_institute_id,
                       COUNT(*) AS proxies, MIN(proxy_date) AS first_date, MAX(proxy_date) AS last_date
                FROM proxy_master
                WHERE teacher_id > 0 AND proxy_teacher_id > 0
                GROUP BY teacher_id, proxy_teacher_id, syear, sub_institute_id",

            // 356,872 punch rows -> 16,056 (person, year, month) edges.
            'hrms_attendances_agg' => "
                SELECT user_id, sub_institute_id, YEAR(day) AS yr, MONTH(day) AS mth,
                       COUNT(DISTINCT day) AS days_present, COUNT(*) AS punches,
                       SUM(COALESCE(overtime, 0)) AS overtime_total,
                       MIN(day) AS first_day, MAX(day) AS last_day
                FROM hrms_attendances
                WHERE deleted_at IS NULL AND user_id > 0 AND day IS NOT NULL
                GROUP BY user_id, sub_institute_id, YEAR(day), MONTH(day)",

            // deduction_type resolves to payroll_types.id on 7,567 of 7,567 rows.
            // Counts only, no amounts.
            'hrms_emp_payroll_deduction_agg' => "
                SELECT employee_id, deduction_type, year, sub_institute_id,
                       COUNT(*) AS deductions, COUNT(DISTINCT month) AS months
                FROM hrms_emp_payroll_deduction
                WHERE employee_id > 0 AND deduction_type > 0
                GROUP BY employee_id, deduction_type, year, sub_institute_id",

            'employee_monthly_salary_data_agg' => "
                SELECT employee_id, sub_institute_id, year, COUNT(DISTINCT month) AS months,
                       COUNT(*) AS runs
                FROM employee_monthly_salary_data
                WHERE employee_id > 0
                GROUP BY employee_id, sub_institute_id, year",

            'class_teacher' => "
                SELECT id, teacher_id, division_id, standard_id, grade_id, syear, sub_institute_id
                FROM class_teacher WHERE teacher_id > 0",

            // Named `leave_applications` but keyed on tblstudent.id (18,925 of 20,396
            // resolve there, 592 against tbluser) — it is the STUDENT leave module, so it
            // hangs off :StuDetail, not off staff.
            'leave_applications_agg' => "
                SELECT l.student_id, l.syear, l.sub_institute_id,
                       COUNT(*) AS applications,
                       SUM(CASE WHEN l.status = 'Approved' THEN 1 ELSE 0 END) AS approved,
                       MIN(l.apply_date) AS first_apply, MAX(l.apply_date) AS last_apply,
                       (SELECT MIN(a.id) FROM academic_year a
                         WHERE a.sub_institute_id = l.sub_institute_id AND a.syear = l.syear) AS academic_year_id
                FROM leave_applications l
                WHERE l.student_id > 0
                GROUP BY l.student_id, l.syear, l.sub_institute_id",
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | result — exams, grades, co-scholastic, marks
    |--------------------------------------------------------------------------
    |
    | `result_create_exam` becomes :Examination, NOT :Exam. The career-intelligence
    | seed already owns `:Exam {exam_id}` with a curated string vocabulary
    | ('EXAM-NATA'), and reusing that label would mix an entrance-exam reference set
    | with 33,760 school exam rows keyed on integers.
    |
    | `result_personalize_marks.exam_id` points at `result_create_exam.id`, measured
    | 3,323 of 3,334 distinct values (99.7%) against 525 for `result_exam_master.Id`.
    | That settles which node SCORED attaches to.
    |
    | Two tables use PascalCase columns (`Id`, `SubInstituteId`); they are aliased in
    | SQL so the Cypher stays uniform.
    */
    'result' => [
        'file'    => '30_result.cypher',
        'extends' => [],
        'csv' => [

            'result_create_exam' => "
                SELECT id, syear, term_id, exam_id, standard_id, subject_id, title, points,
                       con_point, marks_type, medium, report_card_status, sort_order, exam_date,
                       sub_institute_id
                FROM result_create_exam",

            'result_exam_master' => "
                SELECT Id AS id, Code AS code, ExamType AS exam_type, ExamTitle AS exam_title,
                       SortOrder AS sort_order, standard_id, term_id, weightage,
                       SubInstituteId AS sub_institute_id
                FROM result_exam_master",

            'result_exam_type_master' => "
                SELECT Id AS id, Code AS code, ExamType AS exam_type, ShortName AS short_name,
                       SortOrder AS sort_order, SubInstituteId AS sub_institute_id
                FROM result_exam_type_master",

            'grade_master_data' => "
                SELECT id, syear, grade_id, title, breakoff, gp, sort_order, comment, sub_institute_id
                FROM grade_master_data",

            'result_co_scholastic' => "
                SELECT id, term_id, title, parent_id, mark_type, max_mark, co_grade, standard_id,
                       sort_order, sub_institute_id
                FROM result_co_scholastic",

            'result_co_scholastic_parent' => "
                SELECT id, title, part_no, part_name, sort_order, status, sub_institute_id
                FROM result_co_scholastic_parent",

            // Grade bands per co-scholastic area — no student column, so these are
            // reference nodes, not marks. `map_id` resolves on 166 of 371 distinct values.
            'result_co_scholastic_grades' => "
                SELECT id, map_id, title, break_off, sub_institute_id
                FROM result_co_scholastic_grades",

            'result_skillset' => "
                SELECT id, main_title, title, standard, `group` AS skill_group, sort_order,
                       main_sort_order, sub_institute_id
                FROM result_skillset",

            'result_activity_master' => "
                SELECT id, title, skill_id, standard, term_id, sort_order, sub_institute_id
                FROM result_activity_master",

            'result_activity_group' => "
                SELECT id, title, `group` AS activity_group, sort_order, sub_institute_id
                FROM result_activity_group",

            'result_remark_masters' => "
                SELECT id, syear, marking_period_id, title, remark_status, sort_order, sub_institute_id
                FROM result_remark_masters",

            'exam_schedule' => "
                SELECT id, syear, standard_id, division_id, title, date_ AS exam_date, sub_institute_id
                FROM exam_schedule",

            'result_std_grd_maping' => "
                SELECT id, grade_scale, standard, sub_institute_id FROM result_std_grd_maping",

            // 1,308,379 mark rows -> 54,722 (student, exam) edges.
            'result_personalize_marks_agg' => "
                SELECT student_id, exam_id, syear, sub_institute_id,
                       SUM(COALESCE(total, 0)) AS total_marks, SUM(COALESCE(obtain, 0)) AS obtained,
                       COUNT(DISTINCT subject_id) AS subjects, COUNT(*) AS rows_merged,
                       MAX(standard_id) AS standard_id
                FROM result_personalize_marks
                WHERE student_id > 0 AND exam_id > 0
                GROUP BY student_id, exam_id, syear, sub_institute_id",

            // `term_id` is NULL on most report-card rows (and `sub_institute_id` on some),
            // so the exact-term join that works for attendance returns nothing here.
            // Resolve the term when it is named, else the year's first term; rows with no
            // tenant cannot name an academic year at all and are dropped.
            'result_reportcard_marks_agg' => "
                SELECT m.student_id, m.syear, COALESCE(m.term_id, 0) AS term_id, m.sub_institute_id,
                       COALESCE(
                         (SELECT MIN(a.id) FROM academic_year a
                           WHERE a.sub_institute_id = m.sub_institute_id AND a.syear = m.syear
                             AND a.term_id = m.term_id),
                         (SELECT MIN(a.id) FROM academic_year a
                           WHERE a.sub_institute_id = m.sub_institute_id AND a.syear = m.syear)
                       ) AS academic_year_id,
                       MAX(m.student_percentage) AS percentage,
                       MAX(m.present_working_day) AS present_days,
                       MAX(m.total_working_day) AS working_days,
                       MAX(m.standard_id) AS standard_id, COUNT(*) AS rows_merged
                FROM result_reportcard_marks m
                WHERE m.deleted_at IS NULL AND m.student_id > 0 AND m.sub_institute_id IS NOT NULL
                GROUP BY m.student_id, m.syear, m.term_id, m.sub_institute_id",
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | assessment — the parts of the question/exam layer the k12 script left out
    |--------------------------------------------------------------------------
    |
    | :Question, :Assessment and :Result already exist and are NOT touched. What is
    | missing is the tagging (516,184 question/taxonomy rows), the question types, the
    | counselling bank, and chapter-grain mastery derived from 2.4M answer rows.
    |
    | The counselling tables get their own labels. `counselling_question_master.id`
    | starts at 1 and would collide with `Question.qId`; the same for
    | `counselling_online_exam` against `Result.resultId`.
    */
    'assessment' => [
        'file'    => '40_assessment.cypher',
        'extends' => [],
        'csv' => [

            'question_type_master' => "
                SELECT id, question_type, status, syear, sub_institute_id FROM question_type_master",

            // 516,184 rows -> 509,009 distinct (question, taxonomy value) pairs.
            // `mapping_value_id` is the taxonomy VALUE (153 of 156 resolve to
            // lms_mapping_type); `mapping_type_id` is the dimension it belongs to
            // (Bloom's, Depth of Knowledge) and rides along as an edge property.
            'lms_question_mapping_agg' => "
                SELECT questionmaster_id, mapping_value_id, MIN(mapping_type_id) AS mapping_type_id
                FROM lms_question_mapping
                WHERE questionmaster_id > 0 AND mapping_value_id > 0
                GROUP BY questionmaster_id, mapping_value_id",

            'counselling_course' => "
                SELECT id, title, description, sort_order, status, sub_institute_id
                FROM counselling_course",

            'counselling_question_master' => "
                SELECT id, counselling_course_id, question_type_id, question_title, description,
                       points, multiple_answer, status, sub_institute_id
                FROM counselling_question_master",

            'counselling_online_exam' => "
                SELECT id, user_id, course_id, total_right, total_wrong, obtain_marks, sub_institute_id
                FROM counselling_online_exam",

            'counselling_question_mapping' => "
                SELECT id, questionmaster_id, mapping_type_id, mapping_value_id
                FROM counselling_question_mapping",

            'lms_offline_exam' => "
                SELECT id, student_id, question_paper_id, assignment_id, total_right, total_wrong,
                       obtain_marks, syear, sub_institute_id
                FROM lms_offline_exam",

            'lms_online_exam_student' => "
                SELECT id, student_id, question_paper_id, total_right, total_wrong, obtain_marks,
                       start_time
                FROM lms_online_exam_student",

            'MBTI_paper' => "SELECT id, sub_institute_id FROM MBTI_paper",

            // Chapter-grain mastery. 2,418,164 + 778 + 5 answer rows collapse to ~24,000
            // (student, chapter) edges. `ans_status` is the string 'right' / 'wrong'.
            // Each source is grouped before the union so MariaDB never materialises the
            // 2.4M-row intermediate.
            //
            // The chapter comes from `lms_question_master.chapter_id`, which is populated
            // on 62,206 of 62,209 questions — `concept_id` is populated on 47, so concept
            // grain is not reachable from this table.
            'lms_online_exam_answer_agg' => "
                SELECT student_id, chapter_id, sub_institute_id,
                       SUM(attempts) AS attempts, SUM(correct) AS correct, MAX(last_at) AS last_at
                FROM (
                    SELECT a.student_id, q.chapter_id, q.sub_institute_id,
                           COUNT(*) AS attempts, SUM(a.ans_status = 'right') AS correct,
                           MAX(a.created_at) AS last_at
                      FROM lms_online_exam_answer a
                      JOIN lms_question_master q ON q.id = a.question_id
                     WHERE q.chapter_id > 0 AND a.student_id > 0
                     GROUP BY a.student_id, q.chapter_id, q.sub_institute_id
                    UNION ALL
                    SELECT a.student_id, q.chapter_id, q.sub_institute_id,
                           COUNT(*), SUM(a.ans_status = 'right'), MAX(a.created_at)
                      FROM lms_online_exam_answer_student a
                      JOIN lms_question_master q ON q.id = a.question_id
                     WHERE q.chapter_id > 0 AND a.student_id > 0
                     GROUP BY a.student_id, q.chapter_id, q.sub_institute_id
                    UNION ALL
                    SELECT a.student_id, q.chapter_id, q.sub_institute_id,
                           COUNT(*), SUM(a.ans_status = 'right'), MAX(a.created_at)
                      FROM lms_offline_exam_answer a
                      JOIN lms_question_master q ON q.id = a.question_id
                     WHERE q.chapter_id > 0 AND a.student_id > 0
                     GROUP BY a.student_id, q.chapter_id, q.sub_institute_id
                ) x
                GROUP BY student_id, chapter_id, sub_institute_id",
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | operations — library, transport, hostel, inventory, front desk
    |--------------------------------------------------------------------------
    |
    | 28 labels, none of which exist in the graph today, so nothing here can collide
    | with the reference layer. Two names are disambiguated on purpose:
    | :TransportShift (hr already has :StaffShift from `tbluser_shift_master`) and
    | :BookCopy for `library_items`, which are physical copies rather than titles.
    |
    | Several tables use UPPERCASE column names (`front_desk`, `complaint`,
    | `inventory_allocation_details`); they are aliased in SQL so the Cypher stays
    | uniform.
    */
    'operations' => [
        'file'    => '50_operations.cypher',
        'extends' => [],
        'csv' => [

            // Bibliographic columns only — the review/notes/attachment blobs have no
            // traversal value and would dominate the export.
            'library_books' => "
                SELECT id, title, sub_title, author_name, publisher_name, isbn_issn, edition,
                       publish_year, language, call_number, classification, subject, standard,
                       material_resource_type, doc_type, academic_year, pages, sub_institute_id
                FROM library_books WHERE deleted_at IS NULL",

            'library_items' => "
                SELECT id, book_id, item_code, call_number, item_status, received_date,
                       order_no, order_date, sub_institute_id
                FROM library_items WHERE deleted_at IS NULL",

            // 67,487 loans -> one edge per (student, book) carrying how often.
            'library_book_circulations_agg' => "
                SELECT student_id, book_id, sub_institute_id,
                       COUNT(*) AS times_borrowed,
                       MIN(issued_date) AS first_issued, MAX(issued_date) AS last_issued,
                       SUM(return_date IS NULL) AS outstanding
                FROM library_book_circulations
                WHERE deleted_at IS NULL AND student_id > 0 AND book_id > 0
                GROUP BY student_id, book_id, sub_institute_id",

            'transport_route' => "
                SELECT id, syear, route_name, from_time, to_time, sub_institute_id
                FROM transport_route",

            'transport_stop' => "
                SELECT id, syear, stop_name, sub_institute_id FROM transport_stop",

            'transport_vehicle' => "
                SELECT id, title, vehicle_number, vehicle_type, sitting_capacity, school_shift,
                       vehicle_identity_number, driver, conductor, sub_institute_id
                FROM transport_vehicle",

            'transport_driver_detail' => "
                SELECT id, first_name, last_name, mobile, type, status, sub_institute_id
                FROM transport_driver_detail",

            'transport_school_shift' => "
                SELECT id, shift_title, shift_rate, km_amount, sub_institute_id
                FROM transport_school_shift",

            'transport_vehicle_type' => "SELECT id, name FROM transport_vehicle_type",

            'transport_route_stop' => "
                SELECT id, syear, route_id, stop_id, pickuptime, droptime, sub_institute_id
                FROM transport_route_stop",

            'transport_route_bus' => "
                SELECT id, syear, route_id, bus_id, sub_institute_id FROM transport_route_bus",

            // Two edges per row: the morning stop and the afternoon stop.
            'transport_map_student' => "
                SELECT id, syear, student_id, from_stop, to_stop, from_bus_id, to_bus_id,
                       from_shift_id, to_shift_id, distance, sub_institute_id
                FROM transport_map_student WHERE student_id > 0",

            'hostel_master' => "
                SELECT id, hostel_type_id, code, name, description, warden, warden_contact,
                       sub_institute_id
                FROM hostel_master",

            'hostel_building_master' => "
                SELECT id, hostel_type_id, hostel_id, building_name, sub_institute_id
                FROM hostel_building_master",

            'hostel_floor_master' => "
                SELECT id, building_id, floor_name, sub_institute_id FROM hostel_floor_master",

            'hostel_room_master' => "
                SELECT id, floor_id, room_name, sub_institute_id FROM hostel_room_master",

            'hostel_type_master' => "
                SELECT id, hostel_type, description, status, sub_institute_id FROM hostel_type_master",

            'room_type_master' => "
                SELECT id, room_type, status, sub_institute_id FROM room_type_master",

            // `user_id` is the resident. Only 9 rows, so the script resolves it against
            // both the student master and staff rather than guessing which one it means.
            'hostel_room_allocation' => "
                SELECT id, user_id, hostel_id, room_id, bed_no, locker_no, term_id, syear,
                       admission_category_id, sub_institute_id
                FROM hostel_room_allocation",

            // A visitor log with names only — no FK to a student or a staff member.
            'hostel_visitor_master' => "
                SELECT id, name, contact, email, coming_from, to_meet, relation, meet_date,
                       in_time, out_time, sub_institute_id
                FROM hostel_visitor_master",

            'inventory_item_master' => "
                SELECT id, syear, category_id, sub_category_id, item_type_id, title, description,
                       opening_stock, minimum_stock, item_status, sub_institute_id
                FROM inventory_item_master",

            'inventory_item_category_master' => "
                SELECT id, syear, title, description, status, sub_institute_id
                FROM inventory_item_category_master",

            'inventory_item_sub_category_master' => "
                SELECT id, syear, category_id, title, description, status, sub_institute_id
                FROM inventory_item_sub_category_master",

            'inventory_item_type' => "SELECT id, title, sub_institute_id FROM inventory_item_type",

            // Bank and tax identifiers are not exported — same rule as tbluser.
            'inventory_vendor_master' => "
                SELECT id, syear, vendor_name, short_name, company_name, business_type,
                       contact_number, email, office_address, sort_order, sub_institute_id
                FROM inventory_vendor_master",

            'inventory_requisition_details' => "
                SELECT id, syear, requisition_no, requisition_by, requisition_date, item_id,
                       item_qty, approved_qty, requisition_status, department_id, sub_institute_id
                FROM inventory_requisition_details",

            'inventory_allocation_details' => "
                SELECT ID AS id, SYEAR AS syear, ITEM_ID AS item_id,
                       PERSON_RESPONSIBLE AS person_responsible,
                       LOCATION_OF_MATERIAL AS location_of_material,
                       REQUISITION_ID AS requisition_id, SUB_INSTITUTE_ID AS sub_institute_id
                FROM inventory_allocation_details",

            'inventory_item_direct_purchase' => "
                SELECT id, syear, vendor_id, item_id, item_qty, price, amount, bill_no, bill_date,
                       sub_institute_id
                FROM inventory_item_direct_purchase",

            'inventory_generate_po_details' => "
                SELECT id, syear, po_number, item_id, vendor_id, qty, price, amount,
                       po_approval_status, sub_institute_id
                FROM inventory_generate_po_details",

            'inventory_item_quotation_details' => "
                SELECT id, syear, item_id, vendor_id, qty, price, total, approved_status,
                       sub_institute_id
                FROM inventory_item_quotation_details",

            'physical_file_location' => "
                SELECT id, title, description, file_code, file_location, sub_institute_id
                FROM physical_file_location",

            'visitor_master' => "
                SELECT id, name, contact, email, visitor_type, appointment_type, coming_from,
                       to_meet, relation, purpose, meet_date, in_time, out_time, sub_institute_id
                FROM visitor_master",

            'visitor_type' => "SELECT id, title, status, sub_institute_id FROM visitor_type",

            'inward' => "
                SELECT id, syear, place_id, file_location_id, inward_number, title, description,
                       inward_date, sub_institute_id
                FROM inward",

            'outward' => "
                SELECT id, syear, place_id, file_location_id, outward_number, title, description,
                       outward_date, sub_institute_id
                FROM outward",

            'front_desk' => "
                SELECT ID AS id, SYEAR AS syear, VISITOR_TYPE AS visitor_type,
                       DATE AS visit_date, IN_TIME AS in_time, OUT_TIME AS out_time,
                       TITLE AS title, DESCRIPTION AS description, STUDENT_ID AS student_id,
                       TO_WHOM_MEET AS to_whom_meet, SUB_INSTITUTE_ID AS sub_institute_id
                FROM front_desk",

            'complaint' => "
                SELECT ID AS id, SYEAR AS syear, DATE AS complaint_date, TITLE AS title,
                       DESCRIPTION AS description, COMPLAINT_BY AS complaint_by,
                       COMPLAINT_SOLUTION AS solution, SUB_INSTITUTE_ID AS sub_institute_id
                FROM complaint",

            'circular' => "
                SELECT id, syear, standard_id, division_id, title, message,
                       date_ AS circular_date, type, sub_institute_id
                FROM circular",

            'circular_type' => "SELECT id, type FROM circular_type",

            'announcement' => "
                SELECT id, syear, title, description, from_date, to_date, user_profile_id,
                       sub_institute_id
                FROM announcement",
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | finance — fee masters and non-authoritative aggregates
    |--------------------------------------------------------------------------
    |
    | Every node and edge in this module carries `authoritative: false`. MariaDB is
    | the ledger; a second copy of a balance in an eventually-consistent store that
    | nobody reconciles is a business incident waiting to happen. The graph gets the
    | STRUCTURE (which fee applies to which class, which learner owes which head) and
    | counts; totals ride along only where they were already aggregated, and no
    | per-student liability is derived from the schedule.
    |
    | Two FK targets, measured 2026-09-03 because the column name says neither:
    |   `fees_breackoff.fee_type_id`    -> fees_title      (499 of 500 distinct values)
    |   `fees_breakoff_other.fee_type_id` -> fees_other_head (5 of 10; fees_title 0)
    |
    | `fees_breackoff` has NO student_id — it is a fee schedule per
    | (grade, standard, quota, month), not a student ledger, so it attaches to
    | :Standard. `fees_receipt_book_master.fees_head_id` resolves on 15 of 702 values,
    | so that edge is deliberately not built.
    */
    'finance' => [
        'file'    => '60_finance.cypher',
        'extends' => [],
        'csv' => [

            'fees_head_master' => "
                SELECT id, code, head_title, description, mandatory, syear, sub_institute_id
                FROM fees_head_master",

            'fees_title' => "
                SELECT id, fees_title_id, fees_title, display_name, cumulative_name, append_name,
                       mandatory, sort_order, other_fee_id, syear, sub_institute_id
                FROM fees_title",

            'fees_title_master' => "SELECT id, title, fee_paid_title FROM fees_title_master",

            'fees_other_head' => "
                SELECT id, display_name, amount, include_imprest, status, sort_order, syear,
                       sub_institute_id
                FROM fees_other_head",

            'fees_config_master' => "
                SELECT id, syear, late_fees_amount, send_sms, send_email, institute_name,
                       auto_head_counting, show_month, sub_institute_id
                FROM fees_config_master",

            'fees_late_master' => "
                SELECT id, late_date, standard_id, term_id, fine_type, status, syear, sub_institute_id
                FROM fees_late_master",

            'fees_month_header' => "
                SELECT id, month_id, header, sub_institute_id FROM fees_month_header",

            // Bank account numbers are not exported, for the same reason staff bank
            // details are not: this graph has one shared credential and no RBAC.
            'fees_circular_master' => "
                SELECT id, syear, grade_id, standard_id, bank_name, branch, shift, form_no,
                       paid_collection, sub_institute_id
                FROM fees_circular_master",

            'fees_cancel_type' => "SELECT id, title FROM fees_cancel_type",

            'bank_master' => "SELECT id, bank_name FROM bank_master",

            'fees_receipt_book_master' => "
                SELECT id, syear, receipt_id, receipt_prefix, receipt_postfix, sort_order,
                       last_receipt_number, grade_id, standard_id, fees_head_id, status,
                       branch, sub_institute_id
                FROM fees_receipt_book_master",

            'petty_cash_master' => "SELECT id, title, sub_institute_id FROM petty_cash_master",

            'fees_map_years' => "
                SELECT id, from_month, to_month, type, syear, sub_institute_id FROM fees_map_years",

            // 182,379 schedule rows -> 10,178 (fee title, class, year, quota) edges.
            'fees_breackoff_agg' => "
                SELECT fee_type_id, standard_id, syear, quota, sub_institute_id,
                       COUNT(*) AS rows_merged, COUNT(DISTINCT month_id) AS months,
                       SUM(COALESCE(amount, 0)) AS total_amount
                FROM fees_breackoff
                WHERE fee_type_id > 0 AND standard_id > 0
                GROUP BY fee_type_id, standard_id, syear, quota, sub_institute_id",

            // 46,864 rows -> 10,357 (learner, other-fee head, year) edges.
            'fees_breakoff_other_agg' => "
                SELECT student_id, fee_type_id, syear, sub_institute_id,
                       COUNT(*) AS rows_merged, COUNT(DISTINCT month_id) AS months,
                       SUM(COALESCE(amount, 0)) AS total_amount
                FROM fees_breakoff_other
                WHERE student_id > 0 AND fee_type_id > 0
                GROUP BY student_id, fee_type_id, syear, sub_institute_id",

            'fees_other_collection_agg' => "
                SELECT student_id, deduction_head_id, syear, sub_institute_id,
                       COUNT(*) AS receipts, SUM(COALESCE(deduction_amount, 0)) AS total_amount,
                       MIN(deduction_date) AS first_paid, MAX(deduction_date) AS last_paid
                FROM fees_other_collection
                WHERE COALESCE(is_deleted, 'N') <> 'Y' AND student_id > 0 AND deduction_head_id > 0
                GROUP BY student_id, deduction_head_id, syear, sub_institute_id",

            // The main collection ledger holds 20 rows in this database; it is loaded
            // for completeness of shape, not for its volume.
            'fees_collect_agg' => "
                SELECT c.student_id, c.syear, c.sub_institute_id,
                       COUNT(*) AS receipts, SUM(COALESCE(c.amount, 0)) AS total_amount,
                       MIN(c.receiptdate) AS first_paid, MAX(c.receiptdate) AS last_paid,
                       (SELECT MIN(a.id) FROM academic_year a
                         WHERE a.sub_institute_id = c.sub_institute_id AND a.syear = c.syear) AS academic_year_id
                FROM fees_collect c
                WHERE COALESCE(c.is_deleted, 'N') <> 'Y' AND c.student_id > 0
                GROUP BY c.student_id, c.syear, c.sub_institute_id",

            'petty_cash_agg' => "
                SELECT p.user_id, p.title_id, p.sub_institute_id,
                       COUNT(*) AS entries, SUM(COALESCE(p.amount, 0)) AS total_amount
                FROM petty_cash p
                WHERE p.user_id > 0 AND p.title_id > 0
                GROUP BY p.user_id, p.title_id, p.sub_institute_id",

            'donation_collection' => "
                SELECT id, donar_id, paid_date, donation_head, donation_amount, payment_mode,
                       reciept_no, bank_name, sub_institute_id
                FROM donation_collection WHERE deleted_at IS NULL",
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | skills — skills, job roles, SQAA, and the O*NET reference subgraph
    |--------------------------------------------------------------------------
    |
    | JOBROLE-KEY, the decision this module turns on: `s_jobrole_skills` (176,460 rows)
    | joins on NAME STRINGS, not ids, which a graph MERGE cannot use as a key. The
    | names are resolved to ids HERE, in SQL, and unmatched rows are dropped — 4,594 of
    | 4,596 distinct job-role names and 15,420 of 15,474 skill names resolve, measured
    | 2026-09-03. `sub_institute_id` is NULL on every row of that table, so the join is
    | on name alone; and because a name is not unique (1,895 distinct names across
    | 5,805 job-role rows) each name resolves to its LOWEST id, which gives one edge
    | per pair instead of a fan-out.
    |
    | Skill names live in `master_skills.title`, not `.name` — `.name` matches 0 of
    | 15,474 while `.title` matches 15,420.
    |
    | O*NET is US reference data with no tenant: every node is loaded with
    | sub_institute_id 0 and scope 'global'. Its occupations become :OnetOccupation
    | keyed on the SOC code, NOT :Occupation — the career-intelligence seed already
    | owns `:Occupation {occupation_id}` with a curated 'OCC-*' vocabulary, and the
    | two id spaces are unrelated.
    |
    | The rating tables are pivoted in SQL: O*NET stores one row per
    | (occupation, element, scale), and the graph wants one edge per
    | (occupation, element) carrying importance and level as properties.
    */
    'skills' => [
        'file'    => '70_skills.cypher',
        'extends' => [],
        'csv' => [

            'master_skills' => "
                SELECT id, title, description, industries, category, sub_category,
                       proficiency_level, status, sub_institute_id
                FROM master_skills WHERE deleted_at IS NULL",

            's_jobrole' => "
                SELECT id, jobrole, track, description, code, type, status, sub_institute_id
                FROM s_jobrole WHERE deleted_at IS NULL",

            's_industries' => "
                SELECT id, industries, department, sub_department, type FROM s_industries",

            's_assessment_library' => "
                SELECT id, title, description, total_questions, attempted_users, duration,
                       type, level, languages, job_role
                FROM s_assessment_library",

            // JOBROLE-KEY. The name -> id resolution was tried here first, as two joins
            // against derived tables, and it does not finish: `s_jobrole_skills` has no
            // index on `jobrole` or `skill`, so MariaDB materialises both maps and scans
            // 176,460 rows against them, over a WAN link, for more than ten minutes.
            //
            // So the export only DEDUPLICATES (176,460 rows -> 174,268 distinct
            // name pairs) and the graph resolves the names, where `jobrole_name_idx` and
            // `skill_title_idx` make each lookup a point read. The rule is the same
            // either way: a name that maps to several rows resolves to the LOWEST id, so
            // one CSV row still produces exactly one edge.
            's_jobrole_skills_agg' => "
                SELECT jobrole, skill,
                       MIN(proficiency_level) AS proficiency_level,
                       MIN(sector) AS sector, MIN(track) AS track,
                       COUNT(*) AS rows_merged
                FROM s_jobrole_skills
                WHERE deleted_at IS NULL
                  AND jobrole IS NOT NULL AND TRIM(jobrole) <> ''
                  AND skill IS NOT NULL AND TRIM(skill) <> ''
                GROUP BY jobrole, skill",

            // Tasks are free text with no id of their own, so the task node is keyed on
            // a hash of (job role, task) — stable across re-runs, unlike a row id that
            // changes when the table is rebuilt.
            's_jobrole_task_agg' => "
                SELECT jr.id AS jobrole_id,
                       SHA1(CONCAT(jr.id, '|', jt.task)) AS task_key,
                       MIN(jt.task) AS task, MIN(jt.critical_work_function) AS critical_work_function,
                       COUNT(*) AS rows_merged
                FROM s_jobrole_task jt
                JOIN (SELECT jobrole, MIN(id) AS id FROM s_jobrole WHERE deleted_at IS NULL
                       GROUP BY jobrole) jr ON jr.jobrole = jt.jobrole
                WHERE jt.deleted_at IS NULL AND jt.task IS NOT NULL AND TRIM(jt.task) <> ''
                GROUP BY jr.id, SHA1(CONCAT(jr.id, '|', jt.task))",

            's_skill_matrix' => "
                SELECT id, user_id, skill_id, skill_level, interest_level, knowledge, ability, type
                FROM s_skill_matrix WHERE deleted_at IS NULL",

            'sqaa_master' => "
                SELECT id, title, description, parent_id, level, status, sort_order, sub_institute_id
                FROM sqaa_master",
            'sqaa_documant_master' => "
                SELECT id, menu_id, title, sub_institute_id FROM sqaa_documant_master",
            'sqaa_documents' => "
                SELECT id, menu_id, document_id, title, reasons, availability, sub_institute_id
                FROM sqaa_documents",
            'sqaa_marks' => "
                SELECT id, menu_id, mark, sub_institute_id FROM sqaa_marks",

            // ---- O*NET reference subgraph (global, no tenant) ----

            'onet_occupation_data' => "
                SELECT onetsoc_code, title, description FROM onet_occupation_data",
            'onet_content_model_reference' => "
                SELECT element_id, element_name, description, type, level
                FROM onet_content_model_reference",
            'onet_scales_reference' => "
                SELECT scale_id, scale_name, minimum, maximum FROM onet_scales_reference",
            'onet_job_zone_reference' => "
                SELECT job_zone, title, name, experience, education, job_training, svp_range
                FROM onet_job_zone_reference",
            'onet_unspsc_reference' => "
                SELECT commodity_code, commodity_title, class_code, class_title, family_code,
                       family_title, segment_code, segment_title
                FROM onet_unspsc_reference",
            'onet_work_context_categories' => "
                SELECT element_id, scale_id, category, category_description
                FROM onet_work_context_categories",
            'onet_career_cluster' => "
                SELECT career_id, career_cluster, career_pathway, onetsoc_code, title, description
                FROM onet_career_cluster",
            'onet_job_zones' => "
                SELECT onetsoc_code, job_zone FROM onet_job_zones",
            'onet_task_statements' => "
                SELECT task_id, onetsoc_code, task, task_type, incumbents_responding
                FROM onet_task_statements",

            // The five rating tables share one shape: one row per
            // (occupation, element, scale). Pivoted to one row per (occupation, element)
            // with IM = importance and LV = level, which is how O*NET is meant to be read.
            'onet_skills_agg' => "
                SELECT onetsoc_code, element_id,
                       MAX(CASE WHEN scale_id = 'IM' THEN data_value END) AS importance,
                       MAX(CASE WHEN scale_id = 'LV' THEN data_value END) AS level_value
                FROM onet_skills GROUP BY onetsoc_code, element_id",
            'onet_abilities_agg' => "
                SELECT onetsoc_code, element_id,
                       MAX(CASE WHEN scale_id = 'IM' THEN data_value END) AS importance,
                       MAX(CASE WHEN scale_id = 'LV' THEN data_value END) AS level_value
                FROM onet_abilities GROUP BY onetsoc_code, element_id",
            'onet_knowledge_agg' => "
                SELECT onetsoc_code, element_id,
                       MAX(CASE WHEN scale_id = 'IM' THEN data_value END) AS importance,
                       MAX(CASE WHEN scale_id = 'LV' THEN data_value END) AS level_value
                FROM onet_knowledge GROUP BY onetsoc_code, element_id",
            'onet_work_activities_agg' => "
                SELECT onetsoc_code, element_id,
                       MAX(CASE WHEN scale_id = 'IM' THEN data_value END) AS importance,
                       MAX(CASE WHEN scale_id = 'LV' THEN data_value END) AS level_value
                FROM onet_work_activities GROUP BY onetsoc_code, element_id",
            'onet_work_styles_agg' => "
                SELECT onetsoc_code, element_id,
                       MAX(CASE WHEN scale_id = 'IM' THEN data_value END) AS importance
                FROM onet_work_styles GROUP BY onetsoc_code, element_id",
            'onet_interests_agg' => "
                SELECT onetsoc_code, element_id, MAX(data_value) AS data_value
                FROM onet_interests GROUP BY onetsoc_code, element_id",
            'onet_work_values_agg' => "
                SELECT onetsoc_code, element_id, MAX(data_value) AS data_value
                FROM onet_work_values GROUP BY onetsoc_code, element_id",

            // 289,173 rows -> one edge per (occupation, element).
            'onet_work_context_agg' => "
                SELECT onetsoc_code, element_id,
                       MAX(CASE WHEN scale_id = 'CX' THEN data_value END) AS context_value,
                       MAX(data_value) AS max_value
                FROM onet_work_context GROUP BY onetsoc_code, element_id",

            // 161,847 rows -> one edge per (occupation, task).
            'onet_task_ratings_agg' => "
                SELECT onetsoc_code, task_id,
                       MAX(CASE WHEN scale_id = 'IM' THEN data_value END) AS importance,
                       MAX(CASE WHEN scale_id = 'RT' THEN data_value END) AS relevance,
                       MAX(CASE WHEN scale_id = 'FT' THEN data_value END) AS frequency
                FROM onet_task_ratings GROUP BY onetsoc_code, task_id",

            'onet_technology_skills_agg' => "
                SELECT onetsoc_code, commodity_code,
                       COUNT(*) AS examples,
                       MAX(hot_technology) AS hot_technology, MAX(in_demand) AS in_demand
                FROM onet_technology_skills
                WHERE commodity_code IS NOT NULL
                GROUP BY onetsoc_code, commodity_code",

            'onet_tools_used_agg' => "
                SELECT onetsoc_code, commodity_code, COUNT(*) AS examples
                FROM onet_tools_used
                WHERE commodity_code IS NOT NULL
                GROUP BY onetsoc_code, commodity_code",
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | platform — calendar, tasks, PTM, leaderboard, communication logs
    |--------------------------------------------------------------------------
    |
    | 101 of the 122 tables the classification puts in this module are EXCLUDE
    | (menus, rights matrices, gateway config, push-token registries, audit logs), so
    | this is the smallest real surface of the eight.
    |
    | The four send logs (SMS, WhatsApp, email, portal) are aggregated to one edge per
    | (learner, channel, year) carrying how many messages. A send log mirrored row for
    | row would add ~90,000 nodes that answer no question a traversal asks.
    |
    | `task`, `ptm_booking_master` and `sms_sent_parents` use UPPERCASE column names;
    | they are aliased in SQL.
    */
    'platform' => [
        'file'    => '80_platform.cypher',
        'extends' => [],
        'csv' => [

            'calendar_events' => "
                SELECT id, syear, school_date, title, description, event_type, standard,
                       sub_institute_id
                FROM calendar_events",

            'task' => "
                SELECT ID AS id, TASK_TITLE AS task_title, TASK_DESCRIPTION AS task_description,
                       TASK_DATE AS task_date, task_type, STATUS AS status,
                       TASK_ALLOCATED AS task_allocated, TASK_ALLOCATED_TO AS task_allocated_to,
                       KRA AS kra, KPA AS kpa, required_skill, estimated_hours, actual_hours,
                       status_label, approve_status, SYEAR AS syear, CREATED_BY AS created_by,
                       sub_institute_id
                FROM task WHERE deleted_at IS NULL",

            'ptm_time_slots_master' => "
                SELECT id, syear, ptm_date, standard_id, division_id, title, from_time, to_time,
                       sub_institute_id
                FROM ptm_time_slots_master",

            'ptm_booking_master' => "
                SELECT ID AS id, DATE AS booking_date, TEACHER_ID AS teacher_id,
                       TIME_SLOT_ID AS time_slot_id, CONFIRM_STATUS AS confirm_status,
                       STUDENT_ID AS student_id, PTM_ATTENDED_STATUS AS attended_status,
                       SUB_INSTITUTE_ID AS sub_institute_id
                FROM ptm_booking_master",

            'lb_master' => "
                SELECT id, grade_id, standard_id, module_name, per_value, points, description,
                       status, sub_institute_id
                FROM lb_master",

            'lb_points_agg' => "
                SELECT user_id, module_name, syear, sub_institute_id,
                       SUM(COALESCE(points, 0)) AS points, COUNT(*) AS awards
                FROM lb_points
                WHERE user_id > 0
                GROUP BY user_id, module_name, syear, sub_institute_id",

            'user_activities' => "
                SELECT id, user_id, content_id, action, sub_institute_id
                FROM user_activities WHERE deleted_at IS NULL",

            // The four communication channels, each aggregated to (learner, year).
            // `academic_year_id` is resolved here because the :AcademicYear uid needs a
            // concrete term id.
            'sms_sent_parents_agg' => "
                SELECT s.STUDENT_ID AS student_id, s.SYEAR AS syear, s.sub_institute_id,
                       COUNT(*) AS messages, MIN(s.CREATED_ON) AS first_sent,
                       MAX(s.CREATED_ON) AS last_sent,
                       (SELECT MIN(a.id) FROM academic_year a
                         WHERE a.sub_institute_id = s.sub_institute_id AND a.syear = s.SYEAR) AS academic_year_id
                FROM sms_sent_parents s
                WHERE s.STUDENT_ID > 0
                GROUP BY s.STUDENT_ID, s.SYEAR, s.sub_institute_id",

            'whatsapp_sent_messages_agg' => "
                SELECT w.student_id, w.syear, w.sub_institute_id,
                       COUNT(*) AS messages, MIN(w.sent_date) AS first_sent,
                       MAX(w.sent_date) AS last_sent,
                       (SELECT MIN(a.id) FROM academic_year a
                         WHERE a.sub_institute_id = w.sub_institute_id AND a.syear = w.syear) AS academic_year_id
                FROM whatsapp_sent_messages w
                WHERE w.student_id > 0
                GROUP BY w.student_id, w.syear, w.sub_institute_id",

            'parent_communication_agg' => "
                SELECT p.student_id, p.syear, p.sub_institute_id,
                       COUNT(*) AS messages, MIN(p.date_) AS first_sent, MAX(p.date_) AS last_sent,
                       (SELECT MIN(a.id) FROM academic_year a
                         WHERE a.sub_institute_id = p.sub_institute_id AND a.syear = p.syear) AS academic_year_id
                FROM parent_communication p
                WHERE p.student_id > 0
                GROUP BY p.student_id, p.syear, p.sub_institute_id",

            // Keyed on USER_ID (the staff sender), not on a student — this table has no
            // student column, so it hangs off the person, not the learner.
            'email_sent_parents_agg' => "
                SELECT e.USER_ID AS user_id, e.SYEAR AS syear, e.sub_institute_id,
                       COUNT(*) AS messages, MIN(e.CREATED_ON) AS first_sent,
                       MAX(e.CREATED_ON) AS last_sent
                FROM email_sent_parents e
                WHERE e.USER_ID > 0
                GROUP BY e.USER_ID, e.SYEAR, e.sub_institute_id",

            'sms_sent_staff_agg' => "
                SELECT s.staff_id, s.syear, s.sub_institute_id,
                       COUNT(*) AS messages, MIN(s.created_on) AS first_sent,
                       MAX(s.created_on) AS last_sent
                FROM sms_sent_staff s
                WHERE s.staff_id > 0
                GROUP BY s.staff_id, s.syear, s.sub_institute_id",
        ],
    ],
];
