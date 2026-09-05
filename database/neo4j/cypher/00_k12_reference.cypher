// =====================================================================
//  REFERENCE ONLY — NEVER EXECUTED BY `neo4j:cypher`
// =====================================================================
//
//  This is the ingest that built the existing PAL/K12 graph, kept verbatim as the
//  record of where those nodes and the 24 protected relationship types came from. Until
//  2026-09-04 the repository held no copy of it at all: the graph existed and the Cypher
//  that made it lived only in a document outside version control.
//
//  Two things depend on it:
//    * every module script in this directory copies its dialect — MERGE on an integer
//      native key, `displayLabel`, `sub_institute_id`, `toInteger(trim(row.x))`;
//    * `CypherRunCommand::PROTECTED_RELS` is exactly the relationship list below, and
//      the runner aborts if any of their counts move.
//
//  `neo4j:cypher` refuses to run a file whose name starts `00_`. Re-running this against
//  the live graph would be an ingest, not a no-op: several statements use bare `SET`,
//  which OVERWRITES properties on nodes that already exist.
//
//  Source: k12_cypher.txt, supplied by the owner 2026-09-04.
// =====================================================================


// ################## Schema Constraints for PAL Dashboard ###############

CREATE CONSTRAINT stu_details_sdId_unique
  FOR (sd:StuDetail)
  REQUIRE sd.sdId IS UNIQUE;

CREATE CONSTRAINT student_stuId_unique
  FOR (stu:Student)
  REQUIRE stu.stuId IS UNIQUE;

CREATE CONSTRAINT standard_stId_unique
  FOR (st:Standard)
  REQUIRE st.stId IS UNIQUE;

CREATE CONSTRAINT subject_subId_unique
  FOR (sub:Subject)
  REQUIRE sub.subId IS UNIQUE;

CREATE CONSTRAINT assessment_assId_unique
  FOR (ass:Assessment)
  REQUIRE ass.assId IS UNIQUE;

CREATE CONSTRAINT result_resultId_unique
  FOR (r:Result)
  REQUIRE r.resultId IS UNIQUE;

CREATE CONSTRAINT question_qId_unique
  FOR (q:Question)
  REQUIRE q.qId IS UNIQUE;

CREATE CONSTRAINT chapter_chId_unique
  FOR (ch:Chapter)
  REQUIRE ch.chId IS UNIQUE;

CREATE CONSTRAINT teacher_teacherId_unique
FOR (t:Teacher)
REQUIRE t.teacherId IS UNIQUE;

CREATE CONSTRAINT concept_conceptId_unique
FOR (con:Concept)
REQUIRE con.conceptId IS UNIQUE;

CREATE CONSTRAINT curriculum_curriculumId_unique
FOR (curr:Curriculum)
REQUIRE curr.curriculumId IS UNIQUE;

CREATE CONSTRAINT lesson_lessonId_unique
FOR (l:Lesson)
REQUIRE l.lessonId IS UNIQUE;

CREATE CONSTRAINT misconception_misconceptionId_unique
FOR (m:Misconception)
REQUIRE m.misconceptionId IS UNIQUE;

CREATE CONSTRAINT content_contentId_unique
FOR (lc:LearningContent)
REQUIRE lc.contentId IS UNIQUE;

CREATE CONSTRAINT unit_unitId_unique
FOR (u:Unit)
REQUIRE u.unitId IS UNIQUE;

CREATE CONSTRAINT LearningObject_learningobjectId_unique
FOR (lo:LearningObjects)
REQUIRE lo.learningobjectId IS UNIQUE;

CREATE CONSTRAINT CompetencyStandards_competencystandardsId_unique
FOR (cs:CompetencyStandards)
REQUIRE cs.competencystandardsId IS UNIQUE;

CREATE CONSTRAINT chapterstandardmap_chapterstandardmapId_unique
FOR (csm:ChapterStandardMap)
REQUIRE csm.chapterstandardmapId IS UNIQUE;

CREATE CONSTRAINT assessmenttypology_assessmenttypologyId_unique
FOR (csm:AssessmentTypology)
REQUIRE csm.assessmenttypologyId IS UNIQUE;


// **************** Ingest student_details and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///opt/neo4j-next_lms/import/tblstudent.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL
MERGE (sd:StuDetail {sdId: toInteger(trim(row.id))})
ON CREATE SET
  sd.student_id = toInteger(trim(row.id)),
  sd.first_name = row.first_name,
  sd.middle_name = row.middle_name,
  sd.last_name = row.last_name,
  sd.admission_year = row.admission_year,
  sd.mobile = row.mobile,
  sd.email = row.email,
  sd.displayLabel = "Student Details:" + trim(row.first_name),
  sd.sub_institute_id = toInteger(trim(row.sub_institute_id))
SET
  sd.admission_year = toInteger(trim(row.admission_year)),
  sd.mobile = row.mobile,
  sd.email = row.email
RETURN count(sd) AS studetailsProcessed;

