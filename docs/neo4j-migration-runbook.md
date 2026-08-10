# Neo4j Migration Runbook — phased, resumable, safe

**Companion to:** [`neo4j-migration-status.md`](./neo4j-migration-status.md) (progress tracker) ·
[`neo4j-full-erp-graph-master-prompt.md`](./neo4j-full-erp-graph-master-prompt.md) (the rules) ·
[`neo4j-graph-audit-and-rebuild-prompt.md`](./neo4j-graph-audit-and-rebuild-prompt.md) (the defects)

---

## 1. The one idea that makes this work

> **The conversation is not the state. The repository is the state.**

If you try to hold the migration in your head — or in one long Claude Code session — then a session
limit, a crash or a closed laptop loses everything. So instead: **every phase ends by writing a file
to disk and committing it.** The next session reads those files and picks up exactly where you left
off. It does not need to remember anything.

This means a lost session costs you *at most one unfinished phase*, never the whole migration.

Three files carry all the state:

| File | Role |
|---|---|
| `docs/neo4j-migration-status.md` | **The resume point.** What's done, what's next, what's blocked |
| `config/neo4j_graph.php` | The registry — how all 487 tables map to nodes and relationships |
| `docs/neo4j-table-classification.md` | The reviewed decision for every table |

Plus generated artifacts: `storage/app/neo4j/<module>/*.csv` and the four artisan commands.

---

## 2. Why phases, and why this order

A module can only load **after everything it points at already exists**. Load questions before
chapters and you recreate defect D9 — 7,674 questions pointing at chapters that were never created.
So the order is a dependency order, not a preference.

```
0  Freeze & backup          ← protect the irreplaceable
1  Classify 487 tables      ← HUMAN REVIEW GATE
2  Registry + tooling       ← build the machine
3  Wipe & schema reset      ← one destructive step, once
─────────────────────────────
4  Foundation      Institute, AcademicYear, Standard, Subject, Division
5  Curriculum      Chapter, Topic, Concept, Content        ← needs 4
6  Questions       Question + mappings                     ← needs 5
7  People          Student (person), Enrollment            ← needs 4
8  Assessment      Assessment, attempts, mastery           ← needs 6 + 7
9  Result                                                  ← needs 8
10 Staff / HR                                              ← needs 4
11 Finance         (blocked on the security decision)      ← needs 7
12 Operations      library, transport, hostel, inventory…  ← needs 7 + 10
13 Skill / Career  + O*NET                                 ← needs 5
14 Platform / misc + PAL stubs
─────────────────────────────
15 Live sync
16 Cleanup — delete the legacy writers
```

**One phase per session.** Do not try to do two. Phases 4–14 are each roughly one module group and
comfortably fit in a single session's budget. If a phase feels too big (Finance is 34 tables), split
it and add a row to the checklist — that is expected, not a failure.

---

## 3. The universal session prompt

Paste **this exact text** at the start of every migration session. It is the same every time — you
never have to remember where you were, because the file remembers for you.

````text
Read docs/neo4j-migration-status.md first. It is the source of truth for what is done and what
is next — trust it over anything you infer.

Then read, in this order:
  docs/neo4j-full-erp-graph-master-prompt.md      (conventions, platform limits, the rules)
  docs/neo4j-graph-audit-and-rebuild-prompt.md    (defects D1-D10 that must not reappear)
  docs/neo4j-table-classification.md              (if it exists yet)
  config/neo4j_graph.php                          (if it exists yet)

Execute ONLY the next unchecked phase in the STATUS checklist. Do not skip ahead. Do not start
a second phase even if the first finishes early — stop and report instead.

Before you begin, confirm back to me in 3 lines:
  - which phase you are about to run
  - what it will change (files, and whether it writes to Neo4j)
  - which blocking decisions in STATUS §3 are still OPEN and whether they affect this phase

