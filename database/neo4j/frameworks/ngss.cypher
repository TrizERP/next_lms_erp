// =============================================================================
// FRAMEWORKS — NGSS (Next Generation Science Standards), real slice (2026-10-10)
// =============================================================================
//
// WHY ONLY NGSS, AND WHY ONLY THIS MUCH OF IT. `pal_contents` has
// casel_domain/ngss_practice/ncdg_goal/music_domain/sports_domain/finance_domain
// columns (App\Services\PAL\Framework\FrameworkCatalogService::normalizeValue())
// and every one of them is null across the table's one live row — no framework
// has any populated backing data. Rather than fabricate thin, equally-fake data
// across all six, this seeds ONE real, citable framework (NGSS) against REAL
// Concept nodes already in the live K12 graph. The other five stay honestly
// unseeded.
//
// SOURCE FOR THE 8 PRACTICE NAMES (verbatim, official):
//   NGSS Appendix F — "Science and Engineering Practices in the NGSS"
//   https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf
//   (same 8 practices are restated at
//   https://www.nextgenscience.org/three-dimensions/dimension-1-scientific-and-engineering-practices)
// These are the SAME 8 practices config/pal_v4.php's `ngss` group already
// encodes as informal slugs (asking_questions, developing_models,
// investigation, data_analysis, math_computation, explanation, argumentation,
// communication) — this file gives them their real official names and codes
// and, for the first time, real edges to real content.
//
// CONCEPT NODES MATCHED, NOT MERGED. Every `(c:Concept {uid: '...'})` below is
// an EXISTING live node — confirmed by direct Cypher query against the K12
// graph (dev.triz.co.in:7688) on 2026-10-10:
//   MATCH (c:Concept) WHERE c.uid IS NOT NULL AND c.subject_id = '3975'
//   (subject_id 3975 = Science, confirmed via `sub_std_map`, standard_id 43 —
//   CBSE Class 10, chapters 1013-1024 in `chapter_master`)
// 1,161 of the graph's 1,372 Concept nodes carry a uid at all (the rest are
// legacy-keyed — see GraphSchema's own measured counts); this seed only
// touches the 16 real uid-keyed nodes named below, each verified to exist
// with the exact name quoted in its comment before this file was written.
// If a future reconciliation ever renumbers these ids, this file's MATCHes
// will simply find nothing for the moved ones (never invent a replacement) —
// same "say so, don't force it" rule the rest of today's work follows.
//
// WHY THESE 16 CONCEPTS FOR THESE 8 PRACTICES — one real pedagogical reason
// per edge, not a plausible-sounding guess:
//   SEP1 Asking Questions and Defining Problems
//     - Reflex Action: the textbook's own opening question — why does a hand
//       recoil from heat before you consciously decide to move it?
//     - Overloading: the problem a domestic circuit's fuse exists to define.
//   SEP2 Developing and Using Models
//     - Spherical Mirrors: taught entirely through the ray-diagram model.
//     - Reflex Arc: the standard receptor->CNS->effector pathway diagram.
//   SEP3 Planning and Carrying Out Investigations
//     - Ohm's Law: the canonical "vary V, measure I, hold R" lab investigation.
//     - Acid-Base Indicators: the titration/indicator-colour-change experiment.
//   SEP4 Analyzing and Interpreting Data
//     - Resistors in Series: reading combined-resistance measurements against
//       the series formula.
//     - Energy Flow in Ecosystems: interpreting trophic-level energy-loss data
//       (the 10% law).
//   SEP5 Using Mathematics and Computational Thinking
//     - Mirror Formula: 1/v + 1/u = 1/f, solved numerically.
//     - Electric Power: P = VI, solved numerically.
//   SEP6 Constructing Explanations and Designing Solutions
//     - Prevention of Corrosion: explaining rusting and designing a barrier
//       (galvanising/painting) against it.
//     - Electric Fuse: an engineered solution to a defined overcurrent problem.
//   SEP7 Engaging in Argument from Evidence
//     - Reactivity Series of Metals: ranking metals by displacement-reaction
//       evidence.
//     - Dominant Traits: Mendel's evidence-based argument for inheritance
//       patterns.
//   SEP8 Obtaining, Evaluating, and Communicating Information
//     - Ozone Layer Depletion: the textbook's own "read and discuss current
//       reports" topic.
//     - Human Brain: researching and communicating brain-region function.
//
// Idempotent (MERGE throughout) — safe to re-run.
// =============================================================================

