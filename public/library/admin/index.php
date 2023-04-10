<?php
session_start();
/*$ss= array(
    "user_id "=> $_REQUEST['DUSER_ID'],
 "password" => $_REQUEST['DUSER_PWD'],
  "new_erp" => $_REQUEST['NEW_ERP'],
 "user_group_id" => $_REQUEST['USER_GROUP_ID'],
 "db_user" => $_REQUEST['db_user'],
 );   
echo json_encode($ss);*/
error_reporting(0);
ini_set('display_error',1);
require '../sysconfig.inc.php';

if($_REQUEST['NEW_ERP'] ==  1)
{
    $user_id = $_REQUEST['DUSER_ID'];
    $_SESSION['DUSER_ID'] = $user_id;
    $user_pwd = $_REQUEST['DUSER_PWD'];
    $_SESSION['DUSER_PWD'] = $user_pwd;
}else{
    $user_id = $_SESSION['DUSER_ID'];
    $user_pwd = $_SESSION['DUSER_PWD'];
}
if($user_id == '' && $user_pwd == '')
{
     session_destroy();
     header('Location:../logout.php');
}


// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';

// session checking
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';

require SIMBIO_BASE_DIR . 'simbio_GUI/template_parser/simbio_template_parser.inc.php';
require LIB_DIR . 'module.inc.php';
require SIMBIO_BASE_DIR . 'simbio_DB/simbio_dbop.inc.php';
echo $_REQUEST['DUSER_PWD'];
if ($_REQUEST['Confirm'] == "Confirm") {

    if (!isset($_REQUEST['temp_id1'])) {
        echo '<script type="text/javascript">';
        echo 'alert(\'' . __('Please Select Any Item !') . '\');';
        echo 'location.href = \'index.php?mod=circulation\';';
        echo '</script>';
        exit();
    }

    if ($_POST['status'] > 0) {
        echo '<script type="text/javascript">';
        echo 'alert(\'' . __('Please Do check Item Status For Purticuler Item!') . '\');';
        echo 'location.href = \'index.php?mod=circulation\';';
        echo '</script>';
        exit();

    }
    /*$due_date="select c.cat_issue_period,m.expire_date from member as m
            left join category_mast as c ON c.cat_mast_user =m.member_type_id where m.member_id like '$_POST[member_id]' and c.material_sub_id=$_POST[material_sub_id]";
            $due_date1=$dbs->query($due_date);
            $due_date2=$due_date1->fetch_row();
            $data1['due_date']=date('Y-m-d', strtotime('+'.$due_date2[0].' days'));

            $datediff =strtotime($due_date2[1])-strtotime($data1['due_date']) ;

            $days_diff=floor($datediff/(60*60*24));

            if ($days_diff<0)
            {

                echo '<script type="text/javascript">';
                echo 'alert(\''.__('Member will Expire before Due Date!').'\');';
                echo 'location.href = \'index.php?mod=circulation\';';
                echo '</script>';
                exit();
    */

    for ($i = 0; $i < count($_POST['temp_id1']); $i++) {
        $data = $_POST['temp_id1'][$i];
        $data1 = $data;
        $data2 = array();

        if (!empty($_POST['values'][$data])) {

            $temp = $_POST['values'][$data];

            $bib = "SELECT biblio_id FROM item WHERE item_code='$temp'";
            $bib1 = $dbs->query($bib);
            $bib2 = $bib1->fetch_row();
            $bib3 = $bib2[0];

            /*$session_bib2="select item_code from item where biblio_id=$bib3";
                                            $session_bib3=$dbs->query($session_bib2);
                                            $session_val1='';
                                            while($row=$session_bib3->fetch_assoc())
                                            {
                                                $session_val1.=$row['item_code'].',';
                                            }
                                                $explode1=explode(",",$session_val1);
                                            if(in_array($temp,$explode1))
                                            {
                                                    echo '<script type="text/javascript">';
                                                    echo 'alert(\''.__('You Cannot Issue Same Copy Of Books').'\');';
                                                    echo 'location.href = \'index.php?mod=circulation\';';
                                                    echo '</script>';
                                                    die();
            */

            $item_request = "select * from temp_request where  temp_id='" . $data . "'  order by req_time limit 1";
            $available_item = $dbs->query($item_request);
            $available_item1 = '';

            while ($available = $available_item->fetch_assoc()) {

                $itemID = $available['temp_id'];
                $item_code = $available['item_code'];

                $item_request_temp = "select item_code from loan where item_code='$item_code' and  return_date is null";
                $available_item_temp = $dbs->query($item_request_temp);

                if ($available_item_temp->num_rows > 0) {

                    $item_request_temp1 = "select item_code from item where biblio_id=$bib3 and item_code not in (select l.item_code from loan as l
                                                                   left join item as it on it.item_code=l.item_code
                                                                   Left Join biblio as b on it.biblio_id=b.biblio_id
                                                                   where l.return_date is null and b.biblio_id=$bib3) limit 1";
                    $available_item_temp1 = $dbs->query($item_request_temp1);
                    $available1 = $available_item_temp1->fetch_assoc();
                    $item_code = $available1['item_code'];

                }

                $_POST['member_id'] = $available['member_id'];
                $time = $available['req_time'];
                $req_date = $available['request_date'];

            }

            $time_of_other_request = "select tm.req_time,request_date from temp_request as tm
                                    inner join item as i on i.item_code=tm.item_code
                                    inner join biblio as b on b.biblio_id=i.biblio_id
                                    where  i.biblio_id=$bib3  and tm.temp_id !='$data'
                                    and tm.temp_id not in (select temp_id from loan where  temp_id >0)";

            $time_of_other_request1 = $dbs->query($time_of_other_request);
            while ($time_of_other_request2 = $time_of_other_request1->fetch_assoc()) {

                if (empty($time_of_other_request2['req_time'])) {
                    $time_of_other_request2['req_time'] = 0;
                }

                /*if($req_date<$time_of_other_request2['request_date'])
                                                                {

                                                                    if ($time_of_other_request2['req_time']<$time)
                                                                    {

                                                                            echo '<script type="text/javascript">';
                                                                            echo 'alert(\''.__('Other member have this Request Before this Member Request Time!').'\');';
                                                                            echo 'location.href = \'index.php?mod=circulation\';';
                                                                            echo '</script>';
                                                                            exit();

                                                                    }
                */
                if ($req_date > $time_of_other_request2['request_date']) {

                    echo '<script type="text/javascript">';
                    echo 'alert(\'' . __('Other member have this Request Before this Member Request Time!') . '\');';
                    echo 'location.href = \'index.php?mod=circulation\';';
                    echo '</script>';
                    exit();

                } elseif ($req_date == $time_of_other_request2['request_date']) {

                    if ($time_of_other_request2['req_time'] < $time) {

                        echo '<script type="text/javascript">';
                        echo 'alert(\'' . __('Other member have this Request Before this Member Request Time!') . '\');';
                        echo 'location.href = \'index.php?mod=circulation\';';
                        echo '</script>';
                        exit();

                    }

                }

            }
            $data2['item_code'] = $item_code;
            $data2['member_id'] = $_POST['member_id'];
            $data2['loan_date'] = date("Y-m-d");
            $data2['temp_id'] = $itemID;

            //$cat_master_data="select category_mast.* from member LEFT JOIN category_mast ON member.member_type_id =category_mast.cat_mast_user where member_id like '$_POST[member_id]' and material_sub_id=$_POST[material_sub_id] ";

            if ($_REQUEST['user_type'][$i] == 2) {
                $cat_master_data = "select category_mast.* from " . $inte_schema . ".tblstaff AS ts
                                        LEFT JOIN category_mast ON ts.user_group_id =category_mast.cat_mast_user
                                        where ts.user_name like '$_POST[member_id]' and material_sub_id=$_POST[material_sub_id] and ts.sub_institute_id = " . $_SESSION['SUB_INSTITUTE_ID'] . " ";
            } else {
                $cat_master_data = "select category_mast.* from " . $inte_schema . ".tblstudent AS ts
                                        LEFT JOIN category_mast ON ts.user_group_id =category_mast.cat_mast_user where enrollment_no like '$_POST[member_id]' and material_sub_id=$_REQUEST[material_sub_id] and ts.sub_institute_id = " . $_SESSION['SUB_INSTITUTE_ID'] . "";

            }
            //echo $cat_master_data;die;
            $cat_master = $dbs->query($cat_master_data);
            $issue_days_limit1 = '';
            while ($issue_days_limit = $cat_master->fetch_assoc()) {

                $issue_days_limit1 .= $issue_days_limit['cat_issue_period'];
                $book_issue_limit = (integer) $issue_days_limit['cat_issue_limit'];
            }
            $issue_days_limit1 = intval($issue_days_limit1);
            $data2['due_date'] = date('Y-m-d', strtotime('+' . $issue_days_limit1 . ' days'));
            if ($_POST['avl_copy'] <= 0 || $_POST['avl_copy'] == '') {
                utility::jsAlert(__('Requested Book is Not available'));
            } else {

                ///LOAN LIMIT CALUCATION
                $no_of_book_issued = "select count(*) as num_of_book from loan where member_id like '$_POST[member_id]' and return_date is null";

                $no_of_book_issued1 = $dbs->query($no_of_book_issued);
                $no_of_book = $no_of_book_issued1->fetch_assoc();
                $tot_issued_book = $no_of_book['num_of_book'];
                if (intval($tot_issued_book) >= $book_issue_limit) {
                    utility::jsAlert('You Have already ' . $tot_issued_book . ' Books and  you have Permission to Issue ' . $book_issue_limit . ' Books !');
                    echo '<script type="text/javascript">';
                    echo 'alert(\'' . __('Loan Limit has been Finished Please Increase It!') . '\');';
                    echo 'location.href = \'./index.php?mod=circulation\';';
                    echo '</script>';
                    exit();

                }

                $sql_op = new simbio_dbop($dbs);
                $failed_array = array();
                $error_num = 0;

                $itemID = explode(',', $itemID);

                foreach ($itemID as $itemID) {
                    $sql = "update temp_request set req_status='confirm',confirm_date='" . date('Y-m-d') . "' where temp_id=" . $data2['temp_id'];
                    $dbs->query($sql);

                    if (!$sql_op->insert('loan', $data2)) {
                        $error_num++;
                    }
                    $itemID = (integer) $itemID;

                }
                if ($error_num == 0) {
                    utility::jsAlert(__('All Data Successfully Confirmed'));
                    echo '<script language="Javascript">parent.setContent(\'mainContent\', \'' . $_SERVER['PHP_SELF'] . '?' . $_POST['lastQueryStr'] . '\', \'post\');</script>';

                } else {
                    utility::jsAlert(__('Some or All Data NOT deleted successfully!\nPlease contact system administrator'));
                    echo '<script language="Javascript">parent.setContent(\'mainContent\', \'' . $_SERVER['PHP_SELF'] . '?' . $_POST['lastQueryStr'] . '\', \'post\');</script>';

                }

            }

        }

    }
}

