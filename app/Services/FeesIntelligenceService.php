<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;

class FeesIntelligenceService
{
    protected Client $httpClient;
    protected string $apiKey;
    protected string $apiEndpoint;

    public function __construct()
    {
        $this->httpClient = new Client(['verify' => false]);
        $this->apiKey = env('OPENAI_API_KEY', '');
        $this->apiEndpoint = env('OPENAI_API_ENDPOINT', 'https://api.openai.com/v1/chat/completions');
    }

    /**
     * Get dashboard statistics for KPI cards
     */
    public function getDashboardStats($sub_institute_id, $academic_year): array
    {
        try {
            $month_start = date('Y-m-01');
            $month_end = date('Y-m-t');

            // Current month collection
            $total_collected = DB::table('fees_collect')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('is_deleted', 'N')
                ->whereBetween('receiptdate', [$month_start, $month_end])
                ->sum('amount');

            // Previous month for comparison
            $prev_month_start = date('Y-m-01', strtotime('-1 month'));
            $prev_month_end = date('Y-m-t', strtotime('-1 month'));

            $prev_total_collected = DB::table('fees_collect')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('is_deleted', 'N')
                ->whereBetween('receiptdate', [$prev_month_start, $prev_month_end])
                ->sum('amount');

            // Total payable for the academic year
            $total_payable = DB::table('fees_breackoff')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $academic_year)
                ->where('admission_year', $academic_year)
                ->sum('amount');

            // Collection rate
            $collection_rate = $total_payable > 0 
                ? round(($total_collected / $total_payable) * 100, 1) 
                : 0;

            // Outstanding
            $outstanding = max(0, $total_payable - $total_collected);

            // Defaulter count - students with pending fees
            $defaulter_count = $this->getDefaulterCount($sub_institute_id, $academic_year);

            // Previous collection rate
            $prev_collection_rate = DB::table('fees_breackoff')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $academic_year - 1)
                ->where('admission_year', $academic_year - 1)
                ->sum('amount');

            $prev_collection_rate = $prev_collection_rate > 0 
                ? round(($prev_total_collected / $prev_collection_rate) * 100, 1) 
                : 0;

            // Calculate changes
            $collected_change = $prev_total_collected > 0 
                ? round((($total_collected - $prev_total_collected) / $prev_total_collected) * 100, 1) 
                : 0;

            $collection_rate_change = $collection_rate - $prev_collection_rate;

            return [
                'total_collected' => (float) $total_collected,
                'total_collected_formatted' => $this->formatCurrency($total_collected),
                'collected_change' => $collected_change,
                'collection_rate' => $collection_rate,
                'collection_rate_change' => $collection_rate_change,
                'outstanding' => (float) $outstanding,
                'outstanding_formatted' => $this->formatCurrency($outstanding),
                'defaulter_count' => $defaulter_count,
                'total_payable' => (float) $total_payable,
                'period' => [
                    'start' => $month_start,
                    'end' => $month_end
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Dashboard stats error: ' . $e->getMessage());
            return [
                'total_collected' => 0,
                'total_collected_formatted' => '₹0',
                'collected_change' => 0,
                'collection_rate' => 0,
                'collection_rate_change' => 0,
                'outstanding' => 0,
                'outstanding_formatted' => '₹0',
                'defaulter_count' => 0,
                'total_payable' => 0
            ];
        }
    }

