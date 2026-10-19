# Shared Context Model (Gate 2 — Phase 1)

**Status: PROPOSED. Nothing in this document has been implemented.** Per the master prompt's own
gating, architectural changes that affect persistent data, graph schema, external services, or existing
contracts require separate approval before implementation begins (Gate 3+). This document identifies
every such change explicitly, in §7, so that approval can be scoped precisely.

This model is written against the findings in [`PHASE_0_AUDIT.md`](./PHASE_0_AUDIT.md). Its central
premise, carried over from that audit: **all three products already have independently-evolved partial
implementations of this model.** The job here is to define one shared conceptual shape that each
product's existing classes already mostly satisfy, map every field to its real source, and name the
handful of genuinely new (additive, optional) elements needed to make the three comparable — not to
design a new system and retrofit products into it.

## 1. What "the Context Graph" is, precisely

A **Context Object** is the bounded, authorized, timestamped answer to "what does the active service
need to know, right now, to make or explain one decision for one user in one tenant." It is assembled
at request time, mostly from references, not copies. It is not a new database. It is not a replacement
for the Knowledge Graph or SQL. Per the master prompt's own architecture rules (§3 of the master
prompt), it sits strictly below the decision-making layer: it supplies evidence, it does not decide.

## 2. Context object schema, mapped to what already exists

Each dimension below is the master prompt's own required set (§6). For each field, this table states
the shared name, and — critically — what already produces that value in each product today, per the
Phase 0 audit. "— (new)" means no existing source was found and a minimal addition is proposed in §7.

### 2.1 Identity and scope

| Shared field | K-12 (`next_lms_erp`/`lms_k12`) | G2G (`hp_erp`/`g2gv0`) | Enterprise Brain |
|---|---|---|---|
| `tenant_id` | `sub_institute_id`, enforced per-route by `RequireApiJwt`/`PalApiAuth`/etc. | `sub_institute_id`, resolved via `ResolvesApiIdentity` / `AiScopeResolver` | `tenantId`, enforced by `EnsureTenantScope` (cleanest single enforcement point found) |
| `user_id` | JWT subject / session `user_id` | Sanctum token owner / session `user_id` | JWT subject (`App\Support\Jwt`) |
| `session_id` | Frontend `AuthContext`/`menuContext` session | Not separately confirmed — **open item** | Not separately confirmed — **open item** |
| `task_id` / workflow id | Route + entity key from `AiContextService` (`moduleKey`, `entityKey`, `entityId`) | `AiContextHydrator`'s scope attribute; workflow tables exist (`WorkflowController`) | `hpbrain_cases.id` / `hpbrain_signals.id` as the natural task anchor |
| `product` / `module` | `AiContextService::$moduleKey` (data-driven, from `ai_modules` table) | Route group (`routes/{api,hrms,lms,gtm,platform,ai}.php`) | Controller module (Organization/Case/Signal/etc.) |
| `authorization_scope` | Resolved entity + capabilities list (`AiContext::capabilities`) | `ai_scope` request attribute | Role + tenant from JWT claims |

### 2.2 Temporal context

| Shared field | K-12 | G2G | Enterprise Brain |
|---|---|---|---|
| `event_timestamp` | `ai_audit_logs.created_at` / `eso_decision_log` row timestamp | `g2g_event.occurred_at` (distinct from `recorded_at` — already models capture lag) | `hpbrain_audit_logs`/`hpbrain_decisions` timestamps |
| `assembly_timestamp` | Not separately persisted — computed at `AiContextService::resolve()` call time (cached 600s) | Not separately persisted | Not separately persisted |
| `source_effective_time` | Re-read from MariaDB at projection time (`*GraphProjection.php` docblock: "re-reading is also what makes a replay correct") | Re-read at projection time (`Neo4jProjector`) | Read at `GraphBuilder` assembly time |
| `sequence/correlation_id` | — (new) | `g2g_event.correlation_id` / `causation_id` — already exists, exactly as specced | — (new) |
| `freshness/staleness` | Graph sync lag is bounded by `neo4j:drain` cadence (every minute) + `neo4j:reconcile` nightly | Bounded by `events:project` 5-minute cadence | Bounded by `brain:process-events` (every minute) |

