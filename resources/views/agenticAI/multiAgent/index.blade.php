@include('../includes.headcss')
@include('../includes.header')
@include('../includes.sideNavigation')

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<style>
    .content-transition {
        transition: margin-left 300ms ease-in-out;
    }
    
    @media (max-width: 768px) {
        .content-area {
            margin-left: 0 !important;
            padding: 1rem;
        }
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid" style="background-color:#fff">
        <!-- Main Content Area -->
        <div id="content-area" class="content-transition bg-white rounded-2xl p-4">
            <!-- MultiAgent component will be loaded here -->
            <div id="multi-agent-container">
                <!-- Loading or content will be inserted here -->
            </div>
        </div>
    </div>
</div>

<script>
    // Global state
    let isSidebarOpen = false;
    let mobileOpen = false;
    
    // Check sidebar state from localStorage
    function checkSidebarState() {
        const sidebarState = localStorage.getItem("sidebarOpen");
        isSidebarOpen = sidebarState === "true";
        updateContentMargin();
    }
    
    // Update content margin based on sidebar state
    function updateContentMargin() {
        const contentArea = document.getElementById('content-area');
        if (contentArea) {
            if (isSidebarOpen) {
                contentArea.classList.remove('ml-2');
                contentArea.classList.add('ml-4');
            } else {
                contentArea.classList.remove('ml-4');
                contentArea.classList.add('ml-2');
            }
        }
    }
    
    // Handle mobile sidebar close
    function handleCloseMobileSidebar() {
        mobileOpen = false;
        // You might need to dispatch an event to update sidebar state
        const event = new Event('mobileSidebarClosed');
        window.dispatchEvent(event);
    }
    
    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Check initial sidebar state
        checkSidebarState();
        
        // Listen for sidebar state changes (custom event)
        window.addEventListener('sidebarStateChange', checkSidebarState);
        
        // Load MultiAgent component
        loadMultiAgent();
        
        // Handle resize events for responsive design
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                document.getElementById('content-area').classList.remove('ml-2', 'ml-4');
            } else {
                updateContentMargin();
            }
        });
    });
    
    // Load MultiAgent component
    async function loadMultiAgent() {
        const container = document.getElementById('multi-agent-container');
        
        try {
            // You can fetch the MultiAgent component via AJAX or load it directly
            // For now, we'll create a simple placeholder
            container.innerHTML = `
                <div class="space-y-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Multi-Agent System</h1>
                        <p class="text-gray-500">Manage and coordinate multiple AI agents</p>
                    </div>
                    
                    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <!-- Agent Card 1 -->
                        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-600">
                                        <path d="M9.5 2A2.5 2.5 0 0 1 12 4.5v15a2.5 2.5 0 0 1-4.96.44 2.5 2.5 0 0 1-2.96-3.08 3 3 0 0 1-.34-5.58 2.5 2.5 0 0 1 1.32-4.24 2.5 2.5 0 0 1 1.98-3A2.5 2.5 0 0 1 9.5 2Z"></path>
                                        <path d="M14.5 2A2.5 2.5 0 0 0 12 4.5v15a2.5 2.5 0 0 0 4.96.44 2.5 2.5 0 0 0 2.96-3.08 3 3 0 0 0 .34-5.58 2.5 2.5 0 0 0-1.32-4.24 2.5 2.5 0 0 0-1.98-3A2.5 2.5 0 0 0 14.5 2Z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">Customer Support Agent</h3>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Active
                                    </span>
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 mb-4">Handles customer queries and support tickets</p>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Success Rate: 94%</span>
                                <span>28 runs today</span>
                            </div>
                        </div>
                        
                        <!-- Agent Card 2 -->
                        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-purple-600">
                                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">Data Analyst Agent</h3>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Active
                                    </span>
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 mb-4">Analyzes data and generates insights</p>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Success Rate: 97%</span>
                                <span>15 runs today</span>
                            </div>
                        </div>
                        
                        <!-- Agent Card 3 -->
                        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-green-600">
                                        <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900">Content Writer Agent</h3>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Idle
                                    </span>
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 mb-4">Creates and edits written content</p>
                            <div class="flex justify-between text-sm text-gray-600">
                                <span>Success Rate: 89%</span>
                                <span>8 runs today</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stats Section -->
                    <div class="grid gap-6 md:grid-cols-3">
                        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                            <h3 class="font-semibold text-gray-900 mb-2">Total Agents</h3>
                            <div class="text-3xl font-bold text-gray-900">8</div>
                            <p class="text-sm text-gray-500">6 active, 2 idle</p>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                            <h3 class="font-semibold text-gray-900 mb-2">Total Runs Today</h3>
                            <div class="text-3xl font-bold text-gray-900">156</div>
                            <p class="text-sm text-gray-500">92% success rate</p>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                            <h3 class="font-semibold text-gray-900 mb-2">Avg Response Time</h3>
                            <div class="text-3xl font-bold text-gray-900">2.4s</div>
                            <p class="text-sm text-gray-500">Across all agents</p>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow border border-gray-200 p-6">
                        <h3 class="font-semibold text-gray-900 mb-4">Quick Actions</h3>
                        <div class="flex flex-wrap gap-4">
                            <button class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Create New Agent
                            </button>
                            <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                Run Agent Coordination
                            </button>
                            <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                View Analytics
                            </button>
                            <button class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                Manage Workflows
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
        } catch (error) {
            console.error('Error loading MultiAgent component:', error);
            container.innerHTML = `
                <div class="text-center py-8">
                    <p class="text-red-500">Failed to load MultiAgent component</p>
                </div>
            `;
        }
    }
    
    // Custom event for sidebar state change
    window.addEventListener('storage', function(e) {
        if (e.key === 'sidebarOpen') {
            const event = new Event('sidebarStateChange');
            window.dispatchEvent(event);
        }
    });
    
    // For mobile sidebar control
    function toggleMobileSidebar() {
        mobileOpen = !mobileOpen;
        // Dispatch event for sidebar component
        const event = new CustomEvent('toggleMobileSidebar', { detail: mobileOpen });
        window.dispatchEvent(event);
    }
</script>

@include('includes.footerJs')
@include('includes.footer')