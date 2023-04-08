<?php
//echo "I am back";
        echo "<center>";
	echo '<div id="accordion">';
        $color_new = array("tab1","tab2","tab3","tab4","tab5","tab6");
	$color_new_link_color = array("#333","#333","#333","#333","#333","#333");
	$count = 0;
	
?> 
  <ul class="test3">
  <li>
     <div class="handle3"><img src='template/school/images/tab1.png'></div>
    <h2 style="font-size:18px;">Quick Link</h2>
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
    <h2>Reference</h2>
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
    <h2 >Useful Websites</h2>
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
    <h2 >Other</h2>
    <div style="padding: 2%; width: 100%;">
        <table id="tab_bullet"  width="80%">
            <tbody>
            <tr>
            <td valign="top" >
               <ul><?php
               $set=array("How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music",'Other');            
               $qry2 = "select * from mst_material_sub_type where gmd_id = 16 order by material_sub_name ";
                                     $stat_queryforstudentsubtype =$dbs->query($qry2);
                                      $count1=1;
                                        while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc())
                                        {                                            
                                                $flagset = 0;
                                                //echo count($set);
                                                for($i=0;$i<count($set);$i++)
                                                {
                                                    //echo $datasubtype['material_sub_name']."=".$set[$i];
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
    <h2>Physical Link</h2>
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
echo '</div>';echo "<center>";
?>