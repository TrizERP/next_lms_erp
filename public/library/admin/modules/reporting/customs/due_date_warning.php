<?php
/**
 *
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/* Due Date Warning Report */
session_start();
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
/*if(isset($_REQUEST['action']))
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php?action=detail class="headerText2">Add New Member</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php class="headerText2">View Member List</a>';
}*/
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'reporting/customs/due_date_warning.php class="headerText2">Due Date Warning</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
    <!-- filter -->
    <fieldset style="margin-bottom: 3px;">
    <legend style="font-weight: bold"><?php echo strtoupper(gettext('Due Date Warning')); ?> - <?php echo gettext('Report Filter'); ?></legend>
    <div><?php echo gettext('This report loan items which will due in 3 to 0 days'); ?></div>
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">
    <div id="filterForm">
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Member ID').'/'.gettext('Member Name'); ?></div>
            <div class="divRowContent">
            <?php
            //comment by iresh on 25-1-2011  echo simbio_form_element::textField('text', 'id_name', '', 'style="width: 50%"');
            /*added by iresh on 25-1-2011*/  echo simbio_form_element::textField('text', 'id_name', '', 'style="width: 140px"');
            ?>
            </div>
        </div>
        
        <!--<div class="divRow">-->
            <div class="divRowLabel"><?php echo gettext('Standard'); ?></div>
            <div class="divRowContent">
            <?php
			$str = "SELECT cs.id AS subject_id,cs.name AS title
                        FROM ".$inte_schema.".standard cs
                        INNER JOIN ".$inte_schema.".academic_section sg ON sg.id = cs.grade_id AND cs.sub_institute_id = sg.sub_institute_id
                        WHERE sg.sub_institute_id='".$_SESSION['SUB_INSTITUTE_ID']."' ";
			$std_q = $dbs->query($str);
			$std_options = array();
			$std_options[] = array('ALL', gettext('ALL'));
			while ($std_d = $std_q->fetch_row()) {
				$std_options[] = array($std_d[0], $std_d[1]);
			}
			echo simbio_form_element::selectList('standard', $std_options);
			
			
            //echo simbio_form_element::radioButton('standard', $gender_chbox, 'ALL');
            ?>
            </div>
        <!--</div> -->
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Division'); ?></div>
            <div class="divRowContent">
            <?php
			$str_section = "select id,name from ".$inte_schema.".division where sub_institute_id='".$_SESSION['SUB_INSTITUTE_ID']."'";
			$section_q = $dbs->query($str_section);
			$section_options = array();
			$section_options[] = array('ALL', gettext('ALL'));
			while ($section_d = $section_q->fetch_row()) {
				$section_options[] = array($section_d[0], $section_d[1]);
			}
			echo simbio_form_element::selectList('section', $section_options);
			
            ?>
            </div>
        </div>
        <div class="divRow">
        	<div class="divRow">
            	<div class="divRowLabel"><?php echo gettext('Due From Date'); ?></div>
	            <div class="divRowContent"><?php echo simbio_form_element::dateField('startDate', date('Y-m-d')); ?></div>
    	    </div>
        </div>
        <div class="divRow">
        	<div class="divRow">
            	<div class="divRowLabel"><?php echo gettext('Due To Date'); ?></div>
	            <div class="divRowContent"><?php echo simbio_form_element::dateField('toDate', date('Y-m-d')); ?></div>
    	    </div>
        </div>        
        
            <div class="divRowLabel"><?php echo gettext('Record each page'); ?></div>
            <div class="divRowContent"><input type="text" name="recsEachPage" size="3" maxlength="3" value="<?php echo $num_recs_show; ?>" /> <?php echo gettext('Set between 20 and 200'); ?></div>
        </div>
    </div>
    <div style="padding-top: 10px; clear: both;">
    <input type="submit" name="applyFilter" value="<?php echo gettext('Search'); ?>" />
    <input type="button" name="moreFilter" value="<?php echo gettext('Advance Search'); ?>" onclick="showHideTableRows('filterForm', 1, this, '<?php echo gettext('Show More Search Options'); ?>', '<?php echo gettext('Hide Search Options'); ?>')" />
    <input type="hidden" name="reportView" value="true" />
    </div>
    </form>
    </fieldset>
    <script type="text/javascript">hideRows('filterForm', 1);</script>
    <!-- filter end -->
    <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: auto;"></iframe>
