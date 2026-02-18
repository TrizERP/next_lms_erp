@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<!-- Include Lucide Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lucide@latest/font/lucide.css">
<style>
    /* Custom styles for slider and progress bar */
    input[type="range"] {
        -webkit-appearance: none;
        appearance: none;
        width: 100%;
        height: 8px;
        background: #e5e7eb;
        border-radius: 4px;
        outline: none;
    }

    input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        appearance: none;
        width: 20px;
        height: 20px;
        background: #3b82f6;
        border-radius: 50%;
        cursor: pointer;
    }

    .step-active {
        border-color: #3b82f6;
        color: #3b82f6;
    }

    .step-completed {
        background-color: #3b82f6;
        border-color: #3b82f6;
        color: white;
    }
    
    .line-active {
        background-color: #3b82f6;
    }
    
    .line-inactive {
        background-color: #e5e7eb;
    }
</style>
@php
    $isEdit = $data['isEdit'] ?? false;
    $currentStep = $data['currentStep'] ?? 1;
    $agent = $data['agent'] ?? null;
    $formData = $data['formData'] ?? [
        'name' => '',
        'description' => '',
        'module' => '',
        'model' => 'gpt-4',
        'temperature' => 0.7,
        'maxTokens' => 2000,
        'submodule' => 'gpt-4',
        'role' => '',
        'systemPrompt' => '',
        'tools' => [],
    ];
