<?php
///echo $path.'/js/slider/';

/*echo "<link rel='stylesheet' type='text/css' href='$path/js/slider/css/jquery.hrzAccordion.defaults.css'>";
echo "<link rel='stylesheet' type='text/css' href='$path/js/slider/css/jquery.hrzAccordion.examples.css'>";
echo "<script type=\"text/javascript\" src=\"$path/js/jsor-jcarousel-7bb2e0a/lib/jquery-1.4.2.min.js\"></script>";
//echo "<script type='text/javascript' src='http://ajax.googleapis.com/ajax/libs/jquery/1.6.1/jquery.min.js'></script>";


echo "<script type='text/javascript' src='$path/js/slider/lib/jquery.hrzAccordion.js'></script>";
echo "<script type='text/javascript' src='$path/js/slider/lib/jquery.hrzAccordion.examples.js'></script>";
*/
//echo "<script type=\"text/javascript\" src=\"$path/js/jsor-jcarousel-7bb2e0a/lib/jquery-1.4.2.min.js\"></script>";
echo "<link rel='stylesheet' type='text/css' href='$path/js/slider1/css/jquery.hrzAccordion.defaults.css'>";
echo "<link rel='stylesheet' type='text/css' href='$path/js/slider1/css/jquery.hrzAccordion.examples.css'>";
echo "<script type=\"text/javascript\" src=\"$path/js/jsor-jcarousel-7bb2e0a/lib/jquery-1.4.2.min.js\"></script>";
echo "<script type='text/javascript' src='$path/js/slider1/lib/jquery.hrzAccordion.js'></script>";
echo "<script type='text/javascript' src='$path/js/slider1/lib/jquery.hrzAccordion.examples.js'></script>";

?>

<!--<link href="template/school/css/tabs-accordion-horizontal.css" rel="stylesheet" type="text/css">
<link rel="stylesheet" type="text/css" href="template/school/css/standalone.css"/>	
<link rel="stylesheet" type="text/css" href="template/school/css/scrollable.css" />
<script src="template/school/jquery.tools.min.js" type="text/javascript"></script>
<link rel="stylesheet" type="text/css" href="template/school/css/scrollable-buttons.css" />
<script src="template/school/jquery.tools.min.js" type="text/javascript" > 
</script>-->

           <script>
              
           /* var $j = jQuery.noConflict();
            $j(function() {
            
            $j("#accordion").tabs("#accordion div", {
                tabs: 'img', 
                effect: 'horizontal'
            });
            });
//
//            $(function() {
//            
//            $("#accordion").tabs("#accordion div", {
//                tabs: 'img', 
//                effect: 'horizontal'
//            });
//            }); */
            </script>
<?php

