# ADR-002 — PAL / ESO Rule Precedence (D3)

**Status:** ACCEPTED — documented and regression-tested. **No production code change.**
**Date:** 5 September 2026
**Audited against:** `TrizERP/next_lms_erp` @ `student_workflow` / `origin/development`
**Relates to:** ADR-001 (mastery definition, D1/D2)

---

## 1. Context

Multiple learner states can be true at once — an unmet prerequisite, an active misconception,
stale mastery and a knowledge gap can all hold simultaneously. Without a written precedence,
"which rule wins" lives only in the order of `if` statements, and any refactor can silently
change what a learner is shown next.

The specific question raised was whether **misconception should outrank prerequisite gap**.

**It should not.** The reasoning is below, and it turns on a structural fact about the engine
that is easy to miss.

---

## 2. Decision

**Keep the existing `nextAction()` precedence exactly as it behaves today.** This ADR changes
no production code; it documents the order and locks it with regression tests.

### 2.1 Canonical precedence

| # | State | Rule | Where |
|---:|---|---|---|
| 1 | `NO_AUTHORED_NODES` | — | `nextAction()` guard |
| 2 | `NOT_ASSESSED` | **D1** | `$states->isEmpty()` → `diagnostic` |
| 3 | `PREREQUISITE_GAP` | **D2** | `prerequisiteGate()` → `remediate_prerequisite` |
| 4 | `STALE_PREREQUISITE` | **D2** | `stalePrerequisiteProbe()` → `prerequisite_quick_probe` |
| 5 | `MISCONCEPTION` | **D3** | node loop → `serve_contrast_pair` |
| 6 | `STALE_MASTERY` / due retrieval | **D5** | node loop → `retrieval_due` |
| 7 | `KNOWLEDGE_GAP` | **D1/D4** | node loop → `teach` → `check_understanding` → `practice` |
| 8 | `MASTERY` | **D4** | `masteryVerdict()` → `mastered_stop_practice` |
| 9 | `ENRICHMENT` / next concept | post-D4 | mastery payload |

### 2.2 One correction to the written order

Ranks **3 and 4 are per-prerequisite, and can invert across multiple prerequisites.**

`prerequisiteGate()` runs `stalePrerequisiteProbe()` *first*, then the unmet loop. For any
**single** prerequisite the two are mutually exclusive — the probe explicitly skips anything
below `PREREQUISITE_THRESHOLD` (`if ($mastery === null || $mastery < …) continue;`) — so a
weak prerequisite always goes to full remediation, never a probe.

But with **two** prerequisites, one weak (0.30) and one passing-but-stale (0.90, 45 days), the
probe loop runs first and the **stale one wins**, deferring the weak one.

This is existing behaviour and is **not changed here**. It is latent rather than live: every
`requires` relation in Chapter 1014 is single-prerequisite. Recorded so the documented order
is not more confident than the code.

---

## 3. The structural fact that decides this

**`recordAttempt()` has a separate, immediate corrective path that never consults
`nextAction()` precedence:**

```php
} elseif (! $correct) {
    $misconceptionAction = $this->checkMisconception(...);
    if ($misconceptionAction !== null) {
        return $misconceptionAction;   // served now — precedence not consulted
    }
}
```

So a learner who triggers a misconception **always sees the corrective immediately**,
whatever the precedence says. D3 governs only what happens when they *return later*.

**These two paths must not be merged.** The immediate path is about the moment of error; the
precedence path is about resuming a session. Collapsing them would either delay corrections
to the next navigation, or let a prerequisite-gated learner be pulled into a concept the
engine has just judged them unready for.

---

## 4. Why prerequisite stays above misconception

1. **The correction already happened.** Immediate service (§3) delivers it at the moment of
   error, regardless of ordering.
2. **A misconception can normally only be triggered on a concept the learner was already
   allowed to practise** — flagging requires an attempt, which requires the concept to have
   been served, which requires the D2 gate to have passed.
