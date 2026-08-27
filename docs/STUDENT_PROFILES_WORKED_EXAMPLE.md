# Student Profiles — the 14 stages, walked end to end with one example

One student. One conversation. Every stage shown with the prompt, what fires inside, the
actual rows, and what the user sees.

**The students are real** — taken from your own chat transcript (`EVAAN RAJESH RAFALIYA`,
Standard 7 C, roll 4, ref 10; `Sonika P Pansuriya`, Standard 7 C; `Darshna R Hirani`,
Standard 7 C). **Column names, JSON keys, class names and file paths are exact** — read from
the code. **Metric values (62%, 41%, …) are illustrative** — yours will differ; what must match
is the shape and the traceability.

Status key: ✓ works today · ◑ partly works · ✗ needs implementation (phase noted)

---

## Setup

Page: `/students/search_student` → **Student Profiles**
Filter the list to **Standard 7 C** so the page shows a small known set. Say it shows 5:

```
EVAAN RAJESH RAFALIYA · Sonika P Pansuriya · Darshna R Hirani · Moncey k Patel · Shivraj . Singh
```

`app/students/search_student/page.tsx:183` registers exactly that set with the assistant:

```jsonc
// what useRegisterPageAiContext sends
{
  "pageTitle": "Student profiles",
  "pageType": "list",
  "filters": { "class": "7 C", "status": "", "house": "" },
  "records": [
    { "id": 10,  "label": "EVAAN RAJESH RAFALIYA", "class": "7 C", "status": "active" },
    { "id": 100234, "label": "Sonika P Pansuriya", "class": "7 C", "status": "active" }
    // … 3 more
  ],
  "recordCount": 5
}
```

The ContextBanner should now read: **Working with Student profiles · 5 shown**.
If it says a different number, stop — the assistant is not seeing your screen.

---

# STAGE 1 — Conversational AI  ✗ (Phase 2)

**You type, in Ask:**
```
Which students are at academic risk?
```

**What must happen inside**

`ChatbotPanel.sendMessage()` → `POST /api/ai/workspace/ask` → `AskOrchestrator`.
The orchestrator's job here is only to decide *where this goes* — it touches no student data.

**Example — the orchestrator's classification**
```json
{
  "intent": "student_risk_analysis",
  "domain": "k12",
  "module": "students",
  "entity": "student",
  "scope": { "source": "page", "subject_ids": [10, 100234, 664, 233, 4021] },
  "filters": { "standard": "7", "division": "C" },
  "confidence": 0.94
}
```

**What you see:** nothing yet — this stage is invisible. It only determines that
`k12_academic_risk` runs rather than a fee lookup or a general chat reply.

**Verify**
```sql
SELECT id, role, content, resolved_intent, agent_key
FROM ai_conversation_turns ORDER BY id DESC LIMIT 1;
```
`resolved_intent.intent = 'student_risk_analysis'`, `agent_key = 'k12_academic_risk'`.

**Today it fails.** Your transcript shows the actual reply:
> *"I don't have the capability to identify which students are at academic risk…"*

That is honest — `lib/ai/adapters/lms-k12/tools.ts` has no risk tool, and Ask never reaches
Laravel. Needs **Phase 2**.

**→ Stage 2** takes the raw sentence and turns it into those fields.

---

# STAGE 2 — Gen AI (understanding)  ✗ (Phase 2)

**You type these four, each in a fresh chat:**
```
Which students are at academic risk?
Who is struggling academically?
Show me students falling behind.
Which kids need extra help?
```

**What must happen inside**

`IntentResolver` runs a deterministic pre-pass; anything it cannot decide goes to
`GenerationService` with template `k12.intent.classify` and a JSON schema enforced by the
existing `OutputValidator`.

**Example — the prompt the model receives**
```
Page: Student profiles (list), module "students", 5 records shown,
filters: class 7 C.
User said: "Who is struggling academically?"
Return JSON: { intent, entity, filters, metrics, confidence }
```

