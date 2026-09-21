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
        /*
        | ---------------------------------------------------------------
        | 2026-09-21 vertical: Image Hotspots, Memory Game, Course
        | Presentation, Arithmetic Quiz.
        | ---------------------------------------------------------------
        |
        | VERSIONS ARE MANIFEST CLAIMS, NOT INSTALLED CODE. Nothing in this
        | repository loads these libraries -- see this file's header. The
        | numbers below are the closures the official library.json files
        | declare at the versions named, and they are what an exported
        | package asks an importing host to resolve. When H5P ships a new
        | minor, this is the one place to bump.
        */

        'image_hotspots' => [
            'machine_name' => 'H5P.ImageHotspots',
            'title' => 'Image Hotspots',
            'major_version' => 1,
            'minor_version' => 10,
            'patch_version' => 6,
            'runnable' => 1,
            'embed_types' => ['div'],
            'license' => 'MIT',

            // H5P.ImageHotspots renders each popup through a sub-content
            // library, which is why H5P.Image / H5P.AdvancedText / H5P.Video
            // are dependencies and not optional: a "rich content" hotspot IS
            // one of those libraries nested in the hotspot's `action`.
            'dependencies' => [
                ['machineName' => 'FontAwesome', 'majorVersion' => 4, 'minorVersion' => 5],
                ['machineName' => 'H5P.Image', 'majorVersion' => 1, 'minorVersion' => 1],
                ['machineName' => 'H5P.AdvancedText', 'majorVersion' => 1, 'minorVersion' => 1],
                ['machineName' => 'H5P.Video', 'majorVersion' => 1, 'minorVersion' => 6],
            ],

            'editor_dependencies' => [
                ['machineName' => 'H5PEditor.VerticalTabs', 'majorVersion' => 1, 'minorVersion' => 3],
                ['machineName' => 'H5PEditor.ImageCoordinateSelector', 'majorVersion' => 1, 'minorVersion' => 0],
            ],
        ],

        'memory_game' => [
            'machine_name' => 'H5P.MemoryGame',
            'title' => 'Memory Game',
            'major_version' => 1,
            'minor_version' => 3,
            'patch_version' => 22,
            'runnable' => 1,
            'embed_types' => ['div'],
            'license' => 'MIT',

            'dependencies' => [
                ['machineName' => 'H5P.JoubelUI', 'majorVersion' => 1, 'minorVersion' => 3],
                ['machineName' => 'H5P.Transition', 'majorVersion' => 1, 'minorVersion' => 0],
                ['machineName' => 'FontAwesome', 'majorVersion' => 4, 'minorVersion' => 5],
            ],

            'editor_dependencies' => [
                ['machineName' => 'H5PEditor.VerticalTabs', 'majorVersion' => 1, 'minorVersion' => 3],
            ],
        ],

        'course_presentation' => [
            'machine_name' => 'H5P.CoursePresentation',
            'title' => 'Course Presentation',
            'major_version' => 1,
            'minor_version' => 25,
            'patch_version' => 9,
            'runnable' => 1,
            'embed_types' => ['div'],
            'license' => 'MIT',

            /*
            | A Course Presentation is a CONTAINER: every slide element is a
            | nested library, so the closure has to carry the libraries this
            | product's authoring UI can place on a slide, not just the ones
            | the shell needs to boot. Drop one of these and an importing host
            | resolves the deck but renders an empty box where the question was.
            |
            | The interaction libraries below are exactly the ones the slide
            | editor offers, so this list and H5PCoursePresentationBuilder's
            | element map are two views of one decision.
            */
            'dependencies' => [
                // Shell.
                ['machineName' => 'jQuery.ui', 'majorVersion' => 1, 'minorVersion' => 10],
                ['machineName' => 'H5P.JoubelUI', 'majorVersion' => 1, 'minorVersion' => 3],
                ['machineName' => 'H5P.Transition', 'majorVersion' => 1, 'minorVersion' => 0],
                ['machineName' => 'H5P.FontIcons', 'majorVersion' => 1, 'minorVersion' => 0],
                // The library a 'go to slide' element declares. It is part of
                // the deck's navigation, not of a slide's content, which is why
                // it sits with the shell rather than the interactions.
                ['machineName' => 'H5P.GoToSlide', 'majorVersion' => 1, 'minorVersion' => 3],
                ['machineName' => 'FontAwesome', 'majorVersion' => 4, 'minorVersion' => 5],

                // Static slide elements.
                ['machineName' => 'H5P.AdvancedText', 'majorVersion' => 1, 'minorVersion' => 1],
                ['machineName' => 'H5P.Image', 'majorVersion' => 1, 'minorVersion' => 1],
                ['machineName' => 'H5P.Video', 'majorVersion' => 1, 'minorVersion' => 6],
                ['machineName' => 'H5P.Audio', 'majorVersion' => 1, 'minorVersion' => 5],

                // Interactive slide elements.
                ['machineName' => 'H5P.Question', 'majorVersion' => 1, 'minorVersion' => 5],
                ['machineName' => 'H5P.MultiChoice', 'majorVersion' => 1, 'minorVersion' => 16],
                ['machineName' => 'H5P.TrueFalse', 'majorVersion' => 1, 'minorVersion' => 8],
                ['machineName' => 'H5P.Blanks', 'majorVersion' => 1, 'minorVersion' => 14],
                ['machineName' => 'H5P.DragQuestion', 'majorVersion' => 1, 'minorVersion' => 14],
            ],

            'editor_dependencies' => [
                ['machineName' => 'H5PEditor.CoursePresentation', 'majorVersion' => 1, 'minorVersion' => 25],
                ['machineName' => 'H5PEditor.VerticalTabs', 'majorVersion' => 1, 'minorVersion' => 3],
                ['machineName' => 'H5PEditor.Wizard', 'majorVersion' => 1, 'minorVersion' => 2],
            ],
        ],

        'arithmetic_quiz' => [
            'machine_name' => 'H5P.ArithmeticQuiz',
            'title' => 'Arithmetic Quiz',
            'major_version' => 1,
            'minor_version' => 1,
            'patch_version' => 20,
            'runnable' => 1,
            'embed_types' => ['div'],
            'license' => 'MIT',

            'dependencies' => [
                ['machineName' => 'H5P.JoubelUI', 'majorVersion' => 1, 'minorVersion' => 3],
                ['machineName' => 'H5P.Transition', 'majorVersion' => 1, 'minorVersion' => 0],
                ['machineName' => 'H5P.Timer', 'majorVersion' => 0, 'minorVersion' => 4],
                ['machineName' => 'FontAwesome', 'majorVersion' => 4, 'minorVersion' => 5],
            ],

            'editor_dependencies' => [
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
