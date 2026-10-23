<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\StudyDeckImages;
use App\Services\StudyDeck\StudyDeckImageUrls;
use App\Services\StudyDeck\StudyImageStores;
use Illuminate\Support\Facades\DB;
use Tests\Unit\StudyDeck\Support\BootSafeTestCase;
use Tests\Unit\StudyDeck\Support\UsesImageDatabase;

/**
 * A downloaded picture's whole life after the download, on a throw-away in-memory SQLite database (never the shared one):
 *   bytes -> study_deck_images (StudyDeckImages::put) -> read back (bytes / chunks / verify) -> a deck's reference
 *   `study-deck-image:<id>` -> signed address (StudyDeckImageUrls) -> GET /api/study-deck/images/{id} -> the same bytes.
 * Also the `image-urls` response the Next player asks for. No Spaces, no network, no model.
 *
 * Set STUDY_DECK_EXPORT_DIR to have the test write the JSON it produced (the hydrated deck and the image-urls response)
 * so the player's own TypeScript can be run against the exact shapes the API sends.
 */
class StudyDeckImagePersistenceTest extends BootSafeTestCase
{
    use UsesImageDatabase;

    private const TENANT = 7;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useImageDatabase();
        config(['api_guard.enforce' => false, 'app.url' => 'http://localhost', 'logging.default' => 'null']);
        $this->school(self::TENANT, true);
        $this->school(8, false);
        $this->school(StudyDeckImages::PLATFORM_TENANT, true);
    }

    /** A noisy picture: it does not compress, so a large size is a large file. */
    private function image(string $format, int $w, int $h, int $seed = 1): string
    {
        $im = imagecreatetruecolor($w, $h);
        mt_srand($seed);
        for ($i = 0; $i < ($w * $h) / 40; $i++) {
            imagesetpixel($im, mt_rand(0, $w - 1), mt_rand(0, $h - 1), imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
        ob_start();
        match ($format) {
            'png' => imagepng($im, null, 0),
            'jpeg' => imagejpeg($im, null, 95),
            'webp' => imagewebp($im, null, 90),
        };

        return (string) ob_get_clean();
    }

    /** @return array<string,array{string,string,int,int}> */
    public static function formats(): array
    {
        return [
            'png' => ['png', 'image/png', 640, 480],
            'jpeg' => ['jpeg', 'image/jpeg', 800, 600],
            'webp' => ['webp', 'image/webp', 500, 300],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formats')]
    public function test_a_picture_is_stored_and_read_back_with_the_same_bytes_hash_and_size(string $format, string $mime, int $w, int $h): void
    {
        $bytes = $this->image($format, $w, $h);
        $images = new StudyDeckImages();

        $put = $images->put($bytes, self::TENANT, 8592, 'https://upload.wikimedia.org/wikipedia/commons/a/ab/Example.' . $format);

        $this->assertTrue($put['created']);
        $this->assertSame(StudyDeckImages::ref($put['id']), $put['ref']);
        $this->assertSame([$w, $h, $mime], [$put['width'], $put['height'], $put['mime']]);

        // The row, as the database holds it.
        $row = DB::table('study_deck_images')->where('id', $put['id'])->first();
        $this->assertSame(hash('sha256', $bytes), $row->sha256);
        $this->assertSame(strlen($bytes), (int) $row->byte_size);
        $this->assertSame([$w, $h], [(int) $row->width, (int) $row->height]);
        $this->assertSame($mime, $row->mime_type);
        $this->assertSame(self::TENANT, (int) $row->sub_institute_id);
        $this->assertSame(8592, (int) $row->chapter_id);

        // Read back: the bytes themselves, then their own dimensions and hash, not the columns'.
        $back = $images->bytes($put['id']);
        $this->assertSame($bytes, $back);
        $info = getimagesizefromstring($back);
        $this->assertSame([$w, $h, $mime], [$info[0], $info[1], $info['mime']]);
        $this->assertSame(hash('sha256', $bytes), hash('sha256', $back));
        $this->assertTrue($images->verify($put['id'], hash('sha256', $bytes), strlen($bytes)));
        $this->assertFalse($images->verify($put['id'], hash('sha256', $bytes . 'x'), strlen($bytes)), 'a wrong checksum is not verified');
    }

    public function test_a_large_picture_is_read_back_in_slices_and_reassembles_exactly(): void
    {
        $bytes = $this->image('png', 1100, 900, 5);
        $this->assertGreaterThan(StudyDeckImages::CHUNK_BYTES * 2, strlen($bytes), 'the fixture must need several slices');
        $images = new StudyDeckImages();
        $put = $images->put($bytes, self::TENANT);

        $pieces = iterator_to_array($images->chunks($put['id'], $put['bytes']), false);

        $this->assertGreaterThan(2, count($pieces));
        $this->assertSame($bytes, implode('', $pieces));
        $this->assertTrue($images->verify($put['id'], $put['sha256'], $put['bytes']));
    }

    public function test_the_same_picture_is_stored_once_per_school_and_never_shared_across_schools(): void
    {
        $bytes = $this->image('png', 300, 200, 3);
        $images = new StudyDeckImages();

        $first = $images->put($bytes, self::TENANT);
        $again = $images->put($bytes, self::TENANT, 9001);
        $other = $images->put($bytes, 8);

        $this->assertTrue($first['created']);
        $this->assertFalse($again['created']);
        $this->assertSame($first['id'], $again['id']);
        $this->assertNotSame($first['id'], $other['id'], 'another school gets its own row');
        $this->assertSame(2, DB::table('study_deck_images')->count());
        $this->assertSame(9001, (int) DB::table('study_deck_images')->where('id', $first['id'])->value('chapter_id'), 'a repeat fills in the chapter it lacked');
    }

    public function test_a_picture_that_is_not_png_jpeg_or_webp_is_refused_and_nothing_is_stored(): void
    {
        $images = new StudyDeckImages();
        foreach (['<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>', 'GIF89a' . str_repeat("\0", 30), 'not a picture', ''] as $bad) {
            try {
                $images->put($bad, self::TENANT);
                $this->fail('accepted: ' . substr($bad, 0, 12));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, DB::table('study_deck_images')->count());
    }

    public function test_a_download_is_found_again_by_its_address_so_a_rerun_does_not_fetch_it(): void
    {
        $bytes = $this->image('jpeg', 400, 300, 9);
        $url = 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/X.svg/960px-X.svg.png';
        $store = StudyImageStores::database(self::TENANT, 8592);

        $ref = $store->store($bytes, 'image/jpeg', $url);
        $hit = $store->cached($url);

        $this->assertMatchesRegularExpression('/^study-deck-image:[1-9][0-9]*$/', $ref);
        $this->assertSame($bytes, $hit['bytes']);
        $this->assertSame([400, 300, 'image/jpeg'], [$hit['width'], $hit['height'], $hit['mime']]);
        $this->assertNull($store->cached($url . '?other'));
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Retrieval: reference -> signed address -> the picture

    /** @return array{0:array<string,mixed>,1:array<int,string>} the golden deck pointed at two stored pictures, and their bytes by id */
    private function deckWithStoredPictures(): array
    {
        $deck = json_decode((string) file_get_contents(base_path('tests/Fixtures/study-deck-golden.json')), true);
        $images = new StudyDeckImages();
        $byId = [];
        $map = [];
        foreach ([1 => ['png', 640, 480], 2 => ['jpeg', 800, 600]] as $fixtureId => [$format, $w, $h]) {
            $bytes = $this->image($format, $w, $h, 20 + $fixtureId);
            $put = $images->put($bytes, self::TENANT, 8592);
            $map[StudyDeckImages::ref($fixtureId)] = $put['ref'];
            $byId[$put['id']] = $bytes;
        }
        array_walk_recursive($deck, function (&$value) use ($map) {
            if (is_string($value) && isset($map[$value])) {
                $value = $map[$value];
            }
        });

        return [$deck, $byId];
    }

    private function relative(string $signed): string
    {
        return (string) preg_replace('#^https?://[^/]+#', '', $signed);
    }

    public function test_a_stored_deck_is_hydrated_with_signed_addresses_that_serve_the_stored_bytes(): void
    {
        [$deck, $byId] = $this->deckWithStoredPictures();

        $hydrated = (new StudyDeckImageUrls())->hydrate($deck, self::TENANT);

        $served = 0;
        foreach ($hydrated['slides'] as $slide) {
            $url = $slide['image']['url'] ?? null;
            if ($url === null) {
                continue;
            }
            $this->assertStringNotContainsString('study-deck-image:', $url, 'every reference became an address');
            $this->assertStringContainsString('/api/study-deck/images/', $url);

            $response = $this->get($this->relative($url));
            $response->assertOk();
            $id = (int) preg_replace('#.*/images/(\d+).*#', '$1', $url);
            $body = $response->streamedContent();

            $this->assertSame($byId[$id], $body, "picture #$id is served byte for byte");
            $this->assertSame(hash('sha256', $byId[$id]), trim((string) $response->headers->get('ETag'), '"'));
            $info = getimagesizefromstring($body);
            $this->assertSame($info['mime'], $response->headers->get('Content-Type'));
            $this->assertSame((string) strlen($body), $response->headers->get('Content-Length'));
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
            $served++;
        }
        $this->assertGreaterThanOrEqual(2, $served);
    }

    public function test_a_repeat_request_with_the_etag_is_a_304_and_a_tampered_address_is_refused(): void
    {
        [$deck] = $this->deckWithStoredPictures();
        $url = $this->relative((new StudyDeckImageUrls())->hydrate($deck, self::TENANT)['slides'][array_key_first(array_filter($deck['slides'], fn ($s) => isset($s['image']['url'])))]['image']['url']);

        $first = $this->get($url);
        $etag = $first->headers->get('ETag');
        $this->get($url, ['If-None-Match' => $etag])->assertStatus(304);

        $this->get(str_replace('t=' . self::TENANT, 't=8', $url))->assertStatus(403);   // the signature covers the school
        $this->get(preg_replace('#/images/\d+#', '/images/999', $url))->assertStatus(403);   // and the picture
    }

    public function test_a_school_that_does_not_own_a_picture_is_refused_even_with_a_valid_signature(): void
    {
        $put = (new StudyDeckImages())->put($this->image('png', 200, 200, 4), self::TENANT);   // school 7's picture

        $own = (new StudyDeckImageUrls())->signed($put['id'], self::TENANT);
        $notLms = (new StudyDeckImageUrls())->signed($put['id'], 8);   // a validly signed address issued to school 8, which has no claim on it
        $missing = (new StudyDeckImageUrls())->signed(4242, self::TENANT);

        $this->get($this->relative($own))->assertOk();
        $this->get($this->relative($notLms))->assertStatus(403);
        $this->get($this->relative($missing))->assertStatus(404);
    }

    public function test_the_platform_library_is_visible_to_a_school_with_the_lms_only(): void
    {
        $platform = (new StudyDeckImages())->put($this->image('png', 200, 200, 6), StudyDeckImages::PLATFORM_TENANT);

        $this->get($this->relative((new StudyDeckImageUrls())->signed($platform['id'], self::TENANT)))->assertOk();
        $this->get($this->relative((new StudyDeckImageUrls())->signed($platform['id'], 8)))->assertStatus(403);
    }

    public function test_the_image_urls_endpoint_answers_in_the_shape_the_player_reads_and_only_for_pictures_the_school_may_see(): void
    {
        [, $byId] = $this->deckWithStoredPictures();
        $ids = array_keys($byId);
        $foreign = (new StudyDeckImages())->put($this->image('png', 120, 120, 8), 8)['id'];   // school 8's, not school 7's

        $response = $this->postJson('/api/lms-study-deck/image-urls', ['sub_institute_id' => self::TENANT, 'image_ids' => array_merge($ids, [$foreign, 5555])]);

        $response->assertOk()->assertJsonPath('status_code', 1);
        $data = $response->json('data');
        $this->assertEqualsCanonicalizing(array_map('strval', $ids), array_keys($data), 'keys are the picture ids as strings; foreign and missing ids are absent');
        foreach ($data as $id => $address) {
            $this->get($this->relative($address))->assertOk();
        }
        $this->postJson('/api/lms-study-deck/image-urls', ['sub_institute_id' => self::TENANT, 'image_ids' => []])->assertStatus(422);

        if (($dir = getenv('STUDY_DECK_EXPORT_DIR')) && is_dir($dir)) {
            [$deck] = [json_decode((string) file_get_contents(base_path('tests/Fixtures/study-deck-golden.json')), true)];
            $map = [];
            foreach ($ids as $n => $id) {
                $map[StudyDeckImages::ref($n + 1)] = StudyDeckImages::ref($id);
            }
            array_walk_recursive($deck, function (&$v) use ($map) {
                if (is_string($v) && isset($map[$v])) {
                    $v = $map[$v];
                }
            });
            file_put_contents($dir . '/stored-deck.json', json_encode($deck));                                       // what the sidecar holds: references
            file_put_contents($dir . '/image-urls-response.json', $response->getContent());                          // what /image-urls answers
            file_put_contents($dir . '/hydrated-deck.json', json_encode((new StudyDeckImageUrls())->hydrate($deck, self::TENANT)));   // what /lms-study-deck sends
        }
    }
}