**Critical:** notice what is *not* in that prompt — no student names, no attendance figures,
no scores. The model classifies language. It is never given the data it could invent from.

**Example — output for all four**
```json
{ "intent": "student_risk_analysis", "entity": "student",
  "filters": { "standard": "7", "division": "C" },
  "metrics": ["attendance_rate", "assessment_average", "assignment_completion"],
  "confidence": 0.91 }
```

**Verify**
```sql
SELECT template_key, LEFT(rendered_prompt, 400) FROM ai_generation_requests ORDER BY id DESC LIMIT 4;
```
The prompt must contain the user's words and the page summary — **and no student rows**.

**Negative example:** `What is the capital of France?` → `{"intent":"general"}` → routed to the
old `/api/ai/chat`, and **no** `ai_agent_runs` row is created.

**→ Stage 3** receives `intent = student_risk_analysis`.

---

# STAGE 3 — Agent selection  ◑ (works from Analyse; needs Phase 2 from Ask)

**What happens inside**

`IntentRouter` looks up `ai_intent_routes` → `agent_key = 'k12_academic_risk'` →
`AgentRunner::run()` loads the manifest and pins the tenant.

**Example — the manifest that constrains the run**
```json
{
  "agent_key": "k12_academic_risk",
  "max_verb": "recommend",
  "allowed_signal_keys": ["assessment_decline", "attendance_risk", "missed_assignments"],
  "timeout_seconds": 60
}
```

`max_verb: "recommend"` is the safety line. This agent may detect, explain and propose.
It **cannot** create an intervention — that needs a human decision and a workflow.

**Example — the run row created**
```
ai_agent_runs
  id                     = 41
  run_reference          = AGENT-2026-000041
  agent_key              = k12_academic_risk
  trigger_type           = conversation
  subject_entity_key     = student
  input                  = {"student_ids":[10,100234,664,233,4021],"limit":50}
  status                 = running
  sub_institute_id       = <yours>
```

**Verify** — `input.student_ids` is **exactly your 5 on-screen ids**.

**Today it fails this check.** `lib/intelligence/workspace.ts:297` sends only
`route`/`entity_type`/`entity_id`, so `input.student_ids` is absent, `resolveStudentIds()`
returns `null`, and `StudentScope` sweeps up to **500 institute-wide students** — your
transcript's "The institute has 21 enrolled students" is that same whole-institute reflex.
Needs **Phase 1**.

**→ Stage 4** runs the detection steps.

---

# STAGE 4 — Workflow / detection  ◑ (runs; mis-scoped and mis-reported)

**What happens inside** — `AcademicRiskAgent::run()`, in order:

| # | Step | Class | Reads |
|---|---|---|---|
| 1 | Resolve scope | `StudentScope::students()` | `tblstudent`, `tblstudent_enrollment` |
| 2 | Assessment decline | `AssessmentDeclineDetector` | `lms_online_exam` |
| 3 | Attendance risk | `AttendanceRiskDetector` | `attendance_student` |
| 4 | Missed assignments | `MissedAssignmentDetector` | `homework` |
| 5 | Score + classify | `ThresholdRegistry::classify()` | — |
| 6 | Persist signals | `SignalStore` | `ai_signals` |
| 7 | Persist evidence | `EvidenceStore` | `ai_evidence` |
| 8 | Group by student | `AcademicRiskAgent` | — |
| 9 | Open case | `CaseBuilder` | `ai_cases` |
| 10 | Explain + recommend | `ExplanationBuilder`, `RecommendationDrafter` | `ai_explanations`, `ai_recommendations` |

**Example — attendance scoring for EVAAN (id 10)**
```
absent_days     = 15
recorded_days   = 40
rate            = 0.375
streak          = 3 consecutive
rateComponent   * 0.6  +  streakComponent * 0.4   =  score 0.58
classify(0.58)  → severity "high"
```

