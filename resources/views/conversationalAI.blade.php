<style>
  
  
    .d-flex .flex-column .gap-1{
        width: 95%;
    }
    .side-panel {
        position: fixed;
        top: 0;
        right: -400px;
        width: 400px;
        height: 100%;
        background: #fff;
        box-shadow: -2px 0 12px rgba(0, 0, 0, 0.08);
        transition: right 0.35s cubic-bezier(.4, 0, .2, 1), width 0.35s cubic-bezier(.4, 0, .2, 1);
        z-index: 1050;
    }

    .side-panel.expanded {
        width: 70vw !important;
        right: 0;
    }

    .side-panel.open {
        right: 0;
    }

    .avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        width: 2rem;
        height: 2rem;
    }

    .avatar-sm {
        width: 1.75rem;
        height: 1.75rem;
        font-size: 0.875rem;
    }

    .bg-gradient-primary {
        background: linear-gradient(135deg, #0d6efd, #6610f2);
    }

    .user-message {
        background-color: #0d6efd;
        color: #fff;
        border-radius: 1rem 1rem 0.25rem 1rem;
        box-shadow: 0 2px 4px rgba(13, 110, 253, .25);
    }

    .bot-message {
        background-color: #f8f9fa;
        color: #212529;
        border-radius: 1rem 1rem 1rem 0.25rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, .06);
    }

    .loading-dot {
        width: 8px;
        height: 8px;
        background-color: #0d6efd;
        border-radius: 50%;
        animation: bounce 1.4s infinite ease-in-out both;
        animation-delay: var(--delay);
    }

    @keyframes bounce {
        0%, 80%, 100% { transform: scale(0); }
        40% { transform: scale(1); }
    }

    .side-panel-body::-webkit-scrollbar {
        width: 6px;
    }

    .side-panel-body::-webkit-scrollbar-thumb {
        background: #ced4da;
        border-radius: 3px;
    }

    /* Modal Styles */
    .custom-side-modal, .details-modal, .intent-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1060;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,0.5);
    }
    
    .custom-side-modal.show, .details-modal.show, .intent-modal.show {
        display: flex !important;
    }
    
    .custom-modal-content, .details-modal-content, .intent-modal-content {
        position: relative;
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.3);
        animation: modalFadeIn 0.25s ease-out;
        overflow: hidden;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
    }
    
    .details-modal-content, .intent-modal-content {
        min-width: 300px;
    }
    
    @keyframes modalFadeIn {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    
    .custom-modal-header, .details-modal-header, .intent-modal-header {
        padding: 1rem 1.25rem;
        background: linear-gradient(135deg, #f8f9fa, #fff);
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .custom-modal-header h5, .details-modal-header h5, .intent-modal-header h5 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: #1e293b;
    }
    
    .custom-modal-body, .details-modal-body, .intent-modal-body {
        padding: 1.25rem;
    }
    
    .intent-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .intent-item {
        padding: 10px 12px;
        background: #f8f9fa;
        border-radius: 8px;
        border-left: 3px solid #0d6efd;
        font-size: 0.85rem;
        color: #2c3e50;
    }
    
    .intent-category {
        font-weight: 700;
        color: #0d6efd;
        margin-bottom: 4px;
        font-size: 0.8rem;
        text-transform: uppercase;
    }
    
    .custom-modal-footer, .details-modal-footer, .intent-modal-footer {
        padding: 0.75rem 1rem;
        border-top: 1px solid #e9ecef;
        display: flex;
        justify-content: center;
        gap: 0.75rem;
        background: #fafbfc;
    }
    
    .btn-custom-primary {
        background: linear-gradient(135deg, #0d6efd, #6610f2);
        border: none;
        color: white;
        padding: 0.35rem 1rem;
        border-radius: 40px;
        font-size: 0.8rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-custom-primary:hover {
        background: linear-gradient(135deg, #0b5ed7, #5a0fc2);
        transform: translateY(-1px);
    }
    
    .btn-custom-secondary {
        background: transparent;
        border: 1px solid #cbd5e1;
        color: #475569;
        padding: 0.35rem 1rem;
        border-radius: 40px;
        font-size: 0.8rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-custom-secondary:hover {
        background: #f1f5f9;
        border-color: #94a3b8;
    }

    .side-panel-content {
        transition: all 0.2s ease;
        height: 100%;
        position: relative;
    }

    .icon-btn1 {
        background: transparent;
        border: none;
        color: white;
        transition: transform 0.2s, background 0.2s;
        border-radius: 8px;
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }

    .icon-btn1:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.05);
    }

    /* Custom Dropdown Styles */
    .custom-dropdown {
        position: relative;
        display: inline-block;
    }
    
    .custom-dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        z-index: 1000;
        display: none;
        min-width: 150px;
        padding: 0.5rem 0;
        margin: 0.125rem 0 0;
        font-size: 0.875rem;
        color: #212529;
        text-align: left;
        list-style: none;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid rgba(0,0,0,.15);
        border-radius: 0.375rem;
        box-shadow: 0 0.5rem 1rem rgba(0,0,0,.175);
    }
    
    .custom-dropdown-menu.show {
        display: block;
    }
    
    .custom-dropdown-item {
        display: block;
        width: 100%;
        padding: 0.5rem 1rem;
        clear: both;
        font-weight: 400;
        color: #212529;
        text-align: inherit;
        text-decoration: none;
        white-space: nowrap;
        background-color: transparent;
        border: 0;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .custom-dropdown-item:hover {
        background-color: #f8f9fa;
        color: #0d6efd;
    }
    
    .enrollment-input-group {
        margin-top: 8px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }
    
    .student-detail-card {
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        width: 95%;
    }
    
    .student-detail-header {
        background: linear-gradient(135deg, #0d6efd, #6610f2);
        color: white;
        padding: 12px 16px;
    }
    
    .student-detail-header h6 {
        margin: 0;
        font-weight: 600;
        font-size: 1rem;
    }
    
    .student-detail-body {
        padding: 16px;
    }
    
    .detail-item {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px solid #e9ecef;
    }
    
    .detail-item:last-child {
        border-bottom: none;
    }
    
    .detail-label {
        width: 120px;
        font-weight: 600;
        color: #495057;
        font-size: 0.85rem;
    }
    
    .detail-value {
        flex: 1;
        color: #212529;
        font-size: 0.85rem;
    }
    
    .modern-fees-card {
        flex: 1;
        min-width: 250px;
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-radius: 16px;
        padding: 16px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        cursor: pointer;
        border: 1px solid #e9ecef;
    }
    
    .modern-fees-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    
    .fees-card-header {
        text-align: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid #e9ecef;
    }
    
    .fees-card-header-title {
        font-size: 1rem;
        font-weight: 700;
        color: #1a1a2e;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .fees-amount-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 16px;
    }
    
    .fees-amount-label {
        font-size: 0.7rem;
        color: #6c757d;
        margin-bottom: 4px;
    }
    
    .fees-amount-value {
        font-size: 1.2rem;
        font-weight: 700;
    }
    
    .fees-amount-value-lg {
        font-size: 1.5rem;
        font-weight: 800;
    }
    
    .totals-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        margin: 20px 0 24px 0;
        padding: 16px;
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border-radius: 20px;
        border: 1px solid #e9ecef;
    }
    
    .fees-card-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin: 16px 0;
    }
    
    .text-success { color: #28a745; }
    .text-warning { color: #ffc107; }
    .text-info { color: #17a2b8; }
    .text-primary { color: #0d6efd; }
    .fw-bold { font-weight: 700; }
</style>

<div id="conversationAI" class="side-panel" style="border-radius:25px;">
    <div class="side-panel-content d-flex flex-column h-100">
        <div class="side-panel-header d-flex justify-content-between align-items-center p-3 border-bottom bg-gradient-primary text-white" style="border-top-left-radius:25px; border-top-right-radius:25px;">
            <div class="d-flex align-items-center gap-2">
                <div class="border rounded-circle p-2 bg-white text-blue">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 8V4H8"></path>
                        <rect width="16" height="12" x="4" y="8" rx="2"></rect>
                        <path d="M2 14h2"></path>
                        <path d="M20 14h2"></path>
                        <path d="M15 13v2"></path>
                        <path d="M9 13v2"></path>
                    </svg>
                </div>
                <h5 class="mt-2 ml-2 mb-0 fw-semibold">Conversational AI</h5>
            </div>
            <div class="d-flex gap-1">
                <!-- Custom Dropdown without Bootstrap -->
                <div class="custom-dropdown">
                    <button type="button" class="icon-btn1" id="optionsDropdownBtn" title="Options">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="1"></circle>
                            <circle cx="12" cy="5" r="1"></circle>
                            <circle cx="12" cy="19" r="1"></circle>
                        </svg>
                    </button>
                    <div class="custom-dropdown-menu" id="optionsDropdownMenu">
                        <a class="custom-dropdown-item" href="#" id="detailsOption">📋 Details</a>
                        <a class="custom-dropdown-item" href="#" id="intentListsOption">🎯 Intent Lists</a>
                    </div>
                </div>
                <button type="button" class="icon-btn1" id="expandPanelBtn" title="Expand">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                    </svg>
                </button>
                <button type="button" class="icon-btn1" id="newConversationBtn" title="New Conversation">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                </button>
                <button type="button" class="icon-btn1" id="closeConversationAI" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div class="side-panel-body flex-fill p-3 overflow-auto">
            <div id="chatMessages" class="d-flex flex-column gap-3"></div>
            <div id="loadingIndicator" class="gap-2" style="display: none;">
                <div class="flex-shrink-0 bg-blue-100">
                    <div class="avatar avatar-sm bg-gradient-primary text-white rounded-circle">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 8V4H8"></path>
                            <rect width="16" height="12" x="4" y="8" rx="2"></rect>
                            <path d="M2 14h2"></path>
                            <path d="M20 14h2"></path>
                            <path d="M15 13v2"></path>
                            <path d="M9 13v2"></path>
                        </svg>
                    </div>
                </div>
                <div class="bg-white rounded-3 px-3 py-2 border d-flex align-items-center gap-2 shadow-sm">
                    <div class="d-flex gap-1">
                        <div class="loading-dot" style="--delay: 0s"></div>
                        <div class="loading-dot" style="--delay: 0.1s"></div>
                        <div class="loading-dot" style="--delay: 0.2s"></div>
                    </div>
                    <span class="text-muted fw-medium small">Analyzing your data...</span>
                </div>
            </div>
        </div>

        <div class="side-panel-footer p-3 border-top bg-light">
            <div id="saveJDBtn" class="d-flex justify-content-end mb-2"></div>
            <div class="bg-white border rounded-3 p-2 shadow-sm" style="border-radius:25px;">
                <div class="input-group" style="display:block;text-align:right;">
                    <textarea id="chatInput" class="form-control border-0 bg-transparent shadow-none" placeholder="Type your message here..." rows="4" style="resize: none; max-height: 120px; border: 1px solid #ced4da; outline: none;width: 100%;" onfocus="this.style.borderColor='#0d6efd';" onblur="this.style.borderColor='#ced4da';"></textarea>
                    <button id="voiceActor" class="btn btn-secondary btn-sm rounded-circle ms-2" type="button"><i class="fas fa-microphone"></i></button>
                    <button id="sendButton" class="btn btn-primary btn-sm rounded-circle ms-2" type="button">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Conversation Modal -->
<div id="customSideModal" class="custom-side-modal">
    <div class="custom-modal-content">
        <div class="custom-modal-header">
            <h5><i class="fas fa-comment-plus me-2" style="color: #0d6efd;"></i> New Conversation</h5>
            <button type="button" class="close-modal-btn" id="closeModalBtn" aria-label="Close" style="border:none !important;background:transparent !important; font-size: 1.8rem; cursor: pointer;">×</button>
        </div>
        <div class="custom-modal-body">
            <p style="margin-bottom: 10px;">Start a new conversation?</p>
            <small style="color: #6c757d;">This will clear the current chat history.</small>
        </div>
        <div class="custom-modal-footer">
            <button type="button" class="btn-custom-secondary" id="cancelModalBtn">Cancel</button>
            <button type="button" class="btn-custom-primary" id="confirmNewChatModal">Yes, Start New</button>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div id="detailsModal" class="details-modal">
    <div class="details-modal-content">
        <div class="details-modal-header">
            <h5><i class="fas fa-info-circle me-2" style="color: #0d6efd;"></i> System Details</h5>
            <button type="button" class="close-modal-btn" id="closeDetailsModalBtn" aria-label="Close" style="border:none !important;background:transparent !important; font-size: 1.8rem; cursor: pointer;">×</button>
        </div>
        <div class="details-modal-body">
            <p><strong>Conversational AI Assistant</strong></p>
            <p>Version: 1.0.0</p>
            <p><strong>Features:</strong></p>
            <ul style="margin-bottom: 15px;">
                <li>Student Details Query</li>
                <li>Fees Details Query</li>
                <li>Admission Details Query</li>
                <li>Remaining Fees Query</li>
                <li>Paid Fees Query</li>
                <li>Database Query Support</li>
            </ul>
            <p><strong>How to use:</strong></p>
            <p>Type your query naturally. Example: "Show student details", "Get fees details", "Show admission details"</p>
        </div>
        <div class="details-modal-footer">
            <button type="button" class="btn-custom-primary" id="closeDetailsBtn">Close</button>
        </div>
    </div>
</div>

<!-- Intent Lists Modal -->
<div id="intentModal" class="intent-modal">
    <div class="intent-modal-content">
        <div class="intent-modal-header">
            <h5><i class="fas fa-list me-2" style="color: #0d6efd;"></i> Available Intents</h5>
            <button type="button" class="close-modal-btn" id="closeIntentModalBtn" aria-label="Close" style="border:none !important;background:transparent !important; font-size: 1.8rem; cursor: pointer;">×</button>
        </div>
        <div class="intent-modal-body">
            <div class="intent-list" id="intentListContent">
                <div class="text-center">Loading intents...</div>
            </div>
        </div>
        <div class="intent-modal-footer">
            <button type="button" class="btn-custom-primary" id="closeIntentBtn">Close</button>
        </div>
    </div>
</div>

<script>
// Wait for jQuery to be fully loaded and document ready
(function($) {
    $(document).ready(function() {
        
        var $chatPanel = $('#conversationAI');
        var $chatMessages = $('#chatMessages');
        var $chatInput = $('#chatInput');
        var $sendButton = $('#sendButton');
        var $loadingIndicator = $('#loadingIndicator');
        var $expandBtn = $('#expandPanelBtn');
        var $newConvBtn = $('#newConversationBtn');
        
        var $customModal = $('#customSideModal');
        var $closeModalBtn = $('#closeModalBtn');
        var $cancelModalBtn = $('#cancelModalBtn');
        var $confirmNewChatModal = $('#confirmNewChatModal');
        
        var $detailsModal = $('#detailsModal');
        var $closeDetailsModalBtn = $('#closeDetailsModalBtn');
        var $closeDetailsBtn = $('#closeDetailsBtn');
        
        var $intentModal = $('#intentModal');
        var $closeIntentModalBtn = $('#closeIntentModalBtn');
        var $closeIntentBtn = $('#closeIntentBtn');

        var messages = [];
        var sessionId = 'session_' + Date.now();
        var pendingEnrollmentAction = null;
        var isExpanded = false;
        var currentEnrollmentInputId = null;

        // Dropdown functionality
        var $dropdownBtn = $('#optionsDropdownBtn');
        var $dropdownMenu = $('#optionsDropdownMenu');
        var isDropdownOpen = false;

        $dropdownBtn.on('click', function(e) {
            e.stopPropagation();
            if (isDropdownOpen) {
                $dropdownMenu.removeClass('show');
                isDropdownOpen = false;
            } else {
                $dropdownMenu.addClass('show');
                isDropdownOpen = true;
            }
        });

        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.custom-dropdown').length) {
                $dropdownMenu.removeClass('show');
                isDropdownOpen = false;
            }
        });

        function resetConversation() {
            messages = [];
            $chatMessages.empty();
            pendingEnrollmentAction = null;
            currentEnrollmentInputId = null;
            
            var welcomeHtml = `
                <div class="flex flex-col items-center justify-center py-4 space-y-4">
                    <div style="display: flex; justify-content: center; align-items: center; width: 100%;">
                        <div class="bg-gradient-primary" style="border-radius: 25%; padding: 20px; border: 1px solid #cbd5e1; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05); display: inline-flex; align-items: center; justify-content: center;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="70" height="70" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: block; margin: 0 auto;">
                                <path d="M12 8V4H8"></path>
                                <rect width="16" height="12" x="4" y="8" rx="2"></rect>
                                <path d="M2 14h2"></path>
                                <path d="M20 14h2"></path>
                                <path d="M15 13v2"></path>
                                <path d="M9 13v2"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="text-center m-3">
                        <h4 class="font-semibold text-gray-800">Conversational AI</h4>
                    </div>
                </div>
                <div class="d-flex gap-2 mb-3">
                    <div class="flex-shrink-0 avatar avatar-sm bg-primary-subtle text-primary rounded-circle">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 2rem; height: 2rem; background-color: #dbeafe;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bot w-4 h-4 text-blue-600">
                                <path d="M12 8V4H8"></path>
                                <rect width="16" height="12" x="4" y="8" rx="2"></rect>
                                <path d="M2 14h2"></path>
                                <path d="M20 14h2"></path>
                                <path d="M15 13v2"></path>
                                <path d="M9 13v2"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-1">
                        <div class="bot-message px-3 py-2">Hello! I am Conversational AI, your assistant to help you with your queries. How can I assist you today?</div>
                    </div>
                </div>
            `;
            $chatMessages.append(welcomeHtml);
            scrollToBottom();
        }

        function loadIntents() {
            $('#intentListContent').html('<div class="text-center">Loading intents...</div>');
            $.ajax({
                url: "{{route('getIntentsList')}}",
                method: "GET",
                success: function(response) {
                    var html = '<div class="intent-list">';
                    if (response.intents && response.intents.length > 0) {
                        response.intents.forEach(function(intent) {
                            html += '<div class="intent-item">' + intent + '</div>';
                        });
                    } else {
                        html += '<div class="text-muted">No intents available</div>';
                    }
                    html += '</div>';
                    $('#intentListContent').html(html);
                },
                error: function() {
                    $('#intentListContent').html('<div class="text-danger">Failed to load intents</div>');
                }
            });
        }

        function addMessageToDOM(message) {
            var $messageDiv = $('<div>').addClass('d-flex gap-2 ' + (message.type === 'user' ? 'flex-row-reverse' : ''));

            var avatarHtml = message.type === 'user' 
                ? `<div class="flex-shrink-0 avatar avatar-sm bg-secondary-subtle text-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 2rem; height: 2rem; background-color: #e9ecef;"><i class="fas fa-user text-secondary"></i></div>`
                : `<div class="flex-shrink-0 avatar avatar-sm bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 2rem; height: 2rem; background-color: #dbeafe;"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"></path><rect width="16" height="12" x="4" y="8" rx="2"></rect><path d="M2 14h2"></path><path d="M20 14h2"></path><path d="M15 13v2"></path><path d="M9 13v2"></path></svg></div>`;
            
            var $avatarDiv = $(avatarHtml);
            var $contentDiv = $('<div>').addClass('d-flex flex-column gap-1 ' + (message.type === 'user' ? 'align-items-end' : ''));
            var $bubbleDiv = $('<div>').addClass((message.type === 'user' ? 'user-message' : 'bot-message') + ' px-3 py-2');
            
            if (message.type === 'bot' && message.isHtml) {
                $bubbleDiv.html(message.content);
            } else {
                $bubbleDiv.text(message.content);
            }
            $contentDiv.append($bubbleDiv);

            if (message.metadata && message.metadata.sql) {
                var $sqlDetails = $('<details>').addClass('w-100 mt-2');
                $sqlDetails.append($('<summary>').addClass('text-muted fw-medium d-flex align-items-center gap-2 small').html('<i class="fas fa-code"></i> View SQL Query <i class="fas fa-chevron-down text-gray-400 ms-auto"></i>'));
                $sqlDetails.append($('<pre>').addClass('sql-block mt-2').text(formatSQL(message.metadata.sql)));
                $contentDiv.append($sqlDetails);
            }

            $contentDiv.append($('<span>').addClass('text-muted small mt-1').text(formatTime(message.timestamp)));
            $messageDiv.append($avatarDiv).append($contentDiv);
            $chatMessages.append($messageDiv);
            scrollToBottom();
        }

        function fetchStudentData(enrollmentNo, actionType, callback) {
            $.ajax({
                url: "{{route('genkitDetailsAPI')}}",
                method: "GET",
                data: { enrollment_no: enrollmentNo, action_type: actionType },
                success: function(response) { callback(null, response); },
                error: function(xhr) { callback("Error fetching data", null); }
            });
        }

        function fetchMockQueries(sentence, callback) {
            $.ajax({
                url: "{{route('MockQueriesAPI')}}",
                method: "GET",
                data: { sentence: sentence },
                success: function(response) { 
                    var htmlContent = response.html || '<div class="text-muted p-2">No data available for this query.</div>';
                    callback(null, htmlContent); 
                },
                error: function(xhr) { 
                    callback("Error fetching mock data. Please try again later.", null); 
                }
            });
        }

        function isValidEnrollmentNumber(value) {
            var enrollmentRegex = /^[a-zA-Z0-9\s\-]+$/;
            return value && value.trim().length > 0 && enrollmentRegex.test(value.trim());
        }

        function sendMessage(userContent) {
            var content = userContent.trim();
            if (!content) return;

            var userMessage = {
                id: Date.now().toString(),
                type: 'user',
                content: content,
                timestamp: new Date(),
                metadata: {}
            };
            messages.push(userMessage);
            addMessageToDOM(userMessage);
            $chatInput.val('').css('height', 'auto');
            scrollToBottom();

            if (pendingEnrollmentAction && isValidEnrollmentNumber(content)) {
                var action = pendingEnrollmentAction;
                var enrollmentNumber = content;
                pendingEnrollmentAction = null;
                $loadingIndicator.show();
                
                fetchStudentData(enrollmentNumber, action, function(err, result) {
                    $loadingIndicator.hide();
                    var botHtml = err ? '<div class="text-danger p-2">Sorry, unable to retrieve data. Please try again later.</div>' : (result.html || '<div class="text-muted p-2">No data available.</div>');
                    
                    var botMessage = {
                        id: generateUUID(),
                        type: 'bot',
                        content: botHtml,
                        timestamp: new Date(),
                        metadata: { canEscalate: true },
                        isHtml: true
                    };
                    messages.push(botMessage);
                    addMessageToDOM(botMessage);
                    scrollToBottom();
                });
                return;
            } 
            else if (pendingEnrollmentAction && !isValidEnrollmentNumber(content)) {
                var errorBot = {
                    id: generateUUID(),
                    type: 'bot',
                    content: "Please enter a valid enrollment number (e.g., HN-25-379, 12345, or STUDENT-001).",
                    timestamp: new Date(),
                    metadata: { canEscalate: false },
                    isHtml: false
                };
                messages.push(errorBot);
                addMessageToDOM(errorBot);
                scrollToBottom();
                return;
            }

            var studentKeywords = ["student detail", "student details", "fees details", "fees detail", "fee details", "fee detail", "admission details", "admission detail","remain fees","fees remain","pending fees","paid fees","fees paid"];
            var matchedKeyword = studentKeywords.find(kw => content.toLowerCase().includes(kw));
            
            if (matchedKeyword) {
                var detectedAction = "student_details";
                if ((content.toLowerCase().includes("remain") && content.toLowerCase().includes("fees")) || content.toLowerCase().includes("pending") && content.toLowerCase().includes("fees")) {
                    detectedAction = "remain_fees";
                } else if (content.toLowerCase().includes("paid") && content.toLowerCase().includes("fees")) {
                    detectedAction = "paid_fees";
                } else if (content.toLowerCase().includes("fees") || content.toLowerCase().includes("fee") && (content.toLowerCase().includes("detail") || content.toLowerCase().includes("details"))) {
                    detectedAction = "fees_details";
                } else if (content.toLowerCase().includes("admission") || content.toLowerCase().includes("admissions") && (content.toLowerCase().includes("detail") || content.toLowerCase().includes("details"))) {
                    detectedAction = "admission_details";
                }
                
                pendingEnrollmentAction = detectedAction;
                var uniqueId = 'enrollmentInput_' + Date.now();
                currentEnrollmentInputId = uniqueId;
                
                var inputHtml = `
                    <div>
                        <span class="mb-2 d-block">Please provide the student enrollment number:</span>
                        <div class="enrollment-input-group">
                            <input type="text" id="${uniqueId}" class="form-control form-control-sm" placeholder="Enter enrollment no. (e.g., HN-25-379)" style="max-width:200px;" />
                            <button type="button" class="submit-enrollment-btn btn btn-primary btn-sm rounded-pill" data-input-id="${uniqueId}"><i class="fas fa-check"></i></button>
                        </div>
                    </div>
                `;
                
                var botAskMessage = {
                    id: generateUUID(),
                    type: 'bot',
                    content: inputHtml,
                    timestamp: new Date(),
                    metadata: { canEscalate: false },
                    isHtml: true
                };
                messages.push(botAskMessage);
                addMessageToDOM(botAskMessage);
                
                setTimeout(function() {
                    $(document).off('click', '.submit-enrollment-btn').on('click', '.submit-enrollment-btn', function() {
                        var inputId = $(this).data('input-id');
                        var enrollmentVal = $('#' + inputId).val().trim();
                        if (enrollmentVal && isValidEnrollmentNumber(enrollmentVal)) {
                            sendMessage(enrollmentVal);
                        } else {
                            var errorBot = {
                                id: generateUUID(),
                                type: 'bot',
                                content: "Please enter a valid enrollment number (e.g., HN-25-379, 12345, or STUDENT-001).",
                                timestamp: new Date(),
                                metadata: { canEscalate: false },
                                isHtml: false
                            };
                            messages.push(errorBot);
                            addMessageToDOM(errorBot);
                        }
                    });
                    
                    $(document).off('keypress', '#' + uniqueId).on('keypress', '#' + uniqueId, function(e) {
                        if (e.key === 'Enter') {
                            $(this).closest('.enrollment-input-group').find('.submit-enrollment-btn').click();
                        }
                    });
                }, 100);
                return;
            }
            
            $loadingIndicator.show();
            
            fetchMockQueries(content, function(err, htmlContent) {
                $loadingIndicator.hide();
                
                var botResponseText = "";
                var metadata = { sql: null, canEscalate: false };
                var isHtmlResponse = false;
                
                if (err) {
                    botResponseText = err;
                } else {
                    botResponseText = htmlContent;
                    isHtmlResponse = true;
                }
                
                var botMessage = {
                    id: generateUUID(),
                    type: 'bot',
                    content: botResponseText,
                    timestamp: new Date(),
                    metadata: metadata,
                    isHtml: isHtmlResponse
                };
                messages.push(botMessage);
                addMessageToDOM(botMessage);
                scrollToBottom();
            });
        }

        function formatSQL(sql) {
            return sql.replace(/\b(SELECT|FROM|WHERE|JOIN|LEFT|RIGHT|INNER|OUTER|GROUP BY|ORDER BY|HAVING|LIMIT)\b/gi, '\n$1').replace(/,/g, ',\n  ').trim();
        }
        
        function formatTime(date) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        function scrollToBottom() {
            $('.side-panel-body').scrollTop($('.side-panel-body')[0].scrollHeight);
        }

        function generateUUID() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16 | 0;
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
        }

        function showModal(modal) { 
            modal.addClass('show'); 
            $('body').css('overflow', 'hidden');
        }
        
        function hideModal(modal) { 
            modal.removeClass('show'); 
            $('body').css('overflow', '');
        }

        // New Conversation Modal Events
        $newConvBtn.on('click', function() { showModal($customModal); });
        $closeModalBtn.on('click', function() { hideModal($customModal); });
        $cancelModalBtn.on('click', function() { hideModal($customModal); });
        $confirmNewChatModal.on('click', function() { resetConversation(); hideModal($customModal); });
        
        // Details Modal Events
        $('#detailsOption').on('click', function(e) {
            e.preventDefault();
            $dropdownMenu.removeClass('show');
            isDropdownOpen = false;
            showModal($detailsModal);
        });
        $closeDetailsModalBtn.on('click', function() { hideModal($detailsModal); });
        $closeDetailsBtn.on('click', function() { hideModal($detailsModal); });
        
        // Intent Modal Events
        $('#intentListsOption').on('click', function(e) {
            e.preventDefault();
            $dropdownMenu.removeClass('show');
            isDropdownOpen = false;
            loadIntents();
            showModal($intentModal);
        });
        $closeIntentModalBtn.on('click', function() { hideModal($intentModal); });
        $closeIntentBtn.on('click', function() { hideModal($intentModal); });
        
        // Close modals when clicking outside
        $customModal.on('click', function(e) { if ($(e.target).is($customModal)) hideModal($customModal); });
        $detailsModal.on('click', function(e) { if ($(e.target).is($detailsModal)) hideModal($detailsModal); });
        $intentModal.on('click', function(e) { if ($(e.target).is($intentModal)) hideModal($intentModal); });

        $expandBtn.on('click', function() {
            if (!isExpanded) {
                $chatPanel.addClass('expanded').addClass('open');
                isExpanded = true;
            } else {
                $chatPanel.removeClass('expanded');
                isExpanded = false;
            }
        });

        $(document).on('click', '#openConversationAI', function() {
            $chatPanel.addClass('open');
            $chatInput.focus();
        });
        
        $('#closeConversationAI').on('click', function() {
            $chatPanel.removeClass('open').removeClass('expanded');
            isExpanded = false;
        });
        
        $sendButton.on('click', function() { sendMessage($chatInput.val()); });
        
        $chatInput.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage($chatInput.val());
            }
        });
        
        $chatInput.on('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        
        $('#voiceActor').on('click', function() { alert("Voice input feature coming soon!"); });
        
        resetConversation();
        
        window.openChatbot = function() { 
            $chatPanel.addClass('open'); 
            $chatInput.focus(); 
        };
    });
})(jQuery);
</script>