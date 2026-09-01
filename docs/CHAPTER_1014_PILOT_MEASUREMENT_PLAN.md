# Chapter 1014 — Pilot Measurement Plan

**Status: DESIGN ONLY at time of writing — implemented immediately after in the same pass, but
no real student has been enrolled and the pilot has not started.** This document is the spec the
implementation in §5 below follows exactly; if the two ever disagree, this document is wrong and
should be corrected, not the code.

Source of truth for what's being measured: Developer Brief §7 (five metrics) and the
pre-registered success bar quoted there:

> Arm B achieves equal-or-better mastery and retention with ≥25% less time OR ≥25% less
> explanation served → build on and extend. No material difference → simplify or stop.

---

## 1. Arm A — "current LMS flow"

The existing, pre-ESO experience: the legacy `/lms/pal` quiz flow (`palController`), its
diagnostic (`lms/diagnostic-assessment`), adaptive-practice (`lms/adaptive-practice`), and
suggested-content/misconception surfaces (`suggested_content`, `getMisconceptionQuestions()`).
**No code in Arm A changes for the pilot** — it is measured exactly as it already behaves today,
which is precisely why it's a valid control: nothing about this pass touches
`palController`/`assessmentQuestionController`/`suggested_content`.

## 2. Arm B — "ESO adaptive flow"

The Adaptive Learning Engine built in prior sessions: `/pal/eso`, `EsoPolicyService` (D1–D5),
`eso_decision_log`. **No code in Arm B's decision logic changes for the pilot either** — D1–D5
are read-only inputs to measurement, never touched.

## 3. Enrollment, cohort, and arm isolation

One new table, `pal_pilot_enrollments` (§5.1), is the *only* new state this plan introduces for
assignment. One row = one (student, chapter) pair with a fixed arm:

- **Cohort** = a `cohort_label` string on the enrollment row (e.g. `"2026-pilot-1"`), letting more
  than one pilot batch coexist without ambiguity.
- **Arm assignment** = the `arm` column (`'A'` or `'B'`), set once at enrollment time.
- **Chapter 1014 scope** = the `chapter_id` column — the mechanism is chapter-agnostic (so it
  isn't rebuilt for chapter 2 later), but every row created for *this* pilot will have
  `chapter_id = 1014`.
- **Isolation between arms**: a unique constraint on `(student_id, chapter_id)` — a student can
  have at most one enrollment per chapter, so their arm for Chapter 1014 is a single, unambiguous
  value for the life of the pilot. Every metric query joins through this table, so a student who
  somehow used both flows (e.g. a curious Arm A student manually typing an `/pal/eso` URL) still
  only ever counts toward the arm they were *enrolled* in — their Arm-B-flow activity, if any,
  would appear in `eso_decision_log` but is simply not queried for an Arm A student, because the
  metric queries always filter by `pal_pilot_enrollments.arm`, not by "which system has rows for
  this student." This is a passive isolation guarantee (nothing *blocks* an Arm A student from
  opening `/pal/eso` — the existing `pal.auth` authorization would still allow their own account
  to reach it), not an active one; §9 covers when that should invalidate an observation.

## 4. Events / timestamps each metric needs

