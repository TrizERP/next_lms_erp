<?php

require '../../../sysconfig.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';

 
?>
<br/>

<table  align=center>
<tr>
	<td valign=top>
	<?php
	$bradecum = '';       
        $basedir = basename(dirname(__FILE__));
      $bradecum = "<a href=javascript:void(0); onclick=javascript:new_set_home(); >Home</a>-><a class='' href=javascript:void(0); onclick=javascript:new_set('reporting');>"; 
	$query = "select module_name from mst_module where module_path = 'reporting'";
	$set_query = $dbs->query($query);
	while($row=$set_query->fetch_assoc())
	{
                $_formated_module_name = ucwords(str_replace('_', ' ', $row['module_name']));
		$bradecum .= $_formated_module_name;
	}
$bradecum .= '</a>->';
/*if(isset($_REQUEST['action']))
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php?action=detail class="headerText2">Add New Member</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php class="headerText2">View Member List</a>';
}*/
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/category_master.php class="headerText2">category master</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
<table>
<tr>
	<td class="tab_menu_top">
                            <ul class="tabs"> 
				<li>
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>membership/category_master.php" class="headerText2"><?php echo __('Category Master'); ?></a> </li>
                            </ul>
        </td>
</tr>
</table>
                                <fieldset class="menuBox">
<div class="menuBoxInner importIcon">
    <?php echo __('Category Master'); ?>
    <p class="only_border">&nbsp;</p>    
</div>
</fieldset>
<form enctype="multipart/form-data" method="post" id="myform" name="myform" action="index.php?mod=membership">

    <table cellspacing="0" cellpadding="3" style="width: 100%; background-color: rgb(255, 255, 255);">
        <tbody>
            <tr>
                <td>
                    <a class="notAJAX editFormLink" href="#">EDIT</a>
                    <input onclick="return show_confirm();" type="submit" class="button" value="Update" name="saveData" disabled=""/> 
                    <input type="hidden" value="category_user" name="category_user" />
                </td>
                <td align="right"/>
            </tr>
        </tbody>
    </table>
   
   
    <table cellspacing="0" cellpadding="5" align="center" id="dataList" >
    <tbody>       
        <tr>
            <td width="20%" valign="top" style="font-weight: bold;" class="alterCell">Material Code</td>
            <td width="1%" valign="top" style="font-weight: bold;" class="alterCell">:</td>
            <td class="alterCell2">Physical Resources</td>                                                                 

        </tr>
        <tr>
            <td width="20%" valign="top" style="font-weight: bold;" class="alterCell">Material Type*</td>
            <td width="1%" valign="top" style="font-weight: bold;" class="alterCell">:</td>
            <td width="79%" class="alterCell2">                              
                <select name="sub_type" id="sub_type" onChange="generatetable(this.value);">                    
                    <option value="130">Books</option>
				<!--<option value="131">CD</option>
                    <option value="132">Magazines</option>
                    <option value="133">News Paper</option>-->
                </select> 
            </td>
        </tr>    
        </tbody>        
        </table>
     <table width="100%" id="maintable" name="maintable">                       
            <tr  align="center" height="10%">
            <td width="20%"  height="10%" valign="top" style="font-weight: bold;" class="alterCell">&nbsp;</td>                
                <?php
                
                  // $qry="SELECT * FROM ".$inte_schema.".tbluser_groups where user_group_id in(1,2,3,4) order by sort_order";
				  $qry="SELECT * FROM ".$inte_schema.".tbluserprofilemaster where sub_institute_id = '".$_SESSION['SUB_INSTITUTE_ID']."' order by sort_order";
                  
                  $user=array();
                  $user_name=array();
                  $category_user = $dbs->query($qry);  
                  if ($category_user->num_rows>0)
                  {
                      while ($row = $category_user->fetch_assoc())
                        {
                            $user[]=$row['id'];
                            $user_name[]=$row['name'];

                ?>             
                <td width="15%"   valign="top" style="font-weight: bold;" class="alterCell"><?php echo $row['name']; ?></td>
                <?php
                }

                  }     
                  
                  
	$query="SELECT * FROM category_mast WHERE material_sub_id=130 ";                        
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


       <br style="clear:both;"/>
    <div id="divContent" name="divContent"></div>
