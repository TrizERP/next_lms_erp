# Student Profiles → Ask / Analyse: gap analysis and implementation plan

Mapped against the real code in `D:\next_lms_erp` (backend) and `D:\lms_k12` (frontend).
Nothing below is invented — every file, route, table and column named here was read.

The page in the screenshots is `app/students/search_student/page.tsx` (`/students/search_student`,
`<h1>Student Profiles</h1>`). It matches `ai_modules.module_key = 'students'` via the route
pattern `/students/**` seeded in
`database/migrations/2026_08_20_000012_seed_ai_workspace_config.php:66`, and resolves to page
type `list` (the default in `config/ai.php`), which grants `conversational` + `agent` —
the **Ask** and **Analyse** tabs.

---

## 1. What actually exists today

### 1.1 The intelligence layer is real and largely built

`app/Domain/` already contains the five stages the specification asks for:

| Stage | Location | State |
|---|---|---|
| Ontology / KG | `app/Domain/Ontology/`, `app/Domain/KnowledgeGraph/` | Built |
| Workflow engine | `app/Domain/Workflow/` (6 step handlers, approval gate) | Built |
| Agents | `app/Domain/AI/Agents/` (`AgentRunner`, `AgentManifest`, verb ceiling) | Built |
| Signals → Evidence → Case → Explanation → Recommendation → Outcome | `app/Domain/AI/{Signals,Evidence,Cases,Explanations,Recommendations,Outcomes}` | Built |
| Governance | `app/Domain/Governance/` (`GroundedClaims`, `GovernanceValidator`) | Built |
| Generative AI | `app/Domain/GenerativeAI/` (`GenerationService`, `OutputValidator`, `SafetyChecker`) | Built |
| K-12 academic risk | `app/Domain/K12/AcademicRisk/` (3 detectors) | Built |
| HTTP surface | `routes/ai.php` (49 routes), `app/Http/Controllers/AI/` (9 controllers) | Built |

The detectors read **real tables**, not mocks:
`AttendanceRiskDetector.php:85` → `attendance_student`;
`AssessmentDeclineDetector.php:81` → `lms_online_exam`;
`MissedAssignmentDetector.php:77` → `homework`.
Tenant isolation is enforced once, in `StudentScope.php`, over `tblstudent` /
`tblstudent_enrollment`.

**So the architecture in §18 of the specification is not missing. It is built, and the
Analyse tab is wired to it.**

### 1.2 The problem: Ask and Analyse are two unrelated systems

```
ASK tab
  ChatbotPanel.tsx:418  POST /api/ai/chat  (Next.js)
    -> app/api/ai/chat/route.ts
    -> packages/conversational-ai-core/src/conversation.ts   (3,938 lines)
    -> lib/ai/adapters/lms-k12/tools.ts                      (2,745 lines)
    -> legacy Laravel REST (/api/lms-homework/students, /fees/..., ...)
                                              X  never touches app/Domain

ANALYSE tab
  AnalyseTab.tsx:85  runWorkspaceAgent()
    -> lib/intelligence/workspace.ts:297
    -> POST /api/ai/workspace/agents/{agent}/run   (Laravel, routes/ai.php:53)
    -> WorkspaceController::runAgent -> AgentRunner -> AcademicRiskAgent
    -> Signals -> Evidence -> Case -> Explanation -> Recommendation
                                              X  no conversation, no memory
```

This is the single structural defect. It produces every symptom in the screenshots:

* Ask cannot read a signal, an evidence row or a case, because it never calls the layer
  that writes them.
* Analyse cannot hold a conversation, because it is a one-shot button.
* "Only show Grade 7" and "Why is Raj at risk?" (§15) are unimplementable on either side.
* Intent detection lives in `conversation.ts` as ~40 hand-written `infer*` regex functions
  (`inferStudentName` at `conversation.ts:158`, `inferEnrollmentNo` at `:206`, …) and a
  per-tool `if (toolName === …)` summary chain starting at `conversation.ts:1383` —
  exactly the hardcoded-question approach §4 rules out.

---

## 2. The three defects in the screenshots — confirmed root causes

### Defect A — "Signals found: 1, Evidence: 4, Cases: 0" but "No students are currently showing academic risk signals"

Not a detection failure. Detection worked. **The reporting throws the result away.**

1. `CaseBuilder::warrantsCase()` (`app/Domain/AI/Cases/CaseBuilder.php:263`) opens a case
   only when a signal is `high`/`critical` (`ThresholdRegistry::isActionable()` requires
   rank ≥ 3, `ThresholdRegistry.php:123`) **or** when at least
   `CORROBORATION_COUNT = 2` moderate signals coincide on one student.
