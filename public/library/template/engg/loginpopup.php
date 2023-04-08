<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr"><head><title><?php echo $page_title; ?></title><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<link rel="icon" href="webicon.ico" type="image/x-icon" />
<link rel="shortcut icon" href="webicon.ico" type="image/x-icon" />
<link href="template/core.style.css" rel="stylesheet" type="text/css" />
<link href="template/igos/960.css" rel="stylesheet" type="text/css" />
<link href="<?php echo $sysconf['template']['css']; ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/prototype.js"></script>
<script type="text/javascript" src="js/form.js"></script>
<script type="text/javascript" src="js/gui.js"></script>

<body>
 <div class="block-header1"><?php echo 'Login'; ?></div>
            <form name="login" action="../../index.php?p=member" method="post">
            <div class="fieldLabel"><?php echo 'Id/Username' ?>:</div>
            <input type="text" name="uname" value="<?php echo 'Enter Username'.$_REQUEST['username']; ?>" onclick="this.value=''" style="width:115px;"/>
	    <div class="fieldLabel marginTop"><?php echo 'Password' ?>:</div>
	    <input type="password" name="pass" value="<?php echo 'Enter Password'. $_REQUEST['password']; ?>" onclick="this.value=''"/>
	    <div class="fieldLabel marginTop"><?php echo 'User Profile' ?>:</div>	
	   <!-- added by iresh on 9-2-2011-->
             <?php	             
		$member_status = 'unchecked';
		$admin_status = 'unchecked';

		
		if($_REQUEST['profile']=='teacher' || $_REQUEST['profile']=='student' )
	        {
		$member_status = 'checked';
		}
		else if ($_REQUEST['profile']=='admin') {
		$admin_status = 'checked';
		}
		else
		{
			$member_status = 'checked';
		}

	
	   ?>
		
             <input type="radio" name="user" value="member"<?php print $member_status; ?>/>member
             <input type="radio" name="user" value="admin" <?php print $admin_status; ?>/>admin
           <!-- end by iresh on 9-2-2011-->
             <br>
            <input type="submit" name="logMeIn" value="<?php echo 'login'; ?>" class="button marginTop" />
            </form>
                 

</body>
</html>
