# PAL V4 — Addendum, revised against the canonical implementation

**Status:** Reconciliation report + revised plan, for team review
**Supersedes:** *PAL V4 — What to Add to the Solution* (5 Sep 2026)
**Verified against:** `TrizERP/next_lms_erp` @ `student_workflow` / `origin/development` (5 Sep 2026)
**Method:** every claim below re-checked against the repository and, where stated, against
runtime. No claim is carried over from either the original addendum or the first fact-check
without independent verification.

---

## 0. Why this revision exists

The original addendum was audited against `TrizERP/next_lms_erp @ b674df7`. That commit is
`origin/main`. It is **not an ancestor** of the branch PAL V4 is built on.

```
                    9d06c92fb (PR #581, ~19 Mar 2026)
                   /                        \
   origin/main ---+ 153 commits              + 734 commits --- origin/development
   (b674df702)      "LIVE_ERP by Rajesh"       PAL V4 / ESO      (0bfe544e5)
   default branch   production line                              \
                                                                  + 1 commit
                                                                  student_workflow
                                                                  (44a8c8cd8)
```

The auditor read the repository's **default branch**, which was the reasonable thing to do.
The addendum is therefore *accurate for what it read* and *stale for the work in flight*.
Nothing in it was careless; the finding is a branch-topology problem, not an analysis error.

---

## 1. Phase 1 — Canonical implementation

### 1.1 Which branch has the most complete PAL V4?

`origin/development` (and `student_workflow`, which equals it plus one unrelated commit).

| Branch | `app/Services/Eso/` | PAL controllers | `tests/Feature/` | PAL/ESO migrations |
|---|---:|---:|---:|---:|
| `origin/main` | **0** | 1 | 1 | 3 |
| `origin/development` | **6** | 11 | 25 | 34 |
| `student_workflow` | **6** | 11 | 25 | 34 |
| `origin/sonika_palv` | 2 | 11 | 24 | 34 |
| `origin/zeel_api` | 2 | 11 | 24 | 34 |

`sonika_palv` and `zeel_api` carry a partial, older ESO snapshot (2 of 6 services). They are
not candidates.

### 1.2 Which branch is canonical?

**For PAL V4 work: `origin/development`.** Repository evidence is unambiguous — it is the only
integration branch containing the whole engine.

**Whether `development` should be merged into `main` is a HUMAN DECISION and is not
answerable from repository evidence.** The two branches are not "one behind the other":

- `development` is **734 commits ahead** of `main`
- `main` carries **153 commits not in `development`**, dating from **19 Mar 2026** to
  **2 Sep 2026**, with subjects of the form `LIVE_ERP: by Rajesh: <date>` and
  `TRIZ_ERP: by Rajesh: <date>`

`main` reads as a **live/production line under separate maintenance**. Only **2** of its 153
divergent commits touch PAL/ESO paths at all. Deciding whether these lines converge, when,
and in which direction is a release-ownership call. This document does not make it.

### 1.3 Why did the original audit see an older implementation?

Because `origin/main` **is** `b674df702`. Every blocker it reported was **true on `main`**:

| Claim | Verified on `main` @ `b674df7` |
|---|---|
| No `PalApiAuth.php` | Confirmed absent |
| `pal.auth` not registered in `Kernel.php` | Confirmed absent |
| 7 of 8 PAL controllers missing | Confirmed (1 of 8 present) |
| No `app/Services/Eso/` | Confirmed (0 files) |
| Test coverage effectively zero | Confirmed (1 file) |

### 1.4 Is PAL/ESO work stranded on `student_workflow`?

**No.** `student_workflow` is ahead of `development` by exactly one commit — `44a8c8cd8
feat(api): implement fees menu category management` — which touches a fees controller,
four fees migrations and `routes/api.php`. **Nothing PAL or ESO.**

The working tree is clean; no PAL/ESO change is uncommitted.

### 1.5 What must be reconciled before further PAL V4 development?

**Nothing blocks PAL V4 work on `development`.** It is self-consistent, tested and running.

Two items need a human owner, neither of which blocks development:

1. **`main` <-> `development` convergence** — 153 vs 734 divergent commits, ~6 months apart.
   This is a release decision, not a merge chore. It should not be attempted as a
   side-effect of PAL work.
