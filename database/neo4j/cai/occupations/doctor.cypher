// =============================================================================
// CAI-CORE — Doctor / Physician (CAI-CORE expansion, 2026-10-10)
// =============================================================================
//
// SOURCED FACTS (verified via live search 2026-10-10):
//   - NEET-UG eligibility requires Class 12 (any recognised board) with Physics,
//     Chemistry, Biology/Biotechnology and English, at least 50% aggregate in
//     PCB (40% SC/ST/OBC, 45% PwD) —
//     https://sartha.in/blogs/neet-2026-eligibility-criteria-minimum-age-pcb-marks-nri-oci-rules-codes-01-07-12th-marks-requirement
//   - NEET-UG is the single qualifying entrance exam for MBBS admission
//     nationwide (NTA-conducted) — same source.
//
// O*NET occupation_id is a SPECIFIC physician specialty ("Family Medicine
// Physicians", 29-1215.00) rather than the broader "Physicians and Surgeons"
// grouping, because O*NET's current taxonomy (2019+) splits physicians by
// specialty and 29-1215.00 is a real, individually browsable occupation
// (confirmed live, 2026-10-10) — https://www.onetonline.org/link/details/29-1215.00
// A family-medicine MBBS graduate's entry requirements (NEET, PCB, MBBS) are
// identical to every other medical specialty's, so this is the honest
// representative choice rather than a stand-in for "doctor in general".
//
// Stream is the coarse SCIENCE node (see architect.cypher's own documented
// deviation note on why this graph cannot express a PCM/PCB split at the
// Stream level) — the PCB requirement is enforced precisely by the three
// REQUIRES_SUBJECT edges below instead.
// =============================================================================

MERGE (o:Occupation {occupation_id:'OCC-DOCTOR-FAMILY-MEDICINE'})
  SET o.title = 'Doctor (Family Medicine Physician)';

MERGE (st:Stream  {code:'SCIENCE'})    SET st.name = 'Science';
MERGE (p:Subject  {code:'PHYSICS'})    SET p.name = 'Physics';
MERGE (c:Subject  {code:'CHEMISTRY'})  SET c.name = 'Chemistry';
MERGE (b:Subject  {code:'BIOLOGY'})    SET b.name = 'Biology';
MERGE (ex:Exam    {exam_id:'EXAM-NEET-UG'}) SET ex.name = 'NEET-UG';
MERGE (dg:Degree  {degree_id:'DEG-MBBS'})   SET dg.name = 'MBBS';

MERGE (o)-[rs:REQUIRES_STREAM]->(st)
  SET rs.source_url = 'https://sartha.in/blogs/neet-2026-eligibility-criteria-minimum-age-pcb-marks-nri-oci-rules-codes-01-07-12th-marks-requirement',
      rs.verified_at = date('2026-10-10');
MERGE (o)-[r1:REQUIRES_SUBJECT]->(p)
  SET r1.essentiality = 'essential',
      r1.source_url = 'https://sartha.in/blogs/neet-2026-eligibility-criteria-minimum-age-pcb-marks-nri-oci-rules-codes-01-07-12th-marks-requirement',
      r1.verified_at = date('2026-10-10');
MERGE (o)-[r2:REQUIRES_SUBJECT]->(c)
  SET r2.essentiality = 'essential',
      r2.source_url = 'https://sartha.in/blogs/neet-2026-eligibility-criteria-minimum-age-pcb-marks-nri-oci-rules-codes-01-07-12th-marks-requirement',
      r2.verified_at = date('2026-10-10');
MERGE (o)-[r3:REQUIRES_SUBJECT]->(b)
  SET r3.essentiality = 'essential',
      r3.source_url = 'https://sartha.in/blogs/neet-2026-eligibility-criteria-minimum-age-pcb-marks-nri-oci-rules-codes-01-07-12th-marks-requirement',
      r3.verified_at = date('2026-10-10');
MERGE (o)-[re:REQUIRES_EXAM]->(ex)
  SET re.source_url = 'https://sartha.in/blogs/neet-2026-eligibility-criteria-minimum-age-pcb-marks-nri-oci-rules-codes-01-07-12th-marks-requirement',
      re.verified_at = date('2026-10-10');
MERGE (o)-[rd:LEADS_TO_DEGREE]->(dg)
  SET rd.source_url = 'https://sartha.in/blogs/neet-2026-eligibility-criteria-minimum-age-pcb-marks-nri-oci-rules-codes-01-07-12th-marks-requirement',
      rd.verified_at = date('2026-10-10');
