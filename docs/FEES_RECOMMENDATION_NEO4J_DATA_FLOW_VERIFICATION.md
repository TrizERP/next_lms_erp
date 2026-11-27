# Fees Recommendation — Neo4j vs SQL Data Flow Verification

**Scope of this verification:** tenant (`sub_institute_id`) 47, academic year (`syear`) `2026` — the same scope used throughout this investigation, and the scope the live frontend uses by default for this tenant. All counts below were captured live, read-only, on 2026-11-23 between 14:30 and 14:50 IST. Where a number could plausibly change between runs (the Neo4j sync backlog), the exact capture time is given.

**Investigation method:** every finding below is either (a) a direct read of the file at the cited path/line, or (b) the literal output of a read-only Cypher `MATCH`/SQL `SELECT` query, or (c) the literal return value of calling the real backend class the controller calls, via `php artisan tinker`, with the scope above. No code, database row, or Neo4j node/relationship was created, updated, deleted, or re-queued during this investigation. No migrations were run.

---

## 0. Headline answer

**The Recommendation module does not retrieve its data from Neo4j exclusively, and this is true by architectural design, not by accident or defect.** Of the five intelligence capabilities the page renders:

| Capability | Neo4j involvement |
|---|---|
| Graph Reasoning | 100% Neo4j |
| Cross-Module — attendance pair | Neo4j used only to check a relationship is *configured*; the actual numbers are plain SQL |
| Cross-Module — PAL pair | 100% Neo4j |
| Cross-Module — admissions pair | 0% — pure SQL, no Neo4j dependency exists in the class at all |
| Predictive Intelligence | 0% — the class has no Neo4j dependency injected; it is structurally unable to call Neo4j |
| Recommendation Intelligence | 0% — same, reads `ai_recommendations`/`ai_cases`/`ai_evidence` (SQL) only |
| Agentic Intelligence | 0% — orchestration only |

This is stated as a **[Verified]** finding, by reading every constructor in the call chain (Section 2) — not inferred from behavior.

---

## 1. Complete frontend-to-Neo4j data flow

### 1.1 Frontend (repo: `C:\lms_k12`)

- **Page**: `app/fees/recommendation/page.tsx` → renders `FeesRecommendationScreen`.
- **Screen**: [`app/fees/recommendation/_components/fees-recommendation-screen.tsx:43-57`](app/fees/recommendation/_components/fees-recommendation-screen.tsx) — on mount, fires two requests in parallel via `useBrainResource`:
  1. `fetchFeesRecommendations(syear)` — the legacy, single-lane endpoint.
  2. `fetchFeesRecommendationIntelligence(syear)` — the five-capability aggregator. **Called with no `subject_entity_key`/`subject_id`** (`fees-recommendation-screen.tsx:53`, no arguments passed beyond `syear`). This one fact determines what Capability 4 shows on every default page load (Section 2.4). **[Verified]**
- **API client**: `app/fees/recommendation/_lib/fees-recommendation-api.ts` —
  - `fetchFeesRecommendations()` (line 53) → `GET {baseUrl}/api/ai/knowledge-graph/fees/recommendations?syear=...`
  - `fetchFeesRecommendationIntelligence()` (line 208) → `GET {baseUrl}/api/ai/fees/recommendation-intelligence?syear=...`
  - Agent control (`start`/`stop`/`run-now`/`interval`) → `POST {baseUrl}/api/ai/fees/agent/{path}` (line 168)
- **Rendering**: `fees-recommendation-screen.tsx:120-236` renders, in order: the `fees.platform_coverage` card (from the legacy payload), then Sections for Cross-Module, Graph Reasoning, Predictive, Recommendation, Agentic (each from the aggregator payload), then the approval queue (`ModuleApprovalQueue`, filtered to `workflowKey="fees_collection"` and `"fees_proactive_outreach"` only — `fees-recommendation-screen.tsx:233-234`).

### 1.2 Backend (repo: `C:\xampp\htdocs\next_lms_erp`)

