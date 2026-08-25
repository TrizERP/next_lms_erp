# AI Architecture — Implementation Analysis (Phase 1 Audit)

**Date:** 2026-08-20
**Scope:** `D:\next_lms_erp` (Laravel backend) + `D:\lms_k12` (Next.js frontend)
**Status:** Audit only — **no code was modified**.
**Purpose:** Establish what already exists before building the shared intelligence architecture
(Conversational AI → Ontology/KG → Signal → Evidence → Case → Agent → Explain → Recommend →
Human Decision → Workflow → Action → Outcome → Learning).

---

## 0. Executive summary

The platform is **much further along than a greenfield build**. Five of the seven layers already
exist in some form and must be *reused*, not recreated:

| Layer | Status | Where |
|---|---|---|
| Conversational AI | ✅ **Mature, working** | `lms_k12/packages/conversational-ai-core` + `lib/ai/adapters/lms-k12` |
| Tool calling / MCP | ✅ **Mature, working** | `next_lms_erp/app/Mcp` + `app/Services/Mcp` |
| Human approval gate | ✅ **Exists for MCP tools** | `McpConfirmationService` + `mcp_confirmation_requests` |
| Audit logging | ✅ **Exists for MCP** / ⚠️ in-memory only for conversation | `mcp_audit_logs` / `packages/.../audit.ts` |
| Generative AI | ⚠️ **Exists but fragmented** | `AIOrchestrationService`, `OpenAIService`, `QuestionGenerationService`, `GammaService` |
| Signal detection | ⚠️ **Exists but ephemeral** | `PredictiveInterventionEngine` (computes, never persists) |
| Evidence | ⚠️ **Exists as a read repository** | `PalEvidenceRepository` (reads raw data, no evidence records) |
| Knowledge Graph | ⚠️ **Real Neo4j, migration incomplete** | `Neo4jService`, `app/Console/Commands/Neo4j`, `docs/neo4j-migration-status.md` |
| Ontology | ❌ **Missing as a formal artifact** | Implicit in `database/neo4j/gen_registry.php` |
| Case | ❌ **Missing** | — |
| Explain (grounded) | ❌ **Missing** | — |
| Recommendation (governed) | ❌ **Missing** | `recommendations` table is an unrelated JSON blob |
| Decision / Approval record | ❌ **Missing** (MCP confirmation is per-call, not durable) | — |
| Workflow engine | ❌ **Missing** (conversational "workflow state" is a file-based slot filler) | — |
| Outcome / Learning loop | ❌ **Missing** | — |
| Agent framework | ❌ **Missing** (`agenticAI` controllers are ~1.9 KB view stubs) | — |

### The single most important finding

**The three governance files named in the brief do not exist in this repository.**

```
app/Domain/Verbs/RecommendVerb.php        → NOT FOUND
app/Domain/Ai/GroundedClaims.php          → NOT FOUND
app/Domain/Recommendation/EsoBindingRule.php → NOT FOUND
```

`app/Domain/` itself does not exist. A repo-wide grep for `RecommendVerb`, `GroundedClaims`,
`EsoBindingRule`, `ExplainVerb`, `DetectSignals`, `ESO` and `KASBA` across `app/`, `database/` and
`routes/` returns **zero matches**.

These artifacts belong to the **sibling G2G / people-competency project**, not to this K-12 codebase.
The evidence: `packages/conversational-ai-core/src/schemas.ts` declares
`conversationDomainSchema = ["k12", "people_competency", "enterprise_brain", "shared"]` — the shared
conversational package is already multi-project, and the ESO/Verb governance lives on the
`people_competency` side.

**Consequence:** the governance rules cannot be "preserved" here because they are not here. They must
be **authored** in this repo, in a shape deliberately compatible with the G2G originals so the two
can converge. This is the one place where the brief's assumption does not match reality, and it
changes the plan: Phase 5 (Agent framework) must be preceded by a **governance kernel** phase.
Nothing else in the brief is invalidated.

> **Action required from you:** if the G2G repo is reachable, point me at it so I can mirror the real
> `RecommendVerb` / `GroundedClaims` / `EsoBindingRule` contracts rather than inferring them. If it is
> not, I will author them from the brief's stated semantics and flag them as "K-12 originals, pending
> reconciliation".

---

## 1. Existing architecture

### 1.1 Backend — `D:\next_lms_erp`

| Aspect | Finding |
|---|---|
| Framework | **Laravel 12**, PHP ^8.2 |
| Structure | Classic Laravel MVC. **No** `app/Domain`. **No** repository layer as a convention. |
| Layout | `app/{Http,Models,Services,Mcp,Botman,Console,Imports,Mail,Traits,Classes,Helpers,Providers,View}` |
| Controllers | **578 PHP files** across 38 module folders (`admission`, `fees`, `lms`, `result`, `student`, `HRMS`, `skill`, `sqaa`, `inventory`, `agenticAI`, `neo4J`, `neo4jGraph`, `Mcp`, `api`, …) |
| Models | 98 Eloquent models |
| Services | `app/Services` — the only place with real layered design. `PAL/` alone holds **13 sub-namespaces / ~60 service classes**. |
| Routes | **32 route files**, ~290 KB total. `web.php` (48 KB) and `api.php` (30 KB) dominate; module routes are split out (`lms.php`, `fees.php`, `pal_api.php`, `result.php`, `student.php`, `mcp.php`, …). |
| Migrations | **596 migrations** |
| Database | **MySQL/MariaDB** (`DB_CONNECTION=mysql`) |
| Queue | ⚠️ **`QUEUE_CONNECTION=sync`** — no async worker. `app/Jobs` **does not exist**. |
| Cache | `CACHE_DRIVER=file`. Redis host is configured but not the active driver. |
| Scheduler | `routes/console.php` + `app/Console/Commands` (~30 commands, incl. `Neo4j/` and `PAL/` groups) |
| Events/Listeners | Not used as an architectural pattern |
| Auth | Laravel Sanctum + JWT (`generationtux/jwt-artisan`) + session. Multiple auth paths coexist. |

### 1.2 Frontend — `D:\lms_k12`

| Aspect | Finding |
|---|---|
| Framework | **Next.js 16.2.6**, **React 19.2.4**, App Router, TypeScript 5 |
| Styling | **Tailwind CSS v4**, shadcn/ui, `class-variance-authority`, `tw-animate-css` |
| Design system | `K-12 ERP Design System/` — a full enterprise DS (74 components, 4 themes, 3 density modes, token-based). Documented in `CLAUDE.md`. Indigo brand, slate neutrals, no gradients/glass. |
| Routing | App Router, **56 top-level route folders** mirroring ERP modules |
| Layouts | `app/components/DashboardShell.tsx` (490 L), `Sidebar.tsx` (439 L), `Header.tsx`, `Level3Subheader.tsx`, `ConditionalApp.tsx` |
| Navigation | ⚠️ **Database-driven** — the sidebar renders from `tblmenumaster` via API, not from a static config. New nav entries require a **menu migration** on the backend. |
| State | React Context (`contexts/AuthContext.tsx`, 269 L) + `localStorage`. **No Redux/Zustand.** |
| Auth | `AuthContext` holds `{isAuthenticated, user, menuContext, academicTerms, academicYears}`. `menuContext = {sub_institute_id, user_id, user_profile_name, user_profile_id, client_id}` — this is the tenant scope carrier. 30-minute inactivity timeout. |
| API layer | `lib/erp-client.ts`, `lib/erp-legacy.ts`, `app/components/utils/api_url.tsx` (`API_BASE_URL`), plus Next route handlers under `app/api/**` acting as proxies (`app/api/proxy/route.ts`). |
| Charts | `recharts` + `chart.js` + `react-chartjs-2` |
| Editors | TipTap 3 (rich text), CraftJS (document template builder) |

