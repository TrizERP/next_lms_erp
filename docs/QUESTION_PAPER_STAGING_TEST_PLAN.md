# Question Paper processing: staging end-to-end test plan

**Status: NOT RUN.** Nothing in this document has been executed. The acceptance criterion below is met only when every
step passes on a real staging environment, and the result is written down in section 9.

## Acceptance criterion

Uploading a Question Paper PDF on staging (1) automatically starts persistent background processing with no manual
command, (2) keeps going after the browser is closed, (3) saves validated questions to the question bank with the
right academic mapping, and (4) creates exactly one completion notification.

## 0. Rules

- **Staging only.** A separate database, a separate Spaces bucket or prefix, and a separate AI key budget. Never the shared
  `vivek_erp`, never the production Spaces bucket.
- Use a **non-sensitive** 2-4 page text PDF written for this test (known questions, so results can be checked line by line),
  plus one scanned/image PDF for the OCR path.
- Do not start a general `queue:work`. Use only `queue:work question_papers --queue=question-papers`.
- Do not re-run Job 1 of the shared database, and leave queued jobs 14 and 15 there untouched.
- Record every command and its output in section 9.

## 1. Prerequisites (each must be answered before step 2)

| # | Item | Evidence to record |
|---|---|---|
| 1.1 | Staging host identified: app path, PHP binary, run user, process manager | the filled "Host discovery" table in `QUESTION_PAPER_DEPLOYMENT_RUNBOOK.md` |
| 1.2 | Staging database exists, empty or a **copy** of the schema, never the live one | `SELECT DATABASE();` and host |
| 1.3 | Code deployed to staging: backend branch and frontend branch (commit hashes) | `git rev-parse HEAD` on both |
| 1.4 | `.env` on staging: `APP_ENV=staging`, `QUEUE_CONNECTION=database`, own DB, own `DO_SPACES_*` | `grep` of those keys with secrets masked |
| 1.5 | Staging frontend (Vercel preview or equivalent) points at the staging backend | the API base URL it uses |
| 1.6 | A staging school (tenant) with standards, subjects (including two same-named subjects, one mapped to the standard through `sub_std_map`), and chapters | counts from `standard`, `subject`, `sub_std_map`, `chapter_master` |
| 1.7 | A staging user with an admin/staff profile | user id, profile |

## 2. Migrations (three files, by path, in this order)

```
php artisan migrate --path=database/migrations/2026_10_28_100000_create_question_paper_jobs_tables.php --force
php artisan migrate --path=database/migrations/2026_10_28_100100_create_user_notifications_table.php --force
php artisan migrate --path=database/migrations/2026_10_28_100200_add_declared_type_to_document_master.php --force
```

Never a bare `migrate` (the shared schema has hundreds of unrecorded baseline files; staging may too).

Verify: `question_paper_jobs`, `question_paper_items`, `user_notifications` exist and are empty;
`document_master.declared_type` exists and is nullable; three rows recorded in `migrations`.
Pre-existing tables needed by the feature must exist too: `document_master`, `document_history`, `standard`, `subject`,
`sub_std_map`, `chapter_master`, `lms_question_master`, `answer_master`, `jobs`, `failed_jobs`.

## 3. Platform checks (before any upload)

| Check | Pass condition |
|---|---|
| AI provider | AI Providers screen shows a working provider for `document_intelligence` for the staging school |
| Spaces | `Storage::disk('digitalocean')->put/get/delete` of a 1-byte file under a `qp-staging-check/` prefix succeeds, then is deleted |
| Worker | process manager shows `question-papers-worker` RUNNING; its command line contains `queue:work question_papers --queue=question-papers --timeout=900` and it runs from the staging app path with the staging `.env` |
| Scheduler | cron entry present; `artisan schedule:list` shows `idms:dispatch-question-papers` every minute; after 2 minutes the cron log shows a run |
| Not `sync` | `config('queue.default')` is not `sync` |
| Queue isolation | the worker's queue is `question-papers`; the `jobs` table on staging contains nothing from other features, or they are documented and untouched |

## 4. The end-to-end run

