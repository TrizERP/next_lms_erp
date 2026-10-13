// =============================================================================
// FRAMEWORKS — CASEL (Collaborative for Academic, Social, and Emotional
// Learning), real slice (2026-10-12)
// =============================================================================
//
// WHY NOT `pal_contents.casel_domain`. Verified live again today, same result
// as the NGSS seed found: `pal_contents` has one row, and its `casel_domain`
// column is NULL on it. Zero real rows to anchor against there.
//
// THE REAL ANCHOR THIS FILE USES INSTEAD: `:CoScholasticArea`, the live-synced
// Neo4j projection of the `result_co_scholastic` table (report-card
// co-scholastic grading areas — see config/neo4j.php's `projections.entities.
// result_co_scholastic` spec and App\Services\Graph\GraphSchema's key entry
// `'CoScholasticArea' => ['key' => 'coscholasticareaId', ...]`). This table is
// NOT academic-concept data, but it IS real, live, behavioural/character
// report-card data schools are actually grading students on — titles like
// "Discipline", "Value Education", "Regularity & Punctuality", "To be
// Friendly" are exactly the kind of social-emotional-behaviour judgement CASEL
// describes, just authored by each school rather than tagged with CASEL's own
// vocabulary. Confirmed live via:
//   MATCH (ca:CoScholasticArea) WHERE ca.title IN
//     ['Discipline','Regularity & Punctuality','Attitude towards schoolmates',
//      'To be Polite and Respectful','To be Friendly','Work Experience',
//      'Value Education','SEWA']
//   RETURN ca.coscholasticareaId, ca.title, ca.parent_id, ca.standard_id
// — every row below is one of the live matches that query returned.
//
// SOURCE FOR THE 5 COMPETENCY NAMES AND DEFINITIONS (verbatim, official):
//   CASEL. "What Is the CASEL Framework?" casel.org/fundamentals-of-sel/what-
//   is-the-casel-framework/ (the "CASEL wheel") — verified live 2026-10-12.
//   Self-awareness, self-management, social awareness, relationship skills,
//   responsible decision-making.
//
// HONEST GAP WITHIN CASEL ITSELF: SELF-AWARENESS IS NOT SEEDED. CASEL defines
// self-awareness as recognising one's OWN emotions, thoughts and values —
// an introspective judgement. Every `result_co_scholastic` title found is an
// externally-observed BEHAVIOUR ("Discipline", "To be Friendly", "Attitude
// towards schoolmates") or a subject-based grading area, not a self-report of
// a student's own emotional self-knowledge. Stretching one of those titles to
// stand for self-awareness would be exactly the "plausible-sounding guess"
// this work is instructed not to make, so this file maps 4 of CASEL's 5
// domains and leaves self-awareness out, on purpose.
//
// WHY THESE 8 NODES FOR THESE 4 DOMAINS — one real reason per edge:
//   SEL2 Self-Management (regulating emotions/behaviour, self-discipline,
//        organisational skills — CASEL's own sub-skill list)
//     - "Discipline" (coscholasticareaId 70): literally self-discipline.
//     - "Regularity & Punctuality" (id 1898): organisational/self-regulation
//       sub-skill, CASEL's own wording.
//   SEL3 Social Awareness (understanding others' perspectives, empathy)
//     - "Attitude towards schoolmates" (id 1925): a school's own judgement of
//       how a student regards and empathises with peers.
//     - "To be Polite and Respectful" (id 711): respect for others is
//       CASEL's own worked example of social awareness in practice.
//   SEL4 Relationship Skills (communication, cooperation, teamwork)
//     - "To be Friendly" (id 708): direct behavioural proxy for relationship-
//       building.
//     - "Work Experience" (id 25): practical, group-based work activity —
//       cooperation/teamwork is the graded skill.
//   SEL5 Responsible Decision-Making (ethical, constructive choices;
//        prosocial behaviour — CASEL's own wording)
//     - "Value Education" (id 423): a school subject whose explicit purpose
//       is teaching ethical/value-based choice-making.
//     - "SEWA" (id 497, Hindi/Sanskrit for "service"): community-service
//       grading area — CASEL's definition names "prosocial behaviour"
//       explicitly as part of this competency.
//
// NODES MATCHED, NOT MERGED. Every `(ca:CoScholasticArea {coscholasticareaId:
// N})` below is an EXISTING live node, confirmed by direct Cypher query
// against the K12 graph (dev.triz.co.in:7688) on 2026-10-12, with its exact
// title quoted in this file's comments before the file was written. Same
// "say so, don't force it" rule as every other framework seed here.
//
// PROPERTY NAMES. Reuses `practice_code`/`practice_name` on the MAPS_TO edge
// (holding e.g. "SEL2"/"Self-Management") for the same reason gardner.cypher
// does — one framework-agnostic read in FrameworkCatalogService::
// graphMappings(). See that file's 2026-10-12 change note.
//
// Idempotent (MERGE throughout) — safe to re-run.
// =============================================================================

