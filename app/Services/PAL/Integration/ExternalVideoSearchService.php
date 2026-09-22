<?php

namespace App\Services\PAL\Integration;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * YouTube Data API v3 search, for concepts the institute has no video for.
 *
 * Measured on the live estate, only 251 of 2,571 concepts sit in a chapter
 * that has any video at all — so without an external tier this feature reaches
 * one concept in ten. That is what this is for.
 *
 * It is called from the harvest command ONLY, never from a student request.
 * Two independent reasons: `search.list` costs 100 of the 10,000 daily quota
 * units (about 100 searches a day in total), and the approval gate means
 * anything found mid-request is unreviewed and therefore cannot be shown to
 * the student who triggered it anyway. A blocking API call on that path would
 * buy them nothing and cost everyone else the day's quota.
 *
 * The existing helper at contentController::ajax_getYouTubeSuggestion is
 * deliberately not reused: it calls file_get_contents() with no timeout, and
 * dereferences $video['items'] unconditionally, so a 403 quota error takes the
 * request down with it.
 */
class ExternalVideoSearchService
{
    protected const ENDPOINT = 'https://www.googleapis.com/youtube/v3/search';

    /** Quota spend is tracked per day so a sweep cannot silently exhaust it. */
    protected const QUOTA_CACHE_KEY = 'pal:video:youtube:searches:';

    public function available(): bool
    {
        return (bool) config('pal_content.video.external.enabled', false)
            && trim((string) config('pal_content.video.external.api_key', '')) !== '';
    }

    /**
     * Search for videos teaching this concept.
     *
     * Always returns an array. A missing key, a quota refusal, a timeout or a
     * malformed body all yield [] — a re-explanation is never worth failing a
     * command over, let alone a student's screen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, array $opts = []): array
    {
        if (! $this->available()) {
            Log::debug('[pal-video] external search skipped: not configured');

            return [];
        }

        $query = $this->clipQuery($query);
        if ($query === '') {
            return [];
        }

        $cacheKey = 'pal:video:youtube:' . sha1($query);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        if (! $this->reserveQuota()) {
            Log::info('[pal-video] daily YouTube search cap reached; skipping', ['query' => $query]);

            return [];
        }

        try {
            $response = Http::timeout((int) config('pal_content.video.external.timeout', 6))
                ->retry(0)
                ->get(self::ENDPOINT, [
                    'q' => $query,
                    'part' => 'snippet',
                    'type' => 'video',

                    // Without this we harvest videos whose owners forbid
                    // embedding, and they render inside the player as a
                    // refusal notice rather than a lesson.
                    'videoEmbeddable' => 'true',

                    'safeSearch' => config('pal_content.video.external.safe_search', 'strict'),

                    // 4-20 minutes: excludes Shorts and three-hour marathons,
                    // neither of which is a remediation explanation.
                    'videoDuration' => config('pal_content.video.external.video_duration', 'medium'),

                    'order' => 'relevance',
                    'maxResults' => (int) ($opts['max_results'] ?? config('pal_content.video.external.max_results', 5)),
                    'relevanceLanguage' => config('pal_content.video.external.relevance_language', 'en'),
                    'regionCode' => config('pal_content.video.external.region_code', 'IN'),
                    'key' => (string) config('pal_content.video.external.api_key'),
                ]);

            if (! $response->successful()) {
                Log::info('[pal-video] YouTube search failed', [
                    'status' => $response->status(),
                    'query' => $query,
                ]);

                return [];
            }

            $items = $response->json('items');
            if (! is_array($items)) {
                return [];
            }

            $results = $this->shape($items);

            Cache::put($cacheKey, $results, now()->addHours((int) config('pal_content.video.external.cache_hours', 168)));

            return $results;
        } catch (\Throwable $e) {
            Log::info('[pal-video] YouTube search errored', ['message' => $e->getMessage(), 'query' => $query]);

            return [];
        }
    }

    /**
     * Build the search phrase.
     *
     * The concept name alone is not enough — "Corrosion" returns industrial
     * engineering material, and "Reactivity Series" returns university
     * chemistry. Chapter, subject and grade anchor it to school level. Only
     * curriculum names leave the building; no learner data is ever sent.
     */
    public function queryFor(string $conceptName, ?string $chapterName, ?string $subject, ?string $grade): string
    {
        $parts = [$conceptName];

        if ($chapterName !== null && trim($chapterName) !== '' && ! $this->covers($conceptName, $chapterName)) {
            $parts[] = $chapterName;
        }

        if ($grade !== null && trim($grade) !== '') {
            $parts[] = 'class ' . trim($grade);
        }

        if ($subject !== null && trim($subject) !== '') {
            $parts[] = $subject;
        }

        return $this->clipQuery(implode(' ', array_filter($parts)));
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function shape(array $items): array
    {
        $results = [];

        foreach ($items as $item) {
            $videoId = data_get($item, 'id.videoId');
            if (! is_string($videoId) || preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) !== 1) {
                continue;
            }

            $results[] = [
                'external_id' => $videoId,
                'media_url' => 'https://www.youtube.com/watch?v=' . $videoId,

                // The API returns HTML-escaped text ("Metals &amp; non-metals"),
                // and these strings are shown to students and reviewers as
                // plain text, not markup.
                'title' => $this->decode(data_get($item, 'snippet.title')),
                'description' => $this->decode(data_get($item, 'snippet.description')),
                'attribution' => $this->decode(data_get($item, 'snippet.channelTitle')),
                'thumbnail_url' => (string) (data_get($item, 'snippet.thumbnails.medium.url')
                    ?? data_get($item, 'snippet.thumbnails.default.url', '')),
                'provider' => 'youtube',
                'source' => 'youtube',
            ];
        }

        return $results;
    }

    protected function decode(mixed $value): string
    {
        return trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** True when the phrase already contains the other, so we do not repeat it. */
    protected function covers(string $haystack, string $needle): bool
    {
        return str_contains(mb_strtolower($haystack), mb_strtolower(trim($needle)));
    }

    protected function clipQuery(string $query): string
    {
        $query = trim((string) preg_replace('/\s+/', ' ', $query));

        return mb_substr($query, 0, 120);
    }

    /**
     * Consume one unit of the day's search budget, or refuse.
     *
     * search.list is 100 units against a 10,000/day default, so a full sweep
     * of every concept is a multi-week job by construction. Failing loudly at
     * a cap beats discovering the quota is gone when a teacher needs it.
     */
    protected function reserveQuota(): bool
    {
        $cap = (int) config('pal_content.video.external.daily_search_cap', 90);
        if ($cap <= 0) {
            return true;
        }

        $key = self::QUOTA_CACHE_KEY . now()->toDateString();
        $used = (int) Cache::get($key, 0);

        if ($used >= $cap) {
            return false;
        }

        Cache::put($key, $used + 1, now()->endOfDay());

        return true;
    }

    public function searchesUsedToday(): int
    {
        return (int) Cache::get(self::QUOTA_CACHE_KEY . now()->toDateString(), 0);
    }
}
