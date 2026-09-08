# Lifecycle cutover — test matrix, staged rollout, rollback point

`AI_LIFECYCLE_ENABLED` chooses between two pipelines that answer the same endpoint:
`AskService` (`ask_service_v1`) and `LifecycleAskService` (`lifecycle_v2`). This document
records what running both on a fixed question set actually produced, what has to be true
before the flag is deleted, and the one thing that can still be rolled back.

Run it yourself:

```bash
php artisan ai:compare-pipelines --set=read        --institute=9001 --role=staff
php artisan ai:compare-pipelines --set=governance  --institute=9001 --role=staff
php artisan ai:compare-pipelines --set=all --institute=9001,1 --role=admin,staff,student \
    --json=storage/app/cutover.json --fail-on-diff
```

Nothing persists by default: each case runs inside a transaction that is rolled back,
because a risk scan opens cases and drafts recommendations and the matrix would write
every one of them twice. Audit rows are read inside the transaction, before the rollback.

---

## 1. What the first run found

Institute 9001, role `staff`, 2026-09-04. **13 of 13 cases differ.** That is not itself
alarming — the lifecycle is meant to answer better — but three of the differences decide
whether this ships.

### Improvements — the cutover's case for itself

| Question | `ask_service_v1` | `lifecycle_v2` |
| --- | --- | --- |
| *What is 12 times 7?* | "I did not understand that" | **84**, tagged as general knowledge, not institute records |
| *hi* | "I did not understand that" | Greets |
| *આજે કેટલા વિદ્યાર્થીઓ ગેરહાજર છે?* | "I did not understand that" | Replies in Gujarati |
| *Show pending admission enquiries* | "I did not understand that" | **12 enquiries**, and says "10 of 12 shown" rather than implying it showed all |
| *Show me Rohan Sharma's attendance* | "I did not understand that" | "Where it stopped — Planning: no registered intent matched and the Attendance module has no tool for it" |

The last row is the pattern worth noticing: the lifecycle's failures explain themselves.
"I did not understand that" and "here is the stage where I stopped and why" are both
refusals, but only one of them can be acted on.

### 🔴 Regression — blocks the flag flip

**"What has the system learned?"**

- `ask_service_v1`: *"Nothing has been measured yet, so there is nothing learned yet,"*
  followed by how the loop closes. Correct, and useful.
- `lifecycle_v2`: *"I need to know which student or case you mean."* Wrong.

The intent classifies correctly — `learning_effectiveness` is asserted in
`QuestionRoutingTest` and passes — so the loss is downstream of classification: the
question resolves to a module with no effectiveness reader and falls through to the
student/case prompt. A question the old pipeline answers and the new one does not is the
definition of a regression, and this one is silent.

### 🟠 One fallback is swallowing every reason — resolve before flipping

This looked like three unrelated differences. It is one defect with three symptoms: when
the lifecycle cannot produce an answer, it composes *"I need to know which student or case
you mean"* regardless of why, and the real reason survives only in the trace.

| Question | Actual reason | What `lifecycle_v2` says | What `ask_service_v1` says |
| --- | --- | --- | --- |
| *Approve recommendation 42.* | The record belongs to another tenant | "I need to know which student or case you mean" | "I could not find that recommendation" |
| *Show pending admission enquiries* (as `student`) | **Permission refusal** — Laravel MCP refused the tool call | "I need to know which student or case you mean" | — |
| *What has the system learned?* | No effectiveness reader on the resolved module | "I need to know which student or case you mean" | Answers correctly |

The permission case is the one to look at hardest. The governance model **worked** — the
trace reads `Laravel MCP refused 1 tool call` and `Real Data — no source records were
read`, and the student got no enquiry data. But the sentence the student reads suggests
they simply phrased it badly, and invites them to try again against data they will never
be allowed to see. A refusal that does not say it is a refusal is worse than a blunt one:
it converts a correct security decision into a usability puzzle, and it will generate
support tickets that look like bugs.

Fix the fallback to report the stage that stopped the turn — the trace already knows, and
the *"Show me Rohan Sharma's attendance"* case proves the lifecycle can say it well
("Where it stopped — Planning: no registered intent matched…"). That phrasing exists; it
just is not reached on these paths.

Also here: several cases write **two** audit rows on the lifecycle where the legacy path
writes **one**. Decide whether that is intended before the flag flips, because audit
volume is the kind of thing that is very hard to reinterpret after the fact.

