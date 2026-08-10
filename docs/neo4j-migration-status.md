# Neo4j Migration — STATUS

> **This file is the source of truth for migration progress.**
> Every session starts by reading it and ends by updating it. If a session dies, this file — not the
> conversation — is what tells the next session where to resume. Never delete it; never let it drift.

**Last updated:** 2026-08-10 (Phase 0 complete — frozen and backed up)
**Current phase:** 1 — Classify all 487 tables
**Graph state:** ORIGINAL — pre-migration graph still in place, untouched and verified unchanged
(261,828 nodes / 618,991 relationships re-measured after Phase 0)
**Authoritative source DB:** `vivek_erp` @ `202.47.117.220` (user `vivek_user`) — **decided**
**PII scope:** full projection on Community, risk accepted — **decided**, owner to be named
**Blocking decisions outstanding:** TENANT-SCOPE, PREREQ-SOURCE, ONET-WANTED, **RESIDUAL-WRITERS**
(none block Phase 1; RESIDUAL-WRITERS blocks Phase 3)

---

## 1. Phase checklist

Mark a phase `[x]` **only** after its verification gate passes and the work is committed.

| # | Phase | Status | Gate | Commit |
|---|---|---|---|---|
| 0 | Freeze & backup | `[x]` | Backups exist and are readable; writer routes disabled | _(this session)_ |
| 1 | Classify all 487 tables | `[ ]` | `docs/neo4j-table-classification.md` reviewed **by a human** | — |
| 2 | Registry + tooling | `[ ]` | `neo4j:registry-check` passes | — |
| 3 | Wipe & schema reset | `[ ]` | `MATCH (n) RETURN count(n)` = 0; constraints created | — |
| 4 | Foundation — Institute, AcademicYear, Standard, Subject, Division | `[ ]` | `neo4j:verify --module=foundation` | — |
| 5 | Curriculum — Chapter, Topic, Concept, Content | `[ ]` | `neo4j:verify --module=curriculum` | — |
| 6 | Question bank — Question + mappings | `[ ]` | `neo4j:verify --module=questions` | — |
| 7 | People — Student (person) + Enrollment | `[ ]` | `neo4j:verify --module=people` | — |
| 8 | Assessment — Assessment, attempts, mastery aggregates | `[ ]` | `neo4j:verify --module=assessment` | — |
| 9 | Result module | `[ ]` | `neo4j:verify --module=result` | — |
| 10 | Staff / HR | `[ ]` | `neo4j:verify --module=hr` | — |
| 11 | Finance — full ledger projection | `[ ]` | `neo4j:verify --module=finance` **incl. per-tenant SUM reconciliation** | — |
| 12 | Operations — library, transport, hostel, inventory, front desk, visitor, inward | `[ ]` | `neo4j:verify --module=operations` | — |
| 13 | Skill / Career + O\*NET | `[ ]` | `neo4j:verify --module=skills` | — |
| 14 | Platform / misc + PAL stubs | `[ ]` | `neo4j:verify --module=platform` | — |
| 15 | Live sync (outbox + drain) | `[ ]` | Queue depth stable for 24h under Supervisor | — |
| 16 | Cleanup — delete legacy writers | `[ ]` | Full `neo4j:verify` green | — |

---

## 2. Session log

One line per session. Append; never rewrite history.

| Date | Phase | What happened | Left in this state |
|---|---|---|---|
| 2026-08-10 | — | STATUS seeded from audit docs | Nothing started |
| 2026-08-10 | 0 | Freeze & backup. Baseline snapshotted to `docs/neo4j-baseline-2026-08-10.md` (261,828 nodes / 618,991 rels / 19 labels / 24 rel types / 24 constraints / 26 indexes — matches the audit doc exactly). 32 CSVs written to `docs/neo4j-backup-2026-08-10/` covering every label <1,000 nodes and every rel type <1,000 edges (3,734 rows), a superset of §5. `/sync-neo4j` and `/migrate-data` commented out and verified absent from the 4,016-route table. Found 3 further live Neo4j-writing routes → new blocking decision RESIDUAL-WRITERS. Found `PREREQUISITE_OF` is a mechanical C(8,2) closure, not authored. | Graph UNTOUCHED and re-verified at baseline counts; 2 writer routes disabled; ready for Phase 1 |

---

## 3. Blocking decisions

