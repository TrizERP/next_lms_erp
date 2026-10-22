// =============================================================================
// CAI-CORE — Chartered Accountant (CAI-CORE expansion, 2026-10-10)
// =============================================================================
//
// O*NET HAS NO "CHARTERED ACCOUNTANT" OCCUPATION — this is an Indian/
// Commonwealth professional qualification (ICAI), not a role the US-built
// O*NET taxonomy enumerates. 'OCC-CHARTERED-ACCOUNTANT' is this wedge's own
// internal occupation_id (matching the existing documented convention of a
// small curated vocabulary, NOT the O*NET catalog — same convention
// architect.cypher's OCC-ARCHITECT already uses for its internal id).
//
// CaiCoreService::OCCUPATION_MAP maps the nearest real O*NET occupation,
// "Accountants and Auditors" (13-2011.00, confirmed live 2026-10-10 at
// mynextmove.org/onetonline.org), to THIS node — a deliberate, documented
// approximation, not an exact match: a CBSE student's `student_aspirations.
// occupation_id` comes from an O*NET dropdown that has no "Chartered
// Accountant" entry at all, so 13-2011.00 is the closest real option a
// student aspiring to be a CA could actually have selected. Flagged here so
// nobody mistakes this for a precise taxonomy match later.
//
// SOURCED FACTS (verified via live search 2026-10-10):
//   - CA Foundation (ICAI) has NO stream restriction and no minimum
//     percentage — any student who has passed or is appearing for Class 12,
//     in any stream, is eligible —
//     https://finance.careers360.com/articles/ca-foundation-eligibility-criteria
//
// DELIBERATELY NO REQUIRES_STREAM / REQUIRES_SUBJECT edge, for the same
// reason as lawyer.cypher: this is a genuinely stream-neutral qualification,
// and an absent edge is how this graph's query semantics represent "no
// constraint" rather than "constraint unknown".
// =============================================================================

MERGE (o:Occupation {occupation_id:'OCC-CHARTERED-ACCOUNTANT'})
  SET o.title = 'Chartered Accountant';

MERGE (ex:Exam   {exam_id:'EXAM-CA-FOUNDATION'}) SET ex.name = 'CA Foundation (ICAI)';
MERGE (dg:Degree {degree_id:'DEG-CA-ICAI'})
  SET dg.name = 'Chartered Accountant (ICAI) — professional qualification, not a university degree';

MERGE (o)-[re:REQUIRES_EXAM]->(ex)
  SET re.source_url = 'https://finance.careers360.com/articles/ca-foundation-eligibility-criteria',
      re.verified_at = date('2026-10-10');
MERGE (o)-[rd:LEADS_TO_DEGREE]->(dg)
  SET rd.source_url = 'https://finance.careers360.com/articles/ca-foundation-eligibility-criteria',
      rd.verified_at = date('2026-10-10');
