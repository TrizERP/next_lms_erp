@extends('reportsnew.layout.layout')

@section('content')
<div class="container">
        <x-filters :showContries='true'/>
        <div class="card">
            <div class="card-body">
                <canvas id="doughnutChart" width="800" height="400"></canvas>
            </div>
        </div>
        <!-- Download Report Button -->
        <input type="button" class="btn btn-primary" value="Download Report" onclick="downloadReport()" />
    </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <!-- Bar Chart Script -->
    <script>
    let chart;

    function getData() {
    $.ajax({
        url: '/doughnut-chart-data',
        method: 'GET',
        dataType: 'json',
        data: {
            'sub_institute_id': $("#sub_institute_id").val(),
            'from': $("#from").val(),
            'to': $("#to").val(),
            'x_field': $("#xField").val(), 
            'y_field' : $("#yField").val(), 
            'report_type': $("#reportType").val(),
            'data_type' : window.selectedDataType,
            'syear': window.selectedsYear,
            'report_name': window.selectedReportType,
            'count_type': window.selectedCountType
        },
        success: function(data) {
            const ctx = document.getElementById('doughnutChart').getContext('2d');
            
            if (chart) {
                chart.destroy();
            }

            const labels = data.map(item => item.label);
            const chartData = data.map(item => item.count);

            chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: chartData,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',  
                            'rgba(54, 162, 235, 0.7)',  
                            'rgba(255, 206, 86, 0.7)',  
                            'rgba(75, 192, 192, 0.7)',  
                            'rgba(153, 102, 255, 0.7)',  
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(tooltipItem) {
                                    return tooltipItem.label + ': ' + tooltipItem.raw.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        },
        error: function(error) {
            console.error('Error fetching doughnut chart data:', error);
        }
    });
}

$(document).ready(function() {
    getData();
});


    function downloadReport() {
        const imgData = window.chart.toBase64Image();

        const country = $("#sub_institute_id option:selected").text();
        const fromDate = $("#from").val();
        const toDate = $("#to").val();
        const reportType = $("#reportType").val();
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        doc.setFontSize(16);
        doc.text(`${reportType} Report (Doughnut Chart)`, 10, 10);
        doc.setFontSize(12);
        doc.text(`Institute: ${country}`, 10, 20);
        doc.text(`Date Range: ${fromDate} to ${toDate}`, 10, 30);

        // Add the chart image to the PDF
        doc.addImage(imgData, 'PNG', 10, 40, 180, 100);

        doc.save('doughnut-chart-report.pdf');
    }
</script>

@endsection
