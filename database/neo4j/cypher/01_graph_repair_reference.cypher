// =====================================================================
//  REFERENCE ONLY — NEVER EXECUTED BY `neo4j:cypher`
// =====================================================================
//
//  The repair pass applied on top of `00_k12_reference.cypher`: it is what fixed the
//  spine (Standard->Subject), the HAS_RESULT fan-out, the ASSESSES join, the orphan
//  labels and the misconception/content layer. Kept verbatim as the record of how the
//  live graph reached its present shape.
//
//  IT IS NOT SAFE TO RE-RUN. Section 1.2 Step B DELETES 518,266 HAS_RESULT
//  relationships and 1.8 DETACH DELETEs two Misconception nodes; several statements use
//  bare `SET`, which overwrites properties on nodes that already exist. The module
//  scripts in this directory are additive precisely because this one is not.
//
//  `neo4j:cypher` refuses any file whose name starts `00_` or `01_`, and would refuse
//  this one twice over — its destructive-verb guard rejects DELETE, REMOVE, DROP and
//  DETACH anywhere in a file.
//
//  Source: reference_code.txt, supplied by the owner 2026-09-04.
//  Target : dev.triz.co.in (Neo4j 4.4.40 Community, no APOC)
//  Run    : cypher-shell -a bolt://localhost:7688 -u neo4j -p admin -f <this file>
// =====================================================================


// =====================================================================
//  PART 1 — PURE CYPHER. Safe to run right now, in this order.
// =====================================================================

// ---------------------------------------------------------------------
// 1.1  SPINE FIX — Standard -> Subject for the CSV-loaded subjects.
//      The document's HAS_SUBJECT never matched: it joined app-sync
//      Standard/Subject nodes (uid-keyed), never the CSV ones (subId).
//      This is what makes the end-to-end PAL traversal return 0 rows.
//      Expected: 836 relationships.
// ---------------------------------------------------------------------
MATCH (sub:Subject)
WHERE sub.subId IS NOT NULL AND sub.standard_id IS NOT NULL
MATCH (st:Standard {stId: sub.standard_id})
MERGE (st)-[:HAS_SUBJECT]->(sub);


// ---------------------------------------------------------------------
// 1.2  HAS_RESULT FAN-OUT FIX
//      lms_online_exam.student_id is tblstudent.id (the student MASTER
//      id), not tblstudent_enrollment.student_id. Matching on
//      Student.student_id fanned every Result out to ~3.5 Student nodes
//      (518,266 edges for 149,045 Results).
//
//      Step A: attach Results to StuDetail (the true 1:1 owner).
//      Step B: drop the inflated Student edges.
//      Step C: re-attach ONE Student edge (latest enrolment) so the
//              existing Student-based PAL queries keep working.
//
//      `:auto` is required for CALL {} IN TRANSACTIONS in cypher-shell.
// ---------------------------------------------------------------------

// Step A — correct owner edge. Expected: 117,069.
:auto MATCH (r:Result)
WHERE r.student_id IS NOT NULL
MATCH (sd:StuDetail {sdId: r.student_id})
CALL { WITH sd, r MERGE (sd)-[:HAS_RESULT]->(r) } IN TRANSACTIONS OF 10000 ROWS;

// Step B — remove the fanned-out Student edges.
:auto MATCH (:Student)-[rel:HAS_RESULT]->(:Result)
CALL { WITH rel DELETE rel } IN TRANSACTIONS OF 10000 ROWS;

// Step C — one Student edge per Result, on the most recent enrolment.
:auto MATCH (sd:StuDetail)-[:HAS_RESULT]->(r:Result)
MATCH (sd)-[:HAS_STUDENT]->(s:Student)
WITH r, s ORDER BY s.syear DESC
WITH r, head(collect(s)) AS latest
CALL { WITH latest, r MERGE (latest)-[:HAS_RESULT]->(r) } IN TRANSACTIONS OF 10000 ROWS;


// ---------------------------------------------------------------------
// 1.3  Assessment -> Concept  (ASSESSES)
//      question_paper has NO concept_id column, so the document's
//      `ass.concept_id` join can never work. Route through the chapter
//      instead, which is the only populated key.
//      Expected: ~130 relationships.
// ---------------------------------------------------------------------
MATCH (a:Assessment)-[:HAS_QUESTION]->(:Question)-[:BELONGS_TO]->(ch:Chapter)-[:HAS_CONCEPT]->(c:Concept)
MERGE (a)-[:ASSESSES]->(c);

