<?php

namespace App\Http\Controllers\lms\h5p;

/**
 * Fill in the Blanks (H5P.Blanks).
 *
 * Everything this type does lives in H5PTextActivityController, which it
 * shares with the other two text-passage types. All that is type-specific is
 * the discriminator, the route prefix and the view directory -- see that
 * class for why the behaviour is not copied three times.
 */
class H5PBlanksController extends H5PTextActivityController
{
    protected function contentType(): string
    {
        return 'fill_in_the_blanks';
    }

    protected function routePrefix(): string
    {
        return 'h5p_blanks';
    }

    protected function viewDirectory(): string
    {
        return 'blanks';
    }
}
