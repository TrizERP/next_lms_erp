# Neo4j Migration — STATUS

> **This file is the source of truth for migration progress.**
> Every session starts by reading it and ends by updating it. If a session dies, this file — not the
> conversation — is what tells the next session where to resume. Never delete it; never let it drift.

**Last updated:** 2026-08-10 (seeded; DB-AUTH and SECURITY-RBAC decided)
**Current phase:** 0
**Graph state:** ORIGINAL — pre-migration graph still in place, untouched
**Authoritative source DB:** `vivek_erp` @ `202.47.117.220` (user `vivek_user`) — **decided**
**PII scope:** full projection on Community, risk accepted — **decided**, owner to be named
**Blocking decisions outstanding:** TENANT-SCOPE, PREREQ-SOURCE, ONET-WANTED (none block Phase 0–4)

---

## 1. Phase checklist

Mark a phase `[x]` **only** after its verification gate passes and the work is committed.

| # | Phase | Status | Gate | Commit |
|---|---|---|---|---|
| 0 | Freeze & backup | `[ ]` | Backups exist and are readable; writer routes disabled | — |
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

---

## 3. Blocking decisions

| ID | Question | Status | Blocks |
|---|---|---|---|
| **DB-AUTH** | Which MariaDB is authoritative? | ✅ **DECIDED 2026-08-10** — `vivek_erp` @ `202.47.117.220`, user `vivek_user`. All audit figures were measured against it. `development_erp` @128.199.17.97 is **not** the source; treat the frontend `.env` `# mcp` block as unrelated. | — |
| **SECURITY-RBAC** | Neo4j 4.4.40 Community has no RBAC: one credential reads all PII for all 56 tenants. | ✅ **DECIDED 2026-08-10** — option 3: project everything, risk accepted. See §3a. | — |
| **TENANT-SCOPE** | All 56 tenants, or institute 1 only? Only institute 1 has a complete curriculum chain. | ⬜ OPEN | Phase 5 |
| **PREREQ-SOURCE** | Where does `PREREQUISITE_OF` come from? No table supplies it. | ⬜ OPEN | Phase 5 (can defer) |
| **ONET-WANTED** | Is O\*NET (43 tables, ~1.26M rows, unreferenced in code) actually wanted? | ⬜ OPEN | Phase 13 |

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

| Item | Why it cannot be regenerated | Backed up? |
|---|---|---|
| 28 `PREREQUISITE_OF` edges | No MariaDB table supplies them; hand-authored | ⬜ **NO** |
| 11 `Concept` nodes' hand-set `bloom_level` / `pedagogy_tag` | May not exist in `lms_concept` | ⬜ NO |
| 40 `CompetencyStandards`, 8 `LearningObjects`, 3 `AssessmentTypology` | Source table unconfirmed | ⬜ NO |

Everything else in the graph is re-derivable from MariaDB.
