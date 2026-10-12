// =============================================================================
// FRAMEWORKS — Gardner's Theory of Multiple Intelligences, real slice (2026-10-12)
// =============================================================================
//
// SOURCE FOR THE 8 INTELLIGENCE NAMES (verbatim, official):
//   Gardner, H. (1983). "Frames of Mind: The Theory of Multiple Intelligences."
//   New York: Basic Books. (the original 7: linguistic, logical-mathematical,
//   spatial, bodily-kinesthetic, musical, interpersonal, intrapersonal)
//   Gardner, H. (1999). "Intelligence Reframed: Multiple Intelligences for the
//   21st Century." New York: Basic Books. (added the 8th, naturalistic; also
//   discussed existential as a candidate 9th — NOT included here, since
//   Gardner himself never confirmed it as a full intelligence, only a
//   "half" one worth considering — seeding it would misstate his own position)
//   Public, citable restatement of the same 8 (verified live 2026-10-12):
//   https://pz.harvard.edu/50th/intelligences — Harvard Graduate School of
//   Education, Project Zero (the research group Gardner co-founded and where
//   this theory originated).
//
// WHY CROSS-CURRICULAR, UNLIKE THE NGSS SEED. Gardner's theory is explicitly
// NOT a science-only framework — it claims every subject can be taught/
// assessed through multiple intelligence "entry points". So this file
// deliberately draws its 16 concepts from SIX different live subjects
// (Mathematics, Hindi, English, Social Science, Physical Education,
// Agriculture), not just Science, unlike ngss.cypher's single-subject slice.
//
// CONCEPT NODES MATCHED, NOT MERGED. Every `(c:Concept {uid: '...'})` below is
// an EXISTING live node, confirmed by direct Cypher query against the K12
// graph (dev.triz.co.in:7688) on 2026-10-12, e.g.:
//   MATCH (c:Concept) WHERE c.uid IS NOT NULL AND c.subject_id = '3976'
//   (subject_id 3976 = Mathematics; 3977 = Hindi; 3978 = English;
//    4469 = Social Science; 5574 = Physical Education; 5576 = Agriculture —
//    each confirmed via `sub_std_map`, standard_id 42)
// This file only touches the 16 real uid-keyed nodes named below, each
// verified to exist with the exact name quoted in its comment before this
// file was written. Same "say so, don't force it" rule as ngss.cypher: if a
// future reconciliation renumbers these ids, the MATCHes simply find nothing
// for the moved ones rather than inventing a replacement.
//
// WHY THESE 16 CONCEPTS FOR THESE 8 INTELLIGENCES — one real pedagogical
// reason per edge, grounded in Gardner's own definition of that intelligence,
// not a plausible-sounding guess:
//   MI1 Linguistic (sensitivity to the sounds, meanings, structures of
//       language)
//     - Use of Idioms (मुहावरों का प्रयोग): idiom use is a textbook example of
//       sensitivity to a language's figurative structure.
//     - Premchand's Language Style (प्रेमचंद की भाषा-शैली): analysing an
//       author's own prose style is a direct linguistic-intelligence task.
//   MI2 Logical-Mathematical (numerical reasoning, abstract pattern
//       recognition)
//     - Distance Formula: symbolic derivation and numeric application.
//     - Midpoint Formula: the same numeric/symbolic reasoning, paired co-ordinate
//       geometry topic.
//   MI3 Spatial (capacity to perceive and mentally transform visual-spatial
//       information)
//     - Reflection (coordinate geometry): the canonical task of visualising a
//       mirror-image transformation in a plane.
//     - Prehistoric Art and Culture: cave/rock art and visual culture is
//       Gardner's own recurring example of spatial-artistic intelligence.
//   MI4 Bodily-Kinesthetic (control of one's body movements, capacity to
//       handle objects skilfully) — Gardner's own definition, almost
//       word-for-word the PE textbook's own vocabulary:
//     - Locomotor Skills: body-movement control, the definition's first half.
//     - Manipulative Skills: skilful handling of objects, the definition's
//       second half.
//   MI5 Musical (sensitivity to pitch, rhythm, timbre; discrimination of
//       sound patterns)
//     - Musical Terminology: direct vocabulary of musical sound patterns,
//       taught inside an English-literature drama/poetry unit.
//     - Stress and Intonation: prosodic (pitch/rhythm) pattern recognition in
//       spoken language — the same auditory-pattern skill applied to speech.
//   MI6 Interpersonal (capacity to understand and work effectively with
//       other people)
//     - Interconnectedness of Society: a Social Science concept defined
//       entirely in terms of how people and groups relate to one another.
//     - Mentorship and Guidance: an English-literature concept built around a
//       mentor-mentee relationship — understanding another person's
//       perspective is the comprehension task.
//   MI7 Intrapersonal (self-knowledge, understanding one's own motivation and
//       capacities)
//     - Determination and Willpower: a concept explicitly about a character's
//       internal motivation and self-regulation.
//     - Value of Independence: explicitly about self-reliance and personal
//       agency — self-knowledge applied to one's own capacities.
//   MI8 Naturalistic (recognising, categorising, and reasoning about features
//       of the natural world) — Gardner's own worked examples for this
//       intelligence are farmers, botanists and gardeners, which is exactly
//       the real-world content these two concepts teach:
//     - Agro-climatic Relationship: classifying farming practice against
//       natural climate patterns.
//     - Soil Testing: direct empirical classification of a natural material
//       (soil) by its properties.
//
// PROPERTY NAMES. The MAPS_TO edge properties below reuse the SAME keys
// (`practice_code`, `practice_name`) that ngss.cypher's edges use, even
// though "practice" is Gardner's-theory vocabulary stretched a little thin —
// this is deliberate, not sloppy: App\Services\PAL\Framework\
// FrameworkCatalogService::graphMappings() reads every framework's edges
// through one single, framework-agnostic Cypher query keyed on those two
// property names (now generalised — see that file's own change note dated
// 2026-10-12). Reusing the same keys means this framework is readable by the
// existing query with no further change. Here `practice_code`/`practice_name`
// hold the intelligence code (e.g. "MI4") and its full name
// (e.g. "Bodily-Kinesthetic").
//
// Idempotent (MERGE throughout) — safe to re-run.
// =============================================================================

