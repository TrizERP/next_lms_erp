# Adaptive Learning Engine (Learning ESO) — Implementation Plan

Source of truth: `Scholar_Adaptive_Learning_Engine_Developer_Brief_v1.pdf` (v1.0, Aug 2026).
Pilot scope: Chapter 3 — Metals and Non-metals, ~10–15 concepts.
This document is Phase 3 of that brief: the gap analysis between the brief and the existing
`next_lms_erp` (Laravel) / `lms_k12` (Next.js) codebase, produced by direct code inspection
(migrations, models, services, controllers, routes, frontend data layers — not assumption).

Status: **Phase 1–3 complete (read brief, inspect codebase, gap analysis). Phase 5+ (actual
implementation) has NOT started** — this plan needs the "Conflicts requiring a decision" section
(§L) resolved first, because they change which tables/services get built on.

---

## A. Existing architecture relevant to the feature

The codebase already contains **three generations of a PAL (Personalized Adaptive Learning)
system**, layered on top of each other, none of which was retired when the next was built:

1. **Legacy `/lms/*` PAL quiz flow** — `app/Http/Controllers/lms/pal/palController.php` (2,500
   lines) + `routes/lms.php`. This is what a student actually uses today: `lms/pal` (chapter
   picker), `lms/pal/create` (start quiz), `lms/pal` POST (submit), `lms/pal/{id}` (result),
   `lms/misconception`, `lms/pedagogy-suggested-content`. Backed by legacy tables
   `question_paper` (`exam_type='PAL'`, chapter id stored in `paper_desc`), `lms_online_exam`,
   `lms_online_exam_answer(_student)`, `lms_question_master`, `answer_master`. Question
   selection is score-banded (`<40 easy / 40–70 medium / ≥70 hard`), first attempt mixed
   easy4/medium3/hard3 — this is the "adaptive difficulty" the brief's D1 partially overlaps
   with, but it operates on **chapter-level** score bands, not per-concept mastery.

2. **PAL V4 "Intelligence" engine** — `/api/pal/*` (`routes/pal_api.php`, ~135 routes across 9
   controllers), 27 core `pal_*` tables (`2026_06_11_131905_create_pal_tables.php`) plus ~34 more
   added since. Contains `LearnerStateEngine`, `PredictiveInterventionEngine`,
   `LearningVelocityEngine`, `MisconceptionIntelligenceEngine`, `RecommendationEngine`,
   `TelemetryService`, `BehavioralAnalyticsService`. **Documented dead-data problem**: these
   engines read `pal_competencies` / `pal_learning_sessions` / `pal_session_events`, and the
   codebase's own comments say those tables have "no confirmed writer in production" — i.e. this
   generation is architecturally real but largely unfed.

3. **PAL Content Intelligence / Content Model layer** ("New PAL", Aug 2026) — the actively
   maintained generation. `pal_question_metadata`, `pal_content_metadata`, `pal_concept_metadata`,
   `pal_concept_relations`, `pal_misconception_library`, `pal_misconception_corrective`,
   `pal_learner_content_exposure`, `pal_content_review_log` (8 tables,
   `2026_08_13_100000_create_pal_content_intelligence_tables.php`), plus `BktEngine` (real
   Bayesian Knowledge Tracing, `app/Services/PAL/Runtime/BktEngine.php`),
   `PalEvidenceRepository` (reads the **real** evidence chain:
   `lms_online_exam_answer → lms_online_exam → question_paper`), `BloomLadderService` (per-concept
   5-level practice-ladder gate/regression chain), `VariantRouterService` (content re-routing),
   `MisconceptionLibraryService` (detect→confirm→route pipeline). This generation's own code
   comments state it explicitly: *"The PAL V4 engines were written against the `pal_*` tables,
   which nothing writes. The learner evidence PAL actually produces lives in the legacy LMS
   stack."* **This is the generation the Adaptive Learning Engine should build on.**

Frontend (`lms_k12/app/pal/`) mirrors this layering: `app/pal/exam`, `/result`, `/report` (legacy
flow), `app/pal/intelligence` (V4 dashboard), `app/pal/new/content-model`, `/coherence-map`,
`/gamification` (content-intelligence-layer admin/authoring tools). A working
`DiagnosticPanel.tsx` (BKT-based diagnostic → mastered/partial/gap/prerequisite_gap
classification), `PracticePanel.tsx` (adaptive practice, spaced-repetition review schedule,
practice history), `StudentPicker.tsx`/`ViewAsBanner.tsx` (staff learner-scoping) already exist
and are directly reusable.

**Auth**: `pal.auth` middleware (`PalApiAuth`, GenTux JWT) already secures all `/api/pal/*`
routes with tenant/ownership scoping — reuse as-is for any new engine endpoints.

**Chapter/Subject/Concept schema**: `chapter_master` (subject_id, standard_id) ← `subject`,
`standard` (→ grade); `lms_concept` (chapter_id, subject_id, standard_id, bloom_level,
difficulty_level, mastery_threshold — **no K/A/S, no prerequisite/misconception FK**). Enumerated
today via `PalEvidenceRepository::conceptsForChapters()` (`lms_concept` LEFT JOIN
`pal_concept_metadata`).

---

## B. Existing tables that can be reused

