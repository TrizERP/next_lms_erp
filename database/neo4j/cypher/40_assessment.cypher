// =====================================================================
//  ASSESSMENT — question tagging, question types, counselling, chapter mastery
//  Style and key convention follow k12_cypher.txt / reference_code.txt exactly.
//
//      php artisan neo4j:csv-export --module=assessment
//      php artisan neo4j:cypher --module=assessment
//
//  :Question, :Assessment and :Result already exist and are NOT rewritten here. Only
//  the layers the reference script had no source for are added.
//
//  WHY MASTERS_CHAPTER AND NOT MASTERS
//  The reference script's MASTERS is (:Student)-[:MASTERS]->(:Concept), computed from
//  exam scores against assessment totals. This is a different measurement at a
//  different grain: per-ANSWER accuracy against a CHAPTER, from :StuDetail. Writing it
//  into MASTERS would mix two definitions under one name and would move a protected
//  count. It gets its own type and carries `grain: 'chapter'` so a reader can tell
//  them apart.
//
//  Chapter grain is not a preference — `lms_question_master.chapter_id` is populated
//  on 62,206 of 62,209 rows while `concept_id` is populated on 47, so concept grain
//  is not reachable from the question bank.
//
//  ADDITIVE. MERGE + ON CREATE SET only. No protected relationship type is written.
// =====================================================================


// @section constraints
// ---------------------------------------------------------------------
// 1. CONSTRAINTS
// ---------------------------------------------------------------------

CREATE CONSTRAINT questiontype_questiontypeId_unique IF NOT EXISTS
FOR (qt:QuestionType) REQUIRE qt.questiontypeId IS UNIQUE;

CREATE CONSTRAINT counsellingcourse_counsellingcourseId_unique IF NOT EXISTS
FOR (cc:CounsellingCourse) REQUIRE cc.counsellingcourseId IS UNIQUE;

CREATE CONSTRAINT counsellingquestion_counsellingquestionId_unique IF NOT EXISTS
FOR (cq:CounsellingQuestion) REQUIRE cq.counsellingquestionId IS UNIQUE;

CREATE CONSTRAINT counsellingresult_counsellingresultId_unique IF NOT EXISTS
FOR (cr:CounsellingResult) REQUIRE cr.counsellingresultId IS UNIQUE;

CREATE CONSTRAINT offlineexam_offlineexamId_unique IF NOT EXISTS
FOR (oe:OfflineExam) REQUIRE oe.offlineexamId IS UNIQUE;

CREATE CONSTRAINT mbtipaper_mbtipaperId_unique IF NOT EXISTS
FOR (mp:MbtiPaper) REQUIRE mp.mbtipaperId IS UNIQUE;


// @section nodes
// ---------------------------------------------------------------------
// 2. NODES
// ---------------------------------------------------------------------

LOAD CSV WITH HEADERS FROM 'file:///question_type_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (qt:QuestionType {questiontypeId: toInteger(trim(row.id))})
ON CREATE SET
  qt.question_type    = CASE WHEN trim(coalesce(row.question_type, '')) = '' THEN null ELSE trim(row.question_type) END,
  qt.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  qt.syear            = toInteger(trim(row.syear)),
  qt.displayLabel     = "QuestionType:" + trim(coalesce(row.question_type, '')),
  qt.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  qt.src              = "question_type_master"
RETURN count(qt) AS questionTypeProcessed;


LOAD CSV WITH HEADERS FROM 'file:///counselling_course.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (cc:CounsellingCourse {counsellingcourseId: toInteger(trim(row.id))})
ON CREATE SET
  cc.title            = CASE WHEN trim(coalesce(row.title, '')) = '' THEN null ELSE trim(row.title) END,
  cc.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  cc.sort_order       = toInteger(trim(row.sort_order)),
  cc.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  cc.displayLabel     = "CounsellingCourse:" + trim(coalesce(row.title, '')),
  cc.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  cc.src              = "counselling_course"
RETURN count(cc) AS counsellingCourseProcessed;


LOAD CSV WITH HEADERS FROM 'file:///counselling_question_master.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (cq:CounsellingQuestion {counsellingquestionId: toInteger(trim(row.id))})
ON CREATE SET
  cq.question_title   = CASE WHEN trim(coalesce(row.question_title, '')) = '' THEN null ELSE trim(row.question_title) END,
  cq.description      = CASE WHEN trim(coalesce(row.description, '')) = '' THEN null ELSE trim(row.description) END,
  cq.course_id        = toInteger(trim(row.counselling_course_id)),
  cq.question_type_id = toInteger(trim(row.question_type_id)),
  cq.points           = toInteger(trim(row.points)),
  cq.multiple_answer  = CASE WHEN trim(coalesce(row.multiple_answer, '')) = '' THEN null ELSE trim(row.multiple_answer) END,
  cq.status           = CASE WHEN trim(coalesce(row.status, '')) = '' THEN null ELSE trim(row.status) END,
  cq.displayLabel     = "CounsellingQuestion:" + trim(coalesce(row.question_title, '')),
  cq.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  cq.src              = "counselling_question_master"
