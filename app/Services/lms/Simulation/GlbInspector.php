<?php

namespace App\Services\lms\Simulation;

/**
 * Looks inside a binary glTF (.glb) BEFORE it is stored, so that a customer's upload cannot make the player reach outside the file, ask for a
 * feature it does not have, or run it out of memory. It reads the 12-byte header, walks the chunks and decodes only the JSON "description"
 * chunk; the geometry is never decoded. A picture inside the model has its header read (PNG, JPEG, WebP or KTX2) to learn its size, and nothing more.
 *
 * WHAT IT REFUSES, each with a sentence that names the file and the limit (every problem found is listed, not only the first):
 *   - a link to anything outside the file: every `buffers[].uri` and `images[].uri` must be absent (the data is the embedded BIN chunk) or a
 *     base64 `data:` URI, which is data inside the file. An address, a path, `file:` and the like are refused;
 *   - an extension the model REQUIRES (`extensionsRequired`) that is not on the allow-list: KHR_materials_*, KHR_texture_transform,
 *     KHR_mesh_quantization, KHR_texture_basisu, EXT_meshopt_compression, KHR_draco_mesh_compression, KHR_lights_punctual;
 *   - more than 300 000 triangles. Counted from the accessors' `count` (never from the bytes): a TRIANGLES primitive is its index count, or its
 *     POSITION count when it has no indices, divided by 3 (strips and fans: count - 2). The model is held to the larger of the sum over all
 *     primitives and the number it DRAWS, which counts a mesh once for every node that uses it (and once per instance of EXT_mesh_gpu_instancing),
 *     so a small mesh used a thousand times is a thousand times its size;
 *   - a picture inside the model larger than 4096 x 4096 pixels, or one that cannot be read as PNG, JPEG, WebP or KTX2;
 *   - more than 128 MB "decoded in all": every picture at width x height x 4 bytes (RGBA, no mip levels) plus the vertex and index data its
 *     meshes use (accessor count x element size, so compressed geometry counts at its unpacked size);
 *   - a description (JSON chunk) larger than 4 MB, which is far more than a model needs and is what bounds the memory this check itself uses.
 * The size of the file (25 MB) is checked by the caller before it gets here.
 *
 * Nothing here is a glTF validator (CI runs glTF-Validator on the shipped models); it answers "may this platform keep and play this file".
 */
final class GlbInspector
{
    public const MAX_TRIANGLES = 300000;
    public const MAX_TEXTURE_SIDE = 4096;
    public const MAX_DECODED_BYTES = 134217728;   // 128 MB
    public const MAX_JSON_BYTES = 1048576;        // 1 MB of description (about 50 times that once decoded into memory)
    public const MAX_IMAGES = 128;                // pictures inside one model
    public const MAX_IMAGE_SIDE = 4096;           // a picture uploaded on its own (the same side as a texture)
    public const MAX_HDR_SIDE = 4096;             // a Radiance HDR sky, its longest side in pixels

    /** Extensions a model may REQUIRE, besides anything that starts with KHR_materials_ (see extensionAllowed()). */
    public const ALLOWED_EXTENSIONS = [
        'KHR_texture_transform', 'KHR_mesh_quantization', 'KHR_texture_basisu', 'EXT_meshopt_compression', 'KHR_draco_mesh_compression', 'KHR_lights_punctual',
    ];

    private const MAX_NODE_DEPTH = 1000;
    private const PICTURE_HEAD = 1048576;   // a picture's size is in its first bytes; this many are enough for any real file
    private const HDR_HEAD = 65536;         // the header of a Radiance file is a few lines
    private const COMPONENT_BYTES = [5120 => 1, 5121 => 1, 5122 => 2, 5123 => 2, 5125 => 4, 5126 => 4];
    private const TYPE_COMPONENTS = ['SCALAR' => 1, 'VEC2' => 2, 'VEC3' => 3, 'VEC4' => 4, 'MAT2' => 4, 'MAT3' => 9, 'MAT4' => 16];
    private const KTX2_MAGIC = "\xABKTX 20\xBB\r\n\x1A\n";

