<?php
session_start();
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

/* Overdues Report */

// main system configuration
require '../../../../sysconfig.inc.php';
// start the session
//require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
// privileges checking
$can_read = utility::havePrivilege('circulation', 'r') || utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('circulation', 'w') || utility::havePrivilege('reporting', 'w');

if (!$can_read) {
    die('<div class="errorBox">'.gettext('You don\'t have enough privileges to access this area!').'</div>');
}

require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require MODULES_BASE_DIR.'reporting/report_dbgrid.inc.php';

$page_title = 'Active/Inactive Member Report';
$reportView = false;
$num_recs_show = 3;
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
/*if(isset($_REQUEST['action']))
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php?action=detail class="headerText2">Add New Member</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php class="headerText2">View Member List</a>';
}*/
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'reporting/customs/active_inactive_member.php class="headerText2">Active/Inactive Report</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
    <!-- filter -->
    <fieldset style="margin-bottom: 3px;">
    <legend style="font-weight: bold"><?php echo strtoupper(gettext('Active/Inactive Member')); ?> - <?php echo gettext('Report Filter'); ?></legend>
    <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" target="reportView">gettext
    <div id="filterForm">
        
	   <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Member Type'); ?></div>
            <div class="divRowContent">
            <?php
	    //$select_member_type=$dbs->query('select member_type_id,member_type_name from mst_member_type');
            
                    $select_member_type=$dbs->query('select user_id,user_name from category_user');
	      $member_type_options[] = array('0', strtoupper(gettext('N/A')));
     		   while ($member_type_d = $select_member_type->fetch_row()) {
          		  $member_type_options[] = array($member_type_d[0], $member_type_d[1]);
        			}
           //comment by iresh on 25-1-2011 echo simbio_form_element::textField('text', 'id_name', '', 'style="width: 50%"');
	   /*added by iresh on 25-1-2011 */echo simbio_form_element::selectList('membertypeid', $member_type_options, '', 'style="width: 140px"');
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Loan Date From'); ?></div>
            <div class="divRowContent">
            <?php
            echo simbio_form_element::dateField('startDate', '2000-01-01');
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Loan Date Until'); ?></div>
            <div class="divRowContent">
            <?php
            echo simbio_form_element::dateField('untilDate', date('Y-m-d'));
            ?>
            </div>
        </div>
	<div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Transaction >'); ?></div>
            <div style="float:left">
            <?php
            echo simbio_form_element::textField('text', 'transaction_max', '', 'style="width: 140px"').'&nbsp;';
            ?>
            </div>
            <div style="float:left;"><?php echo gettext('Or').'&nbsp;'; ?></div>
            <div style="float:left"><?php echo gettext('Transaction <').'&nbsp;'; ?></div>
            <div style="float:left">
            <?php
            echo simbio_form_element::textField('text', 'transaction_less', '', 'style="width: 140px"').'&nbsp;';
            ?>
            </div>
        </div>
        <div class="divRow">
            <div class="divRowLabel"><?php echo gettext('Record each page'); ?></div>
            <div class="divRowContent"><input type="text" name="recsEachPage" size="3" maxlength="3" value="<?php echo $num_recs_show; ?>" /> <?php echo gettext('Set between 20 and 200'); ?></div>
        </div>
    </div>
    <div style="padding-top: 10px; clear: both;">
    <input type="submit" name="applyFilter" value="<?php echo gettext('Search'); ?>" />
    <input type="button" name="moreFilter" value="<?php echo gettext('Advance Search'); ?>" onclick="showHideTableRows('filterForm', 1, this, '<?php echo gettext('Show More Search Options'); ?>', '<?php echo gettext('Hide Search Options'); ?>')" gettext    <input type="hidden" name="reportView" value="true" />
    </div>
    </form>
    </fieldset>
    <script type="text/javascript">hideRows('filterForm', 1);</script>
    <!-- filter end -->
    <div class="dataListHeader" style="padding: 3px;"><span id="pagingBox"></span></div>
    <iframe name="reportView" id="reportView" src="<?php echo $_SERVER['PHP_SELF'].'?reportView=true'; ?>" frameborder="0" style="width: 100%; height: 500px;"></iframe>
