# Student Profiles — complete 14-stage AI test plan

**Module:** Student Profiles
**Frontend page:** `D:\lms_k12\app\students\search_student\page.tsx` → `/students/search_student`
**Backend module key:** `ai_modules.module_key = 'students'`
**Reference agent:** `k12_academic_risk` (`app/Domain/K12/AcademicRisk/AcademicRiskAgent.php`)
**Reference workflow:** `k12_academic_intervention`

---

## READ THIS FIRST — current pass/fail status

The intelligence layer is built. **The orchestrator that joins Ask to it is not.**
Running this plan today, tests T1–T4, T13–T16 and T21–T23 will fail, for the reasons
established in `STUDENT_PROFILES_AI_REFERENCE_MODULE.md`. That is expected. This document is
both the test plan and the acceptance spec for the five implementation phases.

| Stage | Tests | Status today | Unblocked by |
|---|---|---|---|
| 1 Conversational AI | T1–T3 | ✗ no domain intent layer | Phase 2 |
| 2 Gen AI (intent/entity) | T4 | ✗ regex `infer*` only | Phase 2 |
| 3 Agent selection | T5–T6 | ◑ Analyse tab only, one agent | Phase 2 |
| 4 Workflow (detection) | T7–T8 | ◑ runs, mis-scoped, mis-reported | **Phase 1** |
| 5 Ontology / KG | T9–T10 | ✓ endpoints work | — |
| 6 Real data | T11 | ✓ detectors read real tables | — |
| 7 Evidence | T12 | ✓ written; ✗ not reachable from Ask | Phase 2/4 |
| 8 Case | T13–T14 | ◑ opens; sub-threshold discarded | **Phase 1** |
| 9 Explain | T15 | ◑ written; not conversational | Phase 2/4 |
| 10 Recommendation | T16–T17 | ✓ drafted; ✗ not askable | Phase 2/4 |
| 11 Human approval | T18–T19 | ✓ endpoint works | — |
| 12 Action | T20 | ✓ workflow runs | — |
| 13 Outcome | T21 | ◑ baseline captured; measure manual | — |
| 14 Learning | T22 | ✓ `effectiveness` endpoint | — |
| Follow-up conversation | T23–T25 | ✗ no conversation memory | Phase 2 |
| Analyse suggestions | T26–T27 | ◑ static | Phase 5 |
| Template engine | T28–T30 | ◑ agent prose is hardcoded | Phase 4 |

Legend: ✓ passes now · ◑ partially passes · ✗ cannot pass yet

---

## Pre-flight (do this before any test)

**P1 — Confirm the tables exist.** Every controller guards with `Schema::hasTable()`, so an
unmigrated database returns empty results instead of errors. That failure mode looks exactly
like "the AI found nothing".

```sql
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'ontology_entities','ontology_relationships',
    'ai_signal_definitions','ai_signals','ai_evidence',
    'ai_cases','ai_case_signals','ai_case_evidence','ai_hypotheses','ai_explanations',
    'ai_recommendations','ai_decisions','ai_outcomes','ai_audit_logs',
    'ai_agents','ai_agent_tools','ai_agent_runs',
    'workflow_definitions','workflow_versions','workflow_runs','workflow_steps',
    'workflow_approvals','workflow_outcomes',
    'ai_templates','ai_generation_requests','ai_generation_outputs',
    'academic_interventions','academic_intervention_activities',
    'ai_modules','ai_suggestions','ai_ontology_views'
  );
```
Expect **31 rows**. Fewer → run `php artisan migrate` before continuing.

**P2 — Confirm the seed data.**
```sql
SELECT agent_key, max_verb, status FROM ai_agents WHERE agent_key = 'k12_academic_risk';
SELECT workflow_key, trigger_type, status FROM workflow_definitions WHERE workflow_key = 'k12_academic_intervention';
SELECT module_key, capabilities FROM ai_modules WHERE module_key IN ('student','students');
SELECT capability, label, action_type, action_ref FROM ai_suggestions WHERE module_key = 'students';
SELECT signal_key, label, status FROM ai_signal_definitions WHERE domain = 'k12';
```
`ai_agents.max_verb` must be `recommend` — if it is `act`, the agent could bypass approval.

**P3 — Confirm `syear`.** In the browser console on the Student Profiles page:
```js
localStorage.getItem('syear'); localStorage.getItem('selectedAcademicYear');
```
Must be a plain integer year (`2025`), not `"2025-2026"`. If it is a range,
`lib/ai/adapters/lms-k12/tools.ts:1362` sends `null` and student search 422s silently.