### 🟡 Shared defect — not a cutover risk, but real

**"Why is Tara Mehta at risk?"** — *both* pipelines answer *"I need to know which student
you mean."* Tara Mehta is a real student in 9001 (`283268`) with an open case (`76`), and
the name is in the sentence. Named-student resolution on a fresh thread is broken in both
paths, so it neither blocks nor is fixed by the cutover. It is, separately, the single
most damaging bug in the product: the flagship follow-up question does not work unless
the user has already run a scan in the same thread.

### Wording drift — accept or fix, but decide

The legacy path had three distinct prompts ("I need to know which *student*", "...before I
can show its *evidence*", "...before I can *recommend* anything"). The lifecycle collapses
all three into one generic sentence. Less specific, and cheap to restore.

---

## 2. The matrix

The comparison runs the product of three axes. Roles and tenants are not cosmetic: the
role decides what the agent and the workflow permit, and the tenant decides what is
visible at all, so a difference that appears only for `student` in tenant 1 is exactly the
difference that reaches production unnoticed.

| Axis | Values | Why |
| --- | --- | --- |
| Question | `read` (10), `governance` (3), `write` (1) | Parity, domain reads, refusals, and one consequential turn |
| Role | `admin`, `staff`, `student` | Permission-shaped answers must not change under the cutover |
| Tenant | `9001` (seeded), `1` (real data shape) | Scope isolation, and behaviour when tables are populated differently |

`write` is separated because it is slow and consequential: the scan took **57 s (v1)** and
**61 s (v2)**. Note that both exceed the 60 s `max_execution_time` that kills this scan
over HTTP — the CLI has no such limit, which is why it completes here and not in a browser.

---

## 3. Gates

The flag flips when all four are true. The first is currently false.

1. **No regression.** Every question the legacy path answers, the lifecycle answers at
   least as well. Today: fails on *"What has the system learned?"*
2. **Refusals say why they refused.** A turn stopped by scope, by permission, or by a
   missing reader must say which — not the generic student/case prompt. The trace already
   distinguishes them; the composed answer must too. Today: fails on three questions,
   including a permission refusal shown to a `student`.
3. **Audit is explained.** Any difference in rows written is intended and documented.
4. **The matrix runs green in CI.** `--fail-on-diff` against an allowlist of differences
   that are improvements rather than regressions.

---

## 4. Phases, and the rollback point

**Phase 1 — shim in place (now).** `AskPipeline` holds both services and chooses per call.
`AI_LIFECYCLE_ENABLED=false` returns the entire estate to `ask_service_v1` in one
environment variable, with no deploy. This is the rollback point, and it is the **only**
one left.

> The frontend fallback is already gone. The plan in
> `frontend-lifecycle-gap-analysis.md` ordered the work as *measure first* (step 1),
> *delete the TypeScript layer* (step 5), *retire `/api/ai/chat`* (step 6). Steps 5 and 6
> shipped first: the TS brain, the duplicated tool layer and the chat route have all been
> deleted. So the browser can no longer fall back to anything — `AI_LIFECYCLE_ENABLED` is
> the whole safety net, and it only reaches as far as Laravel.

**Phase 2 — flag on, shim retained.** Flip to `true` in staging, then production. Both
services stay in the container. Watch the audit log and the four gates. Any regression is
a one-variable revert.

**Phase 3 — the shim is redundant.** Reached when the flag has been on in production
through a full academic cycle of the flows that matter (a scan, an approval, a workflow
run to completion, a measured outcome) with no revert. Only then:

- delete `AskService`
- delete `AskPipeline` and inject `LifecycleAskService` directly — its own docblock says
  it is "scaffolding for a migration, and it is written to be deleted"
- delete `AI_LIFECYCLE_ENABLED` from `config/ai.php` and both `.env.example` files
- delete `ai:compare-pipelines` and this document's §1

**Do not delete the flag before Phase 3.** Deleting it while the comparison still fails
would remove the last revert path in exchange for tidiness, at the point of maximum
uncertainty. The shim costs one branch in one class; the rollback it buys cannot be
rebuilt in an incident.

---

## 5. What this replaces

Step 1 of the order of work in `frontend-lifecycle-gap-analysis.md` — *"turn the flag on
in staging, run a fixed question set through both paths and diff. This is the cheap step
that replaces the rest of this document with measurements."* The measurements are above.