2. **Default-branch signalling** — while `main` is the default branch and lacks PAL V4,
   *every* future reviewer will repeat this audit and reach the same wrong conclusion.
   Whether that is fixed by merging, by changing the default branch, or by a README note is
   a decision for the repository owner. **It will recur until it is addressed.**

---

## 2. Phase 2 — Re-audit of the original addendum

Classification against `student_workflow`. File paths and line numbers are current.

### Section 1 — the three blockers

| Claim | Classification | Evidence |
|---|---|---|
| 1.1 `pal.auth` applied but not implemented | **STALE** | `app/Http/Middleware/PalApiAuth.php` exists; `app/Http/Kernel.php:78` registers `pal.auth`; `:79` registers `eso.student` |
| 1.1 Four role scopes unenforced | **VERIFIED — ALREADY IMPLEMENTED** | `PalApiAuth::resolveTargetLearner()` + `authorizeLearner()`; student self-only, staff class-scoped, admin institute-scoped. `EsoStudentOnlyAuth` adds a student-only gate on execute routes |
| 1.2 7 of 8 controllers absent | **STALE** | All 8 present in `app/Http/Controllers/api/PAL/` |
| 1.3 `mastery_score` has no writer | **STALE** | `app/Console/Commands/PAL/DeriveCompetenciesCommand.php:283`; `app/Http/Controllers/lms/pal/palController.php:212` |
| 1.3 `avg('mastery_score')` returns 0 for every learner | **STALE** | Live DB: **24,003** `pal_competencies` rows, **23,876** with `mastery_score > 0` |
| 1.3 `?? 0` violates Principle 7 | **VERIFIED — STILL OPEN** | 6 sites — see section 3 |
| 1.4 ESO D1–D5 does not exist | **STALE** | `app/Services/Eso/` — 6 services; **31** `D1`–`D5` rule identifiers in `EsoPolicyService.php` |
| 1.4 Test coverage is zero | **STALE** | **188** test methods across 22 files. ESO suite: **124 tests / 445 assertions, green** |

**Answering D6 from repository evidence: ESO lives at `app/Services/Eso/`, on
`origin/development`. It is built, tested and exercised end-to-end in a browser. D6 is
CLOSED.**

### Section 3 — build order

| Item | Classification |
|---|---|
| B1 `PalAuth` middleware + scopes | **STALE — do not rebuild** |
| B2 Land the 7 controllers | **STALE — do not rebuild** |
| B3 Evidence write path | **VERIFIED — ALREADY IMPLEMENTED.** `EsoEvidenceBridge` -> `MasteryUpdater::recordBatch()` -> `pal_learning_evidence` -> BKT replay -> `pal_concept_mastery` -> graph outbox. Its own acceptance test ("a student answers one question and a mastery value changes") was executed live in a browser: 7 evidence rows, 1 `pal_concept_mastery` row |
| B4 `NOT_ASSESSED` distinct from zero | **PARTIALLY IMPLEMENTED** — see section 3 |
| B5 Mastery-definition ADR | **REQUIRES HUMAN DECISION** — but note ESO already ships a *working* numeric policy (K >= 0.80, A >= 0.70, no active misconception). The ADR should ratify or amend that, not invent one from scratch |
| B6 Critical-path E2E tests | **PARTIALLY IMPLEMENTED** — 188 methods exist incl. cross-learner isolation; no cross-*tenant* denial test found |

### Section 4 — step-by-step additions

