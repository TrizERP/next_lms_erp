# Is the Concept Intelligence panel writing to a shared Evidence/KASBA store?

**Answers:** Tracker sheet "Content & LMS Architecture" row 6 · "Decisions & Risk Log" #38
**Date:** 2026-09-07 · **Method:** direct code reading + read-only queries against live `vivek_erp`
**Status of the answer:** settled. No team confirmation needed — this is measurable, and it was measured.

---

## The question as the tracker asked it

> VERIFY: does this write into the same central Evidence/KASBA store that Fees' AI Stack, PAL, and future
> Career Intelligence are meant to read from — or is it currently a chapter-local table? This is the single
> most important thing to confirm before more modules are built assuming a shared Evidence layer exists.

## The answer

**Neither option the tracker offers is correct, and the distinction matters.**

The panel reads `semantic_intelligence` — **one global table, not a chapter-local one.** But it is a
**chapter-scoped, write-once AI-extraction cache of opaque JSON**, with no learner linkage and no queryable
surface. It is not, and cannot become without redesign, the shared Evidence layer that Fees' AI Stack, PAL
and Career Intelligence are meant to read from.

So the honest status is: **shared table — not a shared Evidence store.**

Recommend row 6's status change from `Live – verify backend` to
**`Live — KASBA vocabulary proven, Evidence layer NOT present`**, and #38 resolve to a *decision* (who builds
the Evidence layer, and when) rather than a *confirmation*.

---

## Evidence

Six findings. Every one is re-runnable.

### 1. It covers 6.6% of the chapter space PAL routes over

| Measure | Value |
|---|---:|
| `semantic_intelligence` rows | **89** |
| distinct `chapter_id` in it | **89** (one row per chapter) |
| distinct `sub_institute_id` in it | **2** (inst 1 → 86 rows, inst 341 → 3 rows) |
| `chapter_master` rows | 120 |
| distinct `chapter_id` carrying questions in `lms_question_master` | **1,351** |

89 / 1,351 = **6.6%**. Any dashboard reporting "100% coverage (86/86)" is reporting 100% of an 86-chapter
denominator that is itself 6.6% of the estate. The denominator hides the gap rather than measuring it.

### 2. Every intelligence field is an opaque blob

`database/migrations/2026_06_15_000001_add_semantic_fields_to_semantic_intelligence_table.php` adds
`knowledge`, `ability`, `skill`, `competency`, `blooms_level`, `dok`, `prerequisites`, `misconceptions`,
`real_world_applications`, `pedagogy`, `learning_objectives`, `learning_outcomes`, `assessment_blueprint` —
**all JSON/longtext**. `2026_07_21_000000` adds `assessment_rubrics` (longText).

Consequence: a question as basic as *"which concepts develop competency X?"* requires a full-table decode.
There is no index, no join key, no `WHERE`. That is the practical difference between a *cache* and a *store*.

### 3. There is no learner linkage — the structural disqualifier

The table has no `learner_id`, `student_id` or `user_id` column. Its grain is **content × chapter**.

KASBA evidence is **learner × concept**. A store that cannot name the learner cannot answer "does this student
have this capability?", which is the only question Fees' AI Stack, PAL's ESO and Career Intelligence actually
want to ask it. This is not a gap to be filled by more extraction runs; it is the wrong grain.

### 4. Every consumer is read-only, and they are all inside K-12 curriculum

| Consumer | File |
|---|---|
| PAL Content Model projection | `app/Services/PAL/ContentModel/SemanticSourceRepository.php` |
| PAL content intelligence reader | `app/Services/PAL/Content/SemanticIntelligenceSource.php:75,101` |
| PAL runtime evidence repository | `app/Services/PAL/Runtime/PalEvidenceRepository.php:315-345` |
| The panel's own API | `app/Http/Controllers/api/lms/SemanticIntelligenceApiController.php` |

`database/migrations/2026_08_14_100000_create_pal_content_model_tables.php` states the contract in its own
header: the Content Model is *projected* from `semantic_intelligence` on every request, never copied, and
`semantic_intelligence` is **read and never written** by that module.

Nothing outside K-12 curriculum reads it. No Fees, no Career, no Enterprise Brain caller exists.

### 5. The real evidence estate is fragmented across seven tables — and the curriculum path writes none of them

| Table | Live rows |
|---|---:|
| `ai_evidence` | 1,296 |
| `ai_case_evidence` | 667 |
| `hpbrain_evidence` | 135 |
| `hpbrain_case_evidence` | 135 |
| `pal_learning_evidence` | 34 |
| `evidence_events` | 7 |
| `s_competency_evidence` | 0 |

Seven stores, no shared schema, and the Concept Intelligence path writes to none of them. Competency is
duplicated the same way — `competency`, `competency_kasba_item`, `competency_kasba_rating`,
`course_competency_map`, `pal_competencies` (23,047 rows), `hpbrain_competencies`, and 17 `s_competency_*`
tables. This is the "built but inert" pattern the Session Audit tab names, showing up in the evidence layer
itself.

### 6. The table is not under migration control

| | Migration declares | Production has |
|---|---|---|
| blob column | `full_intelligence_json` | **`full_intelegance_json`** |
| quality column | `quality_flag` | **`qulity_flag`** |
| topic count | `total_topics` | **`total_concepts`** |

Source: `database/migrations/2026_06_10_000000_create_semantic_intelligence_table.php:24,25,29` vs
`SHOW COLUMNS FROM semantic_intelligence` on live.