MERGE (f:Framework {framework_code: 'NGSS'})
  SET f.name = 'Next Generation Science Standards',
      f.full_name = 'Next Generation Science Standards — Science and Engineering Practices (Appendix F)',
      f.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      f.practice_count = 8,
      f.seeded_at = date('2026-10-10');

// ---- SEP1 Asking Questions and Defining Problems --------------------------
WITH f
MATCH (c1:Concept {uid: 'Concept:1:0:174'})
MERGE (f)-[m1:MAPS_TO]->(c1)
  SET m1.practice_code = 'SEP1', m1.practice_name = 'Asking Questions and Defining Problems',
      m1.rationale = "Reflex Action is the textbook's own opening question: why does the hand recoil before a conscious decision is made?",
      m1.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m1.verified_at = date('2026-10-10');

WITH f
MATCH (c2:Concept {uid: 'Concept:1:0:289'})
MERGE (f)-[m2:MAPS_TO]->(c2)
  SET m2.practice_code = 'SEP1', m2.practice_name = 'Asking Questions and Defining Problems',
      m2.rationale = 'Overloading is the engineering problem a domestic circuit fuse is designed to answer.',
      m2.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m2.verified_at = date('2026-10-10');

// ---- SEP2 Developing and Using Models --------------------------------------
WITH f
MATCH (c3:Concept {uid: 'Concept:1:0:224'})
MERGE (f)-[m3:MAPS_TO]->(c3)
  SET m3.practice_code = 'SEP2', m3.practice_name = 'Developing and Using Models',
      m3.rationale = 'Spherical Mirrors is taught through the ray-diagram model of image formation.',
      m3.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m3.verified_at = date('2026-10-10');

WITH f
MATCH (c4:Concept {uid: 'Concept:1:0:175'})
MERGE (f)-[m4:MAPS_TO]->(c4)
  SET m4.practice_code = 'SEP2', m4.practice_name = 'Developing and Using Models',
      m4.rationale = 'Reflex Arc is the standard receptor -> CNS -> effector pathway diagram students build.',
      m4.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m4.verified_at = date('2026-10-10');

// ---- SEP3 Planning and Carrying Out Investigations -------------------------
WITH f
MATCH (c5:Concept {uid: 'Concept:1:0:264'})
MERGE (f)-[m5:MAPS_TO]->(c5)
  SET m5.practice_code = 'SEP3', m5.practice_name = 'Planning and Carrying Out Investigations',
      m5.rationale = "Ohm's Law is taught via the canonical lab investigation: vary voltage, measure current, hold resistance.",
      m5.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m5.verified_at = date('2026-10-10');

WITH f
MATCH (c6:Concept {uid: 'Concept:1:0:47'})
MERGE (f)-[m6:MAPS_TO]->(c6)
  SET m6.practice_code = 'SEP3', m6.practice_name = 'Planning and Carrying Out Investigations',
      m6.rationale = 'Acid-Base Indicators is taught through the titration / colour-change experiment.',
      m6.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m6.verified_at = date('2026-10-10');

// ---- SEP4 Analyzing and Interpreting Data ----------------------------------
WITH f
MATCH (c7:Concept {uid: 'Concept:1:0:268'})
MERGE (f)-[m7:MAPS_TO]->(c7)
  SET m7.practice_code = 'SEP4', m7.practice_name = 'Analyzing and Interpreting Data',
      m7.rationale = 'Resistors in Series requires reading measured resistance data against the series-sum formula.',
      m7.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m7.verified_at = date('2026-10-10');

WITH f
MATCH (c8:Concept {uid: 'Concept:1:0:298'})
MERGE (f)-[m8:MAPS_TO]->(c8)
  SET m8.practice_code = 'SEP4', m8.practice_name = 'Analyzing and Interpreting Data',
      m8.rationale = 'Energy Flow in Ecosystems is interpreted through trophic-level energy-loss data (the 10% law).',
      m8.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m8.verified_at = date('2026-10-10');