**Verify**
```sql
SELECT DISTINCT subject_id FROM ai_signals WHERE detected_by_run_id = 41;
```
Every id must be one of your 5. Any sixth id means scope leaked.

**→ Stage 5** connects those numbers to the person they belong to.

---

# STAGE 5 — Ontology / Knowledge Graph  ✓

**You call:**
```
POST /api/ai/workspace/ontology-views/student-learning
     { "route":"/students/search_student", "entity_type":"student", "entity_id":10 }
```

**Example — what comes back**
```
EVAAN RAJESH RAFALIYA (student:10)
  └─ enrolled_in    → Standard 7 (standard:7)
       └─ has_division → C (division:C)
  └─ has_subject    → Mathematics (subject:14)
       └─ has_assessment → Unit Test 3 (lms_online_exam:8891)
  └─ has_attendance → 40 records (attendance_student)
```

And `student-evidence` walks the other chain:
```
EVAAN RAJESH RAFALIYA (student:10)
  └─ has_signal → attendance_risk (ai_signals:77)
       └─ supported_by → ai_evidence:301, 302, 303, 304
            └─ part_of → ai_cases:12
                 └─ led_to → ai_recommendations:9
```

**Verify — the isolation test that matters most**
```sql
SELECT e.id, e.subject_id, e.kind FROM ai_case_evidence ce
JOIN ai_evidence e ON e.id = ce.evidence_id WHERE ce.case_id = 12;
```
`subject_id` must be **10 on every single row**. One row belonging to Sonika inside EVAAN's
case means the graph is joining wrongly, and every explanation downstream is contaminated.

**→ Stage 6** is where those node values actually came from.

---

# STAGE 6 — Real data  ✓

**The proof that nothing is invented.** Take one evidence row and walk it back.

```sql
SELECT id, kind, subject_id, source_table, source_column, source_id,
       summary, numeric_value, unit, observed_at, verified, is_generated
FROM ai_evidence WHERE id = 303;
```

**Example row**
```
id            = 303
kind          = attendance_absence
subject_id    = 10
source_table  = attendance_student      ← a real business table
source_column = attendance_code
source_id     = 55219                   ← a real row id
summary       = "Marked absent on 2026-08-04."
observed_at   = 2026-08-04
verified      = 1
is_generated  = 0                       ← no model wrote this
```

Then open the source:
```sql
SELECT * FROM attendance_student WHERE id = 55219;
-- must show student_id = 10, attendance_code = 'A', attendance_date = '2026-08-04'
```

**The moving test:** mark one more absence for EVAAN, re-run, and confirm
`ai_signals.components.absence_rate` changes. If the number does not move, it was never read.

**→ Stage 7** turns these rows into the citations behind a claim.

---

# STAGE 7 — Evidence  ✓ written · ✗ not reachable from Ask (Phase 2/4)

**You type:**
```
What evidence supports this?
```

**Example — the four rows behind EVAAN's signal**

| id | kind | summary | numeric | unit |
|---|---|---|---|---|
| 301 | `attendance_rate` | Absent on 15 of 40 recorded days in the last 60 days (37.5%). | 37.50 | percent |
| 302 | `attendance_streak` | Absent for 3 consecutive recorded days, most recently 2026-08-06. | 3 | days |
| 303 | `attendance_absence` | Marked absent on 2026-08-04. | — | — |
| 304 | `attendance_absence` | Marked absent on 2026-08-05. | — | — |

Those `summary` strings are `sprintf()` output from
`AttendanceRiskDetector.php:180-230` — assembled from row values, not written by a model.

**What you should see**
```
Evidence for EVAAN RAJESH RAFALIYA

  Attendance      Absent 15 of 40 recorded days (37.5%)     [view source]
  Pattern         3 consecutive absences to 6 Aug 2026      [view source]
  Records         Absent 4 Aug, 5 Aug, 6 Aug 2026           [view source]
```

