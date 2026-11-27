<?php

/**
 * One entry per role dashboard: which profile names open it, and which menus that role
 * needs to do its job.
 *
 * `rights` is a list of [menu_id, flags, descendants]:
 *   - flags: any of v (view) a (add) e (edit) d (delete)
 *   - descendants: true also applies the same flags to every active menu under it
 *
 * Applied ADDITIVELY by `roles:sync-dashboard-rights` — a flag a role already has is never
 * removed, and a menu is never revoked. Menu ids are the ones in `tblmenumaster`.
 *
 * Main menu ids: 259 Student · 6 Fees · 265 Attendance · 13 Communication · 434 Timetable ·
 * 67 Exam · 173 Admission · 10 Inward Outward · 102 Circular · 99 Parent Communication ·
 * 258 PTM · 244 Visitor · 257 Inventory · 887 Library · 255 Front Desk · 253 Task ·
 * 254 Complaint · 146 Petty Cash · 18 Mobile Apps · 4 Reports · 230 LMS+PAL ·
 * 455 Career Counseling · 54 Exam (Master) · 130 Inventory (Master) · 358 Library (Master) ·
 * 866 Signals · 869 Intelligence Workspace · 871 Executive Dashboard · 872 Decision Analytics · 873 Decision Intelligence (Enterprise Brain).
 */
