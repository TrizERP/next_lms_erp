<?php 
// echo "<pre>";
// print_r($garphdat);
// die;
$cat = [];
$higt = [];
$avg = [];
$marks = [];
foreach ($garphdat as $key => $value) {
    $cat[] =  "'".$key."'";
    $higt[] = $garphdat[$key]['higt'];
    $avg[] =  $garphdat[$key]['final_avge'];
    $marks[] = $garphdat[$key]['marks'];
}


?>
<style type="text/css">
.highcharts-figure,
.highcharts-data-table table {
  min-width: 310px;
  max-width: 800px;
  margin: 1em auto;
}

#container {
  height: 400px;
}

.highcharts-data-table table {
  font-family: Verdana, sans-serif;
  border-collapse: collapse;
  border: 1px solid #ebebeb;
  margin: 10px auto;
  text-align: center;
  width: 100%;
  max-width: 500px;
}

.highcharts-data-table caption {
  padding: 1em 0;
  font-size: 1.2em;
  color: #555;
}

.highcharts-data-table th {
  font-weight: 600;
  padding: 0.5em;
}

.highcharts-data-table td,
.highcharts-data-table th,
.highcharts-data-table caption {
  padding: 0.5em;
}

.highcharts-data-table thead tr,
.highcharts-data-table tr:nth-child(even) {
  background: #f8f8f8;
}

.highcharts-data-table tr:hover {
  background: #f1f7ff;
}
</style>



                <div class="" style="width:100%; height:250px;">
                    <!-- <h3 class="box-title" style="font-size: 10px;">Student Attendance</h3>                   -->
                    <div id="container_<?php echo $gc; ?>" style=" height: 250px;"></div>
                </div>
          

<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/highcharts-more.js"></script>
<script src="https://code.highcharts.com/modules/sunburst.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>
<script type="text/javascript">
   
    var nam = 'container_'+<?php echo($gc); ?>;
    Highcharts.chart(nam, {
    chart: {
    type: 'column'
  },
  title: {
    text: 'Student Marks Graph'
  },  
  xAxis: {
    categories:<?php echo '['.implode(',', $cat).']'; ?>,
    crosshair: true
  },
  yAxis: {
    title: {
      useHTML: true,
      text: 'Marks'
    },
    min:0,
    max:100,
  },   
  series: [{
    name: 'Highest',
    data: <?php echo '['.implode(',', $higt).']'; ?>

  }, {
    name: 'Avarage',
    data: <?php echo '['.implode(',', $avg).']'; ?>

  }, {
    name: 'Obtain',
    data: <?php echo '['.implode(',', $marks).']'; ?>

  }]
});


</script>