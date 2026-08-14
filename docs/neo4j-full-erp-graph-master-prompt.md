# Full-ERP Neo4j Graph — Master Prompt

**Date:** 2026-08-10
**Scope:** *All* modules, sub-modules and functionalities projected into Neo4j.
**Supersedes the scope of:** [`neo4j-module-graph-scope.md`](./neo4j-module-graph-scope.md) (~40 tables) — that doc's *modelling rules* still stand; only its exclusion list is overridden.
**Builds on:** [`neo4j-graph-audit-and-rebuild-prompt.md`](./neo4j-graph-audit-and-rebuild-prompt.md) — the 8 verified defects and the rebuild-not-repair decision carry forward unchanged.

---

## 1. What changes, and what does not

The earlier docs recommended a curated ~40-table projection and excluded fees, HRMS, payroll, inventory, transport, hostel, library, admission and front-desk. **That exclusion is now reversed by decision.** All 487 tables are in scope for classification.

Three constraints from those docs survive the scope change, because they are about data shape rather than module boundaries:

1. **MariaDB stays authoritative for everything.** Neo4j is a derived, disposable read-model. Nothing writes to Neo4j except the projection.
2. **High-volume transactional tables are aggregated, never mirrored row-for-row.** This is not a scope question — 2.29M `lms_online_exam_answer` rows as nodes would make every traversal slower without answering a single new question.
3. **Money must reconcile.** Fees can be projected, but the graph must be explicitly marked non-authoritative and a reconciliation assertion must compare graph totals to MariaDB totals per tenant on every load. A fee figure from the graph that disagrees with the ledger is a business incident, not a bug.

### 1.1 Platform constraints — verified live

```
Neo4j Kernel 4.4.40, edition: community
Databases:  neo4j, system   (no others possible)
APOC / GDS procedures installed: 0
Index type: BTREE
```

Four consequences that shape everything below, and one of them is a blocker:

| Constraint | Consequence for full-ERP scope |
|---|---|
| **Community Edition → no RBAC** | There is **no way to restrict who reads what** inside the graph. One credential grants full read/write to every node. Projecting salary, student documents and fee ledgers means anyone with that credential sees all of it, for all 56 tenants. |
| **Community Edition → single database** | Only `neo4j` and `system`. O\*NET cannot live in its own database, and per-tenant database isolation is impossible. Tenant separation is *purely* application-enforced by `sub_institute_id` filters — and 88 cross-tenant edges show that already fails today. |
| **Community Edition → no property-existence constraints** | "`sub_institute_id` mandatory on every node" **cannot be enforced by a constraint**. It must be enforced by the loader and asserted by `neo4j:verify`. Uniqueness constraints work; existence and node-key constraints are Enterprise-only. |
| **No APOC / GDS** | No `apoc.periodic.iterate`, no `apoc.meta.schema`, no graph algorithms. Batching must use `CALL {} IN TRANSACTIONS` (available in 4.4) and schema validation must be hand-written Cypher. |

**Neo4j 4.4 is also past end-of-life** — it no longer receives security patches.

### 1.2 Security gate — blocking

`NEO4J_PASSWORD=admin`, browser served over **plain HTTP** at `http://dev.triz.co.in:7475`, WAN-exposed, on an **EOL** version with **no access control**. Today that instance holds curriculum and exam data. This scope change adds salary (`hrms_salary_certificate`, `hrms_emp_payroll_deduction`, `payroll_types`), student documents (`tblstudent_document`, 65,722 rows) and full fee ledgers for 56 schools.

Rotate the password, put bolt on TLS, and restrict network access **before** the first load containing those tables.

> ### ✅ Decided 2026-08-10 — full projection, risk accepted
>
> **Scope is full.** HR salary, student documents and complete fee ledgers all project. The absence
> of RBAC on Community Edition is an accepted risk, recorded in
> [`neo4j-migration-status.md` §3a](./neo4j-migration-status.md) with a risk owner to be named
> before Phase 10. Classify these tables normally — do not mark them `EXCLUDE`.
>
> **Hardening remains required.** These were common to every option and are not waived by the
> decision. All three must be done before Phase 10 (HR); none of them block Phases 0–9:
>
> 1. rotate `NEO4J_PASSWORD` off `admin`
> 2. TLS on bolt (`bolt+s://`) and HTTPS on the browser port
> 3. firewall `7475` / `7688` to known source IPs
>
> Plus a plan to move off EOL Neo4j 4.4. Track all four in STATUS §3a.

