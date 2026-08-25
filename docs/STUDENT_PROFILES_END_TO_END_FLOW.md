# Student Profiles — the complete end-to-end flow

This is the reference module. Every stage of the architecture runs here, reports what it
did, and can be verified independently. Once this one is understood, the same shape
applies to every other module.

The problem this document solves: the pipeline already worked, but nothing joined it to a
question a person types, and nothing reported which layer had done what. From the outside
that is indistinguishable from a system that does nothing. So the missing piece was never
the intelligence — it was **the conversational entry point and the trace**.

---

## 1. What was added

| Piece | File | What it does |
|---|---|---|
| Intent understanding | [IntentClassifier.php](app/Domain/AI/Conversation/IntentClassifier.php) | Natural language → one of nine intents + slots |
| Conversation memory | [ConversationStore.php](app/Domain/AI/Conversation/ConversationStore.php) | Carries the student/case/recommendation between turns |
| The orchestrator | [AskService.php](app/Domain/AI/Conversation/AskService.php) | Runs the real services, records every stage |
| The trace | [FlowTrace.php](app/Domain/AI/Conversation/FlowTrace.php) | The fifteen stages, each with status, component, rows, verification |
| Answer shaping | [AnswerComposer.php](app/Domain/AI/Conversation/AnswerComposer.php) | Structured answers — records, evidence, steps, before/after |
| HTTP surface | [AskController.php](app/Http/Controllers/AI/AskController.php) | `POST /api/ai/ask` and friends |
| The console | [journey.blade.php](resources/views/ai/journey.blade.php) | Chat on the left, live fifteen-stage trace on the right |
| CLI verifier | [AiJourneyCommand.php](app/Console/Commands/AiJourneyCommand.php) | `php artisan ai:journey` — the whole journey, no browser needed |
| Storage | [migration](database/migrations/2026_08_22_000001_create_ai_conversation_tables.php) | `ai_conversations`, `ai_conversation_turns` (question, answer, trace) |

**Nothing was reimplemented.** AskService orchestrates; it never writes a case, an
approval or an intervention by hand. Risk detection is still `AcademicRiskAgent` via
`AgentRunner`, approval is still `DecisionGate`, the intervention is still created by the
workflow. Every governance rule that applied before applies to a question asked in English.

---

## 2. The journey, as it actually runs

Real output from this codebase, against the live database. Student and numbers are real.