| # | Action | Expected | Evidence |
|---|---|---|---|
| 4.1 | Sign in to staging as the staff user, open `/documents_new`, Upload documents, choose the text PDF | the type picker shows four options; Upload stays disabled until one is chosen | screenshot |
| 4.2 | Choose **Question paper** and upload (select standard/subject/chapter in the Question papers tab later, not here) | the dialog says "Processing in background" within seconds; no document-pipeline polling | network log |
| 4.3 | Query: `SELECT id,status,stage,attempts,last_dispatched_at FROM question_paper_jobs` | one row, `queued` then `processing` **without any manual command** | rows + timestamps |
| 4.4 | Note the time, then **close the browser completely** | - | time |
| 4.5 | After the expected duration, query the job again | `completed` or `completed_with_warnings`, `finished_at` set, counters equal the item rows | rows |
| 4.6 | Reopen the app | bell shows exactly one unread notice; the Question papers tab shows the same job; progress survived | screenshot |
| 4.7 | Verify the extracted questions against the PDF, line by line | text, options, formats, marks match; no invented answer key or chapter | table in section 9 |
| 4.8 | Ambiguity: when the paper's subject is the same-named pair | resolver picks only the subject mapped to the standard, else offers candidates; nothing guessed | job `metadata_notes` |
| 4.9 | Choose standard, subject, chapter in the detail view and **Retry** | the job resumes **without calling the AI again** (`parsed_chunks` unchanged), saves held questions | rows, AI usage |
| 4.10 | Review: select a complete question, choose the correct option for an unkeyed MCQ, **Activate** | `lms_question_master.status` 1, `chapter_id` set, `answer_master` rows with exactly one `correct_answer=1` | rows |
| 4.11 | Read back: `SELECT ... FROM lms_question_master WHERE answer LIKE '%<job uuid>%'` and `answer_master` | counts equal the job counters; envelope `source.job` = the job uuid | rows |
| 4.12 | Click the notification | opens `/documents_new?tab=question-papers&job=<uuid>`; reload: badge cleared (`user_notifications.read_at` set) | screenshot + row |

## 5. Idempotency and failure drills (staging data only)

| # | Drill | Pass condition |
|---|---|---|
| 5.1 | Re-upload the same PDF | no new question rows; items marked `duplicate`; one new notification for the new job |
| 5.2 | Retry the finished job | same question count; no extra `answer_master` rows |
| 5.3 | Stop the worker mid-job (`supervisorctl stop` / `systemctl stop`), then set `lease_expires_at` into the past on the staging row, start the worker | within ~1-2 min the scheduler re-dispatches; the job finishes with no duplicate questions |
| 5.4 | Break the AI key for the school, upload a paper | status `retrying` then `failed` after 4 attempts; `last_error` readable; one failure notification; restore the key and press Retry |
| 5.5 | Upload a non-PDF renamed `.pdf` and a truncated PDF | refused at upload with a clear message; no row created |
| 5.6 | Scanned PDF | `used_ocr=1`, questions extracted, or a clear "No text could be read" failure |
| 5.7 | Second staff user in the same school, and an admin of another school | each gets 404 for the job; the same-school admin sees it |
| 5.8 | Timing | record `started_at`/`finished_at` per page count to replace the runbook's estimates with measured values |

## 6. Rollback

All drills touch only staging. To reset: delete the staging rows created by the test job (by `job` uuid in the envelope)
with a reviewed, targeted statement; never `TRUNCATE`/`DROP TABLE` (the application blocks them anyway). The three
migrations roll back with `php artisan migrate:rollback --path=... --force` in reverse order **on staging only**.

## 7. What a pass does NOT prove

- Production hosting differences (different PHP/OS/process manager) beyond the host discovery done here.
- AI quality on other papers, languages, or long/scanned books.
- Load: one worker, one paper at a time.

## 8. Approval gates before production

1. Section 4 and 5 all green, evidence attached.
2. Written approval to apply the migrations (already applied once to the shared DB) and to start the production worker.
3. A decision on the unrelated queued jobs 14 and 15 and the two `failed_jobs` rows.
4. A decision on Job 1: re-run (writes real inactive questions) or discard.

## 9. Result log

| Date | Environment | Step | Result | Evidence |
|---|---|---|---|---|
| | | | | |