<table cellspacing="0" cellpadding="3" style="width: 100%; background-color: rgb(255, 255, 255);">
    <tbody>
        <tr>
            <td>
                <a class="notAJAX editFormLink" href="#">EDIT</a> 
                
                <input onclick="return show_confirm();" type="submit" class="button" value="Update" name="saveData" disabled="" onclick="Validator('myform')"/>
                <input type="hidden" value="category_user" name="category_user" />
            </td>
            <td align="right"/>
        </tr>
    </tbody>
</table>
<input type="hidden" value="3" id="updateRecordID" name="updateRecordID" disabled="" />
</form>


<script language="JavaScript" type="text/javascript"   xml:space="preserve">
    
  /*var id_name1;
  function validation(id_name)
    {
     id_name1=id_name;
    }  */
  var frmvalidator  = new Validator("myform"); 
  
  /*frmvalidator.addValidation("admin_issuelimit","req","Please enter Issue Limit");    
  frmvalidator.addValidation("admin_issuelimit","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("stu_issuelimit","req","Please enter Issue Limit");    
  frmvalidator.addValidation("stu_issuelimit","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("teach_issueperiod","req","Please enter Issue Limit");    
  frmvalidator.addValidation("teach_issueperiod","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("non_tech_issuelimit","req","Please enter Issue Limit");    
  frmvalidator.addValidation("non_tech_issuelimit","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("parnt_issuelimit","req","Please enter Issue Limit");    
  frmvalidator.addValidation("parnt_issuelimit","numeric","Please Enter Numeric Value");
  
  
  frmvalidator.addValidation("admin_issueperiod","req","Please enter Issue Limit");    
  frmvalidator.addValidation("admin_issueperiod","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("stu_issueperiod","req","Please enter Issue Limit");    
  frmvalidator.addValidation("stu_issueperiod","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("teach_issueperiod","req","Please enter Issue Limit");    
  frmvalidator.addValidation("teach_issueperiod","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("non_tech_issueperiod","req","Please enter Issue Limit");    
  frmvalidator.addValidation("non_tech_issueperiod","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("parnt_issueperiod","req","Please enter Issue Limit");    
  frmvalidator.addValidation("parnt_issueperiod","numeric","Please Enter Numeric Value");

     
  
  frmvalidator.addValidation("admin_reissuelimit","req","Please enter Issue Limit");    
  frmvalidator.addValidation("admin_reissuelimit","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("stu_reissuelimit","req","Please enter Issue Limit");    
  frmvalidator.addValidation("stu_reissuelimit","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("teach_reissuelimit","req","Please enter Issue Limit");    
  frmvalidator.addValidation("teach_reissuelimit","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("non_teach_reissuelimit","req","Please enter Issue Limit");    
  frmvalidator.addValidation("non_teach_reissuelimit","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("parant_reissuelimit","req","Please enter Issue Limit");    
  frmvalidator.addValidation("parant_reissuelimit","numeric","Please Enter Numeric Value");

  
     
  frmvalidator.addValidation("admin_fine","req","Please enter Issue Limit");    
  frmvalidator.addValidation("admin_fine","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("stu_fine","req","Please enter Issue Limit");    
  frmvalidator.addValidation("stu_fine","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("teach_fine","req","Please enter Issue Limit");    
  frmvalidator.addValidation("teach_fine","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("non_teach_fine","req","Please enter Issue Limit");    
  frmvalidator.addValidation("non_teach_fine","numeric","Please Enter Numeric Value");
  
  frmvalidator.addValidation("parant_fine","req","Please enter Issue Limit");    
  frmvalidator.addValidation("parant_fine","numeric","Please Enter Numeric Value");
*/
      
</script>