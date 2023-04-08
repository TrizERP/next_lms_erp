<?php
$sertracking=array();
$sertracking=explode('/', $_SERVER[SCRIPT_FILENAME]);
$data_file='/'.$sertracking[1].'/'.$sertracking[2].'/'.$sertracking[3].'/'.$sertracking[4].'/data.php';

require $data_file;
include_once '../sysconfig.inc.php';     
        global $dbs;                           
       
	$query="SELECT * FROM category_mast WHERE  material_sub_id=$_REQUEST[id] order by cat_mast_user";                   
        //$query="SELECT * FROM $inte_schema.tbluser_groups order by user_group_name LIMIT 5";                           
	$data=$dbs->query($query);
	
        $record_data=array();
        
	while($row= $data->fetch_assoc())
	{                 
             $i=$row['cat_mast_user'];
             $record_data[$i][1]=$row['cat_issue_limit'];
             $record_data[$i][2]=$row['cat_issue_period'];
             $record_data[$i][3]=$row['cat_re_issue_limit'];
             $record_data[$i][4]=$row['cat_fine_each_day'];                                                          
        }   
    
?>	                

        <table  width="100%" id="maintable1" name="maintable1" >
            <tr  align="center" height="10%">
            <td width="20%"  height="10%" valign="top" style="font-weight: bold;" class="alterCell">&nbsp;</td>                
                <?php                            
                    $user=array();
                    $user_name=array();
                  $qry="SELECT * FROM ".$inte_schema.".tbluser_groups  order by sort_order LIMIT 5";                                                                   
                  $category_user = $dbs->query($qry);  
                  if ($category_user->num_rows>0)
                  {
                      while ($row = $category_user->fetch_assoc())
                        {
                          $user[]=$row['user_group_id'];
                          $user_name[]=$row['user_group_name'];

                ?>             
                <td width="15%"   valign="top" style="font-weight: bold;" class="alterCell"><? echo $row['user_group_name']; ?></td>
                <?php
                }

                  }      
                ?>              
        </tr>   
        
        
        <tr>
            <td width="15%"  valign="top" style="font-weight: bold;" class="alterCell">Issue Limit (Quantity)</td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[0]]['1'] ?>" id="admin_issuelimit" name="admin_issuelimit" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[0] ;?>" name="ADMIN">
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[1]]['1'] ?>" id="stu_issuelimit" name="stu_issuelimit" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[1] ;?>"  name="STUDENT" >
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[2]]['1'] ?>" id="teach_issuelimit" name="teach_issuelimit" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[2] ;?>" name="STAFF" >
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[3]]['1'] ?>"  id="non_tech_issuelimit" name="non_tech_issuelimit" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[3] ;?>" name="EMPLOYEE" >
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[4]]['1'] ?>" id="parnt_issuelimit" name="parnt_issuelimit" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[4] ;?>" name="PARENTS" >
            </td>                        
        </tr>
        
        <tr height="5%">
            <td width="15%" height="5%" valign="top" style="font-weight: bold;" class="alterCell">Issue Period (In Days)</td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[0]]['2'] ?>" id="admin_issueperiod" name="admin_issueperiod" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[0] ;?>" name="ADMIN">
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[1]]['2'] ?>" id="stu_issueperiod" name="stu_issueperiod" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[1] ;?>"  name="STUDENT" >
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[2]]['2'] ?>" id="teach_issueperiod" name="teach_issueperiod" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[2] ;?>" name="STAFF" >
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[3]]['2'] ?>"  id="non_tech_issueperiod" name="non_tech_issueperiod" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[3] ;?>" name="EMPLOYEE" >
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[4]]['2'] ?>" id="parnt_issueperiod" name="parnt_issueperiod" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[4] ;?>" name="PARENTS" >
            </td>                        
        </tr>
        
        <tr height="5%">
            <td width="15%" height="5%" valign="top" style="font-weight: bold;" class="alterCell">Re Issue limit</td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[0]]['3'] ?>" id="admin_reissuelimit" name="admin_reissuelimit" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[0] ;?>" name="ADMIN">
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[1]]['3'] ?>" id="stu_reissuelimit" name="stu_reissuelimit" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[1] ;?>"  name="STUDENT" >
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[2]]['3'] ?>" id="teach_reissuelimit" name="teach_reissuelimit" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[2] ;?>" name="STAFF" >
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[3]]['3'] ?>"  id="non_teach_reissuelimit" name="non_teach_reissuelimit" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[3] ;?>" name="EMPLOYEE" >
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[4]]['3'] ?>" id="parant_reissuelimit" name="parant_reissuelimit" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[4] ;?>" name="PARENTS" >
            </td>                        
        </tr>
        
        <tr height="5%">
            <td width="15%" height="5%" valign="top" style="font-weight: bold;" class="alterCell">Fine Each Day ( Rs. )</td>
            
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[0]]['4'] ?>" id="admin_fine" name="admin_fine" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[0] ;?>" name="ADMIN">
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[1]]['4'] ?>" id="stu_fine" name="stu_fine" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[1] ;?>"  name="STUDENT" >
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[2]]['4'] ?>" id="teach_fine" name="teach_fine" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[2] ;?>" name="STAFF" >
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[3]]['4'] ?>"  id="non_teach_fine" name="non_teach_fine" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[3] ;?>" name="EMPLOYEE" >
            </td>
            <td width="15%" valign="top" style="font-weight: bold;" class="alterCell">
                <input onblur="return numericcheck(this.name);" onkeyup="return checkspecialcharacterdynamic(this.name);" onchange="maxlength(this.name,4);" type="text" maxlength="50" style="width: 140px;" value="<?php echo $record_data[$user[4]]['4'] ?>" id="parant_fine" name="parant_fine" disabled="" onblur="javascript:return valid();"  />
                <input type="hidden" value="<?php echo $user[4] ;?>" name="PARENTS" >
            </td>                        
        </tr>

    
        </table>
