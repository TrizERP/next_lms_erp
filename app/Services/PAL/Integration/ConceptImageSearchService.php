<?php

namespace App\Services\PAL\Integration;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Openverse image search, for the PAL Learn page's "learn this concept
 * visually" entry point — the same shape as `ExternalVideoSearchService`
 * (query building from real concept/chapter/subject data, `available()`,
 * cache-before-call, graceful `[]`/`null` on any failure) applied to images
 * instead of video, because that is the existing pattern for "find external
 * teaching material for a concept" in this estate.
 *
 * WHY OPENVERSE AND NOT AN ARBITRARY IMAGE SEARCH
 *
 * Openverse (openverse.org) indexes only openly-licensed images (Creative
 * Commons and public domain) and returns real license/attribution metadata
 * with every result — which is the one thing that makes returning a random
 * web image to a student defensible at all. It needs no API key for search
 * volume at this scale; `client_id`/`client_secret` (optional) only raise
 * the anonymous rate limit via an OAuth2 client-credentials token, never
 * change what is searched.
 *
 * WHY THIS DOES NOT GO THROUGH ConceptVideoLibraryService'S REVIEW GATE
 *
 * That service exists because CONTENT LAW C4/C5 require a HUMAN to approve a
 * video before any student sees it. An illustrative image for a concept name
 * a teacher already put in the syllabus is a different trust boundary — the
 * same one an ordinary image-search box would have — and gating it behind
 * manual review would mean no student ever sees an image until a teacher has
 * reviewed one for every concept, which defeats the point of this feature
 * working for every concept on day one. Nothing here writes to a table; a
 * result is picked and returned in the same request, cached, never stored as
 * content for later curation.
 */
class ConceptImageSearchService
{
    protected const SEARCH_ENDPOINT_CONFIG = 'pal_content.image.external.endpoint';

    protected const TOKEN_ENDPOINT = 'https://api.openverse.org/v1/auth_tokens/token/';

    protected const TOKEN_CACHE_KEY = 'pal:image:openverse:token';

    public function available(): bool
    {
        return (bool) config('pal_content.image.enabled', true)
            && (bool) config('pal_content.image.external.enabled', true);
    }

    /**
     * Common words with no search value — stripped before anything is sent
     * to Openverse. Verified live (see the class-level note on query length)
     * that Openverse's own search degrades hard as a phrase gets longer or
     * more sentence-like: the full concept name as a literal phrase routinely
     * returns zero results, while the same words with connectives removed
     * finds real matches. This is the one lever that actually moves that
     * number, so it is applied before anything else.
     */
    protected const STOPWORDS = [
        'a', 'an', 'the', 'as', 'of', 'is', 'are', 'was', 'were', 'with', 'to',
        'in', 'on', 'for', 'and', 'or', 'using', 'use', 'by', 'from', 'this',
        'that', 'these', 'those', 'into', 'than', 'then', 'its', 'their',
    ];

    /**
     * Build the search phrase from whichever real concept fields are on
     * hand. Only `conceptName` is required — a concept with no chapter,
     * subject or description on this code path still gets a real, specific
     * query, never a generic placeholder.
     *
     * This is the widest, most-specific variant — the one shown to the
     * student as "query" when it is the one that actually found the image.
     * `bestImageFor()` tries progressively narrower slices of it if this one
     * returns nothing, rather than failing straight to the fallback on a
     * single search attempt.
     */
    public function queryFor(
        string $conceptName,
        ?string $chapterName = null,
        ?string $description = null,
        ?string $subject = null,
        ?string $grade = null,
    ): string {
        $parts = [$this->significantWords($conceptName, 6)];

        if ($chapterName !== null && trim($chapterName) !== '' && ! $this->covers($conceptName, $chapterName)) {
            $parts[] = $this->significantWords($chapterName, 3);
        }

        // A short phrase pulled from the concept's own authored description,
        // when it has one — real vocabulary specific to this concept, not a
        // second copy of the concept name. Capped hard: a whole paragraph
        // handed to an image search degrades relevance rather than helping it.
        if ($description !== null && trim($description) !== '') {
            $snippet = $this->descriptionSnippet($description, $conceptName, $chapterName);
            if ($snippet !== '') {
                $parts[] = $snippet;
            }
        }

        if ($subject !== null && trim($subject) !== '' && ! $this->covers($conceptName, $subject)) {
            $parts[] = $this->significantWords($subject, 2);
        }

        if ($grade !== null && trim($grade) !== '') {
            $parts[] = 'class ' . trim($grade);
        }

        return $this->clipQuery(implode(' ', array_filter($parts)));
    }

