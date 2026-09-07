# Chapter 1014, Concept 114 — Adaptive Learning Engine: Real User Journey

Every route, component name, UI label, and API endpoint below is copied directly from the actual
Next.js (`d:/lms_k12`) and Laravel (`d:/next_lms_erp`) source — nothing here is invented or based
only on the Developer Brief. File references are given so every claim is traceable.

## 1. Purpose

A click-by-click walkthrough of the Adaptive Learning Engine as it actually exists today, for
Chapter 1014 ("Metals and Non-metals"), Concept 114 ("Physical Properties of Metals") — the
current pilot scope. Verified by running the real UI against the real API against the real
database with Playwright, not by reading code alone.

## 2. Prerequisites

- Laravel backend (`php artisan serve`, pointed at the shared `vivek_erp` DB) and the Next.js
  frontend (`npm run dev`, `d:/lms_k12`) both running.
- A staff/admin account (this walkthrough used a minted institute-admin session — see §16
  Troubleshooting for why the synthetic student can't use the real login form directly).

## 3. Test student

`student_id = 283919`, "ESO Pilot Test Student" — synthetic only, `tblstudent.email` /
`username` / `password` are all `NULL` for this row (confirmed in the DB), so it has **no real
login credentials** and cannot be driven through the real `/login` form itself. No real student
was used anywhere in this document.

## 4. Starting point

`http://localhost:3000/login` — the real login page (`app/login/page.tsx`). A staff/teacher
account with real credentials logs in here, is redirected to `/dashboard`, then navigates to the
PAL workspace and picks student 283919 via **"Review student"** (see §5 Step B). This is the same
"view as student" mechanism a real teacher reviewing a real student's progress would use.

## 5. Exact click-by-click journey

### A. Login
- **Route:** `/login`
- **Component:** `app/login/page.tsx`
- **What you see:** "Welcome back" — an email + password form (labels: "Email address",
  "Password"), a "Sign in" button.
- **Action:** enter real staff credentials, click **Sign in**.
- **API:** `POST /api/api-login` (`{ email, password, type: 'API' }`) — `AuthContext.login()`,
  `contexts/AuthContext.tsx:147`.
- **Result:** on success, a checkmark animation, then redirect to `/dashboard`.
- **ESO decision:** none — this predates the PAL workspace entirely.

### B. Reach the PAL workspace and select the test student
- **Navigation:** in the app's left sidebar menu, **LMS + PAL → Test → PAL** — the code's own
  comment names this exact path as "the legacy PAL workspace at `/pal`"
  (`app/components/DashboardShell.tsx:129`). Clicking it routes to `/pal`.
- **Route:** `/pal`
- **Component:** `app/pal/page.tsx` (`PalEntryPage`)
- **What you see:** because a staff/admin session has not yet picked a student, the page shows
  **`StudentPicker`** (`app/pal/_components/StudentPicker.tsx`): header "Select a student",
  subtext "Choose a grade, standard and division, pick a student, then review their PAL." Three
  cascading dropdowns labeled **Grade**, **Standard**, **Division**.
- **Action:** select Grade **SEC**, Standard **10**, Division **A** (283919's real enrollment:
  `standard_id=43` = "10", `academic_section.id=12` = "SEC", `division.id=9` = "A"). A searchable
  student list appears; search or scroll to **"ESO Pilot Test Student"**, click it to select
  (row highlights), then click **Review student** (the button's exact label for `audience:
  'Teacher'`, `StudentPicker.tsx:32`).
- **API:** the class list comes from `fetchClassStudents()` (`app/pal/data/pal-lookups.ts`).
- **Result:** the app now shows **`ViewAsBanner`** (you are viewing 283919's PAL as staff), and
  loads that student's PAL landing data.
- **ESO decision:** none.

### C. Find Chapter 1014
- **What you see:** `PalEntryPage` lists subjects as collapsible `SubjectCard`s. Click **Science**
  to expand it (chapter list is inside `subject.chapters`).
- **Chapter row:** **"Metals and Non-metals"** (`chapter.name` from the real chapter data).
- **API:** `fetchPalLanding()` (`app/pal/data/pal.ts`).

### D. Click Adaptive Learning
- **Component:** `app/pal/_components/AdaptiveLearningButton.tsx`, rendered inside `ChapterRow`
  (`app/pal/page.tsx:506`).
- **Button label:** **"Adaptive learning"** (with a Sparkles icon) — renders only if the chapter
  has ESO-ready concepts (a real content-readiness check, not decorative).
- **API (on mount, before the button is even clickable):** `GET
  /api/pal/eso/chapter-concepts/1014` (`EsoEngineController::chapterConcepts`,
  `routes/pal_eso_api.php`).
- **Action:** click **Adaptive learning**.

### E. Concept picker
- **What you see:** a modal — title **"Start Adaptive Learning"**, subtitle **"Pick a concept to
  work on"** (`AdaptiveLearningButton.tsx`'s `ConceptPickerModal`). Lists every ESO-ready concept
  in the chapter by name.

### F. Select Concept 114
- **Action:** click **"Physical Properties of Metals"** (`lms_concept.name` for id 114).
- **Navigation:** `router.push('/pal/eso?conceptId=114&learnerId=283919')` —
  `learnerId` comes from the `studentId` prop already resolved by the session/view-as-student
  flow, **never a value the student can hand-edit**; the backend's `pal.auth` middleware
  independently rejects a mismatched `learnerId` regardless.
- **Route:** `/pal/eso?conceptId=114&learnerId=283919`
- **Component:** `app/pal/eso/page.tsx` (`EsoConceptFlowPage` → `EsoConceptFlow`)
- **API:** `GET /api/pal/eso/next-action/283919/114` (`fetchNextAction`,
  `app/pal/data/pal-eso.ts:249`) — the single "what should this student see next" call, backed by
  `EsoPolicyService::nextAction()`.

From here the page renders one of several steps based on `action.action` — see §7-§12 below.

## 6. Student view vs. System view

Every screen from here on has two parallel layers, deliberately kept separate in the code
(`EsoPolicyService`'s docblock: "the engine decides WHAT/WHETHER to teach; `EsoPalRenderer` ...
only phrases what this class already decided"):

**STUDENT VIEW** — the actual card/heading/button text rendered in `app/pal/eso/page.tsx`.
**SYSTEM VIEW** — the D1-D5 rule and `eso_decision_log` row that produced it.

## 7. D1 journey — diagnostic + skip

**STUDENT VIEW**
- Card title: **"Quick diagnostic"**. Subtitle: "A few questions to find out what you already
  know, so we can skip it." (`DiagnosticStep`, `app/pal/eso/page.tsx:222`)
- Each question shows a node-type badge (K/A/S), the question text, and radio options.
- Button: **"Submit diagnostic"**.
- **API:** `GET /api/pal/eso/diagnostic/283919/114` to load, `POST
  /api/pal/eso/diagnostic/283919/114/submit` to submit (`{ responses: [{node_id,
  answer_master_id}] }`) — correctness is always resolved server-side, the client never sends a
  "correct" flag.

**SYSTEM VIEW**
- Backend: `EsoPolicyService::scoreDiagnostic()`. Weight 2.0 per response; `mastery_estimate`
  clamps to [0,1]; `SKIP_THRESHOLD = 0.80`.
- On a perfect diagnostic, K114 (node 91) and A114 (node 92) both reach `mastery_estimate = 0.80`
  → `skip = true` → `eso_decision_log` rows: `rule_fired = "D1: node mastery 0.80 >= 0.80,
  skip-eligible"`, `action = skip_instruction`.
- S114 (node 195) has exactly 2 tagged MCQs (see §17); if both are sampled into the same
  diagnostic and answered correctly, it also reaches skip-eligibility directly from D1 — verified
  live (see §16).
- If a node does **not** skip, `nextAction()` next call routes to `teach` (first exposure) or
  `practice`.

## 8. D2 journey — prerequisite gate

**Concept 114 has no prerequisite relation.** Checked directly: `pal_concept_relations` has zero
rows with `from_concept_id = 114 AND relation_type = 'requires'`. `EsoPolicyService::
prerequisiteGate()` therefore returns `null` immediately for this concept, and the
`PrerequisiteStep` UI (`app/pal/eso/page.tsx:276` — card title "A prerequisite needs work first")
**cannot be demonstrated for Concept 114** as currently authored. This is stated honestly rather
than staged with a fabricated relation — no database content was modified for this document.

## 9. D3 misconception journey (Q104560, real, verified)

**STUDENT VIEW**
1. On a weak A114 diagnostic, `nextAction()` routes to **"Practice"** (`TeachOrPracticeStep`,
   `action.action === 'practice'`).
2. The real UI eventually serves **Q104560** — the question text ends "...these metals are poor
   conductor of heat." — among A114's practice pool (random selection; the journey script
   reloaded until landing on it, matching real-world repeat-visit variability).
3. Student selects **"Lead and aluminium"** (`answer_master.id = 331685`) — the mapped wrong
   answer — and clicks **Submit**.
4. **API:** `POST /api/pal/eso/practice/283919/92/attempt` (`recordAttempt`).
5. Card title: **"Let's clear up a mix-up"** (`ContrastPairStep`, `app/pal/eso/page.tsx:466`). The
   corrective content (`action.contrastPair.body`) renders, plus either Pal's phrased text or the
   plain engine instruction (see §13).
6. Textarea prompt: "In your own words, what's the difference between the example and the
   non-example?" Student types an explanation, clicks **"I understand — retest me"**
   (`data-eso-ready-to-retest`).
7. A fresh practice question loads (`fetchPracticeItem`) — randomly sampled from A114's pool, so
   it can vary run to run (verified runs landed on Q104546 and, separately, Q104560 again).
8. Student answers correctly, clicks **Retest**.
9. State advances past `serve_contrast_pair` — misconception cleared.

**SYSTEM VIEW**
- `EsoPolicyService::recordAttempt()` resolves correctness from `answer_master_id`, then reads
  `answer_master.misconception_id` for that option — **3670**
  (`physical_properties_of_metals_all_metals_equally_good_heat_conductors`).
- `eso_decision_log` row: `rule_fired` names D3, `action = serve_contrast_pair`,
  `state_snapshot.misconception_id = 3670`.
- `llm_instruction` on that row is a non-empty, constrained string built by
  `EsoPalRenderer::contrastPairInstruction()` — never a generic "AI decided".
- On the clean retest: `eso_decision_log` row `action = misconception_corrected`.
- Verified live via `GET /api/pal/eso/decision-log/283919/114` in the same run.

## 10. D4 mastery journey

**STUDENT VIEW**
- Card title: **"Mastered"** (emerald, `CheckCircle2` icon), subtitle: "You've cleared this
  concept — practice stops here. A short review will show up in a few days to lock it in."
  (`MasteredStep`, `app/pal/eso/page.tsx:534`)
- Body shows **`Knowledge: {X}%`** and **`Application: {Y}%`** — `action.knowledgeMastery` /
  `action.applicationMastery`, rounded.

**SYSTEM VIEW**
- `EsoPolicyService::masteryVerdict()`: `kMastery` = average of K-node `mastery_estimate`s,
  `aMastery` = average of A-node `mastery_estimate`s. `KNOWLEDGE_MASTERY_THRESHOLD = 0.80`,
  `APPLICATION_MASTERY_THRESHOLD = 0.70`. Mastered = both thresholds met **and** no node has
  `status = misconception_flagged`.
- S-type node mastery is **not** part of this pass/fail gate (only K/A feed the verdict) — but
  every node must independently reach `isMastered()` for `nextAction()`'s per-node loop to even
  reach `masteryVerdict()` at all (see §17 for why S114 needed real content).
- `eso_decision_log` row: `rule_fired` names D4, `action = mastered_stop_practice`.
- **`learner_node_state` changes:** every not-yet-mastered node's `status` → `mastered`,
  `next_review_at` → `now() + RETRIEVAL_DELAY_DAYS` (4 days). Nodes already mastered via a D1
  skip get this same scheduling (see §17 — this specific path required a fix during this pilot
  pass, since it did not happen before).

## 11. D5 retention journey — Retained

**Test mechanism (no 3-day wait):** the existing test-only script
`eso_scenario.php mastered_due` directly seeds `learner_node_state` with `status = 'mastered'`
and `next_review_at = now()->subDay()` for every node of concept 114, for student 283919 only —
a safe, already-established convention. **This does not modify D1-D5 logic or any real student's
data.**

**STUDENT VIEW**
- Re-entering via the real entry point, `nextAction()` immediately (no waiting) returns
  `retrieval_due` because a mastered node's `next_review_at` has passed.
- Card title: **"Quick review"**, subtitle: "A short check to make sure this is still solid a few
  days later." (`RetrievalDueStep`, `app/pal/eso/page.tsx:616`)
- Student answers all items correctly, clicks **Submit**.
- Result card title: **"Retained"** (emerald), subtitle: "You still had it a few days later —
  this is locked in." Button: **"Continue"**.

**SYSTEM VIEW**
- **API:** `GET /api/pal/eso/retrieval-items/283919/{nodeId}` to load, `POST
  /api/pal/eso/retrieval/283919/{nodeId}/check` to submit.
- `eso_decision_log`: `rule_fired` names D5, `action = retrieval_due`, then on success `action =
  retained`.
- `learner_node_state.status → retained`.

## 12. D5 re-loop journey (real, verified)

**STUDENT VIEW**
- After K114 is confirmed Retained and the student clicks **Continue**, A114's own
  `retrieval_due` surfaces next (each node is scheduled independently).
- Student deliberately answers the **first** item wrong (any non-correct option), the rest
  correctly, and clicks **Submit**.
- Result card title: **"Let's revisit this"** (amber), subtitle: "This one slipped — just this
  part re-loops, nothing else in the chapter is affected."

**SYSTEM VIEW**
- `EsoPolicyService`: a failed retrieval check sets **only that node's** `status` back to
  `learning` (`reloop_node`) — the other node(s) already marked `retained`/`mastered` are
  untouched.
- `eso_decision_log`: `rule_fired` names D5, `action = reloop_node`.
- Verified via the real DB (`learner_node_state`): K114 stayed `retained`, A114 alone became
  `learning`.

## 13. Pal behavior

- **ESO decides WHAT/WHETHER** — `EsoPolicyService` never calls an LLM (confirmed by its own
  class docblock: "It never calls an LLM"). Every `llm_instruction` string stored in
  `eso_decision_log` is deterministic template text built by static methods on
  `EsoPalRenderer` (`teachInstruction()`, `contrastPairInstruction()`) — pure string assembly, no
  network call.
- **Pal decides HOW** — the *only* LLM call in this entire feature is `EsoPalRenderer::render()`,
  invoked from the frontend via `usePalRendering()` (`app/pal/eso/page.tsx:299`), which calls
  `POST /api/pal/eso/render` (`{ learner_id, instruction }`). Its system prompt explicitly
  forbids the model from choosing content — only rephrasing.
- **Fallback, verified live this session:** with the shared PAL Content Model LLM config
  untouched (`config/pal_content_model.php`), the real `/api/pal/eso/render` endpoint returned
  `{ rendered: null, fallback_text: <the plain instruction> }` at HTTP 200. Confirmed via a
  direct `ContentModelLlmClient::json()` call that this was a genuine live provider round-trip:
  DeepSeek returned a real HTTP 402 `{"error":{"message":"Insufficient Balance", ...}}`. **This
  is an external account-balance blocker, not a code defect** — the plain-instruction fallback
  the brief requires is confirmed working. No secret or API key was exposed in any test.
- The UI always shows *something* immediately: `usePalRendering()` initializes its rendered text
  to the plain instruction and only upgrades it if the LLM call later succeeds — a student is
  never blocked waiting on Pal.

## 14. Decision log mapping (this journey, as actually observed)

```
RUN 1 — full mastery (perfect diagnostic):
  D1  skip_instruction     node 91 (K114)
  D1  skip_instruction     node 92 (A114)
  D1  skip_instruction     node 195 (S114)
  D4  mastered_stop_practice

RUN 2 — misconception (Q104560):
  D1  needs_instruction    node 92 (A114, weak)
  D4  practice             (first exposure / continued practice on A114)
  D3  serve_contrast_pair  (misconception_id = 3670)
  D3  misconception_corrected  (after the clean retest)

RUN 3a — retention, retained:
  D5  retrieval_due        node 91 (K114)
  D5  retained              node 91 (K114)

RUN 3b — retention, re-loop:
  D5  retrieval_due        node 91 (K114)
  D5  retained              node 91 (K114)
  D5  retrieval_due        node 92 (A114)
  D5  reloop_node           node 92 (A114)   -- node 91 unaffected
```

Every `rule_fired` value in the real decision log begins with `D1`-`D5` — never a generic "AI
decided" string (`EsoPolicyService`'s own design constraint, verified via the decision-log API in
every run above).

