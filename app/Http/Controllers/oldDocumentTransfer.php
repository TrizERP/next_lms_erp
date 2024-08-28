<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;
use DB;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;

class oldDocumentTransfer extends Controller
{
    private function convertDataToUtf8($data)
    {
        if (is_array($data)) {
            // Convert each element of the array to UTF-8 encoding
            foreach ($data as $key => $value) {
                $data[$key] = $this->convertDataToUtf8($value);
            }
        } elseif (is_object($data)) {
            // Convert each property of the object to UTF-8 encoding
            foreach ($data as $key => $value) {
                $data->$key = $this->convertDataToUtf8($value);
            }
        } elseif (is_string($data)) {
            // Attempt to detect the character encoding
            $encoding = mb_detect_encoding($data, mb_detect_order(), true);
            
            // Specify the input encoding (use ISO-8859-1 as a fallback)
            $inputEncoding = $encoding ?: 'ISO-8859-1';
            
            // Convert string to UTF-8 encoding
            $data = mb_convert_encoding($data, 'UTF-8', $inputEncoding);
        }
        
        return $data;
    }
    


    public function storeImagesToDigitalOcean(Request $request)
    {
        // $directory = public_path('images');
        if($request->type=="storage"){
            $directory = storage_path('app/public/'.$request->directory);
        }else{
            $directory = public_path($request->directory);
        }
        // $directory = $request->directory;
        $i=0;

        if (File::exists($directory)) {
            $files = File::files($directory);
            foreach ($files as $file) {
                $filename = $file->getFilename();
                $filePath = $file->getPathname();
                $stored = Storage::disk('digitalocean')->putFileAs('public/'.$request->digi_directory.'/', $filePath, $filename, 'public');
                if ($stored) {
                    $i++;
                }
            }
        } else {
            echo "Directory does not exist.";
        }

        $message = "Failed";
        if($i>0){
            $message="stored";
        }
        return $message;
    }

}
