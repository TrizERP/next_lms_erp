# Neo4j Migration & Live-Sync Report — K-12 ERP (`vivek_erp`)

**Date:** 2026-08-03
**Scope:** Strategy for projecting MariaDB ERP data into Neo4j as a graph, plus live (ongoing) synchronisation.
**Status:** Recommendation — no code changed yet.
**Verified:** 2026-08-03 against `vivek_erp` (MariaDB 10.11.9). All structural claims re-confirmed; see [scope doc §7](./neo4j-module-graph-scope.md) for the verification log. Two open items resolved, one new blocking finding added below.

> 🔴 **New finding — multi-tenant coverage.** Curriculum data and student data live in largely **different tenants**. `tblstudent` spans 48 institutes, but `chapter_master` and `lms_concept` each cover **1**, and `content_master` only 4. **Only institute 1 has a complete `Chapter → Concept → Content → Student` chain.** Phase 1 therefore yields a graph for one school, not 56. This is a content-authoring gap, not an engineering one — no migration approach closes it. Detail in [scope doc §2A](./neo4j-module-graph-scope.md).

---

## 1. Executive summary

You asked whether to (A) copy the whole database into Neo4j, or (B) clean/filter the database first and then copy it.

**Recommendation: neither. Take Option C — a curated projection.**
MariaDB stays exactly as it is and remains the system of record. Neo4j receives a *deliberately modelled* subset (~40 tables of 484) chosen because they answer traversal questions. Everything else never enters the graph.

Three findings from the live database drive this:

| # | Finding | Consequence |
|---|---|---|
| 1 | 484 tables but only **91 foreign-key constraints** | The schema does not describe its own relationships. Any "mirror everything" tool yields ~484 disconnected node labels with almost no edges — a graph with no graph value. |
| 2 | **Two complete CDC pipelines already exist and are abandoned** (`sync_log`, `neo4j_sync_queue`) | You do not need Kafka. You need to revive and finish the outbox that is already designed and was already working. |
| 3 | The semantic layer is **empty**: all 27 `pal_*` tables ≈ 0 rows; 1 of 26,154 `content_master` rows has a `concept_id` | The queries that justify a graph (prerequisite tracing, mastery gaps) have no source data. Migration technology is not your bottleneck. |

**Headline:** at 2.3 GB the migration itself is a solved, ~15-minute problem. Do not spend the budget on Kafka. Spend it on modelling and on populating the concept layer.

---

## 2. Evidence — what is actually in the database

Measured live against `vivek_erp` @ `202.47.117.220` (read-only queries against `information_schema` and `COUNT(*)`).

### 2.1 Overall scale

| Metric | Value |
|---|---|
| Tables | **484** |
| Rows (approx.) | **8,886,606** |
| Data + index size | **2,319.8 MB** |
| Declared FK constraints | **91** |
| Empty tables | **89** |
| Tables never referenced in `app/`, `routes/`, `resources/views/` | **56** |
| Tables both empty *and* unreferenced | **20** |
| Tenants (`school_setup`) | **56** |

This is a **small** database by Neo4j standards. 2.3 GB is not a "big data" migration; it does not warrant streaming infrastructure.

### 2.2 The size is concentrated in transactional tables

Top tables by disk:

| Table | Rows | Size | Graph-relevant? |
|---|---:|---:|---|
| `result_personalize_marks` | 1,299,518 | 446.0 MB | ❌ aggregate only |
| `lms_online_exam_answer` | 2,294,151 | 377.6 MB | ❌ aggregate only |
| `hrms_attendances` | 351,908 | 96.3 MB | ❌ never |
| `fees_breackoff` | 180,880 | 85.9 MB | ❌ never |
| `s_skill_map_k_a` | 133,143 | 79.7 MB | ⚠️ Phase 3, unreferenced in code |
| `fees_circular_log` | 2,440 | 75.5 MB | ❌ never |
| `tblstudent_enrollment` | 176,634 | 74.7 MB | ✅ core |
| `onet_work_context` | 287,227 | 54.2 MB | ⚠️ Phase 3 |
| `lms_question_mapping` | 533,130 | 51.1 MB | ✅ as edges |
| `csv_data` | 268 | 50.5 MB | ❌ scratch data |