When the phase is done:
  1. Run its verification gate and paste the ACTUAL output. Never summarise it as "passing".
  2. If the gate fails, STOP. Do not proceed to the next phase. Report the failure.
  3. If it passes: update docs/neo4j-migration-status.md — tick the phase, set "Current phase",
     set "Graph state", and append one row to the §2 session log.
  4. git add -A && git commit with message: "neo4j: phase <N> <name> — <one-line result>"
     Commit on the CURRENT branch. Do NOT create a branch, worktree, or new repository, and do
     not push unless I ask. All migration work lives in this repo alongside the app.
  5. Tell me exactly what to paste next session.

Rules that never bend:
  - NEVER write to MariaDB. It is the system of record and the only irreplaceable data.
  - Every Neo4j write is MERGE keyed on the MariaDB PK, so re-running is always safe.
  - Neo4j is 4.4.40 COMMUNITY: no APOC, no GDS, no RBAC, no existence constraints,
    single database. Verify your Cypher against 4.4 syntax before running it.
  - Report only counts you have actually queried.
````

That's it. Same prompt, every session, start to finish.

---

## 4. What happens if your session dies mid-migration

Short answer: **nothing breaks, and you lose at most the current phase.** Here is why, case by case.

| Dies during… | What's on disk | How to recover |
|---|---|---|
| **Classification (Phase 1)** | A partly-written markdown doc | Re-run Phase 1. It regenerates the doc from scratch. Nothing was written to Neo4j |
| **Registry (Phase 2)** | A partly-written PHP config | Re-run Phase 2, or finish the file by hand. `neo4j:registry-check` tells you what's missing |
| **Export (any load phase)** | Some CSVs written, some not | Re-run `neo4j:export --module=X`. It overwrites the folder. Harmless |
| **Load (any load phase)** ⚠️ | **Some batches committed to Neo4j, some not** | **Just re-run `neo4j:load --module=X`.** See below — this is the important one |
| **Verify** | Nothing changed | Re-run the verify command |

### Why an interrupted load is safe

Every write is `MERGE` on a stable key (the MariaDB primary key). `MERGE` means *"match it if it
exists, create it if it doesn't."* So:

- Rows already loaded → matched, nothing duplicated.
- Rows not yet loaded → created.
- Run it five times → identical result to running it once.

This property is called **idempotency**, and it is the single reason this migration is safe to
interrupt. It is also why the master prompt bans `CREATE` in the loader — `CREATE` would duplicate
everything on a re-run.

One caveat to know: `CALL {} IN TRANSACTIONS OF 10000 ROWS` commits in batches of 10,000. If you kill
it halfway, the completed batches **stay committed** — the load does not roll back. That's fine
(re-running finishes the job), but it does mean **a partially-loaded module will fail its verify
gate**, which is correct behaviour: it's telling you the phase isn't done. Re-run the load, then
re-verify.

### Practical tip — don't run long jobs inside the agent session

Exports over big tables (`lms_online_exam_answer`, 2.29M rows) can run for many minutes. If the
session ends, the process dies with it. Run those in a **separate terminal**, logging to a file:

```powershell
php artisan neo4j:export --module=assessment *> storage/logs/neo4j-export-assessment.log
```

Then the agent session only has to *read the log*, not babysit the process. Same for large loads.

---

## 5. Checkpoints — what "done" means for a phase

A phase is done when **all four** are true. Three out of four is not done.

1. **The verify gate passes**, with real output pasted — not a summary.
2. **STATUS.md is updated** — phase ticked, current phase advanced, session log appended.
3. **The work is committed to git** with the phase number in the message.
4. **Nothing was skipped silently.** If something was left out, it's written down in the session log.

Rule: **a failing gate stops the migration.** Do not load the next module on top of a broken one —
that is exactly how the current graph accumulated ten defects. Fix, re-verify, then continue.

### What `neo4j:verify --module=X` must check

Per module, hard-failing on any of:

