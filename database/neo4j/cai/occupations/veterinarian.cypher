// =============================================================================
// CAI-CORE — Veterinarian (CAI-CORE expansion, 2026-10-10)
// =============================================================================
//
// onetsoc_code 29-1131.00 "Veterinarians" — confirmed live, 2026-10-10
// (official Veterinary Council of India / DAHD circular, see source below).
//
// SOURCED FACTS (verified via live search 2026-10-10):
//   - BVSc & AH admission is through NEET (UG) merit, same exam as MBBS/BDS —
//     per Veterinary Council of India (VCI-MSVE Regulations, 2016), official
//     government source:
//     https://www.vci.dahd.gov.in/sites/default/files/News%20update/CompositIBAY2026-27.pdf
//   - Qualifying exam must include Physics, Chemistry, Biology/Biotechnology
//     and English, with 50% aggregate general / 47.5% SC/ST — same source.
// =============================================================================

MERGE (o:Occupation {occupation_id:'OCC-VETERINARIAN'})
  SET o.title = 'Veterinarian';

MERGE (st:Stream  {code:'SCIENCE'})    SET st.name = 'Science';
MERGE (p:Subject  {code:'PHYSICS'})    SET p.name = 'Physics';
MERGE (c:Subject  {code:'CHEMISTRY'})  SET c.name = 'Chemistry';
MERGE (b:Subject  {code:'BIOLOGY'})    SET b.name = 'Biology';
MERGE (ex:Exam    {exam_id:'EXAM-NEET-UG'})   SET ex.name = 'NEET-UG';
MERGE (dg:Degree  {degree_id:'DEG-BVSC-AH'})  SET dg.name = 'B.V.Sc & A.H.';

MERGE (o)-[rs:REQUIRES_STREAM]->(st)
  SET rs.source_url = 'https://www.vci.dahd.gov.in/sites/default/files/News%20update/CompositIBAY2026-27.pdf',
      rs.verified_at = date('2026-10-10');
MERGE (o)-[r1:REQUIRES_SUBJECT]->(p)
  SET r1.essentiality = 'essential',
      r1.source_url = 'https://www.vci.dahd.gov.in/sites/default/files/News%20update/CompositIBAY2026-27.pdf',
      r1.verified_at = date('2026-10-10');
MERGE (o)-[r2:REQUIRES_SUBJECT]->(c)
  SET r2.essentiality = 'essential',
      r2.source_url = 'https://www.vci.dahd.gov.in/sites/default/files/News%20update/CompositIBAY2026-27.pdf',
      r2.verified_at = date('2026-10-10');
MERGE (o)-[r3:REQUIRES_SUBJECT]->(b)
  SET r3.essentiality = 'essential',
      r3.source_url = 'https://www.vci.dahd.gov.in/sites/default/files/News%20update/CompositIBAY2026-27.pdf',
      r3.verified_at = date('2026-10-10');
MERGE (o)-[re:REQUIRES_EXAM]->(ex)
  SET re.source_url = 'https://www.vci.dahd.gov.in/sites/default/files/News%20update/CompositIBAY2026-27.pdf',
      re.verified_at = date('2026-10-10');
MERGE (o)-[rd:LEADS_TO_DEGREE]->(dg)
  SET rd.source_url = 'https://www.vci.dahd.gov.in/sites/default/files/News%20update/CompositIBAY2026-27.pdf',
      rd.verified_at = date('2026-10-10');
