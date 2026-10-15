# K-12 Implementation (Gate 3 — first pilot)

**Scope:** Suggested Content recommendation recording, per `CONTEXT_LAYER_DESIGN.md` §8.
**Status:** Implemented and verified against a live database.

## What this pilot is, precisely

The master prompt's Phase 3 Use Case 1/2 ("Context-aware PAL recommendation", "Explainable Suggested
Content") asks that a recommendation be recorded with its evidence references and a concise explanation.
Tracing the actual student-facing "Suggested Content" screen (the modal in `lms_k12`'s
`app/pal/page.tsx`, calling `fetchPedagogySuggestedContent` →
`App\Services\PAL\Integration\PedagogySuggestedContentService::getSuggestions()`) found it already
computes real evidence — `root_cause` (prerequisite blockers via
`CoherenceMapRepository::rootBlockers()`, Neo4j-backed) and `graph_content` (concept-targeted content via
`CoherenceMapRepository::contentFor()`) per weak concept — but records none of it. Every call was pure
request/response; nothing durable remembered why a suggestion was made.

**Important correction made during this work**: this is a *different* system from the PAL V4 "Pedagogy
Engine" page (`api\PAL\PedagogyEngineController`, `app/pal/pedagogy-engine/`) that `PHASE_0_AUDIT.md`
originally cited as the explainability surface. That page is explicitly "read-only reference data with
no learner or tenant scope" (its own controller docblock) — a rule-set QA tool, not a per-student
recommendation. `PedagogySuggestedContentService` is the actual learner-facing, per-student path, and is
what this pilot targets.

## What was reused vs. what's new

| Reused, unchanged | New |
|---|---|
| `PedagogySuggestedContentService`'s entire scoring/selection/categorisation logic | One new class: `PedagogySuggestedContentContextRecorder` |
| `CoherenceMapRepository::rootBlockers()`/`contentFor()` | One new call site: a single method call at the end of `getSuggestions()`, before its `return` |
| `App\Domain\AI\Support\AiAuditLogger` and its existing `RECOMMENDATION_DRAFTED` constant/`ai_audit_logs` table | — |
| The API response shape returned to the frontend — byte-for-byte identical to before | — |

**Deliberately not used**: `RecommendationDrafter`/`CaseBuilder`/`EvidenceStore`/`DecisionGate` (the
`App\Domain\AI` signal/case pipeline found during this work, see the audit correction note). That
pipeline models *consequential* actions requiring a named teacher's approval before anything happens
(`RecommendationDrafter`'s own docblock: "what lands in the table is a proposal in `pending_approval`,
waiting on a person"; `CreateAcademicInterventionAction` only runs after that approval is in
`ai_decisions`). Routing a routine, real-time, low-stakes content suggestion through it would either
stall the feature behind an approval queue nobody intends to staff, or flood teachers with one approval
request per student per page view. `AiAuditLogger` — pure observability, no approval semantics — is the
correctly-scoped reuse target, per the master prompt's own instruction to implement only the minimum
required capability.

## Files changed

- **New**: `app/Services/PAL/Integration/PedagogySuggestedContentContextRecorder.php` — takes the
  already-computed weak-concept list and categorised content, extracts concept/content **ids only** (not
  full records) from `root_cause`/`graph_content`, and writes one `AiAuditLogger::RECOMMENDATION_DRAFTED`
  event per call via the existing audit mechanism. Wrapped in try/catch so an audit-write failure can
  never break the live suggested-content response — the same defensive stance
  `PedagogySuggestedContentService` already takes around its own graph calls.
- **Modified**: `app/Services/PAL/Integration/PedagogySuggestedContentService.php` — added one
  constructor dependency (optional, `app()`-resolved default, matching the file's existing pattern for
  `CoherenceMapRepository`) and one call to the recorder immediately before the method's final `return`.
  No existing line of scoring, selection, or categorisation logic was touched; the returned array is
  unchanged.

## Database changes

