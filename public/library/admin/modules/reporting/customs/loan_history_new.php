<?php
session_start();
require '../../../../sysconfig.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
//require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';

$can_read = utility::havePrivilege('circulation', 'r') || utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('circulation', 'w') || utility::havePrivilege('reporting', 'w');

if (!$can_read) 
{
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MODULES_BASE_DIR.'reporting/report_dbgrid.inc.php';
?>
<script type="text/javascript">
function b(id,gmd_main,continue_loan)
{    
        window.open ('<?php MODULES_WEB_ROOT_DIR?>test.php?id='+id+"&gmd_main="+gmd_main+"&continue_loan="+continue_loan,"mywindow","status = 1, height = 300, width = 300, resizable = 0" );  
}
function c(id,gmd_main,renewed)
{ 
    window.open ('<?php MODULES_WEB_ROOT_DIR ?>test.php?id='+id+"&gmd_main="+gmd_main+"&renew="+renewed,"mywindow","status = 1, height = 300, width = 300, resizable = 0" );  
}

function d(id,gmd_main,over_due)
{ 
    window.open ('<?php MODULES_WEB_ROOT_DIR?>test.php?id='+id+"&gmd_main="+gmd_main+"&over_due="+over_due,"mywindow","status = 1, height = 300, width = 300, resizable = 0" );   
}
</script>
<?php
$page_title = 'Loan History Report';
$reportView = false;
$num_recs_show = 10;
if (isset($_GET['reportView'])) 
{
    $reportView = true;
}

if (!$reportView) 
{
?>
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
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'reporting/customs/loan_history_new.php class="headerText2">Issue Summary Report</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
    <!-- filter -->
    <fieldset  style="margin-bottom: 3px;">
    <legend style="font-weight: bold"><?php echo strtoupper(gettext('Issue Summary Report')); ?> - <?php echo gettext('Report Filter'); ?></legend>
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
    <div id="filterForm">
        <table width='100%'>
            <tr>
                
                <td width="25%">
                    <div class="divRow">
                        <?php //echo __('GMD'); ?>
                            <div class="divRowLabel"><?php echo gettext('Report Type :-  '); ?>
                            <?php
                                /*$gmd_q = $dbs->query('SELECT gmd_id, gmd_name FROM mst_gmd');
                                $gmd_options[] = array('0', __('ALL'));
                                while ($gmd_d = $gmd_q->fetch_row()) 
                                {
                                    $gmd_options[] = array($gmd_d[0], $gmd_d[1]);
                                }*/
                            
                                $gmd_options[] = array(' ', gettext('--Select Resource Type--'));
                                $gmd_options[] = array('0', gettext('Resource Type'));
                                $gmd_options[] = array('1', gettext('Material Type'));
                                $gmd_options[] = array('2', gettext('Material Sub Type'));
                                /*while ($gmd_d = $gmd_q->fetch_row()) 
                                {
                                    $gmd_options[] = array($gmd_d[0], $gmd_d[1]);
                                }*/
                                echo simbio_form_element::selectList('gmd_main', $gmd_options, '','width=50px;');
                                //,'multiple="multiple" size="5"'
                        ?> 
                            <?php// echo __('Press Ctrl and click to select multiple entries'); ?>
                           </div>
                      </div>
                </td>
                
                <td width='25%'>
                    
                    <div class="divRow">
                            <div class="divRowLabel"><?php // echo __('Member ID').''.__(':'); ?>
                           
                            <?php            
                           // echo simbio_form_element::textField('text', 'id_name', '', 'style="width:  153px;"');
                            ?>
                            </div>
                    </div>
                    
                </td>
                <td width='25%'>
                       <div class="divRow">
                        <div class="divRowLabel"><?php //echo __('Title :'); ?>
                           
                            <?php                            
                        //    echo '<br>';
                          //  echo simbio_form_element::textField('text', 'title', '', 'style="width: 180px"');
                            ?>  
                        </div>
                        </div>                                               
                </td>
                <td width='25%'>
                     <div class="divRow">
                        <div class="divRowLabel"><?php //echo __('Item Code'); ?>
                            <!--</div>
                            <div class="divRowContent">-->
                         <?php
                            
                       //     echo simbio_form_element::textField('text', 'itemCode', '', 'style="width: 140px"');
                          ?>
                        </div>
                        </div>
                    
                </td>
            </tr>
            
            <tr>
              <!--  <td width='25%'>
                     <div class="divRow" style="float:left;" >
                        <div class="divRowLabel"><?php //echo __('From'); ?></div>
                            <div class="divRowContent">
                            <?php                              
                          /*  $temp_date=explode('-', date('d-m-Y'));
                            $temp_date1=$temp_date[2]-1;
                            $from_date=$temp_date[0].'-'.$temp_date[1].'-'.$temp_date1;                            
                                echo simbio_form_element::dateField('startDate', date('d-m-Y',strtotime($from_date)));
                            
                           * */?>
                           */
                            </div>
                      </div>
                </td>-->
               <!-- <td width='25%'>
                    <div class="divRow" style="float:left;">            
                    <div class="divRowLabel"><?php //echo __('To'); ?></div>
                     <div class="divRowContent">
                        <?php
                         //   echo simbio_form_element::dateField('untilDate', date('d-m-Y'));
                        ?>
                       </div>
                     </div>
                </td> -->
            </tr>
            <tr>
              <!--  <td width='25%' >
                    
                    <div class="divRow" style="float:left;">
                     <div class="divRowLabel"><?php //echo __('Report Type'); ?></div>
                        <div class="divRowContent">
                            <select name="report_type" onchange="loantable_report(this.value)" style="width:99%">                                                                                
                                <option value="ALL"> <?php //echo __('ALL'); ?></option>
                                <option value="1"><?php //echo __('Resource Type Wise'); ?></option>
                                <option value="2"><?php //echo __('Material Type Wise'); ?></option>
                                <option value="3"><?php// echo __('Material Sub Type Wise'); ?></option>                                
                            </select>
                        </div>
                     </div>   
                </td>-->
                
               <!-- <td width='25%'>
                    <div class="divRow" style="float:left;">
                     <div class="divRowLabel"><?php //echo __('Loan Status'); ?></div>
                        <div class="divRowContent">
                            <select name="loanStatus" style="width:120%">
                                <option value="ALL"><?php // echo __('ALL'); ?></option>
                                <option value="0"><?php // echo __('Loan Continue'); ?></option>
                                <option value="1"><?php //echo __('Loan Complete'); ?></option>
                            </select>
                        </div>
                     </div>
                </td>-->
                
                <td width='35%'>
                    <table>
                        <tr>
                            <td>
                                
                                <div class="divRow"  width='12%'>
                        <div class="divRowLabel">
                                <?php // echo __('Renew Status'); ?>
                        </div>
                                    <!--
                        <div class="divRowContent">
                            <select name="renew">
                                <option value="ALL"><?php // echo __('No'); ?></option>
                                <option value="1"><?php //echo __('Yes'); ?></option>                                
                            </select>
                        </div>
                                    -->
                     </div>  
                            </td>
                            <td>
                                  <div class="divRow"  width='12%'>
                     <div class="divRowLabel"><?php //echo __('Over Due Status'); ?></div>
                       <!--
                     <div class="divRowContent">
                            <select name="overdue">
                                <option value="ALL"><?php // echo __('No'); ?></option>
                                <option value="1"><?php // echo __('Yes'); ?></option>                                
                            </select>
                        </div>
                     -->
                     </div>  
                            </td></tr>
                    </table>
                      
                    
                                           
                
                
                </td>
                
                <!--<td>-->
                   <!-- <div class="divRow" style="float:left;">
                    <div class="divRowLabel"><?php// echo __('Record each page'); ?></div>
                    <div class="divRowContent"><input type="text" name="recsEachPage" size="3" maxlength="3" value="<?php echo $num_recs_show; ?>" /> <?php echo gettext('Set between 20 and 200'); ?></div>
                    </div>-->
                <!--</td>-->
            </tr>
        </table>                                                                                                                                                               
    </div>
    <div style="padding-top: 10px; clear: both;">
    <input type="submit" name="applyFilter" value="<?php echo gettext('Generate Report'); ?>" />
    <!--<input type="button" name="moreFilter" value="<?php //echo __('Advance Search'); ?>" onclick="showHideTableRows('filterForm', 1, this, '<?php //echo __('Show More Search Options'); ?>', '<?php //echo __('Hide Search Options'); ?>')" />-->
    <input type="hidden" name="reportView" value="true" />
    </div>
    </form>
    </fieldset>

   <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: 600px;"></iframe>
<?php
} 
else 
{
    ob_start();
    // create datagrid
    $reportgrid = new report_datagrid();
    /*$reportgrid->setSQLColumn('b.biblio_id', 'b.title AS \''.__('Title').'\'', 'COUNT(item_id) AS '.__('Copies').'',
        'b.isbn_issn AS \''.__('ISBN/ISSN').'\'',
        'b.call_number AS \''.__('Call Number').'\'');*/
    //$reportgrid->setSQLColumn('b.biblio_id', 'b.title AS \''.__('Title').'\'', 'COUNT(item_id) AS '.__('Total_Item').'',
      //  'b.isbn_issn AS \''.__('ISBN/ISSN').'\'');
 // $reportgrid->setSQLColumn('b.biblio_id', 'b.title AS \''.__('Title').'\'', 'COUNT(item_id) AS '.__('Total_Item'));     
 
    if (isset($_GET['recsEachPage'])) 
    {
        $recsEachPage = (integer)$_GET['recsEachPage'];
        $num_recs_show = ($recsEachPage >= 20 && $recsEachPage <= 200)?$recsEachPage:$num_recs_show;
    }
    
      if ($_GET['gmd_main']==' ' OR !isset($_GET['gmd_main']))
     {       
         echo " ";
     } 
   
    elseif ($_GET['gmd_main']=='0')
    {        
       $subquery_str = '( 								
SELECT  material , biblio , item , issue , available,m_id FROM							
(																
        SELECT mrt.material_resource_id m_id,mg.gmd_name material , b.biblio_id biblio , if(i.item_code is null , 0 , i.item_code) item ,																
               l.item_code , if(l.item_code is not null and l.return_date is null , 1 , 0) issue , 																
               if((l.item_code is not null and l.return_date is null) or ms.no_loan = 1 or i.item_code is null  ,0, i.item_code ) available , 																
               l.return_date , ms.no_loan																
        FROM biblio b 																
        left join item i on i.biblio_id = b.biblio_id																
        left join loan l on l.item_code = i.item_code																
        left join mst_item_status ms on ms.item_status_id = i.item_status_id																
        inner join mst_material_resource_type mrt on mrt.material_resource_id = b.material_resource_id																
        inner join mst_gmd mg on mg.gmd_id = b.gmd_id																
        inner join mst_material_sub_type mst on mst.material_sub_id = b.material_sub_id																
       ) X								
      ) Y';								
    }
    elseif($_GET['gmd_main']=='1')
    {
        $subquery_str ='( 																
SELECT material , biblio , item , issue , available,gmd_id																
FROM  (																								
        SELECT mg.gmd_id gmd_id,mg.gmd_name material , b.biblio_id biblio , if(i.item_code is null , 0 , i.item_code) item ,																								
               l.item_code , if(l.item_code is not null and l.return_date is null , 1 , 0) issue , 																								
               if((l.item_code is not null and l.return_date is null) or ms.no_loan = 1 or i.item_code is null  ,0, i.item_code ) available , 																								
               l.return_date , ms.no_loan																								
        FROM biblio b 																								
        left join item i on i.biblio_id = b.biblio_id																								
        left join loan l on l.item_code = i.item_code																								
        left join mst_item_status ms on ms.item_status_id = i.item_status_id																								
        inner join mst_material_resource_type mrt on mrt.material_resource_id = b.material_resource_id																								
        inner join mst_gmd mg on mg.gmd_id = b.gmd_id																								
        inner join mst_material_sub_type mst on mst.material_sub_id = b.material_sub_id																	
       ) X																
      ) Y';							

    }
    elseif($_GET['gmd_main']=='2')
    {
         $subquery_str ='( 																
SELECT material , biblio , item , issue , available ,ms_id																
FROM  (																								
        SELECT mst.material_sub_id ms_id,mst.material_sub_name material , b.biblio_id biblio , if(i.item_code is null , 0 , i.item_code) item ,																								
               l.item_code , if(l.item_code is not null and l.return_date is null , 1 , 0) issue , 																								
               if((l.item_code is not null and l.return_date is null) or ms.no_loan = 1 or i.item_code is null  ,0, i.item_code ) available , 																								
               l.return_date , ms.no_loan																								
        FROM biblio b 																								
        left join item i on i.biblio_id = b.biblio_id																								
        left join loan l on l.item_code = i.item_code																								
        left join mst_item_status ms on ms.item_status_id = i.item_status_id																								
        inner join mst_material_resource_type mrt on mrt.material_resource_id = b.material_resource_id																								
        inner join mst_gmd mg on mg.gmd_id = b.gmd_id																								
        inner join mst_material_sub_type mst on mst.material_sub_id = b.material_sub_id	and mrt.material_resource_id=14	and mst.material_sub_id!=131 and mst.material_sub_id!=133																							
       ) X																
      ) Y';								

    }
        

     

    
   // table spec
    
    $table_spec = $subquery_str;
    $reportgrid->sql_group_by = 'material';
      /*.' AS b
        LEFT JOIN item AS i ON b.biblio_id=i.biblio_id';*/

    // set group by
    //$reportgrid->sql_group_by = 'b.biblio_id';
    if($_GET['gmd_main']=='0')
 {      
   $reportgrid->setSQLColumn('material',
         'count(distinct biblio) AS \''.gettext('Total_Title').'\'',
         'IF( item = 0 , 0 , count(distinct item)) AS '.gettext('Total_Item'),
         'sum(issue) AS \''.gettext('Total_Issue').'\'', 
         'IF( available = 0 , 0 , (count(distinct available)))-1 AS '.gettext('Total_available'), 
         '((IF( item = 0 , 0 , count(distinct item))) - sum(issue) - 
             IF( available = 0 , 0 , (count(distinct available)))-1) AS \''.gettext('Total_Unavailable').'\''   ,
         'm_id AS \''.('ID').'\''
         );   
 
    $reportgrid->setSQLorder('available DESC');
 }
 elseif($_GET['gmd_main']=='1')
 {  
     $reportgrid->setSQLColumn('material',
         'count(distinct biblio) AS \''.gettext('Total_Title').'\'',
         'IF( item = 0 , 0 , count(distinct item)) AS '.gettext('Total_Item'),
         'sum(issue) AS \''.gettext('Total_Issue').'\'', 
         'IF( available = 0 , 0 , (count(distinct available)))-1 AS '.gettext('Total_available'), 
         '((IF( item = 0 , 0 , count(distinct item))) - sum(issue) - 
             IF( available = 0 , 0 , (count(distinct available)))-1) AS \''.gettext('Total_Unavailable').'\''   ,
         'gmd_id AS \''.('ID').'\''  
);   
 
    $reportgrid->setSQLorder('available DESC');
 }   
 elseif($_GET['gmd_main']=='2')
 {
     $reportgrid->setSQLColumn('material',
         'count(distinct biblio) AS \''.gettext('Total_Title').'\'',
         'IF( item = 0 , 0 , count(distinct item)) AS '.gettext('Total_Item'),
         'sum(issue) AS \''.gettext('Total_Issue').'\'', 
         'IF( available = 0 , 0 , (count(distinct available)))-1 AS '.gettext('Total_available'), 
         '((IF( item = 0 , 0 , count(distinct item))) - sum(issue) - IF( available = 0 , 0 , (count(distinct available)))-1) AS \''.gettext('Total_Unavailable').'\'',
         'ms_id AS \''.('ID').'\''
         );   
 
    $reportgrid->setSQLorder('available DESC');
 }
     
   
    
   // $reportgrid->modifyColumnContent(6, 'callback{loanStatus}');
    if (isset($_GET['gmd_main']))
        $gmd_main=$_GET['gmd_main'];
    else
        $gmd_main=0;
    // put the result into variables
    echo $reportgrid->createDataGrid_Custom($gmd_main,$dbs, $table_spec, $num_recs_show);
    // print_r($reportgrid);
// exit;
    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'pagingBox\').update(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';

    $content = ob_get_clean();
    // include the page template
    require SENAYAN_BASE_DIR.'/admin/admin_template/printed_page_tpl.php';
}

  
?>