@extends('layout')
@section('container')
<link rel="stylesheet" href="/admin_dep/css/h5pCSS.css">
<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
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
        backdrop-filter: blur(5px);
    }
    
    .modal-content {
        background: white;
        margin: 5% auto;
        padding: 30px;
        border-radius: 20px;
        width: 90%;
        max-width: 1000px;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        animation: modalSlideIn 0.3s ease;
    }
    
    @keyframes modalSlideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f5ff;
    }
    
    .modal-header h3 {
        margin: 0;
        color: #1e3c72;
        font-weight: 600;
        font-size: 1.5rem;
    }
    
    .close-modal {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: #64748b;
        transition: color 0.3s ease;
    }
    
    .close-modal:hover {
        color: #1e3c72;
    }
    
    .modal-body {
        margin-bottom: 25px;
        max-height: 70vh;
        overflow-y: auto;
        padding-right: 10px;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding-top: 15px;
        border-top: 2px solid #f0f5ff;
    }
    
    
    /* Point Marker Styles */
    .point {
        position: absolute;
        width: 24px;
        height: 24px;
        background: linear-gradient(145deg, #ff416c, #ff4b2b);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(255, 65, 108, 0.3);
        border: 3px solid white;
        transition: all 0.3s ease;
        animation: pointPulse 2s infinite;
        z-index: 10;
    }
    
    @keyframes pointPulse {
        0%, 100% {
            transform: translate(-50%, -50%) scale(1);
            box-shadow: 0 4px 15px rgba(255, 65, 108, 0.3);
        }
        50% {
            transform: translate(-50%, -50%) scale(1.2);
            box-shadow: 0 8px 25px rgba(255, 65, 108, 0.5);
        }
    }
    
    .point:hover {
        transform: translate(-50%, -50%) scale(1.3);
        box-shadow: 0 8px 25px rgba(255, 65, 108, 0.5);
    }
    
    .point::after {
        content: attr(data-title);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #1e293b;
        color: white;
        padding: 5px 10px;
        border-radius: 8px;
        font-size: 12px;
        white-space: nowrap;
        margin-bottom: 8px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        pointer-events: none;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }
    
    .point:hover::after {
        opacity: 1;
        visibility: visible;
        bottom: 150%;
    }
    
    /* Image Container */
    #imageContainer {
        margin-top: 30px;
        padding: 20px;
        background: #f8fafd;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    }
    
    #imageContainer div {
        position: relative;
        display: inline-block;
        max-width: 100%;
    }
    
    #scenarioImage {
        max-width: 100%;
        height: auto;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        cursor: crosshair;
    }
    
    /* Points List Preview */
    .points-preview {
        margin-top: 20px;
        padding: 20px;
        background: #f8fafd;
        border-radius: 15px;
    }
    
    .points-preview h5 {
        color: #1e3c72;
        margin-bottom: 15px;
        font-weight: 600;
    }
    
    .points-list {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .point-badge {
        background: linear-gradient(145deg, #e0e7ff, #c7d2fe);
        color: #1e3c72;
        padding: 8px 15px;
        border-radius: 30px;
        font-size: 0.9rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .point-badge:hover {
        background: linear-gradient(145deg, #c7d2fe, #b1c3fd);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .point-badge i {
        color: #ff416c;
    }
    
    /* CKEditor Custom Styles */
    .cke_chrome {
        border-radius: 12px !important;
        border: 2px solid #e2e8f0 !important;
    }
    
    .cke_inner {
        border-radius: 10px !important;
    }
    
    .cke_top {
        border-radius: 10px 10px 0 0 !important;
    }
    
    .cke_bottom {
        border-radius: 0 0 10px 10px !important;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        color: #1e3c72;
        font-weight: 500;
        margin-bottom: 8px;
        display: block;
    }
    
    /* Modern Header Styles */
    .modern-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 25px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        color: white;
    }
    
    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .header-left {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    
    .header-icon {
        width: 70px;
        height: 70px;
        background: rgba(255,255,255,0.2);
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 35px;
        backdrop-filter: blur(10px);
    }
    
    .header-text h1 {
        margin: 0 0 10px 0;
        font-size: 28px;
        font-weight: 600;
    }
    
    .header-badges {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
    
    .header-badge {
        background: rgba(255,255,255,0.15);
        padding: 8px 16px;
        border-radius: 30px;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        backdrop-filter: blur(10px);
    }
    
    .header-stats {
        background: rgba(255,255,255,0.15);
        padding: 12px 24px;
        border-radius: 30px;
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        backdrop-filter: blur(10px);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .modal-content {
            margin: 10% auto;
            padding: 20px;
            width: 95%;
        }
        
        .header-content {
            flex-direction: column;
            text-align: center;
        }
        
        .header-left {
            flex-direction: column;
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
        <!-- Animated Header without GIF -->
        <div class="modern-header">
            <div class="header-content">
                <div class="header-left">
                    <div class="header-icon">
                        <i class="fas fa-robot"></i>
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
                <div class="header-right">
                    <div class="header-stats">
                        <i class="fas fa-cubes"></i>
                        {{ count($data['scenarioLists']) }} Scenarios
                    </div>
                </div>
            </div>
        </div>
        
        <div class="container">
            <form method="POST" action="{{route('scenario_based.store')}}" enctype="multipart/form-data" class="card p-4" style="border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: none;">
                @csrf
                <input type="hidden" name="standard_id" value="{{$data['standard_id']}}">
                <input type="hidden" name="chapter_id" value="{{$data['chapter_id']}}">
                <input type="hidden" name="subject_id" value="{{$data['subject_id']}}">
                <div class="row">
                    <div class="col-md-4 form-group mb-3">
                        <label style="color: #1e3c72; font-weight: 500;">Title</label>
                        <input type="text" name="title" class="form-control" style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 12px;" required>
                    </div>
                    <div class="col-md-4 form-group mb-3">
                        <label style="color: #1e3c72; font-weight: 500;">Upload Image</label>
                        <input type="file" name="image" id="imageUpload" class="form-control" style="border-radius: 12px; border: 2px solid #e2e8f0; padding: 10px;" accept="image/*" required>
                        <small class="text-muted">Click on image to add interactive points</small>
                    </div>
                    <div class="col-md-12 form-group mb-3">
                        <label style="color: #1e3c72; font-weight: 500;">Description</label>
                        <textarea name="description" id="descriptionEditor" class="form-control" style="height: 200px;"></textarea>
                    </div>
                </div>

                <div id="imageContainer"></div>
                
                <!-- Points Preview Section -->
                <div id="pointsPreview" class="points-preview" style="display: none;">
                    <h5><i class="fas fa-map-marker-alt me-2" style="color: #ff416c;"></i>Added Points</h5>
                    <div class="points-list" id="pointsList"></div>
                </div>

                <input type="hidden" name="points" id="pointsData">
                <div class="row">
                    <div class="col-md-12">
                        <center>
                            <button type="submit" class="btn btn-success mt-3" style="padding: 12px 40px; border-radius: 12px; font-weight: 600;">Submit</button>
                        </center>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for adding points -->
<div id="pointModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-map-marker-alt me-2" style="color: #ff416c;"></i>Add Interactive Point</h3>
            <button class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 50vh !important; overflow-y: auto;">
            <div class="form-group">
                <label><i class="fas fa-heading me-2"></i>Point Title</label>
                <input type="text" id="pointTitle" placeholder="Enter title for this point" maxlength="100" class="form-control">
            </div>
            <div class="form-group">
                <label><i class="fas fa-align-left me-2"></i>Description</label>
                <textarea id="pointDescription" placeholder="Enter detailed description for this point" class="form-control" style="height: 300px;"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()" style="padding: 10px 25px; border-radius: 10px;">Cancel</button>
            <button class="btn btn-primary" onclick="savePoint()" style="padding: 10px 25px; border-radius: 10px; background: linear-gradient(145deg, #667eea, #764ba2); border: none;">Save Point</button>
        </div>
    </div>
</div>

@include('includes.lmsfooterJs')
<!-- CKEditor 4 Script with Media Upload -->
<script src="https://cdn.ckeditor.com/4.22.1/full-all/ckeditor.js"></script>
<script>
    let points = [];
    let currentX = 0;
    let currentY = 0;
    let mainEditor = null;
    let pointEditor = null;

    // CKEditor configuration for main description
    CKEDITOR.replace('descriptionEditor', {
        height: '200px',
        toolbar: [
            { name: 'document', items: ['Source', '-', 'Save', 'NewPage', 'Preview', 'Print', '-', 'Templates'] },
            { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo'] },
            { name: 'editing', items: ['Find', 'Replace', '-', 'SelectAll', '-', 'Scayt'] },
            { name: 'forms', items: ['Form', 'Checkbox', 'Radio', 'TextField', 'Textarea', 'Select', 'Button', 'ImageButton', 'HiddenField'] },
            '/',
            { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat'] },
            { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', 'CreateDiv', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-', 'BidiLtr', 'BidiRtl', 'Language'] },
            { name: 'links', items: ['Link', 'Unlink', 'Anchor'] },
            { name: 'insert', items: ['Image', 'Flash', 'Table', 'HorizontalRule', 'Smiley', 'SpecialChar', 'PageBreak', 'Iframe'] },
            '/',
            { name: 'styles', items: ['Styles', 'Format', 'Font', 'FontSize'] },
            { name: 'colors', items: ['TextColor', 'BGColor'] },
            { name: 'tools', items: ['Maximize', 'ShowBlocks'] },
            { name: 'about', items: ['About'] }
        ],
        filebrowserUploadUrl: "{{ route('uploadimage', ['_token' => csrf_token()]) }}",
        filebrowserUploadMethod: 'form',
        allowedContent: true,
        extraPlugins: 'image,flash,link',
        removePlugins: 'resize',
        enterMode: CKEDITOR.ENTER_BR,
        shiftEnterMode: CKEDITOR.ENTER_P,
        autoParagraph: false,
        fillEmptyBlocks: false,
        // Ensure proper list handling
        forceEnterMode: true,
        // Enable media embedding
        embed_provider: '//ckeditor.iframe.ly/api/oembed?url={url}&callback={callback}'
    });

    // Initialize CKEditor for main description
    CKEDITOR.on('instanceReady', function(ev) {
        if (ev.editor.name === 'descriptionEditor') {
            mainEditor = ev.editor;
            
            // Ensure proper list formatting
            ev.editor.on('contentDom', function() {
                var editable = ev.editor.editable();
                editable.attachListener(editable, 'keydown', function(evt) {
                    // Handle enter key for lists
                    if (evt.data.getKey() === 13) {
                        setTimeout(function() {
                            var selection = ev.editor.getSelection();
                            var element = selection.getStartElement();
                            if (element && element.is('li')) {
                                ev.editor.execCommand('numberedlist');
                            }
                        }, 10);
                    }
                });
            });
        }
    });

    function initializePointEditor() {
        if (!pointEditor) {
            pointEditor = CKEDITOR.replace('pointDescription', {
                height: '300px',
                toolbar: [
                    { name: 'document', items: ['Source'] },
                    { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo'] },
                    { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', '-', 'RemoveFormat'] },
                    { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'] },
                    { name: 'links', items: ['Link', 'Unlink'] },
                    { name: 'insert', items: ['Image', 'Flash', 'Table', 'HorizontalRule', 'Smiley', 'SpecialChar', 'Iframe'] },
                    '/',
                    { name: 'styles', items: ['Styles', 'Format', 'Font', 'FontSize'] },
                    { name: 'colors', items: ['TextColor', 'BGColor'] }
                ],
                filebrowserUploadUrl: "{{ route('uploadimage', ['_token' => csrf_token()]) }}",
                filebrowserUploadMethod: 'form',
                allowedContent: true,
                extraPlugins: 'image,flash,link',
                enterMode: CKEDITOR.ENTER_BR,
                shiftEnterMode: CKEDITOR.ENTER_P,
                autoParagraph: false,
                fillEmptyBlocks: false,
                embed_provider: '//ckeditor.iframe.ly/api/oembed?url={url}&callback={callback}'
            });
        }
    }

    function openModal(x, y) {
        currentX = x;
        currentY = y;
        document.getElementById('pointModal').style.display = 'block';
        
        // Initialize CKEditor for point description
        setTimeout(function() {
            initializePointEditor();
            document.getElementById('pointTitle').focus();
        }, 300);
    }

    function closeModal() {
        document.getElementById('pointModal').style.display = 'none';
        
        // Destroy CKEditor instance to prevent duplicates
        if (pointEditor) {
            pointEditor.destroy();
            pointEditor = null;
        }
        document.getElementById('pointTitle').value = '';
    }

    function savePoint() {
        let title = document.getElementById('pointTitle').value.trim();
        let description = '';

        // Get description from CKEditor
        if (pointEditor) {
            description = pointEditor.getData();
        }

        if (!title) {
            alert('Please enter a title for this point');
            return;
        }

        if (!description) {
            alert('Please enter a description for this point');
            return;
        }

        points.push({
            title: title,
            description: description,
            x: currentX,
            y: currentY
        });

        // Add point marker to image
        $('#imageContainer div').append(
            '<span class="point" style="left:' + currentX + 'px;top:' + currentY + 'px" data-title="' + title.replace(/"/g, '&quot;') + '"></span>'
        );

        // Update points preview
        updatePointsPreview();

        // Update hidden input
        $('#pointsData').val(JSON.stringify(points));

        // Close modal
        closeModal();
    }

    function updatePointsPreview() {
        if (points.length > 0) {
            $('#pointsPreview').show();
            let html = '';
            points.forEach((point, index) => {
                html += '<span class="point-badge" onclick="showPointDetails(' + index + ')">' +
                        '<i class="fas fa-map-marker-alt"></i>' +
                        point.title +
                        '</span>';
            });
            $('#pointsList').html(html);
        } else {
            $('#pointsPreview').hide();
        }
    }

    function showPointDetails(index) {
        let point = points[index];
        // Create a modal or tooltip to show rich content
        let previewWindow = window.open('', '_blank', 'width=800,height=600');
        previewWindow.document.write(`
            <html>
                <head>
                    <title>${point.title}</title>
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            padding: 30px;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                        }
                        .container {
                            background: rgba(255,255,255,0.1);
                            backdrop-filter: blur(10px);
                            padding: 30px;
                            border-radius: 20px;
                            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
                        }
                        h2 {
                            margin-top: 0;
                            color: white;
                            border-bottom: 2px solid rgba(255,255,255,0.3);
                            padding-bottom: 15px;
                        }
                        .content {
                            line-height: 1.6;
                        }
                        img { max-width: 100%; height: auto; }
                        ul, ol { padding-left: 30px; }
                        li { margin-bottom: 5px; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h2><i class="fas fa-map-marker-alt" style="margin-right: 10px;"></i>${point.title}</h2>
                        <div class="content">${point.description}</div>
                    </div>
                </body>
            </html>
        `);
        previewWindow.document.close();
    }

    $('#imageUpload').change(function(e) {
        let file = e.target.files[0];
        if (file) {
            let reader = new FileReader();

            reader.onload = function(event) {
                $('#imageContainer').html(
                    '<div style="position:relative;display:inline-block;">' +
                    '<img id="scenarioImage" src="' + event.target.result + '" style="max-width:100%;height:auto;cursor:crosshair;">' +
                    '</div>'
                );
                
                // Reset points when new image is uploaded
                points = [];
                $('#pointsData').val('');
                $('#pointsPreview').hide();
            }

            reader.readAsDataURL(file);
        }
    });

    $(document).on('click', '#scenarioImage', function(e) {
        let offset = $(this).offset();
        let x = Math.round(e.pageX - offset.left);
        let y = Math.round(e.pageY - offset.top);
        
        // Open modal
        openModal(x, y);
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
        let modal = document.getElementById('pointModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    // Handle escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    // Form validation before submit
    $('form').on('submit', function(e) {
        // Ensure CKEditor content is updated in textarea
        if (mainEditor) {
            mainEditor.updateElement();
        }
        
        if (pointEditor) {
            pointEditor.updateElement();
        }
        
        // Validate points if image is uploaded
        let imageUploaded = $('#imageUpload').val();
        if (imageUploaded && points.length === 0) {
            if (!confirm('No interactive points added. Are you sure you want to continue?')) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Initialize tooltips
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>

<!-- Route for image upload - Add this in your web.php -->
<!-- Route::post('/upload-image', 'ImageUploadController@upload')->name('uploadimage'); -->

@include('includes.footer')
@endsection