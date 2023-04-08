<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Fancy drop down menu</title>
	<link rel="stylesheet" href="template/igos/fancydropdown.css">
</head>
<body>

<div id="menu">
<ul class="tabs">
	<!--<li><h4><a href="#">In the blog &raquo;</a></h4></li>-->
<?php
//echo 'SELECT material_resource_name FROM mst_material_resource_type WHERE material_resource_id="4"';
$physical=$dbs->query('SELECT material_resource_id,material_resource_name FROM mst_material_resource_type');

while($row=$physical->fetch_assoc())
{
?>
	<li class="hasmore"><a href="#"><span><?php echo $row['material_resource_name']; ?></span></a>
<?php
         $material='select g.gmd_id AS gmd_id,g.gmd_name AS gmd_name,mr.material_resource_name from mst_gmd AS g 
		    LEFT JOIN mst_material_resource_type AS mr ON mr.material_resource_id=g.gmd_code
	            where g.gmd_code='.$row['material_resource_id'].'';
         $material=$dbs->query($material);
	 $m='';
?>
	<ul class="dropdown">
<?php
	 while($row=$material->fetch_assoc())
	 { 
		
			echo "<li><a href=index.php?gmd_search=".urlencode($row['gmd_name'])."&gmd_id=".$row['gmd_id']."&resource=".urlencode($row['material_resource_name']).">".$row['gmd_name']."</a></li>";
			
		
	}?>
	</ul>
	</li>

<?php }?>
	
	
</ul>
</div>

<script type="text/javascript" src="template/igos/fancydropdown.js"></script>
</body>
</html>
