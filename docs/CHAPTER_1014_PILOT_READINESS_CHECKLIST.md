# Chapter 1014 — Adaptive Learning Engine Pilot Readiness Checklist

**Status as of this checklist: NOT READY TO START.** This document is a status snapshot and gap
list only — nothing here starts the pilot, and no second chapter is in scope.

---

## 1. Content coverage

**Status: Mostly ready, 23 questions pending content-team sign-off.**

- 197 of 220 Chapter 1014 questions (89.5%) carry a `concept_id` / `node_id` / `item_type` tag.
- The remaining 23 are enumerated with individual recommendations in
  `docs/CHAPTER_1014_CONTENT_REVIEW_QUEUE.md` — none is silently dropped, each has a stated
  reason and a decision the content team needs to make.
- **Gate for pilot start:** none, strictly — 89.5% coverage is workable for a pilot. But the
  review queue should be resolved (even if every item's decision is "leave untagged") so the
  content team has explicitly signed off on the gap rather than it being an unexamined hole.

## 2. Node (K/A/S) coverage

**Status: Ready.**

- All 17 concepts (114–130) now have at least one K (Knowledge) node; most also have an A
  (Ability) node; concept 114 additionally has an S (Skill/transfer) node.
- 32 `pal_concept_nodes` rows total.
- Not yet true: per-node question density varies a lot (concept 119's A-node alone has 23
  questions; several K-nodes have only 1–2). Thin nodes will exhaust their diagnostic/practice
  pool quickly for a student who needs repeated attempts — worth monitoring during the pilot,
  not necessarily a blocker.

## 3. Misconception coverage

**Status: Narrow but functional — MCQ-only, 11 of 50 chapter MCQs have a mapped distractor.**

- 8 distinct misconceptions are actively mapped to at least one distractor for this chapter (5
  authored/reused in the first 15-question pass: 3670, 3671, 3672 new, 141/142 reused; 3 more
  reused in the 205-question pass: 140, 148, 150).
- 23 `answer_master` rows carry a `misconception_id`, across 11 of the chapter's 50 MCQs.
- D3 (misconception response) can only ever fire on those 11 questions — the other 39 MCQs and
  all 170 narrative questions will always register a wrong answer as "generic error," which is
  correct behaviour (per the brief: "a generic error must remain a generic error") but means most
  students most of the time will not see the D3 contrast-pair flow. This is expected for a first
  pilot pass, not a defect — flagging so pilot metrics interpretation accounts for it.

## 4. Diagnostic readiness

**Status: Proven for concept 114; architecturally identical (not individually smoke-tested) for the other 16.**

- The diagnostic item-selection logic (`EsoPolicyService::diagnosticItems()`) is concept-agnostic
  — it pulls whatever's tagged for a concept's nodes, with no concept-114-specific code path.
- **Only concept 114 has been exercised end-to-end through the real browser UI** (this session's
  Playwright suite). Concepts 115–130 rely on the same code and now have tagged content, but
  nobody has actually loaded `/pal/eso?conceptId=115` (etc.) and watched it render.
- **Recommended before pilot:** a quick manual or scripted spot-check of 3–4 more concepts (pick
  ones with thin node coverage, e.g. 123 or 128, as the more likely failure case) to catch any
  concept-specific surprise before 30–60 real students hit it.

## 5. D1–D5 readiness

**Status: Ready.** All five decisions implemented, unit-tested (16 PHPUnit tests), and verified
against real production data through a real browser (26 Playwright checks, stable across 2 runs):
D1 skip-if-known, D2 prerequisite gate (unit-tested, not exercised in the Chapter 1014 Playwright
run since concept 114 has no prerequisite gating it), D3 misconception response + clean-retest
clearing, D4 multidimensional mastery verdict, D5 delayed retrieval (retained + reloop branches).
No changes made or needed this session — no pilot-blocking bug was found in D1–D5 itself.

## 6. Decision logging

**Status: Ready.** `eso_decision_log` is live and was verified end-to-end through the real
`/api/pal/eso/decision-log` endpoint during Playwright testing: every D1–D5 decision produced a
row with a plain-language `rule_fired` (never "AI decided"), the correct `action`, and — for
teaching/practice/misconception steps — a real `llm_instruction`.

## 7. Pal integration

**Status: Architecture verified; the actual LLM call itself was not exercised this session.**

- Verified: the engine never calls an LLM to decide what to teach (asserted directly in tests);
  `EsoPalRenderer` is the sole LLM call, isolated to one endpoint (`POST /api/pal/eso/render`);
  the frontend shows the plain instruction immediately and only upgrades to Pal's phrasing if the
  render call succeeds, with a graceful fallback if not.
- **Not verified this session:** an actual round-trip to the configured LLM provider (OpenRouter/
  DeepSeek via `ContentModelLlmClient`). No test in this pass exercised a live external API call —
  by design, since the D1–D5 tests specifically prove the decision path makes *no* LLM call, and
  the render path was reviewed architecturally rather than smoke-tested against a live key.
- **Required before pilot:** confirm a valid provider API key is configured for whichever
  environment the pilot runs against, and do at least one live render check so the first real
  student doesn't hit a silently-failing (if gracefully-falling-back) Pal response.

## 8. Automated tests

**Status: Ready.** `tests/Feature/Eso/EsoPolicyServiceTest.php` — 16 tests, 59 assertions, all
passing, covering all 10 of the Developer Brief's Phase 14 scenarios plus the D5-in-`nextAction()`
wiring found and fixed this session. `tsc --noEmit`, `eslint`, and `next build` all clean.

## 9. Playwright (real browser) validation

**Status: Ready, scoped to concept 114.** 26/26 checks passing against the real Next.js UI, the
real Laravel API, and the real production database (via a local Laravel instance pointed at the
same DB) — not mocks, not a direct-service-call shortcut. Stable across two independent runs. See
item 4 above for the "other 16 concepts not individually exercised" caveat.

## 10. Known unrelated failures (confirmed pre-existing, not pilot blockers)

- 6 pre-existing PHPUnit failures (`H5pFrontendTelemetryTest`, `PalMisconceptionAuthTest` ×5) —
  confirmed identical on the unmodified baseline via `git stash` comparison; unrelated to the
  Adaptive Learning Engine.
- One pre-existing browser console error ("require is not defined") from an unrelated CDN
  icon-font script (`@mdi/font`) — confirmed to occur on every route in the app, including the
  homepage, before this feature existed.

---

## What is still required before a 30–60 student pilot can begin

In rough priority order:

1. **Resolve the content review queue** (`CHAPTER_1014_CONTENT_REVIEW_QUEUE.md`) — or explicitly
   accept the 23-question gap as-is. Either is fine; an unexamined gap is not.
2. **Build a real entry point into `/pal/eso`.** Right now the only way to reach the adaptive flow
   is a manually-typed URL with `conceptId`/`learnerId` query params — there is no button or link
   from the existing PAL landing page (`app/pal/page.tsx`) or anywhere else in the product. This
   is a genuine, concrete gap for real students, not a nice-to-have. (Flagged, not built — this
   session was told not to add new engine features.)
3. **Confirm the Pal LLM provider is live and working** for the pilot environment (item 7 above).
4. **Decide the pilot's Arm A / Arm B setup.** The Developer Brief's Phase 2 calls for two arms
   (current LMS flow vs. ESO flow) with pre-registered success thresholds and the five metrics in
   its §7 (time to mastery, mastery rate, retention @ 7 days, explanation volume, misconception
   recurrence). None of that measurement/comparison scaffolding was built this session — only the
   engine and content for the ESO arm exist. Someone needs to decide how Arm A is defined and how
   the five metrics get collected before the pilot can be evaluated against the brief's own bar.
5. **Identify the actual pilot cohort** (which real students, which class/section) and confirm
   they're enrolled under a concept 114-tagged (or broader) standard/subject — this session used
   one synthetic test student (id 283919) for all validation, deliberately, and never touched a
   real learner's data.
6. **Spot-check 3–4 more concepts beyond 114** through the real UI (item 4 above) before trusting
   all 17 equally.

Nothing above requires a second chapter or engine changes — it is entirely content-review,
product-surface, measurement-plan, and pilot-logistics work.
