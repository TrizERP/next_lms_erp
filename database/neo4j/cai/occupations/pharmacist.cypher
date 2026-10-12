// =============================================================================
// CAI-CORE — Pharmacist (CAI-CORE expansion, 2026-10-10)
// =============================================================================
//
// onetsoc_code 29-1051.00 "Pharmacists" — confirmed live, 2026-10-10
// (search result title "Pharmacists: 291051", O*NET SOC taxonomy).
//
// SOURCED FACTS (verified via live search 2026-10-10):
//   - B.Pharm requires Class 12 with Physics and Chemistry compulsory, plus
//     EITHER Mathematics OR Biology as the third subject, at ~50% aggregate.
//     Arts/Commerce-stream students cannot apply directly —
//     https://crescent.education/eligibility-pharmacy/
//
// DEVIATION, DOCUMENTED: the real rule is Physics+Chemistry essential plus
// (Maths OR Biology) — a genuine either/or, not both. Modelling Mathematics
// AND Biology as both essential would wrongly flag a PCB-only or PCM-only
// student (each independently eligible) as missing the other. Only Physics
// and Chemistry are therefore marked essential here; the Maths-or-Biology
// branch is not expressible as a single essential set in this graph's
// current edge shape (`essentiality` has no OR grouping) and is left as a
// known, documented limitation rather than silently modelled wrong.
//
// No single national B.Pharm entrance exam exists (admission runs through
// state/institute-level tests, e.g. state CET or direct merit) — per the same
// source — so, as with nurse.cypher, no REQUIRES_EXAM edge is seeded.
// =============================================================================

MERGE (o:Occupation {occupation_id:'OCC-PHARMACIST'})
  SET o.title = 'Pharmacist';

MERGE (st:Stream  {code:'SCIENCE'})    SET st.name = 'Science';
MERGE (p:Subject  {code:'PHYSICS'})    SET p.name = 'Physics';
MERGE (c:Subject  {code:'CHEMISTRY'})  SET c.name = 'Chemistry';
MERGE (dg:Degree  {degree_id:'DEG-BPHARM'}) SET dg.name = 'B.Pharm';

MERGE (o)-[rs:REQUIRES_STREAM]->(st)
  SET rs.source_url = 'https://crescent.education/eligibility-pharmacy/',
      rs.verified_at = date('2026-10-10');
MERGE (o)-[r1:REQUIRES_SUBJECT]->(p)
  SET r1.essentiality = 'essential',
      r1.source_url = 'https://crescent.education/eligibility-pharmacy/',
      r1.verified_at = date('2026-10-10');
MERGE (o)-[r2:REQUIRES_SUBJECT]->(c)
  SET r2.essentiality = 'essential',
      r2.source_url = 'https://crescent.education/eligibility-pharmacy/',
      r2.verified_at = date('2026-10-10');
MERGE (o)-[rd:LEADS_TO_DEGREE]->(dg)
  SET rd.source_url = 'https://crescent.education/eligibility-pharmacy/',
      rd.verified_at = date('2026-10-10');
