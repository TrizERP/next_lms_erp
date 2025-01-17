@extends('layout.layout')

@section('content')
    <div class="container">
        <x-filters :showContries='true' />
        <div class="card">
            <div class="card-body">
                <canvas id="feesPolarAreaChart" width="800" height="400"></canvas>
            </div>
        </div>
    <!-- Download Report Button -->
    <input type="button" class="btn btn-primary" value="Download Report" onclick="downloadReport()" />
    </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <!-- Polar Area Chart Script -->
    <script>
        let chart;

        // Fetch the Fees Collect data
        function getData() {
            $.ajax({
                url: '/polar-area-chart-data',
                method: 'GET',
                dataType: 'json',
                data: {
                    'sub_institute_id': $("#sub_institute_id").val(),
                    'from': $("#from").val(),
                    'to': $("#to").val(),
                },
                success: function(data) {
                    const labels = data.labels;
                    const feesData = data.fees_collect;
                    console.log(data);
                    const ctx = document.getElementById('feesPolarAreaChart').getContext('2d');

                    if (chart) {
                        chart.destroy();
                    }
                    chart = new Chart(ctx, {
                        type: 'polarArea',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: feesData,
                                backgroundColor: ['rgba(255, 99, 132, 0.7)', 'rgba(75, 192, 192, 0.7)', 'rgba(54, 162, 235, 0.7)'],
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                        }
                    });
                },
                error: function(error) {
                    console.error('Error fetching polar area chart data:', error);
                }
            });
        }

        // Trigger data fetching on page load
        $(document).ready(function() {
            getData();  // Fetch data on page load
        });
        // Function to download the report
        function downloadReport() {
            const imgData = chart.toBase64Image(); // Capture the chart as an image
            const subInstituteId = $("#sub_institute_id option:selected").text(); // Get the institute name
            const fromDate = $("#from").val(); // Get the "From" date
            const toDate = $("#to").val(); // Get the "To" date

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            // Add title and filter information to the PDF
            doc.setFontSize(16);
            doc.text("Fees Collection Date-wise Report", 10, 10);
            doc.setFontSize(12);
            doc.text("Institute: " + subInstituteId, 10, 20);
            doc.text("Date Range: " + fromDate + " to " + toDate, 10, 30);

            // Add the chart image to the PDF
            doc.addImage(imgData, 'PNG', 10, 40, 180, 100); 

            // Save the PDF with a specific name
            doc.save('Fees-Collection-Report.pdf');
        }
    </script>
@endsection