**P4 — Pick a real test student.** Do not invent one. Find a student who genuinely has
risk-bearing data, so the pipeline has something to detect:
```sql
SELECT s.id, CONCAT_WS(' ', s.first_name, s.last_name) AS name,
       se.standard_id, se.section_id, se.roll_no, s.enrollment_no
FROM tblstudent s
JOIN tblstudent_enrollment se
  ON se.student_id = s.id AND se.sub_institute_id = s.sub_institute_id
WHERE s.sub_institute_id = :sub_institute_id
  AND se.syear = :syear AND se.end_date IS NULL
  AND EXISTS (SELECT 1 FROM attendance_student a
              WHERE a.student_id = s.id AND a.sub_institute_id = s.sub_institute_id)
LIMIT 20;
```
Record the id and name. **Everywhere below, `<STUDENT>` means this real student's name and
`<SID>` their `tblstudent.id`.** Never type "Raj Patel" unless a Raj Patel exists.

**P5 — Baseline the counters** so you can tell new rows from old:
```sql
SELECT (SELECT COUNT(*) FROM ai_signals)          AS signals,
       (SELECT COUNT(*) FROM ai_evidence)         AS evidence,
       (SELECT COUNT(*) FROM ai_cases)            AS cases,
       (SELECT COUNT(*) FROM ai_recommendations)  AS recs,
       (SELECT COUNT(*) FROM ai_agent_runs)       AS runs;
```

---

# STAGE 1 — Conversational AI

*Does the system understand the question and choose the right destination?*

### T1 — Intent: student search (slot-filling)

**Where:** Student Profiles → **Ask**
**Type:** `Find a student by name.`

**Expected:** the assistant recognises a search intent with the name slot **empty**, and asks
for it — *"Which student? Give me a name, enrollment number or roll number."*
It must **not** run a search and report "no matching student".

**Verify internally:**
```sql
SELECT id, resolved_intent, agent_key FROM ai_conversation_turns ORDER BY id DESC LIMIT 2;
```
`resolved_intent` must contain `{"intent":"student_search", ..., "missing_slots":["name"]}`.

**Fails today because:** the seeded suggestion is the literal sentence
*"Find a student by name or enrollment number."* (`2026_08_20_000012_..._seed_ai_workspace_config.php:166`),
sent verbatim as the user's message. There is no slot model. **Phase 2 + 3.**

**Connects to:** T2 supplies the missing slot.

---

### T2 — Intent: student search (slot supplied)

**Type:** `Find <STUDENT>.`

**Expected:** the real student record — full name, class/standard, division, roll no,
enrollment no — sourced from `tblstudent`.

**Verify internally:**
* `resolved_intent` = `{"intent":"student_search","entity":"student","filters":{"name":"<STUDENT>"}}`
* `ai_agent_runs`: newest row has `agent_key = 'k12_student_directory'`, `trigger_type = 'conversation'`, `status = 'completed'`.
* The returned id exists: `SELECT * FROM tblstudent WHERE id = <SID>;`
* `ai_audit_logs`: an `agent.run` row with `outcome = 'success'` and the correct `sub_institute_id`.

**Verify it is not fabricated:** change one letter of the name (`Fnid <STUDENT>x`). The system
must return no match — not a plausible-looking student.

**Fails today because:** `searchStudents` (`tools.ts:1356`) never sends the name to the
server. **Phase 3.**

---

### T3 — Intent: search by enrollment number

**Type:** `Find student with enrollment number <ENROLLMENT_NO>.` (use a real one from P4)

**Expected:** exactly that student.

**Verify internally:**
* `resolved_intent.filters.enrollment_no` is populated.
* The match is an **exact** comparison against `tblstudent.enrollment_no`.
* The lookup must succeed **even if the student has no open enrollment row** — this is the
  `INNER JOIN` trap in `StudentHomeworkApiController::studentsList():512-527`. Test it
  deliberately with a student whose `tblstudent_enrollment.end_date IS NOT NULL`.

**Then test the no-match path:** `Find student with enrollment number ZZ999999.`
Expected: *"I couldn't find an exact match for ZZ999999. Did you mean … ?"* with near matches —
never a bare dead end (spec §16).

**Fails today because:** identifiers are filtered client-side with `===` and the endpoint's
inner joins hide unenrolled students. **Phase 3.**

---

# STAGE 2 — Gen AI (natural-language understanding)

*Does understanding survive rephrasing, without a hardcoded question list?*

### T4 — Four phrasings, one intent

Ask each in a **fresh** conversation (click New chat between them):

1. `Find <STUDENT>.`
2. `Show me the profile of <STUDENT>.`
3. `I want to see <STUDENT>'s details.`
4. `Can you find the student named <STUDENT>?`

**Expected:** all four resolve to `intent = student_search`, `entity = student`,
`filters.name = <STUDENT>`, and return the same record.

**Verify internally:**
```sql
SELECT id, content, JSON_EXTRACT(resolved_intent,'$.intent') AS intent,
       JSON_EXTRACT(resolved_intent,'$.filters.name') AS name,
       JSON_EXTRACT(resolved_intent,'$.confidence') AS conf
FROM ai_conversation_turns WHERE role = 'user' ORDER BY id DESC LIMIT 4;
```
All four rows: same `intent`, same `name`.