**Verify** — every claim in the explanation carries a non-empty `evidence_ids`.
`AcademicRiskAgent::composeClaim()` returns `null` when there is nothing to cite, so an
uncited claim should be **absent entirely**, never shown unsupported.

**→ Stage 8** decides whether this is enough to open a case.

---

# STAGE 8 — Case  ◑ (Phase 1 for the sub-threshold path)

**The rule** (`CaseBuilder::warrantsCase():263`): open a case when a signal is
`high`/`critical`, **or** when ≥2 `moderate` signals coincide on one student.

### Example A — case opens (EVAAN)

`attendance_risk` scored 0.58 → `high` → actionable.

```
ai_cases
  id              = 12
  case_reference  = CASE-2026-000012
  case_type       = academic_risk
  title           = "Attendance risk — EVAAN RAJESH RAFALIYA"
  subject_id      = 10
  severity        = high
  priority_score  = 0.5800
  status          = open
  opened_by_run_id= 41
```
Linked: `ai_case_signals` → 1 row · `ai_case_evidence` → 4 rows ·
`ai_signals.status` flips `open` → `cased`.

### Example B — no case, but still a finding (Sonika)

One `moderate` signal, score 0.31. `warrantsCase()` returns false. **This is legitimate** —
not every signal deserves a teacher's ticket.

**What you must see:**
> *"1 student is showing an early academic risk signal. It is below the intervention
> threshold, so no case was opened. **Sonika P Pansuriya** — absent 9 of 40 recorded days
> (22.5%). Monitoring only."*

**What you see today — the bug in your screenshot:**
> Signals found: 1 · Evidence gathered: 4 · Cases opened: 0
> *"No students are currently showing academic risk signals."*

`buildCaseForStudent()` returns `null` on a null case id, discarding the student and the four
evidence rows it just wrote; `summarize()` then falls through to that literal string. Needs
**Phase 1**.

### Example C — re-run (idempotency)

Run the analysis three times. `CaseBuilder::findOpenCase()` updates case 12 in place.
```sql
SELECT COUNT(*) FROM ai_cases WHERE subject_id = 10 AND case_type='academic_risk' AND status='open';
-- must stay 1; updated_at advances, id and case_reference do not change
```

**→ Stage 9** puts the case into words.

---

# STAGE 9 — Explanation  ◑ (Phase 2/4 to reach it conversationally)

**You type:**
```
Why is EVAAN at risk?
```

**Example — the stored narrative**
```
ai_explanations
  id                = 18
  case_id           = 12
  audience          = teacher
  governance_passed = 1
  narrative         = "EVAAN RAJESH RAFALIYA (Standard 7 C) is showing attendance risk:
                       attendance is 37.5% absent over 40 recorded days, including 3
                       consecutive absences."
  claims            = [{ "claim":"attendance is 37.5% absent over 40 recorded days,
                                  including 3 consecutive absences",
                         "evidence_ids":[301,302,303,304], "confidence":0.72 }]
```

That sentence is `sprintf()` from `AcademicRiskAgent::attendanceClaimText():271` — assembled
from `components`, so it cannot say anything the rows do not.

**The governance case:** if `governance_passed = 0`, the UI must show the refusal, not a
confident sentence. `AnalyseTab.tsx:265` already does this — confirm it still does:
> *"The reason could not be backed by verified evidence, so it is not shown."*

**→ Stage 10** proposes what to do about it.

---

# STAGE 10 — Recommendation  ✓ drafted · ✗ not askable (Phase 2/4)

**You type:**
```
What should the teacher do?
```