| Table | Reuse for |
|---|---|
| `lms_concept` | The concept catalogue itself — Chapter 3's 10–15 concepts live here (`chapter_id`, `subject_id`, `standard_id`). `bloom_level`, `difficulty_level`, `mastery_threshold` columns already exist (mostly unused by any engine today) but are compatible with the brief's concept-level metadata. |
| `pal_concept_metadata` | Sidecar over `lms_concept`. `mastery_gate` (float, default 0.70) is exactly the brief's D4 mastery threshold concept, per-concept, per-tenant overridable — reuse instead of re-inventing a threshold field. `bloom_ceiling`, `practice_ceiling` map to the brief's "stop practice at mastery" idea. |
| `pal_concept_relations` | `relation_type='requires'` edges are exactly the brief's D2 prerequisite graph, already schema'd (`from_concept_id`, `to_concept_id`, `mastery_gate` per edge). Currently sparsely populated (LLM-extracted from `semantic_intelligence`, quality varies) but the table itself needs no schema change. |
| `pal_misconception_library` + `pal_misconception_corrective` | The brief's "misconception" + "contrast pair" concept. `pal_misconception_corrective` already models a targeted corrective content item per misconception (CONTENT LAW C6: a misconception without an approved corrective may never be served) — this is close to (not identical to) the brief's D3 "contrast pair" (example + non-example); see gap in §J. |
| `pal_question_metadata` | Sidecar over `lms_question_master`. Already has `concept_ref_id`, `bloom_level`, `practice_level`, `difficulty_1_to_5`, `misconception_tags` (JSON, **question-level**), `quality_status`/`tagged_by` (draft/approved workflow). Reuse as the home for the brief's `item_type` field (new column, see §C) rather than creating a parallel question-tagging table. |
| `pal_learner_content_exposure` | The "never re-serve the same content" shown-set — reusable as-is for D1/D3 "don't repeat the same explanation/corrective." |
| `BktEngine` (`app/Services/PAL/Runtime/BktEngine.php`) | **NOT reused for v1 mastery** per the brief's explicit "no BKT in v1" instruction — see conflict §L.1. Available later if v1's simple rule needs upgrading (brief's own "upgrade only if pilot decisions look wrong" clause). |
| `PalEvidenceRepository` | Read-only evidence access (`responseSequences`, `conceptsForChapters`, `prerequisiteGraph`) — reuse as the read layer the new engine's diagnostic/decision code queries, instead of re-querying `lms_online_exam_answer` etc. directly. |
| `pal_recommendation_log` | Structurally close to what `eso_decision_log` needs (state-before, decision, action, outcome) but scoped to pedagogy/content recommendations specifically, with a fixed column set that doesn't fit D1–D5's `rule_fired`/`llm_instruction` shape — **not reused directly**, but its append-only/no-FK/audit design pattern is copied for `eso_decision_log` (see §D). |
| `PalApiAuth` / `pal.auth` middleware | Reuse as-is for every new Adaptive Learning Engine endpoint. |
| `AIOrchestrationService` / `ContentModelLlmClient` / `OpenAIService` | Reuse as the LLM transport for "Pal renders the engine's instruction" (§I) — do not build a new LLM client. |
| `chapter_master`, `subject`, `standard`, `sub_std_map` | Chapter 3 scoping — no change needed. |

---

## C. Existing tables that require modification

| Table | Change needed | Why |
|---|---|---|
| `answer_master` (MCQ options) | **Add nullable `misconception_id` column** (FK → `pal_misconception_library.id`, nullable = "generic error" per the brief). | This is the single highest-leverage schema gap. Today misconception tagging exists only at the *question* level (`pal_question_metadata.misconception_tags` JSON) — there is no way to say "option C is the misconception, option B is a generic slip." The brief's D3 (and Phase 9) requires exactly this: distractor→misconception mapping. Confirmed via migration inspection: no table anywhere carries a per-option misconception link. |
| `pal_question_metadata` | **Add `item_type` enum column** (`recall` \| `application` \| `transfer`), nullable, defaulting unset. | Confirmed via full-codebase grep: `item_type` does not exist anywhere in this schema. The closest existing field, `knowledge_type` (`factual`/`conceptual`/`procedural`/`metacognitive`), is Bloom's knowledge dimension, not a recall/application/transfer item classification — they are not interchangeable and neither can substitute for the other. |
| `pal_concept_metadata` | **Add K/A/S node fields**, OR (preferred, see §L.2) create a new lightweight child table `pal_concept_nodes` (`concept_id`, `node_type` enum `K`\|`A`\|`S`, `label`, `mastery_threshold`). | The brief's "K/A/S nodes" (Knowledge/Ability/Skill, each independently masterable) do not exist anywhere in this codebase under any name — confirmed by grep. `pal_concept_metadata` today is one row per concept, not per sub-node, so it cannot represent "this concept has a K3 node and an A2 node with separate mastery." |
| `chapter_master` (Chapter 3 row) | **No schema change** — but the row's `key_concepts` (JSON) field and its relationship to `lms_concept.chapter_id` must be verified/reconciled against `pal_chapter_alignment` before Phase 0 tagging starts (see conflict §L.3, the two-chapter-vocabulary problem). | Concepts may have been LLM-extracted against a different `chapter_id` than the one the question bank/`chapter_master` uses for "Chapter 3." This must be resolved per-chapter, not assumed. |

No existing table needs a **destructive** change (rename/drop/type-narrowing) anywhere in this
plan. All modifications above are additive nullable columns or new child tables.

---

## D. New tables required

| Table | Purpose | Notes |
|---|---|---|
| `eso_decision_log` | The brief's non-negotiable decision log (§6/Phase 7). Columns per brief: `id`, `student_id`, `concept_id`, `node_id` (nullable — some decisions are concept-level, some node-level), `state_snapshot` (JSON), `rule_fired` (string, e.g. `"D3: M2 flagged twice"`), `action` (string), `llm_instruction` (text, nullable — only D1/D2/D3 teaching actions produce one), `created_at`. Append-only, no update path (mirrors `pal_recommendation_log`'s design: no FKs to hot-path tables, indexed on `student_id`, `concept_id`, `created_at`). **Not the same table as `pal_recommendation_log`** — different shape, different purpose (audit trace of all 5 decision types vs. pedagogy/content recommendation outcome tracking) — see §L.4 for why they stay separate. |
| `learner_node_state` | The brief's per-student, per-node mastery state (§5/Phase 5). Columns: `id`, `student_id`, `node_id` (FK → the new node identity — see §L.2), `mastery_estimate` (decimal 0–1, default per D1's diagnostic-weighted rule), `attempts` (int), `hint_used_count` (int), `status` (enum `unseen`\|`learning`\|`mastered`\|`retained`\|`misconception_flagged`), `last_seen_at`, `next_review_at` (nullable, set only on mastery). Unique constraint `[student_id, node_id]`. **New table, not an extension of `pal_competencies`** (concept-grain, wrong shape) or `pal_concept_mastery` (BKT-owned, per §L.1 conflict) — see §L.5. |
| `pal_concept_nodes` (if §L.2 resolves this direction — recommended) | K/A/S node identity: `id`, `concept_id` (FK → `lms_concept`), `node_type` enum(`K`,`A`,`S`,`Prerequisite`,`Misconception`), `label`, `mastery_threshold` (nullable, falls back to `pal_concept_metadata.mastery_gate`), `sort_order`. This becomes the `node_id` that `learner_node_state.node_id` and `eso_decision_log.node_id` reference — the brief's spec explicitly says `node_id` is "K / A / S / Prerequisite / Misconception node from Concept Intelligence," i.e. one unified node identity space, which does not exist today (K/A/S doesn't exist; prerequisites are concept-to-concept edges in `pal_concept_relations`, not nodes; misconceptions are in `pal_misconception_library`). This table gives all five node kinds one addressable id. |
| `learning_pattern` (or a static enum, no table — recommended) | The brief's exactly-3 patterns (Declarative, Classification, Causal Model). Per Phase 8/18 ("no per-concept ESO YAML files... 3 patterns written once"), this is closer to a **PHP enum + one column on `lms_concept`** (`learning_pattern` string, nullable) than a full table — no admin CRUD is required in v1. Recommend: add `learning_pattern` column to `lms_concept` (or `pal_concept_metadata`), not a new table. |