```
User: "Which students are at academic risk?"
        │
        ▼
Conversational AI ........ thread CONV-2026-000005 opened, turn 1
        │                  → ai_conversations #5
        ▼
Gen AI (understanding) ... intent = student_risk_scan, confidence 100%
        │                  matched: "academic risk", "at risk", "which…students…risk"
        ▼
Agent .................... Academic Risk Agent selected — the only agent that owns
        │                  the academic_risk case type. Verb ceiling: `recommend`.
        │                  → ai_agent_runs #12
        ▼
Ontology / KG ............ "GREEVA RAFALIYA" resolved to student #97926;
        │                  2 relations walked (declared in ontology_relationships)
        ▼
Real data ................ 3 detectors queried live records → 1 signal
        │                  → ai_signals #2 (academic_assessment_decline, score 0.39)
        ▼
Evidence ................. 4 rows stored, all verified, each naming its source
        │                  → ai_evidence #20, #5, #6, #7
        │                  #5 ← lms_online_exam #67080 — "Scored 0% (0 correct, 5 incorrect)"
        ▼
Case ..................... 1 case opened — one per student, not one per metric
        │                  → ai_cases #1 (CASE-2026-000001, severity high)
        ▼
Explain .................. composed from cited evidence, passed governance
        │                  → ai_explanations #2
        │                  "assessment performance declined from 16.7% to 13.3%
        │                   across the last 3 assessments"
        ▼
Template Engine .......... NOT YET. Generation happens inside the workflow, after
        │                  approval. Nothing is generated before a human agrees.
        ▼
Recommendation ........... "Start an academic intervention for GREEVA RAFALIYA"
        │                  → ai_recommendations #1, status pending_approval
        │                  bound workflow: k12_academic_intervention
        ▼
Human Approval ........... ⏸ WAITING. Nothing downstream runs until a teacher decides.
        │
        │   ── teacher clicks Approve ──
        ▼
Human Approval ........... recorded — decision #2 by user #1 (admin)
        │                  → ai_decisions #2
        ▼
Workflow Engine .......... k12_academic_intervention started as run #2
        │                  → workflow_runs #2
        ▼
Template Engine .......... NOW. "k12.intervention_activity" rendered at the
        │                  generate_activity step. Output stored unverified —
        │                  it may be shown to a human, never cited as evidence.
        ▼
Human Approval (2nd gate)  workflow parks at teacher_approval — confirm the drafted
        │                  activities. A different gate from the one above.
        ▼
Action ................... academic intervention #1 created on the student record
        │                  → academic_interventions #1
        │                  CreateAcademicInterventionAction re-checks the approval
        │                  before writing. Reached any other way, it refuses.
        ▼
Outcome .................. baseline captured: Assessment average = 13.33%
        │                  → ai_outcomes #1, measure due 2026-09-21
        │                  metric + horizon come from the recommendation's ESO binding,
        │                  so "success" was defined before the action ran
        ▼
Learning ................. after measurement: create_academic_intervention
                           → improved 0, unchanged 2, measured 2, rate 0%
                           This is the feedback signal for the next recommendation.
```

---

## 3. The stage table

| # | Stage | What happens | Where it lives | What the user sees | How you test it |
|---|---|---|---|---|---|
| 1 | Conversational AI | Question accepted onto a thread; memory loaded | `ConversationStore` | Their message, thread ref | Ask anything; check `ai_conversation_turns` |
| 2 | Gen AI | Intent + slots decided | `IntentClassifier` | "Understood as …" + confidence | `POST /api/ai/ask/interpret` — rephrase, expect same intent |
| 3 | Agent | Right agent selected and run under its verb ceiling | `AgentRunner` → `AcademicRiskAgent` | Nothing internal; the result | `GET /api/ai/agent-runs` |
| 4 | Ontology / KG | Entity resolved, relations walked | `EntityResolver`, `GraphQueryService` | Related class/attendance/assessment | `GET /api/ai/knowledge-graph/relations/student` |
| 5 | Real data | Detectors query live records | 3 detectors in `Domain/K12/AcademicRisk` | The actual numbers | `GET /api/ai/signals` |
| 6 | Evidence | Observations stored with provenance | `EvidenceStore` | Evidence list, each with source table | `GET /api/ai/cases/{id}/evidence` |
| 7 | Case | One case per student | `CaseBuilder` | Case in the list | `GET /api/ai/cases` |
| 8 | Explain | Claims composed, each citing evidence | `ExplanationBuilder` + `GovernanceValidator` | "Why this was raised" | `GET /api/ai/cases/{id}/explanation` |
| 9 | Template Engine | Intervention text generated — **inside the workflow only** | `TemplateRegistry` → `GenerationService` | Draft activities before confirming | `GET /api/ai/templates`; `ai_generation_requests` |
| 10 | Recommendation | Action drafted, not taken | `RecommendationDrafter` | The proposed action + ESO binding | `GET /api/ai/recommendations/pending` |
| 11 | Human Approval | Decision recorded | `DecisionGate` | Approve / Reject | Approve one, then reject another |
| 12 | Workflow Engine | Process executed step by step | `WorkflowEngine` | Step-by-step progress | `GET /api/ai/workflow-runs/{id}` |
| 13 | Action | The record actually changes | `CreateAcademicInterventionAction` | Intervention on the student | `select * from academic_interventions` |
| 14 | Outcome | Baseline, then measurement | `OutcomeTracker` | Before / after | `POST /api/ai/outcomes/measure-due` |
| 15 | Learning | Effectiveness per action type | `OutcomeTracker::effectivenessByActionType` | "improved 3, unchanged 1" | `GET /api/ai/outcomes/effectiveness` |