**Example — the drafted record**
```
ai_recommendations
  id                       = 9
  recommendation_reference = REC-2026-000009
  case_id                  = 12
  explanation_id           = 18
  action_type              = create_academic_intervention
  title                    = "Start an academic intervention for EVAAN RAJESH RAFALIYA"
  subject_id               = 10
  verb                     = recommend
  risk_level               = medium
  is_consequential         = 1
  requires_approval        = 1
  governance_passed        = 1
  evidence_ids             = [301,302,303,304]
  eso_binding              = {
      "objective": "Return EVAAN RAJESH RAFALIYA to expected academic progress.",
      "strategy":  "Targeted intervention with assigned practice and teacher follow-up.",
      "outcome":   { "metric_key":"assessment_average", "direction":"increase",
                     "horizon_days":30 } }
  workflow_key             = k12_academic_intervention
  workflow_payload         = { "student_id":10, "case_id":12, "severity":"high",
                               "standard_id":7, "section_id":3 }
  status                   = pending_approval        ← NOT approved, NOT executed
  expires_at               = 2026-09-21
```

**What you see**
```
Recommended action
  Start an academic intervention for EVAAN RAJESH RAFALIYA
  Because: 37.5% absence over 40 days, including 3 consecutive absences
  Target: assessment average to increase within 30 days

  ⚠ Nothing has been done yet. This needs your approval.
  [ Approve ]  [ Reject ]  [ View evidence ]
```

**Verify the ceiling held**
```sql
SELECT COUNT(*) FROM academic_interventions WHERE student_id = 10;
-- must still be 0 — the agent proposed, it did not act
```
`eso_binding.outcome.metric_key` must be a metric a `MetricResolver` can actually measure,
or Stage 13 can never close.

**→ Stage 11** is the gate.

---

# STAGE 11 — Human approval  ✓

**You call:**
```
GET  /api/ai/recommendations/pending
POST /api/ai/recommendations/9/approve
```

**Example — the durable decision**
```
ai_decisions
  id                 = 5
  recommendation_id  = 9
  case_id            = 12
  decision           = approved
  decided_by         = 402            ← the real logged-in teacher
  decided_by_role    = teacher
  decided_by_name    = "R. Mehta"
  decided_at         = 2026-08-22 11:04:31
  ip_address         = 10.2.4.77
```
`ai_recommendations.status` → `approved`. An `ai_audit_logs` row appears with
`event_type = 'decision.recorded'`.

**The bypass test — must fail:**
```
POST /api/ai/workspace/workflows/k12_academic_intervention/start
```
Expected **422**: *"This process starts when its recommendation is approved, not from here."*
(`WorkspaceController::startWorkflow()` blocks `trigger_type = recommendation_approved`.)

**Reject example:** approve nothing, reject instead → `status = rejected`,
`ai_decisions.decision = rejected`, and **zero** `workflow_runs` rows.

**→ Stage 12** only now may act.

---

# STAGE 12 — Action  ✓

**What fires:** `WorkflowEngine` starts `k12_academic_intervention`.

**Example — the five steps**
```
workflow_runs id=7  run_reference=WF-2026-000007
  recommendation_id=9   decision_id=5   case_id=12   subject_id=10

  1. generate_activity    generate  ✓ completed  → ai_generation_outputs:22
  2. teacher_approval     approval  ⏸ awaiting   → workflow_approvals:3
  3. create_intervention  action    ✓ completed  → academic_interventions:15
  4. notify_student       notify    ✓ completed
  5. capture_baseline     measure   ✓ completed  → ai_outcomes:6
```
Resolve step 2 via `GET /api/ai/approvals/pending` → `POST /api/ai/approvals/3/resolve`.

**Example — the intervention created**
```
academic_interventions
  id                     = 15
  intervention_reference = INT-2026-000015
  student_id             = 10
  student_name           = "EVAAN RAJESH RAFALIYA"
  standard_id            = 7      section_id = 3
  case_id                = 12
  recommendation_id      = 9
  decision_id            = 5      ← proves a human approved this
  workflow_run_id        = 7
  assigned_to            = 402    assigned_to_name = "R. Mehta"
  activity_is_generated  = 1      generation_output_id = 22
  status                 = active progress_percent = 0
```

