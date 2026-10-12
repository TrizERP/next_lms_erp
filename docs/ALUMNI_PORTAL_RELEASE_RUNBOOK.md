# Alumni Portal - production release runbook

**Status: PREPARED, NOT EXECUTED.** Nothing in this document has been run against production. Every production command below is
for review; none runs until you approve it. Written 2026-10-12 for the person approving and the person executing the release.

What is being released: the alumni portal sign-in (accounts, tokens, sessions - see section 8 of
`ALUMNI_MODULE_IMPLEMENTATION_PLAN.md`), the staff "Portal access" tab, the portal pages, a scheduled purge, and the missing
Super Admin menu rows. **Nothing is switched on until `ALUMNI_JWT_SECRET` is set** - until then every portal endpoint answers 503.

---------------------------------------------------------------------------------------------------------------------------

## 1. Migration review: `2026_10_14_100000_create_alumni_auth_tables`

| Question | Finding |
|---|---|
| What does it do? | Creates 3 new tables: `alumni_accounts`, `alumni_auth_tokens`, `alumni_auth_sessions`. Touches **no existing table**. |
| Safe to re-run? | Yes: every create is guarded by `Schema::hasTable`. Verified: a second `up()` and a second `artisan migrate` both do nothing. |
| Locking / duration | Only the new tables are locked. 78-86 ms on staging; expect a few seconds on production because of network latency (the earlier alumni migrations took 1-3 s each). No impact on running requests. |
| Consistency with production | Tables are created `utf8mb4 / utf8mb4_unicode_ci` from the connection config - identical to the existing alumni tables. `email` is `varchar(190)` like `alumni.email`. Production tables use `ROW_FORMAT=Dynamic`, so the 768-byte composite unique index is well within limits. |
| Foreign keys | None - consistent with every other alumni table (0 foreign keys on production alumni tables). Integrity is enforced by unique indexes and by the code. |
| Indexes | Every query path is covered: accounts by `(institute, email)` and `(institute, alumni)` (unique); tokens by `token_hash` (unique) and `(account, purpose)`; sessions by primary key, `refresh_hash` (unique), `previous_refresh_hash` (reuse alarm) and `(account, revoked_at)`. |
| Data sensitivity | Passwords are stored only as Laravel hashes. One-time tokens and refresh tokens only as SHA-256 hashes. Verified by test and on MariaDB: no live token in any table or log. |
| `down()` | Forward-only on purpose (sign-in records are audit data). Rollback is manual - section 5. |
| Gap found and fixed | The tables would have grown forever. Added `php artisan alumni:run auth-purge`, scheduled daily 02:30: deletes one-time tokens used/expired >30 days ago and sessions revoked/expired >90 days ago (tested). |
| Ordering rule | Apply the migration **before** deploying the code that reads the tables (the staff "Portal access" tab and the purge task query them). Applying it early is harmless to the running version. |

Verdict: approved for production **subject to the checks below**.

## 2. Staging verification on a real MariaDB

- **Server:** a throw-away local MariaDB **10.4.32** (XAMPP binaries) on `127.0.0.1:3307`, with its own data folder, started with `--no-defaults`. **Production is MariaDB 10.11.9** (not Oracle MySQL), `sql_mode=NO_ENGINE_SUBSTITUTION`, server charset latin1 with per-table utf8mb4. Same family, 7 minor versions apart; the migration uses only basic column and index types. *Not verified:* behaviour on 10.11-specific features (none are used).
- **Production-equivalent structure:** `SHOW CREATE TABLE` (structure only, no rows) copied from production for 61 tables - all `alumni_*`, all `platform_*`, `tbluser`, `tbluserprofilemaster`, `tblmenumaster`, `tblgroupwise_rights`, `tblindividual_rights`, `school_setup`, `academic_year`, `standard`, `division`, `tblstudent*`, `class_teacher`, `migrations`, `custom_module_tables`, jobs tables - plus the names of the 835 migrations already applied, so `migrate --path` behaves as in production. For the Super Admin rehearsal only, the Alumni menu rows (17), the SUPER ADMIN/ADMIN/ACCOUNTANT profile rows (102) and their rights rows on those menus (1,647) were copied: configuration, no personal data. All reads from production were `SELECT`/`SHOW`. Staging was only ever written to on port 3307 (every script refuses to run otherwise).
- **Differences from production to keep in mind:** (1) 10.4 vs 10.11; (2) Windows server (`lower_case_table_names=1`; all alumni table names are lower-case already); (3) no real data volume - performance is not measured; (4) other developers applied 5 more migrations on production while this was prepared (835 on staging copy vs 830 when the alumni set was applied).