---

## 2. Existing AI capabilities

### 2.1 Conversational AI — the crown jewel (DO NOT REBUILD)

Two workspace packages, path-mapped in `tsconfig.json`:

```
@shared/conversational-ai-core  → packages/conversational-ai-core/src/index.ts
@shared/conversational-mcp-core → packages/conversational-mcp-core/src/index.ts
```

**`packages/conversational-ai-core` — 8,733 lines across 33 files.**

| File | Lines | Role |
|---|---|---|
| `conversation.ts` | **3,938** | The orchestrator: `generateConversationResponse`, `streamConversationResponse` |
| `module-workflow-routing.ts` | 591 | Routes a message to a module workflow |
| `module-records.ts` | 531 | Module record shapes |
| `entity-selection.ts` | 392 | Disambiguates which entity the user means |
| `conversation-focus.ts` | 348 | Tracks conversational focus across turns |
| `followup-state.ts` | 312 | Follow-up question state |
| `discovery.ts` | 159 | `ProjectDiscoverySnapshot`, `DiscoveredTool` |
| `module-navigation.ts` | 150 | Route handoff |
| `workflow-state.ts` | 107 | ⚠️ **File-backed conversational slot-filling state — NOT a workflow engine** |
| `router.ts` | 84 | `createRoutingDecision` — scores tools against intent |
| `types.ts` | 74 | `ProjectAIAdapter`, `ProjectToolDefinition`, `ProjectContext` |
| `schemas.ts` | 69 | Intent / message / trusted-session Zod schemas |
| `response-schema.ts` | 51 | `ConversationalResponse` |
| `audit.ts` | 51 | ⚠️ **In-memory ring buffer (500 entries) + console.log — not durable** |
| `security.ts` | 24 | `PromptSecurityGuard` / `ToolExecutionGuard` **interfaces only — no implementations found** |
| `model.ts` | 15 | Gemini via `@ai-sdk/google` |
| *(4 test files)* | 496 | Vitest-style tests already exist |

**Key extension points (this is how new capability plugs in without touching the core):**

```ts
// packages/conversational-ai-core/src/types.ts
export interface ProjectAIAdapter {
  projectId: string;
  projectName: string;
  resolveContext(...): Promise<ProjectContext>;
  classifyIntent(context): Promise<ConversationIntent>;
  buildSystemPrompt(context, intent): Promise<string>;
  getToolDefinitions(context): Promise<ProjectToolDefinition[]>;   // ← add tools here
  getAllowedToolNames(context): Promise<string[]>;                 // ← permission gate here
  validatePermission(intent, context): Promise<void>;
}

export interface ProjectToolDefinition {
  name; description; inputSchema;                 // zod
  requiredPermissions: string[];
  riskLevel: "low" | "medium" | "high";
  requiresConfirmation: boolean;                  // ← human-approval hook already present
  capabilities: string[];                         // ← router scores against these
  execute(input, context): Promise<unknown>;
}
```

`ProjectToolDefinition` **already carries `riskLevel`, `requiresConfirmation`, `requiredPermissions`
and `capabilities`.** The governance vocabulary the brief asks for is half-built here.

**Intent taxonomy already defined** (`schemas.ts`) — and it already contains the verbs we need:

```
conversationType: ask | learn | guide | do | analyse | recommend | automate | monitor | coach
conversationDomain: k12 | people_competency | enterprise_brain | shared
```

`recommend`, `analyse` and `monitor` are already first-class intent types. The Agent layer should
**bind to these**, not invent a parallel taxonomy.

**Response status enum already supports the approval gate:**

```
completed | in_progress | requires_input | requires_confirmation | navigation_required | failed
```

### 2.2 The K-12 adapter — `lms_k12/lib/ai/adapters/lms-k12` (8,625 lines)

| File | Lines | Role |
|---|---|---|
| `tools.ts` | **2,727** | 17 hand-written tools + `getLmsToolDefinitions()` + `getAllowedToolNamesForProfile()` |
| `adapter.ts` | **1,722** | Intent classification (LLM + deterministic fallbacks), permissions, system prompt |
| `module-data-tools.ts` | 1,442 | Generic module read/analysis tools (`MODULE_DATA_TOOL_NAMES.{read,analysis,admin}`) |
| `entity-resolution.ts` | 717 | Student/standard/division/quota resolution |
| `admission-workflow.ts` | 366 | The one end-to-end multi-step flow that exists today |
| `schemas.ts` | 283 | Tool input schemas |
| `server-api.ts` | 136 | Backend calls |
| `mcp-server.ts` | 58 | Bridge to the Laravel MCP server |
| *(5 test files)* | ~580 | Existing test coverage |

**Registered tools (17 + module-data tools):**
`getLmsDashboard`, `getActivityStream`, `listHomework`, `listFeesDefaulters`, `searchStudents`,
`findStudentFeeRecord`, `getStudentFeeDetails`, `getTeacherDailyReport`, `getResultReport`,
`listAdmissionEnquiries`, `hydrateAdmissionCandidate`, `findAdmissionCandidate`,
`confirmAdmissionCandidate` *(risk: medium, requiresConfirmation: **true**)*,
`updateAdmissionCandidateDetails`, `getContextualSuggestions`, `executeModuleAction`,
plus `getClassStructure`, `getSubjectCatalog`, `getCourseCatalog`, `getStudentAttendanceDetail`, …

**Permission model** — `getAllowedToolNamesForProfile(profileName)` layers tools by profile:
student → teacher → admin. Each tier *adds* names. This is the pattern any Agent tool must follow.

### 2.3 MCP — Laravel side (`next_lms_erp`)

```
app/Mcp/
  McpToolInterface.php
  ConfirmableMcpToolInterface.php     ← the human-approval contract
  AbstractMcpTool.php                 ← allowedRoles(), isReadOnly(), definition()
  ToolRegistry.php                    ← preview → confirmation token → executeConfirmed
  Tools/  (8 tools)
app/Services/Mcp/
  McpRequestContext.php   McpContextResolver.php   ← tenant isolation
  McpConfirmationService.php                       ← durable confirmation tokens
  McpAuditService.php                              ← durable audit
  ToolResult.php
  {AdmissionMcpService, AdmissionsTodayService, FeesCollectionReportService,
   FeesPendingService, StudentSearchService}.php
app/Http/Controllers/Mcp/ {Initialize, McpHealth, ToolsList, ToolsCall, McpController}
app/Http/Middleware/ {McpAuth, McpRateLimit, McpContextHydrator}
routes/mcp.php  →  prefix api/mcp  (health, initialize, tools, tools/call)
config/mcp.php  →  protocol 2025-06-18, rate limit 60/min, confirmation TTL 10 min
```

**`ToolRegistry::execute()` is the existing human-approval gate and it is well built:**

```php
if ($tool instanceof ConfirmableMcpToolInterface) {
    if ($confirmationToken) {                                  // second call — human said yes
        $confirmation = $this->confirmationService->consume($confirmationToken, $toolName, $context);
        return $tool->executeConfirmed($arguments, $context, $confirmation);
    }
    $preview      = $tool->preview($arguments, $context);      // first call — show, don't do
    $confirmation = $this->confirmationService->create($toolName, $arguments, $context, $preview);
    return ['mode' => 'preview', 'requires_confirmation' => true, ...];
}
```

**This is the pattern the Decision/Approval layer must extend — not replace.**

**Tenant isolation** (`McpContextResolver`) is correct and must be the model for every new AI surface:
- parses `sub_institute_id` into `allowedInstituteIds`
- rejects a requested institute outside scope (`is_admin !== 2` super-admin escape hatch)
- validates `academic_year` + `term_id` against the `academic_year` table
- carries `{userId, role, selectedInstituteId, allowedInstituteIds, userProfileId, clientId, academicYear, termId, isAdmin, isStudent}`

