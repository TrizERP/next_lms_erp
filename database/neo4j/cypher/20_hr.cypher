// =====================================================================
//  HR — staff, leave, payroll, timetable
//  Style and key convention follow k12_cypher.txt / reference_code.txt exactly.
//
//      php artisan neo4j:csv-export --module=hr
//      php artisan neo4j:cypher --module=hr
//
//  ONE PERSON, ONE NODE
//  The reference script created 118 :Teacher nodes keyed teacherId = tbluser.id.
//  `tbluser` holds 4,771 people. Creating :Staff for all of them would put those 118
//  in the graph twice, so the :Staff statement skips any id that is already a
//  :Teacher, and every edge below resolves the person as
//
//      OPTIONAL MATCH (t:Teacher {teacherId: <id>})
//      OPTIONAL MATCH (s:Staff   {staffId:   <id>})
//      WITH ..., coalesce(t, s) AS person WHERE person IS NOT NULL
//
//  The two sets are disjoint by construction, so this is one node, never two edges.
//  Note for readers on 4.4: `(p:Teacher|Staff)` label disjunction inside a pattern is
//  Cypher 5 syntax and does NOT work here — the verify block uses
//  `WHERE p:Teacher OR p:Staff` instead.
//
//  ADDITIVE. MERGE + ON CREATE SET only; no DELETE, no REMOVE, no bare SET on an
//  existing node. No protected relationship type is written by this file.
//
//  MONEY IS NOT PROJECTED. Payroll edges carry counts (months, runs, deductions) and
//  `authoritative: false`; amounts stay in MariaDB, which is the system of record.
//  Credentials, bank and identity numbers are not exported at all.
// =====================================================================


// @section constraints
// ---------------------------------------------------------------------
// 1. CONSTRAINTS
// ---------------------------------------------------------------------

CREATE CONSTRAINT staff_staffId_unique IF NOT EXISTS
FOR (s:Staff) REQUIRE s.staffId IS UNIQUE;

CREATE CONSTRAINT holiday_holidayId_unique IF NOT EXISTS
FOR (h:Holiday) REQUIRE h.holidayId IS UNIQUE;

CREATE CONSTRAINT leavetype_leavetypeId_unique IF NOT EXISTS
FOR (lt:LeaveType) REQUIRE lt.leavetypeId IS UNIQUE;

CREATE CONSTRAINT payrolltype_payrolltypeId_unique IF NOT EXISTS
FOR (pt:PayrollType) REQUIRE pt.payrolltypeId IS UNIQUE;

CREATE CONSTRAINT staffshift_staffshiftId_unique IF NOT EXISTS
FOR (sh:StaffShift) REQUIRE sh.staffshiftId IS UNIQUE;

CREATE CONSTRAINT salarystructure_salarystructureId_unique IF NOT EXISTS
FOR (ss:SalaryStructure) REQUIRE ss.salarystructureId IS UNIQUE;

CREATE CONSTRAINT salarycertificate_salarycertificateId_unique IF NOT EXISTS
FOR (sc:SalaryCertificate) REQUIRE sc.salarycertificateId IS UNIQUE;


// @section nodes
// ---------------------------------------------------------------------
// 2. NODES
// ---------------------------------------------------------------------

// :Staff — every tbluser row that is not already a :Teacher. Expected ~4,653.
LOAD CSV WITH HEADERS FROM 'file:///tbluser.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL

OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.id))})
WITH row, t
WHERE t IS NULL

MERGE (s:Staff {staffId: toInteger(trim(row.id))})

