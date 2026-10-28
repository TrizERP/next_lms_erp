<?php

namespace Tests\Unit;

use App\Services\lms\Simulation\GlbInspector;
use App\Services\lms\Simulation\SimulationException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The GLB inspector reads a model's header and description BEFORE it is stored. Every file here is a few hundred bytes of hand-made
 * fixture for the parser (a description and, where it matters, a few bytes of picture header), not 3D content: what is under test is what the
 * inspector refuses and what it lets through, and that it says so in plain words that name the file and the limit.
 */
class PrayogshalaSimulationGlbInspectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The inspector reads files and touches no table; the guard stays so that no test in this feature can reach the shared database.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName(), 'refusing to run against a real database');
    }

    // ── fixtures ─────────────────────────────────────────────────────────────

    /** A binary glTF: the 12-byte header, a JSON part and (when given) a BIN part. */
    private function glb(array|string $gltf, ?string $bin = null): string
    {
        $json = is_string($gltf) ? $gltf : json_encode($gltf, JSON_UNESCAPED_SLASHES);
        $json .= str_repeat(' ', (4 - strlen($json) % 4) % 4);
        $body = pack('V', strlen($json)) . 'JSON' . $json;
        if ($bin !== null) {
            $bin .= str_repeat("\0", (4 - strlen($bin) % 4) % 4);
            $body .= pack('V', strlen($bin)) . "BIN\0" . $bin;
        }

        return 'glTF' . pack('V', 2) . pack('V', 12 + strlen($body)) . $body;
    }

    private function model(array $extra = []): array
    {
        return $extra + ['asset' => ['version' => '2.0']];
    }

    private function png(int $w, int $h): string
    {
        $ihdr = pack('NNCCCCC', $w, $h, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr));
    }

    private function jpeg(int $w, int $h): string
    {
        return "\xFF\xD8\xFF\xE0" . pack('n', 16) . "JFIF\0\x01\x01\x00\x00\x01\x00\x01\x00\x00" . "\xFF\xC0" . pack('n', 17) . "\x08" . pack('nn', $h, $w) . "\x03\x01\x22\x00\x02\x11\x01\x03\x11\x01\xFF\xD9";
    }

    private function webp(int $w, int $h): string
    {
        $payload = "\0\0\0\0" . substr(pack('V', $w - 1), 0, 3) . substr(pack('V', $h - 1), 0, 3);
        $chunk = 'VP8X' . pack('V', strlen($payload)) . $payload;

        return 'RIFF' . pack('V', 4 + strlen($chunk)) . 'WEBP' . $chunk;
    }

    private function ktx2(int $w, int $h): string
    {
        return "\xABKTX 20\xBB\r\n\x1A\n" . pack('V', 0) . pack('V', 1) . pack('V', $w) . pack('V', $h) . str_repeat("\0", 16);
    }

    /** A model with one mesh whose single primitive has `$indices` indices over `$vertices` vertices (and nothing else). */
    private function mesh(int $indices, int $vertices = 3, array $primitive = []): array
    {
        return $this->model([
            'accessors' => [['count' => $vertices, 'componentType' => 5126, 'type' => 'VEC3'], ['count' => $indices, 'componentType' => 5123, 'type' => 'SCALAR']],
            'meshes' => [['primitives' => [$primitive + ['attributes' => ['POSITION' => 0], 'indices' => 1]]]],
        ]);
    }

    /** A model with these pictures in its BIN part, one after the other. @param list<string> $pictures */
    private function withPictures(array $pictures, array $images = []): string
    {
        $bin = '';
        $views = [];
        $list = [];
        foreach ($pictures as $i => $bytes) {
            $views[] = ['buffer' => 0, 'byteOffset' => strlen($bin), 'byteLength' => strlen($bytes)];
            $list[] = ($images[$i] ?? []) + ['bufferView' => $i, 'mimeType' => 'image/png'];
            $bin .= $bytes . str_repeat("\0", (4 - strlen($bytes) % 4) % 4);
        }

        return $this->glb($this->model(['buffers' => [['byteLength' => strlen($bin)]], 'bufferViews' => $views, 'images' => $list]), $bin);
    }

    /** The problems the inspector finds in these bytes (an empty string when it accepts them). */
    private function problems(string $bytes, string $name = 'eye.glb'): string
    {
        try {
            (new GlbInspector())->inspectBytes($bytes, $name);
        } catch (SimulationException $e) {
            return implode(' | ', $e->problems);
        }

        return '';
    }

    private function facts(string $bytes): array
    {
        return (new GlbInspector())->inspectBytes($bytes, 'eye.glb');
    }

    // ── what it lets through ─────────────────────────────────────────────────

    public function test_a_plain_model_is_accepted_and_its_numbers_are_reported(): void
    {
        $this->assertSame('', $this->problems($this->glb($this->model())));
        $this->assertSame(['triangles' => 0, 'pictures' => 0, 'textureBytes' => 0, 'geometryBytes' => 0, 'decodedBytes' => 0, 'largestPicture' => null], $this->facts($this->glb($this->model())));

        // 12 indices make 4 triangles; the vertex data is 3 floats x 6 vertices and the indices are 2 bytes each
        $facts = $this->facts($this->glb($this->mesh(12, 6), str_repeat("\0", 16)));
        $this->assertSame([4, 6 * 12 + 12 * 2], [$facts['triangles'], $facts['geometryBytes']]);
    }

    public function test_the_file_on_disk_is_read_the_same_as_the_same_bytes(): void
    {
        $bytes = $this->withPictures([$this->png(64, 32)]);
        $file = (string) tempnam(sys_get_temp_dir(), 'glb');
        file_put_contents($file, $bytes);
        try {
            $this->assertSame($this->facts($bytes), (new GlbInspector())->inspect($file, 'eye.glb'));
            $this->assertSame([64, 32], (new GlbInspector())->inspect($file, 'eye.glb')['largestPicture']);
        } finally {
            @unlink($file);
        }
        $this->expectException(SimulationException::class);
        (new GlbInspector())->inspect($file, 'gone.glb');   // no such file
    }

    public function test_every_extension_on_the_allow_list_may_be_required(): void
    {
        $allowed = ['KHR_materials_clearcoat', 'KHR_materials_emissive_strength', 'KHR_texture_transform', 'KHR_mesh_quantization', 'KHR_texture_basisu', 'EXT_meshopt_compression', 'KHR_draco_mesh_compression', 'KHR_lights_punctual'];
        $this->assertSame('', $this->problems($this->glb($this->model(['extensionsRequired' => $allowed, 'extensionsUsed' => array_merge($allowed, ['EXT_texture_webp', 'VENDOR_anything'])]))));
        foreach ($allowed as $extension) {
            $this->assertTrue(GlbInspector::extensionAllowed($extension), $extension);
        }
    }

    // ── links to anything outside the file ───────────────────────────────────

    public function test_a_link_to_anything_outside_the_file_is_refused_naming_the_file(): void
    {
        foreach (['https://example.org/data.bin', 'http://example.org/x', 'HTTP://EXAMPLE.ORG/X', ' https://example.org/x', 'data.bin', '../secret.bin', '/etc/passwd', 'file:///etc/passwd', '//example.org/x', 'ftp://example.org/x', 'data:text/html,<script>', 'data:application/octet-stream,AAAA', 5] as $uri) {
            foreach (['buffers' => ['byteLength' => 4, 'uri' => $uri], 'images' => ['uri' => $uri]] as $key => $entry) {
                $p = $this->problems($this->glb($this->model([$key => [$entry]])));
                $this->assertStringContainsString('"eye.glb"', $p, json_encode([$key, $uri]));
                $this->assertStringContainsString('outside itself', $p, json_encode([$key, $uri]));
            }
        }
        $this->assertStringContainsString('buffer 1 points to', $this->problems($this->glb($this->model(['buffers' => [['byteLength' => 4], ['byteLength' => 4, 'uri' => 'x.bin']]]))));
    }

    public function test_data_inside_the_file_is_not_a_link(): void
    {
        $data = 'data:application/octet-stream;base64,' . base64_encode(str_repeat("\0", 8));
        $this->assertSame('', $this->problems($this->glb($this->model(['buffers' => [['byteLength' => 8, 'uri' => $data]]]))));

        // a picture inside a data: URI is read like one in the BIN part
        $picture = 'data:image/png;base64,' . base64_encode($this->png(8192, 8));
        $p = $this->problems($this->glb($this->model(['images' => [['uri' => $picture]]])));
        $this->assertStringContainsString('8192 x 8', $p);
        $this->assertSame('', $this->problems($this->glb($this->model(['images' => [['uri' => 'data:image/png;base64,' . base64_encode($this->png(8, 8))]]]))));
    }

    // ── extensions ───────────────────────────────────────────────────────────

    public function test_an_extension_the_model_requires_and_the_platform_cannot_show_is_refused_by_name(): void
    {
        $p = $this->problems($this->glb($this->model(['extensionsRequired' => ['KHR_materials_unlit', 'EXT_texture_webp', 'VENDOR_magic']])));
        $this->assertStringContainsString('"EXT_texture_webp"', $p);
        $this->assertStringContainsString('"VENDOR_magic"', $p);
        $this->assertStringNotContainsString('"KHR_materials_unlit"', $p, 'the allowed one is not named as refused');
        $this->assertStringContainsString('"eye.glb"', $p);
        $this->assertStringContainsString('KHR_materials_*', $p, 'and the sentence says what is accepted');

        foreach (['KHR_materials_', 'khr_materials_x', 'KHR_materialsx', 'KHR_texture_transform2', 'KHR_materials_a b'] as $extension) {
            $this->assertFalse(GlbInspector::extensionAllowed($extension), $extension);
        }
        $this->assertStringContainsString('cannot show', $this->problems($this->glb($this->model(['extensionsRequired' => [5, ['x']]]))));
    }

    // ── triangles ────────────────────────────────────────────────────────────

    public function test_300000_triangles_are_allowed_and_one_more_is_not(): void
    {
        $this->assertSame('', $this->problems($this->glb($this->mesh(900000, 450000))));
        $p = $this->problems($this->glb($this->mesh(900003, 450000)));
        $this->assertStringContainsString('"eye.glb" has 300,001 triangles', $p);
        $this->assertStringContainsString('the limit is 300,000', $p);
    }

    public function test_triangles_come_from_the_positions_when_there_are_no_indices_and_strips_and_fans_count_two_less(): void
    {
        $noIndices = $this->model(['accessors' => [['count' => 900, 'componentType' => 5126, 'type' => 'VEC3']], 'meshes' => [['primitives' => [['attributes' => ['POSITION' => 0]]]]]]);
        $this->assertSame(300, $this->facts($this->glb($noIndices))['triangles']);

        foreach ([4 => 300, 5 => 898, 6 => 898, 0 => 0, 1 => 0, 3 => 0] as $mode => $expected) {
            $this->assertSame($expected, $this->facts($this->glb($this->mesh(900, 900, ['mode' => $mode])))['triangles'], "mode {$mode}");
        }
    }

    public function test_a_shape_used_many_times_counts_every_time(): void
    {
        // 3,000 triangles once is nothing; 101 nodes using it draw 303,000
        $nodes = array_map(fn ($n) => ['mesh' => 0], range(1, 101));
        $model = $this->mesh(9000, 9000) + ['nodes' => $nodes, 'scenes' => [['nodes' => range(0, 100)]]];
        $p = $this->problems($this->glb($model));
        $this->assertStringContainsString('303,000 triangles once every use of a shape is counted', $p);
        $this->assertSame(3000, $this->facts($this->glb($this->mesh(9000, 9000) + ['nodes' => [['mesh' => 0]], 'scenes' => [['nodes' => [0]]]]))['triangles']);

        // instances of one node count too
        $instanced = $this->mesh(9000, 9000);
        $instanced['accessors'][] = ['count' => 101, 'componentType' => 5126, 'type' => 'VEC3'];
        $instanced['nodes'] = [['mesh' => 0, 'extensions' => ['EXT_mesh_gpu_instancing' => ['attributes' => ['TRANSLATION' => 2]]]]];
        $instanced['scenes'] = [['nodes' => [0]]];
        $this->assertStringContainsString('303,000 triangles', $this->problems($this->glb($instanced)));

        // a shared child is counted once for each parent that has it, without walking it again
        $shared = $this->mesh(9000, 9000) + ['nodes' => [['children' => [1, 1]], ['mesh' => 0]], 'scenes' => [['nodes' => [0]]]];
        $this->assertSame(6000, $this->facts($this->glb($shared))['triangles']);
    }

    public function test_a_scene_that_contains_itself_is_refused_not_walked_for_ever(): void
    {
        $loop = $this->mesh(9, 9) + ['nodes' => [['mesh' => 0, 'children' => [1]], ['children' => [0]]], 'scenes' => [['nodes' => [0]]]];
        $this->assertStringContainsString('is a loop', $this->problems($this->glb($loop)));
        $this->assertStringContainsString('is a loop', $this->problems($this->glb($this->mesh(9, 9) + ['nodes' => [['mesh' => 0, 'children' => [0]]], 'scenes' => [['nodes' => [0]]]])));
        // with no scene to start from, a node that is only its own child has no root: nothing is drawn, so nothing loops
        $this->assertSame('', $this->problems($this->glb($this->mesh(9, 9) + ['nodes' => [['mesh' => 0, 'children' => [0]]]])));
    }

    // ── pictures ─────────────────────────────────────────────────────────────

    public function test_a_picture_may_be_4096_by_4096_and_no_larger_whatever_its_format(): void
    {
        foreach (['png' => 'png', 'jpeg' => 'jpeg', 'webp' => 'webp', 'ktx2' => 'ktx2'] as $format => $make) {
            $this->assertSame('', $this->problems($this->withPictures([$this->{$make}(4096, 4096)])), "{$format} at the limit");
            foreach ([[4097, 16], [16, 4097], [8192, 4096]] as [$w, $h]) {
                $p = $this->problems($this->withPictures([$this->{$make}($w, $h)], [['name' => 'skin']]));
                $this->assertStringContainsString("Picture 0 (\"skin\") in \"eye.glb\" is {$w} x {$h} pixels; the limit is 4096 x 4096", $p, "{$format} {$w} x {$h}");
            }
        }
        $this->assertSame([4096, 2048], $this->facts($this->withPictures([$this->png(16, 16), $this->webp(4096, 2048)]))['largestPicture']);
    }

    public function test_a_picture_that_cannot_be_read_is_refused(): void
    {
        $p = $this->problems($this->withPictures([$this->png(8, 8), 'this is not a picture at all, however it is named']));
        $this->assertStringContainsString('Picture 1 in "eye.glb" cannot be read as a picture', $p);
        $this->assertStringNotContainsString('Picture 0', $p);

        // one with no data, one whose view lies outside the BIN part, one in a buffer that is not the BIN part
        foreach ([[['name' => 'none']], [['bufferView' => 0]]] as $images) {
            $this->assertStringContainsString('cannot be read as a picture', $this->problems($this->glb($this->model(['images' => $images, 'buffers' => [['byteLength' => 8]], 'bufferViews' => [['buffer' => 0, 'byteLength' => 1000]]]), str_repeat("\0", 8))));
        }
        $this->assertStringContainsString('cannot be read as a picture', $this->problems($this->glb($this->model(['images' => [['bufferView' => 0]], 'buffers' => [['byteLength' => 8], ['byteLength' => 8]], 'bufferViews' => [['buffer' => 1, 'byteLength' => 8]]]), str_repeat("\0", 8))));
    }

    public function test_two_4096_pictures_fit_in_128_mb_and_three_do_not(): void
    {
        $big = $this->png(4096, 4096);   // 64 MB once unpacked
        $this->assertSame('', $this->problems($this->withPictures([$big, $big])));
        $this->assertSame(134217728, $this->facts($this->withPictures([$big, $big]))['decodedBytes']);

        $p = $this->problems($this->withPictures([$big, $big, $big]));
        $this->assertStringContainsString('"eye.glb" would take about 192 MB of memory once opened', $p);
        $this->assertStringContainsString('the limit is 128 MB', $p);
    }

    public function test_the_shapes_count_toward_the_128_mb_too(): void
    {
        // one triangle, but 12 million vertices of 3 floats: 144 MB of vertex data
        $heavy = $this->model(['accessors' => [['count' => 12000000, 'componentType' => 5126, 'type' => 'VEC3'], ['count' => 3, 'componentType' => 5123, 'type' => 'SCALAR']],
            'meshes' => [['primitives' => [['attributes' => ['POSITION' => 0], 'indices' => 1]]]]]);
        $p = $this->problems($this->glb($heavy));
        $this->assertStringContainsString('would take about 137.3 MB', $p);
        $this->assertStringContainsString('shapes 137.3 MB', $p);
        $this->assertStringNotContainsString('triangles', $p, 'it is the memory that is over, not the triangles');
    }

    // ── a picture uploaded on its own ────────────────────────────────────────

    /** The first bytes of a Radiance HDR file: the header a real one starts with (Poly Haven's), and a body that is not read. */
    private function hdr(int $width = 1024, int $height = 512, string $format = 'FORMAT=32-bit_rle_rgbe', string $magic = '#?RADIANCE'): string
    {
        return "{$magic}\n# Made with a tool\n{$format}\n\n-Y {$height} +X {$width}\n" . str_repeat("\x80\x80\x80\x80", 64);
    }

    public function test_the_size_of_a_radiance_hdr_comes_from_its_header_and_only_the_plain_rgbe_format_is_taken(): void
    {
        $this->assertSame([1024, 512], GlbInspector::hdrSize($this->hdr()));
        $this->assertSame([2048, 1024], GlbInspector::hdrSize($this->hdr(2048, 1024, 'FORMAT=32-bit_rle_rgbe', '#?RGBE')));
        // not the right format, not the right orientation, a header that never ends, a size that is not a size, a file that is not one
        foreach ([
            $this->hdr(1024, 512, 'FORMAT=32-bit_rle_xyze'),
            "#?RADIANCE\nFORMAT=32-bit_rle_rgbe\n\n+Y 512 +X 1024\n",
            "#?RADIANCE\nFORMAT=32-bit_rle_rgbe\n\n-Y 0 +X 1024\n",
            "#?RADIANCE\nFORMAT=32-bit_rle_rgbe\n\n-Y 512 +X 99999999\n",
            "#?RADIANCE\nFORMAT=32-bit_rle_rgbe\n",
            "#?RADIANCE\n" . str_repeat("# a comment line\n", 100) . "\n-Y 512 +X 1024\n",
            "#?RADIANCE\n" . str_repeat('x', 2000) . "\n\n-Y 512 +X 1024\n",
            "#?RADIANCE\nFORMAT=32-bit_rle_rgbe\n\n-Y 512 +X 1024",
            "\x89PNG\r\n\x1a\n" . str_repeat("\0", 40),
            '',
        ] as $not) {
            $this->assertNull(GlbInspector::hdrSize($not), json_encode(substr($not, 0, 40)));
        }
    }

    public function test_an_hdr_uploaded_on_its_own_is_checked_for_its_format_and_its_side(): void
    {
        $inspector = new GlbInspector();
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hdr-' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            $file = $dir . DIRECTORY_SEPARATOR . 'sky.hdr';
            file_put_contents($file, $this->hdr(2048, 1024));
            $this->assertSame([2048, 1024], $inspector->inspectHdr($file, 'sky.hdr'));
            file_put_contents($file, $this->hdr(4096, 2048));
            $this->assertSame([4096, 2048], $inspector->inspectHdr($file, 'sky.hdr'));
            file_put_contents($file, $this->hdr(8192, 4096));
            try {
                $inspector->inspectHdr($file, 'sky.hdr');
                $this->fail('an 8K sky is over the limit');
            } catch (SimulationException $e) {
                $this->assertStringContainsString('8192 x 4096 pixels; the limit for an HDR image is 4096 x 4096', $e->problems[0]);
            }
            file_put_contents($file, $this->hdr(1024, 512, 'FORMAT=32-bit_rle_xyze'));
            $this->expectException(SimulationException::class);
            $inspector->inspectHdr($file, 'sky.hdr');
        } finally {
            @unlink($dir . DIRECTORY_SEPARATOR . 'sky.hdr');
            @rmdir($dir);
        }
    }

    public function test_a_picture_on_its_own_is_read_and_held_to_4096_by_4096(): void
    {
        $inspector = new GlbInspector();
        $file = (string) tempnam(sys_get_temp_dir(), 'pic');
        try {
            foreach (['png', 'jpeg', 'webp'] as $format) {
                file_put_contents($file, $this->{$format}(300, 200));
                $this->assertSame([300, 200], $inspector->inspectPicture($file, "photo.{$format}"));
            }
            file_put_contents($file, $this->png(4096, 4096));
            $this->assertSame([4096, 4096], $inspector->inspectPicture($file, 'wide.png'));

            file_put_contents($file, $this->png(4097, 10));
            try {
                $inspector->inspectPicture($file, 'wide.png');
                $this->fail('4097 wide should be refused');
            } catch (SimulationException $e) {
                $this->assertSame('"wide.png" is 4097 x 10 pixels; the limit for a picture is 4096 x 4096. Make it smaller and upload it again.', $e->getMessage());
            }
            foreach (['this is not a picture', "\x89PNG\r\n\x1a\n" . 'cut'] as $junk) {
                file_put_contents($file, $junk);
                try {
                    $inspector->inspectPicture($file, 'junk.png');
                    $this->fail('junk should be refused');
                } catch (SimulationException $e) {
                    $this->assertStringContainsString('"junk.png" cannot be read as a picture', $e->getMessage());
                }
            }
        } finally {
            @unlink($file);
        }
    }

    public function test_the_size_of_a_picture_is_read_from_its_header_only(): void
    {
        $this->assertSame([5, 7], GlbInspector::pictureSize($this->png(5, 7)));
        $this->assertSame([5, 7], GlbInspector::pictureSize($this->jpeg(5, 7)));
        $this->assertSame([5, 7], GlbInspector::pictureSize($this->webp(5, 7)));
        $this->assertSame([5, 7], GlbInspector::pictureSize($this->ktx2(5, 7)));
        $this->assertSame([5, 1], GlbInspector::pictureSize($this->ktx2(5, 0)), 'a height of 0 is a one-dimensional texture');
        foreach (['', 'GIF89a......', '<svg xmlns="http://www.w3.org/2000/svg"/>', substr($this->ktx2(5, 7), 0, 20), $this->ktx2(0, 7), $this->png(0, 5)] as $not) {
            $this->assertNull(GlbInspector::pictureSize($not), json_encode(substr($not, 0, 12)));
        }
    }

    // ── the file itself ──────────────────────────────────────────────────────

    public function test_what_is_not_a_binary_gltf_is_refused(): void
    {
        $good = $this->glb($this->model());
        $json = json_encode($this->model());
        $cases = [
            ['is not a binary glTF', 'GLTF' . substr($good, 4)],
            ['is not a binary glTF', 'glTF'],
            ['glTF version 1', 'glTF' . pack('V', 1) . substr($good, 8)],
            ['declares', substr($good, 0, 8) . pack('V', strlen($good) + 4) . substr($good, 12)],
            ['declares', $good . 'trailing'],
            ['runs past the end', substr($good, 0, 12) . pack('V', 99999) . 'JSON' . substr($good, 20)],
            ['does not begin with its description', 'glTF' . pack('V', 2) . pack('V', 12 + 8 + 4) . pack('V', 4) . "BIN\0" . "\0\0\0\0"],
            ['stray bytes', substr($good, 0, 8) . pack('V', strlen($good) + 3) . substr($good, 12) . 'abc'],
            ['cannot be read', $this->glb('{"asset":')],
            ['not a glTF description', $this->glb('[]')],
            ['not a glTF description', $this->glb('"text"')],
            ['not a glTF description', $this->glb('5')],
            ['does not say it is glTF 2', $this->glb('{"buffers":[]}')],
            ['does not say it is glTF 2', $this->glb('{"asset":{"version":"1.0"}}')],
            ['"buffers" is not a list', $this->glb($this->model(['buffers' => ['a' => []]]))],
            ['"meshes" is not a list', $this->glb($this->model(['meshes' => 'many']))],
        ];
        foreach ($cases as $n => [$needle, $bytes]) {
            $p = $this->problems($bytes);
            $this->assertStringContainsString($needle, $p, "case {$n}: {$p}");
        }
        $this->assertSame('', $this->problems($this->glb($json)), 'and the same description is fine');
        $this->assertSame('', $this->problems($this->glb($this->model() + ['extras' => ['x' => 1]])), 'a description with room to spare');
    }

    public function test_the_description_may_not_be_over_1_mb_and_a_part_that_is_not_the_data_part_is_refused(): void
    {
        $p = $this->problems($this->glb($this->model(['extras' => ['filler' => str_repeat('x', GlbInspector::MAX_JSON_BYTES)]])));
        $this->assertStringContainsString('"eye.glb"', $p);
        $this->assertStringContainsString('description part (JSON)', $p);
        $this->assertStringContainsString('the limit is 1 MB', $p);

        // a binary glTF is one description and at most one block of data: the player's loader keeps the LAST part of each kind and this check reads the FIRST,
        // so a part this does not know, or a second description, is refused instead of skipped (it could carry what was never inspected)
        $good = $this->glb($this->model());
        $withOther = substr($good, 0, 8) . pack('V', strlen($good) + 8 + 4) . substr($good, 12) . pack('V', 4) . 'XTRA' . 'abcd';
        $this->assertStringContainsString('must be its block of data (BIN)', $this->problems($withOther));
    }

    public function test_every_problem_is_listed_not_just_the_first(): void
    {
        $model = $this->mesh(900003, 450000) + ['extensionsRequired' => ['VENDOR_magic'], 'buffers' => [['byteLength' => 4, 'uri' => 'https://example.org/x.bin']]];
        $p = $this->problems($this->glb($model), 'big eye.glb');
        foreach (['outside itself', '"VENDOR_magic"', '300,001 triangles'] as $needle) {
            $this->assertStringContainsString($needle, $p);
        }
        $this->assertSame(3, substr_count($p, '"big eye.glb"'));
    }

    public function test_the_name_in_a_message_is_made_safe_and_short(): void
    {
        $p = $this->problems($this->glb($this->model(['extensionsRequired' => ['VENDOR_x']])), "ey\x00e\n" . str_repeat('x', 200) . '.glb');
        $this->assertStringNotContainsString("\x00", $p);
        $this->assertStringNotContainsString("\n", $p);
        $this->assertLessThan(400, strlen($p));
        $this->assertStringContainsString('the model', $this->problems($this->glb($this->model(['extensionsRequired' => ['VENDOR_x']])), ''), 'with no name it says "the model"');
    }

    public function test_strange_but_valid_json_never_crashes_the_inspector(): void
    {
        // whatever shape the file claims, the answer is a SimulationException or an acceptance - never a PHP error
        $weird = [
            $this->model(['accessors' => 'x', 'meshes' => [['primitives' => 'x']], 'nodes' => []]),
            $this->model(['accessors' => [5, null, 'a'], 'meshes' => [5, ['primitives' => [7, ['attributes' => 5, 'indices' => 'x', 'mode' => 'y']]]]]),
            $this->model(['accessors' => [['count' => 'many', 'type' => [], 'componentType' => []]], 'meshes' => [['primitives' => [['attributes' => ['POSITION' => 0], 'targets' => [5, ['POSITION' => 0]]]]]]]),
            $this->model(['nodes' => [5, ['children' => 'x'], ['children' => [9, 'a', null, 1], 'mesh' => 'x', 'extensions' => 'y']], 'scenes' => [5, ['nodes' => [0, 'a', 99]]], 'scene' => 'x']),
            $this->model(['images' => [5, null, ['bufferView' => 'x'], ['bufferView' => -1]], 'bufferViews' => [5, ['buffer' => 'x'], ['buffer' => 0, 'byteOffset' => -1, 'byteLength' => 4]], 'buffers' => [5, ['uri' => null]]]),
            $this->model(['extensionsRequired' => 'KHR_materials_unlit', 'extensionsUsed' => 5, 'scene' => 1.5]),
            $this->model(['accessors' => [['count' => 1.0e30]], 'meshes' => [['primitives' => [['attributes' => ['POSITION' => 0]]]]]]),
        ];
        foreach ($weird as $n => $model) {
            try {
                (new GlbInspector())->inspectBytes($this->glb($model, str_repeat("\0", 8)), 'weird.glb');
            } catch (SimulationException) {
                // refused, which is fine
            }
            $this->addToAssertionCount(1);
        }
    }
}
