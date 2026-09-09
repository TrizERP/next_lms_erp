# Frontend gap analysis: flipping `NEXT_PUBLIC_AI_LIFECYCLE=1`

What breaks, what disappears, and what genuinely needs building before the chat panel can
run on the Laravel lifecycle instead of the Next.js tool loop.

Both paths already exist in `ChatbotPanel.tsx:563`. The flag chooses between them:

| Flag | Path |
| --- | --- |
| `NEXT_PUBLIC_AI_LIFECYCLE=1` | `ask()` → Laravel `POST /api/ai/ask` → 12-stage lifecycle → 27 MCP tools + evidence + governance + decision gate + audit |
| unset (today) | `POST /api/ai/chat` (Next.js) → Vercel AI SDK + Gemini → 41 hand-written TS tools |

All 27 MCP tools are bound to at least one lifecycle module, so none is unreachable from
the lifecycle. Verified against `config/ai.php`.

---

## Summary

Of the 41 TS tools:

| | Count | Action |
| --- | --- | --- |
| **A** — an MCP tool already covers it | 23 | Delete the TS tool. |
| **B** — a lifecycle *stage* covers it | 10 | Delete. It stops being a tool at all. |
| **C** — genuine gap | 2 | Built: `lms.courses`, `academics.class_teachers`. |
| **D** — meta / no equivalent needed | 4 | Delete; the lifecycle does this natively. |

**Two tools were built** — `lms.courses` and `academics.class_teachers` — taking the
registry to 27. Of the other two candidates, one turned out to be already covered and one
was declined as a screen composite the planner can assemble itself. See section C.

---

## A — Covered by an existing MCP tool (23)

Delete the TS tool; the lifecycle calls the MCP tool instead.

| TS tool | MCP tool |
| --- | --- |
| `searchStudents` | `students.search` |
| `getStudentDirectory` | `students.directory` |
| `getClassStructure` | `academics.structure` |
| `getSubjectCatalog` | `academics.subjects` |
| `getTeacherDirectory` | `teachers.directory` |
| `getTeacherDailyReport` | `teachers.daily_report` |
| `getDepartmentDirectory` | `hr.departments` |
| `getDepartmentInsight` | `hr.departments` |
| `getAttendanceOverview` | `attendance.overview` |
| `getStudentAttendanceDetail` | `attendance.student` |
| `listHomework` | `homework.list` |
| `getResultReport` | `exams.results` |
| `listFeesDefaulters` | `fees.arrears` |
| `getStudentFeeDetails` | `fees.getPending` |
| `findStudentFeeRecord` | `students.search` → `fees.getPending` |
| `getFeesSummary` | `fees.collection_report` |
| `listAiTemplates` | `ai.templates.list` |
| `getAiTemplate` | `ai.templates.render` |
| `listAdmissionEnquiries` | `admissions.listEnquiries` |
| `findAdmissionCandidate` | `admissions.listEnquiries` |
| `hydrateAdmissionCandidate` | `admissions.getEnquiryDetails` |
| `updateAdmissionCandidateDetails` | `admissions.updateEnquiry` |
| `confirmAdmission` / `confirmAdmissionCandidate` | `admissions.confirm` |

Two of these are worth checking rather than assuming, because the shapes differ:

- **`getFeesSummary` → `fees.collection_report`.** The TS tool reads
  `/api/fees-dashboard/summary`; the MCP tool is a collection report. Same subject, and
  possibly not the same numbers. Diff one real institute before deleting.
- **`getDepartmentInsight` → `hr.departments`.** The MCP tool's own description says it
  "reports headcount only — it holds no training or performance data". If the TS tool
  returns more than headcount, this is a partial gap, not a match.

---

## B — Subsumed by lifecycle stages (10)

These reimplement in TypeScript, as tools, what the lifecycle does as stages. On the
lifecycle path they do not migrate — they cease to exist, and the same data arrives in the
answer without the model having to ask for it.

| TS tool | Where it goes |
| --- | --- |
| `findStudentsAtRisk` | Stage 7 real-data / signal detectors |
| `explainStudentRisk` | Stage 10 reasoning — `EntityResolver` + `CaseBuilder` + `ExplanationBuilder` |
| `listIntelligenceCases` | Stage 10, and `GET /api/ai/cases` |
| `listOpenSignals` | Stage 7, and `GET /api/ai/signals` |
| `listRecommendationsAwaitingApproval` | Stage 11, and `GET /api/ai/recommendations/pending` |
| `approveRecommendationAction` | Stage 12 — `DecisionGate` |
| `listWorkflowApprovals` | Stage 12 |
| `resolveWorkflowApproval` | Stage 12 — `DecisionGate` |
| `exploreRelationships` | Stage 10 — `GraphQueryService` |
| `resolveOntologyEntity` | Stage 10 — `EntityResolver` |

