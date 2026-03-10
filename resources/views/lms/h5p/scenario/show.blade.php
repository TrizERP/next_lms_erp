@extends('layout')
@section('container')
<link rel="stylesheet" href="/admin_dep/css/h5pCSS.css">
<style>
    /* Combined Info Card Styles */
    .combined-info-card {
        background: white;
        border-radius: 25px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        border: 1px solid rgba(102, 126, 234, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .combined-info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15);
    }

    .info-row {
        display: flex;
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 2px solid #f0f5ff;
    }

    .info-row:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .info-label {
        min-width: 120px;
        color: #667eea;
        font-weight: 600;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .info-label i {
        font-size: 1.3rem;
        width: 24px;
    }

    .info-value {
        flex: 1;
        color: #2d3748;
        font-size: 1.1rem;
        line-height: 1.6;
        padding-left: 20px;
    }

    /* Image Container Styles */
    .image-container {
        background: #f8fafd;
        border-radius: 25px;
        padding: 30px;
        margin-top: 20px;
        border: 2px dashed rgba(102, 126, 234, 0.2);
    }

    .image-wrapper {
        position: relative;
        display: inline-block;
        max-width: 100%;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }

    #scenarioImage {
        max-width: 100%;
        height: auto;
        display: block;
        transition: transform 0.3s ease;
    }

    .image-wrapper:hover #scenarioImage {
        transform: scale(1.02);
    }

    /* Point Marker Styles */
    .point {
        position: absolute;
        width: 30px;
        height: 30px;
        background: linear-gradient(145deg, #ff416c, #ff4b2b);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(255, 65, 108, 0.4);
        border: 3px solid white;
        transition: all 0.3s ease;
        animation: pointPulse 2s infinite;
        z-index: 10;
    }

    @keyframes pointPulse {

        0%,
        100% {
            transform: translate(-50%, -50%) scale(1);
            box-shadow: 0 4px 15px rgba(255, 65, 108, 0.4);
        }

        50% {
            transform: translate(-50%, -50%) scale(1.2);
            box-shadow: 0 8px 25px rgba(255, 65, 108, 0.6);
        }
    }

    .point:hover {
        transform: translate(-50%, -50%) scale(1.3);
        box-shadow: 0 8px 25px rgba(255, 65, 108, 0.6);
        animation: none;
    }

    .point::after {
        content: attr(data-title);
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: #1e293b;
        color: white;
        padding: 8px 15px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
        margin-bottom: 10px;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        pointer-events: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        z-index: 20;
    }

    .point:hover::after {
        opacity: 1;
        visibility: visible;
        bottom: 150%;
    }

    /* View Modal Styles */
    .view-modal .modal-content {
        background: white;
        border-radius: 30px;
        padding: 0;
        overflow: hidden;
        max-width: 800px;
        width: 95%;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        margin: 3% auto;
    }

    .view-modal .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px 25px;
        border-bottom: none;
        color: white;
        flex-shrink: 0;
    }

    .view-modal .modal-header h3 {
        margin: 0;
        font-weight: 600;
        font-size: 1.4rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .view-modal .modal-header h3 i {
        font-size: 1.6rem;
        color: rgba(255, 255, 255, 0.9);
    }

    .view-modal .modal-body {
        padding: 25px;
        overflow-y: auto !important;
        flex: 1;
        max-height: calc(90vh - 80px);
    }

    .point-detail {
        background: #f8fafd;
        border-radius: 20px;
        padding: 20px;
    }

    .point-detail-item {
        margin-bottom: 20px;
    }

    .point-detail-item:last-child {
        margin-bottom: 0;
    }

    .point-detail-label {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #667eea;
        font-weight: 600;
        margin-bottom: 10px;
        font-size: 1rem;
    }

    .point-detail-label i {
        font-size: 1.2rem;
    }

    .point-detail-content {
        background: white;
        padding: 15px 20px;
        border-radius: 16px;
        color: #2d3748;
        font-size: 1rem;
        line-height: 1.6;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
        border: 1px solid rgba(102, 126, 234, 0.1);
        max-height: 500px;
        overflow-y: auto;
    }

    .point-detail-content.title-content {
        font-weight: 600;
        color: #1e3c72;
        font-size: 1.1rem;
    }

    /* Description Content Styles - CRITICAL FOR BULLET POINTS */
    .point-detail-content ul, 
    .point-detail-content ol,
    .scenario-description ul,
    .scenario-description ol {
        display: block;
        list-style-type: disc !important;
        padding-left: 30px !important;
        margin: 15px 0 !important;
    }

    .point-detail-content ol,
    .scenario-description ol {
        list-style-type: decimal !important;
    }

    .point-detail-content ul li,
    .point-detail-content ol li,
    .scenario-description ul li,
    .scenario-description ol li {
        display: list-item !important;
        margin-bottom: 8px !important;
        line-height: 1.6 !important;
    }

    .point-detail-content ul li {
        list-style-type: disc !important;
    }

    .point-detail-content ol li {
        list-style-type: decimal !important;
    }

    .point-detail-content ul ul,
    .point-detail-content ol ul,
    .scenario-description ul ul,
    .scenario-description ol ul {
        list-style-type: circle !important;
        margin: 8px 0 !important;
    }

    .point-detail-content ul ul ul,
    .point-detail-content ol ul ul,
    .scenario-description ul ul ul,
    .scenario-description ol ul ul {
        list-style-type: square !important;
    }

    /* Link and Iframe Styles */
    .point-detail-content a,
    .scenario-description a {
        color: #667eea !important;
        text-decoration: none !important;
        font-weight: 500 !important;
        transition: color 0.3s ease !important;
        display: inline-block;
        margin: 5px 0;
    }

    .point-detail-content a:hover,
    .scenario-description a:hover {
        color: #764ba2 !important;
        text-decoration: underline !important;
    }

    /* Iframe Container Styles */
    .point-detail-content .iframe-container,
    .scenario-description .iframe-container,
    .point-detail-content iframe,
    .scenario-description iframe {
        width: 100% !important;
        max-width: 100% !important;
        border-radius: 12px !important;
        margin: 15px 0 !important;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
        border: none !important;
        aspect-ratio: 16/9 !important;
    }

    /* Video Embed Styles */
    .point-detail-content .video-wrapper,
    .scenario-description .video-wrapper {
        position: relative;
        width: 100%;
        padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
        height: 0;
        overflow: hidden;
        border-radius: 12px;
        margin: 15px 0;
    }

    .point-detail-content .video-wrapper iframe,
    .scenario-description .video-wrapper iframe {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border-radius: 12px;
    }

    /* Style for embedded YouTube links */
    .point-detail-content a[href*="youtube.com"],
    .point-detail-content a[href*="youtu.be"],
    .scenario-description a[href*="youtube.com"],
    .scenario-description a[href*="youtu.be"] {
        background: linear-gradient(145deg, #667eea, #764ba2);
        color: white !important;
        padding: 10px 20px;
        border-radius: 50px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        text-decoration: none !important;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        margin: 5px 0;
    }

    .point-detail-content a[href*="youtube.com"]:hover,
    .point-detail-content a[href*="youtu.be"]:hover,
    .scenario-description a[href*="youtube.com"]:hover,
    .scenario-description a[href*="youtu.be"]:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        text-decoration: none !important;
        color: white !important;
    }

    .point-detail-content a[href*="youtube.com"] i,
    .point-detail-content a[href*="youtu.be"] i,
    .scenario-description a[href*="youtube.com"] i,
    .scenario-description a[href*="youtu.be"] i {
        font-size: 1.2rem;
    }

    .point-detail-content p,
    .scenario-description p {
        margin-bottom: 10px !important;
    }

    .point-detail-content h1, 
    .point-detail-content h2, 
    .point-detail-content h3, 
    .point-detail-content h4,
    .scenario-description h1,
    .scenario-description h2,
    .scenario-description h3,
    .scenario-description h4 {
        margin: 15px 0 10px 0 !important;
        color: #1e3c72 !important;
    }

    .point-detail-content img,
    .scenario-description img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
        margin: 10px 0;
    }

    .point-detail-content table,
    .scenario-description table {
        width: 100%;
        border-collapse: collapse;
        margin: 10px 0;
    }

    .point-detail-content th,
    .point-detail-content td,
    .scenario-description th,
    .scenario-description td {
        border: 1px solid #e2e8f0;
        padding: 8px 12px;
        text-align: left;
    }

    .point-detail-content th,
    .scenario-description th {
        background: #f7fafc;
        font-weight: 600;
    }

    .close-btn {
        background: linear-gradient(145deg, #667eea, #764ba2);
        border: none;
        padding: 12px 30px;
        border-radius: 50px;
        color: white;
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        margin-top: 20px;
    }

    .close-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    /* Scenario Description Styles */
    .scenario-description {
        line-height: 1.8;
        color: #2d3748;
    }

    /* Ensure bullet points are visible in all browsers */
    .scenario-description ul, 
    .point-detail-content ul {
        list-style-type: disc !important;
        list-style-position: outside !important;
    }

    .scenario-description ol, 
    .point-detail-content ol {
        list-style-type: decimal !important;
        list-style-position: outside !important;
    }

    /* Force bullet display */
    .scenario-description ul li::before,
    .point-detail-content ul li::before {
        display: none !important; /* Override any potential custom bullets */
    }

    .scenario-description ul li,
    .point-detail-content ul li {
        list-style-type: disc !important;
        display: list-item !important;
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
            <div class="single-card-wrapper">
                <div class="info-card">
                    <div class="card-image-box" data-text="Design">
                        <img src="{{ $data['scenario']->file_path }}">
                    </div>

                    <div class="card-content">
                        <div style="width:100%;">
                            <h3>{{ $data['scenario']->title }}</h3>

                            <div class="scenario-description">
                                {!! $data['scenario']->description !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        
            <!-- Image with Points -->
            <div class="image-container">
                <div class="info-row" style="border-bottom: 2px solid #f0f5ff; margin-bottom: 20px; padding-bottom: 15px;">
                    <div class="info-label">
                        <i class="fas fa-image"></i>
                        Interactive Image
                    </div>
                    <div class="info-value">
                        Click on any red dot to view details
                    </div>
                </div>
                @if($data['scenario']->file_path)
                <div class="image-wrapper">
                    <img id="scenarioImage" src="{{ $data['scenario']->file_path }}" alt="Scenario Image">
                    @foreach($data['scenario']->points as $point)
                    <span class="point"
                        style="left:{{ $point->position_x }}px;top:{{ $point->position_y }}px"
                        data-title="{{ $point->title }}"
                        data-id="{{ $point->id }}"
                        data-description="{{ $point->description }}"
                        data-x="{{ $point->position_x }}"
                        data-y="{{ $point->position_y }}"></span>
                    @endforeach
                </div>
                @else
                <div class="text-center py-5">
                    <i class="fas fa-image fa-4x" style="color: #cbd5e0;"></i>
                    <p class="mt-3 text-muted">No image uploaded for this scenario.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- View Modal for displaying point details -->
<div id="pointViewModal" class="modal view-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>
                <i class="fas fa-map-marker-alt"></i>
                Point Details
            </h3>
        </div>
        <div class="modal-body">
            <div class="point-detail">
                <div class="point-detail-item">
                    <div class="point-detail-label">
                        <i class="fas fa-heading"></i>
                        Title
                    </div>
                    <div class="point-detail-content title-content" id="viewPointTitle">
                        <!-- Title will be inserted here -->
                    </div>
                </div>

                <div class="point-detail-item">
                    <div class="point-detail-label">
                        <i class="fas fa-align-left"></i>
                        Description
                    </div>
                    <div class="point-detail-content" id="viewPointDescription">
                        <!-- Description will be inserted here -->
                    </div>
                </div>
            </div>

            <div style="text-align: center;">
                <button class="close-btn" onclick="closeViewModal()">
                    <i class="fas fa-times"></i>
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

@include('includes.lmsfooterJs')
<script>
    function viewPoint(title, description) {
        // Set title (text content for plain text)
        document.getElementById('viewPointTitle').textContent = title;

        // Set description as HTML to render links, lists, etc.
        let descriptionElement = document.getElementById('viewPointDescription');
        if (description && description.trim() !== '') {
            // Process the description to ensure proper iframe rendering
            descriptionElement.innerHTML = description;
            
            // Auto-embed YouTube links if they're not already in iframes
            autoEmbedYouTubeLinks(descriptionElement);
        } else {
            descriptionElement.innerHTML = 'No description provided.';
        }

        // Show modal
        document.getElementById('pointViewModal').style.display = 'block';
    }

    function autoEmbedYouTubeLinks(container) {
        // Find all anchor tags within the container
        const links = container.querySelectorAll('a');
        
        links.forEach(link => {
            const href = link.href || link.getAttribute('href');
            
            // Check if it's a YouTube link
            if (href && (href.includes('youtube.com') || href.includes('youtu.be'))) {
                // Extract YouTube video ID
                let videoId = '';
                
                if (href.includes('youtube.com/watch')) {
                    const urlParams = new URLSearchParams(new URL(href).search);
                    videoId = urlParams.get('v');
                } else if (href.includes('youtu.be')) {
                    videoId = href.split('youtu.be/')[1].split('?')[0];
                }
                
                if (videoId) {
                    // Create iframe wrapper
                    const wrapper = document.createElement('div');
                    wrapper.className = 'video-wrapper';
                    
                    // Create iframe
                    const iframe = document.createElement('iframe');
                    iframe.src = `https://www.youtube.com/embed/${videoId}`;
                    iframe.title = 'YouTube video player';
                    iframe.frameBorder = '0';
                    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
                    iframe.allowFullscreen = true;
                    
                    wrapper.appendChild(iframe);
                    
                    // Replace the link with the iframe
                    link.parentNode.replaceChild(wrapper, link);
                }
            }
        });
    }

    function closeViewModal() {
        document.getElementById('pointViewModal').style.display = 'none';
    }

    // Make point markers clickable to view details
    $(document).on('click', '.point', function(e) {
        e.stopPropagation();
        let title = $(this).data('title');
        let description = $(this).data('description');

        viewPoint(title, description);
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
        let modal = document.getElementById('pointViewModal');
        if (event.target == modal) {
            closeViewModal();
        }
    }

    // Handle escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeViewModal();
        }
    });

    // Initialize point markers and auto-embed YouTube links in scenario description
    $(document).ready(function() {
        // Ensure all point markers have proper data attributes
        $('.point').each(function() {
            let $this = $(this);
            $this.attr('data-title', $this.data('title'));
            $this.attr('data-description', $this.data('description'));
        });
        
        // Auto-embed YouTube links in scenario description
        const scenarioDescription = document.querySelector('.scenario-description');
        if (scenarioDescription) {
            autoEmbedYouTubeLinks(scenarioDescription);
        }
    });
</script>
@include('includes.footer')
@endsection