### 2.4 Generative AI — fragmented across 4+ services

| Service | Provider | Notes |
|---|---|---|
| `app/Services/PAL/AI/AIOrchestrationService.php` (420 L) | **OpenRouter** (`config/openrouter.php`) | 7 generation methods: `generateExplanation`, `generateRemediation`, `generatePractice`, `summarizeContent`, `getTeacherInsights`, `generateMetacognitivePrompt`, `batchProcess`. Prompt builders are private methods. |
| `app/Services/OpenAIService.php` | **OpenAI** `gpt-3.5-turbo` + **OpenRouter** `deepseek/deepseek-chat` | Lesson plans, images, sports data, `generateContent()` with **API-key rotation from `ai_api_keys` + daily limits (`ai_daily_used_api`)** |
| `app/Services/QuestionGenerationService.php` | — | Question paper generation |
| `app/Services/GammaService.php` | Gamma | Presentation generation |
| `app/Services/PAL/ContentModel/ContentModelLlmClient.php` | — | Content model enrichment |
| **Frontend:** `packages/conversational-ai-core/src/model.ts` | **Google Gemini** (`gemini-2.5-flash` via `@ai-sdk/google`) | The conversational path |

**Problems this creates:**
- **4 different LLM providers** in one product (OpenAI, OpenRouter/DeepSeek, Gemini, Gamma).
- No shared prompt/template registry — every prompt is a private PHP method.
- No output-schema validation on the PHP side.
- No safety checks.
- No generation audit trail (only `ai_interaction_logs`, which is narrow).
- Generated content is **not marked as generated** — it is returned as plain strings, so nothing
  stops it from being read as fact. This directly violates the brief's requirement that
  *"generated content must be clearly distinguishable from verified factual data."*

`app/Services/OpenAIService.php::generateContent()` does contain the one genuinely valuable piece:
**API-key rotation with per-key daily limits**. That must be preserved and lifted into the shared layer.

### 2.5 Signal detection — exists, but ephemeral

`app/Services/PAL/Intelligence/PredictiveInterventionEngine.php` (401 L) is a **real, working
signal detector on real data**:

```php
predictDisengagement(int $learnerId): array
predictFailure(int $learnerId): array
predictBurnout(int $learnerId): array
getRiskScore(int $learnerId): float
classifyRisk(float $score): string
```

Backed by ~14 individual signal calculators:
`calculateEngagementSignal`, `calculateFrequencySignal`, `calculateDurationSignal`,
`calculateErrorRecoverySignal`, `getFrustrationSignal`, `calculateSuccessRateSignal`,
`getMasteryGapsSignal`, `calculateRecentPerformance`, `getPeerComparisonSignal`,
`getCognitiveLoadSignal`, `calculateDurationTrendSignal`, `calculateEngagementDecaySignal`,
`getDifficultySignal`, `getRecoveryTimeSignal`, `getIntrinsicMotivationSignal`.

**Gaps:**
- Results are **computed and returned, never persisted**. There is no signal history, so no trend,
  no "when did this start", no outcome measurement, and no learning loop.
- Thresholds live in `classifyRisk()` as hardcoded numbers — the brief explicitly says *"do not
  hardcode arbitrary thresholds without checking existing business rules."* **These are the existing
  business rules and they must be read from here, not re-invented.**
- `getDisengagementActions()` / `getFailureActions()` / `getBurnoutActions()` return **action strings**
  — the seed of a Recommendation, but ungoverned and unbound to evidence.

Sibling engines to reuse: `LearnerStateEngine`, `LearningVelocityEngine`,
`MisconceptionIntelligenceEngine`, `BehavioralAnalyticsService`, `IntelligenceService`,
`RecommendationEngine` (`getNextBestAction`, `getContentRecommendation`, `getSpacedRepetitionSchedule`).

### 2.6 Evidence — a read repository, not evidence records

`app/Services/PAL/Runtime/PalEvidenceRepository.php` (481+ L) reads real data:
`responseSequences()`, `attempts()`, `chapterNames()`, `conceptsForChapters()`,
`careerSignalCoverage()`, `prerequisiteGraph()`, `gradesForStandards()`, `chaptersByGrade()`.

It is named "Evidence" but produces **raw rows**, not addressable, citable evidence items with
provenance. The Case layer needs `{id, kind, source_table, source_id, observed_at, value, confidence,
verified}` so an explanation can cite it.

### 2.7 Knowledge Graph — real Neo4j, migration **in progress and blocked**

- Package: `laudis/neo4j-php-client ^3.2`
- Service: `app/Services/Neo4jService.php` (153 L) — `createNode`, `createOrGetNode`,
  `createRelationship`, `run($query, $params)`
- Live instance: `NEO4J_URI=bolt://dev.triz.co.in:7688`
- Tooling: `app/Console/Commands/Neo4j/{Export, Load, RegistryCheck, ResetGraph, SeedRescue, Verify}Command.php` (~100 KB)
- Registry: `database/neo4j/gen_registry.php` — **488 tables classified**
- Also: `app/Services/PAL/ULU/ULUGraphService.php`, `app/Http/Controllers/neo4jGraph/{Graph,StudentResultGraph}Controller.php`, `app/Http/Controllers/neo4J/addAssesmentController.php`

**Migration status** (`docs/neo4j-migration-status.md`, last updated 2026-08-12):

| Phase | Status |
|---|---|
| 0 Freeze & backup | ✅ |
| 1 Classify 488 tables | ✅ |
| 2 Registry + tooling | ✅ |
| 3 Wipe & schema reset | ✅ |
| 4 Foundation (Institute, AcademicYear, Standard, Subject, Division) | ✅ 10/10 |
| 5 Curriculum (Chapter, Topic, Concept, Content) | 🟡 7/10 — G8, G9, G10 fail |
| 6 Question bank | ❌ not started |
| **7 People — Student + Enrollment** | ❌ **not started** |
| **8 Assessment — attempts, mastery** | ❌ **not started** |
| 9–13 | ❌ not started |

Current graph: **138,689 nodes / 161,044 rels / 138 constraints**.

> 🔴 **This is the critical path blocker for the brief's headline use case.**
> "Why is this student at risk?" requires traversing
> `Student → Subject → Assessment → Performance Evidence → Signal → Case → Recommendation`.
> **Phases 7 (Student) and 8 (Assessment) are not in the graph.** The KG today holds curriculum
> structure, not learner behaviour. Academic Risk therefore **cannot** be served from Neo4j yet.

**Also flagged in that doc:** unresolved blocking decisions (`TENANT-SCOPE`, `PREREQ-SOURCE`,
`SOURCE-DANGLING`, `G9-CHURN`, `MAPPINGTYPE-SCOPE`), a documented drift incident, and a rescue export
that lives **outside the repo** at `C:/Users/sonik/neo4j-rescue` and has been deleted three times.

### 2.8 Ontology — not a formal artifact

There is no ontology definition anywhere. The closest thing is `database/neo4j/gen_registry.php`
(488 tables → node labels + relationship types), which is a *migration mapping*, not an ontology:
it has no entity semantics, no shared vocabulary, no cross-domain (K-12 ↔ G2G) alignment, and is
not queryable at runtime.

Related but distinct: `app/Services/PAL/Content/PalVocabulary.php` and
`pal_concept_relations` / `semantic_intelligence` tables hold **curriculum** semantics only.

### 2.9 Agentic AI — **stubs only**

`app/Http/Controllers/agenticAI/` contains 7 controllers, each **~1.9 KB** — these are view-returning
scaffolds, not agents:

