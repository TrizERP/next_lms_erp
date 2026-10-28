<?php

namespace Tests\Feature;

use App\Services\lms\Simulation\SimulationException;
use App\Services\lms\Simulation\SimulationMapping;
use App\Services\lms\Simulation\SimulationPackageImporter;
use App\Services\lms\Simulation\SimulationStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SimulationTestHelpers;
use Tests\TestCase;

/**
 * Prayogshala Simulation Learning: the package importer (`lms:simulations-import`), the mapping import / sheet, and the reconcile check.
 *
 * RUNS ON IN-MEMORY SQLITE with the `digitalocean` disk FAKED (see SimulationTestHelpers).
 */
class PrayogshalaSimulationImportTest extends TestCase
{
    use SimulationTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootSimulations();
    }

    protected function tearDown(): void
    {
        $this->tearDownSimulations();
        parent::tearDown();
    }

    private function cmd(string $command, array $args = []): array
    {
        $code = Artisan::call($command, $args);

        return [$code, Artisan::output()];
    }

    private function counts(): array
    {
        return [$this->row('lms_simulation'), $this->row('lms_simulation_version'), $this->row('lms_simulation_concept'), $this->row('lms_simulation_asset')];
    }

    /** A package whose one file is registered AND on the disk, ready to import. */
    private function readyPackage(string $slug = 'pkg-sim', string $title = 'Package sim', ?string $label = null): array
    {
        $bytes = $this->glb($label ?? $slug);
        $sha = $this->registerAsset($bytes, 1);

        return $this->buildPackage($slug, [$this->manifestEntry($bytes)], [], $title) + ['_sha' => $sha];
    }

    // ── lms:simulations-import ───────────────────────────────────────────────

    public function test_the_import_is_a_dry_run_unless_apply_is_given(): void
    {
        $dir = $this->packageDir([$this->readyPackage()]);
        $before = $this->counts();

        [$code, $out] = $this->cmd('lms:simulations-import', ['path' => $dir]);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('DRY RUN', $out);
        $this->assertStringContainsString('would create', $out);
        $this->assertStringContainsString('lms_simulation           ' . str_pad('0', 6, ' ', STR_PAD_LEFT) . ' -> 1', $out, 'the report shows the rows it would make');
        $this->assertSame($before, $this->counts(), 'nothing written');
    }

    public function test_apply_creates_the_simulation_through_the_store_from_the_packages_spec_text(): void
    {
        $package = $this->readyPackage('eye-lab', 'Eye lab');
        $dir = $this->packageDir([$package]);

        [$code, $out] = $this->cmd('lms:simulations-import', ['path' => $dir, '--apply' => true, '--user' => 12]);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('created id 1, version 1', $out);

        $sim = DB::table('lms_simulation')->first();
        $this->assertSame([1, 'eye-lab', 'published', 1, 'Science', 'a,b', 'intro', '/simulations/covers/eye-lab.webp', 12], [(int) $sim->sub_institute_id, $sim->slug, $sim->status, (int) $sim->current_version, $sim->category, $sim->tags, $sim->difficulty, $sim->cover_url, (int) $sim->created_by]);
        $version = DB::table('lms_simulation_version')->first();
        $this->assertSame($package['spec_json'], $version->spec, 'the spec text is stored exactly as the package carries it');
        $this->assertSame($package['spec_sha256'], $version->sha256);
        $this->assertSame('imported from eye-lab.package.json', $version->note);
        $this->assertSame(0, $this->row('lms_simulation_concept'), 'a package makes no links: they start with the mapping, in review');
    }

    public function test_the_import_is_idempotent_on_the_spec_hash_and_a_new_hash_is_the_next_version_number(): void
    {
        $package = $this->readyPackage('idem', 'Idem');
        $dir = $this->packageDir([$package]);
        $this->cmd('lms:simulations-import', ['path' => $dir, '--apply' => true]);
        $afterFirst = $this->counts();

        [$code, $out] = $this->cmd('lms:simulations-import', ['path' => $dir, '--apply' => true]);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('already imported', $out);
        $this->assertSame($afterFirst, $this->counts(), 'the same hash is a no-op');

        // the source calls its version 4; K12 numbers it for itself. A changed spec is version 2, whatever the document says inside
        $changed = $this->buildPackage('idem', [$this->manifestEntry($this->glb('idem'))], ['source' => ['host' => 'live', 'simulation_id' => 9, 'version' => 99]], 'Idem retitled');
        $this->packageDir([$changed]);
        [$code, $out] = $this->cmd('lms:simulations-import', ['path' => $this->packageDir([$changed]), '--apply' => true]);
        $this->assertSame(0, $code, $out);
        $this->assertSame([1, 2], [$this->row('lms_simulation'), $this->row('lms_simulation_version')]);
        $this->assertSame(2, (int) DB::table('lms_simulation')->value('current_version'));
        $this->assertSame('Idem retitled', DB::table('lms_simulation')->value('title'));
        $this->assertSame([1, 2], DB::table('lms_simulation_version')->orderBy('version')->pluck('version')->map(fn ($v) => (int) $v)->all());

        // the OLD spec arrives again: its hash is already stored as version 1, so nothing happens (use promote to bring it back)
        [, $out] = $this->cmd('lms:simulations-import', ['path' => $dir, '--apply' => true]);
        $this->assertStringContainsString('already stored as version 1', $out);
        $this->assertSame([1, 2], [$this->row('lms_simulation'), $this->row('lms_simulation_version')]);
        $this->assertSame(2, (int) DB::table('lms_simulation')->value('current_version'), 'and the version played does not move');
    }

    public function test_a_spec_that_names_an_unregistered_asset_is_refused_and_nothing_is_written(): void
    {
        $bytes = $this->glb('never-registered');
        $unregistered = $this->buildPackage('no-asset', [$this->manifestEntry($bytes)]);
        $good = $this->readyPackage('good-one', 'Good');
        $dir = $this->packageDir([$unregistered, $good]);

        [$code, $out] = $this->cmd('lms:simulations-import', ['path' => $dir, '--apply' => true]);
        $this->assertSame(1, $code, $out);
        $this->assertStringContainsString('no-asset.package.json: REFUSED', $out);
        $this->assertStringContainsString('has not been uploaded for this tenant', $out);
        $this->assertSame(['good-one'], DB::table('lms_simulation')->pluck('slug')->all(), 'the good package is imported, the bad one is not');

        // registered but the object is not on the disk
        $registeredOnly = $this->glb('row-without-object');
        $this->registerAsset($registeredOnly, 1);
        Storage::disk('digitalocean')->delete('k12_sim_assets/' . hash('sha256', $registeredOnly) . '.glb');
        $missing = $this->packageDir([$this->buildPackage('no-object', [$this->manifestEntry($registeredOnly)])]);
        [$code, $out] = $this->cmd('lms:simulations-import', ['path' => $missing, '--apply' => true]);
        $this->assertSame(1, $code, $out);
        $this->assertStringContainsString('is not on the disk', $out);
        $this->assertNull(DB::table('lms_simulation')->where('slug', 'no-object')->first());

        // another tenant's registered file does not count either
        $others = $this->glb('tenant-eight');
        $this->registerAsset($others, 8);
        [$code, $out] = $this->cmd('lms:simulations-import', ['path' => $this->packageDir([$this->buildPackage('theirs', [$this->manifestEntry($others)])]), '--apply' => true]);
        $this->assertSame(1, $code, $out);
        $this->assertNull(DB::table('lms_simulation')->where('slug', 'theirs')->first());
    }

    public function test_a_package_whose_hash_text_or_manifest_is_wrong_is_refused(): void
    {
        $base = $this->readyPackage('base', 'Base');
        unset($base['_sha']);
        $cases = [
            'the hash does not match the text' => [['spec_sha256' => str_repeat('a', 64)], 'sha256(spec_json) is'],
            'the text was re-laid out (not canonical)' => [['spec_json' => json_encode(json_decode($base['spec_json']), JSON_PRETTY_PRINT)] , 'not in the store\'s canonical form'],
            'the text is not a spec' => [['spec_json' => '{"schema":"nope"}'], null],
            'the slug differs from the spec id' => [['slug' => 'other'], 'the specification\'s id is'],
            'the format is unknown' => [['format' => 'k12.sim-package/9'], '"format" must be'],
            'no spec text' => [['spec_json' => ''], '"spec_json" is required'],
            'the manifest omits a file the spec names' => [['assets' => []], 'manifest does not list'],
        ];
        foreach ($cases as $why => [$over, $expected]) {
            $package = $over + $base;
            if (isset($over['spec_json']) && ! isset($over['spec_sha256'])) {
                $package['spec_sha256'] = hash('sha256', $package['spec_json']);   // keep the hash honest so the case is about the text
            }
            [$code, $out] = $this->cmd('lms:simulations-import', ['path' => $this->packageDir([$package]), '--apply' => true]);
            $this->assertSame(1, $code, $why . ': ' . $out);
            $this->assertStringContainsString('REFUSED', $out, $why);
            if ($expected !== null) {
                $this->assertStringContainsString($expected, $out, $why);
            }
        }
        $this->assertSame(0, $this->row('lms_simulation'), 'not one of them was imported');
    }

    public function test_only_a_platform_tenant_can_own_an_import_and_a_missing_path_is_an_error(): void
    {
        $dir = $this->packageDir([$this->readyPackage()]);
        [$code, $out] = $this->cmd('lms:simulations-import', ['path' => $dir, '--apply' => true, '--tenant' => 7]);
        $this->assertSame(2, $code);
        $this->assertStringContainsString('must be a platform tenant', $out);
        [$code] = $this->cmd('lms:simulations-import', ['path' => $dir . '-nope']);
        $this->assertSame(2, $code);
        $this->assertSame(0, $this->row('lms_simulation'));
    }

    public function test_the_shipped_packages_pass_every_check_that_needs_no_database(): void
    {
        $importer = new SimulationPackageImporter(new SimulationStore());
        $files = glob(base_path('database/simulation-packages/*.package.json'));
        if ($files === []) {
            $this->markTestSkipped('no packages shipped yet');
        }
        foreach ($files as $file) {
            $package = $importer->load($file);   // sha256(spec_json) = spec_sha256, canonical form, slug = spec id, manifest lists every file named
            $this->assertSame(basename($file, '.package.json'), $package['slug']);
            $this->assertSame(hash('sha256', $package['spec_json']), $package['spec_sha256']);
            foreach ($package['assets'] as $asset) {
                $this->assertMatchesRegularExpression('#^sim_assets/platform/' . $asset['sha256'] . '\.(glb|png|jpg|webp|hdr)$#', $asset['source_key']);
                $this->assertNotSame('', trim($asset['licence'] . $asset['credit']), 'no asset without its licence and credit');
            }
        }
    }

    // ── lms:simulations-mapping-import ───────────────────────────────────────

    private function mappingFile(array $links, string $slug = 'eye-lab'): string
    {
        $file = tempnam(sys_get_temp_dir(), 'simmap');
        file_put_contents($file, json_encode([['slug' => $slug, 'links' => $links]], JSON_UNESCAPED_UNICODE));
        $this->simTmp[] = $file;

        return $file;
    }

    private function link(array $over = []): array
    {
        return $over + ['standard' => '9', 'subject' => 'Science', 'chapter' => 'Ch A', 'topic' => 'Topic One', 'concept' => 'Concept Five', 'role' => 'primary', 'citation' => 'Section 1.2', 'reason' => 'It shows it.'];
    }

    public function test_the_mapping_import_is_a_dry_run_then_creates_the_links_in_review_with_the_hierarchy_copied(): void
    {
        $this->makeSimulation('eye-lab');
        $file = $this->mappingFile([
            $this->link(),
            $this->link(['standard' => '10', 'chapter' => 'The Human Eye and the Colourful World', 'topic' => null, 'concept' => 'Human Eye Structure', 'role' => 'supporting']),
        ]);

        [$code, $out] = $this->cmd('lms:simulations-mapping-import', ['file' => $file]);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('DRY RUN', $out);
        $this->assertSame(0, $this->row('lms_simulation_concept'));

        [$code, $out] = $this->cmd('lms:simulations-mapping-import', ['file' => $file, '--apply' => true, '--user' => 31]);
        $this->assertSame(0, $code, $out);
        $links = DB::table('lms_simulation_concept')->orderBy('concept_id')->get()->keyBy('concept_id');
        $this->assertCount(2, $links);
        $this->assertSame([42, 3975, 100, 11, 'primary', 'review', 'Section 1.2', 'It shows it.', 31, null], [(int) $links[5]->standard_id, (int) $links[5]->subject_id, (int) $links[5]->chapter_id, (int) $links[5]->topic_id, $links[5]->role, $links[5]->review_status, $links[5]->citation, $links[5]->reason, (int) $links[5]->created_by, $links[5]->reviewed_by]);
        $this->assertSame([43, 200, null, 'supporting', 'review'], [(int) $links[7]->standard_id, (int) $links[7]->chapter_id, $links[7]->topic_id, $links[7]->role, $links[7]->review_status], 'a null topic gives a link with topic_id NULL');

        // again: nothing changes, and a link a teacher has since published is not pulled back to review
        DB::table('lms_simulation_concept')->where('concept_id', 5)->update(['review_status' => 'published', 'reviewed_by' => 4]);
        [$code, $out] = $this->cmd('lms:simulations-mapping-import', ['file' => $file, '--apply' => true]);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('Created 0 link(s) in review; 2 already existed', $out);
        $this->assertSame('published', DB::table('lms_simulation_concept')->where('concept_id', 5)->value('review_status'));
    }

    public function test_the_mapping_import_refuses_what_it_cannot_resolve_to_exactly_one_concept_and_writes_nothing(): void
    {
        $this->makeSimulation('eye-lab');
        // an ambiguous tuple: two concepts of the platform tenant carry the same names
        DB::table('lms_concept')->insert([
            ['id' => 50, 'sub_institute_id' => 1, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_id' => 100, 'topic_id' => 11, 'name' => 'Twin', 'review_status' => 'legacy'],
            ['id' => 51, 'sub_institute_id' => 1, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_id' => 100, 'topic_id' => 11, 'name' => 'Twin', 'review_status' => 'legacy'],
        ]);
        $file = $this->mappingFile([
            $this->link(),                                                          // fine
            $this->link(['concept' => 'No Such Concept']),                         // zero matches
            $this->link(['concept' => 'Twin']),                                    // two matches: none is picked
            $this->link(['chapter' => 'The Human Eye and the Colourful World', 'standard' => '10', 'topic' => 'Topic One', 'concept' => 'Human Eye Structure']),   // has no topic, the tuple names one
            $this->link(['chapter' => 'The Human Eye and the Colourful World', 'standard' => '10', 'topic' => null, 'concept' => 'Concept Five']),   // wrong chapter
            $this->link(['concept' => 'Concept Five', 'standard' => '10']),        // wrong standard
            $this->link(['chapter' => 'B only chapter', 'topic' => 'B topic', 'concept' => 'B concept']),   // another tenant's chapter
            $this->link(['role' => 'boss']),
            $this->link(['topic' => '']),
        ]);

        [$code, $out] = $this->cmd('lms:simulations-mapping-import', ['file' => $file, '--apply' => true]);
        $this->assertSame(1, $code, $out);
        $this->assertStringContainsString('REFUSED - nothing was written', $out);
        $this->assertStringContainsString('[9 > Science > Ch A > Topic One > No Such Concept]: no concept of the platform tenant has these names', $out);
        $this->assertStringContainsString('[9 > Science > Ch A > Topic One > Twin]: more than one concept (#50, #51)', $out, 'the tuple is listed and the first match is not picked');
        $this->assertStringContainsString('[10 > Science > The Human Eye and the Colourful World > Topic One > Human Eye Structure]', $out, 'a topic named for a concept that has none');
        $this->assertStringContainsString('(no topic) > Concept Five]', $out);
        $this->assertStringContainsString('"role" must be one of', $out);
        $this->assertStringContainsString('"topic" must be a name, or null', $out);
        $this->assertSame(0, $this->row('lms_simulation_concept'), 'not even the good link was created: all or nothing');

        // a simulation that is not imported
        [$code, $out] = $this->cmd('lms:simulations-mapping-import', ['file' => $this->mappingFile([$this->link()], 'not-imported'), '--apply' => true]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('the simulation is not imported', $out);
        // the same concept twice for one simulation
        [$code, $out] = $this->cmd('lms:simulations-mapping-import', ['file' => $this->mappingFile([$this->link(), $this->link()]), '--apply' => true]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('listed twice', $out);
        $this->assertSame(0, $this->row('lms_simulation_concept'));
    }

    public function test_names_are_compared_exactly_as_the_database_does_curly_apostrophes_and_brackets_included(): void
    {
        $this->makeSimulation('eye-lab');
        $eye = ['standard' => '10', 'chapter' => 'The Human Eye and the Colourful World', 'topic' => null];
        // concept 8 is "Myopia ’Nearsightedness’ (short)" with U+2019 quotes and a bracket
        [$code, $out] = $this->cmd('lms:simulations-mapping-import', ['file' => $this->mappingFile([$this->link($eye + ['concept' => "Myopia \u{2019}Nearsightedness\u{2019} (short)"])]), '--apply' => true]);
        $this->assertSame(0, $code, $out);
        $this->assertSame([8], DB::table('lms_simulation_concept')->pluck('concept_id')->map(fn ($i) => (int) $i)->all());

        DB::table('lms_simulation_concept')->delete();
        [$code, $out] = $this->cmd('lms:simulations-mapping-import', ['file' => $this->mappingFile([$this->link($eye + ['concept' => "Myopia 'Nearsightedness' (short)"])]), '--apply' => true]);
        $this->assertSame(1, $code, 'a straight apostrophe is not the curly one in the database');
        $this->assertSame(0, $this->row('lms_simulation_concept'));
    }

    public function test_the_shipped_mapping_file_has_the_shape_the_importer_reads(): void
    {
        $file = base_path('database/simulation-packages/mapping.json');
        if (! is_file($file)) {
            $this->markTestSkipped('no mapping shipped yet');
        }
        $mapping = (new SimulationMapping(new \App\Services\lms\Simulation\SimulationLinks()))->load($file);
        $this->assertNotEmpty($mapping);
        foreach ($mapping as $entry) {
            $this->assertIsString($entry['slug']);
            foreach ($entry['links'] as $link) {
                foreach (['standard', 'subject', 'chapter', 'concept'] as $key) {
                    $this->assertIsString($link[$key], "{$entry['slug']} {$key}");
                }
                $this->assertTrue(array_key_exists('topic', $link) && ($link['topic'] === null || is_string($link['topic'])), 'topic is a name or null');
                $this->assertContains($link['role'] ?? 'primary', ['primary', 'supporting']);
                $this->assertLessThanOrEqual(255, mb_strlen($link['citation'] ?? ''));
                $this->assertLessThanOrEqual(500, mb_strlen($link['reason'] ?? ''));
            }
        }
    }

    // ── lms:simulations-mapping-sheet ────────────────────────────────────────

    public function test_the_mapping_sheet_has_a_row_per_link_with_the_concept_rating_and_no_topic_text(): void
    {
        $a = $this->makeSimulation('eye-lab');
        $this->linkTo($a, [5, 7], 'review');
        DB::table('lms_simulation_concept')->where('concept_id', 5)->update(['citation' => 'Section "1.2", p. 4', 'reason' => 'It shows it, with a comma.']);

        [$code, $out] = $this->cmd('lms:simulations-mapping-sheet');
        $this->assertSame(0, $code);
        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($out))));
        $this->assertSame(['simulation', 'concept_id', 'concept', 'chapter', 'topic', 'standard', 'subject', 'concept_review_status', 'citation', 'reason', 'link_review_status', 'sign_off'], $rows[0]);
        $this->assertCount(3, $rows);
        $byConcept = collect(array_slice($rows, 1))->keyBy(1);
        $this->assertSame(['Sim eye-lab', '5', 'Concept Five', 'Ch A', 'Topic One', '9', 'Science', 'verified', 'Section "1.2", p. 4', 'It shows it, with a comma.', 'review', ''], $byConcept['5']);
        $this->assertSame('(no topic)', $byConcept['7'][4], 'a concept with no topic says so');
        $this->assertSame(['legacy', 'review', ''], [$byConcept['7'][7], $byConcept['7'][10], $byConcept['7'][11]], 'the concept\'s own rating, the link state and a blank sign-off');

        $out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sheet' . uniqid() . '.csv';
        $this->simTmp[] = $out;
        [$code] = $this->cmd('lms:simulations-mapping-sheet', ['--out' => $out]);
        $this->assertSame(0, $code);
        $this->assertStringStartsWith("\xEF\xBB\xBF" . 'simulation,concept_id', file_get_contents($out), 'a byte-order mark, so a spreadsheet reads the accents');
    }

    // ── lms:simulations-reconcile ────────────────────────────────────────────

    private function reconcile(array $args = []): array
    {
        return $this->cmd('lms:simulations-reconcile', $args);
    }

    /** A healthy world: one simulation naming a registered, stored file, linked to two concepts. */
    private function healthy(): object
    {
        $sha = $this->registerAsset($this->glb('r'), 1);
        $sim = $this->makeSimulation('healthy', 'published', 1, [$this->model($sha)]);
        $this->linkTo($sim, [5, 7], 'published');

        return $sim;
    }

    public function test_reconcile_passes_on_a_healthy_world_and_writes_nothing(): void
    {
        $this->healthy();
        $before = $this->counts();
        $files = Storage::disk('digitalocean')->allFiles();

        [$code, $out] = $this->reconcile(['--check-bucket' => true]);
        $this->assertSame(0, $code, $out);
        foreach (['simulations per tenant', 'no orphan links', 'links match their concept', 'link topics belong', 'every file a current spec names is registered', 'registered objects are on the disk'] as $check) {
            $this->assertMatchesRegularExpression('/^PASS  ' . preg_quote($check, '/') . '/m', $out, $check);
        }
        $this->assertStringContainsString('tenant 1: 1', $out);
        $this->assertStringContainsString('ALL CHECKS PASSED', $out);
        $this->assertSame($before, $this->counts());
        $this->assertSame($files, Storage::disk('digitalocean')->allFiles());
    }

    public function test_reconcile_fails_on_a_seeded_orphan_link(): void
    {
        $sim = $this->healthy();
        // a link to a concept that is not there, and a link to a simulation that is not there
        DB::table('lms_simulation_concept')->insert(['simulation_id' => $sim->id, 'concept_id' => 4040, 'standard_id' => 1, 'subject_id' => 1, 'chapter_id' => 1, 'topic_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('lms_simulation_concept')->insert(['simulation_id' => 777, 'concept_id' => 5, 'standard_id' => 42, 'subject_id' => 3975, 'chapter_id' => 100, 'topic_id' => 11, 'created_at' => now(), 'updated_at' => now()]);

        [$code, $out] = $this->reconcile();
        $this->assertSame(1, $code, $out);
        $this->assertMatchesRegularExpression('/^FAIL  no orphan links/m', $out);
        $this->assertStringContainsString('point at a concept that does not exist', $out);
        $this->assertStringContainsString('point at a simulation that does not exist', $out);
        $this->assertMatchesRegularExpression('/^PASS  simulations per tenant/m', $out, 'the other checks still run and report');
    }

    public function test_reconcile_fails_when_a_links_chapter_or_topic_no_longer_matches_its_concept(): void
    {
        $sim = $this->healthy();
        $link = DB::table('lms_simulation_concept')->where('concept_id', 5)->first();

        DB::table('lms_simulation_concept')->where('id', $link->id)->update(['chapter_id' => 200]);
        [$code, $out] = $this->reconcile();
        $this->assertSame(1, $code, 'chapter mismatch');
        $this->assertMatchesRegularExpression('/^FAIL  links match their concept/m', $out);
        $this->assertStringContainsString("link {$link->id} (simulation {$sim->id}, concept 5): chapter 200 vs 100", $out);

        DB::table('lms_simulation_concept')->where('id', $link->id)->update(['chapter_id' => 100, 'topic_id' => 12]);
        [$code, $out] = $this->reconcile();
        $this->assertSame(1, $code, 'topic mismatch');
        $this->assertStringContainsString('topic 12 vs 11', $out);

        DB::table('lms_simulation_concept')->where('id', $link->id)->update(['topic_id' => null]);
        [$code, $out] = $this->reconcile();
        $this->assertSame(1, $code, 'a topic dropped from the link');

        DB::table('lms_simulation_concept')->where('id', $link->id)->update(['topic_id' => 11, 'standard_id' => 43]);
        [$code, $out] = $this->reconcile();
        $this->assertSame(1, $code, 'standard mismatch');

        // the concept moved after the link was made: the copies are stale
        DB::table('lms_simulation_concept')->where('id', $link->id)->update(['standard_id' => 42]);
        DB::table('lms_concept')->where('id', 5)->update(['topic_id' => 12]);
        [$code, $out] = $this->reconcile();
        $this->assertSame(1, $code, 'the concept changed under the link');

        // a link whose topic is not in its chapter, with chapter and concept agreeing
        DB::table('lms_concept')->where('id', 5)->update(['topic_id' => 31]);
        DB::table('lms_simulation_concept')->where('id', $link->id)->update(['topic_id' => 31]);
        [$code, $out] = $this->reconcile();
        $this->assertSame(1, $code);
        $this->assertMatchesRegularExpression('/^FAIL  link topics belong to the link chapter/m', $out);

        DB::table('lms_concept')->where('id', 5)->update(['topic_id' => 11]);
        DB::table('lms_simulation_concept')->where('id', $link->id)->update(['topic_id' => 11]);
        [$code, $out] = $this->reconcile();
        $this->assertSame(0, $code, 'and it passes again once the copies agree: ' . $out);
    }

    public function test_reconcile_fails_for_an_unregistered_asset_a_bad_object_and_a_simulation_of_the_wrong_tenant(): void
    {
        $sim = $this->healthy();
        $sha = hash('sha256', $this->glb('r'));

        // the object on the disk has other bytes than the registered sha256
        Storage::disk('digitalocean')->put("k12_sim_assets/{$sha}.glb", 'tampered');
        [$code, $out] = $this->reconcile();
        $this->assertSame(0, $code, 'without --check-bucket the disk is not read: ' . $out);
        [$code, $out] = $this->reconcile(['--check-bucket' => true]);
        $this->assertSame(1, $code, $out);
        $this->assertMatchesRegularExpression('/^FAIL  registered objects are on the disk and hash to their sha256/m', $out);
        $this->assertStringContainsString("{$sha}: k12_sim_assets/{$sha}.glb hashes to", $out);

        Storage::disk('digitalocean')->delete("k12_sim_assets/{$sha}.glb");
        [$code, $out] = $this->reconcile(['--check-bucket' => true]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('is not on the disk', $out);

        // a current spec that names a file with no registry row (written straight, as the store would refuse it)
        DB::table('lms_simulation_asset')->delete();
        [$code, $out] = $this->reconcile();
        $this->assertSame(1, $code);
        $this->assertMatchesRegularExpression('/^FAIL  every file a current spec names is registered/m', $out);
        $this->assertStringContainsString("{$sha} (named by simulation {$sim->id} \"healthy\")", $out);

        // a simulation owned by a tenant that may not own one
        DB::table('lms_simulation')->where('id', $sim->id)->update(['sub_institute_id' => 7]);
        [$code, $out] = $this->reconcile();
        $this->assertSame(1, $code);
        $this->assertStringContainsString('tenant 7 owns 1 simulation(s) but is not a platform tenant', $out);
    }

    // ── lms:simulations-promote ──────────────────────────────────────────────

    public function test_an_earlier_version_is_brought_back_with_promote_dry_run_first(): void
    {
        $dir = $this->packageDir([$this->readyPackage('promo', 'Promo')]);
        $this->cmd('lms:simulations-import', ['path' => $dir, '--apply' => true]);
        $changed = $this->buildPackage('promo', [$this->manifestEntry($this->glb('promo'))], [], 'Promo two');
        $this->cmd('lms:simulations-import', ['path' => $this->packageDir([$changed]), '--apply' => true]);
        $this->assertSame([2, 'Promo two'], [(int) DB::table('lms_simulation')->value('current_version'), DB::table('lms_simulation')->value('title')]);

        [$code, $out] = $this->cmd('lms:simulations-promote', ['id' => 1, 'version' => 1]);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('DRY RUN', $out);
        $this->assertSame(2, (int) DB::table('lms_simulation')->value('current_version'), 'a dry run changes nothing');

        [$code, $out] = $this->cmd('lms:simulations-promote', ['id' => 1, 'version' => 1, '--apply' => true]);
        $this->assertSame(0, $code, $out);
        $this->assertSame([1, 'Promo'], [(int) DB::table('lms_simulation')->value('current_version'), DB::table('lms_simulation')->value('title')]);
        [$code] = $this->cmd('lms:simulations-promote', ['id' => 1, 'version' => 9, '--apply' => true]);
        $this->assertSame(1, $code, 'no such version');
        [$code] = $this->cmd('lms:simulations-promote', ['id' => 77, 'version' => 1, '--apply' => true]);
        $this->assertSame(1, $code, 'no such simulation');

        // the importer says how to get back to it
        [, $out] = $this->cmd('lms:simulations-import', ['path' => $this->packageDir([$changed])]);
        $this->assertStringContainsString('already stored as version 2', $out);
        [, $out] = $this->cmd('lms:simulations-import', ['path' => $dir]);
        $this->assertStringContainsString('already imported: version 1 is this spec', $out);
    }
}