// linked student_details to student
LOAD CSV WITH HEADERS FROM 'file:///tblstudent_enrollment.csv' AS row
WITH row
WHERE row.student_id IS NOT NULL
  AND row.id IS NOT NULL
MATCH (sd:StuDetail {sdId: toInteger(trim(row.student_id))})
MATCH (stu:Student {stuId: toInteger(trim(row.id))})
MERGE (sd)-[:HAS_STUDENT]->(stu);


// **************** Ingest student and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///tblstudent_enrollment.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL
MERGE (stu:Student {stuId: toInteger(trim(row.id))})
ON CREATE SET
  stu.student_id = toInteger(trim(row.student_id)),
  stu.displayLabel = "Student:" + trim(row.student_id),
  stu.syear = toInteger(trim(row.syear)),
  stu.standard_id = toInteger(trim(row.standard_id)),
  stu.grade_id = toInteger(trim(row.grade_id)),
  stu.section_id = toInteger(trim(row.section_id)),
  stu.sub_institute_id = toInteger(trim(row.sub_institute_id))
SET
  stu.syear = toInteger(trim(row.syear)),
  stu.standard_id = toInteger(trim(row.standard_id)),
  stu.grade_id = toInteger(trim(row.grade_id)),
  stu.section_id = toInteger(trim(row.section_id))
RETURN count(stu) AS studentProcessed;

LOAD CSV WITH HEADERS FROM 'file:///tblstudent_enrollment.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
MATCH (stu:Student {stuId: toInteger(trim(row.id))})
SET
  stu.preferred_pedagogy = row.preferred_pedagogy,
  stu.engagement_score = toFloat(row.engagement_score),
  stu.learning_velocity = toFloat(row.learning_velocity),
  stu.updated_at = datetime()
RETURN count(stu) AS updatedStudents;

// linked student to standard
LOAD CSV WITH HEADERS FROM 'file:///tblstudent_enrollment.csv' AS row
WITH row
WHERE row.standard_id IS NOT NULL
  AND row.id IS NOT NULL
MATCH (stu:Student {stuId: toInteger(trim(row.id))})
MATCH (st:Standard {stId: toInteger(trim(row.standard_id))})
MERGE (stu)-[:ENROLLED_IN]->(st);


// **************** Ingest standard and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///standard.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL
MERGE (st:Standard {stId: toInteger(trim(row.id))})
ON CREATE SET
  st.standard_id = toInteger(trim(row.id)),
  st.name = row.name,
  st.short_name = row.short_name,
  st.displayLabel = "Standard:" + trim(row.name),
  st.sub_institute_id = toInteger(trim(row.sub_institute_id))
RETURN count(st) AS standardProcessed;

// linked standard to subject
LOAD CSV WITH HEADERS FROM 'file:///sub_std_map.csv' AS row
WITH row
WHERE row.standard_id IS NOT NULL
  AND row.subject_id IS NOT NULL
MATCH (st:Standard {stId: toInteger(trim(row.standard_id))})
MATCH (sub:Subject {subId: toInteger(trim(row.subject_id))})
MERGE (st)-[:HAS_SUBJECT]->(sub);


// **************** Ingest subject and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///sub_std_map.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL
MERGE (sub:Subject {subId: toInteger(trim(row.id))})
ON CREATE SET
  sub.subject_id = toInteger(trim(row.subject_id)),
  sub.standard_id =  toInteger(trim(row.standard_id)),
  sub.display_name = row.display_name,
  sub.sort_order = toInteger(trim(row.sort_order)),
  sub.displayLabel = "subject:" + trim(row.display_name),
  sub.sub_institute_id = toInteger(trim(row.sub_institute_id))
RETURN count(sub) AS subjectProcessed;

LOAD CSV WITH HEADERS FROM 'file:///sub_std_map.csv' AS row
WITH row
WHERE row.subject_id IS NOT NULL
  AND trim(row.subject_id) <> ''
MERGE (sub:Subject {
    subId: toInteger(trim(row.subject_id))
})
ON CREATE SET
    sub.display_name = row.display_name,
    sub.standard_id = toInteger(trim(row.standard_id)),
    sub.sort_order = toInteger(trim(row.sort_order)),
    sub.sub_institute_id = toInteger(trim(row.sub_institute_id)),
    sub.displayLabel = "Subject:" + trim(row.display_name)
RETURN count(sub);

// linked subject to Assessment
LOAD CSV WITH HEADERS FROM 'file:///question_paper.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND row.subject_id IS NOT NULL
MATCH (ass:Assessment {assId: toInteger(trim(row.id))})
MATCH (sub:Subject {subId: toInteger(trim(row.subject_id))})
MERGE (sub)-[:HAS_ASSESSMENT]->(ass);


