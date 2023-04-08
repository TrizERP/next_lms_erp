<?php
if($_SESSION['m_member_type']=='Parent' && $_REQUEST['p']!='help' && $_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' &&$_REQUEST['p']!='show_detail' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself' && empty($_GET['subtype'])  && empty($_GET['Search']) && empty($_GET['author']) && empty($_GET['search']) && empty($_REQUEST['advancesearch']))
	{
	$color_new = array("tab1","tab2","tab3","tab4","tab5","tab6");
	$color_new_link_color = array("#0000CC","#663366","#990000","#FF3300","#009966","#99CC00");
	$count = 0;
	$qry = "select * from mst_material_resource_type where material_resource_name = 'Virtual Library'";
	//$qry = "select * from mst_material_resource_type where material_resource_name = '$_SESSION[m_member_type]'";
		$stat_queryforstudent = $dbs->query($qry);
		while ($data = $stat_queryforstudent->fetch_assoc()) {
    
   							$gmd = $data['material_resource_id'];
							//$qry1 = "select * from mst_gmd where gmd_code = '$gmd' ORDER BY gmd_id ASC";
							//echo $qry1;
							$qry1 = "select * from mst_gmd where material_resource_id = '$gmd' ORDER BY gmd_id ASC";
							$stat_queryforstudentgmd = 	$dbs->query($qry1);
							echo "<table border=0 width=900px style=color:white; align=center><tr>";
							while ($datagmd = $stat_queryforstudentgmd->fetch_assoc()) {
									if($datagmd['gmd_name']!="Teachers")
									{
										$gmd_name = $datagmd['gmd_name'];
										$gmd_id = $datagmd['gmd_id'];
										
										if($gmd_name=="Quick Link")
											{
											
			$set=array("Text Book","Newspapers","Assignments");
										
											}
											if($gmd_name=="Reference")
											{
											
			$set=array("Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright");
										
											}
											if($gmd_name=="Useful Websites")
											{
											
			$set=array("Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India");
										
											}
												if($gmd_name=="Other")
											{
											
			$set=array("How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music");
										
											}
											if($gmd_name=="Social Networks")
											{
											
			$set=array("Blog","Facebook","Twitter","Orkut");
										
											}
											$count1 = 0;
										echo "<td valign=top class=$color_new[$count]><table width=100%><tr align=center><td class=$color_new[$count]_header>$gmd_name</td></tr>";
										
										$qry2 = "select * from mst_material_sub_type where gmd_id = '$gmd_id'";
										$stat_queryforstudentsubtype = 	$dbs->query($qry2);
										while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {
										$flagset = 0;
										
										for($i=0;$i<count($set);$i++)
										{
										
											if($datasubtype['material_sub_name']==$set[$i])
												{
												$flagset = 1;
												}
												
										}
										
										if($flagset == 1)
										{
										$subtype = urlencode($datasubtype['material_sub_name']);
										echo "<tr><td style=padding-left:10px;><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></td></tr>";
										}
										}$count = $count + 1;
										echo "</table></td>";
										if($count%4==0 )
										{
										echo "</tr><tr>";
										}
									
									}
							}
							echo "</tr></table>";
							}
//added started by Parth 382011 for rescent view
             include LIB_DIR.'contents/rescentview.inc.php';
//added ended by Parth 382011 for rescent view
	}
