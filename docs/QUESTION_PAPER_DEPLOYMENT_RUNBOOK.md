# Question Paper processing: deployment runbook

Status when written (2026-10-28): the code is complete and tested; **nothing below has been done on a
server by this change.** Treat every step as pending until you have run its check.

## Why uploads stayed "Waiting to start" (root cause)

An upload does two things in the request: it stores the PDF and creates the job row, then it puts a message in the
`jobs` table. **Nothing in the web request runs the extraction** (it must not: it can take minutes). A separate,
long-running `queue:work` process has to read the `jobs` table. `php artisan serve` is only an HTTP server, so with only
`serve` running the message is stored and never read; the screen correctly says "Waiting to start". The scheduler does not
help either: `idms:dispatch-question-papers` only re-queues, it never extracts. Starting a worker is therefore not optional
and not per upload: it is one always-on process.

**Code versus services (what is and is not already done).** The code and configuration are complete and tested: the job goes
to connection `question_papers`, queue `question-papers`, timeout 900 s, `retry_after` 1000 s, and the worker command that
consumes exactly that is fixed. What the repository cannot do is *run* a process. Something on the machine has to start the
worker, and on a developer machine nothing did, because the only thing started was `php artisan serve`. That is a service to
start locally (below) or install on the server (Supervisor/systemd), not a code defect.

## Local development (Windows): one command

Any of these starts the HTTP server **and** the worker, so there is nothing to remember per upload:

```powershell
.\serve-fast.ps1                          # the project's local server (opcache on) + the question-papers worker; -NoWorker to opt out
.\dev-question-papers.ps1                 # `artisan serve` + the question-papers worker + recovery dispatcher
composer run dev:question-papers          # the same as the line above, from composer
```

`php artisan serve` on its own never starts a worker. If you prefer to keep starting the server yourself, start only the worker
with `.\dev-question-papers.ps1 -NoServer`. Stopping the server (Ctrl+C, or closing it) stops the worker that `serve-fast.ps1`
started; a force-closed window can leave it behind, and `.\dev-question-papers.ps1 -Stop` removes it. A worker that is already
running for this checkout is reused, never doubled, so restarting is safe.

```powershell
.\dev-question-papers.ps1            # server on :8000 + question-papers worker + 60 s recovery dispatcher
.\dev-question-papers.ps1 -NoServer  # you already run serve / serve-fast.ps1: start only the worker
.\dev-question-papers.ps1 -DryRun    # show what would start, start nothing
.\dev-question-papers.ps1 -Stop      # stop a copy running elsewhere (Ctrl+C also stops everything)
```

It runs only `queue:work question_papers --queue=question-papers ...` (restarted if it exits or crashes), the server, and a
60-second loop of `idms:dispatch-question-papers`. It never starts a worker for the `default` queue and never starts the
full scheduler (`schedule:work` would run every unrelated task against whatever database `.env` names). One supervisor per
checkout (PID file `storage\framework\dev-question-papers.json`); a second start is refused; an already-running worker
for this checkout is reused, never doubled. Logs: `storage\logs\dev-*.log`.

The Document Library's Start Worker control uses that watchdog on Windows (`dev-question-papers.ps1 -NoServer`) and a
fixed-argument POSIX watchdog for local Unix development. Both restart a crashed Question Paper worker with bounded
backoff and honor the worker stop flag, so Stop does not cause an automatic restart. The application account needs write
access only to the configured worker state and log directories; it is not granted shell or sudo access. Linux production
should use the Supervisor or systemd driver below.

