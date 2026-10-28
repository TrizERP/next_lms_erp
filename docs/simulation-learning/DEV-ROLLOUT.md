# Dev rollout record: Simulation Learning in K12 (2026-10-28)

Target: the dev copy, database `vivek_erp` on 202.47.117.220, and bucket `s3-triz` (disk `digitalocean`). Authorised by the owner ("Go, all 4 steps"). **Production was not touched.** Acting user for `created_by`: 6956 (tenant 1, "LMS Teacher").

## What was written, in order

| # | Step | Command | Result |
|---|---|---|---|
| 1 | Create 5 tables | `php artisan migrate --path=database/migrations/2026_10_28_100000_create_lms_simulation_tables.php --path=database/migrations/2026_10_28_100100_create_lms_simulation_concept_table.php` | `lms_simulation`, `lms_simulation_version`, `lms_simulation_asset`, `lms_simulation_progress`, `lms_simulation_concept`. No existing table was changed. |
| 2 | Copy 63 files to `k12_sim_assets/<sha256>.<ext>` and register them | `php artisan lms:simulations-assets-copy --user=6956 --apply` | 63 copied, each re-hashed against its manifest sha256; 63 registered; 0 failed. The originals in `sim_assets/platform/` were not touched. |
| 3 | Import 4 simulations (tenant 1) | `php artisan lms:simulations-import database/simulation-packages --user=6956 --apply` | Created ids 1-4: eye-lab, pendulum-lab, projectile-lab, ramp-lab, version 1 each. A re-run changes nothing. |
| 4 | Create 16 concept links, all `review` | `php artisan lms:simulations-mapping-import database/simulation-packages/mapping.json --user=6956 --apply` | 16 created, 0 refused. |

Always use `--path` for K12 migrations: other migrations may be pending on the shared dev database.

## Checks run afterwards (all passed)

- `php artisan lms:simulations-reconcile --check-bucket`: 6 checks, ALL PASSED, including re-hashing 63 bucket objects.
- `docs/simulation-learning/reconcile.sql`: 11 checks returned zero rows, including "traffic-control-room present".
- Browser, real K12 app: login, chapter Prayogshala tab, Simulations section, detail sheet, player in a sandboxed iframe (`allow-scripts` only), authenticated asset downloads, progress save, concept-link editor.

## Rows that came from testing, not from the rollout

- `lms_simulation_progress` id 1: user 6956, simulation 1, 2%. It was created by the browser check. Remove with `DELETE FROM lms_simulation_progress WHERE id = 1;`.

## Learner visibility

All 16 links are in `review`. A learner sees a simulation only through a **published** link of a **published** simulation, so no learner sees any of them until a subject teacher signs `TEACHER-MAPPING-SHEET.csv` and a tenant-1 author publishes the links.

## Undo (reverse order)

1. Links: `DELETE FROM lms_simulation_concept;`
2. Simulations: `DELETE FROM lms_simulation_progress; DELETE FROM lms_simulation_version; DELETE FROM lms_simulation;`
3. Files: for each `storage_path` in `SELECT storage_path FROM lms_simulation_asset;` delete that object from the bucket (only keys under `k12_sim_assets/`; never `sim_assets/`). Then `DELETE FROM lms_simulation_asset;`
4. Tables: `php artisan migrate:rollback --path=database/migrations/2026_10_28_100100_create_lms_simulation_concept_table.php --path=database/migrations/2026_10_28_100000_create_lms_simulation_tables.php`

## Not done / not verified

- Production: no migration, no import, no deploy. The code is uncommitted in both K12 repos.
- Only one login (tenant-1 staff) was used in the browser. The student, other-tenant and 403 rules are proven by the backend's SQLite tests, not in a browser.
- Of the Eye Lab's 9 files, the 2 models loaded at start. The 7 images are requested on demand by the runtime; their loading was not separately observed.
- The runtime bundle still contains the Traffic Control Room as its built-in default. It plays only if `/sim-runtime/index.html` is opened without `?host=1`. The owner accepted this.
