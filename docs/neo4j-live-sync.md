# MariaDB → Neo4j live sync (K12)

How a row saved in MariaDB becomes a node and its relationships in Neo4j, and
what to do when it doesn't.

```
any writer  →  MariaDB  →  [trigger]  →  sync_log            →  neo4j:drain  →  Neo4j
                                          neo4j_sync_queue
```

Only the two tables that already existed are used. Nothing else queues graph
work.

---

## Why the capture is a database trigger

It was a controller call, and controller calls lost data.

`GraphSync` was wired into two API controllers. MariaDB has at least fifteen
code paths that write a student — the legacy web controller, two admission
confirmation flows, four import sites, bulk edits, transfers, console commands,
raw SQL. A student added through any of the other thirteen committed to MariaDB
and never produced an outbox row.

That is not hypothetical. On 2026-08-21 `sync_log` held 9,257 rows, **all
SUCCESS, zero pending, zero failed** — and `tblstudent#281472` ("Jayesh") was
absent from Neo4j. The pipeline was perfectly healthy and the graph was wrong,
because a row that never emitted an event is indistinguishable from one that
synced correctly. A reconcile pass across the 50 newest students found 46 of
them missing.

Enumerating writers is a losing game — the next one added regresses it. Every
writer ends in an INSERT, and an INSERT fires a trigger.

---

## The pieces

| Piece | File | Job |
|---|---|---|
| Triggers | `database/migrations/2026_08_21_100100_create_neo4j_sync_triggers.php` | Record "row X of table T changed" into `sync_log`, inside the caller's transaction |
| Registry | `app/Services/Graph/ProjectionRegistry.php` | table → projection, label → table |
| Projections | `app/Services/Graph/*GraphProjection.php` | Re-read MariaDB, decide the graph shape, write outbox rows |
| Entity specs | `config/neo4j.php` &rarr; `projections` | The column-map entities, as data |
| Outbox producer | `app/Services/Graph/GraphOutbox.php` | Write node / relationship rows |
| Drain | `app/Services/Graph/GraphDrain.php` | Deliver outbox rows to Neo4j |
| Label whitelist | `app/Services/Graph/GraphSchema.php` | Legal labels, keys, relationship types |
| Controller entry | `app/Services/Graph/GraphSync.php` | `flushRecord()` — deliver now instead of within the minute |
| Reconcile | `app/Console/Commands/Neo4j/ReconcileCommand.php` | Compare MariaDB to Neo4j, audit edges, repair drift |

The trigger records only the *fact* of a change. Every decision about the shape
of the graph lives in PHP, so the SQL never changes when the graph does.

### Two kinds of `sync_log` row

`table_name` says which. Both share one envelope, so every row is
self-describing:

```jsonc
// a MariaDB table name -> a trigger event: "this row changed"
{"record_id":282261,"event":"INSERT","source_table":"tblstudent",
 "data":{"sub_institute_id":1}}

// a Neo4j label -> a projected node event, ready to MERGE
{"record_id":439016,"event":"INSERT","node_label":"Student",
 "data":{"stuId":439016,"student_id":282261,"grade_id":1,"standard_id":43,
         "section_id":1,"syear":2024,"sub_institute_id":1,
         "displayLabel":"Student:282261"}}
```

**The trigger row's `data` is not a copy of the row, and should not become one.**
It carries only what cannot be read back after a DELETE — the tenant, and an
enrolment's owner. The projection re-reads MariaDB for everything else. Copying
the full row into the trigger would put the graph's shape into SQL a second
time, where it drifts from the PHP projection the moment either changes, and it
still could not produce the node payload: `displayLabel`, the per-enrolment
fan-out and the type casting are all decided after the re-read. Re-reading is
also what makes a replay correct — draining a week-old event syncs the student
as they are **now**, not as they were when the trigger fired.

To see the node data:

```sql
SELECT * FROM sync_log WHERE table_name = 'Student';     -- full node payload
SELECT * FROM sync_log WHERE table_name = 'tblstudent';  -- "this row changed"
```

---

## Operating it

```bash
php artisan neo4j:drain                 # one pass
php artisan neo4j:drain --status        # queue depth only
php artisan neo4j:drain --watch=10      # supervised worker, for hosts with no cron
php artisan neo4j:reconcile                  # node drift, per entity
php artisan neo4j:reconcile --relationships  # audit all 27 K12 edge types
php artisan neo4j:reconcile --fix            # report and repair
php artisan neo4j:reconcile --fix --dry-run  # show what would be repaired
php artisan neo4j:reconcile --entity=tblstudent --since=2026-08-01 --fix
php artisan neo4j:reconcile --entity=tblstudent --tenant=1 --limit=500 --fix
```

`neo4j:drain` every minute and `neo4j:reconcile --fix` nightly are registered in
`app/Console/Kernel.php`.

`neo4j:reconcile` replaced `neo4j:backfill-students`, which repaired the same
rows through the same projection but could not tell you which rows needed it —
it only ever re-pushed a range whether or not the graph already had them. Its
`--tenant`, `--limit` and `--dry-run` options carried over.