if($_SESSION['m_member_type']=='Teacher' && $_REQUEST['p']!='help' && $_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' &&$_REQUEST['p']!='show_detail' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself' && empty($_GET['subtype'])  && empty($_GET['Search']) && empty($_GET['author']) && empty($_GET['search']) && empty($_REQUEST['advancesearch']))
	{
	$color_new = array("tab1","tab2","tab3","tab4","tab5","tab6");
	$color_new_link_color = array("#0000CC","#663366","#990000","#FF3300","#009966","#99CC00");
	$count = 0;
	$qry = "select * from mst_material_resource_type where material_resource_name = 'Virtual Library'";
	//$qry = "select * from mst_material_resource_type where material_resource_name = '$_SESSION[m_member_type]'";
		$stat_queryforstudent = $dbs->query($qry);
		while ($data = $stat_queryforstudent->fetch_assoc()) {
    
   							$gmd = $data['material_resource_id'];
							//$qry1 = "select * from mst_gmd where gmd_code = '$gmd' ORDER BY gmd_id ASC";
							//echo $qry1;
$qry1 = "select * from mst_gmd where material_resource_id = '$gmd' ORDER BY gmd_id ASC";
							$stat_queryforstudentgmd = $dbs->query($qry1);
							echo "<table border=0 width=900px style=color:white; align=center><tr>";
							while ($datagmd = $stat_queryforstudentgmd->fetch_assoc()) {
									
										$gmd_name = $datagmd['gmd_name'];
										$gmd_id = $datagmd['gmd_id'];
										if($gmd_name=="Teachers")
											{
											
			$set=array("Class Room Data","Teachers Book","Annual Lesson Plan","Repository","Lecture Series","E-Learning","Expert Database");
										
											}
										if($gmd_name=="Quick Link")
											{
											
			$set=array("Text Book","E-Books","Journal","Audio/Video","Reports","Article Database","Newspapers","Case Studies");
										
											}
											if($gmd_name=="Reference")
											{
											
			$set=array("Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright");
										
											}
											if($gmd_name=="Useful Websites")
											{
											
			$set=array("Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India");
										
											}
												if($gmd_name=="Other")
											{
											
			$set=array("How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music");
										
											}
											if($gmd_name=="Social Networks")
											{
											
			$set=array("Blog","Facebook","Twitter","Orkut");
										
											}
											$count1 = 0;
echo "<td valign=top class=$color_new[$count]><table width=100%><tr align=center><td class=$color_new[$count]_header>$gmd_name</td></tr>";
										
										$qry2 = "select * from mst_material_sub_type where gmd_id = '$gmd_id'";
										$stat_queryforstudentsubtype = 	$dbs->query($qry2);
										while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {
										$flagset = 0;
										
										for($i=0;$i<count($set);$i++)
										{
										
											if($datasubtype['material_sub_name']==$set[$i])
												{
												$flagset = 1;
												}
												
										}
										
										if($flagset == 1)
										{
										$subtype = urlencode($datasubtype['material_sub_name']);
										echo "<tr><td style=padding-left:10px;><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></td></tr>";
										}
										}$count = $count + 1;
										echo "</table></td>";
										if($count%4==0 )
										{
										echo "</tr><tr>";
										}
									
									
							}
							echo "</tr></table>";
							}
//added started by Parth 382011 for rescent view
             include LIB_DIR.'contents/rescentview.inc.php';
//added ended by Parth 382011 for rescent view
	}
