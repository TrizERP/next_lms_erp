<?php
session_start();
/* Item Usage Statistic */

// main system configuration
require '../../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
// privileges checking
$can_read = utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('reporting', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MODULES_BASE_DIR.'reporting/report_dbgrid.inc.php';

$page_title = 'Item Usage Report';
$reportView = false;
if (isset($_GET['reportView'])) 
{
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
/*if(isset($_REQUEST['action']))
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php?action=detail class="headerText2">Add New Member</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php class="headerText2">View Member List</a>';
}*/
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'reporting/customs/item_usage.php class="headerText2">Item Usage</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
    <!-- filter -->
    <fieldset style="margin-bottom: 3px;">
    <legend style="font-weight: bold"><?php echo strtoupper(gettext('Items Usage Statistics')); ?> - <?php echo gettext('Report Filter'); ?></legend>
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
    <div id="filterForm">
        <table width='45%'>
            <tr>
                <td>
                        <div class="divRow">
                        <div class="divRowLabel"><?php echo gettext('Title/ISBN'); ?></div>
                        <div class="divRowContent">
                        <?php echo simbio_form_element::textField('text', 'title', '', 'style="width: 70%"'); ?>
                        </div>
                        </div>
                </td>
                <td>
                    <div class="divRow">
                    <div class="divRowLabel"><?php echo gettext('Item Code'); ?></div>
                    <div class="divRowContent">
                    <?php echo simbio_form_element::textField('text', 'itemCode', '', 'style="width: 70%"'); ?>
                    </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                     <div class="divRow" style="float:left;" >
                        <div class="divRowLabel"><?php echo gettext('From'); ?></div>
                            <div class="divRowContent">
                            <?php                              
                            $temp_date=explode('-', date('d-m-Y'));
                            $temp_date1=$temp_date[2]-1;
                            $from_date=$temp_date[0].'-'.$temp_date[1].'-'.$temp_date1;                            
                                echo simbio_form_element::dateField('startDate', date('d-m-Y',strtotime($from_date)));
                            ?>
                            </div>
                      </div>
                 <!--   <div class="divRow" style="float:left;" >
                        <div class="divRowLabel"><?php //echo __('From'); ?></div>
                            <div class="divRowContent">
                            <?php                              
                         //   $temp_date=explode('-', date('d-m-Y'));
                            //$temp_date1=$temp_date[2]-1;
                         //   $temp_date1=$temp_date[2];
                          //  $from_date=$temp_date[0].'-'.$temp_date[1].'-'.$temp_date1;                            
                            //    echo simbio_form_element::dateField('startDate', date('d-m-Y',strtotime($from_date)));
                            ?>
                            </div>
                      </div>-->
                </td>
                <td>
                    <div class="divRow" style="float:left;">            
                    <div class="divRowLabel"><?php  echo gettext('To'); ?></div>
                     <div class="divRowContent">
                        <?php
                            echo simbio_form_element::dateField('untilDate', date('d-m-Y'));
                        ?>
                       </div>
                     </div> 
                </td>
            </tr>
        </table>
      
        
                
                    
                    
        <div class="divRow">
            <div class="divRowLabel"><?php // echo __('Year'); ?></div>
            <div class="divRowContent">
            <?php
               
               /* $current_year = date('Y');
                $year_options = array();
                for ($y = $current_year; $y > 1999; $y--) 
                {
                    $year_options[] = array($y, $y);
                }
                echo simbio_form_element::selectList('year', $year_options, $current_year-1);
                */
            ?>
            </div>
        </div> 
    </div>
    <div style="padding-top: 10px; clear: both;">
    <input type="submit" name="applyFilter" value="<?php echo gettext('Search'); ?>" />
    <input type="hidden" name="reportView" value="true" />
    </div>
    </form>
    </fieldset>
    <!-- filter end -->
   <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: 600px;"></iframe>
<?php
} else {
    ob_start();
    // table spec
    $table_spec = 'item AS i
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id';

    // create datagrid
    $reportgrid = new report_datagrid();
    $reportgrid->setSQLColumn('i.item_code AS \''.gettext('Item Code').'\'',
        'b.title AS \''.gettext('Title').'\'',
        '\'01\' AS \''.gettext('Jan').'\'',
        '\'02\' AS \''.gettext('Feb').'\'',
        '\'03\' AS \''.gettext('Mar').'\'',
        '\'04\' AS \''.gettext('Apr').'\'',
        '\'05\' AS \''.gettext('May').'\'',
        '\'06\' AS \''.gettext('Jun').'\'',
        '\'07\' AS \''.gettext('Jul').'\'',
        '\'08\' AS \''.gettext('Aug').'\'',
        '\'09\' AS \''.gettext('Sep').'\'',
        '\'10\' AS \''.gettext('Oct').'\'',
        '\'11\' AS \''.gettext('Nov').'\'',
        '\'12\' AS \''.gettext('Dec').'\''
        );
    $reportgrid->setSQLorder('b.title ASC');

    // is there any search
    $criteria = 'b.biblio_id IS NOT NULL ';
    if (isset($_GET['title']) AND !empty($_GET['title'])) {
        $keyword = $dbs->escape_string(trim($_GET['title']));
        $words = explode(' ', $keyword);
        if (count($words) > 1) {
            $concat_sql = ' AND (';
            foreach ($words as $word) {
                $concat_sql .= " (b.title LIKE '%$word%' OR b.isbn_issn LIKE '$word%') AND";
            }
            // remove the last AND
            $concat_sql = substr_replace($concat_sql, '', -3);
            $concat_sql .= ') ';
            $criteria .= $concat_sql;
        } 
        else 
        {
            $criteria .= ' AND (b.title LIKE \'%'.$keyword.'%\' OR b.isbn_issn LIKE \''.$keyword.'%\')';
        }
    }
    if (isset($_GET['itemCode']) AND !empty($_GET['itemCode'])) {
        $item_code = $dbs->escape_string(trim($_GET['itemCode']));
        $criteria .= ' AND (i.item_code LIKE \'%'.$item_code.'%\')';
    }
    /*if (isset($_GET['startDate']) AND !empty($_GET['startDate']) AND isset($_GET['untilDate']) AND !empty($_GET['untilDate']))    */
    if (isset($_GET['year']) AND !empty($_GET['year']))    
    {
       // $selected_year = (integer)$_GET['year'];
    } 
    else 
    {
        //$selected_year = date('Y')-1;
    }
    
    $reportgrid->setSQLCriteria($criteria);

    // callback function to show overdued list
    function showUsage($obj_db, $array_data, $int_current_field_num)
    {                 
          global $selected_year;                       
      
        if (!isset($_GET['startDate']) and !isset($_GET['untilDate']))
        {
            $selected_year = '\''.date('Y-m-d').'\' AND \''.date('Y-m-d').'\'';
            $temp_date1=explode('-', date('d-m-Y'));        
        }
        else
        {
            $selected_year = '\''.date('Y-m-d',strtotime($_GET['startDate'])).'\' AND \''.date('Y-m-d',strtotime($_GET['untilDate'])).'\'';
            $temp_date1=explode('-', $_GET['startDate']);
        }    
                                                                               
   //      $a='SELECT COUNT(*) FROM loan AS l WHERE l.item_code=\''.$array_data[0].'\' AND l.loan_date LIKE \''.$selected_year.'-'.$array_data[$int_current_field_num].'%\'';
//$a='SELECT COUNT(*) FROM loan AS l WHERE l.item_code=\' '.$array_data[0].' \' AND l.loan_date BETWEEN \' '.$selected_year ;

$a="SELECT COUNT(*) FROM loan AS l WHERE l.item_code='$array_data[0]' AND l.loan_date BETWEEN $selected_year and l.loan_date like '$temp_date1[2]-$array_data[$int_current_field_num]%'";
       
//        $_usage_q = $obj_db->query('SELECT COUNT(*) FROM loan AS l
  //      WHERE l.item_code=\''.$array_data[0].'\' AND l.loan_date LIKE \''.$selected_year.'-'.$array_data[$int_current_field_num].'%\'');
      
       /*   $_usage_q = $obj_db->query('SELECT COUNT(*) FROM loan AS l
            WHERE l.item_code=\''.$array_data[0].'\' AND l.loan_date BETWEEN \''.$selected_year.'-'.$array_data[$int_current_field_num].'%\'');*/


              $_usage_q = $obj_db->query($a);
        
//        $_usage_q='SELECT COUNT(*) FROM loan AS l WHERE l.item_code=\''.$array_data[0].'\''.$selected_year;
         
  //      utility::jsAlert($_usage_q);
    //    exit();
        $_usage_d = $_usage_q->fetch_row();
        
        
        
        return ($_usage_d[0]=='0')?'<span style="color: #ff0000;">0</span>':'<strong>'.$_usage_d[0].'</strong>';
        
        
       /* global $selected_year;
        $_usage_q = $obj_db->query('SELECT COUNT(*) FROM loan AS l
            WHERE l.item_code=\''.$array_data[0].'\' AND l.loan_date LIKE \''.$selected_year.'-'.$array_data[$int_current_field_num].'%\'');
        $_usage_d = $_usage_q->fetch_row();
        return ($_usage_d[0]=='0')?'<span style="color: #ff0000;">0</span>':'<strong>'.$_usage_d[0].'</strong>';*/
    }
    // modify column value
    $reportgrid->modifyColumnContent(2, 'callback{showUsage}');
    $reportgrid->modifyColumnContent(3, 'callback{showUsage}');
    $reportgrid->modifyColumnContent(4, 'callback{showUsage}');
    $reportgrid->modifyColumnContent(5, 'callback{showUsage}');
    $reportgrid->modifyColumnContent(6, 'callback{showUsage}');
    $reportgrid->modifyColumnContent(7, 'callback{showUsage}');
    $reportgrid->modifyColumnContent(8, 'callback{showUsage}');
    $reportgrid->modifyColumnContent(9, 'callback{showUsage}');
    $reportgrid->modifyColumnContent(10, 'callback{showUsage}');
    $reportgrid->modifyColumnContent(11, 'callback{showUsage}');
    $reportgrid->modifyColumnContent(12, 'callback{showUsage}');
    $reportgrid->modifyColumnContent(13, 'callback{showUsage}');

    // no sort column
    $reportgrid->disableSort(gettext('Jan'), gettext('Feb'), gettext('Mar'), gettext('Apr'), gettext('May'), gettext('Jun'), gettext('Jul'), gettext('Aug'), gettext('Sep'), gettext('Oct'), gettext('Nov'), gettext('Dec'));

    // put the result into variables
    echo $reportgrid->createDataGrid($dbs, $table_spec, 10);
   //echo $peging = '<div style="text-align: center;">'.simbio_paging::paging($reportgrid->num_rows, 10, 5).'</div>';		
    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'pagingBox\').update(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';

    $content = ob_get_clean();
    // include the page template
    require SENAYAN_BASE_DIR.'/admin/admin_template/printed_page_tpl.php';
}
?>