// **************** Ingest Assessment and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///question_paper.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL
MERGE (ass:Assessment {assId: toInteger(trim(row.id))})
ON CREATE SET
  ass.concept_id = toInteger(trim(row.concept_id)),
  ass.grade_id = toInteger(trim(row.grade_id)),
  ass.standard_id = toInteger(trim(row.standard_id)),
  ass.subject_id = toInteger(trim(row.subject_id)),
  ass.total_marks = toInteger(trim(row.total_marks)),
  ass.total_ques = toInteger(trim(row.total_ques)),
  ass.syear = toInteger(trim(row.syear)),
  ass.question_ids = row.question_ids,
  ass.exam_type =  row.exam_type,
  ass.paper_name = row.paper_name,
  ass.displayLabel = "Assessment:" + trim(row.paper_name),
  ass.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  ass.question_type = row.question_type,
  ass.difficulty_level = row.difficulty_level,
  ass.bloom_level = row.bloom_level
RETURN count(ass) AS AssessmentProcessed;

LOAD CSV WITH HEADERS FROM 'file:///question_paper.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL
MERGE (ass:Assessment {assId: toInteger(trim(row.id))})
SET
  ass.concept_id = toInteger(trim(row.concept_id)),
  ass.question_type = row.question_type,
  ass.difficulty_level = row.difficulty_level,
  ass.bloom_level = row.bloom_level
RETURN count(ass) AS AssessmentProcessed;

// linked student to result
LOAD CSV WITH HEADERS FROM 'file:///lms_online_exam.csv' AS row
WITH row
WHERE row.student_id IS NOT NULL
  AND row.id IS NOT NULL
MATCH (stu:Student {student_id: toInteger(trim(row.student_id))})
MATCH (r:Result {resultId: toInteger(trim(row.id))})
MERGE (stu)-[:HAS_RESULT]->(r);


// **************** Ingest Result and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///lms_online_exam.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
MERGE (r:Result {resultId: toInteger(trim(row.id))})
ON CREATE SET
  r.student_id = toInteger(trim(row.student_id)),
  r.question_paper_id = toInteger(trim(row.question_paper_id)),
  r.total_right = toInteger(trim(row.total_right)),
  r.total_wrong = toInteger(trim(row.total_wrong)),
  r.obtain_marks = toInteger(trim(row.obtain_marks)),
  r.displayLabel = "Result:" + toInteger(trim(row.obtain_marks))
RETURN count(r) AS resultProcessed;

// linked question to Result
LOAD CSV WITH HEADERS FROM 'file:///question_paper.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.question_ids IS NOT NULL
  AND trim(row.question_ids) <> ''
MATCH (ass:Assessment {assId: toInteger(trim(row.id))})
WITH ass, split(row.question_ids, ',') AS qids
UNWIND qids AS qid
MATCH (q:Question {qId: toInteger(trim(qid))})
MERGE (ass)-[:HAS_QUESTION]->(q);


// **************** Ingest question and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///lms_question_master.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL
MERGE (q:Question {qId: toInteger(trim(row.id))})
ON CREATE SET
  q.question_type_id = toInteger(trim(row.question_type_id)),
  q.concept_id = toInteger(trim(row.concept_id)),
  q.standard_id = toInteger(trim(row.standard_id)),
  q.subject_id = toInteger(trim(row.subject_id)),
  q.chapter_id = toInteger(trim(row.chapter_id)),
  q.question_title = row.question_title,
  q.points = toInteger(trim(row.points)),
  q.displayLabel = "Question:" + trim(row.question_title),
  q.sub_institute_id = toInteger(trim(row.sub_institute_id))
SET
  q.concept_id = toInteger(trim(row.concept_id))
RETURN count(q) AS questionProcessed;

// linked chapter to question
MATCH (q:Question)
WHERE q.chapter_id IS NOT NULL
MATCH (c:Chapter {chId: q.chapter_id})
MERGE (q)-[:BELONGS_TO]->(c);


// **************** Ingest chapter and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///chapter_master.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL
MERGE (ch:Chapter {chId: toInteger(trim(row.id))})
ON CREATE SET
    ch.subject_id =  toInteger(trim(row.subject_id)),
    ch.standard_id =  toInteger(trim(row.standard_id)),
    ch.chapter_name = row.chapter_name,
    ch.displayLabel = "chapter:" + trim(row.chapter_name),
    ch.sub_institute_id = toInteger(trim(row.sub_institute_id))
SET
  ch.unit_id  =  toInteger(trim(row.unit_id )),
  ch.sort_order   =  toInteger(trim(row.sort_order  )),
  ch.key_concepts   = row.key_concepts
RETURN count(ch) AS chapterProcessed;

// linked subject to chapter
LOAD CSV WITH HEADERS FROM 'file:///chapter_master.csv' AS row
WITH row
WHERE row.subject_id IS NOT NULL
  AND row.id IS NOT NULL
MATCH (sub:Subject {subId: toInteger(trim(row.subject_id))})
MATCH (ch:Chapter {chId: toInteger(trim(row.id))})
MERGE (sub)-[:HAS_CHAPTER]->(ch);