## 15. APIs involved

| Purpose | Endpoint | Frontend caller |
|---|---|---|
| Chapter → ESO-ready concepts | `GET /api/pal/eso/chapter-concepts/{chapterId}` | `fetchChapterConcepts` |
| What's next | `GET /api/pal/eso/next-action/{learnerId}/{conceptId}` | `fetchNextAction` |
| Diagnostic items | `GET /api/pal/eso/diagnostic/{learnerId}/{conceptId}` | `fetchDiagnosticItems` |
| Submit diagnostic | `POST /api/pal/eso/diagnostic/{learnerId}/{conceptId}/submit` | `submitDiagnostic` |
| Practice item | `GET /api/pal/eso/practice-item/{learnerId}/{nodeId}` | `fetchPracticeItem` |
| Record attempt | `POST /api/pal/eso/practice/{learnerId}/{nodeId}/attempt` | `recordAttempt` |
| Retrieval items | `GET /api/pal/eso/retrieval-items/{learnerId}/{nodeId}` | `fetchRetrievalItems` |
| Retrieval check | `POST /api/pal/eso/retrieval/{learnerId}/{nodeId}/check` | `submitRetrievalCheck` |
| Decision log | `GET /api/pal/eso/decision-log/{learnerId}/{conceptId}` | `fetchDecisionLog` |
| Pal rendering | `POST /api/pal/eso/render` | `renderInstruction` |