2. One `moderate` signal satisfies neither, so `buildFromSignals()` returns `null`.
3. `AcademicRiskAgent::buildCaseForStudent()`
   (`app/Domain/K12/AcademicRisk/AcademicRiskAgent.php:150`) returns `null` on a null case id
   — discarding the student, the signal and the four evidence rows it just persisted.
4. `run()` therefore returns `students_at_risk => 0, cases => []`, with **no** `message` key.
5. `summarize()` (`AcademicRiskAgent.php:88`) falls through
   `$result['message'] ?? 'No students are currently showing academic risk signals.'`
   to that literal string.

The counters come from `AgentContext::counters()` and are truthful; the sentence beside them
is not. A moderate signal below the intervention threshold is a real finding and must be
reported as one.

### Defect B — the analysis is not about the students on the screen

The page registers its real, filtered rows — ids, names, class, status
(`app/students/search_student/page.tsx:183`, `useRegisterPageAiContext`).

But `runWorkspaceAgent()` (`lib/intelligence/workspace.ts:297`) posts only
`{ route, entity_type, entity_id, limit }`. `WorkspaceController::runAgent()` builds its
agent input from the resolved context alone — there is no `student_ids` field. So
`AcademicRiskAgent::resolveStudentIds()` returns `null`, and `StudentScope::students()`
scans **up to 500 students institute-wide**, ignoring the class/status/house filters and the
search box the user is looking at.

The plumbing for the fix already exists: `resolveStudentIds()` accepts
`$context->input['student_ids']`. Nothing sends it.

### Defect C — "Find a student by name or enrollment number" finds nobody

Three independent faults stacked:

1. **The suggestion is not a question.** Seeded at
   `2026_08_20_000012_seed_ai_workspace_config.php:166` as the literal prompt
   `'Find a student by name or enrollment number.'`, it is sent verbatim as the user's
   message. `inferStudentName()` correctly extracts nothing from it — there is no name in it.
   The suggestion should open a slot-filling turn ("Who are you looking for?"), not fire a
   search with empty criteria.
2. **The search never sends the identifiers to the server.** `searchStudents()`
   (`lib/ai/adapters/lms-k12/tools.ts:1356`) POSTs to `/api/lms-homework/students` with only
   `sub_institute_id, syear, grade, standard, division`. Name, enrollment no, roll no and
   mobile are dropped, then applied client-side in `filterStudentRows()` (`tools.ts:1093`)
   against whatever page of rows came back — an exact-string match for enrollment/roll/mobile,
   with no fuzzy fallback, so §16's "3 students with a similar name" is impossible.
3. **The endpoint is the wrong one.** `StudentHomeworkApiController::studentsList()`
   (`app/Http/Controllers/api/lms/StudentHomeworkApiController.php:497`):
   * returns **422** unless both `sub_institute_id` and `syear` are present — and the caller
     sends `Number(context.syear || 0)`, which is `NaN → null` in JSON if `syear` is held as
     `"2025-2026"` rather than `2025` (`lib/result/api.ts:48` reads
     `localStorage.selectedAcademicYear`). **Verify this value first — it alone can produce a
     silent empty result.**
   * `INNER JOIN`s `tblstudent_enrollment`, `standard` and `division` and requires
     `se.end_date IS NULL`. Any student without an open enrollment row for that `syear`, or
     without a standard/division, is invisible to student search.

---

## 3. Target architecture, mapped to this code

One orchestrator, entered by both tabs.

```
Ask (free text)  --+
                   +--> POST /api/ai/workspace/ask   (new, routes/ai.php)
Analyse (chip)  ---+          WorkspaceController::ask
                                     |
                              AskOrchestrator
                                     |
            IntentResolver  (Gen AI + deterministic pre-pass, JSON-schema'd)
                                     |
            ConversationStore  (last intent, scope, case ids, subject ids)
                                     |
            IntentRouter  (table-driven: intent -> agent / ontology view)
                                     |
            AgentRunner  -- existing verb ceiling, tenant pinning, audit
                                     |
            Agent  --> Detectors / Tools --> real tables via StudentScope
                                     |
            Signals -> Evidence -> Case -> Explanation -> Recommendation
                                     |
            ResponseComposer  -> TemplateRegistry (`k12.answer.*`)
                                     |
            Structured answer envelope  ->  AnswerRenderer.tsx
```

`/api/ai/chat` is **kept**, demoted to the fallback for anything the orchestrator classifies
as `general` (greetings, arithmetic, non-domain questions). No existing behaviour is deleted.