ON CREATE SET
  s.user_name            = CASE WHEN trim(coalesce(row.user_name, '')) = '' THEN null ELSE trim(row.user_name) END,
  s.first_name           = CASE WHEN trim(coalesce(row.first_name, '')) = '' THEN null ELSE trim(row.first_name) END,
  s.middle_name          = CASE WHEN trim(coalesce(row.middle_name, '')) = '' THEN null ELSE trim(row.middle_name) END,
  s.last_name            = CASE WHEN trim(coalesce(row.last_name, '')) = '' THEN null ELSE trim(row.last_name) END,
  s.email                = CASE WHEN trim(coalesce(row.email, '')) = '' THEN null ELSE trim(row.email) END,
  s.mobile               = CASE WHEN trim(coalesce(row.mobile, '')) = '' THEN null ELSE trim(row.mobile) END,
  s.gender               = CASE WHEN trim(coalesce(row.gender, '')) = '' THEN null ELSE trim(row.gender) END,
  s.user_profile_id      = toInteger(trim(row.user_profile_id)),
  s.department_id        = toInteger(trim(row.department_id)),
  s.jobtitle_id          = toInteger(trim(row.jobtitle_id)),
  s.employee_no          = CASE WHEN trim(coalesce(row.employee_no, '')) = '' THEN null ELSE trim(row.employee_no) END,
  s.qualification        = CASE WHEN trim(coalesce(row.qualification, '')) = '' THEN null ELSE trim(row.qualification) END,
  s.occupation           = CASE WHEN trim(coalesce(row.occupation, '')) = '' THEN null ELSE trim(row.occupation) END,
  s.joined_date          = CASE WHEN trim(coalesce(row.joined_date, '')) = '' THEN null ELSE trim(row.joined_date) END,
  s.relieving_date       = CASE WHEN trim(coalesce(row.relieving_date, '')) = '' THEN null ELSE trim(row.relieving_date) END,
  s.reporting_manager_id = toInteger(trim(row.reporting_manager_id)),
  s.subject_ids          = CASE WHEN trim(coalesce(row.subject_ids, '')) = '' THEN null ELSE trim(row.subject_ids) END,
  s.allocated_standards  = CASE WHEN trim(coalesce(row.allocated_standards, '')) = '' THEN null ELSE trim(row.allocated_standards) END,
  s.total_lecture        = toInteger(trim(row.total_lecture)),
  s.status               = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  s.is_admin             = toInteger(trim(row.is_admin)),
  s.client_id            = toInteger(trim(row.client_id)),
  s.displayLabel         = "Staff:" + trim(coalesce(row.first_name, '')) + " " + trim(coalesce(row.last_name, '')),
  s.sub_institute_id     = toInteger(trim(row.sub_institute_id)),
  s.src                  = "tbluser"

RETURN count(s) AS staffProcessed;


LOAD CSV WITH HEADERS FROM 'file:///hrms_holidays.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (h:Holiday {holidayId: toInteger(trim(row.id))})
ON CREATE SET
  h.holiday_name     = CASE WHEN trim(coalesce(row.holiday_name, '')) = '' THEN null ELSE trim(row.holiday_name) END,
  h.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  h.day_type         = CASE WHEN trim(coalesce(row.day_type, '')) = '' THEN null ELSE trim(row.day_type) END,
  h.department       = CASE WHEN trim(coalesce(row.department, '')) = '' THEN null ELSE trim(row.department) END,
  h.from_date        = CASE WHEN trim(coalesce(row.from_date, '')) = '' THEN null ELSE trim(row.from_date) END,
  h.to_date          = CASE WHEN trim(coalesce(row.to_date, '')) = '' THEN null ELSE trim(row.to_date) END,
  h.displayLabel     = "Holiday:" + trim(coalesce(row.holiday_name, '')),
  h.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  h.src              = "hrms_holidays"
RETURN count(h) AS holidayProcessed;


LOAD CSV WITH HEADERS FROM 'file:///hrms_leave_types.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (lt:LeaveType {leavetypeId: toInteger(trim(row.id))})
ON CREATE SET
  lt.leave_type       = CASE WHEN trim(coalesce(row.leave_type, '')) = '' THEN null ELSE trim(row.leave_type) END,
  lt.leave_type_code  = toInteger(trim(row.leave_type_id)),
  lt.carry_forward    = CASE WHEN trim(coalesce(row.carry_forward, '')) = '' THEN null ELSE trim(row.carry_forward) END,
  lt.sort_order       = toInteger(trim(row.sort_order)),
  lt.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  lt.displayLabel     = "LeaveType:" + trim(coalesce(row.leave_type, '')),
  lt.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  lt.src              = "hrms_leave_types"
RETURN count(lt) AS leaveTypeProcessed;


