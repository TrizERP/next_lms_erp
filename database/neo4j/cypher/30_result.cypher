// =====================================================================
//  RESULT — exams, exam types, grades, co-scholastic, marks
//  Style and key convention follow k12_cypher.txt / reference_code.txt exactly.
//
//      php artisan neo4j:csv-export --module=result
//      php artisan neo4j:cypher --module=result
//
//  LABEL CHOICES THAT AVOID A COLLISION
//   :Examination, not :Exam — the career-intelligence seed owns `:Exam {exam_id}`
//     with a curated string vocabulary ('EXAM-NATA'); 33,760 integer-keyed school
//     exams do not belong in it.
//   :CoScholasticParent, separate from :CoScholasticArea — `result_co_scholastic`
//     and `result_co_scholastic_parent` both start their ids at 1.
//
//  SCORED attaches to :Examination because `result_personalize_marks.exam_id`
//  resolves to `result_create_exam.id` on 3,323 of 3,334 distinct values, against
//  525 for `result_exam_master.Id`.
//
//  ADDITIVE. MERGE + ON CREATE SET only. No protected relationship type is written.
// =====================================================================


// @section constraints
// ---------------------------------------------------------------------
// 1. CONSTRAINTS
// ---------------------------------------------------------------------

CREATE CONSTRAINT examination_examinationId_unique IF NOT EXISTS
FOR (e:Examination) REQUIRE e.examinationId IS UNIQUE;

CREATE CONSTRAINT examtype_examtypeId_unique IF NOT EXISTS
FOR (et:ExamType) REQUIRE et.examtypeId IS UNIQUE;

CREATE CONSTRAINT examtypecategory_examtypecategoryId_unique IF NOT EXISTS
FOR (ec:ExamTypeCategory) REQUIRE ec.examtypecategoryId IS UNIQUE;

CREATE CONSTRAINT grade_gradeId_unique IF NOT EXISTS
FOR (g:Grade) REQUIRE g.gradeId IS UNIQUE;

CREATE CONSTRAINT coscholasticarea_coscholasticareaId_unique IF NOT EXISTS
FOR (ca:CoScholasticArea) REQUIRE ca.coscholasticareaId IS UNIQUE;

CREATE CONSTRAINT coscholasticparent_coscholasticparentId_unique IF NOT EXISTS
FOR (cp:CoScholasticParent) REQUIRE cp.coscholasticparentId IS UNIQUE;

CREATE CONSTRAINT coscholasticgradeband_coscholasticgradebandId_unique IF NOT EXISTS
FOR (cb:CoScholasticGradeBand) REQUIRE cb.coscholasticgradebandId IS UNIQUE;

CREATE CONSTRAINT skillset_skillsetId_unique IF NOT EXISTS
FOR (sk:Skillset) REQUIRE sk.skillsetId IS UNIQUE;

CREATE CONSTRAINT activity_activityId_unique IF NOT EXISTS
FOR (a:Activity) REQUIRE a.activityId IS UNIQUE;

CREATE CONSTRAINT activitygroup_activitygroupId_unique IF NOT EXISTS
FOR (ag:ActivityGroup) REQUIRE ag.activitygroupId IS UNIQUE;

CREATE CONSTRAINT remarktemplate_remarktemplateId_unique IF NOT EXISTS
FOR (rt:RemarkTemplate) REQUIRE rt.remarktemplateId IS UNIQUE;

CREATE CONSTRAINT examschedule_examscheduleId_unique IF NOT EXISTS
FOR (es:ExamSchedule) REQUIRE es.examscheduleId IS UNIQUE;


// @section nodes
// ---------------------------------------------------------------------
// 2. NODES
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///result_create_exam.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (e:Examination {examinationId: toInteger(trim(row.id))})
ON CREATE SET
  e.title              = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  e.exam_type_id       = toInteger(trim(row.exam_id)),
  e.standard_id        = toInteger(trim(row.standard_id)),
  e.subject_id         = toInteger(trim(row.subject_id)),
  e.term_id            = toInteger(trim(row.term_id)),
  e.syear              = toInteger(trim(row.syear)),
  e.points             = toFloat(trim(row.points)),
  e.con_point          = toFloat(trim(row.con_point)),
  e.marks_type         = CASE WHEN trim(coalesce(row.marks_type, '')) = '' THEN null ELSE trim(row.marks_type) END,
  e.medium             = CASE WHEN trim(coalesce(row.medium, '')) = '' THEN null ELSE trim(row.medium) END,
  e.report_card_status = CASE WHEN trim(coalesce(row.report_card_status, '')) = '' THEN null ELSE trim(row.report_card_status) END,
  e.exam_date          = CASE WHEN trim(coalesce(row.exam_date, '')) = '' THEN null ELSE trim(row.exam_date) END,
  e.sort_order         = toInteger(trim(row.sort_order)),
  e.displayLabel       = "Examination:" + trim(coalesce(row.title, '')),
  e.sub_institute_id   = toInteger(trim(row.sub_institute_id)),
  e.src                = "result_create_exam"