### 2.3 Activity context

Already captured per product by the event-capture mechanisms in §2.5 below — no new structure needed.
K-12's `AiAuditLogger` constants (`RECOMMENDATION_DRAFTED`, `DECISION_RECORDED`, etc.) and G2G's
`g2g_event.type` column already enumerate activity types; EB's `brain:*` job names serve the same role.

### 2.4 Knowledge context

| Shared field | K-12 | G2G | Enterprise Brain |
|---|---|---|---|
| `kg_entity_refs` | Neo4j node ids/labels via live sync (§2.1 of audit); `EntityResolver` resolves tenant-scoped entity labels for `AiContextService` | Neo4j node ids via `GraphVocabulary` labels | `GraphBuilder`/`GraphProjection` node refs (MySQL-backed, no separate Neo4j ids) |
| `relevant_concepts/competencies` | `lms_concept`, ESO nodes (`LearnerNodeState`), misconceptions (`MisconceptionLibrary`) — all read by `AiTutorContextService` already | KASBA items, competencies via `GraphRagRetriever` Cypher | `hpbrain_capabilities` |
| `prerequisites/dependencies` | `WalksPrerequisiteGraph` (coherence graph) | `REQUIRES_COMPETENCY`/`REQUIRES_SKILL` relationships | `GraphVocabulary` relationship entries |
| `traversal_provenance` | Not separately persisted — **open item** (which Cypher/query produced which fact) | Not separately persisted — **open item** | Not separately persisted — **open item** |

**Design decision**: the Context Object never copies a KG subgraph — it carries **references** (entity
key + id + tenant) that the consuming service re-resolves through the same tenant-scoped resolver that
produced them (`EntityResolver` in K-12; the equivalent lookup in G2G/EB), exactly as `AiContextService`
already does. This directly satisfies the master prompt's rule against duplicating sensitive data in the
context layer.

### 2.5 Decision context

| Shared field | K-12 | G2G | Enterprise Brain |
|---|---|---|---|
| `decision_id` | `ai_decisions.id` | `g2g_event.id` (type=`recommendation.*`) / `hpbrain_recommendations.id` equivalent | `hpbrain_decisions.id` |
| `decision_type` | `ai_decisions.decision` (approved/rejected/deferred) | `g2g_event.type` | `hpbrain_decisions` has an explicit type column |
| `evidence_refs` | `eso_decision_log.state_snapshot`, `AiTutorContextService` grounding fields | `g2g_event.payload`, `RecommendationRepository` evidence walk | `hpbrain_decisions.trace` (JSON) |
| `rule/policy applied` | `eso_decision_log.rule_fired`, `EsoBindingRule` | Business logic in `RecommendationRepository`/workflow controllers | Not column-level, embedded in `trace` |
| `confidence` | Not uniformly present — ESO has internal mastery estimates, not exposed as a generic "confidence" field | Not found as a column | `hpbrain_decisions.confidence DECIMAL(6,4)` — the only product with this as a first-class field |
| `explanation` | `DecisionGate` reason field; frontend `LifecycleTrace`/`ResolvedEvidence` | `RecommendationRepository::explain()` | `hpbrain_decisions.explanation TEXT` |
| `outcome/feedback` | `ai_decisions` history (`historyFor()`); ESO's own mastery updates | `g2g_audit_log` | Case outcome fields on `hpbrain_cases` |

**Important asymmetry to carry forward, not paper over**: EB is the only product where `confidence` is a
first-class, calibrated column. The master prompt explicitly says confidence should only be surfaced
"where meaningfully calibrated" — so the shared schema makes `confidence` an **optional** field, present
for EB, absent (not defaulted to a fake number) for K-12/G2G unless/until those products calibrate one.