**Also verify the classifier is not doing data work:**
```sql
SELECT template_key, LEFT(rendered_prompt, 400) FROM ai_generation_requests ORDER BY id DESC LIMIT 4;
```
The `k12.intent.classify` prompt must contain **only the user's words and the page context** —
no student rows, no scores, no names from the database. Gen AI classifies; it must never be
handed the data it could then invent from (spec §5).

**Now the negative test:** `What is the capital of France?`
Expected: `intent = general`, routed to the `/api/ai/chat` fallback, **no** `ai_agent_runs` row.

**Fails today because:** intent is 40 regexes in `packages/conversational-ai-core/src/conversation.ts`
(`inferStudentName:158`, `inferEnrollmentNo:206`, …). Phrasings 2 and 3 do not match its
patterns. **Phase 2.**

---

# STAGE 3 — Agent selection

*Does the right agent run, and does it call only the data it needs?*

### T5 — Correct agent for a risk question

**Type:** `Which students are at academic risk?`

**Expected:** `k12_academic_risk` runs — **not** the student directory agent.

**Verify internally:**
```sql
SELECT id, run_reference, agent_key, trigger_type, status, confidence,
       signals_detected, evidence_collected, cases_opened, recommendations_drafted,
       duration_ms, sub_institute_id
FROM ai_agent_runs ORDER BY id DESC LIMIT 1;
```
`agent_key = 'k12_academic_risk'`, `status = 'completed'`, `sub_institute_id` = yours.

### T6 — The agent's tool choice is licensed, not free

```sql
SELECT tool_calls FROM ai_agent_runs ORDER BY id DESC LIMIT 1;
SELECT allowed_signal_keys, max_verb FROM ai_agents WHERE agent_key = 'k12_academic_risk';
```

**Verify:**
- [ ] `tool_calls` lists only detectors named in `allowed_signal_keys`. `AcademicRiskAgent::detectAll():112` skips any detector the manifest does not permit — confirm a signal key removed from the manifest actually stops appearing.
- [ ] `max_verb = 'recommend'`. Then confirm the ceiling holds: **no** `academic_interventions` row was created by this run.
```sql
SELECT COUNT(*) FROM academic_interventions WHERE created_at > NOW() - INTERVAL 5 MINUTE;
```
Must be **0**. The agent may recommend; only an approved workflow may act.
- [ ] No LLM call touched student data:
```sql
SELECT template_key FROM ai_generation_requests WHERE created_at > NOW() - INTERVAL 5 MINUTE;
```
Detection must appear here **not at all** — it is deterministic SQL, not generation.

**Status:** works today from the Analyse tab. Fails from Ask (no router). **Phase 2.**

---

# STAGE 4 — Workflow / detection pipeline

*Do all ten detection steps execute over the right population?*

### T7 — Scope is the students on the screen

**Setup:** on Student Profiles, apply a class filter so the list shows a **known small set**
(e.g. 6 students). Note the count and their names.

**Then:** Analyse → `Find students at risk`.

**Verify internally:**
```sql
SELECT input FROM ai_agent_runs ORDER BY id DESC LIMIT 1;
```
`input.student_ids` must be **exactly the ids of the filtered rows on screen**.

Then confirm nothing outside that set was touched:
```sql
SELECT DISTINCT subject_id FROM ai_signals WHERE detected_by_run_id = <RUN_ID>;
```
Every id must be in your filtered set.

**Fails today because:** `runWorkspaceAgent` (`lib/intelligence/workspace.ts:297`) sends only
`route`/`entity_type`/`entity_id`; `resolveStudentIds()` returns `null`; `StudentScope` scans
**up to 500 institute-wide students**. The answer describes a different population than the
screen. **Phase 1.**

### T8 — Each detection step ran against real tables

```sql
-- Step 5: signals raised
SELECT id, signal_key, subject_id, subject_label, score, severity, confidence,
       components, status, detected_at
FROM ai_signals WHERE detected_by_run_id = <RUN_ID>;

-- Step 6: evidence gathered
SELECT id, kind, subject_id, source_table, source_column, source_id,
       summary, numeric_value, unit, verified, observed_at
FROM ai_evidence WHERE subject_id IN (<ids>) ORDER BY id DESC LIMIT 20;
```

**Verify:**
- [ ] `signal_key` ∈ `{assessment_decline, attendance_risk, missed_assignments}` (per the seed).
- [ ] `ai_evidence.source_table` names a **real** business table — `attendance_student`,
      `lms_online_exam`, `homework` — never `ai_*`.
- [ ] `ai_evidence.source_id` resolves. Pick one row and prove it:
```sql
SELECT * FROM attendance_student WHERE id = <source_id>;
```
- [ ] `verified = 1` on evidence that gets cited (`AcademicRiskAgent::buildClaims()` only cites
      verified rows).
- [ ] `is_generated = 0` on every detection-time evidence row. **Any `is_generated = 1` here is
      a failure** — it would mean a model wrote a fact.