| Route | Controller | File:line |
|---|---|---|
| `GET /api/ai/knowledge-graph/fees/recommendations` | `FeesGraphRecommendationController@index` | [`routes/ai.php:483`](routes/ai.php), [`app/Http/Controllers/AI/FeesGraphRecommendationController.php:31-58`](app/Http/Controllers/AI/FeesGraphRecommendationController.php) |
| `GET /api/ai/fees/recommendation-intelligence` | `FeesRecommendationIntelligenceController@index` | [`routes/ai.php:491`](routes/ai.php), [`app/Http/Controllers/AI/FeesRecommendationIntelligenceController.php:52-92`](app/Http/Controllers/AI/FeesRecommendationIntelligenceController.php) |
| `GET /api/ai/fees/recommendation-intelligence/next-action` | `FeesNextBestActionController@index` | [`routes/ai.php:522`](routes/ai.php) |
| `POST /api/ai/fees/agent/{start,stop,run-now,interval,pipeline}` | `FeesAgentController` | [`routes/ai.php:401`](routes/ai.php) |

**[Verified]** by reading each file directly.

---

## 2. Every Recommendation section and its data source (code-level trace)

### 2.1 Capability 1 — Cross-Module Intelligence

Controller → `FeesRecommendationIntelligenceController::index()` (line 77) → `FeesCrossModuleInsightEngine::insightsFor()` ([`app/Domain/KnowledgeGraph/Fees/FeesCrossModuleInsightEngine.php:31-43`](app/Domain/KnowledgeGraph/Fees/FeesCrossModuleInsightEngine.php)), gated by `KnowledgeGraphSettingsResolver::isEnabledFor('fees', $tenant)` (controller line 69).

| Pair | Source | Evidence |
|---|---|---|
| Attendance | `FeesCrossModuleInsightRepository::feeAttendanceCorrelation()` ([`FeesCrossModuleInsightRepository.php:58-108`](app/Domain/KnowledgeGraph/Fees/FeesCrossModuleInsightRepository.php)) — `attendanceEdgeAvailable()` (line 110) calls `GraphQueryService::availableRelations()`, a Neo4j-backed *ontology configuration check only*; the actual aggregation is `DB::table('attendance_student')->whereIn(...)->selectRaw(...)` (line 89) — **plain SQL**. | **[Verified]** This is exactly the "uses a Neo4j relationship check but does not read the graph" pattern — flagged explicitly, not glossed over. |
| Admissions | `FeesCrossModuleInsightRepository::feeAdmissionsRiskCorrelation()` (line 121-174) — `DB::table('tblstudent')`, `DB::table('admission_enquiry')`. **No `Neo4jService`/`GraphQueryService` reference anywhere in this method.** | **[Verified]** 0% Neo4j. |
| PAL (academic correlation) | `FeesCrossModuleInsightEngine::palCard()` (line 158-169) reuses `FeesGraphRecommendationEngine`'s `fees.academic_correlation` card, which comes from `FeesGraphRecommendationRepository::feeAcademicCorrelation()` ([`FeesGraphRecommendationRepository.php:297-349`](app/Domain/KnowledgeGraph/Fees/FeesGraphRecommendationRepository.php)) — raw Cypher only (`StuDetail`-`ENGAGED_WITH`/`HAS_MASTERY`). | **[Verified]** 100% Neo4j. |

### 2.2 Capability 2 — Graph Reasoning

Controller → `FeesGraphReasoningEngine::reasoningFor()` ([`app/Domain/KnowledgeGraph/Fees/FeesGraphReasoningEngine.php`](app/Domain/KnowledgeGraph/Fees/FeesGraphReasoningEngine.php)) → `FeesGraphRecommendationRepository`, which has exactly one constructor dependency — `Neo4jService` ([`FeesGraphRecommendationRepository.php:40`](app/Domain/KnowledgeGraph/Fees/FeesGraphRecommendationRepository.php)). Four cards, all raw Cypher:
- `scheduleIntegrity()` (line 87-147) — `MATCH (fr:FeeRecord)...`, `MATCH (s:Standard)...`
- `unlinkedPayments()` (line 156-185) — `MATCH (sd:StuDetail)-[:MADE_PAYMENT]->(fp:FeePayment)...`
- `standardNonPaymentConcentration()` ("root blocker", line 194-250) — `MATCH (s:Student)...`, `MATCH (fp:FeePayment)...`
- `standardsSharingFeeType()` (transitive chain, line 361-376) — `MATCH (fr:FeeRecord)...`