RETURN count(cq) AS counsellingQuestionProcessed;


LOAD CSV WITH HEADERS FROM 'file:///counselling_online_exam.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (cr:CounsellingResult {counsellingresultId: toInteger(trim(row.id))})
ON CREATE SET
  cr.user_id          = toInteger(trim(row.user_id)),
  cr.course_id        = toInteger(trim(row.course_id)),
  cr.total_right      = toInteger(trim(row.total_right)),
  cr.total_wrong      = toInteger(trim(row.total_wrong)),
  cr.obtain_marks     = toInteger(trim(row.obtain_marks)),
  cr.displayLabel     = "CounsellingResult:" + trim(coalesce(row.obtain_marks, '')),
  cr.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  cr.src              = "counselling_online_exam"
RETURN count(cr) AS counsellingResultProcessed;


LOAD CSV WITH HEADERS FROM 'file:///lms_offline_exam.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (oe:OfflineExam {offlineexamId: toInteger(trim(row.id))})
ON CREATE SET
  oe.student_id        = toInteger(trim(row.student_id)),
  oe.question_paper_id = toInteger(trim(row.question_paper_id)),
  oe.assignment_id     = toInteger(trim(row.assignment_id)),
  oe.total_right       = toInteger(trim(row.total_right)),
  oe.total_wrong       = toInteger(trim(row.total_wrong)),
  oe.obtain_marks      = toInteger(trim(row.obtain_marks)),
  oe.syear             = toInteger(trim(row.syear)),
  oe.displayLabel      = "OfflineExam:" + trim(coalesce(row.obtain_marks, '')),
  oe.sub_institute_id  = toInteger(trim(row.sub_institute_id)),
  oe.src               = "lms_offline_exam"
RETURN count(oe) AS offlineExamProcessed;


LOAD CSV WITH HEADERS FROM 'file:///MBTI_paper.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (mp:MbtiPaper {mbtipaperId: toInteger(trim(row.id))})
ON CREATE SET
  mp.displayLabel     = "MbtiPaper:" + trim(row.id),
  mp.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  mp.src              = "MBTI_paper"
RETURN count(mp) AS mbtiPaperProcessed;


// @section relationships
// ---------------------------------------------------------------------
// 3. RELATIONSHIPS
// ---------------------------------------------------------------------

// Question -> MappingType. This is Bloom's / Depth-of-Knowledge tagging, which is what
// `lms_question_mapping` actually holds — it is not chapter mapping, despite the name.
// :MappingType is tenant-global in the graph, so its uid is always `MappingType:0:0:<id>`.
LOAD CSV WITH HEADERS FROM 'file:///lms_question_mapping_agg.csv' AS row
WITH row
WHERE row.questionmaster_id IS NOT NULL AND row.mapping_value_id IS NOT NULL

MATCH (q:Question {qId: toInteger(trim(row.questionmaster_id))})
MATCH (m:MappingType {uid: 'MappingType:0:0:' + toString(toInteger(trim(row.mapping_value_id)))})

MERGE (q)-[r:TAGGED_AS]->(m)
ON CREATE SET
  r.mapping_type_id = toInteger(trim(row.mapping_type_id)),
  r.src             = "lms_question_mapping"
RETURN count(r) AS taggedAs;


// CounsellingQuestion -> MappingType, the same tagging for the counselling bank.
LOAD CSV WITH HEADERS FROM 'file:///counselling_question_mapping.csv' AS row
WITH row
WHERE row.questionmaster_id IS NOT NULL AND row.mapping_value_id IS NOT NULL
MATCH (cq:CounsellingQuestion {counsellingquestionId: toInteger(trim(row.questionmaster_id))})
MATCH (m:MappingType {uid: 'MappingType:0:0:' + toString(toInteger(trim(row.mapping_value_id)))})
MERGE (cq)-[r:TAGGED_AS]->(m)
ON CREATE SET
  r.mapping_type_id = toInteger(trim(row.mapping_type_id)),
  r.src             = "counselling_question_mapping"
RETURN count(r) AS counsellingTaggedAs;