| Check (staging) | Result |
|---|---|
| `migrate --pretend` shows only the 3 `create table` + 9 index statements | yes (no `alter` of existing tables) |
| Migration applies | DONE in 86 ms; recorded once in `migrations` |
| Columns / engine / collation / indexes | 17 / 11 / 12 columns, InnoDB, `utf8mb4_unicode_ci`, all 9 indexes present |
| Idempotent (`up()` twice, `artisan migrate` twice) | no error, "Nothing to migrate" |
| **Real-database HTTP smoke** (real middleware, routes, throttles; `staging-smoke.php`) | **49 / 49 pass** - see list below |
| Backup -> restore into a scratch DB -> compare | rows 2/4/6 restored, `CHECKSUM TABLE` identical |
| Rollback (drop 3 tables + delete migration row) | tables and row gone; the other 33 alumni tables untouched |
| State after rollback with secret unset | portal login/register/forgot answer **503** (not 500); staff token 200, student token 403, no token 401 |
| Roll forward from the backup | rows restored, `migrate` re-records the migration |

The 49 smoke checks cover: staff and student tokens behave exactly as before (200 / 403 / 401 / 401); registration, email confirmation, sign-in, case-insensitive code and email; the emailed token appears in **no** table or log; replayed confirmation fails; alumni token refused by 4 staff endpoints and staff/student tokens refused by the portal; a staff-signed token marked as alumni is refused by name; alumni tokens carry no staff-style claims; staff verification opens features; refresh rotation and **reuse revokes the login**; lockout after 5 wrong passwords and its expiry; weak reset password does not burn the link, reset works once and signs every device out; staff invitation end to end (single-use link, still needs email confirmation); staff disable signs out at once, enable restores; self-service update changes only the caller and only allowed fields; event register/cancel under a row lock; privacy/consent save; per-email rate limit (429); MariaDB enforces unique email-per-institute and unique token hashes; every `alumni.auth` audit entry has an actor and no password appears in the audit trail; the purge runs.

*Defect found by this run and fixed:* audit entries for anonymous "register with an existing email" attempts had no actor; they now record "Visitor (not signed in)" (test added).

The staging tooling is kept in the repository so the rehearsal can be repeated: `database/manual/alumni_portal_stage_structure.php` (copies production STRUCTURE into a local staging database on 127.0.0.1:3307; read-only on production, refuses to write anywhere else) and `database/manual/alumni_portal_staging_smoke.php` (the 49-check real-database smoke; refuses any database but that staging one; needs synthetic `JWT_SECRET` / `ALUMNI_JWT_SECRET` values and a dead `MAIL_HOST`/`MAIL_PORT` so nothing can be sent). A local MariaDB for it can be started from XAMPP's binaries with `mysqld --no-defaults --datadir=<new folder> --port=3307 --bind-address=127.0.0.1`.

---------------------------------------------------------------------------------------------------------------------------

## 3. Release order

Everything before step 7 is invisible to users; step 7 is the on-switch.

| # | Step | Who | Reversible? |
|---|---|---|---|
| 0 | Read the pre-flight (section 8) and agree a window. Confirm nobody else is deploying or migrating. | you | n/a |
| 1 | **Back up** (section 4). | DBA | n/a |
| 2 | Apply migration `2026_10_14_100000_create_alumni_auth_tables` (3 new tables). | DevOps | yes (drop 3 empty tables) |
| 3 | Validate the tables (section 6, checks A). | DevOps | n/a |
| 4 | Apply migration `2026_10_14_100100_grant_super_admin_donations_audit_menu` (12 rows). Validate (checks B). | DevOps | yes (delete 12 rows) |
| 5 | Deploy the **backend** code. Portal routes exist but answer 503 (no secret yet). Staff "Portal access" tab starts working. | DevOps | yes |
| 6 | Set `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` (section 7); run the mail test (section 7) and an invitation/confirmation/reset on a staging copy. | DevOps | yes |
| 7 | Deploy the **frontend**, set `ALUMNI_PORTAL_URL` and `ALUMNI_JWT_SECRET`, clear/rebuild config cache, restart workers. **Portal is now live.** | DevOps | yes (unset the secret) |
| 8 | Post-release validation (checks C) and a watch period. | DevOps | n/a |