| Item | Classification | Evidence |
|---|---|---|
| Step 1 — single anchor problem | **VERIFIED — STILL OPEN** | `diagnosticItems()` serves a fixed-length random sample (8 items, `->shuffle()`), all selected before the first answer |
| Step 2 — gap in one sentence | **PARTIALLY IMPLEMENTED** | `EsoPalRenderer::dashboardNextStep()` renders one next-step line; it is not the "2 of 3 ideas" gap sentence |
| Step 4 — three-way branch | **VERIFIED — ALREADY IMPLEMENTED** | CFU exits three ways: correct -> `practice`; incorrect -> `reteach` (different content); distractor mapped to a misconception -> `serve_contrast_pair`. D3 wins the routing over "didn't understand" |
| Steps 4/6/8 — three distinct checks | **VERIFIED — ALREADY IMPLEMENTED** | Step 4 = `check_understanding` (2 items, hints allowed, **not mastery evidence**); Step 6 = `masteryVerdict()`; Step 8 = `retrievalCheck()`, which **can revoke mastery** (status -> `learning`, estimate −0.2, ladder reset) |
| Steps 5–6 — distance to mastery in evidence | **MISSING** | No `demonstrations`/`remaining` concept anywhere in ESO. The *rows* to compute it exist (`eso_response_log` carries `hint_used`, `mode`, `correct`, `question_id`) |
| Steps 5–6 — attempt cap | **VERIFIED — ALREADY IMPLEMENTED** | `CFU_MAX_CYCLES = 2`, then release to guided practice |
| Steps 5–6 — teacher escalation | **PARTIALLY IMPLEMENTED — built but unreachable** | `MisconceptionLibraryService` computes `teacher_alert` (`:68`, `:111`, `:125`). ESO never calls `detectAndRoute()`; it calls only `selectCorrective()`. No teacher surface consumes it |
| Step 8 — growth moment | **MISSING** | Retention verification exists; before/after snapshot does not |
| Step 9 — continuous write | **VERIFIED — ALREADY IMPLEMENTED** | Evidence published at three operation boundaries (`scoreDiagnostic`, `recordAttempt`, `retrievalCheck`) plus four outcome events. Not terminal. A student who abandons mid-session leaves a record |
| Session A vs Session B | **MISSING** | ESO never touches `pal_learning_sessions`; no scheduler drives Session B |
| Time budget | **REQUIRES HUMAN DECISION** — and the addendum's advice (publish the rule, not the minute table) should be kept verbatim |
| Short-form daily mode | **MISSING** | No resume, no recall-only, no single-concept entry |

### Section 5 — open decisions

| # | Classification |
|---|---|
| D1 mastery definition | **REQUIRES HUMAN DECISION** — ratify or amend the shipped policy |
| D2 `STALE_MASTERY` decay | **PARTIALLY IMPLEMENTED + DECISION.** A 30-day staleness rule exists for *prerequisites* (`PREREQUISITE_STALE_AFTER_DAYS`) and a 5-rung retention ladder `[2,7,30,60,180]` exists. Neither expresses concept-level `STALE_MASTERY` |
| D3 rule-tier precedence | **REQUIRES HUMAN DECISION** |
| D4 cold start | **PARTIALLY IMPLEMENTED** — ESO handles a zero-history learner (diagnostic -> teach -> CFU). The *motivational* cold start is undefined |
| D5 missing content | **PARTIALLY IMPLEMENTED** — ESO degrades honestly (no rich media -> text; no material -> recap omitted). No teacher-facing gap report |
| D6 where ESO lives | **CLOSED — answered above** |
| D7 canonical implementation | **CLOSED for branch (`development`); REQUIRES HUMAN DECISION for `main` convergence** |

---

## 3. Phase 3 — Principle 7: `NOT_ASSESSED`

### 3.1 The confirmed violations

Six sites convert "no evidence" into a fabricated number:

| File | Line | Fallback |
|---|---:|---|
| `app/Services/PAL/AI/AIOrchestrationService.php` | 365 | `?? 50` |
| `app/Services/PAL/AI/AIOrchestrationService.php` | 396 | `?? 0` |
| `app/Services/PAL/Content/ContentIntelligenceService.php` | 270 | `?? 50` |
| `app/Services/PAL/Framework/FrameworkProgressService.php` | 161 | `?? 0` |
| `app/Services/PAL/Intelligence/RecommendationEngine.php` | 194 | `?? 50` |
| `app/Services/PAL/Pedagogy/PedagogySelectorEngine.php` | 193 | `?? 0` |

A seventh, in the growth path, matters more than its size suggests:
`app/Services/PAL/Gamification/SessionSummaryService.php:148` — `$before = $mastery ?? 0.0`.
An unmeasured "before" defaults to zero, which **manufactures growth from nothing**. This is
precisely the sentence Step 8 is supposed to be able to say truthfully.

