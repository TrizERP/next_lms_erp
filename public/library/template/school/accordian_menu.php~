<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml2/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">

<head>

<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js"></script>

<script type="text/javascript" src="js/ddaccordion.js">

/***********************************************
* Accordion Content script- (c) Dynamic Drive DHTML code library (www.dynamicdrive.com)
* Visit http://www.dynamicDrive.com for hundreds of DHTML scripts
* This notice must stay intact for legal use
***********************************************/

</script>


<script type="text/javascript">


ddaccordion.init({
	headerclass: "submenuheader", //Shared CSS class name of headers group
	contentclass: "submenu", //Shared CSS class name of contents group
	revealtype: "click", //Reveal content when user clicks or onmouseover the header? Valid value: "click", "clickgo", or "mouseover"
	mouseoverdelay: 200, //if revealtype="mouseover", set delay in milliseconds before header expands onMouseover
	collapseprev: true, //Collapse previous content (so only one open at any time)? true/false 
	defaultexpanded: [], //index of content(s) open by default [index1, index2, etc] [] denotes no content
	onemustopen: false, //Specify whether at least one header should be open always (so never all headers closed)
	animatedefault: false, //Should contents open by default be animated into view?
	persiststate: true, //persist state of opened contents within browser session?
	toggleclass: ["", ""], //Two CSS classes to be applied to the header when it's collapsed and expanded, respectively ["class1", "class2"]
	togglehtml: ["suffix", "<img src='template/igos/images/plus.png' class='statusicon' />", "<img src='template/igos/images/minus.png' class='statusicon' />"], //Additional HTML added to the header when it's collapsed and expanded, respectively  ["position", "html1", "html2"] (see docs)
	animatespeed: "fast", //speed of animation: integer in milliseconds (ie: 200), or keywords "fast", "normal", or "slow"
	oninit:function(headers, expandedindices){ //custom code to run when headers have initalized
		//do nothing
	},
	onopenclose:function(header, index, state, isuseractivated){ //custom code to run whenever a header is opened or closed
		//do nothing
	}
})


</script>


<style type="text/css">

.glossymenu{
margin: 5px 0;
padding: 0;
width: 170px; /*width of menu*/
border: 1px solid #9A9A9A;
border-bottom-width: 0;
}

.glossymenu a.menuitem{
background: black url(template/igos/images/glossyback.gif) repeat-x bottom left;
font-family:Arial, Helvetica, sans-serif;
font-size:13px;
font-weight:bold;
color: #fff;
display: block;
position: relative; /*To help in the anchoring of the ".statusicon" icon image*/
width: auto;
padding: 4px 0;
padding-left: 10px;
text-decoration: none;
}


.glossymenu a.menuitem:visited, .glossymenu .menuitem:active{
color: white;
}

.glossymenu a.menuitem .statusicon{ /*CSS for icon image that gets dynamically added to headers*/
position: absolute;
top: 5px;
right: 5px;
border: none;
}

.glossymenu a.menuitem:hover{
background-image: url(template/igos/images/glossyback2.gif);
}

.glossymenu div.submenu{ /*DIV that contains each sub menu*/
background: white;
}

.glossymenu div.submenu ul{ /*UL of each sub menu*/
list-style-type: none;
margin: 0;
padding: 0;
}

.glossymenu div.submenu ul li{
border-bottom: 1px solid #ccc;
}

.glossymenu div.submenu ul li a{
display: block;
font-family:Arial, Helvetica, sans-serif;
font-size:13px;
color: #000;
text-decoration: none;
padding: 2px 0;
padding-left: 10px;
}

.glossymenu div.submenu ul li a:hover{
background: #e0e0e0;
color: #000;
font-weight:normal;
}

</style>

</head>

<body>

<div class="glossymenu">
<?php
$physical=$dbs->query('SELECT material_resource_id,material_resource_name FROM mst_material_resource_type where active_inactive=1');


while($row=$physical->fetch_assoc())
{
?>
	<a class="menuitem submenuheader" href="#"><?php echo $row['material_resource_name']; ?></a>
<?php
           $material='select g.gmd_id AS gmd_id,g.gmd_name AS gmd_name,mr.material_resource_name from mst_gmd AS g 
		    LEFT JOIN mst_material_resource_type AS mr ON mr.material_resource_id=g.gmd_code
	            where g.gmd_code='.$row['material_resource_id'].' AND g.active_inactive=1';
         $material=$dbs->query($material);
	 $m='';
?>
<div  class="submenu">
	<ul>
<?php
	 while($row=$material->fetch_assoc())
	 { 
		
			echo "<li><a href=index.php?gmd_search=".urlencode($row['gmd_name'])."&gmd_id=".$row['gmd_id']."&resource=".urlencode($row['material_resource_name']).">".$row['gmd_name']."</a>
			
			</li>";
			
		
	}?>
	</ul>
</div>
<?php }?>
</div>

</body>
</html>