### 3.1 New backend files

| File | Purpose |
|---|---|
| `app/Domain/AI/Conversation/AskOrchestrator.php` | The single entry point. Resolve intent → load conversation → route → run → compose. |
| `app/Domain/AI/Conversation/IntentResolver.php` | Deterministic pre-pass for unambiguous forms, then `GenerationService` with template `k12.intent.classify` and a JSON schema validated by the existing `OutputValidator`. Returns `{intent, domain, entity, filters, metrics, operator, threshold, confidence}`. Never touches student data. |
| `app/Domain/AI/Conversation/ConversationStore.php` | Reads/writes turns; exposes `lastIntent()`, `lastScope()`, `lastCaseIds()`, `lastSubjectIds()` for §15 follow-ups. |
| `app/Domain/AI/Conversation/IntentRouter.php` | `ai_intent_routes` lookup, seeded — not a PHP `switch`. |
| `app/Domain/AI/Conversation/ResponseComposer.php` | Agent result + case/evidence rows → the answer envelope. Prose from `TemplateRegistry`; numbers and names from rows. |
| `app/Domain/K12/Students/StudentDirectoryAgent.php` | `intent = student_search`. Manifest `max_verb = explain` (read-only). |
| `app/Domain/K12/Students/StudentDirectoryTool.php` | Server-side search over `tblstudent` scoped by `StudentScope`: exact match on `enrollment_no` / `roll_no` / `mobile`, `LIKE` + similarity ranking on name, `LEFT JOIN` (not `INNER`) to enrollment/standard/division so unenrolled students remain findable. |

### 3.2 New migrations

* `ai_conversations` — `conversation_reference`, `sub_institute_id`, `client_id`, `user_id`, `route`, `module_key`, `academic_year`, `term_id`, timestamps.
* `ai_conversation_turns` — `conversation_id`, `role`, `content`, `resolved_intent` (json), `agent_key`, `agent_run_id`, `case_ids` (json), `subject_ids` (json), `answer` (json).
* `ai_intent_routes` — `intent_key`, `domain`, `module_key`, `agent_key`, `workflow_key`, `ontology_view_key`, `answer_template_key`, `requires_slots` (json), `status`, `sub_institute_id` nullable for tenant override.
* Seed: `student_risk_analysis`, `student_search`, `student_evidence_lookup`, `student_recommendation`, `general`.
* Seed templates `k12.intent.classify`, `k12.answer.student_risk`, `k12.answer.student_search`, `k12.answer.evidence`, `k12.answer.no_match` into the existing `ai_templates` table.

### 3.3 Backend changes to existing files (all additive)

| File | Change |
|---|---|
| `routes/ai.php` | `Route::post('/workspace/ask', [WorkspaceController::class, 'ask'])` inside the existing middleware group. |
| `app/Http/Controllers/AI/WorkspaceController.php` | `ask()`; and in `runAgent()` accept + forward `subject_ids`, `filters`, `page_data`. |
| `app/Domain/K12/AcademicRisk/AcademicRiskAgent.php` | **Defect A.** `buildCaseForStudent()` returns a finding with `case_id => null, status => 'monitoring'` instead of `null`. `run()` returns `students_at_risk` (cased) **and** `students_monitored` (sub-threshold). `summarize()` reports both, and never claims "no signals" when `signals_detected > 0`. |
| `app/Domain/AI/Cases/CaseBuilder.php` | No threshold change. Return the reason (`below_threshold`) alongside `null` so the agent can word the answer, rather than the agent inferring it. |
| `app/Domain/AI/Workspace/CapabilityResolver.php` | **§17.** Filter suggestions against the page snapshot: hide risk analysis when the snapshot reports 0 records; add signal-derived chips by querying `ai_signals` for the visible subject ids. |
| `app/Http/Controllers/api/lms/StudentHomeworkApiController.php` | Leave alone. Student search moves to `StudentDirectoryTool` rather than overloading a homework endpoint. |

### 3.4 Frontend changes