---

## 2. Feasibility

| | Value |
|---|---:|
| Tables in `vivek_erp` | **487** |
| With `sub_institute_id` | 331 (68%) |
| With `syear` | 166 |
| With a PRIMARY KEY | 471 |
| Empty | 88 |
| Total rows | ~8.9M |
| **Projected nodes (estimate)** | **~1.5–2M** |
| **Projected relationships (estimate)** | **~4–6M** |

Full-ERP coverage is comfortably within a single Neo4j instance. The load moves from ~15 minutes to roughly 30–60 minutes. **Size is not the risk here — model drift is.** At 40 tables an inconsistent relationship name is an annoyance; at 487 it makes the graph unqueryable. Section 4 exists to prevent that.

---

## 3. The classification method

487 hand-authored mappings cannot be written or maintained by hand. Every table is instead classified by **structural shape**, and the shape determines the projection. Six shapes, applied mechanically:

| Shape | Test | Projection |
|---|---|---|
| **ENTITY** | Own PK, own identity, referenced by other tables | `(:Label)` node |
| **JUNCTION** | 2–3 FK-ish columns + optional attributes, no independent identity | **relationship**, never a node |
| **EVENT-LOW** | Transactional/dated, < 50k rows | `(:Label)` node with time properties |
| **EVENT-HIGH** | Transactional/dated, ≥ 50k rows | **aggregate** into relationship properties |
| **LOOKUP** | < 100 rows, no `sub_institute_id`, pure enum/vocabulary | **property value** on the referencing node, not a node |
| **EXCLUDE** | Log, audit, scratch, cache, or empty-and-unreferenced | not projected; stub the registry entry |

Two rules that follow from this and are worth stating separately, because they are the most common modelling errors:

- **A junction table is an edge.** `student_optional_subject` (102,990), `s_jobrole_skills` (172,590), `transport_map_student` (30,191), `sub_std_map` (6,652), `hrms_departments_mapping` (53) are all relationships. Turning them into nodes adds a hop to every query and doubles the node count for zero information gain.
- **A 3-row enum is not a node.** `transport_vehicle_type` (3), `fees_cancel_type` (4), `inventory_item_type` (4), `hrms_weekdays` (7) become string properties. A node per enum value creates supernodes — one `:VehicleType` node with 690 incoming edges — which are a genuine performance problem in Neo4j.

---

## 4. Universal conventions

These are what keep 487 tables coherent. They are non-negotiable and machine-checkable.

**Labels.** PascalCase, singular, derived from the table with `_master` / `_data` / `_details` suffixes stripped. `inventory_item_master` → `:InventoryItem`. `transport_vehicle` → `:TransportVehicle`. Registered once in the registry; never invented ad hoc.

**Identity.** Exactly one uniqueness constraint per label, on `<labelCamelCase>Id`, equal to the MariaDB PK cast to integer. `:InventoryItem {inventoryItemId}`. One key property per label — never two (defect D2 was two competing keys on the same label).

**Properties.** Copied columns keep their **MariaDB column name verbatim** (snake_case) so every property traces to a source column. Only the identity property and `displayLabel` are derived. Cast at the boundary: `(int)`, `(float)`, ISO-8601 strings or `datetime()` for dates — `"5"` and `5` MERGE into different nodes.

**Tenancy.** `sub_institute_id` mandatory and indexed on every node from a table that has the column. **Not** part of the uniqueness key — MariaDB ids are globally unique auto-increment PKs, so composite keys cost lookups and buy nothing (verified; see audit doc §D3). For the 156 tables without the column, inherit it from the parent entity at projection time, or mark the label `global: true` in the registry.

**Relationships.** UPPER_SNAKE_CASE, a verb phrase reading source → target, stored **once in one direction only**. Neo4j traverses both directions at equal cost, so a reciprocal pair is pure overhead and a consistency hazard (defect D6). Banned: `BELONGS_TO`, `HAS_DATA`, `MAPPED_TO`, `RELATED_TO` and any type that does not say what the relationship *means*. Every type must be declared in the registry; an undeclared type is a load failure.

