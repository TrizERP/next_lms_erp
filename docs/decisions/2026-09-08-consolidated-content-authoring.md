# One shared content-authoring capability

**Delivers:** Tracker sheet "Content & LMS Architecture" row 3 · "Decisions & Risk Log" #36
**Date:** 2026-09-08 · **Phase:** A3
**Method:** 5 parallel code maps, each adversarially re-verified, plus a completeness critic

---

## What the tracker asked for

> One Generate (via central Generative AI Gateway) + Upload (via central Document Service)
> capability, reused contextually across Classroom Resource, Teacher Resource, and Question Bank —
> not three separately-built creation flows.

## The finding that reshaped the work

The sheet says *three* separately-built flows. **There are at least twelve writers of
`content_master` and six of `lms_question_master`.** The three named are a minority of the estate:

| Writer | Covered by row 3? |
|---|---|
| `ApiLmsCourseController::persistContent` (upload) | ✅ mapped |
| `contentController::storeGammaContent` (2 insert sites) | ✅ mapped |
| `IntelligenceQuestionGenerationApiController::generate` | ✅ mapped |
| `teacherapiController.php:402` — **mobile**, `routes/teacherapi.php` loaded with **no middleware at all** | ❌ |
| `CourseBuilderController.php:657`, `MyLearningController.php:811` — G2G LMS, incl. a **second Gamma client** and a **second AI question generator** (`DeepSeekAssessmentService`) | ❌ |
| `contentController.php:387/563` — the Blade `Route::resource` at `routes/lms.php:85` | ❌ |
| `contentLibraryController.php:357/359` | ❌ |
| `palController.php:524` — **student-authored** rows (`user_profile_name = 'student'`) | ❌ |

Two consequences worth stating plainly:

1. **Replacing the three endpoints with one would have produced a *fourth* implementation, not a
   single one.** Row 3's premise undercounts the problem by 4×.
2. **`content_master` contains student-authored content.** Both mapped write paths assume a
   `TEACHER|ADMIN` author. That assumption is already false in production.

## What was built

An **additional front door**, not a replacement — the first step of a strangler.

| File | Role |
|---|---|
| `config/lms_content.php` → `authoring_types` | The registry. 6 types × (entity, category, permission, modes, provider, upload allowlist). |
| `app/Services/lms/Content/AuthoringTypeRegistry.php` | Resolves a `content_type` to all of the above. |
| `app/Services/lms/Content/ContentAuthoringService.php` | The four common steps: resolve → produce → persist → record ownership. Steps 3-4 in one transaction. |
| `app/Services/lms/Content/ContentGenerationGateway.php` | Narrow AI façade over `GammaService`, Gemini and `QuestionGenerationService`. |
| `app/Services/lms/Content/ContentUploadService.php` | Per-type allowlist, tenant-scoped path, verified write, sha256. |
| `app/Http/Controllers/api/lms/ContentAuthoringController.php` | `POST /api/lms/content/author`, `GET /api/lms/content/authoring-vocabulary`. |
| `lms_k12/app/course-master/data/authoring.ts` | One client — `submitAuthoringJob` + `fetchAuthoringVocabulary`. |
| `lms_content_authoring_audit` table + model | One row per attempt: provider, model, tokens, latency, status, idempotency key. |
| `config/gamma.php`, `config/gemini.php` (`model` key) | Cache-safe key resolution. |

**The consolidation test:** adding a seventh authoring surface is a config entry, not a controller.
`test_every_registered_type_is_fully_resolvable_from_config_alone` asserts it.

### The two placeholders, and what they are placeholders *for*

Neither the Generative AI Gateway nor the Document Service exists — both are Track D's
(Central Engines rows 9 and the AI Stack sheet). Blocking row 3 on them would mean row 3 never ships.
So each is the **narrowest content-scoped version** that Track D can later absorb, and each names its
successor in its own docblock.

`ContentGenerationGateway` serves only `App\Services\lms\Content\*`. It explicitly does **not** capture
the ~13 other LLM call sites (`OpenAIService` from `contentController.php:1025,1147,1230,1274,1325,1350`,
`DeepSeekAssessmentService`, `ContentModelLlmClient`, `AiSopGenerationController`, the H5P controllers,
the two Next.js `app/api/ai/*` routes). Capturing them here would create the second competing gateway
this whole exercise exists to prevent.

`ContentUploadService` likewise leaves the other 118 `Storage::disk('digitalocean')` call sites alone.

