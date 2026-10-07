<?php

namespace App\Services\QuestionGeneration\DragDrop;

use App\Services\QuestionGeneration\DragDrop\Contracts\DiagramImageStore;
use Illuminate\Support\Facades\Storage;

/**
 * Stores the picture where the manual Drag & Drop editor stores its own: the
 * DigitalOcean disk's public/h5p_content/, falling back to the local public folder the
 * way H5PDragDropController does.
 *
 * Named by content hash, so the same picture is stored once however many questions use it.
 */
class SpacesDiagramImageStore implements DiagramImageStore
{
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function store(string $bytes, string $mime): string
    {
        $filename = 'dragdrop_gen_' . sha1($bytes) . '.' . (self::EXTENSIONS[$mime] ?? 'jpg');

        try {
            $disk = Storage::disk('digitalocean');
            $path = 'public/h5p_content/' . $filename;
            if (!$disk->exists($path)) {
                $disk->put($path, $bytes, 'public');
            }
            if (!$disk->exists($path)) {
                throw new \RuntimeException('DigitalOcean upload failed');
            }

            return $disk->url($path);
        } catch (\Throwable $e) {
            $destination = public_path('h5p_content');
            if (!is_dir($destination)) {
                mkdir($destination, 0755, true);
            }
            if (file_put_contents($destination . DIRECTORY_SEPARATOR . $filename, $bytes) === false) {
                throw new \RuntimeException('The picture could not be saved.');
            }

            return asset('h5p_content/' . $filename);
        }
    }
}