- nodes missing `sub_institute_id`, or holding the invalid values `NULL` or `0`
- null or non-integer key properties
- `COUNT(n) <> COUNT(DISTINCT n.<key>)` — duplicate nodes
- **dangling FK properties** — any `*_id` with no matching target node (this is D9)
- relationships whose two endpoints have different `sub_institute_id` — tenant leak
- orphaned nodes
- Neo4j count vs MariaDB `COUNT(*)`, per tenant, for every ENTITY table in the module
- Finance only: `SUM` of every projected amount vs the same `SUM` in MariaDB, per tenant

---

## 6. "Will this break my existing data?"

Worth being precise, because there are two different things called "data" here.

**MariaDB — your real data — is never touched.** Not one write, in any phase. Every command reads
only. This is the guarantee that matters: if the entire migration goes wrong, your ERP is unaffected
and you have lost nothing but time.

**The current Neo4j graph — will be deleted, once, in Phase 3.** This is deliberate. That graph is
measurably broken: only 2.9% of Result nodes attach to exactly one student, 49,105 nodes are
orphaned, 7,674 questions point at chapters that don't exist, and 55% of nodes have no tenant.
Keeping it isn't preserving data, it's preserving corruption. And it *can't* be fixed in place —
the new model keys `:Student` on `studentId` where the old one keys on `stuId`, so loading on top
would create a second set of nodes rather than correcting the first.

Everything in that graph is re-derivable from MariaDB — **except** the handful of items in STATUS §5,
most importantly the **28 hand-authored `PREREQUISITE_OF` edges**. No table can regenerate those.
Phase 0 exists to back them up. Do not skip it.

⚠️ **You have no APOC**, so `apoc.export.cypher.all` is unavailable — there is no one-command full
backup. Phase 0 must either dump per-label CSVs through the HTTP API, or run `neo4j-admin dump` on
the server (which requires filesystem access and a brief stop). Decide which before starting.

### Downtime

Between Phase 3 (wipe) and Phase 14 (last load) the graph is **incomplete but never wrong** — each
module is either fully loaded and verified, or absent. Nothing user-facing depends on it today: the
Next.js frontend has no Neo4j connection at all, and the only consumers are Blade dev views
(`/graph-view`, `/student-result-graph`, `/dashboardNeo4j`). Confirm nobody is demoing those before
Phase 3.

---

## 7. Order of operations for the very first sessions

| Session | Do this | Ends with |
|---|---|---|
| 1 | **Answer DB-AUTH.** Which MariaDB is authoritative? Nothing can start until this is settled | STATUS §3 updated |
| 2 | Phase 0 — back up the 28 `PREREQUISITE_OF` edges and the items in STATUS §5; disable `/sync-neo4j` and `/migrate-data` | Backups on disk, committed |
| 3–4 | Phase 1 — classify all 487 tables. **Review this yourself before approving.** It is the foundation; a wrong shape here propagates everywhere | `neo4j-table-classification.md` approved |
| 5–6 | Phase 2 — registry + the four artisan commands | `neo4j:registry-check` green |
| 7 | Phase 3 — wipe and schema reset | Graph empty, constraints created |
| 8+ | One module per session, Phases 4–14 | A verified module each time |

**Phase 1 is the one to slow down on.** Everything downstream is generated from it. An hour of your
review there saves re-running load phases later.

---

## 8. Common ways this goes wrong

| Mistake | What it causes | Guard |
|---|---|---|
| Running two phases in one session | The second inherits an unverified first; failures compound | The session prompt says stop after one |
| Ticking a phase whose gate you didn't actually read | Silent corruption surfacing three phases later | Paste real output into the log |
| Loading a module before its dependencies | Dangling refs — defect D9 all over again | Follow the dependency order in §2 |
| Skipping Phase 0 | The 28 `PREREQUISITE_OF` edges are gone permanently | Phase 3 refuses to run without the backup |
| Letting `/sync-neo4j` run mid-migration | It MERGEs on names and collapses all 56 tenants | Phase 0 disables it; Phase 16 deletes it |
| Trusting the conversation over STATUS.md | Resuming in the wrong place | The session prompt reads STATUS first, always |
| Changing a convention halfway | Model drift — the thing that ruined the current graph | Conventions live in the master prompt; change them there and re-verify |