MATCH (ch:Chapter)
WHERE ch.unit_id IS NOT NULL
MATCH (u:Unit {unitId: ch.unit_id})
MERGE (u)-[:HAS_CHAPTER]->(ch);

MATCH (a:Assessment)-[:HAS_QUESTION]->(q:Question)-[:BELONGS_TO]->(ch:Chapter)
WITH DISTINCT a, ch
MERGE (a)-[:ASSESSES_CHAPTER]->(ch);

LOAD CSV WITH HEADERS FROM 'file:///lms_online_exam.csv' AS row
WITH row
WHERE row.question_paper_id IS NOT NULL
  AND row.id IS NOT NULL
MATCH (ass:Assessment {assId: toInteger(trim(row.question_paper_id))})
MATCH (r:Result {resultId: toInteger(trim(row.id))})
MERGE (r)-[:FOR_ASSESSMENT]->(ass);


// **************** Ingest teacher and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///tbluser.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL
MERGE (t:Teacher {teacherId: toInteger(trim(row.id))})
ON CREATE SET
  t.user_profile_id = toInteger(trim(row.user_profile_id)),
  t.subject_ids = row.subject_ids,
  t.updated_at = toInteger(trim(row.updated_at)),
  t.displayLabel = "Teacher:" + trim(row.user_profile_id),
  t.sub_institute_id = toInteger(trim(row.sub_institute_id))
RETURN count(t) AS teacherProcessed;


// **************** Ingest Concept and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///lms_concept.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL
MERGE (con:Concept {conceptId: toInteger(trim(row.id))})
ON CREATE SET
  con.id = toInteger(trim(row.id)),
  con.lesson_id = toInteger(trim(row.lesson_id)),
  con.name = row.name,
  con.subject_id = toInteger(trim(row.subject_id)),
  con.standard_id = toInteger(trim(row.standard_id)),
  con.chapter_id =  toInteger(trim(row.chapter_id)),
  con.difficulty_level = toInteger(trim(row.difficulty_level)),
  con.bloom_level = row.bloom_level,
  con.pedagogy_tag = row.pedagogy_tag,
  con.mastery_threshold = row.mastery_threshold,
  con.estimated_mastery_minutes = toInteger(trim(row.estimated_mastery_minutes)),
  con.syear = toInteger(trim(row.syear)),
  con.created_at = toInteger(trim(row.created_at)),
  con.displayLabel = "Concept:" + trim(row.name),
  con.sub_institute_id = toInteger(trim(row.sub_institute_id))
RETURN count(con) AS conceptProcessed;


// **************** Ingest Curriculum and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///lms_curriculum.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL
MERGE (curr:Curriculum  {curriculumId: toInteger(trim(row.id))})
ON CREATE SET
  curr.board  = row.board ,
  curr.framework  = row.framework ,
  curr.total_marks  = toInteger(trim(row.total_marks)),
  curr.internal_marks  = toInteger(trim(row.internal_marks)),
  curr.subject_id = toInteger(trim(row.subject_id)),
  curr.curriculum_name = row.curriculum_name,
  curr.standard_id = toInteger(trim(row.standard_id)),
  curr.syear = toInteger(trim(row.syear)),
  curr.status = row.status,
  curr.displayLabel = "Curriculum:" + trim(row.curriculum_name),
  curr.sub_institute_id = toInteger(trim(row.sub_institute_id))
SET
  curr.board  = row.board ,
  curr.framework  = row.framework ,
  curr.total_marks  = toInteger(trim(row.total_marks)),
  curr.internal_marks  = toInteger(trim(row.internal_marks))
RETURN count(curr) AS CurriculumProcessed;


// **************** Ingest Lesson and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///lms_lesson_plan.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL
MERGE (l:Lesson {lessonId: toInteger(trim(row.id))})
ON CREATE SET
  l.chapter_id = toInteger(trim(row.chapter_id)),
  l.teacher_id = toInteger(trim(row.teacher_id)),
  l.lesson_date = row.lesson_date,
  l.period_number = toInteger(trim(row.period_number)),
  l.pedagogy_tag = row.pedagogy_tag,
  l.status = row.status,
  l.engagement_rating = row.engagement_rating ,
  l.completed_at = toInteger(trim(row.completed_at)),
  l.displayLabel = "Curriculum:" + trim(row.curriculum_name),
  l.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  l.lesson_title = row.lesson_title,
  l.subject_id = toInteger(trim(row.subject_id)),
  l.standard_id = toInteger(trim(row.standard_id)),
  l.learning_objective = row.learning_objective,
  l.duration = row.duration
RETURN count(l) AS lessonProcessed;


// **************** Ingest Misconception and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///lms_misconceptions.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL
MERGE (m:Misconception {misconceptionId: toInteger(trim(row.id))})
ON CREATE SET
  m.error_type = row.error_type,
  m.description = row.description,
  m.severity_default = toInteger(trim(row.severity_default)),
  m.created_at = datetime(),
  m.status = row.status,
  m.displayLabel = "Misconception:" + trim(row.error_type),
  m.sub_institute_id = toInteger(trim(row.sub_institute_id)),
  m.concept_id = toInteger(trim(row.concept_id)),
  m.chapter_id = toInteger(trim(row.chapter_id)),
  m.subject_id = toInteger(trim(row.subject_id)),
  m.standard_id = toInteger(trim(row.standard_id))
