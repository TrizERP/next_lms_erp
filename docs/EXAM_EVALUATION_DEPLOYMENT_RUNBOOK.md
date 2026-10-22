# Exam answer-sheet evaluation — deployment runbook

Applies to: Exam & Assessment > Operations > Exam evaluation (AI-assisted marking of scanned answer
sheets), including the student-identification stage. Written 2026-10-13.

**Status when this was written:** all of it is uncommitted working-tree code. **Neither migration has
been applied** to the shared database (confirmed by a read-only inspection, below). Nothing here has
been exercised with a real scan or a live Gemini call.

How to read this: *Verified* means it was run and observed. *Reasoned* means it follows from the code
or from documentation but was not run. Where the difference matters it is stated.

---

## 1. What changes for users

| Before | After |
|---|---|
| Upload started full AI evaluation at once. | Upload only **reads the student's details**. The sheet waits ("Confirm student") until a teacher confirms or picks the student; only then is it marked. |
| Any student at the school could be assigned to a sheet. | Only students **eligible for the exam** (enrolled that year in the class the paper was set for). |
| An approved sheet could be changed, re-stamped or reset by a second request. | Approval, re-open and re-run are decided under row locks; a second request sees the first one's result. |
| "Re-open" worked on any sheet. | Only an **Approved** sheet can be re-opened. |
| A sheet whose job never finished was stuck. | After 45 minutes untouched it is flagged *stalled* and can be re-queued. |
| Removing/re-running a sheet in a published batch was allowed. | Refused. Removals and re-runs are audited. |

## 2. Environment settings (production)

Set these in the production environment. **Do not edit the shared `.env` from a workstation.**

| Variable | Value | Why |
|---|---|---|
| `QUEUE_CONNECTION` | `database` or `redis` (**not** `sync`) | With `sync` an upload runs every sheet inside the web request. |
| `QUEUE_RETRY_AFTER` | `660` | Must exceed the longest job timeout (evaluation 600 s). See section 3. |
| `CACHE_DRIVER` | a store **shared by every worker** (`redis`, `database`, `memcached`); `file` only if all workers run on one host | The per-sheet overlap lock lives in the cache. |
| `GEMINI_API_KEY` | a **Google AI Studio** key: starts `AIza`, 39 characters | Without it every sheet fails with a clear message. A Vertex AI key (`AQ.`, 53 chars) or an OAuth token (`ya29.`) is refused by the Gemini Developer API with "Request had invalid authentication credentials". See section 9. |
| `GEMINI_MODEL` | a live model | A retired id (e.g. `gemini-2.5-flash`) is mapped forward by `config/ai.php`. |
| `GEMINI_FALLBACK_MODEL` | optional; a model that is **live on your key** | Empty = no fallback. See section 4. |
| `GEMINI_DOCUMENT_TIMEOUT` | `90` (or your choice) | Timeout for whole-document calls. See section 4. |
| `GEMINI_REQUEST_TIMEOUT` | leave unset, or `45` **only together with** `GEMINI_DOCUMENT_TIMEOUT` | The "brain" text client's timeout. See section 4. |

After changing environment or config on a server: `php artisan config:clear` (or re-cache), then
`php artisan queue:restart` so workers pick it up.

Run at least one real worker, supervised, for the connection in use, for example
`php artisan queue:work <connection> --sleep=3 --max-time=3600`. The jobs set their own `$timeout`
(evaluation 600 s, identification 300 s); a worker `--timeout` below those would cut them short.

## 3. The queue lease (`retry_after`) and the 600-second job

A queued job is *leased* to one worker for `retry_after` seconds. If it has not finished by then the
queue assumes the worker died and delivers the job to **another** worker. A job whose own timeout is
longer than the lease therefore runs twice whenever it runs long.

| Job | `$timeout` | `$tries` | Backoff | Lease needed |
|---|---|---|---|---|
| `EvaluateAnswerSheetJob` | 600 s | 4 | 60 / 180 / 420 s | > 600 s |
| `IdentifyAnswerSheetJob` | 300 s | 4 | 30 / 120 / 300 s | > 300 s |

