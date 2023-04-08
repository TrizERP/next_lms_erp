<?php
error_reporting(0);

$sertracking = array();
$sertracking = explode('/', $_SERVER[SCRIPT_FILENAME]);
// $data_file = '/' . $sertracking[1] . '/' . $sertracking[2] . '/' . $sertracking[3] . '/' . $sertracking[4] . '/data.php';

//include_once $data_file;
//echo basename($_SERVER['PHP_SELF']);
//echo basename(__FILE__);
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {

    include_once '../../sysconfig.inc.php';
}

//else {
//    include_once '../../sysconfig.inc.php';
//}
// echo $inte_schemaa;die;
// generate warning messages
$warnings = array();
// check GD extension
if (!extension_loaded('gd')) {
    $warnings[] = __('<strong>PHP GD</strong> extension is not installed. Please install it or application won\'t be able to create image thumbnail and barcode.');
} else {
    // check GD Freetype
    if (!function_exists('imagettftext')) {
        $warnings[] = __('<strong>Freetype</strong> support is not enabled in PHP GD extension. Rebuild PHP GD extension with Freetype support or application won\'t be able to create barcode.');
    }
}


// check if images dir is writable or not
if (!is_writable(IMAGES_BASE_DIR) OR ! is_writable(IMAGES_BASE_DIR . 'barcodes') OR ! is_writable(IMAGES_BASE_DIR . 'persons') OR ! is_writable(IMAGES_BASE_DIR . 'docs')) {

    $warnings[] = __('<strong>Images</strong> directory and directories under it is not writable. Make sure it is writable by changing its permission or you won\'t be able to 			upload any images and create barcodes');
}
// check if file repository dir is writable or not
if (!is_writable(REPO_BASE_DIR)) {

    $warnings[] = __('<strong>Repository</strong> directory is not writable. Make sure it is writable (and all directories under it) by changing its permission or you won\'t be able to upload any bibliographic attachments.');
}
// check if file upload dir is writable or not
if (!is_writable(FILES_UPLOAD_DIR)) {

    $warnings[] = __('<strong>File upload</strong> directory is not writable. Make sure it is writable (and all directories under it) by changing its permission or you won\'t be able to upload any file, create report files and create database backups.');
}
// check mysqldump
if (!file_exists($sysconf['mysqldump'])) {
    $warnings[] = __('The PATH for <strong>mysqldump</strong> program is not right! Please check configuration file or you won\'t be able to do any database backups.');
}

