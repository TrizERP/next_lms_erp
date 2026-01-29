@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<!-- Chart.js for charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .stat-card {
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        border: 1px solid #e5e7eb;
        position: relative;
        overflow: hidden;
    }
    
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .status-active {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    .status-idle {
        background-color: #f3f4f6;
        color: #6b7280;
    }
    
    .status-draft {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    .status-deployed {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .status-unknown {
        background-color: #f3f4f6;
        color: #6b7280;
    }
    
    .sparkline-container {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 40px;
        margin: 0 -1.5rem -1rem;
    }
    
    .fade-in {
        animation: fadeIn 0.3s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="space-y-8 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Agent Dashboard</h1>
                    <p class="text-gray-500">Manage and monitor your AI agents</p>
                </div>
                <a href="{{ route('create_agent.index') }}" class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Create Agent
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <!-- Total Agents Card -->
                <div class="stat-card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-gray-500">Total Agents</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </div>
                        <div class="text-2xl font-bold text-gray-900" id="total-agents">0</div>
                        <p class="text-xs text-gray-500" id="active-agents-text">0 active</p>
                    </div>
                    <div class="sparkline-container">
                        <canvas id="agents-sparkline"></canvas>
                    </div>
                </div>

                <!-- Total Runs Card -->
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
                        <p class="text-xs text-gray-500">Across all agents</p>
                    </div>
                    <div class="sparkline-container">
                        <canvas id="runs-sparkline"></canvas>
                    </div>
                </div>

                <!-- Avg Success Rate Card -->
                <div class="stat-card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-gray-500">Avg Success Rate</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400">
                                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                <polyline points="17 6 23 6 23 12"></polyline>
                            </svg>
                        </div>
                        <div class="text-2xl font-bold text-gray-900" id="avg-success-rate">0%</div>
                        <p class="text-xs text-green-600">+2.5% from last week</p>
                    </div>
                    <div class="sparkline-container">
                        <canvas id="success-sparkline"></canvas>
                    </div>
                </div>

                <!-- Daily Cost Card -->
                <div class="stat-card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-gray-500">Daily Cost</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400">
                                <polyline points="13 3 13 10 19 10"></polyline>
                                <polyline points="23 3 23 10 17 10"></polyline>
                                <polyline points="13 14 13 21 19 21"></polyline>
                                <polyline points="23 14 23 21 17 21"></polyline>
                            </svg>
                        </div>
                        <div class="text-2xl font-bold text-gray-900" id="daily-cost">$0.00</div>
                        <p class="text-xs text-gray-500">7-day trend</p>
                    </div>
                    <div class="sparkline-container">
                        <canvas id="cost-sparkline"></canvas>
                    </div>
                </div>
            </div>

            <!-- Agents List -->
            <div class="stat-card">
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Agents</h2>
                    <p class="text-gray-500 mb-6">View and manage your AI agents</p>
                    
                    <!-- Search and Filter -->
                    <div class="flex items-center gap-2 mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <input 
                            type="text" 
                            id="agent-search"
                            placeholder="Search agents..."
                            class="max-w-sm px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                        <select 
                            id="status-filter"
                            class="w-32 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="all">All</option>
                            <option value="draft">Draft</option>
                            <option value="deployed">Deployed</option>
                        </select>
                    </div>
                    
                    <!-- Agents List -->
                    <div class="space-y-3" id="agents-container">
                        <div class="text-center py-8">
                            <p class="text-gray-500">Loading agents...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Global variables
    let allAgents = [];
    let runs = [];
    let stats = {
        totalAgents: 0,
        activeAgents: 0,
        totalRuns: 0,
        avgSuccessRate: 0
    };
    
    // Status badge component
    function StatusBadge({ status }) {
        const statusConfig = {
            'active': { class: 'status-active', label: 'Active' },
            'idle': { class: 'status-idle', label: 'Idle' },
            'draft': { class: 'status-draft', label: 'Draft' },
            'deployed': { class: 'status-deployed', label: 'Deployed' },
            'unknown': { class: 'status-unknown', label: 'Unknown' }
        };
        
        const config = statusConfig[status] || statusConfig.unknown;
        
        return `
            <span class="badge ${config.class}">
                ${config.label}
            </span>
        `;
    }
    
    // Get agent runs count
    function getAgentRunsCount(agentId) {
        return runs.filter(run => run.agent_id === agentId).length;
    }
    
    // Get agent name by ID
    function getAgentName(agentId) {
        const agent = allAgents.find(a => a.id === agentId);
        return agent ? agent.name : 'Unknown Agent';
    }
    
    // Format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US');
    }
    
    // Create agent card
    function createAgentCard(agent, index) {
        const runsCount = getAgentRunsCount(agent.id);
        
        return `
            <div class="agent-card fade-in flex items-center justify-between rounded-lg border border-gray-200 p-4 transition-all hover:bg-gray-50 hover:shadow-md cursor-pointer"
                 style="animation-delay: ${index * 50}ms"
                 onclick="window.location.href='/content/AgenticAI/AgentDetail?id=${agent.id}'">
                <div class="space-y-1">
                    <div class="flex items-center gap-3">
                        <h3 class="font-semibold text-gray-900">${agent.name}</h3>
                        ${StatusBadge({ status: agent.status })}
                    </div>
                    <p class="text-sm text-gray-500">${agent.description}</p>
                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <span>Model: ${agent.model}</span>
                        <span>•</span>
                        <span>${runsCount} runs</span>
                        <span>•</span>
                        <span>${agent.successRate}% success</span>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <button class="deploy-btn flex items-center gap-2 px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            data-agent-id="${agent.id}"
                            data-current-status="${agent.status}"
                            onclick="event.stopPropagation(); updateAgentStatus('${agent.id}', '${agent.status}')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        ${agent.status === 'deployed' ? 'Undeploy' : 'Deploy'}
                    </button>
                    <div class="text-right text-sm text-gray-500">
                        <p>Last run</p>
                        <p class="font-medium text-gray-900">
                            ${formatDate(agent.lastRun)}
                        </p>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Update agent status
    async function updateAgentStatus(agentId, currentStatus) {
        const newStatus = currentStatus === 'deployed' ? 'draft' : 'deployed';
        const apiStatus = newStatus === 'deployed' ? 'deployed' : 'draft';
        
        try {
            const response = await fetch(`https://trizk-12-agenticai.hf.space/agents/${agentId}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: apiStatus }),
            });
            
            if (response.ok) {
                // Update local agent status
                const agentIndex = allAgents.findIndex(a => a.id === agentId);
                if (agentIndex !== -1) {
                    allAgents[agentIndex].status = newStatus;
                    updateStats();
                    filterAgents();
                }
            } else {
                console.error('Failed to update status');
                alert('Failed to update agent status');
            }
        } catch (error) {
            console.error('Error updating status:', error);
            alert('Error updating agent status');
        }
    }
    
    // Update stats display
    function updateStats() {
        stats.totalAgents = allAgents.length;
        stats.activeAgents = allAgents.filter(a => a.status === 'deployed').length;
        stats.totalRuns = runs.length;
        stats.avgSuccessRate = allAgents.length > 0 
            ? (allAgents.reduce((sum, a) => sum + a.successRate, 0) / allAgents.length).toFixed(1)
            : 0;
        
        // Update DOM
        document.getElementById('total-agents').textContent = stats.totalAgents;
        document.getElementById('active-agents-text').textContent = `${stats.activeAgents} active`;
        document.getElementById('total-runs').textContent = stats.totalRuns.toLocaleString();
        document.getElementById('avg-success-rate').textContent = `${stats.avgSuccessRate}%`;
        document.getElementById('daily-cost').textContent = `$${12.50.toFixed(2)}`; // Mock cost
    }
    
    // Initialize sparkline charts
    function initializeSparklines() {
        // Mock data for sparklines
        const sparklineData = {
            agents: [2, 3, 3, 4, 4, 4, 4],
            runs: [120, 135, 110, 150, 140, 125, 130],
            success: [92.3, 94.4, 88.0, 96.8, 92.1, 94.7, 93.5],
            cost: [12.50, 14.20, 11.80, 15.50, 14.80, 13.20, 13.90]
        };
        
        // Create sparkline charts
        createSparkline('agents-sparkline', sparklineData.agents, '#3b82f6');
        createSparkline('runs-sparkline', sparklineData.runs, '#8b5cf6');
        createSparkline('success-sparkline', sparklineData.success, '#10b981');
        createSparkline('cost-sparkline', sparklineData.cost, '#f59e0b');
    }
    
    // Create a sparkline chart
    function createSparkline(canvasId, data, color) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.map((_, i) => i),
                datasets: [{
                    data: data,
                    borderColor: color,
                    backgroundColor: `${color}20`,
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: false },
                    y: { display: false }
                },
                interaction: { intersect: false },
                elements: {
                    line: { tension: 0.4 }
                }
            }
        });
    }
    
    // Fetch agents from API
    async function fetchAgents() {
        try {
            const response = await fetch('https://pariharajit6348-agenticai.hf.space/agents');
            const data = await response.json();
            
            allAgents = Array.isArray(data) ? data.map(agent => ({
                id: agent.id?.toString() || "",
                name: agent.name || "",
                description: agent.description || "",
                module: agent.module || "",
                submodule: agent.sub_module || "",
                role: "agent",
                status: agent.status || 'draft',
                model: "gpt-4",
                temperature: agent.temperature || 0.7,
                maxTokens: agent.max_tokens || 1024,
                systemPrompt: agent.system_prompt || "",
                tools: [],
                createdAt: agent.created_at || new Date().toISOString(),
                lastRun: agent.created_at || new Date().toISOString(),
                totalRuns: 0,
                successRate: Math.floor(Math.random() * 15) + 85, // Mock success rate
            })) : [];
            
            updateStats();
            filterAgents();
        } catch (error) {
            console.error('Error fetching agents:', error);
            document.getElementById('agents-container').innerHTML = `
                <div class="text-center py-8">
                    <p class="text-red-500">Failed to load agents</p>
                </div>
            `;
        }
    }
    
    // Fetch runs from API
    async function fetchRuns() {
        try {
            const response = await fetch('https://pariharajit6348-agenticai.hf.space/runs');
            const data = await response.json();
            runs = Array.isArray(data) ? data : [];
            updateStats();
        } catch (error) {
            console.error('Error fetching runs:', error);
        }
    }
    
    // Filter agents based on search and status
    function filterAgents() {
        const searchQuery = document.getElementById('agent-search').value.toLowerCase();
        const statusFilter = document.getElementById('status-filter').value;
        
        let filtered = allAgents;
        
        // Apply search filter
        if (searchQuery) {
            filtered = filtered.filter(agent => 
                agent.name.toLowerCase().includes(searchQuery) ||
                agent.description.toLowerCase().includes(searchQuery)
            );
        }
        
        // Apply status filter
        if (statusFilter !== 'all') {
            filtered = filtered.filter(agent => agent.status === statusFilter);
        }
        
        // Display filtered agents
        const container = document.getElementById('agents-container');
        
        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8">
                    <p class="text-gray-500">No agents found</p>
                </div>
            `;
        } else {
            container.innerHTML = filtered.map((agent, index) => createAgentCard(agent, index)).join('');
        }
    }
    
    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize sparklines
        initializeSparklines();
        
        // Fetch data
        fetchAgents();
        fetchRuns();
        
        // Setup event listeners
        document.getElementById('agent-search').addEventListener('input', filterAgents);
        document.getElementById('status-filter').addEventListener('change', filterAgents);
    });
</script>

@include('includes.footerJs')
@include('includes.footer')