// refresh ASSESSES_CHAPTER off the same path
MATCH (a:Assessment)-[:HAS_QUESTION]->(:Question)-[:BELONGS_TO]->(ch:Chapter)
MERGE (a)-[:ASSESSES_CHAPTER]->(ch);


// ---------------------------------------------------------------------
// 1.4  Student -> Concept  (MASTERS) rebuilt with real values.
//      The 2 existing MASTERS edges have every property NULL.
//      Run this AFTER 1.2, 1.3 and (for full coverage) PART 2.4.
// ---------------------------------------------------------------------
MATCH (stu:Student)-[:HAS_RESULT]->(r:Result)-[:FOR_ASSESSMENT]->(ass:Assessment)
MATCH (ass)-[:ASSESSES]->(con:Concept)
WHERE ass.total_marks IS NOT NULL AND ass.total_marks > 0
  AND r.obtain_marks IS NOT NULL
WITH stu, con,
     avg(toFloat(r.obtain_marks) / ass.total_marks) AS raw_mastery,
     count(r)                                       AS practice_count,
     max(toFloat(r.obtain_marks) / ass.total_marks) AS best_ratio
WITH stu, con, practice_count, best_ratio,
     CASE WHEN raw_mastery > 1.0 THEN 1.0 ELSE raw_mastery END AS mastery
MERGE (stu)-[m:MASTERS]->(con)
SET m.mastery_score  = round(mastery * 100) / 100.0,
    m.bkt_mastery    = round(mastery * 100) / 100.0,
    m.times_practiced = practice_count,
    m.confidence     = CASE WHEN mastery >= 0.8 THEN 'high'
                            WHEN mastery >= 0.5 THEN 'medium'
                            ELSE 'low' END,
    m.fluency        = CASE WHEN practice_count >= 10 THEN 'high'
                            WHEN practice_count >= 5  THEN 'medium'
                            ELSE 'low' END,
    m.mastery_level  = CASE WHEN mastery >= 0.85 THEN 'mastered'
                            WHEN mastery >= 0.6  THEN 'intermediate'
                            ELSE 'beginner' END,
    m.forgetting_risk = CASE WHEN mastery >= 0.8 THEN 'low'
                             WHEN mastery >= 0.5 THEN 'medium'
                             ELSE 'high' END,
    m.last_attempt_correct = best_ratio >= 0.5,
    m.next_review_date = CASE WHEN mastery >= 0.8 THEN date() + duration('P14D')
                              WHEN mastery >= 0.5 THEN date() + duration('P7D')
                              ELSE date() + duration('P2D') END,
    m.updated_at = datetime();


// ---------------------------------------------------------------------
// 1.5  Student -> Assessment (ATTEMPTED) with full properties.
//      Currently 391 edges but only 72 carry `percentage`.
// ---------------------------------------------------------------------
MATCH (stu:Student)-[:HAS_RESULT]->(r:Result)-[:FOR_ASSESSMENT]->(ass:Assessment)
WHERE ass.total_marks IS NOT NULL AND ass.total_marks > 0
MERGE (stu)-[a:ATTEMPTED]->(ass)
SET a.obtain_marks = r.obtain_marks,
    a.total_right  = r.total_right,
    a.total_wrong  = r.total_wrong,
    a.percentage   = round(r.obtain_marks * 100.0 / ass.total_marks * 100) / 100.0,
    a.performance_level = CASE
        WHEN toFloat(r.obtain_marks) / ass.total_marks >= 0.8 THEN 'excellent'
        WHEN toFloat(r.obtain_marks) / ass.total_marks >= 0.5 THEN 'average'
        ELSE 'weak' END,
    a.attempted_at = datetime();


// ---------------------------------------------------------------------
// 1.6  ORPHAN NODES — the document creates these but defines no
//      relationships at all. Every FK is already on the node, so these
//      are pure-Cypher links.
// ---------------------------------------------------------------------

