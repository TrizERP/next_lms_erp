# ADR-001 — PAL Mastery Definition

**Status:** ACCEPTED — implemented
**Date:** 5 September 2026
**Audited against:** `TrizERP/next_lms_erp` @ `student_workflow` / `origin/development`
**Implemented in:** `EsoPolicyService`, `PedagogySelectorEngine`, `RecommendationEngine`, `SessionSummaryService`

## Canonical ownership — recorded explicitly

| Role | Owner |
|---|---|
| **Learner-facing mastery policy and state** | **ESO** — `learner_node_state` + `EsoPolicyService::masteryVerdict()`. Everything a student is told about their own mastery derives from here. |
| **Cross-system probabilistic evidence/mastery model** | **BKT** — `pal_concept_mastery.p_mastery` via `MasteryUpdater`. Feeds the coherence map, knowledge graph and Career Intelligence. Never shown to a student as "your mastery"; never gates an ESO decision. |
| **`pal_competencies.mastery_score`** | **Historical accuracy metric only.** `correct / attempts × 100`, no recency, no independence, no misconception term, and on a different scale from the other two. **Not the authoritative learner-facing mastery state.** |

No new service was introduced.

---

## 1. Context

PAL V4 cannot build distance-to-mastery, the gap sentence, anchor selection, `STALE_MASTERY`
or growth evidence until "mastery" has one definition. Today it has **three**, on **three
different scales**, computed by three subsystems that do not reference each other.

| # | Owner | Column | Scale | Definition |
|---|---|---|---|---|
| 1 | `EsoPolicyService` | `learner_node_state.mastery_estimate` | 0–1 | Linear ±0.2 step per scored attempt, clamped. Verdict: mean(K) ≥ 0.80 **and** mean(A) ≥ 0.70 **and** no active misconception |
| 2 | `MasteryUpdater` | `pal_concept_mastery.p_mastery` | 0–1 | Bayesian Knowledge Tracing replayed over full response history. Verdict: `p_mastery >= gate && credited`, gate from `pal_concept_metadata.mastery_gate`, default **0.70** |
| 3 | `DeriveCompetenciesCommand` | `pal_competencies.mastery_score` | **0–100** | Raw accuracy: `correct / attempts × 100`. No recency, no difficulty, no independence |

All three are live. Definition 1 drives what the student experiences; definition 2 feeds the
coherence map and the knowledge graph; definition 3 is what `RecommendationEngine`,
`PedagogySelectorEngine`, `ContentIntelligenceService` and `AIOrchestrationService` actually
read when they decide what to show.

**This ADR proposes which of these is canonical, and defines the evidence rules underneath
it. It changes no thresholds and no code.**

---

## 2. Decision

> **Proposed — requires approval.**
>
> **D1.1** `learner_node_state` + `masteryVerdict()` is the **canonical definition of concept
> mastery for the learner-facing experience**. Everything a student is told about their own
> mastery derives from it.
>
> **D1.2** `pal_concept_mastery.p_mastery` (BKT) remains the **canonical cross-system
> mastery signal** — coherence map, knowledge graph, Career Intelligence. It is not shown to
> a student as "your mastery" and is not used to gate ESO decisions.
>
> **D1.3** `pal_competencies.mastery_score` is **demoted to a historical accuracy metric**.
> It must no longer be read as a mastery judgement by recommendation, pedagogy or content
> selection. It is raw accuracy with no recency, independence or difficulty term, and it is
> on a different scale from the other two.
>
> **D1.4** The existing thresholds — K ≥ 0.80, A ≥ 0.70, no active misconception — are
> **ratified unchanged**. The audit found no evidence they are wrong, and they are already
> exercised by 124 passing tests.
>
> **D1.5** Mastery additionally requires a **minimum evidence floor** and an **independence
> requirement**, which the current implementation does not enforce (§5).
>
> **D1.6** Mastery **expires after 30 days** without re-verification, becoming
> `STALE_MASTERY` (§6).

---

## 3. Mastery definition

A concept is **MASTERED** for a learner when *all* of the following hold:

1. **Knowledge** — every K node has ≥ `MIN_EVENTS_K` valid evidence events, and the mean
   `mastery_estimate` across K nodes is ≥ **0.80**.
2. **Application** — every A node has ≥ `MIN_EVENTS_A` valid evidence events, and the mean
   `mastery_estimate` across A nodes is ≥ **0.70**.
