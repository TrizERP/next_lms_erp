<?php
session_start();
require '../../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
// privileges checking
$can_read = utility::havePrivilege('circulation', 'r') || utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('circulation', 'w') || utility::havePrivilege('reporting', 'w');

//if (!$can_read) {
//    die('<div class="errorBox">'.__('You don\'t have enough privileges to access this area!').'</div>');
//}

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MODULES_BASE_DIR.'reporting/report_dbgrid.inc.php';

$page_title = 'Overdued List Report';
$reportView = false;
$num_recs_show = 20;
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
      $bradecum = "<a href=javascript:void(0); onclick=javascript:new_set_home(); >Home</a>->";
//          <a class='' href=javascript:void(0); onclick=javascript:new_set('reporting');>"; 
//	$query = "select module_name from mst_module where module_path = 'reporting'";
//	$set_query = $dbs->query($query);
//	while($row=$set_query->fetch_assoc())
//	{
//                $_formated_module_name = ucwords(str_replace('_', ' ', $row['module_name']));
//		$bradecum .= $_formated_module_name;
//	}
//$bradecum .= '</a>->';
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'reporting/customs/overdued_list.php class="headerText2">Overdue List</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
    <!-- filter -->
    <fieldset style="margin-bottom: 3px;">
    <legend style="font-weight: bold"><?php echo strtoupper(__('Overdued List')); ?> - <?php echo __('Report Filter'); ?></legend>
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
    <div id="filterForm">
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Member ID').'/'.__('Member Name'); ?></div>
            <div class="divRowContent">
            <?php
           //comment by iresh on 25-1-2011 echo simbio_form_element::textField('text', 'id_name', '', 'style="width: 50%"');
	   /*added by iresh on 25-1-2011 */echo simbio_form_element::textField('text', 'id_name', '', 'style="width: 140px"');
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Loan Date From'); ?></div>
            <div class="divRowContent">
            <?php
            echo simbio_form_element::dateField('startDate', '01-1-2011');
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Loan Date Until'); ?></div>
            <div class="divRowContent">
            <?php
            echo simbio_form_element::dateField('untilDate', date('d-m-Y'));
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo __('Record each page'); ?></div>
            <div class="divRowContent"><input type="text" name="recsEachPage" size="3" maxlength="3" value="<?php echo $num_recs_show; ?>" /> <?php echo __('Set between 20 and 200'); ?></div>
        </div>
    </div>
    <div style="padding-top: 10px; clear: both;">
    <input type="submit" name="applyFilter" value="<?php echo __('Search'); ?>" />
    <input type="button" name="moreFilter" value="<?php echo __('Advance Search'); ?>" onclick="showHideTableRows('filterForm', 1, this, '<?php echo __('Show More Search Options'); ?>', '<?php echo __('Hide Search Options'); ?>')" />
    <input type="hidden" name="reportView" value="true" />
    </div>
    </form>
    </fieldset>
    <script type="text/javascript">hideRows('filterForm', 1);</script>
    <!-- filter end -->
    <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: 600px;"></iframe>
