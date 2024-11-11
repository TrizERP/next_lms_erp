@extends('layout')
@section('container')
<!-- Calendly link widget begin -->
<link href="https://assets.calendly.com/assets/external/widget.css" rel="stylesheet">
<script src="https://assets.calendly.com/assets/external/widget.js" type="text/javascript" async></script>
<!-- Calendly link widget end -->   
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Counseling Report</h4>
            </div>
        </div>
        <div class="container text-center mt-5">
            <a href="" onclick="Calendly.initPopupWidget({url: 'https://calendly.com/scholarclone/30min'});return false;" class="btn btn-primary btn-lg" role="button">Book an Appointment</a>
        </div>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 0;
            background-color: #f9f9f9;
        }
        .container {
            width: 80%;
            margin: 20px auto;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1, h2, h3 {
            color: #2c3e50;
        }
        .header {
            text-align: center;
            padding: 10px 0;
            border-bottom: 2px solid #3498db;
        }
        .header img {
            width: 100px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            color: #3498db;
            margin-bottom: 10px;
            font-size: 20px;
            border-left: 5px solid #3498db;
            padding-left: 10px;
        }
        .content {
            line-height: 1.6;
            font-size: 16px;
            color: #34495e;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .table th, .table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: center;
        }
        .table th {
            background-color: #3498db;
            color: white;
        }
        h2 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #3498db;
            color: white;
            font-size: 18px;
        }
        td {
            background-color: #f4f4f4;
            color: #34495e;
        }
        .related-fields {
            background-color: #ecf0f1;
            font-style: italic;
        }
        /* Graphical Enhancement */
        .bar {
            height: 25px;
            background-color: #3498db;
            width: 0%;
            transition: width 1s;
        }
        .bar-wrapper {
            background-color: #ecf0f1;
            border-radius: 10px;
            overflow: hidden;
        }
        .bar-75 { width: 75%; }
        .bar-80 { width: 80%; }
        .bar-90 { width: 90%; }
                body {
            font-family: 'Arial', sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
            color: #34495e;
        }
        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
        }
        h2 {
            text-align: center;
            color: #2c3e50;
            font-size: 28px;
        }
        .section-title {
            font-size: 22px;
            color: #3498db;
            margin: 20px 0 10px;
            position: relative;
        }
        .section-title::before {
            content: '';
            position: absolute;
            left: 0;
            bottom: -5px;
            width: 50px;
            height: 3px;
            background-color: #3498db;
        }
        .recommendations, .action-plan {
            margin: 20px 0;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        ul li {
            margin: 10px 0;
            padding: 10px;
            background-color: #ecf0f1;
            border-radius: 5px;
            font-size: 18px;
            position: relative;
            animation: fadeIn 1.5s ease-in-out;
        }
        ul li::before {
            content: '✔';
            position: absolute;
            left: -5px;
            top: 10px;
            color: #3498db;
            font-size: 20px;
        }
        .chart-container {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
        }
        .chart {
            width: 30%;
            text-align: center;
        }
        .chart img {
            width: 100%;
            height: auto;
        }
        /* Styling for animated bar chart */
        .bar {
            height: 30px;
            background-color: #3498db;
            border-radius: 5px;
            width: 0%;
            transition: width 2s;
        }
        .bar-wrapper {
            background-color: #ecf0f1;
            border-radius: 5px;
            width: 100%;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .percentage {
            font-size: 14px;
            text-align: center;
        }
        /* GIF styling */
        .gif-container {
            text-align: center;
            margin-top: 30px;
        }
        .gif-container img {
            width: 300px;
            height: auto;
            border-radius: 10px;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
    <div class="container">
        <div class="header">
            <h1>Career Counseling Report</h1>
            <p>Guiding you towards a successful career</p>
        </div>
     
        <div class="section">
            <h2 class="section-title">Personal Information</h2>
            <div class="content">
                <p><strong>Name:</strong> Kalpesh Sheth</p>
                <p><strong>Grade:</strong> 6 - C</p>
                <p><strong>Institute:</strong> ScholarClone International</p>
            </div>
        </div>

        <div class="container">
        <h2>Career Interests and Related Fields</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Career Field</th>
                    <th>Interest Level</th>
                    <th>Related Fields</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Engineering</td>
                    <td>
                        <div class="bar-wrapper">
                            <div class="bar bar-80"></div>
                        </div>
                        <small>80%</small>
                    </td>
                    <td class="related-fields">
                        - Civil Engineering<br>
                        - Computer Science Engineering<br>
                        - Environmental Engineering
                    </td>
                </tr>
                <tr>
                    <td>Business and Management</td>
                    <td>
                        <div class="bar-wrapper">
                            <div class="bar bar-75"></div>
                        </div>
                        <small>75%</small>
                    </td>
                    <td class="related-fields">
                        - Marketing<br>
                        - Human Resources<br>
                        - Financial Management
                    </td>
                </tr>
                <tr>
                    <td>Creative Arts and Design</td>
                    <td>
                        <div class="bar-wrapper">
                            <div class="bar bar-90"></div>
                        </div>
                        <small>90%</small>
                    </td>
                    <td class="related-fields">
                        - Fashion Design<br>
                        - Film and Media<br>
                        - Interior Design
                    </td>
                </tr>
            </tbody>
        </table>

    <script>
        // Simple script to animate the bar charts when the page loads
        document.addEventListener("DOMContentLoaded", function() {
            var bars = document.querySelectorAll('.bar');
            bars.forEach(function(bar) {
                setTimeout(function() {
                    bar.style.width = bar.classList.contains('bar-75') ? '75%' : 
                                      bar.classList.contains('bar-80') ? '80%' : '90%';
                }, 500);
            });
        });
    </script>
        </div>
<p>&nbsp;</p>
        <div class="section">
            <h2 class="section-title">Skill Assessment</h2>
            <div class="content">
                <table class="table">
                    <tr>
                        <th>Skill</th>
                        <th>Score</th>
                    </tr>
                    <tr>
                        <td>Communication</td>
                        <td>85%</td>
                    </tr>
                    <tr>
                        <td>Problem Solving</td>
                        <td>90%</td>
                    </tr>
                    <tr>
                        <td>Leadership</td>
                        <td>75%</td>
                    </tr>
                </table>
            </div>
        </div>
<p>&nbsp;</p>
        <div class="section">
            <h2 class="section-title">Career Recommendations</h2>
            <div class="content">
                <p>Based on the assessment, the following career paths are recommended:</p>
                <ul>
                    <li>Engineering: Focus on Mechanical or Civil Engineering.</li>
                    <li>Business Management: Consider pursuing a career in business administration.</li>
                    <li>Creative Arts: If inclined towards arts, explore careers in design and media.</li>
                </ul>
            </div>
<p>&nbsp;</p>
            <div class="section">
                <h2 class="section-title">Action Plan</h2>
                <div class="content">
                    <p>To achieve your career goals, follow this action plan:</p>
                    <ul>
                        <li>Enroll in extracurricular activities related to your chosen field.</li>
                        <li>Complete relevant certifications or online courses.</li>
                        <li>Gain practical experience through internships or part-time jobs.</li>
                        <li>Maintain a high GPA and participate in career counseling sessions.</li>
                    </ul>
                </div>
            </div>

            <div class="gif-container">
                <img src="https://media.giphy.com/media/3o7aCTfyhYawdOXcFW/giphy.gif" alt="Motivational GIF">
                <p>Stay motivated and work towards your dreams!</p>
            </div>
        </div>
    </div>
</div>
@include('includes.footerJs')
@include('includes.footer')
@endsection