| ID | Question | Status | Blocks |
|---|---|---|---|
| **DB-AUTH** | Which MariaDB is authoritative? | ✅ **DECIDED 2026-08-10** — `vivek_erp` @ `202.47.117.220`, user `vivek_user`. All audit figures were measured against it. `development_erp` @128.199.17.97 is **not** the source; treat the frontend `.env` `# mcp` block as unrelated. | — |
| **SECURITY-RBAC** | Neo4j 4.4.40 Community has no RBAC: one credential reads all PII for all 56 tenants. | ✅ **DECIDED 2026-08-10** — option 3: project everything, risk accepted. See §3a. | — |
| **TENANT-SCOPE** | All 56 tenants, or institute 1 only? Only institute 1 has a complete curriculum chain. | ⬜ OPEN | Phase 5 |
| **PREREQ-SOURCE** | Where does `PREREQUISITE_OF` come from? No table supplies it. | ⬜ OPEN | Phase 5 (can defer) |
| **ONET-WANTED** | Is O\*NET (43 tables, ~1.26M rows, unreferenced in code) actually wanted? | ⬜ OPEN | Phase 13 |
| **RESIDUAL-WRITERS** | Three live routes still write to Neo4j after the Phase 0 freeze. See §3b. | ⬜ **OPEN — raised 2026-08-10** | **Phase 3** |

### 3a. Accepted risk — full PII projection on Community Edition

**Decision (2026-08-10):** all modules project in full, including HR salary
(`hrms_salary_certificate`, `hrms_emp_payroll_deduction`, `payroll_types`), student documents
(`tblstudent_document`, 65,722 rows) and complete fee ledgers.

**What is being accepted:** Neo4j 4.4.40 Community has no role-based access control and no
multi-database isolation. A single credential grants read/write to every node for all 56 tenants,
on a release that is past end-of-life and therefore receives no security patches.

**Risk owner:** _(to be named — fill this in before Phase 10)_

**Hardening still required — these were common to all three options and are not optional:**

| # | Action | Status |
|---|---|---|
| 1 | Rotate `NEO4J_PASSWORD` off `admin`; store in `.env` only | ⬜ |
| 2 | Enable TLS on bolt (`bolt+s://`) and HTTPS on the browser port | ⬜ |
| 3 | Restrict `7475`/`7688` to known source IPs at the firewall | ⬜ |
| 4 | Plan an upgrade off EOL 4.4 | ⬜ |

Items 1–3 must be complete before Phase 10 (HR). They do not block Phases 0–9.

**Status re-checked 2026-08-10 (Phase 0):** all four still ⬜ OPEN. `.env` still has
`NEO4J_PASSWORD=admin` and `NEO4J_URI=bolt://dev.triz.co.in:7688` (plain bolt, not `bolt+s://`).
Correctly does not block Phase 1.

### 3b. RESIDUAL-WRITERS — live Neo4j writers surviving the Phase 0 freeze

Phase 0 disabled the two routes named in the master prompt. A full sweep of the 4,016-route table
found **three more registered routes that write to Neo4j**, none of which were named in the audit
docs. They are ordinary application features, not dead migration code, so Phase 0 deliberately left
them running — but each one will write nodes into the graph *during* the rebuild.