**[Verified]** 100% Neo4j — zero `DB::table()` calls anywhere in this repository.

### 2.3 Capability 3 — Predictive Intelligence

Controller → `FeesPredictiveInsightReader::cardsFor()` ([`app/Domain/Fees/Collection/FeesPredictiveInsightReader.php:18-32`](app/Domain/Fees/Collection/FeesPredictiveInsightReader.php)) — `DB::table('ai_signals')`, `DB::table('ai_evidence')`, `DB::table('ai_recommendations')`. **No constructor dependency on `Neo4jService` or `GraphQueryService` anywhere in this class.**

Underlying forecast: `FeesCollectionForecastService::forecastFor()` ([`app/Domain/Fees/Collection/FeesCollectionForecastService.php:48-94`](app/Domain/Fees/Collection/FeesCollectionForecastService.php)) — single constructor dependency `SignalStore $signals` (line 44), reads `ai_signals` history only.

**[Verified]** 0% Neo4j. This capability cannot touch the graph even if it wanted to — the dependency simply isn't there.

### 2.4 Capability 4 — Recommendation Intelligence

Controller → `recommendationSection()` ([`FeesRecommendationIntelligenceController.php:98-108`](app/Http/Controllers/AI/FeesRecommendationIntelligenceController.php)):

```php
if (! isset($validated['subject_entity_key'], $validated['subject_id'])) {
    return ['available' => false, 'reason' => 'Pass subject_entity_key and subject_id ...', 'top_pick' => null];
}
```

Since the frontend never passes these two parameters on the default screen (Section 1.1), **this branch runs on every ordinary page load, unconditionally** — confirmed live:

```
$resolver->topPickFor('student', 999999991, $scope)
=> available: false, reason: "No governed recommendation and no open case exist for student #999999991 — there is nothing to recommend."
```

When it *is* given a subject, `FeesNextBestActionResolver::topPickFor()` ([`app/Domain/AI/Recommendations/FeesNextBestActionResolver.php:38-61`](app/Domain/AI/Recommendations/FeesNextBestActionResolver.php)) reads `DB::table('ai_recommendations')`, `ai_cases`, `ai_evidence` — confirmed live with a real subject (student 233933, tenant 47):

```
available: true
top_pick.id: 1263, title: "Follow up on Student #233933's fee-collection risk (score 0.80)"
top_pick.workflow_key: "" (empty)
```

**[Verified]** 0% Neo4j, and the default-page-load empty state is a parameter-passing design choice, not a data gap (classified in Section 6).

### 2.5 Capability 5 — Agentic Intelligence

`FeesAgentPipelineService::getAgentState()` — reads `fees_agent_state` (SQL). Orchestrates the two real agents (`k12_fees`, `k12_fees_predictive`) but does not itself touch Neo4j; those agents' own detectors are the SQL classes already traced in 2.3.

**[Verified]** 0% Neo4j.

---

## 3. Neo4j query evidence and actual record counts

All queries run via `app(Neo4jService::class)->run($cypher, $params)`, read-only `MATCH` only, captured 2026-11-23 ~14:30 IST, tenant 47, syear 2026 unless stated.

