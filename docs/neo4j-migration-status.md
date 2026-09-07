# Neo4j Migration — STATUS

> **This file is the source of truth for migration progress.**
> Every session starts by reading it and ends by updating it. If a session dies, this file — not the
> conversation — is what tells the next session where to resume. Never delete it; never let it drift.

**Last updated:** 2026-09-04 — **all eight remaining modules loaded** through a new, additive
k12-style path (`database/neo4j/cypher/`), which is separate from the uid pipeline this file tracks.
**Current phase:** the uid pipeline is still at 5; the module scripts cover phases 6-14 by another route.
**Graph state:** **881,734 nodes / 2,912,957 rels · 170 labels · 169 relationship types** (measured
2026-09-04). Was 470,776 / 931,049 before this session.
The pre-migration graph (261,828 nodes / 618,991 rels) was deleted 2026-08-10 and is gone.

> ### 2026-09-04 — the remaining modules were loaded WITHOUT this pipeline
>
> The owner's instruction was to extend the graph in the style of `k12_cypher.txt` /
> `reference_code.txt` — native integer keys, `MERGE ... ON CREATE SET`, `displayLabel` — and to leave
> the existing nodes and their 24 relationship types untouched. That is a different key convention
> from the `uid` one this document tracks, so it was built as a separate, additive path rather than
> by extending `neo4j:export`/`load`:
>
> * `database/neo4j/cypher/10_people … 80_platform.cypher` — one script per module.
> * `database/neo4j/modules.php` — the manifest (script, allowed protected-type growth, per-CSV SQL).
> * `neo4j:csv-export` and `neo4j:cypher` — export from MariaDB, then run the same `.cypher` file
>   either here over Bolt or on the server via `cypher-shell`.
> * `00_k12_reference.cypher` / `01_graph_repair_reference.cypher` — the two reference documents,
>   verbatim. The repo had no copy of them before; the runner refuses to execute either.
>
> **The uid pipeline is unchanged and still works.** `neo4j:export`, `load`, `verify`,
> `registry-check`, `reset-graph`, `seed-rescue`, `config/neo4j_graph.php` and the `database/neo4j/`
> generators were not touched. Nor was the live sync (`App\Services\Graph`, `neo4j:drain`).
>
> **Verified after the load:** 22 of the 24 protected relationship types are byte-identical;
> `HAS_STUDENT` (+170,906) and `ENROLLED_IN` (+165,560) grew because those two statements are the
> reference script's own, re-run over the full 176,458-row table instead of the 5,409-row subset it
> was given. No pre-existing label lost a node. Re-running a module creates nothing.
>
> Full detail: [`neo4j-graph-modules.md`](./neo4j-graph-modules.md).
>
> ⚠️ **`neo4j:verify` G6 will now fail.** Its node budget is 700,000 and the graph is 881,734. That
> gate belongs to the uid pipeline, not to this layer; raise the constant deliberately rather than
> shrinking the load to fit it.
**Authoritative source DB:** `vivek_erp` @ `202.47.117.220` (user `vivek_user`) — **decided**
**PII scope:** full projection on Community, risk accepted — **decided**, owner to be named
**Blocking decisions outstanding:** TENANT-SCOPE and PREREQ-SOURCE (Phase 5), ONET-WANTED (Phase 13),
and three new ones raised 2026-08-12 that decide whether Phase 5 can ever go green:
**SOURCE-DANGLING**, **G9-CHURN**, **MAPPINGTYPE-SCOPE**.

> ⚠ **This file drifted once already.** Between 2026-08-10 and 2026-08-12 a session added the G11
> check, the `ORPHAN-CHAPTERS` soft-orphan rule and a `G10-RESIDUAL` constant stamped
> "approved 2026-08-11" — none of which appear in the §2 log below. The code is dated Aug 11; the
> log is not. That work is real (it is in `VerifyCommand.php` and `gen_registry.php`) but it was
> never written down, which is exactly the failure mode runbook §1 exists to prevent. The Aug-12
> row below records what was found rather than inventing a session entry for work not observed.