Every stage reports its own row in the console, including the ones that did **not** run —
with the reason. "Workflow Engine — waiting on the approval above" is the fact that makes
the architecture legible; silence would not.

---

## 4. The scripted conversation

Ask these in order. Each one moves the journey one stage forward.

### Q1 — "Which students are at academic risk?"

```
1 student is currently showing academic risk signals.

BREAKDOWN
  High risk    GREEVA RAFALIYA

STUDENTS
  • GREEVA RAFALIYA  [High risk]
    academic_assessment_decline
    Case: #1 · Class: 7 · Priority: 0.39

EVIDENCE BEHIND THE HIGHEST-PRIORITY CASE
  • Assessment average moved from 16.7% to 13.3%  = -3.33 percentage_points
    #20 · assessment_trend · AssessmentDeclineDetector · verified
  • Scored 0% (0 correct, 5 incorrect) on an assessment attempt  = 0 percent
    #5 · assessment_score · lms_online_exam #67080 · verified

RECOMMENDED ACTION
  Start an academic intervention for GREEVA RAFALIYA — waiting for your approval.

[Approve]  [Reject]
```

Stages 1–10 run; 11 shows ⏸ waiting; 12–14 show "waiting on the human decision — this is
the gate, not a gap"; 15 reports what has been measured so far.

### Q2 — "Why is Student A at risk?"

`Student A` is a **position in the previous answer's list**, resolved from thread memory —
the trace shows it under `resolved_from_conversation`. A real name works too
("Why is Greeva at risk?"), as does "why is she at risk" once a student is in memory.

Returns the stored explanation with each claim and the evidence ids it cites. The agent
does **not** re-run: re-running would re-analyse, not explain.

### Q3 — "What evidence supports this?"

Every row, its kind, its **source table and id**, and whether it is verified or generated.
Open `lms_online_exam #67080` yourself and you will find the same number.

### Q4 — "What should the teacher do?"

The drafted recommendation, its ESO binding (objective, strategy, metric, direction,
30-day horizon), its governance status, and exactly what approving will cause.

### Q5 — "Approve the recommendation."

Writes `ai_decisions`, starts `k12_academic_intervention`, renders the template, registers
the outcome baseline, and parks at the second gate.

### Q6 — "Approve the workflow step."

Resumes the run. Creates the intervention, notifies the student, captures the baseline.
**This is the turn where Action stops being "not reached".**

### Q7 — "What happened after approval?" · Q8 — "Did the intervention work?" · Q9 — "What has the system learned?"

Process steps with real timestamps; before/after against the baseline; effectiveness per
action type.

**Also test the negative paths** — they are the point of the gate:

- "Reject the recommendation." → stages 9, 12, 13, 14 all report *"the recommendation was
  rejected; nothing downstream runs — that is the point of the gate"*.
- "What is the weather today?" → intent `unknown`, **nothing runs**, and the trace says
  why: guessing would mean acting on the wrong record.

---

## 5. How to run it

### The console (recommended)

```
/ai/journey
```

Chat on the left; the fifteen stages on the right. Click any stage to see the class that
implements it, the rows it wrote, and the exact API call or SQL to check it yourself.

### The command line — no browser, no token

```bash
php artisan ai:journey --institute=1                      # the scan
php artisan ai:journey --institute=1 --full               # + the follow-up questions
php artisan ai:journey --institute=1 --full --approve     # WRITES: decision + workflow
php artisan ai:journey --institute=1 --ask="Why is Greeva at risk?"
php artisan ai:journey --institute=1 --json               # raw trace
```

### The API

