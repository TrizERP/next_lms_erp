// =====================================================================
//  PEOPLE — completing the student layer the k12 script started
//  Style and key convention follow k12_cypher.txt / reference_code.txt exactly.
//
//  Run from this repo:   php artisan neo4j:cypher --module=people
//  Run on the server:    cypher-shell -a bolt://localhost:7688 -u neo4j -p admin \
//                          -f database/neo4j/cypher/10_people.cypher
//                        (needs the CSVs from `neo4j:csv-export --module=people --sql`
//                         in the import directory)
//
//  ADDITIVE. Every node write is MERGE + ON CREATE SET, so a node that already exists
//  is matched and left exactly as it is. No DELETE, no REMOVE, no bare SET on an
//  existing node. The two protected relationship types that grow here — HAS_STUDENT
//  and ENROLLED_IN — are the reference script's own statements, run over the whole
//  176,458-row table instead of the 5,409-row CSV subset it was given.
//
//  ENDPOINT RESOLVER
//  Some parents exist twice in this graph: once keyed the k12 way (Standard.stId, an
//  integer) and once keyed `uid` by the earlier batch pipeline
//  (`Standard:<tenant>:0:<id>`). Matching only one convention silently drops every
//  edge whose parent happens to live under the other. So each such lookup is
//
//      OPTIONAL MATCH (n1:Label {nativeKey: toInteger(...)})
//      OPTIONAL MATCH (n2:Label {uid: 'Label:' + T + ':0:' + ...})
//      WITH ..., coalesce(n1, n2) AS parent WHERE parent IS NOT NULL
//
//  which prefers the k12 node, falls back to the uid twin, creates neither, and
//  yields exactly one edge per row.
// =====================================================================


// @section constraints
// ---------------------------------------------------------------------
// 1. CONSTRAINTS — all already exist on this graph; IF NOT EXISTS makes the
//    script safe to run on a fresh instance too.
// ---------------------------------------------------------------------

CREATE CONSTRAINT student_stuId_unique IF NOT EXISTS
FOR (stu:Student) REQUIRE stu.stuId IS UNIQUE;

CREATE CONSTRAINT stu_details_sdId_unique IF NOT EXISTS
FOR (sd:StuDetail) REQUIRE sd.sdId IS UNIQUE;


// @section nodes
// ---------------------------------------------------------------------
// 2. NODES
// ---------------------------------------------------------------------

// :Student is the ENROLMENT, not the person — stuId = tblstudent_enrollment.id, and
// student_id points at the master (:StuDetail). That is the model the live graph
// already uses; these statements extend it rather than change it.
// Expected: 176,458 processed, ~170,869 created (5,589 already present).
LOAD CSV WITH HEADERS FROM 'file:///tblstudent_enrollment.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL

MERGE (stu:Student {stuId: toInteger(trim(row.id))})

ON CREATE SET
  stu.student_id       = toInteger(trim(row.student_id)),
  stu.displayLabel     = "Student:" + trim(row.student_id),
  stu.syear            = toInteger(trim(row.syear)),
  stu.standard_id      = toInteger(trim(row.standard_id)),
  stu.grade_id         = toInteger(trim(row.grade_id)),
  stu.section_id       = toInteger(trim(row.section_id)),
  stu.term_id          = toInteger(trim(row.term_id)),
  stu.roll_no          = CASE WHEN trim(coalesce(row.roll_no, '')) = '' THEN null ELSE trim(row.roll_no) END,
  stu.enrollment_code  = CASE WHEN trim(coalesce(row.enrollment_code, '')) = '' THEN null ELSE trim(row.enrollment_code) END,
  stu.student_quota    = toInteger(trim(row.student_quota)),
  stu.house_id         = toInteger(trim(row.house_id)),
  stu.start_date       = CASE WHEN trim(coalesce(row.start_date, '')) = '' THEN null ELSE trim(row.start_date) END,
  stu.end_date         = CASE WHEN trim(coalesce(row.end_date, '')) = '' THEN null ELSE trim(row.end_date) END,
  stu.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  stu.src              = "tblstudent_enrollment"

RETURN count(stu) AS studentProcessed;


