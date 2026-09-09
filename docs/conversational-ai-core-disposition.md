# conversational-ai-core — disposition manifest

**Status:** CONTRACT. Nothing in this area starts without a line here.
**Date:** 2026-08-31 · **Scope:** all of `app/Domain/AI/` — 17 directories, **72 files**
**`lib/` means:** a new framework-free top-level `lib/` in `next_lms_erp`, on its own PSR-4 root. It does not exist yet.

## Dispositions

| Code | Meaning |
|---|---|
| **LIB** | Framework-free behaviour. Moves to `lib/` as-is. |
| **LIB-BLOCKED** | Framework-free itself, but depends on an APP class. Cannot move until that dependency is behind an interface. The named seam must land first. |
| **SHAPE** | Pure type — enum, interface, or DTO. The vocabulary crossing the `app/` ↔ `lib/` boundary. Kept stable; behaviour must never be added. |
| **APP** | Illuminate/Eloquent-coupled. **Stays in `app/`.** Needs an interface seam before it could ever move. |
| **RETIRE** | Dead. **Currently empty — see below.** |

### Two things to know before using this

**1. Nothing retires.** All 72 files are reachable. 54 are imported by name from outside `app/Domain/AI/`; the 12 lifecycle stages are wired by FQN at `AiServiceProvider.php:421-432` (they carry no `use` import, which is why a naive grep reads them as orphans); the rest are live internal collaborators. **`RETIRE` is empty on reachability grounds, not because it went unchecked.** Retirements have to come from a product decision, not from this analysis.

**2. The three-way split does not cover the scope.** 27 of 72 files are framework-coupled and cannot move to a framework-free `lib/`. They are neither retire, nor lib, nor shapes. I added **APP** rather than force-fit them; each carries its coupling count so the porting cost is visible.

**Split: 5 LIB · 17 LIB-BLOCKED · 23 SHAPE · 27 APP · 0 RETIRE.** (Depth-N verified.)

---

## Agents

| Module | Disposition | Note |
|---|---|---|
| `Agents/Agent.php` | SHAPE | interface |
| `Agents/AgentContext.php` | APP | **7 injected services** (`SignalStore`, `EvidenceStore`, `CaseBuilder`, `ExplanationBuilder`, `RecommendationDrafter`, `EntityResolver`, `GraphQueryService`) — not a shape |
| `Agents/AgentManifest.php` | SHAPE | DTO |
| `Agents/AgentRegistry.php` | APP | coupling 4 |
| `Agents/AgentRunner.php` | APP | coupling 7; 4 external callers |

## Cases

| Module | Disposition | Note |
|---|---|---|
| `Cases/CaseBuilder.php` | APP | coupling 10 |

## Conversation

| Module | Disposition | Note |
|---|---|---|
| `Conversation/AnswerComposer.php` | LIB | 8 internal refs, no external — pure composition |
| `Conversation/AskPipeline.php` | LIB-BLOCKED | depth 3 via `LifecycleAskService` -> `ConversationStore` / `ModuleResolver` |
| `Conversation/AskService.php` | APP | coupling 10 |
| `Conversation/ConversationStore.php` | APP | **coupling 15 — heaviest in scope**; persistence |
| `Conversation/FlowTrace.php` | SHAPE | DTO |
| `Conversation/Intent.php` | SHAPE | DTO |
| `Conversation/IntentClassifier.php` | LIB | pure classification |
| `Conversation/LifecycleTraceProjector.php` | LIB | pure projection |
| `Conversation/TraceStage.php` | SHAPE | DTO |

## Decisions

| Module | Disposition | Note |
|---|---|---|
| `Decisions/DecisionGate.php` | APP | coupling 11; issues `confirmation_token` (`:161`) |

## Evidence

| Module | Disposition | Note |
|---|---|---|
| `Evidence/EvidenceItem.php` | SHAPE | DTO |
| `Evidence/EvidenceStore.php` | APP | coupling 8 |

## Explanations

| Module | Disposition | Note |
|---|---|---|
| `Explanations/ExplanationBuilder.php` | APP | coupling 6 |

## Lifecycle (root)

| Module | Disposition | Note |
|---|---|---|
| `Lifecycle/LifecycleAskService.php` | LIB-BLOCKED | needs seam: `ConversationStore`, `ModuleResolver` |
| `Lifecycle/LifecyclePipeline.php` | APP | coupling 1 — **lowest-cost port in scope** |
| `Lifecycle/LifecycleStage.php` | SHAPE | interface — the stage contract |
| `Lifecycle/LifecycleTrace.php` | SHAPE | DTO |
| `Lifecycle/RecordableTrace.php` | SHAPE | interface |
| `Lifecycle/StageContext.php` | SHAPE | DTO, **mutable**; imports out-of-scope `AppServicesMcpMcpRequestContext` — gates 5 files |
| `Lifecycle/StageKey.php` | SHAPE | enum |
| `Lifecycle/StageOutcome.php` | SHAPE | DTO |
| `Lifecycle/StageStatus.php` | SHAPE | enum |

## Lifecycle/Flows

