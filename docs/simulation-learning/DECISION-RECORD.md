# Simulation Learning in K12 Prayogshala: decision record

Date: 2026-10-28. Status: proposed, nothing built, nothing written to any shared database or bucket.
Every number below was read from the live schema or rows on 2026-10-28, not recalled.

## 1. What was measured

**Sync (step 0).**
- lms_k12 `Milan1`: `5dfa1b4` to `ba4e9c25`, fast-forward from `origin/master` (72 commits).
- next_lms_erp `milan1`: `a8e781346` to `0c91672a6`, fast-forward from `origin/development` (65 commits).
- Both trees were clean before and after. Neither update touched Prayogshala or simulations. Nothing was pushed.

**K12 database `vivek_erp` (MariaDB 10.11.9, host 202.47.117.220).**
- Tenant 1 ("Triz International School", `is_Lms = N`) holds 422 chapters, 6,851 topics, 7,142 concepts, and 748 Prayogshala activities.
- All 748 activities are in status `review`, so none is learner-visible yet.
- `lms_prayogshala_activity` has `UNIQUE (sub_institute_id, topic_slot)`. A tenant can hold exactly one lab per topic.
- The real `lms_concept` has `topic_id`, `review_status`, `source_evidence`, `confidence` and `concept_show_hide`. The migration file I read first did not show these.
- `review_status` on tenant 1 concepts: auto_accepted 2,077; flagged 1,921; legacy 1,759; low_confidence 1,181; verified 198; authored 6.
- The real table names are `chapter_master`, `topic_master` and `lms_concept`. There is no `lms_concepts` or `lms_topic`.
- Tenant-1 sharing already works. `config/lms_content.php` sets `platform_sub_institute_ids = [1]`, and `PrayogshalaService::visibleTenants` adds it for tenants with `is_Lms = Y`. Rows owned by another tenant come back `editable: false`.

