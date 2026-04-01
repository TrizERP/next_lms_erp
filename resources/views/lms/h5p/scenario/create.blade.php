@extends('layout')
@section('container')
<link rel="stylesheet" href="/admin_dep/css/h5pCSS.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
    /* Point Marker Styles */
    .point {
        position: absolute;
        width: 28px;
        height: 28px;
        background: #dc3545;
        border-radius: 50%;
        transform: translate(-50%, -50%);
        cursor: pointer;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        border: 2px solid white;
        transition: all 0.2s ease;
        z-index: 10;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        font-weight: bold;
    }
    
    .point:hover {
        transform: translate(-50%, -50%) scale(1.2);
        background: #c82333;
    }
    
    .point::after {
        content: attr(data-title);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        white-space: nowrap;
        margin-bottom: 5px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
        pointer-events: none;
    }
    
    .point:hover::after {
        opacity: 1;
        visibility: visible;
        bottom: 130%;
    }
    
    /* Image Container */
    #imageContainer {
        margin-top: 30px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 12px;
        border: 1px solid #e9ecef;
    }
    
    #imageContainer div {
        position: relative;
        display: inline-block;
        max-width: 100%;
    }
    
    #scenarioImage {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
        cursor: crosshair;
    }
    
    /* Points List Preview */
    .points-preview {
        margin-top: 20px;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 12px;
        border: 1px solid #e9ecef;
    }
    
    .points-preview h5 {
        color: #495057;
        margin-bottom: 15px;
        font-weight: 600;
        font-size: 16px;
    }
    
    .points-list {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .point-badge {
        background: #e9ecef;
        color: #495057;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .point-badge:hover {
        background: #dee2e6;
        transform: translateY(-1px);
    }
    
    .point-badge .badge-number {
        background: #dc3545;
        color: white;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: bold;
    }
    
    /* Form Styles */
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #495057;
        font-weight: 500;
        font-size: 14px;
    }
    
    /* CKEditor Custom Styles */
    .cke_chrome {
        border-radius: 8px !important;
        border: 1px solid #e9ecef !important;
    }
    
    .cke_inner {
        border-radius: 8px !important;
    }
    
    .cke_top {
        border-radius: 8px 8px 0 0 !important;
    }
    
    .cke_bottom {
        border-radius: 0 0 8px 8px !important;
    }
    
    /* AI Button Styles */
    .ai-btn {
        background: #6c757d;
        border: none;
        color: white;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        margin-top: 32px;
        width: 100%;
        justify-content: center;
    }
    
    .ai-btn:hover {
        background: #5a6268;
        color: white;
    }
    
    .ai-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    /* Loading Spinner */
    .ai-loading {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 0.6s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* Alert Notifications */
    .ai-success-alert, .ai-error-alert {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 8px;
        color: white;
        z-index: 9999;
        animation: slideInRight 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
    }
    
    .ai-success-alert {
        background: #28a745;
    }
    
    .ai-error-alert {
        background: #dc3545;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        justify-content: center;
    }
    
    .btn-icon {
        padding: 8px 20px;
        border-radius: 6px;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    @media (max-width: 768px) {
        .points-list {
            justify-content: center;
        }
        .action-buttons {
            flex-direction: column;
        }
        .btn-icon {
            width: 100%;
            justify-content: center;
        }
        .ai-btn {
            margin-top: 0;
        }
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
        
        <div class="modern-header">
            <div class="header-content">
                <div class="header-left">
                    <div class="header-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="header-text">
                        <h1>{{ App\Helpers\getTableFieldFromId('chapter_master','chapter_name',$data['chapter_id']) }}</h1>
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
            </div>
        </div>
        
        <div class="container">
            <form method="POST" action="{{route('scenario_based.store')}}" enctype="multipart/form-data" class="card p-4" style="border-radius: 12px; border: 1px solid #e9ecef;">
                @csrf
                <input type="hidden" name="standard_id" value="{{$data['standard_id']}}">
                <input type="hidden" name="chapter_id" value="{{$data['chapter_id']}}">
                <input type="hidden" name="subject_id" value="{{$data['subject_id']}}">
                <input type="hidden" id="standardName" value="{{ App\Helpers\getTableFieldFromId('standard','name',$data['standard_id']) }}">
                <input type="hidden" id="subjectName" value="{{ App\Helpers\getTableFieldFromId('sub_std_map','display_name',$data['subject_id'],'subject_id') }}">
                <input type="hidden" id="chapterName" value="{{ App\Helpers\getTableFieldFromId('chapter_master','chapter_name',$data['chapter_id']) }}">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Title</label>
                        <input type="text" name="title" id="scenarioTitle" class="form-control" style="border-radius: 8px; border: 1px solid #e9ecef; padding: 10px;" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Upload Image</label>
                        <input type="file" name="image" id="imageUpload" class="form-control" style="border-radius: 8px; border: 1px solid #e9ecef; padding: 10px;" accept="image/*" required>
                        <small class="text-muted">Click on image to add interactive points</small>
                    </div>
                    <div class="col-md-2 mb-3">
                        <button type="button" id="generateAIContent" class="btn ai-btn">
                            <i class="mdi mdi-creation"></i>
                        </button>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label>Description</label>
                        <textarea name="description" id="descriptionEditor" class="form-control" style="height: 200px;"></textarea>
                    </div>
                </div>

                <div id="imageContainer"></div>
                
                <div id="pointsPreview" class="points-preview" style="display: none;">
                    <h5><i class="fas fa-map-marker-alt me-2"></i>Added Points</h5>
                    <div class="points-list" id="pointsList"></div>
                </div>

                <input type="hidden" name="points" id="pointsData">
                
                <div class="action-buttons">
                    <button type="submit" class="btn btn-success btn-icon">
                        <i class="fas fa-save"></i> Submit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap Modal for Point Editor -->
<div class="modal fade" id="pointModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-map-marker-alt text-danger me-2"></i>
                    <span id="modalTitleText">Add Point</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editPointId">
                <input type="hidden" id="editPointX">
                <input type="hidden" id="editPointY">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Title</label>
                    <input type="text" id="pointTitle" class="form-control" placeholder="Enter point title" maxlength="100">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Description</label>
                    <textarea id="pointDescription" class="form-control" rows="8" placeholder="Enter detailed description"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-danger" id="deletePointBtn" style="display: none;">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button type="button" class="btn btn-primary" id="savePointBtn">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

@include('includes.lmsfooterJs')
<script src="{{ asset("/ckeditor_wiris/ckeditor4/ckeditor.js") }}"></script>
<script>
    let points = [];
    let currentX = 0, currentY = 0;
    let isEditing = false;
    let mainEditor = null;
    let pointEditor = null;
    let currentImageDataUrl = null;
    let pointModalInstance = null;
    let currentImageFile = null;

    function showNotification(message, type = 'success') {
        const notification = $('<div>')
            .addClass(type === 'success' ? 'ai-success-alert' : 'ai-error-alert')
            .html(`<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`)
            .appendTo('body');
        
        setTimeout(() => {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Convert image file to base64
    function imageToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => {
                resolve(reader.result);
            };
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    }

    // Function to parse AI response
    function parseAIResponse(response) {
        try {
            if (response.description && response.points) {
                return response;
            }
            
            if (response.raw_output) {
                let rawOutput = response.raw_output;
                let cleanOutput = rawOutput.trim();
                
                if (cleanOutput.startsWith('```json')) {
                    cleanOutput = cleanOutput.replace(/```json\n?/, '').replace(/```\n?$/, '');
                } else if (cleanOutput.startsWith('```')) {
                    cleanOutput = cleanOutput.replace(/```\n?/, '').replace(/```\n?$/, '');
                }
                
                const jsonMatch = cleanOutput.match(/\{[\s\S]*\}/);
                if (jsonMatch) {
                    const parsedData = JSON.parse(jsonMatch[0]);
                    if (parsedData.description && parsedData.points) {
                        return parsedData;
                    }
                }
            }
            
            return null;
        } catch (error) {
            console.error('Error parsing AI response:', error);
            return null;
        }
    }

    // Initialize CKEditor for main description
    CKEDITOR.replace('descriptionEditor', {
        height: 200,
        toolbar: [
            { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline'] },
            { name: 'paragraph', items: ['NumberedList', 'BulletedList'] },
            { name: 'links', items: ['Link', 'Unlink'] },
            { name: 'insert', items: ['Image', 'Table'] }
        ],
        filebrowserUploadUrl: "{{ route('uploadimage', ['_token' => csrf_token()]) }}",
        filebrowserUploadMethod: 'form'
    });

    // Initialize CKEditor for point description
    function initPointEditor() {
        if (pointEditor) {
            pointEditor.destroy();
        }
        pointEditor = CKEDITOR.replace('pointDescription', {
            height: 250,
            toolbar: [
                { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline'] },
                { name: 'paragraph', items: ['NumberedList', 'BulletedList'] },
                { name: 'links', items: ['Link', 'Unlink'] },
                { name: 'insert', items: ['Image'] }
            ],
            filebrowserUploadUrl: "{{ route('uploadimage', ['_token' => csrf_token()]) }}",
            filebrowserUploadMethod: 'form'
        });
    }

    CKEDITOR.on('instanceReady', function(ev) {
        if (ev.editor.name === 'descriptionEditor') {
            mainEditor = ev.editor;
        }
    });

    $(document).ready(function() {
        pointModalInstance = new bootstrap.Modal(document.getElementById('pointModal'));
        initPointEditor();
        
        $('#savePointBtn').off('click').on('click', function() {
            savePoint();
        });
        
        $('#deletePointBtn').off('click').on('click', function() {
            deletePoint();
        });
        
        $('#pointModal').on('hidden.bs.modal', function() {
            if (pointEditor) {
                pointEditor.destroy();
                pointEditor = null;
            }
            initPointEditor();
        });
    });

    function openModal(x, y, editMode = false, pointData = null) {
        currentX = x;
        currentY = y;
        
        setTimeout(() => {
            if (!pointEditor) {
                initPointEditor();
            }
            
            if (editMode && pointData) {
                $('#modalTitleText').text('Edit Point');
                $('#editPointId').val(pointData.id);
                $('#pointTitle').val(pointData.title);
                if (pointEditor) {
                    pointEditor.setData(pointData.description || '');
                }
                $('#editPointX').val(pointData.x);
                $('#editPointY').val(pointData.y);
                $('#deletePointBtn').show();
                isEditing = true;
            } else {
                $('#modalTitleText').text('Add Point');
                $('#editPointId').val('');
                $('#pointTitle').val('');
                if (pointEditor) {
                    pointEditor.setData('');
                }
                $('#editPointX').val('');
                $('#editPointY').val('');
                $('#deletePointBtn').hide();
                isEditing = false;
            }
            
            pointModalInstance.show();
            setTimeout(() => {
                document.getElementById('pointTitle').focus();
            }, 300);
        }, 100);
    }

    function savePoint() {
        let title = $('#pointTitle').val().trim();
        let description = pointEditor ? pointEditor.getData().trim() : '';
        
        if (!title) {
            alert('Please enter a title');
            return;
        }
        
        if (!description) {
            alert('Please enter a description');
            return;
        }
        
        if (isEditing) {
            let id = $('#editPointId').val();
            let index = points.findIndex(p => p.id == id);
            if (index !== -1) {
                points[index] = {
                    ...points[index],
                    title: title,
                    description: description,
                    x: currentX || points[index].x,
                    y: currentY || points[index].y
                };
                updatePointMarker(points[index]);
            }
        } else {
            let newPoint = {
                id: 'temp_' + Date.now(),
                title: title,
                description: description,
                x: currentX,
                y: currentY
            };
            points.push(newPoint);
            addPointMarker(newPoint);
        }
        
        updatePointsPreview();
        updateHiddenInput();
        pointModalInstance.hide();
    }

    function deletePoint() {
        if (confirm('Are you sure you want to delete this point?')) {
            let id = $('#editPointId').val();
            points = points.filter(p => p.id != id);
            $(`.point[data-id="${id}"]`).remove();
            updatePointsPreview();
            updateHiddenInput();
            pointModalInstance.hide();
        }
    }

    function addPointMarker(point) {
        let container = $('#imageContainer div');
        if (!container.length) return;
        
        let number = points.findIndex(p => p.id == point.id) + 1;
        let marker = $(`<span class="point" style="left:${point.x}px;top:${point.y}px" data-id="${point.id}" data-title="${escapeHtml(point.title)}">${number}</span>`);
        
        marker.on('click', function(e) {
            e.stopPropagation();
            openEditModal(point.id);
        });
        
        container.append(marker);
    }

    function updatePointMarker(point) {
        let marker = $(`.point[data-id="${point.id}"]`);
        let number = points.findIndex(p => p.id == point.id) + 1;
        if (marker.length) {
            marker.css({ left: point.x + 'px', top: point.y + 'px' })
                  .attr('data-title', point.title)
                  .html(number);
        } else {
            addPointMarker(point);
        }
    }

    function openEditModal(pointId) {
        let point = points.find(p => p.id == pointId);
        if (point) {
            openModal(point.x, point.y, true, point);
        }
    }

    function updatePointsPreview() {
        if (points.length > 0) {
            $('#pointsPreview').show();
            let html = '';
            points.forEach((point, idx) => {
                html += `<span class="point-badge" onclick="openEditModal('${point.id}')">
                            <span class="badge-number">${idx + 1}</span>
                            ${escapeHtml(point.title)}
                        </span>`;
            });
            $('#pointsList').html(html);
        } else {
            $('#pointsPreview').hide();
        }
    }

    function updateHiddenInput() {
        let data = points.map(p => ({
            id: p.id.toString().startsWith('temp_') ? null : p.id,
            title: p.title,
            description: p.description,
            x: p.x,
            y: p.y
        }));
        $('#pointsData').val(JSON.stringify(data));
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }

    // AI Generate Function with contextual prompts
    $('#generateAIContent').click(async function() {
        const title = $('#scenarioTitle').val().trim();
        const imageFile = $('#imageUpload')[0].files[0];
        const standardName = $('#standardName').val();
        const subjectName = $('#subjectName').val();
        const chapterName = $('#chapterName').val();
        
        if (!title) {
            showNotification('Please enter a title first', 'error');
            $('#scenarioTitle').focus();
            return;
        }
        
        if (!imageFile) {
            showNotification('Please upload an image first', 'error');
            return;
        }
        
        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.html('<span class="ai-loading"></span> Analyzing image...').prop('disabled', true);
        
        try {
            // Convert image to base64
            const imageBase64 = await imageToBase64(imageFile);
            
            // Create contextual prompt based on image content detection
            let imageTypeHint = '';
            // Simple detection based on filename or we can add more sophisticated detection
            const fileName = imageFile.name.toLowerCase();
            if (fileName.includes('face') || fileName.includes('human') || fileName.includes('person') || fileName.includes('man') || fileName.includes('woman')) {
                imageTypeHint = 'This appears to be a human face/body image. Focus on human anatomy parts like eyes, ears, nose, mouth, etc.';
            } else if (fileName.includes('strawberry') || fileName.includes('fruit') || fileName.includes('berry')) {
                imageTypeHint = 'This appears to be a strawberry/fruit image. Focus on parts like seeds, leaves, texture, color, etc.';
            } else if (fileName.includes('flower') || fileName.includes('plant')) {
                imageTypeHint = 'This appears to be a plant/flower image. Focus on parts like petals, stem, leaves, etc.';
            } else if (fileName.includes('animal')) {
                imageTypeHint = 'This appears to be an animal image. Focus on body parts, features, and characteristics.';
            }
            
            // Create comprehensive prompt with educational context
            const textPrompt = `You are an expert educational content creator specializing in ${subjectName} for ${standardName} grade students.

**Educational Context:**
- Subject: ${subjectName}
- Grade Level: ${standardName}
- Chapter: ${chapterName}
- Scenario Title: ${title}
${imageTypeHint ? `- Image Type Hint: ${imageTypeHint}` : ''}

**Task:** Analyze the provided image data and create age-appropriate, accurate educational content.

**What to look for in the image:**
1. Identify the main subject(s) in the image
2. Identify all visible parts, features, or elements
3. Note their positions (left, right, top, bottom, center)
4. Understand their educational significance

**Requirements:**

1. **Main Description (100-150 words):**
   - Provide a detailed, educational description suitable for ${standardName} grade students
   - Explain the subject matter in age-appropriate language
   - Use HTML formatting with <p> tags and bullet points (<ul><li>) where helpful
   - Include scientific/educational terminology appropriate for the grade level

2. **Interactive Points (At least 5-8 points):**
   Create points for each major element/part visible in the image:
   
   For a HUMAN FACE/ANATOMY image, identify:
   - Ears (outer ear, earlobe, ear canal)
   - Eyes (eyeball, eyelid, eyelashes, eyebrow)
   - Nose (nostrils, bridge, tip)
   - Mouth (lips, teeth, tongue)
   - Forehead, cheeks, chin
   - Hair (if visible)
   - Neck
   
   For a STRAWBERRY/FRUIT image, identify:
   - Seeds (achenes)
   - Leaves (calyx)
   - Flesh texture and color
   - Stem
   - Shape and size
   - Surface details
   
   For each point provide:
   - **Title:** Clear, concise name of the part (max 50 chars)
   - **Description:** Educational explanation (50-100 words with HTML <p> tags)
   - **Coordinates (x, y):** 0-100 based on ACTUAL position in image
     * x=0 is left edge, x=100 is right edge
     * y=0 is top edge, y=100 is bottom edge
     * Place marker EXACTLY on the element in the image

**Output Format (JSON only):**
{
    "description": "<p>Educational description with HTML...</p>",
    "points": [
        {
            "title": "Ear",
            "description": "<p>The ear is responsible for hearing and balance...</p>",
            "x": 25,
            "y": 45
        },
        {
            "title": "Strawberry Seeds (Achenes)",
            "description": "<p>The small yellow-brown specks on the strawberry are actually achenes...</p>",
            "x": 55,
            "y": 60
        }
    ]
}

**Important Guidelines:**
- Make content age-appropriate for ${standardName} grade
- Use correct scientific terminology
- Ensure coordinates accurately reflect where each part is located
- Provide educational value in every description
- Return ONLY the JSON object, no other text

Analyze the image data carefully and generate accurate, educational content.`;

            // Send to backend
            const response = await $.ajax({
                url: '{{ route("get-h5p-ai-scenario") }}',
                method: 'POST',
                data: JSON.stringify({ 
                    prompt: textPrompt,
                    image: imageBase64,
                    title: title,
                    standard: standardName,
                    chapter: chapterName,
                    subject: subjectName
                }),
                contentType: 'application/json',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}'
                },
                timeout: 120000
            });
            
            console.log('API Response:', response);
            
            if (response.status_code === 1) {
                const parsedData = parseAIResponse(response);
                console.log('Parsed Data:', parsedData);
                
                if (parsedData && parsedData.description) {
                    // Set description
                    if (mainEditor) {
                        mainEditor.setData(parsedData.description);
                        showNotification('✓ Educational description generated!', 'success');
                    }
                    
                    // Process points
                    if (parsedData.points && parsedData.points.length > 0) {
                        const $img = $('#scenarioImage');
                        if ($img.length) {
                            await new Promise((resolve) => {
                                if ($img[0].complete) resolve();
                                else $img.on('load', resolve);
                            });
                            
                            const imgWidth = $img.width();
                            const imgHeight = $img.height();
                            
                            if (imgWidth > 0 && imgHeight > 0) {
                                points = [];
                                $('.point').remove();
                                
                                let validCount = 0;
                                parsedData.points.forEach((point, idx) => {
                                    if (point.title && point.description) {
                                        let x = parseFloat(point.x);
                                        let y = parseFloat(point.y);
                                        
                                        if (isNaN(x)) x = 50;
                                        if (isNaN(y)) y = 50;
                                        
                                        x = Math.min(Math.max(x, 0), 100);
                                        y = Math.min(Math.max(y, 0), 100);
                                        
                                        let actualX = (x / 100) * imgWidth;
                                        let actualY = (y / 100) * imgHeight;
                                        
                                        actualX = Math.min(Math.max(actualX, 15), imgWidth - 15);
                                        actualY = Math.min(Math.max(actualY, 15), imgHeight - 15);
                                        
                                        console.log(`Point ${idx+1}: ${point.title} - Position: ${actualX}, ${actualY} (${x}%, ${y}%)`);
                                        
                                        points.push({
                                            id: 'temp_' + Date.now() + '_' + idx,
                                            title: point.title.substring(0, 100),
                                            description: point.description,
                                            x: actualX,
                                            y: actualY
                                        });
                                        validCount++;
                                    }
                                });
                                
                                if (validCount > 0) {
                                    points.forEach(point => addPointMarker(point));
                                    updatePointsPreview();
                                    updateHiddenInput();
                                    showNotification(`✓ ${validCount} interactive points generated with accurate placements!`, 'success');
                                } else {
                                    showNotification('⚠️ No valid points were generated', 'warning');
                                }
                            }
                        }
                    } else {
                        showNotification('⚠️ No points generated. The image may need clearer elements.', 'warning');
                    }
                } else {
                    showNotification('Could not parse AI response. Please try again.', 'error');
                }
            } else {
                showNotification(response.message || 'Generation failed. Please try again.', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Error: ' + (error.message || 'Unknown error'), 'error');
        } finally {
            $btn.html(originalHtml).prop('disabled', false);
        }
    });

    // Image upload handler
    $('#imageUpload').change(function(e) {
        let file = e.target.files[0];
        if (file) {
            currentImageFile = file;
            let reader = new FileReader();
            reader.onload = function(event) {
                currentImageDataUrl = event.target.result;
                $('#imageContainer').html(
                    '<div style="position:relative;display:inline-block;">' +
                    '<img id="scenarioImage" src="' + event.target.result + '" style="max-width:100%;height:auto;cursor:crosshair;">' +
                    '</div>'
                );
                points = [];
                $('#pointsData').val('');
                $('#pointsPreview').hide();
                showNotification('Image uploaded! Click "Generate AI" to create educational content.', 'success');
            }
            reader.readAsDataURL(file);
        }
    });

    $(document).on('click', '#scenarioImage', function(e) {
        let offset = $(this).offset();
        let x = Math.round(e.pageX - offset.left);
        let y = Math.round(e.pageY - offset.top);
        openModal(x, y);
    });

    $('form').on('submit', function(e) {
        if (mainEditor) mainEditor.updateElement();
        if (pointEditor) pointEditor.updateElement();
        
        let imageUploaded = $('#imageUpload').val();
        if (imageUploaded && points.length === 0) {
            if (!confirm('No interactive points added. Continue without points?')) {
                e.preventDefault();
                return false;
            }
        }
    });

    $(document).ready(function() {
        if (!$('meta[name="csrf-token"]').length) {
            $('head').append('<meta name="csrf-token" content="{{ csrf_token() }}">');
        }
    });
</script>
@include('includes.footer')
@endsection