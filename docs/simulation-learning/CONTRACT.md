# Simulation Learning in K12: backend/frontend contract (frozen unless amended here)

Read `DECISION-RECORD.md` first. Source to port from: hp_erp (`C:\Users\MILAN\Downloads\hp_erp`, branch Milan-2) and g2gv0 (`C:\Users\MILAN\Downloads\g2gv0`, branch milan1). **Both are read-only sources: never edit them.**

## Rules for everyone who touches this work

1. **No real database, no bucket.** Tests run on in-memory SQLite with the guard `assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a real database')` in `setUp`, exactly as `tests/Feature/PrayogshalaGenerationTest.php` does (`phpunit.xml` would otherwise point at the shared database). Never run `php artisan migrate`, `db:seed`, `tinker` against a real connection, or any command that writes to the `digitalocean` disk. Use `Storage::fake`.
2. **No `git commit`, no `git push`.** Leave changes in the working tree.
3. **Never print `.env` values.** The K12 `.env` holds live Spaces keys.
4. Write PHP/TS that contains backslashes with the Write or Edit tool, never a Bash heredoc.
5. ESLint: `node node_modules/eslint/bin/eslint.js ...`, not `npx eslint`. TypeScript: `node node_modules/typescript/bin/tsc --noEmit`.
6. Stay inside your own repo (backend agent: `next_lms_erp`; frontend agent: `lms_k12`). No git worktrees.

## Auth and tenancy (K12 pattern, replaces hp_erp's `anyaccess:` rights)

- Route group: `Route::middleware(['api.session'])->prefix('lms/prayogshala/simulations')`. Tenant and user come from the verified JWT/session, never from the body (see `PrayogshalaApiController` and `PrayogshalaService::tokenContext` / `visibleTenants`).
- **Read** (list, show, asset bytes, progress): any authenticated role. A learner sees the simulations of `visibleTenants($tenant)` (own tenant plus `config('lms_content.platform_sub_institute_ids')` when the tenant is `is_Lms = Y`) with `status = 'published'` and `deleted_at IS NULL`. A simulation is **mapped** when it has at least one link with `review_status = 'published'` (every card carries `mapped: true|false`). A **mapped** simulation shows under the chapters, topics and concepts it is linked to. A simulation **not mapped yet** (no published link: none at all, or only links in review) shows in EVERY chapter's tab (a `chapter_id`, `standard_id` or `subject_id` filter), flagged, and never under a `topic_id` or `concept_id` filter. A learner is never shown a link that is still in `review`. Staff (non-student) additionally see links in `review`; authors also see drafts.
- **Write** (store, update, delete, restore, asset upload, link edits, link review): `lms.auth` + `perm:lms.content,create`, AND the session tenant must be one of `platform_sub_institute_ids` (so only tenant-1 staff can author or change). Any other tenant gets 403 on writes and sees rows with `editable: false`.
- `progress` is always about the caller; the learner's own tenant id is stored.

## Tables (raw DDL, LONGTEXT for JSON, no foreign keys, no JSON column type; migration dates after `2026_10_27_120000`)

Port hp_erp's `g2g_simulation`, `g2g_simulation_version`, `g2g_simulation_asset`, `g2g_simulation_progress` as `lms_simulation`, `lms_simulation_version`, `lms_simulation_asset`, `lms_simulation_progress` (same columns, same indexes, renamed index names). Drop `g2g_simulation_tenant`. Add:

`lms_simulation_concept` (the link): `id`, `simulation_id`, `concept_id`, `standard_id`, `subject_id`, `chapter_id`, `topic_id` (nullable), `role` varchar(12) default `primary` (`primary|supporting`), `citation` varchar(255) null (textbook section), `reason` varchar(500) null, `review_status` varchar(16) default `review` (`review|published`), `reviewed_by` null, `reviewed_at` null, `sort_order` default 0, `created_by` null, timestamps. **UNIQUE (`simulation_id`, `concept_id`)**; indexes on `concept_id`, `chapter_id`, `topic_id`, `simulation_id`. Hard delete (no soft delete). `standard_id`, `subject_id`, `chapter_id`, `topic_id` are copied from `lms_concept` by the SERVER on every write; the client never supplies them.

## Endpoints (all under `/api/lms/prayogshala/simulations`; response envelope as hp_erp's controller: `{ "success": true|false, "data": ..., "message": ... }`; keep the same field names for the card, spec, assets and credits as hp_erp's `LmsSimulationController`/`SimulationStore`)

| Method + path | Who | Notes |
|---|---|---|
| `GET /` | any | Query: `chapter_id`, `topic_id`, `concept_id`, `standard_id`, `subject_id`, `q`. Each card gains `links: [{ id, concept_id, concept_name, chapter_id, chapter_name, topic_id, topic_name, standard_id, subject_id, role, citation, reason, review_status }]` (learners: published only). Also `editable` (bool). |
| `GET /{id}` | any | Card + current spec + `assets[]` credits (as hp_erp) + `links`. Staff may pass `version`. |
| `GET /{id}/assets/{sha256}` | any | Bytes from the private disk; only for a hash the current spec names (as hp_erp). |
| `GET /{id}/versions` | write | |
| `GET /limits`, `POST /assets` | write | Upload (multipart), as hp_erp incl. throttle `simulation-assets`. |
| `POST /` , `PUT /{id}`, `DELETE /{id}`, `POST /{id}/restore` | write | As hp_erp (spec validation, versions, content-hash). Body: card fields + `spec`. Owner tenant = session tenant. |
| `POST /{id}/progress` | any | As hp_erp. |
| `GET /{id}/links` | any | Same shape as `links` above. |
| `PUT /{id}/links` | write | Replace the link set. Body `{ links: [{ concept_id, role?, citation?, reason?, sort_order? }] }`. Server resolves each concept and derives chapter/topic/standard/subject; unknown or other-tenant concept ids are a 422 naming the id. New links start `review`; links that are unchanged keep their `review_status`. |
| `POST /{id}/links/{linkId}/review` | write | Body `{ status: "review"\|"published" }`; stamps `reviewed_by/at`. |
| `GET /concepts?chapter_id=&q=` | write | Concept picker for the link editor: `[{ id, name, topic_id, topic_name, chapter_id, review_status }]`, tenant-1 concepts only. |