// ChapterStandardMap -> Chapter (96) and -> CompetencyStandards (96)
MATCH (csm:ChapterStandardMap)
MATCH (ch:Chapter {chId: csm.chapter_id})
MERGE (csm)-[:MAPS_CHAPTER]->(ch);

MATCH (csm:ChapterStandardMap)
MATCH (cs:CompetencyStandards {competencystandardsId: csm.competency_standard_id})
MERGE (csm)-[:MAPS_COMPETENCY]->(cs);

// direct shortcut so dashboards don't have to hop the join node (96)
MATCH (csm:ChapterStandardMap)
MATCH (ch:Chapter {chId: csm.chapter_id})
MATCH (cs:CompetencyStandards {competencystandardsId: csm.competency_standard_id})
MERGE (ch)-[:ALIGNED_TO]->(cs);

// CompetencyStandards -> Curriculum (40) + self-hierarchy (29)
MATCH (cs:CompetencyStandards)
MATCH (curr:Curriculum {curriculumId: cs.curriculum_id})
MERGE (curr)-[:HAS_COMPETENCY_STANDARD]->(cs);

MATCH (cs:CompetencyStandards)
WHERE cs.parent_id IS NOT NULL
MATCH (p:CompetencyStandards {competencystandardsId: cs.parent_id})
MERGE (p)-[:PARENT_OF]->(cs);

// AssessmentTypology -> Curriculum (3)
MATCH (at:AssessmentTypology)
MATCH (curr:Curriculum {curriculumId: at.curriculum_id})
MERGE (curr)-[:HAS_TYPOLOGY]->(at);

// LearningObjects -> Curriculum / Subject / Standard (8 each)
MATCH (lo:LearningObjects)
MATCH (curr:Curriculum {curriculumId: lo.curriculum_id})
MERGE (curr)-[:HAS_LEARNING_OBJECTIVE]->(lo);

MATCH (lo:LearningObjects)
MATCH (sub:Subject {subId: lo.subject_id})
MERGE (sub)-[:HAS_LEARNING_OBJECTIVE]->(lo);

MATCH (lo:LearningObjects)
MATCH (st:Standard {stId: lo.standard_id})
MERGE (st)-[:HAS_LEARNING_OBJECTIVE]->(lo);

// Teacher -> Subject. Only 7 of 118 Teachers have subject_ids populated,
// so this links 7; the rest need the source column backfilled.
MATCH (t:Teacher)
WHERE t.subject_ids IS NOT NULL AND trim(t.subject_ids) <> ''
UNWIND split(t.subject_ids, ',') AS sid
MATCH (sub:Subject {subId: toInteger(trim(sid))})
MERGE (t)-[:TEACHES_SUBJECT]->(sub);


// ---------------------------------------------------------------------
// 1.7  Repair the null displayLabels the document produced by reading
//      columns that do not exist in the source tables.
// ---------------------------------------------------------------------
MATCH (l:Lesson) WHERE l.displayLabel IS NULL AND l.lessonId IS NOT NULL
SET l.displayLabel = 'Lesson:' + coalesce(l.lesson_title, toString(l.lessonId));

MATCH (lc:LearningContent) WHERE lc.displayLabel IS NULL
SET lc.displayLabel = 'LearningContent:' + coalesce(lc.title, toString(lc.contentId));


// ---------------------------------------------------------------------
// 1.8  Remove the 2 malformed Misconception nodes (misconceptionId NULL).
//      The unique constraint ignores NULLs, so they slipped through.
//      Guarded so it can never touch the app-sync (uid) nodes.
// ---------------------------------------------------------------------
MATCH (m:Misconception)
WHERE m.misconceptionId IS NULL AND m.uid IS NULL
DETACH DELETE m;