<?php
} else {
    ob_start();
    // table spec
      $table_spec = ''.$inte_schema.'.tblstudent AS m
        INNER JOIN '.$inte_schema.'.tblstudent_enrollment SE on SE.student_id = m.id and SE.syear = '.$dyear.' AND SE.sub_institute_id='.$_SESSION[SUB_INSTITUTE_ID].'
        INNER JOIN '.$inte_schema.'.standard cs on SE.standard_id = cs.id AND SE.sub_institute_id = cs.sub_institute_id AND cs.sub_institute_id='.$_SESSION[SUB_INSTITUTE_ID].'
        INNER JOIN '.$inte_schema.'.division ss on ss.id = SE.section_id AND SE.sub_institute_id = ss.sub_institute_id AND ss.sub_institute_id='.$_SESSION[SUB_INSTITUTE_ID].'
        LEFT JOIN loan AS l ON m.enrollment_no = l.member_id
        LEFT JOIN item AS i ON i.item_code = l.item_code ';

    // create datagrid
    $reportgrid = new report_datagrid();
    $reportgrid->setSQLColumn('m.enrollment_no AS \''.__('Member ID').'\'',
                               'concat_ws(" ",m.first_name,m.middle_name,m.last_name) AS \''.__('Member Name').'\'',
                               'i.item_code AS \''.__('Accession Number').'\'',
                               'i.item_title AS \''.__('Book Title').'\'',
                               'cs.name AS \''.__('Standard').'\'',
                               'ss.name AS \''.__('Division').'\'');
    $reportgrid->setSQLorder('l.due_date DESC');
    //$reportgrid->sql_group_by = 'm.enrollment_no';
    $criteria = ' 1=1 AND ';
    $criteria .= ' (l.loan_date is not null AND return_date is null AND TO_DAYS(due_date) < TO_DAYS(\''.date('Y-m-d').'\')) ';
    if (isset($_GET['recsEachPage'])) {
        $recsEachPage = (integer)$_GET['recsEachPage'];
        $num_recs_show = ($recsEachPage >= 5 && $recsEachPage <= 200)?$recsEachPage:$num_recs_show;
    }
    $reportgrid->setSQLCriteria($criteria);

    // set table and table header attributes
    $reportgrid->table_attr = 'align="center" class="dataListPrinted" cellpadding="5" cellspacing="0"';
    $reportgrid->table_header_attr = 'class="dataListHeaderPrinted"';
    $reportgrid->column_width = array('1' => '80%');

    // callback function to show overdued list
    function showOverduedList($obj_db, $array_data)
    {
        global $date_criteria;

        // member name
        $member_q = $obj_db->query('SELECT first_name, email,mobile as phone_no FROM '.$_SESSION['inte_schema'].'.tblstudent WHERE enrollment_no=\''.$array_data[0].'\'');
        $member_d = $member_q->fetch_row();
        $member_name = $member_d[0];
        unset($member_q);

        $ovd_title_q = $obj_db->query('SELECT l.item_code, i.price, i.price_currency,
            b.title, l.loan_date,
            l.due_date, (TO_DAYS(DATE(NOW()))-TO_DAYS(due_date)) AS \'Overdue Days\'
            FROM loan AS l
                LEFT JOIN item AS i ON l.item_code=i.item_code
                LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
            WHERE (l.is_lent=1 AND l.is_return=0 AND TO_DAYS(due_date) < TO_DAYS(\''.date('Y-m-d').'\')) AND l.member_id=\''.$array_data[0].'\''.( !empty($date_criteria)?$date_criteria:'' ));
        $_buffer = '<div style="font-weight: bold; color: black; font-size: 10pt; margin-bottom: 3px;">'.$member_name.' ('.$array_data[0].')</div>';
        $_buffer .= '<div style="font-size: 10pt; margin-bottom: 3px;">'.__('E-mail').': <a href="mailto:'.$member_d[1].'">'.$member_d[1].'</a> - '.__('Phone Number').': '.$member_d[2].'</div>';
        $_buffer .= '<table width="100%" cellspacing="0">';
        while ($ovd_title_d = $ovd_title_q->fetch_assoc()) {
            $_buffer .= '<tr>';
            $_buffer .= '<td valign="top" width="10%">'.$ovd_title_d['item_code'].'</td>';
            $_buffer .= '<td valign="top" width="40%">'.$ovd_title_d['title'].'<div>'.__('Price').': '.$ovd_title_d['price'].' '.$ovd_title_d['price_currency'].'</div></td>';
            $_buffer .= '<td width="20%">'.__('Overdue').': '.$ovd_title_d['Overdue Days'].' '.__('day(s)').'</td>';
            $_buffer .= '<td width="30%">'.__('Loan Date').': '.$ovd_title_d['loan_date'].' &nbsp; '.__('Due Date').': '.$ovd_title_d['due_date'].'</td>';
            $_buffer .= '</tr>';
        }
        $_buffer .= '</table>';
        return $_buffer;
    }
    // modify column value
   // $reportgrid->modifyColumnContent(0, 'callback{showOverduedList}');
//echo $table_spec;
    // put the result into variables
    //echo '<pre>';
   // print_r($reportgrid);
    echo $reportgrid->createDataGrid($dbs, $table_spec, $num_recs_show);

    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'pagingBox\').update(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';

    $content = ob_get_clean();
    // include the page template
    require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/printed_page_tpl.php';
}
?>
