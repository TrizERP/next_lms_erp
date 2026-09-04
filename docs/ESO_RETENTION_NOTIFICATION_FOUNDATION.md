# ESO retention reminders — the existing mechanism, and what proactive delivery would require

Scope note: this document is research and a plan. **No notification system was built.** The only
thing implemented alongside it is the in-app retention *recap* (see "What is already done" below).

---

## 1. What is already done

`EsoPalRenderer::retentionSummaryInstruction()` existed but had **zero callers** —
`EsoPolicyService::retrievalDueAction()` returned a hard-coded `'llm_instruction' => null`, so no
recap could ever reach a student. That is now wired:

- `retrievalDueAction()` calls `retentionRecap()`, which sources material from
  `EsoLearningContentResolver` first (the content model's own variant body) and falls back to
  `ConceptRelevanceResolver` (the concept's real-world hook, else its definition).
- **No material → no recap.** `retentionSummaryInstruction()` returns `null` rather than asking Pal
  to invent a refresher from a node label. Inventing one before a *memory test* would corrupt the
  very thing D5 measures.
- `retentionSummaryFallback()` supplies a student-facing wording for when Pal cannot render, so the
  engine-facing instruction ("The student mastered…") is never shown verbatim.
- The retention ladder `[2, 7, 30, 60, 180]` and reset-on-failure are untouched, pinned by
  `test_the_recap_does_not_change_the_retention_ladder_or_reset_on_failure`.

**This is still pull-based.** The recap appears when the student happens to open the concept.
Nothing tells them to come back.

---

## 2. The cleanest existing mechanism

`pal_gamification_notifications`, via `App\Models\PAL\Gamification\GamificationNotification`.

It is the right host, and nothing else in the estate comes close:

| Property | Detail |
|---|---|
| Table | `pal_gamification_notifications` — `learner_id`, `type`, `level`, `title`, `body`, `context` (JSON), `read_at` |
| Indexes | `(learner_id, read_at)`, `(learner_id, created_at)` — already shaped for "unread for this learner" |
| Model scope | `GamificationNotification::unread()` |
| Existing writers | `BadgeService`, `PersonalBestService`, `CareerQuestService`, `TeamChallengeService` |
| Existing readers | `GamificationService::overview()` (`unread_notifications`), `SessionSummaryService` |
| HTTP API | `GET api/pal/new/gamification/notifications`, `POST api/pal/new/gamification/notifications/read` |
| Frontend | Already consumed by `d:/lms_k12/app/pal/new/data/gamification.ts` |

ESO already integrates with this subsystem — `EsoPolicyService::awardGamification()` calls
`BadgeService::evaluate()` on D4/D5 outcomes — so a retention reminder is a new `type` on an
existing rail, not a new subsystem.

**Alternatives considered and rejected:**
- `app_notification` / `app_notification_teacher` (2023) — the `easy_com` parent-communication
  system. Separate tables, separate controllers, oriented at staff→parent broadcast. Not per-learner
  learning state.
- Laravel's own `notifications` table — not in use anywhere in this estate; adopting it would mean
  introducing a second in-app notification store alongside the PAL one.

---

## 3. What proactive retention reminders would actually require

Five gaps, smallest first. None of these are built.

### 3.1 A producer (the real missing piece)
Nothing creates a notification row when `next_review_at` comes due. The query already exists —
`EsoPolicyService::dueForRetrieval()` is exactly
`learner_node_state WHERE status='mastered' AND next_review_at <= now()`.

Required: a console command (e.g. `eso:notify-retention-due`) that walks due rows grouped by
learner and writes one `GamificationNotification` per learner, `type = 'retention_due'`, with
`context` carrying `concept_id` / `node_id` so the client can deep-link to `/pal/eso?conceptId=…`.

**Group per learner, not per node.** A student with six due nodes must not receive six
notifications; the retention ladder is designed to be gentle, and six reminders would defeat it.

### 3.2 A scheduler entry
`app/Console/Kernel.php` currently schedules only Neo4j jobs (`neo4j:drain`, `neo4j:reconcile`, the
outbox-depth alarm, the coherence-mastery sweep). A daily `->dailyAt(...)->withoutOverlapping()`
entry would be needed. Time-of-day is a product decision, not an engineering one — a reminder at
02:00 is worse than none.

### 3.3 Idempotency — the one real correctness risk
`pal_gamification_notifications` has **no unique constraint**, and `next_review_at` stays in the
past until the student actually takes the check. A daily job would therefore re-notify the same
learner about the same node **every day** until they act.

Two viable fixes:
- a `retention_notified_at` column on `learner_node_state`, cleared by `scheduleRetention()`; or
- a dedupe read against `pal_gamification_notifications` for an unread `retention_due` row for that
  learner within the current ladder rung.

The first is cheaper and self-clearing. Either way this must be settled **before** the job ships —
it is the difference between a reminder and spam.

### 3.4 Out-of-app delivery
`pal_gamification_notifications` is **in-app only**. A row is visible when the student next opens
PAL — which does not solve "come back on day 30". Genuine out-of-app delivery (push, email, SMS,
WhatsApp) has no existing PAL-side rail; `easy_com` is a staff→parent system and would need a
deliberate product decision about contacting students directly, plus consent handling. **Out of
scope until that decision is made.**

### 3.5 Surfacing
The PAL gamification views already read the unread count. No global header bell in the main app was
found wired to this table, so a retention reminder would surface inside PAL only until one is added.

---

## 4. Recommended order, if this is picked up

1. `retention_notified_at` column + clear it in `scheduleRetention()` (§3.3) — the correctness gate.
2. `eso:notify-retention-due` command, per-learner grouping, `type='retention_due'` (§3.1).
3. Scheduler entry once a send time is chosen (§3.2).
4. Surface the count where students already look (§3.5).
5. Out-of-app delivery only after the consent/product decision (§3.4).

Steps 1–3 are small and self-contained. Step 5 is a different kind of project.