**Verify:** all four provenance columns populated. **A row with `decision_id = NULL` means
something acted without approval** — that is the most serious failure this system can have.

**Duplicate test:** re-resolve the same approval. Count must not increase.

**→ Stage 13** measures whether it worked.

---

# STAGE 13 — Outcome  ◑

**Baseline** — written by step 5, before anything changes:
```
ai_outcomes
  id                = 6
  case_id           = 12    recommendation_id = 9    workflow_run_id = 7
  subject_id        = 10
  metric_key        = assessment_average
  baseline_value    = 41.0000
  baseline_at       = 2026-08-22
  measure_after     = 2026-09-21          ← baseline + horizon_days 30
  status            = pending
```

**Then the world changes.** Add real post-intervention rows — new `lms_online_exam` attempts
and `attendance_student` records dated after `baseline_at`.

**Measure:**
```
POST /api/ai/outcomes/measure-due
```
(`OutcomeTracker::measureDue()` only picks up rows whose `measure_after` has passed — set it
to a past timestamp for testing.)

**You type:**
```
Did EVAAN's situation improve?
```

**Example — after measurement**
```
  observed_value = 65.0000
  observed_at    = 2026-09-21
  delta          = 24.0000
  status         = improved
```

**What you see**
```
EVAAN RAJESH RAFALIYA — intervention outcome

  Assessment average     41%  →  65%     +24
  Attendance             62%  →  78%     +16
  Intervention           INT-2026-000015, started 22 Aug
  Result                 Improved
```

**Verify:** `delta = observed_value − baseline_value`, `status` matches the sign, and the row
links to the right `recommendation_id` **and** `workflow_run_id`.

**→ Stage 14** turns one outcome into a pattern.

---

# STAGE 14 — Learning  ✓

**You call:** `GET /api/ai/outcomes/effectiveness`
**You type:** `What can we learn from this intervention?`

**Example**
```json
{
  "by_action_type": [
    { "action_type": "create_academic_intervention",
      "measured": 14, "improved": 9, "unchanged": 3, "worsened": 2,
      "improvement_rate": 0.64,
      "median_delta": 11.5 }
  ]
}
```

**What you see**
> *"Academic interventions have been measured 14 times. 9 improved, 3 were unchanged, 2
> worsened — a 64% improvement rate, median gain 11.5 points."*

**Verify honesty at zero:** with no measured outcomes it must say so, not report a rate
computed from nothing.

**The full audit walk — proof all 14 stages happened for EVAAN:**
```sql
SELECT event_type, actor_type, related_type, related_id, outcome, created_at
FROM ai_audit_logs
WHERE subject_entity_key = 'student' AND subject_id = 10 ORDER BY id;
```
Expected order:
```
agent.run.started → signal.detected → case.opened → explanation.generated
→ recommendation.drafted → decision.recorded → workflow.transition ×5 → agent.run.completed
```
A missing link means that stage is not auditable.

---

# The follow-up conversation — all stages in one thread

This is the real acceptance test, because it proves the stages are *joined*, not merely present.

| # | You type | What must carry forward | Verify |
|---|---|---|---|
| 1 | `Which students are at academic risk?` | — | `subject_ids` = your 5 |
| 2 | `Show only Standard 7 C.` | intent from turn 1 | `filters` merged; `subject_ids` ⊆ turn 1 |
| 3 | `Why is the first student at risk?` | turn 2's list | `subject_id` = first id of turn 2 |
| 4 | `What evidence supports that?` | turn 3's student | evidence ids all `subject_id = 10` |
| 5 | `What should the teacher do?` | turn 3's case | `case_id = 12` |
| 6 | `Approve this recommendation.` | turn 5's rec | `ai_decisions.recommendation_id = 9` |

**Example of turn 2 done right**
```jsonc
// turn 1
{ "intent":"student_risk_analysis", "subject_ids":[10,100234,664,233,4021] }
// turn 2 — merged, not restarted
{ "intent":"student_risk_analysis",              // carried
  "filters":{ "standard":"7", "division":"C" },  // added
  "subject_ids":[10,100234,4021] }               // narrowed
```

