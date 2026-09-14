<?php

/*
|--------------------------------------------------------------------------
| Document module — source registry
|--------------------------------------------------------------------------
|
| The Document module is a READ-ONLY AGGREGATION LAYER. It owns no table, no
| upload path and no delete path. Every row it shows is selected from a table
| another module already writes, and every URL it builds is the same URL that
| module's own screen already builds.
|
| WHY A REGISTRY RATHER THAN A CONTROLLER FULL OF QUERIES
| There are a dozen-plus document sources and they agree on nothing: the title is
| `document_title` here, `name` there and `title` elsewhere; the file lives in a
| bare filename column in one table, a disk-relative path in another, and a
| fully-resolved URL in a third. Writing that out once per source in a controller
| would make adding the next one a copy-paste. Declaring it here means the
| controller reads one shape, and a new source is an entry in this file.
|
| NOTHING HERE GRANTS ACCESS. Every query the controller builds from this file is
| scoped to the tenant on the verified JWT (see routes/documents.php). A table
| with no tenant column cannot be scoped and is therefore deliberately absent
| from this list rather than silently served unscoped.
|
| `storage.kind` says how to turn the file column into something openable:
|   spaces   — bare filename; prefix + filename on the Spaces bucket
|   local    — path relative to the `public` disk; served from APP_URL/storage
|   absolute — the column already holds a full URL; use it verbatim
|
| These mirror what the owning module does today. They are NOT a new convention,
| and correcting them is explicitly out of scope: this layer must not change how
| any existing file is addressed.
|
*/