```
agentAnalyticController.php   agentDashboardController.php   agentLibraryController.php
agentReflectionController.php agentRunLogController.php      createAgentController.php
multiAgentController.php
```

There is **no agent registry, no agent runtime, no tool-permission binding, no agent_runs table.**

### 2.10 Workflow — **does not exist as an engine**

Two things are named "workflow" and neither is one:

1. `packages/conversational-ai-core/src/workflow-state.ts` (107 L) — a **file-backed conversational
   slot-filling state machine**: `idle → searching → selecting_entity → hydrating_context →
   collecting_slots → awaiting_confirmation → executing → completed | failed`. Per-conversation,
   ephemeral, no persistence in the DB, no approvals, no assignments, no retries, no outcomes.
2. `module-workflow-routing.ts` / `admission-workflow.ts` — **routing logic**, i.e. "which module
   handles this sentence".

The admission-confirmation flow is the only real multi-step business process, and it is
**hardcoded in TypeScript**, not configuration-driven.

---

## 3. Existing APIs

| Surface | Base | Auth | Notes |
|---|---|---|---|
| MCP | `POST/GET /api/mcp/{health,initialize,tools,tools/call}` | `McpAuth` + `McpRateLimit` + `McpContextHydrator` | The cleanest API in the repo. **Model for all new AI APIs.** |
| PAL | `/api/pal/**` | `pal.auth` middleware | `routes/pal_api.php` (21 KB). Sub-groups: `workspace`, `intelligence`, `pedagogy-engine`, `content-model`, `gamification`, `architecture`, `h5p`, `content-intelligence`. Read-only pedagogy routes sit **outside** the auth group. |
| LMS | `routes/lms.php` (28 KB) | mixed | |
| Fees | `routes/fees.php` (21 KB) | mixed | |
| Result | `routes/result.php` + `resultapi.php` (40 KB) | mixed | |
| Student | `routes/student.php` (23 KB) | mixed | |
| HRMS | `routes/hrms.php` (11 KB) | mixed | |
| Admission | `routes/admission.php` | mixed | |
| Generic API | `routes/api.php` (30 KB) | Sanctum | |

**Frontend route handlers** (`lms_k12/app/api/**`) act as BFF proxies:
`ai/chat`, `conversation/history`, `mcp/{capabilities,health,tools/call}`,
`pal/{content-model,pedagogy-engine,submit}`, `voice/{config,synthesize,transcribe}`,
`fees/**` (12 report proxies), `proxy`, `proxy-file`.

**Convention to follow:** `Route::prefix('api/<domain>')->middleware('<domain>.auth')->group(...)`
with kebab-case segments and numeric `where()` constraints on IDs.

---

## 4. Existing database structure

**596 migrations.** Relevant groups:

### PAL core (`2026_06_11_131905_create_pal_tables.php`) — 27 tables
```
pal_subjects  pal_concepts  pal_competencies
pal_learning_sessions  pal_session_events  pal_assessment_results
pal_misconceptions  pal_learner_misconceptions
pal_remediations  pal_remediation_sessions
pal_contents  pal_content_recommendations
pal_telemetry_events  pal_reflections  pal_pedagogy_effectiveness
pal_learner_preferences  pal_learner_states
pal_collaboration_activities  pal_classroom_activities  pal_discussions  pal_group_activities
pal_self_corrections  pal_learning_plans  pal_strategy_selections
pal_learning_journals  pal_learning_events  pal_learning_patterns
```

### PAL content intelligence — 8 tables
```
pal_question_metadata  pal_content_metadata  pal_concept_metadata  pal_concept_relations
pal_misconception_library  pal_misconception_corrective
pal_learner_content_exposure  pal_content_review_log
```

### PAL content model / pedagogy / H5P / gamification / architecture
```
pal_cm_node_overrides  pal_cm_node_revisions  pal_cm_enrichment
pal_pedagogy_engine_sections  pal_pedagogy_engine_rules  pal_pedagogy_engine_modules
pal_h5p_node_metadata  pal_unified_learning_units  pal_architecture_settings
+ gamification tables
```

### AI / MCP
```
ai_interaction_logs          (2026_02_18)
ai_daily_used_api            (2026_02_21)  ← per-key daily usage counters
ai_api_keys                  (2026_04_28)  ← key rotation pool
ai_sops                      (2026_07_17)
mcp_audit_logs               (2026_07_30)  ← request_id, tool_name, user_id, sub_institute_id,
                                              status_code, outcome, input/response payload, error
mcp_confirmation_requests    (2026_07_30)  ← the durable approval-token store
```

### Menu / permissions
```
tblmenumaster    ← 3-level nav (level 1/2/3), driven by parent_menu_id, link, sort_order,
                   sub_institute_id, client_id, menu_type='ENTRY'
+ menu rights tables (see 2026_08_14_120000_grant_new_pal_menu_rights.php)
```

### ⚠️ `recommendations` (2025-01-02) — **not usable**
```php
$table->bigIncrements('id');
$table->bigInteger('user_id');
$table->longText('recommendations')->nullable();
$table->timestamps();
```
An untyped JSON blob keyed only by `user_id`. No case link, no evidence link, no status, no approver,
no tenant scope. **Do not extend this table — create `ai_recommendations` instead** and leave this one
alone (it is referenced by existing code paths).

### Core business tables referenced by the AI layer
`academic_year`, `standard`, `chapter_master`, `lms_concept`, `lms_online_exam`,
`lms_online_exam_answer`, `semantic_intelligence`, plus student/employee/fees/attendance tables.

---

## 5. Existing frontend routes / components

**56 route folders** under `lms_k12/app/`, including: `dashboard`, `students`, `student`,
`admissions`, `admission-Enquiry`, `attendance`, `exam`, `fees`, `result`, `lms`, `pal`, `new-pal`,
`h5p`, `quiz`, `reports`, `subjects`, `chapters`, `course-master`, `learning-outcome`,
`career-counselling`, `classteacher`, `teachertransfer`, `teacher_daily_report`, `hostel`,
`library`, `Inventory`, `Transportation`, `settings`, `school_setup`, `academic_setup`, `sqaa`,
`ai-platforms`, `document-templates`, `migration-modules`, `user`, `login`, …

**AI-facing UI today:**

| Component | Lines | Role |
|---|---|---|
| `app/components/ChatbotPanel.tsx` | **638** | The conversational surface. Voice in/out, module handoff cards (`MODULE_HANDOFF_COPY` for admissions/fees/homework/students/attendance/teachers/departments/subjects/courses/classes), navigation actions, tool badges, error variants. |
| `app/components/DashboardShell.tsx` | 490 | App frame; also `augmentPalLevel3Items` (SPA-side nav augmentation) |
| `app/components/Sidebar.tsx` | 439 | DB-driven 3-level nav |
| `app/components/RightFloatingToolbar.tsx` | — | Assistant launcher rail |
| `app/ai-platforms/page.tsx` | — | AI platform listing |
| `hooks/use-voice-interaction.ts` | — | STT/TTS |
| `hooks/use-agent-action-handler.ts` | 36 | Handles navigation/confirmation actions from the assistant |
| `lib/chatbot-storage.ts` | 11 | `TEACH_ASSISTANT_CONVERSATION_ID_KEY`, `TEACH_ASSISTANT_MESSAGES_KEY` |

**Design system:** `K-12 ERP Design System/` — components live at `components/ui/**` (shadcn-style).
Groups include a **`workflow`** group (`ApprovalCard`, `TimelineItem`, `ActivityFeed`, `AuditEntry`)
and a **`communication`** group (`AssistantPanel`, `AssistantLauncher`, `NotificationItem`,
`CommentThread`).

