<?php
namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options; 
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as Image;
use Illuminate\Support\Facades\Session; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\fees\fees_collect\fees_collect_controller;
use OpenAI;

set_time_limit(200);

class OpenAIService
{
    protected $client;
    protected $apiKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = env('OPENAI_API_KEY');
        $this->apiKey_deepseek = env('OPENROUTER_API_KEY');
    }

    public function generateTitleAndDescription($topicName, $chapterName, $subjectName,$standard_name)
    {   
        $prompt = "Generate a title and description for a topic named '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}' in standard '{$standard_name}'. \n";
        
        try {
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'verify' => false,
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
    
    public function generateLessonPlan($topicName, $chapterName, $subjectName, $contentCategory, $contentType, $booklistData,$curriculum_alignment,$holistic_curriculum,$objective,$assessment_tool,$objective_one,$learning_outcomes,$suggested_materials,$assessment_plan,$standard_name) {   
        if($booklistData){
            $linksString = implode("\n", $booklistData);
        } else{
            $linksString = " ";
        }
        $pdfFilePathNew = storage_path('app/public/pdfs/iess401.pdf'); 
        if(file_exists($pdfFilePathNew)){
            $pdfContent = file_get_contents($pdfFilePathNew);
            $pdfBase64 = base64_encode($pdfContent);
        } else {
            $pdfBase64 = '';
        }
        
        // Prepare the prompt based on category
        if($contentCategory == 'Worksheet' && $topicName != '1. VIDEOS'){
            $prompt = "Create a detailed '{$contentCategory}' for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}' in standard '{$standard_name}'.\n" .
                     "<strong>Curriculum Alignment:</strong>\n {$curriculum_alignment}\n" .
                    "<strong>Holistic Curriculum:</strong>\n {$holistic_curriculum}\n" .
                    "<strong>Objective:</strong>\n {$objective}\n" .
                    "<strong>Assessment Tool:</strong>\n {$assessment_tool}\n".  
                    "<strong>Syllabus:</strong>\n\n".
                    "<strong>Objectives:</strong>\n {$objective_one}\n".
                    "<strong>Learning Outcomes:</strong>\n {$learning_outcomes}\n".
                    "<strong>Suggested Materials:</strong>\n {$suggested_materials}\n".
                    "<strong>Assessment Plan:</strong>\n {$assessment_plan}\n".
                     "Please refer to the following resources for more information:\n{$linksString}\n" .
                       "The response should be detailed enough and no. of questions should be minimum 50, focusing on all genres of questions like long, short, fill in the blanks, and MCQs with answers. Please include examples, explanations, and any relevant information.\n" .
                       "Strictly avoid any personal replies or apologies. Only provide the main content.";
            Log::info('Prompt: ' . $prompt);    
        } else if($topicName == '1. VIDEOS' && $contentCategory != 'Worksheet'){
            $prompt = "Create a detailed '{$contentCategory}' for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}' in standard '{$standard_name}'.\n" .
                    "<strong>Curriculum Alignment:</strong>\n {$curriculum_alignment}\n" .
                    "<strong>Holistic Curriculum:</strong>\n {$holistic_curriculum}\n" .
                    "<strong>Objective:</strong>\n {$objective}\n" .
                    "<strong>Assessment Tool:</strong>\n {$assessment_tool}\n".
                    "<strong>Syllabus:</strong>\n\n".
                    "<strong>Objectives:</strong>\n {$objective_one}\n".
                    "<strong>Learning Outcomes:</strong>\n {$learning_outcomes}\n".
                    "<strong>Suggested Materials:</strong>\n {$suggested_materials}\n".
                    "<strong>Assessment Plan:</strong>\n {$assessment_plan}\n".
                       "Please refer to the following resources for more information:\n{$linksString}\n" .
                       "The response should be detailed enough to generate a PDF of at least 5 pages, focusing strictly on the basis of NCERT curriculum of '{$chapterName}' and structured '{$contentCategory}'. Minimum words should be 1000. Please include examples, explanations, and any relevant information.\n" .
                       "Strictly avoid any personal replies or apologies. Only provide the main content.";
            Log::info('Prompt: ' . $prompt);  
        } else if($contentCategory == 'Worksheet' && $topicName == '1. VIDEOS'){
            $prompt = "Create a detailed '{$contentCategory}' for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}' in standard '{$standard_name}'.\n" .
                      "<strong>Curriculum Alignment:</strong>\n {$curriculum_alignment}\n" .
                    "<strong>Holistic Curriculum:</strong>\n {$holistic_curriculum}\n" .
                    "<strong>Objective:</strong>\n {$objective}\n" .
                    "<strong>Assessment Tool:</strong>\n {$assessment_tool}\n".
                      "<strong>Syllabus:</strong>\n\n".
                    "<strong>Objectives:</strong>\n {$objective_one}\n".
                    "<strong>Learning Outcomes:</strong>\n {$learning_outcomes}\n".
                    "<strong>Suggested Materials:</strong>\n {$suggested_materials}\n".
                    "<strong>Assessment Plan:</strong>\n {$assessment_plan}\n".
                      "Please refer to the following resources for more information:\n{$linksString}\n" .
                      "The response should be detailed enough and no. of questions should be minimum 50, focusing on all genres of questions like long, short, fill in the blanks, and MCQs with answers. Please include examples, explanations, and any relevant information.\n" .
                      "Strictly avoid any personal replies or apologies. Only provide the main content.";    
            Log::info('Prompt: ' . $prompt); 
        } else {
            $prompt = "Create a detailed '{$contentCategory}' for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}' in standard '{$standard_name}'.\n" .
                    "<strong>Curriculum Alignment:</strong>\n {$curriculum_alignment}\n" .
                    "<strong>Holistic Curriculum:</strong>\n {$holistic_curriculum}\n" .
                    "<strong>Objective:</strong>\n {$objective}\n" .
                    "<strong>Assessment Tool:</strong>\n {$assessment_tool}\n".
                    "<strong>Syllabus:</strong>\n\n".
                    "<strong>Objectives:</strong>\n {$objective_one}\n".
                    "<strong>Learning Outcomes:</strong>\n {$learning_outcomes}\n".
                    "<strong>Suggested Materials:</strong>\n {$suggested_materials}\n".
                    "<strong>Assessment Plan:</strong>\n {$assessment_plan}\n".
                    "Please refer to the following resources for more information:<br>{$linksString}\n" .
                    "The response should be detailed enough to generate a PDF of at least 5 pages, focusing strictly on the NCERT curriculum of '{$chapterName}' and structured '{$contentCategory}'. Minimum words should be 1000. Please include examples, explanations, and any relevant information.\n" .
                    "Strictly avoid any personal replies or apologies. Only provide the main content." ;
            Log::info('Prompt: ' . $prompt);    
        }

        try {
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'verify' => false,
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
            
            $imageUrls=[];
            for ($i = 0; $i < 5 ; $i++) {
                $generatedImages = $this->generateImage($topicName, $chapterName, $subjectName, $contentCategory, $contentType, $booklistData);
                
                // Check if the generated images are not null and are an array
                if (is_array($generatedImages) && !empty($generatedImages)) {
                    // Append the generated image URLs to the $imageUrls array
                    $imageUrls = array_merge($imageUrls, $generatedImages);
                    
                    // Log the generated image URLs
                    foreach ($generatedImages as $url) {
                        Log::info("Image in GLP: $url");
                    }
                } else {
                    // Log::warning("No images generated for iteration $i.");
                }
            }
            
            if ($contentType === 'pdf') {
                $images = $imageUrls; // Use the accumulated image URLs
                $filePath = $this->createPDF($formattedText, $images,$topicName, $chapterName, $subjectName, $contentCategory, $contentType);
            } elseif ($contentType === 'jpg') {
                $filePath = $this->createJPG($formattedText);
            } else {
                throw new \Exception('Unsupported content category');
            }

            $fileName = basename($filePath);
            $fileUrl = url('storage/pdfs/' . $fileName);
            return [
                'prompt' => $prompt,
                'fileUrl' => $fileUrl,
            ];
        } catch (RequestException $e) {
            Log::error('OpenAI API Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate lesson plan.'], 500);
        } catch (\Exception $e) {
            Log::error('Error generating Data: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    public function generateLessonPlanNew($topicName, $chapterName, $subjectName, $contentCategory, $contentType, $booklistData, $prompt) {   
        Log::info('Updated Prompt: ' . $prompt);    
        if($booklistData){
            $linksString = implode("\n", $booklistData);
        } else{
            $linksString = " ";
        }
        
        try {
            // Call GPT-3.5 API to generate text
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'verify' => false,
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
            
            $imageUrls=[];
            for ($i = 0; $i < 2 ; $i++) {
                $generatedImages = $this->generateImage($topicName, $chapterName, $subjectName, $contentCategory, $contentType, $booklistData);
                
                // Check if the generated images are not null and are an array
                if (is_array($generatedImages) && !empty($generatedImages)) {
                    // Append the generated image URLs to the $imageUrls array
                    $imageUrls = array_merge($imageUrls, $generatedImages);
                    
                    // Log the generated image URLs
                    foreach ($generatedImages as $url) {
                        Log::info("Image in GLP: $url");
                    }
                } else {
                    // Log::warning("No images generated for iteration $i.");
                }
            }
            
            if ($contentType === 'pdf') {
                $images = $imageUrls; // Use the accumulated image URLs
                $filePath = $this->createPDF($formattedText, $images,$topicName, $chapterName, $subjectName, $contentCategory, $contentType);
            } elseif ($contentType === 'jpg') {
                $filePath = $this->createJPG($formattedText);
            } else {
                throw new \Exception('Unsupported content category');
            }
        
            $fileName = basename($filePath);
            $fileUrl = url('storage/pdfs/' . $fileName);
            return [
                'prompt' => $prompt,
                'fileUrl' => $fileUrl,
            ];
        } catch (RequestException $e) {
            Log::error('OpenAI API Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate lesson plan.'], 500);
        } catch (\Exception $e) {
            Log::error('Error generating Data: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }
    
    public function generateImage($topicName, $chapterName, $subjectName, $contentCategory, $contentType, $booklistData)
    {
        if($booklistData){
            $linksString = implode("\n", $booklistData);
        } else{
            $linksString = " ";
        }
        $prompt = "Generate a image in a very realistic approach for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                  "Please refer to the following resources for more information:\n{$linksString}\n" .
                  "Strictly avoid any personal replies or apologies; and content should be on strictly Indian context with proper english and avoid incorrect spellings or ununderstood text ;Only provide the main content.\n";
    }
    
    public function generateSportsData($topicName, $chapterName, $subjectName, $contentCategory,$contentType)
    {   
        if($contentCategory == 'Worksheet' && $topicName != '1. VIDEOS'){
            $prompt = "Create a detailed worksheet for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                      "The response should be detailed enough and no. of questions should be minimum 50,focusing on all genres of questions like long,short,fill in the blanks and mcqs with answers.  Please include examples, explanations, and any relevant information.\n".
                      "Strictly avoid any personal replies or apologies. Only provide the main content.";
            Log::info('Prompt: ' . $prompt);    
        } else if($topicName == '1. VIDEOS' && $contentCategory != 'Worksheet'){
            $prompt = "Create a detailed '{$contentCategory}' for the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                       "The response should be detailed enough to generate a PDF of at least 5 pages,focusing striclty on the basis of ncert curriculum of '{$chapterName}' and detailed structuredwise '{$contentCategory}'and the minimum words should be 1000 and Please include examples, explanations, and any relevant information.\n".
                       "Strictly avoid any personal replies or apologies. Only provide the main content.";
            Log::info('Prompt: ' . $prompt);  
        } else if($contentCategory == 'Worksheet' && $topicName == '1. VIDEOS'){
            $prompt = "Create a detailed worksheet for the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                      "The response should be detailed enough and no. of questions should be minimum 50,focusing on all genres of questions like long,short,fill in the blanks and mcqs with answers .  Please include examples, explanations, and any relevant information.\n".
                      "Strictly avoid any personal replies or apologies. Only provide the main content.";    
            Log::info('Prompt: ' . $prompt); 
        } else{
            $prompt = "Create a detailed '{$contentCategory}' for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                      "The response should be detailed enough to generate a PDF of at least 5 pages,focusing striclty on the basis of ncert curriculum of '{$chapterName}' and detailed structuredwise '{$contentCategory}' and the minimum words should be 1000 and Please include examples, explanations, and any relevant information.\n".
                      "Strictly avoid any personal replies or apologies. Only provide the main content.\n";
            Log::info('Prompt: ' . $prompt);    
        }
        try {
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'verify' => false,
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
                $filePath = $this->createPDFNEW($formattedText);
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
    }
    
    protected function createPDF($content, $imagePaths = [],$topicName, $chapterName, $subjectName, $contentCategory, $contentType)
    {
        set_time_limit(200);
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

        // Start the HTML content
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
        ";

        // Add the initial content only once
        $htmlContent .= "<div>{$content}</div><hr>";

        // Add images to the PDF with generated content on each page
        foreach ($imagePaths as $imagePath) {
            $htmlContent .= "<div>";

            // Generate more content
            $additionalContent = $this->generateMore($topicName, $chapterName, $subjectName, $contentCategory, $contentType); // Call the method to generate additional content
            $htmlContent .= "<div>{$additionalContent}</div> <hr>"; // Add generated content

            if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                $imageData = file_get_contents($imagePath);
                if ($imageData !== false) {
                    $base64 = base64_encode($imageData);
                    $src = 'data:image/' . pathinfo($imagePath, PATHINFO_EXTENSION) . ';base64,' . $base64;
                    $htmlContent .= "<img src='{$src}' style='max-width: 100%; height: auto;' />";
                } else {
                    Log::info("Failed to fetch image data: ");
                }
            } else {
                Log::info("Invalid image URL: ");
            }

            $htmlContent .= "</div>"; // Close the page div
        }

        $htmlContent .= "
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
    
    protected function createPDFNEW($content)
    {
        set_time_limit(200);
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

        // Start the HTML content
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
        ";

        // Add the initial content only once
        $htmlContent .= "<div>{$content}</div><hr>";

        $htmlContent .= "
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

    // Example of the generateMore method
    protected function generateMore($topicName, $chapterName, $subjectName, $contentCategory, $contentType)
    {
        $prompt="Generate more content on addition to a existing basic content that I already have in a detail manner for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                "Strictly avoid any personal replies or apologies. Only provide the main content.\n";
        try {
            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'verify' => false,
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
            return $formattedText;
        } catch (RequestException $e) {
            Log::error('OpenAI API Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate lesson plan.'], 500);
        } catch (\Exception $e) {
            Log::error('Error generating Data: ' . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
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
        $qaResponse = $this->checkQnAFile($input);
        if ($qaResponse) {
            $this->logConversation($input, $qaResponse);
            return $qaResponse;
        }

        $state = Session::get('state', 'initial');
        
        $this->trackKeyIssues($input);
        
        if ($state === 'feedback') {
            return $this->handleFeedback($input);
        }
        
        switch ($state) {
            case 'initial':
                if (stripos($input, 'fees') !== false) {
                    Session::put('state', 'fees');
                    return $this->handleFeesState($input);
                } elseif (stripos($input, 'attendance') !== false) {
                    Session::put('state', 'attendance');
                    return $this->handleAttendanceState($input);
                } elseif (stripos($input, 'grades') !== false) {
                    Session::put('state', 'grades');
                    return "Please provide your unique student ID to fetch your grades.";
                } else {
                    return $this->handleInitialState($input);
                }
                break;
            case 'fees':
                $botResponse = $this->handleFeesState($input);
                break;
            case 'pending_fees_grno':
                $grno = trim($input);
                $botResponse = $this->getPendingFees($grno);
                break;
            case 'attendance':
                $botResponse = $this->handleAttendanceState($input);
                break;
            case 'monthly_attendance':
                $grno = trim($input);
                $botResponse = $this->getAttendance($grno);
                break;
            case 'yearly_attendance':
                $grno = trim($input);
                $botResponse = $this->getYearlyAttendance($grno);
                break;    
            case 'grades':
                $botResponse = $this->handleGradesState($input);
                break;
            case 'AI':
                $botResponse = $this->handleDynamicResponse($input);
                $endPhrases = [
                    "Thank you",
                    "Are you satisfied with the response",
                    "May I further help you",
                    "Is there anything else I can assist you with?",
                    "Have a great day!",
                    "Feel free to ask if you have more questions!",
                    "Goodbye",
                    "Take care",
                    "You're welcome! If you have any more questions, feel free to ask.",
                    "You're welcome! If you have any more questions or need further assistance, feel free to ask. I'm here to help!"
                ];
                $state='initial';
                foreach ($endPhrases as $phrase) {
                    if (stripos($botResponse, $phrase) !== false) {
                        $state='AI';
                    }
                }
                break;
            default:
                $botResponse = $this->handleDynamicResponse($input);
                break;
        }

        $this->logConversation($input, $botResponse);
        
        if (in_array($state, ['pending_fees_grno', 'attendance', 'grades','AI','monthly_attendance','yearly_attendance'])) {
            Session::put('state', 'feedback'); 
            return $botResponse . "<br><br> \n\nAre you satisfied with the response? (Yes/No)";
        }

        return $botResponse;
    }

    protected function checkQnAFile($input)
    {
        $filePath = storage_path('app/QnA Database/qnadata.json');
        if (!file_exists($filePath)) {
            return null; 
        }

        $qaData = json_decode(file_get_contents($filePath), true);
        $matches = []; 

        foreach ($qaData as $qaPair) {
            $similarity = 0;
            similar_text($input, $qaPair['Chat Question'], $similarity);
            if ($similarity >= 90) {
                $matches[] = $qaPair['Answer']; 
            }
        }
        if (count($matches) > 1) {
            $listItems = array_map(function($answer) {
                return "<li>" . htmlspecialchars($answer) . "</li>"; 
            }, $matches);
            
            return "I found multiple answers:<br /><ul>" . implode('', $listItems) . "</ul>"; 
        } elseif (count($matches) === 1) {
            return $matches[0]; 
        }

        return null;
    }

    public function handleFeedback($input)
    {
        if (stripos($input, 'yes') !== false) {
            Session::put('state', 'initial'); 
            return "Thank you for your feedback! feel free to ask further questions!";
        } elseif (stripos($input, 'no') !== false) {
            Session::put('state', 'initial'); 
            return "Sorry to hear that. How can I further assist you?";
        } else {
            return "Please respond with 'Yes' or 'No'. Are you satisfied with the response?";
        }
    }

    protected function handleInitialState($input)
    {
        if (stripos($input, 'fees') !== false) {
            Session::put('state', 'fees');
            return $this->handleFeesState($input);
        } elseif (stripos($input, 'attendance') !== false) {
            Session::put('state', 'attendance');
            return "Please provide your unique student ID to display attendance.";
        } elseif (stripos($input, 'grades') !== false) {
            Session::put('state', 'grades');
            return "Please provide your unique student ID to fetch your grades.";
        }  else {
            Session::put('state', 'AI');
            return $this->handleDynamicResponse($input);
        }
    }

    protected function handleFeesState($input)
    {
        if (stripos($input, 'pending') !== false) {
            // Fetch pending fees from the database (example)
            Session::put('state', 'pending_fees_grno');
            return "Please provide your GR No. to check the pending fees.";
        } else {
            Session::put('state', 'initial');
            return "I'm sorry, I didn't understand that. Please specify if you need help with pending fees.";
        }
    }

    // Handle attendance-related queries
    protected function handleAttendanceState($input)
    {
        if (stripos($input, 'Monthly') !== false) {
            // Fetch pending fees from the database (example)
            Session::put('state', 'monthly_attendance');
            return "Please provide your GR No. to check the Attendance.";
        } elseif (stripos($input, 'Yearly') !== false) {
            // Fetch pending fees from the database (example)
            Session::put('state', 'yearly_attendance');
            return "Please provide your GR No. to check the Attendance.";
        }  else {
            Session::put('state', 'initial');
            return "I'm sorry, I didn't understand that. Please specify if you need help with pending fees.";
        }
    }
    
    protected function handleGradesState($input)
    {
        $studentId = trim($input);
        $grades = $this->getGrades($studentId);
        Session::put('state', 'initial');
        return $grades;
    }
    
    protected function handleDynamicResponse($input)
    {
        try {
            $conversationHistory = Session::get('conversationHistory', []);
            $conversationHistory[] = ['role' => 'user', 'content' => $input];

            $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => $conversationHistory,
                    'max_tokens' => 4096,
                    'temperature' => 1.8,
                    'top_p' => 0.5,
                ],
            ]);
    
            $data = json_decode($response->getBody(), true);
            $generatedText = $data['choices'][0]['message']['content'];
            $conversationHistory[] = ['role' => 'assistant', 'content' => $generatedText];
            Session::put('conversationHistory', $conversationHistory);
            return $generatedText;
            
        }catch (RequestException $e) {
            Log::error('OpenAI API Error: ' . $e->getMessage());
            return [
                'title' => 'Error generating title',
                'description' => 'Error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate content for question generation
     * Used by assessmentQuestionController
     */
    public function generateContent($prompt)
    {
        try {
            // Use OpenRouter API with DeepSeek model (same as handleDynamicResponse)
            $response = $this->client->post('https://openrouter.ai/api/v1/chat/completions', [
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey_deepseek,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'https://nextlms.in',
                    'X-Title' => 'Next LMS ERP',
                ],
                'json' => [
                    'model' => 'deepseek/deepseek-chat',
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
            
            return $generatedText;
        } catch (RequestException $e) {
            Log::error('OpenRouter API Error: ' . $e->getMessage());
            throw new \Exception('Failed to generate content: ' . $e->getMessage());
        }
    }
    
    protected function getAttendance($studentId)
    {
        try {
            $url = "https://erp.triz.co.in/student/studentAttendanceChatAPI?type=API&sub_institute_id=254&syear=2024&enrollment_no={$studentId}&token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdHVkZW50X2lkIjo5NzM4Miwic3ViX2luc3RpdHV0ZV9pZCI6MX0.gvRa2kggWK5F1J-qYxZdFWhdRx8ZIqzlzT7pwmlWDAM";
            $response = Http::get($url);
            
            $data = json_decode($response->getBody(), true);

            if ($data['status'] === 1) {
                $htmlResponse = "<p><strong>Monthly Attendance:</strong></p>";
                $htmlResponse .= "<ul>";
                $htmlResponse .= "<li>Working Days: {$data['monthly']['workingDays']}</li>";
                $htmlResponse .= "<li>Present Days: {$data['monthly']['presentDays']}</li>";
                $htmlResponse .= "<li>Absent Days: {$data['monthly']['absentDays']}</li>";
                $htmlResponse .= "</ul>";
                return $htmlResponse;
            } else {
                return "No attendance data found for the provided student ID.";
            }
        } catch (\Exception $e) {
            Log::error('API Error: ' . $e->getMessage());
            return 'Sorry for the inconvenience, please contact site admin.';
        }
    }
    
    protected function getYearlyAttendance($studentId)
    {
        try {
            $url = "https://erp.triz.co.in/student/studentAttendanceChatAPI?type=API&sub_institute_id=254&syear=2024&enrollment_no={$studentId}&token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdHVkZW50X2lkIjo5NzM4Miwic3ViX2luc3RpdHV0ZV9pZCI6MX0.gvRa2kggWK5F1J-qYxZdFWhdRx8ZIqzlzT7pwmlWDAM";
            $response = Http::get($url);
            
            $data = json_decode($response->getBody(), true);

            if ($data['status'] === 1) {
                $htmlResponse = "<p><strong>Yearly Attendance:</strong></p>";
                $htmlResponse .= "<ul>";
                $htmlResponse .= "<li>Working Days: {$data['yearly']['workingDays']}</li>";
                $htmlResponse .= "<li>Present Days: {$data['yearly']['presentDays']}</li>";
                $htmlResponse .= "<li>Absent Days: {$data['yearly']['absentDays']}</li>";
                $htmlResponse .= "</ul>";

                return $htmlResponse;
            } else {
                return "No attendance data found for the provided student ID.";
            }
        } catch (\Exception $e) {
            Log::error('API Error: ' . $e->getMessage());
            return 'Sorry for the inconvenience, please contact site admin.';
        }
    }

    protected function getPendingFees($studentId)
    {
        try {
            $grno = $studentId;
            $sub_institute_id = session()->get('sub_institute_id');
            $syear = session()->get('syear');
            $reqArr = [
                'type' => "API",
                'grno' => $grno,
                'sub_institute_id' => $sub_institute_id,
                'syear' => $syear,
            ];
            $request = new Request($reqArr);
            // send created request to fees_collect controller function show_student
            $feesController = new fees_collect_controller;
            $feesData = json_decode($feesController->show_student($request));
            if (isset($feesData->stu_data) && !empty($feesData->stu_data)) {
                $details = 'Fees Details';
                foreach ($feesData->stu_data as $key => $value) {
                    $details .= '<br><br><p><b>Student Name : </b>' . $value->first_name . ' ' . $value->middle_name . ' ' . $value->last_name . '<br><b>GR No. : </b>' . $value->enrollment_no . '<br><b>Pending Fees : </b>' . $value->bkoff . '</p>';
                }
                return $details;
            } else {
                return 'Not able to find fees from this GR No.';
            }
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error: ' . $e->getMessage());
            return 'Sorry for the inconvenience, please contact site admin.';
        }
    }

    protected function getGrades($studentId)
    {
        try {
            $grades = DB::table('grades')
                        ->where('student_id', $studentId)
                        ->pluck('grade', 'subject');
            if ($grades->isEmpty()) {
                return 'No grades found for this student ID.';
            }
            $gradeList = '';
            foreach ($grades as $subject => $grade) {
                $gradeList .= "$subject: $grade\n";
            }
            return $gradeList;
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error: ' . $e->getMessage());
            return 'Sorry for the inconvenience, please contact site admin.';
        }
    }
    
    public function logConversation($userInput, $botResponse)
    {
        $conversation = Session::get('conversation', []);

        $conversation[] = [
            'user' => $userInput,
            'bot' => $botResponse,
            'timestamp' => now()->toDateTimeString()
        ];

        $conversationFilePath = storage_path('app/conversations/conversation_' . now()->format('Ymd_His') . '.json');
        $finalFilePath = storage_path('app/conversations/conversation.json'); // Use a single file to store all conversations

        $existingConversations = [];

        if (file_exists($finalFilePath)) {
            $existingConversations = json_decode(file_get_contents($finalFilePath), true);
        }
        $isDuplicate = false;
        if (!empty($existingConversations) && is_array($existingConversations)) {
            foreach ($existingConversations as $existingConversation) {
                if ($existingConversation === $conversation) {
                    $isDuplicate = true;
                    break;
                }
            }
        }
        if (!$isDuplicate) {
            $existingConversations[] = $conversation;
            file_put_contents($finalFilePath, json_encode($existingConversations, JSON_PRETTY_PRINT));
        }
    }
    
    public function trackKeyIssues($input)
    {
        $keywords = ['fees', 'grades', 'attendance'];
        $issueCounts = json_decode(Storage::get('key_issues.json'), true) ?? [
            'fees' => 0,
            'grades' => 0,
            'attendance' => 0
        ];
        foreach ($keywords as $keyword) {
            if (stripos($input, $keyword) !== false) {
                $issueCounts[$keyword]++;
            }
        }
        Storage::put('key_issues.json', json_encode($issueCounts, JSON_PRETTY_PRINT));
    }

    /**
     * Generate slide outline for slide count feature
     */
    public function generateSlideOutline($topicName, $chapterName, $subjectName, $standardId, $contentCategory, $slideCount)
    {
        // Create a prompt for slide generation
        $prompt = "Create a detailed slide outline with exactly {$slideCount} slides for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}' (Standard: {$standardId}).\n\n";
        $prompt .= "Content Category: {$contentCategory}\n\n";
        $prompt .= "Please provide a structured outline with {$slideCount} slides. Each slide should have:\n";
        $prompt .= "1. Slide Title\n";
        $prompt .= "2. Key Points (3-5 bullet points)\n";
        $prompt .= "3. Brief explanation\n\n";
        $prompt .= "Format the response as a structured outline.\n";
        $prompt .= "Strictly avoid any personal replies or apologies. Only provide the main content.\n";
        
        try {
            // Use OpenRouter API (same as handleDynamicResponse)
            $response = $this->client->post('https://openrouter.ai/api/v1/chat/completions', [
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey_deepseek,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'https://nextlms.in',
                    'X-Title' => 'NEXT LMS ERP',
                ],
                'json' => [
                    'model' => 'deepseek/deepseek-chat',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 4096,
                    'temperature' => 1.8,
                    'top_p' => 0.5,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            $generatedOutline = $data['choices'][0]['message']['content'];
            
            // Parse the outline into structured format
            $slidesData = $this->parseSlideOutline($generatedOutline, $slideCount);
            
            return $slidesData;
        } catch (RequestException $e) {
            Log::error('OpenRouter API Error: ' . $e->getMessage());
            throw new \Exception('Failed to generate slide outline: ' . $e->getMessage());
        }
    }

    /**
     * Parse the generated outline into structured JSON format
     */
    private function parseSlideOutline($outline, $slideCount)
    {
        $slides = [];
        
        // Split by slide sections (looking for patterns like "Slide 1:", "Slide 1.", etc.)
        $pattern = '/(?:^|\n\s*)(?:Slide\s*)?(\d+)[:\.]?\s*(.*?)(?=(?:\n\s*(?:Slide\s*)?\d+[:\.]?)|$)/si';
        
        // Simple parsing - split by numbered slides
        $lines = preg_split('/\n\s*(?:Slide\s*)?(\d+)[:\.]?\s*/i', "\n" . $outline, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        // Remove first empty element
        array_shift($lines);
        
        $slideNum = 1;
        while (count($lines) >= 2 && $slideNum <= $slideCount) {
            $title = trim($lines[0]);
            $content = trim($lines[1]);
            
            // Extract bullet points
            $keyPoints = [];
            preg_match_all('/[-•*]\s*(.+)/', $content, $matches);
            if (!empty($matches[1])) {
                $keyPoints = array_map('trim', $matches[1]);
            }
            
            $slides[] = [
                'slide_number' => $slideNum,
                'title' => $title ?: "Slide $slideNum",
                'key_points' => $keyPoints,
                'content' => $content
            ];
            
            $slideNum++;
            array_shift($lines);
            array_shift($lines);
        }
        
        // If no slides parsed, create one slide with the full content
        if (empty($slides)) {
            $slides[] = [
                'slide_number' => 1,
                'title' => $topicName ?? 'Slide 1',
                'key_points' => [],
                'content' => $outline
            ];
        }
        
        return [
            'slides' => $slides,
            'total_slides' => count($slides),
            'topic' => $topicName ?? '',
            'chapter' => $chapterName ?? '',
            'subject' => $subjectName ?? '',
            'content_category' => $contentCategory ?? '',
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Save slide outline as JSON file
     */
    public function saveSlideOutlineAsJson($slidesData, $topicName)
    {
        $fileName = 'slides_' . preg_replace('/[^a-zA-Z0-9]/', '_', $topicName) . '_' . time() . '.json';
        $directory = storage_path('app/public/slides_json');
        
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $jsonFilePath = $directory . '/' . $fileName;
        file_put_contents($jsonFilePath, json_encode($slidesData, JSON_PRETTY_PRINT));
        
        Log::info('Slide JSON File Path: ' . $jsonFilePath);
        
        return [
            'file_path' => $jsonFilePath,
            'file_name' => $fileName
        ];
    }

    /**
     * Create PDF from slide outline (for slide count feature)
     */
    public function createPDFFromOutline($content, $topicName, $chapterName, $subjectName, $contentCategory, $slideCount)
    {
        set_time_limit(200);
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);
        $fontPath = storage_path('fonts/Arial.ttf');
        if (!file_exists($fontPath)) {
            $fontPath = storage_path('fonts/arial.ttf');
        }
        
        $dompdf->getOptions()->set('isHtml5ParserEnabled', true);
        $dompdf->getOptions()->set('isFontSubsettingEnabled', true);
        $dompdf->getOptions()->set('isRemoteEnabled', true);

        // Create HTML content for the PDF
        $htmlContent = "
            <html>
                <head>
                    <style>
                        @font-face {
                            font-family: 'Arial';
                            src: url('{$fontPath}') format('truetype');
                        }
                        body {
                            font-family: 'Arial', sans-serif;
                            font-size: 12pt;
                            line-height: 1.5;
                            margin: 20px;
                        }
                        .header {
                            text-align: center;
                            margin-bottom: 30px;
                            border-bottom: 2px solid #333;
                            padding-bottom: 20px;
                        }
                        .title {
                            font-size: 24pt;
                            font-weight: bold;
                            color: #2c3e50;
                        }
                        .subtitle {
                            font-size: 14pt;
                            color: #7f8c8d;
                            margin-top: 10px;
                        }
                        .slide-section {
                            margin-bottom: 25px;
                            page-break-inside: avoid;
                        }
                        .slide-number {
                            font-size: 10pt;
                            color: #95a5a6;
                            margin-bottom: 5px;
                        }
                        .slide-title {
                            font-size: 16pt;
                            font-weight: bold;
                            color: #2980b9;
                            margin-bottom: 10px;
                        }
                        .slide-content {
                            margin-left: 20px;
                        }
                        .bullet-point {
                            margin-bottom: 5px;
                        }
                        .footer {
                            position: fixed;
                            bottom: 0;
                            width: 100%;
                            text-align: center;
                            font-size: 10pt;
                            color: #95a5a6;
                            border-top: 1px solid #ecf0f1;
                            padding-top: 10px;
                        }
                    </style>
                </head>
                <body>
        ";
        
        // Add header
        $htmlContent .= "
            <div class=\"header\">
                <div class=\"title\">{$contentCategory} - {$topicName}</div>
                <div class=\"subtitle\">Chapter: {$chapterName} | Subject: {$subjectName} | Total Slides: {$slideCount}</div>
            </div>
        ";
        
        // Add the generated content
        $htmlContent .= "<div>{$content}</div>";
        
        // Add footer
        $htmlContent .= "
            <div class=\"footer\">
                Generated by NEXT LMS | {$topicName} - {$chapterName}
            </div>
        ";
        
        $htmlContent .= "
                </body>
            </html>
        ";

        $dompdf->loadHtml($htmlContent);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Generate unique filename
        $fileName = 'slides_' . $topicName . '_' . time() . '.pdf';
        $pdfFilePath = storage_path('app/public/pdfs/' . $fileName);
        
        // Ensure directory exists
        $directory = storage_path('app/public/pdfs');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        Log::info('Slide PDF File Path: ' . $pdfFilePath);
        file_put_contents($pdfFilePath, $dompdf->output());
        
        return $pdfFilePath;
    }

    /**
     * Generate slide outline with KAAB competencies and pedagogical approach
     */
    public function generateKAABSlideOutline($params = [])
    {
        $topicName = $params['topicName'] ?? '';
        $chapterName = $params['chapterName'] ?? '';
        $subjectName = $params['subjectName'] ?? '';
        $standardId = $params['standardId'] ?? '';
        $contentCategory = $params['contentCategory'] ?? 'General';
        $slideCount = $params['slideCount'] ?? 10;
        
        // KAAB competencies
        $knowledge = $params['knowledge'] ?? [];
        $ability = $params['ability'] ?? [];
        $attitude = $params['attitude'] ?? [];
        $behaviour = $params['behaviour'] ?? [];
        
        // Pedagogical approach
        $mappingType = $params['mappingType'] ?? '';
        $mappingValue = $params['mappingValue'] ?? '';
        $mappingReason = $params['mappingReason'] ?? '';
        
        // Additional context
        $instructionTaskText = $params['instructionTaskText'] ?? '';
        $criticalWorkFunction = $params['criticalWorkFunction'] ?? '';
        $jobRole = $params['jobRole'] ?? '';
        $keyTask = $params['keyTask'] ?? '';
        $industry = $params['industry'] ?? '';
        $department = $params['department'] ?? '';
        $modalityString = $params['modalityString'] ?? 'Online';
        
        // Build the comprehensive prompt
        $prompt = $this->buildKAABPrompt([
            'topicName' => $topicName,
            'chapterName' => $chapterName,
            'subjectName' => $subjectName,
            'standardId' => $standardId,
            'contentCategory' => $contentCategory,
            'slideCount' => $slideCount,
            'knowledge' => $knowledge,
            'ability' => $ability,
            'attitude' => $attitude,
            'behaviour' => $behaviour,
            'mappingType' => $mappingType,
            'mappingValue' => $mappingValue,
            'mappingReason' => $mappingReason,
            'instructionTaskText' => $instructionTaskText,
            'criticalWorkFunction' => $criticalWorkFunction,
            'jobRole' => $jobRole,
            'keyTask' => $keyTask,
            'industry' => $industry,
            'department' => $department,
            'modalityString' => $modalityString,
            'knowledge_count' => count($knowledge),
            'ability_count' => count($ability),
            'attitude_count' => count($attitude),
            'behaviour_count' => count($behaviour),
        ]);
        
        try {
            // Use OpenRouter API
            $response = $this->client->post('https://openrouter.ai/api/v1/chat/completions', [
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey_deepseek,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'https://nextlms.in',
                    'X-Title' => 'NEXT LMS ERP',
                ],
                'json' => [
                    'model' => 'deepseek/deepseek-chat',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 8192,
                    'temperature' => 0.7,
                    'top_p' => 0.9,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            $generatedOutline = $data['choices'][0]['message']['content'];
            
            // Parse the JSON response
            $slidesData = $this->parseKAABSlideOutline($generatedOutline, $slideCount, $params);
            
            return $slidesData;
            
        } catch (RequestException $e) {
            Log::error('OpenRouter API Error: ' . $e->getMessage());
            throw new \Exception('Failed to generate KAAB slide outline: ' . $e->getMessage());
        }
    }

    /**
     * Build comprehensive KAAB prompt
     */
    private function buildKAABPrompt($params)
    {
        extract($params);
        
        $prompt = "You are an expert instructional designer. Create a detailed slide outline with EXACTLY {$slideCount} slides based on the following parameters:\n\n";
        
        // Basic context
        $prompt .= "CONTEXT:\n";
        $prompt .= "- Topic: {$topicName}\n";
        $prompt .= "- Chapter: {$chapterName}\n";
        $prompt .= "- Subject: {$subjectName}\n";
        $prompt .= "- Standard: {$standardId}\n";
        $prompt .= "- Content Category: {$contentCategory}\n";
        $prompt .= "- Total Slides: {$slideCount}\n\n";
        
        // Pedagogical approach
        if (!empty($mappingType) || !empty($mappingValue)) {
            $prompt .= "PEDAGOGICAL APPROACH:\n";
            $prompt .= "- Mapping Type: {$mappingType}\n";
            $prompt .= "- Mapping Value: {$mappingValue}\n";
            if (!empty($mappingReason)) {
                $prompt .= "- Reason: {$mappingReason}\n";
            }
            $prompt .= "- Course designed using {$mappingValue} {$mappingType} approach\n\n";
        }
        
        // KAAB Competencies
        $prompt .= "KAAB COMPETENCIES:\n";
        
        if (!empty($knowledge)) {
            $prompt .= "KNOWLEDGE ({$knowledge_count} items):\n";
            foreach ($knowledge as $index => $item) {
                $title = is_array($item) ? ($item['title'] ?? $item) : $item;
                $category = is_array($item) ? ($item['category'] ?? '') : '';
                $prompt .= "  " . ($index + 1) . ". {$title}";
                if (!empty($category)) {
                    $prompt .= " ({$category})";
                }
                $prompt .= "\n";
            }
            $prompt .= "\n";
        }
        
        if (!empty($ability)) {
            $prompt .= "ABILITY ({$ability_count} items):\n";
            foreach ($ability as $index => $item) {
                $title = is_array($item) ? ($item['title'] ?? $item) : $item;
                $category = is_array($item) ? ($item['category'] ?? '') : '';
                $prompt .= "  " . ($index + 1) . ". {$title}";
                if (!empty($category)) {
                    $prompt .= " ({$category})";
                }
                $prompt .= "\n";
            }
            $prompt .= "\n";
        }
        
        if (!empty($attitude)) {
            $prompt .= "ATTITUDE ({$attitude_count} items):\n";
            foreach ($attitude as $index => $item) {
                $title = is_array($item) ? ($item['title'] ?? $item) : $item;
                $category = is_array($item) ? ($item['category'] ?? '') : '';
                $prompt .= "  " . ($index + 1) . ". {$title}";
                if (!empty($category)) {
                    $prompt .= " ({$category})";
                }
                $prompt .= "\n";
            }
            $prompt .= "\n";
        }
        
        if (!empty($behaviour)) {
            $prompt .= "BEHAVIOUR ({$behaviour_count} items):\n";
            foreach ($behaviour as $index => $item) {
                $title = is_array($item) ? ($item['title'] ?? $item) : $item;
                $category = is_array($item) ? ($item['category'] ?? '') : '';
                $prompt .= "  " . ($index + 1) . ". {$title}";
                if (!empty($category)) {
                    $prompt .= " ({$category})";
                }
                $prompt .= "\n";
            }
            $prompt .= "\n";
        }
        
        // Additional context
        if (!empty($instructionTaskText)) {
            $prompt .= "INSTRUCTION/TASK: {$instructionTaskText}\n\n";
        }
        if (!empty($criticalWorkFunction)) {
            $prompt .= "CRITICAL WORK FUNCTION: {$criticalWorkFunction}\n\n";
        }
        if (!empty($jobRole)) {
            $prompt .= "JOB ROLE: {$jobRole}\n\n";
        }
        if (!empty($keyTask)) {
            $prompt .= "KEY TASK: {$keyTask}\n\n";
        }
        
        // Output format requirements
        $prompt .= "OUTPUT FORMAT:\n";
        $prompt .= "- Language: Practical, professional, engaging, and competency-based\n";
        $prompt .= "- Style: Formal and structured\n";
        $prompt .= "- Visuals: No visuals, design elements, or styling instructions\n";
        $prompt .= "- Repetition: No repetition of content across slides\n";
        $prompt .= "- Tone: {$modalityString}\n";
        $prompt .= "- Each bullet point must be instructional, clear, and under 40 words\n";
        $prompt .= "- Use only plain text and hyphens for bullets. No markdown or numbering\n";
        $prompt .= "- INTEGRATE ALL KAAB COMPETENCIES across the course\n";
        $prompt .= "- ALIGN content with {$mappingValue} pedagogical approach\n\n";
        
        // Required slides structure
        $prompt .= "REQUIRED SLIDES STRUCTURE:\n";
        $prompt .= "Slide 1: Overview/Introduction\n";
        $prompt .= "  - Training course for {$topicName}\n";
        $prompt .= "  - Key topics covered\n";
        $prompt .= "  - Course modality: {$modalityString}\n";
        $prompt .= "  - Pedagogical approach: {$mappingValue}\n";
        $prompt .= "  - Integrate a selection of KAAB competencies\n\n";
        
        $prompt .= "Slide 2: Learning Objectives\n";
        $prompt .= "  - Targeted outcomes\n";
        $prompt .= "  - Importance of monitoring and evaluation\n";
        $prompt .= "  - How {$mappingValue} approach will be used\n";
        $prompt .= "  - Link objectives to KAAB competency development\n\n";
        
        // Middle slides (dynamic based on slide count)
        $middleSlideCount = max(0, $slideCount - 3);
        $prompt .= "Middle Slides ({$middleSlideCount} slides):\n";
        $prompt .= "  - Cover key concepts and content in logical sequence\n";
        $prompt .= "  - Distribute KAAB competencies evenly across slides\n";
        $prompt .= "  - Apply {$mappingValue} principles throughout\n";
        $prompt .= "  - Each slide should have 5-6 concise bullet points\n\n";
        
        $prompt .= "Slide " . ($slideCount - 1) . ": Summary/Assessment\n";
        $prompt .= "  - Key takeaways\n";
        $prompt .= "  - Knowledge assessment\n";
        $prompt .= "  - Apply learning to scenarios\n";
        $prompt .= "  - Evaluate understanding of attitudes and behaviors\n\n";
        
        $prompt .= "Slide {$slideCount}: Completion Criteria & Evaluation\n";
        $prompt .= "  - Final verification\n";
        $prompt .= "  - Quality assurance checkpoints\n";
        $prompt .= "  - Competency assessment methods\n";
        $prompt .= "  - Evaluate mastery of ALL KAAB competencies covered\n\n";
        
        // IMPORTANT INSTRUCTION
        $totalKAAB = $knowledge_count + $ability_count + $attitude_count + $behaviour_count;
        $prompt .= "CRITICAL REQUIREMENTS:\n";
        $prompt .= "1. You MUST use ALL items from ALL four KAAB categories\n";
        $prompt .= "2. Total KAAB items: {$knowledge_count} knowledge + {$ability_count} ability + {$attitude_count} attitude + {$behaviour_count} behaviour = {$totalKAAB} items\n";
        $prompt .= "3. Spread items evenly across ALL slides based on relevance\n";
        $prompt .= "4. Before finalizing, verify ALL items have been incorporated\n";
        $prompt .= "5. Do not cluster items - distribute evenly throughout the course\n\n";
        
        $prompt .= "OUTPUT IN JSON FORMAT:\n";
        $prompt .= "{\n";
        $prompt .= "  \"slides\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"slide_number\": 1,\n";
        $prompt .= "      \"title\": \"Overview\",\n";
        $prompt .= "      \"bullet_points\": [\n";
        $prompt .= "        \"- Point 1\",\n";
        $prompt .= "        \"- Point 2\"\n";
        $prompt .= "      ]\n";
        $prompt .= "    }\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"total_slides\": {$slideCount},\n";
        $prompt .= "  \"kaab_summary\": {\n";
        $prompt .= "    \"knowledge_used\": {$knowledge_count},\n";
        $prompt .= "    \"ability_used\": {$ability_count},\n";
        $prompt .= "    \"attitude_used\": {$attitude_count},\n";
        $prompt .= "    \"behaviour_used\": {$behaviour_count},\n";
        $prompt .= "    \"total_items\": {$totalKAAB}\n";
        $prompt .= "  }\n";
        $prompt .= "}\n";
        
        return $prompt;
    }

    /**
     * Parse KAAB slide outline from JSON response
     */
    private function parseKAABSlideOutline($response, $slideCount, $params = [])
    {
        // Try to extract JSON from the response
        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
            $decoded = json_decode($jsonString, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded['generated_at'] = date('Y-m-d H:i:s');
                $decoded['raw_response'] = $response;
                return $decoded;
            }
        }
        
        // Fallback: create structured data from text response
        $slides = [];
        
        // Extract slide titles and content
        $pattern = '/Slide\s*(\d+)[:\.]?\s*(.*?)(?=(?:Slide\s*\d+[:\.]?)|$)/si';
        preg_match_all($pattern, $response, $matches, PREG_SET_ORDER);
        
        $slideNum = 1;
        foreach ($matches as $match) {
            $slideNum = (int)$match[1];
            $content = trim($match[2]);
            
            // Extract bullet points
            $bulletPoints = [];
            preg_match_all('/[-•*]\s*(.+)/', $content, $bulletMatches);
            if (!empty($bulletMatches[1])) {
                $bulletPoints = array_map('trim', $bulletMatches[1]);
            }
            
            // Extract title from first line
            $lines = preg_split('/\n/', $content);
            $title = trim($lines[0] ?? "Slide {$slideNum}");
            
            $slides[] = [
                'slide_number' => $slideNum,
                'title' => $title,
                'bullet_points' => $bulletPoints,
                'full_content' => $content
            ];
        }
        
        // If no slides found, create default slides
        if (empty($slides)) {
            for ($i = 1; $i <= min($slideCount, 5); $i++) {
                $slides[] = [
                    'slide_number' => $i,
                    'title' => "Slide {$i}: Content",
                    'bullet_points' => [],
                    'full_content' => $response
                ];
            }
        }
        
        $knowledge_count = count($params['knowledge'] ?? []);
        $ability_count = count($params['ability'] ?? []);
        $attitude_count = count($params['attitude'] ?? []);
        $behaviour_count = count($params['behaviour'] ?? []);
        $totalKAAB = $knowledge_count + $ability_count + $attitude_count + $behaviour_count;
        
        return [
            'slides' => $slides,
            'total_slides' => count($slides),
            'raw_response' => $response,
            'generated_at' => date('Y-m-d H:i:s'),
            'kaab_summary' => [
                'knowledge_used' => $knowledge_count,
                'ability_used' => $ability_count,
                'attitude_used' => $attitude_count,
                'behaviour_used' => $behaviour_count,
                'total_items' => $totalKAAB
            ]
        ];
    }

    /**
     * Generate IBL (Inquiry-Based Learning) Presentation Slides
     * Creates a 30-slide interactive presentation with specific phases
     */
    public function generateIBLPresentationOutline($params = [])
    {
        $topicName = $params['topicName'] ?? '';
        $chapterName = $params['chapterName'] ?? '';
        $subjectName = $params['subjectName'] ?? '';
        $standardId = $params['standardId'] ?? '';
        $contentCategory = $params['contentCategory'] ?? 'General';
        
        // Key topics for IBL
        $keyTopics = $params['keyTopics'] ?? [];
        
        // Build the comprehensive IBL prompt
        $prompt = $this->buildIBLPrompt([
            'topicName' => $topicName,
            'chapterName' => $chapterName,
            'subjectName' => $subjectName,
            'standardId' => $standardId,
            'contentCategory' => $contentCategory,
            'keyTopics' => $keyTopics,
        ]);
        
        try {
            $response = $this->client->post('https://openrouter.ai/api/v1/chat/completions', [
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey_deepseek,
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => 'https://nextlms.in',
                    'X-Title' => 'NEXT LMS ERP',
                ],
                'json' => [
                    'model' => 'deepseek/deepseek-chat',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 8192,
                    'temperature' => 0.7,
                    'top_p' => 0.9,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            $generatedOutline = $data['choices'][0]['message']['content'];
            
            // Parse the JSON response
            $slidesData = $this->parseIBLPresentationOutline($generatedOutline, $params);
            
            return $slidesData;
            
        } catch (RequestException $e) {
            Log::error('OpenRouter API Error: ' . $e->getMessage());
            throw new \Exception('Failed to generate IBL presentation outline: ' . $e->getMessage());
        }
    }

    /**
     * Build comprehensive IBL presentation prompt
     */
    private function buildIBLPrompt($params)
    {
        extract($params);
        
        $prompt = "You are an expert instructional designer specializing in Inquiry-Based Learning (IBL) pedagogy. 
Create a detailed 30-slide interactive presentation for the following parameters:

";

        // Basic context
        $prompt .= "CONTEXT:\n";
        $prompt .= "- Topic: {$topicName}\n";
        $prompt .= "- Chapter: {$chapterName}\n";
        $prompt .= "- Subject: {$subjectName}\n";
        $prompt .= "- Standard: {$standardId}\n";
        $prompt .= "- Content Category: {$contentCategory}\n";
        $prompt .= "- Total Slides: 30\n";
        
        if (!empty($keyTopics)) {
            $prompt .= "- Key Topics to Cover:\n";
            foreach ($keyTopics as $index => $topic) {
                $prompt .= "  " . ($index + 1) . ". {$topic}\n";
            }
            $prompt .= "\n";
        }
        
        // IBL Framework Requirements
        $prompt .= "IBL PEDAGOGY FRAMEWORK:\n";
        $prompt .= "This presentation must follow the structured IBL learning format with these phases:\n\n";
        
        $prompt .= "PHASE 1: ENGAGE (Slides 1-3)\n";
        $prompt .= "- Slide 1: Title Slide with essential question\n";
        $prompt .= "- Slide 2: Learning Outcomes (3-5 measurable outcomes with Bloom's taxonomy)\n";
        $prompt .= "- Slide 3: Activate Prior Knowledge (poll, think-pair-share, or hook)\n\n";
        
        $prompt .= "PHASE 2: EXPLORE (Slides 4-10)\n";
        $prompt .= "- Slides 4-5: Hook Phenomenon (puzzling questions, 'What do you notice? What do you wonder?')\n";
        $prompt .= "- Slide 6: Inquiry Map (visual roadmap with essential and supporting questions)\n";
        $prompt .= "- Slides 7-10: Predict & Hypothesize (scenario-based questions with hypothesis textbox)\n\n";
        
        $prompt .= "PHASE 3: INVESTIGATE (Slides 11-20)\n";
        $prompt .= "- For each key topic (3-5 slides per topic):\n";
        $prompt .= "  - Investigation title question\n";
        $prompt .= "  - Materials list with safety/accessibility icons\n";
        $prompt .= "  - Step-by-step inquiry in left panel\n";
        $prompt .= "  - Data collection/observation space in right panel\n\n";
        
        $prompt .= "PHASE 4: ANALYZE (Slides 21-24)\n";
        $prompt .= "- Graphs, charts, or tables (blank or templated)\n";
        $prompt .= "- Sentence starters for pattern analysis\n";
        $prompt .= "- Peer data comparison opportunities\n\n";
        
        $prompt .= "PHASE 5: COMMUNICATE (Slides 25-27)\n";
        $prompt .= "- Group conclusions with sentence frames\n";
        $prompt .= "- Peer feedback using rubric snippet\n";
        $prompt .= "- Present or record option\n\n";
        
        $prompt .= "PHASE 6: REFLECT & APPLY (Slides 28-30)\n";
        $prompt .= "- Slide 28: Reflect on Learning (3-2-1 exit ticket)\n";
        // Design Guidelines
        $prompt .= "DESIGN GUIDELINES:\n";
        $prompt .= "- Each slide must contain ≥30% visual content (charts, diagrams, expressive imagery)\n";
        $prompt .= "- Each slide must BEGIN with a student-facing question or prompt (avoid pure exposition)\n";
        $prompt .= "- Include interactive elements: polls, think-pair-share, entry boxes, live polls\n";
        $prompt .= "- Accessibility: icons, clear color schemes, alt text placeholders, optional audio narration\n";
        $prompt .= "- Gradually increase student ownership from guided to open inquiry\n";
        $prompt .= "- Insert checkpoints for reflection and peer feedback\n";
        $prompt .= "- Use color coding and icons for visual clarity\n";
        $prompt .= "- Language: concise, persuasive, and student-friendly\n\n";
        
        // Curriculum alignment
        $prompt .= "CURRICULUM ALIGNMENT:\n";
        $prompt .= "- Aligned with NCERT, NEP 2020, NCTE, NPST standards\n";
        $prompt .= "- IBL Pedagogy principles throughout\n";
        $prompt .= "- Focus on curiosity, critical thinking, and collaboration\n\n";
        
        // Image descriptions for each slide
        $prompt .= "IMAGE REQUIREMENTS:\n";
        $prompt .= "For each slide, provide a detailed image description that:\n";
        $prompt .= "- Represents the slide content visually\n";
        $prompt .= "- Provokes curiosity, surprise, or emotion\n";
        $prompt .= "- Is phenomenon-based or real-world related\n";
        $prompt .= "- Includes visual style notes (colors, style, mood)\n\n";
        
        // Output format
        $prompt .= "OUTPUT FORMAT:\n";
        $prompt .= "Return the response as a valid JSON object with the following structure:\n";
        $prompt .= "{\n";
        $prompt .= "  \"slides\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"slide_number\": 1,\n";
        $prompt .= "      \"title\": \"Slide Title\",\n";
        $prompt .= "      \"phase\": \"ENGAGE/EXPLORE/INVESTIGATE/ANALYZE/COMMUNICATE/REFLECT\",\n";
        $prompt .= "      \"question\": \"Student-facing question or prompt\",\n";
        $prompt .= "      \"content\": [\"Key point 1\", \"Key point 2\"],\n";
        $prompt .= "      \"interactive_elements\": [\"poll\", \"think-pair-share\"],\n";
        $prompt .= "      \"image_description\": \"Detailed visual description\",\n";
        $prompt .= "      \"accessibility_notes\": \"Alt text and design notes\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"total_slides\": 30,\n";
        $prompt .= "  \"ibl_summary\": {\n";
        $prompt .= "    \"curriculum_alignment\": [\"NCERT\", \"NEP 2020\", \"NCTE\", \"NPST\", \"IBL Pedagogy\"],\n";
        $prompt .= "    \"key_topics_covered\": \"{$topicName}\",\n";
        $prompt .= "    \"learning_phases\": 6\n";
        $prompt .= "  }\n";
        $prompt .= "}\n";
        
        $prompt .= "\nIMPORTANT:\n";
        $prompt .= "- Each slide MUST start with a student-facing question\n";
        $prompt .= "- Include specific interactive element suggestions for each slide\n";
        $prompt .= "- Provide detailed image descriptions for AI image generation\n";
        $prompt .= "- Ensure 3-5 slides per key topic in the investigation phase\n";
        $prompt .= "- Strictly avoid personal replies or apologies\n";
        $prompt .= "- Use only valid JSON format in the response\n";
        
        return $prompt;
    }

    /**
     * Parse IBL presentation outline from JSON response
     */
    private function parseIBLPresentationOutline($response, $params = [])
    {
        // Try to extract JSON from the response
        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');
        
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonString = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
            $decoded = json_decode($jsonString, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                $decoded['generated_at'] = date('Y-m-d H:i:s');
                $decoded['raw_response'] = $response;
                $decoded['params'] = $params;
                return $decoded;
            }
        }
        
        // Fallback: return raw response for debugging
        return [
            'slides' => [],
            'total_slides' => 0,
            'raw_response' => $response,
            'generated_at' => date('Y-m-d H:i:s'),
            'error' => 'Failed to parse JSON response'
        ];
    }

    /**
     * Generate PDF Presentation using Gamma API
     * Creates a presentation and exports as PDF
     */
   /**
 * Generate PDF Presentation using Gamma API
 * Creates a presentation and exports as PDF
 */
public function generateGammaPresentation($params = [])
{
    $topicName = $params['topicName'] ?? '';
    $chapterName = $params['chapterName'] ?? '';
    $subjectName = $params['subjectName'] ?? '';
    $standardId = $params['standardId'] ?? '';
    $slideCount = $params['slideCount'] ?? 10;
    $contentCategory = $params['contentCategory'] ?? 'General';
    $keyTopics = $params['keyTopics'] ?? '';
    
    Log::info('========================================');
    Log::info('=== generateGammaPresentation CALLED ===');
    Log::info('========================================');
    Log::info('Parameters:', $params);
    
    $gammaApiKey = env('GAMMA_API_KEY');
    $gammaThemeId = env('GAMMA_THEME_ID');
    
    // Valid themes supported by Gamma API
    $validThemes = ['simple', 'minimal', 'corporate', 'creative', 'bold', 'elegant', 'modern'];
    
    // Validate theme - if empty or invalid, don't include theme parameter
    if (empty($gammaThemeId) || !in_array($gammaThemeId, $validThemes)) {
        $gammaThemeId = null; // Let Gamma use default theme
    }
    
    if (empty($gammaApiKey)) {
        Log::error('Gamma API key not configured');
        throw new \Exception('Gamma API key not configured');
    }
    
    // Build the presentation topic content
    $inputText = "{$topicName} - {$chapterName} - {$subjectName} (Standard: {$standardId})";
    if (!empty($keyTopics)) {
        $inputText .= ". Key topics: {$keyTopics}";
    }
    
    Log::info('Input Text for Gamma: ' . $inputText);
    Log::info('Slide Count: ' . $slideCount);
    
    $client = new Client();
    
    try {
        // Base request JSON
        $requestJson = [
            'inputText' => $inputText,
            'textMode' => 'generate',
            'format' => 'presentation',
            'numCards' => (int) $slideCount,
            'cardSplit' => 'auto',
            'additionalInstructions' => "Create an engaging educational presentation about {$topicName} for {$subjectName} class. Content category: {$contentCategory}. Make it suitable for students in standard {$standardId}.",
            'exportAs' => 'pdf',
            'textOptions' => [
                'amount' => 'detailed',
                'tone' => 'professional, inspiring',
                'audience' => 'students',
                'language' => 'en'
            ],
            'imageOptions' => [
                'source' => 'aiGenerated',
                'model' => 'imagen-4-pro',
                'style' => 'photorealistic'
            ],
            'cardOptions' => [
                'dimensions' => 'fluid'
            ]
        ];
        
        // Add theme only if valid (never use 'dark' as it's not supported)
        if (!empty($gammaThemeId)) {
            $requestJson['themeId'] = $gammaThemeId;
        }
        
        Log::info('Gamma API Request JSON:', $requestJson);
        
        // Step 1: Create the generation request
        $response = $client->post('https://public-api.gamma.app/v1.0/generations', [
            'verify' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-KEY' => $gammaApiKey,
            ],
            'json' => $requestJson,
            'timeout' => 60
        ]);
        
        $responseData = json_decode($response->getBody()->getContents(), true);
        
        Log::info('========================================');
        Log::info('=== Gamma API Initial Response ===');
        Log::info('========================================');
        Log::info('Full Response:', $responseData);
        
        // Check for validation errors
        if (isset($responseData['statusCode']) && $responseData['statusCode'] >= 400) {
            Log::error('Gamma API Error Response:', $responseData);
            throw new \Exception('Gamma API Error: ' . ($responseData['message'] ?? 'Unknown error'));
        }
        
        // Check if we got an ID back
        if (isset($responseData['id'])) {
            Log::info('Generation ID found: ' . $responseData['id']);
            Log::info('Status: ' . ($responseData['status'] ?? 'processing'));
            return [
                'success' => true,
                'generation_id' => $responseData['id'],
                'status' => $responseData['status'] ?? 'processing',
                'message' => 'Presentation generation started'
            ];
        }
        
        // If direct URL returned
        if (isset($responseData['url'])) {
            Log::info('Direct URL found in response: ' . $responseData['url']);
            return [
                'success' => true,
                'presentation_url' => $responseData['url'],
                'message' => 'Presentation created successfully'
            ];
        }
        
        Log::info('Returning default success response with data');
        return [
            'success' => true,
            'data' => $responseData,
            'message' => 'Gamma API called successfully'
        ];
        
    } catch (RequestException $e) {
        $errorMessage = $e->getMessage();
        
        // Try to get more detailed error information
        if ($e->hasResponse()) {
            $errorBody = $e->getResponse()->getBody()->getContents();
            Log::error('========================================');
            Log::error('=== Gamma API Error Response ===');
            Log::error('========================================');
            Log::error('Error Body: ' . $errorBody);
            
            // Check for theme-related errors
            if (strpos($errorBody, 'Theme with id') !== false && strpos($errorBody, 'not found') !== false) {
                $errorMessage = 'Invalid theme selected. Please choose a valid theme: ' . implode(', ', $validThemes);
            } else {
                $errorMessage = 'Gamma API Error: ' . $errorBody;
            }
        }
        
        Log::error('Gamma API Error: ' . $errorMessage);
        throw new \Exception('Failed to generate presentation: ' . $errorMessage);
    }
}

    /**
     * Poll Gamma API for generation status and download PDF
     */
    public function getGammaPresentationStatus($generationId)
    {
        $gammaApiKey = env('GAMMA_API_KEY');
        
        if (empty($gammaApiKey)) {
            throw new \Exception('Gamma API key not configured');
        }
        
        $client = new Client();
        
        try {
            $response = $client->get("https://public-api.gamma.app/v1.0/generations/{$generationId}", [
                'verify' => false,
                'headers' => [
                    'X-API-KEY' => $gammaApiKey,
                ],
                'timeout' => 30
            ]);
            
            $responseData = json_decode($response->getBody()->getContents(), true);
            
            Log::info('========================================');
            Log::info('=== Gamma Status Response for ' . $generationId . ' ===');
            Log::info('========================================');
            Log::info('Full Response Data:', $responseData);
            
            // Extract status from various possible locations
            $status = $responseData['status'] ?? 'unknown';
            Log::info('Extracted Status: ' . $status);
            
            // Initialize URL variables
            $url = null;
            $pdfUrl = null;
            
            // Check for presentation in the response (Gamma returns this when complete)
            if (isset($responseData['presentation'])) {
                $presentation = $responseData['presentation'];
                Log::info('Found presentation object:', $presentation);
                if (isset($presentation['url'])) {
                    $url = $presentation['url'];
                    Log::info('Found URL in presentation.url: ' . $url);
                }
                if (isset($presentation['shareUrl'])) {
                    $url = $presentation['shareUrl'];
                    Log::info('Found URL in presentation.shareUrl: ' . $url);
                }
                if (isset($presentation['exportUrl'])) {
                    $pdfUrl = $presentation['exportUrl'];
                    Log::info('Found PDF URL in presentation.exportUrl: ' . $pdfUrl);
                }
                if (isset($presentation['id'])) {
                    // Construct Gamma URL from presentation ID
                    $url = "https://gamma.app/docs/" . $presentation['id'];
                    Log::info('Constructed URL from presentation.id: ' . $url);
                }
            }
            
            // Check for direct URL fields
            if (isset($responseData['url'])) {
                $url = $responseData['url'];
                Log::info('Found URL in responseData.url: ' . $url);
            }
            if (isset($responseData['pdf_url'])) {
                $pdfUrl = $responseData['pdf_url'];
                Log::info('Found PDF URL in responseData.pdf_url: ' . $pdfUrl);
            }
            if (isset($responseData['shareUrl'])) {
                $url = $responseData['shareUrl'];
                Log::info('Found URL in responseData.shareUrl: ' . $url);
            }
            if (isset($responseData['exportUrl'])) {
                $pdfUrl = $responseData['exportUrl'];
                Log::info('Found PDF URL in responseData.exportUrl: ' . $pdfUrl);
            }
            if (isset($responseData['presentation_url'])) {
                $url = $responseData['presentation_url'];
                Log::info('Found URL in responseData.presentation_url: ' . $url);
            }
            
            // Check for nested data
            if (isset($responseData['data']) && is_array($responseData['data'])) {
                $nestedData = $responseData['data'];
                Log::info('Checking nested data:', $nestedData);
                
                if (isset($nestedData['url'])) {
                    $url = $nestedData['url'];
                    Log::info('Found URL in nestedData.url: ' . $url);
                }
                if (isset($nestedData['pdf_url'])) {
                    $pdfUrl = $nestedData['pdf_url'];
                    Log::info('Found PDF URL in nestedData.pdf_url: ' . $pdfUrl);
                }
                if (isset($nestedData['shareUrl'])) {
                    $url = $nestedData['shareUrl'];
                    Log::info('Found URL in nestedData.shareUrl: ' . $url);
                }
                if (isset($nestedData['exportUrl'])) {
                    $pdfUrl = $nestedData['exportUrl'];
                    Log::info('Found PDF URL in nestedData.exportUrl: ' . $pdfUrl);
                }
                if (isset($nestedData['presentation_url'])) {
                    $url = $nestedData['presentation_url'];
                    Log::info('Found URL in nestedData.presentation_url: ' . $url);
                }
                
                // Check for presentation object in nested data
                if (isset($nestedData['presentation'])) {
                    $presentation = $nestedData['presentation'];
                    Log::info('Found presentation in nested data:', $presentation);
                    if (isset($presentation['url'])) {
                        $url = $presentation['url'];
                        Log::info('Found URL in nestedData.presentation.url: ' . $url);
                    }
                    if (isset($presentation['shareUrl'])) {
                        $url = $presentation['shareUrl'];
                        Log::info('Found URL in nestedData.presentation.shareUrl: ' . $url);
                    }
                    if (isset($presentation['id'])) {
                        $url = "https://gamma.app/docs/" . $presentation['id'];
                        Log::info('Constructed URL from nestedData.presentation.id: ' . $url);
                    }
                }
                
                // Check for exports array
                if (isset($nestedData['exports']) && is_array($nestedData['exports'])) {
                    Log::info('Checking exports in nested data:', $nestedData['exports']);
                    foreach ($nestedData['exports'] as $export) {
                        if (isset($export['url'])) {
                            if (isset($export['type']) && $export['type'] === 'pdf') {
                                $pdfUrl = $export['url'];
                                Log::info('Found PDF URL in exports: ' . $pdfUrl);
                            } else if (!$url) {
                                $url = $export['url'];
                                Log::info('Found URL in exports: ' . $url);
                            }
                        }
                    }
                }
            }
            
            // Check for exports array at root level
            if (isset($responseData['exports']) && is_array($responseData['exports'])) {
                Log::info('Checking exports at root level:', $responseData['exports']);
                foreach ($responseData['exports'] as $export) {
                    if (isset($export['url'])) {
                        if (isset($export['type']) && $export['type'] === 'pdf') {
                            $pdfUrl = $export['url'];
                            Log::info('Found PDF URL in root exports: ' . $pdfUrl);
                        } else if (!$url) {
                            $url = $export['url'];
                            Log::info('Found URL in root exports: ' . $url);
                        }
                    }
                }
            }
            
            // Check for cards/slides content (indicates completion)
            if (isset($responseData['cards']) && is_array($responseData['cards']) && count($responseData['cards']) > 0) {
                Log::info('Found cards content, count: ' . count($responseData['cards']));
                // Presentation has content, look for URL
                if (isset($responseData['shareUrl'])) {
                    $url = $responseData['shareUrl'];
                    Log::info('Found URL in responseData.shareUrl: ' . $url);
                }
                if (isset($responseData['id'])) {
                    $url = "https://gamma.app/docs/" . $responseData['id'];
                    Log::info('Constructed URL from responseData.id: ' . $url);
                }
                // If still no URL but has cards, mark as completed
                if (!$url && $status === 'pending') {
                    $status = 'completed';
                    Log::info('Status updated to completed based on cards presence');
                }
            }
            
            // If status is completed/succeeded but no URL found, try to construct from ID
            if (($status === 'completed' || $status === 'succeeded' || $status === 'done') && !$url) {
                Log::info('Status is complete but no URL found, attempting to construct from ID');
                if (isset($responseData['presentationId'])) {
                    $url = "https://gamma.app/docs/" . $responseData['presentationId'];
                    Log::info('Constructed URL from presentationId: ' . $url);
                } elseif (isset($responseData['id'])) {
                    $url = "https://gamma.app/docs/" . $responseData['id'];
                    Log::info('Constructed URL from id: ' . $url);
                } elseif (isset($responseData['generationId'])) {
                    $url = "https://gamma.app/docs/" . $responseData['generationId'];
                    Log::info('Constructed URL from generationId: ' . $url);
                }
            }
            
            Log::info('========================================');
            Log::info('=== FINAL EXTRACTED VALUES ===');
            Log::info('Status: ' . $status);
            Log::info('URL: ' . ($url ?? 'null'));
            Log::info('PDF URL: ' . ($pdfUrl ?? 'null'));
            Log::info('========================================');
            
            return [
                'success' => true,
                'data' => $responseData,
                'status' => $status,
                'url' => $url,
                'pdf_url' => $pdfUrl,
                'raw_response' => $responseData
            ];
            
        } catch (RequestException $e) {
            Log::error('Gamma API Status Error: ' . $e->getMessage());
            throw new \Exception('Failed to get generation status: ' . $e->getMessage());
        }
    }
}
