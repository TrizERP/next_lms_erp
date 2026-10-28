<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CkeditorFileUploadController extends Controller
{

    public function index(Request $request)
    {
        //
    }

    public function create()
    {
        return view('editor');
    }

    public function store(Request $request)
    {
        // The callback number goes into a <script> block, so it must be a plain integer.
        $funcNum = (int) $request->input('CKEditorFuncNum');
        $message = $url = '';
       
        if ($request->hasFile('upload')) {
            $file = $request->file('upload');
            $extension = strtolower((string) $file->getClientOriginalExtension());
            if (! $file->isValid()) {
                $message = 'An error occurred while uploading the file.';
            } elseif (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)
                || ! str_starts_with((string) $file->getMimeType(), 'image/')) {
                // The editor only inserts pictures; anything else is not stored under public/.
                $message = 'Only image files can be uploaded.';
            } else {
                // A server-chosen name: the client's own name and extension are not trusted.
                $filename = bin2hex(random_bytes(8)).'.'.$extension;
                $file->move(public_path().'/lms_editor_upload/', $filename);
                $url = url('lms_editor_upload/'.$filename);
            }
        } else {
            $message = 'No file uploaded.';
        }

        // json_encode with the HEX flags makes the url and message inert inside the script tag.
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        return '<script>window.parent.CKEDITOR.tools.callFunction('.$funcNum.', '.json_encode($url, $flags).', '.json_encode($message, $flags).')</script>';
    }
}