3. **Therefore `misconception + prerequisite_gap` is a regression-after-practice scenario,
   not a cold-entry one.** It arises when the prerequisite *later* decays — a failed stale
   probe, or a failed retrieval on the prerequisite.
4. **If the prerequisite regresses, remediation takes precedence** — mastery built on a
   foundation the engine has just judged inadequate is not mastery.
5. **While gated, the learner cannot practise the blocked concept**, so the wrong rule is
   **frozen, not reinforced.** This is the crux: the usual argument for prioritising
   misconceptions is that continued practice entrenches them, and that pressure does not
   exist behind a gate.
6. **Flags are node-local and do not cross-resolve.** The misconception lives on a node of the
   blocked concept and clears only via a clean retest on that node; working the prerequisite
   cannot touch it, and vice versa.
7. **The misconception remains a mastery veto independently.** `masteryVerdict()` ANDs
   `! $misconceptionActive`, so nothing false is granted while it waits.
8. **Once prerequisite remediation resolves, the learner returns to the contrast pair** and
   clears it through the existing clean-retest path. Nothing is lost.

---

## 5. Three-way interactions

| Combination | Winner | What persists |
|---|---|---|
| `NOT_ASSESSED` + `PREREQUISITE_GAP` + `MISCONCEPTION` | **diagnostic (D1)** | Structurally, the misconception cannot be on the unassessed concept itself — flagging requires an attempt |
| `PREREQUISITE_GAP` + `MISCONCEPTION` + `STALE_MASTERY` | **prerequisite remediation** | Misconception stays flagged and keeps vetoing mastery; staleness stays derived. No cross-resolution |
| `MISCONCEPTION` + `KNOWLEDGE_GAP` + threshold met | **contrast pair** | Mastery stays vetoed; raw accuracy never clears a misconception |
| `MISCONCEPTION` + `PREREQUISITE_GAP` | **prerequisite remediation** | No practice served for the blocked concept, so the misconception cannot be reinforced |

---

## 6. Accepted caveat

If a learner **abandons a contrast-pair flow** and their prerequisite regresses in the
meantime, the misconception waits behind prerequisite remediation.

**This is intentional.** The misconception is frozen rather than reinforced: the learner
cannot practise the blocked concept, mastery stays vetoed, and the flag survives to be
resolved afterwards. The alternative — pulling them into a concept whose foundation has just
decayed — trades a dormant wrong rule for active work on unstable ground.

This is the real cost of the ordering, and it is accepted with eyes open rather than
overlooked.

---

## 7. Consequences

- **No production behaviour changes.** This ADR is documentation plus regression tests.
- The precedence is now executable: a refactor that reorders the rules fails the D3 tests.
- The immediate-vs-later distinction is pinned by a test that proves the same learner, at the
  same instant, gets the corrective from `recordAttempt()` and the prerequisite from
  `nextAction()`.

## 8. Rejected alternative

**Promoting misconception above prerequisite gap.** Defensible in principle — a wrong mental
model is corrosive — but its stated benefit is already delivered by the immediate corrective
path (§3), and adopting it would route a learner into a concept whose prerequisite the engine
has just judged unmet. It would buy nothing and cost foundation integrity.

## 9. Regression tests

`tests/Feature/Eso/EsoPolicyServiceTest.php`:

- `test_d3_scenario_a_not_assessed_outranks_both_prerequisite_gap_and_misconception`
- `test_d3_scenario_b_prerequisite_wins_while_misconception_and_stale_mastery_persist`
- `test_d3_scenario_c_misconception_wins_over_a_knowledge_gap_and_still_vetoes_a_met_threshold`
- `test_d3_scenario_d_prerequisite_gap_outranks_misconception_and_serves_no_practice`
- `test_d3_scenario_e_misconception_correction_is_immediate_and_bypasses_precedence_entirely`
- `test_an_active_misconception_still_outranks_a_due_stale_retrieval`
- `test_a_prerequisite_gap_still_outranks_a_due_stale_retrieval`
