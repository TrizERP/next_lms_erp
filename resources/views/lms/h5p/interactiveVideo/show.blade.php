@extends('layout')
@section('container')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link rel="stylesheet" href="/admin_dep/css/h5pCSS.css">
<style>
    .video-container {
        max-width: 1200px;
        margin: 0 auto;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .video-wrapper {
        position: relative;
        background: #000;
        min-height: 400px;
    }
    
    .video-wrapper video,
    .video-wrapper iframe {
        width: 100%;
        height: auto;
        display: block;
    }
    
    /* Video Markers */
    .video-marker {
        position: absolute;
        bottom: 80px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        cursor: pointer;
        transform: translateX(-50%);
        z-index: 100;
        transition: all 0.2s;
        box-shadow: 0 0 0 2px rgba(255,255,255,0.9);
        border: 1px solid rgba(0,0,0,0.2);
    }
    
    .video-marker:hover {
        transform: translateX(-50%) scale(1.3);
        box-shadow: 0 0 0 3px rgba(255,255,255,0.9);
    }
    
    .marker-multiple-choice { background: #ff4444; }
    .marker-true-false { background: #ffaa44; }
    .marker-text-input { background: #44aaff; }
    
    /* Popup */
    .interaction-popup {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        width: 500px;
        max-width: 90vw;
        z-index: 1001;
        display: none;
    }
    
    .interaction-popup.active {
        display: block;
        animation: slideIn 0.2s ease;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translate(-50%, -50%) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }
    }
    
    .popup-header {
        padding: 15px 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Teal header variant for info/description cards */
    .popup-header.info-header {
        background: linear-gradient(135deg, #43cea2 0%, #185a9d 100%);
    }
    
    .popup-header h4 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
    }
    
    .popup-close {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s;
    }
    
    .popup-close:hover {
        background: rgba(255,255,255,0.2);
    }
    
    .popup-body {
        padding: 25px;
    }
    
    .question-text {
        font-size: 16px;
        color: #333;
        margin-bottom: 20px;
        line-height: 1.5;
        font-weight: 500;
    }
    
    .options-list {
        margin-bottom: 20px;
    }
    
    .option-item {
        margin-bottom: 12px;
    }
    
    .option-label {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .option-label:hover {
        border-color: #667eea;
        background: #f8f9ff;
    }
    
    .option-label.selected {
        border-color: #667eea;
        background: linear-gradient(135deg, rgba(102,126,234,0.1) 0%, rgba(118,75,162,0.1) 100%);
    }

    .option-label.correct-answer {
        border-color: #28a745 !important;
        background: rgba(40, 167, 69, 0.1) !important;
    }

    .option-label.wrong-answer {
        border-color: #dc3545 !important;
        background: rgba(220, 53, 69, 0.1) !important;
    }
    
    .option-radio {
        margin-right: 12px;
        width: 18px;
        height: 18px;
        cursor: pointer;
        accent-color: #667eea;
    }
    
    .option-text {
        flex: 1;
        font-size: 14px;
        color: #555;
    }

    /* Info / Description card styles (text_input type) */
    .info-card-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #43cea2 0%, #185a9d 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
    }

    .info-card-icon i {
        font-size: 22px;
        color: #fff;
    }

    .info-card-label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        color: #43cea2;
        margin-bottom: 10px;
    }

    .info-card-text {
        font-size: 15px;
        color: #333;
        line-height: 1.7;
        margin: 0;
    }
    
    .popup-footer {
        padding: 15px 25px;
        border-top: 1px solid #e0e0e0;
        display: flex;
        gap: 12px;
        justify-content: flex-end;
    }

    /* Teal footer border for info cards */
    .popup-footer.info-footer {
        border-top-color: #e8f5f0;
    }
    
    .btn {
        padding: 8px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102,126,234,0.3);
    }
    
    .btn-secondary {
        background: #f0f0f0;
        color: #666;
    }
    
    .btn-secondary:hover {
        background: #e0e0e0;
    }

    /* Teal "Got it" button for info cards */
    .btn-info-close {
        background: linear-gradient(135deg, #43cea2 0%, #185a9d 100%);
        color: white;
        padding: 9px 24px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-info-close:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(67, 206, 162, 0.35);
    }
    
    .feedback-message {
        margin-top: 15px;
        padding: 12px 15px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        display: none;
        animation: fadeIn 0.2s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .feedback-message.correct {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
        display: block !important;
    }
    
    .feedback-message.incorrect {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
        display: block !important;
    }
    
    .overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        display: none;
    }
    
    .overlay.active {
        display: block;
    }
    
    /* Timeline */
    .timeline-container {
        padding: 15px 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #e0e0e0;
        position: relative;
    }
    
    .timeline {
        position: relative;
        height: 6px;
        background: #ddd;
        border-radius: 3px;
        cursor: pointer;
    }
    
    .timeline-progress {
        height: 100%;
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        border-radius: 3px;
        width: 0%;
        transition: width 0.1s linear;
        position: relative;
        z-index: 1;
    }
    
    .timeline-markers {
        position: absolute;
        top: -8px;
        left: 0;
        width: 100%;
        height: 22px;
        pointer-events: none;
        z-index: 2;
    }
    
    .timeline-marker {
        position: absolute;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        transform: translateX(-50%);
        cursor: pointer;
        pointer-events: auto;
        border: 2px solid white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        transition: all 0.2s;
        z-index: 3;
    }
    
    .timeline-marker:hover {
        transform: translateX(-50%) scale(1.2);
        box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    }
    
    /* Controls */
    .control-bar {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px 20px;
        background: white;
    }
    
    .control-btn {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        color: #667eea;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s;
    }
    
    .control-btn:hover {
        background: #f0f0f0;
        transform: scale(1.05);
    }
    
    .time-display {
        font-size: 13px;
        color: #666;
        font-family: monospace;
        font-weight: 500;
    }
    
    .volume-slider {
        width: 80px;
        cursor: pointer;
    }
    
    /* Interactions List */
    .interactions-list {
        padding: 15px 20px;
        background: #fafafa;
        border-top: 1px solid #e0e0e0;
        max-height: 300px;
        overflow-y: auto;
    }
    
    .interactions-list h4 {
        margin: 0 0 12px 0;
        font-size: 14px;
        color: #666;
        font-weight: 600;
    }
    
    .interaction-item {
        padding: 10px 12px;
        margin-bottom: 8px;
        background: white;
        border-radius: 8px;
        border-left: 3px solid #667eea;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .interaction-item:hover {
        transform: translateX(5px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-left-color: #764ba2;
    }
    
    .interaction-time {
        font-size: 11px;
        color: #667eea;
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .interaction-question {
        font-size: 13px;
        color: #555;
        margin-top: 4px;
        line-height: 1.4;
    }
    
    .interaction-type {
        font-size: 10px;
        color: #999;
        margin-top: 5px;
    }
    
    input[type="range"] {
        cursor: pointer;
    }
    
    .auto-popup-indicator {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(0,0,0,0.7);
        color: white;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        z-index: 100;
        display: none;
    }
    
    .auto-popup-indicator.active {
        display: block;
        animation: pulse 1s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 0.6; }
        50% { opacity: 1; }
        100% { opacity: 0.6; }
    }

    @keyframes fadeOut {
        0% { opacity: 1; }
        70% { opacity: 1; }
        100% { opacity: 0; visibility: hidden; }
    }
</style>

<div id="page-wrapper">
    <div class="container-fluid mb-5">
        @if($sessionData = Session::get('data'))
        <div class="alert alert-{{ $sessionData['status'] ? 'success' : 'danger' }} alert-block">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <strong>{{ $sessionData['message'] }}</strong>
        </div>
        @endif

        <!-- Animated Header -->
        <div class="modern-header">
            <div class="header-content">
                <div class="header-left">
                    <div class="header-icon">
                        <i class="fas fa-video"></i>
                    </div>
                    <div class="header-text">
                        <h1>{{ $data['videos']->title }}</h1>
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
                                <i class="fas fa-chapter"></i>
                                {{ App\Helpers\getTableFieldFromId('chapter_master','chapter_name',$data['chapter_id']) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <div class="video-container">
                <!-- Video Player -->
                <div class="video-wrapper" id="videoWrapper">
                    <video id="videoPlayer" controls style="width: 100%;">
                        <source src="{{ $data['videos']->video_path }}" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                    <div id="videoMarkersContainer" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;"></div>
                    <div id="autoPopupIndicator" class="auto-popup-indicator">
                        <i class="fas fa-bell"></i> Question coming up...
                    </div>
                </div>
                
                <!-- Timeline -->
                <div class="timeline-container">
                    <div class="timeline" id="timeline">
                        <div class="timeline-progress" id="timelineProgress"></div>
                        <div class="timeline-markers" id="timelineMarkers"></div>
                    </div>
                </div>
                
                <!-- Controls -->
                <div class="control-bar">
                    <button class="control-btn" id="playPauseBtn">
                        <i class="fas fa-play"></i>
                    </button>
                    <span class="time-display">
                        <span id="currentTime">0:00</span> / <span id="duration">0:00</span>
                    </span>
                    <button class="control-btn" id="volumeBtn">
                        <i class="fas fa-volume-up"></i>
                    </button>
                    <input type="range" id="volumeSlider" min="0" max="1" step="0.1" value="1" class="volume-slider">
                </div>
                
                <!-- Interactions List -->
                <div class="interactions-list">
                    <h4><i class="fas fa-question-circle"></i> Video Interactions ({{ count($data['videos']->interactions) }})</h4>
                    <div id="interactionsList"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Overlay -->
<div id="overlay" class="overlay"></div>

<!-- Popup — structure stays fixed; JS fills it per interaction type -->
<div id="interactionPopup" class="interaction-popup">
    <div class="popup-header" id="popupHeader">
        <h4 id="popupHeaderTitle"><i class="fas fa-question-circle"></i> Interactive Question</h4>
        <button class="popup-close" id="closePopupBtn">&times;</button>
    </div>
    <div class="popup-body" id="popupBody">
        <div class="question-text" id="popupQuestion"></div>
        <div id="popupOptions"></div>
        <div id="popupFeedback" class="feedback-message"></div>
    </div>
    <div class="popup-footer" id="popupFooter">
        {{-- footer content injected by JS --}}
    </div>
</div>

@include('includes.lmsfooterJs')
<script>
    // ─── Video Element ────────────────────────────────────────────────────────
    const video = document.getElementById('videoPlayer');

    // ─── DOM References ───────────────────────────────────────────────────────
    const playPauseBtn          = document.getElementById('playPauseBtn');
    const timeline              = document.getElementById('timeline');
    const timelineProgress      = document.getElementById('timelineProgress');
    const currentTimeSpan       = document.getElementById('currentTime');
    const durationSpan          = document.getElementById('duration');
    const volumeSlider          = document.getElementById('volumeSlider');
    const volumeBtn             = document.getElementById('volumeBtn');
    const timelineMarkers       = document.getElementById('timelineMarkers');
    const videoMarkersContainer = document.getElementById('videoMarkersContainer');
    const autoPopupIndicator    = document.getElementById('autoPopupIndicator');
    const popup                 = document.getElementById('interactionPopup');
    const overlay               = document.getElementById('overlay');
    const popupHeader           = document.getElementById('popupHeader');
    const popupHeaderTitle      = document.getElementById('popupHeaderTitle');
    const popupQuestion         = document.getElementById('popupQuestion');
    const popupOptions          = document.getElementById('popupOptions');
    const popupFeedback         = document.getElementById('popupFeedback');
    const popupFooter           = document.getElementById('popupFooter');
    const closePopupBtn         = document.getElementById('closePopupBtn');

    // ─── Interactions Data ────────────────────────────────────────────────────
    const interactions = @json($data['videos']->interactions);

    // ─── State ────────────────────────────────────────────────────────────────
    let currentInteraction    = null;
    let selectedAnswer        = null;
    let isPopupOpen           = false;
    let autoTriggerEnabled    = true;

    // ─── Helpers ──────────────────────────────────────────────────────────────
    function formatTime(seconds) {
        if (isNaN(seconds) || seconds === Infinity) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    }

    function getMarkerColor(type) {
        return { multiple_choice: '#ff4444', true_false: '#ffaa44', text_input: '#44aaff' }[type] || '#ff4444';
    }

    function getTypeName(type) {
        return { multiple_choice: 'Multiple Choice', true_false: 'True/False', text_input: 'Info' }[type] || type;
    }

    function getTypeIcon(type) {
        return {
            multiple_choice: '<i class="fas fa-list-ul"></i> Multiple Choice',
            true_false:      '<i class="fas fa-check-circle"></i> True/False',
            text_input:      '<i class="fas fa-info-circle"></i> Info'
        }[type] || type;
    }

    function parseOptions(raw) {
        if (!raw) return {};
        try { return typeof raw === 'string' ? JSON.parse(raw) : raw; }
        catch (e) { return {}; }
    }

    // ─── Answer Checking ──────────────────────────────────────────────────────
    function isAnswerCorrect(interaction, answer) {
        if (!answer) return false;
        return String(answer) === String(interaction.correct_answer);
    }

    function getCorrectAnswerMessage(interaction) {
        const options    = parseOptions(interaction.options);
        const correctKey = String(interaction.correct_answer);
        return `Correct answer: ${options[correctKey] || correctKey}`;
    }

    // ─── Reset popup to neutral state ────────────────────────────────────────
    function resetPopup() {
        // Header: back to purple question style
        popupHeader.classList.remove('info-header');
        popupHeaderTitle.innerHTML = '<i class="fas fa-question-circle"></i> Interactive Question';

        // Body
        popupQuestion.textContent  = '';
        popupQuestion.style.display = '';
        popupOptions.innerHTML     = '';

        // Feedback: hidden
        popupFeedback.className    = 'feedback-message';
        popupFeedback.textContent  = '';

        // Footer: cleared
        popupFooter.innerHTML      = '';
        popupFooter.className      = 'popup-footer';
    }

    // ─── Build Question Footer (multiple_choice / true_false) ─────────────────
    function buildQuestionFooter() {
        popupFooter.innerHTML = `
            <button class="btn btn-secondary" id="cancelBtn">Cancel</button>
            <button class="btn btn-primary"   id="submitBtn">Submit Answer</button>
        `;
        document.getElementById('cancelBtn').addEventListener('click', () => closePopup(true));
        document.getElementById('submitBtn').addEventListener('click', submitAnswer);
    }

    // ─── Build Info Footer (text_input) ───────────────────────────────────────
    function buildInfoFooter() {
        popupFooter.className = 'popup-footer info-footer';
        popupFooter.innerHTML = `
            <button class="btn-info-close" id="infoCloseBtn">
                <i class="fas fa-play" style="margin-right:6px;font-size:12px;"></i> Got it, Continue
            </button>
        `;
        document.getElementById('infoCloseBtn').addEventListener('click', () => closePopup(true));
    }

    // ─── Build radio option list (shared for MC and TF) ───────────────────────
    function buildRadioOptions(options) {
        const list = document.createElement('div');
        list.className = 'options-list';

        Object.entries(options).forEach(([key, text]) => {
            const item  = document.createElement('div');
            item.className = 'option-item';

            const label = document.createElement('label');
            label.className    = 'option-label';
            label.dataset.key  = key;

            const input = document.createElement('input');
            input.type      = 'radio';
            input.name      = 'popupAnswer';
            input.value     = key;
            input.className = 'option-radio';
            input.addEventListener('change', () => {
                selectedAnswer = key;
                list.querySelectorAll('.option-label').forEach(l => l.classList.remove('selected'));
                label.classList.add('selected');
            });

            const span = document.createElement('span');
            span.className   = 'option-text';
            span.textContent = text;

            label.appendChild(input);
            label.appendChild(span);
            item.appendChild(label);
            list.appendChild(item);
        });

        popupOptions.appendChild(list);
    }

    // ─── Open Popup ───────────────────────────────────────────────────────────
    function openPopup(interaction) {
        if (isPopupOpen) return;

        resetPopup();
        currentInteraction = interaction;
        selectedAnswer     = null;
        isPopupOpen        = true;

        video.pause();
        updatePlayPauseButton();

        const options  = parseOptions(interaction.options);
        const isInfo   = interaction.interaction_type === 'text_input';

        if (isInfo) {
            // ── Description / Info card mode ──────────────────────────────
            popupHeader.classList.add('info-header');
            popupHeaderTitle.innerHTML = '<i class="fas fa-info-circle"></i> Did You Know?';

            // Hide the plain question-text div; render a styled card instead
            popupQuestion.style.display = 'none';
            popupOptions.innerHTML = `
                <div style="display:flex;flex-direction:column;align-items:flex-start;">
                    <div class="info-card-icon">
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    <div class="info-card-label">Note</div>
                    <p class="info-card-text">${interaction.question}</p>
                </div>
            `;

            buildInfoFooter();

            // Mark as seen so it doesn't re-trigger
            interaction.answered = true;

        } else {
            // ── Question mode (multiple_choice / true_false) ───────────────
            popupQuestion.textContent = interaction.question;

            const opts = (interaction.interaction_type === 'true_false' && Object.keys(options).length === 0)
                ? { '1': 'True', '2': 'False' }
                : options;

            buildRadioOptions(opts);
            buildQuestionFooter();
        }

        overlay.classList.add('active');
        popup.classList.add('active');
    }

    // ─── Close Popup ──────────────────────────────────────────────────────────
    function closePopup(resumeVideo = false) {
        popup.classList.remove('active');
        overlay.classList.remove('active');
        isPopupOpen        = false;
        currentInteraction = null;
        selectedAnswer     = null;
        autoTriggerEnabled = true;

        if (resumeVideo) {
            video.play();
            updatePlayPauseButton();
        }
    }

    // ─── Submit Answer ────────────────────────────────────────────────────────
    function submitAnswer() {
        if (!currentInteraction) return;

        if (!selectedAnswer) {
            popupFeedback.className   = 'feedback-message incorrect';
            popupFeedback.textContent = '⚠ Please select an answer before submitting.';
            return;
        }

        const correct = isAnswerCorrect(currentInteraction, selectedAnswer);

        // Highlight options
        popupOptions.querySelectorAll('.option-label').forEach(label => {
            if (label.dataset.key === String(currentInteraction.correct_answer)) {
                label.classList.add('correct-answer');
            } else if (label.dataset.key === String(selectedAnswer) && !correct) {
                label.classList.add('wrong-answer');
            }
        });

        // Disable further changes
        popupOptions.querySelectorAll('input').forEach(i => i.disabled = true);
        document.getElementById('submitBtn') && (document.getElementById('submitBtn').disabled = true);

        if (correct) {
            popupFeedback.className   = 'feedback-message correct';
            popupFeedback.textContent = '✓ Correct! Great job!';
            currentInteraction.answered = true;
            setTimeout(() => closePopup(true), 1800);
        } else {
            popupFeedback.className   = 'feedback-message incorrect';
            popupFeedback.textContent = '✗ Incorrect. ' + getCorrectAnswerMessage(currentInteraction);
        }
    }

    // ─── Play / Pause ─────────────────────────────────────────────────────────
    function updatePlayPauseButton() {
        playPauseBtn.querySelector('i').className = video.paused ? 'fas fa-play' : 'fas fa-pause';
    }

    // ─── Video Events ─────────────────────────────────────────────────────────
    video.addEventListener('loadedmetadata', () => {
        durationSpan.textContent = formatTime(video.duration);
        buildTimelineMarkers();
    });

    video.addEventListener('timeupdate', () => {
        currentTimeSpan.textContent      = formatTime(video.currentTime);
        timelineProgress.style.width     = `${(video.currentTime / video.duration) * 100}%`;

        // Auto-trigger interactions
        if (!isPopupOpen && autoTriggerEnabled) {
            interactions.forEach(interaction => {
                if (interaction.answered) return;
                if (Math.abs(video.currentTime - interaction.time) < 0.5 && !interaction.triggered) {
                    interaction.triggered = true;
                    autoTriggerEnabled    = false;
                    setTimeout(() => openPopup(interaction), 300);
                    setTimeout(() => { interaction.triggered = false; }, 3000);
                }
            });
        }
    });

    video.addEventListener('play',  updatePlayPauseButton);
    video.addEventListener('pause', updatePlayPauseButton);
    video.addEventListener('ended', updatePlayPauseButton);

    // ─── Controls ─────────────────────────────────────────────────────────────
    playPauseBtn.addEventListener('click', () => {
        video.paused ? video.play() : video.pause();
    });

    timeline.addEventListener('click', (e) => {
        const rect = timeline.getBoundingClientRect();
        const pos  = (e.clientX - rect.left) / rect.width;
        if (video.duration) video.currentTime = Math.max(0, Math.min(pos * video.duration, video.duration));
    });

    volumeSlider.addEventListener('input', (e) => {
        video.volume = e.target.value;
        const icon = volumeBtn.querySelector('i');
        icon.className = video.volume == 0 ? 'fas fa-volume-mute'
                       : video.volume < 0.5 ? 'fas fa-volume-down'
                       : 'fas fa-volume-up';
    });

    volumeBtn.addEventListener('click', () => {
        const muted = video.volume > 0;
        video.volume       = muted ? 0 : 1;
        volumeSlider.value = muted ? 0 : 1;
        volumeBtn.querySelector('i').className = muted ? 'fas fa-volume-mute' : 'fas fa-volume-up';
    });

    // Close on overlay click (no resume — user chose to dismiss)
    overlay.addEventListener('click', () => closePopup(false));
    closePopupBtn.addEventListener('click', () => closePopup(false));

    // ─── Interactions List ────────────────────────────────────────────────────
    const interactionsList = document.getElementById('interactionsList');
    interactions.forEach(interaction => {
        const item = document.createElement('div');
        item.className = 'interaction-item';
        item.innerHTML = `
            <div class="interaction-time">
                <i class="fas fa-clock"></i> ${formatTime(interaction.time)}
            </div>
            <div class="interaction-question">
                ${interaction.question.length > 100 ? interaction.question.substring(0, 100) + '...' : interaction.question}
            </div>
            <div class="interaction-type">${getTypeIcon(interaction.interaction_type)}</div>
        `;
        item.addEventListener('click', () => {
            video.currentTime = interaction.time;
            setTimeout(() => openPopup(interaction), 100);
        });
        interactionsList.appendChild(item);
    });

    // ─── Timeline Markers ────────────────────────────────────────────────────
    function buildTimelineMarkers() {
        timelineMarkers.innerHTML = '';
        interactions.forEach(interaction => {
            const pct = (interaction.time / video.duration) * 100;
            if (pct > 100) return;

            const marker = document.createElement('div');
            marker.className    = 'timeline-marker';
            marker.style.left   = `${pct}%`;
            marker.style.background = getMarkerColor(interaction.interaction_type);
            marker.title        = `${getTypeName(interaction.interaction_type)} at ${formatTime(interaction.time)}`;
            marker.addEventListener('click', (e) => {
                e.stopPropagation();
                video.currentTime = interaction.time;
                setTimeout(() => openPopup(interaction), 100);
            });
            timelineMarkers.appendChild(marker);
        });
    }

    // Fallback if loadedmetadata already fired
    if (video.readyState >= 1 && video.duration) buildTimelineMarkers();
</script>
@endsection