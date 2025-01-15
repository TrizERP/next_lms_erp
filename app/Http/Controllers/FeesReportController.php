<?php

namespace App\Http\Controllers;

use App\Models\fees\fees_collect\FeesCollect;
use App\Models\fees\fees_breackoff\FeesBreackoff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
class FeesReportController extends Controller
{
    public function gethorizontalBarChartData(Request $request)
    {
        $subInstituteId = $request->input('sub_institute_id');
        $fromDate = $request->input('from');
        $toDate = $request->input('to');

        // Parse the 'from' and 'to' dates if they are provided
        if ($fromDate) {
            $fromDate = Carbon::parse($fromDate)->startOfDay()->format('Y-m-d');  // Format to YYYY-MM-DD
        }
        if ($toDate) {
            $toDate = Carbon::parse($toDate)->endOfDay()->format('Y-m-d');  // Format to YYYY-MM-DD
        }

        // Build the query for fees_collect table
        $feesCollectQuery = FeesCollect::where('sub_institute_id', $subInstituteId);

        // Apply date filters only if 'from' and 'to' dates are provided
        if ($fromDate && $toDate) {
            $feesCollectQuery->whereBetween('receiptdate', [$fromDate, $toDate]);
        } elseif ($fromDate) {
            $feesCollectQuery->where('receiptdate', '>=', $fromDate);
        } elseif ($toDate) {
            $feesCollectQuery->where('receiptdate', '<=', $toDate);
        }

        // Execute the query
        $feesCollectData = $feesCollectQuery->get();

        // Build the query for fees_breackoff table
        $feesBreackoffQuery = FeesBreackoff::where('sub_institute_id', $subInstituteId);

        // Apply date filters to fees_breackoff table as well, if provided
        if ($fromDate && $toDate) {
            $feesBreackoffQuery->whereBetween('created_at', [$fromDate, $toDate]);
        } elseif ($fromDate) {
            $feesBreackoffQuery->where('created_at', '>=', $fromDate);
        } elseif ($toDate) {
            $feesBreackoffQuery->where('created_at', '<=', $toDate);
        }

        // Execute the query
        $feesBreackoffData = $feesBreackoffQuery->get();

        // Log the results for debugging
        \Log::debug('Fees Collect Data:', [$feesCollectData]);
        \Log::debug('Fees Breackoff Data:', [$feesBreackoffData]);

        // Process data by grouping it by receiptdate and summing up the relevant fields
        $feesCollectGrouped = $feesCollectData->groupBy(function ($date) {
            // Parse the receiptdate into a standardized format (YYYY-MM-DD)
            return Carbon::parse($date->receiptdate)->toDateString();
        });

        $feesBreackoffGrouped = $feesBreackoffData->groupBy(function ($date) {
            return Carbon::parse($date->created_at)->toDateString(); // Group by created_at
        });

        // Summing the amounts for each group
        $feesCollectSum = $feesCollectGrouped->map(function ($item) {
            return $item->sum('amount');
        });

        $feesBreackoffSum = $feesBreackoffGrouped->map(function ($item) {
            return $item->sum('amount');
        });

        // Return the data as a JSON response
        return response()->json([
            'fees_collect' => $feesCollectSum,
            'fees_breackoff' => $feesBreackoffSum
        ]);
    }
    public function getBarChartData(Request $request)
{
    // Extract the filters from the request
    $subInstituteId = $request->input('sub_institute_id');
    $fromDate = $request->input('from');
    $toDate = $request->input('to');
    $fields = $request->input('field');
    // Validate the input dates and parse them correctly
    try {
        if ($fromDate) {
            $fromDate = Carbon::parse($fromDate)->format('Y-m-d'); // Ensure date format is 'YYYY-MM-DD'
        }

        if ($toDate) {
            $toDate = Carbon::parse($toDate)->format('Y-m-d'); // Ensure date format is 'YYYY-MM-DD'
        }
    } catch (\Exception $e) {
        // Handle date parsing error
        Log::error('Error parsing dates:', ['fromDate' => $fromDate, 'toDate' => $toDate, 'error' => $e->getMessage()]);
        return response()->json(['error' => 'Invalid date format'], 400);
    }

    // Construct the API URL with dynamic parameters
    $url = "https://erp.triz.co.in/easy_com/notification_report/create?";

    // Add the query parameters to the URL
    $url .= http_build_query([
        'type' => 'API',
        'syear' => '2024',  // Assuming 'syear' is constant
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'sub_institute_id' => $subInstituteId
    ]);

    // Log the full request URL
    Log::info('Generated API Request URL:', ['url' => $url]);

    // Make the API call
    try {
        $response = Http::timeout(60)->get($url);

        // Check if the request was successful
        if ($response->successful()) {
            $responseData = $response->json();

            // Ensure the 'data' field exists and is an array
            if (isset($responseData['data']) && is_array($responseData['data'])) {
                $notifications = $responseData['data'];

                $notificationCounts = [];

                // Process each notification and count occurrences by type
                foreach ($notifications as $notification) {
                    if (isset($notification[$fields])) {
                        $notificationType = $notification[$fields];

                        // Count the occurrences of each notification type
                        if (isset($notificationCounts[$notificationType])) {
                            $notificationCounts[$notificationType]++;
                        } else {
                            $notificationCounts[$notificationType] = 1;
                        }
                    } else {
                        // Handle missing NOTIFICATION_TYPE
                        Log::warning('Missing NOTIFICATION_TYPE in notification', ['notification' => $notification]);
                    }
                }

                // Prepare the chart data
                $chartData = [];
                foreach ($notificationCounts as $type => $count) {
                    $chartData[] = [
                        'notification_type' => $type,
                        'count' => $count
                    ];
                }

                // Return the data as JSON for the chart
                return response()->json($chartData);
            } else {
                // Handle case where 'data' is missing or invalid
                Log::error('API response does not contain valid "data" field', ['response' => $responseData]);
                return response()->json(['error' => 'Invalid data format'], 400);
            }
        } else {
            // Handle API request failure (non-200 status)
            Log::error('API request failed', ['status' => $response->status()]);
            return response()->json(['error' => 'API request failed'], 500);
        }
    } catch (\Exception $e) {
        // Handle network or timeout error
        Log::error('Error during API request:', ['error' => $e->getMessage()]);
        return response()->json(['error' => 'Error during API request'], 500);
    }
}


public function getBubbleChartData(Request $request)
{
    // Extract filters from the request
    $subInstituteId = $request->input('sub_institute_id');
    $fromDate = $request->input('from');
    $toDate = $request->input('to');

    // Parse the 'from' and 'to' dates if they are provided
    if ($fromDate) {
        $fromDate = Carbon::parse($fromDate)->startOfDay()->format('Y-m-d');  // Format to YYYY-MM-DD
    }
    if ($toDate) {
        $toDate = Carbon::parse($toDate)->endOfDay()->format('Y-m-d');  // Format to YYYY-MM-DD
    }

    // Query fees_collect data
    $feesCollectQuery = FeesCollect::where('sub_institute_id', $subInstituteId);

    // Apply date filters if provided
    if ($fromDate && $toDate) {
        $feesCollectQuery->whereBetween('receiptdate', [$fromDate, $toDate]);
    } elseif ($fromDate) {
        $feesCollectQuery->where('receiptdate', '>=', $fromDate);
    } elseif ($toDate) {
        $feesCollectQuery->where('receiptdate', '<=', $toDate);
    }

    // Get all fees collected data (could be grouped by day, week, etc. based on requirement)
    $feesCollectData = $feesCollectQuery->select('receiptdate', 'amount')->get();

    // Query fees_breackoff data
    $feesBreackoffQuery = FeesBreackoff::where('sub_institute_id', $subInstituteId);

    // Apply date filters to fees_breackoff if provided
    if ($fromDate && $toDate) {
        $feesBreackoffQuery->whereBetween('created_at', [$fromDate, $toDate]);
    } elseif ($fromDate) {
        $feesBreackoffQuery->where('created_at', '>=', $fromDate);
    } elseif ($toDate) {
        $feesBreackoffQuery->where('created_at', '<=', $toDate);
    }

    // Get all fees break-off data (similar logic to fees_collect)
    $feesBreackoffData = $feesBreackoffQuery->select('created_at', 'amount')->get();

    $combinedData = [];

    // Group fees_collect by receiptdate and fees_breackoff by created_at
    foreach ($feesCollectData as $feeCollect) {
        $date = Carbon::parse($feeCollect->receiptdate)->toDateString();
        $combinedData[$date]['fees_collect'] = $feeCollect->amount;
    }

    foreach ($feesBreackoffData as $feeBreackoff) {
        $date = Carbon::parse($feeBreackoff->created_at)->toDateString();
        $combinedData[$date]['fees_breackoff'] = $feeBreackoff->amount;
    }

    // Now, calculate the bubble sizes based on the combined data
    $feesCollectAmounts = collect($combinedData)->pluck('fees_collect');
    $feesBreackoffAmounts = collect($combinedData)->pluck('fees_breackoff');

    // Calculate the maximum amount from both datasets (fees_collect and fees_breackoff)
    $maxFeesAmount = max($feesCollectAmounts->max(), $feesBreackoffAmounts->max());

    // Calculate bubble sizes for both fees_collect and fees_breackoff
    $bubbleSizes = collect($combinedData)->map(function ($data) use ($maxFeesAmount) {
        // Calculate the relative size based on the max amount
        $totalAmount = ($data['fees_collect'] ?? 0) + ($data['fees_breackoff'] ?? 0);
        return $maxFeesAmount == 0 ? 10 : ($totalAmount / $maxFeesAmount) * 25;  // Scale it down to a reasonable size
    });

    return response()->json([
        'dates' => array_keys($combinedData),  // Dates
        'fees_collect' => $feesCollectAmounts->toArray(),  // Fees collected data as array
        'fees_breackoff' => $feesBreackoffAmounts->toArray(),  // Fees break-off data as array
        'bubbleSizes' => $bubbleSizes->values()->toArray(),  // Bubble sizes as array
    ]);
}

public function getDoughnutChartData(Request $request)
{
    // Extract the filters from the request
    $subInstituteId = $request->input('sub_institute_id');
    $fromDate = $request->input('from');
    $toDate = $request->input('to');
    $fields = $request->input('field');
    // Validate the input dates and parse them correctly
    try {
        if ($fromDate) {
            $fromDate = Carbon::parse($fromDate)->format('Y-m-d'); // Ensure date format is 'YYYY-MM-DD'
        }

        if ($toDate) {
            $toDate = Carbon::parse($toDate)->format('Y-m-d'); // Ensure date format is 'YYYY-MM-DD'
        }
    } catch (\Exception $e) {
        // Handle date parsing error
        Log::error('Error parsing dates:', ['fromDate' => $fromDate, 'toDate' => $toDate, 'error' => $e->getMessage()]);
        return response()->json(['error' => 'Invalid date format'], 400);
    }

    // Construct the API URL with dynamic parameters
    $url = "https://erp.triz.co.in/easy_com/notification_report/create?";

    // Add the query parameters to the URL
    $url .= http_build_query([
        'type' => 'API',
        'syear' => '2024',  // Assuming 'syear' is constant
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'sub_institute_id' => $subInstituteId
    ]);

    // Log the full request URL
    Log::info('Generated API Request URL:', ['url' => $url]);

    // Make the API call
    try {
        $response = Http::timeout(60)->get($url);

        // Check if the request was successful
        if ($response->successful()) {
            $responseData = $response->json();

            // Ensure the 'data' field exists and is an array
            if (isset($responseData['data']) && is_array($responseData['data'])) {
                $notifications = $responseData['data'];

                $notificationCounts = [];

                // Process each notification and count occurrences by type
                foreach ($notifications as $notification) {
                    if (isset($notification[$fields])) {
                        $notificationType = $notification[$fields];

                        // Count the occurrences of each notification type
                        if (isset($notificationCounts[$notificationType])) {
                            $notificationCounts[$notificationType]++;
                        } else {
                            $notificationCounts[$notificationType] = 1;
                        }
                    } else {
                        // Handle missing NOTIFICATION_TYPE
                        Log::warning('Missing NOTIFICATION_TYPE in notification', ['notification' => $notification]);
                    }
                }

                // Prepare the chart data
                $chartData = [];
                foreach ($notificationCounts as $type => $count) {
                    $chartData[] = [
                        'notification_type' => $type,
                        'count' => $count
                    ];
                }

                // Return the data as JSON for the chart
                return response()->json($chartData);
            } else {
                // Handle case where 'data' is missing or invalid
                Log::error('API response does not contain valid "data" field', ['response' => $responseData]);
                return response()->json(['error' => 'Invalid data format'], 400);
            }
        } else {
            // Handle API request failure (non-200 status)
            Log::error('API request failed', ['status' => $response->status()]);
            return response()->json(['error' => 'API request failed'], 500);
        }
    } catch (\Exception $e) {
        // Handle network or timeout error
        Log::error('Error during API request:', ['error' => $e->getMessage()]);
        return response()->json(['error' => 'Error during API request'], 500);
    }
}
public function getRealTimeChartData(Request $request)
{
    
    $subInstituteId = $request->input('sub_institute_id');  // Use Institute ID for the filter
    $fromDate = $request->input('from');
    $toDate = $request->input('to');

    // Parse the 'from' and 'to' dates if they are provided
    if ($fromDate) {
        $fromDate = Carbon::parse($fromDate)->startOfDay()->format('Y-m-d');  // Format to YYYY-MM-DD
    }
    if ($toDate) {
        $toDate = Carbon::parse($toDate)->endOfDay()->format('Y-m-d');  // Format to YYYY-MM-DD
    }

    // Build the query for fees_collect table
    $feesCollectQuery = FeesCollect::where('sub_institute_id', $subInstituteId);

    // Apply date filters if 'from' and 'to' dates are provided
    if ($fromDate && $toDate) {
        $feesCollectQuery->whereBetween('receiptdate', [$fromDate, $toDate]);
    } elseif ($fromDate) {
        $feesCollectQuery->where('receiptdate', '>=', $fromDate);
    } elseif ($toDate) {
        $feesCollectQuery->where('receiptdate', '<=', $toDate);
    }

    // Execute the query to get fees collected data
    $feesCollectData = $feesCollectQuery->get();

    // Group the data by receiptdate and sum the amounts for each date
    $feesCollectGrouped = $feesCollectData->groupBy(function ($date) {
        return Carbon::parse($date->receiptdate)->toDateString();  // Group by date
    });

    // Sum the amounts for each grouped date
    $feesCollectSum = $feesCollectGrouped->map(function ($item) {
        return $item->sum('amount');  // Sum the amounts for each group (date)
    });
    if ($feesCollectData->isNotEmpty()) {
        \Log::debug('Fees Collect Data:', [$feesCollectData->toArray()]);
    } else {
        \Log::debug('No data found for Fees Collect');
    }
    // Return the data as a JSON response
    return response()->json([
        'sub_institute_id' => $subInstituteId,  // Institute ID
        'labels' => $feesCollectSum->keys(),    // Date labels
        'fees_collect' => $feesCollectSum->values(),  // Total fees collected per date
    ]);
}
public function getScatterChartData(Request $request)
{
// Extract the filters from the request
$subInstituteId = $request->input('sub_institute_id');
$fromDate = $request->input('from');
$toDate = $request->input('to');
$fields = $request->input('field');
// Validate the input dates and parse them correctly
try {
    if ($fromDate) {
        $fromDate = Carbon::parse($fromDate)->format('Y-m-d'); // Ensure date format is 'YYYY-MM-DD'
    }

    if ($toDate) {
        $toDate = Carbon::parse($toDate)->format('Y-m-d'); // Ensure date format is 'YYYY-MM-DD'
    }
} catch (\Exception $e) {
    // Handle date parsing error
    Log::error('Error parsing dates:', ['fromDate' => $fromDate, 'toDate' => $toDate, 'error' => $e->getMessage()]);
    return response()->json(['error' => 'Invalid date format'], 400);
}

// Construct the API URL with dynamic parameters
$url = "https://erp.triz.co.in/easy_com/notification_report/create?";

// Add the query parameters to the URL
$url .= http_build_query([
    'type' => 'API',
    'syear' => '2024',
    'from_date' => $fromDate,
    'to_date' => $toDate,
    'sub_institute_id' => $subInstituteId
]);

// Log the full request URL
Log::info('Generated API Request URL:', ['url' => $url]);

// Make the API call
try {
    $response = Http::timeout(60)->get($url);

    // Check if the request was successful
    if ($response->successful()) {
        $responseData = $response->json();
        if (isset($responseData['data']) && is_array($responseData['data'])) {
            $notifications = $responseData['data'];

            $notificationCounts = [];
            $labels = []; 
            // Process each notification and count occurrences by type
            foreach ($notifications as $notification) {
                if (isset($notification[$fields])) {
                    $notificationType = $notification[$fields];
                    $notificationDate = $notification['NOTOFICATION_DATE']; 

                    if (!in_array($notificationDate, $labels)) {
                        $labels[] = $notificationDate;
                    }

                    if (isset($notificationCounts[$notificationType])) {
                        $notificationCounts[$notificationType][] = $notificationDate;
                    } else {
                        $notificationCounts[$notificationType] = [$notificationDate];
                    }
                } else {
                    Log::warning('Missing NOTIFICATION_TYPE in notification', ['notification' => $notification]);
                }
            }

            // Prepare the chart data
            $chartData = [];
            foreach ($notificationCounts as $type => $dates) {
                $dateCounts = array_count_values($dates);  
                $chartData[] = [
                    'notification_type' => $type,
                    'dates' => $dateCounts  
                ];
            }

            // Return the data as JSON for the chart
            return response()->json([
                'labels' => $labels,
                'notification_types' => array_column($chartData, 'notification_type'),
                'counts' => array_map(function($item) {
                    return array_values($item['dates']);
                }, $chartData)
            ]);
        } else {
            // Handle case where 'data' is missing or invalid
            Log::error('API response does not contain valid "data" field', ['response' => $responseData]);
            return response()->json(['error' => 'Invalid data format'], 400);
        }
    } else {
        // Handle API request failure (non-200 status)
        Log::error('API request failed', ['status' => $response->status()]);
        return response()->json(['error' => 'API request failed'], 500);
    }
} catch (\Exception $e) {
    // Handle network or timeout error
    Log::error('Error during API request:', ['error' => $e->getMessage()]);
    return response()->json(['error' => 'Error during API request'], 500);
}
}
public function getPolarAreaChartData(Request $request)
{
    // Extract filters from the request
    $subInstituteId = $request->input('sub_institute_id');
    $fromDate = $request->input('from');
    $toDate = $request->input('to');

    // Parse the 'from' and 'to' dates if they are provided
    if ($fromDate) {
        $fromDate = Carbon::parse($fromDate)->startOfDay()->format('Y-m-d');
    }
    if ($toDate) {
        $toDate = Carbon::parse($toDate)->endOfDay()->format('Y-m-d');
    }

    // Query to get data for Fees Collect
    $feesCollectQuery = FeesCollect::where('sub_institute_id', $subInstituteId);

    // Apply date filters if provided
    if ($fromDate && $toDate) {
        $feesCollectQuery->whereBetween('receiptdate', [$fromDate, $toDate]);
    } elseif ($fromDate) {
        $feesCollectQuery->where('receiptdate', '>=', $fromDate);
    } elseif ($toDate) {
        $feesCollectQuery->where('receiptdate', '<=', $toDate);
    }

    // Execute the query to get the fees collected data
    $feesCollectData = $feesCollectQuery->get();

    // Group the data by receiptdate and sum the amounts for each date
    $feesCollectGrouped = $feesCollectData->groupBy(function ($date) {
        return Carbon::parse($date->receiptdate)->toDateString();  // Group by date
    });

    // Sum the amounts for each grouped date
    $feesCollectSum = $feesCollectGrouped->map(function ($item) {
        return $item->sum('amount');  // Sum the amounts for each group (date)
    });

    // Return the data as a JSON response
    return response()->json([
        'labels' => $feesCollectSum->keys(),    // Date labels
        'fees_collect' => $feesCollectSum->values(),  // Total fees collected per date
    ]);
}

}