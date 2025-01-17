@extends('layout.layout')

@section('content')
    <div class="container">
        <x-filters :showContries='false' />
        <div class="card">
            <div class="card-body">
                <canvas id="horizontalBarChart" width="800" height="400"></canvas>
            </div>
        </div>
    <!-- Download Report Button -->
    <input type="button" class="btn btn-primary" value="Download Report" onclick="downloadReport()" />
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <script>
        let chart;
let labels = [];
let feesCollectData = [];
let feesBreackoffData = [];

// Function to fetch data from the server
function getData() {
    $.ajax({
        url: '/fees-collect-data-hb',
        method: 'GET',
        dataType: 'json',
        data: {
            'sub_institute_id': $("#sub_institute_id").val(), // Get the selected institute
            'from': $("#from").val(),  // Get the start date (YYYY-MM-DD)
            'to': $("#to").val()  // Get the end date (YYYY-MM-DD)
        },
        success: function(data) {
            labels = Object.keys(data.fees_collect);  // Date labels
            feesCollectData = Object.values(data.fees_collect);  // Fees collected data
            feesBreackoffData = Object.values(data.fees_breackoff);  // Fees break-off data

            const ctx = document.getElementById('horizontalBarChart').getContext('2d');
            if (chart) {
                chart.destroy();  // Destroy existing chart if present
            }

            // Create a new bar chart
            chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,  // Dates
                    datasets: [
                        {
                            label: 'Fees Collected',
                            data: feesCollectData,  // Data for Fees Collected
                            backgroundColor: 'rgba(255,99,132)',
                            borderColor: 'rgb(255,99,132)',
                            borderWidth: 1
                        },
                        {
                            label: 'Fees Break-off',
                            data: feesBreackoffData,  // Data for Fees Break-off
                            backgroundColor: 'rgba(54,162,235)',
                            borderColor: 'rgb(54,162,235)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
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


// Function to download the report as a PDF (can use html2canvas + jsPDF as before)
function downloadReport() {
    const imgData = chart.toBase64Image();

                // Get filter values: country, from date, and to date
                const country = $("#sub_institute_id option:selected").text(); // Get the selected country name
                const fromDate = $("#from").val(); // Get the "From" date
                const toDate = $("#to").val(); // Get the "To" date

                // Create a new jsPDF instance
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                // Add title and filter information to the PDF
                doc.setFontSize(16);
                doc.text("Fees Statistics Report", 10, 10);
                doc.setFontSize(12);
                doc.text(`Institute Id: ${country}`, 10, 20);
                doc.text(`Date Range: ${fromDate} to ${toDate}`, 10, 30);

                // Add the chart image to the PDF (adjust position and size as needed)
                doc.addImage(imgData, 'PNG', 10, 40, 180, 100); // Adjust the dimensions as necessary

                // Save the PDF with the name 'report.pdf'
                doc.save('Enhanced-bar-chart-report.pdf');
                    }

$(function() {
    getData();  // Fetch data on page load

});
    </script>
@endsection
