# Context Graph — Security and Governance (Phase 7 / Gate 7)

This is organized against the master prompt's own 14-item Phase 7 checklist, verified item by item
against real code, not assumed. Earlier Gate 7 work covered items 1, 2, 5, 7, 11, 13, 14 and the
cross-product boundary specifically; this pass adds direct verification of items 3, 4, 6, 8, 9, 10, 12,
which had not been individually checked before.

## 1. Tenant and organization isolation

| Product | Mechanism | Evidence |
|---|---|---|
| K-12 | `sub_institute_id` resolved server-side per route (`RequireApiJwt`, `PalApiAuth`, etc.) | Code read, Phase 0 |
| G2G | `AiContextHydrator`, server-side, "controllers read the organisation from that scope and never from request input" | Code read, Gate 5 |
| Enterprise Brain | `EnsureTenantScope`/`AuthenticateJwt`, rejects any route-param tenant mismatching the token's tenant | Code read, Phase 0; live-verified, Gate 6 |

## 2. Authentication and authorization before context retrieval

Confirmed in all three — every context-assembly/retrieval entry point checked in Gates 3/5/6 sits behind
an auth middleware that resolves identity before any business logic runs. No new entry point introduced
by this work bypasses this.

## 3. Field-level filtering for sensitive data

Partially confirmed, not as a dedicated universal mechanism. What exists: K-12's `PageAiContext`/
`PageSnapshot` and G2G's own `PageSnapshot::clean()` both rebuild context from an **allow-list** of known
fields rather than passing arbitrary data through — which is a form of field-level filtering, but by
inclusion (only named fields survive) rather than by an explicit sensitive-field blocklist (e.g., there
is no dedicated "never include salary/health/SSN" rule found in any of the three products' context
assembly). `AiAuditLogger::redact()` (K-12) does blocklist specific keys (`password`, `token`, `api_key`,
`aadhar`, bank account fields) but only for **audit log rows**, not for what is assembled into a context
or sent to an AI provider in the first place. **Honest gap**: allow-list field selection is a reasonable
practical substitute, but a named, reusable "sensitive business field" filter does not exist as its own
component in any product.

## 4. Purpose-limited context assembly

Not confirmed as a distinct, explicit mechanism in any product — no context-assembly service takes a
"purpose" parameter that limits which fields it resolves. What exists in practice is narrower in scope
by construction: K-12's `AiContextService` resolves capabilities from the matched module/page type
(so a different page type gets a different capability set, which is purpose-limiting in effect, not by
an explicit purpose flag); EB's `GraphQueryPort::detail()`/`reasoningTrail()` are narrowly scoped to one
entity at a time rather than a general-purpose query. **Honest gap**: this is satisfied in spirit (no
product hands over a general, unscoped data dump) but not via a named, auditable "purpose" check.

## 5. Minimal data exposure to AI providers

Confirmed in all three — covered in depth in Phase 6 (`RAG_GRAPHRAG_INTEGRATION.md` §6) and this gate's
earlier pass: `PageAiContext`/`PageSnapshot` caps, `GraphBuilder`'s node budget, `ContextGrounding`'s
value/signal limits.

## 6. Protection against cross-tenant graph traversal

| Product | Enforcement point | Verified |
|---|---|---|
| K-12 | **Structural, with a documented near-miss.** `CoherenceMapRepository`'s own docblock: relying on `(standard_id, subject_id)` alone to imply one institute "is how a cross-tenant leak gets shipped." Every method takes an optional `$tenant` and, when absent, resolves and filters on it anyway — a real, deliberate fix to a real risk the team identified themselves. | Code read |
| G2G | **Caller-enforced, not structural** — confirmed in Phase 6: `GraphRagRetriever`'s graph nodes carry no `sub_institute_id` property at all, so the retrieval layer itself cannot filter by tenant. Every known caller re-checks SQL ownership first (5 of 13 callers directly verified); this is sound given the constraint, but it means tenant safety here depends on every future caller remembering to do the same check — there is nothing in the retrieval layer that would catch one that doesn't. | Code read, 5/13 callers |
| Enterprise Brain | **Structural, enforced at every query.** Spot-checked `GraphProjection::ensureNode()`/`detail()`/`expand()` across `Organization`, `Department`, `Person`, `Signal`, `Case`, `Hypothesis`, `Recommendation`, `Capability` — every single case filters by `tenant_id`/`$tenant` at the SQL level before returning a node or edge. The `Organization` case additionally refuses outright when the requested id doesn't match the resolved tenant. | Code read, systematic across all checked labels |

**This gate's one actionable recommendation**: G2G's `GraphRagRetriever` would benefit from the same
structural fix K-12 already made to `CoherenceMapRepository` for the identical risk class — but this is
a code change, not something to make unilaterally here; flagged for a future, separately-approved pass.

## 7. No unauthorized cross-product data sharing

Confirmed — see Gate 7's original pass: zero cross-product references in any file this initiative
touched; the two pre-existing cross-product mechanisms (K-12→G2G's blocked client, EB's human-gated
Person Profile resolver) were reviewed and left exactly as found.

## 8. Retention and deletion rules for context events