Everything above is additive: no existing table is altered destructively, no existing FK is
changed, no existing row is deleted.

---

## E. Existing APIs that can be reused

| Existing endpoint / service | Reuse for |
|---|---|
| `pal.auth` middleware + JWT pattern (`routes/pal_api.php` conventions) | Every new Adaptive Learning Engine route — same tenant/ownership scoping, same header pattern, zero new auth code. |
| `App\Services\PAL\Runtime\PalEvidenceRepository` | Read layer for diagnostic scoring, prerequisite graph reads, response-sequence history — the new engine's D1–D5 logic reads through this rather than raw `DB::table()` calls. |
| `App\Services\PAL\Content\MisconceptionLibraryService::detectAndRoute()` | The detection half of D3 (exact-match / regex-match against `pal_misconception_library`) — reuse its matching logic; the brief's D3 "contrast pair + explain difference + retest" response shape needs new orchestration on top (see §F), but detection itself should not be reimplemented. |
| `App\Services\PAL\Content\VariantRouterService` | The "never re-serve the same content/format" rule — reusable for D1's "skip if known" and D3's "serve a fresh corrective," so the same content isn't repeated. |
| `App\Services\PAL\AI\AIOrchestrationService` / `ContentModelLlmClient` / `OpenAIService` | The LLM transport layer for "Pal renders the engine's instruction" (§I) — the new engine calls one of these with a constrained prompt; it does not open a new HTTP client to OpenRouter. |
| `GET /api/pal/workspace/{learnerId}` (`PalWorkspaceController`) | Chapter/subject enumeration for the student-facing entry point — reuse for "student enters Chapter 3" instead of re-querying `chapter_master`/`sub_std_map`. |
| `pal_content` data layer conventions (`lib/erp-client.ts` `buildSessionContext`/`createAuthHeaders`/`appendCommonParams`) | Base for the new frontend data-layer file — same session/auth/error-handling pattern as every other PAL data file. |

---

## F. New APIs required

