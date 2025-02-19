@extends('reportsnew.layout.layout')

@section('content')
<div class="container">
        <x-filters :showContries='true' />
        <!-- 'sub_institute_id': $("#sub_institute_id").val(), -->
        <div class="card">
            <div class="card-body">
                {{-- This Canvas is for the Bar Chart --}}
                <canvas id="barChart" width="800" height="400"></canvas>
            </div>
        </div>
        <!-- Download Report Button -->
        <input type="button" class="btn btn-primary" value="Download Report" onclick="downloadReport()" />
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <script>
        $(".bar_Menu").addClass('active');
        let chart;
        
        // Function to fetch data from the server 
        function getData() {
    $.ajax({
        url: '/fees-collect-data-b',  
        method: 'GET',
        dataType: 'json',
        data: {  
            'from': $("#from").val(),  
            'to': $("#to").val(),  
            'x_field': $("#xField").val(), 
            'y_field' : $("#yField").val(), 
            'report_type': $("#reportType").val(), 
            'report_name' : window.selectedReportType,
            'data_type' : window.selectedDataType,
            'syear': window.selectedsYear,
            'count_type': window.selectedCountType
        },
        success: function(data) {

            if (!data || data.length === 0) {
                console.warn("No data received.");
                alert("No data available for the selected filters.");
                return;
            }

            // Extract dynamic labels and counts
            const labels = data.map(item => item.label);  
            const counts = data.map(item => item.count);  

            // Destroy the previous chart if it exists
            const ctx = document.getElementById('barChart').getContext('2d');
            if (window.chart) {
                window.chart.destroy();
            }

            
            const reportType = $("#reportType").val();
            const field = $("#field").val();

          
            window.chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,  
                    datasets: [{
                        label: `${reportType} (${field})`, 
                        data: counts, 
                        backgroundColor: 'rgba(54, 162, 235, 1)',  
                        borderColor: 'rgb(54, 162, 235)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },
        error: function(error) {
            console.error('Error fetching data: ', error);
        }
    });
}


        // Download Report Functionality
        function downloadReport() {
            const imgData = window.chart.toBase64Image();

            const country = $("#sub_institute_id option:selected").text();  
            const fromDate = $("#from").val();  
            const toDate = $("#to").val();  
            const reportType = $("#reportType").val();
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            doc.setFontSize(16);
            doc.text(`${reportType} Report (Bar Chart)`, 10, 10);
            doc.setFontSize(12);
            doc.text(`Institute: ${country}`, 10, 20);
            doc.text(`Date Range: ${fromDate} to ${toDate}`, 10, 30);

            doc.addImage(imgData, 'PNG', 10, 40, 180, 100);

            doc.save('bar-chart-report.pdf');
        }

        $(function() {
            getData(); 
        });
    </script>
@endsection