- [ ] `sub_institute_id` on every new row matches yours, and no other tenant's rows changed.

**Connects to:** the signals from T8 are the input to the case decision in T13.

---

# STAGE 5 — Ontology / Knowledge Graph

### T9 — Relationship walk for one student

**API:**
```
POST /api/ai/workspace/ontology-views/student-learning
     { "route": "/students/search_student", "entity_type": "student", "entity_id": <SID> }
POST /api/ai/workspace/ontology-views/student-evidence
     { "route": "/students/search_student", "entity_type": "student", "entity_id": <SID> }
```
(Both seeded at `2026_08_20_000012_..._seed_ai_workspace_config.php:204` and `:219`.)

**Expected:** `student-learning` returns Student → Class/Standard → Subject → Assessment.
`student-evidence` returns Student → Signal → Case → Recommendation.

**Verify:**
- [ ] Every node's `subject_id` is `<SID>`. **No other student appears anywhere in the graph.**
- [ ] Standard/division match `tblstudent_enrollment` for that student and `syear`.
- [ ] `SELECT entity_key, label FROM ontology_entities WHERE status = 1;` contains `student`,
      `standard`, `division`, `subject`.
- [ ] `SELECT * FROM ontology_relationships WHERE from_entity_key = 'student';` — the edges you
      walked are declared, not improvised.

### T10 — Conversational form of the same walk

**Type:** `Why is <STUDENT> at risk?`

**Expected:** an explanation that names the relationships, e.g.
*"<STUDENT> (Standard 7-B) is showing academic risk: attendance is 62% absent over 40 recorded
days, and Mathematics assessment performance declined from 58% to 41% across the last 3
assessments."*

**Verify internally:**
```sql
SELECT id, case_id, audience, narrative, grounded, governance_passed
FROM ai_explanations WHERE case_id = <CASE_ID> ORDER BY id DESC LIMIT 1;
```
- [ ] Every number in the narrative appears in an `ai_evidence` row for `<SID>`.
- [ ] `governance_passed = 1`. If 0, the UI must show the refusal, **not** a confident sentence
      (`AnalyseTab.tsx:265` already does this — confirm it still does).

**Cross-contamination test (critical):** run the analysis over 3+ students, then ask
`Why is <STUDENT> at risk?`. Every cited number must belong to `<SID>`:
```sql
SELECT e.id, e.subject_id, e.kind, e.summary
FROM ai_case_evidence ce JOIN ai_evidence e ON e.id = ce.evidence_id
WHERE ce.case_id = <CASE_ID>;
```
`subject_id` must be `<SID>` on **every** row.

**Status:** T9 passes today via the API. T10 needs the conversational route. **Phase 2/4.**

---

# STAGE 6 — Real data

### T11 — Nothing is mocked

- [ ] `SELECT COUNT(*) FROM ai_evidence WHERE source_table IS NULL;` → new rows must be **0**.
- [ ] Change one underlying fact (mark one extra absence for `<SID>` in `attendance_student`),
      re-run the analysis, and confirm the recomputed `ai_signals.components.absence_rate`
      moves. If the number does not move, it was not read from the table.
- [ ] Grep the response for placeholder names. Any answer containing a student not in
      `tblstudent` for your `sub_institute_id` is an immediate fail.
- [ ] Tenant isolation: log in as another institute and confirm `<SID>` is unreachable —
      `StudentScope::students()` filters on `sub_institute_id` **and** `allowedInstituteIds`.

---

# STAGE 7 — Evidence

### T12 — Every conclusion cites evidence

**Type:** `What evidence supports this risk?`
**Or:** `GET /api/ai/cases/{case}/evidence` (`routes/ai.php:69`)

**Expected:** an itemised list — kind, value, unit, observation date, source.

**Verify:**
- [ ] `ai_explanations.claims` — each claim carries a non-empty `evidence_ids` array.
      `AcademicRiskAgent::composeClaim()` returns `null` for a claim with nothing to cite, so an
      uncited claim should be **absent**, never present-and-unsupported.
- [ ] Every `evidence_id` exists, has `verified = 1`, and `subject_id = <SID>`.
- [ ] `ai_case_evidence.role` is `supporting` / `contradicting` / `context` — and contradicting
      evidence, if present, is shown, not hidden.
- [ ] Traceability, end to end, for one number:
```sql
SELECT e.summary, e.numeric_value, e.unit, e.source_table, e.source_id
FROM ai_evidence e WHERE e.id = <EVIDENCE_ID>;
-- then open e.source_table / e.source_id and confirm the value matches
```

**This is the most important test in the document.** If a number in the answer cannot be walked
back to a business-table row, the grounding guarantee is broken and nothing downstream can be
trusted.

---

# STAGE 8 — Case

### T13 — A case opens only when warranted

**Verify the rule** (`CaseBuilder::warrantsCase():263`): a case opens when a signal is
`high`/`critical`, **or** when ≥2 `moderate` signals coincide on one student.