This is the category that makes the migration worth doing. Ten tools' worth of TypeScript
disappears, and the approval path stops being something a language model can call and
starts being a governed stage with a durable decision row behind it.

---

## C — Genuine gaps: two built, one already covered, one declined

The registry is now **27 tools**. Both new ones were verified against the live estate.

### Built — `lms.courses`

`app/Mcp/Tools/LmsCoursesTool.php` + `App\Services\Mcp\LmsCourseCatalogService`.

The published courses: subjects carrying content, the class each is published for, and
their chapters. Replaces `getCourseCatalog`.

The scoping is the part that was easy to get wrong. Content is published either by the
institute **or centrally**, so the subject map and the chapters are both read across the
institute *and* institute 1 — the shared library — exactly as `ApiLmsCourseController`
does. Institute 254 has **zero** chapters of its own and 98 in the shared library, so
scoping to the institute alone would return an empty catalogue for it and most others.

Verified: `{subject_id: 3975, standard_id: 42}` returns Science / Standard 9 with 13 real
chapters. Courses with `chapter_count: 0` are genuinely unchaptered, not a broken join —
checked both ways.

Bound to `lms`, `course`, `chapters`, `course-master`.

### Built — `academics.class_teachers`

`app/Mcp/Tools/AcademicsClassTeachersTool.php` + `App\Services\Mcp\ClassTeacherService`.

Who teaches a class, from the `timetable` table — the only place the teacher-to-class
relationship exists. `teachers.directory` reads the staff record, which knows a
department and not a class, so "who teaches 8B" was unanswerable from any registered tool.

The timetable stores one row per period: 23,018 rows for institute 254 in 2026. A teacher
taking five periods of one subject is five rows, so rows are collapsed to one entry per
teacher-and-subject, keeping `periods_per_week` and `week_days` — which is how a reader
tells a class teacher from someone who takes them once a week. Asked with no class and no
teacher it refuses and says to resolve the class first, rather than returning the school.

Verified: Standard-10 A returns Anuj Yadav (Mathematics, 9 periods), Tasneem Kachwala
(English, 8) and others; by `teacher_id` it inverts to the classes that teacher takes.

Bound to `hr`, `classteacher`, `proxy`.

**No `academic_year` argument.** The first draft had one; the security invariant test
would have failed it. The year is scoped context the hydrator validates against the
institute, so taking it as an argument would let a caller read another year by writing it
in the payload. It reads `$context->academicYear` only.

### Not a gap after all — `getActivityStream`

My earlier analysis was wrong. `lms.activities` **already** accepts `student_id`, resolves
the class from that student's enrolment and narrows homework to them
(`LmsActivityService` lines 156, 192-200). Nothing to build; it maps to category A.

### Declined — `lms.dashboard`

`lmsDashboardController::getDashboard` returns the student's enrolment history
(`tblstudent_enrollment`), their previous and current results, and their student record.
Every part is already a tool:

| Dashboard payload | Existing tool |
| --- | --- |
| `standardData` — enrolment history | `students.history` |
| `previousData` / `selectedCurrentData` | `exams.results` |
| `studentData` | `students.search` |

A dashboard is a screen; the lifecycle answers questions. Adding a fourth tool that
returns a blob the planner can already assemble from three precise ones would make
answers harder to ground, not easier — `LlmPlanner` plans multi-tool turns natively.
Build it only if a question turns up that genuinely needs the composite.

## D — Meta, no migration needed (4)

| TS tool | Why it goes |
| --- | --- |
| `getContextualSuggestions` | The lifecycle returns `followUpSuggestions` natively. |
| `executeModuleAction` | Stage 12 action dispatch — `WorkflowEngine`. |
| `analyzeLmsData` | A local fan-out over other tools. `LlmPlanner` + `McpToolSelectionStage` plan multi-tool turns natively. |
| `confirmAdmissionCandidate` | Duplicate of `confirmAdmission`; both become `admissions.confirm`. |

---

## Behavioural differences to verify, not gaps

**Confirmation changes shape.** Today `ChatbotPanel` posts `confirmedTools` in the request
context and the TS tool re-runs. On the lifecycle path, approval is `DecisionGate`: the
reply carries `actions`, the user clicks Approve, and the panel calls back with
`payload.recommendation_id` / `workflow_approval_id`. `toChatShapedReply` already maps
`actions`, so the rendering exists — but the round trip is different and needs testing on
a real admission.