    /**
     * Inspects the model at `$path`; `$name` is the file's name as the person knows it (it is quoted in the messages).
     *
     * @return array{triangles: int, pictures: int, textureBytes: int, geometryBytes: int, decodedBytes: int, largestPicture: ?array{0: int, 1: int}}
     *
     * @throws SimulationException with every problem found (422)
     */
    public function inspect(string $path, string $name): array
    {
        $label = $this->label($name);
        $size = is_file($path) ? filesize($path) : false;
        $handle = $size === false ? false : self::quietly(fn () => fopen($path, 'rb'));
        if ($handle === false) {
            throw new SimulationException(["{$label} could not be read."]);
        }
        try {
            return $this->read($handle, (int) $size, $label);
        } finally {
            fclose($handle);
        }
    }

    /** The same for bytes already in memory (tests, small files). @throws SimulationException */
    public function inspectBytes(string $bytes, string $name): array
    {
        $handle = fopen('php://memory', 'r+b');
        fwrite($handle, $bytes);
        rewind($handle);
        try {
            return $this->read($handle, strlen($bytes), $this->label($name));
        } finally {
            fclose($handle);
        }
    }

    /**
     * A picture uploaded on its own (PNG, JPEG or WebP, already told apart by its first bytes): it must be readable and at most 4096 x 4096.
     *
     * @return array{0: int, 1: int}  width and height
     *
     * @throws SimulationException
     */
    public function inspectPicture(string $path, string $name): array
    {
        $label = $this->label($name);
        $bytes = is_file($path) ? self::quietly(fn () => file_get_contents($path, false, null, 0, self::PICTURE_HEAD)) : false;
        $size = $bytes === false ? null : self::pictureSize($bytes);
        if ($size === null) {
            throw new SimulationException(["{$label} cannot be read as a picture: it may be damaged, or it is not a PNG, JPEG or WebP image."]);
        }
        if ($size[0] > self::MAX_IMAGE_SIDE || $size[1] > self::MAX_IMAGE_SIDE) {
            throw new SimulationException(["{$label} is {$size[0]} x {$size[1]} pixels; the limit for a picture is " . self::MAX_IMAGE_SIDE . ' x ' . self::MAX_IMAGE_SIDE . '. Make it smaller and upload it again.']);
        }

        return $size;
    }

    /**
     * What an upload adds for a Radiance HDR image: its header must say it is the plain RGBE format, and its size must be one a browser can hold.
     * Only the header is read (a few lines of text), never the pixels.
     *
     * @return array{0: int, 1: int} width and height
     *
     * @throws SimulationException
     */
    public function inspectHdr(string $path, string $name): array
    {
        $label = $this->label($name);
        $head = is_file($path) ? self::quietly(fn () => file_get_contents($path, false, null, 0, self::HDR_HEAD)) : false;
        $size = $head === false ? null : self::hdrSize($head);
        if ($size === null) {
            throw new SimulationException(["{$label} cannot be read as an HDR image: it may be damaged, or it is not a Radiance .hdr in the plain RGBE format (the one Poly Haven offers)."]);
        }
        if ($size[0] > self::MAX_HDR_SIDE || $size[1] > self::MAX_HDR_SIDE) {
            throw new SimulationException(["{$label} is {$size[0]} x {$size[1]} pixels; the limit for an HDR image is " . self::MAX_HDR_SIDE . ' x ' . self::MAX_HDR_SIDE . '. Download a smaller version (2K is plenty) and upload it again.']);
        }

        return $size;
    }

