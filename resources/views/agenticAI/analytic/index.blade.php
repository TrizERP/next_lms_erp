@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<!-- Chart.js for charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Date formatting library -->
<script src="https://cdn.jsdelivr.net/npm/luxon@3.4.4/build/global/luxon.min.js"></script>

<style>
    .stat-card {
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        border: 1px solid #e5e7eb;
    }
    
    .tab-active {
        background-color: #3b82f6;
        color: white;
    }
    
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }
    
    .chart-tooltip {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 0.375rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        padding: 0.75rem;
        font-size: 0.875rem;
        pointer-events: none;
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid" style="background-color:#fff;">
        <div class="space-y-6 p-4">
            <!-- Header -->
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Performance Analytics</h1>
                <p class="text-gray-500">Track agent performance with cached daily summaries</p>
            </div>

            <!-- Summary Stats -->
            <div class="grid gap-4 md:grid-cols-4">
                <div class="stat-card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-gray-500">Total Runs</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                                <line x1="9" y1="9" x2="9.01" y2="9"></line>
                                <line x1="15" y1="9" x2="15.01" y2="9"></line>
                            </svg>
                        </div>
                        <div class="text-2xl font-bold text-gray-900" id="total-runs">0</div>
                        <p class="text-xs text-gray-500">Last 7 days</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-gray-500">Avg Success Rate</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin=" round" class="text-gray-400">
                                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                <polyline points="17 6 23 6 23 12"></polyline>
                            </svg>
                        </div>
                        <div class="text-2xl font-bold text-gray-900" id="avg-success-rate">0%</div>
                        <p class="text-xs text-gray-500">Across all agents</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-gray-500">Avg Duration</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                        </div>
                        <div class="text-2xl font-bold text-gray-900" id="avg-duration">0s</div>
                        <p class="text-xs text-gray-500">Per execution</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-gray-500">Total Cost</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                        </div>
                        <div class="text-2xl font-bold text-gray-900" id="total-cost">$0</div>
                        <p class="text-xs text-gray-500">Last 7 days</p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="space-y-4">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-4">
                        <button 
                            id="overview-tab"
                            data-tab="overview"
                            class="tab-btn py-2 px-4 border-b-2 font-medium text-sm focus:outline-none border-blue-600 text-blue-600"
                        >
                            Overview
                        </button>
                        <button 
                            id="compare-tab"
                            data-tab="compare"
                            class="tab-btn py-2 px-4 border-b-2 font-medium text-sm focus:outline-none border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        >
                            Compare Agents
                        </button>
                    </nav>
                </div>

                <!-- Overview Tab Content -->
                <div id="overview-content" class="tab-content space-y-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Success Rate Trend Card -->
                        <div class="stat-card">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Success Rate Trend</h3>
                                <p class="text-sm text-gray-500 mb-4">Daily success rate percentage</p>
                                <div class="chart-container">
                                    <canvas id="success-rate-chart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Average Duration Card -->
                        <div class="stat-card">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Average Duration</h3>
                                <p class="text-sm text-gray-500 mb-4">Daily average execution time (seconds)</p>
                                <div class="chart-container">
                                    <canvas id="duration-chart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Success vs Failure Card -->
                        <div class="stat-card">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Success vs Failure Count</h3>
                                <p class="text-sm text-gray-500 mb-4">Daily execution outcomes</p>
                                <div class="chart-container">
                                    <canvas id="success-failure-chart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Cost Analysis Card -->
                        <div class="stat-card">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Cost Analysis</h3>
                                <p class="text-sm text-gray-500 mb-4">Daily operational costs ($)</p>
                                <div class="chart-container">
                                    <canvas id="cost-chart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compare Tab Content -->
                <div id="compare-content" class="tab-content space-y-6 hidden">
                    <!-- Agent Success Rate Comparison -->
                    <div class="stat-card">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Agent Success Rate Comparison</h3>
                            <p class="text-sm text-gray-500 mb-4">Compare success rates across all agents over time</p>
                            <div class="chart-container" style="height: 400px;">
                                <canvas id="agent-success-chart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Agent Execution Volume -->
                    <div class="stat-card">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Agent Execution Volume</h3>
                            <p class="text-sm text-gray-500 mb-4">Stacked comparison of daily runs by agent</p>
                            <div class="chart-container" style="height: 400px;">
                                <canvas id="agent-runs-chart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Agent Performance Cards -->
                    <div id="agent-cards-container" class="grid gap-6 md:grid-cols-2">
                        <!-- Agent cards will be inserted here by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Global variables
    let activeTab = 'overview';
    let dailySummaries = [];
    let mockAgentPerformanceData = {};
    let chartInstances = {};
    
    // Mock data (you can replace with API calls)
    const mockPerformanceData = [
        { date: '2024-01-01', successCount: 120, failureCount: 10, totalRuns: 130, avgDuration: 2.5, successRate: 92.3, totalCost: 12.50 },
        { date: '2024-01-02', successCount: 135, failureCount: 8, totalRuns: 143, avgDuration: 2.3, successRate: 94.4, totalCost: 14.20 },
        { date: '2024-01-03', successCount: 110, failureCount: 15, totalRuns: 125, avgDuration: 2.8, successRate: 88.0, totalCost: 11.80 },
        { date: '2024-01-04', successCount: 150, failureCount: 5, totalRuns: 155, avgDuration: 2.1, successRate: 96.8, totalCost: 15.50 },
        { date: '2024-01-05', successCount: 140, failureCount: 12, totalRuns: 152, avgDuration: 2.4, successRate: 92.1, totalCost: 14.80 },
        { date: '2024-01-06', successCount: 125, failureCount: 7, totalRuns: 132, avgDuration: 2.6, successRate: 94.7, totalCost: 13.20 },
        { date: '2024-01-07', successCount: 130, failureCount: 9, totalRuns: 139, avgDuration: 2.2, successRate: 93.5, totalCost: 13.90 },
    ];
    
    // Mock agent performance data
    const initMockAgentData = () => {
        mockAgentPerformanceData = {
            'Customer Support Agent': [
                { date: '2024-01-01', totalRuns: 45, successRate: 91.1, avgDuration: 2.3 },
                { date: '2024-01-02', totalRuns: 48, successRate: 93.8, avgDuration: 2.1 },
                { date: '2024-01-03', totalRuns: 42, successRate: 88.1, avgDuration: 2.5 },
                { date: '2024-01-04', totalRuns: 52, successRate: 96.2, avgDuration: 1.9 },
                { date: '2024-01-05', totalRuns: 50, successRate: 92.0, avgDuration: 2.2 },
                { date: '2024-01-06', totalRuns: 44, successRate: 93.2, avgDuration: 2.4 },
                { date: '2024-01-07', totalRuns: 46, successRate: 93.5, avgDuration: 2.0 },
            ],
            'Data Analyst Agent': [
                { date: '2024-01-01', totalRuns: 35, successRate: 94.3, avgDuration: 3.2 },
                { date: '2024-01-02', totalRuns: 38, successRate: 95.8, avgDuration: 3.0 },
                { date: '2024-01-03', totalRuns: 33, successRate: 90.9, avgDuration: 3.5 },
                { date: '2024-01-04', totalRuns: 40, successRate: 97.5, avgDuration: 2.8 },
                { date: '2024-01-05', totalRuns: 37, successRate: 94.6, avgDuration: 3.1 },
                { date: '2024-01-06', totalRuns: 34, successRate: 94.1, avgDuration: 3.3 },
                { date: '2024-01-07', totalRuns: 36, successRate: 94.4, avgDuration: 2.9 },
            ],
            'Content Writer Agent': [
                { date: '2024-01-01', totalRuns: 25, successRate: 88.0, avgDuration: 1.8 },
                { date: '2024-01-02', totalRuns: 28, successRate: 92.9, avgDuration: 1.6 },
                { date: '2024-01-03', totalRuns: 23, successRate: 82.6, avgDuration: 2.1 },
                { date: '2024-01-04', totalRuns: 30, successRate: 96.7, avgDuration: 1.5 },
                { date: '2024-01-05', totalRuns: 27, successRate: 88.9, avgDuration: 1.7 },
                { date: '2024-01-06', totalRuns: 26, successRate: 92.3, avgDuration: 1.9 },
                { date: '2024-01-07', totalRuns: 29, successRate: 93.1, avgDuration: 1.6 },
            ],
            'Code Review Agent': [
                { date: '2024-01-01', totalRuns: 15, successRate: 93.3, avgDuration: 4.2 },
                { date: '2024-01-02', totalRuns: 17, successRate: 94.1, avgDuration: 3.9 },
                { date: '2024-01-03', totalRuns: 14, successRate: 85.7, avgDuration: 4.8 },
                { date: '2024-01-04', totalRuns: 18, successRate: 100.0, avgDuration: 3.5 },
                { date: '2024-01-05', totalRuns: 16, successRate: 93.8, avgDuration: 4.1 },
                { date: '2024-01-06', totalRuns: 15, successRate: 93.3, avgDuration: 4.3 },
                { date: '2024-01-07', totalRuns: 17, successRate: 94.1, avgDuration: 3.8 },
            ],
        };
    };
    
    // Color palette for charts
    const colors = {
        success: '#10b981',
        failure: '#ef4444',
        info: '#3b82f6',
        warning: '#f59e0b',
        chart1: '#8b5cf6', // Customer Support Agent
        chart2: '#06b6d4', // Data Analyst Agent
        chart3: '#84cc16', // Content Writer Agent
        chart4: '#f97316', // Code Review Agent
    };
    
    // Format date for display
    function formatDate(dateString) {
        return luxon.DateTime.fromISO(dateString).toFormat('MMM dd');
    }
    
    // Compute daily summaries from mock data
    function computeDailySummaries() {
        return mockPerformanceData.map(day => ({
            date: formatDate(day.date),
            originalDate: day.date,
            successCount: day.successCount,
            failureCount: day.failureCount,
            totalRuns: day.totalRuns,
            avgDuration: day.avgDuration,
            successRate: day.successRate,
            totalCost: day.totalCost,
        }));
    }
    
    // Compute aggregate stats
    function computeAggregateStats(summaries) {
        const totalRuns = summaries.reduce((sum, day) => sum + day.totalRuns, 0);
        const avgSuccessRate = (summaries.reduce((sum, day) => sum + day.successRate, 0) / summaries.length).toFixed(1);
        const avgDuration = (summaries.reduce((sum, day) => sum + day.avgDuration, 0) / summaries.length).toFixed(1);
        const totalCost = summaries.reduce((sum, day) => sum + day.totalCost, 0).toFixed(2);
        
        return { totalRuns, avgSuccessRate, avgDuration, totalCost };
    }
    
    // Compute agent comparison data
    function computeAgentComparison() {
        const allDates = Array.from(
            new Set(
                Object.values(mockAgentPerformanceData)
                    .flat()
                    .map(d => d.date)
            )
        ).sort();
        
        return allDates.map(date => {
            const entry = { date: formatDate(date) };
            Object.keys(mockAgentPerformanceData).forEach(agentName => {
                const dayData = mockAgentPerformanceData[agentName].find(d => d.date === date);
                entry[agentName] = dayData ? dayData.successRate : null;
            });
            return entry;
        });
    }
    
    // Compute runs comparison data
    function computeRunsComparisonData() {
        const allDates = Array.from(
            new Set(
                Object.values(mockAgentPerformanceData)
                    .flat()
                    .map(d => d.date)
            )
        ).sort();
        
        return allDates.map(date => {
            const entry = { date: formatDate(date) };
            Object.keys(mockAgentPerformanceData).forEach(agentName => {
                const dayData = mockAgentPerformanceData[agentName].find(d => d.date === date);
                entry[agentName] = dayData ? dayData.totalRuns : 0;
            });
            return entry;
        });
    }
    
    // Update summary stats
    function updateSummaryStats() {
        const stats = computeAggregateStats(dailySummaries);
        
        document.getElementById('total-runs').textContent = stats.totalRuns.toLocaleString();
        document.getElementById('avg-success-rate').textContent = `${stats.avgSuccessRate}%`;
        document.getElementById('avg-duration').textContent = `${stats.avgDuration}s`;
        document.getElementById('total-cost').textContent = `$${stats.totalCost}`;
    }
    
    // Initialize charts
    function initializeCharts() {
        // Destroy existing charts
        Object.values(chartInstances).forEach(chart => {
            if (chart) chart.destroy();
        });
        
        // Success Rate Trend Chart (Area)
        const successRateCtx = document.getElementById('success-rate-chart').getContext('2d');
        chartInstances.successRate = new Chart(successRateCtx, {
            type: 'line',
            data: {
                labels: dailySummaries.map(d => d.date),
                datasets: [{
                    label: 'Success Rate',
                    data: dailySummaries.map(d => d.successRate),
                    borderColor: colors.success,
                    backgroundColor: `${colors.success}20`,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Success Rate: ${context.parsed.y}%`;
                            }
                        }
                    },
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: true, color: 'rgba(0,0,0,0.05)' }
                    },
                    y: {
                        beginAtZero: false,
                        min: 85,
                        max: 100,
                        grid: { display: true, color: 'rgba(0,0,0,0.05)' },
                        ticks: { callback: value => `${value}%` }
                    }
                }
            }
        });
        
        // Average Duration Chart (Bar)
        const durationCtx = document.getElementById('duration-chart').getContext('2d');
        chartInstances.duration = new Chart(durationCtx, {
            type: 'bar',
            data: {
                labels: dailySummaries.map(d => d.date),
                datasets: [{
                    label: 'Avg Duration',
                    data: dailySummaries.map(d => d.avgDuration),
                    backgroundColor: colors.info,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Avg Duration: ${context.parsed.y}s`;
                            }
                        }
                    },
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: true, color: 'rgba(0,0,0,0.05)' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { display: true, color: 'rgba(0,0,0,0.05)' },
                        ticks: { callback: value => `${value}s` }
                    }
                }
            }
        });
        
        // Success vs Failure Chart (Stacked Bar)
        const successFailureCtx = document.getElementById('success-failure-chart').getContext('2d');
        chartInstances.successFailure = new Chart(successFailureCtx, {
            type: 'bar',
            data: {
                labels: dailySummaries.map(d => d.date),
                datasets: [
                    {
                        label: 'Success',
                        data: dailySummaries.map(d => d.successCount),
                        backgroundColor: colors.success,
                        stack: 'stack1',
                    },
                    {
                        label: 'Failure',
                        data: dailySummaries.map(d => d.failureCount),
                        backgroundColor: colors.failure,
                        stack: 'stack1',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ${context.parsed.y}`;
                            }
                        }
                    }
                },
                scales: {
                    x: { 
                        stacked: true,
                        grid: { display: true, color: 'rgba(0,0,0,0.05)' }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        grid: { display: true, color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });
        
        // Cost Analysis Chart (Line)
        const costCtx = document.getElementById('cost-chart').getContext('2d');
        chartInstances.cost = new Chart(costCtx, {
            type: 'line',
            data: {
                labels: dailySummaries.map(d => d.date),
                datasets: [{
                    label: 'Total Cost',
                    data: dailySummaries.map(d => d.totalCost),
                    borderColor: colors.warning,
                    backgroundColor: `${colors.warning}20`,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Total Cost: $${context.parsed.y}`;
                            }
                        }
                    },
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: true, color: 'rgba(0,0,0,0.05)' }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { display: true, color: 'rgba(0,0,0,0.05)' },
                        ticks: {
                            callback: value => `$${value}`
                        }
                    }
                }
            }
        });
        
        // Initialize compare tab charts (hidden initially)
        initializeCompareCharts();
    }
    
    // Initialize compare tab charts
    function initializeCompareCharts() {
        const comparisonData = computeAgentComparison();
        const runsComparisonData = computeRunsComparisonData();
        
        // Agent Success Rate Comparison Chart
        const agentSuccessCtx = document.getElementById('agent-success-chart').getContext('2d');
        chartInstances.agentSuccess = new Chart(agentSuccessCtx, {
            type: 'line',
            data: {
                labels: comparisonData.map(d => d.date),
                datasets: Object.keys(mockAgentPerformanceData).map((agentName, index) => ({
                    label: agentName,
                    data: comparisonData.map(d => d[agentName]),
                    borderColor: colors[`chart${index + 1}`],
                    backgroundColor: `${colors[`chart${index + 1}`]}20`,
                    fill: false,
                    tension: 0.4,
                    borderWidth: 2,
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ${context.parsed.y}%`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: true, color: 'rgba(0,0,0,0.05)' }
                    },
                    y: {
                        beginAtZero: false,
                        min: 80,
                        max: 100,
                        grid: { display: true, color: 'rgba(0,0,0,0.05)' },
                        ticks: { callback: value => `${value}%` }
                    }
                }
            }
        });
        
        // Agent Execution Volume Chart
        const agentRunsCtx = document.getElementById('agent-runs-chart').getContext('2d');
        chartInstances.agentRuns = new Chart(agentRunsCtx, {
            type: 'bar',
            data: {
                labels: runsComparisonData.map(d => d.date),
                datasets: Object.keys(mockAgentPerformanceData).map((agentName, index) => ({
                    label: agentName,
                    data: runsComparisonData.map(d => d[agentName]),
                    backgroundColor: colors[`chart${index + 1}`],
                    stack: 'stack1',
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ${context.parsed.y}`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: { display: true, color: 'rgba(0,0,0,0.05)' }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        grid: { display: true, color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });
        
        // Create agent performance cards
        createAgentPerformanceCards();
    }
    
    // Create agent performance cards
    function createAgentPerformanceCards() {
        const container = document.getElementById('agent-cards-container');
        container.innerHTML = '';
        
        Object.entries(mockAgentPerformanceData).forEach(([agentName, data]) => {
            const avgSuccessRate = (data.reduce((sum, d) => sum + d.successRate, 0) / data.length).toFixed(1);
            const totalRuns = data.reduce((sum, d) => sum + d.totalRuns, 0);
            const avgDuration = (data.reduce((sum, d) => sum + d.avgDuration, 0) / data.length).toFixed(1);
            
            const card = `
                <div class="stat-card">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">${agentName}</h3>
                        <p class="text-sm text-gray-500 mb-4">7-day performance summary</p>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-500">Avg Success Rate</span>
                                <span class="text-sm font-semibold">${avgSuccessRate}%</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-500">Total Runs</span>
                                <span class="text-sm font-semibold">${totalRuns}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-500">Avg Duration</span>
                                <span class="text-sm font-semibold">${avgDuration}s</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', card);
        });
    }
    
    // Switch tabs
    function switchTab(tabName) {
        activeTab = tabName;
        
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            if (btn.dataset.tab === tabName) {
                btn.classList.add('border-blue-600', 'text-blue-600');
                btn.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            } else {
                btn.classList.remove('border-blue-600', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300');
            }
        });
        
        // Show/hide content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        
        if (tabName === 'overview') {
            document.getElementById('overview-content').classList.remove('hidden');
        } else if (tabName === 'compare') {
            document.getElementById('compare-content').classList.remove('hidden');
        }
    }
    
    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Setup event listeners
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                switchTab(this.dataset.tab);
            });
        });
        
        // Initialize data
        initMockAgentData();
        dailySummaries = computeDailySummaries();
        
        // Update UI
        updateSummaryStats();
        initializeCharts();
    });
</script>

@include('includes.footerJs')
@include('includes.footer')