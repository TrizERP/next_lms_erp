<?php

/*
|--------------------------------------------------------------------------
| LMS content governance vocabulary
|--------------------------------------------------------------------------
|
| Closed sets for the AUTHORING/GOVERNANCE axis of LMS content, kept separate
| from config/pal_content.php on purpose.
|
| config/pal_content.php owns the PEDAGOGY axis: what a piece of content teaches,
| at what Bloom level, in what format, and whether it is fit to serve a learner
| (`quality_status`). Those semantics belong to PAL's delivery engine.
|
| This file owns a different question: WHO authored a thing, WHICH tenant owns it,
| and WHO may see it. Mixing the two would force the legacy Blade content library
| to grow PAL delivery semantics it has no use for, and would make `quality_status`
| do two unrelated jobs.
|
| Every value here is a CLOSED set. An unregistered value is a write failure, not
| a new category — the same discipline as config/pal_content.php. Validation lives
| in App\Services\lms\Content\LmsContentVocabulary (lowercase `lms` - it must match the
| physical directory, because PSR-4 on the Linux host is case-sensitive).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | The platform tenant
    |--------------------------------------------------------------------------
    |
    | `sub_institute_id = 1` is the shared curriculum layer every LMS-enabled
    | school reads ON TOP OF its own content. This is not a guess: it is what the
    | read path already does, in two places —
    |
    |   ApiLmsCourseController::getChapterContentCategories():329
    |       ->where('content_master.sub_institute_id', '1')
    |       ->orWhere('content_master.sub_institute_id', $sub_institute_id)
    |       ... guarded by school_setup.is_Lms = 'Y'
    |
    |   ApiLmsCourseController::chapterContent():390
    |       ->orWhere('sub_institute_id', 1)     // on chapter_master
    |
    | Measured 2026-09-07 on vivek_erp: content_master holds 31,385 rows across
    | 6 tenants (1 => 16,379; 195 => 14,948; then 76/341/47/319 in the tens), and
    | there is NO sub_institute_id = 0 row anywhere in it. So 0 is not the platform
    | marker in this estate, despite being the convention in the pal_* sidecars.
    |
    | Configured rather than hardcoded so a second platform tenant (or a migration
    | to 0) is a config change, not a code change.
    |
    */
    'platform_sub_institute_ids' => [1],

    /*
    |--------------------------------------------------------------------------
    | Ownership — tracker "Content & LMS Architecture" row 4, Decision #37
    |--------------------------------------------------------------------------
    |
    | "Every content item tagged by provenance, so a teacher with creation rights
    |  sees default content PLUS their own additions layered on top — never a fork
    |  that replaces the default."
    |
    | `layer` is what the API returns to the frontend so it can badge an item
    | without re-deriving ownership client-side (which is how the Classroom/Teacher
    | split ended up as a string match in page.tsx:544).
    |
    */
    'ownership' => [
        'platform' => ['label' => 'Platform',        'layer' => 'platform'],
        'school'   => ['label' => 'School-authored', 'layer' => 'school'],
        'teacher'  => ['label' => 'Teacher-authored','layer' => 'mine'],
    ],

    /*
    |--------------------------------------------------------------------------
    | How a thing came to exist
    |--------------------------------------------------------------------------
    |
    | `imported` covers the pre-provenance estate: rows that existed before this
    | table did and whose real authoring mode is unknowable. Recording that
    | honestly is better than defaulting 31,385 rows to 'manual' and inventing
    | a fact.
    |
    */
    'authoring_modes' => ['generate', 'upload', 'manual', 'imported'],

    /*
    |--------------------------------------------------------------------------
    | Visibility
    |--------------------------------------------------------------------------
    |
    | Deliberately NOT a permission. Decision #23: "configuration can never grant
    | a permission." Visibility only ever narrows what RBAC already allows; it can
    | never widen it. The server-side permission check is authoritative.
    |
    */
    'visibility' => ['global', 'tenant', 'self'],

    'statuses' => ['active', 'archived'],

    /*
    |--------------------------------------------------------------------------
    | H5P as a format, not a peer category - tracker row 2, Decision #35
    |--------------------------------------------------------------------------
    |
    | `surface_in_content_list` merges the h5p_* estate into the chapter content
    | list under the "H5P Interactive" bucket, so H5P can be reached as a FILTER
    | inside Classroom Resource and Teacher Workspace instead of as a 4th
    | top-level destination.
    |
    | Turning this off restores the previous behaviour exactly (H5P reachable only
    | via its own /h5p/* routes) without a code change or a redeploy. That is
    | deliberate: a demo cadence is live, and this is the one change in Phase A2
    | that is visible on a screen a customer may be shown.
    |
    */
    'h5p' => [
        'surface_in_content_list' => env('LMS_H5P_IN_CONTENT_LIST', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content API authentication rollout - tracker row 5, Decision #37
    |--------------------------------------------------------------------------
    |
    | The content endpoints have no authentication today: routes/api.php runs under the
    | `api` group, which is throttle + SubstituteBindings only (app/Http/Kernel.php:45-50,
    | Sanctum commented out). The entire content area of the Next.js frontend also calls
    | Laravel with a bare fetch() and NO Authorization header.
    |
    | Enforcing on day one would therefore black out the content screens for 56 tenants.
    | So `lms.auth` and `perm:` both start in WARN-ONLY mode: an unauthenticated or
    | unverified request is logged to the daily channel and allowed through.
    |
    | Sequence to enforce:
    |   1. migrate the frontend content calls onto lib/erp-client.ts (Bearer token)
    |   2. deploy, and watch the daily log for "lms.auth: unauthenticated" entries
    |   3. flip LMS_API_AUTH_ENFORCE=true only once that count is zero
    |
    */
    'api_auth_enforce' => env('LMS_API_AUTH_ENFORCE', false),

    /*
    |--------------------------------------------------------------------------
    | Authoring types - tracker row 3, Decision #36
    |--------------------------------------------------------------------------
    |
    | "One Generate + Upload capability, reused contextually across Classroom Resource,
    |  Teacher Resource, and Question Bank - not three separately-built creation flows."
    |
    | This registry is what makes ONE endpoint serve all three: `content_type` selects the
    | row below, and the row decides which estate is written, which permission is required,
    | and which modes are legal. Adding a fourth authoring type is a config entry, not a
    | new controller.
    |
    | `entity_type` matches the provenance registry above, so every authored item gets an
    | ownership row through the same write path.
    |
    | `upload_mimes` is per type on purpose. The mobile writer
    | (teacherapiController.php:343) accepts pdf,mp3,mp4,html,jpg,jpeg,png,link while the
    | web upload accepts only pdf,ppt,pptx. A single hardcoded list would break one of
    | them; a per-type list is the mechanism that eventually lets both share this endpoint.
    |
    */
    'authoring_types' => [

        'presentation' => [
            'label'         => 'Presentation',
            'entity_type'   => 'content',
            'category'      => 'Classroom Presentation',
            'permission'    => 'lms.content',
            'modes'         => ['generate', 'upload'],
            'provider'      => 'gamma',
            'upload_mimes'  => ['pdf', 'ppt', 'pptx'],
        ],

        'teacher_training' => [
            'label'         => 'Teacher training presentation',
            'entity_type'   => 'content',
            'category'      => 'Teacher Training',
            'permission'    => 'lms.content',
            'modes'         => ['generate', 'upload'],
            'provider'      => 'gamma',
            'upload_mimes'  => ['pdf', 'ppt', 'pptx'],
        ],

        'revision_notes' => [
            'label'         => 'Revision Notes',
            'entity_type'   => 'content',
            'category'      => 'Revision Notes',
            'permission'    => 'lms.content',
            'modes'         => ['generate', 'upload'],
            'provider'      => 'gemini',
            'upload_mimes'  => ['pdf', 'doc', 'docx'],
        ],

        'classroom_activity' => [
            'label'         => 'Classroom Activity',
            'entity_type'   => 'content',
            'category'      => 'Classroom Activity',
            'permission'    => 'lms.content',
            'modes'         => ['generate', 'upload'],
            'provider'      => 'gemini',
            'upload_mimes'  => ['pdf', 'doc', 'docx'],
        ],

        'video' => [
            'label'         => 'Recorded Videos',
            'entity_type'   => 'content',
            'category'      => 'Recorded Videos',
            'permission'    => 'lms.content',
            // No generator for video - declaring it would promise something no provider
            // can deliver, and the endpoint rejects an unsupported mode explicitly.
            'modes'         => ['upload'],
            'provider'      => null,
            'upload_mimes'  => ['mp4', 'mov', 'webm', 'mp3'],
        ],

        'question' => [
            'label'         => 'Question bank item',
            'entity_type'   => 'question',
            'category'      => null,
            'permission'    => 'lms.question_bank',
            'modes'         => ['generate'],
            'provider'      => 'question',
            'upload_mimes'  => [],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Entity types this sidecar covers
    |--------------------------------------------------------------------------
    |
    | One provenance table spanning every content estate, because the "3-way split"
    | is three unrelated tables with no shared field (see
    | docs/decisions/2026-09-07-chapter-resource-split.md). Solving ownership per
    | table would mean solving it three times and reconciling three answers.
    |
    | `table` and `key` are declared here so the backfill and the read path never
    | hardcode a table name.
    |
    */
    'entity_types' => [
        'content' => [
            'table' => 'content_master',
            'key'   => 'id',
            'label' => 'Chapter content',
        ],
        'teacher_resource' => [
            'table' => 'lms_teacher_resource',
            'key'   => 'id',
            'label' => 'Teacher resource',
        ],
        'question' => [
            'table' => 'lms_question_master',
            'key'   => 'id',
            'label' => 'Question bank item',
        ],
        'h5p' => [
            'table' => 'h5p_scenarios',
            'key'   => 'id',
            'label' => 'H5P interactive',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Teacher-profile detection for the backfill
    |--------------------------------------------------------------------------
    |
    | content_master.user_profile_name is populated on only 54 of 31,385 rows
    | (Admin 50, "LMS Teache" 2, student 2 — note the TRUNCATED value: the pattern is
    | 'teache', not 'teacher', because the stored string is cut short). Everything else is
    | blank, so this signal classifies a handful of rows and the rest fall through
    | to the tenant rule. That is the correct outcome: inventing a teacher for
    | 31,331 rows with no author signal would be worse than recording 'school'.
    |
    */
    'teacher_profile_patterns' => ['teache', 'faculty', 'lecturer'],

];
