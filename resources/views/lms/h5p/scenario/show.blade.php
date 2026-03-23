@extends('layout')
@section('container')
<link rel="stylesheet" href="/admin_dep/css/h5pCSS.css">
<!-- Add Bootstrap 5 CSS and JS -->
<!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
    /* Image Container Styles */
    .image-container {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 20px;
        margin-top: 20px;
        border: 1px solid #e9ecef;
    }

    .image-wrapper {
        position: relative;
        display: inline-block;
        max-width: 100%;
        border-radius: 8px;
        overflow: hidden;
    }

    #scenarioImage {
        max-width: 100%;
        height: auto;
        display: block;
    }

    /* Point Marker Styles - with numbers */
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
        z-index: 5;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        font-weight: bold;
        font-family: Arial, sans-serif;
    }

    .point:hover {
        transform: translate(-50%, -50%) scale(1.2);
        background: #c82333;
        z-index: 10;
    }

    .point::after {
        content: attr(data-title);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        white-space: nowrap;
        margin-bottom: 8px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.2s ease;
        pointer-events: none;
        z-index: 20;
        font-weight: normal;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }

    .point:hover::after {
        opacity: 1;
        visibility: visible;
        bottom: 130%;
    }

    .info-row {
        display: flex;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e9ecef;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .info-row:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .info-label {
        min-width: 120px;
        color: #495057;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .info-value {
        flex: 1;
        color: #6c757d;
    }
    .detail-btn {
        background: #6c757d;
        border: none;
        color: white;
        padding: 6px 16px;
        border-radius: 6px;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .detail-btn:hover {
        background: #5a6268;
        color: white;
    }

    .scenario-description {
        line-height: 1.6;
        color: #495057;
    }

    .scenario-description ul, 
    .scenario-description ol {
        padding-left: 20px;
        margin: 10px 0;
    }

    .scenario-description ul li,
    .scenario-description ol li {
        margin-bottom: 5px;
    }

    /* Points Table Styles */
    .points-table {
        width: 100%;
        margin-top: 20px;
    }

    .points-table th {
        background: #f8f9fa;
        padding: 10px;
        font-weight: 600;
        color: #495057;
        border-bottom: 2px solid #dee2e6;
    }

    .points-table td {
        padding: 10px;
        vertical-align: top;
        border-bottom: 1px solid #e9ecef;
    }

    .points-table tr:hover {
        background: #f8f9fa;
    }

    .point-badge {
        display: inline-block;
        width: 20px;
        height: 20px;
        background: #dc3545;
        border-radius: 50%;
        margin-right: 8px;
        text-align: center;
        line-height: 20px;
        font-size: 11px;
        font-weight: bold;
        color: white;
    }

    /* Modal Custom Styles - 80vh height */
    .modal-detail-custom {
        max-width: 90%;
        width: 90%;
    }
    
    .modal-detail-custom .modal-content {
        display: flex;
        flex-direction: column;
        max-height: 80vh;
    }
    
    .modal-detail-custom .modal-body {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
    }
    
    /* Point Modal Custom Styles */
    .modal-point-custom .modal-content {
        max-height: 80vh;
    }
    
    .modal-point-custom .modal-body {
        max-height: calc(80vh - 120px);
        overflow-y: auto;
    }
    
    /* Iframe Container Styles */
    .video-wrapper {
        position: relative;
        width: 100%;
        padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
        height: 0;
        overflow: hidden;
        border-radius: 8px;
        margin: 15px 0;
    }
    
    .video-wrapper iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border: none;
    }
    
    /* Responsive iframe for other content */
    .scenario-description iframe {
        width: 100%;
        border-radius: 8px;
        margin: 10px 0;
    }
    
    /* Ensure iframes are responsive */
    .scenario-description iframe:not([class*="video-wrapper"]) {
        max-width: 100%;
        height: auto;
        min-height: 100%;
    }
    
    /* Custom scrollbar */
    .modal-body::-webkit-scrollbar {
        width: 8px;
    }
    
    .modal-body::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    .modal-body::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }
    
    .modal-body::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    @media (max-width: 768px) {
        .info-row {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .detail-btn {
            width: 100%;
            justify-content: center;
        }
        
        .points-table {
            font-size: 14px;
        }
        
        .points-table th,
        .points-table td {
            padding: 8px;
        }
        
        .modal-detail-custom {
            max-width: 95%;
            width: 95%;
            margin: 10px auto;
        }
        
        .modal-detail-custom .modal-content {
            max-height: 85vh;
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

        <!-- Header -->
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
                        <i class="fas fa-map-marker-alt"></i>
                        <span>{{ count($data['scenario']->points) }}</span> Interactive Points
                    </div>
                </div>
            </div>
        </div>

        <div class="container">
            <!-- Image with Points -->
            <div class="image-container">
                <div class="info-row">
                    <div class="info-label">
                        <i class="fas fa-image"></i>
                        Interactive Image
                    </div>
                    <div class="info-value">
                        Click on any red dot to view details
                    </div>
                    <button type="button" class="detail-btn" onclick="openDetailModal()" title="Details">
                        <i class="fas fa-info-circle"></i>
                    </button>
                </div>
                @if($data['scenario']->file_path)
                <div class="image-wrapper">
                    <img id="scenarioImage" src="{{ $data['scenario']->file_path }}" alt="Scenario Image">
                    @foreach($data['scenario']->points as $index => $point)
                    <span class="point"
                        style="left:{{ $point->position_x }}px;top:{{ $point->position_y }}px"
                        data-title="{{ $point->title }}"
                        data-id="{{ $point->id }}"
                        data-description="{{ $point->description }}"
                        data-x="{{ $point->position_x }}"
                        data-y="{{ $point->position_y }}"
                        data-number="{{ $index + 1 }}"
                        onclick="showPointDetails(event, '{{ addslashes($point->title) }}', `{{ addslashes($point->description) }}`)">{{ $index + 1 }}</span>
                    @endforeach
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fas fa-image fa-3x" style="color: #adb5bd;"></i>
                    <p class="mt-3 text-muted">No image uploaded for this scenario.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Modal for Scenario Details - Custom Height 80vh -->
<div class="modal fade" id="detailDialogue" tabindex="-1" aria-labelledby="detailDialogueLabel" aria-hidden="true">
    <div class="modal-dialog modal-detail-custom modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailDialogueLabel">
                    <i class="fas fa-info-circle me-2"></i>
                    {{ $data['scenario']->title }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Image and Description Section -->
                <div class="row mb-4">
                    <div class="col-md-5 mb-3">
                        <img src="{{ $data['scenario']->file_path }}" class="img-fluid rounded" alt="Scenario Image" style="width: 100%;">
                    </div>
                    <div class="col-md-7">
                        <h6 class="fw-bold text-muted mb-2">Description</h6>
                        <div class="scenario-description">
                            {!! $data['scenario']->description !!}
                        </div>
                    </div>
                </div>

                <!-- Points Details Table -->
                <hr>
                <h6 class="fw-bold text-muted mb-3">
                    <i class="fas fa-map-marker-alt text-danger me-2"></i>
                    Interactive Points ({{ count($data['scenario']->points) }})
                </h6>
                
                @if(count($data['scenario']->points) > 0)
                <div class="table-responsive">
                    <table class="points-table">
                        <thead>
                             <tr>
                                <th width="5%">#</th>
                                <th width="30%">Title</th>
                                <th width="65%" class="text-left">Description</th>
                               </tr>
                        </thead>
                        <tbody>
                            @foreach($data['scenario']->points as $index => $point)
                            <tr>
                                <td class="text-center">
                                    <span class="point-badge">{{ $index + 1 }}</span>
                                </td>
                                <td>{{ $point->title }}</td>
                                <td>
                                    <div class="scenario-description" style="max-height: 200px; overflow-y: auto;">
                                        {!! $point->description !!}
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="text-center py-4">
                    <i class="fas fa-map-marker-alt fa-2x text-muted mb-2"></i>
                    <p class="text-muted">No interactive points added for this scenario.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Modal for Point Details -->
<div class="modal fade" id="pointModal" tabindex="-1" aria-labelledby="pointModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-point-custom">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="pointModalLabel">
                    <i class="fas fa-map-marker-alt me-2 text-danger"></i>
                    Point Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="fw-bold text-muted mb-2">Title</label>
                    <h5 id="modalPointTitle" class="text-dark"></h5>
                </div>
                <div style="width:100%;">
                    <label class="fw-bold text-muted mb-2">Description</label>
                    <div id="modalPointDescription" class="scenario-description"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('includes.lmsfooterJs')
<script>
    // Function to open detail modal
    function openDetailModal() {
        var detailModal = new bootstrap.Modal(document.getElementById('detailDialogue'));
        detailModal.show();
    }
    
    // Function to show point details in Bootstrap modal
    function showPointDetails(event, title, description) {
        event.stopPropagation();
        document.getElementById('modalPointTitle').textContent = title;
        let descriptionElement = document.getElementById('modalPointDescription');
        
        if (description && description.trim() !== '') {
            descriptionElement.innerHTML = description;
            autoEmbedYouTubeLinks(descriptionElement);
            // Fix iframe heights after embedding
            fixIframeHeights(descriptionElement);
        } else {
            descriptionElement.innerHTML = 'No description provided.';
        }
        
        // Show modal using Bootstrap
        var pointModal = new bootstrap.Modal(document.getElementById('pointModal'));
        pointModal.show();
    }
    
    function fixIframeHeights(container) {
        // Find all iframes that are not inside video-wrapper
        const iframes = container.querySelectorAll('iframe:not(.video-wrapper iframe)');
        
        iframes.forEach(iframe => {
            // Check if iframe has a specific height attribute
            if (iframe.hasAttribute('height')) {
                let height = iframe.getAttribute('height');
                if (height && !isNaN(parseInt(height))) {
                    // If height is specified, keep it but make it responsive
                    iframe.style.height = height + 'px';
                    iframe.style.width = '100%';
                } else {
                    // Default height for unspecified iframes
                    iframe.style.height = '400px';
                    iframe.style.width = '100%';
                }
            } else {
                // Check if iframe contains YouTube embed
                if (iframe.src && (iframe.src.includes('youtube.com') || iframe.src.includes('youtu.be'))) {
                    // Wrap YouTube iframes in responsive container
                    const wrapper = document.createElement('div');
                    wrapper.className = 'video-wrapper';
                    iframe.parentNode.insertBefore(wrapper, iframe);
                    wrapper.appendChild(iframe);
                } else {
                    // Default height for other iframes
                    iframe.style.height = '400px';
                    iframe.style.width = '100%';
                }
            }
        });
    }

    function autoEmbedYouTubeLinks(container) {
        const links = container.querySelectorAll('a');
        
        links.forEach(link => {
            const href = link.href || link.getAttribute('href');
            
            if (href && (href.includes('youtube.com') || href.includes('youtu.be'))) {
                let videoId = '';
                
                if (href.includes('youtube.com/watch')) {
                    const urlParams = new URLSearchParams(href.split('?')[1]);
                    videoId = urlParams.get('v');
                } else if (href.includes('youtu.be')) {
                    videoId = href.split('youtu.be/')[1].split('?')[0];
                }
                
                if (videoId) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'video-wrapper';
                    
                    const iframe = document.createElement('iframe');
                    iframe.src = `https://www.youtube.com/embed/${videoId}`;
                    iframe.title = 'YouTube video player';
                    iframe.frameBorder = '0';
                    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
                    iframe.allowFullscreen = true;
                    
                    wrapper.appendChild(iframe);
                    link.parentNode.replaceChild(wrapper, link);
                }
            }
        });
    }

    // Auto-embed YouTube links and fix iframe heights in scenario description
    $(document).ready(function() {
        const scenarioDescription = document.querySelector('.scenario-description');
        if (scenarioDescription) {
            autoEmbedYouTubeLinks(scenarioDescription);
            fixIframeHeights(scenarioDescription);
        }
        
        // Auto-embed YouTube links in all point descriptions
        $('.point').each(function() {
            let description = $(this).data('description');
            if (description) {
                let tempDiv = document.createElement('div');
                tempDiv.innerHTML = description;
                autoEmbedYouTubeLinks(tempDiv);
                fixIframeHeights(tempDiv);
                $(this).data('description', tempDiv.innerHTML);
            }
        });
        
        // Add number to each point badge in the table
        $('.point-badge').each(function(index) {
            $(this).text(index + 1);
        });
    });
</script>
@include('includes.footer')
@endsection