```sql
SELECT c.id, c.case_reference, c.case_type, c.title, c.severity, c.priority_score,
       c.status, c.subject_id, c.subject_label, c.opened_by_run_id,
       (SELECT COUNT(*) FROM ai_case_signals  WHERE case_id = c.id) AS signal_count,
       (SELECT COUNT(*) FROM ai_case_evidence WHERE case_id = c.id) AS evidence_count
FROM ai_cases c WHERE c.subject_id = <SID> ORDER BY c.id DESC;
```

- [ ] `case_reference` follows `CASE-YYYY-NNNNNN`.
- [ ] `severity` equals the highest contributing signal's severity.
- [ ] `signal_count` and `evidence_count` > 0.
- [ ] The linked signals show `ai_signals.status = 'cased'`.

### T14 — Sub-threshold signals are reported, not silently dropped

**This is Defect A from the screenshots.**

**Setup:** find a student with exactly **one moderate** signal, so no case is warranted.

**Expected UI:** *"1 student is showing an early academic risk signal. It is below the
intervention threshold, so no case was opened — here is what was found."* — with the student
named and the evidence listed.

**Must NOT show:** "No students are currently showing academic risk signals" beside
"Signals found: 1 · Evidence gathered: 4".

**Verify:**
```sql
SELECT COUNT(*) FROM ai_signals WHERE detected_by_run_id = <RUN_ID>;   -- > 0
SELECT COUNT(*) FROM ai_cases  WHERE opened_by_run_id  = <RUN_ID>;     -- 0, legitimately
```
Then confirm the API result contains a `students_monitored` array naming that student.

### T15 — Re-analysis updates, never duplicates

Run the same analysis **three times**.

```sql
SELECT COUNT(*) FROM ai_cases WHERE subject_id = <SID> AND case_type = 'academic_risk' AND status = 'open';
```
Must remain **1**. `CaseBuilder::findOpenCase()` updates the existing case and re-attaches
signals and evidence. Confirm `updated_at` advances while `id` and `case_reference` stay fixed.

---

# STAGE 9 — Explanation

Covered by T10 and T12. One additional check:

- [ ] `ai_explanations.audience = 'teacher'` and the wording contains no API names, table names,
      SQL, agent keys or workflow keys (spec §14).
- [ ] `GET /api/ai/cases/{case}/explanation` returns the same narrative the UI showed.

---

# STAGE 10 — Recommendation

### T16 — Ask for the action

**Type:** `What should the teacher do for <STUDENT>?`

**Expected:** a specific, evidence-linked action — *"Start an academic intervention for
<STUDENT> in Mathematics"* — with its rationale.

### T17 — The recommendation record is complete and gated

```sql
SELECT id, recommendation_reference, case_id, explanation_id, action_type, title,
       confidence, risk_level, is_consequential, requires_approval, verb,
       evidence_ids, eso_binding, governance_passed, workflow_key, workflow_payload,
       status, expires_at
FROM ai_recommendations WHERE subject_id = <SID> ORDER BY id DESC LIMIT 1;
```

- [ ] `status = 'pending_approval'` — never `approved`, never `executed`.
- [ ] `requires_approval = 1` and `is_consequential = 1`.
- [ ] `verb = 'recommend'` (the manifest ceiling held).
- [ ] `governance_passed = 1`. If 0, read `governance_report` — the recommendation must **not**
      be offered for approval.
- [ ] `evidence_ids` is non-empty and every id belongs to `<SID>`.
- [ ] `eso_binding` has all three of `objective`, `strategy`, `outcome`, and
      `outcome.metric_key` is a metric a `MetricResolver` can actually measure
      (`AcademicRiskMetrics::ASSESSMENT_AVERAGE`) — otherwise Stage 13 can never close.
- [ ] `workflow_key = 'k12_academic_intervention'` and `workflow_payload.student_id = <SID>`.
- [ ] `expires_at` is set (default 30 days, `config/ai.php` → `recommendation_ttl_days`).
- [ ] **Nothing has happened yet:** `SELECT COUNT(*) FROM academic_interventions WHERE student_id = <SID>;` unchanged.

### T18 — The recommendation is not generic

Run the analysis for a student whose dominant signal is **attendance** and another whose
dominant signal is **assessment decline**. The two recommendation titles and `eso_binding.objective`
values must differ, and the focus subject must be drawn from
`ai_signals.components.dominant_subject`, not a default.

---

# STAGE 11 — Human approval

### T19 — Approve

```
GET  /api/ai/recommendations/pending
POST /api/ai/recommendations/{id}/approve
```

