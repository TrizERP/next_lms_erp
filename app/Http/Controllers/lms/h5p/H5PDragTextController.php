<?php

namespace App\Http\Controllers\lms\h5p;

/**
 * Drag the Words (H5P.DragText).
 *
 * Everything this type does lives in H5PTextActivityController, which it
 * shares with the other two text-passage types. All that is type-specific is
 * the discriminator, the route prefix and the view directory -- see that
 * class for why the behaviour is not copied three times.
 */
class H5PDragTextController extends H5PTextActivityController
{
    protected function contentType(): string
    {
        return 'drag_text';
    }

    protected function routePrefix(): string
    {
        return 'h5p_drag_text';
    }

    protected function viewDirectory(): string
    {
        return 'dragtext';
    }
}
