# Context Layer Design (Gate 2 — Phase 2)

**Status: PROPOSED. Nothing in this document has been implemented.** This defines the reusable
capability shapes the master prompt's Phase 2 (§7) asks for, as thin wrappers over what
[`PHASE_0_AUDIT.md`](./PHASE_0_AUDIT.md) already found working in each product, per the mapping in
[`SHARED_CONTEXT_MODEL.md`](./SHARED_CONTEXT_MODEL.md). No new cross-product network service is
proposed — the master prompt's own rule against that is honored by construction: each product
implements the contract in-process, in its own codebase, over its own existing services.

## 1. What is NOT being built

Stating this first because it's the load-bearing constraint on everything below:

- No new graph database, vector store, event platform, or AI provider.
- No new Neo4j adapter for Enterprise Brain (ADR-008 stands).
- No replacement of `AiContextService`, `AiTutorContextService`, `AiContextHydrator`,
  `AiScopeResolver`, `GraphBuilder`, `GraphProjection`, `DecisionGate`, `EventRecorder`,
  `AiAuditLogger`, or any other class the audit found working. They keep their names, their call
  sites, their tables.
- No change to existing PAL/ESO/recommendation business rules. The Context Layer supplies inputs;
  it does not gain a vote.
- No cross-product data flow. The K-12→G2G client stays exactly as blocked as the audit found it.

## 2. Context Assembly — per product, as a wrapper

The master prompt's "Context Assembly Service" is not one service; it is one **contract** (§5) with
three implementations, two of which already exist:

| Product | Implementation | What's new |
|---|---|---|
| K-12 | Wraps `AiContextService::resolve()` (general) and `AiTutorContextService::forConcept()` (PAL-specific) behind the shared contract shape. | A thin adapter class only — translates their existing return shapes (`AiContext`, the tutor-context array) into the shared Context Object fields from `SHARED_CONTEXT_MODEL.md` §2. No change to either wrapped class. |
| G2G | Wraps the existing `AiContextHydrator`/`AiScopeResolver` middleware output. | Same — an adapter, not a replacement. Because these currently only run on `/api/ai/*` routes, the adapter is scoped to that same route group; extending coverage to other routes is explicitly out of scope here (would be a G2G-side routing change, not a Context Layer concern). |
| Enterprise Brain | New thin service (named here `CaseContextAssembler`, final name TBD at implementation time) combining `GraphBuilder`'s graph-shaped output with the SQL-side case/signal/evidence reads controllers currently do ad hoc. | This is the one genuinely new component the whole initiative proposes (also flagged in `SHARED_CONTEXT_MODEL.md` §7.2). Modeled directly on K-12's `AiContextService` — resolve scope → resolve entity/case → attach bounded graph + evidence refs → return. |

Each implementation must, per master-prompt §7A:
- Resolve the authenticated user and tenant **before** touching any data (reuses each product's
  existing auth: K-12's JWT/session middleware, G2G's Sanctum/session hybrid, EB's `EnsureTenantScope`).
- Reject unauthorized or ambiguous scope the same way `EntityResolver` already does — return no entity,
  not a partial or best-guess one.
- Return source references (not copies) plus the provenance fields from the shared model §2.6.

## 3. Context Retrieval — bounded, per existing patterns

No new retrieval mechanism. Reuse:
- K-12's `PageAiContext` `LIMITS` constant as the model for capping records/filters/metrics sent in a
  context payload — every product's adapter applies an equivalent cap, product-specific values TBD at
  implementation, not invented generically here.
- K-12's `AiContextService::CACHE_TTL` (600s) as the model for how long an assembled context may be
  reused without reassembly — same pattern proposed for G2G/EB adapters, actual TTL per product's own
  data-freshness needs.
- Graph traversal depth: already bounded by each product's existing query code (`GraphRagRetriever`'s
  fixed Cypher patterns in G2G; `WalksPrerequisiteGraph` in K-12; `GraphBuilder`'s `expand` endpoint in
  EB, which already takes a depth parameter). The Context Layer does not add a second traversal engine —
  it calls the existing one and respects whatever limit it already enforces.

No request to "load an entire tenant graph" is in scope anywhere in this design — every per-product
implementation above is already a bounded, single-entity/single-task lookup, not a full-tenant scan.

## 4. Event Capture and Decision Trace — reuse only

No new event or decision table in any product. Per `SHARED_CONTEXT_MODEL.md` §2.5 and §7:
- K-12: `AiAuditLogger` already has the needed event-type constants. One optional new constant,
  `CONTEXT_ASSEMBLED`, may be added if a product team wants visibility into assembly itself as a
  distinct event (not required — assembly is usually implicit in the decision event that follows it).
- G2G: `EventRecorder`/`g2g_event` unchanged; a context assembly, if logged at all, is a normal
  `g2g_event` row with `type = 'context.assembled'`.
- EB: `hpbrain_audit_logs` unchanged, same pattern.

The only schema change proposed anywhere (optional, deferrable, per `SHARED_CONTEXT_MODEL.md` §7.1) is
a nullable `context_version`/`context_ref` pair of columns on each product's own decision table — never
a new table.