All under `Route::prefix('api/pal/eso')->middleware('pal.auth')->group(...)` in a new
`routes/pal_eso_api.php` (kept separate from the sprawling `pal_api.php` for reviewability — see
§L.6), backed by a new `App\Http\Controllers\Api\PAL\EsoEngineController`:

| Method | Route | Purpose | Maps to |
|---|---|---|---|
| GET | `api/pal/eso/diagnostic/{learnerId}/{chapterId}` | Assemble the 5–8 diagnostic items per concept for chapter entry. | D1 entry |
| POST | `api/pal/eso/diagnostic/{learnerId}/{chapterId}/submit` | Score the diagnostic, initialize `learner_node_state` rows (double-weighted), return which nodes are skip-eligible. | D1 |
| GET | `api/pal/eso/next-action/{learnerId}/{conceptId}` | **The core resolver.** Reads `learner_node_state` + `pal_concept_relations` + `pal_misconception_library`, runs D1→D5 in sequence, returns `{action, node_id, rule_fired, llm_instruction}` and writes one `eso_decision_log` row. This is the "Resolved ESO" the brief describes — a decision *function*, not a stored artifact. | D1–D5 dispatcher |
| POST | `api/pal/eso/practice/{learnerId}/{nodeId}/attempt` | Record one practice attempt (correct/wrong, hint_used, guided/independent), update `mastery_estimate` (±0.2 clamped), evaluate D3 (misconception trigger) and D4 (mastery verdict) inline, write `eso_decision_log`. | D3, D4 |
| POST | `api/pal/eso/retrieval/{learnerId}/{nodeId}/check` | Deliver/score the 2–3 item delayed-retrieval check; on pass set `status=retained`; on fail re-loop only the failed node. | D5 |
| GET | `api/pal/eso/decision-log/{learnerId}/{conceptId}` | Teacher/parent-facing readable trace — surfaces `eso_decision_log` rows in the brief's "Mastery rule D4: application accuracy 52%..." plain-language form. | Phase 7 audit requirement |

No route touches `pal_api.php`'s existing ~135 endpoints — this is additive-only, matching the
brief's "do not break existing LMS flows."

---

## G. Existing frontend components that can be reused

| Component | Reuse for |
|---|---|
| `app/pal/_components/DiagnosticPanel.tsx` (`DiagnosticButton`/`DiagnosticModal`) | Near-literal match for the brief's D1 diagnostic step (already does mastered/partial/gap/prerequisite_gap classification with a mastery estimate and DOK/Bloom badges). Point its data layer at the new `eso/diagnostic/*` endpoints instead of `/lms/diagnostic-assessment`; UI/modal/question-renderer transfer as-is. |
| `ChapterRow`'s prerequisite-gate lock (`app/pal/page.tsx` + `fetchChapterGate`) | D2 prerequisite gating UI already exists (lock icon, "master these concepts first" messaging) — repoint to the new resolver's prerequisite-gate decision instead of `/lms/chapter-gate`. |
| `app/pal/new/_components/MisconceptionCard.tsx` | D3's "contrast pair" card — corrective-ladder card UI already built; reuse for showing example/non-example + "explain the difference" prompt. |
| `app/pal/_components/PracticePanel.tsx` (adaptive practice + spaced-repetition + practice history) | D4 (guided→independent practice) and D5 (retrieval scheduling) — the spaced-repetition bucket UI (Today/Tomorrow/This week/Next week) is a direct fit for D5's "3–5 days later" scheduling display. Repoint to new endpoints. |
| `app/pal/_components/StudentPicker.tsx` + `ViewAsBanner.tsx` + `pal-view-as.ts` | Any teacher-facing "review this student's Chapter 3 progress" screen. |
| `lib/erp-client.ts` conventions | Base for the new `app/pal/data/pal-eso.ts` data-layer file. |
| `app/pal/intelligence/page.tsx` presentational pieces (`RiskCard`-style panels) | Optional: a teacher decision-log view could reuse the panel/card visual language, fed from `eso/decision-log/*` instead of V4 intelligence endpoints. |

---

## H. New frontend components required

| Component | Why new |
|---|---|
| A single **concept learning-session screen** (`app/pal/eso/[chapterId]/[conceptId]/page.tsx` or similar) that sequences diagnostic → skip/teach → misconception correction → practice → mastery → retention as one guided flow. | Confirmed gap: today diagnostic, practice, and quiz are three independently-launched modals/pages from the chapter row, never sequenced into one journey. The brief's acceptance criteria (Phase 19) require a single traversable flow. |
| **In-app "teach this concept" content view** rendering the engine's `llm_instruction` via Pal. | Today "suggested content" is a list of external links (`contentUrl`), not an in-app explanation surface. The brief requires Pal to render the engine's constrained instruction conversationally in-flow. |
| **Misconception correction step** triggered inline (not a separate report). | Today misconceptions surface as a post-hoc report + "generate content" button producing external links — not an inline "you answered B, here's why that's a common mix-up, here's the correct case, explain the difference" interrupt. |

Everything else (diagnostic modal, practice modal, spaced-repetition view, prerequisite lock,
student picker) is reuse per §G — the new build surface is genuinely small: one orchestrating
screen plus one new content-rendering surface plus one new misconception-interrupt surface, all
composed from existing modal/card components.