// authoritative:false — the payroll ledger stays in MariaDB. No amounts here.
LOAD CSV WITH HEADERS FROM 'file:///payroll_types.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (pt:PayrollType {payrolltypeId: toInteger(trim(row.id))})
ON CREATE SET
  pt.payroll_type     = CASE WHEN trim(coalesce(row.payroll_type, '')) = '' THEN null ELSE trim(row.payroll_type) END,
  pt.payroll_name     = CASE WHEN trim(coalesce(row.payroll_name, '')) = '' THEN null ELSE trim(row.payroll_name) END,
  pt.amount_type      = CASE WHEN trim(coalesce(row.amount_type, '')) = '' THEN null ELSE trim(row.amount_type) END,
  pt.sort_order       = toInteger(trim(row.sort_order)),
  pt.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  pt.displayLabel     = "PayrollType:" + trim(coalesce(row.payroll_name, '')),
  pt.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  pt.authoritative    = false,
  pt.src              = "payroll_types"
RETURN count(pt) AS payrollTypeProcessed;


// :StaffShift, not :Shift — `transport_school_shift` also becomes a shift and the two
// id spaces would collide on a shared label.
LOAD CSV WITH HEADERS FROM 'file:///tbluser_shift_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (sh:StaffShift {staffshiftId: toInteger(trim(row.id))})
ON CREATE SET
  sh.shift_name       = CASE WHEN trim(coalesce(row.shift_name, '')) = '' THEN null ELSE trim(row.shift_name) END,
  sh.start_time       = CASE WHEN trim(coalesce(row.start_time, '')) = '' THEN null ELSE trim(row.start_time) END,
  sh.end_time         = CASE WHEN trim(coalesce(row.end_time, '')) = '' THEN null ELSE trim(row.end_time) END,
  sh.displayLabel     = "StaffShift:" + trim(coalesce(row.shift_name, '')),
  sh.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  sh.src              = "tbluser_shift_master"
RETURN count(sh) AS shiftProcessed;


LOAD CSV WITH HEADERS FROM 'file:///employee_salary_structures.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ss:SalaryStructure {salarystructureId: toInteger(trim(row.id))})
ON CREATE SET
  ss.employee_id      = toInteger(trim(row.employee_id)),
  ss.year             = toInteger(trim(row.year)),
  ss.displayLabel     = "SalaryStructure:" + trim(coalesce(row.year, '')),
  ss.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  ss.authoritative    = false,
  ss.src              = "employee_salary_structures"
RETURN count(ss) AS salaryStructureProcessed;


LOAD CSV WITH HEADERS FROM 'file:///hrms_salary_certificate.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (sc:SalaryCertificate {salarycertificateId: toInteger(trim(row.id))})
ON CREATE SET
  sc.employee_id      = toInteger(trim(row.employee_id)),
  sc.department_id    = toInteger(trim(row.departement_id)),
  sc.payroll_type_id  = toInteger(trim(row.payroll_type_id)),
  sc.year             = toInteger(trim(row.year)),
  sc.month            = CASE WHEN trim(coalesce(row.month, '')) = '' THEN null ELSE trim(row.month) END,
  sc.reason           = CASE WHEN trim(coalesce(row.reason, '')) = '' THEN null ELSE trim(row.reason) END,
  sc.displayLabel     = "SalaryCertificate:" + trim(coalesce(row.year, '')),
  sc.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  sc.authoritative    = false,
  sc.src              = "hrms_salary_certificate"
RETURN count(sc) AS salaryCertificateProcessed;


// @section relationships
// ---------------------------------------------------------------------
// 3. RELATIONSHIPS
// ---------------------------------------------------------------------

// Person -> Role. :Role exists only under the uid convention, so it is matched on uid.
LOAD CSV WITH HEADERS FROM 'file:///tbluser.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.user_profile_id IS NOT NULL
  AND trim(row.user_profile_id) <> ''
  AND trim(row.user_profile_id) <> '0'

OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.id))})
WITH row, T, coalesce(t, s) AS person
WHERE person IS NOT NULL

MATCH (ro:Role {uid: 'Role:' + T + ':0:' + toString(toInteger(trim(row.user_profile_id)))})
MERGE (person)-[:HAS_ROLE]->(ro)
RETURN count(*) AS hasRole;


LOAD CSV WITH HEADERS FROM 'file:///tbluser.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.department_id IS NOT NULL
  AND trim(row.department_id) <> ''
  AND trim(row.department_id) <> '0'

OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.id))})
WITH row, T, coalesce(t, s) AS person
WHERE person IS NOT NULL

MATCH (d:Department {uid: 'Department:' + T + ':0:' + toString(toInteger(trim(row.department_id)))})
MERGE (person)-[:IN_DEPARTMENT]->(d)
RETURN count(*) AS inDepartment;


LOAD CSV WITH HEADERS FROM 'file:///tbluser.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.sub_institute_id IS NOT NULL AND trim(row.sub_institute_id) <> ''

OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.id))})
WITH row, T, coalesce(t, s) AS person
WHERE person IS NOT NULL

MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (person)-[:WORKS_AT]->(i)
RETURN count(*) AS worksAt;


// Reporting line. Both ends resolve through the same person rule.
LOAD CSV WITH HEADERS FROM 'file:///tbluser.csv' AS row
WITH row
WHERE row.reporting_manager_id IS NOT NULL
  AND trim(row.reporting_manager_id) <> ''
  AND trim(row.reporting_manager_id) <> '0'
  AND trim(row.reporting_manager_id) <> trim(row.id)

OPTIONAL MATCH (t1:Teacher {teacherId: toInteger(trim(row.id))})
OPTIONAL MATCH (s1:Staff {staffId: toInteger(trim(row.id))})
OPTIONAL MATCH (t2:Teacher {teacherId: toInteger(trim(row.reporting_manager_id))})
OPTIONAL MATCH (s2:Staff {staffId: toInteger(trim(row.reporting_manager_id))})
WITH coalesce(t1, s1) AS person, coalesce(t2, s2) AS manager
WHERE person IS NOT NULL AND manager IS NOT NULL

MERGE (person)-[:REPORTS_TO]->(manager)
RETURN count(*) AS reportsTo;


LOAD CSV WITH HEADERS FROM 'file:///hrms_holidays.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MATCH (h:Holiday {holidayId: toInteger(trim(row.id))})
MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})
MERGE (i)-[:HAS_HOLIDAY]->(h)
RETURN count(*) AS hasHoliday;


// Person -> LeaveType, one edge per leave application. The row id is part of the
// MERGE pattern so a re-run matches the same edge instead of adding another.
LOAD CSV WITH HEADERS FROM 'file:///hrms_emp_leaves.csv' AS row
WITH row
WHERE row.user_id IS NOT NULL AND trim(row.user_id) <> '' AND trim(row.user_id) <> '0'
  AND row.leave_type_id IS NOT NULL AND trim(row.leave_type_id) <> ''

OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.user_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.user_id))})
WITH row, coalesce(t, s) AS person
WHERE person IS NOT NULL

MATCH (lt:LeaveType {leavetypeId: toInteger(trim(row.leave_type_id))})

MERGE (person)-[r:TOOK_LEAVE {leaveId: toInteger(trim(row.id))}]->(lt)
ON CREATE SET
  r.from_date        = CASE WHEN trim(coalesce(row.from_date, '')) = '' THEN null ELSE trim(row.from_date) END,
  r.to_date          = CASE WHEN trim(coalesce(row.to_date, '')) = '' THEN null ELSE trim(row.to_date) END,
  r.day_type         = CASE WHEN trim(coalesce(row.day_type, '')) = '' THEN null ELSE trim(row.day_type) END,
  r.slot             = CASE WHEN trim(coalesce(row.slot, '')) = '' THEN null ELSE trim(row.slot) END,
  r.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "hrms_emp_leaves"
RETURN count(r) AS tookLeave;


LOAD CSV WITH HEADERS FROM 'file:///hrms_leave_allocation.csv' AS row
WITH row
WHERE row.employee_id IS NOT NULL AND trim(row.employee_id) <> '' AND trim(row.employee_id) <> '0'
  AND row.leave_type_id IS NOT NULL AND trim(row.leave_type_id) <> ''

OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.employee_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.employee_id))})
WITH row, coalesce(t, s) AS person
WHERE person IS NOT NULL

MATCH (lt:LeaveType {leavetypeId: toInteger(trim(row.leave_type_id))})