    /**
     * The width and height of a Radiance HDR file from its header, or null when it is not one in the plain RGBE format (`FORMAT=32-bit_rle_rgbe`, size
     * line `-Y h +X w`). The header is lines of text up to an empty line, then the size line; at most 64 lines and 1,000 characters each are read.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function hdrSize(string $bytes): ?array
    {
        $at = 0;
        $length = strlen($bytes);
        $line = static function () use (&$at, $bytes, $length): ?string {
            if ($at >= $length) {
                return null;
            }
            $end = strpos($bytes, "\n", $at);
            if ($end === false || $end - $at > 1000) {
                return null;
            }
            $text = substr($bytes, $at, $end - $at);
            $at = $end + 1;

            return $text;
        };
        $magic = $line();
        if ($magic !== '#?RADIANCE' && $magic !== '#?RGBE') {
            return null;
        }
        $format = false;
        for ($i = 0; $i < 64; $i++) {
            $text = $line();
            if ($text === null) {
                return null;
            }
            if ($text === '') {
                break;   // the empty line that ends the header
            }
            if ($text === 'FORMAT=32-bit_rle_rgbe') {
                $format = true;
            }
            if ($i === 63) {
                return null;   // no end to the header
            }
        }
        $size = $line();
        if (! $format || $size === null || ! preg_match('/^-Y ([1-9][0-9]{0,5}) \+X ([1-9][0-9]{0,5})$/D', $size, $m)) {
            return null;
        }

        return [(int) $m[2], (int) $m[1]];
    }

    /**
     * The width and height of a PNG, JPEG, WebP or KTX2 picture from its first bytes, or null when the bytes are none of these (or too damaged
     * to say). Never decodes the pixels.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function pictureSize(string $bytes): ?array
    {
        if (str_starts_with($bytes, self::KTX2_MAGIC)) {
            if (strlen($bytes) < 28) {
                return null;
            }
            $d = unpack('Vwidth/Vheight', substr($bytes, 20, 8));

            return $d['width'] > 0 ? [$d['width'], max(1, $d['height'])] : null;   // a height of 0 is a 1-D texture
        }
        if (strlen($bytes) < 12) {
            return null;   // no picture format has a header this short
        }
        $info = self::quietly(fn () => getimagesizefromstring($bytes));
        if ($info === false || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true) || $info[0] < 1 || $info[1] < 1) {
            return null;
        }

        return [(int) $info[0], (int) $info[1]];
    }

    /** Runs `$f` with PHP's own notices and warnings swallowed: a damaged picture makes the image functions complain, and that is an answer here, not an error. */
    private static function quietly(callable $f): mixed
    {
        set_error_handler(static fn (): bool => true);
        try {
            return $f();
        } finally {
            restore_error_handler();
        }
    }

    /** Whether a model may REQUIRE this extension. */
    public static function extensionAllowed(string $extension): bool
    {
        return in_array($extension, self::ALLOWED_EXTENSIONS, true) || preg_match('/^KHR_materials_[A-Za-z0-9_]+$/', $extension) === 1;
    }

    // ---------------------------------------------------------------- the file