**~60% of the database volume is fee ledgers, HR attendance, and raw answer rows.** None of it belongs in a graph. Those workloads are `SUM`/`GROUP BY` over wide row sets — precisely what Neo4j is worst at and MariaDB is best at.

### 2.3 The core graph is tiny

| Table | Exact rows |
|---|---:|
| `tblstudent` | 83,715 |
| `tblstudent_enrollment` | 176,634 (81,164 distinct students) |
| `tbluser` | 4,763 |
| `standard` | 1,000 |
| `subject` | 2,025 |
| `sub_std_map` | 6,652 |
| `chapter_master` | **98** (covering only **8** distinct subjects) |
| `content_master` | 26,154 |
| `lms_question_master` | 62,206 |
| `school_setup` | 56 |

A Phase-1 graph is therefore roughly **400k nodes and 1–2M relationships** — Neo4j handles this on a laptop. Load time with `LOAD CSV`: minutes.

> ⚠️ **`chapter_master` has 98 rows spanning 8 subjects, against 2,025 subjects.** The curriculum spine is ~99% unpopulated. This must be understood before promising curriculum-graph features.

### 2.4 The semantic layer does not exist yet

| Table | Rows |
|---|---:|
| `pal_concepts` | **1** |
| `pal_competencies` | 0 |
| `pal_learner_states` | 0 |
| `pal_assessment_results` | 0 |
| `pal_content_recommendations` | 0 |
| `pal_learning_events` | 0 |
| `pal_subjects` | 0 |
| `pal_misconceptions` | 2 |
| `pal_session_events` | 18 |
| *(all 27 `pal_*` tables)* | *≈ 0* |
| `content_master` rows with a `concept_id` | **1 of 26,154** |
| `lms_lesson_plan_concepts` | 537 |

The PAL intelligence services (`LearnerStateEngine`, `PredictiveInterventionEngine`, `MisconceptionIntelligenceEngine`, …) are fully built **against empty tables**.

> **Correction (added after per-module analysis).** The `pal_*` tables are empty, but the concept layer is **not** missing — it lives elsewhere. `lms_concept` holds **1,338** rows and `topic_master` holds **13,362**, both actively used by the LMS module. See [`neo4j-module-graph-scope.md` §2](./neo4j-module-graph-scope.md). This is a *data-routing* problem (PAL V4 reads `pal_concepts`; the real data sits in `lms_concept`), not a missing-data problem — materially smaller than first assessed.

Copying the database into Neo4j today still yields only a structural graph — *student → class → subject → exam*. Prerequisite tracing additionally requires `PREREQUISITE_OF` edges, and **no source table supplies them** in either schema; they must be authored by curriculum staff or inferred. That, not the concept data, is the real gate on PAL intelligence — and no migration tool fixes it.

### 2.5 Two abandoned sync pipelines already exist

Both stopped on **2026-04-02**. Neither is referenced anywhere in `app/`, `routes/`, or `database/`.

**`neo4j_sync_queue`** — a relationship-level transactional outbox, 12,193 rows (12,185 `done`, 8 `pending`):

```
id, event_type ENUM('INSERT','UPDATE','DELETE'), source_table, source_id,
rel_type, target_table, old_target_id, new_target_id,
status ENUM('pending','processing','done','failed'), created_at, processed_at
```

Relationships it tracked:

| Source | Rel | Target | Events |
|---|---|---|---:|
| `StuDetail` | `HAS_STUDENT` | `Student` | 6,061 |
| `Student` | `ENROLLED_IN` | `Standard` | 6,053 |
| `tblstudent_enrollment` | `HAS_RESULT` | `lms_online_exam` | 60 |
| `question_paper` | `ASSESSES_CHAPTER` | `chapter_master` | 3 |
| `Standard` | `HAS_SUBJECT` | `Subject` | 3 |