MERGE (person)-[r:ALLOCATED_LEAVE {year: toInteger(trim(row.year))}]->(lt)
ON CREATE SET
  r.value            = toFloat(trim(row.value)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "hrms_leave_allocation"
RETURN count(r) AS allocatedLeave;


LOAD CSV WITH HEADERS FROM 'file:///employee_salary_structures.csv' AS row
WITH row WHERE row.employee_id IS NOT NULL AND trim(row.employee_id) <> '' AND trim(row.employee_id) <> '0'
OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.employee_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.employee_id))})
WITH row, coalesce(t, s) AS person
WHERE person IS NOT NULL
MATCH (ss:SalaryStructure {salarystructureId: toInteger(trim(row.id))})
MERGE (person)-[r:HAS_SALARY_STRUCTURE]->(ss)
ON CREATE SET r.authoritative = false
RETURN count(r) AS hasSalaryStructure;


LOAD CSV WITH HEADERS FROM 'file:///hrms_salary_certificate.csv' AS row
WITH row WHERE row.employee_id IS NOT NULL AND trim(row.employee_id) <> '' AND trim(row.employee_id) <> '0'
OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.employee_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.employee_id))})
WITH row, coalesce(t, s) AS person
WHERE person IS NOT NULL
MATCH (sc:SalaryCertificate {salarycertificateId: toInteger(trim(row.id))})
MERGE (person)-[r:HAS_SALARY_CERTIFICATE]->(sc)
ON CREATE SET r.authoritative = false
RETURN count(r) AS hasSalaryCertificate;


// Person -> Subject, the explicit teacher/subject mapping (11 rows).
LOAD CSV WITH HEADERS FROM 'file:///mapped_teachers.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.teacher_id IS NOT NULL AND trim(row.teacher_id) <> ''
  AND row.subject_id IS NOT NULL AND trim(row.subject_id) <> ''

OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.teacher_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.teacher_id))})
WITH row, T, coalesce(t, s) AS person
WHERE person IS NOT NULL

OPTIONAL MATCH (s1:Subject {subId: toInteger(trim(row.subject_id))})
OPTIONAL MATCH (s2:Subject {uid: 'Subject:' + T + ':0:' + toString(toInteger(trim(row.subject_id)))})
WITH row, person, coalesce(s1, s2) AS sub
WHERE sub IS NOT NULL

MERGE (person)-[r:MAPPED_TO {syear: toInteger(trim(row.syear))}]->(sub)
ON CREATE SET
  r.standard_id      = toInteger(trim(row.standard_id)),
  r.teaching_load    = CASE WHEN trim(coalesce(row.teaching_load, '')) = '' THEN null ELSE trim(row.teaching_load) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "mapped_teachers"
RETURN count(r) AS mappedTo;


// Person -> Division, the class teacher.
LOAD CSV WITH HEADERS FROM 'file:///class_teacher.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.division_id IS NOT NULL AND trim(row.division_id) <> '' AND trim(row.division_id) <> '0'

OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.teacher_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.teacher_id))})
WITH row, T, coalesce(t, s) AS person
WHERE person IS NOT NULL

MATCH (d:Division {uid: 'Division:' + T + ':0:' + toString(toInteger(trim(row.division_id)))})

MERGE (person)-[r:CLASS_TEACHER_OF {syear: toInteger(trim(row.syear))}]->(d)
ON CREATE SET
  r.standard_id      = toInteger(trim(row.standard_id)),
  r.grade_id         = toInteger(trim(row.grade_id)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "class_teacher"
RETURN count(r) AS classTeacherOf;


// @section aggregates
// ---------------------------------------------------------------------
// 4. AGGREGATE EDGES
// ---------------------------------------------------------------------

// TEACHES_SUBJECT, not TEACHES: the reference layer already uses TEACHES for
// (:Content)-[:TEACHES]->(:Concept) and (:LearningContent)-[:TEACHES]->(:Concept).
// Overloading it would make both unreadable and would move a protected count.
// 102,686 timetable rows -> 24,053 (teacher, subject, class, year) edges.
LOAD CSV WITH HEADERS FROM 'file:///timetable_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.teacher_id IS NOT NULL AND row.subject_id IS NOT NULL

OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.teacher_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.teacher_id))})
WITH row, T, coalesce(t, s) AS person
WHERE person IS NOT NULL

