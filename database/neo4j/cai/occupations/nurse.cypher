// =============================================================================
// CAI-CORE — Nurse (CAI-CORE expansion, 2026-10-10)
// =============================================================================
//
// onetsoc_code 29-1141.00 "Registered Nurses" — confirmed live, 2026-10-10
// (https://www.mynextmove.org/profile/summary/29-1141.00, O*NET-affiliated).
//
// SOURCED FACTS (verified via live search 2026-10-10):
//   - B.Sc Nursing requires Class 12 Science stream with Physics, Chemistry,
//     Biology at ~50% aggregate, plus English as a compulsory subject —
//     https://medicine.careers360.com/articles/bsc-nursing-admission
//
// DEVIATION, DOCUMENTED: unlike MBBS/BDS/BVSc, B.Sc Nursing has NO single
// nationwide mandatory entrance exam — "many institutions require NEET, AIIMS
// BSc Nursing, or a state-specific test," per the same source, and which one
// applies depends on the institute, not a fixed national rule. Seeding
// REQUIRES_EXAM here with any one exam (NEET in particular) would wrongly
// gate out a student bound for an institute that uses a different route, or
// none at all — so no REQUIRES_EXAM edge is seeded for this occupation. The
// REQUIRES_STREAM/REQUIRES_SUBJECT edges below are the real, nationwide-
// consistent part of the rule.
// =============================================================================

MERGE (o:Occupation {occupation_id:'OCC-NURSE'})
  SET o.title = 'Nurse';

MERGE (st:Stream  {code:'SCIENCE'})    SET st.name = 'Science';
MERGE (p:Subject  {code:'PHYSICS'})    SET p.name = 'Physics';
MERGE (c:Subject  {code:'CHEMISTRY'})  SET c.name = 'Chemistry';
MERGE (b:Subject  {code:'BIOLOGY'})    SET b.name = 'Biology';
MERGE (dg:Degree  {degree_id:'DEG-BSC-NURSING'}) SET dg.name = 'B.Sc Nursing';

MERGE (o)-[rs:REQUIRES_STREAM]->(st)
  SET rs.source_url = 'https://medicine.careers360.com/articles/bsc-nursing-admission',
      rs.verified_at = date('2026-10-10');
MERGE (o)-[r1:REQUIRES_SUBJECT]->(p)
  SET r1.essentiality = 'essential',
      r1.source_url = 'https://medicine.careers360.com/articles/bsc-nursing-admission',
      r1.verified_at = date('2026-10-10');
MERGE (o)-[r2:REQUIRES_SUBJECT]->(c)
  SET r2.essentiality = 'essential',
      r2.source_url = 'https://medicine.careers360.com/articles/bsc-nursing-admission',
      r2.verified_at = date('2026-10-10');
MERGE (o)-[r3:REQUIRES_SUBJECT]->(b)
  SET r3.essentiality = 'essential',
      r3.source_url = 'https://medicine.careers360.com/articles/bsc-nursing-admission',
      r3.verified_at = date('2026-10-10');
MERGE (o)-[rd:LEADS_TO_DEGREE]->(dg)
  SET rd.source_url = 'https://medicine.careers360.com/articles/bsc-nursing-admission',
      rd.verified_at = date('2026-10-10');