3. **Independence** — at least one valid event on each gated node type was recorded in
   `independent` practice mode, hint-free.
4. **No active misconception** — no node on the concept is `misconception_flagged`.
5. **Recency** — the newest valid event is within the recency window (§6).

If a concept authors **no node of a gated type**, that gate is **declared not applicable and
recorded as such**, rather than passing silently. See §8, consequence 3 — this is live today
for 3 of 17 concepts in Chapter 1014.

**What K represents.** Recall/knowledge nodes (`pal_concept_nodes.node_type = 'K'`) — can the
learner reproduce the fact or procedure.

**What A represents.** Application nodes (`node_type = 'A'`) — can the learner use it on a
problem that is not the one they were taught with.

**What S represents.** Skill/transfer nodes. **Not gated today.** `masteryVerdict()` judges K
and A only, and sweeps S into `mastered` alongside them.

---

## 4. Evidence rules

### 4.1 What is a valid evidence event

A **valid evidence event** is a single scored response that satisfies *all* of:

| Rule | Reason |
|---|---|
| Recorded against a node of the concept | Mastery is per concept, per node type |
| **Not** a check-for-understanding response (`eso_response_log.mode = 'cfu'`) | CFU is explicitly promised to the student as "doesn't count" — and that promise is already honoured through to the graph |
| Not hint-assisted **when in `independent` mode** | Already implemented as `countsForMastery` |
| Not a repeat of the same `question_id` on the same node within the recency window | **New.** Prevents a learner reaching mastery by re-answering one remembered item |

An evidence event is **not** a question attempt. Three attempts can produce one valid event —
one was a CFU, one was hinted, one repeated an item already answered.

### 4.2 Minimum counts — the numbers this ADR exists to fix

| Symbol | Proposed | Rationale |
|---|---:|---|
| `MIN_EVENTS_K` | **3** | With `MASTERY_STEP = 0.2` from a `0.000` default, 4 consecutive correct answers reach 0.80. A floor of 3 does not weaken that; it prevents the *diagnostic* shortcut below |
| `MIN_EVENTS_A` | **3** | Same, against the 0.70 gate (4 correct from zero) |
| `MIN_INDEPENDENT` | **1** per gated node type | Distinguishes "can do it with scaffolding" from "can do it" |

**The shortcut this closes.** `scoreDiagnostic()` applies `applyUpdate(..., weight: 2.0)`,
i.e. ±0.4 per response. From a `0.000` start, **two correct diagnostic answers reach 0.800**
and the node is marked `mastered` and skipped. Mastery is therefore currently reachable on
**two answers to two questions, both scaffolded, both possibly guessed** (4-option MCQ ⇒ ~6%
chance of a blind pass). That is not a threshold problem; it is a missing evidence floor.

### 4.3 What is deliberately *not* required

- **Difficulty spread.** `pal_question_metadata.difficulty_1_to_5` is **NULL on 100% of the
  25 approved questions** on the pilot concept. A difficulty requirement would be unfalsifiable
  today. Recorded as a deferred amendment, not a rule.
- **Transfer (S).** Concept-dependent and not gated. Making transfer mandatory would render
  most of the catalogue unmasterable, since S nodes are sparsely authored.
- **Modality/context distinction.** `eso_response_log` records `mode` (guided/independent/cfu)
  but not modality. No rule can reference what is not recorded.

---

## 5. NOT_ASSESSED semantics

**No evidence ≠ zero mastery. No evidence ≠ 50% mastery. No evidence = `NOT_ASSESSED`.**

### 5.1 Representation — no schema change

`NOT_ASSESSED` is represented by the **absence of evidence rows**, not by a sentinel value.
This is already how `LearnerStateEngine` tests it (`$competencies->isNotEmpty()`) and how
`conceptStatusFor()` derives `not_started`. Making `mastery_score` nullable would create a
second, ambiguous encoding of one state and require backfilling 24,003 rows for no gain.

**No migration. No backfill. Historical rows unaffected.**

### 5.2 Required behaviour

For a concept or learner in `NOT_ASSESSED`:

- Recommendation, pedagogy and content selection **route to diagnosis** — never to
  remediation, never to enrichment. Treating an unassessed learner as low-mastery pushes
  remedial content at a student who may already know the material; treating them as average
  pushes mid-difficulty content at one who may be lost. Both are guesses presented as
  measurements.