return [

    /*
    | Public base for the DigitalOcean Spaces bucket, derived from the same env
    | the `digitalocean` disk uses so this file never carries a hostname of its
    | own and a bucket move does not need an edit here.
    */
    'spaces_base' => rtrim(env(
        'DO_SPACES_URL',
        'https://' . env('DO_SPACES_BUCKET', 's3-triz') . '.' . env('DO_SPACES_REGION', 'fra1') . '.digitaloceanspaces.com'
    ), '/'),

    /** Rows per page. A caller may ask for fewer, never for more than the max. */
    'page_size' => 25,
    'max_page_size' => 100,

    /*
    |--------------------------------------------------------------------------
    | Who may open the Document module
    |--------------------------------------------------------------------------
    |
    | This module lists every document in the institute in one place. That is
    | useful to the people who administer the institute and nobody else: a
    | student reaching it would be reading other students' identity and medical
    | records, and every member of staff's payslip.
    |
    | WHY AN ALLOWLIST HERE RATHER THAN tblgroupwise_rights
    | Rights in this ERP hang off a tblmenumaster row, and this module has none —
    | creating one is a migration, which is out of scope. So the gate is a named
    | list of profiles, checked against the profile on the VERIFIED token. This
    | is a narrower mechanism than the rights tables, deliberately: it can only
    | ever deny, it grants nothing that a menu row would later grant, and when a
    | menu row does exist this list is replaced by `perm:document.*` rather than
    | added to.
    |
    | FAIL CLOSED. An unrecognised profile, an empty profile, or any student
    | session is denied. Names are matched after lowercasing, trimming, and
    | collapsing underscores/whitespace — live data holds "ADMIN", "Admin",
    | "PRINCIPAL " (trailing space) and "collage_admin" for the same roles.
    |
    | Extend this list to grant another profile access; it needs no code change.
    */
    'access' => [

        /*
        | is_admin 1 or 2. HydratesLegacyApiSession already labels these
        | sessions "Super Admin" regardless of their profile row, so they would
        | pass the name check anyway — this makes the intent explicit rather
        | than dependent on that labelling.
        */
        'allow_super_admin' => true,

        /** Normalised profile names permitted to open the module. */
        'profiles' => [
            'super admin',
            'admin',
            'school admin',
            'college admin',
            'collage admin',
            'principal',
            'vice principal',
            'hr',
            'hr admin',
            'hr manager',
        ],
    ],

    /*
    | Dashboard grouping. Each domain is one card on /documents.
    */
    'domains' => [
        'student' => [
            'label' => 'Student documents',
            'icon' => 'graduation-cap',
            'description' => 'Admission, identity and certificate records held against a student.',
        ],
        'staff' => [
            'label' => 'Staff documents',
            'icon' => 'briefcase',
            'description' => 'Employment records, onboarding paperwork and staff certificates.',
        ],
        'academic' => [
            'label' => 'Academic resources',
            'icon' => 'book-open',
            'description' => 'Homework, assignments, classwork, syllabus and teacher resources.',
        ],
        'finance' => [
            'label' => 'Finance documents',
            'icon' => 'receipt-indian-rupee',
            'description' => 'Uploaded result sheets and finance records.',
        ],
        'circular' => [
            'label' => 'Circulars and notices',
            'icon' => 'megaphone',
            'description' => 'Circulars and announcements issued to parents and staff.',
        ],
        'compliance' => [
            'label' => 'Compliance and quality',
            'icon' => 'shield-check',
            'description' => 'Compliance library entries and SQAA evidence.',
        ],
    ],

    /*
    | The sources themselves.
    |
    | `owner` is optional and purely presentational — it names the column holding
    | the id of whoever the document belongs to, so the table can show it. This
    | layer does NOT join to the owner table: a join would multiply the query
    | cost across every source for a label, and the owning module's own screen
    | (`route`) already shows the resolved name.
    |
    | `route` is where "Open in <module>" sends the user — the existing screen,
    | unchanged. This module never becomes the place you manage the record.
    */
    'sources' => [

        'student_documents' => [
            'label' => 'Student documents',
            'domain' => 'student',
            'table' => 'tblstudent_document',
            'tenant_column' => 'sub_institute_id',
            'columns' => [
                'id' => 'id',
                'title' => 'document_title',
                'file' => 'file_name',
                'created_at' => 'created_on',
                'owner' => 'student_id',
            ],
            'storage' => ['kind' => 'spaces', 'prefix' => 'public/student_document/'],
            'route' => '/students/student_documents',
        ],

        'staff_documents' => [
            'label' => 'Staff documents',
            'domain' => 'staff',
            'table' => 'staff_document',
            'tenant_column' => 'sub_institute_id',
            'columns' => [
                'id' => 'id',
                'title' => 'document_title',
                'file' => 'file_name',
                'created_at' => 'created_at',
                'owner' => 'user_id',
            ],
            /*
            | KNOWN AMBIGUITY, DELIBERATELY NOT RESOLVED HERE.
            | EmployeeDirectoryController writes this file to the local `public`
            | disk, while FileController's bulk download reads it from Spaces and
            | the employee screen reads it from Spaces under a third folder name.
            | This layer must not change how any file is addressed, so it follows
            | the reader that covers the legacy estate and reports the mismatch to
            | the owning team rather than silently picking a winner.
            */
            'storage' => ['kind' => 'spaces', 'prefix' => 'public/staff_document/'],
            'route' => '/organization-management/employee-directory',
        ],

        'onboarding_documents' => [
            'label' => 'Onboarding documents',
            'domain' => 'staff',
            'table' => 'talent_onboarding_documents',
            'tenant_column' => 'sub_institute_id',
            'soft_delete' => true,
            'columns' => [
                'id' => 'id',
                'title' => 'title',
                'file' => 'file_path',
                'file_name' => 'file_name',
                'status' => 'status',
                'created_at' => 'created_at',
                'owner' => 'journey_id',
            ],
            'storage' => ['kind' => 'local', 'prefix' => ''],
            'route' => '/talent-management/onboarding',
        ],

        'homework' => [
            'label' => 'Homework attachments',
            'domain' => 'academic',
            'table' => 'homework',
            'tenant_column' => 'sub_institute_id',
            'syear_column' => 'syear',
            'columns' => [
                'id' => 'id',
                'title' => 'title',
                'file' => 'image',
                'created_at' => 'created_on',
                'owner' => 'student_id',
            ],
            /*
            | LOCAL, NOT SPACES. StudentHomeworkApiController:143 writes with
            | `$file->storeAs('public/student/', ...)` on the DEFAULT disk, so
            | "public/" there is storage/app/public and the file is served from
            | APP_URL/storage/student/. Reading it off Spaces resolved nothing.
            */
            'storage' => ['kind' => 'local', 'prefix' => 'student/'],
            'route' => '/lms/homework',
        ],

        'assignments' => [
            'label' => 'Assignment submissions',
            'domain' => 'academic',
            'table' => 'lms_assignment',
            'tenant_column' => 'sub_institute_id',
            'syear_column' => 'syear',
            'columns' => [
                'id' => 'id',
                'title' => 'title',
                'file' => 'submission_image',
                'created_at' => 'created_on',
                'owner' => 'student_id',
            ],
            'storage' => ['kind' => 'local', 'prefix' => 'lms_assignment_submission/'],
            'route' => '/lms/lmsAssignment_submission',
        ],

        'classwork' => [
            'label' => 'Classwork attachments',
            'domain' => 'academic',
            'table' => 'classwork_attachment',
            'tenant_column' => 'sub_institute_id',
            'syear_column' => 'syear',
            'soft_delete' => true,
            'columns' => [
                'id' => 'id',
                'title' => 'title',
                'file' => 'file_path',
                'created_at' => 'created_at',
                'owner' => 'student_id',
            ],
            /* file_path already holds a resolved Storage::url() value. */
            'storage' => ['kind' => 'absolute', 'prefix' => ''],
            'route' => '/front_desk',
        ],

        'teacher_resources' => [
            'label' => 'Teacher resources',
            'domain' => 'academic',
            'table' => 'lms_teacher_resource',
            'tenant_column' => 'sub_institute_id',
            'syear_column' => 'syear',
            'columns' => [
                'id' => 'id',
                'title' => 'title',
                'file' => 'file_name',
                'created_at' => 'created_on',
            ],
            'storage' => ['kind' => 'spaces', 'prefix' => 'public/lms_teacher_resource/'],
            'route' => '/lms/teacher_resource',
        ],

        'syllabus' => [
            'label' => 'Syllabus',
            'domain' => 'academic',
            'table' => 'syllabus',
            'tenant_column' => 'sub_institute_id',
            'syear_column' => 'syear',
            'columns' => [
                'id' => 'id',
                'title' => 'title',
                'file' => 'file_name',
                'created_at' => 'created_at',
            ],
            'storage' => ['kind' => 'spaces', 'prefix' => 'public/syllabus/'],
            'route' => '/lms/syllabus',
        ],

        'upload_result' => [
            'label' => 'Uploaded result sheets',
            'domain' => 'finance',
            'table' => 'upload_result',
            'tenant_column' => 'sub_institute_id',
            'syear_column' => 'syear',
            'columns' => [
                'id' => 'id',
                'title' => 'file_name',
                'file' => 'file_name',
                'created_at' => 'created_on',
                'owner' => 'student_id',
            ],
            'storage' => ['kind' => 'spaces', 'prefix' => 'public/upload_result/'],
            'route' => '/result/upload-result',
        ],

        'circulars' => [
            'label' => 'Circulars',
            'domain' => 'circular',
            'table' => 'circular',
            'tenant_column' => 'sub_institute_id',
            'syear_column' => 'syear',
            'columns' => [
                'id' => 'id',
                'title' => 'title',
                'file' => 'file_name',
                'created_at' => 'created_at',
            ],
            /*
            | LOCAL, NOT SPACES. circularController:286 writes with
            | `$file_data->storeAs('public/circular/', ...)` on the default disk.
            */
            'storage' => ['kind' => 'local', 'prefix' => 'circular/'],
            'route' => '/fees/circulars',
        ],

        'announcements' => [
            'label' => 'Announcements',
            'domain' => 'circular',
            'table' => 'announcement',
            'tenant_column' => 'sub_institute_id',
            'syear_column' => 'syear',
            'columns' => [
                'id' => 'id',
                'title' => 'title',
                'file' => 'attachment',
                'created_at' => 'created_at',
            ],
            'storage' => ['kind' => 'spaces', 'prefix' => 'public/announcements/'],
            'route' => '/easy_com/notification_report',
        ],

        'compliance_library' => [
            'label' => 'Compliance library',
            'domain' => 'compliance',
            'table' => 'org_compliance_library',
            'tenant_column' => 'sub_institute_id',
            'soft_delete' => true,
            'columns' => [
                'id' => 'id',
                'title' => 'name',
                'file' => 'attachment',
                'created_at' => 'created_at',
            ],
            /*
            | LOCAL, NOT SPACES. ComplianceLibraryController:217 — the controller
            | the compliance screen actually uses — writes with
            | `Storage::disk('public')->putFileAs('compliance_library', ...)`.
            | A legacy instituteDetailController path did write the same folder to
            | Spaces; rows old enough to have come from it will not resolve here,
            | which is a data-era split this layer reports rather than papers over.
            */
            'storage' => ['kind' => 'local', 'prefix' => 'compliance_library/'],
            'route' => '/organization-management/compliance-library',
        ],

        'sqaa_documents' => [
            'label' => 'SQAA evidence',
            'domain' => 'compliance',
            'table' => 'sqaa_documents',
            'tenant_column' => 'sub_institute_id',
            'columns' => [
                'id' => 'id',
                'title' => 'title',
                'file' => 'file',
                'created_at' => 'created_at',
            ],
            'storage' => ['kind' => 'spaces', 'prefix' => 'public/sqaa/'],
            'route' => '/sqaa_document_report',
        ],
    ],
];