| Concept | Arm A source (existing, unmodified) | Arm B source (existing, unmodified) |
|---|---|---|
| **Concept entry** | `lms_online_exam.start_time` (first PAL quiz attempt on chapter 1014 after enrollment) | `eso_decision_log` first row for `(student_id, concept_id)` (`rule_fired LIKE 'D1%'`) — `created_at` |
| **Mastery timestamp** | First `lms_online_exam` attempt on the chapter that crosses the pilot's mastery bar (§8) — its `start_time` | `eso_decision_log` row where `action = 'mastered_stop_practice'` — `created_at` |
| **Retention result** | See §7.3 — the next chapter attempt 3–10 days after the mastery attempt; pass/fail from its score | `eso_decision_log` rows where `action IN ('retained','reloop_node')` |
| **Explanation served / skipped** | `suggested_content` rows (`type IN ('pal_content','misconception')`) created for the student on this chapter = served; a `content_visited` value = actually opened | `eso_decision_log` rows with `action IN ('teach','practice')` and non-null `llm_instruction` = served; `action = 'skip_instruction'` = skipped |
| **Misconception flagged / corrected / recurred** | `suggested_content` rows with `type='misconception'` = flagged (a content row is only generated after `getMisconceptionQuestions()` finds a wrong answer); the legacy flow has **no "corrected" concept** — flagging is generated once per wrong question and the flow does not verify a clean retest (documented gap, §9) | `eso_decision_log`: `action='serve_contrast_pair'` = flagged; `action='misconception_corrected'` = corrected; a second `serve_contrast_pair` row for the same `state_snapshot->misconception_id` + `node_id` + `student_id` after an earlier `misconception_corrected` = recurred |

No new writes are required to produce any of the rows in this table — everything on both sides
already gets written by the existing, unmodified code. The measurement layer only *reads* it,
scoped through `pal_pilot_enrollments`.

## 5. How each metric is calculated

1. **Time to mastery** = mastery timestamp − concept entry timestamp, in minutes. Null (not yet
   observed) if the student has no qualifying mastery event.
2. **Mastery rate** = COUNT(enrollments with a mastery timestamp) / COUNT(all active enrollments)
   in that arm.