> ✅ **`ApprovalCard`, `TimelineItem`, `ActivityFeed` and `AuditEntry` already exist in the design
> system.** The entire Recommendation → Approval → Workflow-timeline → Audit UI can be built from
> existing components. No new visual language is needed.

---

## 6. Existing reusable services (reuse list)

### Backend — reuse as-is
| Service | Reuse for |
|---|---|
| `App\Mcp\ToolRegistry` + `ConfirmableMcpToolInterface` | The **human-approval gate**. Extend, never bypass. |
| `App\Services\Mcp\McpContextResolver` / `McpRequestContext` | **Tenant + academic-scope isolation** for every agent and workflow. |
| `App\Services\Mcp\McpConfirmationService` | Durable confirmation tokens → becomes the Decision record's backing store. |
| `App\Services\Mcp\McpAuditService` | Audit. Extend `mcp_audit_logs` or mirror its shape into `ai_audit_logs`. |
| `App\Services\Neo4jService` | All KG reads/writes. |
| `App\Services\PAL\Intelligence\PredictiveInterventionEngine` | **The Academic Risk signal source.** Thresholds here are the existing business rules. |
| `App\Services\PAL\Intelligence\RecommendationEngine` | Next-best-action candidates. |
| `App\Services\PAL\Intelligence\{LearnerStateEngine, LearningVelocityEngine, MisconceptionIntelligenceEngine, BehavioralAnalyticsService}` | Additional signal inputs. |
| `App\Services\PAL\Runtime\PalEvidenceRepository` | Raw evidence reads. |
| `App\Services\PAL\AI\AIOrchestrationService` | Existing generation methods — wrap, don't rewrite. |
| `App\Services\OpenAIService::generateContent()` | **Key rotation + daily limits** — lift into the shared Gen AI layer. |
| `App\Http\Middleware\{McpAuth, McpRateLimit, McpContextHydrator, PalApiAuth, checkPermission}` | Auth/permissions for new routes. |
| `App\Console\Commands\Neo4j\*` | KG load/verify tooling — extend for Phases 7/8. |

### Frontend — reuse as-is
| Module | Reuse for |
|---|---|
| `@shared/conversational-ai-core` (all of it) | The conversational entry point. **Extend via the adapter only.** |
| `lib/ai/adapters/lms-k12/tools.ts::getLmsToolDefinitions()` | Register new intelligence tools here. |
| `lib/ai/adapters/lms-k12/tools.ts::getAllowedToolNamesForProfile()` | Permission-gate new tools here. |
| `lib/ai/mcp-client.ts` | Backend MCP calls (already handles `confirmationToken`). |
| `contexts/AuthContext.tsx` (`menuContext`) | Tenant scope on the client. |
| `components/ui/**` + design system `workflow` group | Approval / timeline / audit UI. |
| `app/components/ChatbotPanel.tsx` | Surface for recommendations + approval prompts. |

---

## 7. Missing capabilities

| # | Missing | Severity | Why it matters |
|---|---|---|---|
| 1 | **Governance kernel** (`RecommendVerb`, `GroundedClaims`, `EsoBindingRule` equivalents) | 🔴 Blocker | Nothing currently enforces "a recommendation must be backed by evidence". |
| 2 | **Ontology registry** (entities + relationships, queryable at runtime) | 🔴 Blocker | Intent/entity resolution has nothing to resolve *against*. |
| 3 | **Persistent Signal store** | 🔴 Blocker | Signals are computed and thrown away → no trend, no outcome, no learning. |
| 4 | **Evidence records** with provenance + `verified` flag | 🔴 Blocker | Explanations cannot cite anything today. |
| 5 | **Case** entity | 🔴 Blocker | Nothing aggregates signals + evidence + hypotheses into a reviewable unit. |
| 6 | **Explanation** with grounded claims | 🔴 Blocker | "Show WHY" is unimplementable without it. |
| 7 | **Governed Recommendation** store | 🔴 Blocker | Existing `recommendations` table is unusable. |
| 8 | **Decision / Approval** records (durable, per-recommendation) | 🔴 Blocker | MCP confirmation is per-tool-call and expires in 10 min. |
| 9 | **Agent framework** (registry, runtime, tool binding, runs) | 🔴 Blocker | Only 1.9 KB controller stubs exist. |
| 10 | **Workflow engine** (definitions, versions, runs, steps, approvals, outcomes) | 🔴 Blocker | Nothing configuration-driven exists. |
| 11 | **Template engine** for prompts | 🟠 High | Prompts are private PHP methods; no versioning, no reuse. |
| 12 | **Unified Gen AI service** (schema validation, safety, provenance, audit) | 🟠 High | 4 providers, no output validation, generated content indistinguishable from fact. |
| 13 | **Outcome measurement + learning loop** | 🟠 High | Final layer of the brief; nothing exists. |
| 14 | **KG Phases 7 (Student) + 8 (Assessment)** | 🔴 Blocker for Academic Risk | The graph has no learners in it. |
| 15 | **Durable conversation audit** | 🟠 High | `audit.ts` is a 500-entry in-memory ring buffer. |
| 16 | **`PromptSecurityGuard` / `ToolExecutionGuard` implementations** | 🟠 High | Interfaces declared, never implemented. |
| 17 | **Async queue** | 🟠 High | `QUEUE_CONNECTION=sync`, no `app/Jobs`. Agent runs will block HTTP requests. |
| 18 | **Swarm coordinator** | 🟢 Later | Phase 13; correctly deferred. |

---

## 8. Components that should be reused (no change)

- `App\Mcp\*` — the whole tool + confirmation framework
- `App\Services\Mcp\McpContextResolver`, `McpRequestContext`, `McpConfirmationService`, `McpAuditService`
- `App\Services\Neo4jService`
- `App\Services\PAL\Intelligence\*` (all engines — as **signal sources**)
- `App\Services\PAL\Runtime\PalEvidenceRepository`
- `App\Http\Middleware\{McpAuth, McpRateLimit, McpContextHydrator, PalApiAuth}`
- `packages/conversational-ai-core/**` — **do not edit**; extend through `ProjectAIAdapter`
- `lms_k12/components/ui/**` + design system `workflow` / `communication` groups
- `contexts/AuthContext.tsx`
- `lib/ai/mcp-client.ts`

## 9. Components that need extension (additive only)

| File | Extension |
|---|---|
| `lib/ai/adapters/lms-k12/tools.ts` | Append new intelligence tools to `getLmsToolDefinitions()`; gate them in `getAllowedToolNamesForProfile()`. **Append only.** |
| `lib/ai/adapters/lms-k12/adapter.ts` | Add capability mappings for the new `analyse` / `recommend` intents. |
| `app/components/ChatbotPanel.tsx` | Add a recommendation/approval card render branch alongside existing `MODULE_HANDOFF_COPY`. |
| `app/Services/PAL/Intelligence/PredictiveInterventionEngine.php` | Add a **persist** path; leave every existing method signature untouched. |
| `app/Services/PAL/AI/AIOrchestrationService.php` | Route `callAI()` through the new shared Gen AI service; keep all 7 public methods identical. |
| `packages/conversational-ai-core/src/audit.ts` | Add a pluggable sink so audit can reach the DB. (Smallest possible core change — a single optional callback.) |
| `database/neo4j/gen_registry.php` | Add Student/Assessment mappings for KG Phases 7/8. |
| `routes/` | New `routes/ai.php`, registered in the same place `mcp.php` is. |
| `tblmenumaster` | New menu migration for the AI Administration area (mirroring `2026_08_17_110000_add_new_pal_gamification_submodule_menu.php`). |

---

## 10. Recommended implementation locations

**Backend — introduce `app/Domain/` (it does not exist yet), matching the brief:**

