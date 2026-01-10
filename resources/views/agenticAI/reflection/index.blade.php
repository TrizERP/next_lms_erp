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
        line-height: 1;
    }
    
    .badge-secondary {
        background-color: #f3f4f6;
        color: #374151;
    }
    
    .badge-outline {
        background-color: transparent;
        border: 1px solid;
    }
    
    .impact-high {
        background-color: #fee2e2;
        color: #991b1b;
        border-color: #fecaca;
    }
    
    .impact-medium {
        background-color: #fef3c7;
        color: #92400e;
        border-color: #fde68a;
    }
    
    .impact-low {
        background-color: #dbeafe;
        color: #1e40af;
        border-color: #93c5fd;
    }
    
    .priority-high {
        border-color: #ef4444;
        color: #ef4444;
    }
    
    .priority-medium {
        border-color: #f59e0b;
        color: #f59e0b;
    }
    
    .priority-low {
        border-color: #3b82f6;
        color: #3b82f6;
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid" style="background-color:#fff;">
        <div class="space-y-6 p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Reflection System</h1>
                    <p class="text-gray-500">AI-powered performance analysis and optimization recommendations</p>
                </div>
                <button class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 1.98-3A2.5 2.5 0 0 1 9.5 2Z"></path>
                        <path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-1.98-3A2.5 2.5 0 0 0 14.5 2Z"></path>
                    </svg>
                    Run New Analysis
                </button>
            </div>

            <!-- Key Insights -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div class="stat-card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-gray-500">Overall Success Rate</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-500">
                                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                <polyline points="17 6 23 6 23 12"></polyline>
                            </svg>
                        </div>
                        <div class="text-2xl font-bold text-gray-900">94.2%</div>
                        <p class="text-xs text-gray-500 mt-2">Increased by 1.8% from last week</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-gray-500">Average Cost/Run</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-red-500">
                                <polyline points="18 15 12 9 6 15"></polyline>
                            </svg>
                        </div>
                        <div class="text-2xl font-bold text-gray-900">$0.023</div>
                        <p class="text-xs text-gray-500 mt-2">Decreased by $0.004 from last week</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-gray-500">Avg Response Time</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-gray-400">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </div>
                        <div class="text-2xl font-bold text-gray-900">2.4s</div>
                        <p class="text-xs text-gray-500 mt-2">Stable compared to last week</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-gray-500">Total Failures (7d)</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-red-500">
                                <polyline points="18 15 12 9 6 15"></polyline>
                            </svg>
                        </div>
                        <div class="text-2xl font-bold text-gray-900">58</div>
                        <p class="text-xs text-gray-500 mt-2">Increased by 12 from last week</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-gray-500">Optimization Score</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-500">
                                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                <polyline points="17 6 23 6 23 12"></polyline>
                            </svg>
                        </div>
                        <div class="text-2xl font-bold text-gray-900">78/100</div>
                        <p class="text-xs text-gray-500 mt-2">Good - 5 recommendations available</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-medium text-gray-500">Cost Savings Potential</h3>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-500">
                                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                                <polyline points="17 6 23 6 23 12"></polyline>
                            </svg>
                        </div>
                        <div class="text-2xl font-bold text-gray-900">$2.10/day</div>
                        <p class="text-xs text-gray-500 mt-2">With high-priority optimizations</p>
                    </div>
                </div>
            </div>

            <!-- Failure Patterns -->
            <div class="stat-card">
                <div class="p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-yellow-500">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path>
                            <path d="M12 9v4"></path>
                            <path d="M12 17h.01"></path>
                        </svg>
                        <h2 class="text-xl font-semibold text-gray-900">Identified Failure Patterns</h2>
                    </div>
                    <p class="text-gray-500 mb-6">
                        Common failure patterns detected across agent runs with frequency analysis
                    </p>
                    
                    <div class="space-y-4">
                        <!-- Pattern 1 -->
                        <div class="rounded-lg border border-gray-200 p-4 space-y-3 hover:bg-gray-50 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-gray-900">API Rate Limiting Failures</h3>
                                        <span class="badge impact-high">high impact</span>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Occurred 28 times in the last 7 days
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p class="text-sm font-medium text-gray-900">Affected Agents:</p>
                                <div class="flex flex-wrap gap-1">
                                    <span class="badge badge-secondary text-xs">Customer Support Agent</span>
                                    <span class="badge badge-secondary text-xs">Content Writer Agent</span>
                                    <span class="badge badge-secondary text-xs">Data Analyst Agent</span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p class="text-sm font-medium text-gray-900">Example Failures:</p>
                                <ul class="space-y-1">
                                    <li class="text-xs text-gray-500 pl-4 relative before:content-['•'] before:absolute before:left-0">
                                        "Rate limit exceeded for OpenAI API - wait 20s before retry"
                                    </li>
                                    <li class="text-xs text-gray-500 pl-4 relative before:content-['•'] before:absolute before:left-0">
                                        "429 Too Many Requests from external service provider"
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Pattern 2 -->
                        <div class="rounded-lg border border-gray-200 p-4 space-y-3 hover:bg-gray-50 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-gray-900">Incomplete Context Extraction</h3>
                                        <span class="badge impact-medium">medium impact</span>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Occurred 15 times in the last 7 days
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p class="text-sm font-medium text-gray-900">Affected Agents:</p>
                                <div class="flex flex-wrap gap-1">
                                    <span class="badge badge-secondary text-xs">Data Analyst Agent</span>
                                    <span class="badge badge-secondary text-xs">Code Review Agent</span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p class="text-sm font-medium text-gray-900">Example Failures:</p>
                                <ul class="space-y-1">
                                    <li class="text-xs text-gray-500 pl-4 relative before:content-['•'] before:absolute before:left-0">
                                        "Unable to extract structured data from PDF attachment"
                                    </li>
                                    <li class="text-xs text-gray-500 pl-4 relative before:content-['•'] before:absolute before:left-0">
                                        "Missing required parameters for SQL query generation"
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Pattern 3 -->
                        <div class="rounded-lg border border-gray-200 p-4 space-y-3 hover:bg-gray-50 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-gray-900">Tool Execution Timeout</h3>
                                        <span class="badge impact-low">low impact</span>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Occurred 10 times in the last 7 days
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p class="text-sm font-medium text-gray-900">Affected Agents:</p>
                                <div class="flex flex-wrap gap-1">
                                    <span class="badge badge-secondary text-xs">Data Analyst Agent</span>
                                    <span class="badge badge-secondary text-xs">Code Review Agent</span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p class="text-sm font-medium text-gray-900">Example Failures:</p>
                                <ul class="space-y-1">
                                    <li class="text-xs text-gray-500 pl-4 relative before:content-['•'] before:absolute before:left-0">
                                        "Database query timeout after 30s - connection pool exhausted"
                                    </li>
                                    <li class="text-xs text-gray-500 pl-4 relative before:content-['•'] before:absolute before:left-0">
                                        "Web search tool timed out - external API unresponsive"
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Pattern 4 -->
                        <div class="rounded-lg border border-gray-200 p-4 space-y-3 hover:bg-gray-50 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-gray-900">Validation Rule Violations</h3>
                                        <span class="badge impact-medium">medium impact</span>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Occurred 5 times in the last 7 days
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p class="text-sm font-medium text-gray-900">Affected Agents:</p>
                                <div class="flex flex-wrap gap-1">
                                    <span class="badge badge-secondary text-xs">Content Writer Agent</span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <p class="text-sm font-medium text-gray-900">Example Failures:</p>
                                <ul class="space-y-1">
                                    <li class="text-xs text-gray-500 pl-4 relative before:content-['•'] before:absolute before:left-0">
                                        "Generated content exceeds character limit (1500 > 1000)"
                                    </li>
                                    <li class="text-xs text-gray-500 pl-4 relative before:content-['•'] before:absolute before:left-0">
                                        "Response format doesn't match required JSON schema"
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Optimization Suggestions -->
            <div class="stat-card">
                <div class="p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-500">
                            <path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"></path>
                            <path d="M9 18h6"></path>
                            <path d="M10 22h4"></path>
                        </svg>
                        <h2 class="text-xl font-semibold text-gray-900">Optimization Recommendations</h2>
                    </div>
                    <p class="text-gray-500 mb-6">
                        AI-generated suggestions to improve agent performance, reliability, and cost efficiency
                    </p>
                    
                    <div class="space-y-4">
                        <!-- Optimization 1 -->
                        <div class="rounded-lg border-2 priority-high p-4 space-y-3">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 space-y-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-semibold text-gray-900">Implement API Rate Limit Retry Logic</h3>
                                        <span class="badge badge-outline priority-high text-xs">high priority</span>
                                        <span class="badge badge-secondary text-xs gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"></path>
                                            </svg>
                                            performance
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Add exponential backoff and retry logic for API calls to handle rate limiting gracefully
                                    </p>
                                </div>
                                <button class="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    Apply
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500">Estimated Impact</p>
                                    <p class="font-medium text-green-600">+8% success rate</p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Implementation</p>
                                    <p class="font-medium text-gray-900 capitalize">low</p>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <p class="text-xs text-gray-500">Affected Agents:</p>
                                <div class="flex flex-wrap gap-1">
                                    <span class="badge badge-secondary text-xs">Customer Support Agent</span>
                                    <span class="badge badge-secondary text-xs">Content Writer Agent</span>
                                    <span class="badge badge-secondary text-xs">Data Analyst Agent</span>
                                </div>
                            </div>
                        </div>

                        <!-- Optimization 2 -->
                        <div class="rounded-lg border-2 priority-medium p-4 space-y-3">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 space-y-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-semibold text-gray-900">Optimize LLM Temperature Settings</h3>
                                        <span class="badge badge-outline priority-medium text-xs">medium priority</span>
                                        <span class="badge badge-secondary text-xs gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                            </svg>
                                            cost
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Adjust temperature from 0.7 to 0.5 for deterministic tasks to reduce token usage
                                    </p>
                                </div>
                                <button class="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    Apply
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500">Estimated Impact</p>
                                    <p class="font-medium text-green-600">Save $1.20/day</p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Implementation</p>
                                    <p class="font-medium text-gray-900 capitalize">low</p>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <p class="text-xs text-gray-500">Affected Agents:</p>
                                <div class="flex flex-wrap gap-1">
                                    <span class="badge badge-secondary text-xs">Customer Support Agent</span>
                                    <span class="badge badge-secondary text-xs">Content Writer Agent</span>
                                </div>
                            </div>
                        </div>

                        <!-- Optimization 3 -->
                        <div class="rounded-lg border-2 priority-high p-4 space-y-3">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 space-y-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-semibold text-gray-900">Add Input Validation Layer</h3>
                                        <span class="badge badge-outline priority-high text-xs">high priority</span>
                                        <span class="badge badge-secondary text-xs gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                            </svg>
                                            reliability
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Pre-validate user inputs before agent execution to catch errors early
                                    </p>
                                </div>
                                <button class="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    Apply
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500">Estimated Impact</p>
                                    <p class="font-medium text-green-600">+5% success rate</p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Implementation</p>
                                    <p class="font-medium text-gray-900 capitalize">medium</p>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <p class="text-xs text-gray-500">Affected Agents:</p>
                                <div class="flex flex-wrap gap-1">
                                    <span class="badge badge-secondary text-xs">Data Analyst Agent</span>
                                    <span class="badge badge-secondary text-xs">Code Review Agent</span>
                                </div>
                            </div>
                        </div>

                        <!-- Optimization 4 -->
                        <div class="rounded-lg border-2 priority-medium p-4 space-y-3">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 space-y-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-semibold text-gray-900">Implement Response Caching</h3>
                                        <span class="badge badge-outline priority-medium text-xs">medium priority</span>
                                        <span class="badge badge-secondary text-xs gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <path d="M12 16v-4"></path>
                                                <path d="M12 8h.01"></path>
                                            </svg>
                                            accuracy
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Cache frequent queries to reduce redundant LLM calls and improve consistency
                                    </p>
                                </div>
                                <button class="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    Apply
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500">Estimated Impact</p>
                                    <p class="font-medium text-green-600">Save $0.50/day</p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Implementation</p>
                                    <p class="font-medium text-gray-900 capitalize">medium</p>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <p class="text-xs text-gray-500">Affected Agents:</p>
                                <div class="flex flex-wrap gap-1">
                                    <span class="badge badge-secondary text-xs">Customer Support Agent</span>
                                </div>
                            </div>
                        </div>

                        <!-- Optimization 5 -->
                        <div class="rounded-lg border-2 priority-low p-4 space-y-3">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 space-y-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="font-semibold text-gray-900">Increase Tool Timeout Threshold</h3>
                                        <span class="badge badge-outline priority-low text-xs">low priority</span>
                                        <span class="badge badge-secondary text-xs gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"></path>
                                            </svg>
                                            performance
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Increase timeout from 30s to 45s for complex database queries
                                    </p>
                                </div>
                                <button class="px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    Apply
                                </button>
                            </div>

                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500">Estimated Impact</p>
                                    <p class="font-medium text-green-600">+3% success rate</p>
                                </div>
                                <div>
                                    <p class="text-gray-500">Implementation</p>
                                    <p class="font-medium text-gray-900 capitalize">low</p>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <p class="text-xs text-gray-500">Affected Agents:</p>
                                <div class="flex flex-wrap gap-1">
                                    <span class="badge badge-secondary text-xs">Data Analyst Agent</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analysis Summary -->
            <div class="stat-card">
                <div class="p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Analysis Summary</h2>
                    <p class="text-gray-500 mb-4">
                        Generated on {{ now()->format('F j, Y') }} at {{ now()->format('g:i A') }}
                    </p>
                    
                    <div class="space-y-4">
                        <div class="rounded-lg bg-gray-50 p-4 space-y-2">
                            <p class="text-sm font-medium text-gray-900">Key Findings:</p>
                            <ul class="space-y-2 text-sm text-gray-500">
                                <li class="pl-4 relative before:content-['•'] before:absolute before:left-0">
                                    <strong class="text-gray-900">58 total failures</strong> analyzed across all agents in the past 7 days
                                </li>
                                <li class="pl-4 relative before:content-['•'] before:absolute before:left-0">
                                    <strong class="text-gray-900">4 distinct failure patterns</strong> identified with API rate limiting being the most critical
                                </li>
                                <li class="pl-4 relative before:content-['•'] before:absolute before:left-0">
                                    <strong class="text-gray-900">5 optimization opportunities</strong> discovered with potential to improve success rate by up to 16%
                                </li>
                                <li class="pl-4 relative before:content-['•'] before:absolute before:left-0">
                                    Implementing high-priority optimizations could save <strong class="text-gray-900">~$2.10/day</strong> in operational costs
                                </li>
                            </ul>
                        </div>

                        <div class="flex items-center justify-between pt-2">
                            <p class="text-sm text-gray-500">
                                Next analysis scheduled: Tomorrow at 9:00 AM
                            </p>
                            <button class="text-sm text-blue-600 hover:text-blue-800 focus:outline-none">
                                View Full Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')