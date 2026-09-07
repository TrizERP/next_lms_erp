# ESO Phase 1 — Manual QA Walkthrough (UI)

Every route, label, role and prerequisite below was read from the live codebase and database, not
assumed. Where a feature **cannot** be tested from the UI as things stand, that is stated up front
with the reason rather than a test you would follow and find broken.

---

## 0. Before you start — read this first

### 0.1 The login blocker (affects every ESO test)

Adaptive Learning is **student-only** at the API level (`eso.student` middleware,
`routes/pal_eso_api.php`). A teacher/admin session gets **403** on every learning route — that is
correct, intended behaviour, not a bug. **So you must log in as a student to test any of this.**

The synthetic pilot student **283919 ("ESO Pilot Test Student") has no email, username or
password** — it cannot log in through `/login`. You have two options:

**Option A (recommended) — give the synthetic student a login.** One command, touches only the
synthetic row, keeps all test learning data off real students:

```bash
cd d:/next_lms_erp && php artisan tinker --execute="
DB::table('tblstudent')->where('id',283919)->update([
  'email' => 'eso.pilot@example.com',
  'password' => bcrypt('Pilot@123'),
  'user_profile_id' => 8,   // profile name 'Student' — drives isStudentSession() in the UI
]);
echo 'done';"
```
Then log in as `eso.pilot@example.com` / `Pilot@123`.

**Option B — use an existing credentialed student.** `student@triz.co.in` (GREEVA RAFALIYA,
id **97926**, standard 43 / section 9 / syear 2022, profile 8 = Student) is in the exact class
Chapter 1014 belongs to. Password unknown to me — ask whoever owns the demo data.
⚠️ This writes real learning rows against that student's record.

`user_profile_id = 8` matters: the UI decides "is this a student?" from the **profile name being
`Student`** (`isStudentSession()` in `app/pal/data/pal-lookups.ts`). Without it the app shows the
staff Student-Picker instead of the student's own PAL.

### 0.2 Environment

