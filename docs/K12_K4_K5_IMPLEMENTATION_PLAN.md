# K-12 K4 + K5 Implementation Plan

Detailed implementation plan for the two features selected in
`KG_CG_BUSINESS_FUNCTIONALITY_ROADMAP.md` as K-12's first KG/CG business
features: K4 (teacher-facing `CoherenceRiskAgent` intervention signal) and K5
(render the 3 already-fetched Coherence Map health fields).

## Context

Both are near-zero-backend-risk: K4 reuses a fully-built, already-deployed
generic AI-agent framework end to end; K5 finishes a render that's already
half-done. This plan is deliberately scoped to exactly that — no new backend
logic for either.

**Resolved decision:** the existing generic approve/reject mechanism for
AI-agent recommendations (`ai_recommendations` table,
`RecommendationController::approve/reject`) is restricted to administrators
only (`AdministratorCheck`/`assertMayDecide()`), not teacher staff — a direct
mismatch with "teacher approves/dismisses." **K4 ships view-only for teachers
in this pass.** Teachers see the flagged students/root-blockers; the
approve/dismiss action itself is not exposed in this UI (it remains exactly
where it already lives, admin-side, unchanged). The shared `AdministratorCheck`
gate — used by every other recommendation type in the AI framework — stays
completely untouched.

## K4 — Teacher-facing intervention signal

### Backend — zero new code, 100% reuse

| Piece | Reused as-is |
|---|---|
| Agent | `App\Domain\PAL\CoherenceRisk\CoherenceRiskAgent` (`app/Domain/PAL/CoherenceRisk/CoherenceRiskAgent.php`), wraps `CompoundingRootBlockerDetector` |
| Route | `POST /api/ai/agents/pal_coherence_risk/run` (`routes/ai.php:322`, `AgentController::run`, `app/Http/Controllers/AI/AgentController.php:68-127`) |
| Auth/tenant scoping | `McpAuth` + `McpContextHydrator`/`McpContextResolver` (already on the route group) |
| Roster (for scoping which students to check) | `StudentActivityReportController::classRoster` via `fetchClassRoster` in `app/pal/learning-intelligence/data/teacher-insights.ts`, server-scoped by `requireClassScope()` |

**Request/response contract:**
- Request: `POST /api/ai/agents/pal_coherence_risk/run`, body
  `{ "student_ids": [<roster student ids>], "limit": <roster.length> }`.
  `subject_id`/`standard_id`/`division_id` are accepted by `AgentController`
  but **ignored by this agent** — the only correct way to scope this to "the
  teacher's current class" is explicit `student_ids` from the
  already-permission-scoped roster.
- Response: `{ success, message, data: { students_flagged, signals_detected,
  cases: [ { case_id, student_id, student_name, placement, severity,
  priority_score, signal: { components: { distinct_chapters,
  chapter_component, root_blocker_count, severity_component,
  weak_concepts_checked } }, explanation: { narrative, governance_passed,
  reason_refused }, recommendation: { id, status, requires_approval, title,
  governance_passed, reason_refused } } ], cohort, detector_coverage,
  confidence }, errors }`. A `rejected` governance status returns HTTP 403.
- **Hard rule:** this endpoint must never be called from this UI without an
  explicit `student_ids` array — omitting it sweeps every student in the
  tenant (`CompoundingRootBlockerDetector.php:102`).

### Frontend

**New file:** `app/pal/learning-intelligence/teacher-insights/_components/InterventionSignalsCard.tsx`
— a `SectionCard` (same wrapper `ActionItemsTimeline.tsx`/`MisconceptionBoard.tsx`
use), inserted into the existing stack in `teacher-insights/page.tsx` right
after the `MisconceptionBoard` render (~line 226) — additive only.

**Reused:** `runAgent()` from `lib/intelligence/client.ts:415-434` (same
function `AiInsightsPanel.tsx` calls for a different agent) — confirm its
exact `(context, agentKey, params)` signature against `AiInsightsPanel.tsx`'s
call site before wiring.

