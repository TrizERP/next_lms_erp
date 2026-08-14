# Neo4j Migration Runbook — phased, resumable, safe

**Companion to:** [`neo4j-migration-status.md`](./neo4j-migration-status.md) (progress tracker) ·
[`neo4j-full-erp-graph-master-prompt.md`](./neo4j-full-erp-graph-master-prompt.md) (the rules) ·
[`neo4j-graph-audit-and-rebuild-prompt.md`](./neo4j-graph-audit-and-rebuild-prompt.md) (the defects)

---

## 1. The one idea that makes this work

> **The conversation is not the state. The repository is the state.**

If you try to hold the migration in your head — or in one long Claude Code session — then a session
limit, a crash or a closed laptop loses everything. So instead: **every phase ends by writing a file
to disk.** The next session reads those files and picks up exactly where you left off. It does not
need to remember anything.

> Originally this read *"…writing a file to disk **and committing it**"*. Commits are off by owner
> instruction (see §5), so the files carry the state but git does not. That is weaker — an
> uncommitted working tree has no recovery point.

This means a lost session costs you *at most one unfinished phase*, never the whole migration.

Three files carry all the state:

| File | Role |
|---|---|
| `docs/neo4j-migration-status.md` | **The resume point.** What's done, what's next, what's blocked |
| `config/neo4j_graph.php` | The registry — how all 488 tables map to nodes and relationships |
| `docs/neo4j-table-classification.md` | The reviewed decision for every table |

Plus generated artifacts: `storage/app/neo4j/<module>/*.csv` and the four artisan commands.

---

## 2. Why phases, and why this order

A module can only load **after everything it points at already exists**. Load questions before
chapters and you recreate defect D9. So the order is a dependency order, not a preference.

```
0  Freeze & backup          ← protect the irreplaceable
1  Classify 488 tables      ← HUMAN REVIEW GATE
2  Registry + tooling       ← build the machine
3  Wipe & schema reset      ← one destructive step, once
─────────────────────────────
4  Foundation      Institute, AcademicYear, Standard, Subject, Division
5  Curriculum      Chapter, Topic, Concept, Content        ← needs 4 + the Phase 0 rescue CSVs
6  Questions       Question + mappings                     ← needs 5
7  People          Student (person), Enrollment            ← needs 4
8  Assessment      Assessment, attempts, mastery           ← needs 6 + 7
9  Result                                                  ← needs 8
10 Staff / HR                                              ← needs 4
11 Finance         (blocked on FEES-MODEL)                 ← needs 7
12 Operations      library, transport, hostel, inventory…  ← needs 7 + 10
13 Skill / Career  + O*NET                                 ← needs 5
14 Platform / misc + PAL stubs
─────────────────────────────
15 Live sync
16 Cleanup — delete the legacy writers
```

> **Correction, 2026-08-10 (Phase 1).** This section used to say D9 was *"7,674 questions pointing at
> chapters that were never created"*, implying correct load order prevents it. Both halves were
> wrong. Measured against the source: **59,314 of 62,206** questions carry a `chapter_id` that has no
> row in `chapter_master` — and `chapter_master` holds only **99 rows**. The chapters are missing
> from **MariaDB**, not merely unloaded. No load order fixes that.
>
> Phase 5 therefore has a dependency this diagram did not have: it must seed `:Chapter` from
> `docs/neo4j-backup-2026-08-10/nodes_Chapter.csv`, which holds **5,521 chapters that exist nowhere
> in MariaDB** and repairs 70.9% of the break. Load Phase 5 from `chapter_master` alone and ~105,000
> child rows orphan. See classification F1 and decision **CHAPTER-SOURCE**.
>
> Also updated above: 487 → **488** tables, and Phase 11's blocker — SECURITY-RBAC was decided, so
> Finance now waits on **FEES-MODEL** instead.

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
  docs/neo4j-table-classification.md              (the reviewed decision for every table)
  config/neo4j_graph.php                          (the registry - GENERATED, never hand-edit)
  database/neo4j/README.md                        (how to regenerate the two files above)

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
     set "Graph state", and append one row to the §2 session log. Record the DROPPED-ROW
     count per table from neo4j:export — a phase that drops rows without logging the number
     is not done (§5.5).
  4. Do NOT commit. Leave every change in the working tree and show me `git status --short`
     instead. Do NOT create a branch, worktree, or new repository, and do NOT pull or push.
     All migration work lives in this repo alongside the app.
  5. Tell me exactly what to paste next session.