    /**
     * Search Openverse and return the single best usable result, or null.
     *
     * "Usable" means: passed the relevance/junk filter AND its image URL
     * actually resolves to a real image (verified live — see pickBest()).
     * A result that fails either check is silently skipped in favour of the
     * next-ranked candidate, never surfaced as a partial or broken result.
     *
     * Tries `$query` as given, then progressively shorter slices of its own
     * significant words (see queryVariants()) before giving up — a query
     * narrow enough to be genuinely specific is also narrow enough to
     * legitimately return nothing on an index built from Flickr/Wikimedia
     * photography rather than textbook diagrams, and a broader retry is a
     * search a person would make by hand rather than an invented visual.
     *
     * `$maxVariants` caps how many of those retries are allowed, counting the
     * full query as one. Null (the default) means "all of them", which is the
     * right behaviour for the Learn page: it is one search for one concept and
     * a broader retry is what rescues a hard one.
     *
     * It exists for callers that search many queries in one request — the
     * journey map is ten of them — where "all four variants for every query"
     * is up to 40 outbound searches and the caller, not this method, is the
     * thing that has to stop. Passing 1 searches only the exact query, which
     * is also what lets a caller tell "this query found its own picture" from
     * "this query fell back to a narrowed prefix of itself".
     *
     * `$minScore` overrides the configured relevance floor for this one call.
     * Null (the default) means "the configured floor", which is what the Learn
     * page wants and what 3.0 exists for. A caller asking a DIFFERENT question
     * — the journey map, whose question is "does this picture depict this
     * step", not "is this picture about this concept" — may pass its own floor
     * and is then responsible for a matching check of its own.
     *
     * `$source` overrides the configured source filter for this one call. Null
     * (the default) means "the configured source", which is `wikimedia` — a
     * deliberate relevance trade for the Learn page (see rank()'s note). A
     * caller asking a different question may widen it; the journey map does,
     * because "which step does this picture show" is better answered by
     * photography of flashcards than by encyclopedia diagrams, and it applies
     * its own stricter gate over the results instead of relying on the corpus.
     *
     * @return array{image:array<string,mixed>, query:string}|null
     */
    public function bestImageFor(
        string $query,
        ?int $maxVariants = null,
        ?float $minScore = null,
        ?string $source = null,
    ): ?array {
        if (! $this->available()) {
            return null;
        }

        $variants = $this->queryVariants($query);
        if ($maxVariants !== null) {
            $variants = array_slice($variants, 0, max(1, $maxVariants));
        }

        foreach ($variants as $variant) {
            $variant = $this->clipQuery($variant);
            if ($variant === '') {
                continue;
            }

            $candidates = $this->search($variant, $source);
            if ($candidates === []) {
                continue;
            }

            $best = $this->pickBest($candidates, $variant, $minScore);
            if ($best !== null) {
                return ['image' => $best, 'query' => $variant];
            }
        }

        return null;
    }

    /**
     * Up to `$limit` ranked, relevance-filtered candidates instead of only the top one.
     *
     * Same search, ranking, licence-presence and score floor as bestImageFor(); the
     * difference is that the caller (the study-deck image planner) gets several to
     * choose between, because for abstract topics the best keyword match is often the
     * wrong picture. URLs are NOT pre-resolved here: the caller downloads the one it
     * picks, which is the stronger check.
     *
     * @return array<int, array<string, mixed>> each candidate plus the `query` variant that found it
     */
    public function rankedImagesFor(string $query, int $limit = 5, ?float $minScore = null, ?string $source = null): array
    {
        if (! $this->available()) {
            return [];
        }

        $floor = $minScore ?? (float) config('pal_content.image.external.min_score', 1.0);
        $out = [];

        foreach ($this->queryVariants($query) as $variant) {
            $variant = $this->clipQuery($variant);
            if ($variant === '') {
                continue;
            }

            $candidates = $this->search($variant, $source);
            if ($candidates === []) {
                continue;
            }

            foreach ($this->rank($candidates, $variant, $minScore) as $row) {
                if ($row['score'] < $floor || isset($out[$row['url']])) {
                    continue;
                }
                unset($row['score']);
                $out[$row['url']] = $row + ['query' => $variant];

                if (count($out) >= $limit) {
                    break 2;
                }
            }
        }

        return array_values($out);
    }

