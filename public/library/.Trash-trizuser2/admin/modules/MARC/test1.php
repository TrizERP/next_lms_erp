<?php
require_once("connection.php"); 
?>
<html>
<form enctype="multipart/form-data" action="modules/MARC/upload_usmarc.php" method="post">
<?php echo "Upload" ?>: <input type="file" name="usmarc_data"><input type="submit" value="Upload" class="button"><br><br>
<b></b>
  
</form>
</html>
