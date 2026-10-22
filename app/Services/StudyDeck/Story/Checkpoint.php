<?php

namespace App\Services\StudyDeck\Story;

/**
 * Finished story work kept on disk while a lesson is being generated: the bible, then each act's skeleton and written story.
 *
 * A story is long (many model calls). Without this, one act that fails late throws away every act before it. The folder is named
 * after a fingerprint of everything the work depends on (the teaching method, the template config, the planned acts and the chapter
 * text), so a changed chapter, template or method never picks up stale work. It holds only what already passed its checks, is
 * re-checked when read, and is deleted once the whole story has been generated.
 */
class Checkpoint
{
    private string $dir;

    /** @param array<int,mixed> $inputs everything the saved work depends on */
    public function __construct(string $base, array $inputs)
    {
        $this->dir = rtrim($base, '/\\') . '/' . substr(hash('sha1', (string) json_encode($inputs)), 0, 16);
    }

    /** @return array<string,mixed>|null */
    public function load(string $name): ?array
    {
        $path = $this->path($name);
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : null;
    }

    /** @param array<string,mixed> $data */
    public function save(string $name, array $data): void
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
        // Written whole and renamed into place, so a run that dies mid-write never leaves half a file to be read next time.
        $tmp = $this->path($name) . '.tmp';
        if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) {
            @rename($tmp, $this->path($name));
        }
    }

    public function clear(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    private function path(string $name): string
    {
        return $this->dir . '/' . preg_replace('/[^a-z0-9_-]/i', '_', $name) . '.json';
    }
}
