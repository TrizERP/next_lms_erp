<?php
require_once("connection.php"); 
?>
<html>
<head>
<title>Upload Check</title>
</head>

<form name="form1" enctype="multipart/form-data" action="modules/bibliography/upload_usmarc.php" method="post">
<?php echo "Upload MRC File" ?>: <input type="file" name="usmarc_data" id="usmarc_data"><input type="submit" value="Upload" class="button"><br><br>
<b></b>
  
</form>
</html>