if($_SESSION['m_member_type']=='Student' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself' && $_REQUEST['p']!='help' && $_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' && $_REQUEST['p']!='contactus' && $_REQUEST['p']!='show_detail' && empty($_GET['subtype'])  && empty($_GET['Search']) && empty($_GET['author']) && empty($_GET['search']) && empty($_REQUEST['advancesearch']))
	{
    echo '<div id="tabs_menu">';
?>
<div class="br"></div>
<div id="side_bar">
    
    <div id="grad1"> <font style="font-size:13px;font-weight:bold;font-family:custom; ">Due Books</font> 

 <?php    
  $qry = "Select DATEDIFF(due_date,now()) as date_diff ,loan_id from loan where member_id='".$_SESSION['mid']."' AND is_lent=1 AND is_return=0 ";          
  $loan_due_date_diff = $dbs->query($qry);  
  if ($loan_due_date_diff->num_rows>0)
  {
      while ($row = $loan_due_date_diff->fetch_assoc())
        {
              if ($row['date_diff']<=3)
              {
                  $qry = "Select l.item_code,b.title,l.loan_date,l.due_date from loan AS l LEFT JOIN member AS m ON l.member_id=m.member_id LEFT JOIN item AS i ON l.item_code=i.item_code LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id where m.member_id='".$_SESSION['mid']."' AND l.is_lent=1 AND is_return=0 AND l.loan_id=".$row['loan_id']."  order by l.due_date desc";                    
                  $stat_queryforstudent = $dbs->query($qry);
                   if ($stat_queryforstudent->num_rows>0)
                    {                        
                        while ($data = $stat_queryforstudent->fetch_assoc())
                        {
                            echo '<div class="due_date_style"><marquee behavior="scroll" direction="up" scrollamount="1" style="height:90px;">'.$data['title'].'<br/>'.date("d-m-Y", strtotime($data['due_date'])).'</marquee></div>';

                        }
                    }
                  
              }                                                  
        }
      
  }      
  else
  {
    echo '<div class="due_date_style" ><marquee behavior="scroll" direction="up" scrollamount="1" style="height:90px;">Please issue book</marquee></div>';    
  }      
    ?>

     </div>
    
     <div id="grad2"><font style="font-size:13px;font-weight:bold;font-family:custom;">Recently Visited Books </font>
         <?php
             
               $_sql_str = "select biblio.biblio_id,biblio.title,biblio.image,biblio.gmd_id, biblio.isbn_issn, biblio.labels, b2.count from biblio biblio INNER JOIN biblio_view b2 ON biblio.biblio_id = b2.biblio_id where b2.member_id = '".$_SESSION['mid']."' ORDER BY b2.last_update  DESC limit 2";
            $recent_act = $dbs->query($_sql_str);
            while ($data = $recent_act->fetch_assoc())
            {
      ?>
                <div class="recent_activity"><?php echo $data[title]; ?></div>
      <?php
                                                                                  
            }
              
     ?>
        <!--    <div class="recent_activity">You are Currently logged in as Member(Student) <?php echo date('d-m-y'); ?></div> -->
           <!-- <div class="recent_activity">You are diverted to Home Page</div> -->

     </div>
   
</div>
<?php } if($_SESSION['m_member_type']=='Teacher' && $_REQUEST['p']!='help' && $_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' &&$_REQUEST['p']!='show_detail' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself' && $_REQUEST['p']!='contactus' && empty($_GET['subtype'])  && empty($_GET['Search']) && empty($_GET['author']) && empty($_GET['search']) && empty($_REQUEST['advancesearch']))
	{
?>
<!--<div class="br"></div>
        
        <div id="side_bar">
            <div id="grad1">-->
<?php } 
        if($_SESSION['m_member_type']=='Parent' && $_REQUEST['p']!='help' && $_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' &&$_REQUEST['p']!='show_detail' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself' && $_REQUEST['p']!='contactus' && empty($_GET['subtype'])  && empty($_GET['Search']) && empty($_GET['author']) && empty($_GET['search']) && empty($_REQUEST['advancesearch']))
	{?>
<!--<div class="br"></div>-->
        
      
<?php } ?>
<?php  
echo '<div id="tabs" height="300px;">';
/*if($_SESSION['m_member_type']=='Parent' && $_REQUEST['p']!='help' && $_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' &&$_REQUEST['p']!='show_detail' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself' && $_REQUEST['p']!='contactus' && empty($_GET['subtype'])  && empty($_GET['Search']) && empty($_GET['author']) && empty($_GET['search']) && empty($_REQUEST['advancesearch']))
	{
	$color_new = array("tab1","tab2","tab3","tab4","tab5","tab6");
	$color_new_link_color = array("#0000CC","#663366","#990000","#FF3300","#009966","#99CC00");
	$count = 0;
	$qry = "select * from mst_material_resource_type where material_resource_name = 'Virtual Library' || material_resource_name = 'Physical Library'";
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
											if($gmd_name=="Physical Links")
											{
											
			$set=array("Books","CD","Magazines","News Paper");
										
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
           //  include LIB_DIR.'contents/rescentview.inc.php';
//added ended by Parth 382011 for rescent view
	}*/
if($_SESSION['m_member_type']=='Teacher' && $_REQUEST['p']!='help' && $_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' &&$_REQUEST['p']!='show_detail' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself' && $_REQUEST['p']!='contactus' && empty($_GET['subtype'])  && empty($_GET['Search']) && empty($_GET['author']) && empty($_GET['search']) && empty($_REQUEST['advancesearch']))
	{
	$color_new = array("tab1","tab2","tab3","tab4","tab5","tab6");
	$color_new_link_color = array("#0000CC","#663366","#990000","#FF3300","#009966","#99CC00");
	$count = 0;
	$qry = "select * from mst_material_resource_type where material_resource_name = 'Virtual Library' || material_resource_name = 'Physical Library'";
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
											if($gmd_name=="Physical Links")
											{
											
			$set=array("Books","CD","Magazines","News Paper");
										
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
										/*if($count%4==0 )
										{
										echo "</tr><tr>";
										}*/
									
									
							}
							echo "</tr></table>";
							}
