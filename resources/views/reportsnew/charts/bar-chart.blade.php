@extends('reportsnew.layout.layout')

@section('content')
    <div class="container">
        <x-filters :showContries='true' />

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
        
        
        function getData() {
            $.ajax({
                url: '/fees-collect-data-b',  
                method: 'GET',
                dataType: 'json',
                data: {
                    'sub_institute_id': $("#sub_institute_id").val(), 
                    'from': $("#from").val(),  
                    'to': $("#to").val(), 
                    'field': $("#field").val()
                },
                success: function(data) {
                    console.log(data);
                    const labels = data.map(item => item.notification_type);  
                    const counts = data.map(item => item.count);  

                    
                    const ctx = document.getElementById('barChart').getContext('2d');
                    if (chart) {
                        chart.destroy();
                    }

                   
                    chart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,  
                            datasets: [{
                                label: 'Notification Count',
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

     
        function downloadReport() {
            const imgData = chart.toBase64Image();

            const country = $("#sub_institute_id option:selected").text();
            const fromDate = $("#from").val();
            const toDate = $("#to").val();

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();

            doc.setFontSize(16);
            doc.text("Notification Report (Bar Chart)", 10, 10);
            doc.setFontSize(12);
            doc.text(`Institute: ${country}`, 10, 20);
            doc.text(`Date Range: ${fromDate} to ${toDate}`, 10, 30);

          
            doc.addImage(imgData, 'PNG', 10, 40, 180, 100);

            doc.save('notification-chart-report.pdf');
        }

       
        $(function() {
            getData(); 
        });
    </script>
@endsection