<?php
} else {
    ob_start();
    // table spec
     $table_spec = ''.$inte_schema.'.tblstudent AS m
        LEFT JOIN loan AS l ON m.enrollment_no = l.member_id ';

    // create datagrid
    $reportgrid = new report_datagrid();
    $reportgrid->setSQLColumn('m.enrollment_no AS \''.gettext('Member ID').'\'');
    $reportgrid->setSQLorder('l.due_date DESC');
    $reportgrid->sql_group_by = 'm.enrollment_no';

    $overdue_criteria = ' l.loan_date is not null AND l.return_date is null AND l.due_date <=  \''.date('Y-m-d').'\' '; //BETWEEN 0 AND 3
     
    if (isset($_GET['recsEachPage'])) {
        $recsEachPage = (integer)$_GET['recsEachPage'];
        $num_recs_show = ($recsEachPage >= 5 && $recsEachPage <= 200)?$recsEachPage:$num_recs_show;
    }
    //echo $overdue_criteria;
    $reportgrid->setSQLCriteria($overdue_criteria);
    $reportgrid->table_attr = 'align="center" class="dataListPrinted" cellpadding="5" cellspacing="0"';
    $reportgrid->table_header_attr = 'class="dataListHeaderPrinted"';
    $reportgrid->column_width = array('1' => '80%');

    // callback function to show overdued list
    function showOverduedList($obj_db, $array_data)
    {
        global $date_criteria;
        // member name
        $member_q = $obj_db->query('SELECT first_name,email,mobile as phone_no FROM '.$_SESSION['inte_schema'].'.tblstudent WHERE enrollment_no=\''.$array_data[0].'\'');
        $member_d = $member_q->fetch_row();
        $member_name = $member_d[0];
        unset($member_q);

        $_title_q = $obj_db->query('SELECT l.item_code, b.title, l.loan_date,
            l.due_date, (TO_DAYS(DATE(NOW()))-TO_DAYS(due_date)) AS \'Overdue Days\'
            FROM loan AS l
                LEFT JOIN item AS i ON l.item_code=i.item_code
                LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
            WHERE (l.is_lent=1 AND l.is_return=0 AND ( (TO_DAYS(\''.date('Y-m-d').'\')-TO_DAYS(due_date)) BETWEEN 0 AND 3) AND l.member_id=\''.$array_data[0].'\')');
        $_buffer = '<div style="font-weight: bold; color: black; font-size: 10pt; margin-bottom: 3px;">'.$member_name.' ('.$array_data[0].')</div>';
        $_buffer .= '<div style="font-size: 10pt; margin-bottom: 3px;">'.gettext('E-mail').': <a href="mailto:'.$member_d[1].'">'.$member_d[1].'</a> - '.gettext('Phone Number').': '.$member_d[2].'</div>';
        $_buffer .= '<table width="100%" cellspacing="0">';
        while ($_title_d = $_title_q->fetch_assoc()) {
            $_buffer .= '<tr>';
            $_buffer .= '<td valign="top" width="10%">'.$_title_d['item_code'].'</td>';
            $_buffer .= '<td valign="top" width="40%">'.$_title_d['title'].'</td>';
            $_buffer .= '<td width="30%">'.gettext('Loan Date').': '.$_title_d['loan_date'].' &nbsp; '.gettext('Due Date').': '.$_title_d['due_date'].'</td>';
            $_buffer .= '</tr>';
        }
        $_buffer .= '</table>';
        return $_buffer;
    }
    // modify column value
    $reportgrid->modifyColumnContent(0, 'callback{showOverduedList}');
    echo $reportgrid->createDataGrid($dbs, $table_spec, $num_recs_show);

    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'pagingBox\').update(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';

    $content = ob_get_clean();
    // include the page template
    require SENAYAN_BASE_DIR.'/admin/admin_template/printed_page_tpl.php';
}
?>