**The provider changes.** The lifecycle path does not use Gemini. If `@ai-sdk/google` is
only there for `/api/ai/chat`, it leaves with that route — along with the quota-exhaustion
fallback path in the route handler, and the hard-coded `capitalCityMap` and
`tryEvaluateSimpleMath` answers in it, which are exactly the static business data the MCP
work was meant to eliminate.

**Streaming.** `/api/ai/chat` streams via `toUIMessageStreamResponse`. `ask()` is a single
JSON round trip returning the full trace. The panel already handles both, but perceived
latency will change — one lifecycle call runs twelve stages before it answers.

---

## Order of work

1. **Turn the flag on in staging.** Run a fixed question set through both paths and diff.
   This is the cheap step that replaces the rest of this document with measurements.
2. **Build the four category-C tools** (or decide 2 and 3 aren't needed).
3. **Verify the two uncertain category-A matches** — `getFeesSummary`, `getDepartmentInsight`.
4. **Test the approval round trip** on a real admission, in staging, against a real enquiry.
5. **Delete categories A, B and D** from `tools.ts`, `intelligence-tools.ts`,
   `module-data-tools.ts`, `schemas.ts`, plus `callBackendMcpTool` in `mcp-server.ts`.
6. **Retire `/api/ai/chat`** and the REST shim. With `callBackendMcpTool` gone,
   `/api/mcp/tools/call` has no consumers and can go well before its `Sunset`.

Steps 1 and 4 are the ones that decide whether this ships. The rest is deletion.

---

## Capability parity: closed

`/api/ai/ask` used to answer "what is the capital of Australia", "hi" and "12 times 7"
with **"Nothing to report for that question."** — which is why `/api/ai/chat` grew a
`capitalCityMap` of two dozen countries and a regex arithmetic evaluator. Both are now
replaced by a real model answer.

`App\Domain\AI\Conversation\GeneralAnswerService` calls the same OpenRouter client the
planner uses, and `LifecycleAskService::composeAnswer()` reaches it in place of the
"nothing to report" fallback.

| Capability | Before | Now |
| --- | --- | --- |
| General knowledge | `capitalCityMap`, 24 countries | Model. Verified: Canberra. |
| Arithmetic | Regex, `N op N` only | Model. Verified: 12 × 7 = 84. |
| Small talk | Four hard-coded phrases | Model. Verified: "hi". |
| Multilingual | English only | Replies in the asking language. Verified: Gujarati in, Gujarati out. |
| Follow-up suggestions | TS route | `answer.follow_ups`, contextual to a general answer rather than the ERP defaults. |
| Citations | TS route | Derived from the trace by `ask-adapter`, plus an explicit provenance section. |

### The guard, which matters more than the feature

A model that answers "how many students are in 8B?" is far worse than one that says it
does not know. `LifecycleAskService::general()` requires **all four**:

1. **The module binds no tools.** A fees question routes to the fees module, which binds
   tools, and never reaches here however badly the turn went.
2. **Only planning blocked.** "Not scoped to a module and matched no registered intent"
   *is* the signature of a non-ERP question. A block anywhere else — no permission, tool
   not bound, provider down — keeps its own message, because hiding a permissions failure
   behind a plausible sentence turns a refusal into a wrong answer.
3. **No tool returned data.** "No rows" is a correct answer and a model must not improve
   on it.
4. **No case, records or sections.** Anything the lifecycle actually built stands.

The prompt is the second line of defence: a school question that merely failed to classify
still reaches the service, and it is instructed never to state a fact about the school.
Verified live — "How many students are in class 8B?" returns *"I don't have access to the
school's records…"*, not a number.

Verified in the other direction too, through the real pipeline against the estate:

| Question | Module | Answer | Path |
| --- | --- | --- | --- |
| How many students are in Standard-10? | student | **200 students.** | ERP |
| Which students are at academic risk? | student | No students currently showing risk signals. | ERP |
| Who are the fee defaulters? | fees | No students with arrears matched. | ERP |
| What is the capital of Australia? | general | The capital of Australia is Canberra. | general, labelled |

### Provenance

A general answer carries a **Source** section saying it came from general knowledge and
that no student, staff, fee or attendance data was read. Without it the reply is
indistinguishable from one built from the institute's records, which is the one thing the
trace beside it exists to prevent.

### Degradation

`GeneralAnswerService` never throws. No API key, a provider outage, an empty completion —
all return null and the lifecycle falls back to its existing honest message. Small talk
cannot 500 the endpoint.

Covered by `tests/Unit/GeneralAnswerTest.php` — 12 tests, no database and no provider.
Five of them are aimed at the guard rather than the answer.
