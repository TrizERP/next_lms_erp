<?php

namespace App\Services\StudyDeck;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Turns a stored deck's picture references into addresses a browser can load.
 *
 * A stored deck says `study-deck-image:<id>`. An <img> tag cannot send an Authorization header, so the picture
 * endpoint (GET /api/study-deck/images/{id}) is opened by a SIGNED address instead: the signature covers the picture
 * and the school it was issued to (`t`), so neither can be changed without breaking it, and an address is only ever
 * issued here, to a caller that has already passed the deck's own school check. The endpoint then checks again that
 * the school may see that picture.
 *
 * The expiry is the end of the next UTC day, so every address issued during a day is the same string: the browser
 * keeps the picture instead of fetching it again each time the deck is opened.
 */
final class StudyDeckImageUrls
{
    /** Where a deck keeps the parts that can carry a picture: slides (study deck) and sections (study documents). */
    private const PARTS = ['slides', 'sections'];

    public function __construct(private readonly StudyDeckImages $images = new StudyDeckImages())
    {
    }

    /** The signed address of one picture for one school. */
    public function signed(int $id, int $tenant): string
    {
        $expires = now()->utc()->startOfDay()->addDays(2);
        $relative = URL::temporarySignedRoute('study-deck.image', $expires, ['id' => $id, 't' => $tenant], absolute: false);

        return $this->origin() . $relative;
    }

    /**
     * Signed addresses for the pictures among these ids that the school may see.
     *
     * @param array<int,int> $ids
     * @return array<int,string> id => address
     */
    public function forIds(array $ids, int $tenant): array
    {
        $urls = [];
        foreach (array_keys($this->images->visibleMetas($ids, $tenant)) as $id) {
            $urls[$id] = $this->signed($id, $tenant);
        }

        return $urls;
    }

    /**
     * A stored deck (or study document) with every picture reference replaced by its signed address.
     *
     * A picture the school may not see (or that is gone) is left as its reference, which loads nothing, and is logged:
     * the rest of the deck is still usable.
     *
     * @param array<string,mixed> $deck
     * @return array<string,mixed>
     */
    public function hydrate(array $deck, int $tenant): array
    {
        $wanted = [];
        foreach (self::PARTS as $part) {
            foreach ($deck[$part] ?? [] as $item) {
                if (($id = StudyDeckImages::idFromRef($item['image']['url'] ?? null)) !== null) {
                    $wanted[$id] = $id;
                }
            }
        }
        if (!$wanted) {
            return $deck;
        }

        $urls = $this->forIds(array_values($wanted), $tenant);
        foreach (self::PARTS as $part) {
            foreach ($deck[$part] ?? [] as $i => $item) {
                $id = StudyDeckImages::idFromRef($item['image']['url'] ?? null);
                if ($id === null) {
                    continue;
                }
                if (isset($urls[$id])) {
                    $deck[$part][$i]['image']['url'] = $urls[$id];
                } else {
                    Log::warning('Study deck picture is not available to this school', ['image_id' => $id, 'tenant' => $tenant]);
                }
            }
        }

        return $deck;
    }

    private function origin(): string
    {
        if (app()->bound('request') && ($host = request()->getSchemeAndHttpHost()) !== '' && !app()->runningInConsole()) {
            return $host;
        }

        return rtrim((string) config('app.url'), '/');
    }
}
