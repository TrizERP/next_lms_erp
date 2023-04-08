<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html><?php //echo "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']; 
?>

	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<title>Highcharts Example</title>
		
		
		<!-- 1. Add these JavaScript inclusions in the head of your page -->
		<!--<script type="text/javascript" src="jquery.min.js"></script>-->
		<!--<script type="text/javascript" src="highcharts.js"></script>-->
                 <script>  
                <?php 
                include('jquery.min.js');
                 ?>
                 </script>
                 <script>  
                 <?php    
                include('highcharts.js'); 
                ?>
                <?php
                include('gray.js'); 
                ?>
              </script>
		<!-- 1a) Optional: add a theme file -->
		<!--
			<script type="text/javascript" src="../js/themes/gray.js"></script>
		-->
		
		<!-- 1b) Optional: the exporting module -->
		<!--<script type="text/javascript" src="/js/modules/exporting.js"></script>-->
		
		
		<!-- 2. Add the JavaScript to initialize the chart on document ready -->
		<script>
		
			var chart;
			$(document).ready(function() {
				chart = new Highcharts.Chart({
					chart: {
						renderTo: 'container',
						defaultSeriesType: 'column',
						margin: [ 50, 50, 200, 80]
					},
					title: {
						text: 'Popular 10 titles'
					},
					xAxis: {
						categories: [
							<?php echo $_GET['set_title']; ?>
						],
						labels: {
							rotation: -45,
							align: 'right',
							style: {
								 font: 'normal 13px Verdana, sans-serif'
							}
						}
					},
					yAxis: {
						min: 0,
						title: {
							text: 'Issue Numbers'
						}
					},
					legend: {
						enabled: false
					},
					tooltip: {
						formatter: function() {
							return '<b>'+ this.x +'</b><br/>';
						}
					},
				        series: [{
						name: 'Population',
						data: [<?php echo $_GET['set_value']; ?>],
						dataLabels: {
							enabled: true,
							rotation: -90,
							color: '#00000',
							align: 'right',
							x: -3,
							y: 10,
							formatter: function() {
								return this.y;
							},
							style: {
								font: 'normal 13px Verdana, sans-serif'
							}
						}			
					}]
				});
				
				
			});
				
		</script>
		
	</head>
	<body>
		
		<!-- 3. Add the container -->
		<div id="container" style="width: 580px; height: 400px; margin: 0 auto"></div>
		
				
	</body>
</html>