// if there any warnings
if ($warnings) {
    echo '<div style="padding: 3px; border: 1px dotted #FF0000; background: #FFFFFF;">';
    echo '<ul>';
    foreach ($warnings as $warning_msg) {
        echo '<li style="color: #FF0000;">' . $warning_msg . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}

// admin page content
//added and commented started by 24/8/2011
/* require LIB_DIR.'content.inc.php';
  $content = new content();
  $content_data = $content->get($dbs, 'adminhome');
  if ($content_data) {
  echo '<div class="contentDesc">'.$content_data['Content'].'</div>';
  unset($content_data);
  } */


echo '<table border=0 cellspacing=0>';
// echo '<tr><td colspan=4 align=left><div class="breadcrumb">';
// $bradecum = $_SESSION['realname'] . '<a class="menu" href=' . SENAYAN_WEB_ROOT_DIR . 'admin/index.php>';
// $bradecum .= 'Home';
// $bradecum .= '</a>';
// echo $bradecum;
// echo '</div></td></tr>';

echo '<div class="top-box">
    <div class="white-box">
        <h4 class="page-title">Dashboard</h4>
        <h5 class="welcome-msg">Welcome Admin '.$_SESSION['school_name'].'</h5>
    </div>
    </div>
';

echo '
	<div class="top-white-box">
        <div class="single-white-box">
            <div class="white-box analytics-info box-radius">
                <h3 class="box-title">Total Students</h3>
                <ul class="list-inline two-part">
                    <li>
                        <img src="https://erp.triz.co.in/admin_dep/images/student.png">
                        <!-- <div id="sparklinedash"></div> -->
                    </li>
                    <li class="text-left">
                        <!-- <i class="ti-arrow-up text-success"></i> --> <span class="counter text-blue">318</span></li>
                </ul>
            </div>
        </div>
        <div class="single-white-box">
            <div class="white-box analytics-info yellow box-radius">
                <h3 class="box-title">Total Employees</h3>
                <ul class="list-inline two-part">
                    <li>
                        <img src="https://erp.triz.co.in/admin_dep/images/employe.png">
                        <!-- <div id="sparklinedash2"></div> -->
                    </li>
                    <li class="text-left">
                        <!-- <i class="ti-arrow-up text-purple"></i> --> <span class="counter text-yellow">59</span></li>
                </ul>
            </div>
        </div>
        <div class="single-white-box">
            <div class="white-box analytics-info pink box-radius">
                <h3 class="box-title">Admission Inquiry</h3>
                <ul class="list-inline two-part">
                    <li>
                        <img src="https://erp.triz.co.in/admin_dep/images/admnission.png">
                        <!-- <div id="sparklinedash3"></div> -->
                    </li>
                    <li class="text-left">
                        <!-- <i class="ti-arrow-up text-info"></i> --> <span class="counter text-pink">0</span></li>
                </ul>
            </div>
        </div>
        <div class="single-white-box">
            <div class="white-box analytics-info green box-radius">
                <h3 class="box-title">Total Income</h3>
                <ul class="list-inline two-part">
                    <li>
                        <img src="https://erp.triz.co.in/admin_dep/images/income.png">
                        <!-- <div id="sparklinedash4"></div> -->
                    </li>
                    <li class="text-left">
                        <!-- <i class="ti-arrow-down text-danger"></i> --> <span class="text-green">0</span></li>
                </ul>
            </div>
        </div>
    </div>
	';

echo '<tr>';
//admin Panel

// echo '<td width=19% valign=top>
		// <div class="admin_top_bg">
			// <p class="title1">Catalog</p>
		// </div>' . $sub_menu1 . '
		// <div class="admin_bot_bg"></div>';
// echo '</td>';


echo '<td valign=top>
		<div class="admin_top_bg">
			<p class="title1">Catalog</p>
		' . $sub_menu1;
echo '</td>';

echo '<td valign=top>
		<div class="cir_top_bg">
			<p class="title1">Circulation</p>
		' . $sub_menu2 .'
			<p class="title">Member Area</p>
			' . $sub_menu3.'</div>';
echo '</td>';
echo '</tr>';
echo '<tr>';
echo '<td valign=top><div class="due_top_bg"><p class="title1">Due Date Warning</p>';


$_buffer = '';
// echo 'SELECT l.item_code, m.enrollment_no, m.first_name, m.email, m.phone_no, b.title, l.loan_date, l.due_date, (TO_DAYS(DATE(NOW()))-TO_DAYS(due_date)) AS \'Overdue Days\' FROM loan AS l LEFT JOIN item AS i ON l.item_code=i.item_code  INNER JOIN ' . $inte_schema . '.tblstudent m ON m.enrollment_no=l.member_id LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id WHERE (l.loan_date is not null AND l.return_date is null AND ((TO_DAYS(\'' . date('Y-m-d') . '\')-TO_DAYS(due_date)) BETWEEN 0 AND 3))';
$due_date = $dbs->query('SELECT l.item_code, m.enrollment_no, m.first_name, m.email, m.phone_no, b.title, l.loan_date, l.due_date, (TO_DAYS(DATE(NOW()))-TO_DAYS(due_date)) AS \'Overdue Days\' FROM loan AS l LEFT JOIN item AS i ON l.item_code=i.item_code  INNER JOIN ' . $inte_schema . '.tblstudent m ON m.enrollment_no collate utf8_general_ci =l.member_id collate utf8_general_ci and m.sub_institute_id = '.$_SESSION['SUB_INSTITUTE_ID'].' LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id WHERE m.sub_institute_id = '.$_SESSION['SUB_INSTITUTE_ID'].' AND (l.loan_date is not null AND l.return_date is null AND ((TO_DAYS(\'' . date('Y-m-d') . '\')-TO_DAYS(due_date)) BETWEEN 0 AND 3))');

$_buffer .= '<marquee direction="up" onmouseover="this.stop()" onmouseout="this.start()" scrollamount="2"><table cellspacing="0" border=0>';
$_buffer .= '<tr bgcolor=#CCCCCC><td>GR.No.</td><td>Title</td><td>Date</td></tr>';
if ($due_date->num_rows) {
    while ($_title_d = $due_date->fetch_assoc()) {

        $_buffer .= '<tr>';
        $_buffer .= '<td valign="top" width="10%">' . $_title_d['enrollment_no'] . '</td>';
        // $_buffer .= '<td valign="top" width="10%">'.$_title_d['item_code'].'</td>';
        $_buffer .= '<td valign="top" width="10%">' . $_title_d['title'] . '</td>';
        $_buffer .= '<td valign="top" width="10%">' . __('Due Date') . ': ' . date('d-m-Y', strtotime($_title_d['due_date'])) . '</td>';
        $_buffer .= '</tr>';
    }
}

$_buffer .= '<tr><td></td><td></td><td><a class="subMenuItem" href=' . MODULES_WEB_ROOT_DIR . 'reporting/customs/due_date_warning.php>Take Action</a></td></tr>';
$_buffer .= '</table></marquee>';
$_buffer .= '</div>';
echo $_buffer;
echo '</td>';

echo '<td valign=top><div class="overdue_top_bg"><p class="title1">Overdue Report</p>';

$ovd_title_q = $dbs->query('SELECT l.item_code, i.price, i.price_currency, b.title, l.loan_date, l.due_date, m.enrollment_no, m.first_name, m.email, m.phone_no, (TO_DAYS(DATE(NOW()))-TO_DAYS(due_date)) AS \'Overdue Days\' FROM loan AS l LEFT JOIN item AS i ON l.item_code=i.item_code INNER JOIN ' . $inte_schema . '.tblstudent m ON m.enrollment_no collate utf8_general_ci =l.member_id collate utf8_general_ci LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id WHERE m.sub_institute_id = '.$_SESSION['SUB_INSTITUTE_ID'].' AND (l.loan_date is not null AND l.return_date is null AND TO_DAYS(due_date) < TO_DAYS(\'' . date('Y-m-d') . '\'))');
$_buffer1 .= '<div  class="overdue_mid_bg"><marquee direction="up" onmouseover="this.stop()" onmouseout="this.start()" scrollamount="2"><table width="100%" cellspacing="0" border=0>';
$_buffer1 .= '<tr bgcolor=#CCCCCC><td>GR.No.</td><td>Item Code</td><td>Title</td><!--<td>Days</td>--><td>Date</td></tr>';
if ($ovd_title_d->num_rows) {
    while ($ovd_title_d = $ovd_title_q->fetch_assoc()) {

        $_buffer1 .= '<tr>';
        $_buffer1 .= '<td valign="top" width="10%">' . $ovd_title_d['enrollment_no'] . '</td>';
          $_buffer1 .= '<td valign="top" width="10%">'.$ovd_title_d['item_code'].'</td>';
        $_buffer1 .= '<td valign="top" width="40%">' . $ovd_title_d['title'] . '<div></div></td>';
        //$_buffer1 .= '<td width="20%">'.__('Overdue').': '.$ovd_title_d['Overdue Days'].' '.__('day(s)').'</td>';
        $_buffer1 .= '<td width="30%" valign="top">'.__('Loan Date').': '.$ovd_title_d['loan_date'].' &nbsp; '.__('Due Date').': '.$ovd_title_d['due_date'].'</td>';
        $_buffer1 .= '<td width="30%" valign="top">&nbsp; ' . __('Due Date') . ': ' . date('d-m-Y', $ovd_title_d['due_date']) . '</td>';
        $_buffer1 .= '</tr>';
    }
}
$_buffer1 .= '<tr><td></td><td></td><td><a class="subMenuItem" href=' . MODULES_WEB_ROOT_DIR . 'reporting/customs/overdued_list.php>Take Action</a>';
$_buffer1 .= '</td></tr>';
$_buffer1 .= '</table></marquee></div>';
$_buffer1 .= '<div class="overdue_bot_bg"></div></div>';
echo $_buffer1;
echo '</td>';
echo '</tr></table>';

echo '<table width=100% border=0><tr><td width=44% valign=top><div class="loan_top_bg"><p class="title">Loan Report</p>';
$report_q = $dbs->query('SELECT gmd_name, COUNT(loan_id) FROM loan AS l
    INNER JOIN item AS i ON l.item_code=i.item_code
    INNER JOIN biblio AS b ON i.biblio_id=b.biblio_id
    INNER JOIN mst_gmd AS gmd ON b.gmd_id=gmd.gmd_id
    GROUP BY b.gmd_id ORDER BY COUNT(loan_id) DESC');
$report_a = '<a class="notAJAX" href="#" onclick="openHTMLpop(\'' . MODULES_WEB_ROOT_DIR . 'reporting/charts_report.php?chart=total_loan_gmd\', 700, 470, \'' . __('Total Loan By Material Type') . '\')">' . __('Show in chart/plot') . '</a>';
echo $report_a;
if ($ovd_title_d->num_rows) {
    while ($data = $report_q->fetch_row()) {
        //echo "<tr>";
        $report_d = $data[0];
        $report_d1 = $data[1] . ",0";
        //echo "</tr>";
        $report_d3 .= '{name:"' . $report_d . '",data:[' . $report_d1 . ']},';
    }
}
$report_d3 = str_replace(' ', '%20', $report_d3);
$report_q = $dbs->query('SELECT material_sub_name, COUNT(loan_id) FROM loan AS l
        INNER JOIN item AS i ON l.item_code=i.item_code
        INNER JOIN biblio AS b ON i.biblio_id=b.biblio_id
        INNER JOIN mst_material_sub_type AS gmd ON b.material_sub_id=gmd.material_sub_id
        GROUP BY b.material_sub_id ORDER BY COUNT(loan_id) DESC');
$report_d = '';
echo '';
echo '<marquee direction="up" onmouseover="this.stop()" onmouseout="this.start()" scrollamount="2">';
echo "<table width=70% border=0 align=center><th>Material Type</th><th>Material Sub Type</th><br>";
if ($report_q->num_rows) {
    while ($data = $report_q->fetch_row()) {
        echo "<tr>";
        echo '<td width="15">' . $data[0] . '</td>';
        echo '<td width="15" align=center>' . $data[1] . '</td>';
        echo "</tr>";
        //echo $report_d2 = '"'.$data[0].'",';
        // $report_d3 .= '{name:"'.$report_d.'",data:['.$report_d1.']},';
    }
}
echo "</table></marquee>";
$report_d2 = '"Material%20Type","Material%20Sub%20Type"';
$report_d3 = str_replace(' ', '%20', $report_d3);


//    <iframe name=new src='.SENAYAN_WEB_ROOT_DIR.'admin/modules/reporting/Highcharts/examples/Chart_admin_loan.php?set_value='.$report_d1.'&set_title='.$report_d.'&title=Loan%20Report&sidex=Loan&width=360px height=300px width=100% frameBorder=0></iframe>';
echo '<table align=right><tr><td><a class="subMenuItem" href=' . MODULES_WEB_ROOT_DIR . 'reporting/loan_report.php>See</a></td></tr></table></div>';


//echo '<table align=right><tr><td><a class="menu" href=index.php?mod=reporting>See</a></td></tr></table></div><div class="loan_bot_bg"></div>';
echo '</td>';
echo '<td valign=top><div class="staff_top_bg"><p class="title">Staff Activity</p>';
$start_date = '2000-01-01';
$until_date = date('Y-m-d');

$user_id = $dbs->query("SELECT id as user_group_id,name as user_group_name from " . $inte_schema . ".tbluserprofilemaster where SUB_INSTITUTE_ID = '".$_SESSION['SUB_INSTITUTE_ID']."'");

echo '';
echo '<marquee direction="up" onmouseover="this.stop()" onmouseout="this.start()" scrollamount="2">';
echo "<table border=0 align=center width=70%><th>USER</th><th>Bibliography Data Entry</th><th>Item Data Entry</th><th>Member Data Entry</th><th>Circulation Tasks</th>";


while ($data = $user_id->fetch_row()) {
	
   $userid = $data[0];
   $_user = $data[1];
//$_username = $data[2];

    $_count_q = $dbs->query('SELECT COUNT(log_id) FROM system_log WHERE log_location=\'bibliography\' AND log_type=\'staff\'
            AND log_msg LIKE \'%insert bibliographic data%\' AND id=\'' . $userid . '\' AND TO_DAYS(log_date) BETWEEN TO_DAYS(\'' . $start_date . '\') AND TO_DAYS(\'' . $until_date . '\')');
    $_count_d = $_count_q->fetch_row();
    $biblio_staff = $_count_d[0];
    $_count_q = $dbs->query('SELECT COUNT(log_id) FROM system_log WHERE log_location=\'bibliography\' AND log_type=\'staff\'
            AND log_msg LIKE \'%insert item data%\' AND id=\'' . $userid . '\' AND TO_DAYS(log_date) BETWEEN TO_DAYS(\'' . $start_date . '\') AND TO_DAYS(\'' . $until_date . '\')');
    $_count_d = $_count_q->fetch_row();
    $item_staff = $_count_d[0];
	
    $_count_q = $dbs->query('SELECT COUNT(log_id) FROM system_log WHERE log_location=\'membership\' AND log_type=\'member\'
            AND log_msg LIKE \'%add new member%\' AND id=\'' . $userid . '\' AND TO_DAYS(log_date) BETWEEN TO_DAYS(\'' . $start_date . '\') AND TO_DAYS(\'' . $until_date . '\')');
    $_count_d = $_count_q->fetch_row();
    $member_staff = $_count_d[0];
	
    $_count_q = $dbs->query('SELECT COUNT(log_id) FROM system_log WHERE log_location=\'circulation\' AND log_type=\'member\'
            AND (log_msg LIKE \'' . $_user . '%transaction with member%\' OR log_msg LIKE \'' . $_user . '%Quick Return%\') AND TO_DAYS(log_date) BETWEEN TO_DAYS(\'' . $start_date . '\') AND TO_DAYS(\'' . $until_date . '\')');
    $_count_d = $_count_q->fetch_row();
	
    $user_staff = $_count_d[0];
	
    echo '<tr><td>' . $_user . '</td><td align=center>' . $biblio_staff . '</td><td align=center>' . $item_staff . '</td><td align=center>' . $member_staff . '</td><td align=center>' . $user_staff . '</td></tr>';		
}

echo "</table></marquee>";
//echo $set_value;
$set_value = str_replace(' ', '%20', $set_value);
$set_title = '"Bibliography%20Data%20Entry","Item%20Data%20Entry","Member%20Data%20Entry","Circulation%20Tasks"';

//<iframe name=new src='.SENAYAN_WEB_ROOT_DIR.'admin/modules/reporting/Highcharts/examples/Chart_admin_loan.php?set_value='.$set_value.'&set_title='.$set_title.'&title=Staff%20Activity&sidex=Activity&width=560px height=300px width=100% frameBorder=0></iframe>';
echo '<table align=right><tr><td><a class="subMenuItem" href=' . MODULES_WEB_ROOT_DIR . 'reporting/customs/staff_act.php>See</a></td></tr></table></div>';

//       echo '<table align=right><tr><td><a class="menu" href=index.php?mod=reporting>See</a></td></tr></table></div><div class="staff_bot_bg"></div>';
echo '</td></tr>';
echo '</table>';
//added and commented ended by 24/8/2011
?>
