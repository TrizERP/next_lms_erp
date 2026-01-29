@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<style>
    .line-clamp-2 {
        overflow: hidden;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
    }
    
    .agent-card {
        position: relative;
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        transition: all 300ms;
    }
    
    .agent-card:hover {
        box-shadow: 0 20px 25px -5px rgba(59, 130, 246, 0.1), 0 10px 10px -5px rgba(59, 130, 246, 0.04);
        border-color: #93c5fd;
        transform: scale(1.05) translateY(-0.25rem);
        background: linear-gradient(to bottom right, white, rgba(219, 234, 254, 0.3));
    }
    
    .card-hover-content {
        position: absolute;
        inset: 0;
        background: white;
        padding: 1.5rem;
        transform: translateY(100%);
        transition: transform 200ms;
        overflow-y: auto;
    }
    
    .agent-card:hover .card-hover-content {
        transform: translateY(0);
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid" style="background-color:#fff;">
        <div class="max-w-7xl px-2 sm:px-6 lg:px-8 py-4">
            <!-- Header -->
            <div class="mb-12">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Agentic Library</h1>
                    <p class="text-gray-600 mt-1 text-sm">Centralized discovery and management of AI agents</p>
                </div>
                <p class="text-gray-600 max-w-3xl text-sm mt-4">
                    Explore our intelligent agents deployed across the platform. Each agent is designed to automate,
                    enhance, and accelerate specific workflows within your organization. Click any card to learn more
                    about capabilities and access points.
                </p>
            </div>

            <!-- Agents Grid -->
            <div id="agents-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <!-- Static Agents will be loaded here -->
                <div id="loading-indicator" class="col-span-full text-center py-8">
                    <p class="text-gray-500">Loading agents...</p>
                </div>
            </div>

            <!-- Purpose & Value Section -->
            <div class="mt-12 p-6 bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-start space-x-4">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-400">
                                <path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 1.98-3A2.5 2.5 0 0 1 9.5 2Z"/>
                                <path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-1.98-3A2.5 2.5 0 0 0 14.5 2Z"/>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Purpose & Value</h3>
                        <ul class="space-y-2 text-sm text-gray-600">
                            <li class="flex items-start">
                                <span class="text-blue-400 mr-2">•</span>
                                <span><strong>Capability Catalog</strong> for leadership to understand AI investments and ROI</span>
                            </li>
                            <li class="flex items-start">
                                <span class="text-blue-400 mr-2">•</span>
                                <span><strong>Discovery Layer</strong> for users to explore and leverage intelligent capabilities</span>
                            </li>
                            <li class="flex items-start">
                                <span class="text-blue-400 mr-2">•</span>
                                <span><strong>Trust-Building Mechanism</strong> through transparency in AI functionality</span>
                            </li>
                            <li class="flex items-start">
                                <span class="text-blue-400 mr-2">•</span>
                                <span><strong>Foundation for Governance</strong> enabling future agent lifecycle management</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Static agents data
    const staticAgents = [
        {
            id: 'task-management',
            name: 'Task Management Agent',
            module: 'Organization Management → Task Assignment',
            summary: 'Intelligently supports task creation, allocation, and prioritization using organizational context',
            function: 'This agent intelligently supports task creation, allocation, and prioritization by analyzing organizational context, roles, and workload distribution.',
            workflow: [
                'User initiates task creation or assignment',
                'Agent analyzes task intent and complexity',
                'Maps tasks to relevant roles or users',
                'Suggests priority, dependencies, and timelines'
            ],
            outputs: ['Smart Task Assignments', 'Priority & Effort Recommendations', 'Role-Aligned Task Distribution', 'Reduced Manual Planning Overhead'],
            cta: 'Go to Task Assignment',
            ctaLink: '/content/task'
        },
        {
            id: 'skill-generator',
            name: 'Skill Generator Agent',
            module: 'Competency Library',
            summary: 'Dynamically creates standardized skill definitions aligned with industry frameworks',
            function: 'The Skill Generator Agent dynamically creates standardized skill definitions aligned with industry frameworks, job roles, and organizational needs.',
            workflow: [
                'User inputs domain, role, or competency requirement',
                'Agent references internal frameworks and standards',
                'Generates structured skill taxonomy',
                'Skills are published to the competency library'
            ],
            outputs: ['Skill Name & Description', 'Category & Sub-Category Mapping', 'Proficiency Level Definitions', 'Reusable Skill Objects'],
            cta: 'Open Competency Library',
            ctaLink: '/content/Libraries/skillLibrary'
        },
        {
            id: 'job-role-generator',
            name: 'Job Role Generator Agent',
            module: 'Competency Library',
            summary: 'Automates creation of detailed job role definitions with skill and responsibility mappings',
            function: 'This agent automates the creation of detailed job role definitions using industry-aligned skill and responsibility mappings.',
            workflow: [
                'User specifies role intent or industry context',
                'Agent analyzes benchmark data and competencies',
                'Generates role structure with expectations',
                'Role is added to the competency ecosystem'
            ],
            outputs: ['Job Role Description', 'Required Skills & Proficiency Levels', 'Responsibility & Outcome Mapping', 'Career Path Alignment'],
            cta: 'Manage Job Roles',
            ctaLink: '/job-roles'
        },
        {
            id: 'sanity-check',
            name: 'Sanity Check Agent',
            module: 'User Profile → Self Rating of Skills',
            summary: 'Validates user self-assessments by identifying inconsistencies and rating gaps',
            function: 'The Sanity Check Agent validates user self-assessments by identifying inconsistencies, overstatements, or gaps in skill ratings.',
            workflow: [
                'User submits self-rated skills',
                'Agent cross-validates ratings against benchmarks',
                'Flags anomalies or unrealistic scores',
                'Provides corrective guidance'
            ],
            outputs: ['Skill Rating Validation', 'Confidence & Gap Indicators', 'Rating Adjustment Suggestions', 'Improved Data Accuracy'],
            cta: 'Review Skill Self-Rating',
            ctaLink: '/content/user/edit'
        },
        {
            id: 'course-generator',
            name: 'Build with AI – Course Generator Agent',
            module: 'Competency Library',
            summary: 'Creates structured, modular learning courses tailored to specific skills and roles',
            function: 'This agent creates structured, modular learning courses tailored to specific skills, roles, or competency gaps.',
            workflow: [
                'User selects skill or role',
                'Agent designs learning objectives',
                'Generates course structure and modules',
                'Content is published for learning delivery'
            ],
            outputs: ['Course Outline & Modules', 'Learning Objectives', 'Skill-to-Content Mapping', 'AI-Generated Learning Assets'],
            cta: 'Create AI Course',
            ctaLink: '/course-generator'
        },
        {
            id: 'pal-agent',
            name: 'Personalized Adaptive Learning (PAL) Agent',
            module: 'Agentic AI → PAL',
            summary: 'Delivers adaptive learning journeys with real-time content adjustment based on performance',
            function: 'PAL delivers adaptive learning journeys by continuously adjusting content based on user performance, behavior, and progress.',
            workflow: [
                'Agent analyzes user skill profile',
                'Maps learning goals and gaps',
                'Dynamically adjusts learning path',
                'Continuously optimizes recommendations'
            ],
            outputs: ['Personalized Learning Paths', 'Real-Time Adaptation', 'Skill Progress Insights', 'Outcome-Oriented Learning'],
            cta: 'Launch PAL Agent',
            ctaLink: 'https://learningagent-kxvkny4y8p5ud4abjappvx7.streamlit.app/'
        },
        {
            id: 'smart-recruitment',
            name: 'Smart Recruitment Agent',
            module: 'Talent Management → Talent Acquisition',
            summary: 'Enhances hiring through intelligent candidate-job matching using competency alignment',
            function: 'The Smart Recruitment Agent enhances hiring by matching candidates with job roles using skill, experience, and competency alignment.',
            workflow: [
                'Job role and requirements are defined',
                'Agent evaluates candidate profiles',
                'Performs intelligent matching',
                'Ranks candidates by fitment score'
            ],
            outputs: ['Candidate Fit Scores', 'Skill Match Analysis', 'Hiring Recommendations', 'Reduced Screening Time'],
            cta: 'Go to Talent Acquisition',
            ctaLink: '/content/Telent-management/Recruitment-management'
        }
    ];

    // Icon mapping
    const iconMap = {
        'task-management': 'check-square',
        'skill-generator': 'award',
        'job-role-generator': 'briefcase',
        'sanity-check': 'shield-check',
        'course-generator': 'book-open',
        'pal-agent': 'brain',
        'smart-recruitment': 'users',
        'default': 'brain'
    };

    // Get icon SVG
    function getIconSVG(iconName) {
        const icons = {
            'check-square': `
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 11 12 14 22 4"></polyline>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                </svg>
            `,
            'award': `
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="7"></circle>
                    <polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline>
                </svg>
            `,
            'briefcase': `
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
            `,
            'shield-check': `
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <path d="m9 12 2 2 4-4"></path>
                </svg>
            `,
            'book-open': `
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                </svg>
            `,
            'brain': `
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 1.98-3A2.5 2.5 0 0 1 9.5 2Z"></path>
                    <path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-1.98-3A2.5 2.5 0 0 0 14.5 2Z"></path>
                </svg>
            `,
            'users': `
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            `
        };
        return icons[iconName] || icons['default'];
    }

    // Create agent card HTML
    function createAgentCard(agent) {
        const iconName = iconMap[agent.id] || 'default';
        const iconSVG = getIconSVG(iconName);
        
        return `
            <div class="agent-card group">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center group-hover:from-blue-100 group-hover:to-purple-100 group-hover:ring-2 group-hover:ring-blue-300 transition-all duration-300">
                                <div class="w-6 h-6 text-blue-400">
                                    ${iconSVG === undefined ? 'N/A' : iconSVG}
                                </div>
                            </div>
                            <div class="flex flex-col">
                                <h3 class="text-lg font-semibold text-gray-900 leading-tight">${agent.name}</h3>
                                <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                    Active
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                            ${agent.module}
                        </span>
                    </div>

                    <p class="text-sm text-gray-600 mb-4 line-clamp-2">${agent.summary}</p>

                    <!-- Hover Content -->
                    <div class="card-hover-content">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center">
                                <div class="w-5 h-5 text-blue-400">
${getIconSVG(iconName) || 'N/A'}
                                </div>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">${agent.name}</h3>
                        </div>

                        <div class="mb-4">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Function</h4>
                            <p class="text-sm text-gray-700">${agent.function}</p>
                        </div>

                        <div class="mb-4">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Workflow</h4>
                            <ol class="space-y-1">
                                ${agent.workflow.map((step, idx) => `
                                    <li key="${idx}" class="text-sm text-gray-700 flex items-start">
                                        <span class="font-medium text-blue-400 mr-2">${idx + 1}.</span>
                                        <span>${step}</span>
                                    </li>
                                `).join('')}
                            </ol>
                        </div>

                        <div class="mb-4">
                            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Outputs Overview</h4>
                            <ul class="space-y-1">
                                ${agent.outputs.map((output, idx) => `
                                    <li key="${idx}" class="text-sm text-gray-700 flex items-start">
                                        <span class="text-blue-400 mr-2">•</span>
                                        <span>${output}</span>
                                    </li>
                                `).join('')}
                            </ul>
                        </div>

                        <button onclick="handleAgentClick('${agent.ctaLink}')"
                            class="w-full mt-4 px-4 py-2.5 bg-blue-400 hover:bg-blue-500 text-white text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2">
                            ${agent.cta}
                        </button>
                    </div>
                </div>

                <div class="px-6 pb-6">
                    <button onclick="handleAgentClick('${agent.ctaLink}')"
                        class="w-full px-4 py-2 bg-gray-50 hover:bg-blue-50 hover:text-blue-700 text-gray-700 text-sm font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        ${agent.cta}
                    </button>
                </div>
            </div>
        `;
    }

    // Handle agent click
    function handleAgentClick(link) {
        if (link.startsWith('http')) {
            window.open(link, '_blank');
        } else {
            window.location.href = link;
        }
    }

    // Fetch agents from API
    async function fetchAgents() {
        try {
            const response = await fetch('https://trizk-12-agenticai.hf.space/agents?status=draft&sub_institute_id='+{{session()->get('sub_institute_id')}});
            if (response.ok) {
                const data = await response.json();
                return data.map(agent => ({
                    id: agent.id,
                    name: agent.name,
                    module: agent.module,
                    summary: agent.description,
                    function: agent.system_prompt || 'AI-powered agent for specialized tasks',
                    workflow: ['Agent is deployed and ready to use'],
                    outputs: ['AI-generated responses', 'Task automation', 'Intelligent recommendations'],
                    cta: 'View Agent',
                    ctaLink: '{{route("agent_library.create")}}?agent_id=' + agent.id
                }));
            } else {
                console.error('Failed to fetch agents');
                return [];
            }
        } catch (error) {
            console.error('Error fetching agents:', error);
            return [];
        }
    }

    // Initialize page
    document.addEventListener('DOMContentLoaded', async function() {
        const container = document.getElementById('agents-container');
        const loadingIndicator = document.getElementById('loading-indicator');
        
        // First, display static agents
        staticAgents.forEach(agent => {
            container.insertAdjacentHTML('beforeend', createAgentCard(agent));
        });
        
        // Then fetch and display dynamic agents
        try {
            const fetchedAgents = await fetchAgents();
            loadingIndicator.remove();
            
            fetchedAgents.forEach(agent => {
                container.insertAdjacentHTML('beforeend', createAgentCard(agent));
            });
        } catch (error) {
            loadingIndicator.textContent = 'Error loading agents';
            loadingIndicator.classList.add('text-red-500');
        }
    });
</script>

@include('includes.footerJs')
@include('includes.footer')