| File | Change |
|---|---|
| `lib/intelligence/workspace.ts` | Add `askWorkspace(session, {route, question, conversation_id, entity_type, entity_id, subject_ids, page})`. **Defect B:** extend `runWorkspaceAgent` to send `subject_ids` derived from `context.selected_records` (or `context.page.records` when nothing is ticked) and the active `filters`. |
| `app/components/ChatbotPanel.tsx` | `sendMessage()` calls `askWorkspace` first; falls back to `/api/ai/chat` only when the orchestrator answers `intent = general`. Keep `conversationId` — it becomes `ai_conversations.conversation_reference`. |
| `app/components/ai-workspace/AnalyseTab.tsx` | `run()` for `action_type = 'run_agent'` dispatches through `askWorkspace` with a structured intent, so both tabs return the same envelope and the result joins the conversation thread. Render `students_monitored` findings, not only cased ones. |
| `app/components/ai-workspace/AnswerRenderer.tsx` *(new)* | Renders §14: headline, risk groups, per-student evidence chips ("View evidence"), recommended action, and the approval button routed to the existing `/api/ai/recommendations/{id}/approve`. |
| `packages/conversational-ai-core/src/conversation.ts` | Unchanged for now. It remains the `general` fallback. Domain intents stop reaching it, so the `if (toolName === …)` chain stops growing. |
| `lib/ai/adapters/lms-k12/tools.ts` | `searchStudents` is superseded for the Ask tab. Leave it registered for MCP callers, but fix the dropped identifiers (`tools.ts:1356`) so it stops reporting "no matching student" for a query it never sent. |

---

## 4. Sequencing

**Phase 1 — make the current answers honest (small, self-contained).**
Defect A (`AcademicRiskAgent`, `CaseBuilder`) and Defect B (`workspace.ts`,
`WorkspaceController::runAgent`). After this, "Find students at risk" reports the moderate
signal it found, about the students on the screen. No new tables.

**Phase 2 — the orchestrator.**
Migrations, `AskOrchestrator`, `IntentResolver`, `IntentRouter`, `ConversationStore`,
`POST /workspace/ask`. Ask and Analyse converge. `general` still falls back to `/api/ai/chat`.

**Phase 3 — student search done properly.**
`StudentDirectoryAgent` + `StudentDirectoryTool`, near-match ranking, the "3 similar names"
response of §16, and the reworded/slot-filling suggestion.

**Phase 4 — the answer surface.**
`ResponseComposer`, `k12.answer.*` templates, `AnswerRenderer.tsx`, evidence drill-down,
approval button.

**Phase 5 — dynamic suggestions (§17)** in `CapabilityResolver`, driven by the page snapshot
and `ai_signals`.

---

## 5. Pre-flight checks before writing code

1. Confirm the AI migrations `2026_08_20_000001`–`2026_08_21_000003` have actually run in the
   target database (`ai_signals`, `ai_evidence`, `ai_cases`, `ai_recommendations`,
   `workflow_definitions`, `ai_modules`, `ai_suggestions`). Every controller guards with
   `Schema::hasTable()`, so an unmigrated database degrades to empty rather than erroring —
   which is easy to mistake for a logic bug.
2. Confirm the value of `syear` in the browser (`localStorage.selectedAcademicYear` /
   `userData.syear`). If it is `"2025-2026"` rather than `2025`, `tools.ts:1362` sends `null`
   and `/api/lms-homework/students` returns 422 — sufficient on its own to explain Defect C.
3. Confirm `AI_GENERATION_MODEL` / the `ai_api_keys` rotation pool is populated, since
   `IntentResolver` will use `GenerationService`.

---

## 6. Acceptance — §22 mapped to this code

| Test | Passes when |
|---|---|
| 1 Show all students | `student_search` with no filter returns `tblstudent` rows in scope, count matching the page. |
| 2 Find student by name | `StudentDirectoryTool` name `LIKE`, server-side. |
| 3 By enrollment number | Exact match on `tblstudent.enrollment_no`, independent of the enrollment/standard joins. |
| 4 Which students are at risk | `k12_academic_risk` run; every finding cites `ai_evidence` ids; sub-threshold findings reported as monitoring. |
| 5 Grade 7 filter | `subject_ids` narrowed by `tblstudent_enrollment.standard_id` before detection. |
| 6 Follow-up | `ai_conversation_turns.resolved_intent` merged with the new turn. |
| 7 Evidence | `GET /api/ai/cases/{case}/evidence` (already exists, `routes/ai.php:69`). |
| 8 Recommendation | `ai_recommendations` row drafted by `RecommendationDrafter`, `status = pending_approval`. |
| 9 Approve | `POST /api/ai/recommendations/{id}/approve` (exists, `routes/ai.php:85`) → `WorkflowEngine` starts `k12_academic_intervention`. |
| 10 No data | `k12.answer.no_match` with near matches and refinement options. |

The §23 criterion — one question traversing Conversational AI → Intent → Gen AI → Agent →
Workflow → Ontology → real data → Evidence → Case → Explanation → Recommendation → Template →
answer — is met at the end of Phase 4. Phases 1–3 are each independently shippable.
