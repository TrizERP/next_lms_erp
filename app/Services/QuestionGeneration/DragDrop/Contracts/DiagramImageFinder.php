<?php

namespace App\Services\QuestionGeneration\DragDrop\Contracts;

/** Finds one openly licensed picture for a concept, and returns its bytes. */
interface DiagramImageFinder
{
    /**
     * @param array<string, mixed> $context      concept_name, concept_description
     * @param array<int, string>   $excludeUrls  source image URLs already tried or used
     *
     * @return array{
     *     bytes: string, mime: string, width: int, height: int,
     *     image_url: string, source_url: string|null, title: string|null,
     *     creator: string|null, licence: string|null, attribution: string|null,
     *     provider: string|null
     * }|null null when nothing usable was found
     */
    public function find(array $context, array $excludeUrls = []): ?array;
}