**`QUEUE_RETRY_AFTER=660`** is the timeout plus a minute's margin, and is also how long each job's
overlap lock is held. Before this change the variable reached only the `database` connection; `redis`
and `beanstalkd` were fixed at 90. It now applies to all three. *Verified:* the real `config/queue.php`
loaded under unset / 660 / 0 / negative / non-numeric values (a value that is not a positive number
falls back to 90, never to an instant redelivery). SQS has no such setting here: set its **visibility
timeout** above the longest job timeout on the queue itself.

If a connection's lease is not longer than a job's timeout, the job logs one warning per process
("Queue retry_after is not longer than this job's timeout…"). It changes nothing; it exists so the
misconfiguration is visible. *Verified in tests.*

### The cost, and an alternative

A longer lease slows recovery from a **crashed worker**: its job sits invisible for the whole interval
(11 minutes at 660) before another worker retries it. This applies to **every** job on that connection:

| Other jobs on the default connection | `$timeout` |
|---|---|
| `EvaluateHomeworkSubmissionJob` | 600 s |
| `GeneratePrayogshalaActivityJob` | 400 s |
| `EvaluateHomeworkSubmissionV2Job`, `EvaluateAssignmentSubmissionJob`, `ProcessDocumentPipelineJob` | 300 s |
| `SendAlumniCampaignJob` | **1800 s** |

So 660 is not a pure cost: at today's 90 s these jobs are *already* redelivered while running. It
fits all of them except `SendAlumniCampaignJob`, which is longer than 660 (pre-existing; 90 is worse).
If crash-recovery time matters more than that, give exam evaluation its own queue and worker with its
own lease, and leave the default queue alone. *Reasoned, not built* (it needs a new worker process):

```php
// config/queue.php — a second connection onto the same table with its own lease
'exam_database' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'exam', 'retry_after' => 660, 'after_commit' => false],
// run:  php artisan queue:work exam_database --queue=exam
// and dispatch the two jobs with ->onConnection('exam_database')->onQueue('exam')
```

## 4. Gemini client: what changed, and what `GEMINI_REQUEST_TIMEOUT=45` does

`App\Services\Homework\GeminiClient` is **shared** with homework and assignment marking. Changes:

* **Timeout.** At HEAD the client read `GEMINI_REQUEST_TIMEOUT` straight from the environment (default
  90). It now reads `config('ai.provider.gemini.document_timeout')`, so a *cached* config honours the
  variable too (before, with `config:cache`, it was silently ignored and 90 applied). Precedence:
  `GEMINI_DOCUMENT_TIMEOUT`, then `GEMINI_REQUEST_TIMEOUT`, then 90.
  **Implication:** the same variable is the brain's 45 s text timeout. `GEMINI_REQUEST_TIMEOUT=45`
  therefore also gives scanned documents 45 s — as it always did uncached, and as it now does cached
  too. A multi-page scan can take longer than 45 s, which surfaces as a timeout. **If you set
  `GEMINI_REQUEST_TIMEOUT=45`, set `GEMINI_DOCUMENT_TIMEOUT=90` beside it.** A timeout is *not* retried
  inside the client (only HTTP 429/5xx are); the queue retries the job on its backoff.
  *Verified:* config precedence under each combination; the local `.env` sets neither.
* **Fallback model.** The old built-in default was `gemini-2.5-flash`, which `config/ai.php` itself
  lists as retired, so "falling back" to it was a second refusal. **There is now no default fallback.**
  If you want one, name a model that is live on the key. *Not verified:* whether `gemini-2.5-flash` is
  still served on the live key — no live calls were made. This is a behaviour change for homework and
  assignment marking if it was in fact still working.
* **Additive only:** `finish_reason` in the result; HTTP 429 raises `RateLimitedException`, a
  `RuntimeException` subclass, so every existing `catch (\RuntimeException)` still works; a refused
  prompt says why; logged provider bodies are cut to 1,000 characters.
* **Privacy:** scans (names, roll numbers) are sent to Gemini. `docs/PAL_LEARNING_COACH_LEGAL_REVIEW_BRIEF.md`
  raises DPDP concerns; that decision is the owner's and is unchanged.

## 5. Migrations

