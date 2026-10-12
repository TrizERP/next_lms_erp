// =============================================================================
// CAI-CORE — Fashion Designer (CAI-CORE expansion, 2026-10-10)
// =============================================================================
//
// onetsoc_code 27-1022.00 "Fashion Designers" — confirmed live, 2026-10-10
// (https://www.onetonline.org/link/details/27-1022.00).
//
// SOURCED FACTS (verified via live search 2026-10-10):
//   - NIFT (National Institute of Fashion Technology) entrance is open to
//     Class 12 pass in ANY stream (Science, Commerce or Arts) —
//     https://design.careers360.com/articles/nift-eligibility-criteria
//
// DELIBERATELY NO REQUIRES_STREAM / REQUIRES_SUBJECT edge, same reasoning as
// lawyer.cypher and chartered_accountant.cypher: a genuinely stream-neutral
// qualification, correctly represented by the absence of a constraint edge.
// =============================================================================

MERGE (o:Occupation {occupation_id:'OCC-FASHION-DESIGNER'})
  SET o.title = 'Fashion Designer';

MERGE (ex:Exam   {exam_id:'EXAM-NIFT'}) SET ex.name = 'NIFT Entrance Exam';
MERGE (dg:Degree {degree_id:'DEG-BDES'}) SET dg.name = 'B.Des (Fashion Design)';

MERGE (o)-[re:REQUIRES_EXAM]->(ex)
  SET re.source_url = 'https://design.careers360.com/articles/nift-eligibility-criteria',
      re.verified_at = date('2026-10-10');
MERGE (o)-[rd:LEADS_TO_DEGREE]->(dg)
  SET rd.source_url = 'https://design.careers360.com/articles/nift-eligibility-criteria',
      rd.verified_at = date('2026-10-10');
