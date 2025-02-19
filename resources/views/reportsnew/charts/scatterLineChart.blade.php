@extends('reportsnew.layout.layout')
@section('content')
<div class="container">
        <x-filters :showContries='true' />
        <div class="card">
            <div class="card-body">
                <canvas id="scatter-line-chart" width="800" height="400"></canvas>
            </div>
        </div>
        <input type="button" class="btn btn-primary" value="Download Report" onclick="downloadReport()" />
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

    <script>
    let chart;

    function getData() {
    $.ajax({
        url: '/scatter-line-chart-data',
        method: 'GET',
        dataType: 'json',
        data: {
            'sub_institute_id': $("#sub_institute_id").val(),
            'from': $("#from").val(),
            'to': $("#to").val(),
            'x_field': $("#xField").val(), 
            'y_field' : $("#yField").val(), 
            'report_type': $("#reportType").val(),
            'report_name': window.selectedReportType,
            'data_type' : window.selectedDataType,
            'syear': window.selectedsYear,
            'count_type': window.selectedCountType
        },
        success: function(data) {
            const labels = data.labels;
            const types = data.types;
            const counts = data.counts;
            
            const ctx = document.getElementById('scatter-line-chart').getContext('2d');
            
            if (chart) {
                chart.destroy();
            }
           
            const parsedLabels = $("#yField").val() === '' || $("#yField").val() === null
                                 ? labels.map(label => moment(label, 'DD-MM-YYYY').toDate())
                                 : labels.map(label => moment(label, 'YYYY-MM-DD').toDate());
            let datasets;
           
            if($("#yField").val() === '' || $("#yField").val() === null){
            datasets = types.map((type, index) => ({
                label: `${type}`,
                data: counts[index].map((count, i) => ({
                    x: parsedLabels[i], 
                    y: count
                })),
                borderColor: `rgb(${(index * 50) % 255}, ${(index * 100) % 255}, ${(index * 150) % 255})`,
                backgroundColor: `rgba(${(index * 50) % 255}, ${(index * 100) % 255}, ${(index * 150) % 255}, 0.2)`,
                pointRadius: 5,
                pointBackgroundColor: `rgb(${(index * 50) % 255}, ${(index * 100) % 255}, ${(index * 150) % 255})`,
                borderWidth: 2,
                showLine: true,
            }));
        } else{
            datasets = types.map((type, index) => ({
                label: `${type}`,
                data: counts[index].map((count, i) => ({
                    x: parsedLabels[index], 
                    y: count
                })),
                borderColor: `rgb(${(index * 50) % 255}, ${(index * 100) % 255}, ${(index * 150) % 255})`,
                backgroundColor: `rgba(${(index * 50) % 255}, ${(index * 100) % 255}, ${(index * 150) % 255}, 0.2)`,
                pointRadius: 5,
                pointBackgroundColor: `rgb(${(index * 50) % 255}, ${(index * 100) % 255}, ${(index * 150) % 255})`,
                borderWidth: 2,
                showLine: true,
            }));
        }
        
            chart = new Chart(ctx, {
                type: 'scatter',
                data: {
                    labels: parsedLabels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            type: 'time',
                            time: {
                                unit: 'day',
                                displayFormats: {
                                    day: 'dd MMM yyyy'
                                }
                            },
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Count'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            enabled: true,
                            mode: 'nearest',
                            intersect: false,
                            callbacks: {
                                title: function(tooltipItems) {
                                    return moment(tooltipItems[0].raw.x).format('DD MMM YYYY');
                                },
                                label: function(tooltipItem) {
                                    return `Count: ${tooltipItem.raw.y}`;
                                }
                            }
                        }
                    }
                }
            });
        },
        error: function(error) {
            console.error('Error fetching scatter-line chart data:', error);
        }
    });
}

$(document).ready(function() {
    getData();
});


    function downloadReport() {
        const imgData = chart.toBase64Image();

        const instituteName = $("#sub_institute_id option:selected").text(); 
        const fromDate = $("#from").val(); 
        const toDate = $("#to").val(); 
        const reportType = $("#reportType").val();
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        doc.setFontSize(16);
        doc.text(`${reportType} Report (Scatter-Line Chart)`, 10, 10);
        doc.setFontSize(12);
        doc.text(`Institute: ${instituteName}`, 10, 20);
        doc.text(`Date Range: ${fromDate} to ${toDate}`, 10, 30);

       
        doc.addImage(imgData, 'PNG', 10, 40, 180, 100);

        doc.save('scatterLine-type-chart-report.pdf');
    }
    </script>

@endsection