    /**
     * `$query` itself, then its first 3 and first 2 significant words, then
     * its single first word — each one strictly narrower in word count but
     * broader in what it can match. Deduplicated and never empty-string.
     *
     * @return array<int, string>
     */
    protected function queryVariants(string $query): array
    {
        $words = array_values(array_filter(preg_split('/\s+/', trim($query)) ?: []));

        $variants = [$query];
        foreach ([3, 2, 1] as $n) {
            if (count($words) > $n) {
                $variants[] = implode(' ', array_slice($words, 0, $n));
            }
        }
        if ($words !== []) {
            $variants[] = $words[0];
        }

        return array_values(array_unique(array_filter($variants, static fn ($v) => trim($v) !== '')));
    }

    /**
     * The first `$max` words of `$text` with stopwords removed and
     * punctuation trimmed — never a hardcoded per-topic keyword list, just
     * the concept's own words with noise removed.
     */
    protected function significantWords(string $text, int $max): string
    {
        $kept = [];

        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $clean = trim($word, ".,;:!?()[]{}\"'“”‘’");
            if ($clean === '' || in_array(mb_strtolower($clean), self::STOPWORDS, true)) {
                continue;
            }

            $kept[] = $clean;
            if (count($kept) >= $max) {
                break;
            }
        }

