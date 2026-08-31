# laravel/mcp — fit evaluation

**Date:** 2026-08-31 · **Status:** research only, `composer require` NOT yet run · **Verdict:** compatible, adopt at `^0.9.4` with eyes open

Sources: [packagist metadata](https://repo.packagist.org/p2/laravel/mcp.json), [Laravel 12.x MCP docs](https://laravel.com/docs/12.x/mcp), and package source on `laravel/mcp@master`.

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

But `src/Server/Transport/HttpTransport.php` is **stateless per request**: it does not branch on HTTP verb (no GET listening stream), does not read or emit an `Mcp-Session-Id` header, and keeps no cross-request state — the instance is discarded when `run()` returns. Its only response headers are `Content-Type` and `X-Accel-Buffering`.

So what we get is *"one POST may answer as an SSE stream for the duration of that call"*, **not** the MCP Streamable HTTP session model (resumable streams, server-initiated messages, session-scoped memory). **Every tool call is an independent, stateless HTTP request.** That single fact drives §4.

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
- `Laravel\Mcp\Request` exposes `all, get, merge, toArray, validate, user, meta, uri, setArguments, setMeta, setUri`. **No session, session id, connection id, or per-client state.**
- The HTTP transport is stateless (§2).

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
| Streamable HTTP? | Partial — SSE within one POST; stateless, no `Mcp-Session-Id`, no GET stream |
| Auth story? | Passport/OAuth 2.1 (recommended by Laravel) or Sanctum; **use Sanctum** |
| Preview → token → execute? | **Yes, with app-owned token storage.** No elicitation or session primitive exists |
| Production ready? | Pre-1.0, fast-moving. Pin `^0.9.4`; plan the 1.0 migration |

**Next step:** `composer require laravel/mcp:^0.9.4`, then `vendor:publish --tag=ai-routes`.