// ---- SEP5 Using Mathematics and Computational Thinking ---------------------
WITH f
MATCH (c9:Concept {uid: 'Concept:1:0:226'})
MERGE (f)-[m9:MAPS_TO]->(c9)
  SET m9.practice_code = 'SEP5', m9.practice_name = 'Using Mathematics and Computational Thinking',
      m9.rationale = 'Mirror Formula (1/v + 1/u = 1/f) is solved numerically for unknown distances.',
      m9.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m9.verified_at = date('2026-10-10');

WITH f
MATCH (c10:Concept {uid: 'Concept:1:0:271'})
MERGE (f)-[m10:MAPS_TO]->(c10)
  SET m10.practice_code = 'SEP5', m10.practice_name = 'Using Mathematics and Computational Thinking',
      m10.rationale = 'Electric Power (P = VI) is a direct numerical computation.',
      m10.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m10.verified_at = date('2026-10-10');

// ---- SEP6 Constructing Explanations and Designing Solutions ----------------
WITH f
MATCH (c11:Concept {uid: 'Concept:1:0:129'})
MERGE (f)-[m11:MAPS_TO]->(c11)
  SET m11.practice_code = 'SEP6', m11.practice_name = 'Constructing Explanations and Designing Solutions',
      m11.rationale = 'Prevention of Corrosion requires explaining rusting and designing a barrier solution (galvanising/painting).',
      m11.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m11.verified_at = date('2026-10-10');

WITH f
MATCH (c12:Concept {uid: 'Concept:1:0:274'})
MERGE (f)-[m12:MAPS_TO]->(c12)
  SET m12.practice_code = 'SEP6', m12.practice_name = 'Constructing Explanations and Designing Solutions',
      m12.rationale = 'Electric Fuse is an engineered solution to a defined overcurrent problem.',
      m12.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m12.verified_at = date('2026-10-10');

// ---- SEP7 Engaging in Argument from Evidence -------------------------------
WITH f
MATCH (c13:Concept {uid: 'Concept:1:0:119'})
MERGE (f)-[m13:MAPS_TO]->(c13)
  SET m13.practice_code = 'SEP7', m13.practice_name = 'Engaging in Argument from Evidence',
      m13.rationale = 'Reactivity Series of Metals ranks metals from displacement-reaction evidence.',
      m13.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m13.verified_at = date('2026-10-10');

WITH f
MATCH (c14:Concept {uid: 'Concept:1:0:210'})
MERGE (f)-[m14:MAPS_TO]->(c14)
  SET m14.practice_code = 'SEP7', m14.practice_name = 'Engaging in Argument from Evidence',
      m14.rationale = "Dominant Traits rests on Mendel's evidence-based argument for inheritance patterns.",
      m14.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m14.verified_at = date('2026-10-10');

// ---- SEP8 Obtaining, Evaluating, and Communicating Information -------------
WITH f
MATCH (c15:Concept {uid: 'Concept:1:0:303'})
MERGE (f)-[m15:MAPS_TO]->(c15)
  SET m15.practice_code = 'SEP8', m15.practice_name = 'Obtaining, Evaluating, and Communicating Information',
      m15.rationale = "Ozone Layer Depletion is the textbook's own 'read and discuss current reports' topic.",
      m15.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m15.verified_at = date('2026-10-10');

WITH f
MATCH (c16:Concept {uid: 'Concept:1:0:176'})
MERGE (f)-[m16:MAPS_TO]->(c16)
  SET m16.practice_code = 'SEP8', m16.practice_name = 'Obtaining, Evaluating, and Communicating Information',
      m16.rationale = 'Human Brain is researched and communicated as brain-region function.',
      m16.source_url = 'https://www.nextgenscience.org/sites/default/files/Appendix%20F%20%20Science%20and%20Engineering%20Practices%20in%20the%20NGSS%20-%20FINAL%20060513.pdf',
      m16.verified_at = date('2026-10-10');