// =====================================================================
//  PART 2 — NEEDS A FRESH CSV EXPORT FIRST
//
//  These four could never work from the CSVs the document names,
//  because the source columns/tables do not exist:
//    * lms_misconceptions      -> table does not exist at all
//    * lms_concept.lesson_id   -> column does not exist
//    * question_paper.concept_id -> column does not exist
//    * suggested_content.concept_id / .misconception_id / .title
//                              -> columns do not exist
//
//  Export these on the MySQL host (vivek_erp), then copy the files to
//  /opt/neo4j-next_lms/import/ on the Neo4j box.
//
//  -- run in mysql, adjust the outfile dir to your secure_file_priv --
//
//  SELECT 'id','tag','concept_ref_id','chapter_ref_id','subject',
//         'grade_band','description','error_pattern','severity',
//         'prevalence_rate','priority_level','sub_institute_id'
//  UNION ALL
//  SELECT id, tag, concept_ref_id, chapter_ref_id, subject, grade_band,
//         REPLACE(REPLACE(COALESCE(description,''),'\r',' '),'\n',' '),
//         REPLACE(REPLACE(COALESCE(error_pattern,''),'\r',' '),'\n',' '),
//         COALESCE(severity,''), COALESCE(prevalence_rate,0),
//         COALESCE(priority_level,0), sub_institute_id
//  FROM pal_misconception_library
//  INTO OUTFILE '/var/lib/mysql-files/pal_misconception_library.csv'
//  FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"' ESCAPED BY '\\'
//  LINES TERMINATED BY '\n';
//
//  SELECT 'id','misconception_id','title','format','h5p_type','language',
//         'estimated_duration_minutes','priority_level','sub_institute_id'
//  UNION ALL
//  SELECT id, misconception_id,
//         REPLACE(REPLACE(COALESCE(title,''),'\r',' '),'\n',' '),
//         COALESCE(format,''), COALESCE(h5p_type,''), COALESCE(language,''),
//         COALESCE(estimated_duration_minutes,0), COALESCE(priority_level,0),
//         sub_institute_id
//  FROM pal_misconception_corrective
//  INTO OUTFILE '/var/lib/mysql-files/pal_misconception_corrective.csv'
//  FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"' ESCAPED BY '\\'
//  LINES TERMINATED BY '\n';
//
//  -- full question_paper, not the 133-row subset currently loaded --
//  SELECT 'id','grade_id','standard_id','subject_id','paper_name',
//         'total_marks','total_ques','question_ids','exam_type',
//         'syear','sub_institute_id'
//  UNION ALL
//  SELECT id, COALESCE(grade_id,0), COALESCE(standard_id,0),
//         COALESCE(subject_id,0),
//         REPLACE(REPLACE(COALESCE(paper_name,''),'\r',' '),'\n',' '),
//         COALESCE(total_marks,0), COALESCE(total_ques,0),
//         COALESCE(question_ids,''), COALESCE(exam_type,''),
//         COALESCE(syear,0), sub_institute_id
//  FROM question_paper
//  INTO OUTFILE '/var/lib/mysql-files/question_paper_full.csv'
//  FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"' ESCAPED BY '\\'
//  LINES TERMINATED BY '\n';
// =====================================================================


// ---------------------------------------------------------------------
// 2.1  Misconception nodes from the REAL registry (3,662 rows).
//      Replaces the document's non-existent lms_misconceptions.csv.
// ---------------------------------------------------------------------
LOAD CSV WITH HEADERS FROM 'file:///pal_misconception_library.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (m:Misconception {misconceptionId: toInteger(trim(row.id))})
SET m.error_type       = row.tag,
    m.description      = row.description,
    m.error_pattern    = row.error_pattern,
    m.concept_id       = toInteger(trim(row.concept_ref_id)),
    m.chapter_id       = toInteger(trim(row.chapter_ref_id)),
    m.subject          = row.subject,
    m.grade_band        = row.grade_band,
    m.severity_default = row.severity,
    m.prevalence_rate  = toFloat(row.prevalence_rate),
    m.priority_level   = toInteger(trim(row.priority_level)),
    m.sub_institute_id = toInteger(trim(row.sub_institute_id)),
    m.displayLabel     = 'Misconception:' + coalesce(row.tag, trim(row.id)),
    m.status           = 'active'
RETURN count(m) AS misconceptionsLoaded;


// ---------------------------------------------------------------------
// 2.2  Misconception -> Concept  (OCCURS_IN)
//      This relationship type does not exist in the graph at all today.
//      Expected: up to ~3,567 where the Concept node is present.
// ---------------------------------------------------------------------
MATCH (m:Misconception)
WHERE m.concept_id IS NOT NULL
MATCH (con:Concept {conceptId: m.concept_id})
MERGE (m)-[:OCCURS_IN]->(con);

