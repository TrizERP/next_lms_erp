# Where KG and CG Are Actually Used, Today

A concrete inventory, not theory. Every item below is a real screen, route, or class confirmed during
this initiative's audit and implementation work — not a proposal. Marked **pre-existing** (was already
there) or **added by this work** (the two gaps this initiative actually closed).

For definitions and the conceptual model, see [`SHARED_CONTEXT_MODEL.md`](./SHARED_CONTEXT_MODEL.md).
For proof you can run live, see [`DEMO_SCRIPT_KG_CG.md`](./DEMO_SCRIPT_KG_CG.md). For full detail and
evidence behind any row here, see [`K12_IMPLEMENTATION.md`](./K12_IMPLEMENTATION.md),
[`G2G_IMPLEMENTATION.md`](./G2G_IMPLEMENTATION.md), [`EB_IMPLEMENTATION.md`](./EB_IMPLEMENTATION.md).

**One-line definitions, for reference:**
- **KG (Knowledge Graph)** — the structural facts and relationships (prerequisites, org structure,
  signal→case links). Stable, not tied to one request.
- **CG (Context Graph)** — the bounded, per-request assembly of what one decision needs right now,
  handed to existing business logic, and (where wired) recorded afterward so it's explainable later.

---

## K-12 (`next_lms_erp` + `lms_k12`)

### KG (Neo4j) — in use at

| Where | What it does | Status |
|---|---|---|
| `CoherenceMapRepository` → Coherence Map screen (6 routes, `CoherenceMapController`) | Prerequisite map, readiness, cycle detection | Pre-existing |
| `CoherenceMapRepository` → Suggested Content (`PedagogySuggestedContentService`) | `rootBlockers()`/`contentFor()` — why a concept is weak, what to show for it | Pre-existing reads, newly **recorded** (see CG below) |
| Broader "uid pipeline" graph (Students/Results/Staff) → `StudentGraphController`, `neo4jGraph\GraphController`, `neo4jGraph\StudentResultGraphController` | Whole-ERP graph exposure, separate key convention from the Coherence Map's | Pre-existing |
| `App\Services\PAL\Coherence\GraphRagRetriever` | Located during audit; caller not traced in this work | Pre-existing, not independently verified |

### CG — in use at

| Where | What it does | Status |
|---|---|---|
| `AiContextService` → AI Workspace / `ChatbotPanel.tsx` | Route → entity → capability resolution, tenant-scoped | Pre-existing |
| `AiTutorContextService` → `EsoEngineController` (AI Tutor) | Grounding + governance per learner/concept | Pre-existing |
| `AiAuditLogger` → `RecommendationDrafter`, `EvidenceStore`, `CaseBuilder`, `SignalStore`, `ExplanationBuilder`, `DecisionGate`, `AgentRunner` | General-purpose CG recorder for the whole AI domain | Pre-existing |
| `PedagogySuggestedContentContextRecorder` → **Suggested Content screen** | Records every suggestion's weak concepts + graph evidence (ids only) to `ai_audit_logs` | **Added by this work** — the one real gap found: KG evidence existed, nothing recorded it |

---

## G2G (`g2gv0` + `hp_erp`)

### KG (Neo4j) — in use at

| Where | What it does | Status |
|---|---|---|
| `GraphRagRetriever` — 13 real callers: `SkillMatchingController`, `CompetencyGapController`, `ToolController`, `SuggestedCourseController`, `DevelopmentPlanController`, `EmployeeCompetencyProfileController`, `SkillHeatmapController`, `CommandCenterController`, 2× `Mobility*Controller`, `PerformanceReviewController`, `SkillDevelopmentController` | Competency/KASBA graph reads, no LLM call in the retriever itself | Pre-existing |
| `JobRoleGraphController` | Separate, direct Neo4j query path | Pre-existing |

### CG — in use at

| Where | What it does | Status |
|---|---|---|
| `AiContextHydrator`/`AiScopeResolver` → every `/api/ai/*` route, including recommendation approve/reject/defer | Org-scope resolution, server-side only | Pre-existing |
| G2G's own `AiAuditLogger` → `RecommendationController::decide()` | Decision trace with from/to status, confidence, evidence | Pre-existing |
| `EventRecorder`/`g2g_event` → 11+ CRM controllers (`HasCrmAuditTrail`), `DepartmentManagementController`, `TaskStatusWriter`, `CertificateIssuer` | General business-event store — wider than AI, covers ordinary CRM/GTM/HRMS events too | Pre-existing |
| — | — | **Nothing added** — audited, found already complete (see `G2G_IMPLEMENTATION.md` for why the obvious candidate change was deliberately rejected) |

---

## Enterprise Brain (`hpbrain_backend` + `hp-enterprise-brain`)

### KG (MySQL projection — not Neo4j, by deliberate standing decision, ADR-008) — in use at

| Where | What it does | Status |
|---|---|---|
| `GraphController` (`graph/{tenantId}/overview\|expand\|node\|summary\|vocabulary\|search\|trail`) | Generic graph explorer | Pre-existing |
| `GraphQueryPort::detail()`/`reasoningTrail()` → `GraphExplanationController` | Generic entity explanation | Pre-existing |
| `GraphQueryPort::reasoningTrail()` → `SignalController::explainReasoning()` → **Signal Chain screen's "Explain this" button** | Walks Signal→Evidence→Case→Hypothesis→Recommendation→Decision, narrates it | Backend pre-existing, **frontend caller added by this work** |
| Same method family → `CapabilityController`, `RiskController`, `EntityIntelligenceController`, `AiWorkspaceController` (`graphNarrative()` each) | Same pattern, different entity types | Pre-existing, **still no frontend caller** — named, scoped, deferred follow-ons |
| `OrganizationalMemoryController::decisionLineage()` | ADR-005 decision→outcome→learning→mental-model chain | Pre-existing |

### CG — in use at

| Where | What it does | Status |
|---|---|---|
| `AiGateway`/`hpbrain_ai_executions` → `GraphExplanationController`, `PersonProfileController`, `RiskController`, `CapabilityController`, `KnowledgeAskController`, recommendation drafting | Generation + billing/audit trace | Pre-existing |
| `hpbrain_decisions`/`hpbrain_audit_logs` | Decision trace — the only product with first-class `confidence`/`explanation`/`trace` columns | Pre-existing |
| — | **Signal Chain "Explain this"** wiring | **Added by this work** |

---

## The pattern across all three

KG is old, structural, and already heavily used everywhere. CG — the recording/explaining half — was
*also* already working in most places. This initiative's entire code contribution was closing exactly
two narrow, confirmed gaps where KG evidence existed but nothing recorded or explained it: K-12's
Suggested Content, and Enterprise Brain's Signal Chain screen. Everywhere else (most of K-12, all of
G2G, four other endpoints in EB), KG and CG were either already connected, or — in G2G's one tempting
case — deliberately left disconnected because connecting them would have reintroduced a pattern that
codebase's own engineers had already rejected for a documented reason.
