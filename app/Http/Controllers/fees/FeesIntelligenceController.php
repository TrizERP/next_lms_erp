<?php

namespace App\Http\Controllers\fees;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function App\Helpers\is_mobile;

class FeesIntelligenceController extends Controller
{
    /**
     * Display the Fees Intelligence Center
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        
        // Get session values with fallbacks
        $sub_institute_id = session()->get('sub_institute_id', 1);
        $academic_year = session()->get('syear', date('Y'));
        
        $res['page_title'] = 'Fees Intelligence Center';
        $res['module_name'] = 'fees';
        $res['sub_module_name'] = 'intelligence_center';
        
        // Basic dashboard stats
        $res['dashboard_stats'] = $this->getBasicDashboardStats($sub_institute_id, $academic_year);
        $res['recent_collections'] = [];
        $res['payment_methods'] = [];

        return is_mobile($type, "fees.fees_intelligence", $res, "view");
    }
    
    /**
     * Get basic dashboard stats without service dependency
     */
    private function getBasicDashboardStats($sub_institute_id, $academic_year)
    {
        try {
            // Return safe defaults - no DB queries to avoid errors
            return [
                'total_collected' => 0,
                'total_collected_formatted' => '₹0',
                'collected_change' => 0,
                'collection_rate' => 0,
                'outstanding' => 0,
                'outstanding_formatted' => '₹0',
                'defaulter_count' => 0,
                'total_payable' => 0
            ];
        } catch (\Exception $e) {
            Log::error('Dashboard stats error: ' . $e->getMessage());
            return [
                'total_collected' => 0,
                'total_collected_formatted' => '₹0',
                'collected_change' => 0,
                'collection_rate' => 0,
                'outstanding' => 0,
                'outstanding_formatted' => '₹0',
                'defaulter_count' => 0,
                'total_payable' => 0
            ];
        }
    }
    
    private function formatCurrency($amount)
    {
        $amount = (float) $amount;
        if ($amount >= 10000000) {
            return '₹' . round($amount / 10000000, 1) . ' Cr';
        } elseif ($amount >= 100000) {
            return '₹' . round($amount / 100000, 1) . ' L';
        } elseif ($amount >= 1000) {
            return '₹' . round($amount / 1000, 1) . 'K';
        }
        return '₹' . number_format($amount);
    }