All defined in `routes/pal_eso_api.php`, implemented in `EsoEngineController` +
`EsoPolicyService` + `EsoPalRenderer`, all mapped 1:1 in `app/pal/data/pal-eso.ts`.

## 16. Database state changes (this journey)

- `learner_node_state` — one row per (student, node): `mastery_estimate`, `status`
  (`unseen`/`learning`/`mastered`/`retained`/`misconception_flagged`), `attempts`,
  `consecutive_correct`, `next_review_at`.
- `eso_decision_log` — one append-only row per decision, as tabulated in §14.
- No other table is written by this journey (question/answer/misconception content is read-only
  during a learning session).

## 17. Expected final state (verified live this session)

- RUN 1: all 3 nodes (91, 92, 195) `status = mastered`, each with a real `next_review_at`
  ~4 days out.
- RUN 2: A114's misconception flag cleared; `misconception_corrected` logged.
- RUN 3a: K114 `status = retained`.
- RUN 3b: K114 stays `retained`; A114 alone becomes `learning` again.

## 18. Known limitations

- **The real `/login` form cannot be used for student 283919** — it has no email/username/
  password in `tblstudent`. This journey uses a staff/admin session's "view as student" flow
  (§5.B), which is how a real teacher reviewing a real student's progress already works — it does
  not bypass `pal.auth`, the API, or the database; only the login *form* is not exercised for the
  synthetic student.