**Temporal.** 166 tables carry `syear`. Academic-year-scoped facts put `syear` on the **relationship**, not the node — one `:Student` across years, with `[:ENROLLED_IN {syear}]` per year. This is the generalised form of the D1 fix.

---

## 5. Module inventory

39 controller modules / 29 model directories. Domains and their principal tables, with row counts:

| Domain | Modules | Principal tables (rows) |
|---|---|---|
| **Academic core** | `lms`, `student`, `result`, `learning_outcome`, `school_setup`, `sqaa`, `ptm`, `report`, `MIS`, `template_result` | `lms_*` 30 tables/3.14M · `result_*` 28/1.44M · `tblstudent*` 13/564k · `timetable` 102k · `dicipline` 21k |
| **Assessment** | `lms`, `neo4J`, `counselling` | `lms_online_exam` 147k · `lms_online_exam_answer` **2.29M** · `answer_master` 459k · `lms_question_mapping` 533k · `question_paper` 5.4k · `counselling_*` 6 tables |
| **Admission** | `admission`, `onboarding`, `consent` | `admission_registration_v1` 836 · `admission_enquiry` 10 · `onboarding_*` 629 |
| **Finance** | `fees` | `fees_*` **34 tables/262k** — `fees_breackoff` 181k · `fees_breakoff_other` 47k · `fees_receipt_book_master` 9.8k · `fees_cancel` 7.9k. **9 gateway tables are empty** |
| **People / HR** | `HRMS`, `Payroll`, `leave`, `user`, `Auth` | `hrms_attendances` **352k** · `hrms_emp_leaves` 28k · `hrms_emp_payroll_deduction` 7.6k · `hrms_departments` 108 · `tbluser*` 4.5k · `payroll_types` 46 |
| **Operations** | `inventory`, `transportation`, `hostel_management`, `library`, `front_desk`, `inward_outward`, `visitor_management` | `library_book_circulations` 67k · `library_items` 36k · `library_books` 35.5k · `transport_map_student` 30k · `inward` 5.8k · `transport_stop` 2.8k · `inventory_*` 18/1.1k · `hostel_*` 7/128 · `visitor_master` 865 |
| **Skill / Career** | `skill` | `s_jobrole_skills` 173k · `s_skill_map_k_a` 133k · `master_skills` 15.8k · `s_jobrole` 5.6k · `onet_*` 25/928k · `o_*` 18/334k |
| **PAL** | `PAL`, `agenticAI` | `pal_*` **27 tables / 25 rows total** — effectively empty |
| **Platform** | `settings`, `Import`, `Mcp`, `calendar`, `custom_module`, `implementation`, `easy_com`, `bazar`, `h5p` | `sharebazar_*` 120k · `sms_*` 57k · `h5p_*` 71 · `task_*` 885 |

Two domain-level calls to make explicitly:

- **O\*NET (~1.26M rows across 43 tables)** is external reference data, not ERP data, and the audit found `s_skill_map_k_a` and ten `o_net_occupation_detail_*` tables referenced nowhere in the codebase. Project it as a **separate tenant-free reference subgraph** with `global: true`, loaded independently, so it can be dropped or reloaded without touching school data.
- **PAL's 27 tables hold 25 rows total.** Register them, project nothing, and let the loader pick them up automatically when they populate.

---

## 6. Master prompt

Run from `c:\xampp\htdocs\next_lms_erp`.

````text
You are building a FULL-ERP knowledge graph in Neo4j covering every module, sub-module and
functionality of the K-12 ERP.

REPOS
  Backend (Laravel):  c:\xampp\htdocs\next_lms_erp
  Frontend (Next.js): c:\lms_k12

SYSTEMS
  MariaDB (system of record): vivek_erp @ 202.47.117.220, user vivek_user   [.env]
  Neo4j (derived read-model):  bolt://dev.triz.co.in:7688, browser :7475, user neo4j
  NEVER write to MariaDB. Neo4j is disposable and rebuildable — a wrong graph is dropped, not patched.

