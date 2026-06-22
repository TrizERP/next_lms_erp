<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use ZipArchive;

class FileController extends Controller
{
    public function downloadFolder()
    {
        $folder = 'public/he_staff_document/';
        $zipName = storage_path('app/temp_documents.zip');

        $zip = new ZipArchive();

        if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Could not create zip');
        }

        $files = Storage::disk('digitalocean')->allFiles($folder);

        foreach ($files as $file) {
            $content = Storage::disk('digitalocean')->get($file);
            $relativePath = str_replace($folder, '', $file);
            $zip->addFromString($relativePath, $content);
        }

        $zip->close();
        //dd(file_exists($zipName), $zipName);
        return response()
            ->download($zipName)
            ->deleteFileAfterSend(true);
    }
}