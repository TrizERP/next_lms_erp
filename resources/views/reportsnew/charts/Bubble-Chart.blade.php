@extends('layout.layout')
@section('content')
    <div class="container">
        <x-filters :showContries='true'/>
        <div class="card">
            <div class="card-body">
                <canvas id="bubbleChart" width="800" height="400"></canvas>
            </div>
        </div>
        <!-- Download Report Button -->
        <input type="button" class="btn btn-primary" value="Download Report" onclick="downloadReport()" />
    </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
        let chart;
let labels = [];
let feesCollectData = [];
let feesBreackoffData = [];
let bubbleSizes = [];

// Function to fetch data from the server
function getData() {
    $.ajax({
        url: '/fees-collect-vs-breackoff',
        method: 'GET',
        dataType: 'json',
        data: {
            'sub_institute_id': $("#sub_institute_id").val(),
            'from': $("#from").val(), 
            'to': $("#to").val() 
        },
        success: function(data) {
            labels = data.dates; 
            feesCollectData = data.fees_collect;
            feesBreackoffData = data.fees_breackoff;
            bubbleSizes = data.bubbleSizes;

            const ctx = document.getElementById('bubbleChart').getContext('2d');
            if (chart) {
                chart.destroy(); 
            }

           
            chart = new Chart(ctx, {
                type: 'bubble',
                data: {
                    labels: labels, 
                    datasets: [
                        {
                            label: 'Fees Collected',
                            data: labels.map((label, index) => ({
                                x: index, 
                                y: feesCollectData[index], 
                                r: bubbleSizes[index] 
                            })),
                            backgroundColor: 'rgba(255,99,132)',  
                        },
                        {
                            label: 'Fees Break-off',
                            data: labels.map((label, index) => ({
                                x: index, 
                                y: feesBreackoffData[index], 
                                r: bubbleSizes[index]
                            })),
                            backgroundColor: 'rgba(54,162,235)', 
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { beginAtZero: true },
                        y: { beginAtZero: true }
                    }
                }
            });
        },
        error: function(error) {
            console.error(error);
        }
    });
}
function downloadReport() {
    const imgData = chart.toBase64Image();

    const country = $("#sub_institute_id option:selected").text();  
    const fromDate = $("#from").val();  
    const toDate = $("#to").val();  

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    doc.setFontSize(16);
    doc.text("Fees Report (Bubble Chart)", 10, 10);
    doc.setFontSize(12);
    doc.text(`Institute: ${country}`, 10, 20);
    doc.text(`Date Range: ${fromDate} to ${toDate}`, 10, 30);

   
    doc.addImage(imgData, 'PNG', 10, 40, 180, 100);

    doc.save('bubble-chart-report.pdf');
}

$(function() {
    getData();
});
    </script>
@endsection
