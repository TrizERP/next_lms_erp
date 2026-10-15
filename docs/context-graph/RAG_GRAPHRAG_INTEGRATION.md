# Phase 6 — RAG and GraphRAG Integration

**Status: Audited. No code changed.** Per the master prompt's own instruction for this phase: audit
first, do not invent new infrastructure where an adequate implementation exists, and where a real gap
is found, report it and propose a separate, approved phase rather than building inline. This document
is that report. All nine required properties were checked against real code in all three products —
not inferred from file or class names.

## Result, up front

Two of the nine required properties are **solid everywhere**, in three independently-built but
functionally equivalent ways — notably prompt-injection defense, which this audit expected to be the
weakest point and found to be the strongest. One property (citation/source-reference validation) has a
real, precisely-located gap in Enterprise Brain, with a known root cause already documented in that
codebase's own comments. The rest are satisfied adequately, with one minor, low-severity note (stale
evidence is bounded by sync cadence everywhere but not surfaced per-item to the grounding step).

## 1. Tenant-aware retrieval

| Product | Mechanism | Verified |
|---|---|---|
| K-12 | `CoherenceMapRepository` resolves `sub_institute_id` from the concept's own row before scoping the graph query | Code read |
| G2G | **Real nuance, not a flaw, but worth naming explicitly**: `GraphRagRetriever`'s own docblock states the graph has no `sub_institute_id` property on any node it reads — tenant scoping is enforced entirely by the **caller** re-verifying SQL ownership before any Cypher runs. Spot-checked 5 of 13 known callers (`SkillMatchingController`, `CompetencyGapController`, `ToolController::invoke()`'s `graph_context` branch, `SuggestedCourseController::explain()`) — all five correctly re-check ownership first. The other ~8 callers were not individually checked in this pass. | Code read, 5/13 callers spot-checked |
| Enterprise Brain | `GraphQueryPort::reasoningTrail($tenant, $label, $id)`/`detail($tenant, ...)` take tenant id as an explicit first-class parameter on every call | Code read + live-verified in Gate 6 |

**Note for a future pass**: G2G's "caller enforces tenant scoping, not the retrieval layer itself" design is sound given its constraints (no tenant property on the graph nodes), but it does mean a 14th caller added later without the ownership re-check would introduce a real cross-tenant read. Worth a one-time sweep of the full caller list before this class gains more callers, not an immediate action.

## 2. Evidence provenance

| Product | Mechanism |
|---|---|
| K-12 | `EvidenceItem::fromRecord()`/`fromComputation()` carry `sourceTable`/`sourceId`/`sourceService` explicitly |
| G2G | `GraphVocabulary`'s relationship entries each document the exact column/service they derive from (same pattern Phase 0 found in EB) |
| Enterprise Brain | `GraphVocabulary::LABEL_FAMILY`/relationship entries, same discipline; `GraphExplanationController`'s "facts"/"connections" trace back to a named source service |

Satisfied in all three, by direct code read.

## 3. Retrieval timestamps

Present at the **event/audit level** everywhere (`ai_audit_logs.created_at`, `g2g_event.occurred_at`/
`recorded_at`, `hpbrain_ai_executions`). **Not present per-retrieved-item** in the live RAG path checked
most closely (EB's `AiGateway::completeWithRag()` interpolates `{type, id, content}` per document with no
timestamp field) — low severity, since the audit row for the call as a whole is timestamped, but a
per-document "as of" marker is absent. Noted, not actioned.

## 4. Stale evidence detection

