<?php

namespace App\Services\StudyDeck;

use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;
use Illuminate\Support\Facades\Storage;

/**
 * Where study-deck pictures are kept. Two stores, one contract:
 *
 *   directory - writes beside the generated bundle; used for the review run
 *               and never touches shared storage
 *   spaces    - the DigitalOcean disk, the only host RendersGeneratedContent
 *               accepts for <img>; used when the deck is actually stored
 *
 * Both name files by content hash, so one picture is kept once.
 */
final class StudyImageStores
{
    private const EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public static function directory(string $dir): DiagramImageStore
    {
        return new class($dir) implements DiagramImageStore {
            public function __construct(private readonly string $dir)
            {
            }

            public function store(string $bytes, string $mime): string
            {
                if (!is_dir($this->dir) && !mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
                    throw new \RuntimeException('Could not create ' . $this->dir);
                }
                $name = sha1($bytes) . '.' . (StudyImageStores::EXT_FOR[$mime] ?? 'jpg');
                file_put_contents($this->dir . '/' . $name, $bytes);

                return 'images/' . $name;
            }
        };
    }

    public static function spaces(): DiagramImageStore
    {
        return new class implements DiagramImageStore {
            public function store(string $bytes, string $mime): string
            {
                $path = 'public/lms_content_file/studydeck/' . sha1($bytes) . '.' . (StudyImageStores::EXT_FOR[$mime] ?? 'jpg');
                $disk = Storage::disk('digitalocean');
                if (!$disk->exists($path)) {
                    $disk->put($path, $bytes, 'public');
                }

                return $disk->url($path);
            }
        };
    }

    public const EXT_FOR = self::EXT;
}
