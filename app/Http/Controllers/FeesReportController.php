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

        $feesCollectGrouped = $feesCollectData->groupBy(function ($date) {
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
    $xFields = $request->input('x_field'); 
    $yFields = $request->input('y_field');
    $reportType = $request->input('report_type'); // Get report type dynamically
    $reportName = $request->input('report_name');
    $dataType = $request->input('data_type');
    $sYear = $request->input('syear');
    // Log::Info($reportType);
    // Log::Info($reportName);
    // Log::Info($dataType);
    // Log::Info($sYear);
    // Log::Info($xFields);
    // Log::Info($yFields);
    // Validate and format the input dates
    try {
        if ($fromDate) {
            $fromDate = Carbon::parse($fromDate)->format('Y-m-d');
        }

        if ($toDate) {
            $toDate = Carbon::parse($toDate)->format('Y-m-d');
        }
    } catch (\Exception $e) {
        Log::error('Error parsing dates:', ['fromDate' => $fromDate, 'toDate' => $toDate, 'error' => $e->getMessage()]);
        return response()->json(['error' => 'Invalid date format'], 400);
    }

    $baseUrl = "https://erp.triz.co.in/";
    $apiEndpoint = strtolower(str_replace(' ', '_', $reportType)) . "_report/create"; 
    $url = $baseUrl .$reportName."/". $apiEndpoint . "?";  
    $queryParams = http_build_query([
        'type' => 'API',
        'syear' => $sYear,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'sub_institute_id' => $subInstituteId,
        'token'=> 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE3NDA4MjU0NTYsInVzZXJfaWQiOjEwMDIsInN1Yl9pbnN0aXR1dGVfaWQiOjEsIm1vYmlsZSI6Ijk5NzkxNzY1NjIifQ.dhGbQPmJiidmH8b2zBo3HePl-lduftwAPM6dTeG90f0'
    ]);

    $url .= $queryParams; 
    Log::info('Generated API Request URL:', ['url' => $url]);

    try {
        $response = Http::timeout(60)->get($url);

        if ($response->successful()) {
            $responseData = $response->json();

            // Ensure 'data' field exists and is an array
            if (isset($responseData[$dataType]) && is_array($responseData[$dataType])) {
                $reportData = $responseData[$dataType];

                $dataCounts = [];

                // Process each entry dynamically based on the selected field
                foreach ($reportData as $entry) {
                    if (isset($entry[$xFields])) {
                        $fieldValue = $entry[$xFields];

                        // If yFields is not provided, count occurrences of xFields
                        if (!$yFields) {
                            $dataCounts[$fieldValue] = isset($dataCounts[$fieldValue]) ? $dataCounts[$fieldValue] + 1 : 1;
                        } 
                        // If yFields is provided, sum up yFields values grouped by xFields
                        else {
                            $yValue = isset($entry[$yFields]) ? floatval($entry[$yFields]) : 0;
                            $dataCounts[$fieldValue] = isset($dataCounts[$fieldValue]) ? $dataCounts[$fieldValue] + $yValue : $yValue;
                        }
                    } else {
                        Log::warning("Missing field '$xFields' in entry", ['entry' => $entry]);
                    }
                }

                // Prepare data for the chart
                $chartData = [];
                foreach ($dataCounts as $fieldValue => $count) {
                    $chartData[] = [
                        'label' => $fieldValue, // Label for chart
                        'count' => $count
                    ];
                }

                return response()->json($chartData);
            } else {
                Log::error('API response does not contain valid "data" field', ['response' => $responseData]);
                return response()->json(['error' => 'Invalid data format'], 400);
            }
        } else {
            Log::error('API request failed', ['status' => $response->status()]);
            return response()->json(['error' => 'API request failed'], 500);
        }
    } catch (\Exception $e) {
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

    $feesBreackoffData = $feesBreackoffQuery->select('created_at', 'amount')->get();

    $combinedData = [];

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
        $xFields = $request->input('x_field'); 
        $yFields = $request->input('y_field');
        $reportType = $request->input('report_type'); 
        $reportName = $request->input('report_name');
        $dataType = $request->input('data_type');
        $sYear = $request->input('syear');
        $countType = $request->input('count_type');
        // Log::Info($reportType);
        // Log::Info($reportName);
        // Log::Info($dataType);
        // Log::Info($sYear);
        // Log::Info($xFields);
        // Log::Info($yFields);
        try {
            if ($fromDate) {
                $fromDate = Carbon::parse($fromDate)->format('Y-m-d');
            }
            if ($toDate) {
                $toDate = Carbon::parse($toDate)->format('Y-m-d');
            }
        } catch (\Exception $e) {
            Log::error('Error parsing dates:', ['fromDate' => $fromDate, 'toDate' => $toDate, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid date format'], 400);
        }
    $baseUrl = "https://erp.triz.co.in/";
    $apiEndpoint = strtolower(str_replace(' ', '_', $reportType)) . "_report/create"; 

    Log::info('Generated API Endpoint:', ['apiEndpoint' => $apiEndpoint]);

    $url = $baseUrl .$reportName."/". $apiEndpoint . "?";  

    $queryParams = http_build_query([
        'type' => 'API',
        'syear' => $sYear,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'sub_institute_id' => $subInstituteId,
        'token'=> 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE3NDA4MjU0NTYsInVzZXJfaWQiOjEwMDIsInN1Yl9pbnN0aXR1dGVfaWQiOjEsIm1vYmlsZSI6Ijk5NzkxNzY1NjIifQ.dhGbQPmJiidmH8b2zBo3HePl-lduftwAPM6dTeG90f0'
    ]);

    $url .= $queryParams; 

    Log::info('Generated API Request URL:', ['url' => $url]);

    try {
        $response = Http::timeout(60)->get($url);

        if ($response->successful()) {
            $responseData = $response->json();

            if (isset($responseData[$dataType]) && is_array($responseData[$dataType])) {
                $reportData = $responseData[$dataType];
                $dataCounts = [];

                foreach ($reportData as $entry) {
                    if (isset($entry[$xFields])) {
                        $fieldValue = $entry[$xFields];

                        if ($yFields && isset($entry[$yFields])) {
                           
                            $dataCounts[$fieldValue] = isset($dataCounts[$fieldValue]) 
                                ? $dataCounts[$fieldValue] + floatval($entry[$yFields]) 
                                : floatval($entry[$yFields]);
                        } else {
            
                            $dataCounts[$fieldValue] = isset($dataCounts[$fieldValue]) 
                                ? $dataCounts[$fieldValue] + 1 
                                : 1;
                        }
                    } else {
                        Log::warning("Missing field '{$xFields}' in entry", ['entry' => $entry]);
                    }
                }

                $chartData = [];
                foreach ($dataCounts as $fieldValue => $count) {
                    $chartData[] = [
                        'label' => $fieldValue,
                        'count' => $count
                    ];
                }

                return response()->json($chartData);
            } else {
                Log::error('API response does not contain valid "data" field', ['response' => $responseData]);
                return response()->json(['error' => 'Invalid data format'], 400);
            }
        } else {
            Log::error('API request failed', ['status' => $response->status()]);
            return response()->json(['error' => 'API request failed'], 500);
        }
    } catch (\Exception $e) {
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
// public function addData()
//     {
//         $date = Carbon::now()->startOfDay();

//         for ($i = 1; $i <= 20; $i++) {
//             $item = new FeesCollect();
//             $item->country_id = 29;
//             $item->date = $date->toDateString();
//             $item->Confirmed = rand(0, 200);
//             $item->Deaths = rand(0, 100);
//             $item->Recovered = rand(0, 100);
//             $item->Active = rand(0, 100);
//             $item->save();
//             $date->addDay();
//             event(new addedDataEvent('India', $item->date, $item->Confirmed));
//             sleep(2);
//         }
//     }    
public function getScatterChartData(Request $request)
{
    // Extract the filters from the request
    $subInstituteId = $request->input('sub_institute_id');
    $fromDate = $request->input('from');
    $toDate = $request->input('to');
    $xFields = $request->input('x_field'); 
    $yFields = $request->input('y_field');
    $reportType = $request->input('report_type'); 
    $reportName = $request->input('report_name');
    $dataType = $request->input('data_type');
    $sYear = $request->input('syear');
    $countType = $request->input('count_type');
    // Log::Info($countType);
    // Log::Info($reportType);
    // Log::Info($reportName);
    // Log::Info($dataType);
    // Log::Info($sYear);
    // Log::Info($xFields);
    // Log::Info($yFields);
    try {
        if ($fromDate) {
            $fromDate = Carbon::parse($fromDate)->format('Y-m-d');
        }
        if ($toDate) {
            $toDate = Carbon::parse($toDate)->format('Y-m-d');
        }
    } catch (\Exception $e) {
        Log::error('Error parsing dates:', ['fromDate' => $fromDate, 'toDate' => $toDate, 'error' => $e->getMessage()]);
        return response()->json(['error' => 'Invalid date format'], 400);
    }

    $baseUrl = "https://erp.triz.co.in/";
    $apiEndpoint = strtolower(str_replace(' ', '_', $reportType)) . "_report/create"; 
    $url = $baseUrl.$reportName ."/". $apiEndpoint . "?";  

    $queryParams = http_build_query([
        'type' => 'API',
        'syear' => $sYear,
        'from_date' => $fromDate,
        'to_date' => $toDate,
        'sub_institute_id' => $subInstituteId,
        'token'=> 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE3NDA4MjU0NTYsInVzZXJfaWQiOjEwMDIsInN1Yl9pbnN0aXR1dGVfaWQiOjEsIm1vYmlsZSI6Ijk5NzkxNzY1NjIifQ.dhGbQPmJiidmH8b2zBo3HePl-lduftwAPM6dTeG90f0'
    ]);

    $url .= $queryParams; 
    // Log the full request URL
    Log::info('Generated API Request URL:', ['url' => $url]);

    // Make the API call
    try {
        $response = Http::timeout(60)->get($url);

        // Check if the request was successful
        if ($response->successful()) {
            $responseData = $response->json();

            if (isset($responseData[$dataType]) && is_array($responseData[$dataType])) {
                $dataEntries = $responseData[$dataType];

                $dataCounts = [];
                $labels = [];

                // Process data dynamically based on field and date
                foreach ($dataEntries as $entry) {
                 if (!$yFields) {
                    if (isset($entry[$xFields])) {
                        $dataValue = $entry[$xFields];
                        $dateField = $entry['DATE'] ?? $entry[$countType] ?? null;

                        if ($dateField) {
                            if (!in_array($dateField, $labels)) {
                                $labels[] = $dateField;
                            }

                            if (isset($dataCounts[$dataValue])) {
                                $dataCounts[$dataValue][] = $dateField;
                            } else {
                                $dataCounts[$dataValue] = [$dateField];
                            }
                        } else {
                            Log::warning('Missing DATE field in entry', ['entry' => $entry]);
                        }
                    } else {
                        Log::warning("Missing {$xFields} in entry", ['entry' => $entry]);
                    }
                } else{
                    if (isset($entry[$xFields]) && isset($entry[$yFields])) {
                        $dataValue = $entry[$xFields];
                        $yValue = intval($entry[$yFields]);   
                        $dateField = $entry['DATE'] ?? $entry[$countType] ?? null;
                
                        if ($dateField) {
                            if (!in_array($dateField, $labels)) {
                                $labels[] = $dateField;
                            }
                
                            if (!isset($dataCounts[$dataValue])) {
                                $dataCounts[$dataValue] = [];
                            }
                
                            if (!isset($dataCounts[$dataValue][$dateField])) {
                                $dataCounts[$dataValue][$dateField] = 0;
                            }
                
                            $dataCounts[$dataValue][$dateField] += $yValue;

                            //Log::Info($dataCounts[$dataValue][$dateField]);
                        } else {
                            Log::warning('Missing DATE field in entry', ['entry' => $entry]);
                        }
                    } else {
                        Log::warning("Missing {$xFields} or {$yFields} in entry", ['entry' => $entry]);
                    }
                }
            }
                $chartData = [];
                if(!$yFields){
                foreach ($dataCounts as $type => $dates) {
                    //Log::Info($dates);
                    $dateCounts = array_count_values($dates);
                    //Log::Info($dateCounts);
                    $chartData[] = [
                        'type' => $type,
                        'dates' => $dateCounts
                    ];
                }
                //Log::Info($chartData);
            }else{
                foreach ($dataCounts as $type => $dates) {
                    // if (is_array($dates)) {
                    //     //Log::Info($dates);
                    //     $dateCounts = array_count_values($dates);
                    // } else {
                    //     //Log::Error("Expected array but got: " . gettype($dates));
                    //     $dateCounts = []; 
                    // }
                    $chartData[] = [
                        'type' => $type,
                        'dates' => $dates
                    ];
                }
                //Log::Info($chartData);
            }
            Log::debug('Response Data', [
                'labels' => $labels,
                'types' => array_column($chartData, 'type'),
                'counts' => array_map(function ($item) {
                    return array_values($item['dates']);
                }, $chartData)
            ]);  
            return response()->json([
                    'labels' => $labels,
                    'types' => array_column($chartData, 'type'),
                    'counts' => array_map(function ($item) {
                        return array_values($item['dates']);
                    }, $chartData)
                ]);
            } else {
                Log::error('API response does not contain valid "data" field', ['response' => $responseData]);
                return response()->json(['error' => 'Invalid data format'], 400);
            }
        } else {
            Log::error('API request failed', ['status' => $response->status()]);
            return response()->json(['error' => 'API request failed'], 500);
        }
    } catch (\Exception $e) {
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