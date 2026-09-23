<?php

/*
|--------------------------------------------------------------------------
| RBAC module registry
|--------------------------------------------------------------------------
|
| Maps an abstract module name onto the tblmenumaster row that already carries
| its rights, plus the action -> column mapping.
|
| WHY A REGISTRY AND NOT MENU IDS IN CODE
| checkPermission.php:73,77 hardcodes `in_array($menu_id,[200])` and
| `in_array($menu_id,[31,82,386])` to bypass delete/edit checks. Menu ids differ
| between environments, so that is both a latent bug AND a Decision #23 violation
| ("configuration can never grant a permission"). Modules are therefore resolved by
| tblmenumaster.link, which is stable across environments, and this file grants
| nothing — it only names where a grant is stored.
|
| WHAT LIVES HERE vs WHAT LIVES IN THE DATABASE
| This file: which menu row a module maps to. Nothing else.
| tblgroupwise_rights / tblindividual_rights: who may do what. The ONLY source of
| grants. Adding a module here gives nobody any access.
|
| Measured on live 2026-09-07: menu 236 (`content_master.index`, "Add Content") already
| carries rights for 89 profiles — can_view 89, can_add 87, can_edit 85, can_delete 80.
| So the mechanism Decision #37 asks us to reuse is already populated; nothing needs
| seeding, only reading.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Action -> rights column
    |--------------------------------------------------------------------------
    |
    | Deliberately the same four columns checkPermission.php already reads, so there
    | is exactly one meaning of "can add" in the system rather than two.
    |
    */
    'actions' => [
        'view'   => 'can_view',
        'create' => 'can_add',
        'update' => 'can_edit',
        'delete' => 'can_delete',
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    |
    | `links` are tblmenumaster.link values, tried in order; the first that resolves
    | to an active menu row wins. More than one is allowed because the same capability
    | is reached from different menu entries in the Blade and Next.js navigations.
    |
    */
    'modules' => [

        // CONTENT_CREATE in the tracker's language (row 5 / Decision #37): the gate on
        // Generate + Upload inside Classroom Resource / Teacher Workspace.
        'lms.content' => [
            'label' => 'LMS content',
            'links' => ['content_master.index', 'course-master/'],
        ],

        'lms.question_bank' => [
            'label' => 'LMS question bank',
            'links' => ['question_paper.index', 'content_master.index'],
        ],

        // The gate on editing the Coherence Map: creating, approving, dismissing and
        // deleting prerequisite relationships.
        //
        // Deliberately NOT folded into lms.content. That key is CONTENT_CREATE - the
        // right to generate and upload material. Approving a `requires` edge is a
        // different act: EsoPolicyService's D2 gate reads those edges to decide what a
        // learner is allowed to reach next, so whoever holds this can change the order
        // a curriculum is taught in. Someone who may upload a worksheet is not
        // automatically someone who may re-sequence the syllabus.
        'lms.curriculum' => [
            'label' => 'LMS curriculum',
            'links' => ['lms_curriculum.index', 'course-master/'],
        ],

        /*
        | Platform services — Communication, Scheduler, Workflow.
        |
        | ONE KEY PER SERVICE, NOT PER BUSINESS MODULE. The question these screens
        | ask is "may this person change how the WHOLE INSTITUTE is notified",
        | which is an administrator's right rather than a Fees clerk's. Keying by
        | business module would mean whoever may edit fee reminders may also edit
        | the safeguarding alert, because both sit in the same matrix.
        |
        | The links are created by
        | database/migrations/2026_09_11_100400_add_platform_services_menu_rows.php,
        | which exists precisely so there is a menu row for a grant to live on.
        | Until an administrator grants rights there, these resolve to deny — the
        | screens read and refuse to save, which is the correct failure and not a
        | bug to work around here. This file grants nothing.
        */
        /*
        | AI agents — who may enable and run them, per module.
        |
        | The Automations tab asks for `agents.<module>` and this key was absent, so
        | `PermissionService::check()` could not resolve it, `allow_when_unresolved`
        | denied everybody, and the screen told people to ask an administrator for a
        | right that did not exist. Registering it here creates nothing and grants
        | nobody anything; it only names the menu row a grant is stored on.
        |
        | Per module rather than one key for all agents, because the screen is per
        | module: a school can let a fees clerk switch on a fees agent without also
        | letting them switch on agents elsewhere. `ai_agents` is the fallback for a
        | school that does not want that distinction and grants once at the parent —
        | the same shape platform.eventbus already uses.
        |
        | The rows are created by
        | database/migrations/2026_09_18_130000_add_ai_agents_menu_rows_for_rights.php
        | and, for attendance,
        | database/migrations/2026_09_19_100100_add_attendance_ai_agent_menu_row.php.
        |
        | ADDING A MODULE HERE IS HALF THE JOB. A key registered here with no
        | `tblmenumaster` row behind it resolves to null and denies everybody — which is
        | exactly the failure the Fees screen shipped with. Each entry below must have a
        | migration that creates its `ai_agents.<module>` row, or the parent `ai_agents`
        | row it falls through to must already exist.
        */
        'agents.fees' => [
            'label' => 'AI agents — Fees',
            'links' => ['ai_agents.fees', 'ai_agents'],
        ],

        'agents.attendance' => [
            'label' => 'AI agents — Attendance',
            'links' => ['ai_agents.attendance', 'ai_agents'],
        ],

        /*
        | The keys the Admission and Student AI Stacks' Automations tabs ask for.
        |
        | `agents.admissions` and `agents.students`, plural, because the key is
        | `agents.<ai_modules key>` and those modules are keyed `admissions` and
        | `students` — the same spelling `AGENT_MODULES` in the frontend registry uses,
        | so `rbacModuleKey(module)` and this file cannot drift apart.
        |
        | Their rows are created by
        | database/migrations/2026_09_21_100300_add_admission_and_student_ai_agent_menu_rows.php,
        | which also mirrors whatever grants `ai_agents.fees` carries. Until that has run,
        | both fall through to the `ai_agents` parent, and if that is absent too they
        | resolve to null and deny — which is the correct failure. This file grants nothing.
        */
        'agents.admissions' => [
            'label' => 'AI agents — Admission',
            'links' => ['ai_agents.admissions', 'ai_agents'],
        ],

        'agents.students' => [
            'label' => 'AI agents — Student',
            'links' => ['ai_agents.students', 'ai_agents'],
        ],

        /*
        | The keys the Exam, PTM, Hostel, Student Request and Circular AI Stacks'
        | Automations tabs ask for.
        |
        | Each is `agents.<ai_modules key>`, which is the same spelling `AGENT_MODULES` in
        | the frontend registry uses, so `rbacModuleKey(module)` and this file cannot drift
        | apart. `student_request` and `circular` are singular here because that is how the
        | modules are keyed in `ai_modules`; `agents.students` a few lines up is a different
        | module and is left exactly as it is.
        |
        | Their rows are created by
        | database/migrations/2026_09_22_100300_add_five_module_ai_agent_menu_rows.php,
        | which also mirrors whatever grants `ai_agents.fees` carries. Until that has run
        | they fall through to the `ai_agents` parent, and if that is absent too they
        | resolve to null and deny — which is the correct failure. This file grants nothing.
        */
        'agents.exam' => [
            'label' => 'AI agents — Exam',
            'links' => ['ai_agents.exam', 'ai_agents'],
        ],

        'agents.ptm' => [
            'label' => 'AI agents — PTM',
            'links' => ['ai_agents.ptm', 'ai_agents'],
        ],

        'agents.hostel' => [
            'label' => 'AI agents — Hostel',
            'links' => ['ai_agents.hostel', 'ai_agents'],
        ],

        'agents.student_request' => [
            'label' => 'AI agents — Student request',
            'links' => ['ai_agents.student_request', 'ai_agents'],
        ],

        'agents.circular' => [
            'label' => 'AI agents — Circular',
            'links' => ['ai_agents.circular', 'ai_agents'],
        ],

        /*
        | The keys the six AI Stacks added in 2026-09-23 ask for.
        |
        | `agents.easy_com` is spelled for the `ai_modules` key, not for the menu slug
        | `communication` — the key is always `agents.<ai_modules key>`, which is what
        | `rbacModuleKey(module)` builds in the frontend registry, so the two cannot drift.
        |
        | `agents.student_medical` is registered like the rest and grants nothing by
        | itself. Whether anybody may operate Student Medical AI remains a decision an
        | administrator makes in Group-wise Rights against that row, and the tools behind
        | it carry their own `student_medical.read` permission besides.
        |
        | Their rows are created by
        | database/migrations/2026_09_23_100300_add_six_module_ai_agent_menu_rows.php.
        | Until that has run they fall through to the `ai_agents` parent, and if that is
        | absent too they resolve to null and deny — the correct failure.
        */
        'agents.mobile_apps' => [
            'label' => 'AI agents — Users Mobile Apps',
            'links' => ['ai_agents.mobile_apps', 'ai_agents'],
        ],

        'agents.student_icard' => [
            'label' => 'AI agents — Student I-Card',
            'links' => ['ai_agents.student_icard', 'ai_agents'],
        ],

        'agents.certificate' => [
            'label' => 'AI agents — Certificate',
            'links' => ['ai_agents.certificate', 'ai_agents'],
        ],

        'agents.easy_com' => [
            'label' => 'AI agents — Communication',
            'links' => ['ai_agents.easy_com', 'ai_agents'],
        ],

        'agents.timetable' => [
            'label' => 'AI agents — Time Table',
            'links' => ['ai_agents.timetable', 'ai_agents'],
        ],

        'agents.student_medical' => [
            'label' => 'AI agents — Student Medical',
            'links' => ['ai_agents.student_medical', 'ai_agents'],
        ],

        /*
        | The keys the six AI Stacks added in 2026-09-24 ask for.
        |
        | Two of them are spelled for a key that predates this work: Inward is
        | `agents.inward_outward` and Transport is `agents.transportation`, because those
        | are the `ai_modules` keys those modules have always had and the key is always
        | `agents.<ai_modules key>`. Spelling either of them for the menu slug — `inward`,
        | `transport` — would name a right the frontend never asks for, so the Automations
        | tab would deny everybody on a module that is correctly configured.
        |
        | Their rows are created by
        | database/migrations/2026_09_24_100300_add_six_more_module_ai_agent_menu_rows.php.
        | Until that has run they fall through to the `ai_agents` parent, and if that is
        | absent too they resolve to null and deny — the correct failure.
        */
        'agents.inward_outward' => [
            'label' => 'AI agents — Inward',
            'links' => ['ai_agents.inward_outward', 'ai_agents'],
        ],

        'agents.user_icard' => [
            'label' => 'AI agents — User I-Card',
            'links' => ['ai_agents.user_icard', 'ai_agents'],
        ],

        'agents.petty_cash' => [
            'label' => 'AI agents — Petty Cash',
            'links' => ['ai_agents.petty_cash', 'ai_agents'],
        ],

        'agents.consent' => [
            'label' => 'AI agents — Consent',
            'links' => ['ai_agents.consent', 'ai_agents'],
        ],

        'agents.visitor_management' => [
            'label' => 'AI agents — Visitor Management',
            'links' => ['ai_agents.visitor_management', 'ai_agents'],
        ],

        'agents.transportation' => [
            'label' => 'AI agents — Transport',
            'links' => ['ai_agents.transportation', 'ai_agents'],
        ],

        /*
        | The keys the six AI Stacks added in 2026-09-25 ask for.
        |
        | Four of them are spelled for keys that predate this work — `inventory`,
        | `front_desk`, `document-templates` and `migration-modules` — because the key is
        | always `agents.<ai_modules key>`. Two of those carry a HYPHEN, which is unusual
        | here and is not a typo: `document-templates` and `migration-modules` are spelled
        | that way in `ai_modules` and `rbacModuleKey()` builds the right from the key
        | verbatim. Writing either with an underscore would name a right nobody holds.
        |
        | `agents.migration-modules` is the Utility module's right. The module is keyed
        | `migration-modules` because that row has claimed `/Utility/**` since the
        | workspace was seeded; its menu slug is `utility`.
        |
        | Their rows are created by
        | database/migrations/2026_09_25_100300_add_final_six_module_ai_agent_menu_rows.php.
        | Until that has run they fall through to the `ai_agents` parent, and if that is
        | absent too they resolve to null and deny — the correct failure.
        */
        'agents.inventory' => [
            'label' => 'AI agents — Inventory',
            'links' => ['ai_agents.inventory', 'ai_agents'],
        ],

        'agents.front_desk' => [
            'label' => 'AI agents — Front Desk',
            'links' => ['ai_agents.front_desk', 'ai_agents'],
        ],

        'agents.task_management' => [
            'label' => 'AI agents — Task Management',
            'links' => ['ai_agents.task_management', 'ai_agents'],
        ],

        'agents.complaint' => [
            'label' => 'AI agents — Complaint',
            'links' => ['ai_agents.complaint', 'ai_agents'],
        ],

        'agents.migration-modules' => [
            'label' => 'AI agents — Utility',
            'links' => ['ai_agents.migration-modules', 'ai_agents'],
        ],

        'agents.document-templates' => [
            'label' => 'AI agents — Document Templates',
            'links' => ['ai_agents.document-templates', 'ai_agents'],
        ],

        /*
        | The last six keys, added 2026-09-26. `user`, `sqaa`, `library`, `lms` and
        | `institute` have had `ai_modules` rows since the workspace was seeded and were
        | simply never given a stack; `parent_communication` is new. Their rows are
        | created by 2026_09_26_100300.
        */
        'agents.parent_communication' => [
            'label' => 'AI agents — Parent Communication',
            'links' => ['ai_agents.parent_communication', 'ai_agents'],
        ],

        'agents.sqaa' => [
            'label' => 'AI agents — Quality assurance',
            'links' => ['ai_agents.sqaa', 'ai_agents'],
        ],

        'agents.user' => [
            'label' => 'AI agents — Users',
            'links' => ['ai_agents.user', 'ai_agents'],
        ],

        'agents.library' => [
            'label' => 'AI agents — Library',
            'links' => ['ai_agents.library', 'ai_agents'],
        ],

        'agents.lms' => [
            'label' => 'AI agents — Learning',
            'links' => ['ai_agents.lms', 'ai_agents'],
        ],

        'agents.institute' => [
            'label' => 'AI agents — Institute',
            'links' => ['ai_agents.institute', 'ai_agents'],
        ],

        /*
        | The keys the five LMS + PAL AI Stacks ask for.
        |
        | Their rows are created by
        | database/migrations/2026_09_28_100300_add_lms_pal_module_ai_agent_menu_rows.php,
        | which mirrors whatever grants `ai_agents.fees` carries, the same as every batch
        | above. `teach_learn`, `curriculum_planning`, `engagement`, `interactions` and
        | `new_pal` are the `ai_modules` keys those five migrations use — none of them had
        | an `ai_modules` row before this batch.
        */
        'agents.teach_learn' => [
            'label' => 'AI agents — Teach/Learn',
            'links' => ['ai_agents.teach_learn', 'ai_agents'],
        ],

        'agents.curriculum_planning' => [
            'label' => 'AI agents — Curriculum Planning',
            'links' => ['ai_agents.curriculum_planning', 'ai_agents'],
        ],

        'agents.engagement' => [
            'label' => 'AI agents — Engagement',
            'links' => ['ai_agents.engagement', 'ai_agents'],
        ],

        'agents.interactions' => [
            'label' => 'AI agents — Interactions',
            'links' => ['ai_agents.interactions', 'ai_agents'],
        ],

        'agents.new_pal' => [
            'label' => 'AI agents — New PAL',
            'links' => ['ai_agents.new_pal', 'ai_agents'],
        ],

        'platform.notification' => [
            'label' => 'Platform services — Communication',
            'links' => ['platform_services.notification', 'platform_services'],
        ],

        'platform.scheduler' => [
            'label' => 'Platform services — Scheduler',
            'links' => ['platform_services.scheduler', 'platform_services'],
        ],

        'platform.workflow' => [
            'label' => 'Platform services — Workflow',
            'links' => ['platform_services.workflow', 'platform_services'],
        ],

        /*
        | Platform services — Event Bus.
        |
        | THE FIRST KEY IN THIS FAMILY ON A READ. The three above gate writes;
        | their reads need only a session, because seeing which notifications the
        | product can raise is useful to most staff and harmful to none. Event Bus
        | is different in kind: it reports operational data, and some of it comes
        | from tables with no tenant column. "Who may look" is therefore a real
        | question here, and it is asked on the GET.
        |
        | THIS KEY CANNOT AUTHORISE CROSS-TENANT READS, and nothing should be
        | written here that implies otherwise. PermissionService resolves every
        | grant `where sub_institute_id = ?` (PermissionService.php:183-199), so a
        | right held in one institute says nothing about another. The estate-wide
        | sections of the screen are gated in code on `is_admin === 2` instead —
        | see EventBusController::TIER_2.
        |
        | NO NEW MENU ROW WAS ADDED. `platform_services.event_bus` does not exist
        | in tblmenumaster, so resolution falls through to `platform_services` —
        | the parent row created by
        | 2026_09_11_100400_add_platform_services_menu_rows.php — exactly as the
        | three siblings do when their own child row is absent. A dedicated row
        | can be added later to grant Event Bus separately from the rest of
        | Platform Services; until then a grant on the parent covers all four.
        */
        'platform.eventbus' => [
            'label' => 'Platform services — Event Bus',
            'links' => ['platform_services.event_bus', 'platform_services'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fail-closed
    |--------------------------------------------------------------------------
    |
    | When a module cannot be resolved to a menu row, or the user has no rights row at
    | all, the answer is DENY.
    |
    | This is the check that is commented out at checkPermission.php:66-69 today, which
    | is why a user with no rights row currently falls through to allowed. Turning it on
    | is the substance of tracker row 5 — the rest is plumbing.
    |
    | Set to true only to debug a misconfigured registry, never in production.
    |
    */
    'allow_when_unresolved' => false,

    // Per-request memoisation only. Rights are security-sensitive, so this is not a
    // cross-request cache: Decision #13 puts RBAC at "0-30 sec cache or none", and a
    // stale "yes you're allowed" is the same failure class as the original auth bypass.
    'cache_seconds' => 0,
];