RETURN count(e) AS examinationProcessed;


LOAD CSV WITH HEADERS FROM 'file:///result_exam_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (et:ExamType {examtypeId: toInteger(trim(row.id))})
ON CREATE SET
  et.code             = CASE WHEN trim(coalesce(row.code, '')) = '' THEN null ELSE trim(row.code) END,
  et.exam_type        = CASE WHEN trim(coalesce(row.exam_type, '')) = '' THEN null ELSE trim(row.exam_type) END,
  et.exam_title       = CASE WHEN trim(coalesce(row.exam_title, '')) = '' THEN null ELSE trim(row.exam_title) END,
  et.standard_id      = toInteger(trim(row.standard_id)),
  et.term_id          = toInteger(trim(row.term_id)),
  et.weightage        = toFloat(trim(row.weightage)),
  et.sort_order       = toInteger(trim(row.sort_order)),
  et.displayLabel     = "ExamType:" + trim(coalesce(row.exam_title, '')),
  et.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  et.src              = "result_exam_master"
RETURN count(et) AS examTypeProcessed;


LOAD CSV WITH HEADERS FROM 'file:///result_exam_type_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ec:ExamTypeCategory {examtypecategoryId: toInteger(trim(row.id))})
ON CREATE SET
  ec.code             = CASE WHEN trim(coalesce(row.code, '')) = '' THEN null ELSE trim(row.code) END,
  ec.exam_type        = CASE WHEN trim(coalesce(row.exam_type, '')) = '' THEN null ELSE trim(row.exam_type) END,
  ec.short_name       = CASE WHEN trim(coalesce(row.short_name, '')) = '' THEN null ELSE trim(row.short_name) END,
  ec.sort_order       = toInteger(trim(row.sort_order)),
  ec.displayLabel     = "ExamTypeCategory:" + trim(coalesce(row.exam_type, '')),
  ec.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  ec.src              = "result_exam_type_master"
RETURN count(ec) AS examTypeCategoryProcessed;


LOAD CSV WITH HEADERS FROM 'file:///grade_master_data.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (g:Grade {gradeId: toInteger(trim(row.id))})
ON CREATE SET
  g.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  g.grade_scheme_id  = toInteger(trim(row.grade_id)),
  g.breakoff         = toFloat(trim(row.breakoff)),
  g.gp               = toFloat(trim(row.gp)),
  g.comment          = CASE WHEN trim(coalesce(row.comment, '')) = '' THEN null ELSE trim(row.comment) END,
  g.syear            = toInteger(trim(row.syear)),
  g.sort_order       = toInteger(trim(row.sort_order)),
  g.displayLabel     = "Grade:" + trim(coalesce(row.title, '')),
  g.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  g.src              = "grade_master_data"
RETURN count(g) AS gradeProcessed;


LOAD CSV WITH HEADERS FROM 'file:///result_co_scholastic.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ca:CoScholasticArea {coscholasticareaId: toInteger(trim(row.id))})
ON CREATE SET
  ca.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  ca.parent_id        = toInteger(trim(row.parent_id)),
  ca.mark_type        = CASE WHEN trim(coalesce(row.mark_type, '')) = '' THEN null ELSE trim(row.mark_type) END,
  ca.max_mark         = toFloat(trim(row.max_mark)),
  ca.co_grade         = CASE WHEN trim(coalesce(row.co_grade, '')) = '' THEN null ELSE trim(row.co_grade) END,
  ca.standard_id      = toInteger(trim(row.standard_id)),
  ca.term_id          = toInteger(trim(row.term_id)),
  ca.sort_order       = toInteger(trim(row.sort_order)),
  ca.displayLabel     = "CoScholasticArea:" + trim(coalesce(row.title, '')),
  ca.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  ca.src              = "result_co_scholastic"