RETURN count(m) AS MisconceptionProcessed;


// **************** Ingest LearningContent and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///suggested_content.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.sub_institute_id IS NOT NULL
MERGE (lc:LearningContent {contentId: toInteger(trim(row.id))})
ON CREATE SET
  lc.concept_id = toInteger(trim(row.concept_id)),
  lc.misconception_id = toInteger(trim(row.misconception_id)),
  lc.title = row.title,
  lc.content_type = row.type,
  lc.modality = row.modality,
  lc.language = row.language,
  lc.student_level = row.student_level,
  lc.difficulty_level = toInteger(trim(row.difficulty_level)),
  lc.bloom_level = row.bloom_level,
  lc.pedagogy_tag = row.pedagogy_tag,
  lc.displayLabel = "LearningContent:" + trim(row.title),
  lc.sub_institute_id = toInteger(trim(row.sub_institute_id))
RETURN count(lc) AS CurriculumProcessed;

// linked curriculum to subject
LOAD CSV WITH HEADERS FROM 'file:///lms_curriculum.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND row.subject_id IS NOT NULL
MATCH (curr:Curriculum {curriculumId: toInteger(trim(row.id))})
MATCH (sub:Subject {subId: toInteger(trim(row.subject_id))})
MERGE (curr)-[:INCLUDES]->(sub);

MATCH (curr:Curriculum)-[:INCLUDES]->(sub:Subject)
MERGE (sub)-[:BELONGS_TO_CURRICULUM]->(curr);

// linked lesson to chapter
LOAD CSV WITH HEADERS FROM 'file:///lms_lesson_plan.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.chapter_id IS NOT NULL
  AND trim(row.chapter_id) <> ''
MATCH (ch:Chapter {
    chId: toInteger(trim(row.chapter_id))
})
MATCH (l:Lesson {
    lessonId: toInteger(trim(row.id))
})
MERGE (ch)-[:HAS_LESSON]->(l);

// linked concept to lesson
LOAD CSV WITH HEADERS FROM 'file:///lms_concept.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
  AND row.lesson_id IS NOT NULL
  AND trim(row.lesson_id) <> ''
MATCH (l:Lesson {
    lessonId: toInteger(trim(row.lesson_id))
})
MATCH (con:Concept {
    conceptId: toInteger(trim(row.id))
})
MERGE (l)-[r:COVERS]->(con)
ON CREATE SET
    r.coverage_depth = "medium",
    r.sequence_order = 1,
    r.is_introduction = true
RETURN count(r) AS relationshipsCreated;

// linked concept to Assessment
MATCH (ass:Assessment)
WHERE ass.concept_id IS NOT NULL
MATCH (con:Concept)
WHERE con.conceptId = toInteger(ass.concept_id)
MERGE (ass)-[:ASSESSES]->(con);

// linked concept to Misconception
MATCH (m:Misconception)
WHERE m.concept_id IS NOT NULL
MATCH (con:Concept {
    conceptId: m.concept_id
})
MERGE (m)-[:OCCURS_IN]->(con);

// linked concept to LearningContent
MATCH (lc:LearningContent)
WHERE lc.concept_id IS NOT NULL
MATCH (con:Concept {
    conceptId: lc.concept_id
})
MERGE (lc)-[:TEACHES]->(con);

// linked Misconception to LearningContent
MATCH (lc:LearningContent)
WHERE lc.misconception_id IS NOT NULL
MATCH (m:Misconception {
    misconceptionId: lc.misconception_id
})
MERGE (lc)-[:REMEDIATES]->(m);

// linked student to Assessment
MATCH (stu:Student)-[:HAS_RESULT]->(r:Result)
MATCH (r)-[:FOR_ASSESSMENT]->(ass:Assessment)
MERGE (stu)-[:ATTEMPTED]->(ass);

// linked student to lesson
MATCH (stu:Student)
MATCH (l:Lesson)
WHERE stu.standard_id = l.standard_id
MERGE (stu)-[:ATTENDED]->(l);

// linked student to concept
MATCH (stu:Student)-[:HAS_RESULT]->(r:Result)
MATCH (r)-[:FOR_ASSESSMENT]->(ass:Assessment)
MATCH (ass)-[:ASSESSES]->(con:Concept)
WITH stu, con, ass,
     avg(r.obtain_marks * 1.0 / ass.total_marks) AS mastery,
     count(r) AS practice_count,
     max(r.obtain_marks) AS latest_score
