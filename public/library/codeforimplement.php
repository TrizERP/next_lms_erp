<?php
error_reporting(0);
if(empty($_GET['p']) && empty($_SESSION['USER_GROUP_ID']))
{
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
	<head>
		<title>Library Management System</title>
		<style>
		.black_overlay{
			display: none;
			position: absolute;
			top: 0%;
			left: 0%;
			width: 100%;
			height: 100%;
			background-color: black;
			z-index:1001;
			-moz-opacity: 0.8;
			opacity:.80;
			filter: alpha(opacity=80);
		}
		.white_content {
			display: none;
			position: absolute;
			top: 25%;
			left: 25%;
			width: 50%;
			height: 50%;
			padding: 16px;
			border: 16px solid orange;
			background-color: white;
			z-index:1002;
			overflow: auto;
		}
	</style>
	</head>
	<body onload = "document.getElementById('light').style.display='block';document.getElementById('fade').style.display='block'">
		<div id="light" class="white_content">  <form action="index.php?p=login" method="post">
    <div class="heading1">Username</div>
    <div><input type="text" name="userName" style="width: 80%;" /></div>
    <div class="heading1 marginTop">Password</div>
    <div><input type="password" name="passWord" style="width: 80%;" /></div>
	<select name="database"><option value="--select--">--Select--</option><option value="senayan">Senayan</option><option value="senayan_2">Senayan 1</option></select>
    <div class="marginTop"><input type="submit" name="logMeIn" value="Logon" id="loginButton" />
        <input type="button" value="Home" id="homeButton" onClick="javascript: location.href = 'index.php';" />
    </div>
    </form>



</form></div>
		<div id="fade" class="black_overlay"></div>
	</body>
</html>
<?php
}
$fh = fopen("config.php", "w");
$string = "<?php define('DB_HOST', 'localhost');\n";
$string.= "define('DB_PORT', '3306');\n";
$string.= "define('DB_NAME', '$_POST[database]');\n";
$string.= "define('DB_USERNAME', 'root');\n";
$string.= "define('DB_PASSWORD', ''); ?>";
fwrite($fh, $string);
if($fh==false)
	die("unable to create file");
	
	?>