// chapter-level fallback for misconceptions whose concept isn't loaded
MATCH (m:Misconception)
WHERE m.chapter_id IS NOT NULL AND NOT (m)-[:OCCURS_IN]->(:Concept)
MATCH (ch:Chapter {chId: m.chapter_id})
MERGE (m)-[:OCCURS_IN_CHAPTER]->(ch);


// ---------------------------------------------------------------------
// 2.3  LearningContent from pal_misconception_corrective (7,307 rows)
//      + REMEDIATES + TEACHES. All three are missing today.
// ---------------------------------------------------------------------
LOAD CSV WITH HEADERS FROM 'file:///pal_misconception_corrective.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (lc:LearningContent {contentId: toInteger(trim(row.id))})
SET lc.title            = row.title,
    lc.misconception_id = toInteger(trim(row.misconception_id)),
    lc.content_type     = row.format,
    lc.h5p_type         = row.h5p_type,
    lc.modality         = row.format,
    lc.language         = row.language,
    lc.duration_minutes = toInteger(trim(row.estimated_duration_minutes)),
    lc.priority_level   = toInteger(trim(row.priority_level)),
    lc.sub_institute_id = toInteger(trim(row.sub_institute_id)),
    lc.displayLabel     = 'LearningContent:' + coalesce(row.title, trim(row.id))
RETURN count(lc) AS learningContentLoaded;

// LearningContent -> Misconception (REMEDIATES)
MATCH (lc:LearningContent)
WHERE lc.misconception_id IS NOT NULL
MATCH (m:Misconception {misconceptionId: lc.misconception_id})
MERGE (lc)-[:REMEDIATES]->(m);

// LearningContent -> Concept (TEACHES), derived through the misconception
MATCH (lc:LearningContent)-[:REMEDIATES]->(:Misconception)-[:OCCURS_IN]->(con:Concept)
MERGE (lc)-[:TEACHES]->(con);


// ---------------------------------------------------------------------
// 2.4  Full Assessment re-ingest (5,456 papers vs the 133 loaded).
//      This is what strands 148,565 Results with no FOR_ASSESSMENT edge.
// ---------------------------------------------------------------------
LOAD CSV WITH HEADERS FROM 'file:///question_paper_full.csv' AS row
WITH row WHERE row.id IS NOT NULL AND trim(row.id) <> ''
MERGE (ass:Assessment {assId: toInteger(trim(row.id))})
SET ass.grade_id        = toInteger(trim(row.grade_id)),
    ass.standard_id     = toInteger(trim(row.standard_id)),
    ass.subject_id      = toInteger(trim(row.subject_id)),
    ass.paper_name      = row.paper_name,
    ass.total_marks     = toInteger(trim(row.total_marks)),
    ass.total_ques      = toInteger(trim(row.total_ques)),
    ass.question_ids    = row.question_ids,
    ass.exam_type       = row.exam_type,
    ass.syear           = toInteger(trim(row.syear)),
    ass.sub_institute_id = toInteger(trim(row.sub_institute_id)),
    ass.displayLabel    = 'Assessment:' + coalesce(row.paper_name, trim(row.id))
RETURN count(ass) AS assessmentsLoaded;

// Result -> Assessment, now that the papers exist
:auto MATCH (r:Result)
WHERE r.question_paper_id IS NOT NULL
MATCH (ass:Assessment {assId: r.question_paper_id})
CALL { WITH r, ass MERGE (r)-[:FOR_ASSESSMENT]->(ass) } IN TRANSACTIONS OF 10000 ROWS;

// Subject -> Assessment
MATCH (ass:Assessment)
WHERE ass.subject_id IS NOT NULL
MATCH (sub:Subject {subId: ass.subject_id})
MERGE (sub)-[:HAS_ASSESSMENT]->(ass);

// Assessment -> Question
MATCH (ass:Assessment)
WHERE ass.question_ids IS NOT NULL AND trim(ass.question_ids) <> ''
UNWIND split(ass.question_ids, ',') AS qid
MATCH (q:Question {qId: toInteger(trim(qid))})
MERGE (ass)-[:HAS_QUESTION]->(q);


