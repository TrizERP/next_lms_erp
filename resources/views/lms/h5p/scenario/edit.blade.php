@extends('layout')
@section('container')
<link rel="stylesheet" href="/admin_dep/css/h5pCSS.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
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
    
    /* CKEditor Content Styles - Ensure links are visible */
    .ck-editor__editable {
        min-height: 250px;
    }
    
    .ck-content a {
        color: #0d6efd !important;
        text-decoration: underline !important;
        cursor: pointer !important;
    }
    
    .ck-content a:hover {
        color: #0a58ca !important;
        text-decoration: underline !important;
    }
    
    .modal-body .ck-content {
        max-height: 400px;
        overflow-y: auto;
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
                        <i class="fas fa-edit"></i>
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
            <form method="POST" action="{{ route('scenario_based.update', $data['scenario']->id) }}" enctype="multipart/form-data" class="card p-4" style="border-radius: 12px; border: 1px solid #e9ecef;">
                @csrf
                @method('PUT')
                <input type="hidden" name="standard_id" value="{{ $data['standard_id'] }}">
                <input type="hidden" name="chapter_id" value="{{ $data['chapter_id'] }}">
                <input type="hidden" name="subject_id" value="{{ $data['subject_id'] }}">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Title</label>
                        <input type="text" name="title" class="form-control" value="{{ $data['scenario']->title }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Upload New Image</label>
                        <input type="file" name="image" id="imageUpload" class="form-control" accept="image/*">
                        <small class="text-muted">Click on image to add interactive points</small>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label>Description</label>
                        <textarea name="description" id="descriptionEditor" class="form-control" style="height: 200px;">{{ $data['scenario']->description }}</textarea>
                    </div>
                </div>

                <div id="imageContainer">
                    @if($data['scenario']->file_path)
                    <div style="position:relative;display:inline-block;">
                        <img id="scenarioImage" src="{{ $data['scenario']->file_path }}">
                        @foreach($data['scenario']->points as $index => $point)
                        <span class="point" 
                              style="left:{{ $point->position_x }}px;top:{{ $point->position_y }}px" 
                              data-id="{{ $point->id }}"
                              data-title="{{ $point->title }}"
                              data-description="{{ htmlspecialchars($point->description, ENT_QUOTES) }}"
                              data-x="{{ $point->position_x }}"
                              data-y="{{ $point->position_y }}">{{ $index + 1 }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
                
                <div id="pointsPreview" class="points-preview" style="{{ count($data['scenario']->points) > 0 ? 'display:block;' : 'display:none;' }}">
                    <h5><i class="fas fa-map-marker-alt me-2"></i>Added Points</h5>
                    <div class="points-list" id="pointsList">
                        @foreach($data['scenario']->points as $index => $point)
                        <span class="point-badge" data-point-id="{{ $point->id }}" data-point-index="{{ $index + 1 }}">
                            <span class="badge-number">{{ $index + 1 }}</span>
                            {{ $point->title }}
                        </span>
                        @endforeach
                    </div>
                </div>

                <input type="hidden" name="points" id="pointsData" value="{{ json_encode($data['scenario']->points) }}">
                
                <div class="action-buttons">
                    <button type="submit" class="btn btn-success btn-icon">
                        <i class="fas fa-save"></i> Update
                    </button>
                  
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Point Modal with CKEditor -->
<div class="modal fade" id="pointModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-map-marker-alt text-danger me-2"></i><span id="modalTitleText">Add Point</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pointId">
                <input type="hidden" id="pointX">
                <input type="hidden" id="pointY">
                
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" id="pointTitle" class="form-control" placeholder="Point title">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description (Supports links, images, lists)</label>
                    <textarea id="pointDesc" class="form-control" rows="8"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> Cancel</button>
                <button type="button" class="btn btn-danger" id="deleteBtn" style="display:none"><i class="fas fa-trash"></i> Delete</button>
                <button type="button" class="btn btn-primary" id="saveBtn"><i class="fas fa-save"></i> Save</button>
            </div>
        </div>
    </div>
</div>

@include('includes.lmsfooterJs')
<script src="https://cdn.ckeditor.com/4.22.1/full-all/ckeditor.js"></script>
<script>
    let points = [];
    let currentX = 0, currentY = 0;
    let isEditing = false;
    let mainEditor, pointEditor;
    let pointModalInstance;

    // Initialize CKEditor for main description
    CKEDITOR.replace('descriptionEditor', {
        height: 200,
        toolbar: [
            { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike'] },
            { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent'] },
            { name: 'links', items: ['Link', 'Unlink', 'Anchor'] },
            { name: 'insert', items: ['Image', 'Table', 'HorizontalRule', 'SpecialChar'] },
            { name: 'styles', items: ['Format', 'Font', 'FontSize'] },
            { name: 'colors', items: ['TextColor', 'BGColor'] },
            { name: 'tools', items: ['Maximize'] }
        ],
        filebrowserUploadUrl: "{{ route('uploadimage', ['_token' => csrf_token()]) }}",
        filebrowserUploadMethod: 'form',
        allowedContent: true,
        removePlugins: 'resize',
        extraPlugins: 'image,link,table'
    });

    // Initialize CKEditor for point description with full toolbar
    function initPointEditor() {
        if (pointEditor) {
            pointEditor.destroy();
        }
        
        pointEditor = CKEDITOR.replace('pointDesc', {
            height: 300,
            toolbar: [
                { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', '-', 'RemoveFormat'] },
                { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote'] },
                { name: 'links', items: ['Link', 'Unlink', 'Anchor'] },
                { name: 'insert', items: ['Image', 'Table', 'HorizontalRule', 'SpecialChar', 'PageBreak'] },
                { name: 'styles', items: ['Format', 'Font', 'FontSize'] },
                { name: 'colors', items: ['TextColor', 'BGColor'] },
                { name: 'document', items: ['Source'] },
                { name: 'tools', items: ['Maximize'] }
            ],
            filebrowserUploadUrl: "{{ route('uploadimage', ['_token' => csrf_token()]) }}",
            filebrowserUploadMethod: 'form',
            allowedContent: true,
            removePlugins: 'resize',
            extraPlugins: 'image,link,table,sourcearea',
            // Ensure links are properly formatted
            linkShowTargetTab: true,
            linkShowAdvancedTab: true,
            // Allow embedding external content
            embed_provider: '//ckeditor.iframe.ly/api/oembed?url={url}&callback={callback}'
        });
        
        // Add event listener for link dialog to ensure proper formatting
        pointEditor.on('dialogDefinition', function(ev) {
            var dialogName = ev.data.name;
            var dialogDefinition = ev.data.definition;
            
            if (dialogName === 'link') {
                var infoTab = dialogDefinition.getContents('info');
                var urlField = infoTab.get('url');
                urlField['default'] = 'http://';
            }
        });
    }

    $(document).ready(function() {
        // Initialize modal
        pointModalInstance = new bootstrap.Modal(document.getElementById('pointModal'));
        initPointEditor();
        
        // Load existing points
        @if($data['scenario']->points)
            points = {!! json_encode($data['scenario']->points) !!}.map(p => ({
                id: p.id, 
                title: p.title, 
                description: p.description, 
                x: p.position_x, 
                y: p.position_y
            }));
        @endif
        
        updatePointsPreview();
        
        // Add click handlers to existing points
        $('.point').each(function() {
            $(this).off('click').on('click', function(e) {
                e.stopPropagation();
                let id = $(this).data('id');
                let point = points.find(p => p.id == id);
                if (point) {
                    openEditModal(point);
                }
            });
        });
        
        // Add click handler for point badges
        $('.point-badge').each(function() {
            $(this).off('click').on('click', function() {
                let id = $(this).data('point-id');
                let point = points.find(p => p.id == id);
                if (point) {
                    openEditModal(point);
                }
            });
        });
        
        // Save button click handler
        $('#saveBtn').off('click').on('click', function() {
            savePoint();
        });
        
        // Delete button click handler
        $('#deleteBtn').off('click').on('click', function() {
            deletePoint();
        });
        
        // Modal close handler to destroy CKEditor and reinitialize
        $('#pointModal').on('hidden.bs.modal', function() {
            if (pointEditor) {
                pointEditor.destroy();
                pointEditor = null;
            }
            initPointEditor();
        });
    });

    function openEditModal(point) {
        isEditing = true;
        $('#pointId').val(point.id);
        $('#pointTitle').val(point.title);
        $('#modalTitleText').text('Edit Point');
        
        // Ensure CKEditor is initialized
        if (!pointEditor) {
            initPointEditor();
        }
        
        // Set CKEditor content with proper HTML rendering
        setTimeout(function() {
            if (pointEditor && point.description) {
                pointEditor.setData(point.description);
            } else if (pointEditor) {
                pointEditor.setData('');
            }
        }, 100);
        
        $('#pointX').val(point.x);
        $('#pointY').val(point.y);
        $('#deleteBtn').show();
        pointModalInstance.show();
    }

    function openAddModal(x, y) {
        isEditing = false;
        currentX = x;
        currentY = y;
        $('#pointId').val('');
        $('#pointTitle').val('');
        $('#modalTitleText').text('Add Point');
        
        // Ensure CKEditor is initialized
        if (!pointEditor) {
            initPointEditor();
        }
        
        // Clear CKEditor content
        setTimeout(function() {
            if (pointEditor) {
                pointEditor.setData('');
            }
        }, 100);
        
        $('#pointX').val('');
        $('#pointY').val('');
        $('#deleteBtn').hide();
        pointModalInstance.show();
    }

    function savePoint() {
        let title = $('#pointTitle').val().trim();
        let description = '';
        
        // Get description from CKEditor
        if (pointEditor) {
            description = pointEditor.getData().trim();
        }
        
        if (!title) {
            alert('Please enter a title');
            return;
        }
        
        if (!description) {
            alert('Please enter a description');
            return;
        }
        
        if (isEditing) {
            // Update existing point
            let id = $('#pointId').val();
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
            // Add new point
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
            let id = $('#pointId').val();
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
        let marker = $(`<span class="point" style="left:${point.x}px;top:${point.y}px" data-id="${point.id}" data-title="${escapeHtml(point.title)}" data-description="${escapeHtml(point.description)}">${number}</span>`);
        
        marker.on('click', function(e) {
            e.stopPropagation();
            openEditModal(point);
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

    function updatePointsPreview() {
        if (points.length > 0) {
            $('#pointsPreview').show();
            let html = '';
            points.forEach((point, idx) => {
                html += `<span class="point-badge" data-point-id="${point.id}">
                            <span class="badge-number">${idx + 1}</span>
                            ${escapeHtml(point.title)}
                        </span>`;
            });
            $('#pointsList').html(html);
            
            // Re-attach click handlers to badges
            $('.point-badge').off('click').on('click', function() {
                let id = $(this).data('point-id');
                let point = points.find(p => p.id == id);
                if (point) {
                    openEditModal(point);
                }
            });
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

    $('#imageUpload').change(function(e) {
        let file = e.target.files[0];
        if (file) {
            let reader = new FileReader();
            reader.onload = function(event) {
                $('#imageContainer').html(`<div style="position:relative;display:inline-block;"><img id="scenarioImage" src="${event.target.result}"></div>`);
                setTimeout(() => {
                    points.forEach(p => addPointMarker(p));
                }, 100);
            };
            reader.readAsDataURL(file);
        }
    });

    $(document).on('click', '#scenarioImage', function(e) {
        let offset = $(this).offset();
        let x = Math.round(e.pageX - offset.left);
        let y = Math.round(e.pageY - offset.top);
        openAddModal(x, y);
    });

    $('form').on('submit', function() {
        if (mainEditor) mainEditor.updateElement();
        if (pointEditor) pointEditor.updateElement();
    });
</script>
@include('includes.footer')
@endsection