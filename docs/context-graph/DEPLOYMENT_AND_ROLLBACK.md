# Context Graph — Deployment and Rollback

## Current state: nothing is committed anywhere

Per standing instruction (ask before committing — see memory), every change this initiative made exists
only as **uncommitted working-tree edits**, verified immediately before writing this document:

| Repo | Working-tree state |
|---|---|
| `next_lms_erp` | Modified: `app/Services/PAL/Integration/PedagogySuggestedContentService.php`. New: `app/Services/PAL/Integration/PedagogySuggestedContentContextRecorder.php`, `docs/context-graph/*`. |
| `hp-enterprise-brain` | Modified: `src/api/intelligence.ts`, `src/components/signal/SignalChainView.tsx`. |
| `lms_k12` | Unchanged by this work — one **pre-existing** uncommitted change (`ContentCard.tsx`) predates this session and was left untouched. |
| `hpbrain_backend`, `g2gv0`, `hp_erp` | Unchanged. |

One database migration **was** run (user-approved) against the live remote dev database
(`vivek_erp@202.47.117.220`): `2026_09_29_100600_add_duration_and_knowledge_graph_to_ai_audit_logs.php`.
This is the only persistent, already-applied change; everything else is still sitting in a working tree.

## To deploy

1. **Review and commit** the two touched repos' diffs (`next_lms_erp`, `hp-enterprise-brain`) —
   not done automatically by this work, per standing instruction.
2. **K-12**: no further migration needed — the one required migration already ran against the dev
   database. If this code is deployed to a *different* database (e.g., production, or a different dev
   host), confirm that migration has also run there (`php artisan migrate:status`), since §6/§2.1 of
   `PHASE_0_AUDIT.md` already documents that this estate has a history of migrations existing in-repo but
   not applied to every environment.
3. **Enterprise Brain**: a plain frontend build (`npm run build`, i.e. `tsc -b && vite build`) is
   sufficient — no backend deploy needed, since no backend file changed.
4. **G2G**: nothing to deploy.

## To roll back

- **K-12 code**: `git checkout -- app/Services/PAL/Integration/PedagogySuggestedContentService.php`
  and delete `PedagogySuggestedContentContextRecorder.php`. The service returns to its pre-existing
  behavior exactly (the new code is purely additive, called only at the end of `getSuggestions()`).
- **K-12 migration**: has a clean, already-reviewed `down()` (`dropColumn(['duration_ms',
  'knowledge_graph_used'])`). Rolling it back would **reintroduce the silent-audit-failure defect** for
  every existing caller of `AiAuditLogger::record()`, not just this pilot — roll back only if there is a
  specific reason to, and be aware of that blast radius first.
- **Enterprise Brain**: `git checkout -- src/api/intelligence.ts
  src/components/signal/SignalChainView.tsx`. No migration, no backend change, so rollback is a pure
  frontend revert with no data-model implication.
- **G2G**: nothing to roll back.

## Operational notes carried over from testing

- One of the K-12 live-verification steps and one of the EB live-verification steps wrote real rows to
  shared tables (`ai_audit_logs` in K-12; `hpbrain_ai_executions` in EB, via a real billed AI call). The
  K-12 test rows were explicitly deleted after verification (sentinel `subject_id` values in the
  999999990s range — confirmed empty afterward). The EB `hpbrain_ai_executions` row was **not** deleted:
  it is a legitimate usage/billing record of exactly the kind this feature is meant to produce once live
  — treating it as test pollution would misrepresent what the AI Gateway's execution ledger is for.
- No scheduled job, cron entry, or Task Scheduler registration was added, removed, or modified by this
  work in any repo.