- Any payload carrying a mastery number carries `has_evidence` beside it, matching the
  pattern already in `EsoPolicyService::signal()`.
- The UI renders "Not assessed yet" — never a number, never a bar.
- No motivational claim, gap sentence or growth statement is generated.

### 5.3 The averaging bug this exposes

`averageMasteryOf()` sums `$state ? $state->mastery_estimate : 0.0` over the nodes of a type.
**A node the learner has never touched contributes 0.0 to the mean.** The direction is safe —
it makes mastery harder, never falsely granted — but the *number it reports* is not a
measurement: a concept showing "Knowledge 40%" may be one node at 0.80 and one never seen.
Under this ADR that concept is `NOT_ASSESSED` on the unseen node, and the mean is undefined
rather than 0.40.

---

## 6. Recency policy — TWO WINDOWS (D2, accepted)

The retention ladder and the recency constant **disagree by construction**: rungs 4 and 5 are
**60 and 180 days**, both *longer* than `EVIDENCE_RECENCY_DAYS` (30). Applying recency to a
node mid-ladder would mark it stale at day 31, pull its Day-60 check forward, and silently cap
the ladder at 30 days — making rungs 4 and 5 unreachable.

So recency is scoped by whether a schedule is active:

| Window | Condition | Governed by |
|---|---|---|
| **Active ladder** | `next_review_at` is set | **The ladder.** Recency does not apply. `stale` may be reported internally but must never pull a scheduled node forward. Day 60 and Day 180 stay reachable |
| **No active schedule** | legacy mastery · completed ladder · never scheduled | **Recency.** Evidence older than 30 days → `STALE_MASTERY` (derived) |

### `STALE_MASTERY` behaviour

- **Derived on read, never written.** No status row is mutated by a dashboard load.
- **Never deletes evidence** and **never silently revokes mastery.**
- **Routes to retrieval** when retrieval is actually due and available — not to diagnosis, not
  to remediation. Prior evidence exists; this is verification, not re-learning.
- **Does not advance or consume a retention rung** when content is unavailable.
- **Counts as settled for chapter progression** (`isConceptSettled()`). A stale concept is a
  previously demonstrated concept awaiting verification; it must not reopen progression and
  drag the learner backwards through completed content. Verification still reaches them via
  the due-retrieval path and the reviews-due count.

### Content unavailable

When verification content does not exist, the engine returns
`content_unavailable` with `mastery_retained: true`, logs the authoring gap, and **leaves the
ladder untouched**. No learner standing is ever lost because the platform lacks authored
content — that is our gap, not theirs.

### Student-facing wording

`STALE_MASTERY` is **internal**: teacher and admin dashboards, logs, APIs. Students see the
ordinary retrieval surface — *"Time for a quick review."* Never "stale mastery", which reads
as an accusation about the learner rather than a fact about the calendar.

---

## 6b. Legacy recency policy (superseded by §6)

| Rule | Value | Basis |
|---|---:|---|
| Mastery remains valid without re-verification for | **30 days** | Reuses the existing `PREREQUISITE_STALE_AFTER_DAYS = 30`, already shipped and tested. Inventing a second staleness constant would create the same drift this ADR exists to end |
| After that, concept state becomes | `STALE_MASTERY` | New concept-level state; node-level `retained`/`mastered` unchanged |
| `STALE_MASTERY` resolves by | one passing retrieval check | The retention ladder `[2, 7, 30, 60, 180]` already schedules and scores these |
| Duplicate-item suppression window (§4.1) | **30 days** | Same window, one constant |

A learner on the retention ladder is being re-verified continuously and will not normally
reach 30 days stale. `STALE_MASTERY` is for the learner who *stopped* — exactly the case the
ladder cannot cover, because it depends on the student returning.

---

## 7. Misconception rule

> **Any active misconception on any node of the concept prevents mastery, regardless of
> accuracy.**

Already implemented and ratified unchanged. `masteryVerdict()` computes
`$misconceptionActive` across all nodes and ANDs it into the verdict.

The rationale is pedagogical, not statistical: a learner holding a wrong rule can score
highly on items the wrong rule happens to answer correctly. Accuracy cannot see the error;
the structured distractor mapping (`answer_master.misconception_id`) can. A misconception
clears only on a clean retest after corrective content — never by accumulating accuracy.