## Why the three legacy endpoints were not deleted

`storeGammaContent` is **490 lines** (`contentController.php:1567-2056`): two providers, two insert
sites, ~15 response shapes, a 90-iteration synchronous poll, and a title that differs between branches
(`:1801` vs `:2003`). It is reachable on **two** routes with different middleware —
`api.php:170` (ungated) and `lms.php:88` (session + `check_permissions`).

Rewriting that while preserving byte-identical output on both routes is not a one-pass change, and the
callers are the Blade UI and possibly shipped mobile clients. **Step 2 — delegation — needs golden-file
tests capturing today's exact envelopes, and those need traffic capture.** Out of scope here, and named
so it is not mistaken for done.

What the legacy paths *did* gain is **provenance parity**: `persistContent` now records ownership after
its insert, wrapped in `try/catch` so a provenance failure can never turn a working upload into a 500 for
56 tenants. An unclassified row is a state the read path already handles by design
(`ContentOwnershipDecorator` renders `layer='unclassified'`), which is diagnosable rather than silently
wrong.

## Two bugs found along the way

**1. Gemini output is stamped as Gamma.** `contentController.php:1812` sets `'source' => 'Gamma AI'`
inside the **Gemini** branch. Every AI-generated document in `content_master` is mislabelled as having
come from Gamma. Left in place deliberately — `ApiLmsCourseController::applyContentSourceFilter` matches
on that literal, so correcting it would change filter results. The new path records the true provider in
provenance and in the audit table instead.

**2. Half the source filter is dead.** `applyContentSourceFilter` matches `['Gamma AI', 'aiGenerated']`,
but **nothing writes `aiGenerated`** into `content_master.source`. All three repo hits are unrelated
Gamma `imageOptions` payloads.

## Ownership is derived, never accepted from the client

`ContentProvenanceService` enforces a strict XOR: platform ownership requires the platform tenant, and
the platform tenant requires platform ownership. Tenant 1 holds **16,379 of 31,385** `content_master`
rows, so a client-supplied `teacher` ownership on tenant 1 would throw on half of real traffic.
`ContentAuthoringService::deriveOwnership()` is the only writer.

This endpoint is also the **first writer anywhere of `derived_from_entity_id`** — the overlay pointer
that makes "layered on top, never a fork" real going forward. The backfill deliberately leaves it NULL
because a historical derivation cannot be reconstructed after the fact.

## Known-open, and NOT closed by this work

A reviewer could reasonably assume `perm:lms.content,create` is airtight. It is the correct check; it is
not the only door.

- **CSRF is disabled in every deployed environment.** `VerifyCsrfToken.php:22-24` excludes
  `'https://erp.triz.co.in/*'` and `'https://dev.triz.co.in/*'` — whole-origin wildcards.
- **`routes/lms.php` registers the `lms/api/teacher_resource` CRUD with no middleware**, and its
  controller writes request-supplied identity into the session before validating — yielding a session
  cookie carrying an arbitrary `user_profile_name`, which defeats `check_permissions` on every
  session-gated route.
- **Nine AI generation routes sit outside every middleware group** (`routes/lms.php:352-361`), burning
  provider credits unauthenticated.
- **The mobile writer accepts a wider mime set** (`pdf,mp3,mp4,html,jpg,jpeg,png,link`) than the web
  upload (`pdf,ppt,pptx`). The per-type `upload_mimes` registry is the mechanism that will eventually let
  it migrate; migrating it now would break shipped clients.

All four are Track D / platform-wide.

## Status recommendation

Row 3: **`Built — one capability live as an additional front door; legacy paths retained pending
golden-file tests`**.

Claiming plain `Done` would imply the three flows are gone. They are not, deliberately — and nine other
writers the sheet never counted are still there.

## Verification

```bash
./vendor/bin/phpunit --filter AuthoringTypeRegistryTest    # 9 tests, 92 assertions, no DB
./vendor/bin/phpunit --testsuite Unit                      # 200 tests, 679 assertions
```

```
GET  /api/lms/content/authoring-vocabulary   -> 200, 6 authoring types
POST /api/lms/content/author {content_type:"nope"}     -> 422 with the allowed list
POST /api/lms/content/author {video, generate}         -> rejected (no generator registered)
POST /api/lms-chapter-content                          -> 200 (legacy read unchanged)
```

Frontend: `tsc --noEmit` 156 errors before and after — all pre-existing, none in the content area.
