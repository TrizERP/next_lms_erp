<?php

/**
 * The screens the mobile app can open natively, and which role starts with which.
 *
 * `key` is the app's own screen name (what `lookupScreen` in the Flutter app resolves), so a module
 * listed here always has a native screen behind it. Which modules a role actually gets is stored per
 * school in `mobile_role_modules` and edited in Roles & Access -> Mobile app; `defaults` below only
 * seeds those rows (php artisan mobile:sync-app-modules, dry run unless --apply).
 *
 * audience `student` = the Student/Parent app, `staff` = Teacher, Admin and every other staff role.
 */
return [
    'groups' => [
        'student' => ['Learning', 'Academics', 'Attendance & Leave', 'Communication', 'Fees & Documents', 'Health', 'Transport & Hostel', 'School'],
        'staff' => ['My Class', 'Students', 'Communication', 'Admissions', 'Fees', 'Front Office', 'My Work'],
    ],

    'modules' => [
        // ---------------------------------------------------------------- student / parent
        'lms_subject' => ['Subjects', 'Learning', 'student'],
        'lms_pal' => ['Adaptive Learning (PAL)', 'Learning', 'student'],
        'virtual_classroom' => ['Virtual Classroom', 'Learning', 'student'],
        'social_collabrative' => ['Social & Collaborative', 'Learning', 'student'],
        'leaderboard' => ['Leaderboard', 'Learning', 'student'],
        'portfolio' => ['Portfolio', 'Learning', 'student'],

        'assignment' => ['Assignments', 'Academics', 'student'],
        'home_work' => ['Homework', 'Academics', 'student'],
        'time_table' => ['Timetable', 'Academics', 'student'],
        'exam_schedule' => ['Exam Schedule', 'Academics', 'student'],
        'result_pdf' => ['Results', 'Academics', 'student'],
        'wrt_progress' => ['Progress', 'Academics', 'student'],
        'classwork_gallery' => ['Classwork Gallery', 'Academics', 'student'],

        'student_attendance' => ['Attendance', 'Attendance & Leave', 'student'],
        'academic_calendar' => ['Academic Calendar', 'Attendance & Leave', 'student'],
        'holiday_list' => ['Holidays', 'Attendance & Leave', 'student'],
        'leave_application' => ['Leave', 'Attendance & Leave', 'student'],

        'notification_hub' => ['Notifications', 'Communication', 'student'],
        'circular_events' => ['Circulars & Events', 'Communication', 'student'],
        'parent_communication' => ['Messages', 'Communication', 'student'],
        'child_remark' => ['Remarks', 'Communication', 'student'],

        'fees' => ['Fees', 'Fees & Documents', 'student'],
        'student_certificate' => ['Certificates', 'Fees & Documents', 'student'],
        'achievement_certificate' => ['Achievement Certificates', 'Fees & Documents', 'student'],

        'health_details' => ['Health', 'Health', 'student'],
        'height_weight' => ['Height & Weight', 'Health', 'student'],
        'student_infirmary' => ['Infirmary', 'Health', 'student'],
        'student_vaccination' => ['Vaccination', 'Health', 'student'],

        'student_transport' => ['Transport', 'Transport & Hostel', 'student'],
        'student_hostel' => ['Hostel', 'Transport & Hostel', 'student'],
        'student_consent' => ['Consent', 'Transport & Hostel', 'student'],

        'photos_gallery' => ['Photo Gallery', 'School', 'student'],
        'video_gallery' => ['Video Gallery', 'School', 'student'],
        'about_us' => ['About Us', 'School', 'student'],
        'principal_desk' => ["Principal's Desk", 'School', 'student'],
        'school_information' => ['School Information', 'School', 'student'],
        'achievement' => ['School Achievements', 'School', 'student'],
        'school_timing' => ['School Timing', 'School', 'student'],
        'rules' => ['Rules', 'School', 'student'],
        'facility' => ['Facilities', 'School', 'student'],
        'academic_activity' => ['Academic Activities', 'School', 'student'],
        'reach_us' => ['Reach Us', 'School', 'student'],

        // ------------------------------------------------------------- teacher / admin / staff
        't_take_attendance' => ['Take Attendance', 'My Class', 'staff'],
        't_homework' => ['Homework', 'My Class', 'staff'],
        't_marks' => ['Marks Entry', 'My Class', 'staff'],
        't_timetable' => ['Timetable', 'My Class', 'staff'],
        't_proxy' => ['Proxy Periods', 'My Class', 'staff'],
        't_teach' => ['Teach', 'My Class', 'staff'],
        't_teacher_dairy' => ['Teacher Diary', 'My Class', 'staff'],
        't_teacher_resource' => ['Resources', 'My Class', 'staff'],
        't_virtual_classroom' => ['Virtual Classroom', 'My Class', 'staff'],
        't_social_collobrative' => ['Social & Collaborative', 'My Class', 'staff'],
        't_wrt_progress' => ['Student Progress', 'My Class', 'staff'],
        't_exam_schedule' => ['Exam Schedule', 'My Class', 'staff'],
        't_calander' => ['Calendar', 'My Class', 'staff'],

        't_student_profile' => ['Student Profiles', 'Students', 'staff'],
        't_teacher_profile' => ['Staff Profiles', 'Students', 'staff'],
        't_disclipline' => ['Discipline', 'Students', 'staff'],
        't_capture_attendance' => ['Capture Attendance', 'Students', 'staff'],
        't_capture_photos' => ['Capture Photos', 'Students', 'staff'],

        't_message' => ['Messages', 'Communication', 'staff'],
        't_circular' => ['Circulars', 'Communication', 'staff'],
        't_parent_comm' => ['Parent Communication', 'Communication', 'staff'],
        't_sms_parent' => ['SMS to Parents', 'Communication', 'staff'],
        't_notification_parent' => ['Notify Parents', 'Communication', 'staff'],
        't_email_parent' => ['Email Parents', 'Communication', 'staff'],
        't_notification_report' => ['Notification Report', 'Communication', 'staff'],
        't_photo_gallary' => ['Photo Gallery', 'Communication', 'staff'],
        't_video_gallary' => ['Video Gallery', 'Communication', 'staff'],

        't_enquiry' => ['Admission Enquiry', 'Admissions', 'staff'],
        't_registration' => ['Admission Registration', 'Admissions', 'staff'],
        't_confirmation' => ['Admission Confirmation', 'Admissions', 'staff'],

        't_feescollect' => ['Collect Fees', 'Fees', 'staff'],

        't_inward' => ['Inward Register', 'Front Office', 'staff'],
        't_outward' => ['Outward Register', 'Front Office', 'staff'],
        't_visitor' => ['Visitors', 'Front Office', 'staff'],
        't_complain' => ['Complaints', 'Front Office', 'staff'],
        't_requisition' => ['Requisition', 'Front Office', 'staff'],

        't_own_profile' => ['My Profile', 'My Work', 'staff'],
        't_punch_inout' => ['Punch In / Out', 'My Work', 'staff'],
        't_my_attendance' => ['My Attendance', 'My Work', 'staff'],
        't_my_leave' => ['My Leave', 'My Work', 'staff'],
        't_approve_leave' => ['Approve Leave', 'My Work', 'staff'],
        't_leave_authorisation' => ['Leave Authorisation', 'My Work', 'staff'],
        't_task' => ['Tasks', 'My Work', 'staff'],
    ],

    // Starting set per role (lower-case profile name). Additive only when synced.
    'defaults' => [
        'student' => [
            'lms_subject', 'lms_pal', 'virtual_classroom', 'social_collabrative', 'leaderboard', 'portfolio',
            'assignment', 'home_work', 'time_table', 'exam_schedule', 'result_pdf', 'wrt_progress', 'classwork_gallery',
            'student_attendance', 'academic_calendar', 'holiday_list', 'leave_application',
            'notification_hub', 'circular_events', 'parent_communication', 'child_remark',
            'fees', 'student_certificate', 'achievement_certificate',
            'health_details', 'height_weight', 'student_infirmary', 'student_vaccination',
            'student_transport', 'student_hostel', 'student_consent',
            'photos_gallery', 'video_gallery', 'about_us', 'principal_desk', 'school_information',
            'achievement', 'school_timing', 'rules', 'facility', 'academic_activity', 'reach_us',
        ],
        'teacher' => [
            't_take_attendance', 't_homework', 't_marks', 't_timetable', 't_proxy', 't_teach', 't_teacher_dairy',
            't_teacher_resource', 't_virtual_classroom', 't_social_collobrative', 't_wrt_progress', 't_exam_schedule', 't_calander',
            't_student_profile', 't_disclipline', 't_capture_attendance', 't_capture_photos',
            't_message', 't_circular', 't_parent_comm', 't_sms_parent', 't_notification_parent', 't_email_parent',
            't_notification_report', 't_photo_gallary', 't_video_gallary',
            't_visitor', 't_complain', 't_requisition',
            't_own_profile', 't_punch_inout', 't_my_attendance', 't_my_leave', 't_task',
        ],
        'admin' => [
            't_student_profile', 't_teacher_profile', 't_disclipline', 't_capture_attendance', 't_capture_photos',
            't_homework', 't_timetable', 't_calander', 't_exam_schedule',
            't_circular', 't_message', 't_sms_parent', 't_notification_parent', 't_email_parent', 't_notification_report',
            't_enquiry', 't_registration', 't_confirmation',
            't_feescollect',
            't_inward', 't_outward', 't_visitor', 't_complain', 't_requisition',
            't_own_profile', 't_approve_leave', 't_leave_authorisation', 't_task',
        ],
    ],
];
