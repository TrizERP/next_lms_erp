@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')
<style>
    :root {
        --background: #ffffff;
        --foreground: #020817;
        --muted: #f1f5f9;
        --muted-foreground: #64748b;
        --border: #e2e8f0;
        --primary: #3b82f6;
        --primary-hover: #2563eb;
        --card-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
    }

    body {
        background-color: #f8fafc;
    }

    .agent-header {
        background: white;
        border-bottom: 1px solid var(--border);
        padding: 1.5rem 0;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1;
    }

    .status-active {
        background-color: #dcfce7;
        color: #166534;
    }

    .status-inactive {
        background-color: #fee2e2;
        color: #991b1b;
    }

    .status-paused {
        background-color: #fff3cd;
        color: #856404;
    }

    .status-unknown {
        background-color: #f1f5f9;
        color: #475569;
    }

    .stats-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 0.5rem;
        padding: 1.5rem;
        height: 100%;
        box-shadow: var(--card-shadow);
    }

    .stats-card h3 {
        color: var(--muted-foreground);
        font-size: 0.875rem;
        font-weight: 500;
        margin-bottom: 0.5rem;
    }

    .stats-card .value {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--foreground);
        margin: 0;
    }

    .nav-tabs {
        border-bottom: 1px solid var(--border);
        margin-bottom: 1.5rem;
    }

    .nav-tabs .nav-link {
        color: var(--muted-foreground);
        border: none;
        padding: 0.75rem 1rem;
        font-weight: 500;
        cursor: pointer;
    }

    .nav-tabs .nav-link:hover {
        color: var(--foreground);
        border: none;
    }

    .nav-tabs .nav-link.active {
        color: var(--primary);
        background: transparent;
        border-bottom: 2px solid var(--primary);
    }

    .info-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--card-shadow);
    }

    .info-card-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border);
    }

    .info-card-header h2 {
        font-size: 1.25rem;
        font-weight: 600;
        margin: 0;
        color: var(--foreground);
    }

    .info-card-body {
        padding: 1.5rem;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 0;
        border-bottom: 1px solid var(--border);
    }

    .info-row:last-child {
        border-bottom: none;
    }

    .info-label {
        color: var(--muted-foreground);
        font-size: 0.875rem;
    }

    .info-value {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--foreground);
    }

    .system-prompt {
        background-color: var(--muted);
        padding: 1rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        color: var(--muted-foreground);
        margin: 0;
    }

    .tool-badge {
        display: inline-block;
        padding: 0.35rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
        margin-right: 0.5rem;
        margin-bottom: 0.5rem;
        cursor: pointer;
        transition: all 0.2s;
    }

    .tool-badge-secondary {
        background-color: var(--muted);
        color: var(--muted-foreground);
        border: 1px solid transparent;
    }

    .tool-badge-secondary:hover {
        background-color: #e2e8f0;
    }

    .tool-badge-primary {
        background-color: var(--primary);
        color: white;
    }

    .run-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 0.5rem;
        margin-bottom: 1rem;
        padding: 1.5rem;
    }

    .run-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .run-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--foreground);
        margin: 0;
    }

    .run-meta {
        display: flex;
        gap: 1rem;
        color: var(--muted-foreground);
        font-size: 0.75rem;
    }

    .run-content {
        margin-top: 1rem;
    }

    .run-content-item {
        margin-bottom: 0.5rem;
    }

    .run-content-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--foreground);
        margin-bottom: 0.25rem;
    }

    .run-content-text {
        font-size: 0.875rem;
        color: var(--muted-foreground);
        background-color: var(--muted);
        padding: 0.5rem;
        border-radius: 0.25rem;
    }

    .reflection-section {
        background-color: var(--muted);
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }

    .reflection-title {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--foreground);
        margin-bottom: 0.5rem;
    }

    .reflection-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .reflection-list li {
        color: var(--muted-foreground);
        font-size: 0.875rem;
        padding: 0.25rem 0;
        padding-left: 1rem;
        position: relative;
    }

    .reflection-list li:before {
        content: "•";
        position: absolute;
        left: 0;
        color: var(--primary);
    }

    .tool-form {
        margin-top: 1.5rem;
        padding: 1.5rem;
        background-color: var(--muted);
        border-radius: 0.5rem;
    }

    .tool-form h3 {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 1rem;
        color: var(--foreground);
    }

    .btn-icon {
        width: 38px;
        height: 38px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .back-btn {
        color: var(--muted-foreground);
        background: transparent;
        border: 1px solid var(--border);
    }

    .back-btn:hover {
        background-color: var(--muted);
    }

    .alert {
        border-radius: 0.5rem;
        margin-top: 1rem;
    }

    .tab-pane {
        display: none;
    }

    .tab-pane.active {
        display: block;
    }
</style>
<div id="page-wrapper">
    <div class="container-fluid" style="background-color:#fff;padding:10px;">
        {{-- Header --}}
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="d-flex align-items-center gap-3">
                
                <div>
                    <div class="d-flex align-items-center gap-3 mb-1">
                        <h1 class="h2 fw-bold mb-0" id="agent-name">Agent Name</h1>
                        <span class="status-badge" id="agent-status-badge">Loading...</span>
                    </div>
                    <p class="text-secondary mb-0" id="agent-description">Loading agent details...</p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary btn-icon">
                    <i class="fas fa-cog"></i>
                </button>
                <button class="btn" id="agent-action-btn">
                    <i class="fas" id="action-icon"></i>
                    <span id="action-text"></span>
                </button>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <h3>Total Runs</h3>
                    <p class="value" id="total-runs">0</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h3>Success Rate</h3>
                    <p class="value" id="success-rate">0%</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h3>Last Run</h3>
                    <p class="value small" id="last-run">Never</p>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <ul class="nav nav-tabs" id="agentTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" id="config-tab" href="#config" type="button" aria-selected="true" data-toggle="tab">Configuration</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="runs-tab" href="#runs-tabs" type="button" aria-selected="false" data-toggle="tab">Recent Runs</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="reflection-tab" href="#reflections-tabs" type="button" aria-selected="false" data-toggle="tab">Reflection</a>
            </li>
        </ul>

        {{-- Tab Content --}}
        <div class="tab-content" id="agentTabsContent">
            {{-- Config Tab --}}
            <div class="tab-pane active" id="config" role="tabpanel">
                {{-- Model Settings --}}
                <div class="info-card">
                    <div class="info-card-header">
                        <h2>Model Settings</h2>
                    </div>
                    <div class="info-card-body">
                        <div class="info-row">
                            <span class="info-label">Model</span>
                            <span class="info-value" id="model-name">Loading...</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Temperature</span>
                            <span class="info-value" id="temperature">0</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Max Tokens</span>
                            <span class="info-value" id="max-tokens">0</span>
                        </div>
                    </div>
                </div>

                {{-- System Prompt --}}
                <div class="info-card">
                    <div class="info-card-header">
                        <h2>System Prompt</h2>
                    </div>
                    <div class="info-card-body">
                        <p class="system-prompt" id="system-prompt">Loading...</p>
                    </div>
                </div>

                {{-- Available Tools --}}
                <div class="info-card">
                    <div class="info-card-header">
                        <h2>Available Tools</h2>
                    </div>
                    <div class="info-card-body">
                        <div id="tools-container" class="mb-3">
                            <span class="text-secondary">Loading tools...</span>
                        </div>

                        {{-- Tool Form Container --}}
                        <div id="tool-form-container" style="display: none;"></div>
                    </div>
                </div>
            </div>

            {{-- Runs Tab --}}
            <div class="tab-pane" id="runs-tabs" role="tabpanel">
                <div id="runs-container">
                    <p class="text-secondary">Loading runs...</p>
                </div>
            </div>

            {{-- Reflection Tab --}}
            <div class="tab-pane" id="reflections-tabs" role="tabpanel">
                <div class="info-card">
                    <div class="info-card-header">
                        <h2>Agent Reflection & Learning</h2>
                        <p class="text-secondary mb-0">Self-improvement insights and patterns</p>
                    </div>
                    <div class="info-card-body">
                        <div class="reflection-section">
                            <h3 class="reflection-title">Performance Insights</h3>
                            <ul class="reflection-list">
                                <li>Successfully handles 94% of customer inquiries without escalation</li>
                                <li>Average response time improved by 15% over the last week</li>
                                <li>Most common tools used: knowledge_base (65%), email (25%)</li>
                            </ul>
                        </div>

                        <div class="reflection-section">
                            <h3 class="reflection-title">Areas for Improvement</h3>
                            <ul class="reflection-list">
                                <li>Complex technical queries require longer processing time</li>
                                <li>Consider adding more context to system prompt for edge cases</li>
                                <li>Token usage could be optimized for shorter responses</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Hidden Input for Agent ID --}}