**Source simulations (hp_erp dev and live, read-only).**
- Org 7 has four published simulations: `eye-lab`, `projectile-lab`, `pendulum-lab`, `ramp-lab`.
- The Traffic Control Room exists only in org 6.
- Three soft-deleted e2e rows (#8-#10) must not be copied.
- The **current spec of all four is byte-identical on dev and live** (same sha256, same asset set), but version numbers differ. Pendulum and Ramp are v4 on dev and v2 on live. The importer therefore keys on content hash, never on version number.
- Spec schemas: Eye and Projectile are `g2g.sim/1` (family `explorer`). Pendulum and Ramp are `g2g.sim/2`.
- Assets are stored at `sim_assets/platform/<sha256>.<ext>`, on disk `digitalocean`, in bucket `s3-triz`. K12 configures the same disk and the same bucket.
- Licences: every asset row carries one. There are CC BY 2.0/3.0/4.0 rows, which require visible credit. The rest are CC0 or public domain.

**Runtime build.**
- `g2gv0/public/sim-runtime` (6.9 MB, entry `index-BUn_GCar.js`) is identical to what production serves and supports the `hdr` asset kind.
- The runtime repo's own `dist-g2g` is stale (`index-DOF-rxwI.js`). **Take the vendored copy.**

**K12 front-end.**
- Next 16.3.6 and React 19.2.4.
- Headers: `frame-ancestors 'self'`, and a report-only CSP. Nothing blocks a same-origin sandboxed iframe.
- `headers()` has only the catch-all `/:path*` rule. The player iframe is sandboxed without `allow-same-origin`, so its origin is opaque. `/sim-runtime/*` therefore needs a CORS `*` rule, as g2gv0 has.

**K12 API pattern.**
- Group `api.session`, prefix `lms/prayogshala`. Tenant and user come from the JWT, never from the body.
- Reads are open to any authenticated role. Writes need `lms.auth` and `perm:lms.content,create`.

## 2. Decisions

| # | Decision | Why (evidence) |
|---|---|---|
| D1 | Simulations are a **sibling feature inside the Prayogshala tab**, not a new `lab_config` type. | The `topic_slot` unique key caps a tenant at one lab per topic. The existing engines are 2D and the 8-step flow is fixed. Simulations are runtime packages with their own assets. |
| D2 | **Five new tables**, `lms_` prefix, raw DDL, LONGTEXT, no foreign keys: `lms_simulation`, `lms_simulation_version`, `lms_simulation_asset`, `lms_simulation_concept`, `lms_simulation_progress`. | This is the house style. The `lms_` prefix avoids confusion with hp_erp's `g2g_` names. I dropped `g2g_simulation_tenant`, because tenant-1 sharing already does that job. |
| D3 | `lms_simulation_concept` is the many-to-many link, **anchored on `concept_id`**. | A concept belongs to exactly one chapter and one topic, so one simulation linked to several concepts can span topics and chapters. |
| D3a | The link table also holds `standard_id`, `subject_id`, `chapter_id`, `topic_id`, copied from `lms_concept` by the server (never from the client), plus `role` (primary or supporting), `citation`, `reason`, `review_status`, `reviewed_by` and `reviewed_at`. | `lms_prayogshala_activity` already denormalises this way, so lists need no join. A reconciliation query checks the copies against `lms_concept`. |
| D4 | **Visibility:** a learner sees a simulation only when the simulation is `published` and the link is `published`. Reads use `visibleTenants`. Writes are allowed only from a tenant-1 session. | Mirrors the existing `editable` rule, so another tenant can read but never edit tenant 1's rows. |
| D5 | **Interchange is package files**, not a cross-database read. Each package is spec JSON plus an asset manifest (sha256, kind, licence, credit, source URL), stored in K12 under `database/simulation-packages/`. | The K12 database user need not (and must not) have grants on `hp_erp`. A package can be reviewed in git and replayed in any environment. |
| D6 | **Assets are copied server-side to a K12-owned prefix** (`k12_sim_assets/<sha256>.<ext>`) and re-hashed to verify. | The two apps share a bucket, and hp_erp deletes objects on a failed upload. A shared key is a delete hazard in both directions. This needs a bucket write, so it waits for your "go". |
| D7 | **Concept mapping is a reviewed file keyed by names** (standard, subject, chapter, topic, concept), resolved to ids at import. The importer refuses to link what it cannot find. | Concept ids will differ between K12 environments. `PrayogshalaStandard9ScienceSeeder` already uses this name-lookup pattern. |
| D8 | Every link starts `review`. Nothing is learner-visible until a subject teacher signs the mapping sheet. | The tenant-1 concept ratings include 1,921 `flagged` and 1,181 `low_confidence`, and the sheet shows each concept's rating. |
| D9 | Take `g2gv0/public/sim-runtime` as it is into `lms_k12/public/sim-runtime/`, and add the CORS rule for `/sim-runtime/*`. | The build matches production. A rebuild could drift from the specs. |
| D10 | CC BY credits are read from the asset rows and shown in the player's info panel. | CC BY requires visible attribution. |

## 3. Mapping candidates (corrected after reading the raw topics and concepts)

These are evidence for the teacher's review sheet, not decisions. The draft is `database/simulation-packages/mapping.json` (16 links, names-keyed; every name tuple was checked to resolve to exactly one concept). Ids are from `vivek_erp` and may differ in other environments.

| Simulation | Links proposed (all start `review`) | Strength |
|---|---|---|
| Eye Lab | Std 10 Science, ch#1021 "The Human Eye and the Colourful World": Human Eye Structure, Power of Accommodation, Near and Far Points, Myopia, Hypermetropia (primary); Presbyopia (supporting). These six concepts (c#242-247) have **no topic**. | Strong on concepts. |
| Pendulum Lab | Std 7 Science, ch#25920 "Measurement of Time and Motion", topic "The Simple Pendulum": What a simple pendulum is, Time period of a pendulum, Time period depends on length (`flagged`). | Strong, but the lab's Earth/Moon/Mars control goes beyond Class 7. |
| Ramp Lab | Std 8 "Friction": Friction opposes the applied force, Rougher surfaces more friction (primary); Static and sliding friction (supporting). Std 9 "How Forces Affect Motion": Newton's second law, Net force from opposing forces (supporting). | Good. |
| Projectile Lab | Std 9 "Describing Motion Around Us": Gravitational acceleration in free fall, Kinematic equations set (both supporting). | **Partial.** Projectile motion in a plane is a Class 11 topic and tenant 1 has **no Std 11/12 Physics chapters**. The teacher may reject both. |

**What reading the raw rows corrected.** My first candidate table (from keyword search) had two errors:
- It listed Std 9 "Time period definition" and "Frequency and time period inverse" for the pendulum. They belong to the **sound waves** chapter and have nothing to do with a pendulum. Dropped.
- It called the Eye chapter's match clean. The chapter's only two topics are "Reflection of Light" and "Spherical Mirrors" (AI placeholders), and its 13 other concepts are prism, dispersion and scattering, which the Eye Lab does not teach.

**Data-quality defects in tenant 1 (reported, not fixed):** a topic "THE HUMAN EYE" (topic#2117) is filed under the Electricity chapter (ch#1022); chapter 1021's two topics do not match its title; 19 of its concepts have no topic; and the pendulum, friction and eye concepts carry OCR-garbled `source_evidence`, so `citation` cites the chapter and asks the teacher to add the section.

A plain keyword search is not a mapping. For example, "eye" also matched "Where to Put the Scale and the Eye" in Std 6 maths.

## 4. Risks and unknowns

- `vivek_erp` on 202.47.117.220 is the database the local K12 backend points at. I have **not** established whether it is K12's production database. Every write needs you to confirm the target first.
- Tenant 1's own `is_Lms` is `N`. The platform tenant works because a tenant always sees its own rows, but this is worth knowing if the sharing logic is ever refactored.
- hp_erp `Milan-2` has one unpushed docs-only commit (`e6932f59`). It doesn't affect this work.

## 5. Open questions for the owner

1. **Authoring UI.** Do you want hp_erp's "Add simulation" dialog (zip upload) in K12 now, or only the backend import commands and a player in v1? I recommend the second for v1, which keeps scope to "what you asked for".
2. **Projectile Lab.** If no honest mapping exists, should it stay unmapped (invisible to learners), or link to the free-fall concepts only?
3. **Write target.** Which database and bucket receive the first import: the dev copy you work against today, or production?