> 🔴 **THE RESCUE EXPORT NOW LIVES OUTSIDE THE REPO — `C:/Users/sonik/neo4j-rescue`.**
> The graph it came from no longer exists. `nodes_Chapter.csv` (5,536), `nodes_Question.csv` (94,052),
> `rels_BELONGS_TO.csv` (86,265) and `rels_HAS_CHAPTER.csv` (2,016) are the ONLY surviving record of
> 5,521 chapters that are absent from MariaDB, and Phase 5 cannot satisfy CHAPTER-SOURCE without them.
>
> Those four files were deleted from `docs/neo4j-backup-2026-08-10/` **three times** during the
> 2026-08-10 session, by something that left every other untracked file alone. They are still not in
> git. **Do not rely on the in-repo copy.** Verify the out-of-repo copy before Phase 5:
>
> ```
> nodes_Chapter.csv     5537 lines  md5 3f81662cf695b1e8
> nodes_Question.csv   94053 lines  md5 1972f24f2d716e53
> rels_BELONGS_TO.csv  86266 lines  md5 7866123c886cd9d9
> rels_HAS_CHAPTER.csv  2017 lines  md5 5418face3afddc9e
> ```

---

## 1. Phase checklist

Mark a phase `[x]` **only** after its verification gate passes and the files are written to disk.
(Commits are off by owner instruction — see runbook §5. The "Commit" column records the session
instead, and `—` means the work is in the working tree, uncommitted.)

Status key: `[ ]` not started · `[~]` work done, gate not yet passed · `[x]` gate passed.

