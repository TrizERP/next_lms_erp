# Neo4j pre-wipe backup — 2026-08-10

**Phase 0 artifact.** Exported live from `bolt://dev.triz.co.in:7688` (Neo4j 4.4.40 Community),
database `neo4j`, on 2026-08-10. Read-only export: nothing was written to Neo4j or MariaDB.

This directory is the **only** record of the graph data that MariaDB cannot regenerate. Phase 3
destroys the graph. Do not delete these files until the rebuild has been verified green.

## Scope rule

Every label with **< 1,000 nodes** and every relationship type with **< 1,000 edges** was exported in
full. That is a deliberate superset of the three items in
[`neo4j-migration-status.md` §5](../neo4j-migration-status.md) — the small labels cost a few hundred
KB and several of them have an unconfirmed source table, so exporting them is cheaper than being
wrong about which ones are re-derivable. The five high-volume sets (`Result`, `Question`, `Student`,
`Chapter`, `StuDetail` and their bulk edges) were skipped: all are re-derivable from `vivek_erp`.

## What was captured

### STATUS §5 items — all three covered

| §5 item | File | Rows | Status |
|---|---|---:|---|
| 28 `PREREQUISITE_OF` edges | `rels_PREREQUISITE_OF.csv` | 28 | ✅ backed up |
| 11 `Concept` nodes incl. `bloom_level` / `pedagogy_tag` | `nodes_Concept.csv` | 11 | ✅ backed up — **both columns exist and hold values** |
| 40 `CompetencyStandards`, 8 `LearningObjects`, 3 `AssessmentTypology` | `nodes_CompetencyStandards.csv`, `nodes_LearningObjects.csv`, `nodes_AssessmentTypology.csv` | 40 / 8 / 3 | ✅ backed up |

### Full manifest

| Item | Kind | Graph count | File | Rows written |
|---|---|---:|---|---:|
| Assessment | node | 166 | `nodes_Assessment.csv` | 166 |
| AssessmentTypology | node | 3 | `nodes_AssessmentTypology.csv` | 3 |
| ChapterStandardMap | node | 96 | `nodes_ChapterStandardMap.csv` | 96 |
| CompetencyStandards | node | 40 | `nodes_CompetencyStandards.csv` | 40 |
| Concept | node | 11 | `nodes_Concept.csv` | 11 |
| Curriculum | node | 31 | `nodes_Curriculum.csv` | 31 |
| LearningContent | node | 11 | `nodes_LearningContent.csv` | 11 |
| LearningObjects | node | 8 | `nodes_LearningObjects.csv` | 8 |
| Lesson | node | 719 | `nodes_Lesson.csv` | 719 |
| Misconception | node | 1 | `nodes_Misconception.csv` | 1 |
| Standard | node | 110 | `nodes_Standard.csv` | 110 |
| Subject | node | 150 | `nodes_Subject.csv` | 150 |
| Teacher | node | 118 | `nodes_Teacher.csv` | 118 |
| Unit | node | 6 | `nodes_Unit.csv` | 6 |
| Chapter | node | 5,536 | _skipped — re-derivable from `chapter_master`_ | — |
| Question | node | 94,052 | _skipped — re-derivable from `lms_question_master`_ | — |
| Result | node | 143,360 | _skipped — re-derivable from `result_*`_ | — |
| StuDetail | node | 4,609 | _skipped — legacy, D5, deliberately not rebuilt_ | — |
| Student | node | 12,801 | _skipped — re-derivable from `tblstudent`_ | — |
| ASSESSES | rel | 2 | `rels_ASSESSES.csv` | 2 |
| ASSESSES_CHAPTER | rel | 90 | `rels_ASSESSES_CHAPTER.csv` | 90 |
| ATTEMPTED | rel | 40 | `rels_ATTEMPTED.csv` | 40 |
| BELONGS_TO_CURRICULUM | rel | 31 | `rels_BELONGS_TO_CURRICULUM.csv` | 31 |
| COVERS | rel | 11 | `rels_COVERS.csv` | 11 |
| FOR_ASSESSMENT | rel | 112 | `rels_FOR_ASSESSMENT.csv` | 112 |
| HAS_ASSESSMENT | rel | 105 | `rels_HAS_ASSESSMENT.csv` | 105 |
| HAS_LESSON | rel | 557 | `rels_HAS_LESSON.csv` | 557 |
| HAS_MISCONCEPTION | rel | 3 | `rels_HAS_MISCONCEPTION.csv` | 3 |
| HAS_QUESTION | rel | 545 | `rels_HAS_QUESTION.csv` | 545 |
| HAS_SUBJECT | rel | 679 | `rels_HAS_SUBJECT.csv` | 679 |
| HAS_UNIT | rel | 6 | `rels_HAS_UNIT.csv` | 6 |
| INCLUDES | rel | 31 | `rels_INCLUDES.csv` | 31 |
| MASTERS | rel | 21 | `rels_MASTERS.csv` | 21 |
| OCCURS_IN | rel | 1 | `rels_OCCURS_IN.csv` | 1 |
| PREREQUISITE_OF | rel | 28 | `rels_PREREQUISITE_OF.csv` | 28 |
| REMEDIATES | rel | 1 | `rels_REMEDIATES.csv` | 1 |
| TEACHES | rel | 1 | `rels_TEACHES.csv` | 1 |
| ATTENDED | rel | 16,350 | _skipped — re-derivable_ | — |
| BELONGS_TO | rel | 86,265 | _skipped — re-derivable; renamed to `ASSESSES` in the target model_ | — |
| ENROLLED_IN | rel | 5,472 | _skipped — re-derivable_ | — |
| HAS_CHAPTER | rel | 2,016 | _skipped — re-derivable_ | — |
| HAS_RESULT | rel | 501,034 | _skipped — re-derivable; collapses into `ATTEMPTED` in the target model_ | — |
| HAS_STUDENT | rel | 5,590 | _skipped — legacy `StuDetail` edge, D5_ | — |