// ---------------------------------------------------------------------
// 2.5  Student -> Misconception  (HAS_MISCONCEPTION)
//      Missing entirely today. Run AFTER 1.4 (MASTERS) and 2.2.
// ---------------------------------------------------------------------
MATCH (stu:Student)-[m:MASTERS]->(con:Concept)
MATCH (mis:Misconception)-[:OCCURS_IN]->(con)
WHERE m.mastery_score < 0.6
MERGE (stu)-[r:HAS_MISCONCEPTION]->(mis)
SET r.mastery_score = m.mastery_score,
    r.severity = CASE WHEN m.mastery_score < 0.3 THEN 'high'
                      WHEN m.mastery_score < 0.5 THEN 'medium'
                      ELSE 'low' END,
    r.recommended_action = CASE WHEN m.mastery_score < 0.3 THEN 'immediate_remediation'
                                WHEN m.mastery_score < 0.5 THEN 'practice_required'
                                ELSE 'revision_required' END,
    r.confidence = CASE WHEN m.times_practiced >= 10 THEN 'high'
                        WHEN m.times_practiced >= 5  THEN 'medium'
                        ELSE 'low' END,
    r.detected_at = datetime(),
    r.status = 'active';


// ---------------------------------------------------------------------
// 2.6  Lesson -> Concept  (COVERS)
//      lms_concept has no lesson_id column, so the document's join is
//      impossible. Derive it through the chapter both nodes share.
//      Only `is_introduction` is guessed; the rest is real data.
// ---------------------------------------------------------------------
MATCH (ch:Chapter)-[:HAS_LESSON]->(l:Lesson)
MATCH (ch)-[:HAS_CONCEPT]->(con:Concept)
MERGE (l)-[r:COVERS]->(con)
ON CREATE SET r.coverage_depth = 'medium',
              r.derived_via    = 'chapter',
              r.created_at     = datetime();


// =====================================================================
//  PART 3 — VERIFY. Every row should now be non-zero.
// =====================================================================
MATCH (:Misconception)-[r:OCCURS_IN]->(:Concept)        RETURN 'OCCURS_IN' AS rel, count(r) AS cnt
UNION ALL MATCH (:Student)-[r:HAS_MISCONCEPTION]->(:Misconception) RETURN 'HAS_MISCONCEPTION' AS rel, count(r) AS cnt
UNION ALL MATCH (:LearningContent)-[r:TEACHES]->(:Concept)         RETURN 'TEACHES' AS rel, count(r) AS cnt
UNION ALL MATCH (:LearningContent)-[r:REMEDIATES]->(:Misconception) RETURN 'REMEDIATES' AS rel, count(r) AS cnt
UNION ALL MATCH (:Lesson)-[r:COVERS]->(:Concept)         RETURN 'COVERS' AS rel, count(r) AS cnt
UNION ALL MATCH (:Assessment)-[r:ASSESSES]->(:Concept)   RETURN 'ASSESSES' AS rel, count(r) AS cnt
UNION ALL MATCH (:Student)-[r:MASTERS]->(:Concept)       RETURN 'MASTERS' AS rel, count(r) AS cnt
UNION ALL MATCH (:Result)-[r:FOR_ASSESSMENT]->(:Assessment) RETURN 'FOR_ASSESSMENT' AS rel, count(r) AS cnt
UNION ALL MATCH (:Student)-[r:HAS_RESULT]->(:Result)     RETURN 'HAS_RESULT' AS rel, count(r) AS cnt
UNION ALL MATCH (:Chapter)-[r:ALIGNED_TO]->(:CompetencyStandards) RETURN 'ALIGNED_TO' AS rel, count(r) AS cnt;

// the spine that returns 0 rows today
MATCH (st:Standard)-[:HAS_SUBJECT]->(sub:Subject)-[:BELONGS_TO_CURRICULUM]->(curr:Curriculum)
      -[:HAS_UNIT]->(u:Unit)-[:HAS_CHAPTER]->(ch:Chapter)-[:HAS_LESSON]->(l:Lesson)-[:COVERS]->(con:Concept)
RETURN st.name, sub.display_name, curr.curriculum_name, u.name,
       ch.chapter_name, l.lesson_title, con.name
LIMIT 20;
