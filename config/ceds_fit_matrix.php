<?php

/*
|--------------------------------------------------------------------------
| CEDS: Teaching Method (pedagogy) x Resource Format fit
|--------------------------------------------------------------------------
|
| Data only. Nothing here reads or writes the database. The matrix is taken
| from the CEDS prompt (std9_ceds_prompt_v2.md) and is meant to be revised
| after teachers have used the pilot, so it lives in config, not in code.
|
| Fit codes: S = strong, P = possible, W = weak (warn, suggest, never block).
*/

return [

    // Period length assumed when the caller does not know it.
    'default_period_minutes' => 40,

    // The six resource formats (D1-D6). `existing` says where already-stored
    // content for the format is found; the resolver reads that, never writes it.
    'formats' => [
        'D1' => ['label' => 'Classroom Content',      'existing' => 'content_master: the chapter\'s Classroom Presentation + Revision Notes (no category of its own)'],
        'D2' => ['label' => 'Classroom Presentation', 'existing' => 'content_master study deck'],
        'D3' => ['label' => 'Classroom Activity',     'existing' => 'content_master study document'],
        'D4' => ['label' => 'Prayogshala',            'existing' => 'lms_prayogshala_activity'],
        'D5' => ['label' => 'Revision Note',          'existing' => 'content_master study document'],
        'D6' => ['label' => 'Remedial Class',         'existing' => 'content_master study document'],
    ],

    // The eight teaching methods and their phases (weight = share of the period;
    // each method's weights sum to 100). Used by the lesson-flow stage, not by fit.
    'pedagogies' => [
        'P1' => ['label' => 'Direct instruction', 'subjects' => ['science', 'maths'], 'phases' => [
            ['Hook', 10], ['Explain', 25], ['Model', 20], ['Guided practice', 20], ['Independent practice', 15], ['Exit check', 10]]],
        'P2' => ['label' => 'Inquiry (5E)', 'subjects' => ['science'], 'phases' => [
            ['Engage', 10], ['Explore', 30], ['Explain', 25], ['Elaborate', 20], ['Evaluate', 15]]],
        'P3' => ['label' => 'Problem / scenario-based', 'subjects' => ['science'], 'phases' => [
            ['Situation', 10], ['Predict', 10], ['Investigate', 30], ['Reason', 20], ['Connect to principle', 15], ['Reflect', 15]]],
        'P4' => ['label' => 'Flipped', 'subjects' => ['science', 'maths'], 'phases' => [
            ['Pre-class input with check', 0], ['In-class clarification', 25], ['Application', 50], ['Debrief', 25]]],
        'P5' => ['label' => 'Collaborative', 'subjects' => ['science', 'maths'], 'phases' => [
            ['Individual think', 15], ['Group task with roles', 40], ['Share', 15], ['Synthesise', 15], ['Individual check', 15]]],
        'P6' => ['label' => 'Socratic', 'subjects' => ['science', 'maths'], 'phases' => [
            ['Opening question', 10], ['Probe', 25], ['Challenge', 20], ['Surface misconception', 20], ['Refine', 15], ['Student summary', 10]]],
        'P7' => ['label' => 'Project-based (after mastery)', 'subjects' => ['science', 'maths'], 'phases' => [
            ['Driving question', 10], ['Plan', 20], ['Build', 40], ['Present', 20], ['Reflect', 10]]],
        'P8' => ['label' => 'Mastery loop', 'subjects' => ['maths', 'science'], 'phases' => [
            ['Diagnose', 15], ['Scaffold gaps', 25], ['Practise with feedback', 30], ['Mastery check', 15], ['Extend', 15]]],
    ],

    // Lesson flow (LessonFlowBuilder): which STORED resources can support each phase of a method.
    // One entry per phase, in the order of `phases` above. A phase lists the resource ROLES that have a real
    // stored counterpart; an empty list means "teacher-led, nothing stored" and is never filled by guessing.
    //   hook       the deck's hook slide (chapter-level)
    //   explain    deck slides of the topic: concept_intro, concept_visual, relationship
    //   worked     deck slides of the topic: worked_example
    //   applied    deck slides of the topic: scenario, application
    //   misconception  deck slides of the topic: misconception
    //   activity   the stored classroom-activity sections of the topic, and the topic's Prayogshala labs
    //   questions  the bank questions the topic's slides already carry
    //   notes      the topic's Revision Notes sections
    // A resource is only ever attached through a verified id: the topic's concept ids (slides), the topic id
    // (activities, notes, labs) or the slide type (hook).
    'phase_roles' => [
        'P1' => [['hook'], ['explain'], ['worked'], ['questions'], ['activity'], []],
        'P2' => [['hook'], ['activity'], ['explain'], ['worked', 'applied'], ['questions', 'notes']],
        'P3' => [['applied'], [], ['activity'], ['explain'], [], []],
        'P4' => [['explain'], ['misconception'], ['activity'], []],
        'P5' => [[], ['activity'], [], [], ['questions']],
        'P6' => [[], [], [], ['misconception'], [], []],
        'P7' => [[], [], [], [], []],
        'P8' => [[], ['explain'], ['questions'], [], ['activity']],
    ],

    'lesson_flow' => ['min_period' => 10, 'max_period' => 120],

    // WHERE THE CEDS PICKER AND LESSON PLANNER ARE AVAILABLE. For now: Std 9 Science, Chapter 1, and nothing else.
    // Both endpoints refuse any other chapter (403), and the Classroom Resource list only asks for a teaching
    // method on this chapter's cards. A chapter is allowed only when its id, standard AND subject all match.
    // To widen it later, add an entry here and its id to lms_k12/lib/ceds/scope.ts.
    'scope' => [
        'label' => 'Std 9 Science, Chapter 1',
        'chapters' => [
            ['chapter_id' => 8592, 'standard_id' => 42, 'subject_id' => 3975],
        ],
    ],

    // Fit: pedagogy => [format => S|P|W]
    'matrix' => [
        'P1' => ['D1' => 'S', 'D2' => 'S', 'D3' => 'P', 'D4' => 'W', 'D5' => 'S', 'D6' => 'P'],
        'P2' => ['D1' => 'P', 'D2' => 'P', 'D3' => 'S', 'D4' => 'S', 'D5' => 'W', 'D6' => 'P'],
        'P3' => ['D1' => 'P', 'D2' => 'P', 'D3' => 'S', 'D4' => 'S', 'D5' => 'W', 'D6' => 'P'],
        'P4' => ['D1' => 'S', 'D2' => 'P', 'D3' => 'S', 'D4' => 'P', 'D5' => 'P', 'D6' => 'W'],
        'P5' => ['D1' => 'W', 'D2' => 'W', 'D3' => 'S', 'D4' => 'S', 'D5' => 'W', 'D6' => 'P'],
        'P6' => ['D1' => 'W', 'D2' => 'P', 'D3' => 'P', 'D4' => 'W', 'D5' => 'W', 'D6' => 'S'],
        'P7' => ['D1' => 'W', 'D2' => 'W', 'D3' => 'P', 'D4' => 'S', 'D5' => 'W', 'D6' => 'W'],
        'P8' => ['D1' => 'P', 'D2' => 'P', 'D3' => 'P', 'D4' => 'P', 'D5' => 'S', 'D6' => 'S'],
    ],

    // D1 Classroom Content is met by existing resources, not generated. These are the places where those
    // resources fall short of the D1 spec in the CEDS prompt. They are RECORDED here only: nothing is
    // generated or changed to close them (found by reading the stored study decks 61401 / 61406, 2026-10-17).
    'd1_known_gaps' => [
        ['key' => 'real_life_links', 'label' => 'Real-life application links', 'detail' => 'Only a scenario slide, and application slides in chapter 8593 (3) but none in 8592.'],
        ['key' => 'key_vocabulary', 'label' => 'Key-vocabulary list', 'detail' => 'The deck has no dedicated vocabulary field; whether the Revision Notes carry one is not yet verified.'],
        ['key' => 'misconceptions', 'label' => 'Common misconceptions', 'detail' => 'Chapter 8592 has 20 slides with one; chapter 8593 has only 1.'],
        ['key' => 'check_yourself', 'label' => 'Check-yourself question coverage', 'detail' => 'Bank questions referenced by the deck: 8 in chapter 8592 and 23 in 8593.'],
    ],

    // Qualifiers the prompt attaches to some cells.
    'notes' => [
        'P2.D2' => 'Suits the explain phase only.',
        'P4.D1' => 'Suits the pre-class input.',
        'P4.D3' => 'Suits the in-class part.',
        'P6.D2' => 'Works when question-led.',
    ],

    // One-line reason shown once for every weak pairing.
    'weak_reasons' => [
        'P1.D4' => 'Direct instruction explains first; a lab is an experience, not an explanation.',
        'P2.D5' => 'Inquiry builds understanding by doing; a revision note summarises it afterwards.',
        'P3.D5' => 'Scenario work is investigative; a revision note is a summary of settled ideas.',
        'P4.D6' => 'Flipped lessons assume the pre-class input worked; remediation is a separate class.',
        'P5.D1' => 'Collaborative work is built around a group task, not a reading text.',
        'P5.D2' => 'A slide deck keeps attention on the teacher, not on group work.',
        'P5.D5' => 'Group work is not revised from a single-reader note.',
        'P6.D1' => 'Socratic teaching draws ideas out by questions rather than handing out a text.',
        'P6.D4' => 'A lab leads with action; Socratic teaching leads with questioning.',
        'P6.D5' => 'Socratic teaching builds understanding in dialogue, not from a note.',
        'P7.D1' => 'Project work runs after mastery; explanatory content comes before it.',
        'P7.D2' => 'Project work is student-built, not teacher-presented.',
        'P7.D5' => 'Project work is an application after mastery, not revision.',
        'P7.D6' => 'Project work happens after mastery; remediation happens before it.',
    ],
];