| Product | What exists |
|---|---|
| K-12 | `PurgeDocumentTrash.php` exists for documents. **No retention/purge mechanism found for `ai_audit_logs`, `ai_decisions`, `ai_recommendations`, or `ai_evidence`** — these tables grow unboundedly. |
| G2G | `PurgeIdmsTrash.php`/`DocumentsPurgeTrash.php` exist for documents. **No retention/purge mechanism found for `g2g_event`, `g2g_audit_log`, or G2G's own `ai_audit_logs`.** |
| Enterprise Brain | **A real, comprehensive mechanism exists — but it is tenant-offboarding, not time-based.** `App\Domain\Tenancy\TenantPurgeService`: permanent, transactional, tenant-scoped deletion of everything a tenant owns, deliberately separate from the existing soft-delete "archive" (which the class's own docblock documents as having left 12,592 Brain rows behind for a genuinely deleted organization before this existed). No ongoing, time-based retention policy (e.g., "delete audit rows older than N days") was found for any Context-Graph-relevant table in any product. |

**Honest gap, all three products**: no product has a time-based retention policy for the event/decision/
audit tables this initiative relies on. EB's tenant-purge mechanism is a real, strong answer to "what
happens when an organization leaves," which is not nothing — but it does not address ordinary data
minimization over time for an active tenant. Worth a future, explicitly-scoped phase; not something to
invent unilaterally here given it touches data-lifecycle policy, which is a business decision.

## 9. Safe handling of retries and duplicate events

| Product | Mechanism | Verified |
|---|---|---|
| K-12 | Neo4j writes are idempotent `MERGE`s (confirmed, Phase 0); no idempotency key on `ai_audit_logs`/`ai_decisions`/`ai_recommendations` specifically | Code read |
| G2G | `g2g_event` has a **real, enforced** unique constraint `uq_event_idem` on `(sub_institute_id, idempotency_key)` — a duplicate event with the same key is rejected at the database level, not just logically ignored | Migration read directly |
| Enterprise Brain | `App\Domain\Events\EventPublisher`/`AlreadyPublished`: publishing an event whose idempotency key is already in `hpbrain_event_store` throws a dedicated exception that unwinds the whole transaction — "the domain write inside it must not commit when the event it belongs to was already published" | Code read |

G2G and EB both have real, structural, enforced idempotency on their primary event stores. K-12 relies
on Neo4j's own MERGE semantics for graph writes, which is idempotent for that specific write path, but
has no equivalent guard on the AI decision/recommendation tables — a narrower, lower-severity version of
the same gap named in item 8.

**This initiative's own new code**: K-12's new recorder and EB's new "Explain this" button both create a
new row/call on every invocation, with no dedup. This was a deliberate, correct choice, not an oversight:
each invocation (a suggestion actually being shown; a user actually clicking "explain") is a genuinely
new, legitimate event, not a retry of the same one — the same convention every other on-demand narrative
button in these codebases already uses (e.g., EB's pre-existing executive-summary narrative).

## 10. Audit logs for sensitive context access

**Decisions, recommendations, and explanations are audited everywhere** — this was verified repeatedly
across Gates 3/5/6. **Routine context-assembly reads are not logged as access events** in any product
(e.g., `AiContextService::resolve()` being called when a page loads does not itself write an audit row).
This is a reasonable engineering trade-off against log volume — logging every context resolution would
multiply the audit tables' write rate by every page view — but it means "who looked at this sensitive
record, when, without necessarily deciding anything" is not independently reconstructable from the audit
trail in any of the three products. Worth naming explicitly rather than leaving implicit.

## 11. Redaction of credentials, tokens and sensitive values from logs

Confirmed, K-12: `AiAuditLogger::redact()` blocklists password/token/api_key/aadhar/bank-account-shaped
keys before any payload is written to `ai_audit_logs`. Not independently re-verified for G2G's/EB's own
equivalent audit loggers in this pass (both follow the same documented "shaped deliberately like
`ai_audit_logs`" convention per their own comments, but the redaction function itself was not re-read for
each).

## 12. No use of unauthorized historical context

Checked specifically: EB's `OrganizationalMemoryController::decisionLineage()` (the ADR-005
"decisions→outcomes→learnings" reconstruction) resolves its graph half through
`GraphQueryPort::reasoningTrail()`, which — per item 6 above — is tenant-filtered at every query. So
reconstructing a past decision's lineage re-applies the current caller's tenant scope on every node
touched; it cannot surface a fact the current caller isn't authorized for, even if the original decision
predates a permission change. This matches the design principle `SHARED_CONTEXT_MODEL.md` §3 states
("reconstruction re-resolves references under the *current* authorization check") — confirmed true in
EB's real implementation, not just the design doc. Not independently re-verified for K-12's `DecisionGate::historyFor()`
or G2G's equivalent in this pass.

## 13. No ungrounded decision explanations

Confirmed everywhere — K-12's `ExplanationBuilder` (claims assembled from evidence, narrative composed
from claims, generation held to the same grounding rules), G2G's `explainMatch()` system prompt ("never
invent a competency name, rating, or requirement not present in the input"), EB's `GraphExplanationController`/
`SignalController::explainReasoning()` (same discipline, live-verified in Gate 6 with a real AI call that
cited only retrieved facts).

## 14. No private model chain-of-thought storage

Confirmed — no product stores a model's internal reasoning. What's stored everywhere is the
evidence-grounded explanation itself (the thing shown to a user), never a hidden reasoning trace.

## Summary of honest gaps from this pass (not fixed here — reporting, per this phase's own instruction)

1. No dedicated, reusable sensitive-field filter distinct from allow-list field selection (item 3).
2. No explicit "purpose" parameter limiting context assembly, though scope is narrow in practice (item 4).
3. G2G's `GraphRagRetriever` tenant safety is caller-enforced, not structural — same risk class K-12
   already fixed for `CoherenceMapRepository` (item 6).
4. No time-based retention/purge policy for any product's AI decision/event/audit tables — EB's
   tenant-offboarding purge is real but addresses a different problem (item 8).
5. K-12's AI decision/recommendation tables have no idempotency key, unlike G2G's and EB's event stores
   (item 9).
6. Routine context-assembly reads are not logged as access events in any product (item 10).

None of these were introduced by this initiative — all predate it, found by applying the master
prompt's own checklist directly to real code.
