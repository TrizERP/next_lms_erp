# Vocabulary — collisions and canonical terms

This estate has **six vocabulary registries** that grew independently, plus a de-facto seventh inside the extraction JSON. The same word means different things in different places, and the same thing has different names.

**31 collisions are documented below.** The worst are #1 (`content_type`), #7 (three encodings of "platform tenant"), and #21 (`assessment_blueprint` naming two unrelated things).

**Read this before adding any vocabulary anywhere.** It is not exhaustive — it is what two survey passes found, and the assessment axis in particular was missed entirely on the first pass.

## The registries

| Registry | File | Axis it owns |
|---|---|---|
| Governance / authoring | `config/lms_content.php` | who authored it, who owns it, who may see it |
| Pedagogy / delivery | `config/pal_content.php` | what it teaches, at what level, in what format |
| H5P / frameworks | `config/pal_h5p.php` | 12 pedagogies, 27 H5P types, CASEL/NGSS/NCDG |
| Alias maps | `config/pal_content_model.php` | **the sanctioned place to reconcile names** |
| Paper design | `assessment_blueprint` table + `app/Domain/Exam/{AssessmentBlueprint,HpcBlueprint}.php` | board paper patterns, marks, question counts, HPC rubrics |
| Exam blueprint config | `config/pal_exam_blueprint.php` | CBSE section patterns and difficulty spread |
| Extraction schema | `semantic_intelligence.full_intelegance_json` | 18 per-concept keys, shape lives only in code |

> **Rule: reconciliation goes in `pal_content_model.php`'s alias maps** (`bloom_aliases`, `difficulty_aliases`, `knowledge_type_aliases`, `assessment_type_map`). Putting it anywhere else creates collision #21.

---

## Collisions that will bite

### 1. `content_type` — two unrelated meanings ⚠️ worst one
- `lms_content.authoring_types` keys = **authoring surface**: `presentation`, `teacher_training`, `revision_notes`, `classroom_activity`, `video`, `question`
- `pal_content.content_types` keys = **pedagogy model**: `concept`, `practice`, `corrective`, `assessment`

Both are validated by a field literally called `content_type`. A third value, `expanded_task`, appears in `PalPedagogyEngineSeeder` and is in neither list.

**Canonical:** this standard says **"content type"** means the authoring surface (the five in §4.3 of the README). When you mean the pedagogy model, say **"pedagogy content model"**.

### 2. `format` — two disjoint closed sets, one column
- `pal_content.formats`: `text_diagram`, `video`, `story_audio`, `simulation`, `h5p`, `pdf`, `external`
- `pal_content_model.assessment_type_map[*].format`: `mcq`, `fill_blank`, `match`, `short_answer`, `essay`, `scenario`, `diagram`, `numerical`, `performance`, `oral`

`pal_question_metadata.format` is one varchar(32). **A row written from the second set cannot pass `PalVocabulary::validate()`.** Live bug.

### 3. `assessment_type` — three meanings
- *purpose*: `diagnostic`, `formative`, `competency`, `retention`, `sky`, `vocational` (`pal_content`)
- *question format*: MCQ, short answer, case study (extraction JSON)
- *board slot*: `blueprint_categories` — `mcq`, `assertion_reason`, `very_short_answer`, …

Note `competency` (purpose) vs `competency_based` (board slot) vs `competency_based` (pedagogy tag) — three different things, near-identical strings.

### 4. Bloom — three spellings
| Spelling | Where |
|---|---|
| `recall` | `pal_content.php` canonical key |
| `Remember` | `QuestionGenerationService::BLOOM_LEVELS`, `lms_mapping_type` id 88 |
| `remember` | drawer `conceptIntelligenceGuide.ts` |

Same for `analyze` / `Analyse` / `analyse`, and `create` / `Creating`.

**Canonical for generated content: lowercase** — `remember understand apply analyze evaluate create`. `pal_content_model.bloom_aliases` already reconciles these; extend it, don't add a sixth spelling.

### 5. DOK is conflated with difficulty
`lms_mapping_type` parent **9** is literally named *"Depth of Knowledge (Easy, Medium, Hard)"* and maps DOK `1→easy, 2→medium, 3→hard`. That is not what DOK is. The real Webb scale is in `pal_content_model.dok_levels`: Recall and Reproduction · Skills and Concepts · Strategic Thinking · Extended Thinking.