3. **Retention @ ~7 days** = COUNT(enrollments with a *pass* retention result within the valid
   window, §7.3/§9) / COUNT(enrollments that reached mastery *and* had enough time elapsed for a
   retention observation to be possible, §9's "valid observation" rule).
4. **Explanation volume served** = COUNT(served events) per enrollment, and separately the ratio
   COUNT(skipped) / COUNT(served + skipped) — the brief's "skipped-because-known" framing.
5. **Misconception recurrence** = COUNT(enrollments with ≥1 recurrence) / COUNT(enrollments with
   ≥1 corrected misconception) — "of the ones we thought we fixed, how many came back."

## 6. Comparing the arms

The pre-registered bar (quoted at the top) is evaluated once, after the pilot window closes, on
the aggregated numbers above, per arm:

- **Gate 1**: Arm B mastery rate ≥ Arm A mastery rate, AND Arm B retention rate ≥ Arm A retention
  rate.
- **Gate 2** (only checked if Gate 1 passes): Arm B mean time-to-mastery ≤ 75% of Arm A's, OR Arm
  B mean explanation-served count ≤ 75% of Arm A's.
- Pass both gates → "build on and extend." Fail Gate 1, or pass Gate 1 but fail Gate 2 → "no
  material difference → simplify or stop," per the brief's own pre-registered wording.

This comparison is a report/query, not a UI — see §5 of the implementation for exactly what's
built (a metrics endpoint returning the aggregates above, not a dashboard).

## 7. Definitions that need to be explicit before anyone reads a result

### 7.1 What counts as "mastery" for Arm A (which has no D4)
Arm A has no multidimensional K≥0.8/A≥0.7 verdict — its only signal is a quiz score. For pilot
comparability, an Arm A attempt counts as "mastery" the first time a chapter-1014 attempt's
`total_right / (total_right + total_wrong) ≥ 0.70` (matching the brief's own existing "hard"
score band, and close to Arm B's application-accuracy bar) — an approximation, not an equivalent
metric, and reported as such rather than presented as identical measurement.

### 7.2 What counts as "concept entry" when Arm A has no concept granularity
Arm A operates at chapter grain (no `concept_id` on the legacy quiz). "Concept entry" for Arm A is
therefore the chapter's first attempt, full stop — Arm A's "time to mastery" is really "time to
chapter mastery," while Arm B's is genuinely per-concept. This asymmetry is inherent to comparing
a chapter-grained system to a concept-grained one and is called out in the final pilot report
template (§10), not hidden in an average.

### 7.3 The retention check itself
Arm B already has a real, engine-driven retention check (D5, 2–3 items, `next_review_at`
scheduled 3–5 days after mastery). **Arm A has no equivalent mechanism at all** — nothing
schedules or prompts a return visit. Rather than build one (explicitly out of scope — no new
engine behavior), Arm A's retention is an *observational* metric: if that Arm A student happens to
re-attempt the same chapter's PAL quiz between 3 and 10 days after their mastery attempt, that
attempt's score (same ≥70% bar) is the retention result. Most Arm A students will simply not
retake a quiz they already passed unprompted — this is expected to suppress Arm A's *observed*
retention rate for reasons unrelated to actual retention, and is flagged explicitly as a
limitation of comparing an active (D5-prompted) mechanism to a passive (unprompted) one. A widened
window (3–10 days, not exactly 3–5) partially compensates for the lack of a prompt.

## 8. What constitutes a valid observation

An enrollment contributes to a metric only if:
- It is `status = 'active'` (or `'completed'`) in `pal_pilot_enrollments` — a `'withdrawn'`
  enrollment is excluded from every metric, not just future ones (a withdrawal removes the
  student from analysis entirely, it does not freeze their partial data as a data point).
- For **mastery rate**: every active enrollment counts (a non-mastering student is a real "0" for
  the rate, not excluded).
- For **time to mastery**: only enrollments that *reached* mastery — an incomplete run has no time
  to report, and is excluded from this specific metric's average (but still counts in the mastery
  *rate* as a non-mastery).
- For **retention**: only enrollments that reached mastery *and* have had ≥3 days elapse since the
  mastery timestamp by the time the report is run (an enrollment mastered yesterday is not yet
  eligible for a retention verdict either way — it is excluded from the denominator, not counted
  as a fail).
- For **misconception recurrence**: only enrollments with at least one `misconception_corrected`
  event — an enrollment that never triggered D3 has nothing to recur.

## 9. Incomplete / abandoned sessions

- A student who starts (has a concept-entry event) but never masters is a real, counted
  non-mastery in the mastery-rate denominator — not dropped, not treated as an error.
- A student with **zero** events of any kind after enrollment (never actually started) is excluded
  from every metric — enrollment alone is not "an observation," an entry event is required. This
  is checked at report-generation time, not enforced at write time.
- If an Arm A student is later found to have also touched `/pal/eso` (see §3's passive-isolation
  note), their Arm A metrics are computed exactly as defined above regardless (their Arm A signal
  sources don't change), and this is flagged as a data-quality note in the report rather than
  silently included or silently dropped — the report template (§10 of the implementation) has an
  explicit "cross-arm contamination" count for this.
- Enrollment `status` transitions (`active → withdrawn` / `active → completed`) are a manual,
  staff-driven action (§6 of the implementation, the enrollment console command) — nothing in this
  plan auto-withdraws a student for inactivity, since "never returned" is itself a legitimate,
  measurable outcome for a pilot, not a data-quality problem to hide.

## 10. What gets built (implementation, not measurement design)

- **New**: `pal_pilot_enrollments` (migration + model) — the only new table.
- **New**: `PilotMetricsService` — computes §5's five metrics from `pal_pilot_enrollments` joined
  to `eso_decision_log` (Arm B) or `lms_online_exam`/`suggested_content` (Arm A). No new event
  table for either arm — see §4, everything needed already exists.
- **New**: a read-only metrics endpoint (not a dashboard) for pulling the aggregates in §5/§6.
- **New**: a console command for cohort/arm assignment (§6 of the readiness work) — an operational
  tool, not something this pass runs against real students.
- **Not built**: any change to `EsoPolicyService`, D1–D5, `eso_decision_log`'s schema, or any
  legacy Arm A controller.