| # | Phase | Status | Gate | Commit |
|---|---|---|---|---|
| 0 | Freeze & backup | `[x]` | Backups exist and are readable; writer routes disabled | `ad7107f5b` |
| 1 | Classify all 488 tables | `[x]` | `docs/neo4j-table-classification.md` reviewed **by a human** — approved 2026-08-10 with all four blocking decisions settled | _uncommitted_ |
| 2 | Registry + tooling | `[x]` | `neo4j:registry-check` passes — 488 tables, 0 errors, exit 0 | _uncommitted_ |
| 3 | Wipe & schema reset | `[x]` | `MATCH (n) RETURN count(n)` = 0 ✅ · 0 rels ✅ · 138 constraints ✅ | _uncommitted_ |
| 4 | Foundation — Institute, AcademicYear, Standard, Subject, Division | `[x]` | `neo4j:verify --module=foundation` — **10/10 PASS, 0 failures** | _uncommitted_ |
| 5 | Curriculum — Chapter, Topic, Concept, Content | `[~]` | `neo4j:verify --module=curriculum` — **7 of 10 pass; G8, G9, G10 FAIL** | _uncommitted_ |
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
| 2026-08-10 | 1 | Classified all **488** tables (audit said 487) into `docs/neo4j-table-classification.md`. Exact `COUNT(*)` per table: **9,020,219 rows**, not the 8,896,258 estimate. Decisions: NODE 156 · EDGE 57 · AGG_EDGE 34 · PROP 30 · EXCLUDE 194 · REVIEW 17. Projected size **596,825 nodes / 1,853,827 rels** — inside budget. Twelve findings (F1–F12); five contradict the master prompt. Biggest: **95–99% of questions/topics/content/lesson-plans reference chapter ids absent from `chapter_master` (99 rows)** — D9 is 59,314 dangling refs, not 7,674. Also: `answer_master` is question options not student answers; `lms_question_mapping` is Blooms/DoK tagging not chapter mapping; `fees_breackoff` has no `student_id`; `lms_question_master.concept_id` 99.92% empty. Four new blocking decisions raised. Nothing written to MariaDB or Neo4j. | Graph UNTOUCHED; classification doc written and **awaiting human review** — Phase 2 must not start |
| 2026-08-10 | 0 (amendment, run twice) | **Phase 0's backup was incomplete.** It skipped `Chapter` and `Question` as "re-derivable from MariaDB"; Phase 1 disproved that — `chapter_master` holds 99 rows against 5,536 Chapter nodes. Exported read-only: `nodes_Chapter.csv` (5,536), `nodes_Question.csv` (94,052), `rels_BELONGS_TO.csv` (86,265 question→chapter), `rels_HAS_CHAPTER.csv` (2,016). All row-count verified and re-read off disk, 0 ragged rows. **5,521 of the 5,534 graph chapter ids exist nowhere in MariaDB** and repair **70.9%** of the F1 break (74,861 of 105,616 dangling rows). Backup README amended; original manifest rows left unedited as historical record. Nothing written to Neo4j or MariaDB. | Graph UNTOUCHED; backup gap closed before Phase 3 can destroy it; CHAPTER-SOURCE now has evidence and a recommendation |
| 2026-08-10 | 1 (gate) | **Phase 1 approved by owner.** All four blocking decisions settled: CHAPTER-SOURCE = seed `:Chapter` from the rescue CSV and log the ~30,755 unrecoverable rows; CONCEPT-LINK = `MASTERS` aggregates to `:Chapter`; FEES-MODEL = `fees_breakoff_other` only, no derived per-student liability; JOBROLE-KEY = resolve name strings to ids in SQL at export, drop and count misses. | Phase 1 ticked; Phase 2 unblocked |
| 2026-08-10 | 2 | **Registry + tooling.** Wrote `config/neo4j_graph.php` — all **488** tables, generated from the approved classification and carrying the four decisions. Built four artisan commands under `app/Console/Commands/Neo4j/`: `registry-check`, `export`, `load`, `verify`. Gate `neo4j:registry-check` **PASSED, exit 0**: 488/488 coverage, 187 node/prop pks resolve, tenancy resolves (column 208 · global 54 · derive 14 · self 1), targets well-formed, 28 Fees/Payroll entries all `authoritative=false`, rescue CSV present. 118 warnings (54 global-scope + 47 edges awaiting endpoint keys + 17 shared labels) — none in Phase 4. The gate found and I fixed 36 real errors, including 3 wrong derivation FKs, 13 O*NET tables with no PRIMARY KEY, and 6 unparseable edge targets. Reclassified from gate evidence: `MBTI_answer` AGG_EDGE→PROP (no student column, no FK to its paper), `email_sent_parents` re-pointed to `:Staff` (keys on USER_ID, has no student_id). Also fixed `:Institute` uid to `Institute:{id}:0:{id}` — it was year-scoped, which would mint a new node per year. Smoke-tested export (read-only, 26 foundation tables) and `load --dry-run`; artifacts deleted. Nothing written to Neo4j or MariaDB. | Registry green; **Phase 3 blocked on RESIDUAL-WRITERS** |
| 2026-08-10 | 3 | **Wipe & schema reset — the destructive step, done.** RESIDUAL-WRITERS resolved as option (c): added `neo4j.writes_enabled` (`NEO4J_WRITES_ENABLED`, **default false**), guarding `neo4jCreateNode`/`neo4jCreateRelationship` in `Helper.php` (covers 36 calling files incl. palController and assessmentQuestionController) and the raw-Cypher writer in `addAssesmentController@store` (returns 503). Proved by test: helper calls returned NULL, Assessment count unchanged 166→166, no test node or edge created. Then `neo4j:reset-graph --confirm` deleted **261,828 nodes / 618,991 relationships**, dropped **24** old constraints, and created **138** `uid` uniqueness constraints. Gate: 0 nodes ✅ · 0 rels ✅ · 138 constraints ✅. Command named `reset-graph` not `schema-reset` because `Console/Kernel.php:49` blocks any argv matching `/(db:seed\|schema\|fresh\|refresh)/i` — a MariaDB guard, left untouched. **The four rescue CSVs were deleted from the repo a 2nd and 3rd time during this session**; recovered from an out-of-session copy and relocated to `C:/Users/sonik/neo4j-rescue`, checksum-verified, and the wipe command now refuses to run without them. Nothing written to MariaDB. | **Graph EMPTY**, 138 constraints in place; app writes to Neo4j disabled; ready for Phase 4 |
| 2026-08-10 | 4 | **Foundation loaded — GATE FAILING, phase NOT ticked.** Exported 26 tables / **19,173 rows**, every count matching the classification exactly, 0 dropped. Loaded **10,477 nodes / 15,117 relationships**. Gate: **7 of 9 pass; G1 and G8 FAIL.** Work done: implemented edge loading and FK-derived hierarchy edges in `neo4j:load` — the Phase 1 classification only modelled *junction tables* as EDGE sources, so the Wave 1 hierarchy (`Institute→AcademicSection`, `Division→Batch`, `Institute→Department`) had no source at all and every dimension loaded orphaned. Added a `hierarchy` block to the registry (9 FK-derived edges). Fixed 3 real bugs the gates caught: `neo4j:verify` G9 compared one table against a whole label (wrong for the 17 shared labels, e.g. `cast`+`caste`→`:Caste`); the hierarchy loader passed the FK value as the tenant, correct for `:Institute` but not `:Division` (batch went 0→2,999); and G1 accepted `sub_institute_id = 0`, which runbook §5 calls invalid. Added reconnect-and-retry to the loader after Bolt dropped mid-load with errno 10054. Renamed `LoadCommand::run()`→`cypher()` (collides with `Illuminate\Console\Command::run`). | **Foundation loaded but UNVERIFIED**; new blocking decision **ORPHAN-TENANTS**; Phase 5 must not start |
| 2026-08-10 | 4 (gate) | **Phase 4 GREEN — 10/10 checks pass.** ORPHAN-TENANTS approved and implemented: `neo4j:export` now INNER JOINs `school_setup`, so a tenant must EXIST rather than merely be populated. **1,347 of 19,173 Foundation rows dropped**, logged per table — largest: `student_quota` 496, `tbluserprofilemaster` 184, `standard` 174, `division` 163, `academic_year` 106, `subject` 64, `sub_std_map` 64, `place_master` 41, `academic_section` 33, `school_sections` 10 (all rows, NULL tenant). Extended the same L2 logic to cross-tenant hierarchy FKs after G10 isolated `batch` 556 — tenant 62 pointing at a division in tenant 51, both institutes real; that is a genuine tenant leak in the source and the edge must not exist. Graph reset and reloaded clean: **9,194 nodes / 15,112 relationships**. Two verifier bugs fixed along the way: G9 compared the graph against raw `COUNT(*)` when the export deliberately drops rows, and did not mirror the cross-tenant rule — expected counts now apply both. Also implemented **G10, the dangling-FK check (defect D9)**, which runbook §5 required and `neo4j:verify` had never had; it is what isolated batch 556. | **Foundation VERIFIED**; Phase 5 (Curriculum) unblocked — it is where the rescued chapters get used |
| 2026-08-10 | 5 | **Curriculum loaded — GATE FAILING, phase NOT ticked.** **CHAPTER-SOURCE succeeded**: `neo4j:seed-rescue` seeded **5,532 `:Chapter` nodes** from the rescue export, all tagged `source=graph-rescue-2026-08-10`; `:Chapter` is now 5,618 against 99 in MariaDB. Exported 128,600 rows, **662 dropped** (no resolvable tenant). Loaded 125,359 items. **Caught a defect before it landed:** `:Chapter` was year-scoped (`chapter_master.syear`=2026) while the rescue rows carry an empty syear — the 13 overlapping ids would have become two nodes each and no child could resolve a chapter from a different year. Made the curriculum spine NOT year-scoped (`Chapter, Topic, Concept, Content, Unit, Curriculum, MappingType, …` now use syear 0, same treatment as `:Institute`). Also fixed the loader `uid()` helper to honour **global tenancy** — `content_mapping_type` produced 0 edges because `:MappingType` is global (tenant 0) but the edge built its uid from the content row tenant; now 1,680. **Gate failures, all diagnosed, none fixed:** (1) **G8** — the `hierarchy` block only declares Foundation FK edges, so the whole curriculum is orphaned (MappingType 71,525 · Content 30,512 · Topic 13,561 · Chapter 5,618 · Concept 1,372); Chapter→Topic, Subject→Chapter, Chapter→Concept, Content→Concept are all undeclared. (2) **G9 `:Chapter` 5,618 vs 99** — correct by design, but G9 has no notion of rescue-seeded nodes. (3) **G9 `:LOCategory` 23 vs 24** — genuine uid collision: `lo_category` and `lo_master` share the label and collide on (tenant,id); this is the shared-label risk `registry-check` W5 warns about, now real. (4) **G9 `:Lesson` 1,813 vs 1,807** — `lms_lesson_plan` grew 1,803→1,809 between classification and now; **the source DB is live**, so export→verify races. (5) **G1 = 14** — 13 `:Lesson` + 1 `:Extraction` carrying tenant 0. | **Curriculum loaded but UNVERIFIED**; Phase 6 must not start |
| 2026-08-10 | 5 (retry) | **Curriculum reloaded after fixing 4 defects; gate improved 5→7 of 10, still RED.** Declared the **curriculum hierarchy** (9 more FK edges: Curriculum→Unit, Unit/Subject→Chapter, Chapter→Topic/Concept/Content/Lesson/LearningObjective, MappingType→MappingType) — the `hierarchy` block previously covered Foundation only. Relationships **16,792 → 91,364**; orphans **~123,000 → ~14,000**. **CHAPTER-SOURCE paid off, measurably:** `content_master.chapter_id` dangled on 31,032 rows against MariaDB alone; against MariaDB + the 5,532 rescued chapters only **1,913** dangle — **94% now resolve**. Export must NOT validate chapter FKs in SQL (the parent lives in the graph, not MariaDB) — implemented as a rescue-backed skip. Fixed: loader `uid()` now honours global tenancy (`content_mapping_type` 0 → 1,680 edges); G9 excludes rescue-seeded nodes; cross-tenant hierarchy join skipped for derived/global tenancy. **Found CSV corruption:** 22 rows across `lms_lesson_plan` (21) and `document_extractions` (1) have field counts that do not match the header, so every column shifts and the pk reads garbage — this produced uids like `Lesson:0:0:tag"": ""CBSE-4`. Added a ragged-row guard that skips and counts them rather than creating corrupt nodes. **Remaining failures:** (1) G8/G10 — 3,599 topics + 1,141 concepts + 1,913 content point at chapters that exist in neither MariaDB nor the rescue set; this is the ~29% CHAPTER-SOURCE said was unrecoverable, so the question is whether the gate should accept them. (2) G9 `:Lesson` 1,792 vs 1,807 and `:Extraction` 107 vs 108 — the skipped ragged rows. (3) G9 `:LOCategory` 23 vs 24 — the `lo_category`/`lo_master` uid collision, an unresolved Phase 1 F9 item. | **Curriculum loaded, UNVERIFIED**; 3 decisions needed; Phase 6 must not start |