---

## I. Existing Pal integration that can be reused

The brief requires "Pal renders, the engine decides" — a constrained-instruction pattern. Two
existing pieces are directly reusable, one is not:

- **Reusable transport**: `AIOrchestrationService::callAI()` / `ContentModelLlmClient::json()` /
  `OpenAIService::generateContent()` — all three already call an OpenRouter-backed model given a
  prompt and return text/JSON. The new engine's "send a constrained instruction to Pal" step is a
  new prompt template through one of these existing clients, not a new HTTP integration.
  Recommend `ContentModelLlmClient` (most mature: provider-chain key resolution, JSON-from-prose
  extraction, response caching by input fingerprint) over `AIOrchestrationService` (hardcodes
  `gpt-4o`, ignores its own config) or the older `OpenAIService` (deepseek, used only by the
  legacy misconception-content path).
- **Confirmed existing discipline to build on**: across every "what's next" engine inspected
  (`RecommendationEngine`, `PedagogySelectorEngine`, `VariantRouterService`,
  `MisconceptionLibraryService::detectAndRoute()`), selection is **already** 100% rule-based —
  the LLM is invoked only after a target (concept/misconception/content id) has been chosen by
  deterministic code, purely to generate explanatory prose for that already-chosen target. This
  is exactly the brief's "engine decides WHAT, LLM decides HOW" rule — **it is already the
  codebase's convention**, not something to newly enforce against resistance. The new engine
  should follow the same shape: D1–D5 resolve a `{node_id, action}` in PHP, then a single new
  prompt-builder function turns that into the "Teach K4, minimal, one worked example..." text
  handed to the LLM client.