    /**
     * @param  resource  $h
     * @return array{triangles: int, pictures: int, textureBytes: int, geometryBytes: int, decodedBytes: int, largestPicture: ?array{0: int, 1: int}}
     */
    private function read($h, int $size, string $label): array
    {
        $head = (string) fread($h, 12);
        if (strlen($head) < 12 || ! str_starts_with($head, 'glTF')) {
            $this->refuse("{$label} is not a binary glTF (.glb) file.");
        }
        ['v' => $version, 'l' => $length] = unpack('Vv/Vl', substr($head, 4));
        if ($version !== 2) {
            $this->refuse("{$label} is glTF version {$version}; only version 2 is accepted.");
        }
        if ($length !== $size) {
            $this->refuse("The header of {$label} declares {$length} bytes but the file is {$size}: it is cut short or has something added.");
        }

        // the parts: a JSON description first, then at most one BIN part of data; anything else is skipped
        $json = null;
        $bin = null;
        $parts = 0;
        for ($pos = 12; $pos < $size; $parts++) {
            // a binary glTF is one description (JSON) and at most one block of data (BIN). The loader in the player keeps the LAST part of each kind and this
            // check reads the FIRST, so a second one of either would let a model carry what was never inspected (and a file of millions of empty parts costs time)
            if ($parts >= 2) {
                $this->refuse("{$label} has more parts than a binary glTF may have: one description (JSON) and at most one block of data (BIN).");
            }
            if ($size - $pos < 8) {
                $this->refuse("{$label} has stray bytes after its last part.");
            }
            fseek($h, $pos);
            $chunk = (string) fread($h, 8);
            $len = unpack('Vlen', $chunk)['len'];
            $type = substr($chunk, 4, 4);
            $start = $pos + 8;
            if ($len > $size - $start) {
                $this->refuse("A part of {$label} runs past the end of the file.");
            }
            if ($parts === 0 && $type !== 'JSON') {
                $this->refuse("{$label} does not begin with its description part (JSON), so it is not a valid binary glTF.");
            }
            if ($parts === 1 && $type !== 'BIN' . chr(0)) {
                $this->refuse("The second part of {$label} must be its block of data (BIN): a binary glTF has one description (JSON) and at most one block of data.");
            }
            if ($type === 'JSON' && $json === null) {
                $json = [$start, $len];
            } elseif ($type === "BIN\0" && $bin === null) {
                $bin = [$start, $len];
            }
            $pos = $start + $len;
        }
        if ($json === null || $json[1] === 0) {
            $this->refuse("{$label} has no description part (JSON), so it is not a valid binary glTF.");
        }
        if ($json[1] > self::MAX_JSON_BYTES) {
            $this->refuse("The description part (JSON) of {$label} is " . $this->mb($json[1]) . ' MB; the limit is ' . $this->mb(self::MAX_JSON_BYTES) . ' MB. Remove what the model does not need (extra data, unused animations and nodes) and export it again.');
        }

        $text = rtrim($this->readAt($h, $json[0], $json[1]), "\0 \t\r\n");
        try {
            $gltf = json_decode($text, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->refuse("The description part (JSON) of {$label} cannot be read: " . $e->getMessage() . '.');
        }
        if (! is_array($gltf) || array_is_list($gltf)) {
            $this->refuse("The description part (JSON) of {$label} is not a glTF description.");
        }
        foreach (['buffers', 'bufferViews', 'images', 'accessors', 'meshes', 'nodes', 'scenes'] as $key) {
            if (isset($gltf[$key]) && (! is_array($gltf[$key]) || ! array_is_list($gltf[$key]))) {
                $this->refuse("The description of {$label} is not valid glTF: \"{$key}\" is not a list.");
            }
        }

        return $this->check($h, $gltf, $bin, $label);
    }

    /**
     * @param  resource  $h
     * @param  array<string, mixed>  $gltf
     * @param  array{0: int, 1: int}|null  $bin  where the BIN part is: offset and length
     */
    private function check($h, array $gltf, ?array $bin, string $label): array
    {
        $problems = [];

        $version = $gltf['asset']['version'] ?? null;
        if (! is_string($version) || ! str_starts_with($version, '2')) {
            $problems[] = "{$label} does not say it is glTF 2 (\"asset.version\" is " . (is_string($version) ? "\"{$this->shorten($version)}\"" : 'missing') . ').';
        }

        // extensions the model cannot be shown without
        $required = $gltf['extensionsRequired'] ?? [];
        $refused = [];
        foreach (is_array($required) ? $required : [$required] as $extension) {
            if (! is_string($extension) || ! self::extensionAllowed($extension)) {
                $refused[] = '"' . (is_string($extension) ? $this->shorten($extension) : '?') . '"';
            }
        }
        if ($refused !== []) {
            $problems[] = "{$label} needs the glTF extension " . implode(', ', array_unique($refused)) . ', which this platform cannot show. It accepts KHR_materials_*, ' . implode(', ', self::ALLOWED_EXTENSIONS) . '. Export the model again without it.';
        }

        // links to anything outside the file
        $buffers = $gltf['buffers'] ?? [];
        foreach ($buffers as $i => $buffer) {
            $uri = is_array($buffer) ? ($buffer['uri'] ?? null) : null;
            if ($uri !== null && ! $this->embeddedUri($uri)) {
                $problems[] = "{$label} reads data from outside itself (buffer {$i} points to \"" . $this->shorten($uri) . '"). A model must carry all its data inside the .glb file: export it again as one binary glTF with everything embedded.';
            }
        }
        $images = $gltf['images'] ?? [];
        if (count($images) > self::MAX_IMAGES) {
            throw new SimulationException(["{$label} has " . number_format(count($images)) . ' pictures; the limit is ' . self::MAX_IMAGES . '. Combine the pictures (a texture atlas) and export the model again.']);
        }
        foreach ($images as $i => $image) {
            $uri = is_array($image) ? ($image['uri'] ?? null) : null;
            if ($uri !== null && ! $this->embeddedUri($uri)) {
                $problems[] = "{$label} loads a picture from outside itself (picture {$i} points to \"" . $this->shorten($uri) . '"). A model must carry its pictures inside the .glb file: export it again as one binary glTF with the textures embedded.';
            }
        }

        // the pictures: readable, no larger than 4096 x 4096, and what they take once unpacked
        $textureBytes = 0;
        $largest = null;
        $dataBuffers = [];
        foreach ($images as $i => $image) {
            if (! is_array($image) || (isset($image['uri']) && ! $this->embeddedUri($image['uri']))) {
                continue;   // already named above
            }
            $name = $this->pictureName($image, $i);
            $bytes = $this->pictureBytes($h, $gltf, $bin, $image, $dataBuffers);
            $size = $bytes === null ? null : self::pictureSize($bytes);
            if ($size === null) {
                $problems[] = "{$name} in {$label} cannot be read as a picture (PNG, JPEG, WebP or KTX2): it may be damaged, or its data is not inside the file.";

                continue;
            }
            if ($size[0] > self::MAX_TEXTURE_SIDE || $size[1] > self::MAX_TEXTURE_SIDE) {
                $problems[] = "{$name} in {$label} is {$size[0]} x {$size[1]} pixels; the limit is " . self::MAX_TEXTURE_SIDE . ' x ' . self::MAX_TEXTURE_SIDE . '. Make the picture smaller and export the model again.';
            }
            $textureBytes += $size[0] * $size[1] * 4;
            if ($largest === null || $size[0] * $size[1] > $largest[0] * $largest[1]) {
                $largest = $size;
            }
        }

        // the shapes: triangles (as stored, and as drawn) and the size of the vertex and index data
        [$perMesh, $geometryBytes, $stored] = $this->meshes($gltf);
        $drawn = $this->drawn($gltf, $perMesh, $label, $problems);
        $triangles = max($stored, $drawn);
        if ($triangles > self::MAX_TRIANGLES) {
            $problems[] = "{$label} has " . number_format($triangles) . ' triangles' . ($drawn > $stored ? ' once every use of a shape is counted' : '') . '; the limit is ' . number_format(self::MAX_TRIANGLES) . '. Simplify the model (fewer triangles, or fewer copies of a shape) and export it again.';
        }

        $decoded = $textureBytes + $geometryBytes;
        if ($decoded > self::MAX_DECODED_BYTES) {
            $problems[] = "{$label} would take about " . $this->mb($decoded) . ' MB of memory once opened (pictures ' . $this->mb($textureBytes) . ' MB, shapes ' . $this->mb($geometryBytes) . ' MB); the limit is ' . $this->mb(self::MAX_DECODED_BYTES) . ' MB. Use smaller pictures or a lighter model.';
        }

        if ($problems !== []) {
            throw new SimulationException(array_values(array_unique($problems)));
        }

        return [
            'triangles' => (int) $triangles,
            'pictures' => count($images),
            'textureBytes' => (int) $textureBytes,
            'geometryBytes' => (int) $geometryBytes,
            'decodedBytes' => (int) $decoded,
            'largestPicture' => $largest,
        ];
    }

    // ---------------------------------------------------------------- shapes

    /**
     * The triangles of every mesh and the size of the vertex and index data its primitives use.
     *
     * @param  array<string, mixed>  $gltf
     * @return array{0: array<int, int>, 1: int, 2: int}  triangles per mesh, bytes of geometry, triangles summed over all meshes
     */
    private function meshes(array $gltf): array
    {
        $accessors = $gltf['accessors'] ?? [];
        $perMesh = [];
        $stored = 0;
        foreach ($gltf['meshes'] ?? [] as $m => $mesh) {
            $triangles = 0;
            foreach (is_array($mesh) && is_array($mesh['primitives'] ?? null) ? $mesh['primitives'] : [] as $primitive) {
                if (! is_array($primitive)) {
                    continue;
                }
                $mode = $this->int($primitive['mode'] ?? 4) ?? 4;
                $attributes = is_array($primitive['attributes'] ?? null) ? $primitive['attributes'] : [];
                $indices = $this->int($primitive['indices'] ?? null);
                $count = $this->accessorCount($accessors, $indices ?? ($this->int($attributes['POSITION'] ?? null)));
                if ($count !== null) {
                    $triangles += match (true) {
                        $mode === 4 => intdiv($count, 3),
                        $mode === 5, $mode === 6 => max(0, $count - 2),
                        default => 0,
                    };
                }
            }
            $perMesh[$m] = $triangles;
            $stored += $triangles;
        }

        // every accessor the loader may allocate counts, not only the ones a mesh reads: an animation or a skin can reach one too
        $bytes = 0;
        foreach (array_keys($accessors) as $index) {
            $a = $accessors[$index] ?? null;
            $count = $this->accessorCount($accessors, $index);
            if ($count === null || ! is_array($a)) {
                continue;
            }
            $component = self::COMPONENT_BYTES[$this->int($a['componentType'] ?? null)] ?? 4;
            $type = is_string($a['type'] ?? null) ? $a['type'] : '';
            $bytes += $count * $component * (self::TYPE_COMPONENTS[$type] ?? 4);
        }

        return [$perMesh, $bytes, $stored];
    }

    /**
     * The triangles the model draws: every node that uses a mesh counts that mesh again, and so does every instance of
     * EXT_mesh_gpu_instancing. Walks the scene's tree once, remembering each subtree, so a tangled one costs no more than a plain one.
     *
     * @param  array<string, mixed>  $gltf
     * @param  array<int, int>  $perMesh
     * @param  list<string>  $problems
     */
    private function drawn(array $gltf, array $perMesh, string $label, array &$problems): int|float
    {
        $nodes = $gltf['nodes'] ?? [];
        if ($nodes === [] || array_sum($perMesh) === 0) {
            return array_sum($perMesh);
        }
        $accessors = $gltf['accessors'] ?? [];
        $children = [];
        $isChild = [];
        foreach ($nodes as $n => $node) {
            $children[$n] = [];
            foreach (is_array($node) && is_array($node['children'] ?? null) ? $node['children'] : [] as $child) {
                if (($child = $this->int($child)) !== null && isset($nodes[$child])) {
                    $children[$n][] = $child;
                    $isChild[$child] = true;
                }
            }
        }
        $roots = [];
        $scenes = $gltf['scenes'] ?? [];
        $scene = $scenes[$this->int($gltf['scene'] ?? 0) ?? 0] ?? null;
        if (is_array($scene) && is_array($scene['nodes'] ?? null)) {
            foreach ($scene['nodes'] as $root) {
                if (($root = $this->int($root)) !== null && isset($nodes[$root])) {
                    $roots[] = $root;
                }
            }
        } else {
            $roots = array_values(array_diff(array_keys($nodes), array_keys($isChild)));
        }

        $own = function (int $n) use ($nodes, $perMesh, $accessors): int {
            $node = $nodes[$n];
            $mesh = is_array($node) ? $this->int($node['mesh'] ?? null) : null;
            $triangles = $mesh === null ? 0 : ($perMesh[$mesh] ?? 0);
            $instancing = is_array($node) ? ($node['extensions']['EXT_mesh_gpu_instancing']['attributes'] ?? null) : null;
            if ($triangles > 0 && is_array($instancing)) {
                $copies = 1;
                foreach ($instancing as $accessor) {
                    $copies = max($copies, $this->accessorCount($accessors, $this->int($accessor)) ?? 1);
                }
                $triangles *= $copies;
            }

            return $triangles;
        };

        $memo = [];
        $open = [];
        $walk = function (int $n, int $depth) use (&$walk, &$memo, &$open, $children, $own, $label, &$problems): int|float {
            if (isset($memo[$n])) {
                return $memo[$n];
            }
            if (isset($open[$n]) || $depth > self::MAX_NODE_DEPTH) {
                $problems[] = isset($open[$n])
                    ? "The scene of {$label} is a loop (a node contains itself)."
                    : "The scene of {$label} is nested more than " . self::MAX_NODE_DEPTH . ' levels deep.';

                return 0;
            }
            $open[$n] = true;
            $sum = $own($n);
            foreach ($children[$n] as $child) {
                $sum += $walk($child, $depth + 1);
            }
            unset($open[$n]);

            return $memo[$n] = $sum;
        };
        $total = 0;
        foreach ($roots as $root) {
            $total += $walk($root, 0);
        }

        return $total;
    }

    // ---------------------------------------------------------------- pictures and links

    /** The first bytes of a picture inside the model: from its buffer view (the BIN part, or a data: buffer) or from its own data: URI. */
    private function pictureBytes($h, array $gltf, ?array $bin, array $image, array &$dataBuffers): ?string
    {
        if (isset($image['uri'])) {
            return $this->decodeDataUri($image['uri'], self::PICTURE_HEAD);
        }
        $view = $gltf['bufferViews'][$this->int($image['bufferView'] ?? null) ?? -1] ?? null;
        if (! is_array($view)) {
            return null;
        }
        $bufferIndex = $this->int($view['buffer'] ?? null);
        $offset = $this->int($view['byteOffset'] ?? 0);
        $length = $this->int($view['byteLength'] ?? null);
        $buffer = $bufferIndex === null ? null : ($gltf['buffers'][$bufferIndex] ?? null);
        if (! is_array($buffer) || $offset === null || $length === null || $offset < 0 || $length < 1) {
            return null;
        }
        if (! isset($buffer['uri'])) {   // the BIN part, which only the first buffer of a .glb is
            if ($bufferIndex !== 0 || $bin === null || $offset + $length > $bin[1]) {
                return null;
            }

            return $this->readAt($h, $bin[0] + $offset, min($length, self::PICTURE_HEAD));
        }
        $data = $dataBuffers[$bufferIndex] ??= $this->decodeDataUri($buffer['uri'], null);

        return $data === null || $offset + $length > strlen($data) ? null : substr($data, $offset, min($length, self::PICTURE_HEAD));
    }

    /** Whether a `uri` is data inside the file (a base64 `data:` URI) and so not a link to anything outside it. */
    private function embeddedUri(mixed $uri): bool
    {
        return is_string($uri) && preg_match('#^data:[^,]*;base64,#i', $uri) === 1;
    }

    private function decodeDataUri(mixed $uri, ?int $limit): ?string
    {
        if (! $this->embeddedUri($uri)) {
            return null;
        }
        $payload = substr($uri, strpos($uri, ',') + 1);
        if ($limit !== null) {
            $payload = substr($payload, 0, intdiv($limit, 3) * 4);   // enough characters for `$limit` bytes
        }
        $bytes = base64_decode($payload, true);

        return $bytes === false ? null : $bytes;
    }

    private function pictureName(array $image, int|string $index): string
    {
        $name = $image['name'] ?? null;

        return 'Picture ' . $index . (is_string($name) && trim($name) !== '' ? ' ("' . $this->shorten($name, 40) . '")' : '');
    }

    // ---------------------------------------------------------------- small helpers

    /** The `count` of accessor `$index`, or null when there is no such accessor or its count is not a whole number. */
    private function accessorCount(array $accessors, ?int $index): ?int
    {
        $a = $index === null ? null : ($accessors[$index] ?? null);
        $count = is_array($a) ? $this->int($a['count'] ?? null) : null;

        return $count !== null && $count >= 0 ? $count : null;
    }

    /** A whole number from JSON (an integer, or a float with nothing after the point), or null. */
    private function int(mixed $v): ?int
    {
        if (is_int($v)) {
            return $v;
        }

        return is_float($v) && is_finite($v) && floor($v) === $v && abs($v) < 9.0e15 ? (int) $v : null;
    }

    /** @param  resource  $h */
    private function readAt($h, int $offset, int $length): string
    {
        if ($length <= 0 || fseek($h, $offset) !== 0) {
            return '';
        }

        return (string) fread($h, $length);
    }

    private function label(string $name): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name));

        return $name === '' ? 'the model' : '"' . $this->shorten($name, 80) . '"';
    }

    /** A value from the file made safe to quote in a sentence: control characters gone, long text cut short, anything that is not text a "?". */
    private function shorten(mixed $text, int $max = 60): string
    {
        $text = is_scalar($text) ? (string) preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $text) : '?';

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
    }

    private function mb(int|float $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1, '.', ''), '0'), '.');
    }

    private function refuse(string $message): never
    {
        throw new SimulationException([$message]);
    }
}
