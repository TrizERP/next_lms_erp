@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<style>
    .tab-active {
        background-color: #3b82f6;
        color: white;
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .status-success {
        background-color: #d1fae5;
        color: #065f46;
    }
    
    .status-error {
        background-color: #fee2e2;
        color: #991b1b;
    }
    
    .status-training {
        background-color: #fef3c7;
        color: #92400e;
    }
    
    .status-completed {
        background-color: #dbeafe;
        color: #1e40af;
    }
    
    .status-pending {
        background-color: #f3f4f6;
        color: #6b7280;
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid" style="background-color:#fff;">
        <div class="space-y-4 p-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Run Logs & Traces</h1>
                <p class="text-gray-500">Monitor agent execution and performance</p>
            </div>

            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input 
                    type="text" 
                    id="search-input"
                    placeholder="Search runs..."
                    class="max-w-sm px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
            </div>

            <!-- Tabs -->
            <div class="space-y-4">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-4">
                        <button 
                            id="runs-tab"
                            data-tab="runs"
                            class="tab-btn py-2 px-4 border-b-2 font-medium text-sm focus:outline-none border-blue-600 text-blue-600"
                        >
                            Agent Runs
                        </button>
                        <button 
                            id="logs-tab"
                            data-tab="logs"
                            class="tab-btn py-2 px-4 border-b-2 font-medium text-sm focus:outline-none border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300"
                        >
                            Detailed Logs
                        </button>
                    </nav>
                </div>

                <!-- Runs Tab Content -->
                <div id="runs-content" class="tab-content space-y-4">
                    <div id="runs-loading" class="text-gray-500">Loading runs...</div>
                    <div id="runs-container" class="space-y-4 hidden"></div>
                    <div id="no-runs" class="text-gray-500 hidden">No runs available</div>
                </div>

                <!-- Logs Tab Content -->
                <div id="logs-content" class="tab-content space-y-4 hidden">
                    <div id="logs-loading" class="text-gray-500">Loading traces...</div>
                    <div id="logs-container" class="space-y-4 hidden"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let agents = [];
    let runs = [];
    let traces = [];
    let activeTab = 'runs';
    
    // Format date
    function formatDateTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleString();
    }
    
    // Format time only
    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString();
    }
    
    // Status badge component
    function StatusBadge({ status }) {
        const statusMap = {
            'success': { class: 'status-success', label: 'Success' },
            'error': { class: 'status-error', label: 'Error' },
            'training': { class: 'status-training', label: 'Training' },
            'completed': { class: 'status-completed', label: 'Completed' },
            'pending': { class: 'status-pending', label: 'Pending' }
        };
        
        const statusConfig = statusMap[status] || statusMap.pending;
        
        return `
            <span class="status-badge ${statusConfig.class}">
                ${statusConfig.label}
            </span>
        `;
    }
    
    // Get agent name by ID
    function getAgentName(agentId) {
        const agent = agents.find(a => a.id?.toString() === agentId);
        return agent ? agent.name : 'Unknown Agent';
    }
    
    // Get run description from traces
    function getRunDescription(runId) {
        const task = traces.find(t => t.run_id === runId && t.description);
        return task?.description || 'Execute agent run';
    }
    
    // Fetch agents
    async function fetchAgents() {
        try {
            const response = await fetch('https://trizk-12-agenticai.hf.space/agents');
            if (response.ok) {
                const data = await response.json();
                agents = Array.isArray(data) ? data : [];
            } else {
                console.error('Failed to fetch agents');
                agents = [];
            }
        } catch (error) {
            console.error('Error fetching agents:', error);
            agents = [];
        }
    }
    
    // Fetch runs
    async function fetchRuns() {
        document.getElementById('runs-loading').classList.remove('hidden');
        document.getElementById('runs-container').classList.add('hidden');
        
        try {
            const response = await fetch('https://trizk-12-agenticai.hf.space/runs');
            if (response.ok) {
                const data = await response.json();
                runs = Array.isArray(data) ? data : [];
                displayRuns();
            } else {
                console.error('Failed to fetch runs');
                runs = [];
                displayRuns();
            }
        } catch (error) {
            console.error('Error fetching runs:', error);
            runs = [];
            displayRuns();
        } finally {
            document.getElementById('runs-loading').classList.add('hidden');
        }
    }
    
    // Fetch traces for a specific run
    async function fetchTraces(runIds) {
        if (activeTab !== 'logs') return;
        
        document.getElementById('logs-loading').classList.remove('hidden');
        document.getElementById('logs-container').classList.add('hidden');
        
        const allTraces = [];
        
        for (const runId of runIds) {
            try {
                const response = await fetch(`https://trizk-12-agenticai.hf.space/runs/${runId}/trace`);
                if (response.ok) {
                    const data = await response.json();
                    console.log(`Trace for run ${runId}:`, data);
                    if (data?.tasks && Array.isArray(data.tasks)) {
                        allTraces.push(
                            ...data.tasks.map(task => ({
                                ...task,
                                runId: runId
                            }))
                        );
                    }
                } else {
                    console.error(`Failed to fetch trace for run ${runId}: ${response.status} ${response.statusText}`);
                }
            } catch (error) {
                console.error(`Error fetching trace for run ${runId}:`, error);
            }
        }
        
        traces = allTraces;
        console.log('All traces:', traces);
        
        displayLogs();
        document.getElementById('logs-loading').classList.add('hidden');
    }
    
    // Display runs in the runs tab
    function displayRuns() {
        const container = document.getElementById('runs-container');
        const noRuns = document.getElementById('no-runs');
        
        container.innerHTML = '';
        
        if (runs.length === 0) {
            noRuns.classList.remove('hidden');
            container.classList.add('hidden');
            return;
        }
        
        noRuns.classList.add('hidden');
        container.classList.remove('hidden');
        
        runs.forEach(run => {
            const runCard = createRunCard(run);
            container.insertAdjacentHTML('beforeend', runCard);
        });
    }
    
    // Create a run card
    function createRunCard(run) {
        return `
            <div class="bg-white rounded-lg shadow border border-gray-200">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="space-y-1">
                            <h3 class="text-lg font-semibold text-gray-900">${getAgentName(run.agent_id)}</h3>
                            <div class="flex items-center gap-2 text-gray-500">
                                ${StatusBadge({ status: run.status || 'pending' })}
                                <span>•</span>
                                <span>${formatDateTime(run.created_at || run.startTime)}</span>
                            </div>
                        </div>
                        <div class="flex gap-4 text-sm text-gray-500">
                            ${run.duration ? `
                                <div class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                    ${run.duration}s
                                </div>
                            ` : ''}
                            ${run.tokens_used ? `
                                <div class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="13 3 13 10 19 10"></polyline>
                                        <polyline points="23 3 23 10 17 10"></polyline>
                                        <polyline points="13 14 13 21 19 21"></polyline>
                                        <polyline points="23 14 23 21 17 21"></polyline>
                                    </svg>
                                    ${run.tokens_used}
                                </div>
                            ` : ''}
                            ${run.cost ? `
                                <div class="flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="1" x2="12" y2="23"></line>
                                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                    </svg>
                                    $${run.cost.toFixed(3)}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
                <div class="px-6 pb-6 space-y-4">
                    ${run.input ? `
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-gray-900">Input:</p>
                            <p class="rounded-md bg-gray-100 p-3 text-sm">${run.input}</p>
                        </div>
                    ` : ''}
                    ${run.output ? `
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-gray-900">Output:</p>
                            <p class="rounded-md bg-gray-100 p-3 text-sm">${run.output}</p>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    }
    
    // Group traces by run_id
    function groupTraces() {
        return traces.reduce((acc, trace) => {
            const rid = trace.run_id;
            if (!acc[rid]) acc[rid] = [];
            acc[rid].push(trace);
            return acc;
        }, {});
    }
    
    // Display logs in the logs tab
    function displayLogs() {
        const container = document.getElementById('logs-container');
        container.innerHTML = '';
        
        const groupedTraces = groupTraces();
        
        runs.forEach(run => {
            const runTasks = groupedTraces[run.id] || [];
            
            if (runTasks.length === 0) return;
            
            const runSection = `
                <div class="space-y-2">
                    <h3 class="font-medium text-gray-900">${getAgentName(run.agent_id)} - ${formatDateTime(run.created_at || run.startTime)}</h3>
                    ${runTasks.map(task => createTaskCard(task)).join('')}
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', runSection);
        });
        
        container.classList.remove('hidden');
    }
    
    // Create a task card for logs
    function createTaskCard(task) {
        const status = task.status === 'success' ? 'completed' : 
                     task.status === 'error' ? 'error' : 'training';
        
        return `
            <div class="ml-8 flex items-start gap-4 rounded-lg border border-gray-200 p-4 text-sm">
                <div class="min-w-[160px] text-gray-500">
                    ${formatTime(task.created_at)}
                </div>
                ${StatusBadge({ status: status })}
                <p class="flex-1">
                    ${task.description || 'Execute agent run tasks'}
                </p>
                ${task.result ? `
                    <code class="rounded bg-gray-100 px-2 py-1 text-xs">
                        ${JSON.stringify(task.result)}
                    </code>
                ` : ''}
            </div>
        `;
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
        
        if (tabName === 'runs') {
            document.getElementById('runs-content').classList.remove('hidden');
        } else if (tabName === 'logs') {
            document.getElementById('logs-content').classList.remove('hidden');
            // Fetch traces when logs tab is activated
            if (runs.length > 0 && traces.length === 0) {
                fetchTraces(runs.map(run => run.id));
            } else {
                displayLogs();
            }
        }
    }
    
    // Search functionality
    function setupSearch() {
        const searchInput = document.getElementById('search-input');
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            
            // Filter runs in both tabs
            if (activeTab === 'runs') {
                // Implement search for runs if needed
            } else if (activeTab === 'logs') {
                // Implement search for logs if needed
            }
        });
    }
    
    // Initialize page
    document.addEventListener('DOMContentLoaded', async function() {
        // Setup event listeners
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                switchTab(this.dataset.tab);
            });
        });
        
        setupSearch();
        
        // Fetch initial data
        await fetchAgents();
        await fetchRuns();
    });
</script>

@include('includes.footerJs')
@include('includes.footer')