**`sync_log`** — a node-level outbox with full JSON payloads, 9,240 rows (9,232 `SUCCESS`, 8 `PENDING`):

```json
{"record_id":436295,"event":"INSERT","node_label":"Student",
 "data":{"id":436295,"stuId":436295,"student_id":280668,
         "standard_id":3109,"sub_institute_id":244}}
```

**Timeline of the failure:** last successful processing `2026-04-02 11:49:55`; last event produced `2026-04-02 13:50:55`. The **consumer died ~2 hours before the producer stopped**, leaving 8 rows stranded in each table. `information_schema.triggers` now returns **0 triggers**, and no PHP references the tables — so the producer was removed afterwards.

This is a *good* design that failed operationally, not architecturally. The schema already has `status`, `retry_count`, and `processed_at`. It needs a supervised consumer and a monitor on queue depth.

### 2.6 Existing Neo4j code

| File | State |
|---|---|
| [`config/neo4j.php`](../config/neo4j.php) | OK. `bolt://dev.triz.co.in:7688` |
| [`Neo4jService.php`](../app/Services/Neo4jService.php) | ⚠️ `createRelationship()` builds Cypher by string concatenation (lines 118–119) — injection risk + unlabelled `MATCH` forces a full scan per write |
| [`Neo4jSyncController.php`](../app/Http/Controllers/Neo4jSyncController.php) | ⚠️ `MERGE`s on **names**, not IDs; no `sub_institute_id` |
| [`RecommendationController.php`](../app/Http/Controllers/RecommendationController.php) | ⚠️ accepts `studentId` and never uses it — `ORDER BY difficulty LIMIT 5` |
| [`StudentGraphController.php`](../app/Http/Controllers/StudentGraphController.php) | The rich query (`MASTERS`, `Assessment`, `Result`) is commented out |
| [`LearnerStateEngine.php:343`](../app/Services/PAL/Intelligence/LearnerStateEngine.php#L343) | `getConceptDependencies()` returns `[]` — *"Get from Neo4j or cache - placeholder"* |
| `lms_data_content_neo4j` | 13,693 rows — but covers **1 institute of 56** |

---

## 3. The three options

### Option A — mirror all 484 tables 1:1

**Verdict: reject.**

- With 91 FKs across 484 tables, relationships live in PHP, not in the schema. Automated mirroring produces nodes with no edges. You would have paid for a graph database and received a slower key-value store.
- ~60% of the volume (fees, HR attendance, raw answers) is aggregate-shaped. Neo4j has no columnar storage; `SUM` over 1.3M `result_personalize_marks` rows will be markedly slower than in MariaDB.
- Every write path doubles. Every `pal_*` table you mirror is 0 rows of nothing.
- ACID reporting on money (`fees_*`) must not be duplicated into an eventually-consistent store — divergence between two fee totals is a business incident, not a bug.

### Option B — clean the source DB, then mirror

**Verdict: reject as stated — but keep the cleanup as a separate project.**

- **428 of 484 tables are referenced in code.** Only 56 are not, and they hold just 286,448 rows in total (~3% of the database). The cleanup upside is small.
- This is a live multi-tenant system (56 institutes). Dropping tables to enable a *reporting* feature couples an irreversible, high-blast-radius change to an additive one.
- Worse, 3 of the top unreferenced tables are `neo4j_sync_queue`, `sync_log`, and `lms_data_neo4j` — i.e. the "unused" list contains exactly the pipeline you are about to rebuild. A naive cleanup would delete your own migration infrastructure.

If you do want a cleanup: run it independently, start with the **20 tables that are both empty and unreferenced**, verify against production logs over one full academic term, and never in the same release as the graph work.

### Option C — curated projection ✅ **recommended**

- MariaDB is untouched and authoritative. Neo4j is a **derived read-model**, rebuildable from scratch at any time.
- Only tables with traversal value are projected (~40).
- Large transactional tables are **aggregated before projection**: 2.29M `lms_online_exam_answer` rows become one `[:MASTERED {score}]` edge per student-concept, not 2.29M nodes.
- If the graph is wrong, you drop the database and reload. No risk to the ERP.
- Multi-tenancy is designed in from day one instead of retrofitted.

---

## 4. Target graph model (Phase 1)

Every node carries `sub_institute_id`. Every node keys on the **MariaDB primary key**, never on a display name.

```
(:Institute {id})
  -[:HAS_SECTION]->   (:AcademicSection {id})
  -[:HAS_STANDARD]->  (:Standard {id})
  -[:HAS_SUBJECT]->   (:Subject {id})
  -[:HAS_CHAPTER]->   (:Chapter {id, order})
  -[:COVERS]->        (:Concept {id, bloom_level, abstractness})

(:Concept)-[:PREREQUISITE_OF]->(:Concept)      // the edge that justifies the whole project

(:Student {id})-[:ENROLLED_IN {syear}]->(:Standard)
(:Student)-[:MASTERED {score, attempts, last_at}]->(:Concept)
(:Student)-[:HAS_MISCONCEPTION {severity}]->(:Misconception)

(:Content {id, type})-[:TEACHES]->(:Concept)
(:Question {id, difficulty})-[:ASSESSES]->(:Concept)
```

**Constraints to create before any load** (these also create the indexes the loader needs):

```cypher
CREATE CONSTRAINT institute_id  IF NOT EXISTS FOR (n:Institute) REQUIRE n.id IS UNIQUE;
CREATE CONSTRAINT standard_key  IF NOT EXISTS FOR (n:Standard)  REQUIRE (n.sub_institute_id, n.id) IS UNIQUE;
CREATE CONSTRAINT subject_key   IF NOT EXISTS FOR (n:Subject)   REQUIRE (n.sub_institute_id, n.id) IS UNIQUE;
CREATE CONSTRAINT chapter_key   IF NOT EXISTS FOR (n:Chapter)   REQUIRE (n.sub_institute_id, n.id) IS UNIQUE;
CREATE CONSTRAINT concept_key   IF NOT EXISTS FOR (n:Concept)   REQUIRE (n.sub_institute_id, n.id) IS UNIQUE;
CREATE CONSTRAINT student_key   IF NOT EXISTS FOR (n:Student)   REQUIRE (n.sub_institute_id, n.id) IS UNIQUE;
CREATE CONSTRAINT content_key   IF NOT EXISTS FOR (n:Content)   REQUIRE (n.sub_institute_id, n.id) IS UNIQUE;
CREATE CONSTRAINT question_key  IF NOT EXISTS FOR (n:Question)  REQUIRE (n.sub_institute_id, n.id) IS UNIQUE;
```

> The composite key on `(sub_institute_id, id)` is what prevents the current bug where all 56 institutes collapse into one graph.

---

## 5. Bulk migration — method comparison

| Method | Throughput | Downtime | Fit here |
|---|---|---|---|
| `neo4j-admin database import` | 1M+ rows/s | Yes — empty DB only, needs server filesystem access | Overkill at 400k nodes |
| **`LOAD CSV` + `CALL {} IN TRANSACTIONS`** | 10–50k rows/s | **None** | ✅ **Recommended** |
| APOC `apoc.load.jdbc` | 5–20k rows/s | None | Convenient, but couples Neo4j to MariaDB creds and needs the MariaDB JDBC driver installed in `plugins/` |
| PHP loop via `laudis` client *(current approach)* | ~100–500 rows/s | None | ❌ One round trip per row over WAN to `dev.triz.co.in`. This is why the existing sync is slow |

At your scale `LOAD CSV` completes Phase 1 in **under 15 minutes**. Both endpoints are remote, so the network is the bottleneck — which is exactly the argument against the row-at-a-time PHP approach currently in `Neo4jSyncController`.

### 5.1 Step 1 — export CSVs (new artisan command)

```php
// app/Console/Commands/Neo4jExport.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Neo4jExport extends Command
{
    protected $signature   = 'neo4j:export {--out=storage/app/neo4j}';
    protected $description = 'Export the curated graph projection to CSV for LOAD CSV';

    /** Only the tables that carry traversal value. */
    private array $nodes = [
        'institutes' => "SELECT id, sub_institute_id, name FROM school_setup",
        'standards'  => "SELECT id, sub_institute_id, name FROM standard",
        'subjects'   => "SELECT id, sub_institute_id, name FROM subject",
        'chapters'   => "SELECT id, sub_institute_id, subject_id, chapter_name AS name,
                                chapter_order FROM chapter_master",
        'students'   => "SELECT id, sub_institute_id,
                                CONCAT(first_name,' ',COALESCE(last_name,'')) AS name
                         FROM tblstudent",
        'content'    => "SELECT id, sub_institute_id, title, content_type, concept_id
                         FROM content_master",
        'questions'  => "SELECT id, sub_institute_id, question_level, chapter_id
                         FROM lms_question_master",
    ];

    private array $rels = [
        // Aggregate BEFORE projecting — never mirror the 2.29M raw answer rows.
        'mastery' => "SELECT e.student_id, q.chapter_id AS concept_ref,
                             ROUND(AVG(a.is_correct)*100, 2) AS score,
                             COUNT(*) AS attempts, MAX(a.created_at) AS last_at
                      FROM lms_online_exam_answer a
                      JOIN lms_question_master q ON q.id = a.question_id
                      JOIN tblstudent_enrollment e ON e.id = a.enrollment_id
                      GROUP BY e.student_id, q.chapter_id",
        'enrolled' => "SELECT student_id, standard_id, syear, sub_institute_id
                       FROM tblstudent_enrollment",
    ];

    public function handle(): int
    {
        $dir = base_path($this->option('out'));
        if (!is_dir($dir)) { mkdir($dir, 0775, true); }

        foreach ($this->nodes + $this->rels as $name => $sql) {
            $path = "$dir/$name.csv";
            $fh   = fopen($path, 'w');
            $first = true; $count = 0;

            // chunk by cursor so 2M-row aggregates never load into memory
            foreach (DB::cursor($sql) as $row) {
                $row = (array) $row;
                if ($first) { fputcsv($fh, array_keys($row)); $first = false; }
                fputcsv($fh, $row);
                $count++;
            }
            fclose($fh);
            $this->info(sprintf('%-12s %8d rows -> %s', $name, $count, $path));
        }
        return self::SUCCESS;
    }
}
```

> Verify the join columns (`a.enrollment_id`, `q.chapter_id`) against your actual schema before running — the aggregate query above is the shape, not a tested statement.

### 5.2 Step 2 — load into Neo4j

Copy the CSVs to the Neo4j server's `import/` directory, then:

```cypher
// --- nodes ---
LOAD CSV WITH HEADERS FROM 'file:///standards.csv' AS row
CALL {
  WITH row
  MERGE (s:Standard {sub_institute_id: toInteger(row.sub_institute_id),
                     id: toInteger(row.id)})
  SET   s.name = row.name
} IN TRANSACTIONS OF 10000 ROWS;

LOAD CSV WITH HEADERS FROM 'file:///students.csv' AS row
CALL {
  WITH row
  MERGE (s:Student {sub_institute_id: toInteger(row.sub_institute_id),
                    id: toInteger(row.id)})
  SET   s.name = row.name
} IN TRANSACTIONS OF 10000 ROWS;

// --- relationships (run AFTER all nodes + constraints exist) ---
LOAD CSV WITH HEADERS FROM 'file:///enrolled.csv' AS row
CALL {
  WITH row
  MATCH (st:Student  {sub_institute_id: toInteger(row.sub_institute_id),
                      id: toInteger(row.student_id)})
  MATCH (sd:Standard {sub_institute_id: toInteger(row.sub_institute_id),
                      id: toInteger(row.standard_id)})
  MERGE (st)-[r:ENROLLED_IN {syear: toInteger(row.syear)}]->(sd)
} IN TRANSACTIONS OF 10000 ROWS;

LOAD CSV WITH HEADERS FROM 'file:///mastery.csv' AS row
CALL {
  WITH row
  MATCH (st:Student {id: toInteger(row.student_id)})
  MATCH (c:Concept  {id: toInteger(row.concept_ref)})
  MERGE (st)-[m:MASTERED]->(c)
  SET   m.score = toFloat(row.score),
        m.attempts = toInteger(row.attempts),
        m.last_at = row.last_at
} IN TRANSACTIONS OF 10000 ROWS;
```

Everything is `MERGE` on a stable composite key, so **the whole load is idempotent** — safe to re-run after a failure.

---

## 6. Live sync — do you need Kafka?

**No. Not at this scale, and not yet.** Here is the decision in tiers.

### Tier 1 — Transactional outbox + Laravel queue ✅ **start here**

You already have the schema. Revive `sync_log`, add a supervised consumer.

**Producer** — an Eloquent observer trait, so the outbox row is written in the *same transaction* as the business row:

```php
// app/Traits/SyncsToNeo4j.php
namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait SyncsToNeo4j
{
    public static function bootSyncsToNeo4j(): void
    {
        $push = function ($model, string $event) {
            DB::table('sync_log')->insert([
                'table_name'     => $model->getTable(),
                'operation_type' => $event,
                'record_id'      => $model->getKey(),
                'payload_json'   => json_encode([
                    'record_id' => $model->getKey(),
                    'event'     => $event,
                    'node_label'=> $model->neo4jLabel(),
                    'data'      => $model->only($model->neo4jFields()),
                ]),
                'status'      => 'PENDING',
                'retry_count' => 0,
                'created_at'  => now(),
            ]);
        };

        static::created(fn ($m) => $push($m, 'INSERT'));
        static::updated(fn ($m) => $push($m, 'UPDATE'));
        static::deleted(fn ($m) => $push($m, 'DELETE'));
    }
}
```

**Consumer** — a draining command run under Supervisor:

```php
// app/Console/Commands/Neo4jDrain.php
namespace App\Console\Commands;

use App\Services\Neo4jService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Neo4jDrain extends Command
{
    protected $signature = 'neo4j:drain {--limit=500}';

    public function handle(Neo4jService $neo): int
    {
        $rows = DB::table('sync_log')
            ->where('status', 'PENDING')
            ->where('retry_count', '<', 5)
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($rows as $row) {
            try {
                $p     = json_decode($row->payload_json, true);
                $label = preg_replace('/[^A-Za-z0-9_]/', '', $p['node_label']); // never interpolate raw
                $data  = $p['data'];

                if ($p['event'] === 'DELETE') {
                    $cypher = "MATCH (n:$label {id: \$id, sub_institute_id: \$sid}) DETACH DELETE n";
                } else {
                    $cypher = "MERGE (n:$label {id: \$id, sub_institute_id: \$sid}) SET n += \$props";
                }

                $neo->run($cypher, [
                    'id'    => (int) $p['record_id'],
                    'sid'   => (int) ($data['sub_institute_id'] ?? 0),
                    'props' => $data,
                ]);

                DB::table('sync_log')->where('id', $row->id)
                    ->update(['status' => 'SUCCESS', 'processed_at' => now()]);
            } catch (\Throwable $e) {
                DB::table('sync_log')->where('id', $row->id)->update([
                    'status'      => 'FAILED',
                    'retry_count' => $row->retry_count + 1,
                ]);
                report($e);
            }
        }
        return self::SUCCESS;
    }
}
```

Note the parameterised `$props` and the sanitised label — this is the fix for the injection pattern currently in `Neo4jService::createRelationship()`.

**Operational guardrails that were missing last time:**

```php
// routes/console.php or app/Console/Kernel.php
$schedule->command('neo4j:drain')->everyMinute()->withoutOverlapping();

// alert when the queue backs up — the failure mode that killed the April attempt
$schedule->call(function () {
    $depth = DB::table('sync_log')->where('status', 'PENDING')->count();
    if ($depth > 1000) { /* notify ops */ }
})->everyFiveMinutes();
```

- Latency: seconds. Infrastructure added: none.
- Reprocessing is safe because every write is a `MERGE`.

> 🚨 **Blocker:** `.env` currently has `QUEUE_CONNECTION=sync`. With `sync`, any queued job runs **inline inside the HTTP request** — every student save would block on a WAN round-trip to `dev.triz.co.in:7688`. Set `QUEUE_CONNECTION=redis` (Redis is already configured at `127.0.0.1:6379`) and run a worker before enabling any live sync. This is the most likely reason the April 2026 attempt was abandoned.

### Tier 2 — Debezium + Kafka + Neo4j Connector

**Only adopt when you can name the trigger.** Legitimate triggers: sustained write volume beyond what a per-minute drain absorbs; a need to capture changes made by raw SQL/admin tools outside Laravel; or a second consumer (search index, warehouse) wanting the same stream.

What it costs: Kafka + Kafka Connect (+ KRaft), `binlog_format=ROW` and `REPLICA` privileges on `202.47.117.220` — which, being a shared remote host, may not be yours to configure. That single constraint often settles the question.

Sketch, for when the time comes:

```json
{
  "name": "erp-mariadb-cdc",
  "config": {
    "connector.class": "io.debezium.connector.mysql.MySqlConnector",
    "database.hostname": "202.47.117.220",
    "database.server.id": "184054",
    "topic.prefix": "erp",
    "database.include.list": "vivek_erp",
    "table.include.list": "vivek_erp.tblstudent,vivek_erp.tblstudent_enrollment,vivek_erp.chapter_master,vivek_erp.content_master",
    "schema.history.internal.kafka.topic": "schema-changes.erp"
  }
}
```

```json
{
  "name": "neo4j-sink",
  "config": {
    "connector.class": "streams.kafka.connect.sink.Neo4jSinkConnector",
    "neo4j.server.uri": "bolt://dev.triz.co.in:7688",
    "topics": "erp.vivek_erp.tblstudent",
    "neo4j.topic.cypher.erp.vivek_erp.tblstudent":
      "MERGE (s:Student {id: event.id, sub_institute_id: event.sub_institute_id}) SET s += event"
  }
}
```

Note `table.include.list` — even with Kafka you project a curated subset. Option A never becomes correct.

### Tier 3 — MariaDB triggers writing the outbox

Catches non-Laravel writes without Kafka. Costs you visibility (logic hidden from the codebase and from git) and adds write latency. The April pipeline appears to have worked this way; the triggers are now gone. Use only for tables written outside the app.

---

## 7. Blockers to fix before any scale-up

| # | Issue | Location | Impact |
|---|---|---|---|
| 1 | `QUEUE_CONNECTION=sync` | `.env` | Live sync would block every HTTP request |
| 2 | `MERGE` on names, not IDs | [`Neo4jSyncController.php:32-47`](../app/Http/Controllers/Neo4jSyncController.php#L32-L47) | Every "Science" across all grades and all 56 institutes collapses into **one node**. The source model already exposes `subject_id`, `chapter_id`, `sub_institute_id` — the sync discards them |
| 3 | No `sub_institute_id` on nodes | all Neo4j code | 56 tenants merge into one graph — a data-isolation breach |
| 4 | Cypher via string concatenation | [`Neo4jService.php:118-119`](../app/Services/Neo4jService.php#L118-L119) | Injection risk; unlabelled `MATCH` full-scans on every relationship write |
| 5 | Recommendation ignores the student | [`RecommendationController.php:25-29`](../app/Http/Controllers/RecommendationController.php#L25-L29) | Not a recommendation engine |
| 6 | `lms_data_content_neo4j` covers 1 of 56 institutes | data | Any conclusion drawn from it is single-tenant |
| 7 | 8 rows stranded `PENDING` in both outboxes | data | Decide: replay or truncate before restarting |

Items 2 and 3 are **correctness** bugs that silently corrupt the graph — fix them before loading volume, because a full reload is the only remedy afterwards.

---

## 8. Recommended roadmap

| Phase | Work | Outcome |
|---|---|---|
| **0 — Foundation** *(1 week)* | Fix blockers 1–4. Switch queue to Redis. Create constraints. Decide replay/truncate for the 8 stranded rows. | Safe to load |
| **1 — Structural graph** *(1–2 weeks)* | `neo4j:export` + `LOAD CSV` for Institute / Standard / Subject / Chapter / Student / Content / Question. ~400k nodes. | Real graph, correct multi-tenancy, ~15 min reload |
| **2 — Live sync** *(1 week)* | Revive `sync_log` outbox + `neo4j:drain` under Supervisor + queue-depth alerting. | Seconds-latency freshness, no new infra |
| **3 — Concept layer** *(the real work)* | Resolve the `lms_concept` (1,338 rows) vs `pal_concepts` (1 row) split — repoint PAL V4 or backfill. Then **author `PREREQUISITE_OF`**, which has no source table. Backfill `content_master.concept_id` (1 of 26,154). Expand `chapter_master` beyond 8 subjects. | Unlocks prerequisite tracing and genuine recommendations |
| **4 — Mastery edges** | Aggregate `lms_online_exam_answer` (2.29M rows) into `[:MASTERED]`. Wire `LearnerStateEngine::getConceptDependencies()`. | PAL intelligence becomes real |
| **5 — Skills / career** | `s_jobrole`, `s_jobrole_skills`, O*NET → `Concept → Skill → JobRole → Occupation`. | Career pathways |
| **6 — Kafka** | Only if a Tier-2 trigger from §6 actually materialises. | — |

**Phase 3 is the critical path, and it is a data/curriculum problem, not an engineering one.** Phases 0–2 can be delivered in about four weeks; they will produce a correct, live graph that still cannot answer "why is this student failing?" until Phase 3 is done. Plan the schedule around that dependency rather than around the migration tooling.

---

## 9. Direct answers

**"Should I copy the same database, or clean it first?"**
Neither. Keep MariaDB untouched and project a curated ~40-table subgraph. Cleaning the source is a separate project with a small payoff (56 tables, 286k rows, ~3%) and a large blast radius on a live 56-tenant system.

**"Can all modules' data be shown as a graph?"**
Technically yes; usefully no. Fees, payroll, HR attendance, inventory, hostel, and library circulation are aggregate and ACID workloads — they belong in MariaDB. Forcing them into Neo4j makes those reports slower and puts two disagreeing sources of truth on money.

**"Do I need Kafka for fast migration?"**
No. Kafka is for *continuous streaming*, not bulk migration — and at 2.3 GB `LOAD CSV` finishes in about 15 minutes. For live sync, the outbox you already built in `sync_log` gets you seconds of latency with zero new infrastructure. Revisit Kafka only against a named trigger from §6.

**"Live data operations on my tool?"**
Yes — Tier 1 in §6, once `QUEUE_CONNECTION` is off `sync`. The pattern is already designed and was already running in April 2026; it needs a supervised consumer and queue-depth monitoring so it cannot die silently again.

---

### Appendix — reproducing these figures

Read-only scripts used are in the session scratchpad (`dbstats.php`, `tables.php`, `verify.php`, `triggers.php`, `concepts.php`). Row counts for the core tables are exact `COUNT(*)`; the 484-table / 8.89M-row / 2,319.8 MB totals are `information_schema` estimates, which are approximate for InnoDB.