**Database warning:** the worker uses the database in `.env`. If that is the shared database, a queued paper is really
processed and writes rows there. Point `.env` (or the shell's `DB_*`) at a disposable database first unless that is intended.

## What has to be running

| Piece | Why | Config in repo |
|---|---|---|
| Queue worker for `question_papers` | extracts and saves questions after upload | `deploy/question-papers/supervisor-question-papers.conf` or `question-papers-worker.service` |
| Scheduler (`schedule:run` every minute) | runs `idms:dispatch-question-papers`, which re-dispatches lost or stalled jobs (bounded, enqueue only) | cron line below; entry is in `app/Console/Kernel.php` |
| `QUEUE_CONNECTION` not `sync` | with `sync` nothing is dispatched (the feature refuses, so a request never runs a 10-minute extraction) | `.env` |
| `jobs` and `failed_jobs` tables | database queue | already present on the shared DB |
| AI provider for module `document_intelligence` | the parser | AI Providers screen |
| OCR (Tesseract or the Gemini vision provider) | scanned PDFs only | existing IDMS config |
| Spaces disk (`IDMS_DISK`) credentials | the worker reads the stored PDF | existing IDMS config |

The Vercel frontend is only a frontend. Nothing here runs on Vercel.

## Why a separate queue connection

`question_papers` (config/queue.php) uses the same `jobs` table but queue name `question-papers` and
`retry_after = 1000`. Reasons:

1. **It cannot run unrelated jobs.** `default` currently holds other work (`GenerateCoursePresentationJob`, and
   `failed_jobs` has rows). A worker started with `--queue=question-papers` ignores them. Do not start a
   plain `queue:work` against the shared database until someone has decided what should happen to those.
2. **No double delivery.** The default `retry_after` is 90 s; an extraction can run up to 900 s. With 90 s the queue
   would hand a running job to a second worker. The lease would stop the second one, but it is wasted work and noise.

Worker flags: `--timeout=900` (equals `QuestionPaperExtractionJob::$timeout`), `--tries=6` (headroom; the real retry
budget is 4 attempts, counted in `question_paper_jobs.attempts`), `--max-time=3600` (restarts hourly to free memory).

## Host discovery (do this first; nothing below may be assumed)

The repository contains no web-server config, deploy script or CI definition, and the `.env` on the development
machine (`APP_ENV=local`, `APP_URL=http://127.0.0.1:8000`) points at the shared remote database. So the real backend
host's application path, PHP binary, process user and process manager are **unknown to this change**. On the staging
host, record the answers to each of these before editing any template:

| Value | How to find it | Used for |
|---|---|---|
| Application path (`__APP_PATH__`) | where `artisan` lives and the web server's document root points (`../public`) | `directory`, cron, `command` |
| PHP binary (`__PHP_BIN__`) | `ps aux | grep -E "php-fpm|apache2|httpd"` then `php-fpm -v` / the binary in the service unit; `php -v` must show 8.2+ | worker, cron |
| Process user (`__RUN_USER__`) | `ps -o user= -C php-fpm` or the owner of `storage/` (`ls -ld storage`) | worker, cron |
| Process manager | `systemctl list-units | grep -iE "supervisor|laravel|queue"`, `supervisorctl status`, Forge/Ploi/Docker dashboards | which template applies |
| Active `.env` | the file at `__APP_PATH__/.env` that the web app really loads | worker must use the same one |
| `QUEUE_CONNECTION` | `grep ^QUEUE_CONNECTION .env`; must not be `sync` | dispatch |
| Queue name / timeouts | already fixed in code: connection `question_papers`, queue `question-papers`, job timeout 900 s, `retry_after` 1000 s, lease 1200 s | worker flags |
| Spaces | `IDMS_DISK` (default `digitalocean`), `DO_SPACES_*`; staging must use its **own** bucket or prefix | worker reads the PDF |
| AI provider | AI Providers screen for module `document_intelligence`, for the staging school | parser |

If the host is a Windows server, a container platform, or a managed PaaS, Supervisor/systemd do not apply: run the same
command line under that platform's service manager (NSSM / Windows service, a dedicated worker container, or a worker
dyno) and keep the same flags.

Values already settled by the code and independent of the host: connection `question_papers`, queue `question-papers`,
`--timeout=900`, `--tries=6` (real budget: 4 attempts, backoff 60/300/900 s), `--sleep=3`, `--max-time=3600`,
`retry_after` 1000, lease 1200.

## Scheduler

**Is it required?** Not for normal processing. An upload puts its job on the queue directly, and the worker takes it from
there, so with a worker running and no scheduler everything works. The scheduler (`idms:dispatch-question-papers`, every
minute) is the **recovery** pass: it re-queues a paper whose queue message was lost, or whose worker died and left an expired
lease, or that was uploaded while the queue was unreachable. Without it those papers stay "Queued" until someone retries. It
only enqueues (bounded, 10 per run); it never extracts. It is the only scheduled entry this feature adds, and nothing
else's schedule was changed. In development the launcher's own 60-second loop does this job, so no scheduler is needed locally.

```
* * * * * cd __APP_PATH__ && __PHP_BIN__ artisan schedule:run >> /dev/null 2>&1
```

Must run as `__RUN_USER__`, from the same directory, with the same `.env` as the worker. Confirm with
`__PHP_BIN__ artisan schedule:list` that `idms:dispatch-question-papers` shows `* * * * *`.

If the host has no cron, `deploy/question-papers/supervisor-scheduler.conf` runs `schedule:work` persistently instead. Use
**one** of cron or that file. (Both would only double the dispatch attempts, which the lease and the 5-minute
`withoutOverlapping` mutex make harmless, but there is no reason to run both.)

## Production persistence and exact commands (fill the placeholders from "Host discovery" first)

Starts at boot, restarts on crash, logs:

| Need | Supervisor | systemd |
|---|---|---|
| Starts when the server boots | `systemctl enable supervisor` (the daemon), program has `autostart=true` | `systemctl enable --now question-papers-worker` |
| Restarts on crash | `autorestart=true`, `startsecs=5` | `Restart=always`, `RestartSec=5` |
| Survives deploys | `__PHP_BIN__ artisan queue:restart` after each deploy (finishes the current job, then reloads code) | same |
| Logs | `/var/log/supervisor/question-papers-worker.log` (20 MB x 5) | `journalctl -u question-papers-worker` |
| Only this queue | `--queue=question-papers` on connection `question_papers` | same |

Install and verify (Supervisor):

