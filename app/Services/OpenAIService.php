<?php
namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options; 
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Support\Facades\Session; 

class OpenAIService
{
    protected $client;
    protected $apiKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = env('OPENAI_API_KEY'); 
    }

    public function generateTitleAndDescription($topicName, $chapterName, $subjectName)
    {   
        $prompt = "Generate a title and description for a topic named '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}'.";

        try {
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 150, 
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            $generatedText = $data['choices'][0]['message']['content'];

            // Split the generated text into title and description
            list($title, $description) = explode("\n", $generatedText, 2);

            return [
                'title' => trim($title),
                'description' => trim($description),
            ];
        } catch (RequestException $e) {
            Log::error('OpenAI API Error: ' . $e->getMessage());
            return [
                'title' => 'Error generating title',
                'description' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    public function generateLessonPlan($topicName, $chapterName, $subjectName, $contentCategory,$contentType,$booklistData)
    {   
        $linksString = implode("\n", $booklistData);
        $pdfFilePathNew = storage_path('app/public/pdfs/iess401.pdf'); 
        if(file_exists($pdfFilePathNew)){
            $pdfContent = file_get_contents($pdfFilePathNew);
            $pdfBase64 = base64_encode($pdfContent);
        }else{
            $pdfBase64 = '';
        }
        if($contentCategory == 'Worksheet' && $topicName != '1. VIDEOS'){
            $prompt = "Create a detailed worksheet for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                      "Please refer to the following resources for more information:\n{$linksString}\n" .
                      "The response should be detailed enough and no. of questions should be minimum 50,focusing on all genres of questions like long,short,fill in the blanks and mcqs with answers.  Please include examples, explanations, and any relevant information.\n".
                      "Strictly avoid any personal replies or apologies. Only provide the main content.";
            Log::info('Prompt: ' . $prompt);    
        } else if($topicName == '1. VIDEOS' && $contentCategory != 'Worksheet'){
            $prompt = "Create a detailed '{$contentCategory}' for the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                       "Please refer to the following resources for more information:\n{$linksString}\n" .
                       "The response should be detailed enough to generate a PDF of at least 5 pages,focusing striclty on the basis of ncert curriculum of '{$chapterName}' and detailed structuredwise '{$contentCategory}'and the minimum words should be 1000 and Please include examples, explanations, and any relevant information.\n".
                       "Strictly avoid any personal replies or apologies. Only provide the main content.";
            Log::info('Prompt: ' . $prompt);  
        } else if($contentCategory == 'Worksheet' && $topicName == '1. VIDEOS'){
            $prompt = "Create a detailed worksheet for the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                      "Please refer to the following resources for more information:\n{$linksString}\n" .
                      "The response should be detailed enough and no. of questions should be minimum 50,focusing on all genres of questions like long,short,fill in the blanks and mcqs with answers .  Please include examples, explanations, and any relevant information.\n".
                      "Strictly avoid any personal replies or apologies. Only provide the main content.";    
            Log::info('Prompt: ' . $prompt); 
        } else{
            $prompt = "Create a detailed '{$contentCategory}' for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                      "Please refer to the following resources for more information:\n{$linksString}\n" .
                      "The response should be detailed enough to generate a PDF of at least 5 pages,focusing striclty on the basis of ncert curriculum of '{$chapterName}' and detailed structuredwise '{$contentCategory}' and the minimum words should be 1000 and Please include examples, explanations, and any relevant information.\n".
                      "Strictly avoid any personal replies or apologies. Only provide the main content.\n";
            Log::info('Prompt: ' . $prompt);    
        }
        try {
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 4096,
                    'temperature' => 1.8,
                    'top_p' => 0.5,
                ],
            ]);
            
            $data = json_decode($response->getBody(), true);
            $generatedText = $data['choices'][0]['message']['content'];
            $formattedText = nl2br($generatedText);

            if ($contentType === 'pdf') {
                $filePath = $this->createPDF($formattedText);
            } elseif ($contentType === 'jpg') {
                $filePath = $this->createJPG($formattedText);
            } else {
                throw new \Exception('Unsupported content category');
            }

            $fileName = basename($filePath);
            $fileUrl = url('storage/pdfs/' . $fileName);
            return $fileUrl;

        } catch (RequestException $e) {
            Log::error('OpenAI API Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate lesson plan.'], 500);
        } catch (\Exception $e) {
            Log::error('Error generating Data: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
        /* else if($contentType ==='jpg'){
            $prompt = "Create a detailed '{$contentCategory}' for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}' in jpg format.";
            try{
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'image-alpha-001', 
                    'prompt' => $prompt,
                    'num_images' => 1,
                    'size' => '1024x1024',
                ],
            ]);
            $data = json_decode($response->getBody(), true);
            Log::info('OpenAI API Response: ' . json_encode($data));
            if (isset($data['data'][0]['url'])) {
                $imageUrl = $data['data'][0]['url'];
                $imageContent = file_get_contents($imageUrl);
                $fileName = time() . '.jpg';
                $jpgFilePath = storage_path('app/public/pdfs/' . $fileName);
                file_put_contents($jpgFilePath, $imageContent);

                Log::info('JPG File Path: ' . $jpgFilePath);

                $fileName = basename($jpgFilePath);
                $fileUrl = url('storage/pdfs/' . $fileName);
                return $fileUrl;
            } else {
                throw new \Exception('Image URL not found in response.');
            }
        } catch (RequestException $e) {
            Log::error('OpenAI API Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate jpg.'], 500);
        } catch (\Exception $e) {
            Log::error('Error generating Data: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);

        }

    }*/
    }
    protected function createPDF($content)
    {
        $options = new Options();
        $options->set('defaultFont', 'Comic Sans MS'); 
        $dompdf = new Dompdf($options);

        $fontPath = storage_path('fonts/Comic Sans MS.ttf');

        if (!file_exists($fontPath)) {
            throw new \Exception('Font file not found: ' . $fontPath);
        }
    
        $dompdf->getOptions()->set('isHtml5ParserEnabled', true);
        $dompdf->getOptions()->set('isFontSubsettingEnabled', true);
        $dompdf->getOptions()->set('isRemoteEnabled', true);

        $htmlContent = "
            <html>
                <head>
                    <style>
                    @font-face {
                        font-family: 'Comic Sans MS';
                        src: url('{$fontPath}') format('truetype');
                    }
                    body {
                        font-family: 'Comic Sans MS', sans-serif;
                    }
                    </style>
                </head>
                <body>
                    {$content}
                </body>
            </html>
        ";

        $dompdf->loadHtml($htmlContent);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $fileName = time() . '.pdf';
        $pdfFilePath = storage_path('app/public/pdfs/' . $fileName);
        Log::info('PDF File Path: ' . $pdfFilePath);

        file_put_contents($pdfFilePath, $dompdf->output());

        return $pdfFilePath;
    }

    protected function createJPG($content)
    {
        $image = Image::canvas(800, 600, '#ffffff');
    $fontPath = storage_path('fonts/ComicSansMS.ttf');
    if (file_exists($fontPath)) {
        $image->text($content, 400, 300, function($font) use ($fontPath) {
            $font->file($fontPath);
            $font->size(24);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });
    } else {
        $image->text($content, 400, 300, function($font) {
            $font->file(1); 
            $font->size(24);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });
    }

    $fileName = time() . '.jpg';
    $jpgFilePath = storage_path('app/public/pdfs/' . $fileName);
    Log::info('JPG File Path: ' . $jpgFilePath);

    $image->save($jpgFilePath);

    return $jpgFilePath;
    }
    public function handleUserInput($input)
    {
        $state = Session::get('state', 'initial');

        switch ($state) {
            case 'initial':
                return $this->handleInitialState($input);
            case 'fees':
                return $this->handleFeesState($input);
            case 'attendance':
                return $this->handleAttendanceState($input);
            default:
                return $this->handleInitialState($input);
        }
    }

    protected function handleInitialState($input)
    {
        if (stripos($input, 'fees') !== false) {
            Session::put('state', 'fees');
            return "What issues regarding fees would you like to discuss? (e.g., pending fees)";
        } elseif (stripos($input, 'attendance') !== false) {
            Session::put('state', 'attendance');
            return "Please provide your unique student ID to display attendance.";
        } elseif (stripos($input, 'hello') !== false || stripos($input, 'hi') !== false) {
            return "Hello! How can I assist you today?";
        }
        else {
            return "I'm sorry, I didn't understand that. Can you please specify if you need help with fees or attendance?";
        }
    }

    protected function handleFeesState($input)
    {
        // Handle fees-related queries
        if (stripos($input, 'pending') !== false) {
            // Fetch pending fees from the database (example)
            $pendingFees = $this->getPendingFees();
            Session::put('state', 'initial');
            return "Your pending fees are: $pendingFees";
        } else {
            Session::put('state', 'initial');
            return "I'm sorry, I didn't understand that. Please specify if you need help with pending fees.";
        }
    }

    protected function handleAttendanceState($input)
    {
        // Handle attendance-related queries
        $studentId = trim($input);
        $attendance = $this->getAttendance($studentId);
        Session::put('state', 'initial');
        return "Your attendance is: $attendance";
    }

    protected function getPendingFees()
    {
        // Fetch pending fees from the database (example)
        return "1990 Rs";
    }

    protected function getAttendance($studentId)
    {
        // Fetch attendance from the database (example)
        return "85%";
    }
}   