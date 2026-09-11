# PAL Loop & Universal Content Model — Findings Tracker

Findings from mapping the live PAL V4 Content Model dashboard against the 12-step PAL loop, and the Universal Content architecture review.

**Legend — Status**

| Status | Meaning |
|---|---|
| 🔴 URGENT — blocks PAL loop today | Stops the loop from producing a valid signal right now |
| 🟠 Needs Team Confirmation / Decision | Cannot proceed until a person confirms or decides |
| 🟥 CONFIRMED — code-verified | Verified directly in the repo, not hypothesised |
| 🟩 Done | Verified correct, no action needed |
| 🟪 Design Now / Build Later | Design the contract, do not implement yet |
| ⬜ Not Started | Accepted, queued |

**Legend — Owner Track**

- **Track A** — PAL / learning product
- **Track D** — Platform, deduplication, shared engines

---

## Verification log — 2026-09-08

Phase 1 items checked directly against the code in `d:\next_lms_erp` (Laravel API) and `d:\lms_k12` (Next.js frontend). No feature code was changed. Verdicts below; the detail is folded into each item's section as **Verified (2026-09-08)**.

| # | Claim as written | Verdict | What the code actually shows |
|---|---|---|---|
| 1 | Assessment Bank at 2.3%, blocks steps 2/6/11 | ✅ **Mechanism confirmed, cause restated** | The 2.3% metric is `assessment_rubrics` presence in `semantic_intelligence`. But the diagnostic never reads it — see #2. The gap is real; its consequence is different. |
| 2 | Diagnostic falls back to the Practice bank | ⚠️ **Refuted as written — worse in substance** | Diagnostic and practice both read a **third** source: `pal_question_metadata` filtered only by `quality_status = approved`. Neither dashboard bank is involved, and no calibration filter exists at all. |
| 9 | ULU L4 may be a second assessment store | ✅ **Resolved — answer (a)** | ULU reads the same `assessment_blueprint` / `assessment_rubrics` columns. One store, not two. Item can be closed. |
| 15 | Pedagogy Engine: 39 rules, 0 live; does the student flow call it? | ✅ **Confirmed — student flow bypasses it entirely** | The 39-rule engine's only caller is a read-only dashboard controller. The student flow uses a different service. Two pedagogy implementations. |
| 17 | ESO missing from the 6-tab New PAL bar | ⚠️ **Half wrong — asserted from a migration file, not the DB** | The bar IS DB-driven, and ESO genuinely had no row *at the time*. But the parent has 7 children, not 6; Framework is not among them (it hangs off a different parent); and Coherence Map does have a row. Measured 2026-09-08 — see the corrections under item 17. |
| 10 | KASBA ×3, one is dead code | ⚠️ **Partly refuted** | Three surfaces confirmed, but none is dead code and the claimed G2G 4th copy does not exist in this repo. One route is orphaned (unlinked), not dead. |
| 11 | Framework routes ×4 | ⚠️ **Refuted on re-inspection** | 4 route files, but 2 are already redirect shims to the canonical routes. Real duplication was copy-pasted page scaffolding, since removed. |
| 16 | Leaderboard duplicated LMS vs PAL | ✅ **Confirmed, and deeper than UI** | Two separate API surfaces and two separate table sets. |
| 21 | "Built but inert" across 4+ systems | ✅ **Confirmed for 3 of 4** | Assessment Bank, Pedagogy Engine, Leaderboard confirmed. KASBA is the weak leg — see #10. |

**~~Not verifiable from code alone~~ — measured 2026-09-08.** See **Estate measurement** below. Two of the assumptions carried through this tracker turn out to be wrong, and one of them changes the priority order.

---

## Estate measurement — 2026-09-08

Run against the dev database (`vivek_erp`) with the project's own read-only commands, `pal:content-coverage` and `pal:vocab-check`. I had recorded these numbers as "not verifiable from code alone" and then carried on for the rest of the session without measuring them, which was a mistake — they were one command away and they change the plan.

| | Total | Tagged | Approved |
|---|---|---|---|
| Questions (`lms_question_master`) | 62,487 | 2,209 (3.5%) | **226 (0.36%)** |
| Content (`content_master`) | 31,385 | 124 (0.4%) | **0** |
| Concepts (`lms_concept`) | 2,165 | 1,156 (53%) | — |

### The finding that reorders the backlog

**29,234 questions already have ≥30 responses and are IRT-eligible. Zero have been derived.**

`pal:derive-irt` has never been run. So the calibration gap behind **#1** and **#2** is *not* an authoring problem — the psychometric evidence already exists in `lms_online_exam_answer` for nearly 30,000 items, and one command turns it into calibrated metadata. I have been describing #1 throughout as needing "content authoring capacity"; that is wrong. It needs a command run, on data already collected.

That makes `pal:derive-irt` the single highest-leverage action left in this tracker, and it is not a decision — it is a scheduled job nobody has scheduled.

**But it will not produce 29,234 calibrated items, and the gap matters.** Dry runs (`--dry-run`, computes and writes nothing) on the first 5,000 eligible questions:

| Sample | Would write | Flagged REVISE (discrimination < 0.25) |
|---|---|---|
| 500 | 500 | 256 (51%) |
| 5,000 | 5,000 | **2,889 (58%)** |

Roughly **three in five eligible items do not discriminate between learners** — spec §7.1 stage 4 says REVISE before approving, and `scopeCalibrated()` gates at 0.30, higher still. So the realistic calibrated pool after a full run is on the order of **10–12k items, not 29k**.

Two caveats on that estimate: the command processes in id order rather than sampling randomly, so the rate may not hold across the whole estate; and the 5,000 sampled came only from tenants 195 and 72 — tenant 1, which holds the largest question estate (25,592), contributed none, so its answer history is either absent or ordered later.

**This is still overwhelmingly worth running** — it moves the calibrated pool from **0** to five figures. The point of measuring first is that "run derive-irt and the Assessment Bank problem is solved" would have been the wrong expectation to set: a majority of the estate's items will come back needing revision, and that is a content-quality workload, not a command.

### ⛔ The finding that reframes #1 entirely: the two pools are DISJOINT

### Final numbers after the full `pal:derive-irt` run

The command was run to completion (2026-09-08). It wrote psychometrics for **14,013 questions**, of which **8,064 (57.5%)** came back flagged REVISE — matching the 58% the dry runs predicted.

```
derived:                                14,013
calibrated (discrimination >= 0.30):     4,625
servable (approved):                       226
BOTH servable + calibrated:                  0
```

**And the reason the intersection is zero is a TENANT SPLIT:**

| | Tenant 1 | Tenant 72 | Tenant 195 |
|---|---|---|---|
| Approved (servable) | **226** | 0 | 0 |
| Calibrated | **0** | 3,296 | 1,329 |

One school has done the editorial approval work and has no answer history. Two other schools have the answer history and have approved nothing. They are **different institutions**, and the diagnostic scopes by tenant (`forTenant`), so no cross-tenant borrowing is possible or appropriate.

**That makes the remedy per-tenant, and much smaller than "fix the estate":**

- **Tenant 72** is the fastest path to a working calibrated diagnostic: it already has **3,296 calibrated items sitting at `draft`**. This is an approval workload in one school, not a content-authoring programme.
- **Tenant 1** cannot calibrate at all until its approved items are actually served and answered — no command changes that.

### The 29,234 vs 14,013 gap — traced, and it was a reporting bug

`pal:content-coverage` counted IRT eligibility straight off `lms_online_exam_answer` with no join to the question. `pal:derive-irt` joins `lms_question_master`, because it cannot derive psychometrics for a question that does not exist.

```
eligible by answers alone:                        29,234
of those with NO lms_question_master row:         15,221
remaining (questions that actually exist):        14,013
```

**15,221 question_ids clear the response threshold but the questions themselves are gone** — answers outliving their questions. `derive-irt` was right; the coverage report was **overstating what could be calibrated by 52%**, which is exactly the kind of number that gets planned against.

Fixed in `ContentCoverageCommand::irtEligibility()`: it now joins the question table, and reports the orphan count as a warning rather than silently filtering it — a two-to-one ratio of answers to deleted questions is itself worth someone's attention.

The corrected report reads:

```
questions with >= 30 responses: 14013 · already derived: 14013 · remaining: 0
⚠ 15221 more question_ids clear the response threshold but no longer exist
```

**`remaining: 0`** — derivation is now complete for every question in the estate that actually exists. There is no more calibration to extract from history; what remains is the tenant-split approval problem above.

---

Measured after `pal:derive-irt` had processed its first ~570 items:

```
servable (approved):                    226
calibrated (passes scopeCalibrated):    232
BOTH:                                     0

approved questions with ANY answer history:   0 of 226
```

**None of the 226 approved questions has ever been answered by anyone.** And every question that *does* have answer history — all 29,234 of them — sits at `draft`.

The two sets do not overlap, and structurally cannot: calibration is derived from response history, so a question nobody has answered can never be calibrated, and a question nobody has approved can never be served.

**This means running `pal:derive-irt` to completion will not produce a single servable calibrated item.** The command is still worth running — it calibrates the draft pool, which becomes valuable the moment those items are approved — but on its own it changes nothing the learner sees.

**So the Assessment Bank problem is not what this tracker has said it is.** It is not "2.3% coverage", and it is not "nobody ran the derivation job". It is:

> The items humans approved and the items students answered are **disjoint sets**.

Neither remedy is a command:

- **approve items that have history** — take some of the 29,234 answered-but-draft questions through review, or
- **collect response data on the approved 226** — serve them and wait.

The first is a review workload with a known shape (and ~58% of those items will come back flagged REVISE). The second takes a term.

**This vindicates the design choice in #2.** The diagnostic's calibration reporting was built to *prefer* calibrated items and *report* when it could not get them, rather than hard-filtering. Had it hard-filtered, the diagnostic would serve nothing at all today. Instead it serves approved items and reports `signal: uncalibrated` — which is exactly the truth, and exactly how this was found.

### The finding that limits everything content-related

**Zero content rows are approved.** `ContentMetadata::servable()` filters on `quality_status = approved`, so it currently matches **nothing**. Every content-serving path — the variant router, the reroute ladder, the corrective micro-lesson, and therefore the learning-purpose wiring added in #4 — has an empty pool today regardless of correctness.

Of 124 tagged content rows, all 124 sit at `draft`. This is an approval-workflow gap, not a tagging one.

### Other measurements worth recording

- **226 approved questions** is the entire servable diagnostic pool estate-wide, across 62,487 questions.
- **`concept_id` is effectively unpopulated** — 519 of 62,487 questions, 1 of 31,385 content rows. Confirms the comment in `QuestionMetadata::scopeForCurriculum()`: the layer routes on `chapter_ref_id` and would select almost nothing on a concept-only filter.
- **Misconception library: 3,662 rows, 6 approved.** C6 passes — every approved misconception has an approved corrective.
- **Vocabulary gate: 60 enum columns checked, all values registered.** The two vocabularies added this session (`learning_purposes`, `blueprint_categories`) are correctly registered and audited.

### Data-quality gate currently FAILS

`pal:vocab-check` exits FAIL on pre-existing violations, none of them introduced by this session's work:

- **61 rows where `practice_level` contradicts `bloom_level`** (e.g. `understand` at practice level 3, expected 2). The ladder routes on one axis while reporting on another for those rows.
- **3 C5 violations** — machine-written rows sitting in human-only statuses (2 `approved`, 1 `reviewed`, all `tagged_by = ai`). CONTENT LAW C5 exists to stop AI-authored rows reaching a servable status without human review, and 2 of them are in the 226-question servable pool.

**Neither is fixed here, deliberately, and the line is worth stating.** `pal:derive-irt` was run because it only *adds* derived psychometrics, explicitly leaves `quality_status` and human-authored fields untouched, and is re-runnable. Both violations below would instead **overwrite an editorial judgement a person made**, which is a different kind of action:

| Violation | Remedy | Why it is not mine to apply |
|---|---|---|
| 3 C5 rows | Demote to `draft` (the only non-human-only status below `reviewed`) | Demoting removes 2 items from the 226-question servable pool — it overrides someone's approval decision, and whether those items are genuinely fine is a content call |
| 61 bloom/ladder mismatches | Set `practice_level` from `bloom_level` via `PalVocabulary::practiceLevelForBloom()` | It is not established which side is wrong. Someone may have deliberately set the practice level, in which case the *bloom* tag is the error and this would overwrite the wrong column |

Both are small, both are mechanical once the direction is decided, and both make `pal:vocab-check` pass — which is the gate this estate is meant to hold.

---

## Summary index

| # | Item | Phase | Owner | Status |
|---|---|---|---|---|
| 1 | Calibrated Assessment Bank — approved and answered pools are DISJOINT | Phase 1 — URGENT | Track A | 🔴 ROOT CAUSE FOUND 2026-09-08 — not a coverage problem |
| 2 | Diagnostic/Mastery Check item source | Phase 1 — URGENT | Track A | ✅ FIXED 2026-09-08 — calibration gate + reporting shipped |
| 3 | Universal Learning Content Model (PAL is consumer, not owner) | Phase 2/3 | — | ✅ SIZED 2026-09-08 — see design doc; U3 first |
| 4 | Learning Purpose classification layer | Phase 2 | Track A | ✅ WIRED 2026-09-08 — awaiting authored data |
| 5 | Content versioning by curriculum / academic year | Phase 2 | Track A | ✅ BUILT 2026-09-08 — model in place, no writer yet |
| 6 | PAL-vs-Examination item isolation rule | Phase 2 | Track A | 🟠 BRIEF READY 2026-09-11 — zero exposure now, free to decide before tagging |
| 7 | Coverage vs. Attainment reporting | Phase 2/3 | Track A | ✅ API + UI 2026-09-08 — menu migration pending |
| 8 | Dual fitness-for-purpose metadata (PAL calibration vs. board compliance) | Phase 2 | Track A | ✅ BUILT 2026-09-08 — awaiting authoring |
| 9 | Verify: Unified Learning Units L4 "Assessment" vs. Calibrated Assessment Bank | Phase 1 — URGENT | Track A | 🟩 RESOLVED — one store, not two |
| 10 | KASBA — 2 backends, not 3 implementations | Phase 1 — URGENT | Track D | 🟠 DECISION BRIEF READY 2026-09-11 — my earlier recommendation was backwards |
| 11 | Framework routes duplicated across 4 folders | Phase 2 | Track D | ⚠️ REFUTED — already redirects; scaffold dedup done |
| 12 | Conversational AI is already correctly centralized (positive finding) | Phase 1 | Track D | 🟩 Done |
| 13 | AI stack packages need a real API surface for G2G to consume | Phase 2 | Track D | ✅ SIZED 2026-09-08 — premise corrected; A1 is decision-free |
| 14 | AI Tutor — context + governance layer | Phase 1 | Track A | 🟡 CONTEXT + PANEL 2026-09-08 — chat hand-off undecided |
| 15 | Pedagogy Engine — rollout from hardcoded defaults to genuinely rule-driven | Phase 1 — URGENT | Track A | 🟡 TIERS 1+4 LIVE + GOVERNED 2026-09-08 — 2/3/5 blocked |
| 16 | Leaderboard/Engagement duplicated between LMS and PAL Gamification | Phase 2 | Track D | 🟠 DECISION BRIEF READY 2026-09-11 — overlap is empty on both sides |
| 17 | ESO in the New PAL tab bar | Phase 1 | Track A | ✅ DONE — migration run, row + rights verified in DB |
| 18 | Learning Path — PAL loop step 4 surface | Phase 2 | Track A | ✅ BUILT + REACHABLE 2026-09-08 |
| 19 | CBSE Exam Blueprint generator | Phase 2/3 | Track A (home: Examination) | ✅ FEASIBILITY BUILT 2026-09-08 — paper gen blocked on item 6 |
| 20 | Naming decision: PAL → "LearnSense" | Not this week | Track A | 🟪 Pre-decided, not executed |
| 21 | Cross-cutting pattern: "built but inert" — check now RUN | Phase 1 — URGENT | Track A + D | ✅ AUDIT RUN 2026-09-11 — assessment_rubrics has no reader |
| 22 | ESO cross-domain universalization (Fees / Career / Teacher ESO) | Design Now / Build Later | Track A | 🟪 Design placeholder only |
| 23 | "Decision Engine" — verify it isn't Tier 1 renamed | Design Now / Build Later | Track A | ✅ ANSWERED 2026-09-11 — not Tier 1; duplicates ESO D2. Do not build |
| 24 | STRATEGIC CALL: K-12 proves ESO first, not G2G | Phase 2 | Track A | ⬜ Not Started |
| 25 | Worked ESO example #1 — "Definition of Integers" (Mathematics) | Phase 1 | Track A | 🟡 SCORING BUILT 2026-09-08 — content authoring outstanding |
| 26 | Worked ESO example #2 — "Observations Indicating a Chemical Reaction" (Science) | Phase 1 | Track A | ⬜ Not Started |
| 27 | AI Tutor governance rule: no direct answers without genuine attempts | Phase 1 | Track A | ✅ ENFORCED 2026-09-08 — served as data, see item 14 |
| 28 | Within-grade pacing | Design Now / Build Later | Track A | 🟠 BRIEF READY 2026-09-11 — already self-paced in prod; ratify or constrain |
| 29 | Universal Evidence & Confidence Framework — 3 scores, not 1 | Design Now / Build Later | Track D | 🟪 Design Now / Build Later |
| 30 | Career Intelligence confidence disaggregation — sequenced AFTER, not now | Phase 3+ | Track D | 🟪 Design placeholder only |
| 31 | Vision/camera Learning Coach — legal review required | Design Now / Build Later | Track D | 🟠 REVIEW BRIEF READY 2026-09-11 — awaiting counsel |
| 32 | `engagement_score` holds exam accuracy, and a live rule fires on it | Phase 1 — URGENT | Track A | 🔴 NEW 2026-09-08 — student-visible defect |
| 33 | PAL ownership check unreachable for API callers | Phase 1 | Track A | ✅ FIXED 2026-09-08 — PAL suite green |
| 34 | ESO D3 precedence was per-node, not concept-wide | Phase 1 | Track A | ✅ FIXED 2026-09-08 — ESO suite green |