### 2.6 Provenance and governance

| Shared field | K-12 | G2G | Enterprise Brain |
|---|---|---|---|
| `source_system/record_ids` | Entity key+id pairs (already tenant-checked by `EntityResolver`) | Entity type+id pairs in `g2g_event` | `entity_type`/`entity_id` in `hpbrain_audit_logs` |
| `retrieval_timestamp` | Request time (not separately persisted) | Request time | Request time |
| `access_control_result` | Implicit — `EntityResolver` returns null for unauthorized records rather than a flag | Implicit — same pattern via `ResolvesApiIdentity` | Explicit — `EnsureTenantScope`'s 403 is itself an access-control decision, could be logged as a result value |
| `data_classification` | Not formalized — **open item** | Not formalized — **open item** | Not formalized — **open item** |
| `retention/deletion` | Governed by existing table retention (no separate policy found) | Same | Same |

## 3. Event and decision lifecycle

This is the same lifecycle in all three products already; the shared model just names the stages so they
can be talked about consistently:

```
1. ASSEMBLE   — a request arrives; tenant+user+task resolved; bounded context built from SQL + KG refs
                (K-12: AiContextService.resolve() / AiTutorContextService.forConcept();
                 G2G:  AiContextHydrator middleware;
                 EB:   GraphBuilder + per-controller SQL reads)
2. ACT        — existing business/PAL/recommendation logic consumes the context and decides
                (unchanged by this initiative — rule #4 of the master prompt)
3. CAPTURE    — the action and its evidence are written to the product's existing event store
                (ai_audit_logs / g2g_event / hpbrain_audit_logs)
4. RECORD     — if the action was a decision/recommendation, it is written to the product's existing
                decision-trace table (ai_decisions+eso_decision_log / g2g_event+g2g_audit_log /
                hpbrain_decisions)
5. OUTCOME    — subsequent activity (a student's next attempt, a manager's follow-up, a case closing)
                is captured the same way and can be joined back to the decision by existing foreign keys
6. RECONSTRUCT— an authorized caller rebuilds "what context led to this decision" by re-running ASSEMBLE
                with the decision's recorded tenant/entity/timestamp inputs, not by reading a stored copy
```

Step 6 is why the Context Object is defined as mostly-references: reconstruction re-resolves references
under the *current* authorization check, so a context can never be replayed to show data the
reconstructing caller isn't currently allowed to see — even if the original caller was.

## 4. Relationship to the existing Knowledge Graph