**Verify:**
```sql
SELECT status FROM ai_recommendations WHERE id = <REC_ID>;          -- 'approved'
SELECT id, recommendation_id, case_id, decision, reason, decided_by,
       decided_by_role, decided_by_name, decided_at, confirmation_token, ip_address
FROM ai_decisions WHERE recommendation_id = <REC_ID>;
```
- [ ] Exactly **one** `ai_decisions` row, `decision = 'approved'`.
- [ ] `decided_by` is the real logged-in user id; `decided_at`, `ip_address`, `user_agent` populated.
- [ ] An `ai_audit_logs` row with `event_type = 'decision.recorded'`.
- [ ] A `workflow_runs` row now exists with `recommendation_id = <REC_ID>` and
      `decision_id` = the decision above. **The decision must precede the run.**

### T20 — Reject, defer, and the bypass attempt

- `POST /api/ai/recommendations/{id}/reject` on a second recommendation →
  `status = 'rejected'`, `ai_decisions.decision = 'rejected'`, and **no** `workflow_runs` row.
- `POST /api/ai/recommendations/{id}/defer` → `status` unchanged from `pending_approval`,
  `ai_decisions.decision = 'deferred'`.
- **Bypass attempt (must fail):** call
  `POST /api/ai/workspace/workflows/k12_academic_intervention/start` directly.
  Expected **422**: *"This process starts when its recommendation is approved, not from here."*
  (`WorkspaceController::startWorkflow()` blocks `trigger_type = 'recommendation_approved'`.)
- **Double-approve (must be idempotent):** approve the same recommendation twice. Expect one
  decision row and one workflow run, not two.
- **Modification:** if approving with edits is supported, the edits land in
  `ai_decisions.modifications` — the original recommendation text stays intact for audit.

---

# STAGE 12 — Action

### T21 — The approved workflow executes its five steps

```sql
SELECT id, run_reference, workflow_key, trigger_type, recommendation_id, decision_id,
       case_id, subject_id, current_step_key, status, started_at, finished_at, error_message
FROM workflow_runs WHERE recommendation_id = <REC_ID>;

SELECT step_key, step_type, status, started_at, finished_at, output, error_message
FROM workflow_steps WHERE run_id = <WF_RUN_ID> ORDER BY id;
```

Expected step sequence (seeded at `2026_08_20_000009_seed_academic_risk_intelligence.php:255-320`):

| # | `step_key` | type | expected outcome |
|---|---|---|---|
| 1 | `generate_activity` | generate | drafts activity content; row in `ai_generation_outputs` |
| 2 | `teacher_approval` | approval | row in `workflow_approvals`; run pauses at `awaiting_approval` |
| 3 | `create_intervention` | action | row in `academic_interventions` |
| 4 | `notify_student` | notify | notification dispatched |
| 5 | `capture_baseline` | measure | row in `ai_outcomes` with `baseline_value` set |

**Verify the run pauses at step 2** — check `status = 'awaiting_approval'` before resolving:
```
GET  /api/ai/approvals/pending
POST /api/ai/approvals/{id}/resolve
```

**Then the intervention record:**
```sql
SELECT id, intervention_reference, student_id, student_name, standard_id, section_id, subject_id,
       case_id, recommendation_id, decision_id, workflow_run_id,
       intervention_type, title, activity_is_generated, generation_output_id,
       assigned_to, assigned_to_name, created_by, start_date, due_date, status, progress_percent
FROM academic_interventions WHERE student_id = <SID> ORDER BY id DESC LIMIT 1;
```
- [ ] `student_id = <SID>`; `standard_id`/`section_id` match `tblstudent_enrollment`.
- [ ] All four provenance columns populated — `case_id`, `recommendation_id`, `decision_id`,
      `workflow_run_id`. **A row with a null `decision_id` means something acted without approval.**
- [ ] `assigned_to` is a real teacher user id.
- [ ] `activity_is_generated = 1` and `generation_output_id` set — and the UI badges that content
      as an AI draft (`GeneratedTag` in `WorkspaceChrome.tsx`).
- [ ] Rows in `academic_intervention_activities` linked to `intervention_id`.
- [ ] **No duplicates:** re-resolve the same approval. Count of `academic_interventions` for
      `<SID>` must not increase.

---

# STAGE 13 — Outcome

### T22 — Baseline, then measure

**Baseline** (written by step 5):
```sql
SELECT id, case_id, recommendation_id, workflow_run_id, subject_id,
       metric_key, metric_label, baseline_value, baseline_at,
       target_value, observed_value, observed_at, delta, status, measure_after
FROM ai_outcomes WHERE subject_id = <SID> ORDER BY id DESC LIMIT 1;
```
- [ ] `baseline_value` is not null and equals the metric computed **before** the intervention:
      cross-check against `AssessmentAverageResolver` / `AttendanceRateResolver` output for `<SID>`.
- [ ] `measure_after` = intervention date + `eso_binding.outcome.horizon_days` (21 or 30).
- [ ] `status = 'pending'`.

**Then change the world:** add real post-intervention data for `<SID>` — new
`lms_online_exam` attempts and `attendance_student` rows dated after `baseline_at`.

