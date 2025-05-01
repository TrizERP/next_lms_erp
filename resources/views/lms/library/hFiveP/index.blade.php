@extends('layout')
@section('container')
<style>
        .h5p-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .h5p-btn {
            padding: 10px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .h5p-btn:hover {
            background-color: #45a049;
        }
        #h5p-iframe {
            width: 100%;
            height: 1000px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .content-title {
            margin: 20px 0 10px;
            color: #333;
        }
        .h5p-actions{
            display:none !important;
        }
    </style> 
    <div id="page-wrapper">
        <div class="container-fluid">
            <div class="row bg-title">
                <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                    <h4 class="page-title">H5P Content</h4>
                </div>
            </div>
            <div class="card">
                <div class="h5p-buttons">
                    <button class="h5p-btn" data-type="quiz">Load Quiz</button>
                    <button class="h5p-btn" data-type="presentation">Load Presentation</button>
                    <button class="h5p-btn" data-type="fillInBlanks">Word</button>
                </div>
                
                <h3 class="content-title" id="content-title">Quiz Content</h3>
                <div id="h5p-iframe-container">
                    <iframe id="h5p-iframe" frameborder="0" allowfullscreen></iframe>
                </div>

            </div>
    
    </div>
</div>
   
@include('includes.footerJs')

 <!-- <script>
        $(document).ready(function() {
            // H5P content URLs - replace these with your actual H5P embed URLs
            const h5pContent = {
                quiz: {
                    url: "https://h5p.org/h5p/embed/6725",
                    title: "Arithmetic Quiz"
                },
                presentation: {
                    url: "https://h5p.org/h5p/embed/612",
                    title: "Course Presentation"
                },
                fillInBlanks: {
                    url: "https://h5p.org/h5p/embed/554805",
                    title: "Find the words"
                }
            };
            
            // Function to load H5P content
            function loadH5P(contentType) {
                if(h5pContent[contentType]) {
                    $('#h5p-iframe').attr('src', h5pContent[contentType].url);
                    $('#content-title').text(h5pContent[contentType].title);
                }
            }
            
            // Set click handlers for all buttons
            $('.h5p-btn').on('click', function() {
                const contentType = $(this).data('type');
                loadH5P(contentType);
            });
            
            // Initialize with quiz content
            loadH5P('quiz');
            
            // Optional: Handle iframe resizing for better responsiveness
            window.addEventListener('message', function(event) {
                if(event.data.context && event.data.context === 'h5p' && event.data.action === 'resize') {
                    $('#h5p-iframe').height(event.data.height);
                }
            });
        });
    </script> -->
    <script>
        $(document).ready(function() {
            // H5P content URLs - replace these with your actual H5P embed URLs
            const h5pContent = {
                quiz: {
                    url: "https://h5p.org/h5p/embed/6725",
                    title: "Arithmetic Quiz"
                },
                presentation: {
                    url: "https://h5p.org/h5p/embed/612",
                    title: "Course Presentation"
                },
                fillInBlanks: {
                    url: "https://h5p.org/h5p/embed/554805",
                    title: "Find the words"
                }
            };
            
            // Function to load H5P content
            function loadH5P(contentType) {
                if(h5pContent[contentType]) {
                    $('#h5p-iframe').attr('src', h5pContent[contentType].url);
                    $('#content-title').text(h5pContent[contentType].title);
                }
            }
            
            // Set click handlers for all buttons
            $('.h5p-btn').on('click', function() {
                const contentType = $(this).data('type');
                loadH5P(contentType);
            });
            
            // Initialize with quiz content
            loadH5P('quiz');
            
            // Handle iframe resizing for better responsiveness
            window.addEventListener('message', function(event) {
                if (event.origin.includes('h5p.org') && event.data.context === 'h5p' && event.data.action === 'resize') {
                    $('#h5p-iframe').height(event.data.height);
                }
            });
        });
    </script>
@include('includes.footer')
@endsection
