<?php

namespace App\Http\Controllers\lms\h5p;

/**
 * Mark the Words (H5P.MarkTheWords).
 *
 * Everything this type does lives in H5PTextActivityController, which it
 * shares with the other two text-passage types. All that is type-specific is
 * the discriminator, the route prefix and the view directory -- see that
 * class for why the behaviour is not copied three times.
 */
class H5PMarkTheWordsController extends H5PTextActivityController
{
    protected function contentType(): string
    {
        return 'mark_the_words';
    }

    protected function routePrefix(): string
    {
        return 'h5p_mark_the_words';
    }

    protected function viewDirectory(): string
    {
        return 'markthewords';
    }
}