The codebase has already absorbed the drift rather than fixing it —
`app/Services/PAL/Runtime/PalEvidenceRepository.php:326-328` carries a runtime `Schema::hasColumn` fallback
for both spellings, and the misspelling is propagated all the way into the frontend TypeScript types
(`lms_k12/app/course-master/data/chapters.ts:356`). A store whose schema is decided by whatever happens to be
in production is not a foundation other modules should build on.

---

## What the panel *does* prove

Stated plainly, because the tracker is right that this is a real proof-point — just of something narrower
than it claims.

The panel demonstrably extracts and displays **Knowledge / Ability / Skill / Competency / Bloom's / DOK /
Prerequisites / Misconceptions / Real World / Pedagogy** per chapter, with a confidence score, and PAL already
projects usable structure out of it (`ConceptIntelligenceTabs.tsx` renders 18 dimensions, filtered to
non-empty).

That proves the **KASBA vocabulary** works end to end. It does not prove an **Evidence layer** exists.
Those are different claims, and the tracker currently reads the first as the second.

---

## Recommendation

1. **Do not let any further module be built on the assumption that a shared Evidence layer exists.** It does
   not. Fees' AI Stack and Career Intelligence would each be reading a chapter-grain JSON cache.
2. **Ship a normalized read projection** (planned as Phase A5) so consumers query a contract instead of
   `full_intelegance_json`: one row per `(chapter, tenant, dimension, item)`, derived and fully rebuildable.
   This is cheap, is not the Evidence Engine, and makes `WHERE dimension='competency' AND item_key=?` possible
   for the first time.
3. **The Evidence & Context Engine stays Track D's**, as the Central Engines sheet already assigns it. Our
   projection is explicitly a read-side convenience, not a claim on that scope.
4. **Fix the schema drift separately** — a migration reconciling the three misspelled columns, or a decision to
   accept them and correct the migration to match production. Leaving a `Schema::hasColumn` fallback in a
   runtime hot path is a latent failure.
5. **Reconcile the seven evidence tables before any of them is called "the" evidence store.** That is a
   prerequisite for #34 (K-12 vs G2G capability overlap), not a follow-on.

---

## Follow-up shipped — a queryable index (Phase A5)

Recommendation 2 above is now built: `lms_concept_intelligence_index`, a derived, fully rebuildable
projection of the blobs. `semantic_intelligence` is still never written.

`php artisan lms:project-concept-intelligence` — idempotent (chunked upsert on `(semantic_id, row_hash)`
plus a stamp-and-prune pass). Verified: two consecutive runs both report 41,745 rows, 0 pruned, 0 failed.

| dimension | rows | distinct items |
|---|---:|---:|
| knowledge | 6,681 | 6,586 |
| ability | 4,684 | 4,569 |
| prerequisite | 4,881 | 3,495 |
| real_world | 4,547 | 4,547 |
| misconception | 3,945 | 3,916 |
| bloom | 3,867 | **7** |
| skill | 3,746 | **209** |
| pedagogy | 3,709 | **10** |
| dok | 3,170 | **4** |
| competency | 2,515 | 2,504 |
| **total** | **41,745** | |

The small distinct counts are the point: 7 Bloom levels, 4 DOK levels, 10 pedagogy strategies and a
**209-skill taxonomy** were all sitting inside the blobs, and none of them could be reached by a `WHERE`
clause before this table existed.

Read API (read-only, no write path — by design):
- `GET /api/lms/concept-intelligence/{chapterId}/index?dimension=&concept=`
- `GET /api/lms/concept-intelligence/lookup?dimension=&item_key=` — the cross-chapter question that was
  previously impossible: *"which concepts, in which chapters, develop this competency?"*

Purity note: `ConceptIntelligenceProjection::project()` takes a row array and returns rows, touching no
database, so `tests/Unit/ConceptIntelligenceProjectionTest.php` (9 tests, 30 assertions) runs without going
near the live shared database that `phpunit.xml` points at.

**A measurement correction worth recording.** An initial ad-hoc PDO script reported 26,893 entries in these
blobs. That was wrong — reading raw and projected side by side inside one Laravel process gives **41,908 raw
entries → 41,745 projected**. The standalone script was silently truncating large `longtext` values, so
`json_decode` failed on the biggest chapters and they counted as zero. Counts elsewhere in this document come
from server-side `COUNT`/`SUM`, which is not affected.

**What this still is not.** No learner linkage, no evidence events, no write API, no ingestion from other
modules, and no reconciliation of the seven fragmented evidence tables. Those remain the Evidence & Context
Engine's job (Track D, Central Engines sheet). This is a read-side convenience beneath it.

---

## How to reproduce

```sql
-- Finding 1
SELECT COUNT(*) rows_total, COUNT(DISTINCT chapter_id) chapters,
       COUNT(DISTINCT sub_institute_id) tenants FROM semantic_intelligence;
SELECT COUNT(DISTINCT chapter_id) FROM lms_question_master;
SELECT COUNT(*) FROM chapter_master;

-- Finding 5
SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE '%evidence%';

-- Finding 6
SHOW COLUMNS FROM semantic_intelligence;
```

Note: probe with a direct PDO script or `DB::table()` builder calls. `php artisan tinker --execute` dies on
the word `schema` anywhere in argv — `app/Console/Kernel.php::bootstrap()` exits 1 on
`db:seed|schema|fresh|refresh`.
