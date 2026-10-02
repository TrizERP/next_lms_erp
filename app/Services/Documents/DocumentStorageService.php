<?php

namespace App\Services\Documents;

use App\Models\Documents\DocumentMaster;
use App\Models\Documents\DocumentHistory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentStorageService
{
    protected string $disk;

    public function __construct()
    {
        $this->disk = config('idms.disk', 'digitalocean');
    }

    /**
     * Store an uploaded file in Spaces with randomized key (documents/{yyyy}/{mm}/{uuid}.{ext})
     */
    public function storeUpload(UploadedFile $file): array
    {
        $year = date('Y');
        $month = date('m');
        $uuid = Str::uuid()->toString();
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $fileName = "{$uuid}.{$extension}";
        $path = "documents/{$year}/{$month}/{$fileName}";

        $stream = fopen($file->getRealPath(), 'r+');
        Storage::disk($this->disk)->put($path, $stream, 'private');
        if (is_resource($stream)) {
            fclose($stream);
        }

        $checksum = hash_file('sha256', $file->getRealPath());

        return [
            'storage_path' => $path,
            'checksum_sha256' => $checksum,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'original_file_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Store raw file contents directly
     */
    public function storeContent(string $content, string $extension): array
    {
        $year = date('Y');
        $month = date('m');
        $uuid = Str::uuid()->toString();
        $fileName = "{$uuid}.{$extension}";
        $path = "documents/{$year}/{$month}/{$fileName}";

        Storage::disk($this->disk)->put($path, $content, 'private');
        $checksum = hash('sha256', $content);

        return [
            'storage_path' => $path,
            'checksum_sha256' => $checksum,
            'size' => strlen($content),
        ];
    }

    /**
     * Generate short-lived temporary signed URL for viewing or downloading (valid 15 minutes)
     */
    public function getTemporaryUrl(string $path, int $minutes = 15): string
    {
        return Storage::disk($this->disk)->temporaryUrl($path, now()->addMinutes($minutes));
    }

    /**
     * Get stream resource for a stored file
     */
    public function readStream(string $path)
    {
        return Storage::disk($this->disk)->readStream($path);
    }

    /**
     * Get raw content of a stored file
     */
    public function get(string $path): string
    {
        return Storage::disk($this->disk)->get($path);
    }
}
