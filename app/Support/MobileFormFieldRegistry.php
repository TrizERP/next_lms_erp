<?php

namespace App\Support;

/**
 * Curated source pages a Custom Mobile Page can be created FROM -- "Select
 * Existing Page" in the Mobile Page Builder's create flow imports this
 * page's fields as Input blocks (a starting layout the admin then edits:
 * remove what you don't want, restyle what you keep), instead of the admin
 * dragging every field on by hand.
 *
 * Deliberately a hand-maintained registry, the same shape/reasoning as
 * MobileDynamicPageFieldRegistry (which does the equivalent job for
 * read-only native_dynamic tiles): reading an arbitrary existing page's
 * actual form markup (Blade or React, hundreds of pages, no shared schema
 * between them) is not something that can be done reliably and generically.
 * A page's real fields are traced by hand once, from its actual React
 * component and the Laravel API it posts to -- see the class doc on each
 * entry below for where it was traced from -- and kept here so the
 * mobile-page-builder API can serve them without re-deriving anything at
 * request time.
 *
 * Adding a page: trace its real field keys/labels/types/required-ness from
 * its own source (not by guessing from the database table), trace its
 * create/update endpoint the same way, add an entry below. Selects backed by
 * a live API use `optionsSource` (endpoint + optional dotted `path` into the
 * response, e.g. "data.quotas") rather than a hardcoded list, so the options
 * stay live; a genuinely fixed list (Gender, Blood Group) uses `options`
 * directly. A field whose real UI is a CASCADING dropdown (its options
 * depend on another field's current value -- Standard needs Section's
 * choice, Division needs Standard's) is imported as plain text instead of a
 * broken/disconnected dropdown; the admin can reconfigure it by hand if they
 * need the real cascading behavior.
 */
