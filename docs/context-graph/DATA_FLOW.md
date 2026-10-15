# Context Graph — Data Flow

Concrete, file-level data flow for each implemented pilot. For architecture rationale see
[`ARCHITECTURE.md`](./ARCHITECTURE.md); for evidence and test results see
[`TEST_REPORT.md`](./TEST_REPORT.md).

## K-12 — Suggested Content recording

| Step | What happens | File |
|---|---|---|
| 1 | Student's PAL screen requests suggestions for a chapter | frontend, unchanged |
| 2 | `getSuggestions(array $context)` resolves learner, mastery data, weak concepts | `app/Services/PAL/Integration/PedagogySuggestedContentService.php` (unchanged logic) |
| 3 | For each weak concept, root-cause blockers and targeted content are read from the live Neo4j graph | `CoherenceMapRepository::rootBlockers()`/`contentFor()` (unchanged) |
| 4 | Content is scored, categorised, returned — **unchanged return shape** | same file |
| 5 | **NEW**: immediately before `return`, the already-computed weak-concept evidence (ids only) and content-category counts are handed to the recorder | `PedagogySuggestedContentContextRecorder::record()` |
| 6 | Recorder extracts concept/content **ids** (never full rows), builds a `knowledge_graph_used` flag from whether any evidence was graph-sourced, and calls the existing audit mechanism | same file → `AiAuditLogger::record()` |
| 7 | One row lands in `ai_audit_logs`, tenant-scoped by the `sub_institute_id` already in scope | `ai_audit_logs` table |
| 8 | Response returns to the student exactly as before | — |

**Data that crosses a boundary**: none new. `sub_institute_id` flows through unchanged from the
existing session-derived `$context` array. No student name, answer, or content body is written to the
audit row — only numeric ids and a mastery percentage already present in the pre-existing response.

## G2G — unchanged (audited, not modified)

```
Request → AiAuth → AiRateLimit → AiContextHydrator (resolves org scope server-side)
        → RecommendationController::decide()
        → RecommendationRepository (SQL read/write on hpbrain_recommendations)
        → AiAuditLogger::record() → ai_audit_logs (G2G's own table, separate from g2g_event)
```
No new data flow. Documented in `G2G_IMPLEMENTATION.md` for completeness of the cross-product picture.

## Enterprise Brain — Signal explanation

| Step | What happens | File |
|---|---|---|
| 1 | User opens a signal's chain view (pre-existing) | `src/components/signal/SignalChainView.tsx` |
| 2 | Chain (signal, evidence, case, hypothesis, reasoning, recommendation, decision, execution, outcome, learning) loads from the existing workspace endpoint | `api.getSignalChain()` → `workspace/{tenantId}/signal/{signalId}/chain` (unchanged) |
| 3 | **NEW**: user clicks "Explain this" (on demand — billed, never automatic) | new `Panel` in same file |
| 4 | Frontend calls the new client function | `src/api/intelligence.ts` → `explainSignalReasoning()` |
| 5 | Request hits the existing, already-routed, permission-gated endpoint | `GET signals/{tenantId}/{id}/reasoning` → `SignalController::explainReasoning()` (unchanged backend) |
| 6 | Retrieval: pure graph walk, no model call, tenant-scoped | `GraphQueryPort::reasoningTrail($tenant, 'Signal', $id)` (MySQL projection) |
| 7 | Generation: strictly grounded on step 6's facts only, system prompt forbids invention | `AiGateway::complete()` |
| 8 | Narrative + trail returned; frontend shows the narrative text or an honest empty/error state | same `Panel` |

**Data that crosses a boundary**: none new. `tenantId` is the prop the screen already receives,
resolved server-side before this code runs (`EnsureTenantScope`/`AuthenticateJwt`, unchanged). This
step performed one real, billed AI-provider call during verification (not production traffic) — see
`TEST_REPORT.md`.

## Cross-product boundary (checked, not changed)

Both new backend/frontend file sets were grepped for any literal reference to another product
(`g2g`, `hp_erp`, `hpbrain`, `vivek_erp`, `cross_product`, `k12`, `next_lms_erp`, `lms_k12`) — zero
matches in any file this work touched. The two pre-existing cross-product mechanisms found during audit
(K-12→G2G's blocked competency client; EB's human-gated Person Profile identity resolver) were read,
documented, and left exactly as found.