PLATFORM — verified live, and it constrains the design. Do not assume Neo4j 5.
  Neo4j Kernel 4.4.40, edition COMMUNITY. Databases: neo4j + system only. APOC/GDS: NOT installed.
  Index type BTREE. Neo4j 4.4 is past end-of-life and receives no security patches.
  Therefore:
    - NO property-existence constraints (Enterprise-only). "sub_institute_id is mandatory" CANNOT be
      a constraint — enforce it in the loader and ASSERT it in neo4j:verify. Uniqueness works.
    - NO node-key constraints (Enterprise-only).
    - NO RBAC. One credential = full read/write to every node, every tenant. There is no way to
      restrict who reads salary or student documents inside the graph.
    - NO multi-database. O*NET and per-tenant isolation must use label/property namespacing, never
      a separate database.
    - NO apoc.periodic.iterate — batch with CALL {} IN TRANSACTIONS (supported in 4.4).
    - NO apoc.meta.schema — schema validation must be hand-written Cypher.
  Verify any Cypher you write against 4.4 syntax before running it.

READ FIRST — all three, before writing any code
  docs/neo4j-full-erp-graph-master-prompt.md        <- this scope; §3 shapes, §4 conventions
  docs/neo4j-graph-audit-and-rebuild-prompt.md      <- the 8 verified defects (D1-D8)
  docs/neo4j-module-graph-scope.md                  <- per-module table analysis (its EXCLUSION
                                                       list is superseded; its RULES still hold)

SCALE — 487 tables, 331 with sub_institute_id, 166 with syear, 471 with a PK, 88 empty, ~8.9M rows.
Target ~1.5-2M nodes and ~4-6M relationships. Size is not the risk. MODEL DRIFT is the risk: at 487
tables an inconsistent relationship name or a second key on a label makes the graph unqueryable.

DEFECTS IN THE CURRENT GRAPH THAT MUST NOT REAPPEAR (measured live, see audit doc)
  D1 :Student conflated person and enrollment -> every per-student aggregate inflated ~3.5x.
  D2 Two competing key properties on one label; 7 constraints keyed on an all-NULL column,
     enforcing nothing; 91 duplicate Result nodes as a result.
  D3 55% of nodes had no sub_institute_id; 88 relationships crossed tenants.
  D4 ~49,084 orphaned nodes, including 56% of all Students and 100% of Teachers.
  D5 Three competing representations of one learner (StuDetail / Student / Result.student_id).
  D6 Reciprocal duplicate edges; the same fact written at two different grains.
  D7 Cypher built by string concatenation with unlabelled MATCH.
  D8 Concept = 11 nodes against 1,372 rows in lms_concept.
  D9 BROKEN REFERENTIAL INTEGRITY: 7,674 :Question nodes carry a chapter_id with no matching
     :Chapter node. FK-style properties were copied without verifying the target exists.
  D10 Only 4,135 of 143,360 :Result nodes (2.9%) attach to exactly one student. The rest fan out
     across 2-9 students each. Tenant values include NULL (1,190 Students) and 0 (2 Students,
     2 Questions) — neither is a valid sub_institute_id.
  Live writers that can recreate these at any time: routes/web.php:639 /sync-neo4j
  (Neo4jSyncController, MERGEs on names) and web.php:721 /migrate-data (DataMigrationController).
  Both are REGISTERED ROUTES, not dead code. palController.php:1339 writes HAS_RESULT.

=============================================================================================
ARCHITECTURE — a DECLARATIVE REGISTRY drives everything. Do not hand-write 487 mappings.
=============================================================================================
Build config/neo4j_graph.php as the single source of truth. One entry per table:

  'chapter_master' => [
      'shape'   => 'ENTITY',           // ENTITY | JUNCTION | EVENT_LOW | EVENT_HIGH | LOOKUP | EXCLUDE
      'module'  => 'lms',
      'label'   => 'Chapter',
      'key'     => 'chapterId',        // = MariaDB PK, cast (int)
      'tenant'  => 'sub_institute_id', // null => 'global' => true
      'props'   => ['chapter_name','sort_order','standard_id','subject_id'],
      'display' => 'chapter_name',
      'rels'    => [
          ['type'=>'HAS_CHAPTER','dir'=>'in','from'=>'Subject','fk'=>'subject_id'],
      ],
  ],

  'student_optional_subject' => [
      'shape'  => 'JUNCTION',          // an EDGE, not a node
      'module' => 'student',
      'rel'    => ['type'=>'STUDIES','from'=>'Student','fk_from'=>'student_id',
                   'to'=>'Subject','fk_to'=>'subject_id','props'=>['syear']],
  ],

  'hrms_attendances' => [
      'shape'  => 'EVENT_HIGH',        // 351,908 rows — AGGREGATE, never nodes
      'module' => 'HRMS',
      'agg'    => ['rel'=>'ATTENDANCE_SUMMARY','from'=>'Staff','to'=>'AcademicYear',
                   'group_by'=>['user_id','syear'],
                   'metrics'=>['present_days'=>'SUM(status="P")','total_days'=>'COUNT(*)']],
  ],