**It is not `neo4j:verify`.** That command gates the *uid migration*: it fails
any node lacking a `uid`, and any `:Result` attached to more than one
`:Student`. The live K12 graph is legacy-keyed and deliberately fans HAS_RESULT
across every enrolment, so `neo4j:verify` reports 193,233 G1 failures and
110,966 G4 failures against a perfectly healthy K12 sync. The two encode
opposite models on purpose; do not merge them, and do not read one's failures as
the other's.

### The scheduler must actually run

**`$schedule->command(...)` does nothing unless `schedule:run` runs.** There was
no cron entry on the Windows dev host, which is why no failed row was ever
retried. On Windows:

```powershell
$action  = New-ScheduledTaskAction -Execute 'C:\xampp\php\php.exe' -Argument 'artisan schedule:run' -WorkingDirectory 'C:\xampp\htdocs\next_lms_erp'
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1)
Register-ScheduledTask -TaskName 'LaravelScheduler_next_lms_erp' -Action $action -Trigger $trigger -Force
```

On Linux: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`.

### `withoutOverlapping()` needs an explicit expiry

It defaults to a **1440-minute (24 hour)** mutex, released only when the command
finishes cleanly. A drain killed mid-run — the box sleeping, a console closing, a
deploy — therefore disables the whole sync for a day, and does it invisibly:
`schedule:run` keeps exiting 0, the command never executes, and rows sit PENDING
with `retry_count 0`, so nothing even looks like it failed.

That happened on 2026-08-21: a run killed at 10:48 held the mutex until 10:48 the
next day. `withoutOverlapping(5)` bounds the damage to one skipped pass. If the
sync ever looks dead with a small backlog and zero retries, check for a stale
mutex in `storage/framework/cache/data` before anything else.

The depth alert now also fires on the AGE of the oldest pending row, not just the
count — that stall queued only ~20 rows, far under any sane depth threshold,
while being completely dead.

### Bulk imports

Triggers honour a session variable. Set it on the importing connection and its
writes queue nothing:

```sql
SET @neo4j_sync_off = 1;
```

Then run one `neo4j:reconcile --fix` pass afterwards instead of queueing an
event per row.

### Switches

`NEO4J_SYNC_ENABLED=true` gates this pipeline. Leave `NEO4J_WRITES_ENABLED=false`
— that flag gates three *legacy* writer routes that still key nodes under the
pre-migration convention, and turning it on reintroduces defect D2.

---

## Adding an entity

1. Add a spec to `config/neo4j.php` (`projections` key) (`label`, `properties`,
   `display_label`, `relationships`).
2. Add the table to the `triggered` list in the same file.
3. Re-run the trigger migration — the watched columns are derived from the spec,
   so there is no second list to keep in step.

Only reach for a bespoke projection class when the shape is not a column map:
`StudentGraphProjection` (two tables, two grains) and `ResultGraphProjection`
(one attempt fans out across every enrolment) are the two that qualify.

---

## Two traps, both of which bit during implementation

**The graph carries two key conventions.** Legacy (`stId`, `chId`, `unitId`) and
uid (`Unit:1:0:22`). Anything that asks "is this row in the graph?" must check
**both**, or it will report present rows as missing and then MERGE a duplicate
legacy-keyed twin — defect D2, the exact duplication being migrated away from.

- Reconcile parses the id back out of `n.uid` (`Label:tenant:syear:id`, segment 3)
  rather than reconstructing the uid. An early version rebuilt it and defaulted
  the tenant to 0 for `lms_units`, which has no `sub_institute_id` column —
  looked for `Unit:0:0:22`, missed all 60 real `Unit:1:0:22` nodes, and would
  have created 60 twins under `--fix`.
- The uid check must **not** be gated on `GraphSchema::hasUidFallback()`. That
  flag answers a different question — whether an *edge* can resolve the label at
  link time — which `:Lesson` fails only because its uid is year-scoped. Gating
  on it hid 1,807 uid-keyed `:Lesson` nodes and reported every one missing.

**`GraphDrain::repairEndpoints()` deliberately refuses uid-capable labels.**
Creating a missing `:Standard` on its legacy key when the tenant's real node is
uid-keyed would mint a twin. For those labels a missing endpoint stays missing,
the row retries, and reconcile reports it. That is the safe failure.

---

## Known data problems this surfaced

Enrolments `438910` and `438911` belong to tenant 1 but carry `standard_id = 2`,
which belongs to **tenant 3** (`CBSE-JR`). No `:Standard` node exists for them in
tenant 1, so their `ENROLLED_IN` edges cannot be built. The drain retries five
times and marks them `failed` rather than inventing a cross-tenant edge. This is
a MariaDB data fault, not a sync fault — fix the rows.

## Entities with no live source in `vivek_erp`

`lms_misconceptions`, `lms_learning_objectives`, `lms_competency_standards`,
`lms_chapter_standard_map` and `lms_assessment_typology` **do not exist** in this
database, so `:Misconception`, `:LearningObjects`, `:CompetencyStandards`,
`:ChapterStandardMap` and `:AssessmentTypology` have no projection. Misconceptions
do exist under `pal_misconceptions` / `pal_misconception_library`, on the PAL
model rather than the K12 one; they sync through the PAL coherence pass.

`suggested_content` exists but has none of the columns `k12_cypher.txt`'s
`:LearningContent` block reads, so projecting it would produce nodes of nulls.

See the header of `config/neo4j.php` (`projections` key) for the full column-by-column
divergence between `k12_cypher.txt` and the real schema.
