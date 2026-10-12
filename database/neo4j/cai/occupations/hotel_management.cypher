// =============================================================================
// CAI-CORE — Hotel Management (CAI-CORE expansion, 2026-10-10)
// =============================================================================
//
// onetsoc_code 11-9081.00 "Lodging Managers" — confirmed live, 2026-10-10
// (SOC 2018 11-9081, per worldoftaxonomy.com's O*NET-derived listing) — the
// nearest real O*NET occupation to an NCHMCT-trained hotel management
// graduate's typical career destination. Documented as the same kind of
// deliberate approximation chartered_accountant.cypher's mapping is: O*NET's
// taxonomy is US hospitality-industry-shaped and has no direct "hotel
// management graduate" occupation.
//
// SOURCED FACTS (verified via live search 2026-10-10):
//   - NCHMCT JEE (National Council for Hotel Management and Catering
//     Technology) requires Class 12 pass (any stream) PLUS English passed as
//     a subject of study (core/elective/functional) — a mandatory, named
//     requirement, unlike the stream itself —
//     https://hospitality.careers360.com/articles/nchmct-jee-eligibility-criteria
// =============================================================================

MERGE (o:Occupation {occupation_id:'OCC-HOTEL-MANAGEMENT'})
  SET o.title = 'Hotel Management';

MERGE (en:Subject {code:'ENGLISH'}) SET en.name = 'English';
MERGE (ex:Exam    {exam_id:'EXAM-NCHMCT-JEE'}) SET ex.name = 'NCHMCT JEE';
MERGE (dg:Degree  {degree_id:'DEG-BSC-HHA'})
  SET dg.name = 'B.Sc Hospitality and Hotel Administration';

// No REQUIRES_STREAM edge — any stream is eligible, per source above. English
// is the one named, mandatory subject requirement (not stream-conditional),
// so it is modelled as essential on its own.
MERGE (o)-[r1:REQUIRES_SUBJECT]->(en)
  SET r1.essentiality = 'essential',
      r1.source_url = 'https://hospitality.careers360.com/articles/nchmct-jee-eligibility-criteria',
      r1.verified_at = date('2026-10-10');
MERGE (o)-[re:REQUIRES_EXAM]->(ex)
  SET re.source_url = 'https://hospitality.careers360.com/articles/nchmct-jee-eligibility-criteria',
      re.verified_at = date('2026-10-10');
MERGE (o)-[rd:LEADS_TO_DEGREE]->(dg)
  SET rd.source_url = 'https://hospitality.careers360.com/articles/nchmct-jee-eligibility-criteria',
      rd.verified_at = date('2026-10-10');
