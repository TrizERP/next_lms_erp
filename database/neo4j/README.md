# Neo4j migration — Phase 1 & 2 generators

These scripts produced [`docs/neo4j-table-classification.md`](../../docs/neo4j-table-classification.md)
(Phase 1) and [`config/neo4j_graph.php`](../../config/neo4j_graph.php) (Phase 2). They live here so
those phases are actually re-runnable — the runbook §4 claims they are, and until 2026-08-10 that was
false: the generators only existed in a session temp directory.

**Everything here is read-only against MariaDB and Neo4j.** No script writes to either.

## Pipeline

Run in order. Steps 1–3 hit the live database and take ~10 minutes; 4–6 are offline and instant.

| # | Script | Reads | Writes | Notes |
|---|---|---|---|---|
| 1 | `inventory.php` | MariaDB `information_schema` + `COUNT(*)` per table | `inventory.json` | Exact row counts, not estimates. ~8 min over 488 tables |
| 2 | `coderefs.php` | 3,302 repo source files | `coderefs.json` | Quoted vs bare reference counts |
| 3 | `tenancy.php` | `inventory.json` | `tenancy.json` | Case/underscore-insensitive tenant column detection |
| 4 | `classify.php` | `decisions.php` + the three JSONs | `classification.json` | Explicit decisions + prefix rules |
| 5 | `render.php` | `classification.json` + the two hand-written sections | `docs/neo4j-table-classification.md` | Generates §1–2 and §6–7, then assembles all four sections |
| 6 | `gen_registry.php` | `classification.json` + `inventory.json` | `config/neo4j_graph.php` | The registry |

### The classification doc is four sections, two of them hand-written

`render.php` generates only the mechanical halves. The analysis is not regenerable and lives here:

| Section | File | Contents |
|---|---|---|
| §1–§2 | generated | Method, headline counts, decision/tier tables |
| §3–§5 | **`part2-findings.md`** | Findings F1–F12, the blocking decisions, the review list |
| §6–§7 | generated | Per-module summary, the 488-table register |
| §8–§10 | **`part4-gate.md`** | Phase 2 requirements, the Phase 1 gate, sign-off |

Edit the analysis in `part2-findings.md` / `part4-gate.md`, never in the assembled document — the next
`render.php` overwrites it. Assembly aborts if a section is missing rather than emitting a partial doc.

> **Keep the doc and the registry in sync.** Both are generated from `classification.json`, so a change
> to `decisions.php` must be followed by **both** `render.php` and `gen_registry.php`. On 2026-08-10
> the doc drifted from the registry for exactly this reason: Phase 2 gate failures forced corrections
> into `decisions.php` (`MBTI_answer` → `PROP`, `MASTERS` → `:Chapter`, O\*NET target syntax,
> `email_sent_parents` → `:Staff`) and the registry was regenerated but the document was not.

`graph_export.php` is separate — it re-exports the Phase 0 rescue CSVs from a **live** Neo4j graph.
After Phase 3 wiped that graph it can no longer be used; kept as the record of how the export was made.

## The one file to edit

**`decisions.php`** holds the per-table decisions in the form:

```php
'table_name' => 'Module|Tier|DECISION|target|phase|note',
```

`DECISION` is one of `NODE` `EDGE` `AGG_EDGE` `PROP` `EXCLUDE` `REVIEW`. Change a decision there, then
re-run steps 4 and 6. Do **not** hand-edit `config/neo4j_graph.php` — it is generated, and the next
regeneration silently discards your edit.

Tables not listed in `decisions.php` fall through to prefix rules in `classify.php` (O\*NET, `pal_*`,
`wk_*`), then to `REVIEW` if nothing matches. `neo4j:registry-check` fails on any table the pipeline
leaves uncovered, so a new table cannot slip through unclassified.

## Verifying a change

```bash
cd database/neo4j
php classify.php && php gen_registry.php
cd ../.. && php artisan neo4j:registry-check
```

The pipeline is deterministic: re-running steps 4 and 6 on unchanged inputs reproduces
`config/neo4j_graph.php` **byte-for-byte** (verified 2026-08-10 with `diff`). If a regeneration
produces a diff you did not intend, something in `decisions.php` or the JSONs changed.

## The JSON snapshots

`inventory.json`, `coderefs.json` and `tenancy.json` are checked in deliberately. They are the
measured state of `vivek_erp` on 2026-08-10, so the registry can be regenerated with no database
access and produces the same result on any machine. Re-run steps 1–3 only when the schema has
actually changed — and expect the row counts to move, which will change the classification doc.
