<?php
error_reporting(1);
$sertracking=array();
$sertracking=explode('/', $_SERVER[SCRIPT_FILENAME]);
$data_file='/'.$sertracking[1].'/'.$sertracking[2].'/'.$sertracking[3].'/'.$sertracking[4].'/data.php';



include_once 'sysconfig.inc.php'; 
require LIB_DIR.'member_logon.inc.php';     
include_once LIB_DIR.'member_session.inc.php';
include_once LIB_DIR.'contents/common.inc.php';  
include_once SIMBIO_BASE_DIR.'simbio_DB/mysql/simbio_mysql_result.inc.php';  

 
//require $data_file;
//echo "<pre>";
//print_r(get_included_files());
$is_member_login = utility::isMemberLogin();

// member's password changing flags
define('CURR_PASSWD_WRONG', -1);
define('PASSWD_NOT_MATCH', -2);
define('CANT_UPDATE_PASSWD', -3);

// if member is logged out
if (isset($_GET['logout']) && $_GET['logout'] == '1')
{

    // write log
    utility::writeLogs($dbs, 'tblstudent', $_SESSION['email'], 'Login', $_SESSION['DUSER_NAME'].' Log Out from address '.$_SERVER['REMOTE_ADDR']);
$id = $_SERVER['REMOTE_ADDR']; 
$id = $id.".php";
$myFile = SENAYAN_BASE_DIR.$id;
unlink($myFile);
    // completely destroy session cookie
    simbio_security::destroySessionCookie(null, SENAYAN_MEMBER_SESSION_COOKIES_NAME, SENAYAN_WEB_ROOT_DIR, false);
    //header('Location: index.php?p=member'); //comment by iresh on 14-5-2011
    header('Location: index.php');//added by iresh on 14-5-2011
    header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    header('Pragma: no-cache');
    exit();
}