One migration run, user-approved before execution: `2026_09_29_100600_add_duration_and_knowledge_graph_to_ai_audit_logs.php`
— adds two nullable columns (`duration_ms unsignedInteger`, `knowledge_graph_used boolean`) to the
existing `ai_audit_logs` table. Already written and reviewed in the repo; this pilot did not author it,
only ran it, after confirming via `php artisan migrate:status` that it was genuinely pending (not
applied-but-unmarked) and reading its `up()`/`down()` to confirm it was narrow, additive, and reversible.
This fixes audit persistence for **every** existing caller of `AiAuditLogger::record()` in the codebase,
not just this pilot's new one — see evidence below.

No other schema, API, or graph change was made.

## Tests executed and actual results

Static checks:
- `php -l` on both the new and modified file — no syntax errors.

Runtime checks (via `php artisan tinker`, against the live `vivek_erp` database this app actually
connects to — there is no isolated test database in this environment):

1. **Before the migration**: called the recorder directly and called `AiAuditLogger::record()` directly.
   Both returned cleanly with no row written. Traced to the actual cause via a diagnostic script
   (`Schema::getColumnListing('ai_audit_logs')` showed no `duration_ms`/`knowledge_graph_used` columns;
   the Laravel log showed the real underlying exception — `SQLSTATE[42S22]: Column not found: 1054
   Unknown column 'duration_ms'` — being caught and silently degraded to a log-only fallback inside
   `AiAuditLogger::record()`'s own try/catch). This is what the correction in `PHASE_0_AUDIT.md`
   documents.
2. Ran the single pending migration (`--path=`, scoped to that one file, `--force` since this
   environment has no interactive confirmation prompt available to tinker/CLI automation — the migration
   itself was already reviewed before running). Result: `DONE` in 815.22ms.
3. **After the migration**: re-ran the recorder directly — row persisted
   (`event_type=recommendation.drafted`, `knowledge_graph_used=true` correctly set because the test
   payload's `root_cause`/`graph_content` were non-empty, `payload` carrying concept/content **ids**, not
   full records).
4. Invoked the actual production call site, `PedagogySuggestedContentService::getSuggestions()`, with a
   synthetic learner id, for a real chapter (8677). Result: `status=1`, the pre-existing message text
   unchanged ("Pedagogy profile loaded, but no mapped content was found for this chapter" — this
   particular chapter/student/subject combination had no mapped content, which is the service's own
   correct, pre-existing behavior, not something this change affected), **and** a matching
   `ai_audit_logs` row was written in the same call. This confirms the wiring is live at the real call
   site, not only in an isolated unit test of the new class.
5. All test rows (sentinel `subject_id` values in the 999999990s range, clearly not a real student)
   were deleted after each verification step. No real student, case, or recommendation data was touched
   at any point.

## Security / tenant isolation

`sub_institute_id` is threaded through unchanged from the existing `$context` array
`PedagogySuggestedContentService` already receives (session-derived, as before) into the recorder and
then into `AiAuditLogger`'s own `sub_institute_id` column — no new resolution path, no new trust
boundary. The recorder never receives or stores a student's name, answers, or full content bodies — only
numeric ids and mastery percentages already present in the existing response.

## Outstanding / deferred

- The optional `context_version`/`context_ref` columns proposed in `SHARED_CONTEXT_MODEL.md` §7.1 were
  **not** added — not needed for this pilot; reconstruction of "what led to this suggestion" already
  works by re-reading the `payload` JSON's concept/content ids.
- No frontend change was made. The suggested-content API response is unchanged; this pilot is purely a
  server-side audit-trail addition. Whether/how to surface "this was recorded" in the UI (e.g., an
  explanation panel reading `ai_audit_logs` back) is a separate, not-yet-scoped follow-on.
- The `ai_audit_logs` column gap almost certainly affected every other existing caller
  (`RecommendationDrafter`, `EvidenceStore`, `CaseBuilder`, `SignalStore`, `ExplanationBuilder`,
  `AgentRunner`, etc.) for however long that migration sat pending. This pilot's fix is retroactive for
  future calls only — any historical event from before 2026-10-14 that silently fell back to the log
  file was not backfilled into the table, and doing so was out of scope here.
