<?php

namespace App\Services\QuestionGeneration\DragDrop\Contracts;

/**
 * Keeps a copy of the picture. The stored zones are exact percentages of these pixels,
 * so the question must not depend on a third party's file staying the same.
 */
interface DiagramImageStore
{
    /** @return string the public URL of the stored copy */
    public function store(string $bytes, string $mime): string;
}
