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
