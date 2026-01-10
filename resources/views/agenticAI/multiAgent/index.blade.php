@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<style>
    .stat-card {
        background: white;
        border-radius: 0.5rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        border: 1px solid #e5e7eb;
    }
    
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    .badge-outline {
        background-color: transparent;
        border: 1px solid;
    }
    
    .badge-default {
        background-color: #3b82f6;
        color: white;
        border: 1px solid #3b82f6;
    }
    
    .badge-secondary {
        background-color: #f3f4f6;
        color: #374151;
    }
    
    .status-idle {
        border-color: #e5e7eb;
    }
    
    .status-processing {
        border-color: #3b82f6;
        animation: pulse 2s infinite;
    }
    
    .status-completed {
        border-color: #10b981;
    }
    
    .status-error {
        border-color: #ef4444;
    }
    
    .fade-in {
        animation: fadeIn 0.3s ease-in;
    }
    
    .slide-in-right {
        animation: slideInRight 0.5s ease-out infinite;
    }
    
    .spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    @keyframes slideInRight {
        from { transform: translateX(-100%); }
        to { transform: translateX(200%); }
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    .node-card {
        min-width: 260px;
    }
    
    .flow-connector {
        position: relative;
        overflow: hidden;
    }
    
    /* .progress-slider {
        position: absolute;
        height: 100%;
        width: 33.33%;
        background-color: #3b82f6;
        animation: slideInRight 1s ease-in-out infinite;
    } */
</style>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="space-y-6 p-4">
            <!-- Header -->
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Multi-Agent Coordination</h1>
                <p class="text-gray-500">Visualize agent interactions and workflows</p>
            </div>

            <!-- Agent Workflow Card -->
            <div class="stat-card">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">Agent Workflow</h2>
                            <p class="text-gray-500">Sequential agent execution pipeline</p>
                        </div>
                        <div id="running-badge" class="badge badge-outline border-blue-500 text-blue-600 hidden">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1">
                                <polyline points="13 3 13 10 19 10"></polyline>
                                <polyline points="23 3 23 10 17 10"></polyline>
                                <polyline points="13 14 13 21 19 21"></polyline>
                                <polyline points="23 14 23 21 17 21"></polyline>
                            </svg>
                            Running
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2 overflow-x-auto pb-4" id="workflow-container">
                        <!-- Workflow nodes will be inserted here -->
                        <div class="text-center py-8 w-full">
                            <p class="text-gray-500">Loading workflow...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parallel Execution & Communication Cards -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Parallel Execution Card -->
                <div class="stat-card">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-2">Parallel Execution</h2>
                        <p class="text-gray-500 mb-6">Agents running simultaneously</p>
                        
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-600 mt-1">
                                    <path d="M6 3v12"></path>
                                    <path d="M18 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"></path>
                                    <path d="M6 21a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"></path>
                                    <path d="M15 6a9 9 0 0 0-9 9"></path>
                                    <path d="M18 15v6"></path>
                                    <path d="M21 18h-6"></path>
                                </svg>
                                <div class="flex-1 space-y-2" id="parallel-agents-container">
                                    <!-- Parallel agents will be inserted here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Agent Communication Card -->
                <div class="stat-card">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-2">Agent Communication</h2>
                        <p class="text-gray-500 mb-6">Inter-agent message passing</p>
                        
                        <div class="space-y-3" id="communication-container">
                            <!-- Communication messages will be inserted here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Mock data
    const mockAgents = [
        {
            id: '1',
            name: 'Customer Support Agent',
            model: 'GPT-4',
            description: 'Handles customer inquiries and support tickets',
            tools: ['knowledge_base', 'email'],
            state: 'idle'
        },
        {
            id: '2',
            name: 'Data Analyst Agent',
            model: 'GPT-4',
            description: 'Analyzes data and generates insights',
            tools: ['sql_query', 'data_viz'],
            state: 'idle'
        },
        {
            id: '3',
            name: 'Content Writer Agent',
            model: 'GPT-4',
            description: 'Creates and edits written content',
            tools: ['web_search'],
            state: 'idle'
        },
        {
            id: '4',
            name: 'Code Review Agent',
            model: 'GPT-4',
            description: 'Reviews and analyzes code',
            tools: ['knowledge_base'],
            state: 'idle'
        },
        {
            id: '5',
            name: 'Research Assistant Agent',
            model: 'Claude 3',
            description: 'Assists with research and analysis',
            tools: ['web_search', 'knowledge_base'],
            state: 'idle'
        }
    ];

    const parallelAgents = mockAgents.slice(0, 3);
    
    // Communication messages
    const communications = [
        { from: 'Customer Support', to: 'Data Analyst', msg: 'Requesting customer behavior analysis for ticket #1234', time: '2 min ago' },
        { from: 'Data Analyst', to: 'Content Writer', msg: 'Sharing insights for blog post generation', time: '5 min ago' },
        { from: 'Content Writer', to: 'Code Review', msg: 'Documentation draft ready for technical review', time: '8 min ago' },
    ];
    
    // Status configuration
    const statusConfig = {
        idle: { icon: null, color: 'status-idle', pulse: false },
        processing: { icon: 'loader', color: 'status-processing', pulse: true },
        completed: { icon: 'check-circle', color: 'status-completed', pulse: false },
        error: { icon: 'alert-circle', color: 'status-error', pulse: false },
    };
    
    // State variables
    let workflowNodes = [];
    let parallelStates = {};
    let activeConnection = -1;
    let isRunning = false;
    
    // SVG Icons
    const icons = {
        'loader': `
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="spin">
                <path d="M21 12a9 9 0 1 1-6.219-8.56"></path>
            </svg>
        `,
        'check-circle': `
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-500">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
        `,
        'alert-circle': `
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-red-500">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
        `,
        'arrow-right': `
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
        `
    };
    
    // Initialize workflow nodes
    function initializeWorkflowNodes() {
        workflowNodes = mockAgents.map(agent => ({
            ...agent,
            state: 'idle'
        }));
    }
    
    // Create workflow node card
    function createWorkflowNodeCard(node, index) {
        const config = statusConfig[node.state];
        const icon = config.icon ? icons[config.icon] : '';
        
        return `
            <div class="node-card fade-in flex flex-col gap-2 rounded-lg border-2 bg-white p-4 ${config.color}"
                 style="animation-delay: ${index * 100}ms">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">${node.name}</h3>
                    <div class="flex items-center gap-2">
                        ${icon}
                        <span class="badge badge-outline text-xs border-gray-300">${node.model}</span>
                    </div>
                </div>
                <p class="text-xs text-gray-500">${node.description}</p>
                <div class="flex flex-wrap gap-1">
                    ${node.tools.slice(0, 2).map(tool => 
                        `<span class="badge badge-secondary text-xs">${tool}</span>`
                    ).join('')}
                    ${node.tools.length > 2 ? 
                        `<span class="badge badge-secondary text-xs">+${node.tools.length - 2}</span>` : 
                        ''
                    }
                </div>
                
                ${node.state === 'processing' ? `
                    <div class="h-1 w-full bg-gray-200 rounded-full overflow-hidden mt-2">
                        <div class="progress-slider"></div>
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    // Create flow connector
    function createFlowConnector(isActive) {
        return `
            <div class="flex items-center gap-1 px-2">
                <div class="flow-connector h-0.5 w-8 ${isActive ? 'bg-blue-600' : 'bg-gray-300'}">
                    ${isActive ? '<div class="progress-slider"></div>' : ''}
                </div>
                <div class="${isActive ? 'text-blue-600' : 'text-gray-400'}">
                    ${icons['arrow-right']}
                </div>
            </div>
        `;
    }
    
    // Create parallel agent card
    function createParallelAgentCard(agent, idx) {
        const state = parallelStates[agent.id] || 'idle';
        const config = statusConfig[state];
        const icon = config.icon ? icons[config.icon] : `
            <div class="h-4 w-4 rounded-full border-2 border-gray-300"></div>
        `;
        
        return `
            <div class="fade-in flex items-center justify-between rounded-md border-2 p-3 ${config.color}"
                 style="animation-delay: ${idx * 100}ms">
                <div class="flex items-center gap-3">
                    ${icon}
                    <span class="text-sm font-medium">${agent.name}</span>
                </div>
                <span class="badge ${state === 'processing' ? 'badge-default' : 'badge-outline'} text-xs capitalize ${state === 'processing' ? 'animate-pulse' : ''}">
                    ${state}
                </span>
            </div>
        `;
    }
    
    // Create communication message
    function createCommunicationMessage(comm, idx) {
        return `
            <div class="fade-in rounded-lg border border-gray-200 p-3 hover:bg-gray-50"
                 style="animation-delay: ${idx * 100}ms">
                <div class="mb-2 flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <span class="text-blue-600">${comm.from}</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                        <span class="text-gray-900">${comm.to}</span>
                    </div>
                    <span class="text-xs text-gray-500">${comm.time}</span>
                </div>
                <p class="text-xs text-gray-500">${comm.msg}</p>
            </div>
        `;
    }
    
    // Update workflow display
    function updateWorkflowDisplay() {
        const container = document.getElementById('workflow-container');
        container.innerHTML = '';
        
        workflowNodes.forEach((node, index) => {
            container.innerHTML += createWorkflowNodeCard(node, index);
            
            if (index < workflowNodes.length - 1) {
                container.innerHTML += createFlowConnector(activeConnection === index + 1);
            }
        });
    }
    
    // Update parallel agents display
    function updateParallelAgentsDisplay() {
        const container = document.getElementById('parallel-agents-container');
        container.innerHTML = parallelAgents
            .map((agent, idx) => createParallelAgentCard(agent, idx))
            .join('');
    }
    
    // Update communication display
    function updateCommunicationDisplay() {
        const container = document.getElementById('communication-container');
        container.innerHTML = communications
            .map((comm, idx) => createCommunicationMessage(comm, idx))
            .join('');
    }
    
    // Run workflow simulation
    async function runWorkflow() {
        isRunning = true;
        document.getElementById('running-badge').classList.remove('hidden');
        
        for (let i = 0; i < workflowNodes.length; i++) {
            // Start processing current node
            workflowNodes[i].state = 'processing';
            activeConnection = i;
            updateWorkflowDisplay();
            
            // Simulate processing time
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            // Complete current node (randomly add error for demo)
            const hasError = i === 3 && Math.random() > 0.5;
            workflowNodes[i].state = hasError ? 'error' : 'completed';
            updateWorkflowDisplay();
        }
        
        activeConnection = -1;
        isRunning = false;
        document.getElementById('running-badge').classList.add('hidden');
        
        // Reset after delay
        setTimeout(() => {
            workflowNodes.forEach(node => node.state = 'idle');
            updateWorkflowDisplay();
        }, 3000);
    }
    
    // Run parallel execution simulation
    function runParallelExecution() {
        parallelAgents.forEach((agent, idx) => {
            setTimeout(() => {
                parallelStates[agent.id] = 'processing';
                updateParallelAgentsDisplay();
                
                setTimeout(() => {
                    parallelStates[agent.id] = Math.random() > 0.9 ? 'error' : 'completed';
                    updateParallelAgentsDisplay();
                    
                    setTimeout(() => {
                        parallelStates[agent.id] = 'idle';
                        updateParallelAgentsDisplay();
                    }, 2000);
                }, 1500 + Math.random() * 1000);
            }, idx * 500);
        });
    }
    
    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize data
        initializeWorkflowNodes();
        
        // Initial display
        updateWorkflowDisplay();
        updateParallelAgentsDisplay();
        updateCommunicationDisplay();
        
        // Start workflow simulation
        setTimeout(runWorkflow, 1000);
        
        // Repeat workflow every 15 seconds
        setInterval(runWorkflow, 15000);
        
        // Start parallel execution
        runParallelExecution();
        
        // Repeat parallel execution every 8 seconds
        setInterval(runParallelExecution, 8000);
    });
</script>

@include('includes.footerJs')
@include('includes.footer')