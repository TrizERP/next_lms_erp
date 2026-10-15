# Context Graph — Architecture (as actually built)

This describes what exists after all of Phases 0–9 (master prompt numbering), not the aspirational
design. For the full conceptual model and reasoning, see
[`SHARED_CONTEXT_MODEL.md`](./SHARED_CONTEXT_MODEL.md) and
[`CONTEXT_LAYER_DESIGN.md`](./CONTEXT_LAYER_DESIGN.md). For per-product detail and evidence, see
[`K12_IMPLEMENTATION.md`](./K12_IMPLEMENTATION.md), [`G2G_IMPLEMENTATION.md`](./G2G_IMPLEMENTATION.md),
[`EB_IMPLEMENTATION.md`](./EB_IMPLEMENTATION.md). For the RAG/GraphRAG and security audits, see
[`RAG_GRAPHRAG_INTEGRATION.md`](./RAG_GRAPHRAG_INTEGRATION.md) and
[`SECURITY_AND_GOVERNANCE.md`](./SECURITY_AND_GOVERNANCE.md). For what was actually executed and
observed, see [`TEST_REPORT.md`](./TEST_REPORT.md). The authoritative status of every individual item is
[`MASTER_IMPLEMENTATION_TRACKER.md`](./MASTER_IMPLEMENTATION_TRACKER.md) — this document explains the
shape, that one tracks the status.

## The headline fact

There is no new Context Graph *system*. There is no new database, no new service, no cross-product
network call, and — except for one narrow, pre-approved column-add migration that fixed an unrelated
pre-existing defect — no schema change anywhere. The "Context Graph" is a name for capabilities that
already existed, independently, in each product, which this work mapped against one shared vocabulary
and, in exactly two places, connected to a UI or event sink that wasn't yet reading them.

## Per-product shape

### K-12 (`next_lms_erp` + `lms_k12`)

```
Request → PedagogySuggestedContentService::getSuggestions()   [unchanged scoring/selection logic]
            ├─ CoherenceMapRepository::rootBlockers()/contentFor()   [Neo4j, evidence]
            └─ PedagogySuggestedContentContextRecorder::record()     [NEW, additive]
                 └─ AiAuditLogger::record()  →  ai_audit_logs        [existing table, now column-complete]
```

Context assembly for the richer AI-tutor/workspace surfaces already existed separately
(`AiContextService`, `AiTutorContextService`) and was not touched. Decision trace for consequential,
human-approved actions already existed separately (`DecisionGate`/`ai_decisions`,
`App\Domain\AI`'s Signal→Case→Recommendation pipeline) and was deliberately **not** used for this
pilot — see `K12_IMPLEMENTATION.md` for why a lighter-weight mechanism was the correct fit.

### G2G (`g2gv0` + `hp_erp`)

```
/api/ai/recommendations/{id}/approve|reject|defer
  → AiAuth → AiRateLimit → AiContextHydrator        [context assembly, pre-existing]
  → RecommendationController::decide()               [pre-existing]
      → RecommendationRepository::decide()
      → AiAuditLogger::record()  →  ai_audit_logs    [pre-existing, already complete]
```

No diagram for a new flow, because none was built. The one candidate addition
(`SkillMatchingController::explainMatch()` gaining event capture) was investigated and rejected on
evidence — see `G2G_IMPLEMENTATION.md`.

### Enterprise Brain (`hpbrain_backend` + `hp-enterprise-brain`)

```
SignalChainView (existing screen)
  → api.getSignalChain()                              [pre-existing, renders the full chain]
  → [NEW] "Explain this" → api.explainSignalReasoning()
       → GET signals/{tenantId}/{id}/reasoning
            → SignalController::explainReasoning()     [pre-existing, now has a caller]
                 → GraphQueryPort::reasoningTrail()     [MySQL graph projection, pure retrieval]
                 → AiGateway::complete()                [strictly grounded generation]
```

The MySQL-backed Signal→Evidence→Case→Hypothesis→Recommendation→Decision→Execution→Outcome→Learning
chain, its decision-trace schema (`hpbrain_decisions.confidence/explanation/trace`), and its
cross-product Person Profile feature all pre-existed this work.

## Shared contract status

[`CONTEXT_LAYER_DESIGN.md`](./CONTEXT_LAYER_DESIGN.md) §5 proposed a logical `ContextAssemblyContract`.
It was **not implemented as code** in any repo — every pilot above calls each product's existing
service directly. The contract remains a documentation-level mapping (useful for reasoning about the
three products together) rather than a runtime abstraction, consistent with the master prompt's
instruction not to force new infrastructure where in-process reuse suffices.

## What would change this picture

Every "Deferred" row in [`MASTER_IMPLEMENTATION_TRACKER.md`](./MASTER_IMPLEMENTATION_TRACKER.md) — wiring
the remaining unused EB narrative endpoints, bridging G2G's two disconnected recommendation systems,
adding K-12's optional explanation UI, the observational citation-verification wiring and the structural
tenant-safety fix found during the Phase 6/7 audits, and a time-based retention policy — would extend
this architecture without altering its shape: each is either "connect an existing backend capability to
an existing or new UI surface" (the same pattern as the implemented pilots) or a scoped data-governance
decision that needs its own approval before any code is written. None of them requires new
infrastructure.