RETURN count(ca) AS coScholasticAreaProcessed;


LOAD CSV WITH HEADERS FROM 'file:///result_co_scholastic_parent.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (cp:CoScholasticParent {coscholasticparentId: toInteger(trim(row.id))})
ON CREATE SET
  cp.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  cp.part_no          = CASE WHEN trim(coalesce(row.part_no, '')) = '' THEN null ELSE trim(row.part_no) END,
  cp.part_name        = CASE WHEN trim(coalesce(row.part_name, '')) = '' THEN null ELSE trim(row.part_name) END,
  cp.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  cp.sort_order       = toInteger(trim(row.sort_order)),
  cp.displayLabel     = "CoScholasticParent:" + trim(coalesce(row.title, '')),
  cp.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  cp.src              = "result_co_scholastic_parent"
RETURN count(cp) AS coScholasticParentProcessed;


// Grade bands, not student marks — this table has no student column.
LOAD CSV WITH HEADERS FROM 'file:///result_co_scholastic_grades.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (cb:CoScholasticGradeBand {coscholasticgradebandId: toInteger(trim(row.id))})
ON CREATE SET
  cb.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  cb.break_off        = toFloat(trim(row.break_off)),
  cb.area_id          = toInteger(trim(row.map_id)),
  cb.displayLabel     = "CoScholasticGradeBand:" + trim(coalesce(row.title, '')),
  cb.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  cb.src              = "result_co_scholastic_grades"
RETURN count(cb) AS coScholasticGradeBandProcessed;


LOAD CSV WITH HEADERS FROM 'file:///result_skillset.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (sk:Skillset {skillsetId: toInteger(trim(row.id))})
ON CREATE SET
  sk.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  sk.main_title       = CASE WHEN trim(coalesce(row.main_title, '')) = '' THEN null ELSE trim(row.main_title) END,
  sk.skill_group      = CASE WHEN trim(coalesce(row.skill_group, '')) = '' THEN null ELSE trim(row.skill_group) END,
  sk.standard         = CASE WHEN trim(coalesce(row.standard, '')) = '' THEN null ELSE trim(row.standard) END,
  sk.sort_order       = toInteger(trim(row.sort_order)),
  sk.displayLabel     = "Skillset:" + trim(coalesce(row.title, '')),
  sk.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  sk.src              = "result_skillset"
RETURN count(sk) AS skillsetProcessed;


LOAD CSV WITH HEADERS FROM 'file:///result_activity_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (a:Activity {activityId: toInteger(trim(row.id))})
ON CREATE SET
  a.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  a.skillset_id      = toInteger(trim(row.skill_id)),
  a.standard         = CASE WHEN trim(coalesce(row.standard, '')) = '' THEN null ELSE trim(row.standard) END,
  a.term_id          = toInteger(trim(row.term_id)),
  a.sort_order       = toInteger(trim(row.sort_order)),
  a.displayLabel     = "Activity:" + trim(coalesce(row.title, '')),
  a.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  a.src              = "result_activity_master"
RETURN count(a) AS activityProcessed;


LOAD CSV WITH HEADERS FROM 'file:///result_activity_group.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ag:ActivityGroup {activitygroupId: toInteger(trim(row.id))})
ON CREATE SET
  ag.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  ag.activity_group   = CASE WHEN trim(coalesce(row.activity_group, '')) = '' THEN null ELSE trim(row.activity_group) END,
  ag.sort_order       = toInteger(trim(row.sort_order)),
  ag.displayLabel     = "ActivityGroup:" + trim(coalesce(row.title, '')),
  ag.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  ag.src              = "result_activity_group"
RETURN count(ag) AS activityGroupProcessed;


LOAD CSV WITH HEADERS FROM 'file:///result_remark_masters.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (rt:RemarkTemplate {remarktemplateId: toInteger(trim(row.id))})
ON CREATE SET
  rt.title             = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  rt.remark_status     = CASE WHEN trim(coalesce(row.remark_status, '')) = '' THEN null ELSE trim(row.remark_status) END,
  rt.marking_period_id = toInteger(trim(row.marking_period_id)),
  rt.syear             = toInteger(trim(row.syear)),
  rt.sort_order        = toInteger(trim(row.sort_order)),
  rt.displayLabel      = "RemarkTemplate:" + trim(coalesce(row.title, '')),
  rt.sub_institute_id  = toInteger(trim(row.sub_institute_id)),
  rt.src               = "result_remark_masters"