- Backend: `php artisan serve` (http://127.0.0.1:8000)
- Frontend: `npm run dev` in `d:/lms_k12` — **check the port it prints.** It may not be 3000; in
  this environment it came up on **3001** because 3000 was occupied.
- URLs below are written as `{APP}` — substitute your actual host:port.

### 0.3 Test data (fixed, already seeded)

| Thing | Value |
|---|---|
| Chapter | **1014 — Metals and Non-metals** (subject: Science, standard 43) |
| Concept | **114 — Physical Properties of Metals** (the only ESO-ready concept in the chapter) |
| Nodes | **K = 91** (Recall), **A = 92** (Application), **S = 195** (Skill/transfer) |
| Misconception question | **Q104560** — "…these metals are poor conductor of heat" |
| Wrong answer that triggers D3 | **"Lead and aluminium"** → misconception **3670** |
| Correct answer to that question | "Lead and mercury" |

### 0.4 Reset between tests

```bash
cd d:/next_lms_erp && php "C:/Users/Asus/AppData/Local/Temp/claude/d--next-lms-erp/3334bed6-2c64-4616-867a-df02872df5f7/scratchpad/eso_scenario.php" clean
```
Wipes only student 283919's concept-114 state and decision log.

---

## 1. ❌ D2 Prerequisite staleness → quick probe — **NOT TESTABLE FROM UI**

**Why not:** Concept 114 has **zero prerequisite relations** (`pal_concept_relations` where
`from_concept_id = 114` returns 0 rows). D2 only runs when a concept *has* a prerequisite, so the
staleness branch can never fire for the one ESO-ready concept in the pilot. Nothing in the UI can
trigger it.

**To make it testable**, someone would have to author a prerequisite relation (a content
decision, not a QA step), give that prerequisite concept K/A/S nodes and at least one approved
MCQ, seed the student above the 0.75 threshold, then backdate `last_seen_at` more than 30 days.
Until then this is covered only by the automated tests
(`EsoPolicyServiceTest::test_a_passing_but_stale_prerequisite_triggers_a_quick_probe...`).

---

## 2. ❌ Adaptive practice tiers / difficulty progression — **NOT VISIBLY TESTABLE**

**Why not:** all **25** questions tagged to concept 114's nodes have `difficulty_1_to_5 = NULL`.
The banding logic has nothing to sort on, so it correctly falls back to random selection — meaning
there is nothing to observe in the UI. There is also, by design, **no difficulty label shown to
students** anywhere.

**To make it testable:** the content team would need to populate `difficulty_1_to_5` on the
question metadata for these nodes. After that, you'd observe it indirectly: answer several
questions correctly in a row and the served questions should skew harder.

---

## 3. ✅ Multi-stage retention ladder (Day 2 → Week → Month → 2mo → 6mo)

**Role:** student. **Start:** `/login`.

**Seed first** (you can't wait 2 days):
```bash
cd d:/next_lms_erp && php artisan tinker --execute="
DB::table('learner_node_state')->where('student_id',283919)->whereIn('node_id',[91,92,195])->delete();
DB::table('eso_decision_log')->where('student_id',283919)->where('concept_id',114)->delete();
DB::table('learner_node_state')->insert([
 ['student_id'=>283919,'node_id'=>91,'sub_institute_id'=>1,'mastery_estimate'=>1.0,'attempts'=>5,'status'=>'mastered','retention_stage'=>0,'next_review_at'=>now()->subDay(),'last_seen_at'=>now()->subDays(3),'created_at'=>now(),'updated_at'=>now()],
 ['student_id'=>283919,'node_id'=>92,'sub_institute_id'=>1,'mastery_estimate'=>0.4,'attempts'=>1,'status'=>'learning','retention_stage'=>0,'next_review_at'=>null,'last_seen_at'=>now(),'created_at'=>now(),'updated_at'=>now()],
 ['student_id'=>283919,'node_id'=>195,'sub_institute_id'=>1,'mastery_estimate'=>1.0,'attempts'=>2,'status'=>'mastered','retention_stage'=>0,'next_review_at'=>now()->addDays(2),'last_seen_at'=>now(),'created_at'=>now(),'updated_at'=>now()],
]); echo 'seeded';"
```

**Navigate:** Login → you land on `/dashboard` → go to `{APP}/pal/eso?conceptId=114`
(or: sidebar **LMS + PAL → Test → PAL** → expand **Science** → **Metals and Non-metals** row →
**Adaptive learning** → **Physical Properties of Metals**).

**What you should see:** card titled **"Quick review"**, subtitle *"A short check to make sure this
is still solid a few days later."*, with 2–3 questions.

**Do:** answer **every** question correctly → **Submit**.

- **PASS:** card flips to **"Retained"** — *"You still had it a few days later — this is locked
  in."* Then verify the ladder actually climbed:
  ```bash
  cd d:/next_lms_erp && php artisan tinker --execute="
  echo json_encode(DB::table('learner_node_state')->where('student_id',283919)->where('node_id',91)
    ->first(['retention_stage','next_review_at','status']));"
  ```
  Expect `retention_stage: 1` and `next_review_at` **≈7 days out** (not 4 — 4 was the old flat
  interval this replaced).
- **FAIL:** `retention_stage` stays 0, or `next_review_at` is ~4 days out, or it's null.

**Reset-on-failure test:** re-seed, then answer **one question wrong**.
- **PASS:** card reads **"Let's revisit this"**, and `retention_stage` is back to **0** with
  status `learning`.
- **FAIL:** stage stays at its previous value.

**Visual confirmation:** the "Retained" card plus the DB check showing 0 → 1 and a 7-day date.

---

## 4. ✅ Activation-energy / real-world motivation nudge

**Role:** student. **Precondition:** a node with **exactly 1 attempt** (this fires once, on the
first call where a node moves from *teach* to *practice*). The seed in §3 already sets node 92
(A114) to `attempts = 1`.

**Navigate:** `{APP}/pal/eso?conceptId=114` → complete the retention check from §3 → click
**Continue** → the flow moves to A114 practice.

**What you should see:** an **amber panel with a sparkle icon**, above the question, reading
something like:
> *"Quick reminder of why this matters — Metals are generally lustrous, hard, malleable, ductile,
> sonorous, and good conductors of heat and electricity. A few practice questions and this one is
> yours."*

- **PASS:** amber nudge appears **once**. Submit an answer and continue practising — it must
  **not** reappear on subsequent practice screens.
- **FAIL:** it appears on every practice screen, never appears at all, or shows engine-facing text
  starting *"The student has just understood…"* (that would mean the student-facing fallback
  isn't being used).

**Expected honest limitation:** Concept 114 has **no `real_world_applications` data** (it was
dropped from this chapter's extraction), so you'll see the **definition-based fallback** above,
not a real-world example. That is correct behaviour, not a failure. Concepts that *do* have the
data (e.g. Corrosion, Alloys) would show a genuine real-world use.

---

## 5. ✅ ESO gamification — badges

**Role:** student. **Precondition:** the student must actually reach **D4 mastery** on concept 114.

**Fastest route to mastery:**
```bash
cd d:/next_lms_erp && php artisan tinker --execute="
DB::table('learner_node_state')->where('student_id',283919)->whereIn('node_id',[91,92,195])->delete();
DB::table('eso_decision_log')->where('student_id',283919)->where('concept_id',114)->delete();
echo 'clean';"
```
Then in the UI: `{APP}/pal/eso?conceptId=114` → **Quick diagnostic** → answer **every question
correctly** → **Submit diagnostic**.

**What you should see:** the **"Concept unlocked"** card (see §7).

**Then check the badge:** navigate to `{APP}/pal/new/gamification/badges`
(menu path: **LMS + PAL → New PAL → Gamification**, then the Badges page — if a student role
lacks the New PAL menu right, go by URL).

- **PASS:** a badge named **"Concept unlocked"** is present, with the message *"You worked a
  concept all the way to mastery — diagnostic, practice and all. That one is yours now."*
  DB check: `SELECT * FROM pal_learner_badges WHERE learner_id=283919 AND badge_id='BADGE_ADAPTIVE_FIRST_MASTERY'`
- **FAIL:** no badge row. First check the catalogue is synced:
  `php artisan pal:sync-badges --dry-run` — it should report **0 new**. If it reports 3 new, run it
  without `--dry-run`.

**"It stuck" badge:** pass a spaced-retention check (§3) → badge **"It stuck"**
(`BADGE_ADAPTIVE_IT_STUCK`) should appear.

**Streaks:** `{APP}/pal/new/gamification/streaks`. Note streak days are driven by
`LearnerActivitySource::dailyActivity()`, which counts PAL quiz/session activity — a single ESO
session may not qualify as a streak day on its own, so **do not treat an empty streak as an ESO
failure**.

---

## 6. ✅ Evidence shown on wrong/misconception answers

**Role:** student. **Reset first** (§0.4).

**Navigate:** `{APP}/pal/eso?conceptId=114` → **Quick diagnostic**. Answer the **K-node** questions
correctly and the **A-node** questions **wrong** (so A114 stays weak) → **Submit diagnostic** →
you land on **Practice**.

**Get Q104560:** the practice question is picked at random from A114's pool of 6. **Refresh the
page** until the question text ends *"…these metals are poor conductor of heat."* (usually a few
reloads).

**Do:** select **"Lead and aluminium"** → **Submit**.

**What you should see** — card **"Let's clear up a mix-up"**, and above the corrective content a
**white panel bordered in rose** containing:
- **"You answered:"** *Lead and aluminium* (in italics)
- **"What that suggests:"** the misconception description (*"Students assume all metals conduct
  heat equally well…"*)
- If it has happened before: *"This has come up once before on this part of the concept."*

- **PASS:** all of the above renders, and the answer shown is **the option you actually clicked**.
- **FAIL:** the mix-up card appears with no evidence panel, or shows a different answer than the
  one you chose.

**Recurrence check:** complete the retest wrongly then trigger the same misconception again — the
"This has come up… before" line should appear and the count should increase.

---

## 7. ✅ "Concept unlocked" / Mastered experience

**Role:** student. Reached at the end of §5.

**What you should see:** green card titled **"Concept unlocked"** (it previously said "Mastered"),
subtitle *"You've cleared this concept — practice stops here…"*, with **Knowledge: X%** and
**Application: Y%**, and two buttons: **"See what this earned you"** and **"Back to subjects"**.

- **PASS:** title reads "Concept unlocked"; **"See what this earned you"** navigates to
  `/pal/eso/mastery/114`; **"Back to subjects"** navigates to `/pal`.
- **FAIL:** card still titled "Mastered", or either button dead-ends.

---

## 8. ❌ Direct-launch remedial content — **NOT TESTABLE WITH CURRENT DATA**

**Why not:** the only corrective authored for misconception 3670
(`pal_misconception_corrective` id 7315, *"Not all metals conduct heat equally"*) has
**`media_url = NULL`** and `format = 'text_diagram'`. The new launcher only renders when a
corrective actually carries a media URL, so with today's content you'll correctly see just the
text body — nothing to launch.

**To make it testable:** author (or temporarily set) a `media_url` on that corrective row — a video
URL renders an inline player; any other URL renders an **"Open the walkthrough for this"** link
that opens in a new tab.

---

## 9. ✅ Near-milestone / progress experience (chapter dashboard)

This was already built in the project — here is how to reach it.

**Navigate:** `{APP}/pal/eso/chapter/1014`

**What you should see:** *"Hello, {name}"*, chapter chip **Metals and Non-metals**, a **Starting
point** chip, four stat cards (**Mastered concepts**, **Current concept**, **Responses on this
concept**, **All responses**), a **"Next Step"** panel, **"Sections in this chapter"** list, and
**"What PAL has seen so far"** (6 mastery signals).

**Do:** click **"See why"** on the Next Step panel.

- **PASS:** Next Step title/subtitle match the student's real state (e.g. *"Start with a quick
  check"* when nothing is recorded, *"Keep practicing"* mid-flow, *"Concept mastered"* after §5);
  **"See why"** reveals a plain-language reason ("Why: based on your diagnostic answers…"); the
  section list shows **Locked / Not started / In progress / Mastered** chips; clicking a
  non-locked section opens `/pal/eso?conceptId=…`.
- **FAIL:** Next Step contradicts the state you just created, or section statuses never change.

---

## 10. ✅ Personalized student dashboard

**Navigate:** log in as the student → you land on **`/dashboard`** automatically.

- **PASS:** the same "Hello, {name}" chapter dashboard renders **without you choosing a chapter** —
  it auto-picks the first ESO-ready chapter with open work (`studentDashboard()`), and the numbers
  match what `/pal/eso/chapter/1014` shows.
- **FAIL:** you see a teacher/admin dashboard instead (means the session's profile name isn't
  `Student` — revisit §0.1), or an error/empty state despite the student having enrollment for the
  selected academic year.

⚠️ **Academic year matters:** the student's enrollment is **syear 2022**. If the header's year
selector shows a different year, the dashboard will legitimately show "no content".

---

## 11. ✅ Concept diagnosis / mastery details

**Navigate:** `{APP}/pal/eso/mastery/114` (or the **"See mastery details"** button on the chapter
dashboard, or **"See what this earned you"** on the unlocked card).

- **PASS:** shows status, an honest confidence note (*"Based on N recorded responses… This is an
  inference from your responses, not a measurement of what you know."*), the 6 mastery signals with
  response counts, a **hint-assisted vs independent** support split, misconception history
  (including whether each was corrected), and your last responses.
- **FAIL:** signals all show "Not enough evidence" *after* you've recorded responses, or the
  misconception you triggered in §6 is missing from the history.

**Knowledge map:** `{APP}/pal/eso/knowledge-map/114` — the chapter's concept graph with
lock/prerequisite reasons.

---

## 12. ❌ STALE_MASTERY display status — **NOT IMPLEMENTED**

This was in the plan but **not built**. The staleness *signal* now exists in the engine (it drives
the §1 prerequisite probe), but it is **not surfaced as a status label** anywhere in the UI.
Concept statuses today are: `locked / not_started / in_progress / mastered / retained / not_ready`.
Nothing to test.

---

## 13. ❌ Third-party content provision (Khan Academy / IXL) — **NOT IMPLEMENTED**

Also in the plan, **not built**. There is currently no `content_source` / `external_url` field
anywhere, and no external-content UI. Nothing to test.

---

## 14. ✅ Teach/Practice fallback text when the LLM is unavailable

**Context:** the LLM provider currently returns HTTP 402 (insufficient balance), so **every** Pal
rendering falls back. That makes this easy to observe right now.

**Navigate:** any teach or practice screen (§4).

- **PASS (current expected state):** the violet instruction panel shows the **plain engine
  instruction** and the student is never blocked — the question still loads and is answerable.
  The **amber motivation panel** shows **student-facing** wording (§4), *not* engine text.
- **FAIL:** the screen hangs waiting for Pal, shows an error instead of the question, or the
  **amber** panel shows *"The student has just understood…"*.

⚠️ **Known cosmetic issue (pre-existing, not Phase 1):** the violet teach/practice panel shows raw
engine-facing instruction text (*"Teach A: … The student is still practicing this node (mastery
40%, 1 attempt(s) so far)…"*). That is meta-commentary a student shouldn't see. It only appears
because the provider is unfunded. Worth fixing the same way the motivation nudge now is — flagging,
not a Phase 1 regression.

---

## MASTER MANUAL TEST FLOW

Shortest complete sequence. Steps marked 🔧 are terminal commands.

1. 🔧 **Enable the test login** (§0.1 Option A) — one-time.
2. **Log in** at `{APP}/login` as `eso.pilot@example.com` / `Pilot@123`.
3. **Land on `/dashboard`** → ✅ **Test 10** (personalized dashboard auto-picks the chapter).
   *Check the year selector reads 2022.*
4. Go to `{APP}/pal/eso/chapter/1014` → ✅ **Test 9** (Next Step panel, "See why", section chips).
5. 🔧 **Reset state** (§0.4).
6. Sidebar **LMS + PAL → Test → PAL** → expand **Science** → **Metals and Non-metals** →
   **Adaptive learning** → **Physical Properties of Metals**.
   *(Confirms the student entry point; URL should be `/pal/eso?conceptId=114` with **no**
   `learnerId`.)*
7. **Quick diagnostic** → answer **K questions right, A questions wrong** → **Submit diagnostic**.
8. On **Practice**, refresh until **Q104560** appears → answer **"Lead and aluminium"** →
   ✅ **Test 6** (evidence panel) and ✅ **Test 14** (fallback text renders, nothing blocks).
9. Type an explanation → **"I understand — retest me"** → answer the retest **correctly**.
10. 🔧 **Reset**, then repeat step 6 and answer the diagnostic **100% correctly** →
    ✅ **Test 7** ("Concept unlocked" card).
11. Click **"See what this earned you"** → ✅ **Test 11** (mastery details).
12. Go to `{APP}/pal/new/gamification/badges` → ✅ **Test 5** ("Concept unlocked" badge present).
13. 🔧 **Seed the retention state** (§3 command) → reopen `{APP}/pal/eso?conceptId=114` →
    **Quick review** → all correct → **Retained** → 🔧 verify `retention_stage = 1` and a **7-day**
    `next_review_at` → ✅ **Test 3**.
14. Click **Continue** → A114 practice → ✅ **Test 4** (amber motivation nudge, appears once).
15. 🔧 Re-seed and fail one retention question → **"Let's revisit this"** → 🔧 verify
    `retention_stage = 0` → ✅ **Test 3 (reset branch)**.
16. 🔧 **Final reset** (§0.4).

**Not covered by this flow because they cannot be tested from the UI today:**
Tests **1** (no prerequisite authored), **2** (no difficulty tagging), **8** (no corrective media),
**12** and **13** (not implemented).
