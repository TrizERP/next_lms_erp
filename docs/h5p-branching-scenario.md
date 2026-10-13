# H5P Branching Scenario

The full report (what was verified about H5P, what was built in both repositories, how
generation works, test results and known limits) is kept beside the frontend, where the
earlier H5P integration reports live:

**`lms_k12/docs/h5p-branching-scenario.md`**

Backend files, for orientation:

| File | What it is |
| --- | --- |
| `database/migrations/2026_10_10_120000_create_h5p_branching_scenario_tables.php` | `h5p_branching_scenario`, `h5p_branching_nodes`. **Not run.** |
| `database/migrations/2026_10_10_130000_sync_h5p_branching_scenario_registry.php` | Re-publishes the PAL H5P registry so the hub card appears. **Not run.** |
| `app/Http/Controllers/lms/h5p/H5PBranchingScenarioController.php` | The type, on the shared `H5PContentTypeController`, plus `sources` and `generate`. |
| `app/Services/lms/H5P/BranchingGraph.php`, `BranchingPayload.php` | The rules a decision tree must satisfy (pure). Mirrored in the frontend by a golden file. |
| `app/Services/lms/H5P/H5PBranchingScenarioBuilder.php`, `…PackageService.php` | Rows ⇄ `H5P.BranchingScenario` params, and `.h5p` import/export. |
| `app/Services/StudyDeck/Branching/*` | The generator, built on the study-deck context, question selector and completer. |
| `app/Console/Commands/LMS/GenerateBranchingScenarioCommand.php` | `php artisan lms:generate-branching-scenario <chapter>`: writes a review bundle, stores nothing. |
| `tests/Unit/H5P/Branching/*`, `tests/Unit/StudyDeck/Branching/*` | No-database tests. |
