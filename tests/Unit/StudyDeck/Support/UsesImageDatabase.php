<?php

namespace Tests\Unit\StudyDeck\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives a test the study-deck picture tables in a throw-away in-memory SQLite database.
 *
 * phpunit.xml points every test at the live shared vivek_erp, so a test that touches the picture store must NOT
 * use the default connection. This makes SQLite-in-memory the default connection for the test, creates the tables
 * from the REAL migration (so the migration itself is exercised), and refuses to continue if the switch did not take.
 * Only the tables a test needs are created; nothing is ever sent to the shared database.
 */
trait UsesImageDatabase
{
    protected function useImageDatabase(): void
    {
        config(['database.connections.study_deck_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        config(['database.default' => 'study_deck_test']);
        DB::purge('study_deck_test');
        Schema::clearResolvedInstance('db.schema');

        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'a picture-store test must never run on the shared database');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        // MySQL reads a backslash in LIKE as an escape (`study\_deck\_%`); SQLite does not unless told to. Make SQLite agree,
        // so the code's own queries behave here as they do on the real database.
        DB::connection()->getPdo()->sqliteCreateFunction('like', function (?string $pattern, ?string $value): int {
            $regex = '';
            for ($i = 0, $n = strlen((string) $pattern); $i < $n; $i++) {
                $c = $pattern[$i];
                if ($c === '\\' && $i + 1 < $n) {
                    $regex .= preg_quote($pattern[++$i], '/');
                } else {
                    $regex .= $c === '%' ? '.*' : ($c === '_' ? '.' : preg_quote($c, '/'));
                }
            }

            return preg_match('/^' . $regex . '$/is', (string) $value) ? 1 : 0;
        }, 2);

        Schema::create('school_setup', function (Blueprint $t) {
            $t->integer('Id')->primary();
            $t->string('is_lms')->nullable();
        });

        (require dirname(__DIR__, 4) . '/database/migrations/2026_10_09_160000_create_study_deck_images_tables.php')->up();
    }

    /** A school, with or without the LMS (which lets it see the platform library's pictures). */
    protected function school(int $id, bool $lms = false): void
    {
        DB::table('school_setup')->updateOrInsert(['Id' => $id], ['is_lms' => $lms ? 'Y' : 'N']);
    }

    /** A real picture of a given colour. */
    protected function pngBytes(int $shade = 10, int $w = 32, int $h = 18): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, $shade, 80, 160));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }
}
