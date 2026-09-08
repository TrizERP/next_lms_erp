# Consuming this MCP server from the Next.js frontend

How `lms_k12` connects to the LMS MCP server so **end users** get tool-backed answers in
the chat panel. The MCP client runs server-side in a Next.js route handler, never in the
browser.

Server-side facts (endpoint, transport, auth, headers, protocol versions) were verified
against this codebase; package APIs were verified against the versions installed in
`lms_k12` (`ai@7.0.62`, `@modelcontextprotocol/sdk@1.29.0`).

---

## Where this fits today

The chat path already reaches these tools, but not over MCP:

```
ChatbotPanel.tsx
  └─ POST /api/ai/chat                     (Next.js route handler)
       └─ streamConversationResponse       (Vercel AI SDK loop, Gemini)
            └─ lmsK12Adapter tools         ← hand-written Zod catalogue
                 └─ callBackendMcpTool()   ← plain fetch
                      └─ POST /api/mcp/tools/call   ← the deprecated REST shim
```

Two consequences worth stating plainly:

1. **The REST shim is the live product path, not legacy dead weight.** Its `Sunset` date
   is the date this integration has to be finished by, not a formality.
2. **There is a second tool catalogue, in TypeScript.**
   `lib/ai/adapters/lms-k12/tools.ts` and `schemas.ts` declare tools by hand — with
   *different names* from the server's (`searchStudents` vs `students.search`,
   `listHomework` vs `homework.list`, `confirmAdmission` vs `admissions.confirm`) and
   hand-maintained Zod schemas. Laravel now has one authoritative registry; the frontend
   has a second one that can drift from it silently, which is the same failure the
   Laravel-side consolidation was for.

Replacing that hand-written catalogue with tools **discovered from the server** is the
point of the work below. The tool list, descriptions and JSON Schemas then come from
`ToolRegistry` and nowhere else.

---

## `ai` v7 has no MCP client

`experimental_createMCPClient` does **not** exist in `ai@7.0.62` — the only MCP-related
export is the rug-pull defence pair `fingerprintTools` / `detectToolDrift`. Guides
written against AI SDK v4/v5 will not apply.

The supported shape on v7 is to drive `@modelcontextprotocol/sdk` directly (already a
dependency at 1.29.0) and convert its `tools/list` output into an AI SDK `ToolSet`.

---

## The endpoint

| | |
| --- | --- |
| **URL** | `POST {LMS_BACKEND_URL}/api/mcp` |
| **Transport** | Streamable HTTP (`StreamableHTTPClientTransport`) |
| **Auth** | `Authorization: Bearer <end user's JWT>` |
| **Tenant** | `X-MCP-Institute-Id: <id>` (optional; validated against the token) |
| **Protocol** | `2025-11-25`, `2025-06-18`, `2025-03-26`, `2024-11-05` |
| **Tools** | 25, in a single `tools/list` page |

---

## The rule that matters most: one client per request

**Create the MCP client inside the request handler, with that request's user token, and
close it when the turn ends.**

A module-level client — the shape most MCP examples show, because they assume a
single-user desktop app — would connect once with whichever user's token arrived first
and then serve every other user under that identity. On a multi-tenant ERP that is a
cross-tenant data leak, and the server cannot catch it: every call would carry a valid
token for a real user, just not the user asking.

The server takes tenant scope from the token and refuses `X-MCP-Institute-Id` outside it,
so a per-request client is scoped correctly by construction.

```ts
// ✗ NEVER — one connection shared by every end user
const client = await createLmsMcpClient(process.env.SERVICE_TOKEN!);

// ✓ per request, per user, closed after
export async function POST(request: Request) {
  const token = request.headers.get("authorization")?.replace(/^Bearer\s+/i, "");
  const session = await createLmsMcpSession({ token });
  try {
    /* ... use session.tools ... */
  } finally {
    await session.close();
  }
}
```

---

## Reference implementation

### `lib/ai/mcp/lms-mcp-session.ts`

