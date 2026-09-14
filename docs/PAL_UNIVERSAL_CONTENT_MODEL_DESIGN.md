# Universal Content Model — design and sizing

Companion to tracker item **#3**, whose action is explicit: *do not fold this into general content cleanup — size and sequence it independently, the same treatment already given to Examination as its own future module.* This is that sizing.

Written 2026-09-08, grounded in the code as it stands rather than in the original proposal. Where the two disagree, the code is described and the disagreement is noted.

---

## 1. What the item actually asserts

> Content is NOT PAL-owned. One "Universal Content" object is consumed by PAL (personalised learning), Classroom (teacher-led), and Examination (board/school assessment).

The important word is **consumed**. This is the same "one engine, many consumers" shape already applied to Administration, the Event Bus and the Question Intelligence Engine. The failure it prevents is each consumer growing its own content store — which is the pattern this tracker has already found four times in other areas (#10, #11, #16, and the two pedagogy engines in #15).

---

## 2. Current state — verified, not assumed

Content is **already shared**, and already sits under one metadata layer. It is not the greenfield the item's phrasing implies.

| Layer | Table | What it holds |
|---|---|---|
| Raw content | `content_master` | The asset itself. Legacy LMS table, read directly across ~8 services |
| Raw questions | `lms_question_master` | The item itself. Legacy, ~62k rows |
| Content metadata | `pal_content_metadata` | PAL's classification over `content_master` |
| Question metadata | `pal_question_metadata` | PAL's classification over `lms_question_master` |
| Extraction | `semantic_intelligence` | Per-chapter extracted concepts, blueprints, rubrics, misconceptions |

**What the metadata layer already carries**, much of it added during this session:

- the 4-type model (`content_type`: concept / practice / corrective / assessment)
- Bloom level, practice level, difficulty, format, language, cultural context
- **learning purpose** (#4) — what a content object is *for*, and whether it may serve as corrective
- **curriculum version** (#5) — which syllabus and year the content belongs to
- **dual fitness** (#8) — PAL calibration and board compliance as independent criteria on the same row
- misconception tags, distractor rationale, psychometrics

**So the schema is closer to "universal" than the item assumes.** The three consumers do not have three stores. What they lack is a *contract*.

---

## 3. What is genuinely missing

Three things, in increasing size.

### 3.1 A read contract per consumer (small)

Today each consumer queries `pal_content_metadata` / `pal_question_metadata` directly with its own filters — `VariantRouterService` for PAL, the blueprint engine for Examination, and nothing yet for Classroom. Each therefore owns its own definition of "servable to me".

That definition has already drifted once: `servable()` means *editorially approved*, which PAL's diagnostic treated as *fit to measure* until #2 separated them.

**The fix is not a new store.** It is a named read API per consumer — `forPal()`, `forExamination()`, `forClassroom()` — each expressing that consumer's fitness rule in one place. `QuestionMetadata::isFitForPalDiagnostic()` / `isFitForBoardExam()` (#8) are the first two, and are the pattern to extend.

### 3.2 Classroom as a real consumer (medium)

PAL and Examination both read the metadata layer. **Classroom does not exist as a consumer at all** — there is no teacher-led delivery path reading `pal_content_metadata`.

Until it does, "three consumers" is an aspiration with two implementations. Sizing this honestly means sizing a *new* consumer, not a refactor of shared plumbing.

### 3.3 Ownership of `content_master` (large)

The genuine architectural debt. `content_master` is read directly by at least eight services across PAL, Coherence, Graph projection and ESO. Every one of them reaches past the metadata layer to the legacy table.

That is what makes "content is not PAL-owned" hard to enforce: there is no boundary to enforce it at. Introducing one means changing every direct reader — which is why it belongs in its own phase and not in a content cleanup.

---

## 4. Dependencies and open decisions

| Depends on | Status |
|---|---|
| Curriculum + CG/LO mapping | `lms_learning_outcomes` exists; `learning_outcome_ref` added in #8 but unpopulated |
| Concept Intelligence | Live |
| Question Intelligence Engine | Sharpened by #8's dual fitness; one engine now defensible |
| **#6 — PAL vs Examination item isolation** | **Open. Blocks 3.1 for the Examination consumer** |

**#6 is the real gate.** A shared item pool with no reservation rule means the Examination read contract cannot be written — the blueprint engine (#19) already stops at *counting* items for exactly this reason. Any sequencing that puts 3.1 before #6 is sequencing around a decision rather than making it.

---

## 5. Sizing

Deliberately in the same shape as the Examination module's own sizing, per the item's instruction.

| Phase | Scope | Size | Gate |
|---|---|---|---|
| **U1** | Read contract for PAL. Consolidate PAL's fitness rules behind one named API; make `VariantRouterService` and the ESO selection paths use it | **S** — the rules exist, they are scattered | None. Can start now |
| **U2** | Read contract for Examination | **S** | **Blocked on #6** |
| **U3** | Populate the new classification: `learning_purpose`, `curriculum_version_id`, `blueprint_category` / `marks` | **M**, and it is authoring effort, not engineering | None. Independent of U1/U2 |
| **U4** | Classroom as a consumer — teacher-led delivery reading the same layer | **L** | U1 (needs the contract pattern) |
| **U5** | Boundary around `content_master` — a content repository the ~8 direct readers go through | **XL** | U1, U4. Do last |

**U3 is the one to start first in practice.** U1 is small but reorganises code that already works; U3 is what turns three columns added this session from schema into behaviour. Every one of #4, #5 and #8 is currently correct and inert for want of authored data.

---

## 6. What this design deliberately rejects

- **A new "universal content" table.** There already is one layer over two legacy stores. A third table would be the fourth duplication this tracker has found, not a fix.
- **One blended fitness score.** #8 established why: an item can be calibrated but not board-fit, or board-fit but uncalibrated, and every newly authored item is the latter.
- **Migrating `content_master` early.** It is the largest and least reversible step, and none of the other phases need it done first.
- **Building Classroom to make the "three consumers" claim true.** The claim is a design intent, not a reason to build a delivery surface nobody has asked for.

---

## 7. Recommendation

Sequence **U3 → U1 → (#6 decision) → U2 → U4 → U5**.

The first two need no decisions. U3 needs authoring capacity rather than engineering. Everything from U2 onward waits on #6, which should therefore be taken sooner than its Phase 2 label suggests — it gates more than its own item.
