@extends('layout')
@section('container')
<link rel="stylesheet" href="/admin_dep/css/h5pCSS.css">
<style>
    /* DataTable Custom Styles */
    .dataTables_wrapper {
        padding: 20px;
        background: white;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        margin-top: 20px;
    }

    .dataTables_length select,
    .dataTables_filter input {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        padding: 8px 15px;
        margin: 0 5px;
    }

    .dataTables_length select:focus,
    .dataTables_filter input:focus {
        border-color: #005bea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(0, 91, 234, 0.1);
    }

    .dataTables_info {
        padding: 15px 0;
        color: #64748b;
    }

    .dataTables_paginate {
        padding: 15px 0;
    }

    .dataTables_paginate .paginate_button {
        border-radius: 12px;
        margin: 0 3px;
        padding: 8px 15px;
    }

    .dataTables_paginate .paginate_button.current {
        background: linear-gradient(145deg, #1e3c72, #2a5298) !important;
        border: none;
        color: white !important;
    }

    /* Table Styles */
    table.dataTable {
        border-collapse: separate;
        border-spacing: 0 10px;
        width: 100%;
    }

    table.dataTable thead th {
        background: #f8fafd;
        color: #1e3c72;
        font-weight: 600;
        padding: 15px;
        border-bottom: 2px solid #e2e8f0;
    }

    table.dataTable tbody tr {
        background: white;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        transition: all 0.3s ease;
    }

    table.dataTable tbody tr:hover {
        box-shadow: 0 8px 25px rgba(30, 60, 114, 0.1);
        transform: translateY(-2px);
    }

    table.dataTable tbody td {
        padding: 15px;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
        justify-content: center;
    }

    .btn-edit {
        background: linear-gradient(145deg, #10b981, #059669);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 10px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
    }

    .btn-edit:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        color: white;
    }

    .btn-delete {
        background: linear-gradient(145deg, #ef4444, #dc2626);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 10px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .btn-delete:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
    }

    .btn-view {
        background: linear-gradient(145deg, #3b82f6, #2563eb);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 10px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        text-decoration: none;
    }

    .btn-view:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
        color: white;
    }

    /* Search Header Inputs */
    .search-input {
        width: 100%;
        padding: 8px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 12px;
    }

    .search-input:focus {
        border-color: #005bea;
        outline: none;
        box-shadow: 0 0 0 2px rgba(0, 91, 234, 0.1);
    }

    /* Image Preview */
    .scenario-thumb {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        object-fit: cover;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    /* Alert Styles */
    .alert {
        border-radius: 15px;
        padding: 15px 20px;
        margin-bottom: 20px;
        border: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }

    .alert-success {
        background: linear-gradient(145deg, #d1fae5, #a7f3d0);
        color: #065f46;
    }

    .alert-danger {
        background: linear-gradient(145deg, #fee2e2, #fecaca);
        color: #991b1b;
    }

    .btn-dt {
        background: linear-gradient(145deg, #1e3c72, #2a5298);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 10px;
        margin: 0 2px;
    }

    .btn-dt:hover {
        transform: translateY(-2px);
        color: white;
    }

    /* Multiple Cards Form Styles */
    .cards-container {
        background: white;
        border-radius: 20px;
        padding: 30px;
        margin-top: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    }

    .card-item {
        background: #f8fafd;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
        position: relative;
    }

    .card-item:hover {
        border-color: #005bea;
        box-shadow: 0 5px 15px rgba(0, 91, 234, 0.1);
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e2e8f0;
    }

    .card-title {
        font-size: 18px;
        font-weight: 600;
        color: #1e3c72;
        margin: 0;
    }

    .card-number {
        background: linear-gradient(145deg, #1e3c72, #2a5298);
        color: white;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
    }

    .remove-card {
        background: linear-gradient(145deg, #ef4444, #dc2626);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .remove-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
    }

    .add-card-btn-card {
        background: linear-gradient(145deg, #10b981, #059669);
        color: white;
        border: none;
        padding: 8px 15px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 14px;
    }

    .add-card-btn-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
    }

    .add-card-btn-bottom {
        background: linear-gradient(145deg, #10b981, #059669);
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 16px;
        font-weight: 600;
        margin-top: 20px;
    }

    .add-card-btn-bottom:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
    }

    .submit-btn {
        background: linear-gradient(145deg, #005bea, #0046b5);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 16px;
        font-weight: 600;
    }

    .submit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 91, 234, 0.3);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        font-weight: 600;
        color: #1e3c72;
        margin-bottom: 8px;
        display: block;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        transition: all 0.3s ease;
        font-size: 14px;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        border-color: #005bea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(0, 91, 234, 0.1);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }

    .info-section {
        background: white;
        border-radius: 20px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    }

    .info-section select {
        width: 100%;
        padding: 12px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 14px;
    }

    /* CKEditor Styles */
    .cke {
        border-radius: 12px !important;
        border: 2px solid #e2e8f0 !important;
    }

    .cke_focus {
        border-color: #005bea !important;
        box-shadow: 0 0 0 3px rgba(0, 91, 234, 0.1) !important;
    }
    
    /* Ensure CKEditor fits well in cards */
    .cke_chrome {
        width: 100% !important;
        box-sizing: border-box !important;
    }
    
    .card-item .cke {
        margin-bottom: 10px;
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

        @if ($errors->any())
        <div class="alert alert-danger alert-block">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </strong>
        </div>
        @endif

        <!-- Animated Header -->
        <div class="modern-header">
            <div class="header-content">
                <div class="header-left">
                    <div class="header-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                   <div class="header-text">
                        <h1> {{ App\Helpers\getTableFieldFromId('chapter_master','chapter_name',$data['chapter_id']) }}</h1>
                        <div class="header-badges">
                            <span class="header-badge">
                                <i class="fas fa-book-open"></i>
                                {{ App\Helpers\getTableFieldFromId('sub_std_map','display_name',$data['subject_id'],'subject_id') }}
                            </span>
                            <span class="header-badge">
                                <i class="fas fa-graduation-cap"></i>
                                {{ App\Helpers\getTableFieldFromId('standard','name',$data['standard_id']) }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="header-right">
                   
                </div>
            </div>
        </div>

        <form action="{{ route('h5p_flashacard.store') }}" method="POST" id="flashcards-form">
            @csrf
            <input type="hidden" name="chapter_id" value="{{$data['chapter_id']}}">
            <input type="hidden" name="subject_id" value="{{$data['subject_id']}}">
            <input type="hidden" name="standard_id" value="{{$data['standard_id']}}">
            
            <!-- Cards Container -->
            <div class="cards-container">
                <div id="cards-wrapper">
                    <!-- Card 1 (Default) -->
                    <div class="card-item" data-card-index="0">
                        <div class="card-header">
                            <div class="d-flex align-items-center gap-3">
                                <!-- <span class="card-number">Card #1</span> -->
                            </div>
                            <div>
                                <button type="button" class="add-card-btn-card me-2" data-position="after-0">
                                    <i class="fas fa-plus-circle"></i>
                                </button>
                                <button type="button" class="remove-card" style="display: none;">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="cards_0_question">Question *</label>
                                    <textarea name="cards[0][question]" id="cards_0_question" class="form-control" rows="5" placeholder="Enter the question...">{{ old('cards.0.question') }}</textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="cards_0_content">Content / Explanation</label>
                                    <textarea name="cards[0][content]" id="cards_0_content" class="form-control ckeditor-textarea" rows="5" placeholder="Enter additional content or explanation...">{{ old('cards.0.content') }}</textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cards_0_correct_answer">Correct Answer *</label>
                                    <input type="text" name="cards[0][correct_answer]" id="cards_0_correct_answer" class="form-control" placeholder="Enter correct answer" required value="{{ old('cards.0.correct_answer') }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="cards_0_hint">Hint (Optional)</label>
                                    <input type="text" name="cards[0][hint]" id="cards_0_hint" class="form-control" placeholder="Enter hint for students" value="{{ old('cards.0.hint') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- <div class="text-center">
                    <button type="button" class="add-card-btn-bottom" id="add-card-bottom-btn">
                        <i class="fas fa-plus-circle"></i> Add Another Card
                    </button>
                </div> -->
                
                <div class="text-center mt-4">
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> Submit
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@include('includes.lmsfooterJs')
<script src="{{ asset("/ckeditor_wiris/ckeditor4/ckeditor.js") }}"></script>
<script>
$(document).ready(function() {
    let cardCount = $('.card-item').length;
    let editorInstances = {};
    
   // CKEditor configuration
    CKEDITOR.config.toolbar_Full = [
        { name: 'document', items: [ 'Source'] },
        { name: 'clipboard', items: [ 'Cut','Copy','Paste','-','Undo','Redo' ] },
        { name: 'editing', items: [ 'Find'] },
        { name: 'basicstyles', items: [ 'Bold','Italic','Underline'] },
        { name: 'paragraph', items: [ 'JustifyLeft','JustifyCenter','JustifyRight'] }
    ];
    CKEDITOR.config.height = '150px';
    
    // Register external plugins if needed
    CKEDITOR.plugins.addExternal('divarea', '../examples/extraplugins/divarea/', 'plugin.js');
    CKEDITOR.plugins.addExternal('sharedspace', '../examples/extraplugins/sharedspace/', 'plugin.js');
    CKEDITOR.plugins.addExternal('filebrowser', '../examples/extraplugins/filebrowser/', 'plugin.js');
    CKEDITOR.plugins.addExternal('enterkey', '../examples/extraplugins/enterkey/', 'plugin.js');
    CKEDITOR.plugins.addExternal('FMathEditor', '../examples/extraplugins/FMathEditor/', 'plugin.js');
    
    CKEDITOR.config.removePlugins = 'maximize,resize';
    
    // Initialize CKEditor only for content fields (not for questions)
    function initCKEditor(elementId) {
        try {
            // Check if element exists and not already initialized
            if (!elementId || editorInstances[elementId]) {
                return;
            }
            
            const element = document.getElementById(elementId);
            if (!element) {
                console.log('Element not found:', elementId);
                return;
            }
            
            editorInstances[elementId] = CKEDITOR.replace(elementId, {
                extraPlugins: 'filebrowser,divarea,sharedspace,FMathEditor,enterkey',
                enterMode: CKEDITOR.ENTER_BR,
                language: 'en',
                height: '200px',
                toolbar: [
                    { name: 'document', items: [ 'Source'] },
                    { name: 'clipboard', items: [ 'Cut','Copy','Paste','-','Undo','Redo' ] },
                    { name: 'editing', items: [ 'Find'] },
                    { name: 'basicstyles', items: [ 'Bold','Italic','Underline'] },
                    { name: 'paragraph', items: [ 'JustifyLeft','JustifyCenter','JustifyRight'] },
                    { name: 'links', items: [ 'Link', 'Unlink' ] },
                    { name: 'insert', items: [ 'Image', 'Table', 'HorizontalRule', 'SpecialChar' ] },
                    { name: 'tools', items: [ 'Maximize' ] }
                ],
                filebrowserUploadUrl: "{{ route('uploadimage', ['_token' => csrf_token()]) }}",
                filebrowserUploadMethod: 'form'
            });
            
            // Add blur event to update textarea
            editorInstances[elementId].on('blur', function() {
                if (editorInstances[elementId] && editorInstances[elementId].getData) {
                    $('#' + elementId).val(editorInstances[elementId].getData());
                }
            });
            
            // Add instance ready event
            editorInstances[elementId].on('instanceReady', function() {
                console.log('CKEditor initialized for content field:', elementId);
            });
            
        } catch(e) {
            console.log('Error initializing CKEditor for ' + elementId, e);
        }
    }
    
    // Function to initialize CKEditors only for content fields
    function initializeContentEditors() {
        $('.ckeditor-textarea').each(function() {
            const id = $(this).attr('id');
            if (id && !editorInstances[id]) {
                // Small delay to ensure DOM is ready
                setTimeout(function() {
                    initCKEditor(id);
                }, 100);
            }
        });
    }
    
    // Function to update textarea values from CKEditor before submit
    function updateTextareaValues() {
        for (let id in editorInstances) {
            if (editorInstances[id] && editorInstances[id].getData) {
                const textarea = $('#' + id);
                if (textarea.length) {
                    textarea.val(editorInstances[id].getData());
                }
            }
        }
    }
    
    // Function to destroy CKEditor instances
    function destroyEditor(editorId) {
        if (editorInstances[editorId]) {
            try {
                editorInstances[editorId].destroy();
                delete editorInstances[editorId];
            } catch(e) {
                console.log('Error destroying editor', e);
            }
        }
    }
    
    // Function to destroy all editors in a card
    function destroyCardEditors(cardElement) {
        $(cardElement).find('.ckeditor-textarea').each(function() {
            const id = $(this).attr('id');
            if (id && editorInstances[id]) {
                destroyEditor(id);
            }
        });
    }
    
    // Function to update card numbers and reinitialize editors for content fields only
    function updateCardNumbers() {
        let index = 0;
        const cards = $('.card-item');
        
        cards.each(function() {
            const $card = $(this);
            $card.attr('data-card-index', index);
            $card.find('.card-number').text('Card #' + (index + 1));
            
            // Update all input names and ids
            $card.find('textarea, input').each(function() {
                const $element = $(this);
                let name = $element.attr('name');
                let id = $element.attr('id');
                
                if (name) {
                    let newName = name.replace(/cards\[\d+\]/, 'cards[' + index + ']');
                    $element.attr('name', newName);
                }
                
                if (id) {
                    let newId = id.replace(/_\d+_/, '_' + index + '_');
                    let oldId = id;
                    $element.attr('id', newId);
                    
                    // Handle CKEditor reinitialization only for content fields
                    if ($element.hasClass('ckeditor-textarea')) {
                        // Destroy old editor instance
                        if (editorInstances[oldId]) {
                            destroyEditor(oldId);
                        }
                        // Initialize new editor with new ID
                        setTimeout(function() {
                            initCKEditor(newId);
                        }, 50);
                    }
                }
            });
            
            // Update add card after button position
            $card.find('.add-card-btn-card').attr('data-position', 'after-' + index);
            index++;
        });
        
        // Show/hide remove buttons based on number of cards
        if ($('.card-item').length > 1) {
            $('.remove-card').show();
        } else {
            $('.remove-card').hide();
        }
    }
    
    // Function to add new card after specific card
    function addCardAfter(positionIndex) {
        const newCardIndex = $('.card-item').length;
        const newCardHtml = `
            <div class="card-item" data-card-index="${newCardIndex}">
                <div class="card-header">
                    <div class="d-flex align-items-center gap-3">
                        <!-- <span class="card-number">Card #${newCardIndex + 1}</span> -->
                    </div>
                    <div>
                        <button type="button" class="add-card-btn-card me-2" data-position="after-${newCardIndex}">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                        <button type="button" class="remove-card">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="cards_${newCardIndex}_question">Question *</label>
                            <textarea name="cards[${newCardIndex}][question]" id="cards_${newCardIndex}_question" class="form-control" rows="5" placeholder="Enter the question..." required></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="cards_${newCardIndex}_content">Content / Explanation</label>
                            <textarea name="cards[${newCardIndex}][content]" id="cards_${newCardIndex}_content" class="form-control ckeditor-textarea" rows="5" placeholder="Enter additional content or explanation..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cards_${newCardIndex}_correct_answer">Correct Answer *</label>
                            <input type="text" name="cards[${newCardIndex}][correct_answer]" id="cards_${newCardIndex}_correct_answer" class="form-control" placeholder="Enter correct answer" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="cards_${newCardIndex}_hint">Hint (Optional)</label>
                            <input type="text" name="cards[${newCardIndex}][hint]" id="cards_${newCardIndex}_hint" class="form-control" placeholder="Enter hint for students">
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Insert after the specified card
        const targetCard = $(`.card-item[data-card-index="${positionIndex}"]`);
        if (targetCard.length) {
            targetCard.after(newCardHtml);
        } else {
            $('#cards-wrapper').append(newCardHtml);
        }
        
        // Update card numbers and reinitialize editors
        updateCardNumbers();
        
        // Scroll to the new card
        setTimeout(function() {
            const newCard = $(`.card-item[data-card-index="${positionIndex + 1}"]`);
            if (newCard.length) {
                $('html, body').animate({
                    scrollTop: newCard.offset().top - 100
                }, 500);
            }
        }, 200);
    }
    
    // Add new card at the bottom
    $('#add-card-bottom-btn').click(function() {
        const lastCardIndex = $('.card-item').length - 1;
        addCardAfter(lastCardIndex);
    });
    
    // Add new card after specific card
    $(document).on('click', '.add-card-btn-card', function() {
        const position = $(this).attr('data-position');
        if (position && position.startsWith('after-')) {
            const index = parseInt(position.split('-')[1]);
            addCardAfter(index);
        }
    });
    
    // Remove card
    $(document).on('click', '.remove-card', function() {
        if (confirm('Are you sure you want to remove this card?')) {
            const cardItem = $(this).closest('.card-item');
            
            // Destroy all CKEditor instances in this card (only content fields)
            destroyCardEditors(cardItem);
            
            // Remove the card
            cardItem.remove();
            
            // Update remaining cards
            updateCardNumbers();
        }
    });
    
    // Form validation before submit
    $('#flashcards-form').submit(function(e) {
        updateTextareaValues();
        
        let hasError = false;
        let errorMessages = [];
        
        $('.card-item').each(function(index) {
            const question = $(this).find('textarea[name*="[question]"]').val().trim();
            const correctAnswer = $(this).find('input[name*="[correct_answer]"]').val().trim();
            
            if (!question) {
                hasError = true;
                errorMessages.push(`Card #${index + 1}: Question is required`);
                $(this).find('textarea[name*="[question]"]').css('border-color', '#ef4444');
            } else {
                $(this).find('textarea[name*="[question]"]').css('border-color', '#e2e8f0');
            }
            
            if (!correctAnswer) {
                hasError = true;
                errorMessages.push(`Card #${index + 1}: Correct answer is required`);
                $(this).find('input[name*="[correct_answer]"]').css('border-color', '#ef4444');
            } else {
                $(this).find('input[name*="[correct_answer]"]').css('border-color', '#e2e8f0');
            }
        });
        
        if (hasError) {
            e.preventDefault();
            alert('Please fix the following errors:\n\n' + errorMessages.join('\n'));
            return false;
        }
        
        return true;
    });
    
    // Clear error styling on focus
    $(document).on('focus', 'textarea, input, select', function() {
        $(this).css('border-color', '#e2e8f0');
    });
    
    // Initialize CKEditors only for content fields when page loads
    $(document).ready(function() {
        // Wait for DOM to be fully ready
        setTimeout(function() {
            initializeContentEditors();
        }, 200);
    });
    
    // Reinitialize editors if needed (for dynamic content)
    $(document).ajaxComplete(function() {
        setTimeout(function() {
            initializeContentEditors();
        }, 100);
    });
});
</script>

@include('includes.footer')
@endsection