---

## 8. What Phase 2 must generate from this

The registry (`config/neo4j_graph.php`) needs one entry per projected table carrying:

1. **Decision** — `NODE` / `EDGE` / `AGG_EDGE` / `PROP`, from the register above.
2. **uid template** — `<Label>:<sub_institute_id>:<syear>:<pk>` (PROJECTION LAW L1). For tables with
   no year column, `syear` is `0`, not null.
3. **Tenancy source** — either the column name (handling all three spellings in F12) or a derivation
   path through a named foreign key. **No default tenant.** A row that cannot resolve one is dropped
   and counted, never assigned `0`.
4. **The `GROUP BY`** for every `AGG_EDGE` — written in SQL, in the export query, not in PHP.
5. **`authoritative=false`** on every Fees and Payroll node and edge (46 tables).
6. **Dropped-row counters** per table, surfaced by `neo4j:verify`. F1 alone will drop ~105,000 child
   rows; that number must appear in a log, not vanish.

Special cases the loader must handle explicitly:

| Case | Tables | Handling |
|---|---|---|
| PascalCase tenancy + PK `Id` | `result_exam_master`, `result_exam_type_master` | Column alias in the export query |
| PK is the tenant | `school_setup` | uid `Institute:{Id}:0:{Id}` |
| Two edges per row | `transport_map_student` | `from_stop` and `to_stop` both become `BOARDS_AT` |
| Aggregate key is a term, not a month | `result_student_attendance_master` | Group by `(student_id, syear, term_id)` |
| Date column is `day`, no year column | `hrms_attendances` | Derive `syear` from `day` |
| Self-referencing tree | `lms_mapping_type` | `PARENT_OF` where `parent_id <> 0`; assert acyclic |
| Name-string joins | `s_jobrole_skills`, `s_jobrole_task` | Blocked on JOBROLE-KEY (§4) |

---

## 9. Phase 1 verification gate

The gate for this phase is **human review**, not a query. These are the mechanical checks that back
the document; all were run against the live source this session.

```
CHECK 1  Every table in the source appears exactly once in the register
         488 tables measured / 488 classified / 0 uncovered ............... PASS

CHECK 2  Every table has a decision from the closed vocabulary
         NODE 156 · EDGE 57 · AGG_EDGE 34 · PROP 30 · EXCLUDE 194 · REVIEW 17
         = 488 ............................................................ PASS

CHECK 3  Every table has a tier
         A 57 · B 189 · C 49 · D 193 = 488 ................................ PASS

CHECK 4  Every EXCLUDE carries a stated reason
         194 / 194 ........................................................ PASS

CHECK 5  Projected size inside the master-prompt budget
         nodes 596,825 < 700,000 .......................................... PASS
         relationships 1,853,827 < 4,000,000 .............................. PASS

CHECK 6  No ledger (>50k rows) is projected as nodes
         Tables >50k rows projected as NODE: lms_online_exam (147,875),
         tblstudent (83,715), lms_mapping_type (71,532), lms_question_master
         (62,209) — all four are entity tables, not ledgers ............... PASS

CHECK 7  Row counts are exact, not estimates
         COUNT(*) run per table, 488/488 ................................. PASS

CHECK 8  Nothing was written to MariaDB or Neo4j
         All queries SELECT-only; no connection opened to Neo4j .......... PASS

GATE     Human review of §3 (findings), §4 (blocking decisions), §5 (review
         items) ........................................................... AWAITING
```

**Phase 1 is not complete until a human signs off.** The four new blocking decisions in §4 change
what Phases 5, 6, 8, 11 and 13 build. Approving the register without settling CHAPTER-SOURCE and
FEES-MODEL means Phase 5 loads a curriculum whose children cannot attach, which is the defect this
migration exists to fix.

---

## 10. Sign-off

| | |
|---|---|
| Reviewed by | _(name)_ |
| Date | |
| CHAPTER-SOURCE decision | |
| CONCEPT-LINK decision | |
| FEES-MODEL decision | |
| JOBROLE-KEY decision | |
| TENANT-SCOPE decision | |
| §5 review items resolved | |