//added started by Parth 382011 for rescent view
          //   include LIB_DIR.'contents/rescentview.inc.php';
//added ended by Parth 382011 for rescent view
	}
if($_SESSION['m_member_type']=='Student' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself' && $_REQUEST['p']!='help' && $_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' && $_REQUEST['p']!='contactus' && $_REQUEST['p']!='show_detail' && empty($_GET['subtype'])  && empty($_GET['Search']) && empty($_GET['author']) && empty($_GET['search']) && empty($_REQUEST['advancesearch']))
	{    
//echo '<div id="accordion">';
        
     /*   $color_new = array("tab1","tab2","tab3","tab4","tab5","tab6");
	$color_new_link_color = array("#333","#333","#333","#333","#333","#333");
	$count = 0;
	$qry = "select * from mst_material_resource_type where material_resource_name = 'Virtual Library' || material_resource_name = 'Physical Library'";
        $stat_queryforstudent = $dbs->query($qry);
		while ($data = $stat_queryforstudent->fetch_assoc()) 
                {
                    $gmd = $data['material_resource_id'];
                    $qry1 = "select * from mst_gmd where material_resource_id = '$gmd' ORDER BY gmd_id ASC";
                    $stat_queryforstudentgmd = $dbs->query($qry1);
                    while ($datagmd = $stat_queryforstudentgmd->fetch_assoc()) 
                    {
                        if($datagmd['gmd_name']!="Teachers")
                        {
                         
                            $gmd_name = $datagmd['gmd_name'];
			    $gmd_id = $datagmd['gmd_id'];
                            if($gmd_name=="Quick Link")
                            {
                                echo '<img src="template/school/images/tab1.png" height=300px width=50px />';								
                                $set=array("Text Book","E-Books","Journal","Audio/Video","Reports","Article Database","Newspapers","Case Studies");
                            }
                        }
                        
                    }
                    
                }
    */
///////  WORKING THINGS//////////////////////     
/*$color_new = array("tab1","tab2","tab3","tab4","tab5","tab6");
	$color_new_link_color = array("#333","#333","#333","#333","#333","#333");
	$count = 0;
 ?>
<ul class="test">
  <li>
        <div class="handle">            
            <img src="template/school/images/tab1.png" height=310px width=50px>
        </div>
          <!--  <img src='images/image_test.gif' align='left'>-->
        
       <?php   
         $set=array("Text Book","E-Books","Journal","Audio/Video","Reports","Article Database","Newspapers","Case Studies");
         
             echo "<div style=width:40%;padding:2%;>";             
             
		//$qry2 = "select * from mst_material_sub_type where gmd_id = '$gmd_id' order by material_sub_name ";
                $qry2 = "select * from mst_material_sub_type where gmd_id=13 order by material_sub_name ";
		$stat_queryforstudentsubtype = 	$dbs->query($qry2);
                $count1=1;
                echo "<table id=tab_bullet><tr><td valign=top><ul >";										
		while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc())
                {
                 
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
                                echo "<li><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></li>";								
                                //echo "<tr><td style=padding-left:10px;><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></td></tr>";
                            }

                            if($count1%6==0)
                            {
                                echo '</td></tr><tr><td valign=top><ul>';
                            }
                                $count1=$count1+1;
                                
                   
		}
                            $count = $count + 1;
                 echo "</ul></td></tr></table></div>";
          ?>
  </li>
  <li>
        <div class="handle">
              <img src='template/school/images/tab2.jpg' height=310px width=50px>
        </div>
        
                <?php  
                 $set=array("Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright");
         
                echo "<div style=width:40%;padding:2%;>";             
             
		//$qry2 = "select * from mst_material_sub_type where gmd_id = '$gmd_id' order by material_sub_name ";
                $qry2 = "select * from mst_material_sub_type where gmd_id=14  order by material_sub_name ";
		$stat_queryforstudentsubtype = 	$dbs->query($qry2);
                $count1=1;
                echo "<table id=tab_bullet><tr><td valign=top><ul >";										
		while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc())
                {
                 
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
                                echo "<li><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></li>";								
                                //echo "<tr><td style=padding-left:10px;><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></td></tr>";
                            }

                            if($count1%6==0)
                            {
                                echo '</td></tr><tr><td valign=top><ul>';
                            }
                                $count1=$count1+1;
                                
                   
		}
                            $count = $count + 1;
                 echo "</ul></td></tr></table></div>";
          ?>

        
  </li>
  <li>
        <div class="handle">
                <img src='template/school/images/tab3.jpg' height=310px width=50px>
        </div>
        
           <?php  
                 $set=array("Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India","Newspapers");
         
                echo "<div style=width:40%;padding:2%;>";             
             
		//$qry2 = "select * from mst_material_sub_type where gmd_id = '$gmd_id' order by material_sub_name ";
                $qry2 = "select * from mst_material_sub_type where gmd_id=15  order by material_sub_name ";
		$stat_queryforstudentsubtype = 	$dbs->query($qry2);
                $count1=1;
                echo "<table id=tab_bullet><tr><td valign=top><ul >";										
		while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc())
                {
                 
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
                                echo "<li><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></li>";								
                                //echo "<tr><td style=padding-left:10px;><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></td></tr>";
                            }

                            if($count1%6==0)
                            {
                                echo '</td></tr><tr><td valign=top><ul>';
                            }
                                $count1=$count1+1;
                                
                   
		}
                            $count = $count + 1;
                 echo "</ul></td></tr></table></div>";
          ?>
  </li>
  <li>
        <div class="handle">          
          <img src='template/school/images/tab4.jpg' height=310px width=50px>
        </div>
           
              <?php  
              $set=array("Symbols","Unit Converter","Currency","World at Glance","School Annual Reports");
         
                echo "<div style=width:40%;padding:2%;>";             
             
		//$qry2 = "select * from mst_material_sub_type where gmd_id = '$gmd_id' order by material_sub_name ";
                $qry2 = "select * from mst_material_sub_type  where gmd_id=16 order by material_sub_name ";
		$stat_queryforstudentsubtype = 	$dbs->query($qry2);
                $count1=1;
                echo "<table id=tab_bullet><tr><td valign=top><ul >";										
		while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc())
                {
                 
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
                                echo "<li><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></li>";								
                                //echo "<tr><td style=padding-left:10px;><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></td></tr>";
                            }

                            if($count1%6==0)
                            {
                                echo '</td></tr><tr><td valign=top><ul>';
                            }
                                $count1=$count1+1;
                                
                   
		}
                            $count = $count + 1;
                 echo "</ul></td></tr></table></div>";
          ?>
  </li>
 
</ul>
       
<?php
        echo ' </div>'; */
    echo "<center>";
	echo '<div id="accordion">';
        $color_new = array("tab1","tab2","tab3","tab4","tab5","tab6");
	$color_new_link_color = array("#333","#333","#333","#333","#333","#333");
	$count = 0;
	/*$qry = "select * from mst_material_resource_type where material_resource_name = 'Virtual Library' || material_resource_name = 'Physical Library'";
	//$qry = "select * from mst_material_resource_type where material_resource_name = '$_SESSION[m_member_type]'";
		$stat_queryforstudent = $dbs->query($qry);
		while ($data = $stat_queryforstudent->fetch_assoc()) 
                {
                	$gmd = $data['material_resource_id'];
			//$qry1 = "select * from mst_gmd where gmd_code = '$gmd' ORDER BY gmd_id ASC";
			//echo $qry1;
			$qry1 = "select * from mst_gmd where material_resource_id = '$gmd' ORDER BY gmd_id ASC";
			$stat_queryforstudentgmd = $dbs->query($qry1);
			//echo "<table border=0 width=900px style=color:white; align=center><tr>";
			while ($datagmd = $stat_queryforstudentgmd->fetch_assoc()) 
                        {
                            if($datagmd['gmd_name']!="Teachers")
                            {
				$gmd_name = $datagmd['gmd_name'];
				$gmd_id = $datagmd['gmd_id'];
									
                                if($gmd_name=="Quick Link")
				{
                                    echo '<img src="template/school/images/tab1.png" height=300px width=50px />';								
                                    $set=array("Text Book","E-Books","Journal","Audio/Video","Reports","Article Database","Newspapers","Case Studies");
										
				}
				if($gmd_name=="Reference")
				{
                                    echo '<img src="template/school/images/tab2.jpg" height=300px width=50px />';										
                                    $set=array("Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright");
				}
				if($gmd_name=="Useful Websites")
				{
                                    echo '<img src="template/school/images/tab3.jpg" height=300px width=50px />';										
                                    $set=array("Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India","Entertainment & Media");
				}
				if($gmd_name=="Other")
				{
                                    echo '<img src="template/school/images/tab4.jpg" height=300px width=50px />';										
                                    $set=array("How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music");
				}
                            /*	if($gmd_name=="Social Networks")
				{
                			echo '<img src="template/school/images/tab6.jpg" height=300px width=50px />';										
                                	$set=array("Blog","Facebook","Twitter","Orkut");
										
											}*/
    ///////temp
    
			/*	if($gmd_name=="Physical Links")
				{
                                    echo '<img src="template/school/images/tab5.jpg" height=300px width=50px />';										
                                    $set=array("Books","CD","Magazines","News Paper");
				}
                                    $count1 = 0;
	//echo "<td valign=top class=$color_new[$count]> <table width=100%><tr align=center><td class=$color_new[$count]_header>$gmd_name</td></tr>";
              
                echo "<div style=width:40%;padding:2%;>";             
                echo "<table id=tab_bullet>";
                    echo "<tr>";
                        echo "<td valign=top>";
                            echo "<ul >";										
                                    $qry2 = "select * from mst_material_sub_type where gmd_id = '$gmd_id' order by material_sub_name ";
                                     $stat_queryforstudentsubtype = 	$dbs->query($qry2);
                                      $count1=1;
                                        while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc())
                                        {
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
                                                        echo "<li><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></li>";
                                                    }

                                                    if($count1%6==0)
                                                    {
                                                        echo '</td><td valign=top><ul>';
                                                    }
                                                    $count1=$count1+1;
                                            }
                                            $count = $count + 1;
                                   echo "</ul>";
                                echo"</td>";
                             echo "</tr>";
                          echo "</table>";
               echo "</div>";
				//echo "</table></td>";
		if($count%4==0 )
		{
                    //echo "</tr><tr>";
		}
		}
									
							}
							//echo "</tr></table>";
}*/
//added started by Parth 382011 for rescent view
           // include LIB_DIR.'contents/rescentview.inc.php';
//added ended by Parth 382011 for rescent view
        
        ///////////////////////////////////////////////////////////////////////////////////
       ?> 
        <ul class="test3">
  <li><div class="handle3"><img src='template/school/images/tab1.png'></div>
    <h3>Quick Link</h3>
    <div style="padding: 2%; width: 100%;">
        <table id="tab_bullet" width="80%">
            <tbody>
            <tr>
            <td valign="top" >
               <ul><?php
               $set=array("Text Book","E-Books","Journal","Audio/Video","Reports","Article Database","Newspapers","Case Studies");            
               $qry2 = "select * from mst_material_sub_type where gmd_id = 13 order by material_sub_name ";
                                     $stat_queryforstudentsubtype =$dbs->query($qry2);
                                      $count1=1;
                                        while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc())
                                        {
                                            
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
                                                        echo "<li><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></li>";
                                                    }

                                                    if($count1%6==0)
                                                    {
                                                        echo '</td><td valign=top><ul>';
                                                    }
                                                    $count1=$count1+1;
                                            }
                                            $count = $count + 1;
                                            ?>
                
              </ul>
           </td>
           
            </tr>
      </tbody>
      </table>
    </div>  
  </li>
  <li><div class="handle3"><img src='template/school/images/tab2.jpg'></div>
    <h3>Reference</h3>