class MobileFormFieldRegistry
{
    /**
     * Traced from D:\lms_k12\app\student\add_student\page.tsx and
     * app\student\student-admin-api.ts (2026-09-24). Submits to
     * `POST student-registration` (relative to /api/), body keys identical
     * to the field keys below (no renaming between frontend and backend
     * here, unlike the separate edit-a-student flow under
     * app\students\search_student, which uses different field names
     * entirely and is NOT what this entry imports).
     */
    private const REGISTRY = [
        'add_student' => [
            'type' => 'form',
            'label' => 'Add Student',
            // tblmenumaster.link for this page (id 81) -- how sourcePages()
            // flags a menu item as "fields already traced for this one" when
            // listing the FULL menu (see menuPages()) rather than just this
            // hand-picked registry.
            'matchLink' => 'student/add_student',
            'submit' => [
                'method' => 'POST',
                'endpoint' => 'student-registration',
                'successMessage' => 'Student added.',
            ],
            'fields' => [
                ['key' => 'enrollment_no', 'label' => 'GR No.', 'inputType' => 'text', 'required' => true],
                ['key' => 'roll_no', 'label' => 'Roll No.', 'inputType' => 'text', 'required' => false],
                ['key' => 'first_name', 'label' => 'First Name', 'inputType' => 'text', 'required' => true],
                ['key' => 'middle_name', 'label' => 'Middle Name', 'inputType' => 'text', 'required' => false],
                ['key' => 'last_name', 'label' => 'Last Name', 'inputType' => 'text', 'required' => true],
                ['key' => 'father_name', 'label' => 'Father Name', 'inputType' => 'text', 'required' => false],
                ['key' => 'mother_name', 'label' => 'Mother Name', 'inputType' => 'text', 'required' => false],
                ['key' => 'dob', 'label' => 'Date of Birth', 'inputType' => 'date', 'required' => true],
                ['key' => 'mobile', 'label' => 'Mobile', 'inputType' => 'text', 'required' => true, 'placeholder' => '10-digit mobile number'],
                ['key' => 'email', 'label' => 'Email', 'inputType' => 'email', 'required' => true],
                ['key' => 'address', 'label' => 'Address', 'inputType' => 'text', 'required' => false],
                [
                    'key' => 'grade', 'label' => 'Section', 'inputType' => 'select', 'required' => true,
                    'optionsSource' => ['endpoint' => 'get_adminAcademicSection', 'path' => 'data'],
                ],
                // Standard/Division are cascading selects on the real page
                // (Standard needs Section's grade_id, Division needs
                // Standard's standard_id) -- imported as text; see class doc.
                ['key' => 'standard', 'label' => 'Standard', 'inputType' => 'text', 'required' => true, 'placeholder' => 'e.g. 10th'],
                ['key' => 'division', 'label' => 'Division', 'inputType' => 'text', 'required' => true, 'placeholder' => 'e.g. A'],
                [
                    'key' => 'bloodgroup', 'label' => 'Blood Group', 'inputType' => 'select', 'required' => true,
                    'options' => [
                        ['value' => 'A+', 'label' => 'A+'], ['value' => 'A-', 'label' => 'A-'],
                        ['value' => 'B+', 'label' => 'B+'], ['value' => 'B-', 'label' => 'B-'],
                        ['value' => 'AB+', 'label' => 'AB+'], ['value' => 'AB-', 'label' => 'AB-'],
                        ['value' => 'O+', 'label' => 'O+'], ['value' => 'O-', 'label' => 'O-'],
                    ],
                ],
                [
                    'key' => 'gender', 'label' => 'Gender', 'inputType' => 'select', 'required' => true,
                    'options' => [
                        ['value' => 'Male', 'label' => 'Male'],
                        ['value' => 'Female', 'label' => 'Female'],
                        ['value' => 'Other', 'label' => 'Other'],
                    ],
                ],
                [
                    'key' => 'student_quota', 'label' => 'Student Quota', 'inputType' => 'select', 'required' => true,
                    'optionsSource' => ['endpoint' => 'student-registration/metadata', 'path' => 'data.quotas'],
                ],
                [
                    'key' => 'house', 'label' => 'House', 'inputType' => 'select', 'required' => false,
                    'optionsSource' => ['endpoint' => 'student-registration/metadata', 'path' => 'data.houses'],
                ],
            ],
        ],

        /**
         * Traced from D:\lms_k12\app\students\search_student\components\
         * StudentDetailDrawer.tsx's "Edit" tab (2026-09-24) -- the edit form
         * that lives inside the Student List page's per-row drawer, NOT the
         * page's own top-level search form (that's a filter form returning a
         * list, which this registry has no component for yet -- see
         * class doc). Submits to `PUT get_adminStudentSearch/{id}`, a
         * DIFFERENT endpoint with DIFFERENT field names than add_student's
         * `POST student-registration` above -- e.g. one combined `name`
         * instead of first/middle/last, `class_name`/`section_name` as text
         * instead of grade/standard/division ids. Do not assume the two are
         * interchangeable.
         *
         * `idField` = 'id': the record id an edit endpoint targets in its
         * URL, not its body -- see MobilePageBuilderAdminApiController's
         * layout schema / generateFromSourcePage.ts on the Next.js side for
         * how an imported page's Save button resolves "…/{{id}}" from this
         * field's current value. Imported as a real, visible Input (labelled
         * "Student ID") since nothing in this registry can know WHICH
         * student's record a given mobile screen is for -- that has to be
         * supplied by however the page is actually used (typed in, or wired
         * to a data source later).
         */
        'edit_student' => [
            'type' => 'form',
            'label' => 'Edit Student Profile',
            // tblmenumaster.link for "Search/Edit Student" (id 80) -- this
            // form lives inside THAT page's drawer, not on a page of its
            // own, so it's the closest real menu item to point at.
            'matchLink' => 'students/search_student',
            'idField' => 'id',
            'submit' => [
                'method' => 'PUT',
                'endpoint' => 'get_adminStudentSearch/{{id}}',
                'successMessage' => 'Student profile updated.',
            ],
            'fields' => [
                ['key' => 'id', 'label' => 'Student ID', 'inputType' => 'text', 'required' => true, 'placeholder' => 'The student record to update'],
                ['key' => 'name', 'label' => 'Student Name', 'inputType' => 'text', 'required' => false],
                ['key' => 'admission_no', 'label' => 'Admission No', 'inputType' => 'text', 'required' => false],
                [
                    'key' => 'class_name', 'label' => 'Class', 'inputType' => 'select', 'required' => false,
                    'options' => [
                        ['value' => '6', 'label' => '6'], ['value' => '7', 'label' => '7'], ['value' => '8', 'label' => '8'],
                        ['value' => '9', 'label' => '9'], ['value' => '10', 'label' => '10'],
                    ],
                ],
                [
                    'key' => 'section_name', 'label' => 'Section', 'inputType' => 'select', 'required' => false,
                    'options' => [['value' => 'A', 'label' => 'A'], ['value' => 'B', 'label' => 'B'], ['value' => 'C', 'label' => 'C']],
                ],
                ['key' => 'roll_no', 'label' => 'Roll Number', 'inputType' => 'number', 'required' => false],
                [
                    'key' => 'gender', 'label' => 'Gender', 'inputType' => 'select', 'required' => false,
                    'options' => [['value' => 'Male', 'label' => 'Male'], ['value' => 'Female', 'label' => 'Female'], ['value' => 'Other', 'label' => 'Other']],
                ],
                ['key' => 'dob', 'label' => 'Date of Birth', 'inputType' => 'date', 'required' => false],
                [
                    'key' => 'blood_group', 'label' => 'Blood Group', 'inputType' => 'select', 'required' => false,
                    'options' => [
                        ['value' => 'A+', 'label' => 'A+'], ['value' => 'A-', 'label' => 'A-'],
                        ['value' => 'B+', 'label' => 'B+'], ['value' => 'B-', 'label' => 'B-'],
                        ['value' => 'AB+', 'label' => 'AB+'], ['value' => 'AB-', 'label' => 'AB-'],
                        ['value' => 'O+', 'label' => 'O+'], ['value' => 'O-', 'label' => 'O-'],
                    ],
                ],
                [
                    'key' => 'house', 'label' => 'House', 'inputType' => 'select', 'required' => false,
                    'options' => [
                        ['value' => 'Red', 'label' => 'Red'], ['value' => 'Blue', 'label' => 'Blue'],
                        ['value' => 'Green', 'label' => 'Green'], ['value' => 'Yellow', 'label' => 'Yellow'],
                    ],
                ],
                ['key' => 'father_name', 'label' => "Father's Name", 'inputType' => 'text', 'required' => false],
                ['key' => 'mother_name', 'label' => "Mother's Name", 'inputType' => 'text', 'required' => false],
                ['key' => 'mobile', 'label' => 'Phone', 'inputType' => 'text', 'required' => false],
                ['key' => 'email', 'label' => 'Email', 'inputType' => 'email', 'required' => false],
                ['key' => 'address', 'label' => 'Address', 'inputType' => 'text', 'required' => false],
            ],
        ],

        /**
         * Traced from D:\lms_k12\app\student\student_attendance\page.tsx
         * (2026-09-24) -- the one genuine daily class-marking entry form
         * among 6 "attendance" candidates checked (the other 5 are
         * read-only reports; app\attendance\attendance_dashboard bundles a
         * real entry form together with charts/reports and was skipped for
         * that reason). Unlike add_student/edit_student above, this is a
         * `type = 'list'` entry: search for a class's roster, then one
         * Present/Absent row per student, submitted together -- see
         * MobileListBlock.tsx / RenderList in MobilePageRenderer.tsx
         * (Next.js) for what this config maps onto.
         *
         * Two things confirmed by a second, targeted trace (not guessed):
         * (1) the roster response has NO single "name" field -- only
         * `first_name`/`last_name` split, hence the space-joined
         * itemLabelField below (RenderList's readItemLabel() reads it as
         * "read these fields and join them"); (2) the search endpoint wants
         * ONE combined `standard_division` value
         * ("{standardId}||{divisionId}"), not the two ids separately --
         * hence `searchBody` rather than sending each search field as-is.
         *
         * Standard/Division are imported as plain text (not live dropdowns)
         * for the same reason add_student's are: their real UI is a
         * cascading dropdown pair, and this registry has no way to pass a
         * parent value into `optionsSource` yet.
         */
        'attendance' => [
            'type' => 'list',
            'label' => 'Mark Attendance',
            // tblmenumaster.link for "Student Attendance" (id 95).
            'matchLink' => 'student/student_attendance',
            'list' => [
                'title' => 'Mark Attendance',
                'searchFields' => [
                    ['key' => 'standard', 'label' => 'Standard', 'inputType' => 'text', 'required' => true, 'placeholder' => 'Standard id'],
                    ['key' => 'division', 'label' => 'Division', 'inputType' => 'text', 'required' => true, 'placeholder' => 'Division id'],
                    ['key' => 'date', 'label' => 'Date', 'inputType' => 'date', 'required' => true],
                ],
                'searchAction' => ['method' => 'POST', 'endpoint' => 'student/show_student_attendance'],
                'searchBody' => ['date' => '{{date}}', 'standard_division' => '{{standard}}||{{division}}'],
                'itemsPath' => 'data.student_data',
                'itemIdField' => 'id',
                'itemLabelField' => 'first_name last_name',
                'itemSubLabelField' => 'roll_no',
                'rowControl' => ['type' => 'toggle', 'trueLabel' => 'Present', 'trueValue' => 'P', 'falseLabel' => 'Absent', 'falseValue' => 'A'],
                'submitAction' => [
                    'method' => 'POST',
                    'endpoint' => 'student/save_student_attendance',
                    'rowKeys' => [['key' => 'student[{itemId}]', 'source' => 'value']],
                    'extraBody' => ['date' => '{{date}}', 'standard_division' => '{{standard}}||{{division}}'],
                    'successMessage' => 'Attendance saved.',
                    'onSuccess' => ['type' => 'goBack'],
                ],
            ],
        ],
    ];