```ts
import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import { StreamableHTTPClientTransport } from "@modelcontextprotocol/sdk/client/streamableHttp.js";
import { jsonSchema, dynamicTool, type ToolSet } from "ai";

/** MCP names are dotted; model function names generally are not. */
const toModelName = (mcpName: string) => mcpName.replace(/\./g, "__");
const toMcpName = (modelName: string) => modelName.replace(/__/g, ".");

export type LmsMcpSession = {
  tools: ToolSet;
  /** MCP name -> confirmation token issued this turn, for consequential tools. */
  pendingConfirmations: Map<string, string>;
  close: () => Promise<void>;
};

export async function createLmsMcpSession(opts: {
  token: string;
  instituteId?: string | number;
  signal?: AbortSignal;
}): Promise<LmsMcpSession> {
  const baseUrl = process.env.LMS_BACKEND_URL;
  if (!baseUrl) throw new Error("LMS_BACKEND_URL is not configured.");
  if (!opts.token) throw new Error("An end-user token is required to reach MCP tools.");

  const transport = new StreamableHTTPClientTransport(
    new URL("/api/mcp", baseUrl),
    {
      requestInit: {
        headers: {
          Authorization: `Bearer ${opts.token}`,
          ...(opts.instituteId
            ? { "X-MCP-Institute-Id": String(opts.instituteId) }
            : {}),
        },
      },
    },
  );

  const client = new Client(
    { name: "lms-k12-chat", version: "1.0.0" },
    { capabilities: {} },
  );
  await client.connect(transport);

  const { tools: mcpTools } = await client.listTools();
  const pendingConfirmations = new Map<string, string>();
  const tools: ToolSet = {};

  for (const mcpTool of mcpTools) {
    tools[toModelName(mcpTool.name)] = dynamicTool({
      description: mcpTool.description ?? "",
      // The server's own schema, verbatim — required fields and
      // additionalProperties:false included. Nothing is re-declared here.
      inputSchema: jsonSchema(mcpTool.inputSchema as Record<string, unknown>),
      execute: async (args) => {
        const name = toMcpName(mcpTool.name);
        const result = await client.callTool({
          name: mcpTool.name,
          arguments: args as Record<string, unknown>,
        });

        const payload = (result.structuredContent ?? {}) as {
          result?: {
            mode?: string;
            requires_confirmation?: boolean;
            confirmation?: { token?: string };
            preview?: unknown;
            result?: unknown;
          };
        };
        const envelope = payload.result ?? {};

        // A consequential tool answered with a preview instead of acting. Hold the
        // token, and hand the model the preview so it can tell the user what would
        // happen. Do NOT loop straight back with the token — see below.
        if (envelope.mode === "preview") {
          if (envelope.confirmation?.token) {
            pendingConfirmations.set(name, envelope.confirmation.token);
          }
          return {
            requiresConfirmation: true,
            preview: envelope.preview,
            message:
              "This action needs the user's explicit confirmation before it runs.",
          };
        }

        return envelope.result ?? envelope;
      },
    });
  }

  return {
    tools,
    pendingConfirmations,
    close: async () => {
      try {
        await client.close();
      } catch {
        // A closed transport is not an error worth failing the response over.
      }
    },
  };
}
```

### Using it in the chat route

```ts
import { streamText, stepCountIs } from "ai";
import { google } from "@ai-sdk/google";

export const runtime = "nodejs";

export async function POST(request: Request) {
  const token = request.headers.get("authorization")?.replace(/^Bearer\s+/i, "").trim();
  if (!token) return new Response("Unauthorized", { status: 401 });

  const session = await createLmsMcpSession({ token });

  try {
    const result = streamText({
      model: google("gemini-2.5-flash"),
      system: SYSTEM_PROMPT,
      messages,
      tools: session.tools,
      stopWhen: stepCountIs(8),
    });

    return result.toUIMessageStreamResponse();
  } finally {
    // streamText resolves before the stream drains — await consumption first
    // (e.g. `await result.consumeStream()`) or close in `onFinish` instead.
    await session.close();
  }
}
```

> **Closing too early is the easy bug here.** `streamText` returns before the stream is
> consumed, so a bare `finally { close() }` can tear the transport down mid-turn while
> the model is still calling tools. Close in `onFinish`, or `await
> result.consumeStream()` before closing.

---

## Confirmation must reach a person

`admissions.confirm` creates a student enrolment. The server answers a tokenless call
with a preview and a single-use token, and only acts when that token comes back.

An LLM loop can call twice in a row. **That is not a confirmation** — it is the model
approving its own write. Wire it so the preview ends the turn, the UI renders the
preview with an explicit approve control, and the user's click sends the token on a new
request:

```
turn 1  model → admissions.confirm {enquiry_id}  → preview + token → render, stop
        user clicks "Confirm"
turn 2  route → admissions.confirm {enquiry_id, confirmation_token} → confirmed
```

