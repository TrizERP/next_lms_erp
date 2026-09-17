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
