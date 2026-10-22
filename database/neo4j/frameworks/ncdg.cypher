// =============================================================================
// FRAMEWORKS — NCDG (National Career Development Guidelines), real slice
// (2026-10-12)
// =============================================================================
//
// WHY THIS FRAMEWORK DOES NOT TARGET `:Concept`. NCDG is a career-development
// competency framework (NCDA — National Career Development Association), not
// an academic-subject standard. Forcing its goals onto curriculum `:Concept`
// nodes the way NGSS/Gardner do would be the fabricated-correspondence
// mistake this work is instructed to avoid — no NCDG document claims, say,
// "Ohm's Law teaches career decision-making." The real, honest anchor for a
// career-development framework is the CAI-CORE career graph seeded earlier
// today (App\CareerIntelligence\CaiCoreService /
// App\Console\Commands\CareerIntelligence\SeedCareerGraphCommand), whose
// `:Occupation`/`:Exam`/`:Degree` nodes are real, O*NET-sourced career data —
// exactly NCDG's own subject matter. This is a SEPARATE Neo4j target label
// from every other framework file in this directory, by design.
//
// SOURCE FOR THE GOAL CODES AND TEXT (verbatim, official, pre-2024 version —
// matching the PS1/PS2/CM1-CM4 style codes already encoded as config('pal_v4.
// ncdg') in this codebase; NCDA renamed the domains in a 2024 update, which
// this file does not use, to stay consistent with the existing config):
//   NCDA. "National Career Development Guidelines (NCDG) Framework."
//   https://ncda.org/aws/NCDA/asset_manager/get_file/3384 — confirmed live
//   2026-10-12 (PDF metadata title: "National Career Development Guidelines
//   (NCDG) Framework").
//   CM1 Create and manage a career plan that meets your career goals.
//   CM3 Use accurate, current and unbiased career information during career
//       planning and management.
//   CM4 Master academic, occupational and general employability skills in
//       order to obtain, create, maintain and/or advance your employment.
//
// WHY ONLY CM1/CM3/CM4, NOT ALL 8 OFFICIAL GOALS. The CAI-CORE graph only has
// real anchors for the CAREER MANAGEMENT domain (Occupation/Exam/Degree
// nodes). The Personal Social Development goals (PS1 self-concept, PS2
// interpersonal skills) would need real `HasInterest`/`HasWorkValue` data —
// GraphSchema.php whitelists those edge types, but a prior agent confirmed
// today they carry no real data yet (see this codebase's RIASEC decision
// note in FrameworkCatalogService's docblock). CM2 ("use a decision-making
// process") has no real captured decision-process node either. Rather than
// seed those four against a fabricated anchor, they are left out — the same
// "say so, don't force it" rule as CASEL's missing self-awareness domain.
//
// WHY THESE SPECIFIC OCCUPATIONS/EXAMS/DEGREES. OCC-SOFTWARE-DEVELOPER and
// OCC-ARCHITECT are not an arbitrary pick: they are the only two of the 11
// seeded occupations that correspond to REAL rows in `student_aspirations`
// (confirmed live 2026-10-12 — `student_aspirations` has exactly 4 rows
// total: ids 11 & 15 = student 97926, occupation_id '15-1252.00' = Software
// Developers; id 17 = student 284976, occupation_id '17-1011.00' =
// Architects). Mapping CM1 ("a career plan that meets your career goals") to
// these two occupations means this edge points at two actual students' own
// stated career goals, not a hypothetical example.
//
// NODES MATCHED, NOT MERGED. Every `(o:Occupation {...})`, `(ex:Exam {...})`,
// `(dg:Degree {...})` below is an EXISTING live node from the CAI-CORE seed
// (database/neo4j/cai/occupations/*.cypher), confirmed by direct query
// against the K12 graph (dev.triz.co.in:7688) on 2026-10-12.
//
// PROPERTY NAMES. Reuses `practice_code`/`practice_name` on the MAPS_TO edge
// (holding e.g. "CM1"/"Create and manage a career plan...") for the same
// reason gardner.cypher/casel.cypher do — one framework-agnostic read in
// FrameworkCatalogService::graphMappings(). See that file's 2026-10-12
// change note.
//
// Idempotent (MERGE throughout) — safe to re-run. Framework node only; this
// file MATCHes real Occupation/Exam/Degree nodes the CAI-CORE seed already
// created, it never MERGEs them (no risk of recreating them under a
// different key convention).
// =============================================================================

