    <?php
// main system configuration
error_reporting(E_ALL);

ini_set('display_errors',1);
session_start();
require '../../../../sysconfig.inc.php';


// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
//require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
// privileges checking
$can_read = utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('reporting', 'w');

if (!$can_read) 
{
     die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
//require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MODULES_BASE_DIR.'reporting/report_dbgrid.inc.php';

?>

<script type="text/javascript">
function b(id,gmd_main,continue_loan)
{    
        window.open ('<?php MODULES_WEB_ROOT_DIR?>test.php?id='+id+"&gmd_main="+gmd_main+"&continue_loan="+continue_loan,"mywindow","status = 1, height = 300, width = 700, resizable = 0" );  
}
function c(id,gmd_main,renewed)
{ 
    window.open ('<?php MODULES_WEB_ROOT_DIR ?>test.php?id='+id+"&gmd_main="+gmd_main+"&renew="+renewed,"mywindow","status = 1, height = 300, width = 700, resizable = 0" );  
}

function d(id,gmd_main,over_due)
{ 
    window.open ('<?php MODULES_WEB_ROOT_DIR?>test.php?id='+id+"&gmd_main="+gmd_main+"&over_due="+over_due,"mywindow","status = 1, height = 300, width = 700, resizable = 0" );   
}
</script>

<?php

$page_title = 'Titles Report';
$reportView = false;
$num_recs_show = 10;

if (isset($_GET['reportView'])) {
    $reportView = true;
}

if (!$reportView) {
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
//if(isset($_REQUEST['action']))
//{
//$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php?action=detail class="headerText2">Add New Member</a>';
//}
//else
//{
//$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php class="headerText2">View Member List</a>';
//}
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'reporting/customs/title_list.php class="headerText2">Catalog Statistics</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
    <!-- filter -->
<!--style="margin-bottom: 3px;" -->
                <br>
     <fieldset>
    <legend style="font-weight: bold">
            <?php echo strtoupper(gettext('Title List')); ?> - <?php echo gettext('Report Filter'); ?>
    </legend>
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
    <div id="filterForm">        
        <table width='70%'>
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
                <td>
                    <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Record each page'); ?></div>
            <div class="divRowContent"><input type="text" name="recsEachPage" size="3" maxlength="3" value="<?php echo $num_recs_show; ?>" /> <?php echo gettext('Set between 20 and 200'); ?></div>
        </div>
                </td>
                                              
            </tr>

            
            
            <tr>
            
                

 <?php
//echo __('Material Type : ');
 
 $material_q = $dbs->query('SELECT material_resource_id, material_resource_name FROM mst_material_resource_type where active_inactive="1"');
	//$gmd_options='';
       $material_options = array('--Material Resource Type--');
        while ($material_d = $material_q->fetch_row()) 	
	{
            $material_options[] = array($material_d[0], $material_d[1]);		
        }
        
$ajax_exp1 = "ajaxFillSelectnew('".SENAYAN_WEB_ROOT_DIR."admin/modules/reporting/customs/catalog_static_report.php','materialnewid1', $('materialsubid').getValue())"; 
        
$ajax = "a('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_material_sub_type_handler_report.php', 'mst_gmd', 'gmd_id:gmd_name:material_resource_id', 'gmdID', $('materialresourceid').getValue())";


       if (isset($rec_d['gmd_name']) && $rec_d['gmd_name'])
       {
            $mst_options[] = array($rec_d['gmd_id'],$rec_d['gmd_name']);
       }
	$mst_options[] = array('0', gettext('--Material Type--'));


    $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_material_sub_type_handler.php', 'mst_material_sub_type', 'material_sub_id:material_sub_name:gmd_id', 'materialsubid', $('gmdID').getValue())";

       if (isset($rec_d['material_sub_name']) && $rec_d['material_sub_name']) 
       {
            $mst_material_sub_type_options[] = array($rec_d['material_sub_id'],$rec_d['material_sub_name']);
       }
	$mst_material_sub_type_options[] = array('0', gettext('--Material Sub Type--'));
    
//echo simbio_form_element::selectList('materialresourceid',$material_options, $rec_d['material_resource_id'],'onchange="'.$ajax.'"');
        
//echo simbio_form_element::selectList('materialresourceid',$material_options, $rec_d['material_resource_id'],'onchange="'.$ajax.'"');        
//echo simbio_form_element::selectList('gmdId','','','id=gmdId');

//echo '<input type="text" id="b" name="b" value=""/>';
//echo '<div id="gmdId" name="gmdId"></div>';
//echo '<select id="gmdId" name="gmdId" ></select>';
//echo simbio_form_element::selectList('gmdID', $mst_options, $rec_d['gmd_id'],'onchange="'.$ajax_exp.'"');
//echo '&nbsp;';
//echo simbio_form_element::selectList('materialsubid', $mst_material_sub_type_options, $rec_d['material_sub_id'], 'onchange="'.$ajax_exp1.'"');


//,'style="width: 25%;"'

//$str_input .= '<br><tr><td id="materialnewid1" class="alterCell2" colspan="3"></td></tr>';
//$form->addAnything(__('Material Type'), $str_input);

 ?> 
           
                
            </tr>
        </table>        
    </div>
    <div style="padding-top: 10px; clear: both;">
    <input type="submit" name="applyFilter" value="<?php echo gettext('Generate Report'); ?>" />
<!--    &nbsp;&nbsp;&nbsp;<b><font size="2.5px">Set Records Per Page:</font></b><input type="checkbox" id="chk" onclick="fnchecked(this.checked);"/>
           <input type="text" size="2" id="disptxt" name="disptxt" style="display:none"/>-->
<!--           <input type="button" name="moreFilter" value="<?php  echo gettext('Advance Search'); ?>" onclick="showHideTableRows('filterForm', 1, this, '<?php echo gettext('Show More Search Options'); ?>', '<?php echo gettext('Hide Search Options'); ?>')" />-->
    <input type="hidden" name="reportView" value="true" />
    </div>
    </form>
    </fieldset>
    <script type="text/javascript">hideRows('filterForm', 1);</script>
    <!-- filter end -->
    <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: 600px;"></iframe>

<?php
// echo $_REQUEST['disptxt'];die;
} else {

    ob_start();
    // create datagrid
    $reportgrid = new report_datagrid();
    /*$reportgrid->setSQLColumn('b.biblio_id', 'b.title AS \''.__('Title').'\'', 'COUNT(item_id) AS '.__('Copies').'',
        'b.isbn_issn AS \''.__('ISBN/ISSN').'\'',
        'b.call_number AS \''.__('Call Number').'\'');*/
    //$reportgrid->setSQLColumn('b.biblio_id', 'b.title AS \''.__('Title').'\'', 'COUNT(item_id) AS '.__('Total_Item').'',
      //  'b.isbn_issn AS \''.__('ISBN/ISSN').'\'');
 // $reportgrid->setSQLColumn('b.biblio_id', 'b.title AS \''.__('Title').'\'', 'COUNT(item_id) AS '.__('Total_Item'));     
    
// $reportgrid->setSQLColumn('material',
//         'count(distinct biblio) AS \''._('Total_Title').'\'',
//         'IF( item = 0 , 0 , count(distinct item)) AS '.__('Total_Item'),
//         'sum(issue) AS \''.__('Total_Issue').'\'', 
//         'IF( available = 0 , 0 , (count(distinct available)-2)) AS '.__('Total_available'), 
//         '((IF( item = 0 , 0 , count(distinct item))) - sum(issue) - IF( available = 0 , 0 , (count(distinct available)-2))) AS \''.__('Total_Unavailable').'\'');         
// 
 
 	/*SELECT material ,count(distinct biblio) tot_title  , sum(item) tot_item , sum(issue) tot_issue ,						
	       sum(available) tot_available , (sum(item)- sum(issue) - sum(available)) tot_noavailable    */
if($_GET['gmd_main']=='0')
 {      
   $reportgrid->setSQLColumn('material',
         'count(distinct biblio) AS \''.gettext('Total_Title').'\'',
         'IF( item = 0 , 0 , count(distinct item)) AS '.gettext('Total_Item'),
         'sum(issue) AS \''.gettext('Total_Issue').'\'', 
         'IF( item = 0 , 0 , count(distinct item)) AS '.gettext('Total_available'),
         '((IF( item = 0 , 0 , count(distinct item))) - sum(issue) - IF( available = 0 , 0 , (count(distinct available)))-1) AS \''.gettext('Total_Unavailable').'\'',
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
         'IF( item = 0 , 0 , count(distinct item)) AS '.gettext('Total_available'), 
         '((IF( item = 0 , 0 , count(distinct item))) - sum(issue) - IF( available = 0 , 0 , (count(distinct available)))-1) AS \''.gettext('Total_Unavailable').'\''   ,
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
         'IF( item = 0 , 0 , count(distinct item)) AS '.gettext('Total_available'),
         '((IF( item = 0 , 0 , count(distinct item))) - sum(issue) - IF( available = 0 , 0 , (count(distinct available)))-1) AS \''.gettext('Total_Unavailable').'\'',
         'ms_id AS \''.('ID').'\''
         ); 
 
    $reportgrid->setSQLorder('available DESC');
 }
     
   
//'IF( available = 0 , 0 , (count(distinct available)))-1  AS '.__('Total_available'),  
 
//added and commented started by Parth 23/8/2011
    //$reportgrid->setSQLorder('b.title ASC');
//    $reportgrid->setSQLorder('material');
//added and commented ended by Parth 23/8/2011	
//    $reportgrid->invisible_fields = array(0);

    // is there any search
   // $criteria = 'bsub.biblio_id IS NOT NULL ';
   // $outer_criteria = 'b.biblio_id > 0 ';
   /* if (isset($_GET['title']) AND !empty($_GET['title'])) 
    {
        $keyword = $dbs->escape_string(trim($_GET['title']));
        $words = explode(' ', $keyword);
        if (count($words) > 1) 
        {
            $concat_sql = ' AND (';
            foreach ($words as $word) 
            {
                $concat_sql .= " (bsub.title LIKE '%$word%' OR bsub.isbn_issn LIKE '%$word%') AND";
            }
            // remove the last AND
            $concat_sql = substr_replace($concat_sql, '', -3);
            $concat_sql .= ') ';
            $criteria .= $concat_sql;
        }
        else 
        {
            $criteria .= ' AND (bsub.title LIKE \'%'.$keyword.'%\' OR bsub.isbn_issn LIKE \'%'.$keyword.'%\')';
        }
    }*/
   /* if (isset($_GET['author']) AND !empty($_GET['author'])) 
    {
        $author = $dbs->escape_string($_GET['author']);
        $criteria .= ' AND ma.author_name LIKE \'%'.$author.'%\'';
    }*/
   /* if (isset($_GET['class']) AND !empty($_GET['class']))
    {
        $class = $dbs->escape_string($_GET['class']);
        $criteria .= ' AND bsub.classification LIKE \''.$class.'%\'';
    }*/
   /* if (isset($_GET['gmd']) AND !empty($_GET['gmd'])) 
    {
       /*utility::jsAlert('hello') ;
       exit();*/
       /* $gmd_IDs = '';
        foreach ($_GET['gmd'] as $id) 
        {
            $id = (integer)$id;
            if ($id) 
            {
                $gmd_IDs .= "$id,";
            }
        }
        $gmd_IDs = substr_replace($gmd_IDs, '', -1);
        if ($gmd_IDs) 
        {
            $outer_criteria .= " AND b.gmd_id IN($gmd_IDs)";
        }
        
    }*/
    /*if (isset($_GET['language']) AND !empty($_GET['language'])) 
    {
        $language = $dbs->escape_string(trim($_GET['language']));
        $criteria .= ' AND bsub.language_id=\''.$language.'\'';
    }*/
    /*if (isset($_GET['location']) AND !empty($_GET['location'])) 
    {
        $location = $dbs->escape_string(trim($_GET['location']));
        $outer_criteria .= ' AND i.location_id=\''.$location.'\'';
    }*/
    if (isset($_GET['recsEachPage'])) 
    {
        $recsEachPage = (integer)$_GET['recsEachPage'];
        $num_recs_show = ($recsEachPage >= 20 && $recsEachPage <= 200)?$recsEachPage:$num_recs_show;
    }

    // subquery/view string
  /*  $subquery_str = '(SELECT DISTINCT bsub.biblio_id, bsub.gmd_id, bsub.title, bsub.isbn_issn, bsub.classification, bsub.language_id
        FROM biblio AS bsub
        LEFT JOIN biblio_author AS ba ON bsub.biblio_id = ba.biblio_id
        LEFT JOIN mst_author AS ma ON ba.author_id = ma.author_id
        LEFT JOIN biblio_topic AS bt ON bsub.biblio_id = bt.biblio_id
        LEFT JOIN mst_topic AS mt ON bt.topic_id = mt.topic_id WHERE '.$criteria.')';*/
    
    
     if ($_GET['gmd_main']==' ' OR !isset($_GET['gmd_main']))
     {       
         echo " ";
     } 
   
    elseif ($_GET['gmd_main']=='0')
    {        
      $subquery_str = '( 																
SELECT material , biblio , item , issue , available , m_id FROM							
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
SELECT material , biblio , item , issue , available , gmd_id 																
FROM  (																								
        SELECT mg.gmd_id gmd_id ,mg.gmd_name material , b.biblio_id biblio , if(i.item_code is null , 0 , i.item_code) item ,																								
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
SELECT material , biblio , item , issue , available , ms_id 																
FROM  (																								
        SELECT mst.material_sub_id ms_id ,mst.material_sub_name material , b.biblio_id biblio , if(i.item_code is null , 0 , i.item_code) item ,																								
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
    //$subquery_str .= " ORDER by available DESC";   

    //echo $subquery_str;//die();

    
   // table spec
    
    $table_spec = $subquery_str;
      /*.' AS b
        LEFT JOIN item AS i ON b.biblio_id=i.biblio_id';*/

    // set group by
    //$reportgrid->sql_group_by = 'b.biblio_id';
     //$reportgrid->setSQLorder('available DESC');
     $reportgrid->sql_group_by = 'material';
   // echo "<pre>";
   // echo $table_spec.$reportgrid->sql_group_by;die;
   // echo "<pre>";
    //$reportgrid->setSQLCriteria($outer_criteria);
	
    
    function showTitleAuthors($obj_db, $array_data)
    {
       
      /*  echo 'SELECT b.title, a.author_name FROM biblio AS b
            LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
            LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
            WHERE b.biblio_id='.$array_data[0];die; */
        
      /*  $_biblio_q = $obj_db->query('SELECT b.title, a.author_name FROM biblio AS b
            LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
            LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
            WHERE b.biblio_id='.$array_data[0]);
        
        $_authors = '';
        while ($_biblio_d = $_biblio_q->fetch_row()) 
        {
            $_title = $_biblio_d[0];
            $_authors .= $_biblio_d[1].' - ';
        }
        $_authors = substr_replace($_authors, '', -3);
        $_output = $_title.'<br /><i>'.$_authors.'</i>'."\n";
        return $_output;*/
    }
    
 //   $reportgrid->modifyColumnContent(1, 'callback{showTitleAuthors}');

    // put the result into variables
    if (isset($_GET['gmd_main']))
        $gmd_main=$_GET['gmd_main'];
    else
        $gmd_main=0;
    
   echo $reportgrid->createDataGrid_Custom($gmd_main,$dbs, $table_spec, $num_recs_show);
  //  echo $peging = '<div style="text-align: center;">'.simbio_paging::paging($reportgrid->num_rows, $num_recs_show, 5).'</div>';	
    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'pagingBox\').update(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';

    $content = ob_get_clean();
    // include the page template
    require SENAYAN_BASE_DIR.'admin/admin_template/printed_page_tpl.php';
}
?>
