<?php

namespace App\Services\QuestionGeneration\DragDrop;

use App\Services\PAL\Integration\ConceptImageSearchService;
use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageFinder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Finds a diagram with the estate's existing image search (Openverse, open licences
 * only) and downloads it.
 *
 * ConceptImageSearchService is reused as it is: its search, ranking, licence filter and
 * "does the URL really resolve to an image" check. What is new here is only the query
 * (asking for a diagram, not an illustration) and the download, because the vision step
 * needs the bytes and the real pixel size rather than the index's claim about them.
 */
class OpenverseDiagramImageFinder implements DiagramImageFinder
{
    public function __construct(private readonly ConceptImageSearchService $search)
    {
    }

    public function find(array $context, array $excludeUrls = []): ?array
    {
        if (!$this->search->available()) {
            return null;
        }

        $concept = trim((string) ($context['concept_name'] ?? ''));
        if ($concept === '') {
            return null;
        }

        $suffixes = (array) config('question_formats.formats.drag_drop.query_suffixes', ['diagram', 'labelled diagram', 'structure']);
        $minScore = config('question_formats.formats.drag_drop.min_score');

        foreach ($suffixes as $suffix) {
            $query = $this->search->queryFor($concept . ' ' . $suffix);
            $found = $this->search->bestImageFor($query, 1, $minScore === null ? null : (float) $minScore);
            $image = $found['image'] ?? null;

            if (!is_array($image) || empty($image['url']) || in_array($image['url'], $excludeUrls, true)) {
                continue;
            }

            $downloaded = $this->download((string) $image['url']);
            if ($downloaded === null) {
                continue;
            }

            return $downloaded + [
                'image_url'   => (string) $image['url'],
                'source_url'  => $image['source_url'] ?? null,
                'title'       => $image['title'] ?? null,
                'creator'     => $image['creator'] ?? null,
                'licence'     => $image['license'] ?? null,
                'attribution' => $image['attribution'] ?? null,
                'provider'    => $image['provider'] ?? 'openverse',
            ];
        }

        return null;
    }

    /** @return array{bytes: string, mime: string, width: int, height: int}|null */
    private function download(string $url): ?array
    {
        try {
            $response = Http::timeout(20)->retry(1, 300, throw: false)->get($url);
            if (!$response->successful()) {
                return null;
            }
            $bytes = $response->body();
            if ($bytes === '' || strlen($bytes) > (int) config('question_formats.formats.drag_drop.max_image_bytes', DragDropGeometry::DEFAULTS['max_image_bytes'])) {
                return null;
            }
            // The decoded size and type, not the index's or the server's claim.
            $info = @getimagesizefromstring($bytes);
            if ($info === false || empty($info['mime'])) {
                return null;
            }

            return ['bytes' => $bytes, 'mime' => (string) $info['mime'], 'width' => (int) $info[0], 'height' => (int) $info[1]];
        } catch (\Throwable $e) {
            Log::info('QuestionGeneration drag_drop: image download failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
