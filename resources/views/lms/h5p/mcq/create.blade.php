<div id="quiz-container" class="container mt-4">
    <style>
        .quiz-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }
        .question-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        
        /* New Modern Answer Options Design */
        .answers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .answer-option {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #dee2e6;
            border-radius: 12px;
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .answer-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }
        .answer-option:hover::before {
            left: 100%;
        }
        .answer-option:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.2);
        }
        .answer-option.selected {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102,126,234,0.3);
        }
        .answer-option.selected .option-icon {
            background: white;
            color: #667eea;
        }
        .answer-option input {
            display: none;
        }
        .answer-option label {
            cursor: pointer;
            margin: 0;
            width: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1rem;
            font-weight: 500;
        }
        .option-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .answer-option.selected .option-icon {
            background: white;
            color: #667eea;
            transform: scale(1.1);
        }
        
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 15px;
        }
        .btn-nav {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn-prev {
            background: #6c757d;
            color: white;
        }
        .btn-prev:hover:not(:disabled) {
            background: #5a6268;
            transform: translateX(-3px);
        }
        .btn-next {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-next:hover:not(:disabled) {
            transform: translateX(3px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        .btn-end {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        .btn-end:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(220,53,69,0.4);
        }
        .btn-nav:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Enhanced Progress Tracking */
        .stats-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 15px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            flex: 1;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon-count {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .stat-card i {
            font-size: 1.8rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            line-height: 1;
        }
        .stat-label {
            font-size: 0.85rem;
            opacity: 0.9;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-weight: 500;
        }
        .progress-container {
            margin-bottom: 25px;
        }
        .progress-bar-custom {
            height: 12px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 1.5s infinite;
        }
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        .question-counter {
            text-align: center;
            color: #666;
            margin-bottom: 15px;
            font-weight: 500;
            font-size: 1.1rem;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 20px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 20px 20px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
        }
        .close-modal {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .close-modal:hover {
            transform: scale(1.1);
        }
        .modal-body {
            padding: 20px;
        }
        .answer-review-item {
            background: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
        }
        .answer-review-item.correct {
            border-left-color: #28a745;
            background: #f0fff4;
        }
        .answer-review-item.wrong {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .answer-review-question {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .answer-review-details {
            font-size: 0.9rem;
            margin-top: 8px;
        }
        .correct-answer {
            color: #28a745;
        }
        .user-answer {
            color: #dc3545;
        }
        
        .result-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        /* Redesigned Certificate - Compact Style */
        .certificate {
            background: linear-gradient(135deg, #fff5e6 0%, #fff0e0 100%);
            border: 3px solid #ffd700;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
            text-align: center;
            position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .certificate-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ffd700;
        }
        .school-logo {
            height: 60px;
            width: auto;
            object-fit: contain;
        }
        .school-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
            margin: 0;
        }
        .certificate-title {
            font-size: 1.8rem;
            font-weight: bold;
            color: #ffd700;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
            margin: 10px 0;
            letter-spacing: 1px;
        }
        .certificate-date {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 15px;
        }
        .certificate-student-name {
            font-size: 1.6rem;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
            text-transform: uppercase;
        }
        .certificate-message {
            font-size: 0.95rem;
            line-height: 1.5;
            color: #555;
            margin: 15px 0;
            padding: 0 15px;
            text-align: center;
        }
        .certificate-details {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 10px;
            margin: 15px auto;
            display: inline-block;
            min-width: 280px;
        }
        .details-grid {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }
        .detail-item {
            text-align: center;
        }
        .detail-label {
            font-size: 0.75rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .detail-value {
            font-size: 1.2rem;
            font-weight: bold;
            margin-top: 5px;
        }
        .score-highlight {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px auto;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .restart-btn, .download-cert-btn, .review-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 10px;
            margin: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            font-weight: 600;
        }
        .restart-btn:hover, .download-cert-btn:hover, .review-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        .download-cert-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .review-btn {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
        }
        .button-group {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .certificate-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed #ffd700;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .question-block {
            animation: fadeIn 0.5s ease;
        }
        
        @media (max-width: 768px) {
            .stat-value {
                font-size: 1.5rem;
            }
            .stat-card i {
                font-size: 1.4rem;
            }
            .stat-icon-count {
                gap: 8px;
            }
            .certificate-title {
                font-size: 1.3rem;
            }
            .certificate-student-name {
                font-size: 1.2rem;
            }
            .school-name {
                font-size: 1rem;
            }
            .school-logo {
                height: 40px;
            }
            .details-grid {
                gap: 10px;
            }
            .detail-value {
                font-size: 1rem;
            }
            .certificate-details {
                min-width: auto;
                width: 100%;
            }
            .certificate-message {
                font-size: 0.85rem;
                padding: 0 5px;
            }
        }
    </style>
   
    <div class="quiz-card">
        <!-- Enhanced Statistics Display - Icon and Count in Row, Label Below -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon-count">
                    <i class="fas fa-question-circle"></i>
                    <span class="stat-value" id="total-questions-stat">0</span>
                </div>
                <div class="stat-label">Total Questions</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-count">
                    <i class="fas fa-check-circle"></i>
                    <span class="stat-value" id="attempted-count">0</span>
                </div>
                <div class="stat-label">Attempted</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon-count">
                    <i class="fas fa-hourglass-half"></i>
                    <span class="stat-value" id="remaining-count">0</span>
                </div>
                <div class="stat-label">Remaining</div>
            </div>
        </div>
        
        <div class="progress-container">
            <div class="question-counter">
                <i class="fas fa-book-open"></i> Question <span id="current-question">1</span> of <span id="total-q-count">0</span>
            </div>
            <div class="progress-bar-custom">
                <div class="progress-fill" id="progress-fill"></div>
            </div>
        </div>

        <form id="quiz-form">
            @foreach($data['question_arr'] as $key => $question)
            <div class="question-block" id="question-{{ $key }}" style="{{ $key == 0 ? 'display:block;' : 'display:none;' }}">
                <div class="question-text">
                    <i class="fas fa-question-circle"></i> 
                    {!! html_entity_decode($question['question_text'], ENT_QUOTES, 'UTF-8') !!}
                </div>
                
                <div class="answers-grid">
                @if(isset($data['answer_arr'][$question['question_id']]))
                    @foreach($data['answer_arr'][$question['question_id']] as $ansKey => $answer)
                        <div class="answer-option" data-question-id="{{ $question['question_id'] }}" data-answer-id="{{ $answer['id'] }}" onclick="selectAnswer(this, {{ $question['question_id'] }}, {{ $answer['id'] }})">
                            <input type="radio" 
                                   name="question_{{ $question['question_id'] }}" 
                                   value="{{ $answer['id'] }}" 
                                   id="answer_{{ $question['question_id'] }}_{{ $ansKey }}"
                                   style="display: none;">
                            <label for="answer_{{ $question['question_id'] }}_{{ $ansKey }}">
                                <span class="option-icon">{{ chr(65 + $ansKey) }}</span>
                                <span class="answer-text">{!! html_entity_decode($answer['answer'], ENT_QUOTES, 'UTF-8') !!}</span>
                            </label>
                        </div>
                    @endforeach
                @endif
                </div>
                
                <div class="nav-buttons">
                    <button type="button" class="btn-nav btn-prev" onclick="navigateQuestion({{ $key - 1 }})" {{ $key == 0 ? 'disabled' : '' }}>
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    @if($key == count($data['question_arr']) - 1)
                        <button type="button" class="btn-nav btn-end" onclick="submitQuiz()">
                            <i class="fas fa-flag-checkered"></i> End Quiz
                        </button>
                    @else
                        <button type="button" class="btn-nav btn-next" onclick="navigateQuestion({{ $key + 1 }})">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                    @endif
                </div>
            </div>
            @endforeach
        </form>
    </div>

    <div id="result-container" style="display:none;">
        <div class="result-card">
            <div class="score-circle" id="score-circle">
                <span id="percentage-score">0</span>%
            </div>
            <h2><i class="fas fa-trophy"></i> Quiz Results</h2>
            <p style="font-size: 1.2rem; margin: 20px 0;">
                Total Points: <strong id="total-points" style="color: #667eea; font-size: 1.5rem;">0</strong> / <strong id="total-questions-result">0</strong>
            </p>
            <div class="button-group">
                <button class="review-btn" onclick="openReviewModal()">
                    <i class="fas fa-clipboard-list"></i> Review Answers
                </button>
            </div>
            <div id="certificate" class="certificate">
                <!-- Certificate Header with School Logo and Name -->
                <div class="certificate-header">
                    @if(session()->get('school_logo') && session()->get('school_logo') != 'null')
                        <img src="/admin_dep/images/{{session()->get('school_logo')}}" class="school-logo" alt="School Logo">
                    @endif
                    <div class="school-name">{{ session()->get('school_name') ?? 'School Name' }}</div>
                </div>
                
                <div class="certificate-title">CERTIFICATE OF ACHIEVEMENT</div>
                <div class="certificate-date" id="certificate-date-display"></div>
                
                <div class="certificate-student-name" id="certificate-student-name">
                    {{ session()->get('name') ?? 'Student' }}
                </div>
                
                <div class="certificate-message" id="certificate-message">
                    <!-- Dynamic message based on score will be inserted here -->
                </div>
                
                <!-- Compact Details Section -->
                <div class="certificate-details" style="display:none;">
                    <div class="details-grid">
                        <div class="detail-item">
                            <div class="detail-label">Standard</div>
                            <div class="detail-value" id="certificate-standard">{{ App\Helpers\getTableFieldFromId('standard','name',$data['standard_id']) }}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Chapter</div>
                            <div class="detail-value" id="certificate-chapter">{{ App\Helpers\getTableFieldFromId('chapter_master','chapter_name',$data['chapter_id']) }}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Score</div>
                            <div class="detail-value"><span class="score-highlight" id="certificate-points-final">0</span> / <span id="certificate-total-final">0</span></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Percentage</div>
                            <div class="detail-value"><span id="certificate-percentage-final">0</span>%</div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Buttons moved outside certificate -->
            <div class="certificate-actions">
                <div class="button-group">
                    <button class="download-cert-btn" onclick="downloadCertificate()">
                        <i class="fas fa-download"></i> Download Certificate
                    </button>
                    <button class="restart-btn" onclick="restartQuiz()">
                        <i class="fas fa-redo-alt"></i> Take Quiz Again
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-clipboard-list"></i> Answer Review</h3>
            <span class="close-modal" onclick="closeReviewModal()">&times;</span>
        </div>
        <div class="modal-body" id="reviewModalBody">
            <!-- Review content will be populated here -->
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
    let userAnswers = {};
    let correctAnswers = {};
    let allQuestionsData = [];
    let totalQuestions = {{ count($data['question_arr']) }};
    let currentQuestionIndex = 0;
    
    // Load questions data and correct answers
    @foreach($data['question_arr'] as $key => $question)
        allQuestionsData[{{ $key }}] = {
            id: {{ $question['question_id'] }},
            text: '{!! addslashes(html_entity_decode($question['question_text'], ENT_QUOTES, 'UTF-8')) !!}',
            answers: @json($data['answer_arr'][$question['question_id']] ?? [])
        };
        @if(isset($data['answer_arr'][$question['question_id']]))
            @foreach($data['answer_arr'][$question['question_id']] as $answer)
                @if($answer['correct_answer'] == 1)
                    correctAnswers[{{ $question['question_id'] }}] = {
                        id: {{ $answer['id'] }},
                        text: '{!! addslashes(html_entity_decode($answer['answer'], ENT_QUOTES, 'UTF-8')) !!}'
                    };
                @endif
            @endforeach
        @endif
    @endforeach
    
    // Initialize total questions display
    document.getElementById('total-questions-stat').innerText = totalQuestions;
    document.getElementById('total-q-count').innerText = totalQuestions;
    document.getElementById('total-questions-result').innerText = totalQuestions;
    document.getElementById('certificate-total-final').innerText = totalQuestions;
    
    // Update stats function
    function updateStats() {
        const attemptedCount = Object.keys(userAnswers).length;
        const remainingCount = totalQuestions - attemptedCount;
        document.getElementById('attempted-count').innerText = attemptedCount;
        document.getElementById('remaining-count').innerText = remainingCount;
        
        // Update progress fill
        const progressPercent = (attemptedCount / totalQuestions) * 100;
        document.getElementById('progress-fill').style.width = progressPercent + '%';
    }
    
    // Function to select answer
    function selectAnswer(element, questionId, answerId) {
        // Remove selected class from all options for this question
        const questionBlock = element.closest('.question-block');
        const allOptions = questionBlock.querySelectorAll('.answer-option');
        allOptions.forEach(opt => opt.classList.remove('selected'));
        
        // Add selected class to clicked option
        element.classList.add('selected');
        
        // Select the radio button
        const radio = element.querySelector('input[type="radio"]');
        if (radio) {
            radio.checked = true;
        }
        
        // Store user answer
        userAnswers[questionId] = answerId;
        
        // Update stats
        updateStats();
    }
    
    // Navigation function
    function navigateQuestion(index) {
        if (index < 0 || index >= totalQuestions) return;
        
        currentQuestionIndex = index;
        
        // Hide all questions
        document.querySelectorAll('.question-block').forEach(block => {
            block.style.display = 'none';
        });
        
        // Show selected question
        const selectedQuestion = document.getElementById('question-' + index);
        if (selectedQuestion) {
            selectedQuestion.style.display = 'block';
        }
        
        // Update question counter
        document.getElementById('current-question').innerText = index + 1;
        
        // Update button states
        updateButtonStates(index);
        
        // Restore selected answer if exists
        const questionDiv = document.getElementById('question-' + index);
        const questionId = getQuestionIdFromBlock(questionDiv);
        
        if (questionId && userAnswers[questionId]) {
            const selectedAnswerId = userAnswers[questionId];
            const selectedOption = questionDiv.querySelector(`.answer-option[data-answer-id="${selectedAnswerId}"]`);
            if (selectedOption) {
                const allOptions = questionDiv.querySelectorAll('.answer-option');
                allOptions.forEach(opt => opt.classList.remove('selected'));
                selectedOption.classList.add('selected');
                const radio = selectedOption.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            }
        }
    }
    
    function getQuestionIdFromBlock(block) {
        const answerOption = block.querySelector('.answer-option');
        return answerOption ? answerOption.getAttribute('data-question-id') : null;
    }
    
    function updateButtonStates(index) {
        const prevBtn = document.querySelector(`#question-${index} .btn-prev`);
        if (prevBtn) {
            prevBtn.disabled = (index === 0);
        }
    }
    
    // Function to generate certificate message based on score
    function getCertificateMessage(percentage, studentName, standard, chapter) {
        if (percentage >= 90) {
            return `This is to certify that <strong>${studentName}</strong> has demonstrated EXCEPTIONAL knowledge and understanding in <strong>${standard}</strong> (Chapter: ${chapter}). With an outstanding score of ${percentage}%, the student has shown mastery of the subject matter and excellent comprehension skills.`;
        } else if (percentage >= 75) {
            return `This is to certify that <strong>${studentName}</strong> has successfully completed the Knowledge Quiz Challenge for <strong>${standard}</strong> (Chapter: ${chapter}) with DISTINCTION. Achieving ${percentage}%, the student has shown strong understanding and commendable performance.`;
        } else if (percentage >= 60) {
            return `This is to certify that <strong>${studentName}</strong> has successfully completed the Knowledge Quiz Challenge for <strong>${standard}</strong> (Chapter: ${chapter}) with GOOD STANDING. Scoring ${percentage}%, the student has demonstrated satisfactory knowledge and understanding.`;
        } else if (percentage >= 40) {
            return `This is to certify that <strong>${studentName}</strong> has participated in and completed the Knowledge Quiz Challenge for <strong>${standard}</strong> (Chapter: ${chapter}). Achieving ${percentage}%, the student has shown basic understanding of the concepts.`;
        } else {
            return `This is to certify that <strong>${studentName}</strong> has participated in the Knowledge Quiz Challenge for <strong>${standard}</strong> (Chapter: ${chapter}). With ${percentage}%, we encourage the student to review the material and try again to improve their understanding.`;
        }
    }
    
    // Open review modal with detailed answers
    function openReviewModal() {
        const modalBody = document.getElementById('reviewModalBody');
        let reviewHtml = '<div style="max-height: 60vh; overflow-y: auto;">';
        
        for (let i = 0; i < totalQuestions; i++) {
            const question = allQuestionsData[i];
            const userAnswerId = userAnswers[question.id];
            const correctAnswer = correctAnswers[question.id];
            const isCorrect = userAnswerId && correctAnswer && userAnswerId == correctAnswer.id;
            
            let userAnswerText = 'Not Answered';
            if (userAnswerId) {
                const userAnswerObj = question.answers.find(a => a.id == userAnswerId);
                userAnswerText = userAnswerObj ? userAnswerObj.answer : 'Unknown';
            }
            
            reviewHtml += `
                <div class="answer-review-item ${isCorrect ? 'correct' : 'wrong'}">
                    <div class="answer-review-question">
                        <strong>Question ${i + 1}:</strong> ${question.text}
                    </div>
                    <div class="answer-review-details">
                        <div><i class="fas fa-check-circle" style="color: #28a745;"></i> <strong>Correct Answer:</strong> <span class="correct-answer">${correctAnswer ? correctAnswer.text : 'N/A'}</span></div>
                        <div><i class="fas fa-user-check"></i> <strong>Your Answer:</strong> <span class="${!isCorrect ? 'user-answer' : ''}">${userAnswerText}</span></div>
                        <div><strong>Status:</strong> ${isCorrect ? '<span style="color: #28a745;">✓ Correct</span>' : '<span style="color: #dc3545;">✗ Incorrect</span>'}</div>
                    </div>
                </div>
            `;
        }
        
        reviewHtml += '</div>';
        modalBody.innerHTML = reviewHtml;
        document.getElementById('reviewModal').style.display = 'block';
    }
    
    function closeReviewModal() {
        document.getElementById('reviewModal').style.display = 'none';
    }
    
    // Submit quiz and show results
    function submitQuiz() {
        let correct = 0;
        for (let qId in correctAnswers) {
            if (userAnswers[qId] && userAnswers[qId] == correctAnswers[qId].id) {
                correct++;
            }
        }
        
        let points = correct;
        let progress = totalQuestions > 0 ? Math.round((correct / totalQuestions) * 100) : 0;
        
        document.getElementById('quiz-form').style.display = 'none';
        document.querySelector('.quiz-card').style.display = 'none';
        document.getElementById('result-container').style.display = 'block';
        
        document.getElementById('percentage-score').innerText = progress;
        document.getElementById('total-points').innerText = points;
        
        // Update certificate with enhanced content
        const studentName = "{{ session()->get('name') ?? 'Student' }}";
        const standard = "{{ App\Helpers\getTableFieldFromId('standard','name',$data['standard_id']) }}";
        const chapter = "{{ App\Helpers\getTableFieldFromId('chapter_master','chapter_name',$data['chapter_id']) }}";
        
        document.getElementById('certificate-points-final').innerText = points;
        document.getElementById('certificate-percentage-final').innerText = progress;
        document.getElementById('certificate-date-display').innerText = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        
        // Set standard and chapter values
        document.getElementById('certificate-standard').innerText = standard;
        document.getElementById('certificate-chapter').innerText = chapter;
        
        // Generate and set the dynamic message
        const message = getCertificateMessage(progress, studentName, standard, chapter);
        document.getElementById('certificate-message').innerHTML = message;
        
        const scoreCircle = document.getElementById('score-circle');
        if (progress >= 80) {
            scoreCircle.style.background = "linear-gradient(135deg, #28a745 0%, #20c997 100%)";
        } else if (progress >= 60) {
            scoreCircle.style.background = "linear-gradient(135deg, #ffc107 0%, #fd7e14 100%)";
        } else if (progress >= 40) {
            scoreCircle.style.background = "linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%)";
        } else {
            scoreCircle.style.background = "linear-gradient(135deg, #dc3545 0%, #c82333 100%)";
        }
        
        document.getElementById('result-container').scrollIntoView({ behavior: 'smooth' });
    }
    
    // Download certificate as image
    function downloadCertificate() {
        const certificate = document.getElementById('certificate');
        html2canvas(certificate, {
            scale: 2,
            backgroundColor: '#fff5e6',
            logging: false
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = 'certificate.png';
            link.href = canvas.toDataURL();
            link.click();
        });
    }
    
    // Restart quiz function
    function restartQuiz() {
        userAnswers = {};
        
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.checked = false;
        });
        
        document.querySelectorAll('.answer-option').forEach(option => {
            option.classList.remove('selected');
        });
        
        updateStats();
        
        document.getElementById('progress-fill').style.width = '0%';
        document.getElementById('quiz-form').style.display = 'block';
        document.querySelector('.quiz-card').style.display = 'block';
        document.getElementById('result-container').style.display = 'none';
        
        navigateQuestion(0);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateStats();
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('reviewModal');
            if (event.target == modal) {
                closeReviewModal();
            }
        }
        
        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (document.getElementById('result-container').style.display === 'none' && 
                document.getElementById('reviewModal').style.display !== 'block') {
                if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    const prevBtn = document.querySelector(`#question-${currentQuestionIndex} .btn-prev`);
                    if (prevBtn && !prevBtn.disabled) {
                        navigateQuestion(currentQuestionIndex - 1);
                    }
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    const nextBtn = document.querySelector(`#question-${currentQuestionIndex} .btn-next`);
                    const endBtn = document.querySelector(`#question-${currentQuestionIndex} .btn-end`);
                    if (nextBtn && !nextBtn.disabled) {
                        navigateQuestion(currentQuestionIndex + 1);
                    } else if (endBtn) {
                        submitQuiz();
                    }
                }
            }
        });
    });
</script>