RETURN count(rt) AS remarkTemplateProcessed;


LOAD CSV WITH HEADERS FROM 'file:///exam_schedule.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (es:ExamSchedule {examscheduleId: toInteger(trim(row.id))})
ON CREATE SET
  es.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  es.standard_id      = toInteger(trim(row.standard_id)),
  es.division_id      = toInteger(trim(row.division_id)),
  es.exam_date        = CASE WHEN trim(coalesce(row.exam_date, '')) = '' THEN null ELSE trim(row.exam_date) END,
  es.syear            = toInteger(trim(row.syear)),
  es.displayLabel     = "ExamSchedule:" + trim(coalesce(row.title, '')),
  es.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  es.src              = "exam_schedule"
RETURN count(es) AS examScheduleProcessed;


// @section relationships
// ---------------------------------------------------------------------
// 3. RELATIONSHIPS
// ---------------------------------------------------------------------

// Examination -> ExamType. 1,106 of 1,148 distinct exam_id values resolve.
LOAD CSV WITH HEADERS FROM 'file:///result_create_exam.csv' AS row
WITH row WHERE row.exam_id IS NOT NULL AND trim(row.exam_id) <> '' AND trim(row.exam_id) <> '0'
MATCH (e:Examination {examinationId: toInteger(trim(row.id))})
MATCH (et:ExamType {examtypeId: toInteger(trim(row.exam_id))})
MERGE (e)-[:OF_EXAM_TYPE]->(et)
RETURN count(*) AS ofExamType;


LOAD CSV WITH HEADERS FROM 'file:///result_create_exam.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.standard_id IS NOT NULL AND trim(row.standard_id) <> '' AND trim(row.standard_id) <> '0'
MATCH (e:Examination {examinationId: toInteger(trim(row.id))})
OPTIONAL MATCH (n1:Standard {stId: toInteger(trim(row.standard_id))})
OPTIONAL MATCH (n2:Standard {uid: 'Standard:' + T + ':0:' + toString(toInteger(trim(row.standard_id)))})
WITH e, coalesce(n1, n2) AS st
WHERE st IS NOT NULL
MERGE (st)-[:HAS_EXAMINATION]->(e)
RETURN count(*) AS hasExamination;


LOAD CSV WITH HEADERS FROM 'file:///result_create_exam.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.subject_id IS NOT NULL AND trim(row.subject_id) <> '' AND trim(row.subject_id) <> '0'
MATCH (e:Examination {examinationId: toInteger(trim(row.id))})
OPTIONAL MATCH (s1:Subject {subId: toInteger(trim(row.subject_id))})
OPTIONAL MATCH (s2:Subject {uid: 'Subject:' + T + ':0:' + toString(toInteger(trim(row.subject_id)))})
WITH e, coalesce(s1, s2) AS sub
WHERE sub IS NOT NULL
MERGE (e)-[:EXAMINES_SUBJECT]->(sub)
RETURN count(*) AS examinesSubject;


// CoScholasticArea -> CoScholasticParent (22 of 24 parents resolve).
LOAD CSV WITH HEADERS FROM 'file:///result_co_scholastic.csv' AS row
WITH row WHERE row.parent_id IS NOT NULL AND trim(row.parent_id) <> '' AND trim(row.parent_id) <> '0'
MATCH (ca:CoScholasticArea {coscholasticareaId: toInteger(trim(row.id))})
MATCH (cp:CoScholasticParent {coscholasticparentId: toInteger(trim(row.parent_id))})
MERGE (ca)-[:IN_PART]->(cp)
RETURN count(*) AS inPart;


// Grade band -> the area it bands. 166 of 371 distinct map_id values resolve; the
// rest point at co-scholastic rows that no longer exist.
LOAD CSV WITH HEADERS FROM 'file:///result_co_scholastic_grades.csv' AS row
WITH row WHERE row.map_id IS NOT NULL AND trim(row.map_id) <> '' AND trim(row.map_id) <> '0'
MATCH (cb:CoScholasticGradeBand {coscholasticgradebandId: toInteger(trim(row.id))})
MATCH (ca:CoScholasticArea {coscholasticareaId: toInteger(trim(row.map_id))})
MERGE (ca)-[:HAS_GRADE_BAND]->(cb)
RETURN count(*) AS hasGradeBand;