MERGE (f:Framework {framework_code: 'CASEL'})
  SET f.name = 'CASEL',
      f.full_name = 'Collaborative for Academic, Social, and Emotional Learning — SEL Framework',
      f.source_url = 'https://casel.org/fundamentals-of-sel/what-is-the-casel-framework/',
      f.domain_count = 5,
      f.seeded_at = date('2026-10-12');

// ---- SEL2 Self-Management ---------------------------------------------------
WITH f
MATCH (c1:CoScholasticArea {coscholasticareaId: 70})
MERGE (f)-[m1:MAPS_TO]->(c1)
  SET m1.practice_code = 'SEL2', m1.practice_name = 'Self-Management',
      m1.rationale = "The report-card area \"Discipline\" is literally self-discipline, one of CASEL's own self-management sub-skills.",
      m1.source_url = 'https://casel.org/fundamentals-of-sel/what-is-the-casel-framework/',
      m1.verified_at = date('2026-10-12');

WITH f
MATCH (c2:CoScholasticArea {coscholasticareaId: 1898})
MERGE (f)-[m2:MAPS_TO]->(c2)
  SET m2.practice_code = 'SEL2', m2.practice_name = 'Self-Management',
      m2.rationale = '"Regularity & Punctuality" is an organisational/self-regulation sub-skill, in CASEL\'s own wording for this competency.',
      m2.source_url = 'https://casel.org/fundamentals-of-sel/what-is-the-casel-framework/',
      m2.verified_at = date('2026-10-12');

// ---- SEL3 Social Awareness ---------------------------------------------------
WITH f
MATCH (c3:CoScholasticArea {coscholasticareaId: 1925})
MERGE (f)-[m3:MAPS_TO]->(c3)
  SET m3.practice_code = 'SEL3', m3.practice_name = 'Social Awareness',
      m3.rationale = '"Attitude towards schoolmates" is a school\'s own judgement of how a student regards and empathises with peers.',
      m3.source_url = 'https://casel.org/fundamentals-of-sel/what-is-the-casel-framework/',
      m3.verified_at = date('2026-10-12');

WITH f
MATCH (c4:CoScholasticArea {coscholasticareaId: 711})
MERGE (f)-[m4:MAPS_TO]->(c4)
  SET m4.practice_code = 'SEL3', m4.practice_name = 'Social Awareness',
      m4.rationale = '"To be Polite and Respectful": respecting others is CASEL\'s own worked example of social awareness in practice.',
      m4.source_url = 'https://casel.org/fundamentals-of-sel/what-is-the-casel-framework/',
      m4.verified_at = date('2026-10-12');

// ---- SEL4 Relationship Skills ------------------------------------------------
WITH f
MATCH (c5:CoScholasticArea {coscholasticareaId: 708})
MERGE (f)-[m5:MAPS_TO]->(c5)
  SET m5.practice_code = 'SEL4', m5.practice_name = 'Relationship Skills',
      m5.rationale = '"To be Friendly" is a direct behavioural proxy for relationship-building.',
      m5.source_url = 'https://casel.org/fundamentals-of-sel/what-is-the-casel-framework/',
      m5.verified_at = date('2026-10-12');

WITH f
MATCH (c6:CoScholasticArea {coscholasticareaId: 25})
MERGE (f)-[m6:MAPS_TO]->(c6)
  SET m6.practice_code = 'SEL4', m6.practice_name = 'Relationship Skills',
      m6.rationale = '"Work Experience" is a practical, group-based work activity where cooperation/teamwork is the graded skill.',
      m6.source_url = 'https://casel.org/fundamentals-of-sel/what-is-the-casel-framework/',
      m6.verified_at = date('2026-10-12');

// ---- SEL5 Responsible Decision-Making ----------------------------------------
WITH f
MATCH (c7:CoScholasticArea {coscholasticareaId: 423})
MERGE (f)-[m7:MAPS_TO]->(c7)
  SET m7.practice_code = 'SEL5', m7.practice_name = 'Responsible Decision-Making',
      m7.rationale = '"Value Education" is a school subject whose explicit purpose is teaching ethical/value-based choice-making.',
      m7.source_url = 'https://casel.org/fundamentals-of-sel/what-is-the-casel-framework/',
      m7.verified_at = date('2026-10-12');

WITH f
MATCH (c8:CoScholasticArea {coscholasticareaId: 497})
MERGE (f)-[m8:MAPS_TO]->(c8)
  SET m8.practice_code = 'SEL5', m8.practice_name = 'Responsible Decision-Making',
      m8.rationale = '"SEWA" (service) grading area: CASEL\'s own definition of this competency explicitly names prosocial behaviour.',
      m8.source_url = 'https://casel.org/fundamentals-of-sel/what-is-the-casel-framework/',
      m8.verified_at = date('2026-10-12');
