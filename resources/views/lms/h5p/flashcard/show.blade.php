@extends('layout')
@section('container')
<link rel="stylesheet" href="/admin_dep/css/h5pCSS.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    /* Flashcard Container Styles */
    .flashcard-container {
        max-width: 900px;
        margin: 30px auto;
        position: relative;
    }
    
    /* Carousel Navigation Icons */
    .carousel-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 100%;
        display: flex;
        justify-content: space-between;
        pointer-events: none;
        z-index: 20;
    }
    
    .carousel-nav-btn {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        pointer-events: auto;
    }
    
    .carousel-nav-btn:hover:not(:disabled) {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
    }
    
    .carousel-nav-btn:active {
        transform: scale(0.95);
    }
    
    .carousel-nav-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    
    .carousel-nav-prev {
        margin-left: -25px;
    }
    
    .carousel-nav-next {
        margin-right: -25px;
    }
    
    .flashcard {
        background: white;
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        margin: 0 20px;
    }
    
    .flashcard:hover {
        transform: translateY(-5px);
        box-shadow: 0 30px 50px rgba(0, 0, 0, 0.15);
    }
    
    /* Card Body Styles - White Background */
    .card-body-content {
        padding: 40px;
        min-height: 400px;
        background: white;
        position: relative;
    }
    
    .card-content {
        font-size: 16px;
        line-height: 1.8;
        color: #333;
    }
    
    .card-content img {
        max-width: 100%;
        height: auto;
        border-radius: 10px;
        margin: 15px 0;
    }
    
    /* Hint Icon in Corner */
    .hint-icon {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 36px;
        height: 36px;
        background: #ffc107;
        color: #856404;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 15;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .hint-icon:hover {
        transform: scale(1.1);
        background: #ffb300;
    }
    
    /* Hint Popup */
    .hint-popup {
        position: absolute;
        top: 60px;
        right: 20px;
        max-width: 300px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        padding: 15px;
        z-index: 20;
        display: none;
        animation: fadeIn 0.3s ease;
        border-left: 4px solid #ffc107;
    }
    
    .hint-popup.show {
        display: block;
    }
    
    .hint-popup:before {
        content: '';
        position: absolute;
        top: -8px;
        right: 12px;
        width: 0;
        height: 0;
        border-left: 8px solid transparent;
        border-right: 8px solid transparent;
        border-bottom: 8px solid white;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Feedback Overlay */
    .feedback-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.9);
        border-radius: 20px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        z-index: 10;
    }
    
    .feedback-overlay.show {
        opacity: 1;
        visibility: visible;
    }
    
    .feedback-content {
        text-align: center;
        color: white;
        padding: 30px;
    }
    
    .feedback-icon {
        font-size: 80px;
        margin-bottom: 20px;
    }
    
    .feedback-icon.correct {
        color: #4caf50;
    }
    
    .feedback-icon.wrong {
        color: #f44336;
    }
    
    .feedback-message {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 10px;
    }
    
    /* Card Footer Styles */
    .card-footer {
        padding: 30px;
        background: #f8f9fa;
        border-top: 1px solid #e9ecef;
        display:block;
    }
    
    .question-text {
        font-size: 18px;
        font-weight: 600;
        color: #1a237e;
        margin-bottom: 20px;
        padding: 12px;
        background: white;
        border-radius: 10px;
        border-left: 4px solid #667eea;
    }
    
    .answer-input {
        width: 100%;
        padding: 14px 18px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 16px;
        transition: all 0.3s ease;
        margin-bottom: 15px;
    }
    
    .answer-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .check-btn {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .check-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    
    .check-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    /* Progress Bar */
    .progress-container {
        margin-bottom: 30px;
        padding: 0 20px;
    }
    
    .progress {
        height: 10px;
        background: #e0e0e0;
        border-radius: 10px;
        overflow: hidden;
    }
    
    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        transition: width 0.3s ease;
    }
    
    .progress-text {
        text-align: center;
        margin-top: 12px;
        font-size: 14px;
        color: #666;
        font-weight: 500;
    }
    
    /* Card Indicators */
    .card-indicators {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 30px;
        padding: 0 20px;
    }
    
    .indicator-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #d1d5db;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .indicator-dot.active {
        width: 30px;
        border-radius: 10px;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    }
    
    .indicator-dot.completed {
        background: #4caf50;
    }
    
    /* Result Modal */
    .result-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    
    .result-modal.show {
        opacity: 1;
        visibility: visible;
    }
    
    .result-content {
        background: white;
        padding: 40px;
        border-radius: 20px;
        text-align: center;
        max-width: 500px;
        width: 90%;
    }
    
    .result-score {
        font-size: 48px;
        font-weight: bold;
        color: #667eea;
        margin: 20px 0;
    }
    
    .restart-btn {
        margin-top: 20px;
        padding: 12px 30px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .carousel-nav-btn {
            width: 40px;
            height: 40px;
            font-size: 18px;
        }
        
        .carousel-nav-prev {
            margin-left: -15px;
        }
        
        .carousel-nav-next {
            margin-right: -15px;
        }
        
        .card-body-content {
            padding: 20px;
            min-height: 300px;
        }
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid mb-5">
        <div class="modern-header">
            <div class="header-content">
                <div class="header-left">
                    <div class="header-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="header-text">
                        <h1>Flashcards - {{ App\Helpers\getTableFieldFromId('chapter_master','chapter_name',$data['chapter_id']) }}</h1>
                        <div class="header-badges">
                            <span class="header-badge">
                                <i class="fas fa-book-open"></i>
                                {{ App\Helpers\getTableFieldFromId('sub_std_map','display_name',$data['subject_id'],'subject_id') }}
                            </span>
                            <span class="header-badge">
                                <i class="fas fa-graduation-cap"></i>
                                {{ App\Helpers\getTableFieldFromId('standard','name',$data['standard_id']) }}
                            </span>
                            <span class="header-badge">
                                <i class="fas fa-cards"></i>
                                Total Cards: {{ count($data['flashCards']) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flashcard-container">
            <!-- Progress Bar -->
           

            <!-- Carousel Navigation -->
            <div class="carousel-nav">
                <button class="carousel-nav-btn carousel-nav-prev" id="prevBtn" disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button class="carousel-nav-btn carousel-nav-next" id="nextBtn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <!-- Flashcard -->
            <div class="flashcard" id="flashcard">
                <div class="card-body-content" id="cardBody">
                    <div class="card-content" id="cardContent"></div>
                    <div class="feedback-overlay" id="feedbackOverlay">
                        <div class="feedback-content">
                            <div class="feedback-icon" id="feedbackIcon"></div>
                            <div class="feedback-message" id="feedbackMessage"></div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer">
                    <div class="question-text" id="questionText"></div>
                    <div class="row">
                        <div class="col-md-10">
                        <input type="text" class="answer-input" id="answerInput" placeholder="Type your answer here..." autocomplete="off">
                        </div>
                        <div class="col-md-2">
                        <button class="check-btn" id="checkBtn">
                            <i class="fas fa-check-circle"></i>
                        </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Indicators -->
            <div class="card-indicators" id="cardIndicators"></div>
        </div>
    </div>
</div>

<!-- Result Modal -->
<div class="result-modal" id="resultModal">
    <div class="result-content">
        <i class="fas fa-trophy" style="font-size: 60px; color: #ffc107;"></i>
        <h2>Quiz Completed!</h2>
        <div class="result-score" id="resultScore">0/0</div>
        <p id="resultMessage">Great job! Keep learning!</p>
        <button class="restart-btn" id="restartBtn">
            <i class="fas fa-redo-alt"></i> Start Over
        </button>
    </div>
</div>

@include('includes.lmsfooterJs')
<script>
    let flashcards = @json($data['flashCards']);
    let currentIndex = 0;
    let userAnswers = [];
    let answerChecked = false;
    let timeoutId = null;

    function createIndicators() {
        const indicatorsContainer = $('#cardIndicators');
        indicatorsContainer.empty();
        
        flashcards.forEach((card, index) => {
            const dot = $('<div>').addClass('indicator-dot').attr('data-index', index);
            if (index === currentIndex) dot.addClass('active');
            if (userAnswers[index] && userAnswers[index].isCorrect) dot.addClass('completed');
            
            dot.click(function() {
                if (index !== currentIndex && confirm('Navigate to card ' + (index + 1) + '?')) {
                    goToCard(index);
                }
            });
            indicatorsContainer.append(dot);
        });
    }
    
    function updateIndicators() {
        $('.indicator-dot').each(function(index) {
            $(this).removeClass('active');
            if (index === currentIndex) $(this).addClass('active');
            if (userAnswers[index] && userAnswers[index].isCorrect) $(this).addClass('completed');
            else $(this).removeClass('completed');
        });
    }
    
    function goToCard(index) {
        if (timeoutId) clearTimeout(timeoutId);
        currentIndex = index;
        answerChecked = userAnswers[currentIndex] ? true : false;
        $('#feedbackOverlay').removeClass('show');
        $('.hint-popup').remove();
        loadCard(currentIndex);
        updateIndicators();
        $('#checkBtn').prop('disabled', answerChecked);
    }
    
    function showHintPopup(hintText) {
        $('.hint-popup').remove();
        const popup = $('<div>').addClass('hint-popup').html('<p><i class="fas fa-lightbulb"></i> ' + hintText + '</p>');
        $('#cardBody').append(popup);
        setTimeout(() => popup.addClass('show'), 10);
        setTimeout(() => {
            popup.removeClass('show');
            setTimeout(() => popup.remove(), 300);
        }, 5000);
    }
    
    function loadCard(index) {
        const card = flashcards[index];
        $('.hint-icon').remove();
        $('.hint-popup').remove();
        $('#cardContent').html(card.content || 'No content available');
        
        if (card.hint && card.hint.trim() !== '') {
            const hintIcon = $('<div>').addClass('hint-icon').html('<i class="fas fa-question"></i>');
            hintIcon.click(e => { e.stopPropagation(); showHintPopup(card.hint); });
            $('#cardBody').append(hintIcon);
        }
        
        $('#questionText').html('<i class="fas fa-question-circle"></i> ' + card.question);
        $('#answerInput').val('').css('border-color', '#e0e0e0');
        
        if (userAnswers[index]) {
            $('#checkBtn').prop('disabled', true);
            $('#answerInput').prop('disabled', true).attr('placeholder', 'Already answered');
        } else {
            $('#checkBtn').prop('disabled', false);
            $('#answerInput').prop('disabled', false).attr('placeholder', 'Type your answer here...');
            setTimeout(() => $('#answerInput').focus(), 100);
        }
        
        updateProgress();
        updateNavButtons();
    }
    
    function updateProgress() {
        const progress = ((currentIndex + 1) / flashcards.length) * 100;
        $('#progressBar').css('width', progress + '%');
        $('#currentCardNum').text(currentIndex + 1);
    }
    
    function updateNavButtons() {
        $('#prevBtn').prop('disabled', currentIndex === 0);
        $('#nextBtn').prop('disabled', currentIndex === flashcards.length - 1);
    }
    
    function checkAnswer() {
        if (answerChecked) {
            showNotification('You already answered this card!', 'warning');
            return;
        }
        
        const userAnswer = $('#answerInput').val().trim().toLowerCase();
        const currentCard = flashcards[currentIndex];
        const correctAnswer = currentCard.correct_answer.toLowerCase();
        
        if (userAnswer === '') {
            showNotification('Please enter an answer!', 'error');
            return;
        }
        
        userAnswers[currentIndex] = {
            userAnswer: userAnswer,
            isCorrect: userAnswer === correctAnswer,
            correctAnswer: currentCard.correct_answer
        };
        
        showFeedback(userAnswer === correctAnswer, currentCard.correct_answer);
        answerChecked = true;
        $('#checkBtn').prop('disabled', true);
        $('#answerInput').prop('disabled', true);
        updateIndicators();
        
        if (currentIndex < flashcards.length - 1) {
            timeoutId = setTimeout(() => goToNextCard(), 2000);
        } else {
            timeoutId = setTimeout(() => showResultModal(), 2000);
        }
    }
    
    function showFeedback(isCorrect, correctAnswer) {
        const iconDiv = $('#feedbackIcon');
        const messageDiv = $('#feedbackMessage');
        
        if (isCorrect) {
            iconDiv.html('<i class="fas fa-check-circle"></i>').addClass('correct').removeClass('wrong');
            messageDiv.html('Correct! 🎉');
            $('.flashcard').css('box-shadow', '0 0 0 3px #4caf50, 0 20px 40px rgba(0,0,0,0.1)');
            setTimeout(() => $('.flashcard').css('box-shadow', '0 20px 40px rgba(0, 0, 0, 0.1)'), 500);
        } else {
            iconDiv.html('<i class="fas fa-times-circle"></i>').addClass('wrong').removeClass('correct');
            messageDiv.html('Incorrect! 😔');
            $('.flashcard').css('box-shadow', '0 0 0 3px #f44336, 0 20px 40px rgba(0,0,0,0.1)');
            setTimeout(() => $('.flashcard').css('box-shadow', '0 20px 40px rgba(0, 0, 0, 0.1)'), 500);
        }
        
        $('#feedbackOverlay').addClass('show');
        setTimeout(() => $('#feedbackOverlay').removeClass('show'), 2000);
    }
    
    function goToNextCard() {
        if (currentIndex < flashcards.length - 1) {
            currentIndex++;
            answerChecked = userAnswers[currentIndex] ? true : false;
            $('#feedbackOverlay').removeClass('show');
            $('.hint-popup').remove();
            loadCard(currentIndex);
            updateIndicators();
        }
    }
    
    function goToPrevCard() {
        if (currentIndex > 0) {
            currentIndex--;
            answerChecked = userAnswers[currentIndex] ? true : false;
            $('#feedbackOverlay').removeClass('show');
            $('.hint-popup').remove();
            loadCard(currentIndex);
            updateIndicators();
        }
    }
    
    function showResultModal() {
        const total = flashcards.length;
        const correctCount = userAnswers.filter(a => a && a.isCorrect).length;
        const score = ((correctCount / total) * 100).toFixed(0);
        
        $('#resultScore').text(`${correctCount}/${total}`);
        let message = score >= 90 ? 'Excellent! You\'re a star! 🌟' : (score >= 70 ? 'Good job! Keep practicing! 👍' : (score >= 50 ? 'Not bad! Review and try again! 📚' : 'Keep learning! You\'ll do better next time! 💪'));
        $('#resultMessage').text(message);
        $('#resultModal').addClass('show');
    }
    
    function restartQuiz() {
        currentIndex = 0;
        userAnswers = [];
        answerChecked = false;
        $('#resultModal').removeClass('show');
        $('#answerInput').prop('disabled', false);
        $('#checkBtn').prop('disabled', false);
        loadCard(0);
        updateIndicators();
        if (timeoutId) clearTimeout(timeoutId);
        showNotification('Quiz restarted! Good luck!', 'success');
    }
    
    function showNotification(message, type = 'success') {
        const notification = $('<div>').addClass('alert alert-' + (type === 'success' ? 'success' : 'danger')).css({
            position: 'fixed', top: '20px', right: '20px', zIndex: 9999, animation: 'slideInRight 0.5s ease'
        }).html('<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + message).appendTo('body');
        setTimeout(() => notification.fadeOut(300, () => notification.remove()), 3000);
    }
    
    $(document).ready(function() {
        createIndicators();
        if (flashcards.length > 0) loadCard(0);
        else $('#cardContent').html('<div class="alert alert-warning">No flashcards available.</div>');
        
        $('#checkBtn').click(() => { if (!answerChecked) checkAnswer(); });
        $('#answerInput').keypress(e => { if (e.which === 13 && !answerChecked && !$('#checkBtn').prop('disabled')) checkAnswer(); });
        $('#nextBtn').click(() => { if (currentIndex < flashcards.length - 1 && (!answerChecked && !userAnswers[currentIndex] ? confirm('Skip this card?') : true)) goToNextCard(); });
        $('#prevBtn').click(() => goToPrevCard());
        $('#restartBtn').click(() => restartQuiz());
        $('#resultModal').click(e => { if (e.target === this) $(this).removeClass('show'); });
    });
</script>

@include('includes.footer')
@endsection