MERGE (f:Framework {framework_code: 'NCDG'})
  SET f.name = 'National Career Development Guidelines',
      f.full_name = 'National Career Development Guidelines (NCDA)',
      f.source_url = 'https://ncda.org/aws/NCDA/asset_manager/get_file/3384',
      f.goal_count = 8,
      f.seeded_at = date('2026-10-12');

// ---- CM1 Create and manage a career plan that meets your career goals -----
WITH f
MATCH (o1:Occupation {occupation_id: 'OCC-SOFTWARE-DEVELOPER'})
MERGE (f)-[m1:MAPS_TO]->(o1)
  SET m1.practice_code = 'CM1', m1.practice_name = 'Create and manage a career plan that meets your career goals',
      m1.rationale = "Software Developer is the real occupation_id in student_aspirations rows 11 and 15 (student 97926) — an actual student's own stated career plan, not a hypothetical example.",
      m1.source_url = 'https://ncda.org/aws/NCDA/asset_manager/get_file/3384',
      m1.verified_at = date('2026-10-12');

WITH f
MATCH (o2:Occupation {occupation_id: 'OCC-ARCHITECT'})
MERGE (f)-[m2:MAPS_TO]->(o2)
  SET m2.practice_code = 'CM1', m2.practice_name = 'Create and manage a career plan that meets your career goals',
      m2.rationale = 'Architect is the real occupation_id in student_aspirations row 17 (student 284976) — a second actual stated career goal.',
      m2.source_url = 'https://ncda.org/aws/NCDA/asset_manager/get_file/3384',
      m2.verified_at = date('2026-10-12');

// ---- CM3 Use accurate, current and unbiased career information ------------
WITH f
MATCH (ex1:Exam {exam_id: 'EXAM-JEE-MAIN'})
MERGE (f)-[m3:MAPS_TO]->(ex1)
  SET m3.practice_code = 'CM3', m3.practice_name = 'Use accurate, current and unbiased career information during career planning and management',
      m3.rationale = "JEE Main's entry requirement on this graph carries a real source_url and verified_at date (careers360.com, 2026-10-10) — exactly the kind of accurate, current, sourced career information this goal names.",
      m3.source_url = 'https://ncda.org/aws/NCDA/asset_manager/get_file/3384',
      m3.verified_at = date('2026-10-12');

WITH f
MATCH (ex2:Exam {exam_id: 'EXAM-NATA'})
MERGE (f)-[m4:MAPS_TO]->(ex2)
  SET m4.practice_code = 'CM3', m4.practice_name = 'Use accurate, current and unbiased career information during career planning and management',
      m4.rationale = "NATA is the Architect occupation's real qualifying-exam data on this graph, sourced the same verifiable way.",
      m4.source_url = 'https://ncda.org/aws/NCDA/asset_manager/get_file/3384',
      m4.verified_at = date('2026-10-12');

// ---- CM4 Master academic, occupational and employability skills ------------
WITH f
MATCH (dg1:Degree {degree_id: 'DEG-BTECH-CS'})
MERGE (f)-[m5:MAPS_TO]->(dg1)
  SET m5.practice_code = 'CM4', m5.practice_name = 'Master academic, occupational and general employability skills in order to obtain, create, maintain and/or advance your employment',
      m5.rationale = "B.Tech (Computer Science) is literally the academic credential this goal describes mastering to obtain the Software Developer occupation.",
      m5.source_url = 'https://ncda.org/aws/NCDA/asset_manager/get_file/3384',
      m5.verified_at = date('2026-10-12');

WITH f
MATCH (dg2:Degree {degree_id: 'DEG-B-ARCH'})
MERGE (f)-[m6:MAPS_TO]->(dg2)
  SET m6.practice_code = 'CM4', m6.practice_name = 'Master academic, occupational and general employability skills in order to obtain, create, maintain and/or advance your employment',
      m6.rationale = 'B.Arch is the same academic-mastery credential for the Architect occupation.',
      m6.source_url = 'https://ncda.org/aws/NCDA/asset_manager/get_file/3384',
      m6.verified_at = date('2026-10-12');
