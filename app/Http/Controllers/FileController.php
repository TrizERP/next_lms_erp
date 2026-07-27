<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function downloadBulkDocuments(Request $request)
    {
        @set_time_limit(0);

        $grade_id = $request->input('grade');
        $standard_id = $request->input('standard');
        $division_id = $request->input('division');
        $user_type = $request->input('user_type', 'student');

        $sub_institute_id = $request->session()->get('sub_institute_id');
        $syear = $request->session()->get('syear');

        if ($user_type == 'student') {
            $documents = DB::table('tblstudent_document as sd')
                ->join('tblstudent as s', 's.id', '=', 'sd.student_id')
                ->join('tblstudent_enrollment as se', 'se.student_id', '=', 's.id')
                ->selectRaw("s.enrollment_no as folder_name, s.id as owner_id, sd.file_name, sd.document_title")
                ->where('sd.sub_institute_id', $sub_institute_id)
                ->where('se.syear', $syear)
                ->when($grade_id != '', fn ($q) => $q->where('se.grade_id', $grade_id))
                ->when($standard_id != '', fn ($q) => $q->where('se.standard_id', $standard_id))
                ->when($division_id != '', fn ($q) => $q->where('se.section_id', $division_id))
                ->distinct()
                ->get();
        } else {
            $documents = DB::table('staff_document as sd')
                ->join('tbluser as s', 's.id', '=', 'sd.user_id')
                ->selectRaw("CONCAT_WS('_',s.first_name,s.last_name) as folder_name, s.id as owner_id, sd.file_name, sd.document_title")
                ->where('sd.sub_institute_id', $sub_institute_id)
                ->distinct()
                ->get();
        }

        if ($documents->isEmpty()) {
            return $this->backWithMessage('No documents found for the selected criteria.');
        }

        $tempDir = storage_path('app/temp_bulk_downloads');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $downloadName = ($user_type == 'student' ? 'Student' : 'Staff') . '_Documents';
        $zipName = $tempDir . '/' . $downloadName . '_' . time() . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->backWithMessage('Could not create zip file.');
        }

        $folderPath = $user_type == 'student' ? 'student_document' : 'staff_document';
        $addedFiles = 0;
        $usedNames = [];

        foreach ($documents as $doc) {
            if (empty($doc->file_name)) {
                continue;
            }

            $filePath = 'public/' . $folderPath . '/' . $doc->file_name;

            try {
                if (!Storage::disk('digitalocean')->exists($filePath)) {
                    \Log::info('Document not found in DigitalOcean Spaces: ' . $filePath);
                    continue;
                }

                $fileContent = Storage::disk('digitalocean')->get($filePath);

                $folder = !empty($doc->folder_name)
                    ? $this->sanitizeName($doc->folder_name)
                    : 'Unknown_' . $doc->owner_id;

                $title = !empty($doc->document_title) ? $this->sanitizeName($doc->document_title) . '_' : '';
                $fileNameInZip = $folder . '/' . $title . $this->sanitizeName($doc->file_name);

                // two documents can share a file name inside the same folder
                if (isset($usedNames[$fileNameInZip])) {
                    $usedNames[$fileNameInZip]++;
                    $ext = pathinfo($fileNameInZip, PATHINFO_EXTENSION);
                    $base = $ext !== '' ? substr($fileNameInZip, 0, -(strlen($ext) + 1)) : $fileNameInZip;
                    $fileNameInZip = $base . '_' . $usedNames[$fileNameInZip] . ($ext !== '' ? '.' . $ext : '');
                } else {
                    $usedNames[$fileNameInZip] = 1;
                }

                $zip->addFromString($fileNameInZip, $fileContent);
                $addedFiles++;
            } catch (\Exception $e) {
                \Log::warning('Failed to add document to zip: ' . $filePath . ' - ' . $e->getMessage());
                continue;
            }
        }

        $zip->close();

        // an empty archive is never written to disk, so download() would explode
        if ($addedFiles === 0 || !file_exists($zipName)) {
            @unlink($zipName);
            return $this->backWithMessage('No document files could be found in storage for the selected criteria.');
        }

        return response()
            ->download($zipName, $downloadName . '.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend(true);
    }

    /**
     * Flash a message in the shape the report blade expects and go back.
     */
    private function backWithMessage($message, $statusCode = 0)
    {
        return back()->with('data', [
            'status_code' => $statusCode,
            'message' => $message,
        ]);
    }

    private function sanitizeName($name)
    {
        return trim(preg_replace('/[\/\\\\:*?"<>|]+/', '_', $name));
    }
}