```
sudo cp deploy/question-papers/supervisor-question-papers.conf /etc/supervisor/conf.d/   # after filling placeholders
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status question-papers-worker:*          # expect RUNNING
ps -o user,args -C php | grep "queue:work question_papers"  # expect --queue=question-papers, the right user and path
crontab -u __RUN_USER__ -l | grep schedule:run              # expect the every-minute line (or the scheduler program)
sudo -u __RUN_USER__ __PHP_BIN__ __APP_PATH__/artisan schedule:list | grep idms:dispatch   # expect * * * * *
sudo systemctl is-enabled supervisor                         # expect enabled (boot persistence)
```

Install and verify (systemd): `sudo cp deploy/question-papers/question-papers-worker.service /etc/systemd/system/`,
fill the placeholders, `sudo systemctl daemon-reload && sudo systemctl enable --now question-papers-worker`,
`systemctl status question-papers-worker`, `systemctl is-enabled question-papers-worker`.

End-to-end proof on staging (not done yet): upload a small PDF, then `SELECT status,stage FROM question_paper_jobs ORDER BY id DESC LIMIT 1`
must move from `queued` to `processing` within seconds with nobody running a command; then reboot the host and upload
again: it must work without anyone starting anything.

## Same environment checklist (worker, scheduler, web)

- Same `.env` (`DB_*`, `QUEUE_CONNECTION=database`, `IDMS_*`, Spaces keys). A worker reading a different `.env`
  will poll a different database and appear idle.
- After editing `.env` or deploying: `php artisan queue:restart` (graceful) and, with Supervisor, nothing else.
- Time zone/clock: leases and dispatch windows use the app clock; keep the servers on NTP.

## Behaviour to rely on (all covered by tests)

- Upload stores the PDF and creates the job row **before** it answers; the browser never waits for extraction.
- An upload may include a future extraction time. The scheduler leaves it queued until that time, then dispatches it to the same worker; the browser does not need to remain open.
- One worker per job (database lease, 20 min, renewed on every progress write). A killed worker's job is picked up
  by the scheduler after the lease expires and resumes from saved progress (parsed chunks, saved questions).
- Retries: 4 attempts, waits of 60 s, 300 s, 900 s, then `failed` with `retryable` kept so the owner can retry.
- Duplicate questions are matched by normalized question text only inside the resolved `chapter_id` for the same school.
  Other chapters never participate. A match is marked as a skipped duplicate before question or answer rows are written;
  remaining questions continue normally. The UI reports extracted, stored, duplicate, and failed question counts.
- Stable `(job_id, source_key)` item identities make extraction retries idempotent while retaining repeated occurrences
  for accurate duplicate counts.
- A paper never reports **completed** while a question is unwritten.

## Apply / verify, in order (staging first)

1. Migrations (already applied once to the shared DB on 2026-10-28; on any other database apply **by path**, never a bare
   `migrate`, because the shared DB has many unrecorded baseline files):
   ```
   php artisan migrate --path=database/migrations/2026_10_28_100000_create_question_paper_jobs_tables.php --force
   php artisan migrate --path=database/migrations/2026_10_28_100100_create_user_notifications_table.php --force
   php artisan migrate --path=database/migrations/2026_10_28_100200_add_declared_type_to_document_master.php --force
   php artisan migrate --path=database/migrations/2026_10_30_140000_add_scheduled_at_to_question_paper_jobs.php --force
   php artisan migrate --path=database/migrations/2026_10_30_160000_scope_question_paper_items_to_extraction_occurrences.php --force
   ```
2. Install the worker (Supervisor **or** systemd) and confirm it is alive:
   `supervisorctl status question-papers-worker:*` / `systemctl status question-papers-worker`.
3. Add the cron line; confirm with `php artisan schedule:list` that `idms:dispatch-question-papers` shows every minute.
4. Confirm the environment: `php artisan tinker --execute="echo config('queue.default');"` is not `sync`.
5. Upload a small non-sensitive paper. Within a few seconds the Question papers tab must move from "Waiting to start" to
   "Processing" **without any manual command**. Close the browser; reopen; the job and counts are still there.
6. Upload a second paper with a future schedule. Close the browser before that time; after the next scheduler tick the worker
   must start it automatically and the status must progress without opening the application.
7. Stop the worker mid-job (`supervisorctl stop ...`), wait for the lease to expire (20 min) or set `lease_expires_at` in the
   past on a **staging** row, start it again: the job must finish with no duplicate questions.

## Known gaps

- The hosting platform of the production backend could not be inspected from the development machine (no server access, and
  nothing in the repository identifies it). The Supervisor and systemd files therefore contain `__PLACEHOLDERS__` instead of
  guessed values; see "Host discovery" above. They are not installable until those are filled in.
- The original migrations were already applied to the shared database; apply the scheduled-time and extraction-occurrence migrations to each target database before deploying these changes. Staging must use its own database.
- Job id 16 in the shared `jobs` table is an old `QuestionPaperExtractionJob` on the `default` queue from before this
  connection existed. It belongs to a finished job, so it would exit immediately, but it only runs if a `default` worker is started.