//added started by parth 22/9/2011
$_priv_q = $dbs->query('SELECT ga.*,mdl.module_path FROM group_access AS ga
                        LEFT JOIN mst_module AS mdl ON ga.module_id=mdl.module_id WHERE ga.group_id=1');
while ($_priv_d = $_priv_q->fetch_assoc()) {
    // init privileges
    // $_SESSION['priv'][$_priv_d['module_path']]['r'] = false;
    // $_SESSION['priv'][$_priv_d['module_path']]['w'] = false;
    if ($_priv_d['r']) {
        $_SESSION['priv'][$_priv_d['module_path']]['r'] = true;
    }
    if ($_priv_d['w']) {
        $_SESSION['priv'][$_priv_d['module_path']]['w'] = true;
    }
}
$_SESSION['logintime'] = time();
// session vars needed by some application modules
$_SESSION['temp_loan'] = array();
$_SESSION['biblioAuthor'] = array();
$_SESSION['biblioTopic'] = array();
$_SESSION['biblioAttach'] = array();
$_SESSION['biblioStandard'] = array();
if (!defined('UCS_VERSION')) {
    // load holiday data from database
    $_holiday_dayname_q = $dbs->query('SELECT holiday_dayname FROM holiday WHERE holiday_date IS NULL');
    $_SESSION['holiday_dayname'] = array();
    while ($_holiday_dayname_d = $_holiday_dayname_q->fetch_row()) {
        $_SESSION['holiday_dayname'][] = $_holiday_dayname_d[0];
    }

    $_holiday_date_q = $dbs->query('SELECT holiday_date FROM holiday WHERE holiday_date IS NOT NULL
                    ORDER BY holiday_date DESC LIMIT 365');
    $_SESSION['holiday_date'] = array();
    while ($_holiday_date_d = $_holiday_date_q->fetch_row()) {
        $_SESSION['holiday_date'][$_holiday_date_d[0]] = $_holiday_date_d[0];
    }
}

// save md5sum of  current application path
$_SESSION['checksum'] = defined('UCS_BASE_DIR') ? md5($_SERVER['SERVER_ADDR'] . UCS_BASE_DIR . 'admin') : md5($_SERVER['SERVER_ADDR'] . SENAYAN_BASE_DIR . 'admin');

// update the last login time
$dbs->query("UPDATE user SET last_login='" . date("Y-m-d H:i:s") . "',
                last_login_ip='" . $_SERVER['REMOTE_ADDR'] . "'
                WHERE user_id=" . $_user_d['user_id']);
//added ended by parth 22/9/2011
// https connection (if enabled)
if ($sysconf['https_enable']) {
    simbio_security::doCheckHttps($sysconf['https_port']);
}

// create the template object
$template = new simbio_template_parser($sysconf['admin_template']['dir'] . '/' . $sysconf['admin_template']['theme'] . '/index_template.html');

// page title
$page_title = $sysconf['library_name'] . ' :: Library Automation System';
// main menu
$module = new module();
$module->setModulesDir(MODULES_BASE_DIR);
$main_menu = $module->generateModuleMenu($dbs);

$current_module = '';

// get module from URL
if (isset($_GET['mod']) AND !empty($_GET['mod'])) {
    $current_module = trim($_GET['mod']);
}
// read privileges
$can_read = utility::havePrivilege($current_module, 'r');
//added by Parth start 24/8/2011
$can_read1 = utility::havePrivilege('bibliography', 'r');
$can_read2 = utility::havePrivilege('circulation', 'r');
$can_read3 = utility::havePrivilege('membership', 'r');
//added by Parth ended 24/8/2011
// submenu
// $sub_menu = $module->generateSubMenu(($current_module AND $can_read)?$current_module:'');
//added by Parth start 24/8/2011
$sub_menu1 = $module->generateSubMenu_admin_home(('bibliography' AND $can_read1) ? 'bibliography' : '');
// $sub_menu1 = '<div class="admin_mid_bg">' . $sub_menu1 . '</div>';
$sub_menu1 =  $sub_menu1 ;
$sub_menu2 = $module->generateSubMenu_admin_home(('circulation' AND $can_read2) ? 'circulation' : '');
//$sub_menu2 = '<div class="cir_mid_bg">' . $sub_menu2 . '</div>';
$sub_menu2 = $sub_menu2 ;
$sub_menu3 = $module->generateSubMenu_admin_home(('membership' AND $can_read3) ? 'membership' : '');
//$sub_menu3 = '<div class="memb_mid_bg">' . $sub_menu3 . '</div>';
$sub_menu3 =  $sub_menu3 ;

//added by Parth ended 24/8/2011
// start the output buffering for main content
ob_start();
// info
//$info = __('Welcome To The Library Automation System, you are currently logged in as').' <strong>'.$_SESSION['realname'].'</strong>'; //mfc
//$info = __('You are currently logged in as').' <strong>'.$_SESSION['realname'].'</strong>'; //mfc
$info1 = __('You are currently logged in as') . ' <strong>' . $_SESSION['realname'] . '</strong>'; //mfc;
// set some javascript vars
echo '<script type="text/javascript">';

if ($current_module) {
    echo 'defaultAJAXurl = \'' . MODULES_WEB_ROOT_DIR . $current_module . '/index.php\';';
} else {
    echo 'defaultAJAXurl = \'' . SENAYAN_WEB_ROOT_DIR . 'admin/default/home.php\';';
}
echo '</script>';

$admin_post = array();
$log_admin_post = array();
$student_post = array();
$teaching_post = array();
$non_teaching_post = array();
$parent_post = array();
$log_student_post = array();
$log_Teaching_Staff_post = array();
$log_non_Teaching_Staff_post = array();
$log_parents_post = array();

if ($_POST['category_user'] == 'category_user' && $_POST['saveData'] == 'Update') {

    $admin_post['material_resource_id'] = 14;
    $admin_post['material_sub_id'] = $_POST['sub_type'];
    $admin_post['cat_mast_user'] = $_REQUEST['ADMIN'];
    $admin_post['cat_issue_limit'] = $_POST['admin_issuelimit'];
    $admin_post['cat_issue_period'] = $_POST['admin_issueperiod'];
    $admin_post['cat_re_issue_limit'] = $_POST['admin_reissuelimit'];
    $admin_post['cat_fine_each_day'] = $_POST['admin_fine'];
    $admin_post['user_name'] = $_SESSION['DUSER_NAME'];
    //$admin_post['inputed_date_time']=date('Y-m-d h:i:s');
    $admin_post['updated_date_time'] = date('Y-m-d h:i:s');

    $student_post['material_resource_id'] = 14;
    $student_post['material_sub_id'] = $_POST['sub_type'];
    $student_post['cat_mast_user'] = $_REQUEST['STUDENT'];
    $student_post['cat_issue_limit'] = $_POST['stu_issuelimit'];
    $student_post['cat_issue_period'] = $_POST['stu_issueperiod'];
    $student_post['cat_re_issue_limit'] = $_POST['stu_reissuelimit'];
    $student_post['cat_fine_each_day'] = $_POST['stu_fine'];
    $student_post['user_name'] = $_SESSION['DUSER_NAME'];
    //$student_post['inputed_date_time']=date('Y-m-d h:i:s');
    $student_post['updated_date_time'] = date('Y-m-d h:i:sa');

    $teaching_post['material_resource_id'] = 14;
    $teaching_post['material_sub_id'] = $_POST['sub_type'];
    $teaching_post['cat_mast_user'] = $_REQUEST['STAFF'];
    $teaching_post['cat_issue_limit'] = $_POST['teach_issuelimit'];
    $teaching_post['cat_issue_period'] = $_POST['teach_issueperiod'];
    $teaching_post['cat_re_issue_limit'] = $_POST['teach_reissuelimit'];
    $teaching_post['cat_fine_each_day'] = $_POST['teach_fine'];
    $teaching_post['user_name'] = $_SESSION['DUSER_NAME'];
    // $teaching_post['inputed_date_time']=date('Y-m-d h:i:s');
    $teaching_post['updated_date_time'] = date('Y-m-d h:i:s');

    $non_teaching_post['material_resource_id'] = 14;
    $non_teaching_post['material_sub_id'] = $_POST['sub_type'];
    $non_teaching_post['cat_mast_user'] = $_REQUEST['EMPLOYEE'];
    $non_teaching_post['cat_issue_limit'] = $_POST['non_tech_issuelimit'];
    $non_teaching_post['cat_issue_period'] = $_POST['non_tech_issueperiod'];
    $non_teaching_post['cat_re_issue_limit'] = $_POST['non_teach_reissuelimit'];
    $non_teaching_post['cat_fine_each_day'] = $_POST['non_teach_fine'];
    $non_teaching_post['user_name'] = $_SESSION['DUSER_NAME'];
    // $non_teaching_post['inputed_date_time']=date('Y-m-d h:i:s');
    $non_teaching_post['updated_date_time'] = date('Y-m-d h:i:s');

    $parent_post['material_resource_id'] = 14;
    $parent_post['material_sub_id'] = $_POST['sub_type'];
    $parent_post['cat_mast_user'] = $_REQUEST['PARENTS'];
    $parent_post['cat_issue_limit'] = $_POST['parnt_issuelimit'];
    $parent_post['cat_issue_period'] = $_POST['parnt_issueperiod'];
    $parent_post['cat_re_issue_limit'] = $_POST['parant_reissuelimit'];
    $parent_post['cat_fine_each_day'] = $_POST['parant_fine'];
    $parent_post['user_name'] = $_SESSION['DUSER_NAME'];
    //$parent_post['inputed_date_time']=date('Y-m-d h:i:s');
    $parent_post['updated_date_time'] = date('Y-m-d h:i:s');

    $error_num = 0;

    $sql_op = new simbio_dbop($dbs);

    /////////LOG INSERT PROCESS////////////////////////
    $admin_data = "select * from category_mast where material_sub_id=$_POST[sub_type] and cat_mast_user=1";
    $admin_data1 = $dbs->query($admin_data);
    $admin_data2 = $admin_data1->fetch_assoc();

    $log_admin_post['material_resource_id'] = $admin_data2['material_resource_id'];
    $log_admin_post['material_sub_id'] = $admin_data2['material_sub_id'];
    $log_admin_post['cat_mst_id'] = $admin_data2['cat_mast_id'];
    $log_admin_post['cat_mast_user'] = $admin_data2['cat_mast_user'];
    $log_admin_post['cat_issue_limit'] = $admin_data2['cat_issue_limit'];
    $log_admin_post['cat_issue_period'] = $admin_data2['cat_issue_period'];
    $log_admin_post['cat_re_issue_limit'] = $admin_data2['cat_re_issue_limit'];
    $log_admin_post['cat_fine_each_day'] = $admin_data2['cat_fine_each_day'];
    $log_admin_post['user_name'] = $_SESSION['DUSER_NAME'];
    $log_admin_post['inputed_date_time'] = $admin_data2['inputed_date_time'];
    $log_admin_post['updated_date_time'] = date('Y-m-d h:i:s');

    if (!$sql_op->insert('Log_category_mst', $log_admin_post)) {
        $error_num++;
    }

    $Student_data = "select * from category_mast where material_sub_id=$_POST[sub_type] and cat_mast_user=2";
    $Student1 = $dbs->query($Student_data);
    $Student2 = $Student1->fetch_assoc();

    $log_student_post['material_resource_id'] = $Student2['material_resource_id'];
    $log_student_post['material_sub_id'] = $Student2['material_sub_id'];
    $log_student_post['cat_mst_id'] = $Student2['cat_mast_id'];
    $log_student_post['cat_mast_user'] = $Student2['cat_mast_user'];
    $log_student_post['cat_issue_limit'] = $Student2['cat_issue_limit'];
    $log_student_post['cat_issue_period'] = $Student2['cat_issue_period'];
    $log_student_post['cat_re_issue_limit'] = $Student2['cat_re_issue_limit'];
    $log_student_post['cat_fine_each_day'] = $Student2['cat_fine_each_day'];
    $log_student_post['user_name'] = $_SESSION['DUSER_NAME'];
    $log_student_post['inputed_date_time'] = $Student2['inputed_date_time'];
    $log_student_post['updated_date_time'] = date('Y-m-d h:i:s');

    if (!$sql_op->insert('Log_category_mst', $log_student_post)) {
        $error_num++;
    }

    $Teaching_Staff_data = "select * from category_mast where material_sub_id=$_POST[sub_type] and cat_mast_user=3";
    $Teaching_Staff_data1 = $dbs->query($Teaching_Staff_data);
    $Teaching_Staff_data2 = $Teaching_Staff_data1->fetch_assoc();

    $log_Teaching_Staff_post['material_resource_id'] = $Teaching_Staff_data2['material_resource_id'];
    $log_Teaching_Staff_post['material_sub_id'] = $Teaching_Staff_data2['material_sub_id'];
    $log_Teaching_Staff_post['cat_mst_id'] = $Teaching_Staff_data2['cat_mast_id'];
    $log_Teaching_Staff_post['cat_mast_user'] = $Teaching_Staff_data2['cat_mast_user'];
    $log_Teaching_Staff_post['cat_issue_limit'] = $Teaching_Staff_data2['cat_issue_limit'];
    $log_Teaching_Staff_post['cat_issue_period'] = $Teaching_Staff_data2['cat_issue_period'];
    $log_Teaching_Staff_post['cat_re_issue_limit'] = $Teaching_Staff_data2['cat_re_issue_limit'];
    $log_Teaching_Staff_post['cat_fine_each_day'] = $Teaching_Staff_data2['cat_fine_each_day'];
    $log_Teaching_Staff_post['user_name'] = $_SESSION['DUSER_NAME'];
    $log_Teaching_Staff_post['inputed_date_time'] = $Teaching_Staff_data2['inputed_date_time'];
    $log_Teaching_Staff_post['updated_date_time'] = date('Y-m-d h:i:s');

    if (!$sql_op->insert('Log_category_mst', $log_Teaching_Staff_post)) {
        $error_num++;
    }

    $Non_Teaching_Staff = "select * from category_mast where material_sub_id=$_POST[sub_type] and cat_mast_user=4";
    $Non_Teaching_Staff1 = $dbs->query($Non_Teaching_Staff);
    $Non_Teaching_Staff2 = $Non_Teaching_Staff1->fetch_assoc();

    $log_non_Teaching_Staff_post['material_resource_id'] = $Non_Teaching_Staff2['material_resource_id'];
    $log_non_Teaching_Staff_post['material_sub_id'] = $Non_Teaching_Staff2['material_sub_id'];
    $log_non_Teaching_Staff_post['cat_mst_id'] = $Non_Teaching_Staff2['cat_mast_id'];
    $log_non_Teaching_Staff_post['cat_mast_user'] = $Non_Teaching_Staff2['cat_mast_user'];
    $log_non_Teaching_Staff_post['cat_issue_limit'] = $Non_Teaching_Staff2['cat_issue_limit'];
    $log_non_Teaching_Staff_post['cat_issue_period'] = $Non_Teaching_Staff2['cat_issue_period'];
    $log_non_Teaching_Staff_post['cat_re_issue_limit'] = $Non_Teaching_Staff2['cat_re_issue_limit'];
    $log_non_Teaching_Staff_post['cat_fine_each_day'] = $Non_Teaching_Staff2['cat_fine_each_day'];
    $log_non_Teaching_Staff_post['user_name'] = $_SESSION['DUSER_NAME'];
    $log_non_Teaching_Staff_post['inputed_date_time'] = $Non_Teaching_Staff2['inputed_date_time'];
    $log_non_Teaching_Staff_post['updated_date_time'] = date('Y-m-d h:i:s');

    if (!$sql_op->insert('Log_category_mst', $log_non_Teaching_Staff_post)) {
        $error_num++;
    }

    $log_parents = "select * from category_mast where material_sub_id=$_POST[sub_type] and cat_mast_user=5";
    $log_parents1 = $dbs->query($log_parents);
    $log_parents2 = $log_parents1->fetch_assoc();

    $log_parents_post['material_resource_id'] = $log_parents2['material_resource_id'];
    $log_parents_post['material_sub_id'] = $log_parents2['material_sub_id'];
    $log_parents_post['cat_mst_id'] = $log_parents2['cat_mast_id'];
    $log_parents_post['cat_mast_user'] = $log_parents2['cat_mast_user'];
    $log_parents_post['cat_issue_limit'] = $log_parents2['cat_issue_limit'];
    $log_parents_post['cat_issue_period'] = $log_parents2['cat_issue_period'];
    $log_parents_post['cat_re_issue_limit'] = $log_parents2['cat_re_issue_limit'];
    $log_parents_post['cat_fine_each_day'] = $log_parents2['cat_fine_each_day'];
    $log_parents_post['user_name'] = $_SESSION['DUSER_NAME'];
    $log_parents_post['inputed_date_time'] = $log_parents2['inputed_date_time'];
    $log_parents_post['updated_date_time'] = date('Y-m-d h:i:s');

    if (!$sql_op->insert('Log_category_mst', $log_parents_post)) {
        $error_num++;
    }

    /////////LOG Update PROCESS////////////////////////

    ////////////////////////////////////////////////////////////////UPDATE DATA/////////////////////////////////////////////////////
    ////ADMIN UPDATE

    if (!$sql_op->update('category_mast', $admin_post, 'cat_mast_user=' . $_REQUEST[ADMIN] . ' and material_sub_id=' . $admin_post['material_sub_id'])) {
        $error_num++;
    }
    ///STUDENT UPDATE
    if (!$sql_op->update('category_mast', $student_post, 'cat_mast_user=' . $_REQUEST[STUDENT] . ' and material_sub_id=' . $student_post['material_sub_id'])) {
        $error_num++;
    }
    ////TEACHING STAFF UPDATE
    if (!$sql_op->update('category_mast', $teaching_post, 'cat_mast_user=' . $_REQUEST[STAFF] . ' and material_sub_id=' . $teaching_post['material_sub_id'])) {
        $error_num++;
    }
    ////NON TEACHING STAFF
    if (!$sql_op->update('category_mast', $non_teaching_post, 'cat_mast_user=' . $_REQUEST[EMPLOYEE] . ' and material_sub_id=' . $non_teaching_post['material_sub_id'])) {
        $error_num++;
    }
    ///PARENTS UPDATE
    if (!$sql_op->update('category_mast', $parent_post, 'cat_mast_user=' . $_REQUEST[PARENTS] . ' and material_sub_id=' . $parent_post['material_sub_id'])) {
        $error_num++;
    }

    /* $error_num = 0;
            $sql_op = new simbio_dbop($dbs);
            if (!$sql_op->insert('category_mast',$student_post))
            {
                    $error_num++;
            }

            $error_num = 0;
            $sql_op = new simbio_dbop($dbs);
            if (!$sql_op->insert('category_mast',$teaching_post))
            {
                    $error_num++;
            }

            $error_num = 0;
            $sql_op = new simbio_dbop($dbs);
            if (!$sql_op->insert('category_mast',$non_teaching_post))
            {
                    $error_num++;
            }

            $error_num = 0;
            $sql_op = new simbio_dbop($dbs);
            if (!$sql_op->insert('category_mast',$parent_post))
            {
                    $error_num++;
            }
    */

    /*if (!$sql_op->update('temp_request',$data,'temp_id='.$itemID))
            {
                    $error_num++;
    */

    /* echo $insert;die;

            echo "<pre>";
            print_r($admin_post);
            echo "<pre>";

            echo "<pre>";
            print_r($student_post);
            echo "<pre>";

            echo "<pre>";
            print_r($teaching_post);
            echo "<pre>";

            echo "<pre>";
            print_r($non_teaching_post);
            echo "<pre>";

            echo "<pre>";
            print_r($parent_post);
    */

}

if ($current_module && $can_read) {

    // get content of module default content with AJAX
    //added and started by Parth 19/8/2011
    if ($current_module == "master_file") {
//added started by Parth 31/8/2011
        /*$bradecum = $_SESSION['realname'].'-><a class="menu" href='.SENAYAN_WEB_ROOT_DIR.'admin/index.php?mod='.$current_module.'>';
    $query = "select module_name from mst_module where module_path = '".$current_module."'";
    $set_query = $dbs->query($query);
    while($row=$set_query->fetch_assoc())
        {
                    $_formated_module_name = ucwords(str_replace('_', ' ', $row['module_name']));
            $bradecum .= $_formated_module_name;
        }
*/
//added ended by Parth 31/8/2011
        $sysconf['page_footer'] .= "\n"
        . '<script type="text/javascript">'
        . 'Event.observe(window, \'load\', function() { lastStr = \'' . addslashes($info) . '\'; registerAdminEvents(); setContent(\'mainContent\', \'' . MODULES_WEB_ROOT_DIR . $current_module . '/publisher.php\', \'get\') });'
            . '</script>';
    } else if ($current_module == "system") {
//added started by Parth 31/8/2011
        /*$bradecum = $_SESSION['realname'].'-><a class="menu" href='.SENAYAN_WEB_ROOT_DIR.'admin/index.php?mod='.$current_module.'>';
    $query = "select module_name from mst_module where module_path = '".$current_module."'";
    $set_query = $dbs->query($query);
    while($row=$set_query->fetch_assoc())
        {
                    $_formated_module_name = ucwords(str_replace('_', ' ', $row['module_name']));
            $bradecum .= $_formated_module_name;
        }
*/
//added ended by Parth 31/8/2011
        $sysconf['page_footer'] .= "\n"
        . '<script type="text/javascript">'
        . 'Event.observe(window, \'load\', function() { lastStr = \'' . addslashes($info) . '\'; registerAdminEvents(); setContent(\'mainContent\', \'' . MODULES_WEB_ROOT_DIR . $current_module . '/app_user.php?changecurrent=true&action=detail\', \'get\') });'
            . '</script>';
    } else {
//added started by Parth 31/8/2011
        /*$bradecum = $_SESSION['realname'].'-><a class="menu" href='.SENAYAN_WEB_ROOT_DIR.'admin/index.php?mod='.$current_module.'>';
    $query = "select module_name from mst_module where module_path = '".$current_module."'";
    $set_query = $dbs->query($query);
    while($row=$set_query->fetch_assoc())
        {
                    $_formated_module_name = ucwords(str_replace('_', ' ', $row['module_name']));
            $bradecum .= $_formated_module_name;
        }
*/
//added ended by Parth 31/8/2011
        //include 'modules/'.$current_module.'/index.php';
        $sysconf['page_footer'] .= "\n"
        . '<script type="text/javascript">'
        . 'Event.observe(window, \'load\', function() { lastStr = \'' . addslashes($info) . '\'; registerAdminEvents(); setContent(\'mainContent\', \'' . MODULES_WEB_ROOT_DIR . $current_module . '/index.php\', \'get\') });'
            . '</script>';
    }
//added and ended by Parth 19/8/2011
} else {

//added started by Parth 31/8/2011
    /*$bradecum = $_SESSION['realname'].'-><a class="menu" href='.SENAYAN_WEB_ROOT_DIR.'admin/index.php>';
        $bradecum .= 'Home';
$bradecum .= '</a>';*/
//added and ended by Parth 19/8/2011
    include 'default/home.php';
    $sysconf['page'] = '1';
    $sysconf['page_footer'] .= "\n"
    . '<script type="text/javascript">Event.observe(window, \'load\', function() { lastStr = \'' . addslashes($info) . '\'; registerAdminEvents(); });</script>';
/*$sysconf['page_footer'] .= "\n"
.'<script type="text/javascript">'
.'Event.observe(window, \'load\', function() { lastStr = \''.addslashes($info).'\'; registerAdminEvents(); setContent(\'mainContent\', \''.SENAYAN_WEB_ROOT_DIR.'admin/default/home.php\', \'get\') });'
.'</script>';*/
}

//added and staretd by Parth 24/8/2011
$sub_menu_new = '';
$sub_menu_new .= '<div class="cal_top_bg"><p class="title">Calender</p></div><div class="cal_mid_bg"><iframe src=calender.php width=170px height=242px frameBorder=0></iframe></div><div class="cal_bot_bg"></div>';
$sub_menu_new .= '<div class="holiday_top_bg"><p class="title">Holiday List</p></div>';
//holiday setting
$sub_menu_new .= '<div class="holiday_mid_bg"><marquee direction="up" onmouseover="this.stop()" onmouseout="this.start()" scrollamount="2" height=120px><table border=0 width=160px><tr bgcolor=#CCCCCC><td>Day</td><td>Date</td><td>Description</td></tr>';
$set_holidays = $dbs->query('select * from holiday ORDER BY holiday_id DESC LIMIT 0,2');
while ($data = $set_holidays->fetch_assoc()) {
    $sub_menu_new .= "<tr valign=top><td>$data[holiday_dayname]</td><td>" . date('d-m-Y', strtotime($data[holiday_date])) . "</td><td>$data[description]</td></tr>";
}

$sub_menu_new .= '<tr align=right><td colspan=3><table><tr><td><a class="subMenuItem" href=' . MODULES_WEB_ROOT_DIR . 'system/holiday.php>See</a></td></tr></table></td></tr></table></marquee></div><div class="holiday_bot_bg"></div>';
$sub_menu_new .= '<div class="recent_top_bg"><p class="title">Recent Activity</p></div>';
//holiday setting
$sub_menu_new .= '<div  class="recent_mid_bg"><marquee direction="up" onmouseover="this.stop()" onmouseout="this.start()" scrollamount="2" height=120px><table border=0 width=180px><!--<tr bgcolor=#CCCCCC><td>Time</td><td>Location</td><td>Message</td></tr>-->';
$set_holidays1 = $dbs->query('SELECT log_date, log_location, log_msg FROM system_log ORDER BY log_date DESC LIMIT 0,2');
while ($data1 = $set_holidays1->fetch_assoc()) {
    $sub_menu_new .= "<tr valign=top><!--<td>$data1[log_date]</td><td>$data1[log_location]</td>--><td>$data1[log_msg]</td></tr>";
//$sub_menu_new .= "<tr><td>$data1[log_date]</td><td>$data1[log_location]</td><td>$data1[log_msg]</td></tr>";
}

$sub_menu_new .= '<tr align=right><td colspan=3><table><tr><td><a class="subMenuItem" href=' . MODULES_WEB_ROOT_DIR . 'system/sys_log.php>See</a></td></tr></table></td></tr></table></marquee></div><div class="recent_bot_bg"></div>';

//added and ended by Parth 24/8/2011

// page content
$main_content = ob_get_clean();

// assign content to markers
$template->assign('<!--PAGE_TITLE-->', $page_title);
//added started by Parth 24/8/2011
$template->assign('<!--LOGIN-->', $info1);
//added ended by Parth 24/8/2011
$template->assign('<!--CSS-->', $sysconf['admin_template']['css']);
$template->assign('<!--MAIN_MENU-->', $main_menu);
//added and commented started by Parth 24/8/2011
if ($current_module) {
    $template->assign('<!--SUB_MENU-->', $sub_menu);
} else {
    $template->assign('<!--SUB_MENU-->', $sub_menu_new);
}
//$template->assign('<!--BAEDCUM_MENU-->', $bradecum);
//added and commented ended by Parth 24/8/2011
$template->assign('<!--INFO-->', $info);
$template->assign('<!--LIBRARY_NAME-->', $sysconf['library_name']);
$template->assign('<!--LIBRARY_SUBNAME-->', $sysconf['library_subname']);
$template->assign('<!--MAIN_CONTENT-->', $main_content);
$template->assign('<!--FOOTER-->', $sysconf['page_footer']);
// print out the template
$template->printOut();

if (isset($_REQUEST['submit']) == 'Confirm') {

    $confirm_date1 = date("Y-m-d");
    $confirm_date .= "'" . str_replace("\'", "''", $confirm_date1) . "'";
    $fields = $values = '';

    foreach ($_REQUEST['values'] as $column => $value) {

        $fields .= $column . ",";

        $values .= "'" . str_replace("\'", "''", $value) . "',";

    }

    $values = substr($values, 0, -1);

    if ($values) {

        $sql = 'UPDATE temp_request set status="Confirm",confirm_date=' . $confirm_date . ' where item_code IN(' . $values . ')';
        $dbs->query($sql);
    }

    if ($values != '') {
        echo '<script type="text/javascript">';
        echo 'alert(\'' . __('Book Request Confirmed') . '\');';
        //echo 'location.href = \'modules/circulation/index.php\';';
        echo '</script>';
    }

}
// echo 'hiiii';
// echo '<script type="text/javascript">';
// echo 'location.href = \'https://erp.triz.co.in/library/admin/index.php\';';
// echo '</script>';
?>
<!-- <script type="text/javascript">
    jQuery(document).ready(function(){
       var href = window.location.href,
           newUrl = href.substring(0, href.indexOf('?'))
       window.history.replaceState({}, '', newUrl);
    });
</script> -->