---

## 8. Consequences

1. **`RecommendationEngine`, `PedagogySelectorEngine`, `ContentIntelligenceService` and
   `AIOrchestrationService` must stop reading `mastery_score` as a mastery judgement.** All
   four currently do, on the 0–100 scale, with fabricated fallbacks. `RecommendationEngine`
   assigns an unassessed learner **level 1 of 3** (`?? 50`, and `50 >= 40`) — a middle
   ability rating for someone never assessed.
2. **`LearnerActivitySource::normaliseMastery()` can be retired.** It currently *guesses*
   which scale a number is on (`$value > 1 ? $value / 100 : $value`) — a symptom of the
   three-scale problem, and wrong by 100× for a genuine 0.5% score.
3. **Three of 17 concepts in Chapter 1014 have no A node**, so `applicationOk` passes
   vacuously and they are mastered on knowledge evidence alone. "Mastered" therefore means
   something different per concept today. Under this ADR the gate is recorded as
   not-applicable rather than silently passed — and the authoring gap becomes visible.
4. **A concept with neither K nor A nodes would be reported MASTERED with zero evidence.**
   Latent, not live (0 concepts today), but reachable the moment someone authors an S-only
   concept.
5. **The diagnostic shortcut closes.** Two correct diagnostic answers will no longer master a
   node; the evidence floor and independence requirement must also be met.
6. **Existing mastered learners are unaffected on approval.** The floor is additive and
   applies to new verdicts; no historical state is recomputed or revoked. Whether to
   back-verify existing mastery is a separate decision, deliberately not taken here.

---

## 9. Rejected alternatives

| Alternative | Why rejected |
|---|---|
| **Adopt BKT (`p_mastery`) as the single learner-facing definition** | BKT is a probability over a response sequence; it cannot express "no evidence" distinctly from "low probability", which is exactly what Principle 7 requires. It also cannot express the misconception veto. Keep it as the cross-system signal |
| **Adopt `mastery_score` (accuracy) as canonical** | Raw `correct/attempts` has no recency, no independence, no misconception term, and is on a different scale. It is the weakest of the three and the most widely read — that combination is the current bug |
| **Raise the thresholds (e.g. K ≥ 0.90)** | The audit found no evidence the thresholds are wrong. The failure mode observed was *too little evidence*, not *too low a bar*. Raising thresholds without an evidence floor makes mastery slower without making it truer |
| **Make transfer (S) mandatory** | S nodes are sparsely authored; most of the catalogue would become unmasterable. Revisit when S coverage is real |
| **Require a difficulty spread** | `difficulty_1_to_5` is NULL on 100% of approved pilot questions. Unfalsifiable today |
| **Make `mastery_score` nullable to encode NOT_ASSESSED** | Creates a second ambiguous encoding (row-with-null vs no-row) and forces a 24,003-row backfill for no gain |
| **Count question attempts instead of evidence events** | Teaches learners to game the counter, and is the explicit warning in the PAL V4 addendum |

---

## 10. Examples

Each example is stated as evidence, then verdict.

### Example A — Not assessed
No rows in `learner_node_state` for the concept's nodes; no `eso_response_log` rows.

**Result: `NOT_ASSESSED`.** No number is shown. Recommendation routes to diagnosis. No gap
sentence, no growth claim.

### Example B — Strong knowledge, no application
K node: 4 valid events, `mastery_estimate` 0.85. A node: 0 events.

**Result: not mastered.** `applicationOk` fails on the evidence floor before the threshold is
even consulted. The A node is `NOT_ASSESSED`, not 0% — the concept is *partially assessed*.

### Example C — Knowledge + application
K: 3 valid events, mean 0.85, ≥ 1 independent hint-free. A: 3 valid events, mean 0.75,
≥ 1 independent hint-free. No node `misconception_flagged`. Newest event today.

**Result: `MASTERED`.** Retention scheduled at rung 0 (Day 2).

### Example D — High accuracy, active misconception
K: 6 valid events, mean 0.90. A: 4 valid events, mean 0.80. One A node is
`misconception_flagged` with `active_misconception_id` set.

**Result: not mastered.** The misconception veto applies regardless of accuracy. Next action
is `serve_contrast_pair`, and mastery is unreachable until a clean retest clears it.

