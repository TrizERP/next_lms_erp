// =============================================================================
// CAI-CORE — Lawyer (CAI-CORE expansion, 2026-10-10)
// =============================================================================
//
// onetsoc_code 23-1011.00 "Lawyers" — confirmed live, 2026-10-10
// (https://onetonline.org/link/custom/23-1011.00).
//
// SOURCED FACTS (verified via live search 2026-10-10):
//   - CLAT (Consortium of National Law Universities) is explicitly
//     stream-neutral: candidates from Science, Commerce, Arts or any other
//     stream are eligible, needing only a pass (45% general/OBC/PWD/NRI/OCI/
//     PIO, 40% SC/ST) in Class 12 —
//     https://www.clatnlti.com/blog/clat-eligibility-criteria-2026-age-qualification-and-rules-explained
//
// DELIBERATELY NO REQUIRES_STREAM / REQUIRES_SUBJECT edge. This is not a gap
// in the data — it is the correct graph shape for a genuinely stream-neutral
// occupation: CaiCoreService's own query treats an absent REQUIRES_STREAM as
// "no stream constraint" and an empty REQUIRES_SUBJECT set as "no subject
// requirement" (see cai_core.cypher's OPTIONAL MATCH / empty-collect
// semantics), so omitting both edges makes every real student's subject plan
// evaluate as ALIGNED on this axis, which is the honest real-world answer.
// =============================================================================

MERGE (o:Occupation {occupation_id:'OCC-LAWYER'})
  SET o.title = 'Lawyer';

MERGE (ex:Exam   {exam_id:'EXAM-CLAT'}) SET ex.name = 'CLAT';
MERGE (dg:Degree {degree_id:'DEG-LLB'}) SET dg.name = 'LLB (5-year integrated)';

MERGE (o)-[re:REQUIRES_EXAM]->(ex)
  SET re.source_url = 'https://www.clatnlti.com/blog/clat-eligibility-criteria-2026-age-qualification-and-rules-explained',
      re.verified_at = date('2026-10-10');
MERGE (o)-[rd:LEADS_TO_DEGREE]->(dg)
  SET rd.source_url = 'https://www.clatnlti.com/blog/clat-eligibility-criteria-2026-age-qualification-and-rules-explained',
      rd.verified_at = date('2026-10-10');