// @section relationships
// ---------------------------------------------------------------------
// 3. RELATIONSHIPS
// ---------------------------------------------------------------------

// StuDetail -> Student. The k12 statement, over the full table.
// 9 of 176,458 enrolments name a student_id with no tblstudent row; they drop.
LOAD CSV WITH HEADERS FROM 'file:///tblstudent_enrollment.csv' AS row
WITH row
WHERE row.student_id IS NOT NULL
  AND row.id IS NOT NULL

MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (stu:Student {stuId: toInteger(trim(row.id))})

MERGE (sd)-[:HAS_STUDENT]->(stu)

RETURN count(*) AS hasStudent;


// Student -> Standard. The k12 statement plus the uid fallback, because 826 of the
// 879 :Standard nodes carry no stId.
LOAD CSV WITH HEADERS FROM 'file:///tblstudent_enrollment.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.standard_id IS NOT NULL
  AND trim(row.standard_id) <> ''
  AND trim(row.standard_id) <> '0'
  AND row.id IS NOT NULL

MATCH (stu:Student {stuId: toInteger(trim(row.id))})
OPTIONAL MATCH (n1:Standard {stId: toInteger(trim(row.standard_id))})
OPTIONAL MATCH (n2:Standard {uid: 'Standard:' + T + ':0:' + toString(toInteger(trim(row.standard_id)))})
WITH stu, coalesce(n1, n2) AS st
WHERE st IS NOT NULL

MERGE (stu)-[:ENROLLED_IN]->(st)

RETURN count(*) AS enrolledIn;


// Student -> Division. `section_id` is the division FK, and :Division exists only
// under the uid convention (492 nodes, no native key).
LOAD CSV WITH HEADERS FROM 'file:///tblstudent_enrollment.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.section_id IS NOT NULL
  AND trim(row.section_id) <> ''
  AND trim(row.section_id) <> '0'

MATCH (stu:Student {stuId: toInteger(trim(row.id))})
MATCH (d:Division {uid: 'Division:' + T + ':0:' + toString(toInteger(trim(row.section_id)))})

MERGE (stu)-[:IN_DIVISION]->(d)

RETURN count(*) AS inDivision;


// StuDetail -> Subject, the optional/elective subjects a learner takes.
LOAD CSV WITH HEADERS FROM 'file:///student_optional_subject_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.student_id IS NOT NULL
  AND row.subject_id IS NOT NULL

MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
OPTIONAL MATCH (s1:Subject {subId: toInteger(trim(row.subject_id))})
OPTIONAL MATCH (s2:Subject {uid: 'Subject:' + T + ':0:' + toString(toInteger(trim(row.subject_id)))})
WITH row, sd, coalesce(s1, s2) AS sub
WHERE sub IS NOT NULL

MERGE (sd)-[r:STUDIES {syear: toInteger(trim(row.syear))}]->(sub)
ON CREATE SET
  r.level            = CASE WHEN trim(coalesce(row.level, '')) = '' THEN null ELSE trim(row.level) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "student_optional_subject"

RETURN count(r) AS studies;


// Sibling pairs. `siblings_id` is a comma-separated list of tblstudent ids, so the
// row expands to every unordered pair within it. `ia < ib` keeps one edge per pair.
LOAD CSV WITH HEADERS FROM 'file:///tblstudent_siblings.csv' AS row
WITH row
WHERE row.siblings_id IS NOT NULL AND trim(row.siblings_id) <> ''

UNWIND split(row.siblings_id, ',') AS a
UNWIND split(row.siblings_id, ',') AS b

WITH row, toInteger(trim(a)) AS ia, toInteger(trim(b)) AS ib
WHERE ia IS NOT NULL AND ib IS NOT NULL AND ia < ib

MATCH (x:StuDetail {sdId: ia})
MATCH (y:StuDetail {sdId: ib})

MERGE (x)-[r:SIBLING_OF]->(y)
ON CREATE SET r.src = "tblstudent_siblings"

RETURN count(r) AS siblingOf;


// @section aggregates
// ---------------------------------------------------------------------
// 4. AGGREGATE EDGES — one edge per group, grouped in SQL at export.
// ---------------------------------------------------------------------