`config/pal_content.php` has **no DOK vocabulary at all** — the one axis with no closed set.

**Canonical:** DOK is `1`–`4` on the Webb scale. Difficulty is a separate axis.

### 6. Difficulty — four scales
`1-5` integer · `Easy|Medium|Hard` · `easy|medium|hard` · bare integer. Bridge: `pal_content_model.difficulty_aliases`.

### 7. Platform tenant has THREE different encodings ⚠️⚠️
- `lms_content.platform_sub_institute_ids => [1]`
- `pal_content.global_sub_institute_id => 0`
- `assessment_blueprint.sub_institute_id` is **NULL** on a platform reference row — the migration comment says so explicitly: *"NULL on a reference blueprint: it belongs to the platform, not a school."*

`sub_institute_id = 0` **does not exist** in `content_master`. Three registries, three encodings of "belongs to the platform". Never hardcode any of the three — go through the accessor for whichever table you are touching.

### 8. Ownership `teacher` surfaces as `mine`
Storage: `platform | school | teacher`. API badge: `platform | school | mine | unclassified`. Four on the wire, three in storage.

### 9. H5P types — 15 vs 27
`pal_content.h5p_types` has 15; `pal_h5p.h5p_types` has 27. `PalVocabulary` validates against the **15**, so 10 registered-and-native types are unwritable. Also `image_hotspot` vs `image_hotspots` — two tables, near-identical keys.

### 10. Pedagogy — 9 vs 12 vs 9 live rows
`pal_content.pedagogy_fallback` (9) and `pal_h5p.pedagogy_tags` (12) overlap in only **5**. `problem_based` is an alias of `project_based` in one and a distinct value in the other. Runtime truth is neither: it is read live from `lms_mapping_type` parent **73569**, where names carry trailing newlines (`"Flipped classroom pedagogy\n"`).

### 11–20. Shorter ones
- **NGSS practices** — long keys in `pal_content`, short keys in `pal_h5p`; the validator still checks the legacy list.
- **NCDG goals** — `pal_content` lacks `CM4`, which `pal_h5p` references.
- **Gardner** — `naturalistic` vs `naturalist`. One character, two registries.
- **`evidence`** — textbook source quotes · learner attempt evidence · a psychometric flag · "Evidence & Confidence framework". Four senses.
- **`stage`** — learning-flow category · QA stage 1-6 · NEP school stage. Three senses.
- **`category`** — `content_category` free text · PAL learning-flow stage · H5P UI grouping · implementation category. Four senses.
- **Content-category casing is not normalised** — `content_master` holds both `"Classroom Presentation"` (2,395 rows) and `"Classroom presentation"` (1). **Always match case-insensitively on read.**
- **HPC** — `Awareness/Sensitivity/Creativity` capitalised in one place, six lowercase domains in another.
- **JSON keys vs fallback columns** — JSON uses `knowledge_items`, `abilities`, `pedagogy_recommendations`; the columns are `knowledge`, `ability`, `pedagogy`. Two independent copies of the translation table exist.
- **`assessment_rubrics` is typed wrong** — declared `teaching_notes?: string` in `chapters.ts`, consumed as `Record<string, string[]>`. The runtime shape is the record.

---

---

## The assessment axis — its own cluster of collisions

These were found in a second pass and are worse than the ones above, because unlike Bloom and difficulty the assessment axis has **no alias map at all** connecting its seven definition sites.

### 21. `assessment_blueprint` names two unrelated things ⚠️ worst in the estate
- The **`assessment_blueprint` table** (`2026_09_21_120000_*`) holds **board paper designs** — marks by chapter, question counts, difficulty split — which schools clone from platform reference rows.
- The **`assessment_blueprint` key inside `full_intelegance_json`** holds **suggested questions for one concept**.

Neither references the other. Anything generating content wants the JSON key; anything building a question paper wants the table.

### 22. "Blueprint" names four things
Paper **design** (`assessment_blueprint` table) · paper **layout** (`QuestionPaperTemplateBlueprint` — page size, header, numbering) · per-concept **question suggestions** (the JSON key) · board **question-type taxonomy** (`pal_content.blueprint_categories`). A fifth, `lms_assessment_typology`, was abandoned and never run on a live DB.

