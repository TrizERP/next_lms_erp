    <style>
            /* Custom overrides - ensure side panel & modals behave correctly with Livewire */
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
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .intent-item:hover {
                background: #e9ecef;
                transform: translateX(5px);
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

            /* Light color fee option cards */
            .fee-option-card {
                background: #fef9e3;
                border-left: 5px solid #f59e0b;
                border-radius: 16px;
                padding: 10px 15px;
                margin: 8px 0;
                transition: all 0.2s ease;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: space-between;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            }
            .fee-option-card:hover {
                background: #fffbeb;
                transform: translateX(4px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            }
            .fee-option-icon {
                font-size: 1.25rem;
                font-weight: 600;
                background: #fff7e6;
                width: 32px;
                height: 32px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 50%;
            }
            .fee-option-title {
                font-weight: 600;
                color: #92400e;
            }
            .fee-option-desc {
                font-size: 0.75rem;
                color: #b45309;
            }
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
    <div id="customSideModal" class="custom-side-modal"><div class="custom-modal-content"><div class="custom-modal-header"><h5><i class="fas fa-comment-plus me-2" style="color: #0d6efd;"></i> New Conversation</h5><button type="button" class="close-modal-btn" id="closeModalBtn" aria-label="Close" style="border:none !important;background:transparent !important; font-size: 1.8rem; cursor: pointer;">×</button></div><div class="custom-modal-body"><p style="margin-bottom: 10px;">Start a new conversation?</p><small style="color: #6c757d;">This will clear the current chat history.</small></div><div class="custom-modal-footer"><button type="button" class="btn-custom-secondary" id="cancelModalBtn">Cancel</button><button type="button" class="btn-custom-primary" id="confirmNewChatModal">Yes, Start New</button></div></div></div>
    <div id="detailsModal" class="details-modal"><div class="details-modal-content"><div class="details-modal-header"><h5><i class="fas fa-info-circle me-2" style="color: #0d6efd;"></i> System Details</h5><button type="button" class="close-modal-btn" id="closeDetailsModalBtn" aria-label="Close" style="border:none !important;background:transparent !important; font-size: 1.8rem; cursor: pointer;">×</button></div><div class="details-modal-body"><p><strong>Conversational AI Assistant</strong></p><p>Version: 1.0.0</p><p><strong>Features:</strong></p><ul style="margin-bottom: 15px;"><li>Student Details Query</li><li>Fees Details Query</li><li>Admission Details Query</li><li>Remaining Fees Query</li><li>Paid Fees Query</li><li>Database Query Support</li></ul><p><strong>How to use:</strong></p><p>Type your query naturally. Example: "Show student details", "Get fees details", "Show admission details"</p></div><div class="details-modal-footer"><button type="button" class="btn-custom-primary" id="closeDetailsBtn">Close</button></div></div></div>
    <div id="intentModal" class="intent-modal"><div class="intent-modal-content"><div class="intent-modal-header"><h5><i class="fas fa-list me-2" style="color: #0d6efd;"></i> Available Intents</h5><button type="button" class="close-modal-btn" id="closeIntentModalBtn" aria-label="Close" style="border:none !important;background:transparent !important; font-size: 1.8rem; cursor: pointer;">×</button></div><div class="intent-modal-body"><div class="intent-list" id="intentListContent">
            @foreach($intents as $intent)
            <div class="intent-item" data-intent="{{ $intent }}" onclick="selectIntent(this)">{{ $intent }}</div>
            @endforeach
            </div></div><div class="intent-modal-footer"><button type="button" class="btn-custom-primary" id="closeIntentBtn">Close</button></div></div></div>

   <script>
    document.addEventListener('alpine:init', () => {});
    document.addEventListener('livewire:navigated', () => { initChatbot(); });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initChatbot);
    else initChatbot();

    function initChatbot() {
        const $chatPanel = document.getElementById('conversationAI');
        const $chatMessages = document.getElementById('chatMessages');
        const $chatInput = document.getElementById('chatInput');
        const $sendButton = document.getElementById('sendButton');
        const $loadingIndicator = document.getElementById('loadingIndicator');
        const $expandBtn = document.getElementById('expandPanelBtn');
        const $newConvBtn = document.getElementById('newConversationBtn');
        const $customModal = document.getElementById('customSideModal');
        const $closeModalBtn = document.getElementById('closeModalBtn');
        const $cancelModalBtn = document.getElementById('cancelModalBtn');
        const $confirmNewChatModal = document.getElementById('confirmNewChatModal');
        const $detailsModal = document.getElementById('detailsModal');
        const $closeDetailsModalBtn = document.getElementById('closeDetailsModalBtn');
        const $closeDetailsBtn = document.getElementById('closeDetailsBtn');
        const $intentModal = document.getElementById('intentModal');
        const $closeIntentModalBtn = document.getElementById('closeIntentModalBtn');
        const $closeIntentBtn = document.getElementById('closeIntentBtn');
         let messages = [];
         let sessionId = 'session_' + Date.now();
         let pendingEnrollmentAction = null;
         let isExpanded = false;
         let currentEnrollmentInputId = null;
         let isDropdownOpen = false;
         let pendingFeeType = null;  // For fee disambiguation: 'total_fees', 'paid_fees', 'remain_fees'
         let pendingMockQuery = false;  // For mock query disambiguation
         let pendingMockQueryType = null;  // Stores the selected type: 'total' or 'list'
         let pendingMockQuerySentence = '';  // Stores the original sentence when waiting for type selection
         let pendingMockQuerySelection = null; // Add this missing variable

        const $dropdownBtn = document.getElementById('optionsDropdownBtn');
        const $dropdownMenu = document.getElementById('optionsDropdownMenu');

        function formatTime(date) { return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }
        function formatSQL(sql) { return sql.replace(/\b(SELECT|FROM|WHERE|JOIN|LEFT|RIGHT|INNER|OUTER|GROUP BY|ORDER BY|HAVING|LIMIT)\b/gi, '\n$1').replace(/,/g, ',\n  ').trim(); }
        function generateUUID() { return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16); }); }
        function scrollToBottom() { const container = document.querySelector('.side-panel-body'); if (container) container.scrollTop = container.scrollHeight; }
        function isValidEnrollmentNumber(value) { const enrollmentRegex = /^[a-zA-Z0-9\s\-]+$/; return value && value.trim().length > 0 && enrollmentRegex.test(value.trim()); }
        function showModal(modal) { if (modal) { modal.classList.add('show'); document.body.style.overflow = 'hidden'; } }
        function hideModal(modal) { if (modal) { modal.classList.remove('show'); document.body.style.overflow = ''; } }

        function addMessageToDOM(message) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `d-flex gap-2 ${message.type === 'user' ? 'flex-row-reverse' : ''}`;
            let avatarHtml = message.type === 'user' ? `<div class="flex-shrink-0 avatar avatar-sm bg-secondary-subtle text-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 2rem; height: 2rem; background-color: #e9ecef;"><i class="fas fa-user text-secondary"></i></div>` : `<div class="flex-shrink-0 avatar avatar-sm bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 2rem; height: 2rem; background-color: #dbeafe;"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"></path><rect width="16" height="12" x="4" y="8" rx="2"></rect><path d="M2 14h2"></path><path d="M20 14h2"></path><path d="M15 13v2"></path><path d="M9 13v2"></path></svg></div>`;
            const contentDiv = document.createElement('div'); contentDiv.className = `d-flex flex-column gap-1 ${message.type === 'user' ? 'align-items-end' : ''}`;
            const bubbleDiv = document.createElement('div'); bubbleDiv.className = `${message.type === 'user' ? 'user-message' : 'bot-message'} px-3 py-2`;
            if (message.type === 'bot' && message.isHtml) bubbleDiv.innerHTML = message.content; else bubbleDiv.textContent = message.content;
            contentDiv.appendChild(bubbleDiv);
            if (message.metadata && message.metadata.sql) { const details = document.createElement('details'); details.className = 'w-100 mt-2'; const summary = document.createElement('summary'); summary.className = 'text-muted fw-medium d-flex align-items-center gap-2 small'; summary.innerHTML = '<i class="fas fa-code"></i> View SQL Query <i class="fas fa-chevron-down text-gray-400 ms-auto"></i>'; details.appendChild(summary); const pre = document.createElement('pre'); pre.className = 'sql-block mt-2'; pre.textContent = formatSQL(message.metadata.sql); details.appendChild(pre); contentDiv.appendChild(details); }
            const timeSpan = document.createElement('span'); timeSpan.className = 'text-muted small mt-1'; timeSpan.textContent = formatTime(message.timestamp); contentDiv.appendChild(timeSpan);
            messageDiv.innerHTML = avatarHtml; messageDiv.appendChild(contentDiv); $chatMessages.appendChild(messageDiv); scrollToBottom();
        }

        function resetConversation() {
            messages = []; $chatMessages.innerHTML = ''; pendingEnrollmentAction = null; currentEnrollmentInputId = null; pendingFeeType = null;
            const welcomeHtml = `<div class="flex flex-col items-center justify-center py-4 space-y-4"><div style="display: flex; justify-content: center; align-items: center; width: 100%;"><div class="bg-gradient-primary" style="border-radius: 25%; padding: 20px; border: 1px solid #cbd5e1; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05); display: inline-flex; align-items: center; justify-content: center;"><svg xmlns="http://www.w3.org/2000/svg" width="70" height="70" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: block; margin: 0 auto;"><path d="M12 8V4H8"></path><rect width="16" height="12" x="4" y="8" rx="2"></rect><path d="M2 14h2"></path><path d="M20 14h2"></path><path d="M15 13v2"></path><path d="M9 13v2"></path></svg></div></div><div class="text-center m-3"><h4 class="font-semibold text-gray-800">Conversational AI</h4></div></div><div class="d-flex gap-2 mb-3"><div class="flex-shrink-0 avatar avatar-sm bg-primary-subtle text-primary rounded-circle"><div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 2rem; height: 2rem; background-color: #dbeafe;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bot w-4 h-4 text-blue-600"><path d="M12 8V4H8"></path><rect width="16" height="12" x="4" y="8" rx="2"></rect><path d="M2 14h2"></path><path d="M20 14h2"></path><path d="M15 13v2"></path><path d="M9 13v2"></path></svg></div></div><div class="d-flex flex-column gap-1"><div class="bot-message px-3 py-2">Hello! I am Conversational AI, your assistant to help you with your queries. How can I assist you today?</div></div></div>`;
            $chatMessages.insertAdjacentHTML('beforeend', welcomeHtml); scrollToBottom();
        }

        async function fetchStudentData(enrollmentNo, actionType) {
            if (window.Livewire) {
                const resp = await fetch(`{{route('genkitDetailsAPI')}}?enrollment_no=${encodeURIComponent(enrollmentNo)}&action_type=${encodeURIComponent(actionType)}`, { method: 'GET', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                return await resp.json();
            } else {
                return new Promise((resolve, reject) => $.ajax({ url: "{{route('genkitDetailsAPI')}}", method: "GET", data: { enrollment_no: enrollmentNo, action_type: actionType }, success: resolve, error: reject }));
            }
        }

        async function fetchMockQueries(sentence) {
            if (window.Livewire) {
                const resp = await fetch(`{{route('MockQueriesAPI')}}?sentence=${encodeURIComponent(sentence)}`, { method: 'GET', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await resp.json();
                return data.html || '<div class="text-muted p-2">No data available for this query.</div>';
            } else {
                return new Promise((resolve, reject) => $.ajax({ url: "{{route('MockQueriesAPI')}}", method: "GET", data: { sentence: sentence }, success: (resp) => resolve(resp.html || '<div class="text-muted p-2">No data available.</div>'), error: reject }));
            }
        }

        function askFeeTypeSelection() {
            const optionsHtml = `
                <div class="mb-2"><strong>💰 I see you're asking about fees. Please select the fee type:</strong></div>
                <div class="fee-option-card" data-fee-type="total_fees"><div><span class="fee-option-icon">💵</span> <span class="fee-option-title">Total Fees</span><div class="fee-option-desc">Complete fee structure & total amount</div></div><i class="fas fa-chevron-right text-warning"></i></div>
                <div class="fee-option-card" data-fee-type="paid_fees"><div><span class="fee-option-icon">✅</span> <span class="fee-option-title">Paid Fees</span><div class="fee-option-desc">Amount already paid till date</div></div><i class="fas fa-chevron-right text-success"></i></div>
                <div class="fee-option-card" data-fee-type="remain_fees"><div><span class="fee-option-icon">⏳</span> <span class="fee-option-title">Remaining / Pending Fees</span><div class="fee-option-desc">Outstanding balance to be paid</div></div><i class="fas fa-chevron-right text-danger"></i></div>
            `;
            const botAsk = { id: generateUUID(), type: 'bot', content: optionsHtml, timestamp: new Date(), metadata: { canEscalate: false }, isHtml: true };
            messages.push(botAsk); addMessageToDOM(botAsk);
            setTimeout(() => {
                document.querySelectorAll('.fee-option-card').forEach(card => {
                    card.removeEventListener('click', feeOptionHandler);
                    card.addEventListener('click', feeOptionHandler);
                });
            }, 50);
        }

        function feeOptionHandler(e) {
            const card = e.currentTarget;
            const feeType = card.getAttribute('data-fee-type');
            pendingFeeType = feeType;
            pendingEnrollmentAction = feeType;  // map: total_fees => total_fees, paid_fees => paid_fees, remain_fees => remain_fees
            const uniqueId = 'enrollmentInput_' + Date.now();
            currentEnrollmentInputId = uniqueId;
            const inputHtml = `<div><span class="mb-2 d-block">📌 Please provide the student enrollment number:</span><div class="enrollment-input-group"><input type="text" id="${uniqueId}" class="form-control form-control-sm" placeholder="Enter enrollment no. (e.g., HN-25-379)" style="max-width:200px;" /><button type="button" class="submit-enrollment-btn btn btn-primary btn-sm rounded-pill" data-input-id="${uniqueId}"><i class="fas fa-check"></i></button></div></div>`;
            const askEnroll = { id: generateUUID(), type: 'bot', content: inputHtml, timestamp: new Date(), isHtml: true };
            messages.push(askEnroll); addMessageToDOM(askEnroll);
            setTimeout(() => {
                document.querySelectorAll('.submit-enrollment-btn').forEach(btn => { btn.removeEventListener('click', handleEnrollmentSubmit); btn.addEventListener('click', handleEnrollmentSubmit); });
                const inputField = document.getElementById(uniqueId);
                if (inputField) { inputField.removeEventListener('keypress', handleEnrollmentKeypress); inputField.addEventListener('keypress', handleEnrollmentKeypress); }
            }, 100);
        }

        async function handleEnrollmentSubmit(e) {
            const btn = e.currentTarget;
            const inputId = btn.getAttribute('data-input-id');
            const enrollmentVal = document.getElementById(inputId)?.value.trim() || '';
            if (enrollmentVal && isValidEnrollmentNumber(enrollmentVal)) {
                const actionToUse = pendingEnrollmentAction; // could be "total_fees", "paid_fees", "remain_fees" or other details
                $loadingIndicator.style.display = 'flex';
                try {
                    const result = await fetchStudentData(enrollmentVal, actionToUse);
                    $loadingIndicator.style.display = 'none';
                    const botHtml = result.html || '<div class="text-muted p-2">No data available.</div>';
                    const botMessage = { id: generateUUID(), type: 'bot', content: botHtml, timestamp: new Date(), metadata: { canEscalate: true }, isHtml: true };
                    messages.push(botMessage); addMessageToDOM(botMessage);
                } catch (err) {
                    $loadingIndicator.style.display = 'none';
                    const botMessage = { id: generateUUID(), type: 'bot', content: '<div class="text-danger p-2">Sorry, unable to retrieve data. Please try again later.</div>', timestamp: new Date(), metadata: { canEscalate: false }, isHtml: true };
                    messages.push(botMessage); addMessageToDOM(botMessage);
                }
                pendingEnrollmentAction = null; pendingFeeType = null;
            } else {
                const errorBot = { id: generateUUID(), type: 'bot', content: "Please enter a valid enrollment number (e.g., HN-25-379, 12345, or STUDENT-001).", timestamp: new Date(), metadata: { canEscalate: false }, isHtml: false };
                messages.push(errorBot); addMessageToDOM(errorBot);
            }
         }

        function handleEnrollmentKeypress(e) { if (e.key === 'Enter') { const input = e.currentTarget; const group = input.closest('.enrollment-input-group'); const submitBtn = group?.querySelector('.submit-enrollment-btn'); if (submitBtn) submitBtn.click(); } }

        async function mockQueryTypeHandler(e) {
            const card = e.currentTarget;
            const queryType = card.getAttribute('data-query-type');
            
            // Process the query immediately with the selected type
            pendingMockQuery = false;
            
            $loadingIndicator.style.display = 'flex';
            try {
                // Make the API call with the selected type
                const resp = await fetch(`{{route('MockQueriesAPI')}}?sentence=${encodeURIComponent(pendingMockQuerySentence)}&mock_query_type=${encodeURIComponent(queryType)}&original_sentence=${encodeURIComponent(pendingMockQuerySentence)}`, { 
                    method: 'GET', 
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } 
                });
                const data = await resp.json();
                $loadingIndicator.style.display = 'none';
                const botHtml = data.html || '<div class="text-muted p-2">No data available.</div>';
                const botMessage = { id: generateUUID(), type: 'bot', content: botHtml, timestamp: new Date(), metadata: {}, isHtml: true };
                messages.push(botMessage); addMessageToDOM(botMessage);
            } catch (err) {
                $loadingIndicator.style.display = 'none';
                const botMessage = { id: generateUUID(), type: 'bot', content: '<div class="text-danger p-2">Sorry, unable to retrieve data. Please try again later.</div>', timestamp: new Date(), metadata: { canEscalate: false }, isHtml: true };
                messages.push(botMessage); addMessageToDOM(botMessage);
            }
            
            // Clear the stored sentence
            pendingMockQuerySentence = '';
        }

        async function handleMockQueryDetailSubmit(e) {
            const btn = e.currentTarget;
            const inputId = btn.getAttribute('data-input-id');
            const detailVal = document.getElementById(inputId)?.value.trim() || '';
            
            $loadingIndicator.style.display = 'flex';
            try {
                // Add the detail to the original sentence if provided
                let finalSentence = pendingMockQuerySentence;
                if (detailVal) {
                    finalSentence += ` ${detailVal}`;
                }
                
                const result = await fetchMockQueries(finalSentence);
                $loadingIndicator.style.display = 'none';
                const botHtml = result || '<div class="text-muted p-2">No data available.</div>';
                const botMessage = { id: generateUUID(), type: 'bot', content: botHtml, timestamp: new Date(), metadata: { canEscalate: true }, isHtml: true };
                messages.push(botMessage); addMessageToDOM(botMessage);
            } catch (err) {
                $loadingIndicator.style.display = 'none';
                const botMessage = { id: generateUUID(), type: 'bot', content: '<div class="text-danger p-2">Sorry, unable to retrieve data. Please try again later.</div>', timestamp: new Date(), metadata: { canEscalate: false }, isHtml: true };
                messages.push(botMessage); addMessageToDOM(botMessage);
            }
            
            pendingMockQuery = false;
            pendingMockQuerySelection = null;
            pendingMockQuerySentence = '';
        }

        function handleMockQueryDetailKeypress(e) { if (e.key === 'Enter') { const input = e.currentTarget; const group = input.closest('.enrollment-input-group'); const submitBtn = group?.querySelector('.submit-enrollment-btn'); if (submitBtn) submitBtn.click(); } }

        async function sendMessage(userContent) {
            const content = userContent.trim();
            if (!content) return;
            
            // Check if we're waiting for mock query type selection and user made a selection
            if (pendingMockQuery && (content.toLowerCase().includes('total') || content.toLowerCase().includes('list'))) {
                // Determine the type based on user's response
                const queryType = content.toLowerCase().includes('total') ? 'total' : 'list';
                
                // Reset the pending state
                pendingMockQuery = false;
                pendingMockQueryType = queryType;
                
                // Show loading indicator
                $loadingIndicator.style.display = 'flex';
                try {
                    // Make the API call with the selected type
                    const resp = await fetch(`{{route('MockQueriesAPI')}}?sentence=${encodeURIComponent(pendingMockQuerySentence)}&mock_query_type=${encodeURIComponent(queryType)}&original_sentence=${encodeURIComponent(pendingMockQuerySentence)}`, { 
                        method: 'GET', 
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } 
                    });
                    const data = await resp.json();
                    $loadingIndicator.style.display = 'none';
                    const botHtml = data.html || '<div class="text-muted p-2">No data available.</div>';
                    const botMessage = { id: generateUUID(), type: 'bot', content: botHtml, timestamp: new Date(), metadata: {}, isHtml: true };
                    messages.push(botMessage); addMessageToDOM(botMessage);
                } catch (err) {
                    $loadingIndicator.style.display = 'none';
                    const botMessage = { id: generateUUID(), type: 'bot', content: '<div class="text-danger p-2">Sorry, unable to retrieve data. Please try again later.</div>', timestamp: new Date(), metadata: { canEscalate: false }, isHtml: true };
                    messages.push(botMessage); addMessageToDOM(botMessage);
                }
                
                // Clear the stored sentence
                pendingMockQuerySentence = '';
                return;
            }
            
            const userMessage = { id: Date.now().toString(), type: 'user', content: content, timestamp: new Date(), metadata: {} };
            messages.push(userMessage); addMessageToDOM(userMessage); $chatInput.value = ''; $chatInput.style.height = 'auto'; scrollToBottom();
            
            if (pendingEnrollmentAction && !pendingFeeType && isValidEnrollmentNumber(content)) {
                const action = pendingEnrollmentAction;
                const enrollmentNumber = content;
                pendingEnrollmentAction = null;
                $loadingIndicator.style.display = 'flex';
                try {
                    const result = await fetchStudentData(enrollmentNumber, action);
                    $loadingIndicator.style.display = 'none';
                    const botHtml = result.html || '<div class="text-muted p-2">No data available.</div>';
                    const botMessage = { id: generateUUID(), type: 'bot', content: botHtml, timestamp: new Date(), metadata: { canEscalate: true }, isHtml: true };
                    messages.push(botMessage); addMessageToDOM(botMessage);
                } catch (err) { $loadingIndicator.style.display = 'none'; const botMessage = { id: generateUUID(), type: 'bot', content: '<div class="text-danger p-2">Sorry, unable to retrieve data. Please try again later.</div>', timestamp: new Date(), metadata: { canEscalate: false }, isHtml: true }; messages.push(botMessage); addMessageToDOM(botMessage); }
                return;
            } else if (pendingEnrollmentAction && pendingFeeType && isValidEnrollmentNumber(content)) {
                const actionToUse = pendingEnrollmentAction;
                $loadingIndicator.style.display = 'flex';
                try {
                    const result = await fetchStudentData(content, actionToUse);
                    $loadingIndicator.style.display = 'none';
                    const botHtml = result.html || '<div class="text-muted p-2">No data available.</div>';
                    const botMessage = { id: generateUUID(), type: 'bot', content: botHtml, timestamp: new Date(), metadata: { canEscalate: true }, isHtml: true };
                    messages.push(botMessage); addMessageToDOM(botMessage);
                } catch (err) { $loadingIndicator.style.display = 'none'; const botMessage = { id: generateUUID(), type: 'bot', content: '<div class="text-danger p-2">Sorry, unable to retrieve data. Please try again later.</div>', timestamp: new Date(), metadata: { canEscalate: false }, isHtml: true }; messages.push(botMessage); addMessageToDOM(botMessage); }
                pendingEnrollmentAction = null; pendingFeeType = null;
                return;
            }
            const genericFeeKeywords = ["fee", "fees"];
            const isGenericFee = genericFeeKeywords.some(kw => content.toLowerCase().includes(kw)) && !content.toLowerCase().includes("paid") && !content.toLowerCase().includes("remain") && !content.toLowerCase().includes("pending") && !content.toLowerCase().includes("total") && !content.toLowerCase().includes("structure");
            if (isGenericFee && !content.toLowerCase().includes("student detail") && !content.toLowerCase().includes("admission")) {
                askFeeTypeSelection(); return;
            }
            
            // Check if this is a mock query that needs type selection
            const mockQueryKeywords = ["academic section", "academic sections", "grade", "grades", "standard", "standards", "class", "classes", "division", "divisions", "section", "sections", "student", "students", "subject", "subjects", "batch", "batches", "announcement", "announcements", "notice", "notices", "chapter", "chapters", "payroll type", "payroll types", "period", "periods", "question paper", "question papers", "transport driver", "transport drivers", "transport kilometer rate", "transport kilometer rates", "transport route", "transport routes", "transport vehicle", "transport vehicles", "user profile", "user profiles", "hrms department", "hrms departments"];
            const isMockQuery = mockQueryKeywords.some(kw => content.toLowerCase().includes(kw));
            
            if (isMockQuery) {
                // Ask user to select between total and list
                pendingMockQuery = true;
                pendingMockQuerySentence = content;
                
                const selectHtml = `
                    <div class="mb-2"><strong>📊 Please select how you'd like to view this information:</strong></div>
                    <div class="fee-option-card" data-query-type="total"><div><span class="fee-option-icon">🔢</span> <span class="fee-option-title">Total Count</span><div class="fee-option-desc">Get the total number of items</div></div><i class="fas fa-chevron-right text-info"></i></div>
                    <div class="fee-option-card" data-query-type="list"><div><span class="fee-option-icon">📋</span> <span class="fee-option-title">List View</span><div class="fee-option-desc">Show the list of items</div></div><i class="fas fa-chevron-right text-success"></i></div>
                `;
                
                const botAsk = { id: generateUUID(), type: 'bot', content: selectHtml, timestamp: new Date(), metadata: { canEscalate: false }, isHtml: true };
                messages.push(botAsk); addMessageToDOM(botAsk);
                setTimeout(() => {
                    document.querySelectorAll('.fee-option-card').forEach(card => {
                        card.removeEventListener('click', mockQueryTypeHandler);
                        card.addEventListener('click', mockQueryTypeHandler);
                    });
                }, 50);
                return;
            }
            
            const studentKeywords = ["student detail", "student details", "fees details", "fees detail", "fee details", "fee detail", "admission details", "admission detail","remain fees","fees remain","pending fees","paid fees","fees paid","total fees"];
            const matchedKeyword = studentKeywords.find(kw => content.toLowerCase().includes(kw));
            if (matchedKeyword) {
                let detectedAction = "student_details";
                if (content.toLowerCase().includes("remain") || content.toLowerCase().includes("pending")) detectedAction = "remain_fees";
                else if (content.toLowerCase().includes("paid")) detectedAction = "paid_fees";
                else if (content.toLowerCase().includes("total")) detectedAction = "total_fees";
                else if ((content.toLowerCase().includes("fees") || content.toLowerCase().includes("fee")) && (content.toLowerCase().includes("detail") || content.toLowerCase().includes("details"))) detectedAction = "fees_details";
                else if ((content.toLowerCase().includes("admission") || content.toLowerCase().includes("admissions")) && (content.toLowerCase().includes("detail") || content.toLowerCase().includes("details"))) detectedAction = "admission_details";
                pendingEnrollmentAction = detectedAction;
                const uniqueId = 'enrollmentInput_' + Date.now(); currentEnrollmentInputId = uniqueId;
                const inputHtml = `<div><span class="mb-2 d-block">Please provide the student enrollment number:</span><div class="enrollment-input-group"><input type="text" id="${uniqueId}" class="form-control form-control-sm" placeholder="Enter enrollment no. (e.g., HN-25-379)" style="max-width:200px;" /><button type="button" class="submit-enrollment-btn btn btn-primary btn-sm rounded-pill" data-input-id="${uniqueId}"><i class="fas fa-check"></i></button></div></div>`;
                const botAskMessage = { id: generateUUID(), type: 'bot', content: inputHtml, timestamp: new Date(), metadata: { canEscalate: false }, isHtml: true };
                messages.push(botAskMessage); addMessageToDOM(botAskMessage);
                setTimeout(() => { document.querySelectorAll('.submit-enrollment-btn').forEach(btn => { btn.removeEventListener('click', handleEnrollmentSubmit); btn.addEventListener('click', handleEnrollmentSubmit); }); const inputField = document.getElementById(uniqueId); if (inputField) { inputField.removeEventListener('keypress', handleEnrollmentKeypress); inputField.addEventListener('keypress', handleEnrollmentKeypress); } }, 100);
                return;
            }
            $loadingIndicator.style.display = 'flex';
            try { const htmlContent = await fetchMockQueries(content); $loadingIndicator.style.display = 'none'; const botMessage = { id: generateUUID(), type: 'bot', content: htmlContent, timestamp: new Date(), metadata: {}, isHtml: true }; messages.push(botMessage); addMessageToDOM(botMessage); } catch (err) { $loadingIndicator.style.display = 'none'; const botMessage = { id: generateUUID(), type: 'bot', content: err, timestamp: new Date(), metadata: {}, isHtml: false }; messages.push(botMessage); addMessageToDOM(botMessage); }
        }

        function setupIntentItems() { document.querySelectorAll('#intentListContent .intent-item').forEach(item => { item.removeEventListener('dblclick', (e) => { const txt = e.currentTarget.getAttribute('data-intent') || e.currentTarget.textContent.trim(); if ($chatInput) { $chatInput.value = txt; $chatInput.dispatchEvent(new Event('input')); $chatInput.focus(); hideModal($intentModal); } }); item.addEventListener('dblclick', (e) => { const txt = e.currentTarget.getAttribute('data-intent') || e.currentTarget.textContent.trim(); if ($chatInput) { $chatInput.value = txt; $chatInput.dispatchEvent(new Event('input')); $chatInput.focus(); hideModal($intentModal); } }); }); }

        if ($dropdownBtn) $dropdownBtn.addEventListener('click', (e) => { e.stopPropagation(); if (isDropdownOpen) { $dropdownMenu.classList.remove('show'); isDropdownOpen = false; } else { $dropdownMenu.classList.add('show'); isDropdownOpen = true; } });
        document.addEventListener('click', (e) => { if ($dropdownBtn && !e.target.closest('.custom-dropdown')) { if ($dropdownMenu) $dropdownMenu.classList.remove('show'); isDropdownOpen = false; } });
        if ($newConvBtn) $newConvBtn.addEventListener('click', () => showModal($customModal));
        if ($closeModalBtn) $closeModalBtn.addEventListener('click', () => hideModal($customModal));
        if ($cancelModalBtn) $cancelModalBtn.addEventListener('click', () => hideModal($customModal));
        if ($confirmNewChatModal) $confirmNewChatModal.addEventListener('click', () => { resetConversation(); hideModal($customModal); });
        if (document.getElementById('detailsOption')) document.getElementById('detailsOption').addEventListener('click', (e) => { e.preventDefault(); if ($dropdownMenu) $dropdownMenu.classList.remove('show'); isDropdownOpen = false; showModal($detailsModal); });
        if ($closeDetailsModalBtn) $closeDetailsModalBtn.addEventListener('click', () => hideModal($detailsModal));
        if ($closeDetailsBtn) $closeDetailsBtn.addEventListener('click', () => hideModal($detailsModal));
        if (document.getElementById('intentListsOption')) document.getElementById('intentListsOption').addEventListener('click', (e) => { e.preventDefault(); if ($dropdownMenu) $dropdownMenu.classList.remove('show'); isDropdownOpen = false; showModal($intentModal); setTimeout(setupIntentItems, 50); });
        if ($closeIntentModalBtn) $closeIntentModalBtn.addEventListener('click', () => hideModal($intentModal));
        if ($closeIntentBtn) $closeIntentBtn.addEventListener('click', () => hideModal($intentModal));
        [$customModal, $detailsModal, $intentModal].forEach(modal => { if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) hideModal(modal); }); });
        if ($expandBtn) $expandBtn.addEventListener('click', () => { if (!isExpanded) { $chatPanel.classList.add('expanded', 'open'); isExpanded = true; } else { $chatPanel.classList.remove('expanded'); isExpanded = false; } });
        if (document.getElementById('openConversationAI')) document.getElementById('openConversationAI').addEventListener('click', () => { $chatPanel.classList.add('open'); $chatInput?.focus(); });
        if (document.getElementById('closeConversationAI')) document.getElementById('closeConversationAI').addEventListener('click', () => { $chatPanel.classList.remove('open', 'expanded'); isExpanded = false; });
        if ($sendButton) $sendButton.addEventListener('click', () => sendMessage($chatInput?.value || ''));
        if ($chatInput) { $chatInput.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage($chatInput.value); } }); $chatInput.addEventListener('input', function() { this.style.height = 'auto'; this.style.height = Math.min(this.scrollHeight, 120) + 'px'; }); }
        if (document.getElementById('voiceActor')) document.getElementById('voiceActor').addEventListener('click', () => alert("Voice input feature coming soon!"));
        resetConversation();
        window.openChatbot = function() { if ($chatPanel) $chatPanel.classList.add('open'); if ($chatInput) $chatInput.focus(); };
    }
    document.addEventListener('livewire:update', () => { setTimeout(initChatbot, 100); });
</script>