MERGE (f:Framework {framework_code: 'GARDNER'})
  SET f.name = 'Gardner Multiple Intelligences',
      f.full_name = "Howard Gardner's Theory of Multiple Intelligences",
      f.source_url = 'https://pz.harvard.edu/50th/intelligences',
      f.intelligence_count = 8,
      f.seeded_at = date('2026-10-12');

// ---- MI1 Linguistic --------------------------------------------------------
WITH f
MATCH (c1:Concept {uid: 'Concept:1:0:776'})
MERGE (f)-[m1:MAPS_TO]->(c1)
  SET m1.practice_code = 'MI1', m1.practice_name = 'Linguistic',
      m1.rationale = 'Use of Idioms (Hindi) is a textbook example of sensitivity to a language\'s figurative structure.',
      m1.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m1.verified_at = date('2026-10-12');

WITH f
MATCH (c2:Concept {uid: 'Concept:1:0:774'})
MERGE (f)-[m2:MAPS_TO]->(c2)
  SET m2.practice_code = 'MI1', m2.practice_name = 'Linguistic',
      m2.rationale = "Premchand's Language Style (Hindi) requires analysing an author's own prose style — a direct linguistic-intelligence task.",
      m2.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m2.verified_at = date('2026-10-12');

// ---- MI2 Logical-Mathematical ----------------------------------------------
WITH f
MATCH (c3:Concept {uid: 'Concept:1:0:1364'})
MERGE (f)-[m3:MAPS_TO]->(c3)
  SET m3.practice_code = 'MI2', m3.practice_name = 'Logical-Mathematical',
      m3.rationale = 'Distance Formula is solved through symbolic derivation and numeric application.',
      m3.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m3.verified_at = date('2026-10-12');

WITH f
MATCH (c4:Concept {uid: 'Concept:1:0:1366'})
MERGE (f)-[m4:MAPS_TO]->(c4)
  SET m4.practice_code = 'MI2', m4.practice_name = 'Logical-Mathematical',
      m4.rationale = 'Midpoint Formula is the same numeric/symbolic co-ordinate-geometry reasoning.',
      m4.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m4.verified_at = date('2026-10-12');

// ---- MI3 Spatial ------------------------------------------------------------
WITH f
MATCH (c5:Concept {uid: 'Concept:1:0:1365'})
MERGE (f)-[m5:MAPS_TO]->(c5)
  SET m5.practice_code = 'MI3', m5.practice_name = 'Spatial',
      m5.rationale = 'Reflection (coordinate geometry) is the canonical task of visualising a mirror-image transformation in a plane.',
      m5.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m5.verified_at = date('2026-10-12');

WITH f
MATCH (c6:Concept {uid: 'Concept:1:0:566'})
MERGE (f)-[m6:MAPS_TO]->(c6)
  SET m6.practice_code = 'MI3', m6.practice_name = 'Spatial',
      m6.rationale = "Prehistoric Art and Culture (cave/rock art, visual culture) is Gardner's own recurring example of spatial-artistic intelligence.",
      m6.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m6.verified_at = date('2026-10-12');