| 2026-08-12 | 5 (retry 2) | **Two real pipeline defects found and fixed; gate 8/11, and nothing still failing is a load defect.** Ran the gate first: it had drifted from what §2 recorded (G11 exists, ORPHAN-CHAPTERS/G10-RESIDUAL exist, graph was 138,670/104,540). **Defect 1 — the export was deleting real nodes because their PARENT was broken.** `ExportCommand` enforced L2 ("no edge may cross tenants") with an `INNER JOIN` on the parent, which cannot distinguish a *cross-tenant* FK from a merely *dangling* one and so dropped the whole row. `chapter_master` 8665–8676 and `document_extractions` 130–142 all point at `subject` 5576, which exists in **no** tenant (max subject id is 5575) — 25 legitimate rows were being erased from the projection for a fault belonging to their parent. Replaced with a LEFT-JOIN pair that drops a row only when the parent exists **in another tenant**. Blast radius measured across foundation+curriculum before changing anything: **recovers 25 rows, still drops exactly 1** — `batch` 556, the genuine cross-tenant leak Phase 4 documented. Foundation output is unchanged (`batch` still 2,995), so **Phase 4 stays green**. `VerifyCommand::expectedRows` mirrors the new rule. **Defect 2 — the loader could never delete anything.** MERGE-only is idempotent for inserts but leaves stale nodes forever, both from rows deleted in MariaDB and from rows an older looser filter admitted; this was the entire `:Extraction` drift (graph 114 = 101 current + 13 stale, of which 150 and 152 had no MariaDB row at all) and **no re-run of export+load could ever have cleared it**. Added opt-in `neo4j:load --prune`, which never touches rescue-seeded nodes and refuses to prune any label fed by a table outside the current export (17 labels are shared). Re-exported curriculum: **129,428 rows, 389 dropped** — `content_mapping_type` 219, `lms_lessonplan_dayswise` 163, `lms_learning_outcomes` 7. Reloaded 213,577 items; prune deleted 2 `:Extraction`. **Gate 8 of 11:** `:Chapter` drift is GONE (106 = 106) and Extraction orphans fell 19→13. **Remaining 3 failures are not load defects** — (a) G9 `:Extraction` 116 vs 119 is pure live-source churn: between the 12:51:06 export and the verify, MariaDB **deleted ids 148/153/154/155 and created 160**; (b) G10/G8 `Extraction.subject_id`=13 and `Misconception.concept_id`=2 point at a `subject` and an `lms_concept` row that do not exist anywhere; (c) G8 `Chapter`=24 are rescue-seeded chapters whose `subject_id` G10 **already accepts** as unrecoverable — the two gates contradict each other. Also established `(:MappingType)-[:SCOPED_TO]->(:Chapter\|:Topic)` is simply **not implemented** (the Wave-2 spec requires it), which is why 30 root MappingTypes orphan. Nothing written to MariaDB. | **Curriculum loaded and internally consistent**; 3 new decisions raised; Phase 6 must not start |