- **D2 (prerequisite gate) cannot be demonstrated for Concept 114** — it has no authored
  prerequisite relation (§8). Demonstrating it would require either picking a different concept
  that does have one, or authoring a new relation — out of scope for this pilot pass.
- **S114 (node 195) has only 2 tagged MCQs** (added this pilot pass to resolve a prior content
  gap — see `docs/CHAPTER_1014_CONCEPT_114_PILOT_READINESS.md`). With only 2 items, repeated
  practice on this node will not have much variety; this is workable for a pilot but thinner than
  K91/A92.
- **Live Pal (LLM) phrasing is blocked on provider account balance** (§13) — the plain-instruction
  fallback is what a real student sees today, not LLM-phrased text. This is an external,
  non-code blocker.
- The local `php artisan serve` test instance is single-threaded and can be slow to answer the
  ~17-chapters'-worth of simultaneous per-chapter requests the PAL landing page fires on load —
  purely a test-environment artifact of this dev setup, not a production behavior (a real
  PHP-FPM/nginx deployment serves requests concurrently).
- Chapter 1014 has 19 other nodes across 8 other concepts with little or no MCQ content (see
  `docs/CHAPTER_1014_NODE_CONTENT_HEALTH_REPORT.md`) and 23 questions with no content-team
  tagging decision yet (`docs/CHAPTER_1014_CONTENT_REVIEW_QUEUE.md`) — out of scope for this
  Concept-114-only pilot, mentioned here only so this document isn't read as claiming the whole
  chapter is ready.