Two, in this order. Both are guarded (safe to run twice or against a partly migrated table), and neither
depends on the other.

1. `2026_10_13_100000_add_review_fields_to_exam_evaluation` — `exam_evaluation_answer.teacher_feedback`
   (nullable text) and a unique `(batch_id, student_id)` on `exam_evaluation_sheet`. If duplicate
   sheets already exist it **skips the index** and logs ids and counts, deleting nothing; resolve the
   duplicates and run it again.
2. `2026_10_13_110000_add_identity_confirmation_to_exam_evaluation_sheet` — nullable
   `identity_confirmed_at`, `identity_confirmed_by`. Nothing is back-filled: a hand-picked student is
   already read as confirmed through `identity_source = 'manual'`. New sheet statuses need no schema
   change (`status` and `identity_source` are `varchar(30)`).

**Rollback** (`down()`): drops exactly what was added. **It discards data:** teacher feedback (1) and
confirmation stamps (2). Export them first if they matter. Rows are never deleted.

Until migration 2 is applied the application is safe but degraded: confirming an AI suggestion writes
`identity_source = 'manual'` (which every job already refuses to overwrite), so the screens say
"Selected by a teacher" instead of "Confirmed". After it is applied, a long-running process notices
within a minute — no worker restart is needed for this flag.

### What was checked, and where

| Check | Where | Result |
|---|---|---|
| Both migrations: idempotent, partial state, existing rows untouched, reversible, independent, either order | in-memory SQLite (`IdentityConfirmationMigrationTest`, `ReviewFieldsMigrationTest`) | pass |
| Same, plus column **position** and exact **types**, the unique index on InnoDB, duplicate path, the `AFTER` guard | a throwaway **MariaDB 10.4.32** on a private port (`IsolatedMysql/MigrationsMysqlTest`) | pass |
| Shared database schema vs the migrations' assumptions | read-only inspection of the shared DB on 2026-10-13 | below |

**Not run:** either migration on the production engine itself. The shared database reports
**MariaDB 10.11.9**; tests ran on 10.4.32. The statements are plain `ALTER TABLE … ADD COLUMN` and
`ADD UNIQUE`, so a difference is unlikely, but it is reasoned, not observed.

**Read-only inspection of the shared database (2026-10-13; schema metadata and counts only, in a
`READ ONLY` transaction):**

* Neither migration is recorded and neither's columns/index exist. (`2026_10_13_100000_create_alumni_donation_tables`
  shares the first migration's timestamp prefix but is a different file.)
* `exam_evaluation_batch/_sheet/_answer` exist and match `2026_09_21_000000`; 1 batch, 2 sheets, 8 answers
  (both sheets `Failed`). No duplicate `(batch, student)` pairs, so the unique index will be created.
* **415 of the repo's 1,137 migration files are not recorded in the shared database's `migrations`
  table** (294 from 2023, 110 from 2026 — mostly baseline `create_*_table` files and column additions for
  schema that already exists there). A bare `php artisan migrate` there would try to run all 415.
  **Apply these two migrations by path, one at a time, and never run a bare `migrate`** (see below).
* `jobs` and `failed_jobs` exist and are empty.
* **`tblstudent_enrollment.drop_code` is `0`, not `NULL`, on 1,932 of 23,568 enrollments in 2026, all in
  one school**, with no drop remark and almost none with an end date: they are current students. The
  class list now counts `NULL` **or** `0` as enrolled; otherwise that school's students would be on
  no class list and could not be assigned a sheet.
* **GR numbers are repeated** across the school (5,515 repeated school+GR pairs) and **roll numbers
  restart in every section** of a class. The matcher only accepts a field that names exactly one
  student on the class list, so these produce "ambiguous", never a wrong pick, but roll-number matching
  will often defer to the teacher in multi-section classes.
* **25 question papers name no class.** Their eligible list would be the whole school; the exam picker
  now says so (and how many students are eligible) before any scanning.

### Read-only SQL checks (run on staging first, then production)

