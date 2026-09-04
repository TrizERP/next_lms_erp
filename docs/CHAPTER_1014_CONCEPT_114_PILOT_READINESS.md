# Chapter 1014, Concept 114 — Pilot Readiness Report

**Scope of this pass: Concept 114 only.** No other concept in Chapter 1014 was touched. The
other 19 zero/thin-MCQ nodes and 8 zero-MCQ concepts documented in
`docs/CHAPTER_1014_NODE_CONTENT_HEALTH_REPORT.md`, and the 23 items in
`docs/CHAPTER_1014_CONTENT_REVIEW_QUEUE.md`, remain exactly as they were — future
content-enrichment work, not part of this pilot.

No real student was enrolled or touched. Only synthetic student 283919 was used for every test
in this document. The real pilot was not started. D1-D5 was not redesigned.

---

## 1. S195 root cause

Node 195 (S114, "Skill/transfer: designing and evaluating an experimental protocol to
differentiate metals from non-metals") had exactly one tagged question (Q85139), and it was
narrative (`question_type_id = 2`) — zero MCQ-answerable content. `practiceItem()` returned
`null` for the node, so a student could reach S114's `teach` step but could never submit a
scorable attempt, meaning `attempts` could never advance past 0 and the node could never be
marked mastered. This was a content gap, not a code defect — D1-D5 was behaving correctly given
what it was fed.

## 2. S195 content solution

Searched all 50 MCQs in Chapter 1014 for one that genuinely assesses the S114 skill (combining
malleability + conductivity test results, accounting for exceptions like graphite). None existed
— the closest candidate, Q104529 ("show experimentally that zinc is more reactive than copper"),
tests reactivity-series procedure (correctly tagged to concept 119/node 200) and was **not**
re-tagged, per instruction.

No existing question could be safely converted in full — Q85139's part (a) ("how would you use
these tools to distinguish metal from non-metal") converts cleanly; part (b) ("assess the
usefulness/limitations of these tests") is genuinely evaluative and was **not** forced into an
MCQ. Q85139 itself was left untouched.

**Two new transfer-level MCQs were authored** (derived directly from Q85139's own hint-text
reasoning: hammer → malleability, circuit → conductivity, graphite as the stated exception) and
inserted:

- **Q150590** — brittle-but-conducting sample (the graphite trap): tests that conductivity alone
  is not sufficient evidence of "metal" when the malleability result disagrees.
- **Q150591** — liquid-but-malleable-and-conducting sample (the mercury/liquid-metal exception):
  tests that "liquid at room temperature" does not rule out "metal."

Both require combining **two** test outcomes — genuine transfer, not recall — matching the node's
own definition.

## 3. Question used/created

| ID | Type | Status | Role |
|---|---|---|---|
| 85139 | Narrative | Unchanged | Left as-is; not answerable by the engine, not deleted |
| 150590 | MCQ (new) | Created, approved | S114 practice/diagnostic item 1 |
| 150591 | MCQ (new) | Created, approved | S114 practice/diagnostic item 2 |

## 4. Final concept/node/item_type

Both new questions: `concept_ref_id = 114`, `node_id = 195`, `item_type = transfer`,
`quality_status = approved`, `sub_institute_id = 1` (matching every other real MCQ in this
chapter). No other node or concept was modified.

## 5. Misconception mappings

Reused **two existing approved** misconceptions where they genuinely fit; every other distractor
is `misconception_id = NULL` (no misconception was invented):

- **#141** (`physical_properties_of_non_metals_non_metals_never_conduct_electricity`, approved) —
  used on Q150590's "conductivity alone proves metal" distractor and again on Q150591's
  equivalent distractor (same underlying misconception, two different surface scenarios — useful
  for retest variety, mirroring how D3's retest pattern already works elsewhere in this concept).
- **#3671** (`physical_properties_of_metals_confuses_liquid_metal_with_low_melting_metal`,
  approved, already concept-114-scoped) — used on Q150591's "liquid state and low melting point
  are the same fact" distractor.
- Remaining distractors (implausible-reasoning options with no catalogued match) are `NULL`, per
  instruction not to over-map.

## 6. Browser test results

Real-browser Playwright suite (`eso_e2e.mjs`, real UI + real API + real DB, synthetic student
283919 only), re-run after the content fix: **30/30 checks pass.** Entry-point suite
(`entry_point_e2e.mjs`): **6/6 pass.**

## 7. D1 result

**PASS.** A perfect K114+A114 diagnostic still correctly makes both skip-eligible. S114's 2 new
MCQs are within `diagnosticItems()`'s per-node sampling budget, so in a perfect run both get
served and scored during diagnostic itself — S114 can now become skip-eligible directly from D1,
confirmed via the real decision log (`skip_instruction` rows for nodes 91, 92, and 195).

## 8. D3 result

**PASS, unaffected.** A114's misconception-detection → contrast-pair → clean-retest loop
(concept 114's existing D3 coverage, using K114/A114 content) still passes end-to-end,
`state_snapshot.misconception_id = 3670`, corrected row confirmed after clean retest.

## 9. D4 result

**PASS — the core fix.** With S114 answerable, `masteryVerdict()` is now organically reachable:
the real UI shows the "Mastered" heading with Knowledge/Application percentages, and the decision
log shows a `D4: ... mastered_stop_practice` row. This was previously impossible for any student.

## 10. D5 result

**PASS — including a second real defect found and fixed.** Verifying D4 end-to-end surfaced a
second, genuine bug (not content — a code defect): `scoreDiagnostic()` marked a node `mastered`
on a D1 skip but never set `next_review_at`, and `masteryVerdict()` only schedules retrieval for
nodes transitioning to mastered *at that moment* — so a node already mastered via D1 skip never
got a D5 retrieval check scheduled at all. This was unreachable before (S114's dead end meant
`masteryVerdict()` was never reached with every node already skip-mastered), so it was invisible
until the content fix made the full-skip path reachable. Per the explicit "unless a genuine
blocking defect is discovered" exception, this was fixed narrowly: `scoreDiagnostic()` now sets
`next_review_at` on the skip path too, mirroring what `masteryVerdict()` already does. No
threshold, mastery rule, or decision logic changed — only a missing scheduling side-effect was
added. Confirmed via the real UI + real decision log + real DB: all 3 nodes end mastered with a
real `next_review_at` 4 days out; the separate D5 retrieval scenario (retained + reloop) also
still passes exactly as before.

## 11. Live Pal result

**BLOCKED — external account/configuration**, honestly disclosed, not faked. Inspected
`config/pal_content_model.php`: `ContentModelLlmClient::json()` has no per-call token override
and always reads the shared, hardcoded `max_output_tokens = 0` (unbounded) — this config is
shared by other PAL Content Model features, so it was **not** changed. Called the real
`POST /api/pal/eso/render` endpoint (the actual student-facing path) with a real S114 teach
instruction: it returned `rendered: null, fallback_text: <the plain instruction>` at HTTP 200 —
the fallback worked correctly. Confirmed this was a genuine live provider round-trip (not a
missing-key short-circuit) by calling `ContentModelLlmClient::json()` directly: DeepSeek returned
a real HTTP 402 with body `{"error":{"message":"Insufficient Balance",...}}`. No API key or
secret was exposed. **This does not block the pilot**: the engine's plain, deterministic
instruction is what the brief requires to remain usable when the LLM is unavailable, and it does.
Topping up the provider account balance (external, no code change) is the actual fix; it is
outside this pass's authority.

## 12. Entry-point result

**PASS**, unaffected by this pass's changes — re-confirmed at 6/6 (see §6).

## 13. Measurement readiness

Unchanged from the prior pre-pilot pass, still **DONE/READY**: `docs/
CHAPTER_1014_PILOT_MEASUREMENT_PLAN.md` defines Arm A (current LMS flow) and Arm B (ESO adaptive
flow); all 5 metrics (time to mastery, mastery rate, retention @~7d, explanation volume,
misconception recurrence) are implemented in `PilotMetricsService` (12/12 tests passing); cohort/
arm assignment via `pal_pilot_enrollments` + `pal:pilot-enroll` (dry-run by default, never run
against real students).

## 14. Regression results

- `tests/Feature/Eso/` (41 tests: policy, authorization, pilot metrics, pilot enroll): **41/41
  pass**, including after both fixes in this pass.
- `tests/Feature/Pal/` (45 tests): **39 pass, 6 pre-existing failures** — all in
  `PalMisconceptionAuthTest` (422-vs-200/403 validation mismatch), confirmed unrelated to this
  pass and identical to the baseline before this session's changes. **Zero new failures.**
- `tsc --noEmit` (lms_k12): **clean.**
- `eslint app/pal/`: **1 pre-existing error + 3 pre-existing warnings**, all in files untouched
  this session (`coherence-map/page.tsx`, `intelligence/page.tsx`, `pal/page.tsx`'s pre-existing
  hook-dependency warning). **Zero new lint issues.**
- `next build`: clean production build.
- Playwright: `eso_e2e.mjs` 30/30, `entry_point_e2e.mjs` 6/6.

## 15. Remaining blockers

- **Live Pal LLM phrasing**: BLOCKED on provider account balance (external, non-code). Does not
  block the pilot — plain-instruction fallback is fully functional and always was the design's
  safety net for exactly this situation.
- Everything else that was in scope for "Concept 114 fully pilot-ready" is resolved.
- Explicitly **not** addressed, and correctly out of scope per this pass's instructions: the
  other 19 zero/thin-MCQ nodes, the other 8 zero-MCQ concepts, the 23 pending content-review
  items, and any chapter-wide taxonomy work.

## 16. Recommendation

# READY TO PILOT

Concept 114 (K91, A92, S195) is content-complete, D1-D5 organically reachable end-to-end
including full mastery and D5 retrieval scheduling, authorized and tested through the real
student entry point, and its 5-metric measurement scaffolding is implemented and tested. The one
open item (live LLM phrasing) is a non-blocking, honestly-disclosed external dependency with a
working fallback already in place.

**Still required before enrolling real students, outside this pass's authority:** a content-team
decision on the provider account balance / LLM budget (optional polish, not a hard blocker), and
the actual human decision to launch a 30-60 student pilot. This pass does not start that pilot.