@endphp
<div id="page-wrapper">
    <div class="container-fluid" style="background-color:#fff;">
        <div class="mx-auto space-y-4 p-4">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <a href="{{ route('create_agent.index') }}"
                    class="p-2 hover:bg-gray-100 rounded-full transition-colors">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                </a>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">
                        {{ $isEdit ? 'Edit Agent' : 'Create New Agent' }}
                    </h1>
                    <p class="text-gray-500">
                        Configure your AI agent's behavior and capabilities
                    </p>
                </div>
            </div>

            <!-- Progress Steps -->
            <nav aria-label="Progress">
                <ol class="flex items-center justify-between">
                    @php
                        $steps = [
                            ['id' => 1, 'name' => 'Basic Info', 'description' => 'Agent identity'],
                            ['id' => 2, 'name' => 'Model Config', 'description' => 'AI parameters'],
                            ['id' => 3, 'name' => 'System Prompt', 'description' => 'Behavior definition'],
                            ['id' => 4, 'name' => 'Tools', 'description' => 'Capabilities'],
                        ];
                    @endphp

                    @foreach ($steps as $index => $step)
                        <li class="relative {{ $index !== count($steps) - 1 ? 'pr-8 sm:pr-20 flex-1' : '' }}">
                            <div class="flex items-center">
                                <div class="relative flex items-center">
                                    <div data-step="{{ $step['id'] }}"
                                        class="flex h-10 w-10 items-center justify-center rounded-full border-2 transition-colors
                                        {{ $currentStep > $step['id']
                                            ? 'step-completed'
                                            : ($currentStep == $step['id']
                                                ? 'step-active'
                                                : 'border-gray-200') }}">
                                        @if ($currentStep > $step['id'])
                                            <i data-lucide="check" class="w-5 h-5"></i>
                                        @else
                                            <span
                                                class="text-sm font-semibold 
                                                {{ $currentStep == $step['id'] ? 'text-blue-600' : 'text-gray-400' }}">
                                                {{ $step['id'] }}
                                            </span>
                                        @endif
                                    </div>

                                    @if ($index !== count($steps) - 1)
                                        <div data-line="{{ $step['id'] }}" class="absolute left-10 top-5 h-0.5 w-full
                                            {{ $currentStep > $step['id'] ? 'line-active' : 'line-inactive' }}"
                                            style="width: calc(100% + 2rem)"></div>
                                    @endif
                                </div>

                                <div class="ml-4">
                                    <span
                                        class="text-sm font-medium
                                        {{ $currentStep == $step['id'] ? 'text-gray-900' : 'text-gray-500' }}">
                                        {{ $step['name'] }}
                                    </span>
                                    <span class="block text-xs text-gray-500">
                                        {{ $step['description'] }}
                                    </span>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </nav>

            <!-- Form -->
            <form id="agent-form" class="space-y-6">
                <!-- Step 1: Basic Info -->
                <div id="step-1" class="step-content {{ $currentStep == 1 ? 'block' : 'hidden' }}">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="mb-6">
                            <h2 class="text-xl font-semibold text-gray-900">Basic Info</h2>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Agent Name
                                </label>
                                <input type="text" id="name" name="name"
                                    value="{{ old('name', $formData['name']) }}" placeholder="Enter agent name"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <div id="name-error" class="text-red-500 text-sm mt-1 hidden"></div>
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                                    Description
                                </label>
                                <textarea id="description" name="description" rows="3" placeholder="Describe the agent's purpose and capabilities"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">{{ old('description', $formData['description']) }}</textarea>
                                <div id="description-error" class="text-red-500 text-sm mt-1 hidden"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Model Configuration -->
                <div id="step-2" class="step-content {{ $currentStep == 2 ? 'block' : 'hidden' }}">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="mb-6">
                            <h2 class="text-xl font-semibold text-gray-900">Model Configuration</h2>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label for="module" class="block text-sm font-medium text-gray-700 mb-1">
                                    Module
                                </label>
                                <input type="text" id="module" name="module"
                                    value="{{ old('module', $formData['module']) }}" placeholder="e.g. research"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <div id="module-error" class="text-red-500 text-sm mt-1 hidden"></div>
                            </div>

                            <div>
                                <label for="submodule" class="block text-sm font-medium text-gray-700 mb-1">
                                    Model
                                </label>
                                <select id="submodule" name="submodule"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="gpt-4"
                                        {{ old('submodule', $formData['submodule']) == 'gpt-4' ? 'selected' : '' }}>
                                        GPT-4
                                    </option>
                                    <option value="gpt-3.5-turbo"
                                        {{ old('submodule', $formData['submodule']) == 'gpt-3.5-turbo' ? 'selected' : '' }}>
                                        GPT-3.5 Turbo
                                    </option>
                                    <option value="claude-3"
                                        {{ old('submodule', $formData['submodule']) == 'claude-3' ? 'selected' : '' }}>
                                        Claude 3
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">
                                    Role
                                </label>
                                <input type="text" id="role" name="role"
                                    value="{{ old('role', $formData['role']) }}"
                                    placeholder="e.g. Interview Assistant"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Temperature: <span
                                        id="temperature-value">{{ old('temperature', $formData['temperature']) }}</span>
                                </label>
                                <input type="range" id="temperature" name="temperature" min="0" max="1"
                                    step="0.1" value="{{ old('temperature', $formData['temperature']) }}"
                                    class="w-full"
                                    oninput="document.getElementById('temperature-value').textContent = this.value">
                            </div>

                            <div>
                                <label for="maxTokens" class="block text-sm font-medium text-gray-700 mb-1">
                                    Max Tokens
                                </label>
                                <input type="number" id="maxTokens" name="max_tokens"
                                    value="{{ old('max_tokens', $formData['maxTokens']) }}"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <div id="maxTokens-error" class="text-red-500 text-sm mt-1 hidden"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: System Prompt -->
                <div id="step-3" class="step-content {{ $currentStep == 3 ? 'block' : 'hidden' }}">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="mb-6">
                            <h2 class="text-xl font-semibold text-gray-900">System Prompt</h2>
                        </div>

                        <div>
                            <textarea id="systemPrompt" name="system_prompt" rows="6"
                                placeholder="Define the agent's behavior, instructions, and personality"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">{{ old('system_prompt', $formData['systemPrompt']) }}</textarea>
                            <div id="systemPrompt-error" class="text-red-500 text-sm mt-1 hidden"></div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Tools -->
                <div id="step-4" class="step-content {{ $currentStep == 4 ? 'block' : 'hidden' }}">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="mb-6">
                            <h2 class="text-xl font-semibold text-gray-900">Tools</h2>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            @php
                                $availableTools = [
                                    'knowledge_base' => 'Knowledge Base',
                                    'web_search' => 'Web Search',
                                    'email' => 'Email',
                                    'sql_query' => 'SQL Query',
                                    'data_viz' => 'Data Visualization',
                                    'file_operations' => 'File Operations',
                                ];

                                $selectedTools = old('tools', $formData['tools']);
                                if (is_string($selectedTools)) {
                                    $selectedTools = json_decode($selectedTools, true) ?? [];
                                }
                            @endphp

                            @foreach ($availableTools as $toolId => $toolLabel)
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="tools[]" value="{{ $toolId }}" id="tool-{{ $toolId }}"
                                        {{ in_array($toolId, $selectedTools) ? 'checked' : '' }}
                                        class="tool-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                    <span>{{ $toolLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div id="tools-error" class="text-red-500 text-sm mt-1 hidden"></div>
                    </div>
                </div>

                <!-- Hidden fields -->
                <input type="hidden" id="model" name="model" value="{{ $formData['model'] ?? 'gpt-4' }}">
                @if($isEdit && $agent)
                    <input type="hidden" id="agent_id" name="agent_id" value="{{ $agent->id }}">
                @endif

                <!-- Buttons -->
                <div class="flex justify-between">
                    <div class="flex gap-4">
                        <a href="{{ route('create_agent.index') }}"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </a>

                        <button type="button" id="prev-btn" onclick="goToStep(currentStep - 1)"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 hidden">
                            Previous
                        </button>
                    </div>

                    <button type="button" id="next-btn" onclick="validateAndNext()"
                        class="px-4 py-2 bg-blue-600 border border-transparent rounded-md text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Next
                    </button>
                    
                    <button type="button" id="submit-btn" onclick="createAgent()"
                        class="px-4 py-2 bg-green-600 border border-transparent rounded-md text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 hidden">
                        {{ $isEdit ? 'Update Agent' : 'Create Agent' }}
                    </button>
                </div>
            </form>

            <!-- Success Display -->
            <div id="success-alert" class="bg-green-50 border border-green-200 rounded-md p-4 hidden">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i data-lucide="check-circle" class="h-5 w-5 text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800" id="success-title">
                            Agent Created Successfully
                        </h3>
                        <div class="mt-4 space-y-4" id="success-content">
                            <!-- Content will be added by JavaScript -->
                        </div>
                        <a href="{{ route('create_agent.index') }}"
                            class="mt-4 inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md text-white hover:bg-blue-700">
                            Go Back to Home
                        </a>
                    </div>
                </div>
            </div>

            <!-- Error Display -->
            <div id="error-alert" class="bg-red-50 border border-red-200 rounded-md p-4 hidden">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i data-lucide="alert-circle" class="h-5 w-5 text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">
                            Error Creating Agent
                        </h3>
                        <div class="mt-2 text-sm text-red-700" id="error-message">
                            <!-- Error message will be added by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for step management and validation -->
