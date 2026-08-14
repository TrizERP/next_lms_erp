# Neo4j Migration — Phase 1: classification of all 488 tables

> **Status: awaiting human review.** This document is the Phase 1 deliverable and its gate is
> *"reviewed by a human"*. Nothing downstream should be built until someone signs off on §3 and §4 —
> the registry in Phase 2 is generated from these decisions, so an error here propagates into every
> load phase.

**Measured:** 2026-08-10 against `vivek_erp` @ `202.47.117.220` (MariaDB 10.11.9), the authoritative
source fixed by decision DB-AUTH. **Read-only — nothing was written to MariaDB or Neo4j.**

**Companion docs:** [`neo4j-migration-status.md`](./neo4j-migration-status.md) ·
[`neo4j-migration-runbook.md`](./neo4j-migration-runbook.md) ·
[`neo4j-full-erp-graph-master-prompt.md`](./neo4j-full-erp-graph-master-prompt.md) ·
[`neo4j-graph-audit-and-rebuild-prompt.md`](./neo4j-graph-audit-and-rebuild-prompt.md)

---

## 1. Method — what was actually measured

Every number in this document was queried, not estimated or carried over from the audit docs.

| Measurement | How |
|---|---|
| Row counts | `SELECT COUNT(*)` per table, all 488. **Not** `information_schema.table_rows`, which is an InnoDB estimate |
| Size | `data_length + index_length` from `information_schema` |
| Code references | Regex sweep of 3,302 `.php/.sql/.js/.json` files under `app/ routes/ resources/views/ database/ config/ public/`, counting *quoted* occurrences (`'table'`, `` `table` ``, `FROM table`) separately from bare word hits — because `batch`, `subject`, `standard` and `timetable` are English words |
| Tenancy columns | `information_schema.columns`, matched case- and underscore-insensitively so `sub_institute_id`, `SUB_INSTITUTE_ID` and `SubInstituteId` all resolve |
| FK integrity | `LEFT JOIN` from child to parent, counting unmatched rows |
| Aggregate cardinality | `SELECT COUNT(*) FROM (SELECT ... GROUP BY ...)` — the real edge count each ledger collapses to |

**Discrepancy with the audit docs, resolved:** the audit reports 487 tables / 8,896,258 rows. Live
measurement finds **488 tables / 9,020,219 rows**. The row delta is because the audit used the
`information_schema` estimate; the table delta is one table added since. Both figures below are exact.

---

## 2. Headline result

| | Naive row-per-node | This classification |
|---|---:|---:|
| Nodes | 9,020,219 | **596,825** |
| Relationships | ~9,000,000+ | **1,853,811** |
| Source rows never mirrored as nodes | 0 | **6,949,135** (77%) |

Both figures sit inside the master prompt's budget (< 700,000 nodes / < 4,000,000 relationships).
The reduction comes entirely from PROJECTION LAW L3/L4 — ledgers become aggregated edges.

### Decisions

| Decision | Meaning | Tables |
|---|---|---:|
| `NODE` | Becomes a node label | 156 |
| `EDGE` | Becomes a relationship, one per row | 57 |
| `AGG_EDGE` | `GROUP BY` in SQL, one edge per group (L4) | 33 |
| `PROP` | Folded as properties onto another node or edge | 31 |
| `EXCLUDE` | Not projected, reason recorded per table | 194 |
| `REVIEW` | **Needs a human decision** — see §5 | 17 |
| | **Total** | **488** |

### Tiers

| Tier | Meaning | Sync cadence | Tables |
|---|---|---|---:|
| **A** | Full graph — entities + rich relationships, traversal is the point | Live (queued observers) | 56 |
| **B** | Entity + aggregated edges | Nightly backfill | 188 |
| **C** | Reference / lookup dimension | Manual, on data refresh | 51 |
| **D** | Excluded — logs, caches, rights matrices, framework tables | — | 193 |

---

