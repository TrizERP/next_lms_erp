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
            'sub_institute_id': $("#sub_institute_id").val(), // Get the selected institute
            'from': $("#from").val(),  // Get the start date (YYYY-MM-DD)
            'to': $("#to").val()  // Get the end date (YYYY-MM-DD)
        },
        success: function(data) {
            labels = data.dates;  // Dates
            feesCollectData = data.fees_collect;  // Fees collected data
            feesBreackoffData = data.fees_breackoff;  // Fees break-off data
            bubbleSizes = data.bubbleSizes;  // Bubble sizes based on amounts

            const ctx = document.getElementById('bubbleChart').getContext('2d');
            if (chart) {
                chart.destroy();  // Destroy existing chart if present
            }

            // Create a new bubble chart
            chart = new Chart(ctx, {
                type: 'bubble',
                data: {
                    labels: labels,  // Dates
                    datasets: [
                        {
                            label: 'Fees Collected',
                            data: labels.map((label, index) => ({
                                x: index,  // Use index for x-axis (could be a time scale instead)
                                y: feesCollectData[index],  // Fees collected amount
                                r: bubbleSizes[index]  // Bubble size
                            })),
                            backgroundColor: 'rgba(255,99,132)',  // Color for fees collected bubbles
                        },
                        {
                            label: 'Fees Break-off',
                            data: labels.map((label, index) => ({
                                x: index,  // Use index for x-axis
                                y: feesBreackoffData[index],  // Fees break-off amount
                                r: bubbleSizes[index]  // Bubble size
                            })),
                            backgroundColor: 'rgba(54,162,235)',  // Color for fees break-off bubbles
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

    const country = $("#sub_institute_id option:selected").text();  // Get selected country/institute name
    const fromDate = $("#from").val();  // Get "From" date
    const toDate = $("#to").val();  // Get "To" date

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    doc.setFontSize(16);
    doc.text("Fees Report (Bubble Chart)", 10, 10);
    doc.setFontSize(12);
    doc.text(`Institute: ${country}`, 10, 20);
    doc.text(`Date Range: ${fromDate} to ${toDate}`, 10, 30);

    // Add the chart image to the PDF
    doc.addImage(imgData, 'PNG', 10, 40, 180, 100);

    doc.save('bubble-chart-report.pdf');
}
// Fetch data when the page is loaded
$(function() {
    getData();
});
    </script>
@endsection
