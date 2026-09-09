# laravel/mcp — fit evaluation

**Date:** 2026-08-31 · **Status:** installed — `laravel/mcp v0.9.4` is in `composer.lock` and `vendor/` · **Verdict:** compatible; see §6 for what it collided with

Sources: [packagist metadata](https://repo.packagist.org/p2/laravel/mcp.json), [Laravel 12.x MCP docs](https://laravel.com/docs/12.x/mcp), and the installed source in `vendor/laravel/mcp` (v0.9.4). Claims first drafted from the GitHub `master` branch have been re-verified against `vendor/` and corrected where they differed.

---

## 1. Version compatibility — PASS, no upgrade required

Latest stable is **v0.9.4** (2026-08-13). A **v1.0.0-beta.1** landed 2026-08-14.

Requirements (v1.0.0-beta.1, confirmed byte-for-byte; v0.9.4 matches on `php` and all `illuminate/*`):

| Requirement | Constraint | This project | |
|---|---|---|---|
| `php` | `^8.2` | 8.2.12 | ✅ |
| `illuminate/*` (container, console, contracts, http, routing, support, validation) | `^11.45.3\|^12.41.1\|^13.0` | framework **v12.64.0** | ✅ |
| `illuminate/json-schema` | `^12.41.1\|^13.0` | ships with framework 12.64.0 | ✅ |
| `symfony/process` | `^7.4.5\|^8.0.5` | v7.4.13 | ✅ |
| `ext-json`, `ext-mbstring` | `*` | both loaded | ✅ |

**The trap to know about:** the Laravel 12 branch is `^12.41.1`, *not* `^12.0`. Our `composer.json` says `laravel/framework: ^12.0`, so the constraint alone would not have saved us — we pass only because the lock is at **12.64.0**. Anyone resolving this project down to an early 12.x will find `laravel/mcp` uninstallable.

### Install command

`composer.json` has `minimum-stability: dev` with `prefer-stable: true`. Plain `composer require laravel/mcp` therefore resolves to stable **v0.9.4**, not the beta — but do not rely on that inference, pin it:

```shell
composer require laravel/mcp:^0.9.4
```

Do **not** write `^1.0` unless we intend to run beta code: `minimum-stability: dev` in this repo means that constraint would happily install `v1.0.0-beta.1`.

### Stability risk

This is pre-1.0 software on a fast cadence — five releases (v0.9.0 → v0.9.4) in the two months to August 2026. Under semver, 0.x minors may break. With 1.0.0-beta.1 out one day after v0.9.4, a 1.0 is imminent and will likely carry a rename/API sweep. Pin exactly, and treat the upgrade to 1.0 as scheduled work rather than a routine bump.

---

## 2. Transports and JSON-RPC

Two registration modes, in a published `routes/ai.php` (`php artisan vendor:publish --tag=ai-routes`):

```php
Mcp::web('/mcp/weather', WeatherServer::class)->middleware(['throttle:mcp']);  // HTTP POST
Mcp::local('weather', WeatherServer::class);                                   // stdio, via `mcp:start`
```

JSON-RPC framing, schema generation and dispatch are fully handled by the package — we author `Tool`/`Resource`/`Prompt` classes and never touch the wire format. Input schemas are declared fluently and converted to JSON Schema for us:

```php
public function schema(JsonSchema $schema): array
{
    return ['location' => $schema->string()->description('...')->required()];
}
```

**On "streamable HTTP" — read this carefully, it is not the full spec transport.** Returning a `Generator` from `handle()` makes a web server respond over SSE, yielding progress mid-call:

```php
public function handle(Request $request): Generator
{
    yield Response::notification('processing/progress', ['current' => 1, 'total' => 3]);
    yield Response::text($this->forecastFor($location));
}
```

> **Corrected 2026-08-31 after installing.** An earlier draft of this section claimed the HTTP transport was fully stateless with no `MCP-Session-Id`. That was read off the GitHub `master` branch via a lossy summary and is **wrong for the installed v0.9.4**. The verified behaviour follows.

There *is* a session identifier, threaded through but never stored:

- `src/Server/Registrar.php:54` reads `$request->header('MCP-Session-Id')` and constructs the server transport with it.
- `src/Server/Transport/HttpTransport.php:117` echoes `MCP-Session-Id` back on the reply.
- `Laravel\Mcp\Request::sessionId(): ?string` exposes it to tool code, and `src/Events/SessionInitialized.php` fires with it.

What does **not** exist is any server-side session *store*: there is no session manager, repository, or persistence layer anywhere under `src/Server/`. The id is an opaque correlator handed to us per request and nothing more.

So: we get a stable session id we can key our own storage by, plus "one POST may answer as an SSE stream for the duration of that call." We do **not** get session-scoped server memory. The practical consequence for §4 is unchanged — **anything that must survive between two tool calls is ours to persist** — but the correlator makes it cheap to scope that storage per session as well as per user.

---

## 3. Authentication — Sanctum is our path

Middleware on the route, exactly like normal routes. Two supported options:

- **OAuth 2.1 via Passport** — `Mcp::oauthRoutes()` + `->middleware('auth:api')`. Registers discovery and dynamic client registration routes, advertises a single `mcp:use` scope. Laravel recommends this because OAuth 2.1 is what the MCP spec documents and what most clients support. **Custom scopes are not supported** — OAuth acts purely as a translation layer to the authenticatable model.
- **Sanctum** — `->middleware('auth:sanctum')`, clients send `Authorization: Bearer <token>`.
- **Custom** — any middleware that inspects the `Authorization` header.

**Recommendation: Sanctum.** We already run `laravel/sanctum` v4.3.3 and have no Passport install; the docs themselves say that when an app is already on Sanctum, adding Passport is cumbersome and to stay on Sanctum "until you have a clear, necessary requirement to use an MCP client that only supports OAuth." Accept the tradeoff knowingly: some MCP clients speak only OAuth and will not be able to connect.

Authorization inside a tool uses the normal gate stack:

```php
if (! $request->user()->can('read-weather')) {
    return Response::error('Permission denied.');
}
```

`Request::user(?string $guard = null)` is the only identity accessor — there is no connection id or client id.

---

## 4. Preview → confirmation-token → execute — YES, but we build the token ourselves

**This is the headline finding.** The pattern is expressible, but the package offers **no primitive for it**. Verified absences:

- `Laravel\Mcp\Response` has `notification, text, html, view, json, blob, structured, error, make, audio, image, resourceLink, fromStorage` + `content, withMeta, asAssistant, isNotification, isError, role`. **No elicitation, confirmation, or mid-call user-prompting method of any kind.**
- `Laravel\Mcp\Request` (v0.9.4, verified in `vendor/`) exposes `all, get, merge, toArray, validate, user, sessionId, meta, uri, setArguments, setSessionId, setMeta, setUri`. There **is** a `sessionId()` — an earlier draft of this doc wrongly denied it — but it is only a correlator, not storage (§2).
- No server-side session store exists anywhere under `src/Server/` (§2).

So MCP **elicitation** is not available to lean on, and there is no framework-managed place to stash a pending operation. The token must be **persisted by us** (cache or DB) and passed back as an ordinary tool argument. Given the app-side store is mandatory anyway, prefer a DB table over cache — it gives us the audit trail for free.

### Recommended shape: two tools

```php
#[IsReadOnly]
#[Description('Preview the effect of X. Makes no changes.')]
class PreviewXTool extends Tool
{
    public function handle(Request $request): Response
    {
        $diff = $this->planner->plan($request->validate([...]));

        $token = PendingOperation::issue(
            user: $request->user(),
            operation: 'x',
            payloadHash: hash('sha256', json_encode($diff->canonical())),
            expiresAt: now()->addMinutes(5),
        );

        return Response::make(Response::text($diff->humanSummary()))
            ->withStructuredContent([
                'confirmation_token' => $token->value,
                'expires_at' => $token->expires_at->toIso8601String(),
                'changes' => $diff->toArray(),
            ]);
    }
}

#[IsDestructive]
#[Description('Execute a previously previewed X. Requires a confirmation_token from preview-x.')]
class ExecuteXTool extends Tool
{
    public function handle(Request $request): Response
    {
        $token = PendingOperation::claim(          // single-use, atomic
            value: $request->get('confirmation_token'),
            user: $request->user(),
            operation: 'x',
        );

        if (! $token) {
            return Response::error('Invalid, expired, or already-used confirmation token. Run preview-x first.');
        }

        // re-plan and compare against $token->payload_hash before writing
    }
}
```

`Response::structured()` / `withStructuredContent()` is the right carrier for the token: the client gets machine-readable data *and* a JSON-encoded text fallback.

### The security property that actually matters

**Nothing forces a client to call preview first.** Tool choice belongs to the model, and the two calls arrive as unrelated stateless HTTP requests. The guarantee therefore has to be enforced entirely server-side in `claim()`:

1. **Single-use** — mark consumed atomically (`UPDATE ... WHERE consumed_at IS NULL` and check affected rows) so a retried/duplicated call cannot double-execute.
2. **Short TTL** — minutes, not hours.
3. **Bound to the authenticated user** — a token issued to user A must be unusable by user B.
4. **Bound to the exact payload** — store a hash of the previewed change and re-verify at execute time, so the preview cannot be swapped for different arguments.
5. **Unguessable** — 32+ bytes of CSPRNG, compared with `hash_equals`.

Annotate honestly: `#[IsReadOnly]` on preview; `#[IsDestructive]` and **not** `#[IsIdempotent]` on execute. These are advisory hints to the client, not enforcement — several clients surface `IsDestructive` as a human approval prompt, which is a useful second layer but must never be the only one.

`shouldRegister(Request $request): bool` can additionally hide the execute tool from users who lack the permission.

### Testing

There is a first-class harness — the two-step flow is testable end to end without a live client:

```php
$response = WeatherServer::tool(ExecuteXTool::class, ['confirmation_token' => $stale]);
$response->assertHasErrors(['Invalid, expired, or already-used confirmation token.']);
```

Assertions available: `assertOk`, `assertSee`, `assertHasErrors`, `assertHasNoErrors`, `assertName`, `assertTitle`, `assertDescription`. Cover at minimum: token replay, expiry, cross-user reuse, and payload-swap.

---

## 5. Summary

| Question | Answer |
|---|---|
| Supports Laravel 12? | Yes — but `^12.41.1+`, and we pass on 12.64.0 |
| Supports PHP 8.2? | Yes, `^8.2`; we run 8.2.12 |
| JSON-RPC handled for us? | Yes, fully |
| Streamable HTTP? | Partial — SSE within one POST; `MCP-Session-Id` **is** read and echoed, but no server-side session store |
| Auth story? | Passport/OAuth 2.1 (recommended by Laravel) or Sanctum; **use Sanctum** |
| Preview → token → execute? | **Yes, with app-owned token storage — and we already have one (§6).** No elicitation primitive; `sessionId()` is a correlator only |
| Production ready? | Pre-1.0, fast-moving. Pin `^0.9.4`; plan the 1.0 migration |

**Next step:** `composer require laravel/mcp:^0.9.4`, then `vendor:publish --tag=ai-routes`.

---

## 6. Install outcome (2026-08-31) — and what it collided with

Ran `composer require laravel/mcp:^0.9.4` (exit 0, `1 install, 0 updates, 0 removals`, no transitive deps) and `php artisan vendor:publish --tag=ai-routes`.

| | |
|---|---|
| `vendor/laravel/mcp` | installed, **v0.9.4** |
| `composer.json` / `composer.lock` | modified, **uncommitted** |
| `Laravel\Mcp\Server\McpServiceProvider` + `Mcp` facade | auto-discovered ✅ |
| `vendor:publish --tag=ai-routes` | **SKIPPED — `routes/ai.php` already exists** |

### Collision 1 — `routes/ai.php` (real, benign today, will bite later)

`routes/ai.php` is **ours**, tracked since `787ed8d99`, holding the hand-written `api/ai/*` REST surface and loaded by `AiServiceProvider:88`. The publish correctly refused to overwrite it.

But the vendor provider claims that exact path unconditionally:

```php
// vendor/laravel/mcp/src/Server/McpServiceProvider.php:71
protected function registerRoutes(): void
{
    $path = base_path('routes/ai.php');
    if (! file_exists($path)) { return; }
    // ...
    Route::group([], $path);
}
```

So our file is now **executed twice per boot** — once by `AiServiceProvider`, once by the vendor.

**Measured impact: none on the route table.** A boot-and-count showed **48 distinct `api/ai/*` routes, 0 registered more than once** — `RouteCollection` keys by method+URI, so the second pass overwrites rather than appends. The cost is one redundant file execution at boot.

The real problem is ownership, not correctness: the vendor provider now silently loads our application route file, and the moment we publish `Mcp::web(...)` servers into `routes/ai.php` the two concerns are tangled in one file with two loaders. **Recommended fix:** rename ours to `routes/ai_api.php`, update `AiServiceProvider:88`, then let `vendor:publish --tag=ai-routes` create a clean `routes/ai.php` owned solely by laravel/mcp.

### Collision 2 — `config/mcp.php` (benign, worth watching)

Both providers `mergeConfigFrom(..., 'mcp')`:

- vendor → `redirect_domains`, `custom_schemes`, `authorization_server`, `tool_search`
- ours (`App\Providers\McpServiceProvider:89`) → `route_prefix`, `server`, `rate_limit`, `confirmation`

No key overlap, so today the union is harmless. But the `mcp` config namespace is now shared, and a future vendor key colliding with one of ours would merge silently. Consider moving ours to a distinct key.

### The thing that changes the plan: we already built §4

The preview → confirmation-token → execute pattern **already exists in this repo**:

- `app/Services/Mcp/McpConfirmationService.php` — TTL from `config('mcp.confirmation.ttl_minutes')`, plus distinct "invalid" and "already been used" paths
- `app/Http/Requests/Mcp/CallToolRequest.php` — `confirmation_token` validated as a `uuid`
- `app/Http/Controllers/Mcp/ToolsCallController.php` — threads the token into the call
- `app/Domain/AI/Decisions/DecisionGate.php:161` — issues `confirmation_token`
- `app/Domain/AI/Support/AiAuditLogger.php:131` — redacts it from audit output
- `app/Domain/AI/Lifecycle/Stages/LaravelMcpStage.php` — a lifecycle stage already named for this package

**So §4 is not a build order — it is a checklist to audit `McpConfirmationService` against.** Confirm it enforces all five properties, in particular the two most often missed: **binding to the authenticated user**, and **binding to a hash of the previewed payload**. A `uuid` token is also worth revisiting — UUIDv4 is random but is not a CSPRNG secret by contract.

### Open decision

Does laravel/mcp **replace**, **wrap**, or **sit beside** the existing custom stack (`routes/mcp.php`, `App\Providers\McpServiceProvider`, `app/Http/Controllers/Mcp/`, `app/Services/Mcp/`)? Nothing is committed yet, so all three remain open.

### Unrelated pre-existing issues noticed

- `php artisan route:list` **crashes**: `Class "App\Http\Controllers\result\result_admin_permission\ResultAdminPermissionController" does not exist`. Blocks all route inspection; predates this work.
- TCP connect to the remote MySQL (`DB_HOST=202.47.117.220`) measured at **~23 s**, making every artisan command extremely slow. This is why the install took >12 minutes despite only ~27 s of CPU.
- `composer audit` reports **8 advisories across 3 packages**, and `google/proto-client` is abandoned. None relate to laravel/mcp.

## 7. Migration outcome (2026-09-01) — the open decision, resolved: **replace**

All 25 tools now extend `Laravel\Mcp\Server\Tool`. The custom `McpToolInterface`
hierarchy is gone as a *base class*; what survives of it is the two things MCP has no
opinion about.

### What changed

- `App\Mcp\AbstractMcpTool` **extends `Laravel\Mcp\Server\Tool`** and still implements
  `McpToolInterface`. One class is now simultaneously what `ToolRegistry` registers and
  what the MCP server publishes.
- Each tool's `name()` and `description()` became **public**, overriding the defaults
  `Laravel\Mcp\Server\Primitive` derives from the class name. They could not be
  re-declared `abstract` in the base — PHP forbids making an inherited concrete method
  abstract — so `McpToolBindingsTest` is what holds tools to declaring both.
- Our per-tool governance metadata moved from `annotations()` to **`toolAnnotations()`**.
  `annotations()` belongs to the package's `HasAnnotations` trait and is public; keeping
  ours protected under that name is a fatal error, and overloading the name would have
  conflated our risk vocabulary with the protocol's client hints.
- `AbstractMcpTool::handle()` is the official entry point. It reads the institute-scoped
  `mcp_context` the middleware hydrated, strips `confirmation_token` out of the arguments
  before they can reach a service as data, and dispatches through `ToolRegistry`.
- `AbstractMcpTool::toArray()` publishes each tool's **existing hand-written JSON Schema
  verbatim** rather than rebuilding it through `schema(JsonSchema)`. Those schemas carry
  the `required` lists and `additionalProperties: false` that stop a planner inventing
  arguments; re-expressing 25 of them through the fluent builder would be a rewrite of 25
  contracts for no gain. `properties` is normalised to an object so an empty map
  serialises as `{}`, matching what the package does with its own schemas.
- `App\Mcp\Servers\RegistryMcpTool` — the generic adapter from the install step —
  **was deleted**. There is nothing left to adapt.

### Why `ToolRegistry` stayed

The package's `Registrar` registers *servers*, not tools, so it is not a competing tool
registry and there was nothing to migrate into. `ToolRegistry` remains the single source
of truth and now holds the **same instances** the server publishes
(`LmsMcpServer::__construct` is one line: `$this->tools = $registry->tools()`), which
`OfficialMcpServerTest` asserts by object identity rather than by name.

It keeps two jobs MCP does not cover:

1. **The preview → confirmation-token → execute handshake** (§4). Both entry points — the
   official JSON-RPC transport and the compatibility façade — go through
   `ToolRegistry::execute()`, so an external MCP client cannot reach a write tool by a
   path that skips the gate the chat UI goes through.
2. **The in-process API the lifecycle calls.** `McpToolCaller`, `LlmPlanner` and
   `McpToolSelectionStage` ask the registry for definitions and results directly; they do
   not go over HTTP to reach a tool in the same process.

Its constructor now **rejects** anything that is not an `AbstractMcpTool`, which is what
stops a second, unpublishable breed of tool reappearing.

### Surfaces after the migration

| Surface | Path | Tool list |
| --- | --- | --- |
| Official MCP | `POST /api/mcp` (JSON-RPC) | `LmsMcpServer` ← `ToolRegistry` |
| Compatibility façade | `GET /api/mcp/tools`, `POST /api/mcp/tools/call` | `ToolRegistry` |
| Chat lifecycle | `POST /api/ai/chat` → `McpToolCaller` | `ToolRegistry` |

Verified identical, all three: 25 tools, same names, no unresolved module binding.

### Verified end to end

`academics.structure` and `students.directory` were driven through
`Container::call([$tool, 'handle'])` — the same path `Laravel\Mcp\Server\ToolInvoker`
uses — against the live estate, and returned real grade and student rows from MySQL.
Nothing in the migration introduced a static or fixture-backed result.

`tests/Feature/OfficialMcpServerTest.php` covers the rest without touching the database:
every registered tool is a `Laravel\Mcp\Server\Tool`; the server publishes the registry's
own objects; published schemas and read-only hints match every definition; `handle()`
routes through the registry and strips the token; and a consequential tool still previews
instead of acting when no token is supplied.

### The confirmation flow, audited

The port added a new way *into* a tool — `AbstractMcpTool::handle()` — so the flow was
re-audited rather than assumed:

- `ToolRegistry::execute()` is **byte-identical** to its pre-migration version. The
  preview branch, the `consume()` branch and the read-only branch were not touched.
- `AdmissionsConfirmTool` changed by exactly three lines: `name()` and `description()`
  became public, `annotations()` became `toolAnnotations()`. `preview()`,
  `executeConfirmed()`, the extra `isAdmin` gate and the `execute()`-falls-back-to-preview
  safety are unchanged.
- **Every** server-side path to a tool goes through `handle()`. `Laravel\Mcp\Server` maps
  only `tools/call` to a tool-executing method, and both it and the optional `ToolSearch`
  meta-tool funnel through `ToolInvoker::invoke()` → `Container::call([$tool, 'handle'])`.
  `LmsMcpServer` does not register `ToolSearch`. There is no bypass.
- `handle()` lifts `confirmation_token` out of the arguments before they reach a service,
  and a non-string token degrades to the preview branch rather than being coerced.

Write surface today: **23 of 25 tools are read-only.** The only two that write are
`admissions.confirm` (confirmable, plus an admin-only gate) and
`admissions.updateEnquiry` (deliberately not confirmable — see its docblock). There are
no fees write tools; `fees.arrears`, `fees.collection_report` and `fees.getPending` all
read.

`tests/Feature/McpConfirmationFlowTest.php` holds this in place, including a registry-wide
invariant: any tool declaring `read_only: false` that does not implement
`ConfirmableMcpToolInterface` fails the suite unless it is added to an allow-list with a
written reason.

#### Fixed alongside the port — the confirmed branch now returns an envelope

A pre-existing defect, found by writing the tests above and fixed as a deliberate,
separate change rather than in passing.

`ToolRegistry::execute()` returned `['mode' => ..., 'result' => ...]` for a read-only
call and `['mode' => 'preview', ...]` for a preview, but the **confirmed** branch returned
`$tool->executeConfirmed(...)` raw, with no envelope. `McpToolCaller` and `AskService`
both switch on `mode`, so a real admission was recorded in the lifecycle trace as status
**`unknown`** with an empty payload, and `AdmissionsFlow::confirm()` fell back to the
generic *"The admission has been confirmed."* instead of the message, data and `uiAction`
that `AdmissionMcpService::confirm()` actually returned. The write always succeeded; what
was lost was the trace status and the real response.

The confirmed branch now returns:

```php
[
    'mode' => 'execute',
    'requires_confirmation' => false,
    'confirmed' => true,
    'result' => $tool->executeConfirmed($arguments, $context, $confirmation),
]
```

`requires_confirmation` is `false` because it describes *this response* — the confirmation
has already been supplied and consumed, and nothing further is asked of the caller.
`confirmed` is the additive key that separates a confirmed write from a plain read-only
execution; it is absent on read-only calls.

This changes the compatibility façade's response shape on the confirmed-write path.
Nothing consumes it: the frontend reaches tools through `POST /api/ai/chat`, not through
`/api/mcp/tools/call`, and `ChatbotPanel.tsx` contains no MCP references at all.

Two tests guard it — `test_all_three_registry_outcomes_are_enveloped_the_same_way` and
`test_a_confirmed_write_reaches_the_lifecycle_as_completed_with_its_real_payload`, the
latter driving the real `McpToolCaller` and asserting the trace row says `completed`.
Both were confirmed to fail against the pre-fix code before the fix was restored.

### The security stack, audited

`McpAuth` → `McpRateLimit` → `McpContextHydrator` run on **all seven** MCP routes, which
was verified by dumping `gatherMiddleware()` off the registered routes rather than by
reading `routes/mcp.php`. That distinction mattered.

#### Fixed — two verbs the package registers were unguarded

`Mcp::web()` registers **three** routes: a `GET` and a `DELETE` returning the protocol's
405 "use POST" stubs, plus the `POST` that runs the server. It returns only the `POST`
route, so the original

```php
Mcp::web($prefix, LmsMcpServer::class)->middleware($middleware);
```

guarded one verb out of three. `GET /api/mcp` and `DELETE /api/mcp` were reachable with
no authentication, no rate limit and no tenant hydration.

The exposure was small — both handlers are static closures returning an empty 405 body,
so no data, no query and no tool was reachable through them — but they were unthrottled,
which is a free unauthenticated request against the app, and "small" is not the standard
the stack is held to. Registering the server inside a group fixes all three at once:

```php
Route::middleware($middleware)->group(function () {
    Mcp::web('/'.trim(config('mcp.route_prefix', 'api/mcp'), '/'), LmsMcpServer::class);
});
```

Unauthenticated `GET`, `DELETE` and `POST` on `/api/mcp` now all return **401** instead of
405/405/401. The package's own `ReorderJsonAccept` and `AddWwwAuthenticateHeader` still
apply to `POST` only, as intended.

#### Tenant scope comes from the token, never from a payload

The invariant holds, structurally rather than by convention:

- `AbstractMcpTool::handle()` reads the scope from `request()->attributes->get('mcp_context')`
  — the hydrator's output — and **refuses the call** if it is absent. It never builds a
  context, and never reads one out of `$request->all()`.
- **No tool schema accepts a tenant key.** Asserted over all 25 published *and* declared
  schemas against `institute_id`, `sub_institute_id`, `tenant_id`, `client_id`, `user_id`,
  `academic_year` and friends.
- The one legitimate way to select a tenant is a request-level override —
  `X-MCP-Institute-Id`, or `_meta.institute_id` on the official transport — and
  `McpContextResolver::resolveInstituteId()` checks it against the institutes the JWT's
  `sub_institute_id` claim actually grants, throwing before any query runs. With no
  override the scope is the token's first allowed institute.
- The `is_admin === 2` super-admin bypass on that check is pre-existing and unchanged: it
  is the only path that reaches an institute outside the token's own list.
- `McpRateLimit` keys on `mcp_auth.user_id` set by `McpAuth`, falling back to IP — never
  on anything in the payload.

`tests/Feature/McpSecurityStackTest.php` holds all of it, including a route-level sweep
that fails if any future `api/mcp*` route is registered without the three middlewares.
Every guard in it was confirmed to fail against the pre-fix routes before the fix was
restored.

#### Fixed — the selected institute is now always inside the allowed set

Pre-existing, found while auditing the `is_admin === 2` bypass, and unrelated to the port.

`McpContextResolver` built `allowedInstituteIds` from the JWT's `sub_institute_id` claim
and `selectedInstituteId` from the request override. The super-admin bypass is the one
path where the second can fall outside the first, and nothing reconciled them — so the
context could describe two different tenants at once:

| Consumer | Clause | Result for a super admin selecting 999 with a claim of 7,8 |
| --- | --- | --- |
| MCP tool services | `where(selected)` | institute 999's data |
| `StudentScope`, `EntityResolver`, `GraphQueryService` | `where(selected)` + `whereIn(allowed)` | **zero rows** — the clauses contradict |

No data was ever exposed: the allowed set is a *narrowing* intersection, so the failure
direction is always closed, and every filter pins to the single selected institute
regardless. What it produced was a silently empty academic-risk scan for a super admin who
was legitimately entitled to the institute — no error, just nothing.

`resolveInstituteScope()` now returns the pair together and folds the selection into the
allowed set when the bypass fires. This grants no authority the bypass has not already
granted — a super admin permitted to select an institute is by definition permitted to
read it — and an ordinary caller is unaffected, because for them the resolver has already
guaranteed the selection came from the allowed set or threw.

The bypass itself was left alone. It is the platform-wide convention (`PALAPIController`,
`PalArchitectureController`, `NewPalContentModelController`, `CoherenceMapController`,
`RolePermissionsController`, `RequiresTalentAdmin` all apply the same rule) and `is_admin`
is a signed JWT claim, not a request field, so it cannot be spoofed without forging the
token. MCP mirrors the platform rule rather than loosening it.

### The REST shim, and what testing both transports found

`/api/mcp/*` (health, initialize, tools, tools/call) stays as a **compatibility shim** so
the frontend is not blocked on the JSON-RPC migration. It is not a second API: every route
delegates to the same `App\Mcp\ToolRegistry` the official server publishes, which
`test_rest_and_json_rpc_return_the_same_tool_result` proves by binding one stub registry
and asserting both surfaces return the identical payload.

`App\Http\Middleware\McpRestDeprecation` marks every shim response — including errors —
with `Deprecation: true`, `Sunset` (RFC 8594 HTTP-date), `Link` carrying
`rel="successor-version"` and `rel="deprecation"`, and a human-readable `Warning`. The
official endpoint is left unmarked.

The removal date is configuration, not code: **`MCP_REST_SUNSET`, defaulting to
2027-03-01** (six months out). Moving it is a product decision. A test fails if the
advertised date has already passed, so an expired shim cannot sit there quietly.

#### Fixed — tools/list published only 15 of the 25 tools

Found by asserting the two transports agree. `Laravel\Mcp\Server::$defaultPaginationLength`
is **15**, and the catalogue is **25**. A client that called `tools/list` once and stopped
— which is most of them, and was this application's own façade behaviour — got fifteen
tools and a `nextCursor`, silently losing **every `admissions.*` and `fees.*` tool** plus
`exams.results`. No error, just a shorter catalogue: a planner would simply never propose
the tools it could not see.

`LmsMcpServer` now sizes the page to the catalogue
(`max($this->defaultPaginationLength, count($this->tools))`), so there is never a second
page to miss. Cursor pagination still works for clients that follow it, and the test
helper follows cursors anyway so the assertion survives pagination coming back.

#### Fixed — the deprecation middleware was a no-op

It guarded with `method_exists($response, 'headers')`. On Symfony's `Response`, `headers`
is a **property**, not a method, so the guard was always false and the middleware returned
without setting a single header. Now checked with `instanceof SymfonyResponse`.

#### What the transport tests cover

`tests/Feature/McpToolBindingsTest.php` gained the handshake, `tools/list`, `tools/call`,
the full preview → token → execute round trip over JSON-RPC, an unknown-tool refusal,
cross-tenant refusal, rate-limit rejection with `Retry-After`, both REST/JSON-RPC parity
assertions, and the deprecation headers.

They mint a **real JWT**, so `McpAuth` is genuinely exercised rather than bypassed, then
substitute only the two collaborators that would otherwise query the estate — the context
resolver and, where a tool actually runs, the registry. The file therefore still holds its
original promise of passing on a machine that cannot reach the database. `McpAuditService`
is stubbed to a no-op so tests never append rows to the estate's audit table.

### Protocol version, reconciled

`config/mcp.php` hard-coded `protocol_version => '2025-06-18'`. That was wrong in two
ways at once: the package supports **four** versions and negotiates down from
**2025-11-25**, and nothing on the official path read the setting at all — only the REST
shim's `/health` and `/initialize` did. So the shim advertised a version the real
endpoint had already moved past, and the config looked authoritative while being
decorative.

The package is now the source of truth. `config/mcp.php` derives the list from
`Laravel\Mcp\Enums\ProtocolVersion::supported()`; `MCP_PROTOCOL_VERSIONS` can only
**narrow** it, because a protocol version is a promise about wire behaviour and config
must not be able to make one the code cannot keep. `LmsMcpServer` sets
`$supportedProtocolVersion` from that list, and both `/health` and `/initialize` report
it, so the two surfaces cannot drift again.

- `mcp.server.protocol_versions` — every version offered, preferred first.
- `mcp.server.protocol_version` — the first of those; what a client gets when it states
  no preference. Kept for the REST shim's existing response shape.

Three tests hold it: the configured list equals what the server offers, the handshake
negotiates an older supported version and refuses an unknown one with the supported list
attached, and the shim reports the same version as the official endpoint.

### Consuming the server from the frontend

See **[mcp-client-setup.md](mcp-client-setup.md)** — the Vercel AI SDK integration for
`lms_k12`, so end users reach these tools through the chat panel.

Two findings from that side belong in this record, because they change what the Laravel
work means:

**The REST shim is the live product path.** `ChatbotPanel` → `POST /api/ai/chat` runs its
own Vercel AI SDK loop (Gemini), whose adapter tools call `callBackendMcpTool()` →
`POST /api/mcp/tools/call`. So the shim is not legacy dead weight kept out of politeness;
it is what the product runs on today, and its `Sunset` is a real deadline for the
frontend migration rather than a formality.

**The second registry survived — in TypeScript.** `lib/ai/adapters/lms-k12/tools.ts` and
`schemas.ts` hand-declare a tool catalogue with different names from the server's
(`searchStudents` vs `students.search`, `confirmAdmission` vs `admissions.confirm`) and
hand-written Zod schemas. The Laravel consolidation gave the backend one authoritative
registry; the frontend still holds a second one that can drift from it silently. Pointing
an MCP client at `tools/list` — so descriptions and schemas come from `ToolRegistry` and
nowhere else — is what finishes the job the Laravel work started.

Two constraints that the server enforces and the client must respect:

- **One MCP client per request, per user.** Tenant scope comes from the caller's JWT, so a
  module-level client would serve every end user under whichever token connected first.
  The server cannot detect this: every call would carry a valid token, just not the
  asking user's.
- **The confirmation handshake is not a human gate on its own.** An LLM loop can call
  `admissions.confirm` twice in a row and approve its own write. The preview must end the
  turn and the token must be spent on a new request the user initiated.

Also worth recording for whoever picks this up: **`ai` v7 removed the MCP client.**
`experimental_createMCPClient` does not exist in `ai@7.0.62`; the integration drives
`@modelcontextprotocol/sdk` directly. What v7 does add is `fingerprintTools` /
`detectToolDrift`, for pinning server-sent tool definitions against a reviewed baseline.

**There is no stdio server, and cannot be one without changing the security model.**
`php artisan mcp:start` and `Mcp::local()` would leave `AbstractMcpTool::handle()` with no
`mcp_context`, because tenant scope is hydrated from the JWT on an HTTP request. A stdio
server would need a second, config-file source of institute scope — exactly the "scope
from somewhere other than the token" the design rules out.

## 8. Single model path (2026-09-02)

The AI brain now depends on `App\Domain\AI\Support\ModelClient`, an interface with two
implementations — `GeminiClient` (default) and `OpenRouterClient` (rollback). Provider
selection is one env var, `AI_PROVIDER`, read in exactly one place in `AiServiceProvider`.

**`GeminiClient` speaks Google's REST API directly.** No wrapper package: the surface used
is one endpoint and four request fields, the estate already talks to OpenRouter this way,
and a dependency whose only job is to build a JSON body is one more thing to keep patched.

The shape conversion is the real work and is not cosmetic. Gemini has no `system` role and
no flat `messages` array — instructions go in `systemInstruction`, turns go in `contents`
with `parts`, and the assistant role is called `model`. Multiple system messages are
concatenated rather than the first winning, because a caller that sets a persona and then
appends a constraint would otherwise silently lose the constraint.

### Config, collapsed

`config/ai.php` gained a `provider` block that is now the **single source** of keys,
endpoints, models and timeouts. The three old files remain as thin shims that derive from
it, so the eight existing readers across PAL and question generation keep working:

| File | What it is now |
| --- | --- |
| `config/gemini.php` | 4-line shim over `ai.provider.gemini` |
| `config/openrouter.php` | Shim over `ai.provider.openrouter`; PAL still reads it |
| `config/deepseek.php` | Credentials derive from `ai.provider.deepseek`; keeps its own prompt versions, temperatures and batch sizes |

`deepseek.php` was deliberately **not** emptied. Its `prompt_version`,
`answer_envelope_version`, `temperature_mcq` and batch sizes are question-generation domain
settings that happen to reach a model. Folding them into a provider block would have made
that block a dumping ground and left nowhere sensible for the next provider.

### Four call sites migrated

`GeneralAnswerService`, `LlmPlanner`, `GenerationService` and `AdmissionsFlow` all
hard-coded `deepseek/deepseek-chat` — a model string that means nothing to Gemini. Each now
passes `null` (the driver's own default) or reads `$client->defaultModel()` where the model
name is recorded in a trace. Verified live on Gemini: general knowledge, arithmetic,
multilingual and JSON planning all work, and ERP questions still answer from the database
at depth 11.

### Findings from wiring it up

**The key pool holds one dead Gemini key and one live one.** `ai_api_keys` has two active
`gemini` rows: id=1 answers `400 API_KEY_INVALID`, id=2 answers `200`. The platform's
`getAIKey()` helper is `where(...)->first()` with **no ordering**, so it is a coin flip
which one a caller gets — and on most engines it returns id=1, taking the brain down while
a working key sits beside it. `GeminiClient` therefore queries the pool directly, newest
active row first, rather than using that helper. **The helper itself is unfixed and still
used by `OpenRouterClient` and others** — worth a separate look.

**`api_type` is `gemini`, lowercase**, not `GEMINI_API_KEY`. Guessing the latter by
symmetry with OpenRouter finds nothing and degrades the brain silently.

**`api_limit` on the Gemini rows is 26, and is not a token budget.** `OpenRouterClient`
uses that column as its output-token ceiling. Verified live: at `maxOutputTokens: 20`,
gemini-2.5-flash answers HTTP 200 with an *empty* candidate, because it spends output
tokens on reasoning before emitting text. `GeminiClient` ignores `api_limit` and uses the
configured `max_output_tokens` instead. Given the 429 below, 26 looks like a daily request
cap.

**gemini-2.5-flash returns transient 503s.** Observed twice. Without a retry that lands as
a null plan and the lifecycle drops silently to its deterministic planner — the turn still
answers, just less well, which nobody reports as a bug. `GeminiClient` retries 3× on
429/500/502/503/504 only; a 400 or 401 still surfaces.

### Blocker before deploy

**The available Gemini key is quota-limited.** Six rapid calls exhausted it:

```
429 You exceeded your current quota, please check your plan and billing details.
```

The code is complete and the default is `AI_PROVIDER=gemini` as intended, but **a billed
Gemini key must be in place before this ships**, or the brain will 429 under real traffic.
`AI_PROVIDER=openrouter` is the rollback and was verified working end to end.

One quality question is also open rather than answered: the Gemini planner produces
different tool arguments run to run, and in one sample matched fewer rows than DeepSeek
did for the same question ("200 students." vs "No students matched."). That is planner
tuning, not a transport fault, and it needs a fixed question set measured across both
drivers before the cutover.

### Frontend

`packages/conversational-ai-core/src/model.ts` **already had no OpenRouter fallback** — it
resolves `GOOGLE_GENERATIVE_AI_API_KEY`, falls back to `GEMINI_API_KEY`, and throws
otherwise. Nothing to retire there.

The `OPENROUTER_API_KEY` fallback that does exist is in
`app/api/screenCandidate/route.ts` — a recruitment candidate-screening route ported as-is
from another product, with its own env vars and its own DeepSeek → OpenRouter → Gemini
chain. It is not on the brain's path, and collapsing its provider chain is a separate
decision about that feature rather than part of this consolidation.

### Driver comparison — measured

A fixed six-question set (four ERP, two general) run through the full lifecycle on both
drivers, paced to respect the free-tier rate limit so the sweep measures the planner
rather than the limiter.

| Question | OpenRouter | Gemini | Agree |
| --- | --- | --- | --- |
| How many students are in Standard-10? | 200 students. | 50 students. | tools yes, **count no** |
| Which teachers teach Standard-10 A? | 24 teachers. | 24 teachers. | yes |
| Who are the fee defaulters? | No students with arrears matched. | same | yes |
| What courses are published for Standard 9? | 22 courses. | 22 courses. | yes |
| What is the capital of Australia? | Canberra. | Canberra. | yes |
| What is 12 times 7? | 84. | 84. | yes |

Identical tool selection and identical depth on all six. Latency is comparable — Gemini
was faster on three of four ERP turns.

**My earlier "the Gemini planner produces worse arguments" note was wrong.** That was the
transient 503 degrading one run to the deterministic planner, not a systematic difference.
Corrected here rather than left standing.

#### The sweep found two real defects

**Both new tools were unreachable from the planner.** `academics.class_teachers` and
`lms.courses` accepted only ids. A planner fills a schema from the user's sentence — "who
teaches Standard-10 A" gives it names — so both answered "no match" for classes that
plainly have data, on *both* drivers. `students.directory` had answered correctly all
along because it accepts `standard_name`/`division_name`.

Both now accept names, resolve them against the institute, and return
`unresolved_filters` rather than silently widening to the whole school when a name does
not exist. After the fix the same questions answer **24 teachers** and **22 courses** on
both drivers.

This is a good argument for keeping a name-or-id pair on every cohort tool: an id-only
tool is reachable by a human who already ran `academics.structure`, and effectively
invisible to a planner.

**`students.directory` reports its page size as a count.** This is the one remaining
disagreement above, and it is pre-existing rather than driver-related. `limit` defaults to
50 and caps at 200; `count` is the number of rows returned, and the answer composer turns
that into "N students". OpenRouter's plan happened to pass `limit: 200` and got "200
students"; Gemini's passed nothing and got "50 students". **The true cohort size is 200,
so "50 students" is a wrong answer to a common question**, and which answer you get depends
on what the planner felt like sending.

The fix is a real total alongside the returned page — a `COUNT(*)` before the limit is
applied, reported separately from the rows — so the headline stops depending on
pagination. Applied on 2026-09-03: `count` is now the full cohort total and
`returned_count` is the size of the bounded `students` page.

## 9. SSE streaming on /api/ai/ask/stream (2026-09-02)

A lifecycle turn runs twelve stages and takes seconds. Non-streaming, the user watches a
spinner with no idea whether anything is happening — while the stages, the most
informative thing the platform knows, were being withheld until the end purely as an
artefact of returning one JSON body.

`POST /api/ai/ask/stream` emits four event types:

| Event | Payload | When |
| --- | --- | --- |
| `stage` | One `TraceStage` — the same element `trace[]` carries | As each stage settles |
| `token` | `{delta}` | Where the answer is written by a model |
| `done` | The complete result, identical to the JSON route's `data` | Once, at the end |
| `error` | `{message, code}` | On failure |

`done` carrying the whole payload is deliberate: streaming is an enhancement, not a
second contract. A client that cannot parse SSE incrementally, or that drops events, can
wait for `done` and be exactly as correct as a caller of `/api/ai/ask`.

**`POST /api/ai/ask` is untouched** — same JSON, same shape — as are `interpret`,
`intents`, `modules` and every test.

### The trace contract is preserved by construction

A streamed stage is `StageOutcome::toArray($key)` — the *same call* `LifecycleTrace::toArray()`
makes. It is not a parallel serialiser that has to be kept in step. Verified end to end
against the live estate: field-for-field identical to the stored trace, carrying all
twelve keys `LifecycleTrace.tsx` reads (`key`, `order`, `layer`, `status`, `summary`,
`component`, `surface`, `data`, `records`, `verify`, `duration_ms`, `note`).

`tests/Unit/LifecycleStreamTest.php` holds it: parity against the stored trace, every
frontend field present, stages after a halt still announced as `not_reached` (a client
draws twelve rows — silence would leave them spinning), an observer that throws not
taking the turn down, and the pipeline still working with no observer at all.

### Measured

| Question | Time to first stage | Total | Tokens |
| --- | --- | --- | --- |
| What is the capital of Australia? | **477ms** | 2587ms | 2 |
| How many students are in Standard-10? | **71ms** | 6503ms | 0 |

The ERP turn now shows progress at 71ms instead of 6.5s — a 90× improvement in
time-to-first-feedback, and the honest one, because it is the twelve stages that take the
time.

### Two things worth knowing

**Token deltas only exist where a model writes the text.** ERP answers are composed from
rows, so those turns emit zero `token` events and their text arrives with `done`. That is
correct rather than a gap — there is no token stream behind a `COUNT(*)`.

**Streaming does not fix time-to-first-token, because the model thinks first.** Measured
on Gemini: first token at ~7-10s, essentially the whole turn. Stage events are where the
perceived-latency win actually comes from.

**Gzip silently defeats streaming.** Measured: with default content negotiation, all 12 SSE
events arrived in a single batch at the end — cURL must buffer the whole body to
decompress it. `GeminiClient::stream()` sends `Accept-Encoding: identity` and
`decode_content => false`, which restored progressive delivery. The endpoint also sets
`X-Accel-Buffering: no`, because nginx buffers proxied responses by default and would
undo the same thing at the edge.

Both drivers stream: `OpenRouterClient::stream()` reads OpenAI-shaped SSE, so a rollback
does not silently stop streaming and look like a frontend bug. Both fall back to a single
non-streaming call if the transport cannot hold a long-lived body, emitting one delta —
degraded delivery, never a degraded answer.

## 10. Audit parity (2026-09-02)

**`mcp_audit_logs` held zero rows on the live estate.** Not a partial gap — nothing had
ever been audited in practice. The table was written from exactly one place,
`McpController::audit()` on the deprecated REST façade, so:

- a lifecycle turn wrote no row even when it called four tools, because it reaches
  `ToolRegistry` in-process rather than over HTTP;
- a turn that answered without a tool at all — small talk, a general answer, a refusal —
  wrote nothing anywhere.

The second is the one that matters. "What did this user ask, and what did the system tell
them" has to be answerable for every turn, not only the ones that happened to touch a
database.

### Two writers, one row each

**`App\Domain\AI\Conversation\AskAuditor`**, called from `AskPipeline::ask()` — the single
chokepoint both pipelines and `php artisan ai:journey` pass through. One row per turn,
including failures: the `catch` audits and rethrows, because a failed turn is the one most
worth having in the trail and the one a happy-path-only audit silently drops.

**`McpToolCaller::record()`** — the six `recordToolCall()` sites were routed through one
method that writes the trace *and* the audit row, so a seventh call site cannot get only
half of it. Lifecycle tool calls now leave the same evidence the REST façade left.

Tool calls were never actually lost — they are on the turn's trace in
`ai_conversation_turns` — but a compliance query looks in the audit table, and having to
know which of two places to read is how an audit trail stops being used.

### `answer_source`, because stage counts lie

The first cut read `stage_counts.blocked > 0` to decide `refused`, and marked **"what is
the capital of Australia" as a refusal** — it had been answered perfectly well. A general
answer and a real refusal both leave planning blocked; the counts cannot tell them apart.

`LifecycleAskService` now records where the answer came from — `stages`, `general` or
`fallback` — and returns it on the result. Only `fallback` (every stage ran, nothing had
anything to say) is a refusal. `refused` stays distinct from `error` because being unable
to answer is the system working, which is the same distinction the Next.js audit drew with
its separate `conversation.refused_no_data` event.

The legacy pipeline reports no source and still falls back to stage counts, which is the
best signal available there.

### Verified live

Two turns, four rows:

| endpoint | tool_name | outcome | |
| --- | --- | --- | --- |
| `lifecycle:mcp_tool_caller` | `academics.structure` | success | tool call |
| `lifecycle:mcp_tool_caller` | `students.directory` | success | tool call |
| `ai:journey` | `academics.structure,students.directory` | success | the turn |
| `ai:journey` | — | success | **the tool-less turn** |

### On "don't lose the Next.js audit"

Worth being plain about what it was. `packages/conversational-ai-core/src/audit.ts` keeps
a 500-entry in-memory array plus a `console.log` — lost on every restart, not per-tenant,
not queryable. Its event vocabulary is worth keeping (`conversation.request`,
`conversation.response`, `conversation.refused_no_data`, `tool.execution`) and is what the
`outcome` and `answer_source` fields above encode. Parity with the storage was a low bar;
durable and institute-scoped is the actual goal.

### Not covered

**The official MCP JSON-RPC endpoint still writes no audit row.** `AbstractMcpTool::handle()`
goes straight to `ToolRegistry::execute()`, so an external MCP client's tool calls leave no
trace in `mcp_audit_logs` — the same gap the lifecycle had until now. Nothing consumes that
endpoint yet, but it should be closed before a third party does. The natural fix is to move
tool-call auditing into `ToolRegistry::execute()` itself, which every transport passes
through; that also means retiring the façade's own row to avoid double-logging, so it is a
deliberate change rather than an addition.

`tests/Unit/AskAuditTest.php` — 9 tests, no database.
