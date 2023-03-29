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
        $CKEditor = $request->input('CKEditor');
        $funcNum = $request->input('CKEditorFuncNum');
        $message = $url = '';
       
        if ($request->hasFile('upload')) {
            $file = $request->file('upload');
            if ($file->isValid()) {
                $filename = rand(1000, 9999).$file->getClientOriginalName();
                $file->move(public_path().'/lms_editor_upload/', $filename);
                $url = url('lms_editor_upload/'.$filename);
            } else {
                $message = 'An error occurred while uploading the file.';
            }
        } else {
            $message = 'No file uploaded.';
        }

        return '<script>window.parent.CKEDITOR.tools.callFunction('.$funcNum.', "'.$url.'", "'.$message.'")</script>';
    }
}
