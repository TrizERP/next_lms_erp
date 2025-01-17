@extends('layout.layout')

@section('content')
    <div class="container">
        <x-filters :showContries='true' />

        <div class="card">
            <div class="card-body">
                {{-- This Canvas is for the Chart --}}
                <canvas id="realTimeChart" width="800" height="400"></canvas>
            </div>
        </div>
        <input type="button" class="btn btn-primary" value="Download Report" onclick="downloadReport()" />
    </div>
    @vite('resources/js/app.js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <script>
        let chart;
        let labels;
        let datas;
        let points;
        function getData() {
            $.ajax({
                url: '/real-time-chart-data',
                method: 'GET',
                dataType: 'json',
                data: {
                        'sub_institute_id': $("#sub_institute_id").val(), 
                        'from': $("#from").val(),  
                        'to': $("#to").val()  
                    },
                success: function(data) {
                    labels = data.labels;  
                    datas = data.fees_collect;  

                    const ctx = document.getElementById('realTimeChart').getContext('2d');
                    console.log(data); 

                    if (chart) {
                        chart.destroy();
                    }

                    chart = new Chart(ctx, {
                        type: 'line',  
                        data: {
                            labels: labels,  
                            datasets: [{
                                label: `Fees Collected for Institute ${data.sub_institute_id}`,
                                data: datas,  
                                pointRadius: 5,
                                backgroundColor: 'rgba(255,99,132, 1.0)', 
                                borderColor: 'rgb(255,99,132)', 
                                borderWidth: 2,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Date'
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Amount Collected'
                                    }
                                }
                            }
                        }
                    });
                },
                error: function(error) {
                    console.log(error);  
                }
            });
        }

        $(document).ready(function() {
            getData();
        });
        setTimeout(() => {
            window.Echo.channel('addedData')
                .listen('.App\\Events\\addedDataEvent', (e) => {
                    console.log(e);

                    chart.data.labels.push(e.label);
                    chart.data.datasets[0].data.push(e.data);

                    chart.data.labels.shift();
                    chart.data.datasets[0].data.shift();

                    chart.update();
                })
        }, 1000);
        function downloadReport() {
            const imgData = chart.toBase64Image();

            const institute = $("#sub_institute_id option:selected").text();
            const fromDate = $("#from").val();
            const toDate = $("#to").val();

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.setFontSize(16);
            doc.text("Fees Collected Report", 10, 10);
            doc.setFontSize(12);
            doc.text(`Institute: ${institute}`, 10, 20);
            doc.text(`Date Range: ${fromDate} to ${toDate}`, 10, 30);

            doc.addImage(imgData, 'PNG', 10, 40, 180, 100);

            doc.save('Fees-Collected-Report.pdf');
        }
    </script>
@endsection