**Data flow:**
1. Teacher picks a class via the existing `ClassScopePicker` — unchanged.
2. `page.tsx` fetches the roster via `fetchClassRoster` — unchanged.
3. New: once the roster loads, `InterventionSignalsCard` calls
   `runAgent(context, 'pal_coherence_risk', { student_ids: rosterStudentIds, limit: rosterStudentIds.length })`.
4. Render each case: student name, severity badge (same `Badge`/bucket-color
   convention as `ActionItemsTimeline.tsx:20-26`), root-blocker concept
   name(s) and chapters spanned (`signal.components.distinct_chapters`/
   `root_blocker_count`), `explanation.narrative` as body text.
5. "View" action reuses the existing `goToStudent` navigation →
   `/pal/teacher/journey/${studentId}` — no new drill-in modal.
6. No approve/dismiss control. `recommendation.status` shown as a small muted
   label ("Pending administrator review").

### States
- **Loading:** skeleton matching `ActionItemsTimeline`/`MisconceptionBoard`'s
  existing treatment.
- **Empty:** "No compounding root-blocker concerns detected for this class" —
  never an error.
- **Error:** hide the card or show a muted "Couldn't load intervention signals
  right now" — never blocks the rest of the page.
- **Success:** the case list as above.

### Test cases / acceptance criteria
1. Class with zero flagged students → empty state, no console errors.
2. Class with ≥1 genuinely flagged student → card shows student, severity,
   root-blocker concept(s), ≥2 distinct chapters, narrative text.
3. Simulated 403/network failure → card degrades gracefully; rest of the page
   unaffected.
4. `student_ids` is always present and roster-scoped in every call this card
   makes — verified via request inspection, never a full-tenant sweep.
5. No approve/dismiss control anywhere in this card.
6. `ClassScopePicker`, "Jump to one student", `IsometricClassBoard`,
   `ActionItemsTimeline`, `MisconceptionBoard` unchanged.

### Risks / dependencies
- `runAgent()`'s exact `context` param must be confirmed against
  `AiInsightsPanel.tsx` before writing code.
- Correctness depends entirely on always passing the right `student_ids`.
- Depends on `CoherenceMapRepository::rootBlockers()` having real projected
  coverage for the viewed students — where it doesn't, expect the empty
  state, not an error.

## K5 — Coherence Map health fields

### Confirms no backend duplication
`CoherenceMapRepository::health()` and `GET /api/pal/coherence/health` are
completely unchanged — frontend-only. `fetchCoherenceHealth()`
(`app/pal/new/data/coherence-map.ts`) already fetches and type-maps
`withoutContent`, `withoutQuestions`, `fitToUse` into `CoherenceHealth`
(`coherence-map.ts:203-215`, mapped at `:386-401`).

### Exact change
In `app/pal/new/coherence-map/page.tsx`, the stat-chip row (`:176-204`)
currently renders `map.stats.{concepts,requires,crossLinks,roots,maxDepth,
acyclic,isolated,draftEdges}` plus `health.cycles.length`. Add, immediately
after line 204, in the same chip-row bar:
- Two plain count chips (styled like "entry points"/"deepest chain"):
  `{health.withoutContent} chapters without content`,
  `{health.withoutQuestions} chapters without questions`.
- One conditional amber/rose pill (styled like `isolated`/`draftEdges`),
  shown only when `health.fitToUse === false`.

No new component, no new state, no new fetch.

### Test cases / acceptance criteria
1. Standard/subject with real gaps → new chips show values matching
   `/api/pal/coherence/health` directly.
2. Standard/subject with no gaps → no new chips/pills render.
3. Existing `cycles`/`isolated`/`draftEdges` chips pixel-identical to before.
4. Existing error/loading handling for `health` fetch is unchanged.

## Restrictions carried into this implementation
No PAL learning-flow change, no automatic execution of agent recommendations,
no Neo4j/graph schema or pipeline changes, no approving the 124 draft TEACHES
edges, existing permissions preserved exactly (including the admin-only
approval gate), no commits/deploys, no changes to unrelated modules.
