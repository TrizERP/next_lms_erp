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
use Illuminate\Support\Facades\Log;
use Dompdf\Dompdf;


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

    public function ConvertBinaryData(Request $request)
    {
        // $filePath = $_SERVER['DOCUMENT_ROOT'].'converted_json.json';

        // // Check if the file exists
        //   if (!file_exists($filePath)) {
        //     return response()->json(['error' => 'File not found'], 404);
        // }

        // // Read the file content
        // $jsonData = file_get_contents($filePath);

        // if (json_last_error() !== JSON_ERROR_NONE) {
        //     return response()->json(['error' => 'JSON decoding error: ' . json_last_error_msg()], 500);
        // }

        // // Return the data as a JSON response
        // $dataArray = json_decode($jsonData, true);
      
        // $storedFiles=[];
        // $message='Please wait we are proccessing!';
        // foreach ($dataArray as $value) {
        //     $fileSize=$value['size'];

        //     if($fileSize==0){
        //         $blobData = isset($value['mediumBlob']) ? $value['mediumBlob'] : null;
        //     }else{
        //         $blobData = isset($value['mediumBlob']) ? base64_decode($value['mediumBlob'],true) : null;
        //     }
        //     // echo "<pre>";print_r($blobData);exit;
        //     $empId=$value['empId'];
        //     $filename='emp_'.$empId.'_'.$value['fileName'];
           
        //     if ($blobData !== false) {
        //         // Generate filename and path
        //         $empId = $value['empId'];
        //         $docId = $value['docId'];
        //         $docTitle = $value['docTitle'];
        //         $sub_institute_id = $value['sub_institute_id'];

        //         $filename = 'emp_' . $empId . '_' . $value['fileName'];

        //         $tempFilePath = tempnam(sys_get_temp_dir(), 'pdf');
        //         file_put_contents($tempFilePath, $blobData);
            
        //         // Step 3: Upload to DigitalOcean Spaces
        //         $storagePath = 'public/staff_document/' . $filename;
        //         $storedFiles[] = $storagePath;
        //         $disk = Storage::disk('digitalocean');
        //         if (Storage::disk('digitalocean')->exists($storagePath)) {
        //             Storage::disk('digitalocean')->delete($storagePath);
        //             // Store the file
        //         } 

        //         if($fileSize!=0){
        //             $disk->put($storagePath, fopen($tempFilePath, 'r+'), 'public');
        //         }else{
        //             // $fileContents = file_get_contents($blobData);

        //             $fileContents = @file_get_contents($value['mediumBlob']); // The '@' suppresses warnings

        //             if ($fileContents !== false) {
        //                 // Store the file in DigitalOcean Spaces
        //                $disk=Storage::disk('digitalocean')->put($storagePath, $fileContents, 'public');
        //             }
        //         }
        //         // Clean up temporary file
        //         unlink($tempFilePath);
        //         // store in database
        //         $checkData = DB::table('staff_document')->where('user_id',$empId)->where('file_name',$filename)->where('document_type_id',$docId)->get()->toArray();
        //         if($disk && empty($checkData)){
        //             $insertData = [
        //                 "user_id"=>$empId,
        //                 "document_type_id"=>$docId,
        //                 "document_title"=>$docTitle,
        //                 "file_name"=>$filename,
        //                 "sub_institute_id"=>$sub_institute_id,
        //                 "created_at"=>now(),

        //             ];
        //             // echo "<pre>";print_r($insertData);exit;
        //             $insert = DB::table('staff_document')->insert($insertData);
        //             $message='Stored Files';
        //         }
        //         $message = 'File stored successfully!';
        //     }else{
        //         $message = 'Blob Data not found!';
        //     }
        // }

        // $filePath = 'old_to_new/doc_transfer/converted_json.json';
        $filePath = $_SERVER['DOCUMENT_ROOT'].'converted_json.json';

        // Check if the file exists
          if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        // Read the file content
        $jsonData = file_get_contents($filePath);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['error' => 'JSON decoding error: ' . json_last_error_msg()], 500);
        }

        // Return the data as a JSON response
        $dataArray = json_decode($jsonData, true);
      
        $storedFiles=[];
        $message='Please wait we are proccessing!';
        foreach ($dataArray as $value) {
            $fileSize=$value['size'];

            if($fileSize==0){
                $blobData = isset($value['mediumBlob']) ? $value['mediumBlob'] : null;
            }else{
                $blobData = isset($value['mediumBlob']) ? base64_decode($value['mediumBlob'],true) : null;
            }
            // echo "<pre>";print_r($blobData);exit;
            $empId=$value['studentId'];
            $docId=$value['docId']; 
            $filename='studentId'.$empId.'_'.$value['fileName'];
            // return response($blobData, 200)
            // ->header('Content-Type', 'application/pdf')
            // ->header('Content-Disposition', 'inline; filename="document.pdf"');
            if ($blobData !== false) {
                // Generate filename and path
                $empId = $value['studentId'];
                $sub_institute_id = $value['sub_institute_id'];
                $docTitle = $value['docTitle'];
                $sub_institute_id = $value['sub_institute_id'];

                $filename = $value['fileName'];

                $tempFilePath = tempnam(sys_get_temp_dir(), 'pdf');
                file_put_contents($tempFilePath, $blobData);
            
                // Step 3: Upload to DigitalOcean Spaces
                $storagePath = 'public/student_document/' . $filename;
                $storedFiles[] = $storagePath;
                $disk = Storage::disk('digitalocean');
                if (Storage::disk('digitalocean')->exists($storagePath)) {
                    Storage::disk('digitalocean')->delete($storagePath);
                    // Store the file
                } 

                if($fileSize!=0){
                    $disk->put($storagePath, fopen($tempFilePath, 'r+'), 'public');
                }else{
                    // $fileContents = file_get_contents($blobData);

                    $fileContents = @file_get_contents($value['mediumBlob']); // The '@' suppresses warnings

                    if ($fileContents !== false) {
                        // Store the file in DigitalOcean Spaces 20383
                       $disk=Storage::disk('digitalocean')->put($storagePath, $fileContents, 'public');
                    }
                }
                // Clean up temporary file
                unlink($tempFilePath);
                // store in database
                $checkData = DB::table('tblstudent_document')->where('student_id',$empId)->where('file_name',$filename)->where('document_type_id',$docId)->where('sub_institute_id',$sub_institute_id)->get()->toArray();
                if($disk && empty($checkData)){
                    $insertData = [
                        "student_id"=>$empId,
                        "document_type_id"=>$docId,
                        "document_title"=>$docTitle,
                        "file_name"=>$filename,
                        "sub_institute_id"=>$sub_institute_id,
                        "created_on"=>now(),
                    ];
                    // echo "<pre>";print_r($insertData);exit;
                    $insert = DB::table('tblstudent_document')->insert($insertData);
                    $message='Stored Files';
                }
                $message = 'File stored successfully!';
            }else{
                $message = 'Blob Data not found!';
            }
        }
        // exit;

        return response()->json(['message' =>$message,'Total stored files'=>count($storedFiles), 'storedFiles' => $storedFiles]);
    }

}