```http
POST /api/ai/ask
{ "question": "Which students are at academic risk?", "conversation_id": null }
```

Returns `{ answer, trace, ladder, intent, links, duration_ms }`. `trace` is the fifteen
stages; `ladder` is the one-line-per-stage form.

Other routes: `POST /api/ai/ask/interpret` (classification only, writes nothing),
`GET /api/ai/ask/intents`, `GET /api/ai/conversations/{id}` (replay a whole thread with
its traces).

---

## 6. If the scan finds nothing

This is the single most confusing outcome, and it has **two different causes**. The system
now distinguishes them.

**No signal fired.** Nothing crossed its trigger. Reported plainly.

**Signals fired but no case opened.** Real work with a real result. The answer lists each
signal, its score, and how far short it fell:

```
No case was opened, but 1 signal did fire — none of them strong enough on its own.

  • GREEVA RAFALIYA  [Moderate]
    academic assessment decline
    Score: 0.387 · Needs: 0.500 to open a case alone · Short by: 0.113
```

The rule: **a case opens when one signal reaches `high` (≥ 0.5), or when the same student
has two or more signals at `moderate` or above.** Corroboration matters — one middling
number is not yet a case.

### Tuning it for your data

Bands are a per-school row, not code. There is no deploy:

```sql
-- Lower the bar for one signal, for one school
INSERT INTO ai_signal_definitions
  (signal_key, sub_institute_id, thresholds, /* …copy the rest from the global row… */)
SELECT signal_key, 1,
       '{"bands":{"critical":0.75,"high":0.35,"moderate":0.2},"trigger":0.3}',
       /* … */
FROM ai_signal_definitions
WHERE signal_key = 'academic_assessment_decline' AND sub_institute_id IS NULL;
```

The override must carry `sub_institute_id` — a global row is the fallback and is **not**
used for band overrides.

> **Note on this database.** Such an override is currently present for institute 1 /
> `academic_assessment_decline` (high 0.5 → 0.35). It was added so the full journey could
> be walked end to end on real records. Remove it with:
> ```sql
> DELETE FROM ai_signal_definitions
> WHERE signal_key = 'academic_assessment_decline' AND sub_institute_id = 1;
> ```

---

## 7. Reading the trace

Each stage carries:

- **status** — `ran` · `pending` (waiting on a human) · `blocked` (refused) · `skipped`
  (reached, nothing to do) · `not_reached` (with the reason)
- **component** — the class that genuinely does the work, so you can open it
- **surface** — where in the product the result appears
- **records** — `{table, ids}`; a stage that claims to have run names its rows
- **verify** — the API call and/or SQL that confirms it independently

The distinction between `skipped` and `not_reached` is deliberate. *Skipped* means the
stage was reached and correctly had nothing to do ("no agent run needed — the case already
exists"). *Not reached* means the journey stopped earlier, and says where.

---

## 8. Verification checklist

| Check | Command | Expect |
|---|---|---|
| Rephrasing is stable | `POST /ask/interpret` with "who is struggling" | `student_risk_scan` |
| Nothing runs on nonsense | ask "what is the weather today" | intent `unknown`, all stages `not_reached` |
| Agent really ran | `select * from ai_agent_runs order by id desc limit 1` | counters match the trace |
| Evidence is real | open the `source_table` / `source_id` from any evidence row | same number |
| Claims are grounded | `GET /cases/{id}/explanation` | every claim has `evidence_ids` |
| Approval is required | `select status from ai_recommendations` | `pending_approval` until you decide |
| Rejection stops everything | reject, then `select * from workflow_runs` | no new run |
| Action re-checks approval | `select * from academic_interventions` | row exists only after both gates |
| Outcome is like-for-like | `select baseline_value, observed_value from ai_outcomes` | same resolver both times |
| Loop closes | `GET /outcomes/effectiveness` | counts per action type |
| Tenant isolation | ask with a token for another institute | no rows from institute 1 |