        return implode(' ', $kept);
    }

    /**
     * Raw Openverse results for a query, cached. Always returns an array —
     * a missing/invalid token, a timeout, a non-2xx or a malformed body all
     * yield [], the same rule ExternalVideoSearchService follows for video.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function search(string $query, ?string $sourceOverride = null): array
    {
        // Keyed by source as well as query: the same text searched across all
        // sources and across Wikimedia alone are different result sets, and
        // caching them under one key would make the second caller silently
        // receive the first one's answer.
        $effectiveSource = $sourceOverride ?? trim((string) config('pal_content.image.external.source', 'wikimedia'));

        $cacheKey = 'pal:image:openverse:' . sha1($effectiveSource . '|' . $query);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $request = Http::timeout((int) config('pal_content.image.external.timeout', 6))->retry(0);

            $token = $this->accessToken();
            if ($token !== null) {
                $request = $request->withToken($token);
            }

            $params = [
                'q' => $query,
                'license_type' => config('pal_content.image.external.license_type', 'commercial,modification'),
                'mature' => 'false',
                'page_size' => (int) config('pal_content.image.external.max_results', 12),
            ];

            // Verified live: Flickr (Openverse's largest source by volume) is
            // personal/travel photography, and its per-photo folksonomy tags
            // create false keyword matches on abstract topic words a title
            // check alone does not catch ("multiplication" hitting a photo
            // tagged with an unrelated sense of the word). Wikimedia Commons —
            // encyclopedia figures, technical diagrams, public-domain
            // textbook-style scans — is where genuinely relevant results for
            // an abstract concept actually live (confirmed live: searching it
            // alone surfaces the literal "Multiplication as repeated
            // addition" diagram). `source` is configurable rather than
            // hardcoded so a school that finds a specific concept needs a
            // wider net can loosen it without a code change.
            $source = $effectiveSource;
            if ($source !== '') {
                $params['source'] = $source;
            }

            $response = $request->get((string) config(self::SEARCH_ENDPOINT_CONFIG, 'https://api.openverse.org/v1/images/'), $params);

            if (! $response->successful()) {
                Log::info('[pal-image] Openverse search failed', ['status' => $response->status(), 'query' => $query]);

                return [];
            }

            $results = $response->json('results');
            if (! is_array($results)) {
                return [];
            }

            $shaped = $this->shape($results);

            Cache::put($cacheKey, $shaped, now()->addHours((int) config('pal_content.image.external.cache_hours', 720)));

            return $shaped;
        } catch (\Throwable $e) {
            Log::info('[pal-image] Openverse search errored', ['message' => $e->getMessage(), 'query' => $query]);

            return [];
        }
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function shape(array $items): array
    {
        $results = [];

        foreach ($items as $item) {
            $url = data_get($item, 'url');
            if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $license = trim(mb_strtoupper((string) data_get($item, 'license', '')));
            $version = trim((string) data_get($item, 'license_version', ''));

            $tags = array_values(array_filter(array_map(
                static fn ($tag) => is_array($tag) ? (string) ($tag['name'] ?? '') : (string) $tag,
                (array) data_get($item, 'tags', [])
            )));

            $results[] = [
                'external_id' => data_get($item, 'id'),
                'url' => $url,
                'thumbnail_url' => (string) (data_get($item, 'thumbnail') ?? $url),
                'title' => trim((string) data_get($item, 'title', '')) ?: null,
                'source_url' => (string) (data_get($item, 'foreign_landing_url') ?? $url),
                'creator' => trim((string) data_get($item, 'creator', '')) ?: null,
                'license' => $license === '' ? null : trim($license . ' ' . $version),
                'attribution' => trim((string) data_get($item, 'attribution', '')) ?: null,
                'provider' => (string) (data_get($item, 'source') ?? data_get($item, 'provider') ?? 'openverse'),
                'width' => (int) data_get($item, 'width', 0),
                'height' => (int) data_get($item, 'height', 0),
                'tags' => $tags,
            ];
        }

        return $results;
    }

    /**
     * Rank by relevance/quality, then verify the top few candidates' image
     * URLs actually resolve before trusting any of them — Openverse indexes
     * third-party hosts, and a dead or moved image is a real, observed
     * failure mode, not a hypothetical one.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>|null
     */
    protected function pickBest(array $candidates, string $query, ?float $minScore = null): ?array
    {
        $ranked = $this->rank($candidates, $query, $minScore);

        // The floor that separates "actually about this concept" from "an
        // unrelated photo Openverse's own full-text match happened to
        // surface once the query was broadened enough to get any hits at
        // all". Verified live: a broadened 2-word query reliably returns
        // *something*, and without this floor that something is routinely a
        // street photo or a portrait with zero real connection to the
        // concept — worse than returning nothing, because it looks like an
        // answer. `min_score` of 1.0 requires at least one genuine keyword
        // hit against the candidate's own title/tags, not merely Openverse's
        // internal match on some other field.
        $minScore = $minScore ?? (float) config('pal_content.image.external.min_score', 1.0);
        $ranked = array_values(array_filter($ranked, static fn (array $row) => $row['score'] >= $minScore));

        $toVerify = (int) config('pal_content.image.external.candidates_to_verify', 3);

        foreach (array_slice($ranked, 0, max(1, $toVerify)) as $candidate) {
            if ($this->urlResolvesToImage($candidate['url'])) {
                unset($candidate['score']);

                return $candidate;
            }

            Log::info('[pal-image] candidate image did not resolve, trying next', ['url' => $candidate['url']]);
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    protected function rank(array $candidates, string $query, ?float $minScore = null): array
    {
        $queryWords = $this->words($query);
        $exclude = array_map('mb_strtolower', (array) config('pal_content.image.exclude_keywords', []));
        $prefer = array_map('mb_strtolower', (array) config('pal_content.image.prefer_keywords', []));
        $minWidth = (int) config('pal_content.image.external.min_width', 200);
        $minHeight = (int) config('pal_content.image.external.min_height', 200);

        $scored = [];

        foreach ($candidates as $candidate) {
            $haystack = mb_strtolower(implode(' ', array_filter([
                $candidate['title'] ?? '',
                implode(' ', $candidate['tags'] ?? []),
                $candidate['creator'] ?? '',
            ])));

            // A decorative/junk match is disqualifying, not merely penalised —
            // a logo that happens to share a keyword with the concept is
            // still a logo.
            foreach ($exclude as $term) {
                if ($term !== '' && str_contains($haystack, $term)) {
                    continue 2;
                }
            }

            // No usable license metadata at all is disqualifying: the whole
            // point of Openverse is that a student can be told where an
            // image came from and under what terms.
            if (empty($candidate['license'])) {
                continue;
            }

            $score = 0.0;

            foreach ($queryWords as $word) {
                if (mb_strlen($word) > 2 && str_contains($haystack, $word)) {
                    $score += 1.0;
                }
            }

            foreach ($prefer as $term) {
                if ($term !== '' && str_contains($haystack, $term)) {
                    $score += 1.5;
                }
            }

            $width = (int) ($candidate['width'] ?? 0);
            $height = (int) ($candidate['height'] ?? 0);

            if ($width > 0 && $height > 0) {
                $score += ($width >= $minWidth && $height >= $minHeight) ? 0.5 : -1.0;
            }

            $scored[] = $candidate + ['score' => $score];
        }

        usort($scored, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $scored;
    }

    /**
     * The User-Agent Wikimedia's own policy requires
     * (meta.wikimedia.org/wiki/User-Agent_policy) — verified live that
     * `upload.wikimedia.org` 403s a HEAD request carrying Guzzle's default
     * one, which is indistinguishable from an anonymous scraper to them.
     * Sent on every outbound request here, not only Wikimedia's, since it is
     * good practice for any external API and costs nothing on the others.
     */
    protected function userAgent(): string
    {
        return 'EduERP-PAL/1.0 (+https://openverse.org concept-image search for a K-12 LMS)';
    }

    /**
     * A HEAD request (GETting the full image would download it twice for
     * nothing) confirming the URL is still live and actually an image —
     * Openverse's own index can lag behind a source removing or moving a
     * file, and a broken `<img>` is worse than falling back cleanly.
     */
    protected function urlResolvesToImage(string $url): bool
    {
        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent()])->timeout(4)->retry(0)->head($url);
            $contentType = (string) $response->header('Content-Type');

            return $response->successful() && str_starts_with(mb_strtolower($contentType), 'image/');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * An OAuth2 client-credentials token, when this estate has registered an
     * Openverse application. Search works with none — this only raises the
     * anonymous rate limit — so a missing/expired/refused token degrades to
     * anonymous rather than failing the search.
     */
    protected function accessToken(): ?string
    {
        $clientId = trim((string) config('pal_content.image.external.client_id', ''));
        $clientSecret = trim((string) config('pal_content.image.external.client_secret', ''));

        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asForm()->timeout(6)->retry(0)->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $token = (string) $response->json('access_token');
            $expiresIn = (int) $response->json('expires_in', 3600);

            if ($token === '') {
                return null;
            }

            // A safety margin under the real expiry so a token is never used
            // right up to the second it dies mid-request.
            Cache::put(self::TOKEN_CACHE_KEY, $token, max(60, $expiresIn - 120));

            return $token;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A short, real phrase from the concept's own description — not the
     * first N characters, which usually reads as a lopped-off sentence.
     * Stops before it would repeat a word the query already has.
     */
    protected function descriptionSnippet(string $description, string $conceptName, ?string $chapterName): string
    {
        $already = $this->words($conceptName . ' ' . ($chapterName ?? ''));

        $plain = trim((string) preg_replace('/\s+/', ' ', strip_tags($description)));
        $sentence = preg_split('/(?<=[.!?])\s+/', $plain, 2)[0] ?? $plain;

        $keep = array_filter(
            explode(' ', $sentence),
            static fn (string $word) => mb_strlen($word) > 3 && ! in_array(mb_strtolower($word), $already, true)
        );

        return $this->clipQuery(implode(' ', array_slice($keep, 0, 6)));
    }

    /** @return array<int, string> */
    protected function words(string $text): array
    {
        return array_values(array_filter(array_map(
            'mb_strtolower',
            preg_split('/[^\p{L}\p{N}]+/u', $text) ?: []
        )));
    }

    /** True when the phrase already contains the other, so we do not repeat it. */
    protected function covers(string $haystack, string $needle): bool
    {
        return str_contains(mb_strtolower($haystack), mb_strtolower(trim($needle)));
    }

    protected function clipQuery(string $query): string
    {
        $query = trim((string) preg_replace('/\s+/', ' ', $query));

        return mb_substr($query, 0, 150);
    }
}
