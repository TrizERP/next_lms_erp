@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')
@php 
$agentId = $data['agent_id'];
@endphp
<div id="page-wrapper">
    <div class="container-fluid" style="background-color:#fff;">
        <div class="space-y-4 p-4">
            <div class="container-fluid py-4">
                <!-- Back Button -->
                <div class="mb-4">
                    <a href="{{ route('agent_library.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <!-- Agent Not Found -->
                @if(!$data['agent_id'])
                <div class="text-center py-5">
                    <p class="text-muted">Agent not found</p>
                    <a href="{{ route('agent_library.index') }}" class="btn btn-primary mt-3">
                        Back to Dashboard
                    </a>
                </div>
                @else
                <!-- Header Section -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-3 mb-1">
                                <h1 class="h2 mb-0">{{ $data['agent_data']['name'] ?? '-' }}</h1>
                                <span class="badge">
                                    {{ ucfirst($data['agent_data']['status'] ?? '-') }}
                                </span>
                            </div>
                            <p class="text-muted mb-0">{{ $data['agent_data']['description'] ?? '-' }}</p>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-cog"></i>
                        </button>
                        @if(isset($data['agent_data']['status']) && $data['agent_data']['status'] == 'active')
                        <button class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-pause me-2"></i>Pause
                        </button>
                        @else
                        <button class="btn btn-primary btn-sm" onclick="startAgentRun('{{ $agentId ?? 0 }}')">

                            <i class="fas fa-play me-2"></i>Activate
                        </button>
                        @endif
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Total Runs</h6>
                                <h3 class="card-title">{{ count($data['agent_data']['runs'] ?? []) }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Success Rate</h6>
                                <h3 class="card-title">{{ $data['agent_data']['successRate'] ?? 100 }}%</h3>    
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Last Run</h6>
                                <p class="card-text mb-0">{{ date('M d, Y h:i A', strtotime($data['agent_data']['lastRun'] ?? 'now')) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="agentTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="config-tab" data-bs-toggle="tab" data-bs-target="#config" type="button">
                            Configuration
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="runs-tab" data-bs-toggle="tab" data-bs-target="#runs" type="button">
                            Recent Runs
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reflection-tab" data-bs-toggle="tab" data-bs-target="#reflection" type="button">
                            Reflection
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="agentTabContent">
                    <!-- Configuration Tab -->
                    <div class="tab-pane fade show active" id="config" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Model Settings</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-2">
                                            <div class="col-6">
                                                <small class="text-muted">Model</small>
                                            </div>
                                            <div class="col-6 text-end">
                                                <span class="fw-medium">{{ $data['agent_data']['model'] ?? 'gpt-4' }}</span>
                                            </div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-6">
                                                <small class="text-muted">Temperature</small>
                                            </div>
                                            <div class="col-6 text-end">
                                                <span class="fw-medium">{{ $data['agent_data']['temperature'] ?? 0.7 }}</span>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">Max Tokens</small>
                                            </div>
                                            <div class="col-6 text-end">
                                                <span class="fw-medium">{{ $data['agent_data']['maxTokens'] ?? 1024 }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Available Tools</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="d-flex flex-wrap gap-2">
                                            @if(isset($data['agent_data']['tools']) && !empty($data['agent_data']['tools']))
                                            @foreach($data['agent_data']['tools'] as $tool)
                                            <span class="badge bg-secondary">{{ $tool }}</span>
                                            @endforeach
                                            @else
                                            <span class="text-muted">No tools configured</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">System Prompt</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-0">{{ $data['agent_data']['systemPrompt'] ?? 'No system prompt configured' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Runs Tab -->
                    <div class="tab-pane fade" id="runs" role="tabpanel">
                        @if(isset($agentRuns) && count($agentRuns) > 0)
                        @foreach($agentRuns as $run)
                        <div class="card mb-3">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Run {{ $run->id }}</h6>
                                <span class="badge {{ $run->status == 'completed' ? 'bg-success' : ($run->status == 'running' ? 'bg-warning' : 'bg-secondary') }}">
                                    {{ ucfirst($run->status) }}
                                </span>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-2">
                                    <small>{{ date('M d, Y h:i A', strtotime($run->created_at ?? $run->startTime)) }}</small>
                                </p>

                                @if(isset($run->input))
                                <div class="mb-3">
                                    <p class="fw-medium mb-1">Input:</p>
                                    <p class="text-muted mb-0">{{ $run->input }}</p>
                                </div>
                                @endif

                                @if(isset($run->output))
                                <div class="mb-3">
                                    <p class="fw-medium mb-1">Output:</p>
                                    <p class="text-muted mb-0">{{ $run->output }}</p>
                                </div>
                                @endif

                                <div class="d-flex gap-3 text-muted">
                                    @if(isset($run->tool))
                                    <small>Tool: {{ $run->tool }}</small>
                                    @endif
                                    @if(isset($run->duration))
                                    <small>Duration: {{ $run->duration }}s</small>
                                    @endif
                                    @if(isset($run->tokensUsed))
                                    <small>Tokens: {{ $run->tokensUsed }}</small>
                                    @endif
                                    @if(isset($run->cost))
                                    <small>Cost: ${{ number_format($run->cost, 3) }}</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                        @else
                        <div class="text-center py-5">
                            <p class="text-muted">No runs available for this agent</p>
                        </div>
                        @endif
                    </div>

                    <!-- Reflection Tab -->
                    <div class="tab-pane fade" id="reflection" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Agent Reflection & Learning</h5>
                                <p class="text-muted mb-0">Self-improvement insights and patterns</p>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="border rounded p-3">
                                            <h6 class="fw-bold mb-3">Performance Insights</h6>
                                            <ul class="list-unstyled mb-0">
                                                <li class="mb-2">
                                                    <i class="fas fa-check-circle text-success me-2"></i>
                                                    Successfully handles 94% of customer inquiries without escalation
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-chart-line text-info me-2"></i>
                                                    Average response time improved by 15% over the last week
                                                </li>
                                                <li>
                                                    <i class="fas fa-tools text-primary me-2"></i>
                                                    Most common tools used: knowledge_base (65%), email (25%)
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="border rounded p-3">
                                            <h6 class="fw-bold mb-3">Areas for Improvement</h6>
                                            <ul class="list-unstyled mb-0">
                                                <li class="mb-2">
                                                    <i class="fas fa-clock text-warning me-2"></i>
                                                    Complex technical queries require longer processing time
                                                </li>
                                                <li class="mb-2">
                                                    <i class="fas fa-lightbulb text-info me-2"></i>
                                                    Consider adding more context to system prompt for edge cases
                                                </li>
                                                <li>
                                                    <i class="fas fa-coins text-warning me-2"></i>
                                                    Token usage could be optimized for shorter responses
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    function startAgentRun(agentId) {
        if (confirm('Are you sure you want to activate this agent?')) {
            fetch(`https://trizk-12-agenticai.hf.space/agents/${agentId}/run`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                })
                .then(response => {
                    if (response.ok) {
                        alert('Agent run started successfully');
                        // Reload page to update status
                        location.reload();
                    } else {
                        alert('Failed to start agent run');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error starting agent run');
                });
        }
    }

    // Fetch runs on page load
    fetch("https://trizk-12-agenticai.hf.space/runs")
        .then(response => response.json())
        .then(data => {
            console.log('Runs data:', data);
        })
        .catch(err => {
            console.error('Failed to fetch runs', err);
        });
</script>

@include('includes.footerJs')
@include('includes.footer')