## 5. Integration Contract

A single logical shape, implemented per product as a plain class (PHP in K-12/G2G/EB backends) — no
network boundary, no shared package across repos (the master prompt explicitly asks not to force a
cross-product network service when in-process services suffice, and nothing in the audit justifies one).

```
ContextAssemblyContract
  assemble(scope: {tenant_id, user_id, task_id?, entity_type?, entity_id?}, hints: array) -> ContextObject | Rejected
  historyFor(decision_id) -> DecisionTrace[]        // thin pass-through to the product's own table
```

`ContextObject` is the schema from `SHARED_CONTEXT_MODEL.md` §2. `Rejected` carries a reason
(`unauthorized`, `ambiguous_scope`, `not_found`) and nothing else — never a partial object. Each
product's adapter class implements this by calling its existing service(s) (§2 above) and mapping the
result; the contract itself has no implementation of its own and is not a new runtime dependency.

## 6. Context Lifecycle

| Stage | Behavior | Reuses |
|---|---|---|
| Assembly | Synchronous, per-request, cached briefly (§3) | Existing per-product cache pattern |
| Refresh/invalidation | Cache TTL expiry; no event-driven invalidation proposed (none of the three products has a cheap "tell me when this changed" signal for the SQL+graph combination — adding one is out of scope for this gate) | — |
| Duplicate-event handling | G2G's `idempotency_key` on `g2g_event` is the only product with this today; K-12/EB rely on their write-once/append-only table design (`eso_decision_log`, `hpbrain_audit_logs`) which makes duplicate assembly harmless even without a key, since assembly itself is never persisted, only the decision that follows it | Existing idempotency columns where present |
| Stale-data handling | Each product already bounds staleness by its sync cadence (§3 of `SHARED_CONTEXT_MODEL.md`); the contract surfaces `assembly_timestamp` so a caller can decide if data is too stale for its purpose — the Context Layer does not itself define a global staleness threshold | — |
| Session closure | Not a Context Layer concern — context is per-request, not session-scoped, in all three implementations above | — |
| Retention/deletion | Governed entirely by each product's existing table retention; the Context Layer persists nothing of its own by default (per §7's "no new table" stance) | — |
| Error recovery | If graph retrieval fails, assembly must still return a ContextObject built from SQL alone with `kg_entity_refs` empty and a flag noting the graph was unavailable — never fail the whole request because the graph is down (direct instance of master-prompt rule: "SQL remains authoritative when graph projections are unavailable") | — |
| Audit retrieval | `historyFor()` is a thin pass-through to each product's existing decision-history query (`DecisionGate::historyFor()` already exists and does exactly this for K-12) | Existing query methods |

## 7. Risks carried from Phase 0, and how this design accounts for them

- **K-12's scheduler/trigger pipeline has a documented history of silent failure.** The contract's error
  recovery rule (above) means a stalled graph sync degrades context quality (missing `kg_entity_refs`)
  rather than breaking assembly — but this is a mitigation, not a fix; the underlying pipeline health
  still needs the monitoring the audit flagged as unverified in production.
- **G2G's undocumented `hpbrain_recommendations`/`hpbrain_recommendation_evidence` tables** — the G2G
  adapter must not be built against these specific tables until their provisioning source is confirmed
  (audit §3.3/§6); it can be built against `g2g_event`/`g2g_audit_log` alone in the meantime.
- **G2G's `GraphDrain`-equivalent row-claim race** (noted in the audit for K-12, same class of risk
  flagged generally) — not directly relevant to context *assembly* (which reads, not writes, the graph)
  but relevant if any product later adds a context-assembly write path; none is proposed here.
- **No CI in K-12 or G2G** — any adapter code added there has no automated regression net. Recommend
  (not mandate, since CI setup is its own decision) that a minimal test harness accompany the K-12 pilot
  adapter specifically, since that's where Gate 3 work lands first.

## 8. What Gate 3 would actually build, if approved

To make this concrete without pre-approving it: Gate 3 (per the master prompt's own order) is the K-12
Suggested Content / Recommendation Reason pilot. Under this design, that means:
1. One new adapter class wrapping `AiContextService`/`AiTutorContextService` to the shared contract
   shape (§2) — new code, no schema change.
2. Wiring the existing PAL recommendation path to call the adapter instead of (or alongside) its current
   direct calls — a call-site change, reviewed for not altering recommendation logic itself.
3. Confirming the explanation UI (`LifecycleTrace`, `ResolvedEvidence`) can render the adapter's output
   without modification (it already renders a compatible shape per the audit).
4. No new table, no new migration, unless the optional `context_version` column (§4) is explicitly
   requested.

This section is informational only — it is not itself an approval to proceed; it exists so the scope of
"what Gate 3 would mean" is visible now rather than discovered mid-implementation.

---

*This design, together with `SHARED_CONTEXT_MODEL.md`, is the complete Gate 2 deliverable. Per the
master prompt: implementation (Gate 3+) requires a separate go-ahead, and any of the proposed changes in
§4/§7 of the shared-model doc should be confirmed or rejected individually before code is written
against them.*