The token is single-use, expires (default 10 minutes), is bound to the tool, user and
institute it was issued for, and the confirmed call acts on the **previewed** payload —
resending different arguments with the token does not change what happens. A confirmed
response carries `"confirmed": true`.

`admissions.updateEnquiry` also writes but is deliberately not confirmable; keep it out
of the model's tool set unless the surface calling it is itself deterministic.

---

## Pinning the tool definitions

The server sends tool descriptions and schemas that the model then follows. `ai` v7 ships
`fingerprintTools` / `detectToolDrift` for exactly this:

```ts
import { fingerprintTools, detectToolDrift } from "ai";

const current = await fingerprintTools(session.tools);
const drift = detectToolDrift(current, REVIEWED_BASELINE);

if (drift.changed.length || drift.added.length) {
  console.warn("[mcp] tool definitions changed since review", drift);
}
```

Both ends of this connection are yours, so this is a deploy-skew signal more than a
supply-chain one — it tells you the frontend is talking to a backend whose tools moved.
Worth having before the server is ever exposed to a third party.

---

## Environment

```bash
LMS_BACKEND_URL=https://<laravel-host>   # no trailing slash
```

The end user's JWT comes from the incoming request, never from an env var. A service
token in the environment would make every chat turn act as one identity — the singleton
problem again, moved into config.

---

## Migration order

1. Add `lms-mcp-session.ts`; assert `listTools()` returns the 25 server names.
2. Run both catalogues side by side — MCP-discovered tools behind a flag — and diff the
   answers on real questions.
3. Delete the hand-written entries in `tools.ts` / `schemas.ts` as each is covered. The
   names change (`searchStudents` → `students__search`), so prompts and any routing that
   matches on tool names need updating with them.
4. When nothing calls `callBackendMcpTool` any more, `/api/mcp/tools/call` has no
   consumers and the shim can be retired ahead of its `Sunset` date.

Step 3 is the real work: the two catalogues are not a rename apart. The TS tools are
coarser (`getLmsDashboard`, `findStudentFeeRecord`) and some compose several backend
calls, so a few will map to more than one MCP tool or need to stay as local composites
that call MCP tools underneath.

---

## Troubleshooting

| Symptom | Cause |
| --- | --- |
| `401` on connect | No/expired user JWT. The token must come from the request, not config. |
| `422` with `meta.institute_id` | `X-MCP-Institute-Id` names an institute the token does not grant. |
| `429` | 60 requests/minute per user by default (`MCP_RATE_LIMIT_PER_MINUTE`). One chat turn with 8 tool steps is 8 requests. |
| `-32602 Tool [x] not found` | Sent the model-facing name; convert `__` back to `.` before `callTool`. |
| `MCP request context was not established` | Called something other than `POST /api/mcp`. |
| Every user sees one tenant's data | A module-level client. Move it inside the handler. |
| Transport closed mid-turn | Closed before the stream drained — close in `onFinish`. |
| Model invents arguments | The schema was re-declared locally instead of passed through from `listTools()`. |

---

## Tool catalogue

25 tools, 23 read-only. `admissions.confirm` (confirmable, admin-only) and
`admissions.updateEnquiry` are the only writers.

`ai.templates.list`, `ai.templates.render`, `students.search`, `students.directory`,
`students.history`, `teachers.directory`, `teachers.daily_report`, `hr.departments`,
`academics.structure`, `academics.subjects`, `attendance.overview`, `attendance.student`,
`homework.list`, `lms.activities`, `exams.list`, `exams.results`, `fees.arrears`,
`fees.collection_report`, `fees.getPending`, `admissions.today`,
`admissions.listEnquiries`, `admissions.getEnquiryDetails`,
`admissions.validateConfirmation`, `admissions.updateEnquiry`, `admissions.confirm`.

Arguments come from `tools/list`. The published JSON Schema is the same one the tool
validates against, so passing it through to the model is both correct and sufficient.

---

## Appendix: other MCP clients

The server is a standard MCP endpoint, so anything speaking streamable HTTP with a bearer
token works — useful for debugging, not the end-user path.

```bash
curl -sS -X POST "$LMS_BACKEND_URL/api/mcp" \
  -H "Authorization: Bearer $JWT" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}'
```

There is **no stdio server** and cannot be one without changing the security model: tools
read tenant scope from `mcp_context`, hydrated from the JWT on an HTTP request, so
`php artisan mcp:start` would leave every call with no tenant and `handle()` refuses
rather than guessing. Clients that need stdio bridge to the HTTP endpoint themselves.