<div style="padding: 2%; width: 100%;">
        <table id="tab_bullet"  width="80%">
            <tbody>
            <tr>
            <td valign="top" >
               <ul><?php
               $set=array("Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright");
               $qry2 = "select * from mst_material_sub_type where gmd_id = 14 order by material_sub_name ";
                                     $stat_queryforstudentsubtype =$dbs->query($qry2);
                                      $count1=1;
                                        while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc())
                                        {
                                            
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
                                                        echo "<li><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></li>";
                                                    }

                                                    if($count1%6==0)
                                                    {
                                                        echo '</td><td valign=top><ul>';
                                                    }
                                                    $count1=$count1+1;
                                            }
                                            $count = $count + 1;
                                            ?>
                
              </ul>
           </td>
           
            </tr>
      </tbody>
      </table>
    </div>      
  </li>
  <li><div class="handle3"><img src='template/school/images/tab3.jpg'></div>
    <h3>Useful Websites</h3>
    <div style="padding: 2%; width: 100%;">
        <table id="tab_bullet"  width="80%">
            <tbody>
            <tr>
            <td valign="top" >
               <ul><?php
               $set=array("Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India");
               $qry2 = "select * from mst_material_sub_type where gmd_id = 15 order by material_sub_name ";
                                     $stat_queryforstudentsubtype =$dbs->query($qry2);
                                      $count1=1;
                                        while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc())
                                        {
                                            
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
                                                        echo "<li><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></li>";
                                                    }

                                                    if($count1%6==0)
                                                    {
                                                        echo '</td><td valign=top><ul>';
                                                    }
                                                    $count1=$count1+1;
                                            }
                                            $count = $count + 1;
                                            ?>
                
              </ul>
           </td>
           
            </tr>
      </tbody>
      </table>
    </div>  

  </li>
  <li><div class="handle3"><img src='template/school/images/tab4.jpg'></div>
    <h3>Other</h3>
    <div style="padding: 2%; width: 100%;">
        <table id="tab_bullet"  width="80%">
            <tbody>
            <tr>
            <td valign="top" >
               <ul><?php
               $set=array("How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music");            
               $qry2 = "select * from mst_material_sub_type where gmd_id = 16 order by material_sub_name ";
                                     $stat_queryforstudentsubtype =$dbs->query($qry2);
                                      $count1=1;
                                        while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc())
                                        {
                                            
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
                                                        echo "<li><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></li>";
                                                    }

                                                    if($count1%6==0)
                                                    {
                                                        echo '</td><td valign=top><ul>';
                                                    }
                                                    $count1=$count1+1;
                                            }
                                            $count = $count + 1;
                                            ?>
                
              </ul>
           </td>
           
            </tr>
      </tbody>
      </table>
    </div>  
  </li>

  <li><div class="handle3"><img src='template/school/images/tab5.jpg'></div>
    <h3>Physical Link</h3>
   <div style="padding: 2%; width: 100%;">
        <table id="tab_bullet"  width="80%"> 
            <tbody>
            <tr>
            <td valign="top" >
               <ul><?php
               $set=array("Books","CD","Magazines","News Paper");            
               $qry2 = "select * from mst_material_sub_type where gmd_id = 36 order by material_sub_name ";
                                     $stat_queryforstudentsubtype =$dbs->query($qry2);
                                      $count1=1;
                                        while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc())
                                        {
                                            
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
                                                        echo "<li><a href=index.php?subtype=$subtype&subid=$datasubtype[material_sub_id] style=text-decoration:none;font-size:15px;color:$color_new_link_color[$count];font-weight:bold;>$datasubtype[material_sub_name]</a></li>";
                                                    }

                                                    if($count1%6==0)
                                                    {
                                                        echo '</td><td valign=top><ul>';
                                                    }
                                                    $count1=$count1+1;
                                            }
                                            $count = $count + 1;
                                            ?>
                
              </ul>
           </td>
           
            </tr>
      </tbody>
      </table>
    </div>  
  </li>