## 19. Screenshot evidence

Captured by the real-browser Playwright run that produced every result in this document (24/24
checks passed, all 4 runs starting from `/pal`, never `/pal/eso?...` typed directly). Saved
locally at `C:\Users\Asus\AppData\Local\Temp\claude\d--next-lms-erp\3334bed6-2c64-4616-867a-df02872df5f7\scratchpad\pw\journey_screenshots\`
— not committed to the repository (this project does not commit binary test-evidence files;
consistent with the rest of this pilot's Playwright evidence handling this session).

| # | State | File |
|---|---|---|
| 1 | PAL landing | `run1_01_pal_landing.png` |
| 2 | Chapter 1014 visible | `run1_02_chapter_1014_visible.png` |
| 3 | Adaptive Learning entry point | `run1_03_adaptive_learning_button.png` |
| 4 | Concept picker | `run1_04_concept_picker.png` |
| 5 | Diagnostic | `run1_05_diagnostic.png` |
| 6 | D1 skip result (Mastered — D1 skip itself has no dedicated screen; it's a decision-log event, see §14) | `run1_06_after_diagnostic.png`, `run1_08_mastered.png` |
| 7 | Practice | `run2_05_practice.png` |
| 8 | D3 misconception trigger (Q104560) | `run2_06_q104560_practice.png` |
| 9 | Contrast pair | `run2_07_contrast_pair.png` |
| 10 | Retest | `run2_08_retest.png` |
| 11 | Mastered | `run1_08_mastered.png` |
| 12 | Retrieval due | `run3a_05_retrieval_due.png`, `run3b_07_a114_retrieval_due.png` |
| 13 | Retained | `run3a_06_retained.png` |
| — | Re-loop ("Let's revisit this") | `run3b_08_reloop.png` |

28 screenshots total across all 4 runs.

## 20. Troubleshooting

- **"No PAL subjects found"** after picking a student: the student has no
  `tblstudent_enrollment` row for the currently-selected academic year in the header — switch
  the year, or (for 283919 specifically) confirm the test enrollment row still exists.
- **"Adaptive learning" button never appears / concept picker is empty:** either the chapter has
  no ESO-ready concepts (`GET /api/pal/eso/chapter-concepts/{chapterId}` returns an empty list —
  correct behavior, not a bug, for a chapter Phase 0 tagging hasn't reached), or (locally only)
  the single-threaded dev server is still processing the page's other simultaneous requests —
  wait a few more seconds before concluding it's broken.
- **Diagnostic/practice shows "No tagged practice item is available for this node yet":** the
  node genuinely has zero servable (approved, MCQ) content — check
  `docs/CHAPTER_1014_NODE_CONTENT_HEALTH_REPORT.md` for which nodes this currently affects.
- **Pal's phrased text never appears, only the plain instruction:** expected right now — see §13;
  check server logs / a direct `ContentModelLlmClient::json()` call for the real provider error
  before assuming it's a frontend bug.
- **Decision log looks empty for a concept:** confirm you're passing the right `conceptId` —
  `eso_decision_log` is scoped per concept, not global to the student.