Rules that never bend:
  - NEVER write to MariaDB. It is the system of record and the only irreplaceable data.
  - Every Neo4j write is MERGE keyed on `uid` — "<Label>:<sub_institute_id>:<syear>:<pk>",
    NOT the bare MariaDB pk. A bare pk is not unique across 48 tenants; merging on one is
    how the original graph collapsed tenants together. Re-running is therefore always safe.
  - Neo4j is 4.4.40 COMMUNITY: no APOC, no GDS, no RBAC, no existence constraints,
    single database. Verify your Cypher against 4.4 syntax before running it.
  - Report only counts you have actually queried.
  - A load over ~100k rows can exceed a single command timeout. Run those in a separate
    terminal logging to a file (see §4) rather than babysitting them in the session.
````

That's it. Same prompt, every session, start to finish.

---

## 4. What happens if your session dies mid-migration

Short answer: **nothing breaks, and you lose at most the current phase.** Here is why, case by case.

| Dies during… | What's on disk | How to recover |
|---|---|---|
| **Classification (Phase 1)** | A partly-written markdown doc | `cd database/neo4j && php classify.php && php render.php`. Nothing was written to Neo4j |
| **Registry (Phase 2)** | A partly-written PHP config | `cd database/neo4j && php gen_registry.php`, then `php artisan neo4j:registry-check` to see what's missing. Never finish it by hand — it is generated |
| **Wipe (Phase 3)** ☠️ | **A partially deleted graph** | Re-run `neo4j:reset-graph --confirm --backup=<dir>`; the delete loop runs until the graph is empty, so it finishes the job. **The deleted nodes are gone either way** — this is the one step with no undo |
| **Export (any load phase)** | Some CSVs written, some not | Re-run `neo4j:export --module=X`. It overwrites each table's CSV. Note it does *not* clear the folder first, so a CSV for a table you have since removed from the module will linger |
| **Load (any load phase)** ⚠️ | **Some batches committed to Neo4j, some not** | **Just re-run `neo4j:load --module=X --confirm`.** See below — this is the important one |
| **Verify** | Nothing changed | Re-run the verify command |

> **Phase 1 and 2 became re-runnable on 2026-08-10.** Until then this table was wrong: the generators
> existed only in a session temp directory, so "re-run Phase 1" was impossible and
> `config/neo4j_graph.php` could not be regenerated at all. They now live in
> [`database/neo4j/`](../database/neo4j/) with a README. Regeneration is deterministic — verified
> byte-identical with `diff`.

### Why an interrupted load is safe

Every write is `MERGE` on a stable key — specifically `uid`, the composite
`<Label>:<sub_institute_id>:<syear>:<pk>` required by PROJECTION LAW L1, *not* the bare MariaDB
primary key. (A bare pk is not unique across 48 tenants; merging on it is how the original graph
collapsed tenants together.) `MERGE` means *"match it if it exists, create it if it doesn't."* So:

- Rows already loaded → matched, nothing duplicated.
- Rows not yet loaded → created.
- Run it five times → identical result to running it once.

This property is called **idempotency**, and it is the single reason this migration is safe to
interrupt. It is also why the master prompt bans `CREATE` in the loader — `CREATE` would duplicate
everything on a re-run.

One caveat to know: the loader commits in batches (`--batch`, default 1,000 rows per `UNWIND`). If you
kill it halfway, the completed batches **stay committed** — the load does not roll back. That's fine
(re-running finishes the job), but it does mean **a partially-loaded module will fail its verify
gate**, which is correct behaviour: it's telling you the phase isn't done. Re-run the load, then
re-verify.

> **Corrected 2026-08-10.** This paragraph used to describe `CALL {} IN TRANSACTIONS OF 10000 ROWS`.
> The loader does not use that syntax — it batches in PHP and issues one `UNWIND … MERGE` per batch
> through the Bolt driver. The idempotency guarantee is unchanged; only the mechanism differs.

### Practical tip — don't run long jobs inside the agent session

Exports over big tables (`lms_online_exam_answer`, 2.29M rows) can run for many minutes. If the
session ends, the process dies with it. Run those in a **separate terminal**, logging to a file:

```powershell
php artisan neo4j:export --module=assessment *> storage/logs/neo4j-export-assessment.log
```

Then the agent session only has to *read the log*, not babysit the process. Same for large loads.

---

## 7. Order of operations for the very first sessions

| Session | Do this | Ends with |
|---|---|---|
| 1 | **Answer DB-AUTH.** Which MariaDB is authoritative? Nothing can start until this is settled | STATUS §3 updated |
| 2 | Phase 0 — back up the 28 `PREREQUISITE_OF` edges and the items in STATUS §5; disable `/sync-neo4j` and `/migrate-data` | Backups on disk, committed |
| 3–4 | Phase 1 — classify all 488 tables. **Review this yourself before approving.** It is the foundation; a wrong shape here propagates everywhere | `neo4j-table-classification.md` approved |
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
