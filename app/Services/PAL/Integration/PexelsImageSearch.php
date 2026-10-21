<?php

namespace App\Services\PAL\Integration;

use Illuminate\Support\Facades\Http;

/**
 * Candidate pictures from Pexels, through its official API (https://api.pexels.com/v1/search). It does nothing until an API key is configured
 * (PEXELS_API_KEY): the key is sent in the Authorization header as Pexels documents, never in a URL, and never logged.
 *
 * Pexels' API guidelines ask that the photographer is credited ("Photo by <name> on Pexels") with a link to the photo's page on Pexels; both are
 * carried on the candidate (`attribution`, `source_url`). Pexels allows the photo to be downloaded and used, so the existing library keeps a copy
 * as it does for any picture; the rate limit (200 requests an hour) is respected by making one request per query.
 *
 * The candidates have the shape ConceptImageSearchService's have, so the licence rule, the keyword evidence, the strict relevance review, the
 * download, the sharpness gate and the pixel check that follow are the existing pipeline's.
 */
class PexelsImageSearch
{
    /** The licence label a Pexels picture carries (ImagePlanner accepts exactly this one for Pexels). */
    public const LICENCE = 'Pexels License';

    private function key(): string
    {
        return trim((string) config('pal_content.image.pexels.key', ''));
    }

    public function available(): bool
    {
        return $this->key() !== '';
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
            $response = Http::timeout((int) config('pal_content.image.pexels.timeout', 8))
                ->withHeaders(['Authorization' => $this->key(), 'User-Agent' => 'EduERP-educational-picture-search/1.0'])
                ->get((string) config('pal_content.image.pexels.endpoint', 'https://api.pexels.com/v1/search'), ['query' => $query, 'per_page' => max(1, min(15, $limit)), 'orientation' => 'landscape']);
        } catch (\Throwable) {
            return [];
        }
        if (!$response->successful()) {
            return [];
        }

        $out = [];
        foreach ((array) $response->json('photos', []) as $p) {
            $src = (string) ($p['src']['large'] ?? $p['src']['medium'] ?? '');
            $page = (string) ($p['url'] ?? '');
            $by = trim((string) ($p['photographer'] ?? ''));
            if (!filter_var($src, FILTER_VALIDATE_URL) || !filter_var($page, FILTER_VALIDATE_URL) || $by === '') {
                continue;
            }
            $alt = trim((string) ($p['alt'] ?? ''));
            $out[] = [
                'external_id' => (string) ($p['id'] ?? ''),
                'url' => $src,
                'thumbnail_url' => (string) ($p['src']['medium'] ?? $src),
                'title' => $alt !== '' ? $alt : null,
                'source_url' => $page,
                'creator' => $by,
                'license' => self::LICENCE,
                'attribution' => 'Photo by ' . $by . ' on Pexels (' . $page . ')',
                'provider' => 'pexels',
                'width' => (int) ($p['width'] ?? 0),
                'height' => (int) ($p['height'] ?? 0),
                'tags' => array_values(array_filter(preg_split('/[^\p{L}]+/u', mb_strtolower($alt)) ?: [], fn ($w) => mb_strlen($w) >= 4)),
            ];
        }

        return $out;
    }
}