    /** @return list<array{key:string,label:string}> */
    public static function pages(): array
    {
        return array_map(
            fn (string $key, array $entry) => ['key' => $key, 'label' => $entry['label']],
            array_keys(self::REGISTRY),
            array_values(self::REGISTRY)
        );
    }

    public static function get(string $key): ?array
    {
        return self::REGISTRY[$key] ?? null;
    }

    /**
     * The registry key whose `matchLink` corresponds to a tblmenumaster
     * link, if any -- how menuPages() flags a real menu item as "fields
     * already traced for this one" (see MobilePageBuilderAdminApiController
     * ::menuPages()) without hand-listing every menu id here. Comparison is
     * on the link's PATH only (leading/trailing slashes stripped, query
     * string dropped) since tblmenumaster stores the same page's link with
     * inconsistent slash formatting across rows.
     */
    public static function keyForMenuLink(?string $link): ?string
    {
        $normalized = self::normalizeLink($link);
        if ($normalized === '') {
            return null;
        }

        foreach (self::REGISTRY as $key => $entry) {
            if (isset($entry['matchLink']) && self::normalizeLink($entry['matchLink']) === $normalized) {
                return $key;
            }
        }

        return null;
    }

    private static function normalizeLink(?string $link): string
    {
        $link = trim((string) $link);
        $link = strtok($link, '?') ?: $link; // drop any query string
        return trim($link, '/');
    }
}
