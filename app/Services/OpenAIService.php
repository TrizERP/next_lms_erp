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
use OpenAI;

set_time_limit(200);

class OpenAIService
{
    protected $client;
    protected $apiKey;

    public function __construct()
    {
        $this->client = OpenAI::client(env('OPENAI_API_KEY'));
    }

    public function generateTitleAndDescription($topicName, $chapterName, $subjectName)
    {   
        $prompt = "Generate a title and description for a topic named '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}'.";

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
    // protected function summarizeText($text)
    // {
    // try {
    //     $response = $this->client->post('https://api.openai.com/v1/chat/completions', [
    //         'verify' => false,
    //         'headers' => [
    //             'Authorization' => 'Bearer ' . $this->apiKey,
    //             'Content-Type' => 'application/json',
    //         ],
    //         'json' => [
    //             'model' => 'gpt-3.5-turbo',
    //             'messages' => [
    //                 ['role' => 'user', 'content' => "Summarize the following text:\n\n{$text}"],
    //             ],
    //             'max_tokens' => 500, // Adjust as needed
    //             'temperature' => 0.7,
    //             'top_p' => 0.9,
    //         ],
    //     ]);

    //     $data = json_decode($response->getBody(), true);
    //     return $data['choices'][0]['message']['content'];
    // } catch (RequestException $e) {
    //     Log::error('OpenAI API Error (Summarization): ' . $e->getMessage());
    //     return $text; // Return original text if summarization fails
    // }
    // }
    public function generateLessonPlan($topicName, $chapterName, $subjectName, $contentCategory, $contentType, $booklistData) {   
    $linksString = implode("\n", $booklistData);
    $pdfFilePathNew = storage_path('app/public/pdfs/iess401.pdf'); 
    if(file_exists($pdfFilePathNew)){
        $pdfContent = file_get_contents($pdfFilePathNew);
        $pdfBase64 = base64_encode($pdfContent);
    } else {
        $pdfBase64 = '';
    }
    
    // Prepare the prompt based on category
    if($contentCategory == 'Worksheet' && $topicName != '1. VIDEOS'){
        $prompt = "Create a detailed worksheet for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                  "Please refer to the following resources for more information:\n{$linksString}\n" .
                  "The response should be detailed enough and no. of questions should be minimum 50, focusing on all genres of questions like long, short, fill in the blanks, and MCQs with answers. Please include examples, explanations, and any relevant information.\n" .
                  "Strictly avoid any personal replies or apologies. Only provide the main content.";
        Log::info('Prompt: ' . $prompt);    
    } else if($topicName == '1. VIDEOS' && $contentCategory != 'Worksheet'){
        $prompt = "Create a detailed '{$contentCategory}' for the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                   "Please refer to the following resources for more information:\n{$linksString}\n" .
                   "The response should be detailed enough to generate a PDF of at least 5 pages, focusing strictly on the basis of NCERT curriculum of '{$chapterName}' and structured '{$contentCategory}'. Minimum words should be 1000. Please include examples, explanations, and any relevant information.\n" .
                   "Strictly avoid any personal replies or apologies. Only provide the main content.";
        Log::info('Prompt: ' . $prompt);  
    } else if($contentCategory == 'Worksheet' && $topicName == '1. VIDEOS'){
        $prompt = "Create a detailed worksheet for the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                  "Please refer to the following resources for more information:\n{$linksString}\n" .
                  "The response should be detailed enough and no. of questions should be minimum 50, focusing on all genres of questions like long, short, fill in the blanks, and MCQs with answers. Please include examples, explanations, and any relevant information.\n" .
                  "Strictly avoid any personal replies or apologies. Only provide the main content.";    
        Log::info('Prompt: ' . $prompt); 
    } else {
        $prompt = "Create a detailed '{$contentCategory}' for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                  "Please refer to the following resources for more information:\n{$linksString}\n" .
                  "The response should be detailed enough to generate a PDF of at least 5 pages, focusing strictly on the NCERT curriculum of '{$chapterName}' and structured '{$contentCategory}'. Minimum words should be 1000. Please include examples, explanations, and any relevant information.\n" .
                  "Strictly avoid any personal replies or apologies. Only provide the main content.\n";
        Log::info('Prompt: ' . $prompt);    
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
                Log::warning("No images generated for iteration $i.");
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

    public function generateLessonPlanNew($topicName, $chapterName, $subjectName, $contentCategory, $contentType, $booklistData, $prompt) {   
                Log::info('Updated Prompt: ' . $prompt);    
                $linksString = implode("\n", $booklistData);
    
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
                    Log::warning("No images generated for iteration $i.");
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
    $linksString = implode("\n", $booklistData);
    $prompt =     "Generate a image in a very realistic approach for the topic '{$topicName}' in the chapter '{$chapterName}' of the subject '{$subjectName}'.\n" .
                  "Please refer to the following resources for more information:\n{$linksString}\n" .
                  "Strictly avoid any personal replies or apologies; and content should be on strictly Indian context with proper english and avoid incorrect spellings or ununderstood text ;Only provide the main content.\n";
        try {
            $response = $this->client->post('https://api.openai.com/v1/images/generations', [
                'verify' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'dall-e-3',
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '1024x1024'
                ],
            ]);
            Log::info("Prompt for image: " . $prompt);
            $data = json_decode($response->getBody(), true);
            $imageUrls = [];
            foreach ($data['data'] as $imageData) {
                $imageUrls[] = $imageData['url'];
                break;
            }
            return $imageUrls;
        } catch (RequestException $e) {
            if ($e->getResponse()->getStatusCode() == 429) {
                // Log::warning('Rate limit exceeded. Retrying in ' . $retryDelay . ' seconds...');
                // sleep($retryDelay); // Wait before retrying
                // continue; // Retry the request
            }
            Log::error('OpenAI Image API Error: ' . $e->getMessage());
            return null;
        }
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

    Log::info('Image Paths: ' . print_r($imagePaths, true));

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
            }catch (RequestException $e) {
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
    $state = Session::get('state', 'initial');

    // Track the key issues based on user input
    $this->trackKeyIssues($input);

    // If in feedback state, handle feedback
    if ($state === 'feedback') {
        return $this->handleFeedback($input);
    }

    // Handle the conversation based on the current state
    switch ($state) {
        case 'initial':
            if (stripos($input, 'fees') !== false) {
                Session::put('state', 'fees');
                return "What issue regarding fees would you like to discuss? (e.g., pending fees)";
            } elseif (stripos($input, 'attendance') !== false) {
                Session::put('state', 'attendance');
                return "Please provide your unique student ID to display attendance.";
            } elseif (stripos($input, 'grades') !== false) {
                Session::put('state', 'grades');
                return "Please provide your unique student ID to fetch your grades.";
            } else {
                return $this->handleInitialState($input); // Handle other unrecognized inputs
            }
            break;
        case 'fees':
            $botResponse = $this->handleFeesState($input);
            break;
        case 'attendance':
            $botResponse = $this->handleAttendanceState($input);
            break;
        case 'grades':
            $botResponse = $this->handleGradesState($input);
            break;
        case 'AI':
            $botResponse = $this->handleDynamicResponse($input);
            break;
        default:
            $botResponse = $this->handleDynamicResponse($input);
            break;
    }

    // Log the conversation (user input and bot response)
    $this->logConversation($input, $botResponse);

    // Ask for feedback after delivering the final output
    if (in_array($state, ['fees', 'attendance', 'grades','AI'])) {
        Session::put('state', 'feedback'); // Switch to feedback state
        return $botResponse . "<br><br> \n\nAre you satisfied with the response? (Yes/No)";
    }

    return $botResponse;
}

// New method to handle user feedback (Yes/No)
public function handleFeedback($input)
{
    if (stripos($input, 'yes') !== false) {
        Session::put('state', 'initial'); // Reset state to initial
        return "Thank you for your feedback! feel free to ask further questions!";
    } elseif (stripos($input, 'no') !== false) {
        Session::put('state', 'initial'); // Reset state to initial
        return "Sorry to hear that. How can I further assist you?";
    } else {
        // If the input is not "Yes" or "No", ask again
        return "Please respond with 'Yes' or 'No'. Are you satisfied with the response?";
    }
}


    protected function handleInitialState($input)
    {
        if (stripos($input, 'fees') !== false) {
            Session::put('state', 'fees');
            return "What issue regarding fees would you like to discuss? (e.g., pending fees)";
        } elseif (stripos($input, 'attendance') !== false) {
            Session::put('state', 'attendance');
            return "Please provide your unique student ID to display attendance.";
        } elseif (stripos($input, 'grades') !== false) {
            Session::put('state', 'grades');
            return "Please provide your unique student ID to fetch your grades.";
        } elseif (stripos($input, 'hello') !== false || stripos($input, 'hi') !== false) {
            // Generic greeting response
            return "Hello! How can I assist you today?";
        } else {
            Session::put('state', 'AI');
            return $this->handleDynamicResponse($input); // Pass any unrecognized input to OpenAI
        }
    }

    // Handle fees-related queries
    protected function handleFeesState($input)
    {
        if (stripos($input, 'pending') !== false) {
            // Fetch pending fees from the database (example)
            $pendingFees = $this->getPendingFees($input);
            Session::put('state', 'initial');
            return $pendingFees;
        } else {
            Session::put('state', 'initial');
            return "I'm sorry, I didn't understand that. Please specify if you need help with pending fees.";
        }
    }

    // Handle attendance-related queries
    protected function handleAttendanceState($input)
    {
        if (is_numeric($input)) {
            // Query the database for attendance
            $attendance = $this->getAttendance($input);
            Session::put('state', 'initial');
            return $attendance;
        } else {
            // If the input is not a valid student ID, prompt the user again
            Session::put('state', 'initial');
            return "Please enter a valid numeric student ID for attendance.";
        }
    }

    // Handle grades-related queries
    protected function handleGradesState($input)
    {
        $studentId = trim($input);
        $grades = $this->getGrades($studentId);
        Session::put('state', 'initial');
        return $grades;
    }
    protected function handleDynamicResponse($input)
    {
        try{
            $response = $this->client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => $input],
                ],
                'max_tokens' => 150, 
            ]);
            Session::put('state','initial');
            return $response['choices'][0]['message']['content'];
        }catch (RequestException $e) {
            Log::error('OpenAI API Error: ' . $e->getMessage());
            Session::put('state','initial');
            return [
                'title' => 'Error generating title',
                'description' => 'Error: ' . $e->getMessage(),
            ];
        }

    }

    protected function getAttendance($studentId)
{
    try {
        $pendingFees = DB::table('fees')
                        ->where('student_id', $studentId)
                        ->value('pendingFees');
        
        return $pendingFees ? $pendingFees . 'Rs' : 'Fees record not found.';
    } catch (\Illuminate\Database\QueryException $e) {
        Log::error('Database error: ' . $e->getMessage());
        return 'Sorry for the inconvenience, please contact site admin.';
    }
}

protected function getPendingFees($studentId)
{
    try {
        $fees = DB::table('attendances')
                        ->where('student_id', $studentId)
                        ->value('attendance_percentage');
        
        return $attendance ? $attendance . '%' : 'Attendance record not found.';
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

}    