Dropped from hp_erp: `catalogue`, `enable`, `disable` (tenant-1 sharing replaces them).

## Artisan commands (backend agent; each is dry-run unless `--apply`)

- `lms:simulations-import {path}`: import package file(s) from `database/simulation-packages/`. Idempotent on (`sub_institute_id`, `slug`, `spec_sha256`): same hash = no-op; new hash = new version. Refuses a spec that names an asset not registered or not on the disk. Prints a before/after row-count report.
- `lms:simulations-assets-copy`: copy asset objects from `--from-prefix` (default `sim_assets/platform`) to the K12-owned prefix `k12_sim_assets/<sha256>.<ext>` on the same disk, re-hash the copied bytes and compare with the sha256 in the package manifest; register `lms_simulation_asset` rows. Never deletes from the source prefix.
- `lms:simulations-mapping-sheet {--out=}`: CSV for the subject teacher (simulation, concept id/name, chapter, topic, standard, subject, concept `review_status`, citation, reason, link review_status, sign-off column blank).
- `lms:simulations-mapping-import {file}`: load the reviewed mapping JSON (names-keyed: standard, subject, chapter, topic, concept) and resolve to ids inside tenant 1 exactly like `PrayogshalaStandard9ScienceSeeder` does; refuse (and list) anything it cannot find; creates links in `review`.
- `lms:simulations-reconcile`: READ-ONLY, exit code non-zero on any failure. Checks: simulation count per tenant; zero orphan links (simulation or concept missing); every link's standard/subject/chapter/topic equals its concept's; every asset sha256 named by a current spec has a row; with `--check-bucket`, re-hash each object and compare. Prints PASS/FAIL per check.

## Package file (`database/simulation-packages/<slug>.package.json`, written by the coordinator, not by the agents)

```json
{ "format": "k12.sim-package/1", "slug": "...", "title": "...", "subtitle": "...", "summary": "...", "category": "...",
  "tags": "...", "audience": "...", "minutes": 15, "difficulty": "intro", "missions": 2, "cover": "simulations/covers/<slug>.webp",
  "spec_json": "<the canonical spec TEXT exactly as hp_erp stores it, a JSON string>", "spec_sha256": "<sha256 of spec_json, 64 hex>",
  "assets": [ { "sha256": "...", "kind": "model|image|hdr", "mime": "...", "bytes": 0, "name": "...", "licence": "...", "credit": "...", "source_url": "...", "source_key": "sim_assets/platform/<sha>.<ext>" } ],
  "source": { "host": "dev|live", "simulation_id": 0, "version": 0 } }
```

Mapping file `database/simulation-packages/mapping.json` (names-keyed, written by the coordinator): `[{ "slug": "...", "links": [{ "standard": "10", "subject": "Science", "chapter": "...", "topic": "...", "concept": "...", "role": "primary", "citation": "...", "reason": "..." }] }]`.

## Frontend (lms_k12)

- Lives in the existing Prayogshala tab (`app/course-master/[courseId]/chapters/Prayogshala*.tsx`): a **Simulations** section at chapter level (simulations linked to any concept of the chapter) and inside the topic panel (linked to concepts of that topic), plus concept chips on each card. A learner sees published links only; staff see `review` links marked as pending. A card whose `mapped` is false carries a small flag icon: "Not mapped yet to a concept and topic".
- API client modelled on `app/course-master/data/prayogshala.ts` (`API_BASE_URL`, `buildSessionContext`).
- Player: the runtime iframe is `sandbox="allow-scripts"` WITHOUT `allow-same-origin`; port g2gv0's `lib/simulation-host.ts` + `simulation-player.tsx`; asset bytes are fetched with the learner's bearer token and posted into the frame. Runtime copied from `g2gv0/public/sim-runtime` to `public/sim-runtime`; add the CORS `*` rule for `/sim-runtime/*` in `next.config.ts` as g2gv0 has it.
- Authoring (tenant-1 staff only, hidden otherwise): the Add/Edit simulation dialog with the zip-upload Create tab (port `add-simulation-dialog.tsx`, `create-simulation-tab.tsx`, `lib/simulation-package*.ts`; K12 already depends on `fflate`), and a **Link to concepts** editor (concept picker, role, citation, reason, publish/unpublish a link).
- Show CC BY credits in the player's info panel from `assets[]`.
- Use K12's own design system and components (`components/ui`, `K-12 ERP Design System`, existing Prayogshala styling), not g2g tokens. Do not change the lab engines or the existing 8-step lab.
- Covers: copy `g2gv0/public/simulations/covers/{eye-lab,projectile-lab,pendulum-lab,ramp-lab}.webp` (NOT traffic-control-room) to `public/simulations/covers/`.
