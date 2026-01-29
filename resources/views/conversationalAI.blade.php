<!-- Side panel -->
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<div id="conversationAI" class="side-panel">
    <div class="side-panel-content">
        <div class="side-panel-header">
            <div class="d-flex align-items-center gap-2">
                <i class="fas fa-robot text-purple-600"></i>
                <h5 class="mb-0">Data Copilot</h5>
            </div>
            <button type="button" class="close" id="closeConversationAI">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="side-panel-body">
            <!-- Messages Container -->
            <div id="chatMessages" class="d-flex flex-column gap-3 overflow-auto" style="max-height: calc(100vh - 250px);">
                <!-- Initial bot message -->
                <div class="d-flex gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 rounded-circle d-flex align-items-center justify-content-center bg-blue-100">
                            <i class="fas fa-robot text-blue-600"></i>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <div class="bg-gray-100 text-gray-800 rounded-3 px-3 py-2">
                            Hello! 👋 I'm your AI Data Assistant. I can help you analyze data, generate SQL queries, and provide insights. What would you like to know?
                        </div>
                        <span class="text-xs text-gray-500 px-1">
                            {{ now()->format('h:i A') }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Loading Indicator (Hidden by default) -->
            <div id="loadingIndicator" class="d-flex gap-3" style="display: none !important;">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 rounded-circle d-flex align-items-center justify-content-center bg-gradient-to-br from-blue-500 to-purple-500 text-white">
                        <i class="fas fa-robot"></i>
                    </div>
                </div>
                <div class="bg-white rounded-3 px-3 py-2 border border-gray-200 d-flex align-items-center gap-2">
                    <div class="d-flex gap-1">
                        <div class="loading-dot" style="--delay: 0s"></div>
                        <div class="loading-dot" style="--delay: 0.1s"></div>
                        <div class="loading-dot" style="--delay: 0.2s"></div>
                    </div>
                    <span class="text-sm text-gray-600 font-medium">Analyzing your data...</span>
                </div>
            </div>
        </div>
        <div class="side-panel-footer">
            <!-- Save JD Button -->
            <div id="saveJDBtn" class="d-flex justify-content-end mb-2">
                <!-- <button type="button" id="saveJDBtn" class="btn btn-sm btn-primary">
                    Save JD
                </button> -->
            </div>

            <!-- Input Area -->
            <div class="bg-gray-50 border rounded-3 p-2">
                <div class="input-group">
                    <textarea 
                        id="chatInput" 
                        class="form-control border-0 bg-transparent" 
                        placeholder="Ask a question..." 
                        rows="1"
                        style="resize: none; max-height: 120px;"
                    ></textarea>
                    <button id="sendButton" class="btn btn-primary btn-sm rounded-circle ms-2" type="button">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                    <span class="text-xs text-gray-500">
                        AI-generated content may be incorrect
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .side-panel {
        position: fixed;
        top: 0;
        right: -400px;
        width: 400px;
        height: 100%;
        background: #fff;
        box-shadow: -2px 0 8px rgba(0,0,0,0.15);
        transition: right 0.3s ease;
        z-index: 1050;
    }
    .side-panel.open {
        right: 0;
    }
    .side-panel-content {
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .side-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
    }
    .side-panel-body {
        flex: 1;
        padding: 1rem;
        overflow-y: auto;
    }
    .side-panel-footer {
        padding: 1rem;
        border-top: 1px solid #dee2e6;
    }
    
    /* Loading dots animation */
    .loading-dot {
        width: 8px;
        height: 8px;
        background-color: #3b82f6;
        border-radius: 50%;
        animation: bounce 1.4s infinite ease-in-out both;
        animation-delay: var(--delay);
    }
    
    @keyframes bounce {
        0%, 80%, 100% {
            transform: scale(0);
        }
        40% {
            transform: scale(1);
        }
    }
    
    /* User message styling */
    .user-message {
        background-color: #3b82f6 !important;
        color: white !important;
        margin-left: auto;
    }
    
    /* Bot message styling */
    .bot-message {
        background-color: #f3f4f6 !important;
        color: #374151 !important;
    }
    
    /* SQL code block */
    .sql-block {
        background-color: #1f2937;
        color: #10b981;
        font-family: 'Courier New', monospace;
        font-size: 0.875rem;
        border-radius: 0.75rem;
        border: 1px solid #374151;
        overflow-x: auto;
    }
    
    /* Table badge */
    .table-badge {
        background-color: #dbeafe;
        color: #1e40af;
        border: 1px solid #93c5fd;
        font-size: 0.75rem;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const chatPanel = document.getElementById('conversationAI');
    const chatMessages = document.getElementById('chatMessages');
    const chatInput = document.getElementById('chatInput');
    const sendButton = document.getElementById('sendButton');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const saveJDBtn = document.getElementById('saveJDBtn');
    
    // State
    let messages = [{
        id: '1',
        type: 'bot',
        content: "Hello! 👋 I'm your AI Data Assistant. I can help you analyze data, generate SQL queries, and provide insights. What would you like to know?",
        timestamp: new Date()
    }];
    let sessionId = `session_${Date.now()}`;
    let conversationId = null;
    
    // Auto-resize textarea
    chatInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    
    // Enter key to send (Shift+Enter for new line)
    chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    // Send button click
    sendButton.addEventListener('click', sendMessage);
    
    // Save JD button
    saveJDBtn.addEventListener('click', function() {
        console.log("Save JD clicked");
        // Future: Add save JD logic here
    });
    
    // Open/close panel event listeners
    document.getElementById('openConversationAI').addEventListener('click', function() {
        chatPanel.classList.add('open');
        chatInput.focus();
    });
    
    document.getElementById('closeConversationAI').addEventListener('click', function() {
        chatPanel.classList.remove('open');
    });
    
    document.getElementById('closeFooterBtn').addEventListener('click', function() {
        chatPanel.classList.remove('open');
    });
    
    // Functions
    function sendMessage() {
        const content = chatInput.value.trim();
        if (!content) return;
        
        // Add user message
        const userMessage = {
            id: Date.now().toString(),
            type: 'user',
            content: content,
            timestamp: new Date()
        };
        
        messages.push(userMessage);
        addMessageToDOM(userMessage);
        chatInput.value = '';
        chatInput.style.height = 'auto';
        
        // Show loading indicator
        loadingIndicator.style.display = 'flex';
        
        // Scroll to bottom
        scrollToBottom();
        
        // Send to backend
        fetch('https://hp-frontend-three.vercel.app/api/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                query: content,
                sessionId: sessionId,
                conversationHistory: messages.slice(-6).map(m => ({
                    role: m.type === 'user' ? 'user' : 'assistant',
                    content: m.content
                }))
            })
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            // Hide loading indicator
            loadingIndicator.style.display = 'none';
            
            // Add bot message
            const botMessage = {
                id: data.id || generateUUID(),
                type: 'bot',
                content: data.answer || "I couldn't process that request. Please try rephrasing your question.",
                timestamp: new Date(),
                conversationId: data.conversationId,
                metadata: {
                    sql: data.sql,
                    tablesUsed: data.tables_used,
                    insights: data.insights,
                    canEscalate: data.canEscalate
                }
            };
            
            if (data.conversationId) {
                conversationId = data.conversationId;
            }
            
            messages.push(botMessage);
            addMessageToDOM(botMessage);
            scrollToBottom();
        })
        .catch(error => {
            console.error('Error:', error);
            loadingIndicator.style.display = 'none';
            
            // Add error message
            const errorMessage = {
                id: (Date.now() + 1).toString(),
                type: 'bot',
                content: 'Sorry, I encountered an error while processing your request. Please check your connection and try again.',
                timestamp: new Date(),
                metadata: {
                    canEscalate: true
                }
            };
            
            messages.push(errorMessage);
            addMessageToDOM(errorMessage);
            scrollToBottom();
        });
    }
    
    function addMessageToDOM(message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `d-flex gap-3 ${message.type === 'user' ? 'flex-row-reverse' : ''}`;
        
        // Avatar
        const avatarDiv = document.createElement('div');
        avatarDiv.className = `flex-shrink-0 w-8 h-8 rounded-circle d-flex align-items-center justify-content-center ${
            message.type === 'user' ? 'bg-gray-200' : 'bg-blue-100'
        }`;
        
        const avatarIcon = document.createElement('i');
        avatarIcon.className = message.type === 'user' 
            ? 'fas fa-user text-gray-600' 
            : 'fas fa-robot text-blue-600';
        avatarDiv.appendChild(avatarIcon);
        
        // Message content container
        const contentDiv = document.createElement('div');
        contentDiv.className = `d-flex flex-column gap-2 ${message.type === 'user' ? 'align-items-end' : ''}`;
        
        // Message bubble
        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = `${message.type === 'user' ? 'user-message' : 'bot-message'} rounded-3 px-3 py-2`;
        bubbleDiv.textContent = message.content;
        
        contentDiv.appendChild(bubbleDiv);
        
        // Metadata: Tables used
        if (message.metadata?.tablesUsed && message.metadata.tablesUsed.length > 0) {
            const tablesDiv = document.createElement('div');
            tablesDiv.className = 'd-flex gap-2 flex-wrap';
            
            message.metadata.tablesUsed.forEach(table => {
                const badge = document.createElement('span');
                badge.className = 'table-badge d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill';
                badge.innerHTML = `<i class="fas fa-database"></i> ${table}`;
                tablesDiv.appendChild(badge);
            });
            
            contentDiv.appendChild(tablesDiv);
        }
        
        // Metadata: SQL Query
        if (message.metadata?.sql) {
            const sqlDetails = document.createElement('details');
            sqlDetails.className = 'w-full cursor-pointer';
            
            const summary = document.createElement('summary');
            summary.className = 'text-gray-600 hover:text-blue-600 fw-medium d-flex align-items-center gap-2';
            summary.innerHTML = `
                <i class="fas fa-database"></i>
                View SQL Query
                <span class="text-gray-400">▾</span>
            `;
            
            const sqlPre = document.createElement('pre');
            sqlPre.className = 'sql-block mt-2 p-3';
            sqlPre.textContent = formatSQL(message.metadata.sql);
            
            sqlDetails.appendChild(summary);
            sqlDetails.appendChild(sqlPre);
            contentDiv.appendChild(sqlDetails);
        }
        
        // Feedback buttons (for bot messages)
        if (message.type === 'bot' && message.metadata?.canEscalate) {
            const feedbackDiv = document.createElement('div');
            feedbackDiv.className = 'd-flex gap-2 pt-1';
            
            // Thumbs up
            const thumbsUpBtn = document.createElement('button');
            thumbsUpBtn.className = 'btn btn-sm p-1 rounded-circle';
            thumbsUpBtn.innerHTML = '<i class="fas fa-thumbs-up"></i>';
            thumbsUpBtn.title = 'Helpful';
            thumbsUpBtn.onclick = () => submitFeedback(message.id, 1);
            
            // Thumbs down
            const thumbsDownBtn = document.createElement('button');
            thumbsDownBtn.className = 'btn btn-sm p-1 rounded-circle';
            thumbsDownBtn.innerHTML = '<i class="fas fa-thumbs-down"></i>';
            thumbsDownBtn.title = 'Not helpful';
            thumbsDownBtn.onclick = () => submitFeedback(message.id, -1);
            
            feedbackDiv.appendChild(thumbsUpBtn);
            feedbackDiv.appendChild(thumbsDownBtn);
            
            // Escalate button
            if (!conversationId && userId) {
                const escalateBtn = document.createElement('button');
                escalateBtn.className = 'btn btn-sm btn-outline-warning ms-auto';
                escalateBtn.textContent = 'Escalate';
                escalateBtn.onclick = () => showEscalationModal();
                feedbackDiv.appendChild(escalateBtn);
            }
            
            contentDiv.appendChild(feedbackDiv);
        }
        
        // Timestamp
        const timestampSpan = document.createElement('span');
        timestampSpan.className = 'text-xs text-gray-500 px-1';
        timestampSpan.textContent = formatTime(message.timestamp);
        
        contentDiv.appendChild(timestampSpan);
        
        // Assemble message
        messageDiv.appendChild(avatarDiv);
        messageDiv.appendChild(contentDiv);
        
        chatMessages.appendChild(messageDiv);
    }
    
    function formatSQL(sql) {
        return sql
            .replace(/\b(SELECT|FROM|WHERE|JOIN|LEFT|RIGHT|INNER|OUTER|GROUP BY|ORDER BY|HAVING|LIMIT)\b/gi, '\n$1')
            .replace(/,/g, ',\n  ')
            .trim();
    }
    
    function formatTime(date) {
        return date.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }
    
    function submitFeedback(messageId, rating) {
        // Implement feedback submission
        console.log('Feedback submitted:', { messageId, rating, conversationId });
    }
    
    function showEscalationModal() {
        // Implement escalation modal
        console.log('Show escalation modal');
    }
    
    // Global function to open chatbot from anywhere
    window.openChatbot = function() {
        chatPanel.classList.add('open');
        chatInput.focus();
    };
});
</script>