MERGE (stu)-[m:MASTERS]->(con)
SET
    m.bkt_mastery =
        CASE
            WHEN mastery > 1 THEN 1
            ELSE round(mastery * 100.0) / 100.0
        END,
    m.fluency =
        CASE
            WHEN practice_count >= 10 THEN "high"
            WHEN practice_count >= 5 THEN "medium"
            ELSE "low"
        END,
    m.confidence =
        CASE
            WHEN mastery >= 0.8 THEN "high"
            WHEN mastery >= 0.5 THEN "medium"
            ELSE "low"
        END,
    m.times_practiced = practice_count,
    m.last_attempt_correct =
        CASE
            WHEN latest_score >= (ass.total_marks * 0.5)
            THEN true
            ELSE false
        END,
    m.next_review_date =
        CASE
            WHEN mastery >= 0.8 THEN date() + duration('P14D')
            WHEN mastery >= 0.5 THEN date() + duration('P7D')
            ELSE date() + duration('P2D')
        END,
    m.forgetting_risk =
        CASE
            WHEN mastery >= 0.8 THEN "low"
            WHEN mastery >= 0.5 THEN "medium"
            ELSE "high"
        END,
    m.updated_at = datetime()
RETURN stu.stuId,
       con.conceptId,
       m;

// linked student to Misconception
MATCH (stu:Student)-[m:MASTERS]->(con:Concept)
MATCH (mis:Misconception)-[:OCCURS_IN]->(con)
MERGE (stu)-[r:HAS_MISCONCEPTION]->(mis)
SET
    r.severity =
        CASE
            WHEN m.bkt_mastery < 0.3 THEN "high"
            WHEN m.bkt_mastery < 0.5 THEN "medium"
            ELSE "low"
        END,
    r.mastery_score = m.bkt_mastery,
    r.detected_at = datetime(),
    r.status = "active"
RETURN
    stu.stuId,
    mis.misconceptionId,
    r;


// **************** Ingest unit and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///lms_units.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
MERGE (u:Unit  {unitId: toInteger(trim(row.id))})
ON CREATE SET
  u.curriculum_id  = toInteger(trim(row.curriculum_id)),
  u.unit_number  = toInteger(trim(row.unit_number)),
  u.planned_periods = toInteger(trim(row.planned_periods)),
  u.name = row.name,
  u.total_marks = toInteger(trim(row.total_marks)),
  u.displayLabel = "Unit:" + trim(row.name)
RETURN count(u) AS unitProcessed;


// **************** Ingest LearningObjects and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///lms_learning_objectives.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
MERGE (lo:LearningObjects {learningobjectId: toInteger(trim(row.id))})
ON CREATE SET
  lo.standard_id  = toInteger(trim(row.standard_id )),
  lo.curriculum_id  = toInteger(trim(row.curriculum_id )),
  lo.subject_id  = toInteger(trim(row.subject_id )),
  lo.objectives  = row.objectives,
  lo.displayLabel = "LearningObjects:" + trim(row.objectives)
RETURN count(lo) AS LearningObjectsProcessed;


// **************** Ingest CompetencyStandards and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///lms_competency_standards.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
MERGE (cs:CompetencyStandards {competencystandardsId: toInteger(trim(row.id))})
ON CREATE SET
  cs.curriculum_id   = toInteger(trim(row.curriculum_id  )),
  cs.code = row.code,
  cs.description   = row.description,
  cs.domain_tag = row.domain_tag ,
  cs.parent_id   = toInteger(trim(row.parent_id)),
  cs.displayLabel = "CompetencyStandards:" + trim(row.code)
RETURN count(cs) AS CompetencyStandardsProcessed;


// **************** Ingest ChapterStandardMap and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///lms_chapter_standard_map.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
MERGE (csm:ChapterStandardMap {chapterstandardmapId: toInteger(trim(row.id))})
ON CREATE SET
  csm.chapter_id    = toInteger(trim(row.chapter_id )),
  csm.competency_standard_id    = toInteger(trim(row.competency_standard_id  )),
  csm.displayLabel = "ChapterStandardMap:" + trim(row.chapter_id )
RETURN count(csm) AS ChapterStandardMapProcessed;


// **************** Ingest AssessmentTypology and Relationships ***************

LOAD CSV WITH HEADERS FROM 'file:///lms_assessment_typology.csv' AS row
WITH row
WHERE row.id IS NOT NULL
  AND trim(row.id) <> ''
MERGE (at:AssessmentTypology {
    assessmenttypologyId: toInteger(trim(row.id))
})
ON CREATE SET
    at.curriculum_id = toInteger(trim(row.curriculum_id)),
    at.total_marks = toInteger(trim(row.total_marks)),
    at.category = row.category,
    at.bloom_levels =
        split(
            replace(
                replace(
                    replace(row.bloom_levels,'[',''),
                ']',''),
            '"',''),
        ','),
    at.weightage_pct = toFloat(trim(row.weightage_pct)),
    at.displayLabel = "AssessmentTypology:" + trim(row.category)
RETURN count(at) AS AssessmentTypologyProcessed;