```sql
-- migration recorded? columns? index?
SELECT migration, batch FROM migrations WHERE migration LIKE '2026_10_13_1%' AND migration LIKE '%exam_evaluation%';
SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE()
  AND ((table_name = 'exam_evaluation_answer' AND column_name = 'teacher_feedback')
    OR (table_name = 'exam_evaluation_sheet' AND column_name IN ('identity_confirmed_at', 'identity_confirmed_by')));
SELECT index_name, non_unique, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS cols
  FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'exam_evaluation_sheet'
   AND index_name = 'ees_batch_student_unique' GROUP BY index_name, non_unique;

-- would the unique index be created? (must be 0 pairs)
SELECT COUNT(*) AS pairs_with_duplicates FROM (SELECT 1 FROM exam_evaluation_sheet WHERE student_id IS NOT NULL
  GROUP BY batch_id, student_id HAVING COUNT(*) > 1) d;

-- health
SELECT status, COUNT(*) FROM exam_evaluation_sheet GROUP BY status;
SELECT COUNT(*) AS stalled FROM exam_evaluation_sheet WHERE status IN ('Identifying','Pending','Processing')
  AND updated_at < NOW() - INTERVAL 45 MINUTE;
SELECT COUNT(*) AS approved_unmarked FROM exam_evaluation_sheet s WHERE s.status = 'Approved'
  AND EXISTS (SELECT 1 FROM exam_evaluation_answer a WHERE a.sheet_id = s.id AND a.teacher_marks IS NULL);

-- the class list's assumptions
SELECT drop_code IS NULL AS is_null, drop_code = 0 AS is_zero, COUNT(*) FROM tblstudent_enrollment WHERE syear = <year> GROUP BY 1, 2;
SELECT COUNT(*) AS papers_without_class FROM question_paper WHERE standard_id IS NULL OR standard_id = 0;
```

### Applying them — by path only

```bash
# show the SQL without running it (select-style checks do not run in pretend mode, so it prints the ALTERs as if absent)
php artisan migrate --pretend --path=database/migrations/2026_10_13_100000_add_review_fields_to_exam_evaluation.php
# then, with approval, one file at a time, in this order:
php artisan migrate --path=database/migrations/2026_10_13_100000_add_review_fields_to_exam_evaluation.php
php artisan migrate --path=database/migrations/2026_10_13_110000_add_identity_confirmation_to_exam_evaluation_sheet.php
php artisan migrate:status   # read-only: confirm both now show as Ran, and that nothing else changed
```

`--path` runs only that file and records only it. Do not use `--force` with a bare `migrate`, `migrate:fresh`,
`migrate:refresh` or `migrate:rollback` against the shared database.

### Prerequisites for a safe staging migration

1. A staging database with the same engine (MariaDB 10.11) and a copy of the `exam_evaluation_*` tables.
2. Backup of those three tables (and `lms_offline_exam`, which publish writes).
3. The read-only checks above, with **0 duplicate pairs**.
4. A low-traffic window; the tables are small and the `ALTER`s add nullable columns and one index.
5. A deploy of **backend and frontend together** (the old frontend has no control for the new statuses).
6. Explicit approval to apply. Applying either migration is a separate decision from deploying the code.

## 6. Staging run-through (fake data only — no real scans, no live Gemini)

1. Apply both migrations on staging **by path** (section 5); run `php artisan config:clear` and start one supervised worker.
2. Create an evaluation on a paper that **has a class**; the picker shows the eligible count. Also try
   a paper with no class and confirm the warning.
3. Upload a multi-page PDF. The sheet goes **Reading student → Confirm student**; nothing is marked.
4. Confirm the suggested student; marking starts. Repeat choosing **another** student from the picker.
   Try a student from another class / a dropped student: refused.
5. Upload two scans of one student: the second shows "already has another sheet" and no suggestion.
6. Review a sheet: edit marks, add feedback, save, reload. Try a mark above the maximum.
7. Approve with an unmarked flagged question (blocked). Approve; **approve again in a second tab**
   (no second stamp); try an edit after approval (refused); **re-open**, then try to re-open again
   (refused); re-open a failed sheet via the API (refused).
8. Move a confirmed sheet to another student: a reason is required; check the audit row has ids only.
9. Re-run a sheet: marks, feedback and the confirmed student survive.
10. Break the AI key; watch retries end in a clear failure. Stop the worker mid-identification, wait 45
    minutes (or backdate `updated_at` in staging), and use "Try again".