### 23. Question type exists in five incompatible spellings
`AssessmentBlueprint::QUESTION_TYPES` (12: `mcq, objective_other, assertion_reason, vsa, sa1, sa2, sa, la, case_based, competency, practical, internal`) · `pal_content.blueprint_categories` (7: `very_short_answer, short_answer, long_answer…`) · `QuestionGenerationService::BLOOM_META[*].sub_type` (`Very Short Answer, Case Study`) · `pal_content_model.assessment_type_map` keys (lowercase prose) · `lms_question_master.question_type_id` (integer FK).

`case_based` / `case_based/source based` / `Case Study` are one slot under three names.

### 24. `assessment_type` — a fourth meaning
Add `AssessmentBlueprint::ASSESSMENT_TYPES` (`Periodic Test, Unit Test, Half Yearly, Term, Annual, Board, Formative, Summative`) to the three in #3. Careful: `Formative` here is a **paper occasion**; `formative` in `pal_content.assessment_types` is a **BKT purpose**. Different concepts, same word, different casing.

### 25. `stage` — a fourth meaning
`assessment_blueprint.stage` is the **NCF stage** (`Foundational, Preparatory, Middle, Secondary`) — the same meaning as `pal_content_model`'s curriculum `stage`, but `pal_question_metadata.stage` uses that identical column name for the learning-flow category.

### 26. `status` — three ladders, two casings
`assessment_blueprint.status` = `Draft | Active | Archived` (TitleCase) · `lms_content.statuses` = `active | archived` · `pal_content.quality_statuses` = `draft | reviewed | …`. `Draft` and `draft` collide across registries.

### 27. Difficulty — a fifth scale
`AssessmentBlueprint` `difficulty_distribution` uses **`easy / average / difficult`**; `pal_exam_blueprint.difficulty_spread` uses **`easy / medium / hard`** over the same 1-5 levels. The two blueprint files disagree on the middle band's name, and `pal_content_model.difficulty_aliases` resolves `difficult → 4` while the `hard` band covers 4-5.

### 28. `evidence` — a fifth meaning
`HpcBlueprint::EVIDENCE_MODES` (`observation, portfolio, activity, project, conversation, worksheet, peer_feedback, self_reflection`) — how a judgement was gathered. Distinct from all four senses in #14.

### 29. See #7 — the platform-tenant marker has a third encoding
`NULL`, alongside `1` and `0`.

### 30. `teacher` means two roles
`HpcBlueprint::ASSESSORS` = `self, peer, teacher, parent` (who **judges**). `lms_content.ownership` = `platform, school, teacher` (who **authored**). Both contain `teacher`.

### 31. Pedagogy — a fourth list
`HpcBlueprint::ACTIVITY_APPROACHES` (`art_integrated, sports_integrated, toy_based, technology_integrated, skill_based, experiential, cross_cutting, iks_integrated, other`) overlaps `pal_h5p.pedagogy_tags` and `pal_content.pedagogy_fallback`, aliased to neither. Note `iks_integrated` here vs `spiritual_science` there for the same territory.

> **Where a new assessment vocabulary would actually help.** Bloom and difficulty at least have alias maps. The assessment axis is defined in **seven** places with nothing connecting them — `pal_content`, `pal_content_model`, `pal_exam_blueprint`, `AssessmentBlueprint`, `HpcBlueprint`, `QuestionGenerationService`, and the extraction JSON. That is the one axis where a shared registry adds the most and competes the least.
>
> **A structural pattern worth copying.** For anything versioned, cloneable and tenant-overridable, `AssessmentBlueprint`/`HpcBlueprint` are a better model than `AuthoringTypeRegistry`: one table, a `kind` discriminator, a JSON `definition`, a `normalize()` that brings old shapes forward, and `preset_key`/`parent_id` provenance. That already solves reference-vs-school layering, which the authoring registry does not.

---

## What this standard introduces

Exactly two new words — **Block** and **Pack** — chosen because nothing in the estate uses either. Everything else reuses an existing canonical term.

New vocabulary goes in `config/lms_content.php` (`authoring_types`, via `AuthoringTypeRegistry`) for the authoring axis, or the `pal_content_model.php` alias maps for the pedagogy axis. Not in a new registry.