`?? 50` is the more serious of the two forms: `?? 0` asserts the learner knows nothing;
`?? 50` asserts they are *average*, which is a confident claim about a learner the system
has never seen.

**ESO itself contains no such violation.**

### 3.2 The model already exists — this is propagation, not invention

Two correct implementations are already in the tree:

```php
// app/Services/Eso/EsoPolicyService.php — nullable value + explicit flag
protected function signal(string $key, string $label, string $description,
                          ?float $value, int $responseCount): array
{
    return [ 'value' => $value, 'has_evidence' => $value !== null, ... ];
}
```

```php
// app/Services/PAL/Intelligence/LearnerStateEngine.php:60-67 — and its comment
// already states the principle exactly:
//   "No pal_competencies rows means this learner has not been assessed yet --
//    that is not the same as a measured 0% mastery, and must not be reported
//    as one ... callers that care about the distinction should check has_data first."
$hasData = $competencies->isNotEmpty();
$masteryScore = $hasData ? $competencies->avg('mastery_score') : 0;
```

`LearnerStateEngine` is half-fixed: it *emits* `has_data`, but still emits `0` alongside it,
and no consumer is obliged to read the flag.

### 3.3 Is a schema change required? **No.**

`pal_competencies.mastery_score` is `float NOT NULL DEFAULT 0`
(`database/migrations/2026_06_11_131905_create_pal_tables.php:46`).

It does not need to become nullable, because **"not assessed" is already representable as the
absence of a row**, which is exactly what `$competencies->isNotEmpty()` tests. Making the
column nullable would introduce a *second*, ambiguous encoding of the same state
(row-with-null vs no-row) and would require backfilling 24,003 rows for no gain.

**Historical rows are therefore unaffected.** No migration, no backfill.

### 3.4 The contract to adopt

1. **Aggregates return `?float`, never a coalesced number.** `avg()` already returns `null`
   on an empty set — the bug is purely the `??`.
2. **Every payload carrying a mastery number carries `has_evidence` beside it**, matching
   `EsoPolicyService::signal()`.
3. **Consumers branch on the flag, not on the number.** For `NOT_ASSESSED`:
   - **Recommendation / pedagogy / content selection must not select on mastery band.**
     They should route to *diagnosis* (ESO's own D1 entry), not to remediation and not to
     enrichment. Treating an unassessed learner as low-mastery pushes remedial content at a
     student who may know the material; `?? 50` pushes mid-difficulty content at a student
     who may be lost. Both are guesses presented as measurements.
   - **UI shows "Not assessed yet", never a number, never a bar.** ESO's mastery page already
     does this (`No evidence` + `has_evidence`).
4. **Growth/before-after refuses to render** when the "before" is unmeasured, rather than
   defaulting to zero.

### 3.5 Tests required

- Unit, per fixed service: empty `pal_competencies` -> `null` + `has_evidence === false`,
  never `0` and never `50`.
- Contract: recommendation/pedagogy/content for a zero-history learner routes to diagnosis.
- Regression: a learner **with** evidence is unchanged (guards against fixing this by
  making everyone unassessed).
- API: `has_evidence` present wherever a mastery number is exposed.
- UI: "Not assessed yet" renders instead of `0%`.
- Growth: unmeasured "before" suppresses the growth statement.

---

## 4. Phase 4 — Revised build order

`B1`, `B2` and `B3` are **removed as implementation tasks** — they are complete on the
canonical branch.

| # | Item | Status | Depends on |
|---|---|---|---|
| 1 | **Principle 7 / `NOT_ASSESSED`** | **Missing (code)** — model exists, 7 sites to fix, no schema change | — |
| 2 | **D1 numeric mastery definition** | **Policy decision only** — ratify/amend shipped thresholds | — |
| 3 | **D7 `main` <-> `development` convergence** | **Policy decision only** | — |
| 4 | **D3 rule-tier precedence** | **Policy decision only** | — |
| 5 | **D2 concept-level `STALE_MASTERY`** | **Partially implemented** — prerequisite staleness + retention ladder exist; concept-level state does not | 2 |
| 6 | **D4 cold-start motivational behaviour** | **Partially implemented** — engine handles it; product behaviour undefined | 1, 2 |
| 7 | **D5 missing-content reporting** | **Partially implemented** — degrades honestly; no authoring gap report | — |
| 8 | **Distance to mastery (in evidence)** | **Missing** — rows exist; needs a mastery policy to count toward | 1, 2 |
| 9 | **Teacher escalation** | **Partially implemented — built but unreachable.** Wire ESO's repeated-misconception loop to `MisconceptionLibraryService`'s existing `teacher_alert`, and give it a teacher surface | — |
| 10 | **Before/after growth evidence** | **Missing** — needs an honest "before" | 1, 8 |
| 11 | **Step 1 anchor problem** | **Missing** — replaces fixed random diagnostic | 2 |
| 12 | **Step 2 gap sentence** | **Partially implemented** | 8 |
| 13 | **Session A / Session B orchestration** | **Missing** — ESO never touches `pal_learning_sessions` | — |
| 14 | **Short-form ~15-minute mode** | **Missing** | 13 |
| 15 | **Cross-tenant denial tests** | **Missing** (cross-learner isolation is covered) | — |
| 16 | **D6 ESO location** | **CLOSED** | — |
| 17 | **Step 4 three-way branch** | **ALREADY IMPLEMENTED — preserve** | — |
| 18 | **Continuous Step 9 writes** | **ALREADY IMPLEMENTED — preserve** | — |
| 19 | **Step 8 delayed verification** | **ALREADY IMPLEMENTED — preserve** | — |
| 20 | **Attempt cap** | **ALREADY IMPLEMENTED — preserve** | — |

### Recommended order

**Now, no decisions needed —**
1. **Principle 7 / `NOT_ASSESSED`** (item 1). Contained, no migration, and it is the one
   confirmed way the system currently lies to a student.
2. **Teacher escalation wiring** (item 9). The alert logic already exists; connecting it is
   small and it is where the school's value sits. *(The addendum's observation that no
   teacher appears anywhere in the loop is correct and remains the sharpest product gap.)*