11. Publish; publish again (refused). Remove a student from the class and publish a second batch:
    refused with the reason.
12. A student login and a second school's login are both refused.

## 7a. Where the scans live (a deployment prerequisite)

A sheet's scan is stored on the **local disk** of whichever server took the upload
(`storage/app/exam_evaluation`), while sheet rows live in the shared database. So every web server **and
every queue worker** must see the same directory — mount one shared volume (or move storage to shared
object storage) before running more than one machine. Otherwise a sheet uploaded on one machine fails on
another with "The uploaded file could not be found on the server". The same happens on a developer
machine pointed at a shared database: sheets uploaded from elsewhere cannot be read there. *Observed on a
developer machine on 2026-10-13; the multi-host case is reasoned, not tested.* The failure message now
says to delete the sheet and upload it again.

## 7. Rollback

* Before migrating: redeploy the previous code; nothing else changed.
* After migrating: older code ignores the new columns. Drain the queue first so in-flight jobs aren't lost.
* To remove the columns run the `down()` methods (they drop feedback and confirmations — export first,
  under access control). Published results are unaffected.

## 8. Known limits

* Roll numbers repeat across sections; a paper belongs to a class, not a section, so roll matching is
  often ambiguous. An optional section filter when creating a batch would fix that (not built).
* A published batch cannot be corrected; there is no unpublish.
* Any non-student staff member can review, approve and publish; there is no per-menu or ownership check,
  and the batch list shows every teacher's batches in the school.
* Publish replaces rows in `lms_offline_exam` by paper and student; `obtain_marks` is an integer.
* Publishing writes only `lms_offline_exam`. Nothing writes `result_marks`/`result_create_exam`, and
  no student-facing page reads the result.
* True concurrency was tested on a real MariaDB; SQLite (which has no row locks) only stages the
  interleavings. A stalled sheet re-queued while its job is secretly still running is dropped by the
  overlap lock (reasoned from Laravel's middleware; the lock itself is covered by an existing test).

## 9. Troubleshooting a failed sheet

The reason is shown on the sheet itself ("Failed" in the evaluation list).

| What it says | Cause | Fix |
|---|---|---|
| "The AI provider rejected the configured credential… (provider said: Request had invalid authentication credentials. Expected OAuth 2 access token, login cookie or other valid authentication credential)" | Google refuses the key. Seen on 2026-10-13 with a 53-character `AQ.…` key (the shape of a Vertex AI key) in `GEMINI_API_KEY`. **Tested live with metadata-only calls (no prompt, no student data): the Gemini Developer API answered `401 UNAUTHENTICATED` and Vertex AI answered `401 API key not valid`**, so that key was dead (expired, revoked or disabled), not merely the wrong kind. The app adds "looks like a Vertex AI key" when the format suggests it; that is a hint, not proof. | Create a key at <https://aistudio.google.com/apikey> (starts `AIza`, 39 characters), set `GEMINI_API_KEY`, `php artisan config:clear`, then **read the failed sheets again**. This service calls the Gemini Developer API only; it has no Vertex support. |
| "Gemini API key is not configured" / "GEMINI_API_KEY is empty" | No key. | As above. |
| "The daily allowance for … is spent" | Per-model free-tier quota. | Wait, add billing, or name another model in `GEMINI_FALLBACK_MODEL`. |
| "The uploaded file could not be found on the server…" | The scan is not on this machine (section 7a) or was removed. | Delete the sheet and upload it again. |
| Spinner for a long time ("taking much longer than usual") | The job was lost (no worker running, or it died). After 45 minutes untouched the sheet shows **Try again**. | Start or fix the worker; use Try again. |

A rejected or missing key fails a sheet **at the first stage** (reading the student) with the real reason;
it is not recorded as "unreadable scan". The exam picker also warns, before anything is uploaded, when
the key's *format* shows it cannot work (empty, `AQ.`, `ya29.`). That check makes no request and never
shows the key; an unfamiliar format raises no warning, and a real rejection is still reported by the 401.
After the key is fixed, nothing needs re-uploading: failed sheets are read again from the stored scan.
