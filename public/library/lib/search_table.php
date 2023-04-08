
<br>
<table border="0" width="90%">
<tr>
        <th>Search:</th>
        <td width="5%">
            <select name="material_sub_type_select"> 
                  <option value="SELECT" >---Select Material Sub Type ---</option>
                <?php
                if($_SESSION['USER_GROUP_ID']=='2')
                {               
                    $qry2 = "select * from mst_gmd order by gmd_id";
                    $m_type = $dbs->query($qry2);
                     while ($datasubtype = $m_type->fetch_array())
                     {              ?>
                                
                         <option value=<?php echo $datasubtype[gmd_id];?> style="font-weight:bold;" <?php if ($_SESSION['material_id']==$datasubtype[gmd_id]) {?> selected="selected" <?php } ?> ><?php echo $datasubtype[gmd_name] ?></option>";                                                                                      
                         <?php      $qry3 = "select * from mst_material_sub_type where gmd_id=$datasubtype[gmd_id] order by material_sub_name";                                           
                               $sub_type = $dbs->query($qry3);
                               while ($datasubtype1 = $sub_type->fetch_array())
                               {  
                          ?> 
                                   <option <?php if ($_SESSION['material_id']==$datasubtype1[material_sub_id]) {?> selected="selected" <?php } ?>  value="<?php echo $datasubtype1[material_sub_id]; ?>"  > &nbsp;&nbsp;&nbsp; <?php  echo $datasubtype1[material_sub_name]; ?></option>";                             
                            
                         <?php  }
                                
                     }
                }
                if($_SESSION['USER_GROUP_ID']=='3')
                {
                                         
                    $qry2 = "select * from mst_gmd order by gmd_id";
                    $m_type = $dbs->query($qry2);
                     while ($datasubtype = $m_type->fetch_array())
                     {              ?>
                                
                         <option value=<?php echo $datasubtype[gmd_id];?> style="font-weight:bold;" <?php if ($_SESSION['material_id']==$datasubtype[gmd_id]) {?> selected="selected" <?php } ?> ><?php  echo $datasubtype[gmd_name]; ?></option>";                                                                                      
                         <?php      $qry3 = "select * from mst_material_sub_type where gmd_id=$datasubtype[gmd_id] order by material_sub_name";                                           
                               $sub_type = $dbs->query($qry3);
                               while ($datasubtype1 = $sub_type->fetch_array())
                               {  
                          ?> 
                                   <option <?php if ($_SESSION['material_id']==$datasubtype1[material_sub_id]) {?> selected="selected" <?php } ?>  value="<?php echo $datasubtype1[material_sub_id]; ?>"  > &nbsp;&nbsp;&nbsp; <?php echo $datasubtype1[material_sub_name]; ?></option>";                             
                            
                         <?php  }
                                
                     }
                                                                  
                                                                          
                }
                if($_SESSION['m_member_type']=='Parent')
                {
                        $set_select=array("Text Book","Newspapers","Assignments","Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright","Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India","How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music","Blog","Facebook","Twitter","Orkut","Books","CD","Magazines","News Paper");
                        $qry2 = "select * from mst_material_sub_type";
                        $stat_queryforstudentsubtype = 	$dbs->query($qry2);
                        while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {

                        $flagset = 0;

                                                        for($i=0;$i<count($set_select);$i++)
                                                                        {

                                                                                        if($datasubtype['material_sub_name']==$set_select[$i])
                                                                                                                {
                                                                                                                $flagset = 1;
                                                                                                                }

                                                                        }

                                                                        if($flagset == 1)
                                                                        {
                                                                                $subtype = urlencode($datasubtype['material_sub_name']);
                                                                echo "<option value=$datasubtype[material_sub_id]>$datasubtype[material_sub_name]</option>";
                                                                        }
                                                                                                }

                }
                if(empty($_SESSION['m_member_type']))
                {
                                $set_select=array("Class Room Data","Teachers Book","Annual Lesson Plan","Repository","Lecture Series","E-Learning","Expert Database","Text Book","E-Books","Journal","Audio/Video","Reports","Article Database","Newspapers","Case Studies","Dictionaries","Directories","Acronyms","Encyclopedia","Biographies","Database","Google scholar","Maps","Quotations","Thesaurus","Yearbook","Right to Information Act","Copyright","Career Development","Search Engines","Government","International education","Travel","Downloads","Education in India","How it work","Hands-on-activities","Reading mission","Symbols","Unit Converter","Currency","World at Glance","Music","Blog","Facebook","Twitter","Orkut");
                                $qry2 = "select * from mst_material_sub_type";
                                $stat_queryforstudentsubtype = 	$dbs->query($qry2);
                                while ($datasubtype = $stat_queryforstudentsubtype->fetch_assoc()) {

                                $flagset = 0;

                                                                for($i=0;$i<count($set_select);$i++)
                                                                                {

                                                                                                if($datasubtype['material_sub_name']==$set_select[$i])
                                                                                                                        {
                                                                                                                        $flagset = 1;
                                                                                                                        }

                                                                                }

                                                                                if($flagset == 1)
                                                                                {
                                                                                        $subtype = urlencode($datasubtype['material_sub_name']);
                                                                        //echo "<option value=$datasubtype[material_sub_id]>$datasubtype[material_sub_name]</option>";
                                                                                }
                                                                                                        }
                }

                ?>
                </select>
    </td>
    <td  align="center"><input type="text" name="q" size="40" value="<?php echo $_GET['q']; ?>" /></td>
    <td><input type="submit" name="Search" value="Keyword Search" /></td>
   <?php if ($_REQUEST['p']!="book_request") {?> <td><input type="submit" name="Search" value="Google Search" /> <?php } ?>
        <input type="hidden" name="search1" value="Search" />
    </td>
    <td width="20%"><a href="index.php?advancesearch=set">Advance Search</a></td></tr>
</table>
<!--</div>-->