<input type="hidden" id="agent-id" value="{{ $_REQUEST['agent_id'] ?? 0 }}">

@include('includes.footerJs')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        const agentId = $('#agent-id').val();
        // alert('Agent ID: ' + agentId);

        if (!agentId || agentId === '0') {
            showError('No agent ID provided');
            return;
        }

        let agent = null;
        let runs = [];
        let agentTools = [];
        
        // Initial fetch
        fetchAgent();
        fetchRuns();
        fetchAgentTools();

        // Helper Functions
        function getStatusBadgeClass(status) {
            switch (status?.toLowerCase()) {
                case 'active':
                    return 'status-active';
                case 'inactive':
                    return 'status-inactive';
                case 'paused':
                    return 'status-paused';
                default:
                    return 'status-unknown';
            }
        }

        function formatDate(dateString) {
            if (!dateString) return 'Never';
            return new Date(dateString).toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function showError(message) {
            $('#agent-name').text('Error');
            $('#agent-description').text(message);
            $('#agent-status-badge').text('Error').attr('class', 'status-badge status-unknown');
        }

        // Fetch Agent Details
        function fetchAgent() {
            $.ajax({
                url: 'https://trizk-12-agenticai.hf.space/agents',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Agent response:', response);
                    const agents = Array.isArray(response) ? response : [];
                    const foundAgent = agents.find(a => a.id?.toString() === agentId);

                    if (foundAgent) {
                        agent = {
                            id: foundAgent.id?.toString() || '',
                            name: foundAgent.name || 'Unnamed Agent',
                            description: foundAgent.description || 'No description available',
                            module: foundAgent.module || '',
                            submodule: foundAgent.sub_module || '',
                            role: 'agent',
                            status: foundAgent.status || 'unknown',
                            model: foundAgent.model || 'gpt-4',
                            temperature: foundAgent.temperature || 0.7,
                            maxTokens: foundAgent.max_tokens || 1024,
                            systemPrompt: foundAgent.system_prompt || 'No system prompt available',
                            tools: [],
                            createdAt: foundAgent.created_at || new Date().toISOString(),
                            lastRun: foundAgent.created_at || new Date().toISOString(),
                            totalRuns: 0,
                            successRate: 100,
                        };

                        updateAgentUI();
                        filterRuns();
                    } else {
                        showError('Agent not found');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to fetch agent:', error);
                    showError('Failed to load agent details');
                }
            });
        }

        // Fetch Runs
        function fetchRuns() {
            $.ajax({
                url: 'https://trizk-12-agenticai.hf.space/runs',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log('Runs response:', response);
                    runs = Array.isArray(response) ? response : [];
                    filterRuns();
                },
                error: function(xhr, status, error) {
                    console.error('Failed to fetch runs:', error);
                    runs = [];
                    filterRuns();
                }
            });
        }

        // Fetch Agent Tools
        function fetchAgentTools() {
            $.ajax({
                url: `https://karan-01-agentic-tools.hf.space/api/agentic_tools/${agentId}`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    console.log("Agent Tools API Response:", response);
                    agentTools = Array.isArray(response.tools) ? response.tools : [];
                    renderTools();
                },
                error: function(xhr, status, error) {
                    console.error('Failed to fetch agent tools:', error);
                    agentTools = [];
                    renderTools();
                }
            });
        }

        // Start Agent Run
        function startAgentRun() {
            const payload = {
                sub_institute_id: {{ session()->get('sub_institute_id') }},
                user_id: {{ session()->get('user_id') }},
                user_profile: "{{ session()->get('user_profile_name') }}",
            };
            $.ajax({
                url: `https://trizk-12-agenticai.hf.space/agents/${agentId}/run`,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(payload),
                success: function(response) {
                    alert('Agent run started successfully');
                    console.log('Agent run started:', response);
                    fetchRuns(); // Refresh runs
                },
                error: function(xhr, status, error) {
                    alert('Error starting agent run');
                    console.error('Error starting agent run:', error);
                }
            });
        }
        // Filter runs for current agent
        function filterRuns() {
            const agentRuns = runs.filter(r => r.agent_id?.toString() === agentId);
            updateStats(agentRuns);
            renderRuns(agentRuns);
        }

        // Update UI with agent data
        function updateAgentUI() {
            if (!agent) return;

            $('#agent-name').text(agent.name);
            $('#agent-description').text(agent.description);
            $('#agent-status-badge')
                .text(agent.status.charAt(0).toUpperCase() + agent.status.slice(1))
                .attr('class', `status-badge ${getStatusBadgeClass(agent.status)}`);

            $('#model-name').text(agent.model);
            $('#temperature').text(agent.temperature);
            $('#max-tokens').text(agent.maxTokens);
            $('#system-prompt').text(agent.systemPrompt);

            // Update action button based on status
            const actionBtn = $('#agent-action-btn');
            if (agent.status === 'active') {
                actionBtn.removeClass('btn-primary').addClass('btn-outline-secondary');
                $('#action-icon').removeClass('fa-play').addClass('fa-pause');
                $('#action-text').text('Pause');
            } else {
                actionBtn.removeClass('btn-outline-secondary').addClass('btn-primary');
                $('#action-icon').removeClass('fa-pause').addClass('fa-play');
                $('#action-text').text('Activate');
            }
        }

        // Update stats
        function updateStats(agentRuns) {
            $('#total-runs').text(agentRuns.length);

            const successRate = agentRuns.length > 0 ?
                Math.round((agentRuns.filter(r => r.status === 'success').length / agentRuns.length) * 100) :
                100;
            $('#success-rate').text(successRate + '%');

            const lastRun = agentRuns.length > 0 ? agentRuns[0].created_at || agentRuns[0].startTime : agent?.lastRun;
            $('#last-run').text(formatDate(lastRun));
        }

        // Render tools
        function renderTools() {
            const container = $('#tools-container');
            container.empty();

            if (agentTools.length > 0) {
                agentTools.forEach(tool => {
                    container.append(
                        `<span class="tool-badge tool-badge-secondary" onclick="selectTool('${tool}')">${tool}</span>`
                    );
                });
            } else {
                container.html('<p class="text-secondary mb-0">No tools assigned to this agent</p>');
            }
        }

        // Select tool and show form
        window.selectTool = function(tool) {
            $('.tool-badge').removeClass('tool-badge-primary').addClass('tool-badge-secondary');
            $(event.target).removeClass('tool-badge-secondary').addClass('tool-badge-primary');

            loadToolForm(tool);
        };

        // Load tool form
        function loadToolForm(tool) {
            const formContainer = $('#tool-form-container');

            let formHtml = '<div class="tool-form"><h3>Fill in the form for tool execution</h3>';

            switch (tool) {
                case 'knowledge_base':
                    formHtml += `
                    <form id="tool-execution-form">
                        <div class="mb-3">
                            <label class="form-label">Query</label>
                            <input type="text" class="form-control" name="query" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Knowledge Base ID</label>
                            <input type="text" class="form-control" name="knowledge_base_id">
                        </div>
                        <button type="submit" class="btn btn-primary">Execute</button>
                    </form>
                `;
                    break;

                case 'email':
                    formHtml += `
                    <form id="tool-execution-form">
                        <div class="mb-3">
                            <label class="form-label">To</label>
                            <input type="email" class="form-control" name="to" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" name="subject" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Body</label>
                            <textarea class="form-control" name="body" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send</button>
                    </form>
                `;
                    break;

                case 'data_viz':
                    formHtml += `
                    <form id="tool-execution-form">
                        <div class="mb-3">
                            <label class="form-label">Data Source</label>
                            <input type="text" class="form-control" name="data_source" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Chart Type</label>
                            <select class="form-control" name="chart_type" required>
                                <option value="bar">Bar Chart</option>
                                <option value="line">Line Chart</option>
                                <option value="pie">Pie Chart</option>
                                <option value="scatter">Scatter Plot</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">X Axis</label>
                            <input type="text" class="form-control" name="x_axis">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Y Axis</label>
                            <input type="text" class="form-control" name="y_axis">
                        </div>
                        <button type="submit" class="btn btn-primary">Generate</button>
                    </form>
                `;
                    break;

                case 'web_search':
                    formHtml += `
                    <form id="tool-execution-form">
                        <div class="mb-3">
                            <label class="form-label">Search Query</label>
                            <input type="text" class="form-control" name="query" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Number of Results</label>
                            <input type="number" class="form-control" name="num_results" value="5" min="1" max="20">
                        </div>
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                `;
                    break;

                case 'sql_query':
                    formHtml += `
                    <form id="tool-execution-form">
                        <div class="mb-3">
                            <label class="form-label">Database Connection</label>
                            <input type="text" class="form-control" name="connection" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SQL Query</label>
                            <textarea class="form-control" name="query" rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Execute</button>
                    </form>
                `;
                    break;

                case 'file':
                    formHtml += `
                    <form id="tool-execution-form">
                        <div class="mb-3">
                            <label class="form-label">File</label>
                            <input type="file" class="form-control" name="file" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Operation</label>
                            <select class="form-control" name="operation" required>
                                <option value="read">Read</option>
                                <option value="write">Write</option>
                                <option value="process">Process</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Execute</button>
                    </form>
                `;
                    break;
            }

            formHtml += '</div>';
            formContainer.html(formHtml).show();

            // Attach form submit handler
            $('#tool-execution-form').on('submit', function(e) {
                e.preventDefault();
                submitToolForm(tool, $(this).serializeArray());
            });
        }

        // Submit tool form
        function submitToolForm(tool, formData) {
            const data = {};
            formData.forEach(item => data[item.name] = item.value);
            data.agent_id = agentId;
            data.tool = tool;

            $.ajax({
                url: '/api/agentic_tools/submit', // Update with your actual endpoint
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data),
                success: function(response) {
                    alert('Tool executed successfully');
                    $('#tool-form-container').hide();
                    $('.tool-badge').removeClass('tool-badge-primary').addClass('tool-badge-secondary');
                },
                error: function(xhr, status, error) {
                    alert('Error executing tool');
                    console.error('Error:', error);
                }
            });
        }

        // Render runs
        function renderRuns(agentRuns) {
            const container = $('#runs-container');
            container.empty();

            if (agentRuns.length > 0) {
                agentRuns.forEach(run => {
                    const runCard = `
                    <div class="run-card">
                        <div class="run-header">
                            <h4 class="run-title">Run ${run.id}</h4>
                            <span class="status-badge ${getStatusBadgeClass(run.status)}">
                                ${run.status || 'Unknown'}
                            </span>
                        </div>
                        <div class="text-secondary small mb-3">
                            ${formatDate(run.created_at || run.startTime)}
                        </div>
                        <div class="run-content">
                            ${run.input ? `
                                <div class="run-content-item">
                                    <div class="run-content-label">Input:</div>
                                    <div class="run-content-text">${run.input}</div>
                                </div>
                            ` : ''}
                            ${run.output ? `
                                <div class="run-content-item">
                                    <div class="run-content-label">Output:</div>
                                    <div class="run-content-text">${run.output}</div>
                                </div>
                            ` : ''}
                            <div class="run-meta mt-2">
                                ${run.tool ? `<span>Tool: ${run.tool}</span>` : ''}
                                ${run.duration ? `<span>Duration: ${run.duration}s</span>` : ''}
                                ${run.tokensUsed ? `<span>Tokens: ${run.tokensUsed}</span>` : ''}
                                ${run.cost ? `<span>Cost: $${parseFloat(run.cost).toFixed(3)}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
                    container.append(runCard);
                });
            } else {
                container.html('<p class="text-secondary">No runs available for this agent</p>');
            }
        }

        // Handle action button click
        $('#agent-action-btn').on('click', function() {
            if (agent?.status === 'active') {
                // Handle pause
                alert('Pause functionality to be implemented');
            } else {
                startAgentRun();
            }
        });
    });
</script>
@include('includes.footer')
