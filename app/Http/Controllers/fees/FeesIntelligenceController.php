<?php

namespace App\Http\Controllers\fees;

use App\Http\Controllers\Controller;
use App\Services\FeesIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use function App\Helpers\is_mobile;

class FeesIntelligenceController extends Controller
{
    protected $intelligenceService;

    public function __construct()
    {
        $this->intelligenceService = new FeesIntelligenceService();
    }

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
        
        // Get dashboard stats from service
        try {
            $res['dashboard_stats'] = $this->intelligenceService->getDashboardStats($sub_institute_id, $academic_year);
        } catch (\Exception $e) {
            Log::error('Dashboard stats error in index: ' . $e->getMessage());
            $res['dashboard_stats'] = $this->getEmptyDashboardStats();
        }
        
        $res['recent_collections'] = [];
        $res['payment_methods'] = [];
        
        return is_mobile($type, "fees.fees_intelligence", $res, "view");
    }

    /**
     * Get empty dashboard stats as fallback
     */
    private function getEmptyDashboardStats()
    {
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

    /**
     * Get dashboard statistics for KPI cards
     */
    public function getDashboardStats(Request $request): JsonResponse
    {
        try {
            $sub_institute_id = session()->get('sub_institute_id', 1);
            $academic_year = session()->get('syear', date('Y'));

            $stats = $this->intelligenceService->getDashboardStats($sub_institute_id, $academic_year);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard stats API error: ' . $e->getMessage());
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

            $data = $this->intelligenceService->getCollectionData($sub_institute_id, $academic_year, $period);

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

    /**
     * Get defaulters data
     */
    public function getDefaultersData(Request $request): JsonResponse
    {
        try {
            $sub_institute_id = session()->get('sub_institute_id', 1);
            $academic_year = session()->get('syear', date('Y'));
            $standard_id = $request->input('standard_id');

            $data = $this->intelligenceService->getDefaultersData($sub_institute_id, $academic_year, $standard_id);

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
            $sub_institute_id = session()->get('sub_institute_id', 1);
            $academic_year = session()->get('syear', date('Y'));
            $user_id = session()->get('user_id', 0);

            $extraction_prompt = $request->input('extraction_prompt', '');
            $intelligence_prompt = $request->input('intelligence_prompt', '');
            $selected_modules = $request->input('selected_modules', []);

            $result = $this->intelligenceService->generateIntelligence(
                $sub_institute_id,
                $academic_year,
                $user_id,
                $extraction_prompt,
                $intelligence_prompt,
                $selected_modules
            );

            return response()->json([
                'success' => true,
                'message' => 'Intelligence generated successfully',
                'data' => $result
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
            $sub_institute_id = session()->get('sub_institute_id', 1);
            $academic_year = session()->get('syear', date('Y'));
            $type = $request->input('type', 'all');

            $data = $this->intelligenceService->getRecommendations($sub_institute_id, $academic_year, $type);

            return response()->json([
                'success' => true,
                'data' => $data
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

    /**
     * Get cross-module workflow suggestions
     */
    public function getCrossModuleWorkflows(Request $request): JsonResponse
    {
        try {
            $sub_institute_id = session()->get('sub_institute_id', 1);
            $academic_year = session()->get('syear', date('Y'));

            $data = $this->intelligenceService->getCrossModuleWorkflows($sub_institute_id, $academic_year);

            return response()->json([
                'success' => true,
                'data' => $data
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
            $sub_institute_id = session()->get('sub_institute_id', 1);

            $data = $this->intelligenceService->getAIAgents($sub_institute_id);

            return response()->json([
                'success' => true,
                'data' => $data
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
            $agent_id = $request->input('agent_id');
            $action = $request->input('action');
            $parameters = $request->input('parameters', []);

            $sub_institute_id = session()->get('sub_institute_id', 1);
            $academic_year = session()->get('syear', date('Y'));
            $user_id = session()->get('user_id', 0);

            $result = $this->intelligenceService->executeAgentAction(
                $agent_id,
                $action,
                $parameters,
                $sub_institute_id,
                $academic_year,
                $user_id
            );

            return response()->json([
                'success' => true,
                'data' => $result
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
            $sub_institute_id = session()->get('sub_institute_id', 1);
            $academic_year = session()->get('syear', date('Y'));
            $priority = $request->input('priority');

            $data = $this->intelligenceService->getActionItems($sub_institute_id, $academic_year, $priority);

            return response()->json([
                'success' => true,
                'data' => $data
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
            $sub_institute_id = session()->get('sub_institute_id', 1);

            $data = $this->intelligenceService->getModuleIntegration($sub_institute_id);

            return response()->json([
                'success' => true,
                'data' => $data
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
            $sub_institute_id = session()->get('sub_institute_id', 1);
            $academic_year = session()->get('syear', date('Y'));
            $table = $request->input('table');

            $data = $this->intelligenceService->getFeesDataTable($sub_institute_id, $academic_year, $table);

            return response()->json([
                'success' => true,
                'data' => $data
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
            $sub_institute_id = session()->get('sub_institute_id', 1);
            $academic_year = session()->get('syear', date('Y'));
            $format = $request->input('format', 'pdf');

            $result = $this->intelligenceService->exportReport($sub_institute_id, $academic_year, $format);

            return response()->json([
                'success' => true,
                'data' => $result
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