No KG schema, label, or relationship type changes. The Context Object's knowledge-context fields (§2.4)
are pointers into the existing graphs (K-12's live-synced Neo4j graph, G2G's `Neo4jProjector`-fed graph,
EB's MySQL `GraphProjection`) resolved through each product's existing tenant-scoped resolver. This
satisfies master-prompt rule #6 ("do not recreate or redesign the Knowledge Graph") by construction —
there is nothing here to redesign it with.

## 5. Data ownership

| Layer | Owns | Does not own |
|---|---|---|
| SQL | Transactional facts (students, employees, cases, scores, org records) | Structural relationships, runtime context |
| Knowledge Graph | Entities and relationships (Concept→Prerequisite, Competency→Skill, Org→Department) | Transactional facts, decision history |
| Context Layer | The *assembled view* for one request: references + minimal inlined fields needed for the task, plus the lifecycle stages in §3 | Nothing durable of its own beyond what's in §7's proposed additive columns — it does not become a second copy of SQL or KG data |
| Decision/Event tables (already exist per product) | Durable record of what was decided/happened and why | The live context that produced it (that's reconstructed, per §3 step 6, not stored verbatim) |

## 6. Security model

- **Enforcement point**: tenant/authorization check happens *inside* context assembly, not assumed from
  the caller. This is already how K-12's `EntityResolver` behaves (silently drops an entity rather than
  leaking its existence) and is the model to apply uniformly — important because the audit found G2G has
  no single global tenant-scope class (routes were found historically unguarded) and must not inherit
  that gap into the shared contract.
- **No cross-product context sharing.** The one real cross-product wire found (K-12→G2G,
  `G2GCompetencyGraphClient`) stays exactly as blocked as the audit found it — a Context Object is never
  assembled using another product's tenant data, and this model does not change that client's status.
- **Minimum necessary fields.** A Context Object sent to an AI provider carries only the fields the task
  needs (mirrors K-12's `PageAiContext` `LIMITS` pattern) — not a dump of every available field.
- **No chain-of-thought storage.** Per master-prompt rule, `explanation`/`trace` fields store the
  evidence-based reasoning already written by existing services (`DecisionGate` reasons,
  `hpbrain_decisions.explanation`), never a model's private reasoning.

## 7. Proposed changes — the complete list (all require separate approval before implementation)

This is deliberately short, because the audit found most of the needed machinery already exists.

1. **(K-12, G2G, EB) Optional `context_version` and `context_ref` columns** on each product's existing
   decision-trace table (`ai_decisions`, `g2g_event`/`g2g_audit_log`, `hpbrain_decisions`) — nullable,
   additive, backward-compatible. Purpose: let a decision record which shared-schema version its context
   was assembled under, and (optionally) a correlation id for faster reconstruction. **Not required for
   v1** — reconstruction works without it via §3 step 6; this only speeds it up. Can be deferred.
2. **(Enterprise Brain only) A new, thin `ContextAssemblyService`-equivalent class.** This is the one
   product where no existing class plays this role end to end — `GraphBuilder`/`GraphProjection` assemble
   graph context only; SQL-side case/signal/evidence context is currently assembled ad hoc per
   controller. Proposed: one new service combining the two, modeled directly on K-12's
   `AiContextService` shape. This is new code, not a database change, and is the only place this model
   proposes a genuinely new component rather than a wrapper.
3. **(All three) A documented, shared integration contract** (see `CONTEXT_LAYER_DESIGN.md` §5) — an
   interface/shape definition only, implemented per product by wrapping existing services. No new
   network service, no new database, per master-prompt rule against unjustified new infrastructure.
4. **No changes proposed to**: Neo4j schema (any product), KG labels/relationships, existing RAG/GraphRAG
   behavior, existing decision-table primary structure, existing AI provider abstractions, EB's
   deliberately-dormant Neo4j adapter, the blocked K-12→G2G credential.

## 8. Versioning strategy

The shared schema itself is versioned (`context_schema_version`, starting at `1`), additive-only going
forward (new optional fields, never removed/renamed fields) so that a consumer written against v1 never
breaks when v2 adds a field. Per-product adapters declare which schema version they emit; a mismatch is
a loud error, not a silent best-effort mapping.

## 9. Cross-product differences (kept explicit, not papered over)

| Dimension | K-12 | G2G | Enterprise Brain |
|---|---|---|---|
| Tenant key name | `sub_institute_id` | `sub_institute_id` (different meaning — HR tenant, not a school) | `tenantId` |
| Auth mechanism | JWT + legacy session hybrid | Sanctum token or session hybrid | HS256 JWT only (cleanest) |
| Graph store | Neo4j (live, trigger-sync) | Neo4j (live, 5-min projector) | MySQL projection (Neo4j dormant by ADR-008) |
| Existing context-assembly analog | `AiContextService` (general) + `AiTutorContextService` (PAL-specific) | `AiContextHydrator`/`AiScopeResolver` (AI routes only) | None general-purpose — proposed in §7.2 |
| Confidence as a field | No | No | Yes, calibrated |
| CI | None | None | Yes, both repos |

---

*Next: `CONTEXT_LAYER_DESIGN.md` defines the reusable capability shapes (Context Assembly, Retrieval,
Event Capture, Decision Trace, Lifecycle, Integration Contract) that implement this model per product.*