**Ambiguity example — must ask, not guess.** With two pending recommendations, type
`Approve it.` → *"Which one — EVAAN's intervention, or Sonika's?"*

**Reset example.** Click New chat, type `Why is the first student at risk?` →
*"Which student?"* with a new `conversation_reference` and no leaked state.

**All of this fails today.** There is no `ai_conversations` table and no state between turns —
which is why your transcript looped: each click was a brand-new, contextless question. Needs
**Phase 2**.

---

# The one query that proves all 14 stages ran

```sql
SET @sid = 10;
SELECT
 (SELECT COUNT(*) FROM ai_agent_runs WHERE JSON_CONTAINS(input->'$.student_ids', CAST(@sid AS JSON))) AS s3_runs,
 (SELECT COUNT(*) FROM ai_signals        WHERE subject_id=@sid)                    AS s4_signals,
 (SELECT COUNT(*) FROM ai_evidence       WHERE subject_id=@sid AND verified=1)     AS s6_evidence,
 (SELECT COUNT(*) FROM ai_cases          WHERE subject_id=@sid)                    AS s8_cases,
 (SELECT COUNT(*) FROM ai_explanations x JOIN ai_cases c ON c.id=x.case_id WHERE c.subject_id=@sid) AS s9_explanations,
 (SELECT COUNT(*) FROM ai_recommendations WHERE subject_id=@sid)                   AS s10_recs,
 (SELECT COUNT(*) FROM ai_decisions d JOIN ai_recommendations r ON r.id=d.recommendation_id WHERE r.subject_id=@sid) AS s11_decisions,
 (SELECT COUNT(*) FROM workflow_runs     WHERE subject_id=@sid)                    AS s12_workflows,
 (SELECT COUNT(*) FROM academic_interventions WHERE student_id=@sid)               AS s12_actions,
 (SELECT COUNT(*) FROM ai_outcomes       WHERE subject_id=@sid AND observed_value IS NOT NULL) AS s13_outcomes,
 (SELECT COUNT(*) FROM ai_audit_logs     WHERE subject_entity_key='student' AND subject_id=@sid) AS s14_audit;
```

**Every column ≥ 1.** A zero names the stage that broke.

---

# Where you stand today

| Stage | Example above | Today | Phase |
|---|---|---|---|
| 1 Conversational AI | intent classification | ✗ refuses | 2 |
| 2 Gen AI | 4 phrasings → 1 intent | ✗ regex only | 2 |
| 3 Agent selection | manifest, `student_ids` | ◑ wrong scope | 1 |
| 4 Detection | scoring EVAAN 0.58 | ◑ runs institute-wide | 1 |
| 5 Ontology / KG | two relationship walks | ✓ | — |
| 6 Real data | evidence 303 → `attendance_student` 55219 | ✓ | — |
| 7 Evidence | 4 rows with sources | ✓ / ✗ from Ask | 2/4 |
| 8 Case | A opens · B monitors · C idempotent | ◑ B is discarded | 1 |
| 9 Explanation | grounded narrative | ✓ / ✗ from Ask | 2/4 |
| 10 Recommendation | REC-2026-000009 | ✓ / ✗ from Ask | 2/4 |
| 11 Approval | decision 5, bypass 422 | ✓ | — |
| 12 Action | 5 steps, intervention 15 | ✓ | — |
| 13 Outcome | 41 → 65, +24 | ◑ manual measure | — |
| 14 Learning | 64% improvement rate | ✓ | — |
| Follow-up | 6 turns, one student | ✗ | 2 |

**Stages 5, 6, 11, 12, 14 you can test right now.** Stages 3, 4, 8 need Phase 1 — four files,
no new tables. Stages 1, 2, 7, 9, 10 and the follow-up thread need Phase 2.
