<!DOCTYPE HTML PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html><head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">



	<title>jQuery Accordion Example</title>
	
	<style type="text/css">
		/* A few IE bug fixes */
		* { margin: 0; padding: 0; }
		* html ul ul li a { height: 100%; }
		* html ul li a { height: 100%; }
		* html ul ul li { margin-bottom: -1px; }
		
		body { padding-left: 10em; font-family: Arial, Helvetica, sans-serif; }
		#theMenu { width: 200px; height: 350px; margin: 30px 0; padding: 0; }
		
		/* Some list and link styling */
		ul li { width: 200px; }
		ul ul li { border-left: 25px solid #69c; padding: 0; width: 175px; margin-bottom: 0; }
		ul ul li a { display:block; color: #000; padding: 3px 6px; font-size: small; }
		ul ul li a:hover { display:block; color: #369; background-color: #eee; padding: 3px 8px; font-size: small; }
		/* For the xtra menu */
		ul ul ul li { border-left: none; border-bottom: 1px solid #eee; padding: 0; width: 175px; margin-bottom: 0; }
		ul ul ul li a { display:block; color: #000; padding: 3px 6px; font-size: small; }
		ul ul ul li a:hover { display:block; color: #369; background-color: #eee; padding: 3px 8px; font-size: small; }
		
		
		li { list-style-type: none; }
		h2 { margin-top: 1.5em; }
		
		/* Header links styling */
		h3.head a { 
		color: #333;
		display:block; 
		border-top: 1px solid #36a;
		border-right: 1px solid #36a;
		background: #ddd url(down.gif) no-repeat; 
		background-position: 98% 50%;
		padding: 3px 6px;
		font-size:14px;
		text-decoration:none;
		}
		h3.head a:hover { 
		color: #000;
		background: #ccc url(down.gif) no-repeat; 
		background-position: 98% 50%;
		
		}
		h3.selected a { 
		background: #69c url(up.gif) no-repeat; 
		background-position:98% 50%;
		color: #fff;
		padding: 3px 6px;
		}
		h3.selected a:hover { 
		background: #69c url(up.gif) no-repeat; 
		background-position:98% 50%;
		color: #36a;
		}
		
		/* Xtra Header links styling */
		h4.head a { 
		color: #333;
		display:block; 
		border-top: 1px solid #36a;
		border-right: 1px solid #36a;
		background: #eee url(down.gif) no-repeat; 
		background-position: 98% 50%;
		padding: 3px 6px;
		}
		h4.head a:hover { 
		color: #000;
		background: #ddd url(down.gif) no-repeat; 
		background-position: 98% 50%;
		}
		h4.selected a { 
		background: #6c9 url(up.gif) no-repeat; 
		background-position:98% 50%;
		color: #fff;
		padding: 3px 6px;
		}
		h4.selected a:hover { 
		background: #6c9 url(up.gif) no-repeat; 
		background-position:98% 50%;
		color: #36a;
		}
	</style>
	
</head><body>
		
<script type="text/javascript" src="template/igos/index-multi.php_files/jquery.js"></script>
<script type="text/javascript" src="template/igos/index-multi.php_files/accordion.js"></script>
<script type="text/javascript">
jQuery().ready(function(){	
	// applying the settings
	jQuery('#theMenu').Accordion({
		active: 'h3.selected',
		header: 'h3.head',
		alwaysOpen: false,
		animated: true,
		showSpeed: 400,
		hideSpeed: 800
	});
	jQuery('#xtraMenu').Accordion({
		active: 'h4.selected',
		header: 'h4.head',
		alwaysOpen: false,
		animated: true,
		showSpeed: 400,
		hideSpeed: 800
	});
});	
</script>

		<ul id="theMenu">
<li style="position: static;">			
<?php
//echo 'SELECT material_resource_name FROM mst_material_resource_type WHERE material_resource_id="4"';
$physical=$dbs->query('SELECT material_resource_id,material_resource_name FROM mst_material_resource_type');

while($row=$physical->fetch_assoc())
{
?>

				<h3 class="head"><a href="javascript:;"><?php echo $row['material_resource_name']; ?></a></h3>
				<ul style="display: none;">
<li>
<ul id="xtraMenu">
<li>
	<?php
         $material='select g.gmd_id AS gmd_id,g.gmd_name AS gmd_name from mst_gmd AS g 
	            where g.gmd_code='.$row['material_resource_id'].'';
         $material=$dbs->query($material);
	 $m='';
	
	 while($row=$material->fetch_assoc())
	 {

		echo "<li><a href=index.php?gmd_search=".urlencode($row['gmd_name'])."&gmd_id=".$row['gmd_id'].">".$row['gmd_name']."</a></li>";
        }
	
	?>
</li>
</ul>
</li>
</ul>

<?php }?>
</li>

</ul> 

</body></html>
