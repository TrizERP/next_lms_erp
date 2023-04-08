<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"
"http://www.w3.org/TR/html4/strict.dtd">
<html lang="en">
	<head>
		<title>A very basic Superfish menu example</title>
		<meta http-equiv="content-type" content="text/html;charset=utf-8">
		//link to the CSS files for this menu type 
<link rel="stylesheet" media="screen" href="template/igos/superfish-1.4.8/css/superfish.css" /> 
<link rel="stylesheet" media="screen" href="template/igos/superfish-1.4.8/css/superfish-navbar.css" /> 
 
// link to the JavaScript files (hoverIntent is optional) 
<script src="template/igos/superfish-1.4.8/js/hoverIntent.js"></script> 
<script src="template/igos/superfish-1.4.8/js/superfish.js"></script> 
 
// initialise Superfish 
<script> 
 
    $(document).ready(function(){ 
        $("ul.sf-menu").superfish({ 
            pathClass:  'current' 
        }); 
    }); 
 
</script>
	</head>
	<body>
		<ul class="sf-menu sf-navbar">
			<?php
//echo 'SELECT material_resource_name FROM mst_material_resource_type WHERE material_resource_id="4"';
$physical=$dbs->query('SELECT material_resource_id,material_resource_name FROM mst_material_resource_type');

while($row=$physical->fetch_assoc())
{
?>
	<li class="current"><li class="hasmore"><a href="#"><span><?php echo $row['material_resource_name']; ?></span></a>
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
	</li></li>

<?php }?>
		</ul>
	</body>
</html>