**Measure:**
```
POST /api/ai/outcomes/measure-due
```
(`OutcomeTracker::measureDue()` only picks up rows whose `measure_after` has passed — either
wait, or set `measure_after` to a past timestamp for the test.)

**Then ask:** `Did <STUDENT>'s situation improve?`

**Verify:**
- [ ] `observed_value` set, `observed_at` set, `delta = observed_value - baseline_value`.
- [ ] `status` ∈ `{improved, unchanged, worsened, inconclusive}` and matches the sign of `delta`.
- [ ] The answer states both numbers and the linked intervention — before, after, and what was done.
- [ ] The outcome row is linked to the right `recommendation_id` **and** `workflow_run_id`.

---

# STAGE 14 — Learning

### T23 — Effectiveness feeds back

```
GET /api/ai/outcomes/effectiveness
```
(`OutcomeTracker::effectivenessByActionType()`)

**Type:** `Did this intervention work?` then `What can we learn from this intervention?`

**Verify:**
- [ ] The response aggregates real `ai_outcomes` rows — the count matches
      `SELECT COUNT(*) FROM ai_outcomes WHERE status IN ('improved','unchanged','worsened');`
- [ ] Breakdown is by `action_type` (`create_academic_intervention`), with an improvement rate.
- [ ] With **zero** measured outcomes, it says so honestly — it must not report a rate computed
      from nothing.
- [ ] Full audit chain is walkable for one student:
```sql
SELECT event_type, actor_type, actor_label, related_type, related_id, outcome, message, created_at
FROM ai_audit_logs WHERE subject_entity_key = 'student' AND subject_id = <SID> ORDER BY id;
```
Expect, in order: `agent.run` → `signal.detected` → `case.opened` → `explanation.generated`
→ `recommendation.drafted` → `decision.recorded` → `workflow.transition` → intervention created.
**Any missing link means that stage is not auditable.**

---

# FOLLOW-UP CONVERSATION

*The hardest requirement, and the clearest proof the layers are joined.*

### T24 — Filter narrows the previous result

| # | Type | Expected |
|---|---|---|
| 1 | `Which students are at academic risk?` | N students, listed |
| 2 | `Show only Grade 7.` | The **same analysis**, narrowed to Standard 7 — not a new unrelated search |
| 3 | `Why is the first student at risk?` | Explanation for the **first student from turn 2's list** |
| 4 | `What should the teacher do?` | Recommendation for **that same student** |
| 5 | `Approve the recommendation.` | Approval of **that same recommendation** |

**Verify after turn 2:**
```sql
SELECT id, content, resolved_intent, subject_ids FROM ai_conversation_turns ORDER BY id DESC LIMIT 2;
```
- [ ] Turn 2's `resolved_intent.intent` is still `student_risk_analysis` (carried forward).
- [ ] Turn 2's `resolved_intent.filters` now contains `{"standard":"7"}` **merged onto** turn 1's scope.
- [ ] Turn 2's `subject_ids` ⊆ turn 1's `subject_ids`.

**Verify after turn 3:**
- [ ] `resolved_intent.subject_id` equals the first id in turn 2's `subject_ids`.
- [ ] The explanation returned is the stored `ai_explanations` row for that student's case —
      not a freshly generated one.

**Verify after turn 5:**
- [ ] `ai_decisions.recommendation_id` equals the `ai_recommendations.id` surfaced in turn 4.
      **If the wrong recommendation is approved, this is a data-integrity failure, not a UX bug.**

### T25 — Ambiguity is not guessed

After turn 1, type `Approve it.` with **two** pending recommendations in play.
Expected: the assistant asks **which one**. It must not pick one.

### T26 — Context resets cleanly

Click **New chat**, then type `Why is the first student at risk?`
Expected: *"Which student?"* — a new `conversation_reference`, no leaked prior state.

**All of T24–T26 fail today:** there is no `ai_conversations` / `ai_conversation_turns` table
and no state carried between turns. **Phase 2.**

---

# ANALYSE SUGGESTION TESTING

### T27 — Every suggestion runs the full pipeline

For each chip in the Analyse tab, confirm the same chain: chip → structured intent → agent →
workflow → real data → evidence → result.

```sql
SELECT capability, label, action_type, action_ref, sequence
FROM ai_suggestions WHERE module_key = 'students' ORDER BY capability, sequence;
```

- [ ] Each chip produces a new `ai_agent_runs` row (or, for `action_type = 'analyse'`, an
      `ai_generation_requests` row — these are different paths and `AnalyseTab.tsx:66` handles
      them separately; verify the right one fires).
- [ ] No chip returns identical text on two different data sets. Change a filter, re-run,
      confirm the output changes. **Identical output across different data means it is static.**
- [ ] Evidence is displayed for every finding, with a working drill-down.

### T28 — Suggestions reflect what is actually on the page