if($_SESSION['m_member_type']=='Student' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself' && $_REQUEST['p']!='help' && $_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' &&$_REQUEST['p']!='show_detail' && empty($_GET['subtype'])  && empty($_GET['Search']) && empty($_GET['author']) && empty($_GET['search']) && empty($_REQUEST['advancesearch']))
	{
	$color_new = array("tab1","tab2","tab3","tab4","tab5","tab6");
	$color_new_link_color = array("#0000CC","#663366","#990000","#FF3300","#009966","#99CC00");
	$count = 0;
	$qry = "select * from mst_material_resource_type where material_resource_name = 'Virtual Library'";
	//$qry = "select * from mst_material_resource_type where material_resource_name = '$_SESSION[m_member_type]'";
		$stat_queryforstudent = $dbs->query($qry);
		while ($data = $stat_queryforstudent->fetch_assoc()) {
    
   							$gmd = $data['material_resource_id'];
							//$qry1 = "select * from mst_gmd where gmd_code = '$gmd' ORDER BY gmd_id ASC";
							//echo $qry1;
							$qry1 = "select * from mst_gmd where material_resource_id = '$gmd' ORDER BY gmd_id ASC";
							$stat_queryforstudentgmd = $dbs->query($qry1);
							echo "<table border=0 width=900px style=color:white; align=center><tr>";
							while ($datagmd = $stat_queryforstudentgmd->fetch_assoc()) {
									if($datagmd['gmd_name']!="Teachers")
									{
										$gmd_name = $datagmd['gmd_name'];
										$gmd_id = $datagmd['gmd_id'];
										
										if($gmd_name=="Quick Link")
											{
											
			$set=array("Text Book","E-Books","Journal","Audio/Video","Reports","Article Database","Newspapers","Case Studies");
										
											}
											if($gmd_name=="Reference")
											{
											
			$set=array("Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright");
										
											}
											if($gmd_name=="Useful Websites")
											{
											
			$set=array("Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India","Entertainment & Media");
										
											}
												if($gmd_name=="Other")
											{
											
			$set=array("How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music");
										
											}
											if($gmd_name=="Social Networks")
											{
											
			$set=array("Blog","Facebook","Twitter","Orkut");
										
											}
											$count1 = 0;
										echo "<td valign=top class=$color_new[$count]><table width=100%><tr align=center><td class=$color_new[$count]_header>$gmd_name</td></tr>";
										
										$qry2 = "select * from mst_material_sub_type where gmd_id = '$gmd_id'";
										$stat_queryforstudentsubtype = 	$dbs->query($qry2);
										while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {
										$flagset = 0;
										
										for($i=0;$i<count($set);$i++)
										{
										
											if($datasubtype['material_sub_name']==$set[$i])
												{
												$flagset = 1;
												}
												
										}
										
										if($flagset == 1)
										{
										$subtype = urlencode($datasubtype['material_sub_name']);
										echo "<tr><td style=padding-left:10px;><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></td></tr>";
										}
										}$count = $count + 1;
										echo "</table></td>";
										if($count%4==0 )
										{
										echo "</tr><tr>";
										}
									}
									
							}
							echo "</tr></table>";
							}
//added started by Parth 382011 for rescent view
             include LIB_DIR.'contents/rescentview.inc.php';
//added ended by Parth 382011 for rescent view
			}
	if(empty($_SESSION['m_member_type']) && $_REQUEST['p']!='help' && $_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' && $_REQUEST['p']!='show_detail' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself' && empty($_GET['subtype']) && empty($_GET['Search']) && empty($_GET['author']) && empty($_GET['search']) && empty($_REQUEST['advancesearch']))
	{
//$id = GetHostByName($_SERVER['REMOTE_ADDR']); 
$id = $_SERVER['REMOTE_ADDR'];
$id = $id.".php";
$myFile = SENAYAN_BASE_DIR.$id;
if(file_exists($myFile))
{
unlink($myFile);
}
$color_new = array("tab1","tab2","tab3","tab4","tab5","tab6");
$color_new_link_color = array("#0000CC","#663366","#990000","#FF3300","#009966","#99CC00");
	$count = 0;
	$qry = "select * from mst_material_resource_type where material_resource_name = 'Virtual Library'";
	//$qry = "select * from mst_material_resource_type where material_resource_name = '$_SESSION[m_member_type]'";
		$stat_queryforstudent = $dbs->query($qry);
		while ($data = $stat_queryforstudent->fetch_assoc()) {
    
   							$gmd = $data['material_resource_id'];
							//$qry1 = "select * from mst_gmd where gmd_code = '$gmd' ORDER BY gmd_id ASC";
							//echo $qry1;
							$qry1 = "select * from mst_gmd where material_resource_id = '$gmd' ORDER BY gmd_id ASC";
							$stat_queryforstudentgmd = 	$dbs->query($qry1);
							echo "<table border=0 width=900px style=color:white; align=center><tr>";
							while ($datagmd = $stat_queryforstudentgmd->fetch_assoc()) {
									if($datagmd['gmd_name']!="Teachers" && $datagmd['gmd_name']!="Quick Link")
									{
										$gmd_name = $datagmd['gmd_name'];
										$gmd_id = $datagmd['gmd_id'];
										
										
											if($gmd_name=="Reference")
											{
											
			$set=array("Dictionaries","Directories","Encyclopedia","Google scholar","Right to Information Act","Copyright");
										
											}
											if($gmd_name=="Useful Websites")
											{
											
			$set=array("Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India","Newspapers");
										
											}
												if($gmd_name=="Other")
											{
											
			$set=array("Symbols","Unit Converter","Currency","World at Glance","School Annual Reports");
										
											}
											if($gmd_name=="Social Networks")
											{
											
			$set=array("Blog","Facebook","Twitter","Orkut");
										
											}
											$count1 = 0;
										echo "<td valign=top class=$color_new[$count]><table width=100%><tr align=center><td class=$color_new[$count]_header>$gmd_name</td></tr>";
										
										$qry2 = "select * from mst_material_sub_type where gmd_id = '$gmd_id'";
										$stat_queryforstudentsubtype = 	$dbs->query($qry2);
										while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {
										$flagset = 0;
										
										for($i=0;$i<count($set);$i++)
										{
										
											if($datasubtype['material_sub_name']==$set[$i])
												{
												$flagset = 1;
												}
												
										}
										
										if($flagset == 1)
										{
										$subtype = urlencode($datasubtype['material_sub_name']);
										echo "<tr><td style=padding-left:10px;><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></td></tr>";
										}
										}$count = $count + 1;
										echo "</table></td>";
										if($count%4==0 )
										{
										echo "</tr><tr>";
										}
									}
									
							}
							echo "</tr></table>";
							}
	}
?>



