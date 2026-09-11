# AI stack as a consumable service — design and sizing

Companion to tracker item **#13**. Written 2026-09-08 against the code in `d:\lms_k12`.

The item's recommendation — expose the AI stack as a hosted service rather than publishing an npm package — is **correct and unchanged**. Its description of the current state is not, and the corrections change the size of the work considerably.

---

## 1. Four corrections to the item's premise

### 1.1 They are not workspace packages

`packages/conversational-ai-core` and `packages/conversational-mcp-core` contain a `src/` directory and **no `package.json`**. They are not npm workspace packages; they are source directories wired in through tsconfig path aliases:

```jsonc
"@shared/conversational-ai-core":  ["./packages/conversational-ai-core/src/index.ts"],
"@shared/conversational-mcp-core": ["./packages/conversational-mcp-core/src/index.ts"]
```

They compile as part of the one Next.js app. Publishing them to npm is not a shortcut that was rejected — it is not currently possible without first making them packages.

### 1.2 The AI stack is *already* an HTTP API

`app/api/ai/chat/route.ts` is a Next.js route handler. So are `api/ai/field-edit`, `api/voice/synthesize`, `api/voice/transcribe` and `api/conversation/history`.

The stack is already reached over HTTP by `ChatbotPanel`, which POSTs JSON to `/api/ai/chat`. **There is no client-side library to replace with a service.** The service exists; what it lacks is the ability to serve a *second* product.

### 1.3 It is already multi-project by design

`lib/ai/project-resolver.ts`:

```ts
registerProjectAdapters([{ id: lmsK12Adapter.projectId, adapter: lmsK12Adapter }]);
const resolveRegisteredProjectAdapter = createProjectAdapterResolver("lms_k12");

export function resolveProjectAdapter() {
  const projectId = (process.env.AI_PROJECT_ID || "lms_k12").trim().toLowerCase();
  return resolveRegisteredProjectAdapter(projectId);
}
```

The core already supports several products through registered adapters. Only `lms_k12` is registered, and — this is the actual constraint — **the project is chosen from an environment variable**, so one deployment serves exactly one product.

### 1.4 The `g2g` folder is not what it sounds like

`g2g/` in `lms_k12` contains one file: `globals.css`. Nothing under it imports `@shared/conversational-*`. The concern about "a `g2g` folder inside `lms_k12` with shared paths" describes something that is not there.

---

## 2. The real gap

Not packaging, and not architecture. It is this line:

```ts
const projectId = (process.env.AI_PROJECT_ID || "lms_k12")
```

**Project identity is per-deployment, not per-request.** A hosted stack serving both K-12 and G2G has to resolve the adapter from the *caller*, and then has to be able to prove which caller it is talking to. Today the endpoints trust the caller's session for tenancy and take the product from their own environment.

So the work is: make project identity a property of the request, authenticate cross-product callers, and only then decide where it runs.

---

## 3. Sizing

| Phase | Scope | Size | Gate |
|---|---|---|---|
| **A1** | Per-request project resolution — take `projectId` from an authenticated request rather than `process.env`, with the env var as fallback | **M** | none |
| **A2** | Caller authentication for cross-product access — a service credential per consuming product, distinct from a user session | **M** | A1 |
| **A3** | A G2G adapter registered alongside `lmsK12Adapter` | **M** | A1. Owned by whoever owns G2G's domain model |
| **A4** | Deployment — where the stack runs, and who operates it | **decision + infra, not code** | A2 |
| **A5** | Contract versioning, so a change for one product cannot break the other | **S** | A3 |

**A1 is the load-bearing one.** Everything downstream is ordinary work once project identity travels with the request; nothing downstream is possible while it does not.

---

## 4. Open decisions this needs

These are not engineering questions and should not be answered by whoever writes A1.

1. **Where does it run?** Extracted as its own deployable, or kept in the K-12 app with G2G calling across? The second is cheaper and makes K-12's uptime G2G's uptime.
2. **Who operates it?** A shared service needs an owner for on-call, model-cost attribution and capacity.
3. **How are model costs attributed** when two products share one gateway?
4. **What is the compatibility promise?** Whether a change for K-12 may break G2G determines whether A5 is small or large.

---

## 5. What this rejects, and why

- **Publishing to npm.** The item already rejects it on version-skew grounds. The code adds a second reason: they are not packages, so this would be new work to reach a worse answer.
- **A rewrite.** The adapter registry, the route handlers and the request/response schemas are all already service-shaped. This is a change to how one value is resolved, plus the auth and operational work that follows.
- **Treating `g2g/` as evidence of coupling.** One stylesheet is not a shared code path.

---

## 6. Recommendation

Do **A1** now — it is small, it is decision-free, and it is the only thing blocking everything else.

Do **not** start A3 or A4 until the ownership and hosting questions in §4 are answered. Building a G2G adapter against a service with no operator, no cost model and no compatibility promise would repeat the premature-generalisation pattern this tracker has already rejected twice (#22 ESO universalization, #30 Career Intelligence).