Do not skip the order: migrations (2, 4) before the code (5), the secret (7) last.

## 4. Backup plan

1. **Full dump by the DBA, before step 2** (read-only on the database; the dump contains personal data - store it encrypted, restrict access, delete per policy):
   ```
   mysqldump --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 -h <DB_HOST> -u <BACKUP_USER> -p vivek_erp | gzip > vivek_erp_pre_alumni_portal_<YYYYMMDD_HHMM>.sql.gz
   ```
   Record the file name, size and `sha256sum`. Test-restoring it elsewhere is the only proof it is usable.
2. **Targeted backup of what this release changes** (small, fast to restore - the migrations add no rows to existing tables, so only `migrations` and the rights table are relevant):
   ```
   mysqldump --single-transaction --no-create-info --skip-extended-insert -h <DB_HOST> -u <BACKUP_USER> -p vivek_erp migrations > migrations_pre_portal.sql
   mysql -h <DB_HOST> -u <USER> -p vivek_erp -N -e "SELECT COUNT(*), MAX(id) FROM tblgroupwise_rights" > rights_counts_pre_portal.txt
   ```
   Expected on 2026-10-12: 53,082 rights rows, highest id 283,141 (it may have moved if others changed rights since - what matters is that the number is recorded before and compared after).
3. Save the read-only Super Admin report from section 9 (BEFORE) with the date.

## 5. Rollback plan

| Stage reached | Rollback | Data lost |
|---|---|---|
| Before step 2 | nothing to do | none |
| After step 2 (tables exist, code not deployed) | none needed (empty, unused tables are harmless). To remove: see below | none |
| After step 4 (12 rights rows) | `DELETE FROM tblgroupwise_rights WHERE menu_id IN (926,929) AND profile_id IN (1375,3182,3183,3201,3567,3636) AND sub_institute_id = 0;` (matches exactly the 12 rows - there were **0** such rows before; confirm `ROW_COUNT() = 12`), then `DELETE FROM migrations WHERE migration='2026_10_14_100100_grant_super_admin_donations_audit_menu';` | none |
| After step 5 (code deployed, portal off) | redeploy the previous backend build; the staff Portal access tab simply errors until the tables are back. | none |
| After step 7 (portal live) | **First switch it off:** unset `ALUMNI_JWT_SECRET` (or set it empty) and clear the config cache - every portal endpoint then answers 503 and no token is accepted. Staff, student and parent sign-in are not involved. Then, only if you also want the tables gone: back them up, then drop them (below). | nothing if only switched off |

Removing the tables (only after the portal is off; take the three-table backup first):
```
mysqldump --single-transaction --skip-lock-tables -h <DB_HOST> -u <USER> -p vivek_erp alumni_accounts alumni_auth_tokens alumni_auth_sessions > alumni_auth_tables_backup_<YYYYMMDD_HHMM>.sql
DROP TABLE alumni_auth_sessions, alumni_auth_tokens, alumni_accounts;
DELETE FROM migrations WHERE migration = '2026_10_14_100000_create_alumni_auth_tables';
```
Restore (rehearsed): `mysql ... vivek_erp < alumni_auth_tables_backup_<...>.sql` then `php artisan migrate --path=database/migrations/2026_10_14_100000_create_alumni_auth_tables.php --force` (re-records the migration; creates nothing).
Disaster rollback (something unrelated to the portal broke): restore the full dump from step 1 - this loses everything written since.

## 6. Post-migration validation checklist

**A. After step 2/3 - the tables** (read-only):
- [ ] `php artisan migrate:status` lists `2026_10_14_100000_create_alumni_auth_tables` as Ran.
- [ ] `SHOW TABLES LIKE 'alumni\_acc%'` and `'alumni\_auth%'` return 3 tables in total.
- [ ] `SHOW INDEX FROM alumni_accounts` shows unique `(sub_institute_id,email)` and `(sub_institute_id,alumni_id)`; `alumni_auth_tokens` unique `token_hash`; `alumni_auth_sessions` unique `refresh_hash`.
- [ ] `SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_NAME LIKE 'alumni_a%'` is `utf8mb4_unicode_ci`.
- [ ] Row counts are 0 / 0 / 0.
- [ ] Existing alumni screens, staff login and a student login still work (open the app, sign in as a staff user and as a test student).