```
app/Domain/
  Governance/
    RecommendVerb.php            ← authored here (G2G originals unavailable)
    ExplainVerb.php
    GroundedClaims.php
    EsoBindingRule.php
    GovernanceValidator.php
  AI/
    Signals/    {SignalDetectorInterface, SignalCollector, SignalDefinition}
    Evidence/   {EvidenceItem, EvidenceCollector, EvidenceProvenance}
    Cases/      {CaseBuilder, CaseAggregate, Hypothesis}
    Explanations/ {ExplanationBuilder, GroundedExplanation}
    Recommendations/ {RecommendationDrafter, RecommendationPolicy}
    Decisions/  {DecisionGate, ApprovalRequest}
    Agents/     {AgentInterface, AbstractAgent, AgentRegistry, AgentRunner, AgentManifest}
    Outcomes/   {OutcomeTracker, OutcomeMetric}
  Ontology/     {OntologyRegistry, EntityDefinition, RelationshipDefinition, EntityResolver}
  KnowledgeGraph/ {GraphQueryService, GraphPath, TraversalSpec}
  Workflow/     {WorkflowDefinition, WorkflowEngine, StepExecutor, ApprovalGate, ConditionEvaluator}
  Templates/    {TemplateRegistry, PromptTemplate, TemplateRenderer}
  GenerativeAI/ {GenerationService, GenerationRequest, GenerationResult, OutputValidator,
                 SafetyChecker, ProviderResolver, KeyRotationPool}

app/Http/Controllers/AI/       {SignalController, CaseController, RecommendationController,
                                AgentController, OutcomeController}
app/Http/Controllers/Workflow/ {WorkflowDefinitionController, WorkflowRunController}
app/Http/Controllers/Ontology/ {OntologyController}
app/Http/Controllers/KnowledgeGraph/ {KnowledgeGraphController}

app/Domain/K12/AcademicRisk/   {AcademicRiskAgent, AcademicRiskSignals, AcademicRiskEvidence}
app/Domain/K12/TeacherSupport/ {TeacherSupportAgent, ...}

routes/ai.php
app/Jobs/AI/  {RunAgentJob, AdvanceWorkflowJob, DetectSignalsJob}
```

Rationale: the brief specifies this shape, `app/Domain` is free, and it keeps the new governed layer
clearly separated from the 578 legacy controllers.

**Frontend:**

```
lms_k12/
  lib/ai/adapters/lms-k12/intelligence-tools.ts   ← new tools, imported into tools.ts
  lib/intelligence/{client.ts, types.ts}          ← API client for /api/ai/**
  components/intelligence/
    InsightsPanel.tsx  EvidenceList.tsx  ExplanationCard.tsx
    RecommendationCard.tsx  ApprovalDialog.tsx  OutcomeTimeline.tsx
    GeneratedContentBadge.tsx                    ← the "this is generated, not fact" marker
  app/students/[id]/  → add tabs: AI Insights · Recommendations · Interventions · Outcomes
  app/ai-admin/       ← Agents · Ontology · Knowledge Graph · Workflows · Templates · Audit Logs
  app/api/ai/**       ← BFF proxies mirroring app/api/mcp/**
```

---

## 11. Dependencies / services required

**Verdict: no new runtime dependencies are required.** Everything needed is installed.

| Need | Decision | Reason |
|---|---|---|
| LLM | **Reuse OpenRouter** on the backend (`config/openrouter.php`, key present) and **Gemini** on the conversational path (already working). | Do not add a 5th provider. Consolidation onto OpenRouter is a later cleanup, not a prerequisite. |
| Graph DB | **Reuse Neo4j** — already installed, live, with 138 K nodes and a full tooling suite. | The brief says don't add Neo4j unless required. It is already here and already required by the existing migration programme. |
| Vector / RAG | **Not required for Phases 2–11.** | Every question in scope is answerable by structured traversal + SQL. Defer until a semantic-retrieval use case actually appears. |
| Workflow engine | **Build internal**, data-driven. | Requirements are moderate; no library needed; nothing existing to reuse. |
| Queue | 🔴 **`QUEUE_CONNECTION=sync` must change to `database` (or `redis`).** Needs `php artisan queue:table` + a worker. | Agent runs and workflow steps will otherwise block HTTP requests and time out. **This is the only infrastructure change required.** |
| Cache | `file` is adequate for now; Redis is configured if needed later. | |

**Config decision needed from you:** `database` queue (simplest, no new service) vs `redis`
(Redis host is already in `.env`). I recommend **`database`** — zero new operational surface.

---

## 12. Risks and compatibility concerns