| Module | Disposition | Note |
|---|---|---|
| `Lifecycle/Flows/AdmissionsFlow.php` | APP | coupling 3 |

## Lifecycle/Modules

| Module | Disposition | Note |
|---|---|---|
| `Lifecycle/Modules/ModuleCapability.php` | SHAPE | DTO |
| `Lifecycle/Modules/ModuleRegistry.php` | APP | coupling 4 |
| `Lifecycle/Modules/ModuleResolver.php` | APP | coupling 3 |

## Lifecycle/Plan

| Module | Disposition | Note |
|---|---|---|
| `Lifecycle/Plan/DeterministicPlanner.php` | LIB-BLOCKED | needs seam: `AdmissionsFlow` |
| `Lifecycle/Plan/HybridPlanner.php` | LIB-BLOCKED | depth 2 via `StageContext` -> `McpRequestContext` (cheap — see below) |
| `Lifecycle/Plan/LlmPlanner.php` | LIB-BLOCKED | needs seam: `OpenRouterClient` |
| `Lifecycle/Plan/Plan.php` | SHAPE | DTO |
| `Lifecycle/Plan/PlanStep.php` | SHAPE | DTO |
| `Lifecycle/Plan/Planner.php` | SHAPE | interface |

## Lifecycle/Stages — all 12 wired at `AiServiceProvider.php:421-432`

| Module | Disposition | Note |
|---|---|---|
| `Lifecycle/Stages/ConversationalAiStage.php` | LIB-BLOCKED | stage 1; needs seam: `ConversationStore` |
| `Lifecycle/Stages/GenerativeAiStage.php` | LIB-BLOCKED | stage 2; needs seam: `ConversationStore` |
| `Lifecycle/Stages/AgentStage.php` | LIB-BLOCKED | stage 3; needs seam: `AgentRunner` |
| `Lifecycle/Stages/PlanningStage.php` | LIB-BLOCKED | stage 4; depth 2 via `StageContext` -> `McpRequestContext` (cheap) |
| `Lifecycle/Stages/McpToolSelectionStage.php` | LIB-BLOCKED | stage 5; needs seam: `AppMcpToolRegistry` (framework-coupled) |
| `Lifecycle/Stages/LaravelMcpStage.php` | LIB-BLOCKED | stage 6; needs seam: `AdmissionsFlow`. See `laravel-mcp-evaluation.md` §6 |
| `Lifecycle/Stages/RealDataStage.php` | APP | stage 7; coupling 3 |
| `Lifecycle/Stages/EvidenceStage.php` | LIB-BLOCKED | stage 8; needs seam: `EvidenceStore` |
| `Lifecycle/Stages/ReasoningStage.php` | LIB-BLOCKED | stage 9; needs seam: `AdmissionsFlow`, `ExplanationBuilder` |
| `Lifecycle/Stages/RecommendationStage.php` | LIB-BLOCKED | stage 10; needs seam: `RecommendationDrafter` |
| `Lifecycle/Stages/HumanApprovalStage.php` | APP | stage 11; coupling 3 |
| `Lifecycle/Stages/ActionStage.php` | APP | stage 12; coupling 3 |

> **The pipeline is 0 movable / 9 blocked / 3 APP.** Depth-N closure removed the last two candidates: `PlanningStage` and `McpToolSelectionStage` both reach app code transitively. **No stage can be extracted as-is today.** Nine are framework-free in themselves but reach `ConversationStore`, `AgentRunner`, `EvidenceStore`, `AdmissionsFlow`, `ExplanationBuilder`, `RecommendationDrafter`, `App\Mcp\ToolRegistry` or `McpRequestContext`; three are directly coupled. Extracting the pipeline is entirely a seam project.

## Lifecycle/Support

| Module | Disposition | Note |
|---|---|---|
| `Lifecycle/Support/CaseResolver.php` | LIB-BLOCKED | needs seam: `CaseBuilder` |
| `Lifecycle/Support/McpToolCaller.php` | LIB-BLOCKED | needs seam: `AppMcpToolRegistry` (framework-coupled) |
| `Lifecycle/Support/ToolAnswerComposer.php` | LIB-BLOCKED | depth 2 via `StageContext` -> `McpRequestContext` (cheap) |

## Outcomes

| Module | Disposition | Note |
|---|---|---|
| `Outcomes/MetricResolver.php` | SHAPE | interface |
| `Outcomes/OutcomeTracker.php` | APP | coupling 11 |

## Recommendations

| Module | Disposition | Note |
|---|---|---|
| `Recommendations/RecommendationDrafter.php` | APP | coupling 8 |

## Signals

| Module | Disposition | Note |
|---|---|---|
| `Signals/DetectedSignal.php` | SHAPE | DTO |
| `Signals/DetectorCoverage.php` | SHAPE | DTO |
| `Signals/SignalDetector.php` | SHAPE | interface |
| `Signals/SignalStore.php` | APP | coupling 10 |
| `Signals/ThresholdRegistry.php` | APP | coupling 3; 5 external importers — **most-imported class in scope** |

## Support

