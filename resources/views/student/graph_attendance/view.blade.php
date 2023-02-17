@include('includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">Graphical Attendance</h4>
            </div>
        </div>
        <div class="card">                           
            <center>
                <div id="container" style="width:70%;"></div>
            </center>                        
        </div>
    </div>
</div>
@include('includes.footerJs')
<script src="https://code.highcharts.com/highcharts.js"></script>
{{-- <script src="https://code.highcharts.com/highcharts-3d.js"></script> --}}
{{-- <script src="https://cdnjs.cloudflare.com/ajax/libs/highcharts/7.2.0/highcharts.js"></script> --}}
<script src="https://code.highcharts.com/modules/sunburst.js"></script>
{{-- <script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script> --}}
<script>
    var data = <?php echo $data['chartData']; ?>;
    console.log(data);
// Splice in transparent for the center circle
Highcharts.getOptions().colors.splice(0, 0, 'transparent');


Highcharts.chart('container', {

    chart: {
        height: '100%'
    },

    title: {
        text: 'Attendance Chart'
    },
    subtitle: {
        text: 'All Data '
    },
    series: [{
        type: "sunburst",
        data: data,
        allowDrillToNode: true,
        cursor: 'pointer',
        dataLabels: {
            format: '{point.name}',
            filter: {
                property: 'innerArcLength',
                operator: '>',
                value: 16
            }
        },
        levels: [{
            level: 1,
            levelIsConstant: false,
            dataLabels: {
                filter: {
                    property: 'outerArcLength',
                    operator: '>',
                    value: 64
                }
            }
        }, {
            level: 2,
            colorByPoint: true
        },
        {
            level: 3,
            colorVariation: {
                key: 'brightness',
                to: -0.5
            }
        }, {
            level: 4,
            colorVariation: {
                key: 'brightness',
                to: 0.5
            }
        }]

    }],
    tooltip: {
        headerFormat: "",
        pointFormat: 'The Count of <b>{point.name}</b> is <b>{point.value}</b>'
    },credits: {
        enabled: false
    },

});
</script>
@include('includes.footer')