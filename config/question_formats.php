<?php

/*
|--------------------------------------------------------------------------
| Question generation -- per-format knobs
|--------------------------------------------------------------------------
|
| Every format in App\Services\QuestionGeneration\Formats works with no entry
| here: each class carries its own defaults. An entry only OVERRIDES one, so a
| deployment can tune a format's batch size, temperature or pinned prompt
| version without a release. Keys: batch_size, temperature, prompt_version.
|
| mcq and the legacy narrative alias are deliberately absent. They keep their
| settings in config/deepseek.php, exactly as before the format seam existed.
|
| Environment overrides follow the existing DEEPSEEK_BATCH_SIZE_* convention,
| e.g. QGEN_BATCH_SIZE_TRUE_FALSE=8.
|
*/

return [
    /*
    | H5P content-type driven generation (App\Services\QuestionGeneration\H5p).
    |
    | For the formats listed in `types` the selected H5P content type chooses the
    | prompt, the JSON the model returns and the validator, and the model is Claude
    | (config/claude.php, `question_generation`). Every other format keeps its
    | existing prompt and provider untouched.
    |
    |   enabled            QGEN_H5P_DRIVEN=false switches the whole layer off, which
    |                      restores the previous DeepSeek behaviour for every format.
    |   types              Catalogue codes offered and generated this way. Phase 1 is
    |                      mcq and true_false; a code with no H5P definition is ignored.
    |   questions_per_type Exactly this many questions per selected type. Server-owned:
    |                      the client's total and custom Bloom mix are not used for these.
    |   max_attempts       Model calls per batch, the first plus retries that carry the
    |                      validation failure back to the model. Never unbounded.
    */
    'h5p' => [
        'enabled' => (bool) env('QGEN_H5P_DRIVEN', true),
        'types' => array_values(array_filter(array_map('trim', explode(',', (string) env('QGEN_H5P_TYPES', 'mcq,true_false'))))),
        'questions_per_type' => (int) env('QGEN_H5P_QUESTIONS_PER_TYPE', 2),
        'max_attempts' => (int) env('QGEN_H5P_MAX_ATTEMPTS', 3),
    ],

    'formats' => [
        'true_false' => ['batch_size' => (int) env('QGEN_BATCH_SIZE_TRUE_FALSE', 10)],
        'fill_blank' => ['batch_size' => (int) env('QGEN_BATCH_SIZE_FILL_BLANK', 10)],
        'assertion_reason' => ['batch_size' => (int) env('QGEN_BATCH_SIZE_ASSERTION_REASON', 5)],
        'numerical' => ['batch_size' => (int) env('QGEN_BATCH_SIZE_NUMERICAL', 5)],
        'match_following' => ['batch_size' => (int) env('QGEN_BATCH_SIZE_MATCH_FOLLOWING', 3)],
        'very_short' => ['batch_size' => (int) env('QGEN_BATCH_SIZE_VERY_SHORT', 5)],
        'short' => ['batch_size' => (int) env('QGEN_BATCH_SIZE_SHORT', 3)],
        'long' => ['batch_size' => (int) env('QGEN_BATCH_SIZE_LONG', 3)],
        'case_study' => ['batch_size' => (int) env('QGEN_BATCH_SIZE_CASE_STUDY', 2)],
        // Image-based Drag & Drop. Not written by the text model: a picture is found
        // (Openverse, via ConceptImageSearchService), a vision model locates its parts, and
        // the boxes are validated. Geometry limits left out use DragDropGeometry::DEFAULTS.
        'drag_drop' => [
            // Pictures used per request; each question needs its own.
            'max_images' => (int) env('QGEN_DRAG_DROP_MAX_IMAGES', 3),
            // Vision model; null uses the Gemini client's default model.
            'vision_model' => env('QGEN_DRAG_DROP_VISION_MODEL'),
            'vision_timeout' => (int) env('QGEN_DRAG_DROP_VISION_TIMEOUT', 60),
            // Appended to the concept name, in turn, to ask the image search for a diagram.
            'query_suffixes' => ['diagram', 'labelled diagram', 'structure'],
            // Null keeps the image search's own relevance floor.
            'min_score' => env('QGEN_DRAG_DROP_MIN_SCORE'),
        ],
    ],
];
