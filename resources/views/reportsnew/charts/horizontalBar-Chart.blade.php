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


function getData() {
    $.ajax({
        url: '/fees-collect-data-hb',
        method: 'GET',
        dataType: 'json',
        data: {
            'sub_institute_id': $("#sub_institute_id").val(),
            'from': $("#from").val(),
            'to': $("#to").val() 
        },
        success: function(data) {
            labels = Object.keys(data.fees_collect); 
            feesCollectData = Object.values(data.fees_collect);  
            feesBreackoffData = Object.values(data.fees_breackoff);  

            const ctx = document.getElementById('horizontalBarChart').getContext('2d');
            if (chart) {
                chart.destroy(); 
            }

  
            chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,  
                    datasets: [
                        {
                            label: 'Fees Collected',
                            data: feesCollectData,  
                            backgroundColor: 'rgba(255,99,132)',
                            borderColor: 'rgb(255,99,132)',
                            borderWidth: 1
                        },
                        {
                            label: 'Fees Break-off',
                            data: feesBreackoffData,  
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

              const country = $("#sub_institute_id option:selected").text(); 
                const fromDate = $("#from").val();
                const toDate = $("#to").val();

                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                doc.setFontSize(16);
                doc.text("Fees Statistics Report", 10, 10);
                doc.setFontSize(12);
                doc.text(`Institute Id: ${country}`, 10, 20);
                doc.text(`Date Range: ${fromDate} to ${toDate}`, 10, 30);

                doc.addImage(imgData, 'PNG', 10, 40, 180, 100); 

                doc.save('Enhanced-bar-chart-report.pdf');
                    }

$(function() {
    getData();  
});
    </script>
@endsection