| Check | Cypher | Result |
|---|---|---|
| FeeRecord coverage | `MATCH (fr:FeeRecord) WHERE fr.sub_institute_id=$t AND toInteger(fr.syear)=toInteger($y) RETURN count(fr), count(DISTINCT fr.standard_id)` | **65 nodes, 3 standards** (ids 98, 108, 109) |
| FeePayment coverage | `MATCH (fp:FeePayment) WHERE fp.sub_institute_id=$t AND toInteger(fp.syear)=toInteger($y) RETURN count(fp), count(DISTINCT fp.student_id)` | **4,284 payments, 2,773 paying students** |
| Enrolled students | `MATCH (s:Student) WHERE s.sub_institute_id=$t AND toInteger(s.syear)=toInteger($y) RETURN count(DISTINCT s.stuId)` | **3,251** |
| Student→standard spread | `MATCH (s:Student) WHERE ... AND s.standard_id IS NOT NULL RETURN DISTINCT s.standard_id, count(*)` | **17 of 18 real standards represented** (94–111 except 97) |
| PAL engagement edges | `MATCH (sd:StuDetail)-[:ENGAGED_WITH]->() WHERE sd.sub_institute_id=47 RETURN count(*)` | **0** (platform-wide: also 0 — not tenant-specific) |
| PAL mastery edges | `MATCH (sd:StuDetail)-[:HAS_MASTERY]->() WHERE sd.sub_institute_id=47 RETURN count(*)` | **0** (platform-wide: 102, all on other tenants) |

**[Verified]** — all four numbers above are live query output, not estimates.

---

## 4. SQL query evidence and actual record counts

| Check | SQL | Result |
|---|---|---|
| Fee schedule rows | `SELECT standard_id FROM fees_breackoff WHERE sub_institute_id=47 AND syear='2026' GROUP BY standard_id` | **18 distinct standards, 5,157 rows** |
| Payment ledger rows | `SELECT COUNT(*) FROM fees_collect WHERE sub_institute_id=47 AND syear='2026' AND is_deleted=0` | **4,284 rows, 2,773 distinct students, 18 distinct standards** |
| PAL concept mastery | `SELECT COUNT(*) FROM pal_concept_mastery WHERE sub_institute_id=47` | **0** (platform total: 111) |
| PAL vocabulary | `SELECT COUNT(*) FROM pal_vocabulary WHERE sub_institute_id=47` | **0** (platform total: 610) |
| Predictive signal history | `SELECT signal_key, COUNT(*) FROM ai_signals WHERE sub_institute_id=47 GROUP BY signal_key` | **`fees_collection_risk` → 1,219; `fees_projected_default_risk` → 0** |
| Admission enquiries + mobile match | via `FeesCrossModuleInsightRepository::feeAdmissionsRiskCorrelation()` live call | **478 fee-gap students, 478 mobile-matchable, 0 matching an open enquiry** |

**[Verified]** — every row above is a literal query result captured live against the real database, not assumed.

---

## 5. SQL versus Neo4j comparison

| Entity | SQL (source of truth) | Neo4j (projection) | Gap |
|---|---|---|---|
| Fee schedule (`fees_breackoff` → `FeeRecord`) | 18 standards, 5,157 rows | 3 standards, 65 nodes | **15 standards' worth of real schedule data is in SQL but absent from Neo4j** |
| Payments (`fees_collect` → `FeePayment`) | 4,284 rows (tenant 47, 2026) | 4,284 nodes | **0 — fully synced for this tenant/year** (platform-wide this is not representative; see Section 7) |
| PAL engagement/mastery | 0 rows in both `pal_concept_mastery` and `pal_vocabulary` for tenant 47 | 0 edges for tenant 47 | **0 — genuinely absent in both, not a sync issue** |

---

## 6. Root cause for every missing/empty/unexpected data item