// Activity -> Skillset (458 of 460 resolve).
LOAD CSV WITH HEADERS FROM 'file:///result_activity_master.csv' AS row
WITH row WHERE row.skill_id IS NOT NULL AND trim(row.skill_id) <> '' AND trim(row.skill_id) <> '0'
MATCH (a:Activity {activityId: toInteger(trim(row.id))})
MATCH (sk:Skillset {skillsetId: toInteger(trim(row.skill_id))})
MERGE (a)-[:IN_SKILLSET]->(sk)
RETURN count(*) AS inSkillset;


// Grade -> the grade scheme it belongs to. :GradeScheme exists only under the uid
// convention (32 nodes from `grade_master`).
LOAD CSV WITH HEADERS FROM 'file:///grade_master_data.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.grade_id IS NOT NULL AND trim(row.grade_id) <> '' AND trim(row.grade_id) <> '0'
MATCH (g:Grade {gradeId: toInteger(trim(row.id))})
MATCH (gs:GradeScheme {uid: 'GradeScheme:' + T + ':0:' + toString(toInteger(trim(row.grade_id)))})
MERGE (gs)-[:HAS_GRADE]->(g)
RETURN count(*) AS hasGrade;


// Standard -> GradeScheme. `standard` and `grade_scale` hold ids here.
LOAD CSV WITH HEADERS FROM 'file:///result_std_grd_maping.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.standard IS NOT NULL AND trim(row.standard) <> '' AND trim(row.standard) <> '0'
  AND row.grade_scale IS NOT NULL AND trim(row.grade_scale) <> '' AND trim(row.grade_scale) <> '0'
OPTIONAL MATCH (n1:Standard {stId: toInteger(trim(row.standard))})
OPTIONAL MATCH (n2:Standard {uid: 'Standard:' + T + ':0:' + toString(toInteger(trim(row.standard)))})
WITH row, T, coalesce(n1, n2) AS st
WHERE st IS NOT NULL
MATCH (gs:GradeScheme {uid: 'GradeScheme:' + T + ':0:' + toString(toInteger(trim(row.grade_scale)))})
MERGE (st)-[:USES_GRADE_SCHEME]->(gs)
RETURN count(*) AS usesGradeScheme;


// ExamSchedule -> Standard / Division.
LOAD CSV WITH HEADERS FROM 'file:///exam_schedule.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.standard_id IS NOT NULL AND trim(row.standard_id) <> '' AND trim(row.standard_id) <> '0'
MATCH (es:ExamSchedule {examscheduleId: toInteger(trim(row.id))})
OPTIONAL MATCH (n1:Standard {stId: toInteger(trim(row.standard_id))})
OPTIONAL MATCH (n2:Standard {uid: 'Standard:' + T + ':0:' + toString(toInteger(trim(row.standard_id)))})
WITH es, coalesce(n1, n2) AS st
WHERE st IS NOT NULL
MERGE (st)-[:HAS_EXAM_SCHEDULE]->(es)
RETURN count(*) AS hasExamSchedule;


// @section aggregates
// ---------------------------------------------------------------------
// 4. AGGREGATE EDGES
// ---------------------------------------------------------------------

// 1,308,379 mark rows -> 54,722 (student, exam) edges. Marks are a school record,
// not money, so the totals are kept and a percentage is derived the way the
// reference script derives its own ratios.
LOAD CSV WITH HEADERS FROM 'file:///result_personalize_marks_agg.csv' AS row
WITH row WHERE row.student_id IS NOT NULL AND row.exam_id IS NOT NULL

MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (e:Examination {examinationId: toInteger(trim(row.exam_id))})