OPTIONAL MATCH (s1:Subject {subId: toInteger(trim(row.subject_id))})
OPTIONAL MATCH (s2:Subject {uid: 'Subject:' + T + ':0:' + toString(toInteger(trim(row.subject_id)))})
WITH row, person, coalesce(s1, s2) AS sub
WHERE sub IS NOT NULL

MERGE (person)-[r:TEACHES_SUBJECT {syear: toInteger(trim(row.syear)),
                                   division_id: toInteger(trim(row.division_id))}]->(sub)
ON CREATE SET
  r.standard_id      = toInteger(trim(row.standard_id)),
  r.periods          = toInteger(trim(row.periods)),
  r.week_days        = toInteger(trim(row.week_days)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "timetable"
RETURN count(r) AS teachesSubject;


// The same aggregate seen from the class side.
LOAD CSV WITH HEADERS FROM 'file:///timetable_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.division_id IS NOT NULL AND trim(row.division_id) <> '' AND trim(row.division_id) <> '0'

OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.teacher_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.teacher_id))})
WITH row, T, coalesce(t, s) AS person
WHERE person IS NOT NULL

MATCH (d:Division {uid: 'Division:' + T + ':0:' + toString(toInteger(trim(row.division_id)))})

MERGE (person)-[r:TEACHES_CLASS {syear: toInteger(trim(row.syear)),
                                 subject_id: toInteger(trim(row.subject_id))}]->(d)
ON CREATE SET
  r.periods          = toInteger(trim(row.periods)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "timetable"
RETURN count(r) AS teachesClass;


// Division -> Subject: what a class is taught, independent of who teaches it.
LOAD CSV WITH HEADERS FROM 'file:///timetable_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.division_id IS NOT NULL AND trim(row.division_id) <> '' AND trim(row.division_id) <> '0'
  AND row.subject_id IS NOT NULL

MATCH (d:Division {uid: 'Division:' + T + ':0:' + toString(toInteger(trim(row.division_id)))})
OPTIONAL MATCH (s1:Subject {subId: toInteger(trim(row.subject_id))})
OPTIONAL MATCH (s2:Subject {uid: 'Subject:' + T + ':0:' + toString(toInteger(trim(row.subject_id)))})
WITH row, d, coalesce(s1, s2) AS sub
WHERE sub IS NOT NULL

MERGE (d)-[r:SCHEDULED {syear: toInteger(trim(row.syear))}]->(sub)
ON CREATE SET
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "timetable"
RETURN count(r) AS scheduled;


// Substitute teaching, person -> person.
LOAD CSV WITH HEADERS FROM 'file:///proxy_master_agg.csv' AS row
WITH row
WHERE row.teacher_id IS NOT NULL AND row.proxy_teacher_id IS NOT NULL

OPTIONAL MATCH (t1:Teacher {teacherId: toInteger(trim(row.proxy_teacher_id))})
OPTIONAL MATCH (s1:Staff {staffId: toInteger(trim(row.proxy_teacher_id))})
OPTIONAL MATCH (t2:Teacher {teacherId: toInteger(trim(row.teacher_id))})
OPTIONAL MATCH (s2:Staff {staffId: toInteger(trim(row.teacher_id))})
WITH row, coalesce(t1, s1) AS proxy, coalesce(t2, s2) AS regular
WHERE proxy IS NOT NULL AND regular IS NOT NULL

MERGE (proxy)-[r:SUBSTITUTED_FOR {syear: toInteger(trim(row.syear))}]->(regular)
ON CREATE SET
  r.proxies          = toInteger(trim(row.proxies)),
  r.first_date       = CASE WHEN trim(coalesce(row.first_date, '')) = '' THEN null ELSE trim(row.first_date) END,
  r.last_date        = CASE WHEN trim(coalesce(row.last_date, '')) = '' THEN null ELSE trim(row.last_date) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "proxy_master"
RETURN count(r) AS substitutedFor;


// Monthly attendance. 356,872 punches -> 16,056 edges. The target is the tenant: a
// punch date maps to a calendar month, and :AcademicYear here is a TERM (2-4 per
// year), so anchoring to it would be a guess.
LOAD CSV WITH HEADERS FROM 'file:///hrms_attendances_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.user_id IS NOT NULL

OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.user_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.user_id))})
WITH row, T, coalesce(t, s) AS person
WHERE person IS NOT NULL

MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})

MERGE (person)-[r:ATTENDANCE_MONTH {year: toInteger(trim(row.yr)),
                                    month: toInteger(trim(row.mth))}]->(i)
ON CREATE SET
  r.days_present     = toInteger(trim(row.days_present)),
  r.punches          = toInteger(trim(row.punches)),
  r.overtime_total   = toFloat(trim(row.overtime_total)),
  r.first_day        = CASE WHEN trim(coalesce(row.first_day, '')) = '' THEN null ELSE trim(row.first_day) END,
  r.last_day         = CASE WHEN trim(coalesce(row.last_day, '')) = '' THEN null ELSE trim(row.last_day) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "hrms_attendances"
RETURN count(r) AS attendanceMonth;


// Payroll deductions, counts only.
LOAD CSV WITH HEADERS FROM 'file:///hrms_emp_payroll_deduction_agg.csv' AS row
WITH row WHERE row.employee_id IS NOT NULL AND row.deduction_type IS NOT NULL

OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.employee_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.employee_id))})
WITH row, coalesce(t, s) AS person
WHERE person IS NOT NULL

MATCH (pt:PayrollType {payrolltypeId: toInteger(trim(row.deduction_type))})

MERGE (person)-[r:DEDUCTION {year: toInteger(trim(row.year))}]->(pt)
ON CREATE SET
  r.deductions       = toInteger(trim(row.deductions)),
  r.months           = toInteger(trim(row.months)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.authoritative    = false,
  r.src              = "hrms_emp_payroll_deduction"
RETURN count(r) AS deduction;


// Payroll runs per year, counts only.
LOAD CSV WITH HEADERS FROM 'file:///employee_monthly_salary_data_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.employee_id IS NOT NULL

OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.employee_id))})
OPTIONAL MATCH (s:Staff {staffId: toInteger(trim(row.employee_id))})
WITH row, T, coalesce(t, s) AS person
WHERE person IS NOT NULL

MATCH (i:Institute {uid: 'Institute:' + T + ':0:' + T})

