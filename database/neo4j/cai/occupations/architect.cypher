// =============================================================================
// CAI-CORE — Architect (CI-GUIDE-DEV-001 Group B, Step B2)
// =============================================================================
//
// SOURCED FACTS (verified 2026-08-26):
//   - 10+2 with Physics, Chemistry, Mathematics compulsory, min. aggregate marks,
//     per Council of Architecture (Minimum Standards of Architectural Education)
//     Regulations, 2020 — https://coa.gov.in/app/myauth/notification/COA_Minimum_Standards_of_Architectural_Education_Regulations_2020.pdf
//     (secondary confirmation: https://educationasia.in/notice-details/coa-makes-pcm-mandatory-for-b-arch-admissions)
//   - NATA is the qualifying entrance exam (JEE Main Paper 2 is an accepted
//     alternative at some institutes, not modelled here — REQUIRES_EXAM only
//     carries NATA, matching the wedge's single-exam example) —
//     https://www.nata.in/assets/pdf/Final-NATA-BROCHURE-2026.pdf (brochure
//     dated "Updated On 4th May 2026" on nata.in)
//   - B.Arch is the qualifying degree, same COA regulation as above.
//
// DEVIATION FROM THE GIVEN SEED SHAPE, DOCUMENTED:
//   The reference cai_core.cypher uses Stream{code:'SCIENCE_PCM'}. This ERP's
//   real enrolment data (see App\CareerIntelligence\Ingestion\
//   ErpSubjectEnrolmentAdapter::resolveStream, verified against real students in
//   Phase 2) can only ever produce a coarse SCIENCE/COMMERCE/ARTS stream code —
//   it has no reliable way to distinguish a PCM combination from a PCB one at
//   the stream level (that distinction isn't a labelled attribute anywhere in
//   the source data). Using 'SCIENCE_PCM' here would mean $current_stream from
//   a real DeclaredPlan could NEVER equal reqStream.code, so a real PCM student
//   would always show ERR_STREAM_MISMATCH — a false negative, exactly the
//   "confident lie" this system exists to prevent. REQUIRES_STREAM therefore
//   points at the coarser Stream{code:'SCIENCE'}; the PCM-specific requirement
//   is still enforced precisely by the two REQUIRES_SUBJECT edges below
//   (Mathematics, Physics), which the query checks independently.
//
// StreamPolicy (CBSE-G9-2026-2027) is seeded PROVISIONALLY — see the note on
// that node below. Its date is NOT independently sourced; do not treat it as
// verified.
// =============================================================================

MERGE (o:Occupation {occupation_id:'OCC-ARCHITECT'})
  SET o.title = 'Architect';

MERGE (st:Stream  {code:'SCIENCE'})     SET st.name = 'Science';
MERGE (m:Subject  {code:'MATHEMATICS'}) SET m.name = 'Mathematics';
MERGE (p:Subject  {code:'PHYSICS'})     SET p.name = 'Physics';
MERGE (ex:Exam    {exam_id:'EXAM-NATA'})    SET ex.name = 'NATA';
MERGE (dg:Degree  {degree_id:'DEG-B-ARCH'}) SET dg.name = 'B.Arch';

MERGE (o)-[rs:REQUIRES_STREAM]->(st)
  SET rs.source_url = 'https://coa.gov.in/app/myauth/notification/COA_Minimum_Standards_of_Architectural_Education_Regulations_2020.pdf',
      rs.verified_at = date('2026-08-26');
MERGE (o)-[r1:REQUIRES_SUBJECT]->(m)
  SET r1.essentiality = 'essential',
      r1.source_url = 'https://coa.gov.in/app/myauth/notification/COA_Minimum_Standards_of_Architectural_Education_Regulations_2020.pdf',
      r1.verified_at = date('2026-08-26');
MERGE (o)-[r2:REQUIRES_SUBJECT]->(p)
  SET r2.essentiality = 'essential',
      r2.source_url = 'https://coa.gov.in/app/myauth/notification/COA_Minimum_Standards_of_Architectural_Education_Regulations_2020.pdf',
      r2.verified_at = date('2026-08-26');
MERGE (o)-[re:REQUIRES_EXAM]->(ex)
  SET re.source_url = 'https://www.nata.in/assets/pdf/Final-NATA-BROCHURE-2026.pdf',
      re.verified_at = date('2026-08-26');
MERGE (o)-[rd:LEADS_TO_DEGREE]->(dg)
  SET rd.source_url = 'https://coa.gov.in/app/myauth/notification/COA_Minimum_Standards_of_Architectural_Education_Regulations_2020.pdf',
      rd.verified_at = date('2026-08-26');

// PROVISIONAL — not independently sourced. No official, board-wide CBSE
// "stream change deadline" for Grade 9 could be found: CBSE's real October
// deadline (confirmed via multiple CBSE-circular-reporting sources, e.g.
// https://news.careers360.com/cbse-urges-schools-complete-100-registration-of-class-9-11-students-october-16-late-fee-for-delays)
// is a Class 9/11 BOARD-EXAM REGISTRATION data-submission deadline (16 Oct
// regular, late fee window to 31 Oct) — a different administrative fact than
// "you must finalise your stream/subject choice by this date". Whether a
// stream-change deadline exists at all is more likely a per-school policy than
// a CBSE-wide one. Seeded so the query mechanics work end-to-end; deliberately
// NOT given a source_url/verified_at, so it reads as unsourced rather than
// falsely authoritative. Replace with a real policy (or remove) before this is
// shown to any real counsellor or student.
//
// academic_year is a bare 4-digit year ('2026'), NOT a 'YYYY-YYYY' range —
// this must match CaiCoreService::evaluate()'s $academicYear param verbatim,
// which is the ERP's real session `syear` convention (see
// ErpSubjectEnrolmentAdapter::resolveSyear() and the frontend's
// normalizeAcademicYear(), both of which reduce any input down to a bare
// 4-digit year, confirming that is this system's actual canonical format —
// a 'YYYY-YYYY' policy_id/academic_year here previously meant this
// OPTIONAL MATCH could never match a real call and Break Point Analysis's
// deadline_date/days_remaining silently stayed null for every student, even
// one fully aligned to this occupation.
MERGE (pol:StreamPolicy {policy_id:'CBSE-G9-2026'})
  SET pol.board = 'CBSE', pol.grade = 9, pol.academic_year = '2026',
      pol.change_deadline = date('2026-10-31'),
      pol.provisional = true;