Generic exporter and loader read this registry. Adding a table = adding a config entry, never code.

CLASSIFICATION RULES (§3 of the scope doc) — apply mechanically to all 487 tables:
  ENTITY     own PK + own identity + referenced elsewhere      -> node
  JUNCTION   2-3 FK columns + attributes, no own identity      -> RELATIONSHIP, never a node
  EVENT_LOW  transactional/dated, < 50k rows                   -> node with time properties
  EVENT_HIGH transactional/dated, >= 50k rows                  -> AGGREGATE into rel properties
  LOOKUP     < 100 rows, no sub_institute_id, pure enum        -> string PROPERTY, never a node
  EXCLUDE    log / audit / scratch / cache / empty+unreferenced -> stub the entry, project nothing

  A 3-row enum as a node creates a SUPERNODE (one :VehicleType with 690 incoming edges) and is a
  real performance problem. A junction table as a node adds a hop to every query for zero gain.

NAMING AND MODELLING CONVENTIONS — machine-checkable, no exceptions:
  1. Label: PascalCase singular, _master/_data/_details stripped. inventory_item_master
     -> :InventoryItem. Declared once in the registry, never invented ad hoc.
  2. Identity: exactly ONE uniqueness constraint per label, on <labelCamelCase>Id = MariaDB PK
     cast to (int). Never two key properties on one label (D2).
  3. Properties keep their MariaDB column name VERBATIM (snake_case) so each traces to its source.
     Only the identity property and displayLabel are derived. Every node carries displayLabel.
  4. Cast at the boundary: (int), (float), ISO-8601 / datetime() for dates. "5" and 5 MERGE into
     different nodes.
  5. sub_institute_id mandatory and INDEXED on every node whose table has it — but NOT part of the
     uniqueness key. MariaDB ids are globally unique auto-increment PKs, so a composite key costs
     lookups and buys no safety (verified). For the 156 tables lacking the column, inherit it from
     the parent entity at projection time or mark the label global:true.
     Community Edition CANNOT enforce this with a constraint. The loader must reject any row
     without a resolvable tenant, and neo4j:verify must assert it. Reject NULL and 0 — both occur
     in the current graph and neither is a valid sub_institute_id.
  5b. Never copy an FK-style property (chapter_id, subject_id, student_id) without creating the
     corresponding relationship AND verifying the target node exists. D9 is 7,674 questions
     pointing at chapters that were never created. A dangling id property is worse than no
     property: it looks like data and silently fails every join built on it.
  6. Relationships: UPPER_SNAKE_CASE verb phrase reading source -> target, stored ONCE in ONE
     direction. BANNED types: BELONGS_TO, HAS_DATA, MAPPED_TO, RELATED_TO, and anything that does
     not state what the relationship means. Undeclared type = load failure.
  7. syear goes on the RELATIONSHIP, not the node. One :Student across all years, with
     [:ENROLLED_IN {syear}] per year. This is the general form of the D1 fix.
  8. Only ever call neo4jCreateNode() / neo4jCreateRelationship() from app/Helpers/Helper.php for
     incremental writes. Never concatenate values into Cypher (D7).

=============================================================================================
TASKS — strictly in order. STOP after each and report with the query output that proves it.
=============================================================================================

