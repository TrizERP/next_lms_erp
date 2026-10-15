# Enterprise Brain Implementation (Gate 6)

Repos: `C:\hpbrain_backend` (branch `sonika_eb`), `C:\hp-enterprise-brain` (branch `sonika_eb_frontend`).

**Status: Implemented and verified against real data, with one real AI-provider call.**

## What this gate found before changing anything

EB turned out to be the most architecturally mature of the three products for exactly what the master
prompt asks for. It already has, independently of this initiative:

- A complete MySQL-backed Signal→Evidence→Case→Hypothesis→Recommendation→Decision→Execution→Outcome→
  Learning chain, with its own ADRs (ADR-005 "decisions→outcomes→learnings→mental models", ADR-007,
  ADR-008) and named invariants (e.g. "Invariant 3: every action is executable" — a recommendation
  categorised `intervene`/`escalate` must name an ESO definition; "Invariant 7: the gateway has already
  recorded the failed execution").
- A full screen, `SignalChainView.tsx`, that already renders this entire chain stage-by-stage for one
  signal, with honest empty states ("a signal with no case yet is not broken — it just hasn't been
  picked up") and a "furthest stage reached" / "loop closed" summary. This is the master prompt's own
  "reconstruct the relevant context for a past decision" ask, already built.
- A governed, human-gated cross-product identity feature (`PersonProfileController`, Phase 7.4/7.5):
  unifies a person's K-12/G2G/EB record under a reviewed `cross_product_identity_links` table. Currently
  **zero confirmed links exist** (73 unreviewed proposals) — the endpoint honestly reports "not yet
  confirmed" rather than fabricating a match, and a K-12 row is only ever read after a human-reviewed,
  confirmed link names it. This is directly relevant to master-prompt rule #13 (no unauthorized
  cross-tenant/cross-product sharing) and Gate 7 — it already meets that bar and needed no action here,
  but is recorded because it's exactly the kind of mechanism those rules exist to scrutinize.
- Several more graph-grounded "explain"/"narrative" endpoints beyond the chain screen:
  `GraphExplanationController::explain()` (generic, any entity label), `SignalController::explainReasoning()`,
  `CapabilityController::graphNarrative()`, `RiskController::graphNarrative()`,
  `EntityIntelligenceController::graphNarrative()`, `AiWorkspaceController::graphNarrative()`. All
  follow the same disciplined pattern: retrieval (`GraphQueryPort`, pure, no model call) and generation
  (`AiGateway`, strictly grounded, "never invent a name, figure, or relationship not present in the
  input") are separate steps, and a failed/unconfigured AI call degrades to "facts only," never a
  fabricated narrative.

## The actual gap: built, routed, permission-gated — and never called

Checked each of the six narrative endpoints above against the frontend (`hp-enterprise-brain/src`) for
a real caller, not just a route. Confirmed by searching for their exact URL segments, not guessing from
names: **only `PersonProfileController::narrative()` and the executive-summary narrative
(`IntelligenceOverviewController`) are actually called from a screen.** The other four — including
`SignalController::explainReasoning()` — are fully implemented, routed, and permission-gated, with
**zero frontend callers**. This is Phase 0's own "exists but is not wired into the runtime" status,
confirmed concretely rather than assumed.

Also confirmed dead in the same pass: `ExplainButton.tsx` / `CitationPanel.tsx`, two generic components
Phase 0 had flagged as "thin presentational shells... not confirmed wired." They are not imported
anywhere in `src`. They were **not** reused here — their prop shape (`messageId`/`onExplain`,
`citations: {id,type}[]`) is built for an unrelated chat-message-citation concept, not a graph-chain
explanation, and forcing them in would be a worse fit than following the pattern already proven and
working in `IntelligenceWorkspace.tsx`'s executive-narrative panel (on-demand load, billed, four explicit
states: error / not-yet-generated / loading / narrative).

## What was done

Picked **one** of the four unwired endpoints — `SignalController::explainReasoning()` — as this gate's
pilot, for the same reason K-12's pilot was Suggested Content: it's the screen whose data already
matches the master prompt's own illustrative use case (`Signal → Evidence → Case → Hypothesis →
Recommendation`) most directly, and the backend capability already exists end to end. Deliberately did
**not** wire all four unwired endpoints in one pass — minimal, reviewable change, one verified pilot,
the other three recorded as explicit follow-ons below.

### Files changed

- **`hp-enterprise-brain/src/api/intelligence.ts`**: added `explainSignalReasoning(tenantId, signalId)`,
  calling the existing `GET /signals/{tenantId}/{id}/reasoning` route. Documented inline why it is a
  *different* function from the neighboring `getReasoningForSignal` (Story 3's raw reasoning-steps list)
  despite the similar name — they are genuinely different features at different URLs, and conflating
  them was a real risk this investigation had to rule out before writing anything.
- **`hp-enterprise-brain/src/components/signal/SignalChainView.tsx`**: added one `Panel` titled
  "Explanation", between the existing loop-status line and the "Evidence" section, following
  `IntelligenceWorkspace.tsx`'s established on-demand-narrative pattern exactly (same four states, same
  `EmptyState`/`Button` usage, same "billed, load on click, not automatic" discipline). No existing
  section, state, or data flow in this file was changed.

No backend file was changed — `SignalController::explainReasoning()` already existed and was called
exactly as the route already exposes it.

### Database changes

None.

## Tests executed and actual results

- `npx tsc -b` (frontend build's own type-check step): clean, no errors.
- `npx vitest run`: **990 passed, 4 failed** — all 4 failures are inside
  `.kilo/worktrees/decisive-king/tests/` (sidebar-placement and fee-intelligence-figure assertions,
  nothing related to signals). Confirmed via `git status` that this worktree directory is untracked in
  this repo (outside `sonika_eb_frontend` branch history, per Phase 0's own earlier finding) and that
  the only files this gate touched are `src/api/intelligence.ts` and
  `src/components/signal/SignalChainView.tsx`. No test for `SignalChainView` exists to have been broken.
- **Live runtime verification against real data**: invoked `SignalController::explainReasoning()`
  directly (same arguments the real route passes — tenant id, signal id, the bound `GraphQueryPort` and
  `AiGateway`) against a real signal in tenant `1` (`leadership` classification, five evidence rows, one
  open case). Result: HTTP 200, `narrativeStatus: "ok"`, and a narrative that correctly cited only the
  retrieved facts (the signal's own classification/severity/source, the five evidence rows' source
  table, and the case's exact title and creation date) with nothing fabricated. This is a genuine,
  configured, live AI Gateway call — **it incurred one real, billed AI-provider request**, noted here
  transparently rather than left implicit; it was necessary to confirm the feature works end-to-end
  rather than resting on code review alone (master-prompt rule #9), and it does not recur on every future
  verification — this was a one-time check.

## Security / tenant isolation

No new code resolves a tenant. `tenantId()` is read from the request attribute `EnsureTenantScope`/
`AuthenticateJwt` already populate server-side, exactly as every other call in this controller does. The
frontend passes the `tenantId` prop `SignalChainView` already receives from its caller — no new input
path, no new trust boundary. The route remains gated `permission:create` (unchanged, pre-existing),
consistent with every other billed AI-Gateway call in this codebase.

## Outstanding / deferred

- **Three more unwired narrative endpoints**, found but not acted on in this gate:
  `GraphExplanationController::explain()` (generic entity explain — would need a UI decision about
  *which* entity-detail screens get an "Explain" affordance, since it's generic by design),
  `CapabilityController::graphNarrative()`, `RiskController::graphNarrative()`,
  `EntityIntelligenceController::graphNarrative()`, `AiWorkspaceController::graphNarrative()`. Each is a
  candidate for the same minimal pattern applied here, one at a time, once this pilot's reception is
  known.
- The dead `ExplainButton.tsx`/`CitationPanel.tsx` components were left exactly as found (unused, not
  deleted) — removing genuinely dead code wasn't in scope for this gate and wasn't asked for.
- The cross-product Person Profile feature (Phase 7.4/7.5) was reviewed and found already compliant
  with this initiative's isolation rules; no action was needed or taken.