MERGE (person)-[r:PAYROLL_YEAR {year: toInteger(trim(row.year))}]->(i)
ON CREATE SET
  r.months           = toInteger(trim(row.months)),
  r.runs             = toInteger(trim(row.runs)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.authoritative    = false,
  r.src              = "employee_monthly_salary_data"
RETURN count(r) AS payrollYear;


// Student leave. `leave_applications` is named like an HR table but keys on
// tblstudent.id (18,925 of 20,396; 592 against tbluser) — it is the pupil leave
// module, so it hangs off :StuDetail.
LOAD CSV WITH HEADERS FROM 'file:///leave_applications_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.student_id IS NOT NULL AND row.academic_year_id IS NOT NULL

MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (ay:AcademicYear {uid: 'AcademicYear:' + T + ':' + toString(toInteger(trim(row.syear)))
                              + ':' + toString(toInteger(trim(row.academic_year_id)))})

MERGE (sd)-[r:APPLIED_FOR_LEAVE {syear: toInteger(trim(row.syear))}]->(ay)
ON CREATE SET
  r.applications     = toInteger(trim(row.applications)),
  r.approved         = toInteger(trim(row.approved)),
  r.first_apply      = CASE WHEN trim(coalesce(row.first_apply, '')) = '' THEN null ELSE trim(row.first_apply) END,
  r.last_apply       = CASE WHEN trim(coalesce(row.last_apply, '')) = '' THEN null ELSE trim(row.last_apply) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "leave_applications"
RETURN count(r) AS appliedForLeave;


// @section derived
// ---------------------------------------------------------------------
// 5. DERIVED EDGES — from properties already on the nodes.
// ---------------------------------------------------------------------

// `subject_ids` is a comma-separated list on the person node. The reference script
// used this shape for :Teacher; it applies to :Staff identically. Kept distinct from
// TEACHES_SUBJECT because this is what the staff record CLAIMS, while TEACHES_SUBJECT
// is what the timetable actually schedules.
MATCH (p:Staff)
WHERE p.subject_ids IS NOT NULL AND trim(p.subject_ids) <> ''
UNWIND split(p.subject_ids, ',') AS sid
WITH p, toInteger(trim(sid)) AS subject_id
WHERE subject_id IS NOT NULL AND subject_id > 0
OPTIONAL MATCH (s1:Subject {subId: subject_id})
OPTIONAL MATCH (s2:Subject {uid: 'Subject:' + toString(p.sub_institute_id) + ':0:' + toString(subject_id)})
WITH p, coalesce(s1, s2) AS sub
WHERE sub IS NOT NULL
MERGE (p)-[r:TEACHES_SUBJECT_DECLARED]->(sub)
ON CREATE SET r.src = "tbluser.subject_ids"
RETURN count(r) AS declaredSubjects;


// @section verify
// ---------------------------------------------------------------------
// 6. VERIFY
// ---------------------------------------------------------------------

MATCH (s:Staff) RETURN 'Staff nodes' AS check, count(s) AS n;
MATCH (h:Holiday) RETURN 'Holiday nodes' AS check, count(h) AS n;
MATCH (lt:LeaveType) RETURN 'LeaveType nodes' AS check, count(lt) AS n;
MATCH (pt:PayrollType) RETURN 'PayrollType nodes' AS check, count(pt) AS n;
MATCH (ss:SalaryStructure) RETURN 'SalaryStructure nodes' AS check, count(ss) AS n;
MATCH (p)-[r:HAS_ROLE]->(:Role) WHERE p:Teacher OR p:Staff RETURN 'HAS_ROLE' AS check, count(r) AS n;
MATCH (p)-[r:IN_DEPARTMENT]->(:Department) WHERE p:Teacher OR p:Staff RETURN 'IN_DEPARTMENT' AS check, count(r) AS n;
MATCH (p)-[r:WORKS_AT]->(:Institute) WHERE p:Teacher OR p:Staff RETURN 'WORKS_AT' AS check, count(r) AS n;
MATCH (p)-[r:REPORTS_TO]->() WHERE p:Teacher OR p:Staff RETURN 'REPORTS_TO' AS check, count(r) AS n;
MATCH (p)-[r:TOOK_LEAVE]->(:LeaveType) WHERE p:Teacher OR p:Staff RETURN 'TOOK_LEAVE' AS check, count(r) AS n;
MATCH (p)-[r:ALLOCATED_LEAVE]->(:LeaveType) WHERE p:Teacher OR p:Staff RETURN 'ALLOCATED_LEAVE' AS check, count(r) AS n;
MATCH (p)-[r:TEACHES_SUBJECT]->(:Subject) WHERE p:Teacher OR p:Staff RETURN 'TEACHES_SUBJECT' AS check, count(r) AS n;
MATCH (p)-[r:TEACHES_CLASS]->(:Division) WHERE p:Teacher OR p:Staff RETURN 'TEACHES_CLASS' AS check, count(r) AS n;
MATCH (:Division)-[r:SCHEDULED]->(:Subject) RETURN 'SCHEDULED' AS check, count(r) AS n;
MATCH (p)-[r:CLASS_TEACHER_OF]->(:Division) WHERE p:Teacher OR p:Staff RETURN 'CLASS_TEACHER_OF' AS check, count(r) AS n;
MATCH (p)-[r:SUBSTITUTED_FOR]->() WHERE p:Teacher OR p:Staff RETURN 'SUBSTITUTED_FOR' AS check, count(r) AS n;
MATCH (p)-[r:ATTENDANCE_MONTH]->(:Institute) WHERE p:Teacher OR p:Staff RETURN 'ATTENDANCE_MONTH' AS check, count(r) AS n;
MATCH (p)-[r:DEDUCTION]->(:PayrollType) WHERE p:Teacher OR p:Staff RETURN 'DEDUCTION' AS check, count(r) AS n;
MATCH (p)-[r:PAYROLL_YEAR]->(:Institute) WHERE p:Teacher OR p:Staff RETURN 'PAYROLL_YEAR' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:APPLIED_FOR_LEAVE]->(:AcademicYear) RETURN 'APPLIED_FOR_LEAVE' AS check, count(r) AS n;
MATCH (s:Staff) WHERE NOT (s)--() RETURN 'Staff with no edge' AS check, count(s) AS n;