</ul>

        
        
       
        
        
        
        
  <?php      
         ///////////////////////////////////////////////////////////////////////////////////
        
echo '</div>';echo "<center>";
}
	/*if(empty($_SESSION['m_member_type']) && $_REQUEST['p']!='help' && $_REQUEST['p']!='member' && $_REQUEST['p']!='book_request' && $_REQUEST['p']!='show_detail' && $_REQUEST['p']!='rescentview' && $_REQUEST['p']!='myeself' && $_REQUEST['p']!='contactus' && empty($_GET['subtype']) && empty($_GET['Search']) && empty($_GET['author']) && empty($_GET['search']) && empty($_REQUEST['advancesearch']))
	{
//$id = GetHostByName($_SERVER['REMOTE_ADDR']); 
$id = $_SERVER['REMOTE_ADDR'];
$id = $id.".php";
$myFile = SENAYAN_BASE_DIR.$id;
if(file_exists($myFile))
{
unlink($myFile);
echo '<script type="text/javascript">';
            //echo 'alert(\''.__('Please fill your Username and Password to Login!').'\');';
            echo 'location.href = \'index.php\';';
            echo '</script>';

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
							while ($datagmd = $stat_queryforstudentgmd->fetch_assoc()) 
                                                        {
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
	}*/

?>



       

<?php
echo '</div></div>';


?>


