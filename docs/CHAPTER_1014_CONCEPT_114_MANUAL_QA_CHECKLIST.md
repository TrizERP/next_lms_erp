# Concept 114 — Manual QA Checklist

Companion to `docs/CHAPTER_1014_CONCEPT_114_USER_JOURNEY.md` (read that first for full detail on
every step, screen, API, and decision-log entry referenced here). Every label below is copied
from the real UI components — nothing here is invented.

Use only synthetic student **283919 ("ESO Pilot Test Student")**. Do not enroll or use a real
student for this checklist.

## Setup

- [ ] Laravel backend running (`php artisan serve`)
- [ ] Next.js frontend running (`npm run dev`, `d:/lms_k12`)
- [ ] Logged in as a staff/teacher account with real credentials (283919 has no login credentials
      of its own — see the journey doc's Known Limitations)

## A. Reach the PAL workspace and pick the test student

- [ ] Navigate: **LMS + PAL → Test → PAL** (sidebar)
- [ ] `StudentPicker` shows: "Select a student"
- [ ] Select Grade **SEC**, Standard **10**, Division **A**
- [ ] Search/select **"ESO Pilot Test Student"**
- [ ] Click **"Review student"**
- [ ] `ViewAsBanner` confirms you are viewing 283919's PAL

## B. Find Chapter 1014 and enter Adaptive Learning

- [ ] Expand the **Science** subject
- [ ] Find the **"Metals and Non-metals"** chapter row
- [ ] Click **"Adaptive learning"** button on that row
- [ ] Modal opens: **"Start Adaptive Learning"** / "Pick a concept to work on"
- [ ] Click **"Physical Properties of Metals"**
- [ ] URL becomes `/pal/eso?conceptId=114&learnerId=283919` (never typed by hand)

## C. Diagnostic (D1)

- [ ] Card title **"Quick diagnostic"** renders with question(s)
- [ ] Each question shows a K/A/S badge
- [ ] Answer all questions, click **"Submit diagnostic"**
- [ ] Verify next screen is either **"Let's learn this"** (S114 first exposure) or **"Mastered"**
      (if S114's items were also sampled and answered correctly)

## D. Verify D1 via the decision log

- [ ] `GET /api/pal/eso/decision-log/283919/114` shows `skip_instruction` rows for node 91 (K114)
      and node 92 (A114) on a perfect diagnostic

## E. Teaching / Practice

- [ ] If routed to practice: card title **"Practice"** (or **"Let's learn this"** on first
      exposure) renders an answerable question
- [ ] Submit an answer, confirm the flow advances

## F. Trigger the Q104560 misconception (real, mapped)

- [ ] Reset to a clean state (`eso_scenario.php clean`) and re-enter via the button flow
- [ ] On the diagnostic, answer K114 correctly and A114 incorrectly (weak) — routes to Practice
- [ ] Reload practice until **Q104560** appears (its text ends "...poor conductor of heat")
- [ ] Select **"Lead and aluminium"**, click **Submit**

## G. Verify the contrast pair (D3)

- [ ] Card title **"Let's clear up a mix-up"** renders
- [ ] Corrective content is visible
- [ ] Type an explanation in the textarea, click **"I understand — retest me"**
- [ ] A fresh question loads (different question id than before)

## H. Complete the fresh retest

- [ ] Answer the retest question correctly, click **Retest**
- [ ] Flow advances past the contrast-pair state

## I. Verify D3 via the decision log

- [ ] A `serve_contrast_pair` row exists with `state_snapshot.misconception_id = 3670`
- [ ] A `misconception_corrected` row exists after the clean retest

## J. Verify D4 mastery

- [ ] Continue answering (K114/A114/S114) until the **"Mastered"** card appears
- [ ] Knowledge % and Application % are both shown
- [ ] A `mastered_stop_practice` decision-log row exists

## K. Verify D5 scheduling

- [ ] Query `learner_node_state` (or use the pilot metrics endpoint) — every mastered node has a
      non-null `next_review_at` (~4 days out)

## L. Trigger retrieval (D5) without waiting

- [ ] Run the existing test-only scenario script: `php eso_scenario.php mastered_due` for
      student 283919 (safe, synthetic-only, does not touch real students)
- [ ] Re-enter Concept 114 via the button flow again
- [ ] **"Quick review"** card appears immediately (no waiting)

## M. Verify Retained

- [ ] Answer all retrieval items correctly, click **Submit**
- [ ] Card title **"Retained"** appears
- [ ] Click **Continue**

## N. Verify the re-loop path

- [ ] On the next node's retrieval check, answer the first item wrong (rest correct), Submit
- [ ] Card title **"Let's revisit this"** appears
- [ ] Confirm via DB/decision log: only that node became `learning` again; the previously
      Retained node is unaffected

## O. Verify the decision log end-to-end

- [ ] `GET /api/pal/eso/decision-log/283919/114` shows a sequence whose every `rule_fired` starts
      with `D1`-`D5` — never a generic "AI decided" string

## P. Pal behavior

- [ ] Confirm the plain instruction (or Pal's phrased text, if the LLM provider has balance)
      appears on the Teach/Practice and Contrast-pair screens — the student is never blocked
      waiting on it
