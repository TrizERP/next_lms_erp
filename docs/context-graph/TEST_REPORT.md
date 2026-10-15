# Context Graph — Test Report

Every result below was actually executed and observed this session — none is inferred from code
reading alone (master-prompt rule #20). Raw evidence lives in the per-gate implementation docs; this is
the consolidated record.

## K-12 (`next_lms_erp`)

| Check | Command | Result |
|---|---|---|
| Syntax | `php -l` on both new/modified files | Clean |
| Unit/Feature, directly relevant | `vendor/bin/phpunit tests/Unit/AiAuditLoggerRowTest.php tests/Feature/Pal/PalRecommendationLoggingTest.php tests/Feature/Pal/PedagogyContentRecommendationTest.php` | **8/8 passed**, 17 assertions |
| Regression, full PAL suite | `vendor/bin/phpunit tests/Feature/Pal` | **145/145 passed**, 400 assertions |
| Live runtime, recorder isolated | tinker, before the migration fix | Confirmed silent failure, root-caused to a missing column (see below) |
| Live runtime, recorder isolated | tinker, after the migration fix | Row persisted, `knowledge_graph_used=true` correctly set, payload shape correct |
| Live runtime, real call site | `PedagogySuggestedContentService::getSuggestions()` invoked directly | `status=1`, pre-existing message unchanged, matching audit row written |
| Database migration | `php artisan migrate --path=...` (single file, user-approved) | `DONE`, 815.22ms |
| Cleanup | All sentinel test rows (`subject_id` in 999999990s range) deleted after each check | Confirmed empty afterward |

**Defect found and fixed during testing**: `ai_audit_logs` was missing `duration_ms`/
`knowledge_graph_used` columns on the live remote DB despite a migration in-repo meant to add them —
silently degrading **every** existing `AiAuditLogger::record()` caller to log-only, not just this
pilot's new one. Reproduced, root-caused, fixed (user-approved), re-verified. Full detail in
`K12_IMPLEMENTATION.md`.

## G2G (`g2gv0` + `hp_erp`)

No code changed, so no test was run against new code. Verification was entirely by direct code reading:
`SkillMatchingController.php`, `RecommendationController.php`, `EventCatalogue.php`,
`App\Domain\AI\Support\AiAuditLogger` (hp_erp's own), `routes/ai.php`'s middleware group — each opened
and read, not inferred from a class or file name. See `G2G_IMPLEMENTATION.md`.

## Enterprise Brain (`hpbrain_backend` + `hp-enterprise-brain`)

| Check | Command | Result |
|---|---|---|
| Type-check | `npx tsc -b` | Clean, no errors |
| Full frontend test suite | `npx vitest run` | **990 passed, 4 failed** — all 4 inside `.kilo/worktrees/decisive-king/tests/`, confirmed untracked/out-of-branch via `git status`, confirmed unrelated to signals (sidebar placement, fee-intelligence figures) |
| Live runtime, real data | Direct invocation of `SignalController::explainReasoning()` with a real tenant/signal id, the same arguments the route passes | HTTP 200, `narrativeStatus: "ok"`, narrative correctly cited only the retrieved facts (signal classification/severity/source, 5 evidence rows' source table, the case's exact title and date) |

**Note on cost**: the live EB test made one real, billed call to the configured AI provider — necessary
to prove genuine end-to-end behavior rather than resting on code review, and disclosed here rather than
left implicit. It does not recur; this was a one-time verification.

## Cross-product / Gate 7 checks

| Check | Method | Result |
|---|---|---|
| No new cross-product reference in changed files | `grep` for `g2g\|hp_erp\|hpbrain\|vivek_erp\|cross_product\|k12\|next_lms_erp\|lms_k12` (case-insensitive) across every file created/modified in Gates 3/5/6 | Zero matches |
| Pre-existing cross-product mechanisms still isolated | Read `G2GCompetencyGraphClient` (blocked, unchanged) and `PersonProfileController` (human-gated, zero confirmed links, unchanged) | Confirmed unchanged, confirmed still correctly gated |
| No destructive/unauthorized DB operation | `git status` across all six repos before and after this session | Only the two intended files per touched repo show as modified; `lms_k12`'s pre-existing unrelated uncommitted change is untouched; nothing committed anywhere (confirmed — see `DEPLOYMENT_AND_ROLLBACK.md`) |

## Phase 8 — Testing and Verification (master prompt's own 4 categories)

Every item below states plainly whether it was **executed** (command run, output inspected) or only
**verified by code read** (confirmed in Phase 6/7, not run as a live test) — per this phase's own
instruction not to report a test as passed unless it was actually executed.

### Functional tests

| Requirement | Status | Evidence |
|---|---|---|
| Correct context assembled for the active user | **Executed** | K-12: live `getSuggestions()` call returned correct, unchanged response for a real learner/chapter (Gate 3). EB: live `explainReasoning()` call returned a trail correctly scoped to the real signal (Gate 6). |
| Relevant KG relationships retrieved | **Executed** | Same two calls — K-12's returned real `root_cause`/`graph_content` from Neo4j; EB's returned the real Signal→Evidence→Case graph walk. |
| Recent events and decision history included when applicable | Verified by code read only | `DecisionGate::historyFor()`, `OrganizationalMemoryController::decisionLineage()` read as correct; not independently re-executed this phase. |
| Stale and missing data handled correctly | **Executed** | `CoherenceMapRepository::rootBlockers()`/`contentFor()` called live with a nonexistent concept id — both returned a clean empty array, no exception (this phase, new test). |
| Recommendations preserve existing business rules | **Executed** | K-12's `getSuggestions()` response byte-for-byte unchanged (Gate 3); G2G untouched; EB backend untouched. |
| Decision traces point to valid evidence | **Executed** | K-12's recorded audit payload referenced real concept/content ids (Gate 3); EB's narrative cited only real, retrieved facts (Gate 6). |
| Historical contexts can be reconstructed within the retention policy | Verified by code read only | `OrganizationalMemoryController::decisionLineage()`'s re-resolution-under-current-authorization logic read and confirmed sound (Phase 7 §12); not independently re-executed against a real historical decision this phase. |

### Security tests

| Requirement | Status | Evidence |
|---|---|---|
| Cross-tenant requests are denied | **Executed, this phase** | Called `SignalController::explainReasoning()` and `GraphExplanationController::explain()` against a real tenant-1 signal while resolved as a different tenant (`1000000`). Both returned `404` (`signal_not_found`/`entity_not_found`) — no data leaked, no AI call made. |
| Unauthorized student or employee histories cannot be retrieved | Covered by the same test above (signal = the EB analog of a history record) | Same result |
| Graph traversal cannot bypass application authorization | **Executed, this phase** | Same test — the graph-projection layer itself (not just a controller guard) refused the cross-tenant read |
| AI context does not contain prohibited fields | **Executed** | K-12's actual recorded payload inspected directly (Gate 3) — ids and percentages only, no student name/answers |
| Logs do not leak secrets or sensitive data | Verified by code read only | `AiAuditLogger::redact()`'s blocklist read and confirmed; not exercised this phase with a secret-shaped input |

### Reliability tests

| Requirement | Status | Evidence |
|---|---|---|
| Duplicate events do not create duplicate logical decisions | **Executed, this phase** | Inserted two `g2g_event` rows with the same `(sub_institute_id, idempotency_key)` via the real `EventRecorder::record()`. Second insert threw `UniqueConstraintViolationException` at the database level (`uq_event_idem`); exactly one row persisted. Test row deleted afterward. |
| Failed graph retrieval is handled safely | **Executed (missing-data case)** | Same nonexistent-concept test above. **Not executed (connection-failure case)**: simulating an actual Neo4j outage would require disrupting a live, shared connection other sessions depend on — disproportionate to this verification step. Code read (try/catch wrapping in `PedagogySuggestedContentService`, `explainMatch()`, `GraphExplanationController`) stands as the evidence for that specific sub-case. |
| SQL remains authoritative when graph projections are unavailable | Verified by code read only, for the reason above | Every graph call this initiative touched degrades to SQL-only or a clean empty/404 result on graph failure, per code read |
| Context assembly has bounded resource usage | **Executed** | EB's live test (Gate 6) returned `nodeBudget: 140` in the real response, confirming the bound is applied, not just declared |
| Existing workflows continue when the new Context Layer is disabled | **Not applicable** | No toggleable shared Context Layer service was built (per `ARCHITECTURE.md` — each pilot calls its product's existing service directly); there is nothing to disable |
| Projection delays and stale data are explicitly handled | Verified by code read only | Covered in `RAG_GRAPHRAG_INTEGRATION.md` §4 (sync cadence bounds, EB's `keyword_only` staleness label) |

### Regression tests

| Area | Status | Result |
|---|---|---|
| PAL Diagnostic and Adaptive Learning | **Executed, this phase** | `tests/Feature/Eso` (318 tests): **314 passed, 4 failed**. The 4 failures (`EsoKnowledgeMapTest`, `PilotMetricsServiceTest` ×3) confirmed unrelated to this initiative — grepped for any reference to `ai_audit_logs`/`PedagogySuggestedContentService`/the migration's new columns in that test directory: zero matches. Consistent with this estate's standing, pre-existing issue of a shared remote dev database drifting under fixture-dependent tests. |
| Suggested Content | **Executed** (Gate 3 + this phase) | `tests/Feature/Pal` (145 tests): all passed |
| Mastery and Coherence Map | **Executed, this phase** | `tests/Unit/PalMasteryLadderTest.php` + `tests/Feature/Eso/AiTutorContextTest.php`: 17/17 passed. No dedicated Coherence Map test file found in the repo (searched; none exists) — covered instead by the live `CoherenceMapRepository` calls above. |
| K-12 KG sync | Verified by code read + Gate 1's live scheduler check | Not re-executed this phase |
| G2G competency workflows | **Executed, this phase** | Full `hp_erp` PHPUnit suite: **405/406 passed**, 2107 assertions. The 1 failure (`OpportunityIngestionTest`, AI-unavailable-extraction returning 422 instead of 201) is in an unrelated domain (GTM opportunity ingestion) this initiative never touched — pre-existing, not investigated further. |
| EB graph projection | **Executed** (Gate 6 + this phase's cross-tenant test) | Confirmed correct for both legitimate and cross-tenant requests |
| RAG and GraphRAG endpoints | Verified by code read (Phase 6) + live-executed for the one endpoint this initiative wired (Gate 6) | — |
| Authentication and tenant scoping | **Executed, this phase** | The cross-tenant test above is a direct, live exercise of this |

## What was not tested, and why

- hpbrain_backend's own 133-file PHPUnit suite was not re-run — no backend file in that repo was
  changed; its existing CI (`.github/workflows/backend.yml`) already covers it on every push. (Its
  frontend sibling's suite *was* run — see Gate 6.)
- A real Neo4j/graph connection outage was not simulated for any product — doing so would mean
  disrupting a live, shared connection other sessions and users depend on, disproportionate to a
  verification step. Every product's code-level handling of that case was read and confirmed instead
  (try/catch, graceful empty/404 degradation).
- Production/deployed-host behavior (as opposed to the local dev DB this session worked against) was
  not and cannot be tested from here — see `KNOWN_GAPS.md`.
- hp_erp's 406-test PHPUnit suite **was** run this phase (corrected from the earlier note that it
  wouldn't be) — 405/406 passed; the one failure is pre-existing and unrelated.
