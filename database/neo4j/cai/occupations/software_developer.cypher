// =============================================================================
// CAI-CORE — Software Developer (CAI-CORE expansion, 2026-10-10)
// =============================================================================
//
// occupation_id/onetsoc_code chosen to match a REAL `student_aspirations` row
// already in this database (id 15, student_id 97926, occupation_id
// '15-1252.00', occupation_name 'Software Developers') — so this file is not
// just schema-complete, it makes an existing real aspiration evaluable for
// the first time instead of silently returning INSUFFICIENT_DATA forever.
//
// SOURCED FACTS (verified via live search 2026-10-10):
//   - JEE Main (NTA) B.Tech/B.E eligibility: Class 12 with Physics + Mathematics
//     compulsory, plus one of Chemistry/Biology/Biotechnology/a technical
//     vocational subject —
//     https://engineering.careers360.com/articles/jee-main-admission-requirements-2026
//   - JEE Main is the qualifying entrance exam feeding NIT/IIIT/GFTI B.Tech CS
//     admission (and, via JEE Advanced, the IITs) — same source.
//
// DEVIATION, DOCUMENTED (same reasoning as architect.cypher's own note): the
// real eligibility rule is Physics + Maths compulsory with a FLEXIBLE third
// subject (Chemistry/Biology/vocational all qualify). This graph models only
// Physics and Mathematics as REQUIRES_SUBJECT essential — adding Chemistry as
// a third essential subject would be MORE restrictive than the real rule and
// would wrongly flag a genuinely eligible Biology-stream-with-Maths student as
// missing a subject they never needed.
// =============================================================================

MERGE (o:Occupation {occupation_id:'OCC-SOFTWARE-DEVELOPER'})
  SET o.title = 'Software Developer';

MERGE (st:Stream  {code:'SCIENCE'})     SET st.name = 'Science';
MERGE (m:Subject  {code:'MATHEMATICS'}) SET m.name = 'Mathematics';
MERGE (p:Subject  {code:'PHYSICS'})     SET p.name = 'Physics';
MERGE (ex:Exam    {exam_id:'EXAM-JEE-MAIN'})  SET ex.name = 'JEE Main';
MERGE (dg:Degree  {degree_id:'DEG-BTECH-CS'}) SET dg.name = 'B.Tech (Computer Science)';

MERGE (o)-[rs:REQUIRES_STREAM]->(st)
  SET rs.source_url = 'https://engineering.careers360.com/articles/jee-main-admission-requirements-2026',
      rs.verified_at = date('2026-10-10');
MERGE (o)-[r1:REQUIRES_SUBJECT]->(m)
  SET r1.essentiality = 'essential',
      r1.source_url = 'https://engineering.careers360.com/articles/jee-main-admission-requirements-2026',
      r1.verified_at = date('2026-10-10');
MERGE (o)-[r2:REQUIRES_SUBJECT]->(p)
  SET r2.essentiality = 'essential',
      r2.source_url = 'https://engineering.careers360.com/articles/jee-main-admission-requirements-2026',
      r2.verified_at = date('2026-10-10');
MERGE (o)-[re:REQUIRES_EXAM]->(ex)
  SET re.source_url = 'https://engineering.careers360.com/articles/jee-main-admission-requirements-2026',
      re.verified_at = date('2026-10-10');
MERGE (o)-[rd:LEADS_TO_DEGREE]->(dg)
  SET rd.source_url = 'https://engineering.careers360.com/articles/jee-main-admission-requirements-2026',
      rd.verified_at = date('2026-10-10');