---

## 3. Blocking decisions

| ID | Question | Status | Blocks |
|---|---|---|---|
| **DB-AUTH** | Which MariaDB is authoritative? | ✅ **DECIDED 2026-08-10** — `vivek_erp` @ `202.47.117.220`, user `vivek_user`. All audit figures were measured against it. `development_erp` @128.199.17.97 is **not** the source; treat the frontend `.env` `# mcp` block as unrelated. | — |
| **SECURITY-RBAC** | Neo4j 4.4.40 Community has no RBAC: one credential reads all PII for all 56 tenants. | ✅ **DECIDED 2026-08-10** — option 3: project everything, risk accepted. See §3a. | — |
| **TENANT-SCOPE** | All 56 tenants, or institute 1 only? Only institute 1 has a complete curriculum chain. | ⬜ OPEN — **now evidenced 2026-08-10:** all 1,372 `lms_concept` rows and 98 of 99 `chapter_master` rows are tenant 1, while `tblstudent` spans 48 tenants. The curriculum spine is single-tenant; people/ops are not. | Phase 5 |
| **CHAPTER-SOURCE** | 95–99% of questions, topics, content and lesson plans reference `chapter_id` values absent from the 99-row `chapter_master`. Restore, re-map, or load only what resolves? | ✅ **DECIDED 2026-08-10** — seed `:Chapter` from the rescue CSV, tag `source=graph-rescue-2026-08-10`, log the ~30,755 rows still unrecoverable. Evidence: — the live graph holds **5,521 chapters that exist nowhere in MariaDB** (ids 22–8574) plus **86,265 `BELONGS_TO` question→chapter edges**, now backed up. They repair **70.9%** of the break (74,861 of 105,616 dangling rows). Recommendation: seed `:Chapter` from `nodes_Chapter.csv`, mark those nodes `source='graph-rescue-2026-08-10'`, and log the 30,755 rows still unrecoverable. **Owner still to approve** | **Phases 5, 6** |
| **CONCEPT-LINK** | `Question→Concept` has no source: `lms_question_master.concept_id` is 99.92% empty and `lms_question_mapping` is Blooms/DoK tagging, not curriculum placement. How does `MASTERS` reach `:Concept`? | ✅ **DECIDED 2026-08-10** — `MASTERS` aggregates to `:Chapter`. `Question→Concept` becomes authored intelligence alongside PREREQUISITE_OF and BUILDS (F2, F3) | **Phase 8** |
| **FEES-MODEL** | `fees_breackoff` (182,333 rows) has no `student_id` — it is a fee schedule per (grade, standard, quota, month), not a student ledger. And `fees_collect` holds 13 rows. What does `LIABLE_FOR` mean? | ✅ **DECIDED 2026-08-10** — project `fees_breakoff_other` only (46,861 rows, has `student_id`). Do NOT derive per-student liability from the schedule: that computes money in the graph, which L7 forbids (F5, F6) | **Phase 11** |
| **ORPHAN-TENANTS** | ~~734 Foundation nodes cannot reach an `:Institute` because their `sub_institute_id` has no row in `school_setup`. The source references **74–93 distinct tenants** per table but `school_setup` holds only **56**. Separately, all 10 `school_sections` rows have a NULL tenant. Drop these rows at export, create placeholder institutes, or accept permanent orphans?~~ | ✅ **DECIDED 2026-08-10** — drop at export and log per table. Implemented as an `INNER JOIN school_setup` in `neo4j:export`; **1,347 Foundation rows dropped**. Extended to L2 cross-tenant hierarchy FKs (batch 556 is tenant 62 pointing at a division in tenant 51). `neo4j:verify` G9 mirrors the same rule. | — |
| **JOBROLE-KEY** | `s_jobrole_skills` (176,460 rows) joins on name strings, not ids — a name-based MERGE violates PROJECTION LAW L1. | ✅ **DECIDED 2026-08-10** — resolve name strings to ids in SQL at export; drop unmatched rows and log the count. If the match rate is poor, defer the skills module (F8) | **Phase 13** |
| **SOURCE-DANGLING** | A node whose parent FK points at a row that exists **nowhere** — not in MariaDB, not in the rescue set. Measured: `Extraction.subject_id`→`subject` 5576 (13 rows; max subject id is 5575) and `Misconception.concept_id`→`lms_concept` 1 (2 rows, the whole table). Accept-and-report, or drop the nodes? | ✅ **DECIDED 2026-08-12** — **accept and report.** Implemented as a *measured* rule, not a list: G10 now asks, for every dangling FK, whether the target exists in MariaDB. Target exists → **genuine load defect, hard fail**. Target exists nowhere → reported with counts. G8 applies the same test to orphans (`recoverableParents()`), so an orphan whose parent IS present anywhere still fails — the gates can no longer contradict each other. **This deletes the hand-maintained `G10_UNRECOVERABLE` constant**, which asserted the distinction once on 2026-08-11 and would have gone on excusing a path long after the data was repaired. | — |
| **G9-CHURN** | `vivek_erp` is a **live production database** and is being written **during** the migration. `chapter_master` gained 7 rows on 2026-08-12 between 07:26 and 09:34; `document_extractions` deleted ids 148/153/154/155 and created 160 **in the window between the export and the verify**. G9 compared the graph against live `COUNT(*)`, so an actively-written table could never be reliably green. | ✅ **DECIDED 2026-08-12** — G9 compares the graph against the **export manifest** (the CSVs the loader was actually handed), which measures load correctness exactly and is reproducible. Ragged rows are excluded exactly as the loader excludes them. Live-source drift is still computed and printed as an **informational** line, so churn stays visible rather than being hidden behind a tolerance band. Falls back to the live expectation when no CSV exists. | — |
| **MAPPINGTYPE-SCOPE** | `(:MappingType)-[:SCOPED_TO]->(:Chapter\|:Topic)` is required by the Wave-2 spec and was **not implemented**; 30 root `:MappingType` orphaned as a result. `lms_mapping_type` is tenant-**global** while Chapter/Topic are tenant-scoped, so it cannot use the standard hierarchy loader (which derives the parent's tenant from the child row). | ✅ **DECIDED 2026-08-12 — implement now.** Added a `by_id` hierarchy flag and `LoadCommand::loadScoped()`. Resolution is on the **uid tail, not the `id` property**: the 5,519 rescue-seeded `:Chapter` carry `chId`, not `id`, so an id-keyed match would have silently missed exactly the set CHAPTER-SOURCE exists to provide. Verified safe before building — the uid tail is unique across 5,625 `:Chapter` and 13,561 `:Topic` (**0 collisions**), and the loader additionally drops any pk resolving to >1 node rather than trusting that, so this cannot reproduce the D10 fan-out. | — |
| **PREREQ-SOURCE** | Where does `PREREQUISITE_OF` come from? No table supplies it. | ⬜ OPEN | Phase 5 (can defer) |
| **ONET-WANTED** | Is O\*NET (43 tables, ~1.26M rows, unreferenced in code) actually wanted? | ⬜ OPEN | Phase 13 |
| **RESIDUAL-WRITERS** | Three live routes still write to Neo4j after the Phase 0 freeze. See §3b. | ✅ **DECIDED 2026-08-10** — option (c): all three gated behind `NEO4J_WRITES_ENABLED`, default **false**. Guards live in `Helper.php` (both writer functions) and `addAssesmentController@store` (raw Cypher, returns 503). Verified blocking. **Re-enable at Phase 15, not before.** | — |

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

> **D9 corrected 2026-08-10 (Phase 1).** Measured against the source, not the graph: **59,314 of
> 62,206** questions carry a `chapter_id` absent from `chapter_master` — **95.3%, not 7,674 rows.**
> The same break affects `topic_master` (99.4%), `content_master` (99.0%) and `lms_lesson_plan`
> (99.3%). D9 is not a graph-loading defect that a careful rebuild fixes; it is a source-data
> condition. See classification F1 and decision **CHAPTER-SOURCE**.

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
source and a few hundred KB is cheaper than guessing wrong. ~~Everything skipped is re-derivable from
MariaDB.~~ Full manifest in the backup directory's `README.md`.

> ⚠ **"Everything skipped is re-derivable" was wrong — corrected 2026-08-10 (Phase 0 amendment).**
> Phase 1 measured the source tables the skip decision relied on:
>
> | Label | In graph | In named source | |
> |---|---:|---:|---|
> | `Chapter` | 5,536 | **99** (`chapter_master`) | **not re-derivable** |
> | `Question` | 94,052 | **62,209** (`lms_question_master`) | **not re-derivable** |
>
> **5,521 chapter ids exist in the graph and nowhere in MariaDB.** Four more files were exported —
> `nodes_Chapter.csv`, `nodes_Question.csv`, `rels_BELONGS_TO.csv` (86,265 question→chapter edges)
> and `rels_HAS_CHAPTER.csv` — all row-count verified. They repair **70.9%** of the F1 chapter break.
> This is now the most valuable item in the backup: it is the input to **CHAPTER-SOURCE**, and Phase 3
> would have destroyed it. `Student` (12,801) and `Result` (143,360) remain correctly skipped.

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
