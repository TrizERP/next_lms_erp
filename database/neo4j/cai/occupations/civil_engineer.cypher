// =============================================================================
// CAI-CORE — Civil Engineer (CAI-CORE expansion, 2026-10-10)
// =============================================================================
//
// onetsoc_code 17-2051.00 "Civil Engineers" — confirmed live, 2026-10-10
// (https://www.mynextmove.org/profile/summary/17-2051.00, an O*NET-affiliated
// site; occupation also independently resolvable at onetonline.org under the
// same code).
//
// SOURCED FACTS (verified via live search 2026-10-10):
//   - JEE Main (NTA) B.Tech/B.E eligibility: Class 12 with Physics + Mathematics
//     compulsory, plus one of Chemistry/Biology/Biotechnology/vocational —
//     https://engineering.careers360.com/articles/jee-main-admission-requirements-2026
//
// Same documented deviation as software_developer.cypher: only Physics and
// Mathematics are modelled as essential, because the real third-subject
// requirement is flexible rather than Chemistry-specific.
// =============================================================================

MERGE (o:Occupation {occupation_id:'OCC-CIVIL-ENGINEER'})
  SET o.title = 'Civil Engineer';

MERGE (st:Stream  {code:'SCIENCE'})     SET st.name = 'Science';
MERGE (m:Subject  {code:'MATHEMATICS'}) SET m.name = 'Mathematics';
MERGE (p:Subject  {code:'PHYSICS'})     SET p.name = 'Physics';
MERGE (ex:Exam    {exam_id:'EXAM-JEE-MAIN'})     SET ex.name = 'JEE Main';
MERGE (dg:Degree  {degree_id:'DEG-BTECH-CIVIL'}) SET dg.name = 'B.Tech (Civil Engineering)';

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
