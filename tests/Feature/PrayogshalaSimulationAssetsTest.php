<?php

namespace Tests\Feature;

use App\Services\lms\Simulation\SimulationStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SimulationTestHelpers;
use Tests\TestCase;

/**
 * Prayogshala Simulation Learning assets: the author's upload (ported from hp_erp's LmsSimulationUploadTest) and `lms:simulations-assets-copy`.
 *
 * RUNS ON IN-MEMORY SQLITE with the `digitalocean` disk FAKED (see SimulationTestHelpers): nothing here can reach the real database or the bucket.
 */
class PrayogshalaSimulationAssetsTest extends TestCase
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

    private function upload(string $bytes, string $name = 'eye.glb', array $fields = [], int $tenant = 1, string $profile = 'Teacher'): array
    {
        $fields = array_filter($fields + ['licence' => 'CC BY 4.0', 'credit' => 'Eye model by A. Author', 'source' => 'https://example.org/eye', 'attested' => '1'], fn ($v) => $v !== null);

        return $this->act('uploadAsset', $tenant, [], $fields, [], $profile, false, 55, ['file' => UploadedFile::fake()->createWithContent($name, $bytes)]);
    }

    private function nothingStored(): void
    {
        $this->assertSame(0, $this->row('lms_simulation_asset'));
        $this->assertSame([], Storage::disk('digitalocean')->allFiles());
    }

    private function hdr(int $w = 1024, int $h = 512, string $label = 'sky'): string
    {
        return "#?RADIANCE\n# {$label}\nFORMAT=32-bit_rle_rgbe\n\n-Y {$h} +X {$w}\n" . str_repeat("\x80\x80\x80\x81", 256);
    }

    // ── the author's upload ──────────────────────────────────────────────────

    public function test_an_upload_is_stored_privately_under_the_k12_prefix_and_registered_for_the_session_tenant(): void
    {
        $bytes = $this->glb();
        $sha = hash('sha256', $bytes);
        [$status, $body] = $this->upload($bytes, 'Human Eye v2.glb', ['sub_institute_id' => 7, 'owner' => 'platform']);

        $this->assertSame(201, $status, json_encode($body));
        $this->assertSame(['sha256' => $sha, 'kind' => 'model', 'mime' => 'model/gltf-binary', 'bytes' => strlen($bytes), 'created' => true], array_intersect_key($body['data'], array_flip(['sha256', 'kind', 'mime', 'bytes', 'created'])));
        $this->assertSame(['name' => 'Human Eye v2', 'licence' => 'CC BY 4.0', 'credit' => 'Eye model by A. Author', 'source_url' => 'https://example.org/eye'], array_intersect_key($body['data'], array_flip(['name', 'licence', 'credit', 'source_url'])));
        $this->assertStringNotContainsString('k12_sim_assets', json_encode($body), 'the storage path is never sent');

        Storage::disk('digitalocean')->assertExists("k12_sim_assets/{$sha}.glb");
        $this->assertSame([], array_filter(Storage::disk('digitalocean')->allFiles(), fn ($f) => str_starts_with($f, 'sim_assets/')), 'hp_erp\'s prefix is never written');
        $this->assertSame($bytes, Storage::disk('digitalocean')->get("k12_sim_assets/{$sha}.glb"));
        $row = DB::table('lms_simulation_asset')->first();
        $this->assertSame([1, $sha, 'model', 'model/gltf-binary', "k12_sim_assets/{$sha}.glb", 55], [(int) $row->sub_institute_id, $row->sha256, $row->kind, $row->mime, $row->storage_path, (int) $row->created_by], 'the owner is the session tenant, not the one the body named');

        [, $named] = $this->upload($this->glb('named'), 'x.glb', ['name' => 'The eye']);
        $this->assertSame('The eye', $named['data']['name']);
    }

    public function test_only_platform_staff_may_upload_and_a_refused_upload_stores_nothing(): void
    {
        foreach ([[7, 'Teacher'], [8, 'Admin'], [5, 'Super Admin']] as [$tenant, $profile]) {
            [$status] = $this->upload($this->glb(), 'eye.glb', [], $tenant, $profile);
            $this->assertSame(403, $status, "tenant {$tenant}");
        }
        [$status] = $this->act('uploadAsset', 1, [], ['licence' => 'CC0', 'credit' => 'x', 'attested' => '1'], [], 'Student', true, 55, ['file' => UploadedFile::fake()->createWithContent('eye.glb', $this->glb())]);
        $this->assertSame(403, $status, 'a student');
        $this->nothingStored();
    }

    public function test_what_a_file_is_comes_from_its_bytes_not_its_name(): void
    {
        [$status, $body] = $this->upload($this->png(8, 8), 'eye.glb');
        $this->assertSame(422, $status);
        $this->assertStringContainsString('is named .glb but it is a PNG image', $body['message']);
        [$status, $body] = $this->upload($this->glb('x'), 'eye.png');
        $this->assertSame(422, $status);
        $this->assertStringContainsString('is named .png but it is a binary glTF model', $body['message']);
        foreach (['<svg xmlns="http://www.w3.org/2000/svg"></svg>' => 'a.svg', "GIF89a\x01\x00\x01\x00\x00\x00\x00;" => 'a.gif', '<html><script>alert(1)</script></html>' => 'a.glb', 'plain text of some length' => 'a.txt'] as $bytes => $name) {
            [$status, $body] = $this->upload((string) $bytes, $name);
            $this->assertSame(422, $status, $name);
            $this->assertStringContainsString('not a file this takes', $body['message'], $name);
        }
        $this->nothingStored();

        [$status, $png] = $this->upload($this->png(8, 8, 'p'), 'p.png');
        $this->assertSame([201, 'image', 'image/png'], [$status, $png['data']['kind'], $png['data']['mime']]);
    }

    public function test_a_radiance_hdr_is_a_kind_of_file_of_its_own(): void
    {
        [$status, $body] = $this->upload($this->hdr(), 'sky.hdr');
        $this->assertSame([201, 'hdr', 'image/vnd.radiance'], [$status, $body['data']['kind'], $body['data']['mime']]);
        $this->assertStringEndsWith('.hdr', DB::table('lms_simulation_asset')->value('storage_path'));
        [, $body] = $this->upload($this->png(8, 8, 'z'), 'sky.hdr');
        $this->assertStringContainsString('is named .hdr but it is a PNG image', $body['message']);
        [, $body] = $this->upload("#?RADIANCE\nFORMAT=32-bit_rle_xyze\n\n-Y 512 +X 1024\n" . str_repeat("\0", 64), 'xyz.hdr');
        $this->assertStringContainsString('cannot be read as an HDR image', $body['message']);
        [, $body] = $this->upload($this->hdr(8192, 4096, 'big'), 'big.hdr');
        $this->assertStringContainsString('the limit for an HDR image is 4096 x 4096', $body['message']);
        [$status, $body] = $this->upload($this->hdr(1024, 512, 'huge') . str_repeat("\0", SimulationStore::MAX_HDR_BYTES), 'huge.hdr');
        $this->assertSame(413, $status);
        $this->assertStringContainsString('the limit for an HDR image is 8 MB', $body['message']);
        $this->assertSame(1, $this->row('lms_simulation_asset'));
    }

    public function test_the_same_file_twice_is_one_asset_and_a_removed_one_is_registered_again(): void
    {
        $bytes = $this->glb('twice');
        $sha = hash('sha256', $bytes);
        [$status, $first] = $this->upload($bytes, 'eye.glb', ['credit' => 'First credit']);
        $this->assertSame([201, true], [$status, $first['data']['created']]);

        [$status, $second] = $this->upload($bytes, 'eye.glb', ['credit' => 'A different credit']);
        $this->assertSame([200, false, 'First credit'], [$status, $second['data']['created'], $second['data']['credit']], 'its details stay as they were');
        $this->assertSame(1, $this->row('lms_simulation_asset'));

        Storage::disk('digitalocean')->delete("k12_sim_assets/{$sha}.glb");
        $this->upload($bytes);
        Storage::disk('digitalocean')->assertExists("k12_sim_assets/{$sha}.glb");   // put back when it had gone missing

        DB::table('lms_simulation_asset')->update(['deleted_at' => now()]);
        [$status, $again] = $this->upload($bytes, 'eye.glb', ['credit' => 'Credit now']);
        $this->assertSame([201, 'Credit now', 1], [$status, $again['data']['credit'], $this->row('lms_simulation_asset')], 'registered again, details from now, no twin row');
        $this->assertNull(DB::table('lms_simulation_asset')->value('deleted_at'));
    }

    public function test_licence_credit_and_the_attestation_are_mandatory_and_every_problem_is_listed(): void
    {
        [$status, $body] = $this->upload($this->glb(), 'eye.glb', ['licence' => '', 'credit' => '', 'source' => 'http://insecure.example', 'name' => str_repeat('n', 121)]);
        $this->assertSame(422, $status);
        $problems = implode(' | ', $body['problems']);
        foreach (['"licence" is required', '"credit" is required', '"source" must be an https address', '"name" is 120 characters at most'] as $expected) {
            $this->assertStringContainsString($expected, $problems);
        }
        $this->assertSame(['file' => $body['problems']], $body['errors']);

        [$status, $body] = $this->upload($this->glb(), 'eye.glb', ['attested' => null]);
        $this->assertSame(422, $status);
        $this->assertStringContainsString('send "attested" as 1', $body['message']);
        [$status] = $this->upload($this->glb(), 'eye.glb', ['attested' => '0']);
        $this->assertSame(422, $status);
        [$status, $body] = $this->upload($this->glb(), 'eye.glb', ['licence' => ['CC0']]);
        $this->assertSame(422, $status);
        $this->assertStringContainsString('"licence" must be text', $body['message']);
        $this->nothingStored();
    }

    public function test_a_missing_file_is_a_422_and_a_file_over_its_limit_a_413(): void
    {
        [$status, $body] = $this->act('uploadAsset', 1, [], ['licence' => 'CC0', 'credit' => 'x', 'attested' => '1']);
        $this->assertSame(422, $status);
        $this->assertSame('"file" is required: send the 3D model or image as the multipart field "file".', $body['problems'][0]);

        $model = SimulationStore::MAX_ASSET_BYTES + 1;
        [$status, $body] = $this->upload('glTF' . pack('V', 2) . pack('V', $model) . str_repeat("\0", $model - 12));
        $this->assertSame(413, $status);
        $this->assertStringContainsString('the limit for a model is 25 MB', $body['message']);
        [$status, $body] = $this->upload($this->png(8, 8) . str_repeat("\0", SimulationStore::MAX_IMAGE_BYTES), 'p.png', ['licence' => null]);
        $this->assertSame(413, $status, 'a size problem is a 413 even with another problem beside it');
        $this->assertStringContainsString('the limit for an image is 2 MB', $body['message']);

        $php = new UploadedFile($this->tmpFile('x'), 'big.glb', 'model/gltf-binary', UPLOAD_ERR_INI_SIZE, true);
        [$status] = $this->act('uploadAsset', 1, [], ['licence' => 'CC0', 'credit' => 'x', 'attested' => '1'], [], 'Teacher', false, 55, ['file' => $php]);
        $this->assertSame(413, $status, 'PHP itself refusing the file');
        $this->nothingStored();
    }

    public function test_a_model_that_breaks_a_rule_is_refused_naming_the_rule_and_nothing_is_stored(): void
    {
        $external = $this->glb('ext', ['buffers' => [['uri' => 'http://evil.example/x.bin', 'byteLength' => 4]]]);
        [$status, $body] = $this->upload($external, 'ext.glb');
        $this->assertSame(422, $status);
        $this->assertNotSame('', $body['message']);
        $this->nothingStored();
    }

    public function test_a_tenant_may_keep_200_files_and_only_its_own_live_ones_count(): void
    {
        $fill = function (?int $tenant, int $files, bool $removed = false) {
            $rows = [];
            for ($i = 0; $i < $files; $i++) {
                $rows[] = ['sub_institute_id' => $tenant, 'sha256' => hash('sha256', "filler {$tenant} {$i} {$removed}"), 'name' => 'filler', 'kind' => 'model', 'mime' => 'model/gltf-binary', 'bytes' => 1,
                    'storage_path' => 'k12_sim_assets/filler' . $i, 'deleted_at' => $removed ? now() : null];
            }
            DB::table('lms_simulation_asset')->insert($rows);
        };
        $fill(8, 300);          // another tenant's files do not count
        $fill(1, 100, true);    // removed ones do not count
        $fill(1, 199);
        [$status] = $this->upload($this->glb('the-200th'));
        $this->assertSame(201, $status, 'the 200th file fits');
        [$status, $body] = $this->upload($this->glb('the-201st'));
        $this->assertSame(422, $status);
        $this->assertStringContainsString('already keeps 200 uploaded files', $body['message']);
        [$status] = $this->upload($this->glb('the-200th'));
        $this->assertSame(200, $status, 'a file it already has costs nothing');
    }

    public function test_a_disk_that_fails_is_a_503_that_says_nothing_about_the_disk_and_leaves_no_row(): void
    {
        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldReceive('exists')->andReturn(false);
        $disk->shouldReceive('put')->andThrow(new \RuntimeException('Bucket secret refused the key AKIA...'));
        Storage::set('digitalocean', $disk);

        [$status, $body] = $this->upload($this->glb());
        $this->assertSame(503, $status);
        $this->assertStringNotContainsString('Bucket', json_encode($body));
        $this->assertStringNotContainsString('AKIA', json_encode($body));
        $this->assertStringContainsString('could not be saved just now', $body['message']);
        $this->assertSame(0, $this->row('lms_simulation_asset'), 'a row that points at nothing is never written');
    }

    public function test_the_limits_are_data_and_show_what_the_tenant_uses(): void
    {
        $this->upload($this->glb('a'));
        [$status, $body] = $this->act('limits', 1);
        $data = $body['data'];
        $this->assertSame(200, $status);
        $this->assertSame(['g2g.sim/1', 'g2g.sim/2'], $data['schemas']);
        $this->assertSame(['model', 'image', 'hdr'], $data['asset']['kinds']);
        $this->assertSame(1000000, $data['spec']['bytes']);
        $this->assertSame(['files' => 200, 'bytes' => 524288000, 'uploadsPerMinute' => 30], $data['quota']);
        $this->assertSame(1, $data['use']['files']);
        $this->assertArrayHasKey('uploadMaxBytes', $data['server']);
    }

    // ── lms:simulations-assets-copy ──────────────────────────────────────────

    /** A package dir whose manifest lists these files, and the files sitting at hp_erp's keys on the fake disk. */
    private function sourceWorld(array $files): array
    {
        $entries = [];
        foreach ($files as $bytes) {
            $entry = $this->manifestEntry($bytes);
            $entries[] = $entry;
            Storage::disk('digitalocean')->put($entry['source_key'], $bytes);
        }

        return [$this->packageDir([$this->buildPackage('copy-sim', $entries)]), $entries];
    }

    private function copy(string $dir, array $options = []): array
    {
        $code = Artisan::call('lms:simulations-assets-copy', ['--packages' => $dir] + $options);

        return [$code, Artisan::output()];
    }

    public function test_a_dry_run_copies_and_registers_nothing(): void
    {
        [$dir] = $this->sourceWorld([$this->glb('a'), $this->glb('b')]);
        $before = Storage::disk('digitalocean')->allFiles();

        [$code, $out] = $this->copy($dir);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('DRY RUN', $out);
        $this->assertStringContainsString('would copy', $out);
        $this->assertStringContainsString('would register', $out);
        $this->assertSame($before, Storage::disk('digitalocean')->allFiles());
        $this->assertSame(0, $this->row('lms_simulation_asset'));
    }

    public function test_the_shipped_manifests_satisfy_the_copy_commands_rules(): void
    {
        $dir = base_path('database/simulation-packages');
        if (glob($dir . '/*.package.json') === []) {
            $this->markTestSkipped('no packages shipped yet');
        }
        // the fake disk holds none of hp_erp's objects, so every asset FAILS on its missing source; what must NOT happen is a REFUSED, which is the
        // manifest itself being wrong (a kind, a size, a licence or credit missing, a key that is not <sha256>.<ext>)
        [$code, $out] = $this->copy($dir);
        $this->assertSame(1, $code);
        $this->assertStringNotContainsString('REFUSED', $out);
        $this->assertStringContainsString('does not exist', $out);
        $this->assertSame(0, $this->row('lms_simulation_asset'));
        $this->assertSame([], Storage::disk('digitalocean')->allFiles());
    }

    public function test_apply_copies_to_the_k12_prefix_verifies_the_hash_registers_and_never_touches_the_source(): void
    {
        $a = $this->glb('a');
        $b = $this->png(8, 8, 'pic');
        [$dir, $entries] = $this->sourceWorld([$a, $b]);

        [$code, $out] = $this->copy($dir, ['--apply' => true, '--user' => 9]);
        $this->assertSame(0, $code, $out);
        foreach ([$a, $b] as $bytes) {
            $sha = hash('sha256', $bytes);
            $ext = $bytes === $a ? 'glb' : 'png';
            $this->assertSame($bytes, Storage::disk('digitalocean')->get("k12_sim_assets/{$sha}.{$ext}"), 'copied byte for byte');
            $this->assertSame($bytes, Storage::disk('digitalocean')->get("sim_assets/platform/{$sha}.{$ext}"), 'the source is untouched');
        }
        $rows = DB::table('lms_simulation_asset')->get()->keyBy('sha256');
        $this->assertCount(2, $rows);
        $row = $rows[hash('sha256', $a)];
        $this->assertSame([1, 'model', 'CC BY 4.0', 'Model by A. Author', 'k12_sim_assets/' . hash('sha256', $a) . '.glb', 9, strlen($a)], [(int) $row->sub_institute_id, $row->kind, $row->licence, $row->credit, $row->storage_path, (int) $row->created_by, (int) $row->bytes]);

        // run again: nothing new, every object verified in place
        $files = Storage::disk('digitalocean')->allFiles();
        [$code, $out] = $this->copy($dir, ['--apply' => true]);
        $this->assertSame(0, $code, $out);
        $this->assertStringContainsString('verified', $out);
        $this->assertSame($files, Storage::disk('digitalocean')->allFiles());
        $this->assertSame(2, $this->row('lms_simulation_asset'));
    }

    public function test_a_copy_whose_bytes_do_not_match_the_manifest_sha256_fails_and_leaves_no_copy_and_no_row(): void
    {
        $good = $this->glb('good');
        [$dir, $entries] = $this->sourceWorld([$good]);
        // someone swapped the object at the source: same size, other bytes
        $swapped = $this->glb('evil');
        $swapped = substr($swapped . str_repeat("\0", 400), 0, strlen($good));
        $this->assertNotSame($good, $swapped);
        Storage::disk('digitalocean')->put($entries[0]['source_key'], $swapped);
        $sourceBefore = Storage::disk('digitalocean')->get($entries[0]['source_key']);

        [$code, $out] = $this->copy($dir, ['--apply' => true]);
        $this->assertSame(1, $code, $out);
        $this->assertStringContainsString('hashed to', $out);
        Storage::disk('digitalocean')->assertMissing('k12_sim_assets/' . $entries[0]['sha256'] . '.glb');
        $this->assertSame(0, $this->row('lms_simulation_asset'));
        $this->assertSame($sourceBefore, Storage::disk('digitalocean')->get($entries[0]['source_key']), 'the source is never deleted or changed');
    }

    public function test_the_copy_refuses_a_missing_source_a_wrong_size_a_bad_target_and_a_manifest_without_credit(): void
    {
        $a = $this->glb('a');
        [$dir, $entries] = $this->sourceWorld([$a]);

        // the source object is gone
        Storage::disk('digitalocean')->delete($entries[0]['source_key']);
        [$code, $out] = $this->copy($dir, ['--apply' => true]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('does not exist', $out);

        // an object already at the target with other bytes is left alone, never overwritten
        Storage::disk('digitalocean')->put($entries[0]['source_key'], $a);
        Storage::disk('digitalocean')->put('k12_sim_assets/' . $entries[0]['sha256'] . '.glb', 'something else');
        [$code, $out] = $this->copy($dir, ['--apply' => true]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('left alone', $out);
        $this->assertSame('something else', Storage::disk('digitalocean')->get('k12_sim_assets/' . $entries[0]['sha256'] . '.glb'));
        $this->assertSame(0, $this->row('lms_simulation_asset'));

        // the K12 prefix cannot be the source
        [$code] = $this->copy($dir, ['--from-prefix' => 'k12_sim_assets']);
        $this->assertSame(2, $code);

        // a file with no credit is never registered
        $noCredit = $this->packageDir([$this->buildPackage('copy-nc', [$this->manifestEntry($a, ['credit' => ''])])]);
        Storage::disk('digitalocean')->delete('k12_sim_assets/' . $entries[0]['sha256'] . '.glb');
        [$code, $out] = $this->copy($noCredit, ['--apply' => true]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('"credit" is required', $out);
        $this->assertSame(0, $this->row('lms_simulation_asset'));
        Storage::disk('digitalocean')->assertMissing('k12_sim_assets/' . $entries[0]['sha256'] . '.glb');
    }
}
