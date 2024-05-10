<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use function App\Helpers\is_mobile;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\File;

class oldDocumentTransfer extends Controller
{
   
    public function storeImagesFromRemoteUrl()
    { // not working code
        try {
            // Remote URL containing the images
            $remoteUrl = 'http://apps.triz.co.in/mmiserp/Products/cms/assets/logo';
    
            // Custom cURL options to specify the host and port
        $curlOptions = [
            CURLOPT_PROXY => 'http://apps.triz.co.in:80', // Set the host and port
            CURLOPT_PROXYTYPE => CURLPROXY_SOCKS5, // Specify the proxy type (optional)
        ];

        // Fetch the contents of the remote directory with custom cURL options
        $response = Http::withOptions(['curl' => $curlOptions])->get($remoteUrl);

    
            // Check if the request was successful
            if ($response->successful()) {
                // Get the body of the response
                $body = $response->body();
    
                // Extract image filenames from the body
                preg_match_all('/href="(.*?)"/', $body, $matches);
    
                // Loop through each filename and store the file
                foreach ($matches[1] as $filename) {
                    // Get the file contents
                    $fileContents = Http::get($remoteUrl . '/' . $filename)->body();
    
                    // Store the file to the desired location with its original name
                    $stored = Storage::disk('digitalocean')->put('public/student_document/' . $filename, $fileContents);
    
                    if ($stored) {
                        echo "File '$filename' has been stored successfully.<br>";
                    } else {
                        echo "Failed to store file '$filename'.<br>";
                    }
                }
            } else {
                echo "Failed to fetch remote directory contents. Status Code: " . $response->status() . "<br>";
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "<br>";
        }
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