MATCH (u:Unit)
WHERE u.curriculum_id IS NOT NULL
MATCH (curr:Curriculum {curriculumId: u.curriculum_id})
MERGE (curr)-[:HAS_UNIT]->(u);


// ................ 1. Concept prerequisite graph ................

MATCH (c1:Concept)
MATCH (c2:Concept)
WHERE c1.subject_id = c2.subject_id
  AND c1.chapter_id = c2.chapter_id
  AND c1.conceptId < c2.conceptId
MERGE (c1)-[:PREREQUISITE_OF]->(c2)
RETURN c1.name, c2.name;


// ................ 3. Curriculum -> Lesson -> Concept ................

MATCH (curr:Curriculum)-[:INCLUDES]->(sub:Subject)
OPTIONAL MATCH (sub)-[:HAS_CHAPTER]->(ch:Chapter)
OPTIONAL MATCH (ch)-[:HAS_LESSON]->(l:Lesson)
OPTIONAL MATCH (l)-[:COVERS]->(con:Concept)
RETURN
    curr.curriculum_name,
    sub.display_name,
    ch.chapter_name,
    l.lesson_title,
    con.name;


// ................ 2. Student assessment attempts calculation ................

MATCH (stu:Student)-[:HAS_RESULT]->(r:Result)
MATCH (r)-[:FOR_ASSESSMENT]->(ass:Assessment)
MERGE (stu)-[a:ATTEMPTED]->(ass)
SET
    a.obtain_marks = r.obtain_marks,
    a.total_right = r.total_right,
    a.total_wrong = r.total_wrong,
    a.percentage =
        round(
            (r.obtain_marks * 100.0 / ass.total_marks),
            2
        ),
    a.performance_level =
        CASE
            WHEN r.obtain_marks * 1.0 / ass.total_marks >= 0.8
                THEN "excellent"
            WHEN r.obtain_marks * 1.0 / ass.total_marks >= 0.5
                THEN "average"
            ELSE "weak"
        END,
    a.attempted_at = datetime()
RETURN a;


// ....... 6. Abstraction of concept based mastery relationship from Result data .......

MATCH (stu:Student)-[:HAS_RESULT]->(r:Result)
MATCH (r)-[:FOR_ASSESSMENT]->(ass:Assessment)
MATCH (ass)-[:ASSESSES]->(con:Concept)
WHERE ass.total_marks IS NOT NULL
  AND ass.total_marks > 0
  AND r.obtain_marks IS NOT NULL
WITH
    stu,
    con,
    avg(r.obtain_marks * 1.0 / ass.total_marks) AS mastery_score,
    count(r) AS practice_count,
    max(r.obtain_marks) AS latest_score,
    avg(r.obtain_marks) AS average_marks
MERGE (stu)-[m:MASTERS]->(con)
SET
    m.mastery_score =
        round(mastery_score * 100) / 100,
    m.average_marks =
        round(average_marks * 100) / 100,
    m.times_practiced = practice_count,
    m.latest_score = latest_score,
    m.confidence =
        CASE
            WHEN mastery_score >= 0.8 THEN "high"
            WHEN mastery_score >= 0.5 THEN "medium"
            ELSE "low"
        END,
    m.fluency =
        CASE
            WHEN practice_count >= 10 THEN "high"
            WHEN practice_count >= 5 THEN "medium"
            ELSE "low"
        END,
    m.mastery_level =
        CASE
            WHEN mastery_score >= 0.85 THEN "mastered"
            WHEN mastery_score >= 0.6 THEN "intermediate"
            ELSE "beginner"
        END,
    m.forgetting_risk =
        CASE
            WHEN mastery_score >= 0.8 THEN "low"
            WHEN mastery_score >= 0.5 THEN "medium"
            ELSE "high"
        END,
    m.next_review_date =
        CASE
            WHEN mastery_score >= 0.8
                THEN date() + duration('P14D')
            WHEN mastery_score >= 0.5
                THEN date() + duration('P7D')
            ELSE date() + duration('P2D')
        END,
    m.updated_at = datetime()
RETURN
    stu.stuId,
    con.conceptId,
    m.mastery_level,
    m.mastery_score
LIMIT 100;


// ................ 5. Misconception detection logic build ................

MATCH (stu:Student)-[m:MASTERS]->(con:Concept)
MATCH (mis:Misconception)-[:OCCURS_IN]->(con)
WHERE m.mastery_score < 0.6
MATCH (stu)-[r:HAS_MISCONCEPTION]->(mis)
SET
    r.mastery_score = m.mastery_score,
    r.severity =
        CASE
            WHEN m.mastery_score < 0.3 THEN "high"
            WHEN m.mastery_score < 0.5 THEN "medium"
            ELSE "low"
        END,
    r.detected_at = datetime(),
    r.status = "active",
    r.recommended_action =
        CASE
            WHEN m.mastery_score < 0.3
                THEN "immediate_remediation"
            WHEN m.mastery_score < 0.5
                THEN "practice_required"
            ELSE "revision_required"
        END,
    r.confidence =
        CASE
            WHEN m.times_practiced >= 10 THEN "high"
            WHEN m.times_practiced >= 5 THEN "medium"
            ELSE "low"
        END