| Module | Disposition | Note |
|---|---|---|
| `Support/AiAuditLogger.php` | APP | coupling 4; 5 external importers; redacts `confirmation_token` (`:131`) |
| `Support/OpenRouterClient.php` | APP | coupling 1; vendor edge |

## Workspace

| Module | Disposition | Note |
|---|---|---|
| `Workspace/AiContext.php` | SHAPE | DTO |
| `Workspace/AiContextService.php` | APP | coupling 4 |
| `Workspace/CapabilityResolver.php` | APP | coupling 6 |
| `Workspace/FlowStateResolver.php` | APP | coupling 11 |
| `Workspace/OntologyViewResolver.php` | APP | coupling 4 |
| `Workspace/PageDataResolver.php` | APP | coupling 3 |
| `Workspace/PageSnapshot.php` | SHAPE | DTO |
| `Workspace/PageTypeResolver.php` | LIB | pure |
| `Workspace/RouteMatcher.php` | LIB | pure |

---

## Rules this contract binds

1. **SHAPE files take no behaviour and no dependencies.** They are the `app/` ↔ `lib/` boundary vocabulary. A change adding a service call to a SHAPE breaks the contract.
2. **LIB files may not import `Illuminate\*`, `App\Models\*`, facades, or `DB::`.** That is the definition, and it is checkable in CI.
3. **APP files stay put.** Moving one requires first landing an interface seam, then re-classifying it here.
4. **Nothing retires without a product decision** recorded on its line.
5. **Amend this file in the same change** as any move. The manifest is the contract; a move not reflected here is a defect.

## Method, and its limits

Three passes, each of which caught errors the previous one made:

1. **Direct coupling** — count of `use Illuminate\*` / `use App\Models\*` / `Eloquent` / `Facades` / `DB::` per file. Separates APP from the rest.
2. **Reachability** — `use`-statement tally across `app/ routes/ tests/ config/ database/` excluding `app/Domain/AI/` as a source, plus an FQN `::class` scan. The scan matters: the 12 stages are registered as `\App\...\Stages\X::class` at `AiServiceProvider.php:421-432` and carry no `use` import, so pass 2 without it reports all twelve as dead.
3. **Transitive coupling** — for every file that passed pass 1, check whether it imports a class that pass 1 marked APP. **This reclassified 11 files from LIB to LIB-BLOCKED.** Direct-import purity is not portability: a file with zero framework imports still cannot move if it depends on one that has them.

**SHAPE claims were verified, not assumed** — line count, public-method count, and constructor dependency count per file. That check demoted `AgentContext` (218 LOC, 14 methods, 7 injected services) from SHAPE to APP. Two others were inspected and kept: `PageSnapshot` and `FlowTrace` are large but their methods are derived queries over their own data, with no dependencies.

### Known limits

- All three passes are greps. A class reached only through a dynamically built name (`app()->make()` on a computed string) would be invisible. None was found; the method cannot prove none exists.
- Transitive coupling is now computed to **full depth** — BFS over every `use App\*` edge across all of `app/`, not just `app/Domain/AI/`. That pass demoted 6 further files and is what the split reflects. Cycles are handled with a seen-set; imports that resolve to no file (vendor, aliases) are skipped rather than assumed clean.
- `StageContext` is classified SHAPE but is **mutable** (`set`, `addSection`, `addAction`, `recordToolCall`). It carries no dependencies, so it satisfies rule 1 as written — but if SHAPE is meant to imply immutability, it needs its own decision.

---

## Unblocking order (from the depth-N closure)

Only **5 files move today**: `AnswerComposer`, `IntentClassifier`, `LifecycleTraceProjector`, `PageTypeResolver`, `RouteMatcher` — all zero-dependency leaves.

The other 17 are gated by just **three** distinct blockers. Fix them in this order:

**1. `App\Services\Mcp\McpRequestContext` — near-zero cost, unblocks 3.**
It has **zero framework coupling and zero imports** — a pure leaf. It blocks anything only because it sits outside `app/Domain/AI/`, so this is a scope artifact, not real coupling. Move it to `lib/` (or re-scope it as a SHAPE) and `HybridPlanner`, `PlanningStage` and `ToolAnswerComposer` become clean immediately, taking the movable set from 5 to 8 and giving the pipeline its first extractable stage.

It reaches them all through `StageContext`, which every stage receives — so this one edge gates the widest area of the codebase.

**2. `App\Mcp\ToolRegistry` — small, unblocks 2.**
Framework-coupled by a single import, `Illuminate\Validation\ValidationException`, plus `McpConfirmationService`. Swapping that for a domain exception behind an interface frees `McpToolSelectionStage` and `McpToolCaller`.

**3. The APP data stores — the real work, gates the remaining 12.**
`ConversationStore` (coupling 15), `EvidenceStore`, `CaseBuilder`, `AgentRunner`, `AdmissionsFlow`, `ExplanationBuilder`, `RecommendationDrafter`. Each needs a genuine interface seam. This is where the extraction budget actually goes.

**Do steps 1 and 2 before planning step 3.** Together they cost little and convert 5 files, which makes the size of step 3 measurable against real code rather than estimated.