- **Not reusable as a "Pal" conversational tutor**: the app-wide `ChatbotPanel.tsx` /
  `conversational-ai-core` assistant has no PAL-specific tool today and is not wired into any PAL
  page (`usePageAiContext()` is never called under `app/pal/`). Building a full conversational
  tutor UI is out of scope per the brief (Phase 18: "No new student UI... Pal Quiz is the
  vehicle") — Pal's role here is realized as **rendered instruction text/content**, not a chat
  turn, so this gap does not block v1.

---

## J. Gaps between the current system and the Developer Brief

1. **K/A/S nodes do not exist under any name.** Confirmed by full-repo grep. The nearest
   existing concept (Bloom's `knowledge_type`: factual/conceptual/procedural/metacognitive) is a
   different taxonomy and cannot substitute. Requires new modeling (§D, §L.2).
2. **No distractor-to-misconception mapping.** `answer_master` (MCQ options) has no
   `misconception_id` column anywhere; misconception tagging today stops at the question level.
   This is Phase 0/Phase 9's "highest-leverage task" per the brief, and it is a genuine gap, not
   an oversight — confirmed no table anywhere carries it.
3. **No `item_type` (recall/application/transfer) classification anywhere.** Confirmed by
   full-repo grep — the brief's Phase 9 tagging requirement is entirely new.
4. **Two disjoint chapter-id vocabularies for the same real syllabus**, bridged only by
   `pal_chapter_alignment` (a name-matched, mostly `status='proposed'` table, per its own
   migration doc comment: *"Joining on chapter_id therefore returns nothing... 321 of 321 content
   rows and 26 of 26 questions land on a chapter with no concepts"* for one measured subject).
   Chapter 3 "Metals and Non-metals" must be checked against this table before Phase 0 tagging
   starts, or concepts may be extracted against a `chapter_id` the question bank never uses (see
   §L.3 — this is a blocking data-quality question, not a code gap).
5. **No unified "resolve the next action" decision pipeline.** The closest existing analogs
   (`BloomLadderService::evaluate()`, `VariantRouterService::nextVariant()`,
   `MisconceptionLibraryService::detectAndRoute()`) are three independently-invoked services, each
   owning one stage's logic, never composed into one D1→D5 sequential resolver. The brief's core
   deliverable — a single "Resolved ESO" function — is new orchestration code, even though most of
   its ingredients (mastery data, prerequisite edges, misconception detection, content routing)
   already exist in some form.
6. **No decision log matching the brief's shape.** `pal_recommendation_log` is close in spirit
   (state-before/decision/outcome, append-only, audit-oriented) but scoped specifically to
   pedagogy→content recommendations, with a fixed schema that doesn't carry `rule_fired`
   (D1…D5 + sub-rule) or `llm_instruction` verbatim. A new `eso_decision_log` table is required
   (§D), though the *design pattern* (append-only, no hot-path FKs) is directly borrowed from it.
7. **No student-facing "why" trace.** The one existing rule-resolution trace
   (`ResolvedEvidence`/`PedagogyRuleTable` in `app/pal/pedagogy-engine/page.tsx`) is a
   curriculum-authoring/debug view, not something a parent or teacher can see today. Phase 7's
   "readable trace for parents/teachers" is a new frontend surface (§H) even though the backing
   data (`eso_decision_log`) is new but straightforward.
8. **Mastery formula mismatch**: the brief mandates a simple ±0.2 clamped update rule and
   explicitly forbids BKT in v1; the codebase's most-current mastery engine (`BktEngine`) is a
   real BKT implementation the "content intelligence" generation is actively built around. This
   is a direct conflict requiring an explicit decision — see §L.1.
9. **Two competing spaced-repetition interval schemes already coexist** in
   `RecommendationEngine` (a 4-tier table and, separately, a 6-tier table used by a different
   method in the same class), plus a third, config-driven `review_interval_days` in
   `BktEngine::band()`. The brief's D5 (fixed 3–5 day window, not mastery-banded intervals) is
   simpler than all three and should not attempt to reconcile them — it needs its own fixed rule,
   independent of `RecommendationEngine`'s interval tables (see §L.1, same root cause: v1 must
   stay simple and not inherit the more sophisticated generation's complexity).
10. **`Models.php` autoload workaround.** The 24 core `pal_*` models in
    `app/Models/PAL/Models.php` are not PSR-4 autoloadable — they load only via a `require_once`
    in `PALServiceProvider::register()`. New engine code must either live inside normal
    Laravel request/CLI lifecycle (safe, providers always boot first) or avoid depending on those
    24 classes from an isolated script/early-migration context. Not a blocker, just a known trap
    to avoid re-triggering.

---

## K. Exact mapping — Developer Brief → existing codebase

| Brief term | Existing codebase equivalent | Status |
|---|---|---|
| Concept Intelligence | `lms_concept` + `pal_concept_metadata` + `pal_concept_relations` + `pal_misconception_library` | EXISTS (fragmented across 4 tables, functional) |
| K/A/S nodes | — | **NEW** (§D `pal_concept_nodes`) |
| Prerequisites | `pal_concept_relations` (`relation_type='requires'`) | EXISTS, REUSE (sparse data — Phase 0 teacher validation applies here) |
| Misconceptions | `pal_misconception_library` + `pal_misconception_corrective` | EXISTS, REUSE |
| Distractor→misconception mapping | — | **NEW** (`answer_master.misconception_id`) |
| Learner State (mastery, attempts, status per node) | `pal_competencies` (concept-grain, wrong shape) / `pal_concept_mastery` (BKT-owned) | **NEW** (`learner_node_state`) — neither existing table fits, see §L.5 |
| Learning ESO resolver | `BloomLadderService`/`VariantRouterService`/`MisconceptionLibraryService` (3 separate stage-owners) | **NEW** orchestration (`EsoEngineController` + a policy service), reusing the 3 above as ingredients |
| D1 Skip-if-known | `BloomLadderService::evaluate()` ceiling/gate logic (partial analog, different grain) | **NEW** (D1-specific diagnostic + threshold, simple rule per §L.1) |
| D2 Prerequisite gate | `LearnerStateEngine::getConceptDependencies()` — literal stub, `return [];` | **NEW** (real gate against `pal_concept_relations`) |
| D3 Misconception response | `MisconceptionLibraryService::detectAndRoute()` (detection ✓) + `pal_misconception_corrective` (single corrective, not explicit example/non-example contrast pair) | **MODIFY/NEW** — reuse detection, add contrast-pair response shape |
| D4 Practice gating + mastery verdict | `BloomLadderService::checkRegression()` (demote/serve_misconception chain — different axis) | **NEW** (brief's specific K≥0.8 AND A≥0.7 AND no-critical-misconception rule) |
| D5 Delayed retrieval | `RecommendationEngine::getSpacedRepetitionSchedule()` (mastery-banded, 2 inconsistent tables) / `BktEngine::band()['review_interval_days']` (config-driven) | **NEW** (brief's fixed 3–5 day rule, deliberately simpler) |
| Decision log | `pal_recommendation_log` (different shape/scope) | **NEW** (`eso_decision_log`) |
| Learning Patterns (Declarative/Classification/Causal Model) | — | **NEW** (one column + enum, not a table — Phase 18) |
| Question bank tagging (concept_id, node_id, item_type) | `pal_question_metadata.concept_ref_id` ✓, `node_id` ✗, `item_type` ✗ | **MODIFY** (add `item_type`; `node_id` comes free once §D's node table exists) |
| Pal as constrained renderer | `AIOrchestrationService`/`ContentModelLlmClient` (transport ✓); selection-before-generation discipline already the codebase convention | REUSE transport, **NEW** prompt-builder |
| Pal Quiz frontend vehicle | `app/pal/exam`, `/result`, `DiagnosticPanel`, `PracticePanel` | EXISTS, REUSE (repoint data layer) |
| Bloom's/DOK (explicitly NOT runtime) | `pal_question_metadata.bloom_level`, `g_bloom` generated column — exist upstream in question generation, never read by a runtime decision today | **NOT REQUIRED IN V1** — matches brief; no change needed, just don't wire them into the new resolver |
| Deep knowledge tracing / IRT / BKT (explicitly NOT v1) | `BktEngine` (real BKT), `pal_question_metadata.irt_a/b/c` (real IRT fields) | **NOT REQUIRED IN V1** — exists, available for a documented future upgrade, must not be the v1 mastery source (§L.1) |

---

## L. Conflicts requiring a decision (per the brief's own instruction: document, do not silently choose)

### L.1 — Mastery formula: brief's simple rule vs. existing `BktEngine`
The brief mandates ±0.2 clamped, diagnostic-double-weighted, explicitly **not** BKT for v1. The
codebase's actively-developed "content intelligence" generation is built around a real BKT engine
(`BktEngine`, config-driven `p_init/p_transit/p_slip/p_guess`) fed by `PalEvidenceRepository`.
**Recommended resolution** (least disruptive, preserves brief's v1 behavior per the brief's own
tie-break rule): implement `learner_node_state.mastery_estimate` with the brief's simple rule, as
a **new, independent** computation — do not call `BktEngine` for v1 mastery, do not modify
`BktEngine`. `BktEngine` stays exactly as-is for whatever currently consumes it
(`CoherenceMapRepository`, `MasteryUpdater`). This is purely additive and reversible: if the pilot
later shows the simple rule under/over-crediting mastery, swapping the node-level formula for
`BktEngine` is a contained change (the brief itself anticipates this: "Refine later; do NOT build
BKT/deep tracing in v1").

### L.2 — Where do K/A/S nodes live?
Two options: (a) extend `pal_concept_metadata` with K/A/S fields directly on the concept row, or
(b) a new child table `pal_concept_nodes` (recommended in §D) giving each K/A/S/Prerequisite/
Misconception item its own row and id. **Recommendation: (b)**, because the brief's own
`learner_node_state.node_id` schema explicitly expects one addressable id per node ("K / A / S /
Prerequisite / Misconception node from Concept Intelligence") — a single concept row cannot hold
that. This needs confirmation before any migration is written, since it determines the shape of
`learner_node_state`, `eso_decision_log.node_id`, and the question-tagging `node_id` field.

### L.3 — Chapter 3 vocabulary alignment — RESOLVED (verified against live DB, read-only)
Good news: for this specific pilot chapter, the two-vocabulary problem does **not** apply.
`chapter_master.id = 1014` ("Metals and Non-metals", `subject_id=3975`, `standard_id=43`,
`sub_institute_id=1`) is the **same** `chapter_id` used directly by both `lms_concept`
(17 concept rows) and `lms_question_master` (220 questions) — no `pal_chapter_alignment` row
exists or is needed for chapter 1014. Verified counts (live DB, read-only, 2026-08-31):

- **17 concepts** on chapter 1014 (`lms_concept`), e.g. Physical/Chemical Properties of Metals
  and Non-metals, Reactivity Series, Ionic Compound Formation, Metallurgy, Extraction of Metals,
  Roasting/Calcination, Electrolytic Refining, Corrosion, Alloys, etc. — a good fit for the
  brief's "10–15 concepts" pilot scope (slightly over; Phase 0 teacher validation may trim it).
  Each row already has a `mastery_threshold` (0–100 scale, e.g. 95/90/85/80 per concept — **not**
  the brief's 0–1 scale, needs unit conversion when read into `pal_concept_metadata.mastery_gate`
  or the new node table).
- **13 of 17** concepts already have a `pal_concept_metadata` sidecar row; 4 do not yet.
- **18 `pal_concept_relations` edges** already touch these concepts (prerequisite graph exists,
  LLM-extracted — needs the brief's Phase 0 teacher validation, not fresh authoring).
- **42 `pal_misconception_library` rows** already scoped to `chapter_ref_id=1014` — strong
  existing misconception coverage to validate/reuse rather than author from scratch.
- **220 questions** on chapter 1014: **50 are MCQ (`question_type_id=1`), 170 are narrative
  free-text (`question_type_id=2`)**. D3's distractor-mapping mechanism only applies to the 50
  MCQs — the 170 narrative questions cannot carry an `answer_master.misconception_id` (no
  discrete options). Phase 0's "map distractors to misconceptions" scope for this chapter is
  therefore the 50 MCQs (~200 `answer_master` option rows), not all 220 questions.
- **0 of 220** questions have `lms_question_master.concept_id` populated — despite the column
  existing since May 2026, no question in this chapter is linked to a specific concept yet. This
  is real, unstarted Phase 0 work, not a schema gap.
- **50 of 220** questions already have a `pal_question_metadata` sidecar row (`chapter_ref_id`
  set) — partial tagging exists; concept-level (`concept_ref_id`) linkage within those 50 was not
  separately verified and should not be assumed complete.

**Operational finding, separate from alignment**: `database/migrations/2026_05_15_120342_create_lms_misconceptions_table.php`
has **never run** against this live database (`SELECT ... FROM migrations WHERE migration LIKE
'%lms_misconceptions%'` → 0 rows; querying the table itself throws "table doesn't exist"). This
is repo/DB drift, not a code gap — worth a separate migration-status audit before Phase 0 begins,
independent of the Adaptive Learning Engine work. **This codebase's `lms_misconceptions` table is
a dead reference** for this feature regardless — `pal_misconception_library` (confirmed populated,
42 rows for this chapter) is the live, actively-used misconception catalogue; the plan already
recommends building on it (§B), and this finding just confirms `lms_misconceptions` should be
ignored, not migrated-and-adopted.

**Safety note**: this database is production (`vivek_erp` at a live host; Laravel's own tinker
guard explicitly blocks `Schema::` introspection with "Database schema commands are blocked in
production"). No migration should be run against it without explicit, separate sign-off — see the
recommended sequence in §N, which assumes migrations are reviewed and run deliberately, not as an
automatic next step.

### L.4 — `eso_decision_log` vs. `pal_recommendation_log`: separate tables, not one extended
Recommendation: keep them separate (as stated in §D) rather than widening
`pal_recommendation_log`'s columns to fit D1–D5, because `pal_recommendation_log`'s own migration
doc comment explains its schema is deliberately narrow (pedagogy→content outcome tracking, meant
to train future calibration) — repurposing it risks breaking whatever already reads/writes it.
Flagging this explicitly so the choice is visible rather than assumed.

### L.5 — `learner_node_state` is new, not an extension of `pal_competencies`
`pal_competencies` is concept-grain (`learner_id`, `concept_id`, `mastery_score`), not node-grain,
and per §A generation (2)'s own dead-data problem, may not be safe to widen without risking
whatever (if anything) already depends on its current shape. `pal_concept_mastery` is explicitly
"owned exclusively by `BktEngine`" per its model's doc comment — writing the brief's simple-rule
mastery into it would corrupt BKT's own state. Recommendation: `learner_node_state` is a clean new
table, as stated in §D.

### L.6 — New route file vs. extending `pal_api.php`
`routes/pal_api.php` is already ~135 routes with at least one confirmed nesting bug (H5P and
Gamification sub-groups accidentally nested inside `new/administration`'s still-open closure,
producing live paths that don't match their own inline comments). Recommendation: put the new
engine's routes in a separate `routes/pal_eso_api.php`, included the same way, to avoid adding to
that file's complexity and risking a similar nesting mistake.

---

## M. Requirement classification summary

| # | Requirement (from brief) | Classification |
|---|---|---|
| 1 | Concept catalogue for Chapter 3 | EXISTS |
| 2 | Prerequisites data | EXISTS (REUSE, sparse — needs Phase 0 teacher validation) |
| 3 | Misconception catalogue | EXISTS (REUSE) |
| 4 | K/A/S nodes | NEW |
| 5 | Distractor→misconception mapping | NEW (MODIFY `answer_master`) |
| 6 | item_type (recall/application/transfer) | NEW (MODIFY `pal_question_metadata`) |
| 7 | learner_node_state | NEW |
| 8 | eso_decision_log | NEW |
| 9 | D1 skip-if-known | NEW (orchestration; ingredients exist) |
| 10 | D2 prerequisite gate | NEW (existing stub must be replaced) |
| 11 | D3 misconception response (contrast pair) | MODIFY/NEW (detection REUSE, response shape NEW) |
| 12 | D4 practice gating + mastery verdict | NEW |
| 13 | D5 delayed retrieval | NEW (deliberately simpler than existing spaced-repetition code) |
| 14 | Three learning patterns | NEW (one column, not a table) |
| 15 | Pal as constrained renderer (transport) | REUSE |
| 16 | Pal as constrained renderer (selection discipline) | EXISTS (already the codebase convention) |
| 17 | Frontend diagnostic UI | EXISTS (REUSE, repoint data layer) |
| 18 | Frontend practice/spaced-repetition UI | EXISTS (REUSE, repoint data layer) |
| 19 | Frontend prerequisite-gate UI | EXISTS (REUSE, repoint data layer) |
| 20 | Frontend single guided concept-flow screen | NEW |
| 21 | Frontend in-app "teach this concept" content view | NEW |
| 22 | Frontend inline misconception-correction step | NEW |
| 23 | Bloom's/DOK in runtime | NOT REQUIRED IN V1 |
| 24 | BKT/deep knowledge tracing/IRT as v1 mastery source | NOT REQUIRED IN V1 (exists, not used for v1 mastery — §L.1) |
| 25 | 15-pattern library | NOT REQUIRED IN V1 |
| 26 | New student UI beyond Pal Quiz vehicle | NOT REQUIRED IN V1 |
| 27 | Second chapter / rollout | NOT REQUIRED IN V1 |

---

## N. Recommended implementation sequence (once §L is resolved)

1. Verify Chapter 3 chapter-id alignment (§L.3) — one read-only query, blocks nothing else once done.
2. Migrations (additive only): `answer_master.misconception_id`, `pal_question_metadata.item_type`, `pal_concept_nodes` (new), `lms_concept.learning_pattern` (or `pal_concept_metadata`), `learner_node_state` (new), `eso_decision_log` (new).
3. `EsoPolicyService` (pure PHP, D1–D5 logic, unit-testable without HTTP) — the actual decision engine, built against `PalEvidenceRepository` + the new tables.
4. `EsoEngineController` + `routes/pal_eso_api.php`, behind `pal.auth`.
5. Pal instruction prompt-builder, through `ContentModelLlmClient`.
6. Automated tests (the brief's 10 scenarios) against `EsoPolicyService` directly — fast, no HTTP.
7. Frontend: new `pal-eso.ts` data layer, then the one new guided-flow screen composing existing `DiagnosticPanel`/`PracticePanel`/`MisconceptionCard` plus the two new components from §H.
8. Manual Chapter 3 end-to-end walkthrough.

This sequence keeps every step independently testable and reversible, touches no existing route,
and does not modify any engine (`BktEngine`, `RecommendationEngine`, `BloomLadderService`, etc.)
that other parts of the live system already depend on.