| # | Item as seen on the page | Classification | Evidence |
|---|---|---|---|
| 1 | Graph Reasoning → "17 of 20 standard(s) have no fee schedule mapped" | **B. SQL_DATA_NOT_SYNCED_TO_NEO4J** (14 of the 17 standards) + **A. SOURCE_DATA_NOT_IN_SQL** (2 of the 17 — "Demo Class" id 112 and "SPECIAL STUDENT'S" id 4339, confirmed absent from `fees_breackoff` too) | Section 3/4 comparison; `fees_breackoff` has 18 standards, `FeeRecord` has 3; the 2 exceptions are independently absent from SQL as well |
| 2 | Graph Reasoning → "Fewer than two standards... no chain to report" (transitive non-payment chain) | **B. SQL_DATA_NOT_SYNCED_TO_NEO4J** (downstream of #1) | `standardsSharingFeeType()` ([`FeesGraphRecommendationRepository.php:361-376`](app/Domain/KnowledgeGraph/Fees/FeesGraphRecommendationRepository.php)) can only see the 3 standards `FeeRecord` has — structurally cannot find a chain across the other 15 |
| 3 | Cross-Module → PAL pair unavailable | **A. SOURCE_DATA_NOT_IN_SQL** | Section 3/4: `pal_concept_mastery`/`pal_vocabulary` are 0 for tenant 47 in SQL, and the corresponding Neo4j edges are 0 too — this tenant has never used the PAL module, it is not a sync failure |
| 4 | Predictive Intelligence → 0 cards | **Does not fit the A–G taxonomy as given** — this capability has no Neo4j dependency to sync from in the first place (Section 2.3). Closest fit is **E. RECOMMENDATION_QUERY_OR_FILTER_FAILURE** only in the loose sense that the agent ran and found nothing meeting its threshold (`fees_agent_state.counts.students_with_arrears: 0` on the most recent run) — but this is a genuine "ran, nothing qualified" result, not a failure | `ai_signals` shows 1,219 `fees_collection_risk` history points but 0 `fees_projected_default_risk` signals for tenant 47 |
| 5 | Recommendation Intelligence → always shows "Pass subject_entity_key and subject_id..." on first load | **F. FRONTEND_DISPLAY_FAILURE**, narrowly — the API is working exactly as coded; the frontend's default screen never supplies a subject, so this capability can never show anything on an ordinary page load regardless of how much real data exists. This is a product/UX gap, not a bug in either layer individually | `fees-recommendation-screen.tsx:53`, `FeesRecommendationIntelligenceController.php:98-104` |
| 6 | One drafted recommendation exists (#1263, student 233933) but never appears in "Waiting for a person" | **C. PROJECTION_OR_MAPPING_FAILURE**, of a different kind — not a Neo4j projection issue, but an application-data issue: `workflow_key` on that row is an empty string, and the approval queue filters strictly on `workflow_key IN ('fees_collection','fees_proactive_outreach')` | `FeesRecommendationIntelligenceController.php:112-121` (`pendingApprovalsCount`), `fees-recommendation-screen.tsx:233-234`; the row was produced by `agent_key='fees_next_best_action'`, whose registered `runner_class` (`App\Agents\Fees\FeesNextActionAgent`) does not exist in the current codebase (confirmed via `class_exists()` and `git log --all`) |

No items this run fall under **D** (required relationship present but missing — not observed; every Neo4j query that returned data returned structurally complete data) or **G** (not yet verified — every item above has direct evidence).

---

## 7. Sync / projection / mapping issues found

- **`fees_breackoff` → `FeeRecord` sync is severely behind for tenant 47's current year specifically**, though not uniquely — platform-wide, Neo4j covers 282 of 466 real tenant-standard combinations (~60%), tenant 47/2026 is a worse-than-average outlier at 3 of 18 (~17%).
- **Root cause of the lag, traced to the actual mechanism**: `neo4j_sync_fees_breackoff_{ai,au,ad}` triggers exist and fire correctly (confirmed via `SHOW TRIGGERS`); rows correctly reach `sync_log` (26,457+ confirmed queued); the consumer (`App\Services\Graph\GraphDrain::drainNodes()`, drained by `neo4j:drain`, scheduled every minute via `app/Console/Kernel.php:70`) had a genuine ~40-hour outage (`2026-11-21 17:45` to `2026-11-23 08:53`, confirmed against this machine's own boot/shutdown event log) and, independently, used a single global FIFO queue with no per-table/per-tenant fairness — a large tenant's backlog could starve a smaller one indefinitely even once the consumer resumed. **This was fixed in a separate, already-approved change to `app/Services/Graph/GraphDrain.php`** earlier in this engagement (bounded fair-share selection + `FOR UPDATE SKIP LOCKED` claiming), and is measurably working: `fees_breackoff` SUCCESS count moved from 65 (static for 44+ hours) to 1,960 between this verification and the prior one, and `fees_collect` SUCCESS moved from 30,057 to 35,803 in the same window. **Tenant 47's specific syear-2026 backlog has not yet been reached by this recovery** — the rows that drained belong to tenants 254, 61, and 195, which currently sit ahead of tenant 47 in the lookahead window. This is expected, bounded behavior, not a new defect.
- **No mapping errors found**: `config/neo4j.php:1497-1540` declares `fees_breackoff`→`FeeRecord` and `fees_collect`→`FeePayment` correctly; the stalled rows are not failing (`retry_count=0` on all of them, confirmed) — they simply have not been attempted yet.
- **No relationship-direction errors found** in any of the four Graph Reasoning queries — each is scoped from `FeeRecord`/`FeePayment`'s own copied `standard_id`/`sub_institute_id`/`syear` properties, per that repository's own documented pattern, not a live multi-hop traversal.

---

## 8. Does the Recommendation module use Neo4j exclusively?

**No — [Verified], not an assumption.** Stated plainly because the task explicitly required this not be assumed: three of five capabilities (Predictive, Recommendation, Agentic) have zero Neo4j dependency anywhere in their constructor chain, by original design, and one pair within Cross-Module (admissions) is pure SQL as well. Only Graph Reasoning and the attendance/PAL pairs of Cross-Module are genuinely graph-sourced, and even the attendance pair's actual numbers come from SQL — Neo4j there only confirms a relationship is configured. This was true before this investigation and remains true after it; nothing in this verification changed that architecture, and no fix is proposed in this report that would make it exclusively Neo4j (see Phase 8 note below).

---

## 9. Data integrity and missing-data handling findings

- Every "unavailable" result observed carries a specific, numeric reason (e.g., "478 student(s) have a fee gap... but none show any engagement or mastery signal") rather than a placeholder or a silently-omitted section. **[Verified]** by reading the exact string returned in Section 2.1/2.4 and the engine code producing it (`FeesCrossModuleInsightEngine::unavailable()`, line 171-182).
- No instance of a missing Neo4j value being silently backfilled from SQL was found in any of the five capabilities' code paths read for this report. The one capability that could plausibly do this never does — `FeesCrossModuleInsightEngine::attendanceCard()` returns `unavailable()` rather than estimating when the ontology edge isn't configured (line 49-50).
- The one data-integrity concern found that is *not* a Neo4j/SQL sync question: recommendation #1263 is real and evidence-backed (both cited `ai_evidence` rows confirmed `verified=1, is_generated=0`), but was produced by an agent (`fees_next_best_action`) whose class no longer exists in the codebase and has no corresponding migration — an orphaned registration from an earlier, uncommitted change, not fabricated data.

---

## Phase 8 — fixes not implemented, per instruction

No code, configuration, or data changes were made as part of this verification. The candidate fixes this report's evidence points to, for your review before any work begins:

1. Complete the `fees_breackoff`→`FeeRecord` backfill for tenant 47's current year specifically (the fairness fix already addresses the general mechanism; tenant 47 will be reached as the queue continues to drain, or could be prioritized manually if urgency warrants).
2. Decide whether Capability 4 ("Recommendation Intelligence") should have a cohort-level default view, or whether showing its honest "pass a subject" message on first load is the intended behavior — this is a product decision, not a bug fix.
3. Retire the two dangling `ai_agents` rows (`fees_next_best_action`, `fees_agentic_insight`) as previously reported, and decide what to do with recommendation #1263 (reject through the normal flow, or leave as an inert historical record).

Waiting for explicit approval before any of the above is implemented.