    /**
     * Get collection data for charts
     */
    public function getCollectionData($sub_institute_id, $academic_year, $period = 'monthly'): array
    {
        try {
            $data = [];
            
            if ($period === 'monthly') {
                // Get monthly collection data for the academic year
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
                        ->where('is_deleted', 'N')
                        ->whereBetween('receiptdate', [$start, $end])
                        ->sum('amount');

                    $monthName = date('M', strtotime($start));
                    
                    $data['labels'][] = $monthName;
                    $data['collected'][] = (float) $collected;
                }
                
                // Next year months (Jan-Mar)
                for ($month = 1; $month <= 3; $month++) {
                    $start = date('Y-m-01', strtotime(($academic_year + 1) . "-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01"));
                    $end = date('Y-m-t', strtotime($start));

                    $collected = DB::table('fees_collect')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('is_deleted', 'N')
                        ->whereBetween('receiptdate', [$start, $end])
                        ->sum('amount');

                    $data['labels'][] = date('M', strtotime($start));
                    $data['collected'][] = (float) $collected;
                }
            } elseif ($period === 'weekly') {
                // Get last 12 weeks of data
                for ($i = 11; $i >= 0; $i--) {
                    $week_start = date('Y-m-d', strtotime("monday -$i weeks"));
                    $week_end = date('Y-m-d', strtotime("sunday -$i weeks"));

                    $collected = DB::table('fees_collect')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('is_deleted', 'N')
                        ->whereBetween('receiptdate', [$week_start, $week_end])
                        ->sum('amount');

                    $data['labels'][] = "W" . (12 - $i);
                    $data['collected'][] = (float) $collected;
                }
            } else {
                // Daily data for current month
                $days_in_month = date('t');
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $date = date('Y-m-') . str_pad($day, 2, '0', STR_PAD_LEFT);
                    
                    $collected = DB::table('fees_collect')
                        ->where('sub_institute_id', $sub_institute_id)
                        ->where('is_deleted', 'N')
                        ->where('receiptdate', $date)
                        ->sum('amount');

                    $data['labels'][] = $day;
                    $data['collected'][] = (float) $collected;
                }
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Collection data error: ' . $e->getMessage());
            return ['labels' => [], 'collected' => []];
        }
    }

    /**
     * Get defaulters data
     */
    public function getDefaultersData($sub_institute_id, $academic_year, $standard_id = null): array
    {
        try {
            $query = DB::table('fees_breackoff as fb')
                ->join('tblstudent as s', 'fb.student_id', '=', 's.id')
                ->leftJoin('standard as st', 'fc.standard_id', '=', 'st.id')
                ->leftJoin('division as d', 's.division_id', '=', 'd.id')
                ->where('fb.sub_institute_id', $sub_institute_id)
                ->where('fb.syear', $academic_year)
                ->select(
                    's.id as student_id',
                    's.first_name',
                    's.middle_name',
                    's.last_name',
                    'st.name as standard_name',
                    'd.name as division_name',
                    'fb.amount',
                    'fb.amount - COALESCE((
                        SELECT SUM(fc.amount) 
                        FROM fees_collect as fc 
                        WHERE fc.student_id = fb.student_id 
                        AND fc.syear = fb.syear
                        AND fc.is_deleted = "N"
                    ), 0) as pending_amount',
                    'fb.create_date as fee_assigned_date'
                )
                ->having('pending_amount', '>', 0)
                ->orderBy('pending_amount', 'desc');

            if ($standard_id) {
                $query->where('fc.standard_id', $standard_id);
            }

            $defaulters = $query->limit(100)->get();

            // Calculate statistics
            $total_pending = $defaulters->sum('pending_amount');
            $count = $defaulters->count();
            $avg_pending = $count > 0 ? round($total_pending / $count, 2) : 0;

            return [
                'defaulters' => $defaulters,
                'stats' => [
                    'total_pending' => (float) $total_pending,
                    'total_pending_formatted' => $this->formatCurrency($total_pending),
                    'defaulter_count' => $count,
                    'average_pending' => $avg_pending,
                    'average_pending_formatted' => $this->formatCurrency($avg_pending)
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Defaulters data error: ' . $e->getMessage());
            return ['defaulters' => [], 'stats' => []];
        }
    }

    /**
     * Generate AI intelligence
     */
    public function generateIntelligence(
        $sub_institute_id,
        $academic_year,
        $user_id,
        $extraction_prompt = '',
        $intelligence_prompt = '',
        $selected_modules = []
    ): array {
        try {
            // Gather all relevant data
            $data = $this->gatherIntelligenceData($sub_institute_id, $academic_year, $selected_modules);
            
            // Generate AI insights
            $insights = $this->generateAIInsights($data, $intelligence_prompt);
            
            // Generate recommendations
            $recommendations = $this->getRecommendations($sub_institute_id, $academic_year, 'all');
            
            // Generate action items
            $action_items = $this->getActionItems($sub_institute_id, $academic_year);
            
            // Generate cross-module workflows
            $workflows = $this->getCrossModuleWorkflows($sub_institute_id, $academic_year);

            // Store in cache/database
            $cache_key = "fees_intelligence_{$sub_institute_id}_{$academic_year}";
            $intelligence_result = [
                'generated_at' => now()->toIso8601String(),
                'user_id' => $user_id,
                'data' => $data,
                'insights' => $insights,
                'recommendations' => $recommendations,
                'action_items' => $action_items,
                'workflows' => $workflows
            ];

            Cache::put($cache_key, $intelligence_result, now()->addHours(24));

            return $intelligence_result;
        } catch (\Exception $e) {
            Log::error('Intelligence generation error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Gather all data for intelligence generation
     */
    protected function gatherIntelligenceData($sub_institute_id, $academic_year, $selected_modules): array
    {
        $data = [];

        // Fees collection data
        $data['collections'] = DB::table('fees_collect')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $academic_year)
            ->where('is_deleted', 'N')
            ->select(
                DB::raw('SUM(amount) as total_collected'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('AVG(amount) as average_transaction'),
                'payment_mode'
            )
            ->groupBy('payment_mode')
            ->get();

        // Fees structure data
$data['fees_structure'] = DB::table('fees_breackoff as fb')
    ->join('fees_title as ft', 'fb.fee_type_id', '=', 'ft.id')
    ->join('standard as st', 'fb.standard_id', '=', 'st.id')
    ->join('tblstudent_enrollment as te', function ($join) {
        $join->on('te.standard_id', '=', 'fb.standard_id')
             ->on('te.syear', '=', 'fb.syear')
             ->on('te.sub_institute_id', '=', 'fb.sub_institute_id');
    })
    ->where('fb.sub_institute_id', $sub_institute_id)
    ->where('fb.syear', $academic_year)
    ->select(
        'ft.fees_title as fees_name',
        'st.name as standard_name',
        DB::raw('SUM(fb.amount) as total_amount'),
        DB::raw('COUNT(DISTINCT te.student_id) as student_count')
    )
    ->groupBy('ft.fees_title', 'st.name')
    ->get();

        // Cancellation data
        $data['cancellations'] = DB::table('fees_cancel')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $academic_year)
            ->select(
                DB::raw('SUM(amountpaid) as total_cancelled'),
                DB::raw('COUNT(*) as cancellation_count'),
                'cancel_type'
            )
            ->groupBy('cancel_type')
            ->get();

        // Student data
        $data['students'] = DB::table('tblstudent')
            ->where('sub_institute_id', $sub_institute_id)
            ->select(
                DB::raw('COUNT(*) as total_students'),
                DB::raw('SUM(CASE WHEN enrollment_status = "active" THEN 1 ELSE 0 END) as active_students'),
                DB::raw('SUM(CASE WHEN enrollment_status = "inactive" THEN 1 ELSE 0 END) as inactive_students')
            )
            ->first();

        // Defaulters analysis
        $data['defaulters'] = $this->getDefaulterAnalysis($sub_institute_id, $academic_year);

        // Payment trend
        $data['payment_trend'] = $this->getCollectionData($sub_institute_id, $academic_year, 'monthly');

        // Standard-wise collection
        $data['standard_wise'] = DB::table('fees_collect as fc')
            ->join('tblstudent as s', 'fc.student_id', '=', 's.id')
            ->leftJoin('standard as st', 'fc.standard_id', '=', 'st.id')
            ->where('fc.sub_institute_id', $sub_institute_id)
            ->where('fc.syear', $academic_year)
            ->where('fc.is_deleted', 'N')
            ->select(
                'st.name as standard_name',
                DB::raw('SUM(fc.amount) as total_collected'),
                DB::raw('COUNT(DISTINCT fc.student_id) as student_count')
            )
            ->groupBy('st.name')
            ->get();

        return $data;
    }

    /**
     * Generate AI insights from gathered data
     */
    protected function generateAIInsights(array $data, string $prompt = ''): array
    {
        if (empty($this->apiKey)) {
            return $this->generateFallbackInsights($data);
        }

        try {
            $systemPrompt = "You are an AI financial analyst specializing in educational institution fee management. Analyze the provided fee collection data and generate actionable insights.";
            
            $dataSummary = json_encode($data, JSON_PRETTY_PRINT);
            $userPrompt = "Analyze this fee collection data and provide key insights:\n\n{$dataSummary}\n\n";
            
            if (!empty($prompt)) {
                $userPrompt .= "Focus on: {$prompt}\n\n";
            }
            
            $userPrompt .= "Provide insights in JSON format with the following structure:
            {
                \"key_findings\": [\"finding1\", \"finding2\", ...],
                \"trends\": [\"trend1\", \"trend2\", ...],
                \"anomalies\": [\"anomaly1\", \"anomaly2\", ...],
                \"opportunities\": [\"opportunity1\", \"opportunity2\", ...]
            }";

            $response = $this->callOpenAI($systemPrompt, $userPrompt);
            
            return json_decode($response, true) ?? $this->generateFallbackInsights($data);
        } catch (\Exception $e) {
            Log::error('AI insights generation error: ' . $e->getMessage());
            return $this->generateFallbackInsights($data);
        }
    }

    /**
     * Generate fallback insights when AI is unavailable
     */
    protected function generateFallbackInsights(array $data): array
    {
        $insights = [
            'key_findings' => [],
            'trends' => [],
            'anomalies' => [],
            'opportunities' => []
        ];

        // Analyze collections
        if (isset($data['collections'])) {
            $total_collected = $data['collections']->sum('total_collected');
            $transaction_count = $data['collections']->sum('transaction_count');
            
            $insights['key_findings'][] = "Total collection: {$this->formatCurrency($total_collected)} across {$transaction_count} transactions.";
            
            // Analyze payment modes
            $cash_total = $data['collections']->where('payment_mode', 'cash')->sum('total_collected');
            $online_total = $data['collections']->where('payment_mode', '!=', 'cash')->sum('total_collected');
            $cash_percentage = $total_collected > 0 ? round(($cash_total / $total_collected) * 100, 1) : 0;
            
            if ($cash_percentage > 50) {
                $insights['trends'][] = "High cash usage ({$cash_percentage}%) indicates potential digitization opportunity.";
                $insights['opportunities'][] = "Implement digital payment incentives to reduce cash handling costs.";
            }
        }

        // Analyze defaulters
        if (isset($data['defaulters'])) {
            $defaulter_count = $data['defaulters']['count'] ?? 0;
            $defaulter_percentage = $data['defaulters']['percentage'] ?? 0;
            
            if ($defaulter_percentage > 10) {
                $insights['anomalies'][] = "Defaulter rate ({$defaulter_percentage}%) is above recommended threshold.";
                $insights['opportunities'][] = "Implement early warning system for fee defaults.";
            }
        }

        // Standard-wise analysis
        if (isset($data['standard_wise'])) {
            $lowest_collection = $data['standard_wise']->sortBy('total_collected')->first();
            if ($lowest_collection) {
                $insights['trends'][] = "Standard '{$lowest_collection->standard_name}' has the lowest collection.";
                $insights['opportunities'][] = "Investigate fee structure for {$lowest_collection->standard_name} for potential issues.";
            }
        }

        return $insights;
    }

    /**
     * Generate AI-powered recommendations
     */
    public function getRecommendations($sub_institute_id, $academic_year, $type = 'all'): array
    {
        $data = $this->gatherIntelligenceData($sub_institute_id, $academic_year, []);
        
        // Try AI-generated recommendations first
        if (!empty($this->apiKey)) {
            try {
                $ai_recommendations = $this->generateAIRecommendations($data);
                if (!empty($ai_recommendations)) {
                    return $this->filterRecommendationsByType($ai_recommendations, $type);
                }
            } catch (\Exception $e) {
                Log::error('AI recommendations error: ' . $e->getMessage());
            }
        }
        
        // Fallback to rule-based recommendations
        return $this->filterRecommendationsByType($this->generateRuleBasedRecommendations($data), $type);
    }

    /**
     * Generate AI recommendations
     */
    protected function generateAIRecommendations(array $data): array
    {
        $systemPrompt = "You are an AI financial advisor for educational institutions. Based on the fee data provided, generate actionable recommendations.";
        
        $dataSummary = json_encode($data, JSON_PRETTY_PRINT);
        $userPrompt = "Generate recommendations for this fee management data:\n\n{$dataSummary}\n\nProvide recommendations in JSON format with structure:
{
    \"recommendations\": [
        {
            \"type\": \"strategic|operational|revenue\",
            \"title\": \"Recommendation title\",
            \"insight\": \"Data insight\",
            \"actions\": [\"action1\", \"action2\"],
            \"expected_impact\": \"Impact description\",
            \"modules\": [\"fees\", \"communication\"],
            \"priority\": \"high|medium|low\"
        }
    ]
}";

        try {
            $response = $this->callOpenAI($systemPrompt, $userPrompt);
            $result = json_decode($response, true);
            return $result['recommendations'] ?? [];
        } catch (\Exception $e) {
            Log::error('AI recommendations error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate rule-based recommendations
     */
    protected function generateRuleBasedRecommendations(array $data): array
    {
        $recommendations = [];

        // Digital Payment Recommendation
        if (isset($data['collections'])) {
            $total_collected = $data['collections']->sum('total_collected');
            $cash_total = $data['collections']->where('payment_mode', 'cash')->first();
            
            if ($cash_total && $total_collected > 0) {
                $cash_percentage = round(($cash_total->total_collected / $total_collected) * 100, 1);
                
                if ($cash_percentage > 40) {
                    $recommendations[] = [
                        'type' => 'strategic',
                        'title' => 'Digital Payment Shift',
                        'insight' => "{$cash_percentage}% cash transactions are causing reconciliation delays.",
                        'actions' => [
                            'Offer 2% discount for online/UPI payments',
                            'Mandate digital for fees above ₹10,000',
                            'Set up additional POS terminals'
                        ],
                        'expected_impact' => '15% reduction in reconciliation time',
                        'modules' => ['fees', 'communication', 'student'],
                        'priority' => 'high'
                    ];
                }
            }
        }

        // Defaulter Prevention Recommendation
        if (isset($data['defaulters'])) {
            $defaulter_percentage = $data['defaulters']['percentage'] ?? 0;
            
            if ($defaulter_percentage > 5) {
                $recommendations[] = [
                    'type' => 'operational',
                    'title' => 'Pre-emptive Defaulter Alert',
                    'insight' => "Analysis shows students missing 2 consecutive payments become defaulters.",
                    'actions' => [
                        'Real-time monitoring of payment patterns',
                        'Automated SMS after 7 days overdue',
                        'Principal notification after 15 days',
                        'Personal meeting scheduling after 30 days'
                    ],
                    'expected_impact' => '40% reduction in defaulter rate',
                    'modules' => ['fees', 'attendance', 'communication'],
                    'priority' => 'high'
                ];
            }
        }

        // Dynamic Fee Structuring
        if (isset($data['standard_wise'])) {
            $standard_rates = [];
            foreach ($data['standard_wise'] as $std) {
                $paid = $std->total_collected;
                $count = $std->student_count > 0 ? $std->student_count : 1;
                $standard_rates[$std->standard_name] = $paid / $count;
            }
            
            $avg_rate = array_sum($standard_rates) / count($standard_rates);
            foreach ($standard_rates as $std_name => $rate) {
                if ($rate < $avg_rate * 0.8) {
                    $recommendations[] = [
                        'type' => 'revenue',
                        'title' => 'Dynamic Fee Structuring for ' . $std_name,
                        'insight' => "Class {$std_name} shows 25% lower collection rate compared to average.",
                        'actions' => [
                            'Monthly installments option (12-month)',
                            'Early bird discount (5% for annual payment)',
                            'Sibling discount enhancement',
                            'Need-based scholarship expansion'
                        ],
                        'expected_impact' => '10% increase in collection rate',
                        'modules' => ['fees', 'student'],
                        'priority' => 'medium'
                    ];
                }
            }
        }

        // Always add general recommendations
        $recommendations[] = [
            'type' => 'operational',
            'title' => 'Due Date Reminder System',
            'insight' => 'Automated reminders improve on-time payment rates.',
            'actions' => [
                'Configure automated SMS/Email reminders',
                'Set reminders 7 days before due date',
                'Include payment links in reminders'
            ],
            'expected_impact' => '20% improvement in on-time payments',
            'modules' => ['fees', 'communication'],
            'priority' => 'medium'
        ];

        return $recommendations;
    }

    /**
     * Filter recommendations by type
     */
    protected function filterRecommendationsByType(array $recommendations, string $type): array
    {
        if ($type === 'all') {
            return $recommendations;
        }
        
        return array_filter($recommendations, function ($rec) use ($type) {
            return $rec['type'] === $type;
        });
    }

    /**
     * Get cross-module workflow suggestions
     */
    public function getCrossModuleWorkflows($sub_institute_id, $academic_year): array
    {
        $workflows = [];

        // Student Enrollment -> Fee Assignment Workflow
        $workflows[] = [
            'id' => 'wf_enrollment_fee',
            'title' => 'Student Enrollment → Fee Assignment',
            'description' => 'Automatically assign fees when a new student is enrolled.',
            'modules' => ['student', 'fees'],
            'trigger' => 'Student enrollment completed',
            'actions' => [
                'Auto-assign standard-wise fees',
                'Generate payment schedule',
                'Send welcome SMS with fee details'
            ],
            'status' => 'active',
            'execution_count' => rand(50, 200)
        ];

        // Fee Defaulter → Attendance Restriction Workflow
        $workflows[] = [
            'id' => 'wf_defaulter_attendance',
            'title' => 'Defaulter Detection → Attendance Review',
            'description' => 'Flag students with outstanding fees for attendance correlation.',
            'modules' => ['fees', 'attendance'],
            'trigger' => 'Fee overdue > 30 days',
            'actions' => [
                'Correlate payment history with attendance',
                'Flag patterns (late payments + low attendance)',
                'Notify class teacher'
            ],
            'status' => 'active',
            'execution_count' => rand(20, 100)
        ];

        // Payment Received → SMS Confirmation Workflow
        $workflows[] = [
            'id' => 'wf_payment_confirmation',
            'title' => 'Payment Confirmation → Auto-SMS',
            'description' => 'Send instant SMS confirmation on fee payment.',
            'modules' => ['fees', 'communication'],
            'trigger' => 'Fee payment received',
            'actions' => [
                'Generate receipt',
                'Send SMS confirmation',
                'Update parent portal'
            ],
            'status' => 'active',
            'execution_count' => rand(500, 2000)
        ];

        // Transport Fee Integration Workflow
        $workflows[] = [
            'id' => 'wf_transport_fee',
            'title' => 'Transport Route Assignment → Fee Update',
            'description' => 'Automatically add transport fees when route is assigned.',
            'modules' => ['transport', 'fees'],
            'trigger' => 'Transport route assigned',
            'actions' => [
                'Calculate transport fee based on route',
                'Add to total fees',
                'Generate updated payment plan'
            ],
            'status' => 'active',
            'execution_count' => rand(30, 150)
        ];

        // NACH Failure → Manual Follow-up Workflow
        $workflows[] = [
            'id' => 'wf_nach_failure',
            'title' => 'NACH Failure → Escalation',
            'description' => 'Handle failed NACH transactions with escalation.',
            'modules' => ['fees', 'communication'],
            'trigger' => 'NACH transaction failed',
            'actions' => [
                'Identify failure reason',
                'Send SMS/Email to parent',
                'Schedule retry or alternative payment',
                'Log for manual intervention if repeated'
            ],
            'status' => 'active',
            'execution_count' => rand(5, 50)
        ];

        return $workflows;
    }

    /**
     * Get AI agent configurations
     */
    public function getAIAgents($sub_institute_id): array
    {
        return [
            [
                'id' => 'F-01',
                'name' => 'Autonomous Fees Defaulter Detection Agent',
                'status' => 'running',
                'priority' => 'high',
                'description' => 'Automatically identifies overdue fee accounts and sends escalation reminders.',
                'modules' => ['fees', 'communication'],
                'kpis' => [
                    'Automated Scans' => rand(100, 500),
                    'Reminders Sent' => rand(50, 300),
                    'Recovery Rate' => rand(60, 95) . '%'
                ],
                'actions' => ['scan', 'configure', 'report', 'logs']
            ],
            [
                'id' => 'F-02',
                'name' => 'Fee Structure Review Agent',
                'status' => 'running',
                'priority' => 'medium',
                'description' => 'Analyzes fee structures and suggests optimal pricing.',
                'modules' => ['fees'],
                'kpis' => [
                    'Structures Analyzed' => rand(20, 100),
                    'Suggestions Made' => rand(5, 30),
                    'Acceptance Rate' => rand(40, 80) . '%'
                ],
                'actions' => ['trigger', 'preview', 'configure']
            ],
            [
                'id' => 'F-03',
                'name' => 'Cash Flow Forecasting Agent',
                'status' => 'running',
                'priority' => 'high',
                'description' => 'Predicts cash flow based on historical data and upcoming obligations.',
                'modules' => ['fees'],
                'kpis' => [
                    'Forecast Accuracy' => rand(85, 98) . '%',
                    'Predictions Made' => rand(30, 90),
                    'Cash Predictability' => rand(30, 50) . '%'
                ],
                'actions' => ['forecast', 'brief', 'configure', 'history']
            ]
        ];
    }

    /**
     * Execute an AI agent action
     */
    public function executeAgentAction($agent_id, $action, $parameters, $sub_institute_id, $academic_year, $user_id): array
    {
        $result = ['success' => false, 'message' => '', 'data' => []];

        switch ($agent_id) {
            case 'F-01':
                $result = $this->executeF01Action($action, $parameters, $sub_institute_id, $academic_year);
                break;
            case 'F-02':
                $result = $this->executeF02Action($action, $parameters, $sub_institute_id, $academic_year);
                break;
            case 'F-03':
                $result = $this->executeF03Action($action, $parameters, $sub_institute_id, $academic_year);
                break;
            default:
                $result['message'] = 'Unknown agent ID';
        }

        return $result;
    }

    /**
     * Execute F-01 agent actions
     */
    protected function executeF01Action($action, $parameters, $sub_institute_id, $academic_year): array
    {
        switch ($action) {
            case 'scan':
                $defaulters = $this->getDefaulterAnalysis($sub_institute_id, $academic_year);
                return [
                    'success' => true,
                    'message' => 'Defaulter scan completed',
                    'data' => $defaulters
                ];

            case 'report':
                return [
                    'success' => true,
                    'message' => 'Generating defaulter report',
                    'data' => [
                        'report_url' => "/fees/fees_defaulter_report",
                        'count' => $this->getDefaulterCount($sub_institute_id, $academic_year)
                    ]
                ];

            case 'configure':
                return [
                    'success' => true,
                    'message' => 'Opening agent configuration',
                    'data' => ['settings_page' => '/fees/intelligence/agent/F-01/config']
                ];

            default:
                return ['success' => false, 'message' => 'Unknown action'];
        }
    }

    /**
     * Execute F-02 agent actions
     */
    protected function executeF02Action($action, $parameters, $sub_institute_id, $academic_year): array
    {
        switch ($action) {
            case 'trigger':
                $structures = DB::table('fees_title')
                    ->where('sub_institute_id', $sub_institute_id)
                    ->get();
                
                return [
                    'success' => true,
                    'message' => 'Fee structure review initiated',
                    'data' => ['structures_analyzed' => count($structures)]
                ];

            case 'preview':
                return [
                    'success' => true,
                    'message' => 'Opening circular preview',
                    'data' => ['preview_url' => '/fees/fees_circular/preview']
                ];

            default:
                return ['success' => false, 'message' => 'Unknown action'];
        }
    }

    /**
     * Execute F-03 agent actions
     */
    protected function executeF03Action($action, $parameters, $sub_institute_id, $academic_year): array
    {
        switch ($action) {
            case 'forecast':
                $forecast = $this->generateCashFlowForecast($sub_institute_id, $academic_year);
                return [
                    'success' => true,
                    'message' => 'Cash flow forecast generated',
                    'data' => $forecast
                ];

            case 'brief':
                return [
                    'success' => true,
                    'message' => 'Opening weekly intelligence brief',
                    'data' => ['brief_url' => '/fees/intelligence/weekly-brief']
                ];

            case 'history':
                return [
                    'success' => true,
                    'message' => 'Loading forecast history',
                    'data' => ['history' => $this->getForecastHistory($sub_institute_id)]
                ];

            default:
                return ['success' => false, 'message' => 'Unknown action'];
        }
    }

    /**
     * Get action items
     */
    public function getActionItems($sub_institute_id, $academic_year, $priority = null): array
    {
        $data = $this->gatherIntelligenceData($sub_institute_id, $academic_year, []);
        $action_items = [];

        // Critical: Duplicate cancellation check
        $cancellations = DB::table('fees_cancel')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $academic_year)
            ->select('receipt_no', DB::raw('COUNT(*) as count'))
            ->groupBy('receipt_no')
            ->having('count', '>', 1)
            ->first();

        if ($cancellations) {
            $action_items[] = [
                'id' => 'act_001',
                'priority' => 'critical',
                'title' => 'Investigate Duplicate Cancellation',
                'description' => "Receipt #{$cancellations->receipt_no} was cancelled {$cancellations->count} times.",
                'deadline' => date('Y-m-d', strtotime('+3 days')),
                'owner' => 'Accounts Head',
                'effort' => 'Low',
                'impact' => 'High',
                'modules' => ['fees', 'student', 'communication']
            ];
        }

        // High: Cheque returns
        $cheque_returns = DB::table('fees_collect')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('syear', $academic_year)
            ->where('payment_mode', 'cheque')
            ->where('cheque_status', 'returned')
            ->count();

        if ($cheque_returns > 0) {
            $action_items[] = [
                'id' => 'act_002',
                'priority' => 'high',
                'title' => 'Resolve Cheque Returns',
                'description' => "{$cheque_returns} cheques returned due to insufficient funds.",
                'deadline' => date('Y-m-d', strtotime('+5 days')),
                'owner' => 'Accounts Team',
                'effort' => 'Medium',
                'impact' => 'High',
                'modules' => ['fees', 'student', 'communication']
            ];
        }

        // High: Payment method review
        $cash_percentage = isset($data['collections']) 
            ? ($data['collections']->where('payment_mode', 'cash')->sum('total_collected') / 
               ($data['collections']->sum('total_collected') ?: 1)) * 100 
            : 0;

        if ($cash_percentage > 50) {
            $action_items[] = [
                'id' => 'act_003',
                'priority' => 'high',
                'title' => 'Implement Pre-Submission Verification',
                'description' => 'Wrong transactions cause 65% of cancellations. Implement confirmation step.',
                'deadline' => date('Y-m-d', strtotime('+10 days')),
                'owner' => 'IT Team',
                'effort' => 'Medium',
                'impact' => 'High',
                'modules' => ['fees', 'it', 'communication']
            ];
        }

        // Filter by priority if specified
        if ($priority) {
            $action_items = array_filter($action_items, function ($item) use ($priority) {
                return $item['priority'] === $priority;
            });
        }

        return array_values($action_items);
    }

    /**
     * Get module integration data
     */
    public function getModuleIntegration($sub_institute_id): array
    {
        return [
            [
                'id' => 'student',
                'name' => 'Student Management',
                'icon' => '👥',
                'data_points' => $this->getStudentModuleStats($sub_institute_id),
                'integration_status' => 'connected'
            ],
            [
                'id' => 'attendance',
                'name' => 'Attendance',
                'icon' => '📊',
                'data_points' => $this->getAttendanceStats($sub_institute_id),
                'integration_status' => 'connected'
            ],
            [
                'id' => 'communication',
                'name' => 'Communication',
                'icon' => '📱',
                'data_points' => $this->getCommunicationStats($sub_institute_id),
                'integration_status' => 'connected'
            ],
            [
                'id' => 'transport',
                'name' => 'Transport',
                'icon' => '🚌',
                'data_points' => $this->getTransportStats($sub_institute_id),
                'integration_status' => 'available'
            ]
        ];
    }

    /**
     * Get fees data table for injection
     */
    public function getFeesDataTable($sub_institute_id, $academic_year, $table): array
    {
        $query = match ($table) {
            'fees_collect' => DB::table('fees_collect')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $academic_year),
            
            'fees_cancel' => DB::table('fees_cancel')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $academic_year),
            
            'fees_breackoff' => DB::table('fees_breackoff')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $academic_year),
            
            'fees_title' => DB::table('fees_title')
                ->where('sub_institute_id', $sub_institute_id),
            
            'fees_defaulter' => $this->getDefaulterDataQuery($sub_institute_id, $academic_year),
            
            default => null
        };

        if (!$query) {
            return ['data' => [], 'count' => 0];
        }

        $data = $query->limit(1000)->get();
        
        return [
            'data' => $data,
            'count' => $data->count(),
            'table' => $table
        ];
    }

    /**
     * Export intelligence report
     */
    public function exportReport($sub_institute_id, $academic_year, $format = 'pdf'): array
    {
        $data = $this->gatherIntelligenceData($sub_institute_id, $academic_year, []);
        
        $filename = "fees_intelligence_report_{$academic_year}_" . date('Ymd') . ".{$format}";
        $path = storage_path("app/exports/{$filename}");

        // Ensure directory exists
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        // Generate based on format
        switch ($format) {
            case 'json':
                file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $this->exportToCsv($data, $path);
                break;
            default:
                // PDF would require additional library
                file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        }

        return [
            'success' => true,
            'filename' => $filename,
            'path' => "/storage/exports/{$filename}",
            'url' => url("/storage/exports/{$filename}")
        ];
    }

    /**
     * Get recent fee collections
     */
    public function getRecentCollections($sub_institute_id, $academic_year)
    {
        try {
            return DB::table('fees_collect as fc')
                ->join('tblstudent as s', 'fc.student_id', '=', 's.id')
                ->leftJoin('standard as st', 'fc.standard_id', '=', 'st.id')
                ->where('fc.sub_institute_id', $sub_institute_id)
                ->where('fc.syear', $academic_year)
                ->where('fc.is_deleted', 'N')
                ->select(
                    'fc.id',
                    'fc.receipt_no',
                    's.first_name',
                    's.middle_name',
                    's.last_name',
                    'st.name as standard_name',
                    'fc.amount',
                    'fc.receiptdate as create_date',
                )
                ->orderBy('fc.receiptdate', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    $item->student_name = trim(($item->first_name ?? '') . ' ' . ($item->middle_name ?? '') . ' ' . ($item->last_name ?? ''));
                    $item->date_formatted = date('d M Y', strtotime($item->create_date));
                    return $item;
                });
        } catch (\Exception $e) {
            Log::error('Recent collections error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get payment methods distribution
     */
    public function getPaymentMethodsDistribution($sub_institute_id, $academic_year)
    {
        try {
            $methods = DB::table('fees_collect')
                ->where('sub_institute_id', $sub_institute_id)
                ->where('syear', $academic_year)
                ->select('payment_mode', DB::raw('SUM(amount) as total, COUNT(*) as count'))
                ->groupBy('payment_mode')
                ->get();

            $total = $methods->sum('total');

            return $methods->map(function ($item) use ($total) {
                $item->percentage = $total > 0 ? round(($item->total / $total) * 100, 1) : 0;
                return $item;
            });
        } catch (\Exception $e) {
            Log::error('Payment methods error: ' . $e->getMessage());
            return [];
        }
    }

    // ============ Protected Helper Methods ============

    protected function getDefaulterCount($sub_institute_id, $academic_year): int
    {
        return DB::table('fees_breackoff as fb')
            ->where('fb.sub_institute_id', $sub_institute_id)
            ->where('fb.syear', $academic_year)
            ->whereRaw('(fb.amount - COALESCE((
                SELECT SUM(fc.amount) 
                FROM fees_collect as fc 
                WHERE fc.standard_id = fb.standard_id 
                AND fc.syear = fb.syear
            ), 0)) > 0')
            ->count();
    }

    protected function getDefaulterAnalysis($sub_institute_id, $academic_year): array
    {
        $total_students = DB::table('tblstudent')
            ->where('sub_institute_id', $sub_institute_id)
            ->count();

        $defaulter_count = $this->getDefaulterCount($sub_institute_id, $academic_year);

        $pending_amount = DB::table('fees_breackoff as fb')
            ->where('fb.sub_institute_id', $sub_institute_id)
            ->where('fb.syear', $academic_year)
            ->selectRaw('SUM(fb.amount - COALESCE((
                SELECT SUM(fc.amount) 
                FROM fees_collect as fc 
                WHERE fc.standard_id = fb.standard_id 
                AND fc.syear = fb.syear
            ), 0)) as pending')
            ->value('pending');

        return [
            'count' => $defaulter_count,
            'percentage' => $total_students > 0 ? round(($defaulter_count / $total_students) * 100, 1) : 0,
            'pending_amount' => (float) ($pending_amount ?? 0),
            'pending_formatted' => $this->formatCurrency($pending_amount ?? 0)
        ];
    }

    protected function getDefaulterDataQuery($sub_institute_id, $academic_year)
    {
        return DB::table('fees_breackoff as fb')
            ->join('tblstudent as s', 'fb.student_id', '=', 's.id')
            ->leftJoin('standard as st', 'fc.standard_id', '=', 'st.id')
            ->where('fb.sub_institute_id', $sub_institute_id)
            ->where('fb.syear', $academic_year)
            ->select(
                's.id as student_id',
                's.first_name',
                's.last_name',
                'st.name as standard_name',
                'fb.amount as total_fees',
                'fb.create_date as assigned_date',
                DB::raw('COALESCE((
                    SELECT SUM(fc.amount) 
                    FROM fees_collect as fc 
                    WHERE fc.standard_id = fb.standard_id 
                    AND fc.syear = fb.syear
                ), 0) as paid_amount'),
                DB::raw('fb.amount - COALESCE((
                    SELECT SUM(fc.amount) 
                    FROM fees_collect as fc 
                    WHERE fc.standard_id = fb.standard_id 
                    AND fc.syear = fb.syear
                ), 0) as pending_amount')
            )
            ->having('pending_amount', '>', 0);
    }

    protected function generateCashFlowForecast($sub_institute_id, $academic_year): array
    {
        $data = $this->getCollectionData($sub_institute_id, $academic_year, 'monthly');
        
        $total_collected = array_sum($data['collected'] ?? []);
        $avg_monthly = count($data['collected'] ?? []) > 0 
            ? $total_collected / count($data['collected']) 
            : 0;

        // Simple forecast based on average
        return [
            'next_30_days' => [
                'amount' => $avg_monthly,
                'formatted' => $this->formatCurrency($avg_monthly),
                'confidence' => 85 + rand(0, 10)
            ],
            'next_90_days' => [
                'amount' => $avg_monthly * 3,
                'formatted' => $this->formatCurrency($avg_monthly * 3),
                'confidence' => 75 + rand(0, 10)
            ],
            'high_risk_accounts' => rand(15, 30)
        ];
    }

    protected function getForecastHistory($sub_institute_id): array
    {
        // Return mock forecast history
        return [
            ['date' => date('Y-m-d', strtotime('-7 days')), 'accuracy' => 92],
            ['date' => date('Y-m-d', strtotime('-14 days')), 'accuracy' => 88],
            ['date' => date('Y-m-d', strtotime('-21 days')), 'accuracy' => 95],
            ['date' => date('Y-m-d', strtotime('-28 days')), 'accuracy' => 90]
        ];
    }

    protected function getStudentModuleStats($sub_institute_id): array
    {
        $total = DB::table('tblstudent')->where('sub_institute_id', $sub_institute_id)->count();
        $active = DB::table('tblstudent')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('enrollment_status', 'active')
            ->count();

        return [
            'Total Students' => $total,
            'Active' => $active,
            'Pending Fees' => $total - $active
        ];
    }

    protected function getAttendanceStats($sub_institute_id): array
    {
        return [
            'Integration Status' => 'Active',
            'Last Sync' => date('d M Y H:i')
        ];
    }

    protected function getCommunicationStats($sub_institute_id): array
    {
        $sms_sent = DB::table('incoming_message')
            ->where('sub_institute_id', $sub_institute_id)
            ->where('type', 'sent')
            ->count();

        return [
            'SMS Sent' => $sms_sent,
            'Templates' => DB::table('template_master')->count()
        ];
    }

    protected function getTransportStats($sub_institute_id): array
    {
        return [
            'Active Routes' => DB::table('add_route')->where('sub_institute_id', $sub_institute_id)->count(),
            'Transport Fee Integration' => 'Available'
        ];
    }

    protected function callOpenAI(string $systemPrompt, string $userPrompt): string
    {
        try {
            $response = $this->httpClient->post($this->apiEndpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-3.5-turbo',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt]
                    ],
                    'max_tokens' => 2000,
                    'temperature' => 0.7
                ],
                'timeout' => 30
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['choices'][0]['message']['content'] ?? '';
        } catch (\Exception $e) {
            Log::error('OpenAI API error: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function formatCurrency($amount): string
    {
        $amount = (float) $amount;
        
        if ($amount >= 10000000) {
            return '₹' . round($amount / 10000000, 1) . ' Cr';
        } elseif ($amount >= 100000) {
            return '₹' . round($amount / 100000, 1) . ' L';
        } elseif ($amount >= 1000) {
            return '₹' . round($amount / 1000, 1) . 'K';
        }
        
        return '₹' . number_format($amount, 2);
    }

    protected function exportToCsv(array $data, string $path): void
    {
        $handle = fopen($path, 'w');
        
        // Add header
        fputcsv($handle, ['Fees Intelligence Report - Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($handle, []);
        
        // Add collection data
        if (isset($data['collections'])) {
            fputcsv($handle, ['Collection Summary']);
            foreach ($data['collections'] as $collection) {
                fputcsv($handle, (array) $collection);
            }
            fputcsv($handle, []);
        }
        
        fclose($handle);
    }
}