return [
    // Profile names whose dashboard is handled by the existing Admin / Teacher / Student screens.
    // Listed so the resolver and the sync command agree on every name.
    'roles' => [
        'admin' => [
            'label' => 'Admin',
            'profiles' => ['super admin', 'admin', 'school admin'],
            'rights' => [], // already hold their rights; this command never changes them
        ],
        'assistant_admin' => [
            'label' => 'Assistant Admin',
            'profiles' => ['assistant admin'],
            'rights' => [
                [259, 'vae', true], [7, 'vae', false], [160, 'vae', false], [316, 'vae', false],
                [265, 'vae', true], [13, 'va', true], [434, 'v', true], [67, 'v', true], [173, 'vae', true],
                [10, 'vae', true], [102, 'vae', false], [99, 'vae', false], [258, 'v', true], [244, 'vae', true],
                [255, 'vae', true], [253, 'vae', true], [254, 'vae', true], [18, 'v', true], [91, 'v', true], [36, 'v', true], [268, 'v', true], [299, 'v', true], [72, 'v', true], [309, 'v', true],
            ],
        ],
        'principal' => [
            'label' => 'Principal',
            'profiles' => ['principal'],
            'rights' => [
                [866, 'v', false], [869, 'v', false], [871, 'v', false], [872, 'v', false], [873, 'v', false], // Enterprise Brain: signals, workspace and analytics, view only
                [259, 'v', true], [265, 'v', true], [67, 'v', true], [173, 'v', true],
                [434, 'v', true], [102, 'vae', false], [99, 'v', false], [13, 'va', true], [18, 'v', true],
                [258, 'v', true], [518, 'v', false], [427, 'v', false], [975, 'v', false], [91, 'v', true], [36, 'v', true], [268, 'v', true], [299, 'v', true], [72, 'v', true], [309, 'v', true], [254, 'v', true], [255, 'v', true],
            ],
        ],
        'vice_principal' => [
            'label' => 'Vice Principal',
            'profiles' => ['vice principal'],
            'rights' => [
                [866, 'v', false], [869, 'v', false], [871, 'v', false], [872, 'v', false], [873, 'v', false], // Enterprise Brain: signals, workspace and analytics, view only
                [259, 'v', true], [265, 'v', true], [67, 'v', true], [173, 'v', true],
                [434, 'v', true], [102, 'vae', false], [99, 'v', false], [13, 'va', true], [18, 'v', true],
                [258, 'v', true], [518, 'v', false], [427, 'v', false], [975, 'v', false], [91, 'v', true], [36, 'v', true], [268, 'v', true], [299, 'v', true], [72, 'v', true], [309, 'v', true], [254, 'v', true], [255, 'v', true],
            ],
        ],
        'teacher' => [
            'label' => 'Teacher',
            'profiles' => ['teacher', 'lms teacher'],
            'rights' => [], // already hold their rights
        ],
        'counsellor' => [
            'label' => 'Counsellor',
            'profiles' => ['counseller', 'counsellor', 'counselor'],
            'rights' => [
                [80, 'v', false], [85, 'v', false], [86, 'v', false], [87, 'v', false], [88, 'v', false],
                [89, 'v', false], [99, 'vae', false], [13, 'v', true], [455, 'vae', true], [265, 'v', true],
                [67, 'v', true], [91, 'v', true], [102, 'v', false],
            ],
        ],
        'clerk' => [
            'label' => 'Clerk',
            'profiles' => ['clerk', 'jr. clerk', 'sr.clerk'],
            'rights' => [
                [259, 'vae', true], [7, 'vae', false], [160, 'vae', false], [316, 'vae', false], [32, 'v', false],
                [8, 'v', false], [317, 'v', false], [173, 'vae', true], [265, 'v', true], [13, 'v', true],
                [102, 'v', false], [255, 'vae', true], [244, 'vae', true], [10, 'vae', true], [146, 'vae', true],
                [36, 'v', true], [91, 'v', true], [268, 'v', true],
            ],
        ],
        'assistant_clerk' => [
            'label' => 'Assistant Clerk',
            'profiles' => ['assistant clerk'],
            'rights' => [
                [80, 'v', false], [81, 'va', false], [85, 'v', false], [7, 'va', false], [316, 'va', false],
                [173, 'vae', true], [255, 'vae', true], [244, 'va', true], [10, 'va', true], [102, 'v', false],
                [36, 'v', true],
            ],
        ],
        'librarian' => [
            'label' => 'Librarian',
            'profiles' => ['librarian', 'librerian'],
            'rights' => [
                [887, 'vae', true], [358, 'vae', true], [80, 'v', false], [102, 'v', false],
            ],
        ],
        'store_keeper' => [
            'label' => 'Store Keeper',
            'profiles' => ['store keeper', 'store-keeper'],
            'rights' => [
                [257, 'vae', true], [130, 'vae', true], [303, 'v', true], [102, 'v', false],
            ],
        ],
        'exam_department' => [
            'label' => 'Exam Department',
            'profiles' => ['exam department'],
            'rights' => [
                [67, 'vae', true], [54, 'vae', true], [72, 'v', true], [80, 'v', false], [265, 'v', true],
                [141, 'v', false], [102, 'v', false],
            ],
        ],
        'trustee' => [
            'label' => 'Trustee',
            'profiles' => ['trustee'],
            'rights' => [
                [866, 'v', false], [869, 'v', false], [871, 'v', false], [872, 'v', false], [873, 'v', false], // Enterprise Brain: signals, workspace and analytics, view only
                [36, 'v', true], [91, 'v', true], [268, 'v', true], [72, 'v', true], [299, 'v', true],
                [102, 'v', false],
            ],
        ],
        'peon' => [
            'label' => 'Peon',
            'profiles' => ['peon'],
            'rights' => [
                [102, 'v', false], [253, 'vae', false], [140, 'vae', false], [20, 'v', false], [255, 'v', false],
            ],
        ],
        'student' => [
            'label' => 'Student',
            'profiles' => ['student'],
            'rights' => [], // already hold their rights
        ],
    ],

    /*
     * Which Enterprise Brain signals each role's dashboard may show. `topics` are substrings matched against
     * the signal's classification (null = every topic); `min_severity` hides anything quieter. A role that is
     * not listed gets no signals. This is a relevance filter on top of the role's own widget list, not a grant:
     * opening the Brain screens still needs the role's menu rights.
     */
    'signals' => [
        'admin' => ['topics' => null, 'min_severity' => 'low'],
        'assistant_admin' => ['topics' => null, 'min_severity' => 'low'],
        'principal' => ['topics' => null, 'min_severity' => 'low'],
        'vice_principal' => ['topics' => null, 'min_severity' => 'low'],
        // Trustees see material issues only, not operational noise.
        'trustee' => ['topics' => null, 'min_severity' => 'high'],
        'counsellor' => ['topics' => ['student', 'attendance', 'engagement', 'result', 'academic'], 'min_severity' => 'low'],
        'clerk' => ['topics' => ['collection', 'fees', 'admission', 'visitor', 'communication', 'data_quality'], 'min_severity' => 'low'],
        'assistant_clerk' => ['topics' => ['collection', 'fees', 'admission', 'visitor', 'communication', 'data_quality'], 'min_severity' => 'low'],
        'librarian' => ['topics' => ['library'], 'min_severity' => 'low'],
        'store_keeper' => ['topics' => ['inventory', 'transport', 'purchase'], 'min_severity' => 'low'],
        'exam_department' => ['topics' => ['result', 'academic', 'teach-learn', 'homework', 'exam'], 'min_severity' => 'low'],
        // School-wide context for teaching; the Brain's signals are not per class, so a teacher sees only the teaching ones.
        'teacher' => ['topics' => ['homework', 'academic', 'teach-learn', 'engagement', 'result'], 'min_severity' => 'medium'],
    ],
];
