# Credential remediation — and the rotation that still has to happen

**Delivers:** Tracker "Decisions & Risk Log" #10 *(Open — urgent)*
**Date:** 2026-09-08 · **Phase:** B3

---

> ## ⚠️ READ THIS FIRST
>
> **Nothing in this document revokes anything.** Every secret listed below is in git history. Removing a
> literal from a file changes what is in `HEAD`; it does not change what an attacker with a clone already
> has. **Only rotation at the issuing account fixes the exposure**, and that is an ops action nobody on the
> engineering side can perform.
>
> **There is also one deploy dependency.** The OpenAI key is now read from the environment. The server must
> have `OPENAI_API_KEY` set **before this merges**, or three AI features return a 500.

---

## What was found, and what was done

| # | Secret | Where it was | Action taken | Still needs rotation? |
|---|---|---|---|---|
| 1 | **Laravel `APP_KEY`** — *and it is the live one* | `.env.example:3`, committed | Blanked in the template | **YES — highest severity** |
| 2 | **OpenAI API key** (live) + a second older key | `AJAXController.php:2552`, `syllabusController.php:306`, `lms_lessonplanController.php:647` | Replaced with `env('OPENAI_API_KEY')` + an explicit guard | **YES** |
| 3 | **MySQL password + username** for `triz_lms` | `config/database.php` `information_schema` connection | `env()` with the current value as fallback | **YES** |
| 4 | **MySQL password** (same value), in a dead array | `apiController.php:722-733` | **Deleted** — the variable was assigned and never read | covered by #3 |
| 5 | **CCAvenue `working_key`**, **Axis `encryption_key`** | 8 commented-out lines in `online_fees_collect_controller.php` | **Deleted** | **YES** |
| 6 | Twilio `messagingServiceSid` / `contentSid` | `TestFunction.php:96,103,110,145,168,176` | **Left as-is** — see below | No |
| 7 | **CCAvenue `access_code`** — 1 live + 4 commented *(missed by the first sweep; found by audit)* | `online_fees_collect_controller.php:424` live, `:202,312,828,907` commented | Commented ones **deleted**; the live one moved to `env('CCAVENUE_ACCESS_CODE', …)` with a fallback so no deploy can break | **YES** |

### Why #1 is the most severe, despite being the least obvious

`.env.example` is a tracked template that normally holds placeholders. This one shipped a real
`base64:` key — and it is **byte-identical to the live `APP_KEY` in `.env`** (verified by comparing both
values; 51 chars, same string).

In Laravel, `APP_KEY` is not a config detail. It signs and encrypts:
- every session cookie,
- every `encrypt()` / `Crypt::` value, including anything stored encrypted in the database,
- signed URLs.

Anyone with a clone of this repository can forge a session cookie for any user and decrypt any encrypted
column. That outranks the API keys.

**Rotating it is not a one-liner.** `php artisan key:generate` invalidates every existing session (all users
logged out) and makes previously-encrypted column data unreadable. Before rotating, someone must check
whether any column is stored via `encrypt()`/`Crypt::` — if so, it needs a decrypt-with-old-key,
re-encrypt-with-new-key migration. **Do not just run `key:generate` on production.**

### Why #6 was left alone

A Twilio `contentSid` / `messagingServiceSid` is an **identifier**, not a credential — it cannot authenticate
a request on its own. The actual secret in that file, `TWILIO_AUTH_TOKEN`, is already read via `env()`
correctly (`TestFunction.php:112,129,141`). Removing the SIDs would be tidying, not remediation, and it would
have meant editing live code in a command for no security gain. Flagged, not changed.

*(An earlier note claimed this file held a hardcoded Twilio auth token. It does not — that was wrong.)*

## The rotation checklist — for whoever owns each account

Ordered by severity. Each item is an ops action.

- [ ] **1. Laravel `APP_KEY`** — read the caveat above first. Audit for `encrypt()`/`Crypt::` usage, plan the
      re-encryption, then `php artisan key:generate` and redeploy. Expect every user to be logged out.
- [ ] **2. OpenAI** — revoke both keys at platform.openai.com (the live one and the older one that sat in the
      trailing comment). Issue a new key. **Set `OPENAI_API_KEY` on the server before merging this branch.**
- [ ] **3. MySQL user `dev_db`** — change the password. Then set `LMS_INSPECT_DB_USERNAME` /
      `LMS_INSPECT_DB_PASSWORD` (and optionally `LMS_INSPECT_DB_HOST` / `LMS_INSPECT_DB_DATABASE`) in the
      environment, and delete the fallbacks from `config/database.php`. Note the same credential also appears
      in `.env` as `DB_PASSWORD` for the primary connection — rotating affects both.
- [ ] **4. CCAvenue merchant working key** — rotate with CCAvenue. The live path already reads it from the
      database (`get_map_bank_detail->working_code`), so the app needs no code change; the stored value must
      be updated.
- [ ] **5. Axis encryption / checksum keys** — same: rotate with the bank, update the stored value.
- [ ] **6. CCAvenue `access_code`** — rotate with CCAvenue, then set `CCAVENUE_ACCESS_CODE` in the environment
      and delete the fallback. *(An access code is a merchant identifier rather than a secret on its own — the
      paired `working_key` is the real secret and is already read from the database — but it should not sit in
      a tracked file either.)*

**Consider the history itself.** These values are recoverable from any clone. Rewriting history
(`git filter-repo` / BFG) is the only way to remove them, and it rewrites every commit hash — a coordinated
action across everyone with a clone. For most teams, rotating and accepting the historical exposure is the
pragmatic choice. That is a decision for whoever owns the repo, not one to make silently.

## Verification

```bash
# No API-key literal survives in any tracked PHP file:
git grep -n "'sk-[A-Za-z0-9_-]\{20,\}'" -- '*.php'      # → no output

# No commented-out gateway credentials survive:
grep -nE "^\s*//\s*\\\$(working_key|encryption_key|checksum_key)\s*=" \
  app/Http/Controllers/fees/online_fees/online_fees_collect_controller.php   # → no output

# The template no longer ships a real key:
grep -n '^APP_KEY' .env.example                          # → APP_KEY=
```

Behaviour, confirmed before and after:

- `env('OPENAI_API_KEY')` was already resolving to the **byte-identical** value the literal held (sha256 of
  both compared), so the swap is a runtime no-op *on this machine*. It is **not** a no-op on a server where
  the variable is unset — hence the explicit guard rather than a silent failure.
- The `information_schema` connection still resolves to the same host/database/username/password through the
  `env(..., fallback)` form. It is genuinely live — `DB::connection("information_schema")` at
  `apiController.php:324` and `:735` — which is why it was made fallback-safe rather than deleted.
  *(A first pass nearly deleted it: a `grep` for `information_schema'` with a single quote missed both call
  sites, which use double quotes. Worth remembering.)*
- All live `$working_key` / `$encryption_key` assignments in the fees controller are untouched; only the
  8 commented lines were removed (net −8/+6, the +6 being an explanatory note).

## Scope note

`.env` itself is **not** tracked (`git ls-files --error-unmatch .env` fails), so no change was needed there —
and the local `.env` already contained a working `OPENAI_API_KEY`. Note its line is written as
`OPENAI_API_KEY =` with a space before the `=`; phpdotenv tolerates this, but it is why a naive
`grep '^OPENAI_API_KEY='` reports the key as absent. Worth normalising at some point.
