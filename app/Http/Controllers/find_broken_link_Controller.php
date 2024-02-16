<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use GenTux\Jwt\GetsJwtToken;
use GenTux\Jwt\JwtToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Mail\BrokenLinksNotification;
use Mail;
use function App\Helpers\is_mobile;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Http\Kernel;

class find_broken_link_Controller extends Controller
{
    public function findBrokenLink(Request $request) 
    {
        $type = $request->input('type');

        if ($type == 'API') 
        {
            $sub_institute_id = $request->input('sub_institute_id');
        } 
        else 
        {
            $sub_institute_id = $request->session()->get('sub_institute_id');
        }

        $brokenLinks = [];
        $otherErrors = [];
        $routeNames = [];

        $get_links = DB::table('tblmenumaster')->take(100)->where('link','!=','javascript:void(0);')->pluck('link')->toArray();
       
        // Get all route names
        foreach (app('router')->getRoutes() as $route) {
            $routeNames[] = $route->getName();
        }

        foreach ($get_links as $link) 
        {
            if (in_array($link, $routeNames)) 
            {
                $convert_link = route($link);
               
                $check_link =$this->isLinkValid($convert_link,$request);
                
                // Check if the link is broken
                if ($check_link !== 200) 
                {
                    $brokenLinks[] = $convert_link;
                }
                
                if($check_link === 'error')
                {
                    $otherErrors[] = $convert_link;
                }
            } 
            else 
            {
                // Skip this route and continue to the next one
                continue;
            }
        }

        // If there are broken links or other errors, send an email
        if (!empty($brokenLinks) || !empty($otherErrors))
        {
            $emailData = [
                'brokenLinks' => $brokenLinks,
                'otherErrors' => $otherErrors,
            ];

            Mail::to(['minhazkhan60@gmail.com', 'rajesh.rafaliya@triz.co.in', 'support@triz.co.in', 'kalpesh@triz.co.in'])->send(new BrokenLinksNotification($emailData));
        }
    }

    // Function to check if a link is valid
    private function isLinkValid($url,$request)
    {
        $url = str_replace('http://127.0.0.1:8000/', 'http://dev.triz.co.in/', $url);

        $response = Http::get($url);
        
        if ($response->getStatusCode() === 200) {
            // Link is correct and the request was successful
            return 200;
        } else {
            // Link may be incorrect or there was an error in the request
            return $url;
        }
    }
}