MERGE (sd)-[r:SCORED {syear: toInteger(trim(row.syear))}]->(e)
ON CREATE SET
  r.total_marks      = toFloat(trim(row.total_marks)),
  r.obtained         = toFloat(trim(row.obtained)),
  r.subjects         = toInteger(trim(row.subjects)),
  r.standard_id      = toInteger(trim(row.standard_id)),
  r.percentage       = CASE WHEN toFloat(trim(row.total_marks)) > 0
                            THEN round(100.0 * toFloat(trim(row.obtained)) / toFloat(trim(row.total_marks)) * 100) / 100.0
                            ELSE null END,
  r.performance_level = CASE
      WHEN toFloat(trim(row.total_marks)) = 0 THEN null
      WHEN toFloat(trim(row.obtained)) / toFloat(trim(row.total_marks)) >= 0.8 THEN "excellent"
      WHEN toFloat(trim(row.obtained)) / toFloat(trim(row.total_marks)) >= 0.5 THEN "average"
      ELSE "weak" END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "result_personalize_marks"
RETURN count(r) AS scored;


// Report card per term.
LOAD CSV WITH HEADERS FROM 'file:///result_reportcard_marks_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.student_id IS NOT NULL AND row.academic_year_id IS NOT NULL

MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (ay:AcademicYear {uid: 'AcademicYear:' + T + ':' + toString(toInteger(trim(row.syear)))
                              + ':' + toString(toInteger(trim(row.academic_year_id)))})

MERGE (sd)-[r:REPORTCARD {term_id: toInteger(trim(row.term_id))}]->(ay)
ON CREATE SET
  r.syear            = toInteger(trim(row.syear)),
  r.percentage       = toFloat(trim(row.percentage)),
  r.present_days     = toInteger(trim(row.present_days)),
  r.working_days     = toInteger(trim(row.working_days)),
  r.standard_id      = toInteger(trim(row.standard_id)),
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src              = "result_reportcard_marks"
RETURN count(r) AS reportcard;


// @section verify
// ---------------------------------------------------------------------
// 5. VERIFY
// ---------------------------------------------------------------------

MATCH (e:Examination) RETURN 'Examination nodes' AS check, count(e) AS n;
MATCH (et:ExamType) RETURN 'ExamType nodes' AS check, count(et) AS n;
MATCH (ec:ExamTypeCategory) RETURN 'ExamTypeCategory nodes' AS check, count(ec) AS n;
MATCH (g:Grade) RETURN 'Grade nodes' AS check, count(g) AS n;
MATCH (ca:CoScholasticArea) RETURN 'CoScholasticArea nodes' AS check, count(ca) AS n;
MATCH (sk:Skillset) RETURN 'Skillset nodes' AS check, count(sk) AS n;
MATCH (a:Activity) RETURN 'Activity nodes' AS check, count(a) AS n;
MATCH (es:ExamSchedule) RETURN 'ExamSchedule nodes' AS check, count(es) AS n;
MATCH (:Examination)-[r:OF_EXAM_TYPE]->(:ExamType) RETURN 'OF_EXAM_TYPE' AS check, count(r) AS n;
MATCH (:Standard)-[r:HAS_EXAMINATION]->(:Examination) RETURN 'HAS_EXAMINATION' AS check, count(r) AS n;
MATCH (:Examination)-[r:EXAMINES_SUBJECT]->(:Subject) RETURN 'EXAMINES_SUBJECT' AS check, count(r) AS n;
MATCH (:CoScholasticArea)-[r:IN_PART]->(:CoScholasticParent) RETURN 'IN_PART' AS check, count(r) AS n;
MATCH (:CoScholasticArea)-[r:HAS_GRADE_BAND]->(:CoScholasticGradeBand) RETURN 'HAS_GRADE_BAND' AS check, count(r) AS n;
MATCH (:Activity)-[r:IN_SKILLSET]->(:Skillset) RETURN 'IN_SKILLSET' AS check, count(r) AS n;
MATCH (:GradeScheme)-[r:HAS_GRADE]->(:Grade) RETURN 'HAS_GRADE' AS check, count(r) AS n;
MATCH (:Standard)-[r:USES_GRADE_SCHEME]->(:GradeScheme) RETURN 'USES_GRADE_SCHEME' AS check, count(r) AS n;
MATCH (:Standard)-[r:HAS_EXAM_SCHEDULE]->(:ExamSchedule) RETURN 'HAS_EXAM_SCHEDULE' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:SCORED]->(:Examination) RETURN 'SCORED' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:REPORTCARD]->(:AcademicYear) RETURN 'REPORTCARD' AS check, count(r) AS n;
MATCH (e:Examination) WHERE NOT (e)--() RETURN 'Examination with no edge' AS check, count(e) AS n;