- [ ] Filter the page to **0 students** → risk-analysis chips are **hidden**, not offered.
- [ ] Filter to students with a **known attendance problem** → an attendance-specific chip appears.
- [ ] Chips derived from live signals match:
```sql
SELECT signal_key, COUNT(*) FROM ai_signals
WHERE subject_id IN (<visible ids>) AND status = 'open' GROUP BY signal_key;
```

**Partially fails today:** suggestions come from the static `ai_suggestions` seed. **Phase 5.**

---

# TEMPLATE ENGINE

### T29 — One template, three cardinalities

Construct three scoped runs and compare the wording:

| Case | Setup | Expected |
|---|---|---|
| Zero | Filter to students with no signals | *"No academic risk signals were found for these 6 students."* — states the population size |
| One | Scope to one at-risk student | *"1 student is showing…"* — **singular** |
| Many | Scope to five | *"5 students are showing…"* — **plural**, ranked by severity |

**Verify:**
- [ ] Template key is identical across all three:
```sql
SELECT template_key, variables FROM ai_generation_requests ORDER BY id DESC LIMIT 3;
```
- [ ] `variables` contains `{{risk_count}}`, `{{total_students}}` etc. filled from row counts —
      no hardcoded numbers.
- [ ] Zero-state names the population it checked. *"No students are at risk"* with no scope is
      not an acceptable empty state.
- [ ] Large set (50+): output is capped and **says** it is capped
      (`WorkspaceController::pageVariables()` sets `is_partial` and `rows_shown` — confirm both
      reach the rendered answer).

### T30 — Templates are data, not code

```sql
SELECT template_key, label, domain, status, output_schema FROM ai_templates WHERE domain = 'k12';
```
- [ ] Edit a template row's text in the database, re-run, and confirm the wording changes with
      **no deploy**. If it does not, the string is hardcoded in PHP or TSX and must move.
- [ ] `output_schema` is enforced — `OutputValidator` should reject a malformed generation and
      the UI should say *"The generated content did not match the expected format"* rather than
      rendering raw JSON (`WorkspaceController::generate()` already returns this — confirm).

---

# FINAL END-TO-END

### T31 — The single acceptance test

One conversation, no page reloads, no manual API calls.

```
1. "Which students are at academic risk?"
2. "Show only Grade 7 students."
3. "Why is the first student at risk?"
4. "What evidence supports that?"
5. "What should the teacher do?"
6. "Approve this recommendation."
7. (after adding post-intervention data + measure-due)
   "Did the intervention improve the student's performance?"
8. "What can we learn from this intervention?"
```

**Single-query proof that all 14 stages executed for one student:**

```sql
SELECT
  (SELECT COUNT(*) FROM ai_agent_runs        WHERE subject_id = <SID> OR JSON_CONTAINS(input->'$.student_ids', CAST(<SID> AS JSON))) AS runs,
  (SELECT COUNT(*) FROM ai_signals           WHERE subject_id = <SID>) AS signals,
  (SELECT COUNT(*) FROM ai_evidence          WHERE subject_id = <SID> AND verified = 1) AS evidence,
  (SELECT COUNT(*) FROM ai_cases             WHERE subject_id = <SID>) AS cases,
  (SELECT COUNT(*) FROM ai_hypotheses h JOIN ai_cases c ON c.id = h.case_id WHERE c.subject_id = <SID>) AS hypotheses,
  (SELECT COUNT(*) FROM ai_explanations x JOIN ai_cases c ON c.id = x.case_id WHERE c.subject_id = <SID>) AS explanations,
  (SELECT COUNT(*) FROM ai_recommendations   WHERE subject_id = <SID>) AS recommendations,
  (SELECT COUNT(*) FROM ai_decisions d JOIN ai_recommendations r ON r.id = d.recommendation_id WHERE r.subject_id = <SID>) AS decisions,
  (SELECT COUNT(*) FROM workflow_runs        WHERE subject_id = <SID>) AS workflow_runs,
  (SELECT COUNT(*) FROM academic_interventions WHERE student_id = <SID>) AS interventions,
  (SELECT COUNT(*) FROM ai_outcomes          WHERE subject_id = <SID> AND observed_value IS NOT NULL) AS measured_outcomes,
  (SELECT COUNT(*) FROM ai_audit_logs        WHERE subject_entity_key = 'student' AND subject_id = <SID>) AS audit_rows;
```

**Every column must be ≥ 1.** A zero identifies exactly which stage broke.

**And the user-facing criterion:** across all eight turns the user saw no API name, no table
name, no SQL, no agent key, no workflow key, no raw JSON — only a conversation.

---

## Sign-off

The Student Profiles module is production-ready when:

1. T1–T31 pass.
2. T12 (evidence traceability) and T20 (approval-bypass attempt) pass **without exception** —
   these two are the safety guarantees; the rest are correctness.
3. T24 passes with the *same* student and the *same* recommendation carried across all five turns.
4. T29/T30 pass with **zero** module-specific strings in the frontend — proving the next module
   needs configuration, not code.

Only then reuse this structure for Attendance, Assessment, and the rest.