<?php
} else {
    ob_start();

    // table spec
   /*$table_spec = 'member AS m
        LEFT JOIN loan AS l ON m.member_id=l.member_id';*/
    $table_spec = ''.$inte_schema.'.tblstudent AS m
        LEFT JOIN loan AS l ON m.enrollment_no=l.member_id';

    // create datagrid
    $reportgrid = new report_datagrid();
   // $reportgrid->setSQLColumn('DISTINCT(m.member_id) AS \''.__('Member ID').'\'','(m.member_name) AS \''.__('Member Name').'\'','(l.item_code) AS \''.__('Barcode').'\'','(l.loan_date) AS \''.__('Loan Date').'\'','(l.due_date) AS \''.__('Due Date').'\'');
     //$reportgrid->setSQLColumn('DISTINCT(m.member_id) AS \''.__('Member ID').'\'');
    $reportgrid->setSQLColumn('DISTINCT(m.enrollment_no) AS \''.gettext('Member ID').'\'');
    $reportgrid->setSQLorder('l.due_date DESC');
    ////if ((isset($_GET['membertypeid'])==0 || isset($_GET['membertypeid'])!=0) && isset($_GET['transaction_max'])=='' && isset($_GET['transaction_less'])=='') 
    //{
    //$reportgrid->sql_group_by = 'm.member_id';
    //}
 
    $overdue_criteria = 'l.is_lent=1';
    // is there any search
    if (isset($_GET['id_name']) AND $_GET['id_name']) {
        $keyword = $dbs->escape_string(trim($_GET['id_name']));
        $words = explode(' ', $keyword);
        if (count($words) > 1) {
            $concat_sql = ' (';
            foreach ($words as $word) {
                //$concat_sql .= " (m.member_id LIKE '%$word%' OR m.member_name LIKE '%$word%') AND";
                $concat_sql .= " (m.enrollment_no LIKE '%$word%' OR m.first_name LIKE '%$word%' OR m.last_name LIKE '%$word%') AND";
            }
            // remove the last AND
            $concat_sql = substr_replace($concat_sql, '', -3);
            $concat_sql .= ') ';
            $overdue_criteria .= ' AND '.$concat_sql;
        } else {
            //$overdue_criteria .= " AND m.member_type_id LIKE '%$keyword%' OR m.member_name LIKE '%$keyword%'";
            $overdue_criteria .= " AND m.user_group_id LIKE '%$keyword%' AND m.first_name LIKE '%$keyword%' OR m.last_name LIKE '%$keyword%'";
        }
    }
     if (isset($_GET['membertypeid']) AND $_GET['membertypeid']) {
        /*$membertypeid = $dbs->escape_string(trim($_GET['membertypeid']));
	$overdue_criteria .= " AND m.member_type_id='$membertypeid'";*/
        $membertypeid = $dbs->escape_string(trim($_GET['membertypeid']));
	$overdue_criteria .= " AND m.user_group_id='$membertypeid'";
      }
    // loan date
    if (isset($_GET['startDate']) AND isset($_GET['untilDate'])) {
        $date_criteria = ' AND (TO_DAYS(l.loan_date) BETWEEN TO_DAYS(\''.$_GET['startDate'].'\') AND
            TO_DAYS(\''.$_GET['untilDate'].'\'))';
        $overdue_criteria .= $date_criteria;
    }
    if (isset($_GET['recsEachPage'])) {
        $recsEachPage = (integer)$_GET['recsEachPage'];
        $num_recs_show = ($recsEachPage >= 5 && $recsEachPage <= 200)?$recsEachPage:$num_recs_show;
    }
      if (isset($_GET['transaction_max']) AND $_GET['transaction_max']) {
        $transaction_max = $dbs->escape_string(trim($_GET['transaction_max']));
	//$overdue_criteria .= " GROUP BY m.member_id  Having count(loan_id)>'$transaction_max'";
        $overdue_criteria .= " GROUP BY m.enrollment_no  Having count(loan_id)>'$transaction_max'";
        
      }
     if (isset($_GET['transaction_less']) AND $_GET['transaction_less']) {
        $transaction_less = $dbs->escape_string(trim($_GET['transaction_less']));
	//$overdue_criteria .= " GROUP BY m.member_id  Having count(loan_id)<'$transaction_less'";
        $overdue_criteria .= " GROUP BY m.enrollment_no  Having count(loan_id)<'$transaction_less'";
        
      }
    $reportgrid->setSQLCriteria($overdue_criteria);

    // set table and table header attributes
    $reportgrid->table_attr = 'align="center" class="dataListPrinted" cellpadding="5" cellspacing="0"';
    $reportgrid->table_header_attr = 'class="dataListHeaderPrinted"';
    $reportgrid->column_width = array('1' => '80%');

    
    
    // callback function to show overdued list
    function showOverduedList($obj_db, $array_data)
    {
        global $date_criteria;

        // member name
        //$member_q = $obj_db->query('SELECT member_name, member_email, member_phone FROM member WHERE member_id=\''.$array_data[0].'\'');
        $member_q = $obj_db->query('SELECT concat(first_name," ",last_name) as member_name, email as member_email, phone_no as member_phone FROM '.$inte_schema.'.tblstudent WHERE enrollment_no=\''.$array_data[0].'\'');
        $member_d = $member_q->fetch_row();
        $member_name = $member_d[0];
        unset($member_q);
        $ovd_title_q = $obj_db->query('SELECT l.item_code, i.price, i.price_currency,
            b.title, l.loan_date,
            l.due_date, (TO_DAYS(DATE(NOW()))-TO_DAYS(due_date)) AS \'Overdue Days\'
            FROM loan AS l
                LEFT JOIN item AS i ON l.item_code=i.item_code
                LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
            WHERE l.is_lent=1   AND l.member_id=\''.$array_data[0].'\''.( !empty($date_criteria)?$date_criteria:'' ));
        $_buffer = '<div style="font-weight: bold; color: black; font-size: 10pt; margin-bottom: 3px;">'.$member_name.' ('.$array_data[0].')</div>';
        $_buffer .= '<div style="font-size: 10pt; margin-bottom: 3px;">'.gettext('E-mail').': <a href="mailto:'.$member_d[1].'">'.$member_d[1].'</a> - '.gettext('Phone Number').': '.$member_d[2].'</div>';
        $_buffer .= '<table width="100%" cellspacing="0">';
        while ($ovd_title_d = $ovd_title_q->fetch_assoc()) {
            $_buffer .= '<tr>';
            $_buffer .= '<td valign="top" width="10%">'.$ovd_title_d['item_code'].'</td>';
            $_buffer .= '<td valign="top" width="40%">'.$ovd_title_d['title'].'<div>'.gettext('Price').': '.$ovd_title_d['price'].' '.$ovd_title_d['price_currency'].'</div></td>';
            $_buffer .= '<td width="30%">'.gettext('Loan Date').': '.$ovd_title_d['loan_date'].' &nbsp; '.gettext('Due Date').': '.$ovd_title_d['due_date'].'</td>';
            $_buffer .= '</tr>';
        }
        $_buffer .= '</table>';
	
        return $_buffer;
    
    }

    // modify column value
    $reportgrid->modifyColumnContent(0, 'callback{showOverduedList}');

    // put the result into variables
    echo $reportgrid->createDataGrid($dbs, $table_spec, $num_recs_show);
    
   // echo $peging = '<div style="text-align: center;">'.simbio_paging::paging($reportgrid->num_rows, $num_recs_show, 5).'</div>';		
    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'pagingBox\').update(\''.str_replace(array("\n", "\r", "\t"), '', $reportgrid->paging_set).'\');'."\n";
    echo '</script>';

    $content = ob_get_clean();
    // include the page template
/*$select_item= $dbs->query('select count(item_code) as itemcode from loan where is_return=0');
$select_item_count=$select_item->fetch_assoc();
if($select_item_count['itemcode']>0 && $reportgrid->num_rows >0)
{
//echo '<div class="printPageInfo"><a style="color:yellow; margin-top:10px; margin-right:470px; float:right;" target="_blank" href="active_inactive_member_list_excel.php" title="Click To View File" ><strong>[Export To Excel]</strong></a></div>';
}*/
    require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/printed_page_tpl.php';

}
?>
