<?php

/*
|--------------------------------------------------------------------------
| H5P library registry
|--------------------------------------------------------------------------
|
| READ THIS BEFORE ADDING A LIBRARY.
|
| This file is a *manifest* registry, not a runtime. This ERP does not embed
| the H5P PHP framework (h5p/h5p-core) or the H5P JS player -- neither is in
| composer.json or package.json, and no h5p_libraries / h5p_contents tables
| exist. Every "H5P" type this platform ships (image hotspot, interactive
| video, flash cards, multiple choice, drag and drop, and now the three
| text-passage types -- drag the words, fill in the blanks, mark the words) is
| a native implementation over its own tables, rendered by this product's own player.
|
| What this registry is FOR is interoperability at the file boundary:
|
|   - it declares the official machine name, version and full dependency
|     closure of each type, so an exported .h5p package carries a manifest a
|     real H5P host (Moodle, Drupal, Lumi, h5p.com) will accept and resolve;
|   - it is what H5PPackageService reads when it writes `h5p.json` and when it
|     validates an uploaded package's `mainLibrary` on import;
|   - it is the single place to edit when the official library ships a new
|     major/minor, rather than a version string scattered through a service.
|
| `dependencies` is the transitive closure as the official library.json
| declares it, flattened. It is written out verbatim as `preloadedDependencies`
| so an importing host loads every required library automatically instead of
| failing on a missing one. `editor_dependencies` is the authoring-side
| closure, carried for hosts that re-open the package in the H5P editor.
|
| If the H5P framework is ever installed here, this registry becomes the
| bootstrap list for it and nothing above changes meaning.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Registered content-type libraries, keyed by PAL registry code
    |----------------------------------------------------------------------
    |
    | The key matches `pal_vocabulary` domain `h5p_types`, so a type's PAL
    | identity and its H5P identity resolve from the same code.
    |
    */
    'libraries' => [

        'drag_and_drop' => [
            'machine_name' => 'H5P.DragQuestion',
            'title' => 'Drag and Drop',
            'major_version' => 1,
            'minor_version' => 14,
            'patch_version' => 5,
            'runnable' => 1,
            'embed_types' => ['div'],
            'license' => 'MIT',

            // Transitive closure, as H5P.DragQuestion 1.14 declares it.
            'dependencies' => [
                ['machineName' => 'jQuery.ui', 'majorVersion' => 1, 'minorVersion' => 10],
                ['machineName' => 'H5P.Question', 'majorVersion' => 1, 'minorVersion' => 4],
                ['machineName' => 'H5P.JoubelUI', 'majorVersion' => 1, 'minorVersion' => 3],
                ['machineName' => 'H5P.Transition', 'majorVersion' => 1, 'minorVersion' => 0],
                ['machineName' => 'FontAwesome', 'majorVersion' => 4, 'minorVersion' => 5],
            ],

            'editor_dependencies' => [
                ['machineName' => 'H5PEditor.DragQuestion', 'majorVersion' => 1, 'minorVersion' => 10],
                ['machineName' => 'H5PEditor.VerticalTabs', 'majorVersion' => 1, 'minorVersion' => 3],
            ],
        ],


        /*
        | The three text-passage types below share a storage model and a
        | builder (H5PTextActivityBuilder), because H5P itself stores all
        | three the same way: one passage string carrying inline `*answer*`
        | markup. They are separate libraries all the same -- a host resolves
        | H5P.Blanks and H5P.DragText independently -- so each declares its
        | own closure here rather than sharing one.
        */

        'drag_text' => [
            'machine_name' => 'H5P.DragText',
            'title' => 'Drag the Words',
            'major_version' => 1,
            'minor_version' => 10,
            'patch_version' => 17,
            'runnable' => 1,
            'embed_types' => ['div'],
            'license' => 'MIT',

            // Transitive closure, as H5P.DragText 1.10 declares it.
            'dependencies' => [
                ['machineName' => 'jQuery.ui', 'majorVersion' => 1, 'minorVersion' => 10],
                ['machineName' => 'H5P.Question', 'majorVersion' => 1, 'minorVersion' => 5],
                ['machineName' => 'H5P.JoubelUI', 'majorVersion' => 1, 'minorVersion' => 3],
                ['machineName' => 'H5P.Transition', 'majorVersion' => 1, 'minorVersion' => 0],
                ['machineName' => 'H5P.TextUtilities', 'majorVersion' => 1, 'minorVersion' => 3],
                ['machineName' => 'FontAwesome', 'majorVersion' => 4, 'minorVersion' => 5],
            ],

            'editor_dependencies' => [
                ['machineName' => 'H5PEditor.RangeList', 'majorVersion' => 1, 'minorVersion' => 0],
                ['machineName' => 'H5PEditor.VerticalTabs', 'majorVersion' => 1, 'minorVersion' => 3],
            ],
        ],

        'fill_in_the_blanks' => [
            'machine_name' => 'H5P.Blanks',
            'title' => 'Fill in the Blanks',
            'major_version' => 1,
            'minor_version' => 14,
            'patch_version' => 11,
            'runnable' => 1,
            'embed_types' => ['div'],
            'license' => 'MIT',

            // Transitive closure, as H5P.Blanks 1.14 declares it.
            'dependencies' => [
                ['machineName' => 'H5P.Question', 'majorVersion' => 1, 'minorVersion' => 5],
                ['machineName' => 'H5P.JoubelUI', 'majorVersion' => 1, 'minorVersion' => 3],
                ['machineName' => 'H5P.Transition', 'majorVersion' => 1, 'minorVersion' => 0],
                ['machineName' => 'H5P.TextUtilities', 'majorVersion' => 1, 'minorVersion' => 3],
                ['machineName' => 'FontAwesome', 'majorVersion' => 4, 'minorVersion' => 5],
            ],

            'editor_dependencies' => [
                ['machineName' => 'H5PEditor.RangeList', 'majorVersion' => 1, 'minorVersion' => 0],
                ['machineName' => 'H5PEditor.VerticalTabs', 'majorVersion' => 1, 'minorVersion' => 3],
            ],
        ],

        'mark_the_words' => [
            'machine_name' => 'H5P.MarkTheWords',
            'title' => 'Mark the Words',
            'major_version' => 1,
            'minor_version' => 11,
            'patch_version' => 11,
            'runnable' => 1,
            'embed_types' => ['div'],
            'license' => 'MIT',

            // Transitive closure, as H5P.MarkTheWords 1.11 declares it.
            'dependencies' => [
                ['machineName' => 'H5P.Question', 'majorVersion' => 1, 'minorVersion' => 5],
                ['machineName' => 'H5P.JoubelUI', 'majorVersion' => 1, 'minorVersion' => 3],
                ['machineName' => 'H5P.Transition', 'majorVersion' => 1, 'minorVersion' => 0],
                ['machineName' => 'H5P.TextUtilities', 'majorVersion' => 1, 'minorVersion' => 3],
                ['machineName' => 'FontAwesome', 'majorVersion' => 4, 'minorVersion' => 5],
            ],

            'editor_dependencies' => [
                ['machineName' => 'H5PEditor.RangeList', 'majorVersion' => 1, 'minorVersion' => 0],
                ['machineName' => 'H5PEditor.VerticalTabs', 'majorVersion' => 1, 'minorVersion' => 3],
            ],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Package defaults
    |----------------------------------------------------------------------
    |
    | Written into every exported h5p.json. `language` is the package default
    | and is overridden per content where the item records one.
    |
    */
    'package' => [
        'language' => 'en',
        'default_license' => 'U',      // Undisclosed -- the H5P default
        'author_role' => 'Author',
        'extra_title_fallback' => 'Untitled activity',
    ],

    /*
    |----------------------------------------------------------------------
    | Import limits
    |----------------------------------------------------------------------
    |
    | A .h5p file is a zip an authenticated teacher uploads. These bounds are
    | what stops a malformed or hostile archive from being expanded blindly:
    | total uncompressed bytes, entry count, and the media types allowed out
    | of `content/`. Paths are additionally rejected if they escape the
    | extraction root -- see H5PPackageService::safeEntryPath().
    |
    */
    'import' => [
        'max_package_bytes' => 64 * 1024 * 1024,
        'max_uncompressed_bytes' => 256 * 1024 * 1024,
        'max_entries' => 2000,
        'allowed_media_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
    ],

];