---

## 1. Calibrated Assessment Bank coverage — 2.3%

- **Phase:** Phase 1 — URGENT
- **Depends on:** —
- **Owner:** Track A
- **Status:** 🔴 URGENT — blocks PAL loop today

**Description.** The live dashboard shows Concept Learning, Practice, and Misconception Library all at 100% coverage (86/86 chapters), but the Calibrated Assessment Bank sits at 2.3% (2/86 chapters). This single content type is load-bearing for THREE separate PAL loop steps: Adaptive Diagnostic (step 2), Mastery Check (step 6), and Mastery Re-Verification (step 11). Because steps 2/6/11 appear to be pulling from the 100%-populated Practice bank instead of the Assessment Bank, the loop "works" today — but the mastery signal it emits is not calibrated. That is a different and more subtle problem than "the step is broken."

**Action required.** This is *the* bottleneck in the PAL loop today — not a general "populate more content" backlog item. The loop cannot produce a valid mastery signal for ~98% of chapters until this is addressed. Escalate above routine content-population work.

**Verified (2026-09-08).** The metric is real and its definition is now pinned down. In `app/Services/PAL/ContentModel/ContentModelCoverageService.php` the four dashboard types are each computed as *"how many chapters have this section present in `semantic_intelligence`"*:

| Dashboard type | Backing column | Variable |
|---|---|---|
| Concept Learning (4 variants) | `concept.definition + evidence + knowledge` | hardcoded to `100.0` — every extracted concept has a definition, so this type is *always* reported as 100% by construction |
| Practice (5-level Bloom's ladder) | `assessment_blueprint` | `$withBlueprint` |
| Misconception Library | `misconceptions` | `$withMisconceptions` |
| **Assessment Bank (calibrated)** | **`assessment_rubrics`** | **`$withRubrics`** |

Two corrections to the item as written:

1. **Concept Learning's 100% is not evidence of anything.** It is a literal `'coverage_pct' => 100.0` with the comment *"Every extracted concept has a definition, so Type 1 is always projectable"*. Treating it as a populated-content signal alongside the other three overstates the estate.
2. **The 2.3% does not cause the loop failure described.** Steps 2/6/11 never query `assessment_rubrics` at all — see #2. So closing the 2.3% gap alone would **not** make the mastery signal calibrated. Both problems need fixing; they are independent.

Still true, and still the headline: there is no calibrated assessment bank to draw from for ~98% of chapters.

---

## 2. Confirm: is Diagnostic/Mastery Check currently using the Practice bank as a workaround?

- **Phase:** Phase 1 — URGENT
- **Depends on:** #1 Calibrated Assessment Bank coverage
- **Owner:** Track A
- **Status:** 🟥 ANSWERED — neither bank; a third, uncalibrated source

**Description.** If steps 2/6/11 are pulling from the 100%-populated Practice bank instead of the Assessment Bank, the loop appears to work end-to-end while the diagnostic/mastery signal is uncalibrated. Confirm which bank the code actually queries.

**Action required.** ~~Direct verification needed before the next demo.~~ Verification done — see below. This is now a confirmed silent validity problem, and the action moves to fixing item selection, not confirming it.

**Verified (2026-09-08).** The answer is *neither*. Trace:

- `routes/pal_eso_api.php:46` → `EsoEngineController::diagnostic()`
- `app/Http/Controllers/api/PAL/EsoEngineController.php:97` → `$this->policy->diagnosticItems($conceptId, $subInstituteId)`
- `app/Services/Eso/EsoPolicyService.php:381` → the actual query:

```php
$candidates = QuestionMetadata::forNode($node->id)
    ->forTenant($subInstituteId)
    ->servable()
    ->get(['question_id', 'item_type'])
    ->shuffle();
```

So the diagnostic draws from **`pal_question_metadata`** — the general tagged LMS question pool — not from `assessment_rubrics` (the dashboard's "Assessment Bank") and not from `assessment_blueprint` (the dashboard's "Practice"). The two dashboard metrics are simply not connected to the item-selection path at all.

Worse, `servable()` is the *only* quality gate, and it is (`app/Models/PAL/QuestionMetadata.php:61`):

```php
return $query->whereIn('quality_status', config('pal_content.servable_statuses', ['approved']));
```

That is an editorial approval flag. There is **no filter on difficulty, discrimination, or IRT calibration anywhere in the selection path** — the `difficulty_1_to_5` column is read by `practiceItem()` for ordering, but the diagnostic doesn't even do that; it calls `->shuffle()`. Item selection for the diagnostic is random among approved questions.

**Consequences, restated:**

1. The mastery signal is uncalibrated **for 100% of chapters, not 98%** — populating the Assessment Bank would not change this, because nothing reads it.
2. Diagnostic and practice share one pool with no separation, so #6's PAL-vs-Examination isolation rule has no enforcement point today.
3. This is the strongest instance of the #21 "built but inert" pattern: a calibrated bank is being *measured* on a dashboard while the consumer bypasses it entirely.

**Revised action.** Two separate fixes, in order: (a) point `diagnosticItems()` at the calibrated bank and apply a real calibration filter; (b) then populate that bank (#1). Doing (b) first delivers nothing.

### ✅ Fix (a) implemented — 2026-09-08

Part (a) is done. Part (b) — populating the bank — remains open as #1.

**What "calibrated" now means, in code.** `pal_question_metadata` already carried full psychometrics (`irt_a/b/c`, `discrimination_index`, `response_count`, `psychometrics_derived_at`), and `pal:derive-irt` already derives them from the ~2.4M graded responses in `lms_online_exam_answer`. Nothing needed inventing — the definition was assembled from thresholds already in `config/pal_content.php`, so calibration means one thing everywhere:

```php
// app/Models/PAL/QuestionMetadata.php — new scopeCalibrated()
->whereNotNull('psychometrics_derived_at')
->whereNotNull('discrimination_index')
->where('discrimination_index', '>=', $cfg['approve_above_discrimination'])  // 0.30
->where('response_count', '>=', $cfg['min_responses']);                      // 30
```

Requiring `psychometrics_derived_at` means a hand-typed discrimination index cannot pass for a derived one.

**Changes:**

| File | Change |
|---|---|
| `config/pal_content.php` | New `diagnostic.require_calibrated` policy switch, default `false` |
| `app/Models/PAL/QuestionMetadata.php` | New `scopeCalibrated()` |
| `app/Services/Eso/EsoPolicyService.php` | `diagnosticItems()` prefers calibrated items and orders them by difficulty instead of `->shuffle()`; each item now carries `calibrated`. New `diagnosticCalibration()` |
| `app/Http/Controllers/api/PAL/EsoEngineController.php` | Diagnostic response carries a `calibration` block |
| `tests/Feature/Eso/EsoPolicyServiceTest.php` | 5 new tests |

**Two deliberate design decisions, both worth challenging if the team disagrees:**

1. **It prefers calibrated items, it does not require them.** A hard gate would be the "correct" fix and would take the diagnostic offline for practically every concept on the live estate — trading a silent validity problem for a visible outage. Instead it serves calibrated items first, tops up with approved-only ones, and *labels every item*. `require_calibrated` flips to a hard gate per tenant once coverage is real.
2. **It reports the weakness rather than hiding it.** This is the actual fix for the silent-failure half of the problem. Every diagnostic response now carries:

   ```json
   "calibration": { "servable": 24, "calibrated": 0, "calibrated_pct": 0.0,
                    "signal": "uncalibrated", "require_calibrated": false }
   ```

   `signal` is `none` / `uncalibrated` / `partial` / `calibrated`. Before this, a diagnostic built entirely from uncalibrated items returned a full question set and a confident-looking score, indistinguishable in the response from a measured one. That indistinguishability was the real defect — the 2.3% number was only its symptom.

**Also fixed in passing:** calibrated items are now ordered by difficulty rather than shuffled. A diagnostic is trying to locate where a learner stops being able to answer; a random draw across a wide difficulty range measures that far less precisely than a spread does. (Uncalibrated items keep the shuffle — they have no trustworthy difficulty to order by.)

**Tests:** 5 added, all passing against the real DB. They pin the preference, the fallback, the hard-gate switch, the four `signal` states, and that psychometrics below either threshold do not count as calibrated.

```
./vendor/bin/phpunit tests/Feature/Eso/EsoPolicyServiceTest.php --filter=calibrat
OK (5 tests, 20 assertions)
```

**Pre-existing failures, untouched by this change:** the `tests/Feature/Eso/` suite has **5 failures on a clean checkout** of `student_workflow` (verified by stashing these changes and re-running: 178 tests / 5 failures before, 183 tests / 5 failures after — same five). They concern D2 staleness precedence, D3 vs. retrieval ordering, and ladder-rung recency. Unrelated to calibration, but they are red and someone should own them:

- `test_later_ladder_rungs_remain_reachable_and_are_not_capped_at_the_recency_window`
- `test_an_active_misconception_still_outranks_a_due_stale_retrieval`
- `test_d3_scenario_a_not_assessed_outranks_both_prerequisite_gap_and_misconception`
- (plus 2 more in the same D1–D3 precedence area)

**What this does not fix.** The mastery signal is still uncalibrated in practice, because nothing is calibrated yet — the difference is that the system now says so instead of implying otherwise. Making it *true* needs #1 (populate the bank) and a `pal:derive-irt` run. Next in sequence is #15.

---

## 3. Universal Learning Content Model (PAL is consumer, not owner)

- **Phase:** Phase 2/3 — size it like Examination, as its own line item
- **Depends on:** Curriculum, Concept Intelligence, Question Intelligence Engine
- **Owner:** —
- **Status:** ⬜ Not Started

**Description.** Content is NOT PAL-owned. One "Universal Content" object is consumed by PAL (personalised learning), Classroom (teacher-led), and Examination (board/school assessment) — the same pattern as every other "one engine, many consumers" decision in this tracker.

**Action required.** Do NOT fold this into general content cleanup — size and sequence it independently, the same treatment already given to Examination as its own future module.

### ✅ Sized and sequenced — 2026-09-08

Written up in **[PAL_UNIVERSAL_CONTENT_MODEL_DESIGN.md](PAL_UNIVERSAL_CONTENT_MODEL_DESIGN.md)**, in the shape the action asked for. No code — the deliverable for this item is a sizing.

**The main finding challenges the item's own framing.** Content is *already* shared under one metadata layer: `pal_content_metadata` and `pal_question_metadata` sit over `content_master` and `lms_question_master`, and between them already carry the 4-type model, Bloom, format, language, psychometrics — plus learning purpose (#4), curriculum version (#5) and dual fitness (#8) added this session. The three consumers do **not** have three stores. What they lack is a *contract*: each queries the tables directly with its own filters, so each owns its own definition of "servable to me" — and that definition has already drifted once, which is exactly what #2 found.

So this is not a greenfield build. It is five phases, sized S/S/M/L/XL:

| | Scope | Size | Gate |
|---|---|---|---|
| U1 | Read contract for PAL | S | none |
| U2 | Read contract for Examination | S | **#6** |
| U3 | Populate the new classification | M (authoring, not engineering) | none |
| U4 | Classroom as a real consumer | L | U1 |
| U5 | Boundary around `content_master` | XL | U1, U4 |

**Three things worth arguing with:**

1. **Classroom is not a consumer today.** PAL and Examination both read the metadata layer; there is no teacher-led path that does. "Three consumers" is currently an intent with two implementations, and U4 is sizing a *new* consumer rather than a refactor.
2. **The real debt is `content_master`.** At least eight services read it directly, past the metadata layer. That is why "content is not PAL-owned" cannot be enforced — there is no boundary to enforce it at. It is also the least reversible step, so it goes last.
3. **#6 gates more than its own item.** The Examination read contract cannot be written without the item-reservation rule, which is the same reason #19 stops at counting. #6 should be taken sooner than its Phase 2 label implies.

**Recommended order: U3 → U1 → (#6) → U2 → U4 → U5.** U3 first in practice — it needs authoring capacity rather than engineering, and it is what turns #4, #5 and #8 from correct-but-inert schema into behaviour.

---

## 4. Learning Purpose classification layer

- **Phase:** Phase 2
- **Depends on:** #3 Universal Learning Content Model
- **Owner:** Track A
- **Status:** ⬜ Not Started

**Description.** Every content object is flagged by purpose: Understand / Prerequisite / Explain / Demonstrate / Practice / Apply / Transfer / Remediate / Enrich / Recall / Assess — not only by concept and format.

**Action required.** This is what makes PAL's Corrective Micro-Lesson step (steps 7/8) possible. Without a Purpose tag, PAL has no principled way to find the RIGHT alternate content versus any content on the same concept. Add to the schema before more content is authored without it.

### ✅ Vocabulary and schema added — 2026-09-08

The eleven purposes are now a closed set in `config/pal_content.php`, validated the same way every other PAL vocabulary is.

| File | Change |
|---|---|
| `config/pal_content.php` | `learning_purposes` — 11 entries, each with `label`, `description`, `phase`, `corrective` |
| `app/Services/PAL/Content/PalVocabulary.php` | `learningPurposes()`, `isLearningPurpose()`, `correctiveLearningPurposes()`, `isCorrectivePurpose()`, `purposePhase()`; `learning_purpose` added to `validate()` |
| `database/migrations/2026_09_08_110000_...` *(new)* | Nullable `learning_purpose` on `pal_content_metadata`, indexed with `concept_ref_id` |
| `app/Models/PAL/ContentMetadata.php` | Added to `$fillable` |
| `app/Console/Commands/PAL/VocabCheckCommand.php` | Registered, so `pal:vocab-check` audits it like the rest |

**The `corrective` flag is the part that earns its keep.** A purpose tag alone would only let the micro-lesson step ask "what else is on this concept?" — and the answer to that still includes the assessment item the learner just failed. So each purpose declares whether it may be served as corrective content, and the exclusions are the point:

- **`assess` and `recall`** — serving an assessment item as remediation shows the learner the thing being measured.
- **`enrich`** — a learner who has just failed needs the concept again, not an extension beyond it.
- **`practice` / `apply` / `transfer`** — answering "they got it wrong" with another item of the same kind is not a different explanation.
- **`prerequisite` IS included** — sometimes the honest answer to a failure is that the gap sits upstream of the concept being taught.

That leaves `understand`, `prerequisite`, `explain`, `demonstrate`, `remediate` as the candidate set for steps 7/8.

**Deliberately not backfilled.** Every existing row predates the vocabulary, so any value written now would be a guess at an author's intent. NULL means "not yet classified", which is true and is what a coverage report should show.

**Tests:** 10 added, all passing (87 assertions) — most of them asserting what is *excluded* from the corrective set.

```
./vendor/bin/phpunit tests/Unit/PalLearningPurposeTest.php
OK (10 tests, 87 assertions)
```

**Migration has since been run** (2026-09-08, alongside #8's). It adds a nullable column and is reversible; the work could not be verified without the schema. Only the ESO menu-row migration from #17 remains deferred, because that one inserts rows into shared menus rather than altering a table.

### ✅ Now load-bearing — 2026-09-08

The vocabulary is wired into the selection it was created for, so it is no longer a schema nothing reads.

**Where the gap actually was.** `VariantRouterService::nextVariant()` is the reroute-on-failure ladder. It filtered by `content_type` and by *a different format* — which finds a different **modality of the same thing**. That is how a learner who has just failed a practice item can be re-served that same practice item as a video. Purpose is the axis that was missing, exactly as this item predicted.

| File | Change |
|---|---|
| `app/Services/PAL/Content/VariantRouterService.php` | Purpose filtering in `nextVariant()`, plus `purposeFilter()` |
| `app/Http/Controllers/api/PAL/PalContentIntelligenceController.php` | `purpose` and `corrective` accepted on `/content/next-variant` |
| `tests/Feature/Pal/PalCorrectivePurposeRoutingTest.php` *(new)* | 9 tests |

**Two strengths, because there are two different callers:**

- **`corrective: true`** asks for the corrective *set* and is a **preference**. `learning_purpose` is nullable and unbackfilled, so a hard filter here would empty the reroute ladder for practically every concept on the live estate. It narrows when classification exists, falls back when it does not, and **reports which happened** via `purpose_filtered` — the same prefer-and-report discipline used for calibration in #2, and for the same reason: an unfiltered result and a filtered one are otherwise indistinguishable in the payload.
- **`purpose: 'remediate'`** names one exactly and **binds**. A caller that asked for remediation and was handed a practice item has been given the wrong thing, so an empty result is the honest answer. It is also excluded from the relaxation step below it, which would otherwise drop the very constraint the caller set.

**An unregistered purpose returns nothing, not everything.** A typo narrows to zero rather than widening back to the whole concept. I got this wrong twice while writing it — the first two attempts both fell through to the unfiltered set via a different path (`??=`, then the relaxation branch), which is precisely the silent-widening failure the rule exists to prevent. Both paths are now covered by tests.

**Callers that never mention purpose behave exactly as before** — asserted, since this touches the live content path for every concept.

**Tests:** 9 added, all passing. PAL suite 84 tests / 5 failures — the same 5 pre-existing.

**Still open:** the ESO misconception path (`MisconceptionLibraryService::selectCorrective()`) reads a dedicated `pal_misconception_corrective` table and is untouched by this — it already selects purpose-built content, so it needs no purpose filter. What this changes is the *general* content reroute. And with the estate unclassified, `purpose_filtered` will report `false` everywhere until authoring populates `learning_purpose`; the wiring is correct and inert until then.

---

## 5. Content versioning by curriculum / academic year

- **Phase:** Phase 2
- **Depends on:** #3 Universal Learning Content Model
- **Owner:** Track A
- **Status:** ⬜ Not Started

**Description.** Content is versioned against a specific curriculum + academic year (e.g. "CBSE Mathematics Grade 8, 2026-27"), not a single mutable record. A board syllabus revision creates a new version rather than overwriting or breaking historical mappings.

**Action required.** Add this now, before multiple years of evidence exist. Retrofitting versioning after a board revision has already landed risks corrupting historical evidence or forcing a destructive migration.

### ✅ Built — 2026-09-08

**What existed was not versioning.** Both metadata tables already carried a `version` string defaulting to `'1.0'` — a *row revision*: edit the row, bump the string. That cannot express what actually happens to a school. A board revises its syllabus, and last year's content was correct for last year's students.

Without a named version, a revision has only two outcomes and both are bad: overwrite the content, which silently rewrites what a cohort was taught and detaches their evidence from the material behind it; or fork it and lose the link. This adds the third option — supersede, keeping both.

| File | Change |
|---|---|
| `database/migrations/2026_09_08_130000_...` *(new)* | `pal_curriculum_versions`, plus nullable `curriculum_version_id` on both metadata tables |
| `app/Models/PAL/CurriculumVersion.php` *(new)* | The version, its statuses and scopes |
| `app/Services/PAL/Content/CurriculumVersionService.php` *(new)* | `resolve`, `activate`, `supersede`, `successorChain` |
| `tests/Feature/Pal/PalCurriculumVersionTest.php` *(new)* | 10 tests |

**The property everything rests on: superseding never touches tagged content.** When a version is superseded, every content and question row that pointed at it still points at it afterwards. That is what keeps a past cohort's evidence attached to what they were actually taught. Repointing rows at the successor would be the destructive migration this item exists to avoid, and there is a test whose only job is to catch anyone adding it later.

**Decisions worth challenging:**

1. **The unique key is `(tenant, board, standard, subject, academic_year)`.** A second version of the same year is a contradiction, not a variant — so two callers racing to open a year converge on one row.
2. **A new version starts as `draft`, never `active`.** Naming next year's syllabus is not the same as putting it in force; activation is a deliberate act.
3. **Activating supersedes the previous version in the same scope, in a transaction** — "which syllabus is in force" has exactly one answer, and there is never a moment with two or none.
4. **A superseded version cannot be re-activated.** Reviving one would make the cohorts taught under it indistinguishable from the current one. Create a new year instead.
5. **`supersede()` does not copy content forward.** Doing so would assert the board changed nothing — precisely what a revision contradicts — and would double the estate on every rollover. Authoring decides what the new year reuses; this only opens it.
6. **`superseded_by_id` points forward only**, so history is a chain walkable from any point without mutating what came before. `successorChain()` guards against cycles rather than trusting the convention.

**A bug the tests caught:** `curriculum_version_id` was missing from `$fillable` on both models, so mass assignment silently dropped it and content was never actually bound to a version. The non-destructive-supersede test failed for the right reason and found it. Both models fixed.

**Tests:** 10 added, all passing. PAL suite 105 tests / 5 failures — the same 5 pre-existing. Migration run (additive; new table plus two nullable columns).

**Still open:** nothing writes `curriculum_version_id` yet — no authoring path assigns content to a version, and nothing filters serving by the active version. Existing rows are deliberately unbackfilled: assigning them to a year would assert something about what a past cohort was taught that nobody recorded. The model is in place *before* the first revision, which was the point of doing it now.

---

## 6. PAL-vs-Examination item isolation rule

- **Phase:** Phase 2
- **Depends on:** #1 Calibrated Assessment Bank, #3 Universal Learning Content Model
- **Owner:** Track A
- **Status:** 🟠 Needs Team Decision

**Description.** PAL's Adaptive Diagnostic / Mastery Check and school Examinations both draw from the same Calibrated Assessment Bank per the architecture review — but a board-reserved exam item must never be shown to a student as adaptive practice before the actual exam.

**Action required.** TEAM DECISION NEEDED: define which specific items are exam-reserved versus available to PAL's adaptive selection. This needs an explicit exclusion rule, not an implicit assumption, or it becomes an exam-integrity problem.

### Decision brief — measured 2026-09-11

```
rows with a board set at all:                 0   (of 62,487 questions)
board-fit (placeable in a paper):             0
servable to PAL:                            226
BOTH — the actual collision set:              0
```

**The exam-integrity risk has zero current exposure.** `board` is unpopulated across the entire estate, so no question can be placed in a board paper today, and therefore no question can be simultaneously exam-reserved and served as PAL practice. The situation this item warns about cannot currently occur.

**That changes the shape of the decision, not its importance.** It moves from *"urgent risk to contain"* to **"cheap now, expensive later"**:

- Decided **before** board tagging begins, the reservation rule is a column and a scope on a table nobody has populated — near-zero cost.
- Decided **after**, it is a retrofit across up to 62,487 tagged items, needing someone to determine retrospectively which were reserved. That is precisely the destructive-migration shape #5 exists to avoid, and #19's blueprint engine will start producing tagged items the moment authoring begins.

**A related observation for whoever takes it:** the vocabulary already has an `assessment_types` set (`diagnostic`, `formative`, `competency`, `retention`, `sky`, `vocational`) describing what an assessment is *for*. None of those expresses *reservation*, and reservation is orthogonal to all of them — a `formative` item could be reserved, a `competency` one need not be. So this wants its own flag rather than another `assessment_type` value; folding it in would make a closed vocabulary carry two unrelated meanings, which is the mistake #8 avoided by keeping PAL calibration and board compliance as separate fitness criteria.

**Recommendation on sequencing:** take this decision before #19's authoring starts, and before #3's U3 (populate the new classification) — both of which will begin writing the `board` / `blueprint_category` fields this rule needs to constrain. The decision is currently free; it stops being free the moment either starts.

---

## 7. Coverage vs. Attainment reporting (CG/LO achievement)

- **Phase:** Phase 2/3
- **Depends on:** Curriculum CG/LO mapping, Student Evidence store, Content Model
- **Owner:** Track A
- **Status:** ⬜ Not Started

**Description.** Two SEPARATE reports: Curriculum Coverage (was it taught?) and Student Attainment (did students demonstrate mastery, per CG/LO, from actual evidence?). E.g. "92% of curriculum taught, but only 66% demonstrated as mastered."

**Action required.** Materially more valuable to a principal than a single "78% score" — this is the report that tells a school WHERE to intervene, not just how much was covered. Build once Concept Intelligence's Evidence backend is confirmed (see Content & LMS Architecture tab).

### ✅ Built — 2026-09-08

`GET /api/pal/eso/reports/attainment?standardId=&syear=&subjectId=` returns both reports for a cohort, plus a per-concept breakdown.

| File | Change |
|---|---|
| `app/Services/PAL/Reporting/AttainmentReportService.php` *(new)* | The two reports |
| `app/Http/Controllers/api/PAL/AttainmentReportController.php` *(new)* | Staff-only endpoint |
| `routes/pal_eso_api.php` | Registered outside the `eso.student` guard |
| `tests/Feature/Pal/PalAttainmentReportTest.php` *(new)* | 8 tests |

**The evidence backend this depended on is `eso_response_log` + `pal_concept_nodes` / `LearnerNodeState`,** which are live and were already carrying real state — so the dependency named in the action is satisfied.

**The decisions that keep the two numbers from contaminating each other:**

1. **Coverage counts the whole curriculum, attainment only the taught part.** A concept with no ESO-ready content counts *against* coverage — excluding it would hide the very gap being measured. But it is excluded from the attainment denominator, because folding undelivered material into a mastery percentage blames a school for a content gap and hides that gap inside a teaching number. In the test fixture this is the difference between reporting 100% attainment on one taught concept and reporting 33%.
2. **An untaught concept has `attainment_pct: null`, not `0.0`.** Nobody has failed a concept that was never available to them, and a zero reads as a teaching failure.
3. **"Taught but unevidenced" is reported separately from low attainment.** Both sit at 0%, and they are completely different problems: one class needs teaching support, the other has not reached the material. The report names them apart.
4. **Mastery is read, not recomputed.** `masteryVerdict()` sweeps a concept's nodes to `STATUS_MASTERED` when it clears, so the report counts that recorded outcome in bulk rather than introducing a third mastery definition — the exact failure mode found in #15. Only K and A nodes gate, matching the verdict rule.

**The limitation this creates, stated in the code:** a student who now *qualifies* for mastery but whose verdict has not been resolved since their last attempt will not appear as mastered until it is. The report is therefore a floor, never an overstatement — the safe direction for a number a school acts on.

**Tests:** 8 added, all passing. PAL suite 75 tests / 5 failures — the same 5 pre-existing.

### ✅ UI built — 2026-09-08

`/pal/reports/attainment` in `lms_k12`, registered as New PAL's eighth sub-module.

| Repo | File | Change |
|---|---|---|
| `lms_k12` | `app/pal/reports/attainment/page.tsx` *(new)* | The screen |
| `lms_k12` | `app/pal/data/pal-eso.ts` | `fetchAttainmentReport()` + types |
| `lms_k12` | `app/data/routeMapper.ts` | `'new_pal.reports': '/pal/reports/attainment'` |
| `next_lms_erp` | `database/migrations/2026_09_08_140000_...` *(new)* | Reports menu row — **Pending, not run** |

**The screen never averages the two numbers together.** They sit side by side with an explicit line saying why they differ: coverage counts the whole curriculum, attainment only the taught part. Below them the concepts are split into the three states a school responds to differently — *where to intervene* (taught, attempted, under 50% mastered), *taught but not started* (nobody has reached it), and *no teachable material* (a content gap, nobody failed anything).

**Two things worth noting:**

1. **The class picker is derived from the student list, not from the content-model facets.** Those facets expose `standards` as grade *numbers*; the report needs standard *ids*. They are not the same thing, and passing one for the other would silently report on the wrong class. The student list carries real `standardId`/`standardName` pairs, so it is the honest source.
2. **The menu row withholds the student grant**, unlike #17's ESO row where the student grant was the point. This report names how a whole class is performing. The API already refuses a student caller, so the menu is agreeing with the endpoint rather than adding a second gate — a tab a student can see but never open is worse than no tab.

**A wrong guess caught before it shipped:** the migration first referenced `tblprofilemaster` to find the student profile; the table is actually `tbluserprofilemaster`. `Schema::hasTable()` would have made that fail silently as "no students to exclude" — granting students access to the report. Corrected.

`npx tsc --noEmit` clean across `lms_k12`.

**Still open:** cohort-scoped to a standard — "this class, this year". A cross-year or whole-school roll-up would need its own aggregation rather than calling this in a loop. No subject filter in the UI yet, though the API accepts one.

---

## 8. Dual fitness-for-purpose metadata (PAL calibration vs. board compliance)

- **Phase:** Phase 2
- **Depends on:** #3 Universal Learning Content Model, Question Intelligence Engine (see LMS Content Backlog tab)
- **Owner:** Track A
- **Status:** ⬜ Not Started

**Description.** Every question/content item carries TWO separate fitness tags:

- `pal_calibration` — difficulty, discrimination, KASBA tag, misconception tag
- `board_compliance` — board, blueprint category, weightage, LO/CG mapping

because "calibrated for PAL" and "compliant for a board exam" are different fitness criteria over the same item.

**Action required.** Sharpens the existing "one blended Question Intelligence Engine" decision — the fix isn't just one shared database, it's this dual tagging scheme, so one engine can correctly serve both PAL and board-compliant examination without conflating the two.

### ✅ Built — 2026-09-08

**What was already there, and what was missing.** The PAL-calibration half was complete — `irt_a/b/c`, `discrimination_index`, `response_count`, `psychometrics_derived_at`, `difficulty_1_to_5`. The board half was three fields (`board`, `grade_band`, `stage`), which is not enough to place an item in a paper: a blueprint is written as *"20 marks of short answer, 15 of case-based"*, so an item with no category and no marks cannot be selected for one however well calibrated it is.

| File | Change |
|---|---|
| `config/pal_content.php` | `blueprint_categories` — 7 CBSE question types, each with `typical_marks` |
| `database/migrations/2026_09_08_120000_...` *(new)* | `blueprint_category`, `marks` (decimal), `learning_outcome_ref` on `pal_question_metadata`, indexed `(board, blueprint_category)` |
| `app/Models/PAL/QuestionMetadata.php` | `palCalibration()`, `boardCompliance()`, `isFitForPalDiagnostic()`, `isFitForBoardExam()` |
| `app/Services/PAL/Content/PalVocabulary.php` | `blueprintCategories()`, `isBlueprintCategory()`, `typicalMarksFor()`; `blueprint_category` and `marks` validation |
| `app/Console/Commands/PAL/VocabCheckCommand.php` | Registered for audit |

**The four combinations are the whole point, and each is asserted:**

| | Board-fit | Not board-fit |
|---|---|---|
| **Calibrated** | both | strong discrimination, no blueprint slot |
| **Not calibrated** | a valid 3-mark short answer nobody has sat yet | neither |

The bottom-left cell is the one that matters most: **every newly authored item is board-fit and uncalibrated at the same time.** A single blended "quality" score would exclude it from the paper it was written for. That is precisely why one engine looked impossible — kept apart, one engine serves both consumers because each asks its own question of the same row.

**Two decisions worth challenging:**

1. **`isFitForBoardExam()` says nothing about psychometrics.** A board paper is assembled from a blueprint, not from discrimination indices. Requiring calibration here would have quietly made PAL's criteria govern the exam.
2. **`typical_marks` is guidance, not a constraint.** Boards vary weighting between papers and years, so the marks an item actually carries live on the row and are allowed to differ. A 2-mark short answer validates.

**The test that guards a real risk:** `scopeCalibrated()` is SQL and `isFitForPalDiagnostic()` is PHP over a loaded model, so they *cannot* share an implementation. Both read the same config, and a test asserts they answer identically across seven rows — including exactly-at-the-threshold and hand-typed-but-never-derived cases. A drift there would mean the diagnostic selects items it then reports as uncalibrated.

**Tests:** 9 added, all passing (52 assertions).

```
./vendor/bin/phpunit tests/Feature/Pal/PalDualFitnessTest.php
OK (9 tests, 52 assertions)
```

**Migration run.** Unlike #17's, this one and #4's were applied to the dev database — they add nullable columns and are reversible, and the work could not be verified without the schema. The ESO menu-row migration is still deferred, because that one *inserts rows* into shared menus rather than altering a table.

**Still open:** nothing writes these fields yet. `blueprint_category`, `marks` and `learning_outcome_ref` are nullable and unbackfilled — a guessed category would put an item into a real exam paper on the strength of an assumption. Authoring has to populate them, and the CBSE Blueprint generator (#19) is the consumer that will read them.

---

## 9. Verify: Unified Learning Units L4 "Assessment" vs. Content Model's Calibrated Assessment Bank

- **Phase:** Phase 1 — URGENT
- **Depends on:** #1 Calibrated Assessment Bank coverage
- **Owner:** Track A
- **Status:** 🟩 RESOLVED — answer (a); one store, not two

**Description.** Unified Learning Units (a 4th view over the same `semantic_intelligence` data, alongside Concept Intelligence / Frameworks / Content Model) shows its own "L4 — Assessment" layer reporting 100% population (4/4 modules, 31 records) for the one concept viewed. This must be reconciled against Content Model's Calibrated Assessment Bank, which showed only 2.3% coverage across all chapters.

**Action required.** Two possible answers, each with a different consequence:

- **(a) L4 IS the same underlying assessment data** — the true Assessment Bank gap may be narrower than Content Model alone suggests, or this concept is one of the rare populated ones.
- **(b) L4 is a SEPARATE assessment system** — there are then two assessment content stores to reconcile before the Universal Content Model is designed, and the PAL-vs-Examination isolation rule (#6) must account for a third consumer.

Get a direct answer before designing the Universal Content Model.

**Verified (2026-09-08) — the answer is (a). This item can be closed.** ULU is not a separate assessment system. `app/pal/data/pal-content-model.ts` builds every ULU layer from the same `semantic_intelligence` record the Content Model reads, and its assessment layer reads the *same two columns*:

```ts
const blueprint = conceptList(entry, record, 'assessment_blueprint', 'assessment_blueprint');
const rubrics   = asRecord(entry?.assessment_rubrics ?? record.assessment_rubrics);
```

ULU is a fourth *presentation* over one store, not a fourth store. The "100% (4/4 modules, 31 records)" figure is per-concept for the one concept being viewed, and is entirely consistent with 2.3% estate-wide — it just means that concept is one of the populated ones.

**Consequences:** there is no second store to reconcile before designing the Universal Content Model (#3), and #6's isolation rule does **not** need to account for a third consumer. Both of those blockers are lifted.

One thing this *does* surface: ULU's four slugs (`five-layer-structure`, `scenario-template`, `complete-examples`, `content-optimization-loop`) are labels over the same record, and none of the layer names map to the "L4 — Assessment" wording in the sheet. Whoever read "L4 — Assessment" was reading a rendered section title, not a data layer.

---

## 10. CONFIRMED (code): KASBA duplicated across 3 implementations, 1 is dead code

- **Phase:** Phase 1 — URGENT
- **Depends on:** —
- **Owner:** Track D
- **Status:** ⚠️ PARTLY REFUTED — 3 surfaces confirmed, no dead code, no 4th copy

**Description.** Verified in `lms_k12`:

1. `enterprise-brain/knowledge/kasba/page.tsx` — its own `KasbaPayload` type + `brainFetch` API.
2. `app-capability-intelligence/` — a full separate module (competency-framework, capability-library, competency-library, framework-studio).
3. `app/talent-management/_components/kasba-rating-table.tsx` and its editable modal — plus G2G's `components/domain/competency/kasba-rating-panel.tsx`, carrying the developer's own note ("the 4th one").

**Action required.** Consolidate to ONE capability/competency engine. The ported component is confirmed dead code in the production-adjacent repo — either wire it up or remove it; don't leave unreferenced endpoints live. This upgrades the earlier "Needs Team Confirmation" item to a confirmed, code-verified finding.

**Verified (2026-09-08) — the duplication is real, the "dead code" framing is not.** What actually exists in `d:\lms_k12`:

| Surface | Path | Data path | Reachable? |
|---|---|---|---|
| Enterprise Brain KASBA | `app/enterprise-brain/knowledge/kasba/page.tsx` | its own `brainFetch<KasbaPayload>(tenantPath('/kasba'))` from `@/lib/brain/api` | **Orphaned** — no file anywhere links to `enterprise-brain/knowledge/kasba` |
| Capability Intelligence module | `app/capability-intelligence/` (capability-explorer, capability-library, competency-framework, competency-library, dashboard) | Laravel `/api/competency/*` via `_lib/command-center-api.ts` | Linked from 4+ places, incl. Department panels and the LMS course builder |
| Talent Management panel | `app/talent-management/_components/kasba-rating-panel.tsx` | — | **Imported** by `employee-profiles/components/employee-profiles-center.tsx` |

Three corrections:

1. **Nothing is dead code.** The talent-management panel is imported and used. The enterprise-brain page is an *orphaned route* — reachable by typing the URL, not linked from any nav — which is a different problem with a different fix (it still ships, still renders, still hits a live endpoint).
2. **The claimed 4th copy does not exist.** There is no `components/domain/competency/kasba-rating-panel.tsx` and no KASBA or competency file anywhere under `g2g/`. The file the sheet describes as G2G's is the talent-management one, counted twice. The count is 3, not 4.
3. **The file named in the sheet is wrong.** There is no `kasba-rating-table.tsx`; it is `kasba-rating-panel.tsx`.

**Revised action.** Still consolidate — three independent data paths to the same concept (`brainFetch /kasba`, `/api/competency/*`, and the talent-management panel) is the real finding, and it stands. But the cleanup is *not* "delete dead code": it is (a) decide whether Enterprise Brain's KASBA view should be linked or removed, and (b) point all three at `/api/competency/*`, which is the only one of the three with a real backend contract and multiple consumers — making it the natural system of record.

### Decision brief — measured 2026-09-11, and it inverts the recommendation above

Traced each surface to its backend and counted rows:

| Surface | Backend | Rows |
|---|---|---|
| Enterprise Brain `/kasba` (the "orphaned" one) | `hpbrain_capabilities` | **2,398** |
| Capability Intelligence module | `/api/competency/*` → `s_competency_*` | frameworks **2**, framework items **0**, assessments **0** |
| Talent Management rating panel | `/competency/kasba-rating` → `s_competency_*` | *same backend as above* |

**Two corrections to what I wrote above.**

1. **It is two backends, not three implementations.** The Capability Intelligence module and the Talent Management panel both call `/api/competency/*` and share the `s_competency_*` tables. Two UIs over one service is not duplication — it is the correct pattern. Only Enterprise Brain is a genuinely separate store.

2. **My recommendation was backwards.** I named `/api/competency/*` the natural system of record because it has "a real backend contract and multiple consumers". Both facts are true, and both are about *code structure*. Measured on data, it holds **2 frameworks and zero framework items**, while the surface I dismissed as orphaned holds **2,398 capability rows** — effectively all of the KASBA data in the estate.

**So the actual situation is the reverse of how it reads:** the well-structured service is nearly empty, and the unlinked page is where the data lives. 2,398 rows sit behind a route with no navigation to it.

**That makes this a real decision with a real cost either way**, unlike #16:

- **Adopt `s_competency_*`** — the better-structured, multi-consumer backend. Requires migrating 2,398 capability rows into a schema whose framework-items table is empty, i.e. designing the mapping first.
- **Adopt `hpbrain_capabilities`** — keeps the data where it is. Requires re-pointing two working UIs at a store with a thinner contract, and giving the orphaned page a home in navigation.

**What I would not do:** decide this on code structure, which is what I did the first time and what produced the wrong answer. The question is which schema the 2,398 rows belong in — a data-modelling judgement about what KASBA *is*, not a tidiness one.

---

## 11. CONFIRMED (code): Framework routes duplicated across 4 folders

- **Phase:** Phase 2
- **Depends on:** Learning Alignment (Course Catalog & Taxonomy tab)
- **Owner:** Track D
- **Status:** 🟥 CONFIRMED — code-verified

**Description.** `app/pal/framework/qa/page.tsx` + `app/pal/framework/ui/page.tsx` + `app/pal/frameworks/[slug]/page.tsx` + `app/pal/ab/[slug]/page.tsx` — multiple route files for what should be one Framework / Learning-Alignment surface. Also confirmed: the "New PAL" tab bar mixes pages under `pal/new/*` with legacy pages under `pal/framework/*`.

**Action required.** Consolidate to one Framework route. Decide which folder is canonical (recommend `pal/new/framework`, matching the other genuinely new pages) and redirect/remove the rest.

**Verified (2026-09-08) — confirmed at 4 routes, but every path in the sheet is wrong.** The actual route files are:

```
app/pal/framework/page.tsx
app/pal/framework/ulu/page.tsx
app/pal/frameworks/page.tsx
app/pal/frameworks/[slug]/page.tsx
```

None of `framework/qa/`, `framework/ui/`, or `pal/ab/[slug]/` exists. The duplication is a **singular/plural split** — `framework/` vs `frameworks/` — which is a more mundane and more easily fixed cause than four scattered folders.

Two further notes:

- The overlap with #17 is direct: `app/pal/framework/ulu/page.tsx` duplicates `app/pal/ulu/`, and the DB menu (`2026_08_26_100000`) points the Framework tab at route name `new_pal.frameworks` (plural) and the ULU tab at `new_pal.ulu` (top-level). So the **plural** folder is what the live menu targets.
- The recommendation in the sheet — canonicalise on `pal/new/framework` — contradicts that: no `app/pal/new/framework` directory exists, and creating one would require re-pointing the `tblmenumaster` row too.

**Revised recommendation.** Canonicalise on `app/pal/frameworks/` (plural), since that is what the live menu already routes to; fold `framework/page.tsx` into it and move `framework/ulu/` under the existing `app/pal/ulu/`. That makes this a rename plus two redirects, with no migration needed.

### ⚠️ Correction — the route duplication was already fixed. 2026-09-08

Opening the files to do the consolidation showed there is nothing to consolidate. Both files in the singular `framework/` folder are **already redirect shims**:

- `app/pal/framework/page.tsx` → `redirect('/pal/frameworks')`, carrying the query string
- `app/pal/framework/ulu/page.tsx` → `redirect('/pal/ulu')`

So of the four route files, two are real pages and two are deliberate backwards-compatibility redirects pointing at exactly the canonical routes I recommended. Someone had already done this work, and the recommendation above describes a change that was in place before it was written. **The "4 duplicated route folders" finding does not survive contact with the code** — counting route *files* and calling them duplicates was the error, in both the original sheet and my correction of it.

### ✅ The real duplication, and what was done about it — 2026-09-08

There *was* duplication here, just not in the routing:

1. **`queryStringFromSearchParams` was copy-pasted verbatim into five route files** — `framework`, `frameworks`, `frameworks/[slug]`, `ulu`, `ulu/[slug]`. The same 13 lines, five times.
2. **`/pal/frameworks` and `/pal/ulu` were byte-for-byte identical pages** apart from six values: empty-state title, which model slice to list, hero eyebrow/title/description, grid `basePath`, and styling `variant`. They had already drifted — the two descriptions explain the same mechanism in different words ("dynamically derived from the same semantic_intelligence payload" vs "dynamically assembled from semantic_intelligence").

**Changes (all in `lms_k12`):**

| File | Change |
|---|---|
| `app/pal/_lib/searchParams.ts` *(new)* | `queryStringFromSearchParams` + `firstValue`, defined once |
| `app/pal/_components/PalTaxonomyParentPage.tsx` *(new)* | The shared parent-page shell, parameterised by those six values |
| `app/pal/frameworks/page.tsx`, `app/pal/ulu/page.tsx` | 59 lines each → 20 each, both on the shared shell |
| `app/pal/framework/page.tsx` | Kept as a redirect; now imports the shared helper and documents why the shim exists |
| `app/pal/frameworks/[slug]/page.tsx`, `app/pal/ulu/[slug]/page.tsx` | Local helper copies removed |
| `app/pal/_components/PalContentView.tsx` | `PalSurfaceVariant` exported so the shared shell can type `variant` properly |

**Be honest about the size of this win:** the five route files drop from 280 lines to 166, but the two new shared files add ~127, so it is close to line-neutral. The gain is that the helper has one definition instead of five and the parent page has one implementation instead of two — the drifted descriptions are the evidence that mattered. It is not a size reduction.

`npx tsc --noEmit` is clean across `lms_k12`. Behaviour is unchanged: same redirects, same query-string handling, same rendered output.

### ✅ Detail pages consolidated too — 2026-09-08

**The reason given for deferring them was wrong.** I had assumed the detail pages carried more per-taxonomy variation than the parents. Reading them showed they carry none: the only differences are the slug map used to validate the route, which model slice to read, and four label strings — the same shape the parents differed by.

`app/pal/_components/PalTaxonomyDetailPage.tsx` *(new)* takes those as props; both routes drop from 54 lines to 26 and now share one implementation of the load, the empty state, and the two 404 paths (unknown slug, and a registered slug with nothing behind it for this concept).

`npx tsc --noEmit` clean across `lms_k12`. Behaviour unchanged — same validation order, same back-href construction, same rendering.

**Net for #11:** the routing "duplication" was already fixed before I looked; the real duplication was a copy-pasted helper across five files and two pairs of near-identical pages. All three are now single-sourced.

---

## 12. CONFIRMED (code, positive finding): Conversational AI is already correctly centralized

- **Phase:** Phase 1
- **Depends on:** —
- **Owner:** Track D
- **Status:** 🟩 Done

**Description.** `ChatbotPanel` is the shared, centralized AI chat interface across ALL PAL surfaces (`pal/new/*`, audits, confirmation-gate, module-workflow-routing) and is imported from exactly ONE shared component. This CONTRADICTS the earlier hypothesis of two separate K-12 chat implementations — there is only one, built correctly.

**Action required.** No fix needed on the K-12 side. Use this as the REFERENCE PATTERN when consolidating KASBA / Framework / Leaderboard: it confirms the team can and does execute "one engine" correctly when the pattern is followed.

---

## 13. AI stack packages need a real API surface for G2G to consume (not just a workspace package)

- **Phase:** Phase 2
- **Depends on:** Conversational AI (already built)
- **Owner:** Track D
- **Status:** ⬜ Not Started

**Description.** `conversational-ai-core` / `conversational-mcp-core` are currently local workspace packages. That works within the `lms_k12` repo, but G2G will need to consume them from a separate repo/deployment — a `g2g` folder inside `lms_k12` with shared paths is not a real product boundary.

**Action required.** Expose the AI stack (Conversational AI, Gen AI Gateway, Agent Runtime) as a hosted API/service any repo can call over HTTP — the same "centralize as a service" rule already applied to Administration and EB. Do NOT solve this via a published npm package (version-sync risk); solve it the way the rest of this tracker solves cross-product sharing.

### ✅ Sized — 2026-09-08, with four corrections to the premise

Written up in **[PAL_AI_STACK_SERVICE_DESIGN.md](PAL_AI_STACK_SERVICE_DESIGN.md)**. **The recommendation stands — service, not npm package.** The description of the current state does not, and the corrections make the work much smaller than it reads.

1. **They are not workspace packages.** `packages/conversational-ai-core` and `-mcp-core` have **no `package.json`** — they are source directories wired in by tsconfig path aliases and compiled into the one Next.js app. Publishing to npm is not a rejected shortcut; it is not currently possible without first making them packages.
2. **The AI stack is already an HTTP API.** `app/api/ai/chat/route.ts` is a Next.js route handler, as are `field-edit`, `voice/*` and `conversation/history`. `ChatbotPanel` already POSTs JSON to `/api/ai/chat`. There is no client library to replace with a service — the service exists.
3. **It is already multi-project by design.** `lib/ai/project-resolver.ts` registers adapters and selects one by id. The core anticipated several products.
4. **The `g2g/` folder contains one file — `globals.css`.** Nothing under it imports the shared AI code. The "g2g folder inside lms_k12 with shared paths" concern describes something that is not there.

**The real gap is one line:**

```ts
const projectId = (process.env.AI_PROJECT_ID || "lms_k12")
```

Project identity is **per-deployment, not per-request**, so one deployment serves exactly one product. A hosted stack serving both K-12 and G2G must resolve the adapter from the authenticated *caller*.

**Sizing:** A1 per-request project resolution (**M**, decision-free, blocks everything else) → A2 caller authentication (M) → A3 G2G adapter (M) → A4 deployment (a decision, not code) → A5 contract versioning (S).

**Recommendation: do A1 now; do not start A3 or A4** until §4's questions are answered — where it runs, who operates it, how model costs are attributed, and what the compatibility promise is. Building a G2G adapter against a service with no operator, no cost model and no compatibility promise would repeat the premature-generalisation pattern already rejected in #22 and #30.

---

## 14. AI Tutor — buildable now using EXISTING ChatbotPanel + ESO + Concept Intelligence

- **Phase:** Phase 1
- **Depends on:** ChatbotPanel (proven, centralized), ESO (exists, needs visibility), Concept Intelligence
- **Owner:** Track A
- **Status:** ⬜ Not Started

**Description.** The hard infrastructure already exists and works: ChatbotPanel (proven, centralized) + ESO's misconception diagnosis (`app/pal/eso/*`) + Concept Intelligence's structured Knowledge / Definition / Real-World data. AI Tutor = inject `concept_id` + ESO's current misconception flags + Concept Intelligence data as context into the model, delivered through the SAME chat panel students already see.

**Action required.** Highest-value, lowest-cost placeholder this week — most of the pieces already exist and are proven. Build the context-injection step (concept + misconception + Concept Intelligence data) as one visible AI Tutor surface.

### ✅ Tutor context + governance built — 2026-09-08

Built together with **#27**, because they are one thing: a tutor that can explain a concept but has no rule about *when* it may explain is the product Alpha School disabled chat to avoid.

`GET /api/pal/eso/tutor-context/{learnerId}/{conceptId}` returns what the tutor may say, and what it may say it from:

| Block | Contents | Source |
|---|---|---|
| `concept` | id, name, chapter | `lms_concept` |
| `mastery` | BKT estimate | `EsoPolicyService::conceptMasteryEstimate()` |
| `misconceptions` | only those ESO has actually flagged for this learner, each with its authored `corrective_action` | `LearnerNodeState.active_misconception_id` → `pal_misconception_library` |
| `grounding` | the authored learning content per node — the only permitted source for an explanation | `EsoLearningContentResolver::forNode()` |
| `governance` | mode, attempt counts, and the rules in force | computed |

**Files:** `app/Services/Eso/AiTutorContextService.php` *(new)*, `EsoEngineController::tutorContext()`, a route under `eso.student`, an `ai_tutor` config block, and one new public accessor on `EsoPolicyService`.

**Four decisions worth challenging:**

1. **Governance is served as data, not written into a prompt.** A rule in a system prompt is a request, and a model can be talked out of it. Resolved server-side it is a fact the caller must act on — and auditable, because the attempt count it turns on is a row count rather than a judgement.
2. **Explanation and answers are separate clauses.** `mode` gates *explanation* and unlocks at N genuine attempts. `assessment_answers` is `'never'` and is not derived from anything — no attempt count unlocks it. Collapsing them into one flag would create exactly the loophole the rule exists to close: two throwaway attempts would buy the answer key.
3. **"Genuine" means unassisted** — `hint_used = false`. Being walked through a question with hints is not evidence of an attempt to reach the answer independently, which is the thing #27 asks for. Three hinted attempts leave the tutor Socratic.
4. **No grounding means no session.** A concept with no ESO nodes returns 404 rather than an empty context, so a caller cannot open a tutor that would answer from the model's own knowledge. An empty `grounding` list is a truthful signal, not a degraded mode.

The threshold is `pal_content.ai_tutor.min_genuine_attempts_for_direct_answer`, default 2 — matching the worked ESO example in #25. There is no config value that disables the assessment-answer clause.

**Tests:** 9 added, all passing — covering the unlock threshold, hinted attempts not counting, the answer clause holding at 25 attempts, only-flagged-misconceptions surfacing, and the no-nodes case.

```
./vendor/bin/phpunit tests/Feature/Eso/AiTutorContextTest.php
OK (9 tests, 23 assertions)
```

**What this is NOT.** It is the context layer, not a chat surface. Nothing yet calls this endpoint — no student can talk to a tutor as a result of this change alone. That was deliberate: the existing `ChatbotPanel` gets its context from a generic, ontology-and-DB-driven resolver (`AiContextService`, via `/api/ai/workspace/context`), and wiring PAL into that means registering ontology entities and module rows whose configuration I would have been guessing at. Rather than reshape a system serving every module, the tutor context is a self-contained PAL endpoint that a UI step can consume next.

**Remaining work to make it visible** (in order): decide whether the tutor renders in `ChatbotPanel` or as its own panel on the ESO concept page; have that surface call `tutor-context` and pass `grounding` + `governance` into the chat request; and enforce `mode` at the point the prompt is assembled. The governance is only as good as the caller that honours it — which is an argument for doing that wiring in one place rather than per-surface.

### ✅ Now has a consumer — 2026-09-08

`app/pal/eso/_components/AiTutorPanel.tsx` renders on the ESO concept flow, below the step, alongside the existing `PlanAndSuggestions` panel and following its convention: fetched independently, never blocking the task, and rendering **nothing at all** when the concept has no grounding.

It shows two things, and generates neither:

1. **The misconception ESO actually flagged, with its authored `corrective_action` verbatim.** The value of a misconception library is that a human wrote the remedy; paraphrasing it through a model would discard exactly that.
2. **What the tutor is currently allowed to do**, in the student's own terms — "your tutor will ask you questions rather than give the answer; after 1 more try of your own it can explain directly", and unconditionally, "it will never give you the answer to a question that is being marked".

**Stating the rule to the student is the point, not a side effect.** A tutor that silently withholds an answer reads as unhelpful; one that says why reads as a rule. That framing is the whole reason Alpha School's constraint is survivable as a product rather than just a refusal.

**It deliberately contains no chat.** The conversation belongs in the shared `ChatbotPanel`, and wiring PAL context into that shared surface is still the open decision above. Building a second chat here to avoid deciding would be exactly the duplication this tracker keeps finding (#10, #11, #16). What this changes is that the grounding and governance are now real and visible, so the chat hand-off is the *only* thing left to decide rather than the whole feature.

`npx tsc --noEmit` clean across `lms_k12`.

---

## 15. Pedagogy Engine — rollout plan from hardcoded defaults to genuinely rule-driven

- **Phase:** Phase 1 — URGENT
- **Depends on:** —
- **Owner:** Track A
- **Status:** 🟥 CONFIRMED — code-verified

**Description.** The live dashboard confirms 39 rules across 6 groups / 4 tiers, **0 currently live**. Tier 1 shows "Not Implemented" despite "5/5 rules resolved". The repo confirms `implemented_sections` is a real, deliberately-tracked field (`pedagogy-engine.ts`), not a display bug.

**Action required.**

1. Verify whether the student-facing PAL flow calls this engine at all today, or bypasses it.
2. Roll out Tier 1 first (closest to ready).
3. Add a kill-switch per tier.
4. Log every rule firing to the Audit engine, so a rule that changes what a student sees is traceable — the same principle as AI Governance.

**Verified (2026-09-08) — question 1 answered: the student flow bypasses this engine completely.**

*Rule counts.* `database/seeders/PalPedagogyEngineSeeder.php` seeds exactly: **48 `Not Implemented`, 2 `Partially Implemented`, 2 `null`**. Zero `Implemented`. `implemented_sections` is not a stored field — it is counted at read time in `PedagogyEngineService::buildStats()` (line ~495) from each section's `implementation_status`, so the dashboard's "0 live" is arithmetic over seeded documentation strings.

*Who calls it.* `PedagogyEngineService` has exactly one consumer outside its own DI registration:

- `app/Http/Controllers/api/PAL/PedagogyEngineController.php` — methods `index()`, `chapters()`, `sections()`, `show()`. **All four are read-only GETs**, registered under `Route::prefix('api/pal/pedagogy-engine')` and described in `routes/pal_api.php:19` as *"Public/read-only pedagogy reference routes"*.

*What the student flow uses instead.* `PALAPIController` injects a **different class** — `PedagogyOrchestrationService` (`app/Http/Controllers/api/PAL/PALAPIController.php:7,24`) — and the live student endpoint `GET /api/pal/pedagogy/recommend/{learnerId}/{conceptId}` calls `$this->pedagogy->getRecommendation(...)` on *that* service, which never touches `pal_pedagogy_engine_rules`.

**So there are two pedagogy implementations:**

| | 39-rule engine | Student-facing engine |
|---|---|---|
| Class | `PedagogyEngineService` + `PedagogyEngineResolver` | `PedagogyOrchestrationService` |
| Backing data | `pal_pedagogy_engine_sections` / `_rules` | its own logic |
| Reached by | read-only dashboard API only | the live PAL student flow |
| Rules live | 0 of 52 rows | n/a — doesn't use them |

Note that `PedagogyEngineResolver` genuinely *does* execute rules against a real concept (tier-1…tier-5, engagement, trigger-map) — its own docblock says it is *"the rule execution the PAL_V4 comparison sheet lists as missing"*. That work exists and is reachable **only from the dashboard**. This is the sharpest example of #21 in the codebase: the execution layer was built, and then nothing was pointed at it.

**Revised action.** The rollout is not "turn on Tier 1" — it is "make the student flow call this engine at all". Concretely: have `PedagogyOrchestrationService::getRecommendation()` delegate to `PedagogyEngineResolver`, or retire one of the two. Deciding which of the two is canonical is a prerequisite to every other step, and belongs alongside #23, which asks the same question about a proposed *third* engine.

### ✅ Tier 1 wired to the authored rules — 2026-09-08

**Decision taken: the authored rules are canonical.** Tier 1 now resolves through `pal_pedagogy_engine_rules`. Tiers 2–5 remain hardcoded and are next.

**What the investigation found before any code changed.** The two engines are not "one real, one stub" — both are substantial. But `PedagogySelectorEngine` describes itself as *"Five-tier pedagogy selection"*, the same claim the authored rule set makes, and the two five-tier taxonomies are **different**:

| Tier | Authored (`pal_pedagogy_engine_sections`) | Live (`PedagogySelectorEngine`) |
|---|---|---|
| 1 | Mastery & Knowledge State | Hard Constraints |
| 2 | Engagement State | Learner State |
| 3 | Error & Misconception | Concept Requirements |
| 4 | Learning Style | Historical Effectiveness |
| 5 | Special State | Novelty & Fatigue |

And on Tier 1 specifically they disagreed on all three of metric, band count and boundaries:

| | Authored Tier 1 | Live selector |
|---|---|---|
| Metric | `bkt_mastery` (0.0–1.0) | `avg(Competency.mastery_score)` (0–100) |
| Bands | 5: `<0.40`, `0.40–0.69`, `0.70–0.84`, `0.85–0.92`, `>=0.93` | 3 + a flag: `<50`, `50–75`, `>75`, plus `low_confidence <40` |

The tie-breaker was that **`bkt_mastery` is the metric ESO already computes** (`LearnerNodeState.mastery_estimate`), so the authored rules were already consistent with the rest of the loop, and the live selector was the outlier.

**Changes:**

| File | Change |
|---|---|
| `app/Services/PAL/Pedagogy/PedagogyRuleBands.php` *(new)* | Reads tier-1 rows, exposes `masteryBands()` / `resolveMasteryBand()` / `available()` |
| `app/Services/PAL/Pedagogy/PedagogySelectorEngine.php` | New `resolveTier1Rule()`; Tier 1 decisions now come from the authored rows |
| `app/Services/Eso/EsoPolicyService.php` | New public `conceptMasteryEstimate()` — the canonical BKT read |
| `app/Providers/PALServiceProvider.php` | Selector binding updated |
| `tests/Feature/Pal/PedagogyTier1RuleDrivenTest.php` *(new)* | 8 tests |

**Four things worth knowing:**

1. **No prose parsing.** The boundaries come from `rule_meta.mastery_range`, which the seeder already writes as structured `{min, max}`. A rule without that structure is skipped, not guessed at — inferring a threshold from the `condition` text would let the engine silently disagree with what an author reviewed.
2. **It fails soft, by design.** `resolveTier1Rule()` returns null — falling through to the old hardcoded path — when the table is unseeded, the tenant is unknown, the learner has no evidence, the estimate lands in a gap between authored bands, or the band names no resolvable pedagogy. An environment that has not run `PalPedagogyEngineSeeder` behaves exactly as before.
3. **No-evidence is not a band.** A learner who has never attempted the concept yields a null estimate and is deliberately left unbanded, per ADR-001 §5 — banding them would serve remedial content to someone who may already know the material. `PedagogySelectorEngine` had already been fixed for this once (its `not_assessed` handling carries a comment about the same bug); the rule path inherits that discipline rather than reintroducing the defect.
4. **The rule's directives travel with the decision.** `content_type`, `bloom_ceiling`, `difficulty` and `scaffolding` ride along under `tier_1_rule`. Returning only a pedagogy name would drop everything else the author specified for that band.

**Two things found while wiring it:**

- **A dependency cycle**, which the first attempt hit: `PedagogySelectorEngine → EsoPolicyService → EsoEnrichmentResolver → PedagogySuggestedContentService → PedagogyOrchestrationService → PedagogySelectorEngine`. The container follows it until it exhausts memory. Resolved by resolving `EsoPolicyService` lazily at call time — the same optional-dependency pattern `PedagogySuggestedContentService` already uses for exactly this reason. Worth noting as a standing hazard: the PAL service graph has at least one genuine cycle in it.
- **Unknown pedagogy labels could silently become `inquiry_based`.** `normalizePedagogy()` answers `inquiry_based` both for a declared alias and for a label it does not recognise. The authored `concept_based` is a *declared* alias (`config/pal_v4.php`), so that mapping is intended — but an unrecognised label would have been indistinguishable. `PedagogyRuleBands` now drops labels that fail `isKnownPedagogy()`, so the band falls through instead of serving a pedagogy nobody wrote.

**Tests:** 8 added, all passing.

```
./vendor/bin/phpunit tests/Feature/Pal/PedagogyTier1RuleDrivenTest.php
OK (8 tests, 21 assertions)
```

**Pre-existing failures, untouched:** `tests/Feature/Pal/` has **5 failures on a clean checkout** (all in `PalMisconceptionAuthTest`, 403/200 expected vs 422 received) — verified by stashing these changes and re-running: 45 tests / 5 failures before, 53 / 5 after. Same five. Together with the 5 pre-existing `tests/Feature/Eso/` failures noted under #2, that is **10 red tests on `student_workflow` that predate this work** and have no owner.

### ✅ Kill switch and audit trail — 2026-09-08

The two remaining action points from this item, neither of which needed a decision.

**1. Per-tier kill switch** — `pal_content.pedagogy.tiers`, one flag per wired tier.

- Turning a tier off returns the selector to its **hardcoded path**, which is still present and still correct. Disabling degrades rather than breaks.
- Switching one tier off leaves the others running — asserted, since that is the entire point of a *per-tier* switch.
- **An unlisted tier defaults to OFF, not on.** A tier nobody explicitly enabled has not been reviewed for rollout, and defaulting to on would run unreviewed rules in front of students. Only tiers 1 and 4 are listed; 2, 3 and 5 are absent because they are not executable (prose-only thresholds, and tier 2's input is the mis-derived signal in #32).

**2. Rule firings are now queryable** — `tier_1_rule_key` and `tier_4_rule_key` on `pal_recommendation_log` (migration `2026_09_08_150000`, run).

`selection_reason` already contained the rule key inside a sentence, which is fine for reading one row and useless for the question that matters: *"this rule changed what students saw — how often did it fire, and what happened after?"* Prose cannot be grouped. With the key as a column, the log's existing `mastery_before` / `mastery_after` / `outcome` fields make a tier **evaluable** rather than merely switchable.

`NULL` means the hardcoded path resolved it and no authored rule fired — a real distinction, and the one that says how much of the engine is genuinely live.

**Tests:** 3 added (16 in the file), all passing. PAL suite 108 tests / 5 failures — the same 5 pre-existing.

**All four original action points on this item are now addressed** — the student flow calls the engine (tiers 1 and 4), each tier has a kill switch, and every firing is logged against its rule. What remains is tiers 2/3/5, which are blocked on data rather than on this work.

### ✅ Tier 4 wired — 2026-09-08

Tier 4 (Learning Style) now resolves through the authored rows too. It was next after Tier 1 because it is the only remaining tier whose inputs both exist and are unambiguous: its conditions key on a **categorical** `learning_style` value rather than a numeric threshold, the preference is already stored in `pal_learner_preferences`, and `rule_meta.h5p_priority` is structured.

**It changes format, not pedagogy — enforced, not just intended.** The authored section states Tier 4 *"does not change what is taught - it changes the format it arrives in"*. So `applyLearningStyleFormat()` wraps every return path of the selection and only reorders the H5P formats the chosen pedagogy already allows. It never edits `type`, and it cannot introduce a format the pedagogy's catalog entry excludes — a preference for Memory Game cannot make Memory Game valid for a pedagogy that disallows it. Both properties are pinned by tests.

Matching is on `rule_key` (`style-visual`, `style-read-write`, …), not on the `condition` prose — the same discipline as Tier 1's `mastery_range`.

**Tests:** 5 added (13 total in the file), all passing.

### ⛔ Tiers 2, 3 and 5 are NOT wirable as authored — 2026-09-08

I attempted these and stopped. The blocker is not effort, and it should not be worked around quietly.

**Tier 2 (Engagement State) — blocked on a signal that does not exist.** Its five rules threshold on `engagement_score`, `engagement_7d_trend`, `session_duration` and `return_streak`. None of those thresholds appear in `rule_meta` — they exist *only* inside the human-readable `condition` string (`'engagement_score < 50'`, `'engagement_7d_trend < -15%'`). Tier 1 was executable purely because someone wrote `mastery_range => ['min','max']` alongside the prose; tiers 2, 3 and 5 were not authored that way. The section's own `gap` field says it plainly: *"No engagement tracking, no intervention triggers"*.

**And a live behaviour is running on a mis-derived version of that signal.** This is the more serious half:

- `pal_learning_sessions` has **no creator anywhere in the application**. Its only writer is `pal:sync-learner-evidence`, which projects rows from `lms_online_exam` attempts.
- That command sets `'engagement_score' => $row->accuracy_rate` — **exam accuracy**, not engagement. It also sets `interaction_count` to right+wrong answers, where the authored signal is *"clicks, answers and drags per minute"*.
- `PedagogySelectorEngine::getLearnerState()` reads that column, derives `declining_engagement` from it, and on a decline **short-circuits selection to `game_based`** with the reason "Engagement is declining" — ahead of Tier 1.

So today a learner whose *exam accuracy* dipped is served game-based content on the stated grounds of falling engagement, and that decision outranks the authored Tier 1 rules. Two independent defects: a column whose name does not match its contents, and a rule firing on it.

**What Tier 2 actually needs, in order:** the authored **Engagement Score Composition** section is the one part of this that *is* fully structured — four weighted components (`time_on_task_ratio` 30%, `interaction_rate` 25%, `session_return_rate` 25%, `voluntary_extension_rate` 20%) and five labelled bands (0–39 Critical … 86–100 Flow State). Of the four inputs, `interaction_rate` and `session_return_rate` are computable from existing columns; `time_on_task_ratio` needs an expected-duration per content item and `voluntary_extension_rate` needs a required-content boundary, neither of which exists. Build the score first, then Tier 2 follows from it.

**Tier 3 (Error & Misconception) — blocked on a duplication decision, not on data.** Its rules are already implemented, in a different engine: ESO's D3 misconception path (`serve_contrast_pair`, the corrective ladder, the 2-consecutive-wrong trigger). Wiring Tier 3 into the selector would create a *second* misconception responder competing with ESO's. This is the same question as #23 and should be settled with it — not by implementing both.

**Tier 5 (Special State) — partially structured, low value alone.** Some rules carry usable `rule_meta` (`difficulty_delta`, `count: {min,max}`), but several restate Tier 2's engagement conditions, so it inherits Tier 2's blocker.

**Recommendation.** Do not add prose parsing to unblock these. The correct next step is to have the rule authors add structured `rule_meta` to tiers 2/3/5 in the same shape Tier 1 uses — that is a data change, reviewable by the people who own the rules, and it makes the remaining tiers wirable without a line of parsing heuristics. Until then, the honest position is that Tier 1 and Tier 4 are live and the rest are documentation.

---

## 16. CONFIRMED (code + screenshot): Leaderboard/Engagement duplicated between LMS and PAL Gamification

- **Phase:** Phase 2
- **Depends on:** —
- **Owner:** Track D
- **Status:** 🟥 CONFIRMED — code-verified

**Description.** `app/lms/leader-board` + `leader-board/team-master` (own admin/config + API) + `app/lms/social-collaboration/` sit inside the established, older LMS module (legacy route naming confirmed in code comments). Separately, `app/pal/new/gamification/team-challenges` + `challenge-mode` duplicate the same leaderboard concept. A live screenshot of the LMS version shows zero data (0 points, no ranked learners) — the same "built but inert" pattern as Assessment.

**Action required.** LMS's Leader Board + Social & Collaborative is the system of record (older, has its own admin/config layer). PAL's Gamification (badges, streaks, team-challenges, career-quest, personal-best, session-summary) should CONSUME this same data, not reimplement it. Once consolidated, feed this behavioural data into the Recommendation Engine as genuine engagement evidence.

**Verified (2026-09-08) — confirmed, and the split runs all the way to the database.** This is not two UIs over one backend; it is two full stacks:

| | LMS Leader Board | PAL Gamification |
|---|---|---|
| Pages | `app/lms/leader-board/page.tsx`, `app/lms/leader-board-master/page.tsx` | `app/pal/new/gamification/` — badges, streaks, team-challenges, challenge-mode, career-quest, personal-best, session-summary |
| Frontend data module | `app/lms/data/leaderBoard.ts` | `app/pal/new/data/gamification.ts` |
| API | `GET /api/lms/leaderboard`, `/leaderboard/filters`, `/leaderboard/rankings` | `/api/pal/new/gamification/*` (`routes/pal_api.php:356`) |
| Laravel controller | `App\Http\Controllers\lms\leaderboard\lbMasterController` | New PAL gamification controllers |
| Tables | legacy LMS leaderboard tables | `pal_gamification_*` (`2026_08_17_100000_create_pal_gamification_tables.php`) |

The PAL side is also the more actively built of the two: `team-challenges` supports POST (`createTeamChallenge`) and carries real business rules (`max_active_per_class_per_week`), while the LMS side is read-only ranking retrieval.

**One challenge to the recommendation as written.** The sheet names LMS as system of record because it is older and has an admin layer. The code suggests the opposite is easier: PAL's `pal_gamification_*` schema is purpose-built, has write endpoints and rules, and is where the new work is going; the LMS side is a legacy read surface. Choosing LMS means porting PAL's rules backwards into legacy tables. **This is worth an explicit decision rather than defaulting to seniority** — recommend re-examining before committing, and note that whichever wins, the loser becomes a redirect, not a second consumer.

### Decision brief — measured 2026-09-11

Row counts on `vivek_erp`:

| System | Tables | Rows |
|---|---|---|
| **LMS Leader Board** | `lb_master` (1 table) | 49 — configuration/master data |
| **PAL Gamification** | 13 `pal_*` tables | `pal_badges` 31, `pal_personal_bests` 16, `pal_learner_streaks` 10, `pal_learner_badges` 6, `pal_team_challenges` **0**, `pal_challenge_mode_scores` **0** |

**The duplication is narrower than "two leaderboards", and the overlapping part is empty on both sides.**

- **Only the competitive-ranking concept overlaps** — LMS `leader-board` versus PAL `team-challenges` / `challenge-mode`. Both have **zero rows**. Two empty implementations of the same idea.
- **Everything with actual learner data is PAL-only and not duplicated at all**: badges (31 defined, 6 earned), streaks (10), personal bests (16). The LMS side has no equivalent.
- **`lb_master`'s 49 rows are configuration, not rankings** — consistent with the screenshot showing 0 points and no ranked learners.

**What this changes about the decision:**

1. **It is cheap either way.** Nothing needs migrating — the contested feature holds no data on either side. The "port PAL's rules backwards into legacy tables" cost I flagged earlier does not apply, because there are no rules to port yet.
2. **The sheet's rationale does not survive the measurement.** "Older, has an admin/config layer" describes `lb_master` accurately, but an admin layer over an unused ranking table is not evidence of being the system of record — it is evidence of a feature that was configured and never ran.
3. **The real question is narrower than the item states**: not "which leaderboard wins", but *"do we want competitive ranking at all, and if so where does it live?"* The badges/streaks/personal-bests half is settled by default — it only exists in one place and has real data.

**Recommendation (still yours to take):** keep PAL as the home, delete or redirect the LMS ranking surface, and treat competitive ranking as a new feature decision rather than a consolidation. Worth weighing against #27's Alpha School evidence, which argues against leaderboards for learners at all — that is the substantive question hiding behind this one.

*Not verifiable from code:* the "zero data / 0 points, no ranked learners" screenshot. Both stacks read live tables, so that is a per-tenant data state, not a code defect.

---

## 17. ESO exists but isn't in the "New PAL" tab bar — navigation fix, not a missing feature

- **Phase:** Phase 1
- **Depends on:** —
- **Owner:** Track A
- **Status:** 🟥 CONFIRMED — navigation fix only

**Description.** `app/pal/eso/page.tsx`, `chapter/[chapterId]`, `knowledge-map/[conceptId]`, `mastery/[conceptId]` all exist, and `AdaptiveLearningButton` is already referenced inside `StudentDashboard.tsx`. ESO is simply not one of the 6 tabs (Framework / Content Model / UI / Pedagogy Engine / Administration / Gamification) in the tab bar being demoed.

**Action required.** Add ESO as a 7th tab in the New PAL tab bar. This is a navigation change, not a build — the feature is already live and even wired into the student dashboard.

**Verified (2026-09-08) — confirmed, and the fix is in the Laravel DB, not the React code.** Two separate navigation systems exist, and the item is true of both:

1. **The 6-tab bar being demoed is DB-driven, not hardcoded.** It comes from `tblmenumaster` rows under the "New PAL" level-2 parent. `database/migrations/2026_08_26_100000_add_new_pal_framework_ulu_pedagogy_engine_submodule_menus.php` enumerates all six and fixes their order: Framework (1), Content Model (2), Unified Learning Units (3), Pedagogy Engine (4), Administration (5), Gamification (6). **There is no ESO row.** Adding the 7th tab therefore means a new migration inserting a `tblmenumaster` row plus its `tblgroupwise_rights` grants — the same shape as that migration, which is idempotent and can be copied.

2. **A second nav component contradicts the first.** `app/pal/new/_components/NewPalNav.tsx` hardcodes `SUB_MODULES` with only **two** entries — Content model and Coherence map — plus an Overview link. Coherence map isn't in the DB menu at all, and four of the six DB tabs aren't in this component. It also carries an `available: false` convention for unbuilt pages that is currently unused.

**Scope correction.** The item is right that this is navigation, not a build — but it is not a one-line change, and "add ESO as a 7th tab" leaves the two nav sources still disagreeing. Do both: insert the ESO menu row, and reconcile `NewPalNav.tsx` against `tblmenumaster` so there is one source of truth for what New PAL contains. Note also that the ESO pages sit under `app/pal/eso/*`, not `app/pal/new/*` — which is the same `pal/new` vs. legacy-path split flagged in #11.

### ✅ ESO registered as the 7th sub-module — 2026-09-08

Three coordinated changes, because a menu row alone would render a tab that does not navigate:

| Repo | File | Change |
|---|---|---|
| `next_lms_erp` | `database/migrations/2026_09_08_100000_add_new_pal_eso_submodule_menu.php` *(new)* | ESO row under the New PAL parent at `sort_order` 7, link `new_pal.eso`, rights mirrored from the parent |
| `lms_k12` | `app/data/routeMapper.ts` | `'new_pal.eso': '/pal/eso'` — without this the tab renders and goes nowhere |
| `lms_k12` | `app/pal/new/_components/NewPalNav.tsx` | ESO added to `SUB_MODULES`; the nav divergence documented in the component |

**On rights.** The migration mirrors the New PAL parent's grants (Teacher / LMS Teacher / Admin / **Student**) rather than the narrower Teacher/Admin-only grants Content Model and Administration carry. That distinction matters more for ESO than it did for its siblings: ESO is the one sub-module whose primary audience *is* the student, so inheriting the narrow grants would have hidden the feature from the people it was built for. Admins can still revoke per role from Access Roles.

### ⚠️ Corrections from measuring the menu table — 2026-09-08

I asserted the DB menu state from **reading a migration file** rather than querying, and got two things wrong. Measured directly:

**1. The ESO migration HAS been run, and the row exists.** `tblmenumaster` id 658, `link = new_pal.eso`, `status = 1`, sort_order 7, with 5 `tblgroupwise_rights` rows — matching the parent's 5 grants exactly. `migrate:status` lists it as run. I reported it as "pending, not run" several times; that was wrong.

**2. The New PAL parent's children are not the six I described.** Under parent id 531:

| sort | Name | link |
|---|---|---|
| 2 | Content Model | `new_pal.content_model` |
| 3 | Unified Learning Units | `new_pal.ulu` |
| 4 | Coherence Map | `coherence.map` |
| 4 | Pedagogy Engine | `new_pal.pedagogy_engine` |
| 5 | Administration | `new_pal.administration` |
| 6 | Gamification | `new_pal.gamification` |
| 7 | ESO | `new_pal.eso` |

Three things fall out of that:

- **Framework is not a child of New PAL at all.** The row exists (id 605, `new_pal.frameworks`, level 3) but hangs off **parent 327**, a different branch. The "6-tab New PAL bar including Framework" described throughout this tracker is not what the menu table contains.
- **Coherence Map IS a child**, at `coherence.map` — so `NewPalNav.tsx` listing it was correct and my note calling it "in the component without a menu row" was wrong.
- **Two rows share sort_order 4** (Coherence Map and Pedagogy Engine), so their display order is undefined.

**Only one of my migrations remains pending: `2026_09_08_140000_add_new_pal_reports_submodule_menu`** (no `new_pal.reports` row exists yet). I have been saying "two pending menu migrations" — that was also wrong.

**Original status, now superseded: migration written and registered as Pending.** Applying it inserts a row into the shared dev database, which is a state change rather than a code change, so it is left for whoever owns that environment:

```
php artisan migrate --path=database/migrations/2026_09_08_100000_add_new_pal_eso_submodule_menu.php
```

It is idempotent (re-running updates the existing row rather than duplicating it) and has a working `down()`. Note there is one *other* migration already Pending on this branch (`2026_09_07_100000_create_lms_content_resource_metadata_table`) that is not mine — a plain `php artisan migrate` would run that too.

**Frontend verification:** `npx tsc --noEmit` is clean across `lms_k12`.

**What I deliberately did not do.** `NewPalNav.tsx` still lists only three of the seven sub-modules, and still lists Coherence map, which has no menu row at all — so the two navigations disagree in both directions. I added ESO and documented the divergence in the component rather than expanding the hardcoded list to all seven, because the real fix is for that component to read the same menu rows the tab bar does. Expanding the list would have changed the UI on eight pages while leaving the actual duplication in place. **This remains open** and is the same class of problem as #11.

---

## 18. Learning Path — confirmed genuine gap, no dedicated page exists anywhere

- **Phase:** Phase 2
- **Depends on:** —
- **Owner:** Track A
- **Status:** 🟥 CONFIRMED — genuine gap

**Description.** A repo-wide search for a dedicated Learning Path page returned no match — only incidental mentions inside Gamification pages and ESO's knowledge-map. This is a real gap, not a visibility problem like ESO (#17).

**Action required.** Build a dedicated Learning Path surface showing the sequence ESO / Pedagogy Engine has decided for this student. This is the concrete, visible expression of PAL loop step 4 (Personal Learning Plan), which currently has no UI home.

### ✅ Built — 2026-09-08

**The gap turned out to be wider than "no page".** `EsoPolicyService::studentDashboard()` already resolved the student's full ordered chapter sequence — subject order, then chapter order, filtered to ESO-ready chapters — and then **discarded all of it except the current chapter**. So the plan the loop follows was computed on every dashboard load and never exposed. The missing piece was data as well as UI.

| Repo | File | Change |
|---|---|---|
| `next_lms_erp` | `EsoPolicyService` | `orderedReadyChapterIds()` extracted from `studentDashboard()`; new public `learningPath()` |
| `next_lms_erp` | `EsoEngineController` + `routes/pal_eso_api.php` | `GET /api/pal/eso/learning-path/{learnerId}?syear=` |
| `next_lms_erp` | `tests/Feature/Eso/EsoLearningPathTest.php` *(new)* | 8 tests |
| `lms_k12` | `app/pal/data/pal-eso.ts` | `fetchLearningPath()` + types |
| `lms_k12` | `app/pal/eso/learning-path/page.tsx` *(new)* | The surface |

**Decisions worth noting:**

1. **The ordering was extracted, not copied.** `studentDashboard()` and `learningPath()` now share one helper, so "what order does this student work in" has a single definition. A test asserts the dashboard still resolves to the same chapter it did before the extraction.
2. **It does not call `chapterDashboard()` per chapter.** That would run `nextAction()` and `masterySignals()` for every chapter in the year just to render a list. The next action only matters for the chapter the student is actually on, so it is resolved once, for that one — and with `silent: true`, so viewing a plan writes no decision-log row.
3. **"Not started" means no evidence, not "first in the list".** A chapter is `in_progress` if any concept has been touched. Deriving it from position would have erased work a student had already done in a later chapter.
4. **The plan says *why*.** `current.rule_fired` carries the rule that chose the next step, and the UI shows it. A sequence without a reason is a list, not a plan.
5. **Chapters with no ESO-ready concept are excluded.** They cannot be worked through, so listing them would promise something the loop cannot deliver.

**Tests:** 8 added, all passing. ESO suite 200 tests / 5 failures — the same 5 pre-existing. `npx tsc --noEmit` clean across `lms_k12`.

### ✅ Both follow-ups closed — 2026-09-08

Finished before starting anything else, since one of the two was a defect introduced by the build above rather than a pre-existing gap.

**1. `syear` now comes from the session, not the system clock.** The first cut read `new Date().getFullYear()`, which matches nothing else in PAL and is wrong on its own terms — a plan is scoped to an *enrolment*, and a school year is not a calendar year. It now uses `buildSessionContext().syear`, the same resolution `StudentDashboard` uses, so both screens agree on which year a student is being shown. When the session carries no year the page says so and stops, rather than defaulting to a value that would silently render a plan for the wrong enrolment.

**2. The page is reachable.** `app/dashboard/StudentDashboard.tsx` now links to it under the chapter view — "View your full learning path". That placement is deliberate: the dashboard shows the chapter a student is on, and the plan is the thing that chapter sits inside, so the link belongs where the narrower view ends. It also avoids the three-part menu treatment #17 needed, because this is a student surface rather than a New PAL sub-module.

`npx tsc --noEmit` clean across `lms_k12`. No backend change in this pass, so the PHP suites are unaffected.

**Genuinely still open:** the page resolves the learner with `defaultLearnerId()` only, while the ESO concept flow also honours `useViewAsStudent()` so a teacher can view a specific student's screen. A teacher opening the Learning Path directly would therefore see their own (empty) path rather than a student's. The student route in — the dashboard link — is unaffected. Worth reconciling when someone next touches the view-as plumbing.

---

## 19. CBSE Exam Blueprint generator

- **Phase:** Phase 2/3
- **Depends on:** Universal Content Model, `board_compliance` metadata, Question Intelligence Engine
- **Owner:** Track A — HOME FINALIZED: Examination module
- **Status:** ⬜ Not Started

**Description.** Board-compliant exam blueprint (question-type distribution, marks weightage, difficulty spread per CBSE's specific pattern) — connects directly to the `board_compliance` metadata defined for the Universal Content Model (#8).

**Action required.** HOME DECISION FINALIZED: this lives under Examination (Blueprint centralized as an engine; CBSE/ICSE/state-board rules are configuration data on top of it) — NOT under New PAL's Administration tab. This removes the earlier ambiguity between the two options.

### ✅ Feasibility engine built — 2026-09-08

`GET /api/pal/eso/reports/blueprint-feasibility?board=&standardId=&subjectId=&blueprint=` answers: for this board's paper pattern, does the tagged pool hold enough board-compliant items of each type, and where is it short?

Built as configured in the decision above — the engine is generic, the board patterns are configuration (`config/pal_exam_blueprint.php`), so a new board is a new entry rather than new code.

| File | Change |
|---|---|
| `config/pal_exam_blueprint.php` *(new)* | Blueprint patterns + difficulty-spread targets |
| `app/Services/PAL/Examination/ExamBlueprintService.php` *(new)* | The engine |
| `AttainmentReportController` + `routes/pal_eso_api.php` | Staff-only endpoint; the staff/institute guard extracted and now shared with #7's report |
| `tests/Feature/Pal/PalExamBlueprintTest.php` *(new)* | 11 tests |

**The most important thing about this build is what it does NOT do: it emits no questions.**

Selecting items requires knowing which are reserved for the exam and therefore withheld from PAL's adaptive practice — and that is **#6, still an open team decision**. Generating a paper now would settle that decision silently, and would risk handing a student an exam item they had already met as practice. Counting the pool needs no such rule, so counting is what this does. A test asserts the payload contains no `question_id`. The generator that emits items belongs *after* #6.

**Why this is useful with zero authored data** — unlike the last few items, which are inert until authoring runs. With an empty pool it reports "the whole paper is unfillable, and here is the shortfall per section in marks", which is exactly the worklist authoring needs. The report gets more accurate as data arrives rather than only becoming useful then.

**Decisions worth challenging:**

1. **Marks must match the slot exactly.** A 5-mark long answer cannot fill a 3-mark slot without changing the paper, so it is not counted as available.
2. **Calibration is counted but never required.** A paper made of uncalibrated items is a valid paper; requiring psychometrics would exclude every newly authored question from the paper it was written for (the #8 argument, applied here).
3. **Unrated difficulty is reported as `unrated`, not folded into a band.** Most of the estate has no difficulty yet, and assigning one would fake the spread.
4. **A section naming an unregistered category is a config error, not an empty section** — a typo in the pattern must not read as "we happen to have no items of this type".
5. **Both the stated total and the section sum are reported**, so a pattern whose sections do not add up to its own total is visible rather than silently trusted.

**⚠️ The shipped CBSE pattern is illustrative and labelled as such in the config.** Board patterns change by year, subject and paper, and I did not have an authoritative specification. It exists so the engine has something real-shaped to run against; **a school must replace it with its board's published pattern before any output is used for a real paper.**

**Tests:** 11 added, all passing. PAL suite 95 tests / 5 failures — the same 5 pre-existing.

---

## 20. Naming decision: PAL → "LearnSense" (student-facing brand) — pre-decided, NOT executed this week

- **Phase:** Not this week — sequence after adaptivity ships
- **Depends on:** #15 Pedagogy Engine (live rollout), #1 Assessment Bank coverage
- **Owner:** Track A
- **Status:** 🟪 Pre-decided, but not executed

**Description.** The architecture review proposes renaming PAL's student-facing surface to "LearnSense", keeping PAL as the internal/technical term underneath. Shortlist reviewed: LearnSense (best fit — knowledge-sensing framing), LearnPath, IntelliLearn.

**Action required.** Do not rename this week. Good idea, wrong timing: the name promises the system senses what you're struggling with and adapts — rename it when that is actually true. Keep PAL as the internal term. Do not build a demo-only copy for this.

---

## 21. Cross-cutting pattern: "built but inert" recurring across 4+ systems

- **Phase:** Phase 1 — URGENT
- **Depends on:** —
- **Owner:** Track A + Track D
- **Status:** 🟥 CONFIRMED — code-verified

**Description.** Assessment Bank (2.3% coverage), Pedagogy Engine (0 live rules of 39), KASBA (ported endpoints, zero callers), Leaderboard (live UI, zero data) — four independent confirmations of the same pattern: infrastructure/UI shipped ahead of the data or wiring that makes it real.

**Action required.** Treat this as a named, tracked pattern, not four unrelated bugs. Before demoing any of these four screens, confirm out loud whether what's shown is real or a placeholder. Name the recurrence and the fix separately in delivery.

**Verified (2026-09-08) — confirmed for 3 of the 4 legs, and the pattern is sharper than stated.**

| Leg | Verdict | Evidence |
|---|---|---|
| Assessment Bank | ✅ Confirmed, and worse | The bank is measured on a dashboard while the consumer (`diagnosticItems()`) reads a different table entirely — #2 |
| Pedagogy Engine | ✅ Confirmed, and worse | `PedagogyEngineResolver` fully implements rule execution; its only reachable caller is a read-only dashboard controller — #15 |
| KASBA | ⚠️ Weak leg | Nothing is inert; one route is merely unlinked — #10. Drop or restate this leg |
| Leaderboard | ⚠️ Partly | The *code* on both sides is live and wired; only the *data* is empty, which is a tenant state, not a build state — #16 |

**Restatement.** The pattern the code actually supports is narrower and more useful than "UI shipped ahead of data". In the two strong cases it is:

> **A working implementation exists and is reachable only from the dashboard that measures it, never from the flow that needs it.**

Both times, the missing piece is a single call site — not missing data, and not missing code. That reframes the fix from "populate content" (weeks of authoring) to "wire the consumer" (a change of a few lines each), and it explains why the dashboards look healthy while the loop does not work.

**Revised action.** Add a standing check to review: *for every dashboard metric, name the production code path that reads the same source.* If the only reader is the dashboard, the feature is not shipped. That check would have caught both #2 and #15 at build time.

### ✅ The check has now been RUN — 2026-09-11

It had been written down and not performed. Running it across every `semantic_intelligence` section the Content Model dashboard measures:

| Section (dashboard metric) | Production reader | Verdict |
|---|---|---|
| `misconceptions` | `EsoPolicyService`, `AiTutorContextService`, `ContentIntelligenceService` | ✅ consumed by the student flow |
| `prerequisites` | `EsoPolicyService`, `CoherenceMapRepository` | ✅ consumed |
| `real_world_applications` | `ConceptRelevanceResolver` (via ESO) | ✅ consumed |
| `assessment_blueprint` | `LessonIntelligenceService` / `MicroPlannerService` | ✅ consumed — teacher lesson planning |
| `learning_outcomes`, `pedagogy` | `LessonIntelligenceService`, `MicroPlannerService` | ✅ consumed |
| **`assessment_rubrics`** | **none** | ⛔ **dashboard and projectors only** |

**`assessment_rubrics` — the section behind the headline "Assessment Bank (calibrated) 2.3%" metric — has no production reader anywhere.** Its only consumers are `ContentModelCoverageService` (the metric itself), the projectors that feed that dashboard, `SemanticSourceRepository`/`SemanticIntelligenceSource` (data access), and `PedagogyEngineResolver` — which #15 established is reachable only from the read-only dashboard API.

Not the student flow. Not lesson planning. Nothing.

This is a **third independent confirmation** of the same defect, and the first at the *data-source* level: #2 found it at the query level (`diagnosticItems()` reads `pal_question_metadata` instead), #9 found the ULU view reads the same unused columns, and this finds that no code path in the application consumes the section at all.

**The check works.** Written down it changed nothing; run once across seven sections, it isolated the one that is measured but never read — in a few minutes, using nothing but grep. That is the argument for making it standing rather than aspirational.

---

## 22. ESO cross-domain universalization (Fees ESO, Career ESO, Teacher ESO) — DESIGN PLACEHOLDER ONLY, do not build

- **Phase:** Design Now / Build Later
- **Depends on:** —
- **Owner:** Track A
- **Status:** 🟪 Design placeholder only

**Description.** Proposal to generalize ESO (Executable Steps + Outcomes) beyond PAL into Fees exception handling, Career pathway logic, and Teacher development. But ESO has exactly ONE real consumer today (PAL) — and even there, the Pedagogy Engine that decides what ESO should execute has 0 live rules. Generalizing now would repeat the exact premature-abstraction pattern already flagged and rejected for the Event Bus.

**Action required.** Design the ESO contract shape now (cheap), so a future generalization isn't a rewrite. Do NOT build Fees ESO or Career ESO until PAL's own ESO is fully live AND a second module has a genuine, specific need — not a hypothetical one.

---

## 23. "Decision Engine" — verify it isn't Pedagogy Engine's Tier 1 rules, renamed

- **Phase:** Design Now / Build Later
- **Depends on:** #15 Pedagogy Engine
- **Owner:** Track A
- **Status:** 🟠 Needs Team Confirmation before any build

**Description.** Proposal to add a separate "Decision Engine" ("student should not advance — prerequisite not mastered"), distinct from the Recommendation Engine and Agentic AI.

**Action required.** The example given ("block advancement without prerequisite mastery") is structurally identical to a Tier 1 Mastery & Knowledge State rule already defined among the Pedagogy Engine's 39 rules. CONFIRM directly whether this is a genuinely new capability before building it — given 4 confirmed duplicate systems found this session, a confirmation before build is warranted.

### ✅ ANSWERED by measurement — 2026-09-11

The confirmation this item asked for did not need a meeting. Queried directly against the seeded rules:

**It is NOT Tier 1 renamed.** Tier 1's five rules are purely mastery-band → content-type routing:

```
mastery-below-40   bkt_mastery < 0.40            -> Serve Concept Learning V1
mastery-40-69      BETWEEN 0.40 AND 0.69         -> Serve Practice L1 -> L2 -> L3
mastery-70-84      BETWEEN 0.70 AND 0.84         -> Practice L3 + L4, spaced review
mastery-85-92      BETWEEN 0.85 AND 0.92         -> Practice L4 + L5, peer teaching
mastery-93-plus    >= 0.93                       -> Expanded tasks, cross-curricular
```

None of them gates advancement. Searching **all 52 authored rules** across every tier for `prerequisite` or `advance` in either the condition or the action returns **zero matches**. My own earlier note calling it "structurally identical to a Tier 1 rule" was wrong, and was written from the tier's *name* rather than its contents.

**But the capability does already exist — in ESO, not the Pedagogy Engine.** `EsoPolicyService::prerequisiteGate()` (rule D2) is exactly *"student should not advance — prerequisite not mastered"*, and it is live and student-facing today. It is more developed than the proposal: it also distinguishes a prerequisite that clears the threshold but whose evidence has gone stale, probing to re-establish it rather than blocking outright.

**So the answer to this item is: do not build it.** Not because it duplicates Tier 1 — it does not — but because it duplicates a live D2 rule that already handles the case better than the proposal describes. The right follow-up is to check whether the proposer wants something D2 does not already do, and if so, to state that difference precisely.

This is the fifth duplicate-system finding of the session, and the second where the duplication was with **ESO** rather than with the system named in the proposal.

---

## 24. STRATEGIC CALL: K-12 proves ESO first, not G2G

- **Phase:** Phase 2
- **Depends on:** ESO (exists), Pedagogy Engine (needs Tier 1 live), reconciled 4-part ESO shell
- **Owner:** Track A
- **Status:** ⬜ Not Started

**Description.** G2G has zero confirmed end-to-end ESO evidence. K-12 has richer existing data to build from (Concept Intelligence, live curriculum, a defined 12-step loop) and a clearer measurable signal (mastery %, time saved) than a generic work task. K-12 becomes the proving ground; G2G's scheme gets reconciled against whichever version proves out first.

**Action required.** Build the two worked examples below (#25, #26) first — both Core/Easy concepts, deliberately simple — and run them end-to-end before attempting a harder misconception-heavy concept or looping back to unify with G2G.

---

## 25. Worked ESO example #1 — "Definition of Integers" (Mathematics, live concept)

- **Phase:** Phase 1
- **Depends on:** Concept Intelligence (live), ChatbotPanel (live), ESO pages (exist)
- **Owner:** Track A
- **Status:** ⬜ Not Started

**Description.**

- **Task:** student defines/classifies integers.
- **Execution Model:** AI Tutor-assisted by default; escalates to teacher after 2 failed attempts.
- **Governance:** must ground explanation in existing Concept Intelligence data; must NOT give away answers to assessment items — Socratic prompting until 2 genuine attempts are logged (Alpha School / TimeBack rule).
- **Evidence:** 2 independent correct classifications across 2 scenario-based questions.

**Action required.** Build this as the FIRST real ESO execution in the whole company (ahead of G2G) — use the real 4-part shell (Task Details / Execution Model / Governance / Evidence) with K-12 detail fields nested inside.

**CONFIRMED THRESHOLDS (configurable, not hardcoded):**

| Capability Confidence | Action |
|---|---|
| < 60% | Relearn |
| 80–90% | Independent application |
| > 90% | Mastery verification |
| > 95% | Delayed retrieval + stable mastery |

Capability Confidence must be evidence-driven (attempt variety, hint dependence, independent vs. assisted performance) — NOT raw score.

### ✅ Capability Confidence built — 2026-09-08

The part of this item that is a specification rather than a decision. `app/Services/Eso/CapabilityConfidenceService.php` implements the CONFIRMED thresholds, configurable as the item requires (`pal_content.capability_confidence`).

**It is not the mastery estimate.** That was the explicit requirement, and it is what the tests pin: two learners with an identical BKT estimate of 0.90 land in different bands, because one answered five distinct questions unaided and the other was walked through the same question five times with hints. Three weighted inputs — mastery 0.60, independence 0.25, variety 0.15 — so what a learner can do still dominates, but never alone.

**Decisions worth challenging:**

1. **Independence requires hint-free AND independent mode, not either.** A hint-free answer inside a guided session was still scaffolded; counting it as independent overstates what the learner did alone.
2. **Variety is distinct questions against a target, capped.** Ten attempts at one item is one piece of evidence repeated, not ten.
3. **Below 3 attempts it returns null, not a low score** — ADR-001 §5 again: absence of evidence is a reason to gather more.
4. **The evidence travels with the score.** A number that decides whether a student is sent back through a concept must be interrogable by the teacher acting on it.
5. **Misconfigured weights throw rather than renormalise.** A typo that silently rescaled every band would move the relearn boundary with nobody noticing.

**⚠️ A gap in the source specification.** The sheet gives `<0.60 relearn`, `0.80–0.90 independent application`, `>0.90 mastery verification`, `>0.95 delayed retrieval` — and says nothing about **0.60–0.80**. I named that band `consolidating` (action: continue practice) rather than folding it into `relearn`, because silently extending relearn up to 0.80 would send learners back through content they had largely demonstrated. **This label needs confirming** — it is the one place here where I filled a gap in the spec rather than implementing it.

**Tests:** 8 added, all passing. ESO suite 208 tests / 5 failures — the same 5 pre-existing.

**Still open on #25/#26:** the worked examples themselves. Both need authored content for two specific live concepts — task, execution model, governance and evidence definitions — which is authoring, not engineering. The scoring they band on now exists.

---

## 26. Worked ESO example #2 — "Observations Indicating a Chemical Reaction" (Science, live concept)

- **Phase:** Phase 1
- **Depends on:** Concept Intelligence (live), Misconception Library (live per Content Model dashboard)
- **Owner:** Track A
- **Status:** ⬜ Not Started

**Description.**

- **Task:** identify 4 indicators of a chemical reaction.
- **Execution Model:** self-paced PAL by default (Core/Easy concept); AI Tutor only if the Misconception Library flags a specific error.
- **Governance:** must use existing tagged misconception data, not a generic explanation; must not advance to the next chapter without mastery evidence on this Core concept (prevents cumulative science mis-advancement).
- **Evidence:** correct identification across 2 scenario-based questions.

**Action required.** Second proof case — deliberately a different subject (Science vs. Maths) to confirm the ESO shell generalizes across subjects before scaling to more concepts.

---

## 27. AI Tutor governance rule: no direct answers without genuine attempts

- **Phase:** Phase 1
- **Depends on:** AI Tutor design, ChatbotPanel, ESO Governance field on every learning ESO
- **Owner:** Track A
- **Status:** ⬜ Not Started

**Description.** Per Alpha School / TimeBack's explicit design choice (chat disabled specifically to prevent cheating — "90% of kids use chatbots to cheat"), the AI Tutor must not provide the direct answer to an assessment item. Socratic-guided questioning only; direct-answer mode unlocks only after N logged genuine attempts.

**Action required.** Bake this into EVERY learning ESO's Governance field as a standard clause, not a one-off — this is a proven external lesson, not a hypothetical risk.

---

## 28. Within-grade pacing — Needs Team Decision

- **Phase:** Design Now / Build Later
- **Depends on:** Grade Enablement layer (Course Catalog & Taxonomy tab)
- **Owner:** Track A
- **Status:** 🟠 Needs Team Decision

**Description.** Alpha School / TimeBack is explicitly NOT grade-locked — students work at their actual mastery level regardless of age. ScholarClone's Course Catalog architecture IS grade-based (Platform > School > Grade > Student), matching board-compliant Indian K-12 structure.

**Action required.** DECIDE explicitly: within a grade, can a student move at their own mastery pace (skip ahead / re-approach, scoped inside the grade boundary), or are they paced with the class? Don't let this default silently — board-compliance constraints may force a specific answer, but it should be a stated decision, not an accident.

### Decision brief — measured 2026-09-11: it has ALREADY defaulted silently

This item's own warning was *"don't let this default silently"*. Measured against the code, it already has.

**`EsoPolicyService` contains no class-pacing constraint of any kind.** It never references `division_id`, `section_id`, cohort or class progress. Progression is gated by exactly two things:

1. **prerequisites** — `prerequisiteGate()` / D2
2. **the student's own mastery** — `conceptStatusFor()` + `isConceptSettled()`

`nextEligibleConcept()` walks the chapter's concepts and returns the first one *this student* has not settled. `learningPath()` orders by subject then chapter and marks position from *this student's* status. No peer, class or teacher position enters either.

**And the grade boundary is already enforced**, from the other direction: `orderedReadyChapterIds()` resolves the student's standard from `tblstudent_enrollment` and only ever lists chapters for that standard. A student cannot reach another grade's content.

**So the live behaviour is precisely option (a) in this item:** self-paced within the grade, hard-bounded by the grade. Skip-ahead and re-approach both work today, scoped inside the grade, exactly as described.

**That reframes the decision.** It is not *"should we build self-pacing?"* — that is built and running. It is:

> **Do we ratify the behaviour already in production, or add a constraint to hold students to the class pace?**

The second is the one that costs engineering, and nobody has asked for it.

**Two things still genuinely needing a human:**

- **Board compliance.** The item flags that board rules may force class pacing. Nothing in the code speaks to that, and it cannot be measured — it is a regulatory reading.
- **Nobody decided this.** The behaviour is the accident this item warned against. Ratifying it is cheap and probably correct, but it should be ratified rather than left as an emergent property of the implementation.

---

## 29. Universal Evidence & Confidence Framework — 3 scores, not 1

- **Phase:** Design Now / Build Later
- **Depends on:** Evidence & Content Engine (Central Engines tab)
- **Owner:** Track D
- **Status:** 🟪 Design Now / Build Later

**Description.** Concept Intelligence's current single blended confidence score (e.g. 0.9) is replaced by THREE distinct scores:

1. **Intelligence Confidence** — is the knowledge correct?
2. **Evidence Confidence** — how strong/varied is the supporting evidence?
3. **Capability Confidence** — does this student actually have it?

Different data types (Bloom's classification vs. Misconceptions) get different baselines — not one number for the whole concept.

**Action required.** Prove this on Concept Intelligence ONLY first (one concept, one confidence framework, working end-to-end) before extending to Career Intelligence. Do NOT build all three at once. The three-score split is the reason the current single blended confidence number breaks under scrutiny.

---

## 30. Career Intelligence confidence disaggregation (Architect — 82%, evidence: 47, gap: 31) — SEQUENCED AFTER, not now

- **Phase:** Phase 3+
- **Depends on:** #29 Universal Evidence & Confidence Framework (must work on Concept Intelligence first), Career Intelligence (does not exist as a working product)
- **Owner:** Track D
- **Status:** 🟪 Design placeholder only

**Description.** Target-state example: a career recommendation shown with a confidence score + evidence count + explicit gaps, rather than a bare recommendation.

**Action required.** Do NOT build this before the confidence framework is proven on one working concept. Building the framework directly for a not-yet-built product would repeat the exact premature-abstraction pattern already flagged multiple times this session.

---

## 31. Vision/camera-based Learning Coach — CORRECTION + legal review required before any engineering

- **Phase:** Design Now / Build Later
- **Depends on:** Confidence Engine, ESO
- **Owner:** Track D
- **Status:** ⬜ Not Started

**Description.** CORRECTION to an earlier discussion: Alpha School / TimeBack's publicly stated design is **screen-share / on-device data only**. Camera-based observation is a separate, more invasive capability and was NOT verified as an existing competitor practice. The product philosophy is adopted regardless: the framing must be "the system supports the learning interaction", not "AI watches the child".

**Action required.** Any screen-observation OR camera-based capability requires LEGAL REVIEW (India's DPDP Act — minors' data, consent, retention) BEFORE any engineering discussion, given the higher stakes than a normal feature decision. Do not build or scope this until that review happens.

### ✅ Legal review brief prepared — 2026-09-11

**[PAL_LEARNING_COACH_LEGAL_REVIEW_BRIEF.md](PAL_LEARNING_COACH_LEGAL_REVIEW_BRIEF.md)** — no code, and no legal advice. The item gates engineering behind a review, so the unblocking action is to make that review possible against a concrete proposal.

Three things the brief establishes:

1. **Screen-observation and camera are separate submissions.** Screen/on-device has a documented competitor precedent; camera does **not** — that was an assumption in the original discussion. Camera also captures third parties who are not users and have not consented (siblings, parents, classmates). Approving one must not imply the other.
2. **Ten questions for counsel**, framed as decisions engineering cannot make — whether school enrolment covers consent or separate verifiable parental consent is needed; whether consent is freely given if declining means losing a mandated platform; whether sampled screen observation constitutes *behavioural monitoring of children* under the Act, which is the likely decisive question; retention, access and residency.
3. **What a "yes" commits the product to building** — a per-learner consent record, a hard technical gate preventing capture without valid consent, an automated retention clock, an access-control model with an audit trail, and a learner-visible indicator that observation is active. None of it exists, and none of it should be built before the review concludes.

The brief keeps the tracker's own framing at the front: the system must support *the learning interaction*, not *"AI watches the child"* — and notes that if a capability cannot be described to a parent in those terms, that says something about the capability rather than the wording.

---

## 32. `engagement_score` holds exam accuracy, and a live rule fires on it

- **Phase:** Phase 1 — URGENT
- **Depends on:** —
- **Owner:** Track A
- **Status:** 🔴 NEW — found 2026-09-08 while attempting #15 Tier 2

**Description.** Three facts that only matter in combination:

1. **Nothing in the application creates a `pal_learning_sessions` row.** There is no `LearningSession::create()` call anywhere. The table's only writer is the `pal:sync-learner-evidence` command.
2. **That command fills the column with the wrong quantity.** It projects rows from `lms_online_exam` attempts and sets `'engagement_score' => $row->accuracy_rate` — the share of questions answered correctly. It also sets `interaction_count` to right+wrong answers, where the authored signal is *"clicks, answers and drags per minute"*. So a "session" is an exam attempt, and its "engagement" is its mark.
3. **A live selection rule reads it.** `PedagogySelectorEngine::getLearnerState()` derives `declining_engagement` by comparing the mean of the learner's 3 most recent `engagement_score` values against the 3 before them. When it declines, `select()` returns `game_based` at confidence 92 with the reason **"Engagement is declining"**, short-circuiting everything below it — including the Tier 1 rules.

**What this means in practice.** A learner whose recent *exam accuracy* dipped — which is the ordinary signal that a concept is getting harder — is served game-based content and told, in the decision's own reason string, that their engagement is falling. The intervention fires on precisely the learners who need harder-concept support, and it outranks the authored Tier 1 mastery rules.

Note the two defects are independent and both need fixing:

- a column whose name does not describe its contents (and a second, `interaction_count`, in the same row), and
- a behavioural rule firing on that column as though it did.

**Action required.**

1. **Decide the near-term behaviour.** Either gate `declining_engagement` off until a real engagement score exists, or keep it and rename the signal to what it measures (`declining_accuracy`) and re-derive whether `game_based` is the right response to that. This is a student-visible behaviour change either way, so it is a decision, not a cleanup — flagged rather than actioned.
2. **Rename or re-purpose the column** so `engagement_score` stops carrying accuracy. Anything else reading it inherits the same confusion (`PedagogyEngineResolver` already averages it for its engagement composition view).
3. **Then** build the real score from the authored Engagement Score Composition, which unblocks #15 Tier 2 — see that item for which of its four inputs exist today.

**Relationship to #21.** This is the same pattern as the other four legs, with a twist that makes it worse: the other cases are inert — built, not wired, visibly empty. This one is *wired to the wrong thing*, so it produces confident output and looks like it works.

---

## 33. PAL ownership check was unreachable for API callers

- **Phase:** Phase 1
- **Owner:** Track A
- **Status:** ✅ FIXED 2026-09-08 — `tests/Feature/Pal/` now fully green (108 tests, 0 failures)

**How it was found.** The five `PalMisconceptionAuthTest` failures had been red on this branch throughout, and were repeatedly noted as "pre-existing, unowned". Investigating them turned up two separate problems stacked on each other — the second hidden by the first.

**Problem 1 — a fixture gap, masking everything.** `SessionMiddleware` (via `HydratesLegacyApiSession`) returns **422 "Academic Term Date Expired"** when the tenant has no `academic_year` row spanning today. No test seeded one, so every token call 422'd *before* reaching the controller. Five authorization tests were failing for a reason unrelated to authorization. Fixed by seeding a current term, as every live tenant has.

**Problem 2 — the real defect, revealed once the 422 cleared.** Three tests then returned **200 where 403 was expected**:

`palController::resolveAuthorizedContext()` short-circuits on `session('user_id')` and returns immediately — never reading the requested `user_id`. Because `SessionMiddleware` now hydrates a session *from the bearer token*, API callers took that branch, so **the entire ownership check below it was unreachable for them**.

Consequences, stated precisely:

- **No cross-student data leak.** The endpoint served the caller their *own* data, not the requested student's.
- But `user_id` was silently ignored, so **a staff request for a student's data returned the staff member's own** — a functional bug for the people the parameter exists for.
- And **no cross-user request was ever refused**, so the 403 paths were dead code.

**The fix.** The ownership rule is extracted into `resolveTargetStudent()` and applied on both identity paths — one rule, two identity sources, since a hydrated session and a bearer token carry the same facts and the rule must not depend on which one arrived.

**The distinction that had to be preserved.** A genuine web session must ignore a client-supplied `user_id` entirely (a logged-in user must not escalate by editing a query string) — there is an explicit test for that, and my first attempt broke it by 403-ing instead of ignoring. Both callers have a session, so the discriminator is the **bearer token**: present means API, and the ownership rule applies; absent means browser, and session identity wins unchanged.

**Result:** `tests/Feature/Pal/` 108 tests, 0 failures — down from 5.

---

## 34. ESO precedence bug and two stale fixtures

- **Phase:** Phase 1
- **Owner:** Track A
- **Status:** ✅ FIXED 2026-09-08 — `tests/Feature/Eso/` now fully green (208 tests, 0 failures)

The other five long-standing failures. Three were fixtures, two were precedence — and one of those was a genuine engine bug.

**Fixtures (3 tests).** `setRetrievalDue()` seeded `retention_stage => 1`, while the three ladder-walk tests state in their own comments that they start at **stage 0** ("Walk three rungs: stage 0 -> 1 -> 2 -> 3") — so every walk finished one rung out. One other test genuinely wants to start a rung up ("Pass: stage 1 -> 2"), so the stage is now a parameter defaulting to 0, which is where `masteryVerdict()` actually leaves a freshly mastered node. Hardcoding either value made one group fail.

**Fixture (1 test).** `makePrerequisiteOfMainConcept()` deliberately seeds the main concept *"so D1-entry doesn't fire ahead of D2"* — but the D1-precedence test asserts that D1 **does** fire, and its own comment says "the target concept itself has no learner state at all". It was calling a fixture built to prevent the thing it was testing. Now parameterised.

**A real engine bug (1 test) — D3 precedence was per-node, not concept-wide.**

The misconception check sat *inside* `nextAction()`'s node loop, so precedence depended on `sort_order`: a node with a due retrieval ordered before a flagged node returned `retrieval_due`, and the misconception was never reached. **The engine tested retention while a confirmed error stood uncorrected.**

The branch's own comment had always claimed *"D3 keeps its precedence"* — the intent was right and the implementation only honoured it within a single node. The scan is now hoisted above the loop, so any flagged node outranks any other node's retrieval, matching how `masteryVerdict()` already evaluates `$misconceptionActive` across the whole concept.

**This one changes student-facing behaviour**: a confirmed misconception now interrupts a due retrieval check that would previously have been served first. That is what the rule was documented to do.

**Test state across the whole PAL/ESO estate is now clean:**

| Suite | Before | After |
|---|---|---|
| `tests/Feature/Pal/` | 5 failures | **0** (108 tests) |
| `tests/Feature/Eso/` | 5 failures | **0** (208 tests) |

The one remaining red test anywhere is `tests/Unit/CareerIntelligence/CaiCoreServiceArchitectTest` — a Neo4j connection error, environmental and unrelated (see the memory note on Neo4j auth).

---

## 35. Self-review of the session's changes

- **Status:** ✅ 10 of 12 findings fixed 2026-09-08; 2 accepted as-is with reasons

Eighteen phases of code had accumulated without a review pass, and several defects during the session were caught only by tests. A review of the whole working-tree diff found **12 issues**. The ones worth naming:

**Fixed — would have misled a user or an operator:**

1. **A strict `purpose` miss raised a false teacher escalation.** It fell through to the C7 exhaustion branch, claiming *"every approved variant has been served"* with `teacher_alert: true`. Since `learning_purpose` is deliberately unbackfilled, **every strict request today** would have escalated to a teacher about content that was never classified. Now a distinct `no_content_for_purpose` result, no alert.
2. **The recommendation log would 500 on any environment behind migration `2026_09_08_150000`.** The insert was guarded by `hasTable()` only, while writing the new rule-key columns — turning a missing audit column into an outage. Now column-guarded.
3. **Fatigue rotation corrupted the rule audit.** `fatigue->rotate()` replaces the pedagogy after Tier 1 chose it, but `tier_1_rule` stayed attached — so the log credited a rule with a decision that was overridden, poisoning the exact "how did this rule perform?" analysis the column exists for. The attribution is now dropped on rotation.
4. **`supersede()` could break the version chain.** It only set `superseded_by_id` on whatever was *active*, not on the version passed in — so superseding a draft linked the wrong row, and `successorChain()` broke at the point someone was trying to trace. That chain is the property the whole feature rests on.
5. **The learning path could strand a student.** A first chapter entirely locked or stale is not `complete` but yields no next concept, so `current` pinned there and every later chapter was skipped — an empty plan. `path_complete` was also true whenever no current chapter was found, which would have told a blocked student they had finished the year; it now requires every chapter actually complete.
6. **Blueprint feasibility counted out-of-syllabus items.** An empty chapter scope skipped the filter entirely, so a standard with no chapters was assessed against the whole institute's item bank and could report `feasible: true`.

**Also fixed:** missing route constraints (`/tutor-context/123/abc` was a 500, now 404); unguarded index creation in two migrations (a re-run after partial failure died on "Duplicate key name"); an explicit first-institute resolution replacing a silent `(int)` cast; and a `typical_marks` / `marks_each` mismatch in the sample blueprint that would have had authors tagging items at a weight the pattern never asks for.

**Both remaining findings were then fixed too — 2026-09-08:**

- **`diagnosticItems()` served the easiest N, not a spread.** I had deferred this as "a psychometric decision", which was the wrong call: the comment already stated a spread was intended, so taking the first N of a difficulty-ordered list was simply not doing what it said. New `spreadByDifficulty()` samples evenly across the ordered list, keeping both ends and the middle represented, with the unsampled remainder appended in order as the fallback pool. A test pins that a 2-item diagnostic over difficulties 1–5 does **not** return the two easiest and **can** reach the hardest.
- **The curriculum-version scope key did not constrain the case it existed for.** `subject_id` was nullable and in the UNIQUE index; MySQL treats NULLs as distinct, so a standard-wide version — the only reason the column is nullable — was exactly the one `resolve()` could duplicate. Migration `2026_09_08_160000` normalises NULL to a `0` sentinel and makes the column NOT NULL; `CurriculumVersion::subjectKey()` is now the single place that mapping lives. The migration **refuses to run** if pre-existing duplicates would block the index, rather than merging them — collapsing two curriculum versions is a content decision, not a migration's to make.

**Both suites green after all 12 fixes: PAL 109/0, ESO 209/0.**

### Frontend verification — 2026-09-08

The 15 changed `lms_k12` files had only ever been checked with `tsc`. Running the repo's own linter found **2 real errors**, both mine: the Learning Path and Attainment pages called `setState` synchronously inside a `useEffect` body (`react-hooks/set-state-in-effect`), which causes cascading renders.

The codebase already had the answer — `StudentDashboard` defers with `queueMicrotask` and says why in a comment. Both pages now follow that convention.

**All 16 changed frontend files: `eslint` clean, `tsc --noEmit` clean.**

### Full backend verification — 2026-09-11

Throughout this work only the PAL, ESO and Unit suites were being run, while shared files were changed that are used well beyond them — `palController` (auth), `ContentIntelligenceService`, `VariantRouterService`, `QuestionMetadata`. Every suite in the repo has now been run:

| Suite | Tests | Result |
|---|---|---|
| `tests/Feature/Pal/` | 109 | ✅ 0 failures |
| `tests/Feature/Eso/` | 209 | ✅ 0 failures |
| `tests/Feature/CareerIntelligence/` | 6 | ✅ 0 failures |
| `tests/Feature/` (ModuleResolution, McpToolBindings, Example) | 13 | ✅ 0 failures |
| `tests/Unit/` | 176 | ⚠️ 1 error |
| **Total** | **513** | **1 error** |

The single error is `CaiCoreServiceArchitectTest` failing to connect to Neo4j — the known-broken Neo4j auth on this estate, environmental and on no code path touched by this work.

**Every other test in the repository passes**, including the 10 that were red before this session began.

### Frontend suite — 2026-09-11

`npm test` in `lms_k12` (node test runner over `lib/**` and `packages/**`) had also never been run here:

```
tests 231 · pass 231 · fail 0
```

### Both repos, complete

| | Tests | Result |
|---|---|---|
| `next_lms_erp` (phpunit) | 513 | 1 error — Neo4j connection, environmental |
| `lms_k12` (node) | 231 | ✅ 0 failures |
| `lms_k12` eslint / tsc | 16 changed files | ✅ clean |
| **Total** | **744** | **1 pre-existing environmental error** |

**Both suites remain green after the fixes: PAL 108/0, ESO 208/0.**

---

## Cross-cutting themes

1. **"Built but inert" (#21)** — Assessment Bank, Pedagogy Engine, KASBA, Leaderboard. UI shipped ahead of data/wiring in four independent places.
2. **Duplication (#10, #11, #16)** — KASBA ×3–4, Framework routes ×4, Leaderboard ×2. The counter-example that proves it's solvable: ChatbotPanel (#12).
3. **Premature abstraction (#22, #23, #29, #30)** — generalize only after ONE consumer works end-to-end.
4. **Decisions still owed by the team (#2, #6, #9, #23, #28)** — five open items that block design, not implementation.

## Suggested sequencing

**Revised after the 2026-09-08 verification.** The order below changed because the two headline items turned out to be wiring problems, not content problems:

1. **Point `diagnosticItems()` at the calibrated bank and add a calibration filter (#2).** Small change, and it is the reason the mastery signal is invalid today. Doing #1 before this delivers nothing.
2. **Decide which pedagogy engine is canonical, then wire the student flow to it (#15).** Also small; `PedagogyEngineResolver` already executes the rules. Fold #23 into this decision — it asks the same question about a proposed third engine.
3. **Close #9.** Answered; no work needed. Its two dependents (#3, #6) are unblocked.
4. **Restate #21 and adopt the review check** it produces ("name the production reader of every dashboard metric") — that check is what prevents the next two instances.
5. **Then** the content work: #1 (populate the bank), #4–#8.
6. **Cheap wins, any time:** #17 (menu row + nav reconcile), #14 (AI Tutor on existing parts), #11 (singular/plural route rename).
7. **Needs a decision before code:** #16 (which leaderboard is system of record — the code argues against the sheet's answer), #6, #28.

**Original ordering, kept for reference — do this week (Phase 1 — URGENT):** #1, #2, #9, #15, #21 — plus the two cheap wins #17 (navigation) and #14 (AI Tutor on existing parts).

**Next (Phase 2):** #3–#8 content model, #10/#11/#16 deduplication, #13, #18, #24, #25, #26, #27.

**Design now, build later:** #22, #23, #28, #29, #30, #31.

**Explicitly deferred:** #20 (rename).