3. **Cross-tenant denial tests** (item 15).

**Blocked on decisions —**
4. **D1** (item 2) unblocks distance-to-mastery, `STALE_MASTERY` and the anchor problem —
   three of the highest-value items. It is the critical path.
5. Then items 8 -> 12 -> 10 (distance -> gap sentence -> growth), which is the addendum's
   motivational core in dependency order.

**Separately owned —**
6. **Branch convergence** (item 3). Needs a release owner, not a developer.
7. Session orchestration and short-form mode (items 13, 14) are a distinct workstream.

---

## 5. What carries over unchanged

The original addendum's product thinking is sound and survives this revision intact:

- **Gap-First Learning**, and the naming argument against "Learning Hunger". Keep verbatim.
- **The ten principles**, especially 7 (never pretend to know) and 10 (the write is not a
  step) — the latter is already honoured in code.
- **Publish the time-budget rule, not the minute table.**
- **Session A / Session B are different session types**, and numbering 1->9 invites a single
  linear controller. Still true; still unbuilt.
- **Everything in "Deliberately excluded"** — no Challenge Engine as a service, defer Career
  Intelligence while recording its guardrail, do not renumber the nine steps, no
  Zeigarnik-based product claims.
- **The closing sentence's logic** — a gap the student can tell is wrong costs more trust
  than saying nothing. Its three named preconditions are now met; Principle 7 is what still
  stands between the current system and a truthful gap.

---

## 6. One paragraph for the team

> The PAL V4 addendum was audited against `main`, which is the default branch and does not
> contain PAL V4. Its three blockers — auth, controllers, evidence write path — are all
> complete on `development`, along with the ESO D1–D5 engine, 188 tests, and an
> evidence -> mastery -> graph pipeline that has been exercised end-to-end in a browser. Do
> not rebuild them. What genuinely remains is one honesty bug and a set of product
> decisions: six services still turn "we have never assessed this learner" into `0` or `50`,
> the teacher alert exists but nothing calls it, and distance-to-mastery cannot be built
> until someone writes down what mastery numerically is. `main` and `development` have been
> divergent for six months in both directions; converging them is a release decision that
> needs an owner, and until it happens every future reviewer will read `main` and reach the
> same conclusion this addendum did.