### Example E — Old mastery
Concept was `MASTERED` 45 days ago. No retrieval check passed since. Newest valid event is
45 days old.

**Result: `STALE_MASTERY`.** Verification required before the concept may be described as
mastered, counted toward a mastered-concept total, or used to satisfy a prerequisite.

### Example F — The diagnostic shortcut (closed by this ADR)
Learner answers 2 diagnostic questions correctly on the K node. `applyUpdate(weight: 2.0)`
takes `mastery_estimate` from 0.000 to 0.800.

**Today: node marked `mastered` and skipped.**
**Under this ADR: not mastered** — 2 events is below `MIN_EVENTS_K = 3`, and neither was
independent. The threshold is met; the evidence floor is not.

---

---

## 11. Legacy-mastery migration — the mechanism used

**No migration was written, because the architecture already had one.**

A learner who reached mastery under the old rules carries `status = mastered|retained` on
every node. `masteryVerdict()` now checks that condition explicitly: if the concept fails the
new floor but every node is already mastered and no misconception is active, the learner
**keeps mastery** and the verdict is flagged `legacy_mastery: true`.

- **No new column.** The existing node status is the migration state.
- **No backfill.** No historical evidence is deleted, rewritten or recomputed.
- **No mass invalidation.** Shipping the floor revokes nothing.
- **Progressive convergence.** The retention ladder brings each legacy learner onto the new
  policy at their next retrieval check: pass and they keep mastery, fail and
  `retrievalCheck()` already drops the node to `learning`, from where the new floor applies
  like anyone else.

`legacy_mastery` is reported in the verdict payload so the size of the legacy cohort is
observable rather than invisible.

---

## 13. OPEN — completed retention ladder

**Unresolved. Do not implement either interpretation until decided.**

When a node passes the final rung, `scheduleRetention()` sets `next_review_at = null` and
nothing reads it again. The repository does not establish which was intended:

- **A — terminal indefinitely.** The ladder is the whole verification contract; once walked,
  mastery stands without further checks.
- **B — terminal only for scheduling.** The ladder stops scheduling, but the no-active-schedule
  recency window (§6) eventually marks the concept `STALE_MASTERY`.

Under the current implementation a completed ladder lands in the second window and *would*
report `stale` — but nothing routes on it, so **behaviour is unchanged**: no retrieval is
served, no rung is scheduled, nothing is revoked. The test
`test_a_completed_ladder_keeps_its_existing_behaviour` deliberately asserts only that, and
does not assert stale-or-not, so neither interpretation is locked in by accident.

**This needs a human decision.** The trade-off: A risks knowledge decaying unverified after
180 days; B risks a learner who has demonstrated retention five times over being asked again
forever.

---

## 12. What was implemented

| Constant | Value | Location |
|---|---|---|
| `MIN_EVENTS_K` | 3 | `EsoPolicyService` |
| `MIN_EVENTS_A` | 3 | `EsoPolicyService` |
| `MIN_INDEPENDENT` | 1 | `EsoPolicyService` |
| `EVIDENCE_RECENCY_DAYS` | `= PREREQUISITE_STALE_AFTER_DAYS` (30) | `EsoPolicyService` — a reference, not a second number |
| `RESPONSE_MODE_DIAGNOSTIC` / `_RETRIEVAL` / `_CFU` | labels | `EsoPolicyService` |

**The diagnostic shortcut is closed at the semantic level, not by tuning the weight.**
`scoreDiagnostic()` no longer writes `STATUS_MASTERED` or schedules retention. A skip now
means "do not teach this" — it stamps `taught_at`/`cfu_passed_at` so the learner goes straight
to the practice that produces valid evidence, and `hasSatisfiedOwnThreshold()` still skips the
node because it reads the estimate, not the status. Mastery and the retention ladder are
granted only by the D4 verdict. The diagnostic weight of 2.0 is unchanged.

Diagnostic and retrieval responses were previously both written with a NULL `mode`, making
them indistinguishable and impossible to exclude from an evidence count. They are now
labelled, which is what makes rule 5 enforceable at all.

`averageMasteryOf()` is now a thin wrapper over `masteryAcross()`, which returns
`{mean, measured, unassessed, total}` and computes the mean over **measured nodes only**. An
entirely unmeasured node type yields `null`, not `0.0`. The numeric field is preserved at the
API boundary; it simply carries `null` instead of a fabricated zero.