STEP 0 — BASELINE + SECURITY GATE
  a. Snapshot current label counts, rel-type counts, constraints and indexes to
     docs/neo4j-baseline-<date>.md.
  b. EXPORT the 28 existing PREREQUISITE_OF edges to CSV. No MariaDB table can regenerate them;
     the wipe in STEP 2 destroys them permanently. Do this before anything else.
  c. SECURITY — DECIDED 2026-08-10, do not re-open or stall on it.
     Scope is FULL: project everything including HR salary (hrms_salary_certificate,
     hrms_emp_payroll_deduction, payroll_types), student documents (tblstudent_document, 65,722
     rows) and complete fee ledgers. The absence of RBAC on Community Edition is an ACCEPTED RISK,
     recorded in docs/neo4j-migration-status.md §3a. Classify these tables normally — do not mark
     them EXCLUDE and do not ask again.
     The hardening actions below were common to all options and remain REQUIRED. Report their
     status in STATUS §3a each session; they must all be done before Phase 10 (HR), and they do
     NOT block Phases 0-9:
       1. rotate NEO4J_PASSWORD off 'admin'
       2. TLS on bolt (bolt+s://) and HTTPS on the browser port
       3. firewall 7475/7688 to known source IPs
       4. a plan to move off EOL Neo4j 4.4
     If any of 1-3 is still open when Phase 10 begins, STOP and tell me.

  d. DISABLE THE LIVE WRITERS before the wipe, or they will recreate the defects mid-rebuild.
     routes/web.php:639  /sync-neo4j   -> Neo4jSyncController (MERGEs on NAMES; would collapse
                                          all 56 tenants into shared nodes)
     routes/web.php:721  /migrate-data -> DataMigrationController::migrateDataToNeo4j
     These are REGISTERED ROUTES reachable by anyone who knows the URL — not dead code. Comment
     them out now; STEP 8 deletes them properly. Report every other route you find that writes
     to Neo4j.

STEP 1 — CLASSIFY ALL 487 TABLES  (the foundation; get this wrong and everything downstream is wrong)
  For every table in vivek_erp emit one row: table, module, shape, label-or-rel, key, tenant column,
  row count, FK evidence, and a one-line reason for the shape.
  Derive `module` by scanning app/Http/Controllers/<module> and app/Models/<module> for
  DB::table('...'), protected $table, and ->join('...') — the same method as the scope doc.
  Derive FKs from information_schema (only 91 exist) PLUS naming convention (*_id columns matched
  against candidate tables) PLUS actual ->join() usage in code. State which evidence you used per FK.
  Write to docs/neo4j-table-classification.md.
  FLAG for my review, do not guess: any table with no PK (16 of them), any table you cannot
  attribute to a module, and every EVENT_HIGH table with its proposed aggregation.
  STOP. I will review the classification before you build the registry.

STEP 2 — REGISTRY
  Generate config/neo4j_graph.php from the approved classification. Include EXCLUDE and empty
  tables as stubs so they are picked up automatically when they populate (all 27 pal_* tables:
  25 rows total today).
  Add a validator command, neo4j:registry-check, that FAILS on: a label declared twice; two key
  properties on one label; an undeclared relationship type; a banned relationship type; a
  reciprocal pair (both A->B and B->A for the same fact); a node label with no relationships;
  a table present in MariaDB but absent from the registry.

STEP 3 — WIPE AND SCHEMA RESET.  THIS IS A REBUILD, NOT AN IN-PLACE REPAIR.
  The new model keys :Student on studentId where the current graph keys on stuId, so a MERGE
  against the existing graph creates a SECOND set of nodes under the same labels instead of
  correcting them — and the old stuId constraint will not stop it, because new nodes have a null
  stuId and Neo4j uniqueness constraints skip nulls (that is defect D2 itself).
  a. Confirm STEP 0's baseline and PREREQUISITE_OF export exist and are readable.
  b. Drop ALL existing constraints and ALL non-LOOKUP indexes (bulk delete is far faster unindexed).
  c. CALL { MATCH (n) DETACH DELETE n } IN TRANSACTIONS OF 10000 ROWS;
     then assert MATCH (n) RETURN count(n) = 0. Batch it — a single DETACH DELETE over ~619k
     relationships exhausts heap.
  d. Generate constraints and indexes FROM THE REGISTRY in a loop: one uniqueness constraint per
     label on its key, one plain BTREE index on sub_institute_id per tenant-scoped label.
     4.4 Community supports UNIQUENESS constraints only — existence and node-key are Enterprise.
     Do not attempt them; enforce those invariants in the loader and assert them in STEP 6.
     Print SHOW CONSTRAINTS and SHOW INDEXES and confirm no record_id entry remains.
  If a stakeholder will not accept a full wipe, STOP and ask me. Do not attempt an in-place
  migration: relabelling 143,360 Result nodes and splitting 12,801 Student nodes into person +
  enrollment is strictly harder, slower and less verifiable than a clean reload.

STEP 4 — EXPORTER
  app/Console/Commands/Neo4jExport.php  (neo4j:export {--module=} {--tenant=} {--out=})
  Registry-driven and generic — no per-table code. Stream with DB::cursor so the 2.29M-row
  aggregates never enter memory. One CSV per label and per relationship type.
  VERIFY every join and aggregate column against the live schema BEFORE writing the query. The SQL
  in neo4j-migration-report.md §5.1 is illustrative and UNTESTED. Report every column that does
  not exist rather than working around it silently.
  Support --module so domains load independently and a failure is contained.

STEP 5 — LOADER
  LOAD CSV + CALL {} IN TRANSACTIONS OF 10000 ROWS, generated from the registry.
  All MERGE, so the load is idempotent and re-runnable after any failure. All nodes, then all
  relationships. Load order by domain: academic core -> assessment -> people/HR -> admission ->
  finance -> operations -> skill/career -> platform. O*NET loads LAST and separately as a
  tenant-free reference subgraph (global:true) so it can be dropped without touching school data.
  Community Edition has ONLY the `neo4j` database, so "separately" means a distinct label namespace
  (:Onet* prefix) plus source='onet' on every node — NOT a second database. Make sure the O*NET
  subgraph can be removed by a single label-scoped DETACH DELETE without touching school nodes.

STEP 6 — VERIFICATION  (this is the deliverable that proves the graph is correct)
  artisan neo4j:verify — FAIL loudly, with counts, on any of:
    - any node missing sub_institute_id where its source table has the column, or holding the
      invalid values NULL or 0 (both present today: 1,190 Students NULL, 2 Students and
      2 Questions = 0)
    - any node with a null or non-integer key property
    - DANGLING REFERENCES: any node carrying an FK-style *_id property with no matching target
      node. Baseline to beat: 7,674 Questions point at non-existent Chapters (D9)
    - COUNT(n) <> COUNT(DISTINCT n.<key>) for any label            (duplicate nodes)
    - any relationship whose endpoints have different sub_institute_id  (tenant leak)
    - any orphaned node                                             (D4)
    - COUNT(:Student) <> COUNT(DISTINCT :Student.studentId)         (D1)
    - any label carrying two id-like properties                     (D2)
    - any reciprocal relationship pair                              (D6)
    - per-label Neo4j count vs MariaDB COUNT(*), per tenant, for every ENTITY table
    - FINANCE RECONCILIATION: SUM of every projected fee amount in Neo4j vs the same SUM in
      MariaDB, per tenant. Any discrepancy is a hard failure, not a warning.
  Mark every finance node authoritative:false and stamp projected_at on it. MariaDB is the only
  source of truth for money — the graph must never be the number anyone acts on.
  Report the actual pass/fail table. Do not summarise it as "passing".

STEP 7 — LIVE SYNC  (only after 0-6 are green)
  Revive the sync_log outbox with a supervised neo4j:drain consumer, per migration report §6 Tier 1.
  BLOCKER: .env has QUEUE_CONNECTION=sync, so every save would block inline on a WAN round-trip to
  dev.triz.co.in. Switch to redis and run a worker BEFORE enabling. Add queue-depth alerting — a
  silently dead consumer is what killed the April 2026 attempt.
  Resolve the 8 rows stranded PENDING in sync_log and neo4j_sync_queue: replay or truncate.
  At 487 tables, sync only ENTITY and JUNCTION tables live. EVENT_HIGH aggregates rebuild on a
  nightly schedule — streaming 2.29M answer rows through an outbox is pointless and will fall behind.

STEP 8 — CLEANUP
  Delete app/Http/Controllers/Neo4jSyncController.php — it MERGEs on names, which WOULD collapse
  all 56 tenants. Verified never run: the graph has no :AcademicSection and no OFFERS edge.
  Remove createNode() and createRelationship() from app/Services/Neo4jService.php (legacy, unsafe,
  superseded by the Helper functions). Keep the constructor, run(), getClient(), testConnection().
  Update every caller and report which ones you changed.

OPEN QUESTIONS — ask me, do not assume:
  a. Which database is authoritative? Backend .env says vivek_erp @202.47.117.220; frontend .env
     says development_erp @128.199.17.97. They disagree and I could not reach the second.
  b. All 56 tenants, or institute 1 only? Only institute 1 has a complete
     Chapter->Concept->Content->Student chain; the other 47 tenants with students have essentially
     no curriculum data. Full-ERP scope does not change that — it is a content gap, not an
     engineering one.
  c. Where does PREREQUISITE_OF come from? No table supplies it. Curriculum-authored, or
     LLM-inferred from the 1,372 lms_concept descriptions? Critical path for PAL.
  d. Does the Next.js frontend need to read the graph? It has NO Neo4j connection today — only a
     'knowledge-graph' toolbar id in app/components/RightFloatingToolbar.tsx. If yes, it goes
     through Laravel; never a bolt connection from a browser.
  e. Is O*NET (43 tables, ~1.26M rows, largely unreferenced in code) actually wanted, or is it
     dormant reference data?

DO NOT
  - project a table as nodes without a traversal query that justifies it — "we might need it" is
    not a justification, and every unjustified label is permanent maintenance cost
  - mirror EVENT_HIGH tables row-for-row (lms_online_exam_answer 2.29M, result_personalize_marks
    1.3M, hrms_attendances 352k, answer_master 459k)
  - turn junction tables into nodes, or enum tables into nodes
  - store any fact in both directions
  - add Kafka or Debezium (~8.9M rows; LOAD CSV finishes in well under an hour)
  - load PII or salary data before the STEP 0c security gate is cleared
  - report any count you have not actually queried
````

---

## 7. Risks to sign off on

| Risk | Why it matters now | Mitigation in the prompt |
|---|---|---|
| **Two sources of truth on money** | Fee ledgers become queryable in an eventually-consistent store. A graph total that disagrees with the ledger in front of a parent is a business incident. | `authoritative:false` + `projected_at` on every finance node; hard-failing per-tenant SUM reconciliation in STEP 6 |
| **PII and salary with no access control** | Community Edition has **no RBAC** — one credential reads every node across all 56 tenants. Password is `admin`, browser is plain HTTP, instance is WAN-exposed and on an **EOL** 4.4 release. Scope adds salary, student documents, fee ledgers. Rotating the password does not close this. | STEP 0c blocking gate with three explicit options: upgrade to Enterprise, exclude PII/payroll values, or accept with a named owner |
| **Live writer routes recreate the defects** | `/sync-neo4j` (MERGEs on names — would collapse all 56 tenants) and `/migrate-data` are **registered routes**, reachable by URL, not dead code. Running either mid-rebuild reintroduces D2/D3. | STEP 0d disables them before the wipe; STEP 8 deletes them |
| **Dangling FK properties** | 7,674 Questions carry a `chapter_id` with no matching Chapter (D9). A dangling id looks like data and silently fails every join built on it. | Convention 5b + a `neo4j:verify` assertion across all FK-style properties |
| **Model drift at 487 tables** | The defect that made the current graph unusable (D2/D6) scales with table count. | Registry as single source of truth + `neo4j:registry-check` failing on drift |
| **Supernodes** | A `:VehicleType` node with 690 edges, or a `:Status` node with 100k, degrades every traversal through it. | LOOKUP shape → property, never a node |
| **Sync volume** | An outbox streaming 2.29M answer rows will fall permanently behind. | ENTITY/JUNCTION sync live; EVENT_HIGH aggregates rebuild nightly |
| **Coverage still capped by content** | Full-ERP scope does not fix D8. Only institute 1 has a complete curriculum chain, and `Concept` has 11 nodes against 1,372 source rows. | Open question (b); no engineering change closes it |

The last row is the one worth repeating to whoever asked for full coverage: projecting all 487 tables makes the graph *broader*, not *smarter*. Prerequisite tracing and real recommendations still need the concept layer populated and `PREREQUISITE_OF` authored — which is curriculum work, and no amount of migration scope substitutes for it.