RETURN
    stu.stuId,
    mis.misconceptionId,
    r.severity,
    r.recommended_action
LIMIT 100;


// ................ 8. Next best concept traversal ................

MATCH (stu:Student {stuId: 100239})-[:HAS_RESULT]->(r:Result)
MATCH (r)-[:FOR_ASSESSMENT]->(ass:Assessment)
MATCH (ass)-[:ASSESSES]->(con:Concept)
MATCH (stu)-[m:MASTERS]->(con)
WHERE m.bkt_mastery < 0.8
MATCH (mis:Misconception)-[:OCCURS_IN]->(con)
OPTIONAL MATCH (stu)-[hm:HAS_MISCONCEPTION]->(mis)
MATCH (lc:LearningContent)-[:REMEDIATES]->(mis)
WHERE
(
    stu.preferred_pedagogy IS NULL
    OR lc.modality = stu.preferred_pedagogy
    OR lc.modality IS NULL
)
RETURN
    con.name AS concept,
    con.bloom_level AS bloom,
    m.bkt_mastery AS mastery,
    mis.error_type AS misconception,
    hm.severity AS severity,
    lc.title AS content_title,
    lc.content_type AS content_type,
    lc.modality AS modality,
    lc.difficulty_level AS difficulty
ORDER BY
    hm.severity DESC,
    m.bkt_mastery ASC,
    lc.difficulty_level ASC;


MATCH (stu:Student {stuId: 100239})
MATCH (stu)-[m:MASTERS]->(con:Concept)
WHERE m.mastery_score < 0.8
OPTIONAL MATCH (pre:Concept)-[:PREREQUISITE_OF]->(con)
OPTIONAL MATCH (stu)-[pm:MASTERS]->(pre)
OPTIONAL MATCH (mis:Misconception)-[:OCCURS_IN]->(con)
OPTIONAL MATCH (stu)-[hm:HAS_MISCONCEPTION]->(mis)
OPTIONAL MATCH (lc:LearningContent)-[:REMEDIATES]->(mis)
RETURN
    con.conceptId,
    con.name AS weak_concept,
    m.mastery_score,
    pre.name AS prerequisite,
    pm.mastery_score AS prerequisite_mastery,
    mis.error_type AS misconception,
    hm.severity,
    lc.title AS remediation_content,
    lc.content_type,
    lc.modality
ORDER BY
    m.mastery_score ASC,
    hm.severity DESC;


// The end-to-end curriculum spine.
MATCH (st:Standard)-[:HAS_SUBJECT]->(sub:Subject)
      -[:BELONGS_TO_CURRICULUM]->(curr:Curriculum)
      -[:HAS_UNIT]->(u:Unit)
      -[:HAS_CHAPTER]->(ch:Chapter)
      -[:HAS_LESSON]->(l:Lesson)
      -[:COVERS]->(con:Concept)
RETURN st.name, sub.display_name, curr.curriculum_name,
       u.name, ch.chapter_name, l.lesson_title, con.name
LIMIT 20;


MATCH (st:Standard{stId:42})
OPTIONAL MATCH (st)-[:HAS_SUBJECT]->(sub:Subject)
OPTIONAL MATCH (sub)-[:BELONGS_TO_CURRICULUM]->(curr:Curriculum)
OPTIONAL MATCH (curr)-[:HAS_UNIT]->(u:Unit)
OPTIONAL MATCH (u)-[:HAS_CHAPTER]->(ch:Chapter)
OPTIONAL MATCH (ch)-[:HAS_LESSON]->(l:Lesson{lesson_title:'test'})
OPTIONAL MATCH (l)-[:COVERS]->(con:Concept)
RETURN st,sub,curr,u,ch,l,con;


// The real misconception registry — pal_misconception_library, not lms_misconceptions.
LOAD CSV WITH HEADERS FROM 'file:///opt/neo4j-next_lms/import/pal_misconception_library.csv ' AS row
WITH row
WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (m:Misconception {misconceptionId: toInteger(trim(row.id))})
SET m.error_type       = row.tag,
    m.description      = row.description,
    m.error_pattern    = row.error_pattern,
    m.concept_id       = toInteger(trim(row.concept_ref_id)),
    m.chapter_id       = toInteger(trim(row.chapter_ref_id)),
    m.subject          = row.subject,
    m.grade_band       = row.grade_band,
    m.severity_default = row.severity,
    m.prevalence_rate  = toFloat(row.prevalence_rate),
    m.priority_level   = toInteger(trim(row.priority_level)),
    m.sub_institute_id = toInteger(trim(row.sub_institute_id)),
    m.displayLabel     = 'Misconception:' + coalesce(row.tag, trim(row.id)),
    m.status           = 'active'
RETURN count(m) AS misconceptionsLoaded;