| Route | Controller | Writes |
|---|---|---|
| `POST /lms/pal` | `palController@store` ([palController.php:1294-1387](../app/Http/Controllers/lms/pal/palController.php#L1294-L1387)) | 2 × `neo4jCreateNode`, 4 × `neo4jCreateRelationship` — includes the `HAS_RESULT` write flagged in the audit |
| `POST /assessment_question/store` | `assessmentQuestionController@store` ([assessmentQuestionController.php:756-773](../app/Http/Controllers/lms/assessmentQuestionController.php#L756-L773)) | 1 × `neo4jCreateNode`, 1 × `neo4jCreateRelationship` |
| `POST /neo4j/assessment` | `addAssesmentController@store` ([addAssesmentController.php:58](../app/Http/Controllers/neo4J/addAssesmentController.php#L58)) | `MERGE (ass:Assessment {...})` |

**Why this blocks Phase 3, not Phase 1.** Phase 3's gate is `MATCH (n) RETURN count(n)` = 0. Any of
these three firing between the `DETACH DELETE` and the assertion fails the gate; firing after the
schema reset seeds nodes under the *old* key convention, which is defect D2 reintroduced. Phase 1
(classification) writes nothing to Neo4j, so it is unaffected.

**Decision needed before Phase 3** — one of: (a) take the app read-only for the rebuild window,
(b) comment these three routes out too and accept the feature outage, or (c) feature-flag the
Neo4j writes behind an env switch (`NEO4J_WRITES_ENABLED`) so they no-op during the rebuild and can
be re-enabled at Phase 15. Option (c) is the recommendation: it is the only one that does not
require a second code change to undo, and it gives Phase 15 the switch it needs anyway.

**Also found — 5 dead routes, no action required.** `routes/cal.php:22-28` registers
`Neo4jAssessmentController@{index,show,update,destroy,storeFromQuestionPaper}`. **No class of that
name exists anywhere in `app/`**, and `routes/cal.php` has no `use` import for it, so the action
resolves to the global `\Neo4jAssessmentController` and every one of those routes throws on
dispatch. They cannot write. Two of them (`PUT`/`DELETE`) would otherwise have been Neo4j writers.
Clean up in Phase 16.

> **Unrelated pre-existing breakage, noted in passing:** `php artisan route:list` fails with
> `Class "App\Http\Controllers\sqaaReportController" does not exist`. It predates this work and does
> not affect the migration — the route table was enumerated directly via `Route::getRoutes()`
> instead. Not fixed here; out of Phase 0 scope.

---

## 4. Baseline — the graph as it was before migration

Measured live 2026-08-10. Restore target if a rollback is ever needed.

```
261,828 nodes / 618,991 relationships / 19 labels / 26 edge patterns
Neo4j 4.4.40 Community · no APOC · no GDS · databases: neo4j + system only
```

Known defects being fixed (full detail in `neo4j-graph-audit-and-rebuild-prompt.md`):
D1 Student = enrollment not person · D2 inert constraints on all-NULL `record_id`, 91 duplicate
Results · D3 55% of nodes untenanted, 88 cross-tenant edges · D4 49,105 orphans · D5 three learner
representations · D6 reciprocal duplicate edges · D7 concatenated Cypher · D8 Concept 11 vs 1,372
· D9 7,674 dangling Question→Chapter refs · D10 only 2.9% of Results attach to exactly one student

---

## 5. Irreplaceable data — back up before Phase 3

✅ **Done 2026-08-10 (Phase 0).** All backups live in
[`docs/neo4j-backup-2026-08-10/`](./neo4j-backup-2026-08-10/) — 32 CSVs, 3,734 rows, read back off
disk and row-count-verified by the Phase 0 gate.

| Item | Why it cannot be regenerated | Backed up? |
|---|---|---|
| 28 `PREREQUISITE_OF` edges | ~~No MariaDB table supplies them; hand-authored~~ — **see note below, this was wrong** | ✅ `rels_PREREQUISITE_OF.csv`, 28 rows |
| 11 `Concept` nodes' hand-set `bloom_level` / `pedagogy_tag` | May not exist in `lms_concept` — **both columns confirmed present and populated** | ✅ `nodes_Concept.csv`, 11 rows |
| 40 `CompetencyStandards`, 8 `LearningObjects`, 3 `AssessmentTypology` | Source table unconfirmed | ✅ 40 / 8 / 3 rows |

The export deliberately went wider than this table: **every** label under 1,000 nodes and **every**
relationship type under 1,000 edges was captured, because several small labels have an unconfirmed
source and a few hundred KB is cheaper than guessing wrong. Everything skipped is re-derivable from
MariaDB. Full manifest in the backup directory's `README.md`.

> **Correction to row 1 — `PREREQUISITE_OF` is not hand-authored.** All 28 edges sit inside one
> chapter (`chapter_id` 8560, `lesson_id` 1874, `sub_institute_id` 1) across exactly 8 concepts,
> ids 4–11, and every edge runs low-id → high-id. C(8,2) = 28: the set is the **complete transitive
> closure of a linear ordering by concept id**, which marks `Introduction to rational numbers` a
> prerequisite of `The square root spiral` and of everything in between. That is machine-generated,
> not pedagogy. It is regenerable in one line of Cypher, so this row is *not* irreplaceable — the
> CSV is kept regardless, as evidence of what the old graph held.
>
> This is direct input to **PREREQ-SOURCE** (§3, OPEN): the current graph contains no genuine
> prerequisite signal, so that decision cannot be settled by mining it. It needs curriculum input or
> inference over the 1,372 `lms_concept` rows.
