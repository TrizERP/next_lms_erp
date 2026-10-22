# G2G Implementation (Gate 5)

Repos audited: `C:\g2gv0` (branch `sonika_g2g`), `C:\xampp\htdocs\hp_erp` (branch `sonika_hp`). All
file paths below are in `hp_erp` unless noted.

**Status: Audited in depth. No code change made — and none is warranted.** This is a legitimate,
evidence-based outcome, not a placeholder: the investigation below is what the master prompt's own
rules (#4, #5, #9, #19 — reuse, no duplicate implementations, verify via runtime evidence, don't change
existing behavior unnecessarily) require before writing anything, and here they point the same
direction K-12's didn't: G2G already has this.

## Why this gate did not mirror the K-12 pilot

The K-12 pilot (`K12_IMPLEMENTATION.md`) found a live, evidence-grounded, learner-facing recommendation
surface (`PedagogySuggestedContentService`) that computed real evidence and recorded none of it. The
natural instinct going into G2G was to find the equivalent surface and do the same thing. Two things
stopped that.

### 1. The obvious candidate is a *derived read*, and this codebase has already ruled that out

`App\Http\Controllers\Api\SkillMatchingController::explainMatch()` (hp_erp) is the closest G2G analog to
K-12's Suggested Content: a live, tenant-checked, GET endpoint that computes a deterministic competency
gap (`competencyGapFor()`, shared via the `ResolvesCompetencyGap` trait with `CompetencyGapController`),
enriches it with KASBA-item detail from `GraphRagRetriever::contextFor()` (pure Cypher, no LLM), and
generates a bounded, strictly-grounded LLM narrative explaining why a person does or doesn't match a job
role. It records nothing today.

Before wiring `EventRecorder` into it the way `AiAuditLogger` was wired into K-12's service, I checked
this codebase's own `App\Services\Events\EventCatalogue::SHIPPED` list — the place "which events exist
and why" is tracked as a reviewed design decision, not ad-hoc. It already contains:

```php
'competency.gap_detected' => [
    'verdict' => 'DROPPED',
    'trigger' => null,
    'reason'  => 'Gaps are DERIVED, not a state change. The gap is a query, not an event;
                  emitting one per recompute would flood the store with the same standing gap.',
],
```

`explainMatch()` computes exactly this kind of value — a gap, re-derived on every call, not a state
change. Recording one event per view would be the precise anti-pattern this codebase's own architects
already rejected, for a documented, sound reason (store flooding from a value that's recomputed, not
changed). Doing it anyway would violate master-prompt rule #19 ("do not change existing ... behavior
unnecessarily") and rule #4 ("preserve existing ... business logic") — here, "existing business logic"
includes an explicit architectural decision, not just the code path.

### 2. The actual decision point already has full Context Graph capability, end to end

`App\Http\Controllers\AI\RecommendationController` (the approve/reject/defer gate over
`hpbrain_recommendations`) is not a derived read — approving a recommendation is a genuine state change.
Checking it found the master prompt's whole Use-Case-1 chain already built:

- **Context assembly**: every route in `routes/ai.php` (including all of `/ai/recommendations/*`) sits
  behind one fixed stack — `AiAuth` (resolves identity) → `AiRateLimit` → `AiContextHydrator` (resolves
  the organisation scope controllers read from; "controllers read the organisation from that scope and
  never from request input, so an authenticated caller cannot name someone else's organisation" — the
  file's own header comment). This is G2G's working equivalent of K-12's `AiContextService`.
- **Explainable evidence**: `RecommendationController::show()` returns the full
  recommendation → reasoning step → evidence chain via `RecommendationRepository::explain()` — the
  class's own docblock states the design intent directly: "a recommendation is only trusted if it can
  be explained, and the explanation is the evidence behind it."
- **Decision trace / event capture**: `decide()` (shared by approve/reject/defer) already calls
  `$this->audit->record("ai.recommendation.{$decision}d", $scope, [...])` with `from`/`to` status,
  confidence, and an optional human note — **before** this gate started, not something this gate added.
- hp_erp has its **own** `App\Domain\AI\Support\AiAuditLogger` class — structurally parallel to K-12's
  (same namespace shape, same `record()` signature taking an event type, a scope/context, and an options
  array) but a separate implementation in this repo. `PHASE_0_AUDIT.md` under-documented this; it
  described `g2g_event`/`EventRecorder` as G2G's event capture without registering that a second,
  AI-recommendation-specific audit mechanism also exists here, parallel to (not the same table as)
  `g2g_event`. Corrected in that document.

So the chain the master prompt's Use Case 1 asks for — resolve scope, retrieve SQL state, retrieve
evidence (including graph-sourced evidence elsewhere in the product), pass to business logic, record the
decision with evidence references — already exists for the one part of this flow that is actually a
decision rather than a recomputed query.

## What this means for "do not copy K-12's domain model into G2G"

Read narrowly, that instruction is about KASBA vs. PAL concepts. The deeper version of it, which this
audit surfaced: K-12 and G2G also made **different, equally valid architectural choices about when a
computed value becomes an event**, and the Context Graph's job is to respect each product's own choice,
not to impose K-12's "record every suggestion" pattern as if it were a universal Context Graph
requirement. It is universal that *decisions* get recorded with evidence. It is not universal that every
*derivation* does — and G2G's own team already drew that line correctly, in writing, with a reason.

## One real, deliberately-deferred observation

`explainMatch()` (graph-grounded, LLM-narrated, un-audited by design per above) and
`hpbrain_recommendations` (SQL-evidence-based, fully audited, approval-gated) are two separate systems
that do not currently connect — an approved recommendation's evidence chain does not appear to draw on
`GraphRagRetriever`'s KASBA-item detail the way `explainMatch()`'s narrative does. Bridging them (so a
formal, approvable recommendation can optionally cite graph evidence) could be a legitimate future
Context Graph enhancement, but it is a bigger change — connecting two previously-independent systems —
than this gate's minimal-change mandate covers, and it needs its own scoping and approval, not a
decision made as a side effect of this audit. Recorded here so it isn't lost, not actioned.

## Files changed

None in `g2gv0` or `hp_erp`. This document and the `PHASE_0_AUDIT.md` correction are the only artifacts
of this gate.

## Tests executed

None required — no code was written. The claims above were verified by direct reads of
`SkillMatchingController.php`, `RecommendationController.php`, `EventCatalogue.php`,
`App\Domain\AI\Support\AiAuditLogger` (hp_erp's own), and `routes/ai.php`'s middleware group, not
inferred from names, per master-prompt rule #9.
