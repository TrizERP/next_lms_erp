<?php

namespace Tests\Support;

use App\Http\Controllers\api\lms\PrayogshalaSimulationApiController;
use App\Services\lms\Simulation\SimulationAssetService;
use App\Services\lms\Simulation\SimulationStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Shared setup for the Prayogshala Simulation Learning tests.
 *
 * EVERYTHING RUNS ON IN-MEMORY SQLITE, FORCED HERE. phpunit.xml leaves its sqlite lines commented out, so a test that touches the DB would otherwise
 * write to the shared database. bootSimulations() swaps the default connection to sqlite and REFUSES to continue unless that took, and it fakes the
 * `digitalocean` disk, so no test can reach a real database or the Spaces bucket.
 *
 * It creates the minimal K12 tables the feature reads (school_setup, standard, subject, chapter_master, topic_master, lms_concept) and runs the two
 * REAL simulation migrations, so a migration bug fails a test. The controller's middleware (api.session, lms.auth, perm) needs a real JWT and is
 * not run: act() hands the controller the session `api.session` would have hydrated, and the route table is asserted separately.
 *
 * THE WORLD
 *   tenants   1 = the platform tenant (is_Lms 'N', as in the real database), 7 = an is_Lms school, 8 = a school that is not linked to the platform
 *   concepts  5, 6 (tenant 1, chapter 100, topics 11 and 12), 7 and 8 (tenant 1, chapter 200, no topic), 9 (tenant 8's own, chapter 300)
 */
trait SimulationTestHelpers
{
    /** @var list<string> temp files made by tmpFile(), removed by tearDownSimulations() */
    private array $simTmp = [];

    protected function bootSimulations(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'lms_content.platform_sub_institute_ids' => [1],
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a real database');
        Storage::fake('digitalocean');

        Schema::create('school_setup', function ($t) {
            $t->integer('Id')->primary();
            $t->string('is_Lms')->nullable();
        });
        Schema::create('standard', function ($t) {
            $t->integer('id')->primary();
            $t->string('name');
        });
        Schema::create('subject', function ($t) {
            $t->integer('id')->primary();
            $t->string('subject_name');
        });
        Schema::create('chapter_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('sub_institute_id');
            $t->integer('grade_id')->nullable();
            $t->integer('standard_id');
            $t->integer('subject_id');
            $t->string('chapter_name');
        });
        Schema::create('topic_master', function ($t) {
            $t->integer('id')->primary();
            $t->integer('chapter_id');
            $t->string('name');
            $t->integer('topic_sort_order')->default(0);
        });
        Schema::create('lms_concept', function ($t) {
            $t->integer('id')->primary();
            $t->integer('sub_institute_id');
            $t->integer('standard_id');
            $t->integer('subject_id');
            $t->integer('chapter_id');
            $t->integer('topic_id')->nullable();
            $t->string('name')->nullable();
            $t->string('review_status')->nullable();
        });
        // the real migrations, so the tests also prove they create what the code reads
        (require base_path('database/migrations/2026_10_28_100000_create_lms_simulation_tables.php'))->up();
        (require base_path('database/migrations/2026_10_28_100100_create_lms_simulation_concept_table.php'))->up();

        DB::table('school_setup')->insert([['Id' => 1, 'is_Lms' => 'N'], ['Id' => 7, 'is_Lms' => 'Y'], ['Id' => 8, 'is_Lms' => 'N']]);
        DB::table('standard')->insert([['id' => 42, 'name' => '9'], ['id' => 43, 'name' => '10']]);
        DB::table('subject')->insert([['id' => 3975, 'subject_name' => 'Science']]);
        DB::table('chapter_master')->insert([
            ['id' => 100, 'sub_institute_id' => 1, 'grade_id' => 12, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_name' => 'Ch A'],
            ['id' => 200, 'sub_institute_id' => 1, 'grade_id' => 13, 'standard_id' => 43, 'subject_id' => 3975, 'chapter_name' => 'The Human Eye and the Colourful World'],
            ['id' => 300, 'sub_institute_id' => 8, 'grade_id' => 12, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_name' => 'B only chapter'],
        ]);
        DB::table('topic_master')->insert([
            ['id' => 11, 'chapter_id' => 100, 'name' => 'Topic One', 'topic_sort_order' => 1],
            ['id' => 12, 'chapter_id' => 100, 'name' => 'Topic Two', 'topic_sort_order' => 2],
            ['id' => 31, 'chapter_id' => 300, 'name' => 'B topic', 'topic_sort_order' => 1],
        ]);
        DB::table('lms_concept')->insert([
            ['id' => 5, 'sub_institute_id' => 1, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_id' => 100, 'topic_id' => 11, 'name' => 'Concept Five', 'review_status' => 'verified'],
            ['id' => 6, 'sub_institute_id' => 1, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_id' => 100, 'topic_id' => 12, 'name' => 'Concept Six', 'review_status' => 'flagged'],
            ['id' => 7, 'sub_institute_id' => 1, 'standard_id' => 43, 'subject_id' => 3975, 'chapter_id' => 200, 'topic_id' => null, 'name' => 'Human Eye Structure', 'review_status' => 'legacy'],
            ['id' => 8, 'sub_institute_id' => 1, 'standard_id' => 43, 'subject_id' => 3975, 'chapter_id' => 200, 'topic_id' => null, 'name' => "Myopia \u{2019}Nearsightedness\u{2019} (short)", 'review_status' => 'auto_accepted'],
            ['id' => 9, 'sub_institute_id' => 8, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_id' => 300, 'topic_id' => 31, 'name' => 'B concept', 'review_status' => 'verified'],
        ]);
    }

    protected function tearDownSimulations(): void
    {
        foreach ($this->simTmp as $file) {
            is_dir($file) ? @rmdir($file) : @unlink($file);
        }
        $this->simTmp = [];
    }

    // ── calling the controller as the api.session middleware leaves things ──────

    /**
     * Calls a controller action with the session the middleware would have hydrated. `$body` is a JSON string (for create/update, which read the raw
     * body), an array (form input, for links and review) or null; `$query` is the query string.
     *
     * @return array{0: int, 1: array<string, mixed>|null}  the status and the decoded JSON body (null for a stream)
     */
    protected function act(string $action, int $tenant, array $args = [], array|string|null $body = null, array $query = [], string $profile = 'Teacher', bool $student = false, int $user = 55, ?array $files = null): array
    {
        $response = $this->actRaw($action, $tenant, $args, $body, $query, $profile, $student, $user, $files);

        return [$response->getStatusCode(), $response instanceof \Illuminate\Http\JsonResponse ? $response->getData(true) : null];
    }

    protected function actRaw(string $action, int $tenant, array $args = [], array|string|null $body = null, array $query = [], string $profile = 'Teacher', bool $student = false, int $user = 55, ?array $files = null, array $server = [])
    {
        $method = in_array($action, ['index', 'show', 'asset', 'versions', 'limits', 'links', 'concepts'], true) ? 'GET' : 'POST';
        $request = is_string($body)
            ? Request::create('/x', $method, $query, [], [], ['CONTENT_TYPE' => 'application/json'] + $server, $body)
            : Request::create('/x', $method, $method === 'GET' ? $query : (($body ?? []) + $query), [], $files ?? [], $server);
        $store = app('session.store');
        $store->flush();
        $request->setLaravelSession($store);
        $store->put('sub_institute_id', $tenant);
        $store->put('user_id', $user);
        $store->put('syear', 2026);
        $store->put('user_profile_name', $profile);
        $store->put('is_student', $student);

        return app(PrayogshalaSimulationApiController::class)->{$action}($request, ...$args);
    }

    // ── specifications, files, simulations ────────────────────────────────────

    /** The canonical text of a valid specification (the small example from the runtime, under another id and title), naming these models. */
    protected function specText(string $id = 'tiny-api', string $title = 'Tiny API', array $models = []): string
    {
        $spec = json_decode((string) file_get_contents(base_path('tests/Fixtures/simulations/tiny-api.json')));
        $spec->id = $id;
        $spec->title = $title;
        if ($models !== []) {
            $spec->assets = (object) ['models' => array_map(fn ($m) => (object) $m, $models)];
        }

        return (new SimulationStore())->parse(json_encode($spec))['json'];
    }

    /** @return array{id: string, sha256: string, name: string} */
    protected function model(string $sha, string $id = 'eye'): array
    {
        return ['id' => $id, 'sha256' => $sha, 'name' => 'Human eye'];
    }

    /** A binary glTF 2: header, a JSON part (a different `$label` is a different file) and, when given, a BIN part. */
    protected function glb(string $label = 'eye', array $gltf = [], ?string $bin = null): string
    {
        $json = json_encode($gltf + ['asset' => ['version' => '2.0'], 'extras' => ['label' => $label]], JSON_UNESCAPED_SLASHES);
        $json .= str_repeat(' ', (4 - strlen($json) % 4) % 4);
        $body = pack('V', strlen($json)) . 'JSON' . $json;
        if ($bin !== null) {
            $bin .= str_repeat("\0", (4 - strlen($bin) % 4) % 4);
            $body .= pack('V', strlen($bin)) . "BIN\0" . $bin;
        }

        return 'glTF' . pack('V', 2) . pack('V', 12 + strlen($body)) . $body;
    }

    /** A PNG that is only a signature and a header: enough to tell its size. */
    protected function png(int $w = 8, int $h = 8, string $label = ''): string
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));

        return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 6, 0, 0, 0)) . ($label === '' ? '' : $chunk('tEXt', "Comment\0" . $label));
    }

    protected function tmpFile(string $bytes, string $extension = ''): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'simk12');
        $this->simTmp[] = $file;
        if ($extension !== '') {
            rename($file, $file .= '.' . $extension);
            $this->simTmp[] = $file;
        }
        file_put_contents($file, $bytes);

        return $file;
    }

    /** Puts `$bytes` on the (fake) disk under the K12 prefix and registers it for `$tenant`, as an upload or the copy command does. Gives its sha256. */
    protected function registerAsset(string $bytes, ?int $tenant = 1, array $extra = []): string
    {
        $store = new SimulationStore();
        $sha = hash('sha256', $bytes);
        $type = $store->assetType(substr($bytes, 0, 12));
        $path = $store->assetPath($sha, $type['ext']);
        Storage::disk('digitalocean')->put($path, $bytes);
        app(SimulationAssetService::class)->record(null, $tenant, $sha, ['kind' => $type['kind'], 'mime' => $type['mime'], 'bytes' => strlen($bytes)], $path, $extra + [
            'name' => 'A model', 'licence' => 'CC BY 4.0', 'credit' => 'Model by A. Author', 'source' => 'https://example.org/model',
        ], 1);

        return $sha;
    }

    /** A simulation of `$tenant` made through the store, like the screen does. */
    protected function makeSimulation(string $slug = 'tiny-api', string $status = 'published', int $tenant = 1, array $models = []): object
    {
        return (new SimulationStore())->create($tenant, 55, $this->specText($slug, 'Sim ' . $slug, $models), ['status' => $status]);
    }

    /** Links a simulation to concepts through the real service. Gives the link rows by concept id. */
    protected function linkTo(object $sim, array $conceptIds, string $status = 'review'): \Illuminate\Support\Collection
    {
        app(\App\Services\lms\Simulation\SimulationLinks::class)->replace($sim, array_map(fn ($id) => ['concept_id' => $id], $conceptIds), 55);
        DB::table('lms_simulation_concept')->where('simulation_id', $sim->id)->update(['review_status' => $status]);

        return DB::table('lms_simulation_concept')->where('simulation_id', $sim->id)->get()->keyBy('concept_id');
    }

    protected function row(string $table): int
    {
        return DB::table($table)->count();
    }

    // ── packages ─────────────────────────────────────────────────────────────

    /** The manifest entry of a file, as a package lists it (the object sits at hp_erp's key). */
    protected function manifestEntry(string $bytes, array $over = []): array
    {
        $sha = hash('sha256', $bytes);
        $type = (new SimulationStore())->assetType(substr($bytes, 0, 12));

        return $over + [
            'sha256' => $sha, 'kind' => $type['kind'], 'mime' => $type['mime'], 'bytes' => strlen($bytes), 'name' => 'A model', 'licence' => 'CC BY 4.0',
            'credit' => 'Model by A. Author', 'source_url' => 'https://example.org/model', 'source_key' => "sim_assets/platform/{$sha}.{$type['ext']}",
        ];
    }

    /** A package array. `$entries` are manifest entries; the spec names all of them. */
    protected function buildPackage(string $slug = 'pkg-sim', array $entries = [], array $over = [], string $title = 'Package sim'): array
    {
        $text = $this->specText($slug, $title, array_map(fn ($e, $i) => ['id' => 'm' . $i, 'sha256' => $e['sha256'], 'name' => $e['name']], $entries, array_keys($entries)));

        return $over + [
            'format' => 'k12.sim-package/1', 'slug' => $slug, 'title' => $title, 'subtitle' => 'Sub', 'summary' => 'Sum', 'category' => 'Science', 'tags' => 'a,b', 'audience' => 'All',
            'minutes' => 5, 'difficulty' => 'intro', 'missions' => 1, 'cover' => "simulations/covers/{$slug}.webp", 'spec_json' => $text, 'spec_sha256' => hash('sha256', $text),
            'assets' => $entries, 'source' => ['host' => 'dev', 'simulation_id' => 1, 'version' => 4],
        ];
    }

    /** A folder holding these packages as `<slug>.package.json`; removed at the end of the test. */
    protected function packageDir(array $packages): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'simpk' . uniqid();
        mkdir($dir);
        foreach ($packages as $package) {
            $file = $dir . DIRECTORY_SEPARATOR . $package['slug'] . '.package.json';
            file_put_contents($file, json_encode($package, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->simTmp[] = $file;
        }
        $this->simTmp[] = $dir;

        return $dir;
    }
}