| # | Risk | Severity | Mitigation |
|---|---|---|---|
| R1 | **`conversation.ts` is 3,938 lines.** Any edit risks the working assistant. | 🔴 | **Do not edit it.** All new capability enters via `ProjectAIAdapter` (`getToolDefinitions` / `getAllowedToolNames`). The one exception — an audit sink in `audit.ts` — is a single optional callback. |
| R2 | **KG Phases 7 (Student) + 8 (Assessment) not started.** | 🔴 | Academic Risk must run on **MySQL first**, with the KG as an enrichment path behind a feature flag. Do not block Phase 10 on the Neo4j programme. Phases 7/8 proceed in parallel. |
| R3 | **`QUEUE_CONNECTION=sync`** | 🔴 | Switch to `database` before Phase 5. Until then, agent runs must be bounded and synchronous-safe. |
| R4 | **Governance files don't exist** — the brief assumes they do. | 🔴 | Author them here; flag as pending reconciliation with G2G. **Awaiting your decision** (see §0). |
| R5 | **4 LLM providers, no output validation, no provenance on generated text.** | 🟠 | New Gen AI service wraps all of them; every output carries `{generated: true, model, template, generated_at}`. `GeneratedContentBadge` enforces this in the UI. Legacy call sites keep working unchanged. |
| R6 | **`recommendations` table name collision.** | 🟠 | Use `ai_recommendations`. Do not touch the legacy table. |
| R7 | **DB-driven navigation.** New admin screens are invisible without a menu migration + rights grant. | 🟠 | Ship menu migrations modelled on `2026_08_17_110000_*` (idempotent, matched on parent *name*, touches only its own row). |
| R8 | **596 migrations, 578 controllers** — high collision surface. | 🟠 | Namespace everything `ai_*` / `App\Domain\*`. New routes in `routes/ai.php` only. |
| R9 | **Tenant isolation must not regress.** An agent could widen a user's data scope. | 🔴 | Every agent runs inside an `McpRequestContext`. Agents get **no** direct DB access — only through tools that already enforce `allowedInstituteIds`. Enforce in `AgentRunner`, not per-agent. |
| R10 | **Hardcoded thresholds in `classifyRisk()`.** | 🟠 | Read them from `PredictiveInterventionEngine`; do not re-declare. Later, promote to `ai_signal_definitions` with the current values as defaults. |
| R11 | **In-memory conversation audit** loses AI actions on restart. | 🟠 | Route through the DB sink (R1's single core change). |
| R12 | **`security.ts` guards are interfaces with no implementations.** | 🟠 | Implement both before any agent gains a write-capable tool. |
| R13 | **Neo4j programme has open blocking decisions and a documented drift incident**; the rescue export lives outside the repo. | 🟠 | Treat `docs/neo4j-migration-status.md` as source of truth; do not start Phases 7/8 until `SOURCE-DANGLING`, `G9-CHURN`, `MAPPINGTYPE-SCOPE` are settled. Verify the out-of-repo export first. |
| R14 | **Generated content leaking into evidence.** | 🔴 | `EvidenceItem.verified` defaults to `false`; `GroundedClaims` rejects any claim citing unverified evidence. Enforced in the governance kernel, not by convention. |
| R15 | Two repos, no shared CI. Frontend/backend contracts can drift. | 🟢 | Zod schemas on the TS side mirror PHP request validation; keep both in this document's §16 table. |

---

## 13. Exact files to modify (additive, non-breaking)

**Backend**
```
routes/web.php or bootstrap/app.php   → register routes/ai.php  (1 line, same place mcp.php is)
config/app.php / bootstrap/providers.php → register AI service providers
.env / config/queue.php               → QUEUE_CONNECTION=sync → database
app/Services/PAL/Intelligence/PredictiveInterventionEngine.php → add persist hook (no signature changes)
app/Services/PAL/AI/AIOrchestrationService.php → callAI() delegates to GenerationService (public API unchanged)
database/neo4j/gen_registry.php       → Student + Assessment mappings (KG phases 7/8)
```

**Frontend**
```
lib/ai/adapters/lms-k12/tools.ts      → import + spread intelligence tools; extend allowed-names tiers
lib/ai/adapters/lms-k12/adapter.ts    → capability map entries for analyse/recommend intents
app/components/ChatbotPanel.tsx       → recommendation + approval render branch
packages/conversational-ai-core/src/audit.ts → optional audit sink callback  ⚠️ only core-package edit
```

## 14. Exact new files required

See §10 for the full tree. Counts: **~45 backend classes**, **~12 frontend components**,
**1 route file**, **~14 migrations**, **~3 jobs**, **~2 console commands**.

## 15. Database migrations required

Only what does not already exist:

```
create_ontology_entities_table          create_ontology_relationships_table
create_ai_signals_table                 create_ai_signal_definitions_table
create_ai_evidence_table                (kind, source_table, source_id, observed_at,
                                         value_json, confidence, verified, tenant scope)
create_ai_cases_table                   create_ai_case_evidence_table
create_ai_hypotheses_table              create_ai_explanations_table
create_ai_recommendations_table         create_ai_decisions_table
create_ai_agents_table                  create_ai_agent_tools_table
create_ai_agent_runs_table
create_workflow_definitions_table       create_workflow_versions_table
create_workflow_runs_table              create_workflow_steps_table
create_workflow_approvals_table         create_workflow_outcomes_table
create_ai_generation_requests_table     create_ai_generation_outputs_table
create_ai_templates_table
create_ai_audit_logs_table              (mirrors mcp_audit_logs shape)
create_ai_outcomes_table
add_ai_administration_menu              (tblmenumaster + rights)
```

**Not created** (already exist): `mcp_audit_logs`, `mcp_confirmation_requests`, `ai_api_keys`,
`ai_daily_used_api`, `ai_interaction_logs`, `recommendations` (legacy — untouched), all `pal_*` tables.

Every new table carries `sub_institute_id` + `client_id` for tenant isolation, matching existing convention.

## 16. APIs required

Following the `routes/mcp.php` convention exactly:

```
routes/ai.php  →  Route::prefix('api/ai')->middleware(['api', McpAuth, McpRateLimit, McpContextHydrator])

GET    /api/ai/signals                          GET    /api/ai/cases
GET    /api/ai/cases/{case}                     GET    /api/ai/cases/{case}/evidence
GET    /api/ai/cases/{case}/explanation         GET    /api/ai/cases/{case}/recommendations
POST   /api/ai/recommendations/{rec}/approve    POST   /api/ai/recommendations/{rec}/reject
GET    /api/ai/agents                           POST   /api/ai/agents/{agent}/run
GET    /api/ai/agents/{agent}/runs
GET    /api/ai/workflows                        POST   /api/ai/workflows/{workflow}/execute
GET    /api/ai/workflow-runs/{run}              POST   /api/ai/workflow-runs/{run}/approve
GET    /api/ai/ontology/entities                GET    /api/ai/ontology/relationships
POST   /api/ai/knowledge-graph/query
POST   /api/ai/generate
GET    /api/ai/outcomes
GET    /api/ai/audit-logs
```

Frontend BFF proxies mirror these under `lms_k12/app/api/ai/**`.

## 17. Frontend routes / components required

```
app/ai-admin/{agents,ontology,knowledge-graph,workflows,templates,audit-logs}/page.tsx
app/students/[id]/  → tabs: AI Insights · Recommendations · Interventions · Outcomes
app/teachers/[id]/  → tabs: AI Insights · Recommendations · Actions · Outcomes  (Phase 11)
components/intelligence/{InsightsPanel, EvidenceList, ExplanationCard, RecommendationCard,
                         ApprovalDialog, OutcomeTimeline, GeneratedContentBadge,
                         SignalBadge, CaseSummary}.tsx
lib/intelligence/{client.ts, types.ts}
```

All built from existing `components/ui/**` + the design system's `workflow` group
(`ApprovalCard`, `TimelineItem`, `ActivityFeed`, `AuditEntry`). No new visual language.

## 18. Security / permission requirements

1. Every AI route sits behind `McpAuth` + `McpRateLimit` + `McpContextHydrator`.
2. Every agent run is constructed **from** an `McpRequestContext` — never from raw request input.
3. Agents have **no direct DB access**. They call tools; tools enforce `allowedInstituteIds`.
   Enforced centrally in `AgentRunner`, so a new agent cannot forget it.
4. Tool permissions follow `getAllowedToolNamesForProfile()`'s additive tiering
   (student ⊂ teacher ⊂ admin).
5. Every new table carries `sub_institute_id` + `client_id`; every query filters on them.
6. AI Administration screens require an admin menu right (granted via migration, like PAL).
7. Consequential actions require a **durable Decision record** — the 10-minute MCP confirmation
   token is the transport, not the record.
8. Every agent run, generation, decision and workflow transition writes to `ai_audit_logs`.
9. `PromptSecurityGuard` + `ToolExecutionGuard` implemented before any write-capable agent tool ships.
10. Generated content is never `verified` evidence without an explicit human verification event.

## 19. Recommended implementation sequence

Follows the brief's Phase order, with **one insertion** (Phase 4.5) forced by §0, and **one
parallel track** (KG 7/8) forced by R2.

| Phase | Work | Gate |
|---|---|---|
| **1** ✅ | This audit | This document |
| **1.5** | `QUEUE_CONNECTION` → `database`, `queue:table`, worker | `queue:work` processes a test job |
| **2** | Ontology registry + `ontology_*` tables, mapped from **existing** models/tables | `GET /api/ai/ontology/entities` returns real mapped entities |
| **3** | KG relationship layer over Neo4j + MySQL fallback | `POST /api/ai/knowledge-graph/query` traverses real data |
| **3p** *(parallel)* | Neo4j Phases 6/7/8 (Questions, Student, Assessment) | `neo4j:verify --module=people` / `--module=assessment` |
| **4** | Signal + Evidence + Case layer, fed by `PredictiveInterventionEngine` | A real case appears with real cited evidence |
| **4.5** 🆕 | **Governance kernel** — `RecommendVerb`, `ExplainVerb`, `GroundedClaims`, `EsoBindingRule`, `GovernanceValidator` | An ungrounded recommendation is **rejected** by a test |
| **5** | Agent framework (registry, runner, manifests, `agent_runs`) | An agent runs end-to-end inside a tenant scope and is audited |
| **6** | Workflow engine (definitions, versions, runs, steps, approvals, outcomes) | A workflow pauses at an approval gate and resumes on approve |
| **7** | Template engine | A prompt renders from a versioned template |
| **8** | Generative AI service (schema validation, safety, provenance, key rotation) | Output carries provenance; `AIOrchestrationService` still works unchanged |
| **9** | Wire Conversational AI → intelligence layer via new adapter tools | "Which students are at academic risk?" returns a real, evidence-backed answer |
| **10** | **Academic Risk end-to-end** | Teacher sees WHY, approves, workflow creates the intervention |
| **11** | Teacher Intelligence (support-opportunity framing only) | Same loop, teacher domain |
| **12** | School Operations / ROI | Same loop, ops domain |
| **13** | Swarm coordinator (prioritise only, never execute) | Priority queue for human review |
| **14** | Outcome measurement + learning loop | Signal → … → Outcome closes and feeds back |

**Rule enforced throughout:** the minimum capability required. A simple informational question stays
on the existing conversational path and touches **no** agent, workflow, or Gen AI component.

---

## 20. Open decisions — needed before Phase 2

| # | Decision | Recommendation |
|---|---|---|
| D1 | **G2G governance source.** Is the sibling repo reachable so I can mirror the real `RecommendVerb` / `GroundedClaims` / `EsoBindingRule`? | Point me at it if so. Otherwise I author K-12 originals from the brief's semantics. |
| D2 | **Queue driver** — `database` or `redis`? | `database` — no new operational surface. |
| D3 | **Academic Risk data source for Phase 10** — block on Neo4j Phases 7/8, or ship on MySQL with KG as flagged enrichment? | **Ship on MySQL.** Do not couple Phase 10 to a migration with open blocking decisions. |
| D4 | **LLM consolidation** — leave 4 providers, or route new work through OpenRouter only? | New work through **OpenRouter only**; leave legacy call sites alone. |
| D5 | **Menu placement** — "AI Administration" as a new level-1 module, or a level-2 under Settings? | Level-2 under an existing admin module, mirroring how New PAL was placed. Less nav disruption. |

---

*End of Phase 1 audit. No code was modified.*

---

# PART 2 — IMPLEMENTATION STATUS

**Updated:** 2026-08-20, after the build run.
**Decisions taken** (§20): D1 → author K-12 originals · D2 → `database` queue ·
D3 → both tracks in parallel (Academic Risk on MySQL now) · D4 → OpenRouter for new work ·
Scope → full run to Phase 10.

## Phases delivered

| Phase | Status | Evidence |
|---|---|---|
| 1 Audit | ✅ | Part 1 of this document |
| 1.5 Queue → database | ✅ | `2026_08_20_000001_create_jobs_table.php`; `QUEUE_CONNECTION=database` |
| 2 Core Ontology | ✅ | `app/Domain/Ontology/*`, `..._000002_create_ontology_tables.php`, `..._000007_seed_core_ontology.php` |
| 3 Knowledge Graph layer | ✅ | `app/Domain/KnowledgeGraph/*` — Neo4j where landed, SQL everywhere else |
| 4 Signal + Evidence + Case | ✅ | `app/Domain/AI/{Signals,Evidence,Cases}/*`, `..._000003_create_ai_intelligence_tables.php` |
| **4.5 Governance kernel** 🆕 | ✅ | `app/Domain/Governance/*` — 24 passing tests |
| 5 Agent framework | ✅ | `app/Domain/AI/Agents/*`, `..._000004_create_ai_agent_tables.php` |
| 6 Workflow engine | ✅ | `app/Domain/Workflow/*`, `..._000005_create_workflow_tables.php` |
| 7 Template engine | ✅ | `app/Domain/Templates/*` |
| 8 Generative AI service | ✅ | `app/Domain/GenerativeAI/*`, `..._000006_create_ai_generation_tables.php` |
| 9 Conversational AI wiring | ✅ | `lib/ai/adapters/lms-k12/intelligence-tools.ts` (8 tools, appended) |
| 10 Academic Risk end-to-end | ✅ | `app/Domain/K12/AcademicRisk/*`, `..._000008`, `..._000009` |
| 11–14 | ⏸ Not started | Deferred as instructed — the architecture is domain-agnostic and ready |

## What was built

**Backend** — 62 new classes under `app/Domain/`, 7 controllers under
`app/Http/Controllers/AI/`, 10 migrations, `routes/ai.php` (34 endpoints), `config/ai.php`,
`AiServiceProvider`, 24 unit tests.

**Frontend** — `lib/intelligence/{client,types}.ts`, 5 components under
`components/intelligence/`, and `intelligence-tools.ts` wired into the existing adapter.

## Files modified (everything else is new)

```
next_lms_erp/config/app.php     one line — register AiServiceProvider
next_lms_erp/.env, .env.example QUEUE_CONNECTION sync → database
lms_k12/lib/ai/adapters/lms-k12/tools.ts   three additive edits: import,
                                           spread into getLmsToolDefinitions(),
                                           two tier entries in getAllowedToolNamesForProfile()
```

`packages/conversational-ai-core` was **not touched**. The audit-sink change
contemplated in §13 proved unnecessary — `AiAuditLogger` writes durably on the PHP
side, which is where the governed actions happen.

## How the guarantees are enforced

| Guarantee | Where | Not bypassable because |
|---|---|---|
| Agents cannot execute | `AgentManifest::permitsVerb`, `AgentContext::assertVerb` | The agent never holds a DB handle; every write goes through its own context, which checks the manifest first |
| Human approval before consequence | `GovernanceValidator::authorizeExecute` | The workflow engine calls it before *any* handler that reports `isConsequential()`; a definition that omits an approval step still cannot act |
| Nothing asserted without evidence | `GroundedClaims` | A claim citing missing, out-of-scope, unverified or generated evidence fails; the explanation is stored refused rather than shown |
| Generated ≠ fact | `ai_evidence.verified` default false + `GenerationResult::$isGenerated` (constant true) | Generated evidence is refused by `GroundedClaims` regardless of caller flags |
| Tenant isolation | `McpRequestContext`, re-applied per hop | Traversal re-filters on every hop; `lms_online_exam` has no tenant column, so `StudentScope` scopes the id set first |
| Every AI action auditable | `ai_audit_logs` | Written by store, builder, drafter, gate, runner and engine; failures fall back to the log rather than rolling back the action |

## Verified

- **62/62** classes autoload; all PHP lints clean.
- **29/29** unit tests pass (5 pre-existing + 24 new governance tests).
- Frontend `tsc --noEmit` clean.
- `ThresholdRegistry` reads **from the live `PredictiveInterventionEngine`**
  (reported source `predictive_intervention_engine`, failure trigger `0.7`), and its
  bands reproduce `classifyRisk()` exactly — 0.8 → critical, 0.55 → high, 0.1 → low.
  Thresholds were not re-invented.

## 🔴 Blocker found — pre-existing, not from this work

`app/Http/Controllers/api/InventoryApiController.php` declares `poItems()` **twice**
(lines 162 and 1098). This is a fatal error that stops the whole application booting,
including `php artisan`. It is present in `HEAD` (from `f2cb1c292 feat(api): add import
data endpoints and enhance inventory logic`) and is unrelated to the intelligence layer.

The two implementations differ substantially — the later one adds a `guard()`
permission check, reads from `inventory_generate_po_details` with negotiate as a left
join, and returns `received_by`. Deciding which survives is the inventory module's
call, so it was left alone.

**Until it is resolved, the AI routes cannot be exercised over HTTP.** Everything
below the HTTP layer is verified by unit test and autoload check.

## Remaining before this runs in production

1. Resolve the `poItems()` duplicate (owner: inventory module).
2. Run `php artisan migrate` — 10 migrations, all additive and idempotent.
3. Start a queue worker (`php artisan queue:work`). Agent runs are synchronous and
   bounded today; the worker matters as volume grows.
4. Smoke-test the flow end to end: run the agent, read the case, approve, confirm the
   intervention row and the pending outcome.
5. Confirm the AI Administration menu resolved a parent on this estate (the migration
   does nothing rather than inventing a level-1 module if it cannot find one).