// Term attendance. Every row resolves to an exact :AcademicYear term node
// (53,035 of 53,035 on the (tenant, syear, term_id) join).
LOAD CSV WITH HEADERS FROM 'file:///result_student_attendance_master_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.student_id IS NOT NULL
  AND row.academic_year_id IS NOT NULL

MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (ay:AcademicYear {uid: 'AcademicYear:' + T + ':' + toString(toInteger(trim(row.syear)))
                              + ':' + toString(toInteger(trim(row.academic_year_id)))})

MERGE (sd)-[r:ATTENDANCE {term_id: toInteger(trim(row.term_id))}]->(ay)
ON CREATE SET
  r.syear            = toInteger(trim(row.syear)),
  r.present          = toInteger(trim(row.attendance)),
  r.working_days     = toInteger(trim(row.working_day)),
  r.percentage       = toFloat(trim(row.percentage)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "result_student_attendance_master"

RETURN count(r) AS attendance;


// Discipline incidents, aggregated per (student, year, category).
// `dicipline.dicipline` is free text, so the category is an edge property; there is
// no id in it to match a :DisciplineCategory node against.
LOAD CSV WITH HEADERS FROM 'file:///dicipline_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.student_id IS NOT NULL
  AND row.academic_year_id IS NOT NULL

MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (ay:AcademicYear {uid: 'AcademicYear:' + T + ':' + toString(toInteger(trim(row.syear)))
                              + ':' + toString(toInteger(trim(row.academic_year_id)))})

MERGE (sd)-[r:HAS_INCIDENT {category: trim(row.category)}]->(ay)
ON CREATE SET
  r.syear            = toInteger(trim(row.syear)),
  r.incidents        = toInteger(trim(row.incidents)),
  r.first_date       = CASE WHEN trim(coalesce(row.first_date, '')) = '' THEN null ELSE trim(row.first_date) END,
  r.last_date        = CASE WHEN trim(coalesce(row.last_date, '')) = '' THEN null ELSE trim(row.last_date) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "dicipline"

RETURN count(r) AS hasIncident;


// @section derived
// ---------------------------------------------------------------------
// 5. DERIVED EDGES — pure Cypher, no CSV.
// ---------------------------------------------------------------------

// The 10,279 :Guardian nodes have no relationship at all today. Their `student_id`
// is a string property holding tblstudent.id, so the link is derivable in the graph.
MATCH (g:Guardian)
WHERE g.student_id IS NOT NULL AND toInteger(g.student_id) > 0
MATCH (sd:StuDetail {sdId: toInteger(g.student_id)})
MERGE (g)-[r:GUARDIAN_OF]->(sd)
ON CREATE SET r.src = "tblstudent_family_history"
RETURN count(r) AS guardianOf;


// @section verify
// ---------------------------------------------------------------------
// 6. VERIFY — read-only counts.
// ---------------------------------------------------------------------

MATCH (stu:Student) RETURN 'Student nodes' AS check, count(stu) AS n;
MATCH (:StuDetail)-[r:HAS_STUDENT]->(:Student) RETURN 'HAS_STUDENT' AS check, count(r) AS n;
MATCH (:Student)-[r:ENROLLED_IN]->(:Standard) RETURN 'ENROLLED_IN' AS check, count(r) AS n;
MATCH (:Student)-[r:IN_DIVISION]->(:Division) RETURN 'IN_DIVISION' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:STUDIES]->(:Subject) RETURN 'STUDIES' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:SIBLING_OF]->(:StuDetail) RETURN 'SIBLING_OF' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:ATTENDANCE]->(:AcademicYear) RETURN 'ATTENDANCE' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:HAS_INCIDENT]->(:AcademicYear) RETURN 'HAS_INCIDENT' AS check, count(r) AS n;
MATCH (:Guardian)-[r:GUARDIAN_OF]->(:StuDetail) RETURN 'GUARDIAN_OF' AS check, count(r) AS n;
MATCH (stu:Student) WHERE NOT (stu)--() RETURN 'Student with no edge' AS check, count(stu) AS n;