// if there is member login action
if (isset($_POST['logMeIn'])){

    if($_REQUEST['user']=='member')
   {

   // comment by iresh on 22-1-2011 $username = trim(strip_tags($_REQUEST['txtusername']));
    // comment by iresh on 22-1-2011 $password = trim(strip_tags($_POST['txtpassWord']));
    /* added by iresh on 22-1-2011*/ $username = trim(strip_tags($_REQUEST['uname']));
    /* added by iresh on 22-1-2011*/  $password =trim(strip_tags( $_REQUEST['pass']));
	//$_SESSION['m']=$_REQUEST['uname'];
				      
    // check if username or password is empty
    if (!$username OR !$password) {
        //echo '<div class="errorBox">'.gettext('Please fill your Username and Password to Login!').'</div>';
	    echo '<script type="text/javascript">';
            echo 'alert(\''.gettext('Please fill your Username and Password to Login!').'\');';
            echo 'location.href = \'index.php\';';
            echo '</script>';
    } else {
        // regenerate session ID to prevent session hijacking
        session_regenerate_id(true);
        // create logon class instance

        $logon = new member_logon($username, $password);
        if ($logon->valid($dbs)) {
            // write log
            utility::writeLogs($dbs, 'tblstudent', $username, 'Login', 'Login success for member '.$username.' from address '.$_SERVER['REMOTE_ADDR']);


			//added by parth 30/06/2011
             if($_GET['lms']=='set' && $_GET['lms1']=='set')
            {
            header('Location: index.php?q='.$_GET['q'].'&Search=Keyword+Search&search1=Search&material_sub_type_select='.$_GET['material_sub_type_select']);
            }
            else
            { 
             header('Location: index.php');
	     } 
	    //header('Location: index.php');	
			//ended addition by parth 30/06/2011
            exit();
        } else {
            // write log
            utility::writeLogs($dbs, 'tblstudent', $username, 'Login', 'Login FAILED for member '.$username.' from address '.$_SERVER['REMOTE_ADDR']);
            // message
            $msg = '<div class="errorBox">'.gettext('Login FAILED! Wrong username or password!').'</div>';
//added by Parth 28/7/2011	     	
$id = $_SERVER['REMOTE_ADDR']; 
$id = $id.".php";
$myFile = SENAYAN_BASE_DIR.$id;
unlink($myFile);
//ended added by Parth 28/7/2011
            simbio_security::destroySessionCookie($msg, SENAYAN_MEMBER_SESSION_COOKIES_NAME, SENAYAN_WEB_ROOT_DIR, false);
            header('Location: index.php');
        }
       
    }
   }
	else if($_REQUEST['user']=='admin') //added by iresh on 22-1-2011
	{
		

		  if (defined('LIGHTWEIGHT_MODE')) 
		  {
		    header('Location: index.php');
		  }

		// required file
		require LIB_DIR.'admin_logon.inc.php';


		// https connection (if enabled)
		if ($sysconf['https_enable']) {
		    simbio_security::doCheckHttps($sysconf['https_port']);
		}

		// check if session browser cookie already exists
		//if (isset($_COOKIE['admin_logged_in'])) {
		 //   header('location: admin/index.php');
		//}

				/* added by iresh on 22-1-2011*/  $username = trim(strip_tags($_REQUEST['uname']));
				/* added by iresh on 22-1-2011*/  $password =trim(strip_tags( $_REQUEST['pass']));


		if (!$username OR !$password)
               {
			echo '<script type="text/javascript">alert(\''.gettext('Please supply valid username and password').'\');</script>';
			echo '<script type="text/javascript">';
                        echo 'alert(\''.gettext('Please supply valid username and password!').'\');';
                        echo 'location.href = \'index.php\';';
                        echo '</script>';
			    	

	       } 
               else {
			// destroy previous session set in OPAC
			simbio_security::destroySessionCookie(null, SENAYAN_MEMBER_SESSION_COOKIES_NAME, SENAYAN_WEB_ROOT_DIR, false);
			require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
			// regenerate session ID to prevent session hijacking
			session_regenerate_id(true);
			// create logon class instance
			$logon = new admin_logon($username, $password);
			if ($logon->adminValid($dbs)) {
			    // set cookie admin flag
			    setcookie('admin_logged_in', true, time()+14400, SENAYAN_WEB_ROOT_DIR);
			    // write log
			    utility::writeLogs($dbs, 'staff', $username, 'Login', 'Login success for user '.$username.' from address '.$_SERVER['REMOTE_ADDR']);
			    echo '<script type="text/javascript">';
			    if ($sysconf['login_message']) {
				echo 'alert(\''.gettext('Welcome to Library Automation, ').$logon->real_name.'\');';
			    }
			    echo 'location.href = \'admin/index.php\';';
			    echo '</script>';
			    exit();
			} else {
			   /*comment by iresh on 22-1-2011 // write log
			    utility::writeLogs($dbs, 'staff', $username, 'Login', 'Login FAILED for user '.$username.' from address '.$_SERVER['REMOTE_ADDR']);
			    // message
			    $msg = '<script type="text/javascript">';
			    $msg .= 'alert(\''.gettext('Wrong Username or Password. ACCESS DENIED').'\');';
			    $msg .= 'history.back();';
			    $msg .= '</script>';
			    simbio_security::destroySessionCookie($msg, SENAYAN_SESSION_COOKIES_NAME, SENAYAN_WEB_ROOT_DIR.'admin', false);
			   // exit();
                           */
			    // write log
			    //added by iresh on 22-1-2011
			    utility::writeLogs($dbs, 'tblstudent', $username, 'Login', 'Login FAILED for user '.$username.' from address '.$_SERVER['REMOTE_ADDR']);
			    // message
			    $msg = '<div class="errorBox">'.gettext('Login FAILED! Wrong username or password!').'</div>';
//added by Parth 28/7/2011	     	
$id = $_SERVER['REMOTE_ADDR']; 
$id = $id.".php";
$myFile = SENAYAN_BASE_DIR.$id;
unlink($myFile);

//ended added by Parth 28/7/2011
			    simbio_security::destroySessionCookie($msg, SENAYAN_MEMBER_SESSION_COOKIES_NAME, SENAYAN_WEB_ROOT_DIR, false);
header('Location: index.php');
          			
			
                             
			}
		    }


	}
}