<script>
    let currentStep = 1;
    const totalSteps = 4;
    let formData = {
        name: '',
        description: '',
        module: '',
        model: 'gpt-4',
        temperature: 0.7,
        maxTokens: 2000,
        submodule: 'gpt-4',
        role: '',
        systemPrompt: '',
        tools: []
    };

    // Initialize from PHP data
    document.addEventListener('DOMContentLoaded', function() {
        // Set initial values from PHP
        @foreach($formData as $key => $value)
            @if(!is_array($value) && !in_array($key, ['tools']))
                formData['{{ $key }}'] = '{{ $value }}';
            @endif
        @endforeach
        
        @if(isset($formData['tools']) && is_array($formData['tools']))
            formData.tools = @json($formData['tools']);
        @endif

        // Set current step from PHP
        currentStep = {{ $currentStep }};
        updateStepDisplay();
        updateButtonStates();
        
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Restore from localStorage
        loadFromLocalStorage();
    });

    function updateStepDisplay() {
        // Hide all steps
        document.querySelectorAll('.step-content').forEach(el => {
            el.classList.add('hidden');
        });
        
        // Show current step
        const currentStepEl = document.getElementById(`step-${currentStep}`);
        if (currentStepEl) {
            currentStepEl.classList.remove('hidden');
        }
        
        // Update progress indicators
        document.querySelectorAll('[data-step]').forEach(el => {
            const stepNum = parseInt(el.dataset.step);
            if (stepNum < currentStep) {
                el.classList.remove('step-active', 'border-gray-200');
                el.classList.add('step-completed');
            } else if (stepNum === currentStep) {
                el.classList.remove('step-completed', 'border-gray-200');
                el.classList.add('step-active');
            } else {
                el.classList.remove('step-completed', 'step-active');
                el.classList.add('border-gray-200');
            }
        });
        
        // Update progress lines
        document.querySelectorAll('[data-line]').forEach(el => {
            const lineStep = parseInt(el.dataset.line);
            if (currentStep > lineStep) {
                el.classList.remove('line-inactive');
                el.classList.add('line-active');
            } else {
                el.classList.remove('line-active');
                el.classList.add('line-inactive');
            }
        });
    }

    function updateButtonStates() {
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const submitBtn = document.getElementById('submit-btn');
        
        // Previous button
        if (currentStep > 1) {
            prevBtn.classList.remove('hidden');
        } else {
            prevBtn.classList.add('hidden');
        }
        
        // Next/Submit buttons
        if (currentStep < totalSteps) {
            nextBtn.classList.remove('hidden');
            submitBtn.classList.add('hidden');
        } else {
            nextBtn.classList.add('hidden');
            submitBtn.classList.remove('hidden');
        }
    }

    function goToStep(step) {
        if (step < 1 || step > totalSteps) return;
        
        // Save current step data before moving
        saveCurrentStepData();
        
        currentStep = step;
        updateStepDisplay();
        updateButtonStates();
        
        // Scroll to top of form
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function saveCurrentStepData() {
        switch(currentStep) {
            case 1:
                formData.name = document.getElementById('name').value.trim();
                formData.description = document.getElementById('description').value.trim();
                break;
            case 2:
                formData.module = document.getElementById('module').value.trim();
                formData.submodule = document.getElementById('submodule').value;
                formData.role = document.getElementById('role').value.trim();
                formData.temperature = parseFloat(document.getElementById('temperature').value);
                formData.maxTokens = parseInt(document.getElementById('maxTokens').value);
                break;
            case 3:
                formData.systemPrompt = document.getElementById('systemPrompt').value.trim();
                break;
            case 4:
                // Save tools
                formData.tools = [];
                document.querySelectorAll('.tool-checkbox:checked').forEach(checkbox => {
                    formData.tools.push(checkbox.value);
                });
                break;
        }
        
        // Save to localStorage
        saveToLocalStorage();
    }

    function validateAndNext() {
        let isValid = true;
        
        // Clear all errors
        clearAllErrors();
        
        // Validate current step
        switch(currentStep) {
            case 1:
                const name = document.getElementById('name').value.trim();
                const description = document.getElementById('description').value.trim();
                
                if (!name) {
                    showError('name', 'Agent name is required');
                    isValid = false;
                }
                if (!description) {
                    showError('description', 'Description is required');
                    isValid = false;
                }
                
                // Save data for validation
                formData.name = name;
                formData.description = description;
                break;
                
            case 2:
                const module = document.getElementById('module').value.trim();
                const maxTokens = parseInt(document.getElementById('maxTokens').value);
                
                if (!module) {
                    showError('module', 'Module is required');
                    isValid = false;
                }
                if (isNaN(maxTokens) || maxTokens < 100 || maxTokens > 8000) {
                    showError('maxTokens', 'Max tokens must be between 100 and 8000');
                    isValid = false;
                }
                
                // Save data for validation
                formData.module = module;
                formData.submodule = document.getElementById('submodule').value;
                formData.role = document.getElementById('role').value.trim();
                formData.temperature = parseFloat(document.getElementById('temperature').value);
                formData.maxTokens = maxTokens;
                break;
                
            case 3:
                const systemPrompt = document.getElementById('systemPrompt').value.trim();
                
                if (!systemPrompt) {
                    showError('systemPrompt', 'System prompt is required');
                    isValid = false;
                }
                
                formData.systemPrompt = systemPrompt;
                break;
                
            case 4:
                // Save tools for validation
                formData.tools = [];
                document.querySelectorAll('.tool-checkbox:checked').forEach(checkbox => {
                    formData.tools.push(checkbox.value);
                });
                
                if (formData.tools.length === 0) {
                    showError('tools', 'At least one tool must be selected');
                    isValid = false;
                }
                break;
        }
        
        if (isValid) {
            saveCurrentStepData();
            
            if (currentStep === totalSteps) {
                // On final step, call createAgent function
                createAgent();
            } else {
                // Move to next step
                goToStep(currentStep + 1);
            }
        }
    }

    async function createAgent() {
        // Hide any existing alerts
        document.getElementById('success-alert').classList.add('hidden');
        document.getElementById('error-alert').classList.add('hidden');
        
        // Save current step data
        saveCurrentStepData();
        
        // Validate all steps before submission
        clearAllErrors();
        let isValid = true;
        
        // Validate all fields
        if (!formData.name.trim()) {
            showError('name', 'Agent name is required');
            isValid = false;
        }
        if (!formData.description.trim()) {
            showError('description', 'Description is required');
            isValid = false;
        }
        if (!formData.module.trim()) {
            showError('module', 'Module is required');
            isValid = false;
        }
        if (formData.maxTokens < 100 || formData.maxTokens > 8000) {
            showError('maxTokens', 'Max tokens must be between 100 and 8000');
            isValid = false;
        }
        if (!formData.systemPrompt.trim()) {
            showError('systemPrompt', 'System prompt is required');
            isValid = false;
        }
        if (formData.tools.length === 0) {
            showError('tools', 'At least one tool must be selected');
            isValid = false;
        }
        
        if (!isValid) {
            // Go to first step with error
            if (!formData.name.trim() || !formData.description.trim()) {
                goToStep(1);
            } else if (!formData.module.trim() || formData.maxTokens < 100 || formData.maxTokens > 8000) {
                goToStep(2);
            } else if (!formData.systemPrompt.trim()) {
                goToStep(3);
            } else {
                goToStep(4);
            }
            return;
        }
        
        // Prepare payload
        const payload = {
            sub_institute_id: {{ session()->get('sub_institute_id') }},
            user_id: {{ session()->get('user_id') }},
            user_profile: "{{ session()->get('user_profile_name') }}",
            name: formData.name,
            description: formData.description,
            module: formData.module,
            sub_module: formData.submodule,
            role: formData.role || 'agent',
            temperature: formData.temperature,
            max_tokens: formData.maxTokens,
            system_prompt: formData.systemPrompt
        };
        
        // Get agent ID if editing
        const agentIdElement = document.getElementById('agent_id');
        const isEdit = agentIdElement && agentIdElement.value;
        const editId = isEdit ? agentIdElement.value : null;
        
        const url = isEdit 
            ? `https://trizk-12-agenticai.hf.space/agents/${editId}` 
            : 'https://trizk-12-agenticai.hf.space/agents';
        const method = isEdit ? 'PUT' : 'POST';
        
        // Show loading state
        const submitBtn = document.getElementById('submit-btn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="flex items-center justify-center"><span class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></span>Processing...</span>';
        submitBtn.disabled = true;
        
        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });
            
            if (response.ok) {
                const data = await response.json();
                
                // Show success message
                document.getElementById('success-title').textContent = 
                    `${isEdit ? 'Agent Updated' : 'Agent Created'} Successfully`;
                
                const successContent = document.getElementById('success-content');
                successContent.innerHTML = `
                    <div>
                        <h4 class="font-semibold">Sent Payload:</h4>
                        <pre class="bg-gray-100 p-4 rounded text-sm overflow-auto">${JSON.stringify(payload, null, 2)}</pre>
                    </div>
                    <div>
                        <h4 class="font-semibold">Response:</h4>
                        <pre class="bg-gray-100 p-4 rounded text-sm overflow-auto">${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
                
                document.getElementById('success-alert').classList.remove('hidden');
                
                // Clear localStorage on success
                localStorage.removeItem('agent_form_data');
                localStorage.removeItem('agent_current_step');
                
                // Show alert
                alert(`${isEdit ? 'Agent updated' : 'Agent created'} successfully`);
                
            } else {
                const errorText = await response.text();
                const errorMessage = `Failed to ${isEdit ? 'update' : 'create'} agent: ${response.status} ${response.statusText} - ${errorText}`;
                
                document.getElementById('error-message').textContent = errorMessage;
                document.getElementById('error-alert').classList.remove('hidden');
                
                alert(`Error: ${errorMessage}`);
            }
        } catch (error) {
            const errorMessage = `Error ${isEdit ? 'updating' : 'creating'} agent: ${error.message}`;
            document.getElementById('error-message').textContent = errorMessage;
            document.getElementById('error-alert').classList.remove('hidden');
            
            alert(`Error: ${errorMessage}`);
        } finally {
            // Restore button state
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    }

    function showError(fieldId, message) {
        const errorDiv = document.getElementById(`${fieldId}-error`);
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.classList.remove('hidden');
            
            // Scroll to error
            const field = document.getElementById(fieldId);
            if (field) {
                field.scrollIntoView({ behavior: 'smooth', block: 'center' });
                field.focus();
            }
        }
    }

    function clearAllErrors() {
        document.querySelectorAll('[id$="-error"]').forEach(el => {
            el.textContent = '';
            el.classList.add('hidden');
        });
    }

    // Local storage functions
    function saveToLocalStorage() {
        localStorage.setItem('agent_form_data', JSON.stringify(formData));
        localStorage.setItem('agent_current_step', currentStep.toString());
    }

    function loadFromLocalStorage() {
        const savedData = localStorage.getItem('agent_form_data');
        const savedStep = localStorage.getItem('agent_current_step');
        
        if (savedData) {
            const parsedData = JSON.parse(savedData);
            // Only load from localStorage if we're not editing
            if (!{{ $isEdit ? 'true' : 'false' }}) {
                formData = { ...formData, ...parsedData };
            }
        }
        
        if (savedStep) {
            // Only use saved step if we're not editing
            if (!{{ $isEdit ? 'true' : 'false' }}) {
                currentStep = parseInt(savedStep);
            }
        }
        
        // Update form fields with loaded data
        updateFormFields();
    }

    function updateFormFields() {
        // Update visible fields with loaded data
        if (document.getElementById('name')) {
            document.getElementById('name').value = formData.name;
            document.getElementById('description').value = formData.description;
        }
        if (document.getElementById('module')) {
            document.getElementById('module').value = formData.module;
            document.getElementById('submodule').value = formData.submodule;
            document.getElementById('role').value = formData.role;
            document.getElementById('temperature').value = formData.temperature;
            document.getElementById('maxTokens').value = formData.maxTokens;
            document.getElementById('temperature-value').textContent = formData.temperature;
        }
        if (document.getElementById('systemPrompt')) {
            document.getElementById('systemPrompt').value = formData.systemPrompt;
        }
        
        // Update tools checkboxes
        document.querySelectorAll('.tool-checkbox').forEach(checkbox => {
            checkbox.checked = formData.tools.includes(checkbox.value);
        });
    }

    // Auto-save on input
    document.addEventListener('input', function(e) {
        if (e.target.matches('#agent-form input, #agent-form textarea, #agent-form select')) {
            saveCurrentStepData();
        }
    });

    // Handle checkbox changes
    document.addEventListener('change', function(e) {
        if (e.target.matches('.tool-checkbox')) {
            saveCurrentStepData();
        }
    });
</script>

@include('includes.footerJs')
@include('includes.footer')
