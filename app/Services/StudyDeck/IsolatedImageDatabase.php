<?php

namespace App\Services\StudyDeck;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An opt-in, throw-away home for the pictures of a study-deck DRY RUN (lms:generate-study-deck --isolated-images=<file>.sqlite).
 *
 * The normal run keeps its pictures in the application's own `study_deck_images` table, which on this estate is a shared database. A dry
 * run for review should not add rows there. This opens a SECOND connection to a local SQLite file and gives the same StudyDeckImages class
 * that connection, so the picture store, the search cache and every read-back behave as they do in production, on a file nobody else uses.
 *
 * What it guarantees
 *   - The tables are created on the isolated connection only (never through the default one), and only after the connection is proved to be
 *     SQLite on the requested file.
 *   - A file that already holds something else (a real application database, say) is refused, as is the application's own SQLite file.
 *   - The bundle it produces carries a marker, and lms:store-study-deck refuses a marked bundle: its picture references are numbers in this
 *     file, which mean other pictures (or none) in the shared table.
 *   - A player copy is made by turning those references into picture files beside deck.json (the player's existing local-review format), so
 *     the player needs neither the isolated file nor the shared table.
 *
 * What it does not do: the run still READS the shared database (the chapter's concepts, the question bank, the model key) and the model client
 * still records its own usage there. It stops the run writing pictures, and nothing else.
 */
final class IsolatedImageDatabase
{
    public const CONNECTION = 'study_deck_isolated';

    /** Written into a bundle made with isolated pictures; lms:store-study-deck refuses a bundle that has it. */
    public const MARKER = 'isolated-images.json';

    private const OWN_TABLES = ['study_deck_images', 'study_deck_image_links', 'sqlite_sequence'];

    /**
     * Open (and, when new, create) the isolated picture store.
     *
     * @throws \InvalidArgumentException when the path is not a safe place for it
     */
    public static function open(string $path): StudyDeckImages
    {
        $path = self::checkedPath($path);
        if (!is_file($path) && @file_put_contents($path, '') === false) {   // Laravel's SQLite connector will not create the file itself
            throw new \InvalidArgumentException("--isolated-images: could not create $path.");
        }

        config(['database.connections.' . self::CONNECTION => [
            'driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge(self::CONNECTION);

        $connection = DB::connection(self::CONNECTION);
        if ($connection->getDriverName() !== 'sqlite' || realpath((string) $connection->getDatabaseName()) !== realpath($path)) {
            throw new \RuntimeException('The isolated picture store is not the SQLite file that was asked for; nothing was written.');
        }

        self::createTables();

        return new StudyDeckImages(self::CONNECTION);
    }

    /** The same two tables as database/migrations/2026_10_09_160000_create_study_deck_images_tables.php, on the isolated connection (a test keeps them equal). */
    private static function createTables(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (!$schema->hasTable('study_deck_images')) {
            $schema->create('study_deck_images', function (Blueprint $table) {
                $table->id();
                $table->integer('sub_institute_id');
                $table->integer('chapter_id')->nullable();
                $table->char('sha256', 64);
                $table->string('mime_type', 32);
                $table->string('format', 8);
                $table->unsignedInteger('byte_size');
                $table->unsignedSmallInteger('width');
                $table->unsignedSmallInteger('height');
                $table->char('source_url_hash', 40)->nullable();
                $table->string('source_url', 2048)->nullable();
                $table->binary('data');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['sub_institute_id', 'sha256'], 'uq_study_deck_images_school_sha256');
                $table->index('chapter_id', 'idx_study_deck_images_chapter');
                $table->index(['sub_institute_id', 'source_url_hash'], 'idx_study_deck_images_source');
                $table->index('created_at', 'idx_study_deck_images_created');
            });
        }
        if (!$schema->hasTable('study_deck_image_links')) {
            $schema->create('study_deck_image_links', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('image_id');
                $table->integer('content_id');
                $table->integer('chapter_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['image_id', 'content_id'], 'uq_study_deck_image_links_pair');
                $table->index('content_id', 'idx_study_deck_image_links_content');
                $table->foreign('image_id', 'fk_study_deck_image_links_image')->references('id')->on('study_deck_images')->cascadeOnDelete();
            });
        }
    }

    /**
     * @return string the absolute path of a file that is safe to use
     * @throws \InvalidArgumentException
     */
    public static function checkedPath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0") || in_array(strtolower($path), [':memory:', 'memory'], true)) {
            throw new \InvalidArgumentException('--isolated-images needs the path of a SQLite file (for example .../study-deck-dry-run.sqlite).');
        }
        if (!preg_match('/\.sqlite3?$/i', $path)) {
            throw new \InvalidArgumentException('--isolated-images must name a file ending in .sqlite, so it cannot be mistaken for a real database.');
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \InvalidArgumentException("--isolated-images: the folder $dir does not exist and could not be made.");
        }
        $absolute = rtrim((string) realpath($dir), '/\\') . DIRECTORY_SEPARATOR . basename($path);

        // Never the application's own SQLite database, whichever connection names it.
        foreach ((array) config('database.connections', []) as $name => $config) {
            if (($config['driver'] ?? null) === 'sqlite' && $name !== self::CONNECTION && !empty($config['database']) && $config['database'] !== ':memory:'
                && realpath((string) $config['database']) !== false && realpath((string) $config['database']) === realpath($absolute)) {
                throw new \InvalidArgumentException("--isolated-images: $absolute is the application's own '$name' database; choose a separate file.");
            }
        }

        // A file that exists must be empty or already ours: never adopt a database that holds other tables.
        if (is_file($absolute) && filesize($absolute) > 0) {
            try {
                $pdo = new \PDO('sqlite:' . $absolute);
                $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Throwable) {
                throw new \InvalidArgumentException("--isolated-images: $absolute exists and is not a SQLite database.");
            } finally {
                unset($pdo);
            }
            $foreign = array_values(array_diff($tables, self::OWN_TABLES));
            if ($foreign !== []) {
                throw new \InvalidArgumentException("--isolated-images: $absolute already holds other tables (" . implode(', ', array_slice($foreign, 0, 3)) . '); choose a new file.');
            }
        }

        return $absolute;
    }

    // -----------------------------------------------------------------------------------------------------
    // The bundle

    /** Mark a review bundle as made with isolated pictures. */
    public static function markBundle(string $bundleDir, string $databasePath): void
    {
        file_put_contents(rtrim($bundleDir, '/\\') . '/' . self::MARKER, json_encode([
            'isolated_pictures' => true,
            'database' => $databasePath,
            'note' => 'The picture references in deck.json are ids in this SQLite file, not in the shared study_deck_images table. This bundle cannot be stored; generate again without --isolated-images to store a deck.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function isIsolatedBundle(string $bundleDir): bool
    {
        return is_file(rtrim($bundleDir, '/\\') . '/' . self::MARKER);
    }

    /**
     * A copy of the bundle's deck the local player can serve with no API and no database: every picture reference becomes
     * `images/<sha256>.<ext>`, and the picture itself is written there. The player resolves such a path beside deck.json (its local-review format).
     *
     * Never overwrites: a folder that already holds a deck.json is refused, so an existing review copy (or a tracked one) is not touched.
     *
     * @return array{deck:string,pictures:int,bytes:int}
     * @throws \RuntimeException
     */
    public static function exportPlayer(string $bundleDir, string $target, StudyDeckImages $images): array
    {
        $target = rtrim($target, '/\\');
        if (is_file($target . '/deck.json')) {
            throw new \RuntimeException("$target already holds a deck.json; it is not overwritten. Choose a new folder.");
        }
        $deck = json_decode((string) @file_get_contents(rtrim($bundleDir, '/\\') . '/deck.json'), true);
        if (!is_array($deck)) {
            throw new \RuntimeException('The bundle has no readable deck.json.');
        }

        $files = [];
        $missing = [];
        $walk = function (array $node) use (&$walk, &$files, &$missing, $images): array {
            foreach ($node as $key => $value) {
                if (is_array($value)) {
                    $node[$key] = $walk($value);
                } elseif ($key === 'url' && ($id = StudyDeckImages::idFromRef($value)) !== null) {
                    $meta = $images->meta($id);
                    $bytes = $meta ? $images->bytes($id) : null;
                    if ($meta === null || $bytes === null) {
                        $missing[] = $value;
                        continue;
                    }
                    $name = 'images/' . $meta['sha256'] . '.' . ($meta['format'] === 'jpeg' ? 'jpg' : $meta['format']);
                    $files[$name] = $bytes;
                    $node[$key] = $name;
                }
            }

            return $node;
        };
        $exported = $walk($deck);
        if ($missing !== []) {
            throw new \RuntimeException('These pictures are not in the isolated store, so no player copy was made: ' . implode(', ', array_slice(array_unique($missing), 0, 5)));
        }

        if (!is_dir($target . '/images') && !@mkdir($target . '/images', 0775, true) && !is_dir($target . '/images')) {
            throw new \RuntimeException("Could not make $target/images.");
        }
        $bytes = 0;
        foreach ($files as $name => $data) {
            file_put_contents($target . '/' . $name, $data);
            $bytes += strlen($data);
        }
        file_put_contents($target . '/deck.json', json_encode($exported, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return ['deck' => $target . '/deck.json', 'pictures' => count($files), 'bytes' => $bytes];
    }
}