// check if member already login
if (!$is_member_login) {
?>
   
   <!--commnet by iresh on 22-1-2011 <fieldset id="memberLogin">-->
    <!--comment by iresh on 11/1/2011 <legend><?php echo gettext('Library Member Login'); ?></legend>-->
    <!-- comment by iresh on 11/1/2011 <div class="loginInfo"><?php echo gettext('Please insert your member ID and password given by library system administrator. If you are library\'s member and don\'t have a password yet, please contact library staff.'); ?></div>-->
   <!--commnet by iresh on 22-1-2011 <form action="index.php?p=member" method="post">-->
 <!-- added by iresh 11/1/2011--> <!--comment by iresh on 22-1-2011 <legend><?php echo gettext('Library Member Login'); ?></legend>-->
  <!--commnet by iresh on 22-1-2011  <br/>
       <div class="fieldLabel"><?php echo gettext('Member ID'); ?></div>
        <div><input type="text" name="memberID" /></div>
    <div class="fieldLabel marginTop"><?php echo gettext('Password'); ?></div>
        <div><input type="password" name="memberPassWord" /></div>
    <div class="marginTop"><input type="submit" name="logMeIn" value="<?php echo gettext('Login'); ?>" />
    </div>
    </form>
    </fieldset>-->
<?php



    /*
     * Function to show member change password form
     *
     * @return      string
     */
    function changePassword()
    {
        // show the member information
        $_form = '<form id="memberChangePassword" method="post" action="index.php?p=member">'."\n";
        $_form .= '<table class="memberDetail" cellpadding="5" cellspacing="0">'."\n";
        $_form .= '<tr>'."\n";
        $_form .= '<td class="alterCell" width="20%"><strong>'.gettext('Current Password').'</strong></td>';
        $_form .= '<td class="alterCell2"><input type="password" name="currPass" /></td>';
        $_form .= '</tr>'."\n";
        $_form .= '<tr>'."\n";
        $_form .= '<td class="alterCell" width="20%"><strong>'.gettext('New Password').'</strong></td>';
        $_form .= '<td class="alterCell2"><input type="password" name="newPass" /></td>';
        $_form .= '</tr>'."\n";
        $_form .= '<tr>'."\n";
        $_form .= '<td class="alterCell" width="20%"><strong>'.gettext('Confirm Password').'</strong></td>';
        $_form .= '<td class="alterCell2"><input type="password" name="newPass2" /></td>';
        $_form .= '</tr>'."\n";
        $_form .= '<tr>'."\n";
        $_form .= '<td class="alterCell2" colspan="2"><input type="submit" name="changePass" value="'.gettext('Change Password').'" /></td>';
        $_form .= '</tr>'."\n";
        $_form .= '</table>'."\n";
        $_form .= '</form>'."\n";

        return $_form;
    }


    /*
     * Function to process member's password changes
     *
     * @param       string      $str_curr_pass = member's current password
     * @param       string      $str_new_pass = member's new password request
     * @param       string      $str_conf_new_pass = member's new password request confirmation
     * @return      boolean     true on success, false on failed
     */
    function procChangePassword($str_curr_pass, $str_new_pass, $str_conf_new_pass)
    {
        global $dbs;
        // current password checking
        $_sql_pass_check = sprintf('SELECT enrollment_no FROM '.$inte_schema.'.tblstudent
            WHERE password=MD5(\'%s\') AND enrollment_no=\'%s\'', 
            $dbs->escape_string(trim($str_curr_pass)), $dbs->escape_string(trim($_SESSION['mid'])));
        $_pass_check = $dbs->query($_sql_pass_check);
        if ($_pass_check->num_rows == 1) {
            $str_new_pass = trim($str_new_pass);
            $str_conf_new_pass = trim($str_conf_new_pass);
            // password confirmation check
            if ($str_new_pass && $str_conf_new_pass && ($str_new_pass === $str_conf_new_pass)) {
                $_sql_update_mpasswd = sprintf('UPDATE '.$inte_schema.'.tblstudent SET password=MD5(\'%s\')
                    WHERE enrollment_no=\'%s\'', $dbs->escape_string($str_conf_new_pass), $dbs->escape_string(trim($_SESSION['mid'])));
                @$dbs->query($_sql_update_mpasswd);
                if (!$dbs->error) {
                    return true;
                } else {
                    return CANT_UPDATE_PASSWD;
                }
            } else {
                return PASSWD_NOT_MATCH;
            }
        } else {
            return CURR_PASSWD_WRONG;
        }
    }


    /*
     * Function to show membership detail of logged in member
     *
     * @return      string
     */
    function showMemberDetail()
    {
        
        global $dbs;
        
          //error_reporting(1);
          $sertracking=array();
          $sertracking=explode('/', $_SERVER[SCRIPT_FILENAME]);
          $data_file='/'.$sertracking[1].'/'.$sertracking[2].'/'.$sertracking[3].'/'.$sertracking[4].'/data.php';
  
            require $data_file;
                        
            
        
            $sql_string .=" select m.expire_date,m.created_on,mst.material_sub_name , cm.cat_issue_limit, cm.cat_issue_period, cm.cat_re_issue_limit, cm.cat_fine_each_day 
                            from category_mast cm left join category_user cu on cu.user_id=cm.cat_mast_user  ";
            $sql_string .=" left join mst_material_sub_type mst on mst.material_sub_id=cm.material_sub_id  ";
            $sql_string .=" left join ".$inte_schema.".tblstudent m on m.user_group_id=cm.cat_mast_user and m.sub_institute_id = '".$_SESSION['SUB_INSTITUTE_ID']."'";
            $sql_string .=" where m.enrollment_no like '$_SESSION[DUSER_ID]' ";
                  
            $detail_info = $dbs->query($sql_string);  
            
            
            
        
        if ($detail_info->num_rows <= 0) 
             {
                $sql_string="";
                $sql_string .=" select m.expire_date,m.created_on,mst.material_sub_name , cm.cat_issue_limit, cm.cat_issue_period, cm.cat_re_issue_limit, cm.cat_fine_each_day from category_mast cm left join category_user cu on cu.user_id=cm.cat_mast_user  ";
                $sql_string .=" left join mst_material_sub_type mst on mst.material_sub_id=cm.material_sub_id  ";
                $sql_string .=" left join ".$inte_schema.".tblstaff m on m.user_group_id=cm.cat_mast_user and m.sub_institute_id = '".$_SESSION['SUB_INSTITUTE_ID']."'";
                $sql_string .=" where m.user_name like '$_SESSION[DUSER_ID]' ";
                
                $detail_info = $dbs->query($sql_string);  
             }
        
                
        $detail_info_data = $detail_info->fetch_assoc();
        
        
        if($detail_info_data['expire_date'])
        {
            $detail_info_data['expire_date']=='N/A';
        }
       
       
      
        
        $_detail = '<table class="memberDetail" cellpadding="5" cellspacing="0">'."\n";        
        if ($_SESSION['m_is_expired']) 
        {
            $_detail .= '<tr>'."\n";
            $_detail .= '<td class="alterCell" width="15%"><strong>Notes</strong></td><td class="alterCell2" colspan="3">';
            if ($_SESSION['is_expired']) {
                $_detail .= '<div style="color: #f00;">'.gettext('Your Membership Already EXPIRED! Please extend your membership.').'</div>';
            }

            $_detail .= '</td>';
            $_detail .= '</tr>'."\n";
        }
          
	
        $_detail .= '<tr>'."\n";
        $_detail .= '<td class="alterCell" width="15%"><strong>'.gettext('Member Name').'</strong></td><td class="alterCell2" width="30%">'.$_SESSION['DUSER_NAME'].'</td>';
        $_detail .= '<td class="alterCell" width="15%"><strong>'.gettext('Member ID').'</strong></td><td class="alterCell2" width="30%">'.$_SESSION['DUSER_ID'].'</td>';
        $_detail .= '</tr>'."\n";
        $_detail .= '<tr>'."\n";
        $_detail .= '<td class="alterCell" width="15%"><strong>'.gettext('Member Email').'</strong></td><td class="alterCell2" width="30%">'.$_SESSION['Email'].'</td>';
        $_detail .= '<td class="alterCell" width="15%"><strong>'.gettext('Member Type').'</strong></td><td class="alterCell2" width="30%">'.$_SESSION['USER_GROUP'].'</td>';
        $_detail .= '</tr>'."\n";
        $_detail .= '<tr>'."\n";
        $_detail .= '<td class="alterCell" width="15%"><strong>'.gettext('Register Date').'</strong></td><td class="alterCell2" width="30%">'.date("d-m-Y", strtotime($detail_info_data['created_on'])).'</td>';
        $_detail .= '<td class="alterCell" width="15%"><strong>'.gettext('Expiry Date').'</strong></td><td class="alterCell2" width="30%">'.date("d-m-Y", strtotime($detail_info_data['expire_date'])).'</td>';
        $_detail .= '</tr>'."\n";               
        $_detail .= '</table>'."\n";

       while($detail_info_data = $detail_info->fetch_row())
       {


       
        
        $_detail .= '<h3 class="memberInfoHead">'.gettext($detail_info_data[material_sub_name]).'</h3>'."\n";
        $_detail .= '<table class="memberDetail" cellpadding="5" cellspacing="0">'."\n";              
        $_detail .= '<tr>'."\n";        
            $_detail .= '<td class="alterCell" width="15%"><strong>'.gettext('Issue Limit ').'</strong></td><td class="alterCell2" width="30%">'.$detail_info_data[cat_issue_limit].'</td>';                               
            $_detail .= '<td class="alterCell" width="15%"><strong>'.gettext('Issue Period ').'</strong></td><td class="alterCell2" width="30%">'.$detail_info_data[cat_issue_period].'</td>';                               
        $_detail .= '</tr>'."\n";
        
        $_detail .= '<tr>'."\n";        
                $_detail .= '<td class="alterCell" width="15%"><strong>'.gettext('Re Issue Limit ').'</strong></td><td class="alterCell2" width="30%">'.$detail_info_data[cat_re_issue_limit].'</td>';                                               
                $_detail .= '<td class="alterCell" width="15%"><strong>'.gettext('Fine Each Day ').'</strong></td><td class="alterCell2" width="30%">'.$detail_info_data[cat_fine_each_day].'</td>';                               
        $_detail .= '</tr>'."\n";

        $_detail .= '</table>';
       }
       
        return $_detail;
		
    }

    /* callback function to show overdue */
    function showOverdue($obj_db, $array_data)
    {
        $_curr_date = date('Y-m-d');
        if (simbio_date::compareDates($array_data[3], $_curr_date) == $_curr_date) {
            return '<strong style="color: #f00;">'.$array_data[3].' '.gettext('OVERDUED').'</strong>';
        } else {
            return $array_data[3];
        }
    }


    /*
     * Function to show list of logged in member loan
     *
     * @param       int         number of loan records to show
     * @return      string
     */
    function showLoanList($num_recs_show = 20)
    {
        global $dbs;
//        require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
//        require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
//        require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
//        require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_date.inc.php';

        // table spec
        $_table_spec = 'loan AS l
            LEFT JOIN '.$inte_schema.'.tblstudent AS m ON l.member_id=m.enrollment_no and m.sub_institute_id = '".$_SESSION['SUB_INSTITUTE_ID']."'
            LEFT JOIN item AS i ON l.item_code=i.item_code
            LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id';

        // create datagrid
        $_loan_list = new simbio_datagrid();
        $_loan_list->setSQLColumn('l.item_code AS \''.gettext('Item Code').'\'',
            'b.title AS \''.gettext('Title').'\'',
            'l.loan_date AS \''.gettext('Loan Date').'\'',
            'l.due_date AS \''.gettext('Due Date').'\'');
        $_loan_list->setSQLorder('l.loan_date DESC');
        $_criteria = sprintf('m.enrollment_no=\'%s\' AND l.is_lent=1 AND is_return=0 ', $_SESSION['mid']);
        $_loan_list->setSQLCriteria($_criteria);

        // modify column value
        $_loan_list->modifyColumnContent(3, 'callback{showOverdue}');
        // set table and table header attributes
        $_loan_list->table_attr = 'align="center" class="memberLoanList" cellpadding="5" cellspacing="0"';
        $_loan_list->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
        $_loan_list->using_AJAX = false;
        // return the result
        $_result = $_loan_list->createDataGrid($dbs, $_table_spec, $num_recs_show);
        $_result = '<div class="memberLoanListInfo">'.$_loan_list->num_rows.' '.gettext('item(s) currently on loan').'</div>'."\n".$_result;
        return $_result;
    }

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    function showLoanhistory($num_recs_show = 20)
    {
        global $dbs;
       // require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
       // require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
       // require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
        //require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_date.inc.php';

        // table spec
        $_table_spec = 'loan AS l

            LEFT JOIN member AS m ON l.member_id=m.member_id
            LEFT JOIN item AS i ON l.item_code=i.item_code
            LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id';

        // create datagrid
        $_loan_list = new simbio_datagrid();
        $_loan_list->setSQLColumn('l.item_code AS \''.gettext('Item Code').'\'',
            'b.title AS \''.gettext('Title').'\'',
            'l.loan_date AS \''.gettext('Loan Date').'\'',
            'l.due_date AS \''.gettext('Due Date').'\'');
        $_loan_list->setSQLorder('l.loan_date DESC');
        $_criteria = sprintf('m.member_id=\'%s\' AND l.is_lent=1 AND is_return=1 ', $_SESSION['mid']);
        $_loan_list->setSQLCriteria($_criteria);

        // modify column value
        $_loan_list->modifyColumnContent(3, 'callback{showOverdue}');
        // set table and table header attributes
        $_loan_list->table_attr = 'align="center" class="memberLoanList" cellpadding="5" cellspacing="0"';
        $_loan_list->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
        $_loan_list->using_AJAX = false;
        // return the result
        $_result = $_loan_list->createDataGrid($dbs, $_table_spec, $num_recs_show);
        //$_result = '<div class="memberLoanListInfo">'.$_loan_list->num_rows.' '.gettext('item(s) currently on loan').'</div>'."\n".$_result;
        return $_result;
    }

   
function showbookrequest($num_recs_show = 20)//added by iresh on 17-2-2011
{

        global $dbs;

       $memberID = trim($_SESSION['DUSER_ID']);

$loan_list_query = $dbs->query("SELECT L.confirm_date,L.req_status,L.temp_id, b.title, i.item_code, L.request_date FROM temp_request AS L
        LEFT JOIN item AS i ON L.item_code=i.item_code
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        LEFT JOIN ".$inte_schema.".tblstudent AS m ON L.member_id=m.enrollment_no and m.sub_institute_id = '".$_SESSION['SUB_INSTITUTE_ID']."'
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        WHERE  L.member_id='$memberID'");

  echo "<form method=post action=index.php?p=member>";	
  echo "<table width=100% cellspacing=0 cellpadding=5  class='memberLoanList'>";
  echo "<tr class='dataListHeader' style='font-weight: bold;'><td>Delete</td><td>Title</td><td>Request Date</td><td>Confirm Date</td><td>Status</td></tr>";
 
  
  $i='';	
  while ($loan_list_data = $loan_list_query->fetch_assoc())
 {

                    if($loan_list_data['confirm_date']!='')
                    {
                    $loan_list_data['confirm_date']=date("d-m-Y", strtotime($loan_list_data['confirm_date']));
                    }
	echo "<tr>";
	echo "<td class='alterCell2'><input type='checkbox' name='checkbox[]' id='checkbox[]' value=".$loan_list_data['temp_id']." ></td>";
       //	echo "<td>" . $loan_list_data['item_code'] ."</td>";
	echo "<td class='alterCell2'>" . $loan_list_data['title'] ."</td>";
	echo "<td class='alterCell2'>" . $loan_list_data['request_date'] ."</td>";
	echo "<td class='alterCell2'>" . $loan_list_data['confirm_date'] ."</td>";
        echo "<td class='alterCell2'>" . $loan_list_data['req_status'] ."</td>";
	
	echo "</tr>";
       $i++;
}
echo "<tr><td align=left class='button'><input  type='submit' name='submit' value='Remove Request'></td></tr>";
echo "</table>";

echo "</form>";




}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    // if there is change password request
    if ($is_member_login && isset($_POST['changePass'])) {
        $change_pass = procChangePassword($_POST['currPass'], $_POST['newPass'], $_POST['newPass2']);
        if ($change_pass === true) {
            $info = '<span style="font-size: 120%; font-weight: bold;">'.gettext('Your password have been changed successfully.').'</span>';
        } else {
            if ($change_pass === CURR_PASSWD_WRONG) {
                $info = gettext('Current password entered WRONG! Please insert the right password!');
            } else if ($change_pass === PASSWD_NOT_MATCH) {
                $info = gettext('Password confirmation FAILED! Make sure to check undercase or uppercase letters!');
            } else {
                $info = gettext('Password update FAILED! ERROR ON DATABASE!');
            }
            $info = '<span style="font-size: 120%; font-weight: bold; color: red;">'.$info.'</span>';
        }
    }

    // show all
    echo '<h3 class="memberInfoHead">'.gettext('Member Detail').'</h3>'."\n";
    echo showMemberDetail();
    	
}
?>