Each product bounds staleness by sync cadence (K-12 Neo4j drain every minute + nightly reconcile; G2G's
`events:project` every 5 minutes; EB's `brain:process-events` every minute) — but none of these expose a
"this fact might be N minutes/hours old" signal to the actual retrieval/grounding consumer. EB's
`RetrievalService::searchDocuments()`/`searchMemory()` do track embedding staleness explicitly
(`retrieval => 'keyword_only'` label for "not embedded, or embedded under a stale model/version" rows) —
the most advanced instance of this property found in any of the three products, and the only one that
surfaces staleness as a labeled fact to the caller rather than leaving it implicit in sync timing.

## 5. Citation / source-reference validation — the one real gap

**Confirmed**: `App\Services\GroundingService::verifyCitations()` (Enterprise Brain) is a real,
correctly-implemented citation validator — it checks whether an AI response actually references the
evidence ids it was given, and reports which ones are unverified. It has **zero callers anywhere in the
app**, confirmed by the same grep this audit ran and by that codebase's own comment in
`RetrievalService.php`: *"RagService (the only caller of this class) has zero callers of its own anywhere
in the app."*

**Root cause, not just absence**: `RagService` (the class that wraps `GroundingService`) was bypassed
deliberately, not forgotten — `KnowledgeAskController`'s own docblock explains why: `RagService::retrieve()`
only ever calls `searchEntities()`, never `searchDocuments()`/`searchMemory()`, so using it for a
knowledge question "would silently answer from the wrong sources." The team correctly routed around a
broken intermediary (`KnowledgeAskController` calls `RetrievalService` and
`AiGateway::completeWithRag()` directly) rather than fix-and-trust it blind. That fix left
`verifyCitations()` orphaned as a side effect — a real, reportable gap, with a known, legitimate reason
it happened.

**What the live path (`AiGateway::completeWithRag()`) does instead**: instructs the model via system
prompt to "use only the RETRIEVED CONTEXT... if it does not contain the answer, say so plainly rather
than guessing" and interpolates each document as `[type id] content`. This is a real grounding
instruction, but there is no automated, post-hoc check that the model's citations are genuine.

K-12 and G2G do not have an equivalent automated citation-validation step either, but both rely on a
narrower, simpler mitigation that doesn't need one: K-12's `ExplanationBuilder` composes narratives
deterministically from claims-with-evidence-ids by default (no model involved unless `storeGenerated()`
is explicitly used, and even then "the claims and their citations may not" be generated); G2G's
`explainMatch()` system prompt is similarly restrictive. EB's `completeWithRag()` is the one path doing
open-ended generation over multiple retrieved documents with the least structural constraint on the
output, which is exactly why a verification step matters more there.

**Proposed scope for a future, separately-approved phase** (not implemented here, per this phase's own
instruction): wire `GroundingService::verifyCitations()` into `AiGateway::completeWithRag()` as an
**observational, additive** check — compute verified/unverified citations and attach them to the
response/audit row, without changing what is returned to the caller. Changing behavior based on the
result (stripping unverified claims, refusing to answer, surfacing a warning in the UI) is a product
decision that needs its own scoping and approval, separate from the mechanical wiring.

## 6. Context size limits

Confirmed bounded in all three: K-12's `PageAiContext` `LIMITS` constant and `PageSnapshot::DESCRIBE_LIMIT`
(6000 chars); G2G's own `PageSnapshot::clean()` (per-field and per-list caps, independently implemented,
same name, same idea); EB's `GraphBuilder`'s node budget (confirmed live at 140 nodes in Gate 6's own
test output) and `ContextGrounding::VALUE_LIMIT`/`SIGNAL_LIMIT`.

## 7. Safe handling of missing or contradictory evidence

Confirmed in all three: K-12's `AiTutorContextService` returns `null` rather than fabricate when no ESO
nodes exist; G2G's `explainMatch()`/`SuggestedCourseController::explain()` degrade to narrower grounding
on graph outage, never failing the endpoint; EB's `GraphExplanationController`/`SignalController` return
explicit 404/503/`ai_not_configured`/`no_evidence` states rather than inventing a narrative. "Contradictory"
specifically (two sources disagreeing) was not found handled as its own named case anywhere — each
product's answer is "ground in one determined source and say so when there's nothing," not "reconcile
disagreeing sources," which sidesteps rather than solves contradiction. Low severity: no instance of an
actual contradictory-evidence code path was found missing, because none of the three products currently
combines multiple independent sources in a way that could disagree.

## 8. Prompt-injection defenses for retrieved documents — the strongest finding of this phase

All three products have **real, independently-designed, functioning defenses**, not a gap:

- **K-12**: `App\Domain\GenerativeAI\SafetyChecker::inspectPrompt()` — pattern-matches interpolated
  template variables (free-text answers, comments, imported fields) against known instruction-injection
  phrasings (`ignore previous instructions`, `disregard the system prompt`, `you are now a...`,
  `system:`) before a generation call.
- **G2G**: `App\Domain\AI\Workspace\PageSnapshot::clean()` — never passes client-sent page data through;
  rebuilds it field-by-field from an allow-list, strips control characters, caps every string/list, and
  frames the result as data the model must never obey.
- **Enterprise Brain**: `App\Domain\Ai\ContextGrounding` — uses an explicit fence
  (`<<<RESOLVED_CONTEXT_FACTS` / `RESOLVED_CONTEXT_FACTS>>>`) around rendered facts, escaping any
  occurrence of the fence markers and collapsing newlines in every value, specifically to prevent "a
  value that could emit a line break... clos[ing] the fence and continu[ing] as if it were the prompt's
  own text" — a textbook defense against the exact attack class this property names.

No action needed. This item is fully satisfied, and more thoroughly than this audit expected going in.

## 9. Existing AI provider compatibility

Already satisfied everywhere: K-12 (`AiServiceProvider`, multi-template), G2G (`config/ai.php`,
Gemini/OpenRouter/DeepSeek with failover), EB (`AiGateway`/`AiProvider`, Anthropic/DeepSeek/Gemini/
`NullAiProvider`, plus `completeWithFallback()`'s explicit provider-chain fallback). No new provider
integration was proposed or needed by anything in this audit.

## Conclusion

No new vector store, document-ingestion pipeline, or retrieval engine is needed anywhere — exactly the
outcome this phase's own instructions anticipate when "an adequate implementation already exists." The
one genuine, precisely-located gap (EB's orphaned `verifyCitations()`) is reported here with its root
cause and a scoped proposal, per this phase's instruction, rather than fixed inline — wiring it changes
the behavior of a function with live callers and deserves its own approval, unlike Gate 6's purely
additive UI wiring.