## File format

**Node files** (`nodes_<Label>.csv`) — header is `_neo4jInternalId` followed by the union of every
property key observed on that label, alphabetically. A blank cell means the property is absent on
that node, not that it is an empty string.

**Relationship files** (`rels_<TYPE>.csv`) — header is
`_relInternalId,_fromInternalId,_fromLabels,_fromKeyProps,_toInternalId,_toLabels,_toKeyProps`
followed by the union of the edge's own property keys. `_fromKeyProps` / `_toKeyProps` are JSON
objects holding only the endpoint's identifying properties (anything matching `*Id`, `*_id`,
`*_name`, `name`, `title`), so an edge can be reconstructed after the wipe by matching on business
keys rather than on Neo4j internal ids — **internal ids do not survive a wipe and must not be used
to restore anything.** They are recorded for traceability only.

## Finding: `PREREQUISITE_OF` is not hand-authored

Worth recording before anyone treats these 28 edges as irreplaceable curriculum knowledge.

All 28 edges lie inside a single chapter (`chapter_id` 8560, `lesson_id` 1874, `sub_institute_id` 1)
and span exactly 8 concepts, ids 4–11. 8 concepts yield C(8,2) = **28** ordered pairs, and every
edge runs from the lower id to the higher id. The set is therefore the **complete transitive closure
of a linear ordering by concept id** — every concept is marked a prerequisite of every later one,
including `Introduction to rational numbers → The square root spiral`.

That is a mechanically generated ordering, not authored pedagogy. Two consequences:

1. It is regenerable in one line of Cypher from any ordered concept list, so the "cannot be
   regenerated" claim in STATUS §5 row 1 is **weaker than recorded** — the CSV is kept anyway,
   because it costs nothing and proves what the old graph contained.
2. It is evidence for the **PREREQ-SOURCE** decision (STATUS §3, still OPEN): the existing graph
   supplies no genuine prerequisite signal, so that decision cannot be resolved by mining the
   current graph. It needs curriculum input or inference over the 1,372 `lms_concept` rows.