LOAD CSV WITH HEADERS FROM 'file:///counselling_question_master.csv' AS row
WITH row WHERE row.counselling_course_id IS NOT NULL AND trim(row.counselling_course_id) <> '0'
MATCH (cq:CounsellingQuestion {counsellingquestionId: toInteger(trim(row.id))})
MATCH (cc:CounsellingCourse {counsellingcourseId: toInteger(trim(row.counselling_course_id))})
MERGE (cc)-[:HAS_COUNSELLING_QUESTION]->(cq)
RETURN count(*) AS hasCounsellingQuestion;


LOAD CSV WITH HEADERS FROM 'file:///counselling_online_exam.csv' AS row
WITH row WHERE row.course_id IS NOT NULL AND trim(row.course_id) <> '0'
MATCH (cr:CounsellingResult {counsellingresultId: toInteger(trim(row.id))})
MATCH (cc:CounsellingCourse {counsellingcourseId: toInteger(trim(row.course_id))})
MERGE (cr)-[:FOR_COURSE]->(cc)
RETURN count(*) AS forCourse;


// Who sat the counselling test. `user_id` is not documented as student or staff, so
// both are tried and whichever exists wins; 35 rows, so a wrong guess here would be
// invisible in a count and is worth resolving explicitly.
LOAD CSV WITH HEADERS FROM 'file:///counselling_online_exam.csv' AS row
WITH row WHERE row.user_id IS NOT NULL AND trim(row.user_id) <> '' AND trim(row.user_id) <> '0'
MATCH (cr:CounsellingResult {counsellingresultId: toInteger(trim(row.id))})
OPTIONAL MATCH (sd:StuDetail {sdId: toInteger(trim(row.user_id))})
OPTIONAL MATCH (t:Teacher {teacherId: toInteger(trim(row.user_id))})
OPTIONAL MATCH (st:Staff {staffId: toInteger(trim(row.user_id))})
WITH cr, coalesce(sd, t, st) AS taker
WHERE taker IS NOT NULL
MERGE (taker)-[:TOOK_COUNSELLING_TEST]->(cr)
RETURN count(*) AS tookCounsellingTest;


// StuDetail -> OfflineExam -> Assessment.
LOAD CSV WITH HEADERS FROM 'file:///lms_offline_exam.csv' AS row
WITH row WHERE row.student_id IS NOT NULL AND trim(row.student_id) <> '0'
MATCH (oe:OfflineExam {offlineexamId: toInteger(trim(row.id))})
MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MERGE (sd)-[:HAS_OFFLINE_EXAM]->(oe)
RETURN count(*) AS hasOfflineExam;


LOAD CSV WITH HEADERS FROM 'file:///lms_offline_exam.csv' AS row
WITH row WHERE row.question_paper_id IS NOT NULL AND trim(row.question_paper_id) <> '0'
MATCH (oe:OfflineExam {offlineexamId: toInteger(trim(row.id))})
MATCH (a:Assessment {assId: toInteger(trim(row.question_paper_id))})
MERGE (oe)-[:FOR_OFFLINE_ASSESSMENT]->(a)
RETURN count(*) AS forOfflineAssessment;


// ASSIGNED_EXAM, not HAS_RESULT: `lms_online_exam_student` records a paper handed to a
// learner, which is not the same fact as the 518,269 protected HAS_RESULT edges that
// link a learner to a completed :Result.
LOAD CSV WITH HEADERS FROM 'file:///lms_online_exam_student.csv' AS row
WITH row
WHERE row.student_id IS NOT NULL AND trim(row.student_id) <> '0'
  AND row.question_paper_id IS NOT NULL AND trim(row.question_paper_id) <> '0'

MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (a:Assessment {assId: toInteger(trim(row.question_paper_id))})

MERGE (sd)-[r:ASSIGNED_EXAM {assignmentId: toInteger(trim(row.id))}]->(a)
ON CREATE SET
  r.total_right  = toInteger(trim(row.total_right)),
  r.total_wrong  = toInteger(trim(row.total_wrong)),
  r.obtain_marks = toInteger(trim(row.obtain_marks)),
  r.start_time   = CASE WHEN trim(coalesce(row.start_time, '')) = '' THEN null ELSE trim(row.start_time) END,
  r.src          = "lms_online_exam_student"
RETURN count(r) AS assignedExam;


// @section aggregates
// ---------------------------------------------------------------------
// 4. AGGREGATE EDGES
// ---------------------------------------------------------------------

// Chapter-grain mastery from 2,418,947 answer rows. The bands mirror the reference
// script's MASTERS bands so the two read the same way, but the type and grain are
// distinct — see the header.
LOAD CSV WITH HEADERS FROM 'file:///lms_online_exam_answer_agg.csv' AS row
WITH row, toString(toInteger(trim(row.sub_institute_id))) AS T
WHERE row.student_id IS NOT NULL AND row.chapter_id IS NOT NULL

MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
OPTIONAL MATCH (c1:Chapter {chId: toInteger(trim(row.chapter_id))})
OPTIONAL MATCH (c2:Chapter {uid: 'Chapter:' + T + ':0:' + toString(toInteger(trim(row.chapter_id)))})
WITH row, sd, coalesce(c1, c2) AS ch
WHERE ch IS NOT NULL

WITH row, sd, ch,
     toFloat(trim(row.correct)) / toFloat(trim(row.attempts)) AS accuracy,
     toInteger(trim(row.attempts)) AS attempts

MERGE (sd)-[r:MASTERS_CHAPTER]->(ch)
ON CREATE SET
  r.grain          = "chapter",
  r.attempts       = attempts,
  r.correct        = toInteger(trim(row.correct)),
  r.accuracy       = round(accuracy * 100) / 100.0,
  r.times_practiced = attempts,
  r.mastery_level  = CASE WHEN accuracy >= 0.85 THEN "mastered"
                          WHEN accuracy >= 0.6  THEN "intermediate"
                          ELSE "beginner" END,
  r.confidence     = CASE WHEN accuracy >= 0.8 THEN "high"
                          WHEN accuracy >= 0.5 THEN "medium"
                          ELSE "low" END,
  r.fluency        = CASE WHEN attempts >= 10 THEN "high"
                          WHEN attempts >= 5  THEN "medium"
                          ELSE "low" END,
  r.last_attempt_at = CASE WHEN trim(coalesce(row.last_at, '')) = '' THEN null ELSE trim(row.last_at) END,
  r.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  r.src            = "lms_online_exam_answer"
RETURN count(r) AS mastersChapter;


// @section derived
// ---------------------------------------------------------------------
// 5. DERIVED EDGES — from properties already on the :Question nodes.
// ---------------------------------------------------------------------

// The reference script already put `question_type_id` on every :Question, so this
// needs no CSV.
MATCH (q:Question)
WHERE q.question_type_id IS NOT NULL AND q.question_type_id > 0
MATCH (qt:QuestionType {questiontypeId: q.question_type_id})
MERGE (q)-[r:OF_QUESTION_TYPE]->(qt)
ON CREATE SET r.src = "lms_question_master.question_type_id"
RETURN count(r) AS ofQuestionType;


// @section verify
// ---------------------------------------------------------------------
// 6. VERIFY
// ---------------------------------------------------------------------

MATCH (qt:QuestionType) RETURN 'QuestionType nodes' AS check, count(qt) AS n;
MATCH (cc:CounsellingCourse) RETURN 'CounsellingCourse nodes' AS check, count(cc) AS n;
MATCH (cq:CounsellingQuestion) RETURN 'CounsellingQuestion nodes' AS check, count(cq) AS n;
MATCH (cr:CounsellingResult) RETURN 'CounsellingResult nodes' AS check, count(cr) AS n;
MATCH (oe:OfflineExam) RETURN 'OfflineExam nodes' AS check, count(oe) AS n;
MATCH (:Question)-[r:TAGGED_AS]->(:MappingType) RETURN 'Question TAGGED_AS' AS check, count(r) AS n;
MATCH (:Content)-[r:TAGGED_AS]->(:MappingType) RETURN 'Content TAGGED_AS (pre-existing)' AS check, count(r) AS n;
MATCH (:Question)-[r:OF_QUESTION_TYPE]->(:QuestionType) RETURN 'OF_QUESTION_TYPE' AS check, count(r) AS n;
MATCH (:CounsellingCourse)-[r:HAS_COUNSELLING_QUESTION]->(:CounsellingQuestion) RETURN 'HAS_COUNSELLING_QUESTION' AS check, count(r) AS n;
MATCH (:CounsellingResult)-[r:FOR_COURSE]->(:CounsellingCourse) RETURN 'FOR_COURSE' AS check, count(r) AS n;
MATCH ()-[r:TOOK_COUNSELLING_TEST]->(:CounsellingResult) RETURN 'TOOK_COUNSELLING_TEST' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:HAS_OFFLINE_EXAM]->(:OfflineExam) RETURN 'HAS_OFFLINE_EXAM' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:ASSIGNED_EXAM]->(:Assessment) RETURN 'ASSIGNED_EXAM' AS check, count(r) AS n;
MATCH (:StuDetail)-[r:MASTERS_CHAPTER]->(:Chapter) RETURN 'MASTERS_CHAPTER' AS check, count(r) AS n;
MATCH (:Student)-[r:MASTERS]->(:Concept) RETURN 'MASTERS (protected, unchanged)' AS check, count(r) AS n;