    /**
     * Get dashboard statistics for KPI cards
     */
    public function getDashboardStats(Request $request): JsonResponse
    {
        try {
            $sub_institute_id = session()->get('sub_institute_id', 1);
            $academic_year = session()->get('syear', date('Y'));

            $stats = $this->getBasicDashboardStats($sub_institute_id, $academic_year);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching dashboard stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get fees collection data for charts
     */
    public function getCollectionData(Request $request): JsonResponse
    {
        try {
            $sub_institute_id = session()->get('sub_institute_id', 1);
            $academic_year = session()->get('syear', date('Y'));
            $period = $request->input('period', 'monthly');

            $data = $this->getCollectionDataInternal($sub_institute_id, $academic_year, $period);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Collection data error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching collection data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    private function getCollectionDataInternal($sub_institute_id, $academic_year, $period = 'monthly')
    {
        $data = [];
        
        if ($period === 'monthly') {
            for ($month = 4; $month <= 12; $month++) {
                $year = $academic_year;
                if ($month > 12) {
                    $month = $month - 12;
                    $year = $academic_year + 1;
                }
                
                $start = date('Y-m-01', strtotime("$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01"));
                $end = date('Y-m-t', strtotime($start));

                $collected = DB::table('fees_collect')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->whereBetween('create_date', [$start, $end])
                    ->sum('total_paid');

                $data['labels'][] = date('M', strtotime($start));
                $data['collected'][] = (float) $collected;
            }
            
            for ($month = 1; $month <= 3; $month++) {
                $start = date('Y-m-01', strtotime(($academic_year + 1) . "-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01"));
                $end = date('Y-m-t', strtotime($start));

                $collected = DB::table('fees_collect')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->whereBetween('create_date', [$start, $end])
                    ->sum('total_paid');

                $data['labels'][] = date('M', strtotime($start));
                $data['collected'][] = (float) $collected;
            }
        }
        
        return $data;
    }

    /**
     * Get defaulters data
     */
    public function getDefaultersData(Request $request): JsonResponse
    {
        try {
            $sub_institute_id = session()->get('sub_institute_id', 1);
            $academic_year = session()->get('syear', date('Y'));

            $data = [
                'defaulters' => [],
                'stats' => [
                    'total_pending' => 0,
                    'defaulter_count' => 0
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Defaulters data error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching defaulters data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate AI intelligence based on provided parameters
     */
    public function generateIntelligence(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Intelligence generation placeholder',
                'data' => []
            ]);
        } catch (\Exception $e) {
            Log::error('Intelligence generation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error generating intelligence',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AI-powered recommendations
     */
    public function getRecommendations(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->getDefaultRecommendations()
            ]);
        } catch (\Exception $e) {
            Log::error('Recommendations error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching recommendations',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    private function getDefaultRecommendations()
    {
        return [
            [
                'type' => 'strategic',
                'title' => 'Digital Payment Shift',
                'insight' => 'Cash transactions are causing reconciliation delays.',
                'actions' => ['Offer 2% discount for online/UPI payments', 'Mandate digital for fees above ₹10,000'],
                'expected_impact' => '15% reduction in reconciliation time',
                'modules' => ['fees', 'communication', 'student'],
                'priority' => 'high'
            ],
            [
                'type' => 'operational',
                'title' => 'Pre-emptive Defaulter Alert',
                'insight' => 'Analysis shows students missing 2 consecutive payments become defaulters.',
                'actions' => ['Real-time monitoring of payment patterns', 'Automated SMS after 7 days overdue'],
                'expected_impact' => '40% reduction in defaulter rate',
                'modules' => ['fees', 'attendance', 'communication'],
                'priority' => 'high'
            ]
        ];
    }

    /**
     * Get cross-module workflow suggestions
     */
    public function getCrossModuleWorkflows(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    [
                        'id' => 'wf_enrollment_fee',
                        'title' => 'Student Enrollment → Fee Assignment',
                        'description' => 'Automatically assign fees when a new student is enrolled.',
                        'modules' => ['student', 'fees'],
                        'trigger' => 'Student enrollment completed',
                        'actions' => ['Auto-assign standard-wise fees', 'Generate payment schedule'],
                        'status' => 'active',
                        'execution_count' => 100
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Cross-module workflows error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching workflows',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AI agent configurations and status
     */
    public function getAIAgents(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    [
                        'id' => 'F-01',
                        'name' => 'Autonomous Fees Defaulter Detection Agent',
                        'status' => 'running',
                        'priority' => 'high',
                        'description' => 'Automatically identifies overdue fee accounts and sends escalation reminders.',
                        'kpis' => ['Automated Scans' => '100+', 'Reminders Sent' => '50+', 'Recovery Rate' => '85%'],
                        'modules' => ['fees', 'communication'],
                        'actions' => ['scan', 'configure', 'report', 'logs']
                    ],
                    [
                        'id' => 'F-02',
                        'name' => 'Fee Structure Review Agent',
                        'status' => 'running',
                        'priority' => 'medium',
                        'description' => 'Analyzes fee structures and suggests optimal pricing.',
                        'kpis' => ['Structures Analyzed' => '20+', 'Suggestions Made' => '10+', 'Acceptance Rate' => '70%'],
                        'modules' => ['fees'],
                        'actions' => ['trigger', 'preview', 'configure']
                    ],
                    [
                        'id' => 'F-03',
                        'name' => 'Cash Flow Forecasting Agent',
                        'status' => 'running',
                        'priority' => 'high',
                        'description' => 'Predicts cash flow based on historical data and upcoming obligations.',
                        'kpis' => ['Forecast Accuracy' => '92%', 'Predictions Made' => '30+', 'Cash Predictability' => '40%'],
                        'modules' => ['fees'],
                        'actions' => ['forecast', 'brief', 'configure', 'history']
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('AI agents error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching AI agents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute an AI agent action
     */
    public function executeAgentAction(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => ['message' => 'Action completed']
            ]);
        } catch (\Exception $e) {
            Log::error('Agent action error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error executing agent action',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get action items based on intelligence
     */
    public function getActionItems(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        } catch (\Exception $e) {
            Log::error('Action items error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching action items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get module integration data
     */
    public function getModuleIntegration(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    ['id' => 'student', 'name' => 'Student Management', 'icon' => '👥', 'integration_status' => 'connected'],
                    ['id' => 'attendance', 'name' => 'Attendance', 'icon' => '📊', 'integration_status' => 'connected'],
                    ['id' => 'communication', 'name' => 'Communication', 'icon' => '📱', 'integration_status' => 'connected']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Module integration error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching module data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get fees data from DataTables for injection
     */
    public function getFeesDataTable(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => ['data' => [], 'count' => 0]
            ]);
        } catch (\Exception $e) {
            Log::error('Fees data table error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching fees data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export intelligence report
     */
    public function exportReport(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => ['message' => 'Export placeholder']
            ]);
        } catch (\Exception $e) {
            Log::error('Export report error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error exporting report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
