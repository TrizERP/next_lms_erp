<?php

namespace Tests\Unit\StudyDeck;

use App\Console\Commands\LMS\GenerateStudyDeckCommand;
use App\Services\StudyDeck\IsolatedImageDatabase;
use App\Services\StudyDeck\StudyDeckImages;
use App\Services\StudyDeck\StudyImageStores;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Unit\StudyDeck\Support\BootSafeTestCase;
use Tests\Unit\StudyDeck\Support\UsesImageDatabase;

/**
 * The opt-in isolated picture store of a dry run (lms:generate-study-deck --isolated-images=<file>.sqlite).
 *
 * "Shared database" in these tests is a MySQL connection pointed at 127.0.0.1:1, where nothing listens. Isolated mode must work with that as
 * the default connection, and must never so much as create the connection object (DB::getConnections() would then hold it).
 * Everything else is a temp folder; nothing reaches a real database, Spaces or the network.
 */
class IsolatedImageStoreTest extends BootSafeTestCase
{
    use UsesImageDatabase;

    private string $tmp = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'isolated-store-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0775, true);
        config(['logging.default' => 'null']);
    }

    protected function tearDown(): void
    {
        DB::purge(IsolatedImageDatabase::CONNECTION);
        $this->rm($this->tmp);
        parent::tearDown();
    }

    private function rm(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);

            return;
        }
        foreach (glob($path . '/{,.}*', GLOB_BRACE) ?: [] as $f) {
            if (!in_array(basename($f), ['.', '..'], true)) {
                $this->rm($f);
            }
        }
        @rmdir($path);
    }

    /** Make an unreachable MySQL the application's default connection: any touch of it fails, and creating it is visible. */
    private function unreachableSharedDatabase(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => 1,
            'database.connections.mysql.database' => 'shared_must_not_be_touched',
        ]);
        DB::purge('mysql');
        Schema::clearResolvedInstance('db.schema');
    }

    private function sharedWasNeverConnected(): void
    {
        $this->assertArrayNotHasKey('mysql', DB::getConnections(), 'the shared (default) connection was created: isolated mode reached for it');
    }

    private function png(int $w, int $h, int $seed = 1): string
    {
        $im = imagecreatetruecolor($w, $h);
        mt_srand($seed);
        for ($i = 0; $i < ($w * $h) / 30; $i++) {
            imagesetpixel($im, mt_rand(0, $w - 1), mt_rand(0, $h - 1), imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
        ob_start();
        imagepng($im, null, 0);

        return (string) ob_get_clean();
    }

    private function jpeg(int $w, int $h, int $seed = 2): string
    {
        $im = imagecreatetruecolor($w, $h);
        mt_srand($seed);
        for ($i = 0; $i < ($w * $h) / 30; $i++) {
            imagesetpixel($im, mt_rand(0, $w - 1), mt_rand(0, $h - 1), imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
        ob_start();
        imagejpeg($im, null, 90);

        return (string) ob_get_clean();
    }

    private function rowsIn(string $sqliteFile, string $table = 'study_deck_images'): int
    {
        $pdo = new \PDO('sqlite:' . $sqliteFile);

        return (int) $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Isolation

    public function test_every_picture_operation_works_with_an_unreachable_shared_database_and_never_creates_its_connection(): void
    {
        $this->unreachableSharedDatabase();
        $file = $this->tmp . '/dry.sqlite';

        $images = IsolatedImageDatabase::open($file);
        $store = StudyImageStores::database(7, 8592, $images);

        $png = $this->png(320, 240);
        $big = $this->png(900, 700, 5);
        $this->assertGreaterThan(StudyDeckImages::CHUNK_BYTES, strlen($big), 'large enough to be read in slices');

        // write
        $ref = $store->store($png, 'image/png', 'https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/X.svg/960px-X.svg.png');
        $put = $images->put($big, 7, 8592);
        // read back, every way the pipeline reads
        $this->assertSame($png, $images->bytes(StudyDeckImages::idFromRef($ref)));
        $this->assertSame($big, implode('', iterator_to_array($images->chunks($put['id'], $put['bytes']), false)));
        $this->assertTrue($images->verify($put['id'], $put['sha256'], $put['bytes']));
        $this->assertSame($png, $store->cached('https://upload.wikimedia.org/wikipedia/commons/thumb/a/ab/X.svg/960px-X.svg.png')['bytes']);
        $this->assertNotNull($images->meta($put['id']));
        $this->assertSame([7], $images->visibleTenants(7), 'the school table of the shared database is not read');
        $this->assertNotNull($images->visibleMeta($put['id'], 7));
        $this->assertSame(2, $images->link(61549, [$put['id'], StudyDeckImages::idFromRef($ref)]));
        $this->assertCount(0, $images->orphans(0)['ids']);
        $this->assertSame(1, $images->deleteUnlinked([999]) + 1);

        $this->sharedWasNeverConnected();
        $this->assertSame(2, $this->rowsIn($file), 'both pictures are in the local file');
    }

    public function test_a_normal_instance_still_uses_the_application_database_and_an_isolated_one_never_does(): void
    {
        $this->useImageDatabase();   // the application's database for this test: in-memory SQLite with the real tables
        $file = $this->tmp . '/dry.sqlite';
        $isolated = IsolatedImageDatabase::open($file);

        $normal = new StudyDeckImages();
        $this->assertFalse($normal->isIsolated());
        $this->assertNull($normal->connectionName());

        $a = $normal->put($this->png(200, 150, 11), 7);
        $b = $isolated->put($this->png(200, 150, 12), 7);

        $this->assertSame(1, DB::table('study_deck_images')->count(), 'the normal picture is in the application database');
        $this->assertSame(1, DB::table('study_deck_images')->where('id', $a['id'])->count());
        $this->assertSame(1, $this->rowsIn($file), 'only the isolated picture is in the local file');
        $this->assertNull(DB::table('study_deck_images')->where('sha256', $b['sha256'])->first(), 'the isolated picture never reached the application database');
        $pdo = new \PDO('sqlite:' . $file);
        $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM study_deck_images WHERE sha256 = '" . $a['sha256'] . "'")->fetchColumn(), 'and the normal picture is not in the local file');
    }

    public function test_the_command_builds_its_picture_store_from_the_application_database_unless_the_option_is_given(): void
    {
        $this->useImageDatabase();
        $command = new GenerateStudyDeckCommand();

        $inner = function (object $store): StudyDeckImages {
            return (new \ReflectionProperty($store, 'images'))->getValue($store);
        };

        $normal = $inner($command->pictureStore(7, 8592));
        $this->assertFalse($normal->isIsolated(), 'no option: the application database, exactly as before');

        $isolated = $inner($command->pictureStore(7, 8592, IsolatedImageDatabase::open($this->tmp . '/dry.sqlite')));
        $this->assertTrue($isolated->isIsolated());
        $this->assertSame(IsolatedImageDatabase::CONNECTION, $isolated->connectionName());
    }

    public function test_the_isolated_tables_have_the_same_columns_and_indexes_as_the_real_migration(): void
    {
        $this->useImageDatabase();   // runs the REAL migration on the default connection
        IsolatedImageDatabase::open($this->tmp . '/dry.sqlite');

        foreach (['study_deck_images', 'study_deck_image_links'] as $table) {
            $real = Schema::getColumns($table);
            $mine = Schema::connection(IsolatedImageDatabase::CONNECTION)->getColumns($table);
            $shape = fn (array $cols) => array_map(fn ($c) => [$c['name'], $c['type_name'], (bool) $c['nullable']], $cols);
            $this->assertSame($shape($real), $shape($mine), "$table columns");

            $idx = fn (array $i) => collect($i)->map(fn ($x) => [$x['name'], $x['columns'], (bool) $x['unique'], (bool) $x['primary']])->sortBy(fn ($x) => $x[0])->values()->all();
            $this->assertSame($idx(Schema::getIndexes($table)), $idx(Schema::connection(IsolatedImageDatabase::CONNECTION)->getIndexes($table)), "$table indexes");
        }
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The option and the path

    public function test_unsafe_paths_are_refused(): void
    {
        $other = $this->tmp . '/other.sqlite';
        $pdo = new \PDO('sqlite:' . $other);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        unset($pdo);

        $own = $this->tmp . '/app.sqlite';
        touch($own);
        config(['database.connections.sqlite.database' => $own]);

        foreach ([
            '' => 'needs the path',
            ':memory:' => 'needs the path',
            $this->tmp . '/pictures.db' => 'ending in .sqlite',
            $this->tmp . '/notes.txt' => 'ending in .sqlite',
            $other => 'already holds other tables',
            $own => "application's own",
        ] as $path => $expected) {
            try {
                IsolatedImageDatabase::checkedPath((string) $path);
                $this->fail("accepted: '$path'");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($expected, $e->getMessage(), "for '$path'");
            }
        }
        $this->assertSame(realpath($this->tmp) . DIRECTORY_SEPARATOR . 'fresh.sqlite', IsolatedImageDatabase::checkedPath($this->tmp . '/fresh.sqlite'));
        $this->assertFileDoesNotExist($this->tmp . '/fresh.sqlite', 'checking a path creates nothing');
    }

    public function test_the_command_refuses_store_with_isolated_images_and_a_bad_path_before_any_database_is_touched(): void
    {
        $this->unreachableSharedDatabase();
        $file = $this->tmp . '/dry.sqlite';

        $this->artisan('lms:generate-study-deck', ['chapter' => 8592, '--isolated-images' => $file, '--store' => true])
            ->expectsOutputToContain('cannot be combined with --store')
            ->assertFailed();
        $this->assertFileDoesNotExist($file, 'a refused run created nothing');

        $this->artisan('lms:generate-study-deck', ['chapter' => 8592, '--isolated-images' => $this->tmp . '/pictures.db'])
            ->expectsOutputToContain('must name a file ending in .sqlite')
            ->assertFailed();

        $this->sharedWasNeverConnected();
    }

    public function test_the_store_command_refuses_a_bundle_made_with_isolated_pictures_even_as_a_dry_run(): void
    {
        $this->unreachableSharedDatabase();
        $bundle = $this->tmp . '/bundle';
        mkdir($bundle, 0775, true);
        file_put_contents($bundle . '/deck.json', '{}');
        IsolatedImageDatabase::markBundle($bundle, $this->tmp . '/dry.sqlite');
        $this->assertTrue(IsolatedImageDatabase::isIsolatedBundle($bundle));

        $this->artisan('lms:store-study-deck', ['chapter' => 8592, '--bundle' => $bundle, '--dry-run' => true])
            ->expectsOutputToContain('cannot be stored')
            ->assertFailed();

        $this->sharedWasNeverConnected();
        $this->assertFalse(IsolatedImageDatabase::isIsolatedBundle($this->tmp), 'an ordinary folder is not marked');
    }

    // ---------------------------------------------------------------------------------------------------------------
    // The player copy

    private function bundleWithPictures(StudyDeckImages $images): array
    {
        $a = $images->put($this->png(640, 400, 21), 7, 8592);
        $b = $images->put($this->jpeg(800, 500, 22), 7, 8592);
        $deck = [
            'version' => 3, 'chapter_id' => 8592,
            'slides' => [
                ['n' => 1, 'title' => 'A', 'image' => ['type' => 'photo', 'url' => $a['ref'], 'alt' => 'one']],
                ['n' => 2, 'title' => 'B', 'interaction' => ['nodes' => [
                    ['id' => 's1', 'image' => ['type' => 'diagram', 'url' => $b['ref'], 'alt' => 'two'], 'extra_images' => [['url' => $a['ref'], 'alt' => 'again']]],
                    ['id' => 's2'],
                ]]],
            ],
            'assets' => ['abc' => ['ref' => $a['ref']]],
        ];
        $bundle = $this->tmp . '/bundle';
        mkdir($bundle, 0775, true);
        file_put_contents($bundle . '/deck.json', json_encode($deck));

        return [$bundle, $deck, $a, $b];
    }

    public function test_the_player_copy_turns_references_into_picture_files_beside_the_deck(): void
    {
        $this->unreachableSharedDatabase();
        $images = IsolatedImageDatabase::open($this->tmp . '/dry.sqlite');
        [$bundle, $deck, $a, $b] = $this->bundleWithPictures($images);
        $before = sha1_file($bundle . '/deck.json');
        $target = $this->tmp . '/player/chapter-885922';

        $made = IsolatedImageDatabase::exportPlayer($bundle, $target, $images);

        $this->assertSame(2, $made['pictures']);
        $out = json_decode((string) file_get_contents($target . '/deck.json'), true);
        $one = 'images/' . $a['sha256'] . '.png';
        $two = 'images/' . $b['sha256'] . '.jpg';
        $this->assertSame($one, $out['slides'][0]['image']['url']);
        $this->assertSame($two, $out['slides'][1]['interaction']['nodes'][0]['image']['url']);
        $this->assertSame($one, $out['slides'][1]['interaction']['nodes'][0]['extra_images'][0]['url']);
        $this->assertSame($a['ref'], $out['assets']['abc']['ref'], 'only picture addresses are rewritten');
        foreach ([$one => $a, $two => $b] as $name => $meta) {
            $this->assertFileExists($target . '/' . $name);
            $this->assertSame($meta['sha256'], hash_file('sha256', $target . '/' . $name), 'the file is the stored picture, byte for byte');
            $info = getimagesize($target . '/' . $name);
            $this->assertSame([$meta['width'], $meta['height']], [$info[0], $info[1]]);
        }
        $this->assertSame($before, sha1_file($bundle . '/deck.json'), 'the review bundle itself is unchanged');
        $this->assertSame(0, preg_match('/study-deck-image:\d+"[^}]*"url"|"url":"study-deck-image:/', (string) file_get_contents($target . '/deck.json')), 'no picture address is left as a reference');
        $this->sharedWasNeverConnected();
    }

    public function test_the_player_copy_never_overwrites_and_writes_nothing_when_a_picture_is_missing(): void
    {
        $images = IsolatedImageDatabase::open($this->tmp . '/dry.sqlite');
        [$bundle] = $this->bundleWithPictures($images);

        // an existing deck.json (for example a tracked review copy) is left alone
        $taken = $this->tmp . '/taken';
        mkdir($taken, 0775, true);
        file_put_contents($taken . '/deck.json', 'KEEP');
        try {
            IsolatedImageDatabase::exportPlayer($bundle, $taken, $images);
            $this->fail('overwrote an existing deck.json');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not overwritten', $e->getMessage());
        }
        $this->assertSame('KEEP', file_get_contents($taken . '/deck.json'));
        $this->assertDirectoryDoesNotExist($taken . '/images');

        // a reference to a picture that is not in the isolated store
        $deck = json_decode((string) file_get_contents($bundle . '/deck.json'), true);
        $deck['slides'][0]['image']['url'] = 'study-deck-image:9999';
        file_put_contents($bundle . '/deck.json', json_encode($deck));
        $fresh = $this->tmp . '/fresh';
        try {
            IsolatedImageDatabase::exportPlayer($bundle, $fresh, $images);
            $this->fail('exported with a missing picture');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('study-deck-image:9999', $e->getMessage());
        }
        $this->assertDirectoryDoesNotExist($fresh, 'nothing was written');
    }
}
