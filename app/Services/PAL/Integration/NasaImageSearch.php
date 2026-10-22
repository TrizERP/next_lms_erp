<?php

namespace App\Services\PAL\Integration;

use Illuminate\Support\Facades\Http;

/**
 * Candidate pictures from the NASA Image and Video Library, through its official public API (https://images-api.nasa.gov/search; it needs no
 * key). Used for astronomy, Earth and space scenes. It returns candidates in the shape ConceptImageSearchService does, so everything after the
 * search (licence rule, keyword evidence, the strict relevance review, the download, the sharpness gate, the pixel check, the credit) is the
 * existing pipeline's and not repeated here.
 *
 * NASA's media usage guidelines: NASA content is generally not subject to copyright in the United States and may be used for educational
 * purposes, with NASA acknowledged as the source. NASA says that items carrying a third party's copyright are marked as such, so anything whose
 * title or description says "copyright" or carries the copyright sign is left out here rather than guessed about.
 */
class NasaImageSearch
{
    /** The licence label a NASA picture carries (ImagePlanner accepts exactly this one for NASA). */
    public const LICENCE = 'NASA media usage guidelines';

    public function available(): bool
    {
        return (bool) config('pal_content.image.nasa.enabled', true);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function search(string $query, int $limit = 6): array
    {
        if (!$this->available() || trim($query) === '') {
            return [];
        }
        try {
            $response = Http::timeout((int) config('pal_content.image.nasa.timeout', 8))
                ->withHeaders(['User-Agent' => 'EduERP-educational-picture-search/1.0'])
                ->get((string) config('pal_content.image.nasa.endpoint', 'https://images-api.nasa.gov/search'), ['q' => $query, 'media_type' => 'image', 'page_size' => max(1, min(12, $limit * 2))]);
        } catch (\Throwable) {
            return [];
        }
        if (!$response->successful()) {
            return [];
        }

        $out = [];
        foreach ((array) $response->json('collection.items', []) as $item) {
            $data = (array) (($item['data'][0] ?? []));
            $href = (string) (($item['links'][0]['href'] ?? ''));
            $id = trim((string) ($data['nasa_id'] ?? ''));
            if (($data['media_type'] ?? '') !== 'image' || $id === '' || !filter_var($href, FILTER_VALIDATE_URL) || !preg_match('/\.(jpe?g|png)(\?|$)/i', $href)) {
                continue;
            }
            $text = ($data['title'] ?? '') . ' ' . ($data['description'] ?? '');
            if (preg_match('/copyright|©/iu', $text)) {
                continue;   // a third party's picture, used on NASA's site with permission: not NASA's to give
            }
            $who = trim((string) preg_replace('/^[\s(]+|[\s)]+$/u', '', (string) ($data['photographer'] ?? $data['secondary_creator'] ?? '')));
            $creator = $who !== '' ? $who : 'NASA';
            $out[] = [
                'external_id' => $id,
                'url' => str_starts_with($href, 'http://') ? 'https://' . substr($href, 7) : $href,
                'thumbnail_url' => str_starts_with($href, 'http://') ? 'https://' . substr($href, 7) : $href,
                'title' => trim((string) ($data['title'] ?? '')) ?: null,
                'source_url' => 'https://images.nasa.gov/details/' . rawurlencode($id),
                'creator' => $creator,
                'license' => self::LICENCE,
                'attribution' => '"' . trim((string) ($data['title'] ?? $id)) . '" by ' . $creator . ', NASA Image and Video Library (images.nasa.gov/details/' . $id . '). NASA is acknowledged as the source.',
                'provider' => 'nasa',
                'width' => 0,
                'height' => 0,
                'tags' => array_values(array_filter(array_map('strval', (array) ($data['keywords'] ?? [])))),
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }
}