// ---- MI4 Bodily-Kinesthetic -------------------------------------------------
WITH f
MATCH (c7:Concept {uid: 'Concept:1:0:1104'})
MERGE (f)-[m7:MAPS_TO]->(c7)
  SET m7.practice_code = 'MI4', m7.practice_name = 'Bodily-Kinesthetic',
      m7.rationale = "Locomotor Skills is literally Gardner's own definition's first half: control of one's body movements.",
      m7.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m7.verified_at = date('2026-10-12');

WITH f
MATCH (c8:Concept {uid: 'Concept:1:0:1106'})
MERGE (f)-[m8:MAPS_TO]->(c8)
  SET m8.practice_code = 'MI4', m8.practice_name = 'Bodily-Kinesthetic',
      m8.rationale = "Manipulative Skills is the definition's second half: capacity to handle objects skilfully.",
      m8.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m8.verified_at = date('2026-10-12');

// ---- MI5 Musical -------------------------------------------------------------
WITH f
MATCH (c9:Concept {uid: 'Concept:1:0:739'})
MERGE (f)-[m9:MAPS_TO]->(c9)
  SET m9.practice_code = 'MI5', m9.practice_name = 'Musical',
      m9.rationale = 'Musical Terminology (English drama/poetry unit) is direct vocabulary of musical sound patterns.',
      m9.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m9.verified_at = date('2026-10-12');

WITH f
MATCH (c10:Concept {uid: 'Concept:1:0:741'})
MERGE (f)-[m10:MAPS_TO]->(c10)
  SET m10.practice_code = 'MI5', m10.practice_name = 'Musical',
      m10.rationale = 'Stress and Intonation is prosodic (pitch/rhythm) pattern recognition in spoken language — the same auditory-pattern skill applied to speech.',
      m10.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m10.verified_at = date('2026-10-12');

// ---- MI6 Interpersonal -------------------------------------------------------
WITH f
MATCH (c11:Concept {uid: 'Concept:1:0:502'})
MERGE (f)-[m11:MAPS_TO]->(c11)
  SET m11.practice_code = 'MI6', m11.practice_name = 'Interpersonal',
      m11.rationale = 'Interconnectedness of Society (Social Science) is defined entirely in terms of how people and groups relate to one another.',
      m11.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m11.verified_at = date('2026-10-12');

WITH f
MATCH (c12:Concept {uid: 'Concept:1:0:687'})
MERGE (f)-[m12:MAPS_TO]->(c12)
  SET m12.practice_code = 'MI6', m12.practice_name = 'Interpersonal',
      m12.rationale = 'Mentorship and Guidance (English) is built around a mentor-mentee relationship — understanding another perspective is the comprehension task.',
      m12.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m12.verified_at = date('2026-10-12');

// ---- MI7 Intrapersonal -------------------------------------------------------
WITH f
MATCH (c13:Concept {uid: 'Concept:1:0:676'})
MERGE (f)-[m13:MAPS_TO]->(c13)
  SET m13.practice_code = 'MI7', m13.practice_name = 'Intrapersonal',
      m13.rationale = "Determination and Willpower is explicitly about a character's internal motivation and self-regulation.",
      m13.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m13.verified_at = date('2026-10-12');

WITH f
MATCH (c14:Concept {uid: 'Concept:1:0:681'})
MERGE (f)-[m14:MAPS_TO]->(c14)
  SET m14.practice_code = 'MI7', m14.practice_name = 'Intrapersonal',
      m14.rationale = 'Value of Independence is self-knowledge applied to one\'s own capacities and self-reliance.',
      m14.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m14.verified_at = date('2026-10-12');

// ---- MI8 Naturalistic --------------------------------------------------------
WITH f
MATCH (c15:Concept {uid: 'Concept:1:0:1192'})
MERGE (f)-[m15:MAPS_TO]->(c15)
  SET m15.practice_code = 'MI8', m15.practice_name = 'Naturalistic',
      m15.rationale = "Agro-climatic Relationship classifies farming practice against natural climate patterns — Gardner's own worked example of this intelligence is the farmer.",
      m15.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m15.verified_at = date('2026-10-12');

WITH f
MATCH (c16:Concept {uid: 'Concept:1:0:1194'})
MERGE (f)-[m16:MAPS_TO]->(c16)
  SET m16.practice_code = 'MI8', m16.practice_name = 'Naturalistic',
      m16.rationale = 'Soil Testing is direct empirical classification of a natural material by its properties.',
      m16.source_url = 'https://pz.harvard.edu/50th/intelligences',
      m16.verified_at = date('2026-10-12');