**B. After step 4 - Super Admin rights:**
- [ ] `php database/manual/alumni_super_admin_rights_report.php` shows **Total missing: 0** and the rights table has exactly **12** more rows than the pre-release count.
- [ ] `SELECT COUNT(*) FROM tblgroupwise_rights WHERE menu_id IN (926,929) AND profile_id IN (1375,3182,3183,3201,3567,3636)` = 12.
- [ ] A Super Admin user sees "Donations" and "Alumni Audit Log" under Alumni Management; an Admin-type, an Accountant and a Teacher see the same menu as before (no new entries for a Teacher).

**C. After step 7 - the portal** (use one synthetic test alumnus created and removed by staff with the institute's agreement; do not use a real person):
- [ ] `php database/manual/alumni_portal_preflight.php` prints READY.
- [ ] `POST /api/alumni-portal/login` with wrong details answers 401 with the generic message.
- [ ] Staff invite from the alumnus profile shows a link once; the invitation email arrives; open the link, set a password, confirm the email, sign in; "My details" shows only that person.
- [ ] Forgot password email arrives; the link works once; the old password stops working.
- [ ] A staff token against `/api/alumni-portal/me` -> 401; the alumni token against `/api/alumni/dashboard` -> 401.
- [ ] Staff "Disable access" ends the open session on its next request.
- [ ] `alumni:run auth-purge` runs without error (`php artisan alumni:run auth-purge`).
- [ ] Application log has no stack traces from `alumni-portal` routes; `platform_audit_log` has `alumni.auth` entries with an actor and no password/token text.
- [ ] Watch 24 h: the rate of 401/429 on `/api/alumni-portal/login`, and the number of `account_locked` / `refresh_token_reuse` audit entries.

## 7. Configuration (no secret is ever printed, logged or committed)

### `ALUMNI_JWT_SECRET`
- Random, **at least 32 characters, different from `JWT_SECRET`** (the portal answers 503 if it is missing, short or equal to the staff secret).
- Generate it **on the server, straight into the environment store, without displaying it**:
  ```
  printf 'ALUMNI_JWT_SECRET=%s\n' "$(php -r 'echo bin2hex(random_bytes(32));')" >> .env
  chmod 600 .env
  ```
  (The command substitution result is never echoed, so it does not enter the terminal log or shell history.) Prefer your secrets manager if you have one and inject it as a real environment variable.
- Never put it in the repository, a ticket, chat, a log line or a screenshot. `.env` must not be world-readable and must not be served by the web server.
- Check it is set **without seeing it:** `php database/manual/alumni_portal_preflight.php` (prints yes/no and the length only).
- **Rotation:** change the value, clear the config cache, restart workers. All alumni are signed out (their access tokens stop verifying) and sign in again; staff are unaffected. Rotate on suspected exposure and at least yearly.
- **If you use `php artisan config:cache`:** run it after setting the variable. The portal reads both secrets through `config('alumni_auth.*')` (not `env()` at runtime), so caching cannot silently disable the "alumni secret must differ from the staff secret" check - covered by a test.

### `ALUMNI_PORTAL_URL`
- The **public origin of the frontend** where `/alumni-portal` is served, `https://`, no trailing slash, no path - e.g. `https://erp.example-school.org`. It is placed in every emailed link, so a wrong value sends people to the wrong site. The pre-flight checks the format.
- Fallbacks if unset: the institute setting `alumni_portal_url`, then `APP_URL` (which is `http://127.0.0.1:8000/` on the machine this was prepared on - **not acceptable for production**).

### Mail (real transport required - do **not** use the `log` driver)
- `MAIL_MAILER=smtp` is already configured to `smtp.gmail.com:587` (TLS) with credentials present. **Verified:** the connection and login succeed (STARTTLS + AUTH, no message sent).
- **Required fix before release:** `MAIL_FROM_ADDRESS` is currently a placeholder on `example.com` and differs from the authenticated account. Gmail rewrites or rejects such a From, so invitations would show the wrong sender or land in spam. Set `MAIL_FROM_ADDRESS` to the authenticated account, or to an alias verified in that account, plus `MAIL_FROM_NAME`. Ideally use a domain you control with SPF/DKIM so invitations are trusted (Gmail's own sending limits also apply: fine for invitations, not for bulk campaigns).
- The `log` driver would write every confirmation and reset link into `storage/logs/laravel.log` in clear text: never use it in production. (The application records email as `skipped` under `log`/`array`, which is the safe behaviour.)
- **Send one real test message** (needs your approval and a recipient; it really sends): `php database/manual/alumni_portal_mail_test.php <recipient>`. Then exercise the three real flows (invitation, email confirmation, password reset) on a staging copy and confirm each email arrives, is not in spam, and its link opens the right site.
- **Not yet verified:** actual delivery/inbox placement of the three flows. That needs the recipient and the From fix above.

### Other settings seen
- The environment this work ran in is `APP_ENV=local`, `APP_DEBUG=true` **while pointing at the production database**. For production serving, `APP_ENV=production` and `APP_DEBUG=false` (debug pages print configuration). Confirm this on the real server; the pre-flight warns.

## 8. Pre-flight (read-only) - current result

`php database/manual/alumni_portal_preflight.php` on this machine against the production database, 2026-10-12: **NOT READY - 11 required checks failing**, all expected before release: no secret, no portal URL, From address is a placeholder, three tables missing, migration not recorded. Re-run it on the production server after steps 2-7; it must say READY.

## 9. Super Admin rights: before / after (12 rows)

**Why:** the six platform-level `SUPER ADMIN` profiles (`sub_institute_id = 0`) were skipped by the name-matched grants (which cover only the module's institute list), so they hold 14 of 16 screens. Their access is not affected (the role gate accepts `SUPER ADMIN`); only two menu entries are missing.

**BEFORE (production, read-only, 2026-10-12):**

| Profile id | Institute id | Module row | Screens held | Missing |
|---|---|---|---|---|
| 1375 | 0 | yes | 14/16 | donations, audit |
| 3182 | 0 | yes | 14/16 | donations, audit |
| 3183 | 0 | yes | 14/16 | donations, audit |
| 3201 | 0 | yes | 14/16 | donations, audit |
| 3567 | 0 | yes | 14/16 | donations, audit |
| 3636 | 0 | yes | 14/16 | donations, audit |

Rights table: 53,082 rows, highest id 283,141. Existing rows on Donations/Audit for these profiles: **0**.

**The 12 rows the migration inserts** (`php database/manual/alumni_super_admin_rights_report.php --plan` prints this against any database):

| Profile ids | menu_id | link | sub_institute_id | view | add | edit | delete |
|---|---|---|---|---|---|---|---|
| 1375, 3182, 3183, 3201, 3567, 3636 | 926 | alumni/donations | 0 | 1 | 1 | 1 | **0** |
| 1375, 3182, 3183, 3201, 3567, 3636 | 929 | alumni/audit | 0 | 1 | 0 | 0 | 0 |

Delete is withheld on Donations (nothing about a donation is ever deleted) and the Audit Log is view-only, the same as every other profile. These Super Admin profiles hold full rights (`1111`) on the other alumni screens, so say if you want Donations to match that instead. No existing row is changed or removed.

**AFTER (staging rehearsal, same 6 profiles and menu rows):** missing 0; rights rows 1,647 -> **1,659** (+12); highest id 283,140 -> 283,152; a checksum of every rights row *except* the two new menus was identical before and after; a second run added nothing.

**AFTER on production** is produced by running the report again after step 4; expected: Total missing 0, +12 rows.

## 10. Alumni tokens vs staff / student authentication

Design (why they cannot be confused): different signing secret, audience `alumni`, claim names (`sub`/`aid`/`tid`/`sid`, no `id`/`sub_institute_id`/`is_student`), a separate middleware (`alumni.auth`) that never builds the legacy session; the shared legacy hydrator also refuses any token marked as an alumni token even if signed with the staff secret. The portal refuses to run (503) if its secret is missing, short, or equal to the staff secret. JWT code inspected: the GenTux wrapper silently falls back to the staff secret and an env-chosen algorithm, so the portal uses the underlying `Firebase\JWT\JWT` directly with HS256 pinned.

Verified three ways: PHPUnit through the real HTTP stack (`AlumniAuthTest`, `AlumniStaffAuthUnaffectedTest`, `AlumniRouteSecurityTest`, `ApiGuardTest`); the real-MariaDB staging smoke; and the browser (an alumni token against `/api/alumni/list` through the relay -> 401; staff app still shows the staff login for a portal visitor). Existing staff / student behaviour (staff 200, student 403, no token 401, garbage 401, wrong signature 401, teacher role gate 403, a staff token carrying an unrelated `aud` still 200) is pinned by a permanent test and matches on MariaDB.

## 11. Regression results (final code, 2026-10-12)

| Suite | Result |
|---|---|
| Backend: 14 alumni test files (SQLite) | **201 tests, 2,191 assertions - all pass** |
| Backend: `tests/Feature/Security` (includes the every-route-is-guarded test) | **23 pass** |
| Real-database staging smoke (MariaDB) | **49 / 49 pass** |
| Staging rollback / restore / roll-forward rehearsals | all as expected (section 2) |
| Frontend `tsc` + `eslint` on `app/alumni`, `app/alumni-portal`, `StudentDashboard.tsx`, `ConditionalApp.tsx` | **clean - no findings** |
| Frontend unit tests (`npm test`) | **939 / 939 pass** |
| Browser (isolated SQLite stack, each suite on a fresh DB, one at a time) | **81 steps, all pass:** Phase B 24/24, C 14/14, D 12/12, E 9/9 (includes the recorder cannot verify rule), F 11/11, alumni portal 11/11. In the one combined run Phase B had 23/24: a check read a dropdown before its options had loaded (a load-related race, the PHP suites were running alongside). It then passed 24/24 on 4 of 4 isolated runs and the check now waits for the dropdown. |

## 12. Findings (all of them)

1. **Production is MariaDB 10.11.9, not MySQL.** Staging used MariaDB 10.4.32. Recorded as a difference, not a blocker.
2. **From address is a placeholder** (`example.com`) and does not match the SMTP account - must be fixed before release (section 7).
3. **`APP_ENV=local` / `APP_DEBUG=true` on a machine pointed at the production database.** Confirm the real production server's environment.
4. **`APP_URL` is `http://127.0.0.1:8000/`** here, so the fallback for emailed links is unusable; `ALUMNI_PORTAL_URL` must be set.
5. Config robustness: the staff secret is now read through config so `config:cache` cannot disable the "must differ" check (fixed, tested).
6. Token/session tables had no clean-up: scheduled `auth-purge` added (tested).
7. Anonymous registration-attempt audit entries had no actor: fixed (tested on SQLite and MariaDB).
8. Test-quality finding: `withHeaders()` leaked a token between requests in two test helpers, which could make "no token" assertions pass or fail for the wrong reason. Both helpers now pass headers per request and the suites still pass.
9. Concurrent changes: other developers applied 5 migrations to production while this was prepared. Re-run the pre-flight immediately before the window.
10. Unchanged and still open: online donation payments (deferred), SMS/WhatsApp/push providers (deferred), real inbox delivery of the three portal emails (needs the From fix and a recipient).

## 13. Exact production commands - FOR REVIEW, NOT EXECUTED

Run from the application root on the production server, in this order, only after approval. Use `--path` always (never a bare `migrate`: ~400 old migrations are still pending on this database and a bare run would try to re-create live tables).

```
# 0. pre-flight (read-only) - expect NOT READY until steps 2-7 are done
php database/manual/alumni_portal_preflight.php
php database/manual/alumni_super_admin_rights_report.php --plan      # confirms "Rows to insert: 12"

# 1. BACKUP - see section 4 (DBA)

# 2. migration: 3 new tables
php artisan migrate --path=database/migrations/2026_10_14_100000_create_alumni_auth_tables.php --pretend --force   # review the SQL
php artisan migrate --path=database/migrations/2026_10_14_100000_create_alumni_auth_tables.php --force

# 3. validate tables - section 6 A

# 4. migration: 12 Super Admin rights rows
php artisan migrate --path=database/migrations/2026_10_14_100100_grant_super_admin_donations_audit_menu.php --force
php database/manual/alumni_super_admin_rights_report.php             # expect: Total missing 0, rights rows +12

# 5. deploy backend code (your normal process), then:
php artisan route:clear 2>/dev/null; php artisan config:clear

# 6. mail: set MAIL_FROM_ADDRESS / MAIL_FROM_NAME in the environment, then (needs approval + recipient):
php database/manual/alumni_portal_mail_test.php <recipient>

# 7. go live: deploy frontend; set ALUMNI_PORTAL_URL; add ALUMNI_JWT_SECRET (section 7); then
php artisan config:clear && php artisan config:cache                  # if you use config caching
# restart php-fpm / queue workers per your process
php database/manual/alumni_portal_preflight.php                       # must print READY
php artisan alumni:run auth-purge                                      # first run of the new scheduled task

# 8. validation - section 6 C
```
Note: `php artisan route:list` is broken on this codebase and the console guard blocks any artisan command line containing the word "schema"; neither is used above.

**Rollback in one line (portal off):** unset `ALUMNI_JWT_SECRET`, `php artisan config:clear` (and re-cache if used).
